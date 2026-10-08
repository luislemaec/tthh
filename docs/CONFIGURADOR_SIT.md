# Análisis del configurador de TEC para SIT

Fecha: 2026-10-08. El documento conserva el análisis de la referencia TEC y registra la primera entrega autorizada en SIT. No se modificó TEC.

## Primera entrega implementada

El engranaje de la topbar abre `SitConfigurator.vue`, un diálogo lateral modal con menú vertical/horizontal, texto al 100 %, 112,5 % y 125 %, y restablecimiento a vertical/100 %. El botón anterior de cambio de menú fue retirado para evitar duplicidad. El panel cierra con Escape, botón de cierre o clic exterior, conserva el foco dentro y devuelve el foco al engranaje.

`preferencias.js` valida valores, lee la preferencia heredada y guarda únicamente preferencias visuales. `useSitPreferences.js` mantiene una sola fuente reactiva del modo de menú, aplica el tamaño antes del montaje y sincroniza otras pestañas. La clave principal es `sit_preferences`, con formato versión 1; se mantiene `sit_menu_mode` por compatibilidad. Ante almacenamiento bloqueado, los pasos se acumulan en memoria.

Se reutilizó la plantilla actual sin recrear el contenido al cambiar preferencias. El restablecimiento no borra `sessionStorage`, no reinicia el login ni modifica permisos o BD. El gráfico del dashboard ajusta su fuente a la escala raíz y sus tarjetas se distribuyen según el espacio disponible para admitir texto grande. Las preferencias corresponden al navegador, no se sincronizan mediante backend.

Menú oscuro, campos con relleno, RTL y temas adicionales siguen fuera de esta primera entrega.

## Resultado

Es viable adaptar el concepto de `config.xhtml` como panel de preferencias visuales en Vue. Sus archivos XHTML/JavaScript no se pueden incorporar directamente: dependen de Jakarta Faces, PrimeFaces, jQuery, el widget Ecuador, un bean Java de sesión y las clases de ese layout. SIT debe utilizar su plantilla única, componentes actuales y tokens CSS, conservando permisos, formularios y sesión.

No hay evidencia que justifique actualizar las dependencias de SIT para este objetivo. La adaptación requiere código JavaScript propio del frontend, no instalar PrimeFaces/PrimeVue/jQuery ni cambiar versiones por anticipado.

## Qué hace TEC realmente

| Opción | Implementación observada en TEC | Equivalencia/propuesta para SIT |
| --- | --- | --- |
| Apertura del panel | Engranaje lateral fijo; `layout.js` alterna `layout-config-active`. Cierra con clic exterior. | Panel Vue controlado por `ref`, abierto desde un botón accesible en topbar. |
| Tipo de menú | Estático, superpuesto, compacto y horizontal. Cambia clases del wrapper y limpia el estado de `PF('EcuadorMenuWidget')`. | Vertical/horizontal. El control de cabecera oculta completamente el lateral; no incorpora el modo slim de TEC. En móvil ambos usan un lateral, conservando la orientación elegida para escritorio. |
| Color del menú | Claro/oscuro. Alterna `layout-menu-light`; el modo oscuro usa el estilo base del layout. | Primera entrega: claro actual. Oscuro del menú requiere tokens propios y contraste de enlaces, grupos, foco y opciones activas. No equivale a tema oscuro de toda la aplicación. |
| Estilo de inputs | Borde/relleno: agrega o elimina `ui-input-filled` en `body`. | Requiere componentes/clases compartidas de campos. No prometer un cambio global mientras los formularios tengan estilos particulares. |
| RTL | Alterna `layout-rtl`, además de actualizar el bean con AJAX. | Posponer: SIT trabaja en español y conserva reglas físicas `left/right`; exige revisar navegación, tablas y paneles. |
| Tamaño de texto | `tribunal-fontsize.js`, botones A−/A+, atributo `data-tec-fs` en HTML y `localStorage`. Sin AJAX. | Reutilizar el concepto, con nombres SIT, estado reactivo y escalas porcentuales. Propuesta inicial: 100 %, 112.5 %, 125 %, con restablecimiento. |
| Selección de tema | `GuestPreferences` admite únicamente Tribunal. Hay funciones `changeLayout/changeScheme` en JS, pero `config.xhtml` no presenta un selector de temas. | Mantener Tribunal inicial. Un selector de otros temas requiere catálogo de tokens y completar la normalización de pantallas. |
| Persistencia | Menú/color/inputs/RTL en `GuestPreferences` con `@SessionScoped`; tamaño en `localStorage`. | Preferencias visuales locales, sin API ni migraciones. Sincronización por usuario/dispositivo sería otro alcance. |

El CSS compilado de TEC presenta un panel de `12rem`, fijo a la derecha, con transición y `z-index:1001`; son decisiones de TEC, no dimensiones que SIT deba copiar. Propuesta SIT: ancho máximo de aproximadamente 20rem y límite relativo al viewport, scroll interno y capas coherentes con su menú y diálogos.

## Cómo intervienen los JavaScript

1. `config.xhtml` conecta los radios/switch con `PrimeFaces.EcuadorConfigurator` mediante `onchange`.
2. `resources/ecuador-layout/js/layout.js` cambia clases en `.layout-wrapper`, el estilo de inputs en `body`, recursos CSS y estado del widget de menú. Sus manejadores de clic abren/cierran el panel.
3. Los `p:ajax` actualizan `GuestPreferences` y, en varias opciones, vuelven a renderizar `config-form`. SIT usa Vue para actualizar sus componentes; este ciclo AJAX de Faces no corresponde a su arquitectura.
4. `resources/demo/js/tribunal-fontsize.js` aplica el tamaño guardado desde el `<head>` antes del pintado, actualiza el atributo de HTML y sincroniza otras pestañas con el evento `storage`.
5. `layout-tribunal.css` interpreta ese atributo. La clave `12` representa el tamaño predeterminado con raíz al 100 %, no significa que la raíz sea de 12px. Las demás claves definen 91.6667 %, 116.6667 % y 133.3333 %.

### Aspectos del código TEC que necesitan adaptación o revisión

- Selectores específicos de Ecuador y `PF('EcuadorMenuWidget')`: no existen en SIT. Copiarlos introduce errores o controles que no hacen nada.
- `changeMenuMode` contiene un `default` que agrega `layout-menu-static` y lo vuelve a retirar en la misma cadena. Debe evitarse ese patrón y validar los valores permitidos. No se modificó TEC.
- `changeLayout/changeScheme` buscan enlaces CSS por fragmentos de URL y reemplazan recursos. En SIT el CSS está compilado por Vite; el cambio debe aplicar tokens mediante atributos/clases, sin editar las URLs de archivos con hash ni recargar el aplicativo.
- `replaceLink` contiene compatibilidad antigua con IE/Edge y referencia `javax.faces.Resource`; `template.xhtml` y `layout.js` contienen parches de jQuery. Son elementos heredados que no hacen falta en SIT. La presencia de esa referencia no prueba un fallo actual de TEC; no se ejecutó su runtime.
- No se observa gestión de Escape, captura/restauración del foco ni semántica de diálogo para el panel en los archivos revisados. En SIT el panel debe incluirlas desde el inicio.
- El selector de fuente de TEC tiene respaldo cuando falla `localStorage`, pero `step()` vuelve a leer del almacenamiento: si las escrituras están bloqueadas, los pasos sucesivos no se acumulan. En SIT el valor vigente debe conservarse en memoria aunque falle la persistencia.
- Tras un reemplazo AJAX de `config-form`, los botones nuevos pueden necesitar que se vuelva a aplicar su estado de límite. En Vue ese estado debe ser declarativo con `:disabled`, evitando búsquedas y reinicialización manual del DOM.

## Base existente en SIT

- `MainLayout.vue` utiliza la preferencia vertical/horizontal compartida. Desde la actualización de cabecera, el lateral se oculta completamente y ambos modos usan un panel móvil por debajo de 992 px; ver [TOPBAR_SIT.md](TOPBAR_SIT.md).
- Antes de esta entrega, `SitTopbar.vue` emitía `toggle-mode`. Ahora abre el configurador mediante `open-preferences`; conserva el menú de usuario y manejo de foco.
- El contenido principal conserva su instancia al cambiar la orientación. La adaptación debe mantener ese comportamiento, sin cambiar `key` de `router-view`, recrear la aplicación ni recargar la página.
- `sit-theme.css` contiene identidad, superficies, texto, estados y capas. Dashboard, Asistencia y Avisos ya consumen tokens. Otras pantallas y componentes, incluido el chatbot global, aún requieren normalización antes de ofrecer cambios completos de tema.
- Chart.js en Dashboard lee los tokens y actualiza colores ante cambios de clase/estilo/`data-theme` en HTML. Esta entrega aplica también tamaños al canvas y recalcula sus dimensiones al cambiar la escala.
- `main.js` inicializa Vue/Pinia/router y aplica preferencias visuales antes del montaje, conservando el tamaño predeterminado y el zoom del navegador.

## Arquitectura propuesta

| Archivo | Responsabilidad propuesta |
| --- | --- |
| `frontend/src/services/preferencias.js` — nuevo | Valores permitidos, defaults, lectura segura, escritura local, migración de la clave de menú actual y aplicación inicial a HTML. |
| `frontend/src/composables/useSitPreferences.js` — nuevo | Estado reactivo compartido, actualización de preferencias y sincronización entre pestañas; una sola fuente del modo de menú. |
| `frontend/src/layouts/components/SitConfigurator.vue` — nuevo | Panel, controles nativos, límites de A−/A+, restablecimiento y accesibilidad. |
| `frontend/src/layouts/components/SitTopbar.vue` | Botón de preferencias y evento de apertura, conservando los accesos existentes. |
| `frontend/src/layouts/MainLayout.vue` | Montaje del panel, coordinación con menú móvil/usuario y reutilización de su modo de menú actual. |
| `frontend/src/styles/sit-theme.css` | Estilos del panel y escalas porcentuales con variables SIT; sin redefinir utilidades Tailwind. |
| `frontend/src/main.js` | Aplicación inicial de preferencias antes de `app.mount`. |
| `frontend/src/views/DashboardView.vue` | Ajuste futuro de tamaño y actualización de fuentes del canvas al cambiar la escala. |

Los archivos propuestos se implementaron en esta primera entrega, con formato versionado y compatibilidad de navegación. No guardar tokens de sesión, roles ni permisos junto a preferencias visuales. Restablecer preferencias no debe cerrar sesión ni borrar `sessionStorage`.

## Primera entrega recomendada

Panel «Preferencias de visualización» accesible desde topbar con:

- Menú vertical/horizontal, aprovechando el comportamiento actual.
- Tamaño de texto: normal, ampliado y grande, con botones y un valor visible.
- Restablecer preferencias visuales.
- Tema Tribunal como información; no ofrecer temas incompletos ni controles deshabilitados sin utilidad.

El panel debe cerrar con Escape y clic exterior, devolver el foco al botón de apertura, mantener el foco dentro mientras sea modal y adaptar su tamaño al móvil. Coordinar sus capas con los mensajes, diálogos de confirmación y menú; el engranaje no debe competir con el chatbot flotante.

Para el tamaño, conservar 100 % como predeterminado y expresar el incremento en porcentaje/rem, sin bloquear el zoom. La base del `body` es `.875rem` (14px con raíz de 16px); las medidas en px de otras pantallas no escalan con la raíz y deben revisarse gradualmente. No modificar globalmente el significado de `.text-sm`, `.grid` u otras utilidades.

## Riesgos y validación antes de publicarlo

- Comprobar que cambiar menú/tamaño conserva filtros, formularios sin guardar, rutas y permisos de todos los módulos.
- Probar 320/390px, texto grande y zoom del navegador; permitir scroll horizontal de tablas dentro de su contenedor, evitando overflow de la página.
- Verificar foco, teclado, lectores de pantalla, clic exterior y coordinación de paneles.
- Probar claves inválidas, almacenamiento bloqueado y dos pestañas; volver a defaults sin impedir el login.
- Mantener sin cambios la duración de sesión de 15 minutos y las restricciones de marcación.
- Verificar el gráfico, las cuatro marcaciones, bloqueo BIOMETRICO y confirmación de salida temprana con API simulada.
- Ejecutar pruebas existentes y build. No requiere cambios de base de datos ni backend en la entrega local propuesta.

## Fuentes inspeccionadas

Raíz TEC: `C:/Users/llema/Documents/workspace/workspace_tec/tec`.

- `src/main/webapp/WEB-INF/config.xhtml` y `template.xhtml`.
- `src/main/webapp/resources/ecuador-layout/js/layout.js`.
- `src/main/webapp/resources/demo/js/tribunal-fontsize.js` y `tribunal-globals.js`.
- `src/main/webapp/resources/ecuador-layout/css/layout-tribunal.css`.
- `src/main/webapp/resources/demo/css/tribunal-globals.css`.
- `src/main/webapp/resources/sass/layout/_config.scss`.
- `src/main/java/ec/com/antenasur/controller/GuestPreferences.java`.

La auditoría previa `docs/auditoria-tema-2026-10-08/AUDITORIA.md` se mantiene como fotografía de su momento; los cambios posteriores en Dashboard/Asistencia se documentan en `docs/DISENO_SIT.md`.

## Validación de la entrega

Pasaron 16 pruebas automatizadas (navegación y preferencias), el build de Vite y la comprobación de diferencias de Git. Las pruebas en Chrome, con API simulada, verificaron límites y restablecimiento, cierre con Escape/clic exterior, foco, filtros y `sessionStorage` conservados, persistencia tras recarga, sincronización entre pestañas del mismo origen y fuente del gráfico sin repetir solicitudes API.

Se revisaron Dashboard y Asistencia a 1440/1024/390/320px según la pantalla, con texto al 125 %, y el panel en escritorio/móvil. Las cuatro marcaciones, cancelación/confirmación de salida temprana y bloqueo BIOMETRICO se verificaron con datos simulados. El caso de acceso a `localStorage` bloqueado se probó sobre los archivos compilados, independientes del cliente de desarrollo de Vite. No hubo errores de JavaScript ni escrituras reales en PostgreSQL.
