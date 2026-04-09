# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Full-stack HR management system ("Gestión de Talento Humano") for the Consejo de Comunicación (Ecuador). Manages employees, attendance, permissions, vacations, vacation planning, and vacation settlement/liquidation.

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
npm run build               # Production build
```

### Run both together (from backend)
```bash
composer dev                # Runs artisan serve + queue + pail + vite concurrently
```

## Architecture

### Authentication
- Login via `POST /api/login` with `identificacion` (employee ID) + `password`
- Returns Sanctum Bearer token + employee data + roles + menu
- Auth guard uses `Empleado` model (table `dbo.ad_empleado`), not the default `User` model
- Frontend stores token in localStorage; Axios injects it on every request
- 401 response clears token and redirects to `/login`

### Role System
Three roles: `ADMINISTRADOR`, `TALENTO HUMANO`, `SUPERVISOR`. Regular employees have no role.
- Backend: checked via `DB::table('dbo.admin_usuario_rol')` joins in controllers (no Laravel policies/gates)
- Frontend: `auth.tieneRol('NOMBRE')` from Pinia store
- Menu items are filtered per role from `dbo.admin_opcion`

### Database Conventions
- All tables use `dbo` schema prefix (PostgreSQL)
- Employee statuses: `ACTIVO` / `INACTIVO` (soft deletes — never hard delete employees)
- Request statuses: `PENDIENTE`, `APROBADO`, `NEGADO`, `ELIMINADO`
- Department 999 (`id_depto = 999`) is excluded from all queries — it's a system/admin placeholder
- Primary key for employees is `id_emp` (string), not integer

### Vacation Balance Calculation
Computed on-the-fly in `calcularSaldoDisponible()` / `calcularSaldo()`:
- Accrual rates: `LOSEP` → 2.50 days/month, `CODIGO DEL TRABAJO` → 1.25 days/month
- Formula: `(days since FECHA_CORTE_VACACIONES / 360) × (rate × 12)`
- If hire date is after cutoff date, use hire date as the base
- `FECHA_CORTE_VACACIONES` is stored in `dbo.d2_configuracion`
- Balance = `dias_adicionales` (initial/carried) + accrued − `total_dias_tomados`

### PDF Generation
Uses `barryvdh/laravel-dompdf`. All Blade PDF templates are in `backend/resources/views/reportes/`.
- Always embed images as base64 (use `public_path()` + `base64_encode(file_get_contents())`) — direct file paths are unreliable in dompdf
- Institution logo: `backend/public/logo.png` (transparent PNG)
- Approver name comes from `dbo.d2_configuracion` key `APROBADOR_INST_VACACION`

### Key Controllers
| Controller | Responsibility |
|---|---|
| `AuthController` | Login / logout / me |
| `EmpleadoController` | Employee CRUD + role assignment |
| `VacacionesController` | Vacation requests (approve/deny/balance) |
| `PlanificacionController` | Annual vacation planning |
| `LiquidacionVacController` | Vacation settlement for commissions/exits |
| `PermisosController` | Permission/leave requests |
| `AsistenciaController` | Attendance marking and reports |
| `DashboardController` | Dashboard stats |
| `ReportePlanificacionController` | Planning PDF + signed upload |

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
  router/index.js       # Routes with meta.requiresAuth / meta.rol guards
  stores/auth.js        # Pinia: token, empleado, roles, menu (localStorage)
  services/api.js       # Axios instance (base URL from VITE_API_URL)
  layouts/MainLayout.vue
  views/                # One folder per module
  components/           # Shared components
```

### Environment
- `VITE_API_URL` in `frontend/.env` sets the API base URL (default: `http://localhost:8000/api`)
- Backend `.env` must have `DB_CONNECTION=pgsql` and correct DB credentials
- Session and cache drivers are `database`
