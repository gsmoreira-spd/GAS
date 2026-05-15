<?php
/**
 * NOVA VENDA / NOVO PEDIDO
 * Criação de pedidos com múltiplos itens, seleção de filial, cálculo automático
 */

// Bootstrap antes de qualquer HTML para permitir header() redirect
require_once '../config/config.php';
require_once '../includes/funcoes.php';
verificarLogin();

$msg = '';
$empresa_id = empresaAtiva();

// === SALVAR PEDIDO (antes do HTML) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_pedido'])) {
    $cliente_id      = (int)$_POST['cliente_id'];
    $motoboy_id      = !empty($_POST['motoboy_id']) ? (int)$_POST['motoboy_id'] : null;
    $filial_id       = (int)$_POST['filial_id'];
    $forma_pagamento = limpar($_POST['forma_pagamento']);
    $taxa_entrega    = (float)($_POST['taxa_entrega'] ?? 0);
    $desconto        = (float)($_POST['desconto'] ?? 0);
    $observacoes     = limpar($_POST['observacoes'] ?? '');

    $produto_ids = $_POST['produto_id'] ?? [];
    $quantidades = $_POST['quantidade'] ?? [];
    $precos      = $_POST['preco_unitario'] ?? [];

    if (empty($cliente_id) || empty($produto_ids)) {
        $msg = '<div class="alert alert-danger">Selecione um cliente e ao menos um produto.</div>';
    } else {
        $numero   = gerarNumeroPedido();
        $subtotal = 0;
        for ($i = 0; $i < count($produto_ids); $i++) {
            if (!empty($produto_ids[$i]) && $quantidades[$i] > 0) {
                $subtotal += $quantidades[$i] * $precos[$i];
            }
        }
        $total = $subtotal + $taxa_entrega - $desconto;

        $stmt = $conn->prepare("
            INSERT INTO pedidos (empresa_id, filial_id, cliente_id, motoboy_id, usuario_id, numero, subtotal, taxa_entrega, desconto, total, forma_pagamento, status, observacoes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'separacao', ?)
        ");
        $usuario_id = $_SESSION['usuario_id'];
        $stmt->bind_param("iiiissdddsss",
            $empresa_id, $filial_id, $cliente_id, $motoboy_id, $usuario_id,
            $numero, $subtotal, $taxa_entrega, $desconto, $total,
            $forma_pagamento, $observacoes
        );
        $stmt->execute();
        $pedido_id = $conn->insert_id;
        $stmt->close();

        for ($i = 0; $i < count($produto_ids); $i++) {
            $pid = (int)$produto_ids[$i];
            $qtd = (int)$quantidades[$i];
            $prc = (float)$precos[$i];
            if ($pid > 0 && $qtd > 0) {
                $sub = $qtd * $prc;
                $stmt = $conn->prepare("INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario, subtotal) VALUES (?,?,?,?,?)");
                $stmt->bind_param("iiidd", $pedido_id, $pid, $qtd, $prc, $sub);
                $stmt->execute();
                $stmt->close();
                movimentarEstoqueVenda($conn, $pid, $filial_id, $qtd, 'venda', $numero, $pedido_id);
            }
        }

        $stmt = $conn->prepare("
            INSERT INTO financeiro (empresa_id, filial_id, tipo, descricao, categoria, valor, data_vencimento, status, forma_pagamento, pedido_id)
            VALUES (?, ?, 'receita', ?, 'Venda', ?, CURDATE(), 'pendente', ?, ?)
        ");
        $desc_fin = "Pedido #{$numero}";
        $stmt->bind_param("iisdsi", $empresa_id, $filial_id, $desc_fin, $total, $forma_pagamento, $pedido_id);
        $stmt->execute();
        $stmt->close();

        if ($motoboy_id) {
            $stmt = $conn->prepare("UPDATE motoboys SET status = 'em_entrega' WHERE id = ?");
            $stmt->bind_param("i", $motoboy_id);
            $stmt->execute();
            $stmt->close();
        }

        registrarLog($conn, 'nova_venda', "Pedido #{$numero} criado - Total: R$ " . number_format($total, 2, ',', '.'));

        header("Location: vendas.php?msg=pedido_criado");
        exit;
    }
}

$titulo_pagina = "Nova Venda";
$menu_ativo = "vendas";
require_once '../includes/header.php';

if (!$empresa_id) {
    echo "<div class='alert alert-warning'>Selecione uma empresa.</div>";
    require_once '../includes/footer.php';
    exit;
}

// Filiais ativas para o select
$filiais_disponiveis = [];
$res_fil = $conn->query("SELECT id, nome FROM filiais WHERE empresa_id = {$empresa_id} AND status = 'ativo' ORDER BY nome");
while ($ff = $res_fil->fetch_assoc()) {
    $filiais_disponiveis[] = $ff;
}

// Filial selecionada para a venda (POST > GET > sessão)
$filial_venda = (int)($_POST['filial_id'] ?? $_GET['filial'] ?? filialAtiva());

// Clientes
$clientes = $conn->query("SELECT id, nome, telefone, endereco, bairro FROM clientes WHERE empresa_id = {$empresa_id} AND status = 'ativo' ORDER BY nome");

// Produtos com estoque da filial selecionada
$produtos = $conn->query("
    SELECT p.id, p.codigo, p.nome, p.tipo, p.preco_venda,
           COALESCE(e.quantidade, 0) as estoque
    FROM produtos p
    LEFT JOIN estoque e ON e.produto_id = p.id AND e.filial_id = {$filial_venda}
    WHERE p.empresa_id = {$empresa_id} AND p.status = 'ativo'
    ORDER BY p.nome
");

// Motoboys da filial selecionada (exceto indisponivel)
$motoboys = $conn->query("
    SELECT id, nome, status FROM motoboys
    WHERE empresa_id = {$empresa_id}
      AND (filial_id IS NULL OR filial_id = {$filial_venda})
      AND status != 'indisponivel'
    ORDER BY nome
");
?>

<?= $msg ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-cart-plus"></i> Novo Pedido</h3>
        <a href="vendas.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>

    <form method="POST" id="formPedido">
        <input type="hidden" name="salvar_pedido" value="1">

        <div class="form-row">
            <!-- FILIAL -->
            <div class="form-group">
                <label><i class="fas fa-store"></i> Filial *</label>
                <select name="filial_id" id="filial_id" required onchange="trocarFilial(this.value)">
                    <?php foreach ($filiais_disponiveis as $fl): ?>
                        <option value="<?= $fl['id'] ?>" <?= $fl['id'] == $filial_venda ? 'selected' : '' ?>>
                            <?= escapar($fl['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- CLIENTE -->
            <div class="form-group">
                <label><i class="fas fa-user"></i> Cliente *</label>
                <select name="cliente_id" required>
                    <option value="">-- Selecione o cliente --</option>
                    <?php while ($c = $clientes->fetch_assoc()): ?>
                        <option value="<?= $c['id'] ?>"><?= escapar($c['nome']) ?> - <?= $c['telefone'] ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <!-- FORMA DE PAGAMENTO -->
            <div class="form-group">
                <label><i class="fas fa-credit-card"></i> Forma de Pagamento</label>
                <select name="forma_pagamento">
                    <option value="dinheiro">Dinheiro</option>
                    <option value="pix">PIX</option>
                    <option value="cartao">Cartão</option>
                    <option value="fiado">Fiado</option>
                </select>
            </div>

            <!-- MOTOBOY -->
            <div class="form-group">
                <label><i class="fas fa-motorcycle"></i> Motoboy</label>
                <select name="motoboy_id">
                    <option value="">-- Sem motoboy --</option>
                    <?php while ($m = $motoboys->fetch_assoc()): ?>
                        <option value="<?= $m['id'] ?>">
                            <?= escapar($m['nome']) ?><?= $m['status'] === 'em_entrega' ? ' (Em Entrega)' : '' ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <!-- PRODUTOS -->
        <div class="card" style="background: var(--gray-100); margin-top: 15px;">
            <h3 style="margin-bottom: 15px; font-size: 15px;"><i class="fas fa-boxes-stacked"></i> Itens do Pedido</h3>

            <div id="itens-container">
                <div class="item-linha" style="display:grid; grid-template-columns: 2fr 100px 120px 120px 40px; gap:10px; align-items:end; margin-bottom:10px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Produto</label>
                        <select name="produto_id[]" class="produto-select" onchange="preencherPreco(this)">
                            <option value="">-- Selecione --</option>
                            <?php
                            $produtos->data_seek(0);
                            while ($pr = $produtos->fetch_assoc()):
                            ?>
                                <option value="<?= $pr['id'] ?>" data-preco="<?= $pr['preco_venda'] ?>" data-estoque="<?= $pr['estoque'] ?>">
                                    <?= escapar($pr['nome']) ?> (Est: <?= $pr['estoque'] ?>)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Qtd</label>
                        <input type="number" name="quantidade[]" value="1" min="1" class="qtd-input" onchange="calcularTotal()">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Preço Un.</label>
                        <input type="number" name="preco_unitario[]" step="0.01" min="0" class="preco-input" onchange="calcularTotal()">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label>Subtotal</label>
                        <input type="text" class="subtotal-input" readonly style="background:#e2e8f0;">
                    </div>
                    <div style="padding-bottom:1px;">
                        <button type="button" class="btn-icon delete" onclick="removerItem(this)" title="Remover"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            </div>

            <button type="button" class="btn btn-secondary btn-sm" onclick="adicionarItem()" style="margin-top:10px;">
                <i class="fas fa-plus"></i> Adicionar Produto
            </button>
        </div>

        <!-- TOTAIS -->
        <div class="form-row" style="margin-top: 20px;">
            <div class="form-group">
                <label>Taxa de Entrega (R$)</label>
                <input type="number" name="taxa_entrega" id="taxa_entrega" step="0.01" min="0" value="0" onchange="calcularTotal()">
            </div>
            <div class="form-group">
                <label>Desconto (R$)</label>
                <input type="number" name="desconto" id="desconto" step="0.01" min="0" value="0" onchange="calcularTotal()">
            </div>
            <div class="form-group">
                <label><strong>TOTAL</strong></label>
                <input type="text" id="total_geral" readonly style="background:#d1fae5; font-size:18px; font-weight:700; color:#065f46;" value="R$ 0,00">
            </div>
        </div>

        <div class="form-group">
            <label>Observações</label>
            <textarea name="observacoes" rows="2" placeholder="Observações do pedido..."></textarea>
        </div>

        <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px; padding-top:20px; border-top:1px solid var(--gray-200);">
            <a href="vendas.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar Pedido</button>
        </div>
    </form>
</div>

<script>
// Trocar filial recarrega a página para atualizar estoque e motoboys
function trocarFilial(filialId) {
    // Salva num form oculto e recarrega via GET
    window.location.href = 'nova_venda.php?filial=' + filialId;
}

<?php
// Se veio via GET com filial, seta no JS
$filial_get = (int)($_GET['filial'] ?? 0);
if ($filial_get > 0) {
    echo "document.addEventListener('DOMContentLoaded', function(){ document.getElementById('filial_id').value = {$filial_get}; });";
}
?>

function preencherPreco(select) {
    const option = select.options[select.selectedIndex];
    const preco = option.dataset.preco || 0;
    const linha = select.closest('.item-linha');
    linha.querySelector('.preco-input').value = parseFloat(preco).toFixed(2);
    calcularTotal();
}

function calcularTotal() {
    let subtotalGeral = 0;
    document.querySelectorAll('.item-linha').forEach(linha => {
        const qtd = parseFloat(linha.querySelector('.qtd-input')?.value) || 0;
        const preco = parseFloat(linha.querySelector('.preco-input')?.value) || 0;
        const sub = qtd * preco;
        const subInput = linha.querySelector('.subtotal-input');
        if (subInput) subInput.value = 'R$ ' + sub.toFixed(2).replace('.', ',');
        subtotalGeral += sub;
    });

    const taxa = parseFloat(document.getElementById('taxa_entrega').value) || 0;
    const desc = parseFloat(document.getElementById('desconto').value) || 0;
    const total = subtotalGeral + taxa - desc;
    document.getElementById('total_geral').value = 'R$ ' + total.toFixed(2).replace('.', ',');
}

function adicionarItem() {
    const container = document.getElementById('itens-container');
    const primeiro = container.querySelector('.item-linha');
    const clone = primeiro.cloneNode(true);

    // Limpar valores
    clone.querySelector('.produto-select').selectedIndex = 0;
    clone.querySelector('.qtd-input').value = 1;
    clone.querySelector('.preco-input').value = '';
    clone.querySelector('.subtotal-input').value = '';

    container.appendChild(clone);
}

function removerItem(btn) {
    const container = document.getElementById('itens-container');
    if (container.querySelectorAll('.item-linha').length > 1) {
        btn.closest('.item-linha').remove();
        calcularTotal();
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
