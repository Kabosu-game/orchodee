<?php
require_once '../includes/admin_check.php';

$conn = getDBConnection();

// Filtres de date
$dateFrom = $_GET['date_from'] ?? date('Y-m-01'); // Premier jour du mois
$dateTo = $_GET['date_to'] ?? date('Y-m-d'); // Aujourd'hui
$courseFilter = $_GET['course_id'] ?? 'all';

// Statistiques globales
$stats = [];

// Total des ventes complétées
$result = $conn->query("SELECT COUNT(*) as total, SUM(amount) as revenue FROM purchases WHERE payment_status = 'completed'");
$salesData = $result->fetch_assoc();
$stats['total_sales'] = $salesData['total'];
$stats['total_revenue'] = $salesData['revenue'] ?? 0;

// Ventes ce mois
$result = $conn->query("SELECT COUNT(*) as total, SUM(amount) as revenue FROM purchases WHERE payment_status = 'completed' AND DATE(purchased_at) >= DATE_FORMAT(NOW(), '%Y-%m-01')");
$monthData = $result->fetch_assoc();
$stats['month_sales'] = $monthData['total'];
$stats['month_revenue'] = $monthData['revenue'] ?? 0;

// Ventes aujourd'hui
$result = $conn->query("SELECT COUNT(*) as total, SUM(amount) as revenue FROM purchases WHERE payment_status = 'completed' AND DATE(purchased_at) = CURDATE()");
$todayData = $result->fetch_assoc();
$stats['today_sales'] = $todayData['total'];
$stats['today_revenue'] = $todayData['revenue'] ?? 0;

// Ventes en attente
$result = $conn->query("SELECT COUNT(*) as total, SUM(amount) as revenue FROM purchases WHERE payment_status = 'pending'");
$pendingData = $result->fetch_assoc();
$stats['pending_sales'] = $pendingData['total'];
$stats['pending_revenue'] = $pendingData['revenue'] ?? 0;

// Ventes par cours
$salesByCourse = [];
$result = $conn->query("
    SELECT c.id, c.title, 
    COUNT(p.id) as sales_count, 
    SUM(p.amount) as total_revenue
    FROM courses c
    LEFT JOIN purchases p ON c.id = p.course_id AND p.payment_status = 'completed'
    GROUP BY c.id, c.title
    HAVING sales_count > 0
    ORDER BY total_revenue DESC
");
while ($row = $result->fetch_assoc()) {
    $salesByCourse[] = $row;
}

// Ventes par méthode de paiement
$salesByMethod = [];
$result = $conn->query("
    SELECT payment_method, 
    COUNT(*) as count, 
    SUM(amount) as total
    FROM purchases 
    WHERE payment_status = 'completed'
    GROUP BY payment_method
    ORDER BY total DESC
");
while ($row = $result->fetch_assoc()) {
    $salesByMethod[] = $row;
}

// Ventes par jour (pour graphique)
$salesByDay = [];
$result = $conn->query("
    SELECT DATE(purchased_at) as sale_date,
    COUNT(*) as count,
    SUM(amount) as total
    FROM purchases
    WHERE payment_status = 'completed'
    AND DATE(purchased_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(purchased_at)
    ORDER BY sale_date ASC
");
while ($row = $result->fetch_assoc()) {
    $salesByDay[] = $row;
}

// Liste des ventes avec filtres
$query = "
    SELECT p.*, 
    u.first_name, 
    u.last_name, 
    u.email as user_email,
    c.title as course_title,
    c.image as course_image
    FROM purchases p
    JOIN users u ON p.user_id = u.id
    JOIN courses c ON p.course_id = c.id
    WHERE p.payment_status = 'completed'
";

$params = [];
$types = '';

if ($dateFrom && $dateTo) {
    $query .= " AND DATE(p.purchased_at) BETWEEN ? AND ?";
    $params[] = $dateFrom;
    $params[] = $dateTo;
    $types .= 'ss';
}

if ($courseFilter !== 'all') {
    $query .= " AND p.course_id = ?";
    $params[] = $courseFilter;
    $types .= 'i';
}

$query .= " ORDER BY p.purchased_at DESC LIMIT 100";

$sales = [];
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

while ($row = $result->fetch_assoc()) {
    $sales[] = $row;
}

if (isset($stmt)) {
    $stmt->close();
}

// Liste des cours pour le filtre
$courses = [];
$result = $conn->query("SELECT id, title FROM courses ORDER BY title ASC");
while ($row = $result->fetch_assoc()) {
    $courses[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Sales & Reports - Orchidee LLC</title>
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
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
                            <i class="fa fa-chart-line me-2"></i>Sales & Reports
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
                            <a class="nav-link" href="users.php">
                                <i class="fa fa-user-friends me-2"></i>Users
                            </a>
                            <a class="nav-link" href="payments.php">
                                <i class="fa fa-credit-card me-2"></i>Payments
                            </a>
                            <a class="nav-link active" href="sales.php">
                                <i class="fa fa-chart-line me-2"></i>Sales & Reports
                            </a>
                            <a class="nav-link" href="payment-settings.php">
                                <i class="fa fa-cog me-2"></i>Payment Settings
                            </a>
                        </nav>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="col-lg-9">
                    <!-- Statistics Cards -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-3">
                            <div class="bg-primary text-white rounded p-4 text-center">
                                <i class="fa fa-dollar-sign fa-3x mb-3"></i>
                                <h2 class="mb-0">$<?php echo number_format($stats['total_revenue'], 2); ?></h2>
                                <p class="mb-0">Total Revenue</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-success text-white rounded p-4 text-center">
                                <i class="fa fa-shopping-cart fa-3x mb-3"></i>
                                <h2 class="mb-0"><?php echo $stats['total_sales']; ?></h2>
                                <p class="mb-0">Total Sales</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-info text-white rounded p-4 text-center">
                                <i class="fa fa-calendar-alt fa-3x mb-3"></i>
                                <h2 class="mb-0">$<?php echo number_format($stats['month_revenue'], 2); ?></h2>
                                <p class="mb-0">This Month</p>
                                <small>(<?php echo $stats['month_sales']; ?> sales)</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-warning text-white rounded p-4 text-center">
                                <i class="fa fa-clock fa-3x mb-3"></i>
                                <h2 class="mb-0">$<?php echo number_format($stats['pending_revenue'], 2); ?></h2>
                                <p class="mb-0">Pending</p>
                                <small>(<?php echo $stats['pending_sales']; ?> sales)</small>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="bg-white rounded p-4 shadow-sm mb-4">
                        <h5 class="mb-3">Filters</h5>
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="date_from" class="form-label">From Date</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="date_to" class="form-label">To Date</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="course_id" class="form-label">Course</label>
                                <select class="form-select" id="course_id" name="course_id">
                                    <option value="all" <?php echo $courseFilter === 'all' ? 'selected' : ''; ?>>All Courses</option>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>" <?php echo $courseFilter == $course['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($course['title']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-filter me-2"></i>Apply Filters
                                </button>
                                <a href="sales.php" class="btn btn-outline-secondary">
                                    <i class="fa fa-times me-2"></i>Clear Filters
                                </a>
                            </div>
                        </form>
                    </div>

                    <!-- Sales Chart -->
                    <?php if (!empty($salesByDay)): ?>
                    <div class="bg-white rounded p-4 shadow-sm mb-4">
                        <h5 class="mb-3">Sales Trend (Last 30 Days)</h5>
                        <canvas id="salesChart" height="80"></canvas>
                    </div>
                    <?php endif; ?>

                    <!-- Sales by Course -->
                    <?php if (!empty($salesByCourse)): ?>
                    <div class="bg-white rounded p-4 shadow-sm mb-4">
                        <h5 class="mb-3">Top Selling Courses</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Course</th>
                                        <th>Sales</th>
                                        <th>Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($salesByCourse as $course): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($course['title']); ?></strong></td>
                                            <td><?php echo $course['sales_count']; ?></td>
                                            <td><strong class="text-primary">$<?php echo number_format($course['total_revenue'] ?? 0, 2); ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Sales by Payment Method -->
                    <?php if (!empty($salesByMethod)): ?>
                    <div class="bg-white rounded p-4 shadow-sm mb-4">
                        <h5 class="mb-3">Sales by Payment Method</h5>
                        <div class="row">
                            <?php foreach ($salesByMethod as $method): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="text-muted"><?php echo ucfirst(str_replace('_', ' ', $method['payment_method'])); ?></h6>
                                            <h4 class="text-primary">$<?php echo number_format($method['total'], 2); ?></h4>
                                            <small class="text-muted"><?php echo $method['count']; ?> transactions</small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Sales List -->
                    <div class="bg-white rounded p-4 shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">Recent Sales</h5>
                            <span class="badge bg-primary"><?php echo count($sales); ?> sales</span>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Customer</th>
                                        <th>Course</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Transaction ID</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($sales)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-5">
                                                <i class="fa fa-chart-line fa-3x mb-3 d-block"></i>
                                                No sales found for the selected period
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($sales as $sale): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y g:i A', strtotime($sale['purchased_at'])); ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($sale['first_name'] . ' ' . $sale['last_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($sale['user_email']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($sale['course_title']); ?></td>
                                                <td><strong class="text-primary">$<?php echo number_format($sale['amount'], 2); ?></strong></td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo ucfirst(str_replace('_', ' ', $sale['payment_method'] ?? 'N/A')); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo !empty($sale['payment_transaction_id']) ? htmlspecialchars(substr($sale['payment_transaction_id'], 0, 20)) . '...' : 'N/A'; ?>
                                                    </small>
                                                </td>
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
    
    <?php if (!empty($salesByDay)): ?>
    <script>
        // Sales Chart
        const ctx = document.getElementById('salesChart');
        if (ctx) {
            const salesData = <?php echo json_encode($salesByDay); ?>;
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: salesData.map(item => new Date(item.sale_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
                    datasets: [{
                        label: 'Revenue ($)',
                        data: salesData.map(item => parseFloat(item.total)),
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: true
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '$' + value.toFixed(2);
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>
    <?php endif; ?>
</body>
</html>



