<?php
require_once __DIR__ . '/../includes/coach_check.php';

$conn = getDBConnection();
$coachId = getUserId();

$students = [];
$myCourseIds = [];
$res = $conn->query("SELECT id FROM courses WHERE created_by = " . intval($coachId));
if ($res) while ($row = $res->fetch_assoc()) $myCourseIds[] = (int)$row['id'];
if (!empty($myCourseIds)) {
    $ids = implode(',', $myCourseIds);
    $q = "SELECT DISTINCT u.id as user_id, u.first_name, u.last_name, u.email, c.title as course_title, c.id as course_id
          FROM users u
          JOIN session_registrations sr ON sr.user_id = u.id
          JOIN session_registration_courses src ON src.registration_id = sr.id AND src.course_id IN ($ids)
          JOIN courses c ON c.id = src.course_id
          ORDER BY u.last_name, u.first_name, c.title";
    $r = $conn->query($q);
    if ($r) while ($row = $r->fetch_assoc()) $students[] = $row;
    $q2 = "SELECT DISTINCT u.id as user_id, u.first_name, u.last_name, u.email, c.title as course_title, c.id as course_id
           FROM users u
           JOIN nclex_registrations nr ON nr.user_id = u.id
           JOIN nclex_registration_courses nrc ON nrc.registration_id = nr.id AND nrc.course_id IN ($ids)
           JOIN courses c ON c.id = nrc.course_id
           ORDER BY u.last_name, u.first_name, c.title";
    $r2 = @$conn->query($q2);
    if ($r2) while ($row = $r2->fetch_assoc()) $students[] = $row;
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Students - Coach - Orchidee LLC</title>
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
                    <h2 class="text-primary mb-0"><i class="fa fa-users me-2"></i>Students</h2>
                    <p class="text-muted small">Students assigned by admin to your courses (from Registration / NCLEX forms).</p>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-3 mb-4">
                    <div class="bg-white rounded p-4 shadow-sm coach-sidebar">
                        <nav class="nav flex-column">
                            <a class="nav-link" href="dashboard.php"><i class="fa fa-tachometer-alt me-2"></i>Dashboard</a>
                            <a class="nav-link" href="courses.php"><i class="fa fa-book me-2"></i>My Courses</a>
                            <a class="nav-link" href="live-sessions.php"><i class="fa fa-video me-2"></i>Live Sessions</a>
                            <a class="nav-link active" href="students.php"><i class="fa fa-users me-2"></i>Students</a>
                            <a class="nav-link" href="../logout.php"><i class="fa fa-sign-out-alt me-2"></i>Logout</a>
                        </nav>
                    </div>
                </div>
                <div class="col-lg-9">
                    <div class="bg-white rounded shadow-sm p-4">
                        <?php if (empty($students)): ?>
                            <p class="text-muted">No students assigned to your courses yet. Admin assigns registrations to courses from Session Registrations or NCLEX Registrations.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead><tr><th>Name</th><th>Email</th><th>Course</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($students as $st): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($st['email']); ?></td>
                                                <td><?php echo htmlspecialchars($st['course_title']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
