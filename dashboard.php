<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors on page, but log them

require_once 'config/database.php';
startSession();
if (!isLoggedIn()) { redirect('login.php'); }
if (isCoach()) { redirect('coach/dashboard.php'); }
if (isAdmin()) { redirect('admin/dashboard.php'); }

$purchasedCourses = [];
$progressData = [];

try {
    $conn = getDBConnection();
    $userId = getUserId();

    // Get purchased courses by user
    $stmt = $conn->prepare("
        SELECT c.*, p.purchased_at, p.payment_status 
        FROM purchases p 
        JOIN courses c ON p.course_id = c.id 
        WHERE p.user_id = ? AND p.payment_status = 'completed'
        ORDER BY p.purchased_at DESC
    ");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $purchasedCourses[] = $row;
        }
        $stmt->close();
    }

    // Get overall progress
    $progressStmt = $conn->prepare("
        SELECT course_id, AVG(progress_percentage) as avg_progress 
        FROM user_progress 
        WHERE user_id = ? 
        GROUP BY course_id
    ");
    if ($progressStmt) {
        $progressStmt->bind_param("i", $userId);
        $progressStmt->execute();
        $progressResult = $progressStmt->get_result();
        while ($row = $progressResult->fetch_assoc()) {
            $progressData[$row['course_id']] = $row['avg_progress'];
        }
        $progressStmt->close();
    }
    
    $conn->close();
} catch (Throwable $e) {
    // On error, continue with empty arrays (catch Exception + Error)
    error_log("Dashboard error: " . $e->getMessage());
    $purchasedCourses = [];
    $progressData = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>My Dashboard - Orchidee LLC</title>
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
        .course-card {
            transition: transform 0.3s;
        }
        .course-card:hover {
            transform: translateY(-5px);
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

    <!-- Dashboard Start -->
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
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fa fa-home me-2"></i>My Dashboard
                            </a>
                            <a class="nav-link" href="courses.php">
                                <i class="fa fa-book me-2"></i>Browse Courses
                            </a>
                            <a class="nav-link" href="my-courses.php">
                                <i class="fa fa-play-circle me-2"></i>My Courses
                            </a>
                            <a class="nav-link" href="payment-history.php">
                                <i class="fa fa-history me-2"></i>Payment History
                            </a>
                            <a class="nav-link" href="profile.php">
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
                    <div class="bg-white rounded p-4 shadow-sm mb-4">
                        <h3 class="text-primary mb-4">
                            <i class="fa fa-tachometer-alt me-2"></i>My Dashboard
                        </h3>
                        
                        <!-- Stats -->
                        <div class="row g-4 mb-4">
                            <div class="col-md-4">
                                <div class="bg-primary text-white rounded p-4 text-center">
                                    <i class="fa fa-book fa-3x mb-3"></i>
                                    <h2 class="mb-0"><?php echo count($purchasedCourses); ?></h2>
                                    <p class="mb-0">Courses Purchased</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="bg-success text-white rounded p-4 text-center">
                                    <i class="fa fa-check-circle fa-3x mb-3"></i>
                                    <h2 class="mb-0"><?php 
                                        $completed = 0;
                                        if (is_array($progressData)) {
                                            foreach ($progressData as $progress) {
                                                if ($progress >= 100) $completed++;
                                            }
                                        }
                                        echo $completed;
                                    ?></h2>
                                    <p class="mb-0">Completed</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="bg-info text-white rounded p-4 text-center">
                                    <i class="fa fa-clock fa-3x mb-3"></i>
                                    <h2 class="mb-0"><?php echo count($purchasedCourses) - (isset($completed) ? $completed : 0); ?></h2>
                                    <p class="mb-0">In Progress</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- My Courses -->
                    <div class="bg-white rounded p-4 shadow-sm">
                        <h4 class="text-primary mb-4">
                            <i class="fa fa-graduation-cap me-2"></i>My Courses
                        </h4>
                        
                        <?php if (empty($purchasedCourses)): ?>
                            <div class="text-center py-5">
                                <i class="fa fa-book-open fa-4x text-muted mb-3"></i>
                                <h5 class="text-muted">No courses yet</h5>
                                <p class="text-muted">Start learning by browsing our available courses</p>
                                <a href="courses.php" class="btn btn-primary">
                                    <i class="fa fa-search me-2"></i>Browse Courses
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="row g-4">
                                <?php foreach ($purchasedCourses as $course): 
                                    $progress = isset($progressData[$course['id']]) ? $progressData[$course['id']] : 0;
                                    $image = !empty($course['image']) ? $course['image'] : 'img/carousel-2.png';
                                ?>
                                    <div class="col-md-6">
                                        <div class="card course-card h-100">
                                            <img src="<?php echo htmlspecialchars($image); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($course['title']); ?>" style="height: 200px; object-fit: cover;">
                                            <div class="card-body">
                                                <h5 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                                                <p class="card-text text-muted small"><?php echo htmlspecialchars(substr($course['short_description'] ?? $course['description'], 0, 100)); ?>...</p>
                                                <div class="mb-2">
                                                    <small class="text-muted">Progress</small>
                                                    <div class="progress" style="height: 8px;">
                                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $progress; ?>%"></div>
                                                    </div>
                                                    <small class="text-muted"><?php echo number_format($progress, 1); ?>%</small>
                                                </div>
                                                <a href="course-view.php?id=<?php echo $course['id']; ?>" class="btn btn-primary btn-sm w-100">
                                                    <i class="fa fa-play me-2"></i>Continue Learning
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Dashboard End -->

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

