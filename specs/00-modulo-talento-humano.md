# Spec 00 — Módulo Talento Humano (paraguas)

| | |
|---|---|
| **Versión** | 1.0 (borrador) |
| **Última actualización** | 2026-09-01 |
| **Ámbito** | Todo el módulo Talento Humano del Sistema de Gestión del Consejo de Comunicación |
| **Tipo** | Retro-especificación (documenta el comportamiento implementado) |

---

## 1. Propósito

El módulo Talento Humano (TH) digitaliza la gestión del personal del Consejo de Comunicación
(institución pública de Ecuador): expediente del empleado, control de asistencia, permisos y
licencias, vacaciones, acciones de personal, horas extras, nómina de pagos accesorios, certificados
laborales y los reportes asociados. Todas las operaciones críticas quedan trazadas para revisión de
la Contraloría General del Estado.

### 1.1 Objetivos

- Mantener un expediente único y confiable por empleado (datos personales, cargo, contrato, puesto
  presupuestario, datos sociales y bancarios).
- Registrar la asistencia diaria (marcación web y reloj biométrico) y conciliar atrasos.
- Gestionar el ciclo de permisos, licencias y vacaciones con cálculo automático de saldos.
- Emitir los documentos oficiales (acciones de personal, certificados, planificaciones) en PDF con
  el formato institucional y archivarlos firmados en Alfresco.
- Calcular los rubros de nómina que no son el sueldo base (décimos, fondos de reserva, rol de pagos).
- Dejar traza de auditoría de toda acción irreversible.

### 1.2 No-objetivos

- **No** es un sistema de nómina completo: no liquida el sueldo base ni genera archivos para el
  banco. Solo calcula décimos, fondos de reserva y el rol de pagos como reporte.
- **No** gestiona reclutamiento ni concursos de méritos y oposición.
- **No** evalúa desempeño.
- **No** administra los módulos de Adquisiciones, Transportes, Comisiones ni Tecnología (son módulos
  hermanos, fuera de esta spec salvo los puntos de integración señalados).

---

## 2. Actores y roles

La autorización real del sistema es **la visibilidad del menú por rol** más chequeos explícitos
`requireRole()` en los controladores de escritura (ver §6.3). Roles relevantes para TH:

| Rol | Descripción funcional en TH |
|---|---|
| `ADMINISTRADOR` | Acceso total a todo el módulo. |
| `TALENTO HUMANO` | Gestión completa de expedientes, permisos, vacaciones, certificados, reportes. |
| `TH ACCIONES PERSONAL` | Crea y procesa acciones de personal. |
| `TH NOMINA` | Décimos, fondos de reserva, rol de pagos, autorización de horas extras. |
| `SUPERVISOR` | Aprueba/niega permisos, vacaciones y horas extras de su equipo; ve su equipo en el dashboard. Se determina por presencia en `dbo.supervisor_area`, no por `admin_rol`. |
| Empleado (sin rol especial) | Marca asistencia, solicita permisos y vacaciones, registra horas extras planificadas, ve su propio expediente y saldos, cambia su contraseña. |

Un empleado puede acumular varios roles (p. ej. `SUPERVISOR` + `TH NOMINA`).

**Regla de diseño heredada:** el backend **no** usa policies/gates de Laravel. Cada endpoint de
escritura que deba restringirse llama `requireRole($request, [...])` al inicio del método; los
endpoints de lectura que alimentan dropdowns de toda la app se dejan abiertos a cualquier
autenticado a propósito (ver §6.3).

---

## 3. Glosario

| Término | Definición |
|---|---|
| **Empleado / servidor** | Persona registrada en `dbo.ad_empleado`. PK = `id_emp` (string, correlativo de 5 dígitos). |
| **Cédula / identificación** | Documento de identidad ecuatoriano, 10 dígitos. Columna `identificacion` (NO `cedula`). Es la credencial de login y el identificador funcional externo (reloj, AD). |
| **Departamento 999** | Placeholder de sistema ("ADMINISTRACIÓN DEL SISTEMA"). **Excluido de toda consulta de TH.** Los funcionarios externos de Comisiones viven ahí. |
| **Partida individual / presupuestaria** | Identificadores del puesto presupuestario MEF. `partida_individual` es el código corto; `partida_presupuestaria` (o "estructura programática") el largo. |
| **Estado del empleado** | `ACTIVO` / `INACTIVO`. Nunca se elimina un empleado. |
| **Estado del puesto** | `OCUPADO` / `VACANTE` / `DISPONIBLE`. `DISPONIBLE` = empleado inactivo cuya partida puede reasignarse. |
| **Modalidad de marcación** | Cómo puede timbrar: `PRESENCIAL` / `TEMPORAL` / `TELETRABAJO` / `BIOMETRICO`. |
| **Modalidad laboral** | Régimen del nombramiento (Nombramiento Definitivo, Provisional, Contrato, Comisión de Servicios, …). Determina reglas de vacaciones y liquidación. |
| **Tipo de contrato** | `LOSEP` / `CODIGO DEL TRABAJO`. Define la tasa de vacaciones y las tasas de aporte IESS. |
| **Cuadre** | Proceso nocturno que concilia marcaciones vs. horario y calcula atrasos en minutos (`dbo.d2_cuadre_marcacion`). |
| **Razón** | Catálogo `dbo.d2_razon` de motivos de permiso; campo `descontable` = SI/NO. |
| **Factor fin de semana** | 30/22 = 1.3636. Cada día hábil de vacación/permiso descontable consume 1.3636 días de saldo. |
| **Tope de 60 días** | LOSEP Art. 29: el saldo de vacaciones visible y solicitable nunca supera 60 días. |
| **Alfresco** | Gestor documental externo donde se archivan los PDF firmados. |
| **CUR** | Comprobante Único de Registro (presupuesto/contabilidad del Estado). Usado en Comisiones, no en TH. |
| **Cuadre de nómina** | Cierre mensual de un período de nómina; una vez `CERRADO` no se recalcula. |

---

## 4. Arquitectura relevante

- **Backend:** Laravel 12 (PHP 8.4), PostgreSQL. Autenticación con Sanctum; el guard usa el modelo
  `Empleado` (tabla `dbo.ad_empleado`), no el `User` de Laravel.
- **Frontend:** Vue 3 (Composition API) + Pinia + Vue Router + Tailwind 4. Token Sanctum en
  `sessionStorage`. Layout del módulo: `layouts/MainLayout.vue`.
- **Esquema de BD:** `dbo` = Talento Humano. El esquema base (tablas `ad_empleado`, `d2_permiso`,
  `d2_vacacion`, `admin_rol`, `admin_opcion`, `d2_razon`, `d2_turno`, `d2_jornada`,
  `d2_configuracion`, …) proviene de un **sistema legado restaurado por fuera de Laravel**. Las
  migraciones del repo solo hacen `ALTER TABLE` o agregan tablas nuevas.
- **Configuración global:** `dbo.d2_configuracion` (clave/valor). Las consultas usan
  `LOWER(concepto)` porque los conceptos están en MAYÚSCULAS.
- **PDFs:** DomPDF, plantillas en `backend/resources/views/reportes/`, siempre A4, encabezado
  institucional estándar (ver CLAUDE.md).
- **Documentos firmados:** se suben a Alfresco vía `relativePath`; se guarda el `entry.id` en BD.
- **Reloj biométrico:** ZKTeco SenseFace 7A, protocolo ADMS push, endpoints públicos bajo
  `/api/iclock/*`.
- **Autenticación:** híbrida contra Active Directory institucional (LDAP) con fallback a clave local
  (`Hash::check` contra `ad_empleado.password`). Si `AD_HOST` está vacío el comportamiento es 100%
  clave local.

### 4.1 Puntos de integración con otros módulos

| Módulo | Integración |
|---|---|
| Comisiones | Crea empleados con `es_externo = true`, `id_depto = 999`; TH no los edita ni los cuenta. Al salir un empleado en comisión, TH lo pasa a `INACTIVO`. |
| Transportes | Lee `ad_empleado.puede_solicitar_vehiculo`; `EmpleadoController::index/show` alimenta sus buscadores. |
| Adquisiciones / Tecnología | Consumen `EmpleadoController::index/show` para buscadores de custodios/solicitantes. |
| Todos | `Admin/DepartamentoController::index`, `Admin/RazonController::index` alimentan formularios de toda la app. |

---

## 5. Entidades transversales

Tablas del esquema `dbo` compartidas por varias sub-specs. Cada sub-spec detalla las columnas que
usa; aquí solo el mapa general.

| Tabla | Contenido | Sub-specs que la usan |
|---|---|---|
| `ad_empleado` | Expediente del empleado | 01 (dueña), todas |
| `ad_empleado_mail` | Correos del empleado (`estado` ACTIVO/INACTIVO) | 01 |
| `ad_empleado_hijo` | Hijos menores (guardería < 5 años) | 01 |
| `ad_empleado_teletrabajo` | Períodos de teletrabajo habilitados | 01, 03 |
| `ad_departamento` | Departamentos (numeración manual, 999 excluido) | 01, 06, 11 |
| `ad_grupo_vulnerable` / `ad_grupo_prioritario` / `ad_tipo_discapacidad` / `ad_enfermedad_catastrofica` | Catálogos sociales (precargados) | 01 |
| `d2_jornada` / `d2_turno` | Jornadas y turnos laborales | 03, 08 |
| `d2_configuracion` | Parámetros globales | todas |
| `sg_control_persona` | Marcaciones individuales | 03 |
| `d2_cuadre_marcacion` | Atrasos calculados por día/empleado | 03, 04, 11 |
| `d2_razon` | Catálogo de motivos de permiso | 04 |
| `d2_permiso` | Permisos y licencias | 04, 11 |
| `d2_vacacion` / `d2_cabecera_vacacion` / `d2_detalle_vacacion` | Vacaciones y saldos | 05, 07, 11 |
| `acc_accion_personal` | Acciones de personal | 06 |
| `nom_he_planificacion_cab` / `_det` / `nom_he_registro` | Horas extras | 08 |
| `nom_decimo_tercero` / `nom_decimo_cuarto` / `nom_fondos_reserva` / `nom_rol_pago_cab` / `_det` / `nom_sbu_historico` / `d2_aportes_iess` | Nómina | 09 |
| `d2_certificado_laboral` | Certificados laborales | 10 |
| `nom_auditoria_log` | Log centralizado de auditoría | 12, todas |
| `admin_rol` / `admin_usuario_rol` / `admin_opcion` | Roles y menú | 00, todas |
| `supervisor_area` | Relación supervisor ↔ departamento | 04, 05, 08, 11 |

---

## 6. Reglas transversales

### 6.1 Manejo del empleado

- **Nunca se borra un empleado.** "Eliminar" = `estado = 'INACTIVO'` (`DELETE /empleados/{id}` es un soft-deactivate).
- **Departamento 999 excluido** de toda consulta de listado, nómina, asistencia, distributivo, vacaciones y reportes.
- **Empleados `es_externo = true`** (creados desde Comisiones) no son editables desde TH y quedan fuera de todas las queries de RRHH.
- **Nombres y apellidos** se guardan siempre en MAYÚSCULAS (`strtoupper`). Igual para `banco`,
  `tipo_cuenta`, `programa`, `actividad`, `sexo`, `tipo_sangre`.
- La **cédula** (`identificacion`) es única. Es la contraseña inicial (`bcrypt(identificacion)`) y el
  valor de reseteo de contraseña.

### 6.2 Fechas y cálculos

- Fechas de negocio se almacenan como `date` (sin hora). Eloquent puede devolverlas como timestamp
  ISO completo → el frontend normaliza con `String(f).substring(0,10)`.
- Cálculos monetarios y de saldo: redondeo explícito documentado en cada sub-spec (nunca implícito).
- Fechas en PDF: en español mediante array manual de meses (no `Carbon::translatedFormat()`).
- Los feriados viven en `dbo.d2_lista_fecha`; el sistema solo comprueba si una fecha existe ahí (no
  usa el campo `factor`).

### 6.3 Autorización

- Endpoints de **escritura** restringidos: `requireRole($request, [roles])` al inicio del método →
  aborta 403 si no cumple. Helper en `app/Http/Controllers/Controller.php`
  (`tieneAlgunRol()` / `requireRole()`).
- Endpoints de **lectura abiertos a propósito** (cualquier autenticado): `EmpleadoController::index/show`,
  `Admin/DepartamentoController::index`, `Admin/RazonController::index`,
  `Admin/AvisoController::activos`. Justificación: los consumen buscadores/dropdowns de otros módulos.
- El frontend además oculta menús y botones por rol (`auth.tieneRol('NOMBRE')` desde el store Pinia).

### 6.4 Auditoría

- Toda acción **irreversible o sensible** (crear/actualizar empleado, aprobar/negar/anular permiso o
  vacación, asignar/revocar rol, emitir certificado, cerrar nómina, cambiar configuración) llama
  `App\Services\AuditoriaService::log($tabla, $registroId, $accion, $datosAnteriores, $datosNuevos, $request, $descripcion)`.
- El servicio es estático y **nunca lanza excepciones** (try/catch interno) — auditar nunca rompe la operación.
- Eventos de sesión (`LOGIN`, `LOGOUT`, `LOGIN_FALLIDO`) se registran con `tabla = 'auth'`,
  `registro_id = 0`. `LOGIN`/`LOGIN_FALLIDO` se insertan directo (no hay usuario aún).
- Vista: `views/admin/AuditoriaView.vue` (`admin/auditoria`), roles `ADMINISTRADOR` / `TALENTO HUMANO`.

### 6.5 Modo mantenimiento

- `d2_configuracion.MODO_MANTENIMIENTO_TH` = `1` activa el modo mantenimiento del módulo.
- Empleados → pantalla bloqueante con botón "Cerrar Sesión". `ADMINISTRADOR` / `TALENTO HUMANO` →
  banner naranja, pueden seguir trabajando.
- Endpoint: `GET /api/modo-mantenimiento?modulo=TH`.

### 6.6 Estándares de UI

- Modales siguen el estándar de CLAUDE.md (overlay `bg-black/40`, cabecera coloreada, botón ×).
- Color institucional del módulo: `#0b5447` / `#0b5547`.
- PDFs siguen el encabezado y pie estándar de CLAUDE.md; `$generadoPor` = `APELLIDO NOMBRE` del `$request->user()`.
- Componente de hora: `components/TimePicker24.vue` (24h, sin AM/PM).

### 6.7 Despliegue

- Claude genera código; **el usuario despliega manualmente** (git pull + `npm run build` si cambió
  frontend + `php artisan migrate` solo migraciones nuevas + `config:clear` si cambió config).
- **Nunca** `migrate:fresh` / `migrate:rollback` / `migrate:reset` en producción.
- Al crear una migración nueva, verificar el número máximo **real** recorriendo todos los archivos
  (hubo colisiones `000095` / `000097`).

---

## 7. Catálogo de sub-especificaciones

| # | Sub-spec | Alcance resumido |
|---|---|---|
| 01 | **Empleados** | CRUD del expediente, foto, hijos, doc. persona sustituta, períodos de teletrabajo, datos sociales/bancarios, partidas vacantes, reset de contraseña, importación de distributivo (CSV legado). |
| 02 | **Importación masiva de empleados** | `ImportacionController`: plantilla de 48 columnas, preview, upsert por cédula, normalización de fechas/tipos/catálogos. |
| 03 | **Control de asistencia** | `sg_control_persona`, secuencia diaria, modalidades de marcación, validación de IP/VLAN, integración ZKTeco, `d2_cuadre_marcacion`, `ProcesarCuadre`, vista personal y de administrador. |
| 04 | **Permisos y licencias** | `d2_permiso`, estados, `tipo_horario`, descuento de vacaciones al aprobar, factor fin de semana, `dias_descuento_efectivo`, anulación, documentos de respaldo. |
| 05 | **Vacaciones** | Solicitud, cálculo de saldo (`calcularSaldoDisponible`), tasa por antigüedad CT, tope 60 días, saldo negativo + informe favorable, backup al aprobar, planificación anual, reporte de saldo, carga de saldos por CSV. |
| 06 | **Acciones de personal** | `acc_accion_personal`, 8 tipos, flujo BORRADOR→ACTIVO, firmantes por acción, PDF oficial, auto-cierre, subida a Alfresco. |
| 07 | **Liquidación de vacaciones** | Motivos por modalidad laboral, comisión (inicio/fin), desvinculación, congelamiento en `fecha_salida`, sin tope de 60. |
| 08 | **Horas extras** | Planificación mensual → registro real, clasificación automática de horas, cálculo monetario, roles, PDFs, Alfresco. |
| 09 | **Nómina** | D13, D14, Fondos de Reserva, Rol de Pagos, SBU histórico, tasas de aporte IESS, días proporcionales, cierre de período. |
| 10 | **Certificados laborales** | `d2_certificado_laboral`, numeración `DATH-CL-NNN-YYYY`, PDF adaptado al género, flujo firmar→subir. |
| 11 | **Dashboard y reportes de TH** | Métricas por rol, reportes de atrasos / marcaciones faltantes / sin atrasos / movimientos de personal / marcaciones del día, LOTAIP, historial de remuneraciones, reporte de empleados. |
| 12 | **Auditoría** | `nom_auditoria_log`, `AuditoriaService`, controladores instrumentados, filtros, vista. |

---

## 8. Criterios de aceptación transversales

- **CA-00-1** — Ningún endpoint de listado o reporte de TH devuelve empleados con `id_depto = 999` ni con `es_externo = true`.
- **CA-00-2** — Dado un usuario sin el rol requerido, cuando llama directamente (sin pasar por la UI) a un endpoint de escritura restringido, entonces recibe HTTP 403 y no se modifica ningún dato.
- **CA-00-3** — Toda acción irreversible listada en §6.4 produce exactamente un registro en `nom_auditoria_log` con `datos_anteriores` y `datos_nuevos` coherentes.
- **CA-00-4** — Un fallo del servicio de auditoría (BD, red) no interrumpe ni revierte la operación de negocio.
- **CA-00-5** — Con `MODO_MANTENIMIENTO_TH = 1`, un empleado sin rol TH/Admin no puede acceder a ninguna vista del módulo; un usuario TH/Admin sí.
- **CA-00-6** — Todo PDF del módulo se genera en A4 con el encabezado institucional estándar y la línea "Generado por: APELLIDO NOMBRE | dd/mm/YYYY HH:mm".
- **CA-00-7** — Si `AD_HOST` está vacío en el `.env`, el login funciona únicamente con la clave local y ningún intento de conexión LDAP se realiza.

---

## 9. Preguntas abiertas / deuda conocida

- **Correcciones de deuda técnica del 2026-09-01** (a partir de las specs 01–05): cerradas y
  documentadas en CLAUDE.md. Resumen:
  - `EmpleadoController` — 10 endpoints de sub-recursos ahora con `requireRole` (spec 01 §10.1).
  - `ImportacionController` — `requireRole` + auditoría `IMPORTACION_MASIVA` + skip de `es_externo` (spec 02 §9).
  - `PermisosController` — fuga de acceso en `show`/documentos cerrada; `aprobar`/`anular` ya no
    tocan `d2_cuadre_marcacion` (ahora `ProcesarCuadre` es idempotente y la única fuente de
    `horas_decto`/`horaspermiso_pag`); claves de auditoría corregidas (spec 04 §10).
  - Vacaciones — `SaldoVacacionesService` unifica las 4 copias del cálculo; `VacacionesController::anular()`
    nuevo; planificación auditada; `empleadosDeSupervisor` con cascada en los 3 controladores;
    planificación validada contra saldo real (spec 05 §12).
  - ZKTeco — `registrarContacto()` ya no auto-activa dispositivos; default de `activo` = `false`
    (migración `000105`) (spec 03 §12.7).
- **Segunda tanda (2026-09-02):**
  - `ProcesarCuadre` filtra `id_depto != 999` y `es_externo` false/null; turno incompleto →
    atrasos en 0 + `warn` (spec 03 §12.1–2).
  - `importarDistributivo` transaccional + auditoría `IMPORTACION_DISTRIBUTIVO` + resolución de
    columnas por nombre (spec 01 §10.4).
  - Typo `nullable` en la validación de períodos de planificación (spec 05 §12.8).
- **Robustez y trazabilidad P2 (2026-09-01):**
  - `EmpleadoController::destroy` audita `DESACTIVAR` + fuerza `estado_puesto = DISPONIBLE`;
    `eliminarDocSustituta` limpia los 4 campos; `departamentos()` excluye el 999;
    `jornada_id` dejó de escribirse (spec 01 §10).
  - `Empleado::generarSiguienteId()` con `pg_advisory_xact_lock` (usado por `store` e importación) —
    fin de la colisión de `id_emp` (spec 01/02).
  - Importación masiva valida `id_depto` contra `ad_departamento` (spec 02 §9.4).
  - `PermisosController::store` audita `SOLICITAR`; export de permisos con rango obligatorio + tope
    5000 (spec 04 §10.8–9).
  - `ProcesarCuadre` marca `procesado = 'SI'`; fallback por posición eliminado (spec 03 §12.3–4).
  - `AsistenciaController::marcar` falla cerrado ante modalidad desconocida (spec 03 §12.5);
    `listado()` con techo 2000 (§12.8).
  - Monitoreo de `schedule:run`: `ULTIMO_CUADRE_PROCESADO` en `d2_configuracion` + banner en el
    dashboard de Admin/TH si el cuadre lleva > 26 h sin correr (spec 03 §12.6).
  - **Dejados sin tocar a propósito:** `d2_cuadre_marcacion.ip` fijo; patrón de autorización mixto
    de `PermisosController`; `index` de vacaciones sin export.
- **Acciones de Personal (2026-09-03):** `AccionPersonalController` instrumentado con auditoría
  (`CREAR`/`PROCESAR`/`EDITAR_BORRADOR`/`CAMBIAR_ESTADO`/`SUBIR_FIRMADO`); `store()` valida el estado
  del empleado según el tipo; `cambiarEstado()` solo permite `ACTIVO → FINALIZADO/ANULADO`;
  numeración con advisory lock + `DB::transaction()`; auto-cierre movido a
  `php artisan cerrar:acciones-vencidas` (06:00, incluye COMISION DE SERVICIOS) — ya no es efecto
  secundario de un GET (spec 06 §11).
- La auditoría de control de acceso (2026-08-17) se hizo por revisión de código, **sin pruebas en
  vivo por rol**. Pendiente: pasada manual con un usuario de cada rol, especialmente
  `SUPERVISOR` / `CONDUCTOR`. Igual para las correcciones del 2026-09-01 (sin `php -l` ni prueba por rol).
- El módulo de Comisiones tiene control de acceso **parcial** en sus controladores financieros
  (fuera de esta spec, pero comparte roles).
- Empleados de modalidad distinta a "Nombramiento Definitivo" con saldo de vacaciones negativo
  quedan bloqueados para pedir vacaciones pero la UI les muestra "0" sin explicación — decisión de
  negocio pendiente (ver spec 05).
- Custodio inactivo con equipos asignados: no hay validación cruzada TH ↔ Tecnología (fuera de
  alcance, documentado en Tecnología).
