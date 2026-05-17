# ERP Gás & Água

Sistema ERP completo para empresas de distribuição de gás e água engarrafada.  
Desenvolvido em **PHP 8.2 + MySQL 8**, containerizado com **Docker**, com automação de pedidos via **n8n + WhatsApp**.

---

## Funcionalidades

| Módulo | Descrição |
|--------|-----------|
| **Login** | Autenticação com `password_hash`, sessão segura |
| **Dashboard** | Resumo de vendas, entregas do dia, estoque baixo, gráfico semanal |
| **Multi-Empresa** | Várias empresas no mesmo banco, isolamento total por `empresa_id` |
| **Multi-Filial** | Cada empresa tem N filiais; selector no topo alterna entre elas |
| **Produtos** | Cadastro por filial, 7 tipos, vínculo de vasilhame, estoque mínimo |
| **Estoque** | Por filial, entrada/saída manual, débito automático na venda, reversão no cancelamento |
| **Vasilhame Vinculado** | Reposição debita produto e credita vasilhame; Completo mantém vasilhame zerado |
| **Clientes** | Cadastro completo, link direto WhatsApp |
| **Nova Venda** | Multi-item, cálculo automático, seleção de filial, motoboy, taxa de entrega, desconto |
| **Pedidos** | Fluxo: Separação → Em Entrega → Entregue → Finalizado / Cancelado |
| **Motoboys** | Cadastro por filial, status (disponível / em entrega / indisponível) |
| **Financeiro** | Contas a receber geradas automaticamente, lançamentos manuais, saldo por filial |
| **Relatórios** | Vendas, Mais Vendidos, Estoque, Clientes, Financeiro — filtros de data, impressão |
| **Usuários** | Perfis: Admin, Gerente, Vendedor, Motoboy |
| **Dark Mode** | Toggle na barra superior, preferência salva no browser |
| **Responsivo** | Desktop, tablet e celular |
| **API REST** | Endpoints para integração externa (n8n, WhatsApp, ERPs) |

---

## Início Rápido

### Pré-requisitos

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- Git

### Subir em 3 comandos

```bash
git clone https://github.com/gsmoreira-spd/GAS.git
cd GAS
cp .env.example .env
docker compose up -d
```

Acesse **http://localhost:8080** e faça login:

| Campo  | Valor               |
|--------|---------------------|
| E-mail | `admin@sistema.com` |
| Senha  | `admin123`          |

> Troque a senha após o primeiro acesso.

---

## Serviços

| Container       | URL                       | Descrição              |
|-----------------|---------------------------|------------------------|
| `gas_app`       | http://localhost:8080      | ERP (PHP + Apache)     |
| `gas_mysql`     | `localhost:3306`           | Banco de dados MySQL 8 |
| `gas_n8n`       | http://localhost:5678      | Automação / Webhooks   |
| `gas_n8n_postgres` | interno                 | Banco do n8n           |

Para produção com Cloudflare Tunnel:

```bash
docker compose --profile production up -d
```

---

## Variáveis de Ambiente

Copie `.env.example` para `.env` e ajuste conforme necessário:

```env
# Aplicação
APP_ENV=local
APP_URL=http://localhost:8080
APP_PORT=8080

# Banco de dados
DB_HOST=mysql
DB_DATABASE=gasagua_erp
DB_USERNAME=gas_user
DB_PASSWORD=gas_password
DB_ROOT_PASSWORD=root_password

# API
API_TOKEN=troque_este_token_em_producao

# n8n
N8N_PORT=5678
N8N_BASIC_AUTH_USER=admin
N8N_BASIC_AUTH_PASSWORD=troque_esta_senha

# Cloudflare Tunnel (somente produção)
CLOUDFLARE_TUNNEL_TOKEN=
```

---

## Estrutura do Projeto

```
gasagua_erp/
├── api/
│   ├── login.php              # Autenticação e criação de sessão
│   ├── logout.php
│   └── v1/                    # API REST (autenticada por Bearer token)
│       ├── clientes.php
│       ├── pedidos.php
│       ├── produtos.php
│       ├── status_pedido.php
│       └── webhook/n8n.php    # Recebe eventos do n8n
├── assets/
│   ├── css/style.css          # Estilos globais, variáveis CSS, dark mode
│   └── js/script.js           # Funções globais, máscaras, modais
├── config/
│   └── config.php             # Conexão DB, constantes, fuso horário
├── database/
│   └── gasagua_erp.sql        # Schema completo + dados iniciais
├── docker/
│   ├── apache/vhost.conf      # VirtualHost Apache
│   └── php/php.ini            # Configuração PHP (timezone, limites, sessão)
├── includes/
│   ├── funcoes.php            # movimentarEstoque, helpers, formatação
│   ├── header.php             # Sidebar, topbar, selector de filial
│   └── footer.php
├── pages/                     # Módulos do sistema
│   ├── dashboard.php
│   ├── selecionar_empresa.php
│   ├── empresas.php
│   ├── filiais.php
│   ├── produtos.php
│   ├── estoque.php
│   ├── clientes.php
│   ├── motoboys.php
│   ├── usuarios.php
│   ├── vendas.php
│   ├── nova_venda.php
│   ├── pedido.php
│   ├── financeiro.php
│   └── relatorios.php
├── scripts/
│   ├── entrypoint.sh          # Aguarda MySQL antes de iniciar Apache
│   └── backup_mysql.sh        # Backup automatizado do banco
├── .env.example               # Modelo de variáveis de ambiente
├── docker-compose.yml         # Orquestração dos containers
├── Dockerfile                 # Imagem PHP 8.2 + Apache + locale pt_BR
├── index.php                  # Tela de login
└── instalar.php               # Instalador web (só em ambiente local)
```

---

## Schema do Banco

```sql
empresas             → id, nome, cnpj, telefone, endereco
filiais              → id, empresa_id, nome, telefone, endereco
usuarios             → id, empresa_id, filial_id, nome, email, senha, perfil, status
clientes             → id, empresa_id, nome, telefone, endereco, cidade
produtos             → id, empresa_id, filial_id, codigo, nome, tipo, vasilhame_id, preco_venda
estoque              → produto_id, filial_id, quantidade       (UNIQUE produto+filial)
movimentacao_estoque → produto_id, filial_id, tipo, quantidade, motivo, pedido_id
pedidos              → id, empresa_id, filial_id, cliente_id, motoboy_id, numero, total, status
pedido_itens         → pedido_id, produto_id, quantidade, preco_unitario
motoboys             → id, empresa_id, filial_id, nome, veiculo, placa, status
financeiro           → id, empresa_id, filial_id, tipo, valor, status, pedido_id
logs                 → id, usuario_id, empresa_id, acao, descricao, ip, created_at
```

---

## Lógica de Estoque por Tipo de Produto

Movimentações automáticas na venda e reversão automática no cancelamento:

| Tipo de Produto         | Reposição | Vasilhame |
|-------------------------|-----------|-----------|
| `gas_reposicao`         | **-1**    | **+1**    |
| `agua_reposicao`        | **-1**    | **+1**    |
| `gas_completo`          | **-1**    | **±0**    |
| `agua_completa`         | **-1**    | **±0**    |
| `vasilhame_gas`         | **±0**    | **-1**    |
| `vasilhame_agua`        | **±0**    | **-1**    |
| `outro`                 | **-1**    | **±0**    |

---

## API REST

Todos os endpoints exigem o header `Authorization: Bearer <API_TOKEN>`.

| Método | Endpoint                            | Descrição                          |
|--------|-------------------------------------|------------------------------------|
| GET    | `/api/v1/clientes.php?telefone=...` | Busca cliente por telefone         |
| POST   | `/api/v1/clientes.php`              | Cria novo cliente                  |
| GET    | `/api/v1/produtos.php`              | Lista produtos ativos com estoque  |
| GET    | `/api/v1/pedidos.php?telefone=...`  | Últimos pedidos do cliente         |
| POST   | `/api/v1/pedidos.php`              | Cria pedido via automação          |
| GET    | `/api/v1/status_pedido.php?id=...`  | Status detalhado do pedido         |
| POST   | `/api/v1/webhook/n8n.php`          | Recebe eventos do n8n              |

Consulte [N8N_WHATSAPP.md](N8N_WHATSAPP.md) para exemplos de payload e configuração dos fluxos n8n.

---

## Automação via n8n + WhatsApp

O n8n roda no próprio Docker e pode ser conectado ao **WhatsApp Business Cloud API** para:

- Receber pedidos por WhatsApp
- Notificar clientes sobre status do pedido
- Confirmar entregas automaticamente

Acesse o painel em **http://localhost:5678** com as credenciais do `.env`.

---

## Segurança

- Senhas com `password_hash()` / `password_verify()` (bcrypt)
- Todas as queries com **prepared statements** (sem SQL Injection)
- Saída HTML com `htmlspecialchars()` (sem XSS)
- Verificação de sessão em todas as páginas protegidas
- Isolamento por `empresa_id` em todas as queries
- Arquivos sensíveis bloqueados via `.htaccess` (`.env`, `.sql`, `.sh`, etc.)
- `instalar.php` bloqueado automaticamente em `APP_ENV=production`

---

## Deploy em Produção

Consulte [DEPLOY.md](DEPLOY.md) para instruções completas de:

- Deploy em VPS (Ubuntu)
- Configuração do Cloudflare Tunnel (HTTPS sem abrir portas)
- Backup automático do banco de dados
- Renovação de certificado SSL

---

## Stack

| Camada       | Tecnologia                        |
|--------------|-----------------------------------|
| Backend      | PHP 8.2                           |
| Banco        | MySQL 8.0                         |
| Frontend     | HTML5, CSS3 (vars nativas), JS vanilla |
| Ícones       | FontAwesome 6                     |
| Gráficos     | Chart.js                          |
| Container    | Docker + Apache 2.4               |
| Automação    | n8n + WhatsApp Business Cloud API |
| Tunnel       | Cloudflare Tunnel                 |
| Locale       | pt_BR.UTF-8 / America/Sao_Paulo   |
