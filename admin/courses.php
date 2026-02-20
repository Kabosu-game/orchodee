<?php
require_once '../includes/admin_check.php';

$conn = getDBConnection();
$action = $_GET['action'] ?? 'list';
$courseId = intval($_GET['id'] ?? 0);

// Traitement de la suppression (GET)
if ($action === 'delete' && $courseId > 0) {
    // Vérifier que le cours existe
    $checkStmt = $conn->prepare("SELECT id, title FROM courses WHERE id = ?");
    $checkStmt->bind_param("i", $courseId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        $courseToDelete = $checkResult->fetch_assoc();
        
        // Supprimer le cours
        $stmt = $conn->prepare("DELETE FROM courses WHERE id = ?");
        $stmt->bind_param("i", $courseId);
        
        if ($stmt->execute()) {
            $stmt->close();
            $checkStmt->close();
            $conn->close();
            redirect('courses.php?success=deleted');
        } else {
            $error = 'Failed to delete course: ' . $conn->error;
        }
        $stmt->close();
    } else {
        $error = 'Course not found.';
    }
    $checkStmt->close();
}

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'edit') {
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $shortDescription = sanitize($_POST['short_description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $categoryId = intval($_POST['category_id'] ?? 0);
        $instructorName = sanitize($_POST['instructor_name'] ?? '');
        $durationHours = intval($_POST['duration_hours'] ?? 0);
        $level = sanitize($_POST['level'] ?? 'intermediate');
        $status = sanitize($_POST['status'] ?? 'draft');
        $userId = getUserId();
        
        // Handle image upload
        $image = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $uploadDir = '../uploads/courses/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileName = time() . '_' . basename($_FILES['image']['name']);
            $targetFile = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                $image = 'uploads/courses/' . $fileName;
            }
        }
        
        if ($action === 'add') {
            if ($image) {
                $stmt = $conn->prepare("INSERT INTO courses (title, description, short_description, price, image, category_id, instructor_name, duration_hours, level, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssdssisssi", $title, $description, $shortDescription, $price, $image, $categoryId, $instructorName, $durationHours, $level, $status, $userId);
            } else {
                $stmt = $conn->prepare("INSERT INTO courses (title, description, short_description, price, category_id, instructor_name, duration_hours, level, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssdisisssi", $title, $description, $shortDescription, $price, $categoryId, $instructorName, $durationHours, $level, $status, $userId);
            }
        } else {
            if ($image) {
                $stmt = $conn->prepare("UPDATE courses SET title=?, description=?, short_description=?, price=?, image=?, category_id=?, instructor_name=?, duration_hours=?, level=?, status=? WHERE id=?");
                $stmt->bind_param("sssdssisssi", $title, $description, $shortDescription, $price, $image, $categoryId, $instructorName, $durationHours, $level, $status, $courseId);
            } else {
                $stmt = $conn->prepare("UPDATE courses SET title=?, description=?, short_description=?, price=?, category_id=?, instructor_name=?, duration_hours=?, level=?, status=? WHERE id=?");
                $stmt->bind_param("sssdisisssi", $title, $description, $shortDescription, $price, $categoryId, $instructorName, $durationHours, $level, $status, $courseId);
            }
        }
        
        if ($stmt->execute()) {
            $stmt->close();
            $conn->close();
            redirect('courses.php?success=' . ($action === 'add' ? 'added' : 'updated'));
        } else {
            $error = 'Failed to save course: ' . $conn->error;
        }
        $stmt->close();
    }
}

// Get categories
$categories = [];
$catResult = $conn->query("SELECT * FROM course_categories ORDER BY name");
while ($row = $catResult->fetch_assoc()) {
    $categories[] = $row;
}

// If action = add or edit, show the form
if ($action === 'add' || $action === 'edit') {
    $course = null;
    if ($action === 'edit' && $courseId) {
        $stmt = $conn->prepare("SELECT * FROM courses WHERE id = ?");
        $stmt->bind_param("i", $courseId);
        $stmt->execute();
        $result = $stmt->get_result();
        $course = $result->fetch_assoc();
        $stmt->close();
        if (!$course) {
            $conn->close();
            redirect('courses.php');
        }
    }
    $conn->close();
    include 'course-form.php';
    exit;
}

// List courses
$courses = [];
$result = $conn->query("
    SELECT c.*, cat.name as category_name 
    FROM courses c 
    LEFT JOIN course_categories cat ON c.category_id = cat.id 
    ORDER BY c.created_at DESC
");
while ($row = $result->fetch_assoc()) {
    $courses[] = $row;
}
$conn->close();

$success = isset($_GET['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Manage Courses - Admin - Orchidee LLC</title>
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

    <!-- Manage Courses Start -->
    <div class="container-fluid bg-light py-5">
        <div class="container py-5">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="text-primary mb-0">
                            <i class="fa fa-book me-2"></i>Manage Courses
                        </h2>
                        <div>
                            <a href="courses.php?action=add" class="btn btn-primary">
                                <i class="fa fa-plus me-2"></i>Add New Course
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                <i class="fa fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fa fa-check-circle me-2"></i>
                    <?php 
                        $successMessages = [
                            'added' => 'Course added successfully!',
                            'updated' => 'Course updated successfully!',
                            'deleted' => 'Course deleted successfully!',
                            '1' => 'Operation completed successfully!'
                        ];
                        echo $successMessages[$_GET['success']] ?? 'Operation completed successfully!';
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded p-4 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Image</th>
                                <th>Title</th>
                                <th>Category</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($courses)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="fa fa-book-open fa-3x mb-3 d-block"></i>
                                        No courses yet. <a href="courses.php?action=add">Add your first course</a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($courses as $course): 
                                    $image = !empty($course['image']) ? '../' . $course['image'] : '../img/carousel-2.png';
                                ?>
                                    <tr>
                                        <td>
                                            <img src="<?php echo htmlspecialchars($image); ?>" alt="" style="width: 60px; height: 60px; object-fit: cover; border-radius: 5px;">
                                        </td>
                                        <td><?php echo htmlspecialchars($course['title']); ?></td>
                                        <td><?php echo htmlspecialchars($course['category_name'] ?? 'Uncategorized'); ?></td>
                                        <td>$<?php echo number_format($course['price'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $course['status'] === 'published' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($course['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($course['created_at'])); ?></td>
                                        <td>
                                            <a href="courses.php?action=edit&id=<?php echo $course['id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                            <a href="course-chapters.php?course_id=<?php echo $course['id']; ?>" class="btn btn-sm btn-info" title="Manage Chapters">
                                                <i class="fa fa-list"></i>
                                            </a>
                                            <a href="course-resources.php?course_id=<?php echo $course['id']; ?>" class="btn btn-sm btn-success" title="Resources & Exams">
                                                <i class="fa fa-folder-open"></i>
                                            </a>
                                            <a href="courses.php?action=delete&id=<?php echo $course['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this course?\n\nThis will also delete all chapters, lessons, and resources associated with this course.\n\nThis action cannot be undone!');">
                                                <i class="fa fa-trash"></i>
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
    <!-- Manage Courses End -->

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

