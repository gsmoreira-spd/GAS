<?php
/**
 * MÓDULO FINANCEIRO
 * ------------------------------------------------------------
 * Lista contas a receber e a pagar.
 * Permite criar lançamento manual (despesa ou receita).
 * Permite marcar como pago.
 * Calcula totais e saldo do dia/período.
 */
require_once '../config/config.php';
require_once '../includes/funcoes.php';
verificarLogin();

$empresa_id = empresaAtiva();
$filial_id  = filialAtiva();

if (!$empresa_id) {
    header('Location: selecionar_empresa.php');
    exit;
}

// ============================================================
// AÇÕES
// ============================================================

// Marcar como pago
if (isset($_GET['pagar'])) {
    $id = (int) $_GET['pagar'];
    $stmt = $conn->prepare("
        UPDATE financeiro
        SET status = 'pago', data_pagamento = CURDATE()
        WHERE id = ? AND empresa_id = ?
    ");
    $stmt->bind_param('ii', $id, $empresa_id);
    $stmt->execute();
    $stmt->close();
    registrarLog($conn, 'Pagar Lançamento', "Lançamento #{$id} marcado como pago");
    header('Location: financeiro.php?sucesso=pago');
    exit;
}

// Excluir
if (isset($_GET['excluir'])) {
    $id = (int) $_GET['excluir'];
    $stmt = $conn->prepare("DELETE FROM financeiro WHERE id = ? AND empresa_id = ? AND pedido_id IS NULL");
    $stmt->bind_param('ii', $id, $empresa_id);
    $stmt->execute();
    $stmt->close();
    header('Location: financeiro.php?sucesso=excluido');
    exit;
}

// Salvar novo lançamento manual
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo            = limpar($_POST['tipo']);
    $descricao       = limpar($_POST['descricao']);
    $valor           = (float) str_replace(',', '.', $_POST['valor']);
    $forma_pagamento = limpar($_POST['forma_pagamento']);
    $status          = limpar($_POST['status']);
    $data_vencimento = limpar($_POST['data_vencimento']);
    $data_pagamento  = $status === 'pago' ? $data_vencimento : null;

    $stmt = $conn->prepare("
        INSERT INTO financeiro
        (empresa_id, filial_id, tipo, descricao, valor, forma_pagamento, status, data_vencimento, data_pagamento)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('iissdssss', $empresa_id, $filial_id, $tipo, $descricao, $valor,
                      $forma_pagamento, $status, $data_vencimento, $data_pagamento);
    $stmt->execute();
    $stmt->close();
    registrarLog($conn, 'Criar Lançamento', "Lançamento manual: {$descricao}");
    header('Location: financeiro.php?sucesso=criado');
    exit;
}

// ============================================================
// FILTROS
// ============================================================
$filtro_tipo   = $_GET['tipo']   ?? '';
$filtro_status = $_GET['status'] ?? '';
$filtro_data_de = $_GET['data_de']  ?? date('Y-m-01');
$filtro_data_ate = $_GET['data_ate'] ?? date('Y-m-t');

$where = "f.empresa_id = {$empresa_id}";
if ($filtro_tipo)   $where .= " AND f.tipo = '" . $conn->real_escape_string($filtro_tipo) . "'";
if ($filtro_status) $where .= " AND f.status = '" . $conn->real_escape_string($filtro_status) . "'";
$where .= " AND DATE(f.data_vencimento) BETWEEN '{$filtro_data_de}' AND '{$filtro_data_ate}'";

// Totais
$totais = $conn->query("
    SELECT
        SUM(CASE WHEN tipo='receita' AND status='pago' THEN valor ELSE 0 END) AS receita_paga,
        SUM(CASE WHEN tipo='receita' AND status='pendente' THEN valor ELSE 0 END) AS receita_pendente,
        SUM(CASE WHEN tipo='despesa' AND status='pago' THEN valor ELSE 0 END) AS despesa_paga,
        SUM(CASE WHEN tipo='despesa' AND status='pendente' THEN valor ELSE 0 END) AS despesa_pendente
    FROM financeiro f
    WHERE {$where}
")->fetch_assoc();

$saldo = ($totais['receita_paga'] ?? 0) - ($totais['despesa_paga'] ?? 0);

// Listagem
$lancamentos = $conn->query("
    SELECT f.*, p.numero AS numero_pedido, c.nome AS cliente_nome
    FROM financeiro f
    LEFT JOIN pedidos p ON p.id = f.pedido_id
    LEFT JOIN clientes c ON c.id = p.cliente_id
    WHERE {$where}
    ORDER BY f.data_vencimento DESC, f.id DESC
");

$titulo_pagina = 'Financeiro';
$menu_ativo = 'financeiro';
require_once '../includes/header.php';
?>

<?php if (isset($_GET['sucesso'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> Operação realizada com sucesso!
    </div>
<?php endif; ?>

<!-- Cards de resumo -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="icon-box green"><i class="fas fa-arrow-down"></i></div>
        <div>
            <h3>Receitas Recebidas</h3>
            <p class="stat-value"><?= formatarMoeda($totais['receita_paga'] ?? 0) ?></p>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon-box orange"><i class="fas fa-clock"></i></div>
        <div>
            <h3>A Receber</h3>
            <p class="stat-value"><?= formatarMoeda($totais['receita_pendente'] ?? 0) ?></p>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon-box red"><i class="fas fa-arrow-up"></i></div>
        <div>
            <h3>Despesas Pagas</h3>
            <p class="stat-value"><?= formatarMoeda($totais['despesa_paga'] ?? 0) ?></p>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon-box <?= $saldo >= 0 ? 'blue' : 'red' ?>">
            <i class="fas fa-wallet"></i>
        </div>
        <div>
            <h3>Saldo (Recebido - Pago)</h3>
            <p class="stat-value"><?= formatarMoeda($saldo) ?></p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h2><i class="fas fa-coins"></i> Lançamentos Financeiros</h2>
        <button class="btn btn-primary" onclick="openModal('modalLancamento')">
            <i class="fas fa-plus"></i> Novo Lançamento
        </button>
    </div>

    <!-- Filtros -->
    <form method="GET" class="form-filters">
        <div class="form-group">
            <label>Tipo</label>
            <select name="tipo" class="form-control">
                <option value="">Todos</option>
                <option value="receita" <?= $filtro_tipo === 'receita' ? 'selected' : '' ?>>Receita</option>
                <option value="despesa" <?= $filtro_tipo === 'despesa' ? 'selected' : '' ?>>Despesa</option>
            </select>
        </div>
        <div class="form-group">
            <label>Status</label>
            <select name="status" class="form-control">
                <option value="">Todos</option>
                <option value="pendente" <?= $filtro_status === 'pendente' ? 'selected' : '' ?>>Pendente</option>
                <option value="pago" <?= $filtro_status === 'pago' ? 'selected' : '' ?>>Pago</option>
                <option value="cancelado" <?= $filtro_status === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
            </select>
        </div>
        <div class="form-group">
            <label>De</label>
            <input type="date" name="data_de" value="<?= $filtro_data_de ?>" class="form-control">
        </div>
        <div class="form-group">
            <label>Até</label>
            <input type="date" name="data_ate" value="<?= $filtro_data_ate ?>" class="form-control">
        </div>
        <div class="form-group">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrar</button>
        </div>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>Vencimento</th>
                <th>Tipo</th>
                <th>Descrição</th>
                <th>Cliente/Pedido</th>
                <th>Valor</th>
                <th>Pagamento</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($lancamentos->num_rows === 0): ?>
                <tr><td colspan="8" class="text-center">Nenhum lançamento encontrado.</td></tr>
            <?php else: ?>
                <?php while ($l = $lancamentos->fetch_assoc()): ?>
                    <tr>
                        <td><?= formatarData($l['data_vencimento']) ?></td>
                        <td>
                            <?php if ($l['tipo'] === 'receita'): ?>
                                <span class="badge badge-success"><i class="fas fa-arrow-down"></i> Receita</span>
                            <?php else: ?>
                                <span class="badge badge-danger"><i class="fas fa-arrow-up"></i> Despesa</span>
                            <?php endif; ?>
                        </td>
                        <td><?= escapar($l['descricao']) ?></td>
                        <td>
                            <?php if ($l['numero_pedido']): ?>
                                <a href="pedido.php?id=<?= $l['pedido_id'] ?>"><?= escapar($l['numero_pedido']) ?></a>
                                <?php if ($l['cliente_nome']): ?>
                                    <br><small><?= escapar($l['cliente_nome']) ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><strong><?= formatarMoeda($l['valor']) ?></strong></td>
                        <td><?= ucfirst(str_replace('_', ' ', $l['forma_pagamento'])) ?></td>
                        <td>
                            <?php if ($l['status'] === 'pago'): ?>
                                <span class="badge badge-success">Pago</span>
                            <?php elseif ($l['status'] === 'cancelado'): ?>
                                <span class="badge badge-secondary">Cancelado</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Pendente</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($l['status'] === 'pendente'): ?>
                                <a href="?pagar=<?= $l['id'] ?>" class="btn btn-sm btn-success"
                                   onclick="return confirm('Marcar como pago?')">
                                    <i class="fas fa-check"></i> Pagar
                                </a>
                            <?php endif; ?>
                            <?php if (!$l['pedido_id']): ?>
                                <a href="?excluir=<?= $l['id'] ?>" class="btn btn-sm btn-danger"
                                   onclick="return confirm('Excluir lançamento?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal: Novo Lançamento -->
<div id="modalLancamento" class="modal-overlay">
    <div class="modal">
        <div class="modal-header">
            <h3>Novo Lançamento Financeiro</h3>
            <button class="close-btn" onclick="closeModal('modalLancamento')">&times;</button>
        </div>
        <form method="POST">
            <div class="grid-2">
                <div class="form-group">
                    <label>Tipo *</label>
                    <select name="tipo" required class="form-control">
                        <option value="receita">Receita</option>
                        <option value="despesa">Despesa</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Valor (R$) *</label>
                    <input type="text" name="valor" required class="form-control" placeholder="0,00">
                </div>
            </div>
            <div class="form-group">
                <label>Descrição *</label>
                <input type="text" name="descricao" required class="form-control" placeholder="Ex: Pagamento de fornecedor">
            </div>
            <div class="grid-2">
                <div class="form-group">
                    <label>Forma de Pagamento *</label>
                    <select name="forma_pagamento" required class="form-control">
                        <option value="dinheiro">Dinheiro</option>
                        <option value="pix">PIX</option>
                        <option value="cartao">Cartão</option>
                        <option value="boleto">Boleto</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status *</label>
                    <select name="status" required class="form-control">
                        <option value="pendente">Pendente</option>
                        <option value="pago">Já Pago</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Data de Vencimento *</label>
                <input type="date" name="data_vencimento" required value="<?= date('Y-m-d') ?>" class="form-control">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalLancamento')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
