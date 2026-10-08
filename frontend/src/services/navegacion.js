export function urlMenu(url) {
  return typeof url === 'string' ? url.replace(/^\/+|\/+$/g, '') : ''
}

export function menuSistema(menu, empleado) {
  const opciones = (menu || []).filter(item => urlMenu(item.url)).map(item => ({ ...item, url: urlMenu(item.url) }))
  // Habilitación individual existente: no depende de un rol o de una opción en BD.
  if (empleado?.puede_solicitar_vehiculo && !opciones.some(item => item.url === 'transporte/movilizacion')) {
    opciones.push({ id: 'solicitud-vehiculo', url: 'transporte/movilizacion', descripcion: 'Solicitud de movilización', categoria: 'TRANSPORTE', orden_categoria: 999, secuencia: 999 })
  }
  opciones.sort((a, b) => Number(a.orden_categoria || 0) - Number(b.orden_categoria || 0) || Number(a.secuencia || 0) - Number(b.secuencia || 0))
  const vistas = new Set()
  return opciones.filter(item => {
    if (vistas.has(item.url)) return false
    vistas.add(item.url)
    return true
  })
}

export function agruparMenu(opciones) {
  return opciones.reduce((grupos, item) => {
    const categoria = item.categoria || 'GENERAL'
    if (!grupos[categoria]) grupos[categoria] = []
    grupos[categoria].push(item)
    return grupos
  }, Object.create(null))
}

export function rutaAutorizada(path, menu, empleado) {
  if (['/dashboard', '/perfil'].includes(path)) return true
  if (path === '/transporte/movilizacion' && empleado?.puede_solicitar_vehiculo) return true
  const segmento = urlMenu(path)
  return (menu || []).some(item => {
    const url = urlMenu(item.url)
    return url && (segmento === url || segmento.startsWith(url + '/'))
  })
}

export function opcionActiva(path, opciones) {
  const segmento = urlMenu(path)
  return opciones.reduce((mejor, item) => {
    const coincide = segmento === item.url || segmento.startsWith(item.url + '/')
    return coincide && item.url.length > (mejor?.url.length || 0) ? item : mejor
  }, null)
}

const MODULOS = { TH: 'Talento Humano', ADQ: 'Adquisiciones', TRANS: 'Transportes', TEC: 'Tecnología', COM: 'Comisiones' }
const TITULOS = {
  EmpleadoCrear: 'Nuevo empleado', EmpleadoEditar: 'Editar empleado', EmpleadoDetalle: 'Detalle del empleado',
  AccionPersonalNueva: 'Nueva acción de personal', HistorialRemuneraciones: 'Historial de remuneraciones',
  EmpleadoImportar: 'Importación de empleados', EmpleadoDistributivo: 'Distributivo de empleados',
  ReporteEmpleados: 'Reporte de empleados', ReporteSinAtrasos: 'Reporte sin atrasos',
  ReportePlanificacion: 'Reporte de planificación', ReporteSaldoVac: 'Reporte de saldo de vacaciones',
  LiquidacionVacaciones: 'Liquidación de vacaciones', AdqDashboard: 'Resumen de adquisiciones',
}

// Se utilizan opciones autorizadas; no se inventan enlaces a módulos o categorías.
export function contextoNavegacion(route, opciones) {
  if (route.path === '/dashboard') return { titulo: 'Inicio', opcion: null, migas: [{ label: 'Inicio' }] }
  if (route.path === '/perfil') return { titulo: 'Mi perfil', opcion: null, migas: [{ label: 'Inicio', to: '/dashboard' }, { label: 'Mi perfil' }] }
  const opcion = opcionActiva(route.path, opciones)
  const esOpcion = opcion?.url === urlMenu(route.path)
  const titulo = (esOpcion && opcion.descripcion) || route.meta?.titulo || TITULOS[route.name] || (route.params?.id ? 'Detalle' : opcion?.descripcion) || 'Página'
  const migas = [{ label: 'Inicio', to: '/dashboard' }]
  if (MODULOS[route.meta?.modulo]) migas.push({ label: MODULOS[route.meta.modulo] })
  if (opcion?.descripcion && !esOpcion && titulo !== opcion.descripcion) migas.push({ label: opcion.descripcion, to: '/' + opcion.url })
  migas.push({ label: titulo })
  return { titulo, opcion, migas }
}

const EXENTOS_MANTENIMIENTO = {
  TH: ['ADMINISTRADOR', 'TALENTO HUMANO'],
  ADQ: ['ADMINISTRADOR', 'ADQUISICIONES'],
  TRANS: ['ADMINISTRADOR', 'TRANSPORTE'],
  TEC: ['ADMINISTRADOR', 'TECNOLOGIA'],
  COM: ['ADMINISTRADOR', 'CONTABILIDAD', 'PRESUPUESTO', 'DIRECTOR FINANCIERO', 'TESORERIA', 'MAXIMA AUTORIDAD', 'DIRECCION ADMINISTRATIVA', 'ASESORIA JURIDICA'],
}

export function puedeTrabajarEnMantenimiento(modulo, roles) {
  return (EXENTOS_MANTENIMIENTO[modulo] || []).some(rol => (roles || []).includes(rol))
}
