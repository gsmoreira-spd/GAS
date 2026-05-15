# 🔥 ERP Gás & Água — Sistema de Gestão Completo

Sistema ERP profissional para empresas de distribuição de gás e água.
Desenvolvido em PHP + MySQL, pronto para rodar no **XAMPP**.

---

## 📋 Funcionalidades

| Módulo | Descrição |
|--------|-----------|
| **Login** | Autenticação com senha criptografada (password_hash) |
| **Dashboard** | Painel com resumo de vendas, entregas, gráfico semanal, estoque baixo |
| **Multi-Empresa** | Suporte a várias empresas no mesmo sistema |
| **Multi-Filial** | Cada empresa pode ter múltiplas filiais com estoque próprio |
| **Produtos** | Cadastro com tipos: Gás Cheio, Gás Vazio, Água Cheia, Água Vazia, Outro |
| **Estoque** | Controle por filial, entrada/saída manual, débito automático na venda, devolução no cancelamento, histórico completo |
| **Clientes** | Cadastro completo com endereço, telefone e link direto para WhatsApp |
| **Vendas/Pedidos** | Criação de pedidos com múltiplos itens, cálculo automático, atribuição de motoboy |
| **Status do Pedido** | Fluxo: Pendente → Separação → Entrega → Entregue → Finalizado / Cancelado |
| **Motoboys** | Cadastro de entregadores com veículo, placa e status |
| **Financeiro** | Contas a receber/pagar, lançamentos manuais, marcar como pago, resumo de saldo |
| **Relatórios** | Vendas, Produtos mais vendidos, Estoque, Clientes, Financeiro — com filtros e impressão |
| **Usuários** | Perfis: Admin, Gerente, Vendedor, Motoboy — com permissões |
| **Impressão** | Cupom/recibo do pedido otimizado para impressão térmica (80mm) |
| **Dark Mode** | Tema escuro com toggle na barra superior |
| **Responsivo** | Funciona em desktop, tablet e celular |

---

## 🛠️ Instalação no XAMPP (Passo a Passo)

### 1. Instalar o XAMPP
- Baixe em: [https://www.apachefriends.org](https://www.apachefriends.org)
- Instale normalmente (Next, Next, Finish).

### 2. Copiar o projeto
- Extraia o ZIP do projeto.
- Copie a pasta `gasagua_erp` para dentro de:
  - **Windows:** `C:\xampp\htdocs\`
  - **Linux:** `/opt/lampp/htdocs/`
  - **Mac:** `/Applications/XAMPP/htdocs/`

### 3. Iniciar os serviços
- Abra o **XAMPP Control Panel**.
- Clique em **Start** no **Apache** e no **MySQL**.
- Os dois devem ficar verdes.

### 4. Instalar o banco de dados
- Abra o navegador e acesse:
  ```
  http://localhost/gasagua_erp/instalar.php
  ```
- Clique em **"Instalar Sistema"**.
- O instalador cria o banco de dados e as tabelas automaticamente.
- Após a instalação, aparecerão as credenciais de acesso.

### 5. Acessar o sistema
- Acesse: `http://localhost/gasagua_erp/`
- **E-mail:** `admin@sistema.com`
- **Senha:** `admin123`

> ⚠️ **IMPORTANTE:** Troque a senha do admin após o primeiro acesso!

---

## 📁 Estrutura de Pastas

```
gasagua_erp/
├── api/                  # Endpoints (login, logout)
│   ├── login.php
│   └── logout.php
├── assets/
│   ├── css/
│   │   └── style.css     # Estilos do sistema inteiro
│   └── js/
│       └── script.js     # Funções JS globais
├── config/
│   └── config.php        # Conexão com banco + constantes
├── database/
│   └── gasagua_erp.sql   # Script SQL completo
├── includes/
│   ├── funcoes.php       # Funções auxiliares (estoque, log, formatação)
│   ├── header.php        # Menu lateral + barra superior
│   └── footer.php        # Fechamento do layout + scripts
├── pages/
│   ├── dashboard.php     # Painel principal
│   ├── selecionar_empresa.php
│   ├── empresas.php      # CRUD empresas
│   ├── filiais.php       # CRUD filiais
│   ├── produtos.php      # CRUD produtos
│   ├── estoque.php       # Controle de estoque
│   ├── clientes.php      # CRUD clientes
│   ├── motoboys.php      # CRUD motoboys
│   ├── usuarios.php      # CRUD usuários
│   ├── vendas.php        # Lista de pedidos
│   ├── nova_venda.php    # Criar novo pedido
│   ├── pedido.php        # Detalhes do pedido
│   ├── financeiro.php    # Controle financeiro
│   └── relatorios.php    # Relatórios com filtros
├── index.php             # Tela de login
├── instalar.php          # Instalador web
└── INSTALACAO.md         # Este arquivo
```

---

## 🔒 Segurança

- **Senhas:** criptografadas com `password_hash()` / `password_verify()`.
- **SQL Injection:** proteção com `prepared statements` em todas as queries.
- **XSS:** sanitização de entrada com `htmlspecialchars()`.
- **Sessões:** verificação de login em todas as páginas protegidas.
- **Perfis:** controle de acesso por nível (admin, gerente, vendedor, motoboy).

---

## 💡 Como Usar — Guia Rápido

### Primeiro Acesso
1. Faça login com `admin@sistema.com` / `admin123`.
2. Selecione a empresa (já vem uma cadastrada: "Gás & Água Express").
3. Você será levado ao **Dashboard**.

### Cadastrar Produtos
- Menu: **Cadastros > Produtos**.
- Clique em "Novo Produto".
- Preencha nome, código, tipo (Gás Cheio, Água Cheia, etc.), preço.
- O sistema já vem com 6 produtos exemplo.

### Cadastrar Clientes
- Menu: **Cadastros > Clientes**.
- Preencha os dados do cliente (nome, telefone, endereço).
- O ícone do WhatsApp abre conversa direto com o cliente.

### Fazer uma Venda
1. Menu: **Vendas** → botão **"Nova Venda"**.
2. Selecione o cliente.
3. Adicione produtos (o preço é preenchido automaticamente).
4. Escolha forma de pagamento.
5. Opcionalmente, atribua um motoboy e taxa de entrega.
6. Clique em **"Salvar Pedido"**.
7. O estoque é debitado automaticamente e a conta a receber é criada.

### Acompanhar Pedidos
- Na lista de vendas, use os botões de status:
  - **Separar** → pedido vai para separação.
  - **Enviar** → pedido sai para entrega.
  - **Entregar** → motoboy confirmou a entrega.
  - **Finalizar** → pedido concluído, pagamento registrado.
  - **Cancelar** → estoque é devolvido, financeiro cancelado.

### Controlar Estoque
- Menu: **Cadastros > Estoque**.
- Veja a quantidade por produto na filial atual.
- Use o botão **"Movimentar"** para entrada/saída manual.
- O histórico de movimentações fica logo abaixo.

### Financeiro
- Menu: **Financeiro**.
- Cards de resumo: receitas, a receber, despesas, saldo.
- Crie lançamentos manuais (despesas como aluguel, combustível, etc.).
- Marque lançamentos como pagos.

### Relatórios
- Menu: **Relatórios**.
- Abas: Vendas, Mais Vendidos, Estoque, Clientes, Financeiro.
- Use os filtros de data.
- Botão **"Imprimir"** gera versão otimizada para impressão.

---

## 🎨 Personalização

### Cores
Edite as variáveis CSS no arquivo `assets/css/style.css`:
```css
:root {
    --primary: #2563eb;    /* Azul principal */
    --success: #16a34a;    /* Verde */
    --danger: #dc2626;     /* Vermelho */
    --warning: #d97706;    /* Laranja */
}
```

### Nome do Sistema
Edite o arquivo `config/config.php`:
```php
define('SISTEMA_NOME', 'Seu Nome Aqui');
```

### Adicionar Nova Empresa
1. Menu: **Administração > Empresas**.
2. Clique em "Nova Empresa" e preencha os dados.
3. Depois, crie filiais para a nova empresa.

---

## 📞 Suporte

Desenvolvido como sistema completo e funcional.
Sinta-se livre para modificar, expandir e adaptar conforme necessidade.

**Tecnologias:** PHP 8+ · MySQL 5.7+ · HTML5 · CSS3 · JavaScript · Chart.js · FontAwesome 6
