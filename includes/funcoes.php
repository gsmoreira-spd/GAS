<?php
/**
 * FUNÇÕES AUXILIARES DO SISTEMA
 * 
 * Reúne funções utilitárias usadas em todo o sistema:
 * - Sanitização de dados
 * - Formatação de valores
 * - Verificação de login
 * - Registro de logs
 */

/**
 * Sanitiza dados recebidos (evita SQL Injection e XSS)
 */
function limpar($dado) {
    if (is_array($dado)) {
        return array_map('limpar', $dado);
    }
    return htmlspecialchars(trim($dado), ENT_QUOTES, 'UTF-8');
}

/**
 * Escapa string para exibição segura em HTML (proteção contra XSS)
 */
function escapar($valor) {
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Verifica se o usuário está logado, senão redireciona
 */
function verificarLogin() {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
}

/**
 * Verifica perfil do usuário
 */
function verificarPerfil($perfis_permitidos = []) {
    if (!in_array($_SESSION['perfil'] ?? '', $perfis_permitidos)) {
        header('Location: ' . BASE_URL . 'pages/dashboard.php?erro=acesso_negado');
        exit;
    }
}

/**
 * Formata valores em reais (R$ 1.234,56)
 */
function formatarMoeda($valor) {
    return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

/**
 * Formata data brasileira (dd/mm/aaaa)
 */
function formatarData($data, $com_hora = false) {
    if (empty($data) || $data == '0000-00-00') return '-';
    $formato = $com_hora ? 'd/m/Y H:i' : 'd/m/Y';
    return date($formato, strtotime($data));
}

/**
 * Gera número de pedido único
 */
function gerarNumeroPedido() {
    return 'PED' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

/**
 * Registra log no sistema
 */
function registrarLog($conn, $acao, $descricao) {
    $usuario_id = $_SESSION['usuario_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $conn->prepare("INSERT INTO logs (usuario_id, acao, descricao, ip) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $usuario_id, $acao, $descricao, $ip);
    $stmt->execute();
    $stmt->close();
}

/**
 * Retorna o nome de status com classe CSS
 */
function statusBadge($status) {
    $badges = [
        'pendente'    => ['Pendente', 'warning'],
        'separacao'   => ['Em Separação', 'info'],
        'entrega'     => ['Em Entrega', 'primary'],
        'entregue'    => ['Entregue', 'success'],
        'finalizado'  => ['Finalizado', 'success'],
        'cancelado'   => ['Cancelado', 'danger'],
        'ativo'       => ['Ativo', 'success'],
        'inativo'     => ['Inativo', 'secondary'],
        'pago'        => ['Pago', 'success'],
        'disponivel'  => ['Disponível', 'success'],
        'em_entrega'  => ['Em Entrega', 'warning'],
    ];
    $info = $badges[$status] ?? [$status, 'secondary'];
    return '<span class="badge badge-' . $info[1] . '">' . $info[0] . '</span>';
}

/**
 * Retorna o nome do tipo de produto
 */
function tipoProdutoNome($tipo) {
    $tipos = [
        'vasilhame_gas'   => 'Vasilhame Gás',
        'vasilhame_agua'  => 'Vasilhame Água',
        'gas_reposicao'   => 'Gás Reposição',
        'gas_completo'    => 'Gás Completo',
        'agua_reposicao'  => 'Água Reposição',
        'agua_completa'   => 'Água Completa',
        'outro'           => 'Outro'
    ];
    return $tipos[$tipo] ?? $tipo;
}

/**
 * Retorna empresa ativa da sessão
 */
function empresaAtiva() {
    return $_SESSION['empresa_id'] ?? null;
}

/**
 * Retorna a filial ativa principal (primeira do array).
 * Usada quando se precisa de UMA filial (ex: nova venda, movimentar estoque).
 */
function filialAtiva() {
    if (!empty($_SESSION['filiais_ativas']) && is_array($_SESSION['filiais_ativas'])) {
        return $_SESSION['filiais_ativas'][0];
    }
    return $_SESSION['filial_id'] ?? null;
}

/**
 * Retorna TODAS as filiais ativas (array de IDs).
 * Usada para listagens/relatórios que mostram múltiplas filiais.
 */
function filiaisAtivas() {
    if (!empty($_SESSION['filiais_ativas']) && is_array($_SESSION['filiais_ativas'])) {
        return $_SESSION['filiais_ativas'];
    }
    $f = $_SESSION['filial_id'] ?? null;
    return $f ? [$f] : [];
}

/**
 * Retorna cláusula SQL para filtrar por filiais ativas.
 * Ex: filiaisSQL('p.filial_id') retorna "p.filial_id IN (1,2,3)"
 */
function filiaisSQL($coluna = 'filial_id') {
    $ids = filiaisAtivas();
    if (empty($ids)) return '1=0';
    $ids_str = implode(',', array_map('intval', $ids));
    return "{$coluna} IN ({$ids_str})";
}

/**
 * Atualiza estoque (entrada ou saída)
 * @param string $tipo 'entrada' ou 'saida' ou 'venda' ou 'cancelamento'
 */
function movimentarEstoque($conn, $produto_id, $filial_id, $quantidade, $tipo, $motivo = '', $pedido_id = null) {
    // Verifica se existe registro de estoque
    $stmt = $conn->prepare("SELECT id, quantidade FROM estoque WHERE produto_id = ? AND filial_id = ?");
    $stmt->bind_param("ii", $produto_id, $filial_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$res) {
        // Cria registro inicial
        $stmt = $conn->prepare("INSERT INTO estoque (produto_id, filial_id, quantidade) VALUES (?, ?, 0)");
        $stmt->bind_param("ii", $produto_id, $filial_id);
        $stmt->execute();
        $stmt->close();
        $atual = 0;
    } else {
        $atual = $res['quantidade'];
    }

    // Calcula nova quantidade
    if (in_array($tipo, ['entrada', 'cancelamento'])) {
        $nova = $atual + $quantidade;
    } else {
        $nova = $atual - $quantidade;
    }

    // Atualiza estoque
    $stmt = $conn->prepare("UPDATE estoque SET quantidade = ? WHERE produto_id = ? AND filial_id = ?");
    $stmt->bind_param("iii", $nova, $produto_id, $filial_id);
    $stmt->execute();
    $stmt->close();

    // Registra movimentação
    $usuario_id = $_SESSION['usuario_id'] ?? null;
    $stmt = $conn->prepare("INSERT INTO movimentacao_estoque (produto_id, filial_id, tipo, quantidade, motivo, usuario_id, pedido_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisisii", $produto_id, $filial_id, $tipo, $quantidade, $motivo, $usuario_id, $pedido_id);
    $stmt->execute();
    $stmt->close();

    return true;
}

/**
 * Movimenta estoque ao realizar uma VENDA com lógica de vasilhame
 * ---------------------------------------------------------------
 * REGRAS:
 *
 * 1) gas_reposicao / agua_reposicao (TROCA):
 *    - Cliente já tem vasilhame, devolve o vazio e leva cheio.
 *    - Debita o produto de reposição: -1
 *    - Credita o vasilhame vinculado: +1 (voltou vazio)
 *
 * 2) gas_completo / agua_completa (VENDA COMPLETA):
 *    - Cliente não tem vasilhame, leva tudo.
 *    - Debita o produto de reposição vinculado: -1 (saiu cheio)
 *    - Credita vasilhame: +1 (retorno do vazio da reposição)
 *    - Debita vasilhame: -1 (perdeu o vasilhame — foi pro cliente)
 *    - Resultado líquido: Reposição -1, Vasilhame 0
 *
 * 3) vasilhame_gas / vasilhame_agua / outro:
 *    - Venda direta, apenas debita.
 *
 * @param string $direcao 'venda' ou 'cancelamento' (cancelamento reverte tudo)
 */
function movimentarEstoqueVenda($conn, $produto_id, $filial_id, $quantidade, $direcao, $numero_pedido, $pedido_id) {
    // Buscar dados do produto (tipo + vasilhame vinculado)
    $stmt = $conn->prepare("SELECT tipo, vasilhame_id FROM produtos WHERE id = ?");
    $stmt->bind_param('i', $produto_id);
    $stmt->execute();
    $prod = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$prod) return false;

    $tipo_prod    = $prod['tipo'];
    $vasilhame_id = $prod['vasilhame_id'];
    $cancelando   = ($direcao === 'cancelamento');

    // -----------------------------------------------------------------
    // REPOSIÇÃO (troca): debita reposição, credita vasilhame
    // -----------------------------------------------------------------
    if (in_array($tipo_prod, ['gas_reposicao', 'agua_reposicao'])) {
        if ($cancelando) {
            // Cancelamento: devolve reposição, debita vasilhame
            movimentarEstoque($conn, $produto_id, $filial_id, $quantidade,
                'cancelamento', "Cancelamento - Pedido {$numero_pedido}", $pedido_id);
            if ($vasilhame_id) {
                movimentarEstoque($conn, $vasilhame_id, $filial_id, $quantidade,
                    'saida', "Cancelamento devolução vasilhame - Pedido {$numero_pedido}", $pedido_id);
            }
        } else {
            // Venda: debita reposição
            movimentarEstoque($conn, $produto_id, $filial_id, $quantidade,
                'venda', "Venda reposição - Pedido {$numero_pedido}", $pedido_id);
            // Credita vasilhame (voltou vazio)
            if ($vasilhame_id) {
                movimentarEstoque($conn, $vasilhame_id, $filial_id, $quantidade,
                    'entrada', "Retorno vasilhame (troca) - Pedido {$numero_pedido}", $pedido_id);
            }
        }
    }
    // -----------------------------------------------------------------
    // COMPLETO (vasilhame + gás/água): reposição -1, vasilhame 0
    // -----------------------------------------------------------------
    elseif (in_array($tipo_prod, ['gas_completo', 'agua_completa'])) {
        if ($cancelando) {
            // Cancelamento: devolve reposição (o vasilhame já era 0, nada a reverter)
            if ($vasilhame_id) {
                // Buscar a reposição vinculada ao mesmo vasilhame
                $stmt2 = $conn->prepare("
                    SELECT id FROM produtos
                    WHERE vasilhame_id = ? AND tipo IN ('gas_reposicao','agua_reposicao') AND empresa_id = (SELECT empresa_id FROM produtos WHERE id = ?)
                    LIMIT 1
                ");
                $stmt2->bind_param('ii', $vasilhame_id, $produto_id);
                $stmt2->execute();
                $rep = $stmt2->get_result()->fetch_assoc();
                $stmt2->close();

                if ($rep) {
                    movimentarEstoque($conn, $rep['id'], $filial_id, $quantidade,
                        'cancelamento', "Cancelamento reposição - Pedido {$numero_pedido}", $pedido_id);
                }
                // Vasilhame: cancelar +1 e -1 = nada (já era 0 líquido)
            }
        } else {
            // Venda completa: debita reposição, vasilhame fica 0 líquido
            if ($vasilhame_id) {
                // Buscar produto de reposição vinculado ao mesmo vasilhame
                $stmt2 = $conn->prepare("
                    SELECT id FROM produtos
                    WHERE vasilhame_id = ? AND tipo IN ('gas_reposicao','agua_reposicao') AND empresa_id = (SELECT empresa_id FROM produtos WHERE id = ?)
                    LIMIT 1
                ");
                $stmt2->bind_param('ii', $vasilhame_id, $produto_id);
                $stmt2->execute();
                $rep = $stmt2->get_result()->fetch_assoc();
                $stmt2->close();

                if ($rep) {
                    // Debita 1 reposição
                    movimentarEstoque($conn, $rep['id'], $filial_id, $quantidade,
                        'venda', "Venda completa (gás/água) - Pedido {$numero_pedido}", $pedido_id);
                }
                // Vasilhame: +1 (voltou vazio) e -1 (saiu pro cliente) = 0
                // Registrar as duas movimentações para rastreabilidade
                movimentarEstoque($conn, $vasilhame_id, $filial_id, $quantidade,
                    'entrada', "Retorno vasilhame (completo) - Pedido {$numero_pedido}", $pedido_id);
                movimentarEstoque($conn, $vasilhame_id, $filial_id, $quantidade,
                    'saida', "Saída vasilhame (vendido ao cliente) - Pedido {$numero_pedido}", $pedido_id);
            }
        }
        // Registrar saída do produto "completo" em si (para histórico no pedido)
        movimentarEstoque($conn, $produto_id, $filial_id, 0,
            $cancelando ? 'cancelamento' : 'venda',
            ($cancelando ? "Cancelamento" : "Venda") . " completo (movimentação via reposição) - Pedido {$numero_pedido}",
            $pedido_id);
    }
    // -----------------------------------------------------------------
    // VASILHAME AVULSO ou OUTRO: débito/crédito simples
    // -----------------------------------------------------------------
    else {
        movimentarEstoque($conn, $produto_id, $filial_id, $quantidade,
            $cancelando ? 'cancelamento' : 'venda',
            ($cancelando ? "Cancelamento" : "Venda") . " - Pedido {$numero_pedido}", $pedido_id);
    }

    return true;
}
