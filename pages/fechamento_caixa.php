<?php
/**
 * FECHAMENTO DE CAIXA
 * Somente gerente e admin.
 * Exibe pedidos entregues com pagamento pendente de confirmação.
 * O estoque já foi baixado na criação do pedido; aqui confirma-se
 * o recebimento do dinheiro, fechando o ciclo financeiro do dia.
 */
require_once '../config/config.php';
require_once '../includes/funcoes.php';
verificarLogin();
verificarPerfil(['admin', 'gerente']);

$msg        = '';
$empresa_id = empresaAtiva();

if (!$empresa_id) {
    header('Location: selecionar_empresa.php');
    exit;
}

// Filiais ativas na sessão (respeita o seletor do topo)
$filiais_ids  = filiaisAtivas($empresa_id);
$filiais_sql  = filiaisSQL($filiais_ids);  // retorna "filial_id IN (1,2)" ou "1=1"

// Filtros de data
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-d');
$data_fim    = $_GET['data_fim']    ?? date('Y-m-d');

// ── CONFIRMAR RECEBIMENTO ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_pedido'])) {
    $pedido_id      = (int)($_POST['pedido_id'] ?? 0);
    $valor_recebido = (float)str_replace(',', '.', $_POST['valor_recebido'] ?? 0);
    $forma_pgto     = limpar($_POST['forma_pagamento_conf'] ?? '');
    $obs_conf       = limpar($_POST['obs_confirmacao'] ?? '');

    $formas_validas = ['pix', 'dinheiro', 'cartao', 'fiado'];
    if (!in_array($forma_pgto, $formas_validas)) $forma_pgto = 'dinheiro';

    if ($pedido_id > 0 && $valor_recebido > 0) {
        // Verifica se o pedido pertence à empresa/filial ativa
        $stmt = $conn->prepare("
            SELECT p.id, p.numero, p.total, p.status, p.filial_id
            FROM pedidos p
            WHERE p.id = ? AND p.empresa_id = ? AND {$filiais_sql}
        ");
        $stmt->bind_param('ii', $pedido_id, $empresa_id);
        $stmt->execute();
        $ped = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($ped) {
            $diferenca = round($valor_recebido - (float)$ped['total'], 2);
            $obs_final = '';
            if ($obs_conf) $obs_final .= $obs_conf;
            if ($diferenca != 0) {
                $sinal = $diferenca > 0 ? '+' : '';
                $obs_final .= ($obs_final ? ' | ' : '') . "Diferença: R$ {$sinal}" . number_format($diferenca, 2, ',', '.');
            }

            // Fechar financeiro
            $stmt = $conn->prepare("
                UPDATE financeiro
                SET status = 'pago',
                    data_pagamento = CURDATE(),
                    forma_pagamento = ?,
                    valor = ?,
                    observacoes = CONCAT(COALESCE(observacoes,''), ?)
                WHERE pedido_id = ? AND status = 'pendente'
            ");
            $obs_sep = $obs_final ? ' [Caixa: ' . $obs_final . ']' : '';
            $stmt->bind_param('sdsi', $forma_pgto, $valor_recebido, $obs_sep, $pedido_id);
            $stmt->execute();
            $stmt->close();

            // Finalizar pedido (se ainda não estiver)
            if (!in_array($ped['status'], ['finalizado', 'cancelado'])) {
                $stmt = $conn->prepare("UPDATE pedidos SET status = 'finalizado' WHERE id = ?");
                $stmt->bind_param('i', $pedido_id);
                $stmt->execute();
                $stmt->close();
            }

            registrarLog($conn, 'fechamento_caixa',
                "Pedido #{$ped['numero']} confirmado — R$ " . number_format($valor_recebido, 2, ',', '.') .
                " ({$forma_pgto})" . ($obs_final ? " — {$obs_final}" : '')
            );

            $msg = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Pedido <strong>' . escapar($ped['numero']) . '</strong> confirmado — R$ ' . number_format($valor_recebido, 2, ',', '.') . '</div>';
        } else {
            $msg = '<div class="alert alert-danger">Pedido não encontrado ou sem permissão.</div>';
        }
    } else {
        $msg = '<div class="alert alert-danger">Informe o valor recebido.</div>';
    }
}

// ── PEDIDOS PENDENTES DE CONFIRMAÇÃO ──────────────────────────────────────
$stmt = $conn->prepare("
    SELECT p.id, p.numero, p.status, p.total, p.subtotal, p.taxa_entrega, p.desconto,
           p.forma_pagamento, p.observacoes, p.criado_em, p.origem,
           c.nome AS cliente_nome, c.whatsapp, c.telefone,
           f.nome AS filial_nome,
           fin.id AS fin_id, fin.status AS fin_status, fin.valor AS fin_valor
    FROM pedidos p
    INNER JOIN clientes  c   ON c.id   = p.cliente_id
    INNER JOIN filiais   f   ON f.id   = p.filial_id
    LEFT  JOIN financeiro fin ON fin.pedido_id = p.id AND fin.tipo = 'receita'
    WHERE p.empresa_id = ?
      AND p.{$filiais_sql}
      AND p.status IN ('entregue','finalizado')
      AND (fin.status = 'pendente' OR fin.id IS NULL)
      AND DATE(p.criado_em) BETWEEN ? AND ?
    ORDER BY p.criado_em DESC
");
$stmt->bind_param('iss', $empresa_id, $data_inicio, $data_fim);
$stmt->execute();
$pendentes = $stmt->get_result();
$stmt->close();

// ── PEDIDOS JÁ CONFIRMADOS NO PERÍODO ─────────────────────────────────────
$stmt = $conn->prepare("
    SELECT p.id, p.numero, p.total, p.forma_pagamento, p.criado_em,
           c.nome AS cliente_nome,
           f.nome AS filial_nome,
           fin.valor AS fin_valor, fin.data_pagamento, fin.forma_pagamento AS fin_forma
    FROM pedidos p
    INNER JOIN clientes   c   ON c.id  = p.cliente_id
    INNER JOIN filiais    f   ON f.id  = p.filial_id
    INNER JOIN financeiro fin ON fin.pedido_id = p.id AND fin.tipo = 'receita' AND fin.status = 'pago'
    WHERE p.empresa_id = ?
      AND p.{$filiais_sql}
      AND fin.data_pagamento BETWEEN ? AND ?
    ORDER BY fin.data_pagamento DESC, p.criado_em DESC
");
$stmt->bind_param('iss', $empresa_id, $data_inicio, $data_fim);
$stmt->execute();
$confirmados = $stmt->get_result();
$stmt->close();

// ── TOTAIS ─────────────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT
        SUM(CASE WHEN fin.status = 'pendente' THEN p.total ELSE 0 END)                         AS total_pendente,
        SUM(CASE WHEN fin.status = 'pago'     THEN fin.valor ELSE 0 END)                        AS total_confirmado,
        SUM(CASE WHEN fin.status = 'pago' AND fin.forma_pagamento = 'dinheiro' THEN fin.valor ELSE 0 END) AS total_dinheiro,
        SUM(CASE WHEN fin.status = 'pago' AND fin.forma_pagamento = 'pix'      THEN fin.valor ELSE 0 END) AS total_pix,
        SUM(CASE WHEN fin.status = 'pago' AND fin.forma_pagamento = 'cartao'   THEN fin.valor ELSE 0 END) AS total_cartao,
        SUM(CASE WHEN fin.status = 'pago' AND fin.forma_pagamento = 'fiado'    THEN fin.valor ELSE 0 END) AS total_fiado,
        COUNT(CASE WHEN fin.status = 'pendente' THEN 1 END)                                     AS qtd_pendente,
        COUNT(CASE WHEN fin.status = 'pago'     THEN 1 END)                                     AS qtd_confirmado
    FROM pedidos p
    INNER JOIN financeiro fin ON fin.pedido_id = p.id AND fin.tipo = 'receita'
    WHERE p.empresa_id = ?
      AND p.{$filiais_sql}
      AND (
          (fin.status = 'pendente' AND DATE(p.criado_em) BETWEEN ? AND ?)
       OR (fin.status = 'pago'     AND fin.data_pagamento BETWEEN ? AND ?)
      )
");
$stmt->bind_param('issss', $empresa_id, $data_inicio, $data_fim, $data_inicio, $data_fim);
$stmt->execute();
$totais = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── HTML ────────────────────────────────────────────────────────────────────
$titulo_pagina = "Fechamento de Caixa";
$menu_ativo    = "fechamento_caixa";
require_once '../includes/header.php';
?>

<?= $msg ?>

<!-- Filtro de data -->
<form method="GET" class="toolbar" style="flex-wrap:wrap; gap:10px; margin-bottom:20px;">
    <div style="display:flex; align-items:center; gap:8px; flex:1;">
        <label style="white-space:nowrap; font-weight:600;"><i class="fas fa-calendar"></i> Período:</label>
        <input type="date" name="data_inicio" value="<?= $data_inicio ?>" class="form-control" style="max-width:160px;">
        <span>até</span>
        <input type="date" name="data_fim" value="<?= $data_fim ?>" class="form-control" style="max-width:160px;">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filtrar</button>
        <?php if ($data_inicio !== date('Y-m-d') || $data_fim !== date('Y-m-d')): ?>
            <a href="fechamento_caixa.php" class="btn btn-secondary btn-sm">Hoje</a>
        <?php endif; ?>
    </div>
</form>

<!-- Cards de totais -->
<div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(160px,1fr)); gap:15px; margin-bottom:25px;">
    <div class="card" style="padding:18px; border-left:4px solid var(--warning); margin:0;">
        <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase; margin-bottom:4px;">A Confirmar</div>
        <div style="font-size:22px; font-weight:700; color:var(--warning);"><?= formatarMoeda($totais['total_pendente'] ?? 0) ?></div>
        <div style="font-size:12px; color:var(--text-muted);"><?= (int)($totais['qtd_pendente'] ?? 0) ?> pedido(s)</div>
    </div>
    <div class="card" style="padding:18px; border-left:4px solid var(--success); margin:0;">
        <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase; margin-bottom:4px;">Confirmado</div>
        <div style="font-size:22px; font-weight:700; color:var(--success);"><?= formatarMoeda($totais['total_confirmado'] ?? 0) ?></div>
        <div style="font-size:12px; color:var(--text-muted);"><?= (int)($totais['qtd_confirmado'] ?? 0) ?> pedido(s)</div>
    </div>
    <div class="card" style="padding:18px; border-left:4px solid #22c55e; margin:0;">
        <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase; margin-bottom:4px;">Dinheiro</div>
        <div style="font-size:18px; font-weight:700;"><?= formatarMoeda($totais['total_dinheiro'] ?? 0) ?></div>
    </div>
    <div class="card" style="padding:18px; border-left:4px solid #3b82f6; margin:0;">
        <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase; margin-bottom:4px;">PIX</div>
        <div style="font-size:18px; font-weight:700;"><?= formatarMoeda($totais['total_pix'] ?? 0) ?></div>
    </div>
    <div class="card" style="padding:18px; border-left:4px solid #8b5cf6; margin:0;">
        <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase; margin-bottom:4px;">Cartão</div>
        <div style="font-size:18px; font-weight:700;"><?= formatarMoeda($totais['total_cartao'] ?? 0) ?></div>
    </div>
    <div class="card" style="padding:18px; border-left:4px solid #f97316; margin:0;">
        <div style="font-size:12px; color:var(--text-muted); text-transform:uppercase; margin-bottom:4px;">Fiado</div>
        <div style="font-size:18px; font-weight:700;"><?= formatarMoeda($totais['total_fiado'] ?? 0) ?></div>
    </div>
</div>

<!-- Pedidos pendentes de confirmação -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-clock" style="color:var(--warning);"></i>
            Aguardando Confirmação de Recebimento
        </h3>
    </div>

    <?php if ($pendentes->num_rows === 0): ?>
        <div style="padding:30px; text-align:center; color:var(--text-muted);">
            <i class="fas fa-check-circle" style="font-size:2rem; color:var(--success); display:block; margin-bottom:10px;"></i>
            Nenhum pedido pendente para o período selecionado.
        </div>
    <?php else: ?>
    <div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>Pedido</th>
                <th>Cliente</th>
                <th>Filial</th>
                <th>Itens</th>
                <th>Forma</th>
                <th>Hora</th>
                <th style="text-align:right;">Total</th>
                <th>Ação</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($p = $pendentes->fetch_assoc()): ?>
            <?php
                // Buscar itens do pedido para exibição no modal
                $stmt_it = $conn->prepare("
                    SELECT pi.quantidade, pi.preco_unitario, pi.subtotal, pr.nome AS produto_nome
                    FROM pedido_itens pi
                    INNER JOIN produtos pr ON pr.id = pi.produto_id
                    WHERE pi.pedido_id = ?
                ");
                $stmt_it->bind_param('i', $p['id']);
                $stmt_it->execute();
                $itens_ped = $stmt_it->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt_it->close();
                $itens_resumo = implode(', ', array_map(fn($i) => $i['quantidade'] . 'x ' . $i['produto_nome'], $itens_ped));
                $itens_json   = htmlspecialchars(json_encode($itens_ped, JSON_UNESCAPED_UNICODE), ENT_QUOTES);
            ?>
            <tr>
                <td><strong><?= escapar($p['numero']) ?></strong>
                    <?php if ($p['origem'] === 'whatsapp'): ?>
                        <br><small style="color:#10b981;"><i class="fab fa-whatsapp"></i> WhatsApp</small>
                    <?php endif; ?>
                </td>
                <td>
                    <?= escapar($p['cliente_nome']) ?>
                    <?php if ($p['whatsapp']): ?>
                        <br><small style="color:var(--text-muted);"><?= escapar($p['whatsapp']) ?></small>
                    <?php endif; ?>
                </td>
                <td><span class="badge badge-info"><?= escapar($p['filial_nome']) ?></span></td>
                <td style="font-size:13px; max-width:220px;"><?= escapar($itens_resumo) ?></td>
                <td><?= ucfirst($p['forma_pagamento']) ?></td>
                <td style="font-size:13px;"><?= date('H:i', strtotime($p['criado_em'])) ?></td>
                <td style="text-align:right; font-weight:700; color:var(--warning);"><?= formatarMoeda($p['total']) ?></td>
                <td>
                    <button class="btn btn-primary btn-sm"
                        onclick="abrirConfirmacao(
                            <?= $p['id'] ?>,
                            '<?= escapar($p['numero']) ?>',
                            '<?= escapar($p['cliente_nome']) ?>',
                            <?= $p['total'] ?>,
                            '<?= $p['forma_pagamento'] ?>',
                            '<?= $itens_json ?>'
                        )">
                        <i class="fas fa-hand-holding-dollar"></i> Confirmar
                    </button>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<!-- Pedidos confirmados no período -->
<?php if ($confirmados->num_rows > 0): ?>
<div class="card" style="margin-top:20px;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-check-circle" style="color:var(--success);"></i>
            Confirmados no Período
        </h3>
    </div>
    <div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>Pedido</th>
                <th>Cliente</th>
                <th>Filial</th>
                <th>Forma</th>
                <th>Confirmado em</th>
                <th style="text-align:right;">Valor Recebido</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($c = $confirmados->fetch_assoc()): ?>
            <tr>
                <td><strong><?= escapar($c['numero']) ?></strong></td>
                <td><?= escapar($c['cliente_nome']) ?></td>
                <td><span class="badge badge-info"><?= escapar($c['filial_nome']) ?></span></td>
                <td><?= ucfirst($c['fin_forma'] ?? $c['forma_pagamento']) ?></td>
                <td style="font-size:13px;"><?= formatarData($c['data_pagamento']) ?></td>
                <td style="text-align:right; font-weight:700; color:var(--success);"><?= formatarMoeda($c['fin_valor'] ?? $c['total']) ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; ?>

<!-- Modal de confirmação -->
<div class="modal-overlay" id="modalConfirmacao">
    <div class="modal" style="max-width:480px;">
        <div class="modal-header">
            <h3><i class="fas fa-hand-holding-dollar"></i> Confirmar Recebimento</h3>
            <button class="modal-close" onclick="closeModal('modalConfirmacao')">&times;</button>
        </div>
        <form method="POST" id="formConfirmacao">
            <input type="hidden" name="confirmar_pedido" value="1">
            <input type="hidden" name="pedido_id" id="conf_pedido_id">

            <div class="modal-body">
                <!-- Resumo do pedido -->
                <div style="background:var(--gray-100); border-radius:8px; padding:12px; margin-bottom:16px;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                        <strong id="conf_numero" style="color:var(--primary);"></strong>
                        <span id="conf_cliente" style="color:var(--text-muted); font-size:13px;"></span>
                    </div>
                    <div id="conf_itens" style="font-size:13px; color:var(--text-muted); margin-bottom:8px;"></div>
                    <div style="display:flex; justify-content:space-between; border-top:1px solid var(--gray-200); padding-top:8px;">
                        <span>Total do pedido:</span>
                        <strong id="conf_total_label" style="font-size:16px;"></strong>
                    </div>
                </div>

                <!-- Valor recebido -->
                <div class="form-group">
                    <label><i class="fas fa-money-bill-wave"></i> Valor Recebido (R$) *</label>
                    <input type="number" name="valor_recebido" id="conf_valor_recebido"
                           step="0.01" min="0.01" required
                           style="font-size:18px; font-weight:700;"
                           oninput="calcularDiferenca()">
                    <small id="msg_diferenca" style="color:var(--danger); font-size:12px; display:none;"></small>
                </div>

                <!-- Forma de pagamento confirmada -->
                <div class="form-group">
                    <label><i class="fas fa-credit-card"></i> Forma de Pagamento</label>
                    <select name="forma_pagamento_conf" id="conf_forma_pgto">
                        <option value="dinheiro">Dinheiro</option>
                        <option value="pix">PIX</option>
                        <option value="cartao">Cartão</option>
                        <option value="fiado">Fiado</option>
                    </select>
                </div>

                <!-- Observação -->
                <div class="form-group">
                    <label>Observação (opcional)</label>
                    <input type="text" name="obs_confirmacao" placeholder="Troco, recebido por quem, etc.">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalConfirmacao')">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i> Confirmar Recebimento
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let totalPedidoAtual = 0;

function abrirConfirmacao(pedidoId, numero, cliente, total, forma, itensJson) {
    totalPedidoAtual = parseFloat(total);

    document.getElementById('conf_pedido_id').value     = pedidoId;
    document.getElementById('conf_numero').textContent  = numero;
    document.getElementById('conf_cliente').textContent = cliente;
    document.getElementById('conf_total_label').textContent = 'R$ ' + total.toFixed(2).replace('.', ',');
    document.getElementById('conf_valor_recebido').value    = total.toFixed(2);

    // Forma de pagamento padrão
    const sel = document.getElementById('conf_forma_pgto');
    for (let i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === forma) { sel.selectedIndex = i; break; }
    }

    // Montar lista de itens
    let itens = '';
    try {
        const arr = typeof itensJson === 'string' ? JSON.parse(itensJson.replace(/&quot;/g, '"')) : itensJson;
        itens = arr.map(i => `${i.quantidade}x ${i.produto_nome} — R$ ${parseFloat(i.subtotal).toFixed(2).replace('.', ',')}`).join('<br>');
    } catch(e) { itens = '—'; }
    document.getElementById('conf_itens').innerHTML = itens;

    calcularDiferenca();
    openModal('modalConfirmacao');
    setTimeout(() => document.getElementById('conf_valor_recebido').focus(), 200);
}

function calcularDiferenca() {
    const recebido   = parseFloat(document.getElementById('conf_valor_recebido').value) || 0;
    const diferenca  = recebido - totalPedidoAtual;
    const msgEl      = document.getElementById('msg_diferenca');

    if (Math.abs(diferenca) < 0.01) {
        msgEl.style.display = 'none';
        return;
    }
    const sinal = diferenca > 0 ? '+' : '';
    msgEl.textContent = diferenca > 0
        ? `Troco: R$ ${Math.abs(diferenca).toFixed(2).replace('.', ',')} (será registrado)`
        : `⚠️ Valor menor que o total! Diferença: R$ ${sinal}${diferenca.toFixed(2).replace('.', ',')}`;
    msgEl.style.color   = diferenca > 0 ? 'var(--success)' : 'var(--danger)';
    msgEl.style.display = 'block';
}
</script>

<?php require_once '../includes/footer.php'; ?>
