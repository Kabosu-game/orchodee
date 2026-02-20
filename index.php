<?php
// ============================================================
// CORRECTION 1 : Centralisation de la création de la table
// Évite la duplication et garantit que la table existe
// avant toute lecture, pas seulement après un POST.
// ============================================================

/**
 * Initialise la connexion DB et crée la table testimonials si absente.
 * Retourne une instance mysqli ou null en cas d'échec.
 */
function getTestimonialsConnection(): ?mysqli {
    $db_path = __DIR__ . '/config/database.php';
    if (!file_exists($db_path)) return null;

    // require_once évite le double-chargement entre les deux blocs
    require_once $db_path;

    if (!function_exists('getDBConnection')) return null;

    try {
        $conn = getDBConnection();
        if (!$conn || !is_object($conn)) return null;

        // Création centralisée : appelée une seule fois, ici
        $conn->query("CREATE TABLE IF NOT EXISTS testimonials (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(255) NOT NULL,
            email       VARCHAR(255) NOT NULL,
            comment     TEXT         NOT NULL,
            rating      INT          DEFAULT 5,
            status      ENUM('pending','approved','rejected') DEFAULT 'pending',
            created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY status     (status),
            KEY created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        return $conn;
    } catch (Throwable $e) {
        // CORRECTION 5 : un seul catch Throwable suffit (capture Exception + Error)
        error_log("DB init error: " . $e->getMessage());
        return null;
    }
}

// ============================================================
// CORRECTION 3 : Génération du token CSRF
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// ============================================================
// Traitement du formulaire POST
// ============================================================
$comment_success = false;
$comment_error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_comment'])) {

    // CORRECTION 3 : Validation du token CSRF
    if (empty($_POST['csrf_token']) || !hash_equals($csrf_token, $_POST['csrf_token'])) {
        $comment_error = 'Invalid security token. Please refresh the page and try again.';
    } else {
        $conn = getTestimonialsConnection();

        if ($conn) {
            $name    = trim($_POST['name']    ?? '');
            $email   = trim($_POST['email']   ?? '');
            $comment = trim($_POST['comment'] ?? '');
            $rating  = isset($_POST['rating']) ? intval($_POST['rating']) : 5;
            $rating  = max(1, min(5, $rating)); // force entre 1 et 5

            if ($name === '' || $email === '' || $comment === '') {
                $comment_error = 'Please fill in all required fields.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $comment_error = 'Please enter a valid email address.';
            } elseif (mb_strlen($comment) < 10) {
                $comment_error = 'Your comment must contain at least 10 characters.';
            } else {
                try {
                    $stmt = $conn->prepare(
                        "INSERT INTO testimonials (name, email, comment, rating, status)
                         VALUES (?, ?, ?, ?, 'pending')"
                    );
                    if ($stmt) {
                        $stmt->bind_param("sssi", $name, $email, $comment, $rating);
                        $comment_success = $stmt->execute();
                        if (!$comment_success) {
                            // CORRECTION 4 : message générique côté client, détail en log
                            error_log("Testimonial insert error: " . $stmt->error);
                            $comment_error = 'An error occurred. Please try again.';
                        }
                        $stmt->close();
                    } else {
                        error_log("Testimonial prepare error: " . $conn->error);
                        $comment_error = 'An error occurred. Please try again.';
                    }
                } catch (Throwable $e) {
                    error_log("Testimonial form error: " . $e->getMessage());
                    $comment_error = 'An error occurred. Please try again later.';
                }
            }

            $conn->close();
        }
    }
}

// ============================================================
// CORRECTION 6 : Chargement des témoignages approuvés
// La table est maintenant créée par getTestimonialsConnection(),
// donc ce bloc fonctionne même avant le premier POST.
// ============================================================
$testimonials = [];
$conn = getTestimonialsConnection();
if ($conn) {
    try {
        $result = $conn->query(
            "SELECT * FROM testimonials
             WHERE status = 'approved'
             ORDER BY created_at DESC
             LIMIT 6"
        );
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $testimonials[] = $row;
            }
        }
    } catch (Throwable $e) {
        error_log("Testimonials load error: " . $e->getMessage());
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="utf-8">
        <title>Orchidee LLC - Together We Go Further | NCLEX Coaching & US Nurse Licensure</title>
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
        <link href="css/hero.css" rel="stylesheet">
        <link href="css/annonces.css" rel="stylesheet">
        <link href="css/gallery.css" rel="stylesheet">
        
        <style>
            /* ============================================================
               CORRECTION 2 : Système de notation étoiles — logique fiable
               On utilise JavaScript pour l'interactivité plutôt que de
               dépendre de sélecteurs CSS fragiles avec row-reverse.
               ============================================================ */
            .rating-input {
                display: flex;
                flex-direction: row;   /* sens naturel gauche→droite */
                gap: 5px;
            }

            .rating-input input[type="radio"] {
                display: none;
            }

            .rating-input label.star-label {
                cursor: pointer;
                color: #ddd;
                font-size: 1.5rem;
                transition: color 0.2s;
            }

            /* État actif géré par JS (classe .active) */
            .rating-input label.star-label.active {
                color: #ffc107;
            }

            /* Survol géré par JS */
            .rating-input label.star-label.hovered {
                color: #ffc107;
            }

            .testimonial-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1) !important;
            }

            #commentForm .form-control:focus,
            #commentForm textarea:focus {
                border-color: #007bff;
                box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
            }

            /* Image Carousel Styles */
            .image-carousel { width: 100%; }

            .image-carousel .item {
                height: 400px;
                overflow: hidden;
            }

            .image-carousel .item img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
            }

            .image-carousel .owl-dots {
                text-align: center;
                margin-top: 15px;
            }

            .image-carousel .owl-nav {
                position: absolute;
                top: 50%;
                width: 100%;
                transform: translateY(-50%);
            }

            .image-carousel .owl-nav button {
                position: absolute;
                background: rgba(255,255,255,0.8) !important;
                color: #007bff !important;
                border: none;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                font-size: 20px;
                transition: all 0.3s;
            }

            .image-carousel .owl-nav button:hover {
                background: #007bff !important;
                color: white !important;
            }

            .image-carousel .owl-nav .owl-prev { left: 15px; }
            .image-carousel .owl-nav .owl-next { right: 15px; }
        </style>
    </head>

    <body>

        <!-- Spinner Start -->
        <div id="spinner" class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
        <!-- Spinner End -->

        <?php include 'includes/promo-banner.php'; ?>
        <?php include 'includes/menu-dynamic.php'; ?>

        <!-- Hero Section -->
        <div class="hero-section">
            <div class="header-carousel owl-carousel">
                <div class="header-carousel-item" style="background: linear-gradient(rgba(0,0,0,0.5),rgba(0,0,0,0.5)), url('img/11.jpeg') center/cover no-repeat;">
                    <div class="carousel-caption">
                        <div class="container">
                            <div class="row g-4 align-items-center">
                                <div class="col-lg-7 animated fadeInLeft">
                                    <div class="text-sm-center text-md-start">
                                        <h1 class="display-4 text-white mb-4">YOUR PATH TO NCLEX SUCCESS STARTS HERE</h1>
                                        <p class="mb-5 fs-5 text-white">Securing legal assistance for licensing is now more accessible than ever. Our team's expertise ensures an efficient and constructive process. We recommend initiating your licensure registration process without delay.</p>
                                        <div class="d-flex justify-content-center justify-content-md-start flex-shrink-0 mb-4">
                                            <a class="btn btn-light rounded-pill py-3 px-4 px-md-5 me-2" href="consultation.html">Book Your Session</a>
                                            <a class="btn btn-outline-light rounded-pill py-3 px-4 px-md-5 ms-2" href="about.html">Learn More</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="header-carousel-item" style="background: linear-gradient(rgba(0,0,0,0.5),rgba(0,0,0,0.5)), url('img/12.jpeg') center/cover no-repeat;">
                    <div class="carousel-caption">
                        <div class="container">
                            <div class="row g-4 align-items-center">
                                <div class="col-lg-7 animated fadeInLeft">
                                    <div class="text-sm-center text-md-start">
                                        <h1 class="display-4 text-white mb-4">JOIN THE BIGGEST COMMUNITY</h1>
                                        <p class="mb-5 fs-5 text-white">Orchidee is an organizational framework established to support both the security and achievement of your objectives. By partnering with us, you will receive the resources and guidance necessary to fulfill your goals.</p>
                                        <div class="d-flex justify-content-center justify-content-md-start flex-shrink-0 mb-4">
                                            <a class="btn btn-light rounded-pill py-3 px-4 px-md-5 me-2" href="consultation.html">Get Started</a>
                                            <a class="btn btn-outline-light rounded-pill py-3 px-4 px-md-5 ms-2" href="courses.php">View Courses</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="header-carousel-item" style="background: linear-gradient(rgba(0,0,0,0.5),rgba(0,0,0,0.5)), url('img/sss.jpeg') center/cover no-repeat;">
                    <div class="carousel-caption">
                        <div class="container">
                            <div class="row g-4 align-items-center">
                                <div class="col-lg-7 animated fadeInLeft">
                                    <div class="text-sm-center text-md-start">
                                        <h1 class="display-4 text-white mb-4">EXPERT COACHING & SUPPORT</h1>
                                        <p class="mb-5 fs-5 text-white">Our interactive classes feature PowerPoint presentations, video materials, and PDF resources, complemented by a dedicated WhatsApp group for ongoing support. Become part of our community of accomplished nursing professionals.</p>
                                        <div class="d-flex justify-content-center justify-content-md-start flex-shrink-0 mb-4">
                                            <a class="btn btn-light rounded-pill py-3 px-4 px-md-5 me-2" href="consultation.html">Book Now</a>
                                            <a class="btn btn-outline-light rounded-pill py-3 px-4 px-md-5 ms-2" href="about.html">About Us</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Hero End -->

        <!-- Announcements -->
        <?php include 'includes/annonces-section.php'; ?>

        <!-- About Start -->
        <div class="container-fluid bg-light about py-5" style="background: linear-gradient(rgba(255,255,255,0.9),rgba(255,255,255,0.9)), url('img/about.jpeg') center/cover no-repeat;">
            <div class="container py-5">
                <div class="row g-5 align-items-center">
                    <div class="col-lg-6 wow fadeInLeft" data-wow-delay="0.2s">
                        <div class="about-item-content bg-white rounded p-5 h-100">
                            <h4 class="text-primary mb-3">About Us</h4>
                            <h1 class="display-4 mb-4">Our Commitment to Empowering Licensed Nurses</h1>
                            <p class="mb-4 fs-5">Guided by a principle that emphasize persistence and the rewards that come from continuous effort—Orchidee LLC stands firmly for solidarity, mutual support. As a distinguished online platform, we are dedicated to supporting licensed nurses in their pursuit of US licensure. Our website serves as the primary hub where we bring together excellence and accessibility to deliver meaningful assistance.</p>
                            <div class="d-flex gap-3 flex-wrap">
                                <a class="btn btn-primary rounded-pill py-3 px-5" href="about.html">ABOUT OUR COMPANY</a>
                                <a class="btn btn-outline-primary rounded-pill py-3 px-5" href="consultation.html">BOOK NOW</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 wow fadeInRight" data-wow-delay="0.4s">
                        <div class="position-relative rounded overflow-hidden shadow-lg" style="min-height: 400px;">
                            <div class="image-carousel owl-carousel">
                                <div class="item" style="height: 400px; overflow: hidden;">
                                    <img src="img/11.jpeg" alt="Orchidee LLC" style="width:100%;height:100%;object-fit:cover;">
                                </div>
                                <div class="item" style="height: 400px; overflow: hidden;">
                                    <img src="img/12.jpeg" alt="Orchidee LLC" style="width:100%;height:100%;object-fit:cover;">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- About End -->

        <!-- Schedule Session Start -->
        <div class="container-fluid service py-5 bg-primary">
            <div class="container py-5">
                <div class="row g-5 align-items-center">
                    <div class="col-lg-12 text-center wow fadeInUp" data-wow-delay="0.2s">
                        <h1 class="display-4 text-white mb-4">Comprehensive Support Throughout Your Journey</h1>
                        <p class="mb-5 fs-5 text-white">Whether you wish to enroll in our 3- or 6-month group coaching program or need tailored, one-on-one tutoring, our team is committed to guiding you every step of the way. We believe that by collaborating and supporting each other, we can achieve greater success—Together We Go Further.</p>
                        <a class="btn btn-light rounded-pill py-3 px-5" href="consultation.html">BOOK NOW</a>
                    </div>
                </div>
            </div>
        </div>
        <!-- Schedule Session End -->

        <!-- Professional Services Start -->
        <div class="container-fluid py-5" style="background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);">
            <div class="container py-5">
                <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.2s" style="max-width: 900px;">
                    <h4 class="text-primary text-uppercase fw-bold mb-3">Our Services</h4>
                    <h1 class="display-4 mb-4 fw-bold">Professional Services</h1>
                    <p class="lead text-muted">We provide thorough support throughout each phase of your US nursing licensure process. From initial registration to exam preparation, our guidance is dedicated to facilitating your success.</p>
                </div>
                <div class="row g-4 justify-content-center">
                    <?php
                    $services       = [];
                    $services_limit = 6;

                    // CORRECTION 1 : réutilisation de getTestimonialsConnection() remplacée
                    // par une connexion dédiée services (même pattern centralisé)
                    $db_path = __DIR__ . '/config/database.php';
                    if (file_exists($db_path)) {
                        // require_once : pas de double-chargement
                        require_once $db_path;

                        if (function_exists('getDBConnection')) {
                            try {
                                $conn = getDBConnection();
                                if ($conn && is_object($conn)) {
                                    $conn->query("CREATE TABLE IF NOT EXISTS services (
                                        id            INT AUTO_INCREMENT PRIMARY KEY,
                                        title         VARCHAR(255)  NOT NULL,
                                        description   TEXT          DEFAULT NULL,
                                        image         VARCHAR(255)  DEFAULT NULL,
                                        icon          VARCHAR(100)  DEFAULT NULL,
                                        price         DECIMAL(10,2) DEFAULT NULL,
                                        display_order INT           DEFAULT 0,
                                        status        ENUM('active','inactive') DEFAULT 'active',
                                        created_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
                                        updated_at    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                                        KEY status        (status),
                                        KEY display_order (display_order)
                                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                                    $limit  = intval($services_limit);
                                    $result = $conn->query(
                                        "SELECT * FROM services
                                         WHERE status = 'active'
                                         ORDER BY display_order ASC, created_at DESC
                                         LIMIT $limit"
                                    );
                                    if ($result) {
                                        while ($row = $result->fetch_assoc()) {
                                            $services[] = $row;
                                        }
                                    }
                                    $conn->close();
                                }
                            } catch (Throwable $e) {
                                // CORRECTION 5 : un seul catch Throwable
                                error_log("Services section error: " . $e->getMessage());
                            }
                        }
                    }

                    if (empty($services)): ?>
                        <div class="col-12 text-center py-5">
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle me-2"></i>
                                <strong>No services available at the moment.</strong> Please check back later.
                            </div>
                        </div>
                    <?php else:
                        $delay      = 0.2;
                        $colors     = ['#007bff','#28a745','#ffc107','#17a2b8','#dc3545','#6f42c1'];
                        $colorIndex = 0;
                        foreach ($services as $service):
                            $color       = $colors[$colorIndex % count($colors)];
                            $colorIndex++;
                            $title       = htmlspecialchars($service['title']       ?? '', ENT_QUOTES, 'UTF-8');
                            $description = $service['description'] ?? '';
                            $image       = htmlspecialchars($service['image']       ?? '', ENT_QUOTES, 'UTF-8');
                    ?>
                        <div class="col-md-6 col-lg-4 wow fadeInUp" data-wow-delay="<?php echo $delay; ?>s">
                            <div class="h-100 bg-white rounded shadow-lg overflow-hidden position-relative service-card" style="transition: all 0.3s ease; border-top: 4px solid <?php echo $color; ?>;">
                                <div class="position-relative" style="height: 200px; overflow: hidden;">
                                    <?php if (!empty($image)): ?>
                                        <img src="<?php echo $image; ?>" alt="<?php echo $title; ?>" class="w-100 h-100" style="object-fit:cover;" onerror="this.src='img/S<?php echo ($colorIndex % 6) + 1; ?>.jpeg'">
                                    <?php else: ?>
                                        <img src="img/S<?php echo ($colorIndex % 6) + 1; ?>.jpeg" alt="<?php echo $title; ?>" class="w-100 h-100" style="object-fit:cover;" onerror="this.style.display='none'">
                                    <?php endif; ?>
                                </div>
                                <div class="p-4">
                                    <?php if (!empty($title)): ?>
                                        <h5 class="fw-bold mb-3"><?php echo $title; ?></h5>
                                    <?php endif; ?>
                                    <?php if (!empty($description)):
                                        $shortLen        = 150;
                                        $shortDesc       = mb_strlen($description) > $shortLen ? mb_substr($description, 0, $shortLen) . '...' : $description;
                                        $shortDescEscaped = htmlspecialchars($shortDesc, ENT_QUOTES, 'UTF-8');
                                    ?>
                                        <div class="service-description text-muted small mb-2">
                                            <p class="mb-1"><?php echo nl2br($shortDescEscaped); ?></p>
                                            <a href="service-details.php?id=<?php echo (int)$service['id']; ?>" class="btn btn-link btn-sm p-0 text-primary">
                                                <i class="fa fa-chevron-right me-1"></i>View more
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="mb-2">
                                            <a href="service-details.php?id=<?php echo (int)$service['id']; ?>" class="btn btn-link btn-sm p-0 text-primary">
                                                <i class="fa fa-chevron-right me-1"></i>View more
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <div class="mt-3">
                                        <a href="service-form.php?service_id=<?php echo (int)$service['id']; ?>" class="btn btn-sm btn-primary rounded-pill px-4">
                                            <i class="fa fa-arrow-right me-2"></i>Apply Now
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php
                            $delay += 0.2;
                        endforeach;
                    endif; ?>
                </div>
            </div>
        </div>
        <!-- Professional Services End -->

        <!-- Why Choose Us Start -->
        <div class="container-fluid py-5" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);">
            <div class="container py-5">
                <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.2s" style="max-width: 900px;">
                    <h4 class="text-primary text-uppercase fw-bold mb-3">Why Choose Us</h4>
                    <h1 class="display-4 mb-4 fw-bold">Your Success is Our Mission</h1>
                    <p class="lead text-muted">We leverage our expertise, innovative strategies, and individualized guidance to facilitate a seamless and successful pathway to US nursing licensure.</p>
                </div>
                <div class="row g-4 justify-content-center">
                    <div class="col-md-6 col-lg-6 col-xl-3 wow fadeInUp" data-wow-delay="0.2s">
                        <div class="bg-white rounded shadow-lg h-100 p-4 text-center" style="transition: transform 0.3s ease; border-top: 4px solid #007bff;">
                            <div class="mb-4">
                                <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary bg-gradient" style="width:80px;height:80px;box-shadow:0 4px 15px rgba(0,123,255,0.3);">
                                    <i class="fa fa-headset fa-2x text-white"></i>
                                </div>
                            </div>
                            <h4 class="mb-3 fw-bold">24/7 support</h4>
                            <p class="text-muted mb-0">A dedicated WhatsApp group, scheduled calls, and ongoing mentorship are provided until you successfully complete your examination.</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-6 col-xl-3 wow fadeInUp" data-wow-delay="0.4s">
                        <div class="bg-white rounded shadow-lg h-100 p-4 text-center" style="transition: transform 0.3s ease; border-top: 4px solid #28a745;">
                            <div class="mb-4">
                                <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-gradient" style="width:80px;height:80px;box-shadow:0 4px 15px rgba(40,167,69,0.3);">
                                    <i class="fa fa-chart-line fa-2x text-white"></i>
                                </div>
                            </div>
                            <h4 class="mb-3 fw-bold">Proven Results</h4>
                            <p class="text-muted mb-0">A structured methodology that integrates academic excellence with practical application has resulted in a 98% passing rate.</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-6 col-xl-3 wow fadeInUp" data-wow-delay="0.6s">
                        <div class="bg-white rounded shadow-lg h-100 p-4 text-center" style="transition: transform 0.3s ease; border-top: 4px solid #ffc107;">
                            <div class="mb-4">
                                <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-warning bg-gradient" style="width:80px;height:80px;box-shadow:0 4px 15px rgba(255,193,7,0.3);">
                                    <i class="fa fa-briefcase-medical fa-2x text-white"></i>
                                </div>
                            </div>
                            <h4 class="mb-3 fw-bold">Real-world ready</h4>
                            <p class="text-muted mb-0">Comprehensive content updates, interactive learning materials, and live sessions designed to prepare you effectively for both examinations and professional practice.</p>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-6 col-xl-3 wow fadeInUp" data-wow-delay="0.8s">
                        <div class="bg-white rounded shadow-lg h-100 p-4 text-center" style="transition: transform 0.3s ease; border-top: 4px solid #dc3545;">
                            <div class="mb-4">
                                <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger bg-gradient" style="width:80px;height:80px;box-shadow:0 4px 15px rgba(220,53,69,0.3);">
                                    <i class="fa fa-star fa-2x text-white"></i>
                                </div>
                            </div>
                            <h4 class="mb-3 fw-bold">Premium Excellence</h4>
                            <p class="text-muted mb-0">High-quality content, individualized tutoring services, and an environment that fosters solidarity and mutual support.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Why Choose Us End -->

        <!-- What We Offer Start -->
        <div class="container-fluid service py-5">
            <div class="container py-5">
                <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.1s" style="max-width: 800px;">
                    <h1 class="display-4 mb-4 fw-bold">What We Offer</h1>
                    <p class="lead text-muted">Comprehensive support for your NCLEX journey with flexible options tailored to your needs.</p>
                </div>
                <div class="row g-4 justify-content-center">
                    <div class="col-md-6 col-lg-4 wow fadeInUp" data-wow-delay="0.2s">
                        <div class="service-item bg-white rounded shadow-lg h-100" style="transition: all 0.3s ease;">
                            <div class="text-center p-5">
                                <div class="service-icon d-inline-block p-4 mb-4 bg-primary rounded-circle">
                                    <i class="fa fa-chalkboard-teacher fa-3x text-white"></i>
                                </div>
                                <h4 class="mb-4 fw-bold">Online Group Coaching</h4>
                                <p class="mb-0 text-muted">Enroll in our 3- or 6-month online coaching programs, conducted through secure video conferencing platforms. Receive instruction from certified NCLEX educators utilizing interactive PowerPoint presentations, videos, and thorough supporting materials.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4 wow fadeInUp" data-wow-delay="0.4s">
                        <div class="service-item bg-white rounded shadow-lg h-100" style="transition: all 0.3s ease;">
                            <div class="text-center p-5">
                                <div class="service-icon d-inline-block p-4 mb-4 bg-primary rounded-circle">
                                    <i class="fa fa-calendar-alt fa-3x text-white"></i>
                                </div>
                                <h4 class="mb-4 fw-bold">Monthly Subscription</h4>
                                <p class="mb-0 text-muted">We offer convenient monthly payment plans through Zelle, Cash App, or bank deposit. Students may progress at their own pace and benefit from ongoing support via our dedicated WhatsApp group.</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4 wow fadeInUp" data-wow-delay="0.6s">
                        <div class="service-item bg-white rounded shadow-lg h-100" style="transition: all 0.3s ease;">
                            <div class="text-center p-5">
                                <div class="service-icon d-inline-block p-4 mb-4 bg-primary rounded-circle">
                                    <i class="fa fa-trophy fa-3x text-white"></i>
                                </div>
                                <h4 class="mb-4 fw-bold">Certification and Support</h4>
                                <p class="mb-0 text-muted">Upon completion, you will receive a certificate of participation. Additionally, our WhatsApp group offers continued support through Q&A sessions, practice questions, and ongoing motivation until you successfully pass your exam.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- What We Offer End -->

        <!-- Testimonials & Comments Section Start -->
        <div class="container-fluid testimonials-section py-5" style="background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);">
            <div class="container py-5">
                <div class="text-center mx-auto mb-5 wow fadeInUp" data-wow-delay="0.2s" style="max-width: 900px;">
                    <h4 class="text-primary text-uppercase fw-bold mb-3">Testimonials</h4>
                    <h1 class="display-4 mb-4 fw-bold">What Our Clients Say</h1>
                    <p class="lead text-muted">Discover the experiences of our students who have succeeded in their NCLEX journey thanks to our support.</p>
                </div>

                <div class="row g-5">
                    <!-- Existing Testimonials -->
                    <div class="col-lg-8">
                        <div class="row g-4">
                            <?php
                            $default_testimonials = [
                                ['name' => 'Abdonie J.',        'comment' => 'Well-tailored training with quality materials. They keep mentoring you until you pass.',                                                          'rating' => 5, 'title' => 'NCLEX Success'],
                                ['name' => 'Fabyrose T.',       'comment' => 'I definitely recommend Orchidee LLC! They explain everything well and give you everything you need to pass.',                                       'rating' => 5, 'title' => 'USRN Success'],
                                ['name' => 'Marie Michelle M.', 'comment' => 'Thanks to their guidance, I realized my dream of becoming an RN in the USA. The staff is dynamic and competent.',                                  'rating' => 5, 'title' => 'RN in USA'],
                                ['name' => 'Joseph B.',         'comment' => 'A trusted organization with talented individuals ready to make a difference in your life.',                                                         'rating' => 5, 'title' => 'Satisfied Client']
                            ];

                            $display_testimonials = !empty($testimonials) ? $testimonials : $default_testimonials;
                            $colors     = ['#007bff','#28a745','#ffc107','#dc3545','#17a2b8','#6f42c1'];
                            $colorIndex = 0;

                            foreach (array_slice($display_testimonials, 0, 4) as $index => $testimonial):
                                $tname    = htmlspecialchars($testimonial['name']    ?? 'Client', ENT_QUOTES, 'UTF-8');
                                $tcomment = htmlspecialchars($testimonial['comment'] ?? '',       ENT_QUOTES, 'UTF-8');
                                $trating  = isset($testimonial['rating']) ? intval($testimonial['rating']) : 5;
                                $ttitle   = isset($testimonial['title']) 
                                              ? htmlspecialchars($testimonial['title'], ENT_QUOTES, 'UTF-8') 
                                              : (isset($testimonial['created_at']) ? date('M Y', strtotime($testimonial['created_at'])) : 'Client');
                                $color    = $colors[$colorIndex % count($colors)];
                                $colorIndex++;
                            ?>
                                <div class="col-md-6 wow fadeInUp" data-wow-delay="<?php echo ($index * 0.1) + 0.2; ?>s">
                                    <div class="bg-white rounded shadow-lg p-4 h-100 position-relative" style="border-left: 4px solid <?php echo $color; ?>; transition: transform 0.3s ease;">
                                        <div class="d-flex align-items-center mb-3">
                                            <div class="flex-shrink-0">
                                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold"
                                                     style="width:60px;height:60px;background:<?php echo $color; ?>;font-size:1.5rem;">
                                                    <?php echo strtoupper(substr($tname, 0, 1)); ?>
                                                </div>
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h6 class="mb-1 fw-bold"><?php echo $tname; ?></h6>
                                                <small class="text-muted"><?php echo $ttitle; ?></small>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fa fa-star <?php echo $i <= $trating ? 'text-warning' : 'text-muted'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <p class="text-muted mb-0" style="font-style:italic;">
                                            <i class="fa fa-quote-left text-primary me-2"></i>
                                            <?php echo $tcomment; ?>
                                            <i class="fa fa-quote-right text-primary ms-2"></i>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Comment Form -->
                    <div class="col-lg-4">
                        <div class="bg-white rounded shadow-lg p-4 h-100 wow fadeInUp" data-wow-delay="0.6s" style="position: sticky; top: 20px;">
                            <h4 class="fw-bold mb-4 text-primary">
                                <i class="fa fa-comment-dots me-2"></i>Share Your Experience
                            </h4>

                            <?php if ($comment_success): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fa fa-check-circle me-2"></i>
                                    <strong>Thank You!</strong> Your comment has been submitted successfully. It will be published after moderation.
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <?php if ($comment_error): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fa fa-exclamation-circle me-2"></i>
                                    <?php echo htmlspecialchars($comment_error, ENT_QUOTES, 'UTF-8'); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="" id="commentForm">
                                <!-- CORRECTION 3 : token CSRF caché -->
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">

                                <div class="mb-3">
                                    <label for="name" class="form-label fw-bold">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="name" name="name" required
                                           placeholder="Your name"
                                           value="<?php echo isset($_POST['name']) && !$comment_success ? htmlspecialchars($_POST['name'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                                </div>

                                <div class="mb-3">
                                    <label for="email" class="form-label fw-bold">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" required
                                           placeholder="your.email@example.com"
                                           value="<?php echo isset($_POST['email']) && !$comment_success ? htmlspecialchars($_POST['email'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Rating <span class="text-danger">*</span></label>
                                    <!-- CORRECTION 2 : inputs radio en ordre naturel, interactivité gérée par JS -->
                                    <div class="rating-input" id="ratingInput">
                                        <?php
                                        $selectedRating = (isset($_POST['rating']) && !$comment_success) ? intval($_POST['rating']) : 5;
                                        for ($i = 1; $i <= 5; $i++):
                                        ?>
                                            <input type="radio" name="rating" id="rating<?php echo $i; ?>"
                                                   value="<?php echo $i; ?>"
                                                   <?php echo $i === $selectedRating ? 'checked' : ''; ?> required>
                                            <label for="rating<?php echo $i; ?>" class="star-label <?php echo $i <= $selectedRating ? 'active' : ''; ?>"
                                                   data-value="<?php echo $i; ?>">
                                                <i class="fa fa-star"></i>
                                            </label>
                                        <?php endfor; ?>
                                    </div>
                                    <small class="text-muted d-block mt-2">Select your rating (1-5 stars)</small>
                                </div>

                                <div class="mb-4">
                                    <label for="comment" class="form-label fw-bold">Your Comment <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="comment" name="comment" rows="5" required
                                              placeholder="Share your experience with Orchidee LLC..."><?php
                                        echo isset($_POST['comment']) && !$comment_success
                                            ? htmlspecialchars($_POST['comment'], ENT_QUOTES, 'UTF-8')
                                            : '';
                                    ?></textarea>
                                    <small class="text-muted">Minimum 10 characters</small>
                                </div>

                                <button type="submit" name="submit_comment" class="btn btn-primary w-100 py-3 fw-bold rounded-pill">
                                    <i class="fa fa-paper-plane me-2"></i>Submit My Comment
                                </button>

                                <small class="text-muted d-block text-center mt-3">
                                    <i class="fa fa-info-circle me-1"></i>
                                    Your comment will be submitted for moderation before publication.
                                </small>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Testimonials & Comments Section End -->

        <!-- Gallery -->
        <?php include 'includes/gallery-section.php'; ?>

        <?php include 'includes/footer.php'; ?>

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
        <script src="js/hero.js"></script>
        <script src="js/annonces.js"></script>

        <script>
        /**
         * CORRECTION 2 : Gestion JS du système de notation étoiles
         * Remplace la logique CSS fragile basée sur row-reverse + sélecteurs ~
         */
        (function () {
            const container = document.getElementById('ratingInput');
            if (!container) return;

            const labels = Array.from(container.querySelectorAll('label.star-label'));

            function setActive(value) {
                labels.forEach(function (lbl) {
                    const v = parseInt(lbl.dataset.value, 10);
                    lbl.classList.toggle('active', v <= value);
                    lbl.classList.remove('hovered');
                });
            }

            // Hover
            labels.forEach(function (lbl) {
                lbl.addEventListener('mouseenter', function () {
                    const v = parseInt(lbl.dataset.value, 10);
                    labels.forEach(function (l) {
                        l.classList.toggle('hovered', parseInt(l.dataset.value, 10) <= v);
                    });
                });
                lbl.addEventListener('mouseleave', function () {
                    labels.forEach(function (l) { l.classList.remove('hovered'); });
                });
                // Click : met à jour l'état actif
                lbl.addEventListener('click', function () {
                    setActive(parseInt(lbl.dataset.value, 10));
                });
            });

            // Init depuis l'état checked
            const checked = container.querySelector('input[type="radio"]:checked');
            if (checked) setActive(parseInt(checked.value, 10));
        })();
        </script>

    </body>
</html>