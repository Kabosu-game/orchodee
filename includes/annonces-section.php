<?php
$annonces = [];
$dbPath = __DIR__ . '/../config/database.php';
if (file_exists($dbPath)) {
    try {
        require_once $dbPath;
        if (function_exists('getDBConnection')) {
            $conn = getDBConnection();
            $tbl = @$conn->query("SHOW TABLES LIKE 'annonces'");
            if ($tbl && $tbl->num_rows > 0) {
                $r = $conn->query("SELECT * FROM annonces WHERE status = 'active' ORDER BY display_order ASC, id ASC");
                if ($r) {
                    while ($row = $r->fetch_assoc()) $annonces[] = $row;
                }
                $conn->close();
            }
        }
    } catch (Exception $e) { /* ignore */ }
}
?>
<?php if (!empty($annonces)): ?>
<div class="py-4 bg-light">
    <div class="container">
        <div class="annonces-simple owl-carousel">
            <?php foreach ($annonces as $a): 
                $mediaPath = htmlspecialchars($a['file_path'] ?? '', ENT_QUOTES, 'UTF-8');
                $title = htmlspecialchars($a['title'] ?? '', ENT_QUOTES, 'UTF-8');
                $link = !empty($a['link_url']) ? htmlspecialchars($a['link_url'], ENT_QUOTES, 'UTF-8') : '';
            ?>
            <div class="annonces-simple-item">
                <?php if ($link): ?><a href="<?php echo $link; ?>" target="_blank" rel="noopener"><?php endif; ?>
                <?php if (($a['type'] ?? '') === 'image'): ?>
                    <img src="<?php echo $mediaPath; ?>" alt="<?php echo $title; ?>" class="annonces-simple-img">
                <?php else: ?>
                    <video class="annonces-simple-img" muted loop playsinline><source src="<?php echo $mediaPath; ?>" type="video/mp4"></video>
                <?php endif; ?>
                <?php if ($link): ?></a><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>
