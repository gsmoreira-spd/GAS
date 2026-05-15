<?php
/**
 * GESTÃO DE MOTOBOYS
 */
$titulo_pagina = "Motoboys";
$menu_ativo = "motoboys";
require_once '../includes/header.php';

$msg = '';
$empresa_id = empresaAtiva();
$filial_id = filialAtiva();
if (!$empresa_id) { echo "<div class='alert alert-warning'>Selecione uma empresa.</div>"; require_once '../includes/footer.php'; exit; }

// SALVAR
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['id'] ?? 0);
    $nome     = limpar($_POST['nome']);
    $telefone = limpar($_POST['telefone']);
    $cpf      = limpar($_POST['cpf']);
    $veiculo  = limpar($_POST['veiculo']);
    $placa    = limpar($_POST['placa']);
    $status   = limpar($_POST['status'] ?? 'disponivel');
    $fil_id   = (int)$_POST['filial_id'];

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE motoboys SET filial_id=?, nome=?, telefone=?, cpf=?, veiculo=?, placa=?, status=? WHERE id=? AND empresa_id=?");
        $stmt->bind_param("issssssii", $fil_id, $nome, $telefone, $cpf, $veiculo, $placa, $status, $id, $empresa_id);
        $stmt->execute();
        $msg = '<div class="alert alert-success">✅ Motoboy atualizado!</div>';
    } else {
        $stmt = $conn->prepare("INSERT INTO motoboys (empresa_id, filial_id, nome, telefone, cpf, veiculo, placa, status) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param("iissssss", $empresa_id, $fil_id, $nome, $telefone, $cpf, $veiculo, $placa, $status);
        $stmt->execute();
        $msg = '<div class="alert alert-success">✅ Motoboy cadastrado!</div>';
    }
}

if (isset($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];
    $stmt = $conn->prepare("DELETE FROM motoboys WHERE id=? AND empresa_id=?");
    $stmt->bind_param("ii", $id, $empresa_id);
    $stmt->execute();
    $msg = '<div class="alert alert-success">✅ Motoboy excluído.</div>';
}

$editar = null;
if (isset($_GET['editar'])) {
    $id = (int)$_GET['editar'];
    $stmt = $conn->prepare("SELECT * FROM motoboys WHERE id = ? AND empresa_id = ?");
    $stmt->bind_param("ii", $id, $empresa_id);
    $stmt->execute();
    $editar = $stmt->get_result()->fetch_assoc();
}

$stmt = $conn->prepare("
    SELECT m.*, f.nome as filial_nome,
    (SELECT COUNT(*) FROM pedidos WHERE motoboy_id = m.id AND status = 'entregue') as total_entregas
    FROM motoboys m
    LEFT JOIN filiais f ON f.id = m.filial_id
    WHERE m.empresa_id = ?
    ORDER BY m.nome
");
$stmt->bind_param("i", $empresa_id);
$stmt->execute();
$motoboys = $stmt->get_result();

$stmt = $conn->prepare("SELECT id, nome FROM filiais WHERE empresa_id = ? AND status='ativo'");
$stmt->bind_param("i", $empresa_id);
$stmt->execute();
$filiais = $stmt->get_result();
?>

<?= $msg ?>

<div class="toolbar">
    <div class="toolbar-search">
        <i class="fas fa-search"></i>
        <input type="text" id="busca" placeholder="Buscar motoboy..." class="form-control">
    </div>
    <button class="btn btn-primary" onclick="openModal('modalMotoboy')">
        <i class="fas fa-plus"></i> Novo Motoboy
    </button>
</div>

<div class="table-container">
<table class="table" id="tabelaMotoboys">
    <thead>
        <tr><th>Nome</th><th>Telefone</th><th>Veículo</th><th>Placa</th><th>Filial</th><th>Entregas</th><th>Status</th><th>Ações</th></tr>
    </thead>
    <tbody>
        <?php while ($m = $motoboys->fetch_assoc()): ?>
        <tr>
            <td><strong><?= $m['nome'] ?></strong></td>
            <td><?= $m['telefone'] ?></td>
            <td><?= $m['veiculo'] ?></td>
            <td><code><?= $m['placa'] ?></code></td>
            <td><?= $m['filial_nome'] ?? '-' ?></td>
            <td><span class="badge badge-info"><?= $m['total_entregas'] ?></span></td>
            <td><?= statusBadge($m['status']) ?></td>
            <td class="actions">
                <a href="?editar=<?= $m['id'] ?>" class="btn-icon edit"><i class="fas fa-pen"></i></a>
                <button onclick="confirmarExclusao('?excluir=<?= $m['id'] ?>', 'este motoboy')" class="btn-icon delete"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
</div>

<div class="modal-overlay <?= $editar ? 'active' : '' ?>" id="modalMotoboy">
    <div class="modal">
        <div class="modal-header">
            <h3><?= $editar ? 'Editar' : 'Novo' ?> Motoboy</h3>
            <button class="modal-close" onclick="closeModal('modalMotoboy')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="id" value="<?= $editar['id'] ?? '' ?>">
                <div class="form-group">
                    <label>Nome *</label>
                    <input type="text" name="nome" required value="<?= $editar['nome'] ?? '' ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text" name="telefone" value="<?= $editar['telefone'] ?? '' ?>" oninput="mascaraTelefone(this)">
                    </div>
                    <div class="form-group">
                        <label>CPF</label>
                        <input type="text" name="cpf" value="<?= $editar['cpf'] ?? '' ?>" oninput="mascaraCpfCnpj(this)">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Veículo</label>
                        <input type="text" name="veiculo" value="<?= $editar['veiculo'] ?? '' ?>" placeholder="Ex: Honda CG 160">
                    </div>
                    <div class="form-group">
                        <label>Placa</label>
                        <input type="text" name="placa" value="<?= $editar['placa'] ?? '' ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Filial</label>
                        <select name="filial_id">
                            <option value="">-- Selecione --</option>
                            <?php while ($f = $filiais->fetch_assoc()): ?>
                                <option value="<?= $f['id'] ?>" <?= ($editar['filial_id'] ?? $filial_id) == $f['id'] ? 'selected' : '' ?>><?= $f['nome'] ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="disponivel" <?= ($editar['status']??'')=='disponivel'?'selected':'' ?>>Disponível</option>
                            <option value="em_entrega" <?= ($editar['status']??'')=='em_entrega'?'selected':'' ?>>Em Entrega</option>
                            <option value="indisponivel" <?= ($editar['status']??'')=='indisponivel'?'selected':'' ?>>Indisponível</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <a href="motoboys.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>pesquisarTabela('busca','tabelaMotoboys');</script>
<?php require_once '../includes/footer.php'; ?>
