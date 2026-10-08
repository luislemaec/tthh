import assert from 'node:assert/strict'
import { test } from 'node:test'
import { menuSistema, agruparMenu, rutaAutorizada, opcionActiva, puedeTrabajarEnMantenimiento, contextoNavegacion } from '../src/services/navegacion.js'

const opciones = [
  { id: 'th', url: 'empleados', categoria: 'PERSONAL', orden_categoria: 1, secuencia: 1 },
  { id: 'adq', url: 'adquisiciones/articulos', categoria: 'INVENTARIO', orden_categoria: 2, secuencia: 1 },
  { id: 'trans', url: 'transporte/vehiculos', categoria: 'TRANSPORTE', orden_categoria: 3, secuencia: 1 },
  { id: 'tec', url: 'tecnologia/equipos', categoria: 'TECNOLOGIA', orden_categoria: 4, secuencia: 1 },
  { id: 'com', url: 'comisiones/solicitudes', categoria: 'COMISIONES', orden_categoria: 5, secuencia: 1 },
]

test('las migas de edición enlazan únicamente al padre autorizado', () => {
  const menu = [{ url: 'empleados', descripcion: 'Empleados' }]
  const contexto = contextoNavegacion({ path: '/empleados/42/editar', name: 'EmpleadoEditar', params: { id: '42' }, meta: { modulo: 'TH' } }, menu)
  assert.equal(contexto.titulo, 'Editar empleado')
  assert.deepEqual(contexto.migas, [{ label: 'Inicio', to: '/dashboard' }, { label: 'Talento Humano' }, { label: 'Empleados', to: '/empleados' }, { label: 'Editar empleado' }])
  assert.equal(rutaAutorizada(contexto.migas[2].to, menu, {}), true)
})

test('la navegación contextual usa la opción más específica y su descripción de BD', () => {
  const menu = [{ url: 'empleados', descripcion: 'Personal' }, { url: 'empleados/reporte', descripcion: 'Reporte institucional' }]
  const contexto = contextoNavegacion({ path: '/empleados/reporte', name: 'ReporteEmpleados', meta: { modulo: 'TH' } }, menu)
  assert.equal(contexto.titulo, 'Reporte institucional')
  assert.equal(contexto.opcion.url, 'empleados/reporte')
  assert.equal(contexto.migas.at(-1).to, undefined)
})

test('inicio y perfil no agregan enlaces a módulos sin opciones', () => {
  assert.deepEqual(contextoNavegacion({ path: '/dashboard' }, []).migas, [{ label: 'Inicio' }])
  assert.deepEqual(contextoNavegacion({ path: '/perfil' }, []).migas, [{ label: 'Inicio', to: '/dashboard' }, { label: 'Mi perfil' }])
})

test('la habilitación individual de vehículo conserva su contexto sin inventar una ruta padre', () => {
  const menu = menuSistema([], { puede_solicitar_vehiculo: true })
  const contexto = contextoNavegacion({ path: '/transporte/movilizacion', meta: { modulo: 'TRANS' } }, menu)
  assert.equal(contexto.titulo, 'Solicitud de movilización')
  assert.deepEqual(contexto.migas.filter(item => item.to).map(item => item.to), ['/dashboard'])
})

test('los accesos de varios roles y módulos conviven sin duplicados', () => {
  const resultado = menuSistema([...opciones.toReversed(), { ...opciones[0], id: 'otro-rol' }], {})
  assert.deepEqual(resultado.map(item => item.url), opciones.map(item => item.url))
  assert.deepEqual(Object.keys(agruparMenu(resultado)), opciones.map(item => item.categoria))
})

test('el usuario de un solo módulo no recibe accesos de los demás', () => {
  const menu = menuSistema([opciones[1]], {})
  assert.equal(rutaAutorizada('/adquisiciones/articulos', menu, {}), true)
  assert.equal(rutaAutorizada('/adquisiciones/articulos/123', menu, {}), true)
  assert.equal(rutaAutorizada('/adquisiciones/articulos-privados', menu, {}), false)
  for (const opcion of opciones.filter(item => item.id !== 'adq')) {
    assert.equal(rutaAutorizada('/' + opcion.url, menu, {}), false)
  }
})

test('sin opciones se permite únicamente inicio y perfil', () => {
  assert.deepEqual(menuSistema([], {}), [])
  assert.equal(rutaAutorizada('/dashboard', [], {}), true)
  assert.equal(rutaAutorizada('/perfil', [], {}), true)
  assert.equal(rutaAutorizada('/empleados', [], {}), false)
  assert.equal(rutaAutorizada('/admin/roles', [], {}), false)
})

test('la habilitación individual de vehículo agrega solo movilización', () => {
  const empleado = { puede_solicitar_vehiculo: true }
  const menu = menuSistema([], empleado)
  assert.equal(menu.length, 1)
  assert.equal(menu[0].url, 'transporte/movilizacion')
  assert.equal(rutaAutorizada('/transporte/movilizacion', [], empleado), true)
  assert.equal(rutaAutorizada('/transporte/vehiculos', menu, empleado), false)
  assert.equal(menuSistema(menu, empleado).length, 1)
})

test('normaliza URLs y descarta opciones vacías sin modificar los datos recibidos', () => {
  const datos = [{ url: '/empleados/', categoria: 'PERSONAL' }, { url: 'empleados' }, { url: null }, { url: '' }]
  const menu = menuSistema(datos, {})
  assert.equal(menu.length, 1)
  assert.equal(menu[0].url, 'empleados')
  assert.equal(datos[0].url, '/empleados/')
  assert.equal(rutaAutorizada('/empleados/12', datos, {}), true)
  assert.equal(rutaAutorizada('/admin/roles', [{ url: '' }], {}), false)
})

test('la opción activa es la más específica entre padre y subruta', () => {
  const menu = [...opciones, { url: 'empleados/reporte' }]
  assert.equal(opcionActiva('/empleados/reporte', menu).url, 'empleados/reporte')
  assert.equal(opcionActiva('/empleados/12/editar', menu).url, 'empleados')
  assert.equal(opcionActiva('/perfil', menu), null)
})

test('el mantenimiento conserva las excepciones de cada sección', () => {
  assert.equal(puedeTrabajarEnMantenimiento('TH', ['TALENTO HUMANO']), true)
  assert.equal(puedeTrabajarEnMantenimiento('ADQ', ['TALENTO HUMANO']), false)
  assert.equal(puedeTrabajarEnMantenimiento('ADQ', ['ADQUISICIONES']), true)
  assert.equal(puedeTrabajarEnMantenimiento('TRANS', ['TRANSPORTE']), true)
  assert.equal(puedeTrabajarEnMantenimiento('TEC', ['TECNOLOGIA']), true)
  assert.equal(puedeTrabajarEnMantenimiento('COM', ['CONTABILIDAD']), true)
  for (const modulo of ['TH', 'ADQ', 'TRANS', 'TEC', 'COM']) {
    assert.equal(puedeTrabajarEnMantenimiento(modulo, ['ADMINISTRADOR']), true)
    assert.equal(puedeTrabajarEnMantenimiento(modulo, ['EMPLEADO']), false)
  }
})
