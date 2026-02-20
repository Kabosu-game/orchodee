<?php
require_once 'config/database.php';

startSession();

// If already logged in, redirect
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect('admin/dashboard.php');
    } else {
        redirect('dashboard.php');
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = sanitize($_POST['first_name'] ?? '');
    $lastName = sanitize($_POST['last_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        try {
            $conn = getDBConnection();
            
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            if (!$stmt) {
                $error = 'Registration failed. Please try again.';
                $conn->close();
            } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $error = 'Email already registered. Please login instead.';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt2 = $conn->prepare("INSERT INTO users (first_name, last_name, email, phone, password) VALUES (?, ?, ?, ?, ?)");
                if ($stmt2) {
                    $stmt2->bind_param("sssss", $firstName, $lastName, $email, $phone, $hashedPassword);
                    if ($stmt2->execute()) {
                        $success = 'Registration successful! You can now login.';
                    } else {
                        $error = 'Registration failed. Please try again.';
                    }
                    $stmt2->close();
                } else {
                    $error = 'Registration failed. Please try again.';
                }
            }
            $stmt->close();
            $conn->close();
            }
        } catch (Exception $e) {
            // Log the error for debugging
            error_log("Registration error: " . $e->getMessage());
            
            if ($e->getMessage() === 'DATABASE_CONNECTION_ERROR') {
                $error = 'Database connection error. Please contact administrator.';
            } else {
                $error = 'Connection error. Please try again later.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Register - Orchidee LLC</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    
    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Inter:slnt,wght@-10..0,100..900&display=swap" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="img/orchideelogo.png">
    <link rel="apple-touch-icon" href="img/orchideelogo.png">
    
    <!-- Icon Font Stylesheet -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Libraries Stylesheet -->
    <link rel="stylesheet" href="lib/animate/animate.min.css"/>
    
    <!-- Customized Bootstrap Stylesheet -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
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
    <?php include 'includes/menu-simple.php'; ?>

    <!-- Header Start -->
    <div class="container-fluid bg-breadcrumb" style="background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('img/ban.jpeg') center/cover no-repeat;">
        <div class="container text-center py-5" style="max-width: 900px;">
            <h4 class="text-white display-4 mb-4">Registration</h4>
            <ol class="breadcrumb d-flex justify-content-center mb-0">
                <li class="breadcrumb-item"><a href="index.html" class="text-white">Home</a></li>
                <li class="breadcrumb-item active text-primary">Register</li>
            </ol>    
        </div>
    </div>
    <!-- Header End -->

    <!-- Register Start -->
    <div class="container-fluid bg-light py-5">
        <div class="container py-5">
            <!-- Registration Form -->
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="bg-white rounded p-5 shadow-sm">
                        <div class="text-center mb-4">
                            <img src="img/orchideelogo.png" alt="Orchidee Logo" style="height: 50px;" class="mb-3">
                            <h2 class="text-primary mb-2">Create Your Account</h2>
                            <p class="text-muted">Join Orchidee LLC and start your learning journey</p>
                        </div>
                        
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
                        
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-phone"></i></span>
                                    <input type="tel" class="form-control" id="phone" name="phone">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required minlength="6">
                                </div>
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-lock"></i></span>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-3 mb-3">
                                <i class="fa fa-user-plus me-2"></i>Create Account
                            </button>
                        </form>
                        
                        <div class="text-center">
                            <p class="mb-0">Already have an account? <a href="login.php" class="text-primary">Sign In</a></p>
                            <p class="mt-3"><a href="index.html" class="text-muted"><i class="fa fa-arrow-left me-2"></i>Back to Home</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Register End -->

    <?php include 'includes/footer.php'; ?>

    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="lib/wow/wow.min.js"></script>
    <script src="lib/easing/easing.min.js"></script>
    <script src="lib/waypoints/waypoints.min.js"></script>
    <script src="lib/counterup/counterup.min.js"></script>
    <script src="js/main.js"></script>
    
</body>
</html>

