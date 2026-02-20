<?php
require_once '../includes/admin_check.php';

$conn = getDBConnection();

$tableCheck = $conn->query("SHOW TABLES LIKE 'gallery'");
if ($tableCheck->num_rows === 0) {
    $conn->query("CREATE TABLE IF NOT EXISTS gallery (
        id INT AUTO_INCREMENT PRIMARY KEY,
        image_path VARCHAR(500) NOT NULL,
        title VARCHAR(255) DEFAULT NULL,
        caption VARCHAR(500) DEFAULT NULL,
        display_order INT DEFAULT 0,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$uploadError = '';

if ($action === 'delete' && $id > 0) {
    $stmt = $conn->prepare("SELECT image_path FROM gallery WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row && file_exists('../' . $row['image_path'])) {
        @unlink('../' . $row['image_path']);
    }
    $stmt = $conn->prepare("DELETE FROM gallery WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: gallery.php?success=deleted");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title'] ?? '');
    $caption = sanitize($_POST['caption'] ?? '');
    $displayOrder = intval($_POST['display_order'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';

    $filePath = '';
    if ($action === 'edit' && $id) {
        $stmt = $conn->prepare("SELECT image_path FROM gallery WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $filePath = $row['image_path'] ?? '';
        $stmt->close();
    }

    $uploadError = '';
    if (isset($_FILES['image']) && $_FILES['image']['name']) {
        $err = $_FILES['image']['error'];
        if ($err === 0) {
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (in_array($ext, $allowed)) {
                $dir = '../uploads/gallery/';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['image']['name']);
                $target = $dir . $fileName;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                    if ($filePath && file_exists('../' . $filePath)) @unlink('../' . $filePath);
                    $filePath = str_replace('../', '', $target);
                } else {
                    $uploadError = 'Could not save the file. Check folder permissions.';
                }
            } else {
                $uploadError = 'Invalid format. Use jpg, png, gif or webp.';
            }
        } else {
            $uploadError = $err == 1 ? 'File too large.' : 'Upload error.';
        }
    }

    if ($filePath) {
        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO gallery (image_path, title, caption, display_order, status) VALUES (?,?,?,?,?)");
            $stmt->bind_param("sssis", $filePath, $title, $caption, $displayOrder, $status);
        } else {
            $stmt = $conn->prepare("UPDATE gallery SET image_path=?, title=?, caption=?, display_order=?, status=? WHERE id=?");
            $stmt->bind_param("sssisi", $filePath, $title, $caption, $displayOrder, $status, $id);
        }
        if ($stmt->execute()) {
            header("Location: gallery.php?success=" . ($action === 'add' ? 'added' : 'updated'));
            exit;
        }
    } elseif ($action === 'edit' && $id) {
        $stmt = $conn->prepare("UPDATE gallery SET title=?, caption=?, display_order=?, status=? WHERE id=?");
        $stmt->bind_param("ssisi", $title, $caption, $displayOrder, $status, $id);
        if ($stmt->execute()) {
            header("Location: gallery.php?success=updated");
            exit;
        }
    }
    if ($uploadError) $_GET['upload_error'] = $uploadError;
}

$item = null;
if ($action === 'edit' && $id) {
    $stmt = $conn->prepare("SELECT * FROM gallery WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($uploadError)) {
    $item = $item ?? [];
    $item['title'] = $_POST['title'] ?? '';
    $item['caption'] = $_POST['caption'] ?? '';
    $item['display_order'] = $_POST['display_order'] ?? 0;
    $item['status'] = $_POST['status'] ?? 'active';
}

$items = [];
$r = $conn->query("SELECT * FROM gallery ORDER BY display_order ASC, id DESC");
if ($r) while ($row = $r->fetch_assoc()) $items[] = $row;
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Gallery - Admin | Orchidee LLC</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"/>
    <link href="../css/bootstrap.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/menu-dynamic.php'; ?>
    <div class="container-fluid bg-light py-5">
        <div class="container py-5">
            <div class="row mb-4">
                <div class="col-12 d-flex justify-content-between align-items-center">
                    <h2 class="text-primary mb-0"><i class="fa fa-images me-2"></i>Gallery</h2>
                    <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fa fa-arrow-left me-2"></i>Dashboard</a>
                </div>
            </div>
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">Item <?php echo htmlspecialchars($_GET['success']); ?> successfully.</div>
            <?php endif; ?>
            <?php if (!empty($_GET['upload_error'])): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['upload_error']); ?></div>
            <?php endif; ?>
            <?php if ($action === 'add' || $action === 'edit'): ?>
                <div class="bg-white rounded p-4 shadow-sm mb-4">
                    <h4><?php echo $action === 'add' ? 'Add Image' : 'Edit Image'; ?></h4>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Image <?php echo $action === 'edit' ? '(leave empty to keep)' : '*'; ?></label>
                            <input type="file" name="image" class="form-control" accept="image/*" <?php echo $action === 'add' ? 'required' : ''; ?>>
                            <small class="text-muted">jpg, png, gif, webp</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Title (optional)</label>
                            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($item['title'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Caption (optional)</label>
                            <input type="text" name="caption" class="form-control" value="<?php echo htmlspecialchars($item['caption'] ?? ''); ?>">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Display order</label>
                                <input type="number" name="display_order" class="form-control" value="<?php echo (int)($item['display_order'] ?? 0); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="active" <?php echo ($item['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($item['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                        <a href="gallery.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary"><i class="fa fa-save me-2"></i>Save</button>
                    </form>
                </div>
            <?php endif; ?>
            <div class="bg-white rounded p-4 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">Gallery List</h4>
                    <a href="gallery.php?action=add" class="btn btn-primary"><i class="fa fa-plus me-2"></i>Add Image</a>
                </div>
                <?php if (empty($items)): ?>
                    <p class="text-muted">No images. <a href="gallery.php?action=add">Add the first one</a>.</p>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($items as $g): ?>
                        <div class="col-md-4 col-lg-3">
                            <div class="card h-100">
                                <img src="../<?php echo htmlspecialchars($g['image_path']); ?>" class="card-img-top" style="height:180px;object-fit:cover;" alt="">
                                <div class="card-body p-2">
                                    <small class="text-muted"><?php echo htmlspecialchars($g['title'] ?? '-'); ?></small>
                                </div>
                                <div class="card-footer p-2">
                                    <a href="gallery.php?action=edit&id=<?php echo $g['id']; ?>" class="btn btn-sm btn-primary"><i class="fa fa-edit"></i></a>
                                    <a href="gallery.php?action=delete&id=<?php echo $g['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this image?');"><i class="fa fa-trash"></i></a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
