import { readonly, ref } from 'vue'
import { PREFERENCIAS_DEFAULT, PREFERENCIAS_KEY, aplicarPreferencias, guardarPreferencias, leerPreferencias, normalizarPreferencias, siguienteEscala } from '@/services/preferencias'

const preferencias = ref({ ...PREFERENCIAS_DEFAULT })
let inicializadas = false

function sincronizar(event) {
  try { if (event.storageArea !== window.localStorage) return } catch { return }
  if (event.key !== PREFERENCIAS_KEY && event.key !== null) return
  preferencias.value = leerPreferencias()
  aplicarPreferencias(preferencias.value)
}

export function inicializarSitPreferences() {
  if (inicializadas) return
  inicializadas = true
  preferencias.value = leerPreferencias()
  aplicarPreferencias(preferencias.value)
  window.addEventListener('storage', sincronizar)
}

function actualizar(cambios) {
  preferencias.value = normalizarPreferencias({ ...preferencias.value, ...cambios })
  aplicarPreferencias(preferencias.value)
  guardarPreferencias(preferencias.value)
}

export function useSitPreferences() {
  inicializarSitPreferences()
  return {
    preferencias: readonly(preferencias),
    cambiarMenu: menuMode => actualizar({ menuMode }),
    cambiarTamano: direccion => actualizar({ fontScale: siguienteEscala(preferencias.value.fontScale, direccion) }),
    restablecer: () => actualizar(PREFERENCIAS_DEFAULT),
  }
}

if (import.meta.hot) import.meta.hot.dispose(() => window.removeEventListener('storage', sincronizar))
