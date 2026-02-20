<?php
/**
 * Retour PayPal pour l'inscription NCLEX.
 * Capture la commande PayPal et met à jour nclex_registrations en "completed".
 */
require_once 'config/database.php';

$token = $_GET['token'] ?? '';
$registrationId = (int) ($_GET['registration_id'] ?? 0);

if (!$token || !$registrationId) {
    header('Location: nclex-registration-form.php');
    exit;
}

$conn = getDBConnection();

$stmt = $conn->prepare("SELECT id, payment_status FROM nclex_registrations WHERE id = ?");
$stmt->bind_param("i", $registrationId);
$stmt->execute();
$result = $stmt->get_result();
$registration = $result->fetch_assoc();
$stmt->close();

if (!$registration) {
    header('Location: nclex-registration-form.php');
    exit;
}

$paypalConfig = null;
$pr = $conn->query("SELECT config_data FROM payment_config WHERE payment_method = 'paypal' LIMIT 1");
if ($pr && $rowp = $pr->fetch_assoc() && !empty(trim($rowp['config_data'] ?? ''))) {
    $paypalConfig = json_decode($rowp['config_data'], true);
}

if ($paypalConfig && !empty(trim($paypalConfig['client_id'] ?? '')) && !empty(trim($paypalConfig['client_secret'] ?? ''))) {
    require_once __DIR__ . '/includes/paypal_helper.php';
    $sandbox = (isset($paypalConfig['mode']) && strtolower($paypalConfig['mode']) === 'live') ? false : true;
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
            $updateStmt = $conn->prepare("UPDATE nclex_registrations SET payment_status = 'completed', payment_transaction_id = ? WHERE id = ?");
            $updateStmt->bind_param("si", $token, $registrationId);
            $updateStmt->execute();
            $updateStmt->close();
        }
    }
}

$conn->close();
header('Location: nclex-registration-thank-you.php?id=' . $registrationId);
exit;
