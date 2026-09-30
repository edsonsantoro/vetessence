# Docker — Ambiente de Desenvolvimento AgroVerde

Ambiente Docker para desenvolvimento do fork AgroVerde do VetEssence.

## Stack

| Serviço | Imagem | Porta | Papel |
|---------|--------|-------|-------|
| `app` | `serversideup/php:8.4-fpm-nginx` | 8000 | PHP 8.4 FPM + Nginx |
| `db` | `mariadb:10.11` | 3306 | Banco de dados |
| `redis` | `redis:7-alpine` | 6379 | Cache, queue, session |
| `mailpit` | `axllent/mailpit` | 8025 (UI), 1025 (SMTP) | Captura de e-mails |
| `queue` | `serversideup/php:8.4-fpm-nginx` | — | Worker de filas |

## Por Que `serversideup/php`

- ✅ Imagem oficial recomendada pela comunidade Laravel
- ✅ Nginx embutido (não precisa de container separado)
- ✅ Suporte nativo a Laravel (autorun de migrations, storage link)
- ✅ Variáveis de ambiente para configurar PHP (OPcache, memory_limit)
- ✅ Multi-arquitetura (amd64, arm64)

## Setup Rápido

```bash
./docker/setup.sh
```

O script:
1. Verifica Docker
2. Cria `.env` a partir do `.env.example`
3. Ajusta as variáveis para o ambiente Docker
4. Sobe os containers
5. Roda `composer install`
6. Gera `APP_KEY`
7. Roda migrations + seeders
8. Cria o storage link

## Setup Manual

```bash
# 1. Criar .env
cp .env.example .env

# 2. Ajustar variáveis (DB_HOST=db, REDIS_HOST=redis, etc.)
#    Ver docker/setup.sh para a lista completa

# 3. Subir containers
docker compose up -d

# 4. Instalar dependências
docker compose exec app composer install

# 5. Gerar key
docker compose exec app php artisan key:generate

# 6. Migrations + seeders
docker compose exec app php artisan migrate --seed
```

## Comandos Úteis

```bash
# Subir / parar
docker compose up -d
docker compose down
docker compose down -v          # remove volumes (apaga o banco)

# Logs
docker compose logs -f app
docker compose logs -f queue

# Shell no container
docker compose exec app bash

# Artisan
docker compose exec app php artisan migrate
docker compose exec app php artisan tinker
docker compose exec app php artisan test

# Composer
docker compose exec app composer install
docker compose exec app composer require <pacote>

# NPM
docker compose exec app npm install
docker compose exec app npm run dev
```

## Configurações de Performance

Baseadas em `docs/performance.md` (guia do VetEssence):

### PHP (`docker/php/99-agroverde.ini`)

| Config | Valor | Motivo |
|--------|-------|--------|
| `memory_limit` | 256M | Evita OOM-kill (o default `-1` é perigoso) |
| `max_execution_time` | 300 | Suficiente para web + fila |
| `opcache.enable` | 1 | **Maior impacto** — evita recompilar PHP |
| `opcache.memory_consumption` | 256 | Cache de scripts compilados |
| `opcache.max_accelerated_files` | 20000 | Cobre o projeto inteiro |
| `post_max_size` / `upload_max_filesize` | 64M | Uploads de exames/documentos |

### MariaDB (`docker/mariadb/99-agroverde.cnf`)

| Config | Valor | Motivo |
|--------|-------|--------|
| `innodb_buffer_pool_size` | 512M | **Principal gargalo** — evita I/O de disco |
| `innodb_flush_log_at_trx_commit` | 2 | Melhor performance (dev) |
| `slow_query_log` | 1 | Identifica queries lentas |

> **Produção:** aumentar `innodb_buffer_pool_size` para 1.5G (servidor 4GB RAM).

## Variáveis de Ambiente

Podem ser sobrescritas via `.env` ou shell:

```env
APP_PORT=8000              # porta da aplicação
DB_PORT_EXTERNAL=3306      # porta do banco (host)
REDIS_PORT_EXTERNAL=6379   # porta do redis (host)
MAILPIT_UI_PORT=8025       # porta da UI do mailpit
DB_DATABASE=vetessence
DB_USERNAME=vetessence
DB_PASSWORD=secret
DB_ROOT_PASSWORD=root
```

## Troubleshooting

### Porta já em uso

```bash
# Mudar a porta no .env
APP_PORT=8080
docker compose up -d
```

### Banco não sobe

```bash
# Ver logs
docker compose logs db

# Recriar volume (APAGA os dados)
docker compose down -v
docker compose up -d
```

### Permissões de arquivo

O container roda como `www-data` (uid 33). Se houver erro de permissão:

```bash
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
```

### OPcache não atualiza após editar código

Em desenvolvimento, `opcache.validate_timestamps=1` já revalida. Se persistir:

```bash
docker compose restart app
```

## Diferenças do Ambiente de Produção

| Aspecto | Dev (Docker) | Produção |
|---------|-------------|----------|
| `APP_DEBUG` | true | false |
| `QUEUE_CONNECTION` | redis | redis (Supervisor) |
| OPcache | habilitado | habilitado |
| Config/Route cache | desabilitado | habilitado |
| `innodb_buffer_pool_size` | 512M | 1.5G |
| SSL | não | Certbot |
| Nginx | embutido | dedicado |

Ver `docs/performance.md` para o guia completo de produção.
