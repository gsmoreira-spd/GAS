# ERP Gás & Água

Sistema ERP completo para empresas de distribuição de gás e água. Desenvolvido em **PHP + MySQL**, roda no **XAMPP** sem configuração extra.

---

## Funcionalidades

| Módulo | Descrição |
|--------|-----------|
| **Login** | Autenticação com `password_hash`, sessão segura |
| **Dashboard** | Resumo de vendas, entregas do dia, estoque baixo, gráfico semanal |
| **Multi-Empresa** | Várias empresas no mesmo banco, isolamento total por `empresa_id` |
| **Multi-Filial** | Cada empresa tem N filiais; selector no topo alterna entre elas |
| **Produtos** | Cadastro por filial, 7 tipos, vínculo de vasilhame, estoque mínimo |
| **Estoque** | Por filial, entrada/saída manual, débito automático na venda, reversão no cancelamento, histórico |
| **Vasilhame Vinculado** | Lógica automática: Reposição debita produto e credita vasilhame; Completo debita produto e mantém vasilhame zerado |
| **Clientes** | Cadastro completo, link direto WhatsApp |
| **Nova Venda** | Multi-item, cálculo automático, seleção de filial, motoboy, taxa de entrega, desconto |
| **Pedidos** | Fluxo completo: Separação → Entrega → Entregue → Finalizado / Cancelado |
| **Motoboys** | Cadastro por filial, status (disponível / em entrega / indisponível), vinculado ao pedido |
| **Financeiro** | Contas a receber geradas automaticamente na venda, lançamentos manuais, saldo por filial |
| **Relatórios** | Vendas, Mais Vendidos, Estoque, Clientes, Financeiro — filtros de data, impressão |
| **Usuários** | Perfis: Admin, Gerente, Vendedor, Motoboy |
| **Dark Mode** | Toggle na barra superior, preferência salva no browser |
| **Responsivo** | Desktop, tablet e celular |

---

## Lógica de Estoque por Tipo de Produto

O sistema aplica movimentações automáticas conforme o tipo do produto vendido:

| Tipo | O que acontece na venda |
|------|------------------------|
| `gas_reposicao` / `agua_reposicao` | Cliente devolve vasilhame vazio → Reposição **-1**, Vasilhame **+1** |
| `gas_completo` / `agua_completa` | Cliente compra vasilhame + gás → Reposição **-1**, Vasilhame **±0** (sai cheio, não volta vazio) |
| `vasilhame_gas` / `vasilhame_agua` | Venda avulsa do vasilhame → Vasilhame **-1** |
| `outro` | Débito simples |

Cancelamento de pedido reverte todos os movimentos automaticamente.

---

## Isolamento por Filial

- Produtos têm `filial_id` — cada produto pertence a **uma** filial
- Selector no topo permite ver uma filial ou ambas simultaneamente
- Quando uma filial está selecionada, apenas produtos, estoque, motoboys e histórico daquela filial aparecem
- Vasilhame vinculado só lista vasilhames da mesma filial

---

## Instalação

### Pré-requisitos
- [XAMPP](https://www.apachefriends.org) com Apache + MySQL rodando

### Passos

**1. Clonar o repositório**
```bash
git clone https://github.com/gsmoreira-spd/GAS.git
```
Ou baixar o ZIP e extrair.

**2. Mover para o htdocs**
```
C:\xampp\htdocs\gasagua_erp\
```

**3. Iniciar XAMPP**
- Apache: Start
- MySQL: Start

**4. Instalar o banco**
```
http://localhost/gasagua_erp/instalar.php
```
Clique em **Instalar Sistema**. Banco, tabelas e dados iniciais são criados automaticamente.

**5. Acessar**
```
http://localhost/gasagua_erp/
```
| Campo | Valor |
|-------|-------|
| E-mail | `admin@sistema.com` |
| Senha | `admin123` |

> Troque a senha após o primeiro acesso.

---

## Estrutura do Projeto

```
gasagua_erp/
├── api/
│   ├── login.php             # Autenticação + criação de sessão
│   └── logout.php
├── assets/
│   ├── css/style.css         # Estilos globais (variáveis CSS, dark mode)
│   └── js/script.js          # Funções globais (modal, busca, máscaras)
├── config/
│   └── config.php            # Conexão DB + constantes do sistema
├── database/
│   └── gasagua_erp.sql       # Schema completo + dados iniciais
├── includes/
│   ├── funcoes.php           # movimentarEstoque, movimentarEstoqueVenda, helpers
│   ├── header.php            # Sidebar, topbar, selector de filial
│   └── footer.php            # Fechamento do layout
├── pages/
│   ├── dashboard.php
│   ├── selecionar_empresa.php
│   ├── empresas.php
│   ├── filiais.php
│   ├── produtos.php          # CRUD com filial_id, vasilhame vinculado condicional
│   ├── estoque.php           # Visualização por filial, movimentação manual
│   ├── clientes.php
│   ├── motoboys.php
│   ├── usuarios.php
│   ├── vendas.php
│   ├── nova_venda.php        # POST processado antes do HTML (sem headers already sent)
│   ├── pedido.php
│   ├── financeiro.php
│   └── relatorios.php
├── index.php                 # Tela de login
└── instalar.php              # Instalador web
```

---

## Schema Principal

```sql
empresas      → id, nome, cnpj, ...
filiais       → id, empresa_id, nome, ...
usuarios      → id, empresa_id, nome, email, senha, perfil
clientes      → id, empresa_id, nome, telefone, endereco, ...
produtos      → id, empresa_id, filial_id, codigo, nome, tipo, vasilhame_id, preco_venda, ...
estoque       → produto_id, filial_id, quantidade           (UNIQUE produto+filial)
movimentacao_estoque → produto_id, filial_id, tipo, quantidade, motivo, pedido_id, ...
pedidos       → id, empresa_id, filial_id, cliente_id, motoboy_id, numero, total, status, ...
pedido_itens  → pedido_id, produto_id, quantidade, preco_unitario
motoboys      → id, empresa_id, filial_id, nome, veiculo, placa, status
financeiro    → id, empresa_id, filial_id, tipo, valor, status, pedido_id, ...
```

---

## Stack

- **Backend:** PHP 8+
- **Banco:** MySQL 5.7+ / MariaDB
- **Frontend:** HTML5, CSS3 (variáveis nativas), JavaScript vanilla
- **Ícones:** FontAwesome 6
- **Gráficos:** Chart.js
- **Servidor local:** XAMPP

---

## Segurança

- Senhas com `password_hash()` / `password_verify()`
- Todas as queries com `prepared statements` (sem SQL injection)
- Saída HTML com `htmlspecialchars()` (sem XSS)
- Verificação de sessão em todas as páginas protegidas
- Isolamento por `empresa_id` em todas as queries
