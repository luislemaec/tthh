# Diseño de SIT: modernización por etapas

## Alcance de la primera etapa

Se adapta la referencia visual Tribunal de TEC a Vue y Tailwind, manteniendo la identidad institucional y la navegación autorizada por el backend. La primera etapa comprende plantilla, cabecera, menú, migas de pan, pie, login y tokens de diseño. Las vistas de los módulos conservan sus estilos específicos; su normalización corresponde a las etapas posteriores.

No se incorporan PrimeFaces, PrimeVue, PrimeFlex, scripts JSF ni los CSS completos de TEC. PrimeFaces pertenece a Java/Jakarta Faces; sus widgets no son componentes Vue y las utilidades de PrimeFlex pueden entrar en conflicto con Tailwind.

## Archivos y responsabilidades

| Archivo | Responsabilidad |
| --- | --- |
| `frontend/src/style.css` | Entrada del CSS activo: Tailwind y tema SIT. |
| `frontend/src/styles/sit-theme.css` | Fuentes, tokens y estilos de la plantilla; las clases propias llevan prefijo `sit-`. |
| `frontend/src/assets/fonts/` | Montserrat WOFF2 local: regular, medium y bold, tomada de los recursos de TEC. |
| `frontend/public/licenses/Montserrat-OFL.txt` | Licencia de Montserrat incluida en el build para su distribución. |
| `frontend/src/layouts/MainLayout.vue` | Plantilla única, orientación del menú, contenido estable y adaptación al viewport. |
| `frontend/src/layouts/components/SitTopbar.vue` | Identidad centrada, visibilidad del menú, acceso al configurador y cuenta con Perfil/Cerrar sesión. |
| `frontend/src/layouts/components/SitMenu.vue` | Opciones autorizadas, categorías, panel móvil y desplegables. |
| `frontend/src/layouts/components/SitBreadcrumb.vue` | Contexto de navegación accesible. |
| `frontend/src/layouts/components/SitFooter.vue` | Identificación del sistema y de la institución. |
| `frontend/src/services/navegacion.js` | Reglas existentes de acceso, agrupación y contexto de las rutas. |
| `frontend/src/services/ambiente.js` | Indicador de ambiente compartido entre login y plantilla. |
| `frontend/src/services/preferencias.js` | Validación, persistencia local y compatibilidad de preferencias visuales. |
| `frontend/src/composables/useSitPreferences.js` | Estado reactivo compartido y sincronización entre pestañas. |
| `frontend/src/layouts/components/SitConfigurator.vue` | Panel modal de menú, tamaño de texto y restablecimiento. |

`frontend/src/assets/base.css` y `main.css` son archivos heredados que no forman parte del CSS activo. No importarlos como parte de un cambio de pantalla sin revisar sus reglas globales.

## Tema y reglas de reutilización

- Usar `--sit-primary`, `--sit-primary-hover`, `--sit-primary-active` y `--sit-primary-focus` para la identidad azul y sus estados.
- Usar `--sit-surface`, `--sit-ground`, `--sit-border`, `--sit-text`, `--sit-text-muted` y `--sit-text-strong` para superficies y contenido.
- Los estados tienen tokens `--sit-success`, `--sit-info`, `--sit-warn`, `--sit-danger` y `--sit-neutral`, con sus variantes `-soft`. En avisos amarillos utilizar texto oscuro; blanco sobre amarillo no es legible.
- Reutilizar `--sit-radius`, las sombras y las dimensiones de la plantilla. Añadir nuevos valores solo cuando exista un uso compartido concreto.
- Mantener el tamaño raíz del navegador como base y aplicar únicamente la escala visual elegida. Montserrat se carga localmente con `font-display: swap`; con raíz de 16px el texto base es de 14px y los campos del login de 16px. Las utilidades de tamaño de Tailwind conservan su significado.
- No redefinir globalmente utilidades como `.grid`, `.flex`, `.text-sm`, ni estilos de todos los botones, tablas o inputs. Adaptar cada componente cuando corresponda a su etapa.
- Reutilizar `ContenidoSistema`, `AvisosInicio`, `ChatbotFAB`, `TimePicker24` y `TipTapEditor`; revisar sus contratos antes de crear componentes equivalentes.
- Mantener visibles los estados de foco y permitir navegación por teclado. El menú móvil conserva el foco y cierra con Escape; los desplegables admiten flecha abajo y Escape.

## Validación y entrega

Desde `frontend`, ejecutar `npm.cmd test` y `npm.cmd run build`. Con Docker, desde la raíz, ejecutar `docker compose exec frontend npm test` y `docker compose exec frontend npm run build`. El servidor Vite de desarrollo recarga los cambios; en producción el responsable publica el build mediante el procedimiento existente.

Para esta primera etapa se comprobaron en navegador login/logout, los cinco módulos, usuarios con cero o una opción, permisos, mantenimiento, cambio de orientación sin reiniciar filtros, sesión vencida, menú móvil, anchura de 320 px y texto ampliado. Las marcaciones se verificaron con API simulada: cuatro conceptos, bloqueo biométrico y confirmación/cancelación de salida temprana. Estas pruebas no autentican contra AD ni escriben registros reales en PostgreSQL.

Antes de publicar, comprobar con cuentas reales los roles representativos, la carga de datos y las marcaciones en el ambiente de pruebas. No requiere migraciones ni modificaciones al backend.

La segunda etapa recomendada es normalizar formularios, tablas, botones y diálogos de un módulo piloto, y extender los componentes reutilizados después de validar ese módulo.

## Normalización de Dashboard y Asistencia — 2026-10-08

`DashboardView.vue`, `AsistenciaView.vue` y los avisos de `AvisosInicio.vue` usan las utilidades semánticas de `sit-theme.css`: `bg-sit-surface`, `text-sit-strong`, `text-sit-muted`, `border-sit-border`, `bg-sit-primary` y los estados `sit-success`, `sit-info`, `sit-warn` y `sit-danger`, con sus fondos `-soft`. Para amarillo utilizar `text-sit-warn-text`. Las sombras utilizan `shadow-sit-card` y `shadow-sit-float`; el fondo de diálogos utiliza `bg-sit-overlay`.

No añadir valores HEX ni clases de paletas físicas (`green`, `teal`, `gray`, etc.) a estas vistas. El texto sobre fondos sólidos usa `text-sit-on-color`; las superficies tienen un token independiente. Las utilidades Tailwind de distribución y tamaños permanecen disponibles.

## Preferencias de visualización — 2026-10-08

El engranaje de topbar abre un diálogo lateral con menú vertical/horizontal y tamaños 100 %, 112,5 % y 125 %. El botón anterior de cambio de menú se retiró. El restablecimiento vuelve a vertical/100 % y conserva sesión, filtros y permisos. Los controles se actualizan en Vue y utilizan exclusivamente tokens SIT; no utilizan AJAX de Faces, jQuery ni recursos de TEC.

Las preferencias se aplican antes del montaje, se guardan en `sit_preferences` con versión 1 y se sincronizan mediante el evento `storage`. La lectura conserva `sit_menu_mode`/`th_menu_mode`; la escritura mantiene también `sit_menu_mode`. Valores inválidos vuelven a defaults, y el bloqueo de almacenamiento no impide aplicar preferencias en memoria. La escala global utiliza `--sit-font-scale`; Dashboard ajusta también las fuentes de Chart.js.

El diálogo nativo permite Escape, botón de cierre y clic exterior. El foco permanece dentro y vuelve al engranaje. Menú móvil, desplegable horizontal y menú de usuario se coordinan al abrirlo. Consultar [CONFIGURADOR_SIT.md](CONFIGURADOR_SIT.md) para el análisis, responsabilidades y opciones de etapas posteriores.

La cabecera adapta la distribución de TEC: control izquierdo, identidad centrada y cuenta a la derecha. Desde 992 px el menú vertical se puede ocultar por completo; por debajo, ambos modos utilizan un lateral que inicia cerrado. La elección horizontal se conserva para escritorio. Nombre/roles extensos se truncan; en móvil permanecen completos en el menú de cuenta. La altura y tipografía respetan la escala elegida. Consultar [TOPBAR_SIT.md](TOPBAR_SIT.md) para el análisis de los estados de TEC y la adaptación.

`App.vue` monta una sola capa global de actividad, avisos, confirmaciones y advertencia de sesión. `SitScrollTop.vue` actúa sobre el área de contenido del layout. Todos consumen los tokens existentes; la primera adopción de mensajes/confirmaciones corresponde a Perfil y Parámetros. Ver [GLOBALS_SIT.md](GLOBALS_SIT.md) para el contrato asíncrono, recursos revisados de TEC y alcance.

Chart.js lee los tokens CSS calculados para barras, ejes, cuadrícula, fuente y tooltip. Conserva los umbrales: cero = éxito, uno o dos = advertencia, más de dos = peligro. Al cambiar `class`, `style` o `data-theme` en `<html>`, actualiza el gráfico; al salir de la vista libera el gráfico y su observador. Este mecanismo no incorpora un selector de temas.

Las marcaciones conservan su disponibilidad y validaciones: siguiente concepto = acción primaria, ya registrado = neutral, concepto futuro = fondo primario suave. Las pruebas de navegador con API simulada cubrieron los tres perfiles del dashboard, cambios de tokens en HTML y canvas, pantallas de 1440/390 px, los cuatro conceptos, error de marcación, cancelación/confirmación de salida temprana y bloqueo BIOMETRICO. No se escribieron marcaciones reales ni se modificó el backend.
