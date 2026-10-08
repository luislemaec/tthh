# Auditoría integral de temas de SIT

Fecha: 8 de octubre de 2026. Alcance: diagnóstico y propuesta; no se modifica la aplicación, su apariencia, los permisos, las rutas ni las marcaciones. Se generan únicamente estos documentos e inventarios.

## Diagnóstico

**SIT todavía no está preparado para cambiar el tema de toda su interfaz modificando solamente sus tokens.** La plantilla compartida ofrece una base adecuada, pero las vistas, los controles reutilizables anteriores y los colores asignados desde JavaScript conservan dependencias visuales separadas. Una modificación de `--sit-primary` cambia principalmente la plantilla/login; no cambia automáticamente los botones verdes, los encabezados por módulo, los checkbox, los tabs ni los estados de las tablas.

El usuario confirmó que **los colores de Adquisiciones, Transporte, Tecnología y Comisiones podrán adaptarse al tema elegido**. La normalización inicial debe conservar la apariencia actual; la unificación de esos colores pertenece a un tema posterior, después de migrar sus consumidores.

## A. Arquitectura y alcance medido

El flujo activo es `frontend/index.html` → `frontend/src/main.js` → `frontend/src/style.css` → Tailwind + `frontend/src/styles/sit-theme.css`. Vue Router carga un `MainLayout` compartido y las vistas; los componentes propios conviven con utilidades Tailwind, estilos scoped, atributos `style` y funciones que devuelven clases o colores. TipTap y Chart.js añaden contenido editable y canvas. No existen PrimeFaces, PrimeVue, PrimeFlex, DataTables de jQuery ni páginas XHTML en SIT: sus tablas son HTML/Vue.

Se recorrieron 330 archivos de código/recurso de `frontend/src`, `frontend/public`, `backend/resources`, `backend/app` y `backend/routes`, más la entrada HTML. 155 contienen presentación o indicios visuales. El grafo de importaciones detecta 104 archivos activos del frontend, 96 de ellos visuales: 93 Vue, 82 vistas, CSS/entrada HTML. No se contaron `node_modules`, `vendor`, `dist`, `.git`, fuentes binarias, `.env`, SQL ni archivos generados. Los PNG/fotografías se reconocen como recursos independientes; sus píxeles no se convierten en tokens.

| Grupo | Archivos | HEX | RGB/RGBA | HSL | OKLCH | Clases Tailwind físicas | Tailwind arbitrario de color | Inline |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Frontend activo | 104 | 1.071 | 10 | 0 | 0 | 7.030 | 606 | 401 |
| Frontend sin importación detectada | 12 | 17 | 6 | 2 | 0 | 69 | 1 | 9 |
| Documentos/PDF | 43 | 732 | 1 | 0 | 0 | 0 | 0 | 1.087 |
| Página inicial Laravel | 1 | 246 | 6 | 0 | 242 | 16 | 53 | 7 |
| Recurso SVG | 1 | 2 | 0 | 0 | 0 | 0 | 0 | 0 |
| Otros recursos/código backend | 169 | 1 | 0 | 0 | 0 | 0 | 0 | 0 |

El frontend activo incluye 118 coincidencias de nombres de color/atributos: 112 son herencia (`currentColor`/`inherit`), tres son transparencia y tres son colores literales. La herencia y la transparencia no deben convertirse automáticamente. Hay 91 etiquetas `<table>` en 65 archivos y 145 patrones de overlay `fixed inset-0`; son apariciones de código, no 145 componentes compartidos ni 145 diálogos abiertos. Los formularios suelen implementarse como grupos de inputs: contar únicamente `<form>` subestimaría su alcance.

**Cobertura aproximada de dependencia semántica directa:** 105 referencias `var(...)` frente a 8.089 apariciones visuales dependientes de valores/clases físicos, excluyendo definiciones centrales, herencia, transparencia y el doble conteo de HEX dentro de clases arbitrarias. Resultado: **1,28 % directo / 98,72 % pendiente de clasificación o normalización**. Es una métrica estática de consumidores en código, no un porcentaje de píxeles o de pantallas: la plantilla y la fuente compartidas afectan a todas las rutas. No se debe interpretar como que solo el 1,28 % de la UI funciona o hereda estilos.

Los inventarios agrupan cifras por archivo y conservan ubicación, valor, clasificación, token candidato, impacto y acción. La clasificación automática es una ayuda de revisión; no autoriza reemplazos automáticos ni resuelve por sí sola el significado de un estado.

## B. Tailwind real y configuración

Versiones exactas de `frontend/package-lock.json`: Tailwind **4.2.1**, `@tailwindcss/vite` **4.2.1**, Vite **7.3.1**, Vue **3.5.30**, Router **5.0.3**, Pinia **3.0.4**, TipTap Vue **3.27.3**, Chart.js **4.5.1**. Los rangos de `package.json` no sustituyen esta comprobación del lockfile.

- Instalación mediante npm; integración en Vite con `tailwindcss()` y `vue()`.
- Entrada: `@import "tailwindcss"` y después `sit-theme.css`. Compilación: `npm run build` → `frontend/dist`.
- No hay `tailwind.config.js/ts` ni configuración PostCSS propia. Tampoco `theme.extend`, plugins de formularios o librería de componentes UI adicional para botones/tablas.
- Ya existe `@theme inline`: fuente y seis alias `--color-sit-*`. La integración correcta es ampliarla con las utilidades semánticas que se migren; no introducir una configuración de Tailwind 3.
- No hay selector de tema, `data-theme`, variantes `dark:` ni `color-scheme` en el flujo activo. `assets/base.css` contiene un modo oscuro del starter Vue, pero no está importado.
- Los colores Tailwind 4 ya son primitivas CSS globales `--color-*`, en muchos casos **OKLCH**, no los HEX de Tailwind 3. Esto permite cambiar una primitiva, pero no establece el significado funcional de todas sus apariciones. `bg-green-100` no identifica por sí mismo si representa aprobado, selección, información o decoración.

Los ejemplos de integración se sustentan en el código instalado y en la [documentación oficial de tokens de Tailwind](https://tailwindcss.com/docs/theme). No se propone actualizar dependencias.

## C. Paleta real y duplicados

No se incorporaron colores de los ejemplos del requerimiento ni de TEC. Los valores siguientes proceden del **SIT actual** o de sus primitivas Tailwind instaladas. El archivo `tailwind-primitivas.json` registra las expresiones OKLCH reales y una aproximación sRGB para inspección; para preservar la apariencia se deben conservar las expresiones originales.

| Valor/clase | Usos activos | Archivos | Significado observado | Token recomendado |
| --- | ---: | ---: | --- | --- |
| `#034ea2` / `#03458f` / `#023b7b` | Definidos en tema | 1 | Identidad de plantilla, login, hover/active | Reutilizar `--sit-primary`, `-hover`, `-active` |
| `#0b5447` | 248 | 47 | Acciones/énfasis heredados, principalmente TH | Rol de acción primaria del tema inicial, no success automático |
| `#0b5547` | 9 | Revisar inventario | Variante de verde en chatbot/Asistencia | Conservar variante en migración; decidir posteriormente unificación |
| `#00372e` | 77 | Revisar inventario | Hover de acción verde | Rol de hover primario heredado |
| `#579186` | 212 | 36 | Foco/ring y otros detalles | Rol de foco del tema inicial |
| `#4a5e3a` | 104 | 15 | Adquisiciones | Rol de acción/encabezado ADQ del tema inicial |
| `#1e3a5f` | 64 | Revisar inventario | Transporte y algunas cards | Distinguir identidad TRANS de una serie/KPI |
| `#4d7c8a` | 62 | Revisar inventario | Tecnología | Rol de acción/encabezado TEC del tema inicial |
| `#5c4a6e` | 106 | 5 | Comisiones, tabs y checkbox | Rol de acción/selección COM del tema inicial |
| `text-gray-600` | 969 | 82 | Texto de contenido | `--sit-content-text`, inicialmente `var(--color-gray-600)` |
| `text-gray-500` | 560 | 78 | Texto secundario | `--sit-content-muted`, inicialmente `var(--color-gray-500)` |
| `text-gray-700` / `text-gray-800` | 221 / 197 | 67 / 82 | Texto fuerte/títulos | Roles de texto fuerte y título; no fusionar tonos durante migración visual |
| `text-gray-400` | 357 | 80 | Empty states, metadatos, elementos deshabilitados | Clasificar; hay riesgo de contraste en texto informativo activo |
| `bg-white` | 439 | 86 | Cards, modales, inputs, filas | `--sit-surface`, `--sit-card`, `--sit-field-background` según contexto |
| `hover:bg-gray-50` | 197 | 59 | Hover de filas/acciones | `--sit-hover` / rol específico de fila |
| `border-gray-300` | 140 | 34 | Bordes de campos/tablas | `--sit-field-border` / borde de componente |
| `text-white` | 620 | 81 | Texto sobre distintos fondos | Foreground del rol correspondiente; no asumir blanco en todo tema |
| `text-red-600` / `text-red-700` | 182 / 106 | 71 / 49 | Errores, eliminar, saldos/estados | Danger: diferenciar sólido, texto y fondo suave |
| `text-green-700` / `bg-green-100` | 150 / 92 | 63 / 62 | Estados correctos/aprobados y algunos otros usos | Success cuando lo confirme el contexto |
| `bg-black/50` | 78 | 36 | Fondos de modal | Rol de máscara; separado de superficie del diálogo |

Los duplicados se repiten entre `style`, clases arbitrarias, funciones JS y templates. `#fff` y `#ffffff` son equivalentes; `#0b5447` y `#0b5547` son parecidos, pero **no idénticos**. `#034ea2` y los azules Tailwind tampoco son equivalentes. Un buscador/reemplazador global cambiaría la apariencia o mezclaría acciones, estados y gráficos.

No existe un único secundario/accent global funcional para toda la UI. Hay un neutral de plantilla `--sit-neutral`, identidades por módulo, colores de severidad y colores de categorías/series. No se deben reunir en un solo `--sit-secondary` sin revisar sus funciones.

## D. Matriz de hallazgos y acciones

La matriz exhaustiva, con cada aparición, está en `inventario.csv`; las cifras agrupadas están en `archivos.csv` y `colores.csv`. Esta selección muestra los casos que determinan la arquitectura:

| Archivo / componente | Valor actual | Tipo | Variable/estrategia propuesta | Impacto | Acción |
| --- | --- | --- | --- | --- | --- |
| `frontend/src/styles/sit-theme.css`: `:root`, plantilla | `--sit-primary`, superficies, semántica | Tokenizado | Reutilizar nombres existentes | Todas las rutas | Completar consumidores; no crear un segundo registro de identidad |
| Mismo archivo: `--sit-surface` | `var(--sit-on-color)` | Variable con acoplamiento | Superficie independiente del foreground sobre colores sólidos | Futuro tema claro/oscuro | Separar responsabilidades manteniendo blanco inicial |
| Mismo archivo: hover de iconos/topbar | `rgb(255 255 255 / .12)` y bordes similares | Hardcoded CSS | Tokens de contraste sobre cabecera y transparencias | Cabecera | Normalizar; conservar valores iniciales |
| `frontend/src/App.vue`: cinta | Variables + sombra/radio literales | Mixto | Sombra/radio compartidos si tienen consumidores equivalentes | Login y aviso de ambiente | No convertir toda dimensión aislada en token |
| `frontend/src/views/LoginView.vue` | Fondo/botón tokenizados; `bg-white`, grises | Mixto | Card, texto, campo, focus, disabled | Login | Completar superficies; mantener autenticación intacta |
| `frontend/src/components/ContenidoSistema.vue` | Acción/aviso tokenizados, `bg-white`, grises | Mixto | Card y contenido semántico | Mantenimiento | Normalizar componente compartido primero |
| `frontend/src/components/TimePicker24.vue` | `bg-white`, `focus:ring-[#579186]` | Hardcoded Tailwind | Campo, borde, texto y foco | Pantallas que lo reutilizan | Migrar estilos; conservar modelo/eventos/step |
| `frontend/src/components/TipTapEditor.vue` | Grises, ring verde, selección | Hardcoded Tailwind | Superficie, borde, selección de toolbar, foco | Editor de formularios | Preservar contenido, comandos y altura dinámica |
| `frontend/src/components/ChatbotFAB.vue` | Verdes y `element.style.background/color` | Hardcoded CSS/JS | Roles de acción, superficies, burbujas y estados CSS | Componente global | Sustituir handlers visuales por estados CSS; conservar eventos funcionales |
| `frontend/src/components/AvisosInicio.vue` | Clases por estado, superficies y overlay | Hardcoded Tailwind | Roles de aviso, máscara y card | Dashboard | Normalizar semántica; no modificar reglas de avisos/pendientes |
| `frontend/src/views/AsistenciaView.vue` | `btnStyle`: verde, turquesa, apagado | CSS desde JS | Marcación disponible / pendiente / registrada | Marcaciones | Conservar secuencia, bloqueo y confirmación; no confundir pendiente con success |
| `frontend/src/views/DashboardView.vue`: Chart.js | Verde/amarillo/rojo por número de atrasos | Requiere análisis | Series/severidades de datos y texto/grillas separados | Gráficos | El cambio de CSS no repinta canvas; conservar umbrales y categorías |
| `frontend/src/views/empleados/ReporteEmpleadosView.vue` | Series por sexo, contrato, antigüedad | Requiere análisis | Paleta de datos estable y colores de ejes/leyenda tematizables | Gráficos | Revisar cada serie; no convertir categorías en primary |
| `frontend/src/views/adquisiciones/ReporteLibroComprasView.vue` | `COLORES[i]`, ancho proporcional | Requiere análisis | Paleta de datos; ancho sigue dinámico | Barras analíticas | Mantener correspondencia categoría/color y valores porcentuales |
| `frontend/src/views/admin/calendario/CalendarioView.vue` | `tipo.color` / `colorTipo` | Color de datos | Conservar color de categoría; adaptar contorno/texto si corresponde | Calendario | No sustituir por tema automáticamente |
| `frontend/src/views/admin/turnos/TurnosView.vue` | `t.color` y fallback azul | Color de datos / fallback fijo | Conservar dato; fallback explícito centralizado | Horarios | Revisar contrato con `Admin/TurnoController.php` |
| Vistas TEC/TRANS con `style="--tw-ring-color:..."` | `#4d7c8a55`, `#1e3a5f` | Acoplamiento a internals de Tailwind | Utilidad semántica de ring | Filtros | Evitar usar `--tw-*` como API propia |
| `backend/resources/views/reportes/*.blade.php` | 732 HEX, impresión/documento | Excepción justificada | Estilos de impresión separados | 43 documentos | No hacerlos depender del modo oscuro del navegador |
| Logos, fotos, SVG/iconos decorativos | Imagen / `currentColor` | Excepción o herencia | Conservar recurso; usar contraste del padre para iconos SVG genéricos | Identidad y contenido | No recolorear fotografías/logos mediante reemplazo masivo |

## E. Variables existentes y fuente de verdad

Hay **36 nombres `--sit-*`**, seis aliases `--color-sit-*` y `--font-sans` en `sit-theme.css`. Además, cuatro declaraciones locales de `--tw-ring-color` comparten un mismo nombre interno. Total: 48 declaraciones activas y 44 nombres únicos; las redeclaraciones de `--sit-menu-top`/ring no son variables globales nuevas.

El inventario completo con valor, archivo, línea y referencias está en `variables.csv` y en el anexo. Las 25 referencias de `base.css`/starter Vue no participan en el tema activo. Las variables predefinidas de Tailwind están en su dependencia y no son un registro semántico propio de SIT.

Actualmente tienen cero referencias directas, entre otros, `--sit-primary-active`, `--sit-neutral`, `--sit-success`, `--sit-info`, `--sit-success-soft`, `--sit-info-soft`, `--sit-neutral-soft` y `--sit-shadow-card`. Sus equivalentes funcionales sí existen en vistas, pero consumen otras clases/valores. Los seis aliases de color del `@theme` están definidos, sin usos detectados de sus utilidades de color en las vistas. Definir tokens no equivale a haber migrado sus consumidores.

No hay tokens compartidos consumidos para readonly, disabled, tablas, campos, paginación, tabs o todos los overlays. `disabled:opacity-50` es frecuente y mezcla el color original con el fondo: no representa una pareja semántica de colores verificable para todos los temas.

## F. Tokens recomendados (propuesta, no aplicados)

Se recomienda mantener el prefijo `--sit-` existente y ampliar solo roles que tienen consumidores reales. No introducir `--primary-color`/`--surface-card` como un registro paralelo ni copiar familias de PrimeOne. La siguiente propuesta es el núcleo; las variantes heredadas requieren un mapa central durante la migración, descrito después. Los valores provienen del SIT actual.

```css
:root {
  /* Core */
  color-scheme: light; /* futuro: parte de cada definición de tema */
  --sit-radius: .5rem;
  --sit-shadow-card: 0 1px 4px rgb(0 0 0 / .08);
  --sit-shadow-float: 0 4px 16px rgb(0 0 0 / .15);

  /* Typography: plantilla y contenido legado difieren hoy */
  --sit-font-ui: 'Montserrat', system-ui, 'Segoe UI', sans-serif;
  --sit-text: #495057;
  --sit-text-muted: #5f6870;
  --sit-text-strong: #212529;
  --sit-content-text: var(--color-gray-600);
  --sit-content-muted: var(--color-gray-500);
  --sit-content-title: var(--color-gray-800);

  /* Surfaces: sin depender del texto de botones */
  --sit-ground: #f8f9fa;
  --sit-surface: var(--color-white);
  --sit-card: var(--sit-surface);
  --sit-dialog: var(--sit-surface);
  --sit-border: #dee2e6;
  --sit-hover: var(--color-gray-50);
  --sit-overlay: rgb(15 23 42 / .45); /* máscara de menú actual */
  --sit-modal-mask: rgb(0 0 0 / .5); /* overlay modal mayoritario */

  /* Primary: identidad de la plantilla y roles de acciones */
  --sit-primary: #034ea2;
  --sit-primary-hover: #03458f;
  --sit-primary-active: #023b7b;
  --sit-primary-focus: #a9c3df;
  --sit-primary-soft: #f0f4f9;
  --sit-on-color: var(--color-white);
  --sit-primary-foreground: var(--sit-on-color);
  --sit-action-primary: var(--sit-primary);
  --sit-action-hover: var(--sit-primary-hover);
  --sit-action-foreground: var(--sit-primary-foreground);
  --sit-focus-outline: var(--sit-primary); /* mejora propuesta: contraste del foco */

  /* Semantic states: sólido, superficie suave, texto y texto sobre sólido */
  --sit-neutral: #597481;
  --sit-neutral-soft: var(--sit-ground);
  --sit-success: #517c2c;
  --sit-success-soft: #f6fbf6;
  --sit-success-text: var(--sit-success);
  --sit-success-foreground: var(--sit-on-color);
  --sit-info: #0276b6;
  --sit-info-soft: #f4fafe;
  --sit-info-text: var(--sit-info);
  --sit-info-foreground: var(--sit-on-color);
  --sit-warn: #fbc02d;
  --sit-warn-soft: #fffcf5;
  --sit-warn-text: #8d6c19;
  --sit-warn-foreground: var(--sit-text-strong);
  --sit-danger: #d32f2f;
  --sit-danger-soft: #fef4f4;
  --sit-danger-text: var(--sit-danger);
  --sit-danger-foreground: var(--sit-on-color);

  /* Components/Layout: consumidores compartidos reales */
  --sit-topbar-end: #0b2e6b;
  --sit-topbar-hover: rgb(255 255 255 / .12);
  --sit-topbar-divider: rgb(255 255 255 / .3);
  --sit-avatar-border: rgb(255 255 255 / .4);
  --sit-avatar-background: rgb(255 255 255 / .1);
  --sit-field-background: var(--sit-surface);
  --sit-field-border: var(--color-gray-300);
  --sit-field-text: var(--color-gray-700);
  --sit-field-readonly: var(--color-gray-100);
  --sit-field-disabled-text: var(--color-gray-400);
  --sit-table-heading: var(--color-gray-50);
  --sit-table-hover: var(--sit-hover);
  --sit-selected: var(--sit-primary-soft);
  --sit-header-height: 4rem;
  --sit-menu-top: var(--sit-header-height);
  --sit-sidebar-width: 16rem;
  --sit-sidebar-collapsed: 4rem;
  --sit-z-topbar: 30;
  --sit-z-overlay: 40;
  --sit-z-menu: 45;
}
```

El núcleo reutiliza valores actuales, pero **aplicarlo por sustitución masiva cambiaría vistas**: `text-gray-600` no es `--sit-text`; `bg-green-100` no es `--sit-success-soft`; los overlays tienen varias opacidades. Durante la migración deben conservarse variantes de componentes necesarias, por ejemplo tags heredados con fondo `var(--color-green-100)` y texto `var(--color-green-700)`, separados de las severidades del shell. Lo mismo para los dos estados danger/rojo y las distintas opacidades de máscara. Crear una variante solo si existe ese consumidor; eliminar aliases transitorios cuando se complete una unificación visual aprobada.

Para conservar los primarios por módulo del tema inicial, centralizar en el archivo de **valores del tema** un pequeño mapa de acciones TH/ADQ/TRANS/TEC/COM, con los colores encontrados y sus hover/focus efectivos. El contenedor de contenido puede resolver los roles de acción a partir del `meta.modulo` existente, sin cambiar rutas ni permisos. Los componentes usarán `bg-sit-action`, no clases `bg-th` o `bg-green-700`.

El usuario permite que los módulos se adapten: un tema uniforme futuro resolverá esos roles a `var(--sit-primary)` y `var(--sit-primary-hover)`. Así el primario controlará la identidad común. En el tema inicial mixto, cambiar únicamente `--sit-primary` no debe borrar silenciosamente los colores heredados; para cambiar toda la UI se aplicará la definición completa del tema. Esta transición necesita **una migración inicial de consumidores**, no una migración de pantallas por cada nuevo tema.

Los valores de readonly/disabled y el nuevo foco requieren validación contextual; no se declara que toda pareja resultante ya cumpla contraste. `--sit-field-disabled-text` es para controles efectivamente deshabilitados, no para texto informativo.

## G. Integración con Tailwind 4

```css
/* Propuesta de ampliación del @theme inline existente */
@theme inline {
  --font-sans: var(--sit-font-ui);
  --color-sit-primary: var(--sit-primary);
  --color-sit-primary-hover: var(--sit-primary-hover);
  --color-sit-primary-foreground: var(--sit-primary-foreground);
  --color-sit-action: var(--sit-action-primary);
  --color-sit-action-hover: var(--sit-action-hover);
  --color-sit-action-foreground: var(--sit-action-foreground);
  --color-sit-ground: var(--sit-ground);
  --color-sit-surface: var(--sit-surface);
  --color-sit-card: var(--sit-card);
  --color-sit-text: var(--sit-text);
  --color-sit-muted: var(--sit-text-muted);
  --color-sit-border: var(--sit-border);
  --color-sit-content-text: var(--sit-content-text);
  --color-sit-field-border: var(--sit-field-border);
  --color-sit-focus: var(--sit-focus-outline);
  --color-sit-danger: var(--sit-danger);
  --color-sit-danger-soft: var(--sit-danger-soft);
  --color-sit-danger-text: var(--sit-danger-text);
  --radius-sit: var(--sit-radius);
  --shadow-sit-card: var(--sit-shadow-card);
}
```

Ejemplo de consumo posterior: `bg-sit-action text-sit-action-foreground hover:bg-sit-action-hover`; card: `bg-sit-card text-sit-text border-sit-border rounded-sit shadow-sit-card`. Añadir aliases equivalentes para los estados que se estén migrando, manteniendo una sola definición semántica. No mapear una utility que no tenga consumidor.

No redefinir globalmente `.bg-white`, `.text-gray-*`, `.grid` ni todas las variables `--color-green-*` para simular un tema. Una misma clase se usa para acciones, estados y datos. No cambiar `--tw-*`, `!important`, breakpoints o escalas de texto para resolver colores. Las funciones JS deben devolver clases semánticas **completas y estáticas**; no interpolar `bg-${estado}-500`, porque la detección de utilidades de Tailwind debe poder encontrar sus nombres.

## H. Componentes reutilizables y prioridades

Ya existen diez componentes/layouts reutilizados: `MainLayout`, `SitTopbar`, `SitMenu`, `SitBreadcrumb`, `SitFooter`, `ContenidoSistema`, `AvisosInicio`, `ChatbotFAB`, `TimePicker24` y `TipTapEditor`. Reutilizarlos y normalizar sus consumidores antes de añadir equivalentes.

No se detectó un componente general activo para botón, campo, select, checkbox/radio, card, tabla, modal, badge/tag, paginación, loader o empty state. Predominan bloques repetidos dentro de las 82 vistas. El dropdown del usuario y los desplegables del menú pertenecen a la plantilla; no son todavía un dropdown genérico con contrato reutilizable.

Orden recomendado:

1. Registro de valores/roles, separación superficie/foreground y cobertura de plantilla/login.
2. Componentes compartidos anteriores: TimePicker, editor, avisos, mantenimiento y chatbot.
3. Extraer primitivas comunes de botón, campos, badge/alerta, card, diálogo, tabla/paginación cuando la repetición y los contratos existentes lo justifiquen. Conservar `v-model`, eventos, slots, loading, errores y condiciones.
4. Migrar un módulo piloto y comparar visualmente: formularios, tabla, filtros, selección, hover, readonly/disabled, modales, paginación y errores de API.
5. Extender por módulo y revisar Chart.js, categorías de datos y estados especiales de marcación.
6. Solo después, implementar tema/persistencia y validar un segundo conjunto de tokens.

## I. Referencia TEC y estrategia futura

TEC usa Java/Jakarta Faces y PrimeFaces **15.0.0**, según su `pom.xml`. `WEB-INF/template.xhtml` centraliza includes, stylesheet global y `layout-#{guestPreferences.theme}.css`; `GuestPreferences` es de sesión y actualmente admite solo **Tribunal**. No debe presentarse su selector como un catálogo funcional de varios temas.

`resources/primefaces-tribunal/theme.css` define variables y hace que botones/campos reaccionen a ellas; `resources/demo/css/tribunal-globals.css` añade roles/componentes y una escala tipográfica. `layout-tribunal.css` mezcla variables con valores fijos: la referencia tampoco garantiza por sí sola que cambiar una variable transforme cada recurso. Su JS de configuración incluye sustitución de enlaces de recursos; no es un mecanismo que SIT necesite copiar.

Se reutiliza **el concepto** de registro central, roles y componentes compartidos. No se copian colores, variables PrimeFaces, CSS comercial, PrimeFlex ni JSF. SIT no tiene componentes PrimeFaces que tematizar: sus consumidores son Vue/Tailwind/HTML y canvas. `right_panel.xhtml` no está en el directorio proporcionado; no se deduce su implementación de referencias indirectas.

Arquitectura futura recomendada para SIT:

`Valores del tema + roles semánticos → aliases Tailwind/CSS propio → componentes → vistas`

El modo futuro debe usar un atributo validado en el elemento raíz, por ejemplo `data-sit-theme`, **después de la normalización**, porque las variables heredadas alcanzan componentes y contenido teletransportado al `body`. No implementar ahora atributos, selector, hooks ni almacenamiento. No hacen falta variantes `dark:` para todos los colores si las utilidades ya consumen roles; reservar variantes para excepciones estructurales realmente necesarias.

Flujo posterior:

1. Catálogo cerrado de temas conocidos; la selección es un identificador, no CSS arbitrario.
2. Aplicar el conjunto de tokens al `<html>` y `color-scheme` correspondiente.
3. Persistir preferencia de UI separada de `sit_menu_mode`, token y `expires_at`. No extender la sesión ni guardar secretos con el tema.
4. Restaurar antes del primer render para evitar el destello del tema anterior; validar valor guardado y volver al tema predeterminado si es desconocido. Respetar restricciones de almacenamiento.
5. Al iniciar sesión, aplicar una política explícita: preferencia del dispositivo inicialmente, o preferencia por usuario si después se aprueba persistencia en BD. Para equipos compartidos, definir si el logout restaura el predeterminado; no presumir esa política.
6. Recalcular colores de texto/ejes/grillas y ejecutar `chart.update()` en canvas que deban reaccionar; no asumir que CSS repinta Chart.js.

Los temas claros/oscuros deben definir parejas de texto/fondo, estados hover/active/selected, focus y readonly. No requieren cambiar auth, endpoints, permisos o reglas de marcación.

## J. Archivos a normalizar y excepciones

El anexo enumera exactamente los archivos activos con coincidencias que requieren revisión. `archivos.csv` incluye también los archivos sin colores: no hay que modificarlos solo por aparecer en el recorrido. Los nuevos módulos de tokens/componentes que se creen en la fase de implementación deberán responder a consumidores comprobados, no a un catálogo especulativo.

No modificar durante esta fase ni incorporar automáticamente:

- `frontend/src/assets/base.css`/`main.css` y componentes `HelloWorld`/`TheWelcome`/`WelcomeItem`/sus iconos: heredados sin importación desde la aplicación activa.
- `frontend/src/views/adquisiciones/OrdenesCompraView.vue` y `frontend/src/views/empleados/EmpleadosView.vue`: sin importación detectada en el grafo actual. Revisar enlaces/rutas antes de decidir su normalización o retiro.
- `backend/resources/views/welcome.blade.php`: sí responde al `/` del backend, pero es la página Laravel inicial, no el SIT Vue. Contiene CSS de ejemplo embebido; no mezclar sus métricas con el tema de SIT.
- Los 43 Blade de reportes: mantener su diseño de documento/impresión independiente. Una refactorización de estilos de PDF necesitaría otro alcance y renderización de verificación.
- `Admin/TurnoController.php`: un color por defecto de categoría pertenece al contrato de datos, no a la preferencia de tema. No cambiarlo desde una auditoría de CSS.

## K. Contraste, riesgos y criterios de aceptación

Se calcularon contrastes de pares existentes en sRGB; los resultados completos están en `contrastes.json`. Para las primitivas OKLCH, la muestra sRGB se obtuvo con canvas en Chrome y es aproximada. Es un muestreo de pares, **no una certificación de accesibilidad de cada pantalla** ni un examen de todas las combinaciones de opacidad.

| Pareja actual | Ratio aproximado | Diagnóstico |
| --- | ---: | --- |
| Primario `#034ea2` / blanco | 8,03:1 | Adecuado para texto normal |
| Hover / blanco y active / blanco | 9,33:1 / 10,97:1 | Adecuados |
| Texto `#495057` / blanco | 8,18:1 | Adecuado |
| Texto secundario `#5f6870` / ground | 5,38:1 | Adecuado |
| Danger `#d32f2f` / `#fef4f4` | 4,61:1 | Adecuado, poco margen para opacidad |
| Success / blanco e info / blanco | 4,92:1 / 4,92:1 | Adecuados |
| Texto warning `#8d6c19` / `#fffcf5` | 4,78:1 | Adecuado |
| Blanco / warning amarillo | 1,66:1 | No usar como texto normal |
| Foco `#a9c3df` / blanco | 1,82:1 | Revisar indicador sobre superficies claras; sobre azul la situación es diferente |
| Foco heredado `#579186` / blanco | 3,62:1 | Adecuado como indicador no textual; no garantiza texto AA |
| Gray 400 / blanco | 2,60:1 | Riesgo en metadatos, placeholders y empty states activos |
| Acción TH / blanco | 8,85:1 | Adecuado |
| Marcación pendiente / blanco | 4,77:1 | Adecuado sin reducir opacidad; las reglas actuales de opacidad requieren revisión contextual |

Los umbrales de referencia son 4,5:1 para texto normal y 3:1 para indicadores visuales relevantes, con excepciones para controles realmente inactivos. Véanse [WCAG: contraste mínimo](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html) y [WCAG: contraste no textual](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html). Readonly sigue transmitiendo información y no debe tratarse automáticamente como una excepción disabled.

Riesgos principales: cambio visual accidental al unir verdes/azules/grises parecidos; pérdida de significado en tags de negocio; contraste con opacidad; canvas que no reacciona a variables; colores por categoría provenientes de datos; Teleport y overlays con z-index separados; foco visible del menú; controles nativos sin `color-scheme`; contenido TipTap; PDFs/formularios normados y logo blanco sobre una cabecera clara. Las mejoras de contraste deben revisarse como cambios visuales explícitos, no esconderse dentro de un reemplazo de colores.

La normalización se aceptará cuando un segundo conjunto de tokens de prueba cambie coherententemente plantilla, acciones, campos, cards, tablas, tags, modales, hover/focus/active/selected y estados readonly/disabled, sin editar vistas para ese segundo tema. Deben conservarse llamadas API, modelos/eventos, controles de acceso, filtros, fechas, secuencia/bloqueos de marcación, confirmación de salida temprana, rutas y vencimiento de sesión. Deben verificarse varios roles/módulos, mantenimiento, errores de API, móvil, zoom y teclado.

## Evidencias y reproducción

- `inventario.csv`: cada aparición con archivo, línea, valor, clasificación, propuesta e impacto; puede contener coincidencias superpuestas entre clase arbitraria y HEX, que no se suman para cobertura.
- `archivos.csv`: todas las rutas analizadas, ámbitos y métricas por archivo.
- `colores.csv`: repeticiones, archivos y usos activos separados de documentos/heredados.
- `variables.csv`: declaraciones y referencias exactas, separando heredados y activos.
- `resumen.json`: versiones y métricas del recorrido.
- `tailwind-primitivas.json`: valores del Tailwind instalado para consulta, no una paleta nueva de SIT.
- `contrastes.json`: pares muestreados y ratios calculados.
- `auditar.cjs`: repetir desde la raíz con `node docs/auditoria-tema-2026-10-08/auditar.cjs`; solo escribe los inventarios, no normaliza CSS. Los análisis humanos y contrastes no se regeneran automáticamente con ese comando.

Se descartaron comentarios HTML/bloque/comentarios de línea completa para las métricas. El grafo de imports es estático: no prueba rutas ejecutadas, datos de todos los roles ni recursos externos. Las métricas no cuentan colores recibidos de BD, píxeles de PNG ni todos los estilos predeterminados de librerías/navegador; sí documentan esos casos que necesitan revisión. No se ejecutaron migraciones, autenticación real ni operaciones de BD. No se recompiló la aplicación porque no se cambió código del aplicativo.

<!-- ANEXOS GENERADOS -->

## Anexo 1. Variables activas existentes

No incluye las variables del starter Vue inactivo ni la paleta completa de la dependencia Tailwind. Las referencias son coincidencias directas de `var()`; no cuentan reglas generadas por el compilador.

| Archivo/línea | Variable | Valor actual | Referencias directas |
| --- | --- | --- | ---: |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:6) | `--sit-font-ui` | `'Montserrat', system-ui, 'Segoe UI', sans-serif` | 2 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:7) | `--sit-primary` | `#034ea2` | 14 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:7) | `--sit-primary-hover` | `#03458f` | 3 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:7) | `--sit-primary-active` | `#023b7b` | 0 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:7) | `--sit-primary-focus` | `#a9c3df` | 2 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:8) | `--sit-topbar-end` | `#0b2e6b` | 2 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:8) | `--sit-on-color` | `#ffffff` | 5 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:8) | `--sit-surface` | `var(--sit-on-color)` | 8 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:8) | `--sit-ground` | `#f8f9fa` | 5 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:9) | `--sit-border` | `#dee2e6` | 9 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:9) | `--sit-text` | `#495057` | 4 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:9) | `--sit-text-muted` | `#5f6870` | 5 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:9) | `--sit-text-strong` | `#212529` | 3 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:10) | `--sit-neutral` | `#597481` | 0 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:10) | `--sit-success` | `#517c2c` | 0 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:10) | `--sit-info` | `#0276b6` | 0 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:10) | `--sit-warn` | `#fbc02d` | 2 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:10) | `--sit-warn-text` | `#8d6c19` | 2 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:10) | `--sit-danger` | `#d32f2f` | 5 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:11) | `--sit-primary-soft` | `#f0f4f9` | 3 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:11) | `--sit-success-soft` | `#f6fbf6` | 0 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:11) | `--sit-info-soft` | `#f4fafe` | 0 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:11) | `--sit-warn-soft` | `#fffcf5` | 2 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:11) | `--sit-danger-soft` | `#fef4f4` | 2 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:11) | `--sit-neutral-soft` | `var(--sit-ground)` | 0 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:12) | `--sit-radius` | `.5rem` | 10 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:12) | `--sit-shadow-card` | `0 1px 4px rgb(0 0 0 / .08)` | 0 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:12) | `--sit-shadow-float` | `0 4px 16px rgb(0 0 0 / .15)` | 3 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:12) | `--sit-overlay` | `rgb(15 23 42 / .45)` | 1 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:13) | `--sit-header-height` | `4rem` | 3 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:13) | `--sit-menu-top` | `var(--sit-header-height)` | 2 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:13) | `--sit-sidebar-width` | `16rem` | 2 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:13) | `--sit-sidebar-collapsed` | `4rem` | 1 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:14) | `--sit-z-topbar` | `30` | 1 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:14) | `--sit-z-overlay` | `40` | 1 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:14) | `--sit-z-menu` | `45` | 3 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:17) | `--font-sans` | `var(--sit-font-ui)` | 0 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:18) | `--color-sit-primary` | `var(--sit-primary)` | 0 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:18) | `--color-sit-primary-hover` | `var(--sit-primary-hover)` | 0 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:19) | `--color-sit-text` | `var(--sit-text)` | 0 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:19) | `--color-sit-muted` | `var(--sit-text-muted)` | 0 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:19) | `--color-sit-border` | `var(--sit-border)` | 0 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:19) | `--color-sit-surface` | `var(--sit-surface)` | 0 |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css:27) | `--sit-menu-top` | `calc(var(--sit-header-height) + 1.5rem)` | 2 |
| [frontend/src/views/tecnologia/EquiposView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/tecnologia/EquiposView.vue:34) | `--tw-ring-color` | `#4d7c8a55` | 0 |
| [frontend/src/views/tecnologia/MantenimientoView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/tecnologia/MantenimientoView.vue:19) | `--tw-ring-color` | `#4d7c8a55` | 0 |
| [frontend/src/views/tecnologia/PiezasView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/tecnologia/PiezasView.vue:17) | `--tw-ring-color` | `#4d7c8a55` | 0 |
| [frontend/src/views/transporte/VehiculosView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/transporte/VehiculosView.vue:16) | `--tw-ring-color` | `#1e3a5f` | 0 |

## Anexo 2. Archivos activos con consumidores por revisar

Esta es la lista exacta de revisión, no una orden de editar todo archivo. Las coincidencias de dimensiones/datos/series se mantienen como revisión; los estilos ya tokenizados no necesitan una conversión automática. Los HEX pueden estar incluidos en clases arbitrarias: no sumar columnas como si fueran casos independientes.

| Archivo | HEX | Tailwind físico | Tailwind arbitrario | Inline | Prioridad/contexto |
| --- | ---: | ---: | ---: | ---: | --- |
| [frontend/src/App.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/App.vue) | 0 | 0 | 0 | 0 | Compartido / revisar primero |
| [frontend/src/components/AvisosInicio.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/components/AvisosInicio.vue) | 5 | 9 | 5 | 0 | Compartido / revisar primero |
| [frontend/src/components/ChatbotFAB.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/components/ChatbotFAB.vue) | 16 | 14 | 0 | 10 | Compartido / revisar primero |
| [frontend/src/components/ContenidoSistema.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/components/ContenidoSistema.vue) | 0 | 5 | 0 | 0 | Compartido / revisar primero |
| [frontend/src/components/TimePicker24.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/components/TimePicker24.vue) | 2 | 3 | 2 | 0 | Compartido / revisar primero |
| [frontend/src/components/TipTapEditor.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/components/TipTapEditor.vue) | 1 | 8 | 1 | 0 | Compartido / revisar primero |
| [frontend/src/layouts/components/SitMenu.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/layouts/components/SitMenu.vue) | 0 | 0 | 0 | 2 | Compartido / revisar primero |
| [frontend/src/styles/sit-theme.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/styles/sit-theme.css) | 22 | 0 | 0 | 0 | Registro/CSS global |
| [frontend/src/views/acciones/AccionesPersonalView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/acciones/AccionesPersonalView.vue) | 22 | 116 | 21 | 6 | Migrar por módulo |
| [frontend/src/views/acciones/AccionPersonalForm.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/acciones/AccionPersonalForm.vue) | 27 | 95 | 27 | 7 | Migrar por módulo |
| [frontend/src/views/acciones/HistorialRemuneracionesView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/acciones/HistorialRemuneracionesView.vue) | 7 | 56 | 7 | 0 | Migrar por módulo |
| [frontend/src/views/admin/aportes/AportesIessView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/admin/aportes/AportesIessView.vue) | 13 | 54 | 12 | 1 | Migrar por módulo |
| [frontend/src/views/admin/AuditoriaView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/admin/AuditoriaView.vue) | 0 | 56 | 0 | 0 | Migrar por módulo |
| [frontend/src/views/admin/AvisosView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/admin/AvisosView.vue) | 3 | 43 | 0 | 3 | Migrar por módulo |
| [frontend/src/views/admin/calendario/CalendarioView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/admin/calendario/CalendarioView.vue) | 16 | 70 | 11 | 3 | Migrar por módulo |
| [frontend/src/views/admin/configuracion/ConfiguracionView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/admin/configuracion/ConfiguracionView.vue) | 11 | 34 | 10 | 1 | Migrar por módulo |
| [frontend/src/views/admin/cuadre/CuadreView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/admin/cuadre/CuadreView.vue) | 7 | 60 | 7 | 0 | Migrar por módulo |
| [frontend/src/views/admin/departamentos/DepartamentosView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/admin/departamentos/DepartamentosView.vue) | 10 | 67 | 9 | 1 | Migrar por módulo |
| [frontend/src/views/admin/jornadas/JornadasView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/admin/jornadas/JornadasView.vue) | 12 | 41 | 11 | 1 | Migrar por módulo |
| [frontend/src/views/admin/ModalidadLaboralView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/admin/ModalidadLaboralView.vue) | 6 | 23 | 4 | 2 | Migrar por módulo |
| [frontend/src/views/admin/opciones/OpcionesView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/admin/opciones/OpcionesView.vue) | 13 | 52 | 12 | 1 | Migrar por módulo |
| [frontend/src/views/admin/periodos/PeriodosView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/admin/periodos/PeriodosView.vue) | 10 | 33 | 9 | 1 | Migrar por módulo |
| [frontend/src/views/admin/ProvinciaCiudadView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/admin/ProvinciaCiudadView.vue) | 12 | 44 | 10 | 5 | Migrar por módulo |
| [frontend/src/views/admin/razones/RazonesView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/admin/razones/RazonesView.vue) | 10 | 64 | 9 | 1 | Migrar por módulo |
| [frontend/src/views/admin/RolesView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/admin/RolesView.vue) | 8 | 39 | 6 | 2 | Migrar por módulo |
| [frontend/src/views/admin/SbuView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/admin/SbuView.vue) | 0 | 22 | 0 | 0 | Migrar por módulo |
| [frontend/src/views/admin/TarifasViaticosView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/admin/TarifasViaticosView.vue) | 12 | 91 | 4 | 9 | Migrar por módulo |
| [frontend/src/views/admin/turnos/TurnosView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/admin/turnos/TurnosView.vue) | 20 | 66 | 8 | 4 | Migrar por módulo |
| [frontend/src/views/admin/ZktecoView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/admin/ZktecoView.vue) | 4 | 49 | 1 | 3 | Migrar por módulo |
| [frontend/src/views/adquisiciones/AdqDashboardView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/adquisiciones/AdqDashboardView.vue) | 0 | 37 | 0 | 0 | Migrar por módulo |
| [frontend/src/views/adquisiciones/AjusteInventarioView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/adquisiciones/AjusteInventarioView.vue) | 2 | 54 | 0 | 2 | Migrar por módulo |
| [frontend/src/views/adquisiciones/ArticulosView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/adquisiciones/ArticulosView.vue) | 29 | 182 | 21 | 8 | Migrar por módulo |
| [frontend/src/views/adquisiciones/CatalogoInventarioView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/adquisiciones/CatalogoInventarioView.vue) | 2 | 62 | 0 | 2 | Migrar por módulo |
| [frontend/src/views/adquisiciones/EgresosBienesView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/adquisiciones/EgresosBienesView.vue) | 22 | 133 | 5 | 14 | Migrar por módulo |
| [frontend/src/views/adquisiciones/IngresosBienesView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/adquisiciones/IngresosBienesView.vue) | 31 | 190 | 7 | 21 | Migrar por módulo |
| [frontend/src/views/adquisiciones/IvaView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/adquisiciones/IvaView.vue) | 2 | 46 | 0 | 2 | Migrar por módulo |
| [frontend/src/views/adquisiciones/ProcesoContratacionView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/adquisiciones/ProcesoContratacionView.vue) | 2 | 35 | 0 | 2 | Migrar por módulo |
| [frontend/src/views/adquisiciones/ProveedoresView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/adquisiciones/ProveedoresView.vue) | 11 | 95 | 9 | 2 | Migrar por módulo |
| [frontend/src/views/adquisiciones/ReporteEgresosView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/adquisiciones/ReporteEgresosView.vue) | 1 | 38 | 0 | 1 | Migrar por módulo |
| [frontend/src/views/adquisiciones/ReporteInventarioMensualView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/adquisiciones/ReporteInventarioMensualView.vue) | 11 | 18 | 8 | 2 | Migrar por módulo |
| [frontend/src/views/adquisiciones/ReporteInventarioValorizadoView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/adquisiciones/ReporteInventarioValorizadoView.vue) | 10 | 53 | 1 | 10 | Migrar por módulo |
| [frontend/src/views/adquisiciones/ReporteKardexView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/adquisiciones/ReporteKardexView.vue) | 28 | 116 | 0 | 27 | Migrar por módulo |
| [frontend/src/views/adquisiciones/ReporteLibroComprasView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/adquisiciones/ReporteLibroComprasView.vue) | 11 | 79 | 0 | 7 | Migrar por módulo |
| [frontend/src/views/adquisiciones/SolicitudesView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/adquisiciones/SolicitudesView.vue) | 11 | 121 | 0 | 11 | Migrar por módulo |
| [frontend/src/views/adquisiciones/UnidadesMedidaView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/adquisiciones/UnidadesMedidaView.vue) | 2 | 40 | 0 | 2 | Migrar por módulo |
| [frontend/src/views/asistencia/ReporteSinAtrasosView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/asistencia/ReporteSinAtrasosView.vue) | 6 | 23 | 6 | 0 | Migrar por módulo |
| [frontend/src/views/AsistenciaView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/AsistenciaView.vue) | 15 | 68 | 7 | 3 | Migrar por módulo |
| [frontend/src/views/certificados/CertificadosView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/certificados/CertificadosView.vue) | 18 | 58 | 17 | 1 | Migrar por módulo |
| [frontend/src/views/comisiones/ComisionesView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/comisiones/ComisionesView.vue) | 49 | 286 | 28 | 31 | Migrar por módulo |
| [frontend/src/views/comisiones/FuncionariosExternosView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/comisiones/FuncionariosExternosView.vue) | 16 | 72 | 9 | 14 | Migrar por módulo |
| [frontend/src/views/comisiones/LiquidacionesView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/comisiones/LiquidacionesView.vue) | 27 | 256 | 13 | 17 | Migrar por módulo |
| [frontend/src/views/DashboardView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/DashboardView.vue) | 14 | 104 | 1 | 7 | Migrar por módulo |
| [frontend/src/views/empleados/DistributivoView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/empleados/DistributivoView.vue) | 3 | 39 | 3 | 0 | Migrar por módulo |
| [frontend/src/views/empleados/EmpleadoDetalle.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/empleados/EmpleadoDetalle.vue) | 5 | 68 | 5 | 0 | Migrar por módulo |
| [frontend/src/views/empleados/EmpleadoForm.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/empleados/EmpleadoForm.vue) | 41 | 131 | 26 | 10 | Migrar por módulo |
| [frontend/src/views/empleados/EmpleadosIndex.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/empleados/EmpleadosIndex.vue) | 11 | 46 | 11 | 0 | Migrar por módulo |
| [frontend/src/views/empleados/ImportacionView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/empleados/ImportacionView.vue) | 7 | 76 | 7 | 0 | Migrar por módulo |
| [frontend/src/views/empleados/ReporteEmpleadosView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/empleados/ReporteEmpleadosView.vue) | 33 | 183 | 22 | 2 | Migrar por módulo |
| [frontend/src/views/horasextras/HorasExtrasView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/horasextras/HorasExtrasView.vue) | 28 | 294 | 20 | 8 | Migrar por módulo |
| [frontend/src/views/LoginView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/LoginView.vue) | 0 | 10 | 0 | 0 | Migrar por módulo |
| [frontend/src/views/nomina/ConsolidadoView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/nomina/ConsolidadoView.vue) | 0 | 62 | 0 | 0 | Migrar por módulo |
| [frontend/src/views/nomina/DecimoCuartoView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/nomina/DecimoCuartoView.vue) | 2 | 90 | 0 | 2 | Migrar por módulo |
| [frontend/src/views/nomina/DecimosView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/nomina/DecimosView.vue) | 0 | 4 | 0 | 0 | Migrar por módulo |
| [frontend/src/views/nomina/DecimoTerceroView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/nomina/DecimoTerceroView.vue) | 2 | 87 | 0 | 2 | Migrar por módulo |
| [frontend/src/views/nomina/FondosReservaView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/nomina/FondosReservaView.vue) | 2 | 82 | 0 | 2 | Migrar por módulo |
| [frontend/src/views/nomina/RolPagoView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/nomina/RolPagoView.vue) | 12 | 193 | 9 | 3 | Migrar por módulo |
| [frontend/src/views/PerfilView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/PerfilView.vue) | 6 | 17 | 6 | 0 | Migrar por módulo |
| [frontend/src/views/PermisosView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/PermisosView.vue) | 37 | 228 | 33 | 4 | Migrar por módulo |
| [frontend/src/views/planificacion/LiquidacionVacView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/planificacion/LiquidacionVacView.vue) | 0 | 88 | 0 | 0 | Migrar por módulo |
| [frontend/src/views/planificacion/PlanificacionesView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/planificacion/PlanificacionesView.vue) | 50 | 162 | 46 | 4 | Migrar por módulo |
| [frontend/src/views/planificacion/ReportePlanificacionView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/planificacion/ReportePlanificacionView.vue) | 8 | 58 | 8 | 0 | Migrar por módulo |
| [frontend/src/views/planificacion/ReporteSaldoVacView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/planificacion/ReporteSaldoVacView.vue) | 20 | 125 | 20 | 0 | Migrar por módulo |
| [frontend/src/views/reportes/LotaipView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/reportes/LotaipView.vue) | 5 | 56 | 5 | 0 | Migrar por módulo |
| [frontend/src/views/reportes/ReportesView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/reportes/ReportesView.vue) | 18 | 174 | 18 | 0 | Migrar por módulo |
| [frontend/src/views/supervisores/SupervisoresView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/supervisores/SupervisoresView.vue) | 9 | 69 | 8 | 1 | Migrar por módulo |
| [frontend/src/views/tecnologia/ActividadesMantenimientoView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/tecnologia/ActividadesMantenimientoView.vue) | 3 | 25 | 0 | 3 | Migrar por módulo |
| [frontend/src/views/tecnologia/EquiposView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/tecnologia/EquiposView.vue) | 32 | 173 | 2 | 22 | Migrar por módulo |
| [frontend/src/views/tecnologia/MantenimientoView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/tecnologia/MantenimientoView.vue) | 27 | 102 | 1 | 14 | Migrar por módulo |
| [frontend/src/views/tecnologia/PiezasView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/tecnologia/PiezasView.vue) | 17 | 107 | 0 | 15 | Migrar por módulo |
| [frontend/src/views/tecnologia/ReporteEquiposView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/tecnologia/ReporteEquiposView.vue) | 11 | 53 | 0 | 7 | Migrar por módulo |
| [frontend/src/views/tecnologia/TiposEquipoView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/tecnologia/TiposEquipoView.vue) | 3 | 23 | 0 | 3 | Migrar por módulo |
| [frontend/src/views/transporte/MantenimientoView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/transporte/MantenimientoView.vue) | 9 | 129 | 0 | 9 | Migrar por módulo |
| [frontend/src/views/transporte/MovilizacionView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/transporte/MovilizacionView.vue) | 9 | 118 | 0 | 9 | Migrar por módulo |
| [frontend/src/views/transporte/PlanPreventivoView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/transporte/PlanPreventivoView.vue) | 5 | 93 | 0 | 5 | Migrar por módulo |
| [frontend/src/views/transporte/ReportesView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/transporte/ReportesView.vue) | 18 | 144 | 13 | 5 | Migrar por módulo |
| [frontend/src/views/transporte/TalleresView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/transporte/TalleresView.vue) | 3 | 36 | 0 | 3 | Migrar por módulo |
| [frontend/src/views/transporte/TiposMantenimientoView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/transporte/TiposMantenimientoView.vue) | 3 | 23 | 0 | 3 | Migrar por módulo |
| [frontend/src/views/transporte/ValesCombustibleView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/transporte/ValesCombustibleView.vue) | 3 | 46 | 0 | 3 | Migrar por módulo |
| [frontend/src/views/transporte/VehiculosView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/transporte/VehiculosView.vue) | 9 | 64 | 0 | 7 | Migrar por módulo |
| [frontend/src/views/VacacionesView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/VacacionesView.vue) | 31 | 172 | 25 | 6 | Migrar por módulo |

Archivos de integración cuyo cambio se decidirá al diseñar la implementación: [frontend/src/style.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/style.css), [frontend/src/layouts/MainLayout.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/layouts/MainLayout.vue), [frontend/index.html](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/index.html), [frontend/src/main.js](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/main.js). No es necesario modificarlos todos para la normalización; el inicio temprano del tema/persistencia pertenece a la fase futura.

## Anexo 3. Archivos fuera del frontend activo

| Archivo/ámbito | Acción |
| --- | --- |
| [frontend/src/assets/base.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/assets/base.css) | Sin importación detectada: revisar uso antes de migrar/retirar |
| [frontend/src/assets/logo.svg](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/assets/logo.svg) | Recurso: conservar identidad/colores |
| [frontend/src/assets/main.css](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/assets/main.css) | Sin importación detectada: revisar uso antes de migrar/retirar |
| [frontend/src/components/HelloWorld.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/components/HelloWorld.vue) | Sin importación detectada: revisar uso antes de migrar/retirar |
| [frontend/src/components/icons/IconCommunity.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/components/icons/IconCommunity.vue) | Sin importación detectada: revisar uso antes de migrar/retirar |
| [frontend/src/components/icons/IconDocumentation.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/components/icons/IconDocumentation.vue) | Sin importación detectada: revisar uso antes de migrar/retirar |
| [frontend/src/components/icons/IconEcosystem.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/components/icons/IconEcosystem.vue) | Sin importación detectada: revisar uso antes de migrar/retirar |
| [frontend/src/components/icons/IconSupport.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/components/icons/IconSupport.vue) | Sin importación detectada: revisar uso antes de migrar/retirar |
| [frontend/src/components/icons/IconTooling.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/components/icons/IconTooling.vue) | Sin importación detectada: revisar uso antes de migrar/retirar |
| [frontend/src/components/TheWelcome.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/components/TheWelcome.vue) | Sin importación detectada: revisar uso antes de migrar/retirar |
| [frontend/src/components/WelcomeItem.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/components/WelcomeItem.vue) | Sin importación detectada: revisar uso antes de migrar/retirar |
| [frontend/src/views/adquisiciones/OrdenesCompraView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/adquisiciones/OrdenesCompraView.vue) | Sin importación detectada: revisar uso antes de migrar/retirar |
| [frontend/src/views/empleados/EmpleadosView.vue](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/frontend/src/views/empleados/EmpleadosView.vue) | Sin importación detectada: revisar uso antes de migrar/retirar |
| [backend/resources/views/reportes/accion_personal.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/accion_personal.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/acc_historial_remuneraciones.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/acc_historial_remuneraciones.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/acc_lista.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/acc_lista.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/adq_inventario_mensual.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/adq_inventario_mensual.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/adq_inventario_valorizado.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/adq_inventario_valorizado.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/adq_solicitud_material.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/adq_solicitud_material.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/certificado_laboral.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/certificado_laboral.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/com_ficha_exterior.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/com_ficha_exterior.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/com_ficha_interior.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/com_ficha_interior.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/com_informe_exterior.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/com_informe_exterior.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/com_informe_interior.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/com_informe_interior.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/com_solicitud_exterior.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/com_solicitud_exterior.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/com_solicitud_interior.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/com_solicitud_interior.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/egresos_valorizados.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/egresos_valorizados.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/egreso_bodega.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/egreso_bodega.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/he_planificacion.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/he_planificacion.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/he_registros.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/he_registros.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/ingreso_bodega.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/ingreso_bodega.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/kardex.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/kardex.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/libro_compras.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/libro_compras.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/liquidacion_vacaciones.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/liquidacion_vacaciones.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/nom_consolidado.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/nom_consolidado.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/nom_decimo_cuarto.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/nom_decimo_cuarto.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/nom_decimo_tercero.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/nom_decimo_tercero.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/nom_fondos_reserva.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/nom_fondos_reserva.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/nom_rol_pago.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/nom_rol_pago.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/nom_rol_pago_resumenes.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/nom_rol_pago_resumenes.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/permisos_lista.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/permisos_lista.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/planificacion_vacaciones.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/planificacion_vacaciones.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/reporte_atrasos.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/reporte_atrasos.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/reporte_empleados.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/reporte_empleados.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/reporte_faltantes.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/reporte_faltantes.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/reporte_movimientos.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/reporte_movimientos.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/ti_acta_mantenimiento.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/ti_acta_mantenimiento.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/ti_acta_mantenimiento_externo.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/ti_acta_mantenimiento_externo.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/ti_reporte_equipos.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/ti_reporte_equipos.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/trans_orden_movilizacion.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/trans_orden_movilizacion.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/trans_orden_trabajo.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/trans_orden_trabajo.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/trans_reporte_mantenimiento.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/trans_reporte_mantenimiento.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/trans_reporte_movilizacion.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/trans_reporte_movilizacion.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/trans_reporte_vales.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/trans_reporte_vales.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/trans_vale_combustible.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/trans_vale_combustible.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/reportes/vac_reporte_saldo.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/reportes/vac_reporte_saldo.blade.php) | Documento: excluir del tema interactivo |
| [backend/resources/views/welcome.blade.php](C:/Users/llema/Documents/workspace/workspace_tthh/rrhh/backend/resources/views/welcome.blade.php) | Página backend: alcance separado |
