<?php
/**
 * GESTÃO DE EMPRESAS
 * CRUD completo (apenas administradores)
 */
$titulo_pagina = "Empresas";
$menu_ativo = "empresas";
require_once '../includes/header.php';
verificarPerfil(['admin']);

$msg = '';

// === CRIAR / ATUALIZAR ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id        = (int)($_POST['id'] ?? 0);
    $nome      = limpar($_POST['nome']);
    $cnpj      = limpar($_POST['cnpj']);
    $endereco  = limpar($_POST['endereco']);
    $telefone  = limpar($_POST['telefone']);
    $email     = limpar($_POST['email']);
    $status    = limpar($_POST['status'] ?? 'ativo');

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE empresas SET nome=?, cnpj=?, endereco=?, telefone=?, email=?, status=? WHERE id=?");
        $stmt->bind_param("ssssssi", $nome, $cnpj, $endereco, $telefone, $email, $status, $id);
        if ($stmt->execute()) {
            $msg = '<div class="alert alert-success">✅ Empresa atualizada com sucesso!</div>';
            registrarLog($conn, 'editar_empresa', "Empresa $nome editada");
        } else {
            $msg = '<div class="alert alert-danger">❌ Erro: ' . $stmt->error . '</div>';
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO empresas (nome, cnpj, endereco, telefone, email, status) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param("ssssss", $nome, $cnpj, $endereco, $telefone, $email, $status);
        if ($stmt->execute()) {
            $msg = '<div class="alert alert-success">✅ Empresa cadastrada com sucesso!</div>';
            registrarLog($conn, 'criar_empresa', "Empresa $nome criada");
        } else {
            $msg = '<div class="alert alert-danger">❌ Erro: ' . $stmt->error . '</div>';
        }
    }
}

// === EXCLUIR ===
if (isset($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];
    $stmt = $conn->prepare("DELETE FROM empresas WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $msg = '<div class="alert alert-success">✅ Empresa excluída.</div>';
        registrarLog($conn, 'excluir_empresa', "Empresa ID $id excluída");
    }
}

// === BUSCAR PARA EDITAR ===
$editar = null;
if (isset($_GET['editar'])) {
    $id = (int)$_GET['editar'];
    $stmt = $conn->prepare("SELECT * FROM empresas WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editar = $stmt->get_result()->fetch_assoc();
}

// === LISTAR ===
$empresas = $conn->query("SELECT e.*, (SELECT COUNT(*) FROM filiais f WHERE f.empresa_id = e.id) as total_filiais FROM empresas e ORDER BY nome");
?>

<?= $msg ?>

<div class="toolbar">
    <div class="toolbar-search">
        <i class="fas fa-search"></i>
        <input type="text" id="busca" placeholder="Buscar empresa..." class="form-control">
    </div>
    <button class="btn btn-primary" onclick="openModal('modalEmpresa')">
        <i class="fas fa-plus"></i> Nova Empresa
    </button>
</div>

<div class="table-container">
<table class="table" id="tabelaEmpresas">
    <thead>
        <tr>
            <th>ID</th>
            <th>Nome</th>
            <th>CNPJ</th>
            <th>Telefone</th>
            <th>Email</th>
            <th>Filiais</th>
            <th>Status</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($e = $empresas->fetch_assoc()): ?>
            <tr>
                <td>#<?= $e['id'] ?></td>
                <td><strong><?= $e['nome'] ?></strong></td>
                <td><?= $e['cnpj'] ?></td>
                <td><?= $e['telefone'] ?></td>
                <td><?= $e['email'] ?></td>
                <td><span class="badge badge-info"><?= $e['total_filiais'] ?></span></td>
                <td><?= statusBadge($e['status']) ?></td>
                <td class="actions">
                    <a href="?editar=<?= $e['id'] ?>" class="btn-icon edit" title="Editar"><i class="fas fa-pen"></i></a>
                    <button onclick="confirmarExclusao('?excluir=<?= $e['id'] ?>', 'a empresa <?= addslashes($e['nome']) ?>')" class="btn-icon delete" title="Excluir"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
</div>

<!-- MODAL CADASTRO/EDIÇÃO -->
<div class="modal-overlay <?= $editar ? 'active' : '' ?>" id="modalEmpresa">
    <div class="modal">
        <div class="modal-header">
            <h3><?= $editar ? '<i class="fas fa-pen"></i> Editar Empresa' : '<i class="fas fa-plus"></i> Nova Empresa' ?></h3>
            <button class="modal-close" onclick="closeModal('modalEmpresa')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="id" value="<?= $editar['id'] ?? '' ?>">
                <div class="form-group">
                    <label>Nome da Empresa *</label>
                    <input type="text" name="nome" required value="<?= $editar['nome'] ?? '' ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>CNPJ *</label>
                        <input type="text" name="cnpj" required value="<?= $editar['cnpj'] ?? '' ?>" oninput="mascaraCpfCnpj(this)">
                    </div>
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text" name="telefone" value="<?= $editar['telefone'] ?? '' ?>" oninput="mascaraTelefone(this)">
                    </div>
                </div>
                <div class="form-group">
                    <label>Endereço</label>
                    <input type="text" name="endereco" value="<?= $editar['endereco'] ?? '' ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= $editar['email'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="ativo" <?= ($editar['status'] ?? '') == 'ativo' ? 'selected' : '' ?>>Ativo</option>
                            <option value="inativo" <?= ($editar['status'] ?? '') == 'inativo' ? 'selected' : '' ?>>Inativo</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="empresas.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
pesquisarTabela('busca', 'tabelaEmpresas');
</script>

<?php require_once '../includes/footer.php'; ?>
