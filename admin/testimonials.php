<?php
require_once '../includes/admin_check.php';

$conn = getDBConnection();

// Vérifier si la table testimonials existe, sinon la créer
$tableCheck = $conn->query("SHOW TABLES LIKE 'testimonials'");
if ($tableCheck->num_rows === 0) {
    $createTableSQL = "CREATE TABLE IF NOT EXISTS testimonials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        comment TEXT NOT NULL,
        rating INT DEFAULT 5,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY status (status),
        KEY created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($createTableSQL)) {
        die("Error creating testimonials table: " . $conn->error);
    }
}

$action = $_GET['action'] ?? 'list';
$testimonialId = $_GET['id'] ?? 0;
$filter = $_GET['filter'] ?? 'all';

// Gestion des actions
if ($action === 'approve' && $testimonialId) {
    $stmt = $conn->prepare("UPDATE testimonials SET status = 'approved' WHERE id = ?");
    $stmt->bind_param("i", $testimonialId);
    if ($stmt->execute()) {
        header("Location: testimonials.php?success=approved&filter=" . $filter);
        exit;
    }
    $stmt->close();
}

if ($action === 'reject' && $testimonialId) {
    $stmt = $conn->prepare("UPDATE testimonials SET status = 'rejected' WHERE id = ?");
    $stmt->bind_param("i", $testimonialId);
    if ($stmt->execute()) {
        header("Location: testimonials.php?success=rejected&filter=" . $filter);
        exit;
    }
    $stmt->close();
}

if ($action === 'delete' && $testimonialId) {
    $stmt = $conn->prepare("DELETE FROM testimonials WHERE id = ?");
    $stmt->bind_param("i", $testimonialId);
    if ($stmt->execute()) {
        header("Location: testimonials.php?success=deleted&filter=" . $filter);
        exit;
    }
    $stmt->close();
}

// Récupération des témoignages
$testimonials = [];
$statusFilter = $filter === 'all' ? '' : ($filter === 'pending' ? "WHERE status = 'pending'" : "WHERE status = '$filter'");
$query = "SELECT * FROM testimonials $statusFilter ORDER BY created_at DESC";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $testimonials[] = $row;
}

// Statistiques
$stats = [];
$result = $conn->query("SELECT COUNT(*) as total FROM testimonials");
$stats['total'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM testimonials WHERE status = 'pending'");
$stats['pending'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM testimonials WHERE status = 'approved'");
$stats['approved'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM testimonials WHERE status = 'rejected'");
$stats['rejected'] = $result->fetch_assoc()['total'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Testimonials Management - Orchidee LLC</title>
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
        .testimonial-card {
            border-left: 4px solid #007bff;
        }
        .testimonial-card.pending {
            border-left-color: #ffc107;
        }
        .testimonial-card.approved {
            border-left-color: #28a745;
        }
        .testimonial-card.rejected {
            border-left-color: #dc3545;
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
                            <i class="fa fa-comments me-2"></i>Testimonials Management
                        </h2>
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="fa fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
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
                            <a class="nav-link" href="services.php">
                                <i class="fa fa-cogs me-2"></i>Services
                            </a>
                            <a class="nav-link active" href="testimonials.php">
                                <i class="fa fa-comments me-2"></i>Testimonials
                            </a>
                            <a class="nav-link" href="users.php">
                                <i class="fa fa-users me-2"></i>Users
                            </a>
                        </nav>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="col-lg-9">
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php
                            $messages = [
                                'approved' => 'Testimonial approved successfully!',
                                'rejected' => 'Testimonial rejected successfully!',
                                'deleted' => 'Testimonial deleted successfully!'
                            ];
                            echo $messages[$_GET['success']] ?? 'Action completed successfully!';
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Statistics Cards -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="bg-white rounded p-3 shadow-sm text-center">
                                <h5 class="text-primary mb-1"><?php echo $stats['total']; ?></h5>
                                <small class="text-muted">Total</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-white rounded p-3 shadow-sm text-center">
                                <h5 class="text-warning mb-1"><?php echo $stats['pending']; ?></h5>
                                <small class="text-muted">Pending</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-white rounded p-3 shadow-sm text-center">
                                <h5 class="text-success mb-1"><?php echo $stats['approved']; ?></h5>
                                <small class="text-muted">Approved</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-white rounded p-3 shadow-sm text-center">
                                <h5 class="text-danger mb-1"><?php echo $stats['rejected']; ?></h5>
                                <small class="text-muted">Rejected</small>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="bg-white rounded p-3 shadow-sm mb-4">
                        <div class="btn-group" role="group">
                            <a href="testimonials.php?filter=all" class="btn btn-outline-primary <?php echo $filter === 'all' ? 'active' : ''; ?>">
                                All (<?php echo $stats['total']; ?>)
                            </a>
                            <a href="testimonials.php?filter=pending" class="btn btn-outline-warning <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                                Pending (<?php echo $stats['pending']; ?>)
                            </a>
                            <a href="testimonials.php?filter=approved" class="btn btn-outline-success <?php echo $filter === 'approved' ? 'active' : ''; ?>">
                                Approved (<?php echo $stats['approved']; ?>)
                            </a>
                            <a href="testimonials.php?filter=rejected" class="btn btn-outline-danger <?php echo $filter === 'rejected' ? 'active' : ''; ?>">
                                Rejected (<?php echo $stats['rejected']; ?>)
                            </a>
                        </div>
                    </div>

                    <!-- Testimonials List -->
                    <div class="bg-white rounded shadow-sm">
                        <?php if (empty($testimonials)): ?>
                            <div class="p-5 text-center">
                                <i class="fa fa-comments fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No testimonials found.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Rating</th>
                                            <th>Comment</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($testimonials as $testimonial): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($testimonial['name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                                </td>
                                                <td>
                                                    <small><?php echo htmlspecialchars($testimonial['email'], ENT_QUOTES, 'UTF-8'); ?></small>
                                                </td>
                                                <td>
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fa fa-star <?php echo $i <= $testimonial['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                                    <?php endfor; ?>
                                                </td>
                                                <td>
                                                    <small><?php echo htmlspecialchars(substr($testimonial['comment'], 0, 100)); ?><?php echo strlen($testimonial['comment']) > 100 ? '...' : ''; ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $testimonial['status'] === 'approved' ? 'success' : 
                                                            ($testimonial['status'] === 'rejected' ? 'danger' : 'warning'); 
                                                    ?>">
                                                        <?php echo ucfirst($testimonial['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small><?php echo date('M d, Y', strtotime($testimonial['created_at'])); ?></small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <?php if ($testimonial['status'] !== 'approved'): ?>
                                                            <a href="testimonials.php?action=approve&id=<?php echo $testimonial['id']; ?>&filter=<?php echo $filter; ?>" 
                                                               class="btn btn-success" title="Approve">
                                                                <i class="fa fa-check"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <?php if ($testimonial['status'] !== 'rejected'): ?>
                                                            <a href="testimonials.php?action=reject&id=<?php echo $testimonial['id']; ?>&filter=<?php echo $filter; ?>" 
                                                               class="btn btn-danger" title="Reject">
                                                                <i class="fa fa-times"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="testimonials.php?action=delete&id=<?php echo $testimonial['id']; ?>&filter=<?php echo $filter; ?>" 
                                                           class="btn btn-outline-danger" 
                                                           onclick="return confirm('Are you sure you want to delete this testimonial?');" 
                                                           title="Delete">
                                                            <i class="fa fa-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <!-- Expanded view for comment -->
                                            <tr class="table-light">
                                                <td colspan="7">
                                                    <div class="p-3">
                                                        <strong>Full Comment:</strong>
                                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($testimonial['comment'], ENT_QUOTES, 'UTF-8')); ?></p>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Admin Content End -->

    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/main.js"></script>
</body>
</html>

