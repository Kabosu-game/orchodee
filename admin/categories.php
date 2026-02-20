<?php
require_once '../includes/admin_check.php';

$conn = getDBConnection();
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$categoryId = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);

// Suppression (GET ou POST)
if (($action === 'delete') && $categoryId > 0) {
    $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM courses WHERE category_id = ?");
    $checkStmt->bind_param("i", $categoryId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $checkData = $checkResult->fetch_assoc();
    $checkStmt->close();
    if ($checkData['count'] > 0) {
        $error = "Cannot delete category: It is being used by " . $checkData['count'] . " course(s).";
    } else {
        $stmt = $conn->prepare("DELETE FROM course_categories WHERE id = ?");
        $stmt->bind_param("i", $categoryId);
        if ($stmt->execute()) {
            header("Location: categories.php?success=deleted");
            exit;
        }
        $stmt->close();
        $error = "Error deleting category.";
    }
}

// Traitement des actions POST (ajout / modification)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'add' || $action === 'edit')) {
    if ($action === 'add' || $action === 'edit') {
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        
        if ($action === 'add') {
            // Vérifier si la catégorie existe déjà
            $checkStmt = $conn->prepare("SELECT id FROM course_categories WHERE name = ?");
            $checkStmt->bind_param("s", $name);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $error = "Category with this name already exists.";
            } else {
                $stmt = $conn->prepare("INSERT INTO course_categories (name, description) VALUES (?, ?)");
                $stmt->bind_param("ss", $name, $description);
                if ($stmt->execute()) {
                    header("Location: categories.php?success=added");
                    exit;
                } else {
                    $error = "Error adding category: " . $conn->error;
                }
                $stmt->close();
            }
            $checkStmt->close();
        } else {
            // Vérifier si la catégorie existe déjà (pour un autre ID)
            $checkStmt = $conn->prepare("SELECT id FROM course_categories WHERE name = ? AND id != ?");
            $checkStmt->bind_param("si", $name, $categoryId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $error = "Category with this name already exists.";
            } else {
                $stmt = $conn->prepare("UPDATE course_categories SET name=?, description=? WHERE id=?");
                $stmt->bind_param("ssi", $name, $description, $categoryId);
                if ($stmt->execute()) {
                    header("Location: categories.php?success=updated");
                    exit;
                } else {
                    $error = "Error updating category: " . $conn->error;
                }
                $stmt->close();
            }
            $checkStmt->close();
        }
    }
}

// Récupération des données
$category = null;
if ($action === 'edit' && $categoryId) {
    $stmt = $conn->prepare("SELECT * FROM course_categories WHERE id = ?");
    $stmt->bind_param("i", $categoryId);
    $stmt->execute();
    $result = $stmt->get_result();
    $category = $result->fetch_assoc();
    $stmt->close();
}

// Liste des catégories avec nombre de cours
$categories = [];
$result = $conn->query("
    SELECT c.*, 
    (SELECT COUNT(*) FROM courses WHERE category_id = c.id) as courses_count
    FROM course_categories c 
    ORDER BY c.name ASC
");
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Category Management - Orchidee LLC</title>
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
                            <i class="fa fa-tags me-2"></i>Category Management
                        </h2>
                        <div>
                            <a href="dashboard.php" class="btn btn-outline-secondary me-2">
                                <i class="fa fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                            <a href="categories.php?action=add" class="btn btn-primary">
                                <i class="fa fa-plus me-2"></i>Add New Category
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
                            <a class="nav-link" href="users.php">
                                <i class="fa fa-user-friends me-2"></i>Users
                            </a>
                            <a class="nav-link active" href="categories.php">
                                <i class="fa fa-tags me-2"></i>Categories
                            </a>
                            <a class="nav-link" href="payment-settings.php">
                                <i class="fa fa-credit-card me-2"></i>Payment Settings
                            </a>
                        </nav>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="col-lg-9">
                    <?php if (isset($_GET['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            Category <?php echo $_GET['success']; ?> successfully!
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
                        <div class="bg-white rounded p-4 shadow-sm">
                            <h4 class="mb-4"><?php echo $action === 'add' ? 'Add New' : 'Edit'; ?> Category</h4>
                            
                            <form method="POST" action="categories.php<?php echo $action === 'edit' && $categoryId ? '?action=edit&id=' . $categoryId : '?action=add'; ?>">
                                <input type="hidden" name="action" value="<?php echo $action === 'edit' ? 'edit' : 'add'; ?>">
                                <?php if ($action === 'edit' && $categoryId): ?>
                                    <input type="hidden" name="id" value="<?php echo $categoryId; ?>">
                                <?php endif; ?>
                                <div class="mb-3">
                                    <label for="name" class="form-label">Category Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars(($category ?? [])['name'] ?? ''); ?>" required>
                                    <small class="text-muted">e.g., NCLEX-RN Review, Credential Evaluation</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars(($category ?? [])['description'] ?? ''); ?></textarea>
                                    <small class="text-muted">Brief description of what this category represents</small>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <a href="categories.php" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-save me-2"></i>Save Category
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- List View -->
                        <div class="bg-white rounded p-4 shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4 class="mb-0">All Categories</h4>
                                <div>
                                    <span class="badge bg-primary me-2">Total: <?php echo count($categories); ?></span>
                                </div>
                            </div>
                            
                            <?php if (empty($categories)): ?>
                                <div class="text-center text-muted py-5">
                                    <i class="fa fa-tags fa-3x mb-3 d-block"></i>
                                    <p>No categories yet. <a href="categories.php?action=add">Create your first category</a></p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Description</th>
                                                <th>Courses</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($categories as $cat): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($cat['name']); ?></strong>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars(substr($cat['description'] ?? '', 0, 100)); ?>
                                                        <?php echo strlen($cat['description'] ?? '') > 100 ? '...' : ''; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo $cat['courses_count']; ?> course(s)</span>
                                                    </td>
                                                    <td><?php echo date('M d, Y', strtotime($cat['created_at'])); ?></td>
                                                    <td>
                                                        <a href="categories.php?action=edit&id=<?php echo $cat['id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                                            <i class="fa fa-edit"></i>
                                                        </a>
                                                        <?php if ($cat['courses_count'] == 0): ?>
                                                            <a href="categories.php?action=delete&id=<?php echo $cat['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this category?')">
                                                                <i class="fa fa-trash"></i>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="btn btn-sm btn-secondary disabled" title="Cannot delete: Category is in use">
                                                                <i class="fa fa-lock"></i>
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <!-- Admin Content End -->

    <!-- Footer Start -->
    <div class="container-fluid footer py-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                    <a href="../index.html" class="p-0">
                        <img src="../img/orchideelogo.png" alt="Orchidee LLC" style="height: 40px;">
                    </a>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="text-white-50 mb-0 small">
                        <i class="fas fa-copyright me-1"></i> 2025 Orchidee LLC. All rights reserved.
                    </p>
                </div>
            </div>
        </div>
    </div>
    <!-- Footer End -->

    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/main.js"></script>
</body>
</html>



