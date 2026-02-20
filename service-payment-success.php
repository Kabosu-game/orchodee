<?php
require_once 'config/database.php';

$requestId = $_GET['request_id'] ?? 0;
if (!$requestId) {
    header("Location: index.php");
    exit;
}

$conn = getDBConnection();

// Get service request
$stmt = $conn->prepare("SELECT sr.*, s.title as service_title FROM service_requests sr JOIN services s ON sr.service_id = s.id WHERE sr.id = ?");
$stmt->bind_param("i", $requestId);
$stmt->execute();
$result = $stmt->get_result();
$request = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$request) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payment Success - Orchidee LLC</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    
    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Inter:slnt,wght@-10..0,100..900&display=swap" rel="stylesheet">
    
    <!-- Icon Font Stylesheet -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"/>
    
    <!-- Customized Bootstrap Stylesheet -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Menu -->
    <?php include 'includes/menu-dynamic.php'; ?>

    <div class="container-fluid bg-light py-5">
        <div class="container py-5">
            <div class="row">
                <div class="col-lg-6 mx-auto text-center">
                    <div class="bg-white rounded shadow-lg p-5">
                        <div class="mb-4">
                            <i class="fa fa-check-circle fa-5x text-success"></i>
                        </div>
                        <h2 class="text-success mb-3">Payment Submitted Successfully!</h2>
                        <p class="lead mb-4">Your service request for <strong><?php echo htmlspecialchars($request['service_title']); ?></strong> has been received.</p>
                        <div class="alert alert-info">
                            <i class="fa fa-info-circle me-2"></i>
                            Your payment is pending admin approval. You will receive a confirmation email once your payment is verified.
                        </div>
                        <div class="d-grid gap-2 mt-4">
                            <a href="index.php" class="btn btn-primary btn-lg">
                                <i class="fa fa-home me-2"></i>Back to Home
                            </a>
                            <a href="service.php" class="btn btn-outline-primary">
                                <i class="fa fa-list me-2"></i>View All Services
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>

