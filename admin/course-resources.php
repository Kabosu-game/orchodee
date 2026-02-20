<?php
require_once '../includes/admin_check.php';

$courseId = $_GET['course_id'] ?? 0;
$chapterId = $_GET['chapter_id'] ?? 0;
$lessonId = $_GET['lesson_id'] ?? 0;
$action = $_GET['action'] ?? 'list';
$resourceId = $_GET['resource_id'] ?? 0;
$examId = $_GET['exam_id'] ?? 0;

if (!$courseId) {
    redirect('courses.php');
}

$conn = getDBConnection();

// Vérifier si les tables nécessaires existent
$tableCheck = $conn->query("SHOW TABLES LIKE 'lesson_resources'");
if ($tableCheck->num_rows === 0) {
    $conn->close();
    die("
    <!DOCTYPE html>
    <html>
    <head>
        <title>Tables Manquantes - Orchidee LLC</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
            .error-box { background: #fff3cd; border: 2px solid #ffc107; padding: 20px; border-radius: 5px; margin: 20px 0; }
            .success-box { background: #d1ecf1; border: 2px solid #0c5460; padding: 20px; border-radius: 5px; margin: 20px 0; }
            a { color: #007bff; text-decoration: none; font-weight: bold; }
            .btn { display: inline-block; padding: 12px 30px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-top: 10px; }
        </style>
    </head>
    <body>
        <h1>⚠️ Tables Manquantes</h1>
        <div class='error-box'>
            <h2>Les tables nécessaires pour les ressources et examens n'existent pas encore.</h2>
            <p>Vous devez créer ces tables avant de pouvoir utiliser cette fonctionnalité.</p>
        </div>
        <div class='success-box'>
            <h3>Solution Rapide :</h3>
            <p><strong>Créer toutes les tables automatiquement :</strong></p>
            <a href='../create-all-admin-tables.php' class='btn'>
                Cliquez ici pour créer toutes les tables
            </a>
        </div>
        <p><a href='courses.php'>← Retour aux cours</a></p>
    </body>
    </html>
    ");
}

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

// Handle resource uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_resource'])) {
        $title = sanitize($_POST['title'] ?? '');
        $fileType = sanitize($_POST['file_type'] ?? 'pdf');
        $displayOrder = intval($_POST['display_order'] ?? 0);
        
        // Handle file upload
        $fileUrl = '';
        if (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] === 0) {
            $uploadDir = '../uploads/courses/resources/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileName = time() . '_' . basename($_FILES['resource_file']['name']);
            $targetFile = $uploadDir . $fileName;
            $fileSize = $_FILES['resource_file']['size'];
            
            if (move_uploaded_file($_FILES['resource_file']['tmp_name'], $targetFile)) {
                $fileUrl = 'uploads/courses/resources/' . $fileName;
                
                $stmt = $conn->prepare("INSERT INTO lesson_resources (lesson_id, chapter_id, course_id, title, file_url, file_type, file_size, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iiisssii", $lessonId, $chapterId, $courseId, $title, $fileUrl, $fileType, $fileSize, $displayOrder);
                $stmt->execute();
                $stmt->close();
            }
        }
        redirect('course-resources.php?course_id=' . $courseId . '&success=resource_added');
    }
    
    if (isset($_POST['delete_resource'])) {
        $stmt = $conn->prepare("DELETE FROM lesson_resources WHERE id = ?");
        $stmt->bind_param("i", $resourceId);
        $stmt->execute();
        $stmt->close();
        redirect('course-resources.php?course_id=' . $courseId . '&success=resource_deleted');
    }
    
    if (isset($_POST['add_exam'])) {
        $title = sanitize($_POST['exam_title'] ?? '');
        $description = sanitize($_POST['exam_description'] ?? '');
        $passingScore = intval($_POST['passing_score'] ?? 70);
        $timeLimit = intval($_POST['time_limit_minutes'] ?? 60);
        $maxAttempts = intval($_POST['max_attempts'] ?? 3);
        $displayOrder = intval($_POST['exam_display_order'] ?? 0);
        
        $stmt = $conn->prepare("INSERT INTO exams (course_id, chapter_id, title, description, passing_score, time_limit_minutes, max_attempts, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissiiii", $courseId, $chapterId, $title, $description, $passingScore, $timeLimit, $maxAttempts, $displayOrder);
        $stmt->execute();
        $examId = $conn->insert_id;
        $stmt->close();
        redirect('course-resources.php?course_id=' . $courseId . '&action=manage_exam&exam_id=' . $examId);
    }
}

// Get chapters
$chapters = [];
$chapterStmt = $conn->prepare("SELECT * FROM chapters WHERE course_id = ? ORDER BY order_number");
$chapterStmt->bind_param("i", $courseId);
$chapterStmt->execute();
$chapterResult = $chapterStmt->get_result();
while ($ch = $chapterResult->fetch_assoc()) {
    $chapters[] = $ch;
}
$chapterStmt->close();

// Get resources
$resources = [];
$resourceStmt = $conn->prepare("SELECT * FROM lesson_resources WHERE course_id = ? ORDER BY display_order");
$resourceStmt->bind_param("i", $courseId);
$resourceStmt->execute();
$resourceResult = $resourceStmt->get_result();
while ($res = $resourceResult->fetch_assoc()) {
    $resources[] = $res;
}
$resourceStmt->close();

// Get exams
$exams = [];
$examStmt = $conn->prepare("SELECT * FROM exams WHERE course_id = ? ORDER BY display_order");
$examStmt->bind_param("i", $courseId);
$examStmt->execute();
$examResult = $examStmt->get_result();
while ($ex = $examResult->fetch_assoc()) {
    $exams[] = $ex;
}
$examStmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Course Resources & Exams - Orchidee LLC</title>
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
    
    <style>
        .sidebar {
            min-height: calc(100vh - 100px);
            background: #f8f9fa;
            border-right: 1px solid #dee2e6;
        }
        .sidebar .nav-link {
            color: #495057;
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 5px;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: #007bff;
            color: white;
        }
    </style>
</head>
<body>
    <!-- Menu -->
    <?php include '../includes/menu-dynamic.php'; ?>

    <!-- Admin Content Start -->
    <div class="container-fluid bg-light py-5">
        <div class="container py-5">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="text-primary mb-0">
                            <i class="fa fa-folder-open me-2"></i>Course Resources & Exams: <?php echo htmlspecialchars($course['title']); ?>
                        </h2>
                        <div>
                            <a href="courses.php" class="btn btn-outline-secondary me-2">
                                <i class="fa fa-arrow-left me-2"></i>Back to Courses
                            </a>
                            <a href="course-chapters.php?course_id=<?php echo $courseId; ?>" class="btn btn-outline-primary">
                                <i class="fa fa-book me-2"></i>Manage Chapters
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Sidebar -->
                <div class="col-lg-3 mb-4">
                    <div class="bg-white rounded p-4 shadow-sm sidebar">
                        <nav class="nav flex-column">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fa fa-home me-2"></i>Dashboard
                            </a>
                            <a class="nav-link" href="courses.php">
                                <i class="fa fa-book me-2"></i>All Courses
                            </a>
                            <a class="nav-link active" href="course-resources.php?course_id=<?php echo $courseId; ?>">
                                <i class="fa fa-folder-open me-2"></i>Resources & Exams
                            </a>
                        </nav>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="col-lg-9">
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php 
                                $messages = [
                                    'resource_added' => 'Resource added successfully!',
                                    'resource_deleted' => 'Resource deleted successfully!',
                                    'exam_added' => 'Exam created successfully!'
                                ];
                                echo $messages[$_GET['success']] ?? 'Action completed successfully!';
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Tabs -->
                    <ul class="nav nav-tabs mb-4" id="resourcesTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="resources-tab" data-bs-toggle="tab" data-bs-target="#resources" type="button" role="tab">
                                <i class="fa fa-file me-2"></i>Resources
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="exams-tab" data-bs-toggle="tab" data-bs-target="#exams" type="button" role="tab">
                                <i class="fa fa-clipboard-check me-2"></i>Exams
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="resourcesTabsContent">
                        <!-- Resources Tab -->
                        <div class="tab-pane fade show active" id="resources" role="tabpanel">
                            <div class="bg-white rounded p-4 shadow-sm mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h4 class="mb-0">Course Resources</h4>
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addResourceModal">
                                        <i class="fa fa-plus me-2"></i>Add Resource
                                    </button>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Type</th>
                                                <th>Size</th>
                                                <th>Chapter/Lesson</th>
                                                <th>Order</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($resources)): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted">No resources added yet</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($resources as $res): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($res['title']); ?></td>
                                                        <td>
                                                            <span class="badge bg-info"><?php echo strtoupper($res['file_type']); ?></span>
                                                        </td>
                                                        <td><?php echo $res['file_size'] ? number_format($res['file_size'] / 1024, 2) . ' KB' : '-'; ?></td>
                                                        <td>
                                                            <?php 
                                                                if ($res['lesson_id']) echo 'Lesson';
                                                                elseif ($res['chapter_id']) echo 'Chapter';
                                                                else echo 'Course';
                                                            ?>
                                                        </td>
                                                        <td><?php echo $res['display_order']; ?></td>
                                                        <td>
                                                            <a href="../<?php echo htmlspecialchars($res['file_url']); ?>" target="_blank" class="btn btn-sm btn-info">
                                                                <i class="fa fa-download"></i>
                                                            </a>
                                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this resource?')">
                                                                <input type="hidden" name="delete_resource" value="1">
                                                                <input type="hidden" name="resource_id" value="<?php echo $res['id']; ?>">
                                                                <button type="submit" class="btn btn-sm btn-danger">
                                                                    <i class="fa fa-trash"></i>
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Exams Tab -->
                        <div class="tab-pane fade" id="exams" role="tabpanel">
                            <div class="bg-white rounded p-4 shadow-sm mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h4 class="mb-0">Course Exams</h4>
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addExamModal">
                                        <i class="fa fa-plus me-2"></i>Create Exam
                                    </button>
                                </div>
                                
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Title</th>
                                                <th>Chapter</th>
                                                <th>Passing Score</th>
                                                <th>Time Limit</th>
                                                <th>Max Attempts</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($exams)): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted">No exams created yet</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($exams as $ex): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($ex['title']); ?></td>
                                                        <td><?php echo $ex['chapter_id'] ? 'Chapter ' . $ex['chapter_id'] : 'Course Level'; ?></td>
                                                        <td><?php echo $ex['passing_score']; ?>%</td>
                                                        <td><?php echo $ex['time_limit_minutes']; ?> min</td>
                                                        <td><?php echo $ex['max_attempts']; ?></td>
                                                        <td>
                                                            <a href="exam-questions.php?exam_id=<?php echo $ex['id']; ?>&course_id=<?php echo $courseId; ?>" class="btn btn-sm btn-primary">
                                                                <i class="fa fa-edit"></i> Manage Questions
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Admin Content End -->

    <!-- Add Resource Modal -->
    <div class="modal fade" id="addResourceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Resource</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="add_resource" value="1">
                        <div class="mb-3">
                            <label for="title" class="form-label">Resource Title *</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label for="resource_file" class="form-label">File *</label>
                            <input type="file" class="form-control" id="resource_file" name="resource_file" required accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.mp4,.mp3">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="file_type" class="form-label">File Type</label>
                                <select class="form-select" id="file_type" name="file_type">
                                    <option value="pdf">PDF</option>
                                    <option value="doc">DOC</option>
                                    <option value="docx">DOCX</option>
                                    <option value="ppt">PPT</option>
                                    <option value="pptx">PPTX</option>
                                    <option value="image">Image</option>
                                    <option value="video">Video</option>
                                    <option value="audio">Audio</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="display_order" class="form-label">Display Order</label>
                                <input type="number" class="form-control" id="display_order" name="display_order" value="0" min="0">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="chapter_id" class="form-label">Assign to Chapter (optional)</label>
                            <select class="form-select" id="chapter_id" name="chapter_id">
                                <option value="0">Course Level</option>
                                <?php foreach ($chapters as $ch): ?>
                                    <option value="<?php echo $ch['id']; ?>"><?php echo htmlspecialchars($ch['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Resource</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Exam Modal -->
    <div class="modal fade" id="addExamModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Exam</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="add_exam" value="1">
                        <div class="mb-3">
                            <label for="exam_title" class="form-label">Exam Title *</label>
                            <input type="text" class="form-control" id="exam_title" name="exam_title" required>
                        </div>
                        <div class="mb-3">
                            <label for="exam_description" class="form-label">Description</label>
                            <textarea class="form-control" id="exam_description" name="exam_description" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="passing_score" class="form-label">Passing Score (%)</label>
                                <input type="number" class="form-control" id="passing_score" name="passing_score" value="70" min="0" max="100">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="time_limit_minutes" class="form-label">Time Limit (min)</label>
                                <input type="number" class="form-control" id="time_limit_minutes" name="time_limit_minutes" value="60" min="1">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="max_attempts" class="form-label">Max Attempts</label>
                                <input type="number" class="form-control" id="max_attempts" name="max_attempts" value="3" min="1">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="chapter_id" class="form-label">Assign to Chapter (optional)</label>
                            <select class="form-select" id="chapter_id" name="chapter_id">
                                <option value="0">Course Level</option>
                                <?php foreach ($chapters as $ch): ?>
                                    <option value="<?php echo $ch['id']; ?>"><?php echo htmlspecialchars($ch['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Exam</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

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

