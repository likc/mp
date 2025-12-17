<?php
// Configurações do Banco de Dados
define('DB_HOST', 'localhost');
define('DB_USER', 'minec761_mantospremium');
define('DB_PASS', 'bvncm203o490');
define('DB_NAME', 'minec761_mantospremium');

// Configurações de Sessão
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Mude para 1 se usar HTTPS
session_start();

// Conexão com o Banco de Dados
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Carregar configurações do banco de dados
require_once __DIR__ . '/../includes/settings.php';
defineFromSettings();
?>