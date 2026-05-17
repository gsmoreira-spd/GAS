<?php
/**
 * Autenticação Bearer Token para endpoints /api/v1
 *
 * Inclua este arquivo no início de cada endpoint.
 * Requer header: Authorization: Bearer <API_TOKEN>
 */

// Apenas JSON
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Impedir cache de respostas da API
header('Cache-Control: no-store, no-cache, must-revalidate');

function apiError(int $code, string $message): void
{
    http_response_code($code);
    echo json_encode(['erro' => $message]);
    exit;
}

function apiSuccess(mixed $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Validar token
$token_esperado = defined('API_TOKEN') ? API_TOKEN : (getenv('API_TOKEN') ?: '');
if (empty($token_esperado)) {
    apiError(500, 'API_TOKEN não configurado no servidor.');
}

$header_auth = $_SERVER['HTTP_AUTHORIZATION']
    ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
    ?? '';

if (!preg_match('/^Bearer\s+(.+)$/i', $header_auth, $m)) {
    apiError(401, 'Token não informado. Use: Authorization: Bearer <token>');
}

if (!hash_equals($token_esperado, $m[1])) {
    apiError(401, 'Token inválido.');
}
