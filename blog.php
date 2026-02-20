<?php
require_once 'config/database.php';

$conn = getDBConnection();

// Vérifier si la table blog_posts existe
$tableCheck = $conn->query("SHOW TABLES LIKE 'blog_posts'");
if ($tableCheck->num_rows === 0) {
    $conn->close();
    die("
    <!DOCTYPE html>
    <html>
    <head>
        <title>Table Blog Non Trouvée - Orchidee LLC</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
            .error-box { background: #fff3cd; border: 2px solid #ffc107; padding: 20px; border-radius: 5px; margin: 20px 0; }
            .success-box { background: #d1ecf1; border: 2px solid #0c5460; padding: 20px; border-radius: 5px; margin: 20px 0; }
            a { color: #007bff; text-decoration: none; font-weight: bold; }
            a:hover { text-decoration: underline; }
        </style>
    </head>
    <body>
        <h1>⚠️ Table Blog Non Trouvée</h1>
        <div class='error-box'>
            <h2>La table 'blog_posts' n'existe pas encore dans votre base de données.</h2>
            <p>Vous devez créer la table avant de pouvoir utiliser le blog.</p>
        </div>
        <div class='success-box'>
            <h3>Solution Rapide :</h3>
            <ol>
                <li><strong>Créer la table automatiquement :</strong><br>
                    <a href='create-blog-table.php' style='display: inline-block; margin-top: 10px; padding: 10px 20px; background: #007bff; color: white; border-radius: 5px;'>
                        Cliquez ici pour créer la table
                    </a>
                </li>
                <li><strong>Ou créer manuellement via phpMyAdmin :</strong><br>
                    Exécutez le fichier <code>database/create_admin_tables.sql</code> dans phpMyAdmin
                </li>
            </ol>
        </div>
        <p><a href='index.html'>← Retour à l'accueil</a></p>
    </body>
    </html>
    ");
}

// Filtres
$categoryFilter = $_GET['category'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$page = intval($_GET['page'] ?? 1);
$perPage = 6;
$offset = ($page - 1) * $perPage;

// Construire la requête
$sql = "SELECT bp.*, u.first_name, u.last_name, 
        (SELECT COUNT(*) FROM blog_posts WHERE status = 'published') as total_posts
        FROM blog_posts bp 
        LEFT JOIN users u ON bp.author_id = u.id 
        WHERE bp.status = 'published'";
$params = [];
$types = '';

if (!empty($categoryFilter)) {
    $sql .= " AND bp.category = ?";
    $params[] = $categoryFilter;
    $types .= 's';
}

if (!empty($searchQuery)) {
    $sql .= " AND (bp.title LIKE ? OR bp.content LIKE ? OR bp.excerpt LIKE ?)";
    $searchTerm = "%$searchQuery%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

$sql .= " ORDER BY bp.created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$blogPosts = [];
while ($row = $result->fetch_assoc()) {
    $blogPosts[] = $row;
}
$stmt->close();

// Compter le total pour la pagination
$countSql = "SELECT COUNT(*) as total FROM blog_posts bp WHERE bp.status = 'published'";
$countParams = [];
$countTypes = '';

if (!empty($categoryFilter)) {
    $countSql .= " AND bp.category = ?";
    $countParams[] = $categoryFilter;
    $countTypes .= 's';
}

if (!empty($searchQuery)) {
    $countSql .= " AND (bp.title LIKE ? OR bp.content LIKE ? OR bp.excerpt LIKE ?)";
    $searchTerm = "%$searchQuery%";
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countTypes .= 'sss';
}

$countStmt = $conn->prepare($countSql);
if (!empty($countParams)) {
    $countStmt->bind_param($countTypes, ...$countParams);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalPosts = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalPosts / $perPage);
$countStmt->close();

// Récupérer les catégories uniques
$categories = [];
$catResult = $conn->query("SELECT DISTINCT category, COUNT(*) as count FROM blog_posts WHERE status = 'published' GROUP BY category ORDER BY category");
while ($row = $catResult->fetch_assoc()) {
    $categories[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="utf-8">
        <title>Blog - Orchidee LLC | NCLEX Tips & Updates</title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
        <meta content="NCLEX tips, nursing licensure, credential evaluation, US nursing career" name="keywords">
        <meta content="Stay updated with the latest news, tips, and insights about NCLEX preparation and US nursing licensure." name="description">

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
                <h4 class="text-white display-4 mb-4 wow fadeInDown" data-wow-delay="0.1s">Our Blog</h4>
                <ol class="breadcrumb d-flex justify-content-center mb-0 wow fadeInDown" data-wow-delay="0.3s">
                    <li class="breadcrumb-item"><a href="index.html" class="text-white">Home</a></li>
                    <li class="breadcrumb-item active text-primary">Blog</li>
                </ol>    
            </div>
        </div>
        <!-- Header End -->


        <!-- Blog Start -->
        <div class="container-fluid blog bg-light py-5">
            <div class="container py-5">
                <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.2s" style="max-width: 800px;">
                    <h4 class="text-primary">Our Latest News</h4>
                    <h1 class="display-4 mb-4">From Our Blog</h1>
                    <p class="mb-0">Stay updated with the latest news, tips, and insights about NCLEX preparation, US nursing licensure, and career opportunities. Our blog features expert advice, success stories, and important updates to help you on your journey.</p>
                </div>

                <!-- Filters -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <form method="GET" action="blog.php" class="d-flex gap-2">
                            <input type="text" name="search" class="form-control" placeholder="Search articles..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                            <button type="submit" class="btn btn-primary">
                                <i class="fa fa-search me-2"></i>Search
                            </button>
                            <?php if (!empty($searchQuery) || !empty($categoryFilter)): ?>
                                <a href="blog.php" class="btn btn-outline-secondary">Clear</a>
                            <?php endif; ?>
                        </form>
                    </div>
                    <div class="col-md-4">
                        <form method="GET" action="blog.php">
                            <select name="category" class="form-select" onchange="this.form.submit()">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $categoryFilter === $cat['category'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['category']); ?> (<?php echo $cat['count']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!empty($searchQuery)): ?>
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchQuery); ?>">
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- Blog Posts -->
                <div class="row g-4 justify-content-center">
                    <?php if (empty($blogPosts)): ?>
                        <div class="col-12">
                            <div class="text-center py-5">
                                <i class="fa fa-newspaper fa-4x text-muted mb-3"></i>
                                <h3 class="text-muted">No articles found</h3>
                                <p class="text-muted">Try adjusting your search or filter criteria.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($blogPosts as $post): ?>
                            <div class="col-lg-6 col-xl-4 wow fadeInUp" data-wow-delay="0.2s">
                                <div class="blog-item">
                                    <div class="blog-img">
                                        <?php if (!empty($post['featured_image'])): ?>
                                            <img src="<?php echo htmlspecialchars($post['featured_image']); ?>" class="img-fluid rounded-top w-100" alt="<?php echo htmlspecialchars($post['title']); ?>" style="height: 250px; object-fit: cover;">
                                        <?php else: ?>
                                            <img src="img/blog-<?php echo strtolower($post['category']) === 'nclex tips' ? '1' : (strtolower($post['category']) === 'licensure' ? '2' : '3'); ?>.png" class="img-fluid rounded-top w-100" alt="<?php echo htmlspecialchars($post['title']); ?>" style="height: 250px; object-fit: cover;">
                                        <?php endif; ?>
                                        <div class="blog-categiry py-2 px-4">
                                            <span><?php echo htmlspecialchars($post['category']); ?></span>
                                        </div>
                                    </div>
                                    <div class="blog-content p-4">
                                        <div class="blog-comment d-flex justify-content-between mb-3">
                                            <div class="small">
                                                <span class="fa fa-user text-primary"></span> 
                                                <?php echo htmlspecialchars(($post['first_name'] ?? 'Orchidee') . ' ' . ($post['last_name'] ?? 'Team')); ?>
                                            </div>
                                            <div class="small">
                                                <span class="fa fa-calendar text-primary"></span> 
                                                <?php echo date('M d, Y', strtotime($post['created_at'])); ?>
                                            </div>
                                            <div class="small">
                                                <span class="fa fa-eye text-primary"></span> 
                                                <?php echo $post['views'] ?? 0; ?> views
                                            </div>
                                        </div>
                                        <a href="blog-details.php?id=<?php echo $post['id']; ?>" class="h4 d-inline-block mb-3">
                                            <?php echo htmlspecialchars($post['title']); ?>
                                        </a>
                                        <p class="mb-3">
                                            <?php echo htmlspecialchars($post['excerpt'] ?: substr(strip_tags($post['content']), 0, 150) . '...'); ?>
                                        </p>
                                        <a href="blog-details.php?id=<?php echo $post['id']; ?>" class="btn p-0">
                                            Read More <i class="fa fa-arrow-right"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Blog pagination" class="mt-5">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($categoryFilter) ? '&category=' . urlencode($categoryFilter) : ''; ?><?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>">Previous</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <?php if ($i == 1 || $i == $totalPages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($categoryFilter) ? '&category=' . urlencode($categoryFilter) : ''; ?><?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($categoryFilter) ? '&category=' . urlencode($categoryFilter) : ''; ?><?php echo !empty($searchQuery) ? '&search=' . urlencode($searchQuery) : ''; ?>">Next</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
        <!-- Blog End -->

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

