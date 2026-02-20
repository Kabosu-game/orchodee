<?php
require_once 'includes/auth_check.php';

$conn = getDBConnection();
$userId = getUserId();

$assignedCourses = [];
$myCourseIds = [];

$r = $conn->query("SELECT src.course_id FROM session_registrations sr JOIN session_registration_courses src ON src.registration_id = sr.id WHERE sr.user_id = " . intval($userId));
if ($r) while ($row = $r->fetch_assoc()) $myCourseIds[$row['course_id']] = true;
$r2 = @$conn->query("SELECT nrc.course_id FROM nclex_registrations nr JOIN nclex_registration_courses nrc ON nrc.registration_id = nr.id WHERE nr.user_id = " . intval($userId));
if ($r2) while ($row = $r2->fetch_assoc()) $myCourseIds[$row['course_id']] = true;
$myCourseIds = array_keys($myCourseIds);
if (!empty($myCourseIds)) {
    $ids = implode(',', array_map('intval', $myCourseIds));
    $coursesRes = $conn->query("SELECT id, title, description, short_description FROM courses WHERE id IN ($ids) ORDER BY title");
    if ($coursesRes) {
        while ($c = $coursesRes->fetch_assoc()) {
            $c['sessions'] = [];
            $assignedCourses[] = $c;
        }
    }
    $sessionsRes = @$conn->query("SELECT id, course_id, title, session_date, session_time, meet_url, status FROM course_live_sessions WHERE course_id IN ($ids) AND status IN ('scheduled', 'live') ORDER BY session_date ASC, session_time ASC");
    if ($sessionsRes) {
        while ($s = $sessionsRes->fetch_assoc()) {
            foreach ($assignedCourses as &$co) {
                if ($co['id'] == $s['course_id']) {
                    $co['sessions'][] = $s;
                    break;
                }
            }
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>My Coaching - Orchidee LLC</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"/>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/menu-dynamic.php'; ?>
    <div class="container-fluid bg-light py-5">
        <div class="container py-5">
            <div class="row mb-4">
                <div class="col-12">
                    <h2 class="text-primary mb-0"><i class="fa fa-video me-2"></i>My Coaching</h2>
                    <p class="text-muted">Courses assigned to you. Join live sessions directly on the platform (Google Meet embedded).</p>
                </div>
            </div>
            <?php if (empty($assignedCourses)): ?>
                <div class="bg-white rounded shadow-sm p-5 text-center">
                    <p class="text-muted mb-0">You have no assigned coaching courses yet. Complete a registration form and the admin will assign you to courses.</p>
                    <a href="index.php" class="btn btn-primary mt-3">Back to Home</a>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($assignedCourses as $course): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="bg-white rounded shadow-sm p-4 h-100">
                                <h5 class="text-primary"><?php echo htmlspecialchars($course['title']); ?></h5>
                                <?php if (!empty($course['short_description'])): ?>
                                    <p class="text-muted small"><?php echo htmlspecialchars($course['short_description']); ?></p>
                                <?php endif; ?>
                                <?php if (empty($course['sessions'])): ?>
                                    <p class="text-muted small mb-0">No upcoming live sessions.</p>
                                <?php else: ?>
                                    <p class="fw-bold mb-2">Upcoming sessions:</p>
                                    <ul class="list-unstyled mb-0">
                                        <?php foreach ($course['sessions'] as $sess): ?>
                                            <li class="mb-2">
                                                <span><?php echo htmlspecialchars($sess['title']); ?></span> —
                                                <span><?php echo date('M j, Y', strtotime($sess['session_date'])); ?> at <?php echo date('g:i A', strtotime($sess['session_time'])); ?></span>
                                                <a href="live-session-view.php?session_id=<?php echo (int)$sess['id']; ?>" class="btn btn-sm btn-success ms-2" target="_blank">Join session</a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
