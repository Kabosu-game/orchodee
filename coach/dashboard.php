<?php
require_once __DIR__ . '/../includes/coach_check.php';

$coursesCount = 0;
$sessionsCount = 0;

try {
    $conn = getDBConnection();
    $coachId = getUserId() ?? 0;

    $r = @$conn->query("SELECT COUNT(*) as n FROM courses WHERE created_by = " . intval($coachId));
    if ($r && $row = $r->fetch_assoc()) $coursesCount = (int)$row['n'];

    $r2 = @$conn->query("SELECT COUNT(*) as n FROM course_live_sessions WHERE coach_id = " . intval($coachId));
    if ($r2 && $row2 = $r2->fetch_assoc()) $sessionsCount = (int)$row2['n'];

    if (isset($conn) && $conn) $conn->close();
} catch (Throwable $e) {
    error_log("Coach dashboard error: " . $e->getMessage());
    if (isset($conn) && $conn) @$conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Coach Dashboard - Orchidee LLC</title>
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
                    <h2 class="text-primary mb-0"><i class="fa fa-tachometer-alt me-2"></i>Coach Dashboard</h2>
                    <p class="text-muted mb-0">Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? ''); ?></p>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-3 mb-4">
                    <div class="bg-white rounded p-4 shadow-sm coach-sidebar">
                        <nav class="nav flex-column">
                            <a class="nav-link active" href="dashboard.php"><i class="fa fa-tachometer-alt me-2"></i>Dashboard</a>
                            <a class="nav-link" href="courses.php"><i class="fa fa-book me-2"></i>My Courses</a>
                            <a class="nav-link" href="live-sessions.php"><i class="fa fa-video me-2"></i>Live Sessions</a>
                            <a class="nav-link" href="students.php"><i class="fa fa-users me-2"></i>Students</a>
                            <a class="nav-link" href="../logout.php"><i class="fa fa-sign-out-alt me-2"></i>Logout</a>
                        </nav>
                    </div>
                </div>
                <div class="col-lg-9">
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="bg-white rounded shadow-sm p-4 h-100">
                                <h5 class="text-primary"><i class="fa fa-book me-2"></i>My Courses</h5>
                                <p class="h3 mb-0"><?php echo $coursesCount; ?></p>
                                <a href="courses.php" class="btn btn-outline-primary btn-sm mt-2">Manage courses</a>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="bg-white rounded shadow-sm p-4 h-100">
                                <h5 class="text-primary"><i class="fa fa-video me-2"></i>Live Sessions</h5>
                                <p class="h3 mb-0"><?php echo $sessionsCount; ?></p>
                                <a href="live-sessions.php" class="btn btn-outline-primary btn-sm mt-2">Manage sessions</a>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white rounded shadow-sm p-4">
                        <h5 class="mb-3">Quick actions</h5>
                        <a href="courses.php?action=add" class="btn btn-primary me-2"><i class="fa fa-plus me-2"></i>Create a course</a>
                        <a href="live-sessions.php?action=add" class="btn btn-success"><i class="fa fa-video me-2"></i>Schedule a live session</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
