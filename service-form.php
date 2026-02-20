<?php
require_once 'config/database.php';

$conn = getDBConnection();

// Create tables if they don't exist
$tables = [
    'service_forms' => "CREATE TABLE IF NOT EXISTS service_forms (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'form_fields' => "CREATE TABLE IF NOT EXISTS form_fields (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'service_requests' => "CREATE TABLE IF NOT EXISTS service_requests (
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
];

// Check and create tables
foreach ($tables as $tableName => $createSQL) {
    $tableCheck = $conn->query("SHOW TABLES LIKE '$tableName'");
    if ($tableCheck && $tableCheck->num_rows === 0) {
        // Temporarily disable foreign key checks
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");
        $conn->query($createSQL);
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    }
}

$serviceId = $_GET['service_id'] ?? 0;
if (!$serviceId) {
    $conn->close();
    header("Location: index.php");
    exit;
}

// Get service details (including recurring columns)
$stmt = $conn->prepare("SELECT * FROM services WHERE id = ? AND status = 'active'");
$stmt->bind_param("i", $serviceId);
$stmt->execute();
$result = $stmt->get_result();
$service = $result->fetch_assoc();
$stmt->close();

$servicePlans = [];
if ($service && !empty($service['recurring_enabled'])) {
    $res = $conn->query("SELECT * FROM service_plans WHERE service_id = " . intval($serviceId) . " ORDER BY duration_months ASC");
    if ($res) while ($row = $res->fetch_assoc()) $servicePlans[] = $row;
}

if (!$service) {
    $conn->close();
    header("Location: index.php");
    exit;
}

// Check if user is admin (before any HTML output, without forcing login)
$isAdmin = false;
$isLoggedIn = false;
if (file_exists('config/database.php')) {
    require_once 'config/database.php';
    if (function_exists('startSession')) {
        startSession();
        if (function_exists('isLoggedIn')) {
            $isLoggedIn = isLoggedIn();
        }
        if (function_exists('isAdmin')) {
            $isAdmin = isAdmin();
        }
    }
}

// Get active form for this service
$form = null;
$formFields = [];

$stmt = $conn->prepare("SELECT * FROM service_forms WHERE service_id = ? AND is_active = 1 ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param("i", $serviceId);
$stmt->execute();
$result = $stmt->get_result();
$form = $result->fetch_assoc();
$stmt->close();

if ($form) {
    $stmt = $conn->prepare("SELECT * FROM form_fields WHERE form_id = ? ORDER BY display_order ASC");
    $stmt->bind_param("i", $form['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $formFields[] = $row;
    }
    $stmt->close();
}

// Process form submission
$formData = [];
$error = '';
// $isLoggedIn already checked above

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_service_form'])) {
    // $isLoggedIn already checked above
    
    // Validate required fields FIRST
    $allValid = true;
    $missingFields = [];
    foreach ($formFields as $field) {
        if ($field['is_required']) {
            $fieldName = $field['field_name'];
            $fieldType = $field['field_type'];
            $isEmpty = false;
            
            // Handle different field types
            if ($fieldType === 'file') {
                // Check if file was uploaded
                $isEmpty = !isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE || $_FILES[$fieldName]['size'] === 0;
            } elseif ($fieldType === 'checkbox') {
                // Checkboxes with multiple options - check if at least one is checked
                $isEmpty = !isset($_POST[$fieldName]) || (is_array($_POST[$fieldName]) && empty($_POST[$fieldName]));
            } elseif ($fieldType === 'radio') {
                // Radio buttons - check if one option is selected
                $isEmpty = !isset($_POST[$fieldName]) || trim($_POST[$fieldName]) === '';
            } else {
                // Text, email, textarea, select, date, number - check if value exists and is not empty
                $isEmpty = !isset($_POST[$fieldName]) || trim($_POST[$fieldName]) === '' || $_POST[$fieldName] === null;
            }
            
            if ($isEmpty) {
                $missingFields[] = $field['field_label'];
                $allValid = false;
            }
        }
    }
    
    if (!$allValid) {
        $error = "Please fill in all required fields: " . implode(', ', $missingFields);
    } elseif (!empty($service['recurring_enabled']) && empty($_POST['plan_id'])) {
        $error = "Please select a plan (duration and price).";
    } else {
        // Collect form data ONLY if validation passes
        $formData = [];
        foreach ($_POST as $key => $value) {
            if ($key !== 'submit_service_form' && $key !== 'service_id' && $key !== 'form_id' && $key !== 'plan_id') {
                if (is_array($value)) {
                    $formData[$key] = implode(', ', $value);
                } else {
                    $formData[$key] = trim($value);
                }
            }
        }
        
        // Handle file uploads
        foreach ($formFields as $field) {
            if ($field['field_type'] === 'file' && isset($_FILES[$field['field_name']])) {
                $fileField = $_FILES[$field['field_name']];
                if ($fileField['error'] === UPLOAD_ERR_OK) {
                    // Store file info in form data
                    $formData[$field['field_name']] = $fileField['name'] . ' (uploaded)';
                    // TODO: You might want to save the file and store the path
                }
            }
        }
    }
    
    if ($allValid && isset($formData)) {
        // Save form data and create service request
        $formDataJson = json_encode($formData, JSON_UNESCAPED_UNICODE);
        $userId = $isLoggedIn && function_exists('getUserId') ? getUserId() : null;
        $planId = !empty($_POST['plan_id']) ? intval($_POST['plan_id']) : null;
        
        // Ensure plan_id column exists
        $colCheck = $conn->query("SHOW COLUMNS FROM service_requests LIKE 'plan_id'");
        if ($colCheck && $colCheck->num_rows === 0) {
            $conn->query("ALTER TABLE service_requests ADD plan_id INT NULL, ADD KEY plan_id (plan_id)");
        }
        
        $stmt = $conn->prepare("INSERT INTO service_requests (service_id, form_id, user_id, form_data, payment_status, request_status, plan_id) VALUES (?, ?, ?, ?, 'pending', 'pending', ?)");
        $stmt->bind_param("iiisi", $serviceId, $form['id'], $userId, $formDataJson, $planId);
        
        if ($stmt->execute()) {
            $requestId = $conn->insert_id;
            $stmt->close();
            $conn->close();
            
            // Redirect to payment page
            header("Location: service-payment.php?request_id=" . $requestId);
            exit;
        } else {
            $error = "An error occurred. Please try again.";
        }
        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Service Request - <?php echo htmlspecialchars($service['title']); ?> - Orchidee LLC</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    
    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Inter:slnt,wght@-10..0,100..900&display=swap" rel="stylesheet">
    
    <!-- Icon Font Stylesheet -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"/>
    
    <!-- Customized Bootstrap Stylesheet -->
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Menu -->
    <?php include 'includes/menu-dynamic.php'; ?>

    <!-- Service Form Start -->
    <div class="container-fluid bg-light py-5">
        <div class="container py-5">
            <div class="row mb-4">
                <div class="col-12">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                            <li class="breadcrumb-item"><a href="service.php">Services</a></li>
                            <li class="breadcrumb-item active"><?php echo htmlspecialchars($service['title']); ?></li>
                        </ol>
                    </nav>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <div class="bg-white rounded shadow-lg p-5">
                        <div class="text-center mb-4">
                            <h2 class="text-primary mb-2"><?php echo htmlspecialchars($service['title']); ?></h2>
                            <?php if (!empty($service['recurring_enabled']) && !empty($servicePlans)): ?>
                                <p class="h5 text-success mb-2">Recurring payment plans available</p>
                                <p class="text-muted small">Choose a plan below. You will pay per <?php echo ($service['billing_interval'] ?? 'month') === 'week' ? 'week' : 'month'; ?>.</p>
                            <?php elseif ($service['price'] && $service['price'] > 0): ?>
                                <p class="h4 text-success mb-3">$<?php echo number_format($service['price'], 2); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($service['description'])): ?>
                                <div class="mb-4">
                                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($service['description'])); ?></p>
                                </div>
                            <?php endif; ?>
                            <?php if ($form): ?>
                                <h4 class="mb-3"><?php echo htmlspecialchars($form['name']); ?></h4>
                                <?php if (!empty($form['description'])): ?>
                                    <p class="text-muted"><?php echo nl2br(htmlspecialchars($form['description'])); ?></p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fa fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (!$form || empty($formFields)): ?>
                            <div class="alert alert-warning">
                                <i class="fa fa-exclamation-triangle me-2"></i>
                                <strong>No form available for this service at the moment.</strong>
                                <p class="mb-2 mt-2">
                                    The administrator has not created a form for this service yet. 
                                    Please contact us for more information or check back later.
                                </p>
                                <?php 
                                // $isAdmin already checked at the top of the file
                                if ($isAdmin): ?>
                                    <hr>
                                    <p class="mb-2"><strong>Admin:</strong> Create a form for this service now:</p>
                                    <a href="admin/service-forms.php?service_id=<?php echo $serviceId; ?>&action=add" class="btn btn-primary btn-sm">
                                        <i class="fa fa-plus me-2"></i>Create Form for This Service
                                    </a>
                                <?php endif; ?>
                                <div class="mt-3">
                                    <a href="consultation.html" class="btn btn-outline-secondary btn-sm">
                                        <i class="fa fa-phone me-2"></i>Contact Us
                                    </a>
                                    <a href="index.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="fa fa-arrow-left me-2"></i>Back to Home
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="" enctype="multipart/form-data" id="serviceForm">
                                <input type="hidden" name="service_id" value="<?php echo $serviceId; ?>">
                                <input type="hidden" name="form_id" value="<?php echo $form['id']; ?>">
                                
                                <?php foreach ($formFields as $field): 
                                    $fieldName = htmlspecialchars($field['field_name'], ENT_QUOTES, 'UTF-8');
                                    $fieldLabel = htmlspecialchars($field['field_label'], ENT_QUOTES, 'UTF-8');
                                    $fieldType = $field['field_type'];
                                    $isRequired = $field['is_required'];
                                    $placeholder = htmlspecialchars($field['placeholder'] ?? '', ENT_QUOTES, 'UTF-8');
                                    $options = !empty($field['field_options']) ? json_decode($field['field_options'], true) : [];
                                    $value = isset($_POST[$field['field_name']]) ? htmlspecialchars($_POST[$field['field_name']], ENT_QUOTES, 'UTF-8') : '';
                                ?>
                                    <div class="mb-4">
                                        <label for="<?php echo $fieldName; ?>" class="form-label fw-bold">
                                            <?php echo $fieldLabel; ?>
                                            <?php if ($isRequired): ?><span class="text-danger">*</span><?php endif; ?>
                                        </label>
                                        
                                        <?php if ($fieldType === 'textarea'): ?>
                                            <textarea class="form-control" id="<?php echo $fieldName; ?>" 
                                                      name="<?php echo $fieldName; ?>" 
                                                      rows="4" 
                                                      <?php echo $isRequired ? 'required' : ''; ?>
                                                      placeholder="<?php echo $placeholder; ?>"><?php echo $value; ?></textarea>
                                        <?php elseif ($fieldType === 'select'): ?>
                                            <select class="form-select" id="<?php echo $fieldName; ?>" 
                                                    name="<?php echo $fieldName; ?>" 
                                                    <?php echo $isRequired ? 'required' : ''; ?>>
                                                <option value="">-- Select --</option>
                                                <?php foreach ($options as $option): ?>
                                                    <option value="<?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>" 
                                                            <?php echo $value === $option ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php elseif ($fieldType === 'radio'): ?>
                                            <div>
                                                <?php foreach ($options as $option): ?>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" 
                                                               name="<?php echo $fieldName; ?>" 
                                                               id="<?php echo $fieldName . '_' . md5($option); ?>" 
                                                               value="<?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>" 
                                                               <?php echo $isRequired && $option === reset($options) ? 'required' : ''; ?>
                                                               <?php echo $value === $option ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="<?php echo $fieldName . '_' . md5($option); ?>">
                                                            <?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php elseif ($fieldType === 'checkbox'): ?>
                                            <div>
                                                <?php foreach ($options as $option): ?>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" 
                                                               name="<?php echo $fieldName; ?>[]" 
                                                               id="<?php echo $fieldName . '_' . md5($option); ?>" 
                                                               value="<?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>">
                                                        <label class="form-check-label" for="<?php echo $fieldName . '_' . md5($option); ?>">
                                                            <?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php elseif ($fieldType === 'file'): ?>
                                            <input type="file" class="form-control" id="<?php echo $fieldName; ?>" 
                                                   name="<?php echo $fieldName; ?>" 
                                                   <?php echo $isRequired ? 'required' : ''; ?>>
                                            <small class="text-muted">Maximum file size: 5MB</small>
                                        <?php else: ?>
                                            <input type="<?php echo $fieldType; ?>" 
                                                   class="form-control" 
                                                   id="<?php echo $fieldName; ?>" 
                                                   name="<?php echo $fieldName; ?>" 
                                                   value="<?php echo $value; ?>"
                                                   <?php echo $isRequired ? 'required' : ''; ?>
                                                   placeholder="<?php echo $placeholder; ?>">
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if (!empty($service['recurring_enabled']) && !empty($servicePlans)): ?>
                                    <div class="mb-4 p-3 border rounded bg-light">
                                        <label class="form-label fw-bold">Select a plan <span class="text-danger">*</span></label>
                                        <?php 
                                        $interval = ($service['billing_interval'] ?? 'month') === 'week' ? 'week' : 'month';
                                        foreach ($servicePlans as $p): 
                                            $dur = (int)$p['duration_months'];
                                            $total = (float)$p['total_price'];
                                            $perInterval = $interval === 'month' ? ($dur > 0 ? $total / $dur : $total) : ($dur > 0 ? $total / ($dur * 4) : $total); // ~4 weeks per month
                                        ?>
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="radio" name="plan_id" id="plan_<?php echo $p['id']; ?>" value="<?php echo (int)$p['id']; ?>" required>
                                                <label class="form-check-label" for="plan_<?php echo $p['id']; ?>">
                                                    <strong><?php echo $dur; ?> month<?php echo $dur > 1 ? 's' : ''; ?></strong> — Total $<?php echo number_format($total, 2); ?> 
                                                    (<?php echo $interval === 'month' ? '$' . number_format($perInterval, 2) . '/month' : '$' . number_format($perInterval, 2) . '/week'; ?>)
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="text-center mt-4">
                                    <button type="submit" name="submit_service_form" class="btn btn-primary btn-lg px-5 py-3 rounded-pill">
                                        <i class="fa fa-paper-plane me-2"></i>Submit & Continue to Payment
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Service Form End -->

    <!-- Footer -->
    <?php include 'includes/footer.php'; ?>

    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="lib/wow/wow.min.js"></script>
    <script src="lib/easing/easing.min.js"></script>
    <script src="lib/waypoints/waypoints.min.js"></script>
    <script src="lib/counterup/counterup.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>

