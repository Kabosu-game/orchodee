<?php
require_once 'config/database.php';

$conn = getDBConnection();

// Vérifier si la table team_members existe, sinon la créer
$tableCheck = $conn->query("SHOW TABLES LIKE 'team_members'");
if ($tableCheck->num_rows === 0) {
    $createTableSQL = "CREATE TABLE IF NOT EXISTS team_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        position VARCHAR(200) NOT NULL,
        description TEXT DEFAULT NULL,
        email VARCHAR(255) DEFAULT NULL,
        phone VARCHAR(50) DEFAULT NULL,
        photo VARCHAR(255) DEFAULT NULL,
        facebook_url VARCHAR(255) DEFAULT NULL,
        twitter_url VARCHAR(255) DEFAULT NULL,
        linkedin_url VARCHAR(255) DEFAULT NULL,
        instagram_url VARCHAR(255) DEFAULT NULL,
        display_order INT DEFAULT 0,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY status (status),
        KEY display_order (display_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->query($createTableSQL);
}

// Récupérer les membres actifs
$members = [];
$result = $conn->query("SELECT * FROM team_members WHERE status = 'active' ORDER BY display_order ASC, created_at DESC");
while ($row = $result->fetch_assoc()) {
    $members[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="utf-8">
        <title>Our Team - Orchidee LLC | Meet Our Expert Team Members</title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
        <meta content="NCLEX instructors, nursing education team, expert coaches" name="keywords">
        <meta content="Meet our dedicated team of certified NCLEX instructors and expert coaches committed to your success." name="description">

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
        
        <style>
            .team-card {
                background: white;
                border-radius: 15px;
                overflow: hidden;
                box-shadow: 0 5px 20px rgba(0,0,0,0.1);
                transition: all 0.3s ease;
                height: 100%;
                display: flex;
                flex-direction: column;
            }
            .team-card:hover {
                transform: translateY(-10px);
                box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            }
            .team-card-img {
                position: relative;
                overflow: hidden;
                height: 350px;
            }
            .team-card-img img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                transition: transform 0.3s ease;
            }
            .team-card:hover .team-card-img img {
                transform: scale(1.1);
            }
            .team-card-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: linear-gradient(to bottom, transparent 0%, rgba(0,0,0,0.7) 100%);
                opacity: 0;
                transition: opacity 0.3s ease;
                display: flex;
                align-items: flex-end;
                justify-content: center;
                padding: 20px;
            }
            .team-card:hover .team-card-overlay {
                opacity: 1;
            }
            .team-social {
                display: flex;
                gap: 10px;
            }
            .team-social a {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background: rgba(255,255,255,0.2);
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                text-decoration: none;
                transition: all 0.3s ease;
                backdrop-filter: blur(10px);
            }
            .team-social a:hover {
                background: #007bff;
                transform: scale(1.1);
            }
            .team-card-body {
                padding: 25px;
                flex-grow: 1;
                display: flex;
                flex-direction: column;
            }
            .team-card-name {
                font-size: 1.5rem;
                font-weight: 700;
                color: #2c3e50;
                margin-bottom: 8px;
            }
            .team-card-position {
                color: #007bff;
                font-weight: 600;
                font-size: 1rem;
                margin-bottom: 15px;
            }
            .team-card-description {
                color: #6c757d;
                font-size: 0.95rem;
                line-height: 1.6;
                margin-bottom: 20px;
                flex-grow: 1;
            }
            .team-card-link {
                display: inline-flex;
                align-items: center;
                color: #007bff;
                text-decoration: none;
                font-weight: 600;
                transition: all 0.3s ease;
            }
            .team-card-link:hover {
                color: #0056b3;
                transform: translateX(5px);
            }
            .team-card-link i {
                margin-left: 5px;
                transition: transform 0.3s ease;
            }
            .team-card-link:hover i {
                transform: translateX(3px);
            }
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


        <?php include 'includes/menu-dynamic.php'; ?>


        <!-- Header Start -->
        <div class="container-fluid bg-breadcrumb" style="background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('img/ban.jpeg') center/cover no-repeat;">
            <div class="container text-center py-5" style="max-width: 900px;">
                <h4 class="text-white display-4 mb-4 wow fadeInDown" data-wow-delay="0.1s">Our Team</h4>
                <ol class="breadcrumb d-flex justify-content-center mb-0 wow fadeInDown" data-wow-delay="0.3s">
                    <li class="breadcrumb-item"><a href="index.html" class="text-white">Home</a></li>
                    <li class="breadcrumb-item active text-primary">Team</li>
                </ol>    
            </div>
        </div>
        <!-- Header End -->


        <!-- Team Start -->
        <div class="container-fluid team py-5" style="background: #f8f9fa;">
            <div class="container py-5">
                <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.2s" style="max-width: 800px;">
                    <h4 class="text-primary mb-3">Our Team</h4>
                    <h1 class="display-4 mb-4">Meet Our Expert Team Members</h1>
                    <p class="mb-0 lead">Our dedicated team embodies the values of solidarity, mutual support, and benevolence. Our certified NCLEX instructors bring years of experience and a proven methodology that combines structured learning with personalized support. We're committed to your success from registration to exam day and beyond.</p>
                </div>
                <div class="row g-4">
                    <?php if (empty($members)): ?>
                        <div class="col-12 text-center py-5">
                            <i class="fa fa-users fa-4x text-muted mb-3"></i>
                            <h3 class="text-muted">No team members available</h3>
                            <p class="text-muted">Team members will be displayed here once added by the administrator.</p>
                        </div>
                    <?php else: ?>
                        <?php 
                        $delay = 0.1;
                        foreach ($members as $member): 
                            $fullName = htmlspecialchars($member['first_name'] . ' ' . $member['last_name']);
                            $photo = !empty($member['photo']) ? $member['photo'] : 'img/team-1.jpg';
                            $hasSocial = !empty($member['facebook_url']) || !empty($member['twitter_url']) || !empty($member['linkedin_url']) || !empty($member['instagram_url']);
                        ?>
                            <div class="col-md-6 col-lg-4 col-xl-3 wow fadeInUp" data-wow-delay="<?php echo $delay; ?>s">
                                <div class="team-card">
                                    <div class="team-card-img">
                                        <img src="<?php echo htmlspecialchars($photo); ?>" alt="<?php echo $fullName; ?>">
                                        <?php if ($hasSocial): ?>
                                            <div class="team-card-overlay">
                                                <div class="team-social">
                                                    <?php if (!empty($member['facebook_url'])): ?>
                                                        <a href="<?php echo htmlspecialchars($member['facebook_url']); ?>" target="_blank" rel="noopener" title="Facebook">
                                                            <i class="fab fa-facebook-f"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (!empty($member['twitter_url'])): ?>
                                                        <a href="<?php echo htmlspecialchars($member['twitter_url']); ?>" target="_blank" rel="noopener" title="Twitter">
                                                            <i class="fab fa-twitter"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (!empty($member['linkedin_url'])): ?>
                                                        <a href="<?php echo htmlspecialchars($member['linkedin_url']); ?>" target="_blank" rel="noopener" title="LinkedIn">
                                                            <i class="fab fa-linkedin-in"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (!empty($member['instagram_url'])): ?>
                                                        <a href="<?php echo htmlspecialchars($member['instagram_url']); ?>" target="_blank" rel="noopener" title="Instagram">
                                                            <i class="fab fa-instagram"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="team-card-body">
                                        <h3 class="team-card-name"><?php echo $fullName; ?></h3>
                                        <p class="team-card-position"><?php echo nl2br(htmlspecialchars($member['position'])); ?></p>
                                        <?php if (!empty($member['description'])): ?>
                                            <p class="team-card-description">
                                                <?php echo htmlspecialchars(substr($member['description'], 0, 120)); ?>
                                                <?php echo strlen($member['description']) > 120 ? '...' : ''; ?>
                                            </p>
                                        <?php endif; ?>
                                        <a href="team-details.php?id=<?php echo $member['id']; ?>" class="team-card-link">
                                            View Profile <i class="fa fa-arrow-right"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php 
                            $delay += 0.1;
                        endforeach; 
                        ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- Team End -->

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
    </body>

</html>
