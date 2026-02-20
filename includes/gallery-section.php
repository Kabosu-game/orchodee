<?php
$gallery = [];
$dbPath = __DIR__ . '/../config/database.php';
if (file_exists($dbPath)) {
    try {
        require_once $dbPath;
        if (function_exists('getDBConnection')) {
            $conn = getDBConnection();
            $tbl = @$conn->query("SHOW TABLES LIKE 'gallery'");
            if ($tbl && $tbl->num_rows > 0) {
                $r = $conn->query("SELECT * FROM gallery WHERE status = 'active' ORDER BY display_order ASC, id ASC");
                if ($r) {
                    while ($row = $r->fetch_assoc()) $gallery[] = $row;
                }
                $conn->close();
            }
        }
    } catch (Exception $e) { /* ignore */ }
}
?>
<?php if (!empty($gallery)): ?>
<section class="gallery-section py-5">
    <div class="container py-5">
        <div class="text-center mx-auto mb-5 wow fadeInUp" data-wow-delay="0.2s" style="max-width: 700px;">
            <h4 class="text-primary text-uppercase fw-bold mb-2">Gallery</h4>
            <h1 class="display-5 mb-3 fw-bold">Our Moments</h1>
            <p class="lead text-muted">Discover our community, events, and achievements.</p>
        </div>
        <div class="gallery-grid">
            <?php foreach ($gallery as $g):
                $imgPath = htmlspecialchars($g['image_path'] ?? '', ENT_QUOTES, 'UTF-8');
                $title = htmlspecialchars($g['title'] ?? '', ENT_QUOTES, 'UTF-8');
                $caption = htmlspecialchars($g['caption'] ?? '', ENT_QUOTES, 'UTF-8');
            ?>
            <a href="<?php echo $imgPath; ?>" class="gallery-item" data-lightbox="gallery" data-title="<?php echo $title ?: $caption; ?>">
                <div class="gallery-item-inner">
                    <img src="<?php echo $imgPath; ?>" alt="<?php echo $title; ?>" loading="lazy" onerror="this.style.display='none'">
                    <div class="gallery-item-overlay">
                        <?php if ($title): ?><span class="gallery-item-title"><?php echo $title; ?></span><?php endif; ?>
                        <?php if ($caption): ?><span class="gallery-item-caption"><?php echo $caption; ?></span><?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>
