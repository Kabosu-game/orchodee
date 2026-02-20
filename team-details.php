<?php
require_once 'config/database.php';

$conn = getDBConnection();
$memberId = $_GET['id'] ?? 0;

if (!$memberId) {
    header("Location: team.php");
    exit;
}

// Récupérer le membre
$member = null;
$stmt = $conn->prepare("SELECT * FROM team_members WHERE id = ? AND status = 'active'");
$stmt->bind_param("i", $memberId);
$stmt->execute();
$result = $stmt->get_result();
$member = $result->fetch_assoc();
$stmt->close();

if (!$member) {
    header("Location: team.php");
    exit;
}

// Récupérer les autres membres pour la section "Related"
$otherMembers = [];
$stmt = $conn->prepare("SELECT id, first_name, last_name, position, photo FROM team_members WHERE id != ? AND status = 'active' ORDER BY display_order ASC LIMIT 3");
$stmt->bind_param("i", $memberId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $otherMembers[] = $row;
}
$stmt->close();

$conn->close();

$fullName = htmlspecialchars($member['first_name'] . ' ' . $member['last_name']);
$photo = !empty($member['photo']) ? $member['photo'] : 'img/team-1.jpg';

// Meta description: plain text, truncated (155-160 chars for SEO)
$rawDesc = $member['description'] ?? '';
$metaDesc = trim(preg_replace('/\s+/', ' ', strip_tags($rawDesc)));
$metaDesc = mb_substr($metaDesc, 0, 160);
if (mb_strlen($rawDesc) > 160) {
    $metaDesc = preg_replace('/\s+\S*$/', '...', $metaDesc);
}

// Description formatée : support texte brut ET HTML (éditeur avancé)
function formatTeamDescription($text) {
    if (empty(trim($text))) return '';
    // If HTML content (rich editor), allow safe tags
    $allowedTags = '<p><br><strong><b><em><i><u><ul><ol><li><a><h2><h3><h4><span>';
    if (preg_match('/<[a-z][a-z0-9]*[\s>]/i', $text)) {
        return strip_tags($text, $allowedTags);
    }
    // Texte brut : paragraphes et sauts de ligne
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $paragraphs = preg_split('/\n\s*\n/', $text);
    $out = '';
    foreach ($paragraphs as $p) {
        $p = trim($p);
        if ($p === '') continue;
        $out .= '<p class="mb-3">' . nl2br($p) . '</p>';
    }
    return $out ?: '<p>' . nl2br($text) . '</p>';
}
$formattedDescription = formatTeamDescription($rawDesc);
?>
<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="utf-8">
        <title><?php echo $fullName; ?> - Team Member | Orchidee LLC</title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
        <?php $positionMeta = trim(str_replace(["\r\n","\n","\r"], ', ', $member['position'] ?? '')); ?>
        <meta content="<?php echo htmlspecialchars($positionMeta); ?>, NCLEX instructor, Orchidee LLC" name="keywords">
        <meta content="<?php echo htmlspecialchars($metaDesc); ?>" name="description">
        <meta property="og:title" content="<?php echo $fullName; ?> - <?php echo htmlspecialchars(trim(explode("\n", $member['position'] ?? '')[0] ?? '')); ?> | Orchidee LLC">
        <meta property="og:description" content="<?php echo htmlspecialchars($metaDesc); ?>">
        <?php 
        $ogImage = $photo;
        if (strpos($photo, 'http') !== 0 && strpos($photo, '//') !== 0) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $ogImage = $protocol . '://' . $host . '/' . ltrim($photo, '/');
        }
        ?>
        <meta property="og:image" content="<?php echo htmlspecialchars($ogImage); ?>">

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
            .team-detail-hero {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 80px 0;
                color: white;
            }
            .team-detail-img {
                border-radius: 20px;
                overflow: hidden;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                max-width: 500px;
                margin: 0 auto;
            }
            .team-detail-img img {
                width: 100%;
                height: auto;
                display: block;
            }
            .team-detail-info {
                background: white;
                border-radius: 20px;
                padding: 40px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.1);
                margin-top: -50px;
                position: relative;
                z-index: 10;
                overflow: hidden;
                word-wrap: break-word;
            }
            .team-detail-name {
                font-size: 2.5rem;
                font-weight: 700;
                color: #2c3e50;
                margin-bottom: 10px;
            }
            .team-detail-position {
                font-size: 1.3rem;
                color: #007bff;
                font-weight: 600;
                margin-bottom: 30px;
            }
            .team-detail-description {
                font-size: 1.1rem;
                line-height: 1.8;
                color: #555;
                margin-bottom: 30px;
                word-wrap: break-word;
                overflow-wrap: break-word;
                max-width: 100%;
            }
            .team-detail-description p:last-child {
                margin-bottom: 0 !important;
            }
            .team-detail-description ul, .team-detail-description ol {
                margin: 0.75rem 0;
                padding-left: 1.5rem;
            }
            .team-detail-description a {
                color: #007bff;
                text-decoration: none;
            }
            .team-detail-description a:hover {
                text-decoration: underline;
            }
            .team-detail-contact {
                background: #f8f9fa;
                border-radius: 15px;
                padding: 25px;
                margin-bottom: 30px;
            }
            .team-detail-contact-item {
                display: flex;
                align-items: center;
                margin-bottom: 15px;
            }
            .team-detail-contact-item:last-child {
                margin-bottom: 0;
            }
            .team-detail-contact-item i {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background: #007bff;
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 15px;
            }
            .team-detail-social {
                display: flex;
                gap: 15px;
                flex-wrap: wrap;
            }
            .team-detail-social a {
                width: 50px;
                height: 50px;
                border-radius: 50%;
                background: #007bff;
                color: white;
                display: flex;
                align-items: center;
                justify-content: center;
                text-decoration: none;
                transition: all 0.3s ease;
                font-size: 1.2rem;
            }
            .team-detail-social a:hover {
                background: #0056b3;
                transform: translateY(-5px);
                box-shadow: 0 5px 15px rgba(0,123,255,0.3);
            }
            .team-detail-social a.facebook { background: #1877f2; }
            .team-detail-social a.twitter { background: #1da1f2; }
            .team-detail-social a.linkedin { background: #0077b5; }
            .team-detail-social a.instagram { background: #e4405f; }
            .related-member-card {
                background: white;
                border-radius: 15px;
                overflow: hidden;
                box-shadow: 0 5px 20px rgba(0,0,0,0.1);
                transition: all 0.3s ease;
                text-decoration: none;
                color: inherit;
                display: block;
            }
            .related-member-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 10px 30px rgba(0,0,0,0.15);
                text-decoration: none;
                color: inherit;
            }
            .related-member-img {
                height: 250px;
                overflow: hidden;
            }
            .related-member-img img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            .related-member-body {
                padding: 20px;
            }
        </style>
        <!-- Schema.org JSON-LD for indexing -->
        <?php
        $schemaPerson = [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $fullName,
            'jobTitle' => trim(explode("\n", $member['position'] ?? '')[0] ?? ''),
            'description' => $metaDesc
        ];
        if (!empty($photo)) $schemaPerson['image'] = (strpos($photo, 'http') === 0 || strpos($photo, '//') === 0) ? $photo : ($ogImage ?? $photo);
        if (!empty($member['email'])) $schemaPerson['email'] = $member['email'];
        if (!empty($member['phone'])) $schemaPerson['telephone'] = $member['phone'];
        ?>
        <script type="application/ld+json"><?php echo json_encode($schemaPerson, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
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


        <!-- Hero Section Start -->
        <div class="team-detail-hero">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-5 text-center mb-4 mb-lg-0">
                        <div class="team-detail-img wow fadeInLeft" data-wow-delay="0.2s">
                            <img src="<?php echo htmlspecialchars($photo); ?>" alt="<?php echo $fullName; ?>">
                        </div>
                    </div>
                    <div class="col-lg-7 text-center text-lg-start wow fadeInRight" data-wow-delay="0.3s">
                        <h1 class="display-3 mb-3"><?php echo $fullName; ?></h1>
                        <p class="lead mb-4"><?php echo nl2br(htmlspecialchars($member['position'])); ?></p>
                        <div class="team-detail-social">
                            <?php if (!empty($member['facebook_url'])): ?>
                                <a href="<?php echo htmlspecialchars($member['facebook_url']); ?>" target="_blank" rel="noopener" class="facebook" title="Facebook">
                                    <i class="fab fa-facebook-f"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($member['twitter_url'])): ?>
                                <a href="<?php echo htmlspecialchars($member['twitter_url']); ?>" target="_blank" rel="noopener" class="twitter" title="Twitter">
                                    <i class="fab fa-twitter"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($member['linkedin_url'])): ?>
                                <a href="<?php echo htmlspecialchars($member['linkedin_url']); ?>" target="_blank" rel="noopener" class="linkedin" title="LinkedIn">
                                    <i class="fab fa-linkedin-in"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($member['instagram_url'])): ?>
                                <a href="<?php echo htmlspecialchars($member['instagram_url']); ?>" target="_blank" rel="noopener" class="instagram" title="Instagram">
                                    <i class="fab fa-instagram"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Hero Section End -->


        <!-- Detail Info Start -->
        <div class="container-fluid py-5" style="background: #f8f9fa;">
            <div class="container">
                <div class="row">
                    <div class="col-lg-10 mx-auto">
                        <div class="team-detail-info wow fadeInUp" data-wow-delay="0.4s">
                            <h2 class="team-detail-name"><?php echo $fullName; ?></h2>
                            <div class="team-detail-position"><?php echo nl2br(htmlspecialchars($member['position'])); ?></div>
                            
                            <?php if (!empty($formattedDescription)): ?>
                                <div class="team-detail-description">
                                    <?php echo $formattedDescription; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($member['email']) || !empty($member['phone'])): ?>
                                <div class="team-detail-contact">
                                    <h4 class="mb-4">Contact Information</h4>
                                    <?php if (!empty($member['email'])): ?>
                                        <div class="team-detail-contact-item">
                                            <i class="fa fa-envelope"></i>
                                            <div>
                                                <strong>Email:</strong><br>
                                                <a href="mailto:<?php echo htmlspecialchars($member['email']); ?>"><?php echo htmlspecialchars($member['email']); ?></a>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($member['phone'])): ?>
                                        <div class="team-detail-contact-item">
                                            <i class="fa fa-phone"></i>
                                            <div>
                                                <strong>Phone:</strong><br>
                                                <a href="tel:<?php echo htmlspecialchars($member['phone']); ?>"><?php echo htmlspecialchars($member['phone']); ?></a>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="text-center mt-4">
                                <a href="team.php" class="btn btn-primary btn-lg px-5">
                                    <i class="fa fa-arrow-left me-2"></i>Back to Team
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Detail Info End -->


        <!-- Related Members Start -->
        <?php if (!empty($otherMembers)): ?>
        <div class="container-fluid py-5">
            <div class="container">
                <div class="text-center mb-5">
                    <h3 class="display-5 mb-3">Other Team Members</h3>
                    <p class="text-muted">Meet the rest of our amazing team</p>
                </div>
                <div class="row g-4">
                    <?php foreach ($otherMembers as $other): ?>
                        <div class="col-md-4">
                            <a href="team-details.php?id=<?php echo $other['id']; ?>" class="related-member-card">
                                <div class="related-member-img">
                                    <img src="<?php echo !empty($other['photo']) ? htmlspecialchars($other['photo']) : 'img/team-1.jpg'; ?>" alt="<?php echo htmlspecialchars($other['first_name'] . ' ' . $other['last_name']); ?>">
                                </div>
                                <div class="related-member-body">
                                    <h5 class="mb-1"><?php echo htmlspecialchars($other['first_name'] . ' ' . $other['last_name']); ?></h5>
                                    <p class="text-primary mb-0 small"><?php echo nl2br(htmlspecialchars($other['position'])); ?></p>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <!-- Related Members End -->

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

