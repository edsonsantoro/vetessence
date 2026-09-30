# Docker — Ambiente de Desenvolvimento AgroVerde

Ambiente Docker para desenvolvimento do fork AgroVerde do VetEssence.

## Stack

| Serviço | Imagem | Porta | Papel |
|---------|--------|-------|-------|
| `app` | `agroverde/php:8.4` (build local) | **8080** | PHP 8.4 FPM + Nginx |
| `db` | `mariadb:10.11` | 3306 | Banco de dados |
| `redis` | `redis:7-alpine` | 6379 | Cache, queue, session |
| `mailpit` | `axllent/mailpit` | 8025 (UI), 1025 (SMTP) | Captura de e-mails |
| `queue` | `agroverde/php:8.4` | — | Worker de filas |

> **Nota:** a porta padrão é **8080** (a 8000 pode estar ocupada por outro serviço). Ajuste via `APP_PORT` no `.env`.

## Por Que `serversideup/php`

- ✅ Imagem oficial recomendada pela comunidade Laravel
- ✅ Nginx embutido (não precisa de container separado)
- ✅ Suporte nativo a Laravel (autorun de migrations, storage link)
- ✅ Variáveis de ambiente para configurar PHP (OPcache, memory_limit)
- ✅ Multi-arquitetura (amd64, arm64)

## Imagem Customizada

A imagem base **não inclui** todas as extensões que o VetEssence requer. O
`docker/php/Dockerfile` estende a base e instala:

| Extensão | Usada por |
|----------|-----------|
| `gd` | simple-qrcode, intervention/image |
| `bcmath` | cálculos financeiros |
| `intl` | formatação i18n |
| `exif` | metadados de imagens |

O build é feito automaticamente pelo `setup.sh` ou manualmente:

```bash
docker compose build app
```

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

## Limites de Recursos

Cada container tem limites de CPU e memória para evitar que um serviço
consuma todos os recursos da máquina.

| Serviço | CPU (limit) | Memória (limit) | CPU (reserve) | Memória (reserve) |
|---------|-------------|-----------------|---------------|-------------------|
| `app` | 2.0 | 1 GB | 0.5 | 512 MB |
| `db` | 2.0 | 1 GB | 0.5 | 512 MB |
| `queue` | 1.0 | 512 MB | 0.25 | 128 MB |
| `redis` | 0.5 | 256 MB | 0.1 | 64 MB |
| `mailpit` | 0.5 | 128 MB | 0.1 | 32 MB |
| **Total** | **6.0** | **2.9 GB** | **1.45** | **1.25 GB** |

> **Limits** = teto máximo. **Reservations** = garantia mínima.

Ajuste conforme os recursos da sua máquina:

```yaml
deploy:
  resources:
    limits:
      cpus: "2.0"
      memory: 1G
    reservations:
      cpus: "0.5"
      memory: 512M
```

Verificar os limites aplicados:

```bash
docker inspect agroverde-app --format '{{.HostConfig.Memory}} {{.HostConfig.NanoCpus}}'
docker stats --no-stream
```

## Logging e Rotação

Os logs são protegidos em **3 camadas**:

| Camada | Onde | Config |
|--------|------|--------|
| **1. Daemon** | `/etc/docker/daemon.json` | `json-file`, max-size 50m, max-file 3, compress |
| **2. Compose** | `docker-compose.yml` | `json-file`, max-size 10m, max-file 3, compress |
| **3. Logrotate** | `/etc/logrotate.d/docker-containers.conf` | diário, size 50M, rotate 3 |

A camada 2 (por container) é **mais restritiva** que a 1 (global), garantindo
que nenhum container acumule mais de ~30 MB de log.

### Logs da aplicação (Laravel)

O Laravel usa `LOG_CHANNEL=daily` (rotação própria de 14 dias).

Para instalar o logrotate dos logs da aplicação (fallback):

```bash
sudo ./docker/logrotate/install.sh
```

Isso cria `/etc/logrotate.d/agroverde` com:

```
storage/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    notifempty
    create 0664 www-data www-data
    dateext
    dateformat -%Y-%m-%d
    su www-data www-data
}
```

> **Nota:** os logs do Docker **não** são incluídos aqui — já são cobertos
> por `/etc/logrotate.d/docker-containers.conf` (política do sistema).
> Duplicar causaria conflito.

### Verificar

```bash
# Logs do Docker (tamanho atual)
sudo du -sh /var/lib/docker/containers/*/*-json.log | sort -rh | head

# Config do logrotate
sudo logrotate -d /etc/logrotate.d/agroverde    # dry-run
sudo logrotate -f /etc/logrotate.d/agroverde    # forçar

# Timer do sistema
systemctl list-timers logrotate.timer
```

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

### Container `app` em loop de restart

**Sintoma:** `Restarting (1)` e log `Could not detect Laravel installation`.

**Causa:** o `vendor/` não existe (composer install não rodou).

**Solução:**

```bash
docker compose stop app queue
docker compose run --rm --no-deps -u root app composer install
docker compose run --rm --no-deps -u root app chown -R www-data:www-data vendor bootstrap/cache storage
docker compose up -d
```

### `composer install` falha: "vendor does not exist and could not be created"

**Causa:** o container roda como `www-data` (uid 33) e não pode criar `vendor/`.

**Solução:** rodar como root e depois ajustar permissões:

```bash
docker compose run --rm --no-deps -u root app composer install
docker compose run --rm --no-deps -u root app chown -R www-data:www-data vendor bootstrap/cache storage
```

### Warnings de PUSHER no docker compose

**Sintoma:** `The "PUSHER_HOST" variable is not set`.

**Causa:** o `.env` do projeto tem `VITE_PUSHER_HOST="${PUSHER_HOST}"` e o Docker Compose tenta interpolar.

**Solução:** adicionar ao `.env`:

```env
PUSHER_HOST=127.0.0.1
PUSHER_PORT=6001
PUSHER_SCHEME=http
```

### Container `queue` unhealthy

**Causa:** o healthcheck da imagem base espera PHP-FPM, mas o container roda apenas o worker.

**Solução:** já corrigido no `docker-compose.yml` (`healthcheck: disable: true`).

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
