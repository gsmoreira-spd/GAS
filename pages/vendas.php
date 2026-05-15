<?php
/**
 * VENDAS / PEDIDOS
 * Listagem de pedidos com filtros e ações de status
 */
$titulo_pagina = "Vendas / Pedidos";
$menu_ativo = "vendas";
require_once '../includes/header.php';

$msg = '';
$empresa_id = empresaAtiva();
$filial_id  = filialAtiva();
if (!$empresa_id) { echo "<div class='alert alert-warning'>Selecione uma empresa.</div>"; require_once '../includes/footer.php'; exit; }

// === ATUALIZAR STATUS ===
if (isset($_GET['status']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $novo_status = limpar($_GET['status']);
    $validos = ['pendente','separacao','entrega','entregue','finalizado','cancelado'];
    
    if (in_array($novo_status, $validos)) {
        // Busca pedido atual
        $stmt = $conn->prepare("SELECT * FROM pedidos WHERE id = ? AND empresa_id = ?");
        $stmt->bind_param("ii", $id, $empresa_id);
        $stmt->execute();
        $pedido = $stmt->get_result()->fetch_assoc();
        
        if ($pedido && $pedido['status'] != $novo_status) {
            $status_antigo = $pedido['status'];
            
            // Atualiza status
            $stmt = $conn->prepare("UPDATE pedidos SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $novo_status, $id);
            $stmt->execute();
            
            // CANCELAMENTO: devolve estoque se já tinha sido debitado
            if ($novo_status == 'cancelado' && in_array($status_antigo, ['separacao','entrega','entregue','finalizado'])) {
                $stmt = $conn->prepare("SELECT produto_id, quantidade FROM pedido_itens WHERE pedido_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
                $itens = $stmt->get_result();
                while ($it = $itens->fetch_assoc()) {
                    movimentarEstoqueVenda($conn, $it['produto_id'], $pedido['filial_id'], $it['quantidade'], 'cancelamento', $pedido['numero'], $id);
                }
                // Cancela financeiro
                $stmt = $conn->prepare("UPDATE financeiro SET status='cancelado' WHERE pedido_id = ?");
                $stmt->bind_param("i", $id);
                $stmt->execute();
            }
            
            // FINALIZAÇÃO: marca financeiro como pago
            if ($novo_status == 'finalizado' || $novo_status == 'entregue') {
                $stmt = $conn->prepare("UPDATE financeiro SET status='pago', data_pagamento=CURDATE() WHERE pedido_id = ? AND status='pendente'");
                $stmt->bind_param("i", $id);
                $stmt->execute();
            }
            
            registrarLog($conn, 'atualizar_status_pedido', "Pedido #$id: $status_antigo → $novo_status");
            $msg = '<div class="alert alert-success">✅ Status atualizado!</div>';
        }
    }
}

// === FILTROS ===
$filtro_status = $_GET['f_status'] ?? '';
$filtro_data   = $_GET['f_data'] ?? '';
$filtro_busca  = $_GET['f_busca'] ?? '';

$where = "p.empresa_id = ? AND " . filiaisSQL('p.filial_id');
$params = [$empresa_id];
$types  = "i";

if ($filtro_status) {
    $where .= " AND p.status = ?";
    $params[] = $filtro_status;
    $types .= "s";
}
if ($filtro_data) {
    $where .= " AND DATE(p.criado_em) = ?";
    $params[] = $filtro_data;
    $types .= "s";
}
if ($filtro_busca) {
    $where .= " AND (c.nome LIKE ? OR p.numero LIKE ?)";
    $params[] = "%$filtro_busca%";
    $params[] = "%$filtro_busca%";
    $types .= "ss";
}

$sql = "
    SELECT p.*, c.nome as cliente_nome, c.telefone as cliente_tel,
           m.nome as motoboy_nome, f.nome as filial_nome
    FROM pedidos p
    JOIN clientes c ON c.id = p.cliente_id
    LEFT JOIN motoboys m ON m.id = p.motoboy_id
    LEFT JOIN filiais f ON f.id = p.filial_id
    WHERE $where
    ORDER BY p.criado_em DESC
    LIMIT 200
";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$pedidos = $stmt->get_result();
?>

<?= $msg ?>

<div class="toolbar">
    <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; flex:1;">
        <input type="text" name="f_busca" placeholder="Cliente ou nº pedido..." value="<?= htmlspecialchars($filtro_busca) ?>" class="form-control" style="max-width:200px;">
        <select name="f_status" class="form-control" style="max-width:180px;">
            <option value="">Todos os status</option>
            <option value="pendente" <?= $filtro_status=='pendente'?'selected':'' ?>>Pendente</option>
            <option value="separacao" <?= $filtro_status=='separacao'?'selected':'' ?>>Em Separação</option>
            <option value="entrega" <?= $filtro_status=='entrega'?'selected':'' ?>>Em Entrega</option>
            <option value="entregue" <?= $filtro_status=='entregue'?'selected':'' ?>>Entregue</option>
            <option value="finalizado" <?= $filtro_status=='finalizado'?'selected':'' ?>>Finalizado</option>
            <option value="cancelado" <?= $filtro_status=='cancelado'?'selected':'' ?>>Cancelado</option>
        </select>
        <input type="date" name="f_data" value="<?= $filtro_data ?>" class="form-control" style="max-width:160px;">
        <button type="submit" class="btn btn-secondary"><i class="fas fa-filter"></i> Filtrar</button>
        <a href="vendas.php" class="btn btn-secondary"><i class="fas fa-times"></i> Limpar</a>
    </form>
    <a href="nova_venda.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> Novo Pedido
    </a>
</div>

<div class="table-container">
<table class="table">
    <thead>
        <tr>
            <th>Nº</th>
            <th>Cliente</th>
            <th>Total</th>
            <th>Pgto</th>
            <th>Filial</th>
            <th>Motoboy</th>
            <th>Status</th>
            <th>Data</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($pedidos->num_rows == 0): ?>
            <tr><td colspan="9" style="text-align:center; padding:30px; color:#64748b;">
                <i class="fas fa-inbox" style="font-size:30px;"></i><br>
                Nenhum pedido encontrado.
            </td></tr>
        <?php endif; ?>
        <?php while ($p = $pedidos->fetch_assoc()): ?>
        <tr>
            <td><strong>#<?= $p['numero'] ?? $p['id'] ?></strong></td>
            <td>
                <strong><?= escapar($p['cliente_nome']) ?></strong><br>
                <small style="color:#64748b;"><?= escapar($p['cliente_tel']) ?></small>
            </td>
            <td><strong><?= formatarMoeda($p['total']) ?></strong></td>
            <td><?= ucfirst($p['forma_pagamento']) ?></td>
            <td><span class="badge badge-info"><?= escapar($p['filial_nome'] ?? '—') ?></span></td>
            <td><?= escapar($p['motoboy_nome'] ?? '') ?: '<span style="color:#94a3b8;">-</span>' ?></td>
            <td><?= statusBadge($p['status']) ?></td>
            <td><?= formatarData($p['criado_em'], true) ?></td>
            <td class="actions">
                <a href="pedido.php?id=<?= $p['id'] ?>" class="btn-icon view" title="Detalhes"><i class="fas fa-eye"></i></a>
                <?php if ($p['status'] == 'pendente'): ?>
                    <a href="?status=separacao&id=<?= $p['id'] ?>" class="btn-icon edit" title="Aprovar p/ Separação"><i class="fas fa-check"></i></a>
                <?php elseif ($p['status'] == 'separacao'): ?>
                    <a href="?status=entrega&id=<?= $p['id'] ?>" class="btn-icon edit" title="Saiu p/ Entrega"><i class="fas fa-truck"></i></a>
                <?php elseif ($p['status'] == 'entrega'): ?>
                    <a href="?status=entregue&id=<?= $p['id'] ?>" class="btn-icon view" title="Entregue"><i class="fas fa-check-double"></i></a>
                <?php endif; ?>
                <?php if (!in_array($p['status'], ['cancelado','finalizado'])): ?>
                <button onclick="if(confirm('Cancelar pedido #<?= $p['numero'] ?? $p['id'] ?>?')) location.href='?status=cancelado&id=<?= $p['id'] ?>'" class="btn-icon delete" title="Cancelar"><i class="fas fa-ban"></i></button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
</div>

<?php require_once '../includes/footer.php'; ?>
