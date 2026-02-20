<?php
require_once 'config/database.php';

startSession();
session_destroy();
redirect('login.php');
?>











