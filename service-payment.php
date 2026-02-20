<?php
require_once 'config/database.php';
require_once 'includes/payment_functions.php';

$conn = getDBConnection();

// Create tables if they don't exist
$tables = [
    'service_requests' => "CREATE TABLE IF NOT EXISTS service_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        service_id INT NOT NULL,
        form_id INT NOT NULL,
        user_id INT,
        form_data TEXT NOT NULL,
        payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
        payment_method VARCHAR(50),
        payment_transaction_id VARCHAR(255),
        payment_amount DECIMAL(10, 2),
        request_status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
        admin_notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
        FOREIGN KEY (form_id) REFERENCES service_forms(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        KEY service_id (service_id),
        KEY form_id (form_id),
        KEY user_id (user_id),
        KEY payment_status (payment_status),
        KEY request_status (request_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

// Check and create tables
foreach ($tables as $tableName => $createSQL) {
    $tableCheck = $conn->query("SHOW TABLES LIKE '$tableName'");
    if ($tableCheck && $tableCheck->num_rows === 0) {
        // Temporarily disable foreign key checks
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        $conn->query($createSQL);
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    }
}

$requestId = $_GET['request_id'] ?? 0;
if (!$requestId) {
    $conn->close();
    header("Location: index.php");
    exit;
}

// Get service request (with plan if recurring)
$stmt = $conn->prepare("SELECT sr.*, s.title as service_title, s.price, s.description as service_description, s.recurring_enabled, s.billing_interval, sp.id as plan_id_join, sp.duration_months, sp.total_price as plan_total_price FROM service_requests sr JOIN services s ON sr.service_id = s.id LEFT JOIN service_plans sp ON sr.plan_id = sp.id AND sp.service_id = s.id WHERE sr.id = ?");
$stmt->bind_param("i", $requestId);
$stmt->execute();
$result = $stmt->get_result();
$request = $result->fetch_assoc();
$stmt->close();

if (!$request) {
    $conn->close();
    header("Location: index.php");
    exit;
}

// Recurring: compute first payment amount (amount per interval)
$isRecurring = !empty($request['plan_id']) && !empty($request['plan_id_join']) && (int)$request['duration_months'] > 0;
$paymentAmount = $request['price'] ?? 0;
$amountPerInterval = null;
$intervalLabel = 'month';
if ($isRecurring) {
    $interval = ($request['billing_interval'] ?? 'month') === 'week' ? 'week' : 'month';
    $intervalLabel = $interval;
    $dur = (int)$request['duration_months'];
    $total = (float)$request['plan_total_price'];
    $amountPerInterval = $interval === 'month' ? ($dur > 0 ? $total / $dur : $total) : ($dur > 0 ? $total / ($dur * 4) : $total); // ~4 weeks per month
    $paymentAmount = round($amountPerInterval, 2);
}

// Get enabled payment methods
$enabledMethods = getEnabledPaymentMethods($conn);

// Process payment
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentMethod = sanitize($_POST['payment_method'] ?? '');
    
    if (empty($paymentMethod)) {
        $error = 'Please select a payment method.';
    } elseif (isset($enabledMethods[$paymentMethod])) {
        $amountToCharge = $isRecurring ? round($amountPerInterval, 2) : ($request['price'] ?? 0);
        
        $transactionId = sanitize($_POST['transaction_id'] ?? '');
        $stmt = $conn->prepare("UPDATE service_requests SET payment_method=?, payment_transaction_id=?, payment_amount=?, payment_status='pending' WHERE id=?");
        $stmt->bind_param("ssdi", $paymentMethod, $transactionId, $amountToCharge, $requestId);
        
        if ($stmt->execute()) {
            $stmt->close();
            // If recurring: create subscription and first payment record
            if ($isRecurring && !empty($request['plan_id'])) {
                $tblSub = $conn->query("SHOW TABLES LIKE 'service_subscriptions'");
                if ($tblSub && $tblSub->num_rows > 0) {
                    $startDate = date('Y-m-d');
                    $dur = (int)$request['duration_months'];
                    $interval = ($request['billing_interval'] ?? 'month') === 'week' ? 'week' : 'month';
                    $endDate = date('Y-m-d', strtotime($startDate . ' + ' . $dur . ' months'));
                    $nextDate = $interval === 'week' ? date('Y-m-d', strtotime($startDate . ' + 1 week')) : date('Y-m-d', strtotime($startDate . ' + 1 month'));
                    $userId = $request['user_id'] ? (int)$request['user_id'] : null;
                    $ins = $conn->prepare("INSERT INTO service_subscriptions (service_id, plan_id, user_id, request_id, amount_per_interval, billing_interval, start_date, next_payment_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')");
                    $ins->bind_param("iiiidssss", $request['service_id'], $request['plan_id'], $userId, $requestId, $amountPerInterval, $interval, $startDate, $nextDate, $endDate);
                    if ($ins->execute()) {
                        $subId = $conn->insert_id;
                        $ins->close();
                        $tblPay = $conn->query("SHOW TABLES LIKE 'service_subscription_payments'");
                        if ($tblPay && $tblPay->num_rows > 0) {
                            $payIns = $conn->prepare("INSERT INTO service_subscription_payments (subscription_id, amount, payment_date, payment_method, transaction_id, status) VALUES (?, ?, ?, ?, ?, 'completed')");
                            $payIns->bind_param("idsss", $subId, $amountToCharge, $startDate, $paymentMethod, $transactionId);
                            $payIns->execute();
                            $payIns->close();
                        }
                    } else {
                        $ins->close();
                    }
                }
            }
            $conn->close();
            header("Location: service-payment-success.php?request_id=" . $requestId);
            exit;
        } else {
            $error = 'Payment processing failed. Please try again.';
        }
        $stmt->close();
    } else {
        // Payment method not available
        $error = 'Selected payment method is not available. Please select another method.';
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payment - <?php echo htmlspecialchars($request['service_title']); ?> - Orchidee LLC</title>
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
    <!-- Menu -->
    <?php include 'includes/menu-dynamic.php'; ?>

    <!-- Payment Start -->
    <div class="container-fluid bg-light py-5">
        <div class="container py-5">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="bg-white rounded shadow-lg p-5">
                        <h2 class="text-primary mb-4">Complete Your Payment</h2>
                        
                        <!-- Service Summary -->
                        <div class="card mb-4 border-primary">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($request['service_title']); ?></h5>
                                <p class="card-text text-muted"><?php echo htmlspecialchars(substr($request['service_description'] ?? '', 0, 150)); ?>...</p>
                                <?php if ($isRecurring): ?>
                                    <p class="text-muted small mb-2">Recurring plan: <?php echo (int)$request['duration_months']; ?> month(s) — Total $<?php echo number_format((float)$request['plan_total_price'], 2); ?></p>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <span class="h4 text-success mb-0">First payment: $<?php echo number_format($paymentAmount, 2); ?></span>
                                    </div>
                                    <p class="text-muted small mt-1">Then $<?php echo number_format($amountPerInterval, 2); ?> per <?php echo $intervalLabel; ?> for <?php echo (int)$request['duration_months']; ?> month(s).</p>
                                <?php else: ?>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <span class="h4 text-success mb-0">Total: $<?php echo number_format($paymentAmount, 2); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fa fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Payment Methods -->
                        <form method="POST" action="" id="paymentForm">
                            <input type="hidden" name="request_id" value="<?php echo $requestId; ?>">
                            
                            <h5 class="mb-3">Select Payment Method</h5>
                            
                            <?php if (empty($enabledMethods)): ?>
                                <div class="alert alert-warning mb-3">
                                    <i class="fa fa-exclamation-triangle me-2"></i>
                                    <strong>Aucune méthode de paiement disponible.</strong> Veuillez contacter l'administrateur.
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <div class="list-group">
                                    <?php if (isset($enabledMethods['stripe'])): ?>
                                        <label class="list-group-item">
                                            <input class="form-check-input me-2" type="radio" name="payment_method" value="stripe" required>
                                            <i class="fab fa-cc-stripe me-2 text-primary"></i>Credit/Debit Card (Stripe)
                                            <span class="badge bg-success ms-2">Secure</span>
                                        </label>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($enabledMethods['paypal'])): ?>
                                        <label class="list-group-item">
                                            <input class="form-check-input me-2" type="radio" name="payment_method" value="paypal" required>
                                            <i class="fab fa-paypal me-2 text-primary"></i>PayPal
                                            <span class="badge bg-success ms-2">Secure</span>
                                        </label>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($enabledMethods['zelle'])): ?>
                                        <label class="list-group-item">
                                            <input class="form-check-input me-2" type="radio" name="payment_method" value="zelle" required>
                                            <i class="fa fa-mobile-alt me-2 text-primary"></i>Zelle
                                            <small class="text-muted ms-2">(Manual Verification)</small>
                                        </label>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($enabledMethods['cashapp'])): ?>
                                        <label class="list-group-item">
                                            <input class="form-check-input me-2" type="radio" name="payment_method" value="cashapp" required>
                                            <i class="fa fa-dollar-sign me-2 text-primary"></i>Cash App
                                            <small class="text-muted ms-2">(Manual Verification)</small>
                                        </label>
                                    <?php endif; ?>
                                    
                                    <?php if (isset($enabledMethods['bank_deposit'])): ?>
                                        <label class="list-group-item">
                                            <input class="form-check-input me-2" type="radio" name="payment_method" value="bank_deposit" required>
                                            <i class="fa fa-university me-2 text-primary"></i>Bank Deposit
                                            <small class="text-muted ms-2">(Manual Verification)</small>
                                        </label>
                                    <?php endif; ?>
                                    
                                    <?php if (empty($enabledMethods)): ?>
                                        <div class="list-group-item text-danger">
                                            <i class="fa fa-exclamation-triangle me-2"></i>Aucune méthode de paiement disponible. Veuillez contacter l'administrateur.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Transaction ID for manual methods -->
                            <div class="mb-3" id="transactionIdField" style="display: none;">
                                <label class="form-label fw-bold">Transaction ID / Reference Number</label>
                                <input type="text" class="form-control" name="transaction_id" placeholder="Enter transaction ID or reference number">
                                <small class="text-muted">Please provide your payment transaction ID for verification.</small>
                            </div>
                            
                            <div class="d-grid gap-2 mt-4">
                                <button type="submit" class="btn btn-primary btn-lg py-3" <?php echo empty($enabledMethods) ? 'disabled' : ''; ?>>
                                    <i class="fa fa-lock me-2"></i>Complete Payment
                                </button>
                                <a href="service-form.php?service_id=<?php echo $request['service_id']; ?>" class="btn btn-outline-secondary">
                                    <i class="fa fa-arrow-left me-2"></i>Back to Form
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Payment End -->

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="lib/wow/wow.min.js"></script>
    <script src="lib/easing/easing.min.js"></script>
    <script src="lib/waypoints/waypoints.min.js"></script>
    <script src="lib/counterup/counterup.min.js"></script>
    <script src="js/main.js"></script>
    <script>
        // Show/hide transaction ID field based on payment method
        $('input[name="payment_method"]').change(function() {
            const method = $(this).val();
            if (['zelle', 'cashapp', 'bank_deposit'].includes(method)) {
                $('#transactionIdField').show();
                $('input[name="transaction_id"]').prop('required', true);
            } else {
                $('#transactionIdField').hide();
                $('input[name="transaction_id"]').prop('required', false);
            }
        });
    </script>
</body>
</html>

