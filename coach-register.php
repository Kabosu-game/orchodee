<?php
require_once 'config/database.php';

startSession();
if (isLoggedIn()) {
    if (isCoach()) {
        redirect('coach/dashboard.php');
    }
    redirect('dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $conn = getDBConnection();
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            if (!$stmt) {
                $error = 'Registration failed. Please try again.';
            } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows > 0) {
                $error = 'This email is already registered.';
                $stmt->close();
            } else {
                $stmt->close();
                $role = 'coach';
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, ?)");
                if (!$ins) {
                    $error = 'Registration failed. Please try again.';
                    $conn->close();
                } elseif ($ins->execute()) {
                    $ins->close();
                    $conn->close();
                    $success = 'Registration successful. You can now log in.';
                    $_POST = [];
                } else {
                    $error = 'Registration failed. Please try again.';
                    $ins->close();
                    $conn->close();
                }
            }
            }
        } catch (Exception $e) {
            $error = 'An error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Coach Registration - Orchidee LLC</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"/>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/menu-simple.php'; ?>
    <div class="container-fluid bg-light py-5">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-lg-5">
                    <div class="bg-white rounded shadow-lg p-5">
                        <h2 class="text-primary mb-4"><i class="fa fa-user-plus me-2"></i>Coach Registration</h2>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?>
                                <a href="login.php" class="alert-link">Log in here</a>.
                            </div>
                        <?php else: ?>
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">First Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="password" required minlength="6">
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" name="password_confirm" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-2">Register as Coach</button>
                        </form>
                        <?php endif; ?>
                        <p class="mt-3 text-center text-muted small">
                            Already have an account? <a href="login.php">Log in</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
