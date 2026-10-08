import assert from 'node:assert/strict'
import { test } from 'node:test'
import { PREFERENCIAS_DEFAULT, PREFERENCIAS_KEY, guardarPreferencias, leerPreferencias, normalizarPreferencias, siguienteEscala } from '../src/services/preferencias.js'

function almacenamiento(datos = {}) {
  const valores = new Map(Object.entries(datos))
  return { getItem: key => valores.get(key) ?? null, setItem: (key, value) => valores.set(key, value), valores }
}

test('migra el menú heredado y prioriza la preferencia SIT existente', () => {
  assert.equal(leerPreferencias(almacenamiento({ th_menu_mode: 'horizontal' })).menuMode, 'horizontal')
  assert.equal(leerPreferencias(almacenamiento({ sit_menu_mode: 'vertical', th_menu_mode: 'horizontal' })).menuMode, 'vertical')
  assert.equal(leerPreferencias(almacenamiento({ [PREFERENCIAS_KEY]: '{', th_menu_mode: 'horizontal' })).menuMode, 'horizontal')
})

test('lee el formato vigente y descarta valores o versiones desconocidas', () => {
  assert.deepEqual(leerPreferencias(almacenamiento({ [PREFERENCIAS_KEY]: JSON.stringify({ version: 1, menuMode: 'horizontal', fontScale: 125 }) })), { version: 1, menuMode: 'horizontal', fontScale: 125 })
  for (const valor of [null, [], { menuMode: 'overlay', fontScale: 500 }, { fontScale: '125' }]) {
    assert.deepEqual(normalizarPreferencias(valor), PREFERENCIAS_DEFAULT)
  }
  assert.deepEqual(leerPreferencias(almacenamiento({ [PREFERENCIAS_KEY]: JSON.stringify({ version: 99, menuMode: 'horizontal', fontScale: 125 }) })), PREFERENCIAS_DEFAULT)
})

test('guarda solamente preferencias visuales y conserva los otros datos del navegador', () => {
  const local = almacenamiento({ unrelated: 'conservar', token: 'no-tocar' })
  guardarPreferencias({ menuMode: 'horizontal', fontScale: 112.5, token: 'no-serializar', roles: ['ADMINISTRADOR'] }, local)
  assert.deepEqual(JSON.parse(local.getItem(PREFERENCIAS_KEY)), { version: 1, menuMode: 'horizontal', fontScale: 112.5 })
  assert.equal(local.getItem('sit_menu_mode'), 'horizontal')
  guardarPreferencias(PREFERENCIAS_DEFAULT, local)
  assert.equal(local.getItem('unrelated'), 'conservar')
  assert.equal(local.getItem('token'), 'no-tocar')
})

test('el almacenamiento bloqueado no impide leer ni cambiar las preferencias', () => {
  const bloqueado = { getItem() { throw new Error('bloqueado') }, setItem() { throw new Error('bloqueado') } }
  assert.deepEqual(leerPreferencias(bloqueado), PREFERENCIAS_DEFAULT)
  assert.doesNotThrow(() => guardarPreferencias({ menuMode: 'horizontal', fontScale: 125 }, bloqueado))
})

test('los pasos de texto respetan sus límites y se acumulan sin depender del almacenamiento', () => {
  let escala = 100
  assert.equal(siguienteEscala(escala, -1), 100)
  escala = siguienteEscala(escala, 1)
  assert.equal(escala, 112.5)
  escala = siguienteEscala(escala, 1)
  assert.equal(escala, 125)
  assert.equal(siguienteEscala(escala, 1), 125)
  assert.equal(siguienteEscala(escala, -1), 112.5)
})
