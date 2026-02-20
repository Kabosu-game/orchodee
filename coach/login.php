<?php
/**
 * Redirection vers la page de connexion principale.
 * Les coachs se connectent sur la même page que les utilisateurs (login.php).
 * Après connexion, ils sont redirigés automatiquement vers coach/dashboard.php.
 */
header('Location: ../login.php');
exit;
