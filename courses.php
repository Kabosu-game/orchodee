<?php
require_once 'config/database.php';

startSession();
$isLoggedIn = isLoggedIn();

$conn = getDBConnection();

// Get categories
$categories = [];
$catResult = $conn->query("SELECT * FROM course_categories ORDER BY name");
if ($catResult) {
    while ($row = $catResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Filters
$categoryFilter = $_GET['category'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Build query (exclude coach-only courses; run create-all-tables.php if column missing)
$sql = "SELECT c.*, cat.name as category_name FROM courses c 
        LEFT JOIN course_categories cat ON c.category_id = cat.id 
        WHERE c.status = 'published' AND (COALESCE(c.visible_public, 1) = 1)";
$params = [];
$types = '';

if (!empty($categoryFilter)) {
    $sql .= " AND c.category_id = ?";
    $params[] = $categoryFilter;
    $types .= 'i';
}

if (!empty($searchQuery)) {
    $sql .= " AND (c.title LIKE ? OR c.description LIKE ?)";
    $searchTerm = "%$searchQuery%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ss';
}

$sql .= " ORDER BY c.created_at DESC";

$stmt = $conn->prepare($sql);
$courses = [];
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $courses[] = $row;
        }
    }
    $stmt->close();
}

// If logged in, check which courses are already purchased
$purchasedCourseIds = [];
if ($isLoggedIn) {
    $userId = getUserId();
    $purchasedStmt = $conn->prepare("SELECT course_id FROM purchases WHERE user_id = ? AND payment_status = 'completed'");
    if ($purchasedStmt) {
        $purchasedStmt->bind_param("i", $userId);
        $purchasedStmt->execute();
        $purchasedResult = $purchasedStmt->get_result();
        if ($purchasedResult) {
            while ($row = $purchasedResult->fetch_assoc()) {
                $purchasedCourseIds[] = $row['course_id'];
            }
        }
        $purchasedStmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Courses - Orchidee LLC</title>
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
    <!-- Spinner Start -->
    <div id="spinner" class="bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center" style="display: none !important;">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>
    <!-- Spinner End -->

    <!-- Menu -->
    <?php include 'includes/menu-dynamic.php'; ?>

    <!-- Header Start -->
    <div class="container-fluid bg-breadcrumb" style="background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('img/ban.jpeg') center/cover no-repeat;">
        <div class="container text-center py-5" style="max-width: 900px;">
            <h4 class="text-white display-4 mb-4 wow fadeInDown" data-wow-delay="0.1s">Our Courses</h4>
            <ol class="breadcrumb d-flex justify-content-center mb-0 wow fadeInDown" data-wow-delay="0.3s">
                <li class="breadcrumb-item"><a href="index.html">Home</a></li>
                <li class="breadcrumb-item active text-primary">Courses</li>
            </ol>    
        </div>
    </div>
    <!-- Header End -->

    <!-- Courses Start -->
    <div class="container-fluid bg-light py-5">
        <div class="container py-5">
            <!-- Filters -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="bg-white rounded p-4 shadow-sm">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="search" placeholder="Search courses..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="category">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo $categoryFilter == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fa fa-search me-2"></i>Filter
                                </button>
                            </div>
                            <div class="col-md-3 text-end">
                                <?php if ($isLoggedIn): ?>
                                    <a href="dashboard.php" class="btn btn-outline-primary">
                                        <i class="fa fa-user me-2"></i>My Dashboard
                                    </a>
                                <?php else: ?>
                                    <a href="login.php" class="btn btn-primary">
                                        <i class="fa fa-sign-in-alt me-2"></i>Login
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Courses Grid -->
            <div class="row g-4">
                <?php if (empty($courses)): ?>
                    <div class="col-12">
                        <div class="bg-white rounded p-5 text-center">
                            <i class="fa fa-book-open fa-4x text-muted mb-3"></i>
                            <h4 class="text-muted">No courses found</h4>
                            <p class="text-muted">Try adjusting your search or filters</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($courses as $course): 
                        $image = !empty($course['image']) ? $course['image'] : 'img/carousel-2.png';
                        $isPurchased = in_array($course['id'], $purchasedCourseIds);
                    ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 course-card shadow-sm">
                                <img src="<?php echo htmlspecialchars($image); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($course['title']); ?>" style="height: 200px; object-fit: cover;">
                                <div class="card-body d-flex flex-column">
                                    <span class="badge bg-primary mb-2"><?php echo htmlspecialchars($course['category_name'] ?? 'Uncategorized'); ?></span>
                                    <h5 class="card-title"><?php echo htmlspecialchars($course['title']); ?></h5>
                                    <p class="card-text text-muted small flex-grow-1"><?php echo htmlspecialchars(substr($course['short_description'] ?? $course['description'], 0, 100)); ?>...</p>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div>
                                            <strong class="text-primary">$<?php echo number_format($course['price'], 2); ?></strong>
                                        </div>
                                        <div>
                                            <small class="text-muted">
                                                <i class="fa fa-clock me-1"></i><?php echo $course['duration_hours']; ?>h
                                            </small>
                                        </div>
                                    </div>
                                    <?php if ($isPurchased): ?>
                                        <a href="course-view.php?id=<?php echo $course['id']; ?>" class="btn btn-success w-100">
                                            <i class="fa fa-play me-2"></i>Continue Learning
                                        </a>
                                    <?php else: ?>
                                        <a href="course-details.php?id=<?php echo $course['id']; ?>" class="btn btn-primary w-100">
                                            <i class="fa fa-shopping-cart me-2"></i>View Details
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- Courses End -->

    <?php include 'includes/footer.php'; ?>

    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="lib/wow/wow.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>

