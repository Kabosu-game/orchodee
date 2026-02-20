<?php
require_once 'config/database.php';
require_once 'includes/payment_functions.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header("Location: login.php?redirect=nclex-registration-form.php");
    exit;
}

$userId = $_SESSION['user_id'];
$conn = getDBConnection();

// Get admin email for sending documents
$adminEmail = 'admin@orchideellc.com'; // Change this to your admin email

// Get enabled payment methods
$enabledMethods = getEnabledPaymentMethods($conn);

// Process form submission
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['step'])) {
    // Save step data to session
    if (!isset($_SESSION['nclex_registration_data'])) {
        $_SESSION['nclex_registration_data'] = [];
    }
    
    // Merge current step data with existing session data
    $_SESSION['nclex_registration_data'] = array_merge($_SESSION['nclex_registration_data'], $_POST);
    unset($_SESSION['nclex_registration_data']['step']); // Remove step from data
    
    if ($_POST['step'] == '1') {
        // Validate step 1
        $required = ['first_name', 'last_name', 'dob_day', 'dob_month', 'dob_year', 'phone', 'email', 'confirm_email', 'address_line1', 'city', 'state', 'zip_code', 'immigration_status'];
        
        $missing = [];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                $missing[] = $field;
            }
        }
        
        if (!empty($missing)) {
            $error = 'Please fill in all required fields.';
        } elseif ($_POST['email'] !== $_POST['confirm_email']) {
            $error = 'Email addresses do not match.';
        } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $success = 'Step 1 completed. Please proceed to step 2.';
        }
    } elseif ($_POST['step'] == '2') {
        // Validate step 2 - Elementary School
        $required = ['elementary_school_name', 'elementary_address_line1', 'elementary_city', 'elementary_state', 'elementary_zip_code', 'elementary_another_school'];
        $missing = [];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                $missing[] = $field;
            }
        }
        if (!empty($missing)) {
            $error = 'Please fill in all required fields for Elementary School.';
        } elseif ($_POST['elementary_another_school'] == 'Yes' && empty($_POST['elementary_another_school_name'])) {
            $error = 'Please provide the name of the other elementary school.';
        } else {
            $success = 'Step 2 completed. Please proceed to step 3.';
        }
    } elseif ($_POST['step'] == '3') {
        // Validate step 3 - High School
        $required = ['high_school_name', 'high_school_address_line1', 'high_school_city', 'high_school_state', 'high_school_zip_code', 'high_school_another_school'];
        $missing = [];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                $missing[] = $field;
            }
        }
        if (!empty($missing)) {
            $error = 'Please fill in all required fields for High School.';
        } elseif ($_POST['high_school_another_school'] == 'Yes' && empty($_POST['high_school_another_school_name'])) {
            $error = 'Please provide the name of the other high school.';
        } else {
            $success = 'Step 3 completed. Please proceed to step 4.';
        }
    } elseif ($_POST['step'] == '4') {
        // Validate step 4 - University
        $required = ['university_name', 'university_address_line1', 'university_city', 'university_state', 'university_zip_code', 'university_entry_date', 'university_years', 'university_another', 'university_specialization'];
        $missing = [];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                $missing[] = $field;
            }
        }
        if (!empty($missing)) {
            $error = 'Please fill in all required fields for University.';
        } elseif ($_POST['university_another'] == 'Yes' && empty($_POST['university_another_name'])) {
            $error = 'Please provide the name of the other university.';
        } else {
            $success = 'Step 4 completed. Please proceed to step 5.';
        }
    } elseif ($_POST['step'] == '5') {
        // Validate step 5 - Payment
        $paymentMethod = sanitize($_POST['payment_method'] ?? '');
        
        if (empty($paymentMethod)) {
            $error = 'Please select a payment method.';
        } else {
            // Check if manual payment method requires transaction ID
            if (in_array($paymentMethod, ['zelle', 'cashapp', 'bank_deposit'])) {
                $transactionId = sanitize($_POST['transaction_id'] ?? '');
                if (empty($transactionId)) {
                    $error = 'Please provide a transaction ID or reference number for manual payment methods.';
                }
            }
            
            if (empty($error)) {
                $success = 'Step 5 completed. Please proceed to step 6.';
            }
        }
    } elseif ($_POST['step'] == '6') {
        // Validate step 6 - Agreement
        if (!isset($_POST['agreement']) || $_POST['agreement'] != '1') {
            $error = 'You must agree to the terms and conditions to proceed.';
        } else {
            // Save registration to database
            $allData = $_SESSION['nclex_registration_data'];
            
            // Calculate payment amounts
            $registrationFee = 2500.00;
            $tax = 0.00; // No tax for this registration
            $total = $registrationFee + $tax;
            $paymentMethod = $allData['payment_method'] ?? '';
            // Manual methods store transaction_id at step 5
            $transactionId = $allData['transaction_id'] ?? ($allData['payment_transaction_id'] ?? '');
            
            // Check if table exists
            $tableCheck = $conn->query("SHOW TABLES LIKE 'nclex_registrations'");
            if ($tableCheck->num_rows == 0) {
                // Create table
                $createTableSql = "CREATE TABLE IF NOT EXISTS nclex_registrations (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT,
                        first_name VARCHAR(255) NOT NULL,
                        middle_name VARCHAR(255),
                        last_name VARCHAR(255) NOT NULL,
                        dob_day VARCHAR(2),
                        dob_month VARCHAR(2),
                        dob_year VARCHAR(4),
                        phone VARCHAR(50) NOT NULL,
                        email VARCHAR(255) NOT NULL,
                        address_line1 VARCHAR(255) NOT NULL,
                        address_line2 VARCHAR(255),
                        city VARCHAR(255) NOT NULL,
                        state VARCHAR(50) NOT NULL,
                        zip_code VARCHAR(20) NOT NULL,
                        immigration_status VARCHAR(255),
                        other_immigration_status TEXT,
                        elementary_school_name VARCHAR(255),
                        elementary_address_line1 VARCHAR(255),
                        elementary_address_line2 VARCHAR(255),
                        elementary_city VARCHAR(255),
                        elementary_state VARCHAR(50),
                        elementary_zip_code VARCHAR(20),
                        elementary_entry_date DATE,
                        elementary_exit_date DATE,
                        elementary_grade_from VARCHAR(50),
                        elementary_grade_to VARCHAR(50),
                        elementary_another_school VARCHAR(10),
                        elementary_another_school_name VARCHAR(255),
                        high_school_name VARCHAR(255),
                        high_school_address_line1 VARCHAR(255),
                        high_school_address_line2 VARCHAR(255),
                        high_school_city VARCHAR(255),
                        high_school_state VARCHAR(50),
                        high_school_zip_code VARCHAR(20),
                        high_school_entry_date DATE,
                        high_school_exit_date DATE,
                        high_school_grade_from VARCHAR(50),
                        high_school_grade_to VARCHAR(50),
                        high_school_another_school VARCHAR(10),
                        high_school_another_school_name VARCHAR(255),
                        university_name VARCHAR(255),
                        university_address_line1 VARCHAR(255),
                        university_address_line2 VARCHAR(255),
                        university_city VARCHAR(255),
                        university_state VARCHAR(50),
                        university_zip_code VARCHAR(20),
                        university_entry_date DATE,
                        university_exit_date DATE,
                        university_years INT,
                        university_another VARCHAR(10),
                        university_another_name VARCHAR(255),
                        university_specialization VARCHAR(10),
                        specialization VARCHAR(255),
                        documents TEXT,
                        registration_fee DECIMAL(10, 2) DEFAULT 2500.00,
                        tax DECIMAL(10, 2) DEFAULT 0.00,
                        total_amount DECIMAL(10, 2) NOT NULL,
                        payment_method VARCHAR(50),
                        payment_transaction_id VARCHAR(255),
                        payment_status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
                        status ENUM('pending', 'reviewed', 'approved', 'rejected') DEFAULT 'pending',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_user_id (user_id),
                        INDEX idx_email (email),
                        INDEX idx_status (status),
                        INDEX idx_created_at (created_at)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                $conn->query($createTableSql);
            }

            // If the table already exists (older installs), ensure required columns exist
            // This prevents: Unknown column 'registration_fee' in field list
            try {
                $ensureColumn = function(mysqli $conn, string $table, string $column, string $alterSql) {
                    $colResult = $conn->query("SHOW COLUMNS FROM `$table` LIKE '" . $conn->real_escape_string($column) . "'");
                    if ($colResult && (int)$colResult->num_rows === 0) {
                        $conn->query($alterSql);
                    }
                };

                $ensureColumn($conn, 'nclex_registrations', 'registration_fee',
                    "ALTER TABLE `nclex_registrations` ADD COLUMN `registration_fee` DECIMAL(10,2) DEFAULT 2500.00"
                );
                $ensureColumn($conn, 'nclex_registrations', 'tax',
                    "ALTER TABLE `nclex_registrations` ADD COLUMN `tax` DECIMAL(10,2) DEFAULT 0.00"
                );
                $ensureColumn($conn, 'nclex_registrations', 'total_amount',
                    "ALTER TABLE `nclex_registrations` ADD COLUMN `total_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00"
                );
                $ensureColumn($conn, 'nclex_registrations', 'payment_method',
                    "ALTER TABLE `nclex_registrations` ADD COLUMN `payment_method` VARCHAR(50)"
                );
                $ensureColumn($conn, 'nclex_registrations', 'payment_transaction_id',
                    "ALTER TABLE `nclex_registrations` ADD COLUMN `payment_transaction_id` VARCHAR(255)"
                );
                $ensureColumn($conn, 'nclex_registrations', 'payment_status',
                    "ALTER TABLE `nclex_registrations` ADD COLUMN `payment_status` VARCHAR(20) DEFAULT 'pending'"
                );
                $ensureColumn($conn, 'nclex_registrations', 'status',
                    "ALTER TABLE `nclex_registrations` ADD COLUMN `status` VARCHAR(20) DEFAULT 'pending'"
                );
                $ensureColumn($conn, 'nclex_registrations', 'created_at',
                    "ALTER TABLE `nclex_registrations` ADD COLUMN `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
                );
                $ensureColumn($conn, 'nclex_registrations', 'updated_at',
                    "ALTER TABLE `nclex_registrations` ADD COLUMN `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP"
                );
            } catch (Throwable $e) {
                error_log("NCLEX schema update error: " . $e->getMessage());
                $error = "Erreur base de données : votre table NCLEX est ancienne et ne contient pas toutes les colonnes. Importez la dernière base SQL ou contactez l'administrateur.";
            }
            
            // Insert registration
            try {
                // Keep placeholders count in sync with columns (59)
                $placeholders = implode(', ', array_fill(0, 59, '?'));
                $stmt = $conn->prepare("INSERT INTO nclex_registrations (
                    user_id, first_name, middle_name, last_name, dob_day, dob_month, dob_year,
                    phone, email, address_line1, address_line2, city, state, zip_code,
                    immigration_status, other_immigration_status,
                    elementary_school_name, elementary_address_line1, elementary_address_line2,
                    elementary_city, elementary_state, elementary_zip_code, elementary_entry_date,
                    elementary_exit_date, elementary_grade_from, elementary_grade_to,
                    elementary_another_school, elementary_another_school_name,
                    high_school_name, high_school_address_line1, high_school_address_line2,
                    high_school_city, high_school_state, high_school_zip_code,
                    high_school_entry_date, high_school_exit_date, high_school_grade_from,
                    high_school_grade_to, high_school_another_school, high_school_another_school_name,
                    university_name, university_address_line1, university_address_line2,
                    university_city, university_state, university_zip_code,
                    university_entry_date, university_exit_date, university_years,
                    university_another, university_another_name, university_specialization,
                    specialization, documents, registration_fee, tax, total_amount, payment_method, payment_transaction_id
                ) VALUES ($placeholders)");
            } catch (Throwable $e) {
                error_log("NCLEX insert prepare error: " . $e->getMessage());
                $stmt = false;
                $error = "Erreur base de données lors de l'enregistrement. Assurez-vous que la table NCLEX est à jour (colonnes payment/fees).";
            }
            
            if ($stmt) {
                // Prepare variables for bind_param (must be variables, not expressions)
                $first_name = $allData['first_name'] ?? '';
                $middle_name = $allData['middle_name'] ?? '';
                $last_name = $allData['last_name'] ?? '';
                $dob_day = $allData['dob_day'] ?? '';
                $dob_month = $allData['dob_month'] ?? '';
                $dob_year = $allData['dob_year'] ?? '';
                $phone = $allData['phone'] ?? '';
                $email = $allData['email'] ?? '';
                $address_line1 = $allData['address_line1'] ?? '';
                $address_line2 = $allData['address_line2'] ?? '';
                $city = $allData['city'] ?? '';
                $state = $allData['state'] ?? '';
                $zip_code = $allData['zip_code'] ?? '';
                $immigration_status = $allData['immigration_status'] ?? '';
                $other_immigration_status = $allData['other_immigration_status'] ?? '';
                $elementary_school_name = $allData['elementary_school_name'] ?? '';
                $elementary_address_line1 = $allData['elementary_address_line1'] ?? '';
                $elementary_address_line2 = $allData['elementary_address_line2'] ?? '';
                $elementary_city = $allData['elementary_city'] ?? '';
                $elementary_state = $allData['elementary_state'] ?? '';
                $elementary_zip_code = $allData['elementary_zip_code'] ?? '';
                $elementary_entry_date = !empty($allData['elementary_entry_date']) ? $allData['elementary_entry_date'] : null;
                $elementary_exit_date = !empty($allData['elementary_exit_date']) ? $allData['elementary_exit_date'] : null;
                $elementary_grade_from = $allData['elementary_grade_from'] ?? '';
                $elementary_grade_to = $allData['elementary_grade_to'] ?? '';
                $elementary_another_school = $allData['elementary_another_school'] ?? '';
                $elementary_another_school_name = $allData['elementary_another_school_name'] ?? '';
                $high_school_name = $allData['high_school_name'] ?? '';
                $high_school_address_line1 = $allData['high_school_address_line1'] ?? '';
                $high_school_address_line2 = $allData['high_school_address_line2'] ?? '';
                $high_school_city = $allData['high_school_city'] ?? '';
                $high_school_state = $allData['high_school_state'] ?? '';
                $high_school_zip_code = $allData['high_school_zip_code'] ?? '';
                $high_school_entry_date = !empty($allData['high_school_entry_date']) ? $allData['high_school_entry_date'] : null;
                $high_school_exit_date = !empty($allData['high_school_exit_date']) ? $allData['high_school_exit_date'] : null;
                $high_school_grade_from = $allData['high_school_grade_from'] ?? '';
                $high_school_grade_to = $allData['high_school_grade_to'] ?? '';
                $high_school_another_school = $allData['high_school_another_school'] ?? '';
                $high_school_another_school_name = $allData['high_school_another_school_name'] ?? '';
                $university_name = $allData['university_name'] ?? '';
                $university_address_line1 = $allData['university_address_line1'] ?? '';
                $university_address_line2 = $allData['university_address_line2'] ?? '';
                $university_city = $allData['university_city'] ?? '';
                $university_state = $allData['university_state'] ?? '';
                $university_zip_code = $allData['university_zip_code'] ?? '';
                $university_entry_date = !empty($allData['university_entry_date']) ? $allData['university_entry_date'] : null;
                $university_exit_date = !empty($allData['university_exit_date']) ? $allData['university_exit_date'] : null;
                $university_years = isset($allData['university_years']) && $allData['university_years'] !== '' ? intval($allData['university_years']) : 0;
                $university_another = $allData['university_another'] ?? '';
                $university_another_name = $allData['university_another_name'] ?? '';
                $university_specialization = $allData['university_specialization'] ?? '';
                $specialization = $allData['specialization'] ?? '';
                $documents = ''; // documents field - empty since files are sent via email
                $payment_method = $allData['payment_method'] ?? '';
                $payment_transaction_id = $allData['transaction_id'] ?? ($allData['payment_transaction_id'] ?? '');
                
                // Build type string: 2 integers (user_id, university_years) + 52 strings + 3 decimals (registration_fee, tax, total_amount) + 2 strings (payment_method, payment_transaction_id)
                // Total: 1 + 48 + 1 + 4 + 3 + 2 = 59 parameters
                $typeString = "i" . str_repeat("s", 48) . "i" . str_repeat("s", 4) . "dddss";
                
                $stmt->bind_param($typeString,
                        $userId,
                        $first_name,
                        $middle_name,
                        $last_name,
                        $dob_day,
                        $dob_month,
                        $dob_year,
                        $phone,
                        $email,
                        $address_line1,
                        $address_line2,
                        $city,
                        $state,
                        $zip_code,
                        $immigration_status,
                        $other_immigration_status,
                        $elementary_school_name,
                        $elementary_address_line1,
                        $elementary_address_line2,
                        $elementary_city,
                        $elementary_state,
                        $elementary_zip_code,
                        $elementary_entry_date,
                        $elementary_exit_date,
                        $elementary_grade_from,
                        $elementary_grade_to,
                        $elementary_another_school,
                        $elementary_another_school_name,
                        $high_school_name,
                        $high_school_address_line1,
                        $high_school_address_line2,
                        $high_school_city,
                        $high_school_state,
                        $high_school_zip_code,
                        $high_school_entry_date,
                        $high_school_exit_date,
                        $high_school_grade_from,
                        $high_school_grade_to,
                        $high_school_another_school,
                        $high_school_another_school_name,
                        $university_name,
                        $university_address_line1,
                        $university_address_line2,
                        $university_city,
                        $university_state,
                        $university_zip_code,
                        $university_entry_date,
                        $university_exit_date,
                        $university_years,
                        $university_another,
                        $university_another_name,
                        $university_specialization,
                        $specialization,
                        $documents,
                        $registrationFee,
                        $tax,
                        $total,
                        $payment_method,
                        $payment_transaction_id
                );
                
                if ($stmt->execute()) {
                    $registrationId = $conn->insert_id;
                    $stmt->close();
                    
                    // If Stripe: redirect to Stripe Checkout
                    if ($paymentMethod === 'stripe') {
                        $stripeConfig = isset($enabledMethods['stripe']['config']) ? $enabledMethods['stripe']['config'] : null;
                        if (!$stripeConfig || !is_array($stripeConfig)) {
                            $stripeRow = $conn->query("SELECT config_data FROM payment_config WHERE payment_method = 'stripe' LIMIT 1");
                            if ($stripeRow && $row = $stripeRow->fetch_assoc()) {
                                $raw = trim($row['config_data'] ?? '');
                                if ($raw !== '') {
                                    $dec = json_decode($raw, true);
                                    if (is_array($dec)) {
                                        $stripeConfig = $dec;
                                    }
                                }
                            }
                        }
                        $secretKey = $stripeConfig ? trim($stripeConfig['secret_key'] ?? $stripeConfig['secretKey'] ?? '') : '';
                        if (!$stripeConfig || $secretKey === '' || !preg_match('/^sk_(test|live)_/', $secretKey)) {
                            if ($paymentMethod === 'stripe') {
                                $error = 'Paiement par carte indisponible. Vérifiez la configuration Stripe dans l\'admin ou choisissez une autre méthode.';
                            }
                        } else {
                            $amountCents = (int) round($total * 100);
                            if ($amountCents < 50) $amountCents = 50;
                            $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
                            $scriptPath = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
                            $basePath = rtrim($baseUrl, '/') . ($scriptPath === '/' ? '' : $scriptPath);
                            $successUrl = $basePath . '/nclex-payment-success.php?session_id={CHECKOUT_SESSION_ID}&registration_id=' . $registrationId;
                            $cancelUrl = $basePath . '/nclex-registration-form.php';
                            $postFields = [
                                'mode' => 'payment',
                                'payment_method_types[]' => 'card',
                                'line_items[0][price_data][currency]' => 'usd',
                                'line_items[0][price_data][product_data][name]' => 'NCLEX Registration',
                                'line_items[0][price_data][product_data][description]' => 'NCLEX Registration - Orchidee LLC',
                                'line_items[0][price_data][unit_amount]' => $amountCents,
                                'line_items[0][quantity]' => 1,
                                'success_url' => $successUrl,
                                'cancel_url' => $cancelUrl,
                                'client_reference_id' => (string) $registrationId,
                            ];
                            $secretKey = trim($stripeConfig['secret_key']);
                            $ch = curl_init('https://api.stripe.com/v1/checkout/sessions');
                            curl_setopt_array($ch, [
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_POST => true,
                                CURLOPT_POSTFIELDS => http_build_query($postFields),
                                CURLOPT_USERPWD => $secretKey . ':',
                                CURLOPT_HTTPHEADER => [
                                    'Content-Type: application/x-www-form-urlencoded',
                                ],
                                CURLOPT_TIMEOUT => 30,
                                CURLOPT_SSL_VERIFYPEER => true,
                            ]);
                            $stripeResponse = curl_exec($ch);
                            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                            curl_close($ch);
                            if ($stripeResponse !== false && $httpCode >= 200 && $httpCode < 300) {
                                $sessionData = json_decode($stripeResponse, true);
                                if (!empty($sessionData['url'])) {
                                    unset($_SESSION['nclex_registration_data']);
                                    header('Location: ' . $sessionData['url']);
                                    exit;
                                }
                            }
                        }
                        // Stripe choisi mais redirection non faite : ne pas aller à la page de remerciement
                        if ($paymentMethod === 'stripe') {
                            $error = 'Impossible de lancer le paiement par carte. Vérifiez la configuration Stripe dans l’admin ou choisissez une autre méthode.';
                        }
                    }
                    // PayPal : même flux que registration-next-session (config admin, redirect checkout)
                    if ($paymentMethod === 'paypal') {
                        require_once __DIR__ . '/includes/paypal_helper.php';
                        $paypalConfig = isset($enabledMethods['paypal']['config']) ? $enabledMethods['paypal']['config'] : null;
                        if (!$paypalConfig || !is_array($paypalConfig)) {
                            $pr = $conn->query("SELECT config_data FROM payment_config WHERE payment_method = 'paypal' LIMIT 1");
                            if ($pr && $rowp = $pr->fetch_assoc() && !empty(trim($rowp['config_data'] ?? ''))) {
                                $paypalConfig = json_decode($rowp['config_data'], true);
                            }
                        }
                        $clientId = $paypalConfig ? trim($paypalConfig['client_id'] ?? '') : '';
                        $clientSecret = $paypalConfig ? trim($paypalConfig['client_secret'] ?? '') : '';
                        $sandbox = (isset($paypalConfig['mode']) && strtolower($paypalConfig['mode']) === 'live') ? false : true;
                        if ($clientId && $clientSecret) {
                            $tokenResult = paypal_get_access_token($clientId, $clientSecret, $sandbox);
                            $token = $tokenResult['token'] ?? null;
                            if ($token) {
                                $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
                                $scriptPath = str_replace('\\', '/', dirname($_SERVER['PHP_SELF']));
                                $basePath = rtrim($baseUrl, '/') . ($scriptPath === '/' ? '' : $scriptPath);
                                $returnUrl = $basePath . '/nclex-payment-success-paypal.php?registration_id=' . $registrationId;
                                $cancelUrl = $basePath . '/nclex-registration-form.php';
                                $amountStr = number_format($total, 2, '.', '');
                                $orderResult = paypal_create_order($token, $amountStr, $returnUrl, $cancelUrl, 'NCLEX Registration', $sandbox);
                                if (!empty($orderResult['url'])) {
                                    unset($_SESSION['nclex_registration_data']);
                                    header('Location: ' . $orderResult['url']);
                                    exit;
                                }
                                $error = 'PayPal : ' . ($orderResult['error'] ?? 'Impossible de créer la commande.');
                            } else {
                                $error = 'PayPal : ' . ($tokenResult['error'] ?? 'Impossible d\'obtenir l\'accès. Vérifiez Client ID et Secret (sandbox vs live).');
                            }
                        } else {
                            $error = 'PayPal non configuré. Allez dans Admin → Paramètres de paiement.';
                        }
                    }
                    
                    if ($paymentMethod !== 'stripe' && $paymentMethod !== 'paypal') {
                    // Send email to admin with documents info
                    $userEmail = $allData['email'];
                    $userName = ($allData['first_name'] ?? '') . ' ' . ($allData['last_name'] ?? '');
                    
                    $subject = "New NCLEX Registration - " . $userName;
                    $message = "A new NCLEX registration has been submitted.\n\n";
                    $message .= "Registration ID: " . $registrationId . "\n";
                    $message .= "User: " . $userName . "\n";
                    $message .= "Email: " . $userEmail . "\n";
                    $message .= "Phone: " . ($allData['phone'] ?? '') . "\n";
                    $message .= "Payment Amount: $" . number_format($total, 2) . "\n";
                    $message .= "Payment Method: " . ucfirst(str_replace('_', ' ', $paymentMethod)) . "\n";
                    if (!empty($transactionId)) {
                        $message .= "Transaction ID: " . $transactionId . "\n";
                    }
                    $message .= "Payment Status: Pending\n\n";
                    $message .= "IMPORTANT: The user has been instructed to send the required documents via email.\n";
                    $message .= "Please check your email for the documents from: " . $userEmail . "\n\n";
                    $message .= "View registration: " . (isset($_SERVER['HTTPS']) ? 'https' : 'http') . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/admin/nclex-registrations.php?action=view&id=" . $registrationId;
                    
                    $headers = "From: noreply@orchideellc.com\r\n";
                    $headers .= "Reply-To: " . $userEmail . "\r\n";
                    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                    
                    @mail($adminEmail, $subject, $message, $headers);
                    
                    // Clear session data
                    unset($_SESSION['nclex_registration_data']);
                    
                    // Redirect to thank you page
                    header("Location: nclex-registration-thank-you.php?id=" . $registrationId);
                    exit;
                    }
                } else {
                    $error = 'Registration failed: ' . $stmt->error . '. Please try again.';
                    $stmt->close();
                }
            } else {
                $error = 'Database error: ' . $conn->error . '. Please try again.';
            }
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="utf-8">
        <title>NCLEX Registration Form - Orchidee LLC</title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
        <meta content="NCLEX registration form for board of nursing at Orchidee LLC" name="description">

        <!-- Google Web Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,100..1000;1,9..40,100..1000&family=Inter:slnt,wght@-10..0,100..900&display=swap" rel="stylesheet">

        <!-- Favicon -->
        <link rel="icon" type="image/png" href="img/orchideelogo.png">
        <link rel="apple-touch-icon" href="img/orchideelogo.png">

        <!-- Icon Font Stylesheet -->
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"/>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

        <!-- Libraries Stylesheet -->
        <link rel="stylesheet" href="lib/animate/animate.min.css"/>
        <link href="lib/lightbox/css/lightbox.min.css" rel="stylesheet">
        <link href="lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">

        <!-- Customized Bootstrap Stylesheet -->
        <link href="css/bootstrap.min.css" rel="stylesheet">

        <!-- Template Stylesheet -->
        <link href="css/style.css" rel="stylesheet">
        
        <style>
            .step-indicator {
                display: flex;
                justify-content: center;
                margin-bottom: 30px;
                flex-wrap: wrap;
            }
            .step {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                background: #e9ecef;
                color: #6c757d;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                margin: 0 5px;
                position: relative;
            }
            .step.active {
                background: var(--bs-primary);
                color: white;
            }
            .step.completed {
                background: #28a745;
                color: white;
            }
            .step::after {
                content: '';
                position: absolute;
                top: 50%;
                left: 100%;
                width: 10px;
                height: 2px;
                background: #e9ecef;
                transform: translateY(-50%);
            }
            .step:last-child::after {
                display: none;
            }
            .step.completed::after {
                background: #28a745;
            }
            .form-step {
                display: none;
            }
            .form-step.active {
                display: block;
            }
        </style>
    </head>

    <body>

        <!-- Spinner Start -->
        <div id="spinner" class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="sr-only">Loading...</span>
            </div>
        </div>
        <!-- Spinner End -->


        <?php include 'includes/promo-banner.php'; ?>
        <?php include 'includes/menu-dynamic.php'; ?>


        <!-- Header Start -->
        <div class="container-fluid bg-breadcrumb" style="background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('img/ban.jpeg') center/cover no-repeat;">
            <div class="container text-center py-5" style="max-width: 900px;">
                <h4 class="text-white display-4 mb-4 wow fadeInDown" data-wow-delay="0.1s">NCLEX Registration Form</h4>
                <ol class="breadcrumb d-flex justify-content-center mb-0 wow fadeInDown" data-wow-delay="0.3s">
                    <li class="breadcrumb-item"><a href="index.php" class="text-white">Home</a></li>
                    <li class="breadcrumb-item active text-primary">NCLEX Registration Form</li>
                </ol>    
            </div>
        </div>
        <!-- Header End -->


        <!-- Registration Form Start -->
        <div class="container-fluid bg-light py-5">
            <div class="container py-5">
                <div class="row">
                    <div class="col-lg-10 mx-auto">
                        <div class="bg-white rounded shadow-sm p-5 wow fadeInUp" data-wow-delay="0.1s">
                            
                            <!-- Step Indicator -->
                            <div class="step-indicator mb-4">
                                <div class="step active" id="step1-indicator">1</div>
                                <div class="step" id="step2-indicator">2</div>
                                <div class="step" id="step3-indicator">3</div>
                                <div class="step" id="step4-indicator">4</div>
                                <div class="step" id="step5-indicator">5</div>
                                <div class="step" id="step6-indicator">6</div>
                            </div>

                            <?php if ($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fa fa-exclamation-circle me-2"></i>
                                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <?php if ($success): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fa fa-check-circle me-2"></i>
                                    <?php echo htmlspecialchars($success); ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <!-- User Info Notice -->
                            <div class="alert alert-info mb-4">
                                <i class="fa fa-user-check me-2"></i>
                                <strong>Logged in as:</strong> <?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['email'] ?? 'User'); ?>
                            </div>

                            <form id="nclexRegistrationForm" method="POST" action="">
                                
                                <!-- Step 1: Personal Information -->
                                <div class="form-step active" id="step1">
                                    <h3 class="text-primary mb-4">Step 1: Personal Information</h3>
                                    
                                    <!-- Name -->
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="first_name" 
                                                   value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['first_name'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Middle Name</label>
                                            <input type="text" class="form-control" name="middle_name" 
                                                   value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['middle_name'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="last_name" 
                                                   value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['last_name'] ?? ''); ?>" required>
                                        </div>
                                    </div>

                                    <!-- Date of Birth -->
                                    <div class="mb-3">
                                        <label class="form-label">Date Of Birth <span class="text-danger">*</span></label>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <label class="form-label small">DD</label>
                                                <select class="form-select" name="dob_day" required>
                                                    <option value="">Day</option>
                                                    <?php for ($i = 1; $i <= 31; $i++): ?>
                                                        <option value="<?php echo $i; ?>" <?php echo (isset($_SESSION['nclex_registration_data']['dob_day']) && $_SESSION['nclex_registration_data']['dob_day'] == $i) ? 'selected' : ''; ?>>
                                                            <?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small">MM</label>
                                                <select class="form-select" name="dob_month" required>
                                                    <option value="">Month</option>
                                                    <?php 
                                                    $months = ['01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April', 
                                                              '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August', 
                                                              '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'];
                                                    foreach ($months as $num => $name): 
                                                    ?>
                                                        <option value="<?php echo $num; ?>" <?php echo (isset($_SESSION['nclex_registration_data']['dob_month']) && $_SESSION['nclex_registration_data']['dob_month'] == $num) ? 'selected' : ''; ?>>
                                                            <?php echo $name; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small">YYYY</label>
                                                <select class="form-select" name="dob_year" required>
                                                    <option value="">Year</option>
                                                    <?php for ($i = date('Y') - 18; $i >= date('Y') - 100; $i--): ?>
                                                        <option value="<?php echo $i; ?>" <?php echo (isset($_SESSION['nclex_registration_data']['dob_year']) && $_SESSION['nclex_registration_data']['dob_year'] == $i) ? 'selected' : ''; ?>>
                                                            <?php echo $i; ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Phone Number -->
                                    <div class="mb-3">
                                        <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                        <input type="tel" class="form-control" name="phone" 
                                               value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['phone'] ?? ''); ?>" required>
                                    </div>

                                    <!-- Email -->
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Email <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" name="email" 
                                                   value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['email'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Confirm Email <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" name="confirm_email" 
                                                   value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['confirm_email'] ?? ''); ?>" required>
                                        </div>
                                    </div>

                                    <!-- Address -->
                                    <div class="mb-3">
                                        <label class="form-label">Address <span class="text-danger">*</span></label>
                                        <div class="mb-2">
                                            <label class="form-label small">Address Line 1</label>
                                            <input type="text" class="form-control" name="address_line1" 
                                                   value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['address_line1'] ?? ''); ?>" required>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small">Address Line 2</label>
                                            <input type="text" class="form-control" name="address_line2" 
                                                   value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['address_line2'] ?? ''); ?>">
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-2">
                                                <label class="form-label small">City</label>
                                                <input type="text" class="form-control" name="city" 
                                                       value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['city'] ?? ''); ?>" required>
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <label class="form-label small">Country</label>
                                                <select class="form-select" name="state" required>
                                                    <option value="">--- Select country ---</option>
                                                    <?php
                                                    $countries = [
                                                        'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AS' => 'American Samoa', 'AD' => 'Andorra',
                                                        'AO' => 'Angola', 'AI' => 'Anguilla', 'AQ' => 'Antarctica', 'AG' => 'Antigua and Barbuda', 'AR' => 'Argentina',
                                                        'AM' => 'Armenia', 'AW' => 'Aruba', 'AU' => 'Australia', 'AT' => 'Austria', 'AZ' => 'Azerbaijan',
                                                        'BS' => 'Bahamas', 'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados', 'BY' => 'Belarus',
                                                        'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin', 'BM' => 'Bermuda', 'BT' => 'Bhutan',
                                                        'BO' => 'Bolivia', 'BA' => 'Bosnia and Herzegovina', 'BW' => 'Botswana', 'BV' => 'Bouvet Island', 'BR' => 'Brazil',
                                                        'IO' => 'British Indian Ocean Territory', 'BN' => 'Brunei', 'BG' => 'Bulgaria', 'BF' => 'Burkina Faso', 'BI' => 'Burundi',
                                                        'KH' => 'Cambodia', 'CM' => 'Cameroon', 'CA' => 'Canada', 'CV' => 'Cape Verde', 'KY' => 'Cayman Islands',
                                                        'CF' => 'Central African Republic', 'TD' => 'Chad', 'CL' => 'Chile', 'CN' => 'China', 'CX' => 'Christmas Island',
                                                        'CC' => 'Cocos Islands', 'CO' => 'Colombia', 'KM' => 'Comoros', 'CG' => 'Congo', 'CD' => 'Congo (Democratic Republic)',
                                                        'CK' => 'Cook Islands', 'CR' => 'Costa Rica', 'CI' => 'Cote d\'Ivoire', 'HR' => 'Croatia', 'CU' => 'Cuba',
                                                        'CY' => 'Cyprus', 'CZ' => 'Czech Republic', 'DK' => 'Denmark', 'DJ' => 'Djibouti', 'DM' => 'Dominica',
                                                        'DO' => 'Dominican Republic', 'EC' => 'Ecuador', 'EG' => 'Egypt', 'SV' => 'El Salvador', 'GQ' => 'Equatorial Guinea',
                                                        'ER' => 'Eritrea', 'EE' => 'Estonia', 'ET' => 'Ethiopia', 'FK' => 'Falkland Islands', 'FO' => 'Faroe Islands',
                                                        'FJ' => 'Fiji', 'FI' => 'Finland', 'FR' => 'France', 'GF' => 'French Guiana', 'PF' => 'French Polynesia',
                                                        'TF' => 'French Southern Territories', 'GA' => 'Gabon', 'GM' => 'Gambia', 'GE' => 'Georgia', 'DE' => 'Germany',
                                                        'GH' => 'Ghana', 'GI' => 'Gibraltar', 'GR' => 'Greece', 'GL' => 'Greenland', 'GD' => 'Grenada',
                                                        'GP' => 'Guadeloupe', 'GU' => 'Guam', 'GT' => 'Guatemala', 'GG' => 'Guernsey', 'GN' => 'Guinea',
                                                        'GW' => 'Guinea-Bissau', 'GY' => 'Guyana', 'HT' => 'Haiti', 'HM' => 'Heard and McDonald Islands', 'HN' => 'Honduras',
                                                        'HK' => 'Hong Kong', 'HU' => 'Hungary', 'IS' => 'Iceland', 'IN' => 'India', 'ID' => 'Indonesia',
                                                        'IR' => 'Iran', 'IQ' => 'Iraq', 'IE' => 'Ireland', 'IM' => 'Isle of Man', 'IL' => 'Israel',
                                                        'IT' => 'Italy', 'JM' => 'Jamaica', 'JP' => 'Japan', 'JE' => 'Jersey', 'JO' => 'Jordan',
                                                        'KZ' => 'Kazakhstan', 'KE' => 'Kenya', 'KI' => 'Kiribati', 'KP' => 'North Korea', 'KR' => 'South Korea',
                                                        'KW' => 'Kuwait', 'KG' => 'Kyrgyzstan', 'LA' => 'Laos', 'LV' => 'Latvia', 'LB' => 'Lebanon',
                                                        'LS' => 'Lesotho', 'LR' => 'Liberia', 'LY' => 'Libya', 'LI' => 'Liechtenstein', 'LT' => 'Lithuania',
                                                        'LU' => 'Luxembourg', 'MO' => 'Macao', 'MK' => 'Macedonia', 'MG' => 'Madagascar', 'MW' => 'Malawi',
                                                        'MY' => 'Malaysia', 'MV' => 'Maldives', 'ML' => 'Mali', 'MT' => 'Malta', 'MH' => 'Marshall Islands',
                                                        'MQ' => 'Martinique', 'MR' => 'Mauritania', 'MU' => 'Mauritius', 'YT' => 'Mayotte', 'MX' => 'Mexico',
                                                        'FM' => 'Micronesia', 'MD' => 'Moldova', 'MC' => 'Monaco', 'MN' => 'Mongolia', 'ME' => 'Montenegro',
                                                        'MS' => 'Montserrat', 'MA' => 'Morocco', 'MZ' => 'Mozambique', 'MM' => 'Myanmar', 'NA' => 'Namibia',
                                                        'NR' => 'Nauru', 'NP' => 'Nepal', 'NL' => 'Netherlands', 'AN' => 'Netherlands Antilles', 'NC' => 'New Caledonia',
                                                        'NZ' => 'New Zealand', 'NI' => 'Nicaragua', 'NE' => 'Niger', 'NG' => 'Nigeria', 'NU' => 'Niue',
                                                        'NF' => 'Norfolk Island', 'MP' => 'Northern Mariana Islands', 'NO' => 'Norway', 'OM' => 'Oman', 'PK' => 'Pakistan',
                                                        'PW' => 'Palau', 'PS' => 'Palestine', 'PA' => 'Panama', 'PG' => 'Papua New Guinea', 'PY' => 'Paraguay',
                                                        'PE' => 'Peru', 'PH' => 'Philippines', 'PN' => 'Pitcairn', 'PL' => 'Poland', 'PT' => 'Portugal',
                                                        'PR' => 'Puerto Rico', 'QA' => 'Qatar', 'RE' => 'Reunion', 'RO' => 'Romania', 'RU' => 'Russia',
                                                        'RW' => 'Rwanda', 'BL' => 'Saint Barthelemy', 'SH' => 'Saint Helena', 'KN' => 'Saint Kitts and Nevis',
                                                        'LC' => 'Saint Lucia', 'MF' => 'Saint Martin', 'PM' => 'Saint Pierre and Miquelon', 'VC' => 'Saint Vincent and the Grenadines',
                                                        'WS' => 'Samoa', 'SM' => 'San Marino', 'ST' => 'Sao Tome and Principe', 'SA' => 'Saudi Arabia', 'SN' => 'Senegal',
                                                        'RS' => 'Serbia', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone', 'SG' => 'Singapore', 'SK' => 'Slovakia',
                                                        'SI' => 'Slovenia', 'SB' => 'Solomon Islands', 'SO' => 'Somalia', 'ZA' => 'South Africa', 'GS' => 'South Georgia',
                                                        'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SD' => 'Sudan', 'SR' => 'Suriname', 'SJ' => 'Svalbard and Jan Mayen',
                                                        'SZ' => 'Swaziland', 'SE' => 'Sweden', 'CH' => 'Switzerland', 'SY' => 'Syria', 'TW' => 'Taiwan',
                                                        'TJ' => 'Tajikistan', 'TZ' => 'Tanzania', 'TH' => 'Thailand', 'TL' => 'Timor-Leste', 'TG' => 'Togo',
                                                        'TK' => 'Tokelau', 'TO' => 'Tonga', 'TT' => 'Trinidad and Tobago', 'TN' => 'Tunisia', 'TR' => 'Turkey',
                                                        'TM' => 'Turkmenistan', 'TC' => 'Turks and Caicos Islands', 'TV' => 'Tuvalu', 'UG' => 'Uganda', 'UA' => 'Ukraine',
                                                        'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States', 'UY' => 'Uruguay',
                                                        'UZ' => 'Uzbekistan', 'VU' => 'Vanuatu', 'VA' => 'Vatican City', 'VE' => 'Venezuela', 'VN' => 'Vietnam',
                                                        'VG' => 'Virgin Islands (British)', 'VI' => 'Virgin Islands (US)', 'WF' => 'Wallis and Futuna', 'EH' => 'Western Sahara',
                                                        'YE' => 'Yemen', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe'
                                                    ];
                                                    foreach ($countries as $code => $name):
                                                    ?>
                                                        <option value="<?php echo $code; ?>" <?php echo (isset($_SESSION['nclex_registration_data']['state']) && $_SESSION['nclex_registration_data']['state'] == $code) ? 'selected' : ''; ?>>
                                                            <?php echo $name; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-3 mb-2">
                                                <label class="form-label small">Zip Code</label>
                                                <input type="text" class="form-control" name="zip_code" 
                                                       value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['zip_code'] ?? ''); ?>" required>
                                            </div>
                                        </div>
                                    </div>



                                    <!-- Immigration Status -->
                                    <div class="mb-3">
                                        <label class="form-label">Immigration Status <span class="text-danger">*</span></label>
                                        <select class="form-select" name="immigration_status" id="immigration_status" required onchange="toggleOtherImmigration()">
                                            <option value="">-- Select Immigration Status --</option>
                                            <option value="U.S. Citizen" <?php echo (isset($_SESSION['nclex_registration_data']['immigration_status']) && $_SESSION['nclex_registration_data']['immigration_status'] == 'U.S. Citizen') ? 'selected' : ''; ?>>U.S. Citizen</option>
                                            <option value="Permanent Resident (Green Card Holder)" <?php echo (isset($_SESSION['nclex_registration_data']['immigration_status']) && $_SESSION['nclex_registration_data']['immigration_status'] == 'Permanent Resident (Green Card Holder)') ? 'selected' : ''; ?>>Permanent Resident (Green Card Holder)</option>
                                            <option value="Humanitarian Parole" <?php echo (isset($_SESSION['nclex_registration_data']['immigration_status']) && $_SESSION['nclex_registration_data']['immigration_status'] == 'Humanitarian Parole') ? 'selected' : ''; ?>>Humanitarian Parole</option>
                                            <option value="Asylum Seeker" <?php echo (isset($_SESSION['nclex_registration_data']['immigration_status']) && $_SESSION['nclex_registration_data']['immigration_status'] == 'Asylum Seeker') ? 'selected' : ''; ?>>Asylum Seeker</option>
                                            <option value="Temporary Protected Status (TPS)" <?php echo (isset($_SESSION['nclex_registration_data']['immigration_status']) && $_SESSION['nclex_registration_data']['immigration_status'] == 'Temporary Protected Status (TPS)') ? 'selected' : ''; ?>>Temporary Protected Status (TPS)</option>
                                            <option value="Refugee" <?php echo (isset($_SESSION['nclex_registration_data']['immigration_status']) && $_SESSION['nclex_registration_data']['immigration_status'] == 'Refugee') ? 'selected' : ''; ?>>Refugee</option>
                                            <option value="Work Visa (H-1B, L-1, etc.)" <?php echo (isset($_SESSION['nclex_registration_data']['immigration_status']) && $_SESSION['nclex_registration_data']['immigration_status'] == 'Work Visa (H-1B, L-1, etc.)') ? 'selected' : ''; ?>>Work Visa (H-1B, L-1, etc.)</option>
                                            <option value="Student Visa (F-1, J-1, etc.)" <?php echo (isset($_SESSION['nclex_registration_data']['immigration_status']) && $_SESSION['nclex_registration_data']['immigration_status'] == 'Student Visa (F-1, J-1, etc.)') ? 'selected' : ''; ?>>Student Visa (F-1, J-1, etc.)</option>
                                            <option value="Deferred Action for Childhood Arrivals (DACA)" <?php echo (isset($_SESSION['nclex_registration_data']['immigration_status']) && $_SESSION['nclex_registration_data']['immigration_status'] == 'Deferred Action for Childhood Arrivals (DACA)') ? 'selected' : ''; ?>>Deferred Action for Childhood Arrivals (DACA)</option>
                                            <option value="Pending Immigration Application" <?php echo (isset($_SESSION['nclex_registration_data']['immigration_status']) && $_SESSION['nclex_registration_data']['immigration_status'] == 'Pending Immigration Application') ? 'selected' : ''; ?>>Pending Immigration Application</option>
                                            <option value="Other (Specify)" <?php echo (isset($_SESSION['nclex_registration_data']['immigration_status']) && $_SESSION['nclex_registration_data']['immigration_status'] == 'Other (Specify)') ? 'selected' : ''; ?>>Other (Specify)</option>
                                        </select>
                                    </div>

                                    <div class="mb-3" id="otherImmigrationField" style="display: none;">
                                        <label class="form-label">Please specify your immigration status</label>
                                        <input type="text" class="form-control" name="other_immigration_status" 
                                               value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['other_immigration_status'] ?? ''); ?>">
                                    </div>

                                    <div class="d-flex justify-content-end">
                                        <button type="button" class="btn btn-primary btn-lg px-5" onclick="goToStep2()">
                                            Next: Step 2 <i class="fa fa-arrow-right ms-2"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Step 2: Elementary School -->
                                <div class="form-step" id="step2">
                                    <h3 class="text-primary mb-4">Step 2: Elementary School</h3>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">School Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="elementary_school_name" 
                                               value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['elementary_school_name'] ?? ''); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Address <span class="text-danger">*</span></label>
                                        <div class="mb-2">
                                            <label class="form-label small">Address Line 1</label>
                                            <input type="text" class="form-control" name="elementary_address_line1" 
                                                   value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['elementary_address_line1'] ?? ''); ?>" required>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small">Address Line 2</label>
                                            <input type="text" class="form-control" name="elementary_address_line2" 
                                                   value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['elementary_address_line2'] ?? ''); ?>">
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4 mb-2">
                                                <label class="form-label small">City</label>
                                                <input type="text" class="form-control" name="elementary_city" 
                                                       value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['elementary_city'] ?? ''); ?>" required>
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="form-label small">Country</label>
                                                <select class="form-select" name="elementary_state" required>
                                                    <option value="">--- Select country ---</option>
                                                    <?php
                                                    $countries = [
                                                        'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AS' => 'American Samoa', 'AD' => 'Andorra',
                                                        'AO' => 'Angola', 'AI' => 'Anguilla', 'AQ' => 'Antarctica', 'AG' => 'Antigua and Barbuda', 'AR' => 'Argentina',
                                                        'AM' => 'Armenia', 'AW' => 'Aruba', 'AU' => 'Australia', 'AT' => 'Austria', 'AZ' => 'Azerbaijan',
                                                        'BS' => 'Bahamas', 'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados', 'BY' => 'Belarus',
                                                        'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin', 'BM' => 'Bermuda', 'BT' => 'Bhutan',
                                                        'BO' => 'Bolivia', 'BA' => 'Bosnia and Herzegovina', 'BW' => 'Botswana', 'BV' => 'Bouvet Island', 'BR' => 'Brazil',
                                                        'IO' => 'British Indian Ocean Territory', 'BN' => 'Brunei', 'BG' => 'Bulgaria', 'BF' => 'Burkina Faso', 'BI' => 'Burundi',
                                                        'KH' => 'Cambodia', 'CM' => 'Cameroon', 'CA' => 'Canada', 'CV' => 'Cape Verde', 'KY' => 'Cayman Islands',
                                                        'CF' => 'Central African Republic', 'TD' => 'Chad', 'CL' => 'Chile', 'CN' => 'China', 'CX' => 'Christmas Island',
                                                        'CC' => 'Cocos Islands', 'CO' => 'Colombia', 'KM' => 'Comoros', 'CG' => 'Congo', 'CD' => 'Congo (Democratic Republic)',
                                                        'CK' => 'Cook Islands', 'CR' => 'Costa Rica', 'CI' => 'Cote d\'Ivoire', 'HR' => 'Croatia', 'CU' => 'Cuba',
                                                        'CY' => 'Cyprus', 'CZ' => 'Czech Republic', 'DK' => 'Denmark', 'DJ' => 'Djibouti', 'DM' => 'Dominica',
                                                        'DO' => 'Dominican Republic', 'EC' => 'Ecuador', 'EG' => 'Egypt', 'SV' => 'El Salvador', 'GQ' => 'Equatorial Guinea',
                                                        'ER' => 'Eritrea', 'EE' => 'Estonia', 'ET' => 'Ethiopia', 'FK' => 'Falkland Islands', 'FO' => 'Faroe Islands',
                                                        'FJ' => 'Fiji', 'FI' => 'Finland', 'FR' => 'France', 'GF' => 'French Guiana', 'PF' => 'French Polynesia',
                                                        'TF' => 'French Southern Territories', 'GA' => 'Gabon', 'GM' => 'Gambia', 'GE' => 'Georgia', 'DE' => 'Germany',
                                                        'GH' => 'Ghana', 'GI' => 'Gibraltar', 'GR' => 'Greece', 'GL' => 'Greenland', 'GD' => 'Grenada',
                                                        'GP' => 'Guadeloupe', 'GU' => 'Guam', 'GT' => 'Guatemala', 'GG' => 'Guernsey', 'GN' => 'Guinea',
                                                        'GW' => 'Guinea-Bissau', 'GY' => 'Guyana', 'HT' => 'Haiti', 'HM' => 'Heard and McDonald Islands', 'HN' => 'Honduras',
                                                        'HK' => 'Hong Kong', 'HU' => 'Hungary', 'IS' => 'Iceland', 'IN' => 'India', 'ID' => 'Indonesia',
                                                        'IR' => 'Iran', 'IQ' => 'Iraq', 'IE' => 'Ireland', 'IM' => 'Isle of Man', 'IL' => 'Israel',
                                                        'IT' => 'Italy', 'JM' => 'Jamaica', 'JP' => 'Japan', 'JE' => 'Jersey', 'JO' => 'Jordan',
                                                        'KZ' => 'Kazakhstan', 'KE' => 'Kenya', 'KI' => 'Kiribati', 'KP' => 'North Korea', 'KR' => 'South Korea',
                                                        'KW' => 'Kuwait', 'KG' => 'Kyrgyzstan', 'LA' => 'Laos', 'LV' => 'Latvia', 'LB' => 'Lebanon',
                                                        'LS' => 'Lesotho', 'LR' => 'Liberia', 'LY' => 'Libya', 'LI' => 'Liechtenstein', 'LT' => 'Lithuania',
                                                        'LU' => 'Luxembourg', 'MO' => 'Macao', 'MK' => 'Macedonia', 'MG' => 'Madagascar', 'MW' => 'Malawi',
                                                        'MY' => 'Malaysia', 'MV' => 'Maldives', 'ML' => 'Mali', 'MT' => 'Malta', 'MH' => 'Marshall Islands',
                                                        'MQ' => 'Martinique', 'MR' => 'Mauritania', 'MU' => 'Mauritius', 'YT' => 'Mayotte', 'MX' => 'Mexico',
                                                        'FM' => 'Micronesia', 'MD' => 'Moldova', 'MC' => 'Monaco', 'MN' => 'Mongolia', 'ME' => 'Montenegro',
                                                        'MS' => 'Montserrat', 'MA' => 'Morocco', 'MZ' => 'Mozambique', 'MM' => 'Myanmar', 'NA' => 'Namibia',
                                                        'NR' => 'Nauru', 'NP' => 'Nepal', 'NL' => 'Netherlands', 'AN' => 'Netherlands Antilles', 'NC' => 'New Caledonia',
                                                        'NZ' => 'New Zealand', 'NI' => 'Nicaragua', 'NE' => 'Niger', 'NG' => 'Nigeria', 'NU' => 'Niue',
                                                        'NF' => 'Norfolk Island', 'MP' => 'Northern Mariana Islands', 'NO' => 'Norway', 'OM' => 'Oman', 'PK' => 'Pakistan',
                                                        'PW' => 'Palau', 'PS' => 'Palestine', 'PA' => 'Panama', 'PG' => 'Papua New Guinea', 'PY' => 'Paraguay',
                                                        'PE' => 'Peru', 'PH' => 'Philippines', 'PN' => 'Pitcairn', 'PL' => 'Poland', 'PT' => 'Portugal',
                                                        'PR' => 'Puerto Rico', 'QA' => 'Qatar', 'RE' => 'Reunion', 'RO' => 'Romania', 'RU' => 'Russia',
                                                        'RW' => 'Rwanda', 'BL' => 'Saint Barthelemy', 'SH' => 'Saint Helena', 'KN' => 'Saint Kitts and Nevis',
                                                        'LC' => 'Saint Lucia', 'MF' => 'Saint Martin', 'PM' => 'Saint Pierre and Miquelon', 'VC' => 'Saint Vincent and the Grenadines',
                                                        'WS' => 'Samoa', 'SM' => 'San Marino', 'ST' => 'Sao Tome and Principe', 'SA' => 'Saudi Arabia', 'SN' => 'Senegal',
                                                        'RS' => 'Serbia', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone', 'SG' => 'Singapore', 'SK' => 'Slovakia',
                                                        'SI' => 'Slovenia', 'SB' => 'Solomon Islands', 'SO' => 'Somalia', 'ZA' => 'South Africa', 'GS' => 'South Georgia',
                                                        'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SD' => 'Sudan', 'SR' => 'Suriname', 'SJ' => 'Svalbard and Jan Mayen',
                                                        'SZ' => 'Swaziland', 'SE' => 'Sweden', 'CH' => 'Switzerland', 'SY' => 'Syria', 'TW' => 'Taiwan',
                                                        'TJ' => 'Tajikistan', 'TZ' => 'Tanzania', 'TH' => 'Thailand', 'TL' => 'Timor-Leste', 'TG' => 'Togo',
                                                        'TK' => 'Tokelau', 'TO' => 'Tonga', 'TT' => 'Trinidad and Tobago', 'TN' => 'Tunisia', 'TR' => 'Turkey',
                                                        'TM' => 'Turkmenistan', 'TC' => 'Turks and Caicos Islands', 'TV' => 'Tuvalu', 'UG' => 'Uganda', 'UA' => 'Ukraine',
                                                        'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States', 'UY' => 'Uruguay',
                                                        'UZ' => 'Uzbekistan', 'VU' => 'Vanuatu', 'VA' => 'Vatican City', 'VE' => 'Venezuela', 'VN' => 'Vietnam',
                                                        'VG' => 'Virgin Islands (British)', 'VI' => 'Virgin Islands (US)', 'WF' => 'Wallis and Futuna', 'EH' => 'Western Sahara',
                                                        'YE' => 'Yemen', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe'
                                                    ];
                                                    foreach ($countries as $code => $name):
                                                    ?>
                                                        <option value="<?php echo $code; ?>" <?php echo (isset($_SESSION['nclex_registration_data']['elementary_state']) && $_SESSION['nclex_registration_data']['elementary_state'] == $code) ? 'selected' : ''; ?>>
                                                            <?php echo $name; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="form-label small">Zip Code</label>
                                                <input type="text" class="form-control" name="elementary_zip_code" 
                                                       value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['elementary_zip_code'] ?? ''); ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Entry Date</label>
                                            <input type="date" class="form-control" name="elementary_entry_date" 
                                                   value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['elementary_entry_date'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Exit Date</label>
                                            <input type="date" class="form-control" name="elementary_exit_date" 
                                                   value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['elementary_exit_date'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">From Which Grade To Which Grade</label>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <input type="text" class="form-control" name="elementary_grade_from" 
                                                       placeholder="From Grade" 
                                                       value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['elementary_grade_from'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <input type="text" class="form-control" name="elementary_grade_to" 
                                                       placeholder="To Grade" 
                                                       value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['elementary_grade_to'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Were You In Another School? <span class="text-danger">*</span></label>
                                        <div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="elementary_another_school" value="Yes" 
                                                       <?php echo (isset($_SESSION['nclex_registration_data']['elementary_another_school']) && $_SESSION['nclex_registration_data']['elementary_another_school'] == 'Yes') ? 'checked' : ''; ?> 
                                                       required onchange="toggleElementaryAnotherSchool()">
                                                <label class="form-check-label">Yes</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="elementary_another_school" value="No" 
                                                       <?php echo (isset($_SESSION['nclex_registration_data']['elementary_another_school']) && $_SESSION['nclex_registration_data']['elementary_another_school'] == 'No') ? 'checked' : ''; ?> 
                                                       required onchange="toggleElementaryAnotherSchool()">
                                                <label class="form-check-label">No</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3" id="elementaryAnotherSchoolField" style="display: none;">
                                        <label class="form-label">School Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="elementary_another_school_name" 
                                               value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['elementary_another_school_name'] ?? ''); ?>">
                                    </div>

                                    <div class="d-flex justify-content-between">
                                        <button type="button" class="btn btn-outline-secondary btn-lg px-5" onclick="goToStep1()">
                                            <i class="fa fa-arrow-left me-2"></i>Back
                                        </button>
                                        <button type="button" class="btn btn-primary btn-lg px-5" onclick="goToStep3()">
                                            Next: Step 3 <i class="fa fa-arrow-right ms-2"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Step 3: High School -->
                                <div class="form-step" id="step3">
                                    <h3 class="text-primary mb-4">Step 3: High School</h3>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">High School Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="high_school_name" 
                                               value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['high_school_name'] ?? ''); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Address <span class="text-danger">*</span></label>
                                        <div class="mb-2">
                                            <label class="form-label small">Address Line 1</label>
                                            <input type="text" class="form-control" name="high_school_address_line1" 
                                                   value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['high_school_address_line1'] ?? ''); ?>" required>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small">Address Line 2</label>
                                            <input type="text" class="form-control" name="high_school_address_line2" 
                                                   value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['high_school_address_line2'] ?? ''); ?>">
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4 mb-2">
                                                <label class="form-label small">City</label>
                                                <input type="text" class="form-control" name="high_school_city" 
                                                       value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['high_school_city'] ?? ''); ?>" required>
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="form-label small">Country</label>
                                                <select class="form-select" name="high_school_state" required>
                                                    <option value="">--- Select country ---</option>
                                                    <?php
                                                    $countries = [
                                                        'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AS' => 'American Samoa', 'AD' => 'Andorra',
                                                        'AO' => 'Angola', 'AI' => 'Anguilla', 'AQ' => 'Antarctica', 'AG' => 'Antigua and Barbuda', 'AR' => 'Argentina',
                                                        'AM' => 'Armenia', 'AW' => 'Aruba', 'AU' => 'Australia', 'AT' => 'Austria', 'AZ' => 'Azerbaijan',
                                                        'BS' => 'Bahamas', 'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados', 'BY' => 'Belarus',
                                                        'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin', 'BM' => 'Bermuda', 'BT' => 'Bhutan',
                                                        'BO' => 'Bolivia', 'BA' => 'Bosnia and Herzegovina', 'BW' => 'Botswana', 'BV' => 'Bouvet Island', 'BR' => 'Brazil',
                                                        'IO' => 'British Indian Ocean Territory', 'BN' => 'Brunei', 'BG' => 'Bulgaria', 'BF' => 'Burkina Faso', 'BI' => 'Burundi',
                                                        'KH' => 'Cambodia', 'CM' => 'Cameroon', 'CA' => 'Canada', 'CV' => 'Cape Verde', 'KY' => 'Cayman Islands',
                                                        'CF' => 'Central African Republic', 'TD' => 'Chad', 'CL' => 'Chile', 'CN' => 'China', 'CX' => 'Christmas Island',
                                                        'CC' => 'Cocos Islands', 'CO' => 'Colombia', 'KM' => 'Comoros', 'CG' => 'Congo', 'CD' => 'Congo (Democratic Republic)',
                                                        'CK' => 'Cook Islands', 'CR' => 'Costa Rica', 'CI' => 'Cote d\'Ivoire', 'HR' => 'Croatia', 'CU' => 'Cuba',
                                                        'CY' => 'Cyprus', 'CZ' => 'Czech Republic', 'DK' => 'Denmark', 'DJ' => 'Djibouti', 'DM' => 'Dominica',
                                                        'DO' => 'Dominican Republic', 'EC' => 'Ecuador', 'EG' => 'Egypt', 'SV' => 'El Salvador', 'GQ' => 'Equatorial Guinea',
                                                        'ER' => 'Eritrea', 'EE' => 'Estonia', 'ET' => 'Ethiopia', 'FK' => 'Falkland Islands', 'FO' => 'Faroe Islands',
                                                        'FJ' => 'Fiji', 'FI' => 'Finland', 'FR' => 'France', 'GF' => 'French Guiana', 'PF' => 'French Polynesia',
                                                        'TF' => 'French Southern Territories', 'GA' => 'Gabon', 'GM' => 'Gambia', 'GE' => 'Georgia', 'DE' => 'Germany',
                                                        'GH' => 'Ghana', 'GI' => 'Gibraltar', 'GR' => 'Greece', 'GL' => 'Greenland', 'GD' => 'Grenada',
                                                        'GP' => 'Guadeloupe', 'GU' => 'Guam', 'GT' => 'Guatemala', 'GG' => 'Guernsey', 'GN' => 'Guinea',
                                                        'GW' => 'Guinea-Bissau', 'GY' => 'Guyana', 'HT' => 'Haiti', 'HM' => 'Heard and McDonald Islands', 'HN' => 'Honduras',
                                                        'HK' => 'Hong Kong', 'HU' => 'Hungary', 'IS' => 'Iceland', 'IN' => 'India', 'ID' => 'Indonesia',
                                                        'IR' => 'Iran', 'IQ' => 'Iraq', 'IE' => 'Ireland', 'IM' => 'Isle of Man', 'IL' => 'Israel',
                                                        'IT' => 'Italy', 'JM' => 'Jamaica', 'JP' => 'Japan', 'JE' => 'Jersey', 'JO' => 'Jordan',
                                                        'KZ' => 'Kazakhstan', 'KE' => 'Kenya', 'KI' => 'Kiribati', 'KP' => 'North Korea', 'KR' => 'South Korea',
                                                        'KW' => 'Kuwait', 'KG' => 'Kyrgyzstan', 'LA' => 'Laos', 'LV' => 'Latvia', 'LB' => 'Lebanon',
                                                        'LS' => 'Lesotho', 'LR' => 'Liberia', 'LY' => 'Libya', 'LI' => 'Liechtenstein', 'LT' => 'Lithuania',
                                                        'LU' => 'Luxembourg', 'MO' => 'Macao', 'MK' => 'Macedonia', 'MG' => 'Madagascar', 'MW' => 'Malawi',
                                                        'MY' => 'Malaysia', 'MV' => 'Maldives', 'ML' => 'Mali', 'MT' => 'Malta', 'MH' => 'Marshall Islands',
                                                        'MQ' => 'Martinique', 'MR' => 'Mauritania', 'MU' => 'Mauritius', 'YT' => 'Mayotte', 'MX' => 'Mexico',
                                                        'FM' => 'Micronesia', 'MD' => 'Moldova', 'MC' => 'Monaco', 'MN' => 'Mongolia', 'ME' => 'Montenegro',
                                                        'MS' => 'Montserrat', 'MA' => 'Morocco', 'MZ' => 'Mozambique', 'MM' => 'Myanmar', 'NA' => 'Namibia',
                                                        'NR' => 'Nauru', 'NP' => 'Nepal', 'NL' => 'Netherlands', 'AN' => 'Netherlands Antilles', 'NC' => 'New Caledonia',
                                                        'NZ' => 'New Zealand', 'NI' => 'Nicaragua', 'NE' => 'Niger', 'NG' => 'Nigeria', 'NU' => 'Niue',
                                                        'NF' => 'Norfolk Island', 'MP' => 'Northern Mariana Islands', 'NO' => 'Norway', 'OM' => 'Oman', 'PK' => 'Pakistan',
                                                        'PW' => 'Palau', 'PS' => 'Palestine', 'PA' => 'Panama', 'PG' => 'Papua New Guinea', 'PY' => 'Paraguay',
                                                        'PE' => 'Peru', 'PH' => 'Philippines', 'PN' => 'Pitcairn', 'PL' => 'Poland', 'PT' => 'Portugal',
                                                        'PR' => 'Puerto Rico', 'QA' => 'Qatar', 'RE' => 'Reunion', 'RO' => 'Romania', 'RU' => 'Russia',
                                                        'RW' => 'Rwanda', 'BL' => 'Saint Barthelemy', 'SH' => 'Saint Helena', 'KN' => 'Saint Kitts and Nevis',
                                                        'LC' => 'Saint Lucia', 'MF' => 'Saint Martin', 'PM' => 'Saint Pierre and Miquelon', 'VC' => 'Saint Vincent and the Grenadines',
                                                        'WS' => 'Samoa', 'SM' => 'San Marino', 'ST' => 'Sao Tome and Principe', 'SA' => 'Saudi Arabia', 'SN' => 'Senegal',
                                                        'RS' => 'Serbia', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone', 'SG' => 'Singapore', 'SK' => 'Slovakia',
                                                        'SI' => 'Slovenia', 'SB' => 'Solomon Islands', 'SO' => 'Somalia', 'ZA' => 'South Africa', 'GS' => 'South Georgia',
                                                        'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SD' => 'Sudan', 'SR' => 'Suriname', 'SJ' => 'Svalbard and Jan Mayen',
                                                        'SZ' => 'Swaziland', 'SE' => 'Sweden', 'CH' => 'Switzerland', 'SY' => 'Syria', 'TW' => 'Taiwan',
                                                        'TJ' => 'Tajikistan', 'TZ' => 'Tanzania', 'TH' => 'Thailand', 'TL' => 'Timor-Leste', 'TG' => 'Togo',
                                                        'TK' => 'Tokelau', 'TO' => 'Tonga', 'TT' => 'Trinidad and Tobago', 'TN' => 'Tunisia', 'TR' => 'Turkey',
                                                        'TM' => 'Turkmenistan', 'TC' => 'Turks and Caicos Islands', 'TV' => 'Tuvalu', 'UG' => 'Uganda', 'UA' => 'Ukraine',
                                                        'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States', 'UY' => 'Uruguay',
                                                        'UZ' => 'Uzbekistan', 'VU' => 'Vanuatu', 'VA' => 'Vatican City', 'VE' => 'Venezuela', 'VN' => 'Vietnam',
                                                        'VG' => 'Virgin Islands (British)', 'VI' => 'Virgin Islands (US)', 'WF' => 'Wallis and Futuna', 'EH' => 'Western Sahara',
                                                        'YE' => 'Yemen', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe'
                                                    ];
                                                    foreach ($countries as $code => $name):
                                                    ?>
                                                        <option value="<?php echo $code; ?>" <?php echo (isset($_SESSION['nclex_registration_data']['high_school_state']) && $_SESSION['nclex_registration_data']['high_school_state'] == $code) ? 'selected' : ''; ?>>
                                                            <?php echo $name; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="form-label small">Zip Code</label>
                                                <input type="text" class="form-control" name="high_school_zip_code" 
                                                       value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['high_school_zip_code'] ?? ''); ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Entry Date</label>
                                            <input type="date" class="form-control" name="high_school_entry_date" 
                                                   value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['high_school_entry_date'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Exit Date</label>
                                            <input type="date" class="form-control" name="high_school_exit_date" 
                                                   value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['high_school_exit_date'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">From Which Grade To Which Grade</label>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <input type="text" class="form-control" name="high_school_grade_from" 
                                                       placeholder="From Grade" 
                                                       value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['high_school_grade_from'] ?? ''); ?>">
                                            </div>
                                            <div class="col-md-6">
                                                <input type="text" class="form-control" name="high_school_grade_to" 
                                                       placeholder="To Grade" 
                                                       value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['high_school_grade_to'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Were You In Another High School? <span class="text-danger">*</span></label>
                                        <div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="high_school_another_school" value="No" 
                                                       <?php echo (isset($_SESSION['nclex_registration_data']['high_school_another_school']) && $_SESSION['nclex_registration_data']['high_school_another_school'] == 'No') ? 'checked' : ''; ?> 
                                                       required onchange="toggleHighSchoolAnotherSchool()">
                                                <label class="form-check-label">No</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="high_school_another_school" value="Yes" 
                                                       <?php echo (isset($_SESSION['nclex_registration_data']['high_school_another_school']) && $_SESSION['nclex_registration_data']['high_school_another_school'] == 'Yes') ? 'checked' : ''; ?> 
                                                       required onchange="toggleHighSchoolAnotherSchool()">
                                                <label class="form-check-label">Yes</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3" id="highSchoolAnotherSchoolField" style="display: none;">
                                        <label class="form-label">High School Name</label>
                                        <input type="text" class="form-control" name="high_school_another_school_name" 
                                               value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['high_school_another_school_name'] ?? ''); ?>">
                                    </div>

                                    <div class="d-flex justify-content-between">
                                        <button type="button" class="btn btn-outline-secondary btn-lg px-5" onclick="goToStep2()">
                                            <i class="fa fa-arrow-left me-2"></i>Back
                                        </button>
                                        <button type="button" class="btn btn-primary btn-lg px-5" onclick="goToStep4()">
                                            Next: Step 4 <i class="fa fa-arrow-right ms-2"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Step 4: University -->
                                <div class="form-step" id="step4">
                                    <h3 class="text-primary mb-4">Step 4: University</h3>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">University Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="university_name" 
                                               value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['university_name'] ?? ''); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Address <span class="text-danger">*</span></label>
                                        <div class="mb-2">
                                            <label class="form-label small">Address Line 1</label>
                                            <input type="text" class="form-control" name="university_address_line1" 
                                                   value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['university_address_line1'] ?? ''); ?>" required>
                                        </div>
                                        <div class="mb-2">
                                            <label class="form-label small">Address Line 2</label>
                                            <input type="text" class="form-control" name="university_address_line2" 
                                                   value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['university_address_line2'] ?? ''); ?>">
                                        </div>
                                        <div class="row">
                                            <div class="col-md-4 mb-2">
                                                <label class="form-label small">City</label>
                                                <input type="text" class="form-control" name="university_city" 
                                                       value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['university_city'] ?? ''); ?>" required>
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="form-label small">Country</label>
                                                <select class="form-select" name="university_state" required>
                                                    <option value="">--- Select country ---</option>
                                                    <?php
                                                    $countries = [
                                                        'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AS' => 'American Samoa', 'AD' => 'Andorra',
                                                        'AO' => 'Angola', 'AI' => 'Anguilla', 'AQ' => 'Antarctica', 'AG' => 'Antigua and Barbuda', 'AR' => 'Argentina',
                                                        'AM' => 'Armenia', 'AW' => 'Aruba', 'AU' => 'Australia', 'AT' => 'Austria', 'AZ' => 'Azerbaijan',
                                                        'BS' => 'Bahamas', 'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados', 'BY' => 'Belarus',
                                                        'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin', 'BM' => 'Bermuda', 'BT' => 'Bhutan',
                                                        'BO' => 'Bolivia', 'BA' => 'Bosnia and Herzegovina', 'BW' => 'Botswana', 'BV' => 'Bouvet Island', 'BR' => 'Brazil',
                                                        'IO' => 'British Indian Ocean Territory', 'BN' => 'Brunei', 'BG' => 'Bulgaria', 'BF' => 'Burkina Faso', 'BI' => 'Burundi',
                                                        'KH' => 'Cambodia', 'CM' => 'Cameroon', 'CA' => 'Canada', 'CV' => 'Cape Verde', 'KY' => 'Cayman Islands',
                                                        'CF' => 'Central African Republic', 'TD' => 'Chad', 'CL' => 'Chile', 'CN' => 'China', 'CX' => 'Christmas Island',
                                                        'CC' => 'Cocos Islands', 'CO' => 'Colombia', 'KM' => 'Comoros', 'CG' => 'Congo', 'CD' => 'Congo (Democratic Republic)',
                                                        'CK' => 'Cook Islands', 'CR' => 'Costa Rica', 'CI' => 'Cote d\'Ivoire', 'HR' => 'Croatia', 'CU' => 'Cuba',
                                                        'CY' => 'Cyprus', 'CZ' => 'Czech Republic', 'DK' => 'Denmark', 'DJ' => 'Djibouti', 'DM' => 'Dominica',
                                                        'DO' => 'Dominican Republic', 'EC' => 'Ecuador', 'EG' => 'Egypt', 'SV' => 'El Salvador', 'GQ' => 'Equatorial Guinea',
                                                        'ER' => 'Eritrea', 'EE' => 'Estonia', 'ET' => 'Ethiopia', 'FK' => 'Falkland Islands', 'FO' => 'Faroe Islands',
                                                        'FJ' => 'Fiji', 'FI' => 'Finland', 'FR' => 'France', 'GF' => 'French Guiana', 'PF' => 'French Polynesia',
                                                        'TF' => 'French Southern Territories', 'GA' => 'Gabon', 'GM' => 'Gambia', 'GE' => 'Georgia', 'DE' => 'Germany',
                                                        'GH' => 'Ghana', 'GI' => 'Gibraltar', 'GR' => 'Greece', 'GL' => 'Greenland', 'GD' => 'Grenada',
                                                        'GP' => 'Guadeloupe', 'GU' => 'Guam', 'GT' => 'Guatemala', 'GG' => 'Guernsey', 'GN' => 'Guinea',
                                                        'GW' => 'Guinea-Bissau', 'GY' => 'Guyana', 'HT' => 'Haiti', 'HM' => 'Heard and McDonald Islands', 'HN' => 'Honduras',
                                                        'HK' => 'Hong Kong', 'HU' => 'Hungary', 'IS' => 'Iceland', 'IN' => 'India', 'ID' => 'Indonesia',
                                                        'IR' => 'Iran', 'IQ' => 'Iraq', 'IE' => 'Ireland', 'IM' => 'Isle of Man', 'IL' => 'Israel',
                                                        'IT' => 'Italy', 'JM' => 'Jamaica', 'JP' => 'Japan', 'JE' => 'Jersey', 'JO' => 'Jordan',
                                                        'KZ' => 'Kazakhstan', 'KE' => 'Kenya', 'KI' => 'Kiribati', 'KP' => 'North Korea', 'KR' => 'South Korea',
                                                        'KW' => 'Kuwait', 'KG' => 'Kyrgyzstan', 'LA' => 'Laos', 'LV' => 'Latvia', 'LB' => 'Lebanon',
                                                        'LS' => 'Lesotho', 'LR' => 'Liberia', 'LY' => 'Libya', 'LI' => 'Liechtenstein', 'LT' => 'Lithuania',
                                                        'LU' => 'Luxembourg', 'MO' => 'Macao', 'MK' => 'Macedonia', 'MG' => 'Madagascar', 'MW' => 'Malawi',
                                                        'MY' => 'Malaysia', 'MV' => 'Maldives', 'ML' => 'Mali', 'MT' => 'Malta', 'MH' => 'Marshall Islands',
                                                        'MQ' => 'Martinique', 'MR' => 'Mauritania', 'MU' => 'Mauritius', 'YT' => 'Mayotte', 'MX' => 'Mexico',
                                                        'FM' => 'Micronesia', 'MD' => 'Moldova', 'MC' => 'Monaco', 'MN' => 'Mongolia', 'ME' => 'Montenegro',
                                                        'MS' => 'Montserrat', 'MA' => 'Morocco', 'MZ' => 'Mozambique', 'MM' => 'Myanmar', 'NA' => 'Namibia',
                                                        'NR' => 'Nauru', 'NP' => 'Nepal', 'NL' => 'Netherlands', 'AN' => 'Netherlands Antilles', 'NC' => 'New Caledonia',
                                                        'NZ' => 'New Zealand', 'NI' => 'Nicaragua', 'NE' => 'Niger', 'NG' => 'Nigeria', 'NU' => 'Niue',
                                                        'NF' => 'Norfolk Island', 'MP' => 'Northern Mariana Islands', 'NO' => 'Norway', 'OM' => 'Oman', 'PK' => 'Pakistan',
                                                        'PW' => 'Palau', 'PS' => 'Palestine', 'PA' => 'Panama', 'PG' => 'Papua New Guinea', 'PY' => 'Paraguay',
                                                        'PE' => 'Peru', 'PH' => 'Philippines', 'PN' => 'Pitcairn', 'PL' => 'Poland', 'PT' => 'Portugal',
                                                        'PR' => 'Puerto Rico', 'QA' => 'Qatar', 'RE' => 'Reunion', 'RO' => 'Romania', 'RU' => 'Russia',
                                                        'RW' => 'Rwanda', 'BL' => 'Saint Barthelemy', 'SH' => 'Saint Helena', 'KN' => 'Saint Kitts and Nevis',
                                                        'LC' => 'Saint Lucia', 'MF' => 'Saint Martin', 'PM' => 'Saint Pierre and Miquelon', 'VC' => 'Saint Vincent and the Grenadines',
                                                        'WS' => 'Samoa', 'SM' => 'San Marino', 'ST' => 'Sao Tome and Principe', 'SA' => 'Saudi Arabia', 'SN' => 'Senegal',
                                                        'RS' => 'Serbia', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone', 'SG' => 'Singapore', 'SK' => 'Slovakia',
                                                        'SI' => 'Slovenia', 'SB' => 'Solomon Islands', 'SO' => 'Somalia', 'ZA' => 'South Africa', 'GS' => 'South Georgia',
                                                        'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SD' => 'Sudan', 'SR' => 'Suriname', 'SJ' => 'Svalbard and Jan Mayen',
                                                        'SZ' => 'Swaziland', 'SE' => 'Sweden', 'CH' => 'Switzerland', 'SY' => 'Syria', 'TW' => 'Taiwan',
                                                        'TJ' => 'Tajikistan', 'TZ' => 'Tanzania', 'TH' => 'Thailand', 'TL' => 'Timor-Leste', 'TG' => 'Togo',
                                                        'TK' => 'Tokelau', 'TO' => 'Tonga', 'TT' => 'Trinidad and Tobago', 'TN' => 'Tunisia', 'TR' => 'Turkey',
                                                        'TM' => 'Turkmenistan', 'TC' => 'Turks and Caicos Islands', 'TV' => 'Tuvalu', 'UG' => 'Uganda', 'UA' => 'Ukraine',
                                                        'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States', 'UY' => 'Uruguay',
                                                        'UZ' => 'Uzbekistan', 'VU' => 'Vanuatu', 'VA' => 'Vatican City', 'VE' => 'Venezuela', 'VN' => 'Vietnam',
                                                        'VG' => 'Virgin Islands (British)', 'VI' => 'Virgin Islands (US)', 'WF' => 'Wallis and Futuna', 'EH' => 'Western Sahara',
                                                        'YE' => 'Yemen', 'ZM' => 'Zambia', 'ZW' => 'Zimbabwe'
                                                    ];
                                                    foreach ($countries as $code => $name):
                                                    ?>
                                                        <option value="<?php echo $code; ?>" <?php echo (isset($_SESSION['nclex_registration_data']['university_state']) && $_SESSION['nclex_registration_data']['university_state'] == $code) ? 'selected' : ''; ?>>
                                                            <?php echo $name; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-4 mb-2">
                                                <label class="form-label small">Zip Code</label>
                                                <input type="text" class="form-control" name="university_zip_code" 
                                                       value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['university_zip_code'] ?? ''); ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Entry Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" name="university_entry_date" 
                                                   value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['university_entry_date'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Exit Date</label>
                                            <input type="date" class="form-control" name="university_exit_date" 
                                                   value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['university_exit_date'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Number Of Years <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" name="university_years" min="1" 
                                               value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['university_years'] ?? ''); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Have You Been To Another University? <span class="text-danger">*</span></label>
                                        <div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="university_another" value="Yes" 
                                                       <?php echo (isset($_SESSION['nclex_registration_data']['university_another']) && $_SESSION['nclex_registration_data']['university_another'] == 'Yes') ? 'checked' : ''; ?> 
                                                       required onchange="toggleUniversityAnother()">
                                                <label class="form-check-label">Yes</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="university_another" value="No" 
                                                       <?php echo (isset($_SESSION['nclex_registration_data']['university_another']) && $_SESSION['nclex_registration_data']['university_another'] == 'No') ? 'checked' : ''; ?> 
                                                       required onchange="toggleUniversityAnother()">
                                                <label class="form-check-label">No</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3" id="universityAnotherField" style="display: none;">
                                        <label class="form-label">University Name</label>
                                        <input type="text" class="form-control" name="university_another_name" 
                                               value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['university_another_name'] ?? ''); ?>">
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Do You Have A Specialization? <span class="text-danger">*</span></label>
                                        <div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="university_specialization" value="Yes" 
                                                       <?php echo (isset($_SESSION['nclex_registration_data']['university_specialization']) && $_SESSION['nclex_registration_data']['university_specialization'] == 'Yes') ? 'checked' : ''; ?> 
                                                       required onchange="toggleSpecialization()">
                                                <label class="form-check-label">Yes</label>
                                            </div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="radio" name="university_specialization" value="No" 
                                                       <?php echo (isset($_SESSION['nclex_registration_data']['university_specialization']) && $_SESSION['nclex_registration_data']['university_specialization'] == 'No') ? 'checked' : ''; ?> 
                                                       required onchange="toggleSpecialization()">
                                                <label class="form-check-label">No</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3" id="specializationField" style="display: none;">
                                        <label class="form-label">Specialization</label>
                                        <input type="text" class="form-control" name="specialization" 
                                               value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['specialization'] ?? ''); ?>">
                                    </div>

                                    <div class="d-flex justify-content-between">
                                        <button type="button" class="btn btn-outline-secondary btn-lg px-5" onclick="goToStep3()">
                                            <i class="fa fa-arrow-left me-2"></i>Back
                                        </button>
                                        <button type="button" class="btn btn-primary btn-lg px-5" onclick="goToStep5()">
                                            Next: Step 5 <i class="fa fa-arrow-right ms-2"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Step 5: Payment -->
                                <div class="form-step" id="step5">
                                    <h3 class="text-primary mb-4">Step 5: Payment</h3>
                                    
                                    <!-- Payment Summary -->
                                    <div class="alert alert-info mb-4">
                                        <h5 class="alert-heading"><i class="fa fa-credit-card me-2"></i>Payment Summary</h5>
                                        <div class="row mb-0">
                                            <div class="col-md-6">
                                                <p class="mb-2"><strong>Registration Fee:</strong> $<?php echo number_format(2500.00, 2); ?></p>
                                                <p class="mb-2"><strong>Tax:</strong> $<?php echo number_format(0.00, 2); ?></p>
                                            </div>
                                            <div class="col-md-6 text-md-end">
                                                <p class="mb-0"><strong>Total:</strong> <span class="text-primary fw-bold fs-4">$<?php echo number_format(2500.00, 2); ?></span></p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Payment Methods -->
                                    <div class="mb-4">
                                        <label class="form-label fw-bold">Secure Payment Options <span class="text-danger">*</span></label>
                                        <div class="list-group">
                                            <?php if (isset($enabledMethods['stripe'])): ?>
                                                <label class="list-group-item">
                                                    <input class="form-check-input me-2" type="radio" name="payment_method" 
                                                           value="stripe" 
                                                           <?php echo (isset($_SESSION['nclex_registration_data']['payment_method']) && $_SESSION['nclex_registration_data']['payment_method'] == 'stripe') ? 'checked' : ''; ?>
                                                           required onchange="toggleTransactionId()">
                                                    <i class="fab fa-cc-stripe me-2 text-primary"></i>Credit/Debit Card (Stripe)
                                                    <span class="badge bg-success ms-2">Secure</span>
                                                </label>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($enabledMethods['paypal'])): ?>
                                                <label class="list-group-item">
                                                    <input class="form-check-input me-2" type="radio" name="payment_method" 
                                                           value="paypal" 
                                                           <?php echo (isset($_SESSION['nclex_registration_data']['payment_method']) && $_SESSION['nclex_registration_data']['payment_method'] == 'paypal') ? 'checked' : ''; ?>
                                                           required onchange="toggleTransactionId()">
                                                    <i class="fab fa-paypal me-2 text-primary"></i>PayPal
                                                    <span class="badge bg-success ms-2">Secure</span>
                                                </label>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($enabledMethods['zelle'])): ?>
                                                <label class="list-group-item">
                                                    <input class="form-check-input me-2" type="radio" name="payment_method" 
                                                           value="zelle" 
                                                           <?php echo (isset($_SESSION['nclex_registration_data']['payment_method']) && $_SESSION['nclex_registration_data']['payment_method'] == 'zelle') ? 'checked' : ''; ?>
                                                           required onchange="toggleTransactionId()">
                                                    <i class="fa fa-mobile-alt me-2 text-primary"></i>Zelle
                                                    <small class="text-muted ms-2">(Manual Verification)</small>
                                                </label>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($enabledMethods['cashapp'])): ?>
                                                <label class="list-group-item">
                                                    <input class="form-check-input me-2" type="radio" name="payment_method" 
                                                       value="cashapp" 
                                                       <?php echo (isset($_SESSION['nclex_registration_data']['payment_method']) && $_SESSION['nclex_registration_data']['payment_method'] == 'cashapp') ? 'checked' : ''; ?>
                                                       required onchange="toggleTransactionId()">
                                                    <i class="fa fa-dollar-sign me-2 text-primary"></i>Cash App
                                                    <small class="text-muted ms-2">(Manual Verification)</small>
                                                </label>
                                            <?php endif; ?>
                                            
                                            <?php if (isset($enabledMethods['bank_deposit'])): ?>
                                                <label class="list-group-item">
                                                    <input class="form-check-input me-2" type="radio" name="payment_method" 
                                                       value="bank_deposit" 
                                                       <?php echo (isset($_SESSION['nclex_registration_data']['payment_method']) && $_SESSION['nclex_registration_data']['payment_method'] == 'bank_deposit') ? 'checked' : ''; ?>
                                                       required onchange="toggleTransactionId()">
                                                    <i class="fa fa-university me-2 text-primary"></i>Bank Deposit
                                                    <small class="text-muted ms-2">(Manual Verification)</small>
                                                </label>
                                            <?php endif; ?>
                                            
                                            <?php if (empty($enabledMethods)): ?>
                                                <div class="list-group-item text-danger">
                                                    <i class="fa fa-exclamation-triangle me-2"></i>Aucune méthode de paiement disponible. Veuillez contacter l'administrateur.
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Transaction ID (for manual payment methods) -->
                                    <div class="mb-4" id="transactionIdField" style="display: none;">
                                        <label class="form-label">Transaction ID / Reference Number <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="transaction_id" 
                                               placeholder="Enter transaction ID or reference number"
                                               value="<?php echo htmlspecialchars($_SESSION['nclex_registration_data']['transaction_id'] ?? ($_SESSION['nclex_registration_data']['payment_transaction_id'] ?? '')); ?>">
                                        <small class="text-muted">Please provide the transaction ID or reference number from your payment.</small>
                                    </div>

                                    <div class="d-flex justify-content-between">
                                        <button type="button" class="btn btn-outline-secondary btn-lg px-5" onclick="goToStep4()">
                                            <i class="fa fa-arrow-left me-2"></i>Back
                                        </button>
                                        <button type="button" class="btn btn-primary btn-lg px-5" onclick="goToStep6()">
                                            Next: Step 6 <i class="fa fa-arrow-right ms-2"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Step 6: Documents and Agreement -->
                                <div class="form-step" id="step6">
                                    <h3 class="text-primary mb-4">Step 6: Send the documents for confirmation</h3>
                                    
                                    <div class="alert alert-warning mb-4">
                                        <h5 class="alert-heading"><i class="fa fa-exclamation-triangle me-2"></i>Important: Send Documents via Email</h5>
                                        <p class="mb-3"><strong>Please send the following required documents via email to the administrator:</strong></p>
                                        <ul class="mb-3">
                                            <li>A copy of your passport.</li>
                                            <li>A copy of your marriage certificate (if your spouse's name appears on the nurse's documents).</li>
                                            <li>A copy of the nursing license issued by MSPP.</li>
                                            <li>A copy of the nursing diploma issued by the Faculty of Nursing Sciences.</li>
                                            <li>Current address of the nurse. A safe address in the United States (if possible) where the board can send the license after passing the NCLEX.</li>
                                            <li>Your immigration status in the United States? (US Citizen, Permanent Resident, Asylum Seeker, TPS, Other).</li>
                                            <li>If you have a Social Security Number, send the document. If not, send any document containing an Alien number.</li>
                                        </ul>
                                        <div class="alert alert-info mb-0">
                                            <strong><i class="fa fa-info-circle me-2"></i>Instructions:</strong>
                                            <ul class="mb-0 mt-2">
                                                <li>All documents must be scanned and clearly readable.</li>
                                                <li>Any missing document or document that does not meet the described standard will cause a delay in the process.</li>
                                                <li>Applications are processed in the order they are received.</li>
                                                <li><strong>Send all documents by email to the administrator after submitting this form.</strong></li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="agreement" id="agreement" value="1" required>
                                            <label class="form-check-label" for="agreement">
                                                <strong>Permission & Agreement <span class="text-danger">*</span></strong><br>
                                                <small class="text-muted">
                                                    I Agree And Give My Permission. By submitting this form, you confirm that the information provided is accurate and complete to the best of your knowledge. You also agree to allow OrchideeLLC to contact you regarding your consultation and future updates related to our services. Your privacy is important to us, and your details will be handled with care and confidentiality.
                                                </small>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between">
                                        <button type="button" class="btn btn-outline-secondary btn-lg px-5" onclick="goToStep5()">
                                            <i class="fa fa-arrow-left me-2"></i>Back
                                        </button>
                                        <button type="submit" class="btn btn-primary btn-lg px-5" id="submitStep6" <?php echo empty($enabledMethods) ? 'disabled' : ''; ?>>
                                            <i class="fa fa-paper-plane me-2"></i>Submit Registration
                                        </button>
                                    </div>
                                </div>

                                <input type="hidden" name="step" value="1" id="formStep">
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Registration Form End -->


        <?php include 'includes/footer.php'; ?>
        <?php include 'includes/chat-button.php'; ?>
        <a href="#" class="btn btn-primary btn-lg-square rounded-circle back-to-top"><i class="fa fa-arrow-up"></i></a>   


        <!-- JavaScript Libraries -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="lib/wow/wow.min.js"></script>
        <script src="lib/easing/easing.min.js"></script>
        <script src="lib/waypoints/waypoints.min.js"></script>
        <script src="lib/counterup/counterup.min.js"></script>
        <script src="lib/lightbox/js/lightbox.min.js"></script>
        <script src="lib/owlcarousel/owl.carousel.min.js"></script>
        

        <!-- Template Javascript -->
        <script src="js/main.js"></script>
        
        <script>
            // Toggle Other Immigration Status field
            function toggleOtherImmigration() {
                const status = document.getElementById('immigration_status').value;
                const otherField = document.getElementById('otherImmigrationField');
                
                if (status === 'Other (Specify)') {
                    otherField.style.display = 'block';
                } else {
                    otherField.style.display = 'none';
                }
            }
            
            // Navigation functions
            function goToStep1() {
                showStep(1);
            }
            
            function goToStep2() {
                if (validateStep(1)) {
                    // Check email match
                    const email = document.querySelector('input[name="email"]').value;
                    const confirmEmail = document.querySelector('input[name="confirm_email"]').value;
                    if (email !== confirmEmail) {
                        alert('Email addresses do not match.');
                        return false;
                    }
                    
                    document.getElementById('formStep').value = '1';
                    document.getElementById('nclexRegistrationForm').submit();
                }
            }
            
            function goToStep3() {
                if (validateStep(2)) {
                    document.getElementById('formStep').value = '2';
                    document.getElementById('nclexRegistrationForm').submit();
                }
            }
            
            function goToStep4() {
                if (validateStep(3)) {
                    document.getElementById('formStep').value = '3';
                    document.getElementById('nclexRegistrationForm').submit();
                }
            }
            
            function goToStep5() {
                if (validateStep(4)) {
                    document.getElementById('formStep').value = '4';
                    document.getElementById('nclexRegistrationForm').submit();
                }
            }
            
            function goToStep6() {
                if (validateStep(5)) {
                    document.getElementById('formStep').value = '5';
                    document.getElementById('nclexRegistrationForm').submit();
                }
            }
            
            function toggleTransactionId() {
                const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
                const transactionField = document.getElementById('transactionIdField');
                
                if (paymentMethod && ['zelle', 'cashapp', 'bank_deposit'].includes(paymentMethod.value)) {
                    transactionField.style.display = 'block';
                    transactionField.querySelector('input').required = true;
                } else {
                    transactionField.style.display = 'none';
                    transactionField.querySelector('input').required = false;
                    transactionField.querySelector('input').value = '';
                }
            }
            
            function showStep(stepNumber) {
                // Hide all steps
                for (let i = 1; i <= 6; i++) {
                    document.getElementById('step' + i).classList.remove('active');
                    document.getElementById('step' + i + '-indicator').classList.remove('active');
                }
                
                // Show selected step
                document.getElementById('step' + stepNumber).classList.add('active');
                document.getElementById('step' + stepNumber + '-indicator').classList.add('active');
                
                // Mark previous steps as completed
                for (let i = 1; i < stepNumber; i++) {
                    document.getElementById('step' + i + '-indicator').classList.add('completed');
                }
            }
            
            function validateStep(stepNumber) {
                const form = document.getElementById('nclexRegistrationForm');
                const step = document.getElementById('step' + stepNumber);
                const inputs = step.querySelectorAll('input[required], select[required]');
                let isValid = true;
                
                inputs.forEach(input => {
                    if (input.type === 'radio') {
                        const radioGroup = form.querySelectorAll('input[name="' + input.name + '"]');
                        const isRadioChecked = Array.from(radioGroup).some(radio => radio.checked);
                        if (!isRadioChecked) {
                            isValid = false;
                        }
                    } else if (input.type === 'checkbox') {
                        if (!input.checked) {
                            isValid = false;
                            input.classList.add('is-invalid');
                        } else {
                            input.classList.remove('is-invalid');
                        }
                    } else if (!input.value.trim()) {
                        isValid = false;
                        input.classList.add('is-invalid');
                    } else {
                        input.classList.remove('is-invalid');
                    }
                });
                
                if (!isValid) {
                    alert('Please fill in all required fields.');
                }
                
                return isValid;
            }
            
            // Toggle functions
            function toggleElementaryAnotherSchool() {
                const hasAnother = document.querySelector('input[name="elementary_another_school"]:checked');
                const field = document.getElementById('elementaryAnotherSchoolField');
                if (hasAnother && hasAnother.value === 'Yes') {
                    field.style.display = 'block';
                    field.querySelector('input').required = true;
                } else {
                    field.style.display = 'none';
                    field.querySelector('input').required = false;
                    field.querySelector('input').value = '';
                }
            }
            
            function toggleHighSchoolAnotherSchool() {
                const hasAnother = document.querySelector('input[name="high_school_another_school"]:checked');
                const field = document.getElementById('highSchoolAnotherSchoolField');
                if (hasAnother && hasAnother.value === 'Yes') {
                    field.style.display = 'block';
                } else {
                    field.style.display = 'none';
                    field.querySelector('input').value = '';
                }
            }
            
            function toggleUniversityAnother() {
                const hasAnother = document.querySelector('input[name="university_another"]:checked');
                const field = document.getElementById('universityAnotherField');
                if (hasAnother && hasAnother.value === 'Yes') {
                    field.style.display = 'block';
                } else {
                    field.style.display = 'none';
                    field.querySelector('input').value = '';
                }
            }
            
            function toggleSpecialization() {
                const hasSpecialization = document.querySelector('input[name="university_specialization"]:checked');
                const field = document.getElementById('specializationField');
                if (hasSpecialization && hasSpecialization.value === 'Yes') {
                    field.style.display = 'block';
                } else {
                    field.style.display = 'none';
                    field.querySelector('input').value = '';
                }
            }
            
            // Handle step 6 submission
            document.getElementById('submitStep6').addEventListener('click', function(e) {
                e.preventDefault();
                
                if (!validateStep(6)) {
                    return false;
                }
                
                const agreement = document.getElementById('agreement');
                if (!agreement.checked) {
                    alert('You must agree to the terms and conditions to proceed.');
                    agreement.focus();
                    return false;
                }
                
                // Set step to 6 and submit
                document.getElementById('formStep').value = '6';
                document.getElementById('nclexRegistrationForm').submit();
            });
            
            // Show appropriate step based on session
            <?php 
            $currentStep = 1;
            
            // If there's an error, stay on the current step that was submitted
            if (isset($_POST['step']) && !empty($error)) {
                $currentStep = intval($_POST['step']);
            } elseif (isset($_POST['step']) && empty($error)) {
                // If step was submitted successfully, go to next step
                $submittedStep = intval($_POST['step']);
                $currentStep = $submittedStep + 1;
                // Don't go beyond step 6
                if ($currentStep > 6) {
                    $currentStep = 6;
                }
            } elseif (isset($_SESSION['nclex_registration_data']) && !empty($_SESSION['nclex_registration_data'])) {
                // Check which step was last completed in order
                // Step 1 is completed if we have first_name and email
                if (isset($_SESSION['nclex_registration_data']['first_name']) && !empty($_SESSION['nclex_registration_data']['first_name']) &&
                    isset($_SESSION['nclex_registration_data']['email']) && !empty($_SESSION['nclex_registration_data']['email'])) {
                    // Step 2 is completed if we have elementary_school_name
                    if (isset($_SESSION['nclex_registration_data']['elementary_school_name']) && !empty($_SESSION['nclex_registration_data']['elementary_school_name'])) {
                        // Step 3 is completed if we have high_school_name
                        if (isset($_SESSION['nclex_registration_data']['high_school_name']) && !empty($_SESSION['nclex_registration_data']['high_school_name'])) {
                            // Step 4 is completed if we have university_name
                            if (isset($_SESSION['nclex_registration_data']['university_name']) && !empty($_SESSION['nclex_registration_data']['university_name'])) {
                                // Step 5 is completed if we have payment_method
                                if (isset($_SESSION['nclex_registration_data']['payment_method']) && !empty($_SESSION['nclex_registration_data']['payment_method'])) {
                                    $currentStep = 6;
                                } else {
                                    $currentStep = 5;
                                }
                            } else {
                                $currentStep = 4;
                            }
                        } else {
                            $currentStep = 3;
                        }
                    } else {
                        $currentStep = 2;
                    }
                } else {
                    $currentStep = 1;
                }
            }
            ?>
            
            // Initialize on page load
            document.addEventListener('DOMContentLoaded', function() {
                toggleOtherImmigration();
                toggleElementaryAnotherSchool();
                toggleHighSchoolAnotherSchool();
                toggleUniversityAnother();
                toggleSpecialization();
                toggleTransactionId();
                
                // Show appropriate step
                <?php if ($currentStep > 1): ?>
                    showStep(<?php echo $currentStep; ?>);
                <?php endif; ?>
            });
        </script>
    </body>

</html>
