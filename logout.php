<?php
require_once 'config/config.php';

// Destrói a sessão
session_destroy();

// Redireciona para a home
header('Location: /index.php');
exit;
?>
