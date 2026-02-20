<?php
require_once '../includes/admin_check.php';

$conn = getDBConnection();
$action = $_GET['action'] ?? 'list';
$blogId = $_GET['id'] ?? 0;

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'edit') {
        $title = sanitize($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $excerpt = sanitize($_POST['excerpt'] ?? '');
        $category = sanitize($_POST['category'] ?? '');
        $status = sanitize($_POST['status'] ?? 'draft');
        $authorId = getUserId();
        
        // Handle featured image upload
        $featuredImage = '';
        if (isset($_FILES['featured_image']) && $_FILES['featured_image']['error'] === 0) {
            $uploadDir = '../uploads/blog/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $fileName = time() . '_' . basename($_FILES['featured_image']['name']);
            $targetFile = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['featured_image']['tmp_name'], $targetFile)) {
                $featuredImage = 'uploads/blog/' . $fileName;
            }
        }
        
        if ($action === 'add') {
            if ($featuredImage) {
                $stmt = $conn->prepare("INSERT INTO blog_posts (title, content, excerpt, category, featured_image, status, author_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssi", $title, $content, $excerpt, $category, $featuredImage, $status, $authorId);
            } else {
                $stmt = $conn->prepare("INSERT INTO blog_posts (title, content, excerpt, category, status, author_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssi", $title, $content, $excerpt, $category, $status, $authorId);
            }
        } else {
            if ($featuredImage) {
                $stmt = $conn->prepare("UPDATE blog_posts SET title=?, content=?, excerpt=?, category=?, featured_image=?, status=? WHERE id=?");
                $stmt->bind_param("ssssssi", $title, $content, $excerpt, $category, $featuredImage, $status, $blogId);
            } else {
                $stmt = $conn->prepare("UPDATE blog_posts SET title=?, content=?, excerpt=?, category=?, status=? WHERE id=?");
                $stmt->bind_param("sssssi", $title, $content, $excerpt, $category, $status, $blogId);
            }
        }
        
        if ($stmt->execute()) {
            header("Location: blog.php?success=" . ($action === 'add' ? 'added' : 'updated'));
            exit;
        } else {
            $error = "Error saving blog post.";
        }
        $stmt->close();
    } elseif ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM blog_posts WHERE id = ?");
        $stmt->bind_param("i", $blogId);
        if ($stmt->execute()) {
            header("Location: blog.php?success=deleted");
            exit;
        }
        $stmt->close();
    }
}

// Récupération des données
$blogPost = null;
if ($action === 'edit' && $blogId) {
    $stmt = $conn->prepare("SELECT * FROM blog_posts WHERE id = ?");
    $stmt->bind_param("i", $blogId);
    $stmt->execute();
    $result = $stmt->get_result();
    $blogPost = $result->fetch_assoc();
    $stmt->close();
}

// Liste des articles
$blogPosts = [];
$result = $conn->query("SELECT bp.*, u.first_name, u.last_name FROM blog_posts bp LEFT JOIN users u ON bp.author_id = u.id ORDER BY bp.created_at DESC");
while ($row = $result->fetch_assoc()) {
    $blogPosts[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Blog Management - Orchidee LLC</title>
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
    
    <!-- CKEditor 5 - Éditeur avancé sans API -->
    <script src="https://cdn.ckeditor.com/ckeditor5/41.0.0/classic/ckeditor.js"></script>
    
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
                            <i class="fa fa-blog me-2"></i>Blog Management
                        </h2>
                        <div>
                            <a href="dashboard.php" class="btn btn-outline-secondary me-2">
                                <i class="fa fa-arrow-left me-2"></i>Back to Dashboard
                            </a>
                            <a href="blog.php?action=add" class="btn btn-primary">
                                <i class="fa fa-plus me-2"></i>Add New Post
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
                            <a class="nav-link active" href="blog.php">
                                <i class="fa fa-blog me-2"></i>Blog Management
                            </a>
                            <a class="nav-link" href="webinars.php">
                                <i class="fa fa-video me-2"></i>Webinars
                            </a>
                            <a class="nav-link" href="sessions.php">
                                <i class="fa fa-calendar-check me-2"></i>NCLEX Sessions
                            </a>
                            <a class="nav-link" href="users.php">
                                <i class="fa fa-users me-2"></i>Users
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
                            Blog post <?php echo $_GET['success']; ?> successfully!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($action === 'add' || $action === 'edit'): ?>
                        <!-- Add/Edit Form -->
                        <div class="bg-white rounded p-4 shadow-sm">
                            <h4 class="mb-4"><?php echo $action === 'add' ? 'Add New' : 'Edit'; ?> Blog Post</h4>
                            
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($blogPost['title'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="excerpt" class="form-label">Excerpt</label>
                                    <textarea class="form-control" id="excerpt" name="excerpt" rows="3"><?php echo htmlspecialchars($blogPost['excerpt'] ?? ''); ?></textarea>
                                    <small class="text-muted">Short description for preview</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="content" class="form-label">Content *</label>
                                    <textarea class="form-control" id="content" name="content" rows="15"><?php echo $blogPost['content'] ?? ''; ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="category" class="form-label">Category</label>
                                        <select class="form-select" id="category" name="category">
                                            <option value="NCLEX Tips" <?php echo ($blogPost['category'] ?? '') === 'NCLEX Tips' ? 'selected' : ''; ?>>NCLEX Tips</option>
                                            <option value="Licensure" <?php echo ($blogPost['category'] ?? '') === 'Licensure' ? 'selected' : ''; ?>>Licensure</option>
                                            <option value="Success Story" <?php echo ($blogPost['category'] ?? '') === 'Success Story' ? 'selected' : ''; ?>>Success Story</option>
                                            <option value="News" <?php echo ($blogPost['category'] ?? '') === 'News' ? 'selected' : ''; ?>>News</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="status" class="form-label">Status</label>
                                        <select class="form-select" id="status" name="status">
                                            <option value="draft" <?php echo ($blogPost['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>Draft</option>
                                            <option value="published" <?php echo ($blogPost['status'] ?? '') === 'published' ? 'selected' : ''; ?>>Published</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="featured_image" class="form-label">Featured Image</label>
                                    <input type="file" class="form-control" id="featured_image" name="featured_image" accept="image/*">
                                    <?php if (!empty($blogPost['featured_image'])): ?>
                                        <img src="../<?php echo htmlspecialchars($blogPost['featured_image']); ?>" alt="Featured" class="img-thumbnail mt-2" style="max-width: 200px;">
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <a href="blog.php" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa fa-save me-2"></i>Save Post
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- List View -->
                        <div class="bg-white rounded p-4 shadow-sm">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4 class="mb-0">All Blog Posts</h4>
                                <a href="blog.php?action=add" class="btn btn-primary btn-sm">
                                    <i class="fa fa-plus me-2"></i>Add New
                                </a>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Title</th>
                                            <th>Category</th>
                                            <th>Author</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($blogPosts)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">No blog posts yet</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($blogPosts as $post): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($post['title']); ?></td>
                                                    <td><?php echo htmlspecialchars($post['category']); ?></td>
                                                    <td><?php echo htmlspecialchars(($post['first_name'] ?? '') . ' ' . ($post['last_name'] ?? '')); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $post['status'] === 'published' ? 'success' : 'warning'; ?>">
                                                            <?php echo ucfirst($post['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('M d, Y', strtotime($post['created_at'])); ?></td>
                                                    <td>
                                                        <a href="blog.php?action=edit&id=<?php echo $post['id']; ?>" class="btn btn-sm btn-primary">
                                                            <i class="fa fa-edit"></i>
                                                        </a>
                                                        <a href="blog.php?action=delete&id=<?php echo $post['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">
                                                            <i class="fa fa-trash"></i>
                                                        </a>
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
    
    <script>
        // Initialize CKEditor 5
        ClassicEditor
            .create(document.querySelector('#content'), {
                toolbar: {
                    items: [
                        'heading', '|',
                        'bold', 'italic', 'underline', 'strikethrough', '|',
                        'fontSize', 'fontFamily', 'fontColor', 'fontBackgroundColor', '|',
                        'bulletedList', 'numberedList', '|',
                        'alignment', '|',
                        'link', 'insertImage', 'insertTable', 'blockQuote', '|',
                        'undo', 'redo', '|',
                        'sourceEditing'
                    ],
                    shouldNotGroupWhenFull: true
                },
                heading: {
                    options: [
                        { model: 'paragraph', title: 'Paragraph', class: 'ck-heading_paragraph' },
                        { model: 'heading1', view: 'h1', title: 'Heading 1', class: 'ck-heading_heading1' },
                        { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
                        { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' },
                        { model: 'heading4', view: 'h4', title: 'Heading 4', class: 'ck-heading_heading4' }
                    ]
                },
                fontSize: {
                    options: [9, 11, 13, 'default', 17, 19, 21, 24, 32]
                },
                fontFamily: {
                    options: [
                        'default',
                        'Arial, Helvetica, sans-serif',
                        'Courier New, Courier, monospace',
                        'Georgia, serif',
                        'Lucida Sans Unicode, Lucida Grande, sans-serif',
                        'Tahoma, Geneva, sans-serif',
                        'Times New Roman, Times, serif',
                        'Trebuchet MS, Helvetica, sans-serif',
                        'Verdana, Geneva, sans-serif'
                    ]
                },
                link: {
                    decorators: {
                        openInNewTab: {
                            mode: 'manual',
                            label: 'Open in a new tab',
                            attributes: {
                                target: '_blank',
                                rel: 'noopener noreferrer'
                            }
                        }
                    }
                },
                image: {
                    toolbar: [
                        'imageStyle:inline',
                        'imageStyle:block',
                        'imageStyle:side',
                        '|',
                        'toggleImageCaption',
                        'imageTextAlternative',
                        '|',
                        'linkImage'
                    ]
                },
                table: {
                    contentToolbar: [
                        'tableColumn',
                        'tableRow',
                        'mergeTableCells',
                        'tableCellProperties',
                        'tableProperties'
                    ]
                },
                language: 'en',
                placeholder: 'Rédigez votre contenu ici...'
            })
            .then(editor => {
                console.log('CKEditor 5 initialized successfully', editor);
                window.editor = editor; // Garder une référence globale
            })
            .catch(error => {
                console.error('Error initializing CKEditor 5:', error);
            });
    </script>
</body>
</html>

