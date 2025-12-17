<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();

$orderNumber = $_GET['order'] ?? '';

setFlashMessage('Pagamento cancelado. Você pode tentar novamente.', 'info');
redirect('/checkout.php');
?>
