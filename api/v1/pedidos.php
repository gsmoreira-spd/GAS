<?php
/**
 * GET  /api/v1/pedidos.php?telefone=NUMERO — lista últimos pedidos do cliente
 * POST /api/v1/pedidos.php                  — cria pedido vindo do n8n/WhatsApp
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/funcoes.php';
require_once __DIR__ . '/_auth.php';

$metodo = $_SERVER['REQUEST_METHOD'];

// ------------------------------------------------------------------
// GET — listar pedidos por telefone do cliente
// ------------------------------------------------------------------
if ($metodo === 'GET') {
    $telefone = preg_replace('/\D/', '', $_GET['telefone'] ?? '');
    if (strlen($telefone) < 8) {
        apiError(400, 'Parâmetro "telefone" inválido ou ausente.');
    }

    $like = '%' . $telefone . '%';
    $stmt = $conn->prepare("
        SELECT p.id, p.numero, p.status, p.origem, p.total, p.forma_pagamento,
               p.taxa_entrega, p.desconto, p.observacoes, p.criado_em,
               c.nome AS cliente_nome, c.telefone AS cliente_telefone,
               f.nome AS filial_nome
        FROM pedidos p
        INNER JOIN clientes c ON c.id = p.cliente_id
        INNER JOIN filiais  f ON f.id = p.filial_id
        WHERE (REGEXP_REPLACE(c.telefone, '[^0-9]', '') LIKE ?
            OR REGEXP_REPLACE(c.whatsapp, '[^0-9]', '') LIKE ?)
        ORDER BY p.criado_em DESC
        LIMIT 10
    ");
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    $pedidos = [];
    while ($row = $res->fetch_assoc()) {
        $pedidos[] = $row;
    }

    apiSuccess($pedidos);
}

// ------------------------------------------------------------------
// POST — criar pedido
// ------------------------------------------------------------------
if ($metodo === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        apiError(400, 'Body JSON inválido.');
    }

    // Validar campos obrigatórios
    $empresa_id      = (int)($body['empresa_id']      ?? 0);
    $filial_id       = (int)($body['filial_id']       ?? 0);
    $cliente_id      = (int)($body['cliente_id']      ?? 0);
    $itens           = $body['itens']                 ?? [];
    $forma_pagamento = trim($body['forma_pagamento']  ?? 'dinheiro');

    if ($empresa_id <= 0 || $filial_id <= 0 || $cliente_id <= 0 || empty($itens)) {
        apiError(422, 'Campos obrigatórios: empresa_id, filial_id, cliente_id, itens.');
    }

    $formas_validas = ['pix', 'dinheiro', 'cartao', 'fiado'];
    if (!in_array($forma_pagamento, $formas_validas, true)) {
        apiError(422, 'forma_pagamento inválido. Valores: ' . implode(', ', $formas_validas));
    }

    $taxa_entrega = (float)($body['taxa_entrega'] ?? 0);
    $desconto     = (float)($body['desconto']     ?? 0);
    $observacoes  = trim($body['observacoes']     ?? '');
    $origem       = trim($body['origem']          ?? 'whatsapp');

    $origens_validas = ['manual', 'whatsapp', 'site', 'api'];
    if (!in_array($origem, $origens_validas, true)) {
        $origem = 'api';
    }

    // Verificar empresa e filial
    $stmt = $conn->prepare("SELECT id FROM filiais WHERE id = ? AND empresa_id = ? AND status = 'ativo'");
    $stmt->bind_param('ii', $filial_id, $empresa_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        $stmt->close();
        apiError(404, 'Filial não encontrada ou inativa nessa empresa.');
    }
    $stmt->close();

    // Verificar cliente
    $stmt = $conn->prepare("SELECT id FROM clientes WHERE id = ? AND empresa_id = ? AND status = 'ativo'");
    $stmt->bind_param('ii', $cliente_id, $empresa_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        $stmt->close();
        apiError(404, 'Cliente não encontrado ou inativo.');
    }
    $stmt->close();

    // Validar e calcular itens
    $itens_validos = [];
    $subtotal = 0.0;
    foreach ($itens as $idx => $item) {
        $produto_id  = (int)($item['produto_id']  ?? 0);
        $quantidade  = (int)($item['quantidade']  ?? 0);
        if ($produto_id <= 0 || $quantidade <= 0) {
            apiError(422, "Item {$idx}: produto_id e quantidade obrigatórios e > 0.");
        }

        $stmt = $conn->prepare("
            SELECT p.id, p.nome, p.preco_venda,
                   COALESCE(e.quantidade, 0) AS estoque
            FROM produtos p
            LEFT JOIN estoque e ON e.produto_id = p.id AND e.filial_id = ?
            WHERE p.id = ? AND p.empresa_id = ? AND p.status = 'ativo'
        ");
        $stmt->bind_param('iii', $filial_id, $produto_id, $empresa_id);
        $stmt->execute();
        $prod = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$prod) {
            apiError(404, "Produto {$produto_id} não encontrado ou inativo.");
        }
        if ((int)$prod['estoque'] < $quantidade) {
            apiError(422, "Estoque insuficiente para '{$prod['nome']}'. Disponível: {$prod['estoque']}.");
        }

        $preco_unit = isset($item['preco_unitario']) ? (float)$item['preco_unitario'] : (float)$prod['preco_venda'];
        $sub_item   = $preco_unit * $quantidade;
        $subtotal  += $sub_item;

        $itens_validos[] = [
            'produto_id'     => $produto_id,
            'quantidade'     => $quantidade,
            'preco_unitario' => $preco_unit,
            'subtotal'       => $sub_item,
        ];
    }

    $total = $subtotal + $taxa_entrega - $desconto;

    // Gerar número do pedido
    $numero_pedido = gerarNumeroPedido();

    $conn->begin_transaction();
    try {
        // Criar pedido
        $stmt = $conn->prepare("
            INSERT INTO pedidos
                (empresa_id, filial_id, cliente_id, numero, subtotal, taxa_entrega,
                 desconto, total, forma_pagamento, observacoes, origem)
            VALUES (?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->bind_param(
            'iiisddddsss',
            $empresa_id, $filial_id, $cliente_id, $numero_pedido,
            $subtotal, $taxa_entrega, $desconto, $total,
            $forma_pagamento, $observacoes, $origem
        );

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $pedido_id = $conn->insert_id;
        $stmt->close();

        // Inserir itens e movimentar estoque
        foreach ($itens_validos as $it) {
            $stmt = $conn->prepare("
                INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario, subtotal)
                VALUES (?,?,?,?,?)
            ");
            $stmt->bind_param('iiidd', $pedido_id, $it['produto_id'], $it['quantidade'], $it['preco_unitario'], $it['subtotal']);
            if (!$stmt->execute()) {
                throw new Exception($stmt->error);
            }
            $stmt->close();

            movimentarEstoqueVenda($conn, $it['produto_id'], $filial_id, $it['quantidade'], 'venda', $numero_pedido, $pedido_id);
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        apiError(500, IS_PRODUCTION ? 'Erro ao criar pedido.' : $e->getMessage());
    }

    apiSuccess([
        'id'     => $pedido_id,
        'numero' => $numero_pedido,
        'total'  => $total,
        'status' => 'pendente',
        'mensagem' => 'Pedido criado com sucesso.',
    ], 201);
}

apiError(405, 'Método não permitido.');
