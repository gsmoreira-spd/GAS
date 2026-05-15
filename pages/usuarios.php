<?php
/**
 * GESTÃO DE USUÁRIOS DO SISTEMA
 */
$titulo_pagina = "Usuários";
$menu_ativo = "usuarios";
require_once '../includes/header.php';
verificarPerfil(['admin']);

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id         = (int)($_POST['id'] ?? 0);
    $nome       = limpar($_POST['nome']);
    $email      = limpar($_POST['email']);
    $perfil     = limpar($_POST['perfil']);
    $empresa_id = (int)$_POST['empresa_id'] ?: null;
    $filial_id  = (int)$_POST['filial_id'] ?: null;
    $telefone   = limpar($_POST['telefone']);
    $status     = limpar($_POST['status']);
    $senha      = $_POST['senha'] ?? '';

    if ($id > 0) {
        if (!empty($senha)) {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE usuarios SET nome=?,email=?,perfil=?,empresa_id=?,filial_id=?,telefone=?,status=?,senha=? WHERE id=?");
            $stmt->bind_param("sssiisssi", $nome,$email,$perfil,$empresa_id,$filial_id,$telefone,$status,$hash,$id);
        } else {
            $stmt = $conn->prepare("UPDATE usuarios SET nome=?,email=?,perfil=?,empresa_id=?,filial_id=?,telefone=?,status=? WHERE id=?");
            $stmt->bind_param("sssiissi", $nome,$email,$perfil,$empresa_id,$filial_id,$telefone,$status,$id);
        }
        $stmt->execute();
        $msg = '<div class="alert alert-success">✅ Usuário atualizado!</div>';
    } else {
        $hash = password_hash($senha ?: '123456', PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO usuarios (nome,email,senha,perfil,empresa_id,filial_id,telefone,status) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param("ssssiiss", $nome,$email,$hash,$perfil,$empresa_id,$filial_id,$telefone,$status);
        if ($stmt->execute()) {
            $msg = '<div class="alert alert-success">✅ Usuário criado!</div>';
        } else {
            $msg = '<div class="alert alert-danger">❌ ' . $stmt->error . '</div>';
        }
    }
}

if (isset($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];
    if ($id != $_SESSION['usuario_id']) {
        $stmt = $conn->prepare("DELETE FROM usuarios WHERE id=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $msg = '<div class="alert alert-success">✅ Usuário excluído.</div>';
    } else {
        $msg = '<div class="alert alert-danger">❌ Não é possível excluir o próprio usuário.</div>';
    }
}

$editar = null;
if (isset($_GET['editar'])) {
    $id = (int)$_GET['editar'];
    $stmt = $conn->prepare("SELECT * FROM usuarios WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $editar = $stmt->get_result()->fetch_assoc();
}

$usuarios = $conn->query("
    SELECT u.*, e.nome as empresa_nome, f.nome as filial_nome
    FROM usuarios u
    LEFT JOIN empresas e ON e.id = u.empresa_id
    LEFT JOIN filiais f ON f.id = u.filial_id
    ORDER BY u.nome
");
$empresas = $conn->query("SELECT id, nome FROM empresas WHERE status='ativo'");
$filiais  = $conn->query("SELECT id, nome FROM filiais WHERE status='ativo'");
?>

<?= $msg ?>

<div class="toolbar">
    <div class="toolbar-search">
        <i class="fas fa-search"></i>
        <input type="text" id="busca" placeholder="Buscar usuário..." class="form-control">
    </div>
    <button class="btn btn-primary" onclick="openModal('modalUsuario')">
        <i class="fas fa-plus"></i> Novo Usuário
    </button>
</div>

<div class="table-container">
<table class="table" id="tabelaUsuarios">
    <thead>
        <tr><th>Nome</th><th>Email</th><th>Perfil</th><th>Empresa</th><th>Último Acesso</th><th>Status</th><th>Ações</th></tr>
    </thead>
    <tbody>
        <?php while ($u = $usuarios->fetch_assoc()): ?>
        <tr>
            <td><strong><?= $u['nome'] ?></strong></td>
            <td><?= $u['email'] ?></td>
            <td><span class="badge badge-info"><?= ucfirst($u['perfil']) ?></span></td>
            <td><?= $u['empresa_nome'] ?? '-' ?></td>
            <td><?= $u['ultimo_acesso'] ? formatarData($u['ultimo_acesso'], true) : 'Nunca' ?></td>
            <td><?= statusBadge($u['status']) ?></td>
            <td class="actions">
                <a href="?editar=<?= $u['id'] ?>" class="btn-icon edit"><i class="fas fa-pen"></i></a>
                <?php if ($u['id'] != $_SESSION['usuario_id']): ?>
                <button onclick="confirmarExclusao('?excluir=<?= $u['id'] ?>', 'este usuário')" class="btn-icon delete"><i class="fas fa-trash"></i></button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
</div>

<div class="modal-overlay <?= $editar ? 'active' : '' ?>" id="modalUsuario">
    <div class="modal">
        <div class="modal-header">
            <h3><?= $editar ? 'Editar Usuário' : 'Novo Usuário' ?></h3>
            <button class="modal-close" onclick="closeModal('modalUsuario')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="id" value="<?= $editar['id'] ?? '' ?>">
                <div class="form-row">
                    <div class="form-group">
                        <label>Nome *</label>
                        <input type="text" name="nome" required value="<?= $editar['nome'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" required value="<?= $editar['email'] ?? '' ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Senha <?= $editar ? '(deixe vazio p/ não alterar)' : '*' ?></label>
                        <input type="password" name="senha" <?= $editar ? '' : 'required' ?>>
                    </div>
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text" name="telefone" value="<?= $editar['telefone'] ?? '' ?>" oninput="mascaraTelefone(this)">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Perfil *</label>
                        <select name="perfil" required>
                            <option value="admin" <?= ($editar['perfil']??'')=='admin'?'selected':'' ?>>Administrador</option>
                            <option value="gerente" <?= ($editar['perfil']??'')=='gerente'?'selected':'' ?>>Gerente</option>
                            <option value="vendedor" <?= ($editar['perfil']??'')=='vendedor'?'selected':'' ?>>Vendedor</option>
                            <option value="motoboy" <?= ($editar['perfil']??'')=='motoboy'?'selected':'' ?>>Motoboy</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="ativo" <?= ($editar['status']??'')=='ativo'?'selected':'' ?>>Ativo</option>
                            <option value="inativo" <?= ($editar['status']??'')=='inativo'?'selected':'' ?>>Inativo</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Empresa</label>
                        <select name="empresa_id">
                            <option value="">-- Todas (admin) --</option>
                            <?php while ($e = $empresas->fetch_assoc()): ?>
                            <option value="<?= $e['id'] ?>" <?= ($editar['empresa_id']??'')==$e['id']?'selected':'' ?>><?= $e['nome'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Filial</label>
                        <select name="filial_id">
                            <option value="">-- Nenhuma --</option>
                            <?php while ($f = $filiais->fetch_assoc()): ?>
                            <option value="<?= $f['id'] ?>" <?= ($editar['filial_id']??'')==$f['id']?'selected':'' ?>><?= $f['nome'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="usuarios.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>pesquisarTabela('busca','tabelaUsuarios');</script>
<?php require_once '../includes/footer.php'; ?>
