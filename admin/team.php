<?php
require_once '../includes/admin_check.php';

$conn = getDBConnection();

// Vérifier si la table team_members existe, sinon la créer
$tableCheck = $conn->query("SHOW TABLES LIKE 'team_members'");
if ($tableCheck->num_rows === 0) {
    $createTableSQL = "CREATE TABLE IF NOT EXISTS team_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        position VARCHAR(200) NOT NULL,
        description TEXT DEFAULT NULL,
        email VARCHAR(255) DEFAULT NULL,
        phone VARCHAR(50) DEFAULT NULL,
        photo VARCHAR(255) DEFAULT NULL,
        facebook_url VARCHAR(255) DEFAULT NULL,
        twitter_url VARCHAR(255) DEFAULT NULL,
        linkedin_url VARCHAR(255) DEFAULT NULL,
        instagram_url VARCHAR(255) DEFAULT NULL,
        display_order INT DEFAULT 0,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY status (status),
        KEY display_order (display_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($createTableSQL)) {
        die("Error creating team_members table: " . $conn->error);
    }
}

$action = $_GET['action'] ?? 'list';
$memberId = $_GET['id'] ?? 0;

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'edit') {
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        // Normaliser position : une par ligne, lignes vides supprimées
$positionRaw = $_POST['position'] ?? '';
$positionLines = array_filter(array_map('trim', explode("\n", str_replace("\r", '', $positionRaw))));
$position = implode("\n", $positionLines);
$position = sanitize($position);
        $description = $_POST['description'] ?? '';
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $facebookUrl = sanitize($_POST['facebook_url'] ?? '');
        $twitterUrl = sanitize($_POST['twitter_url'] ?? '');
        $linkedinUrl = sanitize($_POST['linkedin_url'] ?? '');
        $instagramUrl = sanitize($_POST['instagram_url'] ?? '');
        $displayOrder = intval($_POST['display_order'] ?? 0);
        $status = sanitize($_POST['status'] ?? 'active');
        
        // Handle photo upload
        $photo = '';
        if ($action === 'edit' && $memberId) {
            // Récupérer la photo existante
            $stmt = $conn->prepare("SELECT photo FROM team_members WHERE id = ?");
            $stmt->bind_param("i", $memberId);
            $stmt->execute();
            $result = $stmt->get_result();
            $existingMember = $result->fetch_assoc();
            $photo = $existingMember['photo'] ?? '';
            $stmt->close();
        }
        
        // Si une nouvelle photo est uploadée, la remplacer
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
            $uploadDir = '../uploads/team/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileName = time() . '_' . basename($_FILES['photo']['name']);
            $targetFile = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $targetFile)) {
                $photo = 'uploads/team/' . $fileName;
            }
        }
        
        if ($action === 'add') {
            if ($photo) {
                $stmt = $conn->prepare("INSERT INTO team_members (first_name, last_name, position, description, email, phone, photo, facebook_url, twitter_url, linkedin_url, instagram_url, display_order, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssssssssis", $firstName, $lastName, $position, $description, $email, $phone, $photo, $facebookUrl, $twitterUrl, $linkedinUrl, $instagramUrl, $displayOrder, $status);
            } else {
                $stmt = $conn->prepare("INSERT INTO team_members (first_name, last_name, position, description, email, phone, facebook_url, twitter_url, linkedin_url, instagram_url, display_order, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssssssis", $firstName, $lastName, $position, $description, $email, $phone, $facebookUrl, $twitterUrl, $linkedinUrl, $instagramUrl, $displayOrder, $status);
            }
        } else {
            if ($photo) {
                $stmt = $conn->prepare("UPDATE team_members SET first_name=?, last_name=?, position=?, description=?, email=?, phone=?, photo=?, facebook_url=?, twitter_url=?, linkedin_url=?, instagram_url=?, display_order=?, status=? WHERE id=?");
                $stmt->bind_param("sssssssssssisi", $firstName, $lastName, $position, $description, $email, $phone, $photo, $facebookUrl, $twitterUrl, $linkedinUrl, $instagramUrl, $displayOrder, $status, $memberId);
            } else {
                $stmt = $conn->prepare("UPDATE team_members SET first_name=?, last_name=?, position=?, description=?, email=?, phone=?, facebook_url=?, twitter_url=?, linkedin_url=?, instagram_url=?, display_order=?, status=? WHERE id=?");
                $stmt->bind_param("ssssssssssisi", $firstName, $lastName, $position, $description, $email, $phone, $facebookUrl, $twitterUrl, $linkedinUrl, $instagramUrl, $displayOrder, $status, $memberId);
            }
        }
        
        if ($stmt->execute()) {
            header("Location: team.php?success=" . ($action === 'add' ? 'added' : 'updated'));
            exit;
        } else {
            $error = "Error saving team member: " . $conn->error;
        }
        $stmt->close();
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM team_members WHERE id = ?");
        $stmt->bind_param("i", $memberId);
        if ($stmt->execute()) {
            header("Location: team.php?success=deleted");
            exit;
        }
        $stmt->close();
    }
}

// Récupération des données
$member = null;
if ($action === 'edit' && $memberId) {
    $stmt = $conn->prepare("SELECT * FROM team_members WHERE id = ?");
    $stmt->bind_param("i", $memberId);
    $stmt->execute();
    $result = $stmt->get_result();
    $member = $result->fetch_assoc();
    $stmt->close();
}

// Liste des membres
$members = [];
$result = $conn->query("SELECT * FROM team_members ORDER BY display_order ASC, created_at DESC");
while ($row = $result->fetch_assoc()) {
    $members[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Team Management - Orchidee LLC</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    
    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Inter:slnt,wght@-10..0,100..900&display=swap" rel="stylesheet">
    
    <!-- Icon Font Stylesheet -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"/>
    
    <!-- Customized Bootstrap Stylesheet -->
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    
    <!-- Summernote - Éditeur riche gratuit (open source) -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs5.min.css" rel="stylesheet">
    
    <style>
        .sidebar {
            min-height: calc(100vh - 100px);
            background: #f8f9fa;
            border-right: 1px solid #dee2e6;
        }
        .sidebar .nav-link {
            color: #495057;
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 5px;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: #007bff;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Menu -->
    <?php include '../includes/menu-dynamic.php'; ?>

    <!-- Admin Content Start -->
    <div class="container-fluid bg-light py-5">
        <div class="container py-5">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="text-primary mb-0">
                            <i class="fa fa-users me-2"></i>Team Management
                        </h2>
                        <div>
                            <a href="dashboard.php" class="btn btn-outline-secondary me-2">
                                <i class="fa fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                            <a href="team.php?action=add" class="btn btn-primary">
                                <i class="fa fa-plus me-2"></i>Add Team Member
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Sidebar -->
                <div class="col-lg-3 mb-4">
                    <div class="bg-white rounded p-4 shadow-sm sidebar">
                        <nav class="nav flex-column">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fa fa-home me-2"></i>Dashboard
                            </a>
                            <a class="nav-link" href="courses.php">
                                <i class="fa fa-book me-2"></i>Manage Courses
                            </a>
                            <a class="nav-link" href="blog.php">
                                <i class="fa fa-blog me-2"></i>Blog Management
                            </a>
                            <a class="nav-link" href="webinars.php">
                                <i class="fa fa-video me-2"></i>Webinars
                            </a>
                            <a class="nav-link" href="sessions.php">
                                <i class="fa fa-calendar-check me-2"></i>NCLEX Sessions
                            </a>
                            <a class="nav-link active" href="team.php">
                                <i class="fa fa-users me-2"></i>Team Members
                            </a>
                            <a class="nav-link" href="users.php">
                                <i class="fa fa-user-friends me-2"></i>Users
                            </a>
                        </nav>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="col-lg-9">
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            Team member <?php echo $_GET['success']; ?> successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($action === 'add' || $action === 'edit'): ?>
                        <!-- Add/Edit Form -->
                        <div class="bg-white rounded p-5 shadow-sm">
                            <h4 class="mb-4"><?php echo $action === 'add' ? 'Add New' : 'Edit'; ?> Team Member</h4>
                            
                            <form method="POST" enctype="multipart/form-data">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="first_name" class="form-label">First Name *</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($member['first_name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="last_name" class="form-label">Last Name *</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($member['last_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="position" class="form-label">Position/Title *</label>
                                    <textarea class="form-control" id="position" name="position" rows="4" placeholder="One position per line&#10;E.g.: NCLEX Instructor&#10;Curriculum Developer&#10;Student Mentor" required><?php echo htmlspecialchars($member['position'] ?? ''); ?></textarea>
                                    <small class="text-muted">One position per line. Enter each title on a new line.</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description/Bio *</label>
                                    <textarea class="form-control" id="description" name="description" rows="8" required><?php echo htmlspecialchars($member['description'] ?? ''); ?></textarea>
                                    <small class="text-muted">Detailed bio with formatting: bold, italic, lists, links. Use the editor for advanced formatting.</small>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($member['email'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Phone</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($member['phone'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="photo" class="form-label">Photo</label>
                                    <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                                    <?php if (!empty($member['photo'])): ?>
                                        <div class="mt-2">
                                            <img src="../<?php echo htmlspecialchars($member['photo']); ?>" alt="Current photo" class="img-thumbnail" style="max-width: 200px;">
                                            <p class="small text-muted mt-1">Current photo</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <h5 class="mt-4 mb-3">Social Media Links</h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="facebook_url" class="form-label">Facebook URL</label>
                                        <input type="url" class="form-control" id="facebook_url" name="facebook_url" value="<?php echo htmlspecialchars($member['facebook_url'] ?? ''); ?>" placeholder="https://facebook.com/...">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="twitter_url" class="form-label">Twitter URL</label>
                                        <input type="url" class="form-control" id="twitter_url" name="twitter_url" value="<?php echo htmlspecialchars($member['twitter_url'] ?? ''); ?>" placeholder="https://twitter.com/...">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="linkedin_url" class="form-label">LinkedIn URL</label>
                                        <input type="url" class="form-control" id="linkedin_url" name="linkedin_url" value="<?php echo htmlspecialchars($member['linkedin_url'] ?? ''); ?>" placeholder="https://linkedin.com/in/...">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="instagram_url" class="form-label">Instagram URL</label>
                                        <input type="url" class="form-control" id="instagram_url" name="instagram_url" value="<?php echo htmlspecialchars($member['instagram_url'] ?? ''); ?>" placeholder="https://instagram.com/...">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="display_order" class="form-label">Display Order</label>
                                        <input type="number" class="form-control" id="display_order" name="display_order" value="<?php echo $member['display_order'] ?? 0; ?>" min="0">
                                        <small class="text-muted">Lower numbers appear first</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="active" <?php echo ($member['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo ($member['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <a href="team.php" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-save me-2"></i>Save Team Member
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- List View -->
                        <div class="bg-white rounded p-4 shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4 class="mb-0">All Team Members</h4>
                                <a href="team.php?action=add" class="btn btn-primary btn-sm">
                                    <i class="fa fa-plus me-2"></i>Add New
                                </a>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Photo</th>
                                            <th>Name</th>
                                            <th>Position</th>
                                            <th>Order</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($members)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-5">
                                                    <i class="fa fa-users fa-3x mb-3 d-block"></i>
                                                    No team members yet. <a href="team.php?action=add">Add your first team member</a>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($members as $m): ?>
                                                <tr>
                                                    <td>
                                                        <?php if (!empty($m['photo'])): ?>
                                                            <img src="../<?php echo htmlspecialchars($m['photo']); ?>" alt="<?php echo htmlspecialchars($m['first_name']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                                                        <?php else: ?>
                                                            <div style="width: 50px; height: 50px; background: #ddd; border-radius: 5px; display: flex; align-items: center; justify-content: center;">
                                                                <i class="fa fa-user text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($m['first_name'] . ' ' . $m['last_name']); ?></td>
                                                    <td><?php echo nl2br(htmlspecialchars($m['position'])); ?></td>
                                                    <td><?php echo $m['display_order']; ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $m['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                            <?php echo ucfirst($m['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="team.php?action=edit&id=<?php echo $m['id']; ?>" class="btn btn-sm btn-primary">
                                                            <i class="fa fa-edit"></i>
                                                        </a>
                                                        <a href="team.php?action=delete&id=<?php echo $m['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this team member?')">
                                                            <i class="fa fa-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <!-- Admin Content End -->

    <!-- Footer Start -->
    <div class="container-fluid footer py-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                    <a href="../index.html" class="p-0">
                        <img src="../img/orchideelogo.png" alt="Orchidee LLC" style="height: 40px;">
                    </a>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="text-white-50 mb-0 small">
                        <i class="fas fa-copyright me-1"></i> 2025 Orchidee LLC. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </div>
    <!-- Footer End -->

    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/main.js"></script>
    <?php if ($action === 'add' || $action === 'edit'): ?>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-bs5.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof $.fn.summernote !== 'undefined' && document.getElementById('description')) {
            $('#description').summernote({
                height: 300,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'italic', 'underline', 'clear']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['insert', ['link']],
                    ['view', ['codeview']]
                ],
                placeholder: 'Write the bio with formatting: bold, italic, lists, links...'
            });
        }
    });
    </script>
    <?php endif; ?>
</body>
</html>

