# Spec 02 — Importación masiva de empleados

| | |
|---|---|
| **Versión** | 1.0 (borrador) |
| **Última actualización** | 2026-09-01 |
| **Depende de** | [00 — Módulo Talento Humano](00-modulo-talento-humano.md), [01 — Empleados](01-empleados.md) |
| **Controlador** | `app/Http/Controllers/ImportacionController.php` |
| **Vista** | `frontend/src/views/empleados/ImportacionView.vue` (ruta `empleados/importar`) |
| **Tipo** | Retro-especificación |

---

## 1. Propósito

Cargar o actualizar **muchos empleados a la vez** desde un archivo CSV, para el bootstrap inicial
del sistema y para actualizaciones masivas periódicas (p. ej. cambios de distributivo de toda la
institución). Reemplaza la digitación uno por uno en la ficha (`EmpleadoForm.vue`).

### 1.1 Diferencia con `importarDistributivo` (spec 01 §5.11)

| | Este CSV (spec 02) | `importarDistributivo` (spec 01) |
|---|---|---|
| Crea empleados nuevos | **Sí** (upsert) | No (solo actualiza existentes) |
| Columnas | 47 (todos los campos planos de `ad_empleado`) | ~7 campos del distributivo |
| Transaccional | **Sí** (`DB::transaction`, todo o nada) | No (mejor esfuerzo) |
| Nombres de columna | Por cabecera (`array_combine`) | Por cabecera + índices fijos (0, 9) |
| Preview previo | **Sí** (`preview`) | No |

> **Preguntas abiertas (spec 01 §12):** evaluar deprecar `importarDistributivo` en favor de este.

### 1.2 Alcance

Incluye: plantilla descargable, vista previa (validación sin escribir), importación transaccional
con upsert por cédula, normalización de fechas / `tipo_contrato` / catálogos sociales.

**No** cubre (se gestionan desde la ficha del empleado — spec 01):
- Hijos menores individuales (`ad_empleado_hijo`).
- Documento de persona sustituta (Alfresco).
- Períodos de teletrabajo (`ad_empleado_teletrabajo`).
- Asignación de roles (`admin_usuario_rol`).
- Foto.

---

## 2. Actores

| Actor | Puede |
|---|---|
| `ADMINISTRADOR`, `TALENTO HUMANO` | Descargar plantilla, previsualizar, importar. |

> **Control de acceso (cerrado el 2026-09-01):** los tres métodos (`plantilla`, `preview`,
> `importar`) llaman `requireRole($request, self::ROLES_ADMIN = ['ADMINISTRADOR','TALENTO HUMANO'])`
> al inicio. Antes no tenían ningún control — cualquier autenticado podía crear altas y sobrescribir
> expedientes en masa. Ver §9.1.

---

## 3. Historias de usuario

- **HU-02.1** — Como TH, quiero descargar una plantilla CSV con las columnas correctas y una fila de
  ejemplo para saber qué formato usar.
- **HU-02.2** — Como TH, quiero subir mi CSV y ver una vista previa con los errores detectados y qué
  filas son altas vs. actualizaciones, **antes** de confirmar.
- **HU-02.3** — Como TH, quiero importar el CSV y que, si alguna fila tiene un error grave, **no se
  guarde nada** (para no dejar la carga a medias).
- **HU-02.4** — Como TH, quiero que una cédula ya existente actualice al empleado y una cédula nueva
  cree el empleado con su `id_emp` automático.
- **HU-02.5** — Como TH, quiero que el importador tolere fechas en formato ecuatoriano
  (`DD/MM/AAAA`) y el typo común "CODIGO DE TRABAJO".

---

## 4. Formato del archivo

### 4.1 Columnas (47, en este orden en la plantilla)

Delimitador **coma** (`,`). Codificación con BOM UTF-8 (la plantilla lo incluye). Primera fila =
cabeceras; el mapeo es **por nombre de cabecera**, no por posición (el orden puede variar mientras
los nombres coincidan).

```
identificacion, nombre_emp, apellido_emp, id_depto,
estado, estado_puesto, tipo_contrato, sueldo, nivel,
cargo_empleado, grupo_ocupacional, proceso_institucional,
modalidad_laboral, modalidad_marcacion,
partida_individual, partida_presupuestaria,
programa, actividad,
fecha_ingreso, fecha_salida,
motivo_salida, motivo_reactivacion, institucion_comision,
acumula_decimos, acumula_fondos_reserva,
puede_solicitar_vehiculo,
sexo, tipo_sangre,
num_sercop, fecha_vence_sercop,
banco, tipo_cuenta, numero_cuenta,
telefono, extension, calle_y_numero, email,
grupo_vulnerable, grupo_prioritario,
tiene_discapacidad, tipo_discapacidad, porcentaje_discapacidad,
tiene_enfermedad_catastrofica, enfermedad_catastrofica,
tiene_persona_sustituta, sustituta_fecha_caducidad,
num_hijos_mayores
```

### 4.2 Reglas por campo

| Campo(s) | Regla |
|---|---|
| `identificacion` | Obligatorio. Cédula; al **crear** se rellena a 10 dígitos con ceros a la izquierda (`str_pad`). Al **actualizar** se busca tal cual viene. |
| `nombre_emp`, `apellido_emp` | Obligatorios. Se guardan en MAYÚSCULAS. |
| `id_depto` | Obligatorio, entero. **Validado (2026-09-01)**: `preview()` e `importar()` verifican que exista en `ad_departamento` y no sea `999` — antes se aceptaba cualquier número en silencio. |
| `estado` | Default `ACTIVO`. MAYÚSCULAS. |
| `estado_puesto` | Default `OCUPADO`. MAYÚSCULAS. |
| `tipo_contrato` | Normalizado: tolera tildes / mayúsculas y "CODIGO DE TRABAJO" (sin "DEL"). Resultado exacto: `LOSEP` o `CODIGO DEL TRABAJO`. Valor no reconocible → **error de fila** (aborta la transacción, §5.3). Vacío → `null`. |
| `sueldo` | Float o `null` si vacío. |
| `nivel` | Entero o `null` si vacío. |
| `modalidad_marcacion` | Default `PRESENCIAL`. MAYÚSCULAS. (Valores válidos: ver spec 03; **no** se valida el enum aquí.) |
| `programa`, `actividad`, `sexo`, `tipo_sangre`, `banco`, `tipo_cuenta` | MAYÚSCULAS; `null` si vacío. |
| `fecha_ingreso`, `fecha_salida`, `fecha_vence_sercop`, `sustituta_fecha_caducidad` | Formatos aceptados: `DD/MM/AAAA`, `AAAA-MM-DD`, `DD-MM-AAAA`, `DD/MM/AA`. Se guardan como `AAAA-MM-DD`. Formato irreconocible → **error de fila**. Vacío → `null`. |
| `acumula_decimos` | Una sola columna. `(bool)` del valor → se aplica **a la vez** a `acumula_decimo_tercero` y `acumula_decimo_cuarto`. (`0`/vacío = false = cobra mensual; cualquier otro = true = acumula.) |
| `acumula_fondos_reserva` | Entero (default 0). |
| `puede_solicitar_vehiculo`, `acumula_decimos`, `tiene_discapacidad`, `tiene_enfermedad_catastrofica`, `tiene_persona_sustituta` | Se evalúan con `$boolSi()` (2026-09-10): `true` solo si el valor es `SI`/`SÍ`/`1`/`TRUE`/`X` (mayús/minús indistinto); `NO`, `0`, vacío → `false`. Antes `puede_solicitar_vehiculo` y `acumula_decimos` usaban `(bool)` del string → `"NO"` daba `true` (ver §9). |
| `porcentaje_discapacidad` | Float o `null`. |
| `num_hijos_mayores` | Entero (default 0). |
| `grupo_vulnerable`, `grupo_prioritario`, `tipo_discapacidad`, `enfermedad_catastrofica` | Se escribe el **nombre** (no el ID). Se resuelve contra el catálogo real (`ad_grupo_vulnerable` / `ad_grupo_prioritario` / `ad_tipo_discapacidad` / `ad_enfermedad_catastrofica`) case-insensitive. Si no coincide → se deja `null` y se agrega un **aviso** a `errores[]` (no aborta). Vacío → `null`. |
| `email` | Opcional. Si viene, se maneja como en spec 01 (desactiva los previos, crea uno `ACTIVO`). |
| `telefono`, `extension`, `calle_y_numero`, `num_sercop`, `numero_cuenta`, `institucion_comision`, `motivo_salida`, `motivo_reactivacion` | Texto tal cual; `null` si vacío (los últimos cuatro). |

---

## 5. Comportamiento por endpoint

### 5.1 `GET /api/importacion/plantilla` — `plantilla()`

- **RN-02.1** — Devuelve un stream `text/csv` (`Content-Disposition: attachment; filename=plantilla_empleados.csv`) con BOM UTF-8, la fila de cabeceras (`self::COLUMNAS`) y una fila de ejemplo (`self::EJEMPLO`).
- **RN-02.1b (2026-09-01)** — Requiere rol `ADMINISTRADOR`/`TALENTO HUMANO` (`requireRole`), igual que `preview` e `importar`.

### 5.2 `POST /api/importacion/preview` — `preview()`

- **RN-02.2** — Valida `archivo` `required|file|mimes:csv,txt|max:5120` (KB).
- **RN-02.3** — Lee todas las filas. Por cada una:
  - Si el número de columnas ≠ número de cabeceras → `errores[] = "Fila N: número de columnas incorrecto"` y se salta.
  - Valida presencia de `identificacion`, `nombre_emp`, `apellido_emp`, `id_depto` → agrega error por cada faltante (pero **igual incluye la fila** en `filas`).
  - Agrega `_existe` = `true` si ya hay un empleado con esa `identificacion`.
- **RN-02.4** — Respuesta: `{ filas: [...], errores: [...], total: <nº de filas> }`. **No escribe nada.**

### 5.3 `POST /api/importacion/importar` — `importar()`

- **RN-02.5** — Valida `archivo` igual que preview. Requiere rol Admin/TH (`requireRole`, 2026-09-01).
- **RN-02.6** — Precarga los 4 catálogos sociales en memoria (`nombre en MAYÚSCULAS → id`).
- **RN-02.7** — **Toda la importación en una transacción** (`DB::beginTransaction`). Por cada fila de datos:
  - Nº de columnas incorrecto → `errores[]` y `continue` (no aborta).
  - Se arma el array `$campos` con todas las transformaciones de §4.2.
  - `$normalizarContrato` o `$parseFecha` lanzan `\Exception` → se captura **por fila**:
    `errores[] = "Fila N (cédula): <mensaje>"` y `continue`. *(La fila no se guarda, pero la
    transacción continúa.)*
  - **`es_externo = true` (2026-09-01):** si la cédula coincide con un empleado `es_externo = true`,
    la fila se **omite** con un error legible ("es Funcionario Externo, se omite") en vez de
    sobrescribirlo. Antes el upsert lo actualizaba como a cualquier empleado, contradiciendo la regla
    de que solo se editan desde `FuncionariosExternosView` (Comisiones).
  - **Upsert:**
    - Cédula existe → `$empleado->update($campos)` (sobrescribe **todos** los campos, incluidos los
      que quedaron `null`). `actualizados++`. Si viene `email`, se rota.
    - Cédula nueva → `id_emp = Empleado::generarSiguienteId()` (con `pg_advisory_xact_lock()`,
      dentro de la transacción — 2026-09-01; antes `max(id_emp)+1` sin bloqueo), `identificacion`
      rellenada a 10 dígitos, `password = bcrypt(identificacion_original)`, `Empleado::create()`. Se
      crea `d2_cabecera_vacacion` (saldo 0). Si viene `email`, se crea `ACTIVO`. `importados++`.
- **RN-02.8** — Si se lanza una excepción **fuera** del try por fila (p. ej. error de BD irrecuperable)
  → `DB::rollBack()` y respuesta `{ message: "Error: ..." }` HTTP 500. **Nada se guarda.**
- **RN-02.9** — Al terminar el bucle → `DB::commit()`.
- **RN-02.10** — Respuesta 200: `{ message, importados, actualizados, errores: [...], total: importados+actualizados }`.
- **RN-02.11 (2026-09-01)** — Registra **una** entrada `IMPORTACION_MASIVA` en `nom_auditoria_log`
  por cada importación, con el conteo de importados / actualizados / errores y el nombre del archivo.
  Antes no dejaba ningún rastro. `ImportacionController` figura ahora en la tabla de controladores
  instrumentados de la Auditoría Centralizada.

---

## 6. UI

`ImportacionView.vue` (ruta `empleados/importar`). Flujo:

1. Botón **"Descargar plantilla"** → `GET /importacion/plantilla`.
2. Selector de archivo + botón **"Vista previa"** → `POST /importacion/preview`. Muestra tabla con
   `_existe` (badge "Nuevo" / "Actualiza") y la lista de `errores`.
3. Botón **"Importar"** (habilitado tras la preview) → `POST /importacion/importar`. Muestra
   `importados`, `actualizados` y `errores`.

### 6.1 Advertencia de Excel (ceros a la izquierda / notación científica)

Si la cédula o `partida_presupuestaria` se editan en Excel sin formatear la columna como **Texto**,
Excel puede quitar el cero inicial o pasar a notación científica (`2.02622E+44`). El importador
**no** intenta recuperar esto: si la columna llegó mal, se guarda mal o no encuentra al empleado.
La vista debe recordar al usuario formatear como Texto antes de pegar.

---

## 7. Criterios de aceptación

- **CA-02-1** — La plantilla descargada abre en Excel con las 47 cabeceras correctas y una fila de ejemplo, sin corrupción de tildes (BOM presente).
- **CA-02-2** — `preview` de un CSV con una fila a la que le falta `id_depto` devuelve esa fila en `filas` y un `errores[]` con "Fila N: id_depto requerido", y **no** crea ningún empleado.
- **CA-02-3** — Dado un CSV con 10 filas válidas y 1 con fecha inválida, `importar` guarda las 10 válidas, reporta la fallida en `errores[]` y responde `total = 10`. *(La fila con error no aborta la transacción — solo se omite.)*
- **CA-02-4** — Dado un CSV donde una fila provoca un error de BD irrecuperable (p. ej. violación de constraint no capturada), `importar` hace rollback: **ninguna** de las filas del archivo queda persistida y responde HTTP 500.
- **CA-02-5** — Una cédula ya existente actualiza el expediente y sobrescribe con `null` los campos que vienen vacíos en el CSV (no hace merge).
- **CA-02-6** — Una cédula nueva `987654321` se crea con `identificacion = '0987654321'`, `id_emp` correlativo de 5 dígitos, `password = bcrypt('987654321')` y una fila en `d2_cabecera_vacacion`.
- **CA-02-7** — `tipo_contrato = "codigo de trabajo"` se normaliza a `CODIGO DEL TRABAJO`; `tipo_contrato = "indefinido"` produce un error de fila y esa fila no se guarda.
- **CA-02-8** — `grupo_vulnerable = "Mujer embarazada"` (con distinta capitalización que el catálogo) resuelve al `id` correcto; `grupo_vulnerable = "inexistente"` deja el campo `null` y agrega un aviso a `errores[]` **sin** omitir la fila.
- **CA-02-9** — `acumula_decimos = 1` deja `acumula_decimo_tercero = true` y `acumula_decimo_cuarto = true`; `acumula_decimos = 0` deja ambos en `false`.
- **CA-02-10** — `fecha_ingreso = "15/01/2020"` se persiste como `2020-01-15`.

---

## 8. Casos borde

| Caso | Comportamiento |
|---|---|
| CSV con delimitador `;` (Excel español) | **No soportado** — `fgetcsv(..., ',')` fijo. Las filas quedarán con "número de columnas incorrecto". *(Contrasta con el importador de Tecnología, que autodetecta `;`.)* |
| Fila totalmente vacía | Nº de columnas ≠ cabeceras → error de fila, se salta. |
| `id_depto` de un departamento inexistente o el 999 | Fila rechazada (2026-09-01) — se valida contra `ad_departamento` y se descarta el 999. |
| Cédula duplicada **dentro del mismo CSV** | La segunda aparición hace `update` sobre lo que dejó la primera (se procesan en orden). |
| `sueldo = "1.500,00"` (formato es-EC) | `(float)"1.500,00"` = `1.5` → dato incorrecto silencioso. Debe venir con punto decimal. |
| `email` inválido | **No se valida** (a diferencia de spec 01 que exige `email`). Se inserta tal cual. |
| Muchas filas (cientos) | Todo en una transacción; `id_emp` de cada alta vía `Empleado::generarSiguienteId()` (`pg_advisory_xact_lock`, 2026-09-01) — sin colisión con altas concurrentes. |
| Empleado `es_externo = true` cuya cédula está en el CSV | **Se omite** con error legible (2026-09-01). Antes se actualizaba igual. |

---

## 9. Deuda técnica / hallazgos

1. ✅ **RESUELTO (2026-09-01)** — Sin control de acceso: `plantilla`, `preview`, `importar` ahora
   llaman `requireRole(ROLES_ADMIN)`.
2. ✅ **RESUELTO (2026-09-01)** — Sin auditoría: cada importación registra `IMPORTACION_MASIVA` en
   `nom_auditoria_log` (conteo + nombre de archivo).
3. ✅ **RESUELTO (2026-09-01)** — No respetaba `es_externo`: las filas con cédula de funcionario
   externo se **omiten** con error legible.
4. ✅ **RESUELTO (2026-09-01)** — `id_depto` se valida contra `ad_departamento` y se rechaza el 999,
   tanto en `preview()` como en `importar()`.
5. **Delimitador fijo `,`** — no autodetecta `;`; Excel en español falla. *(abierto — P3)*
6. ✅ **RESUELTO (2026-09-10)** — `puede_solicitar_vehiculo` y `acumula_decimos` usaban `(bool)` del
   string (`"NO"` → `true`). Ahora usan `$boolSi()` (`true` solo con `SI`/`1`/`TRUE`/`X`). Se
   corrigió justo antes del import masivo del paso a producción, donde la plantilla traía
   `puede_solicitar_vehiculo = "NO"` en las 94 filas.
7. **`sueldo` sin normalización de separador decimal** — `"1.500,00"` se corrompe. *(abierto — P3)*
8. ✅ **RESUELTO (2026-09-01)** — `id_emp` de cada alta vía `Empleado::generarSiguienteId()`
   (`pg_advisory_xact_lock`), no `max(id_emp)+1` sin bloqueo.
9. El `update` de una cédula existente **sobrescribe con `null`** los campos ausentes — diseño
   declarado ("no hace merge"). *(abierto — riesgo aceptado)*

---

## 10. Dependencias

- **Spec 01** — misma tabla `ad_empleado`, mismo patrón de `id_emp`, `password`, `email`, `d2_cabecera_vacacion`.
- **Spec 03** — `modalidad_marcacion` (este importador no valida el enum).
- **Spec 05** — inicializa `d2_cabecera_vacacion` con saldo 0; el saldo real es cálculo de la spec 05.
- **Spec 09** — `acumula_*`, `sueldo`, `tipo_contrato`.
- Catálogos sociales (`ad_grupo_vulnerable`, `ad_grupo_prioritario`, `ad_tipo_discapacidad`, `ad_enfermedad_catastrofica`).

---

## 11. Preguntas abiertas

- ~~¿Cerrar el acceso a rol Admin/TH y añadir auditoría?~~ ✅ hecho 2026-09-01.
- ¿Autodetectar `;` como delimitador (como en Tecnología)?
- ¿Fusionar / deprecar `importarDistributivo` (spec 01 §5.11)?
- ¿Validar `id_depto`, `email`, enums de `estado` / `modalidad_marcacion` / `tipo_sangre`?
- ¿Debe `importar` rechazar todo el archivo si hay ≥1 error de fila (como la carga de saldos de
  vacaciones, que es "todo o nada"), en vez de omitir filas?
