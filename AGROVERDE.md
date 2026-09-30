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

## 9. Referências

- `PLAN.md` — Build plan do upstream (não editar)
- `AGENTS.md` — Convenções do upstream (não editar)
- `docs/plans/0009-estrategia-fork-upstream.md` — Estratégia detalhada (no repo agroverde)
