<?php
/**
 * API DE LOGIN
 * Valida credenciais e cria sessão
 */
require_once '../config/config.php';
require_once '../includes/funcoes.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$email = limpar($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';

if (empty($email) || empty($senha)) {
    header('Location: ../index.php?erro=invalido');
    exit;
}

// Busca usuário (Prepared Statement contra SQL Injection)
$stmt = $conn->prepare("SELECT id, nome, email, senha, perfil, empresa_id, filial_id, status FROM usuarios WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$usuario || !password_verify($senha, $usuario['senha'])) {
    header('Location: ../index.php?erro=invalido');
    exit;
}

if ($usuario['status'] != 'ativo') {
    header('Location: ../index.php?erro=inativo');
    exit;
}

// Cria sessão
$_SESSION['usuario_id'] = $usuario['id'];
$_SESSION['usuario_nome'] = $usuario['nome'];
$_SESSION['usuario_email'] = $usuario['email'];
$_SESSION['perfil'] = $usuario['perfil'];
$_SESSION['empresa_id'] = $usuario['empresa_id'];
$_SESSION['filial_id'] = $usuario['filial_id'];

// Carregar todas as filiais da empresa do usuário
$_SESSION['filiais_ativas'] = [];
if ($usuario['empresa_id']) {
    $stmtF = $conn->prepare("SELECT id FROM filiais WHERE empresa_id = ? AND status = 'ativo' ORDER BY nome");
    $stmtF->bind_param("i", $usuario['empresa_id']);
    $stmtF->execute();
    $resF = $stmtF->get_result();
    while ($fRow = $resF->fetch_assoc()) {
        $_SESSION['filiais_ativas'][] = $fRow['id'];
    }
    $stmtF->close();
}

// Atualiza último acesso
$stmt = $conn->prepare("UPDATE usuarios SET ultimo_acesso = NOW() WHERE id = ?");
$stmt->bind_param("i", $usuario['id']);
$stmt->execute();
$stmt->close();

// Registra log
registrarLog($conn, 'login', 'Usuário fez login no sistema');

// Redireciona: se admin e sem empresa fixa, vai para seleção
if ($usuario['perfil'] === 'admin' && empty($usuario['empresa_id'])) {
    header('Location: ../pages/selecionar_empresa.php');
} else {
    header('Location: ../pages/dashboard.php');
}
exit;
