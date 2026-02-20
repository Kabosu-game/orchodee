<?php
/**
 * Functions to manage Stripe and PayPal payments
 */

/**
 * Get enabled payment methods
 */
function getEnabledPaymentMethods($conn) {
    $methods = [];
    
    // Check if table exists, create if not
    $tableCheck = $conn->query("SHOW TABLES LIKE 'payment_config'");
    if ($tableCheck->num_rows == 0) {
        $conn->query("CREATE TABLE IF NOT EXISTS payment_config (
            id INT AUTO_INCREMENT PRIMARY KEY,
            payment_method VARCHAR(50) NOT NULL UNIQUE,
            is_enabled BOOLEAN DEFAULT FALSE,
            config_data TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $stripeConfig = json_encode(['publishable_key' => '', 'secret_key' => '', 'webhook_secret' => '']);
        $paypalConfig = json_encode(['client_id' => '', 'client_secret' => '', 'mode' => 'sandbox']);
        $stmt = $conn->prepare("INSERT IGNORE INTO payment_config (payment_method, is_enabled, config_data) VALUES ('stripe', FALSE, ?), ('paypal', FALSE, ?), ('zelle', TRUE, '{}'), ('cashapp', TRUE, '{}'), ('bank_deposit', TRUE, '{}')");
        $stmt->bind_param("ss", $stripeConfig, $paypalConfig);
        $stmt->execute();
        $stmt->close();
    }
    
    // Return methods that are enabled (or have valid config for stripe/paypal)
    $result = $conn->query("SELECT * FROM payment_config");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $config = json_decode($row['config_data'], true);
            if (!$config) {
                $config = [];
            }
            $isEnabled = (bool)$row['is_enabled'];
            $hasValidConfig = false;
            if ($row['payment_method'] === 'stripe') {
                $hasValidConfig = !empty($config['publishable_key']) && !empty($config['secret_key']);
            } elseif ($row['payment_method'] === 'paypal') {
                $hasValidConfig = !empty($config['client_id']) && !empty($config['client_secret']);
            }
            // Manual methods (zelle, cashapp, bank_deposit): only if is_enabled
            if (in_array($row['payment_method'], ['zelle', 'cashapp', 'bank_deposit'])) {
                if ($isEnabled) {
                    $methods[$row['payment_method']] = ['method' => $row['payment_method'], 'config' => $config];
                }
            } elseif ($isEnabled || $hasValidConfig) {
                $methods[$row['payment_method']] = ['method' => $row['payment_method'], 'config' => $config];
            }
        }
    }
    return $methods;
}

/**
 * Get payment method configuration
 */
function getPaymentConfig($conn, $method) {
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'payment_config'");
    if ($tableCheck->num_rows == 0) {
        return null;
    }
    
    $stmt = $conn->prepare("SELECT * FROM payment_config WHERE payment_method = ? AND is_enabled = 1");
    $stmt->bind_param("s", $method);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return json_decode($row['config_data'], true);
    }
    return null;
}

/**
 * Check if a payment method is enabled
 */
function isPaymentMethodEnabled($conn, $method) {
    // Check if table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'payment_config'");
    if ($tableCheck->num_rows == 0) {
        return false;
    }
    
    $stmt = $conn->prepare("SELECT is_enabled FROM payment_config WHERE payment_method = ?");
    $stmt->bind_param("s", $method);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return (bool)$row['is_enabled'];
    }
    return false;
}

