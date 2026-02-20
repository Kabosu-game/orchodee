<?php
/**
 * Setup script to create all tables for service forms system
 * Run this file once to create all necessary tables
 */

require_once 'config/database.php';

$conn = getDBConnection();

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Creating Service Forms System Tables</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
    .success { color: #28a745; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; margin: 5px 0; border-radius: 5px; }
    .error { color: #dc3545; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 5px 0; border-radius: 5px; }
    .info { color: #0c5460; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; margin: 5px 0; border-radius: 5px; }
</style>";

$tables = [
    [
        'name' => 'service_forms',
        'sql' => "CREATE TABLE IF NOT EXISTS service_forms (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ],
    [
        'name' => 'form_fields',
        'sql' => "CREATE TABLE IF NOT EXISTS form_fields (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ],
    [
        'name' => 'service_requests',
        'sql' => "CREATE TABLE IF NOT EXISTS service_requests (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ]
];

// First, make sure services table exists
echo "<div class='info'>Step 1: Checking services table...</div>";
$checkServices = $conn->query("SHOW TABLES LIKE 'services'");
if ($checkServices->num_rows === 0) {
    echo "<div class='info'>Creating services table first...</div>";
    $createServicesSQL = "CREATE TABLE IF NOT EXISTS services (
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
    
    if ($conn->query($createServicesSQL)) {
        echo "<div class='success'>✓ Services table created successfully</div>";
    } else {
        echo "<div class='error'>✗ Error creating services table: " . $conn->error . "</div>";
    }
} else {
    echo "<div class='success'>✓ Services table already exists</div>";
}

// Create users table if it doesn't exist (needed for foreign key)
echo "<div class='info'>Step 2: Checking users table...</div>";
$checkUsers = $conn->query("SHOW TABLES LIKE 'users'");
if ($checkUsers->num_rows === 0) {
    echo "<div class='info'>Creating users table (basic structure)...</div>";
    $createUsersSQL = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('user', 'admin') DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($createUsersSQL)) {
        echo "<div class='success'>✓ Users table created successfully</div>";
    } else {
        echo "<div class='error'>✗ Error creating users table: " . $conn->error . "</div>";
    }
} else {
    echo "<div class='success'>✓ Users table already exists</div>";
}

// Now create all service forms tables
echo "<div class='info'>Step 3: Creating service forms tables...</div>";

foreach ($tables as $table) {
    // Check if table exists
    $checkTable = $conn->query("SHOW TABLES LIKE '{$table['name']}'");
    
    if ($checkTable->num_rows > 0) {
        echo "<div class='info'>Table '{$table['name']}' already exists. Dropping and recreating...</div>";
        $conn->query("DROP TABLE IF EXISTS {$table['name']}");
    }
    
    if ($conn->query($table['sql'])) {
        echo "<div class='success'>✓ Table '{$table['name']}' created successfully</div>";
    } else {
        echo "<div class='error'>✗ Error creating table '{$table['name']}': " . $conn->error . "</div>";
    }
}

echo "<hr>";
echo "<h3>Setup Complete!</h3>";
echo "<p>All tables have been created. You can now:</p>";
echo "<ul>";
echo "<li>Go to <a href='admin/service-forms.php'>Admin → Service Forms</a> to create forms</li>";
echo "<li>Go to <a href='admin/services.php'>Admin → Services</a> to manage services</li>";
echo "<li>Go to <a href='admin/service-requests.php'>Admin → Service Requests</a> to view requests</li>";
echo "</ul>";

$conn->close();
?>

