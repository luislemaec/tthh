# Entorno Docker de desarrollo y pruebas

## Diagnóstico previo (2026-10-08)

Antes de implementar se revisaron los manifiestos, lockfiles, modelos, autenticación, configuración efectiva y PostgreSQL local, sin cambiar su estructura.

| Componente | Hallazgo |
|---|---|
| Backend | Laravel 12.54.1 (`composer.lock`), Sanctum 4.3.1, PHP nativo 8.4.25; Composer declara PHP `^8.2`; las dependencias instaladas exigen PHP 8.4. |
| Frontend | Aplicación independiente en `frontend/`: Vue 3, Vite 7.3, Tailwind 4, Axios, Pinia. |
| Node | `frontend/package.json`: `^20.19.0 \|\| >=22.12.0`; instalación observada: 26.3.0. |
| BD | PostgreSQL 17.9 externo; conexión `pgsql`, `search_path=dbo`. Ambiente local observado: `BDD_RRHH_LIMPIA`. |
| Configuración nativa | `APP_ENV=local`, caché y sesiones `file`, cola `sync`; AD y Alfresco configurados. No se reproducen secretos en este informe. |
| Usuario real del sistema | `App\Models\Empleado`, tabla `dbo.ad_empleado`; PK string `id_emp`, cédula string `identificacion` UNIQUE, departamento obligatorio `id_depto`. El modelo `User` no es el utilizado en este login. |
| Departamento | `Departamento`, tabla `dbo.ad_departamento`, PK `id_depto`. El 62 existe, está ACTIVO y corresponde a DIRECCIÓN DE ADMINISTRACIÓN DEL TALENTO HUMANO. |
| Roles | `AdminRol` / `dbo.admin_rol`; rol 1: ADMINISTRADOR, activo. Asignación `AdminUsuarioRol` / `dbo.admin_usuario_rol`, PK compuesta `(id_emp, identificacion, id_rol)`. Menú mediante `dbo.admin_rol_opcion` y `dbo.admin_opcion`. |
| Relaciones | `Empleado::departamento()` utiliza `id_depto`; `Empleado::roles()` utiliza `id_emp`. No se usa un paquete paralelo de permisos. |
| Autenticación | Busca cédula de empleado ACTIVO, intenta AD y después valida Bcrypt en `password` mediante `AutenticacionService::validarPasswordLocal`. Admite las variantes `2a`, `2b` y `2y`; rechaza texto plano, otros algoritmos y valores mal formados sin generar error 500. Sanctum emite sesiones independientes WEB/APP de 15 minutos. |
| Migraciones | Dos: `0000_01_01_000000_create_initial_rrhh_structure` y `0000_01_01_000001_load_initial_rrhh_base_data`. En la BD local ya están ejecutadas. La primera bloquea instalaciones sobre BD existentes. |
| Seeders | `DatabaseSeeder` llamaba a `DatosBaseInicialesSeeder`; este reutiliza el snapshot de 877 filas y 30 tablas, conservando registros existentes. `ArticulosSeeder` es una importación manual, no una dependencia del arranque. |
| CORS | Rutas `api/*`, métodos/cabeceras/orígenes permitidos, sin cookies; `max_age=0`. Axios agrega Bearer y Content-Type JSON, provocando preflight entre puertos. |
| Arranque nativo | `php artisan serve --host=127.0.0.1 --port=8000` y `npm.cmd run dev -- --host 127.0.0.1 --port 5173 --strictPort`. |
| Rendimiento previo | Captura: carga 27,34 s, GET de 6,36–10,86 s y OPTIONS de 3,23–9,70 s. Medición directa previa del dashboard: 195 ms, 8 consultas/74 ms SQL (sin middleware/HTTP). El servidor HTTP nativo no cargaba OPcache. |

Extensiones requeridas por dependencias y código: PDO/PostgreSQL, mbstring, GD, zip, DOM/XML/SimpleXML/XMLReader/XMLWriter, fileinfo, curl y LDAP para AD, además de las extensiones incluidas en PHP (ctype, filter, hash, iconv, JSON, libxml, OpenSSL, PCRE, session, tokenizer, zlib). SQLite/PDO SQLite se necesitan para los fixtures de pruebas. OPcache se añade para el rendimiento del intérprete. No se requiere Redis, MySQL ni Xdebug para este entorno.

## Decisiones antes de implementar

Tres servicios separados: `backend` ejecuta PHP-FPM 8.4; `nginx` recibe HTTP y sirve archivos públicos; `frontend` conserva Vite/HMR. Separarlos permite sus ciclos de desarrollo y procesos propios sin sustituir Vite por un build estático ni ejecutar Artisan Serve en Docker. PostgreSQL permanece externo. Los puertos Docker serán distintos a los nativos para permitir comparar ambos ambientes sin detenerlos.

La cuenta inicial utilizará los catálogos existentes: departamento 62, rol 1 ADMINISTRADOR. SUPERADMIN es el nombre funcional de esta cuenta, no un rol nuevo. La contraseña vendrá exclusivamente de una variable privada y se generará con `Hash::driver('bcrypt')->make()` durante el seeding. El seeder conservará una contraseña ya cambiada y rechazará una cédula existente con datos distintos a los de la cuenta solicitada.

Existe `/api/cambiar-password`, pero no una columna ni flujo de cambio obligatorio al primer acceso. Añadirlo implicaría modelo, migración, middleware, web y app móvil; no se incluye en esta dockerización. Cambiar la contraseña inicial inmediatamente mediante el flujo existente.

No se modificarán migraciones ejecutadas, snapshots ni restricciones de marcación. Tampoco se iniciarán migraciones, seeders o tareas programadas automáticamente al levantar contenedores.

## Implementación

| Servicio | Ejecución y puerto |
|---|---|
| `nginx` | HTTP en `127.0.0.1:8001`; raíz `backend/public`, FastCGI únicamente para `index.php`, conexión interna a `backend:9000`. |
| `backend` | PHP-FPM 8.4; puerto 9000 accesible solo dentro de la red Compose, sin `artisan serve`. |
| `frontend` | Node 22, `npm ci`, Vite en `127.0.0.1:5174`, HMR por WebSocket y vigilancia por polling para archivos de Windows. |

Las imágenes PHP, Node, Composer y Nginx se fijaron por digest de sus manifiestos oficiales consultados el 2026-10-08. Las dependencias se instalan con `composer.lock` y `package-lock.json`. Actualizar los digests expresamente al incorporar parches; no quedan congelados para siempre por razones de seguridad.

Código montado desde el repositorio; `vendor`, `node_modules`, `bootstrap/cache`, sesiones/vistas/caché de Laravel y logs propios de Docker en volúmenes. Esto evita reutilizar dependencias/cachés de Windows en Linux y reduce lecturas de dependencias por el montaje Windows. `storage/app` permanece compartido con la instalación nativa para reutilizar archivos existentes. Nginx sirve `/storage/` directamente desde `storage/app/public`, sin depender de un enlace simbólico creado en Windows.

Los entrypoints verifican el hash de los lockfiles e instalan dependencias si cambian. El backend prepara carpetas y permisos de caché, elimina solo su caché de configuración Docker y descubre paquetes. No migra, no crea usuarios ni ejecuta tareas institucionales durante el arranque. No se comparte `bootstrap/cache` nativo.

PHP-FPM: pool dinámico, 8 trabajadores máximos, 2 iniciales, 2–4 disponibles y reciclaje después de 500 solicitudes. Medir RAM real antes de aumentar `pm.max_children`; `memory_limit=256M` por solicitud no significa que ocho procesos dispongan de RAM ilimitada. Slowlog a stderr cuando una solicitud supera 3 segundos; terminación a 65 segundos.

OPcache: habilitado para FPM, 192 MB, 20.000 archivos, comprobación de cambios en cada petición (`validate_timestamps=1`, `revalidate_freq=0`). Se deshabilita para CLI para no confundir su estado con el de FPM. Nginx registra `total_s` y `upstream_s`, sin cuerpos, tokens ni query strings. PHP y Laravel Docker escriben a stderr.

CORS mantiene métodos, orígenes y autorización actuales; `CORS_MAX_AGE` permite 600 segundos de caché de preflight en Docker. Su valor predeterminado continúa siendo 0 fuera de Docker. No elimina el preflight inicial ni cambia permisos. Se conservaron las llamadas consecutivas del frontend para no ampliar el alcance funcional; seguirán formando parte del análisis si persiste una demora.

## Configuración privada y PostgreSQL externo

`.env.docker` es un archivo privado ignorado y excluido del contexto de construcción. `compose.yaml` lo carga **solo en backend** con `format: raw`; escribir valores literales sin comillas envolventes ni interpolaciones `${...}`. Así una contraseña que contenga `$` se conserva exactamente. Se requiere Docker Compose 2.30 o posterior.

En esta PC se preparó `.env.docker` a partir de la configuración nativa, conservando la clave de aplicación y las credenciales sin imprimirlas; solo se ajustaron host de BD, URL, logs y caché CORS, y se agregó la contraseña inicial autorizada. No se modificó `backend/.env`.

En una instalación nueva, copiar `.env.docker.example` y completar:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=host.docker.internal
DB_PORT=5432
DB_DATABASE=<base-autorizada-del-ambiente>
DB_USERNAME=<usuario-del-ambiente>
DB_PASSWORD=<secreto-privado>
APP_KEY=<clave-propia-del-ambiente>
BOOTSTRAP_ADMIN_PASSWORD=<contraseña-inicial-entregada-por-canal-privado>
```

`host.docker.internal` permite llegar al PostgreSQL del host Windows; `localhost` dentro de backend apuntaría al propio contenedor. `extra_hosts: host-gateway` también permite el caso de un host Linux. Si la BD está en otro servidor, configurar su host real únicamente en el archivo privado. No existe servicio PostgreSQL ni Redis en Compose.

PostgreSQL debe escuchar en una interfaz accesible desde Docker y aceptar la conexión en `pg_hba.conf`; el firewall debe permitir únicamente la red necesaria. Primero comprobar el error concreto y la IP de origen antes de adaptar esas reglas. No usar `trust`, no abrir indiscriminadamente el puerto y no reutilizar `127.0.0.1` como supuesto origen del contenedor.

AD y Alfresco conservan sus variables existentes, configuradas en privado. Comprobar alcance de red, resolución DNS y certificados desde el contenedor. Para una prueba exclusiva del hash local se puede dejar `AD_HOST` vacío en ese ambiente, sin cambiar producción. Una espera de AD inaccesible puede aumentar el tiempo del login.

Los parámetros de publicación Compose se leen del entorno de la terminal o del `.env` raíz (también ignorado), **no de `.env.docker`**: `DOCKER_BIND_ADDRESS`, `DOCKER_BACKEND_PORT`, `DOCKER_FRONTEND_PORT`, `DOCKER_API_URL`. Al cambiar el puerto backend, actualizar también `DOCKER_API_URL` y `APP_URL`. `VITE_*` son públicos: no colocar secretos allí.

## Comandos en orden (CMD, desde la raíz del repositorio)

**Estado actual:** las imágenes ya construyen y los tres servicios están saludables. El motor WSL fue habilitado después del incidente inicial. La configuración privada de esta PC ya existe; no sobrescribirla con el ejemplo.

```cmd
cd /d C:\Users\llema\Documents\workspace\workspace_tthh\rrhh
docker version
docker compose version
if not exist .env.docker copy .env.docker.example .env.docker
notepad .env.docker
docker compose config --quiet
docker compose build
docker compose up -d
docker compose ps
docker compose logs --tail=100 backend nginx frontend
```

Si falta una clave y es una instalación realmente nueva, generarla con `docker compose run --rm --no-deps --entrypoint php backend artisan key:generate --show` y guardarla en `APP_KEY` del archivo privado. Conservar la clave en bases y archivos existentes. No ejecutar `key:generate` sin `--show`: el directorio nativo está montado y podría cambiar `backend/.env`.

Comprobar la conexión antes de migrar:

```cmd
docker compose exec backend php artisan migrate:status
```

En la **BD local actual** las dos migraciones ya están ejecutadas. Se puede ejecutar `migrate` para verificar que no hay pendientes; no recrear la base:

```cmd
docker compose exec backend php artisan migrate --database=pgsql
docker compose exec backend php artisan db:seed --class=SuperAdminInicialSeeder
```

Para una **BD nueva vacía**, crear primero la base y el esquema `dbo` con la herramienta PostgreSQL del host y configurar ese nombre en el archivo privado. Entonces:

```cmd
docker compose exec backend php artisan migrate --database=pgsql
docker compose exec backend php artisan db:seed
```

La primera migración crea la estructura; la segunda carga catálogos. `DatabaseSeeder` confirma/conserva los catálogos y luego ejecuta `SuperAdminInicialSeeder`. Las migraciones iniciales no se deben ejecutar sobre una BD institucional existente sin el procedimiento de adopción revisado en `backend/database/baseline/README.md`.

La cuenta inicial tiene cédula string `0123467890`, nombre/apellido Admin, departamento `id_depto=62`, estado ACTIVO y rol `id_rol=1` ADMINISTRADOR. Se genera `id_emp` mediante el método y bloqueo PostgreSQL existentes. La contraseña solicitada se obtiene de `BOOTSTRAP_ADMIN_PASSWORD`, se procesa con `Hash::driver('bcrypt')->make()` y se guarda en `password`; no se escribe en SQL, imagen, seeder ni documentación versionada. Cambiarla después de ingresar por Mi Perfil. La repetición conserva la contraseña cambiada; si la cédula ya pertenece a otro empleado, el seeder se detiene y no lo promueve.

Administración habitual:

```cmd
docker compose exec backend php artisan config:clear
docker compose exec backend php artisan migrate:status
docker compose exec backend composer check-platform-reqs
docker compose exec backend php artisan test
docker compose exec frontend npm run build
docker compose exec nginx nginx -t
docker compose exec backend php-fpm -tt
docker compose logs -f
docker compose down
```

`down` conserva los volúmenes y no afecta PostgreSQL externo. No utilizar `down -v` como rutina: eliminaría dependencias, sesiones y cachés/logs Docker. Las dependencias del host y sus archivos `.env` permanecen independientes. Para volver a levantar: `docker compose up -d`.

URLs predeterminadas: frontend `http://127.0.0.1:5174`, API `http://127.0.0.1:8001/api`. Para pruebas desde un teléfono, publicar expresamente en la interfaz requerida y configurar las URLs con la IP accesible de la PC, tanto en Vite como en Flutter; `127.0.0.1` en el teléfono es el teléfono. El HMR usa el puerto público configurado en `DOCKER_FRONTEND_PORT`.

## Verificación y comparación de rendimiento

```cmd
docker compose exec nginx nginx -t
docker compose exec backend php-fpm -tt
docker compose exec backend php /usr/local/bin/fpm-health.php --diagnostic
docker compose exec backend php artisan migrate:status
curl.exe -s -o NUL -w "estado=%{http_code} ttfb=%{time_starttransfer}s total=%{time_total}s\n" http://127.0.0.1:8001/up
```

Los comandos corresponden a CMD interactivo; si se guardan en un archivo `.bat`, duplicar los signos `%` de curl. El diagnóstico FastCGI debe devolver `sapi=fpm-fcgi` y `opcache_enabled=true`; prueba un trabajador real mediante un archivo temporal interno que se elimina al finalizar. No publica endpoints de diagnóstico.

Verificar preflight desde el origen del frontend Docker:

```cmd
curl.exe -i -X OPTIONS -H "Origin: http://127.0.0.1:5174" -H "Access-Control-Request-Method: GET" -H "Access-Control-Request-Headers: authorization,content-type" http://127.0.0.1:8001/api/dashboard
```

Debe responder 204 con autorización CORS y `Access-Control-Max-Age: 600`. Abrir el frontend, autenticar la cuenta inicial, cargar dashboard y asistencia. Confirmar desde Red que apunta al puerto 8001 y registrar estado, espera/TTFB, duración y secuencia de llamadas. Probar HMR cambiando un archivo Vue y revirtiendo ese cambio.

Comparar con el mismo usuario, base, pantalla y número de peticiones: al menos una carga fría y tres cargas posteriores tanto nativas como Docker. No sumar duraciones de peticiones que se superponen. `/up` no mide las consultas de dashboard ni el AD.

Para localizar una demora: el navegador mide la carga total y la secuencia; curl separa conexión y TTFB; Nginx registra `total_s` frente a `upstream_s`; el slowlog FPM permite encontrar una solicitud PHP de más de 3 s; Laravel/DB::listen permiten medir número y duración de consultas sin registrar parámetros sensibles. Consultar esos logs juntos, no atribuir la demora automáticamente a Docker. Las llamadas consecutivas y el AD son hipótesis que necesitan medición propia.

En Windows, los montajes desde `C:` pueden penalizar lecturas de archivos. Si las mediciones lo muestran, evaluar una copia de desarrollo en el sistema de archivos Linux de WSL; no mover automáticamente este checkout ni sus archivos. [Recomendación de Docker para WSL](https://docs.docker.com/desktop/features/wsl/best-practices/).

## Evidencia de esta entrega y pendientes

| Verificación realizada | Resultado |
|---|---|
| Compose y ausencia de servicio de BD | `docker compose config --quiet` correcto; servicios backend/nginx/frontend. |
| Seeder aislado | 5 pruebas, 17 aserciones: bcrypt, autenticación, cero inicial, relaciones, repetición sin cambiar contraseña, catálogo incompleto y colisión con otra cuenta. |
| PostgreSQL real temporal | `php tools/verificar-baseline.php` correcto: dos migraciones, 877 filas base, 43 conversiones VARCHAR y seeder inicial repetible con departamento 62/rol 1/hash bcrypt/login. La BD temporal fue eliminada por el verificador. |
| BD local existente | Cuenta inicial creada mediante el seeder en BDD_RRHH_LIMPIA; una cuenta/una asignación rol 1, departamento 62, bcrypt y verificación de contraseña correctos. Segunda ejecución conservó el hash. No se cambió la estructura ni el historial. |
| Login Laravel real | Kernel HTTP nativo: 200, rol ADMINISTRADOR, 6 opciones de menú y expiración devuelta. Tokens/auditorías de esta prueba se revirtieron; el usuario inicial permanece creado. |
| Frontend nativo | `npm run build` correcto (227 módulos, 5,74 s). |
| Suite completa | 20 pruebas aprobadas, 91 aserciones. Las fallas GPS previas se resolvieron fijando el escenario de prueba y verificando que los parámetros de BD prevalecen. No se modificaron límites operativos. |
| Formato/sintaxis | Seeder/tests/config nuevos pasan Pint y sintaxis PHP; revisar también las configuraciones de Nginx y FPM dentro de sus contenedores cuando arranquen. |
| Construcción Docker | Backend y frontend construidos correctamente; corregido propietario de `/app` para instalar npm como usuario node. Tres servicios saludables. |
| HTTP antes/después | Comparación HTTP realizada en la misma PC/BD/cuenta; resultados detallados abajo. La captura previa de 27,34 s no es una comparación equivalente de navegación completa. |

Incidente inicial resuelto: Docker Desktop había reportado `Wsl/Service/CreateInstance/CreateVm/HCS/0x80070569`. Posteriormente el motor quedó disponible (Docker Desktop 4.94.0 / Engine 29.8.2). No se cambiaron políticas de Windows desde esta tarea ni se eliminaron distribuciones. La construcción posterior detectó EACCES en frontend: `/app` era propiedad de root aunque los archivos tenían propietario node. Se agregó `RUN chown node:node /app` antes de `USER node`; la instalación y el arranque ya pasan sin ejecutar npm como root.

La marcación web PRESENCIAL conserva la restricción de red institucional. Docker Desktop puede presentar a Nginx una IP de gateway/NAT en vez de la IP del dispositivo; verificar la IP recibida antes de concluir que la marcación falló por Docker. No se agregaron redes permitidas ni se confía en cabeceras arbitrarias para sortear esa restricción. La app sigue sus reglas de geolocalización y los canales mantienen sesiones de 15 minutos. Las marcaciones no se probaron mediante inserciones reales en esta entrega.

Se vaciaron secretos del respaldo antiguo versionado `backend/.envoriginal`; la configuración activa `backend/.env` no se modificó. Las reglas nuevas impiden incorporar archivos privados `.env*` nuevos, con excepciones explícitas solo para los ejemplos seguros. No se imprimieron contraseñas, tokens ni claves.

## Archivos de la entrega

Nuevos: `compose.yaml`, `.dockerignore`, `.env.docker.example`, `.gitattributes`; `docker/php/{Dockerfile,php.ini,fpm.conf,entrypoint.sh,fpm-health.php}`; `docker/nginx/default.conf`; `docker/frontend/{Dockerfile,entrypoint.sh}`; `backend/config/bootstrap_admin.php`; `backend/database/seeders/SuperAdminInicialSeeder.php`; `backend/tests/Feature/SuperAdminInicialTest.php`; esta guía.

Modificados: `.gitignore`, `README.md`, `backend/.env.example`, `backend/.envoriginal` (solo campos privados vacíos), `backend/config/cors.php`, `backend/database/seeders/DatabaseSeeder.php`, `frontend/vite.config.js`, `tools/verificar-baseline.php`, `backend/database/baseline/README.md`. `.env.docker` fue creado localmente y **no se versiona**. No se modificaron migraciones, snapshot inicial, estructura de la BD existente, controladores de negocio ni reglas de marcación.

## Producción

Este Compose es para desarrollo/pruebas: monta el código, ejecuta Vite, incluye dependencias de pruebas y `APP_DEBUG=true` en el ejemplo. No publicarlo como configuración de producción.

Producción nativa continúa con su procedimiento actual. Descargar estos archivos no instala ni inicia Docker. No copiar `.env.docker` al servidor y no ejecutar el seeder inicial sobre producción sin una decisión expresa de operación.

Para una futura imagen de producción: código inmutable y frontend compilado, Composer sin dependencias dev, `APP_DEBUG=false`, HTTPS, secretos por ambiente y OPcache con `validate_timestamps=0` únicamente si cada despliegue reinicia/reemplaza FPM. Dimensionar trabajadores con mediciones, restringir orígenes CORS, configurar proxy/IP real solo para intermediarios confiables y definir workers/scheduler según las tareas existentes. Mantener la clave de aplicación y respaldos de PostgreSQL; no usar migraciones destructivas.

Referencias: [Docker/PHP](https://docs.docker.com/guides/php/), [red del host en Docker Desktop](https://docs.docker.com/desktop/features/networking/), [opciones de servidor Vite](https://vite.dev/config/server-options), [PHP-FPM](https://www.php.net/manual/en/install.fpm.php).

Referencia para el administrador del equipo: [Microsoft: error 0x80070569 en virtualización](https://learn.microsoft.com/en-us/troubleshoot/windows-server/virtualization/starting-or-live-migrating-hyper-v-vms-fails). Microsoft documenta la falta del derecho de inicio de sesión como servicio de la identidad de máquinas virtuales como una causa de este error en Hyper-V; en esta PC aún debe verificarse la política efectiva.

## Verificación Docker completada el 2026-10-08

- `docker compose build`: ambas imágenes construidas; `/app` y `node_modules` escribibles por UID 1000 node.
- `docker compose up -d` / `ps`: backend, nginx y frontend en ejecución y saludables. Backend no publica FastCGI; Nginx publica 8001 y Vite 5174.
- `nginx -t` y `php-fpm -t`: correctos. El log identifica la ruta original de la API, excluye query strings y registra tiempos total/upstream.
- Diagnóstico FastCGI: `sapi=fpm-fcgi`, PHP 8.4.26, OPcache cargado/activo, 687 scripts cacheados al verificar. Composer confirma todos los requisitos.
- PostgreSQL externo accesible; ambas migraciones [1] Ran; `migrate` respondió `Nothing to migrate`. Seeder ejecutado en Docker conservó la cuenta y contraseña existentes.
- Login HTTP `/api/login`: 200, ADMINISTRADOR, expiración de 15 minutos, 1.339 ms incluyendo autenticación híbrida y bcrypt. No se imprimió el token ni la contraseña.
- Endpoints de dashboard, mantenimiento, avisos, pendientes, atrasos y asistencia: 200 en las tres lecturas de cada uno. No se registraron marcaciones reales.
- Preflight Docker: 204 en 87 ms, origen permitido y `Access-Control-Max-Age: 600`; nativo: 204 en 124 ms y max-age 0.
- Frontend: HTML y módulos 200; API configurada a 8001; WebSocket HMR conectado a 5174. Build de 227 módulos correcto, 11,75 s dentro del contenedor.
- Pruebas `SuperAdminInicialTest` en Docker: 5 aprobadas, 17 aserciones, 3,12 s.

### Mediciones comparables de API

Misma PC, PostgreSQL BDD_RRHH_LIMPIA, cuenta inicial ADMINISTRADOR y token, sin registrar marcaciones. Servidor nativo temporal en 18001, iniciado con `artisan serve` estándar, detenido al terminar. En esta comparación se comprobó que el servidor nativo **también tenía OPcache activo**; no corresponde al estado de la captura inicial. El backend nativo usó PHP 8.4.25 y Docker 8.4.26.

Tres solicitudes seriales por endpoint; la tabla muestra la mediana, en milisegundos:

| Endpoint | Docker | Nativo temporal |
|---|---:|---:|
| Dashboard | 635 | 467 |
| Mantenimiento | 286 | 273 |
| Avisos activos | 449 | 295 |
| Atrasos coordinación | 429 | 329 |
| Pendientes | 474 | 397 |
| Asistencia: estado | 466 | 430 |
| Asistencia: historial | 520 | 375 |

Primera lectura protegida del dashboard: Docker 785 ms; nativo 3.307 ms. Las siguientes fueron Docker 635/599 ms y nativo 428/467 ms. No se presenta esa primera lectura como un arranque frío completo de FPM, porque el healthcheck ya había cargado Laravel.

Una tanda de cinco lecturas simultáneas (dashboard, avisos, atrasos, pendientes y estado de asistencia) terminó en **1.061 ms Docker** y **2.039 ms nativo**. Todas respondieron 200. Es una muestra básica de concurrencia, no una prueba de carga ni una garantía de reducción del 48 % para todos los usuarios. En solicitudes individuales calientes, Docker fue más lento en esta muestra; los montajes desde Windows siguen siendo un candidato para medir si se busca optimizar más.

Perfil directo del controlador del dashboard dentro de Docker, en transacción de solo lectura: 114 ms, 8 consultas con 45,7 ms SQL. Esa medida no incluye HTTP, middleware ni autenticación. Nginx registró tiempos upstream próximos al total, indicando que la transferencia/proxy consumió una parte pequeña de las peticiones medidas.

La captura anterior de 27,34 s correspondía a otra navegación y cuenta; no se calculó un porcentaje de mejora respecto de esa captura. Falta repetir esa navegación completa en el navegador si se requiere un tiempo global de pantalla comparable. Tampoco se validaron marcaciones reales, AD para otras cuentas ni Alfresco en todos los módulos. Nginx recibió IP de gateway `172.21.0.1` en estas pruebas locales: conservar las restricciones de red institucional y revisar el despliegue antes de probar marcación web PRESENCIAL desde Docker.

### Orden para evitar «service backend is not running»

`build` crea imágenes; no inicia contenedores. `exec` requiere que el servicio esté iniciado. En CMD:

```cmd
docker compose build
docker compose up -d
docker compose ps
docker compose exec backend php artisan migrate:status
docker compose exec backend php artisan migrate --database=pgsql
docker compose exec backend php artisan db:seed --class=SuperAdminInicialSeeder
```

En esta PC esos pasos ya se ejecutaron correctamente. Abrir `http://127.0.0.1:5174`; el servidor nativo 5173 sigue siendo otro ambiente. Para detener Docker: `docker compose down`, sin eliminar volúmenes ni PostgreSQL.

## Corrección de las pruebas GPS (2026-10-08)

Las pruebas usaban umbrales fijos (radio 50 m, precisión 25 m, antigüedad 30 s), pero tomaban implícitamente los valores del ambiente (1000 m, 100 m, 200 s). Por eso una lectura de 26 m de precisión o 31 s de antigüedad podía admitirse, mientras el test esperaba 422. `MarcacionSitTest::setUp()` ahora define su propio escenario GPS, solo en la aplicación de pruebas.

Se agregó una prueba de parámetros guardados en la BD de memoria: confirma que 1000/100/200 sustituyen los límites del escenario base, acepta una lectura dentro de esos valores y rechaza precisión 101 m, distancia excesiva y antigüedad 201 s. Conserva las verificaciones de secuencia, antigüedad, simulación, sesiones, red institucional y desafío no reutilizable.

Verificación en Docker: `php artisan test` termina con **20 pruebas aprobadas y 91 aserciones**, en 5,68 s. No se modificó `backend/config/marcacion.php`, la BD local ni las reglas del aplicativo. Archivo adicional modificado: `backend/tests/Feature/MarcacionSitTest.php`.
