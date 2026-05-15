<?php
/**
 * GESTÃO DE FILIAIS
 */
$titulo_pagina = "Filiais";
$menu_ativo = "filiais";
require_once '../includes/header.php';
verificarPerfil(['admin','gerente']);

$msg = '';
$empresa_id = empresaAtiva();

// SALVAR
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = (int)($_POST['id'] ?? 0);
    $emp_id      = (int)$_POST['empresa_id'];
    $nome        = limpar($_POST['nome']);
    $endereco    = limpar($_POST['endereco']);
    $telefone    = limpar($_POST['telefone']);
    $responsavel = limpar($_POST['responsavel']);
    $status      = limpar($_POST['status'] ?? 'ativo');

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE filiais SET empresa_id=?, nome=?, endereco=?, telefone=?, responsavel=?, status=? WHERE id=?");
        $stmt->bind_param("isssssi", $emp_id, $nome, $endereco, $telefone, $responsavel, $status, $id);
        $stmt->execute();
        $msg = '<div class="alert alert-success">✅ Filial atualizada!</div>';
    } else {
        $stmt = $conn->prepare("INSERT INTO filiais (empresa_id, nome, endereco, telefone, responsavel, status) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("isssss", $emp_id, $nome, $endereco, $telefone, $responsavel, $status);
        $stmt->execute();
        $msg = '<div class="alert alert-success">✅ Filial cadastrada!</div>';
    }
}

// EXCLUIR
if (isset($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];
    $stmt = $conn->prepare("DELETE FROM filiais WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $msg = '<div class="alert alert-success">✅ Filial excluída.</div>';
}

// EDITAR
$editar = null;
if (isset($_GET['editar'])) {
    $id = (int)$_GET['editar'];
    $stmt = $conn->prepare("SELECT * FROM filiais WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editar = $stmt->get_result()->fetch_assoc();
}

// LISTAR
$filiais = $conn->query("SELECT f.*, e.nome as empresa_nome FROM filiais f JOIN empresas e ON e.id = f.empresa_id ORDER BY e.nome, f.nome");
$empresas = $conn->query("SELECT id, nome FROM empresas WHERE status='ativo' ORDER BY nome");
?>

<?= $msg ?>

<div class="toolbar">
    <div class="toolbar-search">
        <i class="fas fa-search"></i>
        <input type="text" id="busca" placeholder="Buscar filial..." class="form-control">
    </div>
    <button class="btn btn-primary" onclick="openModal('modalFilial')">
        <i class="fas fa-plus"></i> Nova Filial
    </button>
</div>

<div class="table-container">
<table class="table" id="tabelaFiliais">
    <thead>
        <tr>
            <th>ID</th>
            <th>Empresa</th>
            <th>Nome da Filial</th>
            <th>Responsável</th>
            <th>Telefone</th>
            <th>Status</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($f = $filiais->fetch_assoc()): ?>
            <tr>
                <td>#<?= $f['id'] ?></td>
                <td><?= $f['empresa_nome'] ?></td>
                <td><strong><?= $f['nome'] ?></strong></td>
                <td><?= $f['responsavel'] ?></td>
                <td><?= $f['telefone'] ?></td>
                <td><?= statusBadge($f['status']) ?></td>
                <td class="actions">
                    <a href="?editar=<?= $f['id'] ?>" class="btn-icon edit"><i class="fas fa-pen"></i></a>
                    <button onclick="confirmarExclusao('?excluir=<?= $f['id'] ?>', 'esta filial')" class="btn-icon delete"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
</div>

<div class="modal-overlay <?= $editar ? 'active' : '' ?>" id="modalFilial">
    <div class="modal">
        <div class="modal-header">
            <h3><?= $editar ? 'Editar Filial' : 'Nova Filial' ?></h3>
            <button class="modal-close" onclick="closeModal('modalFilial')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="id" value="<?= $editar['id'] ?? '' ?>">
                <div class="form-group">
                    <label>Empresa *</label>
                    <select name="empresa_id" required>
                        <option value="">-- Selecione --</option>
                        <?php while ($e = $empresas->fetch_assoc()): ?>
                            <option value="<?= $e['id'] ?>" <?= ($editar['empresa_id'] ?? $empresa_id) == $e['id'] ? 'selected' : '' ?>>
                                <?= $e['nome'] ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nome da Filial *</label>
                    <input type="text" name="nome" required value="<?= $editar['nome'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label>Endereço</label>
                    <input type="text" name="endereco" value="<?= $editar['endereco'] ?? '' ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Responsável</label>
                        <input type="text" name="responsavel" value="<?= $editar['responsavel'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text" name="telefone" value="<?= $editar['telefone'] ?? '' ?>" oninput="mascaraTelefone(this)">
                    </div>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="ativo" <?= ($editar['status'] ?? '') == 'ativo' ? 'selected' : '' ?>>Ativo</option>
                        <option value="inativo" <?= ($editar['status'] ?? '') == 'inativo' ? 'selected' : '' ?>>Inativo</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <a href="filiais.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>pesquisarTabela('busca', 'tabelaFiliais');</script>

<?php require_once '../includes/footer.php'; ?>
