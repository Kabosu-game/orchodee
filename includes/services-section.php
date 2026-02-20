<?php
// Gestion d'erreurs complète pour éviter tout plantage
$services = [];
$section_style = isset($section_style) ? $section_style : 'default';

// Vérifier si les variables nécessaires sont définies
$services_limit = isset($services_limit) ? intval($services_limit) : null;

// Tentative de chargement des services depuis la base de données
if (file_exists(__DIR__ . '/../config/database.php')) {
    try {
        require_once __DIR__ . '/../config/database.php';
        
        if (function_exists('getDBConnection')) {
            $conn = @getDBConnection();
            
            if ($conn && is_object($conn) && method_exists($conn, 'query')) {
                // Vérifier si la table existe
                $tableCheck = @$conn->query("SHOW TABLES LIKE 'services'");
                if ($tableCheck && $tableCheck->num_rows === 0) {
                    // Créer la table
                    $createTableSQL = "CREATE TABLE IF NOT EXISTS services (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        title VARCHAR(255) NOT NULL,
                        description TEXT DEFAULT NULL,
                        image VARCHAR(255) DEFAULT NULL,
                        icon VARCHAR(100) DEFAULT NULL,
                        price DECIMAL(10, 2) DEFAULT NULL,
                        display_order INT DEFAULT 0,
                        status ENUM('active', 'inactive') DEFAULT 'active',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        KEY status (status),
                        KEY display_order (display_order)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    
                    @$conn->query($createTableSQL);
                }
                
                // Récupérer les services
                if ($services_limit && $services_limit > 0) {
                    $limit = intval($services_limit);
                    $query = "SELECT * FROM services WHERE status = 'active' ORDER BY display_order ASC, created_at DESC LIMIT " . intval($limit);
                    $result = @$conn->query($query);
                } else {
                    $query = "SELECT * FROM services WHERE status = 'active' ORDER BY display_order ASC, created_at DESC";
                    $result = @$conn->query($query);
                }
                
                if ($result && method_exists($result, 'fetch_assoc')) {
                    while ($row = $result->fetch_assoc()) {
                        $services[] = $row;
                    }
                }
                
                if ($conn && method_exists($conn, 'close')) {
                    @$conn->close();
                }
            }
        }
    } catch (Exception $e) {
        // En cas d'erreur, continuer sans services
        error_log("Services section error: " . $e->getMessage());
    } catch (Error $e) {
        // Gérer aussi les erreurs fatales PHP 7+
        error_log("Services section fatal error: " . $e->getMessage());
    }
}
?>

<?php if (empty($services)): ?>
    <div class="col-12 text-center py-5">
        <p class="text-muted">No services available at the moment. Please check back later.</p>
    </div>
<?php else: ?>
    <?php 
    $delay = 0.2;
    $colors = ['#007bff', '#28a745', '#ffc107', '#17a2b8', '#dc3545', '#6f42c1'];
    $colorIndex = 0;
    foreach ($services as $service): 
        $color = $colors[$colorIndex % count($colors)];
        $colorIndex++;
        
        // Sécuriser les valeurs
        $title = isset($service['title']) ? htmlspecialchars($service['title'], ENT_QUOTES, 'UTF-8') : '';
        $description = isset($service['description']) ? $service['description'] : '';
        $image = isset($service['image']) ? htmlspecialchars($service['image'], ENT_QUOTES, 'UTF-8') : '';
        $icon = isset($service['icon']) ? htmlspecialchars($service['icon'], ENT_QUOTES, 'UTF-8') : '';
        $price = isset($service['price']) && $service['price'] !== null ? floatval($service['price']) : null;
    ?>
        <?php if ($section_style === 'compact'): ?>
            <!-- Style compact pour index.php -->
            <div class="col-md-6 col-lg-4 wow fadeInUp" data-wow-delay="<?php echo $delay; ?>s">
                <div class="h-100 bg-white rounded shadow-lg overflow-hidden position-relative" style="transition: all 0.3s ease; border-top: 4px solid <?php echo $color; ?>;">
                    <div class="position-relative" style="height: 200px; overflow: hidden;">
                        <?php if (!empty($image)): ?>
                            <img src="<?php echo $image; ?>" alt="<?php echo $title; ?>" class="w-100 h-100" style="object-fit: cover;" onerror="this.src='img/S<?php echo ($colorIndex % 6) + 1; ?>.jpeg';">
                        <?php else: ?>
                            <img src="img/S<?php echo ($colorIndex % 6) + 1; ?>.jpeg" alt="<?php echo $title; ?>" class="w-100 h-100" style="object-fit: cover;" onerror="this.style.display='none';">
                        <?php endif; ?>
                    </div>
                    <div class="p-4 position-relative">
                        <?php if (!empty($title)): ?>
                            <h5 class="fw-bold mb-3"><?php echo $title; ?></h5>
                        <?php endif; ?>
                        <?php if ($price !== null && $price > 0): ?>
                            <div class="mb-2">
                                <span class="h6 text-primary fw-bold">$<?php echo number_format($price, 2); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($description)): ?>
                            <p class="text-muted mb-0 small"><?php 
                                $desc = strlen($description) > 150 ? substr($description, 0, 150) . '...' : $description;
                                echo nl2br(htmlspecialchars($desc, ENT_QUOTES, 'UTF-8')); 
                            ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Style default pour service.php -->
            <div class="col-md-6 col-lg-6 col-xl-3 wow fadeInUp" data-wow-delay="<?php echo $delay; ?>s">
                <div class="service-item">
                    <div class="service-img">
                        <?php if (!empty($image)): ?>
                            <img src="<?php echo $image; ?>" class="img-fluid rounded-top w-100" alt="<?php echo $title; ?>" onerror="this.src='img/blog-1.png';">
                        <?php else: ?>
                            <img src="img/blog-1.png" class="img-fluid rounded-top w-100" alt="<?php echo $title; ?>">
                        <?php endif; ?>
                        <?php if (!empty($icon)): ?>
                            <div class="service-icon p-3">
                                <i class="<?php echo $icon; ?> fa-2x"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="service-content p-4">
                        <div class="service-content-inner">
                            <?php if (!empty($title)): ?>
                                <a href="#" class="d-inline-block h4 mb-3"><?php echo $title; ?></a>
                            <?php endif; ?>
                            <?php if ($price !== null && $price > 0): ?>
                                <div class="mb-3">
                                    <span class="h5 text-primary fw-bold">$<?php echo number_format($price, 2); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($description)): ?>
                                <p class="mb-4"><?php echo nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8')); ?></p>
                            <?php endif; ?>
                            <a class="btn btn-primary rounded-pill py-2 px-4" href="consultation.html">
                                <?php echo ($price !== null && $price > 0) ? 'Purchase Now' : 'Get Started'; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php 
        $delay += 0.2;
    endforeach; 
    ?>
<?php endif; ?>
