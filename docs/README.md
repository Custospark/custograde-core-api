# Custograde Backend Docs

## Workspace map

Custograde ships as two tracked repos sharing one Laravel API:

```text
custograde-core-api (this repo)
  Backend/ equivalent root
  docs/requirements/   Requirements spec (.docx source of truth, .pdf snapshot, requirements.md mirror)
  docs/entities.md     Per-entity build log (Quill appends - see AGENTS.md)
  docs/decisions.md    Architecture decision records
custograde-web-desktop (frontend repo)
  Frontend/ equivalent root
  docs/product/design-system.md   UI source of truth
```

## Requirements

- Source of truth: `docs/requirements/Custograde_Requirements_Document.docx` (v1.1, 1 Oct 2026)
- PDF snapshot: `docs/requirements/Custograde_Requirements_Document.pdf` (same version, for sharing)
- Searchable mirror: `docs/requirements/requirements.md` (regenerate if the .docx changes)

## Run - Backend

```bash
composer install
php artisan serve            # http://localhost:8000
php artisan route:list       # verify api/v1/health
composer vera:fast           # gate before every commit
```

## Run - Frontend (custograde-web-desktop repo)

```bash
npm install
npm run dev                  # Vite (:5173) + Electron together
npm run vera:fast            # gate before every commit
```

Env: Frontend `.env` holds `VITE_API_URL=http://localhost:8000/api/v1`.

## Agent workflow

See `AGENTS.md` at the repo root (Mike orchestrator, Vera gates, entity order).
