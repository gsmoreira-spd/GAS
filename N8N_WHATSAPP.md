# N8N_WHATSAPP.md — Integração n8n + WhatsApp Business

## Fluxo Principal — Pedido via WhatsApp

```
WhatsApp Trigger
  ↓
Normalizar telefone
  ↓
HTTP Request: GET /api/v1/clientes.php?telefone=...
  ↓
IF cliente existe?
  ├── Sim: listar produtos disponíveis (GET /api/v1/produtos.php)
  └── Não: pedir nome/endereço e cadastrar (POST /api/v1/clientes.php)
  ↓
Receber escolha do cliente
  ↓
Calcular total
  ↓
Confirmar pedido com o cliente
  ↓
HTTP Request: POST /api/v1/pedidos.php
  ↓
WhatsApp: "Pedido PED2025... criado! Aguarde."
```

## Fluxo de Avisos de Status

```
ERP muda status do pedido (pages/vendas.php)
  ↓
HTTP POST para webhook do n8n
  ↓
n8n envia WhatsApp ao cliente:
  "Seu pedido saiu para entrega." / "Entregue!" / etc.
```

---

## Visão Geral Técnica

```
WhatsApp Business Cloud API
         ↓ webhook
        n8n (localhost:5678)
         ↓ HTTP Requests
    ERP /api/v1/*  (Bearer Token)
```

---

## 1. Criar App na Meta Developers

1. Acesse https://developers.facebook.com/ e faça login
2. Clique em **Meus Apps → Criar App**
3. Escolha **Business** como tipo
4. Preencha nome (ex: `GasAgua Bot`) e conta Business
5. No painel do app, procure **WhatsApp** e clique **Configurar**
6. Anote:
   - **Phone Number ID** (ex: `123456789012345`)
   - **WhatsApp Business Account ID**
   - **Token de Acesso Temporário** (para testes) ou gere um permanente

### Gerar Token Permanente

1. Acesse **Configurações → Avançado → Tokens de Acesso do Sistema**
2. Crie um System User com permissão `whatsapp_business_messaging`
3. Gere um token com validade longa (60 ou 90 dias) ou sem expiração

---

## 2. Configurar WhatsApp Business Cloud API no n8n

### Credenciais no n8n

1. Abra o n8n em `http://localhost:5678` (ou `https://n8n.meudominio.com.br`)
2. Vá em **Configurações → Credenciais → Nova Credencial**
3. Escolha **WhatsApp Business Cloud**
4. Preencha:
   - **Access Token**: token gerado acima
   - **Phone Number ID**: ID do número de telefone

---

## 3. Configurar Webhook do WhatsApp → n8n

### No n8n

1. Crie um novo workflow
2. Adicione um node **Webhook**
3. Configure:
   - **Method**: POST
   - **Path**: `/whatsapp` (ou qualquer nome)
4. Ative o workflow e copie a URL do webhook (ex: `https://n8n.meudominio.com.br/webhook/whatsapp`)

### No Meta Developers

1. No painel do app → **WhatsApp → Configuração**
2. Em **Webhook**, clique **Editar**
3. Cole a URL do n8n no campo **URL de Callback**
4. **Token de Verificação**: crie um texto qualquer (ex: `gasaguaverify`)
5. No n8n, configure o webhook para responder ao challenge de verificação:
   - O WhatsApp enviará `GET ?hub.mode=subscribe&hub.verify_token=...&hub.challenge=...`
   - O n8n deve responder com o valor de `hub.challenge`
6. Assine os eventos: **messages**

---

## 4. Endpoints da API do ERP

Base URL: `https://app.meudominio.com.br/api/v1/`  
Header obrigatório: `Authorization: Bearer <API_TOKEN>`

### GET /clientes.php?telefone=NUMERO

Busca cliente por telefone ou WhatsApp.

```json
// Response 200
[
  {
    "id": 1,
    "nome": "João Silva",
    "telefone": "(31) 98888-1111",
    "whatsapp": "(31) 98888-1111",
    "endereco": "Rua das Flores",
    "numero": "50",
    "bairro": "Centro",
    "cidade": "Ribeirão das Neves",
    "status": "ativo"
  }
]
// Response 200 com array vazio [] = cliente não cadastrado
```

### POST /clientes.php

Cria novo cliente.

```json
// Request Body
{
  "empresa_id": 1,
  "nome": "Pedro Costa",
  "telefone": "(31) 99999-3333",
  "whatsapp": "(31) 99999-3333",
  "endereco": "Av. Central",
  "numero": "100",
  "bairro": "Jardim",
  "cidade": "Ribeirão das Neves"
}

// Response 201
{ "id": 5, "mensagem": "Cliente criado com sucesso." }
```

### GET /produtos.php?empresa_id=1&filial_id=1

Lista produtos ativos com estoque.

```json
// Response 200
[
  {
    "id": 3,
    "codigo": "GAS-REP13",
    "nome": "Gás P13 Reposição (troca)",
    "tipo": "gas_reposicao",
    "preco_venda": 110.00,
    "unidade": "UN",
    "estoque_atual": 20,
    "estoque_minimo": 10,
    "disponivel": true,
    "filial_nome": "Matriz - Centro"
  }
]
```

### POST /pedidos.php

Cria pedido vindo do WhatsApp.

```json
// Request Body
{
  "empresa_id": 1,
  "filial_id": 1,
  "cliente_id": 5,
  "itens": [
    { "produto_id": 3, "quantidade": 2 },
    { "produto_id": 5, "quantidade": 1 }
  ],
  "forma_pagamento": "pix",
  "taxa_entrega": 5.00,
  "desconto": 0,
  "observacoes": "Portão azul, tocar a campainha",
  "origem": "whatsapp"
}

// Response 201
{
  "id": 42,
  "numero": "PED202505150001",
  "total": 243.00,
  "status": "pendente",
  "mensagem": "Pedido criado com sucesso."
}
```

### GET /pedidos.php?telefone=31988881111

Lista últimos pedidos do cliente (por telefone).

```json
// Response 200
[
  {
    "id": 42,
    "numero": "PED202505150001",
    "status": "entrega",
    "origem": "whatsapp",
    "total": 243.00,
    "criado_em": "2025-05-15 10:30:00"
  }
]
```

### GET /status_pedido.php?id=42

Retorna status detalhado do pedido.

```json
// Response 200
{
  "id": 42,
  "numero": "PED202505150001",
  "status": "entrega",
  "total": 243.00,
  "motoboy_nome": "Carlos Entregador",
  "motoboy_telefone": "(31) 96666-3333",
  "itens": [
    { "produto_nome": "Gás P13 Reposição", "quantidade": 2, "subtotal": 220.00 },
    { "produto_nome": "Água 20L Reposição", "quantidade": 1, "subtotal": 14.00 }
  ]
}
```

### POST /webhook/n8n.php

O ERP recebe eventos do n8n (ex: atualizar status de pedido).

```json
// Request Body
{
  "evento": "pedido_status",
  "pedido_id": 42,
  "dados": { "status": "entregue" }
}

// Response 200
{ "mensagem": "Status do pedido 42 atualizado para 'entregue'." }
```

---

## 5. Fluxos n8n — Roteiros

### Fluxo 1 — Atendimento Inicial

```
[Webhook WhatsApp] → Extrair telefone da mensagem
  → GET /api/v1/clientes.php?telefone=...
  → Se vazio: pedir nome e endereço ao cliente
       → POST /api/v1/clientes.php
  → Se encontrado: saudar pelo nome
  → Responder via [WhatsApp node] "Olá, {nome}! Como posso ajudar?"
```

### Fluxo 2 — Cardápio de Produtos

```
Trigger: mensagem contém "produtos", "cardápio", "preço"
  → GET /api/v1/produtos.php?empresa_id=1&filial_id=1
  → Montar texto com produtos disponíveis e preços
  → Responder via WhatsApp
```

**Exemplo de mensagem montada no n8n (Function node):**
```javascript
const produtos = $input.all()[0].json;
const disponiveis = produtos.filter(p => p.disponivel);
const texto = disponiveis.map(p =>
  `• ${p.nome}: R$ ${p.preco_venda.toFixed(2).replace('.',',')} (${p.estoque_atual} em estoque)`
).join('\n');
return [{ json: { mensagem: `Nossos produtos disponíveis:\n\n${texto}\n\nQual você deseja?` } }];
```

### Fluxo 3 — Criar Pedido

```
Trigger: cliente escolheu produto e confirmou
  → Coletar: produto_id, quantidade, forma_pagamento
  → Confirmar endereço (GET cliente, mostrar endereço cadastrado)
  → Calcular total (produto * qtd + taxa_entrega)
  → Enviar confirmação: "Posso confirmar? Total: R$ X"
  → Se cliente confirmar: POST /api/v1/pedidos.php
  → Responder: "Pedido {numero} criado! Aguarde."
```

### Fluxo 4 — Status do Pedido

```
Trigger: mensagem contém "cadê", "pedido", "entrega", "status"
  → GET /api/v1/pedidos.php?telefone=...
  → Pegar pedido mais recente
  → GET /api/v1/status_pedido.php?id=...
  → Montar resposta com status em português
  → Responder via WhatsApp
```

**Mapeamento de status para texto:**
```javascript
const statusTexto = {
  pendente:   '⏳ Recebido e aguardando confirmação',
  separacao:  '📦 Em separação no depósito',
  entrega:    '🛵 Saiu para entrega',
  entregue:   '✅ Entregue',
  finalizado: '✅ Finalizado',
  cancelado:  '❌ Cancelado',
};
```

### Fluxo 5 — Avisos Automáticos de Status

Para notificar clientes quando o status mudar no ERP, configure um webhook no n8n:

1. Crie um workflow no n8n com trigger **Webhook**
2. Anote a URL (ex: `https://n8n.meudominio.com.br/webhook/status-mudou`)
3. No ERP (arquivo `pages/vendas.php`), ao atualizar o status, dispare via HTTP:

```php
// Disparar n8n quando status mudar (adicionar em vendas.php)
$n8n_url = getenv('N8N_WEBHOOK_URL_STATUS');
if ($n8n_url && $novo_status !== $status_anterior) {
    $payload = json_encode([
        'pedido_id' => $pedido_id,
        'status'    => $novo_status,
        'telefone'  => $cliente['whatsapp'],
    ]);
    $ch = curl_init($n8n_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_exec($ch);
    curl_close($ch);
}
```

**Mensagens sugeridas para cada status:**
| Status | Mensagem WhatsApp |
|--------|-------------------|
| separacao | "Seu pedido {numero} está sendo separado! 📦" |
| entrega | "Seu pedido {numero} saiu para entrega! 🛵 {motoboy_nome} vai aí." |
| entregue | "Seu pedido foi entregue! ✅ Obrigado pela preferência!" |
| cancelado | "Seu pedido {numero} foi cancelado. Dúvidas? Fale conosco." |

---

## 6. Testar a API Manualmente

```bash
# Definir token (copie do .env)
TOKEN="seu_api_token_aqui"
BASE="http://localhost:8080/api/v1"

# Buscar cliente por telefone
curl -H "Authorization: Bearer $TOKEN" \
     "$BASE/clientes.php?telefone=31988881111"

# Listar produtos
curl -H "Authorization: Bearer $TOKEN" \
     "$BASE/produtos.php?empresa_id=1&filial_id=1"

# Criar pedido
curl -X POST \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"empresa_id":1,"filial_id":1,"cliente_id":1,"itens":[{"produto_id":3,"quantidade":1}],"forma_pagamento":"pix","origem":"whatsapp"}' \
     "$BASE/pedidos.php"

# Status do pedido
curl -H "Authorization: Bearer $TOKEN" \
     "$BASE/status_pedido.php?id=1"
```
