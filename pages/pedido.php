<?php
/**
 * DETALHES DO PEDIDO
 * ------------------------------------------------------------
 * Mostra todas as informações de um pedido específico.
 * Permite imprimir o cupom/recibo e atribuir motoboy.
 */
require_once '../config/config.php';
require_once '../includes/funcoes.php';
verificarLogin();

$id = (int) ($_GET['id'] ?? 0);
$empresa_id = empresaAtiva();

if ($id <= 0) {
    header('Location: vendas.php');
    exit;
}

// Atribuir motoboy
if (isset($_POST['motoboy_id'])) {
    $motoboy_id = (int) $_POST['motoboy_id'];
    $stmt = $conn->prepare("UPDATE pedidos SET motoboy_id = ? WHERE id = ? AND empresa_id = ?");
    $stmt->bind_param('iii', $motoboy_id, $id, $empresa_id);
    $stmt->execute();
    $stmt->close();
    registrarLog($conn, 'Atribuir Motoboy', "Motoboy atribuído ao pedido #{$id}");
    header("Location: pedido.php?id={$id}&atualizado=1");
    exit;
}

// Buscar pedido
$stmt = $conn->prepare("
    SELECT p.*, p.numero AS numero_pedido, p.criado_em AS data_pedido,
           c.nome AS cliente_nome, c.telefone AS cliente_telefone,
           c.endereco, c.numero AS cliente_numero, c.bairro, c.cidade, c.referencia,
           m.nome AS motoboy_nome, m.telefone AS motoboy_telefone,
           u.nome AS vendedor_nome,
           e.nome AS empresa_nome, e.cnpj, e.endereco AS empresa_endereco, e.telefone AS empresa_telefone,
           f.nome AS filial_nome
    FROM pedidos p
    INNER JOIN clientes c ON c.id = p.cliente_id
    INNER JOIN empresas e ON e.id = p.empresa_id
    INNER JOIN filiais f ON f.id = p.filial_id
    LEFT JOIN motoboys m ON m.id = p.motoboy_id
    LEFT JOIN usuarios u ON u.id = p.usuario_id
    WHERE p.id = ? AND p.empresa_id = ?
");
$stmt->bind_param('ii', $id, $empresa_id);
$stmt->execute();
$pedido = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$pedido) {
    header('Location: vendas.php');
    exit;
}

// Buscar itens
$stmt = $conn->prepare("
    SELECT pi.*, pr.nome AS produto_nome, pr.codigo, pr.tipo
    FROM pedido_itens pi
    INNER JOIN produtos pr ON pr.id = pi.produto_id
    WHERE pi.pedido_id = ?
");
$stmt->bind_param('i', $id);
$stmt->execute();
$itens = $stmt->get_result();
$stmt->close();

// Motoboys para atribuir
$motoboys = $conn->query("
    SELECT id, nome FROM motoboys
    WHERE empresa_id = {$empresa_id} AND status != 'indisponivel'
    ORDER BY nome
");

$titulo_pagina = 'Pedido ' . $pedido['numero_pedido'];
$menu_ativo = 'vendas';
require_once '../includes/header.php';
?>

<?php if (isset($_GET['novo'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> Pedido criado com sucesso!
    </div>
<?php endif; ?>

<?php if (isset($_GET['atualizado'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> Pedido atualizado!
    </div>
<?php endif; ?>

<div class="card no-print">
    <div class="card-header">
        <h2><i class="fas fa-file-invoice"></i> Pedido <?= escapar($pedido['numero_pedido']) ?></h2>
        <div>
            <button onclick="window.print()" class="btn btn-info">
                <i class="fas fa-print"></i> Imprimir
            </button>
            <a href="vendas.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Voltar
            </a>
        </div>
    </div>

    <div class="pedido-grid">
        <div>
            <h4>Status</h4>
            <p><?= statusBadge($pedido['status']) ?></p>
        </div>
        <div>
            <h4>Data</h4>
            <p><?= formatarData($pedido['data_pedido'], true) ?></p>
        </div>
        <div>
            <h4>Vendedor</h4>
            <p><?= escapar($pedido['vendedor_nome'] ?? '—') ?></p>
        </div>
        <div>
            <h4>Filial</h4>
            <p><?= escapar($pedido['filial_nome']) ?></p>
        </div>
    </div>

    <hr>

    <div class="pedido-grid">
        <div>
            <h4><i class="fas fa-user"></i> Cliente</h4>
            <p>
                <strong><?= escapar($pedido['cliente_nome']) ?></strong><br>
                <i class="fas fa-phone"></i> <?= escapar($pedido['cliente_telefone']) ?><br>
                <i class="fas fa-map-marker-alt"></i>
                <?= escapar($pedido['endereco'] . ', ' . $pedido['cliente_numero']) ?> —
                <?= escapar($pedido['bairro'] . ' / ' . $pedido['cidade']) ?>
                <?php if ($pedido['referencia']): ?>
                    <br><small>Ref: <?= escapar($pedido['referencia']) ?></small>
                <?php endif; ?>
            </p>
        </div>

        <div>
            <h4><i class="fas fa-motorcycle"></i> Motoboy</h4>
            <?php if ($pedido['motoboy_nome']): ?>
                <p>
                    <strong><?= escapar($pedido['motoboy_nome']) ?></strong><br>
                    <i class="fas fa-phone"></i> <?= escapar($pedido['motoboy_telefone']) ?>
                </p>
            <?php else: ?>
                <form method="POST" class="form-inline">
                    <select name="motoboy_id" required class="form-control">
                        <option value="">— Selecione —</option>
                        <?php while ($m = $motoboys->fetch_assoc()): ?>
                            <option value="<?= $m['id'] ?>"><?= escapar($m['nome']) ?></option>
                        <?php endwhile; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Atribuir</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <hr>

    <h4><i class="fas fa-box"></i> Itens do Pedido</h4>
    <table class="table">
        <thead>
            <tr>
                <th>Código</th>
                <th>Produto</th>
                <th>Tipo</th>
                <th>Qtd.</th>
                <th>Preço Unit.</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($it = $itens->fetch_assoc()): ?>
                <tr>
                    <td><?= escapar($it['codigo']) ?></td>
                    <td><?= escapar($it['produto_nome']) ?></td>
                    <td><?= tipoProdutoNome($it['tipo']) ?></td>
                    <td><?= $it['quantidade'] ?></td>
                    <td><?= formatarMoeda($it['preco_unitario']) ?></td>
                    <td><strong><?= formatarMoeda($it['subtotal']) ?></strong></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="totais-box">
        <div>Subtotal: <strong><?= formatarMoeda($pedido['subtotal']) ?></strong></div>
        <div>Taxa de entrega: <strong><?= formatarMoeda($pedido['taxa_entrega']) ?></strong></div>
        <div class="total-final">TOTAL: <strong><?= formatarMoeda($pedido['total']) ?></strong></div>
        <div style="margin-top:0.5rem;">Forma de pagamento: <strong><?= ucfirst(str_replace('_', ' ', $pedido['forma_pagamento'])) ?></strong></div>
    </div>

    <?php if ($pedido['observacoes']): ?>
        <div class="info-box">
            <strong>Observações:</strong><br>
            <?= nl2br(escapar($pedido['observacoes'])) ?>
        </div>
    <?php endif; ?>
</div>

<!-- VERSÃO PARA IMPRESSÃO (cupom) -->
<div class="print-only" style="display:none;">
    <div class="cupom">
        <div class="cupom-header">
            <h2><?= escapar($pedido['empresa_nome']) ?></h2>
            <p>
                CNPJ: <?= escapar($pedido['cnpj']) ?><br>
                <?= escapar($pedido['empresa_endereco']) ?><br>
                Tel: <?= escapar($pedido['empresa_telefone']) ?>
            </p>
        </div>
        <hr>
        <p>
            <strong>PEDIDO:</strong> <?= escapar($pedido['numero_pedido']) ?><br>
            <strong>Data:</strong> <?= formatarData($pedido['data_pedido'], true) ?><br>
            <strong>Cliente:</strong> <?= escapar($pedido['cliente_nome']) ?><br>
            <strong>Endereço:</strong> <?= escapar($pedido['endereco'] . ', ' . $pedido['cliente_numero'] . ' - ' . $pedido['bairro']) ?><br>
            <strong>Telefone:</strong> <?= escapar($pedido['cliente_telefone']) ?>
        </p>
        <hr>
        <table style="width:100%; border-collapse:collapse;">
            <thead>
                <tr>
                    <th style="text-align:left;">Qtd Produto</th>
                    <th style="text-align:right;">Valor</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $itens->data_seek(0);
                while ($it = $itens->fetch_assoc()):
                ?>
                <tr>
                    <td><?= $it['quantidade'] ?>x <?= escapar($it['produto_nome']) ?></td>
                    <td style="text-align:right;"><?= formatarMoeda($it['subtotal']) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <hr>
        <p style="text-align:right;">
            Subtotal: <?= formatarMoeda($pedido['subtotal']) ?><br>
            Taxa entrega: <?= formatarMoeda($pedido['taxa_entrega']) ?><br>
            <strong style="font-size:1.2em;">TOTAL: <?= formatarMoeda($pedido['total']) ?></strong><br>
            Pagamento: <?= ucfirst(str_replace('_', ' ', $pedido['forma_pagamento'])) ?>
        </p>
        <hr>
        <p style="text-align:center; font-size:0.9em;">
            Obrigado pela preferência!<br>
            <?= date('d/m/Y H:i') ?>
        </p>
    </div>
</div>

<style>
.pedido-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin: 1rem 0;
}
.pedido-grid h4 { color: var(--text-muted); font-size: 0.85rem; text-transform: uppercase; margin-bottom: 0.25rem; }
.pedido-grid p { margin: 0; }
.form-inline { display: flex; gap: 0.5rem; }
.form-inline .form-control { flex: 1; }
.totais-box {
    background: var(--bg-light);
    padding: 1rem;
    border-radius: 8px;
    text-align: right;
    margin: 1rem 0;
}
.total-final { font-size: 1.3rem; color: var(--primary); border-top: 1px solid var(--border); padding-top: 0.5rem; margin-top: 0.5rem; }
.info-box {
    background: var(--bg-light);
    border-left: 4px solid var(--info);
    padding: 0.75rem;
    border-radius: 4px;
    margin-top: 1rem;
}

@media print {
    body { background: white !important; }
    .no-print, .sidebar, .topbar { display: none !important; }
    .main { margin-left: 0 !important; padding: 0 !important; }
    .print-only { display: block !important; }
    .cupom { max-width: 80mm; margin: 0 auto; font-family: 'Courier New', monospace; font-size: 12px; }
    .cupom-header { text-align: center; }
    .cupom-header h2 { margin: 0; font-size: 14px; }
}
</style>

<?php require_once '../includes/footer.php'; ?>
