<?php
require_once '../includes/admin_check.php';

$conn = getDBConnection();

// Create necessary tables
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

$serviceId = $_GET['service_id'] ?? $_POST['service_id'] ?? 0;
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$formId = $_GET['id'] ?? 0;

// Get service details
$service = null;
if ($serviceId) {
    $stmt = $conn->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->bind_param("i", $serviceId);
    $stmt->execute();
    $result = $stmt->get_result();
    $service = $result->fetch_assoc();
    $stmt->close();
}

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'edit') {
        $formName = sanitize($_POST['form_name'] ?? '');
        $formDescription = $_POST['form_description'] ?? '';
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        
        if ($action === 'add' && $serviceId) {
            $stmt = $conn->prepare("INSERT INTO service_forms (service_id, name, description, is_active) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("issi", $serviceId, $formName, $formDescription, $isActive);
            if ($stmt->execute()) {
                $formId = $conn->insert_id;
                header("Location: service-forms.php?service_id=$serviceId&action=edit&id=$formId&success=added");
                exit;
            }
            $stmt->close();
        } elseif ($action === 'edit' && $formId) {
            $stmt = $conn->prepare("UPDATE service_forms SET name=?, description=?, is_active=? WHERE id=?");
            $stmt->bind_param("ssii", $formName, $formDescription, $isActive, $formId);
            $stmt->execute();
            $stmt->close();
        }
        
        // Save form fields
        if ($formId && isset($_POST['fields'])) {
            // Delete existing fields
            $deleteStmt = $conn->prepare("DELETE FROM form_fields WHERE form_id = ?");
            $deleteStmt->bind_param("i", $formId);
            $deleteStmt->execute();
            $deleteStmt->close();
            
            // Insert new fields
            $fields = $_POST['fields'];
            foreach ($fields as $fieldIndex => $field) {
                $fieldType = sanitize($field['type'] ?? 'text');
                $fieldLabel = sanitize($field['label'] ?? '');
                $fieldName = sanitize($field['name'] ?? '');
                
                // Handle options - if it's a textarea with line breaks, split it
                $fieldOptions = null;
                if (isset($field['options'])) {
                    if (is_string($field['options'])) {
                        // Split by line breaks and filter empty values
                        $optionsArray = array_filter(array_map('trim', explode("\n", $field['options'])));
                        if (!empty($optionsArray)) {
                            $fieldOptions = json_encode(array_values($optionsArray));
                        }
                    } elseif (is_array($field['options'])) {
                        $fieldOptions = json_encode($field['options']);
                    }
                }
                
                $isRequired = isset($field['required']) ? 1 : 0;
                $displayOrder = intval($field['order'] ?? $fieldIndex);
                $placeholder = sanitize($field['placeholder'] ?? '');
                
                $insertStmt = $conn->prepare("INSERT INTO form_fields (form_id, field_type, field_label, field_name, field_options, is_required, display_order, placeholder) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $insertStmt->bind_param("issssiis", $formId, $fieldType, $fieldLabel, $fieldName, $fieldOptions, $isRequired, $displayOrder, $placeholder);
                $insertStmt->execute();
                $insertStmt->close();
            }
        }
        
        if ($action === 'edit') {
            header("Location: service-forms.php?service_id=$serviceId&action=edit&id=$formId&success=updated");
            exit;
        }
    }
}

if ($action === 'delete' && $formId) {
    $stmt = $conn->prepare("DELETE FROM service_forms WHERE id = ?");
    $stmt->bind_param("i", $formId);
    $stmt->execute();
    $stmt->close();
    header("Location: service-forms.php?service_id=$serviceId&success=deleted");
    exit;
}

// Get forms for service
$forms = [];
if ($serviceId) {
    $stmt = $conn->prepare("SELECT * FROM service_forms WHERE service_id = ? ORDER BY created_at DESC");
    $stmt->bind_param("i", $serviceId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $forms[] = $row;
    }
    $stmt->close();
}

// Get form with fields for editing
$form = null;
$formFields = [];
if ($action === 'edit' && $formId) {
    $stmt = $conn->prepare("SELECT * FROM service_forms WHERE id = ?");
    $stmt->bind_param("i", $formId);
    $stmt->execute();
    $result = $stmt->get_result();
    $form = $result->fetch_assoc();
    $stmt->close();
    
    if ($form) {
        $stmt = $conn->prepare("SELECT * FROM form_fields WHERE form_id = ? ORDER BY display_order ASC");
        $stmt->bind_param("i", $formId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $formFields[] = $row;
        }
        $stmt->close();
    }
}

// Get all services for dropdown
$allServices = [];
$result = $conn->query("SELECT * FROM services ORDER BY title ASC");
while ($row = $result->fetch_assoc()) {
    $allServices[] = $row;
}

// Keep connection open for later use in loop
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Service Forms Management - Orchidee LLC</title>
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
        .field-item {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            background: #f8f9fa;
        }
        .sortable-placeholder {
            border: 2px dashed #007bff;
            height: 80px;
            margin-bottom: 15px;
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
                            <i class="fa fa-wpforms me-2"></i>Service Forms Management
                        </h2>
                        <a href="services.php" class="btn btn-outline-secondary">
                            <i class="fa fa-arrow-left me-2"></i>Back to Services
                        </a>
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
                            <a class="nav-link" href="services.php">
                                <i class="fa fa-cogs me-2"></i>Services
                            </a>
                            <a class="nav-link active" href="service-forms.php">
                                <i class="fa fa-wpforms me-2"></i>Service Forms
                            </a>
                            <a class="nav-link" href="service-requests.php">
                                <i class="fa fa-list-alt me-2"></i>Service Requests
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
                            <?php
                            $messages = ['added' => 'Form added successfully!', 'updated' => 'Form updated successfully!', 'deleted' => 'Form deleted successfully!'];
                            echo $messages[$_GET['success']] ?? 'Action completed successfully!';
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (!$serviceId): ?>
                        <!-- Service Selection -->
                        <div class="bg-white rounded shadow-sm p-4 mb-4">
                            <h4 class="mb-3">Select a Service</h4>
                            <div class="row g-3">
                                <?php foreach ($allServices as $svc): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <a href="service-forms.php?service_id=<?php echo $svc['id']; ?>" class="text-decoration-none">
                                            <div class="card h-100 hover-shadow">
                                                <div class="card-body">
                                                    <h5 class="card-title"><?php echo htmlspecialchars($svc['title']); ?></h5>
                                                    <p class="card-text text-muted small"><?php echo htmlspecialchars(substr($svc['description'] ?? '', 0, 100)); ?>...</p>
                                                </div>
                                            </div>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php elseif ($service && $action === 'list'): ?>
                        <!-- Forms List for Service -->
                        <div class="bg-white rounded shadow-sm p-4 mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4>Forms for: <?php echo htmlspecialchars($service['title']); ?></h4>
                                <a href="service-forms.php?service_id=<?php echo $serviceId; ?>&action=add" class="btn btn-primary">
                                    <i class="fa fa-plus me-2"></i>Add New Form
                                </a>
                            </div>
                            
                            <?php if (empty($forms)): ?>
                                <div class="alert alert-warning">
                                    <i class="fa fa-exclamation-triangle me-2"></i>
                                    <strong>No forms created yet for this service.</strong>
                                    <p class="mb-2 mt-2">
                                        Users cannot apply for this service until you create and activate a form. 
                                        Click the button below to create your first form.
                                    </p>
                                    <a href="service-forms.php?service_id=<?php echo $serviceId; ?>&action=add" class="btn btn-primary">
                                        <i class="fa fa-plus me-2"></i>Create New Form
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Form Name</th>
                                                <th>Status</th>
                                                <th>Fields</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($forms as $f): 
                                                $fieldCountStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM form_fields WHERE form_id = ?");
                                                $fieldCountStmt->bind_param("i", $f['id']);
                                                $fieldCountStmt->execute();
                                                $fieldResult = $fieldCountStmt->get_result();
                                                $fieldCount = $fieldResult->fetch_assoc()['cnt'];
                                                $fieldCountStmt->close();
                                            ?>
                                                <tr>
                                                    <td><strong><?php echo htmlspecialchars($f['name']); ?></strong></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $f['is_active'] ? 'success' : 'secondary'; ?>">
                                                            <?php echo $f['is_active'] ? 'Active' : 'Inactive'; ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $fieldCount; ?> fields</td>
                                                    <td><?php echo date('M d, Y', strtotime($f['created_at'])); ?></td>
                                                    <td>
                                                        <a href="service-forms.php?service_id=<?php echo $serviceId; ?>&action=edit&id=<?php echo $f['id']; ?>" class="btn btn-sm btn-primary">
                                                            <i class="fa fa-edit"></i>
                                                        </a>
                                                        <a href="service-forms.php?service_id=<?php echo $serviceId; ?>&action=delete&id=<?php echo $f['id']; ?>" 
                                                           class="btn btn-sm btn-danger" 
                                                           onclick="return confirm('Are you sure?');">
                                                            <i class="fa fa-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php elseif ($service && $action === 'add'): ?>
                        <!-- Add Form -->
                        <div class="bg-white rounded shadow-sm p-4">
                            <h4>Create New Form for: <?php echo htmlspecialchars($service['title']); ?></h4>
                            <form method="POST" action="service-forms.php?service_id=<?php echo $serviceId; ?>&action=add">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="service_id" value="<?php echo $serviceId; ?>">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Form Name *</label>
                                    <input type="text" class="form-control" name="form_name" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Description</label>
                                    <textarea class="form-control" name="form_description" rows="3"></textarea>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                                        <label class="form-check-label" for="is_active">Active</label>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">Create Form</button>
                                <a href="service-forms.php?service_id=<?php echo $serviceId; ?>" class="btn btn-secondary">Cancel</a>
                            </form>
                        </div>
                    <?php elseif ($serviceId && !$service): ?>
                        <!-- Service Not Found -->
                        <div class="alert alert-danger">
                            <i class="fa fa-exclamation-circle me-2"></i>
                            <strong>Service not found.</strong> The service with ID <?php echo htmlspecialchars($serviceId); ?> does not exist.
                            <div class="mt-3">
                                <a href="service-forms.php" class="btn btn-primary">Select a Service</a>
                            </div>
                        </div>
                    <?php elseif ($service && $action === 'edit' && $form): ?>
                        <!-- Edit Form -->
                        <div class="bg-white rounded shadow-sm p-4">
                            <h4>Edit Form: <?php echo htmlspecialchars($form['name']); ?></h4>
                            <form method="POST" action="service-forms.php?service_id=<?php echo $serviceId; ?>&action=edit&id=<?php echo $formId; ?>" id="formBuilder">
                                <input type="hidden" name="action" value="edit">
                                <input type="hidden" name="service_id" value="<?php echo $serviceId; ?>">
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Form Name *</label>
                                    <input type="text" class="form-control" name="form_name" value="<?php echo htmlspecialchars($form['name']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Description</label>
                                    <textarea class="form-control" name="form_description" rows="3"><?php echo htmlspecialchars($form['description'] ?? ''); ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" <?php echo $form['is_active'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">Active</label>
                                    </div>
                                </div>

                                <hr>
                                <h5 class="mb-3">Form Fields</h5>
                                <div id="fieldsContainer">
                                    <?php foreach ($formFields as $field): ?>
                                        <div class="field-item" data-order="<?php echo $field['display_order']; ?>">
                                            <div class="row g-2">
                                                <div class="col-md-3">
                                                    <select class="form-select field-type" name="fields[<?php echo $field['id']; ?>][type]" required>
                                                        <option value="text" <?php echo $field['field_type'] === 'text' ? 'selected' : ''; ?>>Text</option>
                                                        <option value="email" <?php echo $field['field_type'] === 'email' ? 'selected' : ''; ?>>Email</option>
                                                        <option value="textarea" <?php echo $field['field_type'] === 'textarea' ? 'selected' : ''; ?>>Textarea</option>
                                                        <option value="select" <?php echo $field['field_type'] === 'select' ? 'selected' : ''; ?>>Select</option>
                                                        <option value="radio" <?php echo $field['field_type'] === 'radio' ? 'selected' : ''; ?>>Radio</option>
                                                        <option value="checkbox" <?php echo $field['field_type'] === 'checkbox' ? 'selected' : ''; ?>>Checkbox</option>
                                                        <option value="date" <?php echo $field['field_type'] === 'date' ? 'selected' : ''; ?>>Date</option>
                                                        <option value="number" <?php echo $field['field_type'] === 'number' ? 'selected' : ''; ?>>Number</option>
                                                        <option value="file" <?php echo $field['field_type'] === 'file' ? 'selected' : ''; ?>>File</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <input type="text" class="form-control" name="fields[<?php echo $field['id']; ?>][label]" 
                                                           placeholder="Label" value="<?php echo htmlspecialchars($field['field_label']); ?>" required>
                                                </div>
                                                <div class="col-md-2">
                                                    <input type="text" class="form-control" name="fields[<?php echo $field['id']; ?>][name]" 
                                                           placeholder="Field Name" value="<?php echo htmlspecialchars($field['field_name']); ?>" required>
                                                </div>
                                                <div class="col-md-2">
                                                    <input type="text" class="form-control" name="fields[<?php echo $field['id']; ?>][placeholder]" 
                                                           placeholder="Placeholder" value="<?php echo htmlspecialchars($field['placeholder'] ?? ''); ?>">
                                                </div>
                                                <div class="col-md-1">
                                                    <div class="form-check mt-2">
                                                        <input class="form-check-input" type="checkbox" name="fields[<?php echo $field['id']; ?>][required]" 
                                                               <?php echo $field['is_required'] ? 'checked' : ''; ?>>
                                                    </div>
                                                </div>
                                                <div class="col-md-1">
                                                    <button type="button" class="btn btn-sm btn-danger remove-field">
                                                        <i class="fa fa-times"></i>
                                                    </button>
                                                </div>
                                                <input type="hidden" name="fields[<?php echo $field['id']; ?>][order]" value="<?php echo $field['display_order']; ?>">
                                                <?php if (in_array($field['field_type'], ['select', 'radio'])): 
                                                    $options = json_decode($field['field_options'], true);
                                                    // If options is not an array, try to convert it
                                                    if (!is_array($options) && !empty($field['field_options'])) {
                                                        // If it's a string with line breaks, split it
                                                        if (is_string($field['field_options'])) {
                                                            $options = array_filter(array_map('trim', explode("\n", $field['field_options'])));
                                                        } else {
                                                            $options = [];
                                                        }
                                                    }
                                                    $optionsText = is_array($options) ? implode("\n", $options) : '';
                                                ?>
                                                    <div class="col-12 mt-2 options-container">
                                                        <label class="small">Options (one per line):</label>
                                                        <textarea class="form-control form-control-sm" name="fields[<?php echo $field['id']; ?>][options]" rows="2"><?php echo htmlspecialchars($optionsText); ?></textarea>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <button type="button" class="btn btn-outline-primary mt-3" id="addField">
                                    <i class="fa fa-plus me-2"></i>Add Field
                                </button>
                                
                                <hr>
                                <button type="submit" class="btn btn-primary">Save Form</button>
                                <a href="service-forms.php?service_id=<?php echo $serviceId; ?>" class="btn btn-secondary">Cancel</a>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    <script src="../js/main.js"></script>
    <script>
        let fieldIndex = <?php echo !empty($formFields) ? max(array_column($formFields, 'id')) + 1 : 1000; ?>;
        
        $('#addField').click(function() {
            const fieldHtml = `
                <div class="field-item" data-order="${fieldIndex}">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <select class="form-select field-type" name="fields[${fieldIndex}][type]" required>
                                <option value="text">Text</option>
                                <option value="email">Email</option>
                                <option value="textarea">Textarea</option>
                                <option value="select">Select</option>
                                <option value="radio">Radio</option>
                                <option value="checkbox">Checkbox</option>
                                <option value="date">Date</option>
                                <option value="number">Number</option>
                                <option value="file">File</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control" name="fields[${fieldIndex}][label]" placeholder="Label" required>
                        </div>
                        <div class="col-md-2">
                            <input type="text" class="form-control" name="fields[${fieldIndex}][name]" placeholder="Field Name" required>
                        </div>
                        <div class="col-md-2">
                            <input type="text" class="form-control" name="fields[${fieldIndex}][placeholder]" placeholder="Placeholder">
                        </div>
                        <div class="col-md-1">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="fields[${fieldIndex}][required]">
                            </div>
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-sm btn-danger remove-field">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>
                        <input type="hidden" name="fields[${fieldIndex}][order]" value="${fieldIndex}">
                        <div class="col-12 mt-2 options-container" style="display:none;">
                            <label class="small">Options (one per line):</label>
                            <textarea class="form-control form-control-sm" name="fields[${fieldIndex}][options]" rows="2"></textarea>
                        </div>
                    </div>
                </div>
            `;
            $('#fieldsContainer').append(fieldHtml);
            fieldIndex++;
        });
        
        $(document).on('click', '.remove-field', function() {
            $(this).closest('.field-item').remove();
        });
        
        $(document).on('change', '.field-type', function() {
            const fieldItem = $(this).closest('.field-item');
            const optionsContainer = fieldItem.find('.options-container');
            if (['select', 'radio'].includes($(this).val())) {
                optionsContainer.show();
            } else {
                optionsContainer.hide();
            }
        });
        
        // Make fields sortable
        $('#fieldsContainer').sortable({
            placeholder: 'sortable-placeholder',
            update: function(event, ui) {
                $('#fieldsContainer .field-item').each(function(index) {
                    $(this).find('input[name$="[order]"]').val(index);
                });
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>

