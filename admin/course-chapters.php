<?php
require_once '../includes/admin_check.php';

$courseId = $_GET['course_id'] ?? 0;
if (!$courseId) {
    redirect('courses.php');
}

$conn = getDBConnection();

// Get the course
$courseStmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
$courseStmt->bind_param("i", $courseId);
$courseStmt->execute();
$courseResult = $courseStmt->get_result();
$course = $courseResult->fetch_assoc();
$courseStmt->close();

if (!$course) {
    $conn->close();
    redirect('courses.php');
}

// Handle actions
$action = $_GET['action'] ?? 'list';
$chapterId = $_GET['chapter_id'] ?? 0;
$lessonId = $_GET['lesson_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_chapter'])) {
        $title = sanitize($_POST['chapter_title'] ?? '');
        $description = sanitize($_POST['chapter_description'] ?? '');
        $orderNumber = intval($_POST['chapter_order'] ?? 0);
        
        $stmt = $conn->prepare("INSERT INTO chapters (course_id, title, description, order_number) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("issi", $courseId, $title, $description, $orderNumber);
        $stmt->execute();
        $stmt->close();
        redirect('course-chapters.php?course_id=' . $courseId . '&success=1');
    }
    
    if (isset($_POST['add_lesson'])) {
        $chapterId = intval($_POST['chapter_id'] ?? 0);
        $title = sanitize($_POST['lesson_title'] ?? '');
        $description = sanitize($_POST['lesson_description'] ?? '');
        $videoUrl = sanitize($_POST['video_url'] ?? '');
        $videoType = sanitize($_POST['video_type'] ?? 'upload');
        $durationMinutes = intval($_POST['duration_minutes'] ?? 0);
        $orderNumber = intval($_POST['lesson_order'] ?? 0);
        
        $stmt = $conn->prepare("INSERT INTO lessons (chapter_id, title, description, video_url, video_type, duration_minutes, order_number) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issssii", $chapterId, $title, $description, $videoUrl, $videoType, $durationMinutes, $orderNumber);
        $stmt->execute();
        $stmt->close();
        redirect('course-chapters.php?course_id=' . $courseId . '&success=1');
    }
    
    if (isset($_POST['delete_chapter'])) {
        $stmt = $conn->prepare("DELETE FROM chapters WHERE id = ?");
        $stmt->bind_param("i", $chapterId);
        $stmt->execute();
        $stmt->close();
        redirect('course-chapters.php?course_id=' . $courseId . '&success=1');
    }
    
    if (isset($_POST['delete_lesson'])) {
        $stmt = $conn->prepare("DELETE FROM lessons WHERE id = ?");
        $stmt->bind_param("i", $lessonId);
        $stmt->execute();
        $stmt->close();
        redirect('course-chapters.php?course_id=' . $courseId . '&success=1');
    }
}

// Get chapters with their lessons
$chapters = [];
$chapterStmt = $conn->prepare("SELECT * FROM chapters WHERE course_id = ? ORDER BY order_number");
$chapterStmt->bind_param("i", $courseId);
$chapterStmt->execute();
$chapterResult = $chapterStmt->get_result();

while ($chapter = $chapterResult->fetch_assoc()) {
    $lessonStmt = $conn->prepare("SELECT * FROM lessons WHERE chapter_id = ? ORDER BY order_number");
    $lessonStmt->bind_param("i", $chapter['id']);
    $lessonStmt->execute();
    $lessonResult = $lessonStmt->get_result();
    $chapter['lessons'] = [];
    while ($lesson = $lessonResult->fetch_assoc()) {
        $chapter['lessons'][] = $lesson;
    }
    $lessonStmt->close();
    
    $chapters[] = $chapter;
}
$chapterStmt->close();
$conn->close();

$success = isset($_GET['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Manage Chapters - <?php echo htmlspecialchars($course['title']); ?> - Admin</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    
    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Inter:slnt,wght@-10..0,100..900&display=swap" rel="stylesheet">
    
    <!-- Icon Font Stylesheet -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"/>
    
    <!-- Customized Bootstrap Stylesheet -->
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
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
    <?php include '../includes/menu-dynamic.php'; ?>

    <!-- Manage Chapters Start -->
    <div class="container-fluid bg-light py-5">
        <div class="container py-5">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="text-primary mb-1">
                                <i class="fa fa-list me-2"></i>Manage Chapters & Lessons
                            </h2>
                            <p class="text-muted mb-0">Course: <?php echo htmlspecialchars($course['title']); ?></p>
                        </div>
                        <a href="courses.php" class="btn btn-outline-secondary">
                            <i class="fa fa-arrow-left me-2"></i>Back to Courses
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fa fa-check-circle me-2"></i>Changes saved successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Add Chapter Form -->
            <div class="bg-white rounded p-4 shadow-sm mb-4">
                <h5 class="mb-3"><i class="fa fa-plus me-2"></i>Add New Chapter</h5>
                <form method="POST" class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" name="chapter_title" placeholder="Chapter Title" required>
                    </div>
                    <div class="col-md-5">
                        <input type="text" class="form-control" name="chapter_description" placeholder="Chapter Description">
                    </div>
                    <div class="col-md-2">
                        <input type="number" class="form-control" name="chapter_order" placeholder="Order" value="<?php echo count($chapters) + 1; ?>">
                    </div>
                    <div class="col-12">
                        <button type="submit" name="add_chapter" class="btn btn-primary">
                            <i class="fa fa-plus me-2"></i>Add Chapter
                        </button>
                    </div>
                </form>
            </div>

            <!-- Chapters List -->
            <div class="bg-white rounded p-4 shadow-sm">
                <h5 class="mb-4">Chapters & Lessons</h5>
                
                <?php if (empty($chapters)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fa fa-book-open fa-3x mb-3 d-block"></i>
                        No chapters yet. Add your first chapter above.
                    </div>
                <?php else: ?>
                    <?php foreach ($chapters as $chapter): ?>
                        <div class="card mb-3">
                            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">
                                        <i class="fa fa-book me-2"></i><?php echo htmlspecialchars($chapter['title']); ?>
                                        <span class="badge bg-secondary ms-2"><?php echo count($chapter['lessons']); ?> lessons</span>
                                    </h6>
                                    <?php if ($chapter['description']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($chapter['description']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this chapter and all its lessons?');">
                                    <input type="hidden" name="delete_chapter" value="1">
                                    <input type="hidden" name="chapter_id" value="<?php echo $chapter['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                            <div class="card-body">
                                <!-- Add Lesson Form -->
                                <div class="mb-3 p-3 bg-light rounded">
                                    <h6 class="mb-3">Add Lesson to this Chapter</h6>
                                    <form method="POST" class="row g-2">
                                        <input type="hidden" name="chapter_id" value="<?php echo $chapter['id']; ?>">
                                        <div class="col-md-3">
                                            <input type="text" class="form-control form-control-sm" name="lesson_title" placeholder="Lesson Title" required>
                                        </div>
                                        <div class="col-md-2">
                                            <input type="text" class="form-control form-control-sm" name="video_url" placeholder="Video URL">
                                        </div>
                                        <div class="col-md-2">
                                            <select class="form-select form-select-sm" name="video_type">
                                                <option value="upload">Upload</option>
                                                <option value="youtube">YouTube</option>
                                                <option value="vimeo">Vimeo</option>
                                                <option value="external">External</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <input type="number" class="form-control form-control-sm" name="duration_minutes" placeholder="Minutes" min="0">
                                        </div>
                                        <div class="col-md-2">
                                            <input type="number" class="form-control form-control-sm" name="lesson_order" placeholder="Order" value="<?php echo count($chapter['lessons']) + 1; ?>">
                                        </div>
                                        <div class="col-md-1">
                                            <button type="submit" name="add_lesson" class="btn btn-primary btn-sm w-100">
                                                <i class="fa fa-plus"></i>
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                
                                <!-- Lessons List -->
                                <?php if (empty($chapter['lessons'])): ?>
                                    <p class="text-muted small mb-0">No lessons in this chapter yet.</p>
                                <?php else: ?>
                                    <ul class="list-group">
                                        <?php foreach ($chapter['lessons'] as $lesson): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <i class="fa fa-play-circle text-primary me-2"></i>
                                                    <strong><?php echo htmlspecialchars($lesson['title']); ?></strong>
                                                    <?php if ($lesson['video_url']): ?>
                                                        <small class="text-muted ms-2">
                                                            <i class="fa fa-video me-1"></i><?php echo ucfirst($lesson['video_type']); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                    <?php if ($lesson['duration_minutes']): ?>
                                                        <small class="text-muted ms-2">
                                                            <i class="fa fa-clock me-1"></i><?php echo $lesson['duration_minutes']; ?>m
                                                        </small>
                                                    <?php endif; ?>
                                                </div>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this lesson?');">
                                                    <input type="hidden" name="delete_lesson" value="1">
                                                    <input type="hidden" name="lesson_id" value="<?php echo $lesson['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                </form>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- Manage Chapters End -->

    <!-- Footer Start -->
    <div class="container-fluid footer py-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                    <a href="../index.html" class="p-0">
                        <img src="../img/orchideelogo.png" alt="Orchidee LLC" style="height: 40px;">
                    </a>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="text-white-50 mb-0 small">
                        <i class="fas fa-copyright me-1"></i> 2025 Orchidee LLC. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </div>
    <!-- Footer End -->

    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/main.js"></script>
</body>
</html>

