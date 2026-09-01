# Spec 05 — Vacaciones

| | |
|---|---|
| **Versión** | 1.0 (borrador) |
| **Última actualización** | 2026-09-01 |
| **Depende de** | [00 — Módulo Talento Humano](00-modulo-talento-humano.md), [01 — Empleados](01-empleados.md), [04 — Permisos y licencias](04-permisos-licencias.md) |
| **Controladores** | `VacacionesController`, `PlanificacionVacController`, `ReporteVacacionesController`, `ReportePlanificacionController`, `PeriodoPlanificacionController` |
| **Modelos** | `Vacacion` (`dbo.d2_vacacion`), `CabeceraVacacion` (`dbo.d2_cabecera_vacacion`), `DetalleVacacion` (`dbo.d2_detalle_vacacion`), `PlanificacionCab` (`dbo.vac_planificacion_cab`), `PlanificacionDet` (`dbo.vac_planificacion_det`), `PeriodoPlanificacion` (`dbo.vac_periodo_planificacion`) |
| **Vistas** | `VacacionesView.vue`, `planificacion/PlanificacionesView.vue`, `planificacion/ReportePlanificacionView.vue`, `planificacion/ReporteSaldoVacView.vue`, `admin/periodos/PeriodosView.vue` |
| **PDF** | `reportes/planificacion_vacaciones.blade.php`, `reportes/vac_reporte_saldo.blade.php` |
| **Tipo** | Retro-especificación |

---

## 1. Propósito

Gestionar el derecho a vacaciones de cada servidor: cálculo del **saldo disponible**, **solicitud**
puntual de días con aprobación del supervisor, **planificación anual** obligatoria de los 30 días, y
los **reportes** de saldo (kardex) y de planificación (con archivo firmado en Alfresco).

El saldo de vacaciones es un valor **derivado** (se calcula al vuelo a partir de `fecha_ingreso`,
`tipo_contrato`, la fecha de corte y los días ya tomados) más un ajuste manual guardado
(`d2_cabecera_vacacion.dias_adicionales`). No hay un "saldo" almacenado que se mantenga sincronizado.

> **Fuente única de cálculo (desde 2026-09-01):** `App\Services\SaldoVacacionesService`. Antes la
> fórmula estaba **duplicada en 4 controladores** con reglas divergentes (ver §5.3). Los 4 ahora
> inyectan el servicio y delegan en `calcular()` / `calcularInterno()` / `tasaVacaciones()`.

### 1.1 Alcance

Incluye: fórmula de saldo (tasa LOSEP / Código del Trabajo con antigüedad, tope de 60 días, saldo
negativo para Nombramiento Definitivo), solicitud/aprobación/negación/eliminación de vacaciones,
flujo de "informe favorable" de TH, backup al aprobar, planificación anual (períodos, ventana de
planificación, re-planificación), reporte de saldo con kardex, carga masiva de saldos, edición de
saldo individual, reporte de planificación + subida a Alfresco, CRUD de períodos de planificación.

**No** cubre: liquidación de vacaciones por comisión/desvinculación (**spec 07**); el descuento de
vacaciones que producen los **permisos descontables** (**spec 04** — aquí solo se documenta que
comparten `d2_cabecera_vacacion`).

---

## 2. Actores

| Actor | Puede |
|---|---|
| Empleado `ACTIVO` (no depto 999) | Ver su saldo y kardex; solicitar vacaciones; crear su planificación anual (si tiene ≥ 11 meses de servicio y hay período activo). |
| `SUPERVISOR` (`dbo.supervisor_area`) | Aprobar / negar / eliminar solicitudes y planificaciones `PENDIENTE` de su equipo; asignar backup al aprobar; re-planificar. No sobre sí mismo. **Auto-aprueba** su propia planificación al crearla. |
| `ADMINISTRADOR`, `TALENTO HUMANO` | Todo lo anterior sobre cualquier empleado; marcar informe favorable/desfavorable; ver el reporte de saldo de todos; ver/generar el reporte de planificación; subir el PDF firmado. |
| `ADMINISTRADOR` (solo) | Editar el saldo individual de un empleado; cargar saldos masivamente; gestionar los períodos de planificación. |

Helpers de rol: `esSupervisor` (en `supervisor_area`), `esAdminOTH` (rol `ADMINISTRADOR`/`TALENTO HUMANO`),
`esAdministrador` (rol `ADMINISTRADOR`). `empleadosDeSupervisor()` **incluye supervisores de
departamentos hijos en los 3 controladores** (`VacacionesController`, `PermisosController` y
`PlanificacionVacController` — este último alineado el 2026-09-01; antes solo miraba el
departamento directo).

---

## 3. Historias de usuario

- **HU-05.1** — Como empleado, quiero ver cuántos días de vacaciones tengo disponibles hoy.
- **HU-05.2** — Como empleado de Código del Trabajo con más de 5 años, quiero que mi saldo incluya los días adicionales por antigüedad.
- **HU-05.3** — Como empleado, quiero solicitar un rango de días de vacaciones y que se valide contra mi saldo.
- **HU-05.4** — Como empleado con Nombramiento Definitivo, quiero poder solicitar aunque no me alcance el saldo, sabiendo que TH debe emitir un informe favorable primero.
- **HU-05.5** — Como supervisor, quiero aprobar la solicitud de un colaborador y designar quién lo reemplaza (backup del mismo departamento).
- **HU-05.6** — Como TH, quiero emitir un informe favorable o desfavorable sobre una solicitud con exceso de saldo.
- **HU-05.7** — Como empleado, quiero planificar mis 30 días del año en hasta 4 períodos dentro de la ventana de planificación.
- **HU-05.8** — Como supervisor, quiero aprobar/negar la planificación de mi equipo y, si hace falta, re-planificarla una vez.
- **HU-05.9** — Como TH, quiero un reporte del saldo de todos los empleados y el kardex de movimientos de cada uno.
- **HU-05.10** — Como Administrador, quiero cargar los saldos iniciales de todos los empleados desde un CSV y mover la fecha de corte, todo o nada.
- **HU-05.11** — Como TH, quiero generar el PDF consolidado de la planificación anual, imprimirlo, hacerlo firmar y subir el escaneado.

---

## 4. Modelo de datos

### 4.1 `dbo.d2_cabecera_vacacion` — ajuste + acumulados por empleado

PK `id_emp` (1 fila por empleado). Se crea con contadores en 0 al dar de alta un empleado (spec 01 / 02).

| Campo | Significado |
|---|---|
| `dias_adicionales` | **Saldo base cargado manualmente** (carga inicial CSV o edición individual). Es el "saldo inicial" de la fórmula. Puede quedar negativo solo por `UPDATE` directo (la UI valida `min:0`). |
| `total_dias_tomados` | Días consumidos: vacaciones aprobadas (días calendario) + permisos descontables (días × factor 30/22, spec 04). La carga masiva lo resetea a 0. |
| `total_tomados` | Contador paralelo que `aprobar()` de vacaciones también incrementa (no lo tocan los permisos). |
| `dias_x_tomar_normal` | Saldo "por tomar" que se decrementa al aprobar y se recompone al anular permisos. La carga masiva lo iguala a `dias_adicionales`. |
| `fecha_proceso` | Fecha de la última carga de saldo del empleado. |
| `total_fin_semana`, `dias_x_tomar_fin_semana`, `dias_totales`, `venta_normal`, `venta_adicional` | Legado; se inicializan en 0. |

### 4.2 `dbo.d2_vacacion` — solicitudes puntuales

PK del modelo: `secuencial_clave`. Ruta `{id}` = `secuencial_clave`.

| Campo | Contenido |
|---|---|
| `id_emp`, `nombre_emp` | Empleado (nombre = `"APELLIDO NOMBRE"` copiado al crear). |
| `fecha_inicial`, `fecha_final` | Rango solicitado (date). `fecha_final >= fecha_inicial`. |
| `hora_desde`, `hora_hasta` | Timestamps `"fecha hora:00"` (vacaciones siempre son día completo — la hora es vestigial). |
| `todo_dia` | Default `'SI'`. |
| `estado_permiso` | `PENDIENTE` → `APROBADO` / `NEGADO` / `ELIMINADO`; `APROBADO` → `ANULADO` (nuevo 2026-09-01, vía `anular()`). |
| `observaciones` | Motivo del empleado (≤250). |
| `observacion_negacion` | Motivo de negación / eliminación / anulación. |
| `aprobado_por` (FK `aprobador()`), `aprobado_en` | Supervisor que aprobó + timestamp. |
| `backup_id`, `backup_nombre` | Empleado que cubre durante la ausencia (mig. `000055`). Opcionales. |
| `requiere_informe` | Boolean (mig. `000100`). `true` si se creó con saldo insuficiente (solo Nombramiento Definitivo). |
| `informe_estado` | `FAVORABLE` / `DESFAVORABLE` / null (mig. `000100`). |
| `informe_fecha`, `informe_por` | Fecha y autor del informe. |
| `ip` | IP del solicitante. |
| `updated_at`, `updated_by` | Auditoría de la última acción. |

### 4.3 `dbo.d2_detalle_vacacion`

Tabla legada, **vacía / sin uso** (no hay migración que la pueble). `miSaldo` la devuelve en
`detalle` pero la UI ya no muestra "detalle por período". Ver §12.

### 4.4 `dbo.vac_planificacion_cab` — planificación anual (cabecera)

Constraint lógica: `unique(id_emp, anio)` (registros `NEGADO`/`ELIMINADO` se **reutilizan** con `UPDATE`).

| Campo | Contenido |
|---|---|
| `id_emp`, `anio` | — |
| `estado` | `PENDIENTE` → `APROBADO` → `REPLANIFICADO` · `NEGADO` · `ELIMINADO`. |
| `total_dias_planificados` | Suma de días de los períodos (≤ 30). |
| `fecha_registro`, `usuario_registro` | Alta. |
| `fecha_decision`, `usuario_decision` | Aprobación / negación / re-planificación. |
| `observacion` | Motivo de negación / eliminación. |
| `replanificada` | `'SI'` / `'NO'` — solo se puede re-planificar **una vez**. |

### 4.5 `dbo.vac_planificacion_det` — períodos

`cab_id`, `numero_periodo` (1–4), `fecha_inicial`, `fecha_final`, `dias_calculados` (inclusivo).

### 4.6 `dbo.vac_periodo_planificacion` — ventanas de planificación

`anio`, `fecha_inicio`, `fecha_fin`, `estado` (`ACTIVO`/…). Define **cuándo** los empleados pueden
crear su planificación de ese año. CRUD: `PeriodoPlanificacionController` (rutas `/admin/periodos-planificacion`).

### 4.7 `dbo.d2_configuracion` — parámetros

| Concepto | Uso |
|---|---|
| `FECHA_CORTE_VACACIONES` | Fecha base del devengo. La carga masiva la actualiza. |
| `APROBADOR_INST_VACACION` | Firmante del PDF de planificación. |
| `nombre_institucion` | Encabezado de PDFs. |

---

## 5. Reglas de negocio — cálculo de saldo

### 5.1 Tasa de vacaciones (`tasaVacaciones`)

- **RN-05.1 (LOSEP)** → `tasa_mensual = 2.50`, `dias_anuales = 30`, `dias_adicionales_antiguedad = 0`.
- **RN-05.2 (CODIGO DEL TRABAJO — Art. 69)** → `anios = floor(diffInYears(fecha_ingreso, hasta))`;
  `dias_extra = min(max(0, anios − 5), 15)`; `dias_anuales = 15 + dias_extra`;
  `tasa_mensual = dias_anuales / 12`.
  - 0–5 años → 15 días/año (1.25/mes); 6 años → 16; 7 → 17; … 20+ años → 30 (tope +15).
- **RN-05.3** — Cualquier otro `tipo_contrato` → tasa 0 (sin devengo).

### 5.2 Saldo disponible (`SaldoVacacionesService::calcular()` — desde 2026-09-01)

> Antes: `VacacionesController::calcularSaldoDisponible()`. La fórmula no cambió; solo se movió a un
> servicio único inyectado por los 4 controladores.

- **RN-05.4** — `fechaCorte = FECHA_CORTE_VACACIONES`; si `fecha_ingreso > fechaCorte` → `fechaCorte = fecha_ingreso`.
- **RN-05.5** — `fechaHasta = hoy`; si el empleado está `INACTIVO` **y** tiene `fecha_salida` →
  `fechaHasta = fecha_salida` (**el acumulado se congela** en la fecha de salida).
- **RN-05.6** — `diasCalendario = max(0, diffInDays(fechaCorte, fechaHasta))`;
  `diasAcumulados = round(diasCalendario / 360 × (tasa_mensual × 12), 2)`.
- **RN-05.7** — `disponibles = round(dias_adicionales + diasAcumulados − total_dias_tomados, 2)`.
- **RN-05.8 (dos valores de salida)**:
  - `dias_disponibles = min(60, max(0, disponibles))` — nunca negativo. **Se usa para planificación
    y para el descuento de permisos** (spec 04).
  - `dias_disponibles_real = min(60, disponibles)` — **puede ser negativo**. Se usa para mostrar en
    UI y para validar nuevas solicitudes.
- **RN-05.9 (TOPE DE 60 DÍAS — LOSEP Art. 29)** — el saldo visible/solicitable nunca supera 60. El
  acumulado interno sigue corriendo sin límite (no se borra ni se congela); solo el display y la
  validación están topados. **Excepción:** la liquidación (spec 07) usa el valor real sin tope.

### 5.3 Historial de divergencia — ✅ RESUELTO (2026-09-01)

Hasta el 2026-09-01 el cálculo de saldo estaba replicado en **4 lugares** con reglas distintas:

| Implementación (histórica) | Tasa CT | Tope 60 | Piso 0 | Congela en `fecha_salida` |
|---|---|---|---|---|
| `VacacionesController::calcularSaldoDisponible` | por antigüedad ✅ | sí | `_real` no | sí |
| `PermisosController::calcularInternoVac` | por antigüedad ✅ | **no** | no | **no** |
| `ReporteVacacionesController::calcularSaldoActual` / `…Real` | por antigüedad ✅ (`tasaVacaciones` propio duplicado) | sí | `Real` no | sí |
| `PlanificacionVacController::calcularSaldo` | **fija 1.25** ❌ (ignoraba antigüedad) | sí | sí | **no** |

**Fix:** `App\Services\SaldoVacacionesService` es ahora la única fuente. Métodos públicos:
- `calcular()` — shape completo (`saldo_inicial`, `acumulado_a_hoy`, `tomados`, `dias_disponibles`,
  `dias_disponibles_real`, `dias_anuales`, `dias_adicionales_antiguedad`).
- `calcularInterno()` — sin tope de 60 (para `PermisosController::aprobar()`).
- `tasaVacaciones()` — tasa por antigüedad.

`VacacionesController`, `PlanificacionVacController`, `ReporteVacacionesController` y
`PermisosController` lo inyectan por constructor y delegan. La pantalla de planificación de un
empleado CT con 6+ años ahora muestra el saldo real (antes salía menor por la tasa fija 1.25).

### 5.4 Saldo negativo y Nombramiento Definitivo (mig. `000100`)

- **RN-05.10** — Solo empleados con `modalidad_laboral = 'Nombramiento Definitivo'` pueden terminar
  con saldo negativo (vía RN-05.13 o por permisos descontables no bloqueados).
- **RN-05.11** — En la UI (`VacacionesView.vue`, Dashboard, Reporte de saldo, PDF) el
  `dias_disponibles_real` negativo se muestra **en rojo** cuando
  `modalidad_laboral === 'Nombramiento Definitivo' && saldo_real < 0`.
- **RN-05.12** — Otras modalidades con saldo negativo (típicamente por corrección manual) quedan
  **bloqueadas** para pedir vacaciones pero la UI les muestra "0" sin explicación — decisión de
  negocio pendiente (§13).

---

## 6. Reglas de negocio — solicitud de vacaciones

### 6.1 Listado (`GET /api/vacaciones` — `index`)

- **RN-05.13a** — Visibilidad por rol + `vista` (`"mia"`/`"equipo"`/`""`), idéntico patrón a permisos
  (spec 04 RN-04.1). Filtros: `estado`, `fecha_desde` (`fecha_inicial >=`), `fecha_hasta`
  (`fecha_final <=`). Paginado (`per_page`, default 15), orden `fecha_hora` desc. **Sin export.**

### 6.2 `GET /api/vacaciones/mi-saldo` — `miSaldo`

- **RN-05.14** — Empleado `INACTIVO` → responde `inactivo: true` y saldo 0. `ACTIVO` → `cabecera`,
  `detalle` (de `d2_detalle_vacacion`, normalmente vacío), `saldo_calculado`
  (`calcularSaldoDisponible`), `modalidad_laboral`.

### 6.3 Solicitud (`POST /api/vacaciones` — `store`)

Request: `fecha_inicial*`, `fecha_final*` (`after_or_equal:fecha_inicial`), `hora_desde*`,
`hora_hasta*`, `todo_dia`, `observaciones` (≤250).

- **RN-05.15** — Solicitante = `$request->user()`. `id_depto = 999` → 403. `estado != 'ACTIVO'` → 403.
- **RN-05.16** — `diasSolicitados = diffInDays(fecha_inicial, fecha_final) + 1` (días calendario, inclusivo).
- **RN-05.17 (race condition — fix)** — `diasPendientes` = suma de `(fecha_final − fecha_inicial + 1)`
  de las solicitudes del empleado en estado `PENDIENTE`. `saldoEfectivo = dias_disponibles_real − diasPendientes`.
  Descontar las pendientes evita que dos solicitudes simultáneas pasen la validación con el mismo saldo.
- **RN-05.18 (bloqueo por saldo)** — si `saldoEfectivo < diasSolicitados`:
  - `modalidad_laboral = 'Nombramiento Definitivo'` → se crea igual con `requiere_informe = true`
    (el saldo puede quedar negativo). Auditoría `SOLICITUD_CON_EXCESO`.
  - otra modalidad → HTTP 422 `"No tienes suficientes días disponibles. Disponibles: X, solicitados: Y"`.
- **RN-05.19 (solapamiento)** — si existe otra vacación del empleado en estado ≠ `NEGADO`/`ELIMINADO`
  cuyo rango se cruce → HTTP 422.
- **RN-05.20** — Se crea `PENDIENTE`, `todo_dia` default `'SI'`, `nombre_emp = "APELLIDO NOMBRE"`.
  **No** descuenta saldo todavía (el descuento es al aprobar). HTTP 201.

### 6.4 Aprobación (`PATCH /api/vacaciones/{id}/aprobar` — `aprobar`)

- **RN-05.21** — No sobre la vacación propia → 403. Autorización: Admin/TH cualquiera; supervisor
  solo su equipo (`empleadosDeSupervisor`, con hijos) → 403.
- **RN-05.22** — Debe estar `PENDIENTE` → 422.
- **RN-05.23 (gate de informe)** — si `requiere_informe && informe_estado !== 'FAVORABLE'` → HTTP 422
  "Esta solicitud requiere informe favorable de Talento Humano antes de ser aprobada".
- **RN-05.24** — `estado_permiso = 'APROBADO'`, `aprobado_en`, `aprobado_por`, `updated_by = supervisor`;
  guarda `backup_id` / `backup_nombre` (opcionales del request).
- **RN-05.25 (descuento)** — `dias = diffInDays(fecha_inicial, fecha_final) + 1` (calendario, **sin**
  factor 30/22 — los 30 días de vacaciones ya incluyen fines de semana). Sobre `d2_cabecera_vacacion`:
  - `dias_x_tomar_normal = max(0, dias_x_tomar_normal − dias)`
  - `total_dias_tomados += dias`
  - `total_tomados += dias`
- **RN-05.26** — Auditoría `APROBAR`.

### 6.5 `GET /api/vacaciones/{id}/empleados-depto` — `empleadosDepto`

- **RN-05.27** — Empleados `ACTIVO` del mismo departamento del solicitante (excluyéndolo), para
  elegir el backup en el modal de aprobación.

### 6.6 Informe favorable/desfavorable (`PATCH /api/vacaciones/{id}/marcar-informe` — `marcarInforme`)

- **RN-05.28** — Solo `ADMINISTRADOR` / `TALENTO HUMANO` (`requireRole`). `informe_estado` requerido
  (`FAVORABLE`|`DESFAVORABLE`).
- **RN-05.29** — La solicitud debe tener `requiere_informe = true` (422 si no) y estar `PENDIENTE` (422).
- **RN-05.30** — Guarda `informe_estado`, `informe_fecha = hoy`, `informe_por`.
- **RN-05.31 (desfavorable → negar)** — si `DESFAVORABLE` → `estado_permiso = 'NEGADO'` automáticamente.
- **RN-05.32** — Auditoría `INFORME_FAVORABLE` / `INFORME_DESFAVORABLE`.
- En la UI: badge naranja "Requiere informe TH" en la tabla; modal con botones Favorable / Desfavorable.

### 6.7 Negar / eliminar (`negar`, `destroy`)

- **RN-05.33** — `negar`: `observacion_negacion` opcional (≤120). `destroy`: **requerido** (≤120).
  Ambos: no sobre la propia, autorización supervisor/Admin-TH, estado `PENDIENTE` (422). Cambian a
  `NEGADO` / `ELIMINADO` (soft). Auditoría `NEGAR` / `ELIMINAR`.
- **RN-05.34** — `negar` / `destroy` **no** revierten saldo (nunca se descontó — solo operan sobre `PENDIENTE`).

### 6.8 Anulación de vacaciones aprobadas (`PATCH /api/vacaciones/{id}/anular` — `anular`) — nuevo 2026-09-01

- **RN-05.35** — Solo `ADMINISTRADOR` / `TALENTO HUMANO`. `observacion_negacion` **requerido**.
  El estado debe ser `APROBADO`.
- **RN-05.35b (reversa)** — resta los **mismos días calendario** (`diffInDays + 1`) que sumó
  `aprobar()`: `dias_x_tomar_normal += dias`, `total_dias_tomados −= dias`, `total_tomados −= dias`
  sobre `d2_cabecera_vacacion`. Estado → `ANULADO`. Auditoría `ANULAR`.
- **Uso:** vacación aprobada que el empleado finalmente no tomó. Mismo patrón que
  `PermisosController::anular()`. Antes no existía — el descuento quedaba fijo sin vía limpia de reversa.

---

## 7. Reglas de negocio — planificación anual

### 7.1 `GET /api/planificacion/mi-planificacion` — `miPlanificacion`

- **RN-05.36** — Devuelve: el `periodo` de planificación `ACTIVO` que cubre hoy; la `planificacion`
  del empleado para `?anio=` (default año actual) en estado ≠ `ELIMINADO`/`NEGADO`; su `saldo`
  (`calcularSaldo` → delega en `SaldoVacacionesService` desde 2026-09-01, ya correcto para CT);
  `meses_servicio`; `puede_planificar`.
- **RN-05.37 (11 meses de servicio)** — si `fecha_ingreso > FECHA_CORTE_VACACIONES` →
  `meses_servicio = diffInMonths(fecha_ingreso, hoy)`, `puede_planificar = meses_servicio >= 11`.
  Si `fecha_ingreso <= corte` → se asume 11 meses cumplidos (`puede_planificar = true`).

### 7.2 Crear planificación (`POST /api/planificacion` — `store`)

Request: `anio*` (int), `periodos*` (array 1–4 de `{fecha_inicial, fecha_final}`).

- **RN-05.38** — `id_depto = 999` → 403.
- **RN-05.39** — `meses_servicio < 11` → 422.
- **RN-05.40** — Debe existir un `PeriodoPlanificacion` `ACTIVO` para ese `anio` que cubra hoy → 422 si no.
- **RN-05.41** — Solo una planificación por `(id_emp, anio)` en estado ≠ `ELIMINADO`/`NEGADO` → 422 si ya hay.
- **RN-05.42** — Al menos un período con ambas fechas → 422 si no.
- **RN-05.43** — Todas las fechas de los períodos deben pertenecer al `anio` planificado → 422.
- **RN-05.44** — Los períodos no pueden solaparse entre sí (`haysolapamiento`) → 422.
- **RN-05.45** — `total_dias = Σ (fecha_final − fecha_inicial + 1)`; si `> 30` → 422.
- **RN-05.45b (validación contra saldo — 2026-09-01)** — si `total_dias > saldo` disponible
  (`SaldoVacacionesService`, equivalente a `dias_disponibles`) → 422. Antes solo se validaba el tope
  fijo de 30 días y `calcularSaldo()` se usaba únicamente para *mostrar* el saldo en
  `miPlanificacion()`. El tope de 30 se mantiene como segundo límite.
- **RN-05.46 (auto-aprobación de supervisores)** — si el solicitante es supervisor →
  `estado = 'APROBADO'` con `fecha_decision`/`usuario_decision = él mismo`; si no → `PENDIENTE`.
- **RN-05.47 (reutilizar `NEGADO`/`ELIMINADO`)** — si ya existe una cabecera `(id_emp, anio)` en
  estado `NEGADO`/`ELIMINADO` → se hace `UPDATE` de esa fila (y se borran sus `_det`) en vez de
  `INSERT`, para no violar el `unique(id_emp, anio)`.
- **RN-05.48** — Se crean los `vac_planificacion_det` (`numero_periodo = i+1`, `dias_calculados`).
  Los períodos sin fechas completas se guardan con `null`. HTTP 201.
- **RN-05.49 (auditoría — 2026-09-01)** — `store` registra `CREAR` en `nom_auditoria_log` contra
  `dbo.vac_planificacion_cab` (PK real `id`). Antes ningún método de planificación auditaba.

### 7.3 Listado (`GET /api/planificacion` — `index`)

- **RN-05.50** — Admin/TH → todas; supervisor → `empleadosDeSupervisor` (**con** cascada a
  departamentos hijos desde 2026-09-01); empleado → solo las propias. Filtros `estado`, `anio`.
  Orden: `anio` desc, `estado`.

### 7.4 Aprobar / negar / eliminar planificación

- **RN-05.51** — `aprobar`: no la propia (403); autorización supervisor/Admin-TH; estado `PENDIENTE`
  (422) → `APROBADO` + decisión. Auditoría `APROBAR` (2026-09-01).
- **RN-05.52** — `negar` / `destroy`: `observacion` **requerida** (≤250); autorización; estado
  `PENDIENTE` (422) → `NEGADO` / `ELIMINADO`. Auditoría `NEGAR` / `ELIMINAR` (2026-09-01). (Los
  estados `NEGADO`/`ELIMINADO` habilitan re-planificar — RN-05.47.)

### 7.5 Re-planificar (`PATCH /api/planificacion/{id}/replanificar` — `replanificar`)

- **RN-05.53** — Autorización supervisor/Admin-TH. Estado debe ser `APROBADO` (422) y
  `replanificada != 'SI'` (422 — **solo una vez**).
- **RN-05.54** — Mismas validaciones de períodos que `store` (incluida la validación contra saldo,
  RN-05.45b), pero las fechas deben ser del **año actual** (`now()->year`), no del `anio` de la
  cabecera. Tope 30 días. No solapamiento.
- **RN-05.55** — Borra los `_det` anteriores, crea los nuevos, `estado = 'REPLANIFICADO'`,
  `replanificada = 'SI'`, `total_dias_planificados` actualizado. Auditoría `REPLANIFICAR` (2026-09-01).

### 7.6 Períodos de planificación (`PeriodoPlanificacionController`)

- **RN-05.56** — CRUD bajo `/api/admin/periodos-planificacion`. `activo` devuelve el período `ACTIVO`
  vigente. (Autorización y detalle: pendiente de verificar en la spec de administración.)

---

## 8. Reglas de negocio — reportes

### 8.1 Reporte de saldo (`ReporteVacacionesController`)

- **RN-05.57 (`index`)** — Empleado sin rol TH/Admin → devuelve **solo su propio** registro
  (`saldo_actual` topado + `saldo_actual_real` sin piso). Admin/TH → todos los `ACTIVO` (depto ≠ 999),
  filtros `id_depto`, `buscar` (nombre completo ILIKE o cédula). Cada fila: nombre, departamento,
  `tipo_contrato`, `modalidad_laboral`, `tomados`, `saldo_actual`, `saldo_actual_real`. Los dos
  valores de saldo salen de `SaldoVacacionesService::calcular()` desde 2026-09-01 (antes:
  `calcularSaldoActual()` / `calcularSaldoActualReal()` con `tasaVacaciones()` duplicado).
- **RN-05.58 (`detalle` / kardex)** — Empleado normal solo su propio `id_emp` (403 si otro). Devuelve
  `movimientos[]` construidos por `buildKardex()`:
  1. **INICIAL** — fecha = `FECHA_CORTE_VACACIONES` (o fecha del último "reset" por comisión), entrada = `dias_adicionales`.
  2. **LIQUIDACION** — liquidaciones históricas de contexto (spec 07).
  3. **VACACION** — una fila por vacación `APROBADO` (fecha = `aprobado_en` o `fecha_inicial`), salida = días.
  4. **VACACION legado** — si `total_dias_tomados − Σ(vacaciones individuales) > 0` → fila "Descuentos por permisos y vacaciones anteriores al sistema".
  5. **DEVENGADO** — fecha = hoy, entrada = `diasAcumulados` (descripción incluye "15 base + X por antigüedad" si aplica).
  6. **TOMADOS** — salida = `total_dias_tomados`, `saldo` = saldo final exacto del servicio.
  - Si hubo un "reset" por `FIN_COMISION_RETORNO` / `COMISION_ENTRANTE`, el kardex arranca desde esa fecha.
- **RN-05.59 (`actualizarSaldo`)** — Solo `ADMINISTRADOR`. `PATCH /reporte-vacaciones/{id_emp}/saldo`
  con `dias_adicionales` (`numeric|min:0`). Hace `updateOrCreate` de `d2_cabecera_vacacion` fijando
  `dias_adicionales` al valor recibido. **La UI calcula ese valor** a partir del "saldo disponible
  deseado" (`dias_adicionales = saldo_deseado + tomados − acumulado`). No toca la fecha de corte ni
  otros empleados.
- **RN-05.60 (`cargarSaldos` — carga masiva, todo o nada)** — Solo `ADMINISTRADOR`.
  `POST /reporte-vacaciones/cargar-saldos` con `fecha_corte*` y `saldos*` (`[{cedula, saldo>=0}]`).
  - **1ª pasada (solo verificación):** busca cada cédula como empleado `ACTIVO`, depto ≠ 999. Si
    **alguna** no existe → HTTP 422 con `no_encontrados[]` y **no se escribe nada** (ni saldos ni
    fecha de corte).
  - **2ª pasada (transaccional):** por cada empleado, `updateOrCreate` de `d2_cabecera_vacacion` con
    `dias_adicionales = saldo`, `total_dias_tomados = 0`, `total_tomados = 0`,
    `dias_x_tomar_normal = saldo`, `fecha_proceso = fecha_corte`. Luego actualiza
    `FECHA_CORTE_VACACIONES = fecha_corte`. `DB::rollBack()` + 500 ante cualquier excepción.
  - **Cédula sin ceros iniciales** (típico de Excel) es la causa más común de "no encontrado" — el
    backend **no** rellena ceros aquí (`trim($cedula)` sin `str_pad`).
  - `saldo` no acepta negativos: un Nombramiento Definitivo con saldo real negativo se carga aparte
    con un `UPDATE` directo a `d2_cabecera_vacacion`.
- **RN-05.61 (`pdf`)** — Solo TH/Admin. PDF portrait A4 (`vac_reporte_saldo.blade.php`) con los
  empleados `ACTIVO` (filtro opcional `id_depto`), columnas de saldo topado y real.

### 8.2 Reporte de planificación (`ReportePlanificacionController`)

- **RN-05.62 (`estado`)** — `GET /reporte-planificacion/{anio}/estado` — por departamento, cuenta
  empleados elegibles vs. con planificación `APROBADO`/`REPLANIFICADO`, y marca si hay replanificados.
- **RN-05.63 (`generarPdf`)** — `GET /reporte-planificacion/{anio}/pdf` — PDF consolidado
  (`planificacion_vacaciones.blade.php`) agrupado por departamento, con hasta 4 períodos por empleado
  y firma del `APROBADOR_INST_VACACION`. Solo planificaciones `APROBADO`/`REPLANIFICADO`.
- **RN-05.64 (`subirFirmado` / `descargarFirmado`)** — Sube/recupera el PDF firmado en Alfresco,
  carpeta `planificacion-vacaciones/{año}/` (patrón `relativePath`, `getOrCreateFolderNodeId`). 502
  si Alfresco falla.

---

## 9. UI

| Ruta | Componente | Descripción |
|---|---|---|
| `vacaciones` | `VacacionesView.vue` | Solicitudes del empleado y del supervisor. Tabla: Empleado, Fecha Inicio, Fecha Fin, Días (`fecha_final − fecha_inicial + 1`), Estado, Aprobado por, Acciones. Modal "Solicitar Vacaciones" (solo fechas + observaciones). Recordatorio de la planificación aprobada del año. Badge azul "15 base + X por antigüedad" para CT 6+ años. `dias_disponibles_real` en rojo si Nombramiento Definitivo y negativo. Badge naranja "Requiere informe TH". |
| `planificacion` | `planificacion/PlanificacionesView.vue` | Crear planificación (hasta 4 períodos); supervisor aprueba/niega/elimina/replanifica. |
| `planificacion/reporte` | `planificacion/ReportePlanificacionView.vue` | Estado de planificación por departamento + PDF + subir/descargar firmado. |
| `planificacion/reporte-saldo` | `planificacion/ReporteSaldoVacView.vue` | Reporte de saldo. Toggle Resumido/Detallado (empleado normal ve solo el suyo y expande su kardex). Botones "Cargar Saldos" y "Editar saldo individual" solo Admin. PDF resumido solo TH/Admin. |
| `planificacion/liquidacion` | `planificacion/LiquidacionVacView.vue` | Spec 07. |
| `admin/periodos-planificacion` | `admin/periodos/PeriodosView.vue` | CRUD de ventanas de planificación. |

Detalle de la UI del reporte de saldo (kardex, back-cálculo de `dias_adicionales`, carga todo-o-nada)
en CLAUDE.md §`views/planificacion/`.

---

## 10. Criterios de aceptación

**Saldo**
- **CA-05-1** — Empleado LOSEP con `FECHA_CORTE_VACACIONES` hace 180 días y `dias_adicionales = 0`, `total_dias_tomados = 0` → `dias_disponibles ≈ 15.00` (180/360 × 30).
- **CA-05-2** — Empleado CT con `fecha_ingreso` hace 8 años → `dias_anuales = 18` (15 + 3), `tasa_mensual = 1.5`.
- **CA-05-3** — Empleado cuyo cálculo interno da 75 → `dias_disponibles = 60` y `dias_disponibles_real = 60`.
- **CA-05-4** — Nombramiento Definitivo con cálculo interno −4 → `dias_disponibles = 0`, `dias_disponibles_real = −4` (se muestra en rojo).
- **CA-05-5** — Empleado `INACTIVO` con `fecha_salida = 2026-06-30` → el acumulado se calcula hasta el 30/06, no hasta hoy.

**Solicitud**
- **CA-05-6** — Empleado no-Nombramiento-Definitivo con `dias_disponibles_real = 3` que solicita 5 días → HTTP 422.
- **CA-05-7** — Nombramiento Definitivo con `dias_disponibles_real = 3` que solicita 5 → se crea `PENDIENTE` con `requiere_informe = true` y auditoría `SOLICITUD_CON_EXCESO`.
- **CA-05-8** — Empleado con una solicitud `PENDIENTE` de 4 días y saldo real 5 que crea otra de 3 → 422 (`saldoEfectivo = 5 − 4 = 1 < 3`).
- **CA-05-9** — Solicitar un rango que se cruza con una vacación `APROBADO` existente → 422.
- **CA-05-10** — Al crear la solicitud, `d2_cabecera_vacacion` **no** cambia.

**Aprobación / informe**
- **CA-05-11** — Aprobar la vacación propia → 403.
- **CA-05-12** — Aprobar una solicitud con `requiere_informe = true` y `informe_estado = null` → 422.
- **CA-05-13** — TH marca informe `FAVORABLE`; luego el supervisor aprueba → `total_dias_tomados` sube exactamente los días calendario del rango.
- **CA-05-14** — TH marca informe `DESFAVORABLE` → la solicitud queda `NEGADO` automáticamente.
- **CA-05-15** — Aprobar con `backup_id` → se guardan `backup_id` y `backup_nombre`.
- **CA-05-16** — Un supervisor sin rol TH marca informe → 403.

**Planificación**
- **CA-05-17** — Empleado con 9 meses de servicio → `POST /planificacion` responde 422.
- **CA-05-18** — Sin `PeriodoPlanificacion` `ACTIVO` para el año → 422.
- **CA-05-19** — Planificación con períodos que suman 31 días → 422.
- **CA-05-20** — Períodos [01/07–10/07] y [05/07–12/07] → 422 (solapamiento).
- **CA-05-21** — Un período con fecha del año siguiente al `anio` → 422.
- **CA-05-22** — Un supervisor crea su planificación → queda `APROBADO` directamente.
- **CA-05-23** — Planificación `NEGADO` del mismo `(id_emp, anio)` → una nueva `store` reutiliza esa fila (no viola `unique`).
- **CA-05-24** — Re-planificar una planificación `APROBADO` con `replanificada = 'SI'` → 422.
- **CA-05-25** — Re-planificar → estado `REPLANIFICADO`, `replanificada = 'SI'`.

**Reportes**
- **CA-05-26** — `cargarSaldos` con una cédula inexistente en la lista → HTTP 422, `no_encontrados` no vacío, y **ningún** `d2_cabecera_vacacion` modificado ni `FECHA_CORTE_VACACIONES` cambiada.
- **CA-05-27** — `cargarSaldos` con todas las cédulas válidas → cada `d2_cabecera_vacacion` queda con `total_dias_tomados = 0`, `dias_adicionales = saldo`, y `FECHA_CORTE_VACACIONES = fecha_corte`.
- **CA-05-28** — `actualizarSaldo` por un usuario que no es `ADMINISTRADOR` (aunque sea TH) → 403.
- **CA-05-29** — El kardex de un empleado con 3 vacaciones aprobadas muestra 3 filas `VACACION` + `INICIAL` + `DEVENGADO` + `TOMADOS`, y el `saldo` de la fila `TOMADOS` coincide con el saldo del servicio.
- **CA-05-30** — Un empleado normal que pide el kardex de otro `id_emp` → 403.
- **CA-05-31 (2026-09-01)** — Anular una vacación `APROBADO` de 5 días → `total_dias_tomados` baja 5, `dias_x_tomar_normal` sube 5, estado `ANULADO`, y un registro `ANULAR` en `nom_auditoria_log`. Un usuario TH sin rol `ADMINISTRADOR`… (permitido: `anular` es Admin **o** TH). Un supervisor sin rol TH → 403.
- **CA-05-32 (2026-09-01)** — Empleado CT con 8 años que planifica 20 días teniendo 12 de saldo real → `POST /planificacion` responde 422 (validación contra saldo), aunque 20 < 30.
- **CA-05-33 (2026-09-01)** — Crear una planificación genera un registro `CREAR` en `nom_auditoria_log` contra `vac_planificacion_cab`.

---

## 11. Casos borde

| Caso | Comportamiento |
|---|---|
| Empleado sin `d2_cabecera_vacacion` | `miSaldo` devuelve saldo 0; el servicio devuelve solo `diasAcumulados`. |
| `FECHA_CORTE_VACACIONES` no configurada | Se usa `hoy` → `diasCalendario = 0` → devengo 0. |
| `fecha_ingreso` posterior a la fecha de corte | La base del devengo pasa a ser `fecha_ingreso`. |
| Solicitud que cruza fin de año | `diasSolicitados` cuenta días calendario reales; no hay validación de que el rango sea de un solo año (a diferencia de la planificación). |
| Vacación aprobada que el empleado finalmente no toma | `PATCH /api/vacaciones/{id}/anular` (Admin/TH) revierte el saldo y pasa a `ANULADO` (2026-09-01). |
| `total_dias_tomados` mayor que devengo + adicionales | `disponibles` negativo; se muestra 0 salvo Nombramiento Definitivo. |
| Planificación: período sin fechas completas | Se guarda `dias_calculados = null`; no cuenta para el total ni el solapamiento. |
| `replanificar` con fechas del año de la cabecera pero no del año actual | 422 (la replanificación exige año actual, aunque la cabecera sea de un año pasado). |
| `d2_detalle_vacacion` | Siempre vacía; `miSaldo.detalle` = `[]`. |
| Empleado CT 10 años en la **pantalla de planificación** | El saldo sale correcto (con antigüedad) desde 2026-09-01 — `calcularSaldo()` delega en `SaldoVacacionesService`. Antes usaba tasa 1.25 fija. |

---

## 12. Deuda técnica / hallazgos

> **Los 6 hallazgos originales de esta spec se corrigieron el 2026-09-01** (ver CLAUDE.md
> §"Corrección de deuda técnica — Vacaciones y Planificación (2026-09-01)"). Sin cambios de schema.

1. ✅ **RESUELTO (2026-09-01)** — `PlanificacionVacController::calcularSaldo` con tasa CT fija 1.25 y
   sin congelar en `fecha_salida`: ahora delega en `SaldoVacacionesService`.
2. ✅ **RESUELTO (2026-09-01)** — 4 implementaciones divergentes del cálculo de saldo
   (`VacacionesController`, `ReporteVacacionesController`, `PlanificacionVacController`,
   `PermisosController::calcularInternoVac`): reemplazadas por `App\Services\SaldoVacacionesService`
   (`calcular()` / `calcularInterno()` / `tasaVacaciones()`), inyectado en los 4 controladores.
3. ✅ **RESUELTO (2026-09-01)** — No existía "anular" para vacaciones `APROBADO`:
   `VacacionesController::anular()` nuevo (`PATCH /api/vacaciones/{id}/anular`, solo Admin/TH,
   `observacion_negacion` requerida) revierte los mismos días calendario que sumó `aprobar()`, pasa a
   `ANULADO` y audita. Mismo patrón que `PermisosController::anular()`.
4. ✅ **RESUELTO (2026-09-01)** — `AuditoriaService::log` con `$vacacion->id` (PK real
   `secuencial_clave` → `registro_id = NULL`): corregido a `$vacacion->getKey()` en
   `aprobar`/`negar`/`destroy` + el nuevo `anular`.
5. ✅ **RESUELTO (2026-09-01)** — Planificación no auditaba: `store`/`aprobar`/`negar`/`destroy`/
   `replanificar` ahora registran `CREAR`/`APROBAR`/`NEGAR`/`ELIMINAR`/`REPLANIFICAR` contra
   `dbo.vac_planificacion_cab` (PK `id`). `PlanificacionVacController` figura ahora en la tabla de
   controladores instrumentados de la Auditoría Centralizada.
6. ✅ **RESUELTO (2026-09-01)** — `empleadosDeSupervisor` divergente: la versión de
   `PlanificacionVacController` ahora incluye la cascada a supervisores de departamentos hijos,
   igual que `VacacionesController` / `PermisosController`.
7. ✅ **RESUELTO (2026-09-01)** — `store`/`replanificar` de planificación no validaban contra el
   saldo: ahora comparan `total_dias` contra `SaldoVacacionesService` (equivalente a
   `dias_disponibles`) y rechazan con 422 si excede. El tope de 30 días se mantiene como segundo límite.
8. **Regla de validación con typo** en `PlanificacionVacController::store`:
   `'periodos.*.fecha_final' => 'nullable|date|nullable|after_or_equal:periodos.*.fecha_inicial'`
   (`nullable` repetido).
9. **`replanificar` valida solapamiento sobre `$request->periodos`** (todos, incluidos los vacíos),
   igual que `store` — `haysolapamiento` filtra los vacíos internamente, ok, pero la asimetría entre
   "validar contra `$request->periodos`" y "calcular total sobre `$periodosValidos`" es frágil.
10. **`negar`/`destroy` de vacaciones no revierten saldo** — correcto (solo operan sobre `PENDIENTE`);
    la reversa de un `APROBADO` ya existe vía `anular()` (§12.3).
11. **`index` de vacaciones sin export** (a diferencia de permisos, que sí exporta Excel/PDF). *(abierto)*

---

## 13. Dependencias

- **Spec 00** — roles, `supervisor_area`, `d2_configuracion`, Alfresco, auditoría.
- **Spec 01** — `fecha_ingreso`, `fecha_salida`, `tipo_contrato`, `modalidad_laboral`, `estado`,
  `id_depto`; `d2_cabecera_vacacion` se crea al dar de alta.
- **Spec 04 (Permisos)** — comparte `d2_cabecera_vacacion` (`total_dias_tomados`,
  `dias_x_tomar_normal`); los permisos descontables descuentan con factor 30/22, las vacaciones sin
  factor. El saldo interno sin tope lo da `SaldoVacacionesService::calcularInterno()` (mismo servicio).
- **Spec 07 (Liquidación)** — `LiquidacionHistorico` alimenta el kardex; la liquidación usa el saldo
  real **sin** tope de 60.
- **Spec 11 (Dashboard/Reportes)** — el Dashboard y el reporte de movimientos de personal consumen
  `d2_vacacion` y el saldo (verificar que también usen `SaldoVacacionesService` tras el fix del 2026-09-01).
- `PeriodoPlanificacionController` — ventanas de planificación (spec de administración).

---

## 14. Preguntas abiertas

- ~~¿Servicio único de saldo / tasa CT / anular vacaciones / auditoría de planificación / cascada de supervisores / validación contra saldo?~~ ✅ los 6 hechos el 2026-09-01 (§12.1–7).
- ¿Corregir el typo `nullable` repetido y la asimetría de `replanificar`? (§12.8–9, menores)
- ¿Definir el comportamiento de la UI para modalidades ≠ Nombramiento Definitivo con saldo negativo
  (hoy muestran "0" sin explicación y quedan bloqueadas)? (§5.4 / RN-05.12)
