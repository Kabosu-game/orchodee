<?php
/**
 * Script pour créer directement toutes les tables dans la base de données
 * Exécutez ce fichier une fois dans votre navigateur ou via ligne de commande
 */

require_once 'config/database.php';

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='utf-8'>
    <title>Création des Tables</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; max-width: 900px; margin: 0 auto; }
        .success { color: #28a745; padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; margin: 10px 0; border-radius: 5px; }
        .error { color: #dc3545; padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 10px 0; border-radius: 5px; }
        .info { color: #0c5460; padding: 15px; background: #d1ecf1; border: 1px solid #bee5eb; margin: 10px 0; border-radius: 5px; }
        h1 { color: #007bff; }
        h2 { color: #0056b3; margin-top: 20px; }
        .sql-box { background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; margin: 10px 0; border-radius: 5px; font-family: monospace; font-size: 12px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔧 Création des Tables dans la Base de Données</h1>";

try {
    $conn = getDBConnection();
    
    if ($conn->connect_error) {
        throw new Exception("Erreur de connexion: " . $conn->connect_error);
    }
    
    echo "<div class='success'>✓ Connexion à la base de données réussie</div>";
    
    // Désactiver les vérifications de clés étrangères
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    
    // Définir toutes les tables à créer
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
    
    echo "<h2>Étape 1: Vérification des tables prérequises</h2>";
    
    // Vérifier si la table services existe
    $checkServices = $conn->query("SHOW TABLES LIKE 'services'");
    if ($checkServices->num_rows === 0) {
        echo "<div class='info'>Création de la table 'services'...</div>";
        $createServicesSQL = "CREATE TABLE IF NOT EXISTS services (
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
        
        if ($conn->query($createServicesSQL)) {
            echo "<div class='success'>✓ Table 'services' créée avec succès</div>";
        } else {
            echo "<div class='error'>✗ Erreur lors de la création de la table 'services': " . $conn->error . "</div>";
        }
    } else {
        echo "<div class='success'>✓ Table 'services' existe déjà</div>";
    }
    
    // Vérifier si la table users existe
    $checkUsers = $conn->query("SHOW TABLES LIKE 'users'");
    if ($checkUsers->num_rows === 0) {
        echo "<div class='info'>Création de la table 'users'...</div>";
        $createUsersSQL = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('user', 'admin') DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($createUsersSQL)) {
            echo "<div class='success'>✓ Table 'users' créée avec succès</div>";
        } else {
            echo "<div class='error'>✗ Erreur lors de la création de la table 'users': " . $conn->error . "</div>";
        }
    } else {
        echo "<div class='success'>✓ Table 'users' existe déjà</div>";
    }
    
    // Recurring payments: add columns to services if missing
    echo "<h2>Étape 1b: Colonnes paiements récurrents (services)</h2>";
    $tblServices = $conn->query("SHOW TABLES LIKE 'services'");
    if ($tblServices && $tblServices->num_rows > 0) {
        $cols = $conn->query("SHOW COLUMNS FROM services LIKE 'recurring_enabled'");
        if ($cols && $cols->num_rows === 0) {
            $conn->query("ALTER TABLE services ADD recurring_enabled TINYINT(1) DEFAULT 0, ADD billing_interval VARCHAR(10) DEFAULT 'month'");
            echo "<div class='success'>✓ Colonnes recurring_enabled et billing_interval ajoutées à services</div>";
        } else {
            echo "<div class='info'>ℹ Colonnes recurring déjà présentes</div>";
        }
    } else {
        echo "<div class='info'>ℹ Table services absente (créée plus bas si besoin)</div>";
    }
    
    // service_plans: duration + total_price per service
    $checkPlans = $conn->query("SHOW TABLES LIKE 'service_plans'");
    if ($checkPlans->num_rows === 0) {
        $sql = "CREATE TABLE service_plans (
            id INT AUTO_INCREMENT PRIMARY KEY,
            service_id INT NOT NULL,
            duration_months INT NOT NULL,
            total_price DECIMAL(10, 2) NOT NULL,
            display_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
            KEY service_id (service_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if ($conn->query($sql)) {
            echo "<div class='success'>✓ Table service_plans créée</div>";
        } else {
            echo "<div class='error'>✗ service_plans: " . $conn->error . "</div>";
        }
    } else {
        echo "<div class='success'>✓ Table service_plans existe déjà</div>";
    }
    
    // service_subscriptions: one per user/service/plan
    $checkSubs = $conn->query("SHOW TABLES LIKE 'service_subscriptions'");
    if ($checkSubs->num_rows === 0) {
        $sql = "CREATE TABLE service_subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            service_id INT NOT NULL,
            plan_id INT NOT NULL,
            user_id INT,
            request_id INT,
            amount_per_interval DECIMAL(10, 2) NOT NULL,
            billing_interval ENUM('week','month') NOT NULL DEFAULT 'month',
            start_date DATE NOT NULL,
            next_payment_date DATE,
            end_date DATE,
            status ENUM('active','cancelled','completed') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
            FOREIGN KEY (plan_id) REFERENCES service_plans(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            KEY service_id (service_id),
            KEY user_id (user_id),
            KEY status (status),
            KEY next_payment_date (next_payment_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if ($conn->query($sql)) {
            echo "<div class='success'>✓ Table service_subscriptions créée</div>";
        } else {
            echo "<div class='error'>✗ service_subscriptions: " . $conn->error . "</div>";
        }
    } else {
        echo "<div class='success'>✓ Table service_subscriptions existe déjà</div>";
    }
    
    // service_subscription_payments: each payment
    $checkPay = $conn->query("SHOW TABLES LIKE 'service_subscription_payments'");
    if ($checkPay->num_rows === 0) {
        $sql = "CREATE TABLE service_subscription_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            subscription_id INT NOT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            payment_date DATE NOT NULL,
            payment_method VARCHAR(50),
            transaction_id VARCHAR(255),
            status ENUM('pending','completed','failed') DEFAULT 'completed',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (subscription_id) REFERENCES service_subscriptions(id) ON DELETE CASCADE,
            KEY subscription_id (subscription_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if ($conn->query($sql)) {
            echo "<div class='success'>✓ Table service_subscription_payments créée</div>";
        } else {
            echo "<div class='error'>✗ service_subscription_payments: " . $conn->error . "</div>";
        }
    } else {
        echo "<div class='success'>✓ Table service_subscription_payments existe déjà</div>";
    }
    
    // --- Coach system ---
    echo "<h2>Étape 1c: Système coachs</h2>";
    $tblUsers = $conn->query("SHOW TABLES LIKE 'users'");
    if ($tblUsers && $tblUsers->num_rows > 0) {
        $r = $conn->query("SHOW COLUMNS FROM users WHERE Field = 'role'");
        $row = ($r && $r->num_rows > 0) ? $r->fetch_assoc() : null;
        $type = $row['Type'] ?? '';
        if ($row && strpos($type, 'coach') === false) {
            $conn->query("ALTER TABLE users MODIFY role ENUM('user', 'admin', 'coach') DEFAULT 'user'");
            echo "<div class='success'>✓ Role 'coach' ajouté à users</div>";
        } else {
            echo "<div class='info'>ℹ Role coach déjà présent</div>";
        }
    }
    $tblCourses = $conn->query("SHOW TABLES LIKE 'courses'");
    if ($tblCourses && $tblCourses->num_rows > 0) {
        $c = $conn->query("SHOW COLUMNS FROM courses LIKE 'visible_public'");
        if ($c && $c->num_rows === 0) {
            $conn->query("ALTER TABLE courses ADD visible_public TINYINT(1) DEFAULT 1");
            echo "<div class='success'>✓ Colonne visible_public ajoutée à courses</div>";
        }
    }
    $tblLive = $conn->query("SHOW TABLES LIKE 'course_live_sessions'");
    if (!$tblLive || $tblLive->num_rows === 0) {
        $sql = "CREATE TABLE course_live_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_id INT NOT NULL,
            coach_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            session_date DATE NOT NULL,
            session_time TIME NOT NULL,
            meet_url VARCHAR(500) NOT NULL,
            duration_minutes INT DEFAULT 60,
            status ENUM('scheduled', 'live', 'ended', 'cancelled') DEFAULT 'scheduled',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY course_id (course_id),
            KEY coach_id (coach_id),
            KEY session_date (session_date),
            KEY status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        if ($conn->query($sql)) {
            echo "<div class='success'>✓ Table course_live_sessions créée</div>";
        } else {
            echo "<div class='error'>✗ course_live_sessions: " . $conn->error . "</div>";
        }
    } else {
        echo "<div class='success'>✓ Table course_live_sessions existe déjà</div>";
    }
    
    echo "<h2>Étape 2: Création des tables du système de formulaires</h2>";
    
    // Créer toutes les tables
    foreach ($tables as $tableName => $createSQL) {
        echo "<div class='info'>Création de la table '$tableName'...</div>";
        
        if ($conn->query($createSQL)) {
            echo "<div class='success'>✓ Table '$tableName' créée avec succès</div>";
        } else {
            // Si la table existe déjà, c'est OK
            if (strpos($conn->error, 'already exists') !== false) {
                echo "<div class='info'>ℹ Table '$tableName' existe déjà</div>";
            } else {
                echo "<div class='error'>✗ Erreur lors de la création de la table '$tableName': " . $conn->error . "</div>";
            }
        }
    }
    
    // service_requests: add plan_id if missing (after table may have been created)
    $tblReq = $conn->query("SHOW TABLES LIKE 'service_requests'");
    if ($tblReq && $tblReq->num_rows > 0) {
        $colPlan = $conn->query("SHOW COLUMNS FROM service_requests LIKE 'plan_id'");
        if ($colPlan && $colPlan->num_rows === 0) {
            $conn->query("ALTER TABLE service_requests ADD plan_id INT NULL, ADD KEY plan_id (plan_id)");
            echo "<div class='success'>✓ Colonne plan_id ajoutée à service_requests</div>";
        }
    }
    
    // Réactiver les vérifications de clés étrangères
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "<h2>Étape 3: Vérification finale</h2>";
    
    // Vérifier que toutes les tables existent
    $allTablesExist = true;
    foreach ($tables as $tableName => $createSQL) {
        $checkTable = $conn->query("SHOW TABLES LIKE '$tableName'");
        if ($checkTable->num_rows > 0) {
            echo "<div class='success'>✓ Table '$tableName' vérifiée et existante</div>";
        } else {
            echo "<div class='error'>✗ Table '$tableName' n'existe pas</div>";
            $allTablesExist = false;
        }
    }
    
    echo "<hr>";
    
    if ($allTablesExist) {
        echo "<div class='success' style='font-size: 18px; font-weight: bold;'>
            <h2>✅ Toutes les tables ont été créées avec succès !</h2>
            <p>Le système de formulaires de services est maintenant opérationnel.</p>
            <ul>
                <li><a href='admin/service-forms.php'>Admin → Service Forms</a> - Créer des formulaires</li>
                <li><a href='admin/services.php'>Admin → Services</a> - Gérer les services</li>
                <li><a href='admin/service-requests.php'>Admin → Service Requests</a> - Voir les demandes</li>
            </ul>
        </div>";
    } else {
        echo "<div class='error'>
            <h2>⚠️ Certaines tables n'ont pas pu être créées</h2>
            <p>Veuillez vérifier les erreurs ci-dessus et réessayer.</p>
        </div>";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<div class='error'><h2>❌ Erreur</h2><p>" . htmlspecialchars($e->getMessage()) . "</p></div>";
}

echo "</body>
</html>";
?>

