# Servicios globales de interfaz: status y globals

Actualización: 2026-10-08. Adaptación de la referencia TEC a Vue/Tailwind.

## Qué hacen los archivos de TEC

Referencias bajo `C:/Users/llema/Documents/workspace/workspace_tec/tec/src/main/webapp`:

| Archivo/recurso | Función real | Equivalente/adaptación en SIT |
| --- | --- | --- |
| `WEB-INF/status.xhtml` | `p:ajaxStatus` abre `PF('statusDialog')` al comenzar AJAX y lo cierra al completar o fallar. Diálogo modal, sin cierre manual, con `resources/img/loader.gif`. | Indicador superior no bloqueante en `SitGlobals.vue`, conectado al cliente Axios compartido. Usa animación CSS, sin GIF ni dependencia nueva. |
| `WEB-INF/globals.xhtml`: tooltip | Tooltip para `[title]`, excepto interior del editor, siguiendo el puntero. | Se conservan títulos nativos y etiquetas accesibles de los controles de SIT. No se interceptan indiscriminadamente elementos del editor. |
| `globals.xhtml`: scrollTop | Flecha arriba al superar 400 px de scroll de `window`. | `SitScrollTop.vue` observa los 400 px del scroll real de `.sit-shell__workspace`. El body de SIT no desplaza. Se ubica separado del chatbot y respeta movimiento reducido. |
| `globals.xhtml`: growl | Mensajes globales del servidor con autoUpdate; summary visible, detail oculto, duración 5000 ms. | `notificar()` y `SitToast.vue`. Mensaje y severidad visibles; pausa al pasar el puntero/enfocar. Los errores permanecen hasta cerrarlos. Sin mostrar automáticamente cada respuesta API. |
| `globals.xhtml`: confirmDialog | Diálogo responsive de 420 px con Confirmar/Cancelar, compartido por `p:confirm`. | `confirmarAccion()` devuelve una Promise. Diálogo nativo modal, foco inicial en Cancelar, Escape cancela, acepta acciones destructivas con estado danger. Cola serializada y cancelación al navegar/cerrar sesión. |
| `globals.xhtml`: idleMonitor | Comentario: aviso a 14 min. Valor efectivo: `timeout=3000000`, es decir 50 min de inactividad. `web.xml` fija sesión a 15 min. Su botón Continuar usa AJAX. | Aviso durante los últimos 60 segundos del `expires_at` existente. No depende de inactividad ni extiende la sesión de 15 minutos desde login aprobada para SIT. Entendido solo oculta el aviso. |
| `template.xhtml` | Incluye ambos archivos; además añade otro `p:ajaxStatus` con spinner, junto con CSS/JS generales. | Un único montaje global en `App.vue`. No se duplican loaders modal/spinner/barra. |
| `resources/demo/js/tribunal-globals.js` | `Tribunal.toast`, puente `notify` al growl, máscaras Inputmask, mayúsculas, apertura de widgets y Escape. Conecta NProgress al API `jsf.ajax` si existe. | API ES modules/Vue propia. Se conserva la directiva de mayúsculas actual. No se importan máscaras que alteren fechas, cédulas o valores enviados sin revisar cada formulario. |
| `resources/demo/css/nprogress.min.css`, `js/nprogress.min.js` | NProgress 0.2.0, barra de progreso indeterminada; el override global la deja blanca y de 3 px. | Barra CSS de 3 px con tokens SIT y movimiento reducido. No representa porcentaje ni promete tiempo de respuesta. |
| `resources/demo/css/tribunal-globals.css` | Tokens tipográficos y de severidad, presentación de growl, tooltip, scrollTop, botones y diálogos. | Se reutilizan `--sit-*`; `sit-globals.css` contiene exclusivamente reglas de los componentes globales. |
| `resources/ecuador-layout/css/layout-tribunal.css`, `js/layout.js` | Capas y estilos del layout; cierre de desplegables. Los widgets de globals/status dependen también de recursos generados por PrimeFaces. | Estado reactivo, diálogo HTML nativo y manejo de foco. Sin `PF()`, Jakarta Faces, jQuery, JSF AJAX ni PrimeFlex. |

NProgress de TEC comprueba específicamente `window.jsf.ajax`; cargar su JS no garantiza que reciba todo el AJAX de PrimeFaces/Jakarta Faces. Copiarlo a SIT dejaría la barra sin conexión con Axios. La adaptación registra realmente cada operación del cliente API usado por los módulos.

## Integración y contratos

La actividad funciona para todas las llamadas realizadas mediante `services/api.js`. Cada petición incrementa un contador y se cierra una sola vez al completar, fallar o cancelarse. Si finaliza una petición mientras otra sigue activa, el indicador permanece. Aparece tras 200 ms y evita destellos muy breves. No cambia los payloads, permisos, respuestas ni errores devueltos a las vistas.

Las consultas de fondo pueden utilizar `sitBackground: true`; se aplica al sondeo periódico de notificaciones de Transporte para no mostrar actividad cada 30 segundos. Las consultas iniciales de datos siguen siendo visibles. Las cargas locales conservan sus indicadores y los botones de guardar/marcar conservan sus bloqueos existentes.

```js
import { confirmarAccion, notificar } from '@/services/ui'

if (!await confirmarAccion({
  titulo: 'Eliminar registro',
  mensaje: '¿Eliminar este registro?',
  aceptar: 'Eliminar',
  peligrosa: true,
})) return

// Ejecutar aquí la llamada API existente y después notificar su resultado real.
notificar('Registro eliminado.', 'success')
// Severidades: success, info, warn, danger. Nunca pasar HTML.
```

Las confirmaciones se resuelven `false` al cancelar/Escape o cambiar de ruta/sesión. No sustituir `window.confirm` globalmente: el contrato nuevo es asíncrono y requiere `await` en cada acción. Eventos close atrasados de un diálogo anterior no resuelven el siguiente. La cola no ejecuta operaciones: la acción sigue en su vista y el backend mantiene su validación.

Primera adopción concreta:

- Parámetros del sistema: confirmaciones de eliminar/cargar bases y avisos de guardar/eliminar/cargar/error. Los endpoints y reglas existentes permanecen.
- Perfil: aviso compartido al cambiar contraseña; errores de validación siguen junto al formulario.
- Todas las páginas: indicador API. Plantilla autenticada: volver arriba. Sesión autenticada con `expires_at`: aviso previo al vencimiento.

Las demás vistas conservan sus mensajes y confirmaciones actuales para migrarlos por módulo. La confirmación de salida temprana y los resultados de marcación de Asistencia conservan su interfaz y comportamiento específico. No hay captura automática de errores que duplique esos mensajes. Los tooltips nativos existentes permanecen; no se introduce un widget global de seguimiento del puntero.

## Archivos

- `components/SitGlobals.vue`: indicador, avisos, confirmación activa y aviso de sesión; montado una sola vez en App.
- `components/SitToast.vue`, `SitConfirmDialog.vue`, `SitScrollTop.vue`: componentes reutilizables.
- `services/ui.js`: estado compartido, avisos y cola de confirmaciones.
- `services/operaciones.js`: contador idempotente y cálculo de tiempo absoluto.
- `styles/sit-globals.css`: consume colores, fuente y tamaños globales.
- `services/api.js`: conexión de actividad; `stores/auth.js`: expone el vencimiento que ya aplica el store.

## Validación y alcance

Pruebas automatizadas de concurrencia/idempotencia, expiración absoluta, confirmaciones en cola, cancelación por navegación y límites de avisos. Chrome con API simulada: completar peticiones en diferente orden, fallo del servidor, cancelar/confirmar sin operaciones dobles, foco del diálogo, scroll del workspace, lectura/pausa/cierre de toast, diálogo a 320 px con texto al 125 % y aviso a los 14 minutos con expiración a los 15. Compilación Vite y pruebas de navegación/preferencias.

No requiere migraciones, nuevas dependencias ni configuración de producción. La nueva capa informa actividad; no reduce por sí misma la latencia del servidor. Validar las acciones de cada módulo con cuentas reales en pruebas antes de adoptar sus avisos/confirmaciones compartidos.
