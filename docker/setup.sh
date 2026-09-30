#!/usr/bin/env bash
#
# AgroVerde — Setup do ambiente Docker
#
# Uso: ./docker/setup.sh
#
set -euo pipefail

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m'

info()  { echo -e "${BLUE}▸${NC} $1"; }
ok()    { echo -e "${GREEN}✓${NC} $1"; }
warn()  { echo -e "${YELLOW}!${NC} $1"; }
error() { echo -e "${RED}✗${NC} $1"; }

echo ""
echo "=========================================="
echo "  AgroVerde — Setup Docker"
echo "=========================================="
echo ""

# --- 1. Verificar Docker ---
if ! command -v docker &>/dev/null; then
    error "Docker não encontrado. Instale: https://docs.docker.com/engine/install/"
    exit 1
fi
ok "Docker encontrado: $(docker --version)"

if ! docker compose version &>/dev/null; then
    error "Docker Compose não encontrado."
    exit 1
fi
ok "Docker Compose: $(docker compose version --short)"

# --- 2. Criar .env ---
if [ ! -f .env ]; then
    info "Criando .env a partir do .env.example..."
    cp .env.example .env
    ok ".env criado"
else
    warn ".env já existe — mantendo o atual"
fi

# --- 3. Ajustar .env para Docker ---
info "Ajustando .env para o ambiente Docker..."

set_env() {
    local key="$1" value="$2"
    if grep -q "^${key}=" .env; then
        sed -i "s|^${key}=.*|${key}=${value}|" .env
    else
        echo "${key}=${value}" >> .env
    fi
}

set_env "APP_NAME" "AgroVerde"
set_env "APP_URL" "http://localhost:8000"
set_env "DB_CONNECTION" "mysql"
set_env "DB_HOST" "db"
set_env "DB_PORT" "3306"
set_env "DB_DATABASE" "vetessence"
set_env "DB_USERNAME" "vetessence"
set_env "DB_PASSWORD" "secret"
set_env "CACHE_DRIVER" "redis"
set_env "QUEUE_CONNECTION" "redis"
set_env "SESSION_DRIVER" "redis"
set_env "REDIS_HOST" "redis"
set_env "REDIS_PORT" "6379"
set_env "MAIL_MAILER" "smtp"
set_env "MAIL_HOST" "mailpit"
set_env "MAIL_PORT" "1025"

# Branding AgroVerde
set_env "AGROVERDE_NAME" "AgroVerde"
set_env "AGROVERDE_PRIMARY_COLOR" "#2E7D32"

ok ".env ajustado"

# --- 4. Subir containers ---
info "Subindo containers (app, db, redis, mailpit, queue)..."
docker compose up -d
ok "Containers iniciados"

# --- 5. Aguardar o banco ---
info "Aguardando o banco de dados ficar pronto..."
for i in $(seq 1 30); do
    if docker compose exec -T db healthcheck.sh --connect --innodb_initialized &>/dev/null; then
        ok "Banco pronto"
        break
    fi
    if [ "$i" -eq 30 ]; then
        error "Banco não ficou pronto em 30 tentativas"
        exit 1
    fi
    sleep 2
done

# --- 6. Composer install ---
info "Instalando dependências (composer install)..."
docker compose exec -T app composer install --no-interaction --prefer-dist
ok "Dependências instaladas"

# --- 7. Key generate ---
info "Gerando APP_KEY..."
docker compose exec -T app php artisan key:generate --force
ok "APP_KEY gerada"

# --- 8. Migrations + seeders ---
info "Rodando migrations e seeders..."
docker compose exec -T app php artisan migrate --seed --force
ok "Banco populado"

# --- 9. Storage link ---
info "Criando storage link..."
docker compose exec -T app php artisan storage:link 2>/dev/null || warn "storage:link já existe"
ok "Storage link pronto"

# --- Fim ---
echo ""
echo "=========================================="
echo -e "  ${GREEN}Setup concluído!${NC}"
echo "=========================================="
echo ""
echo "  App:      http://localhost:8000"
echo "  Mailpit:  http://localhost:8025"
echo ""
echo "  Login (demo):"
echo "    super@vet.com / super123"
echo "    vet@vet.com   / vet123"
echo "    recep@vet.com / recep123"
echo ""
echo "  Comandos úteis:"
echo "    docker compose logs -f app     # logs"
echo "    docker compose exec app bash   # shell"
echo "    docker compose down            # parar"
echo ""
