-- ============================================================
-- SISTEMA ERP - GÁS E ÁGUA
-- Banco de Dados Completo
-- ============================================================
-- Para instalar: importe este arquivo no phpMyAdmin
-- ou acesse instalar.php pelo navegador.
-- ============================================================

DROP DATABASE IF EXISTS gasagua_erp;
CREATE DATABASE gasagua_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gasagua_erp;

-- ============================================================
-- TABELA: empresas (Multi-empresa)
-- ============================================================
CREATE TABLE IF NOT EXISTS empresas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    cnpj VARCHAR(20) UNIQUE NOT NULL,
    endereco VARCHAR(255),
    telefone VARCHAR(20),
    email VARCHAR(100),
    logo VARCHAR(255) DEFAULT 'default-logo.png',
    cor_primaria VARCHAR(7) DEFAULT '#2563eb',
    status ENUM('ativo','inativo') DEFAULT 'ativo',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- TABELA: filiais (Cada empresa pode ter várias filiais)
-- ============================================================
CREATE TABLE IF NOT EXISTS filiais (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    nome VARCHAR(150) NOT NULL,
    endereco VARCHAR(255),
    telefone VARCHAR(20),
    responsavel VARCHAR(150),
    status ENUM('ativo','inativo') DEFAULT 'ativo',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABELA: usuarios (Usuários do sistema)
-- ============================================================
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(150) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    senha VARCHAR(255) NOT NULL,
    perfil ENUM('admin','gerente','vendedor','motoboy') DEFAULT 'vendedor',
    empresa_id INT DEFAULT NULL,
    filial_id INT DEFAULT NULL,
    telefone VARCHAR(20),
    foto VARCHAR(255) DEFAULT 'default-user.png',
    status ENUM('ativo','inativo') DEFAULT 'ativo',
    ultimo_acesso DATETIME DEFAULT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE SET NULL,
    FOREIGN KEY (filial_id) REFERENCES filiais(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TABELA: produtos
-- ============================================================
CREATE TABLE IF NOT EXISTS produtos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    filial_id INT NOT NULL,
    codigo VARCHAR(50) NOT NULL,
    nome VARCHAR(150) NOT NULL,
    tipo ENUM('vasilhame_gas','vasilhame_agua','gas_reposicao','gas_completo','agua_reposicao','agua_completa','outro') NOT NULL,
    vasilhame_id INT DEFAULT NULL COMMENT 'Para reposição/completo: aponta para o vasilhame vinculado',
    preco_compra DECIMAL(10,2) DEFAULT 0,
    preco_venda DECIMAL(10,2) NOT NULL,
    unidade VARCHAR(20) DEFAULT 'UN',
    estoque_minimo INT DEFAULT 5,
    descricao TEXT,
    imagem VARCHAR(255) DEFAULT 'default-product.png',
    status ENUM('ativo','inativo') DEFAULT 'ativo',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (filial_id) REFERENCES filiais(id) ON DELETE CASCADE,
    FOREIGN KEY (vasilhame_id) REFERENCES produtos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TABELA: estoque (Estoque separado por filial)
-- ============================================================
CREATE TABLE IF NOT EXISTS estoque (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    filial_id INT NOT NULL,
    quantidade INT DEFAULT 0,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY produto_filial (produto_id, filial_id),
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE,
    FOREIGN KEY (filial_id) REFERENCES filiais(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABELA: movimentacao_estoque (Histórico)
-- ============================================================
CREATE TABLE IF NOT EXISTS movimentacao_estoque (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produto_id INT NOT NULL,
    filial_id INT NOT NULL,
    tipo ENUM('entrada','saida','transferencia','venda','cancelamento') NOT NULL,
    quantidade INT NOT NULL,
    motivo VARCHAR(255),
    usuario_id INT,
    pedido_id INT DEFAULT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE,
    FOREIGN KEY (filial_id) REFERENCES filiais(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TABELA: clientes
-- ============================================================
CREATE TABLE IF NOT EXISTS clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    filial_id INT DEFAULT NULL,
    nome VARCHAR(150) NOT NULL,
    cpf_cnpj VARCHAR(20),
    telefone VARCHAR(20),
    whatsapp VARCHAR(20),
    email VARCHAR(100),
    endereco VARCHAR(255),
    numero VARCHAR(10),
    bairro VARCHAR(100),
    cidade VARCHAR(100),
    cep VARCHAR(10),
    referencia VARCHAR(255),
    observacoes TEXT,
    status ENUM('ativo','inativo') DEFAULT 'ativo',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (filial_id) REFERENCES filiais(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TABELA: motoboys
-- ============================================================
CREATE TABLE IF NOT EXISTS motoboys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    filial_id INT,
    nome VARCHAR(150) NOT NULL,
    telefone VARCHAR(20),
    cpf VARCHAR(20),
    veiculo VARCHAR(50),
    placa VARCHAR(20),
    status ENUM('disponivel','em_entrega','indisponivel') DEFAULT 'disponivel',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (filial_id) REFERENCES filiais(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TABELA: pedidos (Vendas)
-- ============================================================
CREATE TABLE IF NOT EXISTS pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    filial_id INT NOT NULL,
    cliente_id INT NOT NULL,
    motoboy_id INT DEFAULT NULL,
    usuario_id INT,
    numero VARCHAR(20) UNIQUE,
    subtotal DECIMAL(10,2) DEFAULT 0,
    taxa_entrega DECIMAL(10,2) DEFAULT 0,
    desconto DECIMAL(10,2) DEFAULT 0,
    total DECIMAL(10,2) DEFAULT 0,
    forma_pagamento ENUM('pix','dinheiro','cartao','fiado') DEFAULT 'dinheiro',
    status ENUM('pendente','separacao','entrega','entregue','finalizado','cancelado') DEFAULT 'pendente',
    observacoes TEXT,
    origem VARCHAR(30) DEFAULT 'manual',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (filial_id) REFERENCES filiais(id) ON DELETE CASCADE,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE,
    FOREIGN KEY (motoboy_id) REFERENCES motoboys(id) ON DELETE SET NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TABELA: pedido_itens (Itens de cada pedido)
-- ============================================================
CREATE TABLE IF NOT EXISTS pedido_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    produto_id INT NOT NULL,
    quantidade INT NOT NULL,
    preco_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- TABELA: financeiro (Contas a pagar/receber)
-- ============================================================
CREATE TABLE IF NOT EXISTS financeiro (
    id INT AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT NOT NULL,
    filial_id INT,
    tipo ENUM('receita','despesa') NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    categoria VARCHAR(100),
    valor DECIMAL(10,2) NOT NULL,
    data_vencimento DATE,
    data_pagamento DATE DEFAULT NULL,
    status ENUM('pendente','pago','cancelado') DEFAULT 'pendente',
    forma_pagamento ENUM('pix','dinheiro','cartao','boleto','fiado') DEFAULT NULL,
    pedido_id INT DEFAULT NULL,
    observacoes TEXT,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE CASCADE,
    FOREIGN KEY (filial_id) REFERENCES filiais(id) ON DELETE SET NULL,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- TABELA: logs (Logs do sistema)
-- ============================================================
CREATE TABLE IF NOT EXISTS logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    acao VARCHAR(100),
    descricao TEXT,
    ip VARCHAR(45),
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- DADOS INICIAIS DE TESTE
-- ============================================================

-- Empresa padrão
INSERT INTO empresas (nome, cnpj, endereco, telefone, email) VALUES
('Gás & Água Express', '12.345.678/0001-99', 'Rua Principal, 100 - Centro', '(31) 99999-0000', 'contato@gasagua.com');

-- Filial padrão
INSERT INTO filiais (empresa_id, nome, endereco, telefone, responsavel) VALUES
(1, 'Matriz - Centro', 'Rua Principal, 100', '(31) 99999-0000', 'Administrador');

-- Usuário admin padrão (senha: admin123)
-- Hash gerado com password_hash('admin123', PASSWORD_DEFAULT)
INSERT INTO usuarios (nome, email, senha, perfil, empresa_id, filial_id) VALUES
('Administrador', 'admin@sistema.com', '$2y$10$YourHashWillBeReplacedByInstaller', 'admin', 1, 1);

-- Produtos padrão
INSERT INTO produtos (empresa_id, filial_id, codigo, nome, tipo, vasilhame_id, preco_compra, preco_venda, unidade, estoque_minimo) VALUES
(1, 1, 'VAS-GAS13',  'Vasilhame Gás P13',            'vasilhame_gas',   NULL, 180.00,   0.00, 'UN', 5),
(1, 1, 'VAS-AGUA20', 'Vasilhame Galão 20L',           'vasilhame_agua',  NULL,  25.00,   0.00, 'UN', 10),
(1, 1, 'GAS-REP13',  'Gás P13 Reposição (troca)',     'gas_reposicao',   NULL,  80.00, 110.00, 'UN', 10),
(1, 1, 'GAS-COMP13', 'Gás P13 Completo (vasilhame+gás)', 'gas_completo',NULL, 260.00, 320.00, 'UN', 5),
(1, 1, 'AGUA-REP20', 'Água 20L Reposição (troca)',    'agua_reposicao',  NULL,   8.00,  14.00, 'UN', 20),
(1, 1, 'AGUA-COMP20','Água 20L Completa (galão+água)','agua_completa',   NULL,  33.00,  45.00, 'UN', 5);

-- Vincular produtos ao seu vasilhame (atualizar vasilhame_id)
-- GAS-REP13 (id=3) e GAS-COMP13 (id=4) → Vasilhame Gás P13 (id=1)
-- AGUA-REP20 (id=5) e AGUA-COMP20 (id=6) → Vasilhame Galão 20L (id=2)
UPDATE produtos SET vasilhame_id = 1 WHERE id IN (3, 4);
UPDATE produtos SET vasilhame_id = 2 WHERE id IN (5, 6);

-- Estoque inicial
-- Estoque inicial
-- id 1 = Vasilhame Gás (100), id 2 = Vasilhame Água (60)
-- id 3 = Gás Reposição (20), id 4 = Gás Completo (depende do 1 e 3)
-- id 5 = Água Reposição (50), id 6 = Água Completa (depende do 2 e 5)
INSERT INTO estoque (produto_id, filial_id, quantidade) VALUES
(1, 1, 100), (2, 1, 60), (3, 1, 20), (4, 1, 0), (5, 1, 50), (6, 1, 0);

-- Cliente exemplo
INSERT INTO clientes (empresa_id, nome, cpf_cnpj, telefone, whatsapp, endereco, bairro, cidade) VALUES
(1, 'João Silva', '123.456.789-00', '(31) 98888-1111', '(31) 98888-1111', 'Rua das Flores, 50', 'Centro', 'Ribeirão das Neves'),
(1, 'Maria Souza', '987.654.321-00', '(31) 97777-2222', '(31) 97777-2222', 'Av. Brasil, 200', 'Vila Nova', 'Ribeirão das Neves');

-- Motoboy exemplo
INSERT INTO motoboys (empresa_id, filial_id, nome, telefone, veiculo, placa) VALUES
(1, 1, 'Carlos Entregador', '(31) 96666-3333', 'Honda CG 160', 'ABC-1234');
