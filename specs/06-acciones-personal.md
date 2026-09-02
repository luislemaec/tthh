# Spec 06 — Acciones de Personal

| | |
|---|---|
| **Versión** | 1.0 (borrador) |
| **Última actualización** | 2026-09-02 |
| **Depende de** | [00 — Módulo Talento Humano](00-modulo-talento-humano.md), [01 — Empleados](01-empleados.md) |
| **Controlador** | `app/Http/Controllers/AccionPersonalController.php` |
| **Modelo** | `App\Models\AccionPersonal` (`dbo.acc_accion_personal`, PK `id_accion`) |
| **Vistas** | `views/acciones/AccionesPersonalView.vue`, `AccionPersonalForm.vue`, `HistorialRemuneracionesView.vue` |
| **PDF** | `reportes/accion_personal.blade.php` (individual), `reportes/acc_lista.blade.php` (listado), `reportes/acc_historial_remuneraciones.blade.php` |
| **Tipo** | Retro-especificación |

---

## 1. Propósito

Emitir el documento oficial de **acción de personal** (formato del Ministerio del Trabajo) para
cada movimiento de la carrera de un servidor: ingreso, encargo, subrogación, vacaciones,
destitución, cesación de funciones, comisión de servicios y reingreso. Cada acción se elabora como
**borrador**, se genera en PDF para revisión, y al **procesarla** se le asigna un número oficial
correlativo y queda `ACTIVO`. El PDF firmado se archiva en Alfresco.

### 1.1 Alcance

Incluye: creación en borrador, edición del borrador, procesamiento (numeración), cambio de estado,
auto-cierre de acciones temporales vencidas, generación del PDF individual (borrador y definitivo),
reportes (listado PDF/Excel, historial de cargos y remuneraciones), subida/descarga del PDF firmado,
y el helper de autocompletado de la "situación actual".

**No** cubre:
- El **cambio de estado del empleado** en `ad_empleado` (pasar a `INACTIVO` por comisión/destitución,
  reactivar por reingreso). Eso lo hace TH **manualmente en la ficha** (spec 01) — este controlador
  **nunca escribe en `ad_empleado`** (ver §5.8 y §11).
- La liquidación de vacaciones por comisión/desvinculación → **spec 07**.

---

## 2. Actores

| Actor | Puede |
|---|---|
| `ADMINISTRADOR`, `TALENTO HUMANO`, `TH ACCIONES PERSONAL` | Todo: crear, editar borrador, procesar, cambiar estado, generar PDF, reportes, subir/descargar firmado. |

**Todos** los endpoints del controlador llaman `requireRole($request, self::ROLES_ADMIN)` con
`ROLES_ADMIN = ['ADMINISTRADOR', 'TALENTO HUMANO', 'TH ACCIONES PERSONAL']`. No hay endpoints
abiertos ni acceso para el empleado.

---

## 3. Historias de usuario

- **HU-06.1** — Como TH Acciones, quiero crear el borrador de una acción de personal eligiendo el tipo y el empleado, y que el sistema me autocomplete la situación actual del servidor.
- **HU-06.2** — Como TH Acciones, quiero revisar el PDF del borrador (con banda "NO VÁLIDO") antes de oficializarlo.
- **HU-06.3** — Como TH Acciones, quiero corregir la motivación, la fecha de elaboración, los firmantes, el medio y la especificación mientras la acción está en borrador.
- **HU-06.4** — Como TH Acciones, quiero procesar el borrador para que reciba su número oficial (`DATH-2026-00042`) y quede activo, con el PDF ya sin la banda de borrador.
- **HU-06.5** — Como TH Acciones, quiero que las subrogaciones y vacaciones se cierren solas cuando vence su fecha fin.
- **HU-06.6** — Como TH Acciones, quiero subir el PDF escaneado y firmado y archivarlo en Alfresco.
- **HU-06.7** — Como TH, quiero un reporte del histórico de cargos y remuneraciones de un empleado con la diferencia contra su sueldo actual.

---

## 4. Modelo de datos — `dbo.acc_accion_personal`

PK `id_accion`. `fecha_*` casteadas a `date`; `actual_remuneracion` / `propuesto_remuneracion` /
`diferencial` a `decimal:2`.

| Campo | Contenido |
|---|---|
| `tipo_accion` | Uno de los 8 (§5.1). |
| `numero_accion` | `null` en borrador; se asigna al procesar (`{PREFIJO}-{año}-{NNNNN}`). |
| `estado` | `BORRADOR` → `ACTIVO` → `FINALIZADO` / `ANULADO`. |
| `fecha_elaboracion` | Fecha del documento (editable en borrador). |
| `id_emp` | Servidor de la acción. |
| `id_emp_titular` | Titular del puesto (para ENCARGO/SUBROGACION); opcional. Relación `titular()`. |
| `fecha_inicio`, `fecha_fin` | Vigencia. `fecha_fin` requerida para SUBROGACION, VACACIONES, COMISION DE SERVICIOS. |
| `motivacion` | Texto o **HTML de TipTap** (el blade detecta `<p>`/`<strong>`/`<em>` y renderiza con `{!! !!}`). |
| `actual_*` (`cargo`, `grupo_ocup`, `grado`, `remuneracion`, `partida`, `proceso_inst`) | Situación actual del servidor. `null` / `0` para INGRESO y REINGRESO. Autocompletados desde el empleado si no vienen en el request. |
| `propuesto_*` (mismos 6) | Situación propuesta. Obligatorios (`cargo`, `remuneracion`) salvo para los tipos "sin propuesta" (§5.2). |
| `diferencial` | `max(0, propuesto_remuneracion − actual_remuneracion)`. |
| `medio` | `DIGITAL` / `MANUAL` (default `DIGITAL`). |
| `especificacion` | VARCHAR(300), MAYÚSCULAS. Solo se usa para COMISION DE SERVICIOS / REINGRESO / CESACION DE FUNCIONES en el PDF (mig. `000097`). |
| `firmante_th_nombre` / `_th_cargo` / `_autoridad_nombre` / `_autoridad_cargo` | VARCHAR(200) (mig. `000087`). Firmantes **de esta acción** (ver §5.6). |
| `creado_por` | `id_emp` del usuario que creó el borrador. |
| `pdf_firmado` | Node ID de Alfresco del PDF firmado. |
| `created_at`, `updated_at` | Timestamps (base para la numeración anual y el orden del listado). |

### 4.1b Configuración adicional

| Concepto | Uso |
|---|---|
| `ULTIMO_CIERRE_ACCIONES` | Timestamp de la última corrida de `cerrar:acciones-vencidas` (para detectar si `schedule:run` dejó de correr). No editar a mano. |

> **Auditoría (2026-09-03):** `AccionPersonalController` está ahora en la tabla de controladores
> instrumentados. `store` → `CREAR`, `procesar` → `PROCESAR`, `editarBorrador` → `EDITAR_BORRADOR`,
> `cambiarEstado` → `CAMBIAR_ESTADO`, `subirFirmado` → `SUBIR_FIRMADO` (todos con
> `AuditoriaService::log(..., $accion->getKey(), ...)`). El auto-cierre (comando CLI) inserta
> `AUTO_CERRAR` directo en `nom_auditoria_log` (`usuario_id = 'SISTEMA'`) porque `AuditoriaService`
> exige un `Request` no nulo — mismo criterio que `LOGIN`/`LOGOUT`.

### 4.0b Comando programado — `cerrar:acciones-vencidas` (2026-09-03)

`php artisan cerrar:acciones-vencidas` (`app/Console/Commands/CerrarAccionesVencidas.php`).
Programado en `routes/console.php` a las **06:00** (`Schedule::command(...)->dailyAt('06:00')`).
Cierra las acciones `SUBROGACION` / `VACACIONES` / **`COMISION DE SERVICIOS`** en `estado = 'ACTIVO'`
con `fecha_fin < hoy` → `FINALIZADO` + `AUTO_CERRAR`. Guarda `ULTIMO_CIERRE_ACCIONES` (timestamp) en
`d2_configuracion` para el monitoreo de `schedule:run` (mismo criterio que `ULTIMO_CUADRE_PROCESADO`,
spec 03). Antes esto era un efecto secundario de `index()` (un GET) y no cubría COMISION.

### 4.1 Configuración (`dbo.d2_configuracion`)

| Concepto | Uso |
|---|---|
| `PREFIJO_ACCION_PERSONAL` | Prefijo del número (default `DATH`). |
| `FIRMANTE_TH_NOMBRE` / `_TH_CARGO` / `_AUTORIDAD_NOMBRE` / `_AUTORIDAD_CARGO` | Pre-llenado de firmantes al crear y fallback en el PDF. |
| `DIRECTOR_TALENTO_HUMANO` / `APROBADOR_ACCION_PERSONAL` | Fallback histórico de firmantes (2º nivel). |
| `nombre_institucion`, `UBICACION_DEFAULT` | Encabezado / lugar en el PDF. |

---

## 5. Reglas de negocio

### 5.1 Los 8 tipos y su comportamiento

| Tipo | Estado del empleado al crear *(validado 2026-09-03)* | Situación actual | Situación propuesta | `fecha_fin` | Posesión (PDF) | Decl. jurada (PDF) | Especificación | Después *(acción manual de TH)* |
|---|---|---|---|---|---|---|---|---|
| `INGRESO` | ACTIVO | No aplica | Obligatoria; auto-llena del empleado | opcional | Se llena | **SÍ** | No | Sigue ACTIVO |
| `ENCARGO` | ACTIVO | Del empleado | Manual | opcional | Se llena | No | No | Sigue ACTIVO; **NO** se auto-cierra (`fecha_fin` opcional) — cerrar con `cambiarEstado` |
| `SUBROGACION` | ACTIVO | Del empleado | Manual | **requerida** | Se llena | No | No | Sigue ACTIVO; **auto-cierra** al vencer |
| `VACACIONES` | ACTIVO | Del empleado | No aplica | **requerida** | Se llena | No | No | Sigue ACTIVO; **auto-cierra** al vencer |
| `DESTITUCION` | **INACTIVO** *(TH lo desactiva antes)* | Del empleado | No aplica | opcional | Se llena | **SÍ** | No | Ya estaba INACTIVO |
| `CESACION DE FUNCIONES` | ACTIVO | Del empleado | No aplica | opcional | Vacía | **SÍ** | **Sí** | TH decide en la ficha: ACTIVO o INACTIVO |
| `COMISION DE SERVICIOS` | ACTIVO | Del empleado | No aplica | **requerida** | Vacía | No | **Sí** | TH lo pasa a INACTIVO en la ficha; **auto-cierra** al vencer (2026-09-03) |
| `REINGRESO` | ACTIVO *(TH lo reactiva antes)* | No aplica | Auto-llena del empleado | opcional | Vacía | No | **Sí** | Sigue ACTIVO |

- **RN-06.1 (2026-09-03)** — `store()` **valida el estado del empleado** contra el tipo:
  `DESTITUCION` exige `estado = 'INACTIVO'`, todos los demás exigen `'ACTIVO'` → HTTP 422 si no
  calza. Antes era solo convención (el filtro del buscador es del frontend). *(No valida la
  coherencia de secuencia — p. ej. REINGRESO sin COMISION previa — ver §11.9.)*

### 5.2 Booleanos derivados del tipo (idénticos en backend y en el blade)

| Bandera | Fórmula | Usada para |
|---|---|---|
| `conPropuesta` / `showPropuesta` | `tipo ∉ {DESTITUCION, CESACION DE FUNCIONES, VACACIONES, COMISION DE SERVICIOS}` | `propuesto_cargo` / `propuesto_remuneracion` obligatorios; sección "situación propuesta" del PDF |
| `sinActual` / `!showActual` | `tipo ∈ {INGRESO, REINGRESO}` | `actual_*` se guardan `null` / `0`; sección "situación actual" en blanco |
| `fechaFinRequerida` | `tipo ∈ {SUBROGACION, VACACIONES, COMISION DE SERVICIOS}` | `fecha_fin` `required` |
| `fillPosesion` | `tipo ∉ {COMISION DE SERVICIOS, REINGRESO, CESACION DE FUNCIONES}` | Bloque "acta de posesión" del PDF se llena |
| `declaracionSI` | `tipo ∈ {INGRESO, DESTITUCION, CESACION DE FUNCIONES}` | Checkbox "declaración juramentada: SÍ" del PDF |
| `deptPropuestoFinal` | `tipo ∈ {INGRESO, REINGRESO} ? deptActual : deptPropuesto` | Unidad administrativa propuesta en el PDF |

### 5.3 Creación (`POST /api/acciones-personal` — `store`)

- **RN-06.2** — Requiere rol. `tipo_accion` `required|in:` los 8. `fecha_elaboracion`, `id_emp`,
  `fecha_inicio` requeridos. `fecha_fin` `after_or_equal:fecha_inicio` y `required` si
  `fechaFinRequerida`. `propuesto_cargo` (≤200) y `propuesto_remuneracion` (`numeric|min:0`)
  requeridos si `conPropuesta`.
- **RN-06.3** — `actual_*`: si `sinActual` → `null` / `0`; si no → toma el valor del request o lo
  autocompleta del empleado (`actual_cargo ← cargo_empleado`, `actual_grado ← nivel`,
  `actual_remuneracion ← sueldo`, `actual_partida ← "{partida_presupuestaria}-{partida_individual}"`,
  `actual_proceso_inst ← proceso_institucional`).
- **RN-06.4** — `diferencial = max(0, propuesto_remuneracion − actual_remuneracion)` (nunca negativo).
- **RN-06.5** — Firmantes: se toman del request o se pre-llenan desde `d2_configuracion`
  (`FIRMANTE_TH_*`, `FIRMANTE_AUTORIDAD_*`), siempre en MAYÚSCULAS (`strtoupper(trim(...))`).
- **RN-06.6** — `medio` = `DIGITAL`/`MANUAL` (default `DIGITAL` si el valor no es válido).
  `especificacion` → MAYÚSCULAS o `null`.
- **RN-06.7** — Se crea con `estado = 'BORRADOR'`, `numero_accion = null`,
  `creado_por = user.id_emp`. HTTP 201.
- **RN-06.8 (2026-09-03)** — Registra `CREAR` en `nom_auditoria_log`.

### 5.4 Edición del borrador (`PATCH /api/acciones-personal/{id}/editar-borrador` — `editarBorrador`)

- **RN-06.9** — Solo si `estado = 'BORRADOR'` (422 si no). `fecha_elaboracion` requerida.
- **RN-06.10** — Campos editables: `motivacion`, `fecha_elaboracion`, los 4 firmantes (→ MAYÚSCULAS),
  `medio` (si válido, si no conserva), `especificacion` (`$request->has()` distingue "no enviado" de
  "vaciar"). **No** se puede cambiar el tipo, el empleado, ni la situación actual/propuesta.
- **RN-06.11 (2026-09-03)** — Registra `EDITAR_BORRADOR` en `nom_auditoria_log`.

### 5.5 Procesamiento (`PATCH /api/acciones-personal/{id}/procesar` — `procesar`)

- **RN-06.12** — Solo si `estado = 'BORRADOR'` (422 si no).
- **RN-06.13 (numeración — 2026-09-03)** — `AccionPersonal::generarSiguienteNumero($prefijo, $anio)`
  (método estático, con **advisory lock** de Postgres) calcula
  `MAX(CAST(SPLIT_PART(numero_accion,'-',3) AS INT)) + 1` sobre las acciones de ese año, y devuelve
  `"{prefijo}-{anio}-{NNNNN}"` (p. ej. `DATH-2026-00042`). **Reinicia cada año.** Antes: `MAX+1` sin
  bloqueo → dos procesamientos simultáneos podían generar el mismo número.
- **RN-06.14** — Todo `procesar()` corre dentro de `DB::transaction()` (el advisory lock solo vive
  mientras dura la transacción). `estado = 'ACTIVO'`, `numero_accion` asignado. El PDF pierde la
  banda de borrador.
- **RN-06.15 (2026-09-03)** — Registra `PROCESAR` en `nom_auditoria_log` (con el número asignado).

### 5.6 Firmantes por acción (mig. `000087`)

- **RN-06.16** — Cada acción guarda sus 4 firmantes. Flujo:
  - Al **crear**: pre-llenados desde `d2_configuracion`, editables antes de guardar.
  - Al **editar borrador**: el modal muestra los guardados; `index()` puebla desde config los
    borradores que los tengan vacíos (registros previos a la migración).
  - En el **PDF** (`pdf()`): usa los de la acción con fallback en cadena →
    `FIRMANTE_TH_NOMBRE` → `DIRECTOR_TALENTO_HUMANO` (TH); `FIRMANTE_AUTORIDAD_NOMBRE` →
    `APROBADOR_ACCION_PERSONAL` (autoridad). Cargos con default literal si todo falla.

### 5.7 Cambio de estado y auto-cierre

- **RN-06.17 (`cambiarEstado` — `PATCH /{id}/estado`, 2026-09-03)** — Única transición válida:
  **`ACTIVO → FINALIZADO` o `ACTIVO → ANULADO`** (terminales). Si `estado != 'ACTIVO'` → HTTP 422
  ("solo se permite desde ACTIVO"); si el destino no es `FINALIZADO`/`ANULADO` → HTTP 422. Un
  borrador ya no puede saltar a `ANULADO` sin número (debe pasar por `procesar()`). Si `FINALIZADO` +
  `fecha_fin` → la guarda. Registra `CAMBIAR_ESTADO` en `nom_auditoria_log`.
- **RN-06.18 (auto-cierre — 2026-09-03)** — Ya **no** vive en `index()`. Es el comando programado
  `cerrar:acciones-vencidas` (§4.0b), a las 06:00: `SUBROGACION` / `VACACIONES` / **`COMISION DE
  SERVICIOS`** en `ACTIVO` con `fecha_fin < hoy` → `FINALIZADO` + `AUTO_CERRAR` en `nom_auditoria_log`
  (una fila por acción). `ENCARGO` **no** se auto-cierra (`fecha_fin` opcional).

### 5.8 El controlador nunca toca `ad_empleado`

- **RN-06.19** — Ninguna acción cambia el `estado`, `cargo`, `sueldo` ni `id_depto` del empleado.
  Los movimientos reales de la carrera (INACTIVO por comisión, reactivación por reingreso, cambio de
  puesto por CESACION) los aplica TH **a mano** en la ficha del empleado (spec 01). `store()` sí
  valida que el estado **actual** del empleado sea coherente con el tipo (RN-06.1), pero no aplica ni
  propaga cambios.

### 5.9 Autocompletado de "situación actual" (`GET /{id_emp}/ultima-activa` — `ultimaActiva`)

- **RN-06.20** — Devuelve la última acción `ACTIVO` del empleado excluyendo
  `VACACIONES`/`ENCARGO`/`SUBROGACION` (no cambian la posición permanente), ordenada por
  `fecha_elaboracion` desc, `id` desc. Si el tipo de esa acción es `INGRESO`/`REINGRESO` devuelve sus
  campos `propuesto_*` como "actual"; si no, sus `actual_*`. Alimenta el formulario de una acción nueva.

### 5.10 PDF individual (`GET /{id}/pdf` — `pdf`)

- **RN-06.21** — Funciona en **cualquier estado**. Portrait A4, plantilla `accion_personal.blade.php`.
- **RN-06.22** — Si `estado = 'BORRADOR'` → banda roja "** BORRADOR — NO VÁLIDO **" en la parte
  superior (en flujo normal, no `position:fixed`, para no solaparse con DomPDF). Desaparece al procesar.
- **RN-06.23** — El nombre del archivo es `accion_personal_{numero}.pdf` o
  `accion_personal_borrador.pdf`.
- **RN-06.24** — `motivacion`: el blade detecta HTML (`<p>`/`<strong>`/`<em>`) y renderiza con
  `{!! !!}`; texto plano con `white-space:pre-wrap`.

### 5.11 PDF / Excel firmado (Alfresco)

- **RN-06.25 (`subirFirmado`)** — `archivo` `required|file|mimes:pdf|max:20480` (KB). Rechaza si
  `estado = 'BORRADOR'` (422). Si ya había `pdf_firmado` → borra el nodo anterior. Sube a
  `acciones-personal/{año de fecha_elaboracion}` con `relativePath` + `autoRename`; nombre
  `accion_{numero}_firmado.pdf`. Guarda `pdf_firmado = entry.id`. Registra `SUBIR_FIRMADO` en
  `nom_auditoria_log` (2026-09-03). 502 si Alfresco falla.
- **RN-06.26 (`descargarFirmado`)** — 404 si no hay `pdf_firmado`; si hay, stream con
  `Content-Disposition: attachment`. 502 si Alfresco falla.

---

## 6. Reportes

### 6.1 Listado (`queryFiltrada`)

- **RN-06.27** — `index` (paginado, `per_page` default 15), `reportePdf` (`acc_lista.blade.php`,
  landscape A4, `->download()`), `reporteExcel` (PhpSpreadsheet, 10 columnas). Filtros comunes:
  `tipo_accion`, `estado`, `buscar` (ILIKE nombre/apellido/identificación del empleado **o**
  `numero_accion`). Orden: `created_at` desc.
- **RN-06.28** — `reporteExcel` requiere `phpoffice/phpspreadsheet` en el servidor.

### 6.2 Historial de cargos y remuneraciones (`historialRemuneraciones`)

- **RN-06.29** — `GET /api/acciones-personal/historial-remuneraciones` (+ `?formato=pdf|excel`).
  Solo tipos `INGRESO`, `ENCARGO`, `SUBROGACION`, `CESACION DE FUNCIONES`, `DESTITUCION`; excluye
  `estado = BORRADOR`. Orden `fecha_inicio` asc.
- **RN-06.30** — Filtros: `id_emp` **o** `buscar` (ILIKE); `fecha_desde`/`fecha_hasta` (sobre
  `fecha_inicio`); `tipos` (coma-separado, intersección con los permitidos).
- **RN-06.31** — Por fila: `INGRESO`/`ENCARGO`/`SUBROGACION` usan `propuesto_cargo` /
  `propuesto_remuneracion` (lo que ganó en ese rol); `CESACION`/`DESTITUCION` usan `actual_*`.
  `diferencia = round(sueldo_actual_del_empleado − remuneracion_de_la_acción, 2)` (verde si +, rojo si −).
- **RN-06.32** — PDF `acc_historial_remuneraciones.blade.php` (landscape A4) + Excel.

---

## 7. UI

| Ruta | Componente | Descripción |
|---|---|---|
| `acciones-personal` | `AccionesPersonalView.vue` | Listado + filtros; acciones por fila: Ver PDF, Editar Borrador (modal), Procesar, Cambiar estado, Subir/Descargar firmado. |
| `acciones-personal/nueva` | `AccionPersonalForm.vue` | Alta del borrador. Buscador de empleado: **DESTITUCION filtra INACTIVOS**, el resto filtra ACTIVOS (solo frontend). Campo `especificacion` visible solo para COMISION DE SERVICIOS / REINGRESO / CESACION. `medio` select DIGITAL/MANUAL. |
| `acciones-personal/historial-remuneraciones` | `HistorialRemuneracionesView.vue` | Reporte del §6.2 con autocomplete de empleado (debounced), rango de fechas, checkboxes de tipos, export PDF/Excel. Solo acciones `ACTIVO` (excluye BORRADOR). |

Modal "Editar Borrador": motivación (TipTap), fecha de elaboración, firmantes, medio, especificación.

---

## 8. Criterios de aceptación

- **CA-06-1** — Un usuario sin rol Admin/TH/TH-Acciones que llama cualquier endpoint → HTTP 403.
- **CA-06-2** — Crear un `SUBROGACION` sin `fecha_fin` → HTTP 422.
- **CA-06-3** — Crear un `DESTITUCION` de un empleado `INACTIVO` sin `propuesto_cargo` → **se crea** (DESTITUCION no lleva propuesta).
- **CA-06-3b (2026-09-03)** — Crear un `DESTITUCION` de un empleado `ACTIVO` → HTTP 422 (exige INACTIVO). Crear un `COMISION DE SERVICIOS` de un empleado `INACTIVO` → HTTP 422 (exige ACTIVO).
- **CA-06-4** — Crear un `ENCARGO` sin `propuesto_cargo` → HTTP 422.
- **CA-06-5** — Crear un `INGRESO` → `actual_cargo`, `actual_grupo_ocup`, `actual_grado` quedan `null` y `actual_remuneracion = 0`.
- **CA-06-6** — `diferencial` de una acción con `propuesto_remuneracion = 800` y `actual_remuneracion = 1000` → `0` (no negativo).
- **CA-06-7** — Procesar un borrador → `estado = ACTIVO`, `numero_accion` con formato `DATH-{añoActual}-NNNNN` y `NNNNN` = (máximo del año + 1) con 5 dígitos.
- **CA-06-8** — Procesar una acción ya `ACTIVO` → HTTP 422. Dos procesamientos simultáneos → números distintos (advisory lock, 2026-09-03).
- **CA-06-9** — El primer procesamiento de un año nuevo produce `...-00001`.
- **CA-06-10** — `editarBorrador` sobre una acción `ACTIVO` → HTTP 422.
- **CA-06-11 (2026-09-03)** — Tras `php artisan cerrar:acciones-vencidas`, una `SUBROGACION` / `VACACIONES` / `COMISION DE SERVICIOS` `ACTIVO` con `fecha_fin` de ayer queda `FINALIZADO` con una fila `AUTO_CERRAR` en `nom_auditoria_log`. Un `ENCARGO` vencido **no** se cierra.
- **CA-06-12 (2026-09-03)** — `GET /acciones-personal` (`index`) **no** modifica ningún dato (el auto-cierre ya no vive ahí).
- **CA-06-12b** — `cambiarEstado` de una acción `BORRADOR` o `FINALIZADO` → HTTP 422 (solo `ACTIVO → FINALIZADO/ANULADO`).
- **CA-06-13** — `GET /{id}/pdf` de un borrador → PDF con la banda "BORRADOR — NO VÁLIDO"; tras procesar, el mismo endpoint devuelve el PDF sin banda.
- **CA-06-14** — `subirFirmado` sobre un borrador → HTTP 422.
- **CA-06-15** — `subirFirmado` con un segundo PDF → el nodo Alfresco anterior se elimina y `pdf_firmado` apunta al nuevo.
- **CA-06-16** — `historial-remuneraciones` de un `INGRESO` muestra `cargo`/`remuneracion` = los `propuesto_*`; de un `DESTITUCION`, los `actual_*`.
- **CA-06-17** — El PDF de un `INGRESO` deja la sección "situación actual" en blanco y marca la declaración juramentada en SÍ.
- **CA-06-18** — El PDF de una `COMISION DE SERVICIOS` no llena el acta de posesión y muestra el texto de `especificacion`.

---

## 9. Casos borde

| Caso | Comportamiento |
|---|---|
| Crear una acción sobre un empleado que **no** cumple el estado esperado | HTTP 422 (RN-06.1, 2026-09-03) — DESTITUCION exige INACTIVO, el resto ACTIVO. |
| Procesar dos borradores "a la vez" (concurrencia) | Números distintos — `generarSiguienteNumero()` con advisory lock dentro de `DB::transaction()` (2026-09-03). |
| Acción `ACTIVO` cuya `fecha_fin` se corrige a futuro después de un auto-cierre | Queda `FINALIZADO`; hay que reabrir con `cambiarEstado`… → **ya no se puede** (`cambiarEstado` no permite `FINALIZADO → ACTIVO` desde 2026-09-03). Corregir `fecha_fin` **antes** de que corra el comando de las 06:00, o vía SQL. |
| `numero_accion` con formato inesperado en la BD (datos migrados) | `SPLIT_PART(..., '-', 3)` puede devolver algo no numérico → `CAST ... AS INTEGER` falla la query. |
| Empleado eliminado de `ad_empleado` | Imposible (nunca se borra); la relación `empleado()` siempre resuelve. |
| `historial-remuneraciones`: empleado INACTIVO | `sueldo_actual` puede ser 0 → `diferencia` engañosa (compara contra 0). |
| `cambiarEstado` a `ANULADO` de un borrador | HTTP 422 (2026-09-03) — hay que `procesar()` primero. |
| El cron `schedule:run` deja de correr | `cerrar:acciones-vencidas` no ejecuta; `ULTIMO_CIERRE_ACCIONES` se estanca (el dashboard puede alertar, mismo patrón que el cuadre). |

---

## 10. Dependencias

- **Spec 00** — roles, `d2_configuracion`, Alfresco, PDF estándar.
- **Spec 01** — `ad_empleado` (`cargo_empleado`, `nivel`, `sueldo`, `partida_*`, `proceso_institucional`,
  `estado`, `id_depto`); el cambio de estado del empleado por comisión/destitución/reingreso se hace
  ahí, sin vínculo automático.
- **Spec 07** — la liquidación de vacaciones por comisión/desvinculación es un flujo separado
  (`LiquidacionVacController`); comparte los conceptos de comisión pero no `acc_accion_personal`.
- **Spec 11 (Reportes)** — el reporte de "movimientos de personal" (spec 11) usa otras fuentes
  (`d2_vacacion`, `d2_permiso`, `vac_liquidacion_historico`), **no** `acc_accion_personal`.
- **Spec 12 (Auditoría)** — `AccionPersonalController` instrumentado desde 2026-09-03
  (`CREAR`/`PROCESAR`/`EDITAR_BORRADOR`/`CAMBIAR_ESTADO`/`SUBIR_FIRMADO`/`AUTO_CERRAR`).
- Cron del servidor (`schedule:run`) para `cerrar:acciones-vencidas`.
- `phpoffice/phpspreadsheet` para los export Excel.

---

## 11. Deuda técnica / hallazgos

> **Los 6 hallazgos originales se corrigieron el 2026-09-03** (ver CLAUDE.md §"Acciones de
> Personal"). Sin cambios de schema.

1. ✅ **RESUELTO (2026-09-03)** — Auditoría agregada: `store`→`CREAR`, `procesar`→`PROCESAR`,
   `editarBorrador`→`EDITAR_BORRADOR`, `cambiarEstado`→`CAMBIAR_ESTADO`, `subirFirmado`→`SUBIR_FIRMADO`,
   auto-cierre→`AUTO_CERRAR`. `AccionPersonalController` está ahora en la tabla de controladores
   instrumentados.
2. ✅ **RESUELTO (2026-09-03)** — `store()` exige `estado = 'INACTIVO'` para `DESTITUCION` y `'ACTIVO'`
   para el resto → 422 si no calza. *(No valida la coherencia de secuencia — REINGRESO sin COMISION
   previa, etc. — sigue abierto, §11.9.)*
3. ✅ **RESUELTO (2026-09-03)** — `COMISION DE SERVICIOS` se auto-cierra (incluida en el comando
   `cerrar:acciones-vencidas`).
4. ✅ **RESUELTO (2026-09-03)** — `AccionPersonal::generarSiguienteNumero()` con advisory lock;
   `procesar()` envuelto en `DB::transaction()`.
5. ✅ **RESUELTO (2026-09-03)** — `cambiarEstado()` solo permite `ACTIVO → FINALIZADO/ANULADO`
   (terminales); cualquier otra transición → 422.
6. ✅ **RESUELTO (2026-09-03)** — Auto-cierre movido a `php artisan cerrar:acciones-vencidas`
   (programado 06:00), ya no vive dentro de un `GET`. Guarda `ULTIMO_CIERRE_ACCIONES` para el
   monitoreo de `schedule:run`.
7. **`historial-remuneraciones`: `diferencia` contra `sueldo_actual`** — para un empleado INACTIVO
   `sueldo_actual` suele ser 0 y la diferencia sale como todo el valor histórico en rojo, lo que
   confunde. Debería excluir inactivos o comparar contra otra base.
8. **`numero_accion` correlativo por `created_at`, no por `fecha_elaboracion`** — si se procesa en
   enero un borrador creado en diciembre, cuenta para el año de creación, no el de elaboración. Y la
   `fecha_elaboracion` (la que aparece en el documento) puede quedar desalineada del año del número.
   *(abierto — P3)*
9. **`store()` no valida la coherencia de secuencia** — permite `REINGRESO` sin una `COMISION DE
   SERVICIOS` previa, o dos `COMISION` seguidas. Solo valida el estado actual del empleado, no el
   historial de acciones. *(abierto — menor)*

---

## 12. Preguntas abiertas

- ~~¿Auditoría / validar estado del empleado / auto-cerrar COMISION / mover el auto-cierre a un comando / bloqueo en la numeración / transiciones de `cambiarEstado`?~~ ✅ los 6 hechos el 2026-09-03 (§11.1–6).
- ¿Validar la coherencia de secuencia de acciones (REINGRESO exige COMISION previa, etc.)? (§11.9)
- ¿La numeración debe basarse en `fecha_elaboracion` en vez de `created_at`? (§11.8)
- ¿`historial-remuneraciones` debería excluir inactivos o comparar contra otra base? (§11.7)
- ¿`cambiarEstado` a `ANULADO` debería exigir un motivo?
- ¿Debe existir un vínculo (aunque sea informativo) entre la acción y el cambio de estado que TH
  hace en la ficha del empleado?
