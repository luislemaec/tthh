import test from 'node:test'
import assert from 'node:assert/strict'
import { crearContadorOperaciones, segundosRestantes } from '../src/services/operaciones.js'
import { cancelarConfirmaciones, cerrarNotificacion, confirmarAccion, estadoUI, notificar, resolverConfirmacion } from '../src/services/ui.js'
import axios from 'axios'
import api from '../src/services/api.js'

test('el indicador permanece activo hasta finalizar todas las peticiones, aun en orden inverso', () => {
  const cambios = []
  const contador = crearContadorOperaciones(total => cambios.push(total))
  const primera = contador.iniciar(), segunda = contador.iniciar(), tercera = contador.iniciar()
  segunda(); segunda()
  assert.equal(contador.pendientes, 2)
  tercera()
  assert.equal(contador.pendientes, 1)
  primera(); tercera(); primera()
  assert.equal(contador.pendientes, 0)
  assert.deepEqual(cambios, [1, 2, 3, 2, 1, 0])
})

test('el aviso de sesión usa el vencimiento absoluto recibido y no renueva por actividad', () => {
  const inicio = Date.parse('2026-10-08T10:00:00-05:00')
  const fin = '2026-10-08T10:15:00-05:00'
  assert.equal(segundosRestantes(fin, inicio), 900)
  assert.equal(segundosRestantes(fin, inicio + 840000), 60)
  assert.equal(segundosRestantes(fin, inicio + 899900), 1)
  assert.equal(segundosRestantes(fin, inicio + 1000000), 0)
  assert.equal(segundosRestantes('invalida', inicio), null)
  assert.equal(segundosRestantes(null, inicio), null)
})

test('las confirmaciones se serializan y un evento close anterior no acepta ni cancela la siguiente', async () => {
  const primera = confirmarAccion({ mensaje: 'Primera' })
  const idPrimera = estadoUI.confirmacion.value.id
  const segunda = confirmarAccion({ mensaje: 'Segunda' })
  assert.equal(estadoUI.confirmacion.value.mensaje, 'Primera')
  resolverConfirmacion(true, idPrimera)
  assert.equal(await primera, true)
  resolverConfirmacion(false, idPrimera)
  assert.equal(estadoUI.confirmacion.value.mensaje, 'Segunda')
  resolverConfirmacion(false, estadoUI.confirmacion.value.id)
  assert.equal(await segunda, false)
  assert.equal(estadoUI.confirmacion.value, null)
})

test('navegar o salir cancela la confirmación activa y toda la cola sin ejecutar acciones', async () => {
  const primera = confirmarAccion({ mensaje: 'Eliminar' })
  const segunda = confirmarAccion({ mensaje: 'Procesar' })
  cancelarConfirmaciones()
  assert.deepEqual(await Promise.all([primera, segunda]), [false, false])
  assert.equal(estadoUI.confirmacion.value, null)
})

test('los avisos tienen severidad válida, límite de acumulación y cierre individual', () => {
  for (let i = 0; i < 6; i++) notificar(`Aviso ${i}`, i === 5 ? 'desconocida' : 'success')
  assert.equal(estadoUI.mensajes.value.length, 5)
  assert.equal(estadoUI.mensajes.value[0].mensaje, 'Aviso 1')
  assert.equal(estadoUI.mensajes.value.at(-1).tipo, 'info')
  const ultimo = estadoUI.mensajes.value.at(-1).id
  cerrarNotificacion(ultimo)
  assert.equal(estadoUI.mensajes.value.length, 4)
  for (const item of [...estadoUI.mensajes.value]) cerrarNotificacion(item.id)
})

test('Axios cierra actividad en éxito, error y cancelación; excluye consultas de fondo', async () => {
  const storageAnterior = globalThis.sessionStorage
  globalThis.sessionStorage = { getItem: () => null }
  try {
    let completarPrimera, fallarSegunda
    const primera = api.get('/simulada-1', { adapter: config => new Promise(resolve => { completarPrimera = () => resolve({ data: {}, status: 200, statusText: 'OK', headers: {}, config }) }) })
    const segunda = api.get('/simulada-2', { adapter: config => new Promise((_resolve, reject) => { fallarSegunda = () => reject(new axios.AxiosError('simulado', 'ERR_BAD_RESPONSE', config, null, { status: 500, config })) }) }).catch(error => error)
    await new Promise(resolve => setImmediate(resolve))
    assert.equal(estadoUI.pendientes.value, 2)
    await api.get('/fondo-simulado', { sitBackground: true, adapter: async config => ({ data: {}, status: 200, statusText: 'OK', headers: {}, config }) })
    assert.equal(estadoUI.pendientes.value, 2)
    fallarSegunda(); await segunda
    assert.equal(estadoUI.pendientes.value, 1)
    completarPrimera(); await primera
    assert.equal(estadoUI.pendientes.value, 0)
    const cancelada = new AbortController()
    cancelada.abort()
    await assert.rejects(api.get('/cancelada', { signal: cancelada.signal, adapter: () => { throw new Error('No debe ejecutarse') } }), error => axios.isCancel(error))
    assert.equal(estadoUI.pendientes.value, 0)
  } finally {
    if (storageAnterior === undefined) delete globalThis.sessionStorage
    else globalThis.sessionStorage = storageAnterior
  }
})
