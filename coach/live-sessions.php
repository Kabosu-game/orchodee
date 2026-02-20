<?php
require_once __DIR__ . '/../includes/coach_check.php';

$conn = getDBConnection();
$coachId = getUserId();

if (@$conn->query("SELECT 1 FROM course_live_sessions LIMIT 1") === false) {
    $conn->query("CREATE TABLE course_live_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        coach_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        session_date DATE NOT NULL,
        session_time TIME NOT NULL,
        meet_url VARCHAR(500) NOT NULL,
        duration_minutes INT DEFAULT 60,
        status ENUM('scheduled', 'live', 'ended', 'cancelled') DEFAULT 'scheduled',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY course_id (course_id),
        KEY coach_id (coach_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

$action = $_GET['action'] ?? 'list';
$sessionId = intval($_GET['id'] ?? 0);
$courseId = intval($_GET['course_id'] ?? 0);
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $sessionDate = trim($_POST['session_date'] ?? '');
    $sessionTime = trim($_POST['session_time'] ?? '');
    $meetUrl = trim($_POST['meet_url'] ?? '');
    $durationMinutes = intval($_POST['duration_minutes'] ?? 60);
    $postCourseId = intval($_POST['course_id'] ?? 0);
    if (empty($title) || empty($sessionDate) || empty($sessionTime) || empty($meetUrl)) {
        $error = 'Please fill in title, date, time and Google Meet URL.';
    } else {
        $stmt = $conn->prepare("SELECT id FROM courses WHERE id = ? AND created_by = ?");
        $stmt->bind_param("ii", $postCourseId, $coachId);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r->num_rows === 0) {
            $error = 'Invalid course.';
            $stmt->close();
        } else {
            $stmt->close();
            if ($action === 'add') {
                $ins = $conn->prepare("INSERT INTO course_live_sessions (course_id, coach_id, title, session_date, session_time, meet_url, duration_minutes, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled')");
                $ins->bind_param("iissssi", $postCourseId, $coachId, $title, $sessionDate, $sessionTime, $meetUrl, $durationMinutes);
                if ($ins->execute()) {
                    $ins->close();
                    $conn->close();
                    header('Location: live-sessions.php?success=added');
                    exit;
                }
                $ins->close();
            } else {
                $up = $conn->prepare("UPDATE course_live_sessions SET title=?, session_date=?, session_time=?, meet_url=?, duration_minutes=? WHERE id=? AND coach_id=?");
                $up->bind_param("ssssiii", $title, $sessionDate, $sessionTime, $meetUrl, $durationMinutes, $sessionId, $coachId);
                if ($up->execute()) {
                    $up->close();
                    $conn->close();
                    header('Location: live-sessions.php?success=updated');
                    exit;
                }
                $up->close();
            }
            $error = 'Failed to save session.';
        }
    }
}

$myCourses = [];
$res = $conn->query("SELECT id, title FROM courses WHERE created_by = " . intval($coachId) . " ORDER BY title");
if ($res) while ($row = $res->fetch_assoc()) $myCourses[] = $row;

$sessions = [];
$q = "SELECT cls.*, c.title as course_title FROM course_live_sessions cls JOIN courses c ON cls.course_id = c.id WHERE cls.coach_id = " . intval($coachId) . " ORDER BY cls.session_date DESC, cls.session_time DESC";
$result = $conn->query($q);
if ($result) while ($row = $result->fetch_assoc()) $sessions[] = $row;

$session = null;
if ($action === 'edit' && $sessionId) {
    $stmt = $conn->prepare("SELECT cls.*, c.title as course_title FROM course_live_sessions cls JOIN courses c ON cls.course_id = c.id WHERE cls.id = ? AND cls.coach_id = ?");
    $stmt->bind_param("ii", $sessionId, $coachId);
    $stmt->execute();
    $r = $stmt->get_result();
    $session = $r->fetch_assoc();
    $stmt->close();
    if (!$session) { $conn->close(); header('Location: live-sessions.php'); exit; }
}

if (isset($_GET['success'])) {
    $success = $_GET['success'] === 'added' ? 'Live session created.' : 'Live session updated.';
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Live Sessions - Coach - Orchidee LLC</title>
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
                    <h2 class="text-primary mb-0"><i class="fa fa-video me-2"></i>Live Sessions (Google Meet)</h2>
                    <p class="text-muted small">Paste your Google Meet link. Students will join the session on the platform (embedded).</p>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-3 mb-4">
                    <div class="bg-white rounded p-4 shadow-sm coach-sidebar">
                        <nav class="nav flex-column">
                            <a class="nav-link" href="dashboard.php"><i class="fa fa-tachometer-alt me-2"></i>Dashboard</a>
                            <a class="nav-link" href="courses.php"><i class="fa fa-book me-2"></i>My Courses</a>
                            <a class="nav-link active" href="live-sessions.php"><i class="fa fa-video me-2"></i>Live Sessions</a>
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
                            <h4 class="mb-4"><?php echo $action === 'add' ? 'Schedule live session' : 'Edit session'; ?></h4>
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label class="form-label">Course <span class="text-danger">*</span></label>
                                    <?php if ($action === 'edit'): ?>
                                        <input type="hidden" name="course_id" value="<?php echo (int)$session['course_id']; ?>">
                                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($session['course_title'] ?? ''); ?>" disabled>
                                    <?php else: ?>
                                        <select class="form-select" name="course_id" required>
                                            <?php foreach ($myCourses as $mc): ?>
                                                <option value="<?php echo $mc['id']; ?>" <?php echo $courseId == $mc['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($mc['title']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Session title <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($session['title'] ?? ''); ?>" required placeholder="e.g. Week 1 - Introduction">
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" name="session_date" value="<?php echo htmlspecialchars($session['session_date'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Time <span class="text-danger">*</span></label>
                                        <input type="time" class="form-control" name="session_time" value="<?php echo htmlspecialchars($session['session_time'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Google Meet URL <span class="text-danger">*</span></label>
                                    <input type="url" class="form-control" name="meet_url" value="<?php echo htmlspecialchars($session['meet_url'] ?? ''); ?>" required placeholder="https://meet.google.com/xxx-xxxx-xxx">
                                    <small class="text-muted">Students will see the meeting embedded on the platform.</small>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label">Duration (minutes)</label>
                                    <input type="number" class="form-control" name="duration_minutes" value="<?php echo (int)($session['duration_minutes'] ?? 60); ?>" min="15">
                                </div>
                                <button type="submit" class="btn btn-primary">Save session</button>
                                <a href="live-sessions.php" class="btn btn-secondary">Cancel</a>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="bg-white rounded shadow-sm p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span>Upcoming and past sessions</span>
                                <a href="live-sessions.php?action=add" class="btn btn-primary"><i class="fa fa-plus me-2"></i>Schedule session</a>
                            </div>
                            <?php if (empty($sessions)): ?>
                                <p class="text-muted">No live sessions yet.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead><tr><th>Course</th><th>Title</th><th>Date</th><th>Time</th><th>Meet link</th><th>Status</th><th>Actions</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($sessions as $s): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($s['course_title']); ?></td>
                                                    <td><?php echo htmlspecialchars($s['title']); ?></td>
                                                    <td><?php echo date('Y-m-d', strtotime($s['session_date'])); ?></td>
                                                    <td><?php echo date('g:i A', strtotime($s['session_time'])); ?></td>
                                                    <td><a href="<?php echo htmlspecialchars($s['meet_url']); ?>" target="_blank" rel="noopener">Open Meet</a></td>
                                                    <td><span class="badge bg-<?php echo $s['status'] === 'scheduled' ? 'primary' : ($s['status'] === 'ended' ? 'secondary' : 'warning'); ?>"><?php echo $s['status']; ?></span></td>
                                                    <td>
                                                        <a href="../live-session-view.php?session_id=<?php echo $s['id']; ?>" class="btn btn-sm btn-success" target="_blank">Join</a>
                                                        <a href="live-sessions.php?action=edit&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
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
