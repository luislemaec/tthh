# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Sistema de Gestión para el Consejo de Comunicación (Ecuador) con tres módulos:
1. **Talento Humano** — empleados, asistencia, permisos, vacaciones, acciones de personal, nomina
2. **Adquisiciones/Bienes** — inventario, ingresos, egresos, kardex, reportes
3. **Transportes** — vehículos institucionales, mantenimiento, solicitudes de movilización

- **Backend:** Laravel 12 (PHP 8.4), PostgreSQL, Laravel Sanctum, DomPDF
- **Frontend:** Vue 3 (Composition API), Pinia, Vue Router 5, Tailwind CSS 4, Axios, Vite 7

> **Tailwind CSS 4 — `@apply` en `<style scoped>`:** requiere `@reference "tailwindcss";` al inicio del bloque `<style scoped>` cuando se usa `@apply`. Sin esa línea el build falla con "Cannot apply unknown utility class".

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

> **IMPORTANTE — `php artisan migrate` en producción:** Solo ejecuta migraciones NUEVAS (pendientes). NUNCA correr `migrate:rollback`, `migrate:fresh` o `migrate:reset` en producción — borra datos. Si una migración ya ejecutada no aparece en la tabla `public.migrations`, insertarla manualmente antes de correr `migrate`.

> **IMPORTANTE — Quién ejecuta los comandos del servidor:** `php artisan migrate`, `npm run build`, `git pull` y cualquier comando en el servidor de aplicaciones (`192.168.26.19`) los ejecuta **siempre el usuario manualmente**. Claude NUNCA corre comandos en el servidor. Al crear migraciones o cambios de frontend, Claude solo genera el código — el usuario es quien hace el deploy.

## Backups de Base de Datos

- **Servidor BD:** `192.168.26.38:5432`, base de datos `BDD_RRHH`, usuario `postgres`
- **Script:** `/usr/local/bin/backup_rrhh.sh` en el servidor de aplicaciones (`192.168.26.19`)
- **Destino:** `/var/backups/rrhh/rrhh_YYYYMMDD_HHMM.sql.gz`
- **Cron:** diario a las 02:00 (`0 2 * * * /usr/local/bin/backup_rrhh.sh`)
- **Retención:** 7 días (los más antiguos se eliminan automáticamente)
- Para backup manual: `sudo /usr/local/bin/backup_rrhh.sh`
- Para restaurar: `gunzip -c archivo.sql.gz | psql -U postgres -h 192.168.26.38 BDD_RRHH`

### Incidente 2026-06-24 — pérdida de datos adq

Las tablas `adq.orden_compra`, `adq.egreso`, `adq.kardex`, `adq.solicitud_material` quedaron vacías porque las migraciones de adquisiciones no estaban registradas en `public.migrations`. Al correr `php artisan migrate`, Laravel las ejecutó de nuevo borrando los datos. Los 482 artículos (`adq.articulo`) no se vieron afectados. **Solución aplicada:** se insertaron manualmente los registros de las 21 migraciones adq en `public.migrations` con batch=1.

### Incidente 2026-07-17 — disco lleno en servidor BD (`192.168.26.38`) por logs de Alfresco/Tomcat

**Causa raíz:** `/opt/alfresco-community/tomcat/logs/localhost_access_log*.txt` (logs de acceso de Tomcat, sin rotación configurada) acumularon ~12.5 GB (1-2 GB/día) hasta llenar el `/` del servidor de BD al 100%. Con el disco lleno, PostgreSQL 13 (`postgresql-13.service`) no podía operar con normalidad y empezó a rechazar conexiones (`SQLSTATE[08006] Connection refused` / `No route to host` / `FATAL: the database system is starting up`), afectando toda la aplicación (login, marcaciones web, todo lo que dependiera de la BD) entre aprox. `08:21` y `08:22:41` del 2026-07-17.

**Solución aplicada:**
1. Se liberó espacio borrando los `localhost_access_log*.txt` viejos (los de fecha pasada ya no los usa Tomcat, es seguro `rm` directo; **no** usar `rm` en `catalina.out` mientras Tomcat corre — ahí se trunca con `: > catalina.out` porque el proceso mantiene el file handle abierto).
2. Se instaló `/etc/logrotate.d/alfresco-tomcat` con `rotate 7` / `maxage 7` para `localhost_access_log*.txt`, `catalina.out` (con `copytruncate`, `maxsize 200M`) y el resto de `*.log` de Tomcat/Alfresco, para que no se repita.

**Efecto colateral descubierto — pérdida de marcaciones del reloj biométrico durante la caída:** con la red y Apache/Laravel funcionando normal pero Postgres caído, el reloj ZKTeco (`VDE2261200055`) sí intentó enviar sus marcaciones en tiempo real (`Realtime=1`), pero `ZktecoController` dejaba que la excepción de conexión a BD reventara como error 500 HTML sin manejar. El reloj recibía *una respuesta* (aunque fuera de error) y no reintentaba esa transacción — a diferencia de un corte de red puro (sin respuesta / timeout), donde el reloj sí reintenta solo hasta entregar el dato exitosamente. Confirmado con dos pruebas controladas en producción: (a) desconectar el cable de red → el reloj reintentó y sincronizó solo con la hora original; (b) bajar `postgresql-13` a propósito, timbrar, y volver a subirlo → **antes del fix esto se perdía, después del fix la marcación llegó con la hora real** una vez restablecida la BD.

**Fix aplicado en `ZktecoController.php`:** las 4 rutas del protocolo ADMS que usa el reloj (`cdata` GET/handshake, `cdata` POST/marcaciones reales, `getrequest`, `registry`, `devicecmd`) ahora envuelven su lógica en `try/catch` y, ante cualquier falla (típicamente de conexión a BD), responden `"ERROR"` en texto plano (helper privado `errorAdms()`) en vez de dejar pasar el error 500 de Laravel — ese es el formato que el protocolo ADMS ya usa para los rechazos (mismo que la respuesta 403 de dispositivo no autorizado), y es lo que le permite al reloj reconocer el rechazo y reintentar más tarde en vez de darlo por entregado.

**Limitación conocida:** las marcaciones perdidas la mañana del 2026-07-17 (antes de aplicar el fix, en el grupo piloto del reloj biométrico) no se recuperan automáticamente — el fix solo corrige el comportamiento hacia adelante. Para ese día puntual se decidió no hacer corrección manual (dejar el atraso registrado tal cual); si se necesitara justificar sin afectar vacaciones, la vía es aprobar un permiso `tipo_horario=ENTRADA` con una razón `descontable=NO` en `dbo.d2_razon` (ver sección Permisos).

---

## Autenticación

- Login: `POST /api/login` con `identificacion` + `password`
- Guard usa modelo `Empleado` (tabla `dbo.ad_empleado`), no el `User` de Laravel
- Token Sanctum en **sessionStorage** (no localStorage — sesión se cierra al cerrar el navegador); Axios lo inyecta en cada request
- 401 → limpia token y redirige a `/login`

## Roles

Roles: `ADMINISTRADOR`, `TALENTO HUMANO`, `TH ACCIONES PERSONAL`, `TH NOMINA`, `SUPERVISOR`, `ADQUISICIONES`, `TRANSPORTE`, `CONDUCTOR`, `MAXIMA AUTORIDAD`, `CONTABILIDAD`, `PRESUPUESTO`, `DIRECTOR FINANCIERO`, `TESORERIA`, `COMISIONADO EXTERNO`. Empleados sin rol = acceso básico.
- Backend: `DB::table('dbo.admin_usuario_rol')` — sin Laravel policies/gates
- Frontend: `auth.tieneRol('NOMBRE')` desde Pinia store
- Menú filtrado por rol desde `dbo.admin_opcion`

## Base de Datos

- Schema `dbo` → Talento Humano | Schema `adq` → Adquisiciones (misma BD PostgreSQL)
- Empleados: PK = `id_emp` (string); estados `ACTIVO`/`INACTIVO` (nunca eliminar)
- Depto 999 excluido de todas las consultas (placeholder de sistema)
- `dbo.d2_configuracion` → parámetros globales (clave/valor/descripcion). Campos de auditoría: `created_at`, `created_by`, `updated_at`, `updated_by`. La query siempre usa `LOWER(concepto)` porque los conceptos se guardan en MAYÚSCULAS. Migración `000030` agregó `descripcion`, migración `000031` agregó auditoría.
- `dbo.ad_departamento` → numeración manual recomendada: padres en múltiplos de 10 (10,50,60,70,80,90), hijos en +1 a +9 del padre. Al crear desde la app, el campo ID es opcional; si se omite genera el siguiente correlativo (excluyendo 999). Campos de auditoría implementados (migración `000032`): `created_at`, `created_by`, `updated_at`, `updated_by`.
- `dbo.ad_empleado` → campos de auditoría implementados (migración `000032`): `created_at`, `created_by`, `updated_at`, `updated_by`. Campos adicionales: `puede_solicitar_vehiculo BOOLEAN DEFAULT false`, `sexo VARCHAR(10) NULL` (MASCULINO/FEMENINO), `tipo_sangre VARCHAR(5) NULL` (A+, A-, B+, B-, AB+, AB-, O+, O-) — migración `000063`. Campos SERCOP: `num_sercop VARCHAR(50) NULL`, `fecha_vence_sercop DATE NULL` — migración `000065`. Campos sociales — migración `000070`: `grupo_vulnerable_id`, `grupo_prioritario_id`, `tiene_discapacidad`, `tipo_discapacidad_id`, `porcentaje_discapacidad`, `tiene_enfermedad_catastrofica`, `enfermedad_catastrofica_id`, `tiene_persona_sustituta`, `sustituta_alfresco_id`, `sustituta_nombre_archivo`, `sustituta_fecha_caducidad`, `num_hijos_mayores`. Campos de baja/comisión — migración `000073`: `motivo_salida VARCHAR(50) NULL`, `motivo_reactivacion VARCHAR(50) NULL`, `institucion_comision VARCHAR(200) NULL`.
- `dbo.ad_empleado_hijo` — hijos menores de edad: `id_emp`, `nombre NULL`, `fecha_nacimiento` — migración `000071`. Sin límite de registros. El sistema calcula si el hijo es menor de 5 años (derecho a guardería).
- `dbo.ad_empleado_teletrabajo` — historial de períodos de teletrabajo habilitados: `id_emp`, `fecha_desde`, `fecha_hasta`, `created_by`, timestamps — migración `000086`. El backend valida que hoy esté dentro de un período activo antes de aceptar marcaciones TELETRABAJO. Rutas: `GET|POST /empleados/{id}/teletrabajo`, `DELETE /empleados/{id}/teletrabajo/{periodoId}`. Métodos en `EmpleadoController`: `teletrabajoIndex`, `teletrabajoStore`, `teletrabajoDestroy`.
- Catálogos sociales precargados (migración `000069`): `dbo.ad_grupo_vulnerable` (8 registros), `dbo.ad_grupo_prioritario` (8), `dbo.ad_tipo_discapacidad` CONADIS (7), `dbo.ad_enfermedad_catastrofica` MSP (15).
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
- Guardar `entry.id` en DB tras subir; descargar con `GET /nodes/{id}/content`
- Helpers `getDocLibNodeId()` y `getOrCreateFolderNodeId()` repetidos en cada controlador que usa Alfresco

### Estructura de carpetas estandarizada

```
talentohumano/
  acciones-personal/{año}/
  horas-extras/{año}/
  planificacion-vacaciones/{año}/
  permisos/{año}/{cedula_APELLIDO}/
```

### Patrón de subida preferido — `relativePath`

Usar el campo `relativePath` en el POST de upload. Alfresco crea las carpetas intermedias automáticamente, reduciendo de 5-8 llamadas HTTP a solo 2:

```php
$upload = Http::withBasicAuth($user, $pass)
    ->attach('filedata', file_get_contents($archivo->getRealPath()), $nombre)
    ->post("{$this->alfrescoBase}/nodes/{$docLibId}/children", [
        'name'         => $nombre,
        'nodeType'     => 'cm:content',
        'relativePath' => 'modulo/{año}',   // crea carpetas automáticamente
        'autoRename'   => true,
    ]);
```

### `getOrCreateFolderNodeId()` — filtro por nombre en PHP

El filtro `where=(isFolder=true AND name='X')` de Alfresco es poco fiable (puede devolver vacío aunque la carpeta exista). La implementación correcta: traer **todas** las carpetas con `(isFolder=true)` y filtrar por nombre en PHP. Además manejar 409 (carpeta ya existe por concurrencia):

```php
$buscarPorNombre = function (string $parent, string $nombre): ?string {
    $resp    = Http::withBasicAuth(...)->get(".../nodes/{$parent}/children", ['where' => '(isFolder=true)', 'maxItems' => 500]);
    $entries = $resp->json('list.entries') ?? [];
    foreach ($entries as $e) {
        if ($e['entry']['name'] === $nombre) return $e['entry']['id'];
    }
    return null;
};
$found = $buscarPorNombre($parentNodeId, $folderName);
if ($found) return $found;
$create = Http::withBasicAuth(...)->post(".../nodes/{$parentNodeId}/children", ['name' => $folderName, 'nodeType' => 'cm:folder']);
if ($create->status() === 409) {  // ya existía (race condition)
    $found = $buscarPorNombre($parentNodeId, $folderName);
    if ($found) return $found;
}
if (!$create->successful()) abort(502, 'No se pudo crear la carpeta en Alfresco');
return $create->json('entry.id');
```

### Descarga de documentos en el frontend (blob URL)

```js
const resp = await api.get(`/endpoint/descargar`, { responseType: 'blob' })
const blob = new Blob([resp.data], { type: resp.headers['content-type'] || 'application/pdf' })
const url  = URL.createObjectURL(blob)
window.open(url, '_blank')
setTimeout(() => URL.revokeObjectURL(url), 60000)   // revocar después de 60s, no inmediatamente
```

---

## Módulo Talento Humano

### Controladores clave

| Controlador | Función |
|---|---|
| `AuthController` | Login / logout / me |
| `EmpleadoController` | CRUD empleados + asignación de roles + partidas disponibles |
| `AccionPersonalController` | Acciones (encargo, subrogación, ingreso, vacaciones, destitución, cesación) |
| `VacacionesController` | Solicitudes de vacaciones (aprobar/negar/saldo) — al aprobar, modal pide empleado backup del mismo departamento |
| `PlanificacionVacController` | Planificación anual de vacaciones — estados `ELIMINADO` y `NEGADO` permiten re-planificar; fechas de períodos se validan contra el año planificado |
| `LiquidacionVacController` | Liquidación por comisión/desvinculación |
| `ReportePlanificacionController` | PDF planificación + subida Alfresco |
| `PermisosController` | Permisos y licencias — incluye `anular()` para TH/Admin |
| `AsistenciaController` | Marcaciones y reportes de asistencia |
| `CuadreController` | Conciliación de asistencia (atrasos) |
| `HorasExtrasController` | Planificación y registro de horas extras |
| `DashboardController` | Estadísticas del dashboard — Admin/TH: métricas globales; Supervisor: pendientes + equipo hoy; TH: gráfico atrasos por coordinación |
| `ReportesController` | Reportes de atrasos, marcaciones no realizadas y movimientos de personal — todos con export Excel/PDF |
| `CertificadoLaboralController` | Certificados laborales — emitir (PDF+Alfresco), historial, re-descargar |
| `Admin/*` | Departamentos, causas, turnos, horarios, calendario, configuración, aportes IESS |

### Empleados (`dbo.ad_empleado`)

Campos relevantes:
- `estado`: `ACTIVO` / `INACTIVO` — nunca se elimina
- `estado_puesto`: `OCUPADO` / `VACANTE` / `DISPONIBLE` — DISPONIBLE = empleado inactivo, partida presupuestaria libre para reasignar
- `partida_individual` / `partida_presupuestaria`: identificadores de la partida MEF
- `programa` (VARCHAR 4) / `actividad` (VARCHAR 6): clasificación presupuestaria MEF (ej: 55 / 001); opcionales
- `modalidad_marcacion`: `PRESENCIAL` / `TEMPORAL` / `TELETRABAJO` (ver Control de Asistencia) — **REMOTO renombrado a TEMPORAL** (migración `000064`)
- `modalidad_laboral`: determina motivos válidos en liquidación de vacaciones
- `tipo_contrato`: `LOSEP` / `CODIGO DEL TRABAJO` — define tasa de vacaciones
- `sexo`: `MASCULINO` / `FEMENINO` / NULL — para estadísticas de género (migración `000063`)
- `tipo_sangre`: `A+`, `A-`, `B+`, `B-`, `AB+`, `AB-`, `O+`, `O-` / NULL (migración `000063`)
- `num_sercop` / `fecha_vence_sercop`: certificado SERCOP y fecha de vigencia (migración `000065`)
- `motivo_salida`: por qué pasó a INACTIVO — valores: `COMISIÓN DE SERVICIOS` / `FIN DE COMISIÓN DE SERVICIOS` / `FIN DE CONTRATO` / `RENUNCIA VOLUNTARIA` / `JUBILACIÓN` — migración `000073`
- `motivo_reactivacion`: por qué volvió a ACTIVO — valor actual: `RETORNO DE COMISIÓN DE SERVICIOS` — migración `000073`
- `institucion_comision`: nombre de la institución destino (si `motivo_salida = COMISIÓN DE SERVICIOS`) o institución de origen (si `modalidad_laboral = Comisión de Servicios` en empleado entrante) — migración `000073`
- `banco` / `tipo_cuenta` / `numero_cuenta`: datos bancarios del empleado para transferencias (migración `000081`); se auto-rellenan en el formulario de comisión al buscar un servidor
- `es_externo BOOLEAN DEFAULT false`: empleados creados automáticamente desde "Dar acceso" en Funcionarios Externos (migración `000082`). Cuando `es_externo = true`: EmpleadoForm muestra banner amarillo de advertencia y EmpleadoController bloquea la edición (solo editable desde FuncionariosExternosView). `id_depto = 999` — excluidos de todas las queries de RRHH (nómina, asistencia, distributivo, vacaciones).
- `extension VARCHAR(10) NULL`: extensión telefónica institucional del empleado — migración `000093`. Campo opcional; si está vacío sale en blanco. Visible en EmpleadoForm Tab 1 entre Teléfono y Email. Se usa en el reporte LOTAIP (Directorio y Distributivo).

**Partidas disponibles** (`GET /api/empleados/partidas-vacantes`): devuelve empleados con `estado=INACTIVO` + `estado_puesto=DISPONIBLE`. En `EmpleadoForm.vue`, el campo Partida Individual tiene input libre + botón "Seleccionar libre" que abre un modal con la lista — al seleccionar una fila se auto-llenan `partida_individual` y `partida_presupuestaria`.

### Control de Asistencia (`dbo.sg_control_persona`)

Tabla de marcaciones individuales. Flujo diario en orden estricto: `ENTRADA → SALIDA AL LUNCH → ENTRADA DEL LUNCH → SALIDA`

**modalidad_marcacion** controla cómo puede timbrar el empleado:
- `PRESENCIAL` (default): la IP del request debe comenzar con algún prefijo de `VLANS_PERMITIDAS` en `dbo.d2_configuracion`. Formato: `10.10.12.,10.10.26.` (prefijos con punto final, separados por coma). Si la lista está vacía se permite todo.
- `TEMPORAL`: puede marcar desde cualquier IP sin validación. `tipo_marcacion = 'WEB'`. Uso: comisiones, viajes temporales. (**antes se llamaba REMOTO** — migración `000064` actualizó todos los registros existentes)
- `TELETRABAJO`: puede marcar desde cualquier IP. `tipo_marcacion = 'TELETRABAJO'`. Uso: trabajo desde casa. **Requiere período activo** en `dbo.ad_empleado_teletrabajo` (migración `000086`); si no hay período vigente el backend rechaza la marcación con 403. TH gestiona los períodos desde la ficha del empleado (Tab 4).
- `BIOMETRICO`: marcación exclusivamente por reloj ZKTeco — el backend rechaza cualquier intento de marcación web con 403. Los botones de AsistenciaView se deshabilitan y muestran un banner ámbar explicativo. Útil cuando TH exige timbrado físico presencial. (migración `000086`)

**Validación de IP:** El backend corre detrás de Apache (proxy a puerto 9000). `bootstrap/app.php` tiene `trustProxies(at: '127.0.0.1')` para leer `X-Forwarded-For` y obtener la IP real del cliente. La query usa `LOWER(concepto) = 'vlans_permitidas'` porque en la BD el concepto está en mayúsculas (`VLANS_PERMITIDAS`).

**Variables de configuración relevantes para asistencia:**
- `VLANS_PERMITIDAS`: prefijos de red permitidos para marcación PRESENCIAL (ej: `10.10.12.,10.10.26.`)
- `CONTROL_IP_MARCACION`: valor `1` = una IP solo puede ser usada por un empleado por día (evita timbrar por otro)
- `ARTICULO_ATRASOS`: texto del artículo de ley que se muestra bajo "Mis Marcaciones" en AsistenciaView (configurable desde Admin → Configuración)

Campos clave de `sg_control_persona`: `nro_documento` (= id_emp), `clasificacion` (ENTRADA/SALIDA), `concepto` (ENTRADA/SALIDA AL LUNCH/ENTRADA DEL LUNCH/SALIDA), `fecha_hora`, `tipo_marcacion` (WEB/TELETRABAJO/**BIOMETRICO**), `ip`, `ubicacion`, `procesado` (SI/NO), `origen`.

**Marcación biométrica:** el reloj ZKTeco escribe en esta misma tabla con `tipo_marcacion = 'BIOMETRICO'`. El sistema asigna el concepto por secuencia del día sin importar el origen — se pueden combinar libremente marcaciones WEB, TELETRABAJO y BIOMETRICO.

**Cuadre** (`dbo.d2_cuadre_marcacion`): tabla con atrasos en minutos por día por empleado (`atraso_entrada`, `atraso_lunch`, `atraso_salida`, `horas_decto`, `horaspermiso_pag`). Se usa en el reporte personal de asistencia para mostrar atrasos y si están justificados por permisos aprobados.

**`ProcesarCuadre.php` — lógica de permisos aprobados:** antes de calcular atrasos, carga todos los permisos APROBADO del empleado para ese día desde `dbo.d2_permiso`:
- `tipo_horario = 'ENTRADA'` → define `$entradaJustificada` (hora hasta la que puede llegar tarde sin penalidad). Si `hora_real <= entradaJustificada` → `atraso_entrada = 0`.
- `tipo_horario = 'SALIDA'` → define `$salidaJustificada` (hora mínima de salida sin penalidad). Si `hora_real >= salidaJustificada` → `atraso_salida = 0`. Si `hora_real < salidaJustificada` → `atraso_salida = minutos(salidaJustificada - hora_real)`.
- `todo_dia = 'SI'` → `entradaJustificada = 99.0` y `salidaJustificada = 0.0` (todo el día justificado).

### Vacaciones — cálculo de saldo

Calculado en `calcularSaldoDisponible()` — usa helper `tasaVacaciones()` en VacacionesController:
- `LOSEP` → fijo 2.50 días/mes (30 días/año)
- `CODIGO DEL TRABAJO` → tasa variable según antigüedad (Art. 69 Código del Trabajo):
  - 0–5 años completos → 1.25 días/mes (15 días/año)
  - 6 años completos → +1 día adicional (16 días/año)
  - 7 años completos → +2 días adicionales (17 días/año)
  - ... hasta máximo +15 días adicionales (30 días/año desde los 20 años)
  - Fórmula: `años_servicio = floor(diffInYears(fecha_ingreso, hoy))` → `dias_extra = min(max(0, años-5), 15)` → `tasa_mensual = (15 + dias_extra) / 12`
- Fórmula saldo: `(días desde FECHA_CORTE_VACACIONES / 360) × (tasa_mensual × 12)`
- Si fecha_ingreso > fecha_corte, se usa fecha_ingreso como base
- Saldo = `dias_adicionales` (CSV) + devengado − `total_dias_tomados`
- **Empleados INACTIVOS con `fecha_salida`:** el acumulado se congela en `fecha_salida` (no sigue creciendo hasta hoy). Aplica en `ReporteVacacionesController` (reporte de saldo + kardex) y `LiquidacionVacController`. Condición: `estado = INACTIVO` AND `fecha_salida` no nulo.
- El response incluye `dias_adicionales_antiguedad` y `dias_anuales` para mostrar en UI
- Dashboard (empleado CT con 6+ años): chip "+X días/año por antigüedad" en tarjeta saldo
- Vista Vacaciones (empleado CT con 6+ años): badge azul "15 base + X por antigüedad"
- **CSV carga inicial:** el `saldo` debe incluir base + adicionales ya acumulados hasta fecha de corte
- **TOPE DE 60 DÍAS (LOSEP Art. 29) — REGLA CRÍTICA:** el saldo disponible que se muestra al empleado y que se valida al solicitar/planificar vacaciones tiene un máximo de 60 días. Si el cálculo interno supera 60, se muestra y valida como 60. El acumulado interno sigue corriendo normalmente (no se borra ni se congela), pero el empleado nunca puede ver ni solicitar más de 60 días disponibles. Implementado con `min(60, max(0, $disponibles))` en: `VacacionesController::calcularSaldoDisponible()`, `ReporteVacacionesController::calcularSaldoActual()`, `PlanificacionVacController::calcularSaldo()`. **Excepción:** `LiquidacionVacController` NO aplica el tope — usa el valor real acumulado para calcular el pago de liquidación por desvinculación.

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

**Estados del flujo:** `BORRADOR → ACTIVO` (estado final). TH ACCIONES PERSONAL crea en BORRADOR; `procesar()` pasa a ACTIVO y asigna `numero_accion`. `numero_accion` es nullable — se asigna al procesar, no al crear.

**Métodos del controlador:**
- `procesar($id)` — `PATCH /api/acciones-personal/{id}/procesar` — cambia estado a ACTIVO, asigna número de acción, sube PDF firmado a Alfresco
- `editarBorrador($id)` — `PATCH /api/acciones-personal/{id}/editar-borrador` — permite modificar motivación, fecha de elaboración y firmantes mientras está en BORRADOR

**PDFs y reportes:**
- `accion_personal.blade.php` — PDF individual; usa `{!! !!}` (no `{{ }}`) para entidades HTML como `&nbsp;` en checkboxes y para el campo `motivacion` cuando contiene HTML de TipTap
- `acc_lista.blade.php` — PDF de listado de acciones con filtros
- Excel export disponible (requiere `phpoffice/phpspreadsheet` instalado en servidor: `composer require phpoffice/phpspreadsheet`)
- PDF firmado se sube a Alfresco en `acciones-personal/{año}/`
- **Vista previa en BORRADOR**: el endpoint `GET /api/acciones-personal/{id}/pdf` funciona en cualquier estado. El PDF muestra `— BORRADOR — BORRADOR — BORRADOR —` en gris encima del encabezado cuando `$accion->estado === 'BORRADOR'`; esa línea desaparece al procesar.

**Campo `motivacion` con HTML (TipTap):** el editor TipTap en el formulario guarda HTML (`<p>`, `<strong>`, etc.). El blade detecta si el contenido es HTML con `str_contains($motivacion, '<p>')` y lo renderiza con `{!! !!}`; si es texto plano (registros anteriores) usa `{{ }}` con `white-space:pre-wrap`.

**Firmantes por acción — migración `000087`:** se agregaron 4 columnas VARCHAR(200) NULL a `dbo.acc_accion_personal`:
- `firmante_th_nombre`, `firmante_th_cargo` — Responsable de TH para esta acción específica
- `firmante_autoridad_nombre`, `firmante_autoridad_cargo` — Autoridad Nominadora para esta acción

Flujo de firmantes:
- Al **crear**: se pre-llenan desde `dbo.d2_configuracion` (`FIRMANTE_TH_NOMBRE`, etc.) pero el usuario puede editarlos antes de guardar
- Al **editar borrador**: el modal muestra los firmantes guardados en la acción; `index()` los puebla desde config para acciones BORRADOR que tengan los campos vacíos (registros previos a migración `000087`)
- En el **PDF**: usa los firmantes de la acción con fallback a `d2_configuracion` y luego a los parámetros anteriores (`DIRECTOR_TALENTO_HUMANO` / `APROBADOR_ACCION_PERSONAL`)
- Todos los valores se guardan en MAYÚSCULAS (`strtoupper`)
- Endpoint config para pre-llenar formulario nuevo: `GET /api/configuracion/firmantes` → `{ firmante_th_nombre, firmante_th_cargo, firmante_autoridad_nombre, firmante_autoridad_cargo }`

### Vacaciones — backup al aprobar

Al aprobar una solicitud de vacaciones, el supervisor debe seleccionar un empleado de backup del mismo departamento. Campos en `dbo.d2_vacacion`: `backup_id` (VARCHAR 20, nullable), `backup_nombre` (VARCHAR 300, nullable). Migración `000055`. El endpoint `PATCH /api/vacaciones/{id}/aprobar` acepta `backup_id` y `backup_nombre` opcionales. Endpoint auxiliar: `GET /api/vacaciones/{id}/empleados-depto` — lista empleados activos del mismo departamento del solicitante.

### Permisos y Licencias (`dbo.d2_permiso`)

**Estados:** `PENDIENTE → APROBADO / NEGADO / ELIMINADO / ANULADO`

**`tipo_horario`:** `ENTRADA` / `ENTRE JORNADA` / `SALIDA`. Determina qué parte del día justifica:
- `ENTRADA`: justifica llegada tarde (cuadre compara con hora_hasta del permiso)
- `SALIDA`: justifica salida anticipada (cuadre compara con hora_desde del permiso)
- `ENTRE JORNADA`: justifica atraso de retorno del lunch

**Descuento de vacaciones:** ocurre **inmediatamente al aprobar** (no en el cuadre nocturno). Se calcula sobre las horas del permiso (`hora_desde`/`hora_hasta`), no sobre la marcación real del empleado.

**FACTOR PROPORCIONAL SÁBADOS/DOMINGOS — REGLA CRÍTICA:** Los 30 días de vacaciones LOSEP se componen de 22 días hábiles + 8 días de fin de semana (4 sábados + 4 domingos). Por eso cada día hábil de permiso descontable carga **1.3636 días** del saldo (factor = 30/22). Aplica a LOSEP y Código del Trabajo, y tanto a permisos por horas como de día completo. El método `anular()` usa el mismo factor para revertir exactamente lo descontado.
```php
$factorFds     = 30 / 22;  // 1.3636...
// Permiso por horas:
$diasDescuento = round(diffInMinutes(hora_desde, hora_hasta) / 60 / $horasJornada * $factorFds, 4);
// Permiso día completo:
$diasDescuento = round($diasBase * $factorFds, 4);
```
Ejemplos: 1 hora → 0.1705 días | 4 horas → 0.6818 días | 1 día completo → 1.3636 días

**Validación de solapamiento:** la validación al crear un permiso filtra por `tipo_horario` — un permiso ENTRADA **no bloquea** la creación de un permiso SALIDA del mismo día aunque compartan rango de fechas. Solo bloquea permisos del **mismo tipo** que se crucen en horario.

**Tope de 60 días (LOSEP Art. 29) y descuento de permisos — REGLA CRÍTICA:** el saldo visible al empleado es máximo 60 días (`min(60, saldoInterno)`). Al aprobar un permiso descontable, el descuento se aplica **desde los 60 días visibles**, no desde el saldo interno real (que puede ser 70, 80, etc.). Implementado con campo `dias_descuento_efectivo DECIMAL(10,4) NULL` en `dbo.d2_permiso` (migración `000094`):
- `aprobar()`: calcula `internoSaldo` (sin tope), `exceso = max(0, internoSaldo - 60)`, `efectivo = exceso + diasDescuento` → suma `efectivo` a `total_dias_tomados` y lo guarda en `dias_descuento_efectivo`
- `anular()`: revierte usando `dias_descuento_efectivo` guardado (compatible con permisos anteriores a migración `000094` → usa `diasDescuento` si el campo es null)
- `LiquidacionVacController` NO aplica el tope — usa el valor real acumulado (correcto para pago por cesación)
- El saldo interno sigue acumulando sin límite; solo el display y el descuento de permisos están limitados a 60

**`PATCH /api/permisos/{id}/anular`** — solo ADMINISTRADOR / TALENTO HUMANO:
- Requiere campo `observacion_negacion` (motivo)
- Cambia estado a `ANULADO`
- Si `descontable = 'SI'`: revierte usando `dias_descuento_efectivo` del permiso (o `diasDescuento` si null)
- Revierte el campo correspondiente en `d2_cuadre_marcacion` del día del permiso
- Uso: permiso aprobado que el empleado no utilizó (ej. salió a su hora normal)

**Vista personal de asistencia:** muestra `atraso` (minutos medidos) + `justificado`:
- `"TOTAL"` si `minutos_permiso >= atraso` → badge verde "Justificado"
- `"PARCIAL"` si `minutos_permiso > 0 pero < atraso` → badge naranja
- `"NO"` → badge rojo
- El "14min Justificado" significa: llegaste 14 min tarde PERO hay un permiso que lo cubre → no se descuenta. No indica minutos pendientes de descuento.

**Advertencia de tipo_horario:** un permiso con `tipo_horario = 'ENTRADA'` y horas amplias (ej. 10:00-16:30) puede hacer que el cuadre y la vista personal muestren el día como "Justificado" para la entrada aunque el rango no tenga sentido semánticamente. Verificar que el tipo_horario sea correcto al crear permisos.

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
TH NOMINA procesa con N° memorando → PROCESADO
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
- Botones PDF (planificación): visibles en estados APROBADO y PROCESADO.

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
| PATCH | `/planificacion/{id}/autorizar` | TH NOMINA procesa (estado → PROCESADO) |
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
views/empleados/        # CRUD empleados, detalle, importación, distributivo, reporte de personal
                        # ReporteEmpleadosView.vue — ruta: empleados/reporte — roles TH/ADMIN
                        #   Sección 1: 5 tarjetas de alerta clickables (SERCOP vencido/próximo,
                        #     Sustituta vencida/próxima, Guardería) → al clic aplica el filtro automáticamente
                        #   Sección 2: 3 gráficos Chart.js — donut por sexo, barras por tipo contrato,
                        #     barras por modalidad de marcación (datos del endpoint resumen)
                        #   Sección 3: panel de filtros colapsable con badge de filtros activos —
                        #     16 filtros: departamento, estado, búsqueda, tipo_contrato, modalidad_marcacion,
                        #     sexo, tipo_sangre, discapacidad, enf. catastrófica, grupo vulnerable,
                        #     grupo prioritario, hijos<5 (guardería), persona sustituta, SERCOP, vehículo,
                        #     motivo_salida
                        #   Sección 4: tabla con badges de color para fechas vencidas/próximas + paginación
                        #   Exportar Excel (PhpSpreadsheet, 26 columnas, filas alternadas) y PDF (landscape A4)
                        #   Controlador: ReporteEmpleadosController.php
                        #   Rutas: GET /api/empleados/reporte/resumen (alertas + stats)
                        #          GET /api/empleados/reporte (listado filtrado + ?formato=excel|pdf)
                        #   IMPORTANTE: campo cédula en dbo.ad_empleado se llama `identificacion`, NO `cedula`
views/empleados/        # CRUD empleados, detalle, importación, distributivo
                        # EmpleadoForm: campos con bg-gray-50 + border-gray-300 en reposo, focus:bg-white
                        #   (clase .input-field en <style scoped>) — distingue visualmente los campos en fondo blanco
                        # EmpleadoForm: reorganizado en 4 pestañas con diseño visual atractivo:
                        #   Tab 4 "Asistencia": modalidad_marcacion (radio cards: PRESENCIAL/TEMPORAL/TELETRABAJO/BIOMETRICO)
                        #     Cuando modalidad = TELETRABAJO (solo en edición): sección "Períodos de Teletrabajo Habilitados"
                        #       tabla historial (fecha_desde, fecha_hasta, registrado por, estado: Activo/Futuro/Vencido)
                        #       botón "Agregar período" → formulario inline con fecha_desde y fecha_hasta
                        #       badges de estado calculados en frontend con hoy()
                        #     Cuando modalidad = BIOMETRICO: solo se muestra el radio seleccionado (sin sección adicional)
                        #   Tab 1 "Datos Personales": nombres, apellidos, cédula, teléfono, extensión (opcional), email, dirección, sexo, tipo_sangre
                        #     + grupo_vulnerable, grupo_prioritario (selects de catálogos sociales)
                        #     + bloque Discapacidad (toggle → tipo CONADIS + porcentaje %)
                        #     + bloque Enfermedad Catastrófica (toggle → tipo MSP)
                        #     + bloque Persona Sustituta (toggle → fecha caducidad + subir/ver/eliminar PDF Alfresco)
                        #     + bloque Hijos: num_hijos_mayores + lista dinámica menores con fecha nacimiento
                        #       badge "Guardería" automático si hijo < 5 años
                        #     + bloque Datos Bancarios: banco (text-transform:uppercase), tipo_cuenta (select AHORROS/CORRIENTE),
                        #       numero_cuenta (text-transform:uppercase) — migración 000081
                        #   Tab 2 "Cargo y Contrato": departamento, cargo, tipo_contrato, modalidad_laboral, jornada,
                        #     estado, fecha_ingreso, fecha_salida (v-if INACTIVO), salario
                        #     + motivo_salida (select, v-if INACTIVO): COMISIÓN DE SERVICIOS / FIN DE COMISIÓN / FIN DE CONTRATO / RENUNCIA / JUBILACIÓN
                        #     + institucion_comision "Institución Destino" (v-if INACTIVO && motivo_salida=COMISIÓN DE SERVICIOS)
                        #     + institucion_comision "Institución de Origen" (v-if modalidad_laboral=Comisión de Servicios, aplica activos e inactivos)
                        #     + motivo_reactivacion (select, v-if ACTIVO && motivo_salida previo registrado): RETORNO DE COMISIÓN DE SERVICIOS
                        #   Tab 3 "Datos del Puesto": grupo_ocupacional, grado, proceso_institucional, estado_puesto,
                        #     partida_individual (+ botón "Seleccionar libre"), programa, actividad, décimos, fondos_reserva,
                        #     "Estructura Programática" (antes "Partida Presupuestaria"), N° SERCOP, Vigencia SERCOP
                        #   Tab 4 "Asistencia": modalidad_marcacion (radio cards: PRESENCIAL/TEMPORAL/TELETRABAJO), puede_solicitar_vehiculo
                        #   Botones Guardar/Cancelar al final del formulario (no fijos — no tapan el sidebar)
                        #   Foto compacta fuera de las pestañas (solo en edición)
                        # Endpoints hijos: GET|POST /empleados/{id}/hijos, DELETE /empleados/{id}/hijos/{hijoId}
                        # Endpoints sustituta: POST|GET|DELETE /empleados/{id}/sustituta-doc (Alfresco, carpeta empleados/{cedula_APELLIDO}/)
                        # GET /empleados/catalogos-sociales → { grupos_vulnerables, grupos_prioritarios, tipos_discapacidad, enfermedades_catastroficas }
views/acciones/         # Acciones de personal (lista + formulario + PDF)
                        # HistorialRemuneracionesView.vue — ruta: acciones-personal/historial-remuneraciones
                        #   Reporte de cargos y remuneraciones por empleado: todas las acciones de personal
                        #   con el cargo y sueldo que tenía en cada rol, el sueldo actual y la diferencia
                        #   Tipos incluidos: INGRESO, ENCARGO, SUBROGACION, CESACION DE FUNCIONES, DESTITUCION
                        #   INGRESO/ENCARGO/SUBROGACION → usa propuesto_cargo + propuesto_remuneracion
                        #   CESACION/DESTITUCION → usa actual_cargo + actual_remuneracion
                        #   Filtros: empleado (autocomplete debounced), fecha_desde, fecha_hasta, tipos (checkboxes)
                        #   Export PDF (landscape A4, acc_historial_remuneraciones.blade.php) + Excel (PhpSpreadsheet)
                        #   Diferencia en verde (+) o rojo (-); solo incluye acciones en estado ACTIVO (excluye BORRADOR)
                        #   Ruta API: GET /api/acciones-personal/historial-remuneraciones [?formato=pdf|excel]
                        #   Método: AccionPersonalController::historialRemuneraciones()
views/planificacion/    # Planificación anual de vacaciones, liquidación, reporte
                        # ReporteSaldoVacView.vue — reporte de saldo de vacaciones
                        #   Acceso: TH/ADMIN ven todos los empleados; empleado normal ve solo su propio saldo
                        #   Toggle "Resumido / Detallado" visible para todos los roles
                        #     — empleado normal: carga automáticamente su registro y expande el kardex al entrar
                        #     — TH/ADMIN: pueden buscar por empleado/departamento y paginar
                        #   Botón "Cargar Saldos" y "Editar saldo individual": solo visible para TH/ADMIN
                        #   Botón "Cargar Saldos": modal para subir CSV (cedula,saldo) + nueva fecha de corte
                        #     → actualiza dias_adicionales + total_dias_tomados=0 en d2_cabecera_vacacion
                        #     → actualiza FECHA_CORTE_VACACIONES en d2_configuracion
                        #   Botón "Editar saldo individual": modal para ajustar el saldo de UN empleado
                        #     → pide el saldo disponible total DESEADO (no dias_adicionales directamente)
                        #     → back-calcula: dias_adicionales = saldo_deseado + tomados - acumulado
                        #     → NO toca FECHA_CORTE_VACACIONES ni datos de otros empleados
                        #     → Ruta: PATCH /api/reporte-vacaciones/{id_emp}/saldo
                        #   PDF descargable (resumido): GET /api/reporte-vacaciones/pdf — solo TH/ADMIN
                        #   Rutas: GET /api/reporte-vacaciones, GET /api/reporte-vacaciones/{id_emp},
                        #          GET /api/reporte-vacaciones/pdf, POST /api/reporte-vacaciones/cargar-saldos,
                        #          PATCH /api/reporte-vacaciones/{id_emp}/saldo
                        #   Controlador: ReporteVacacionesController.php
                        #     — index(): TH/ADMIN → todos los empleados; empleado → solo su propio registro
                        #     — detalle(): TH/ADMIN → cualquier empleado; empleado → solo su propio id_emp (403 si otro)
                        #     — tiene su propio helper `tasaVacaciones()` (igual que VacacionesController)
                        #       que calcula la tasa correcta incluyendo días adicionales por antigüedad (CT)
                        #     — NO usar tasa fija 1.25 para CÓDIGO DEL TRABAJO: siempre llamar tasaVacaciones()
                        #   Kardex: dias legados (sin registro en d2_vacacion) aparecen como fila "registros anteriores"
                        #     "registros anteriores" = total_dias_tomados − suma de registros individuales d2_vacacion
                        #     el CSV resetea total_dias_tomados=0; el saldo del CSV ya incluye días tomados descontados
                        #   Kardex fila DEVENGADO: descripción incluye "X días/año (15 base + Y por antigüedad)"
                        #     cuando el empleado CT tiene 6+ años de servicio
views/permisos/         # Permisos y licencias — fecha_desde/fecha_hasta default = hoy al abrir modal
                        # Permisos NO descontables (descontable='NO'): sección "Documentos de respaldo"
                        #   - Subir/ver/eliminar archivos solo cuando estado_permiso = 'PENDIENTE'
                        #   - Tabla dbo.d2_permiso_documento: permiso_id, tipo_doc, nombre_archivo, alfresco_id, created_by
                        #   - Carpeta Alfresco: permisos/{año}/{cedula_APELLIDO}/
                        #   - Rutas: GET|POST /permisos/{id}/documentos, GET|DELETE /permisos/{id}/documentos/{docId}/descargar
                        #   - Descarga usa blob URL con window.open + revoke después de 60s
                        # Estado ANULADO: badge naranja en tabla; botón "Anular" visible para TH/Admin
                        #   en permisos con estado APROBADO; abre modal con campo motivo obligatorio
                        # Filtro de estado incluye opción "ANULADO" en el select de estados
                        # Aviso "⚠ Sin atraso" (badge ámbar) visible SOLO para el supervisor en tab "equipo",
                        #   junto al botón Aprobar, cuando el permiso es descontable=SI, tipo ENTRADA/SALIDA,
                        #   la fecha ya pasó (≤ hoy) Y d2_cuadre_marcacion muestra atraso=0 ese día.
                        #   Lógica en PermisosController::index() post-pagination — campo `sin_atraso` bool.
                        #   Si la fecha es futura o el cuadre aún no procesó ese día → sin_atraso=false (sin aviso).
                        #   No bloquea la aprobación, es informativo. Solo permisos de tipo ENTRADA o SALIDA.
DashboardView.vue       # Admin/TH: métricas globales (Empleados, Departamentos, Permisos)
                        # Supervisor (no admin): 4 tarjetas pendientes (permisos/vacaciones/HE/materiales)
                        #   + widget "Mi equipo hoy" (presentes/permiso/vacaciones/sin marcar + barra)
                        #   + atrasos del mes del equipo
                        #   + tarjeta "Vacaciones próximas del equipo" (próximos 60 días o en curso)
                        #     botón "Ver quiénes" expande lista con nombre, fechas y badge En curso/Próximo
                        # Las tarjetas originales se ocultan para supervisores (v-if="!es_supervisor||es_admin_th")
                        # Gráfico personal "Mis trámites personales no justificados por mes" (TODOS los roles):
                        #   GET /api/dashboard/atrasos-coordinacion → { meses, datos[] }
                        #   Muestra solo los días del empleado logueado con atraso SIN permiso aprobado que lo cubra
                        #   Colores: verde=#d1fae5 (0), ámbar=#fbbf24 (1-2), rojo=#dc2626 (3+)
                        #   Backend usa SQL puro (DB::select) con NOT EXISTS correlacionado — NO usar Query Builder
                        #     para este patrón porque no genera correctamente el alias de la query externa en PostgreSQL
                        #   Título deliberadamente "trámites personales" (no "atrasos") para no confundir con
                        #     el término "atraso" del reglamento disciplinario (2 atrasos = amonestación verbal)
                        # Empleado sin rol especial: 3 tarjetas compactas en una sola fila (lg:grid-cols-3)
                        #   1. Permisos pendientes  2. Saldo vacaciones  3. Próximo período de vacaciones
                        #   Tarjeta 3: título cambia automáticamente "VACACIONES EN CURSO" vs "PRÓXIMO PERÍODO"
                        #     color ámbar si en curso, verde oscuro si próximo; muestra fechas y días
                        #     countdown "X días para vacaciones" si es futuro
                        #   Solo muestra períodos del año actual con estado APROBADO o REPLANIFICADO
                        #   GET /api/dashboard → datos_empleado.proximo_periodo + datos_supervisor.vacaciones_proximas[]
views/asistencia/       # Reporte de asistencia personal y admin
                        # AsistenciaView: botones de marcación con íconos PNG desde /public/marcacion/
                        #   Archivos: marcacion_entrada.png, marcacion_salida_almuerzo.png,
                        #             marcacion_entrada_almuerzo.png, marcacion_salida.png
                        #   3 estados visuales con colores hex via inline style (btnStyle()):
                        #     apagado (#c3dbd7) = ya timbrado | activo (#0b5547) = siguiente a timbrar
                        #     por_activarse (#068174) = aún no le toca
                        #   Tamaño responsivo: w-28 h-28 móvil / md:w-52 md:h-52 PC (íconos)
                        #   Contorno del botón reducido: py-2 md:py-3 (solo padding, íconos sin cambio)
                        #   Bloqueo por modalidad: `miEstado()` retorna `puede_marcar` (bool) y `mensaje_bloqueo` (string)
                        #     BIOMETRICO → puede_marcar=false, mensaje "Tu marcación es exclusivamente por reloj biométrico"
                        #     TELETRABAJO sin período activo → puede_marcar=false, mensaje "Tu período de teletrabajo ha vencido..."
                        #     Banner ámbar en AsistenciaView cuando puede_marcar=false; botones bloqueados (disponible=false)
                        #   Imágenes precargadas en onMounted con new Image() para evitar flash
                        #   Confirmación al marcar SALIDA antes de las 16:30 con window.confirm()
                        #   ARTICULO_ATRASOS: se muestra con fondo #0b5447 y texto blanco (text-sm)
                        #     debajo del título "Mis Marcaciones" — más visible que el texto gris anterior
                        #   Columna "Atraso" en tabla historial: en fila ENTRADA DEL LUNCH, si hay atraso,
                        #     muestra "Debió: HH:MM" en ámbar — hora a la que debió regresar (SALIDA AL LUNCH + 30 min)
                        #     Calculado en frontend con horaDebiRegresarLunch(fecha): busca la marcación
                        #     SALIDA AL LUNCH del mismo día y le suma 30 minutos. Sin cambios de backend.
views/horasextras/
  HorasExtrasView.vue   # 4 tabs:
                        #   MI PLANIFICACIÓN: crear/editar, PDF planificación, subir PDF firmado
                        #   MIS HORAS TRABAJADAS: registrar horas, PDF horas trabajadas
                        #   PLANIFICACIONES DEL EQUIPO: aprobar/negar (supervisor/admin)
                        #   REGISTROS DEL EQUIPO: revisar/confirmar/negar + desglose monetario (TH NOMINA)
                        #   Badge naranja "Dev. Xv" en registros devueltos al empleado (campo devuelto_count en nom_he_registro)
views/asistencia/
  ReporteSinAtrasosView.vue  # Vista standalone (ruta asistencia/sin-atrasos) — INTEGRADA como tercer tab
                             # en views/reportes/ReportesView.vue (tab "Sin Atrasos")
                             # NO debe tener entrada de menú propia (sería duplicado) — eliminar si existe
views/reportes/
  LotaipView.vue             # Reporte LOTAIP Art. 7 lit. m) — ruta: reportes/lotaip (página independiente, NO tab de ReportesView)
                             # Roles: TALENTO HUMANO, ADMINISTRADOR — agregar en Admin → Opciones de Menú con URL reportes/lotaip
                             # 2 tabs solo con export Excel (sin PDF):
                             #   Tab 1 "Directorio y Distributivo": Nro, Apellidos y Nombres, Dirección/Área,
                             #     Dirección Institucional (config DIRECCION_INSTITUCIONAL), Ciudad (config UBICACION_DEFAULT),
                             #     Teléfono (config TELEFONO_INSTITUCIONAL), Extensión, Correo Electrónico institucional
                             #   Tab 2 "Remuneraciones": Nro, Cargo, Tipo Contrato, Partida Individual, Grado,
                             #     Salario Base, Remuneración Anual (sueldo×12), D13 (en blanco), D14 (en blanco)
                             # Ordenado alfabéticamente por apellido ASC (sin agrupación por departamento)
                             # Email desde dbo.ad_empleado_mail (primer registro ACTIVO por empleado)
                             # Controlador: LotaipController.php — rutas:
                             #   GET /api/reportes/lotaip/directorio [?formato=excel]
                             #   GET /api/reportes/lotaip/remuneraciones [?formato=excel]
                             # Variables de configuración requeridas: DIRECCION_INSTITUCIONAL, UBICACION_DEFAULT, TELEFONO_INSTITUCIONAL
  ReportesView.vue           # 5 tabs: Atrasos | Marcaciones No Realizadas | Sin Atrasos | Movimientos de Personal | Marcaciones del Día
                             # Filtros comunes: fecha_desde, fecha_hasta, departamento, empleado
                             # Filtros depto/empleado se ocultan automáticamente en tab "Sin Atrasos"
                             # Botones Excel + PDF visibles cuando datos.length > 0 (excepto Sin Atrasos y Marcaciones del Día)
                             # Export usa blob URL + a.download (no window.open)
                             # IMPORTANTE Vue 3: nunca poner v-for y v-if en el mismo elemento — usar
                             #   <template v-for><span v-if> anidado. Si ambos están en el mismo tag
                             #   Vue 3 evalúa v-if primero (antes de v-for) → variable del loop es undefined → crash
                             #
                             # Tab "Atrasos":
                             #   API: GET /reportes/atrasos?fecha_desde&fecha_hasta[&id_depto][&id_emp][&formato=excel|pdf]
                             #   Fuente: d2_cuadre_marcacion con permisos aprobados como subquery
                             #   Solo muestra registros con minutos_pendientes > 0 (filtra totalmente justificados)
                             #   Columna "Justificación": "Parcial (Xmin pend.)" o "Sin justificar"
                             #   PDF: reporte_atrasos.blade.php (landscape A4)
                             #
                             # Tab "Marcaciones No Realizadas":
                             #   API: GET /reportes/marcaciones-faltantes?... [&formato=excel|pdf]
                             #   Cruza todos los empleados activos × fechas del rango; muestra solo días con ≥1 marcación faltante
                             #   PDF: reporte_faltantes.blade.php (landscape A4)
                             #
                             # Tab "Sin Atrasos":
                             #   API: GET /asistencia/reporte-sin-atrasos (sin export)
                             #
                             # Tab "Movimientos de Personal":
                             #   API: GET /reportes/movimientos-personal?fecha_desde&fecha_hasta[&id_depto][&id_emp][&tipos=...][&formato=excel|pdf]
                             #   Parámetro tipos: valores separados por coma — VACACIONES,PERMISO,LICENCIA,COMISION (default: todos)
                             #   Fuentes por tipo (queries separadas, merge en PHP con collect()->concat()):
                             #     VACACIONES → d2_vacacion (estado_permiso=APROBADO, fecha_inicial en rango)
                             #     PERMISO    → d2_permiso (descontable=SI, estado=APROBADO, fecha_desde en rango)
                             #     LICENCIA   → d2_permiso (descontable=NO, estado=APROBADO, fecha_desde en rango)
                             #     COMISION   → vac_liquidacion_historico (motivo IN INICIO_COMISION/FIN_COMISION_SALIDA)
                             #   Columnas: Tipo (badge de color), Empleado, Cargo, Departamento, Fecha Desde, Fecha Hasta, Días, Detalle
                             #   PDF: reporte_movimientos.blade.php (landscape A4)
                             #   Nota: COMISION se usa sin tilde en todo el código (parámetros URL, lógica PHP, template)
                             #
                             # Tab "Marcaciones del Día":
                             #   Filtro: fecha única (default hoy) + departamento + buscar texto — NO rango de fechas
                             #   API: GET /asistencia/listado?fecha&departamento_id&buscar (sin export)
                             #   Columnas: Empleado, Departamento, Concepto (badge color), Hora, Tipo (WEB/TELETRABAJO/BIOMETRICO), IP
                             #   Accesible por roles TH/ADMIN a través del menú "Reportes y Marcaciones"
                             #   Mismos datos que ve el ADMINISTRADOR en AsistenciaView; cuadre nocturnamente
VacacionesView.vue      # Solicitudes de vacaciones del empleado y supervisor
                        # Tabla muestra: Empleado, Fecha Inicio, Fecha Fin, Días (calculado), Estado, Aprobado por, Acciones
                        #   Columna "Aprobado por": apellido+nombre del supervisor que aprobó (campo aprobado_por en d2_vacacion)
                        #   Columna "Días" = diferencia en días inclusiva (fecha_final - fecha_inicial + 1)
                        #   "Todo el día" eliminado de tabla y modal — vacaciones siempre son día completo
                        # Modal "Solicitar Vacaciones": solo fechas + observaciones (sin checkbox todo_dia ni horas)
                        #   Bloque recordatorio: si el empleado tiene planificación APROBADO/REPLANIFICADO del año
                        #   actual, muestra sus períodos planificados como referencia (GET /planificacion/mi-planificacion)
                        # "Ver detalle por período" eliminado — tabla d2_detalle_vacacion vacía (sin migración)
                        # Modelo Vacacion.php: relación aprobador() → belongsTo(Empleado, 'aprobado_por', 'id_emp')
                        # VacacionesController::index() eager-load aprobador junto con empleado.departamento
views/certificados/
  CertificadosView.vue    # Certificados laborales — solo TH / ADMINISTRADOR
                          # Panel superior: buscador de empleado con debounce 300ms + dropdown de resultados
                          #   Al seleccionar → chip verde con datos del empleado + botón "Cambiar"
                          #   Botón "Generar Certificado" → modal de confirmación con datos del empleado
                          #   Modal → POST /api/certificados-laborales → respuesta blob PDF + descarga directa
                          #   Número en encabezado del header: X-Numero de la respuesta HTTP
                          # Panel filtros: búsqueda de empleado, fecha_desde, fecha_hasta (default: 1-ene a hoy)
                          # Historial paginado (30/pág): N° Certificado (mono verde), Empleado+cédula, Cargo,
                          #   Fecha Emisión, Emitido por, botón PDF (descarga desde Alfresco)
                          # Ruta Vue: certificados-laborales → CertificadosLaborales
views/admin/            # Roles, departamentos, turnos, configuración, IESS, avisos ticker
                        # ZktecoView.vue: tabla de dispositivos, toggle activo/inactivo, editar nombre, eliminar
                        # OpcionesView.vue (admin/opciones): tiene filtro de búsqueda en tiempo real
                        #   — input por descripción/URL/categoría, select por categoría, select activos/inactivos
                        #   — computed opcionesFiltradas; contador de resultados visibles
layouts/MainLayout.vue  # Layout del módulo RRHH (menú colapsado, se abre el grupo activo)
                        # Modo mantenimiento: lee GET /api/modo-mantenimiento?modulo=TH
                        #   Variable en d2_configuracion: MODO_MANTENIMIENTO_TH = 1 (activo) / 0
                        #   Empleados → pantalla verde bloqueante con botón "Cerrar Sesión"
                        #   ADMINISTRADOR / TALENTO HUMANO → banner naranja, pueden seguir trabajando
                        # Incluye <ChatbotFAB /> como elemento raíz adicional (Vue 3 fragment)
```

### Estándar de modales (OBLIGATORIO en todos los modales nuevos)

```html
<div v-if="modalX" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
  <div class="bg-white rounded-xl shadow-lg w-full max-w-lg overflow-hidden">
    <div class="flex items-center justify-between px-6 py-4" style="background-color:COLOR;">
      <h2 class="text-lg font-semibold text-white">Título</h2>
      <button @click="modalX = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
    </div>
    <div class="p-6 space-y-4">
      <!-- contenido -->
      <div class="flex justify-end gap-3 pt-2">
        <button @click="modalX = false" class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
      </div>
    </div>
  </div>
</div>
```

Color por módulo: TH/Admin/Adq `#0b5447` · Transportes `#1e3a5f` · Comisiones `#5c4a6e` · Tecnología `#4d7c8a`

### Componentes reutilizables

```
components/TimePicker24.vue  # Selector de hora 24h con dos <select> (horas 0-23, minutos en intervalo configurable)
                             # Props: modelValue (HH:mm string), minuteInterval (default 5)
                             # Sin AM/PM; intervalo de minutos configurable (se usa intervalo de 1 min en asistencia)

components/ChatbotFAB.vue    # Botón flotante estilo WhatsApp con asistente de ayuda contextual
                             # Posición: fixed bottom-6 right-6 z-[9980] (visible en todos los módulos)
                             # Al abrir: ventana de chat 320px × 480px con header verde #0b5547
                             # Mensajes: burbuja bot (blanca, izquierda) / usuario (verde, derecha)
                             # Quick replies: 4 botones de acceso rápido al iniciar la conversación
                             # Matching: función findAnswer() busca en knowledge base por keywords normalizados
                             #   normalize() → lowercase + NFD + strip \p{M} (tildes/diacríticos)
                             #   Score = nº de keywords que el input contiene como substring
                             #   Si score = 0 → FALLBACK: "Comuníquese con la Dirección de Tecnologías"
                             # Base de conocimiento: src/data/chatbot-knowledge.js
                             #   Array de { keywords: string[], answer: string } — ~40 temas del sistema
                             #   Temas: asistencia, permisos, vacaciones, horas extras, acciones personal,
                             #     certificados, nómina, adquisiciones, transportes, administración
                             # Sin props ni emits — completamente autocontenido
                             # Incluido en: MainLayout, AdqLayout, TransporteLayout, LauncherView
                             # z-index [9980]: visible sobre modales (z-50) pero tapado por overlay
                             #   mantenimiento (z-[9999]) — correcto, no usar durante mantenimiento
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
| `PermisosController` | APROBAR, NEGAR, ELIMINAR, ANULAR |
| `HorasExtrasController` | APROBAR, NEGAR, AUTORIZAR, CONFIRMAR, NEGAR (registro) |
| `CertificadoLaboralController` | EMITIR |
| `Admin/ConfiguracionController` | ACTUALIZAR (valor anterior/nuevo) |
| `NominaController` | (vía AuditoriaService desde registrarAuditoria()) |
| `Adquisiciones/OrdenCompraController` | CONFIRMAR_INGRESO, REVERSAR_INGRESO |
| `Adquisiciones/EgresoController` | CONFIRMAR_EGRESO, REVERSAR_EGRESO |
| `Adquisiciones/SolicitudMaterialController` | APROBAR, NEGAR, DESPACHAR |
| `Adquisiciones/AjusteController` | AJUSTE_POSITIVO / AJUSTE_NEGATIVO |
| `TransporteController` | APROBAR_MOV, NEGAR_MOV, ORDEN_TRABAJO, NEGAR_MANT, EN_TALLER, FINALIZAR_MANT |

### Endpoint y vista

- `GET /api/admin/auditoria` — roles ADMINISTRADOR o TALENTO HUMANO; filtros: `modulo`, `accion`, `usuario_id`, `fecha_desde`, `fecha_hasta`, `descripcion`; paginado 50/página
- Vista: `views/admin/AuditoriaView.vue` (ruta `admin/auditoria`)
- Tabla muestra fecha/hora, usuario, tabla, acción (con badge de color), descripción, IP
- Clic en fila expande JSON datos_anteriores / datos_nuevos
- Usa `@/services/api` (no axios directamente) para enviar el token de autenticación

### Eventos de sesión auditados

LOGIN, LOGOUT y LOGIN_FALLIDO se registran con `tabla = 'auth'` y `registro_id = 0`. Como no hay usuario autenticado en el momento del login, se inserta **directamente** con `DB::table('dbo.nom_auditoria_log')->insert([...])` — no se puede usar `AuditoriaService::log()` porque `$request->user()` es null. El LOGOUT sí usa `AuditoriaService::log()` porque el token todavía es válido en ese momento.

El filtro de módulo "Talento" en `AuditoriaController` incluye `tabla = 'auth'` además de `dbo.%` (excluyendo `trans_%`).

### Auditoría de ACTUALIZAR empleado

`EmpleadoController::update()` captura en `$anterior` y `datos_nuevos`: `sueldo`, `estado`, `id_depto`, `cargo_empleado`, `tipo_contrato`, `modalidad_laboral`, `partida_individual`, `programa`, `actividad`, `modalidad_marcacion`.

### Si se agrega un nuevo módulo

Instrumentar sus controladores con `AuditoriaService::log()` en las acciones irreversibles (aprobar, confirmar, eliminar, cambios de estado).

---

## Certificados Laborales (`dbo.d2_certificado_laboral`)

Módulo para emitir y archivar certificados laborales. Solo accesible por ADMINISTRADOR / TALENTO HUMANO.

### Tabla

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | bigint PK | — |
| `numero` | varchar(25) unique | Formato `DATH-CL-NNN-YYYY`; NNN reinicia cada año |
| `id_emp` | varchar(20) | Empleado certificado |
| `fecha_emision` | date | Fecha de emisión |
| `alfresco_id` | varchar(100) nullable | Node ID en Alfresco |
| `nombre_archivo` | varchar(200) nullable | Nombre del PDF |
| `usuario_emision` | varchar(20) | Empleado que lo emitió |
| `created_at` / `updated_at` | timestamps | Auditoría automática |

Migración: `000072`. Modelo: `App\Models\CertificadoLaboral` — relaciones `empleado()` y `emisor()` (ambas a `Empleado`; `emisor` evita conflicto con la columna `usuario_emision`).

### Numeración

`DATH-CL-{SEQ}-{AÑO}` — SEQ con 3 dígitos y cero a la izquierda, reinicia a 001 cada año. Query: `MAX(CAST(SPLIT_PART(numero, '-', 3) AS INTEGER))` filtrando por `whereYear('fecha_emision', $año)`.

### PDF (`certificado_laboral.blade.php`)

Portrait A4. Márgenes `body { margin: 2.5cm }` (NO `@page { margin }` — DomPDF no lo aplica al margen superior con `@page`).
Texto adaptado al género del empleado (`sexo` = MASCULINO/FEMENINO/NULL):
- `el señor` / `la señora` · `portador` / `portadora` · `el interesado` / `la interesada`
- NULL → masculino por defecto
- Empleados INACTIVOS: verbo en pasado (`prestó`), agrega `hasta el {fecha_salida}` en el texto
- Firmantes leídos de `d2_configuracion`: `FIRMANTE_TH_NOMBRE` / `FIRMANTE_TH_CARGO`
- Texto fijo: "para los fines que estime conveniente" (sin campo motivo)
- Espaciado: 44px después de "EL RESPONSABLE..." y después de "CERTIFICA:"; 100px antes de la firma

### Flujo de firma

El certificado se genera y descarga **sin subir a Alfresco**. Después de imprimirlo y firmarlo manualmente, el usuario sube el PDF firmado con el botón "Subir firmado" (color azul). Una vez subido, el botón cambia a "PDF" (rojo) que descarga desde Alfresco. Este flujo es igual al de horas extras — primero se descarga, se firma físicamente, luego se sube el escaneado.

### Rutas

| Método | Ruta | Función |
|---|---|---|
| GET | `/api/certificados-laborales` | Historial paginado (30/pág) con filtros id_emp/fecha_desde/fecha_hasta |
| POST | `/api/certificados-laborales` | Emitir certificado — genera PDF, guarda en BD, retorna blob con header `X-Numero` |
| POST | `/api/certificados-laborales/{id}/subir-firmado` | Sube PDF firmado a Alfresco, guarda `alfresco_id` |
| GET | `/api/certificados-laborales/{id}/descargar` | Re-descarga desde Alfresco |

Alfresco: carpeta `certificados-laborales/{año}/{cedula_APELLIDO}/` via `relativePath`.
Si Alfresco no disponible al subir firmado, retorna error 502 (el certificado ya está en BD desde la emisión).

### Vista

`views/certificados/CertificadosView.vue` — ruta `certificados-laborales`. Agregar opción de menú en Admin → Opciones de Menú con roles TH/ADMINISTRADOR.

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

**Tasas de aporte** (`dbo.d2_aportes_iess`): `modalidad` debe coincidir con `TRIM(tipo_contrato)` del empleado. Estructura actual (migración `000061`): cada fila tiene columnas `iece_patronal`, `iece_personal`, `secap_patronal`, `secap_personal` — la lógica de qué aplica a quién está en los datos, no en el código. LOSEP tiene secap = 0; CODIGO DEL TRABAJO tiene secap > 0. Ya no existen filas separadas para IECE y SECAP.
- LOSEP: `aporte_individual = 11.45%`, `aporte_patronal = 9.15%`, `iece_patronal = 0.5%`, `secap_patronal = 0%`
- CODIGO DEL TRABAJO: `aporte_individual = 9.45%`, `aporte_patronal = 11.15%`, `iece_patronal = 0.5%`, `secap_patronal = 0.5%`
- Campos de auditoría en `d2_aportes_iess`: `created_by`, `updated_at`, `updated_by` (migración `000062`)
- Vista admin: `views/admin/aportes/AportesIessView.vue` — muestra y edita las 4 columnas IECE/SECAP

**Campos en `nom_rol_pago_det`** (migración `000060`): además de los existentes, se agregan:
- `iece_pct`, `iece` — porcentaje y valor del aporte IECE
- `secap_pct`, `secap` — porcentaje y valor del aporte SECAP
- `programa`, `actividad` — clasificación presupuestaria copiada del empleado al calcular
- `poliza_blanket` — descuento manual editable inline (igual que quirografario)
- `total_aporte_patronal` NO se guarda — se calcula al vuelo: `iece + secap + aporte_patronal`

**Campos manuales en Rol de Pagos:** `quirografario`, `hipotecario`, `impuesto_renta`, `supa`, `poliza_blanket` — editables inline (click en celda) o importando CSV con columnas `cedula, quirografario, hipotecario, impuesto_renta, poliza_blanket`.

### Vistas Frontend (`views/nomina/`)

```
DecimosView.vue       # Tabs: Décimo Tercero | Décimo Cuarto | Consolidado
FondosReservaView.vue # Filtro tipo MENSUAL/IESS/Todos
RolPagoView.vue       # Tab 1: tabla con columnas IECE, SECAP, Total Patronal, Programa, Actividad,
                      #   Póliza Blanket + sticky headers + sticky columnas N°/Cédula/Nombre
                      # Tab 2: Resúmenes agrupados por Programa/Actividad + botón PDF Resumen
```

Vista admin SBU: `views/admin/SbuView.vue` (ruta `admin/sbu`) — el SBU se gestiona aquí, NO en DecimoCuarto.

### PDFs (`resources/views/reportes/`)

- `nom_decimo_tercero.blade.php`, `nom_decimo_cuarto.blade.php`, `nom_fondos_reserva.blade.php`, `nom_consolidado.blade.php` — portrait letter
- `nom_rol_pago.blade.php` — **landscape** A4, 7pt; columnas IECE, SECAP, Ap.Patronal, Total Patronal, Programa, Actividad, Póliza Blanket, **Sanciones**, **Otros Descuentos**, **Observaciones**; % en encabezado de columna
- `nom_rol_pago_resumenes.blade.php` — portrait A4; tabla agrupada por Programa/Actividad con totales
- Ruta resúmenes PDF: `GET /api/nomina/rol-pago/{cabId}/resumenes/pdf`

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
| `OrdenCompraController` | Ingresos: store/update/confirmar/confirmarConEgreso/reversar/pdf |
| `EgresoController` | Egresos: store/update/confirmar/reversar/pdf |
| `ProveedorController` | CRUD proveedores |
| `IvaController` | CRUD tasas IVA |
| `AjusteController` | Ajuste de inventario (toma física): store/index — inserta en kardex tipo AJUSTE_POSITIVO/NEGATIVO. También `importarStock`: carga masiva desde CSV |
| `SolicitudMaterialController` | Solicitudes internas: store/aprobar/negar/despachar — al despachar inserta en kardex tipo `EGRESO` con `referencia_tipo = 'solicitud_material'` |
| `ReporteAdqController` | Kardex NIC 2, Libro de Compras, Egresos Valorizados, **Inventario Mensual**, **Inventario Valorizado** (JSON + PDF + Excel) |

### Reglas de Precio

- `precio_unitario` se guarda con 5 decimales; subtotales y totales con 2 decimales
- Al **confirmar ingreso** (promedio ponderado): `precio_unitario = (stock_antes × precio_anterior + cantidad × precio_nuevo) / stock_despues`; si stock_antes = 0 → `precio_unitario = precio_nuevo`
- **Excepción CAJA CHICA**: no aplica promedio ponderado. El `precio_unitario` del artículo se actualiza siempre al **último precio de ingreso** (sin promediar). Así el egreso posterior sale al precio real de compra.
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
- Supervisor **NO ve el stock** en el modal de aprobación (se ocultó para evitar condicionamiento); solo ve nombre, cantidad solicitada y campo editable de cantidad a aprobar
- Si el solicitante es supervisor, la solicitud se crea directamente en APROBADO
- Estado final en despacho: DESPACHADO (todo), DESPACHADO PARCIAL (parcial), NEGADO (todo en 0)
- `despachar()` usa `DB::table()->update(['stock_actual' => DB::raw('GREATEST(0, stock_actual - N)')])` — nunca Eloquent
- `despachar()` inserta en `adq.kardex` con tipo `EGRESO` (igual que `EgresoController::confirmar()`)

Campos de cabecera: `id_emp`, `id_depto`, `estado`, `justificacion`, `fecha_solicitud`, `fecha_aprobacion`, `fecha_despacho`, `usuario_aprobacion`, `usuario_despacho`
Campos de detalle: `articulo_id`, `cantidad_solicitada`, `cantidad_autorizada`, `unidad_medida`

### Ajuste de Inventario

Toma física: el usuario ingresa la cantidad contada físicamente; el sistema calcula la diferencia vs `stock_actual` y genera un movimiento AJUSTE_POSITIVO o AJUSTE_NEGATIVO en el kardex. No genera egreso ni ingreso de bienes, solo corrige el stock y el `valor_saldo`.

### Reportes Adquisiciones

- **Kardex NIC 2**: filtros combinables — artículo individual, Nivel 1 MEF (`nivel1`, 2 chars) y/o Nivel 2 MEF (`nivel2`, 6 chars). Al menos uno requerido. Devuelve siempre un array `[{ articulo, filas }, ...]`; PDF y frontend iteran una sección por artículo. Tabla doble encabezado (INGRESO/EGRESO/SALDO, cada uno con Cant./P.Unit./Total). Clasifica AJUSTE_POSITIVO y REVERSO_EGRESO como INGRESO; AJUSTE_NEGATIVO y REVERSO_INGRESO como EGRESO.
- **Libro de Compras**: facturas de proveedores por período + filtro proceso + filtro RUC/nombre proveedor (parámetro `proveedor`, busca con `ILIKE` en ambos campos) → Top 5 proveedores por monto (calculado en frontend desde los datos cargados) + PDF SRI
- **Egresos Valorizados**: salidas despachadas por período + filtro dirección/área → PDF
- **Inventario Valorizado**: snapshot actual de existencias — `stock_actual × precio_unitario` (sin IVA, NIC 2). Vista agrupada por categoría MEF con acordeón o lista plana por código. Filtro stock > 0 / todos. Export PDF + Excel. Ruta: `GET /api/adquisiciones/reportes/inventario-valorizado`

### Imágenes de Artículos

`Storage::disk('public')` en carpeta `articulos/`. Ejecutar `php artisan storage:link` una vez al desplegar. Se retorna `imagen_url` en la API.

### Rutas

Todas las rutas de Adquisiciones bajo `/api/adquisiciones/*` en `routes/api.php`.

### Vistas Frontend (Adquisiciones)

```
views/adquisiciones/
  ArticulosView.vue           # Inventario con precio, IVA, stock, imagen
                              # Botón "Importar Stock CSV": modal que sube CSV con columnas
                              #   codigo, stock, precio_unitario, unidad_medida (precio sin IVA)
                              #   Endpoint: POST /api/adquisiciones/ajustes/importar-stock
                              #   Busca artículo por código; actualiza precio y unidad; genera AJUSTE_POSITIVO/NEGATIVO
                              #   en kardex con numero_documento='CARGA INICIAL'. Reporta códigos no encontrados.
                              #   Artículos no encontrados → se reportan, no se crean (nombre es obligatorio)
  IngresosBienesView.vue      # Ingresos (BORRADOR/RECIBIDO) + descuento + PDF
                              # CAJA CHICA: modal al confirmar — "¿Confirmar sin egreso?" o "¿Confirmar con egreso?"
                              #   Con egreso: selecciona Dirección + Empleado → PATCH /ordenes/{id}/confirmar-con-egreso
                              #   Sin egreso: PATCH /ordenes/{id}/confirmar (precio se actualiza al último precio CAJA CHICA)
  EgresosBienesView.vue       # Egresos (BORRADOR/DESPACHADO) + PDF
  ProveedoresView.vue         # CRUD proveedores
  IvaView.vue                 # Tasas IVA
  ProcesoContratacionView.vue # Procesos de contratación configurables
  UnidadesMedidaView.vue      # Unidades de medida configurables
  CatalogoInventarioView.vue  # Catálogo MEF nivel1/nivel2
  SolicitudesView.vue         # Solicitudes internas: crear, aprobar (supervisor — sin stock visible), despachar (bienes)
                              # Modales "Nueva Solicitud" y "Despachar": botón X (✕) en el header para cerrar
                              #   sin necesidad de desplazarse al botón Cancelar al final de la lista de artículos
  AjusteInventarioView.vue    # Toma física: buscar artículo, ingresar cant. física, registra ajuste
  ReporteKardexView.vue            # Kardex NIC 2 — filtros: artículo individual, Nivel 1 MEF, Nivel 2 MEF (combinables)
                                   # Resultado: una sección por artículo; PDF itera todos los artículos encontrados
  ReporteLibroComprasView.vue      # Libro de compras + filtro RUC/proveedor + Top 5 proveedores por monto + PDF
  ReporteEgresosView.vue           # Egresos valorizados + PDF
  ReporteInventarioMensualView.vue # Reporte inventario mensual agrupado por partida presupuestaria
                                   # Ruta: adquisiciones/reportes/inventario-mensual
                                   # Filtros: mes + año (selector); botón Generar + botón Descargar PDF
                                   # CUENTA = primeros 6 chars de asociacion_presupuestaria formateado (ej: 53.08.01)
                                   # DESCRIPCIÓN = STRING_AGG de catalogo_nivel1.descripcion para los nivel1 que
                                   #   tienen entradas en catalogo_inventario con esa partida en asociacion_presupuestaria
                                   # Columnas: Saldo Anterior | Ingreso Procesos | Ingreso Caja Chica | Egreso | Saldo Final
                                   # Ingresos: INGRESO + REVERSO_EGRESO + AJUSTE_POSITIVO (Caja Chica = proceso = CAJA CHICA)
                                   # Egresos:  EGRESO + REVERSO_INGRESO + AJUSTE_NEGATIVO
                                   # PDF landscape A4 verde institucional
  ReporteInventarioValorizadoView.vue # Inventario valorizado — snapshot actual de existencias
                                   # Ruta: adquisiciones/reportes/inventario-valorizado
                                   # Rol: ADQUISICIONES (agregar en Admin → Opciones de Menú)
                                   # Controles: Vista (agrupado por categoría MEF / plano por código) +
                                   #   Filtro (solo existencias > 0 / todos los artículos)
                                   # Vista agrupada: acordeón por categoría con ▶/▼, expandir/colapsar todo,
                                   #   subtotal por grupo + total general; header sticky al hacer scroll
                                   # Vista plana: lista ordenada por código + total general
                                   # Columnas: Código | Descripción | Unidad | Stock | Precio Unit. | Valor Total ($)
                                   #   El $ va solo en el encabezado de columna, NO en cada celda (evita desbordamiento)
                                   # Valor Total = stock_actual × precio_unitario (SIN IVA — precio de costo neto, estándar NIC 2)
                                   # Export: Excel (PhpSpreadsheet, filas de grupo en verde, subtotales) + PDF landscape A4
                                   # Backend: ReporteAdqController::inventarioValorizado()
                                   #   JOIN adq.articulo + adq.catalogo_nivel1 ON nivel1
                                   #   orderByRaw("COALESCE(cn1.descripcion, 'SIN CLASIFICACIÓN')") — no usar orderBy con alias
                                   #   Artículos sin nivel1 agrupados en 'SIN CLASIFICACIÓN'
                                   # Ruta API: GET /api/adquisiciones/reportes/inventario-valorizado
                                   #   Params: tipo (agrupado|plano), solo_existencias (1|0), formato (pdf|excel)
layouts/AdqLayout.vue              # Layout verde, roles ADQUISICIONES/BIENES
                                   # Modo mantenimiento: variable MODO_MANTENIMIENTO_ADQ = 1
                                   #   ADMINISTRADOR / ADQUISICIONES → banner naranja, siguen trabajando
                                   #   Resto → pantalla verde oliva bloqueante con botón "Cerrar Sesión"
                                   # Menú colapsado por defecto, auto-abre el grupo de la ruta activa
                                   # Incluye <ChatbotFAB /> como elemento raíz adicional (Vue 3 fragment)
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
- `GET /notificaciones-pendientes` — solo TRANSPORTE; devuelve `{ pendientes, ultima_at, items[] }`
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
                              # Modo mantenimiento: variable MODO_MANTENIMIENTO_TRANS = 1
                              #   ADMINISTRADOR / TRANSPORTE → banner naranja, siguen trabajando
                              #   Resto → pantalla azul oscuro bloqueante con botón "Cerrar Sesión"
                              # Notificaciones Web (Opción A): polling cada 30s a /notificaciones-pendientes
                              #   Pide permiso al montar; compara ultima_at para detectar nuevas solicitudes
                              #   new Notification() con clic → navega a /transporte/movilizacion
                              #   Funciona con navegador minimizado; se detiene al hacer logout (onUnmounted)
                              # Incluye <ChatbotFAB /> como elemento raíz adicional (Vue 3 fragment)
```

`MainLayout.vue` excluye `transporte/`, `adquisiciones/`, `tecnologia/` y `comisiones/` de su menú. `LauncherView.vue` muestra tarjeta Transportes si tiene rol TRANSPORTE, CONDUCTOR, o `puede_solicitar_vehiculo`. Tarjetas del launcher: `w-44 p-5` con íconos `w-12 h-12`.

### Opciones de menú a configurar en Admin

| URL | Roles |
|---|---|
| `admin/zkteco` | ADMINISTRADOR |
| `transporte/vehiculos` | TRANSPORTE |
| `transporte/talleres` | TRANSPORTE |
| `transporte/tipos-mantenimiento` | TRANSPORTE |
| `transporte/plan-preventivo` | TRANSPORTE |
| `transporte/mantenimiento` | TRANSPORTE, CONDUCTOR |
| `transporte/movilizacion` | TRANSPORTE, CONDUCTOR |
| `transporte/vales-combustible` | TRANSPORTE, CONDUCTOR |

---

## Módulo Comisiones de Servicios

Cuarto módulo del sistema. Color institucional: `#5c4a6e` (malva apagado). Digitaliza el proceso PRO.GF-CS.001 v5 (24/03/2026).

### Roles requeridos

`MAXIMA AUTORIDAD`, `CONTABILIDAD`, `PRESUPUESTO`, `DIRECTOR FINANCIERO`, `TESORERIA`, `COMISIONADO EXTERNO`

Estos roles se suman a los existentes — un empleado puede tener SUPERVISOR + DIRECTOR FINANCIERO simultáneamente.

> **Roles eliminados del flujo original:** `DIRECCION ADMINISTRATIVA` y `ASESORIA JURIDICA` — el flujo multi-nivel fue simplificado (ver Flujo abajo). Pueden eliminarse de `dbo.admin_rol` si no se usan en otro módulo.

### Opciones de menú

| URL | Descripción | Roles |
|---|---|---|
| `comisiones/solicitudes` | Comisiones de Servicio | Todos los roles de Comisiones + ADMINISTRADOR + COMISIONADO EXTERNO |
| `comisiones/liquidaciones` | Liquidaciones de Viáticos | CONTABILIDAD + PRESUPUESTO + DIRECTOR FINANCIERO + TESORERIA + ADMINISTRADOR |
| `comisiones/tarifas` | Tarifas de Viáticos | ADMINISTRADOR |
| `admin/funcionarios-externos` | Funcionarios Externos | ADMINISTRADOR (menú TH Admin) |
| `admin/provincias-ciudades` | Provincias y Ciudades | ADMINISTRADOR (menú Admin Comisiones) |

### Tablas (`dbo.*`)

| Tabla | Descripción |
|---|---|
| `com_solicitud` | Cabecera de la solicitud; campos: tipo (INTERIOR/EXTERIOR), id_emp, id_depto, fechas, destino (string "PROVINCIA - CIUDAD" para INTERIOR, texto libre para EXTERIOR), tiene_viaticos, tiene_movilizaciones, tiene_anticipo, estado, numero_solicitud, `observacion_devolucion VARCHAR(500) NULL` (migración `000083`) |
| `com_solicitud_servidor` | Servidor comisionado; campos: solicitud_id, id_emp, unidad, puesto, orden, banco, tipo_cuenta, numero_cuenta (migración `000080`). En el flujo actual solo hay 1 servidor = el empleado logueado (auto-insertado al crear solicitud) |
| `com_solicitud_transporte` | Transportes de la solicitud (tipo, nombre, ruta, salida/llegada) |
| `com_solicitud_documento` | Documentos adjuntos por solicitud (migración `000082`); campos: solicitud_id, tipo_doc (AUTORIZACION\|PASAJES\|CERTIFICACION\|FIRMADO), alfresco_id, nombre_archivo, created_by, created_at. Carpeta Alfresco: `comisiones/{año}/{cedula_APELLIDO}/` |
| `com_anticipo` | Anticipo de viáticos; campos: solicitud_id, monto, cur_compromiso, cur_devengado, estado |
| `com_informe` | Informe de actividades post-comisión; campos: solicitud_id, actividades, productos, estado, pdf_firmado_id, pdf_firmado_nombre (migración `000082`). Fechas (`fecha_informe`, `fecha_salida`, `fecha_llegada`) se castean a Carbon — al devolverlas como Eloquent model vienen como ISO timestamp completo; el frontend usa `String(f).substring(0,10)` para formatear |
| `com_informe_transporte` | Transportes del informe (real vs planificado) |
| `com_ficha_liquidacion` | Ficha financiera; campos: solicitud_id, valor_por_dia, dias, total, cur_compromiso, cur_devengado, comprobante_pago, comprobante_devolucion, pais_destino, coeficiente_pais, estado |
| `com_tarifa_viatico` | Tarifas diarias; campos: descripcion, valor_dia, tipo (INTERIOR/EXTERIOR), aplica_jerarquico BOOLEAN, activo |
| `com_coeficiente_pais` | 40 países MEF con coeficiente DECIMAL(6,4) y región; CRUD editable desde Admin → Tarifas (tab Exterior) |
| `com_funcionario_externo` | Personal temporal (ej. seguridad presidencial); campos: cedula UNIQUE, nombres, cargo, banco, tipo_cuenta, numero_cuenta, programa (4 chars), actividad (6 chars), activo — migración `000082` agrega programa/actividad |
| `com_provincia` | 24 provincias de Ecuador (migración `000082`); seed precargado |
| `com_ciudad` | Ciudades por provincia (migración `000082`); seed con ~4-6 ciudades por provincia |

### Migraciones

- `000074` — com_solicitud, com_solicitud_servidor, com_solicitud_transporte, com_tarifa_viatico
- `000075` — com_anticipo
- `000076` — com_informe, com_informe_transporte
- `000077` — com_ficha_liquidacion
- `000078` — amplía admin_rol.descripcion a VARCHAR(50) (drop/recreate view_usuario_opciones)
- `000079` — aplica_jerarquico en tarifa_viatico; seed 2 tarifas INTERIOR ($130/$80); com_coeficiente_pais + seed 40 países; com_funcionario_externo; VALOR_BASE_EXTERIOR=185 en d2_configuracion; pais_destino/coeficiente_pais en com_ficha_liquidacion
- `000080` — banco/tipo_cuenta/numero_cuenta en com_solicitud_servidor
- `000081` — banco/tipo_cuenta/numero_cuenta en ad_empleado y com_funcionario_externo
- `000082` — es_externo en ad_empleado; programa/actividad en com_funcionario_externo; com_provincia + seed 24 provincias; com_ciudad + seed ciudades; com_solicitud_documento; pdf_firmado_id/pdf_firmado_nombre en com_informe
- `000083` — `observacion_devolucion VARCHAR(500) NULL` en com_solicitud (estado DEVUELTO)

### Tarifas de viáticos

**INTERIOR** — 2 niveles según `grupo_ocupacional` del empleado:
- `aplica_jerarquico = true` → empleados cuyo grupo_ocupacional contiene 'JERARQUICO' → **$130/día**
- `aplica_jerarquico = false` → todos los demás → **$80/día**

**EXTERIOR** — fórmula única para todos:
- `valor_por_dia = VALOR_BASE_EXTERIOR ($185) × coeficiente_pais`
- País destino se selecciona en el frontend al crear la ficha de liquidación
- 40 países MEF agrupados por región (AFRICA, AMERICA CENTRAL, AMERICA DEL SUR, AMERICA DEL NORTE, ASIA, EUROPA, OCEANIA)
- Coeficientes editables desde Admin → Tarifas → tab Exterior (CRUD via CoeficientePaisController)

### Numeración de solicitudes

Formato: `CS-{centro_de_costo}-{año}-{NNN}`
- `centro_de_costo` viene de `dbo.ad_departamento.centro_de_costo` del departamento del solicitante
- NNN: secuencial anual de 3 dígitos, institución-wide (no por área)
- Ejemplo: `CS-100-2026-001`
- **Se genera al momento de subir el PDF firmado** (acción `subirFirmado()` en ComisionController) → cambia estado a APROBADO

### Funcionarios externos con acceso al sistema

`com_funcionario_externo` — personal temporal (ej. seguridad presidencial). Pueden tener acceso al sistema:
- HR crea el externo en `com_funcionario_externo`
- HR hace clic en "Dar acceso" → modal con contraseña temporal
- Backend crea/actualiza registro en `dbo.ad_empleado` con `id_emp = cedula`, `id_depto = 999`, `es_externo = true`, asigna rol `COMISIONADO EXTERNO`
- El externo se loguea y solo ve el módulo de Comisiones (launcher filtra por rol)
- Desactivar externo (`destroy()`) pone `activo = false` en com_funcionario_externo Y `estado = INACTIVO` en ad_empleado

### Destino de comisión

- **INTERIOR**: selects en cascada Provincia → Ciudad. Destino se guarda como `"PROVINCIA - CIUDAD"` en `com_solicitud.destino`. Provincias y ciudades gestionadas desde `admin/provincias-ciudades` (ProvinciaCiudadView.vue). Backend: `GET /comisiones/provincias` retorna provincias con ciudades anidadas (dos queries separadas para evitar excluir provincias sin ciudades).
- **EXTERIOR**: campo de texto libre.

### Flujo simplificado (único para INTERIOR y EXTERIOR)

```
BORRADOR / DEVUELTO
  → Empleado llena form (Tab 1 Datos + Tab 3 Transporte)
  → Tab 4 Documentos: sube 3 PDFs externos
      AUTORIZACION — Solicitud de Autorización y Aprobación
      PASAJES      — Pasajes Aéreos
      CERTIFICACION — Certificación Presupuestaria
  → Genera PDF de la solicitud (descarga para imprimir y firmar)
  → Sube PDF Firmado → estado cambia a APROBADO + se asigna número CS-xxx-año-NNN
APROBADO
  → (opcional) Anticipo: PRESUPUESTO CUR compromiso → CONTABILIDAD CUR devengado → TESORERIA paga
  → Empleado crea Informe → genera PDF → sube PDF Firmado → INFORME_APROBADO (directo, sin revisión)
INFORME_APROBADO
  → Empleado solicita pago → EN_PAGO
EN_PAGO / EN_LIQUIDACION / POR_COBRAR
  → Roles financieros pueden devolver → DEVUELTO (empleado corrige todo desde cero)
  → CONTABILIDAD crea ficha liquidación → EN_LIQUIDACION
  → PRESUPUESTO CUR compromiso → CONTABILIDAD CUR devengado
  → TESORERIA confirma pago → CERRADO (o POR_COBRAR si hay devolución pendiente)
POR_COBRAR → TESORERIA registra devolución → CERRADO
```

**Estado DEVUELTO** (migración `000083`):
- Cualquier rol financiero (CONTABILIDAD, PRESUPUESTO, DIRECTOR FINANCIERO, TESORERIA, ADMINISTRADOR) puede devolver desde `LiquidacionesView` o desde el stepper
- Endpoint: `PATCH /comisiones/solicitudes/{id}/devolver` con `observacion` obligatoria
- DEVUELTO se trata igual que BORRADOR en backend: `update()`, `uploadDocumento()` y `procesar()` aceptan ambos estados
- El empleado ve un banner rojo con el motivo en el Paso 1 del stepper y puede reeditar todo
- Se registra en auditoría (`AuditoriaService::log`)

> **Estados eliminados vs flujo anterior:** PENDIENTE_DIR_ADM, PENDIENTE_JEFE, PENDIENTE_AUTORIDAD, PENDIENTE_JURIDICA, PENDIENTE_SISTEMA_EXT, AUTORIZADO, NEGADO, INFORME_PRESENTADO, INFORME_REVISADO

### Tab Servidores (read-only)

Tab 2 del modal de solicitud muestra solo el empleado logueado (datos del auth store) en modo lectura. Al crear la solicitud, el backend auto-inserta en `com_solicitud_servidor` con los datos del `$request->user()`. No hay búsqueda ni múltiples servidores.

### Controladores (`app/Http/Controllers/Comisiones/`)

| Controlador | Métodos clave |
|---|---|
| `ComisionController` | index, store, update, show/detalle, provincias, uploadDocumento, deleteDocumento, descargarDocumento, subirFirmado, solicitarPago, **devolver** |
| `InformeComisionController` | store, update, subirFirmado, descargarFirmado, pdf |
| `AnticipController` | store, curCompromiso, curDevengado, pagar |
| `LiquidacionController` | store, update, registrarCurCompromiso (PRESUPUESTO), registrarCurDevengado (CONTABILIDAD), confirmarPago (TESORERIA), registrarDevolucion (TESORERIA), coeficientes, calcularValorDia |
| `TarifaViaticosController` | index, store, update, destroy |
| `CoeficientePaisController` | index, store, update |
| `FuncionarioExternoController` | index, store, update, destroy, darAcceso |
| `ProvinciaCiudadController` | index, storeProvincia, updateProvincia, destroyProvincia, storeCiudad, updateCiudad, destroyCiudad |

**Roles por acción en Liquidación:**
- Crear ficha: solo CONTABILIDAD (y ADMINISTRADOR)
- CUR compromiso: PRESUPUESTO o DIRECTOR FINANCIERO (y ADMINISTRADOR)
- CUR devengado: CONTABILIDAD o DIRECTOR FINANCIERO (y ADMINISTRADOR)
- Confirmar pago / registrar devolución: solo TESORERIA (y ADMINISTRADOR)
- Devolver solicitud: CONTABILIDAD, PRESUPUESTO, DIRECTOR FINANCIERO, TESORERIA, ADMINISTRADOR

### PDFs

- `com_solicitud_interior.blade.php` / `com_solicitud_exterior.blade.php`
- `com_informe_interior.blade.php` / `com_informe_exterior.blade.php` — **JOIN correcto**: `sa.id_supervisor` (no `sa.id_emp`) al buscar supervisor del departamento en `dbo.supervisor_area`
- `com_ficha_interior.blade.php` / `com_ficha_exterior.blade.php`

**Bug crítico resuelto — firma de `pdf()` en controladores de comisiones:** La ruta define `{id}` pero si el método tiene `pdf(int $otroNombre)` Laravel no inyecta el parámetro (falla con ArgumentCountError → 500). Siempre usar `pdf(Request $request, int $id)` para que el nombre coincida con el parámetro de ruta. Usar `->stream()` (no `->download()`) para compatibilidad con el patrón blob URL del frontend.

### Vistas Frontend

```
views/comisiones/
  ComisionesView.vue          # STEPPER UNIFICADO — reemplaza 3 modales separados por un único modal con
                              # barra de progreso de 4 pasos: Solicitud → Informe → Pago → Liquidación
                              # El paso activo y el estado de cada paso se derivan de com_solicitud.estado:
                              #   BORRADOR/PROCESADO/DEVUELTO → paso 1 activo
                              #   APROBADO → paso 2 activo
                              #   INFORME_APROBADO → paso 3 activo
                              #   EN_PAGO/EN_LIQUIDACION/POR_COBRAR/CERRADO → paso 4 activo
                              # Estado DEVUELTO: paso 1 muestra banner rojo con observacion_devolucion
                              # Botón "Devolver" en el footer del stepper visible para roles financieros
                              #   (esFinanciero computed: es_contabilidad|es_presupuesto|es_dir_financiero|es_tesoreria|es_admin)
                              # Paso 1 (form): 4 tabs internos — Datos Generales, Servidores (read-only), Transporte, Documentos
                              # Tab 1 Destino INTERIOR: selects cascada Provincia → Ciudad (carga separada del resto)
                              #   form_provinciaId/form_ciudadId = solo frontend; form.destino = "PROV - CIUDAD"
                              #   cargar() separa carga de provincias en try/catch independiente
                              # Tab 2 Servidores: read-only — muestra datos del auth store sin inputs
                              # Tab 4 Documentos: auto-guarda solicitud silenciosamente al entrar (get ID)
                              #   3 slots obligatorios (AUTORIZACION/PASAJES/CERTIFICACION) + botón Generar PDF
                              #   + slot PDF Firmado (aparece cuando 3 docs subidos) → sube y cambia a APROBADO
                              #   guardarSolicitudSilencioso(): errores se muestran DENTRO del tab Documentos,
                              #   NO redirige a Tab 1 — botón "Volver a Datos Generales →" para navegar
                              # Tabs de listado: "Mis Comisiones" (cards) + "Todas las Comisiones" (tabla)
                              # CONVENCIÓN MAYÚSCULAS: todos los inputs/textareas con style="text-transform:uppercase"
                              #   aplica a toda la vista y modales. Backend hace strtoupper() en campos relevantes.
  LiquidacionesView.vue       # Tabs sólidos con #5c4a6e; lista fichas por estado; CURs, confirmar pago
                              # Modales con cabecera coloreada #5c4a6e: Ficha Liquidación, CUR, Devolución
                              # Botón "Ver todo" por solicitud: abre modal read-only con datos generales,
                              #   4 slots de documentos (✓/○ + Descargar), informe completo (actividades,
                              #   productos) y slot "Informe Firmado" con el mismo estilo visual de documentos
                              # Botón "Devolver": visible para roles financieros, abre modal con textarea
                              #   obligatoria → PATCH /comisiones/solicitudes/{id}/devolver
                              # formatFecha(): usa String(f).substring(0,10) para manejar ISO timestamps
                              #   completos que devuelve Eloquent (ej: "2026-06-19T05:00:00.000000Z")
                              # formatHora(): usa String(h).substring(0,5) para mostrar solo HH:MM
  FuncionariosExternosView.vue # CRUD funcionarios externos (ruta: admin/funcionarios-externos y comisiones/funcionarios-externos)
                               # Campos: cedula, nombres, cargo, banco, tipo_cuenta, numero_cuenta,
                               #   programa (4 chars), actividad (6 chars), activo
                               # Botón "Dar acceso": modal con contraseña → POST /admin/funcionarios-externos/{id}/dar-acceso
                               #   Badge "Con acceso" (verde) / "Sin acceso" (gris) por fila
views/admin/
  TarifasViaticosView.vue     # Tab INTERIOR: 2 filas con badge Jerárquico/Otros
                              # Tab EXTERIOR: acordeón colapsable por región
  ProvinciaCiudadView.vue     # CRUD provincias y ciudades para destinos INTERIOR
                              # Acordeón por provincia; chips de ciudades con editar/eliminar
                              # Input inline "Nueva ciudad..." con Enter; modal para crear/editar provincia
                              # Ruta: admin/provincias-ciudades
layouts/ComisionesLayout.vue  # Color #5c4a6e; menú dinámico; modo mantenimiento MODO_MANTENIMIENTO_COM
                              # Tarjeta en LauncherView: visible para todos los roles de Comisiones + COMISIONADO EXTERNO
                              # Incluye <ChatbotFAB />
```

### Rutas (`/api/comisiones/*`)

| Método | Ruta | Función |
|---|---|---|
| GET | `/provincias` | Lista provincias con ciudades anidadas (sin auth de rol) |
| GET/POST | `/solicitudes` | Listar / crear (auto-inserta servidor = empleado logueado) |
| GET/PUT | `/solicitudes/{id}` | Ver / editar (solo BORRADOR) |
| GET | `/solicitudes/{id}/pdf` | PDF solicitud |
| POST | `/solicitudes/{id}/documentos` | Subir documento (tipo_doc: AUTORIZACION\|PASAJES\|CERTIFICACION) |
| DELETE | `/solicitudes/{id}/documentos/{docId}` | Eliminar documento |
| GET | `/solicitudes/{id}/documentos/{docId}/descargar` | Descargar documento desde Alfresco |
| POST | `/solicitudes/{id}/subir-firmado` | Sube PDF firmado → APROBADO + número CS-xxx |
| PATCH | `/solicitudes/{id}/solicitar-pago` | Empleado solicita pago → EN_PAGO |
| PATCH | `/solicitudes/{id}/devolver` | Roles financieros devuelven → DEVUELTO + observacion_devolucion |
| GET/POST | `/informes/{solicitudId}` | Ver / crear informe |
| PUT | `/informes/{id}` | Editar informe |
| POST | `/solicitudes/{id}/informe/subir-firmado` | Sube PDF firmado del informe → INFORME_APROBADO |
| GET | `/solicitudes/{id}/informe/descargar-firmado` | Descarga informe firmado desde Alfresco |
| GET | `/informes/{solicitudId}/pdf` | PDF informe |
| POST | `/anticipo/{solicitudId}` | Crear anticipo |
| PATCH | `/anticipo/{id}/cur-compromiso` | PRESUPUESTO registra CUR |
| PATCH | `/anticipo/{id}/cur-devengado` | CONTABILIDAD registra CUR |
| PATCH | `/anticipo/{id}/pagar` | TESORERIA confirma pago anticipo |
| GET/POST | `/liquidaciones` | Listar / crear ficha |
| PUT | `/liquidaciones/{id}` | Editar ficha |
| GET | `/liquidaciones/{id}/pdf` | PDF ficha |
| PATCH | `/liquidaciones/{id}/cur-compromiso` | PRESUPUESTO |
| PATCH | `/liquidaciones/{id}/cur-devengado` | CONTABILIDAD |
| PATCH | `/liquidaciones/{id}/confirmar-pago` | TESORERIA confirma |
| PATCH | `/liquidaciones/{id}/registrar-devolucion` | TESORERIA devolución |
| GET | `/coeficientes-pais` | Lista países activos |
| GET/POST | `/admin/tarifas` | Tarifas viáticos |
| PUT/DELETE | `/admin/tarifas/{id}` | Editar / eliminar tarifa |
| GET/POST | `/admin/coeficientes-pais` | CRUD países |
| PUT | `/admin/coeficientes-pais/{id}` | Editar coeficiente |
| GET/POST | `/admin/funcionarios-externos` | CRUD externos |
| PUT | `/admin/funcionarios-externos/{id}` | Editar externo |
| DELETE | `/admin/funcionarios-externos/{id}` | Desactivar externo (también pone INACTIVO en ad_empleado) |
| POST | `/admin/funcionarios-externos/{id}/dar-acceso` | Crea/actualiza empleado con es_externo=true + rol COMISIONADO EXTERNO |
| GET/POST | `/admin/provincias` | CRUD provincias |
| PUT/DELETE | `/admin/provincias/{id}` | Editar / eliminar provincia |
| POST | `/admin/provincias/{id}/ciudades` | Agregar ciudad |
| PUT/DELETE | `/admin/ciudades/{id}` | Editar / eliminar ciudad |

---

## Módulo Inventario Tecnológico

Quinto módulo del sistema, para la Dirección de Tecnología. Color institucional: `#4d7c8a` (azul petróleo/teal apagado). Controla el inventario de equipos tecnológicos (computadoras, laptops, impresoras, etc.), su custodia (quién tiene cada equipo, con trazabilidad histórica) y el mantenimiento preventivo anual con acta digital. Módulo 100% independiente — sin relación funcional con Adquisiciones (`adq.*`) ni Transportes; solo reutiliza el estilo de código de esos módulos por consistencia.

### Rol requerido

`TECNOLOGIA` — acceso completo al módulo (junto con `ADMINISTRADOR`). Sin flujo de aprobación entre roles: a diferencia de Transportes/Movilización, la Dirección de Tecnología gestiona todo directamente (asigna, devuelve, registra mantenimiento) sin que el empleado interactúe con el sistema.

### Opciones de menú

| URL | Descripción | Roles |
|---|---|---|
| `tecnologia/equipos` | Inventario de Equipos | TECNOLOGIA, ADMINISTRADOR |
| `tecnologia/piezas` | Piezas y Repuestos | TECNOLOGIA, ADMINISTRADOR |
| `tecnologia/mantenimiento` | Mantenimiento | TECNOLOGIA, ADMINISTRADOR |
| `tecnologia/reportes` | Reportes de Equipos | TECNOLOGIA, ADMINISTRADOR |
| `tecnologia/tipos-equipo` | Tipos de Equipo | TECNOLOGIA, ADMINISTRADOR |
| `tecnologia/actividades-mantenimiento` | Actividades del checklist | TECNOLOGIA, ADMINISTRADOR |

### Tablas (`dbo.ti_*`)

| Tabla | Descripción |
|---|---|
| `ti_tipo_equipo` | Catálogo editable: Computador de Escritorio, Laptop, Impresora, Monitor, Escáner, Proyector, Otro |
| `ti_equipo` | Un registro por unidad física. `estado`: `DISPONIBLE`/`ASIGNADO`/`DAÑADO`/`DE_BAJA` (custodia, gestionado por el sistema al asignar/devolver). `condicion`: `BUENO`/`REGULAR`/`MALO` (estado físico, distinto de `estado`). `vida_util_anios`, `ultimo_mantenimiento` (caché). `marca VARCHAR(150)`, `modelo VARCHAR(300)` (ampliados en migración `000089` — el inventario real de TI trae descripciones largas en "modelo") |
| `ti_asignacion` | Historial de custodia. Solo puede existir **una fila con `fecha_devolucion IS NULL` por `equipo_id`** a la vez (regla aplicada en el controlador, no a nivel de constraint) |
| `ti_actividad_mantenimiento` | Catálogo maestro del checklist de mantenimiento (10 ítems reales del formulario físico de TI, editable) |
| `ti_mantenimiento` | Cabecera de cada ejecución de mantenimiento. **El "una vez al año" solo aplica al `tipo = PREVENTIVO`**: índice único parcial `ti_mantenimiento_preventivo_anio_uq` sobre `(equipo_id, anio) WHERE tipo = 'PREVENTIVO'` (migración `000095`, reemplazó el `UNIQUE(equipo_id, anio)` original que bloqueaba erróneamente registrar una reparación el mismo año del preventivo). El `CORRECTIVO` se puede registrar las veces que haga falta. Incluye `hora_inicio`/`hora_fin` (nullable — el mantenimiento externo por lote y las reparaciones rápidas no siempre registran hora exacta), `id_emp_tecnico` (usuario que registró) e `id_emp_custodio` (snapshot del custodio en ese momento). Campos de mantenimiento externo (migración `000090`): `origen` (`INTERNO`/`EXTERNO`), `proveedor`, `proceso_contratacion`, `numero_orden_compra`, `lote_externo` — ver sección "Mantenimiento externo" abajo |
| `ti_mantenimiento_detalle` | Snapshot SI/NO del checklist para esa ejecución (una fila por actividad del catálogo activa al momento de registrar). Solo se genera cuando `origen = INTERNO` |
| `ti_pieza` | Catálogo de piezas/repuestos (ver sección "Piezas y repuestos" abajo). `codigo`/`serie` opcionales, `descripcion` obligatoria, `fecha_entrega` (obligatoria — fecha en que la Unidad de Bienes/Dirección Administrativa entregó la pieza a Tecnología; **Tecnología no tiene bodega propia**, las piezas llegan ya codificadas por Bienes). `estado`: `DISPONIBLE`/`INSTALADA`/`DE_BAJA`. `equipo_id` = equipo donde está instalada actualmente (null si disponible o de baja) |
| `ti_pieza_movimiento` | Historial de instalación/retiro de piezas, mismo patrón que `ti_asignacion` pero pieza↔equipo. `mantenimiento_id` (nullable) liga el movimiento al mantenimiento en el que se hizo el cambio. Solo puede existir **una fila con `fecha_retiro IS NULL` por `pieza_id`** a la vez |

Migraciones: `000088` (crea las 6 tablas + rol `TECNOLOGIA` + seeds de tipos de equipo y checklist), `000089` (amplía `ti_equipo.marca`/`modelo`, ver arriba), `000090` (agrega columnas de mantenimiento externo a `ti_mantenimiento` y hace `hora_inicio`/`hora_fin` nullable), `000092` (crea `ti_pieza` y `ti_pieza_movimiento`), `000095` (cambia el `UNIQUE(equipo_id, anio)` de `ti_mantenimiento` a índice único parcial solo para `tipo=PREVENTIVO`, ver arriba).

`Equipo` (modelo) tiene un accessor `vida_util_vencida` (`$appends`, calculado en PHP con `fecha_ingreso + vida_util_anios <= hoy`, sin necesidad de cast ni columna nueva) — se usa para el badge/filtro/tarjeta "Vida útil vencida" en `EquiposView.vue`. `EquipoController::resumen()` incluye el conteo `vida_util_vencida` (excluye equipos `DE_BAJA`) y `index()` acepta `?vida_util_vencida=1` como filtro.

**Custodio inactivo:** no hay ningún trigger ni validación que cruce `ad_empleado.estado` con la custodia de equipos — si un empleado pasa a `INACTIVO` mientras tiene equipos asignados, estos se quedan `ASIGNADO` a esa persona indefinidamente hasta que alguien lo note y haga "Devolver" manualmente. Para que no pase desapercibido: accessor `custodio_inactivo` en `Equipo` (`$appends`, true si `estado=ASIGNADO` y el empleado de la asignación activa tiene `estado=INACTIVO`), tarjeta de alerta roja "Custodio inactivo" en `EquiposView.vue` (clickeable, filtra con `?custodio_inactivo=1`) y badge "⚠ Empleado inactivo" junto al nombre del custodio tanto en `EquiposView.vue` como en `ReporteEquiposView.vue`. `EquipoController::resumen()` incluye el conteo `custodio_inactivo`.

Las opciones de menú (`admin_opcion`) y su asignación al rol `TECNOLOGIA` (`admin_rol_opcion`) se crean desde la UI (Admin → Opciones de Menú / Admin → Roles) — no se gestionan por migración.

### Custodia (asignar / devolver)

- **Asignar**: solo si `equipo.estado = DISPONIBLE`. El buscador de empleado consulta el mismo catálogo `dbo.ad_empleado` que usa Talento Humano (no hay lista separada). Crea fila en `ti_asignacion`, pasa el equipo a `ASIGNADO`.
- **Devolver**: solo si `equipo.estado = ASIGNADO`. Pide fecha de devolución (editable, no forzada a "hoy" — permite registrar devoluciones retroactivas), motivo (`REASIGNACION`/`SALIDA_EMPLEADO`/`DAÑO`/`OTRO`) y observación. Si el motivo es `DAÑO` el equipo pasa a `DAÑADO`; en cualquier otro caso vuelve a `DISPONIBLE`. La asignación anterior no se borra, queda cerrada en el historial.
- **Historial**: botón por equipo abre un modal con la línea de tiempo (timeline visual) de todas las asignaciones — activa (punto verde) e históricas (punto gris), con motivo de devolución.
- **Dar de baja / Marcar disponible**: transición manual de estado, solo permitida cuando el equipo no está `ASIGNADO`.

### Mantenimiento preventivo (checklist real)

El mantenimiento **preventivo** se ejecuta **una vez al año por equipo** (índice único parcial sobre `ti_mantenimiento` solo para `tipo=PREVENTIVO`, ver arriba — el correctivo no tiene ese límite). El checklist es fijo (catálogo `ti_actividad_mantenimiento`, editable desde `tecnologia/actividades-mantenimiento`) y reproduce el formulario físico que ya usaba TI:

1. Ingreso al equipo · 2. Limpieza interna del equipo · 3. Limpieza externa del equipo · 4. Borrado archivos temporales · 5. Ingreso al equipo por la IP · 6. Actualización del antivirus · 7. Formateo del equipo · 8. Respaldo carpeta Escritorio · 9. Respaldo carpeta Mis documentos · 10. Respaldo correo electrónico institucional (PST)

Al registrar un mantenimiento se marca SI/NO por cada ítem (hora de inicio/fin con `components/TimePicker24.vue`), y se genera automáticamente el acta en PDF. `MantenimientoView.vue` tiene tabs "Pendientes {año}" (equipos activos sin `ti_mantenimiento` ese año) / "Realizados {año}", más un gráfico donut (Chart.js) con el % de avance del año.

**Acta de mantenimiento** (`resources/views/reportes/ti_acta_mantenimiento.blade.php`, A4 portrait): incluye fecha, hora de inicio y fin, tabla del checklist con columnas SI/NO, y firmas — **"Técnico que realizó"** = usuario logueado que registró (`id_emp_tecnico`, autocapturado de `$request->user()`, no es un campo editable del formulario) y **"Responsable del equipo"** = custodio con asignación activa al momento del registro (`id_emp_custodio`, snapshot — no cambia si luego se reasigna el equipo a otra persona). PDF generado con DomPDF (`->stream()`); opcionalmente se puede subir firmado a Alfresco (carpeta `mantenimiento-ti/{año}/`), mismo patrón de `relativePath` que Certificados Laborales / Horas Extras.

`MantenimientoView.vue` tiene sus propios filtros (Buscar + Tipo de equipo) sobre `pendientes`/`realizados`, iguales a los de `EquiposView.vue` — útil porque antes había que buscar equipo por equipo en la lista.

### Reparación de equipos dañados (mantenimiento correctivo)

Cuando un equipo está en estado `DAÑADO`, su fila en `EquiposView.vue` muestra el botón **"Registrar reparación"** (reemplazó a un simple botón "Disponible" sin registro alguno). Abre un modal pidiendo solo **fecha** y **qué se hizo** (observación libre) — sin checklist ni horas, esos son propios del preventivo. Al guardar:
- Crea un `ti_mantenimiento` con `tipo = CORRECTIVO` (sin filas en `ti_mantenimiento_detalle`, ya que no aplica el checklist de 10 puntos).
- El equipo pasa automáticamente de `DAÑADO` a `DISPONIBLE` (antes había que hacerlo aparte, sin dejar ningún rastro de la reparación).
- Se genera y abre automáticamente el acta en PDF — mismo `ti_acta_mantenimiento.blade.php`, pero oculta la tabla de checklist cuando no hay `detalle` y titula la sección de observaciones como "Descripción de la Reparación" en ese caso.

Esta reparación queda visible en la pestaña "Realizados" de `MantenimientoView.vue` igual que un preventivo — es la constancia de "qué se hizo para volver a poner operativo el equipo".

### Mantenimiento externo (por proveedor, en lote)

Algunas categorías de equipo (ej. **INFRAESTRUCTURA DATA CENTER**, **INFRAESTRUCTURA EQUIPOS MTTO EXTERNO**) no las mantiene un técnico interno con el checklist de 10 puntos, sino un **proveedor externo** que atiende de una sola vez **todos los equipos de esa categoría** bajo una sola orden de compra. La funcionalidad no está restringida por nombre de categoría — aplica a cualquier `tipo_equipo_id` que se elija al registrar.

Flujo (`MantenimientoController::storeExterno`, botón "Registrar mantenimiento externo" en `MantenimientoView.vue`):
1. Se elige: categoría (tipo de equipo), fecha, proveedor, proceso de contratación (lista fija: `ÍNFIMA CUANTÍA`, `SUBASTA INVERSA`, `CATÁLOGO ELECTRÓNICO`, `CONTRATACIÓN DIRECTA`, `OTRO` — constante `MantenimientoController::PROCESOS_CONTRATACION`), N° de orden de compra, observaciones.
2. El backend busca todos los equipos de esa categoría que aún no tengan `ti_mantenimiento` para ese año (misma lógica que "pendientes") y crea **un registro `ti_mantenimiento` por cada uno**, todos con `origen = EXTERNO` y un mismo `lote_externo` (UUID generado por lote) que los agrupa — sin checklist (`ti_mantenimiento_detalle` no se crea para estos).
3. `id_emp_tecnico` sigue siendo el usuario de TI que **registró** el lote (no quien ejecutó físicamente el mantenimiento); `id_emp_custodio` se snapshotea por equipo igual que en el flujo interno.

**Acta agrupada** (`resources/views/reportes/ti_acta_mantenimiento_externo.blade.php`): una sola acta por lote — datos del proveedor/proceso/N° de orden una sola vez, más una tabla con todos los equipos cubiertos (código, marca/modelo, serie). Firmas: "Responsable Dirección de Tecnología" / "Proveedor". Se genera y descarga automáticamente al guardar. `subirFirmadoExterno`/`descargarFirmadoExterno` operan sobre **todo el lote** (actualizan `acta_alfresco_id`/`acta_nombre_archivo` en todas las filas de ese `lote_externo` a la vez), mismo folder Alfresco `mantenimiento-ti/{año}/`.

En la pestaña "Realizados" de `MantenimientoView.vue`, los registros con `lote_externo` se agrupan visualmente en una sola tarjeta (badge naranja "EXTERNO", proveedor, proceso, N° de orden, cantidad de equipos, botón "Ver equipos" para expandir la lista) en vez de aparecer como N filas sueltas; los de `origen = INTERNO` se siguen mostrando uno por equipo como antes. La lógica de agrupación (`realizadosAgrupados`) es puramente client-side sobre el array plano que ya devuelve `GET /mantenimiento/realizados`.

### Importación de inventario (CSV)

`EquipoController::importarCsv` — columnas `codigo_bien, tipo_equipo, marca, modelo, descripcion, serie, estado(condición), fecha_ingreso, vida_util_anios`.
- **Detecta automáticamente el delimitador** (`,` o `;`) comparando ambos en la primera línea del archivo — Excel en español suele exportar CSV separado por `;`.
- **Fechas**: acepta `DD/MM/AAAA` (formato típico de Excel/Ecuador), `AAAA-MM-DD` y `DD-MM-AAAA`; se convierten a `AAAA-MM-DD` para Postgres. El campo puede dejarse vacío (opcional).
- **Tipo de equipo**: se compara contra el catálogo ignorando mayúsculas/minúsculas, punto final y espacios repetidos — evita falsos "no existe en el catálogo" por diferencias de formato (ej. `"INFRAESTRUCTURA DE VIDEOVIGILANCIA."` con punto vs `"INFRAESTRUCTURA DE VIDEOVIGILANCIA"` sin punto en el catálogo).
- Valida todas las filas antes de insertar y reporta todos los errores encontrados de una vez; si hay algún error no inserta nada (`DB::transaction`).

### Piezas y repuestos

Trazabilidad de partes/piezas cambiadas durante un mantenimiento (ej. disco, RAM, fuente de poder), para saber en qué equipo está instalada cada una y no perder el rastro cuando se mueven entre equipos. **Importante:** Tecnología no mantiene bodega propia de piezas — las entrega la **Unidad de Bienes (Dirección Administrativa)**, que ya las codifica y gestiona su trámite interno; Tecnología solo las solicita, las recibe y ahí procede con el cambio. Por eso `ti_pieza.fecha_entrega` es obligatoria: es la fecha en que Bienes entrega físicamente la pieza a Tecnología (distinta de `created_at`, que es cuándo se registró en el sistema).

Ciclo de vida de una pieza: `DISPONIBLE` (ya entregada por Bienes, sin instalar) → `INSTALADA` (en un `equipo_id`) → puede volver a `DISPONIBLE` (retiro por reemplazo/desinstalación, reutilizable) o pasar a `DE_BAJA` (retiro por daño, o baja directa desde disponible).

**Dos puntos de entrada** para instalar/retirar (`PiezaController`):
1. **Pantalla `tecnologia/piezas`** (`PiezasView.vue`) — catálogo completo: crear pieza (código, serie, descripción, fecha de entrega), buscar por código/serie/descripción, ver en qué equipo está instalada, instalar/retirar/dar de baja en cualquier momento, historial (timeline) de en qué equipos ha estado.
2. **Dentro del formulario de "Registrar Mantenimiento"** (`MantenimientoView.vue`, solo mantenimiento interno individual, no el externo por lote) — sección "Piezas Cambiadas": buscador inline de piezas `DISPONIBLE` existentes, o botón "+ Pieza nueva" para crear una al vuelo (código/serie/descripción); al guardar el mantenimiento, cada pieza queda instalada en ese equipo con `mantenimiento_id` ligado al registro. Si la pieza se crea al vuelo desde aquí, `fecha_entrega` se autocompleta con la fecha del mantenimiento (asumiendo que Bienes la entregó por esas fechas); si no es correcto, se puede corregir después desde `tecnologia/piezas`.

**Simplificación deliberada:** el formulario de mantenimiento solo *instala* piezas nuevas — no intenta adivinar qué pieza vieja reemplaza a cuál. Si se está reemplazando una pieza que ya estaba en ese equipo, esa se retira aparte desde `tecnologia/piezas` (ahí se ve qué piezas tiene instaladas cada equipo).

`EquipoController`/modelo `Equipo` tiene `piezasInstaladas()` (`hasMany` filtrado por `estado = INSTALADA`) para consultar rápido qué piezas tiene un equipo sin pasar por el historial completo de movimientos.

### Reportes de equipos

Pantalla `tecnologia/reportes` (`ReporteEquiposView.vue`) — una sola pantalla de reportes combinables en vez de pantallas separadas por cada tipo de consulta (por empleado, por marca/modelo, por vida útil vencida/vigente son todos, en el fondo, el mismo listado de equipos con distintos filtros). Filtros: **Custodio** (buscador de empleado, mismo patrón que `EquiposView.vue`), **Marca** y **Modelo** (selects poblados con los valores únicos que ya existen en el inventario vía `ReporteEquipoController::filtros()`, no texto libre), **Tipo de equipo**, **Vida útil** (Todos/Vencida/Vigente). No pagina en el backend (el reporte necesita el listado completo para exportar); se pagina 30/pág solo en el frontend para no listar cientos de filas de una vez.

Columna **"🔧 N Piezas"**: si el equipo tiene piezas instaladas (`piezas_instaladas_count` vía `withCount`), un botón abre un modal con el detalle (reutiliza `GET /equipos/{id}/piezas`, el mismo endpoint de `PiezaController::porEquipo` que ya existía pero no estaba conectado a ninguna pantalla).

**Exportar Excel/PDF**: los botones envían los mismos filtros activos en pantalla con `?formato=excel` o `?formato=pdf` — exportan exactamente lo que se está viendo, no todo el inventario. Excel con PhpSpreadsheet (patrón `ob_start()` + `Xlsx->save('php://output')`, sin archivo temporal), encabezado con fondo `#4d7c8a`. PDF con plantilla nueva `resources/views/reportes/ti_reporte_equipos.blade.php` (landscape A4, sin firmas — es un listado, no un documento a firmar).

### Backend

Controladores en `app/Http/Controllers/Tecnologia/`:

| Controlador | Función |
|---|---|
| `TipoEquipoController` | CRUD catálogo de tipos de equipo |
| `EquipoController` | `index` (paginado 20/pág, filtros tipo/estado/búsqueda/`vida_util_vencida`), `resumen` (conteos por estado + vida útil vencida para las tarjetas del frontend), `store`/`update`, `importarCsv`, `asignar`/`devolver`/`historial`/`marcarBaja`/`marcarDisponible` |
| `ActividadMantenimientoController` | CRUD catálogo del checklist |
| `MantenimientoController` | `checklist`, `procesos` (lista fija de procesos de contratación), `pendientes`/`realizados` (con filtros tipo/búsqueda), `store` (acepta `piezas[]` opcional — instala piezas nuevas/existentes ligadas al mantenimiento), `pdf`, `subirFirmado`, `descargarFirmado`, `storeExterno`, `pdfExterno`, `subirFirmadoExterno`, `descargarFirmadoExterno` (estos 4 últimos operan por `lote_externo`, no por `id`) |
| `PiezaController` | `index` (paginado 20/pág, filtros estado/búsqueda), `store`/`update`, `instalar`/`retirar`/`historial`, `marcarBaja`/`marcarDisponible`, `porEquipo` (piezas instaladas/históricas de un equipo — `GET /equipos/{id}/piezas`) |
| `ReporteEquipoController` | `filtros` (marcas/modelos únicos existentes, para los selects), `index` (sin paginar — filtros custodio/marca/modelo/tipo/vida útil; con `?formato=excel\|pdf` exporta exactamente lo filtrado) |

Modelos en `app/Models/Tecnologia/`: `TipoEquipo`, `Equipo` (`tipoEquipo()`, `asignaciones()`, `asignacionActiva()` — `hasOne` con `whereNull('fecha_devolucion')`; `piezasInstaladas()`; accessor `vida_util_vencida`), `Asignacion`, `ActividadMantenimiento`, `Mantenimiento` (`tecnico()`/`custodio()` → `Empleado`, `detalle()` → `MantenimientoDetalle`), `MantenimientoDetalle` (`$timestamps = false`), `Pieza` (`equipo()`, `movimientos()`), `PiezaMovimiento` (`pieza()`, `equipo()`, `mantenimiento()`).

Rutas bajo `/api/tecnologia/*`, dentro del grupo `auth:sanctum` existente — sin middleware de rol dedicado, mismo patrón del resto del sistema (autorización real es la visibilidad del menú).

Auditado con `AuditoriaService::log()` en `ASIGNAR`, `DEVOLVER`, `DAR_DE_BAJA`, `MARCAR_DISPONIBLE`, `REGISTRAR_MANTENIMIENTO`, `REGISTRAR_MANTENIMIENTO_EXTERNO` (equipos/mantenimiento) e `INSTALAR`/`RETIRAR`/`DAR_DE_BAJA`/`MARCAR_DISPONIBLE` (piezas, tabla `dbo.ti_pieza`).

### Frontend

```
layouts/TecnologiaLayout.vue         # Layout azul petróleo #4d7c8a; menú desde auth.menuAgrupado (prefijo tecnologia/)
                                     # Modo mantenimiento: variable MODO_MANTENIMIENTO_TEC; incluye <ChatbotFAB />
views/tecnologia/
  EquiposView.vue                    # 7 tarjetas de resumen (Total/Disponibles/Asignados/Dañados/De baja/
                                     #   Vida útil vencida/Custodio inactivo), clickeables para filtrar la
                                     #   tabla (toggleStatCard); badge "⚠ Empleado inactivo" junto al custodio
                                     # Tabla paginada 20/pág: código, equipo (con badge naranja si
                                     #   vida_util_vencida), serie, estado, custodio actual, acciones
                                     #   (Asignar/Devolver como botón sólido; Historial/Editar/Dar de baja
                                     #   como botones de texto con borde — los íconos solos se descartaron
                                     #   por poco visibles). Columna "Condición" removida de la tabla
                                     #   (el campo se sigue editando desde el modal)
                                     # Equipo DAÑADO: botón "Registrar reparación" (ver sección "Reparación
                                     #   de equipos dañados" arriba) — modal fecha + qué se hizo, genera acta
                                     #   como mantenimiento correctivo y pasa el equipo a DISPONIBLE
                                     # Historial de custodia: línea de tiempo visual (timeline)
                                     # Importar CSV con plantilla de 9 columnas (ver sección arriba)
                                     # Todos los modales (crear/editar, Asignar, Devolver, Importar CSV)
                                     #   tienen botón "×" de cerrar en la cabecera, no solo "Cancelar" al final
  MantenimientoView.vue              # Filtros Buscar + Tipo (iguales a EquiposView) sobre pendientes/realizados
                                     # Tabs Pendientes/Realizados por año + gráfico donut de avance (Chart.js)
                                     # Botón "Registrar mantenimiento externo" (ver sección arriba) — modal
                                     #   categoría/fecha/proveedor/proceso/N° orden de compra
                                     # Modal de registro individual: checklist SI/NO + TimePicker24 (hora inicio/fin)
                                     # Técnico y custodio se autocompletan en el backend, no se piden en el form
                                     # Pestaña Realizados agrupa visualmente los mantenimientos EXTERNO por
                                     #   lote_externo (computed realizadosAgrupados, client-side)
  PiezasView.vue                     # Catálogo de piezas/repuestos (ver sección "Piezas y repuestos" arriba)
                                     # Tabla: código, descripción (+ fecha de entrega de Bienes), serie, estado,
                                     #   equipo actual, acciones (Instalar/Retirar/Marcar disponible/Historial/
                                     #   Editar/Dar de baja). Modal Instalar busca equipo por código/marca/modelo
                                     #   (mismo endpoint de búsqueda que usa EquiposView)
                                     # Modal crear/editar exige "Fecha de entrega (Bienes)" — no hay bodega
                                     #   propia de TI, las piezas llegan ya codificadas desde Bienes
                                     # Modal Historial: además del equipo, muestra su descripción y el
                                     #   custodio actual (no solo el código de bien) — modal max-w-2xl
  ReporteEquiposView.vue             # Ver sección "Reportes de equipos" arriba — filtros combinables
                                     #   (custodio/marca/modelo/tipo/vida útil) + export Excel/PDF de lo filtrado
  TiposEquipoView.vue                # CRUD catálogo de tipos de equipo
  ActividadesMantenimientoView.vue   # CRUD catálogo del checklist de mantenimiento (nombre + orden + activo)
```

`LauncherView.vue`: tarjeta "Tecnología" (color `#4d7c8a`) visible para roles `TECNOLOGIA`/`ADMINISTRADOR` (`tieneAccesoTecnologia`).

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

## Integración ZKTeco (reloj biométrico)

Reloj **ZKTeco SenseFace 7A** (reconocimiento facial). Protocolo **ADMS push** — el reloj inicia las llamadas HTTP al servidor.

### Tabla `dbo.d2_zkteco_dispositivo`

| Campo | Tipo | Descripción |
|---|---|---|
| `id` | int PK | — |
| `serial` | varchar(50) unique | Número de serie del dispositivo |
| `nombre` | varchar(100) nullable | Nombre descriptivo (ej: "Reloj Entrada Principal") |
| `ip` | varchar(45) nullable | IP detectada en el último push |
| `ultimo_push` | timestamp nullable | Fecha/hora del último push recibido |
| `activo` | boolean | `true` = puede enviar marcaciones; `false` = rechazado con 403 |

Migración: `2026_05_27_000059_create_zkteco_dispositivo_table.php`

### Endpoints ADMS — públicos (sin Sanctum)

Rutas en `routes/api.php` **fuera** del grupo `auth:sanctum`, bajo el prefijo `/api/iclock/`. El reloj debe configurarse con **Server Path: `/api/`** porque Apache solo proxea `/api/*` a Laravel.

| Método | Ruta | Función |
|---|---|---|
| POST | `/api/iclock/cdata` | Recibe marcaciones tab-separated |
| GET | `/api/iclock/getrequest` | Polling del reloj (responde vacío) |
| GET\|POST | `/api/iclock/registry` | Registro automático al arrancar |
| POST | `/api/iclock/devicecmd` | Confirmación de comandos (ignorar) |

**Seguridad:** `cdata`, `getrequest` y `devicecmd` validan `SN` (serial) contra `d2_zkteco_dispositivo` con `activo=true` — responden `403 ERROR` si no autorizado. `registry` es abierto para que el reloj se registre automáticamente; el admin luego activa desde la UI.

**Formato de datos recibidos en `cdata`** (body, una línea por marcación, campos separados por tab):
```
PIN\tDateTime\tStatus\tVerify\tWorkcode\tReserved1\tReserved2
0102030405\t2026-05-22 08:30:00\t0\t15\t\t0\t0
```
- `PIN` = cédula del empleado (configurada al enrolarlo en el dispositivo)
- `DateTime` = `YYYY-MM-DD HH:MM:SS`
- El campo `Status` (0=IN/1=OUT) no se usa — el sistema determina el concepto por secuencia del día
- **PIN con cero inicial:** ZKTeco trata el PIN como número y puede eliminar el cero inicial de cédulas que empiecen con 0. El controller detecta PINs de 9 dígitos y los completa: `if (strlen($pin) === 9) $pin = '0' . $pin`
- **Lookup por cédula:** el controller busca al empleado por `identificacion` (cédula 10 dígitos) en `dbo.ad_empleado`, NO por `id_emp`. El `id_emp` (código corto como `00002`) es lo que se guarda en `nro_documento` de `sg_control_persona`

**Lógica de asignación de concepto:** igual que el aplicativo web — cuenta las marcaciones del empleado en el día y asigna la siguiente en la secuencia `ENTRADA → SALIDA AL LUNCH → ENTRADA DEL LUNCH → SALIDA`. Si ya tiene 4, descarta.

**Manejo de errores de BD (desde el incidente 2026-07-17, ver sección Backups):** las 4 rutas (`cdata` en ambos métodos, `getrequest`, `registry`, `devicecmd`) envuelven su lógica en `try/catch`. Ante cualquier excepción (típicamente pérdida de conexión a Postgres) responden `"ERROR"` en texto plano vía el helper privado `errorAdms()`, en el mismo formato que ya usa la respuesta 403 de dispositivo no autorizado — **nunca dejar que una excepción llegue sin capturar aquí**, porque el error 500 en HTML de Laravel no lo reconoce el protocolo ADMS y el reloj no reintenta esa marcación (la da por entregada aunque haya fallado). Un corte de red puro sí se recupera solo (el reloj no recibe respuesta y reintenta), pero una respuesta HTTP de error mal formada no.

### Endpoints admin — protegidos con Sanctum

| Método | Ruta | Función |
|---|---|---|
| GET | `/api/admin/zkteco` | Lista todos los dispositivos |
| PUT | `/api/admin/zkteco/{id}` | Editar nombre y estado activo |
| DELETE | `/api/admin/zkteco/{id}` | Eliminar dispositivo |

Vista: `views/admin/ZktecoView.vue` (ruta `admin/zkteco`) — solo rol ADMINISTRADOR.

### Dispositivo registrado en producción

| Campo | Valor |
|---|---|
| Serial | `VDE2261200055` |
| IP | `192.168.26.9` |
| Nombre | Reloj Principal |

> **Si se restaura un backup** y el reloj deja de funcionar (403 en los logs), el registro se perdió. Insertarlo manualmente:
> ```sql
> INSERT INTO dbo.d2_zkteco_dispositivo (serial, nombre, ip, activo, ultimo_push, created_at, updated_at)
> VALUES ('VDE2261200055', 'Reloj Principal', '192.168.26.9', true, NOW(), NOW(), NOW());
> ```

### Configuración del dispositivo físico

Al llegar el reloj:
1. Conectar a VLAN `192.168.10.x` (conectividad a `192.168.26.x` ya confirmada)
2. En menú Red / ADMS del dispositivo: **Server Address:** `192.168.26.19` | **Server Port:** `8081` | **Server Path:** `/api/`
3. El reloj llama a `/api/iclock/registry?SN=SERIAL` al arrancar y queda registrado en BD
4. **Activarlo desde la UI** (`admin/zkteco`) — por defecto se registra en BD con `activo=true`, pero verificar
5. Enrolar empleados: PIN = cédula exacta (10 dígitos), valor del campo `identificacion` en `dbo.ad_empleado` (NO el `id_emp`)
6. Empleados inactivos: solo cambiar `estado='INACTIVO'` en la ficha; no es necesario borrarlos del reloj

---

## Environment

- `VITE_API_URL` en `frontend/.env` — URL base de la API
- Backend `.env`: `DB_CONNECTION=pgsql`, credenciales BD
- Session y cache driver: `database`
