# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Sistema de Gestión para el Consejo de Comunicación (Ecuador) con dos módulos:
1. **Talento Humano** — empleados, asistencia, permisos, vacaciones, acciones de personal
2. **Adquisiciones/Bienes** — inventario, ingresos, egresos, kardex, reportes

- **Backend:** Laravel 12 (PHP 8.4), PostgreSQL, Laravel Sanctum, DomPDF
- **Frontend:** Vue 3 (Composition API), Pinia, Vue Router 5, Tailwind CSS 4, Axios, Vite 7

## Development Commands

```bash
# Backend (d:\rrhh\backend)
php artisan serve       # API en localhost:8000
php artisan migrate     # Ejecutar migraciones

# Frontend (d:\rrhh\frontend)
npm run dev             # Dev server
npm run build           # Build producción (solo si cambiaron .vue/.js/.css)

# Ambos juntos (desde backend)
composer dev
```

## Deployment

Después de cualquier cambio: Push → Pull en servidor → `npm run build` (solo si hay cambios frontend) → `php artisan migrate` (solo si hay nuevas migraciones).

---

## Autenticación

- Login: `POST /api/login` con `identificacion` + `password`
- Guard usa modelo `Empleado` (tabla `dbo.ad_empleado`), no el `User` de Laravel
- Token Sanctum en localStorage; Axios lo inyecta en cada request
- 401 → limpia token y redirige a `/login`

## Roles

Cinco roles: `ADMINISTRADOR`, `TALENTO HUMANO`, `SUPERVISOR`, `ADQUISICIONES`, `BIENES`. Empleados sin rol = acceso básico.
- Backend: `DB::table('dbo.admin_usuario_rol')` — sin Laravel policies/gates
- Frontend: `auth.tieneRol('NOMBRE')` desde Pinia store
- Menú filtrado por rol desde `dbo.admin_opcion`

## Base de Datos

- Schema `dbo` → Talento Humano | Schema `adq` → Adquisiciones (misma BD PostgreSQL)
- Empleados: PK = `id_emp` (string); estados `ACTIVO`/`INACTIVO` (nunca eliminar)
- Depto 999 excluido de todas las consultas (placeholder de sistema)
- `dbo.d2_configuracion` → parámetros globales (clave/valor)
- Stock: siempre usar `DB::table()->update(['stock_actual' => DB::raw('stock_actual + N')])` — nunca Eloquent para tablas con schema prefix en PostgreSQL

## PDF (DomPDF)

- Templates en `backend/resources/views/reportes/`
- Logo siempre en base64: `base64_encode(file_get_contents(public_path('logo.png')))`
- Márgenes en `@page { margin: ... }` (no en `.page` div)
- `table-layout: fixed` en todas las tablas
- Fechas en español: array manual `$meses` — NO usar `Carbon::translatedFormat()`

## Alfresco (documentos firmados)

- URL: `http://192.168.26.38:8080/alfresco/api/-default-/public/alfresco/versions/1`
- Credenciales: `admin/admin`, sitio: `talentohumano`
- Subir a `talentohumano/{módulo}/{año}/`, guardar `entry.id` en DB
- Helpers `getDocLibNodeId()` y `getOrCreateFolderNodeId()` repetidos en cada controlador que usa Alfresco

---

## Módulo Talento Humano

### Controladores clave

| Controlador | Función |
|---|---|
| `AuthController` | Login / logout / me |
| `EmpleadoController` | CRUD empleados + asignación de roles |
| `AccionPersonalController` | Acciones (encargo, subrogación, ingreso, vacaciones, destitución, cesación) |
| `VacacionesController` | Solicitudes de vacaciones (aprobar/negar/saldo) |
| `PlanificacionVacController` | Planificación anual de vacaciones |
| `LiquidacionVacController` | Liquidación por comisión/desvinculación |
| `ReportePlanificacionController` | PDF planificación + subida Alfresco |
| `PermisosController` | Permisos y licencias |
| `AsistenciaController` | Marcaciones y reportes |
| `CuadreController` | Conciliación de asistencia |
| `DashboardController` | Estadísticas del dashboard |
| `Admin/*` | Departamentos, causas, turnos, horarios, calendario, configuración, aportes IESS |

### Vacaciones — cálculo de saldo

Calculado en `calcularSaldoDisponible()` / `calcularSaldo()`:
- `LOSEP` → 2.50 días/mes | `CODIGO DEL TRABAJO` → 1.25 días/mes
- Fórmula: `(días desde FECHA_CORTE_VACACIONES / 360) × (tasa × 12)`
- Si fecha_ingreso > fecha_corte, se usa fecha_ingreso como base
- Saldo = `dias_adicionales` + devengado − `total_dias_tomados`

### Acciones de Personal (`dbo.acc_accion_personal`)

| Tipo | fecha_fin | Sit. Propuesta | Buscador Titular | Decl. Jurada | Auto-cierra |
|---|---|---|---|---|---|
| INGRESO | No aplica | Requerida (auto-llena) | No | Sí | No |
| ENCARGO | Opcional | Requerida | Sí | No | No |
| SUBROGACION | Requerida | Requerida | Sí | No | Sí (al vencer) |
| VACACIONES | Requerida | No aplica | No | No | Sí (al vencer) |
| DESTITUCION | No aplica | No aplica | No | Sí | No |
| CESACION | No aplica | No aplica | No | Sí | No |

Auto-cierre corre en cada `index()` para SUBROGACION y VACACIONES con `fecha_fin < hoy`.

### Liquidación de Vacaciones

- `INICIO_COMISION` / `FIN_COMISION_SALIDA` → genera certificado PDF
- `FIN_COMISION_RETORNO` / `COMISION_ENTRANTE` → carga saldo desde certificado externo
- `DESVINCULACION` → reporte de liquidación
- Motivos válidos dependen de `modalidad_laboral` del empleado

### Vistas Frontend (Talento Humano)

```
views/empleados/        # CRUD, detalle, importación, distributivo
views/acciones/         # Acciones de personal (lista + formulario)
views/planificacion/    # Planificación, liquidación, reporte
views/admin/            # Roles, departamentos, turnos, configuración, IESS
layouts/MainLayout.vue  # Layout del módulo RRHH
```

---

## Módulo Adquisiciones / Bienes

### Tablas principales (`adq.*`)

| Tabla | Descripción |
|---|---|
| `adq.articulo` | Inventario; `precio_unitario DECIMAL(10,5)`, `stock_actual`, `iva_id`, `nivel1`, `nivel2` |
| `adq.orden_compra` / `adq.orden_compra_det` | Ingresos de bienes (BORRADOR → RECIBIDO) |
| `adq.egreso` / `adq.egreso_det` | Egresos de bienes (BORRADOR → DESPACHADO) |
| `adq.kardex` | Log inmutable de movimientos; snapshot de stock_antes/despues y precio_antes/despues |
| `adq.iva` | Tasas de IVA (15%, 0%, etc.) |
| `adq.proveedor` | Proveedores |
| `adq.catalogo_inventario` | Catálogo MEF (nivel1, nivel2, item_presupuestario) |
| `adq.catalogo_nivel1` | Categorías nivel 1 del catálogo MEF |
| `adq.proceso_contratacion` | Procesos configurables (Catálogo Electrónico, Subasta, Caja Chica, etc.) |
| `adq.unidad_medida` | Unidades de medida configurables |

### Controladores Adquisiciones

| Controlador | Función |
|---|---|
| `ArticuloController` | CRUD artículos + imagen + alertas de stock |
| `OrdenCompraController` | Ingresos: store/update/confirmar/reversar/pdf |
| `EgresoController` | Egresos: store/update/confirmar/reversar/pdf |
| `ProveedorController` | CRUD proveedores |
| `IvaController` | CRUD tasas IVA |
| `ReporteAdqController` | Kardex, Libro de Compras, Egresos Valorizados (JSON + PDF) |

### Reglas de Precio

- `precio_unitario` se guarda con 5 decimales; subtotales y totales con 2 decimales
- Al **confirmar ingreso** (promedio ponderado): `precio_unitario = (stock_antes × precio_anterior + cantidad × precio_nuevo) / stock_despues`; si stock_antes = 0 → `precio_unitario = precio_nuevo`
- Al **confirmar egreso**: si stock llega a 0 → `precio_unitario = 0`; al reversar, restaura precio solo si precio actual es 0 y `precio_anterior > 0`

### Cálculo de Totales con Descuento

El descuento es a nivel de cabecera y se aplica **antes** del IVA (formato SRI):
```
subtotal        = suma de subtotales de líneas (sin IVA)
descuento       = valor manual ingresado
base_imponible  = subtotal - descuento
iva_valor       = iva_lineas × (base_imponible / subtotal)   ← proporcional
total           = base_imponible + iva_valor
```

### Kardex (`adq.kardex`)

Fila inmutable insertada por cada línea de detalle al confirmar/reversar:
- `INGRESO` → al confirmar orden_compra
- `REVERSO_INGRESO` → al reversar orden_compra
- `EGRESO` → al confirmar egreso
- `REVERSO_EGRESO` → al reversar egreso

Captura: `stock_antes`, `stock_despues`, `precio_antes`, `precio_despues`, `precio_movimiento`, usuario.

### Reportes Adquisiciones

- **Kardex**: por artículo + rango fechas → tabla de movimientos + PDF (legal landscape)
- **Libro de Compras**: facturas de proveedores por período + filtro proceso → PDF SRI
- **Egresos Valorizados**: salidas despachadas por período + filtro dirección/área → PDF

### Imágenes de Artículos

`Storage::disk('public')` en carpeta `articulos/`. Ejecutar `php artisan storage:link` una vez al desplegar. Se retorna `imagen_url` en la API.

### Rutas

Todas las rutas de Adquisiciones bajo `/api/adquisiciones/*` en `routes/api.php`.

### Vistas Frontend (Adquisiciones)

```
views/adquisiciones/
  ArticulosView.vue           # Inventario con precio, IVA, stock, imagen
  IngresosBienesView.vue      # Ingresos (BORRADOR/RECIBIDO) + descuento + PDF
  EgresosBienesView.vue       # Egresos (BORRADOR/DESPACHADO) + PDF
  ProveedoresView.vue         # CRUD proveedores
  IvaView.vue                 # Tasas IVA
  ProcesoContratacionView.vue # Procesos de contratación configurables
  UnidadesMedidaView.vue      # Unidades de medida configurables
  CatalogoInventarioView.vue  # Catálogo MEF nivel1/nivel2
  ReporteKardexView.vue       # Kardex por artículo + PDF
  ReporteLibroComprasView.vue # Libro de compras + PDF
  ReporteEgresosView.vue      # Egresos valorizados + PDF
layouts/AdqLayout.vue         # Layout verde, roles ADQUISICIONES/BIENES
```

---

## Environment

- `VITE_API_URL` en `frontend/.env` — URL base de la API
- Backend `.env`: `DB_CONNECTION=pgsql`, credenciales BD
- Session y cache driver: `database`
