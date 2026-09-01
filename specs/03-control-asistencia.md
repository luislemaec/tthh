# Spec 03 — Control de asistencia

| | |
|---|---|
| **Versión** | 1.0 (borrador) |
| **Última actualización** | 2026-09-01 |
| **Depende de** | [00 — Módulo Talento Humano](00-modulo-talento-humano.md), [01 — Empleados](01-empleados.md) |
| **Controladores** | `AsistenciaController`, `CuadreController`, `ZktecoController` |
| **Comando** | `app/Console/Commands/ProcesarCuadre.php` (`procesar:cuadre`) |
| **Modelo** | `App\Models\SgControlPersona` (`dbo.sg_control_persona`) |
| **Vistas** | `views/AsistenciaView.vue`, `views/admin/cuadre/CuadreView.vue`, `views/asistencia/ReporteSinAtrasosView.vue`, `views/admin/ZktecoView.vue` |
| **Tipo** | Retro-especificación |

---

## 1. Propósito

Registrar la asistencia diaria de cada servidor —por marcación web o por reloj biométrico ZKTeco— y
**conciliarla** contra su turno para calcular atrasos en minutos, considerando los permisos
aprobados. Es la base de:
- La vista personal "Mis Marcaciones" y el reporte de atrasos del empleado.
- El **cuadre** (`d2_cuadre_marcacion`), consumido por Permisos (spec 04), Vacaciones (spec 05),
  Nómina/rol de pagos (spec 09), Dashboard y reportes (spec 11).

### 1.1 Alcance

Incluye: marcación web con validación por modalidad, secuencia diaria y VLAN; integración ADMS del
reloj ZKTeco; proceso de cuadre (turnos, atrasos, ajuste por permisos); reportes de asistencia
(personal y administrador); CRUD de dispositivos biométricos.

**No** cubre: la creación de permisos y su lógica de descuento (spec 04); la planificación de
turnos/programación mensual (`d2_programacion`, `d2_turno` — se consumen aquí pero su gestión es de
otra spec/pantalla); horas extras (spec 08).

---

## 2. Actores

| Actor | Puede |
|---|---|
| Empleado autenticado | Ver su estado de marcación (`mi-estado`), marcar (`marcar`), ver su historial (`mi-reporte`). |
| `ADMINISTRADOR`, `TALENTO HUMANO` | Listado de marcaciones del día (`listado`), reporte por rango (`reporte`), reporte "sin atrasos", disparar y listar el cuadre (`cuadre/*`), CRUD de dispositivos ZKTeco. |
| Reloj ZKTeco (sin autenticación de usuario) | Endpoints públicos `/api/iclock/*` (protocolo ADMS); autorización por `serial` + `activo`. |

Chequeos: `AsistenciaController::listado/reporte` y `CuadreController::*` usan
`requireRole($request, ['ADMINISTRADOR','TALENTO HUMANO'])`. `reporteSinAtrasos` hace chequeo inline
equivalente. `miEstado`, `marcar`, `miReporte` no requieren rol (operan sobre el propio usuario).

---

## 3. Historias de usuario

- **HU-03.1** — Como empleado, quiero ver cuál es mi siguiente marcación pendiente del día y marcarla con un clic.
- **HU-03.2** — Como empleado presencial, quiero que el sistema solo me deje marcar desde la red de la institución.
- **HU-03.3** — Como empleado en teletrabajo, quiero marcar desde casa **solo** si tengo un período de teletrabajo vigente.
- **HU-03.4** — Como empleado con marcación biométrica obligatoria, quiero que la web me impida marcar y me explique por qué.
- **HU-03.5** — Como TH, quiero que las marcaciones del reloj facial entren automáticamente al sistema sin digitación.
- **HU-03.6** — Como TH, quiero que cada noche se calcule el atraso de cada empleado activo, descontando lo que cubran sus permisos aprobados.
- **HU-03.7** — Como TH, quiero re-procesar el cuadre de una fecha manualmente si hubo correcciones.
- **HU-03.8** — Como empleado, quiero ver mi historial de marcaciones con los atrasos y si están justificados.
- **HU-03.9** — Como TH, quiero un reporte de qué empleados no tuvieron ningún atraso en un período.
- **HU-03.10** — Como administrador, quiero activar/desactivar un dispositivo biométrico y renombrarlo.

---

## 4. Modelo de datos

### 4.1 `dbo.sg_control_persona` — marcaciones individuales

PK `secuencial` (autoincremental). `timestamps = false`. Una fila por marcación.

| Campo | Contenido |
|---|---|
| `nro_documento` | = `ad_empleado.id_emp` (¡no la cédula!). |
| `identificador` | Se inserta `0` (legado). |
| `clasificacion` | `ENTRADA` o `SALIDA` (derivada del concepto). |
| `concepto` | `ENTRADA` / `SALIDA AL LUNCH` / `ENTRADA DEL LUNCH` / `SALIDA`. |
| `fecha_hora` | Timestamp de la marcación. |
| `motivo` | Texto opcional que el empleado escribe al marcar (web). |
| `tipo_marcacion` | `WEB` / `TELETRABAJO` / `BIOMETRICO`. |
| `lugar` | `WEB` (marcación web) / `BIOMETRICO` (reloj). *(campo legado, redundante con `tipo_marcacion`.)* |
| `ip` | IP del cliente (web) o del reloj (biométrico). |
| `ubicacion` | `ad_empleado.ubicacion` o `"Quito"` por defecto (solo web). |
| `procesado` | `SI` / `NO` — lo pone `NO` al insertar; el cuadre no lo actualiza *(§12.3, abierto)*. |
| `origen` | `WEB` (solo lo setea la marcación web). |

**Secuencia diaria estricta:** `ENTRADA → SALIDA AL LUNCH → ENTRADA DEL LUNCH → SALIDA`. Máximo 4
marcaciones por empleado por día. El concepto **no** depende del origen: WEB, TELETRABAJO y
BIOMETRICO se pueden combinar libremente en el mismo día; el sistema asigna el siguiente concepto de
la secuencia según cuántas marcaciones ya tiene el empleado ese día.

### 4.2 `dbo.d2_cuadre_marcacion` — conciliación diaria (una fila por empleado/día)

Clave lógica: `(id_emp, fecha)` donde `fecha` se guarda como `'YYYY-MM-DD 00:00:00'`.

| Campo | Contenido |
|---|---|
| `id_emp`, `identificacion`, `apellido`, `nombre`, `area` | Snapshot del empleado. |
| `jornada` | `id_turno` usado ese día. |
| `hora_turno_entrada` / `_sal_lunch` / `_ent_lunch` / `_sal` | Horas programadas (decimal, p. ej. 8.5 = 08:30). |
| `hora_real_entrada` / `_sal_lunch` / `_ent_lunch` / `_sal` | Horas reales marcadas (decimal) o `null`. |
| `atraso_entrada`, `atraso_lunch`, `atraso_salida` | Minutos de atraso (enteros, ≥ 0), **ya ajustados** por permisos. |
| `horas_totales` | Horas trabajadas = `(salida − entrada) − almuerzo`, o `null` si falta entrada o salida. |
| `horas_decto` | Horas a descontar. Desde **2026-09-01** `ProcesarCuadre` lo recalcula **desde cero cada corrida** (nunca `+=`): `(atraso_entrada + atraso_lunch + atraso_salida)/60` **más** el aporte de los permisos `APROBADO` **descontables** vigentes ese día (factor 30/22). Antes lo incrementaba `PermisosController::aprobar()` con un `+=` que el cuadre luego pisaba. |
| `horaspermiso_pag` | Aporte de los permisos `APROBADO` **no descontables** (`descontable = 'NO'`) del día. Desde **2026-09-01** lo escribe `ProcesarCuadre` (recalculado desde cero); antes el cuadre nunca lo tocaba y solo lo incrementaba `aprobar()`. |
| `tiempo_lunch` | Minutos de almuerzo (reales o programados). |
| `falta` | `'S'` si no hay ninguna marcación de entrada; `'N'` en caso contrario. |
| `motivo_entrada` / `motivo_lunch` / `motivo_salida` | *(seleccionados por el listado; poblados por otra vía — no por `ProcesarCuadre`.)* |
| `ip` | `'127.0.0.1'` fijo (lo escribe el comando). |

### 4.3 `dbo.d2_zkteco_dispositivo` — dispositivos biométricos

| Campo | Contenido |
|---|---|
| `id` PK | — |
| `serial` (unique) | Nº de serie del reloj (parámetro `SN` del protocolo). |
| `nombre` | Descriptivo, editable. |
| `ip` | Última IP detectada en un push. |
| `ultimo_push` | Timestamp del último contacto. |
| `activo` | `true` = acepta marcaciones; `false` = responde `403 ERROR`. **Default `false` desde 2026-09-01** (migración `000105`; antes `true`). Un dispositivo nuevo requiere que el admin lo active a mano desde `admin/zkteco`. |

### 4.4 Tablas consumidas (gestión fuera de esta spec)

| Tabla | Uso en el cuadre |
|---|---|
| `dbo.d2_turno` | Filas `(id_turno, concepto, hora)` → horas programadas por turno. |
| `dbo.d2_programacion` | Turno asignado por día del mes: columna `s{día}` (`s1`…`s31`) con el `id_turno`. Si no hay programación → turno `1`. |
| `dbo.d2_permiso` | Permisos `APROBADO` que solapan el día → ajustan `atraso_entrada` / `atraso_salida`. |
| `dbo.d2_configuracion` | `VLANS_PERMITIDAS`, `CONTROL_IP_MARCACION`, `ARTICULO_ATRASOS`. |
| `dbo.ad_empleado_teletrabajo` | Períodos de teletrabajo vigentes (validación de marcación TELETRABAJO). |

---

## 5. Reglas de negocio — marcación web

### 5.1 `GET /api/asistencia/mi-estado` — `miEstado()`

- **RN-03.1** — Devuelve, del empleado autenticado y para **hoy**: datos del empleado, `fecha`,
  `hora` (servidor), lista de `marcaciones` del día, `siguiente` (primer concepto de la secuencia
  aún no marcado, o `null` si ya tiene 4), `articulo_atrasos` (texto de config `ARTICULO_ATRASOS`),
  `modalidad` (`modalidad_marcacion` o `PRESENCIAL`), `puede_marcar` (bool) y `mensaje_bloqueo`.
- **RN-03.2** — `puede_marcar = false` si:
  - `modalidad = BIOMETRICO` → mensaje "Tu marcación es exclusivamente por reloj biométrico."
  - `modalidad = TELETRABAJO` **y** no existe período en `ad_empleado_teletrabajo` con
    `fecha_desde <= hoy <= fecha_hasta` → mensaje "Tu período de teletrabajo ha vencido o no está
    habilitado. Contacta a Talento Humano."
- **RN-03.3** — `PRESENCIAL` y `TEMPORAL` → `puede_marcar = true` (la validación de VLAN de PRESENCIAL
  ocurre recién al **marcar**, no aquí).

### 5.2 `POST /api/asistencia/marcar` — `marcar()`

Request: `concepto` (`in:ENTRADA,SALIDA AL LUNCH,ENTRADA DEL LUNCH,SALIDA`), `motivo` (opcional, ≤120).

Validaciones **en orden**:

1. **RN-03.4 (modalidad BIOMETRICO)** → HTTP 403, no marca.
2. **RN-03.5 (modalidad TELETRABAJO)** — sin período vigente → HTTP 403.
3. **RN-03.6 (modalidad PRESENCIAL — VLAN)** — lee `VLANS_PERMITIDAS` (config), formato
   `"10.10.12.,10.10.26."` (prefijos con punto final, separados por coma). Se permite si la lista
   está **vacía** o si `request()->ip()` empieza por alguno de los prefijos. Si no → HTTP 403 "Solo
   puede registrar asistencia desde las instalaciones de la institución."
   - `TEMPORAL` **no** pasa por esta validación (marca desde cualquier IP).
4. **RN-03.7 (CONTROL_IP_MARCACION)** — si la config `CONTROL_IP_MARCACION` (trim) = `'1'`: si ya
   existe hoy una marcación con la **misma IP** de **otro** `nro_documento` → HTTP 403 "Esta
   computadora ya fue utilizada por otro empleado hoy." (Aplica a **todas** las modalidades.)
5. **RN-03.8 (concepto duplicado)** — si el empleado ya marcó ese `concepto` hoy → HTTP 422 "Ya registraste {concepto} hoy".
6. **RN-03.9 (orden de secuencia)** — si el concepto anterior en la secuencia no está marcado hoy →
   HTTP 422 "Debes registrar {conceptoAnterior} primero".
7. **RN-03.10 (inserción)** — crea la fila con:
   - `clasificacion` = `ENTRADA` si concepto ∈ {ENTRADA, ENTRADA DEL LUNCH}, si no `SALIDA`.
   - `tipo_marcacion` = `TELETRABAJO` si modalidad TELETRABAJO, si no `WEB`.
   - `lugar = 'WEB'`, `origen = 'WEB'`, `procesado = 'NO'`, `ip = request()->ip()`,
     `ubicacion = empleado.ubicacion ?? 'Quito'`, `identificador = 0`.
   - Respuesta HTTP 201 con la marcación y la hora.

> **Nota:** `marcar()` **no** valida que la modalidad sea una de las 4 conocidas; una modalidad
> desconocida cae en el camino "no BIOMETRICO, no TELETRABAJO, no PRESENCIAL" → marca como `WEB` sin
> validación de VLAN (equivalente a `TEMPORAL`).

### 5.3 Validación de IP tras proxy

El backend corre detrás de Apache (reverse proxy). `bootstrap/app.php` declara
`trustProxies(at: '127.0.0.1')` para que `$request->ip()` devuelva la IP real del cliente
(`X-Forwarded-For`) y no la del proxy. Sin esto, la validación PRESENCIAL de RN-03.6 se rompería.

---

## 6. Reglas de negocio — integración ZKTeco (protocolo ADMS push)

Endpoints **públicos** (fuera de `auth:sanctum`) bajo `/api/iclock/`. El reloj se configura con
**Server Path `/api/`** porque Apache solo proxea `/api/*` a Laravel. Reloj real en producción:
`SenseFace 7A`, serial `VDE2261200055`.

### 6.1 Autorización de dispositivo

- **RN-03.11** — `dispositivoAutorizado()` = existe `d2_zkteco_dispositivo` con `serial = SN` y
  `activo = true`. Si no → respuesta `ERROR` en **texto plano** con HTTP 403 (formato que el
  protocolo ADMS reconoce como rechazo → el reloj reintenta después).

### 6.2 `GET|POST /api/iclock/cdata` — `cdata()`

- **RN-03.12 (GET = handshake)** — si viene `SN`: registra el contacto vía `registrarContacto()`
  (ver RN-03.11b). Responde el bloque de opciones ADMS (`Realtime=1`,
  `TransFlag=TransData AttLog`, etc.) en texto plano. Sin `SN` → `ERROR` 400.
- **RN-03.11b (`registrarContacto()` — 2026-09-01)** — helper único que corre en cada handshake y en
  `registry`. **Dispositivo nuevo** → se inserta con `activo = false` (queda pendiente de que el
  admin lo active desde `admin/zkteco`). **Dispositivo ya conocido** → solo actualiza `ip` /
  `ultimo_push`; **`activo` nunca se reescribe** en el contacto. Antes ponía `activo = true`
  incondicionalmente, lo que anulaba cualquier desactivación manual del admin en segundos (basta con
  que el reloj vuelva a tocar el endpoint).
- **RN-03.13 (POST = marcaciones reales)** — requiere `dispositivoAutorizado()` (403 si no).
  Actualiza `ultimo_push`/`ip`. Cada línea del body (`\n`) tiene campos separados por **tab**:
  `PIN \t DateTime \t Status \t Verify \t ...`.
  - **PIN → cédula:** si `strlen(PIN) === 9` y es numérico → se antepone `'0'` (ZKTeco borra el cero
    inicial de cédulas que empiezan por 0).
  - `DateTime` debe cumplir `^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$` — si no, se ignora la línea.
  - **Lookup:** empleado `ACTIVO` con `identificacion = PIN` (cédula 10 díg.). Si no existe → se ignora.
  - **Deduplicación:** si ya existe una fila con ese `nro_documento` + `fecha_hora` exacta → se ignora.
  - **Concepto:** se asigna el primer concepto de la secuencia diaria que aún no tenga el empleado
    ese día (mismo criterio que la web). Si ya tiene 4 → se ignora.
  - Inserta en `sg_control_persona` con `lugar = 'BIOMETRICO'`, `tipo_marcacion = 'BIOMETRICO'`,
    `clasificacion` derivada del concepto, `procesado = 'NO'`, `ip` = IP del reloj.
  - Respuesta `OK` 200 (texto plano).
- **RN-03.14 (manejo de errores)** — todo el cuerpo va en `try/catch (\Throwable)`. Ante cualquier
  excepción (típicamente pérdida de conexión a Postgres) → `errorAdms()` responde `ERROR` en texto
  plano HTTP 500 y **loguea**. **Nunca** se deja escapar el error 500 HTML de Laravel: el protocolo
  ADMS no lo reconoce y el reloj daría la marcación por entregada sin reintentar. *(Incidente
  2026-07-17 — ver CLAUDE.md.)*

### 6.3 Otros endpoints ADMS

| Endpoint | Comportamiento |
|---|---|
| `GET /api/iclock/getrequest` | Polling. Requiere dispositivo autorizado (403 si no). Cada 2 min (cache key por `SN`) devuelve un comando `DATA QUERY table=attlog ...` para traer marcaciones; el resto del tiempo responde vacío. Envuelto en try/catch → `ERROR`. |
| `GET|POST /api/iclock/registry` | Registro inicial al arrancar. Llama `registrarContacto()` (RN-03.11b): dispositivo nuevo → `activo = false`; conocido → solo `ip`/`ultimo_push`. **Abierto** (no exige autorización previa) para que el reloj se registre solo, pero ya no puede quedar operativo sin activación manual del admin. |
| `POST /api/iclock/devicecmd` | Confirmación de comandos. Requiere autorización; responde `OK`. |
| `GET /api/iclock/ping` | Responde `OK` siempre. |

### 6.4 Administración de dispositivos (`/api/admin/zkteco`, Sanctum)

- **RN-03.15** — `index`: lista todos los dispositivos. `update`: edita `nombre` y `activo`
  (`activo` requerido, bool). `destroy`: elimina el dispositivo.
- Vista: `views/admin/ZktecoView.vue`, rol `ADMINISTRADOR`.
- **RN-03.16** — Si se restaura un backup y se pierde el registro del dispositivo, el reloj empieza
  a recibir 403; hay que reinsertarlo manualmente (SQL en CLAUDE.md).

---

## 7. Reglas de negocio — proceso de cuadre (`procesar:cuadre`)

Comando artesanal `php artisan procesar:cuadre {--fecha=YYYY-MM-DD}`. Sin `--fecha` → hoy.

### 7.1 Programación automática

- **RN-03.17** — `routes/console.php`: `Schedule::command('procesar:cuadre')->dailyAt('23:55')`.
  Requiere que el cron del sistema ejecute `php artisan schedule:run` cada minuto en el servidor.

### 7.2 Disparo manual

- **RN-03.18** — `POST /api/cuadre/procesar` (`CuadreController::procesar`) — rol Admin/TH. `fecha`
  opcional. Ejecuta `Artisan::call('procesar:cuadre', ['--fecha' => $fecha])` y devuelve el output.

### 7.3 Algoritmo (`ProcesarCuadre::handle`)

Para **cada empleado `ACTIVO`** (no filtra depto 999 — ver §9):

1. **Turno del día:** `colTurno = 's' . díaDelMes`. Busca `d2_programacion` del empleado para
   ese año/mes; `idTurno = (int)($prog->{$colTurno} ?? 1)`; si no hay programación → `1`.
2. **Horas programadas** (`turnoHora`): de `d2_turno` filtrado por `id_turno`, toma la `hora` de cada
   concepto y la convierte a decimal (`hour + minute/60`, redondeo 4). Concepto ausente → `0.0`.
3. **Marcaciones reales:** las de `sg_control_persona` del empleado para esa fecha, ordenadas. Se
   busca por `concepto`; **fallback por posición** (0,1,2,3) si el concepto no aparece.
4. **Horas reales** (`toDecimalHours`): `hour + minute/60` (trunca segundos), o `null` si falta la marca.
5. **Permisos aprobados que solapan el día** (`d2_permiso`, `estado_permiso = APROBADO`,
   `fecha_desde <= día <= fecha_hasta`):
   - `todo_dia = 'SI'` → `entradaJustificada = 99.0`, `salidaJustificada = 0.0` (todo el día justificado); rompe el bucle.
   - `tipo_horario = 'ENTRADA'` → `entradaJustificada = max(hora_hasta del permiso)` (hora hasta la que puede llegar tarde sin penalidad).
   - `tipo_horario = 'SALIDA'` → `salidaJustificada = min(hora_desde del permiso)` (hora mínima de salida sin penalidad).
   - `tipo_horario = 'ENTRE JORNADA'` → **no** ajusta `atraso_entrada`/`atraso_salida`.
6. **Atraso de entrada** (minutos, ≥ 0):
   - Si `entradaJustificada` definida y `rEntrada <= entradaJustificada` → `max(0, round((rEntrada − entradaJustificada) × 60))` (normalmente 0).
   - Si no → `max(0, round((rEntrada − horaTurnoEntrada) × 60))`.
   - Sin `rEntrada` → 0.
7. **Atraso de lunch:** solo si hay `rSalLunch` y `rEntLunch`. Límite de regreso =
   `rSalLunch + 30 min` (30 minutos exactos desde que salió, **sin importar** la hora de turno).
   `atrasoLunch = max(0, round((min(rEntLunch, tSalida) − límite) × 60))`.
8. **Atraso de salida:**
   - `salidaJustificada` definida y `rSalida >= salidaJustificada` → 0.
   - `salidaJustificada` definida y `rSalida < salidaJustificada` → `round((salidaJustificada − rSalida) × 60)`.
   - Si no → `max(0, round((horaTurnoSalida − rSalida) × 60))`.
   - Sin `rSalida` → 0.
9. **Aporte de permisos al descuento (2026-09-01)** — para los permisos `APROBADO` vigentes ese día:
   los **descontables** suman su equivalente en horas (factor 30/22) a `horas_decto`; los **no
   descontables** suman a `horaspermiso_pag`. El aporte se cuenta solo si la fecha procesada cae
   dentro del rango real del permiso.
10. **`horas_decto`** = `round((atrasoEntrada + atrasoLunch + atrasoSalida) / 60 + aporte_permisos_descontables, 4)`.
    **Recalculado desde cero cada corrida** — `ProcesarCuadre` es la única fuente de verdad de este
    campo; `PermisosController::aprobar()`/`anular()` ya **no** lo tocan (spec 04). Una anulación se
    refleja sola en el siguiente `procesar:cuadre --fecha=X`.
11. **`horaspermiso_pag`** = aporte de los permisos no descontables del día (también desde cero).
12. **`tiempo_lunch`** = minutos entre salida y entrada del lunch (reales si existen, si no programados).
13. **`horas_totales`** = `round((rSalida − rEntrada) − almuerzo, 4)` (almuerzo real o programado), o `null` si falta entrada o salida.
14. **`falta`** = `'S'` si no hay marcación de entrada, `'N'` si la hay.
15. **`updateOrInsert`** en `d2_cuadre_marcacion` por `(id_emp, fecha '00:00:00')` con todos los
    campos + snapshot del empleado + `ip = '127.0.0.1'`.

> **Idempotencia (2026-09-01):** el orden aprobación-de-permiso vs. corrida-de-cuadre ya no importa;
> `horas_decto` y `horaspermiso_pag` se reconstruyen enteros en cada corrida (nunca con `+=`).

### 7.4 Listado del cuadre

- **RN-03.19** — `GET /api/cuadre/listado` (rol Admin/TH): `d2_cuadre_marcacion` join `ad_empleado`
  + `ad_departamento`, filtrado por `fecha` (default hoy), opcional `departamento_id` y `buscar`
  (ILIKE apellido/nombre/identificación). Vista: `views/admin/cuadre/CuadreView.vue` (ruta `admin/cuadre`).

---

## 8. Reglas de negocio — reportes de asistencia

### 8.1 `GET /api/asistencia/listado` — `listado()` (Admin/TH)

- **RN-03.20** — Marcaciones de `sg_control_persona` de una `fecha` (default hoy) con
  `empleado.departamento`, ordenadas por `fecha_hora`. Filtros: `departamento_id`, `buscar` (ILIKE).
- Consumido también por el tab "Marcaciones del Día" de `ReportesView.vue` (spec 11). Sin export.

### 8.2 `GET /api/asistencia/reporte` — `reporte()` (Admin/TH)

- **RN-03.21** — `fecha_desde`/`fecha_hasta` requeridas. Marcaciones en el rango, opcional `id_emp`.
  Ordenado por `nro_documento`, `fecha_hora`. Sin export en este endpoint (el export vive en spec 11).

### 8.3 `GET /api/asistencia/mi-reporte` — `miReporte()` (empleado)

- **RN-03.22** — Del empleado autenticado, rango `fecha_desde`/`fecha_hasta` (default: inicio de mes
  → hoy). `tipo` ∈ {`todos`, `justificados`, `injustificados`}.
- **RN-03.23** — Cruza cada marcación (`ENTRADA`, `ENTRADA DEL LUNCH`, `SALIDA`) con:
  - El cuadre del día (`d2_cuadre_marcacion`) → `atraso` en minutos del campo correspondiente
    (`atraso_entrada` / `atraso_lunch` / `atraso_salida`).
  - Los minutos de permisos `APROBADO` del día por `tipo_horario`
    (`ENTRADA` → concepto ENTRADA; `ENTRE JORNADA` → concepto ENTRADA DEL LUNCH; `SALIDA` → concepto SALIDA),
    calculados como `SUM(hora_hasta − hora_desde)` en minutos.
- **RN-03.24** — Campo `justificado` por fila:
  - `null` si `atraso = 0`.
  - `"TOTAL"` si `minutosPermiso >= atraso`.
  - `"PARCIAL"` si `0 < minutosPermiso < atraso`.
  - `"NO"` si `minutosPermiso = 0`.
- **RN-03.25** — Filtro `justificados`: solo filas con `atraso > 0` y algún permiso.
  `injustificados`: solo filas con `atraso > 0` y **sin** permiso.

### 8.4 `GET /api/asistencia/reporte-sin-atrasos` — `reporteSinAtrasos()` (Admin/TH inline)

- **RN-03.26** — `fecha_desde`/`fecha_hasta` requeridas (422 si faltan). Empleados `ACTIVO`,
  `id_depto != 999`, que **tienen** al menos un registro de cuadre en el rango **y** **no tienen**
  ningún cuadre en el rango con `atraso_entrada > 0` o `atraso_lunch > 0` o `atraso_salida > 0`.
  Ordenado por departamento, apellido.
- Vista standalone `ReporteSinAtrasosView.vue` + integrado como tab "Sin Atrasos" en
  `ReportesView.vue` (spec 11). **No** debe tener entrada de menú propia.

---

## 9. UI

| Ruta | Componente | Rol | Descripción |
|---|---|---|---|
| `asistencia` | `views/AsistenciaView.vue` | empleado | Botones de marcación (íconos PNG), 3 estados visuales (apagado / activo / por activarse). Bloqueo por modalidad con banner ámbar. Toast flotante fijo para errores/éxito. Confirmación al marcar SALIDA antes de 16:30. Muestra `ARTICULO_ATRASOS` y el historial personal (columna "Atraso", "Debió: HH:MM" en el retorno de lunch). |
| `asistencia/sin-atrasos` | `views/asistencia/ReporteSinAtrasosView.vue` | Admin/TH | Reporte sin atrasos (también tab en Reportes). |
| `admin/cuadre` | `views/admin/cuadre/CuadreView.vue` | Admin/TH | Listado del cuadre por fecha + botón "Procesar cuadre". |
| `admin/zkteco` | `views/admin/ZktecoView.vue` | ADMINISTRADOR | CRUD de dispositivos: toggle activo/inactivo, editar nombre, eliminar. |

Detalle de `AsistenciaView.vue` (colores, tamaños, precarga de imágenes, `btnStyle()`, `miEstado()`)
en CLAUDE.md §`views/asistencia/`.

---

## 10. Criterios de aceptación

**Marcación web — modalidad**
- **CA-03-1** — Empleado `BIOMETRICO`: `mi-estado` devuelve `puede_marcar = false` con el mensaje de reloj biométrico; `POST /marcar` responde 403 y no inserta.
- **CA-03-2** — Empleado `TELETRABAJO` sin período vigente hoy: `marcar` responde 403. Con período `fecha_desde <= hoy <= fecha_hasta`: `marcar` inserta con `tipo_marcacion = 'TELETRABAJO'`.
- **CA-03-3** — Empleado `PRESENCIAL` con `VLANS_PERMITIDAS = '10.10.12.'` marcando desde `10.10.99.5`: 403. Desde `10.10.12.7`: se inserta. Con `VLANS_PERMITIDAS` vacío: se inserta desde cualquier IP.
- **CA-03-4** — Empleado `TEMPORAL` marca desde cualquier IP sin validación de VLAN, con `tipo_marcacion = 'WEB'`.

**Marcación web — secuencia y control de IP**
- **CA-03-5** — Marcar `SALIDA AL LUNCH` sin haber marcado `ENTRADA` → 422 "Debes registrar ENTRADA primero".
- **CA-03-6** — Marcar `ENTRADA` dos veces el mismo día → la segunda vez 422 "Ya registraste ENTRADA hoy".
- **CA-03-7** — Con `CONTROL_IP_MARCACION = 1`, si el empleado B intenta marcar desde una IP que el empleado A ya usó hoy → 403.
- **CA-03-8** — Tras 4 marcaciones, `mi-estado.siguiente = null` y un 5º intento con cualquier concepto ya existente → 422.

**ZKTeco**
- **CA-03-9** — POST a `/api/iclock/cdata` con `SN` de un dispositivo `activo = false` → cuerpo `ERROR`, HTTP 403, texto plano; ninguna marcación insertada.
- **CA-03-10** — POST con una línea `987654321\t2026-09-01 08:03:00\t0\t15` y un empleado `ACTIVO` con cédula `0987654321` → se inserta una marcación `BIOMETRICO` con concepto `ENTRADA` (si es la primera del día).
- **CA-03-11** — Reenviar exactamente la misma línea (mismo `nro_documento` + `fecha_hora`) → no se duplica.
- **CA-03-12** — Si Postgres está caído durante un POST a `cdata`, la respuesta es `ERROR` texto plano HTTP 500 (no una página HTML de Laravel) y queda un log de error.
- **CA-03-13** — `GET /api/iclock/cdata?SN=NUEVO` (handshake) crea el dispositivo con **`activo = false`** (2026-09-01) y responde el bloque de opciones ADMS; sus POST de marcaciones siguen siendo rechazados con 403 hasta que un admin lo active.
- **CA-03-13b** — Un admin desactiva un dispositivo desde `admin/zkteco`; el reloj vuelve a hacer handshake → `activo` **sigue en `false`** (`registrarContacto()` no reescribe `activo` de un dispositivo conocido).
- **CA-03-24** — Aprobar un permiso descontable a futuro y correr `procesar:cuadre` de esa fecha → `horas_decto` refleja el permiso, sin importar que la fila de cuadre no existiera al aprobar. Anular el permiso y volver a correr el cuadre → `horas_decto` vuelve a bajar solo.

**Cuadre**
- **CA-03-14** — Empleado con turno de entrada 08:00 que marcó 08:14 y **sin** permiso → `atraso_entrada = 14`.
- **CA-03-15** — Mismo caso con un permiso `APROBADO` `tipo_horario = ENTRADA`, `hora_hasta = 08:30` que cubre el día → `atraso_entrada = 0`.
- **CA-03-16** — Permiso `APROBADO` con `todo_dia = 'SI'` → `atraso_entrada = atraso_salida = 0` sin importar las marcaciones.
- **CA-03-17** — Empleado que marcó `SALIDA AL LUNCH` 13:00 y `ENTRADA DEL LUNCH` 13:45 → `atraso_lunch = 15` (regresó 15 min después del límite de 30).
- **CA-03-18** — Empleado sin ninguna marcación de entrada ese día → fila de cuadre con `falta = 'S'`, `horas_totales = null`.
- **CA-03-19** — `POST /api/cuadre/procesar` sin `fecha` procesa el día de hoy y devuelve el resumen "N empleados procesados".
- **CA-03-20** — Re-procesar la misma fecha actualiza (no duplica) la fila `(id_emp, fecha)` en `d2_cuadre_marcacion`.

**Reportes**
- **CA-03-21** — `mi-reporte` con `tipo = injustificados` solo devuelve días con `atraso > 0` y sin permiso que lo cubra.
- **CA-03-22** — `reporte-sin-atrasos` excluye a un empleado que tuvo `atraso_salida = 5` cualquier día del rango, aunque el resto de días haya llegado puntual.
- **CA-03-23** — Un usuario sin rol Admin/TH que llama `GET /api/asistencia/listado` recibe 403.

---

## 11. Casos borde

| Caso | Comportamiento |
|---|---|
| Empleado sin `d2_programacion` para el mes | Usa `id_turno = 1` en el cuadre. |
| Turno `1` sin filas en `d2_turno` | Horas programadas = `0.0` → cualquier marcación real cuenta como atraso enorme (`round((rEntrada − 0) × 60)`). Riesgo real si el catálogo de turnos está incompleto. |
| Marcaciones en desorden (concepto no coincide con la posición) | `ProcesarCuadre` hace fallback por posición 0/1/2/3 — puede tomar la marca equivocada. |
| Empleado `INACTIVO` que marcó ese día | `ProcesarCuadre` solo itera `ACTIVO` → su marcación no genera cuadre. |
| Empleado con `id_depto = 999` activo | **Sí** se le procesa cuadre (el comando no excluye 999). *(Hallazgo §12.)* |
| Marca `SALIDA` a las 16:00 (antes de 16:30) | La web pide confirmación (`window.confirm`); si confirma, se registra y el cuadre calcula `atraso_salida` contra la hora de turno. |
| PIN del reloj con longitud distinta de 9 o 10 | Si ≠ 9 no se rellena; si no matchea ningún empleado se ignora silenciosamente. |
| `fecha_hora` del reloj con segundos | El cuadre trunca segundos (`toDecimalHours`); la dedupe del reloj compara `fecha_hora` exacta (con segundos). |
| Permiso `tipo_horario = ENTRADA` con rango amplio (10:00–16:30) | Puede marcar el día como "Justificado" para la entrada aunque el rango no tenga sentido semántico. Advertencia conocida (spec 04). |
| `modalidad_marcacion` con valor no reconocido | `marcar()` cae al camino por defecto → marca como `WEB` sin validación de VLAN. |

---

## 12. Deuda técnica / hallazgos

1. **`ProcesarCuadre` no excluye `id_depto = 999`** ni empleados `es_externo` → genera cuadres
   basura para los funcionarios externos activos. Contradice CA-00-1.
2. **Turno `0.0` por catálogo incompleto** produce atrasos falsos gigantes sin ninguna alerta.
3. **`procesado` nunca se actualiza a `'SI'`** — el flag de `sg_control_persona` queda siempre en `NO`;
   revisar si algo depende de él (nómina/legado).
4. **Fallback por posición** en el cuadre es frágil si las marcaciones no están en orden canónico.
5. **`marcar()` sin validación de enum de modalidad** — una modalidad mal escrita evita la
   validación de VLAN silenciosamente.
6. **Dependencia del `schedule:run`** — si el cron del servidor no está configurado, el cuadre
   nocturno no corre y nadie se entera hasta que faltan datos.
7. ✅ **RESUELTO (2026-09-01)** — Auto-activación de dispositivos: `registrarContacto()` (antes
   `cdata` GET / `registry` ponían `activo = true` en cada contacto, anulando la desactivación
   manual del admin) ahora inserta los nuevos con `activo = false` y nunca reescribe `activo` de un
   dispositivo conocido; migración `000105` cambia el default de la columna a `false`. `registry`
   sigue **abierto** (el reloj necesita registrarse solo) pero un dispositivo no autorizado ya no
   puede quedar operativo sin acción del admin.
8. **`reporte` / `listado` de marcaciones sin paginar** — un rango grande puede devolver miles de filas.
9. **`d2_cuadre_marcacion.ip = '127.0.0.1'` fijo** — el campo no aporta información.

---

## 13. Dependencias

- **Spec 00** — roles, `d2_configuracion`, `trustProxies`, incidente 2026-07-17.
- **Spec 01** — `modalidad_marcacion`, `ad_empleado_teletrabajo`, `ubicacion`, `estado`.
- **Spec 04 (Permisos)** — provee `d2_permiso` (`estado_permiso`, `tipo_horario`, `todo_dia`,
  `hora_desde`/`hora_hasta`) que el cuadre consume para ajustar atrasos; su lógica de descuento de
  vacaciones es independiente.
- **Spec 05 / 09 / 11** — consumen `d2_cuadre_marcacion`.
- `d2_turno` / `d2_programacion` — gestión fuera de esta spec (pantalla de turnos / planificación).
- Cron del servidor (`schedule:run`), Apache reverse proxy, reloj físico ZKTeco.

---

## 14. Preguntas abiertas

- ¿`ProcesarCuadre` debe filtrar `id_depto != 999` y `es_externo = false`? (§12.1)
- ¿Alertar cuando un empleado activo no tiene turno válido (horas 0.0)? (§12.2)
- ~~¿Cerrar la auto-activación de dispositivos nuevos?~~ ✅ hecho 2026-09-01 (§12.7). Queda abierto si `registry` debería además exigir algún token.
- ¿El cuadre debe marcar `procesado = 'SI'` en las marcaciones que consumió? (§12.3)
- ¿Registrar auditoría de las marcaciones manuales / correcciones de cuadre? (hoy no hay ninguna traza)
- ¿Paginar `asistencia/listado` y `asistencia/reporte`?
