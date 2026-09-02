# Spec 04 — Permisos y licencias

| | |
|---|---|
| **Versión** | 1.0 (borrador) |
| **Última actualización** | 2026-09-01 |
| **Depende de** | [00 — Módulo Talento Humano](00-modulo-talento-humano.md), [01 — Empleados](01-empleados.md), [03 — Control de asistencia](03-control-asistencia.md) |
| **Controlador** | `app/Http/Controllers/PermisosController.php` |
| **Modelos** | `App\Models\Permiso` (`dbo.d2_permiso`), `App\Models\Razon` (`dbo.d2_razon`), `App\Models\CabeceraVacacion` (`dbo.d2_cabecera_vacacion`) |
| **Vista** | `frontend/src/views/PermisosView.vue` (ruta `permisos`) |
| **PDF / Excel** | `resources/views/reportes/permisos_lista.blade.php` (PDF landscape A4), export Excel con PhpSpreadsheet |
| **Tipo** | Retro-especificación |

---

## 1. Propósito

Gestionar el ciclo de **permisos y licencias** del personal: solicitud por el empleado, aprobación
o negación por el supervisor (o TH/Admin), y anulación posterior por TH/Admin. Un permiso aprobado:

1. **Justifica** atrasos en el cuadre de asistencia (spec 03) según su `tipo_horario`.
2. Si su razón es **descontable**, reduce inmediatamente el saldo de vacaciones del empleado.

Las **licencias** son permisos con razón **no descontable** (`descontable = 'NO'`): justifican pero
no consumen saldo, y admiten documentos de respaldo.

### 1.1 Alcance

Incluye: solicitud, validación de solapamiento, aprobación (con descuento de vacaciones y ajuste de
cuadre), negación, eliminación, anulación (con reversa), documentos de respaldo en Alfresco,
estadística por supervisor, export Excel/PDF, catálogo de razones (solo lectura).

**No** cubre: el catálogo de razones en sí (`Admin/RazonController` — administración); el cálculo
base del saldo de vacaciones (**spec 05** — aquí solo se lee/decrementa `d2_cabecera_vacacion`); la
recomputación nocturna de atrasos (**spec 03**).

---

## 2. Actores

| Actor | Puede |
|---|---|
| Empleado `ACTIVO` (no depto 999) | Solicitar permiso para sí mismo; ver sus permisos; adjuntar/quitar documentos de respaldo mientras el permiso está `PENDIENTE` (solo razones no descontables). |
| `SUPERVISOR` (en `dbo.supervisor_area`) | Aprobar / negar / eliminar permisos `PENDIENTE` de los empleados que supervisa (incluye supervisores de departamentos hijos). Ver "equipo". No puede actuar sobre su propio permiso. |
| `ADMINISTRADOR`, `TALENTO HUMANO` | Aprobar / negar / eliminar **cualquier** permiso `PENDIENTE`; **anular** permisos `APROBADO`; ver todos; export; estadística global. |

**Determinación de roles** (helpers privados del controlador):
- `esSupervisor(id)` = existe en `dbo.supervisor_area` con `id_supervisor = id`.
- `esAdminOTH(id)` = tiene rol `ADMINISTRADOR` o `TALENTO HUMANO` en `admin_usuario_rol`.
- `empleadosDeSupervisor(id)` = empleados `ACTIVO` de los departamentos que supervisa (`id_depto` en
  `supervisor_area`) **+** los supervisores de los departamentos **hijos** (`ad_departamento.padre_id`),
  excluyéndose a sí mismo. Permite que un coordinador vea/apruebe permisos del director de un área hija.

---

## 3. Historias de usuario

- **HU-04.1** — Como empleado, quiero solicitar un permiso de entrada/entre jornada/salida indicando fecha(s), horas y motivo.
- **HU-04.2** — Como empleado, quiero solicitar un permiso de "todo el día" y que el sistema tome mi horario real de ese día (no un 08:00–17:00 fijo).
- **HU-04.3** — Como empleado con una licencia (permiso no descontable), quiero adjuntar el PDF/imagen de respaldo mientras está pendiente.
- **HU-04.4** — Como supervisor, quiero ver los permisos pendientes de mi equipo y aprobarlos o negarlos con un motivo.
- **HU-04.5** — Como supervisor, quiero ver un aviso cuando apruebo un permiso descontable de entrada/salida de un día que ya pasó y el empleado **no** tuvo atraso real (posible error).
- **HU-04.6** — Como TH, quiero anular un permiso aprobado que el empleado no usó y que se le devuelva el saldo de vacaciones descontado.
- **HU-04.7** — Como TH, quiero un reporte de permisos filtrable y exportable a Excel/PDF.
- **HU-04.8** — Como TH/supervisor, quiero una estadística de cuántos permisos aprobó / negó / eliminó cada supervisor en un período.

---

## 4. Modelo de datos

### 4.1 `dbo.d2_permiso`

PK del modelo: `secuencial_clave` (autoincremental). `timestamps = false`. Los métodos usan
`Permiso::findOrFail($id)` con `$id = secuencial_clave` (parámetro de ruta `{id}`).

| Campo | Contenido |
|---|---|
| `id_emp` | Empleado del permiso. |
| `sec_permiso` | FK → `d2_razon.secuencial` (razón elegida). |
| `razon` | Texto: `trim(razon.descripcion)` copiado al crear. |
| `fecha_desde`, `fecha_hasta` | Rango de días del permiso (date). |
| `hora_desde`, `hora_hasta` | Timestamps `"fecha hora:00"`. Para "todo el día", la hora sale del turno real (§5.2). |
| `todo_dia` | `'SI'` / `'NO'`. |
| `tipo_horario` | `ENTRADA` / `ENTRE JORNADA` / `SALIDA` (null permitido si `todo_dia = 'SI'`). |
| `descontable` | `'SI'` / `'NO'` — copiado de `razon.descontable` al crear (`=== 'SI' ? 'SI' : 'NO'`). |
| `estado_permiso` | `PENDIENTE` → `APROBADO` / `NEGADO` / `ELIMINADO` / `ANULADO`. |
| `concepto` | Texto libre, default `'PERMISO'`. |
| `observaciones` | Motivo del empleado (≤250). |
| `observacion_negacion` | Motivo de negación / eliminación / anulación (según el caso). |
| `usuario` | Se sobrescribe con quien realiza la última acción (crea = empleado; aprueba/niega/anula = actor). **También** es la FK de la relación `aprobador()`. |
| `aprobado_en` | Timestamp de aprobación. |
| `dias_descuento_efectivo` | DECIMAL(10,4) NULL (mig. `000094`) — días realmente sumados a `total_dias_tomados` al aprobar (incluye el "exceso invisible" sobre 60). Lo usa `anular()` para revertir exacto. |
| `terminal` | IP del solicitante. |
| `fecha_hora` | `now()` al crear. |
| `transmitio`, `origen`, `cargo`, `disminuir_dias`, `secuencial`, `principal` | Legado; se inician con valores fijos (`'NO'`, `'WEB'`, `0`, `0`, `0`, `0`). |

> **✅ RESUELTO (2026-09-01):** el modelo apunta a `secuencial_clave` como PK. Antes las 4 llamadas a
> `AuditoriaService::log()` pasaban `$permiso->id` (atributo inexistente → `registro_id = NULL` en
> cada log) y usaban `$permiso->nombre_emp` (columna que existe en `d2_vacacion` pero **no** en
> `d2_permiso` → descripción "Aprobación de permiso: " sin nombre). Corregido a `$permiso->getKey()`
> + helper `nombreEmpleadoPermiso()` que arma el nombre vía la relación `empleado()`.

### 4.2 `dbo.d2_razon` — catálogo de motivos

| Campo | Contenido |
|---|---|
| `secuencial` PK | — |
| `descripcion` | Nombre del motivo (se copia a `d2_permiso.razon`). |
| `descontable` | `'SI'` = descuenta vacaciones / `'NO'` = licencia. |
| `tipo_razon`, `nomina`, `nomenclatura`, `leyenda_justificacion` | Metadatos. |
| `estado` | `ACTIVO` / (inactivo). Solo se listan los `ACTIVO`. |
| `created_*` / `updated_*` | Auditoría. |

### 4.3 `dbo.d2_permiso_documento` — respaldos (licencias)

| Campo | Contenido |
|---|---|
| `id` PK, `permiso_id` | — |
| `tipo_doc` | Etiqueta libre (≤60). |
| `nombre_archivo` | Nombre final en Alfresco (`entry.name`). |
| `alfresco_id` | Node ID. |
| `created_by`, `created_at` | — |

Carpeta Alfresco: `permisos/{año}/{id_emp}_{APELLIDO}` (vía `relativePath`, `autoRename`).
Nombre base: `permiso_{id}_{tipo_doc}.{ext}`.

### 4.4 Tablas que este módulo **escribe** (además de `d2_permiso`)

| Tabla | Cuándo | Qué |
|---|---|---|
| `dbo.d2_cabecera_vacacion` | Aprobar / anular permiso **descontable** | `total_dias_tomados` ± `efectivo`; `dias_x_tomar_normal` ∓ `diasDescuento`. |
| `dbo.nom_auditoria_log` | Aprobar / negar / eliminar / anular | vía `AuditoriaService::log`. |

> **`d2_cuadre_marcacion` — 2026-09-01:** `aprobar()` y `anular()` **ya no escriben** en esta tabla.
> El aporte de un permiso al descuento del día lo calcula `ProcesarCuadre` desde cero en cada corrida
> (spec 03 §7.3, pasos 9–11). Antes `aprobar()` hacía un `UPDATE ... += N` que —para un permiso a
> futuro, el caso normal— no-opeaba en silencio porque la fila del cuadre aún no existía, y luego el
> cuadre la creaba sin rastro del permiso.

---

## 5. Reglas de negocio

### 5.1 Listado (`GET /api/permisos` — `index`)

- **RN-04.1** — Visibilidad por rol y parámetro `vista` (`"mia"` / `"equipo"` / `""`):
  - Admin/TH: `""` o `"equipo"` → todos; `"mia"` → solo los propios.
  - Supervisor: `"mia"` → propios; `"equipo"` → `empleadosDeSupervisor()`; `""` → propios **+** equipo.
  - Empleado sin rol: siempre solo los propios.
- **RN-04.2** — Filtros: `estado` (`estado_permiso`), `fecha_desde` (`fecha_desde >= X`),
  `fecha_hasta` (`fecha_hasta <= X`), `descontable`.
- **RN-04.3 (2026-09-01)** — Si `formato` (`excel`|`pdf`) → devuelve archivo (§8.3). El export
  **exige `fecha_desde` y `fecha_hasta`** y tiene un tope de **5000 filas** (antes `->get()` sin
  ningún límite podía traer el historial completo de la institución). Si no hay `formato` → paginado
  (`per_page`, default 15), ordenado por `fecha_hora` desc.
- **RN-04.4 (aviso `sin_atraso`)** — Por cada permiso de la página se calcula el flag booleano
  `sin_atraso`: `true` sii `descontable = 'SI'` **y** `tipo_horario ∈ {ENTRADA, SALIDA}` **y**
  `fecha_desde <= hoy` **y** existe cuadre de ese día **y** el atraso correspondiente
  (`atraso_entrada` para ENTRADA, `atraso_salida` para SALIDA) es `0`. Es **informativo**, no
  bloquea. Si la fecha es futura o el cuadre aún no procesó → `sin_atraso = false`. En la UI:
  badge ámbar "⚠ Sin atraso" visible **solo para el supervisor en el tab "equipo"**, junto a "Aprobar".

### 5.2 Solicitud (`POST /api/permisos` — `store`)

Request: `sec_permiso*` (int), `fecha_desde*`, `fecha_hasta*`, `hora_desde*`, `hora_hasta*`,
`todo_dia`, `observaciones` (≤250), `concepto`, `tipo_horario` (`required|in:ENTRADA,ENTRE JORNADA,SALIDA`
salvo que `todo_dia = 'SI'`, donde es `nullable`).

- **RN-04.5** — El solicitante es siempre `$request->user()` (no se puede pedir por otro).
- **RN-04.6** — `id_depto = 999` → HTTP 403 "El usuario administrador no puede solicitar permisos".
- **RN-04.7** — `estado != 'ACTIVO'` → HTTP 403 "Solo empleados activos pueden solicitar permisos".
- **RN-04.8** — La razón (`Razon::findOrFail(sec_permiso)`) define `descontable` del permiso.
- **RN-04.9 (validación de solapamiento)** — Se bloquea (HTTP 422) si existe otro permiso del
  **mismo `tipo_horario`**, en estado **no** `NEGADO`/`ELIMINADO`/`ANULADO`, cuyo rango de fechas se
  cruce. Permisos de **distinto** `tipo_horario` (p. ej. ENTRADA y SALIDA) **pueden coexistir** el
  mismo día.
  - Si el nuevo permiso **no** es "todo el día": además solo bloquea si hay **cruce de horas**
    (`hora_desde < horaHastaNuevo AND hora_hasta > horaDesdeNuevo`) o si el permiso existente es
    "todo el día".
- **RN-04.10 (horas de "todo el día")** — Si `todo_dia = 'SI'`: `hora_desde` = ENTRADA del turno
  real del primer día, `hora_hasta` = SALIDA del turno real del último día
  (`horaTurnoDelDia()` → `d2_programacion` col `s{día}` → `d2_turno`). Si el turno no tiene ese
  concepto configurado → cae al valor del formulario.
- **RN-04.11** — Se crea con `estado_permiso = 'PENDIENTE'`, `todo_dia` default `'NO'`,
  `concepto` default `'PERMISO'`. Respuesta HTTP 201.
- **RN-04.12 (2026-09-01)** — `store` registra `SOLICITAR` en `nom_auditoria_log` al final (antes
  la creación de la solicitud era la única acción del flujo sin auditoría).

### 5.3 Aprobación (`PATCH /api/permisos/{id}/aprobar` — `aprobar`)

- **RN-04.13** — No se puede aprobar el permiso propio → 403.
- **RN-04.14** — Autorización: Admin/TH aprueban cualquiera; supervisor solo si el `id_emp` del
  permiso está en `empleadosDeSupervisor()` → 403 si no.
- **RN-04.15** — El permiso debe estar `PENDIENTE` → 422 si no.
- **RN-04.16** — `estado_permiso = 'APROBADO'`, `usuario = actor.id_emp`, `aprobado_en = now()`.
- **RN-04.17 (cálculo de días a descontar)** — `horasJornada` = `empleado.jornada.normal` o `8.0`.
  **Factor fin de semana** `factorFds = 30/22 = 1.3636…`:
  - `todo_dia = 'SI'` → `diasBase = diffInDays(fecha_desde, fecha_hasta) + 1`;
    `diasDescuento = round(diasBase × factorFds, 4)`.
  - por horas → `horas = diffInMinutes(hora_desde, hora_hasta)/60`;
    `diasDescuento = round(horas / horasJornada × factorFds, 4)`.
  - Ejemplos: 1 h → 0.1705 · 4 h → 0.6818 · 1 día completo → 1.3636.
- **RN-04.18 (descuento de vacaciones — solo si `descontable = 'SI'`)** — Sobre
  `d2_cabecera_vacacion` del empleado:
  - `internoSaldo` = saldo **sin** tope de 60, vía `SaldoVacacionesService::calcularInterno()`
    (2026-09-01; antes `PermisosController::calcularInternoVac()`, un cuarto duplicado de la fórmula).
  - `exceso = max(0, internoSaldo − 60)`.
  - `efectivo = round(exceso + diasDescuento, 4)`.
  - `dias_x_tomar_normal = max(0, dias_x_tomar_normal − diasDescuento)`.
  - `total_dias_tomados = round(total_dias_tomados + efectivo, 4)`.
  - Se guarda `dias_descuento_efectivo = efectivo` en el permiso.
  - **Racional:** el saldo visible al empleado tiene tope 60 (LOSEP Art. 29). Si internamente tiene
    70, el permiso primero "consume" ese exceso invisible (10) y luego el descuento real, para que
    el saldo visible baje de forma coherente.
- **RN-04.19 (ajuste del cuadre) — 2026-09-01: `aprobar()` ya NO toca `d2_cuadre_marcacion`.** El
  aporte del permiso al descuento del día (`horas_decto` si descontable, `horaspermiso_pag` si no) lo
  calcula `ProcesarCuadre` desde cero en cada corrida (spec 03 §7.3, pasos 9–11), usando el mismo
  factor 30/22 y contando solo los días que caen en el rango real del permiso. Esto elimina el
  `UPDATE ... += N` sobre una fila que a futuro no existía (no-op silencioso) y el bucle
  `for ($i=0; $i<$diasDescuento; ...)` con contador fraccionario (que en "todo el día" tocaba un día
  de más con un `+1` fantasma).
- **RN-04.20** — Auditoría `APROBAR` (anterior `PENDIENTE` → nuevo `APROBADO` + fechas + descontable).
- **RN-04.21 (permisos descontables NO se bloquean por saldo negativo)** — se aprueban aunque el
  saldo quede en rojo (el descuento sigue acumulando sobre `total_dias_tomados`).

### 5.4 Negación (`PATCH /api/permisos/{id}/negar` — `negar`)

- **RN-04.22** — `observacion_negacion` opcional (≤120). No sobre permiso propio (403). Autorización
  supervisor/Admin-TH. Debe estar `PENDIENTE` (422).
- **RN-04.23** — `estado_permiso = 'NEGADO'`, `usuario = actor`, guarda la observación. Auditoría `NEGAR`.
- No toca vacaciones ni cuadre (el permiso nunca llegó a aprobarse).

### 5.5 Eliminación (`DELETE /api/permisos/{id}` — `destroy`)

- **RN-04.24** — `observacion_negacion` **requerido** (≤120). Mismas reglas de autorización y estado
  `PENDIENTE` que negar.
- **RN-04.25** — `estado_permiso = 'ELIMINADO'` (soft — la fila permanece). Auditoría `ELIMINAR`.

### 5.6 Anulación (`PATCH /api/permisos/{id}/anular` — `anular`)

- **RN-04.26** — Solo `ADMINISTRADOR` / `TALENTO HUMANO` (403 si no). `observacion_negacion` **requerido** (≤120).
- **RN-04.27** — El permiso debe estar `APROBADO` (422 si no).
- **RN-04.28 (reversa de vacaciones — si `descontable = 'SI'`)** — recalcula `diasDescuento` con el
  mismo factor. `efectivoRevertir` = `dias_descuento_efectivo` guardado (o `diasDescuento` si es
  null — compatibilidad con permisos anteriores a mig. `000094`).
  - `dias_x_tomar_normal += diasDescuento`.
  - `total_dias_tomados = max(0, total_dias_tomados − efectivoRevertir)`.
- **RN-04.29 (reversa del cuadre) — 2026-09-01: `anular()` ya NO toca `d2_cuadre_marcacion`.** Al
  pasar el permiso a `ANULADO` deja de contar automáticamente: el próximo
  `php artisan procesar:cuadre --fecha=X` de cada fecha del permiso recalcula `horas_decto` /
  `horaspermiso_pag` sin él. Esto elimina la asimetría de factor que antes dejaba un residuo
  permanente (la reversa restaba `horas/horasJornada` **sin** factor mientras la aprobación había
  sumado **con** factor).
- **RN-04.30** — `estado_permiso = 'ANULADO'`, guarda observación y `usuario = actor`. Auditoría `ANULAR`.
- **Uso:** permiso aprobado que el empleado no utilizó (p. ej. salió a su hora normal).

### 5.7 Documentos de respaldo

- **RN-04.30b (control de acceso — 2026-09-01)** — dos helpers nuevos, aplicados a los endpoints de
  lectura y de documentos (antes ninguno tenía chequeo de propiedad ni rol; cualquier autenticado
  accedía incrementando el `{id}`/`{docId}`):
  - `puedeVerPermiso()` = dueño **o** su supervisor **o** TH/Admin → `show()`, `listarDocumentos()`, `descargarDocumento()`.
  - `puedeEditarDocumentosPermiso()` = dueño **o** TH/Admin (**sin** supervisor) → `subirDocumento()`, `eliminarDocumento()`.
- **RN-04.31** — `subirDocumento`: requiere `puedeEditarDocumentosPermiso()`. `archivo`
  `mimes:pdf,jpg,jpeg,png|max:20480` (KB), `tipo_doc` requerido. El permiso debe estar `PENDIENTE`
  (422). Sube a Alfresco `permisos/{año}/{cedula_APELLIDO}`; registra en `d2_permiso_documento`. 502
  si Alfresco falla. HTTP 201.
- **RN-04.32** — `eliminarDocumento`: requiere `puedeEditarDocumentosPermiso()`. Solo si el permiso
  está `PENDIENTE` (422). Borra el nodo en Alfresco y la fila. 404 si el documento no existe / no
  pertenece al permiso.
- **RN-04.33** — `descargarDocumento`: requiere `puedeVerPermiso()`. Stream del contenido desde
  Alfresco con MIME según extensión (`image/*` o `application/pdf`), `Content-Disposition: inline`.
  403 / 404 / 502.
- **RN-04.34** — En la UI, la sección "Documentos de respaldo" aparece **solo** para permisos con
  `descontable = 'NO'` (licencias). Descarga vía blob URL (`window.open` + revoke a los 60 s).

### 5.8 Semántica de `tipo_horario` (cómo justifica en el cuadre — spec 03 §7.3)

| `tipo_horario` | Qué justifica |
|---|---|
| `ENTRADA` | Llegada tarde. El cuadre compara la hora real con `hora_hasta` del permiso (`entradaJustificada`). |
| `SALIDA` | Salida anticipada. El cuadre compara con `hora_desde` del permiso (`salidaJustificada`). |
| `ENTRE JORNADA` | Atraso en el retorno del almuerzo. **No** ajusta `atraso_entrada` / `atraso_salida` en `ProcesarCuadre`. |
| (`todo_dia = 'SI'`) | Todo el día: `entradaJustificada = 99.0`, `salidaJustificada = 0.0`. |

> **Advertencia conocida:** un permiso `tipo_horario = 'ENTRADA'` con horas amplias (p. ej.
> 10:00–16:30) puede hacer que el cuadre y la vista personal marquen el día como "Justificado" para
> la entrada aunque el rango no tenga sentido semántico. Verificar `tipo_horario` al crear.

### 5.9 Dos descuentos distintos, dos momentos distintos

- **RN-04.35a — Saldo de vacaciones (`d2_cabecera_vacacion`):** se descuenta **al aprobar**
  (`aprobar()`), calculado sobre las **horas del permiso** (`hora_desde`/`hora_hasta`) × factor
  30/22. Se revierte **al anular** (`anular()`) usando `dias_descuento_efectivo`.
- **RN-04.35b — Descuento del día en el cuadre (`d2_cuadre_marcacion.horas_decto` /
  `horaspermiso_pag`):** lo calcula **`ProcesarCuadre` desde cero en cada corrida** (2026-09-01), no
  los métodos del controlador. Idempotente: el orden aprobación-vs-cuadre no importa y una anulación
  se refleja sola en el siguiente reproceso.

### 5.10 Otros endpoints

- **RN-04.36** — `GET /api/permisos/razones` → razones `ACTIVO` ordenadas por descripción.
- **RN-04.37** — `GET /api/permisos/mi-rol` → `{ es_supervisor, es_admin_th }`.
- **RN-04.38** — `GET /api/permisos/{id}` → permiso con empleado + departamento + razón. Requiere
  `puedeVerPermiso()` (dueño / supervisor / TH-Admin) desde 2026-09-01.
- **RN-04.39** — `GET /api/permisos/estadistica` (`fecha_desde`/`fecha_hasta` requeridas) — agrupa
  por supervisor (`p.usuario`) los permisos `APROBADO`/`NEGADO`/`ELIMINADO` del rango, excluyendo
  `terminal = '0.0.0.0'` y `observaciones = 'MIGRACION'` (datos migrados). Supervisor ve solo su
  equipo; Admin/TH ven todos. Respuesta: `[{ id_supervisor, nombre_supervisor, aprobados, negados,
  eliminados, total }]`.

---

## 6. API

| Método | Ruta | Función | Autorización |
|---|---|---|---|
| GET | `/permisos/razones` | `razones` | autenticado |
| GET | `/permisos/estadistica` | `estadistica` | supervisor (su equipo) / Admin-TH (todos) |
| GET | `/permisos/mi-rol` | `miRol` | autenticado |
| GET | `/permisos` | `index` (paginado / export) | según rol y `vista` |
| POST | `/permisos` | `store` → 201 | empleado ACTIVO, no depto 999 |
| GET | `/permisos/{id}` | `show` | `puedeVerPermiso()`: dueño / supervisor / Admin-TH ✅ 2026-09-01 |
| PATCH | `/permisos/{id}/aprobar` | `aprobar` | supervisor del empleado / Admin-TH |
| PATCH | `/permisos/{id}/negar` | `negar` | supervisor del empleado / Admin-TH |
| PATCH | `/permisos/{id}/anular` | `anular` | **solo Admin-TH** |
| DELETE | `/permisos/{id}` | `destroy` (soft → ELIMINADO) | supervisor del empleado / Admin-TH |
| GET | `/permisos/{id}/documentos` | `listarDocumentos` | `puedeVerPermiso()` ✅ 2026-09-01 |
| POST | `/permisos/{id}/documentos` | `subirDocumento` → 201 | `puedeEditarDocumentosPermiso()` (dueño / Admin-TH), permiso PENDIENTE ✅ 2026-09-01 |
| GET | `/permisos/{id}/documentos/{docId}/descargar` | `descargarDocumento` | `puedeVerPermiso()` ✅ 2026-09-01 |
| DELETE | `/permisos/{id}/documentos/{docId}` | `eliminarDocumento` | `puedeEditarDocumentosPermiso()`, permiso PENDIENTE ✅ 2026-09-01 |

`index` con `?formato=excel|pdf` → descarga.

---

## 7. UI

`PermisosView.vue` (ruta `permisos`). Tabs: "Mis Permisos" (`vista=mia`), "Equipo" (`vista=equipo`,
supervisor/Admin-TH), y filtros comunes.

- **Modal "Solicitar Permiso":** `fecha_desde`/`fecha_hasta` default = hoy al abrir. Selector de
  razón (`/permisos/razones`), `tipo_horario`, horas (`TimePicker24`), observaciones. Sin checkbox
  visible de "todo el día" documentado aquí (ver componente).
- **Documentos de respaldo:** solo para `descontable = 'NO'` y `estado = 'PENDIENTE'` — subir / ver
  / eliminar.
- **Estado `ANULADO`:** badge naranja; opción "ANULADO" en el filtro de estados.
- **Botón "Anular":** visible para TH/Admin en permisos `APROBADO`; modal con motivo obligatorio.
- **Aviso "⚠ Sin atraso":** badge ámbar junto a "Aprobar", solo supervisor + tab equipo (RN-04.4).
- **Vista personal de asistencia** (spec 03 §9): muestra `atraso` (minutos) + `justificado`
  (`TOTAL` verde / `PARCIAL` naranja / `NO` rojo). "14 min Justificado" = llegó 14 min tarde pero
  un permiso lo cubre; **no** indica minutos pendientes.

---

## 8. Criterios de aceptación

**Solicitud**
- **CA-04-1** — Empleado `INACTIVO` o de depto 999 → `POST /permisos` responde 403.
- **CA-04-2** — Solicitar un permiso `ENTRADA` 08:00–09:00 el 10/09 cuando ya existe uno `ENTRADA` 08:30–09:30 el 10/09 → 422 (mismo tipo, cruce de horas).
- **CA-04-3** — Solicitar un permiso `SALIDA` el mismo 10/09 con un permiso `ENTRADA` existente ese día → **se crea** (distinto `tipo_horario`).
- **CA-04-4** — Solicitar "todo el día" para un empleado cuyo turno de ese día es 07:30–16:30 → el permiso queda con `hora_desde` 07:30 y `hora_hasta` 16:30 (no 08:00–17:00).

**Aprobación / descuento**
- **CA-04-5** — Aprobar el permiso propio → 403.
- **CA-04-6** — Un supervisor aprueba el permiso de un empleado que **no** supervisa → 403.
- **CA-04-7** — Aprobar un permiso descontable de 4 h para un empleado con jornada de 8 h → `total_dias_tomados` sube 0.6818 (4/8 × 1.3636) y `dias_descuento_efectivo = 0.6818` (si el saldo interno ≤ 60).
- **CA-04-8** — Aprobar un permiso descontable de 4 h para un empleado con saldo interno 70 → `efectivo = 10 + 0.6818 = 10.6818` sumado a `total_dias_tomados`; `dias_descuento_efectivo = 10.6818`.
- **CA-04-9** — Aprobar un permiso **no** descontable → `d2_cabecera_vacacion` no cambia; `d2_cuadre_marcacion.horaspermiso_pag` del día sube.
- **CA-04-10** — Tras aprobar, el cuadre del día refleja el permiso como justificación (spec 03 CA-03-15/16).
- **CA-04-11** — Aprobar un permiso ya `APROBADO`/`NEGADO` → 422.
- **CA-04-12** — Toda solicitud (`SOLICITAR`, 2026-09-01) / aprobación / negación / eliminación / anulación genera exactamente un registro en `nom_auditoria_log`.

**Anulación / reversa**
- **CA-04-13** — Anular un permiso descontable de 4 h (aprobado con `dias_descuento_efectivo = 0.6818`) → `total_dias_tomados` baja exactamente 0.6818 y `dias_x_tomar_normal` sube 0.6818.
- **CA-04-14** — Anular un permiso anterior a la mig. `000094` (`dias_descuento_efectivo = null`) → la reversa usa `diasDescuento` recalculado.
- **CA-04-15** — Anular un permiso que no está `APROBADO` → 422.
- **CA-04-16** — Un supervisor (no TH) intenta `PATCH /permisos/{id}/anular` → 403.
- **CA-04-17** — `anular` sin `observacion_negacion` → 422.

**Documentos**
- **CA-04-18** — Subir un documento a un permiso `APROBADO` → 422.
- **CA-04-19** — Subir un PDF a un permiso `PENDIENTE` → 201 y aparece en `listarDocumentos`; la carpeta Alfresco es `permisos/{año}/{cedula_APELLIDO}`.
- **CA-04-19b (2026-09-01)** — Un empleado sin relación con el permiso llama `GET /permisos/{id}` o `.../documentos/{docId}/descargar` de un permiso ajeno → 403 (`puedeVerPermiso()`). El supervisor del dueño llama `subirDocumento` → 403 (`puedeEditarDocumentosPermiso()` no incluye supervisor).

**Estadística / export**
- **CA-04-20** — `estadistica` no cuenta permisos con `observaciones = 'MIGRACION'` ni `terminal = '0.0.0.0'`.
- **CA-04-21** — `GET /permisos?formato=excel` devuelve un `.xlsx` con el encabezado institucional y las 9 columnas; `?formato=pdf` devuelve un PDF landscape A4.

---

## 9. Casos borde

| Caso | Comportamiento |
|---|---|
| Empleado sin `jornada` asociada | `horasJornada = 8.0` por defecto en el cálculo del descuento de vacaciones. |
| Permiso "todo el día" de varios días (descuento de vacaciones) | `diasBase` incluye fines de semana del rango (`diffInDays + 1`), luego × 1.3636 — puede descontar de más si el rango cruza sábados/domingos. *(abierto)* |
| Cuadre corre después de aprobar un permiso descontable | Sin problema desde 2026-09-01: `ProcesarCuadre` recalcula `horas_decto`/`horaspermiso_pag` desde cero incluyendo el aporte del permiso; el orden no importa. |
| `sec_permiso` inexistente | `Razon::findOrFail` → 404. |
| `show` / documentos de un permiso ajeno | 403 desde 2026-09-01 (`puedeVerPermiso()`). |
| Permiso migrado (`observaciones = 'MIGRACION'`, `terminal = '0.0.0.0'`) | Se excluye de `estadistica` pero **sí** aparece en `index`. |
| `SaldoVacacionesService::calcularInterno()` con `tipo_contrato` distinto de LOSEP / CODIGO DEL TRABAJO | `tasaMensual = 0` → saldo interno = `dias_adicionales − total_dias_tomados` (sin devengo). |

---

## 10. Deuda técnica / hallazgos

> **Los 5 hallazgos originales de esta spec se corrigieron el 2026-09-01** (ver CLAUDE.md
> §"Corrección de deuda técnica — Permisos (2026-09-01)"). Ninguno tocó schema, `config/*` ni `.env`.

1. ✅ **RESUELTO (2026-09-01)** — Fuga de acceso en `show()` / `listarDocumentos()` /
   `descargarDocumento()` / `subirDocumento()` / `eliminarDocumento()`: helpers `puedeVerPermiso()`
   (dueño / supervisor / TH-Admin) y `puedeEditarDocumentosPermiso()` (dueño / TH-Admin) aplicados.
2. ✅ **RESUELTO (2026-09-01)** — `subirDocumento` / `eliminarDocumento` ahora exigen
   `puedeEditarDocumentosPermiso()` además del estado `PENDIENTE`.
3. ✅ **RESUELTO (2026-09-01)** — Bucle `for ($i=0; $i<$diasDescuento; ...)` con contador fraccionario
   en `aprobar()` para "todo el día": eliminado. `aprobar()` ya no toca `d2_cuadre_marcacion`;
   `ProcesarCuadre` suma `1` solo cuando la fecha procesada cae en el rango real del permiso.
4. ✅ **RESUELTO (2026-09-01)** — `ProcesarCuadre` vs. `aprobar()`: `aprobar()`/`anular()` ya **no**
   escriben en `d2_cuadre_marcacion`. `ProcesarCuadre` es la única fuente de verdad de `horas_decto`
   / `horaspermiso_pag` y los recalcula desde cero cada corrida (idempotente).
5. ✅ **RESUELTO (2026-09-01)** — Asimetría del factor 30/22 entre `aprobar()` y `anular()` en el
   cuadre: ya no aplica (no hay resta manual que pueda desalinearse de la suma).
6. ✅ **RESUELTO (2026-09-01)** — `AuditoriaService::log($permiso->id, ...)`: corregido a
   `$permiso->getKey()` en `aprobar`/`negar`/`eliminar`/`anular` (antes `registro_id = NULL`).
7. ✅ **RESUELTO (2026-09-01)** — `$permiso->nombre_emp` (columna inexistente): helper
   `nombreEmpleadoPermiso()` que arma el nombre vía la relación `empleado()`.
8. ✅ **RESUELTO (2026-09-01)** — `store` registra `SOLICITAR` en `nom_auditoria_log`.
9. ✅ **RESUELTO (2026-09-01)** — Export de `index` con `?formato=`: exige `fecha_desde`/`fecha_hasta`
   y tope de 5000 filas.
10. **Patrón de autorización mixto** — helpers ad-hoc (`esAdminOTH`, `empleadosDeSupervisor`,
    `puedeVerPermiso`, …) en vez de `requireRole` / policy unificada. Evaluado y **dejado sin tocar
    a propósito** (funcionalmente equivalente, refactor de mayor superficie).

---

## 11. Dependencias

- **Spec 00** — roles, `supervisor_area`, Alfresco, auditoría.
- **Spec 01** — `id_emp`, `estado`, `id_depto`, `jornada`.
- **Spec 03** — `d2_cuadre_marcacion` (campos `atraso_*`, `horas_decto`, `horaspermiso_pag`),
  `d2_programacion` / `d2_turno` (turno real para "todo el día"), y la lógica de `ProcesarCuadre`
  que consume permisos `APROBADO`.
- **Spec 05 (Vacaciones)** — `d2_cabecera_vacacion` (`dias_adicionales`, `total_dias_tomados`,
  `dias_x_tomar_normal`); el saldo interno **sin** tope lo da `SaldoVacacionesService::calcularInterno()`
  (fuente única desde 2026-09-01 — antes `PermisosController::calcularInternoVac()`); el tope de 60
  días (LOSEP Art. 29); `FECHA_CORTE_VACACIONES`.
- **Spec 12 (Auditoría)** — `AuditoriaService`.
- `Admin/RazonController` — administración del catálogo de razones (fuera de esta spec).

---

## 12. Preguntas abiertas

- ~~¿Cerrar `show` / documentos?~~ ✅ hecho 2026-09-01 (§10.1–2).
- ~~¿Arreglar el bucle fraccionario / la fuente de verdad de `horas_decto` / la asimetría del factor?~~ ✅ hecho 2026-09-01 (§10.3–5) — `ProcesarCuadre` es ahora la única fuente de verdad.
- ~~¿`store` debe auditar `SOLICITAR`? / ¿límite al export?~~ ✅ hechos 2026-09-01 (§10.8–9).
- ¿Se debe validar que el rango de un permiso "todo el día" excluya fines de semana antes de aplicar el factor? (§9)
- Patrón de autorización mixto: dejado sin tocar a propósito (§10.10).
