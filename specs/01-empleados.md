# Spec 01 — Empleados

| | |
|---|---|
| **Versión** | 1.0 (borrador) |
| **Última actualización** | 2026-09-01 |
| **Depende de** | [00 — Módulo Talento Humano](00-modulo-talento-humano.md) |
| **Controlador** | `app/Http/Controllers/EmpleadoController.php` |
| **Modelo** | `app/Models/Empleado.php` (+ `EmpleadoMail`, `EmpleadoHijo`, `Departamento`, `CabeceraVacacion`) |
| **Vistas** | `frontend/src/views/empleados/` (`EmpleadosIndex.vue`, `EmpleadoForm.vue`, `EmpleadoDetalle.vue`, `DistributivoView.vue`) |
| **Tipo** | Retro-especificación |

---

## 1. Propósito

Mantener el **expediente único** de cada servidor de la institución: datos personales, cargo y
contrato, datos del puesto presupuestario, datos sociales (grupos vulnerables/prioritarios,
discapacidad, enfermedad catastrófica, persona sustituta, hijos), datos bancarios, foto, y la
configuración de asistencia (modalidad de marcación, períodos de teletrabajo).

Es la entidad raíz del módulo: todas las demás sub-specs referencian al empleado por `id_emp`.

### 1.1 Alcance

Incluye:
- Alta, consulta, listado paginado con filtros, edición y desactivación de empleados.
- Gestión de la foto del empleado (subir/eliminar, almacenamiento local).
- Gestión de hijos menores (con cálculo de derecho a guardería).
- Documento de persona sustituta (PDF en Alfresco).
- Períodos de teletrabajo habilitados.
- Catálogos sociales (solo lectura para el formulario).
- Selección de partidas presupuestarias vacantes.
- Reseteo de contraseña (por TH/Admin) y cambio de contraseña propia (por el empleado).
- Importación del distributivo por CSV legado (`importarDistributivo`).

Excluye (otras sub-specs):
- Importación masiva completa de 47 columnas → **spec 02**.
- Asignación/revocación de roles → `RolController` (spec 00 / futura spec de administración).
- Modalidad de marcación *en tiempo de marcación* y validación de teletrabajo activo → **spec 03**.
- Reporte de empleados con alertas y gráficos (`ReporteEmpleadosController`) → **spec 11**.
- Cálculo de saldo de vacaciones → **spec 05** (aquí solo se crea la cabecera con saldo 0).

---

## 2. Actores

| Actor | Puede |
|---|---|
| `ADMINISTRADOR`, `TALENTO HUMANO` | Crear, editar, desactivar empleados; subir/eliminar foto; gestionar hijos, doc. sustituta, teletrabajo; resetear contraseña; importar distributivo. |
| Cualquier usuario autenticado | Listar y ver empleados (`index` / `show`) — abierto a propósito (§6.3 de la paraguas); cambiar **su propia** contraseña. |
| Módulos hermanos | Consumen `index` / `show` / `partidas-vacantes` / `catalogos-sociales` para buscadores y formularios. |

> **Nota de seguridad (actualizada 2026-09-01):** los endpoints de **lectura** `index`, `show`,
> `hijoIndex`, `teletrabajoIndex`, `catalogosSociales`, `partidasVacantes`, `departamentos` se dejan
> abiertos a cualquier autenticado **a propósito** (§6.3 de la paraguas — los consumen buscadores de
> otros módulos). Los de **escritura** `store`, `update`, `destroy` usan
> `requireRole(ROLES_ADMIN = ['ADMINISTRADOR','TALENTO HUMANO'])`. `resetPassword` e
> `importarDistributivo` hacen chequeo inline contra `admin_usuario_rol`.
>
> **Cerrado el 2026-09-01** (antes sin ningún control — ver §10): los 10 endpoints de sub-recursos
> `subirFoto`, `eliminarFoto`, `hijoStore`, `hijoDestroy`, `subirDocSustituta`,
> `descargarDocSustituta`, `eliminarDocSustituta`, `teletrabajoStore`, `teletrabajoDestroy` — y
> también `hijoIndex` / `teletrabajoIndex` según la implementación — ahora llaman
> `requireRole($request, self::ROLES_ADMIN)` al inicio.

---

## 3. Historias de usuario

- **HU-01** — Como TH, quiero registrar un nuevo empleado con sus datos completos para que exista su
  expediente y pueda iniciar sesión con su cédula.
- **HU-02** — Como TH, quiero buscar empleados por nombre, apellido o cédula y filtrar por
  departamento/estado/tipo de contrato para encontrar rápido a una persona.
- **HU-03** — Como TH, quiero editar el expediente de un empleado y que los cambios sensibles
  (sueldo, estado, departamento, cargo, partida, modalidad) queden auditados.
- **HU-04** — Como TH, quiero desactivar a un empleado que ya no trabaja en la institución sin
  perder su historial.
- **HU-05** — Como TH, quiero al asignar una partida individual que el puesto del titular anterior
  (inactivo/disponible) pase a `OCUPADO` automáticamente.
- **HU-06** — Como TH, quiero registrar los hijos menores de un empleado y ver cuáles dan derecho a
  guardería (menores de 5 años).
- **HU-07** — Como TH, quiero adjuntar el PDF de la persona sustituta con su fecha de caducidad.
- **HU-08** — Como TH, quiero habilitar períodos de teletrabajo para un empleado para que pueda
  marcar desde casa dentro de ese rango.
- **HU-09** — Como TH, quiero resetear la contraseña de un empleado a su cédula cuando la olvida.
- **HU-10** — Como empleado, quiero cambiar mi propia contraseña.
- **HU-11** — Como TH, quiero cargar el distributivo (grado, grupo ocupacional, proceso, partidas,
  acumulaciones) de varios empleados desde un CSV.
- **HU-12** — Como TH, quiero ver la lista de partidas presupuestarias disponibles para asignar una
  al crear/editar un empleado.

---

## 4. Modelo de datos

### 4.1 `dbo.ad_empleado` (tabla principal)

PK `id_emp` VARCHAR (correlativo 5 dígitos, `str_pad`). `timestamps = false` en el modelo;
auditoría manual con `created_at/by`, `updated_at/by` (migración `000032`).

Campos por bloque (los tipos son los de la BD legada + migraciones; ver CLAUDE.md §Base de Datos
para el detalle de migraciones):

**Identificación y datos personales**
| Campo | Notas |
|---|---|
| `identificacion` | Cédula, único, 10 díg. **Login + contraseña inicial.** |
| `nombre_emp`, `apellido_emp` | Se guardan en MAYÚSCULAS. |
| `telefono` | Opcional. |
| `extension` | VARCHAR(10) nullable (mig. `000093`). Accessor hace `trim()`. |
| `calle_y_numero` | Dirección. |
| `sexo` | `MASCULINO` / `FEMENINO` / null (mig. `000063`). MAYÚSCULAS. |
| `tipo_sangre` | `A+`,`A-`,`B+`,`B-`,`AB+`,`AB-`,`O+`,`O-` / null (mig. `000063`). |
| `foto` | Ruta relativa en `storage/app/public/empleados/`. Accessor `foto_url`. |

**Cargo y contrato**
| Campo | Notas |
|---|---|
| `id_depto` | FK `ad_departamento`. 999 excluido. |
| `cargo_empleado` | Obligatorio en store/update. |
| `tipo_contrato` | `LOSEP` / `CODIGO DEL TRABAJO`. |
| `modalidad_laboral` | Obligatorio. Determina reglas de vacaciones/liquidación. |
| `id_jornada` | FK de la jornada laboral; alimenta la relación `jornada()` (Permisos / Horas Extras). `jornada_id` es una columna duplicada que **ya no se escribe** (2026-09-01) y quedaba siempre en `null`. |
| `fecha_ingreso` | Base para antigüedad y saldo de vacaciones. |
| `fecha_salida` | Solo si `INACTIVO`. Congela acumulados. |
| `sueldo` | `numeric`, `min:0`. Auditado. |
| `estado` | `ACTIVO` / `INACTIVO`. |
| `motivo_salida` | `COMISIÓN DE SERVICIOS` / `FIN DE COMISIÓN DE SERVICIOS` / `FIN DE CONTRATO` / `RENUNCIA VOLUNTARIA` / `JUBILACIÓN` (mig. `000073`). |
| `motivo_reactivacion` | `RETORNO DE COMISIÓN DE SERVICIOS` (mig. `000073`). |
| `institucion_comision` | Institución destino/origen de comisión (mig. `000073`). |
| `es_comisionado_entrante` | Boolean. Filtro en `index`. |

**Datos del puesto (distributivo)**
| Campo | Notas |
|---|---|
| `grupo_ocupacional` | Obligatorio. Contiene 'JERARQUICO' → tarifa de viáticos alta. |
| `nivel` (= "grado") | Integer, obligatorio. |
| `proceso_institucional` | Obligatorio, max 30. |
| `partida_individual` | Integer `min:1`, obligatorio. |
| `partida_presupuestaria` | String, obligatorio ("estructura programática"). |
| `estado_puesto` | `OCUPADO` / `VACANTE` / `DISPONIBLE`. Se fuerza a `DISPONIBLE` si el empleado pasa a `INACTIVO`. |
| `programa` VARCHAR(4), `actividad` VARCHAR(6) | Clasificación MEF, opcionales. MAYÚSCULAS. |
| `num_sercop`, `fecha_vence_sercop` | Certificado SERCOP (mig. `000065`). |
| `acumula_fondos_reserva` | 0/1/2 (usado por Nómina). |
| `acumula_decimo_tercero`, `acumula_decimo_cuarto` | Boolean. En la UI un solo selector fija ambos. |
| `puede_solicitar_vehiculo` | Boolean (Transportes). |

**Datos sociales (mig. `000070`)**
`grupo_vulnerable_id`, `grupo_prioritario_id`, `tiene_discapacidad`, `tipo_discapacidad_id`,
`porcentaje_discapacidad` (0–100), `tiene_enfermedad_catastrofica`, `enfermedad_catastrofica_id`,
`tiene_persona_sustituta`, `sustituta_alfresco_id`, `sustituta_nombre_archivo`,
`sustituta_fecha_caducidad`, `num_hijos_mayores`.

**Datos bancarios (mig. `000081`)**
`banco` (MAYÚSCULAS), `tipo_cuenta` (`AHORROS`/`CORRIENTE`), `numero_cuenta`.

**Asistencia**
`modalidad_marcacion` (`PRESENCIAL`/`TEMPORAL`/`TELETRABAJO`/`BIOMETRICO` — ver spec 03).

**Sistema**
`es_externo` (mig. `000082`) — si `true`, `update()` responde 422 y el empleado queda fuera de las
queries de RRHH. `password` (hidden), `clave` (hidden, legado).

### 4.2 Tablas relacionadas

| Tabla | Relación | Reglas |
|---|---|---|
| `dbo.ad_empleado_mail` | `hasMany` `emails()` filtrado `estado = 'ACTIVO'` | Al guardar un email nuevo se hace `UPDATE ... SET estado='INACTIVO'` de los anteriores y se inserta el nuevo `ACTIVO`. Un empleado puede tener varios registros históricos; solo uno activo. |
| `dbo.ad_empleado_hijo` | `hasMany` `hijos()` ordenado por `fecha_nacimiento` | Campos: `id_emp`, `nombre` (nullable), `fecha_nacimiento` (mig. `000071`). Sin límite. `fecha_nacimiento` obligatoria, `before_or_equal:today`. El sistema calcula `anos` = floor((hoy − fnac)/365.25d) y `guarderia = anos < 5`. |
| `dbo.ad_empleado_teletrabajo` | tabla plana vía `DB::table` | Campos: `id_emp`, `fecha_desde`, `fecha_hasta`, `created_by`, timestamps (mig. `000086`). `fecha_hasta >= fecha_desde`. Sin validación de solapamiento entre períodos. |
| `dbo.d2_cabecera_vacacion` | `firstOrCreate` al crear empleado | Se crea con todos los contadores en 0 y `fecha_proceso = fecha_ingreso ?? hoy`. El cálculo real de saldo es de la spec 05. |
| `dbo.ad_departamento` | `belongsTo` `departamento()` | 999 excluido. |
| Catálogos sociales | solo lectura | `ad_grupo_vulnerable`, `ad_grupo_prioritario`, `ad_tipo_discapacidad`, `ad_enfermedad_catastrofica` — se devuelven solo `WHERE activo = true` ordenados por `nombre`. |

---

## 5. Reglas de negocio

### 5.1 Alta (`store`)

- **RN-01** — Requiere rol `ADMINISTRADOR` o `TALENTO HUMANO`.
- **RN-02** — Campos obligatorios: `identificacion` (único), `nombre_emp`, `apellido_emp`,
  `id_depto`, `cargo_empleado`, `grupo_ocupacional`, `nivel`, `sueldo` (≥0),
  `partida_presupuestaria`, `partida_individual` (≥1), `proceso_institucional`, `modalidad_laboral`.
- **RN-03 (2026-09-01)** — `id_emp` lo genera `Empleado::generarSiguienteId()` (método estático del
  modelo) usando `pg_advisory_xact_lock()` para serializar la generación entre transacciones
  concurrentes. **Debe llamarse dentro de una transacción activa**; por eso `store()` ahora envuelve
  toda la creación en `DB::transaction()`. Antes: `orderByRaw('id_emp DESC')->value('id_emp') + 1`
  sin bloqueo → dos altas simultáneas podían chocar contra la PK.
- **RN-04** — `estado` por defecto `ACTIVO`. `nombre_emp`/`apellido_emp` → MAYÚSCULAS.
- **RN-05** — Se fija `password = bcrypt(identificacion)`.
- **RN-06** — Se crea `d2_cabecera_vacacion` (saldo 0) con `firstOrCreate`.
- **RN-07** — Si viene `email`, se inserta en `ad_empleado_mail` como `ACTIVO`.
- **RN-08** — Si viene `partida_individual`, los empleados `INACTIVO` + `DISPONIBLE` con esa misma
  partida (distintos del nuevo) pasan a `estado_puesto = 'OCUPADO'`.
- **RN-09** — Auditoría: `AuditoriaService::log('dbo.ad_empleado', id_emp, 'CREAR', null, {...}, ...)`.
- **RN-10** — Respuesta: HTTP 201 con el empleado + `departamento` + `emails`.

### 5.2 Edición (`update`)

- **RN-11** — Requiere rol `ADMINISTRADOR` o `TALENTO HUMANO`.
- **RN-12** — Si `emp.es_externo` → HTTP 422 `"Los funcionarios externos se gestionan desde el módulo de Comisiones."`, sin cambios.
- **RN-13** — Mismos campos obligatorios de datos del puesto que en `store` (`cargo_empleado`,
  `grupo_ocupacional`, `nivel`, `sueldo`, `partida_presupuestaria`, `partida_individual`,
  `proceso_institucional`, `modalidad_laboral`). Los datos personales son `nullable` (merge parcial:
  `$request->campo ?? $emp->campo`).
- **RN-14** — Si el `estado` resultante es `INACTIVO`, `estado_puesto` se fuerza a `DISPONIBLE`.
- **RN-15** — Campo `email`: si viene, se desactivan los emails previos y se crea uno nuevo activo.
- **RN-16** — Misma regla de partida → `OCUPADO` que RN-08.
- **RN-17** — Los campos booleanos sociales usan `$request->has(...)` para distinguir "no enviado"
  de "enviado en false"; `banco`/`tipo_cuenta`/`numero_cuenta` se ponen a `null` si se envían vacíos.
- **RN-18** — Auditoría `ACTUALIZAR` con snapshot **antes/después** de: `sueldo`, `estado`,
  `id_depto`, `cargo_empleado`, `tipo_contrato`, `modalidad_laboral`, `partida_individual`,
  `programa`, `actividad`, `modalidad_marcacion`, `motivo_salida`, `motivo_reactivacion`,
  `institucion_comision`, `es_comisionado_entrante`.
- **RN-19** — Respuesta: empleado + `departamento` + `emails` + `foto_url`.

### 5.3 Desactivación (`destroy`)

- **RN-20 (2026-09-01)** — Requiere rol Admin/TH. `DELETE /empleados/{id}` hace
  `estado = 'INACTIVO'` **y `estado_puesto = 'DISPONIBLE'`** (coherente con `update`, RN-14), y
  registra auditoría `DESACTIVAR` en `nom_auditoria_log`. **No borra la fila.** Antes solo cambiaba
  `estado` y no auditaba — un empleado desactivado por aquí no aparecía en `partidasVacantes()`.

### 5.4 Listado (`index`)

- **RN-21** — Base: `WHERE id_depto != 999`, con `departamento` y `emails`, ordenado por
  `apellido_emp`, `nombre_emp`, paginado (`per_page`, default 10).
- **RN-22** — Filtros combinables: `buscar` (ILIKE sobre nombre/apellido/identificación),
  `departamento_id`, `estado` (upper), `tipo_contrato`, `modalidad_laboral`, `es_comisionado_entrante`
  (`'1'` → true).

### 5.5 Detalle (`show`)

- **RN-23** — Devuelve el empleado con `departamento`, `emails`, `hijos`, `foto_url`, y `hijos`
  enriquecidos con `anos` y `guarderia`.

### 5.6 Foto

- **RN-24** — `subirFoto`: valida `image|max:2048` (KB). Borra la foto anterior del disco `public`.
  Guarda en `empleados/`. Devuelve `{ foto }`.
- **RN-25** — `eliminarFoto`: borra del disco y pone `foto = null`.
- **RN-26** — No hay chequeo de rol explícito en foto (deuda menor; ver §10).

### 5.7 Hijos

- **RN-27** — `hijoStore`: `fecha_nacimiento` obligatoria, no futura; `nombre` opcional. Devuelve
  201 con `{ id, nombre, fecha_nacimiento, anos, guarderia }`.
- **RN-28** — `hijoDestroy`: borra físicamente (`delete()`) validando que el hijo pertenece al `id_emp`.
- **RN-29** — Derecho a guardería = hijo con `anos < 5` (cálculo en días / 365.25).

### 5.8 Documento de persona sustituta (Alfresco)

- **RN-30** — `subirDocSustituta`: `documento` obligatorio `mimes:pdf|max:5120` (KB);
  `sustituta_fecha_caducidad` opcional.
- **RN-31** — Carpeta Alfresco: `empleados/{cedula}_{PRIMER_APELLIDO}` (vía `relativePath`,
  `autoRename`). Nombre: `sustituta_{cedula}_{Ymd_His}.pdf`.
- **RN-32** — Al subir: guarda `sustituta_alfresco_id`, `sustituta_nombre_archivo`,
  `sustituta_fecha_caducidad`, y fuerza `tiene_persona_sustituta = true`.
- **RN-33** — Si Alfresco responde no-exitoso → HTTP 502.
- **RN-34** — `descargarDocSustituta`: 404 si no hay `sustituta_alfresco_id`; si hay, hace stream del
  contenido con `Content-Type: application/pdf`, `Content-Disposition: inline`.
- **RN-35 (2026-09-01)** — `eliminarDocSustituta`: borra el nodo en Alfresco (si existe) y limpia
  **los 4 campos juntos**: `sustituta_alfresco_id`, `sustituta_nombre_archivo`,
  `tiene_persona_sustituta = false`, `sustituta_fecha_caducidad = null`. Antes solo limpiaba los dos
  primeros y la ficha quedaba diciendo "tiene persona sustituta" sin documento.

### 5.9 Períodos de teletrabajo

- **RN-36** — `teletrabajoStore`: `fecha_desde` y `fecha_hasta` obligatorias,
  `fecha_hasta >= fecha_desde`. Guarda `created_by = user.id_emp`. Devuelve 201 con el período.
- **RN-37** — `teletrabajoIndex`: lista los períodos del empleado ordenados por `fecha_desde` desc.
- **RN-38** — `teletrabajoDestroy`: borra el período validando `id_emp`; 404 si no existe.
- **RN-39** — No valida solapamiento entre períodos ni exige que `modalidad_marcacion` sea
  `TELETRABAJO` (esa validación es en tiempo de marcación — spec 03).

### 5.10 Contraseñas

- **RN-40** — `resetPassword`: solo `ADMINISTRADOR`/`TALENTO HUMANO` (chequeo inline). Pone
  `password = bcrypt(identificacion)`.
- **RN-41** — `cambiarPassword` (`POST /api/cambiar-password`): el empleado autenticado; valida
  `password_actual` con `Hash::check`, `password_nuevo` `min:6` y `password_confirmar` igual. 422 si
  la actual es incorrecta.

### 5.11 Importación de distributivo (`importarDistributivo`)

> CSV **legado**, distinto de la importación masiva de la spec 02. Solo actualiza campos del
> distributivo de empleados **ya existentes** (no crea).

- **RN-42** — Solo `ADMINISTRADOR`/`TALENTO HUMANO` (chequeo inline). `archivo` `mimes:csv,txt|max:2048` (KB).
- **RN-43** — Encabezados se normalizan a MAYÚSCULAS y espacios colapsados.
- **RN-44** — Filas con menos de 12 columnas se saltan. Cédula se rellena a 10 dígitos con ceros a
  la izquierda (`str_pad`); `'0000000000'` se salta.
- **RN-45** — Empleado no encontrado por `identificacion` → se acumula en `no_encontrados` (no
  bloquea).
- **RN-46 (2026-09-02)** — Campos actualizados por fila: `nivel` (col. "GRADO"), `grupo_ocupacional`,
  `proceso_institucional`, `partida_individual`, `partida_presupuestaria`, `estado_puesto`,
  `acumula_fondos_reserva`, `acumula_decimo_tercero` (`"SI"`→true), `acumula_decimo_cuarto`
  (`"SI"`→true). Las **dos** columnas homónimas "PARTIDA INDIVIDUAL" se resuelven con
  `array_keys($header, 'PARTIDA INDIVIDUAL')` calculado una vez al leer el encabezado (la 1ª =
  `partida_individual`, la 2ª = `partida_presupuestaria`). Si no aparecen exactamente esas dos
  columnas → HTTP 422 explícito. Antes se asumían las posiciones fijas 0 y 9 en silencio.
- **RN-47 (2026-09-02)** — Todo el loop corre dentro de `DB::beginTransaction()` / `commit()` /
  `rollBack()`: si el proceso se corta a mitad, **no queda nada aplicado**. Dentro, sigue el
  criterio "mejor esfuerzo": los errores por fila (empleado no encontrado, `update()` fallido) se
  capturan y reportan en `errores[]` / `no_encontrados[]` sin abortar el resto del archivo. Devuelve
  `{ actualizados, no_encontrados[], errores[] }`.
- **RN-48 (2026-09-02)** — Registra `IMPORTACION_DISTRIBUTIVO` en `nom_auditoria_log`. Antes no auditaba.

### 5.12 Partidas vacantes y catálogos

- **RN-49** — `partidasVacantes` (`GET /api/empleados/partidas-vacantes`): empleados
  `estado = INACTIVO` + `estado_puesto = DISPONIBLE` + `partida_individual` no nula, ordenados por
  `partida_individual`. Devuelve `id_emp`, nombre, apellido, `partida_individual`,
  `partida_presupuestaria`.
- **RN-50** — `catalogosSociales` (`GET /api/empleados/catalogos-sociales`): los 4 catálogos con
  `activo = true`.
- **RN-51 (2026-09-01)** — `departamentos` (`GET /api/departamentos`): departamentos ordenados por
  nombre, **excluyendo el 999** (antes lo incluía — este endpoint quedó fuera de la corrección del
  2026-08-17 a `Admin/DepartamentoController::index()`).

---

## 6. API

Todas bajo `/api`, grupo `auth:sanctum`. Códigos: 200 salvo indicado.

| Método | Ruta | Función | Rol |
|---|---|---|---|
| GET | `/departamentos` | `departamentos` | autenticado |
| GET | `/empleados/partidas-vacantes` | `partidasVacantes` | autenticado |
| GET | `/empleados/catalogos-sociales` | `catalogosSociales` | autenticado |
| GET | `/empleados` | `index` (paginado, filtros) | autenticado |
| POST | `/empleados` | `store` → 201 | Admin/TH |
| GET | `/empleados/{id}` | `show` | autenticado |
| PUT | `/empleados/{id}` | `update` (422 si `es_externo`) | Admin/TH |
| DELETE | `/empleados/{id}` | `destroy` (soft: `estado=INACTIVO`) | Admin/TH |
| POST | `/empleados/importar-distributivo` | `importarDistributivo` | Admin/TH (inline) |
| POST | `/empleados/{id}/reset-password` | `resetPassword` | Admin/TH (inline) |
| POST | `/empleados/{id}/foto` | `subirFoto` | Admin/TH ✅ |
| DELETE | `/empleados/{id}/foto` | `eliminarFoto` | Admin/TH ✅ |
| POST | `/cambiar-password` | `cambiarPassword` | empleado (propio) |
| GET | `/empleados/{id}/hijos` | `hijoIndex` | Admin/TH ✅ |
| POST | `/empleados/{id}/hijos` | `hijoStore` → 201 | Admin/TH ✅ |
| DELETE | `/empleados/{id}/hijos/{hijoId}` | `hijoDestroy` | Admin/TH ✅ |
| POST | `/empleados/{id}/sustituta-doc` | `subirDocSustituta` (502 si Alfresco falla) | Admin/TH ✅ |
| GET | `/empleados/{id}/sustituta-doc` | `descargarDocSustituta` (404 si no hay) | Admin/TH ✅ |
| DELETE | `/empleados/{id}/sustituta-doc` | `eliminarDocSustituta` | Admin/TH ✅ |
| GET | `/empleados/{id}/teletrabajo` | `teletrabajoIndex` | Admin/TH ✅ |
| POST | `/empleados/{id}/teletrabajo` | `teletrabajoStore` → 201 | Admin/TH ✅ |
| DELETE | `/empleados/{id}/teletrabajo/{periodoId}` | `teletrabajoDestroy` | Admin/TH ✅ |

✅ = **cerrado el 2026-09-01** con `requireRole($request, self::ROLES_ADMIN)` (antes: cualquier
autenticado — ver §10.1).

### 6.1 Contrato `POST /empleados` (resumen)

Request (JSON) — campos obligatorios en **negrita**:

```
identificacion*, nombre_emp*, apellido_emp*, id_depto*, cargo_empleado*,
grupo_ocupacional*, nivel*, sueldo*, partida_presupuestaria*, partida_individual*,
proceso_institucional*, modalidad_laboral*,
fecha_ingreso, estado, email, tipo_contrato, jornada_id, id_jornada, ubicacion,
telefono, extension, calle_y_numero, programa, actividad, sexo, tipo_sangre,
num_sercop, fecha_vence_sercop, grupo_vulnerable_id, grupo_prioritario_id,
tiene_discapacidad, tipo_discapacidad_id, porcentaje_discapacidad,
tiene_enfermedad_catastrofica, enfermedad_catastrofica_id, tiene_persona_sustituta,
sustituta_fecha_caducidad, num_hijos_mayores, banco, tipo_cuenta, numero_cuenta
```

Response 201: objeto `Empleado` + `departamento` + `emails`.
Errores: 422 validación; 403 rol.

---

## 7. UI

Rutas Vue (`frontend/src/router/index.js`, dentro de `MainLayout`):

| Ruta | Componente | Descripción |
|---|---|---|
| `empleados` | `EmpleadosIndex.vue` | Listado con buscador + filtros + paginación. |
| `empleados/crear` | `EmpleadoForm.vue` | Alta. |
| `empleados/:id` | `EmpleadoDetalle.vue` | Ficha de solo lectura. |
| `empleados/:id/editar` | `EmpleadoForm.vue` | Edición. |
| `empleados/importar` | `ImportacionView.vue` | Spec 02. |
| `empleados/distributivo` | `DistributivoView.vue` | Vista del distributivo + carga CSV legado. |
| `empleados/reporte` | `ReporteEmpleadosView.vue` | Spec 11. |

### 7.1 `EmpleadoForm.vue` — 4 pestañas

Diseño y detalle completo en CLAUDE.md (`views/empleados/`). Resumen:

- **Tab 1 "Datos Personales":** nombres, apellidos, cédula, teléfono, extensión (opcional), email,
  dirección, sexo, tipo de sangre + grupo vulnerable + grupo prioritario + bloque Discapacidad
  (toggle → tipo CONADIS + %) + bloque Enfermedad Catastrófica (toggle → tipo MSP) + bloque Persona
  Sustituta (toggle → fecha caducidad + subir/ver/eliminar PDF) + bloque Hijos (`num_hijos_mayores`
  + lista dinámica de menores con fecha de nacimiento y badge "Guardería" si <5) + bloque Datos
  Bancarios (banco, tipo de cuenta, número).
- **Tab 2 "Cargo y Contrato":** departamento, cargo, tipo de contrato, modalidad laboral, jornada,
  estado, fecha de ingreso, fecha de salida (v-if INACTIVO), salario, `motivo_salida` (v-if
  INACTIVO), `institucion_comision`, `motivo_reactivacion` (v-if ACTIVO con salida previa).
- **Tab 3 "Datos del Puesto":** grupo ocupacional, grado, proceso institucional, estado del puesto,
  partida individual (+ botón "Seleccionar libre" → modal con `partidas-vacantes`), programa,
  actividad, décimos (selector único), fondos de reserva, estructura programática, N° SERCOP,
  vigencia SERCOP.
- **Tab 4 "Asistencia":** `modalidad_marcacion` (radio cards PRESENCIAL/TEMPORAL/TELETRABAJO/
  BIOMETRICO), `puede_solicitar_vehiculo`, y —si modalidad = TELETRABAJO en edición— la sección
  "Períodos de Teletrabajo Habilitados" (tabla historial + agregar período inline).

**Regla de UI crítica (Vue 3):** no usar `required` en inputs dentro de tabs con `v-show` — el
navegador falla al hacer focus en campos ocultos y bloquea el submit sin mensaje. Validación manual
al inicio de `guardar()` dentro del `try`; si hay errores, saltar `tabActivo` al primer tab con
error. Campos numéricos que llegan como número desde la BD: `String(val ?? '').trim()`.

### 7.2 Estados vacíos y errores

- `es_externo = true` en edición → banner amarillo de advertencia; el submit responde 422.
- Cédula duplicada → 422 mostrado en Tab 1.

---

## 8. Criterios de aceptación

**Alta**
- **CA-01-1** — Dado un usuario TH, cuando crea un empleado con todos los obligatorios y una cédula
  nueva, entonces se crea con `id_emp` correlativo de 5 dígitos, `estado = ACTIVO`, `password =
  bcrypt(cédula)`, una fila en `d2_cabecera_vacacion` con contadores en 0, y un registro de
  auditoría `CREAR`.
- **CA-01-2** — Dado un intento de alta con una cédula ya existente, entonces HTTP 422 y no se crea nada.
- **CA-01-3** — Dado un usuario sin rol Admin/TH, cuando llama `POST /empleados`, entonces HTTP 403.
- **CA-01-4** — Dado que se crea/edita un empleado con `partida_individual = X`, y existe otro
  empleado `INACTIVO` + `DISPONIBLE` con `partida_individual = X`, entonces ese otro pasa a
  `estado_puesto = OCUPADO`.

**Edición**
- **CA-01-5** — Dado un empleado con `es_externo = true`, cuando TH intenta `PUT /empleados/{id}`,
  entonces HTTP 422 con el mensaje de funcionarios externos y ningún campo cambia.
- **CA-01-6** — Dado que TH cambia el `sueldo` de 1000 a 1200, entonces la auditoría `ACTUALIZAR`
  registra `datos_anteriores.sueldo = 1000` y `datos_nuevos.sueldo = 1200`.
- **CA-01-7** — Dado que TH pone `estado = INACTIVO` vía `update`, entonces `estado_puesto` queda
  `DISPONIBLE` aunque no se haya enviado ese campo.
- **CA-01-8** — Dado que se envía un `email` nuevo, entonces los `ad_empleado_mail` previos del
  empleado quedan `estado = INACTIVO` y hay exactamente uno `ACTIVO` con el nuevo valor.

**Listado / detalle**
- **CA-01-9** — El listado nunca incluye empleados con `id_depto = 999`.
- **CA-01-10** — El filtro `buscar` encuentra por coincidencia parcial (ILIKE) en nombre, apellido
  o identificación.
- **CA-01-11** — `show` devuelve cada hijo con `anos` y `guarderia`, donde `guarderia = true` sii
  el hijo tiene menos de 5 años a la fecha actual.

**Foto / hijos / sustituta / teletrabajo**
- **CA-01-12** — Al subir una foto nueva, la anterior se elimina del disco `public`.
- **CA-01-13** — Al subir el PDF de persona sustituta con éxito, `tiene_persona_sustituta` pasa a
  `true` y se guardan `sustituta_alfresco_id` y `sustituta_nombre_archivo`.
- **CA-01-14** — Si Alfresco no acepta la subida de la sustituta, HTTP 502 y no se modifica el empleado.
- **CA-01-15** — `teletrabajoStore` con `fecha_hasta < fecha_desde` → HTTP 422.
- **CA-01-16** — `hijoStore` con `fecha_nacimiento` futura → HTTP 422.

**Contraseñas**
- **CA-01-17** — `resetPassword` por un usuario sin rol Admin/TH → HTTP 403.
- **CA-01-18** — `cambiarPassword` con `password_actual` incorrecta → HTTP 422 y la contraseña no cambia.
- **CA-01-19** — `cambiarPassword` con `password_confirmar` distinta de `password_nuevo` → HTTP 422.

**Distributivo CSV**
- **CA-01-20** — Una fila cuyo `IDENTIFICACION` no corresponde a ningún empleado se lista en
  `no_encontrados` y el resto de filas sí se procesan.
- **CA-01-21** — Una cédula escrita sin ceros iniciales (p. ej. `987654321`) se normaliza a 10
  dígitos y encuentra al empleado.

---

## 9. Casos borde

| Caso | Comportamiento esperado |
|---|---|
| Dos altas simultáneas (dos admins, o alta + importación) | `Empleado::generarSiguienteId()` serializa con `pg_advisory_xact_lock()` dentro de la transacción de `store()` (2026-09-01) — ya no chocan contra la PK. |
| Empleado sin `fecha_ingreso` al crear | `d2_cabecera_vacacion.fecha_proceso = hoy`. |
| Editar empleado y **no** enviar campos personales | Se conservan (merge `?? $emp->campo`). |
| Enviar `banco = ""` en update | `banco` pasa a `null` (RN-17). |
| `destroy` sobre un empleado ya INACTIVO | Idempotente: queda INACTIVO + `estado_puesto = DISPONIBLE` + auditoría `DESACTIVAR` (2026-09-01). |
| Subir foto > 2 MB | 422 (`max:2048` KB). |
| PDF de sustituta que no es PDF real pero tiene extensión `.pdf` | `mimes:pdf` valida por MIME; se rechaza si el MIME no es PDF. |
| Dos períodos de teletrabajo solapados | Ambos se guardan; spec 03 decide validez en marcación (basta con que hoy caiga en alguno). |
| `eliminarDocSustituta` | Limpia los 4 campos de sustituta juntos (2026-09-01) — el flag ya no queda "encendido" sin documento. |

---

## 10. Deuda técnica / hallazgos

1. ✅ **RESUELTO (2026-09-01)** — Endpoints de sub-recursos sin `requireRole`: `subirFoto`,
   `eliminarFoto`, `hijoStore`, `hijoDestroy`, `subirDocSustituta`, `descargarDocSustituta`,
   `eliminarDocSustituta`, `teletrabajoStore`, `teletrabajoDestroy` (y los `*Index` según la
   implementación). Cerrados con `$this->requireRole($request, self::ROLES_ADMIN)` al inicio de cada
   método (gap fuera del alcance de la auditoría de seguridad del 2026-08-17; ver CLAUDE.md
   §"Gaps encontrados y cerrados el 2026-09-01").
2. ✅ **RESUELTO (2026-09-01)** — `destroy` ahora audita `DESACTIVAR` y fuerza
   `estado_puesto = DISPONIBLE` (coherente con `update`).
3. ✅ **RESUELTO (2026-09-01)** — `eliminarDocSustituta` limpia los 4 campos de sustituta juntos
   (`sustituta_alfresco_id`, `sustituta_nombre_archivo`, `tiene_persona_sustituta`,
   `sustituta_fecha_caducidad`).
4. ✅ **RESUELTO (2026-09-02)** — `importarDistributivo`: ahora transaccional
   (`DB::beginTransaction`/`commit`/`rollBack`), audita `IMPORTACION_DISTRIBUTIVO`, y las dos columnas
   homónimas "PARTIDA INDIVIDUAL" se resuelven con `array_keys($header, ...)` + 422 si no aparecen
   (antes índices fijos 0 y 9).
5. ✅ **RESUELTO (2026-09-01)** — `Empleado::generarSiguienteId()` (nuevo) con `pg_advisory_xact_lock()`
   dentro de la transacción de `store()`; también usado por `ImportacionController::importar()`.
6. ✅ **RESUELTO (2026-09-01)** — `jornada_id` dejó de escribirse en `store()`/`update()` (era un
   duplicado muerto de `id_jornada`, que es el que alimenta la relación `jornada()` usada en Permisos
   / Horas Extras). La columna sigue en BD (sin cambio de schema), solo se dejó de escribir el `null`.
7. ✅ **RESUELTO (2026-09-01)** — `EmpleadoController::departamentos()` ahora excluye el 999.

---

## 11. Dependencias

- **Spec 00** — reglas transversales, auditoría, roles, Alfresco.
- **Spec 03** — consume `modalidad_marcacion` y `ad_empleado_teletrabajo`.
- **Spec 05** — consume `fecha_ingreso`, `tipo_contrato`, `modalidad_laboral`, `fecha_salida`,
  `d2_cabecera_vacacion`; aquí solo se inicializa la cabecera.
- **Spec 09** — consume `acumula_*`, `sueldo`, `tipo_contrato`, `programa`, `actividad`.
- **Spec 02** — importación masiva (47 columnas) hace upsert sobre esta misma tabla.
- `RolController` — asignación de roles (rutas `/empleados/{id}/roles`), fuera de esta spec.
- Alfresco — `config('services.alfresco.*')`.
- Storage local `public` — fotos (`php artisan storage:link` requerido en el servidor).

---

## 12. Preguntas abiertas

- ~~¿Cerrar los sub-recursos / auditar `destroy` / unificar `jornada_id`?~~ ✅ hechos 2026-09-01.
- ¿`destroy` debería además pedir `motivo_salida` (hoy solo audita el cambio de estado)?
- ¿Eliminar de la BD la columna `jornada_id` ya muerta (requiere migración)?
- ¿La importación de distributivo debería fusionarse con la importación masiva de la spec 02 (que ya
  cubre estos campos) y deprecarse?
