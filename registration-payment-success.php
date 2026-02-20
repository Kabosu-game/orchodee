<?php
/**
 * Page de retour Stripe Checkout pour l'inscription "Registration for the Next Session".
 * Vérifie le paiement Stripe et met à jour l'inscription en "completed", puis redirige vers la page de remerciement.
 */
require_once 'config/database.php';
require_once 'includes/auth_check.php';
require_once 'includes/payment_functions.php';

$sessionId = $_GET['session_id'] ?? '';
$registrationId = (int) ($_GET['registration_id'] ?? 0);

if (!$sessionId || !$registrationId) {
    header('Location: registration-next-session.php');
    exit;
}

$conn = getDBConnection();
$userId = getUserId();

// Vérifier que l'inscription existe et appartient à l'utilisateur
$stmt = $conn->prepare("SELECT id, user_id, payment_status FROM session_registrations WHERE id = ?");
$stmt->bind_param("i", $registrationId);
$stmt->execute();
$result = $stmt->get_result();
$registration = $result->fetch_assoc();
$stmt->close();

if (!$registration) {
    header('Location: registration-next-session.php');
    exit;
}

// Optionnel : vérifier user_id si la table a user_id (peut être null pour inscriptions sans compte)
if (!empty($registration['user_id']) && (int) $registration['user_id'] !== (int) $userId) {
    header('Location: registration-next-session.php');
    exit;
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
            $updateStmt = $conn->prepare("UPDATE session_registrations SET payment_status = 'completed', payment_transaction_id = ? WHERE id = ?");
            $updateStmt->bind_param("si", $sessionId, $registrationId);
            $updateStmt->execute();
            $updateStmt->close();
        }
    }
}

$conn->close();
header('Location: registration-thank-you.php?id=' . $registrationId);
exit;
