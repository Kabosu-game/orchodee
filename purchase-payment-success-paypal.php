<?php
/**
 * Retour PayPal après paiement d'un cours.
 * Capture la commande PayPal et met à jour l'achat en "completed".
 * Même logique que registration-payment-success-paypal.php pour les formulaires.
 */
require_once 'config/database.php';
require_once 'includes/auth_check.php';

$token = $_GET['token'] ?? '';
$purchaseId = (int) ($_GET['purchase_id'] ?? 0);

if (!$token || !$purchaseId) {
    header('Location: payment-history.php');
    exit;
}

$conn = getDBConnection();
$userId = getUserId();

$stmt = $conn->prepare("SELECT id, user_id, payment_status FROM purchases WHERE id = ?");
$stmt->bind_param("i", $purchaseId);
$stmt->execute();
$result = $stmt->get_result();
$purchase = $result->fetch_assoc();
$stmt->close();

if (!$purchase) {
    $conn->close();
    header('Location: payment-history.php');
    exit;
}

if ((int) $purchase['user_id'] !== (int) $userId) {
    $conn->close();
    header('Location: payment-history.php');
    exit;
}

$paypalConfig = null;
$pr = $conn->query("SELECT config_data FROM payment_config WHERE payment_method = 'paypal' LIMIT 1");
if ($pr && $rowp = $pr->fetch_assoc() && !empty(trim($rowp['config_data'] ?? ''))) {
    $paypalConfig = json_decode($rowp['config_data'], true);
}

if ($paypalConfig && !empty(trim($paypalConfig['client_id'] ?? '')) && !empty(trim($paypalConfig['client_secret'] ?? ''))) {
    require_once __DIR__ . '/includes/paypal_helper.php';
    $sandbox = (isset($paypalConfig['mode']) && strtolower($paypalConfig['mode'] ?? '') === 'live') ? false : true;
    $tokenResult = paypal_get_access_token(trim($paypalConfig['client_id']), trim($paypalConfig['client_secret']), $sandbox);
    $accessToken = $tokenResult['token'] ?? null;
    if ($accessToken) {
        $baseUrl = $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
        $ch = curl_init($baseUrl . '/v2/checkout/orders/' . $token . '/capture');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => '{}',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $accessToken,
            ],
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode >= 200 && $httpCode < 300) {
            $updateStmt = $conn->prepare("UPDATE purchases SET payment_status = 'completed', payment_transaction_id = ? WHERE id = ?");
            $updateStmt->bind_param("si", $token, $purchaseId);
            $updateStmt->execute();
            $updateStmt->close();
        }
    }
}

$conn->close();
header('Location: payment-history.php?success=1');
exit;
