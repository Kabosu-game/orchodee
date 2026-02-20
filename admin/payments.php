<?php
require_once '../includes/admin_check.php';

$conn = getDBConnection();
$action = $_GET['action'] ?? 'list';
$purchaseId = $_GET['id'] ?? 0;

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE purchases SET payment_status = 'completed' WHERE id = ?");
        $stmt->bind_param("i", $purchaseId);
        if ($stmt->execute()) {
            header("Location: payments.php?success=approved");
            exit;
        }
        $stmt->close();
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE purchases SET payment_status = 'failed' WHERE id = ?");
        $stmt->bind_param("i", $purchaseId);
        if ($stmt->execute()) {
            header("Location: payments.php?success=rejected");
            exit;
        }
        $stmt->close();
    }
}

// Récupérer les paiements
$purchases = [];
$statusFilter = $_GET['status'] ?? 'all';

$query = "
    SELECT p.*, 
    c.title as course_title, 
    c.image as course_image,
    u.first_name, 
    u.last_name, 
    u.email as user_email
    FROM purchases p
    JOIN courses c ON p.course_id = c.id
    JOIN users u ON p.user_id = u.id
";

if ($statusFilter !== 'all') {
    $query .= " WHERE p.payment_status = ?";
    $query .= " ORDER BY p.purchased_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $statusFilter);
} else {
    $query .= " ORDER BY p.purchased_at DESC";
    $stmt = $conn->prepare($query);
}

$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $purchases[] = $row;
}
$stmt->close();

// Statistiques
$stats = [];
$result = $conn->query("SELECT payment_status, COUNT(*) as count, SUM(amount) as total FROM purchases GROUP BY payment_status");
while ($row = $result->fetch_assoc()) {
    $stats[$row['payment_status']] = [
        'count' => $row['count'],
        'total' => $row['total'] ?? 0
    ];
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payment Management - Orchidee LLC</title>
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
                            <i class="fa fa-credit-card me-2"></i>Payment Management
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
                            <a class="nav-link active" href="payments.php">
                                <i class="fa fa-credit-card me-2"></i>Payments
                            </a>
                            <a class="nav-link" href="payment-settings.php">
                                <i class="fa fa-cog me-2"></i>Payment Settings
                            </a>
                        </nav>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="col-lg-9">
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            Payment <?php echo $_GET['success']; ?> successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Statistics -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-3">
                            <div class="bg-warning text-white rounded p-4 text-center">
                                <i class="fa fa-clock fa-3x mb-3"></i>
                                <h2 class="mb-0"><?php echo $stats['pending']['count'] ?? 0; ?></h2>
                                <p class="mb-0">Pending</p>
                                <p class="mb-0 small">$<?php echo number_format($stats['pending']['total'] ?? 0, 2); ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-success text-white rounded p-4 text-center">
                                <i class="fa fa-check-circle fa-3x mb-3"></i>
                                <h2 class="mb-0"><?php echo $stats['completed']['count'] ?? 0; ?></h2>
                                <p class="mb-0">Completed</p>
                                <p class="mb-0 small">$<?php echo number_format($stats['completed']['total'] ?? 0, 2); ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-danger text-white rounded p-4 text-center">
                                <i class="fa fa-times-circle fa-3x mb-3"></i>
                                <h2 class="mb-0"><?php echo $stats['failed']['count'] ?? 0; ?></h2>
                                <p class="mb-0">Failed</p>
                                <p class="mb-0 small">$<?php echo number_format($stats['failed']['total'] ?? 0, 2); ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-primary text-white rounded p-4 text-center">
                                <i class="fa fa-dollar-sign fa-3x mb-3"></i>
                                <h2 class="mb-0">$<?php echo number_format(($stats['completed']['total'] ?? 0), 2); ?></h2>
                                <p class="mb-0">Total Revenue</p>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="bg-white rounded p-3 shadow-sm mb-4">
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="payments.php?status=all" class="btn btn-sm <?php echo $statusFilter === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                All (<?php echo count($purchases); ?>)
                            </a>
                            <a href="payments.php?status=pending" class="btn btn-sm <?php echo $statusFilter === 'pending' ? 'btn-warning' : 'btn-outline-warning'; ?>">
                                Pending (<?php echo $stats['pending']['count'] ?? 0; ?>)
                            </a>
                            <a href="payments.php?status=completed" class="btn btn-sm <?php echo $statusFilter === 'completed' ? 'btn-success' : 'btn-outline-success'; ?>">
                                Completed (<?php echo $stats['completed']['count'] ?? 0; ?>)
                            </a>
                            <a href="payments.php?status=failed" class="btn btn-sm <?php echo $statusFilter === 'failed' ? 'btn-danger' : 'btn-outline-danger'; ?>">
                                Failed (<?php echo $stats['failed']['count'] ?? 0; ?>)
                            </a>
                        </div>
                    </div>

                    <!-- Payments List -->
                    <div class="bg-white rounded p-4 shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="mb-0">All Payments</h4>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Course</th>
                                        <th>Amount</th>
                                        <th>Payment Method</th>
                                        <th>Transaction ID</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($purchases)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-5">
                                                <i class="fa fa-credit-card fa-3x mb-3 d-block"></i>
                                                No payments found
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($purchases as $p): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($p['user_email']); ?></small>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!empty($p['course_image'])): ?>
                                                            <img src="../<?php echo htmlspecialchars($p['course_image']); ?>" alt="<?php echo htmlspecialchars($p['course_title']); ?>" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px; margin-right: 10px;">
                                                        <?php endif; ?>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($p['course_title']); ?></strong>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <strong class="text-primary">$<?php echo number_format($p['amount'], 2); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo ucfirst(str_replace('_', ' ', $p['payment_method'] ?? 'N/A')); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo !empty($p['payment_transaction_id']) ? htmlspecialchars(substr($p['payment_transaction_id'], 0, 20)) . '...' : 'N/A'; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status = $p['payment_status'] ?? 'pending';
                                                    $statusClass = [
                                                        'pending' => 'warning',
                                                        'completed' => 'success',
                                                        'failed' => 'danger',
                                                        'refunded' => 'secondary'
                                                    ];
                                                    $badgeClass = $statusClass[$status] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $badgeClass; ?>">
                                                        <?php echo ucfirst($status); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo date('M d, Y g:i A', strtotime($p['purchased_at'])); ?>
                                                </td>
                                                <td>
                                                    <?php if ($status === 'pending'): ?>
                                                        <form method="POST" action="payments.php?action=approve&id=<?php echo $p['id']; ?>" style="display: inline;">
                                                            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Approve this payment?')" title="Approve">
                                                                <i class="fa fa-check"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="payments.php?action=reject&id=<?php echo $p['id']; ?>" style="display: inline;">
                                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Reject this payment?')" title="Reject">
                                                                <i class="fa fa-times"></i>
                                                            </button>
                                                        </form>
                                                    <?php elseif ($status === 'completed'): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fa fa-check me-1"></i>Approved
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">
                                                            <i class="fa fa-times me-1"></i>Rejected
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



