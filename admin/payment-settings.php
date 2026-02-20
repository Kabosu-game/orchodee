<?php
require_once '../includes/admin_check.php';

$conn = getDBConnection();
$success = '';
$error = '';

// Create table if it doesn't exist (with all payment methods)
$conn->query("CREATE TABLE IF NOT EXISTS payment_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_method VARCHAR(50) NOT NULL UNIQUE,
    is_enabled BOOLEAN DEFAULT FALSE,
    config_data TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Allow manual methods: ensure column accepts zelle, cashapp, bank_deposit (convert ENUM to VARCHAR if needed)
@$conn->query("ALTER TABLE payment_config MODIFY payment_method VARCHAR(50) NOT NULL UNIQUE");

// Insert default configurations if they don't exist (all 5 methods)
$conn->query("INSERT IGNORE INTO payment_config (payment_method, is_enabled, config_data) VALUES
('stripe', FALSE, '{\"publishable_key\":\"\",\"secret_key\":\"\",\"webhook_secret\":\"\"}'),
('paypal', FALSE, '{\"client_id\":\"\",\"client_secret\":\"\",\"mode\":\"sandbox\"}'),
('zelle', TRUE, '{}'),
('cashapp', TRUE, '{}'),
('bank_deposit', TRUE, '{}')");

// Process form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Charger la config Stripe actuelle pour ne pas écraser les clés si les champs sont laissés vides
    $currentStripe = ['publishable_key' => '', 'secret_key' => '', 'webhook_secret' => ''];
    $r = $conn->query("SELECT config_data FROM payment_config WHERE payment_method = 'stripe' LIMIT 1");
    if ($r && $row = $r->fetch_assoc() && !empty($row['config_data'])) {
        $dec = json_decode($row['config_data'], true);
        if (is_array($dec)) {
            $currentStripe = array_merge($currentStripe, $dec);
        }
    }
    
    $stripeEnabled = isset($_POST['stripe_enabled']) ? 1 : 0;
    $stripePublishableKey = trim(sanitize($_POST['stripe_publishable_key'] ?? ''));
    $stripeSecretKey = trim(sanitize($_POST['stripe_secret_key'] ?? ''));
    $stripeWebhookSecret = trim(sanitize($_POST['stripe_webhook_secret'] ?? ''));
    // Ne pas écraser par une chaîne vide si l'admin n'a pas re-saisi la clé
    if ($stripePublishableKey === '') {
        $stripePublishableKey = $currentStripe['publishable_key'] ?? '';
    }
    if ($stripeSecretKey === '') {
        $stripeSecretKey = $currentStripe['secret_key'] ?? '';
    }
    if ($stripeWebhookSecret === '') {
        $stripeWebhookSecret = $currentStripe['webhook_secret'] ?? '';
    }
    
    // Charger la config PayPal actuelle pour ne pas écraser les clés si les champs sont vides
    $currentPaypal = ['client_id' => '', 'client_secret' => '', 'mode' => 'sandbox'];
    $rp = $conn->query("SELECT config_data FROM payment_config WHERE payment_method = 'paypal' LIMIT 1");
    if ($rp && $rowp = $rp->fetch_assoc() && !empty($rowp['config_data'])) {
        $decp = json_decode($rowp['config_data'], true);
        if (is_array($decp)) {
            $currentPaypal = array_merge($currentPaypal, $decp);
        }
    }
    
    $paypalEnabled = isset($_POST['paypal_enabled']) ? 1 : 0;
    $paypalClientId = trim(sanitize($_POST['paypal_client_id'] ?? ''));
    $paypalClientSecret = trim(sanitize($_POST['paypal_client_secret'] ?? ''));
    $paypalMode = trim(sanitize($_POST['paypal_mode'] ?? 'sandbox'));
    if ($paypalClientId === '') {
        $paypalClientId = $currentPaypal['client_id'] ?? '';
    }
    if ($paypalClientSecret === '') {
        $paypalClientSecret = $currentPaypal['client_secret'] ?? '';
    }
    if ($paypalMode === '') {
        $paypalMode = $currentPaypal['mode'] ?? 'sandbox';
    }
    
    $zelleEnabled = isset($_POST['zelle_enabled']) ? 1 : 0;
    $cashappEnabled = isset($_POST['cashapp_enabled']) ? 1 : 0;
    $bankDepositEnabled = isset($_POST['bank_deposit_enabled']) ? 1 : 0;
    
    // Update Stripe
    $stripeConfig = json_encode([
        'publishable_key' => $stripePublishableKey,
        'secret_key' => $stripeSecretKey,
        'webhook_secret' => $stripeWebhookSecret
    ]);
    
    $stmt = $conn->prepare("UPDATE payment_config SET is_enabled = ?, config_data = ? WHERE payment_method = 'stripe'");
    $stmt->bind_param("is", $stripeEnabled, $stripeConfig);
    $stmt->execute();
    $stmt->close();
    
    // Update PayPal
    $paypalConfig = json_encode([
        'client_id' => $paypalClientId,
        'client_secret' => $paypalClientSecret,
        'mode' => $paypalMode
    ]);
    
    $stmt = $conn->prepare("UPDATE payment_config SET is_enabled = ?, config_data = ? WHERE payment_method = 'paypal'");
    $stmt->bind_param("is", $paypalEnabled, $paypalConfig);
    $stmt->execute();
    $stmt->close();
    
    // Update manual methods
    $stmt = $conn->prepare("UPDATE payment_config SET is_enabled = ? WHERE payment_method = 'zelle'");
    $stmt->bind_param("i", $zelleEnabled);
    $stmt->execute();
    $stmt->close();
    
    $stmt = $conn->prepare("UPDATE payment_config SET is_enabled = ? WHERE payment_method = 'cashapp'");
    $stmt->bind_param("i", $cashappEnabled);
    $stmt->execute();
    $stmt->close();
    
    $stmt = $conn->prepare("UPDATE payment_config SET is_enabled = ? WHERE payment_method = 'bank_deposit'");
    $stmt->bind_param("i", $bankDepositEnabled);
    $stmt->execute();
    $stmt->close();
    
    $success = 'Payment settings updated successfully!';
}

// Get current configurations
$stripeConfig = ['publishable_key' => '', 'secret_key' => '', 'webhook_secret' => ''];
$paypalConfig = ['client_id' => '', 'client_secret' => '', 'mode' => 'sandbox'];
$stripeEnabled = false;
$paypalEnabled = false;
$zelleEnabled = true;
$cashappEnabled = true;
$bankDepositEnabled = true;

$result = $conn->query("SELECT * FROM payment_config");
while ($row = $result->fetch_assoc()) {
    if ($row['payment_method'] === 'stripe') {
        $stripeEnabled = (bool)$row['is_enabled'];
        $stripeConfig = json_decode($row['config_data'], true) ?: $stripeConfig;
    } elseif ($row['payment_method'] === 'paypal') {
        $paypalEnabled = (bool)$row['is_enabled'];
        $paypalConfig = json_decode($row['config_data'], true) ?: $paypalConfig;
    } elseif ($row['payment_method'] === 'zelle') {
        $zelleEnabled = (bool)$row['is_enabled'];
    } elseif ($row['payment_method'] === 'cashapp') {
        $cashappEnabled = (bool)$row['is_enabled'];
    } elseif ($row['payment_method'] === 'bank_deposit') {
        $bankDepositEnabled = (bool)$row['is_enabled'];
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payment Settings - Admin - Orchidee LLC</title>
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

    <!-- Payment Settings Start -->
    <div class="container-fluid bg-light py-5">
        <div class="container py-5">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="text-primary mb-0">
                            <i class="fa fa-credit-card me-2"></i>Payment Settings
                        </h2>
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="fa fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fa fa-check-circle me-2"></i><?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa fa-exclamation-circle me-2"></i><?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="row">
                    <!-- Stripe Configuration -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fab fa-stripe me-2"></i>Stripe Configuration
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="stripe_enabled" name="stripe_enabled" <?php echo $stripeEnabled ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="stripe_enabled">
                                        Enable Stripe Payments
                                    </label>
                                </div>

                                <div class="mb-3">
                                    <label for="stripe_publishable_key" class="form-label">Publishable Key</label>
                                    <input type="text" class="form-control" id="stripe_publishable_key" name="stripe_publishable_key" 
                                           value="<?php echo htmlspecialchars($stripeConfig['publishable_key']); ?>" 
                                           placeholder="pk_test_...">
                                    <small class="text-muted">Get this from your Stripe Dashboard</small>
                                </div>

                                <div class="mb-3">
                                    <label for="stripe_secret_key" class="form-label">Secret Key</label>
                                    <input type="password" class="form-control" id="stripe_secret_key" name="stripe_secret_key" 
                                           value="<?php echo htmlspecialchars($stripeConfig['secret_key']); ?>" 
                                           placeholder="sk_test_... ou sk_live_...">
                                    <small class="text-muted">Clé secrète Stripe (sk_test_ ou sk_live_). Saisissez-la puis cliquez Enregistrer. Si vous laissez le champ vide, la clé actuelle est conservée.</small>
                                </div>

                                <div class="mb-3">
                                    <label for="stripe_webhook_secret" class="form-label">Webhook Secret (Optional)</label>
                                    <input type="password" class="form-control" id="stripe_webhook_secret" name="stripe_webhook_secret" 
                                           value="<?php echo htmlspecialchars($stripeConfig['webhook_secret']); ?>" 
                                           placeholder="whsec_...">
                                    <small class="text-muted">For webhook verification</small>
                                </div>

                                <div class="alert alert-info">
                                    <i class="fa fa-info-circle me-2"></i>
                                    <strong>Stripe Setup:</strong> 
                                    <ol class="mb-0 mt-2">
                                        <li>Create an account at <a href="https://stripe.com" target="_blank">stripe.com</a></li>
                                        <li>Get your API keys from the Dashboard</li>
                                        <li>Use test keys for development, live keys for production</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- PayPal Configuration -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">
                                    <i class="fab fa-paypal me-2"></i>PayPal Configuration
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="paypal_enabled" name="paypal_enabled" <?php echo $paypalEnabled ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="paypal_enabled">
                                        Enable PayPal Payments
                                    </label>
                                </div>

                                <div class="mb-3">
                                    <label for="paypal_client_id" class="form-label">Client ID</label>
                                    <input type="text" class="form-control" id="paypal_client_id" name="paypal_client_id" 
                                           value="<?php echo htmlspecialchars($paypalConfig['client_id']); ?>" 
                                           placeholder="Your PayPal Client ID">
                                    <small class="text-muted">Get this from PayPal Developer Dashboard</small>
                                </div>

                                <div class="mb-3">
                                    <label for="paypal_client_secret" class="form-label">Client Secret</label>
                                    <input type="password" class="form-control" id="paypal_client_secret" name="paypal_client_secret" 
                                           value="<?php echo htmlspecialchars($paypalConfig['client_secret']); ?>" 
                                           placeholder="Your PayPal Client Secret">
                                    <small class="text-muted">Keep this secret!</small>
                                </div>

                                <div class="mb-3">
                                    <label for="paypal_mode" class="form-label">Mode</label>
                                    <select class="form-select" id="paypal_mode" name="paypal_mode">
                                        <option value="sandbox" <?php echo $paypalConfig['mode'] === 'sandbox' ? 'selected' : ''; ?>>Sandbox (Testing)</option>
                                        <option value="live" <?php echo $paypalConfig['mode'] === 'live' ? 'selected' : ''; ?>>Live (Production)</option>
                                    </select>
                                    <small class="text-muted">Use Sandbox for testing, Live for production</small>
                                </div>

                                <div class="alert alert-info">
                                    <i class="fa fa-info-circle me-2"></i>
                                    <strong>PayPal Setup:</strong> 
                                    <ol class="mb-0 mt-2">
                                        <li>Create an app at <a href="https://developer.paypal.com" target="_blank">developer.paypal.com</a></li>
                                        <li>Get your Client ID and Secret</li>
                                        <li>Use Sandbox mode for testing</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Manual payment methods -->
                <div class="row">
                    <div class="col-12 mb-4">
                        <div class="card">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="mb-0">
                                    <i class="fa fa-money-bill-alt me-2"></i>Méthodes manuelles (vérification manuelle)
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3">Activez ou désactivez chaque méthode. Si toutes sont désactivées, seules les options Stripe/PayPal (si activées) seront proposées sur les formulaires.</p>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="zelle_enabled" name="zelle_enabled" <?php echo $zelleEnabled ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="zelle_enabled">Zelle</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="cashapp_enabled" name="cashapp_enabled" <?php echo $cashappEnabled ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="cashapp_enabled">Cash App</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="bank_deposit_enabled" name="bank_deposit_enabled" <?php echo $bankDepositEnabled ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="bank_deposit_enabled">Bank Deposit</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="d-flex justify-content-end gap-2">
                            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-save me-2"></i>Save Settings
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <!-- Payment Settings End -->

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

