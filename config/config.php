<?php
/**
 * CONFIGURAÇÃO DO SISTEMA
 *
 * Lê variáveis de ambiente primeiro; cai nos padrões de desenvolvimento local
 * se elas não existirem (compatibilidade com XAMPP).
 */

// === CONFIGURAÇÕES GERAIS ===
define('SISTEMA_NOME', 'ERP Gás & Água');
define('SISTEMA_VERSAO', '1.0.0');

// === AMBIENTE ===
$app_env = getenv('APP_ENV') ?: 'development';
define('APP_ENV', $app_env);
define('IS_PRODUCTION', $app_env === 'production');

// === BANCO DE DADOS ===
define('DB_HOST',     getenv('DB_HOST')     ?: 'localhost');
define('DB_PORT',     getenv('DB_PORT')     ?: '3306');
define('DB_USER',     getenv('DB_USERNAME') ?: 'root');
define('DB_PASS',     getenv('DB_PASSWORD') ?: '');
define('DB_NAME',     getenv('DB_DATABASE') ?: 'gasagua_erp');

// === API TOKEN (para endpoints /api/v1) ===
define('API_TOKEN',   getenv('API_TOKEN')   ?: 'dev-insecure-token-change-me');

// === CAMINHOS DO SISTEMA ===
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$app_url  = getenv('APP_URL') ?: ($protocol . $host . '/gasagua_erp/');
define('BASE_URL',  rtrim($app_url, '/') . '/');
define('BASE_PATH', dirname(__DIR__) . '/');

// === SESSÃO ===
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// === FUSO HORÁRIO ===
date_default_timezone_set('America/Sao_Paulo');

// === EXIBIÇÃO DE ERROS ===
if (IS_PRODUCTION) {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

// === CONEXÃO COM O BANCO ===
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int) DB_PORT);
    if ($conn->connect_error) {
        throw new Exception($conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
} catch (Exception $e) {
    if (IS_PRODUCTION) {
        http_response_code(503);
        die('Serviço temporariamente indisponível.');
    }
    die('❌ Erro de conexão: ' . $e->getMessage() .
        '<br>Execute o instalador: <a href="' . BASE_URL . 'instalar.php">instalar.php</a>');
}
