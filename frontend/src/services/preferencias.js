export const PREFERENCIAS_KEY = 'sit_preferences'
export const MENU_MODES = Object.freeze(['vertical', 'horizontal'])
export const FONT_SCALES = Object.freeze([100, 112.5, 125])
export const PREFERENCIAS_DEFAULT = Object.freeze({ version: 1, menuMode: 'vertical', fontScale: 100 })

export function normalizarPreferencias(valor) {
  return {
    version: 1,
    menuMode: MENU_MODES.includes(valor?.menuMode) ? valor.menuMode : PREFERENCIAS_DEFAULT.menuMode,
    fontScale: FONT_SCALES.includes(valor?.fontScale) ? valor.fontScale : PREFERENCIAS_DEFAULT.fontScale,
  }
}

function almacen(storage) {
  try { return storage ?? window.localStorage } catch { return null }
}

function leerClave(storage, key) {
  try { return storage?.getItem(key) ?? null } catch { return null }
}

export function leerPreferencias(storage) {
  const local = almacen(storage)
  const guardado = leerClave(local, PREFERENCIAS_KEY)
  try {
    const valor = JSON.parse(guardado)
    if (valor?.version === 1) return normalizarPreferencias(valor)
  } catch { /* Una preferencia corrupta no impide iniciar el aplicativo. */ }
  const menu = leerClave(local, 'sit_menu_mode') ?? leerClave(local, 'th_menu_mode')
  return normalizarPreferencias({ menuMode: menu })
}

export function guardarPreferencias(valor, storage) {
  const local = almacen(storage)
  const preferencias = normalizarPreferencias(valor)
  try { local?.setItem(PREFERENCIAS_KEY, JSON.stringify(preferencias)) } catch { /* Solo en memoria. */ }
  // Compatibilidad con la preferencia de navegación existente.
  try { local?.setItem('sit_menu_mode', preferencias.menuMode) } catch { /* Solo en memoria. */ }
}

export function aplicarPreferencias(valor, root = document.documentElement) {
  const { fontScale } = normalizarPreferencias(valor)
  const porcentaje = `${fontScale}%`
  if (root.style.getPropertyValue('--sit-font-scale') !== porcentaje) root.style.setProperty('--sit-font-scale', porcentaje)
  if (root.getAttribute('data-sit-font-size') !== String(fontScale)) root.setAttribute('data-sit-font-size', String(fontScale))
}

export function siguienteEscala(escala, direccion) {
  const actual = FONT_SCALES.indexOf(normalizarPreferencias({ fontScale: escala }).fontScale)
  return FONT_SCALES[Math.max(0, Math.min(FONT_SCALES.length - 1, actual + Math.sign(direccion)))]
}
