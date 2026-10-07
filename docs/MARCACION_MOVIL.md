# Integración de marcación móvil con SIT

La app Flutter `app_consejo` incorpora **Servicios Ciudadanos → Talento Humano → Marcación**. El Registro de Alerta Previa continúa siendo público y utiliza su API existente. SIT utiliza una conexión independiente a `https://sit.consejodecomunicacion.gob.ec/api`.

## Sesiones y autorización

- Login móvil por cédula y contraseña: reutiliza la autenticación AD/local de la web y `dbo.ad_empleado`. No crea otra base de usuarios.
- Ambas sesiones vencen **15 minutos después del login**, aunque haya actividad. No hay renovación automática.
- Se permite una sesión web y una móvil por empleado. Un nuevo login reemplaza únicamente la sesión del mismo canal.
- Los tokens móviles tienen capacidad `sit:marcacion` y solo permiten consultar la sesión, consultar el estado propio, marcar y cerrar sesión. No acceden a empleados, nómina, administración ni `/me`.
- Empleado activo con un rol activo que conceda la opción `asistencia`, o con rol ADMINISTRADOR/TALENTO HUMANO. BIOMETRICO queda bloqueado; TELETRABAJO exige un período vigente.
- La app almacena únicamente token y vencimiento en almacenamiento seguro. Nunca guarda la contraseña. HTTPS es obligatorio en la API móvil en producción y en la app compilada en release.
- Login limitado a 5 solicitudes por cédula/minuto y 30 por IP/minuto, compartidos entre canales.

## Marcación

La web y la app llaman a la misma lógica del backend. La fila del empleado se bloquea dentro de una transacción PostgreSQL para serializar peticiones simultáneas, volver a comprobar autorización y evitar duplicados entre canales. La hora guardada siempre es la del servidor.

Se conservan ENTRADA, SALIDA AL LUNCH, ENTRADA DEL LUNCH y SALIDA, su orden y el registro existente en `dbo.sg_control_persona`. `origen` y `lugar` distinguen APP/WEB; `tipo_marcacion` conserva WEB/TELETRABAJO para compatibilidad con el cuadre. La app confirma visualmente la salida anterior a las 16:30 usando la hora del servidor.

Se eliminó el control de una IP por empleado. El parámetro antiguo `control_ip_marcacion` ya no se consulta. La web PRESENCIAL conserva la restricción por prefijos institucionales en `vlans_permitidas`; si falta o está vacío, esa marcación se bloquea. La app puede usar Wi-Fi, VPN o datos móviles y aplica ubicación únicamente para PRESENCIAL. TEMPORAL y TELETRABAJO no piden ubicación.

## Parámetros de ubicación

Valores predeterminados en `backend/config/marcacion.php`; se pueden sobrescribir en Administración → Configuración, en `dbo.d2_configuracion`. **Cargar parámetros base** agrega los parámetros que falten sin sobrescribir los existentes.

| Concepto | Valor inicial |
|---|---|
| `app_latitud` | `-0.1805373` |
| `app_longitud` | `-78.4892070` |
| `app_radio_m` | `50` |
| `app_precision_m` | `25` |
| `app_antiguedad_s` | `30` |

La lectura debe ser reciente, no simulada y tener precisión ≤25 m. Para aceptar dentro de la sede se exige **distancia al centro + precisión ≤50 m**. Cerca del borde puede bloquearse aunque el punto estimado esté dentro; acercarse al centro o mejorar la precisión resuelve ese caso.

Se usa un desafío cifrado, ligado al token, con vencimiento y uso único, almacenado en caché para impedir su reutilización. La caché del servidor debe funcionar y ser compartida si existen varias instancias. Los relojes de servidor y dispositivo deben estar sincronizados. La auditoría guarda distancia/precisión y canal; no guarda las coordenadas personales del teléfono.

El permiso de ubicación se solicita al pulsar marcar, sin seguimiento continuo. La validación bloquea lecturas simuladas declaradas por el sistema operativo; por sí sola no prueba que un dispositivo manipulado no falsifique GPS. La integración no incluye atestación de dispositivos.

### Lectura antigua o diferencia entre relojes

La app descarta posiciones fechadas antes del intento de captura (tolerancia local de 3 segundos), espera una lectura reciente y cancela la suscripción al recibirla o al agotar 20 segundos. Envía el timestamp original del GPS; no lo sustituye por la hora del servidor o del envío.

SIT mantiene el máximo parametrizado de 30 segundos y rechaza una fecha más de 3 segundos por delante del servidor. El mensaje distingue ambos casos y muestra los segundos de diferencia. La advertencia de revisar la lista por un corte de conexión se muestra para errores de red, no para errores de validación de ubicación.

Si el celular y SIT muestran horas distintas, revisar fecha/hora automáticas y zona de Ecuador (`America/Guayaquil`, UTC−05:00) en el celular y la PC. En Windows, usar la sincronización de hora del sistema o ejecutar `w32tm /resync` en una terminal con permisos de administrador. No modificar las fuentes de tiempo de dominio sin coordinación institucional. La zona horaria por sí sola no equivale a un desfase de timestamps UTC; el nuevo mensaje permite comprobar la diferencia real de la lectura.

Pruebas del ajuste: 12 pruebas de marcación backend y 12 pruebas móviles correctas, incluida cancelación de GPS tras timeout.

## Contrato API

| Método y ruta relativa a `/api` | Uso |
|---|---|
| POST `/mobile/login` | `identificacion`, `password`; devuelve token, `expires_at`, empleado mínimo, capacidades y mensaje de bloqueo |
| GET `/mobile/session` | Restaurar y validar sesión móvil; no renueva vencimiento |
| GET `/asistencia/mi-estado` | Estado diario, siguiente concepto, hora del servidor, necesidad de ubicación y desafío |
| POST `/asistencia/marcar` | Concepto, desafío y ubicación cuando corresponda; devuelve fecha/hora confirmadas |
| POST `/logout` | Revocar solo la sesión actual |

Ejemplo del cuerpo de marcación presencial móvil:

```json
{
  "concepto": "ENTRADA",
  "desafio": "<emitido por mi-estado>",
  "ubicacion": {
    "latitud": -0.1805373,
    "longitud": -78.4892070,
    "precision": 10,
    "capturada_en": "<fecha ISO 8601 de la lectura GPS>",
    "simulada": false
  }
}
```

Todas las llamadas privadas usan `Authorization: Bearer <token>`. 401 pide login; 403 bloquea permisos/red; 422 explica la validación; 429 indica exceso de intentos. Ante un corte de conexión al marcar, consultar el estado antes de repetir: no hay reintento automático.

## Despliegue manual

Primero desplegar SIT y después distribuir la app. El usuario ejecuta los comandos en el servidor; no se han ejecutado aquí. Revisar cambios y respaldar antes del despliegue habitual.

```bash
# En el repositorio SIT del servidor
git pull
cd backend
php artisan config:clear
php artisan route:clear
php artisan migrate --path=database/migrations/2026_10_07_000113_fix_control_persona_origen_type.php
cd ../frontend
npm run build
```

La lógica móvil no cambia las credenciales AD/BD. La migración `2026_10_07_000113_fix_control_persona_origen_type.php` corrige el relleno de espacios de `origen`: convierte `CHAR(120)` a `VARCHAR(120)` y elimina los espacios finales existentes, conservando las filas y los valores NULL. Se aplicó únicamente esta migración en la BD local; en producción debe ejecutarla el usuario. No ejecutar migraciones pendientes ajenas a este cambio. Conservar las rutinas de caché del despliegue existente después de limpiar la configuración. Comprobar `APP_ENV=production`, certificado HTTPS, proxies de confianza y `vlans_permitidas`. Tras desplegar, las sesiones antiguas de más de 15 minutos exigirán volver a ingresar.

En el proyecto Flutter:

```powershell
flutter pub get
flutter analyze
flutter test
flutter build apk --debug
# Tras QA y con la firma existente configurada:
flutter build appbundle --release
```

`assets/.env` contiene `SIT_API_BASE_URL`; no debe contener secretos. Android solicita ubicación precisa y desactiva backup para proteger el token; mínimo efectivo API 24 con Flutter 3.44. iOS tiene descripción de permiso mientras se usa y entitlements Keychain; compilar y verificar en macOS/Xcode antes de distribuir en iOS. Revisar la configuración `BYPASS_PERMISSION_LOCATION_ALWAYS=1` del plugin geolocator_apple antes de publicar en App Store; no se solicita ubicación en segundo plano.

APK Android de pruebas compilada correctamente: `build/app/outputs/flutter-apk/app-debug.apk` del proyecto Flutter. Apunta a la URL institucional configurada; los endpoints móviles deben desplegarse en SIT antes de probarla contra producción.

## Verificación antes de distribuir

Pruebas backend herméticas en SQLite en memoria: sesión simultánea por canal, revocación, vencimiento exacto, API restringida, login HTTPS, GPS inválido, parámetros, desafío no reutilizable, red web, equipos compartidos, secuencia y modalidades. No prueban la ejecución real del bloqueo PostgreSQL bajo carga.

Pruebas móviles con HTTP/repositorios simulados: bearer POST, almacenamiento sin contraseña, vencimiento, 401, cierre sin red, acceso privado, confirmación de salida temprana y ubicación denegada.

Validación local realizada: PHPUnit **13 pruebas / 57 comprobaciones**, build Vite correcto y **10 pruebas nuevas Flutter correctas**. `flutter analyze` no encuentra problemas nuevos, pero mantiene un aviso previo de variable sin usar en `inicio_screen.dart:262`. La suite Flutter completa mantiene una prueba previa fallida de imagen en `inicio_screen_test.dart:21`; esos archivos no fueron modificados por esta integración. La dependencia transitiva `package_info_plus` genera un aviso de compatibilidad Kotlin para futuras versiones de Flutter; evitar actualizaciones mayores sin verificar los plugins.

QA en teléfono real: login AD/local, Wi-Fi/datos móviles, permisos precisos/aproximados/denegados, interior/exterior y borde de 50 m, sesiones web+app y segundo dispositivo, reanudar después de 15 minutos, teletrabajo vigente/vencido, TEMPORAL, BIOMETRICO y corte de red. Verificar que el cuadre y los reportes toman la marcación APP y que los servicios ciudadanos y anexos conservan su funcionamiento.
