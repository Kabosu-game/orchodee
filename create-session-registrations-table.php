<?php
require_once 'config/database.php';

$conn = getDBConnection();

// Disable foreign key checks temporarily
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

$errors = [];
$success = [];

// Check if users table exists
$usersTableCheck = $conn->query("SHOW TABLES LIKE 'users'");
if ($usersTableCheck->num_rows == 0) {
    // Create minimal users table if it doesn't exist
    $usersSql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(255),
        last_name VARCHAR(255),
        email VARCHAR(255) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($usersSql)) {
        $success[] = "Table 'users' created successfully!";
    } else {
        $errors[] = "Error creating table 'users': " . $conn->error;
    }
}

// Create session_registrations table
$sql = "CREATE TABLE IF NOT EXISTS session_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    nursing_school VARCHAR(255) NOT NULL,
    years_attended VARCHAR(50) NOT NULL,
    session_duration VARCHAR(50) NOT NULL,
    credentials_started ENUM('Yes', 'No') NOT NULL,
    motivation TEXT,
    comments TEXT,
    registration_fee DECIMAL(10, 2) DEFAULT 50.00,
    tax DECIMAL(10, 2) DEFAULT 3.99,
    total_amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    payment_transaction_id VARCHAR(255),
    payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_email (email),
    INDEX idx_payment_status (payment_status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql)) {
    $success[] = "Table 'session_registrations' created/verified successfully!";
} else {
    $errors[] = "Error creating table 'session_registrations': " . $conn->error;
}

// Check if user_id column exists, if not add it
$columnCheck = $conn->query("SHOW COLUMNS FROM session_registrations LIKE 'user_id'");
if ($columnCheck->num_rows == 0) {
    $alterSql = "ALTER TABLE session_registrations ADD COLUMN user_id INT AFTER id, ADD INDEX idx_user_id (user_id)";
    if ($conn->query($alterSql)) {
        $success[] = "Column 'user_id' added to existing table 'session_registrations'!";
    } else {
        $errors[] = "Error adding column 'user_id': " . $conn->error;
    }
}

// Check if courses table exists, if not create a minimal version
$coursesTableCheck = $conn->query("SHOW TABLES LIKE 'courses'");
if ($coursesTableCheck->num_rows == 0) {
    $coursesSql = "CREATE TABLE IF NOT EXISTS courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        status ENUM('draft', 'published') DEFAULT 'published',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($coursesSql)) {
        $success[] = "Table 'courses' created successfully!";
    } else {
        $errors[] = "Error creating table 'courses': " . $conn->error;
    }
}

// Create session_registration_courses table (for course assignments) — always create
$sql2 = "CREATE TABLE IF NOT EXISTS session_registration_courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    registration_id INT NOT NULL,
    course_id INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT,
    UNIQUE KEY unique_registration_course (registration_id, course_id),
    INDEX idx_registration_id (registration_id),
    INDEX idx_course_id (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql2)) {
    $success[] = "Table 'session_registration_courses' created successfully!";
    
    // Add foreign keys only if referenced tables exist
    $hasSessionReg = $conn->query("SHOW TABLES LIKE 'session_registrations'")->num_rows > 0;
    $hasCourses = $conn->query("SHOW TABLES LIKE 'courses'")->num_rows > 0;
    
    if ($hasSessionReg && $hasCourses) {
        try {
            $fkCheck = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'session_registration_courses' 
                AND CONSTRAINT_TYPE = 'FOREIGN KEY'");
            
            if ($fkCheck->num_rows == 0) {
                $conn->query("ALTER TABLE session_registration_courses 
                    ADD CONSTRAINT fk_registration 
                    FOREIGN KEY (registration_id) REFERENCES session_registrations(id) ON DELETE CASCADE");
                $conn->query("ALTER TABLE session_registration_courses 
                    ADD CONSTRAINT fk_course 
                    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE");
                $success[] = "Foreign key constraints (session_registration_courses) added successfully!";
            }
        } catch (Exception $e) {
            $errors[] = "Note: Foreign key constraints could not be added: " . $e->getMessage();
        }
    }
} else {
    $errors[] = "Error creating table 'session_registration_courses': " . $conn->error;
}

// Add foreign key for user_id if users table exists
$usersTableCheck2 = $conn->query("SHOW TABLES LIKE 'users'");
if ($usersTableCheck2->num_rows > 0) {
    try {
        // Check if foreign key already exists
        $fkCheck = $conn->query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'session_registrations' 
            AND CONSTRAINT_NAME = 'fk_user'");
        
        if ($fkCheck->num_rows == 0) {
            // Add foreign key for user_id
            $conn->query("ALTER TABLE session_registrations 
                ADD CONSTRAINT fk_user 
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL");
            
            $success[] = "Foreign key constraint for user_id added successfully!";
        }
    } catch (Exception $e) {
        $errors[] = "Note: Foreign key constraint for user_id could not be added: " . $e->getMessage();
    }
}

// Re-enable foreign key checks
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Create Tables - Orchidee LLC</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"/>
</head>
<body>
    <div class="container py-5">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="bg-white rounded shadow-sm p-5">
                    <h2 class="text-primary mb-4">
                        <i class="fa fa-database me-2"></i>Database Tables Creation
                    </h2>
                    
                    <?php if (count($success) > 0): ?>
                        <div class="alert alert-success">
                            <h5><i class="fa fa-check-circle me-2"></i>Success:</h5>
                            <ul class="mb-0">
                                <?php foreach ($success as $msg): ?>
                                    <li><?php echo htmlspecialchars($msg); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (count($errors) > 0): ?>
                        <div class="alert alert-danger">
                            <h5><i class="fa fa-exclamation-circle me-2"></i>Errors:</h5>
                            <ul class="mb-0">
                                <?php foreach ($errors as $msg): ?>
                                    <li><?php echo htmlspecialchars($msg); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mt-4">
                        <a href="registration-next-session.php" class="btn btn-primary">
                            <i class="fa fa-arrow-right me-2"></i>Go to Registration Form
                        </a>
                        <a href="admin/session-registrations.php" class="btn btn-outline-primary">
                            <i class="fa fa-cog me-2"></i>Admin Panel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
