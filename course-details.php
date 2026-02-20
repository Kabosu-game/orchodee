<?php
require_once 'config/database.php';

startSession();
$isLoggedIn = isLoggedIn();

$courseId = $_GET['id'] ?? 0;
if (!$courseId) {
    redirect('courses.php');
}

$conn = getDBConnection();

// Get course details
$stmt = $conn->prepare("
    SELECT c.*, cat.name as category_name, u.first_name, u.last_name 
    FROM courses c 
    LEFT JOIN course_categories cat ON c.category_id = cat.id 
    LEFT JOIN users u ON c.created_by = u.id 
    WHERE c.id = ? AND c.status = 'published'
");
$stmt->bind_param("i", $courseId);
$stmt->execute();
$result = $stmt->get_result();
$course = $result->fetch_assoc();
$stmt->close();

if (!$course) {
    redirect('courses.php');
}

// Get chapters
$chapters = [];
$chapterStmt = $conn->prepare("SELECT * FROM chapters WHERE course_id = ? ORDER BY order_number");
$chapterStmt->bind_param("i", $courseId);
$chapterStmt->execute();
$chapterResult = $chapterStmt->get_result();
while ($row = $chapterResult->fetch_assoc()) {
    // Get lessons for each chapter
    $lessonStmt = $conn->prepare("SELECT COUNT(*) as lesson_count FROM lessons WHERE chapter_id = ?");
    $lessonStmt->bind_param("i", $row['id']);
    $lessonStmt->execute();
    $lessonResult = $lessonStmt->get_result();
    $lessonData = $lessonResult->fetch_assoc();
    $row['lesson_count'] = $lessonData['lesson_count'];
    $lessonStmt->close();
    
    $chapters[] = $row;
}
$chapterStmt->close();

// Check if user has already purchased this course
$isPurchased = false;
if ($isLoggedIn) {
    $userId = getUserId();
    $purchaseStmt = $conn->prepare("SELECT id FROM purchases WHERE user_id = ? AND course_id = ? AND payment_status = 'completed'");
    $purchaseStmt->bind_param("ii", $userId, $courseId);
    $purchaseStmt->execute();
    $purchaseResult = $purchaseStmt->get_result();
    $isPurchased = $purchaseResult->num_rows > 0;
    $purchaseStmt->close();
}

$conn->close();

$image = !empty($course['image']) ? $course['image'] : 'img/carousel-2.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars($course['title']); ?> - Orchidee LLC</title>
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

    <!-- Course Details Start -->
    <div class="container-fluid bg-light py-5">
        <div class="container py-5">
            <div class="row">
                <div class="col-lg-8">
                    <div class="bg-white rounded p-4 shadow-sm mb-4">
                        <img src="<?php echo htmlspecialchars($image); ?>" class="img-fluid rounded mb-4" alt="<?php echo htmlspecialchars($course['title']); ?>">
                        <h1 class="mb-3"><?php echo htmlspecialchars($course['title']); ?></h1>
                        <div class="mb-3">
                            <span class="badge bg-primary me-2"><?php echo htmlspecialchars($course['category_name'] ?? 'Uncategorized'); ?></span>
                            <span class="badge bg-info"><?php echo ucfirst($course['level']); ?> Level</span>
                        </div>
                        <div class="mb-4">
                            <p class="lead"><?php echo htmlspecialchars($course['description']); ?></p>
                        </div>
                        
                        <h4 class="mb-3">Course Curriculum</h4>
                        <div class="accordion" id="chaptersAccordion">
                            <?php foreach ($chapters as $index => $chapter): ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading<?php echo $chapter['id']; ?>">
                                        <button class="accordion-button <?php echo $index > 0 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $chapter['id']; ?>">
                                            <i class="fa fa-book me-2"></i>
                                            <?php echo htmlspecialchars($chapter['title']); ?>
                                            <span class="badge bg-secondary ms-2"><?php echo $chapter['lesson_count']; ?> lessons</span>
                                        </button>
                                    </h2>
                                    <div id="collapse<?php echo $chapter['id']; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" data-bs-parent="#chaptersAccordion">
                                        <div class="accordion-body">
                                            <p><?php echo htmlspecialchars($chapter['description'] ?? 'No description available.'); ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="bg-white rounded p-4 shadow-sm sticky-top" style="top: 100px;">
                        <div class="text-center mb-4">
                            <h2 class="text-primary mb-0">$<?php echo number_format($course['price'], 2); ?></h2>
                        </div>
                        
                        <ul class="list-unstyled mb-4">
                            <li class="mb-2">
                                <i class="fa fa-clock text-primary me-2"></i>
                                <strong>Duration:</strong> <?php echo $course['duration_hours']; ?> hours
                            </li>
                            <li class="mb-2">
                                <i class="fa fa-user text-primary me-2"></i>
                                <strong>Instructor:</strong> <?php echo htmlspecialchars($course['instructor_name'] ?? 'Orchidee Team'); ?>
                            </li>
                            <li class="mb-2">
                                <i class="fa fa-book text-primary me-2"></i>
                                <strong>Chapters:</strong> <?php echo count($chapters); ?>
                            </li>
                            <li class="mb-2">
                                <i class="fa fa-signal text-primary me-2"></i>
                                <strong>Level:</strong> <?php echo ucfirst($course['level']); ?>
                            </li>
                        </ul>
                        
                        <?php if ($isPurchased): ?>
                            <a href="course-view.php?id=<?php echo $courseId; ?>" class="btn btn-success w-100 py-3 mb-2">
                                <i class="fa fa-play me-2"></i>Start Learning
                            </a>
                        <?php else: ?>
                            <?php if ($isLoggedIn): ?>
                                <a href="purchase.php?id=<?php echo $courseId; ?>" class="btn btn-primary w-100 py-3 mb-2">
                                    <i class="fa fa-shopping-cart me-2"></i>Purchase Course
                                </a>
                            <?php else: ?>
                                <a href="login.php?redirect=course-details.php?id=<?php echo $courseId; ?>" class="btn btn-primary w-100 py-3 mb-2">
                                    <i class="fa fa-sign-in-alt me-2"></i>Login to Purchase
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <a href="courses.php" class="btn btn-outline-secondary w-100">
                            <i class="fa fa-arrow-left me-2"></i>Back to Courses
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Course Details End -->

    <?php include 'includes/footer.php'; ?>

    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>

