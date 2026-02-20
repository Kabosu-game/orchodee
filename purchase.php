<?php
require_once 'includes/auth_check.php';
require_once 'includes/payment_functions.php';

$courseId = $_GET['id'] ?? 0;
if (!$courseId) {
    redirect('courses.php');
}

$conn = getDBConnection();
$userId = getUserId();

// Get enabled payment methods
$enabledMethods = getEnabledPaymentMethods($conn);

// Get course details
$stmt = $conn->prepare("SELECT * FROM courses WHERE id = ? AND status = 'published'");
if (!$stmt) {
    $conn->close();
    redirect('courses.php');
}
$stmt->bind_param("i", $courseId);
$stmt->execute();
$result = $stmt->get_result();
$course = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$course) {
    $conn->close();
    redirect('courses.php');
}

$purchaseStmt = $conn->prepare("SELECT id FROM purchases WHERE user_id = ? AND course_id = ? AND payment_status = 'completed'");
if (!$purchaseStmt) {
    $conn->close();
    redirect('courses.php');
}
$purchaseStmt->bind_param("ii", $userId, $courseId);
$purchaseStmt->execute();
$purchaseResult = $purchaseStmt->get_result();
if ($purchaseResult->num_rows > 0) {
    $conn->close();
    redirect('course-view.php?id=' . $courseId);
}
$purchaseStmt->close();

// Payment processing — même logique que les formulaires (inscription session / NCLEX) : POST serveur, redirection Stripe/PayPal
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentMethod = sanitize($_POST['payment_method'] ?? '');
    $transactionId = trim(sanitize($_POST['transaction_id'] ?? ''));

    if (empty($paymentMethod)) {
        $error = 'Please select a payment method.';
    } elseif (!isset($enabledMethods[$paymentMethod])) {
        $error = 'Selected payment method is not available.';
    } elseif (in_array($paymentMethod, ['zelle', 'cashapp', 'bank_deposit']) && empty($transactionId)) {
        $error = 'Please provide a transaction ID or reference number for manual payment methods.';
    } else {
        // Contrainte unique (user_id, course_id) : réutiliser l'achat existant si pending, ne pas dupliquer
        $existStmt = $conn->prepare("SELECT id, payment_status FROM purchases WHERE user_id = ? AND course_id = ? LIMIT 1");
        $existStmt->bind_param("ii", $userId, $courseId);
        $existStmt->execute();
        $existResult = $existStmt->get_result();
        $existing = $existResult->fetch_assoc();
        $existStmt->close();

        if ($existing) {
            if ($existing['payment_status'] === 'completed') {
                $conn->close();
                redirect('course-view.php?id=' . $courseId);
                exit;
            }
            $purchaseId = (int) $existing['id'];
            $updateStmt = $conn->prepare("UPDATE purchases SET payment_method = ?, payment_transaction_id = ?, amount = ? WHERE id = ?");
            $updateStmt->bind_param("ssdi", $paymentMethod, $transactionId, $course['price'], $purchaseId);
            $updateStmt->execute();
            $updateStmt->close();
        } else {
            $insertStmt = $conn->prepare("INSERT INTO purchases (user_id, course_id, amount, payment_method, payment_transaction_id, payment_status) VALUES (?, ?, ?, ?, ?, 'pending')");
            $insertStmt->bind_param("iidss", $userId, $courseId, $course['price'], $paymentMethod, $transactionId);
            if (!$insertStmt->execute()) {
                $error = 'Payment processing failed. Please try again.';
                $insertStmt->close();
            } else {
                $purchaseId = $conn->insert_id;
                $insertStmt->close();
            }
        }

        if (empty($error) && isset($purchaseId)) {

            if ($paymentMethod === 'stripe') {
                $stripeConfig = isset($enabledMethods['stripe']['config']) ? $enabledMethods['stripe']['config'] : null;
                if (!$stripeConfig || !is_array($stripeConfig)) {
                    $stripeRow = $conn->query("SELECT config_data FROM payment_config WHERE payment_method = 'stripe' LIMIT 1");
                    if ($stripeRow && $row = $stripeRow->fetch_assoc() && !empty(trim($row['config_data'] ?? ''))) {
                        $stripeConfig = json_decode($row['config_data'], true);
                    }
                }
                $secretKey = $stripeConfig ? trim($stripeConfig['secret_key'] ?? '') : '';
                if (!$stripeConfig || $secretKey === '' || !preg_match('/^sk_(test|live)_/', $secretKey)) {
                    $error = 'Stripe is not configured. Go to Admin → Payment Settings.';
                } else {
                    $amountCents = (int) round((float) $course['price'] * 100);
                    if ($amountCents < 50) $amountCents = 50;
                    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
                    $scriptPath = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
                    $basePath = rtrim($baseUrl, '/') . ($scriptPath === '/' ? '' : $scriptPath);
                    $successUrl = $basePath . '/payment-success.php?session_id={CHECKOUT_SESSION_ID}&purchase_id=' . $purchaseId;
                    $cancelUrl = $basePath . '/purchase.php?id=' . $courseId;
                    $postFields = [
                        'mode' => 'payment',
                        'payment_method_types[]' => 'card',
                        'line_items[0][price_data][currency]' => 'usd',
                        'line_items[0][price_data][product_data][name]' => $course['title'],
                        'line_items[0][price_data][product_data][description]' => 'Course purchase - Orchidee LLC',
                        'line_items[0][price_data][unit_amount]' => $amountCents,
                        'line_items[0][quantity]' => 1,
                        'success_url' => $successUrl,
                        'cancel_url' => $cancelUrl,
                        'client_reference_id' => (string) $purchaseId,
                    ];
                    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => http_build_query($postFields),
                        CURLOPT_USERPWD => $secretKey . ':',
                        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                        CURLOPT_TIMEOUT => 30,
                    ]);
                    $stripeResponse = curl_exec($ch);
                    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    if ($httpCode >= 200 && $httpCode < 300 && $stripeResponse) {
                        $sessionData = json_decode($stripeResponse, true);
                        if (!empty($sessionData['url'])) {
                            $conn->close();
                            header('Location: ' . $sessionData['url']);
                            exit;
                        }
                    }
                    $error = 'Unable to start card payment. Please try again or choose another method.';
                }
            } elseif ($paymentMethod === 'paypal') {
                require_once __DIR__ . '/includes/paypal_helper.php';
                $paypalConfig = isset($enabledMethods['paypal']['config']) ? $enabledMethods['paypal']['config'] : null;
                if (!$paypalConfig || !is_array($paypalConfig)) {
                    $pr = $conn->query("SELECT config_data FROM payment_config WHERE payment_method = 'paypal' LIMIT 1");
                    if ($pr && $rowp = $pr->fetch_assoc() && !empty(trim($rowp['config_data'] ?? ''))) {
                        $paypalConfig = json_decode($rowp['config_data'], true);
                    }
                }
                $clientId = $paypalConfig ? trim($paypalConfig['client_id'] ?? '') : '';
                $clientSecret = $paypalConfig ? trim($paypalConfig['client_secret'] ?? '') : '';
                $sandbox = (isset($paypalConfig['mode']) && strtolower($paypalConfig['mode'] ?? '') === 'live') ? false : true;
                if ($clientId && $clientSecret) {
                    $tokenResult = paypal_get_access_token($clientId, $clientSecret, $sandbox);
                    $token = $tokenResult['token'] ?? null;
                    if ($token) {
                        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
                        $scriptPath = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
                        $basePath = rtrim($baseUrl, '/') . ($scriptPath === '/' ? '' : $scriptPath);
                        $returnUrl = $basePath . '/purchase-payment-success-paypal.php?purchase_id=' . $purchaseId;
                        $cancelUrl = $basePath . '/purchase.php?id=' . $courseId;
                        $amountStr = number_format((float) $course['price'], 2, '.', '');
                        $orderResult = paypal_create_order($token, $amountStr, $returnUrl, $cancelUrl, $course['title'], $sandbox);
                        if (!empty($orderResult['url'])) {
                            $conn->close();
                            header('Location: ' . $orderResult['url']);
                            exit;
                        }
                        $error = 'PayPal: ' . ($orderResult['error'] ?? 'Unable to create order.');
                    } else {
                        $error = 'PayPal: ' . ($tokenResult['error'] ?? 'Unable to get access. Check Client ID and Secret.');
                    }
                } else {
                    $error = 'PayPal is not configured. Go to Admin → Payment Settings.';
                }
            } else {
                $conn->close();
                header('Location: payment-history.php?success=submitted');
                exit;
            }
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Purchase Course - Orchidee LLC</title>
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
    <!-- jQuery first so inline payment script can use $ -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
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

    <!-- Purchase Start -->
    <div class="container-fluid bg-light py-5">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="bg-white rounded p-5 shadow-sm">
                        <div class="text-center mb-4">
                            <h2 class="text-primary mb-3">Complete Your Purchase</h2>
                            <h4><?php echo htmlspecialchars($course['title']); ?></h4>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fa fa-exclamation-circle me-2"></i><?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($_GET['success'])): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fa fa-check-circle me-2"></i>Your payment request has been submitted. You will receive access once payment is confirmed by admin.
                                <p class="mb-0 mt-2"><a href="payment-history.php" class="alert-link">View Payment History</a></p>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!$error && !isset($_GET['success'])): ?>
                            <div class="mb-4 p-3 bg-light rounded">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fs-5">Total Amount:</span>
                                    <span class="fs-3 text-primary fw-bold">$<?php echo number_format($course['price'], 2); ?></span>
                                </div>
                            </div>
                            
                            <form method="POST" action="" id="paymentForm">
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Secure Payment Options <span class="text-danger">*</span></label>
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
                                                <i class="fa fa-exclamation-triangle me-2"></i>No payment methods available. Please contact the administrator.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mb-4 mt-3" id="transactionIdField" style="display: none;">
                                        <label class="form-label fw-bold">Transaction ID / Reference Number <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="transaction_id" id="transaction_id" placeholder="Enter transaction ID or reference number">
                                        <small class="text-muted">Please provide your payment transaction ID for verification.</small>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100 py-3 mb-3" <?php echo empty($enabledMethods) ? 'disabled' : ''; ?>>
                                    <i class="fa fa-credit-card me-2"></i>Complete Purchase
                                </button>
                            </form>
                            
                            <script>
                                $(function() {
                                    $('input[name="payment_method"]').on('change', function() {
                                        var method = $(this).val();
                                        if (['zelle', 'cashapp', 'bank_deposit'].indexOf(method) !== -1) {
                                            $('#transactionIdField').show();
                                        } else {
                                            $('#transactionIdField').hide();
                                            $('#transaction_id').val('');
                                        }
                                    });
                                });
                            </script>
                            <div class="text-center mt-3">
                                <a href="course-details.php?id=<?php echo $courseId; ?>" class="text-muted">
                                    <i class="fa fa-arrow-left me-2"></i>Back to Course Details
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Purchase End -->

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

    <!-- JavaScript Libraries (jQuery already in head for payment script) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- main.js non chargé sur cette page pour éviter WOW/owlCarousel non présents -->
</body>
</html>

