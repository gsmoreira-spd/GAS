<?php
/**
 * CONTROLE DE ESTOQUE
 * Visualização e movimentação de estoque por filial
 */
$titulo_pagina = "Estoque";
$menu_ativo = "estoque";
require_once '../includes/header.php';

$msg = '';
$empresa_id = empresaAtiva();
if (!$empresa_id) {
    echo "<div class='alert alert-warning'>Selecione uma empresa.</div>";
    require_once '../includes/footer.php';
    exit;
}

// Filiais ativas
$filiais_ativas_ids = filiaisAtivas();
if (empty($filiais_ativas_ids)) {
    echo "<div class='alert alert-warning'>Nenhuma filial ativa.</div>";
    require_once '../includes/footer.php';
    exit;
}

// Filial para movimentação (selecionada pelo dropdown ou primeira ativa)
$filial_mov = (int)($_POST['filial_id'] ?? $_GET['filial'] ?? $filiais_ativas_ids[0]);

// === MOVIMENTAÇÃO MANUAL ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['movimentar'])) {
    $produto_id = (int)$_POST['produto_id'];
    $filial_id  = (int)$_POST['filial_id'];
    $tipo       = limpar($_POST['tipo_mov']);
    $quantidade = (int)$_POST['quantidade_mov'];
    $motivo     = limpar($_POST['motivo'] ?? '');

    if ($produto_id > 0 && $quantidade > 0 && in_array($tipo, ['entrada', 'saida'])) {
        movimentarEstoque($conn, $produto_id, $filial_id, $quantidade, $tipo, $motivo);
        registrarLog($conn, 'movimentar_estoque', "Produto #{$produto_id} - {$tipo} de {$quantidade} un. na filial #{$filial_id}");
        $msg = '<div class="alert alert-success">✅ Estoque atualizado com sucesso!</div>';
    } else {
        $msg = '<div class="alert alert-danger">Preencha todos os campos corretamente.</div>';
    }
}

// Buscar filiais para exibição
$filiais_list = [];
$ids_str = implode(',', array_map('intval', $filiais_ativas_ids));
$res_filiais = $conn->query("SELECT id, nome FROM filiais WHERE id IN ({$ids_str}) ORDER BY nome");
while ($fl = $res_filiais->fetch_assoc()) {
    $filiais_list[] = $fl;
}

// Buscar estoque agrupado por filial ativa
$sql_estoque = "
    SELECT p.id as produto_id, p.codigo, p.nome, p.tipo, p.estoque_minimo,
           COALESCE(e.quantidade, 0) as quantidade, p.filial_id, fil.nome as filial_nome
    FROM produtos p
    JOIN filiais fil ON fil.id = p.filial_id
    LEFT JOIN estoque e ON e.produto_id = p.id AND e.filial_id = p.filial_id
    WHERE p.empresa_id = {$empresa_id} AND p.status = 'ativo'
      AND p.filial_id IN ({$ids_str})
    ORDER BY fil.nome, p.nome
";
$estoque_result = $conn->query($sql_estoque);

// Agrupar por filial
$estoque_por_filial = [];
while ($row = $estoque_result->fetch_assoc()) {
    $fid = $row['filial_id'] ?? 0;
    if ($fid > 0) {
        $estoque_por_filial[$fid]['nome'] = $row['filial_nome'];
        $estoque_por_filial[$fid]['itens'][] = $row;
    }
}

// Buscar produtos para o modal de movimentação — apenas da(s) filial(is) ativa(s)
$produtos_mov = $conn->query("
    SELECT id, codigo, nome FROM produtos
    WHERE empresa_id = {$empresa_id} AND status = 'ativo'
      AND filial_id IN ({$ids_str})
    ORDER BY nome
");

// Histórico de movimentações recentes
$sql_hist = "
    SELECT me.*, p.nome as produto_nome, fil.nome as filial_nome, u.nome as usuario_nome
    FROM movimentacao_estoque me
    JOIN produtos p ON p.id = me.produto_id
    JOIN filiais fil ON fil.id = me.filial_id
    LEFT JOIN usuarios u ON u.id = me.usuario_id
    WHERE me.filial_id IN ({$ids_str})
    ORDER BY me.criado_em DESC
    LIMIT 50
";
$historico = $conn->query($sql_hist);
?>

<?= $msg ?>

<div class="toolbar">
    <div class="toolbar-search">
        <i class="fas fa-search"></i>
        <input type="text" id="busca" placeholder="Buscar produto..." class="form-control">
    </div>
    <button class="btn btn-primary" onclick="openModal('modalMovimentar')">
        <i class="fas fa-arrows-up-down"></i> Movimentar Estoque
    </button>
</div>

<!-- ESTOQUE POR FILIAL -->
<?php if (empty($estoque_por_filial)): ?>
    <div class="alert alert-info">Nenhum registro de estoque encontrado nas filiais ativas.</div>
<?php endif; ?>

<?php foreach ($estoque_por_filial as $fid => $dados): ?>
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-store"></i> <?= escapar($dados['nome']) ?></h3>
        <span class="badge badge-info"><?= count($dados['itens']) ?> produtos</span>
    </div>
    <div class="table-container" style="box-shadow:none;">
        <table class="table tabela-estoque">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Produto</th>
                    <th>Tipo</th>
                    <th>Quantidade</th>
                    <th>Mínimo</th>
                    <th>Situação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dados['itens'] as $item): ?>
                <tr>
                    <td><strong><?= escapar($item['codigo']) ?></strong></td>
                    <td><?= escapar($item['nome']) ?></td>
                    <td><?= tipoProdutoNome($item['tipo']) ?></td>
                    <td>
                        <strong style="font-size:16px;"><?= (int)$item['quantidade'] ?></strong>
                    </td>
                    <td><?= (int)$item['estoque_minimo'] ?></td>
                    <td>
                        <?php
                        $qtd = (int)$item['quantidade'];
                        $min = (int)$item['estoque_minimo'];
                        if ($qtd <= 0) {
                            echo '<span class="badge badge-danger">Sem Estoque</span>';
                        } elseif ($qtd <= $min) {
                            echo '<span class="badge badge-warning">Estoque Baixo</span>';
                        } else {
                            echo '<span class="badge badge-success">Normal</span>';
                        }
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<!-- HISTÓRICO DE MOVIMENTAÇÕES -->
<div class="card" style="margin-top: 25px;">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-clock-rotate-left"></i> Últimas Movimentações</h3>
    </div>
    <div class="table-container" style="box-shadow:none;">
        <table class="table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Filial</th>
                    <th>Produto</th>
                    <th>Tipo</th>
                    <th>Qtd</th>
                    <th>Motivo</th>
                    <th>Usuário</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($historico->num_rows == 0): ?>
                    <tr><td colspan="7" style="text-align:center; padding:20px; color:#64748b;">Nenhuma movimentação registrada.</td></tr>
                <?php endif; ?>
                <?php while ($h = $historico->fetch_assoc()): ?>
                <tr>
                    <td><?= formatarData($h['criado_em'], true) ?></td>
                    <td><span class="badge badge-info"><?= escapar($h['filial_nome']) ?></span></td>
                    <td><?= escapar($h['produto_nome']) ?></td>
                    <td>
                        <?php
                        $cores = ['entrada' => 'success', 'saida' => 'danger', 'venda' => 'warning', 'cancelamento' => 'info', 'transferencia' => 'primary'];
                        $cor = $cores[$h['tipo']] ?? 'secondary';
                        echo '<span class="badge badge-' . $cor . '">' . ucfirst($h['tipo']) . '</span>';
                        ?>
                    </td>
                    <td><strong><?= $h['quantidade'] ?></strong></td>
                    <td style="max-width:200px; font-size:12px;"><?= escapar($h['motivo']) ?></td>
                    <td><?= escapar($h['usuario_nome'] ?? '-') ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL MOVIMENTAR ESTOQUE -->
<div class="modal-overlay" id="modalMovimentar">
    <div class="modal">
        <div class="modal-header">
            <h3>Movimentar Estoque</h3>
            <button class="modal-close" onclick="closeModal('modalMovimentar')">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="movimentar" value="1">
            <div class="modal-body">
                <div class="form-group">
                    <label><i class="fas fa-store"></i> Filial *</label>
                    <select name="filial_id" required>
                        <?php foreach ($filiais_list as $fl): ?>
                            <option value="<?= $fl['id'] ?>" <?= $fl['id'] == $filial_mov ? 'selected' : '' ?>>
                                <?= escapar($fl['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-box"></i> Produto *</label>
                    <select name="produto_id" required>
                        <option value="">-- Selecione --</option>
                        <?php while ($pm = $produtos_mov->fetch_assoc()): ?>
                            <option value="<?= $pm['id'] ?>"><?= escapar($pm['codigo']) ?> - <?= escapar($pm['nome']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Tipo *</label>
                        <select name="tipo_mov" required>
                            <option value="entrada">📥 Entrada</option>
                            <option value="saida">📤 Saída</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Quantidade *</label>
                        <input type="number" name="quantidade_mov" min="1" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Motivo</label>
                    <input type="text" name="motivo" placeholder="Ex: Compra de fornecedor, Quebra, etc.">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modalMovimentar')">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Movimentar</button>
            </div>
        </form>
    </div>
</div>

<script>
// Pesquisa nas tabelas de estoque
document.getElementById('busca')?.addEventListener('keyup', function() {
    const filtro = this.value.toLowerCase();
    document.querySelectorAll('.tabela-estoque tbody tr').forEach(tr => {
        tr.style.display = tr.textContent.toLowerCase().includes(filtro) ? '' : 'none';
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
