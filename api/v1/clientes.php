<?php
/**
 * GET  /api/v1/clientes.php?telefone=NUMERO  — busca cliente por telefone/WhatsApp
 * POST /api/v1/clientes.php                  — cria novo cliente
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/_auth.php';

$metodo = $_SERVER['REQUEST_METHOD'];

// ------------------------------------------------------------------
// GET — buscar cliente por telefone
// ------------------------------------------------------------------
if ($metodo === 'GET') {
    $telefone = preg_replace('/\D/', '', $_GET['telefone'] ?? '');
    if (strlen($telefone) < 8) {
        apiError(400, 'Parâmetro "telefone" inválido ou ausente (mínimo 8 dígitos).');
    }

    $like = '%' . $telefone . '%';
    $stmt = $conn->prepare("
        SELECT id, nome, cpf_cnpj, telefone, whatsapp, email,
               endereco, numero, bairro, cidade, cep, referencia, observacoes, status
        FROM clientes
        WHERE (REGEXP_REPLACE(telefone,  '[^0-9]', '') LIKE ?
            OR REGEXP_REPLACE(whatsapp, '[^0-9]', '') LIKE ?)
        ORDER BY nome
        LIMIT 5
    ");
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();
    $stmt->close();

    $clientes = [];
    while ($row = $res->fetch_assoc()) {
        $clientes[] = $row;
    }

    apiSuccess($clientes);
}

// ------------------------------------------------------------------
// POST — criar novo cliente
// ------------------------------------------------------------------
if ($metodo === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    if (!is_array($body)) {
        apiError(400, 'Body JSON inválido.');
    }

    $empresa_id  = (int)($body['empresa_id']  ?? 0);
    $nome        = trim($body['nome']         ?? '');
    if ($empresa_id <= 0 || $nome === '') {
        apiError(422, 'Campos obrigatórios: empresa_id, nome.');
    }

    $cpf_cnpj    = trim($body['cpf_cnpj']    ?? '');
    $telefone    = trim($body['telefone']    ?? '');
    $whatsapp    = trim($body['whatsapp']    ?? $telefone);
    $email       = trim($body['email']       ?? '');
    $endereco    = trim($body['endereco']    ?? '');
    $numero      = trim($body['numero']      ?? '');
    $bairro      = trim($body['bairro']      ?? '');
    $cidade      = trim($body['cidade']      ?? '');
    $cep         = trim($body['cep']         ?? '');
    $referencia  = trim($body['referencia']  ?? '');
    $observacoes = trim($body['observacoes'] ?? '');

    // Verificar empresa existe
    $stmt = $conn->prepare("SELECT id FROM empresas WHERE id = ? AND status = 'ativo'");
    $stmt->bind_param('i', $empresa_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        $stmt->close();
        apiError(404, 'Empresa não encontrada ou inativa.');
    }
    $stmt->close();

    // Verificar duplicidade por telefone
    if ($telefone !== '') {
        $tel_digits = preg_replace('/\D/', '', $telefone);
        $tel_like = '%' . $tel_digits . '%';
        $stmt = $conn->prepare("
            SELECT id FROM clientes
            WHERE empresa_id = ?
              AND (REGEXP_REPLACE(telefone, '[^0-9]', '') LIKE ?
                OR REGEXP_REPLACE(whatsapp, '[^0-9]', '') LIKE ?)
            LIMIT 1
        ");
        $stmt->bind_param('iss', $empresa_id, $tel_like, $tel_like);
        $stmt->execute();
        $dup = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($dup) {
            apiError(409, 'Já existe cliente com este telefone. id=' . $dup['id']);
        }
    }

    $stmt = $conn->prepare("
        INSERT INTO clientes
            (empresa_id, nome, cpf_cnpj, telefone, whatsapp, email,
             endereco, numero, bairro, cidade, cep, referencia, observacoes)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->bind_param(
        'issssssssssss',
        $empresa_id, $nome, $cpf_cnpj, $telefone, $whatsapp, $email,
        $endereco, $numero, $bairro, $cidade, $cep, $referencia, $observacoes
    );

    if (!$stmt->execute()) {
        $err = $stmt->error;
        $stmt->close();
        apiError(500, IS_PRODUCTION ? 'Erro ao salvar cliente.' : $err);
    }
    $novo_id = $conn->insert_id;
    $stmt->close();

    apiSuccess(['id' => $novo_id, 'mensagem' => 'Cliente criado com sucesso.'], 201);
}

apiError(405, 'Método não permitido.');
