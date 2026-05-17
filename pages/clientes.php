<?php
/**
 * GESTÃO DE CLIENTES
 */
$titulo_pagina = "Clientes";
$menu_ativo = "clientes";
require_once '../includes/header.php';

$msg = '';
$empresa_id = empresaAtiva();
if (!$empresa_id) { echo "<div class='alert alert-warning'>Selecione uma empresa.</div>"; require_once '../includes/footer.php'; exit; }

// Filiais disponíveis para o formulário
$filiais_lista = [];
$res_fil = $conn->prepare("SELECT id, nome FROM filiais WHERE empresa_id = ? AND status = 'ativo' ORDER BY nome");
$res_fil->bind_param("i", $empresa_id);
$res_fil->execute();
$ff = $res_fil->get_result();
while ($fl = $ff->fetch_assoc()) { $filiais_lista[] = $fl; }
$res_fil->close();

// SALVAR
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = (int)($_POST['id'] ?? 0);
    $filial_id   = !empty($_POST['filial_id']) ? (int)$_POST['filial_id'] : null;
    $nome        = limpar($_POST['nome']);
    $cpf_cnpj    = limpar($_POST['cpf_cnpj']);
    $telefone    = limpar($_POST['telefone']);
    $whatsapp    = limpar($_POST['whatsapp']);
    $email       = limpar($_POST['email']);
    $endereco    = limpar($_POST['endereco']);
    $bairro      = limpar($_POST['bairro']);
    $cidade      = limpar($_POST['cidade']);
    $cep         = limpar($_POST['cep']);
    $referencia  = limpar($_POST['referencia']);
    $observacoes = limpar($_POST['observacoes']);
    $status      = limpar($_POST['status'] ?? 'ativo');

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE clientes SET filial_id=?,nome=?,cpf_cnpj=?,telefone=?,whatsapp=?,email=?,endereco=?,bairro=?,cidade=?,cep=?,referencia=?,observacoes=?,status=? WHERE id=? AND empresa_id=?");
        $stmt->bind_param("issssssssssssii", $filial_id,$nome,$cpf_cnpj,$telefone,$whatsapp,$email,$endereco,$bairro,$cidade,$cep,$referencia,$observacoes,$status,$id,$empresa_id);
        $stmt->execute();
        $msg = '<div class="alert alert-success">✅ Cliente atualizado!</div>';
    } else {
        $stmt = $conn->prepare("INSERT INTO clientes (empresa_id,filial_id,nome,cpf_cnpj,telefone,whatsapp,email,endereco,bairro,cidade,cep,referencia,observacoes,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param("iissssssssssss", $empresa_id,$filial_id,$nome,$cpf_cnpj,$telefone,$whatsapp,$email,$endereco,$bairro,$cidade,$cep,$referencia,$observacoes,$status);
        $stmt->execute();
        $msg = '<div class="alert alert-success">✅ Cliente cadastrado!</div>';
    }
}

// EXCLUIR
if (isset($_GET['excluir'])) {
    $id = (int)$_GET['excluir'];
    $stmt = $conn->prepare("DELETE FROM clientes WHERE id = ? AND empresa_id = ?");
    $stmt->bind_param("ii", $id, $empresa_id);
    $stmt->execute();
    $msg = '<div class="alert alert-success">✅ Cliente excluído.</div>';
}

// EDITAR
$editar = null;
if (isset($_GET['editar'])) {
    $stmt = $conn->prepare("SELECT * FROM clientes WHERE id = ? AND empresa_id = ?");
    $id = (int)$_GET['editar'];
    $stmt->bind_param("ii", $id, $empresa_id);
    $stmt->execute();
    $editar = $stmt->get_result()->fetch_assoc();
}

// LISTAR
$stmt = $conn->prepare("SELECT c.*, f.nome AS filial_nome FROM clientes c LEFT JOIN filiais f ON f.id = c.filial_id WHERE c.empresa_id = ? ORDER BY c.nome");
$stmt->bind_param("i", $empresa_id);
$stmt->execute();
$clientes = $stmt->get_result();
?>

<?= $msg ?>

<div class="toolbar">
    <div class="toolbar-search">
        <i class="fas fa-search"></i>
        <input type="text" id="busca" placeholder="Buscar cliente..." class="form-control">
    </div>
    <button class="btn btn-primary" onclick="openModal('modalCliente')">
        <i class="fas fa-plus"></i> Novo Cliente
    </button>
</div>

<div class="table-container">
<table class="table" id="tabelaClientes">
    <thead>
        <tr>
            <th>Nome</th>
            <th>CPF/CNPJ</th>
            <th>WhatsApp</th>
            <th>Endereço</th>
            <th>Filial</th>
            <th>Status</th>
            <th>Ações</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($c = $clientes->fetch_assoc()): ?>
            <tr>
                <td><strong><?= $c['nome'] ?></strong></td>
                <td><?= $c['cpf_cnpj'] ?></td>
                <td>
                    <?php if ($c['whatsapp']): ?>
                        <a href="https://wa.me/55<?= preg_replace('/\D/','',$c['whatsapp']) ?>" target="_blank" style="color:#10b981;">
                            <i class="fab fa-whatsapp"></i> <?= $c['whatsapp'] ?>
                        </a>
                    <?php endif; ?>
                </td>
                <td><?= $c['endereco'] ?><?= $c['bairro'] ? ' - ' . $c['bairro'] : '' ?></td>
                <td><?= $c['filial_nome'] ? '<span class="badge badge-info">' . escapar($c['filial_nome']) . '</span>' : '<span style="color:var(--gray-400)">—</span>' ?></td>
                <td><?= statusBadge($c['status']) ?></td>
                <td class="actions">
                    <a href="?editar=<?= $c['id'] ?>" class="btn-icon edit"><i class="fas fa-pen"></i></a>
                    <button onclick="confirmarExclusao('?excluir=<?= $c['id'] ?>', 'este cliente')" class="btn-icon delete"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
</div>

<div class="modal-overlay <?= $editar ? 'active' : '' ?>" id="modalCliente">
    <div class="modal">
        <div class="modal-header">
            <h3><?= $editar ? 'Editar Cliente' : 'Novo Cliente' ?></h3>
            <button class="modal-close" onclick="closeModal('modalCliente')">&times;</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="id" value="<?= $editar['id'] ?? '' ?>">
                <div class="form-row">
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Nome / Razão Social *</label>
                        <input type="text" name="nome" required value="<?= $editar['nome'] ?? '' ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Filial</label>
                        <select name="filial_id">
                            <option value="">-- Sem filial definida --</option>
                            <?php foreach ($filiais_lista as $fl): ?>
                                <option value="<?= $fl['id'] ?>" <?= ($editar['filial_id'] ?? '') == $fl['id'] ? 'selected' : '' ?>>
                                    <?= escapar($fl['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>CPF / CNPJ</label>
                        <input type="text" name="cpf_cnpj" value="<?= $editar['cpf_cnpj'] ?? '' ?>" oninput="mascaraCpfCnpj(this)">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text" name="telefone" value="<?= $editar['telefone'] ?? '' ?>" oninput="mascaraTelefone(this)">
                    </div>
                    <div class="form-group">
                        <label>WhatsApp</label>
                        <input type="text" name="whatsapp" value="<?= $editar['whatsapp'] ?? '' ?>" oninput="mascaraTelefone(this)">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= $editar['email'] ?? '' ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Endereço</label>
                    <input type="text" name="endereco" value="<?= $editar['endereco'] ?? '' ?>">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Bairro</label>
                        <input type="text" name="bairro" value="<?= $editar['bairro'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Cidade</label>
                        <input type="text" name="cidade" value="<?= $editar['cidade'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>CEP</label>
                        <input type="text" name="cep" value="<?= $editar['cep'] ?? '' ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Ponto de Referência</label>
                    <input type="text" name="referencia" value="<?= $editar['referencia'] ?? '' ?>">
                </div>
                <div class="form-group">
                    <label>Observações</label>
                    <textarea name="observacoes" rows="2"><?= $editar['observacoes'] ?? '' ?></textarea>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="ativo" <?= ($editar['status'] ?? '')=='ativo'?'selected':'' ?>>Ativo</option>
                        <option value="inativo" <?= ($editar['status'] ?? '')=='inativo'?'selected':'' ?>>Inativo</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <a href="clientes.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>pesquisarTabela('busca', 'tabelaClientes');</script>
<?php require_once '../includes/footer.php'; ?>
