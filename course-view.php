<?php
require_once 'includes/auth_check.php';

$courseId = $_GET['id'] ?? 0;
$chapterId = $_GET['chapter'] ?? 0;
$lessonId = $_GET['lesson'] ?? 0;

if (!$courseId) {
    redirect('courses.php');
}

$conn = getDBConnection();
$userId = getUserId();

// Check if user has purchased this course
$purchaseStmt = $conn->prepare("SELECT id FROM purchases WHERE user_id = ? AND course_id = ? AND payment_status = 'completed'");
$purchaseStmt->bind_param("ii", $userId, $courseId);
$purchaseStmt->execute();
$purchaseResult = $purchaseStmt->get_result();
if ($purchaseResult->num_rows === 0) {
    $purchaseStmt->close();
    $conn->close();
    redirect('course-details.php?id=' . $courseId);
}
$purchaseStmt->close();

// Get the course
$courseStmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
$courseStmt->bind_param("i", $courseId);
$courseStmt->execute();
$courseResult = $courseStmt->get_result();
$course = $courseResult->fetch_assoc();
$courseStmt->close();

// Get chapters with their lessons
$chapters = [];
$chapterStmt = $conn->prepare("SELECT * FROM chapters WHERE course_id = ? ORDER BY order_number");
$chapterStmt->bind_param("i", $courseId);
$chapterStmt->execute();
$chapterResult = $chapterStmt->get_result();

while ($chapter = $chapterResult->fetch_assoc()) {
    // Get lessons for this chapter
    $lessonStmt = $conn->prepare("SELECT * FROM lessons WHERE chapter_id = ? ORDER BY order_number");
    $lessonStmt->bind_param("i", $chapter['id']);
    $lessonStmt->execute();
    $lessonResult = $lessonStmt->get_result();
    $chapter['lessons'] = [];
    while ($lesson = $lessonResult->fetch_assoc()) {
        // Check if lesson is completed
        $progressStmt = $conn->prepare("SELECT completed FROM user_progress WHERE user_id = ? AND lesson_id = ?");
        $progressStmt->bind_param("ii", $userId, $lesson['id']);
        $progressStmt->execute();
        $progressResult = $progressStmt->get_result();
        $progress = $progressResult->fetch_assoc();
        $lesson['completed'] = $progress && $progress['completed'];
        $progressStmt->close();
        
        $chapter['lessons'][] = $lesson;
    }
    $lessonStmt->close();
    
    $chapters[] = $chapter;
}
$chapterStmt->close();

// Déterminer le chapitre et la leçon à afficher
if (empty($chapters)) {
    $conn->close();
    redirect('courses.php');
}

// If no chapter/lesson specified, take the first one
if (!$chapterId) {
    $chapterId = $chapters[0]['id'];
}
if (!$lessonId) {
    foreach ($chapters as $ch) {
        if ($ch['id'] == $chapterId && !empty($ch['lessons'])) {
            $lessonId = $ch['lessons'][0]['id'];
            break;
        }
    }
}

// Get current lesson
$currentLesson = null;
$currentChapter = null;
foreach ($chapters as $ch) {
    if ($ch['id'] == $chapterId) {
        $currentChapter = $ch;
        foreach ($ch['lessons'] as $lesson) {
            if ($lesson['id'] == $lessonId) {
                $currentLesson = $lesson;
                break;
            }
        }
        break;
    }
}

if (!$currentLesson) {
    $conn->close();
    redirect('course-view.php?id=' . $courseId);
}

// Save progress
$progressStmt = $conn->prepare("
    INSERT INTO user_progress (user_id, course_id, chapter_id, lesson_id, last_accessed_at) 
    VALUES (?, ?, ?, ?, NOW())
    ON DUPLICATE KEY UPDATE last_accessed_at = NOW()
");
$progressStmt->bind_param("iiii", $userId, $courseId, $chapterId, $lessonId);
$progressStmt->execute();
$progressStmt->close();

// Mark as completed if necessary (can be triggered by JS)
if (isset($_POST['mark_complete'])) {
    $completeStmt = $conn->prepare("
        INSERT INTO user_progress (user_id, course_id, chapter_id, lesson_id, completed, progress_percentage) 
        VALUES (?, ?, ?, ?, 1, 100)
        ON DUPLICATE KEY UPDATE completed = 1, progress_percentage = 100
    ");
    $completeStmt->bind_param("iiii", $userId, $courseId, $chapterId, $lessonId);
    $completeStmt->execute();
    $completeStmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars($currentLesson['title']); ?> - <?php echo htmlspecialchars($course['title']); ?></title>
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
    <style>
        .course-sidebar {
            max-height: calc(100vh - 100px);
            overflow-y: auto;
        }
        .lesson-item {
            padding: 10px;
            border-left: 3px solid transparent;
            cursor: pointer;
        }
        .lesson-item:hover {
            background: #f8f9fa;
        }
        .lesson-item.active {
            border-left-color: #007bff;
            background: #e7f3ff;
        }
        .lesson-item.completed {
            color: #28a745;
        }
        .video-container {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
        }
        .video-container iframe,
        .video-container video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
    </style>
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

    <!-- Course View Start -->
    <div class="container-fluid bg-light py-4">
        <div class="container-fluid">
            <div class="row">
                <!-- Sidebar -->
                <div class="col-lg-3 bg-white border-end p-0">
                    <div class="p-3 border-bottom">
                        <h5 class="mb-0">
                            <a href="dashboard.php" class="text-decoration-none text-primary">
                                <i class="fa fa-arrow-left me-2"></i><?php echo htmlspecialchars($course['title']); ?>
                            </a>
                        </h5>
                    </div>
                    <div class="course-sidebar p-3">
                        <?php foreach ($chapters as $ch): ?>
                            <div class="mb-3">
                                <h6 class="fw-bold mb-2">
                                    <i class="fa fa-book me-2"></i><?php echo htmlspecialchars($ch['title']); ?>
                                </h6>
                                <?php foreach ($ch['lessons'] as $lesson): 
                                    $isActive = $lesson['id'] == $lessonId;
                                    $isCompleted = $lesson['completed'] ?? false;
                                ?>
                                    <div class="lesson-item <?php echo $isActive ? 'active' : ''; ?> <?php echo $isCompleted ? 'completed' : ''; ?>" 
                                         onclick="window.location.href='course-view.php?id=<?php echo $courseId; ?>&chapter=<?php echo $ch['id']; ?>&lesson=<?php echo $lesson['id']; ?>'">
                                        <div class="d-flex align-items-center">
                                            <i class="fa <?php echo $isCompleted ? 'fa-check-circle' : 'fa-play-circle'; ?> me-2"></i>
                                            <span class="flex-grow-1"><?php echo htmlspecialchars($lesson['title']); ?></span>
                                            <small class="text-muted"><?php echo $lesson['duration_minutes']; ?>m</small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="col-lg-9">
                    <div class="p-4">
                        <!-- Navigation -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div>
                                <nav aria-label="breadcrumb">
                                    <ol class="breadcrumb">
                                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                        <li class="breadcrumb-item"><a href="course-view.php?id=<?php echo $courseId; ?>"><?php echo htmlspecialchars($course['title']); ?></a></li>
                                        <li class="breadcrumb-item active"><?php echo htmlspecialchars($currentLesson['title']); ?></li>
                                    </ol>
                                </nav>
                            </div>
                            <div>
                                <?php 
                                // Trouver la leçon précédente et suivante
                                $prevLesson = null;
                                $nextLesson = null;
                                $found = false;
                                foreach ($chapters as $ch) {
                                    foreach ($ch['lessons'] as $index => $lesson) {
                                        if ($found && !$nextLesson) {
                                            $nextLesson = ['id' => $lesson['id'], 'chapter_id' => $ch['id']];
                                            break 2;
                                        }
                                        if ($lesson['id'] == $lessonId) {
                                            $found = true;
                                            if ($index > 0) {
                                                $prevLesson = ['id' => $ch['lessons'][$index-1]['id'], 'chapter_id' => $ch['id']];
                                            } else {
                                                // Chercher dans le chapitre précédent
                                                foreach ($chapters as $prevCh) {
                                                    if ($prevCh['id'] == $ch['id']) break;
                                                    if (!empty($prevCh['lessons'])) {
                                                        $prevLesson = ['id' => end($prevCh['lessons'])['id'], 'chapter_id' => $prevCh['id']];
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                                ?>
                                
                                <?php if ($prevLesson): ?>
                                    <a href="course-view.php?id=<?php echo $courseId; ?>&chapter=<?php echo $prevLesson['chapter_id']; ?>&lesson=<?php echo $prevLesson['id']; ?>" class="btn btn-outline-primary">
                                        <i class="fa fa-arrow-left me-2"></i>Previous
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($nextLesson): ?>
                                    <a href="course-view.php?id=<?php echo $courseId; ?>&chapter=<?php echo $nextLesson['chapter_id']; ?>&lesson=<?php echo $nextLesson['id']; ?>" class="btn btn-primary">
                                        Next<i class="fa fa-arrow-right ms-2"></i>
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-success" onclick="markComplete()">
                                        <i class="fa fa-check me-2"></i>Complete Course
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Lesson Content -->
                        <div class="bg-white rounded p-4 shadow-sm mb-4">
                            <h2 class="mb-3"><?php echo htmlspecialchars($currentLesson['title']); ?></h2>
                            
                            <?php if ($currentLesson['description']): ?>
                                <p class="text-muted mb-4"><?php echo htmlspecialchars($currentLesson['description']); ?></p>
                            <?php endif; ?>

                            <!-- Video Player -->
                            <?php if ($currentLesson['video_url']): 
                                $videoUrl = $currentLesson['video_url'];
                                $videoType = $currentLesson['video_type'] ?? 'upload';
                            ?>
                                <div class="video-container mb-4">
                                    <?php if ($videoType === 'youtube'): 
                                        // Extraire l'ID de YouTube
                                        preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $videoUrl, $matches);
                                        $youtubeId = $matches[1] ?? '';
                                    ?>
                                        <iframe src="https://www.youtube.com/embed/<?php echo $youtubeId; ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                                    <?php elseif ($videoType === 'vimeo'): 
                                        preg_match('/vimeo\.com\/(\d+)/', $videoUrl, $matches);
                                        $vimeoId = $matches[1] ?? '';
                                    ?>
                                        <iframe src="https://player.vimeo.com/video/<?php echo $vimeoId; ?>" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe>
                                    <?php else: ?>
                                        <video controls class="w-100" style="max-height: 600px;">
                                            <source src="<?php echo htmlspecialchars($videoUrl); ?>" type="video/mp4">
                                            Your browser does not support the video tag.
                                        </video>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Mark as Complete Button -->
                            <?php if (!$currentLesson['completed']): ?>
                                <form method="POST" id="completeForm" class="mb-4">
                                    <input type="hidden" name="mark_complete" value="1">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fa fa-check-circle me-2"></i>Mark as Complete
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="alert alert-success">
                                    <i class="fa fa-check-circle me-2"></i>This lesson has been completed.
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Resources -->
                        <?php
                        $conn = getDBConnection();
                        $resourceStmt = $conn->prepare("SELECT * FROM resources WHERE lesson_id = ? OR chapter_id = ? OR course_id = ?");
                        $resourceStmt->bind_param("iii", $lessonId, $chapterId, $courseId);
                        $resourceStmt->execute();
                        $resourceResult = $resourceStmt->get_result();
                        $resources = [];
                        while ($row = $resourceResult->fetch_assoc()) {
                            $resources[] = $row;
                        }
                        $resourceStmt->close();
                        $conn->close();
                        ?>
                        
                        <?php if (!empty($resources)): ?>
                            <div class="bg-white rounded p-4 shadow-sm">
                                <h5 class="mb-3"><i class="fa fa-download me-2"></i>Resources</h5>
                                <div class="list-group">
                                    <?php foreach ($resources as $resource): ?>
                                        <a href="<?php echo htmlspecialchars($resource['file_url']); ?>" target="_blank" class="list-group-item list-group-item-action">
                                            <i class="fa fa-file-<?php echo $resource['file_type'] === 'pdf' ? 'pdf' : 'alt'; ?> me-2"></i>
                                            <?php echo htmlspecialchars($resource['title']); ?>
                                            <span class="badge bg-secondary float-end"><?php echo strtoupper($resource['file_type']); ?></span>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Course View End -->

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
                    <p class="text-white-50 mb-0 small">
                        <i class="fas fa-copyright me-1"></i> 2025 Orchidee LLC. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </div>
    <!-- Footer End -->

    <!-- Chat Button -->
    <?php include 'includes/chat-button.php'; ?>

    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
    <script>
        function markComplete() {
            document.getElementById('completeForm').submit();
        }
    </script>
</body>
</html>

