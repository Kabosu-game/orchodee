<?php
// Script to automatically create the database and all tables
error_reporting(E_ALL);
ini_set('display_errors', 1);

$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'orchidee_courses';

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Database Installation</title>";
echo "<style>body{font-family:Arial,sans-serif;padding:20px;background:#f5f5f5;}";
echo ".container{max-width:800px;margin:0 auto;background:white;padding:30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}";
echo "h1{color:#007bff;} .success{color:green;} .error{color:red;} .info{color:#666;margin:10px 0;}";
echo "ul{list-style:none;padding:0;} li{padding:5px 0;border-bottom:1px solid #eee;}";
echo ".btn{display:inline-block;padding:12px 30px;background:#007bff;color:white;text-decoration:none;border-radius:5px;margin-top:20px;}";
echo "</style></head><body><div class='container'>";
echo "<h1>🔧 Database Installation</h1>";

try {
    // Step 1: Connect to MySQL
    echo "<h2>Step 1: Connect to MySQL</h2>";
    $conn = new mysqli($dbHost, $dbUser, $dbPass);
    
    if ($conn->connect_error) {
        throw new Exception("Connection error: " . $conn->connect_error);
    }
    echo "<p class='success'>✓ MySQL connection successful</p>";
    
    // Step 2: Create the database
    echo "<h2>Step 2: Create Database</h2>";
    $sql = "CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
    if ($conn->query($sql)) {
        echo "<p class='success'>✓ Database '$dbName' created</p>";
    } else {
        throw new Exception("Error: " . $conn->error);
    }
    
    // Select the database
    $conn->select_db($dbName);
    echo "<p class='success'>✓ Database selected</p>";
    
    // Step 3: Create tables
    echo "<h2>Step 3: Create Tables</h2>";
    
    // Table users
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(255) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        role ENUM('user', 'admin') DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->query($sql);
    echo "<p class='success'>✓ Table 'users' created</p>";
    
    // Table course_categories
    $sql = "CREATE TABLE IF NOT EXISTS course_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->query($sql);
    echo "<p class='success'>✓ Table 'course_categories' created</p>";
    
    // Table courses
    $sql = "CREATE TABLE IF NOT EXISTS courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        short_description VARCHAR(500),
        price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
        image VARCHAR(255),
        category_id INT,
        instructor_name VARCHAR(100),
        duration_hours INT DEFAULT 0,
        level ENUM('beginner', 'intermediate', 'expert') DEFAULT 'intermediate',
        status ENUM('draft', 'published') DEFAULT 'draft',
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES course_categories(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->query($sql);
    echo "<p class='success'>✓ Table 'courses' created</p>";
    
    // Table chapters
    $sql = "CREATE TABLE IF NOT EXISTS chapters (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        order_number INT NOT NULL DEFAULT 0,
        is_locked BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->query($sql);
    echo "<p class='success'>✓ Table 'chapters' created</p>";
    
    // Table lessons
    $sql = "CREATE TABLE IF NOT EXISTS lessons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        chapter_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        video_url VARCHAR(500),
        video_type ENUM('youtube', 'vimeo', 'upload', 'external') DEFAULT 'upload',
        duration_minutes INT DEFAULT 0,
        order_number INT NOT NULL DEFAULT 0,
        is_locked BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->query($sql);
    echo "<p class='success'>✓ Table 'lessons' created</p>";
    
    // Table resources
    $sql = "CREATE TABLE IF NOT EXISTS resources (
        id INT AUTO_INCREMENT PRIMARY KEY,
        lesson_id INT,
        chapter_id INT,
        course_id INT,
        title VARCHAR(255) NOT NULL,
        file_url VARCHAR(500) NOT NULL,
        file_type ENUM('pdf', 'doc', 'docx', 'ppt', 'pptx', 'other') DEFAULT 'pdf',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
        FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->query($sql);
    echo "<p class='success'>✓ Table 'resources' created</p>";
    
    // Table purchases
    $sql = "CREATE TABLE IF NOT EXISTS purchases (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        course_id INT NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        payment_method VARCHAR(50),
        payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
        purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
        UNIQUE KEY unique_purchase (user_id, course_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->query($sql);
    echo "<p class='success'>✓ Table 'purchases' created</p>";
    
    // Table user_progress
    $sql = "CREATE TABLE IF NOT EXISTS user_progress (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        course_id INT NOT NULL,
        chapter_id INT,
        lesson_id INT,
        completed BOOLEAN DEFAULT FALSE,
        progress_percentage DECIMAL(5, 2) DEFAULT 0.00,
        last_accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
        FOREIGN KEY (chapter_id) REFERENCES chapters(id) ON DELETE CASCADE,
        FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->query($sql);
    echo "<p class='success'>✓ Table 'user_progress' created</p>";
    
    // Table services
    $sql = "CREATE TABLE IF NOT EXISTS services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        image VARCHAR(255) DEFAULT NULL,
        icon VARCHAR(100) DEFAULT NULL,
        price DECIMAL(10, 2) DEFAULT NULL,
        display_order INT DEFAULT 0,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY status (status),
        KEY display_order (display_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->query($sql);
    echo "<p class='success'>✓ Table 'services' created</p>";
    
    // Table service_forms
    $sql = "CREATE TABLE IF NOT EXISTS service_forms (
        id INT AUTO_INCREMENT PRIMARY KEY,
        service_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
        KEY service_id (service_id),
        KEY is_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->query($sql);
    echo "<p class='success'>✓ Table 'service_forms' created</p>";
    
    // Table form_fields
    $sql = "CREATE TABLE IF NOT EXISTS form_fields (
        id INT AUTO_INCREMENT PRIMARY KEY,
        form_id INT NOT NULL,
        field_type ENUM('text', 'email', 'textarea', 'select', 'radio', 'checkbox', 'date', 'file', 'number') NOT NULL,
        field_label VARCHAR(255) NOT NULL,
        field_name VARCHAR(100) NOT NULL,
        field_options TEXT,
        is_required BOOLEAN DEFAULT FALSE,
        display_order INT DEFAULT 0,
        placeholder VARCHAR(255),
        validation_rules TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (form_id) REFERENCES service_forms(id) ON DELETE CASCADE,
        KEY form_id (form_id),
        KEY display_order (display_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->query($sql);
    echo "<p class='success'>✓ Table 'form_fields' created</p>";
    
    // Table service_requests
    $sql = "CREATE TABLE IF NOT EXISTS service_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        service_id INT NOT NULL,
        form_id INT NOT NULL,
        user_id INT,
        form_data TEXT NOT NULL,
        payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
        payment_method VARCHAR(50),
        payment_transaction_id VARCHAR(255),
        payment_amount DECIMAL(10, 2),
        request_status ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
        admin_notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
        FOREIGN KEY (form_id) REFERENCES service_forms(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        KEY service_id (service_id),
        KEY form_id (form_id),
        KEY user_id (user_id),
        KEY payment_status (payment_status),
        KEY request_status (request_status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->query($sql);
    echo "<p class='success'>✓ Table 'service_requests' created</p>";
    
    // Table testimonials
    $sql = "CREATE TABLE IF NOT EXISTS testimonials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        comment TEXT NOT NULL,
        rating INT DEFAULT 5,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY status (status),
        KEY created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $conn->query($sql);
    echo "<p class='success'>✓ Table 'testimonials' created</p>";
    
    // Step 4: Insert initial data
    echo "<h2>Step 4: Insert Initial Data</h2>";
    
    // Check if admin exists
    $result = $conn->query("SELECT id FROM users WHERE email = 'admin@orchideellc.com'");
    if ($result->num_rows == 0) {
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, role) VALUES (?, ?, ?, ?, 'admin')");
        $firstName = 'Admin';
        $lastName = 'Orchidee';
        $email = 'admin@orchideellc.com';
        $stmt->bind_param("ssss", $firstName, $lastName, $email, $hashedPassword);
        if ($stmt->execute()) {
            echo "<p class='success'>✓ Admin account created</p>";
        }
        $stmt->close();
    } else {
        echo "<p class='info'>ℹ Admin account already exists</p>";
    }
    
    // Insert categories
    $categories = [
        ['NCLEX-RN Review', 'Comprehensive review courses for NCLEX-RN examination'],
        ['NCLEX-PN Review', 'Comprehensive review courses for NCLEX-PN examination'],
        ['Credential Evaluation', 'Guidance on credential evaluation process'],
        ['License Endorsement', 'Support for license endorsement process'],
        ['Resume Writing', 'Professional resume writing for nurses'],
        ['Interview Preparation', 'NCLEX and job interview preparation']
    ];
    
    foreach ($categories as $cat) {
        $result = $conn->query("SELECT id FROM course_categories WHERE name = '" . $conn->real_escape_string($cat[0]) . "'");
        if ($result->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO course_categories (name, description) VALUES (?, ?)");
            $stmt->bind_param("ss", $cat[0], $cat[1]);
            $stmt->execute();
            $stmt->close();
        }
    }
    echo "<p class='success'>✓ Course categories created</p>";
    
    // Step 5: Verification
    echo "<h2>Step 5: Verification</h2>";
    $tables = ['users', 'course_categories', 'courses', 'chapters', 'lessons', 'resources', 'purchases', 'user_progress', 'services', 'service_forms', 'form_fields', 'service_requests', 'testimonials'];
    echo "<ul>";
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            $count = $conn->query("SELECT COUNT(*) as cnt FROM $table")->fetch_assoc()['cnt'];
            echo "<li class='success'>✓ Table '$table' exists ($count records)</li>";
        } else {
            echo "<li class='error'>✗ Table '$table' missing</li>";
        }
    }
    echo "</ul>";
    
    // Check admin
    $result = $conn->query("SELECT * FROM users WHERE role = 'admin'");
    if ($result && $result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        echo "<h3>Admin Account:</h3>";
        echo "<p><strong>Email:</strong> " . htmlspecialchars($admin['email']) . "</p>";
        echo "<p><strong>Password:</strong> admin123</p>";
        echo "<p class='error'><strong>⚠️ CHANGE THIS PASSWORD AFTER FIRST LOGIN!</strong></p>";
    }
    
    $conn->close();
    
    echo "<hr>";
    echo "<h1 class='success'>✅ Installation completed successfully!</h1>";
    echo "<p class='info'>The database and all tables have been created.</p>";
    echo "<p><a href='login.php' class='btn'>Go to Login Page</a></p>";
    echo "<p><a href='index.html' class='btn' style='background:#6c757d;'>Back to Home</a></p>";
    
} catch (Exception $e) {
    echo "<h2 class='error'>❌ Error</h2>";
    echo "<p class='error'>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p class='info'>Please check that MySQL is running and that the credentials are correct in config/database.php</p>";
}

echo "</div></body></html>";
?>

