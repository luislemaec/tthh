# Spec 07 — Liquidación de Vacaciones

| | |
|---|---|
| **Versión** | 1.0 (borrador) |
| **Última actualización** | 2026-09-03 |
| **Depende de** | [00 — Módulo Talento Humano](00-modulo-talento-humano.md), [01 — Empleados](01-empleados.md), [05 — Vacaciones](05-vacaciones.md) |
| **Controlador** | `app/Http/Controllers/LiquidacionVacController.php` |
| **Modelo** | `App\Models\LiquidacionHistorico` (`dbo.vac_liquidacion_historico`) + `CabeceraVacacion` |
| **Vista** | `views/planificacion/LiquidacionVacView.vue` (ruta `planificacion/liquidacion`) |
| **PDF** | `reportes/liquidacion_vacaciones.blade.php` (portrait A4) |
| **Migración** | `2026_04_06_000002_create_vac_liquidacion_historico.php` |
| **Tipo** | Retro-especificación |

---

## 1. Propósito

Registrar un **evento de cambio de estado laboral** de un servidor (salida a comisión, retorno de
comisión, llegada en comisión, salida de comisión, desvinculación) junto con una **fotografía del
saldo de vacaciones** a la fecha del evento. Según el motivo:

- **Genera un certificado PDF** de días de vacaciones acumulados (para que el servidor lo lleve a la
  otra institución) — `INICIO_COMISION`, `FIN_COMISION_SALIDA`.
- **Carga días desde un certificado externo** en `d2_cabecera_vacacion` (el servidor vuelve /
  llega con un saldo acumulado en otra institución) — `FIN_COMISION_RETORNO`, `COMISION_ENTRANTE`.
- **Solo deja constancia histórica** — `DESVINCULACION`.

El histórico (`vac_liquidacion_historico`) es además la fuente del **"reset" del kardex** de saldo
de vacaciones (spec 05 §8.1): tras un `FIN_COMISION_RETORNO` / `COMISION_ENTRANTE`, el kardex
arranca de nuevo desde la fecha de ese evento.

### 1.1 Qué NO es

- **No** es la liquidación financiera de **viáticos** de comisiones de servicio — eso es el módulo
  Comisiones (`Comisiones\LiquidacionController`, `com_ficha_liquidacion`, rutas `/comisiones/*`),
  fuera de esta spec.
- **No** paga nada ni genera transferencias — solo registra el saldo y (opcionalmente) emite un PDF.
- **No** cambia el `estado` del empleado — TH debe dejar al empleado `ACTIVO`/`INACTIVO` **con su
  `fecha_salida`/`fecha_ingreso`** en la ficha (spec 01) **antes** de registrar el evento aquí.

---

## 2. Actores

| Actor | Puede |
|---|---|
| `ADMINISTRADOR`, `TALENTO HUMANO` | Buscar empleado, consultar saldo, registrar evento, generar el certificado PDF. |

Los 4 métodos verifican `esAdminOTH($request->user()->id_emp)` **inline** (no `requireRole`) →
HTTP 403 si no cumple.

---

## 3. Historias de usuario

- **HU-07.1** — Como TH, quiero buscar a cualquier empleado (activo o inactivo) y ver su saldo de vacaciones a la fecha relevante.
- **HU-07.2** — Como TH, quiero registrar que un servidor con Nombramiento Definitivo salió a comisión de servicios y descargar el certificado de días acumulados.
- **HU-07.3** — Como TH, quiero registrar el retorno de un servidor y cargar los días que trae del certificado de la otra institución.
- **HU-07.4** — Como TH, quiero registrar la llegada de un servidor que viene en comisión desde otra institución, con su saldo externo.
- **HU-07.5** — Como TH, quiero dejar constancia de una desvinculación (contrato ocasional / provisional) con el saldo a la fecha de salida.
- **HU-07.6** — Como TH, quiero ver el historial de eventos de liquidación de un empleado.

---

## 4. Modelo de datos — `dbo.vac_liquidacion_historico`

`timestamps = false`. Una fila por evento registrado. Es una **fotografía inmutable** (no se edita
ni se borra desde la app).

| Campo | Contenido |
|---|---|
| `id_emp` | Empleado del evento. Relación `empleado()`. |
| `motivo` | `INICIO_COMISION` / `FIN_COMISION_RETORNO` / `COMISION_ENTRANTE` / `FIN_COMISION_SALIDA` / `DESVINCULACION`. |
| `fecha_evento` | Fecha del cambio de estado (la ingresa TH). |
| `fecha_corte_usada` | La fecha de corte con la que se calculó el saldo (para trazabilidad). |
| `saldo_inicial` | `dias_adicionales` de `d2_cabecera_vacacion` al momento del cálculo. |
| `acumulado` | Días devengados entre `fecha_corte_usada` y `fecha_evento`. |
| `tomados` | `total_dias_tomados` al momento del cálculo. |
| `saldo_liquidado` | `max(0, saldo_inicial + acumulado − tomados)`. **Sin tope de 60** (a diferencia del saldo visible al empleado — spec 05 §5.9). |
| `observacion` | Texto libre de TH (≤500). |
| `usuario_proceso` | `id_emp` del usuario que registró. |
| `fecha_registro` | `now()` al crear. |

> **Consumido por** `ReporteVacacionesController::buildKardex()` (spec 05): las filas
> `FIN_COMISION_RETORNO` / `COMISION_ENTRANTE` marcan el punto desde el que se reconstruye el kardex;
> las demás aparecen como filas `LIQUIDACION` de contexto.

---

## 5. Reglas de negocio

### 5.1 Catálogo de motivos

**Motivos válidos por modalidad laboral** — `MOTIVOS_POR_MODALIDAD` está **keyeado por el `codigo`
estable** de `dbo.d2_modalidad_laboral` (mig. `000109`, 2026-09-10), resuelto con
`ModalidadLaboral::codigoDe($emp->modalidad_laboral)` (insensible a mayúsculas/tildes/espacios; el
catálogo lo puede renombrar TH sin romper esto):

| `codigo` de la modalidad | Motivos ofrecidos |
|---|---|
| `NOMBRAMIENTO_DEFINITIVO` | `INICIO_COMISION`, `FIN_COMISION_RETORNO` |
| `CONTRATO_OCASIONAL` (nombre visible: "Contrato de Servicios Ocasionales") | `DESVINCULACION` |
| `NOMBRAMIENTO_PROVISIONAL` | `DESVINCULACION` |
| `LIBRE_NOMBRAMIENTO_REMOCION` | `DESVINCULACION` |

Una modalidad cuyo `codigo` no está en el mapa (ej. `COMISION_SERVICIOS`, o una modalidad nueva
creada desde Admin) → sin motivos, salvo los de comisionado entrante si `es_comisionado_entrante`.

**Motivos adicionales para `es_comisionado_entrante = true`** (independiente de la modalidad):
`COMISION_ENTRANTE`, `FIN_COMISION_SALIDA`.

**`NUEVO_INGRESO` no existe como motivo** — un ingreso nuevo se registra creando al empleado en la
ficha con su `fecha_ingreso` y saldo 0.

### 5.2 Estado del empleado requerido por motivo (`ESTADO_REQUERIDO`)

| Motivo | Estado requerido | Requiere `fecha_salida` en la ficha | Genera certificado PDF | Carga saldo externo |
|---|---|---|---|---|
| `INICIO_COMISION` | `INACTIVO` | sí | **sí** | no |
| `FIN_COMISION_RETORNO` | `INACTIVO` | sí | no | **sí** |
| `COMISION_ENTRANTE` | `ACTIVO` | no | no | **sí** |
| `FIN_COMISION_SALIDA` | `INACTIVO` | sí | **sí** | no |
| `DESVINCULACION` | `INACTIVO` | sí | no | no |

- **RN-07.1** — TH debe actualizar la ficha del empleado (`estado`, `fecha_salida` / `fecha_ingreso`)
  **antes** de registrar el evento. `registrar()` valida el estado actual contra `ESTADO_REQUERIDO`
  → HTTP 422 si no calza; y si el motivo exige `INACTIVO`, exige además que la ficha tenga
  `fecha_salida` → HTTP 422 "no tiene fecha de salida registrada".

### 5.3 Cálculo del saldo (`calcularSaldo($emp, $fechaReferencia)`)

- **RN-07.2 (2026-09-03)** — Delega en `App\Services\SaldoVacacionesService::calcular($emp, $cabecera,
  $fechaRef)` — **fuente única** de la fórmula (spec 05 §5.3). Antes tenía su propia copia con
  `tasa = 1.25` **fija** para Código del Trabajo, que ignoraba los días adicionales por antigüedad
  del Art. 69 → un servidor CT con 6+ años se liquidaba de menos.
- **RN-07.3 (fecha de corte efectiva)** — `SaldoVacacionesService::fechaCorteEfectiva()` toma la
  **más reciente** entre: `FECHA_CORTE_VACACIONES` (global), `fecha_ingreso` (empleado nuevo), y
  **`cabecera.fecha_proceso`** (nuevo — carga de saldo puntual posterior al corte global).
- **RN-07.4** — `acumulado = round(diffInDays(fechaCorteEfectiva, fechaReferencia) / 360 × (tasa_por_antigüedad × 12), 2)`.
- **RN-07.5** — `saldo_inicial = dias_adicionales`; `tomados = total_dias_tomados` (de `d2_cabecera_vacacion`).
- **RN-07.6** — `saldo_liquidado = max(0, saldo_inicial + acumulado − tomados)`. `LiquidacionVacController`
  aplica el `max(0, …)` **pero no** el `min(60, …)` de `SaldoVacacionesService` — la liquidación
  paga/certifica el valor real acumulado (excepción al tope de 60, LOSEP Art. 29).
- **RN-07.7 (`fechaReferencia`)** — la fija el **caller**:
  - `consultar()`: `fecha_salida` si el empleado está `INACTIVO` y la tiene; si no, hoy.
  - `registrar()`: siempre `fecha_evento` del request.
- **RN-07.7b (helper `motivosDisponiblesPara($emp)`)** — un solo lugar calcula los motivos válidos
  del empleado (por modalidad + los de comisionado entrante si aplica). Lo usan **tanto**
  `consultar()` (para la UI) **como** `registrar()` (para la validación) — ver §11.2.

### 5.4 `GET /api/liquidacion/buscar` — `buscar`

- **RN-07.8** — `q` requerido (≥2). Busca empleados (`id_depto != 999`, **activos e inactivos**) por
  `identificacion` ILIKE **o** `"apellido nombre"` ILIKE. Máx. 15, ordenados por apellido.

### 5.5 `GET /api/liquidacion/{id_emp}` — `consultar`

- **RN-07.9** — Empleado con `departamento` (`id_depto != 999`, 404 si no existe). Devuelve:
  - `empleado` (datos + estado + modalidad + fechas + tipo de contrato + departamento).
  - `saldo` (shape de `calcularSaldo`, con `fecha_referencia` = `fecha_salida` o hoy).
  - `historial` (todas las filas de `vac_liquidacion_historico` del empleado, `fecha_evento` desc).
  - `motivos_disponibles` (`motivosDisponiblesPara($emp)` — por modalidad + los de comisionado entrante si aplica; RN-07.7b).
  - `motivos_carga_saldo` = `['FIN_COMISION_RETORNO', 'COMISION_ENTRANTE']` (para que la UI muestre el campo "días a cargar").

### 5.6 `POST /api/liquidacion/{id_emp}/registrar` — `registrar`

Request: `motivo*`, `fecha_evento*`, `dias_a_cargar` (nullable, `numeric|min:0`),
`observacion` (nullable, ≤500).

- **RN-07.10 (2026-09-03)** — Validación `motivo` `in:` contra `motivosDisponiblesPara($emp)` — el
  **mismo** helper que usa `consultar()`. Antes se validaba contra una lista fija
  (`array_merge(...array_values(MOTIVOS_POR_MODALIDAD))`) que nunca incluía `COMISION_ENTRANTE` ni
  `FIN_COMISION_SALIDA` → el flujo de comisionado entrante devolvía 422 al enviar (§11.2).
- **RN-07.11** — Valida el estado del empleado contra `ESTADO_REQUERIDO` (RN-07.1).
- **RN-07.12** — Calcula `saldo` con `fechaReferencia = fecha_evento`.
- **RN-07.13 (carga de saldo externo — 2026-09-03)** — si `motivo ∈ {FIN_COMISION_RETORNO,
  COMISION_ENTRANTE}`: `updateOrCreate` de `d2_cabecera_vacacion` con `dias_adicionales = dias_a_cargar`,
  `total_dias_tomados = 0`, **`fecha_proceso = fecha_evento`** (antes `now()`). Ese `fecha_proceso`
  actúa como **corte por-empleado** en `SaldoVacacionesService::fechaCorteEfectiva()` → el devengo
  posterior arranca desde la fecha del retorno, no desde el corte global. Antes el saldo del empleado
  se inflaba en `VacacionesView` porque se sumaba todo el período desde el corte global encima del
  saldo recién cargado.
  - Sigue sin actualizar `dias_x_tomar_normal` (a diferencia de la carga masiva de spec 05 §8.1) —
    menor.
- **RN-07.14** — Crea la fila en `vac_liquidacion_historico` con la fotografía del saldo,
  `usuario_proceso = user.id_emp`, `fecha_registro = now()`. HTTP 201.
- **RN-07.15** — Respuesta incluye `genera_certificado` = `motivo ∈ {INICIO_COMISION, FIN_COMISION_SALIDA}`.
- **RN-07.16 (2026-09-03)** — Registra `REGISTRAR` en `nom_auditoria_log`. Si hubo corrección de
  cabecera (`MOTIVOS_CARGA_SALDO`), guarda el estado anterior de `d2_cabecera_vacacion`
  (`dias_adicionales`, `total_dias_tomados`, `fecha_proceso`) en `datos_anteriores` y
  `cabecera_vacacion_tocada = true` + `dias_cargados` en `datos_nuevos`.

### 5.7 `GET /api/liquidacion/certificado/{historico_id}` — `generarCertificado`

- **RN-07.17 (2026-09-03)** — Solo si `historico.motivo ∈ {INICIO_COMISION, FIN_COMISION_SALIDA}` →
  si no, HTTP 422 ("el motivo … no genera certificado"). Genera el PDF `liquidacion_vacaciones.blade.php`
  (portrait A4). Firmantes desde `FIRMANTE_TH_NOMBRE` / `_CARGO` con fallback a
  `APROBADOR_INST_VACACION` / literal. Fecha en español (`isoFormat`). Nombre:
  `vacaciones_{motivo}_{cédula}_{fecha_evento}.pdf`. `->download()`.

---

## 6. UI

`LiquidacionVacView.vue` (ruta `planificacion/liquidacion`). Flujo:

1. Buscador de empleado (`/liquidacion/buscar`) → seleccionar.
2. Panel con datos del empleado + saldo calculado + historial de eventos.
3. Select de **motivo** (de `motivos_disponibles`). Si el motivo está en `motivos_carga_saldo` →
   aparece el campo "Días a cargar (certificado externo)".
4. `fecha_evento` + `observacion` → **Registrar** (`/liquidacion/{id_emp}/registrar`).
5. Si `genera_certificado` → botón para descargar el PDF (`/liquidacion/certificado/{id}`).

---

## 7. Flujos típicos

| Caso | Pasos |
|---|---|
| **Servidor propio sale a comisión** | TH: ficha → `INACTIVO` + `fecha_salida`. Módulo → `INICIO_COMISION` → registrar → descargar certificado. El servidor lo lleva a la institución destino. |
| **Servidor propio regresa de comisión** | TH: ficha → `ACTIVO` + `fecha_ingreso` + `motivo_reactivacion`. Módulo → `FIN_COMISION_RETORNO` → ingresar días del certificado externo → registrar (carga `dias_adicionales`). |
| **Servidor viene de otra institución en comisión** | TH: crea empleado con `modalidad_laboral = Comisión de Servicios`, `es_comisionado_entrante = true`, `ACTIVO` + `fecha_ingreso`. Módulo → `COMISION_ENTRANTE` → días del certificado externo → registrar. |
| **Servidor comisionado se va a su institución de origen** | TH: ficha → `INACTIVO` + `fecha_salida`. Módulo → `FIN_COMISION_SALIDA` → registrar → descargar certificado. |
| **Desvinculación (contrato ocasional / provisional)** | TH: ficha → `INACTIVO` + `fecha_salida`. Módulo → `DESVINCULACION` → registrar (solo constancia). |

---

## 8. Criterios de aceptación

- **CA-07-1** — Un usuario sin rol Admin/TH que llama cualquier endpoint → HTTP 403.
- **CA-07-2** — `consultar` de un empleado `INACTIVO` con `fecha_salida = 2026-06-30` → el `acumulado` se calcula hasta el 30/06, no hasta hoy.
- **CA-07-3** — `registrar` `INICIO_COMISION` sobre un empleado `ACTIVO` → HTTP 422 (exige `INACTIVO`).
- **CA-07-4** — `registrar` `DESVINCULACION` sobre un empleado `INACTIVO` **sin** `fecha_salida` en la ficha → HTTP 422.
- **CA-07-5** — `registrar` `FIN_COMISION_RETORNO` con `dias_a_cargar = 12` → `d2_cabecera_vacacion` queda con `dias_adicionales = 12`, `total_dias_tomados = 0`; la fila del histórico tiene `saldo_liquidado = 12`.
- **CA-07-6** — `registrar` `INICIO_COMISION` → respuesta con `genera_certificado = true`; `generarCertificado` de ese histórico devuelve un PDF. `generarCertificado` de un histórico `DESVINCULACION` → HTTP 422.
- **CA-07-7** — La fila de `vac_liquidacion_historico` guarda `fecha_corte_usada` y `usuario_proceso`, y `registrar` deja un registro `REGISTRAR` en `nom_auditoria_log`.
- **CA-07-7b (2026-09-03)** — `registrar` `COMISION_ENTRANTE` sobre un empleado `ACTIVO` con `es_comisionado_entrante = true` y `dias_a_cargar = 10` → se crea el histórico (ya no da 422 de validación) y `d2_cabecera_vacacion` queda con `fecha_proceso = fecha_evento`.
- **CA-07-7c (2026-09-03)** — Un empleado CT con 8 años que se desvincula → `acumulado` usa la tasa de 18 días/año (15 + 3 por antigüedad), no 15.
- **CA-07-8** — Un empleado LOSEP con `FECHA_CORTE_VACACIONES` hace 360 días y `dias_adicionales = 0`, `tomados = 0` → `saldo_liquidado ≈ 30`.
- **CA-07-9** — Un `saldo_liquidado` calculado en negativo (tomados > devengado + adicionales) se guarda como `0`.
- **CA-07-10** — Tras un `FIN_COMISION_RETORNO`, el kardex de saldo (spec 05) del empleado arranca desde `fecha_evento` de ese registro.

---

## 9. Casos borde

| Caso | Comportamiento |
|---|---|
| Empleado `CODIGO DEL TRABAJO` con 10 años de servicio | `calcularSaldo` (vía `SaldoVacacionesService`) usa la tasa por antigüedad (2026-09-03). |
| `registrar` con `motivo = COMISION_ENTRANTE` / `FIN_COMISION_SALIDA` para un `es_comisionado_entrante` | Se acepta (2026-09-03) — validación contra `motivosDisponiblesPara($emp)`. |
| `FIN_COMISION_RETORNO` sin `dias_a_cargar` | `dias_a_cargar` = `0` → `dias_adicionales = 0`, `saldo_liquidado = 0`. |
| Empleado que regresó de comisión: su saldo en `VacacionesView` (spec 05) | Correcto (2026-09-03) — `cabecera.fecha_proceso = fecha_evento` actúa como corte por-empleado en `SaldoVacacionesService`. |
| `generarCertificado` de un histórico `DESVINCULACION` | HTTP 422 (2026-09-03). |
| `modalidad_laboral` con capitalización distinta (`Nombramiento Definitivo` vs. la clave `Nombramiento definitivo`) | OK — la comparación es `mb_strtolower`. |
| `modalidad_laboral` no reconocida (p. ej. vacía) | `motivos_disponibles = []` (solo se agregan los de comisionado entrante si aplica). |
| Dos eventos del mismo motivo para el mismo empleado | Se permiten — el histórico acumula ambas filas. |

---

## 10. Dependencias

- **Spec 00** — roles, `d2_configuracion` (`FECHA_CORTE_VACACIONES`, `FIRMANTE_TH_*`, `APROBADOR_INST_VACACION`).
- **Spec 01** — `ad_empleado` (`estado`, `fecha_salida`, `fecha_ingreso`, `modalidad_laboral`,
  `tipo_contrato`, `es_comisionado_entrante`); TH actualiza la ficha **antes** de registrar el evento.
- **Spec 05 (Vacaciones)** — comparte `d2_cabecera_vacacion` y `SaldoVacacionesService`;
  `vac_liquidacion_historico` alimenta el kardex (`buildKardex`); esta liquidación es la **única**
  que usa el saldo real **sin** tope de 60. **Cambio compartido (2026-09-03):** al mover
  `LiquidacionVacController` al servicio, se agregó `fechaCorteEfectiva()` (considera
  `cabecera.fecha_proceso`) — afecta a los **5** consumidores del servicio (Vacaciones, Reporte,
  Permisos, Planificación, Liquidación). Es retrocompatible (para casi todos, `fecha_proceso ==`
  corte global o `fecha_ingreso`), pero **pendiente de prueba de humo** en los otros 4 flujos antes
  de darlo por cerrado en producción (anotado en CLAUDE.md).
- **Módulo Comisiones** — `Comisiones\LiquidacionController` / `com_ficha_liquidacion` es la
  liquidación **financiera de viáticos**, no relacionada con este módulo pese al nombre.
- **Spec 12 (Auditoría)** — `registrar` registra `REGISTRAR` en `nom_auditoria_log` (2026-09-03).

---

## 11. Deuda técnica / hallazgos

> **Los hallazgos 1, 2, 3, 5 y 6 se corrigieron el 2026-09-03** (ver CLAUDE.md §"Liquidación de
> Vacaciones"). Sin cambios de schema.

1. ✅ **RESUELTO (2026-09-03)** — `calcularSaldo` delega en `SaldoVacacionesService` (la 5ª copia
   eliminada). Tasa de Código del Trabajo correcta con antigüedad (Art. 69).
2. ✅ **RESUELTO (2026-09-03)** — `registrar()` valida `motivo` contra `motivosDisponiblesPara($emp)`
   — el mismo helper que usa `consultar()`. `COMISION_ENTRANTE` / `FIN_COMISION_SALIDA` ahora se
   aceptan para `es_comisionado_entrante = true`.
3. ✅ **RESUELTO (2026-09-03)** — La carga de saldo externo fija `cabecera.fecha_proceso = fecha_evento`,
   que `SaldoVacacionesService::fechaCorteEfectiva()` usa como **corte por-empleado** — el devengo
   posterior arranca desde la fecha del retorno, no desde el corte global. Ya no se infla el saldo en
   `VacacionesView`. *(Sigue sin mover `dias_x_tomar_normal` — menor.)*
4. **`esAdminOTH` inline** en los 4 métodos en vez del helper `requireRole` de `Controller.php`.
   Funcionalmente equivalente; inconsistente con el patrón. *(abierto — menor)*
5. ✅ **RESUELTO (2026-09-03)** — `registrar` registra `REGISTRAR` en `nom_auditoria_log`, con el
   estado anterior de `d2_cabecera_vacacion` cuando hubo carga de saldo externo.
6. ✅ **RESUELTO (2026-09-03)** — `generarCertificado` responde HTTP 422 si el motivo no es
   `INICIO_COMISION` / `FIN_COMISION_SALIDA`.
7. **`saldo_liquidado` con piso 0 pero sin tope 60** — correcto para el pago, pero para
   `DESVINCULACION` de un servidor con más de 60 días acumulados conviene confirmar con la norma si
   se paga todo lo acumulado o hay un límite. *(abierto — pregunta de negocio)*
8. **Prueba de humo pendiente en producción** — el cambio compartido a
   `SaldoVacacionesService::fechaCorteEfectiva()` afecta a los 5 consumidores del servicio; validado
   solo por código, no en vivo. Pasada de humo recomendada en Vacaciones / Reporte de saldo /
   Permisos / Planificación (anotado en CLAUDE.md).

---

## 12. Preguntas abiertas

- ~~¿Migrar el cálculo / arreglar la validación de motivos / la fecha de corte por-empleado / auditar / restringir `generarCertificado`?~~ ✅ los 5 hechos el 2026-09-03 (§11.1–3, 5–6).
- Pasada de humo en producción de los 4 flujos que comparten `SaldoVacacionesService` (§11.8).
- ¿Hay un tope legal para el pago de `DESVINCULACION` con > 60 días acumulados? (§11.7)
- ¿La carga de saldo externo debería también actualizar `dias_x_tomar_normal`? (§11.3, menor)
- ¿`Libre Nombramiento y Remoción` es una modalidad real en producción, o sobra en `MOTIVOS_POR_MODALIDAD`?
