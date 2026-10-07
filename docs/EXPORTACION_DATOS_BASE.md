# Datos para la carga inicial

La instalación inicial conserva 877 registros de las 30 tablas base utilizadas por el aplicativo. El export original contenía 57 tablas: sus 27 tablas sin consumidores actuales estaban vacías y fueron excluidas tras la auditoría. No se descartaron registros de los catálogos activos.

La lista vigente para `tools/Exportar-DatosBase.ps1` es [tablas-datos-base.txt](tablas-datos-base.txt). Todas sus filas se exportan. La utilidad es manual: no se ejecuta al iniciar la aplicación, migrar o compilar. La instalación usa el snapshot JSON versionado; el exportador sirve al equipo para actualizarlo desde una fuente institucional revisada.

El listado original de 57 tablas permanece en `tablas-datos-base-original.txt` para reproducir la generación desde el primer export. El generador acepta tanto ese export original como uno nuevo con las 30 tablas vigentes. El archivo `inventario-datos-base.csv` es histórico y sus referencias a migraciones/seeders retirados pueden recuperarse desde Git.

La auditoría por tabla y módulo está en [USO_TABLAS.csv](USO_TABLAS.csv) y sus criterios en [USO_TABLAS.md](USO_TABLAS.md). Las instrucciones de instalación y transición están en `backend/database/baseline/README.md`. La BD local solo representa producción si sus datos están sincronizados.

## Exportar desde Windows PowerShell

Desde la raíz del proyecto:

```powershell
cd C:\Users\llema\Documents\workspace\workspace_tthh\rrhh
& .\tools\Exportar-DatosBase.ps1
```

Para ejecutarlo desde CMD:

```cmd
powershell.exe -NoProfile -File tools\Exportar-DatosBase.ps1
```

El script usa `pg_dump` de PostgreSQL 17 y exporta exclusivamente datos de las tablas listadas, con nombres de columnas y sin propietarios ni privilegios. No modifica la BD. Solicita la contraseña directamente si PostgreSQL la requiere. El archivo se guarda en **Documentos**, con nombre `datos_base_BDD_RRHH_AAAAMMDD_HHMMSS.sql`; el script muestra la ruta absoluta. No sobrescribe archivos existentes.

Para ver el comando sin ejecutarlo:

```powershell
& .\tools\Exportar-DatosBase.ps1 -MostrarComando
```

Puede especificarse `-Servidor`, `-Puerto`, `-BaseDatos`, `-Usuario`, `-PgDump` y `-Destino`. En una exportación desde otro servidor, utilizar una versión de `pg_dump` compatible con el servidor fuente. El usuario ejecuta cualquier comando de producción manualmente.

Revisar valores sensibles en `d2_configuracion`, `adq.configuracion` y `sg_parametro` antes de compartir o versionar el archivo. Las semillas versionadas deben referenciar secretos desde el entorno cuando corresponda.

## Qué requiere una decisión aparte

El inventario incluye tablas que no deben confundirse con catálogos. Para incluir sus datos en la instalación inicial hay que definir el alcance y dependencias:

- **Asignaciones de personas:** `dbo.admin_usuario_rol`, `dbo.ad_permisos_area`, `dbo.supervisor_area`, `dbo.d2_supervisor`, `dbo.d2_pantalla_empleado`, `dbo.d2_correo`, `dbo.d2_direcciones_mail`, `dbo.d2_integracion`, `dbo.d2_no_aplica`. Requieren personas existentes y no se reconstruyen exportando solamente roles y departamentos.
- **Dispositivos:** `dbo.d2_zkteco_dispositivo` contiene configuración institucional de equipos; se revisa por separado.
- **Inventario institucional:** `adq.articulo`, `adq.proveedor`, `adq.proveedor_catalogo`, `dbo.trans_taller`, `dbo.trans_vehiculo`, `dbo.ti_equipo`, `dbo.ti_pieza`, `dbo.com_funcionario_externo`. Pueden requerirse como maestros de la institución, pero contienen datos operativos, contactos, identificaciones, stocks o relaciones con personas. Los saldos deben concordar con sus movimientos; no convertirlos automáticamente en una semilla general.
- **Planes:** `dbo.trans_plan_preventivo_cab`, `dbo.trans_plan_preventivo_det`, `dbo.vac_periodo_planificacion` contienen configuración de planes o periodos que se revisará según el alcance.
- **Servicios heredados:** `dbo.d2_servicios` combina descripción con estado y fecha de envío; revisar antes de clasificar sus filas como base.

El resto de tablas está incluido en el inventario como infraestructura o como operación/legado por revisar. Esta clasificación es una propuesta técnica para revisar con el equipo, no una afirmación de que todas las tablas heredadas sin referencias en el código actual estén fuera de uso.

## Preparación posterior de la carga

El archivo exportado es la **fuente de datos para preparar la carga**, no un script idempotente listo para ejecutar en producción. `pg_dump --table` no ordena una instalación parcial resolviendo automáticamente todas las dependencias externas.

La revisión de claves foráneas de las tablas seleccionadas encontró referencias externas de `dbo.d2_configuracion.created_by` y `updated_by` a `dbo.ad_empleado.id_emp`. Como ambas columnas admiten NULL, en una semilla independiente de empleados se pueden dejar nulas si esas personas no existen, conservando concepto, valor y descripción. Se debe documentar esa transformación en la futura carga, sin modificar la fuente ni la BD institucional.

Para cada tabla: conciliar sus filas vigentes con las semillas históricas, conservar identificadores, cargar padres antes que hijos, ajustar secuencias y definir claves estables para evitar duplicados. En tablas sin clave primaria o única debe definirse expresamente la estrategia de repetición. `sg_secuencial` se debe revisar como contador y no copiar ciegamente a una instalación sin datos operativos.

La carga posterior debe probarse dos veces en una base nueva: la segunda ejecución no debe duplicar datos ni sobrescribir parámetros institucionales ya configurados. No ejecutar las migraciones históricas completas como seeders: mezclan cambios estructurales, cargas y transformaciones de datos.
