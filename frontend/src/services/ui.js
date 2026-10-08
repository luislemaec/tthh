import { readonly, ref, shallowRef } from 'vue'
import { crearContadorOperaciones } from './operaciones.js'

const pendientes = ref(0)
const mensajes = ref([])
const confirmacion = shallowRef(null)
const contador = crearContadorOperaciones(total => { pendientes.value = total })
const cola = []
let secuencia = 0

export const estadoUI = { pendientes: readonly(pendientes), mensajes: readonly(mensajes), confirmacion: readonly(confirmacion) }
export const iniciarOperacion = () => contador.iniciar()

export function notificar(mensaje, tipo = 'info') {
  const id = ++secuencia
  const severidad = ['success', 'warn', 'danger', 'info'].includes(tipo) ? tipo : 'info'
  mensajes.value = [...mensajes.value.slice(-4), { id, mensaje: String(mensaje || ''), tipo: severidad }]
  return id
}
export function cerrarNotificacion(id) { mensajes.value = mensajes.value.filter(item => item.id !== id) }

export function confirmarAccion({ mensaje, titulo = 'Confirmar acción', aceptar = 'Confirmar', peligrosa = false }) {
  return new Promise(resolve => {
    cola.push({ id: ++secuencia, mensaje, titulo, aceptar, peligrosa, resolve })
    siguienteConfirmacion()
  })
}
function siguienteConfirmacion() {
  if (!confirmacion.value && cola.length) confirmacion.value = cola.shift()
}
export function resolverConfirmacion(aceptada = false, id) {
  const actual = confirmacion.value
  if (!actual || (id !== undefined && id !== actual.id)) return
  confirmacion.value = null
  actual.resolve(aceptada === true)
  siguienteConfirmacion()
}
export function cancelarConfirmaciones() {
  for (const item of cola.splice(0)) item.resolve(false)
  resolverConfirmacion(false)
}
