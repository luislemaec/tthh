# Instalación inicial RRHH: estructura y todos los datos base exportados

Estado: consolidado y verificado localmente el 7 de octubre de 2026. Las dos únicas migraciones activas están en `database/migrations` y se ejecutan mediante el `migrate` habitual. Este directorio conserva SQL, snapshot de datos, contratos y documentación. Se retiraron las 133 migraciones históricas, recuperables desde Git, y dos seeders de catálogos con `truncate()` que quedaron sustituidos por el snapshot completo.

## Contenido

- `../migrations/0000_01_01_000000_create_initial_rrhh_structure.php`: crea la estructura en una BD nueva y bloquea una BD con tablas o vistas existentes.
- `../migrations/0000_01_01_000001_load_initial_rrhh_base_data.php`: carga el snapshot mediante `DatosBaseInicialesService`.
- `estructura-inicial.sql`: estructura PostgreSQL derivada del export original; contiene tablas, índices, claves foráneas, vistas, secuencias, función de auditoría y triggers.
- `datos-base.json`: las 877 filas de las 30 tablas base utilizadas; conserva sus identificadores.
- `manifest.json`: hashes de las fuentes y archivos generados, cantidades por tabla y transformaciones documentadas.
- `contrato-estructura.json`: relaciones, columnas, tipos, nulabilidad, claves primarias, restricciones únicas y claves foráneas requeridas para la transición.
- `historial-consolidado.json`: nombres y hashes de las 133 migraciones retiradas y commit anterior para recuperarlas. Las 000112 y 000113 no se exigen como registros históricos: sus condiciones estructurales se tratan expresamente.
- `Database\Seeders\DatosBaseInicialesSeeder`: permite repetir expresamente la carga sin volver a ejecutar la migración de estructura.

El export original contenía 57 tablas: 30 con datos y 27 vacías. La auditoría completa retiró las 27 vacías sin consumidores de módulos. El snapshot conserva íntegramente los 877 registros de las 30 tablas base activas. La lista histórica está en docs/tablas-datos-base-original.txt; docs/tablas-datos-base.txt contiene las 30 tablas que utiliza el exportador actual.

## Transformaciones

1. Se excluyeron `dbo.migrations`, `public.migrations` y sus secuencias del SQL inicial. Laravel administra su propio historial. Tras retirar las 94 tablas sin consumidores actuales, la estructura generada tiene 105 tablas del export y Laravel crea una tabla adicional de historial en `dbo`.
2. Se retiraron las órdenes de cliente `\restrict`/`\unrestrict`, porque la migración ejecuta SQL mediante PDO, y `SET transaction_timeout`, específico de PostgreSQL 17. El origen y la validación son PostgreSQL 17.9: **todavía no se confirmó compatibilidad con PostgreSQL 13**.
3. `d2_configuracion.created_by` y `updated_by` quedaron NULL en el snapshot. Sus referencias son a empleados que no forman parte de la instalación base. Se conservaron conceptos, valores y descripciones; no se cambiaron los archivos originales ni la BD fuente.
4. Se ordenaron las tablas por dependencias y los departamentos por jerarquía. La carga ajusta secuencias sin disminuir contadores ya utilizados.
5. Las **43 columnas `CHAR(n)` originales de 12 tablas conservadas** ahora se crean como `VARCHAR(n)` manteniendo su longitud. Las columnas de tablas retiradas no se incluyen en ese inventario. Las 13 vistas originales, sin consumidores en el repositorio, también fueron retiradas. El inventario individual de columnas y longitudes está en `manifest.json`, campo `char_to_varchar`.
6. En los datos base de las columnas originalmente CHAR se retiran únicamente espacios ASCII al final, correspondientes al relleno del tipo fijo. No se aplica `trim` a columnas VARCHAR ni se eliminan espacios iniciales, tabs o saltos de línea. Se conservan NULL y todos los registros.
7. `dbo.supervisor_area` se crea directamente con `uq_supervisor_area_depto_sup UNIQUE (id_depto, id_supervisor)`. No existe el UNIQUE antiguo sobre `id_depto`. Por eso se retiró también la 000112. El límite de dos supervisores y su aplicación a áreas sin padre sigue siendo una regla del controlador; esta restricción evita repetir una pareja, no limita por sí sola la cantidad de supervisores.
8. Se eliminaron `s1` a `s30` únicamente de `dbo.ad_empleado`. No aparecen en sus altas, actualizaciones, importación ni campos asignables; no tienen dependencias de BD y estaban NULL en los 101 empleados locales revisados. Las lecturas diarias del cuadre, permisos, vacaciones y horas extras corresponden a `dbo.d2_programacion`, que conserva sus 30 columnas. La validación prueba su conservación y un alta mediante el modelo `Empleado` sin esos campos. No se eliminaron columnas ni datos de la BD existente.

La prueba específica comprueba que no queden columnas CHAR en tablas ni vistas y que cada columna convertida tenga exactamente la longitud original. Los índices, claves, defaults, relaciones y demás objetos se crean a partir del mismo esquema y sus restricciones se validan durante la carga. Este cambio está aplicado a la instalación inicial; no se ejecutó una conversión sobre la BD institucional existente. Esa conversión requiere una migración incremental revisada, con tratamiento de vistas dependientes y ventana de mantenimiento para posibles bloqueos y reescrituras de tablas.

Las filas con una clave primaria existente se conservan, aunque se hayan configurado valores diferentes. En tablas sin clave primaria se compara la fila completa para evitar duplicar las filas del snapshot; esto no implica identificar cambios de negocio futuros en esas tablas. Una carga repetida no borra registros ni actualiza los existentes.

## Auditoría completa de tablas

Se revisaron las 201 tablas y 13 vistas originales. Se conservaron 104 tablas utilizadas por módulos o por Laravel y una por dependencia (`dbo.d2_cab_prog`, referenciada por empleados). Se excluyeron 94 tablas sin consumidores actuales y las 13 vistas sin uso. Laravel crea su propio historial, separado de las dos tablas de historial originales.

El detalle de cada tabla, módulos, lectura/escritura, archivo, línea y dependencias está en [USO_TABLAS.csv](../../../docs/USO_TABLAS.csv) y [criterios de auditoría](../../../docs/USO_TABLAS.md). `uso-tablas.json` conserva la evidencia y `manifest.json` registra las relaciones retiradas. El generador elimina primero las vistas y después las tablas, con `RESTRICT`, exclusivamente en su BD temporal: una dependencia no prevista impide la generación. Las secuencias propias, índices y restricciones desaparecen con sus tablas.

Se conservan las tablas de infraestructura utilizadas indirectamente por Laravel. Sanctum usa `public.personal_access_tokens`; el duplicado en `dbo` se retiró. Las 27 tablas excluidas del snapshot estaban vacías; las otras 30 conservan los mismos 877 registros.

Esta exclusión afecta únicamente a instalaciones nuevas. La adopción conserva todas las tablas, columnas y datos de la BD existente. Es evidencia estática del repositorio, no un registro de consultas ejecutadas ni una auditoría de otras aplicaciones externas.

## Verificación reproducible

Desde la raíz del proyecto, con PHP disponible y `.env` del backend configurado para PostgreSQL local:

```powershell
php tools/verificar-baseline.php
```

Este verificador exige `APP_ENV=local`, host `127.0.0.1` y ausencia de `DB_URL`. Crea una BD temporal con nombre aleatorio, ejecuta las dos migraciones y elimina únicamente esa BD al terminar. Requiere permisos para crear/eliminar esa base. No modifica `BDD_RRHH`.

Verifica las cantidades de las 30 tablas y el contenido de las 877 filas, repetición sin duplicados, conservación de un parámetro modificado, secuencia para crear un rol, bloqueo de una BD existente, constraint compuesto de supervisores y que solo se registren dos migraciones.

Para verificar una transición de una BD existente en una base temporal, sin modificar `BDD_RRHH`:

```powershell
php tools/verificar-transicion-baseline.php
```

También comprueba bloqueo de historial incompleto y columnas incompatibles, modo de verificación sin escrituras, adopción repetible, conservación de las 877 filas y de los registros históricos, ajuste explícito de supervisores y `migrate` habitual posterior sin recrear tablas.

Verificación actual de la suite general (2026-10-08): 20 pruebas aprobadas, 91 aserciones en Docker. Las antiguas fallas GPS se resolvieron aislando los parámetros del escenario de pruebas y verificando los límites configurables, sin cambiar la configuración operativa. La verificación específica de la base inicial también terminó correctamente.

Para regenerar las fuentes desde una exportación revisada:

```powershell
php tools/preparar-baseline.php "C:\ruta\datos_base_BDD_RRHH.sql" "C:\ruta\estructura_BDD_RRHH.sql"
```

La copia local del SQL original se conservó en `backend/storage/app/baseline-source/estructura_BDD_RRHH.sql`, excluida de Git. Las herramientas la usan por defecto si no se indica otra ruta; un clon nuevo debe proporcionar el export original para regenerar o probar la transición. La lista `docs/tablas-datos-base.txt` sí se versiona. Se exige la misma conexión local y se utilizan `psql`/`pg_dump` instalados en `C:\Program Files\PostgreSQL\17\bin`. La generación necesita permisos para `session_replication_role` exclusivamente en su base temporal de extracción. La instalación posterior **mantiene activas las claves foráneas**. La revisión automática de posibles secretos no sustituye la revisión humana de los valores.

## Instalación nueva, separada de la BD actual

Los siguientes comandos son únicamente para una **BD nueva vacía**. Crear previamente la BD y el esquema `dbo` con un usuario autorizado; `CREATE DATABASE` no puede ejecutarse dentro de la transacción de una migración. Laravel usa `search_path=dbo` y debe poder crear ahí su historial antes de ejecutar la migración inicial.

Ejemplo SQL ejecutado desde una conexión administrativa:

```sql
CREATE DATABASE rrhh_nueva TEMPLATE template0;
```

Conectarse a `rrhh_nueva` y ejecutar:

```sql
CREATE SCHEMA dbo;
```

Configurar la conexión del backend apuntando a esa BD nueva. Desde `backend`:

```powershell
php artisan config:clear
php artisan migrate --database=pgsql
```

Repetir `migrate` sobre esta instalación no ejecuta las antiguas: esos archivos se retiraron y el historial nuevo contiene las dos migraciones iniciales.

Las correcciones 000112 y 000113 están incorporadas en la estructura inicial y ya no existen como migraciones independientes.

Para repetir solamente la carga de los datos base en la conexión expresamente revisada:

```powershell
php artisan db:seed --class=DatosBaseInicialesSeeder
```

Esta carga no incluye empleados, asignaciones personales de roles, marcaciones, nómina histórica, saldos de inventario ni sesiones. Una instalación base no permite probar el login institucional hasta disponer del empleado autorizado y de sus asignaciones. Los maestros adicionales pendientes de clasificación se describen en `docs/EXPORTACION_DATOS_BASE.md`.

## Transición de los ambientes existentes

No aplicar la estructura inicial sobre una BD existente. Antes del primer `migrate` con el código consolidado, el responsable ejecuta manualmente, con respaldo y conexión del ambiente revisada:

```powershell
php artisan rrhh:adoptar-baseline --actualizar-supervisores
```

Este primer comando **solo verifica**: no escribe en la BD. Compara el contrato estructural y las 131 migraciones históricas requeridas contra el historial que realmente utiliza la conexión. No mezcla automáticamente `dbo.migrations` y `public.migrations`. Si falla, revisar las diferencias y completar los cambios mediante la versión anterior; no inventar registros para ocultar pendientes.

Si la verificación pasa, para adoptar y ajustar explícitamente la restricción antigua de supervisores cuando corresponda:

```powershell
php artisan rrhh:adoptar-baseline --actualizar-supervisores --registrar
php artisan migrate --database=pgsql
```

El segundo comando registra los dos nombres iniciales, preserva los registros antiguos y sustituye únicamente la restricción de supervisores solicitada. No recrea tablas, no carga el snapshot y no cambia valores institucionales. El ajuste de supervisores es transaccional, tiene timeout de bloqueo y debe hacerse en la ventana operativa revisada. Sin `--actualizar-supervisores`, `--registrar` solo registra si la restricción compuesta ya existe y la antigua ya no está presente.

La adopción **no convierte** las columnas CHAR de la BD existente a VARCHAR ni limpia sus valores. Esa conversión requiere una entrega incremental independiente que trate las vistas y los bloqueos. La instalación nueva sí se crea íntegramente con VARCHAR para las 43 columnas antiguas conservadas.

No se modificó el `.env` ni se ejecutó la adopción con escritura en `BDD_RRHH`. La producción no se consultó. El comando inicial bloquea el despliegue si faltan cambios históricos o condiciones estructurales; no ejecutar un `migrate` general sobre la BD existente antes de adoptar.

Estas migraciones rechazan `down()` deliberadamente: no eliminan el esquema ni datos potencialmente referenciados mediante rollback. La recuperación de una instalación fallida sigue su respaldo y el plan revisado. Los comandos de producción los ejecuta el equipo manualmente.

## Cuenta inicial de desarrollo/pruebas

Después de las dos migraciones, el seeder principal ahora ejecuta los catálogos y `SuperAdminInicialSeeder`, en ese orden. Configurar `BOOTSTRAP_ADMIN_PASSWORD` en el archivo privado del ambiente y ejecutar `php artisan db:seed`. Para cargar únicamente catálogos, continuar usando `php artisan db:seed --class=DatosBaseInicialesSeeder`.

La cuenta inicial es Admin Admin, cédula string `0123467890`, departamento existente 62 y rol existente 1 ADMINISTRADOR. El seeder utiliza bcrypt y no cambia contraseñas existentes. No ejecutarlo automáticamente sobre producción. Los comandos Docker y las verificaciones están en [Entorno Docker](../../../docs/DOCKER_DESARROLLO.md).
