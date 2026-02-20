<?php
/**
 * Script UNIQUE : enregistre les clés PayPal fournies dans la base de données.
 * À exécuter UNE FOIS dans le navigateur (ex: http://localhost/orchidee/setup-paypal-keys-once.php)
 * puis SUPPRIMER CE FICHIER pour des raisons de sécurité.
 */
require_once 'config/database.php';

$conn = getDBConnection();

// Clés PayPal fournies par l'utilisateur
$clientId     = 'Af9rdstDIjShKWRUR3DXgE-9C59Bjh5Vab21bw8DGt-pCac8ZLkA1I7vVvYsigV5yrEnCbFS9gBrgvFG';
$clientSecret = 'ELX-hhJ1fnUkA75zuerk4UwruVqv7zGI8LgJaX4lIp5LbKjsGnR9xsI9wg51Mpa_qC4IjC0ks-f8Vdbw';
$mode         = 'sandbox'; // mettre 'live' en production si ce sont des clés live

$configData = json_encode([
    'client_id'     => $clientId,
    'client_secret' => $clientSecret,
    'mode'          => $mode,
]);

// S'assurer qu'une ligne paypal existe
$conn->query("INSERT IGNORE INTO payment_config (payment_method, is_enabled, config_data) VALUES ('paypal', 0, '{\"client_id\":\"\",\"client_secret\":\"\",\"mode\":\"sandbox\"}')");

$stmt = $conn->prepare("UPDATE payment_config SET is_enabled = 1, config_data = ? WHERE payment_method = 'paypal'");
$stmt->bind_param("s", $configData);
$stmt->execute();
$stmt->close();
$conn->close();

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>PayPal configuré</title></head><body>';
echo '<h1>PayPal configuré</h1>';
echo '<p>Les clés PayPal ont été enregistrées en base de données. PayPal est activé.</p>';
echo '<p><strong>Important :</strong> Supprimez ce fichier <code>setup-paypal-keys-once.php</code> pour des raisons de sécurité.</p>';
echo '<p><a href="admin/payment-settings.php">Aller aux paramètres de paiement</a> | <a href="registration-next-session.php">Formulaire d\'inscription</a></p>';
echo '</body></html>';
