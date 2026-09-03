# Spec 08 — Horas Extras

| | |
|---|---|
| **Versión** | 1.0 (borrador) |
| **Última actualización** | 2026-09-03 |
| **Depende de** | [00 — Paraguas](00-modulo-talento-humano.md), [01 — Empleados](01-empleados.md), [03 — Asistencia](03-control-asistencia.md) |
| **Controlador** | `app/Http/Controllers/HorasExtrasController.php` |
| **Modelos** | `HePlanificacionCab` (`dbo.nom_he_planificacion_cab`), `HePlanificacionDet` (`dbo.nom_he_planificacion_det`), `HeRegistro` (`dbo.nom_he_registro`) |
| **Vista** | `views/horasextras/HorasExtrasView.vue` (ruta `horas-extras`) |
| **PDF** | `reportes/he_planificacion.blade.php`, `reportes/he_registros.blade.php` (portrait A4) |
| **Tipo** | Retro-especificación |

---

## 1. Propósito

Gestionar el ciclo mensual de horas extraordinarias y suplementarias de un servidor: el empleado
**planifica** las actividades y horas previstas del mes, el supervisor las aprueba, TH NOMINA las
procesa con un N° de memorando, el empleado **registra las horas reales** trabajadas, TH NOMINA las
revisa y el supervisor las confirma. Sobre las horas confirmadas, TH NOMINA calcula el valor a pagar.

### 1.1 Conceptos

| Término | Definición |
|---|---|
| **Horas extraordinarias** | Trabajo en fin de semana / feriado, o en días hábiles entre 00:00–06:00. Se pagan con recargo `porc_extraordinaria` de la jornada. |
| **Horas suplementarias** | Trabajo en días hábiles 06:00–08:00 y 16:30–24:00. Recargo `porc_suplementaria`. |
| **Horas normales** | Días hábiles, entre la ENTRADA y la SALIDA del **turno real** del empleado ese día (2026-09-03) — **no** generan pago extra. |
| **Planificación** | Cabecera mensual (`HePlanificacionCab`) + detalle de actividades previstas (`HePlanificacionDet`). |
| **Registro** | Hora real trabajada un día concreto (`HeRegistro`), con clasificación automática. |

### 1.2 Alcance / fuera de alcance

Incluye: calculadora de clasificación, planificación (CRUD + aprobación + procesamiento), registro
de horas reales (con revisión y confirmación), PDFs, subida del firmado a Alfresco, cálculo
monetario.

**No** cubre: la nómina en sí (spec 09 — el valor calculado aquí no se persiste ni se traslada
automáticamente al rol de pagos); la **gestión** de turnos/jornadas/asistencia (spec 03) — este
módulo solo **lee** `d2_jornada` (recargo) y `d2_programacion`/`d2_turno` (turno real del día, desde
2026-09-03) para clasificar y valorar horas, no las administra.

---

## 2. Actores

| Actor | Puede |
|---|---|
| Empleado | Planificar su mes, editar/eliminar su planificación mientras está `PENDIENTE`, registrar horas reales (solo mes planificado = mes actual y planificación `PROCESADO`), editar su registro mientras está `EN REVISION`. |
| **Supervisor** (`dbo.supervisor_area`) | Aprobar / negar planificaciones `PENDIENTE` de su equipo; confirmar / negar registros `PENDIENTE` de su equipo. Ve el tab "del equipo". No sobre su propia planificación. |
| **TH NOMINA** / `ADMINISTRADOR` / `TALENTO HUMANO` (`esAdminOTH` — **incluye `TH NOMINA`**, a diferencia del resto de controladores) | Todo lo del supervisor sobre cualquier empleado; **procesar** (autorizar con memorando) planificaciones `APROBADO`; **revisar** registros `EN REVISION` (aprobar la revisión o devolver al empleado); ver el desglose monetario. |

`empleadosDeSupervisor()` **(2026-09-03) ya cae en cascada** a los departamentos hijos del
supervisor (mismo patrón que specs 04/05/11) — antes solo miraba el departamento directo. Ver §11.5.

---

## 3. Modelo de datos

### 3.1 `dbo.nom_he_planificacion_cab` (cabecera mensual)

Clave lógica `unique(id_emp, anio, mes)` (verificada con `->exists()`, sin lock — §11.4).

| Campo | Contenido |
|---|---|
| `id` PK, `id_emp`, `anio`, `mes` | — |
| `estado` | `PENDIENTE` → `APROBADO` → `PROCESADO` · `NEGADO`. |
| `total_extraordinarias`, `total_suplementarias` | Suma de los detalles (máx. 20 h cada uno). |
| `usuario_registro`, `fecha_registro` | Alta. |
| `usuario_decision`, `fecha_decision` | Aprobación / negación. |
| `observacion` | Motivo de negación. |
| `memorando` | N° de memorando (obligatorio al **procesar**). |
| `usuario_autorizacion`, `fecha_autorizacion` | Procesamiento (TH NOMINA). |
| `pdf_aprobado` | Node ID de Alfresco del PDF firmado. |

### 3.2 `dbo.nom_he_planificacion_det` (actividades previstas)

`cab_id`, `actividad` (texto ≤300), `horas_extraordinarias`, `horas_suplementarias`.

### 3.3 `dbo.nom_he_registro` (horas reales)

| Campo | Contenido |
|---|---|
| `id` PK, `cab_id`, `id_emp` | — |
| `fecha`, `hora_inicio`, `hora_fin` | Rango trabajado (`H:i`). |
| `horas_extraordinarias`, `horas_suplementarias` | **Calculadas automáticamente** por `calcularHorasExtras()` — no las digita el empleado. |
| `descripcion` | Texto libre ≤300. |
| `estado` | `EN REVISION` → `PENDIENTE` → `APROBADO` / `NEGADO`. |
| `observacion` | Motivo de devolución / negación. |
| `devuelto_count` | Nº de veces que TH NOMINA lo devolvió al empleado (badge en la UI). |
| `usuario_decision`, `fecha_decision` | Última decisión. |

### 3.4 Consumido de otras tablas

| Tabla | Uso |
|---|---|
| `dbo.d2_lista_fecha` | ¿La fecha es feriado? (`whereDate('fecha')->exists()`). |
| `dbo.d2_jornada` (`Jornada`) | `porc_extraordinaria` / `porc_suplementaria` para el cálculo monetario. |
| `dbo.d2_programacion` + `dbo.d2_turno` (2026-09-03) | Turno real del empleado ese día (`s{día}` → `id_turno` → hora de `ENTRADA`/`SALIDA`), para definir el tramo "normal" — mismo mecanismo que `ProcesarCuadre` (spec 03) y `PermisosController::horaTurnoDelDia()` (spec 04). Antes las horas "normales" estaban fijas en 08:00–16:30 sin importar el turno real (§11.1). |
| `dbo.d2_configuracion` | `nombre_institucion`, `DIRECTOR_TALENTO_HUMANO` (PDFs). |
| `dbo.supervisor_area` | Supervisor del departamento (firmante del PDF, autorización). |

---

## 4. Clasificación automática de horas (`calcularHorasExtras($fecha, $horaInicio, $horaFin, $id_emp)`)

- **RN-08.1** — `hora_fin = 00:00` → se trata como `24:00`. `hora_fin <= hora_inicio` → cruza
  medianoche (`+24h`).
- **RN-08.2 (fin de semana o feriado)** — `Carbon::isWeekend()` **o** existe en `d2_lista_fecha` →
  **todo** el rango cuenta como **extraordinarias**.
- **RN-08.3 (día hábil — 2026-09-03, jornada real del turno)** — se solapa el rango contra estos
  tramos, calculados con la ENTRADA (`tEntrada`) y SALIDA (`tSalida`) del **turno real** del
  empleado ese día (`horaTurnoDelDia($id_emp, $fecha, 'ENTRADA'|'SALIDA')`, vía `d2_programacion` →
  `d2_turno`, mismo mecanismo de `ProcesarCuadre`/`PermisosController` — spec 03 / spec 04):
  | Tramo | Tipo |
  |---|---|
  | 00:00–06:00 | extraordinaria |
  | 06:00–`tEntrada` | suplementaria |
  | **`tEntrada`–`tSalida`** | **normal (no paga)** |
  | `tSalida`–24:00 | suplementaria |
  | 24:00–30:00 (día siguiente) | extraordinaria |
  | 30:00–(24:00+`tEntrada`) (día siguiente) | suplementaria |

  Si no se pasa `$id_emp`, o el turno del empleado no tiene `ENTRADA`/`SALIDA` configurada ese día,
  cae al **default histórico 08:00–16:30** (antes fijo para cualquier empleado — un servidor con
  horario real distinto, ej. 07:30–16:00 o jornada partida, recibía la clasificación incorrecta;
  el cálculo monetario ya usaba la jornada real, pero solo para los **porcentajes** de recargo, no
  para estos límites). Sin soporte para jornada partida con dos tramos de ENTRADA/SALIDA en el
  mismo día (fuera de alcance — caso raro, no reportado en producción).
- **RN-08.4** — Devuelve `{ horas_extraordinarias, horas_suplementarias }` redondeadas a 2 decimales.
- **RN-08.5 (`GET /api/horas-extras/calcular`)** — endpoint calculadora puro (valida `fecha`,
  `hora_inicio`/`hora_fin` `H:i`); lo usa el frontend para previsualizar **las horas propias** del
  usuario autenticado (`$request->user()->id_emp`, 2026-09-03) — nunca de otro empleado.

---

## 5. Reglas de negocio — planificación

### 5.1 Crear (`POST /api/horas-extras/planificacion` — `store`)

- **RN-08.6** — Request: `anio` (≥2020), `mes` (1–12), `detalles[]` (≥1) con `actividad` (≤300),
  `horas_extraordinarias`/`horas_suplementarias` (nullable, ≥0).
- **RN-08.7** — El solicitante es siempre `$request->user()`. Si ya existe planificación
  `(id_emp, anio, mes)` → HTTP 422.
- **RN-08.8** — `total_extraordinarias` / `total_suplementarias` = suma de los detalles.
  - Ambos ≤ 0 → 422 "debe ingresar al menos una hora".
  - `total_extraordinarias > 20` **o** `total_suplementarias > 20` → 422.
- **RN-08.9 (auto-aprobación)** — si el solicitante es supervisor **o** `esAdminOTH` →
  `estado = 'APROBADO'` con decisión propia; si no → `PENDIENTE`.
- **RN-08.10** — Se crean los `_det`. HTTP 201. **No** valida que el mes sea actual/futuro (se puede
  planificar un mes pasado — §11.3). **Audita `CREAR`** (2026-09-03; antes no auditaba).

### 5.2 Editar / eliminar (`update`, `destroy`)

- **RN-08.11** — `update` (`PUT`) y `destroy` (`DELETE`): solo el **dueño** o `esAdminOTH`; solo si
  `estado = 'PENDIENTE'` (422 si no).
- **RN-08.12** — `update` reemplaza **todos** los `_det` (delete + recrear) y recalcula los totales
  (mismos topes de 20 h). `destroy` hace `$cab->delete()` (**borrado físico** — no soft state).
  **Ambos auditan** (`ACTUALIZAR` / `ELIMINAR`, 2026-09-03) — `destroy` toma una fotografía de la
  cabecera (`anio`, `mes`, `estado`, totales) **antes** del `delete()` para tener `datos_anteriores`,
  ya que el borrado es físico y no queda ninguna fila de la que leer después.

### 5.3 Listar (`GET /api/horas-extras/planificacion` — `index`)

- **RN-08.13** — Requiere supervisor **o** `esAdminOTH` (403 si no). Filtra por `anio`/`mes`
  (default mes actual). `esAdminOTH` → todas; supervisor → solo su equipo (`empleadosDeSupervisor`,
  **con cascada a hijos desde 2026-09-03** — §11.5).

### 5.4 Aprobar / negar (`aprobar`, `negar`)

- **RN-08.14** — `aprobar` (`PATCH .../aprobar`): no la propia (403); supervisor (equipo) o admin;
  estado `PENDIENTE` (422). → `APROBADO` + decisión + `observacion = null`. **Audita `APROBAR`**.
- **RN-08.15** — `negar` (`PATCH .../negar`): `observacion` **requerida** (≤250); mismas reglas de
  autorización y estado; → `NEGADO`. **Audita `NEGAR`**. *(`negar` no bloquea negar la propia
  planificación — §11.6.)*

### 5.5 Procesar / autorizar (`PATCH .../autorizar` — `autorizar`)

- **RN-08.16** — **Solo `esAdminOTH`** (TH NOMINA / Admin / TH). `memorando` **requerido** (≤300).
  Estado debe ser `APROBADO` (422). → `PROCESADO` + `memorando` + `usuario_autorizacion`.
  **Audita `PROCESAR`**. Solo con la planificación `PROCESADO` el empleado puede registrar horas reales.

---

## 6. Reglas de negocio — registro de horas reales

### 6.1 Registrar (`POST /api/horas-extras/registro` — `registrar`)

- **RN-08.17** — Request: `cab_id`, `fecha`, `hora_inicio`/`hora_fin` (`H:i`), `descripcion` (≤300).
- **RN-08.18** — El `cab.id_emp` debe ser el del usuario (403). `cab.estado` debe ser `PROCESADO` (422).
- **RN-08.19 (mes)** — el mes/año de **hoy** debe coincidir con `cab.anio` / `cab.mes` → 422
  "solo puede registrar horas en el mes planificado". (Contrasta con la planificación, que sí acepta
  meses pasados.)
- **RN-08.20** — `calcularHorasExtras()` de las horas del request — **recibe además el `id_emp` del
  solicitante desde 2026-09-03**, para usar su turno real (§4, RN-08.3) en vez del default
  08:00–16:30. Si el rango no genera ni extra ni supl (todo cae en el tramo normal del turno) → 422.
- **RN-08.21 (tope contra lo planificado)** — la suma de `horas_extraordinarias` de los registros del
  `cab` en estado `PENDIENTE`/`APROBADO` + las nuevas no puede superar `cab.total_extraordinarias`
  (422 con las disponibles). Ídem para suplementarias.
- **RN-08.22** — Se crea el `HeRegistro` con `estado = 'EN REVISION'`. HTTP 201. **Audita
  `REGISTRAR`** (2026-09-03; antes no auditaba).

### 6.2 Editar el registro (`PUT .../registro/{id}` — `actualizarRegistro`)

- **RN-08.23** — Solo el dueño; solo si `estado = 'EN REVISION'` (422). Recalcula las horas (con el
  turno real del empleado, RN-08.20), limpia `observacion`. **Audita `ACTUALIZAR`** (2026-09-03).
  *(Sigue sin re-verificar el tope de RN-08.21 al editar — §11.7, no era parte del alcance corregido
  hoy.)*

### 6.3 Revisar (`PATCH .../registro/{id}/revisar` — `revisarRegistro`)

- **RN-08.24** — **Solo `esAdminOTH`**. `accion` ∈ {`aprobar`, `devolver`}. El registro debe estar
  `EN REVISION` (422).
  - `aprobar` → `estado = 'PENDIENTE'` (pasa al supervisor) + decisión. Sin observación.
  - `devolver` → `observacion` **requerida**; `estado` sigue `EN REVISION`, `devuelto_count += 1`.
- **RN-08.25 (2026-09-03)** — **Audita `REVISAR_APROBAR` / `REVISAR_DEVOLVER`** — antes era el único
  paso de control de nómina sobre un registro de pago sin ninguna traza. *(Bonus encontrado al tocar
  el archivo: `devuelto_count` no estaba en el `$fillable` de `HeRegistro` — el `update()` de
  `devolver` lo descartaba en silencio [Laravel no lanza excepción por defecto en este proyecto] y el
  contador **nunca subía**, pese a que el `+1` se ejecutaba sin error. Corregido agregándolo al
  `$fillable`.)*

### 6.4 Confirmar / negar (`confirmar`, `negarRegistro`)

- **RN-08.26** — `confirmar` (`PATCH .../confirmar`): supervisor (equipo) o admin; estado
  `PENDIENTE` (422). → `APROBADO` + decisión. **Audita `CONFIRMAR`**.
- **RN-08.27** — `negarRegistro` (`PATCH .../negar`): `observacion` **requerida** (≤250); mismas
  reglas; → `NEGADO`. **Audita `NEGAR`**.

### 6.5 Listar registros del equipo (`GET .../equipo-registros` — `equipoHoras`)

- **RN-08.28** — Supervisor **o** `esAdminOTH` (403 si no). Registros cuya planificación es del
  `anio`/`mes`; supervisor → solo su equipo. Filtro opcional `estado`.
- **RN-08.29 (desglose monetario)** — **solo si `esAdminOTH`**, para los registros `APROBADO`:
  `tarifa = sueldo / 240`; `valor_extraordinarias = round(tarifa × (1 + porc_extraordinaria/100) × horas_extraordinarias, 2)`;
  `valor_suplementarias` análogo con `porc_suplementaria`; `valor_total` = suma. **Calculado al vuelo,
  no se persiste.**

### 6.6 Mis registros (`GET .../mis-registros` — `misHoras`)

- **RN-08.30** — Del usuario: `{ planificacion, registros }` del `anio`/`mes` (default actual).

---

## 7. Flujo completo (estados)

```
Planificación:  PENDIENTE ──aprobar──▶ APROBADO ──autorizar(memorando)──▶ PROCESADO
                    │                                                       │
                  negar                                            (habilita registro)
                    ▼
                 NEGADO
  (si el creador es supervisor/admin → nace APROBADO directamente)

Registro:  EN REVISION ──TH NOMINA aprobar──▶ PENDIENTE ──supervisor confirmar──▶ APROBADO
                 ▲                                  │
          TH NOMINA devolver (+1)              supervisor negar
                 │                                  ▼
          empleado corrige                       NEGADO
```

---

## 8. PDFs y Alfresco

- **RN-08.31 (`GET .../pdf`)** — `he_planificacion.blade.php` (portrait A4): actividades planificadas,
  datos del empleado, jornada, firmas (empleado + supervisor del departamento, con fallback a
  `DIRECTOR_TALENTO_HUMANO`). **Valida `puedeVerPlanificacion()`** (2026-09-03) — dueño, su
  supervisor (`empleadosDeSupervisor`, con cascada), o `esAdminOTH`; 403 si no. Antes sin ningún
  chequeo — cualquier autenticado podía descargar la planificación de cualquiera (§11.8).
- **RN-08.32 (`GET .../pdf-registros`)** — `he_registros.blade.php`: solo registros `APROBADO`.
  Valida dueño o `esAdminOTH` (403 si no) — **más estricto** que `puedeVerPlanificacion` (no incluye
  al supervisor); se dejó tal cual, no era parte del hallazgo.
- **RN-08.33 (`POST .../subir-firmado`)** — `archivo` `mimes:pdf|max:20480` (KB). **Valida
  `puedeEditarFirmadoPlanificacion()`** (2026-09-03) — solo el dueño o `esAdminOTH` (sin supervisor,
  a diferencia de ver/descargar); 403 si no. Si ya había `pdf_aprobado`, borra el nodo anterior. Sube
  a `horas-extras/{año}` (`relativePath` + `autoRename`), nombre
  `he_{id_emp}_{anio}_{mes}_firmado.pdf`. Guarda `pdf_aprobado`. 502 si Alfresco falla. **Audita
  `SUBIR_FIRMADO`** (2026-09-03). Antes sin chequeo ni auditoría — cualquier autenticado podía subir
  un "PDF firmado" a la carpeta Alfresco de cualquier planificación ajena, **borrando el firmado
  anterior** si ya existía (§11.8).
- **RN-08.34 (`GET .../descargar-firmado`)** — 404 si no hay `pdf_aprobado`; stream desde Alfresco.
  **Valida `puedeVerPlanificacion()`** (2026-09-03, mismo criterio que RN-08.31); 403 si no. Antes
  sin ningún chequeo (§11.8).

---

## 9. UI (`HorasExtrasView.vue`) — 4 tabs

| Tab | Contenido |
|---|---|
| **Mi Planificación** | crear/editar, PDF de planificación, subir PDF firmado. Botones PDF visibles en `APROBADO` y `PROCESADO`. |
| **Mis Horas Trabajadas** | registrar horas (mes planificado + actual), PDF de horas trabajadas (si ≥1 registro `APROBADO`). Badge naranja "Dev. Nv" si `devuelto_count > 0`. |
| **Planificaciones del Equipo** | aprobar/negar (supervisor/admin). |
| **Registros del Equipo** | revisar (TH NOMINA) / confirmar (supervisor) / negar + **desglose monetario** (solo TH NOMINA). |

`GET /api/horas-extras/mi-rol` → `{ es_supervisor, es_admin_th }` (para mostrar/ocultar tabs). Horas
en formato `Xh Ym`. Detalle en CLAUDE.md §"Horas Extras" / `views/horasextras/`.

---

## 10. Criterios de aceptación

**Clasificación**
- **CA-08-1** — `calcular` para un sábado 08:00–12:00 → 4 h extraordinarias, 0 suplementarias.
- **CA-08-2** — `calcular` para un martes 17:00–20:00 → 3 h suplementarias, 0 extraordinarias.
- **CA-08-3** — `calcular` para un martes 05:00–09:00 → 1 h extraordinaria (05–06) + 2 h suplementarias (06–08) + 1 h normal (08–09) descartada.
- **CA-08-4** — `calcular` para una fecha que existe en `d2_lista_fecha` (feriado) 09:00–13:00 → 4 h extraordinarias.
- **CA-08-5** — `calcular` para un martes 10:00–15:00 (todo dentro de 08:00–16:30) → 0 y 0.

  *(CA-08-1 a CA-08-5 asumen el default 08:00–16:30 — válido para un empleado sin turno especial
  configurado en `d2_programacion`/`d2_turno` ese mes, que sigue siendo el caso más común. Ver
  CA-08-19 para el caso con turno real distinto.)*

**Planificación**
- **CA-08-6** — `store` de un mes que ya tiene planificación → HTTP 422.
- **CA-08-7** — `store` con `total_extraordinarias = 21` → HTTP 422.
- **CA-08-8** — `store` por un empleado sin rol → `estado = 'PENDIENTE'`; por un supervisor → `estado = 'APROBADO'`.
- **CA-08-9** — `update` de una planificación `APROBADO` → HTTP 422.
- **CA-08-10** — `aprobar` la planificación propia → HTTP 403; aprobar la de un empleado que no es del equipo (siendo supervisor no-admin) → HTTP 403.
- **CA-08-11** — `autorizar` por un supervisor (no TH NOMINA/Admin) → HTTP 403; `autorizar` sin `memorando` → HTTP 422; sobre una planificación `PENDIENTE` → HTTP 422.
- **CA-08-12** — Aprobar / negar / procesar una planificación deja un registro `APROBAR` / `NEGAR` / `PROCESAR` en `nom_auditoria_log`.

**Registro**
- **CA-08-13** — `registrar` sobre una planificación no `PROCESADO` → HTTP 422.
- **CA-08-14** — `registrar` en un mes distinto del planificado → HTTP 422.
- **CA-08-15** — `registrar` que haría superar `cab.total_extraordinarias` → HTTP 422 con las horas disponibles.
- **CA-08-16** — Registrar → `estado = 'EN REVISION'`; TH NOMINA `revisar` con `accion=devolver` → `devuelto_count` sube 1 y sigue `EN REVISION`.
- **CA-08-17** — TH NOMINA `revisar` con `accion=aprobar` → `PENDIENTE`; supervisor `confirmar` → `APROBADO` + auditoría `CONFIRMAR`.
- **CA-08-18** — En `equipo-registros`, un registro `APROBADO` visto por TH NOMINA trae `valor_total = valor_extraordinarias + valor_suplementarias` con `tarifa = sueldo/240`.

**Turno real y seguridad (2026-09-03)**
- **CA-08-19** — Empleado con turno `ENTRADA=07:30`/`SALIDA=16:00`; `calcular` para un martes 07:30–08:00 (30 min) → `0` extraordinarias y `0` suplementarias (cae dentro del tramo normal del turno real). Antes de la corrección, con el límite fijo en 08:00, ese mismo rango daba `0.5` h suplementarias.
- **CA-08-20** — `store` / `destroy` de una planificación, y `registrar` / `revisar` (ambas acciones) de un registro, dejan `CREAR` / `ELIMINAR` / `REGISTRAR` / `REVISAR_APROBAR` / `REVISAR_DEVOLVER` en `nom_auditoria_log`.
- **CA-08-21** — `GET .../pdf`, `POST .../subir-firmado` y `GET .../descargar-firmado` de la planificación de **otro** empleado, llamados por alguien que no es su supervisor ni TH/Admin → HTTP 403 en los 3.
- **CA-08-22** — Un supervisor cuyo departamento tiene un departamento **hijo** ve, en `index` / `equipo-registros`, las planificaciones/registros de los empleados de ese departamento hijo (antes solo veía el directo).
- **CA-08-23** — TH NOMINA `revisar` con `accion=devolver` dos veces sobre el mismo registro (con el empleado corrigiendo y re-enviando entre medio) → `devuelto_count = 2` (antes se quedaba en `0`, el incremento se descartaba en silencio por no estar en el `$fillable`).

---

## 11. Deuda técnica / hallazgos

> **Los hallazgos 1, 2, 5 y 8 se corrigieron el 2026-09-03** (ver CLAUDE.md §"Horas Extras"). Sin
> cambios de schema — solo `git pull`, sin `migrate` ni `config:clear`. El 9 se dejó documentado como
> gap de feature (no bug), pendiente de decisión de diseño.

1. ✅ **RESUELTO (2026-09-03)** — Las horas "normales" ya no están fijas en 08:00–16:30: se resuelven
   desde el turno real del empleado ese día (`d2_programacion` → `d2_turno`, mismo mecanismo de
   `ProcesarCuadre`/spec 03). Si no hay turno configurado, cae al mismo default 08:00–16:30 de antes.
   El cálculo monetario seguía usando la jornada correctamente para los **porcentajes** de recargo —
   eso no cambió.
2. ✅ **RESUELTO (2026-09-03)** — `store` / `update` / `destroy` (planificación) y `registrar` /
   `actualizarRegistro` / `revisarRegistro` (registro) ahora llaman `AuditoriaService::log()`
   (`CREAR`/`ACTUALIZAR`/`ELIMINAR`/`REGISTRAR`/`ACTUALIZAR`/`REVISAR_APROBAR`/`REVISAR_DEVOLVER`).
   `destroy` sigue borrando físicamente la fila (no se cambió a un estado tipo ANULADO — fuera del
   alcance corregido hoy), pero ahora la auditoría guarda una fotografía de la cabecera *antes* del
   `delete()`.
3. **`store` de planificación acepta meses pasados** — no valida que `(anio, mes)` sea el actual o
   futuro (a diferencia de `registrar`, que sí lo exige). Se puede planificar retroactivamente.
   *(abierto — no estaba en el alcance de hoy)*
4. **`unique(id_emp, anio, mes)` sin lock/transacción** — el `->exists()` previo no protege contra un
   doble-submit concurrente → el segundo `create` violaría la constraint con un 500. Bajo riesgo
   (flujo de un solo usuario). *(abierto — mismo patrón que se corrigió con advisory lock en
   Empleados/Acciones de Personal/Certificados, no aplicado aquí todavía)*
5. ✅ **RESUELTO (2026-09-03)** — `empleadosDeSupervisor()` ahora cae en cascada a los departamentos
   hijos del supervisor (`deptosHijos → supervisoresHijos`), mismo patrón que
   `PermisosController`/`VacacionesController`/`DashboardController` (specs 04/05/11). Se aplicó
   tanto a la lista (`index`, `equipo-registros`) como a los nuevos helpers de propiedad
   (`puedeVerPlanificacion`).
6. **`negar` (planificación) no bloquea negar la propia** — a diferencia de `aprobar` (que sí
   rechaza `cab.id_emp === user.id_emp`). Menor. *(abierto)*
7. **`actualizarRegistro` no re-verifica el tope** de RN-08.21 — un empleado puede crear un registro
   pequeño (pasa el tope) y luego editarlo a un rango mayor que exceda `cab.total_*`. *(abierto — no
   estaba en el alcance de hoy)*
8. ✅ **RESUELTO (2026-09-03)** — `pdf`, `subir-firmado` y `descargar-firmado` de la planificación
   ahora validan propiedad/rol (`puedeVerPlanificacion()` — dueño, su supervisor con cascada, o
   TH/Admin — para ver/descargar; `puedeEditarFirmadoPlanificacion()` — dueño o TH/Admin, sin
   supervisor — para subir el firmado), mismo tipo de gap que ya se había cerrado en Permisos
   (spec 04 §10.1) y Certificados (spec 10). `subir-firmado` además ahora audita `SUBIR_FIRMADO`.
   `pdf-registros` no se tocó — ya validaba correctamente (aunque de forma más estricta, sin incluir
   al supervisor; se dejó así por no ser parte del hallazgo).
9. **El valor monetario no se persiste ni fluye a la nómina.** `equipoHoras` lo calcula al vuelo; el
   rol de pagos (spec 09, aún no escrita) no lo consume automáticamente — hay que trasladarlo a
   mano. *(abierto a propósito — es un gap de feature, no un bug: requeriría decidir dónde persistir
   el valor calculado — ¿columnas nuevas en `nom_he_registro`? ¿una tabla puente hacia
   `nom_rol_pago_det`?— y cómo evitar double-counting si TH edita el rol de pagos manualmente
   mientras tanto. Se deja para cuando se escriba/retoque la spec 09.)*

---

## 12. Dependencias

- **Spec 00** — roles (`esAdminOTH` incluye `TH NOMINA`), `supervisor_area`, `d2_configuracion`, Alfresco, auditoría.
- **Spec 01** — `ad_empleado` (`id_jornada`, `sueldo`, `id_depto`, `estado`).
- **Spec 03 (Asistencia)** — `d2_lista_fecha` (feriados); `d2_programacion`/`d2_turno` y el patrón
  `ProcesarCuadre` para resolver el turno real del día (2026-09-03, RN-08.3, §11.1) — mismo mecanismo
  que ya usa el cuadre nocturno de marcaciones.
- **Spec 04 (Permisos)** — `PermisosController::horaTurnoDelDia()` es el mismo patrón que
  `horaTurnoDelDia()` de este controlador (copias equivalentes, no compartidas).
- **Spec 09 (Nómina, aún no escrita)** — el valor de HE calculado aquí **no** se traslada
  automáticamente al rol de pagos (§11.9, abierto a propósito).
- `d2_jornada` — `normal`, `porc_extraordinaria`, `porc_suplementaria` (solo para el cálculo
  monetario, no para los límites de clasificación desde 2026-09-03).

---

## 13. Preguntas abiertas

- ~~¿La clasificación de horas "normales" debería leer el turno real del empleado / cerrar
  `pdf`-`subir-firmado`-`descargar-firmado` / auditar `store`-`destroy`-`revisarRegistro` / unificar
  `empleadosDeSupervisor` con cascada?~~ ✅ los 4 hechos el 2026-09-03 (§11.1, 2, 5, 8).
- ¿`store` de planificación debería rechazar meses pasados? (§11.3)
- ¿`actualizarRegistro` debe re-verificar el tope contra lo planificado? (§11.7)
- ¿El valor de HE debería persistirse y aparecer en el rol de pagos del mes? Si sí, ¿dónde vive el
  dato — nuevas columnas en `nom_he_registro`, o una tabla puente hacia `nom_rol_pago_det`? (§11.9,
  para cuando se escriba la spec 09)
- ¿Vale la pena el advisory lock en `store` (RN-08.7 / §11.4), igual que se hizo en
  Empleados/Acciones de Personal/Certificados, dado el bajo riesgo real (flujo de un solo usuario)?
