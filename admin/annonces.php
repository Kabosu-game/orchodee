<?php
require_once '../includes/admin_check.php';

$conn = getDBConnection();

// Créer la table si elle n'existe pas
$tableCheck = $conn->query("SHOW TABLES LIKE 'annonces'");
if ($tableCheck->num_rows === 0) {
    $conn->query("CREATE TABLE IF NOT EXISTS annonces (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type ENUM('image','video') NOT NULL DEFAULT 'image',
        file_path VARCHAR(500) NOT NULL,
        title VARCHAR(255) DEFAULT NULL,
        link_url VARCHAR(500) DEFAULT NULL,
        display_order INT DEFAULT 0,
        status ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$uploadError = '';

// Suppression
if ($action === 'delete' && $id > 0) {
    $stmt = $conn->prepare("SELECT file_path FROM annonces WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row && file_exists('../' . $row['file_path'])) {
        @unlink('../' . $row['file_path']);
    }
    $stmt = $conn->prepare("DELETE FROM annonces WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    header("Location: annonces.php?success=deleted");
    exit;
}

// POST add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = in_array($_POST['type'] ?? '', ['image','video']) ? $_POST['type'] : 'image';
    $title = sanitize($_POST['title'] ?? '');
    $linkUrl = sanitize($_POST['link_url'] ?? '');
    $displayOrder = intval($_POST['display_order'] ?? 0);
    $status = in_array($_POST['status'] ?? '', ['active','inactive']) ? $_POST['status'] : 'active';
    
    $filePath = '';
    if ($action === 'edit' && $id) {
        $stmt = $conn->prepare("SELECT file_path FROM annonces WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $filePath = $row['file_path'] ?? '';
        $stmt->close();
    }
    
    $uploadError = '';
    if (!isset($_FILES['media']) && $action === 'add') {
        $uploadError = 'No file received. If your video is large, increase post_max_size and upload_max_filesize in php.ini.';
    } elseif (isset($_FILES['media']) && $_FILES['media']['name']) {
        $err = $_FILES['media']['error'];
        if ($err === 0) {
            $ext = strtolower(pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION));
            $allowedImage = ['jpg','jpeg','png','gif','webp'];
            $allowedVideo = ['mp4','webm','ogg','mov'];
            $allowed = ($type === 'image') ? $allowedImage : $allowedVideo;
            if (in_array($ext, $allowed)) {
                $dir = $type === 'image' ? '../uploads/annonces/' : '../uploads/annonces/videos/';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['media']['name']);
                $target = $dir . $fileName;
                if (move_uploaded_file($_FILES['media']['tmp_name'], $target)) {
                    if ($filePath && file_exists('../' . $filePath)) @unlink('../' . $filePath);
                    $filePath = str_replace('../', '', $target);
                } else {
                    $uploadError = 'Could not save the file. Check folder permissions.';
                }
            } else {
                $uploadError = ($type === 'video') ? 'Invalid format. Use mp4, webm or ogg.' : 'Invalid format. Use jpg, png, gif or webp.';
            }
        } else {
            $errors = [
                1 => 'File too large. Edit C:\\wamp64\\bin\\php\\phpX.X.XX\\php.ini: upload_max_filesize=64M and post_max_size=64M, then restart WAMP.',
                2 => 'File too large (form max).',
                3 => 'Upload interrupted.',
                4 => 'No file selected.',
                6 => 'Temp folder missing.',
                7 => 'Write failed.',
                8 => 'Extension stopped upload.'
            ];
            $uploadError = $errors[$err] ?? 'Upload error (code ' . $err . ').';
        }
    } elseif ($action === 'add' && empty($uploadError)) {
        $uploadError = 'Please select a file. For large videos, edit php.ini (upload_max_filesize, post_max_size) and restart Apache.';
    }
    
    if ($filePath) {
        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO annonces (type, file_path, title, link_url, display_order, status) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("ssssis", $type, $filePath, $title, $linkUrl, $displayOrder, $status);
        } else {
            $stmt = $conn->prepare("UPDATE annonces SET type=?, file_path=?, title=?, link_url=?, display_order=?, status=? WHERE id=?");
            $stmt->bind_param("ssssisi", $type, $filePath, $title, $linkUrl, $displayOrder, $status, $id);
        }
        if ($stmt->execute()) {
            header("Location: annonces.php?success=" . ($action === 'add' ? 'added' : 'updated'));
            exit;
        }
    } elseif ($action === 'edit' && $id && $filePath) {
        $stmt = $conn->prepare("UPDATE annonces SET type=?, file_path=?, title=?, link_url=?, display_order=?, status=? WHERE id=?");
        $stmt->bind_param("ssssisi", $type, $filePath, $title, $linkUrl, $displayOrder, $status, $id);
        if ($stmt->execute()) {
            header("Location: annonces.php?success=updated");
            exit;
        }
    } elseif ($action === 'edit' && $id) {
        $stmt = $conn->prepare("UPDATE annonces SET title=?, link_url=?, display_order=?, status=? WHERE id=?");
        $stmt->bind_param("ssisi", $title, $linkUrl, $displayOrder, $status, $id);
        if ($stmt->execute()) {
            header("Location: annonces.php?success=updated");
            exit;
        }
    }
    if ($uploadError && $action === 'add') {
        $_GET['upload_error'] = $uploadError;
    } elseif ($uploadError && $action === 'edit') {
        $_GET['upload_error'] = $uploadError;
    }
}

$item = null;
if (($action === 'edit') && $id) {
    $stmt = $conn->prepare("SELECT * FROM annonces WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($uploadError) && $uploadError !== '') {
    $item = $item ?? [];
    $item['type'] = $_POST['type'] ?? 'image';
    $item['title'] = $_POST['title'] ?? '';
    $item['link_url'] = $_POST['link_url'] ?? '';
    $item['display_order'] = $_POST['display_order'] ?? 0;
    $item['status'] = $_POST['status'] ?? 'active';
}

$items = [];
$r = $conn->query("SELECT * FROM annonces ORDER BY display_order ASC, id DESC");
while ($row = $r->fetch_assoc()) $items[] = $row;
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Announcements - Admin | Orchidee LLC</title>
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
                    <h2 class="text-primary mb-0"><i class="fa fa-bullhorn me-2"></i>Announcements</h2>
                    <a href="dashboard.php" class="btn btn-outline-secondary"><i class="fa fa-arrow-left me-2"></i>Dashboard</a>
                </div>
            </div>
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">Annonce <?php echo htmlspecialchars($_GET['success']); ?> avec succès.</div>
            <?php endif; ?>
            <?php if (!empty($_GET['upload_error'])): ?>
                <div class="alert alert-danger"><i class="fa fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($_GET['upload_error']); ?></div>
            <?php endif; ?>
            <?php if ($action === 'add' || $action === 'edit'): ?>
                <div class="bg-white rounded p-4 shadow-sm mb-4">
                    <h4><?php echo $action === 'add' ? 'Add Announcement' : 'Edit Announcement'; ?></h4>
                    <form method="POST" enctype="multipart/form-data" action="annonces.php?action=<?php echo $action; ?><?php echo $id ? '&id='.$id : ''; ?>">
                        <div class="mb-3">
                            <label class="form-label">Type *</label>
                            <select name="type" class="form-select" required>
                                <option value="image" <?php echo ($item['type'] ?? '') === 'image' ? 'selected' : ''; ?>>Image</option>
                                <option value="video" <?php echo ($item['type'] ?? '') === 'video' ? 'selected' : ''; ?>>Video</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Photo or video file <?php echo $action === 'edit' ? '(leave empty to keep current)' : '*'; ?></label>
                            <input type="file" name="media" id="mediaInput" class="form-control" accept="image/*,video/mp4,video/webm,video/ogg,video/quicktime" <?php echo $action === 'add' ? 'required' : ''; ?>>
                            <small class="text-muted d-block">Images: jpg, png, gif, webp. Videos: mp4, webm, ogg. Current limit: <?php echo ini_get('upload_max_filesize'); ?>.</small>
                            <details class="mt-2"><summary class="text-muted small" style="cursor:pointer">Video upload fails? Click for WAMP fix</summary>
                            <ol class="small text-muted mt-1 mb-0 ps-3"><li>WAMP icon → PHP → php.ini</li><li>Find upload_max_filesize and post_max_size</li><li>Set both to 64M</li><li>Save, then WAMP → Restart Apache</li></ol></details>
                            <?php if ($action === 'edit' && !empty($item['file_path'])): ?>
                                <p class="mt-2 mb-0 small">Current: <?php echo htmlspecialchars($item['file_path']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Title (optional)</label>
                            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($item['title'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Link (optional)</label>
                            <input type="url" name="link_url" class="form-control" placeholder="https://..." value="<?php echo htmlspecialchars($item['link_url'] ?? ''); ?>">
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
                        <div>
                            <a href="annonces.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary"><i class="fa fa-save me-2"></i>Save</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
            <div class="bg-white rounded p-4 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="mb-0">Announcements List</h4>
                    <a href="annonces.php?action=add" class="btn btn-primary"><i class="fa fa-plus me-2"></i>Add</a>
                </div>
                <?php if (empty($items)): ?>
                    <p class="text-muted">No announcements. <a href="annonces.php?action=add">Add the first one</a>.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>Type</th><th>Preview</th><th>Title</th><th>Order</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($items as $a): ?>
                                    <tr>
                                        <td><span class="badge bg-<?php echo $a['type'] === 'video' ? 'info' : 'secondary'; ?>"><?php echo $a['type']; ?></span></td>
                                        <td>
                                            <?php if ($a['type'] === 'image'): ?>
                                                <img src="../<?php echo htmlspecialchars($a['file_path']); ?>" alt="" style="max-width:80px;max-height:50px;object-fit:cover;" onerror="this.style.display='none'">
                                            <?php else: ?>
                                                <i class="fa fa-video text-info"></i> Video
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($a['title'] ?? '-'); ?></td>
                                        <td><?php echo $a['display_order']; ?></td>
                                        <td><span class="badge bg-<?php echo $a['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo $a['status']; ?></span></td>
                                        <td>
                                            <a href="annonces.php?action=edit&id=<?php echo $a['id']; ?>" class="btn btn-sm btn-primary"><i class="fa fa-edit"></i></a>
                                            <a href="annonces.php?action=delete&id=<?php echo $a['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this announcement?');"><i class="fa fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
