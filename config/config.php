<?php
/**
 * CONFIGURAÇÃO DO BANCO DE DADOS
 * 
 * Configurações de conexão MySQL para o XAMPP.
 * Por padrão, o XAMPP usa: host=localhost, user=root, sem senha.
 */

// === CONFIGURAÇÕES GERAIS ===
define('SISTEMA_NOME', 'ERP Gás & Água');
define('SISTEMA_VERSAO', '1.0.0');

// === CONFIGURAÇÕES DO BANCO ===
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'gasagua_erp');

// === CAMINHOS DO SISTEMA ===
// Detecta automaticamente a URL base
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Pasta do sistema (ajuste se mudar o nome da pasta)
define('BASE_URL', $protocol . $host . '/gasagua_erp/');
define('BASE_PATH', dirname(__DIR__) . '/');

// === CONFIGURAÇÕES DE SESSÃO ===
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// === FUSO HORÁRIO ===
date_default_timezone_set('America/Sao_Paulo');

// === EXIBIR ERROS (apenas em desenvolvimento) ===
error_reporting(E_ALL);
ini_set('display_errors', 1);

// === CONEXÃO COM O BANCO DE DADOS ===
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Erro de conexão: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("❌ Erro: " . $e->getMessage() . "<br>Execute o instalador: <a href='" . BASE_URL . "instalar.php'>instalar.php</a>");
}
