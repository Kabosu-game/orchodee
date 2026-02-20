<?php
require_once '../includes/admin_check.php';

$conn = getDBConnection();

// Vérifier si la table services existe, sinon la créer
$tableCheck = $conn->query("SHOW TABLES LIKE 'services'");
if ($tableCheck->num_rows === 0) {
    $createTableSQL = "CREATE TABLE IF NOT EXISTS services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        image VARCHAR(255) DEFAULT NULL,
        icon VARCHAR(100) DEFAULT NULL,
        price DECIMAL(10, 2) DEFAULT NULL,
        display_order INT DEFAULT 0,
        status ENUM('active', 'inactive') DEFAULT 'active',
        recurring_enabled TINYINT(1) DEFAULT 0,
        billing_interval VARCHAR(10) DEFAULT 'month',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        KEY status (status),
        KEY display_order (display_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($createTableSQL)) {
        die("Error creating services table: " . $conn->error);
    }
} else {
    // Ensure recurring columns exist
    $colCheck = $conn->query("SHOW COLUMNS FROM services LIKE 'recurring_enabled'");
    if ($colCheck && $colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE services ADD recurring_enabled TINYINT(1) DEFAULT 0, ADD billing_interval VARCHAR(10) DEFAULT 'month'");
    }
}
// Ensure service_plans table exists
$tblPlans = $conn->query("SHOW TABLES LIKE 'service_plans'");
if ($tblPlans && $tblPlans->num_rows === 0) {
    $conn->query("CREATE TABLE IF NOT EXISTS service_plans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        service_id INT NOT NULL,
        duration_months INT NOT NULL,
        total_price DECIMAL(10, 2) NOT NULL,
        display_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        KEY service_id (service_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

$action = $_GET['action'] ?? 'list';
$serviceId = $_GET['id'] ?? 0;

// Gestion de la suppression
if ($action === 'delete' && $serviceId) {
    $stmt = $conn->prepare("SELECT id FROM services WHERE id = ?");
    $stmt->bind_param("i", $serviceId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $error = "Service not found.";
    } else {
        $stmt->close();
        $stmt = $conn->prepare("DELETE FROM services WHERE id = ?");
        $stmt->bind_param("i", $serviceId);
        if ($stmt->execute()) {
            header("Location: services.php?success=deleted");
            exit;
        } else {
            $error = "Error deleting service: " . $conn->error;
        }
    }
    $stmt->close();
}

// Traitement des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'edit') {
        $title = sanitize($_POST['title'] ?? '');
        $description = $_POST['description'] ?? '';
        $icon = sanitize($_POST['icon'] ?? '');
        $price = !empty($_POST['price']) ? floatval($_POST['price']) : null;
        $displayOrder = intval($_POST['display_order'] ?? 0);
        $status = sanitize($_POST['status'] ?? 'active');
        $recurringEnabled = isset($_POST['recurring_enabled']) ? 1 : 0;
        $billingInterval = in_array($_POST['billing_interval'] ?? '', ['week', 'month']) ? $_POST['billing_interval'] : 'month';
        
        // Handle image upload
        $image = '';
        if ($action === 'edit' && $serviceId) {
            // Récupérer l'image existante
            $stmt = $conn->prepare("SELECT image FROM services WHERE id = ?");
            $stmt->bind_param("i", $serviceId);
            $stmt->execute();
            $result = $stmt->get_result();
            $existingService = $result->fetch_assoc();
            $image = $existingService['image'] ?? '';
            $stmt->close();
        }
        
        // Si une nouvelle image est uploadée, la remplacer
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $uploadDir = '../uploads/services/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileName = time() . '_' . basename($_FILES['image']['name']);
            $targetFile = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
                $image = 'uploads/services/' . $fileName;
            }
        }
        
        if ($action === 'add') {
            if ($image) {
                $stmt = $conn->prepare("INSERT INTO services (title, description, image, icon, price, display_order, status, recurring_enabled, billing_interval) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssdisss", $title, $description, $image, $icon, $price, $displayOrder, $status, $recurringEnabled, $billingInterval);
            } else {
                $stmt = $conn->prepare("INSERT INTO services (title, description, icon, price, display_order, status, recurring_enabled, billing_interval) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssdisss", $title, $description, $icon, $price, $displayOrder, $status, $recurringEnabled, $billingInterval);
            }
        } else {
            if ($image) {
                $stmt = $conn->prepare("UPDATE services SET title=?, description=?, image=?, icon=?, price=?, display_order=?, status=?, recurring_enabled=?, billing_interval=? WHERE id=?");
                $stmt->bind_param("ssssdissi", $title, $description, $image, $icon, $price, $displayOrder, $status, $recurringEnabled, $billingInterval, $serviceId);
            } else {
                $stmt = $conn->prepare("UPDATE services SET title=?, description=?, icon=?, price=?, display_order=?, status=?, recurring_enabled=?, billing_interval=? WHERE id=?");
                $stmt->bind_param("sssdisssi", $title, $description, $icon, $price, $displayOrder, $status, $recurringEnabled, $billingInterval, $serviceId);
            }
        }
        
        if ($stmt->execute()) {
            $stmt->close();
            $savedId = $action === 'add' ? $conn->insert_id : $serviceId;
            // Save recurring plans
            if ($recurringEnabled && $savedId) {
                $conn->query("DELETE FROM service_plans WHERE service_id = " . intval($savedId));
                if (!empty($_POST['plan_duration']) && is_array($_POST['plan_duration'])) {
                    $ins = $conn->prepare("INSERT INTO service_plans (service_id, duration_months, total_price, display_order) VALUES (?, ?, ?, ?)");
                    foreach ($_POST['plan_duration'] as $i => $dur) {
                        $dur = intval($dur);
                        $total = isset($_POST['plan_price'][$i]) ? floatval($_POST['plan_price'][$i]) : 0;
                        if ($dur > 0 && $total >= 0) {
                            $ins->bind_param("iidi", $savedId, $dur, $total, $i);
                            $ins->execute();
                        }
                    }
                    $ins->close();
                }
            }
            header("Location: services.php?success=" . ($action === 'add' ? 'added' : 'updated'));
            exit;
        } else {
            $error = "Error saving service: " . $conn->error;
        }
        $stmt->close();
    }
}

// Récupération des données
$service = null;
$servicePlans = [];
if ($action === 'edit' && $serviceId) {
    $stmt = $conn->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->bind_param("i", $serviceId);
    $stmt->execute();
    $result = $stmt->get_result();
    $service = $result->fetch_assoc();
    $stmt->close();
    $res = $conn->query("SELECT * FROM service_plans WHERE service_id = " . intval($serviceId) . " ORDER BY display_order ASC, duration_months ASC");
    if ($res) while ($row = $res->fetch_assoc()) $servicePlans[] = $row;
}

// Liste des services
$services = [];
$result = $conn->query("SELECT * FROM services ORDER BY display_order ASC, created_at DESC");
while ($row = $result->fetch_assoc()) {
    $services[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Services Management - Orchidee LLC</title>
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
                            <i class="fa fa-cogs me-2"></i>Services Management
                        </h2>
                        <div>
                            <a href="dashboard.php" class="btn btn-outline-secondary me-2">
                                <i class="fa fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                            <a href="services.php?action=add" class="btn btn-primary">
                                <i class="fa fa-plus me-2"></i>Add Service
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
                            <a class="nav-link" href="sessions.php">
                                <i class="fa fa-calendar-check me-2"></i>NCLEX Sessions
                            </a>
                            <a class="nav-link" href="team.php">
                                <i class="fa fa-users me-2"></i>Team Members
                            </a>
                            <a class="nav-link active" href="services.php">
                                <i class="fa fa-cogs me-2"></i>Services
                            </a>
                            <a class="nav-link" href="service-forms.php">
                                <i class="fa fa-wpforms me-2"></i>Service Forms
                            </a>
                            <a class="nav-link" href="service-requests.php">
                                <i class="fa fa-list-alt me-2"></i>Service Requests
                            </a>
                            <a class="nav-link" href="service-subscriptions.php">
                                <i class="fa fa-sync-alt me-2"></i>Subscriptions
                            </a>
                            <a class="nav-link" href="users.php">
                                <i class="fa fa-user-friends me-2"></i>Users
                            </a>
                        </nav>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="col-lg-9">
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            Service <?php echo $_GET['success']; ?> successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($action === 'add' || $action === 'edit'): ?>
                        <!-- Add/Edit Form -->
                        <div class="bg-white rounded p-5 shadow-sm">
                            <h4 class="mb-4"><?php echo $action === 'add' ? 'Add New' : 'Edit'; ?> Service</h4>
                            
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Service Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($service['title'] ?? ''); ?>" placeholder="e.g., Licensure registration" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description *</label>
                                    <textarea class="form-control" id="description" name="description" rows="6" required><?php echo htmlspecialchars($service['description'] ?? ''); ?></textarea>
                                    <small class="text-muted">Detailed description of the service</small>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="icon" class="form-label">FontAwesome Icon Class</label>
                                        <input type="text" class="form-control" id="icon" name="icon" value="<?php echo htmlspecialchars($service['icon'] ?? ''); ?>" placeholder="e.g., fa fa-users">
                                        <small class="text-muted">FontAwesome icon class (e.g., fa fa-users, fa fa-hospital)</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="price" class="form-label">Price (USD)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" step="0.01" min="0" class="form-control" id="price" name="price" value="<?php echo $service['price'] ?? ''; ?>" placeholder="0.00">
                                        </div>
                                        <small class="text-muted">Leave empty if service is free or contact-based</small>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="image" class="form-label">Service Image</label>
                                    <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                    <?php if (!empty($service['image'])): ?>
                                        <div class="mt-2">
                                            <img src="../<?php echo htmlspecialchars($service['image']); ?>" alt="Current image" class="img-thumbnail" style="max-width: 300px;">
                                            <p class="small text-muted mt-1">Current image</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="display_order" class="form-label">Display Order</label>
                                        <input type="number" class="form-control" id="display_order" name="display_order" value="<?php echo $service['display_order'] ?? 0; ?>" min="0">
                                        <small class="text-muted">Lower numbers appear first</small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="active" <?php echo ($service['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo ($service['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <hr class="my-4">
                                <h5 class="mb-3">Recurring Payments</h5>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="recurring_enabled" name="recurring_enabled" <?php echo !empty($service['recurring_enabled']) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="recurring_enabled">Enable recurring payments for this service</label>
                                    </div>
                                    <small class="text-muted">User pays per week or per month according to the plan duration.</small>
                                </div>
                                <div class="mb-3" id="recurringOptions" style="<?php echo !empty($service['recurring_enabled']) ? '' : 'display:none;'; ?>">
                                    <label class="form-label">Billing interval (what the user pays each time)</label>
                                    <select class="form-select" name="billing_interval" style="max-width: 200px;">
                                        <option value="month" <?php echo ($service['billing_interval'] ?? 'month') === 'month' ? 'selected' : ''; ?>>Per month</option>
                                        <option value="week" <?php echo ($service['billing_interval'] ?? '') === 'week' ? 'selected' : ''; ?>>Per week</option>
                                    </select>
                                    <small class="text-muted">Amount per interval is calculated from total price ÷ number of intervals.</small>
                                </div>
                                <div class="mb-4" id="plansContainer" style="<?php echo !empty($service['recurring_enabled']) ? '' : 'display:none;'; ?>">
                                    <label class="form-label">Plans (duration + total price)</label>
                                    <div id="plansList">
                                        <?php
                                        if (!empty($servicePlans)) {
                                            foreach ($servicePlans as $idx => $p) {
                                                echo '<div class="input-group mb-2 plan-row"><span class="input-group-text">Duration</span>';
                                                echo '<input type="number" name="plan_duration[]" class="form-control" placeholder="Months" value="' . (int)$p['duration_months'] . '" min="1" style="max-width:100px;">';
                                                echo '<span class="input-group-text">$</span><input type="number" step="0.01" name="plan_price[]" class="form-control" placeholder="Total price" value="' . htmlspecialchars($p['total_price']) . '">';
                                                echo '<button type="button" class="btn btn-outline-danger remove-plan">Remove</button></div>';
                                            }
                                        } else {
                                            echo '<div class="input-group mb-2 plan-row"><span class="input-group-text">Duration</span>';
                                            echo '<input type="number" name="plan_duration[]" class="form-control" placeholder="Months" value="1" min="1" style="max-width:100px;">';
                                            echo '<span class="input-group-text">$</span><input type="number" step="0.01" name="plan_price[]" class="form-control" placeholder="Total price">';
                                            echo '<button type="button" class="btn btn-outline-danger remove-plan">Remove</button></div>';
                                        }
                                        ?>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="addPlan">+ Add plan</button>
                                </div>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <a href="services.php" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-save me-2"></i>Save Service
                                    </button>
                                </div>
                            </form>
                            <script>
                            (function() {
                                var recurringCb = document.getElementById('recurring_enabled');
                                var recurringOpts = document.getElementById('recurringOptions');
                                var plansCont = document.getElementById('plansContainer');
                                if (recurringCb) {
                                    recurringCb.addEventListener('change', function() {
                                        var show = recurringCb.checked;
                                        recurringOpts.style.display = show ? '' : 'none';
                                        plansCont.style.display = show ? '' : 'none';
                                    });
                                }
                                function addPlanRow() {
                                    var list = document.getElementById('plansList');
                                    var div = document.createElement('div');
                                    div.className = 'input-group mb-2 plan-row';
                                    div.innerHTML = '<span class="input-group-text">Duration</span><input type="number" name="plan_duration[]" class="form-control" placeholder="Months" value="1" min="1" style="max-width:100px;"><span class="input-group-text">$</span><input type="number" step="0.01" name="plan_price[]" class="form-control" placeholder="Total price"><button type="button" class="btn btn-outline-danger remove-plan">Remove</button>';
                                    list.appendChild(div);
                                    div.querySelector('.remove-plan').onclick = function() { div.remove(); };
                                }
                                document.getElementById('addPlan').onclick = addPlanRow;
                                document.querySelectorAll('.remove-plan').forEach(function(btn) {
                                    btn.onclick = function() { this.closest('.plan-row').remove(); };
                                });
                            })();
                            </script>
                        </div>
                    <?php else: ?>
                        <!-- List View -->
                        <div class="bg-white rounded p-4 shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4 class="mb-0">All Services</h4>
                                <a href="services.php?action=add" class="btn btn-primary btn-sm">
                                    <i class="fa fa-plus me-2"></i>Add New
                                </a>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Image</th>
                                            <th>Title</th>
                                            <th>Price</th>
                                            <th>Order</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($services)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">
                                                    No services found. <a href="services.php?action=add">Add your first service</a>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($services as $svc): ?>
                                                <tr>
                                                    <td><?php echo $svc['id']; ?></td>
                                                    <td>
                                                        <?php if (!empty($svc['image'])): ?>
                                                            <img src="../<?php echo htmlspecialchars($svc['image']); ?>" alt="<?php echo htmlspecialchars($svc['title']); ?>" class="img-thumbnail" style="max-width: 80px; max-height: 80px; object-fit: cover;">
                                                        <?php else: ?>
                                                            <span class="text-muted">No image</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($svc['title']); ?></strong>
                                                        <?php if (!empty($svc['recurring_enabled'])): ?>
                                                            <span class="badge bg-info ms-1">Recurring</span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($svc['icon'])): ?>
                                                            <br><small class="text-muted"><i class="<?php echo htmlspecialchars($svc['icon']); ?>"></i> <?php echo htmlspecialchars($svc['icon']); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($svc['recurring_enabled'])): ?>
                                                            <span class="text-muted">Plans</span>
                                                        <?php elseif ($svc['price'] !== null): ?>
                                                            <strong class="text-success">$<?php echo number_format($svc['price'], 2); ?></strong>
                                                        <?php else: ?>
                                                            <span class="text-muted">Contact us</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo $svc['display_order']; ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $svc['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                            <?php echo ucfirst($svc['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="services.php?action=edit&id=<?php echo $svc['id']; ?>" class="btn btn-outline-primary" title="Edit">
                                                                <i class="fa fa-edit"></i>
                                                            </a>
                                                            <a href="service-forms.php?service_id=<?php echo $svc['id']; ?>" class="btn btn-outline-info" title="Manage Forms">
                                                                <i class="fa fa-wpforms"></i>
                                                            </a>
                                                            <a href="services.php?action=delete&id=<?php echo $svc['id']; ?>" class="btn btn-outline-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this service?');">
                                                                <i class="fa fa-trash"></i>
                                                            </a>
                                                        </div>
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

    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/main.js"></script>
</body>
</html>

