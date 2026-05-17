# Integração n8n + WhatsApp Bot com IA

## Cenário completo

```
Cliente envia mensagem no WhatsApp
         ↓
   n8n recebe via Webhook
         ↓
   IA conversa com o cliente
   (coleta produto, endereço, pagamento)
         ↓
   Busca ou cadastra cliente no ERP
         ↓
   Lança venda automaticamente
         ↓
   ┌────────────────────┐
   │  Sucesso           │  → "Seu pedido PED... foi criado! Em breve sai."
   │  Erro de estoque   │  → "Produto indisponível no momento."
   │  Erro de sistema   │  → "Transferindo para atendente..." + alerta interno
   └────────────────────┘
         ↓
   Quando atendente muda status → ERP notifica n8n → WhatsApp cliente
```

---

## Arquitetura

```
WhatsApp Business Cloud API
        ↓ POST webhook
   n8n (localhost:5678)
        ↓ Bearer Token
   ERP /api/v1/*  (localhost:8080)
        ↓ N8N_WEBHOOK_STATUS_URL
   n8n recebe avisos de status do ERP
```

---

## Pré-requisitos

1. **Meta Developers** — App WhatsApp Business criado
2. **n8n** rodando (já está no docker-compose)
3. **Cloudflare Tunnel** (produção) ou ngrok (teste local) — a URL pública do n8n é necessária para o webhook da Meta
4. **API_TOKEN** configurado no `.env`
5. **Modelo de IA** — OpenAI GPT-4o ou Claude (configurar credencial no n8n)

---

## Parte 1 — Configurar WhatsApp → n8n

### 1.1 Criar App na Meta

1. Acesse **developers.facebook.com → Meus Apps → Criar App → Business**
2. Adicione o produto **WhatsApp** e clique **Configurar**
3. Anote:
   - **Phone Number ID** (ex: `123456789012345`)
   - **Token de Acesso** (gere um token permanente via System User)

### 1.2 Webhook no n8n

1. Abra o n8n em `http://localhost:5678`
2. Crie um workflow → adicione node **WhatsApp Trigger**
3. Configure a credencial **WhatsApp Business Cloud** (Phone Number ID + Token)
4. Ative o workflow e copie a **Webhook URL**

### 1.3 Registrar webhook na Meta

1. No painel do app → **WhatsApp → Configuração → Webhook → Editar**
2. Cole a URL do n8n
3. Token de verificação: use o mesmo que o n8n mostrar
4. Assine o evento: **messages**

---

## Parte 2 — Workflow do Bot de Pedidos

### Diagrama de nós

```
[1] WhatsApp Trigger
      ↓
[2] Code — Extrair telefone e mensagem
      ↓
[3] HTTP Request — GET /api/v1/clientes.php?telefone=&empresa_id=
      ↓
[4] IF — Cliente encontrado?
      ├── Sim → usar cliente_id existente
      └── Não → [5] AI Agent coleta nome+endereço → [6] POST /api/v1/clientes.php
      ↓
[7] HTTP Request — GET /api/v1/produtos.php?empresa_id=1&filial_id=1
      ↓
[8] AI Agent — conversa com o cliente, usa produtos disponíveis como contexto
      (coleta: produto, quantidade, endereço de entrega, forma de pagamento)
      ↓
[9] Code — monta body do pedido
      ↓
[10] HTTP Request — POST /api/v1/pedidos.php
      ↓
[11] IF — HTTP status 201?
      ├── Sim → [12] WhatsApp envia confirmação ao cliente
      └── Não → [13] WhatsApp envia "transferindo para atendente"
                     [14] Alerta interno (Slack/WhatsApp do atendente)
```

---

### Nó 2 — Extrair telefone e mensagem

**Tipo:** Code (JavaScript)

```javascript
const msg = $input.first().json;

// Estrutura do webhook WhatsApp Business Cloud
const entry   = msg.entry?.[0];
const change  = entry?.changes?.[0];
const value   = change?.value;
const message = value?.messages?.[0];

if (!message) return [];  // ignorar outros eventos (status, leitura)

const telefone = message.from;             // ex: "5531988881111"
const texto    = message.text?.body ?? ''; // texto da mensagem
const msgId    = message.id;

return [{
  json: {
    telefone,
    texto,
    msgId,
    timestamp: message.timestamp,
  }
}];
```

---

### Nó 3 — Buscar cliente

**Tipo:** HTTP Request

```
Método: GET
URL: http://gas_app/api/v1/clientes.php
Headers: Authorization: Bearer {{ $env.API_TOKEN }}
Params: telefone={{ $json.telefone }}&empresa_id=1
```

---

### Nó 8 — AI Agent (núcleo do bot)

**Tipo:** AI Agent

**System Prompt:**
```
Você é um atendente virtual da [NOME DA EMPRESA], distribuidora de gás e água.
Seu trabalho é atender pedidos via WhatsApp de forma rápida e simpática.

PRODUTOS DISPONÍVEIS (atualizados em tempo real):
{{ JSON.stringify($node["Buscar Produtos"].json, null, 2) }}

CLIENTE IDENTIFICADO:
- Nome: {{ $node["Buscar Cliente"].json[0]?.nome ?? "Novo cliente" }}
- Endereço cadastrado: {{ $node["Buscar Cliente"].json[0]?.endereco ?? "Não informado" }}

INSTRUÇÕES:
1. Cumprimente o cliente pelo nome se ele já for cadastrado
2. Mostre apenas produtos com disponivel=true
3. Pergunte o que deseja pedir (produto + quantidade)
4. Confirme o endereço de entrega (use o cadastrado ou pergunte um novo)
5. Pergunte a forma de pagamento: pix, dinheiro, cartão ou fiado
6. Apresente o resumo do pedido com total e peça confirmação
7. Quando o cliente confirmar, responda APENAS com este JSON (sem texto extra):

{
  "acao": "criar_pedido",
  "produto_id": <id do produto>,
  "quantidade": <numero>,
  "endereco_entrega": "<endereço completo>",
  "forma_pagamento": "pix|dinheiro|cartao|fiado",
  "observacoes": "<observações do cliente ou vazio>"
}

Se não conseguir entender o pedido após 3 tentativas, responda:
{ "acao": "transferir_atendente", "motivo": "<razão>" }

REGRAS:
- Seja objetivo, máximo 2 frases por mensagem
- Use emojis com moderação
- Nunca invente produtos que não estão na lista
- Nunca aceite preços diferentes dos cadastrados
```

---

### Nó 9 — Montar body do pedido

**Tipo:** Code (JavaScript)

```javascript
const aiResponse = $input.first().json.output;

// Parsear JSON da resposta da IA
let dados;
try {
  const jsonMatch = aiResponse.match(/\{[\s\S]*\}/);
  dados = JSON.parse(jsonMatch[0]);
} catch (e) {
  return [{ json: { acao: 'erro_parse', resposta_ia: aiResponse } }];
}

if (dados.acao === 'transferir_atendente') {
  return [{ json: dados }];
}

const cliente = $node["Buscar Cliente"].json[0];

return [{
  json: {
    acao: 'criar_pedido',
    body: {
      empresa_id: 1,
      filial_id: cliente?.filial_id ?? 1,
      cliente_id: parseInt($node["Buscar Cliente"].json[0]?.id ?? $node["Criar Cliente"].json.id),
      itens: [{
        produto_id: dados.produto_id,
        quantidade: dados.quantidade,
      }],
      forma_pagamento: dados.forma_pagamento,
      taxa_entrega: 5.00,
      observacoes: dados.observacoes ?? '',
      origem: 'whatsapp',
    }
  }
}];
```

---

### Nó 10 — Criar pedido no ERP

**Tipo:** HTTP Request

```
Método: POST
URL: http://gas_app/api/v1/pedidos.php
Headers:
  Authorization: Bearer {{ $env.API_TOKEN }}
  Content-Type: application/json
Body: {{ JSON.stringify($json.body) }}
```

---

### Nó 12 — Mensagem de confirmação

**Tipo:** WhatsApp Business Cloud → Send Message

```javascript
// Montar mensagem de confirmação
const pedido = $node["Criar Pedido"].json;
const total  = pedido.total.toFixed(2).replace('.', ',');

return `✅ Pedido *${pedido.numero}* confirmado!\n\n` +
       `💰 Total: R$ ${total}\n` +
       `📦 Status: Em separação\n\n` +
       `Em breve seu pedido sairá para entrega. Obrigado! 🙏`;
```

---

### Nó 13 — Mensagem de fallback (erro)

**Tipo:** WhatsApp Business Cloud → Send Message

```javascript
const erro = $node["Criar Pedido"].json?.erro ?? 'Erro desconhecido';
const msg_estoque = erro.includes('Estoque insuficiente');

if (msg_estoque) {
  return `😕 Infelizmente esse produto está sem estoque no momento.\n` +
         `Posso te ajudar com outro produto?`;
}

return `⚠️ Não consegui processar seu pedido agora.\n` +
       `Um atendente vai te chamar em breve! 👋`;
```

---

### Nó 14 — Alerta para atendente (opcional)

**Tipo:** WhatsApp Business Cloud → Send Message  
**Para:** número do atendente/grupo interno

```javascript
const telefone = $node["Extrair Mensagem"].json.telefone;
const texto    = $node["Extrair Mensagem"].json.texto;
const motivo   = $node["Montar Pedido"].json.motivo ?? 'Erro na criação do pedido';

return `⚠️ *TRANSFERÊNCIA NECESSÁRIA*\n\n` +
       `📱 Cliente: +${telefone}\n` +
       `💬 Última mensagem: "${texto}"\n` +
       `❌ Motivo: ${motivo}`;
```

---

## Parte 3 — Avisos Automáticos de Status

Quando o atendente muda o status do pedido no ERP, o sistema notifica o n8n automaticamente (já implementado em `pages/vendas.php`).

### 3.1 Configurar no .env

```env
N8N_WEBHOOK_STATUS_URL=http://localhost:5678/webhook/status-pedido
```

### 3.2 Criar Workflow "Avisos de Status" no n8n

**Nó 1 — Webhook**
```
Método: POST
Path: status-pedido
```

**Nó 2 — Switch por status**

| Status     | Mensagem ao cliente |
|------------|---------------------|
| `separacao` | `📦 Seu pedido *{numero}* está sendo separado!` |
| `entrega`   | `🛵 Seu pedido *{numero}* saiu para entrega! Em breve chega.` |
| `entregue`  | `✅ Seu pedido foi entregue! Obrigado pela preferência! 🙏` |
| `cancelado` | `❌ Seu pedido *{numero}* foi cancelado. Dúvidas? Fale conosco.` |

**Nó 3 — WhatsApp Send Message**
```javascript
// Código do Switch node
const { status, numero, telefone } = $input.first().json;

const mensagens = {
  separacao: `📦 Seu pedido *${numero}* está sendo separado!`,
  entrega:   `🛵 Seu pedido *${numero}* saiu para entrega! Já já chega. 😊`,
  entregue:  `✅ Pedido entregue! Obrigado pela preferência! 🙏`,
  cancelado: `❌ Seu pedido *${numero}* foi cancelado.\nDúvidas? Fale conosco.`,
};

return [{
  json: {
    to: telefone,
    mensagem: mensagens[status] ?? `Seu pedido *${numero}* foi atualizado: ${status}`,
  }
}];
```

---

## Parte 4 — Endpoints da API

Base URL: `http://gas_app/api/v1/` (interno Docker) ou `https://seudominio.com/api/v1/` (externo)  
Header obrigatório: `Authorization: Bearer <API_TOKEN>`

| Método | Endpoint | Parâmetros |
|--------|----------|------------|
| GET | `/clientes.php` | `telefone=55319...` `empresa_id=1` |
| POST | `/clientes.php` | body JSON com `empresa_id`, `nome`, `telefone`, `filial_id` |
| GET | `/produtos.php` | `empresa_id=1` `filial_id=1` |
| POST | `/pedidos.php` | body JSON (ver abaixo) |
| GET | `/status_pedido.php` | `id=42` ou `numero=PED...` |
| POST | `/webhook/n8n.php` | body JSON com `evento` e `pedido_id` |

### POST /pedidos.php — body completo

```json
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
  "observacoes": "Portão azul, tocar campainha",
  "origem": "whatsapp"
}
```

**Respostas:**

| HTTP | Significado | O que fazer no n8n |
|------|-------------|-------------------|
| 201 | Pedido criado ✅ | Enviar confirmação ao cliente |
| 404 | Produto/cliente/filial não encontrado | Verificar dados e tentar novamente |
| 409 | Cliente duplicado | Usar o `id` retornado na mensagem de erro |
| 422 | Estoque insuficiente | Informar cliente e oferecer alternativa |
| 500 | Erro interno | Transferir para atendente |

---

## Parte 5 — Teste Manual da API

```bash
TOKEN="seu_api_token_aqui"
BASE="http://localhost:8080/api/v1"

# Buscar cliente por telefone (com filtro de empresa)
curl -H "Authorization: Bearer $TOKEN" \
     "$BASE/clientes.php?telefone=31988881111&empresa_id=1"

# Cadastrar novo cliente
curl -X POST \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"empresa_id":1,"filial_id":1,"nome":"Teste Bot","telefone":"31988881111","whatsapp":"31988881111","endereco":"Rua Teste","bairro":"Centro","cidade":"Ribeirão das Neves"}' \
     "$BASE/clientes.php"

# Listar produtos disponíveis
curl -H "Authorization: Bearer $TOKEN" \
     "$BASE/produtos.php?empresa_id=1&filial_id=1"

# Criar pedido (simula bot)
curl -X POST \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"empresa_id":1,"filial_id":1,"cliente_id":1,"itens":[{"produto_id":3,"quantidade":1}],"forma_pagamento":"pix","taxa_entrega":5.00,"origem":"whatsapp"}' \
     "$BASE/pedidos.php"

# Verificar status do pedido
curl -H "Authorization: Bearer $TOKEN" \
     "$BASE/status_pedido.php?id=1"

# Simular mudança de status via webhook
curl -X POST \
     -H "Authorization: Bearer $TOKEN" \
     -H "Content-Type: application/json" \
     -d '{"evento":"pedido_status","pedido_id":1,"dados":{"status":"entrega"}}' \
     "$BASE/webhook/n8n.php"
```

---

## Parte 6 — Checklist de Deploy

- [ ] App criado na Meta Developers
- [ ] Token de acesso WhatsApp gerado
- [ ] Credencial WhatsApp configurada no n8n
- [ ] Webhook Meta → URL pública do n8n registrado
- [ ] `API_TOKEN` definido no `.env` (valor forte, não o padrão)
- [ ] `N8N_WEBHOOK_STATUS_URL` definido no `.env`
- [ ] Workflow "Bot de Pedidos" ativo no n8n
- [ ] Workflow "Avisos de Status" ativo no n8n
- [ ] Credencial de IA (OpenAI/Claude) configurada no n8n
- [ ] Teste end-to-end com número real de WhatsApp
