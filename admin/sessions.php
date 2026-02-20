<?php
require_once '../includes/admin_check.php';

$conn = getDBConnection();
$action = $_GET['action'] ?? 'list';
$sessionId = $_GET['id'] ?? 0;

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'edit') {
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $sessionType = sanitize($_POST['session_type'] ?? '');
        $startDate = sanitize($_POST['start_date'] ?? '');
        $endDate = sanitize($_POST['end_date'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $maxStudents = intval($_POST['max_students'] ?? 50);
        $status = sanitize($_POST['status'] ?? 'open');
        $createdBy = getUserId();
        
        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO nclex_sessions (title, description, session_type, start_date, end_date, price, max_students, status, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssdiis", $title, $description, $sessionType, $startDate, $endDate, $price, $maxStudents, $status, $createdBy);
        } else {
            $stmt = $conn->prepare("UPDATE nclex_sessions SET title=?, description=?, session_type=?, start_date=?, end_date=?, price=?, max_students=?, status=? WHERE id=?");
            $stmt->bind_param("sssssdiisi", $title, $description, $sessionType, $startDate, $endDate, $price, $maxStudents, $status, $sessionId);
        }
        
        if ($stmt->execute()) {
            header("Location: sessions.php?success=" . ($action === 'add' ? 'added' : 'updated'));
            exit;
        }
        $stmt->close();
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM nclex_sessions WHERE id = ?");
        $stmt->bind_param("i", $sessionId);
        $stmt->execute();
        $stmt->close();
        header("Location: sessions.php?success=deleted");
        exit;
    }
}

// Récupération des données
$session = null;
if ($action === 'edit' && $sessionId) {
    $stmt = $conn->prepare("SELECT * FROM nclex_sessions WHERE id = ?");
    $stmt->bind_param("i", $sessionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $session = $result->fetch_assoc();
    $stmt->close();
}

// Liste des sessions
$sessions = [];
$result = $conn->query("SELECT s.*, 
    (SELECT COUNT(*) FROM nclex_session_registrations WHERE session_id = s.id) as registrations_count,
    (SELECT COUNT(*) FROM nclex_session_registrations WHERE session_id = s.id AND payment_status = 'completed') as paid_count
    FROM nclex_sessions s 
    ORDER BY s.start_date DESC");
while ($row = $result->fetch_assoc()) {
    $sessions[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>NCLEX Sessions Management - Orchidee LLC</title>
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
                            <i class="fa fa-calendar-check me-2"></i>NCLEX Sessions Management
                        </h2>
                        <div>
                            <a href="dashboard.php" class="btn btn-outline-secondary me-2">
                                <i class="fa fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                            <a href="sessions.php?action=add" class="btn btn-primary">
                                <i class="fa fa-plus me-2"></i>Create New Session
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
                            <a class="nav-link" href="webinars.php">
                                <i class="fa fa-video me-2"></i>Webinars
                            </a>
                            <a class="nav-link active" href="sessions.php">
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
                            Session <?php echo $_GET['success']; ?> successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($action === 'add' || $action === 'edit'): ?>
                        <!-- Add/Edit Form -->
                        <div class="bg-white rounded p-4 shadow-sm">
                            <h4 class="mb-4"><?php echo $action === 'add' ? 'Create New' : 'Edit'; ?> NCLEX Session</h4>
                            
                            <form method="POST">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Session Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($session['title'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($session['description'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="session_type" class="form-label">Session Type *</label>
                                        <select class="form-select" id="session_type" name="session_type" required>
                                            <option value="3-months" <?php echo ($session['session_type'] ?? '') === '3-months' ? 'selected' : ''; ?>>3 Months</option>
                                            <option value="6-months" <?php echo ($session['session_type'] ?? '') === '6-months' ? 'selected' : ''; ?>>6 Months</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="start_date" class="form-label">Start Date *</label>
                                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $session['start_date'] ?? ''; ?>" required>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="end_date" class="form-label">End Date *</label>
                                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $session['end_date'] ?? ''; ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="price" class="form-label">Price ($) *</label>
                                        <input type="number" class="form-control" id="price" name="price" value="<?php echo $session['price'] ?? 0; ?>" step="0.01" min="0" required>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="max_students" class="form-label">Max Students</label>
                                        <input type="number" class="form-control" id="max_students" name="max_students" value="<?php echo $session['max_students'] ?? 50; ?>" min="1">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="open" <?php echo ($session['status'] ?? 'open') === 'open' ? 'selected' : ''; ?>>Open for Registration</option>
                                        <option value="full" <?php echo ($session['status'] ?? '') === 'full' ? 'selected' : ''; ?>>Full</option>
                                        <option value="closed" <?php echo ($session['status'] ?? '') === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                        <option value="completed" <?php echo ($session['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    </select>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <a href="sessions.php" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-save me-2"></i>Save Session
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- List View -->
                        <div class="bg-white rounded p-4 shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4 class="mb-0">All NCLEX Sessions</h4>
                                <a href="sessions.php?action=add" class="btn btn-primary btn-sm">
                                    <i class="fa fa-plus me-2"></i>Create New
                                </a>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Type</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th>Price</th>
                                            <th>Registrations</th>
                                            <th>Paid</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($sessions)): ?>
                                            <tr>
                                                <td colspan="9" class="text-center text-muted">No sessions created yet</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($sessions as $s): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($s['title']); ?></td>
                                                    <td><?php echo $s['session_type'] === '3-months' ? '3 Months' : '6 Months'; ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($s['start_date'])); ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($s['end_date'])); ?></td>
                                                    <td>$<?php echo number_format($s['price'], 2); ?></td>
                                                    <td><?php echo $s['registrations_count']; ?> / <?php echo $s['max_students']; ?></td>
                                                    <td><?php echo $s['paid_count']; ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $s['status'] === 'open' ? 'success' : 
                                                            ($s['status'] === 'full' ? 'warning' : 
                                                            ($s['status'] === 'completed' ? 'info' : 'secondary')); 
                                                        ?>">
                                                            <?php echo ucfirst($s['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="sessions.php?action=edit&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-primary">
                                                            <i class="fa fa-edit"></i>
                                                        </a>
                                                        <a href="session-registrations.php?session_id=<?php echo $s['id']; ?>" class="btn btn-sm btn-info">
                                                            <i class="fa fa-users"></i>
                                                        </a>
                                                        <a href="sessions.php?action=delete&id=<?php echo $s['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">
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



