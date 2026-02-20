<?php
require_once 'config/database.php';

startSession();

// If already logged in, redirect
if (isLoggedIn()) {
    $base = buildBasePath();
    if (isAdmin()) {
        redirect($base . 'admin/dashboard.php');
    } elseif (isCoach()) {
        redirect($base . 'coach/dashboard.php');
    } else {
        redirect($base . 'dashboard.php');
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!empty($email) && !empty($password)) {
        try {
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT id, first_name, last_name, email, password, role FROM users WHERE email = ?");
            if (!$stmt) {
                $conn->close();
                $error = 'Erreur base de données. Vérifiez config/database_production.php et l\'import SQL.';
            } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    $role = trim(strtolower((string)($user['role'] ?? 'user')));
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_role'] = $role;
                    
                    if ($role === 'admin') {
                        redirect(buildBasePath() . 'admin/dashboard.php');
                    } elseif ($role === 'coach') {
                        redirect(buildBasePath() . 'coach/dashboard.php');
                    } else {
                        redirect(buildBasePath() . 'dashboard.php');
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
            } else {
                $error = 'Invalid email or password.';
            }
            $stmt->close();
            $conn->close();
            }
        } catch (Exception $e) {
            if ($e->getMessage() === 'DATABASE_NOT_FOUND') {
                $error = 'Database not found. Please run setup-database.php first.';
            } else {
                $error = 'Connection error. Please try again later.';
            }
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Login - Orchidee LLC</title>
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

    <!-- Login Start -->
    <div class="container-fluid bg-light py-5">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-md-6 col-lg-5">
                    <div class="bg-white rounded p-5 shadow-sm">
                        <div class="text-center mb-4">
                            <img src="img/orchideelogo.png" alt="Orchidee Logo" style="height: 50px;" class="mb-3">
                            <h2 class="text-primary mb-2">Welcome Back</h2>
                            <p class="text-muted">Sign in to access your courses</p>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fa fa-exclamation-circle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="remember">
                                <label class="form-check-label" for="remember">Remember me</label>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-3 mb-3">
                                <i class="fa fa-sign-in-alt me-2"></i>Sign In
                            </button>
                        </form>
                        
                        <div class="text-center">
                            <p class="mb-0">Don't have an account? <a href="register.php" class="text-primary">Sign Up</a></p>
                            <p class="mt-3"><a href="index.html" class="text-muted"><i class="fa fa-arrow-left me-2"></i>Back to Home</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Login End -->

    <?php include 'includes/footer.php'; ?>

    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>

