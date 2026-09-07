# Spec 12 — Auditoría Centralizada

| | |
|---|---|
| **Versión** | 1.0 (borrador) |
| **Última actualización** | 2026-09-07 |
| **Depende de** | [00 — Paraguas](00-modulo-talento-humano.md) |
| **Servicio** | `App\Services\AuditoriaService` |
| **Controladores de lectura** | `app/Http/Controllers/Admin/AuditoriaController.php`, `NominaController::auditoria()` |
| **Migración** | `2026_05_05_000023_nom_auditoria_log.php` + `2026_09_07_000107_widen_accion_and_protect_nom_auditoria_log.php` |
| **Vista** | `views/admin/AuditoriaView.vue` (ruta `admin/auditoria`) |
| **Tipo** | Retro-especificación |

---

## 1. Propósito

Dejar constancia, para efectos de trazabilidad ante la Contraloría General del Estado, de **quién
hizo qué, cuándo, desde dónde, y con qué datos** en las acciones administrativas de todos los
módulos del sistema (Talento Humano, Adquisiciones, Transportes, Tecnología, Comisiones). Es un
**log de eventos centralizado en una sola tabla** (`dbo.nom_auditoria_log`), no un sistema de
versionado por tabla — cada fila es un evento discreto, no una revisión de un registro.

### 1.1 Qué NO es

- **No** es un sistema de versionado / historial editable de registros (no hay "revertir a esta
  versión") — es un log de eventos de solo lectura.
- **No** cubre automáticamente cada módulo nuevo — instrumentarlo es una **responsabilidad manual**
  de quien escribe el controlador (ver CLAUDE.md §"Si se agrega un nuevo módulo"); esta spec
  documenta precisamente las brechas que resultan de que ese paso se salte u olvide.
- **No** reemplaza los logs de aplicación de Laravel (`storage/logs/laravel.log`) — errores de
  `AuditoriaService::log()` en sí mismo van ahí, no a `nom_auditoria_log` (ver §5.1, es
  intencional pero tiene una consecuencia real, §11.1).

---

## 2. Actores

| Actor | Puede |
|---|---|
| `ADMINISTRADOR`, `TALENTO HUMANO`, `TH NOMINA` | Ver el log de auditoría completo (`GET /api/admin/auditoria`, `GET /api/nomina/auditoria` — mismos 3 roles desde 2026-09-07, ver §5.4/§11.4). |
| Cualquier controlador del backend | **Escribe** en el log llamando `AuditoriaService::log()` — no hay un actor humano que "audite manualmente", es 100% automático desde el código. |

---

## 3. Historias de usuario

- **HU-12.1** — Como auditor/Contraloría, quiero ver todas las acciones críticas del sistema
  (aprobaciones, ediciones de descuentos, cierres de período, cambios de rol) con quién las hizo y
  cuándo.
- **HU-12.2** — Como ADMINISTRADOR, quiero filtrar el log por módulo, tabla, acción, usuario y
  rango de fechas.
- **HU-12.3 (2026-09-07)** — Como usuario del filtro de "Acción", quiero ver **todas** las acciones
  que realmente existen en el log, no una lista fija que quedó desactualizada.
- **HU-12.4 (2026-09-07)** — Como Contraloría, quiero tener garantía de que ninguna fila del log
  puede editarse o borrarse después de escrita, ni siquiera por acceso directo a la base de datos.

---

## 4. Modelo de datos — `dbo.nom_auditoria_log`

| Campo | Contenido |
|---|---|
| `id` PK | — |
| `tabla` VARCHAR(60) | Tabla afectada (`dbo.xxx`, `adq.xxx`, o el literal `auth` para eventos de sesión — ver §5.5). |
| `registro_id` INTEGER | PK del registro afectado; `0` cuando el evento es de todo un período/lote (ej. `CALCULAR` de un rol de pagos mensual) o no aplica un ID numérico único (ver `dbo.d2_lista_fecha`, clave natural compuesta). |
| `accion` VARCHAR(**40** desde 2026-09-07, antes 20) | Texto libre — ver §5.2, §11.1. |
| `datos_anteriores` / `datos_nuevos` JSONB (nullable) | Sin esquema fijo — cada llamador decide qué guardar (ver §5.3, §11.2). |
| `usuario_id`, `nombre_usuario` | Quién ejecutó la acción (`$request->user()`). |
| `ip_origen` | `$request->ip()`. |
| `descripcion` VARCHAR(200) (nullable) | Texto libre legible por humanos, mostrado en la UI. |
| `created_at` | Timestamp del evento — **es la única fuente de "cuándo"**, no hay `updated_at` (la tabla nunca se actualiza, ver §5.6). |

Índices: `(tabla, registro_id)`, `usuario_id`, `created_at`.

---

## 5. Reglas de negocio

### 5.1 `AuditoriaService::log()` — nunca revienta el flujo del caller

- **RN-12.1** — Firma: `log(string $tabla, mixed $registroId, string $accion, mixed $datosAnteriores, mixed $datosNuevos, Request $request, ?string $descripcion)`. Envuelve el `insert()` en `try/catch`; si falla, escribe a `Log::error()` de Laravel y **continúa sin lanzar excepción** — la acción de negocio que se estaba auditando ya se ejecutó y no debe revertirse solo porque el log falló.
- **RN-12.2 (consecuencia real, no solo teórica)** — Esto significa que un `INSERT` que viole una restricción de columna (ej. `accion` más largo que el `VARCHAR` de la columna) **falla en completo silencio** para cualquiera que no esté mirando el log de Laravel en ese instante — ni el usuario ni el admin se enteran de que la auditoría de esa acción específica nunca se escribió. Confirmado en producción antes de esta corrección — ver §11.1.
- **RN-12.3** — `registro_id` se castea a `int` solo si `is_numeric()`; si no, guarda `0`. Usado para eventos de sesión (`auth`, `registro_id=0`) y de período/lote (ej. `CALCULAR` de D13/D14/FR/Rol de Pagos).

### 5.2 `accion` — texto libre, sin enum (2026-09-07: hallazgo confirmado con evidencia real)

- **RN-12.4** — No existe una lista canónica ni una validación de valores permitidos — cada
  controlador escribe el string que le parezca. Relevamiento real del código (2026-09-07): **~70
  valores distintos** en uso simultáneo (`CREAR`, `ACTUALIZAR`, `REVISAR_APROBAR`,
  `ACTUALIZAR_DETALLE`, `REABRIR`, `SOLICITUD_CON_EXCESO`, `REGISTRAR_MANTENIMIENTO_EXTERNO`, …),
  sin espacio central donde consultarlos.
- **RN-12.5 (hallazgo con impacto real, corregido 2026-09-07)** — Al relevar los ~70 valores se
  encontraron **3 que ya excedían el `VARCHAR(20)` original** de la columna `accion`:
  `IMPORTACION_DISTRIBUTIVO` (24), `REGISTRAR_MANTENIMIENTO` (23),
  `REGISTRAR_MANTENIMIENTO_EXTERNO` (31). Por RN-12.2, **cada una de esas 3 acciones venía
  fallando en silencio desde que se instrumentaron** (`EmpleadoController::importarDistributivo()`
  documentado como auditado desde 2026-09-01; `Tecnologia\MantenimientoController` desde su
  creación) — nunca dejaron una fila real en el log pese a que el código "ya las auditaba"
  aparentemente sin error. Corregido: columna ampliada a `VARCHAR(40)` (migración `000107`).
- **RN-12.6 (2026-09-07)** — Nuevo `GET /api/admin/auditoria/acciones`: `SELECT DISTINCT accion
  ORDER BY accion` sobre la tabla real — reemplaza el array hardcodeado de ~25 valores que tenía
  `AuditoriaView.vue` (desactualizado, le faltaban más de dos tercios de las acciones reales). No
  resuelve la falta de un enum de negocio (sigue siendo texto libre, cualquiera puede introducir un
  valor nuevo sin registrarlo en ningún lado más que el propio `AuditoriaService::log()`), pero
  soluciona el problema práctico inmediato: filtrar por una acción real ya no exige conocerla de
  memoria de antemano.

### 5.3 `datos_anteriores` / `datos_nuevos` — sin formato estándar (abierto)

- **RN-12.7** — No hay contrato: algunos llamadores pasan una fotografía completa de los campos
  relevantes (ej. `RolPagoController::updateDetalle` — los 7 descuentos + líquido, antes y
  después), otros solo `{estado: 'PENDIENTE'}` → `{estado: 'APROBADO'}` (la mayoría de los flujos
  de aprobación), y otros `null` en ambos (los eventos de período/lote como `CALCULAR`, que solo
  llevan un resumen en `datos_nuevos` y `null` en `datos_anteriores` porque no había "antes").
- **RN-12.8 (por qué importa)** — Para Contraloría, un `datos_anteriores = {"estado":"PENDIENTE"}`
  no permite **reconstruir** cómo era el registro antes del cambio si el cambio tocó más que el
  estado (ej. un `updateDetalle` de Rol de Pagos que además cambia el monto de una sanción — antes
  de la corrección de spec 09, este caso específico ni siquiera se auditaba; ahora sí lleva
  snapshot completo, ver spec 09 RN-09.24). El problema es **estructural**: no hay nada que impida
  que un nuevo controlador vuelva a auditar solo `{estado:'X'}` para un cambio que merece más.
- **Convención recomendada de aquí en adelante (documentada, no retrofit de los ~96 call sites
  existentes — ver §11.2):** cuando la acción modifica campos con impacto financiero/legal directo
  (montos, fechas de corte, tasas, descuentos), `datos_anteriores`/`datos_nuevos` deben incluir esos
  campos explícitamente, no solo el campo de estado. Cuando la acción es puramente un cambio de
  estado sin otros efectos (aprobar/negar un flujo simple), `{estado: 'X'}` es aceptable.

### 5.4 Lectura del log — dos endpoints, guards unificados (2026-09-07)

- **RN-12.9** — `Admin/AuditoriaController::index()` — el visor general (`AuditoriaView.vue`),
  filtra por `tabla`, `accion`, `usuario_id`, `fecha_desde`/`fecha_hasta`, `descripcion`, y deriva
  `modulo` (`adquisiciones`/`transportes`/`tecnologia`/`talento`) por patrones `ILIKE` sobre
  `tabla`. Paginado 50/página (configurable con `per_page`).
- **RN-12.10** — `NominaController::auditoria()` — mismo `nom_auditoria_log`, filtros más
  reducidos (`tabla`, `usuario_id`, y un filtro `anio` que en realidad hace `descripcion LIKE
  '%anio%'`, no compara contra `created_at`). **Sin consumidor en el frontend** — no hay ninguna
  pantalla de Nómina que llame a este endpoint (relevado 2026-09-07); es código vivo pero sin uso
  actual, no un endpoint activamente usado con un guard distinto causando un problema visible hoy.
- **RN-12.11 (hallazgo corregido 2026-09-07)** — Antes de hoy, **ambos** endpoints leían la misma
  tabla con roles distintos: `Admin/AuditoriaController` exigía `ADMINISTRADOR`/`TALENTO HUMANO`;
  `NominaController::auditoria` exigía `ADMINISTRADOR`/`TH NOMINA` (vía su propio
  `esNominaOAdmin()` inline). Ni uno cubría el rol que sí aceptaba el otro. Unificado: los 2
  endpoints ahora aceptan los mismos 3 roles (`ADMINISTRADOR`, `TALENTO HUMANO`, `TH NOMINA`).

### 5.5 Eventos de sesión (`auth`)

- **RN-12.12** — `LOGIN`, `LOGOUT`, `LOGIN_FALLIDO` se insertan con `tabla='auth'`,
  `registro_id=0`. `LOGIN`/`LOGIN_FALLIDO` se insertan **directamente** con
  `DB::table(...)->insert()` (no vía `AuditoriaService::log()`) porque en el momento del login
  todavía no hay `$request->user()` autenticado del que leer `id_emp`/nombre. `LOGOUT` sí usa
  `AuditoriaService::log()` porque el token sigue siendo válido en ese instante.
- El filtro `modulo=talento` de `Admin/AuditoriaController::index()` incluye explícitamente
  `tabla='auth'` además de `dbo.%` (excluyendo `trans_%`/`ti_%`).

### 5.6 Append-only real (2026-09-07 — antes solo por convención)

- **RN-12.13** — Hasta hoy, "append-only" era una convención de código: ningún controlador llama
  `UPDATE`/`DELETE` sobre `nom_auditoria_log`, pero **nada a nivel de base de datos lo impedía** —
  una fila podía editarse o borrarse con una sesión `psql` directa, sin dejar ningún rastro de que
  pasó (la tabla no tiene ni siquiera un trigger de log-de-log). Corregido con la migración
  `000107`: dos triggers (`BEFORE UPDATE` / `BEFORE DELETE`) que lanzan una excepción de Postgres
  ante cualquier intento de modificar o borrar una fila, sin importar qué rol de base de datos lo
  intente — la única forma de saltárselo es deshabilitar el trigger explícitamente
  (`ALTER TABLE ... DISABLE TRIGGER`), una acción deliberada y nombrada, no un `UPDATE` accidental.

### 5.7 Controladores sensibles instrumentados o revisados el 2026-09-07

| Controlador / método | Antes | Ahora |
|---|---|---|
| `NominaController::sbuStore` | Sin auditar — cambiar el SBU de un año recalcula el D14 completo de esa vigencia | Audita `ACTUALIZAR` (valor anterior/nuevo) |
| `CuadreController::procesar` | Ya tenía `requireRole`, pero recalcular el cuadre manualmente (afecta atrasos/descuentos de toda la institución para esa fecha) no dejaba traza | Audita `PROCESAR_CUADRE` |
| `ZktecoController::update`/`destroy` (activar/desactivar/eliminar el reloj biométrico) | **Sin `requireRole()` — cualquier autenticado podía activar/desactivar o eliminar el dispositivo** (hallazgo de acceso, no solo de auditoría — ver §11.3), y tampoco auditaba | `requireRole(['ADMINISTRADOR'])` + audita `ACTUALIZAR`/`ELIMINAR` |
| `Admin/DepartamentoController` (store/update/inactivar/activar) | Sin auditar | Audita `CREAR`/`ACTUALIZAR`/`DESACTIVAR`/`ACTIVAR` |
| `Admin/RazonController` (store/update/inactivar) | Sin auditar | Audita `CREAR`/`ACTUALIZAR`/`DESACTIVAR` |
| `Admin/ModalidadLaboralController` (store/update) | Sin auditar | Audita `CREAR`/`ACTUALIZAR` |
| `Admin/TurnoController` (store/update/destroy/guardarHorarios) | Sin auditar — `guardarHorarios` cambia las horas de ENTRADA/SALIDA que alimentan atrasos (spec 03) y horas extras (spec 08) de todos los empleados con ese turno | Audita `CREAR`/`ACTUALIZAR`/`ELIMINAR`/`ACTUALIZAR_HORARIOS` (este último con snapshot completo del horario anterior) |
| `Admin/CalendarioController` (store/update/destroy/cargarFeriadosEcuador) | Sin auditar — un feriado mal cargado/borrado cambia la clasificación de horas extras (spec 08 RN-08.2) de esa fecha para todos | Audita `CREAR`/`ACTUALIZAR`/`ELIMINAR`/`CARGAR_FERIADOS` |
| `Admin/AportesIessController` (store/update/destroy) | Sin auditar — la tasa alimenta directamente el aporte de todo el Rol de Pagos (spec 09 §4.4) | Audita `CREAR`/`ACTUALIZAR`/`ELIMINAR` |
| `AsistenciaController::marcar` | Sin auditar | **Se evaluó y se decidió no instrumentar — ver §11.5** |

`Admin/ConfiguracionController` ya auditaba `ACTUALIZAR` desde antes (sin cambios).

---

## 6. UI (`views/admin/AuditoriaView.vue`)

- Filtros: Módulo (select derivado), Acción (select — **poblado dinámicamente desde
  `GET /admin/auditoria/acciones` desde 2026-09-07**, antes hardcodeado), Usuario (cédula, texto
  libre), rango de fechas, texto libre sobre `descripcion`.
- Tabla: fecha/hora, usuario, tabla, badge de color por `accion` (mapa fijo en `badgeAccion()` —
  las acciones fuera del mapa caen a un gris genérico, no es un problema ya que es solo cosmético),
  descripción, IP.
- Clic en una fila expande el JSON de `datos_anteriores`/`datos_nuevos` (pretty-printed).
- Paginación server-side (50/página).

---

## 7. Flujos típicos

| Caso | Pasos |
|---|---|
| **Auditoría solicitada por Contraloría** | ADMINISTRADOR/TH filtra por rango de fechas + módulo → exporta o revisa fila por fila, expandiendo el JSON de cada cambio relevante. |
| **Investigar quién cambió una tasa IESS** | Filtrar `tabla = dbo.d2_aportes_iess`, `accion = ACTUALIZAR` (ahora seleccionable desde el dropdown dinámico, spec 12 §5.2) → ver antes/después. |
| **Confirmar que nadie manipuló el log** | El intento mismo de un `UPDATE`/`DELETE` directo por SQL sobre `nom_auditoria_log` ya falla con la excepción del trigger (§5.6) — no requiere "confirmar", está garantizado a nivel de BD. |

---

## 8. Criterios de aceptación

- **CA-12-1 (2026-09-07)** — Llamar a `AuditoriaService::log()` con una `accion` de hasta 40
  caracteres ya no falla — probado con `IMPORTACION_DISTRIBUTIVO` (24) y
  `REGISTRAR_MANTENIMIENTO_EXTERNO` (31), ambas insertan una fila real.
- **CA-12-2** — `GET /api/admin/auditoria/acciones` devuelve la lista de valores `accion`
  realmente presentes en la tabla, ordenada alfabéticamente, sin duplicados.
- **CA-12-3** — Un `UPDATE dbo.nom_auditoria_log SET descripcion = 'x' WHERE id = 1` ejecutado
  directo en `psql` (con cualquier rol, incluido `postgres`) → falla con la excepción del trigger.
  Ídem un `DELETE`.
- **CA-12-4 (2026-09-07)** — Un usuario con rol `TH NOMINA` (sin `TALENTO HUMANO`) puede acceder a
  `GET /api/admin/auditoria`; un usuario con `TALENTO HUMANO` (sin `TH NOMINA`) puede acceder a
  `GET /api/nomina/auditoria` — antes cualquiera de los dos daba 403 en el endpoint "del otro rol".
- **CA-12-5 (2026-09-07)** — Activar/desactivar/eliminar un dispositivo ZKTeco con un usuario sin
  rol `ADMINISTRADOR` → HTTP 403 (antes: sin ningún chequeo, la operación se ejecutaba igual).
- **CA-12-6 (2026-09-07)** — Cambiar el SBU de un año, recalcular el cuadre manualmente, editar un
  departamento/razón/modalidad laboral/turno/feriado/tasa IESS deja una fila nueva en
  `nom_auditoria_log` con el `usuario_id` real y (donde aplica) el valor anterior.

---

## 9. Casos borde

| Caso | Comportamiento |
|---|---|
| `AuditoriaService::log()` lanza una excepción de BD (ej. violación de constraint) | Se captura, se loguea a `storage/logs/laravel.log`, y la acción de negocio que se estaba auditando **ya se ejecutó y su respuesta HTTP es exitosa** — el usuario nunca ve el fallo de auditoría (RN-12.1/12.2). Es el diseño deliberado (no bloquear al usuario por un problema del log), con la contrapartida de que un problema de este tipo puede pasar desapercibido mucho tiempo — como pasó con los 3 valores de `accion` demasiado largos. |
| Un `registro_id` no numérico (ej. una clave natural como `fecha+ubicación` en `d2_lista_fecha`) | Se guarda `0`, no se intenta forzar un ID — el `datos_anteriores`/`datos_nuevos` debe llevar suficiente contexto (fecha, ubicación) para identificar el registro igual. |
| Alguien deshabilita el trigger de append-only a propósito (`ALTER TABLE ... DISABLE TRIGGER ALL`) | Posible — el trigger protege contra `UPDATE`/`DELETE` accidentales o no autorizados desde código de aplicación, no contra un DBA con acceso directo y la intención explícita de bypassearlo. Es la misma limitación de cualquier control a nivel de aplicación/BD sin auditoría del propio motor de BD (fuera de alcance). |
| `NominaController::auditoria()` sin ningún consumidor en el frontend | Confirmado — no es un bug, es código sin uso activo; se dejó funcional (con los guards ya unificados) por si se conecta a futuro, no se eliminó. |

---

## 10. Dependencias

- **Spec 00** — roles, patrón `requireRole()`.
- **Spec 03 (Asistencia)** — `Admin/TurnoController::guardarHorarios` y `Admin/CalendarioController`
  alimentan directamente `ProcesarCuadre`.
- **Spec 08 (Horas Extras)** — mismo turno/calendario alimenta la clasificación de horas extras.
- **Spec 09 (Nómina)** — `Admin/AportesIessController` alimenta `RolPagoController::tasasVigentes()`;
  `NominaController::sbuStore` alimenta el cálculo de D14.
- Todos los módulos (Adquisiciones, Transportes, Tecnología, Comisiones) escriben en la misma tabla
  `nom_auditoria_log` — es compartida, no exclusiva de Talento Humano.

---

## 11. Deuda técnica / hallazgos

> **Los hallazgos 1, 3 (parcial), 4 y 6 se corrigieron el 2026-09-07** (ver CLAUDE.md
> §"Corrección de deuda técnica — Auditoría"). El 5 se decidió no bloquear (marcación) — ver
> detalle. El 2 queda documentado como convención, sin retrofit de las ~96 llamadas existentes.

1. ✅ **RESUELTO (2026-09-07)** — `accion` era texto libre sin enum, con ~70 valores en uso y sin
   lista canónica. Se encontraron **3 valores que ya excedían el `VARCHAR(20)` original**
   (`IMPORTACION_DISTRIBUTIVO`, `REGISTRAR_MANTENIMIENTO`, `REGISTRAR_MANTENIMIENTO_EXTERNO`) —
   **fallaban en silencio desde que se instrumentaron**, sin que nadie lo notara, por el
   `try/catch` de `AuditoriaService::log()` (RN-12.1/12.2). Corregido: columna ampliada a
   `VARCHAR(40)` (migración `000107`) + nuevo `GET /admin/auditoria/acciones` (lista dinámica real,
   reemplaza el array hardcodeado y desactualizado del frontend). Sigue sin existir un enum de
   negocio real — es mitigación, no la solución completa (ver pregunta abierta §12).
2. **`datos_anteriores`/`datos_nuevos` sin formato estándar.** Algunos llamadores pasan una
   fotografía completa, otros solo `{estado:'X'}`, otros `null`. Para Contraloría, un "antes" que
   solo dice el estado no permite reconstruir el registro si el cambio tocó más campos. **Abierto a
   propósito** — con ~96 llamadas a `AuditoriaService::log()` ya existentes en el código, un
   retrofit sistemático es un refactor de alcance mayor al de esta pasada. Se documentó una
   convención (§5.3) para llamadas nuevas de aquí en adelante.
3. **Controladores sensibles sin instrumentar — mayoría cerrada, una excluida a propósito.**
   `AsistenciaController::marcar`, `CuadreController::procesar`, `ZktecoController`,
   `NominaController::sbuStore`, y el CRUD de 6 catálogos `Admin/*`
   (Departamento/Razon/Turno/Calendario/ModalidadLaboral/AportesIess) — de estos, **solo
   `AsistenciaController::marcar` se dejó deliberadamente sin auditar** (ver punto 5); los otros 8
   controladores ya auditan sus escrituras (§5.7). **Bonus encontrado al revisar
   `ZktecoController`, fuera del hallazgo original:** `update()`/`destroy()` no tenían **ningún**
   `requireRole()` — no era solo un problema de auditoría, cualquier autenticado podía
   activar/desactivar o eliminar el registro del reloj biométrico llamando directo a la API
   (activar un serial no autorizado habría permitido inyectar marcaciones falsas vía el protocolo
   ADMS). Corregido con `requireRole(['ADMINISTRADOR'])`, consistente con lo que ya documentaba
   CLAUDE.md sobre `ZktecoView.vue` ("solo rol ADMINISTRADOR").
4. ✅ **RESUELTO (2026-09-07)** — `Admin/AuditoriaController::index()` (roles
   `ADMINISTRADOR`/`TALENTO HUMANO`) y `NominaController::auditoria()` (roles
   `ADMINISTRADOR`/`TH NOMINA`) leían la misma tabla con guards distintos, ninguno cubriendo el rol
   del otro. Unificados a los 3 roles en ambos. Al revisar se confirmó que
   `NominaController::auditoria()` **no tiene ningún consumidor en el frontend** hoy — el guard
   distinto no causaba un problema de acceso visible en la práctica, pero sí era código
   inconsistente esperando a que alguien lo conectara.
5. **Sin auditoría de la marcación web (`AsistenciaController::marcar`) — evaluado y dejado sin
   instrumentar a propósito.** Cada marcación exitosa ya queda registrada de forma append-only en
   `dbo.sg_control_persona` (con `ip`, `tipo_marcacion`, `fecha_hora`, `nro_documento` — spec 03),
   que es la fuente de verdad real de "quién marcó qué y cuándo"; auditarla también en
   `nom_auditoria_log` duplicaría esa información a un volumen de **cientos de filas diarias**
   (4 marcaciones × cada empleado activo), muy por encima del volumen de cualquier otra acción
   auditada hoy en el sistema (que son decisiones administrativas de baja frecuencia), sin agregar
   valor forense nuevo. `marcar()` además no tiene ninguna rama con privilegios especiales (es
   estrictamente de auto-servicio, el empleado solo marca lo suyo) — a diferencia de, por ejemplo,
   `RolPagoController::updateDetalle`, donde sí vale la pena la auditoría porque un TH NOMINA edita
   el registro **de otra persona**. *(decisión documentada — reabrir si en la práctica se necesita
   auditar casos específicos, ej. solo los rechazos de marcación, no cada marcación exitosa)*
6. ✅ **RESUELTO (2026-09-07)** — La tabla no era append-only a nivel de base de datos: nada
   impedía un `UPDATE`/`DELETE` directo por SQL, solo la convención de que ningún controlador lo
   hace. Agregados 2 triggers (`BEFORE UPDATE`/`BEFORE DELETE`) que bloquean cualquier intento,
   sin importar el rol de conexión a la BD (migración `000107`).

Ninguno de estos cambios tocó `.env` ni `config/*.php`. **Sí requiere `php artisan migrate`**
(migración `000107` — ampliar `accion` a `VARCHAR(40)` y crear los 2 triggers) antes de que las 3
acciones previamente rotas (`IMPORTACION_DISTRIBUTIVO` y las 2 de mantenimiento externo/interno) y
la protección append-only tomen efecto; el resto de correcciones (auditoría en los 8 controladores,
`requireRole` en Zkteco, unificación de roles de lectura, endpoint de acciones dinámico) ya
funcionan solo con `git pull`, sin `migrate`.

**Sin pruebas funcionales en vivo:** verificado por código y con `php -l` (sin errores de sintaxis
en los 12 archivos PHP tocados — 8 controladores + `NominaController`/`CuadreController` +
`Admin/AuditoriaController` + 1 migración), pero sin ejecutar la migración contra una base real ni
confirmar en vivo que el trigger de append-only efectivamente bloquea un `UPDATE`/`DELETE` en
Postgres tal como está escrito. Antes de dar esto por cerrado en producción: correr `php artisan
migrate`, y confirmar con un `UPDATE`/`DELETE` de prueba contra una fila de auditoría en el
ambiente de pruebas que la excepción del trigger efectivamente se dispara.

---

## 12. Preguntas abiertas

- ¿Vale la pena convertir `accion` en un enum de negocio real (tabla catálogo o `CHECK` constraint)
  ahora que se conoce la lista completa de ~70 valores, en vez de solo haber ampliado el
  `VARCHAR`? (§11.1) — el endpoint dinámico de §5.2 cubre el caso de uso inmediato (filtrar sin
  adivinar), pero no impide que a futuro se repita el mismo tipo de error de longitud con un valor
  nuevo mal medido.
- ¿Se justifica un retrofit sistemático de `datos_anteriores`/`datos_nuevos` a un formato
  estándar, o basta con aplicar la convención documentada (§5.3) solo hacia adelante? (§11.2)
- ¿`NominaController::auditoria()` debería conectarse a alguna pantalla, o eliminarse por no tener
  uso? (§11.4)
- ¿Hay algún caso real (no solo teórico) donde valga la pena auditar selectivamente la marcación
  web — por ejemplo, solo los rechazos por modalidad/VLAN, no las marcaciones exitosas? (§11.5)
- ¿El trigger de append-only debería extenderse a otras tablas de trazabilidad legal del sistema
  (ej. `sg_control_persona`, `vac_liquidacion_historico`), o `nom_auditoria_log` es la única que
  amerita esta protección?
