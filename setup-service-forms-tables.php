<?php
/**
 * Setup script to create all tables needed for service forms system
 * Run this file once to initialize the database tables
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database configuration
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'orchidee_courses';

// Try to get database config from config file if it exists
if (file_exists('config/database.php')) {
    require_once 'config/database.php';
    if (function_exists('getDBConnection')) {
        try {
            $conn = getDBConnection();
        } catch (Exception $e) {
            // Fallback to direct connection
            $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }
        }
    } else {
        $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
    }
} else {
    // Direct connection
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
}

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>Setup Service Forms Tables</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0; }
        .info { color: #004085; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 5px; margin: 10px 0; }
        h1 { color: #007bff; }
    </style>
</head>
<body>
    <h1>Setup Service Forms Tables</h1>";

try {
    // First, check if services table exists, create it if not
    $checkServices = $conn->query("SHOW TABLES LIKE 'services'");
    if ($checkServices->num_rows === 0) {
        $sqlServices = "CREATE TABLE IF NOT EXISTS services (
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
        
        if ($conn->query($sqlServices)) {
            echo "<div class='success'>✓ Table 'services' created successfully</div>";
        } else {
            echo "<div class='error'>✗ Error creating services: " . $conn->error . "</div>";
        }
    } else {
        echo "<div class='info'>ℹ Table 'services' already exists</div>";
    }
    
    // Create service_forms table
    $sql1 = "CREATE TABLE IF NOT EXISTS service_forms (
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
    
    if ($conn->query($sql1)) {
        echo "<div class='success'>✓ Table 'service_forms' created successfully</div>";
    } else {
        echo "<div class='error'>✗ Error creating service_forms: " . $conn->error . "</div>";
    }
    
    // Create form_fields table
    $sql2 = "CREATE TABLE IF NOT EXISTS form_fields (
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
    
    if ($conn->query($sql2)) {
        echo "<div class='success'>✓ Table 'form_fields' created successfully</div>";
    } else {
        echo "<div class='error'>✗ Error creating form_fields: " . $conn->error . "</div>";
    }
    
    // Create service_requests table
    $sql3 = "CREATE TABLE IF NOT EXISTS service_requests (
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
    
    if ($conn->query($sql3)) {
        echo "<div class='success'>✓ Table 'service_requests' created successfully</div>";
    } else {
        echo "<div class='error'>✗ Error creating service_requests: " . $conn->error . "</div>";
    }
    
    // Check if testimonials table exists, create if not
    $testimonialsCheck = $conn->query("SHOW TABLES LIKE 'testimonials'");
    if ($testimonialsCheck->num_rows === 0) {
        $sql4 = "CREATE TABLE IF NOT EXISTS testimonials (
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
        
        if ($conn->query($sql4)) {
            echo "<div class='success'>✓ Table 'testimonials' created successfully</div>";
        } else {
            echo "<div class='error'>✗ Error creating testimonials: " . $conn->error . "</div>";
        }
    } else {
        echo "<div class='info'>ℹ Table 'testimonials' already exists</div>";
    }
    
    // Verify tables exist
    echo "<hr><h2>Verification</h2>";
    $tables = ['service_forms', 'form_fields', 'service_requests', 'testimonials'];
    $allCreated = true;
    
    foreach ($tables as $table) {
        $check = $conn->query("SHOW TABLES LIKE '$table'");
        if ($check->num_rows > 0) {
            echo "<div class='success'>✓ Table '$table' exists</div>";
        } else {
            echo "<div class='error'>✗ Table '$table' does NOT exist</div>";
            $allCreated = false;
        }
    }
    
    if ($allCreated) {
        echo "<div class='success' style='margin-top: 20px; font-size: 1.2em; font-weight: bold;'>
            ✓ All tables have been created successfully!<br>
            You can now use the service forms system.
        </div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>✗ Error: " . $e->getMessage() . "</div>";
}

$conn->close();
echo "</body>
</html>";
?>

