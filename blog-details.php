<?php
require_once 'config/database.php';

$postId = intval($_GET['id'] ?? 0);

if (!$postId) {
    header("Location: blog.php");
    exit;
}

$conn = getDBConnection();

// Vérifier si la table blog_posts existe
$tableCheck = $conn->query("SHOW TABLES LIKE 'blog_posts'");
if ($tableCheck->num_rows === 0) {
    $conn->close();
    header("Location: create-blog-table.php");
    exit;
}

// Récupérer l'article
$stmt = $conn->prepare("SELECT bp.*, u.first_name, u.last_name FROM blog_posts bp LEFT JOIN users u ON bp.author_id = u.id WHERE bp.id = ? AND bp.status = 'published'");
$stmt->bind_param("i", $postId);
$stmt->execute();
$result = $stmt->get_result();
$post = $result->fetch_assoc();
$stmt->close();

if (!$post) {
    $conn->close();
    header("Location: blog.php");
    exit;
}

// Incrémenter les vues
$updateViews = $conn->prepare("UPDATE blog_posts SET views = views + 1 WHERE id = ?");
$updateViews->bind_param("i", $postId);
$updateViews->execute();
$updateViews->close();

// Récupérer les articles récents (pour la sidebar)
$recentPosts = [];
$recentStmt = $conn->prepare("SELECT id, title, created_at, featured_image FROM blog_posts WHERE status = 'published' AND id != ? ORDER BY created_at DESC LIMIT 5");
$recentStmt->bind_param("i", $postId);
$recentStmt->execute();
$recentResult = $recentStmt->get_result();
while ($row = $recentResult->fetch_assoc()) {
    $recentPosts[] = $row;
}
$recentStmt->close();

// Récupérer les articles de la même catégorie
$relatedPosts = [];
$relatedStmt = $conn->prepare("SELECT id, title, created_at, featured_image, excerpt FROM blog_posts WHERE status = 'published' AND category = ? AND id != ? ORDER BY created_at DESC LIMIT 3");
$relatedStmt->bind_param("si", $post['category'], $postId);
$relatedStmt->execute();
$relatedResult = $relatedStmt->get_result();
while ($row = $relatedResult->fetch_assoc()) {
    $relatedPosts[] = $row;
}
$relatedStmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="utf-8">
        <title><?php echo htmlspecialchars($post['title']); ?> - Orchidee LLC Blog</title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
        <meta content="<?php echo htmlspecialchars($post['category']); ?>, NCLEX, nursing" name="keywords">
        <meta content="<?php echo htmlspecialchars($post['excerpt'] ?: substr(strip_tags($post['content']), 0, 160)); ?>" name="description">

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
                <h4 class="text-white display-4 mb-4 wow fadeInDown" data-wow-delay="0.1s"><?php echo htmlspecialchars($post['title']); ?></h4>
                <ol class="breadcrumb d-flex justify-content-center mb-0 wow fadeInDown" data-wow-delay="0.3s">
                    <li class="breadcrumb-item"><a href="index.html" class="text-white">Home</a></li>
                    <li class="breadcrumb-item"><a href="blog.php" class="text-white">Blog</a></li>
                    <li class="breadcrumb-item active text-primary"><?php echo htmlspecialchars($post['category']); ?></li>
                </ol>    
            </div>
        </div>
        <!-- Header End -->


        <!-- Blog Details Start -->
        <div class="container-fluid blog bg-light py-5">
            <div class="container py-5">
                <div class="row">
                    <!-- Main Content -->
                    <div class="col-lg-8">
                        <article class="bg-white rounded p-5 shadow-sm mb-4">
                            <!-- Featured Image -->
                            <?php if (!empty($post['featured_image'])): ?>
                                <div class="mb-4">
                                    <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" class="img-fluid rounded w-100" alt="<?php echo htmlspecialchars($post['title']); ?>" style="max-height: 400px; object-fit: cover;">
                                </div>
                            <?php endif; ?>

                            <!-- Category Badge -->
                            <div class="mb-3">
                                <span class="badge bg-primary px-3 py-2"><?php echo htmlspecialchars($post['category']); ?></span>
                            </div>

                            <!-- Title -->
                            <h1 class="mb-3"><?php echo htmlspecialchars($post['title']); ?></h1>

                            <!-- Meta Information -->
                            <div class="d-flex flex-wrap gap-3 mb-4 text-muted">
                                <div>
                                    <i class="fa fa-user text-primary me-2"></i>
                                    <strong>Author:</strong> <?php echo htmlspecialchars(($post['first_name'] ?? 'Orchidee') . ' ' . ($post['last_name'] ?? 'Team')); ?>
                                </div>
                                <div>
                                    <i class="fa fa-calendar text-primary me-2"></i>
                                    <strong>Published:</strong> <?php echo date('F d, Y', strtotime($post['created_at'])); ?>
                                </div>
                                <div>
                                    <i class="fa fa-eye text-primary me-2"></i>
                                    <strong>Views:</strong> <?php echo $post['views'] ?? 0; ?>
                                </div>
                            </div>

                            <!-- Excerpt -->
                            <?php if (!empty($post['excerpt'])): ?>
                                <div class="alert alert-info mb-4">
                                    <p class="mb-0"><strong>Summary:</strong> <?php echo htmlspecialchars($post['excerpt']); ?></p>
                                </div>
                            <?php endif; ?>

                            <!-- Content -->
                            <div class="blog-content">
                                <?php echo $post['content']; ?>
                            </div>

                            <!-- Share Buttons -->
                            <div class="mt-5 pt-4 border-top">
                                <h5 class="mb-3">Share this article:</h5>
                                <div class="d-flex gap-2">
                                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="btn btn-primary btn-sm">
                                        <i class="fab fa-facebook-f me-2"></i>Facebook
                                    </a>
                                    <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>&text=<?php echo urlencode($post['title']); ?>" target="_blank" class="btn btn-info btn-sm">
                                        <i class="fab fa-twitter me-2"></i>Twitter
                                    </a>
                                    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']); ?>" target="_blank" class="btn btn-primary btn-sm" style="background-color: #0077b5;">
                                        <i class="fab fa-linkedin-in me-2"></i>LinkedIn
                                    </a>
                                    <button class="btn btn-secondary btn-sm" onclick="copyToClipboard()">
                                        <i class="fa fa-link me-2"></i>Copy Link
                                    </button>
                                </div>
                            </div>
                        </article>

                        <!-- Related Posts -->
                        <?php if (!empty($relatedPosts)): ?>
                            <div class="bg-white rounded p-5 shadow-sm mb-4">
                                <h3 class="mb-4">Related Articles</h3>
                                <div class="row g-4">
                                    <?php foreach ($relatedPosts as $related): ?>
                                        <div class="col-md-4">
                                            <div class="blog-item">
                                                <div class="blog-img">
                                                    <?php if (!empty($related['featured_image'])): ?>
                                                        <img src="<?php echo htmlspecialchars($related['featured_image']); ?>" class="img-fluid rounded-top w-100" alt="<?php echo htmlspecialchars($related['title']); ?>" style="height: 150px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <img src="img/blog-1.png" class="img-fluid rounded-top w-100" alt="<?php echo htmlspecialchars($related['title']); ?>" style="height: 150px; object-fit: cover;">
                                                    <?php endif; ?>
                                                </div>
                                                <div class="blog-content p-3">
                                                    <a href="blog-details.php?id=<?php echo $related['id']; ?>" class="h6 d-inline-block mb-2">
                                                        <?php echo htmlspecialchars($related['title']); ?>
                                                    </a>
                                                    <p class="small text-muted mb-0">
                                                        <i class="fa fa-calendar me-1"></i>
                                                        <?php echo date('M d, Y', strtotime($related['created_at'])); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Sidebar -->
                    <div class="col-lg-4">
                        <!-- Recent Posts -->
                        <?php if (!empty($recentPosts)): ?>
                            <div class="bg-white rounded p-4 shadow-sm mb-4">
                                <h4 class="mb-4">Recent Posts</h4>
                                <ul class="list-unstyled">
                                    <?php foreach ($recentPosts as $recent): ?>
                                        <li class="mb-3 pb-3 border-bottom">
                                            <a href="blog-details.php?id=<?php echo $recent['id']; ?>" class="text-decoration-none">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($recent['title']); ?></h6>
                                                <small class="text-muted">
                                                    <i class="fa fa-calendar me-1"></i>
                                                    <?php echo date('M d, Y', strtotime($recent['created_at'])); ?>
                                                </small>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <!-- Call to Action -->
                        <div class="bg-primary text-white rounded p-4 shadow-sm mb-4">
                            <h5 class="mb-3">Ready to Start Your NCLEX Journey?</h5>
                            <p class="mb-3">Get expert guidance and personalized support from our experienced NCLEX coaches.</p>
                            <a href="consultation.html" class="btn btn-light w-100">
                                <i class="fa fa-calendar-check me-2"></i>Book a Consultation
                            </a>
                        </div>

                        <!-- Back to Blog -->
                        <div class="text-center">
                            <a href="blog.php" class="btn btn-outline-primary">
                                <i class="fa fa-arrow-left me-2"></i>Back to Blog
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Blog Details End -->

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
        
        <script>
            function copyToClipboard() {
                const url = window.location.href;
                navigator.clipboard.writeText(url).then(function() {
                    alert('Link copied to clipboard!');
                });
            }
        </script>
        
        <style>
            .blog-content {
                line-height: 1.8;
                font-size: 1.1rem;
            }
            .blog-content h2 {
                color: #007bff;
                margin-top: 2rem;
                margin-bottom: 1rem;
            }
            .blog-content h3 {
                color: #495057;
                margin-top: 1.5rem;
                margin-bottom: 0.75rem;
            }
            .blog-content h4 {
                color: #6c757d;
                margin-top: 1.25rem;
                margin-bottom: 0.5rem;
            }
            .blog-content ul, .blog-content ol {
                margin-bottom: 1rem;
                padding-left: 2rem;
            }
            .blog-content li {
                margin-bottom: 0.5rem;
            }
            .blog-content p {
                margin-bottom: 1rem;
            }
            .blog-content a {
                color: #007bff;
                text-decoration: underline;
            }
        </style>
    </body>

</html>

