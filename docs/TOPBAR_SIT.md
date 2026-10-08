# Cabecera y navegación responsive de SIT

Actualización: 2026-10-08. Referencia visual: TEC, sin trasladar Jakarta Faces, PrimeFaces ni jQuery a SIT.

## Referencias revisadas

En `C:/Users/llema/Documents/workspace/workspace_tec/tec/src/main/webapp`:

- `WEB-INF/toopbar.xhtml`: control izquierdo, logo central y cuenta a la derecha.
- `WEB-INF/menu.xhtml`: estructura del menú y opciones provenientes de su modelo.
- `resources/ecuador-layout/css/layout-tribunal.css`: distribución, tamaños, estados y breakpoint.
- `resources/demo/css/tribunal-globals.css`: sobrescribe la presentación del nombre/roles y el icono del usuario.
- `resources/ecuador-layout/js/layout.js`: alterna estados y cierra desplegables al hacer clic fuera.

La distribución efectiva de TEC depende de estos CSS y JS, no únicamente del XHTML. Su cabecera mide 60 px. El control del menú ocupa 60 × 60 px y su icono mide aproximadamente 23 px. El logo está centrado; el usuario ocupa la derecha, con nombre, roles, icono de usuario y flecha. Los estilos globales conservan el nombre, con truncamiento; a 640 px o menos limitan el nombre a 6 rem y ocultan los roles. El desplegable de usuario contiene Cerrar sesión.

## Comportamiento real de TEC

| Aspecto | Escritorio, desde 992 px | Pantallas menores de 992 px |
| --- | --- | --- |
| Menú estático | Lateral de 224 px; el botón lo oculta completamente y el contenido recupera ese espacio. La flecha cambia de orientación. | Se oculta inicialmente. El botón activa el menú móvil. |
| Menú superpuesto | El lateral se abre sobre el contenido; clic exterior lo cierra. | Usa la presentación móvil común. |
| Menú compacto, slim | Franja de 60 px y submenús; sin el control de ocultamiento del modo estático. | Usa la presentación móvil común. |
| Menú horizontal | Barra bajo la cabecera, categorías y desplegables; sin botón lateral. Clic exterior cierra submenús. | Usa la presentación móvil común. |
| Menú móvil | No aplica. | El CSS de TEC muestra navegación de ancho completo bajo la cabecera. La flecha vertical indica el estado. Abrir la cuenta cierra el menú móvil. |

## Adaptación implementada en SIT

| Elemento | SIT anterior | Referencia y adaptación |
| --- | --- | --- |
| Cabecera | Flex, logo después del botón, altura 64 px. | Grid con tres zonas: navegación, identidad centrada, preferencias/cuenta. Altura 3,75 rem: 60 px con texto normal y 75 px al 125 %. |
| Control del menú | Hamburguesa fija. | Flechas izquierda/derecha en escritorio y abajo/arriba en móvil; etiquetas Mostrar/Ocultar menú y estado accesible. |
| Ocultar lateral | Franja de 4 rem con iniciales de opciones. | Ocultamiento completo, sin controles invisibles enfocables; mantiene las categorías abiertas para restaurarlas. |
| Tablet y móvil | Breakpoint de 768 px; horizontal seguía mostrando categorías en una barra. | Breakpoint común CSS/JS de 992 px. Ambos modos usan un panel lateral superpuesto, con fondo de cierre y ancho limitado para dejar visible una zona exterior. |
| Identidad | Logo y SIT. | Se conservan los recursos de SIT. Centrado sin posiciones absolutas ni solapamientos. Se oculta el subtítulo en tablet/móvil. |
| Cuenta | Nombre, etiqueta Mi cuenta, foto/iniciales. | Nombre y roles reales del store en escritorio, nombre en tablet; a 640 px o menos queda el avatar. Nombre y roles completos permanecen en el desplegable. Se conservan Perfil y Cerrar sesión. |
| Preferencias | Engranaje y configurador existente. | Se conserva. La orientación se elige exclusivamente en el configurador; el control izquierdo solo muestra/oculta navegación. |
| Interacción | Escape, foco y clic exterior existentes. | Se conservan y coordinan cuenta, menú móvil y preferencias. En móvil el panel retiene el foco, el contenido queda inert y Escape devuelve el foco al control. |
| Estilo | Tokens Tribunal, Montserrat, SVG locales. | Se reutilizan. Iconos coherentes y controles táctiles de al menos 44 px. Nombres largos se truncan sin encoger arbitrariamente la letra. |

La elección horizontal permanece guardada mientras se utiliza el móvil y reaparece al volver a escritorio. Mostrar/ocultar el lateral es un estado temporal: no cambia la preferencia de orientación. Los modos superpuesto y slim de escritorio de TEC no se incorporan al configurador de SIT en esta etapa.

La barra mantiene su fila incluso con texto al 125 %. El desplegable de cuenta tiene scroll si el nombre/roles exceden la altura disponible. El lateral móvil permite scroll independiente. El contenido conserva su instancia al cambiar la presentación; no se reinician formularios ni se repiten consultas por cambiar el menú.

## Archivos y alcance

- `frontend/src/layouts/MainLayout.vue`: presentación móvil independiente de la preferencia guardada y coordinación de aperturas.
- `frontend/src/layouts/components/SitTopbar.vue`: distribución, iconos por estado e identidad de cuenta.
- `frontend/src/layouts/components/SitMenu.vue`: ocultamiento completo y conservación de navegación existente.
- `frontend/src/styles/sit-theme.css`: dimensiones, grid, truncamiento, capas y media queries.

No hay cambios de autenticación, roles/permisos, llamadas de marcación, backend ni base de datos. La principal diferencia visible es la desaparición de la franja compacta y el uso del lateral en tablet aunque se haya elegido horizontal. No se copian colores por rol de TEC, banderas, utilidades PrimeFlex ni widgets PrimeFaces.

## Validación

Compilación del frontend y pruebas existentes de navegación/preferencias. Verificación en Chrome con API y sesión simuladas: 320, 390, 640, 768, 991, 992, 1024 y 1440 px, texto 100 %/125 %, ambos modos, nombre y roles extensos, cierre con Escape/exterior, foco del menú/cuenta, preferencias, conservación del formulario y ausencia de consultas adicionales al cambiar presentación. Perfil y cierre de sesión se comprueban contra la simulación; no se realizan marcaciones ni autenticaciones reales.
