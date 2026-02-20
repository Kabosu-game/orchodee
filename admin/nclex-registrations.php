<?php
require_once '../includes/admin_check.php';
require_once '../config/database.php';

$conn = getDBConnection();

// Ensure NCLEX tables exist (no manual script required)
$conn->query("SET FOREIGN_KEY_CHECKS = 0");
if ($conn->query("SHOW TABLES LIKE 'nclex_registrations'")->num_rows == 0) {
    $conn->query("CREATE TABLE nclex_registrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        first_name VARCHAR(255) NOT NULL,
        middle_name VARCHAR(255),
        last_name VARCHAR(255) NOT NULL,
        dob_day VARCHAR(2),
        dob_month VARCHAR(2),
        dob_year VARCHAR(4),
        phone VARCHAR(50) NOT NULL,
        email VARCHAR(255) NOT NULL,
        address_line1 VARCHAR(255) NOT NULL,
        address_line2 VARCHAR(255),
        city VARCHAR(255) NOT NULL,
        state VARCHAR(50) NOT NULL,
        zip_code VARCHAR(20) NOT NULL,
        immigration_status VARCHAR(255),
        other_immigration_status TEXT,
        elementary_school_name VARCHAR(255),
        elementary_address_line1 VARCHAR(255),
        elementary_address_line2 VARCHAR(255),
        elementary_city VARCHAR(255),
        elementary_state VARCHAR(50),
        elementary_zip_code VARCHAR(20),
        elementary_entry_date DATE,
        elementary_exit_date DATE,
        elementary_grade_from VARCHAR(50),
        elementary_grade_to VARCHAR(50),
        elementary_another_school VARCHAR(10),
        elementary_another_school_name VARCHAR(255),
        high_school_name VARCHAR(255),
        high_school_address_line1 VARCHAR(255),
        high_school_address_line2 VARCHAR(255),
        high_school_city VARCHAR(255),
        high_school_state VARCHAR(50),
        high_school_zip_code VARCHAR(20),
        high_school_entry_date DATE,
        high_school_exit_date DATE,
        high_school_grade_from VARCHAR(50),
        high_school_grade_to VARCHAR(50),
        high_school_another_school VARCHAR(10),
        high_school_another_school_name VARCHAR(255),
        university_name VARCHAR(255),
        university_address_line1 VARCHAR(255),
        university_address_line2 VARCHAR(255),
        university_city VARCHAR(255),
        university_state VARCHAR(50),
        university_zip_code VARCHAR(20),
        university_entry_date DATE,
        university_exit_date DATE,
        university_years INT,
        university_another VARCHAR(10),
        university_another_name VARCHAR(255),
        university_specialization VARCHAR(10),
        specialization VARCHAR(255),
        documents TEXT,
        registration_fee DECIMAL(10, 2) DEFAULT 5300.00,
        tax DECIMAL(10, 2) DEFAULT 0.00,
        total_amount DECIMAL(10, 2) NOT NULL,
        payment_method VARCHAR(50),
        payment_transaction_id VARCHAR(255),
        payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
        status ENUM('pending', 'reviewed', 'approved', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_email (email),
        INDEX idx_status (status),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
if ($conn->query("SHOW TABLES LIKE 'nclex_registration_courses'")->num_rows == 0) {
    $conn->query("CREATE TABLE nclex_registration_courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        registration_id INT NOT NULL,
        course_id INT NOT NULL,
        assigned_by INT,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_assignment (registration_id, course_id),
        INDEX idx_registration_id (registration_id),
        INDEX idx_course_id (course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

// getUserId() and sanitize() are defined in config/database.php

// Handle actions
$action = $_GET['action'] ?? '';
$registrationId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $id = intval($_POST['registration_id']);
        $status = sanitize($_POST['status']);
        $stmt = $conn->prepare("UPDATE nclex_registrations SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: nclex-registrations.php?success=status_updated");
        exit;
    }
    
    if (isset($_POST['assign_course'])) {
        $regId = intval($_POST['registration_id']);
        $courseId = intval($_POST['course_id']);
        $adminId = getUserId();
        
        // Create table if it doesn't exist
        $conn->query("CREATE TABLE IF NOT EXISTS nclex_registration_courses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            registration_id INT NOT NULL,
            course_id INT NOT NULL,
            assigned_by INT,
            assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (registration_id) REFERENCES nclex_registrations(id) ON DELETE CASCADE,
            FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
            UNIQUE KEY unique_assignment (registration_id, course_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // Check if already assigned
        $checkStmt = $conn->prepare("SELECT id FROM nclex_registration_courses WHERE registration_id = ? AND course_id = ?");
        $checkStmt->bind_param("ii", $regId, $courseId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO nclex_registration_courses (registration_id, course_id, assigned_by) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $regId, $courseId, $adminId);
            $stmt->execute();
            $stmt->close();
        }
        $checkStmt->close();
        
        header("Location: nclex-registrations.php?action=view&id=" . $regId . "&success=course_assigned");
        exit;
    }
    
    if (isset($_POST['remove_course'])) {
        $regId = intval($_POST['registration_id']);
        $courseId = intval($_POST['course_id']);
        
        $stmt = $conn->prepare("DELETE FROM nclex_registration_courses WHERE registration_id = ? AND course_id = ?");
        $stmt->bind_param("ii", $regId, $courseId);
        $stmt->execute();
        $stmt->close();
        
        header("Location: nclex-registrations.php?action=view&id=" . $regId . "&success=course_removed");
        exit;
    }
}

// Get all registrations with user information
$registrations = [];
$query = "SELECT nr.*, u.first_name as user_first_name, u.last_name as user_last_name, u.email as user_email 
          FROM nclex_registrations nr 
          LEFT JOIN users u ON nr.user_id = u.id 
          ORDER BY nr.created_at DESC";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $registrations[] = $row;
}

// Get single registration details
$registration = null;
$assignedCourses = [];
if ($registrationId && $action == 'view') {
    $stmt = $conn->prepare("SELECT nr.*, u.first_name as user_first_name, u.last_name as user_last_name, u.email as user_email 
                            FROM nclex_registrations nr 
                            LEFT JOIN users u ON nr.user_id = u.id 
                            WHERE nr.id = ?");
    $stmt->bind_param("i", $registrationId);
    $stmt->execute();
    $result = $stmt->get_result();
    $registration = $result->fetch_assoc();
    $stmt->close();
    
    // Get assigned courses
    $stmt = $conn->prepare("
        SELECT nrc.*, c.title, c.id as course_id 
        FROM nclex_registration_courses nrc 
        JOIN courses c ON nrc.course_id = c.id 
        WHERE nrc.registration_id = ?
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
    
    // Parse documents
    $documents = [];
    if (!empty($registration['documents'])) {
        $documents = json_decode($registration['documents'], true);
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>NCLEX Registrations - Admin - Orchidee LLC</title>
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
                            <i class="fa fa-file-alt me-2"></i>NCLEX Registrations
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
                        echo '<i class="fa fa-check-circle me-2"></i>Status updated successfully!';
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
                            <h4 class="text-primary mb-4">NCLEX Registration Details</h4>
                            
                            <!-- Personal Information -->
                            <div class="mb-4">
                                <h5 class="text-secondary mb-3">Personal Information</h5>
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <strong>Name:</strong> <?php echo htmlspecialchars($registration['first_name'] . ' ' . ($registration['middle_name'] ? $registration['middle_name'] . ' ' : '') . $registration['last_name']); ?>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Date of Birth:</strong> 
                                        <?php 
                                        if ($registration['dob_day'] && $registration['dob_month'] && $registration['dob_year']) {
                                            echo $registration['dob_day'] . '/' . $registration['dob_month'] . '/' . $registration['dob_year'];
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Phone:</strong> <?php echo htmlspecialchars($registration['phone']); ?>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>Email:</strong> <?php echo htmlspecialchars($registration['email']); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Address:</strong> 
                                        <?php 
                                        echo htmlspecialchars($registration['address_line1']);
                                        if ($registration['address_line2']) {
                                            echo ', ' . htmlspecialchars($registration['address_line2']);
                                        }
                                        echo ', ' . htmlspecialchars($registration['city']) . ', ' . htmlspecialchars($registration['state']) . ' ' . htmlspecialchars($registration['zip_code']);
                                        ?>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <strong>Immigration Status:</strong> <?php echo htmlspecialchars($registration['immigration_status']); ?>
                                        <?php if ($registration['other_immigration_status']): ?>
                                            <br><small class="text-muted">Other: <?php echo htmlspecialchars($registration['other_immigration_status']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Status:</strong> 
                                        <span class="badge bg-<?php echo $registration['status'] == 'approved' ? 'success' : ($registration['status'] == 'rejected' ? 'danger' : ($registration['status'] == 'reviewed' ? 'info' : 'warning')); ?>">
                                            <?php echo ucfirst($registration['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Educational Background -->
                            <?php if ($registration['elementary_school_name']): ?>
                            <div class="mb-4">
                                <h5 class="text-secondary mb-3">Elementary School</h5>
                                <div class="row mb-2">
                                    <div class="col-md-6"><strong>School Name:</strong> <?php echo htmlspecialchars($registration['elementary_school_name']); ?></div>
                                    <div class="col-md-6"><strong>Address:</strong> <?php echo htmlspecialchars($registration['elementary_address_line1'] . ', ' . $registration['elementary_city'] . ', ' . $registration['elementary_state'] . ' ' . $registration['elementary_zip_code']); ?></div>
                                </div>
                                <?php if ($registration['elementary_entry_date']): ?>
                                <div class="row mb-2">
                                    <div class="col-md-6"><strong>Entry Date:</strong> <?php echo date('M d, Y', strtotime($registration['elementary_entry_date'])); ?></div>
                                    <?php if ($registration['elementary_exit_date']): ?>
                                    <div class="col-md-6"><strong>Exit Date:</strong> <?php echo date('M d, Y', strtotime($registration['elementary_exit_date'])); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($registration['elementary_grade_from'] || $registration['elementary_grade_to']): ?>
                                <div class="mb-2"><strong>Grades:</strong> <?php echo htmlspecialchars($registration['elementary_grade_from'] ?? '') . ' to ' . htmlspecialchars($registration['elementary_grade_to'] ?? ''); ?></div>
                                <?php endif; ?>
                                <?php if ($registration['elementary_another_school'] == 'Yes' && $registration['elementary_another_school_name']): ?>
                                <div class="mb-2"><strong>Another School:</strong> <?php echo htmlspecialchars($registration['elementary_another_school_name']); ?></div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($registration['high_school_name']): ?>
                            <div class="mb-4">
                                <h5 class="text-secondary mb-3">High School</h5>
                                <div class="row mb-2">
                                    <div class="col-md-6"><strong>School Name:</strong> <?php echo htmlspecialchars($registration['high_school_name']); ?></div>
                                    <div class="col-md-6"><strong>Address:</strong> <?php echo htmlspecialchars($registration['high_school_address_line1'] . ', ' . $registration['high_school_city'] . ', ' . $registration['high_school_state'] . ' ' . $registration['high_school_zip_code']); ?></div>
                                </div>
                                <?php if ($registration['high_school_entry_date']): ?>
                                <div class="row mb-2">
                                    <div class="col-md-6"><strong>Entry Date:</strong> <?php echo date('M d, Y', strtotime($registration['high_school_entry_date'])); ?></div>
                                    <?php if ($registration['high_school_exit_date']): ?>
                                    <div class="col-md-6"><strong>Exit Date:</strong> <?php echo date('M d, Y', strtotime($registration['high_school_exit_date'])); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($registration['high_school_grade_from'] || $registration['high_school_grade_to']): ?>
                                <div class="mb-2"><strong>Grades:</strong> <?php echo htmlspecialchars($registration['high_school_grade_from'] ?? '') . ' to ' . htmlspecialchars($registration['high_school_grade_to'] ?? ''); ?></div>
                                <?php endif; ?>
                                <?php if ($registration['high_school_another_school'] == 'Yes' && $registration['high_school_another_school_name']): ?>
                                <div class="mb-2"><strong>Another School:</strong> <?php echo htmlspecialchars($registration['high_school_another_school_name']); ?></div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($registration['university_name']): ?>
                            <div class="mb-4">
                                <h5 class="text-secondary mb-3">University</h5>
                                <div class="row mb-2">
                                    <div class="col-md-6"><strong>University Name:</strong> <?php echo htmlspecialchars($registration['university_name']); ?></div>
                                    <div class="col-md-6"><strong>Address:</strong> <?php echo htmlspecialchars($registration['university_address_line1'] . ', ' . $registration['university_city'] . ', ' . $registration['university_state'] . ' ' . $registration['university_zip_code']); ?></div>
                                </div>
                                <?php if ($registration['university_entry_date']): ?>
                                <div class="row mb-2">
                                    <div class="col-md-6"><strong>Entry Date:</strong> <?php echo date('M d, Y', strtotime($registration['university_entry_date'])); ?></div>
                                    <?php if ($registration['university_exit_date']): ?>
                                    <div class="col-md-6"><strong>Exit Date:</strong> <?php echo date('M d, Y', strtotime($registration['university_exit_date'])); ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                                <?php if ($registration['university_years']): ?>
                                <div class="mb-2"><strong>Number of Years:</strong> <?php echo $registration['university_years']; ?></div>
                                <?php endif; ?>
                                <?php if ($registration['university_another'] == 'Yes' && $registration['university_another_name']): ?>
                                <div class="mb-2"><strong>Another University:</strong> <?php echo htmlspecialchars($registration['university_another_name']); ?></div>
                                <?php endif; ?>
                                <?php if ($registration['university_specialization'] == 'Yes' && $registration['specialization']): ?>
                                <div class="mb-2"><strong>Specialization:</strong> <?php echo htmlspecialchars($registration['specialization']); ?></div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Documents -->
                            <?php if (!empty($documents)): ?>
                            <div class="mb-4">
                                <h5 class="text-secondary mb-3">Uploaded Documents</h5>
                                <div class="list-group">
                                    <?php foreach ($documents as $doc): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <i class="fa fa-file me-2"></i>
                                                    <strong><?php echo htmlspecialchars($doc['original_name']); ?></strong>
                                                    <small class="text-muted ms-2">(<?php echo number_format($doc['size'] / 1024, 2); ?> KB)</small>
                                                </div>
                                                <a href="../<?php echo htmlspecialchars($doc['path']); ?>" target="_blank" class="btn btn-sm btn-primary">
                                                    <i class="fa fa-download me-1"></i>Download
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Update Status -->
                            <form method="POST" class="mb-4">
                                <input type="hidden" name="registration_id" value="<?php echo $registration['id']; ?>">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label"><strong>Update Status:</strong></label>
                                        <select name="status" class="form-select">
                                            <option value="pending" <?php echo $registration['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="reviewed" <?php echo $registration['status'] == 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                            <option value="approved" <?php echo $registration['status'] == 'approved' ? 'selected' : ''; ?>>Approved</option>
                                            <option value="rejected" <?php echo $registration['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 d-flex align-items-end">
                                        <button type="submit" name="update_status" class="btn btn-primary">
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
                                <a href="nclex-registrations.php" class="btn btn-outline-secondary">
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
                                            <th>Status</th>
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
                                                        <?php if ($reg['user_id']): ?>
                                                            <?php if ($reg['user_first_name']): ?>
                                                                <span class="badge bg-success">
                                                                    <i class="fa fa-user me-1"></i>
                                                                    <?php echo htmlspecialchars($reg['user_first_name'] . ' ' . $reg['user_last_name']); ?>
                                                                </span>
                                                                <br><small class="text-muted"><?php echo htmlspecialchars($reg['user_email']); ?></small>
                                                            <?php else: ?>
                                                                <span class="badge bg-info">User ID: <?php echo $reg['user_id']; ?></span>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning">No account</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($reg['phone']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $reg['status'] == 'approved' ? 'success' : ($reg['status'] == 'rejected' ? 'danger' : ($reg['status'] == 'reviewed' ? 'info' : 'warning')); ?>">
                                                            <?php echo ucfirst($reg['status']); ?>
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
                                                <td colspan="8" class="text-center text-muted">No registrations found.</td>
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
