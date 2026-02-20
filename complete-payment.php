<?php
error_reporting(0);
ini_set('display_errors', '0');
ob_start();
require_once 'includes/auth_check.php';
require_once 'includes/payment_functions.php';
header('Content-Type: application/json');
if (ob_get_level()) ob_end_clean();

$conn = getDBConnection();
$userId = getUserId();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$purchaseId = intval($_POST['purchase_id'] ?? 0);
$paymentMethod = sanitize($_POST['payment_method'] ?? '');
$transactionId = sanitize($_POST['transaction_id'] ?? '');

if (!$purchaseId || !$paymentMethod) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

// Verify that purchase belongs to user
$stmt = $conn->prepare("SELECT * FROM purchases WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $purchaseId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$purchase = $result->fetch_assoc();
$stmt->close();

if (!$purchase) {
    echo json_encode(['success' => false, 'error' => 'Purchase not found']);
    exit;
}

// Mettre à jour le statut du paiement en 'pending' - nécessite validation admin
$stmt = $conn->prepare("UPDATE purchases SET payment_status = 'pending', payment_transaction_id = ? WHERE id = ?");
$stmt->bind_param("si", $transactionId, $purchaseId);
$stmt->execute();
$stmt->close();

echo json_encode([
    'success' => true,
    'message' => 'Payment submitted successfully. Your payment is pending admin approval. You will receive access once approved.',
    'course_id' => $purchase['course_id'],
    'status' => 'pending'
]);

$conn->close();
?>

