<?php
require_once 'config/database.php';

$registrationId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$registrationId) {
    header("Location: index.php");
    exit;
}

$conn = getDBConnection();
$stmt = $conn->prepare("SELECT * FROM nclex_registrations WHERE id = ?");
$stmt->bind_param("i", $registrationId);
$stmt->execute();
$result = $stmt->get_result();
$registration = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$registration) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="utf-8">
        <title>Thank You - NCLEX Registration Complete - Orchidee LLC</title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">

        <!-- Google Web Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Inter:slnt,wght@-10..0,100..900&display=swap" rel="stylesheet">

        <!-- Favicon -->
        <link rel="icon" type="image/png" href="img/orchideelogo.png">
        <link rel="apple-touch-icon" href="img/orchideelogo.png">

        <!-- Icon Font Stylesheet -->
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"/>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

        <!-- Libraries Stylesheet -->
        <link rel="stylesheet" href="lib/animate/animate.min.css"/>

        <!-- Customized Bootstrap Stylesheet -->
        <link href="css/bootstrap.min.css" rel="stylesheet">

        <!-- Template Stylesheet -->
        <link href="css/style.css" rel="stylesheet">
    </head>

    <body>

        <!-- Spinner Start -->
        <div id="spinner" class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
        <!-- Spinner End -->


        <?php include 'includes/menu-dynamic.php'; ?>


        <!-- Thank You Section Start -->
        <div class="container-fluid bg-light py-5">
            <div class="container py-5">
                <div class="row">
                    <div class="col-lg-8 mx-auto">
                        <div class="bg-white rounded shadow-sm p-5 text-center wow fadeInUp" data-wow-delay="0.1s">
                            <div class="mb-4">
                                <i class="fas fa-check-circle text-success" style="font-size: 80px;"></i>
                            </div>
                            
                            <h2 class="text-primary mb-3">Thank You!</h2>
                            <h4 class="mb-4">Your NCLEX Registration Has Been Received</h4>
                            
                            <div class="alert alert-info text-start mb-4">
                                <h5 class="alert-heading"><i class="fa fa-info-circle me-2"></i>Registration Details</h5>
                                <hr>
                                <p class="mb-2"><strong>Registration ID:</strong> #<?php echo $registration['id']; ?></p>
                                <p class="mb-2"><strong>Name:</strong> <?php echo htmlspecialchars($registration['first_name'] . ' ' . $registration['last_name']); ?></p>
                                <p class="mb-2"><strong>Email:</strong> <?php echo htmlspecialchars($registration['email']); ?></p>
                                <p class="mb-0"><strong>Status:</strong> 
                                    <span class="badge bg-<?php echo $registration['status'] == 'approved' ? 'success' : ($registration['status'] == 'rejected' ? 'danger' : 'warning'); ?>">
                                        <?php echo ucfirst($registration['status']); ?>
                                    </span>
                                </p>
                            </div>

                            <div class="alert alert-warning mb-4">
                                <i class="fa fa-exclamation-triangle me-2"></i>
                                <strong>Important - Send Documents via Email:</strong> Please send all required documents via email to the administrator. Your registration form has been received, but we need the documents to complete the process.
                            </div>

                            <div class="mb-4">
                                <p class="text-muted">
                                    We have received your NCLEX registration form. 
                                    <strong>Please send all required documents via email to the administrator.</strong>
                                </p>
                                <p class="text-muted">
                                    Our team will review your information and documents, then contact you via email with next steps.
                                </p>
                                <p class="text-muted">
                                    If you have any questions, please don't hesitate to contact us using the chat button or through our contact page.
                                </p>
                            </div>

                            <div class="d-flex justify-content-center gap-3 flex-wrap">
                                <a href="index.php" class="btn btn-primary btn-lg px-5">
                                    <i class="fa fa-home me-2"></i>Return to Home
                                </a>
                                <a href="contact.html" class="btn btn-outline-primary btn-lg px-5">
                                    <i class="fa fa-envelope me-2"></i>Contact Us
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Thank You Section End -->


        <?php include 'includes/footer.php'; ?>
        <?php include 'includes/chat-button.php'; ?>
        <a href="#" class="btn btn-primary btn-lg-square rounded-circle back-to-top"><i class="fa fa-arrow-up"></i></a>   


        <!-- JavaScript Libraries -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="lib/wow/wow.min.js"></script>
        <script src="lib/easing/easing.min.js"></script>
        <script src="lib/waypoints/waypoints.min.js"></script>
        <script src="lib/counterup/counterup.min.js"></script>
        <script src="lib/lightbox/js/lightbox.min.js"></script>
        <script src="lib/owlcarousel/owl.carousel.min.js"></script>
        

        <!-- Template Javascript -->
        <script src="js/main.js"></script>
    </body>

</html>
