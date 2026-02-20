<?php
$isEdit = $action === 'edit';
$pageTitle = $isEdit ? 'Edit Course' : 'Add New Course';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo $pageTitle; ?> - Admin - Orchidee LLC</title>
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
</head>
<body>
    <!-- Spinner Start -->
    <div id="spinner" class="bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center" style="display: none !important;">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>
    <!-- Spinner End -->

    <!-- Menu -->
    <?php include '../includes/menu-dynamic.php'; ?>

    <!-- Course Form Start -->
    <div class="container-fluid bg-light py-5">
        <div class="container py-5">
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="text-primary mb-0">
                            <i class="fa fa-<?php echo $isEdit ? 'edit' : 'plus'; ?> me-2"></i><?php echo $pageTitle; ?>
                        </h2>
                        <a href="courses.php" class="btn btn-outline-secondary">
                            <i class="fa fa-arrow-left me-2"></i>Back to Courses
                        </a>
                    </div>
                </div>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="bg-white rounded p-5 shadow-sm">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Course Title <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="title" name="title" required value="<?php echo htmlspecialchars($course['title'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="short_description" class="form-label">Short Description</label>
                                        <textarea class="form-control" id="short_description" name="short_description" rows="2"><?php echo htmlspecialchars($course['short_description'] ?? ''); ?></textarea>
                                        <small class="text-muted">Brief description (max 500 characters)</small>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Full Description <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="description" name="description" rows="6" required><?php echo htmlspecialchars($course['description'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="image" class="form-label">Course Image</label>
                                        <?php if ($isEdit && !empty($course['image'])): ?>
                                            <div class="mb-2">
                                                <img src="../<?php echo htmlspecialchars($course['image']); ?>" alt="Current image" class="img-fluid rounded" style="max-height: 200px;">
                                            </div>
                                        <?php endif; ?>
                                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                        <small class="text-muted">Recommended: 800x450px</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="category_id" class="form-label">Category</label>
                                    <select class="form-select" id="category_id" name="category_id">
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>" <?php echo (isset($course['category_id']) && $course['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="price" class="form-label">Price ($) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required value="<?php echo htmlspecialchars($course['price'] ?? '0.00'); ?>">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="instructor_name" class="form-label">Instructor Name</label>
                                    <input type="text" class="form-control" id="instructor_name" name="instructor_name" value="<?php echo htmlspecialchars($course['instructor_name'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="duration_hours" class="form-label">Duration (Hours)</label>
                                    <input type="number" class="form-control" id="duration_hours" name="duration_hours" min="0" value="<?php echo htmlspecialchars($course['duration_hours'] ?? '0'); ?>">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="level" class="form-label">Level</label>
                                    <select class="form-select" id="level" name="level">
                                        <option value="beginner" <?php echo (isset($course['level']) && $course['level'] === 'beginner') ? 'selected' : ''; ?>>Beginner</option>
                                        <option value="intermediate" <?php echo (!isset($course['level']) || $course['level'] === 'intermediate') ? 'selected' : ''; ?>>Intermediate</option>
                                        <option value="expert" <?php echo (isset($course['level']) && $course['level'] === 'expert') ? 'selected' : ''; ?>>Expert</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="draft" <?php echo (!isset($course['status']) || $course['status'] === 'draft') ? 'selected' : ''; ?>>Draft</option>
                                    <option value="published" <?php echo (isset($course['status']) && $course['status'] === 'published') ? 'selected' : ''; ?>>Published</option>
                                </select>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-save me-2"></i>Save Course
                                </button>
                                <a href="courses.php" class="btn btn-outline-secondary">Cancel</a>
                                <?php if ($isEdit): ?>
                                    <a href="course-chapters.php?course_id=<?php echo $courseId; ?>" class="btn btn-info">
                                        <i class="fa fa-list me-2"></i>Manage Chapters
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Course Form End -->

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

