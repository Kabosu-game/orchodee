<?php
require_once 'includes/auth_check.php';

$conn = getDBConnection();
$userId = getUserId();

// Récupérer l'historique des paiements de l'utilisateur
$purchases = [];
$stmt = $conn->prepare("
    SELECT p.*, c.title as course_title, c.image as course_image, u.first_name, u.last_name
    FROM purchases p
    JOIN courses c ON p.course_id = c.id
    JOIN users u ON p.user_id = u.id
    WHERE p.user_id = ?
    ORDER BY p.purchased_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $purchases[] = $row;
}
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payment History - Orchidee LLC</title>
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

    <!-- Payment History Start -->
    <div class="container-fluid bg-light py-5">
        <div class="container py-5">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="text-primary mb-0">
                            <i class="fa fa-history me-2"></i>Payment History
                        </h2>
                        <div>
                            <a href="dashboard.php" class="btn btn-outline-secondary me-2">
                                <i class="fa fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                            <a href="courses.php" class="btn btn-primary">
                                <i class="fa fa-book me-2"></i>Browse Courses
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-12">
                    <div class="bg-white rounded p-4 shadow-sm">
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fa fa-check-circle me-2"></i>Your payment has been submitted successfully. It is now pending admin approval. You will receive access to the course once your payment is approved.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (empty($purchases)): ?>
                        <div class="text-center py-5">
                            <i class="fa fa-receipt fa-4x text-muted mb-3"></i>
                            <h3 class="text-muted">No payment history</h3>
                            <p class="text-muted">You haven't made any purchases yet.</p>
                            <a href="courses.php" class="btn btn-primary mt-3">
                                <i class="fa fa-book me-2"></i>Browse Courses
                            </a>
                        </div>
                    <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
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
                                        <?php foreach ($purchases as $purchase): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if (!empty($purchase['course_image'])): ?>
                                                            <img src="<?php echo htmlspecialchars($purchase['course_image']); ?>" alt="<?php echo htmlspecialchars($purchase['course_title']); ?>" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px; margin-right: 15px;">
                                                        <?php endif; ?>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($purchase['course_title']); ?></strong>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <strong class="text-primary">$<?php echo number_format($purchase['amount'], 2); ?></strong>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo ucfirst(str_replace('_', ' ', $purchase['payment_method'] ?? 'N/A')); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo !empty($purchase['payment_transaction_id']) ? htmlspecialchars(substr($purchase['payment_transaction_id'], 0, 20)) . '...' : 'N/A'; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = [
                                                        'pending' => 'warning',
                                                        'completed' => 'success',
                                                        'failed' => 'danger',
                                                        'refunded' => 'secondary'
                                                    ];
                                                    $status = $purchase['payment_status'] ?? 'pending';
                                                    $badgeClass = $statusClass[$status] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $badgeClass; ?>">
                                                        <?php echo ucfirst($status); ?>
                                                    </span>
                                                    <?php if ($status === 'pending'): ?>
                                                        <br><small class="text-muted">Awaiting admin approval</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo date('M d, Y g:i A', strtotime($purchase['purchased_at'])); ?>
                                                </td>
                                                <td>
                                                    <?php if ($status === 'completed'): ?>
                                                        <a href="course-view.php?id=<?php echo $purchase['course_id']; ?>" class="btn btn-sm btn-primary">
                                                            <i class="fa fa-play me-1"></i>Access Course
                                                        </a>
                                                    <?php elseif ($status === 'pending'): ?>
                                                        <span class="text-muted small">
                                                            <i class="fa fa-clock me-1"></i>Pending
                                                        </span>
                                                    <?php else: ?>
                                                        <a href="purchase.php?id=<?php echo $purchase['course_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fa fa-redo me-1"></i>Retry
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Summary -->
                            <div class="row mt-4">
                                <div class="col-md-4">
                                    <div class="card bg-primary text-white">
                                        <div class="card-body text-center">
                                            <h3><?php echo count(array_filter($purchases, fn($p) => $p['payment_status'] === 'completed')); ?></h3>
                                            <p class="mb-0">Completed</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-warning text-white">
                                        <div class="card-body text-center">
                                            <h3><?php echo count(array_filter($purchases, fn($p) => $p['payment_status'] === 'pending')); ?></h3>
                                            <p class="mb-0">Pending</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-success text-white">
                                        <div class="card-body text-center">
                                            <h3>$<?php 
                                                $total = array_sum(array_column(array_filter($purchases, fn($p) => $p['payment_status'] === 'completed'), 'amount'));
                                                echo number_format($total, 2);
                                            ?></h3>
                                            <p class="mb-0">Total Spent</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Payment History End -->

    <!-- Footer Start -->
    <div class="container-fluid footer py-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                    <a href="index.html" class="p-0">
                        <img src="img/orchideelogo.png" alt="Orchidee LLC" style="height: 40px;">
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
    <script src="js/main.js"></script>
</body>
</html>

