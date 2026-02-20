<?php
require_once '../includes/admin_check.php';

$conn = getDBConnection();
$action = $_GET['action'] ?? 'list';
$userId = $_GET['id'] ?? 0;

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'edit') {
        $firstName = sanitize($_POST['first_name'] ?? '');
        $lastName = sanitize($_POST['last_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $role = sanitize($_POST['role'] ?? 'user');
        $password = $_POST['password'] ?? '';
        
        // Vérifier si l'email existe déjà pour un autre utilisateur
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $checkStmt->bind_param("si", $email, $userId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            $error = "Email already exists for another user.";
        } else {
            if (!empty($password)) {
                // Mettre à jour avec le nouveau mot de passe
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=?, role=?, password=? WHERE id=?");
                $stmt->bind_param("ssssssi", $firstName, $lastName, $email, $phone, $role, $hashedPassword, $userId);
            } else {
                // Mettre à jour sans changer le mot de passe
                $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, email=?, phone=?, role=? WHERE id=?");
                $stmt->bind_param("sssssi", $firstName, $lastName, $email, $phone, $role, $userId);
            }
            
            if ($stmt->execute()) {
                header("Location: users.php?success=updated");
                exit;
            } else {
                $error = "Error updating user: " . $conn->error;
            }
            $stmt->close();
        }
        $checkStmt->close();
    } elseif ($action === 'delete') {
        // Empêcher la suppression de l'utilisateur actuellement connecté
        $currentUserId = getUserId();
        if ($userId == $currentUserId) {
            $error = "You cannot delete your own account.";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $userId);
            if ($stmt->execute()) {
                header("Location: users.php?success=deleted");
                exit;
            } else {
                $error = "Error deleting user.";
            }
            $stmt->close();
        }
    }
}

// Récupération des données
$user = null;
if ($action === 'edit' && $userId) {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
}

// Liste des utilisateurs avec statistiques
$users = [];
$result = $conn->query("
    SELECT u.*, 
    (SELECT COUNT(*) FROM purchases WHERE user_id = u.id) as purchases_count,
    (SELECT COUNT(*) FROM webinar_registrations WHERE user_id = u.id) as webinar_registrations_count
    FROM users u 
    ORDER BY u.created_at DESC
");
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>User Management - Orchidee LLC</title>
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
                            <i class="fa fa-users me-2"></i>User Management
                        </h2>
                        <div>
                            <a href="dashboard.php" class="btn btn-outline-secondary me-2">
                                <i class="fa fa-arrow-left me-2"></i>Back to Dashboard
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
                            <a class="nav-link" href="team.php">
                                <i class="fa fa-users me-2"></i>Team Members
                            </a>
                            <a class="nav-link active" href="users.php">
                                <i class="fa fa-user-friends me-2"></i>Users
                            </a>
                            <a class="nav-link" href="payment-settings.php">
                                <i class="fa fa-credit-card me-2"></i>Payment Settings
                            </a>
                        </nav>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="col-lg-9">
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            User <?php echo $_GET['success']; ?> successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($action === 'edit'): ?>
                        <!-- Edit Form -->
                        <div class="bg-white rounded p-4 shadow-sm">
                            <h4 class="mb-4">Edit User</h4>
                            
                            <form method="POST">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="first_name" class="form-label">First Name *</label>
                                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars(($user ?? [])['first_name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="last_name" class="form-label">Last Name *</label>
                                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars(($user ?? [])['last_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars(($user ?? [])['email'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars(($user ?? [])['phone'] ?? ''); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="role" class="form-label">Role *</label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="user" <?php echo (($user ?? [])['role'] ?? 'user') === 'user' ? 'selected' : ''; ?>>User</option>
                                        <option value="admin" <?php echo (($user ?? [])['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                        <option value="coach" <?php echo (($user ?? [])['role'] ?? '') === 'coach' ? 'selected' : ''; ?>>Coach</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Leave blank to keep current password">
                                    <small class="text-muted">Only fill this if you want to change the password</small>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <a href="users.php" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-save me-2"></i>Update User
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- List View -->
                        <div class="bg-white rounded p-4 shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4 class="mb-0">All Users</h4>
                                <div>
                                    <span class="badge bg-primary me-2">Total: <?php echo count($users); ?></span>
                                    <span class="badge bg-success">Admins: <?php echo count(array_filter($users, fn($u) => $u['role'] === 'admin')); ?></span>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Phone</th>
                                            <th>Role</th>
                                            <th>Purchases</th>
                                            <th>Webinars</th>
                                            <th>Registered</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($users)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-5">
                                                    <i class="fa fa-users fa-3x mb-3 d-block"></i>
                                                    No users found
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($users as $u): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></strong>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                                    <td><?php echo htmlspecialchars($u['phone'] ?? '-'); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $u['role'] === 'admin' ? 'danger' : ($u['role'] === 'coach' ? 'info' : 'primary'); ?>">
                                                            <?php echo ucfirst($u['role']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $u['purchases_count'] ?? 0; ?></td>
                                                    <td><?php echo $u['webinar_registrations_count'] ?? 0; ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                                    <td>
                                                        <a href="users.php?action=edit&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                                            <i class="fa fa-edit"></i>
                                                        </a>
                                                        <?php if ($u['id'] != getUserId()): ?>
                                                            <a href="users.php?action=delete&id=<?php echo $u['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                                                <i class="fa fa-trash"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="btn btn-sm btn-secondary disabled" title="Cannot delete your own account">
                                                                <i class="fa fa-lock"></i>
                                                            </span>
                                                        <?php endif; ?>
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
</body>
</html>



