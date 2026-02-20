<?php
require_once 'config/database.php';

$conn = getDBConnection();

// Vérifier si la table services existe
$tableCheck = $conn->query("SHOW TABLES LIKE 'services'");
if ($tableCheck->num_rows === 0) {
    // Créer la table si elle n'existe pas
    $createTableSQL = "CREATE TABLE IF NOT EXISTS services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        image VARCHAR(255) DEFAULT NULL,
        icon VARCHAR(100) DEFAULT NULL,
        price DECIMAL(10, 2) DEFAULT NULL,
        display_order INT DEFAULT 0,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY status (status),
        KEY display_order (display_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->query($createTableSQL);
}

// Récupérer les services actifs
$services = [];
$result = $conn->query("SELECT * FROM services WHERE status = 'active' ORDER BY display_order ASC, created_at DESC");
while ($row = $result->fetch_assoc()) {
    $services[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="utf-8">
        <title>Our Services - Orchidee LLC</title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
        <meta content="" name="keywords">
        <meta content="" name="description">

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


        <!-- Header Start -->
        <div class="container-fluid bg-breadcrumb" style="background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('img/ban.jpeg') center/cover no-repeat;">
            <div class="container text-center py-5" style="max-width: 900px;">
                <h4 class="text-white display-4 mb-4 wow fadeInDown" data-wow-delay="0.1s">Our Services</h4>
                <ol class="breadcrumb d-flex justify-content-center mb-0 wow fadeInDown" data-wow-delay="0.3s">
                    <li class="breadcrumb-item"><a href="index.html">Home</a></li>
                    <li class="breadcrumb-item active text-primary">Services</li>
                </ol>    
            </div>
        </div>
        <!-- Header End -->


        <!-- Service Start -->
        <div class="container-fluid service py-5">
            <div class="container py-5">
                <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.2s" style="max-width: 800px;">
                    <h4 class="text-primary">Our Services</h4>
                    <h1 class="display-4 mb-4">OUR SERVICES</h1>
                    <p class="mb-0">We provide thorough support throughout each phase of your US nursing licensure process. From initial registration to exam preparation, our guidance is dedicated to facilitating your success.
                    </p>
                </div>
                <div class="row g-4 justify-content-center">
                    <?php if (empty($services)): ?>
                        <div class="col-12 text-center py-5">
                            <p class="text-muted">No services available at the moment. Please check back later.</p>
                        </div>
                    <?php else: ?>
                        <?php 
                        $delay = 0.2;
                        foreach ($services as $service): 
                        ?>
                            <div class="col-md-6 col-lg-6 col-xl-3 wow fadeInUp" data-wow-delay="<?php echo $delay; ?>s">
                                <div class="service-item">
                                    <div class="service-img">
                                        <?php if (!empty($service['image'])): ?>
                                            <img src="<?php echo htmlspecialchars($service['image']); ?>" class="img-fluid rounded-top w-100" alt="<?php echo htmlspecialchars($service['title']); ?>">
                                        <?php else: ?>
                                            <img src="img/blog-1.png" class="img-fluid rounded-top w-100" alt="<?php echo htmlspecialchars($service['title']); ?>">
                                        <?php endif; ?>
                                        <?php if (!empty($service['icon'])): ?>
                                            <div class="service-icon p-3">
                                                <i class="<?php echo htmlspecialchars($service['icon']); ?> fa-2x"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="service-content p-4">
                                        <div class="service-content-inner">
                                            <a href="service-form.php?service_id=<?php echo $service['id']; ?>" class="d-inline-block h4 mb-3"><?php echo htmlspecialchars($service['title']); ?></a>
                                            <?php if ($service['price'] !== null && $service['price'] > 0): ?>
                                                <div class="mb-3">
                                                    <span class="h5 text-primary fw-bold">$<?php echo number_format($service['price'], 2); ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <div class="mb-4">
                                                <a class="btn btn-primary rounded-pill py-2 px-4" href="service-form.php?service_id=<?php echo $service['id']; ?>">
                                                    <i class="fa fa-arrow-right me-2"></i>Apply Now
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php 
                            $delay += 0.2;
                        endforeach; 
                        ?>
                    <?php endif; ?>
                    <div class="col-12 text-center wow fadeInUp" data-wow-delay="0.2s">
                        <a class="btn btn-primary rounded-pill py-3 px-5" href="consultation.html">Book Your Consultation</a>
                    </div>
                </div>
            </div>
        </div>
        <!-- Service End -->


        <!-- Footer Start -->
        <div class="container-fluid footer py-4">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                        <a href="index.html" class="p-0">
                            <img src="img/orchideelogo.png" alt="Orchidee LLC" style="height: 40px;">
                        </a>
                    </div>
                    <div class="col-md-6 text-center text-md-end">
                        <div class="footer-btn d-flex justify-content-center justify-content-md-end">
                            <a class="btn btn-sm-square rounded-circle me-2" href="#"><i class="fab fa-facebook-f"></i></a>
                            <a class="btn btn-sm-square rounded-circle me-2" href="#"><i class="fab fa-twitter"></i></a>
                            <a class="btn btn-sm-square rounded-circle me-2" href="#"><i class="fab fa-instagram"></i></a>
                            <a class="btn btn-sm-square rounded-circle me-0" href="#"><i class="fab fa-linkedin-in"></i></a>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12 text-center">
                        <p class="text-white mb-2">
                            <a href="about.php" class="text-white me-3">About Us</a>
                            <a href="blog.php" class="text-white me-3">Blog</a>
                            <a href="contact.html" class="text-white">Contact</a>
                        </p>
                        <p class="text-white-50 mb-0 small">
                            <i class="fas fa-copyright me-1"></i> 2025 Orchidee LLC. All rights reserved.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <!-- Footer End -->


        <!-- Back to Top -->
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

