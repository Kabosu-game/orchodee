<?php
require_once 'includes/auth_check.php';
require_once 'includes/payment_functions.php';

$sessionId = $_GET['session_id'] ?? '';
$purchaseId = intval($_GET['purchase_id'] ?? 0);

if (!$sessionId || !$purchaseId) {
    redirect('courses.php');
}

$conn = getDBConnection();
$userId = getUserId();

// Verify that purchase belongs to user
$stmt = $conn->prepare("SELECT * FROM purchases WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $purchaseId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$purchase = $result->fetch_assoc();
$stmt->close();

if (!$purchase) {
    redirect('courses.php');
}

// Verify Stripe session (get config with or without "Enable" checked)
$stripeConfig = null;
$stripeRow = $conn->query("SELECT config_data FROM payment_config WHERE payment_method = 'stripe' LIMIT 1");
if ($stripeRow && $row = $stripeRow->fetch_assoc() && !empty($row['config_data'])) {
    $stripeConfig = json_decode($row['config_data'], true);
}
if ($stripeConfig && !empty(trim($stripeConfig['secret_key'] ?? ''))) {
    $secretKey = trim($stripeConfig['secret_key']);
    $ch = curl_init('https://api.stripe.com/v1/checkout/sessions/' . $sessionId);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $secretKey . ':',
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response && $httpCode === 200) {
        $session = json_decode($response, true);
        if (!empty($session['payment_status']) && $session['payment_status'] === 'paid') {
            $updateStmt = $conn->prepare("UPDATE purchases SET payment_status = 'completed', payment_transaction_id = ? WHERE id = ?");
            $updateStmt->bind_param("si", $sessionId, $purchaseId);
            $updateStmt->execute();
            $updateStmt->close();
            redirect('payment-history.php?success=1');
        }
    }
}

$conn->close();
// Si pas de session Stripe valide, rediriger vers l'historique
redirect('payment-history.php');
?>

