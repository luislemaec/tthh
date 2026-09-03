# Spec 11 — Dashboard y Reportes de Talento Humano

| | |
|---|---|
| **Versión** | 1.0 (borrador) |
| **Última actualización** | 2026-09-03 |
| **Depende de** | [00 — Paraguas](00-modulo-talento-humano.md), [01 — Empleados](01-empleados.md), [03 — Asistencia](03-control-asistencia.md), [04 — Permisos](04-permisos-licencias.md), [05 — Vacaciones](05-vacaciones.md), [07 — Liquidación](07-liquidacion-vacaciones.md) |
| **Controladores** | `DashboardController`, `ReportesController`, `LotaipController`, `ReporteEmpleadosController` (+ `AsistenciaController::listado/reporteSinAtrasos` de spec 03, `AccionPersonalController::historialRemuneraciones` de spec 06) |
| **Vistas** | `DashboardView.vue`, `reportes/ReportesView.vue`, `reportes/LotaipView.vue`, `empleados/ReporteEmpleadosView.vue`, `acciones/HistorialRemuneracionesView.vue`, `asistencia/ReporteSinAtrasosView.vue` |
| **Tipo** | Retro-especificación |

---

## 1. Propósito

Reunir las pantallas de **consulta y análisis** de Talento Humano: el dashboard con métricas
adaptadas al rol del usuario, y los reportes operativos (atrasos, marcaciones faltantes, movimientos
de personal, LOTAIP, nómina de personal). Ninguna de estas pantallas **modifica** datos — son solo
lectura y export (Excel / PDF).

### 1.1 Mapa de pantallas

| Pantalla | Controlador / método | Ruta Vue |
|---|---|---|
| Dashboard | `DashboardController::index` / `pendientes` / `atrasosCoordinacion` | `dashboard` |
| Reportes (5 tabs) | `ReportesController` + `AsistenciaController` | `reportes` |
| LOTAIP (2 tabs) | `LotaipController::directorio` / `remuneraciones` | `reportes/lotaip` |
| Reporte de Empleados / Nómina de Personal | `ReporteEmpleadosController::resumen` / `index` | `empleados/reporte` |
| Historial de cargos y remuneraciones | `AccionPersonalController::historialRemuneraciones` (**spec 06 §6.2**) | `acciones-personal/historial-remuneraciones` |
| Sin Atrasos (standalone + tab de Reportes) | `AsistenciaController::reporteSinAtrasos` (**spec 03 §8.4**) | `asistencia/sin-atrasos` |

### 1.2 Fuera de alcance

- El cálculo del cuadre y sus reglas (spec 03), del saldo de vacaciones (spec 05), del descuento de
  permisos (spec 04) — aquí solo se **consumen**.
- Los reportes de otros módulos (Adquisiciones, Transportes, Tecnología, Comisiones).

---

## 2. Actores y control de acceso

| Pantalla | Guard | Observación |
|---|---|---|
| Dashboard (`/dashboard`, `/dashboard/*`) | ninguno (por diseño) | El endpoint es compartido: **acota el alcance de los datos según el rol dentro del método** en vez de bloquear. `total_activos` / `por_departamento` (headcount por área) sí se devuelven a cualquier autenticado — dato poco sensible (§11.4). |
| Reportes — Atrasos / Marcaciones faltantes / Movimientos | `requireRole(['ADMINISTRADOR','TALENTO HUMANO'])` en cada método ✅ | |
| Reportes — Sin Atrasos | chequeo inline Admin/TH (spec 03 §8.4) ✅ | |
| Reportes — Marcaciones del Día | `requireRole` (spec 03 §8.1) ✅ | |
| **LOTAIP** (`/reportes/lotaip/*`) | `requireRole(ROLES_ADMIN)` en `directorio` y `remuneraciones` ✅ | |
| **Reporte de Empleados** (`/empleados/reporte`, `/empleados/reporte/resumen`) | `requireRole(ROLES_ADMIN)` en `resumen` e `index` ✅ (**cerrado 2026-09-03** — antes sin control) | |
| Historial de remuneraciones | `requireRole` (spec 06) ✅ | |

El frontend además oculta estas pantallas del menú para roles distintos de TH/Admin.

---

## 3. Dashboard (`DashboardController`)

### 3.1 `GET /api/dashboard` — `index`

Devuelve un objeto único cuyo contenido depende de dos flags calculados por consulta a
`admin_usuario_rol`: `es_admin_th` (`ADMINISTRADOR` o `TALENTO HUMANO`) y `es_supervisor`
(presencia en `dbo.supervisor_area`).

**Campos siempre presentes:**
- **RN-11.1** — `total_activos` = empleados `ACTIVO`, `id_depto != 999`.
- **RN-11.2** — `por_departamento` = `[{nombre_depto, total}]` de activos por departamento, orden desc.
- **RN-11.3** — `permisos_pendientes` / `vacaciones_pendientes`: cuenta de `PENDIENTE` filtrada por rol:
  - Admin/TH → todos (depto ≠ 999).
  - Supervisor (no Admin/TH) → solo su equipo (`empleadosDeSupervisor()` — con cascada a departamentos hijos, igual que specs 04/05).
  - Empleado sin rol → solo los propios.
- **RN-11.4** — `es_supervisor`, `es_admin_th`.

**`datos_supervisor`** (solo si `es_supervisor && !es_admin_th`):
- **RN-11.5** — `total_equipo`, `he_pendientes` (`nom_he_planificacion_cab` PENDIENTE del equipo),
  `materiales_pendientes` (`adq.solicitud_material` PENDIENTE), `presentes_hoy` (marcaron `ENTRADA`
  hoy), `con_permiso_hoy` (permiso `APROBADO` `todo_dia = 'SI'` vigente hoy), `con_vacaciones_hoy`
  (vacación `APROBADO` vigente hoy), `atrasos_mes`.
- **RN-11.6 (`atrasos_mes`)** — SQL puro (`DB::select`) con `NOT EXISTS` correlacionado: días del mes
  actual del equipo con `atraso_entrada > 0` **o** `atraso_lunch > 0` que **no** estén cubiertos por
  un permiso `APROBADO` del tipo correspondiente (`ENTRADA` / `ENTRE JORNADA`) o `todo_dia = 'SI'`.
- **RN-11.7 (`vacaciones_proximas`)** — planificaciones `APROBADO`/`REPLANIFICADO` del año actual del
  equipo cuyo período cae en los próximos 60 días o ya está en curso; cada una con `en_curso`
  (`fecha_inicial <= hoy`). `vacaciones_proximas_count` aparte.

**`datos_empleado`** (solo si `!es_admin_th && !es_supervisor`):
- **RN-11.8 (saldo de vacaciones)** — ⚠ **cálculo inline propio**, NO `SaldoVacacionesService`
  (§11.3). Tasa LOSEP 2.50 / Código del Trabajo por antigüedad; devengo desde `FECHA_CORTE_VACACIONES`
  (o `fecha_ingreso` si es posterior). Devuelve `saldo_vacaciones` (`min(60, max(0, …))`) y
  `saldo_vacaciones_real` (`min(60, …)`, puede ser negativo — para que un Nombramiento Definitivo en
  rojo se vea también aquí, no solo en `VacacionesView`).
- **RN-11.9** — `atrasos_por_mes` (12 valores: días del año con `atraso_entrada > 0` o
  `atraso_lunch > 0`), `dias_adicionales_antiguedad`, `modalidad_laboral`, `proximo_periodo` (primer
  período planificado `APROBADO`/`REPLANIFICADO` del año con `fecha_final >= hoy`).

**`cuadre_alerta`** (solo `es_admin_th`):
- **RN-11.10** — `{ ultimo_at, atrasado }` — `atrasado = true` si `ULTIMO_CUADRE_PROCESADO`
  (`d2_configuracion`) es nulo o de hace más de 26 h. `DashboardView.vue` muestra un banner ámbar.
  (Monitoreo del cron `schedule:run` — spec 03 §12.6.)

### 3.2 `GET /api/dashboard/pendientes` — `pendientesSupervisor`

- **RN-11.11** — Empleado sin rol → `{permisos:0, vacaciones:0, horas_extras:0, materiales:0}`.
  Supervisor → conteos de su equipo; Admin/TH → conteos globales (depto ≠ 999). Alimenta las 4
  tarjetas "pendientes" del dashboard del supervisor.

### 3.3 `GET /api/dashboard/atrasos-coordinacion` — `atrasosCoordinacion`

- **RN-11.12** — Del **empleado autenticado** (cualquier rol): por cada mes transcurrido del año,
  el nº de días con atraso (`entrada` / `lunch` / `salida`) que **no** están cubiertos por un permiso
  `APROBADO` del tipo correspondiente. SQL puro con `NOT EXISTS`. Alimenta el gráfico personal "Mis
  trámites personales no justificados por mes" (título deliberadamente "trámites personales", no
  "atrasos", para no colisionar con el término del reglamento disciplinario). Colores:
  verde 0 / ámbar 1–2 / rojo 3+.

---

## 4. Reportes (`ReportesView.vue` — 5 tabs)

Filtros comunes de los tabs con datos de rango: `fecha_desde`, `fecha_hasta` (**requeridos**),
`id_depto`, `id_emp` (⚠ búsqueda difusa ILIKE sobre cédula/nombre/apellido, no match exacto).
Cada tab con datos exporta con `?formato=excel|pdf` (blob URL + `a.download`).

### 4.1 Tab "Atrasos" (`GET /api/reportes/atrasos` — `atrasos`)

- **RN-11.13** — Base `d2_cuadre_marcacion` join empleado + departamento, filas con
  `atraso_entrada > 0` **o** `atraso_lunch > 0` **o** `atraso_salida > 0` en el rango.
- **RN-11.14** — Por fila calcula los minutos **pendientes** = `atraso − minutos_justificados_por_permiso`
  (subquery `SUM(hora_hasta − hora_desde)` de permisos `APROBADO` del día por `tipo_horario`). Solo
  se devuelven filas con `minutos_pendientes > 0` (las totalmente justificadas se descartan).
- **RN-11.15** — Campo `justificacion`: `"PARCIAL"` si hubo algún permiso, `"NINGUNA"` si no.
- PDF `reporte_atrasos.blade.php` (landscape A4).

### 4.2 Tab "Marcaciones No Realizadas" (`GET /api/reportes/marcaciones-faltantes` — `marcacionesFaltantes`)

- **RN-11.16** — Cruza **todos los empleados `ACTIVO`** (depto ≠ 999, filtros opcionales) × cada
  fecha del rango. Para cada (empleado, fecha) agrupa las marcaciones de `sg_control_persona` por
  concepto; devuelve la fila **solo si falta al menos una** de las 4 (`ENTRADA`, `SALIDA AL LUNCH`,
  `ENTRADA DEL LUNCH`, `SALIDA`).
- **RN-11.17** — Etiqueta `motivo = 'VACACIONES'` si el empleado tiene una vacación `APROBADO` que
  cubre esa fecha. **No** excluye fines de semana ni feriados (§11.4).
- PDF `reporte_faltantes.blade.php` (landscape A4).

### 4.3 Tab "Sin Atrasos"

- **RN-11.18** — `GET /api/asistencia/reporte-sin-atrasos` (spec 03 §8.4). Empleados `ACTIVO` que
  **tienen** cuadre en el rango y **ninguno** con atraso. Los filtros depto/empleado se **ocultan**
  en este tab. Sin export. La vista standalone `asistencia/sin-atrasos` no debe tener entrada de
  menú propia (sería duplicado).

### 4.4 Tab "Movimientos de Personal" (`GET /api/reportes/movimientos-personal` — `movimientosPersonal`)

- **RN-11.19** — Parámetro `tipos` (coma-sep, default `VACACIONES,PERMISO,LICENCIA,COMISION`;
  `COMISIÓN` con tilde se normaliza a `COMISION`). Una query por tipo, merge en PHP
  (`collect()->concat()`), orden final por `fecha_desde` + nombre:
  - `VACACIONES` → `d2_vacacion` `APROBADO`, `fecha_inicial` en rango; `dias = fecha_final − fecha_inicial + 1`.
  - `PERMISO` → `d2_permiso` `descontable = 'SI'`, `APROBADO`, `fecha_desde` en rango; `dias` solo si `todo_dia = 'SI'`.
  - `LICENCIA` → `d2_permiso` `descontable = 'NO'`, ídem.
  - `COMISION` → `vac_liquidacion_historico` `motivo ∈ {INICIO_COMISION, FIN_COMISION_SALIDA}`, `fecha_evento` en rango (spec 07).
- **RN-11.20** — Columnas: Tipo (badge de color), Empleado, Cargo, Departamento, Fecha Desde, Fecha
  Hasta, Días, Detalle. PDF `reporte_movimientos.blade.php` (landscape A4). Todo con `id_depto != 999`.

### 4.5 Tab "Marcaciones del Día"

- **RN-11.21** — `GET /api/asistencia/listado` (spec 03 §8.1). Filtro de **fecha única** (default
  hoy) + departamento + buscar. Columnas: Empleado, Departamento, Concepto (badge), Hora, Tipo
  (`WEB`/`TELETRABAJO`/`BIOMETRICO`), IP. Techo `->limit(2000)`. Sin export.

---

## 5. LOTAIP (`LotaipController` — `LotaipView.vue`, 2 tabs, solo export Excel)

Reporte de transparencia LOTAIP Art. 7 lit. m). Ambos métodos exigen `requireRole(ROLES_ADMIN)`.
Base: empleados `ACTIVO`, `id_depto != 999`, ordenados por apellido, nombre (sin agrupar por
departamento).

### 5.1 Tab "Directorio y Distributivo" (`GET /api/reportes/lotaip/directorio`)

- **RN-11.22** — Columnas: Nro, Cédula, Apellidos y Nombres, Dirección/Área (`nombre_depto`),
  Dirección Institucional (config `DIRECCION_INSTITUCIONAL`), Ciudad (config `UBICACION_DEFAULT`),
  Teléfono Institucional (config `TELEFONO_INSTITUCIONAL`), Extensión (`ad_empleado.extension`),
  Correo Electrónico (primer `ad_empleado_mail` `ACTIVO` por `secuencial`).
- **RN-11.23** — `?formato=excel` → `.xlsx` con encabezado verde institucional. Sin `formato` → JSON.

### 5.2 Tab "Remuneraciones" (`GET /api/reportes/lotaip/remuneraciones`)

- **RN-11.24** — Columnas: Nro, Cédula, Cargo, Tipo Contrato, Partida Individual, Grado (`nivel`),
  Salario Base (`sueldo`), Remuneración Anual (`sueldo × 12`), Décimo Tercero (**en blanco**),
  Décimo Cuarto (**en blanco**).
- **RN-11.25** — Variables de config requeridas para el Directorio: `DIRECCION_INSTITUCIONAL`,
  `UBICACION_DEFAULT`, `TELEFONO_INSTITUCIONAL`.

---

## 6. Reporte de Empleados / Nómina de Personal (`ReporteEmpleadosController` — `ReporteEmpleadosView.vue`)

Ambos métodos (`resumen`, `index`) exigen `requireRole(ROLES_ADMIN)` — **cerrado el 2026-09-03**
(antes no tenían ningún control; ver §11.2).

### 6.1 `GET /api/empleados/reporte/resumen` — `resumen`

- **RN-11.26** — Sobre empleados `ACTIVO`, `id_depto != 999`:
  - **Alertas** (números clickables que aplican el filtro): `sercop_vencido`, `sercop_proximo`
    (≤30 días), `sustituta_vencida`, `sustituta_proxima`, `guarderia` (empleados con ≥1 hijo < 5 años).
  - **Stats** (para gráficos Chart.js): `por_sexo`, `por_contrato`, `por_modalidad` (de marcación),
    `por_antiguedad` (rangos < 5 / 5–10 / 10–15 / 15–20 / 20+, ordenados por antigüedad mínima).

### 6.2 `GET /api/empleados/reporte` — `index`

- **RN-11.27** — Listado con `left join` a departamento + 4 catálogos sociales. **16+ filtros**:
  `estado` (default `ACTIVO`, `TODOS` para no filtrar), `id_depto`, `busqueda`, `tipo_contrato`
  (TRIM), `modalidad_laboral`, `modalidad_marcacion`, `sexo`, `tipo_sangre`, `grupo_vulnerable_id`,
  `grupo_prioritario_id`, `tiene_discapacidad`, `tiene_enfermedad`, `puede_vehiculo`, `motivo_salida`,
  `es_comisionado_entrante`, `antiguedad` (5 rangos), `sercop_filter` (vencido/proximo/sin/con),
  `sustituta_filter` (vencida/proxima/tiene), `con_guarderia`.
- **RN-11.28** — Enriquece cada empleado con `hijos_menores_5` (conteo). Orden: departamento, apellido.
- **RN-11.29** — **No pagina en backend** — devuelve el listado completo (el frontend pagina 30/pág
  y el export necesita todo). `?formato=excel` (27 columnas, título dinámico según `antiguedad`,
  filas alternadas) / `?formato=pdf` (landscape A4, `reporte_empleados.blade.php`).
- **RN-11.30** — El campo cédula en `ad_empleado` es **`identificacion`**, no `cedula`.

---

## 7. UI — notas

- **`DashboardView.vue`** — 3 layouts según rol:
  - Admin/TH: métricas globales (Empleados, Departamentos, Permisos) + gráfico de atrasos por
    coordinación + banner `cuadre_alerta`.
  - Supervisor (no admin): 4 tarjetas de pendientes + widget "Mi equipo hoy" + atrasos del mes +
    tarjeta "Vacaciones próximas del equipo" ("Ver quiénes" expande la lista).
  - Empleado sin rol: 3 tarjetas compactas (permisos pendientes / saldo vacaciones / próximo
    período) + gráfico "Mis trámites personales no justificados por mes".
  - Todos: `<ChatbotFAB />`. `saldo_vacaciones_real` en rojo si Nombramiento Definitivo y negativo.
- **`ReportesView.vue`** — 5 tabs; filtros depto/empleado se ocultan en "Sin Atrasos" y "Marcaciones
  del Día"; botones Excel/PDF visibles cuando `datos.length > 0` (excepto Sin Atrasos y Marcaciones
  del Día). Export usa blob URL + `a.download`. **Vue 3:** nunca `v-for` + `v-if` en el mismo tag.
- **`LotaipView.vue`** — página independiente (no tab de Reportes); solo export Excel, sin PDF.
- **`ReporteEmpleadosView.vue`** — 4 secciones: tarjetas de alerta clickables → aplican filtro; 3
  gráficos Chart.js; panel de filtros colapsable con badge de filtros activos; tabla con badges de
  color para fechas vencidas/próximas + paginación (frontend).

Detalle completo de cada vista en CLAUDE.md (`views/reportes/`, `DashboardView.vue`, `views/empleados/`).

---

## 8. Criterios de aceptación

**Dashboard**
- **CA-11-1** — Un empleado sin rol especial recibe `datos_empleado` poblado y `datos_supervisor = null`.
- **CA-11-2** — Un supervisor (no Admin/TH) ve `permisos_pendientes` contando solo su equipo (incluidos los departamentos hijos).
- **CA-11-3** — `dashboard/atrasos-coordinacion` de un empleado con un atraso de entrada cubierto por un permiso `APROBADO` tipo `ENTRADA` ese día → ese día **no** cuenta.
- **CA-11-4** — Un Nombramiento Definitivo con saldo real −3 ve `datos_empleado.saldo_vacaciones = 0` y `saldo_vacaciones_real = -3`.
- **CA-11-5** — Si `ULTIMO_CUADRE_PROCESADO` es de hace 30 h, un usuario Admin/TH recibe `cuadre_alerta.atrasado = true`.

**Reportes**
- **CA-11-6** — `GET /api/reportes/atrasos` sin `fecha_desde`/`fecha_hasta` → HTTP 422.
- **CA-11-7** — Un usuario sin rol Admin/TH que llama `GET /api/reportes/movimientos-personal`, `/reportes/lotaip/remuneraciones` o `/empleados/reporte` → HTTP 403.
- **CA-11-8** — El reporte de Atrasos **no** incluye filas cuyo atraso quede totalmente cubierto por permisos.
- **CA-11-9** — El reporte de Marcaciones No Realizadas incluye una fila por cada día del rango en que un empleado activo tenga ≥1 marcación faltante, etiquetada `VACACIONES` si estaba de vacaciones aprobadas.
- **CA-11-10** — `movimientos-personal?tipos=VACACIONES` devuelve solo filas de vacaciones aprobadas del rango.
- **CA-11-11** — Cualquier tab con datos exporta `.xlsx` y `.pdf` con el encabezado institucional.

**LOTAIP / Reporte de Empleados**
- **CA-11-12** — `GET /api/reportes/lotaip/remuneraciones` devuelve `sueldo` y `sueldo × 12` de todos los empleados activos, ordenados por apellido.
- **CA-11-13** — `GET /api/empleados/reporte?sercop_filter=vencido` devuelve solo empleados con `fecha_vence_sercop < hoy`.
- **CA-11-14** — `empleados/reporte/resumen` devuelve `guarderia` = nº de empleados con al menos un hijo menor de 5 años.
- **CA-11-15** — El export Excel del reporte de empleados con `antiguedad=mas20` titula "NÓMINA DE PERSONAL — 20 O MÁS AÑOS DE SERVICIO".

---

## 9. Casos borde

| Caso | Comportamiento |
|---|---|
| Empleado sin `fecha_ingreso` en `resumen` / `por_antiguedad` | Excluido de `por_antiguedad` (`whereNotNull`); en `index`, `anios_servicio` sale `null`. |
| `movimientos-personal` con rango de meses | Una query por tipo, todas en memoria → un rango muy amplio con muchos tipos puede ser pesado (sin paginación ni límite). |
| `marcaciones-faltantes` en un rango que incluye fines de semana / feriados | Los cuenta como "faltantes" igual — no cruza contra `d2_lista_fecha` ni contra la programación de turnos (§11.2). |
| `empleados/reporte` sin filtros | Devuelve **toda** la nómina (activos por defecto) sin paginar — cientos de filas en una respuesta. |
| Dashboard de un usuario que es Admin/TH **y** supervisor | `es_admin_th` gana: ve el layout Admin/TH, `datos_supervisor = null`. |
| `atrasos_mes` / `atrasosCoordinacion` con equipo vacío | Devuelve 0 sin ejecutar el SQL (`if (!empty($idsParam))`). |
| LOTAIP: empleado sin email activo | Columna "Correo Electrónico" en blanco. |
| Décimo 13°/14° en LOTAIP Remuneraciones | Siempre en blanco — se llenan a mano en el Excel. |

---

## 10. Dependencias

- **Spec 00** — roles, `d2_configuracion`, `supervisor_area`, PDF/Excel estándar.
- **Spec 01** — `ad_empleado`, `ad_departamento`, catálogos sociales, `ad_empleado_mail`, `ad_empleado_hijo`, `ad_empleado.extension`.
- **Spec 03** — `d2_cuadre_marcacion` (todos los reportes de atrasos), `sg_control_persona`
  (marcaciones faltantes / del día), `ULTIMO_CUADRE_PROCESADO`.
- **Spec 04** — `d2_permiso` (justificación de atrasos, movimientos).
- **Spec 05** — `d2_vacacion`, `vac_planificacion_cab/det`, `d2_cabecera_vacacion`,
  `FECHA_CORTE_VACACIONES` (saldo del dashboard).
- **Spec 07** — `vac_liquidacion_historico` (movimientos tipo COMISION).
- **Spec 06** — `AccionPersonalController::historialRemuneraciones` (pantalla listada aquí, spec propia allá).
- `nom_he_planificacion_cab`, `adq.solicitud_material` (conteos de pendientes del supervisor).
- `phpoffice/phpspreadsheet` para los export Excel.

---

## 11. Deuda técnica / hallazgos

1. ✅ **RESUELTO (2026-09-03)** — `ReporteEmpleadosController::resumen` / `index` sin control de
   rol (exponía la nómina completa con datos sociales/salud sensibles a cualquier autenticado):
   cerrado con `requireRole(ROLES_ADMIN)` en ambos métodos. `LotaipController` ya estaba cerrado
   (ambos métodos con `requireRole`) — verificado. `ReportesController` (atrasos / marcaciones
   faltantes / movimientos) también, desde la auditoría del 2026-08-17.
2. **`marcaciones-faltantes` cuenta fines de semana y feriados** como faltantes — no cruza contra
   `d2_lista_fecha` ni contra `d2_programacion` (¿el empleado tenía turno ese día?). El reporte
   infla el nº de "faltantes" con días no laborables. *(abierto)*
3. **`DashboardController::index` — 6ª copia del cálculo de saldo de vacaciones** (inline, líneas
   ~199–226), no migrada a `SaldoVacacionesService` cuando se unificaron las otras 5 (spec 05 §12.2,
   spec 07). Riesgo de divergencia; además no considera `cabecera.fecha_proceso` (el corte
   por-empleado del retorno de comisión — spec 07). *(abierto)*
4. **`empleados/reporte` e `historialRemuneraciones` no paginan en backend** — devuelven el listado
   completo. Para instituciones grandes, respuestas pesadas. (Deliberado para el export; convendría
   paginar el modo JSON.) *(abierto — menor)*
5. **`movimientosPersonal` sin límite** — merge en memoria de N queries sin tope de filas. *(abierto — menor)*
6. **Filtro `id_emp` con nombre engañoso** — en todos los reportes es una búsqueda difusa ILIKE,
   no un `id_emp` exacto. Cosmético, transversal a spec 03/06/11. *(abierto — cosmético)*
7. **Dashboard sin guard de rol — evaluado, no es un gap.** `index` / `pendientesSupervisor` acotan
   el alcance de los datos según el rol dentro del método (diseño correcto para un dashboard
   compartido). `total_activos` / `por_departamento` (headcount) se devuelven a cualquier autenticado
   — dato poco sensible.

---

## 12. Preguntas abiertas

- ~~¿Cerrar `LotaipController` / `ReporteEmpleadosController` a rol Admin/TH?~~ ✅ hecho / verificado 2026-09-03 (§11.1).
- ¿Migrar el saldo del dashboard a `SaldoVacacionesService`? (§11.3)
- ¿`marcaciones-faltantes` debería excluir fines de semana / feriados / días sin turno programado? (§11.2)
- ¿Paginar el modo JSON de `empleados/reporte` y `historialRemuneraciones`? (§11.4)
- ¿Un tope de filas / rango máximo para `movimientos-personal`? (§11.5)
- ¿Renombrar el parámetro `id_emp` a `buscar` en los reportes? (§11.6)
