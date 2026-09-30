#!/usr/bin/env bash
#
# AgroVerde — Instalação da política de logrotate
#
# Instala /etc/logrotate.d/agroverde para rotacionar os logs da aplicação
# (storage/logs/*.log).
#
# Os logs do Docker já são cobertos por /etc/logrotate.d/docker-containers.conf
# (política do sistema) + limites do daemon e do compose.
#
# Requer sudo.
#
# Uso: sudo ./docker/logrotate/install.sh
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

# --- Verificar root ---
if [ "$(id -u)" -ne 0 ]; then
    error "Este script precisa de sudo."
    echo "  Uso: sudo $0"
    exit 1
fi

# --- Detectar o caminho do projeto ---
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_PATH="$(cd "$SCRIPT_DIR/../.." && pwd)"

info "Projeto: $PROJECT_PATH"

if [ ! -d "$PROJECT_PATH/storage/logs" ]; then
    error "Diretório storage/logs não encontrado em $PROJECT_PATH"
    exit 1
fi

# --- Detectar o usuário/grupo do container (www-data = uid 33) ---
LOG_OWNER="www-data"
LOG_GROUP="www-data"

# --- Gerar o arquivo de configuração ---
CONF_FILE="/etc/logrotate.d/agroverde"

info "Gerando $CONF_FILE..."

cat > "$CONF_FILE" <<EOF
# ---------------------------------------------------------------------------
# AgroVerde — Política de logrotate
#
# Gerado automaticamente por docker/logrotate/install.sh
# Projeto: $PROJECT_PATH
#
# Rotaciona:
#   1. Logs da aplicação Laravel (storage/logs/*.log)
#
# Os logs do Docker são cobertos por /etc/logrotate.d/docker-containers.conf.
#
# Testar: sudo logrotate -d /etc/logrotate.d/agroverde
# Forçar: sudo logrotate -f /etc/logrotate.d/agroverde
# ---------------------------------------------------------------------------

# --- 1. Logs da aplicação Laravel ---
# O Laravel usa LOG_CHANNEL=daily (rotação própria de 14 dias).
# Esta política é um fallback para logs que escapam do canal daily
# (ex: laravel.log legado, logs de pacotes).
$PROJECT_PATH/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0664 $LOG_OWNER $LOG_GROUP
    sharedscripts
    dateext
    dateformat -%Y-%m-%d
    su $LOG_OWNER $LOG_GROUP
}

# --- 2. Logs do Docker ---
# NÃO incluídos aqui: já são cobertos por /etc/logrotate.d/docker-containers.conf
# (política do sistema que cobre todos os containers, incluindo agroverde-*).
#
# Camadas de proteção dos logs do Docker:
#   1. Daemon:    json-file max-size 50m, max-file 3 (global, /etc/docker/daemon.json)
#   2. Compose:   json-file max-size 10m, max-file 3, compress (por container)
#   3. Logrotate: /etc/logrotate.d/docker-containers.conf (diário, size 50M, rotate 3)
#
# Duplicar a política aqui causaria conflito (dois logrotate no mesmo arquivo).
EOF

ok "Arquivo criado: $CONF_FILE"

# --- Validar a configuração ---
info "Validando a configuração (dry-run)..."
if logrotate -d "$CONF_FILE" >/dev/null 2>&1; then
    ok "Configuração válida"
else
    warn "logrotate reportou avisos — verifique com: sudo logrotate -d $CONF_FILE"
fi

# --- Verificar o timer do systemd ---
echo ""
info "Verificando o timer do logrotate..."
if systemctl list-timers logrotate.timer --no-pager 2>/dev/null | grep -q logrotate; then
    ok "logrotate.timer ativo"
    systemctl list-timers logrotate.timer --no-pager 2>/dev/null | head -2
else
    warn "logrotate.timer não encontrado — verifique o cron do sistema"
fi

# --- Resumo ---
echo ""
echo "=========================================="
echo -e "  ${GREEN}Logrotate instalado!${NC}"
echo "=========================================="
echo ""
echo "  Config:  $CONF_FILE"
echo "  Logs:    $PROJECT_PATH/storage/logs/*.log"
echo ""
echo "  Logs do Docker: já cobertos por /etc/logrotate.d/docker-containers.conf"
echo ""
echo "  Comandos:"
echo "    sudo logrotate -d /etc/logrotate.d/agroverde   # dry-run"
echo "    sudo logrotate -f /etc/logrotate.d/agroverde   # forçar rotação"
echo "    sudo logrotate -v /etc/logrotate.d/agroverde   # verbose"
echo ""
