<?php
require_once 'config/database.php';
require_once 'includes/payment_functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php?redirect=registration-next-session.php");
    exit;
}

$userId = $_SESSION['user_id'];
$conn = getDBConnection();

// Get enabled payment methods
$enabledMethods = getEnabledPaymentMethods($conn);

// Process form submission
$error = '';
$success = '';

// Debug: Log POST data (remove in production)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST data: " . print_r($_POST, true));
    error_log("Step value: " . ($_POST['step'] ?? 'NOT SET'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step'])) {
    if ($_POST['step'] == '1') {
        // Step 1: Save form data to session
        $_SESSION['registration_data'] = [
            'first_name' => sanitize($_POST['first_name'] ?? ''),
            'last_name' => sanitize($_POST['last_name'] ?? ''),
            'email' => sanitize($_POST['email'] ?? ''),
            'phone' => sanitize($_POST['phone'] ?? ''),
            'nursing_school' => sanitize($_POST['nursing_school'] ?? ''),
            'years_attended' => sanitize($_POST['years_attended'] ?? ''),
            'session_duration' => sanitize($_POST['session_duration'] ?? ''),
            'credentials_started' => sanitize($_POST['credentials_started'] ?? ''),
            'motivation' => sanitize($_POST['motivation'] ?? ''),
            'comments' => sanitize($_POST['comments'] ?? '')
        ];
        
        // Validate required fields
        if (empty($_SESSION['registration_data']['first_name']) || 
            empty($_SESSION['registration_data']['last_name']) || 
            empty($_SESSION['registration_data']['email']) || 
            empty($_SESSION['registration_data']['phone']) || 
            empty($_SESSION['registration_data']['nursing_school']) || 
            empty($_SESSION['registration_data']['years_attended']) || 
            empty($_SESSION['registration_data']['session_duration']) || 
            empty($_SESSION['registration_data']['credentials_started'])) {
            $error = 'Please fill in all required fields.';
        } elseif (!filter_var($_SESSION['registration_data']['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Step 1 is valid, proceed to step 2
            $success = 'Step 1 completed. Please proceed to payment.';
        }
    } elseif ($_POST['step'] == '2' && isset($_SESSION['registration_data'])) {
        // Step 2: Process payment
        $paymentMethod = sanitize($_POST['payment_method'] ?? '');
        $agreement = isset($_POST['agreement']) ? true : false;
        
        // Validate step 2
        if (empty($paymentMethod)) {
            $error = 'Please select a payment method.';
        } elseif (!$agreement) {
            $error = 'You must agree to the terms and conditions to proceed.';
        } else {
            // Check if manual payment method requires transaction ID
            if (in_array($paymentMethod, ['zelle', 'cashapp', 'bank_deposit'])) {
                $transactionId = sanitize($_POST['transaction_id'] ?? '');
                if (empty($transactionId)) {
                    $error = 'Please provide a transaction ID or reference number for manual payment methods.';
                }
            }
            
            if (empty($error)) {
                // Calculate total
                $registrationFee = 50.00;
                $tax = 3.99;
                $total = $registrationFee + $tax;
                
                // Check if table exists, create if not
                $tableCheck = $conn->query("SHOW TABLES LIKE 'session_registrations'");
                if ($tableCheck->num_rows == 0) {
                    // Table doesn't exist, try to create it automatically
                    $createTableSql = "CREATE TABLE IF NOT EXISTS session_registrations (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT,
                        first_name VARCHAR(255) NOT NULL,
                        last_name VARCHAR(255) NOT NULL,
                        email VARCHAR(255) NOT NULL,
                        phone VARCHAR(50) NOT NULL,
                        nursing_school VARCHAR(255) NOT NULL,
                        years_attended VARCHAR(50) NOT NULL,
                        session_duration VARCHAR(50) NOT NULL,
                        credentials_started ENUM('Yes', 'No') NOT NULL,
                        motivation TEXT,
                        comments TEXT,
                        registration_fee DECIMAL(10, 2) DEFAULT 50.00,
                        tax DECIMAL(10, 2) DEFAULT 3.99,
                        total_amount DECIMAL(10, 2) NOT NULL,
                        payment_method VARCHAR(50) NOT NULL,
                        payment_transaction_id VARCHAR(255),
                        payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_user_id (user_id),
                        INDEX idx_email (email),
                        INDEX idx_payment_status (payment_status),
                        INDEX idx_created_at (created_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    
                    if (!$conn->query($createTableSql)) {
                        $error = 'Database table not found and could not be created. Please run create-session-registrations-table.php first. Error: ' . $conn->error;
                    }
                }
                
                // Check again after potential creation
                $tableCheck2 = $conn->query("SHOW TABLES LIKE 'session_registrations'");
                if ($tableCheck2->num_rows > 0) {
                    // Save registration to database
                    $stmt = $conn->prepare("INSERT INTO session_registrations (
                        user_id, first_name, last_name, email, phone, nursing_school, years_attended,
                        session_duration, credentials_started, motivation, comments,
                        registration_fee, tax, total_amount, payment_method, payment_status, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
                    
                    if ($stmt) {
                        $stmt->bind_param("issssssssssddds",
                            $userId,
                            $_SESSION['registration_data']['first_name'],
                            $_SESSION['registration_data']['last_name'],
                            $_SESSION['registration_data']['email'],
                            $_SESSION['registration_data']['phone'],
                            $_SESSION['registration_data']['nursing_school'],
                            $_SESSION['registration_data']['years_attended'],
                            $_SESSION['registration_data']['session_duration'],
                            $_SESSION['registration_data']['credentials_started'],
                            $_SESSION['registration_data']['motivation'],
                            $_SESSION['registration_data']['comments'],
                            $registrationFee,
                            $tax,
                            $total,
                            $paymentMethod
                        );
                        
                        if ($stmt->execute()) {
                            $registrationId = $conn->insert_id;
                            $stmt->close();
                            
                            // Handle payment based on method
                            $stripeMsg = null;
                            $stripeDebug = null;
                            if ($paymentMethod === 'stripe') {
                                // Utiliser la même config que celle qui affiche Stripe (getEnabledPaymentMethods)
                                $stripeConfig = isset($enabledMethods['stripe']['config']) ? $enabledMethods['stripe']['config'] : null;
                                if (!$stripeConfig || !is_array($stripeConfig)) {
                                    $stripeRow = $conn->query("SELECT config_data FROM payment_config WHERE payment_method = 'stripe' LIMIT 1");
                                    if ($stripeRow && $row = $stripeRow->fetch_assoc()) {
                                        $raw = trim($row['config_data'] ?? '');
                                        if ($raw !== '') {
                                            $dec = json_decode($raw, true);
                                            if (is_array($dec)) {
                                                $stripeConfig = $dec;
                                            }
                                        }
                                    }
                                }
                                $secretKey = $stripeConfig ? trim($stripeConfig['secret_key'] ?? $stripeConfig['secretKey'] ?? '') : '';
                                if (!$stripeConfig || $secretKey === '') {
                                    $stripeMsg = 'Clé secrète Stripe non configurée. Allez dans Admin → Paramètres de paiement, saisissez votre Secret Key (sk_test_ ou sk_live_) et cliquez sur Enregistrer.';
                                } elseif (!preg_match('/^sk_(test|live)_/', $secretKey)) {
                                    $stripeMsg = 'La clé secrète doit commencer par sk_test_ (test) ou sk_live_ (production).';
                                } else {
                                    $amountCents = (int) round($total * 100);
                                    if ($amountCents < 50) $amountCents = 50;
                                    $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
                                    $scriptPath = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
                                    $basePath = rtrim($baseUrl, '/') . ($scriptPath === '/' ? '' : $scriptPath);
                                    $successUrl = $basePath . '/registration-payment-success.php?session_id={CHECKOUT_SESSION_ID}&registration_id=' . $registrationId;
                                    $cancelUrl = $basePath . '/registration-next-session.php';
                                    // Stripe API v1 attend form-urlencoded, pas JSON
                                    $postFields = [
                                        'mode' => 'payment',
                                        'payment_method_types[]' => 'card',
                                        'line_items[0][price_data][currency]' => 'usd',
                                        'line_items[0][price_data][product_data][name]' => 'Registration for Next Session',
                                        'line_items[0][price_data][product_data][description]' => 'NCLEX Session Registration - Orchidee LLC',
                                        'line_items[0][price_data][unit_amount]' => $amountCents,
                                        'line_items[0][quantity]' => 1,
                                        'success_url' => $successUrl,
                                        'cancel_url' => $cancelUrl,
                                        'client_reference_id' => (string) $registrationId,
                                    ];
                                    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
                                    curl_setopt_array($ch, [
                                        CURLOPT_RETURNTRANSFER => true,
                                        CURLOPT_POST => true,
                                        CURLOPT_POSTFIELDS => http_build_query($postFields),
                                        CURLOPT_USERPWD => $secretKey . ':',
                                        CURLOPT_HTTPHEADER => [
                                            'Content-Type: application/x-www-form-urlencoded',
                                        ],
                                        CURLOPT_TIMEOUT => 30,
                                        CURLOPT_SSL_VERIFYPEER => true,
                                    ]);
                                    $stripeResponse = curl_exec($ch);
                                    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                    $curlErr = curl_error($ch);
                                    curl_close($ch);
                                    if ($stripeResponse === false) {
                                        $stripeMsg = 'Connexion à Stripe impossible. ' . ($curlErr ?: 'Vérifiez votre connexion internet.');
                                        $stripeDebug = 'cURL: ' . $curlErr;
                                    } elseif ($httpCode >= 200 && $httpCode < 300) {
                                        $sessionData = json_decode($stripeResponse, true);
                                        if (!empty($sessionData['url'])) {
                                            unset($_SESSION['registration_data']);
                                            header('Location: ' . $sessionData['url']);
                                            exit;
                                        }
                                        $stripeMsg = 'Réponse Stripe sans URL de paiement.';
                                        $stripeDebug = 'HTTP ' . $httpCode;
                                    } else {
                                        $errData = json_decode($stripeResponse, true);
                                        $stripeMsg = $errData['error']['message'] ?? $errData['message'] ?? 'Erreur Stripe (HTTP ' . $httpCode . ').';
                                        $stripeDebug = 'HTTP ' . $httpCode . ' - ' . substr($stripeResponse, 0, 300);
                                    }
                                }
                                // Stripe choisi mais redirection non faite : ne pas aller à la page de remerciement
                                if ($paymentMethod === 'stripe') {
                                    $error = 'Impossible de lancer le paiement par carte. ' . $stripeMsg . ($stripeDebug !== null ? ' [Debug: ' . htmlspecialchars($stripeDebug) . ']' : '');
                                    //’                                }
                                }
                            }
                            // PayPal : même principe que Stripe (config admin, redirect checkout)
                            if ($paymentMethod === 'paypal') {
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
                                $sandbox = (isset($paypalConfig['mode']) && strtolower($paypalConfig['mode']) === 'live') ? false : true;
                                if ($clientId && $clientSecret) {
                                    $tokenResult = paypal_get_access_token($clientId, $clientSecret, $sandbox);
                                    $token = $tokenResult['token'] ?? null;
                                    if ($token) {
                                        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
                                        $scriptPath = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
                                        $basePath = rtrim($baseUrl, '/') . ($scriptPath === '/' ? '' : $scriptPath);
                                        $returnUrl = $basePath . '/registration-payment-success-paypal.php?registration_id=' . $registrationId;
                                        $cancelUrl = $basePath . '/registration-next-session.php';
                                        $amountStr = number_format($total, 2, '.', '');
                                        $orderResult = paypal_create_order($token, $amountStr, $returnUrl, $cancelUrl, 'Registration for Next Session', $sandbox);
                                        if (!empty($orderResult['url'])) {
                                            unset($_SESSION['registration_data']);
                                            header('Location: ' . $orderResult['url']);
                                            exit;
                                        }
                                        $error = 'PayPal : ' . ($orderResult['error'] ?? 'Impossible de créer la commande.');
                                    } else {
                                        $error = 'PayPal : ' . ($tokenResult['error'] ?? 'Impossible d\'obtenir l\'accès. Vérifiez Client ID et Secret (sandbox vs live).');
                                    }
                                } else {
                                    $error = 'PayPal non configuré. Allez dans Admin → Paramètres de paiement (Client ID et Secret).';
                                }
                            }
                            if ($paymentMethod !== 'stripe' && $paymentMethod !== 'paypal' || !empty($error)) {
                                if ($paymentMethod !== 'stripe' && $paymentMethod !== 'paypal') {
                                    if (in_array($paymentMethod, ['zelle', 'cashapp', 'bank_deposit']) && !empty($transactionId)) {
                                        $updateStmt = $conn->prepare("UPDATE session_registrations SET payment_transaction_id = ? WHERE id = ?");
                                        $updateStmt->bind_param("si", $transactionId, $registrationId);
                                        $updateStmt->execute();
                                        $updateStmt->close();
                                    }
                                    unset($_SESSION['registration_data']);
                                    header("Location: registration-thank-you.php?id=" . $registrationId);
                                    exit;
                                }
                            }
                        } else {
                            $error = 'Registration failed: ' . $stmt->error . '. Please try again.';
                            $stmt->close();
                        }
                    } else {
                        $error = 'Database error: ' . $conn->error . '. Please try again.';
                    }
                } else {
                    $error = 'Database table not found. Please run create-session-registrations-table.php first.';
                }
            }
        }
    } elseif (isset($_POST['step']) && $_POST['step'] == '2' && !isset($_SESSION['registration_data'])) {
        $error = 'Session expired. Please start over from step 1.';
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="utf-8">
        <title>Registration for the Next Session - Orchidee LLC</title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
        <meta content="Register for the next NCLEX review session at Orchidee LLC" name="description">

        <!-- Google Web Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Inter:slnt,wght@-10..0,100..900&display=swap" rel="stylesheet">

        <!-- Favicon -->
        <link rel="icon" type="image/png" href="img/orchideelogo.png">
        <link rel="apple-touch-icon" href="img/orchideelogo.png">

        <!-- Icon Font Stylesheet -->
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"/>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

        <!-- Libraries Stylesheet -->
        <link rel="stylesheet" href="lib/animate/animate.min.css"/>
        <link href="lib/lightbox/css/lightbox.min.css" rel="stylesheet">
        <link href="lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">

        <!-- Customized Bootstrap Stylesheet -->
        <link href="css/bootstrap.min.css" rel="stylesheet">

        <!-- Template Stylesheet -->
        <link href="css/style.css" rel="stylesheet">
        
        <style>
            .step-indicator {
                display: flex;
                justify-content: center;
                margin-bottom: 30px;
            }
            .step {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background: #e9ecef;
                color: #6c757d;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                margin: 0 10px;
                position: relative;
            }
            .step.active {
                background: var(--bs-primary);
                color: white;
            }
            .step.completed {
                background: #28a745;
                color: white;
            }
            .step::after {
                content: '';
                position: absolute;
                top: 50%;
                left: 100%;
                width: 20px;
                height: 2px;
                background: #e9ecef;
                transform: translateY(-50%);
            }
            .step:last-child::after {
                display: none;
            }
            .step.completed::after {
                background: #28a745;
            }
            .form-step {
                display: none;
            }
            .form-step.active {
                display: block;
            }
        </style>
    </head>

    <body>

        <!-- Spinner Start -->
        <div id="spinner" class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
        <!-- Spinner End -->


        <?php include 'includes/promo-banner.php'; ?>
        <?php include 'includes/menu-dynamic.php'; ?>


        <!-- Header Start -->
        <div class="container-fluid bg-breadcrumb" style="background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('img/ban.jpeg') center/cover no-repeat;">
            <div class="container text-center py-5" style="max-width: 900px;">
                <h4 class="text-white display-4 mb-4 wow fadeInDown" data-wow-delay="0.1s">Registration for the Next Session</h4>
                <ol class="breadcrumb d-flex justify-content-center mb-0 wow fadeInDown" data-wow-delay="0.3s">
                    <li class="breadcrumb-item"><a href="index.php" class="text-white">Home</a></li>
                    <li class="breadcrumb-item active text-primary">Registration for the Next Session</li>
                </ol>    
            </div>
        </div>
        <!-- Header End -->


        <!-- Registration Form Start -->
        <div class="container-fluid bg-light py-5">
            <div class="container py-5">
                <div class="row">
                    <div class="col-lg-10 mx-auto">
                        <div class="bg-white rounded shadow-sm p-5 wow fadeInUp" data-wow-delay="0.1s">
                            
                            <!-- Step Indicator -->
                            <div class="step-indicator mb-4">
                                <div class="step active" id="step1-indicator">1</div>
                                <div class="step" id="step2-indicator">2</div>
                            </div>

                            <?php if ($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fa fa-exclamation-circle me-2"></i>
                                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($_POST['step']) && $_POST['step'] == '2' && $error): ?>
                                <div class="alert alert-warning">
                                    <i class="fa fa-info-circle me-2"></i>
                                    <strong>Debug Info:</strong> Step received: <?php echo htmlspecialchars($_POST['step'] ?? 'NOT SET'); ?>, 
                                    Payment Method: <?php echo htmlspecialchars($_POST['payment_method'] ?? 'NOT SET'); ?>, 
                                    Agreement: <?php echo isset($_POST['agreement']) ? 'Checked' : 'NOT Checked'; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($success): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fa fa-check-circle me-2"></i>
                                    <?php echo htmlspecialchars($success); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <form id="registrationForm" method="POST" action="">
                                
                                <!-- User Info Notice -->
                                <div class="alert alert-info mb-4">
                                    <i class="fa fa-user-check me-2"></i>
                                    <strong>Logged in as:</strong> <?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['email'] ?? 'User'); ?>
                                </div>
                                
                                <!-- Step 1: Personal Information -->
                                <div class="form-step active" id="step1">
                                    <h3 class="text-primary mb-4">Step 1: Personal Information</h3>
                                    
                                    <!-- Payment Summary Info Box -->
                                    <div class="alert alert-info mb-4">
                                        <h6 class="alert-heading mb-3"><i class="fa fa-info-circle me-2"></i>Registration Fee Information</h6>
                                        <div class="row mb-0">
                                            <div class="col-md-6">
                                                <small><strong>Registration Fee:</strong> $50.00</small><br>
                                                <small><strong>Tax:</strong> $3.99</small>
                                            </div>
                                            <div class="col-md-6 text-md-end">
                                                <small><strong>Total Amount:</strong> <span class="text-primary fw-bold">$53.99</span></small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="first_name" 
                                                   value="<?php echo htmlspecialchars($_SESSION['registration_data']['first_name'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="last_name" 
                                                   value="<?php echo htmlspecialchars($_SESSION['registration_data']['last_name'] ?? ''); ?>" required>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Email <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" name="email" 
                                                   value="<?php echo htmlspecialchars($_SESSION['registration_data']['email'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Phone <span class="text-danger">*</span></label>
                                            <input type="tel" class="form-control" name="phone" 
                                                   value="<?php echo htmlspecialchars($_SESSION['registration_data']['phone'] ?? ''); ?>" required>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Nursing School Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="nursing_school" 
                                               value="<?php echo htmlspecialchars($_SESSION['registration_data']['nursing_school'] ?? ''); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Years Attended <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="years_attended" 
                                               value="<?php echo htmlspecialchars($_SESSION['registration_data']['years_attended'] ?? ''); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Select One Session From The Following Options <span class="text-danger">*</span></label>
                                        <select class="form-select" name="session_duration" required>
                                            <option value="">-- Select Session --</option>
                                            <option value="3 Months" <?php echo (isset($_SESSION['registration_data']['session_duration']) && $_SESSION['registration_data']['session_duration'] == '3 Months') ? 'selected' : ''; ?>>3 Months</option>
                                            <option value="6 Months" <?php echo (isset($_SESSION['registration_data']['session_duration']) && $_SESSION['registration_data']['session_duration'] == '6 Months') ? 'selected' : ''; ?>>6 Months</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Have You Started The Process For Your Credentials <span class="text-danger">*</span></label>
                                        <div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="credentials_started" value="Yes" 
                                                       <?php echo (isset($_SESSION['registration_data']['credentials_started']) && $_SESSION['registration_data']['credentials_started'] == 'Yes') ? 'checked' : ''; ?> required>
                                                <label class="form-check-label">Yes</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="credentials_started" value="No" 
                                                       <?php echo (isset($_SESSION['registration_data']['credentials_started']) && $_SESSION['registration_data']['credentials_started'] == 'No') ? 'checked' : ''; ?> required>
                                                <label class="form-check-label">No</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">What Motivates You To Participate In The Review?</label>
                                        <textarea class="form-control" name="motivation" rows="3"><?php echo htmlspecialchars($_SESSION['registration_data']['motivation'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Do you have any comments or questions?</label>
                                        <textarea class="form-control" name="comments" rows="3"><?php echo htmlspecialchars($_SESSION['registration_data']['comments'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="d-flex justify-content-end">
                                        <button type="button" class="btn btn-primary btn-lg px-5" onclick="goToStep2()">
                                            Next: Payment <i class="fa fa-arrow-right ms-2"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Step 2: Payment -->
                                <div class="form-step" id="step2">
                                    <h3 class="text-primary mb-4">Step 2: Payment & Agreement</h3>
                                    
                                    <!-- Payment Summary -->
                                    <div class="card mb-4 border-primary shadow-sm">
                                        <div class="card-header bg-primary text-white">
                                            <h5 class="card-title mb-0"><i class="fa fa-receipt me-2"></i>Payment Summary</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row mb-2">
                                                <div class="col-6">
                                                    <span class="text-muted">Registration Fee:</span>
                                                </div>
                                                <div class="col-6 text-end">
                                                    <strong>$50.00</strong>
                                                </div>
                                            </div>
                                            <div class="row mb-2">
                                                <div class="col-6">
                                                    <span class="text-muted">Tax:</span>
                                                </div>
                                                <div class="col-6 text-end">
                                                    <strong>$3.99</strong>
                                                </div>
                                            </div>
                                            <hr class="my-3">
                                            <div class="row">
                                                <div class="col-6">
                                                    <strong class="text-primary">Total:</strong>
                                                </div>
                                                <div class="col-6 text-end">
                                                    <strong class="text-primary fs-4">$53.99</strong>
                                                </div>
                                            </div>
                                            <div class="mt-3">
                                                <small class="text-muted">
                                                    <i class="fa fa-info-circle me-1"></i>
                                                    This is a fixed rate for all registrations.
                                                </small>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Payment Methods -->
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
                                                    <i class="fa fa-exclamation-triangle me-2"></i>Aucune méthode de paiement disponible. Veuillez contacter l'administrateur.
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Transaction ID for manual methods -->
                                    <div class="mb-4" id="transactionIdField" style="display: none;">
                                        <label class="form-label fw-bold">Transaction ID / Reference Number <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="transaction_id" id="transaction_id" placeholder="Enter transaction ID or reference number">
                                        <small class="text-muted">Please provide your payment transaction ID for verification.</small>
                                    </div>

                                    <!-- Agreement -->
                                    <div class="mb-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="agreement" id="agreement" required>
                                            <label class="form-check-label" for="agreement">
                                                <strong>Permission & Agreement <span class="text-danger">*</span></strong><br>
                                                <small class="text-muted">
                                                    I agree and give my permission. By submitting this form, you confirm that the information provided is accurate and complete to the best of your knowledge. You also agree to allow OrchideeLLC to contact you regarding your consultation and future updates related to our services. Your privacy is important to us, and your details will be handled with care and confidentiality.
                                                </small>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between">
                                        <button type="button" class="btn btn-outline-secondary btn-lg px-5" onclick="goToStep1()">
                                            <i class="fa fa-arrow-left me-2"></i>Back
                                        </button>
                                        <button type="submit" class="btn btn-primary btn-lg px-5" id="submitStep2" <?php echo empty($enabledMethods) ? 'disabled' : ''; ?>>
                                            <i class="fa fa-lock me-2"></i>Complete Registration
                                        </button>
                                    </div>
                                </div>

                                <input type="hidden" name="step" value="1" id="formStep">
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Registration Form End -->


        <?php include 'includes/footer.php'; ?>
        <?php include 'includes/chat-button.php'; ?>
        <a href="#" class="btn btn-primary btn-lg-square rounded-circle back-to-top"><i class="fa fa-arrow-up"></i></a>   


        <!-- JavaScript Libraries -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="lib/wow/wow.min.js"></script>
        <script src="lib/easing/easing.min.js"></script>
        <script src="lib/waypoints/waypoints.min.js"></script>
        <script src="lib/counterup/counterup.min.js"></script>
        <script src="lib/lightbox/js/lightbox.min.js"></script>
        <script src="lib/owlcarousel/owl.carousel.min.js"></script>
        

        <!-- Template Javascript -->
        <script src="js/main.js"></script>
        
        <script>
            function goToStep2() {
                // Validate step 1
                const form = document.getElementById('registrationForm');
                const step1Inputs = form.querySelectorAll('#step1 input[required], #step1 select[required]');
                let isValid = true;
                
                step1Inputs.forEach(input => {
                    if (input.type === 'radio') {
                        const radioGroup = form.querySelectorAll('input[name="' + input.name + '"]');
                        const isRadioChecked = Array.from(radioGroup).some(radio => radio.checked);
                        if (!isRadioChecked) {
                            isValid = false;
                        }
                    } else if (!input.value.trim()) {
                        isValid = false;
                        input.classList.add('is-invalid');
                    } else {
                        input.classList.remove('is-invalid');
                    }
                });
                
                if (isValid) {
                    // Submit step 1
                    document.getElementById('formStep').value = '1';
                    form.submit();
                } else {
                    alert('Please fill in all required fields.');
                }
            }
            
            function goToStep1() {
                document.getElementById('step1').classList.add('active');
                document.getElementById('step2').classList.remove('active');
                document.getElementById('step1-indicator').classList.add('active');
                document.getElementById('step2-indicator').classList.remove('active');
            }
            
            // Show step 2 if coming from step 1 submission OR if there's an error on step 2
            <?php if (isset($_SESSION['registration_data'])): ?>
                document.getElementById('step1').classList.remove('active');
                document.getElementById('step2').classList.add('active');
                document.getElementById('step1-indicator').classList.remove('active');
                document.getElementById('step1-indicator').classList.add('completed');
                document.getElementById('step2-indicator').classList.add('active');
            <?php endif; ?>
            
            // Handle step 2 submission
            document.getElementById('submitStep2').addEventListener('click', function(e) {
                e.preventDefault();
                
                // Check if payment method is selected
                const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
                if (!paymentMethod) {
                    alert('Please select a payment method.');
                    return false;
                }
                
                // Check if agreement is checked
                const agreement = document.getElementById('agreement');
                if (!agreement.checked) {
                    alert('You must agree to the terms and conditions to proceed.');
                    agreement.focus();
                    return false;
                }
                
                // Check if transaction ID is required for manual methods
                const method = paymentMethod.value;
                if (['zelle', 'cashapp', 'bank_deposit'].includes(method)) {
                    const transactionId = document.querySelector('input[name="transaction_id"]');
                    if (!transactionId || !transactionId.value.trim()) {
                        alert('Please provide a transaction ID or reference number.');
                        if (transactionId) transactionId.focus();
                        return false;
                    }
                }
                
                // Set step to 2 and submit
                document.getElementById('formStep').value = '2';
                document.getElementById('registrationForm').submit();
            });
            
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
