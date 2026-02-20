<?php
// Determine relative path based on page location
$scriptPath = $_SERVER['PHP_SELF'] ?? '';
$isAdminPage = (strpos($scriptPath, '/admin/') !== false);
$isCoachPage = (strpos($scriptPath, '/coach/') !== false);
$basePath = ($isAdminPage || $isCoachPage) ? '../' : '';

// Try to load config, otherwise use simple menu
$isLoggedIn = false;
$isAdmin = false;
$userName = '';

// Start session without error
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Check session directly without loading DB config
// This avoids errors if DB is not accessible
$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
$isCoach = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'coach';
$userName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
?>
<!-- Navbar & Hero Start -->
<div class="container-fluid nav-bar px-0 px-lg-4 py-lg-0">
    <div class="container">
        <nav class="navbar navbar-expand-lg navbar-light"> 
            <a href="<?php echo $basePath; ?>index.php" class="navbar-brand p-0">
                <img src="<?php echo $basePath; ?>img/orchideelogo.png" alt="Orchidee Logo">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                <span class="fa fa-bars"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarCollapse">
                <div class="navbar-nav mx-0 mx-lg-auto">
                    <a href="<?php echo $basePath; ?>index.php" class="nav-item nav-link" data-page="home">Home</a>
                    <a href="<?php echo $basePath; ?>courses.php" class="nav-item nav-link" data-page="courses">Courses</a>
                    <div class="nav-item dropdown">
                        <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" data-page="registration">Registration</a>
                        <div class="dropdown-menu">
                            <a href="<?php echo $basePath; ?>registration-next-session.php" class="dropdown-item">Registration for the Next Session</a>
                            <a href="<?php echo $basePath; ?>nclex-registration-form.php" class="dropdown-item">NCLEX Registration Form</a>
                        </div>
                    </div>
                    <a href="<?php echo $basePath; ?>consultation.html" class="nav-item nav-link" data-page="consultation">Book a Consultation</a>
                    <a href="<?php echo $basePath; ?>about.php" class="nav-item nav-link" data-page="about">About Us</a>
                    <a href="<?php echo $basePath; ?>blog.php" class="nav-item nav-link" data-page="blog">Blog</a>
                    <a href="<?php echo $basePath; ?>contact.html" class="nav-item nav-link" data-page="contact">Contact</a>
                </div>
                <!-- Login/Register buttons for mobile -->
                <div class="d-flex d-xl-none flex-column px-3 py-2" style="gap: 0.5rem;">
                    <?php if ($isLoggedIn): ?>
                        <div class="dropdown">
                            <button class="btn btn-primary w-100 dropdown-toggle" type="button" id="userMenuMobile" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa fa-user me-2"></i><?php echo htmlspecialchars($userName); ?>
                            </button>
                            <ul class="dropdown-menu w-100" aria-labelledby="userMenuMobile">
                                <?php if ($isCoach): ?>
                                    <li><a class="dropdown-item" href="<?php echo $basePath; ?>coach/dashboard.php"><i class="fa fa-tachometer-alt me-2"></i>Coach Dashboard</a></li>
                                    <li><a class="dropdown-item" href="<?php echo $basePath; ?>my-coaching.php"><i class="fa fa-video me-2"></i>My Coaching</a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="<?php echo $basePath; ?>dashboard.php"><i class="fa fa-home me-2"></i>My Dashboard</a></li>
                                    <li><a class="dropdown-item" href="<?php echo $basePath; ?>my-courses.php"><i class="fa fa-play-circle me-2"></i>My Courses</a></li>
                                    <li><a class="dropdown-item" href="<?php echo $basePath; ?>profile.php"><i class="fa fa-user-circle me-2"></i>My Profile</a></li>
                                <?php endif; ?>
                                <?php if ($isAdmin): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?php echo $basePath; ?>admin/dashboard.php"><i class="fa fa-cog me-2"></i>Admin Panel</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo $basePath; ?>logout.php"><i class="fa fa-sign-out-alt me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="<?php echo $basePath; ?>login.php" class="btn btn-outline-primary w-100">
                            <i class="fa fa-sign-in-alt me-2"></i>Login
                        </a>
                        <a href="<?php echo $basePath; ?>register.php" class="btn btn-primary w-100">
                            <i class="fa fa-user-plus me-2"></i>Register
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-none d-xl-flex flex-shrink-0 ps-4 align-items-center">
                <?php if ($isLoggedIn): ?>
                    <!-- Logged in user menu -->
                    <div class="dropdown me-3">
                        <button class="btn btn-primary dropdown-toggle" type="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fa fa-user me-2"></i><?php echo htmlspecialchars($userName); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
                            <?php if ($isCoach): ?>
                                <li><a class="dropdown-item" href="<?php echo $basePath; ?>coach/dashboard.php"><i class="fa fa-tachometer-alt me-2"></i>Coach Dashboard</a></li>
                                <li><a class="dropdown-item" href="<?php echo $basePath; ?>my-coaching.php"><i class="fa fa-video me-2"></i>My Coaching</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="<?php echo $basePath; ?>dashboard.php"><i class="fa fa-home me-2"></i>My Dashboard</a></li>
                                <li><a class="dropdown-item" href="<?php echo $basePath; ?>my-courses.php"><i class="fa fa-play-circle me-2"></i>My Courses</a></li>
                                <li><a class="dropdown-item" href="<?php echo $basePath; ?>profile.php"><i class="fa fa-user-circle me-2"></i>My Profile</a></li>
                            <?php endif; ?>
                            <?php if ($isAdmin): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo $basePath; ?>admin/dashboard.php"><i class="fa fa-cog me-2"></i>Admin Panel</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo $basePath; ?>logout.php"><i class="fa fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <!-- Not logged in user menu -->
                    <a href="<?php echo $basePath; ?>login.php" class="btn btn-outline-primary me-2">
                        <i class="fa fa-sign-in-alt me-2"></i>Login
                    </a>
                    <a href="<?php echo $basePath; ?>register.php" class="btn btn-primary">
                        <i class="fa fa-user-plus me-2"></i>Register
                    </a>
                <?php endif; ?>
            </div>
        </nav>
    </div>
</div>
<!-- Navbar & Hero End -->

