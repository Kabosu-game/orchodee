<?php
require_once '../includes/admin_check.php';

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

$action = $_GET['action'] ?? 'list';
$requestId = $_GET['id'] ?? 0;
$filter = $_GET['filter'] ?? 'all';

// Handle status updates
if ($action === 'update_status' && $requestId && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = sanitize($_POST['status'] ?? '');
    $adminNotes = $_POST['admin_notes'] ?? '';
    
    if (in_array($newStatus, ['pending', 'in_progress', 'completed', 'cancelled'])) {
        $stmt = $conn->prepare("UPDATE service_requests SET request_status=?, admin_notes=? WHERE id=?");
        $stmt->bind_param("ssi", $newStatus, $adminNotes, $requestId);
        $stmt->execute();
        $stmt->close();
        header("Location: service-requests.php?success=updated&filter=" . $filter);
        exit;
    }
}

// Handle payment status updates
if ($action === 'update_payment' && $requestId && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $paymentStatus = sanitize($_POST['payment_status'] ?? '');
    
    if (in_array($paymentStatus, ['pending', 'completed', 'failed'])) {
        $stmt = $conn->prepare("UPDATE service_requests SET payment_status=? WHERE id=?");
        $stmt->bind_param("si", $paymentStatus, $requestId);
        $stmt->execute();
        $stmt->close();
        header("Location: service-requests.php?success=payment_updated&filter=" . $filter);
        exit;
    }
}

// Get requests
$requests = [];
$statusFilter = $filter === 'all' ? '' : ($filter === 'pending' ? "WHERE sr.request_status = 'pending'" : "WHERE sr.request_status = '$filter'");
$query = "SELECT sr.*, s.title as service_title, s.price, u.first_name, u.last_name, u.email as user_email, sf.name as form_name 
          FROM service_requests sr 
          JOIN services s ON sr.service_id = s.id 
          LEFT JOIN users u ON sr.user_id = u.id 
          LEFT JOIN service_forms sf ON sr.form_id = sf.id 
          $statusFilter 
          ORDER BY sr.created_at DESC";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}

// Statistics
$stats = [];
$result = $conn->query("SELECT COUNT(*) as total FROM service_requests");
$stats['total'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM service_requests WHERE request_status = 'pending'");
$stats['pending'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM service_requests WHERE request_status = 'in_progress'");
$stats['in_progress'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM service_requests WHERE request_status = 'completed'");
$stats['completed'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM service_requests WHERE payment_status = 'pending'");
$stats['payment_pending'] = $result->fetch_assoc()['total'];

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Service Requests - Orchidee LLC</title>
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
        .request-card {
            border-left: 4px solid #007bff;
        }
        .modal-lg {
            max-width: 900px;
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
                            <i class="fa fa-list-alt me-2"></i>Service Requests
                        </h2>
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="fa fa-arrow-left me-2"></i>Back to Dashboard
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
                            <a class="nav-link" href="service-forms.php">
                                <i class="fa fa-wpforms me-2"></i>Service Forms
                            </a>
                            <a class="nav-link active" href="service-requests.php">
                                <i class="fa fa-list-alt me-2"></i>Service Requests
                            </a>
                            <a class="nav-link" href="service-subscriptions.php">
                                <i class="fa fa-sync-alt me-2"></i>Subscriptions
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
                            $messages = [
                                'updated' => 'Request status updated successfully!',
                                'payment_updated' => 'Payment status updated successfully!'
                            ];
                            echo $messages[$_GET['success']] ?? 'Action completed successfully!';
                            ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Statistics Cards -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <div class="bg-white rounded p-3 shadow-sm text-center">
                                <h5 class="text-primary mb-1"><?php echo $stats['total']; ?></h5>
                                <small class="text-muted">Total Requests</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-white rounded p-3 shadow-sm text-center">
                                <h5 class="text-warning mb-1"><?php echo $stats['pending']; ?></h5>
                                <small class="text-muted">Pending</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-white rounded p-3 shadow-sm text-center">
                                <h5 class="text-info mb-1"><?php echo $stats['in_progress']; ?></h5>
                                <small class="text-muted">In Progress</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="bg-white rounded p-3 shadow-sm text-center">
                                <h5 class="text-success mb-1"><?php echo $stats['completed']; ?></h5>
                                <small class="text-muted">Completed</small>
                            </div>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="bg-white rounded p-3 shadow-sm mb-4">
                        <div class="btn-group" role="group">
                            <a href="service-requests.php?filter=all" class="btn btn-outline-primary <?php echo $filter === 'all' ? 'active' : ''; ?>">
                                All (<?php echo $stats['total']; ?>)
                            </a>
                            <a href="service-requests.php?filter=pending" class="btn btn-outline-warning <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                                Pending (<?php echo $stats['pending']; ?>)
                            </a>
                            <a href="service-requests.php?filter=in_progress" class="btn btn-outline-info <?php echo $filter === 'in_progress' ? 'active' : ''; ?>">
                                In Progress (<?php echo $stats['in_progress']; ?>)
                            </a>
                            <a href="service-requests.php?filter=completed" class="btn btn-outline-success <?php echo $filter === 'completed' ? 'active' : ''; ?>">
                                Completed (<?php echo $stats['completed']; ?>)
                            </a>
                        </div>
                    </div>

                    <!-- Requests List -->
                    <div class="bg-white rounded shadow-sm">
                        <?php if (empty($requests)): ?>
                            <div class="p-5 text-center">
                                <i class="fa fa-list-alt fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No service requests found.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Service</th>
                                            <th>Client</th>
                                            <th>Amount</th>
                                            <th>Payment Status</th>
                                            <th>Request Status</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($requests as $req): 
                                            $formData = json_decode($req['form_data'], true);
                                        ?>
                                            <tr>
                                                <td><strong>#<?php echo $req['id']; ?></strong></td>
                                                <td><?php echo htmlspecialchars($req['service_title']); ?></td>
                                                <td>
                                                    <?php if ($req['user_email']): ?>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($req['first_name'] . ' ' . $req['last_name']); ?></strong>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($req['user_email']); ?></small>
                                                        </div>
                                                    <?php else: ?>
                                                        <small class="text-muted">Guest User</small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($req['payment_amount']): ?>
                                                        <strong class="text-success">$<?php echo number_format($req['payment_amount'], 2); ?></strong>
                                                    <?php else: ?>
                                                        <span class="text-muted">N/A</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $req['payment_status'] === 'completed' ? 'success' : 
                                                            ($req['payment_status'] === 'failed' ? 'danger' : 'warning'); 
                                                    ?>">
                                                        <?php echo ucfirst($req['payment_status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $req['request_status'] === 'completed' ? 'success' : 
                                                            ($req['request_status'] === 'in_progress' ? 'info' : 
                                                            ($req['request_status'] === 'cancelled' ? 'danger' : 'warning')); 
                                                    ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $req['request_status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small><?php echo date('M d, Y', strtotime($req['created_at'])); ?></small>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#requestModal<?php echo $req['id']; ?>">
                                                        <i class="fa fa-eye"></i> View
                                                    </button>
                                                </td>
                                            </tr>
                                            
                                            <!-- Modal for Request Details -->
                                            <div class="modal fade" id="requestModal<?php echo $req['id']; ?>" tabindex="-1">
                                                <div class="modal-dialog modal-lg">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Service Request #<?php echo $req['id']; ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <div class="row mb-3">
                                                                <div class="col-md-6">
                                                                    <strong>Service:</strong><br>
                                                                    <?php echo htmlspecialchars($req['service_title']); ?>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <strong>Form:</strong><br>
                                                                    <?php echo htmlspecialchars($req['form_name'] ?? 'N/A'); ?>
                                                                </div>
                                                            </div>
                                                            
                                                            <hr>
                                                            
                                                            <h6 class="mb-3">Form Data:</h6>
                                                            <div class="table-responsive">
                                                                <table class="table table-sm table-bordered">
                                                                    <tbody>
                                                                        <?php foreach ($formData as $key => $value): ?>
                                                                            <tr>
                                                                                <td><strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $key))); ?>:</strong></td>
                                                                                <td><?php echo is_array($value) ? htmlspecialchars(implode(', ', $value)) : nl2br(htmlspecialchars($value)); ?></td>
                                                                            </tr>
                                                                        <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                            
                                                            <hr>
                                                            
                                                            <form method="POST" action="service-requests.php?action=update_status&id=<?php echo $req['id']; ?>&filter=<?php echo $filter; ?>">
                                                                <div class="row">
                                                                    <div class="col-md-6 mb-3">
                                                                        <label class="form-label fw-bold">Request Status</label>
                                                                        <select class="form-select" name="status" required>
                                                                            <option value="pending" <?php echo $req['request_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                            <option value="in_progress" <?php echo $req['request_status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                                            <option value="completed" <?php echo $req['request_status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                                            <option value="cancelled" <?php echo $req['request_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                                        </select>
                                                                    </div>
                                                                    <div class="col-md-6 mb-3">
                                                                        <label class="form-label fw-bold">Payment Status</label>
                                                                        <select class="form-select payment-status-select" 
                                                                                data-request-id="<?php echo $req['id']; ?>" 
                                                                                data-filter="<?php echo $filter; ?>">
                                                                            <option value="pending" <?php echo $req['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                            <option value="completed" <?php echo $req['payment_status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                                            <option value="failed" <?php echo $req['payment_status'] === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">Admin Notes</label>
                                                                    <textarea class="form-control" name="admin_notes" rows="3"><?php echo htmlspecialchars($req['admin_notes'] ?? ''); ?></textarea>
                                                                </div>
                                                                <button type="submit" class="btn btn-primary">Update Status</button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/main.js"></script>
    <script>
        // Handle payment status update
        $(document).on('change', '.payment-status-select', function() {
            const requestId = $(this).data('request-id');
            const filter = $(this).data('filter');
            const paymentStatus = $(this).val();
            
            const form = $('<form>', {
                method: 'POST',
                action: 'service-requests.php?action=update_payment&id=' + requestId + '&filter=' + filter
            });
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'payment_status',
                value: paymentStatus
            }));
            
            $('body').append(form);
            form.submit();
        });
    </script>
</body>
</html>

