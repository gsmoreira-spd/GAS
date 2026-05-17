# DEPLOY.md — Guia de Deploy

## Visão Geral

O sistema roda em containers Docker:
- **app** — PHP 8.2 + Apache
- **mysql** — MySQL 8
- **n8n** — automação / integração WhatsApp
- **postgres_n8n** — banco exclusivo do n8n
- **phpmyadmin** — apenas local (profile `local`)

---

## 1. Subir Localmente

### Pré-requisitos
- Docker Desktop instalado e rodando
- Git

### Passo a passo

```bash
# 1. Clonar o repositório
git clone https://github.com/gsmoreira-spd/GAS.git
cd GAS

# 2. Criar o arquivo .env
cp .env.example .env
# Edite .env com suas senhas e configurações

# 3. Subir todos os serviços (incluindo phpmyadmin)
docker compose --profile local up -d

# 4. Aguardar inicialização (MySQL demora ~30s na primeira vez)
docker compose logs -f app

# 5. Acessar
# ERP:        http://localhost:8080
# phpMyAdmin: http://localhost:8081
# n8n:        http://localhost:5678
```

### Credenciais padrão (alterar imediatamente)
| Sistema | Usuário | Senha |
|---------|---------|-------|
| ERP | admin@sistema.com | admin123 |
| n8n | admin | definido em N8N_BASIC_AUTH_PASSWORD |
| phpMyAdmin | root | definido em MYSQL_ROOT_PASSWORD |

---

## 2. Instalar em VPS (Produção)

### Pré-requisitos
- VPS com Ubuntu 22.04 ou Debian 12
- Docker e Docker Compose instalados
- Domínio apontando para o IP da VPS

### Instalar Docker na VPS

```bash
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER
newgrp docker
```

### Deploy

```bash
# 1. Clonar o projeto
git clone https://github.com/gsmoreira-spd/GAS.git /opt/gasagua_erp
cd /opt/gasagua_erp

# 2. Configurar .env
cp .env.example .env
nano .env
# Defina: APP_ENV=production, senhas fortes, APP_URL com seu domínio

# 3. Subir (sem phpmyadmin em produção)
docker compose up -d

# 4. Verificar logs
docker compose logs -f
```

### Variáveis críticas para produção

```env
APP_ENV=production
APP_URL=https://app.meudominio.com.br/
DB_PASSWORD=senha_muito_forte_aqui
MYSQL_ROOT_PASSWORD=outra_senha_forte
API_TOKEN=$(openssl rand -hex 32)
N8N_BASIC_AUTH_PASSWORD=senha_n8n_forte
```

---

## 3. Configurar Cloudflare Tunnel

O Cloudflare Tunnel expõe seus serviços locais via HTTPS sem abrir portas no firewall.

### Criar o túnel

```bash
# 1. Instalar cloudflared na VPS
curl -L https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb -o cloudflared.deb
sudo dpkg -i cloudflared.deb

# 2. Autenticar (abre URL no navegador)
cloudflared tunnel login

# 3. Criar o túnel
cloudflared tunnel create gasagua-erp

# 4. Obter o token
cloudflared tunnel token gasagua-erp
```

### Configurar subdomínios no painel Cloudflare

No painel Cloudflare → Zero Trust → Tunnels → Configure:

| Subdomínio | Serviço | Porta Interna |
|------------|---------|---------------|
| app.meudominio.com.br | http://app:80 | 80 |
| n8n.meudominio.com.br | http://n8n:5678 | 5678 |

### Ativar cloudflared no docker-compose.yml

Descomente o serviço `cloudflared` no `docker-compose.yml` e adicione ao `.env`:
```env
CLOUDFLARE_TUNNEL_TOKEN=cole_o_token_aqui
```

---

## 4. Configurar Domínio (sem Tunnel)

Se preferir usar nginx como proxy reverso em vez do Tunnel:

```nginx
# /etc/nginx/sites-available/gasagua
server {
    listen 80;
    server_name app.meudominio.com.br;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    server_name app.meudominio.com.br;

    ssl_certificate     /etc/letsencrypt/live/app.meudominio.com.br/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/app.meudominio.com.br/privkey.pem;

    location / {
        proxy_pass http://localhost:8080;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

Instalar SSL com Certbot:
```bash
sudo apt install certbot python3-certbot-nginx
sudo certbot --nginx -d app.meudominio.com.br -d n8n.meudominio.com.br
```

---

## 5. Backup do Banco de Dados

### Backup manual

```bash
# Dentro do container:
docker compose exec app /var/www/html/scripts/backup_mysql.sh

# Ou direto no host:
docker compose exec mysql \
  sh -c 'MYSQL_PWD=$MYSQL_ROOT_PASSWORD mysqldump -u root gasagua_erp' \
  > backups/manual_$(date +%Y-%m-%d).sql
```

### Backup automático via cron (no host da VPS)

```bash
# Adicionar ao crontab: crontab -e
0 3 * * * cd /opt/gasagua_erp && docker compose exec -T app /var/www/html/scripts/backup_mysql.sh >> /var/log/gasagua_backup.log 2>&1
```

### Restaurar backup

```bash
gunzip backups/gasagua_erp_2025-05-15_03-00.sql.gz
docker compose exec -T mysql \
  sh -c 'MYSQL_PWD=$MYSQL_ROOT_PASSWORD mysql -u root gasagua_erp' \
  < backups/gasagua_erp_2025-05-15_03-00.sql
```

---

## 6. Atualizar o Projeto

```bash
cd /opt/gasagua_erp

# Baixar atualizações
git pull origin main

# Reconstruir o container da aplicação
docker compose build app
docker compose up -d app

# Se houve mudança no banco (ALTER TABLE), aplicar manualmente:
docker compose exec mysql \
  sh -c 'MYSQL_PWD=$MYSQL_ROOT_PASSWORD mysql -u root gasagua_erp' \
  < database/migrations/nova_migration.sql
```

---

## 7. Segurança em Produção — Checklist

- [ ] `APP_ENV=production` no `.env`
- [ ] Senhas fortes para MySQL, n8n e API_TOKEN
- [ ] `.env` nunca versionado (está no `.gitignore`)
- [ ] `instalar.php` bloqueado (automático quando APP_ENV=production)
- [ ] phpMyAdmin desativado (não usar profile `local` em produção)
- [ ] MySQL não exposto publicamente (sem `ports:` para 3306)
- [ ] Trocar senha padrão do admin: `admin@sistema.com` / `admin123`
- [ ] HTTPS configurado (Cloudflare Tunnel ou nginx + Certbot)
- [ ] Backup automático configurado no cron
- [ ] Firewall: apenas portas 80/443 abertas no servidor

---

## Comandos Úteis

```bash
# Ver logs em tempo real
docker compose logs -f app

# Reiniciar apenas o PHP/Apache
docker compose restart app

# Acessar shell do container
docker compose exec app bash

# Ver uso de recursos
docker stats

# Parar tudo
docker compose down

# Parar e remover volumes (CUIDADO: apaga dados!)
docker compose down -v
```
