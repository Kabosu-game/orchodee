<?php
require_once __DIR__ . '/../includes/coach_check.php';

$conn = getDBConnection();
$coachId = getUserId();

$action = $_GET['action'] ?? 'list';
$courseId = intval($_GET['id'] ?? 0);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $shortDescription = trim($_POST['short_description'] ?? '');
    if (empty($title)) {
        $error = 'Title is required.';
    } else {
        if ($action === 'add') {
            $col = @$conn->query("SHOW COLUMNS FROM courses LIKE 'visible_public'");
            if (!$col || $col->num_rows === 0) {
                $conn->query("ALTER TABLE courses ADD visible_public TINYINT(1) DEFAULT 1");
            }
        }
        $price = 0;
        $categoryId = null; // NULL = no category (coach courses)
        $instructorName = $_SESSION['user_name'] ?? '';
        $durationHours = 0;
        $level = 'intermediate';
        $status = 'published';
        $visiblePublic = 0;
        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO courses (title, description, short_description, price, category_id, instructor_name, duration_hours, level, status, created_by, visible_public) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssdisissii", $title, $description, $shortDescription, $price, $categoryId, $instructorName, $durationHours, $level, $status, $coachId, $visiblePublic);
        } else {
            $stmt = $conn->prepare("UPDATE courses SET title=?, description=?, short_description=? WHERE id=? AND created_by=?");
            $stmt->bind_param("sssii", $title, $description, $shortDescription, $courseId, $coachId);
        }
        if ($stmt->execute()) {
            $stmt->close();
            $conn->close();
            header('Location: courses.php?success=' . ($action === 'add' ? 'added' : 'updated'));
            exit;
        }
        $error = 'Failed to save course.';
        $stmt->close();
    }
}

$courses = [];
$res = $conn->query("SELECT id, title, description, short_description, status, created_at FROM courses WHERE created_by = " . intval($coachId) . " ORDER BY created_at DESC");
if ($res) while ($row = $res->fetch_assoc()) $courses[] = $row;

$course = null;
if ($action === 'edit' && $courseId) {
    $stmt = $conn->prepare("SELECT * FROM courses WHERE id = ? AND created_by = ?");
    $stmt->bind_param("ii", $courseId, $coachId);
    $stmt->execute();
    $r = $stmt->get_result();
    $course = $r->fetch_assoc();
    $stmt->close();
    if (!$course) { $conn->close(); header('Location: courses.php'); exit; }
}

if (isset($_GET['success'])) {
    $success = $_GET['success'] === 'added' ? 'Course created successfully.' : 'Course updated successfully.';
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo $action === 'add' || $action === 'edit' ? ($action === 'add' ? 'Add' : 'Edit') . ' Course' : 'My Courses'; ?> - Coach - Orchidee LLC</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"/>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <style>
        .coach-sidebar { background: #f8f9fa; border-right: 1px solid #dee2e6; min-height: calc(100vh - 100px); }
        .coach-sidebar .nav-link { color: #495057; padding: 12px 20px; border-radius: 5px; margin-bottom: 5px; }
        .coach-sidebar .nav-link:hover, .coach-sidebar .nav-link.active { background: #007bff; color: white; }
    </style>
</head>
<body>
    <?php include '../includes/menu-dynamic.php'; ?>
    <div class="container-fluid bg-light py-5">
        <div class="container py-5">
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="text-primary mb-0"><i class="fa fa-book me-2"></i>My Courses</h2>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-3 mb-4">
                    <div class="bg-white rounded p-4 shadow-sm coach-sidebar">
                        <nav class="nav flex-column">
                            <a class="nav-link" href="dashboard.php"><i class="fa fa-tachometer-alt me-2"></i>Dashboard</a>
                            <a class="nav-link active" href="courses.php"><i class="fa fa-book me-2"></i>My Courses</a>
                            <a class="nav-link" href="live-sessions.php"><i class="fa fa-video me-2"></i>Live Sessions</a>
                            <a class="nav-link" href="students.php"><i class="fa fa-users me-2"></i>Students</a>
                            <a class="nav-link" href="../logout.php"><i class="fa fa-sign-out-alt me-2"></i>Logout</a>
                        </nav>
                    </div>
                </div>
                <div class="col-lg-9">
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <?php if ($action === 'add' || $action === 'edit'): ?>
                        <div class="bg-white rounded shadow-sm p-5">
                            <h4 class="mb-4"><?php echo $action === 'add' ? 'Create course' : 'Edit course'; ?></h4>
                            <p class="text-muted small">Coach courses have no price and are not shown on the public site. They are assigned by the admin to registrations.</p>
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label class="form-label">Title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($course['title'] ?? ''); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Short description</label>
                                    <textarea class="form-control" name="short_description" rows="2"><?php echo htmlspecialchars($course['short_description'] ?? ''); ?></textarea>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description" rows="5"><?php echo htmlspecialchars($course['description'] ?? ''); ?></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">Save course</button>
                                <a href="courses.php" class="btn btn-secondary">Cancel</a>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="bg-white rounded shadow-sm p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span>Your coaching courses (assigned by admin to registrations)</span>
                                <a href="courses.php?action=add" class="btn btn-primary"><i class="fa fa-plus me-2"></i>Add course</a>
                            </div>
                            <?php if (empty($courses)): ?>
                                <p class="text-muted">No courses yet. Create one to get started.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead><tr><th>Title</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($courses as $c): ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($c['title']); ?></strong></td>
                                                    <td><span class="badge bg-success"><?php echo htmlspecialchars($c['status']); ?></span></td>
                                                    <td><?php echo date('Y-m-d', strtotime($c['created_at'])); ?></td>
                                                    <td>
                                                        <a href="courses.php?action=edit&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                                        <a href="live-sessions.php?course_id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline-success">Sessions</a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
