# SIT / GSIT — Sistema de Gestión del Consejo de Comunicación

Aplicación institucional para Talento Humano, asistencia, permisos, vacaciones, nómina, adquisiciones/bienes, transportes y comisiones de servicios. Este repositorio contiene la API Laravel y el frontend Vue. La app Flutter `app_consejo` se mantiene en un repositorio separado y consume la API de SIT para marcación.

Esta guía está dirigida al equipo de desarrollo y operación. Los comandos locales usan PowerShell en Windows; los de despliegue usan Bash. Ejecutar cada bloque desde el directorio indicado.

## Índice

- [Arquitectura y requisitos](#arquitectura-y-requisitos)
- [Preparar el entorno local](#preparar-el-entorno-local)
- [Ejecutar el aplicativo](#ejecutar-el-aplicativo)
- [Autenticación y marcación](#autenticación-y-marcación)
- [Flujo de trabajo del equipo](#flujo-de-trabajo-del-equipo)
- [Validación antes de entregar](#validación-antes-de-entregar)
- [Base de datos y migraciones](#base-de-datos-y-migraciones)
- [Despliegue en producción](#despliegue-en-producción)
- [Tareas programadas y diagnóstico](#tareas-programadas-y-diagnóstico)
- [Solución de problemas](#solución-de-problemas)
- [Documentación relacionada](#documentación-relacionada)

## Arquitectura y requisitos

| Componente | Tecnología / función |
|---|---|
| API | Laravel 12, PHP, Sanctum, Composer |
| Frontend | Vue 3, Pinia, Vue Router 5, Axios |
| Estilos y compilación | Tailwind CSS 4, Vite 7 |
| Base de datos | PostgreSQL; esquemas `dbo`, `adq` y `public` |
| Autenticación | Cédula; AD/LDAP y alternativa con hash local |
| Archivos institucionales | Alfresco, según el módulo |
| Exportaciones | DomPDF y PhpSpreadsheet |
| Integraciones | Reloj ZKTeco y app móvil de marcación |
| Zona del aplicativo | `America/Guayaquil` (UTC−05:00) |

```text
rrhh/
├── backend/
│   ├── app/Http/Controllers/    Endpoints y autorización
│   ├── app/Services/            Lógica compartida e integraciones
│   ├── app/Models/              Modelos y esquemas PostgreSQL
│   ├── app/Console/Commands/    Cuadre y tareas de mantenimiento
│   ├── config/                 Configuración del backend
│   ├── database/migrations/    Cambios versionados de esquema/datos
│   ├── routes/                 Rutas API y programación de tareas
│   └── tests/                  Pruebas PHPUnit
├── frontend/
│   ├── src/views/              Pantallas por módulo
│   ├── src/stores/             Estado y autenticación
│   └── src/services/           Cliente HTTP
├── docs/                       Guías operativas
└── specs/                      Especificaciones funcionales
```

Instalar antes de comenzar:

- **PHP:** Composer declara PHP `^8.2`, pero el lockfile instalado requiere **PHP 8.4**. El entorno nativo fue validado con 8.4.25 y Docker utiliza PHP-FPM 8.4.
- **Composer 2** y las extensiones PHP necesarias. Revisar especialmente `pdo_pgsql`, `pgsql`, `mbstring`, `curl`, `fileinfo`, `gd`, `zip`, XML/DOM y `ldap` cuando se use AD. Para las pruebas actuales se requiere `pdo_sqlite`.
- **Node.js y npm:** `frontend/package.json` declara `^20.19.0 || >=22.12.0`. Acordar una versión compatible entre el equipo y el servidor de compilación.
- **PostgreSQL**, acceso a una copia autorizada de la BD y Git.
- Acceso de red al AD y Alfresco cuando se prueben esas integraciones.

El desarrollo local y la producción actual utilizan instalación nativa. No se requiere Docker para este flujo.

## Docker para desarrollo y pruebas

El [entorno Docker](docs/DOCKER_DESARROLLO.md) utiliza Nginx, PHP-FPM 8.4 con OPcache y Vite/HMR; PostgreSQL permanece externo. Sus puertos predeterminados son `8001` y `5174`, conservando el arranque nativo en `8000` y `5173`. Descargar los archivos Docker mediante `git pull` no cambia el despliegue nativo.

`DatabaseSeeder` carga primero los catálogos y después la cuenta inicial Admin Admin, cédula `0123467890`, departamento 62 y rol 1 ADMINISTRADOR. Configurar `BOOTSTRAP_ADMIN_PASSWORD` en el archivo privado del ambiente antes de crearla. Repetir el seeder conserva la contraseña existente. Para cargar únicamente catálogos, usar `db:seed --class=DatosBaseInicialesSeeder`.

## Preparar el entorno local

### 1. Comprobar herramientas

Desde la raíz del repositorio:

```powershell
php --version
php --ini
php -m
composer --version
node --version
npm.cmd --version
git --version
```

Si Windows no reconoce `php`, agregar al `PATH` la carpeta que contiene `php.exe` y abrir una terminal nueva. `where.exe php` permite comprobar qué ejecutable se está usando. En Windows se puede utilizar `npm.cmd` para evitar bloqueos de ejecución de `npm.ps1`; en Linux utilizar `npm`.

### 2. Instalar el backend y configurar su entorno

```powershell
cd backend
composer install
composer check-platform-reqs
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
```

Editar **`backend/.env`** para la máquina local. El ejemplo del repositorio es una base de configuración, no una configuración completa de PostgreSQL/AD. Valores orientativos, sin credenciales reales:

```dotenv
APP_NAME=SIT
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=BDD_RRHH
DB_USERNAME=postgres
DB_PASSWORD=<contraseña-local>

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
LOG_CHANNEL=stack
LOG_STACK=single
```

Si existe `DB_URL`, revisar que no sustituya estos datos por otra conexión. Usar `postgres` únicamente para la instalación local acordada; en ambientes compartidos utilizar el usuario y permisos asignados por administración.

Para un entorno **nuevo**, con `APP_KEY` vacía:

```powershell
php artisan key:generate
```

Conservar la clave de los ambientes existentes: cambiarla invalida datos cifrados, incluidos desafíos de ubicación. Después de editar `.env` o archivos de `config/`:

```powershell
php artisan config:clear
```

### 3. Preparar PostgreSQL

Solicitar una copia autorizada, preferentemente anonimizada, de `BDD_RRHH`. El sistema utiliza tablas y datos institucionales preexistentes; una BD vacía y `migrate` por sí solos no equivalen a una instalación funcional.

Restaurar exclusivamente en una BD local/de pruebas. Comprobar los esquemas `dbo`, `adq`, `public`, la tabla `public.personal_access_tokens` y las tablas de roles/configuración. Revisar el historial de migraciones del respaldo antes de aplicar cambios.

```powershell
# Desde backend; inspección de migraciones, no ejecución
php artisan migrate:status
```

La conexión `pgsql` tiene `search_path=dbo` y varios modelos declaran el esquema explícitamente. No cambiar esquemas o conexiones para resolver un error sin analizar sus dependencias.

Si el módulo utiliza archivos del disco público, crear el enlace local una vez:

```powershell
php artisan storage:link
```

### 4. Instalar el frontend

Desde la raíz del repositorio:

```powershell
cd frontend
npm.cmd ci
```

Crear **`frontend/.env`** si no existe y configurar:

```dotenv
VITE_API_URL=http://127.0.0.1:8000/api
```

Las variables `VITE_*` son públicas y se incorporan al frontend. Nunca colocar contraseñas, claves AD, tokens privados o credenciales de BD en ellas.

## Ejecutar el aplicativo

Mantener dos terminales abiertas, ambas iniciadas desde la raíz del repositorio.

**Terminal 1 — backend:**

```powershell
cd backend
php artisan config:clear
php artisan serve --host=127.0.0.1 --port=8000
```

**Terminal 2 — frontend:**

```powershell
cd frontend
npm.cmd run dev -- --host 127.0.0.1 --port 5173 --strictPort
```

| Dirección | Uso |
|---|---|
| `http://127.0.0.1:5173` | Interfaz web Vue |
| `http://127.0.0.1:8000/api` | API Laravel |
| `http://127.0.0.1:8000/up` | Comprobación básica de respuesta de Laravel; no verifica todas las integraciones |

Vite no tiene configurado un proxy hacia Laravel. **El puerto 5173 no es la API**. Detener cada proceso con `Ctrl+C`. Reiniciar Vite después de cambiar su `.env`.

Los scripts `composer setup` y `composer dev` conservan el flujo del esqueleto Laravel: incluyen migraciones automáticas o npm dentro del backend. Para este repositorio con frontend separado utilizar los pasos explícitos anteriores.

### Pruebas de la app desde un celular

El celular debe poder alcanzar la PC por la red Wi-Fi/LAN. En `backend`, reemplazar el servidor local por:

```powershell
php artisan serve --host=0.0.0.0 --port=8000
```

Obtener la IPv4 de la PC con `ipconfig`. En `assets/.env` del proyecto Flutter usar:

```dotenv
SIT_API_BASE_URL=http://<IPv4-de-la-PC>:8000/api
```

Permitir TCP 8000 en el firewall para la red de pruebas autorizada y comprobar `http://<IPv4-de-la-PC>:8000/up` desde el celular. `127.0.0.1` en el teléfono apunta al teléfono, no a la PC; `0.0.0.0` es una dirección de escucha, no una URL para el cliente. El Wi-Fi institucional puede impedir la comunicación entre dispositivos.

Recompilar/reinstalar la app en **debug** para pruebas HTTP. Su `.env` es un asset empaquetado; cambiarlo en la PC no modifica una APK ya instalada. En producción usar `https://sit.consejodecomunicacion.gob.ec/api`; la app release exige HTTPS.

## Autenticación y marcación

El login busca por cédula un empleado `ACTIVO`. La autenticación compartida prueba AD y, si no autentica, verifica el hash local existente. La contraseña de PostgreSQL no es la contraseña de ingreso al sistema.

Para AD, configurar en `backend/.env`:

| Variable | Uso |
|---|---|
| `AD_HOST`, `AD_PORT` | Servidor y puerto LDAP institucionales |
| `AD_USE_TLS` | Uso de StartTLS; acordar con administración de AD y validar certificados |
| `AD_BASE_DN` | Base de búsqueda del directorio |
| `AD_BIND_DN`, `AD_BIND_PASSWORD` | Cuenta de servicio para buscar usuarios |
| `AD_EMPLOYEE_ATTR` | Atributo que contiene la cédula; en la configuración institucional verificada: `postOfficeBox` |

`postOfficeBox` debe contener la cédula exacta y devolver un único usuario. No debe confundirse con `postalCode`. La cuenta de servicio busca el DN y el bind como usuario valida la contraseña. Con `AD_HOST` vacío, el flujo utiliza el hash local. Después de cambiar la configuración ejecutar `php artisan config:clear`.

Reglas actuales de sesión y marcación:

- Sesiones web y móvil de **15 minutos absolutos desde el login**. La actividad no renueva el vencimiento; `SESSION_LIFETIME` no configura estos tokens Sanctum.
- Una sesión por canal y empleado. Otro login web reemplaza la sesión web; otro login móvil reemplaza la móvil.
- Token móvil limitado a sesión propia, estado de asistencia, marcación y logout. Los permisos se verifican en el backend.
- Cuatro conceptos, en orden: ENTRADA → SALIDA AL LUNCH → ENTRADA DEL LUNCH → SALIDA. Se comparte el historial entre web y app.
- BIOMETRICO queda bloqueado para web/app; TELETRABAJO necesita un período vigente. TEMPORAL y TELETRABAJO están exentos de ubicación móvil.
- Web PRESENCIAL requiere los prefijos institucionales configurados en `vlans_permitidas`. Los equipos/IP compartidos están permitidos.
- App PRESENCIAL usa geolocalización; valores iniciales: radio 50 m, precisión máxima 25 m y antigüedad máxima 30 s. Se exige `distancia + precisión ≤ radio` y se rechazan lecturas más de 3 s por delante de SIT.
- La hora almacenada proviene del servidor. Mantener sincronizados los relojes de servidor/PC y dispositivos; revisar el AD/NTP si existe un desfase persistente.

En `dbo.sg_control_persona`, `origen` identifica APP/WEB; `lugar` también conserva el canal. `tipo_marcacion` mantiene la clasificación WEB/TELETRABAJO/BIOMETRICO. La estructura inicial crea `origen` como `VARCHAR(120)` y todas las antiguas columnas CHAR como VARCHAR; la 000113 se retiró por estar incorporada. Adoptar el historial en una BD existente no convierte sus tipos automáticamente.

Consultar [la guía de marcación móvil](docs/MARCACION_MOVIL.md) para parámetros, contrato API, despliegue y QA en dispositivo.

## Flujo de trabajo del equipo

1. Definir el problema, el comportamiento esperado y los criterios de aceptación. Consultar la spec del módulo antes de modificar reglas institucionales.
2. Revisar el estado del repositorio y crear una rama de trabajo. No descartar cambios ajenos ni publicar `.env`, respaldos, credenciales, datos personales o archivos locales generados.
3. Implementar cambios acotados. Mantener lógica compartida en servicios; validar entradas, autorización y restricciones en el backend. El menú visible no sustituye la autorización.
4. Verificar la conexión y el esquema de cada modelo/consulta, las transacciones, fechas y auditoría. No confiar en la hora del navegador para guardar una marcación.
5. Añadir pruebas de regresión para reglas, permisos, concurrencia o errores relevantes. Documentar los cambios de configuración y las migraciones.
6. Entregar un PR con problema resuelto, comportamiento resultante, validaciones y pasos de despliegue/recuperación. Someterlo a revisión antes de integrar.

Comandos habituales desde la raíz:

```powershell
git status --short
git switch -c codex/descripcion-cambio
git diff --check
git diff
# Añadir únicamente los archivos revisados; no usar git add . sin inspección
git add <archivo-revisado>
git diff --cached
```

Utilizar `composer install` y `npm ci` con los lockfiles versionados. Cambiar dependencias mediante `composer update`/`npm install` solo cuando el trabajo incluya su actualización; revisar y entregar el lockfile correspondiente. No versionar `vendor/`, `node_modules/` o `dist/`.

No registrar contraseñas, tokens o coordenadas personales en logs. Revisar también las copias y respaldos de `.env` antes de preparar un commit. Distribuir secretos por el canal autorizado del equipo.

## Validación antes de entregar

**Backend**, desde `backend`:

```powershell
composer check-platform-reqs
php artisan test
php artisan route:list --path=mobile
```

Si `pdo_sqlite` no está habilitado en `php.ini`, pero su extensión está instalada, ejecutar PHPUnit directamente habilitándola solo para ese proceso:

```powershell
php -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit
# Solo las pruebas de sesión/marcación:
php -d extension=pdo_sqlite vendor/phpunit/phpunit/phpunit --filter=MarcacionSitTest
```

La suite actual configura SQLite en memoria. `MarcacionSitTest` sustituye además la conexión llamada `pgsql` por fixtures SQLite con esquemas adjuntos. No restaurar ni migrar la BD institucional para ejecutar estas pruebas. Las pruebas nuevas que utilicen modelos con conexión explícita deben aislar también esa conexión.

Para revisar formato PHP sin modificar archivos ajenos:

```powershell
php vendor/laravel/pint/builds/pint --test <archivo.php>
```

**Frontend**, desde `frontend`:

```powershell
npm.cmd run build
```

Actualmente no hay scripts de pruebas unitarias o lint del frontend en `package.json`. Completar la validación con navegación, permisos, formularios, errores de API y revisión responsive del módulo cambiado. `npm run preview` sirve el build para revisión local y no reemplaza al servidor de producción.

Para Tailwind CSS 4, si se utiliza `@apply` en un `<style scoped>`, añadir al inicio `@reference "tailwindcss";`.

En la app Flutter, ejecutar las validaciones de su propio repositorio. La guía móvil registra las limitaciones conocidas y las verificaciones pendientes; no asumir que una compilación sustituye las pruebas reales de GPS, AD o conectividad.

## Base de datos y migraciones

La BD tiene historial institucional. Los cambios de esquema deben estar versionados, ser revisables y preservar datos. Antes de ejecutar una migración:

- Confirmar ambiente, host, nombre de BD, respaldo y permisos.
- Consultar `php artisan migrate:status` y revisar el contenido de cada archivo pendiente.
- Verificar el esquema del historial que utiliza la conexión: en la PC revisada es `dbo.migrations` (`search_path=dbo`), aunque también existe `public.migrations`. No asumir que ambos historiales coinciden ni insertar registros sin comprobar que el cambio ya fue aplicado.
- Probar el cambio en una copia local. Evaluar índices, bloqueos de tabla, restricciones y compatibilidad con la versión de PostgreSQL del destino.

Antes del primer `migrate` en una BD existente con el historial consolidado, verificar desde `backend`:

```powershell
php artisan rrhh:adoptar-baseline --actualizar-supervisores
```

No ejecutar `migrate` general si hay pendientes que no forman parte de la entrega. **No ejecutar `migrate:fresh`, `migrate:reset`, `migrate:rollback` ni comandos de limpieza/bootstrap sobre datos institucionales de producción.** La recuperación debe seguir el plan revisado, no un borrado o rollback general improvisado.

El SQL de estructura por sí solo no instala catálogos, permisos ni configuración inicial; la segunda migración contiene la carga completa de los datos base exportados.

Quedan únicamente dos migraciones activas: estructura inicial y carga de datos base. Se retiraron 133 históricas y dos seeders con `truncate()` sustituidos por la carga revisada. Las instrucciones para una BD nueva y la adopción del historial existente están en [Instalación inicial revisada](backend/database/baseline/README.md). `DatabaseSeeder` carga los datos base de manera repetible; `ArticulosSeeder` se conserva como importador manual de CSV. La estructura inicial conserva 105 tablas del esquema utilizado, más el historial Laravel, y 877 registros de 30 tablas base. La [auditoría por tabla y módulo](docs/USO_TABLAS.md) documenta las 94 tablas y 13 vistas excluidas sin modificar la BD existente.

Si la verificación de adopción pasa, el responsable puede ejecutar `php artisan rrhh:adoptar-baseline --actualizar-supervisores --registrar` antes del `migrate` habitual. Registra los dos nombres iniciales y ajusta expresamente la restricción de supervisores; preserva filas e historial y no carga el snapshot sobre producción. Si falla, completar los cambios verificados mediante la versión anterior, sin inventar registros en `migrations`.

## Despliegue en producción

El despliegue lo ejecuta **manualmente el responsable autorizado** en el servidor. El entorno local nativo y el servidor pueden recibir el mismo código; cada ambiente conserva su configuración `.env`.

Antes de desplegar, revisar el PR, la revisión/commit a publicar, estado limpio del checkout del servidor, respaldo, migraciones y ventana operativa necesaria. Confirmar `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, permisos de `storage/` y `bootstrap/cache/`, integraciones y sincronización horaria. Conservar `APP_KEY` y las credenciales del ambiente.

Secuencia base desde la raíz del checkout del servidor:

```bash
git status --short
git pull --ff-only
cd backend
composer install --no-dev --optimize-autoloader
php artisan migrate:status
# Primera transición al historial consolidado, sobre una BD existente:
php artisan rrhh:adoptar-baseline --actualizar-supervisores
# Solo si la verificación pasa, con respaldo y ventana operativa revisados:
php artisan rrhh:adoptar-baseline --actualizar-supervisores --registrar
# Ejecutar aquí únicamente las migraciones revisadas para esta entrega.
# Si cambiaron .env o config/*.php:
php artisan config:clear
# Si cambiaron rutas y se utilizan rutas cacheadas:
php artisan route:clear
cd ../frontend
npm ci
npm run build
```

Compilar el frontend cuando cambien sus fuentes, dependencias o variables `VITE_*`. Un `git pull` no recompila los assets. Si la operación del servidor utiliza `config:cache`/`route:cache`, regenerar esos cachés después de limpiar y validar la configuración; documentar la rutina utilizada. Reiniciar workers solamente si existen y el cambio lo requiere.

Comprobar después del despliegue: login, roles, carga de datos, módulo afectado, asistencia web/móvil cuando corresponda, archivos, reportes y logs. La política de 15 minutos puede exigir un nuevo login a sesiones antiguas. Publicar primero los endpoints de SIT y después la versión de la app que los necesita.

Ante un fallo, identificar si está en código, configuración, esquema o servicio externo. Volver a la revisión anterior de código solo después de comprobar compatibilidad con las migraciones ya aplicadas; restaurar datos exclusivamente mediante el procedimiento autorizado y un respaldo verificado.

## Tareas programadas y diagnóstico

Laravel declara estas tareas en `backend/routes/console.php`, usando la zona de la aplicación:

| Tarea | Programación |
|---|---|
| `procesar:cuadre` | Todos los días a las 23:55 |
| `cerrar:acciones-vencidas` | Todos los días a las 06:00 |

El responsable de operación debe mantener el scheduler activo. Ejemplo de cron, sustituyendo las rutas por las reales del servidor:

```cron
* * * * * cd /ruta/rrhh/backend && /ruta/php artisan schedule:run >> /ruta/logs/sit-scheduler.log 2>&1
```

Desde `backend`, comandos de inspección:

```powershell
php artisan schedule:list
php artisan route:list --path=asistencia
php artisan migrate:status
```

`php artisan procesar:cuadre --fecha=YYYY-MM-DD` **modifica datos**: usarlo únicamente para reprocesos autorizados y revisados. No utilizarlo como prueba de conectividad. Si se configura una cola asíncrona, mantener su worker bajo supervisión; con `QUEUE_CONNECTION=sync`, los trabajos se ejecutan en la petición.

Para logs, revisar el canal definido por `LOG_CHANNEL`/`LOG_STACK`; cuando se usa el canal `single`, el archivo es `backend/storage/logs/laravel.log`. Reportar errores con ambiente, pantalla, fecha/hora, endpoint, estado HTTP y pasos de reproducción. Omitir contraseñas, tokens y datos personales; anonimizar capturas.

## Solución de problemas

| Síntoma | Comprobación / acción |
|---|---|
| `php` no se reconoce | Revisar `PATH`, `where.exe php` y abrir una terminal nueva. |
| Puerto 5173 ocupado | Comprobar si Vite ya está ejecutándose. Usar esa instancia o detener el proceso identificado; no cerrar procesos desconocidos. |
| Login móvil devuelve 404 | Configurar la API en `:8000/api`, no el frontend en `:5173`; verificar `/api/mobile/login`. |
| El celular no alcanza la API | Usar la IPv4 de la PC, escucha `0.0.0.0`, firewall y conectividad entre dispositivos. |
| Login devuelve 401 | Revisar empleado ACTIVO, cédula, AD/configuración y hash local; una sesión vencida necesita login nuevo. |
| Operación devuelve 403 | Revisar autorización, modalidad y, para web PRESENCIAL, `vlans_permitidas` e IP real del cliente. |
| Login devuelve 429 | Esperar el intervalo del límite: 5 solicitudes por cédula/minuto y 30 por IP/minuto, compartidos entre canales. |
| Ubicación antigua o adelantada | Leer los segundos del mensaje; obtener una lectura nueva y verificar fecha/hora automáticas y fuente NTP de PC/servidor/celular. |
| Ubicación fuera de radio o imprecisa | Revisar permiso preciso, parámetros y `distancia + precisión`; no ampliar el radio para ocultar un error de reloj. |
| Cambios de configuración no aparecen | Backend: `config:clear`. Frontend: reiniciar Vite o recompilar. Flutter: recompilar/reinstalar el asset `.env`. |
| Web autentica pero no muestra datos | Revisar solicitudes en la pestaña Red: URL API, estado 401/403/500, conexión BD, esquemas, roles y log del backend. |
| `origen` tiene espacios finales | Revisar el tipo de columna; `CHAR(120)` agrega relleno. Aplicar la migración dirigida a `VARCHAR(120)`. |
| `could not find driver` | Revisar extensiones del PHP activo: `pdo_pgsql` para la app, `pdo_sqlite` para las pruebas actuales. |
| Error 500 en archivos/Alfresco | Revisar configuración cacheada, conectividad del servicio, credenciales y log; no publicar credenciales para diagnosticar. |

## Documentación relacionada

- [Marcación móvil: contrato API, parámetros, despliegue y QA](docs/MARCACION_MOVIL.md).
- [Índice de especificaciones funcionales de Talento Humano](specs/README.md).
- [Especificación general de Talento Humano](specs/00-modulo-talento-humano.md).
- [Especificación de control de asistencia](specs/03-control-asistencia.md). Complementarla con la guía móvil para los cambios recientes.
- [Notas técnicas, convenciones e incidentes del proyecto](CLAUDE.md).
- [Dependencias y scripts del backend](backend/composer.json) y [del frontend](frontend/package.json).

Actualizar la documentación y los criterios de aceptación junto con el código cuando cambien reglas, endpoints, configuración o procedimientos de operación. Mantener ejemplos con valores ficticios y revisar los comandos en el ambiente previsto antes de compartirlos.
