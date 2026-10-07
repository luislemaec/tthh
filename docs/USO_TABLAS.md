# Uso de tablas por el aplicativo

Auditoría del 7 de octubre de 2026: 201 tablas y 13 vistas del esquema original. El detalle por relación, módulos, tipo de acceso, archivo y línea está en [USO_TABLAS.csv](USO_TABLAS.csv); la evidencia completa se conserva en `backend/database/baseline/uso-tablas.json`.

| Decisión | Tablas |
| --- | ---: |
| Uso directo o indirecto de módulos/framework | 104 |
| Necesaria por dependencia de otra tabla activa | 1 |
| Sin consumidores actuales: excluidas de instalación inicial | 94 |
| Historial original excluido: Laravel crea su historial | 2 |

La instalación inicial contiene 105 tablas del esquema y una tabla adicional de historial creada por Laravel. Se excluyeron también las 13 vistas originales: los reportes actuales consultan las tablas directamente. La estructura conserva los índices, secuencias, restricciones y triggers de las tablas activas.

## Criterios y alcance

- Se revisaron controladores de todos los módulos, servicios, comandos, modelos y relaciones Eloquent, rutas, configuración, providers, seeders operativos y plantillas PHP de reportes. El frontend accede por estas rutas API.
- Se excluyen comentarios del análisis. Se resuelven namespaces y alias de modelos para distinguir, por ejemplo, los mantenimientos de Tecnología y Transportes. Se revisaron los mapas de tablas dinámicos de importación: sus catálogos se nombran explícitamente en el código.
- El reporte identifica lecturas/referencias, escrituras Query Builder, creación/actualización/borrado por modelo y escrituras sobre instancias. `relacion_modelo` representa acceso indirecto; una mera declaración de modelo no basta para conservar una tabla.
- Las tablas de Laravel se conservan aunque no aparezcan en consultas de controladores: caché, bloqueos, sesiones, colas, lotes, trabajos fallidos, usuarios y recuperación de contraseña. La configuración PostgreSQL utiliza `search_path=dbo`. Sanctum usa expresamente `public.personal_access_tokens` mediante el modelo registrado en `AppServiceProvider`; su duplicado en `dbo` se excluyó.
- Se siguieron claves foráneas, dependencias de vistas y cuerpos de funciones de triggers. `dbo.d2_cab_prog` se conserva por la clave foránea de `dbo.ad_empleado`, aunque no tenga un consumidor directo de módulo.
- Una tabla en el export de datos base no constituye por sí sola uso del aplicativo. Se excluyeron 27 tablas base sin consumidores y vacías en el export original. Las otras 30 conservan íntegramente los 877 registros exportados.
- El comando manual y destructivo `bootstrap:limpiar-produccion` no se considera consumidor de negocio. Se adaptó para omitir tablas inexistentes y mantener compatibilidad con los esquemas anteriores. No se ejecutó este comando.

Es un análisis estático del repositorio y de las dependencias PostgreSQL, no un historial de consultas ejecutadas. No acredita el uso por aplicaciones externas que no están en este repositorio. La exclusión afecta únicamente a instalaciones nuevas; no se borraron tablas ni datos de la BD existente.

## Reproducir

`php tools/auditar-tablas.php` consulta únicamente metadatos en una transacción de solo lectura de PostgreSQL local y escribe `docs/uso-tablas-actual.json` y `.csv`, sin cambiar el baseline. El generador `tools/preparar-baseline.php` ejecuta el mismo análisis sobre una copia temporal del export original, elimina tablas/vistas sin uso con `RESTRICT` y genera el inventario versionado. Una dependencia no prevista impide la generación; no se usa `CASCADE` para eliminar objetos.

Las pruebas `php tools/verificar-baseline.php` y `php tools/verificar-transicion-baseline.php` usan bases temporales y verifican instalación, integridad, ausencia de objetos retirados, los 877 registros, creación de empleado/token Sanctum, caché Laravel, carga repetible y adopción del historial sin pérdida de datos. No recrean `BDD_RRHH`.
