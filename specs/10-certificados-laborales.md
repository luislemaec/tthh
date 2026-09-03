# Spec 10 — Certificados Laborales

| | |
|---|---|
| **Versión** | 1.0 (borrador) |
| **Última actualización** | 2026-09-03 |
| **Depende de** | [00 — Módulo Talento Humano](00-modulo-talento-humano.md), [01 — Empleados](01-empleados.md) |
| **Controlador** | `app/Http/Controllers/CertificadoLaboralController.php` |
| **Modelo** | `App\Models\CertificadoLaboral` (`dbo.d2_certificado_laboral`) |
| **Vista** | `views/certificados/CertificadosView.vue` (ruta `certificados-laborales`) |
| **PDF** | `reportes/certificado_laboral.blade.php` (portrait A4) |
| **Migraciones** | `..._000072_create_certificado_laboral_table.php` · `..._000106_add_estado_to_d2_certificado_laboral.php` (anulación, 2026-09-03) |
| **Tipo** | Retro-especificación |

---

## 1. Propósito

Emitir y archivar el **certificado laboral** de un servidor: documento oficial que acredita que la
persona presta (o prestó) servicios en la institución, desde qué fecha, en qué cargo y con qué
remuneración. Se genera en PDF con numeración correlativa anual, se descarga para imprimir y firmar,
y el **PDF firmado escaneado** se sube a Alfresco.

### 1.1 Alcance

Incluye: emisión (numeración + PDF + registro en BD + auditoría), re-descarga del PDF **sin firmar**
en cualquier momento, historial paginado con filtros, subida del PDF firmado a Alfresco, re-descarga
del firmado, **anulación** de un certificado emitido por error.

**No** cubre:
- El **certificado de días de vacaciones** por comisión de servicios — ese es
  `LiquidacionVacController::generarCertificado` (**spec 07**), documento distinto.
- Cualquier flujo de aprobación / revisión: el certificado se emite directamente por TH.
- Firma electrónica: la firma es **manual** (imprimir → firmar → escanear → subir).

---

## 2. Actores

| Actor | Puede |
|---|---|
| `ADMINISTRADOR`, `TALENTO HUMANO` | Emitir, listar, subir firmado, re-descargar. |

Los 6 endpoints verifican `esAdminOTH($request->user()->id_emp)` **inline** (no `requireRole`) →
HTTP 403 si no cumple. El empleado **no** tiene acceso (no puede solicitar ni ver sus certificados).

---

## 3. Historias de usuario

- **HU-10.1** — Como TH, quiero emitir el certificado laboral de un empleado y descargar el PDF para imprimirlo.
- **HU-10.2** — Como TH, quiero que el texto del certificado se adapte al género del empleado y a si está activo o inactivo.
- **HU-10.3** — Como TH, quiero que cada certificado tenga un número oficial correlativo del año.
- **HU-10.4** — Como TH, quiero subir el PDF ya firmado y escaneado, y que quede archivado en Alfresco.
- **HU-10.5** — Como TH, quiero re-descargar el certificado firmado de cualquier emisión anterior.
- **HU-10.6** — Como TH, quiero un historial de certificados emitidos con filtros por empleado y fecha.

---

## 4. Modelo de datos — `dbo.d2_certificado_laboral`

PK `id` (bigint autoincremental). `fecha_emision` casteada a `date`. `timestamps()` (created/updated automáticos).

| Campo | Contenido |
|---|---|
| `numero` VARCHAR(25), **unique** | `DATH-CL-{SEQ}-{AÑO}` (SEQ = 3 dígitos, reinicia cada año). |
| `id_emp` VARCHAR(20) | Empleado certificado. Relación `empleado()`. |
| `fecha_emision` `date` | Fecha de emisión (= día de la emisión). |
| `alfresco_id` VARCHAR(100) NULL | Node ID del PDF **firmado** en Alfresco. `null` hasta que se suba. |
| `nombre_archivo` VARCHAR(200) NULL | `certificado_laboral_{numero}.pdf`. |
| `usuario_emision` VARCHAR(20) | `id_emp` del usuario que emitió. Relación `emisor()` (a `Empleado` por `usuario_emision`). |
| `estado` VARCHAR(20) NOT NULL DEFAULT `'EMITIDO'` | `EMITIDO` / `ANULADO` (mig. `000106`). |
| `observacion_anulacion` VARCHAR(300) NULL | Motivo de la anulación. |
| `anulado_en` TIMESTAMP NULL, `anulado_por` VARCHAR(20) NULL | Cuándo y quién anuló. |
| `created_at`, `updated_at` | Timestamps. |

> El **PDF sin firmar no se persiste** en Alfresco ni en disco, pero **se puede re-generar en
> cualquier momento** con `GET /{id}/pdf` (2026-09-03) — la plantilla y los datos se arman en el
> helper `construirPdf()`, compartido por `store()` y `pdf()`. Solo el **firmado** se guarda (en
> Alfresco) tras `subir-firmado`.

### 4.1 Configuración (`dbo.d2_configuracion`, leída con `LOWER(concepto)`)

| Concepto | Uso en el PDF | Default si falta |
|---|---|---|
| `nombre_institucion` | Encabezado + "EL RESPONSABLE DE TALENTO HUMANO DEL {inst}" + pie de firma | `CONSEJO DE COMUNICACIÓN` |
| `firmante_th_nombre` | Nombre bajo la línea de firma | `''` |
| `firmante_th_cargo` | Cargo bajo la línea de firma | `RESPONSABLE DE TALENTO HUMANO` |
| `ciudad_institucion` | Ciudad en la línea de fecha ("{ciudad}, {fecha}") | `Quito` |

---

## 5. Reglas de negocio

### 5.1 Emisión (`POST /api/certificados-laborales` — `store`)

Request: `id_emp*` (string).

- **RN-10.1** — Requiere rol Admin/TH (403 si no).
- **RN-10.2 (2026-09-03)** — `Empleado::where('id_depto', '!=', 999)->find($id_emp)` — 404 si no
  existe / es del depto 999. Si `empleado.es_externo` → HTTP 422 ("no se emiten certificados a
  Funcionarios Externos"). Antes no filtraba nada.
- **RN-10.3 (numeración — 2026-09-03)** — `CertificadoLaboral::generarSiguienteNumero($año)`:
  `pg_advisory_xact_lock(963258741)` + `MAX(CAST(SPLIT_PART(numero,'-',3) AS INTEGER)) + 1` sobre las
  filas con `whereYear('fecha_emision', $año)` → `numero = "DATH-CL-{SEQ 3 díg}-{año}"`. **Reinicia
  en `001` cada año.** La generación + el `create()` corren dentro de `DB::transaction()` (el
  advisory lock vive mientras dure la transacción). Antes: `MAX+1` sin bloqueo → dos emisiones
  simultáneas chocaban contra `unique(numero)` con un 500.
- **RN-10.4 (texto del PDF — género y estado)** — el blade calcula:
  - `sexo = MAYÚSCULAS(empleado.sexo)`. `FEMENINO` → "la señora" / "portadora" / "de la interesada";
    cualquier otro valor **o NULL** → "el señor" / "portador" / "del interesado" (masculino por defecto).
  - `verbo` = "presta" si el empleado está `ACTIVO`, "prestó" si no.
  - Si `!activo && fecha_salida` → agrega "hasta el {fecha_salida en español}".
- **RN-10.5 (fechas en español)** — array manual de meses (no `Carbon::translatedFormat()`):
  `"{día} de {mes} de {año}"` para la fecha de emisión (hoy), la de ingreso y la de salida.
- **RN-10.6 (contenido fijo)** — el certificado indica: nombre completo (APELLIDO NOMBRE), cédula,
  fecha de ingreso, cargo (`cargo_empleado`), RMU (`sueldo`, formato `$ 0,000.00`). Cierre fijo:
  "El presente certificado se expide a petición {del interesado/de la interesada}, para los fines
  que estime conveniente." **No hay campo "motivo".**
- **RN-10.7 (persistencia)** — Crea la fila en `d2_certificado_laboral` con `alfresco_id = null`,
  `estado = 'EMITIDO'`, `nombre_archivo = certificado_laboral_{numero}.pdf`,
  `usuario_emision = actor.id_emp`, `fecha_emision = hoy`.
- **RN-10.8 (auditoría)** — `AuditoriaService::log('dbo.d2_certificado_laboral', $cert->id, 'EMITIR', null, {numero, id_emp}, ...)`.
- **RN-10.9 (respuesta)** — HTTP 200 con el **PDF como cuerpo** (`Content-Type: application/pdf`,
  `Content-Disposition: attachment`), más headers `X-Certificado-Id` y `X-Numero` (los lee el
  frontend para mostrar el número y ligar el "subir firmado").

### 5.2 Re-descarga del PDF sin firmar (`GET /api/certificados-laborales/{id}/pdf` — `pdf`) — nuevo 2026-09-03

- **RN-10.9b** — Rol Admin/TH. Regenera el PDF **sin firmar** de un certificado existente con
  `construirPdf()` (misma plantilla/datos que `store()`) y lo hace `stream()`. Funciona en cualquier
  `estado` (incluido `ANULADO`). Resuelve el caso de que TH pierda el archivo descargado antes de
  firmarlo sin tener que re-emitir (consumir otro número).

### 5.3 Subida del firmado (`POST /api/certificados-laborales/{id}/subir-firmado` — `subirFirmado`)

- **RN-10.10** — Rol Admin/TH. `archivo` `required|file|mimes:pdf|max:10240` (KB).
- **RN-10.11** — Si `estado === 'ANULADO'` → HTTP 422 (2026-09-03). Sube a Alfresco en
  `certificados-laborales/{año de fecha_emision}/{cédula}_{PRIMER_APELLIDO}` (vía `relativePath` +
  `autoRename`), con el nombre `nombre_archivo` de la fila. Guarda `alfresco_id = entry.id`. Se puede
  **re-subir** (sobrescribe el `alfresco_id`; el nodo viejo queda en Alfresco por `autoRename`). 502
  si Alfresco falla.
- **RN-10.12 (2026-09-03)** — Registra `SUBIR_FIRMADO` en `nom_auditoria_log`.

### 5.4 Anulación (`PATCH /api/certificados-laborales/{id}/anular` — `anular`) — nuevo 2026-09-03

- **RN-10.12b** — Rol Admin/TH. `observacion` **requerido** (≤300). El certificado no debe estar ya
  `ANULADO` (422). Fija `estado = 'ANULADO'`, `observacion_anulacion`, `anulado_en = now()`,
  `anulado_por = actor.id_emp`. Auditoría `ANULAR`.
- **RN-10.12c** — El `numero` anulado **no se libera ni se reutiliza** — la fila queda como
  constancia de que el certificado existió y se invalidó (mismo criterio que `numero_accion` en
  Acciones de Personal). No hay "des-anular".

### 5.5 Re-descarga del firmado (`GET /api/certificados-laborales/{id}/descargar` — `descargar`)

- **RN-10.13** — Rol Admin/TH. 404 si `alfresco_id` es `null` ("el PDF firmado no ha sido subido
  aún"). Si hay, stream del contenido desde Alfresco (`Content-Disposition: attachment`). 502 si Alfresco falla.

### 5.6 Historial (`GET /api/certificados-laborales` — `index`)

- **RN-10.15** — Rol Admin/TH. `CertificadoLaboral` con `empleado` + `emisor`, ordenado por
  `fecha_emision` desc, `id` desc, paginado **30/página**. Devuelve también `estado`.
- **RN-10.16** — Filtros: `id_emp` (⚠ **no** es match exacto — hace ILIKE sobre
  `identificacion` / `apellido_emp` / `nombre_emp`), `fecha_desde` (`fecha_emision >=`),
  `fecha_hasta` (`fecha_emision <=`).

---

## 6. UI

`CertificadosView.vue` (ruta `certificados-laborales`, roles TH / ADMINISTRADOR).

- **Panel superior:** buscador de empleado (debounce 300 ms) + dropdown de resultados → al
  seleccionar, chip verde con datos + botón "Cambiar". Botón **"Generar Certificado"** → modal de
  confirmación → `POST /certificados-laborales` → respuesta blob PDF + descarga directa. El número se
  toma del header `X-Numero`.
- **Panel de filtros:** búsqueda de empleado, `fecha_desde`, `fecha_hasta` (default: 1-ene → hoy).
- **Historial** paginado (30/pág): N° de certificado (mono verde), empleado + cédula, cargo, fecha de
  emisión, emitido por, `estado`, y acciones:
  - **"Ver PDF"** → `GET /{id}/pdf` (regenera el sin-firmar) — en cualquier estado.
  - Sin firmar (`alfresco_id = null`) y `estado = EMITIDO` → botón azul **"Subir firmado"** → `POST /{id}/subir-firmado`.
  - Firmado → botón rojo **"PDF firmado"** → `GET /{id}/descargar` (blob URL).
  - `estado = EMITIDO` → **"Anular"** (modal con motivo obligatorio) → `PATCH /{id}/anular`. Un certificado `ANULADO` se muestra con badge y sin acciones de subida.

Mismo flujo de firma que Horas Extras / Acciones de Personal: se genera y descarga, se firma
físicamente, luego se sube el escaneado.

---

## 7. Criterios de aceptación

- **CA-10-1** — Un usuario sin rol Admin/TH que llama cualquier endpoint → HTTP 403.
- **CA-10-2** — `store` con un `id_emp` inexistente / del depto 999 → HTTP 404; con un `es_externo = true` → HTTP 422.
- **CA-10-3** — `store` de un empleado `FEMENINO` activo → el PDF dice "la señora … portadora … presta sus servicios … a petición de la interesada".
- **CA-10-4** — `store` de un empleado con `sexo = NULL` → el PDF usa el masculino ("el señor … portador … del interesado").
- **CA-10-5** — `store` de un empleado `INACTIVO` con `fecha_salida` → el PDF dice "prestó sus servicios … desde el {ingreso} hasta el {salida}".
- **CA-10-6** — El primer certificado de un año nuevo tiene `numero = DATH-CL-001-{año}`.
- **CA-10-7** — Emitir dos certificados el mismo año → el segundo es `…-002-…`. Dos emisiones simultáneas → números distintos (advisory lock, 2026-09-03).
- **CA-10-8** — Cada emisión crea exactamente una fila (`estado = EMITIDO`, `alfresco_id = null`) y un registro `EMITIR` en `nom_auditoria_log`.
- **CA-10-9** — La respuesta de `store` trae el PDF en el cuerpo + headers `X-Certificado-Id` y `X-Numero`.
- **CA-10-9b (2026-09-03)** — `GET /{id}/pdf` de un certificado ya emitido devuelve el mismo PDF sin firmar, con el mismo `numero`, en cualquier estado.
- **CA-10-10** — `subir-firmado` con un PDF → `alfresco_id` deja de ser `null` + registro `SUBIR_FIRMADO` en auditoría; la carpeta Alfresco es `certificados-laborales/{año}/{cédula}_{APELLIDO}`.
- **CA-10-10b (2026-09-03)** — `subir-firmado` sobre un certificado `ANULADO` → HTTP 422.
- **CA-10-11** — `descargar` de un certificado sin `alfresco_id` → HTTP 404.
- **CA-10-12** — Si Alfresco está caído, `subir-firmado` y `descargar` responden HTTP 502.
- **CA-10-13** — `index` con `id_emp = "perez"` devuelve los certificados de cualquier empleado cuyo apellido/nombre/cédula contenga "perez".
- **CA-10-14 (2026-09-03)** — `PATCH /{id}/anular` con `observacion` → `estado = ANULADO`, `anulado_por`/`anulado_en` guardados, registro `ANULAR`. Anular sin `observacion` → 422; anular un ya `ANULADO` → 422.

---

## 8. Casos borde

| Caso | Comportamiento |
|---|---|
| Dos emisiones simultáneas | Números distintos — `generarSiguienteNumero()` con `pg_advisory_xact_lock` dentro de `DB::transaction()` (2026-09-03). |
| Empleado sin `fecha_ingreso` | `Carbon::parse(null)` = hoy → el certificado dice "desde el {hoy}". *(abierto — §10.8)* |
| Empleado `es_externo = true` o `id_depto = 999` | Rechazado (2026-09-03) — 422 / 404. |
| TH pierde el PDF descargado antes de firmarlo | `GET /{id}/pdf` regenera el sin-firmar con el mismo número (2026-09-03) — ya no hay que re-emitir. |
| Certificado emitido por error | `PATCH /{id}/anular` con motivo → `estado = ANULADO` (2026-09-03). El `numero` no se libera. |
| `subir-firmado` dos veces (mismo certificado, no anulado) | Se acepta; `alfresco_id` apunta al último; el nodo anterior sigue en Alfresco. |
| Empleado con `sueldo = null` | RMU sale como `$ 0.00`. |

---

## 9. Dependencias

- **Spec 00** — roles, `d2_configuracion`, Alfresco (`config('services.alfresco.*')`), PDF estándar,
  `AuditoriaService`.
- **Spec 01** — `ad_empleado` (`sexo`, `estado`, `fecha_ingreso`, `fecha_salida`, `cargo_empleado`,
  `sueldo`, `identificacion`, `apellido_emp`/`nombre_emp`, `departamento`, `jornada`).
- **Spec 07** — el "certificado" de días de vacaciones por comisión es de otro controlador
  (`LiquidacionVacController`), no relacionado con éste.
- **Spec 12 (Auditoría)** — `CertificadoLaboralController` instrumentado: `EMITIR` (desde el inicio),
  `SUBIR_FIRMADO` y `ANULAR` (2026-09-03).
- **Migración `000106`** (`add_estado_to_d2_certificado_laboral`) — `ALTER TABLE ADD COLUMN` de las 4
  columnas de anulación; requiere `php artisan migrate` en el servidor.

---

## 10. Deuda técnica / hallazgos

> **5 hallazgos corregidos el 2026-09-03** (ver CLAUDE.md §"Certificados Laborales"). Único cambio de
> schema: migración `000106` (columnas de anulación).

1. ✅ **RESUELTO (2026-09-03)** — Numeración: `CertificadoLaboral::generarSiguienteNumero()` con
   `pg_advisory_xact_lock(963258741)`; generación + `create()` en `DB::transaction()`.
2. **`esAdminOTH` inline** en los 6 métodos en vez del helper `requireRole` de `Controller.php` —
   inconsistente con el patrón (mismo caso que spec 07 §11.4). *(abierto — menor)*
3. ✅ **RESUELTO (2026-09-03)** — `GET /{id}/pdf` regenera el PDF sin firmar de un certificado
   existente (`construirPdf()` compartido con `store()`). Funciona en cualquier estado.
4. ✅ **RESUELTO (2026-09-03)** — `subirFirmado` registra `SUBIR_FIRMADO` en `nom_auditoria_log`.
5. ✅ **RESUELTO (2026-09-03)** — `store` filtra `id_depto != 999` y rechaza `es_externo` con 422.
6. ✅ **RESUELTO (2026-09-03)** — `PATCH /{id}/anular` (motivo obligatorio) → `estado = ANULADO`
   (mig. `000106`); auditoría `ANULAR`; el `numero` no se libera; un certificado `ANULADO` no admite
   `subir-firmado`.
7. **`index`: el filtro se llama `id_emp` pero hace búsqueda difusa** (ILIKE sobre cédula / nombre /
   apellido). Nombre engañoso; funcionalmente está bien. *(abierto — cosmético)*
8. **`fecha_ingreso` no validada** — si es `null`, `Carbon::parse(null)` devuelve hoy y el
   certificado sale con una fecha de ingreso incorrecta en silencio. *(abierto — menor)*

---

## 11. Preguntas abiertas

- ~~¿Numeración con lock / re-descarga del sin-firmar / auditar subir-firmado / bloquear externos / anulación?~~ ✅ los 5 hechos el 2026-09-03 (§10.1, 3–6).
- ¿Validar que `fecha_ingreso` no sea `null` antes de emitir? (§10.8)
- ¿El certificado debería incluir el tiempo de servicio calculado (años/meses), o el texto actual (solo fechas) es suficiente para el uso real?
