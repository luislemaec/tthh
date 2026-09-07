# Spec 09 — Nómina (D13 / D14 / Fondos de Reserva / Rol de Pagos)

| | |
|---|---|
| **Versión** | 1.0 (borrador) |
| **Última actualización** | 2026-09-07 |
| **Depende de** | [00 — Paraguas](00-modulo-talento-humano.md), [01 — Empleados](01-empleados.md) |
| **Controladores** | `app/Http/Controllers/NominaController.php` (D13/D14/FR/SBU/Consolidado), `app/Http/Controllers/RolPagoController.php`, `app/Http/Controllers/Admin/AportesIessController.php` |
| **Modelos** | `App\Models\Nomina\{DecimoTercero,DecimoCuarto,FondosReserva,SbuHistorico,RolPago,RolPagoDet}`, `App\Models\AportesIess` |
| **Vistas** | `views/nomina/{DecimosView,FondosReservaView,RolPagoView}.vue`, `views/admin/SbuView.vue`, `views/admin/aportes/AportesIessView.vue` |
| **PDF** | `reportes/nom_{decimo_tercero,decimo_cuarto,consolidado,fondos_reserva,rol_pago,rol_pago_resumenes}.blade.php` |
| **Tipo** | Retro-especificación |

---

## 1. Propósito

Calcular y cerrar, mes a mes, los cuatro artefactos de nómina del servidor público ecuatoriano:

- **Décimo Tercero (D13)** y **Décimo Cuarto (D14)** — beneficios adicionales de ley, proporcionales
  a los días trabajados en el mes.
- **Fondos de Reserva (FR)** — solo para empleados con ≥1 año de antigüedad.
- **Rol de Pagos** — el artefacto mensual completo: RMU, aportes IESS (patronal/personal/IECE/SECAP),
  descuentos manuales (quirografario, hipotecario, impuesto a la renta, SUPA, póliza blanket,
  sanciones, otros) y **líquido a pagar**.

Los 4 comparten el mismo ciclo: **Calcular → BORRADOR (editable/recalculable) → Cerrar → CERRADO**
(irreversible salvo Rol de Pagos, que desde 2026-09-07 tiene `reabrir` — ver §5.7 y §11.5).

### 1.1 Qué NO es

- **No** traslada el valor de Horas Extras calculado en spec 08 — ese valor se calcula al vuelo en
  `HorasExtrasController::equipoHoras()` y no fluye automáticamente al Rol de Pagos (gap de feature
  documentado en spec 08 §11.9, no en esta spec).
- **No** es la liquidación de viáticos de Comisiones de Servicio (`Comisiones\LiquidacionController`).
- **No** genera el pago real (transferencia bancaria) — solo calcula el valor y lo deja listo para que
  Tesorería/Contabilidad lo procese fuera del sistema.

---

## 2. Actores

| Actor | Puede |
|---|---|
| `TH NOMINA` / `ADMINISTRADOR` | Calcular, editar (mientras BORRADOR), cerrar y reabrir (solo Rol de Pagos) los 4 artefactos; gestionar el SBU anual; ver la auditoría de nómina. |
| `TALENTO HUMANO` | Solo el catálogo `Admin/AportesIess` (tasas IESS) — ampliado a propósito el 2026-08-17 porque en la práctica también lo usa TH, no solo TH NOMINA (ver CLAUDE.md). **No** tiene acceso a calcular/cerrar/reabrir ningún artefacto de nómina. |

Los 3 controladores comparten la misma pregunta ("¿es TH NOMINA o Admin?") pero **la resuelven con 2
patrones distintos** — ver §11.2:
- `NominaController` (D13/D14/FR/SBU/consolidado, 14 métodos) — inline `esNominaOAdmin($id_emp)`,
  100% consistente **dentro** del archivo.
- `RolPagoController` — desde 2026-09-07 usa `requireRole($request, self::ROLES_NOMINA)` de forma
  consistente en los 8 métodos (antes `calcular`/`cerrar` eran inline, el resto ya usaba
  `requireRole` — mezcla corregida).
- `Admin/AportesIessController` — `requireRole()` con `ROLES_NOMINA` de 3 roles (incluye
  `TALENTO HUMANO`, intencional).

---

## 3. Historias de usuario

- **HU-09.1** — Como TH NOMINA, quiero calcular el D13/D14/FR de un mes con un clic, sabiendo que se
  aplican correctamente los días proporcionales de quien ingresó a mitad de mes.
- **HU-09.2** — Como TH NOMINA, quiero cerrar un período para que quede como registro histórico
  inmutable... salvo que necesite corregir algo después (ver HU-09.6).
- **HU-09.3** — Como TH NOMINA, quiero calcular el Rol de Pagos completo del mes, con los aportes
  IESS correctos según el tipo de contrato de cada empleado.
- **HU-09.4** — Como TH NOMINA, quiero editar manualmente los descuentos (sanciones, préstamos,
  impuesto a la renta) de un empleado antes de cerrar el rol, o cargarlos en lote por CSV.
- **HU-09.5** — Como Contraloría / auditor interno, quiero ver quién calculó, editó y cerró cada
  período de nómina, y en particular quién tocó los descuentos manuales del líquido a pagar de cada
  empleado.
- **HU-09.6 (2026-09-07)** — Como ADMINISTRADOR/TH NOMINA, quiero poder reabrir un Rol de Pagos ya
  CERRADO cuando se detecta un error después del cierre, dejando constancia de por qué se reabrió.
- **HU-09.7 (2026-09-07)** — Como TH NOMINA, quiero que el sistema me avise explícitamente si algún
  empleado quedó sin tasa IESS asignada al calcular el rol, en vez de que su aporte quede en 0% sin
  que nadie lo note.

---

## 4. Modelo de datos

### 4.1 `dbo.nom_decimo_tercero` / `dbo.nom_decimo_cuarto` (estructura idéntica)

| Campo | Contenido |
|---|---|
| `anio`, `mes`, `id_emp` | — |
| `sueldo_base` (D13) / `sbu` (D14) | Base usada para el cálculo (sueldo del empleado / SBU del año). |
| `dias` | Días proporcionales del mes (`calcularDiasEnMes()`, §5.1). |
| `valor` | `base / 12 / 30 × dias`, redondeado a 2 decimales. |
| `estado` | `BORRADOR` → `CERRADO`. |
| `creado_por`, `fecha_calculo` | Alta. |
| `cerrado_por`, `fecha_cierre` | Cierre. |

Únicos por `(anio, mes, id_emp)` (aplicado por el propio flujo de cálculo — borra los BORRADOR
previos antes de reinsertar, no hay constraint `unique` explícita en la migración).

### 4.2 `dbo.nom_fondos_reserva`

Igual que D13/D14, más: `porcentaje` (fijo 8.33), `tipo` (`MENSUAL` si `acumula_fondos_reserva=1`,
`IESS` si `=2`).

### 4.3 `dbo.nom_sbu_historico`

`anio` (único), `valor`, `usuario_registro`. Fuente del SBU usado en D14. Gestionado desde
`Admin → SBU` (`views/admin/SbuView.vue`), **no** desde la pantalla de Décimo Cuarto.

### 4.4 `dbo.d2_aportes_iess`

| Campo | Contenido |
|---|---|
| `id_aporte` PK, `modalidad` | Debe calzar **exacto** (tras `trim()`) con `ad_empleado.tipo_contrato` — valores reales: `LOSEP`, `CODIGO DEL TRABAJO`. |
| `aporte_individual`, `aporte_patronal` | % IESS. |
| `iece_patronal`, `iece_personal`, `secap_patronal`, `secap_personal` | % IECE/SECAP (migración `000061`; antes existían como filas separadas — ver CLAUDE.md §Nómina). |
| `fecha_desde`, `fecha_hasta` (nullable) | Vigencia — permite cambiar tasas en el tiempo sin perder el histórico. |
| `created_by`/`updated_by` (migración `000062`) | Auditoría de campo. |

`tasasVigentes()` (duplicado idéntico en `RolPagoController`, no compartido — §11.6): trae las filas
con `fecha_hasta IS NULL OR fecha_hasta >= hoy`, ordena por `fecha_desde` desc y se queda con **una
sola fila por `modalidad`** (`->unique('modalidad')`) — es decir, la vigente más reciente si hay
varias.

### 4.5 `dbo.nom_rol_pago_cab` / `dbo.nom_rol_pago_det`

| Tabla | Campos relevantes |
|---|---|
| `nom_rol_pago_cab` | `anio`, `mes` (`unique` juntos), `estado` (`BORRADOR`/`CERRADO`), 5 totales (`total_empleados`, `total_bruto`, `total_patronal`, `total_descuentos`, `total_liquido`), `creado_por`/`fecha_calculo`, `cerrado_por`/`fecha_cierre`. |
| `nom_rol_pago_det` | `cab_id`+`id_emp` (`unique` juntos), `tipo_contrato`, `rmu_puesto`, `dias`, `valor_rmu`, `aporte_patronal_pct`/`aporte_patronal`, `aporte_personal_pct`/`aporte_personal`, `iece_pct`/`iece`, `secap_pct`/`secap` (migración `000060`), 7 descuentos manuales (`quirografario`, `hipotecario`, `impuesto_renta`, `supa`, `poliza_blanket`, `sanciones`, `otros_descuentos` — migraciones `000067`/`000068`), `observaciones`, `total_descuentos`, `liquido`, `programa`/`actividad` (copiados del empleado al calcular). |

> **Nota (menor) — modelos Eloquent sin usar y desactualizados.** `App\Models\Nomina\RolPago` y
> `RolPagoDet` existen pero **`RolPagoController` no los usa en ningún método** — todo el
> controlador trabaja con `DB::table()` crudo. Sus `$fillable` además están desactualizados (no
> incluyen `iece`, `secap`, `poliza_blanket`, `sanciones`, `otros_descuentos`, `observaciones`,
> `programa`, `actividad` — todos campos reales de la tabla). No es un bug activo (no se usan), pero
> si algo en el futuro intenta usarlos vía Eloquent con esos campos, el `update()`/`create()` los
> descartaría en silencio (mismo patrón de riesgo que el `devuelto_count` de spec 08 §11.2). *(abierto
> — no era parte del alcance de hoy)*

---

## 5. Reglas de negocio

### 5.1 Días proporcionales (`calcularDiasEnMes($fechaIngreso, $anio, $mes)`)

- **RN-09.1** — Sin `fecha_ingreso` → 30 días (mes completo).
- **RN-09.2** — Ingreso después del último día del mes → 0 días (excluido del cálculo).
- **RN-09.3** — Ingreso antes del primer día del mes → 30 días.
- **RN-09.4** — Ingreso dentro del mes → `max(1, 30 - día_ingreso + 1)` (base 30 días/mes, no el real del mes calendario).
- Implementada **de forma idéntica** en `NominaController` y `RolPagoController` (copias
  independientes, mismo código) — funciona igual en ambos, es una duplicación menor, no un bug.
  *(abierto — no era parte del alcance de hoy; candidato a extraerse a un helper/trait compartido)*

### 5.2 Décimo Tercero (`NominaController::calcular13`/`cerrar13`/`pdf13`)

- **RN-09.5** — Empleados: `estado=ACTIVO`, `acumula_decimo_tercero=false` (cobra mensualmente, no
  acumulado), `id_depto != 999`, con `fecha_ingreso` ≤ último día del mes (o sin `fecha_ingreso`).
- **RN-09.6** — `valor = round(sueldo / 12 / 30 × dias, 2)`.
- **RN-09.7** — No se puede recalcular si ya existe un registro `CERRADO` para `(anio, mes)` → 422.
  Recalcular borra los `BORRADOR` previos de ese `(anio, mes)` y reinserta (sin `DB::transaction()` —
  mismo tipo de riesgo que tenía Rol de Pagos antes de la corrección de hoy, ver §11.3, pero **no**
  fue parte del alcance corregido — D13/D14/FR insertan con `Model::create()` fila por fila igual
  que antes tenía Rol de Pagos).
- **RN-09.8** — `cerrar13` exige que existan registros `BORRADOR` para ese período (422 si 0);
  marca todos `CERRADO` con `cerrado_por`/`fecha_cierre`. **Sin flujo de reapertura** (§11.5).
- **RN-09.9** — Auditoría: `CALCULAR` (con `total_empleados`) y `CERRAR` (con `total_empleados`) en
  `dbo.nom_decimo_tercero`, `registro_id = 0` (evento de todo el período, no de una fila).

### 5.3 Décimo Cuarto (`calcular14`/`cerrar14`/`pdf14`)

- **RN-09.10** — Igual que D13 pero: filtra por `acumula_decimo_cuarto=false`; usa el **SBU del año**
  (`nom_sbu_historico`) en vez del sueldo — si no existe SBU para ese año, 422 explícito
  ("Ingrese el SBU primero").
- **RN-09.11** — `valor = round(sbu / 12 / 30 × dias, 2)`.
- Mismas reglas de cierre/auditoría que D13 (RN-09.8/9.9), sobre `dbo.nom_decimo_cuarto`.

### 5.4 Consolidado D13+D14 (`consolidado`/`pdfConsolidado`)

- **RN-09.12** — Solo lectura: une por `id_emp` los registros de D13 y D14 del mismo `(anio, mes)`
  (`keyBy('id_emp')` + `merge` de las claves), muestra `valor_13`, `valor_14`, `total`, y el
  `estado` de cada uno por separado (pueden estar en estados distintos si se calculó/cerró uno sin
  el otro). No audita (es una vista derivada, no un cálculo propio).

### 5.5 Fondos de Reserva (`calcularFR`/`cerrarFR`/`pdfFR`)

- **RN-09.13** — Empleados: `estado=ACTIVO`, `acumula_fondos_reserva IN (1,2)`, `id_depto != 999`,
  **y `fecha_ingreso` ≤ primer día del mes − 12 meses** (solo quienes ya cumplieron el año).
- **RN-09.14** — `valor = round(sueldo × 8.33 / 100 / 30 × dias, 2)`; `tipo = 'MENSUAL'` si
  `acumula_fondos_reserva=1`, `'IESS'` si `=2`.
- Mismas reglas de cierre/auditoría que D13 (RN-09.8/9.9), sobre `dbo.nom_fondos_reserva`. Filtro
  opcional `tipo` en el PDF.

### 5.6 Tasas de aporte IESS (`Admin/AportesIessController`)

- **RN-09.15** — `store()` **cierra automáticamente** la fila vigente anterior de la misma
  `modalidad` (`fecha_hasta = fecha_desde_nueva - 1 día`) antes de crear la nueva — así nunca hay dos
  filas vigentes (`fecha_hasta IS NULL`) para la misma modalidad al mismo tiempo. Envuelto en
  `DB::transaction()`.
- **RN-09.16** — Roles: `ADMINISTRADOR`, `TH NOMINA`, **y `TALENTO HUMANO`** (ampliado 2026-08-17,
  documentado en CLAUDE.md — TH también gestiona este catálogo en la práctica).

### 5.7 Rol de Pagos (`RolPagoController`)

#### 5.7.1 Calcular (`POST /rol-pago/calcular`)

- **RN-09.17** — Rechaza si ya existe un `CERRADO` para `(anio, mes)` → 422.
- **RN-09.18 (2026-09-07, antes sin transacción)** — Todo el cálculo (borrar el `BORRADOR` anterior
  si existía + insertar la cabecera + insertar cada detalle) corre dentro de **una sola
  `DB::transaction()`**. Antes, si el proceso se cortaba a mitad del loop (timeout, caída de
  conexión), quedaba un rol de pagos a medias — cabecera creada, solo algunos empleados insertados —
  sin ninguna forma de saberlo, y la cabecera ya insertada bloqueaba un reintento limpio (violaría
  `unique(anio, mes)`).
- **RN-09.19** — Trae todos los `ACTIVO`, `id_depto != 999`. Por cada uno: `dias` (RN-09.4),
  `valor_rmu = round(sueldo × dias / 30, 2)`.
- **RN-09.20 (tasa IESS por `tipo_contrato`)** — busca en `tasasVigentes()` (§4.4) por
  `trim(tipo_contrato)`. `aporte_patronal = round(valor_rmu × aporte_patronal_pct / 100, 2)`; ídem
  `aporte_personal`, `iece`, `secap`. `total_descuentos` inicial = `aporte_personal` (los demás
  descuentos manuales entran en 0, se editan después vía `updateDetalle`/`importar`).
  `liquido = round(valor_rmu - total_descuentos, 2)`.
- **RN-09.21 (2026-09-07 — antes silencioso)** — Si `tipo_contrato` **no calza** con ninguna
  `modalidad` vigente (typo, valor vacío, o una modalidad sin fila en `d2_aportes_iess`), el
  empleado se sigue insertando (**no se bloquea el rol completo por un solo empleado**), pero con
  todos los porcentajes en `0` → `aporte_patronal=aporte_personal=iece=secap=0` y
  `liquido = valor_rmu` completo, **sin ningún descuento**. Antes esto no se comunicaba en ningún
  lado. Ahora: el controlador recolecta estos casos en `sin_tasa[]` (`id_emp`, `nombre`,
  `tipo_contrato`) y los devuelve en la respuesta de `calcular()`; el frontend (`RolPagoView.vue`)
  muestra un banner ámbar con la lista antes de que TH NOMINA cierre el período. También queda en la
  auditoría (`sin_tasa: N` en `datos_nuevos` de `CALCULAR`, y en la descripción si `N > 0`).
- **RN-09.22 (2026-09-07)** — `calcular()` audita `CALCULAR` en `dbo.nom_rol_pago_cab` (antes de
  hoy, **ningún** método de `RolPagoController` auditaba nada — 0 coincidencias con
  `AuditoriaService`, a diferencia de D13/D14/FR).

#### 5.7.2 Editar detalle (`PUT /rol-pago/detalle/{id}` — `updateDetalle`)

- **RN-09.23** — Solo si `cab.estado = 'BORRADOR'` (422 si no). Recibe los 7 descuentos manuales +
  `observaciones`; recalcula `total_descuentos = aporte_personal + suma(7 descuentos)` y `liquido`.
- **RN-09.24 (2026-09-07)** — **Audita `ACTUALIZAR_DETALLE`** con el antes/después de los 7
  descuentos + `liquido` — antes esta era la edición más sensible del módulo (ajustar sanciones o
  impuesto a la renta de un empleado específico, tocando directamente su líquido a pagar) sin dejar
  ninguna traza de quién cambió qué.

#### 5.7.3 Importar CSV (`POST /rol-pago/importar`)

- **RN-09.25** — Solo sobre un `cab` en `BORRADOR` para `(anio, mes)`. Por fila: busca por `cedula`
  (`identificacion`), si no existe el empleado o no tiene detalle en ese rol → se reporta en
  `no_encontrados` (no bloquea el resto del archivo). Actualiza los descuentos presentes en la fila
  (los ausentes conservan el valor que ya tenía el detalle).
- **RN-09.26 (2026-09-07)** — **Audita `IMPORTAR`** (conteo de `actualizados`/`no_encontrados`) —
  antes una carga masiva de descuentos de decenas de empleados no dejaba ningún rastro.

#### 5.7.4 Cerrar (`POST /rol-pago/cerrar`)

- **RN-09.27** — Exige un `cab` en `BORRADOR` para `(anio, mes)` → `CERRADO` + `cerrado_por` +
  `fecha_cierre`. **Audita `CERRAR`** (ya auditaba antes de hoy — no era parte del hallazgo, pero se
  unificó el chequeo de rol a `requireRole()`, ver RN-09.29).

#### 5.7.5 Reabrir (`POST /rol-pago/reabrir` — nuevo, 2026-09-07)

- **RN-09.28** — Antes **no existía ningún flujo de reapertura**: una vez `CERRADO`, el único
  remedio ante un error detectado después del cierre era corregirlo a mano en la base de datos
  (riesgoso y sin traza). Nuevo endpoint: exige `anio`, `mes`, y **`observacion` obligatoria**
  (≤300, la justificación de por qué se reabre); requiere que exista un `cab` en `CERRADO` para ese
  `(anio, mes)` (422 si no); lo pasa a `BORRADOR` (**sin** limpiar `cerrado_por`/`fecha_cierre` — quedan
  como constancia histórica de que existió un cierre previo, se sobrescriben en el próximo cierre).
  **Sin columnas nuevas** — la traza de la reapertura (quién, cuándo, por qué) vive enteramente en
  `nom_auditoria_log` (`REABRIR`, con el estado/cierre anterior en `datos_anteriores` y la
  observación en `datos_nuevos`), igual que el resto del módulo.
  - Mismos roles que pueden cerrar (`ROLES_NOMINA` — `ADMINISTRADOR`/`TH NOMINA`), no una lista más
    restringida — consistente con que son los mismos roles responsables de todo el ciclo.
  - Una vez reabierto, el rol vuelve a comportarse como cualquier `BORRADOR`: `updateDetalle()` ya
    lo acepta, y `calcular()` puede recalcularlo desde cero (perdiendo cualquier edición manual ya
    hecha — mismo riesgo que ya existe hoy para cualquier `BORRADOR`, no es nuevo de `reabrir`).
  - **D13/D14/FR no tienen un `reabrir` equivalente** — quedan con el mismo gap. Se priorizó Rol de
    Pagos por ser el artefacto más sensible (líquido a pagar + ediciones manuales de descuentos);
    D13/D14/FR son cálculos puramente aditivos sin edición manual, menor riesgo de necesitar
    corrección post-cierre. *(abierto — §11.5)*

#### 5.7.6 Chequeo de rol unificado (2026-09-07)

- **RN-09.29** — `calcular()` y `cerrar()` usaban un `esNominaOAdmin($id_emp)` **inline** (con su
  propia consulta a `admin_usuario_rol`/`admin_rol`), mientras `index()`, `updateDetalle()`,
  `importar()`, `resumenes()`, `pdfResumenes()`, `pdf()` ya usaban `requireRole()` — dos patrones
  mezclados **dentro del mismo controlador**. Unificado: los 8 métodos (+`reabrir()`) usan
  `requireRole($request, self::ROLES_NOMINA)`; el helper `esNominaOAdmin()` (que ya no lo usa nadie
  en este archivo) se eliminó.

---

## 6. UI

- **`DecimosView.vue`** — 3 tabs: Décimo Tercero / Décimo Cuarto / Consolidado. Cada tab: selector
  `mes`/`año`, botón Calcular (deshabilitado si `CERRADO`), tabla de registros, botón Cerrar, botón
  PDF.
- **`FondosReservaView.vue`** — mismo patrón, con filtro adicional `tipo` (MENSUAL/IESS/Todos).
- **`RolPagoView.vue`** — selector mes/año, botones **Calcular**, **Importar CSV** (solo BORRADOR),
  **Cerrar Período** (solo BORRADOR), **Reabrir Período** (solo CERRADO, 2026-09-07 — abre modal
  pidiendo la justificación obligatoria), **Generar PDF**. Tarjetas de totales (servidores, RMU,
  descuentos, líquido). Tabla de detalle con columnas editables inline (click para editar, solo si
  `BORRADOR`): quirografario, hipotecario, impuesto renta, SUPA, póliza blanket, sanciones, otros
  descuentos, observaciones. Tab de Resúmenes agrupados por Programa/Actividad + PDF. **Banner ámbar
  de "sin tasa IESS"** (2026-09-07): aparece tras `calcular()` si algún empleado quedó con aporte 0%
  por no calzar `tipo_contrato` contra el catálogo, con la lista de empleados afectados.
- **`views/admin/SbuView.vue`** — CRUD del SBU por año (usado por D14).
- **`views/admin/aportes/AportesIessView.vue`** — CRUD de `d2_aportes_iess`, muestra las 4 columnas
  IECE/SECAP/patronal/personal.

---

## 7. Flujos típicos

| Caso | Pasos |
|---|---|
| **Cierre mensual normal** | TH NOMINA: Calcular D13/D14/FR/Rol de Pagos del mes → revisar/editar descuentos del Rol → Cerrar los 4. |
| **Error detectado después de cerrar el Rol de Pagos** | ADMINISTRADOR/TH NOMINA: `Reabrir Período` con justificación → corrige el detalle o recalcula → vuelve a `Cerrar`. |
| **Empleado con `tipo_contrato` mal escrito** | Al Calcular, aparece en el banner "sin tasa IESS" → TH NOMINA corrige el campo en la ficha del empleado (spec 01) → recalcula. |
| **Carga masiva de descuentos** | TH NOMINA arma un CSV `cedula, quirografario, hipotecario, impuesto_renta, poliza_blanket, sanciones, otros_descuentos` → `Importar CSV` sobre el `BORRADOR` del mes. |

---

## 8. Criterios de aceptación

**D13 / D14 / FR**
- **CA-09-1** — `calcular13` de un empleado que ingresó el 15 del mes → `dias = 16`.
- **CA-09-2** — `calcular14` sin SBU registrado para el año → HTTP 422, mensaje explícito.
- **CA-09-3** — `calcularFR` excluye a un empleado con `fecha_ingreso` hace 11 meses (no cumplió el año).
- **CA-09-4** — `cerrar13`/`cerrar14`/`cerrarFR` de un período ya `CERRADO` → sin efecto duplicado (0 registros `BORRADOR` → 422).
- **CA-09-5** — Auditoría: cada `calcular*`/`cerrar*` deja `CALCULAR`/`CERRAR` en `nom_auditoria_log`.

**Rol de Pagos**
- **CA-09-6** — `calcular` de un mes con un `CERRADO` existente → HTTP 422, no se borra nada.
- **CA-09-7** — Un empleado LOSEP recibe `aporte_patronal_pct`/`aporte_personal_pct` de la fila
  vigente `modalidad='LOSEP'`; uno con `tipo_contrato` vacío o no reconocido recibe 0% en los 4
  porcentajes y aparece en `sin_tasa` de la respuesta.
- **CA-09-8** — `updateDetalle` sobre un `cab` `CERRADO` → HTTP 422 (no se puede editar).
- **CA-09-9 (2026-09-07)** — `updateDetalle` que cambia `sanciones` deja un registro
  `ACTUALIZAR_DETALLE` en `nom_auditoria_log` con el valor anterior y nuevo de los 7 descuentos.
- **CA-09-10 (2026-09-07)** — `reabrir` sobre un período `BORRADOR` (no `CERRADO`) → HTTP 422.
  `reabrir` sin `observacion` → HTTP 422 (validación). `reabrir` exitoso → `estado` vuelve a
  `BORRADOR`, queda auditado `REABRIR`, y `updateDetalle`/`calcular` vuelven a funcionar sobre ese
  período.
- **CA-09-11 (2026-09-07)** — `calcular`/`cerrar` llamados por un usuario sin rol `ADMINISTRADOR`/
  `TH NOMINA` → HTTP 403 (antes de hoy, el mensaje difería del resto del controlador pero el
  resultado ya era 403 — el fix unificó el *mecanismo*, no cambió el resultado observable).
- **CA-09-12 (2026-09-07)** — Si el proceso de `calcular` se interrumpe (simulado forzando una
  excepción a mitad del loop en pruebas), no debe quedar ninguna cabecera ni detalle a medias —
  `DB::transaction()` revierte todo.

---

## 9. Casos borde

| Caso | Comportamiento |
|---|---|
| Empleado con `acumula_decimo_tercero=true` (cobra acumulado, no mensual) | Excluido de D13 — solo se le paga en la liquidación por desvinculación (spec 07), no mes a mes. |
| `tipo_contrato` con espacios extra (`" LOSEP "`) | `trim()` en ambos lados (`tipasVigentes()` y `modalidad` del empleado) — calza igual. |
| Dos filas vigentes para la misma `modalidad` en `d2_aportes_iess` (no debería pasar por RN-09.15, pero si pasa por corrección manual en BD) | `tasasVigentes()` se queda con la de `fecha_desde` más reciente (`orderByDesc + unique`). |
| `reabrir` de un período que nunca se cerró (solo existe en `BORRADOR`) | HTTP 422 — "No existe un período CERRADO para este mes/año". |
| `importar` con una cédula que no tiene fila en `nom_rol_pago_det` para ese `cab` (ej. empleado inactivo al momento del CSV) | Reportado en `no_encontrados`, no falla el resto del archivo. |
| Empleado activo el día de `calcular` pero que estuvo `INACTIVO` parte del mes | No hay lógica de "días activo" — solo se filtra por `estado=ACTIVO` **al momento del cálculo**; si ingresó/reactivó a mitad de mes, los días proporcionales sí lo cubren (RN-09.4), pero un empleado que **se desactivó** a mitad de mes y luego alguien recalcula, ya no aparece en absoluto (no hay una versión "proporcional a la baja"). *(no evaluado como bug — comportamiento no cubierto por los 5 hallazgos de hoy)* |

---

## 10. Dependencias

- **Spec 00** — roles, `AuditoriaService`, patrón `requireRole()`.
- **Spec 01 (Empleados)** — `tipo_contrato`, `sueldo`, `fecha_ingreso`, `acumula_decimo_tercero`,
  `acumula_decimo_cuarto`, `acumula_fondos_reserva`, `programa`/`actividad`.
- **Spec 07 (Liquidación de Vacaciones)** — empleados con `acumula_decimo_tercero`/`_cuarto = true`
  cobran esos beneficios acumulados en la liquidación por desvinculación, no aquí.
- **Spec 08 (Horas Extras)** — el valor monetario de HE no fluye a este módulo (gap documentado ahí,
  no aquí).
- `d2_aportes_iess` — catálogo compartido, gestionado desde `Admin/AportesIessController`.

---

## 11. Deuda técnica / hallazgos

> **Los hallazgos 1, 3, 4 y 5 (parcial) se corrigieron el 2026-09-07** (ver CLAUDE.md
> §"Corrección de deuda técnica — Nómina"). Sin cambios de schema — solo `git pull`, sin `migrate`
> ni `config:clear`.

1. ✅ **RESUELTO (2026-09-07)** — El Rol de Pagos no auditaba nada: `calcular`/`cerrar`/
   `updateDetalle`/`importar` no llamaban a `AuditoriaService`, a diferencia de D13/D14/FR que sí
   auditan `CALCULAR`/`CERRAR`. El artefacto más sensible (líquido a pagar) y las ediciones
   manuales de descuentos (sanciones, impuesto renta, etc.) no dejaban traza. Los 4 métodos + el
   nuevo `reabrir()` ahora auditan (`CALCULAR`, `ACTUALIZAR_DETALLE`, `CERRAR` —ya auditaba—,
   `REABRIR`, `IMPORTAR`).
2. **Roles inconsistentes entre controladores — parcialmente resuelto.** `RolPagoController` mezclaba
   `requireRole()` con un `esNominaOAdmin()` inline en el mismo archivo — **unificado a
   `requireRole()`** en los 8 métodos (2026-09-07). `NominaController` sigue usando su propio
   `esNominaOAdmin()` inline en los 14 métodos (D13/D14/FR/SBU/consolidado/auditoría) —
   funcionalmente equivalente, consistente **dentro** de ese archivo, pero distinto patrón que
   `RolPagoController`/`AportesIessController`. *(abierto — refactor de mayor superficie, mismo
   criterio de "no urgente" que el hallazgo equivalente ya documentado en Permisos, spec 04 §10.4)*.
   `AportesIessController` incluyendo `TALENTO HUMANO` **no es un bug** — es un ajuste intencional
   ya documentado el 2026-08-17 (CLAUDE.md), reconfirmado en esta revisión.
3. ✅ **RESUELTO (2026-09-07)** — `RolPagoController::calcular` no usaba `DB::transaction()`: borraba
   el `BORRADOR` anterior y reinsertaba fila por fila sin atomicidad — si el proceso se cortaba a
   mitad de camino, quedaba un rol de pagos incompleto sin ninguna señal de que pasó, y la cabecera
   ya insertada bloqueaba un reintento limpio. Todo el cálculo (borrar + insertar cabecera + insertar
   cada detalle) ahora corre dentro de una sola transacción.
4. ✅ **RESUELTO (2026-09-07)** — Un `tipo_contrato` que no calzaba con ninguna fila vigente de
   `d2_aportes_iess` dejaba los 4 porcentajes de aporte en `0` y el líquido igual al RMU completo,
   **sin ningún aviso** — el empleado terminaba con un rol de pagos "más alto" de lo que le
   correspondía, de forma silenciosa. Ahora `calcular()` recolecta estos casos y los devuelve en
   `sin_tasa[]` en la respuesta (consumido por un banner ámbar en `RolPagoView.vue`) y los cuenta en
   la auditoría de `CALCULAR`. **Sigue sin bloquear el cálculo** (decisión deliberada: un solo
   empleado con dato mal cargado no debería impedir calcular el rol de toda la institución) — el
   control pasa a ser visibilidad antes de cerrar, no un bloqueo duro.
5. **Cierre irreversible sin flujo de reapertura auditado — resuelto para Rol de Pagos, abierto para
   D13/D14/FR.** Nuevo `POST /rol-pago/reabrir` (2026-09-07): exige `observacion`, mismos roles que
   `cerrar`, pasa el período de `CERRADO` a `BORRADOR`, auditado como `REABRIR`, sin columnas nuevas
   en el schema. `nom_decimo_tercero`/`nom_decimo_cuarto`/`nom_fondos_reserva` **no** tienen un
   `reabrir` equivalente todavía — se priorizó Rol de Pagos por ser el artefacto con ediciones
   manuales y mayor impacto económico directo; los otros tres son cálculos puramente aditivos, menor
   riesgo. *(abierto — candidato a extender el mismo patrón si se necesita en la práctica)*
6. **`calcularDiasEnMes()` duplicado idéntico** entre `NominaController` y `RolPagoController` (§5.1)
   — mismo código, sin compartir. Funciona igual en ambos, no es un bug, pero un futuro cambio a la
   fórmula (ej. usar el día calendario real del mes en vez de base 30) tendría que aplicarse en dos
   lugares. *(abierto — candidato a extraer a un trait/helper compartido, menor)*
7. **Modelos Eloquent `RolPago`/`RolPagoDet` sin usar y con `$fillable` desactualizado** (§4.5,
   bonus encontrado al revisar el modelo de datos, fuera de los 5 hallazgos originales) — no es un
   bug activo porque `RolPagoController` no los usa, pero es un riesgo latente si alguien los adopta
   más adelante sin notar que faltan columnas reales en `$fillable`. *(abierto — menor)*

Ninguno de estos cambios tocó `config/*.php`, `.env` ni el schema de BD — solo `git pull`, sin
`migrate` ni `config:clear`.

**Importante — sin pruebas funcionales en vivo:** verificado por código y con `php -l` (sin errores
de sintaxis en los 2 archivos tocados), pero sin correr un cálculo real contra la base de pruebas ni
probar el flujo completo `calcular → editar → cerrar → reabrir` con un usuario real de cada rol.
Antes de dar esto por cerrado en producción conviene una pasada manual, en particular:
`reabrir` seguido de un recálculo (para confirmar que efectivamente descarta ediciones manuales
previas, como se documenta en RN-09.28) y el caso de un empleado real con `tipo_contrato` mal cargado
(para confirmar que el banner de `sin_tasa` aparece y el PDF sigue generándose sin romperse).

---

## 12. Preguntas abiertas

- ¿Vale la pena extender `reabrir` a D13/D14/FR, o alcanza con que TH corrija a mano en casos
  puntuales dado su menor riesgo? (§11.5)
- ¿Conviene bloquear `calcular` del Rol de Pagos si hay empleados `sin_tasa`, en vez de solo avisar?
  (§11.4 — decisión de negocio, hoy se optó por no bloquear)
- ¿Unificar `NominaController` a `requireRole()` para que los 3 controladores del módulo compartan
  el mismo patrón? (§11.2)
- ¿Extraer `calcularDiasEnMes()` a un helper compartido? (§11.6)
- ¿Actualizar o eliminar los modelos Eloquent `RolPago`/`RolPagoDet` sin uso? (§11.7)
- ¿Cómo debería fluir el valor de Horas Extras (spec 08) hacia el Rol de Pagos? (dependencia cruzada,
  ver spec 08 §11.9 — no es parte de esta spec pero el Rol de Pagos sería el consumidor natural)
