<?php
require_once '../includes/admin_check.php';

$conn = getDBConnection();

$tblCheck = $conn->query("SHOW TABLES LIKE 'service_subscriptions'");
if (!$tblCheck || $tblCheck->num_rows === 0) {
    header("Location: dashboard.php");
    exit;
}

$filter = $_GET['filter'] ?? 'active';
$subscriptions = [];
$q = "SELECT ss.*, s.title as service_title, u.email as user_email, u.first_name, u.last_name, sp.duration_months, sp.total_price 
      FROM service_subscriptions ss 
      JOIN services s ON ss.service_id = s.id 
      LEFT JOIN users u ON ss.user_id = u.id 
      LEFT JOIN service_plans sp ON ss.plan_id = sp.id 
      WHERE 1=1";
if ($filter === 'active') {
    $q .= " AND ss.status = 'active'";
} elseif ($filter === 'completed') {
    $q .= " AND ss.status = 'completed'";
} elseif ($filter === 'cancelled') {
    $q .= " AND ss.status = 'cancelled'";
}
$q .= " ORDER BY ss.next_payment_date ASC, ss.created_at DESC";
$result = $conn->query($q);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $subscriptions[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Service Subscriptions - Orchidee LLC</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"/>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <style>
        .sidebar .nav-link { color: #495057; padding: 12px 20px; border-radius: 5px; margin-bottom: 5px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background: #007bff; color: white; }
    </style>
</head>
<body>
    <?php include '../includes/menu-dynamic.php'; ?>

    <div class="container-fluid bg-light py-5">
        <div class="container py-5">
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="text-primary mb-0"><i class="fa fa-sync-alt me-2"></i>Service Subscriptions (Recurring)</h2>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-3 mb-4">
                    <div class="bg-white rounded p-4 shadow-sm sidebar">
                        <nav class="nav flex-column">
                            <a class="nav-link" href="dashboard.php"><i class="fa fa-home me-2"></i>Dashboard</a>
                            <a class="nav-link" href="services.php"><i class="fa fa-cogs me-2"></i>Services</a>
                            <a class="nav-link" href="service-requests.php"><i class="fa fa-list-alt me-2"></i>Service Requests</a>
                            <a class="nav-link active" href="service-subscriptions.php"><i class="fa fa-sync-alt me-2"></i>Subscriptions</a>
                        </nav>
                    </div>
                </div>
                <div class="col-lg-9">
                    <div class="mb-3">
                        <a href="?filter=active" class="btn btn-sm <?php echo $filter === 'active' ? 'btn-primary' : 'btn-outline-primary'; ?>">Active</a>
                        <a href="?filter=completed" class="btn btn-sm <?php echo $filter === 'completed' ? 'btn-primary' : 'btn-outline-primary'; ?>">Completed</a>
                        <a href="?filter=cancelled" class="btn btn-sm <?php echo $filter === 'cancelled' ? 'btn-primary' : 'btn-outline-primary'; ?>">Cancelled</a>
                        <a href="?filter=all" class="btn btn-sm <?php echo $filter === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">All</a>
                    </div>
                    <div class="bg-white rounded shadow-sm p-4">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Service</th>
                                        <th>User</th>
                                        <th>Amount / Interval</th>
                                        <th>Next Payment</th>
                                        <th>End Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($subscriptions)): ?>
                                        <tr><td colspan="7" class="text-center text-muted py-4">No subscriptions found.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($subscriptions as $sub): ?>
                                            <tr>
                                                <td><?php echo (int)$sub['id']; ?></td>
                                                <td><?php echo htmlspecialchars($sub['service_title']); ?></td>
                                                <td>
                                                    <?php if ($sub['user_email']): ?>
                                                        <?php echo htmlspecialchars($sub['first_name'] . ' ' . $sub['last_name']); ?><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($sub['user_email']); ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>$<?php echo number_format($sub['amount_per_interval'], 2); ?> / <?php echo $sub['billing_interval'] === 'week' ? 'week' : 'month'; ?></td>
                                                <td><?php echo $sub['next_payment_date'] ? date('Y-m-d', strtotime($sub['next_payment_date'])) : '—'; ?></td>
                                                <td><?php echo $sub['end_date'] ? date('Y-m-d', strtotime($sub['end_date'])) : '—'; ?></td>
                                                <td><span class="badge bg-<?php echo $sub['status'] === 'active' ? 'success' : ($sub['status'] === 'completed' ? 'secondary' : 'danger'); ?>"><?php echo ucfirst($sub['status']); ?></span></td>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
