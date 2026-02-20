<?php
/**
 * Database Export Script for Orchidee LLC
 * This script exports the complete database structure and data to a file
 * WITHOUT CREATE DATABASE command (for shared hosting)
 */

require_once 'config/database.php';

$exportFile = 'database/orchidee_courses_export_' . date('Y-m-d_H-i-s') . '.sql';

try {
    $conn = getDBConnection();
    
    $fp = fopen($exportFile, 'w');
    
    if (!$fp) {
        die("Error: Cannot create export file: $exportFile\n");
    }
    
    fwrite($fp, "-- ============================================\n");
    fwrite($fp, "-- Orchidee LLC - Database Export\n");
    fwrite($fp, "-- Export Date: " . date('Y-m-d H:i:s') . "\n");
    fwrite($fp, "-- Database: orchidee_courses\n");
    fwrite($fp, "-- ============================================\n\n");
    
    fwrite($fp, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
    fwrite($fp, "SET AUTOCOMMIT = 0;\n");
    fwrite($fp, "START TRANSACTION;\n");
    fwrite($fp, "SET time_zone = \"+00:00\";\n\n");
    
    // Disable foreign key checks to avoid constraint errors
    fwrite($fp, "-- Disable foreign key checks during import\n");
    fwrite($fp, "SET FOREIGN_KEY_CHECKS = 0;\n\n");
    
    // Note: CREATE DATABASE and USE are removed for shared hosting compatibility
    // The user must select the database manually in phpMyAdmin before importing
    fwrite($fp, "-- --------------------------------------------------------\n");
    fwrite($fp, "-- Database: `orchidee_courses`\n");
    fwrite($fp, "-- NOTE: Select your database in phpMyAdmin before importing this file\n");
    fwrite($fp, "-- The database name might be different on shared hosting\n");
    fwrite($fp, "-- --------------------------------------------------------\n\n");
    fwrite($fp, "-- USE `orchidee_courses`; -- Commented out for shared hosting compatibility\n\n");
    
    // Get all tables
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_array()) {
        $tables[] = $row[0];
    }
    
    // Define table order (tables without foreign keys first)
    $tableOrder = [
        'users',
        'course_categories',
        'courses',
        'chapters',
        'lessons',
        'resources',
        'purchases',
        'user_progress',
        'payment_config'
    ];
    
    // Filter and order tables
    $orderedTables = [];
    foreach ($tableOrder as $tableName) {
        if (in_array($tableName, $tables)) {
            $orderedTables[] = $tableName;
        }
    }
    // Add any remaining tables not in the order list
    foreach ($tables as $table) {
        if (!in_array($table, $orderedTables)) {
            $orderedTables[] = $table;
        }
    }
    
    // Export structure and data for each table
    foreach ($orderedTables as $table) {
        fwrite($fp, "-- --------------------------------------------------------\n");
        fwrite($fp, "-- Table structure for table `$table`\n");
        fwrite($fp, "-- --------------------------------------------------------\n\n");
        
        // Drop table
        fwrite($fp, "DROP TABLE IF EXISTS `$table`;\n");
        
        // Get create table statement
        $createResult = $conn->query("SHOW CREATE TABLE `$table`");
        $createRow = $createResult->fetch_array();
        $createStatement = $createRow[1];
        
        // Remove foreign key constraints from CREATE TABLE statement
        // We'll add them back at the end using ALTER TABLE
        // Remove CONSTRAINT ... FOREIGN KEY ... REFERENCES ... from the end (with optional ON DELETE clause)
        $createStatement = preg_replace('/,\s*CONSTRAINT\s+`[^`]+`\s+FOREIGN\s+KEY\s*\([^)]+\)\s+REFERENCES\s+`[^`]+`\s*\([^)]+\)\s*(ON\s+DELETE\s+\w+)?/i', '', $createStatement);
        
        // Clean up any artifacts left by the regex
        // Remove any trailing NULL NULL or duplicate NULL
        $createStatement = preg_replace('/\s+NULL\s+NULL/i', '', $createStatement);
        // Remove NULL before closing parenthesis
        $createStatement = preg_replace('/,\s*NULL\s*\)/i', ')', $createStatement);
        $createStatement = preg_replace('/\s+NULL\s*\)/i', ')', $createStatement);
        // Remove any trailing commas before closing parenthesis
        $createStatement = preg_replace('/,\s*\)\s*ENGINE/i', ') ENGINE', $createStatement);
        // Remove any double spaces
        $createStatement = preg_replace('/\s{2,}/', ' ', $createStatement);
        
        fwrite($fp, $createStatement . ";\n\n");
        
        // Export data
        fwrite($fp, "-- --------------------------------------------------------\n");
        fwrite($fp, "-- Dumping data for table `$table`\n");
        fwrite($fp, "-- --------------------------------------------------------\n\n");
        
        $dataResult = $conn->query("SELECT * FROM `$table`");
        if ($dataResult->num_rows > 0) {
            // Get column names
            $columns = [];
            $fields = $dataResult->fetch_fields();
            foreach ($fields as $field) {
                $columns[] = "`" . $field->name . "`";
            }
            $columnList = implode(", ", $columns);
            
            fwrite($fp, "INSERT INTO `$table` ($columnList) VALUES\n");
            
            $values = [];
            while ($row = $dataResult->fetch_assoc()) {
                $rowValues = [];
                foreach ($row as $key => $value) {
                    if ($value === null) {
                        $rowValues[] = "NULL";
                    } else {
                        // Escape special characters
                        $escaped = $conn->real_escape_string($value);
                        $rowValues[] = "'$escaped'";
                    }
                }
                $values[] = "(" . implode(", ", $rowValues) . ")";
            }
            
            fwrite($fp, implode(",\n", $values) . ";\n\n");
        } else {
            fwrite($fp, "-- No data in table `$table`\n\n");
        }
    }
    
    // Add foreign key constraints after all tables are created
    fwrite($fp, "-- --------------------------------------------------------\n");
    fwrite($fp, "-- Adding foreign key constraints\n");
    fwrite($fp, "-- --------------------------------------------------------\n\n");
    
    // Get and add foreign key constraints
    foreach ($orderedTables as $table) {
        $fkResult = $conn->query("
            SELECT 
                CONSTRAINT_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM 
                INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE 
                TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = '$table'
                AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        
        if ($fkResult && $fkResult->num_rows > 0) {
            while ($fk = $fkResult->fetch_assoc()) {
                $constraintName = $fk['CONSTRAINT_NAME'];
                $columnName = $fk['COLUMN_NAME'];
                $refTable = $fk['REFERENCED_TABLE_NAME'];
                $refColumn = $fk['REFERENCED_COLUMN_NAME'];
                
                fwrite($fp, "ALTER TABLE `$table` ADD CONSTRAINT `$constraintName` FOREIGN KEY (`$columnName`) REFERENCES `$refTable` (`$refColumn`) ON DELETE CASCADE;\n");
            }
        }
    }
    
    fwrite($fp, "\n");
    
    // Re-enable foreign key checks
    fwrite($fp, "-- Re-enable foreign key checks\n");
    fwrite($fp, "SET FOREIGN_KEY_CHECKS = 1;\n\n");
    
    fwrite($fp, "-- --------------------------------------------------------\n");
    fwrite($fp, "-- End of export\n");
    fwrite($fp, "-- --------------------------------------------------------\n\n");
    fwrite($fp, "COMMIT;\n");
    
    fclose($fp);
    $conn->close();
    
    echo "✅ Database export completed successfully!\n";
    echo "📁 File saved to: $exportFile\n";
    echo "📊 Tables exported: " . count($tables) . "\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>

