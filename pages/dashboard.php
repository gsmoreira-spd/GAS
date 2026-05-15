<?php
/**
 * DASHBOARD PRINCIPAL
 * Tela inicial após login com estatísticas
 */
$titulo_pagina = "Dashboard";
$menu_ativo = "dashboard";
require_once '../includes/header.php';

$empresa_id = empresaAtiva();
$filiais_sql_dash = filiaisSQL('e.filial_id');

if (!$empresa_id) {
    echo "<div class='alert alert-warning'>Selecione uma empresa primeiro. <a href='selecionar_empresa.php'>Clique aqui</a></div>";
    require_once '../includes/footer.php';
    exit;
}

// === BUSCAR ESTATÍSTICAS ===
$hoje = date('Y-m-d');
$filiais_pedidos = filiaisSQL('filial_id');

// Total vendido hoje
$vendas_hoje = $conn->query("
    SELECT COALESCE(SUM(total),0) as total, COUNT(*) as qtd
    FROM pedidos WHERE empresa_id = {$empresa_id} AND {$filiais_pedidos} AND DATE(criado_em) = '{$hoje}' AND status != 'cancelado'
")->fetch_assoc();

// Total de entregas hoje
$entregas_hoje = $conn->query("
    SELECT COUNT(*) as qtd FROM pedidos
    WHERE empresa_id = {$empresa_id} AND {$filiais_pedidos} AND DATE(criado_em) = '{$hoje}' AND status = 'entregue'
")->fetch_assoc();

// Pedidos pendentes
$pedidos_pendentes = $conn->query("
    SELECT COUNT(*) as qtd FROM pedidos
    WHERE empresa_id = {$empresa_id} AND {$filiais_pedidos} AND status IN ('pendente','separacao','entrega')
")->fetch_assoc();

// Total de clientes
$stmt = $conn->prepare("SELECT COUNT(*) as qtd FROM clientes WHERE empresa_id = ? AND status = 'ativo'");
$stmt->bind_param("i", $empresa_id);
$stmt->execute();
$total_clientes = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Produtos com estoque baixo (todas filiais ativas)
$produtos_baixo = $conn->query("
    SELECT p.nome, p.estoque_minimo, COALESCE(e.quantidade, 0) as quantidade, f.nome as filial_nome
    FROM produtos p
    LEFT JOIN estoque e ON e.produto_id = p.id AND {$filiais_sql_dash}
    LEFT JOIN filiais f ON f.id = e.filial_id
    WHERE p.empresa_id = {$empresa_id} AND p.status = 'ativo' AND COALESCE(e.quantidade, 0) <= p.estoque_minimo
    LIMIT 15
");

// Últimos pedidos (todas filiais ativas)
$ultimos_pedidos = $conn->query("
    SELECT p.*, c.nome as cliente_nome, f.nome as filial_nome
    FROM pedidos p
    JOIN clientes c ON c.id = p.cliente_id
    LEFT JOIN filiais f ON f.id = p.filial_id
    WHERE p.empresa_id = {$empresa_id} AND " . filiaisSQL('p.filial_id') . "
    ORDER BY p.criado_em DESC LIMIT 5
");

// Vendas dos últimos 7 dias (para gráfico)
$vendas_grafico = [];
for ($i = 6; $i >= 0; $i--) {
    $data = date('Y-m-d', strtotime("-$i days"));
    $r = $conn->query("
        SELECT COALESCE(SUM(total),0) as total FROM pedidos
        WHERE empresa_id = {$empresa_id} AND " . filiaisSQL('filial_id') . " AND DATE(criado_em) = '{$data}' AND status != 'cancelado'
    ")->fetch_assoc();
    $vendas_grafico[] = ['data' => date('d/m', strtotime($data)), 'total' => (float)$r['total']];
}
?>

<!-- Cards de Estatísticas -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="icon-box icon-blue"><i class="fas fa-dollar-sign"></i></div>
        <div class="info">
            <h3><?= formatarMoeda($vendas_hoje['total']) ?></h3>
            <p>Vendas Hoje (<?= $vendas_hoje['qtd'] ?>)</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon-box icon-green"><i class="fas fa-truck"></i></div>
        <div class="info">
            <h3><?= $entregas_hoje['qtd'] ?></h3>
            <p>Entregas Realizadas</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon-box icon-orange"><i class="fas fa-clock"></i></div>
        <div class="info">
            <h3><?= $pedidos_pendentes['qtd'] ?></h3>
            <p>Pedidos em Andamento</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="icon-box icon-purple"><i class="fas fa-users"></i></div>
        <div class="info">
            <h3><?= $total_clientes['qtd'] ?></h3>
            <p>Clientes Cadastrados</p>
        </div>
    </div>
</div>

<!-- Gráfico e Estoque Baixo -->
<div style="display:grid; grid-template-columns: 2fr 1fr; gap:20px; margin-bottom:20px;">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-chart-line"></i> Vendas dos Últimos 7 Dias</h3>
        </div>
        <canvas id="graficoVendas" height="100"></canvas>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-triangle-exclamation"></i> Estoque Baixo</h3>
        </div>
        <?php if ($produtos_baixo->num_rows == 0): ?>
            <p style="text-align:center; color:#64748b; padding:20px;">
                <i class="fas fa-circle-check" style="color:#10b981; font-size:30px;"></i><br>
                Todos os produtos com estoque OK
            </p>
        <?php else: ?>
            <?php while ($p = $produtos_baixo->fetch_assoc()): ?>
                <div style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #f1f5f9;">
                    <div>
                        <strong><?= escapar($p['nome']) ?></strong>
                        <span class="badge badge-info" style="font-size:10px;"><?= escapar($p['filial_nome'] ?? '') ?></span><br>
                        <small style="color:#64748b;">Mínimo: <?= $p['estoque_minimo'] ?></small>
                    </div>
                    <span class="badge badge-danger"><?= $p['quantidade'] ?> un.</span>
                </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Últimos Pedidos -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-list"></i> Últimos Pedidos</h3>
        <a href="vendas.php" class="btn btn-primary btn-sm">Ver Todos</a>
    </div>
    <div style="overflow-x:auto;">
    <table class="table">
        <thead>
            <tr>
                <th>Nº</th>
                <th>Cliente</th>
                <th>Total</th>
                <th>Pagamento</th>
                <th>Status</th>
                <th>Data</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($ultimos_pedidos->num_rows == 0): ?>
                <tr><td colspan="6" style="text-align:center; padding:20px; color:#64748b;">Nenhum pedido registrado ainda.</td></tr>
            <?php else: ?>
                <?php while ($p = $ultimos_pedidos->fetch_assoc()): ?>
                    <tr>
                        <td><strong>#<?= $p['numero'] ?? $p['id'] ?></strong></td>
                        <td><?= $p['cliente_nome'] ?></td>
                        <td><strong><?= formatarMoeda($p['total']) ?></strong></td>
                        <td><?= ucfirst($p['forma_pagamento']) ?></td>
                        <td><?= statusBadge($p['status']) ?></td>
                        <td><?= formatarData($p['criado_em'], true) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
</div>

<!-- Chart.js para o gráfico -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const vendasData = <?= json_encode($vendas_grafico) ?>;
new Chart(document.getElementById('graficoVendas'), {
    type: 'line',
    data: {
        labels: vendasData.map(d => d.data),
        datasets: [{
            label: 'Vendas (R$)',
            data: vendasData.map(d => d.total),
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, 0.1)',
            fill: true,
            tension: 0.4,
            borderWidth: 3,
            pointRadius: 5,
            pointBackgroundColor: '#2563eb'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: v => 'R$ ' + v.toLocaleString('pt-BR')
                }
            }
        }
    }
});
</script>

<style>
@media(max-width:900px){
    .stats-grid{grid-template-columns:1fr 1fr;}
    [style*="grid-template-columns: 2fr 1fr"]{grid-template-columns:1fr !important;}
}
</style>

<?php require_once '../includes/footer.php'; ?>
