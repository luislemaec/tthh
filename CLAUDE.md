# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Sistema de Gestión para el Consejo de Comunicación (Ecuador) con tres módulos:
1. **Talento Humano** — empleados, asistencia, permisos, vacaciones, acciones de personal
2. **Adquisiciones/Bienes** — inventario, ingresos, egresos, kardex, reportes
3. **Transportes** — vehículos institucionales, mantenimiento, solicitudes de movilización

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

Roles: `ADMINISTRADOR`, `TALENTO HUMANO`, `SUPERVISOR`, `ADQUISICIONES`, `BIENES`, `TRANSPORTE`, `CONDUCTOR`. Empleados sin rol = acceso básico.
- Backend: `DB::table('dbo.admin_usuario_rol')` — sin Laravel policies/gates
- Frontend: `auth.tieneRol('NOMBRE')` desde Pinia store
- Menú filtrado por rol desde `dbo.admin_opcion`

## Base de Datos

- Schema `dbo` → Talento Humano | Schema `adq` → Adquisiciones (misma BD PostgreSQL)
- Empleados: PK = `id_emp` (string); estados `ACTIVO`/`INACTIVO` (nunca eliminar)
- Depto 999 excluido de todas las consultas (placeholder de sistema)
- `dbo.d2_configuracion` → parámetros globales (clave/valor/descripcion). Campos de auditoría: `created_at`, `created_by`, `updated_at`, `updated_by`. La query siempre usa `LOWER(concepto)` porque los conceptos se guardan en MAYÚSCULAS. Migración `000030` agregó `descripcion`, migración `000031` agregó auditoría.
- `dbo.ad_departamento` → numeración manual recomendada: padres en múltiplos de 10 (10,50,60,70,80,90), hijos en +1 a +9 del padre. Al crear desde la app, el campo ID es opcional; si se omite genera el siguiente correlativo (excluyendo 999). Campos de auditoría implementados (migración `000032`): `created_at`, `created_by`, `updated_at`, `updated_by`.
- `dbo.ad_empleado` → campos de auditoría implementados (migración `000032`): `created_at`, `created_by`, `updated_at`, `updated_by`. Campo adicional: `puede_solicitar_vehiculo BOOLEAN DEFAULT false`.
- Stock: siempre usar `DB::table()->update(['stock_actual' => DB::raw('stock_actual + N')])` — nunca Eloquent para tablas con schema prefix en PostgreSQL

## PDF (DomPDF)

- Templates en `backend/resources/views/reportes/`
- Logo siempre en base64: `base64_encode(file_get_contents(public_path('logo.png')))`
- Márgenes en `@page { margin: ... }` (no en `.page` div)
- `table-layout: fixed` en todas las tablas
- Fechas en español: array manual `$meses` — NO usar `Carbon::translatedFormat()`
- Papel: **siempre A4** (`->setPaper('a4', 'portrait')` o `'landscape'`) — nunca `'letter'` en reportes nuevos

### Estándar de encabezado para todos los PDFs

Todo PDF del sistema sigue esta estructura de encabezado:

```
┌─────────────────────────────────────────────────────────────────┐
│  [LOGO]  │  CONSEJO DE COMUNICACIÓN (negrita, centrado)         │
│ izquierda│  TÍTULO DEL REPORTE (negrita, mayúsculas, centrado)  │
│          │  Subtítulo opcional (ej: período, año)               │
└─────────────────────────────────────────────────────────────────┘
```

Implementación HTML estándar:
```html
<table style="width:100%; margin-bottom:10px; border-collapse:collapse;">
  <tr>
    <td style="width:15%; vertical-align:middle; text-align:center;">
      @if($logo)<img src="{{ $logo }}" style="max-height:80px; max-width:110px;">@endif
    </td>
    <td style="text-align:center; vertical-align:middle; padding:0 10px;">
      <div style="font-size:11pt; font-weight:bold; text-transform:uppercase;">{{ $nombreInst }}</div>
      <div style="font-size:10pt; font-weight:bold; text-transform:uppercase; margin-top:3px;">TÍTULO DEL REPORTE</div>
      {{-- subtítulo opcional --}}
    </td>
  </tr>
</table>
```

### Pie de página estándar

- **Reportes sin firma** (listados, kardex, estadísticas): solo línea `Generado por` al final
- **Reportes con firma** (acciones de personal, órdenes, planificaciones): tabla de firmas + línea `Generado por`

Línea generado por (siempre al final, antes de cerrar body):
```html
<p style="font-size:7.5pt; color:#555; margin-top:12px; text-align:right;">
  Generado por: {{ strtoupper($generadoPor) }} &nbsp;&nbsp;|&nbsp;&nbsp; {{ now()->format('d/m/Y H:i') }}
</p>
```

Variable `$generadoPor` = `trim($request->user()->apellido_emp) . ' ' . trim($request->user()->nombre_emp)` desde el controlador.

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
| `EmpleadoController` | CRUD empleados + asignación de roles + partidas disponibles |
| `AccionPersonalController` | Acciones (encargo, subrogación, ingreso, vacaciones, destitución, cesación) |
| `VacacionesController` | Solicitudes de vacaciones (aprobar/negar/saldo) |
| `PlanificacionVacController` | Planificación anual de vacaciones — estados `ELIMINADO` y `NEGADO` permiten re-planificar; fechas de períodos se validan contra el año planificado |
| `LiquidacionVacController` | Liquidación por comisión/desvinculación |
| `ReportePlanificacionController` | PDF planificación + subida Alfresco |
| `PermisosController` | Permisos y licencias |
| `AsistenciaController` | Marcaciones y reportes de asistencia |
| `CuadreController` | Conciliación de asistencia (atrasos) |
| `HorasExtrasController` | Planificación y registro de horas extras |
| `DashboardController` | Estadísticas del dashboard — Admin/TH: métricas globales; Supervisor: pendientes + equipo hoy |
| `Admin/*` | Departamentos, causas, turnos, horarios, calendario, configuración, aportes IESS |

### Empleados (`dbo.ad_empleado`)

Campos relevantes:
- `estado`: `ACTIVO` / `INACTIVO` — nunca se elimina
- `estado_puesto`: `OCUPADO` / `VACANTE` / `DISPONIBLE` — DISPONIBLE = empleado inactivo, partida presupuestaria libre para reasignar
- `partida_individual` / `partida_presupuestaria`: identificadores de la partida MEF
- `modalidad_marcacion`: `PRESENCIAL` / `REMOTO` / `TELETRABAJO` (ver Control de Asistencia)
- `modalidad_laboral`: determina motivos válidos en liquidación de vacaciones
- `tipo_contrato`: `LOSEP` / `CODIGO DEL TRABAJO` — define tasa de vacaciones

**Partidas disponibles** (`GET /api/empleados/partidas-vacantes`): devuelve empleados con `estado=INACTIVO` + `estado_puesto=DISPONIBLE`. En `EmpleadoForm.vue`, el campo Partida Individual tiene input libre + botón "Seleccionar libre" que abre un modal con la lista — al seleccionar una fila se auto-llenan `partida_individual` y `partida_presupuestaria`.

### Control de Asistencia (`dbo.sg_control_persona`)

Tabla de marcaciones individuales. Flujo diario en orden estricto: `ENTRADA → SALIDA AL LUNCH → ENTRADA DEL LUNCH → SALIDA`

**modalidad_marcacion** controla cómo puede timbrar el empleado:
- `PRESENCIAL` (default): la IP del request debe comenzar con algún prefijo de `VLANS_PERMITIDAS` en `dbo.d2_configuracion`. Formato: `10.10.12.,10.10.26.` (prefijos con punto final, separados por coma). Si la lista está vacía se permite todo.
- `REMOTO`: puede marcar desde cualquier IP sin validación. `tipo_marcacion = 'WEB'`. Uso: comisiones, viajes.
- `TELETRABAJO`: puede marcar desde cualquier IP. `tipo_marcacion = 'TELETRABAJO'`. Uso: trabajo desde casa.

**Validación de IP:** El backend corre detrás de Apache (proxy a puerto 9000). `bootstrap/app.php` tiene `trustProxies(at: '127.0.0.1')` para leer `X-Forwarded-For` y obtener la IP real del cliente. La query usa `LOWER(concepto) = 'vlans_permitidas'` porque en la BD el concepto está en mayúsculas (`VLANS_PERMITIDAS`).

**Variables de configuración relevantes para asistencia:**
- `VLANS_PERMITIDAS`: prefijos de red permitidos para marcación PRESENCIAL (ej: `10.10.12.,10.10.26.`)
- `CONTROL_IP_MARCACION`: valor `1` = una IP solo puede ser usada por un empleado por día (evita timbrar por otro)

Campos clave de `sg_control_persona`: `nro_documento` (= id_emp), `clasificacion` (ENTRADA/SALIDA), `concepto` (ENTRADA/SALIDA AL LUNCH/ENTRADA DEL LUNCH/SALIDA), `fecha_hora`, `tipo_marcacion` (WEB/TELETRABAJO), `ip`, `ubicacion`, `procesado` (SI/NO), `origen`.

**Cuadre** (`dbo.d2_cuadre_marcacion`): tabla con atrasos en minutos por día por empleado (`atraso_entrada`, `atraso_lunch`, `atraso_salida`). Se usa en el reporte personal de asistencia para mostrar atrasos y si están justificados por permisos aprobados.

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

PDF: `accion_personal.blade.php` — usa `{!! !!}` (no `{{ }}`) para entidades HTML como `&nbsp;` en checkboxes.

### Liquidación de Vacaciones

- `INICIO_COMISION` / `FIN_COMISION_SALIDA` → genera certificado PDF
- `FIN_COMISION_RETORNO` / `COMISION_ENTRANTE` → carga saldo desde certificado externo
- `DESVINCULACION` → reporte de liquidación
- Motivos válidos dependen de `modalidad_laboral` del empleado

### Horas Extras

#### Tablas (`dbo.*`)

| Tabla | Descripción |
|---|---|
| `dbo.nom_he_planificacion_cab` | Cabecera mensual: id_emp, anio, mes, estado, total_extraordinarias, total_suplementarias, memorando, pdf_aprobado (Alfresco node id), fechas y usuarios de cada transición |
| `dbo.nom_he_planificacion_det` | Detalle: cab_id, actividad (texto), horas_extraordinarias, horas_suplementarias |
| `dbo.nom_he_registro` | Horas reales: cab_id, id_emp, fecha, hora_inicio, hora_fin, horas_extraordinarias, horas_suplementarias, descripcion, estado, observacion, usuario_decision, fecha_decision |

#### Flujo completo

```
Empleado crea planificación → PENDIENTE
  ↓ (si es supervisor/admin → directamente APROBADO)
Supervisor aprueba → APROBADO  |  niega → NEGADO (con observación)
  ↓
TH NOMINA autoriza con N° memorando → AUTORIZADO
  ↓
Empleado registra horas reales (solo en mes planificado y mes actual) → EN REVISION
  ↓
TH NOMINA revisa → aprueba → PENDIENTE  |  devuelve al empleado para corrección
  ↓
Supervisor confirma → APROBADO  |  niega → NEGADO
```

Estados del registro de horas: `EN REVISION → PENDIENTE → APROBADO / NEGADO`

#### Clasificación automática de horas

El sistema calcula automáticamente el tipo a partir de hora_inicio y hora_fin:
- **Lunes–Viernes** (sin feriado): 00:00–06:00 = Extra | 06:00–08:00 = Supl | 08:00–16:30 = Normal | 16:30–24:00 = Supl
- **Fin de semana o feriado** (`dbo.d2_lista_fecha`): todo el rango = Extraordinarias
- Límite máximo: 20h extraordinarias y 20h suplementarias por mes

#### Cálculo monetario (visible solo para TH NOMINA / ADMINISTRADOR)

```
tarifa_hora = sueldo / 240
valor_extra = tarifa_hora × (1 + porc_extraordinaria/100) × horas_extraordinarias
valor_supl  = tarifa_hora × (1 + porc_suplementaria/100)  × horas_suplementarias
```
Porcentajes se obtienen de `dbo.d2_jornada` (campos `porc_extraordinaria`, `porc_suplementaria`) según la jornada del empleado.

#### PDFs y Alfresco

- `GET /api/horas-extras/planificacion/{id}/pdf` → `he_planificacion.blade.php` — actividades planificadas, datos del empleado, firmas (empleado + supervisor del departamento)
- `GET /api/horas-extras/planificacion/{id}/pdf-registros` → `he_registros.blade.php` — solo registros en estado APROBADO, mismas firmas. Visible cuando hay al menos 1 registro APROBADO.
- PDF firmado: se sube a Alfresco en `horas-extras/{año}/`, se guarda el `entry.id` en `pdf_aprobado` de la cabecera.
- Botones PDF (planificación): visibles en estados APROBADO y AUTORIZADO.

#### Roles en Horas Extras

- **Supervisor**: presencia en `dbo.supervisor_area` — endpoint `/horas-extras/mi-rol` devuelve `es_supervisor`
- **TH NOMINA**: rol exacto `TH NOMINA` (sin tilde) en `dbo.admin_rol` — devuelve `es_admin_th`
- Supervisores ven tabs "Planificaciones del Equipo" y "Registros del Equipo"
- TH NOMINA ve además el desglose monetario y puede revisar/autorizar
- Horas en UI: formato `Xh Ym` (ej: 4h 15m), no decimal

#### Rutas (`/api/horas-extras/*`)

| Método | Ruta | Función |
|---|---|---|
| GET | `/calcular` | Calcula horas por rango horario |
| GET | `/mi-rol` | Indica si es supervisor / admin TH |
| GET | `/mi-planificacion` | Planificación del empleado para mes/año |
| POST | `/planificacion` | Crear planificación |
| PUT | `/planificacion/{id}` | Editar (solo PENDIENTE) |
| DELETE | `/planificacion/{id}` | Eliminar (solo PENDIENTE) |
| GET | `/planificacion` | Lista planificaciones del equipo |
| PATCH | `/planificacion/{id}/aprobar` | Supervisor aprueba |
| PATCH | `/planificacion/{id}/negar` | Supervisor niega |
| PATCH | `/planificacion/{id}/autorizar` | TH NOMINA autoriza |
| GET | `/planificacion/{id}/pdf` | PDF planificación |
| GET | `/planificacion/{id}/pdf-registros` | PDF horas trabajadas |
| POST | `/planificacion/{id}/subir-firmado` | Sube PDF firmado a Alfresco |
| GET | `/planificacion/{id}/descargar-firmado` | Descarga desde Alfresco |
| GET | `/mis-registros` | Registros del empleado para mes/año |
| POST | `/registro` | Registrar horas reales |
| PUT | `/registro/{id}` | Editar registro (solo EN REVISION) |
| GET | `/equipo-registros` | Registros del equipo (supervisor/admin) |
| PATCH | `/registro/{id}/revisar` | TH NOMINA aprueba/devuelve |
| PATCH | `/registro/{id}/confirmar` | Supervisor confirma → APROBADO |
| PATCH | `/registro/{id}/negar` | Supervisor niega |

### Vistas Frontend (Talento Humano)

```
views/empleados/        # CRUD empleados, detalle, importación, distributivo
                        # EmpleadoForm: bloque "Control de Asistencia" (modalidad_marcacion)
                        #   Partida Individual: input libre + botón "Seleccionar libre" (modal partidas disponibles)
views/acciones/         # Acciones de personal (lista + formulario + PDF)
views/planificacion/    # Planificación anual de vacaciones, liquidación, reporte
                        # ReporteSaldoVacView.vue — reporte de saldo de vacaciones (TH/ADMIN)
                        #   Vista resumida: tabla paginada por empleado con saldo actual
                        #   Vista detallada: kardex expandible inline por empleado (INICIAL→VACACIONES→DEVENGADO→TOMADOS)
                        #   Botón "Cargar Saldos": modal para subir CSV (cedula,saldo) + nueva fecha de corte
                        #     → actualiza dias_adicionales + total_dias_tomados=0 en d2_cabecera_vacacion
                        #     → actualiza FECHA_CORTE_VACACIONES en d2_configuracion
                        #   PDF descargable (resumido): GET /api/reporte-vacaciones/pdf
                        #   Rutas: GET /api/reporte-vacaciones, GET /api/reporte-vacaciones/{id_emp},
                        #          GET /api/reporte-vacaciones/pdf, POST /api/reporte-vacaciones/cargar-saldos
                        #   Controlador: ReporteVacacionesController.php
                        #   Kardex: dias legados (sin registro en d2_vacacion) aparecen como fila "registros anteriores"
views/permisos/         # Permisos y licencias — fecha_desde/fecha_hasta default = hoy al abrir modal
DashboardView.vue       # Admin/TH: métricas globales + tabla por depto
                        # Supervisor (no admin): 4 tarjetas pendientes (permisos/vacaciones/HE/materiales)
                        #   + widget "Mi equipo hoy" (presentes/permiso/vacaciones/sin marcar + barra)
                        #   + atrasos del mes del equipo
                        # Las tarjetas originales se ocultan para supervisores (v-if="!es_supervisor||es_admin_th")
views/asistencia/       # Reporte de asistencia personal y admin
views/horasextras/
  HorasExtrasView.vue   # 4 tabs:
                        #   MI PLANIFICACIÓN: crear/editar, PDF planificación, subir PDF firmado
                        #   MIS HORAS TRABAJADAS: registrar horas, PDF horas trabajadas
                        #   PLANIFICACIONES DEL EQUIPO: aprobar/negar (supervisor/admin)
                        #   REGISTROS DEL EQUIPO: revisar/confirmar/negar + desglose monetario (TH NOMINA)
views/admin/            # Roles, departamentos, turnos, configuración, IESS, avisos ticker
layouts/MainLayout.vue  # Layout del módulo RRHH (menú colapsado, se abre el grupo activo)
```

---

## Auditoría Centralizada

Implementada para trazabilidad ante la Contraloría General del Estado. Todas las acciones críticas se registran en `dbo.nom_auditoria_log`.

### Servicio

`App\Services\AuditoriaService::log($tabla, $registroId, $accion, $datosAnteriores, $datosNuevos, $request, $descripcion)` — estático, nunca lanza excepciones (try/catch interno). Insertar en cualquier controlador con `use App\Services\AuditoriaService;`.

### Controladores instrumentados

| Controlador | Acciones auditadas |
|---|---|
| `EmpleadoController` | CREAR, ACTUALIZAR |
| `RolController` | ASIGNAR_ROL, REVOCAR_ROL |
| `VacacionesController` | APROBAR, NEGAR, ELIMINAR |
| `PermisosController` | APROBAR, NEGAR, ELIMINAR |
| `HorasExtrasController` | APROBAR, NEGAR, AUTORIZAR, CONFIRMAR, NEGAR (registro) |
| `Admin/ConfiguracionController` | ACTUALIZAR (valor anterior/nuevo) |
| `NominaController` | (vía AuditoriaService desde registrarAuditoria()) |
| `Adquisiciones/OrdenCompraController` | CONFIRMAR_INGRESO, REVERSAR_INGRESO |
| `Adquisiciones/EgresoController` | CONFIRMAR_EGRESO, REVERSAR_EGRESO |
| `Adquisiciones/SolicitudMaterialController` | APROBAR, NEGAR, DESPACHAR |
| `Adquisiciones/AjusteController` | AJUSTE_POSITIVO / AJUSTE_NEGATIVO |
| `TransporteController` | APROBAR_MOV, NEGAR_MOV, ORDEN_TRABAJO, NEGAR_MANT, EN_TALLER, FINALIZAR_MANT |

### Endpoint y vista

- `GET /api/admin/auditoria` — solo rol ADMINISTRADOR; filtros: `modulo`, `accion`, `usuario_id`, `fecha_desde`, `fecha_hasta`, `descripcion`; paginado 50/página
- Vista: `views/admin/AuditoriaView.vue` (ruta `admin/auditoria`)
- Tabla muestra fecha/hora, usuario, tabla, acción (con badge de color), descripción, IP
- Clic en fila expande JSON datos_anteriores / datos_nuevos
- Agregar opción de menú en Admin > Opciones de Menú con URL `admin/auditoria`, rol ADMINISTRADOR

### Si se agrega un nuevo módulo

Instrumentar sus controladores con `AuditoriaService::log()` en las acciones irreversibles (aprobar, confirmar, eliminar, cambios de estado).

---

## Módulo Nómina

Rol: `TH NOMINA`. Flujo general: seleccionar mes/año → Calcular → BORRADOR → Cerrar → CERRADO. Un período CERRADO no se puede recalcular.

### Tablas (`dbo.*`)

| Tabla | Descripción |
|---|---|
| `nom_auditoria_log` | Log centralizado de auditoría (accion, datos_anteriores/nuevos JSONB, usuario, ip) |
| `nom_decimo_tercero` | D13 mensual; unique(anio, mes, id_emp) |
| `nom_sbu_historico` | SBU por año; unique(anio) — administrado desde Admin → SBU |
| `nom_decimo_cuarto` | D14 mensual; unique(anio, mes, id_emp) |
| `nom_fondos_reserva` | FR mensual; campo `tipo` MENSUAL/IESS; solo empleados con ≥1 año |
| `nom_rol_pago_cab` | Cabecera rol de pagos; unique(anio, mes) |
| `nom_rol_pago_det` | Detalle rol de pagos; unique(cab_id, id_emp) |

### Controladores

- `NominaController` — D13, D14, Fondos de Reserva, Consolidado, SBU
- `RolPagoController` — Rol de Pagos

### Reglas de negocio clave

**¿Quiénes se calculan?**
- D13: `acumula_decimo_tercero = false` (cobra mensualmente)
- D14: `acumula_decimo_cuarto = false`
- Fondos de Reserva: `acumula_fondos_reserva IN (1,2)` + `fecha_ingreso ≤ primer día del mes - 12 meses`
- Rol de Pagos: todos los empleados ACTIVOS (depto ≠ 999)

**Días proporcionales** (`calcularDiasEnMes`): ingreso antes del mes → 30 días; ingreso dentro del mes → `30 - día_ingreso + 1`; ingreso después del mes → 0 (excluido).

**Fórmulas:**
- D13: `(sueldo / 12 / 30) × dias`
- D14: `(sbu / 12 / 30) × dias`
- FR: `(sueldo × 8.33 / 100 / 30) × dias`
- Rol: `valor_rmu = ROUND(sueldo × dias / 30, 2)`; `aporte_patronal/personal = ROUND(valor_rmu × pct / 100, 2)`

**Tasas de aporte** (`dbo.d2_aportes_iess`): `modalidad` debe coincidir con `TRIM(tipo_contrato)` del empleado. Los % almacenados ya incluyen IECE y SECAP:
- LOSEP: `aporte_individual = 11.45%`, `aporte_patronal = 9.65%`
- CODIGO DEL TRABAJO: `aporte_individual = 9.45%`, `aporte_patronal = 12.15%`

**Campos manuales en Rol de Pagos:** `quirografario`, `hipotecario`, `impuesto_renta`, `supa` — editables inline (click en celda) o importando CSV con columnas `cedula, quirografario, hipotecario, impuesto_renta`.

### Vistas Frontend (`views/nomina/`)

```
DecimosView.vue       # Tabs: Décimo Tercero | Décimo Cuarto | Consolidado
FondosReservaView.vue # Filtro tipo MENSUAL/IESS/Todos
RolPagoView.vue       # Edición inline de 4 campos manuales + importar CSV + PDF landscape
```

Vista admin SBU: `views/admin/SbuView.vue` (ruta `admin/sbu`) — el SBU se gestiona aquí, NO en DecimoCuarto.

### PDFs (`resources/views/reportes/`)

- `nom_decimo_tercero.blade.php`, `nom_decimo_cuarto.blade.php`, `nom_fondos_reserva.blade.php`, `nom_consolidado.blade.php` — portrait letter
- `nom_rol_pago.blade.php` — **landscape** letter, 7pt; % de aportes en encabezado de columna (no en cada fila)

---

## Módulo Adquisiciones / Bienes

### Tablas principales (`adq.*`)

| Tabla | Descripción |
|---|---|
| `adq.articulo` | Inventario; `precio_unitario DECIMAL(10,5)`, `stock_actual`, `iva_id`, `nivel1`, `nivel2` |
| `adq.orden_compra` / `adq.orden_compra_det` | Ingresos de bienes (BORRADOR → RECIBIDO) |
| `adq.egreso` / `adq.egreso_det` | Egresos de bienes (BORRADOR → DESPACHADO) |
| `adq.kardex` | Log inmutable de movimientos; snapshot de stock_antes/despues, precio_antes/despues y `valor_saldo` (= stock_despues × precio_despues) |
| `adq.iva` | Tasas de IVA (15%, 0%, etc.) |
| `adq.proveedor` | Proveedores |
| `adq.catalogo_inventario` | Catálogo MEF (nivel1, nivel2, item_presupuestario) |
| `adq.catalogo_nivel1` | Categorías nivel 1 del catálogo MEF |
| `adq.proceso_contratacion` | Procesos configurables (Catálogo Electrónico, Subasta, Caja Chica, etc.) |
| `adq.unidad_medida` | Unidades de medida configurables |
| `adq.solicitud_material` / `adq.solicitud_material_det` | Solicitudes internas de materiales; estados PENDIENTE/APROBADO/NEGADO/DESPACHADO/DESPACHADO PARCIAL |

### Controladores Adquisiciones

| Controlador | Función |
|---|---|
| `ArticuloController` | CRUD artículos + imagen + alertas de stock |
| `OrdenCompraController` | Ingresos: store/update/confirmar/reversar/pdf |
| `EgresoController` | Egresos: store/update/confirmar/reversar/pdf |
| `ProveedorController` | CRUD proveedores |
| `IvaController` | CRUD tasas IVA |
| `AjusteController` | Ajuste de inventario (toma física): store/index — inserta en kardex tipo AJUSTE_POSITIVO/NEGATIVO |
| `SolicitudMaterialController` | Solicitudes internas: store/aprobar/negar/despachar — **PENDIENTE: despachar() debe insertar en kardex** |
| `ReporteAdqController` | Kardex NIC 2, Libro de Compras, Egresos Valorizados (JSON + PDF) |

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

Fila inmutable insertada por cada línea de detalle al confirmar/reversar/ajustar:

| tipo_movimiento | Cuándo se inserta | Columna afectada |
|---|---|---|
| `INGRESO` | Confirmar orden_compra | cantidad_entrada |
| `REVERSO_INGRESO` | Reversar orden_compra | cantidad_salida |
| `EGRESO` | Confirmar egreso | cantidad_salida |
| `REVERSO_EGRESO` | Reversar egreso | cantidad_entrada |
| `AJUSTE_POSITIVO` | Ajuste inventario (físico > sistema) | cantidad_entrada |
| `AJUSTE_NEGATIVO` | Ajuste inventario (físico < sistema) | cantidad_salida |

Captura: `stock_antes`, `stock_despues`, `precio_antes`, `precio_despues`, `precio_movimiento`, `valor_saldo`, usuario.

`valor_saldo = ROUND(stock_despues × precio_despues, 2)` — valor total del inventario tras el movimiento.

### Solicitudes de Materiales (`adq.solicitud_material`)

Flujo: empleado crea → supervisor revisa y aprueba (puede modificar cantidades) / niega → bienes despacha con `cantidad_autorizada` por línea.

- Empleado NO ve el stock disponible al crear la solicitud
- Supervisor SÍ ve el stock en el modal de aprobación y puede modificar las cantidades solicitadas antes de aprobar
- Si el solicitante es supervisor, la solicitud se crea directamente en APROBADO
- Estado final en despacho: DESPACHADO (todo), DESPACHADO PARCIAL (parcial), NEGADO (todo en 0)
- `despachar()` usa `DB::table()->update(['stock_actual' => DB::raw('GREATEST(0, stock_actual - N)')])` — nunca Eloquent
- **PENDIENTE**: `despachar()` aún no inserta en `adq.kardex` — al implementar, usar tipo `EGRESO` igual que `EgresoController::confirmar()`

Campos de cabecera: `id_emp`, `id_depto`, `estado`, `justificacion`, `fecha_solicitud`, `fecha_aprobacion`, `fecha_despacho`, `usuario_aprobacion`, `usuario_despacho`
Campos de detalle: `articulo_id`, `cantidad_solicitada`, `cantidad_autorizada`, `unidad_medida`

### Ajuste de Inventario

Toma física: el usuario ingresa la cantidad contada físicamente; el sistema calcula la diferencia vs `stock_actual` y genera un movimiento AJUSTE_POSITIVO o AJUSTE_NEGATIVO en el kardex. No genera egreso ni ingreso de bienes, solo corrige el stock y el `valor_saldo`.

### Reportes Adquisiciones

- **Kardex NIC 2**: por artículo + rango fechas → tabla doble encabezado (INGRESO/EGRESO/SALDO, cada uno con Cant./P.Unit./Total) + PDF legal landscape. `valor_saldo` en columna SALDO Total. Clasifica AJUSTE_POSITIVO y REVERSO_EGRESO como INGRESO; AJUSTE_NEGATIVO y REVERSO_INGRESO como EGRESO.
- **Libro de Compras**: facturas de proveedores por período + filtro proceso → muestra columna Descuento cuando aplica → PDF SRI
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
  SolicitudesView.vue         # Solicitudes internas: crear, aprobar (supervisor), despachar (bienes)
  AjusteInventarioView.vue    # Toma física: buscar artículo, ingresar cant. física, registra ajuste
  ReporteKardexView.vue       # Kardex NIC 2 por artículo + PDF (doble encabezado INGRESO/EGRESO/SALDO)
  ReporteLibroComprasView.vue # Libro de compras + PDF (incluye columna descuento)
  ReporteEgresosView.vue      # Egresos valorizados + PDF
layouts/AdqLayout.vue         # Layout verde, roles ADQUISICIONES/BIENES
                              # Menú colapsado por defecto, auto-abre el grupo de la ruta activa
```

---

## Módulo Transportes

Layout azul oscuro (`#1e3a5f`), separado de TH y Adquisiciones. Tarjeta en el launcher.

### Roles
| Rol | Acceso |
|---|---|
| `TRANSPORTE` | Todo: vehículos, talleres, tipos, plan preventivo, mantenimiento, movilización, vales |
| `CONDUCTOR` | Mantenimiento (crear requerimientos) + movilización (ver asignadas, hoja de ruta) + vales combustible |
| Sin rol especial | Solo movilización si `puede_solicitar_vehiculo = true` en `ad_empleado` |

Combinación recomendada: EMPLEADO + TRANSPORTE o EMPLEADO + CONDUCTOR.

### Tablas (`dbo.*`)

| Tabla | Descripción |
|---|---|
| `trans_vehiculo` | Catálogo de vehículos; estado ACTIVO/INACTIVO/MANTENIMIENTO |
| `trans_taller` | Talleres mecánicos externos; estado ACTIVO/INACTIVO |
| `trans_tipo_mantenimiento` | Categorías: PREVENTIVO, CORRECTIVO, PREVENTIVO Y CORRECTIVO; estado ACTIVO/INACTIVO |
| `trans_plan_preventivo_cab` | Plan preventivo cabecera: vehiculo_id, km_hito, nombre del plan |
| `trans_plan_preventivo_det` | Actividades del plan: cab_id, orden, tipo_actividad (MO/RE/CL), cantidad, actividad |
| `trans_mantenimiento` | Requerimientos; estados PENDIENTE→ORDEN_GENERADA→EN_TALLER→FINALIZADO |
| `trans_mantenimiento_actividad` | Actividades del requerimiento de mantenimiento |
| `trans_solicitud_mov` | Solicitudes de movilización; estados PENDIENTE→APROBADO/NEGADO→COMPLETADO. Campos adicionales: `direccion_salida`, `direccion_destino` (VARCHAR 200), `pasajeros` (TEXT) |
| `trans_vale_combustible` | Vales de combustible; formato FR05-PRO.GA-TR.001; numero auto-secuencial |

Campo adicional en `ad_empleado`: `puede_solicitar_vehiculo BOOLEAN DEFAULT false`.

### Tipos de Mantenimiento — lógica clave

`trans_tipo_mantenimiento.nombre` es la categoría: `PREVENTIVO`, `CORRECTIVO`, o `PREVENTIVO Y CORRECTIVO`.
- Backend: `str_contains($tipo->nombre, 'PREVENTIVO')` y `str_contains($tipo->nombre, 'CORRECTIVO')` para determinar qué secciones aplican
- Frontend: `tipoSeleccionado?.nombre?.includes('PREVENTIVO')` / `includes('CORRECTIVO')`
- "PREVENTIVO Y CORRECTIVO" contiene ambas cadenas → activa ambas secciones simultáneamente
- El CRUD permite activar/desactivar tipos y agregar nuevos (ej. EMERGENCIA)

### Plan Preventivo

Actividades agrupadas por vehículo + km_hito + nombre. Cada actividad tiene:
- `tipo_actividad`: MO (Mano de Obra), RE (Repuesto), CL (Combustibles/Lubricantes)
- `cantidad`: entero ≥ 1
- `actividad`: descripción de la tarea

Importación CSV: columnas `placa,km_hito,nombre,tipo_actividad,actividad,cantidad`. Agrupa por clave compuesta `vehiculo_id|km_hito|nombre`, crea una cabecera por grupo y los detalles correspondientes.

### Vales de Combustible

Formato oficial FR05-PRO.GA-TR.001. PDF media carta (`[0, 0, 396, 504]`).
- Numeración secuencial: `MAX(numero) + 1`, semilla desde `VALE_COMBUSTIBLE_INICIO` en `dbo.d2_configuracion`
- Agregar `VALE_COMBUSTIBLE_INICIO` en Admin > Configuración con el último número de vale en papel
- Combustibles: Extra (glns/pu/valor), Super, Diesel — valor se calcula automáticamente
- PDF se abre en nueva pestaña al guardar (para imprimir y firmar)
- Conductor ve solo sus propios vales; TRANSPORTE ve todos

### Flujos

**Mantenimiento:** conductor crea requerimiento (tipo + km_actual + actividades) → TRANSPORTE genera orden de trabajo (asigna taller de la lista, N° orden, fecha) → EN_TALLER → FINALIZAR (actualiza km del vehículo). PDF disponible desde ORDEN_GENERADA.

**Movilización:** empleado autorizado solicita (con lugar_salida, lugar_destino, direccion_salida, direccion_destino opcionales, y pasajeros opcional) → TRANSPORTE aprueba (asigna vehículo + conductor, valida conflicto de horario) o niega → conductor llena hoja de ruta (km_salida, km_retorno) → COMPLETADO (actualiza km del vehículo). PDF disponible desde APROBADO.

**Vale combustible:** conductor abre formulario → selecciona vehículo, gasolinera, fecha, combustibles → guarda → PDF se abre automáticamente en nueva pestaña.

### Controladores y Rutas

Controladores en `app/Http/Controllers/Transporte/`:
- `TransporteController` — vehículos, mantenimiento, movilización, conductores
- `TallerController` — CRUD talleres
- `TipoMantenimientoController` — CRUD tipos
- `PlanPreventivoController` — CRUD plan + importar CSV
- `ValeController` — vales combustible + PDF

Rutas bajo `/api/transporte/*`:
- `GET/POST /vehiculos`, `PUT /vehiculos/{id}`
- `GET/POST /talleres`, `GET /talleres/activos`, `PUT /talleres/{id}`
- `GET/POST /tipos-mantenimiento`, `GET /tipos-mantenimiento/activos`, `PUT /tipos-mantenimiento/{id}`
- `GET/POST /plan-preventivo`, `PUT /plan-preventivo/{id}`, `POST /plan-preventivo/importar-csv`
- `GET/POST /mantenimiento`, `PUT /mantenimiento/{id}`, `GET /mantenimiento/{id}/pdf`
- `GET/POST /movilizacion`, `PUT /movilizacion/{id}`, `GET /movilizacion/{id}/pdf`
- `GET/POST /vales-combustible`, `GET /vales-combustible/{id}/pdf`
- `GET /conductores` — lista empleados con rol CONDUCTOR

`PUT /mantenimiento/{id}` con campo `accion`: `orden` / `en_taller` / `finalizar`.
`PUT /movilizacion/{id}` con campo `accion`: `aprobar` / `negar` / `hoja_ruta`.

### PDFs (`resources/views/reportes/`)

- `trans_orden_trabajo.blade.php` — portrait letter; secciones: vehículo, requerimiento, actividades, orden, firmas (conductor/responsable/taller)
- `trans_orden_movilizacion.blade.php` — portrait letter; secciones: solicitud, vehículo+conductor, hoja de ruta (solo si COMPLETADO), firmas
- `trans_vale_combustible.blade.php` — **media carta** `[0,0,396,504]`; formato FR05-PRO.GA-TR.001; tabla combustibles, km/vehículo/fecha, firmas

### Vistas Frontend

```
views/transporte/
  VehiculosView.vue           # CRUD vehículos; solo TRANSPORTE
  TalleresView.vue            # CRUD talleres; solo TRANSPORTE
  TiposMantenimientoView.vue  # CRUD tipos (activar/desactivar); solo TRANSPORTE
  PlanPreventivoView.vue      # CRUD plan preventivo + importar CSV; solo TRANSPORTE
  MantenimientoView.vue       # Conductor crea; TRANSPORTE gestiona estados, asigna taller de lista, PDF
  MovilizacionView.vue        # Empleado solicita; TRANSPORTE aprueba/niega; conductor llena hoja de ruta
  ValesCombustibleView.vue    # CONDUCTOR + TRANSPORTE; guarda y abre PDF automáticamente
layouts/TransporteLayout.vue  # Menú dinámico desde auth.menuAgrupado filtrado a transporte/
```

`MainLayout.vue` excluye `transporte/` y `adquisiciones/` de su menú. `LauncherView.vue` muestra tarjeta Transportes si tiene rol TRANSPORTE, CONDUCTOR, o `puede_solicitar_vehiculo`. Tarjetas del launcher: `w-44 p-5` con íconos `w-12 h-12`.

### Opciones de menú a configurar en Admin

| URL | Roles |
|---|---|
| `transporte/vehiculos` | TRANSPORTE |
| `transporte/talleres` | TRANSPORTE |
| `transporte/tipos-mantenimiento` | TRANSPORTE |
| `transporte/plan-preventivo` | TRANSPORTE |
| `transporte/mantenimiento` | TRANSPORTE, CONDUCTOR |
| `transporte/movilizacion` | TRANSPORTE, CONDUCTOR |
| `transporte/vales-combustible` | TRANSPORTE, CONDUCTOR |

---

## Avisos Ticker (Launcher)

Mensajes de publicidad/información que se muestran en `LauncherView.vue` con animación CSS.

### Tabla
`dbo.d2_aviso_ticker` — campos: `id`, `texto`, `activo`, `orden`, `created_at`, `updated_at`

> **Nota:** existe también `dbo.d2_aviso` (tabla anterior del sistema, diferente estructura). El modelo `Aviso.php` apunta a `d2_aviso_ticker`, no a `d2_aviso`.

### Configuración de dirección
`dbo.d2_configuracion` concepto `AVISOS_DIRECCION` → valor `horizontal` o `vertical`.

### Backend
- Modelo: `App\Models\Aviso` (`$table = 'dbo.d2_aviso_ticker'`)
- Controlador: `Admin\AvisoController` — métodos: `index`, `activos`, `store`, `update`, `destroy`, `setDireccion`
- Rutas bajo `/api/admin/avisos`:
  - `GET /admin/avisos/activos` — público (sin auth de rol); devuelve `{ avisos: [...], direccion: 'horizontal'|'vertical' }`
  - `GET/POST /admin/avisos`, `PUT /admin/avisos-direccion`, `PUT/DELETE /admin/avisos/{id}`

### Frontend
- CRUD en `views/admin/AvisosView.vue` (ruta `admin/avisos`) — agregar opción de menú en Admin > Opciones de Menú
- `LauncherView.vue` carga `/admin/avisos/activos` en `onMounted`
- **Horizontal:** barra inferior con `.ticker-horizontal` (CSS `scroll-left`, `translateX -50%`) — texto duplicado para loop continuo
- **Vertical:** panel lateral izquierdo `w-56` con `.ticker-vertical` (CSS `scroll-up`, `translateY -50%`) — texto duplicado para loop continuo
- Ambas animaciones pausan al hacer hover
- **campo Orden:** número para ordenar los avisos (menor = primero); la query ordena `ORDER BY orden ASC`

---

## Environment

- `VITE_API_URL` en `frontend/.env` — URL base de la API
- Backend `.env`: `DB_CONNECTION=pgsql`, credenciales BD
- Session y cache driver: `database`
