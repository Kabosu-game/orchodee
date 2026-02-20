<?php
require_once 'config/database.php';

$conn = getDBConnection();

$service = null;
$serviceId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($serviceId > 0) {
    $stmt = $conn->prepare("SELECT * FROM services WHERE id = ? AND status = 'active'");
    $stmt->bind_param("i", $serviceId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $service = $row;
    }
    $stmt->close();
}

$conn->close();

if (!$service) {
    header('Location: index.php');
    exit;
}

$title = isset($service['title']) ? htmlspecialchars($service['title'], ENT_QUOTES, 'UTF-8') : 'Service';
$description = isset($service['description']) ? $service['description'] : '';
$image = isset($service['image']) ? htmlspecialchars($service['image'], ENT_QUOTES, 'UTF-8') : '';
$icon = isset($service['icon']) ? htmlspecialchars($service['icon'], ENT_QUOTES, 'UTF-8') : '';
$price = isset($service['price']) && $service['price'] !== null && $service['price'] !== '' ? floatval($service['price']) : null;
$recurringEnabled = isset($service['recurring_enabled']) ? (int)$service['recurring_enabled'] : 0;
$billingInterval = isset($service['billing_interval']) ? htmlspecialchars($service['billing_interval'], ENT_QUOTES, 'UTF-8') : '';
?>
<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="utf-8">
        <title><?php echo $title; ?> - Orchidee LLC</title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
        <meta content="<?php echo $title; ?>" name="description">

        <!-- Google Web Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Inter:slnt,wght@-10..0,100..900&display=swap" rel="stylesheet">

        <!-- Icon Font Stylesheet -->
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"/>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

        <!-- Libraries Stylesheet -->
        <link rel="stylesheet" href="lib/animate/animate.min.css"/>
        <link href="lib/lightbox/css/lightbox.min.css" rel="stylesheet">
        <link href="lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">

        <!-- Customized Bootstrap Stylesheet -->
        <link href="css/bootstrap.min.css" rel="stylesheet">
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

        <!-- Header / Breadcrumb -->
        <div class="container-fluid bg-breadcrumb py-4" style="background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('<?php echo !empty($image) ? $image : 'img/ban.jpeg'; ?>') center/cover no-repeat;">
            <div class="container py-3">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="index.php" class="text-white">Home</a></li>
                    <li class="breadcrumb-item"><a href="service.php" class="text-white">Services</a></li>
                    <li class="breadcrumb-item active text-primary"><?php echo $title; ?></li>
                </ol>
            </div>
        </div>

        <!-- Détails du service -->
        <div class="container-fluid py-5">
            <div class="container py-5">
                <div class="row justify-content-center">
                    <div class="col-lg-10 col-xl-8">
                        <article class="bg-white rounded shadow-lg overflow-hidden">
                            <?php if (!empty($image)): ?>
                                <div class="position-relative" style="height: 320px; overflow: hidden;">
                                    <img src="<?php echo $image; ?>" alt="<?php echo $title; ?>" class="w-100 h-100" style="object-fit: cover;">
                                    <?php if (!empty($icon)): ?>
                                        <div class="position-absolute bottom-0 end-0 m-4 bg-white rounded-circle p-3 shadow">
                                            <i class="<?php echo $icon; ?> fa-2x text-primary"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php elseif (!empty($icon)): ?>
                                <div class="p-5 bg-light text-center">
                                    <i class="<?php echo $icon; ?> fa-4x text-primary"></i>
                                </div>
                            <?php endif; ?>

                            <div class="p-5">
                                <h1 class="display-6 fw-bold mb-4"><?php echo $title; ?></h1>

                                <?php if ($price !== null && $price > 0): ?>
                                    <div class="mb-4">
                                        <span class="h4 text-primary fw-bold">$<?php echo number_format($price, 2); ?></span>
                                        <?php if ($recurringEnabled && $billingInterval): ?>
                                            <span class="text-muted ms-2">/ <?php echo $billingInterval === 'month' ? 'month' : ($billingInterval === 'year' ? 'year' : $billingInterval); ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($recurringEnabled && !$price): ?>
                                    <p class="text-muted mb-4">
                                        <i class="fa fa-sync-alt me-2"></i>Paiement récurrent
                                        <?php if ($billingInterval): ?>
                                            (<?php echo $billingInterval === 'month' ? 'Mensuel' : ($billingInterval === 'year' ? 'Annuel' : $billingInterval); ?>)
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>

                                <?php if (!empty($description)): ?>
                                    <div class="service-detail-description text-muted mb-5" style="white-space: pre-wrap;"><?php echo nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8')); ?></div>
                                <?php endif; ?>

                                <a href="service-form.php?service_id=<?php echo (int)$service['id']; ?>" class="btn btn-primary rounded-pill px-5 py-3">
                                    <i class="fa fa-arrow-right me-2"></i>Apply Now
                                </a>
                                <a href="service.php" class="btn btn-outline-secondary rounded-pill px-4 py-3 ms-2">
                                    <i class="fa fa-list me-2"></i>All Services
                                </a>
                            </div>
                        </article>
                    </div>
                </div>
            </div>
        </div>

        <?php include 'includes/footer.php'; ?>

        <!-- Back to Top -->
        <a href="#" class="btn btn-primary btn-lg-square rounded-circle back-to-top"><i class="fa fa-arrow-up"></i></a>

        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="lib/wow/wow.min.js"></script>
        <script src="js/main.js"></script>
    </body>

</html>
