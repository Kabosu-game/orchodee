<?php
try {
    require_once __DIR__ . '/../config/database.php';
    
    $conn = getDBConnection();
    
    // Vérifier si la table team_members existe, sinon la créer
    $tableCheck = $conn->query("SHOW TABLES LIKE 'team_members'");
    if ($tableCheck->num_rows === 0) {
        $createTableSQL = "CREATE TABLE IF NOT EXISTS team_members (
            id INT AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            position VARCHAR(200) NOT NULL,
            description TEXT DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            photo VARCHAR(255) DEFAULT NULL,
            facebook_url VARCHAR(255) DEFAULT NULL,
            twitter_url VARCHAR(255) DEFAULT NULL,
            linkedin_url VARCHAR(255) DEFAULT NULL,
            instagram_url VARCHAR(255) DEFAULT NULL,
            display_order INT DEFAULT 0,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY status (status),
            KEY display_order (display_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $conn->query($createTableSQL);
    }
    
    // Récupérer les membres actifs
    $members = [];
    $result = $conn->query("SELECT * FROM team_members WHERE status = 'active' ORDER BY display_order ASC, created_at DESC LIMIT 4");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $members[] = $row;
        }
    }
    
    $conn->close();
} catch (Exception $e) {
    $members = [];
}
?>
<!-- Team Start -->
<div class="container-fluid team py-5" style="background: #f8f9fa;">
    <div class="container py-5">
        <div class="text-center mx-auto pb-5 wow fadeInUp" data-wow-delay="0.2s" style="max-width: 800px;">
            <h4 class="text-primary mb-3">Our Team</h4>
            <h1 class="display-4 mb-4">Meet Our Expert Team Members</h1>
            <p class="mb-0 lead">Our dedicated team embodies the values of solidarity, mutual support, and benevolence. Our certified NCLEX instructors bring years of experience and a proven methodology that combines structured learning with personalized support. We're committed to your success from registration to exam day and beyond.</p>
        </div>
        <div class="row g-4">
            <?php if (empty($members)): ?>
                <div class="col-12 text-center py-5">
                    <i class="fa fa-users fa-4x text-muted mb-3"></i>
                    <h3 class="text-muted">No team members available</h3>
                    <p class="text-muted">Team members will be displayed here once added by the administrator.</p>
                </div>
            <?php else: ?>
                <?php 
                $delay = 0.1;
                foreach ($members as $member): 
                    $fullName = htmlspecialchars($member['first_name'] . ' ' . $member['last_name']);
                    $photo = !empty($member['photo']) ? htmlspecialchars($member['photo']) : 'img/team-1.jpg';
                    $hasSocial = !empty($member['facebook_url']) || !empty($member['twitter_url']) || !empty($member['linkedin_url']) || !empty($member['instagram_url']);
                ?>
                    <div class="col-md-6 col-lg-4 col-xl-3 wow fadeInUp" data-wow-delay="<?php echo $delay; ?>s">
                        <div class="team-item bg-white rounded shadow-sm" style="overflow: hidden; transition: all 0.3s ease;">
                            <div class="team-img position-relative" style="overflow: hidden;">
                                <img src="<?php echo $photo; ?>" class="img-fluid w-100" alt="<?php echo $fullName; ?>" style="height: 300px; object-fit: cover; transition: transform 0.3s ease;">
                                <?php if ($hasSocial): ?>
                                    <div class="team-icon position-absolute bottom-0 start-50 translate-middle-x mb-3" style="opacity: 0; transition: opacity 0.3s ease;">
                                        <div class="d-flex gap-2">
                                            <?php if (!empty($member['facebook_url'])): ?>
                                                <a class="btn btn-primary btn-sm-square rounded-pill" href="<?php echo htmlspecialchars($member['facebook_url']); ?>" target="_blank" rel="noopener" title="Facebook">
                                                    <i class="fab fa-facebook-f"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (!empty($member['twitter_url'])): ?>
                                                <a class="btn btn-primary btn-sm-square rounded-pill" href="<?php echo htmlspecialchars($member['twitter_url']); ?>" target="_blank" rel="noopener" title="Twitter">
                                                    <i class="fab fa-twitter"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (!empty($member['linkedin_url'])): ?>
                                                <a class="btn btn-primary btn-sm-square rounded-pill" href="<?php echo htmlspecialchars($member['linkedin_url']); ?>" target="_blank" rel="noopener" title="LinkedIn">
                                                    <i class="fab fa-linkedin-in"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (!empty($member['instagram_url'])): ?>
                                                <a class="btn btn-primary btn-sm-square rounded-pill" href="<?php echo htmlspecialchars($member['instagram_url']); ?>" target="_blank" rel="noopener" title="Instagram">
                                                    <i class="fab fa-instagram"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="team-title p-4">
                                <h4 class="mb-2"><?php echo $fullName; ?></h4>
                                <p class="mb-3"><?php echo nl2br(htmlspecialchars($member['position'])); ?></p>
                                <a href="team-details.php?id=<?php echo $member['id']; ?>" class="btn btn-primary btn-sm w-100 mt-2" style="font-weight: 600;">
                                    View Profile <i class="fa fa-arrow-right ms-1"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php 
                    $delay += 0.1;
                endforeach; 
                ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<!-- Team End -->
<style>
.team-item:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15) !important;
}
.team-item:hover .team-img img {
    transform: scale(1.1);
}
.team-item:hover .team-icon {
    opacity: 1 !important;
}
</style>

