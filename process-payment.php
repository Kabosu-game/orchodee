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

$courseId = intval($_POST['course_id'] ?? 0);
$paymentMethod = sanitize($_POST['payment_method'] ?? '');

if (!$courseId || !$paymentMethod) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

// Get the course
$stmt = $conn->prepare("SELECT * FROM courses WHERE id = ? AND status = 'published'");
$stmt->bind_param("i", $courseId);
$stmt->execute();
$result = $stmt->get_result();
$course = $result->fetch_assoc();
$stmt->close();

if (!$course) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Course not found']);
    exit;
}

// Check if already purchased
$purchaseStmt = $conn->prepare("SELECT id FROM purchases WHERE user_id = ? AND course_id = ? AND payment_status = 'completed'");
$purchaseStmt->bind_param("ii", $userId, $courseId);
$purchaseStmt->execute();
$purchaseResult = $purchaseStmt->get_result();
if ($purchaseResult->num_rows > 0) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Course already purchased']);
    exit;
}
$purchaseStmt->close();

if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json');

// Process according to payment method
if ($paymentMethod === 'stripe') {
    $stripeConfig = getPaymentConfig($conn, 'stripe');
    if (!$stripeConfig || empty($stripeConfig['secret_key'])) {
        echo json_encode(['success' => false, 'error' => 'Stripe is not properly configured']);
        exit;
    }
    
    // Pour Stripe, on crée d'abord un achat en attente
    $stmt = $conn->prepare("INSERT INTO purchases (user_id, course_id, amount, payment_method, payment_status) VALUES (?, ?, ?, ?, 'pending')");
    $stmt->bind_param("iids", $userId, $courseId, $course['price'], $paymentMethod);
    $stmt->execute();
    $purchaseId = $conn->insert_id;
    $stmt->close();
    
    // Retourner les informations pour Stripe Checkout
    echo json_encode([
        'success' => true,
        'payment_method' => 'stripe',
        'purchase_id' => $purchaseId,
        'amount' => $course['price'],
        'currency' => 'usd',
        'course_title' => $course['title'],
        'stripe_publishable_key' => $stripeConfig['publishable_key']
    ]);
    
} elseif ($paymentMethod === 'paypal') {
    $paypalConfig = getPaymentConfig($conn, 'paypal');
    if (!$paypalConfig || empty($paypalConfig['client_id'])) {
        echo json_encode(['success' => false, 'error' => 'PayPal is not properly configured']);
        exit;
    }
    
    // Pour PayPal, on crée d'abord un achat en attente
    $stmt = $conn->prepare("INSERT INTO purchases (user_id, course_id, amount, payment_method, payment_status) VALUES (?, ?, ?, ?, 'pending')");
    $stmt->bind_param("iids", $userId, $courseId, $course['price'], $paymentMethod);
    $stmt->execute();
    $purchaseId = $conn->insert_id;
    $stmt->close();
    
    // Retourner les informations pour PayPal
    echo json_encode([
        'success' => true,
        'payment_method' => 'paypal',
        'purchase_id' => $purchaseId,
        'amount' => $course['price'],
        'currency' => 'USD',
        'course_title' => $course['title'],
        'paypal_client_id' => $paypalConfig['client_id'],
        'paypal_mode' => $paypalConfig['mode']
    ]);
    
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid payment method']);
}

$conn->close();
?>

