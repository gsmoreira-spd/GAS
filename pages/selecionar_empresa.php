<?php
/**
 * SELEÇÃO DE EMPRESA
 * Após o login, usuário escolhe qual empresa acessar.
 * Ao selecionar, TODAS as filiais ativas são carregadas na sessão.
 */
require_once '../config/config.php';
require_once '../includes/funcoes.php';
verificarLogin();

// Se selecionou uma empresa via GET
if (isset($_GET['empresa'])) {
    $empresa_id = (int)$_GET['empresa'];
    
    $stmt = $conn->prepare("SELECT id FROM empresas WHERE id = ? AND status = 'ativo'");
    $stmt->bind_param("i", $empresa_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $_SESSION['empresa_id'] = $empresa_id;
        
        // Carregar TODAS as filiais ativas da empresa
        $stmt2 = $conn->prepare("SELECT id FROM filiais WHERE empresa_id = ? AND status = 'ativo' ORDER BY nome");
        $stmt2->bind_param("i", $empresa_id);
        $stmt2->execute();
        $filiais_result = $stmt2->get_result();
        
        $filiais_ids = [];
        while ($f = $filiais_result->fetch_assoc()) {
            $filiais_ids[] = $f['id'];
        }
        
        $_SESSION['filiais_ativas'] = $filiais_ids;
        $_SESSION['filial_id'] = $filiais_ids[0] ?? null; // compatibilidade
        
        header('Location: dashboard.php');
        exit;
    }
}

// Lista empresas
if ($_SESSION['perfil'] === 'admin') {
    $empresas = $conn->query("SELECT * FROM empresas WHERE status = 'ativo' ORDER BY nome");
} else {
    $stmt = $conn->prepare("SELECT * FROM empresas WHERE id = ? AND status = 'ativo'");
    $stmt->bind_param("i", $_SESSION['empresa_id']);
    $stmt->execute();
    $empresas = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Selecionar Empresa - <?= SISTEMA_NOME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body style="background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%); min-height:100vh; padding:40px 20px;">

<div style="max-width: 1000px; margin: 0 auto;">
    <div style="text-align:center; margin-bottom:30px; color:#fff;">
        <h1 style="font-size:28px; margin-bottom:8px;">
            <i class="fas fa-building"></i> Selecione uma Empresa
        </h1>
        <p style="opacity:0.9;">Escolha a empresa que deseja acessar</p>
    </div>

    <div class="empresa-grid">
        <?php if ($empresas->num_rows == 0): ?>
            <div style="background:#fff; padding:30px; border-radius:10px; text-align:center; grid-column: 1/-1;">
                <i class="fas fa-circle-info" style="font-size:40px; color:#94a3b8;"></i>
                <h3 style="margin-top:15px;">Nenhuma empresa cadastrada</h3>
            </div>
        <?php endif; ?>

        <?php while ($emp = $empresas->fetch_assoc()):
            // Contar filiais
            $stmtF = $conn->prepare("SELECT COUNT(*) as qtd FROM filiais WHERE empresa_id = ? AND status = 'ativo'");
            $stmtF->bind_param("i", $emp['id']);
            $stmtF->execute();
            $qtdFiliais = $stmtF->get_result()->fetch_assoc()['qtd'];
        ?>
            <a href="?empresa=<?= $emp['id'] ?>" class="empresa-card">
                <div class="logo-emp"><i class="fas fa-building"></i></div>
                <h3><?= escapar($emp['nome']) ?></h3>
                <p><?= escapar($emp['cnpj']) ?></p>
                <p><?= escapar($emp['telefone']) ?></p>
                <p><small><i class="fas fa-store"></i> <?= $qtdFiliais ?> filial(is)</small></p>
            </a>
        <?php endwhile; ?>
    </div>

    <div style="text-align:center; margin-top:30px;">
        <a href="<?= BASE_URL ?>api/logout.php" style="color:#fff; opacity:0.8;">
            <i class="fas fa-right-from-bracket"></i> Sair
        </a>
    </div>
</div>

</body>
</html>
