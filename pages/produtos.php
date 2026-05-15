<?php
/**
 * GESTÃO DE PRODUTOS
 * CRUD de produtos com seleção de filial para estoque inicial
 */
$titulo_pagina = "Produtos";
$menu_ativo = "produtos";
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
$ids_str = implode(',', array_map('intval', $filiais_ativas_ids));

// Buscar filiais para o select
$filiais_disponiveis = [];
$res_fil = $conn->query("SELECT id, nome FROM filiais WHERE empresa_id = {$empresa_id} AND status = 'ativo' ORDER BY nome");
while ($ff = $res_fil->fetch_assoc()) {
    $filiais_disponiveis[] = $ff;
}

// Vasilhames da(s) filial(is) ativa(s) para vincular
$vasilhames = $conn->query("
    SELECT id, nome FROM produtos
    WHERE empresa_id = {$empresa_id}
      AND filial_id IN ({$ids_str})
      AND tipo IN ('vasilhame_gas','vasilhame_agua')
      AND status = 'ativo'
    ORDER BY nome
");

// === SALVAR ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id             = (int)($_POST['id'] ?? 0);
    $filial_id_prod = (int)($_POST['filial_id_prod'] ?? 0);
    $codigo         = limpar($_POST['codigo']);
    $nome           = limpar($_POST['nome']);
    $tipo           = limpar($_POST['tipo']);
    $vasilhame_id   = !empty($_POST['vasilhame_id']) ? (int)$_POST['vasilhame_id'] : null;
    if (!in_array($tipo, ['gas_reposicao', 'gas_completo', 'agua_reposicao', 'agua_completa'])) {
        $vasilhame_id = null;
    }
    $preco_compra   = (float)($_POST['preco_compra'] ?? 0);
    $preco_venda    = (float)($_POST['preco_venda'] ?? 0);
    $unidade        = limpar($_POST['unidade'] ?? 'UN');
    $estoque_minimo = (int)($_POST['estoque_minimo'] ?? 5);
    $descricao      = limpar($_POST['descricao'] ?? '');
    $status         = limpar($_POST['status'] ?? 'ativo');
    $qtd_inicial    = (int)($_POST['qtd_inicial'] ?? 0);

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE produtos SET filial_id=?, codigo=?, nome=?, tipo=?, vasilhame_id=?, preco_compra=?, preco_venda=?, unidade=?, estoque_minimo=?, descricao=?, status=? WHERE id=? AND empresa_id=?");
        $stmt->bind_param("isssiddsissii", $filial_id_prod, $codigo, $nome, $tipo, $vasilhame_id, $preco_compra, $preco_venda, $unidade, $estoque_minimo, $descricao, $status, $id, $empresa_id);
        $stmt->execute();
        $stmt->close();
        $msg = '<div class="alert alert-success">✅ Produto atualizado!</div>';
    } else {
        $stmt = $conn->prepare("INSERT INTO produtos (empresa_id, filial_id, codigo, nome, tipo, vasilhame_id, preco_compra, preco_venda, unidade, estoque_minimo, descricao, status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("iisssiddsiss", $empresa_id, $filial_id_prod, $codigo, $nome, $tipo, $vasilhame_id, $preco_compra, $preco_venda, $unidade, $estoque_minimo, $descricao, $status);
        $stmt->execute();
        $novo_produto_id = $conn->insert_id;
        $stmt->close();

        if ($novo_produto_id > 0 && $filial_id_prod > 0 && $qtd_inicial > 0) {
            movimentarEstoque($conn, $novo_produto_id, $filial_id_prod, $qtd_inicial, 'entrada', 'Estoque inicial ao cadastrar produto');
        }

        $msg = '<div class="alert alert-success">✅ Produto cadastrado!</div>';
    }
}

// === EXCLUIR ===
if (isset($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];
    $stmt = $conn->prepare("DELETE FROM produtos WHERE id = ? AND empresa_id = ?");
    $stmt->bind_param("ii", $id, $empresa_id);
    $stmt->execute();
    $stmt->close();
    $msg = '<div class="alert alert-success">✅ Produto excluído.</div>';
}

// === EDITAR ===
$editar = null;
if (isset($_GET['editar'])) {
    $id = (int)$_GET['editar'];
    $stmt = $conn->prepare("SELECT * FROM produtos WHERE id = ? AND empresa_id = ?");
    $stmt->bind_param("ii", $id, $empresa_id);
    $stmt->execute();
    $editar = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// === LISTAR — somente produtos da(s) filial(is) ativa(s) ===
$sql_list = "
    SELECT p.*,
           f.nome as filial_nome,
           COALESCE(e.quantidade, 0) as estoque_atual
    FROM produtos p
    LEFT JOIN filiais f ON f.id = p.filial_id
    LEFT JOIN estoque e ON e.produto_id = p.id AND e.filial_id = p.filial_id
    WHERE p.empresa_id = {$empresa_id}
      AND p.filial_id IN ({$ids_str})
    ORDER BY f.nome, p.nome
";
$produtos = $conn->query($sql_list);
?>

<?= $msg ?>

<div class="toolbar">
    <div class="toolbar-search">
        <i class="fas fa-search"></i>
        <input type="text" id="busca" placeholder="Buscar produto..." class="form-control">
    </div>
    <button class="btn btn-primary" onclick="openModal('modalProduto')">
        <i class="fas fa-plus"></i> Novo Produto
    </button>
</div>

<div class="table-container">
<table class="table" id="tabelaProdutos">
    <thead>
        <tr>
            <th>Código</th>
            <th>Produto</th>
            <th>Tipo</th>
            <th>Preço Compra</th>
            <th>Preço Venda</th>
            <th>Estoque por Filial</th>
            <th>Status</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($produtos->num_rows == 0): ?>
            <tr><td colspan="8" style="text-align:center; padding:20px; color:#64748b;">Nenhum produto cadastrado.</td></tr>
        <?php endif; ?>
        <?php while ($p = $produtos->fetch_assoc()):
            $qtd = (int)$p['estoque_atual'];
            $min = (int)$p['estoque_minimo'];
            $cor = $qtd <= 0 ? 'danger' : ($qtd <= $min ? 'warning' : 'success');
        ?>
        <tr>
            <td><strong><?= escapar($p['codigo']) ?></strong></td>
            <td><?= escapar($p['nome']) ?></td>
            <td><?= tipoProdutoNome($p['tipo']) ?></td>
            <td><?= formatarMoeda($p['preco_compra']) ?></td>
            <td><strong><?= formatarMoeda($p['preco_venda']) ?></strong></td>
            <td><span class="badge badge-<?= $cor ?>"><?= $qtd ?> un.</span></td>
            <td><?= statusBadge($p['status']) ?></td>
            <td class="actions">
                <a href="?editar=<?= $p['id'] ?>" class="btn-icon edit"><i class="fas fa-pen"></i></a>
                <button onclick="confirmarExclusao('?excluir=<?= $p['id'] ?>', 'este produto')" class="btn-icon delete"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
</div>

<!-- MODAL PRODUTO -->
<div class="modal-overlay <?= $editar ? 'active' : '' ?>" id="modalProduto">
    <div class="modal" style="max-width: 700px;">
        <div class="modal-header">
            <h3><?= $editar ? 'Editar Produto' : 'Novo Produto' ?></h3>
            <button class="modal-close" onclick="closeModal('modalProduto')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="id" value="<?= $editar['id'] ?? '' ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label>Filial *</label>
                        <select name="filial_id_prod" required>
                            <option value="">-- Selecione --</option>
                            <?php foreach ($filiais_disponiveis as $fl): ?>
                            <option value="<?= $fl['id'] ?>"
                                <?= ($editar['filial_id'] ?? $filiais_ativas_ids[0] ?? '') == $fl['id'] ? 'selected' : '' ?>>
                                <?= escapar($fl['nome']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Código *</label>
                        <input type="text" name="codigo" required value="<?= $editar['codigo'] ?? '' ?>" placeholder="Ex: GAS-REP13">
                    </div>
                    <div class="form-group">
                        <label>Nome do Produto *</label>
                        <input type="text" name="nome" required value="<?= $editar['nome'] ?? '' ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Tipo *</label>
                        <select name="tipo" required>
                            <option value="">-- Selecione --</option>
                            <?php
                            $tipos = ['vasilhame_gas'=>'Vasilhame Gás','vasilhame_agua'=>'Vasilhame Água','gas_reposicao'=>'Gás Reposição','gas_completo'=>'Gás Completo','agua_reposicao'=>'Água Reposição','agua_completa'=>'Água Completa','outro'=>'Outro'];
                            foreach ($tipos as $k => $v):
                            ?>
                                <option value="<?= $k ?>" <?= ($editar['tipo'] ?? '') == $k ? 'selected' : '' ?>><?= $v ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="grupoVasilhame">
                        <label>Vasilhame Vinculado</label>
                        <select name="vasilhame_id">
                            <option value="">-- Nenhum --</option>
                            <?php
                            $vasilhames->data_seek(0);
                            while ($vas = $vasilhames->fetch_assoc()):
                            ?>
                                <option value="<?= $vas['id'] ?>" <?= ($editar['vasilhame_id'] ?? '') == $vas['id'] ? 'selected' : '' ?>>
                                    <?= escapar($vas['nome']) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Preço de Compra (R$)</label>
                        <input type="number" name="preco_compra" step="0.01" min="0" value="<?= $editar['preco_compra'] ?? '0' ?>">
                    </div>
                    <div class="form-group">
                        <label>Preço de Venda (R$) *</label>
                        <input type="number" name="preco_venda" step="0.01" min="0" required value="<?= $editar['preco_venda'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Unidade</label>
                        <input type="text" name="unidade" value="<?= $editar['unidade'] ?? 'UN' ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Estoque Mínimo</label>
                        <input type="number" name="estoque_minimo" min="0" value="<?= $editar['estoque_minimo'] ?? '5' ?>">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="ativo" <?= ($editar['status'] ?? '') == 'ativo' ? 'selected' : '' ?>>Ativo</option>
                            <option value="inativo" <?= ($editar['status'] ?? '') == 'inativo' ? 'selected' : '' ?>>Inativo</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Descrição</label>
                    <textarea name="descricao" rows="2"><?= $editar['descricao'] ?? '' ?></textarea>
                </div>

                <!-- ESTOQUE INICIAL (só para novo produto) -->
                <?php if (!$editar): ?>
                <div style="margin-top:15px; padding-top:15px; border-top:1px solid var(--gray-200);">
                    <h4 style="font-size:14px; margin-bottom:8px; color:var(--gray-700);">
                        <i class="fas fa-warehouse"></i> Estoque Inicial
                    </h4>
                    <div class="form-group">
                        <label>Quantidade Inicial (na filial selecionada)</label>
                        <input type="number" name="qtd_inicial" min="0" value="0" placeholder="0">
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <a href="produtos.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
pesquisarTabela('busca', 'tabelaProdutos');

(function() {
    const tipoSelect = document.querySelector('[name="tipo"]');
    const grupoVasilhame = document.getElementById('grupoVasilhame');
    const selectVasilhame = grupoVasilhame.querySelector('select');

    const tiposComVasilhame = ['gas_reposicao', 'gas_completo', 'agua_reposicao', 'agua_completa'];

    function toggleVasilhame() {
        if (tiposComVasilhame.includes(tipoSelect.value)) {
            grupoVasilhame.style.display = '';
            selectVasilhame.required = true;
        } else {
            grupoVasilhame.style.display = 'none';
            selectVasilhame.value = '';
            selectVasilhame.required = false;
        }
    }

    tipoSelect.addEventListener('change', toggleVasilhame);
    toggleVasilhame();
})();
</script>
<?php require_once '../includes/footer.php'; ?>
