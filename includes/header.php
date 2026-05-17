<?php
/**
 * HEADER E SIDEBAR
 * Incluir em todas as páginas internas do sistema.
 * 
 * Variáveis esperadas:
 * $titulo_pagina - título da página atual
 * $menu_ativo    - nome do menu ativo (para destacar)
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/funcoes.php';

verificarLogin();

// =====================================================
// TOGGLE FILIAL via GET (adicionar/remover filial ativa)
// =====================================================
if (isset($_GET['toggle_filial'])) {
    $fid = (int)$_GET['toggle_filial'];
    $filiais_ativas = $_SESSION['filiais_ativas'] ?? [];

    if (in_array($fid, $filiais_ativas)) {
        // Só remove se não for a última
        if (count($filiais_ativas) > 1) {
            $filiais_ativas = array_values(array_diff($filiais_ativas, [$fid]));
        }
    } else {
        $filiais_ativas[] = $fid;
    }

    $_SESSION['filiais_ativas'] = $filiais_ativas;
    $_SESSION['filial_id'] = $filiais_ativas[0];

    $redirect = strtok($_SERVER['REQUEST_URI'], '?');
    $params = $_GET;
    unset($params['toggle_filial']);
    if (!empty($params)) {
        $redirect .= '?' . http_build_query($params);
    }
    header("Location: {$redirect}");
    exit;
}

// Busca info da empresa ativa
$empresa_atual = null;
if (!empty($_SESSION['empresa_id'])) {
    $stmt = $conn->prepare("SELECT nome FROM empresas WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['empresa_id']);
    $stmt->execute();
    $empresa_atual = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Busca TODAS as filiais da empresa (para exibir os chips)
$todas_filiais = [];
if (!empty($_SESSION['empresa_id'])) {
    $res = $conn->query("SELECT id, nome FROM filiais WHERE empresa_id = {$_SESSION['empresa_id']} AND status = 'ativo' ORDER BY nome");
    while ($f = $res->fetch_assoc()) {
        $todas_filiais[] = $f;
    }
}
$filiais_ativas = $_SESSION['filiais_ativas'] ?? [];

$titulo_pagina = $titulo_pagina ?? 'Dashboard';
$menu_ativo = $menu_ativo ?? '';
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $titulo_pagina ?> - <?= SISTEMA_NOME ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<div class="layout">

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo-icon"><i class="fas fa-fire-flame-curved"></i></div>
            <div>
                <h3>Gás & Água</h3>
                <small>ERP v<?= SISTEMA_VERSAO ?></small>
            </div>
        </div>

        <?php if ($empresa_atual): ?>
        <div class="sidebar-empresa">
            <div class="label">Empresa Ativa</div>
            <div class="nome"><?= escapar($empresa_atual['nome']) ?></div>
        </div>
        <?php endif; ?>

        <ul class="sidebar-menu">
            <li class="titulo-secao">Principal</li>
            <li>
                <a href="<?= BASE_URL ?>pages/dashboard.php" class="<?= $menu_ativo=='dashboard'?'active':'' ?>">
                    <span class="menu-icon"><i class="fas fa-house"></i></span> Dashboard
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>pages/vendas.php" class="<?= $menu_ativo=='vendas'?'active':'' ?>">
                    <span class="menu-icon"><i class="fas fa-cart-shopping"></i></span> Vendas / Pedidos
                </a>
            </li>

            <li class="titulo-secao">Cadastros</li>
            <li>
                <a href="<?= BASE_URL ?>pages/clientes.php" class="<?= $menu_ativo=='clientes'?'active':'' ?>">
                    <span class="menu-icon"><i class="fas fa-users"></i></span> Clientes
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>pages/produtos.php" class="<?= $menu_ativo=='produtos'?'active':'' ?>">
                    <span class="menu-icon"><i class="fas fa-boxes-stacked"></i></span> Produtos
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>pages/estoque.php" class="<?= $menu_ativo=='estoque'?'active':'' ?>">
                    <span class="menu-icon"><i class="fas fa-warehouse"></i></span> Estoque
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>pages/motoboys.php" class="<?= $menu_ativo=='motoboys'?'active':'' ?>">
                    <span class="menu-icon"><i class="fas fa-motorcycle"></i></span> Motoboys
                </a>
            </li>

            <li class="titulo-secao">Financeiro</li>
            <li>
                <a href="<?= BASE_URL ?>pages/financeiro.php" class="<?= $menu_ativo=='financeiro'?'active':'' ?>">
                    <span class="menu-icon"><i class="fas fa-dollar-sign"></i></span> Financeiro
                </a>
            </li>
            <?php if (in_array($_SESSION['perfil'] ?? '', ['admin','gerente'])): ?>
            <li>
                <a href="<?= BASE_URL ?>pages/fechamento_caixa.php" class="<?= $menu_ativo=='fechamento_caixa'?'active':'' ?>">
                    <span class="menu-icon"><i class="fas fa-cash-register"></i></span> Fechamento de Caixa
                </a>
            </li>
            <?php endif; ?>
            <li>
                <a href="<?= BASE_URL ?>pages/relatorios.php" class="<?= $menu_ativo=='relatorios'?'active':'' ?>">
                    <span class="menu-icon"><i class="fas fa-chart-line"></i></span> Relatórios
                </a>
            </li>

            <?php if ($_SESSION['perfil'] === 'admin'): ?>
            <li class="titulo-secao">Administração</li>
            <li>
                <a href="<?= BASE_URL ?>pages/empresas.php" class="<?= $menu_ativo=='empresas'?'active':'' ?>">
                    <span class="menu-icon"><i class="fas fa-building"></i></span> Empresas
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>pages/filiais.php" class="<?= $menu_ativo=='filiais'?'active':'' ?>">
                    <span class="menu-icon"><i class="fas fa-store"></i></span> Filiais
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>pages/usuarios.php" class="<?= $menu_ativo=='usuarios'?'active':'' ?>">
                    <span class="menu-icon"><i class="fas fa-user-shield"></i></span> Usuários
                </a>
            </li>
            <?php endif; ?>

            <li class="titulo-secao">Conta</li>
            <li>
                <a href="<?= BASE_URL ?>pages/selecionar_empresa.php">
                    <span class="menu-icon"><i class="fas fa-arrow-right-arrow-left"></i></span> Trocar Empresa
                </a>
            </li>
            <li>
                <a href="<?= BASE_URL ?>api/logout.php" onclick="return confirm('Deseja realmente sair?')">
                    <span class="menu-icon"><i class="fas fa-right-from-bracket"></i></span> Sair
                </a>
            </li>
        </ul>
    </aside>

    <!-- ===== ÁREA PRINCIPAL ===== -->
    <main class="main">
        <div class="topbar">
            <div style="display:flex; align-items:center; gap:15px;">
                <button onclick="toggleSidebar()" class="btn-icon" style="background:#f1f5f9; color:#334155; display:none;" id="menu-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1><?= $titulo_pagina ?></h1>
            </div>

            <!-- CHIPS DE FILIAIS -->
            <div class="filiais-chips">
                <?php foreach ($todas_filiais as $f):
                    $ativa = in_array($f['id'], $filiais_ativas);
                ?>
                    <a href="?toggle_filial=<?= $f['id'] ?><?= !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['toggle_filial'=>''])) : '' ?>"
                       class="filial-chip <?= $ativa ? 'active' : '' ?>"
                       title="<?= $ativa ? 'Clique para ocultar' : 'Clique para exibir' ?>">
                        <i class="fas fa-store"></i>
                        <?= escapar($f['nome']) ?>
                        <?php if ($ativa): ?>
                            <span class="chip-x">&times;</span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="topbar-actions">
                <button onclick="toggleDarkMode()" class="btn-icon" style="background:#f1f5f9; color:#334155;" title="Modo escuro">
                    <i class="fas fa-moon"></i>
                </button>
                <div class="topbar-user">
                    <div class="avatar"><?= strtoupper(substr($_SESSION['usuario_nome'] ?? 'U', 0, 1)) ?></div>
                    <div>
                        <div class="nome"><?= escapar($_SESSION['usuario_nome'] ?? '') ?></div>
                        <small style="color:#64748b; font-size:11px;"><?= ucfirst($_SESSION['perfil'] ?? '') ?></small>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">

<style>
@media(max-width:768px){#menu-toggle{display:inline-flex !important;}}

/* ===== CHIPS DE FILIAIS ===== */
.filiais-chips {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}
.filial-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
    cursor: pointer;
    border: 2px solid var(--border);
    color: var(--text-muted);
    background: var(--bg-light);
}
.filial-chip:hover {
    border-color: var(--primary);
    color: var(--primary);
}
.filial-chip.active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}
.filial-chip.active:hover {
    background: #1d4ed8;
}
.chip-x {
    font-size: 16px;
    line-height: 1;
    margin-left: 2px;
    opacity: 0.7;
}
.filial-chip.active .chip-x:hover {
    opacity: 1;
}

/* Topbar ajuste para caber os chips */
.topbar {
    flex-wrap: wrap;
    gap: 10px;
}
</style>
