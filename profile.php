<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once 'includes/auth_check.php';

$error = '';
$success = '';
$user = null;

try {
    $conn = getDBConnection();
    $userId = getUserId();

    // Get user information
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
    }
    $conn->close();
} catch (Exception $e) {
    error_log("Profile error: " . $e->getMessage());
    $error = 'Error loading profile. Please try again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $firstName = sanitize($_POST['first_name'] ?? '');
    $lastName = sanitize($_POST['last_name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    
    if (empty($firstName) || empty($lastName)) {
        $error = 'First name and last name are required.';
    } else {
        try {
            $conn = getDBConnection();
            
            if (!empty($newPassword)) {
                // Verify current password
                $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                $userPass = $result->fetch_assoc();
                
                if (!password_verify($currentPassword, $userPass['password'])) {
                    $error = 'Current password is incorrect.';
                } else {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, phone=?, password=? WHERE id=?");
                    $stmt->bind_param("ssssi", $firstName, $lastName, $phone, $hashedPassword, $userId);
                }
            } else {
                $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, phone=? WHERE id=?");
                $stmt->bind_param("sssi", $firstName, $lastName, $phone, $userId);
            }
            
            if (empty($error)) {
                if ($stmt->execute()) {
                    $_SESSION['user_name'] = $firstName . ' ' . $lastName;
                    $success = 'Profile updated successfully.';
                    // Reload user data
                    $stmt2 = $conn->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt2->bind_param("i", $userId);
                    $stmt2->execute();
                    $result2 = $stmt2->get_result();
                    $user = $result2->fetch_assoc();
                    $stmt2->close();
                } else {
                    $error = 'Failed to update profile.';
                }
            }
            if (isset($stmt)) $stmt->close();
            $conn->close();
        } catch (Exception $e) {
            $error = 'Error updating profile. Please try again.';
            error_log("Profile update error: " . $e->getMessage());
        }
    }
}

if (!$user) {
    $user = [
        'first_name' => $_SESSION['user_name'] ?? 'User',
        'last_name' => '',
        'email' => $_SESSION['user_email'] ?? '',
        'phone' => ''
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>My Profile - Orchidee LLC</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    
    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Inter:slnt,wght@-10..0,100..900&display=swap" rel="stylesheet">
    
    <!-- Icon Font Stylesheet -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"/>
    
    <!-- Customized Bootstrap Stylesheet -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: calc(100vh - 200px);
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
    <!-- Spinner Start -->
    <div id="spinner" class="bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center" style="display: none !important;">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>
    <!-- Spinner End -->

    <!-- Menu -->
    <?php include 'includes/menu-dynamic.php'; ?>

    <!-- Profile Start -->
    <div class="container-fluid bg-light py-5">
        <div class="container py-5">
            <div class="row">
                <!-- Sidebar -->
                <div class="col-lg-3 mb-4">
                    <div class="bg-white rounded p-4 shadow-sm sidebar">
                        <div class="text-center mb-4">
                            <div class="bg-primary rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                                <i class="fa fa-user fa-2x text-white"></i>
                            </div>
                            <h5 class="mt-3 mb-1"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h5>
                            <p class="text-muted small mb-0"><?php echo htmlspecialchars($_SESSION['user_email']); ?></p>
                        </div>
                        <hr>
                        <nav class="nav flex-column">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fa fa-home me-2"></i>My Dashboard
                            </a>
                            <a class="nav-link" href="courses.php">
                                <i class="fa fa-book me-2"></i>Browse Courses
                            </a>
                            <a class="nav-link" href="my-courses.php">
                                <i class="fa fa-play-circle me-2"></i>My Courses
                            </a>
                            <a class="nav-link active" href="profile.php">
                                <i class="fa fa-user-circle me-2"></i>My Profile
                            </a>
                            <a class="nav-link" href="logout.php">
                                <i class="fa fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </nav>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="col-lg-9">
                    <div class="bg-white rounded p-4 shadow-sm">
                        <h3 class="text-primary mb-4">
                            <i class="fa fa-user-circle me-2"></i>My Profile
                        </h3>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fa fa-exclamation-circle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fa fa-check-circle me-2"></i><?php echo $success; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" required value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" required value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled>
                                <small class="text-muted">Email cannot be changed</small>
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                            
                            <hr class="my-4">
                            <h5 class="mb-3">Change Password</h5>
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                                <small class="text-muted">Leave blank if you don't want to change password</small>
                            </div>
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" minlength="6">
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-save me-2"></i>Update Profile
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Profile End -->

    <?php include 'includes/footer.php'; ?>

    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
    <script>
        // Force spinner to disappear
        $(document).ready(function() {
            setTimeout(function() {
                $('#spinner').removeClass('show').hide();
            }, 500);
        });
    </script>
</body>
</html>
