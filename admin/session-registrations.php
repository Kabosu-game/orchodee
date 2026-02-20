<?php
require_once '../includes/admin_check.php';
require_once '../config/database.php';

$conn = getDBConnection();

// Handle actions
$action = $_GET['action'] ?? '';
$registrationId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_payment_status'])) {
        $id = intval($_POST['registration_id']);
        $status = sanitize($_POST['payment_status']);
        $stmt = $conn->prepare("UPDATE session_registrations SET payment_status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: session-registrations.php?success=status_updated");
        exit;
    }
    
    if (isset($_POST['assign_course'])) {
        $regId = intval($_POST['registration_id']);
        $courseId = intval($_POST['course_id']);
        $adminId = getUserId();
        
        // Check if already assigned
        $checkStmt = $conn->prepare("SELECT id FROM session_registration_courses WHERE registration_id = ? AND course_id = ?");
        $checkStmt->bind_param("ii", $regId, $courseId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO session_registration_courses (registration_id, course_id, assigned_by) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $regId, $courseId, $adminId);
            $stmt->execute();
            $stmt->close();
        }
        $checkStmt->close();
        
        header("Location: session-registrations.php?action=view&id=" . $regId . "&success=course_assigned");
        exit;
    }
    
    if (isset($_POST['remove_course'])) {
        $regId = intval($_POST['registration_id']);
        $courseId = intval($_POST['course_id']);
        
        $stmt = $conn->prepare("DELETE FROM session_registration_courses WHERE registration_id = ? AND course_id = ?");
        $stmt->bind_param("ii", $regId, $courseId);
        $stmt->execute();
        $stmt->close();
        
        header("Location: session-registrations.php?action=view&id=" . $regId . "&success=course_removed");
        exit;
    }
}

// Get all registrations (use session_registrations columns; user_id join optional if column exists)
$registrations = [];
$query = "SELECT sr.*, sr.first_name as user_first_name, sr.last_name as user_last_name, sr.email as user_email 
          FROM session_registrations sr 
          ORDER BY sr.created_at DESC";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $registrations[] = $row;
}

// Get single registration details
$registration = null;
$assignedCourses = [];
if ($registrationId && $action == 'view') {
    $stmt = $conn->prepare("SELECT sr.*, sr.first_name as user_first_name, sr.last_name as user_last_name, sr.email as user_email 
                            FROM session_registrations sr 
                            WHERE sr.id = ?");
    $stmt->bind_param("i", $registrationId);
    $stmt->execute();
    $result = $stmt->get_result();
    $registration = $result->fetch_assoc();
    $stmt->close();
    
    // Get assigned courses
    $stmt = $conn->prepare("
        SELECT src.*, c.title, c.id as course_id 
        FROM session_registration_courses src 
        JOIN courses c ON src.course_id = c.id 
        WHERE src.registration_id = ?
    ");
    $stmt->bind_param("i", $registrationId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $assignedCourses[] = $row;
    }
    $stmt->close();
    
    // Get all available courses
    $allCourses = [];
    $coursesResult = $conn->query("SELECT id, title FROM courses WHERE status = 'published' ORDER BY title");
    while ($row = $coursesResult->fetch_assoc()) {
        $allCourses[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Session Registrations - Admin - Orchidee LLC</title>
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
                            <i class="fa fa-user-plus me-2"></i>Session Registrations
                        </h2>
                        <div>
                            <a href="dashboard.php" class="btn btn-outline-secondary me-2">
                                <i class="fa fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php
                    if ($_GET['success'] == 'status_updated') {
                        echo '<i class="fa fa-check-circle me-2"></i>Payment status updated successfully!';
                    } elseif ($_GET['success'] == 'course_assigned') {
                        echo '<i class="fa fa-check-circle me-2"></i>Course assigned successfully!';
                    } elseif ($_GET['success'] == 'course_removed') {
                        echo '<i class="fa fa-check-circle me-2"></i>Course removed successfully!';
                    }
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($action == 'view' && $registration): ?>
                <!-- View Single Registration -->
                <div class="row">
                    <div class="col-12">
                        <div class="bg-white rounded shadow-sm p-4 mb-4">
                            <h4 class="text-primary mb-4">Registration Details</h4>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Name:</strong> <?php echo htmlspecialchars($registration['first_name'] . ' ' . $registration['last_name']); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Email:</strong> <?php echo htmlspecialchars($registration['email']); ?>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Phone:</strong> <?php echo htmlspecialchars($registration['phone']); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Nursing School:</strong> <?php echo htmlspecialchars($registration['nursing_school']); ?>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Years Attended:</strong> <?php echo htmlspecialchars($registration['years_attended']); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Session Duration:</strong> <?php echo htmlspecialchars($registration['session_duration']); ?>
                                </div>
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Credentials Started:</strong> <?php echo htmlspecialchars($registration['credentials_started']); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Payment Status:</strong> 
                                    <span class="badge bg-<?php echo $registration['payment_status'] == 'completed' ? 'success' : ($registration['payment_status'] == 'pending' ? 'warning' : 'danger'); ?>">
                                        <?php echo ucfirst($registration['payment_status']); ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if ($registration['motivation']): ?>
                                <div class="mb-3">
                                    <strong>Motivation:</strong>
                                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($registration['motivation'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($registration['comments']): ?>
                                <div class="mb-3">
                                    <strong>Comments:</strong>
                                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($registration['comments'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <strong>Registration Fee:</strong> $<?php echo number_format($registration['registration_fee'], 2); ?>
                                </div>
                                <div class="col-md-4">
                                    <strong>Tax:</strong> $<?php echo number_format($registration['tax'], 2); ?>
                                </div>
                                <div class="col-md-4">
                                    <strong>Total:</strong> $<?php echo number_format($registration['total_amount'], 2); ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Payment Method:</strong> <?php echo ucfirst(str_replace('_', ' ', $registration['payment_method'])); ?>
                                <?php if ($registration['payment_transaction_id']): ?>
                                    <br><strong>Transaction ID:</strong> <?php echo htmlspecialchars($registration['payment_transaction_id']); ?>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Update Payment Status -->
                            <form method="POST" class="mb-4">
                                <input type="hidden" name="registration_id" value="<?php echo $registration['id']; ?>">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label"><strong>Update Payment Status:</strong></label>
                                        <select name="payment_status" class="form-select">
                                            <option value="pending" <?php echo $registration['payment_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="completed" <?php echo $registration['payment_status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="failed" <?php echo $registration['payment_status'] == 'failed' ? 'selected' : ''; ?>>Failed</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 d-flex align-items-end">
                                        <button type="submit" name="update_payment_status" class="btn btn-primary">
                                            <i class="fa fa-save me-2"></i>Update Status
                                        </button>
                                    </div>
                                </div>
                            </form>
                            
                            <!-- Assigned Courses -->
                            <div class="mb-4">
                                <h5 class="text-primary mb-3">Assigned Courses</h5>
                                <?php if (count($assignedCourses) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Course Title</th>
                                                    <th>Assigned At</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($assignedCourses as $course): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($course['title']); ?></td>
                                                        <td><?php echo date('M d, Y g:i A', strtotime($course['assigned_at'])); ?></td>
                                                        <td>
                                                            <form method="POST" style="display: inline;">
                                                                <input type="hidden" name="registration_id" value="<?php echo $registration['id']; ?>">
                                                                <input type="hidden" name="course_id" value="<?php echo $course['course_id']; ?>">
                                                                <button type="submit" name="remove_course" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to remove this course?');">
                                                                    <i class="fa fa-trash me-1"></i>Remove
                                                                </button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted">No courses assigned yet.</p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Assign New Course -->
                            <div>
                                <h5 class="text-primary mb-3">Assign Course</h5>
                                <form method="POST">
                                    <input type="hidden" name="registration_id" value="<?php echo $registration['id']; ?>">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <select name="course_id" class="form-select" required>
                                                <option value="">-- Select Course --</option>
                                                <?php foreach ($allCourses as $course): ?>
                                                    <?php
                                                    $isAssigned = false;
                                                    foreach ($assignedCourses as $assigned) {
                                                        if ($assigned['course_id'] == $course['id']) {
                                                            $isAssigned = true;
                                                            break;
                                                        }
                                                    }
                                                    ?>
                                                    <?php if (!$isAssigned): ?>
                                                        <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <button type="submit" name="assign_course" class="btn btn-primary w-100">
                                                <i class="fa fa-plus me-2"></i>Assign Course
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            
                            <div class="mt-4">
                                <a href="session-registrations.php" class="btn btn-outline-secondary">
                                    <i class="fa fa-arrow-left me-2"></i>Back to List
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- List All Registrations -->
                <div class="row">
                    <div class="col-12">
                        <div class="bg-white rounded shadow-sm p-4">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>User Account</th>
                                            <th>Phone</th>
                                            <th>Session</th>
                                            <th>Amount</th>
                                            <th>Payment Status</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($registrations) > 0): ?>
                                            <?php foreach ($registrations as $reg): ?>
                                                <tr>
                                                    <td><?php echo $reg['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($reg['first_name'] . ' ' . $reg['last_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($reg['email']); ?></td>
                                                    <td>
                                                        <?php $linkedUserId = $reg['user_id'] ?? null; ?>
                                                        <?php if (!empty($linkedUserId)): ?>
                                                            <span class="badge bg-success">
                                                                <i class="fa fa-user me-1"></i>
                                                                Linked account (ID: <?php echo (int) $linkedUserId; ?>)
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning">No account</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($reg['phone']); ?></td>
                                                    <td><?php echo htmlspecialchars($reg['session_duration']); ?></td>
                                                    <td>$<?php echo number_format($reg['total_amount'], 2); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $reg['payment_status'] == 'completed' ? 'success' : ($reg['payment_status'] == 'pending' ? 'warning' : 'danger'); ?>">
                                                            <?php echo ucfirst($reg['payment_status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('M d, Y', strtotime($reg['created_at'])); ?></td>
                                                    <td>
                                                        <a href="?action=view&id=<?php echo $reg['id']; ?>" class="btn btn-sm btn-primary">
                                                            <i class="fa fa-eye me-1"></i>View
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="9" class="text-center text-muted">No registrations found.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <!-- Admin Content End -->

    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>

    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/main.js"></script>
</body>
</html>
