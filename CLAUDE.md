# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Full-stack HR management system ("Gestión de Talento Humano") for the Consejo de Comunicación (Ecuador). Manages employees, attendance, permissions, vacations, vacation planning, vacation settlement/liquidation, and personnel actions (encargo/subrogación/ingreso/etc.).

- **Backend:** Laravel 12 (PHP 8.2+), PostgreSQL (`dbo` schema), Laravel Sanctum (token auth), DomPDF for PDF generation
- **Frontend:** Vue 3 (Composition API), Pinia, Vue Router 5, Tailwind CSS 4, Axios, Vite 7

## Development Commands

### Backend (`d:\rrhh\backend`)
```bash
php artisan serve           # Start API server at localhost:8000
php artisan migrate         # Run migrations
php artisan tinker          # REPL
composer test               # Run PHPUnit tests
```

### Frontend (`d:\rrhh\frontend`)
```bash
npm run dev                 # Start Vite dev server
npm run build               # Production build (required after any frontend change in prod)
```

### Run both together (from backend)
```bash
composer dev                # Runs artisan serve + queue + pail + vite concurrently
```

## Deployment Workflow

After any change, always tell the user:
1. **Push** (from dev machine)
2. **Pull** (on server)
3. **`npm run build`** in `frontend/` — **only when frontend files changed** (`.vue`, `.js`, `.css`)
4. No build needed for backend-only changes (PHP, Blade, migrations)

## Architecture

### Authentication
- Login via `POST /api/login` with `identificacion` (employee ID) + `password`
- Returns Sanctum Bearer token + employee data + roles + menu
- Auth guard uses `Empleado` model (table `dbo.ad_empleado`), not the default `User` model
- Frontend stores token in localStorage; Axios injects it on every request
- 401 response clears token and redirects to `/login`

### Role System
Five roles: `ADMINISTRADOR`, `TALENTO HUMANO`, `SUPERVISOR`, `ADQUISICIONES`, `BIENES`. Regular employees have no role.
- `ADQUISICIONES` and `BIENES` access `/adquisiciones/*` module via `AdqLayout.vue`
- Backend: checked via `DB::table('dbo.admin_usuario_rol')` joins in controllers (no Laravel policies/gates)
- Frontend: `auth.tieneRol('NOMBRE')` from Pinia store
- Menu items are filtered per role from `dbo.admin_opcion`

### Database Conventions
- HR tables use `dbo` schema; Adquisiciones tables use `adq` schema (same PostgreSQL DB)
- Employee statuses: `ACTIVO` / `INACTIVO` (soft deletes — never hard delete employees)
- Request statuses: `PENDIENTE`, `APROBADO`, `NEGADO`, `ELIMINADO`
- Department 999 (`id_depto = 999`) is excluded from all queries — it's a system/admin placeholder
- Primary key for employees is `id_emp` (string), not integer
- `dbo.d2_configuracion` stores institution-wide config values (key/value pairs)

### PDF Generation
Uses `barryvdh/laravel-dompdf`. All Blade PDF templates are in `backend/resources/views/reportes/`.
- Always embed images as base64 (`public_path()` + `base64_encode(file_get_contents())`) — direct file paths fail in dompdf
- Institution logo: `backend/public/logo.png` (transparent PNG)
- Margins must be set at `@page { margin: ... }` level, not on `.page` div — otherwise dompdf overflows the right edge
- Use `table-layout: fixed` on all tables to prevent overflow
- Spanish dates: use a manual `$meses` PHP array — do NOT use `Carbon::translatedFormat()` (locale may not be set)

### Alfresco Document Storage
Used for signed PDF uploads (vacation planning, personnel actions).
- Base URL: `http://192.168.26.38:8080/alfresco/api/-default-/public/alfresco/versions/1`
- Credentials: `admin / admin`, site: `talentohumano`
- Pattern: upload to `talentohumano/{module-folder}/{year}/`, save returned `entry.id` (node ID) to DB
- Helpers `getDocLibNodeId()` and `getOrCreateFolderNodeId()` are repeated in each controller that uses Alfresco (no shared service yet)

### Vacation Balance Calculation
Computed on-the-fly in `calcularSaldoDisponible()` / `calcularSaldo()`:
- Accrual rates: `LOSEP` → 2.50 days/month, `CODIGO DEL TRABAJO` → 1.25 days/month
- Formula: `(days since FECHA_CORTE_VACACIONES / 360) × (rate × 12)`
- If hire date is after cutoff date, use hire date as the base
- `FECHA_CORTE_VACACIONES` is stored in `dbo.d2_configuracion`
- Balance = `dias_adicionales` (initial/carried) + accrued − `total_dias_tomados`

### Key Controllers
| Controller | Responsibility |
|---|---|
| `AuthController` | Login / logout / me |
| `EmpleadoController` | Employee CRUD + role assignment |
| `AccionPersonalController` | Personnel actions (encargo, subrogación, ingreso, vacaciones, destitución, cesación) |
| `VacacionesController` | Vacation requests (approve/deny/balance) |
| `PlanificacionVacController` | Annual vacation planning |
| `LiquidacionVacController` | Vacation settlement for commissions/exits |
| `ReportePlanificacionController` | Planning PDF + Alfresco signed upload |
| `PermisosController` | Permission/leave requests |
| `AsistenciaController` | Attendance marking and reports |
| `CuadreController` | Attendance reconciliation |
| `DashboardController` | Dashboard stats |
| `Admin/*` | Departments, reasons, shifts, schedules, calendar, configuration, IESS contributions |
| `Adquisiciones/ArticuloController` | Inventory CRUD + image upload + stock alerts |
| `Adquisiciones/OrdenCompraController` | Ingresos de bienes (BORRADOR→RECIBIDO, updates stock + precio promedio) |
| `Adquisiciones/EgresoController` | Egresos de bienes (BORRADOR→DESPACHADO, decrements stock) |
| `Adquisiciones/ProveedorController` | Supplier CRUD |
| `Adquisiciones/IvaController` | IVA rate CRUD |

### Acciones de Personal Module
Table: `dbo.acc_accion_personal`. Supported types and their rules:

| Tipo | fecha_fin | Situación Actual | Situación Propuesta | Buscador Titular | Declaración Jurada | Auto-cierra |
|---|---|---|---|---|---|---|
| INGRESO | No aplica | Vacía (null) | Requerida — auto-llena desde ficha empleado | No | SI | No |
| ENCARGO | Opcional ("Hasta nueva orden" = null) | ✓ | Requerida | ✓ | NO APLICA | No (manual) |
| SUBROGACION | Requerida | ✓ | Requerida | ✓ | NO APLICA | Sí (al vencer fecha_fin) |
| VACACIONES | Requerida | ✓ | No aplica | No | NO APLICA | Sí (al vencer fecha_fin) |
| DESTITUCION | No aplica | ✓ | No aplica | No | SI | No |
| CESACION DE FUNCIONES | No aplica | ✓ | No aplica | No | SI | No |

- Auto-cierre: corre en cada llamada a `index()` para SUBROGACION y VACACIONES cuya `fecha_fin < today`
- Signed PDF stored as Alfresco node ID in `pdf_firmado` column
- Workflow: INGRESO → create employee first, then action; DESTITUCION/CESACION → action first, then deactivate employee

### Vacation Liquidation Module (`LiquidacionVacController`)
Handles special events that require freezing/certifying vacation balances:
- `INICIO_COMISION` / `FIN_COMISION_SALIDA` → generate certificate PDF (employee leaving on commission)
- `FIN_COMISION_RETORNO` / `COMISION_ENTRANTE` → load balance from external certificate
- `DESVINCULACION` → liquidation report for employees leaving the institution
- Valid motives per employee depend on `modalidad_laboral` field
- Estado requerido per motive defined in `ESTADO_REQUERIDO` constant

### Frontend Structure
```
frontend/src/
  router/index.js           # Routes with meta.requiresAuth / meta.rol guards
  stores/auth.js            # Pinia: token, empleado, roles, menu (localStorage)
  services/api.js           # Axios instance (base URL from VITE_API_URL)
  layouts/MainLayout.vue    # HR module layout
  layouts/AdqLayout.vue     # Adquisiciones module layout (green sidebar, roles ADQUISICIONES/BIENES)
  views/acciones/           # Acciones de Personal (list + form)
  views/planificacion/      # Vacation planning, liquidation, report
  views/empleados/          # Employee CRUD, detail, import, distributivo
  views/admin/              # Admin panel (roles, departments, shifts, config, etc.)
  views/adquisiciones/      # Adquisiciones module (articulos, ingresos, egresos, proveedores, IVA)
```

### Adquisiciones Module

**Schema:** `adq.*` tables. Key tables:
- `adq.articulo` — inventory items; `precio_unitario DECIMAL(10,4)`, `stock_actual`, `iva_id`
- `adq.orden_compra` / `adq.orden_compra_det` — ingresos (BORRADOR→RECIBIDO)
- `adq.egreso` / `adq.egreso_det` — egresos (BORRADOR→DESPACHADO)
- `adq.iva` — IVA rates (e.g. 15%, 0%)
- `adq.catalogo_inventario` — MEF catalog (nivel1/nivel2/item_presupuestario)

**Price rules:**
- All monetary calculations use `round(..., 4)` — 4 decimal places throughout (subtotal, iva_valor, total_linea, totals)
- On ingreso confirm: `precio_unitario = (old == 0) ? new : round((old + new) / 2, 4)` (weighted average)
- On egreso confirm: if `stock_actual` reaches 0, set `precio_unitario = 0`; on reversal restore price only if current price is still 0 and `precio_anterior > 0`
- Frontend `fmt()` and `recalcularLinea()` also use 4-decimal rounding (`Math.round(... * 10000) / 10000`)

**Stock updates:** Always use `DB::table()->update(['stock_actual' => DB::raw('stock_actual + N')])` — never Eloquent `$model->update()` for stock (unreliable with schema-prefixed tables in PostgreSQL).

**Routes:** All Adquisiciones API routes are under `/api/adquisiciones/*` in `routes/api.php`.

**Images:** Article images stored via `Storage::disk('public')` in `articulos/` folder. Run `php artisan storage:link` once after deploy. URL returned as `imagen_url` in `articulos` API response.

### Environment
- `VITE_API_URL` in `frontend/.env` sets the API base URL (default: `http://localhost:8000/api`)
- Backend `.env` must have `DB_CONNECTION=pgsql` and correct DB credentials
- Session and cache drivers are `database`
