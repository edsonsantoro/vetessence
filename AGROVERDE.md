# AGROVERDE.md — Guia do Fork

> **Este é um fork do [VetEssence](https://github.com/hlmitecnologia/vetessence) mantido pela AgroVerde.**
>
> O objetivo é customizar o sistema para a clínica **sem perder a capacidade de receber atualizações do repositório original**.

---

## 1. Remotes

| Remote | URL | Papel |
|--------|-----|-------|
| `origin` | `github.com/edsonsantoro/vetessence` | Seu fork (onde você commita) |
| `upstream` | `github.com/hlmitecnologia/vetessence` | Repositório original (fonte de atualizações) |

```bash
git remote -v
```

---

## 2. Branches

| Branch | Papel | Regra |
|--------|-------|-------|
| `main` | Espelho do upstream | **NUNCA commitar direto** |
| `agroverde` | Customizações AgroVerde | Todo o trabalho acontece aqui |

---

## 3. Sincronização com o Upstream

**Frequência recomendada:** semanal, ou antes de cada sprint.

```bash
# 1. Buscar atualizações do original
git fetch upstream

# 2. Atualizar o espelho (main)
git checkout main
git merge upstream/main --ff-only
git push origin main

# 3. Trazer para o branch de trabalho
git checkout agroverde
git rebase main        # ou: git merge main

# 4. Resolver conflitos (se houver) e continuar
```

---

## 4. Estratégia de 3 Camadas

> **Regra de ouro:** 80% do trabalho em arquivos novos, 15% em overlay de views, 5% em modificações mínimas no core.

### 🟢 Camada A — Arquivos Novos (ZERO conflito)

Tudo que é novo vai em pastas próprias:

| Tipo | Onde |
|------|------|
| Models | `app/Models/` (prefixo `Pet*` ou em `Agroverde/`) |
| Controllers | `app/Http/Controllers/Agroverde/` |
| Livewire | `app/Livewire/Agroverde/` |
| Views | `resources/views/agroverde/` |
| Migrations | `database/migrations/agroverde/` |
| Rotas | `routes/agroverde.php` |
| Config | `config/agroverde.php` |
| Observers | `app/Observers/Agroverde/` |
| Listeners | `app/Listeners/Agroverde/` |

### 🟢 Camada B — Overlay de Views (ZERO conflito)

Views em `resources/views/agroverde/` **sobrescrevem** as do core, sem tocar nos originais.

Configurado em `config/view.php`:

```php
'paths' => [
    resource_path('views/agroverde'),  // ← custom primeiro
    resource_path('views'),             // ← core fallback
],
```

**Exemplo:**

```
resources/views/agroverde/pets/show.blade.php
→ sobrescreve resources/views/pets/show.blade.php
```

⚠️ **Atenção:** se o upstream mudar a view original, nossa versão não recebe a mudança automaticamente. Revisar diffs a cada sincronização.

### 🟡 Camada C — Modificações Mínimas no Core (BAIXO conflito)

Modificações pontuais, sempre isoladas e comentadas:

| Arquivo | Modificação | Marcador |
|---------|-------------|----------|
| `config/view.php` | Overlay de views | comentário |
| `config/app.php` | Registro do provider | `// === AGROVERDE (fork) ===` |
| `app/Models/Pet.php` | `use HasAgroverdeFields;` | 1 linha |
| `routes/web.php` | Bloco de rotas | `// === AGROVERDE ===` |
| `database/seeders/PermissionSeeder.php` | Bloco de permissões | `// === AGROVERDE ===` |

---

## 5. O Que NUNCA Fazer

| ❌ Nunca | Por quê |
|---------|---------|
| Commitar no branch `main` | Perde o espelho do upstream |
| Modificar `pets/show.blade.php` diretamente | Use overlay (Camada B) |
| Modificar migrations existentes | Quebra o histórico — crie novas |
| Modificar `Pet.php` extensivamente | Use trait (Camada C) |
| Misturar código AgroVerde com o core | Impossível separar depois |
| Editar `PLAN.md` / `AGENTS.md` do autor | São do upstream — documente em `AGROVERDE.md` |

---

## 6. Estrutura de Pastas AgroVerde

```
app/
├── Http/Controllers/Agroverde/     # Controllers customizados
├── Livewire/Agroverde/             # Componentes Livewire
├── Models/Concerns/                # Traits (HasAgroverdeFields)
├── Observers/Agroverde/            # Observers
├── Listeners/Agroverde/            # Listeners
└── Providers/AgroverdeServiceProvider.php

config/agroverde.php                # Config próprio
routes/agroverde.php                # Rotas próprias
resources/views/agroverde/          # Overlay de views
database/migrations/agroverde/      # Migrations próprias
```

---

## 7. Módulos AgroVerde

| Módulo | Status | Feature flag |
|--------|--------|-------------|
| Patologias | ⏳ Planejado | `agroverde.modules.pathologies` |
| Documentos | ⏳ Planejado | `agroverde.modules.documents` |
| Fotos | ⏳ Planejado | `agroverde.modules.photos` |
| Observações | ⏳ Planejado | `agroverde.modules.observations` |
| Vídeos | ⏳ Planejado | `agroverde.modules.videos` |
| Templates de prescrição | ⏳ Planejado | `agroverde.modules.prescription_templates` |
| Tela consolidada do pet | ⏳ Planejado | `agroverde.modules.consolidated_pet_view` |
| Wizard de atendimento | ⏳ Planejado | `agroverde.modules.attendance_wizard` |

---

## 8. Variáveis de Ambiente

```env
# Branding
AGROVERDE_NAME="AgroVerde"
AGROVERDE_PRIMARY_COLOR="#2E7D32"

# Overlay de views
AGROVERDE_VIEW_OVERLAY=true

# Módulos (feature flags)
AGROVERDE_MODULE_PATHOLOGIES=true
AGROVERDE_MODULE_DOCUMENTS=true
AGROVERDE_MODULE_PHOTOS=true
AGROVERDE_MODULE_OBSERVATIONS=true
AGROVERDE_MODULE_VIDEOS=true
AGROVERDE_MODULE_PRESCRIPTION_TEMPLATES=true
AGROVERDE_MODULE_CONSOLIDATED_PET_VIEW=true
AGROVERDE_MODULE_ATTENDANCE_WIZARD=true
```

---

## 9. Ambiente de Desenvolvimento (Docker)

Ambiente Docker pronto para desenvolvimento, com configurações de performance
baseadas em `docs/performance.md`.

### Stack

| Serviço | Imagem | Porta | Papel |
|---------|--------|-------|-------|
| `app` | `agroverde/php:8.4` (build local) | **8080** | PHP 8.4 FPM + Nginx |
| `db` | `mariadb:10.11` | 3306 | Banco de dados |
| `redis` | `redis:7-alpine` | 6379 | Cache, queue, session |
| `mailpit` | `axllent/mailpit` | 8025 (UI), 1025 (SMTP) | Captura de e-mails |
| `queue` | `agroverde/php:8.4` | — | Worker de filas |

> **Nota:** a porta padrão é **8080** (a 8000 está ocupada por outro serviço nesta máquina).

### Setup

```bash
./docker/setup.sh
```

### Comandos

```bash
docker compose up -d              # subir
docker compose down               # parar
docker compose logs -f app        # logs
docker compose exec app bash      # shell
docker compose exec app php artisan test
```

### Imagem customizada

`docker/php/Dockerfile` estende `serversideup/php:8.4-fpm-nginx` com as
extensões que o VetEssence requer e que não vêm na base:

- `gd` (simple-qrcode, intervention/image)
- `bcmath` (cálculos financeiros)
- `intl` (formatação i18n)
- `exif` (metadados de imagens)

### Performance

Configurações aplicadas (de `docs/performance.md`):

| Arquivo | Config |
|---------|--------|
| `docker/php/99-agroverde.ini` | OPcache 256M, memory_limit 256M, uploads 64M |
| `docker/mariadb/99-agroverde.cnf` | innodb_buffer_pool_size 512M, slow query log |

### Limites de Recursos

| Serviço | CPU (limit) | Memória (limit) |
|---------|-------------|-----------------|
| `app` | 2.0 | 1 GB |
| `db` | 2.0 | 1 GB |
| `queue` | 1.0 | 512 MB |
| `redis` | 0.5 | 256 MB |
| `mailpit` | 0.5 | 128 MB |
| **Total** | **6.0** | **2.9 GB** |

> Em produção o stack inteiro dorme após 2h sem acesso (Sablier), então esse
> teto raramente é atingido — os 6.0 CPU só valem no pico de uso.

### Logging (3 camadas)

| Camada | Onde | Config |
|--------|------|--------|
| Daemon | `/etc/docker/daemon.json` | json-file, 50m × 3, compress |
| Compose | `docker-compose.yml` | json-file, 10m × 3, compress |
| Logrotate | `/etc/logrotate.d/docker-containers.conf` | diário, size 50M, rotate 3 |

Nenhum container do stack publica porta no host — o acesso vem pelo nginx
compartilhado (`wp-nginx`) via DNS na rede `wordpress_wp-network`. Ver seção 10.

Logs da aplicação: `LOG_CHANNEL=daily` (14 dias) + logrotate opcional
(`sudo ./docker/logrotate/install.sh`).

Ver `docker/README.md` para detalhes completos.

---

## 10. Deploy em produção (VPS `vmi3319126`)

App no ar em **https://vet.delsantoro.com.br** desde 2026-09-30.

### Topologia

```
Internet → Cloudflare (laranja) → wp-nginx:443
                                    ├─ auth_basic  ── 401 sem credencial (0,1s)
                                    └─ auth_request ── Sablier acorda a stack
                                                         └─ agroverde-vet-app:8080
```

Não existe porta publicada no host para nenhum container do stack. O único
caminho de entrada é o vhost `vet.conf` dentro do nginx compartilhado.

### Controle de acesso

Duas camadas, nesta ordem:

1. **HTTP Basic** — `~/wordpress/nginx/conf.d/vet.htpasswd`
2. **Sablier** — só acorda a stack depois que a senha passou

A ordem importa: `auth_basic` roda na fase ACCESS antes do `auth_request`,
então um scanner da internet sem a credencial leva 401 **antes** de o Sablier
ser chamado — não desperta nada e não gasta CPU da VPS. Medido: 401 em 0,1s
com o stack inteiro parado; com credencial, 10,9s até o 200.

O 401 não entra no `error_page 500 502 503 504`, então a senha continua valendo
mesmo com o Sablier fora do ar — e o `@fallback_direct` repete o `auth_basic`
de propósito, senão viraria porta aberta.

Trocar a credencial:

```bash
htpasswd -c ~/wordpress/nginx/conf.d/vet.htpasswd <usuario>
docker exec wp-nginx nginx -s reload
```

> O arquivo precisa ser `644`. Com `640` o worker do nginx leva
> `open() ... failed (13: Permission denied)` e **tudo** vira 500 — inclusive
> o caminho sem senha, que deixa de parecer 401. Mesmo modo do
> `agroverde.htpasswd` existente.

| Peça | Onde |
|------|------|
| Vhost | `~/wordpress/nginx/conf.d/vet.conf` (bind mount em `/etc/nginx/conf.d`) |
| Certificado | `~/wordpress/ssl/vet/` (Let's Encrypt, DNS-01 via Cloudflare) |
| DNS | registro A `vet` → `89.117.145.37`, proxied |
| Sablier | container `sablier`, IP fixo `172.18.0.250` |

### Containers

Todos com `sablier.group=agroverde-vet`:

| Container | Função | Redes |
|---|---|---|
| `agroverde-vet-app` | Laravel (nginx interno :8080) | `agroverde`, `shared` |
| `agroverde-vet-queue` | `queue:work` | `agroverde`, `shared` |
| `agroverde-vet-db` | MariaDB 10.11 | `agroverde` |
| `agroverde-vet-redis` | cache/fila/sessão | `agroverde` |
| `agroverde-vet-mailpit` | captura de e-mail | `agroverde` |

### Regras de nome

> **`agroverde-app` NÃO é nosso.** É o middleware de webhooks Bradial
> (`~/projects/agroverde/middleware/docker-compose.vps.yml`). Ele está na mesma
> rede `wordpress_wp-network` e é resolvido por `agroverde.conf` e
> `agroverde-hook.conf`. Reutilizar esse nome quebra os dois.

O prefixo do fork é `agroverde-vet-`.

### Ciclo de vida (Sablier)

1. Request chega em `vet.delsantoro.com.br`
2. Basic auth valida a credencial (401 se não tiver)
3. `auth_request /_sablier_vet` chama a estratégia *blocking* do Sablier
4. Sablier acorda os 5 containers e segura a resposta até ficarem prontos
5. Resposta servida; sessão renewada a cada request
6. Após **2h sem request**, o Sablier derruba o grupo → consumo zero

Medido: wake completo em **~11s** (MariaDB é o gargalo, `start_period: 30s`).

> Verificado: o caminho de *wake* foi testado ponta a ponta. O *sleep* após 2h
> segue a configuração (`session_duration=2h`) mas ainda não foi observado
> completar um ciclo inteiro.

Se o Sablier estiver fora do ar, o `error_page 500 502 503 504 = @fallback_direct`
deixa a request passar direto — o site não cai junto.

### Operação

```bash
cd ~/projects/vetessence

docker compose ps                      # estado
docker compose logs -f app             # logs
docker compose exec app php artisan ...# comandos
docker compose exec db mariadb -u vetessence -psecret vetessence

curl -sI https://vet.delsantoro.com.br/login   # acorda a stack
```

Para acordar/dormir na mão:

```bash
docker stop agroverde-vet-app agroverde-vet-db agroverde-vet-redis \
           agroverde-vet-queue agroverde-vet-mailpit
curl -sI https://vet.delsantoro.com.br/login   # volta sozinha
```

### Gotchas descobertos no deploy

**`proxy_pass` literal quebra com Sablier.** O IP do container muda a cada
start. Com `proxy_pass http://agroverde-vet-app:8080;` o nginx resolve uma
vez só, no boot, e passa a apontar para o container errado. Usar variável:

```nginx
resolver 127.0.0.11 valid=30s ipv6=off;
set $upstream http://agroverde-vet-app:8080;
proxy_pass $upstream;
```

**Container parado trava `nginx -t` em todo o VPS.** `agroverde-hook.conf`
apontava para `agroverde-app` com `proxy_pass` literal; com o middleware
parado, *qualquer* `nginx -s reload` falhava com `host not found in upstream`.
Resolvido aplicando o mesmo padrão `resolver` + `$upstream` nesses dois
arquivos. Backups: `agroverde.conf.bak-20260930-*`, `agroverde-hook.conf.bak-*`.

**Laravel precisa confiar no proxy.** Sem isso ele responde com `http://` e o
navegador entra em loop de redirect. Resolvido na camada A do fork —
`app/Http/Middleware/AgroVerdeTrustProxies.php`, com bind no
`AgroverdeServiceProvider`. O arquivo `TrustProxies.php` do core ficou intocado.

**O certificado `mycert.pem` da VPS não é wildcard.** Cobre apenas
`api.appcodrive.com`, `menkyou` e `social` — apesar de Many vhost apontarem
para ele. Cada subdomínio novo precisa do próprio certificado.

**`branch_id` é obrigatório em dados criados por CLI.** `Appointment`,
`Invoice`, `MedicalRecord` e `ParasiteControl` usam o trait `BranchScoped`,
que aplica global scope `where branch_id = <filial do usuário logado>`. Por
CLI o `BranchContext` está vazio, o trait não preenche a coluna e tudo fica
`NULL` — invisível para o dashboard. Ver `AgroVerdeDemoSeeder`.

**`paid_at` é obrigatório para faturamento.** O card "Receita do Mês" soma por
`whereMonth('paid_at')`. Fatura com `status = paid` e `paid_at` nulo não entra.

### Contas de demonstração

Definidas em `database/seeders/UserSeeder.php`. As senhas padrão já foram
trocadas na VPS — os valores abaixo são só o que está no código:

| Papel | E-mail | Senha no código |
|---|---|---|
| Super admin | `super@vet.com` | `super123` |
| Admin | `admin@vet.com` | `admin123` |
| Veterinário | `vet@vet.com` | `vet123` |
| Recepcionista | `recep@vet.com` | `recep123` |

Dados de volume realista: `php artisan db:seed --class=AgroVerdeDemoSeeder --force`

### Migração do SimplesVet (ERP)

Os dados extraídos em `~/projects/agroverde/svapi/` vêm do **SimplesVet**
(`api.simples.vet/app/v3/...`) — é o ERP da clínica. Não confundir com o
**Bradial**, que é o sistema de atendimento por WhatsApp e conversa com o
middleware de webhook (`~/projects/agroverde/middleware`).

Estado atual da extração:

| Recurso | Situação |
|---|---|
| Clientes/tutores | ✅ 9.928 (9.380 com CPF, 9.912 com telefone, 0 faltantes) |
| Espécies / raças / vacinas (catálogo) | ✅ extraído |
| Pets | ❌ não extraído |
| Agenda / histórico / financeiro | ❌ não extraído |

Sem pets, a recepção não consegue cadastrar atendimento — é o gargalo para
avançar na migração.

---

## 11. Importação do SimplesVet e paginação

### 11.1 Dados importados

O ERP é o **SimplesVet** (`api.simples.vet`), não a Bradial (que é o
atendimento por WhatsApp). A extração vive em `~/projects/agroverde/svapi/`;
o importador lê o JSON **local** — não toca na produção.

```bash
cp ~/projects/agroverde/svapi/clientes_completos.json database/data/
docker compose exec app php artisan agroverde:import-clientes --dry-run
docker compose exec app php artisan agroverde:import-clientes
```

| | Origem | No banco |
|---|---|---|
| Tutores | 9.928 | 9.933 (5 do demo) |
| Pets | 16.202 | 16.212 (10 do demo) |
| Vínculos | 16.202 | 16.212 |

**Idempotente**: o upsert é pela coluna `simplesvet_chave` (unique, migration
`2026_10_01_000001`). Reexecutar atualiza, não duplica — verificado.

Essa coluna não é enfeite: `/v1/calendar/appointments` devolve `customer.key`
e `animal.key`, que são exatamente essa chave. Importar a agenda depois é só
rodar o processo de novo, e o vínculo fecha.

**Fuso**: o ERP opera em `America/Manaus` (clínica em Sinop-MT). O container
está em `America/Sao_Paulo` — divergência pendente de decisão.

### 11.2 Camada C — edições no core (conhecidas, pequenas)

Estratégia de 3 camadas: A = arquivo novo, B = overlay de view, C = edição
mínima no core. Tudo que está aqui é C, com o motivo anotado no código.

| Arquivo | Mudança | Por quê |
|---|---|---|
| `app/Http/Controllers/PetController.php` | `get()` → `paginate(50)->appends()` | 16 mil pets estouravam o PHP |
| `app/Http/Controllers/TutorController.php` | idem | 10 mil tutores |
| `resources/views/pets/index.blade.php` | `data-server-paginated` + rodapé de paginação | ver §11.3 |
| `resources/views/tutors/index.blade.php` | idem | idem |
| `resources/views/layouts/adminlte.blade.php` | 1 linha: pula DataTables em tabela marcada | ver §11.3 |

### 11.3 A briga com o DataTables

O `layouts.adminlte` aplica **DataTables (jQuery) em toda `table.table-bordered`**
— ordenação, busca e "N por página" rodam no navegador. Com paginação de
servidor isso vira paginação dupla, e a busca do DataTables só enxerga a
página atual: pior do que não ter busca numa tabela de 16 mil linhas.

Solução: atributo `data-server-paginated` no card, e uma linha no layout que
pula essas tabelas. Escolha por **edição mínima** em vez de copiar a view
inteira para o overlay: uma linha de JS se resolve, 114 linhas copiadas
divergem do upstream na primeira atualização.

### 11.4 Armadilhas do Laravel 10 neste app

**`->withQueryString()` na View quebra.** É método da `Response`. Chamado na
View cai no `__call` mágico, vira `with('queryString', $parameters[0])` e morre
com `Undefined array key 0`. Usar `->appends($request->query())` no paginator.

**Paginação é Tailwind por padrão.** `Paginator::$defaultView` é
`pagination::tailwind` nesta versão, e `defaultUseBootstrapFour()` não existe.
Por isso: view nossa em `resources/views/vendor/pagination/bootstrap-4.blade.php`
+ `Paginator::defaultView('pagination::bootstrap-4')` no
`AgroverdeServiceProvider`. Setar no provider (camada A) e não em
`AppServiceProvider` (core) mantém o merge limpo.

### 11.5 Pendente: 28 outras listagens

**30 dos 87 controllers fazem `->get()` sem paginar.** Hoje só `pets` e
`tutors` quebram, porque são as únicas com volume real — as outras tabelas
têm só o demo. Conforme entrarem vendas (`/v3/comercial/vendas`) e agenda
(`/v1/calendar/appointments`), as próximas a estourar são previsíveis:
`invoices`, `appointments`, `medical_records`, `vaccination_reminders`.

A correção é mecânica e segue o mesmo padrão de §11.2.

---

## 12. Referências

- `PLAN.md` — Build plan do upstream (não editar)
- `AGENTS.md` — Convenções do upstream (não editar)
- `docs/performance.md` — Guia de otimização (upstream)
- `docker/README.md` — Ambiente Docker
- `docs/plans/0009-estrategia-fork-upstream.md` — Estratégia detalhada (no repo agroverde)
- `~/projects/agroverde/docs/deploy-vps.md` — deploy do middleware webhook
