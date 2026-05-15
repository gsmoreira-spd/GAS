<?php
/**
 * TELA DE LOGIN
 * Página inicial do sistema
 */
require_once 'config/config.php';

// Se já estiver logado, redireciona para seleção de empresa
if (isset($_SESSION['usuario_id'])) {
    header('Location: pages/selecionar_empresa.php');
    exit;
}

$erro = $_GET['erro'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= SISTEMA_NOME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="login-bg">

    <div class="login-box">
        <div class="login-logo">
            <div class="icon">
                <i class="fas fa-fire-flame-curved"></i>
            </div>
            <h2><?= SISTEMA_NOME ?></h2>
            <p>Faça login para acessar o sistema</p>
        </div>

        <?php if ($erro == 'invalido'): ?>
            <div class="alert alert-danger">
                <i class="fas fa-circle-exclamation"></i>
                Email ou senha incorretos!
            </div>
        <?php elseif ($erro == 'inativo'): ?>
            <div class="alert alert-warning">
                <i class="fas fa-triangle-exclamation"></i>
                Usuário inativo. Contate o administrador.
            </div>
        <?php elseif ($erro == 'sessao'): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                Sessão expirada. Faça login novamente.
            </div>
        <?php endif; ?>

        <form action="api/login.php" method="POST">
            <div class="form-group">
                <label><i class="fas fa-envelope"></i> Email</label>
                <input type="email" name="email" required autofocus placeholder="seu@email.com">
            </div>

            <div class="form-group">
                <label><i class="fas fa-lock"></i> Senha</label>
                <input type="password" name="senha" required placeholder="••••••••">
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-right-to-bracket"></i> Entrar
            </button>
        </form>

        <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e2e8f0; text-align: center; font-size: 12px; color: #64748b;">
            <p><strong>Credenciais Padrão:</strong></p>
            <p>📧 admin@sistema.com | 🔑 admin123</p>
        </div>
    </div>

    <script src="assets/js/script.js"></script>
</body>
</html>
