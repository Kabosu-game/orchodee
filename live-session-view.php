<?php
require_once 'includes/auth_check.php';

$sessionId = intval($_GET['session_id'] ?? 0);
if (!$sessionId) {
    header('Location: my-coaching.php');
    exit;
}

$conn = getDBConnection();
$userId = getUserId();

$stmt = @$conn->prepare("SELECT cls.*, c.title as course_title FROM course_live_sessions cls JOIN courses c ON cls.course_id = c.id WHERE cls.id = ?");
if (!$stmt) {
    $conn->close();
    header('Location: my-coaching.php');
    exit;
}
$stmt->bind_param("i", $sessionId);
$stmt->execute();
$r = $stmt->get_result();
$session = $r->fetch_assoc();
$stmt->close();
if (!$session) {
    $conn->close();
    header('Location: my-coaching.php');
    exit;
}

$courseId = (int)$session['course_id'];
$hasAccess = false;
$st1 = $conn->prepare("SELECT 1 FROM session_registrations sr JOIN session_registration_courses src ON src.registration_id = sr.id WHERE sr.user_id = ? AND src.course_id = ?");
$st1->bind_param("ii", $userId, $courseId);
$st1->execute();
if ($st1->get_result()->num_rows > 0) $hasAccess = true;
$st1->close();
if (!$hasAccess) {
    $st2 = @$conn->prepare("SELECT 1 FROM nclex_registrations nr JOIN nclex_registration_courses nrc ON nrc.registration_id = nr.id WHERE nr.user_id = ? AND nrc.course_id = ?");
    if ($st2) {
        $st2->bind_param("ii", $userId, $courseId);
        $st2->execute();
        if ($st2->get_result()->num_rows > 0) $hasAccess = true;
        $st2->close();
    }
}
$conn->close();

if (!$hasAccess) {
    header('Location: my-coaching.php');
    exit;
}

$meetUrl = $session['meet_url'];
if (strpos($meetUrl, 'meet.google.com') !== false && strpos($meetUrl, '/') !== false) {
    $parts = parse_url($meetUrl);
    $path = $parts['path'] ?? '';
    if (preg_match('#([a-z]{3}-[a-z]{4}-[a-z]{3})#', $meetUrl, $m)) {
        $meetCode = $m[1];
        $meetUrl = 'https://meet.google.com/' . $meetCode;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Live Session: <?php echo htmlspecialchars($session['title']); ?> - Orchidee LLC</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"/>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { margin: 0; padding: 0; background: #1a1a1a; min-height: 100vh; }
        .meet-header { background: #2d2d2d; color: #fff; padding: 12px 20px; display: flex; align-items: center; justify-content: space-between; }
        .meet-header a { color: #8ab4f8; }
        .meet-card { max-width: 480px; margin: 60px auto; padding: 32px; background: #2d2d2d; border-radius: 12px; text-align: center; }
        .meet-card h2 { color: #fff; margin-bottom: 8px; font-size: 1.25rem; }
        .meet-card .course { color: #aaa; margin-bottom: 24px; }
        .meet-card p { color: #ccc; font-size: 0.95rem; margin-bottom: 24px; }
        .btn-meet { display: inline-flex; align-items: center; gap: 10px; padding: 14px 28px; background: #1a73e8; color: #fff !important; border-radius: 8px; text-decoration: none; font-weight: 600; transition: background 0.2s; }
        .btn-meet:hover { background: #1557b0; color: #fff; }
        .btn-back { display: inline-block; margin-top: 16px; color: #8ab4f8; text-decoration: none; font-size: 0.9rem; }
        .btn-back:hover { text-decoration: underline; color: #a8c7fa; }
    </style>
</head>
<body>
    <div class="meet-header">
        <span><i class="fa fa-video me-2"></i><?php echo htmlspecialchars($session['course_title']); ?> — <?php echo htmlspecialchars($session['title']); ?></span>
        <a href="my-coaching.php"><i class="fa fa-arrow-left me-2"></i>Back to My Coaching</a>
    </div>
    <div class="meet-card">
        <h2><?php echo htmlspecialchars($session['title']); ?></h2>
        <div class="course"><?php echo htmlspecialchars($session['course_title']); ?></div>
        <p>Google Meet ne peut pas être affiché dans cette page. Cliquez ci-dessous pour ouvrir la session dans un nouvel onglet.</p>
        <a href="<?php echo htmlspecialchars($meetUrl); ?>" target="_blank" rel="noopener noreferrer" class="btn-meet">
            <i class="fab fa-google"></i> Ouvrir la session sur Google Meet
        </a>
        <br>
        <a href="my-coaching.php" class="btn-back"><i class="fa fa-arrow-left me-2"></i>Retour à My Coaching</a>
    </div>
</body>
</html>
