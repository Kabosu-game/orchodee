<?php
require_once '../includes/admin_check.php';

$stats = ['total_courses' => 0, 'published_courses' => 0, 'total_users' => 0, 'total_sales' => 0, 'revenue' => 0];
$recentCourses = [];
$recentSales = [];

try {
    $conn = getDBConnection();

    // Total courses
    $result = @$conn->query("SELECT COUNT(*) as total FROM courses");
    if ($result && $row = $result->fetch_assoc()) {
        $stats['total_courses'] = (int)$row['total'];
    }

    // Published courses
    $result = @$conn->query("SELECT COUNT(*) as total FROM courses WHERE status = 'published'");
    if ($result && $row = $result->fetch_assoc()) {
        $stats['published_courses'] = (int)$row['total'];
    }

    // Total users
    $result = @$conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'");
    if ($result && $row = $result->fetch_assoc()) {
        $stats['total_users'] = (int)$row['total'];
    }

    // Total sales
    $result = @$conn->query("SELECT COUNT(*) as total, SUM(amount) as revenue FROM purchases WHERE payment_status = 'completed'");
    if ($result && $salesData = $result->fetch_assoc()) {
        $stats['total_sales'] = (int)($salesData['total'] ?? 0);
        $stats['revenue'] = (float)($salesData['revenue'] ?? 0);
    }

    // Recent courses
    $result = @$conn->query("SELECT * FROM courses ORDER BY created_at DESC LIMIT 5");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recentCourses[] = $row;
        }
    }

    // Recent sales
    $result = @$conn->query("
        SELECT p.*, u.first_name, u.last_name, u.email, c.title as course_title 
        FROM purchases p 
        JOIN users u ON p.user_id = u.id 
        JOIN courses c ON p.course_id = c.id 
        WHERE p.payment_status = 'completed'
        ORDER BY p.purchased_at DESC 
        LIMIT 10
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recentSales[] = $row;
        }
    }

    if (isset($conn) && $conn) $conn->close();
} catch (Throwable $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    if (isset($conn) && $conn) @$conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Admin Dashboard - Orchidee LLC</title>
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
    <!-- Spinner Start -->
    <div id="spinner" class="bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center" style="display: none !important;">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>
    <!-- Spinner End -->

    <!-- Menu -->
    <?php include '../includes/menu-dynamic.php'; ?>

    <!-- Admin Dashboard Start -->
    <div class="container-fluid bg-light py-5">
        <div class="container py-5">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="text-primary mb-0">
                            <i class="fa fa-tachometer-alt me-2"></i>Admin Dashboard
                        </h2>
                        <div>
                            <a href="../dashboard.php" class="btn btn-outline-primary me-2">
                                <i class="fa fa-user me-2"></i>User View
                            </a>
                            <a href="../logout.php" class="btn btn-outline-danger">
                                <i class="fa fa-sign-out-alt me-2"></i>Logout
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
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fa fa-home me-2"></i>Dashboard
                            </a>
                            <a class="nav-link" href="courses.php">
                                <i class="fa fa-book me-2"></i>Manage Courses
                            </a>
                            <a class="nav-link" href="courses.php?action=add">
                                <i class="fa fa-plus-circle me-2"></i>Add New Course
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
                            <a class="nav-link" href="services.php">
                                <i class="fa fa-cogs me-2"></i>Services
                            </a>
                            <a class="nav-link" href="service-forms.php">
                                <i class="fa fa-wpforms me-2"></i>Service Forms
                            </a>
                            <a class="nav-link" href="service-requests.php">
                                <i class="fa fa-list-alt me-2"></i>Service Requests
                            </a>
                            <a class="nav-link" href="session-registrations.php">
                                <i class="fa fa-user-plus me-2"></i>Session Registrations
                            </a>
                            <a class="nav-link" href="nclex-registrations.php">
                                <i class="fa fa-file-alt me-2"></i>NCLEX Registrations
                            </a>
                            <a class="nav-link" href="testimonials.php">
                                <i class="fa fa-comments me-2"></i>Testimonials
                            </a>
                            <a class="nav-link" href="annonces.php">
                                <i class="fa fa-bullhorn me-2"></i>Announcements
                            </a>
                            <a class="nav-link" href="gallery.php">
                                <i class="fa fa-images me-2"></i>Gallery
                            </a>
                            <a class="nav-link" href="categories.php">
                                <i class="fa fa-tags me-2"></i>Categories
                            </a>
                            <a class="nav-link" href="users.php">
                                <i class="fa fa-users me-2"></i>Users
                            </a>
                            <a class="nav-link" href="payments.php">
                                <i class="fa fa-credit-card me-2"></i>Payment Management
                            </a>
                            <a class="nav-link" href="sales.php">
                                <i class="fa fa-chart-line me-2"></i>Sales & Reports
                            </a>
                            <a class="nav-link" href="payment-settings.php">
                                <i class="fa fa-credit-card me-2"></i>Payment Settings
                            </a>
                            <hr>
                            <a class="nav-link" href="../index.html">
                                <i class="fa fa-globe me-2"></i>View Website
                            </a>
                        </nav>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="col-lg-9">
                    <!-- Stats -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-3">
                            <div class="bg-primary text-white rounded p-4 text-center">
                                <i class="fa fa-book fa-3x mb-3"></i>
                                <h2 class="mb-0"><?php echo $stats['total_courses']; ?></h2>
                                <p class="mb-0">Total Courses</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-success text-white rounded p-4 text-center">
                                <i class="fa fa-check-circle fa-3x mb-3"></i>
                                <h2 class="mb-0"><?php echo $stats['published_courses']; ?></h2>
                                <p class="mb-0">Published</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-info text-white rounded p-4 text-center">
                                <i class="fa fa-users fa-3x mb-3"></i>
                                <h2 class="mb-0"><?php echo $stats['total_users']; ?></h2>
                                <p class="mb-0">Total Users</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-warning text-white rounded p-4 text-center">
                                <i class="fa fa-dollar-sign fa-3x mb-3"></i>
                                <h2 class="mb-0">$<?php echo number_format($stats['revenue'], 2); ?></h2>
                                <p class="mb-0">Revenue</p>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Courses -->
                    <div class="bg-white rounded p-4 shadow-sm mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="mb-0">Recent Courses</h4>
                            <a href="courses.php?action=add" class="btn btn-primary btn-sm">
                                <i class="fa fa-plus me-2"></i>Add New
                            </a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Status</th>
                                        <th>Price</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentCourses)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No courses yet</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentCourses as $course): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($course['title']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $course['status'] === 'published' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($course['status']); ?>
                                                    </span>
                                                </td>
                                                <td>$<?php echo number_format($course['price'], 2); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($course['created_at'])); ?></td>
                                                <td>
                                                    <a href="courses.php?action=edit&id=<?php echo $course['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fa fa-edit"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Recent Sales -->
                    <div class="bg-white rounded p-4 shadow-sm">
                        <h4 class="mb-3">Recent Sales</h4>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Course</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recentSales)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No sales yet</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($recentSales as $sale): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($sale['first_name'] . ' ' . $sale['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($sale['course_title']); ?></td>
                                                <td>$<?php echo number_format($sale['amount'], 2); ?></td>
                                                <td><?php echo ucfirst(str_replace('_', ' ', $sale['payment_method'])); ?></td>
                                                <td><?php echo date('M d, Y g:i A', strtotime($sale['purchased_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Admin Dashboard End -->

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

