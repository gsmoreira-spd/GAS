<?php
/**
 * MÓDULO DE RELATÓRIOS
 * ------------------------------------------------------------
 * Relatórios de vendas, estoque, clientes e financeiro.
 * Filtros por data, filial, produto, etc.
 * Botão para imprimir via window.print() com CSS @media print.
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

// Aba ativa
$aba = $_GET['aba'] ?? 'vendas';

// Filtros comuns
$data_de  = $_GET['data_de']  ?? date('Y-m-01');
$data_ate = $_GET['data_ate'] ?? date('Y-m-d');

// ============================================================
// RELATÓRIO DE VENDAS
// ============================================================
$relVendas = null;
$totalVendas = 0;
$totalPedidos = 0;
if ($aba === 'vendas') {
    $relVendas = $conn->query("
        SELECT p.numero AS numero_pedido, p.criado_em AS data_pedido, p.total, p.forma_pagamento, p.status,
               c.nome AS cliente, m.nome AS motoboy, f.nome AS filial
        FROM pedidos p
        INNER JOIN clientes c ON c.id = p.cliente_id
        LEFT JOIN motoboys m ON m.id = p.motoboy_id
        INNER JOIN filiais f ON f.id = p.filial_id
        WHERE p.empresa_id = {$empresa_id}
          AND DATE(p.criado_em) BETWEEN '{$data_de}' AND '{$data_ate}'
          AND p.status != 'cancelado'
        ORDER BY p.criado_em DESC
    ");
    // Totais
    $t = $conn->query("
        SELECT COUNT(*) AS qtd, COALESCE(SUM(total),0) AS soma
        FROM pedidos
        WHERE empresa_id = {$empresa_id}
          AND DATE(criado_em) BETWEEN '{$data_de}' AND '{$data_ate}'
          AND status != 'cancelado'
    ")->fetch_assoc();
    $totalPedidos = $t['qtd'];
    $totalVendas  = $t['soma'];
}

// ============================================================
// RELATÓRIO DE ESTOQUE
// ============================================================
$relEstoque = null;
if ($aba === 'estoque') {
    $relEstoque = $conn->query("
        SELECT p.codigo, p.nome, p.tipo, p.preco_venda, p.estoque_minimo,
               COALESCE(e.quantidade, 0) AS quantidade,
               f.nome AS filial
        FROM produtos p
        LEFT JOIN estoque e ON e.produto_id = p.id AND e.filial_id = {$filial_id}
        INNER JOIN filiais f ON f.id = {$filial_id}
        WHERE p.empresa_id = {$empresa_id} AND p.status = 'ativo'
        ORDER BY p.nome
    ");
}

// ============================================================
// RELATÓRIO DE CLIENTES
// ============================================================
$relClientes = null;
if ($aba === 'clientes') {
    $relClientes = $conn->query("
        SELECT c.nome, c.telefone, c.email, c.bairro, c.cidade,
               COUNT(p.id) AS total_pedidos,
               COALESCE(SUM(p.total), 0) AS total_gasto
        FROM clientes c
        LEFT JOIN pedidos p ON p.cliente_id = c.id AND p.status != 'cancelado'
        WHERE c.empresa_id = {$empresa_id}
        GROUP BY c.id
        ORDER BY total_gasto DESC
    ");
}

// ============================================================
// RELATÓRIO FINANCEIRO
// ============================================================
$relFinanceiro = null;
$finTotais = [];
if ($aba === 'financeiro') {
    $relFinanceiro = $conn->query("
        SELECT f.tipo, f.descricao, f.valor, f.forma_pagamento, f.status,
               f.data_vencimento, f.data_pagamento
        FROM financeiro f
        WHERE f.empresa_id = {$empresa_id}
          AND DATE(f.data_vencimento) BETWEEN '{$data_de}' AND '{$data_ate}'
        ORDER BY f.data_vencimento DESC
    ");
    $finTotais = $conn->query("
        SELECT
            SUM(CASE WHEN tipo='receita' THEN valor ELSE 0 END) AS total_receitas,
            SUM(CASE WHEN tipo='despesa' THEN valor ELSE 0 END) AS total_despesas,
            SUM(CASE WHEN tipo='receita' AND status='pago' THEN valor ELSE 0 END) AS receita_paga,
            SUM(CASE WHEN tipo='despesa' AND status='pago' THEN valor ELSE 0 END) AS despesa_paga
        FROM financeiro
        WHERE empresa_id = {$empresa_id}
          AND DATE(data_vencimento) BETWEEN '{$data_de}' AND '{$data_ate}'
    ")->fetch_assoc();
}

// ============================================================
// RELATÓRIO DE PRODUTOS MAIS VENDIDOS
// ============================================================
$relProdutos = null;
if ($aba === 'produtos') {
    $relProdutos = $conn->query("
        SELECT pr.codigo, pr.nome, pr.tipo,
               SUM(pi.quantidade) AS qtd_vendida,
               SUM(pi.subtotal) AS receita
        FROM pedido_itens pi
        INNER JOIN produtos pr ON pr.id = pi.produto_id
        INNER JOIN pedidos p ON p.id = pi.pedido_id
        WHERE p.empresa_id = {$empresa_id}
          AND DATE(p.criado_em) BETWEEN '{$data_de}' AND '{$data_ate}'
          AND p.status != 'cancelado'
        GROUP BY pi.produto_id
        ORDER BY qtd_vendida DESC
    ");
}

$titulo_pagina = 'Relatórios';
$menu_ativo = 'relatorios';
require_once '../includes/header.php';
?>

<div class="card no-print">
    <div class="card-header">
        <h2><i class="fas fa-chart-bar"></i> Relatórios</h2>
        <button onclick="window.print()" class="btn btn-info">
            <i class="fas fa-print"></i> Imprimir
        </button>
    </div>

    <!-- Abas -->
    <div class="tabs">
        <a href="?aba=vendas&data_de=<?=$data_de?>&data_ate=<?=$data_ate?>"
           class="tab <?= $aba==='vendas'?'active':'' ?>">
           <i class="fas fa-shopping-cart"></i> Vendas
        </a>
        <a href="?aba=produtos&data_de=<?=$data_de?>&data_ate=<?=$data_ate?>"
           class="tab <?= $aba==='produtos'?'active':'' ?>">
           <i class="fas fa-trophy"></i> Mais Vendidos
        </a>
        <a href="?aba=estoque"
           class="tab <?= $aba==='estoque'?'active':'' ?>">
           <i class="fas fa-boxes"></i> Estoque
        </a>
        <a href="?aba=clientes"
           class="tab <?= $aba==='clientes'?'active':'' ?>">
           <i class="fas fa-users"></i> Clientes
        </a>
        <a href="?aba=financeiro&data_de=<?=$data_de?>&data_ate=<?=$data_ate?>"
           class="tab <?= $aba==='financeiro'?'active':'' ?>">
           <i class="fas fa-coins"></i> Financeiro
        </a>
    </div>

    <!-- Filtro de data (exceto estoque e clientes) -->
    <?php if (!in_array($aba, ['estoque', 'clientes'])): ?>
    <form method="GET" class="form-filters" style="margin-top:1rem;">
        <input type="hidden" name="aba" value="<?= $aba ?>">
        <div class="form-group">
            <label>De</label>
            <input type="date" name="data_de" value="<?= $data_de ?>" class="form-control">
        </div>
        <div class="form-group">
            <label>Até</label>
            <input type="date" name="data_ate" value="<?= $data_ate ?>" class="form-control">
        </div>
        <div class="form-group">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrar</button>
        </div>
    </form>
    <?php endif; ?>
</div>

<!-- ============================================================ -->
<!-- CONTEÚDO DO RELATÓRIO                                        -->
<!-- ============================================================ -->
<div class="card" id="relatorioConteudo">

<?php if ($aba === 'vendas'): ?>
    <h3 class="relatorio-titulo">Relatório de Vendas — <?= formatarData($data_de) ?> a <?= formatarData($data_ate) ?></h3>
    <div class="relatorio-resumo">
        <span><strong><?= $totalPedidos ?></strong> pedidos</span>
        <span>Total: <strong><?= formatarMoeda($totalVendas) ?></strong></span>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>Nº Pedido</th>
                <th>Data</th>
                <th>Cliente</th>
                <th>Filial</th>
                <th>Motoboy</th>
                <th>Pagamento</th>
                <th>Status</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($relVendas->num_rows === 0): ?>
                <tr><td colspan="8" class="text-center">Nenhuma venda no período.</td></tr>
            <?php else: ?>
                <?php while ($v = $relVendas->fetch_assoc()): ?>
                <tr>
                    <td><?= escapar($v['numero_pedido']) ?></td>
                    <td><?= formatarData($v['data_pedido']) ?></td>
                    <td><?= escapar($v['cliente']) ?></td>
                    <td><?= escapar($v['filial']) ?></td>
                    <td><?= escapar($v['motoboy'] ?? '—') ?></td>
                    <td><?= ucfirst(str_replace('_',' ',$v['forma_pagamento'])) ?></td>
                    <td><?= statusBadge($v['status']) ?></td>
                    <td><strong><?= formatarMoeda($v['total']) ?></strong></td>
                </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>

<?php elseif ($aba === 'produtos'): ?>
    <h3 class="relatorio-titulo">Produtos Mais Vendidos — <?= formatarData($data_de) ?> a <?= formatarData($data_ate) ?></h3>
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Código</th>
                <th>Produto</th>
                <th>Tipo</th>
                <th>Qtd. Vendida</th>
                <th>Receita</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($relProdutos->num_rows === 0): ?>
                <tr><td colspan="6" class="text-center">Nenhuma venda no período.</td></tr>
            <?php else: ?>
                <?php $rank = 1; while ($p = $relProdutos->fetch_assoc()): ?>
                <tr>
                    <td><?= $rank++ ?></td>
                    <td><?= escapar($p['codigo']) ?></td>
                    <td><?= escapar($p['nome']) ?></td>
                    <td><?= tipoProdutoNome($p['tipo']) ?></td>
                    <td><strong><?= $p['qtd_vendida'] ?></strong></td>
                    <td><strong><?= formatarMoeda($p['receita']) ?></strong></td>
                </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>

<?php elseif ($aba === 'estoque'): ?>
    <h3 class="relatorio-titulo">Estoque Atual — <?= escapar($conn->query("SELECT nome FROM filiais WHERE id={$filial_id}")->fetch_assoc()['nome'] ?? 'Filial') ?></h3>
    <table class="table">
        <thead>
            <tr>
                <th>Código</th>
                <th>Produto</th>
                <th>Tipo</th>
                <th>Preço</th>
                <th>Quantidade</th>
                <th>Mín.</th>
                <th>Situação</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($e = $relEstoque->fetch_assoc()): ?>
            <tr>
                <td><?= escapar($e['codigo']) ?></td>
                <td><?= escapar($e['nome']) ?></td>
                <td><?= tipoProdutoNome($e['tipo']) ?></td>
                <td><?= formatarMoeda($e['preco_venda']) ?></td>
                <td><strong><?= $e['quantidade'] ?></strong></td>
                <td><?= $e['estoque_minimo'] ?></td>
                <td>
                    <?php if ($e['quantidade'] <= $e['estoque_minimo']): ?>
                        <span class="badge badge-danger"><i class="fas fa-exclamation-triangle"></i> Baixo</span>
                    <?php else: ?>
                        <span class="badge badge-success"><i class="fas fa-check"></i> OK</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

<?php elseif ($aba === 'clientes'): ?>
    <h3 class="relatorio-titulo">Ranking de Clientes por Volume de Compras</h3>
    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Cliente</th>
                <th>Telefone</th>
                <th>Bairro</th>
                <th>Total Pedidos</th>
                <th>Total Gasto</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($relClientes->num_rows === 0): ?>
                <tr><td colspan="6" class="text-center">Nenhum cliente cadastrado.</td></tr>
            <?php else: ?>
                <?php $rank = 1; while ($c = $relClientes->fetch_assoc()): ?>
                <tr>
                    <td><?= $rank++ ?></td>
                    <td><?= escapar($c['nome']) ?></td>
                    <td><?= escapar($c['telefone']) ?></td>
                    <td><?= escapar($c['bairro']) ?></td>
                    <td><?= $c['total_pedidos'] ?></td>
                    <td><strong><?= formatarMoeda($c['total_gasto']) ?></strong></td>
                </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>

<?php elseif ($aba === 'financeiro'): ?>
    <h3 class="relatorio-titulo">Resumo Financeiro — <?= formatarData($data_de) ?> a <?= formatarData($data_ate) ?></h3>
    <div class="stats-grid" style="margin-bottom:1rem;">
        <div class="stat-card">
            <div class="icon-box green"><i class="fas fa-arrow-down"></i></div>
            <div>
                <h3>Total Receitas</h3>
                <p class="stat-value"><?= formatarMoeda($finTotais['total_receitas'] ?? 0) ?></p>
                <small>Recebido: <?= formatarMoeda($finTotais['receita_paga'] ?? 0) ?></small>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon-box red"><i class="fas fa-arrow-up"></i></div>
            <div>
                <h3>Total Despesas</h3>
                <p class="stat-value"><?= formatarMoeda($finTotais['total_despesas'] ?? 0) ?></p>
                <small>Pago: <?= formatarMoeda($finTotais['despesa_paga'] ?? 0) ?></small>
            </div>
        </div>
        <div class="stat-card">
            <div class="icon-box blue"><i class="fas fa-balance-scale"></i></div>
            <div>
                <h3>Resultado</h3>
                <p class="stat-value"><?= formatarMoeda(($finTotais['total_receitas'] ?? 0) - ($finTotais['total_despesas'] ?? 0)) ?></p>
            </div>
        </div>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>Tipo</th>
                <th>Descrição</th>
                <th>Valor</th>
                <th>Pagamento</th>
                <th>Vencimento</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($relFinanceiro->num_rows === 0): ?>
                <tr><td colspan="6" class="text-center">Nenhum lançamento no período.</td></tr>
            <?php else: ?>
                <?php while ($f = $relFinanceiro->fetch_assoc()): ?>
                <tr>
                    <td>
                        <?php if ($f['tipo'] === 'receita'): ?>
                            <span class="badge badge-success">Receita</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Despesa</span>
                        <?php endif; ?>
                    </td>
                    <td><?= escapar($f['descricao']) ?></td>
                    <td><strong><?= formatarMoeda($f['valor']) ?></strong></td>
                    <td><?= ucfirst(str_replace('_',' ',$f['forma_pagamento'])) ?></td>
                    <td><?= formatarData($f['data_vencimento']) ?></td>
                    <td>
                        <?php if ($f['status'] === 'pago'): ?>
                            <span class="badge badge-success">Pago</span>
                        <?php elseif ($f['status'] === 'cancelado'): ?>
                            <span class="badge badge-secondary">Cancelado</span>
                        <?php else: ?>
                            <span class="badge badge-warning">Pendente</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
<?php endif; ?>

</div>

<style>
.tabs { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 1rem; }
.tab {
    padding: 0.5rem 1rem;
    border-radius: 8px;
    background: var(--bg-light);
    color: var(--text);
    text-decoration: none;
    font-weight: 500;
    transition: background 0.2s;
}
.tab:hover { background: var(--primary); color: white; }
.tab.active { background: var(--primary); color: white; }
.relatorio-titulo { margin-bottom: 1rem; color: var(--text); }
.relatorio-resumo {
    display: flex; gap: 2rem; margin-bottom: 1rem;
    padding: 0.75rem; background: var(--bg-light); border-radius: 8px;
}
@media print {
    .no-print, .sidebar, .topbar { display: none !important; }
    .main { margin-left: 0 !important; padding: 0 !important; }
    body { background: white !important; color: black !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd; }
    .badge { border: 1px solid #999; }
}
</style>

<?php require_once '../includes/footer.php'; ?>
