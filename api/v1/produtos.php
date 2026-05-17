<?php
/**
 * GET /api/v1/produtos.php                         — lista todos os produtos ativos
 * GET /api/v1/produtos.php?filial_id=ID            — filtra por filial
 * GET /api/v1/produtos.php?empresa_id=ID           — filtra por empresa
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    apiError(405, 'Método não permitido.');
}

$empresa_id = (int)($_GET['empresa_id'] ?? 0);
$filial_id  = (int)($_GET['filial_id']  ?? 0);

$params = [];
$types  = '';
$where  = ["p.status = 'ativo'"];

if ($empresa_id > 0) {
    $where[]  = 'p.empresa_id = ?';
    $types   .= 'i';
    $params[] = $empresa_id;
}
if ($filial_id > 0) {
    $where[]  = 'p.filial_id = ?';
    $types   .= 'i';
    $params[] = $filial_id;
}

$where_sql = implode(' AND ', $where);

$stmt = $conn->prepare("
    SELECT p.id, p.empresa_id, p.filial_id, p.codigo, p.nome, p.tipo,
           p.preco_venda, p.unidade, p.estoque_minimo, p.descricao,
           f.nome AS filial_nome,
           COALESCE(e.quantidade, 0) AS estoque_atual
    FROM produtos p
    LEFT JOIN filiais f ON f.id = p.filial_id
    LEFT JOIN estoque e ON e.produto_id = p.id AND e.filial_id = p.filial_id
    WHERE {$where_sql}
    ORDER BY f.nome, p.nome
");

if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();
$stmt->close();

$produtos = [];
while ($row = $res->fetch_assoc()) {
    $produtos[] = [
        'id'             => (int)$row['id'],
        'empresa_id'     => (int)$row['empresa_id'],
        'filial_id'      => (int)$row['filial_id'],
        'filial_nome'    => $row['filial_nome'],
        'codigo'         => $row['codigo'],
        'nome'           => $row['nome'],
        'tipo'           => $row['tipo'],
        'preco_venda'    => (float)$row['preco_venda'],
        'unidade'        => $row['unidade'],
        'estoque_atual'  => (int)$row['estoque_atual'],
        'estoque_minimo' => (int)$row['estoque_minimo'],
        'disponivel'     => (int)$row['estoque_atual'] > 0,
        'descricao'      => $row['descricao'],
    ];
}

apiSuccess($produtos);
