<?php
/**
 * POST /api/v1/webhook/n8n.php
 *
 * Recebe eventos enviados pelo n8n para o ERP.
 * Exemplos de uso: atualizar status de pedido via automação,
 * registrar confirmação de pagamento, etc.
 *
 * Payload esperado:
 * {
 *   "evento": "pedido_confirmado" | "pagamento_recebido" | "custom",
 *   "pedido_id": 123,
 *   "dados": { ... }
 * }
 */
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiError(405, 'Método não permitido. Use POST.');
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    apiError(400, 'Body JSON inválido.');
}

$evento    = trim($body['evento']    ?? '');
$pedido_id = (int)($body['pedido_id'] ?? 0);
$dados     = $body['dados']           ?? [];

if ($evento === '') {
    apiError(422, 'Campo "evento" obrigatório.');
}

// Registrar log do evento recebido
$usuario_id = null;
$ip         = $_SERVER['REMOTE_ADDR'] ?? '';
$descricao  = json_encode($body, JSON_UNESCAPED_UNICODE);

$stmt = $conn->prepare("INSERT INTO logs (usuario_id, acao, descricao, ip) VALUES (?, ?, ?, ?)");
$acao = 'webhook_n8n:' . $evento;
$stmt->bind_param('isss', $usuario_id, $acao, $descricao, $ip);
$stmt->execute();
$stmt->close();

// Processar eventos conhecidos
switch ($evento) {
    case 'pedido_status':
        if ($pedido_id <= 0) {
            apiError(422, '"pedido_id" obrigatório para evento pedido_status.');
        }
        $novo_status = trim($dados['status'] ?? '');
        $status_validos = ['pendente','separacao','entrega','entregue','finalizado','cancelado'];
        if (!in_array($novo_status, $status_validos, true)) {
            apiError(422, 'Status inválido. Valores: ' . implode(', ', $status_validos));
        }
        $stmt = $conn->prepare("UPDATE pedidos SET status = ?, atualizado_em = NOW() WHERE id = ?");
        $stmt->bind_param('si', $novo_status, $pedido_id);
        $stmt->execute();
        $afetados = $stmt->affected_rows;
        $stmt->close();
        if ($afetados === 0) {
            apiError(404, 'Pedido não encontrado.');
        }
        apiSuccess(['mensagem' => "Status do pedido {$pedido_id} atualizado para '{$novo_status}'."]);
        break;

    default:
        // Evento customizado — apenas registrado no log acima
        apiSuccess(['mensagem' => "Evento '{$evento}' recebido e registrado.", 'pedido_id' => $pedido_id]);
}
