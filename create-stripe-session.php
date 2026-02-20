<?php
error_reporting(0);
ini_set('display_errors', '0');
ob_start();
require_once 'includes/auth_check.php';
require_once 'includes/payment_functions.php';

$conn = getDBConnection();
$userId = getUserId();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$purchaseId = intval($_POST['purchase_id'] ?? 0);
$courseId = intval($_POST['course_id'] ?? 0);

if (!$purchaseId || !$courseId) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing purchase_id or course_id']);
    exit;
}

// Verify purchase belongs to user
$stmt = $conn->prepare("SELECT p.*, c.title as course_title, c.price FROM purchases p JOIN courses c ON p.course_id = c.id WHERE p.id = ? AND p.user_id = ? AND p.payment_status = 'pending'");
$stmt->bind_param("ii", $purchaseId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$purchase = $result->fetch_assoc();
$stmt->close();

if (!$purchase) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Purchase not found or already paid']);
    exit;
}

if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json');

// Get Stripe config from admin (payment_config) — must be enabled and keys set
$stripeConfig = null;
$stripeRow = $conn->query("SELECT config_data, is_enabled FROM payment_config WHERE payment_method = 'stripe' LIMIT 1");
if ($stripeRow && $row = $stripeRow->fetch_assoc() && (bool)$row['is_enabled'] && !empty($row['config_data'])) {
    $stripeConfig = json_decode($row['config_data'], true);
}
if (!$stripeConfig || empty(trim($stripeConfig['secret_key'] ?? ''))) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Stripe is not configured or not enabled. Configure in Admin → Payment Settings.']);
    exit;
}

$amountCents = (int) round((float) $purchase['price'] * 100);
if ($amountCents < 50) {
    $amountCents = 50; // Stripe minimum
}

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
$scriptPath = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
$basePath = rtrim($baseUrl, '/') . ($scriptPath === '/' ? '' : $scriptPath);
$successUrl = $basePath . '/payment-success.php?session_id={CHECKOUT_SESSION_ID}&purchase_id=' . $purchaseId;
$cancelUrl = $basePath . '/purchase.php?course_id=' . $courseId;

// Stripe API v1 expects form-urlencoded, not JSON
$postFields = [
    'mode' => 'payment',
    'payment_method_types[]' => 'card',
    'line_items[0][price_data][currency]' => 'usd',
    'line_items[0][price_data][product_data][name]' => $purchase['course_title'],
    'line_items[0][price_data][product_data][description]' => 'Course purchase - Orchidee LLC',
    'line_items[0][price_data][unit_amount]' => $amountCents,
    'line_items[0][quantity]' => 1,
    'success_url' => $successUrl,
    'cancel_url' => $cancelUrl,
    'client_reference_id' => (string) $purchaseId,
];

$secretKey = trim($stripeConfig['secret_key']);
$ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($postFields),
    CURLOPT_USERPWD => $secretKey . ':',
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded',
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    echo json_encode(['success' => false, 'error' => 'Stripe request failed']);
    exit;
}

$data = json_decode($response, true);

if ($httpCode >= 200 && $httpCode < 300 && isset($data['id'])) {
    echo json_encode(['success' => true, 'session_id' => $data['id']]);
    exit;
}

$errorMessage = $data['error']['message'] ?? $data['message'] ?? 'Stripe error';
echo json_encode(['success' => false, 'error' => $errorMessage]);
$conn->close();
