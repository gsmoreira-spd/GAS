<?php
/**
 * GET /api/v1/status_pedido.php?id=ID            — status do pedido por ID
 * GET /api/v1/status_pedido.php?numero=PEDxxxxxxx — status do pedido por número
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError(405, 'Método não permitido.');
}

$id     = (int)($_GET['id']     ?? 0);
$numero = trim($_GET['numero']  ?? '');

if ($id <= 0 && $numero === '') {
    apiError(400, 'Informe "id" ou "numero" do pedido.');
}

if ($id > 0) {
    $stmt = $conn->prepare("
        SELECT p.id, p.numero, p.status, p.origem, p.total,
               p.forma_pagamento, p.observacoes, p.criado_em, p.atualizado_em,
               c.nome AS cliente_nome, c.telefone AS cliente_telefone,
               m.nome AS motoboy_nome, m.telefone AS motoboy_telefone,
               f.nome AS filial_nome
        FROM pedidos p
        INNER JOIN clientes c ON c.id = p.cliente_id
        INNER JOIN filiais  f ON f.id = p.filial_id
        LEFT  JOIN motoboys m ON m.id = p.motoboy_id
        WHERE p.id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $id);
} else {
    $stmt = $conn->prepare("
        SELECT p.id, p.numero, p.status, p.origem, p.total,
               p.forma_pagamento, p.observacoes, p.criado_em, p.atualizado_em,
               c.nome AS cliente_nome, c.telefone AS cliente_telefone,
               m.nome AS motoboy_nome, m.telefone AS motoboy_telefone,
               f.nome AS filial_nome
        FROM pedidos p
        INNER JOIN clientes c ON c.id = p.cliente_id
        INNER JOIN filiais  f ON f.id = p.filial_id
        LEFT  JOIN motoboys m ON m.id = p.motoboy_id
        WHERE p.numero = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $numero);
}

$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pedido) {
    apiError(404, 'Pedido não encontrado.');
}

// Buscar itens
$stmt = $conn->prepare("
    SELECT pi.quantidade, pi.preco_unitario, pi.subtotal,
           pr.nome AS produto_nome, pr.codigo, pr.tipo
    FROM pedido_itens pi
    INNER JOIN produtos pr ON pr.id = pi.produto_id
    WHERE pi.pedido_id = ?
");
$stmt->bind_param('i', $pedido['id']);
$stmt->execute();
$res = $stmt->get_result();
$stmt->close();

$itens = [];
while ($it = $res->fetch_assoc()) {
    $itens[] = $it;
}

$pedido['itens'] = $itens;

apiSuccess($pedido);
