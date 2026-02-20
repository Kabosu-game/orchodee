<?php
require_once '../includes/admin_check.php';

$conn = getDBConnection();
$action = $_GET['action'] ?? 'list';
$webinarId = $_GET['id'] ?? 0;

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'edit') {
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $meetingUrl = sanitize($_POST['meeting_url'] ?? '');
        $meetingId = sanitize($_POST['meeting_id'] ?? '');
        $meetingPassword = sanitize($_POST['meeting_password'] ?? '');
        $scheduledDate = sanitize($_POST['scheduled_date'] ?? '');
        $durationMinutes = intval($_POST['duration_minutes'] ?? 60);
        $maxParticipants = intval($_POST['max_participants'] ?? 100);
        $status = sanitize($_POST['status'] ?? 'scheduled');
        $createdBy = getUserId();
        
        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO webinars (title, description, meeting_url, meeting_id, meeting_password, scheduled_date, duration_minutes, max_participants, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssiiss", $title, $description, $meetingUrl, $meetingId, $meetingPassword, $scheduledDate, $durationMinutes, $maxParticipants, $status, $createdBy);
        } else {
            $stmt = $conn->prepare("UPDATE webinars SET title=?, description=?, meeting_url=?, meeting_id=?, meeting_password=?, scheduled_date=?, duration_minutes=?, max_participants=?, status=? WHERE id=?");
            $stmt->bind_param("ssssssiisi", $title, $description, $meetingUrl, $meetingId, $meetingPassword, $scheduledDate, $durationMinutes, $maxParticipants, $status, $webinarId);
        }
        
        if ($stmt->execute()) {
            header("Location: webinars.php?success=" . ($action === 'add' ? 'added' : 'updated'));
            exit;
        }
        $stmt->close();
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM webinars WHERE id = ?");
        $stmt->bind_param("i", $webinarId);
        $stmt->execute();
        $stmt->close();
        header("Location: webinars.php?success=deleted");
        exit;
    }
}

// Récupération des données
$webinar = null;
if ($action === 'edit' && $webinarId) {
    $stmt = $conn->prepare("SELECT * FROM webinars WHERE id = ?");
    $stmt->bind_param("i", $webinarId);
    $stmt->execute();
    $result = $stmt->get_result();
    $webinar = $result->fetch_assoc();
    $stmt->close();
}

// Liste des webinaires
$webinars = [];
$result = $conn->query("SELECT w.*, u.first_name, u.last_name, 
    (SELECT COUNT(*) FROM webinar_registrations WHERE webinar_id = w.id) as registrations_count
    FROM webinars w 
    LEFT JOIN users u ON w.created_by = u.id 
    ORDER BY w.scheduled_date DESC");
while ($row = $result->fetch_assoc()) {
    $webinars[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Webinar Management - Orchidee LLC</title>
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
                            <i class="fa fa-video me-2"></i>Webinar Management
                        </h2>
                        <div>
                            <a href="dashboard.php" class="btn btn-outline-secondary me-2">
                                <i class="fa fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                            <a href="webinars.php?action=add" class="btn btn-primary">
                                <i class="fa fa-plus me-2"></i>Schedule Webinar
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
                                <i class="fa fa-book me-2"></i>Manage Courses
                            </a>
                            <a class="nav-link" href="blog.php">
                                <i class="fa fa-blog me-2"></i>Blog Management
                            </a>
                            <a class="nav-link active" href="webinars.php">
                                <i class="fa fa-video me-2"></i>Webinars
                            </a>
                            <a class="nav-link" href="sessions.php">
                                <i class="fa fa-calendar-check me-2"></i>NCLEX Sessions
                            </a>
                            <a class="nav-link" href="users.php">
                                <i class="fa fa-users me-2"></i>Users
                            </a>
                        </nav>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="col-lg-9">
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            Webinar <?php echo $_GET['success']; ?> successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($action === 'add' || $action === 'edit'): ?>
                        <!-- Add/Edit Form -->
                        <div class="bg-white rounded p-4 shadow-sm">
                            <h4 class="mb-4"><?php echo $action === 'add' ? 'Schedule New' : 'Edit'; ?> Webinar</h4>
                            
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Webinar Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars(($webinar ?? [])['title'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars(($webinar ?? [])['description'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="scheduled_date" class="form-label">Scheduled Date & Time *</label>
                                        <input type="datetime-local" class="form-control" id="scheduled_date" name="scheduled_date" value="<?php echo ($webinar && !empty($webinar['scheduled_date'])) ? date('Y-m-d\TH:i', strtotime($webinar['scheduled_date'])) : ''; ?>" required>
                                    </div>
                                    
                                    <div class="col-md-3 mb-3">
                                        <label for="duration_minutes" class="form-label">Duration (minutes)</label>
                                        <input type="number" class="form-control" id="duration_minutes" name="duration_minutes" value="<?php echo ($webinar ?? [])['duration_minutes'] ?? 60; ?>" min="15" step="15">
                                    </div>
                                    
                                    <div class="col-md-3 mb-3">
                                        <label for="max_participants" class="form-label">Max Participants</label>
                                        <input type="number" class="form-control" id="max_participants" name="max_participants" value="<?php echo ($webinar ?? [])['max_participants'] ?? 100; ?>" min="1">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="meeting_url" class="form-label">Meeting URL (Zoom, Teams, etc.) *</label>
                                    <input type="url" class="form-control" id="meeting_url" name="meeting_url" value="<?php echo htmlspecialchars(($webinar ?? [])['meeting_url'] ?? ''); ?>" placeholder="https://zoom.us/j/..." required>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="meeting_id" class="form-label">Meeting ID</label>
                                        <input type="text" class="form-control" id="meeting_id" name="meeting_id" value="<?php echo htmlspecialchars(($webinar ?? [])['meeting_id'] ?? ''); ?>" placeholder="123 456 7890">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="meeting_password" class="form-label">Meeting Password</label>
                                        <input type="text" class="form-control" id="meeting_password" name="meeting_password" value="<?php echo htmlspecialchars(($webinar ?? [])['meeting_password'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="scheduled" <?php echo (($webinar ?? [])['status'] ?? 'scheduled') === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                        <option value="live" <?php echo (($webinar ?? [])['status'] ?? '') === 'live' ? 'selected' : ''; ?>>Live</option>
                                        <option value="completed" <?php echo (($webinar ?? [])['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="cancelled" <?php echo (($webinar ?? [])['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <a href="webinars.php" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-save me-2"></i>Save Webinar
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- List View -->
                        <div class="bg-white rounded p-4 shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4 class="mb-0">All Webinars</h4>
                                <a href="webinars.php?action=add" class="btn btn-primary btn-sm">
                                    <i class="fa fa-plus me-2"></i>Schedule New
                                </a>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Scheduled Date</th>
                                            <th>Duration</th>
                                            <th>Registrations</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($webinars)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">No webinars scheduled yet</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($webinars as $w): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($w['title']); ?></td>
                                                    <td><?php echo date('M d, Y g:i A', strtotime($w['scheduled_date'])); ?></td>
                                                    <td><?php echo $w['duration_minutes']; ?> min</td>
                                                    <td><?php echo $w['registrations_count']; ?> / <?php echo $w['max_participants']; ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $w['status'] === 'live' ? 'danger' : 
                                                            ($w['status'] === 'completed' ? 'success' : 
                                                            ($w['status'] === 'cancelled' ? 'secondary' : 'primary')); 
                                                        ?>">
                                                            <?php echo ucfirst($w['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="webinars.php?action=edit&id=<?php echo $w['id']; ?>" class="btn btn-sm btn-primary">
                                                            <i class="fa fa-edit"></i>
                                                        </a>
                                                        <a href="webinar-registrations.php?webinar_id=<?php echo $w['id']; ?>" class="btn btn-sm btn-info">
                                                            <i class="fa fa-users"></i>
                                                        </a>
                                                        <a href="webinars.php?action=delete&id=<?php echo $w['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">
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
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <!-- Admin Content End -->

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

