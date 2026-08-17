<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Reportes</h1>

    <!-- Tabs -->
    <div class="flex border-b overflow-x-auto">
      <button
        v-for="tab in tabs" :key="tab.id"
        @click="cambiarTab(tab.id)"
        :class="tabActivo === tab.id
          ? 'border-b-2 border-[#1e3a5f] text-[#1e3a5f] font-medium'
          : 'text-gray-500 hover:text-gray-700'"
        class="px-5 py-3 text-sm transition whitespace-nowrap">
        {{ tab.label }}
      </button>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow p-4 flex flex-wrap gap-3 items-end">
      <div>
        <label class="block text-xs text-gray-500 mb-1">Fecha Desde</label>
        <input v-model="filtros.fecha_desde" type="date"
          class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1e3a5f]" />
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">Fecha Hasta</label>
        <input v-model="filtros.fecha_hasta" type="date"
          class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1e3a5f]" />
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">Vehículo</label>
        <select v-model="filtros.vehiculo_id"
          class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1e3a5f] min-w-40">
          <option value="">Todos</option>
          <option v-for="v in vehiculos" :key="v.id" :value="v.id">{{ v.placa }} — {{ v.marca }} {{ v.modelo }}</option>
        </select>
      </div>

      <!-- Filtros Vales -->
      <template v-if="tabActivo === 'vales'">
        <div>
          <label class="block text-xs text-gray-500 mb-1">Conductor</label>
          <select v-model="filtros.id_emp_conductor"
            class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1e3a5f] min-w-40">
            <option value="">Todos</option>
            <option v-for="c in conductores" :key="c.id_emp" :value="c.id_emp">{{ c.apellido_emp }} {{ c.nombre_emp }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Estado</label>
          <select v-model="filtros.estado" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1e3a5f]">
            <option value="">Todos</option>
            <option value="EMITIDO">Emitido</option>
            <option value="ANULADO">Anulado</option>
          </select>
        </div>
      </template>

      <!-- Filtros Movilización -->
      <template v-if="tabActivo === 'movilizacion'">
        <div>
          <label class="block text-xs text-gray-500 mb-1">Conductor</label>
          <select v-model="filtros.id_emp_conductor"
            class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1e3a5f] min-w-40">
            <option value="">Todos</option>
            <option v-for="c in conductores" :key="c.id_emp" :value="c.id_emp">{{ c.apellido_emp }} {{ c.nombre_emp }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Solicitante</label>
          <input v-model="filtros.id_emp_solicitante" type="text" placeholder="Nombre, apellido o cédula..."
            class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1e3a5f] w-52" />
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Estado</label>
          <select v-model="filtros.estado" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1e3a5f]">
            <option value="">Todos</option>
            <option value="PENDIENTE">Pendiente</option>
            <option value="APROBADO">Aprobado</option>
            <option value="NEGADO">Negado</option>
            <option value="COMPLETADO">Completado</option>
          </select>
        </div>
      </template>

      <!-- Filtros Mantenimiento -->
      <template v-if="tabActivo === 'mantenimiento'">
        <div>
          <label class="block text-xs text-gray-500 mb-1">Tipo</label>
          <select v-model="filtros.tipo_mantenimiento_id"
            class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1e3a5f] min-w-36">
            <option value="">Todos</option>
            <option v-for="t in tiposMantenimiento" :key="t.id" :value="t.id">{{ t.nombre }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Taller</label>
          <select v-model="filtros.taller_id"
            class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1e3a5f] min-w-40">
            <option value="">Todos</option>
            <option v-for="t in talleres" :key="t.id" :value="t.id">{{ t.nombre }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Estado</label>
          <select v-model="filtros.estado" class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#1e3a5f]">
            <option value="">Todos</option>
            <option value="PENDIENTE">Pendiente</option>
            <option value="ORDEN_GENERADA">Orden generada</option>
            <option value="EN_TALLER">En taller</option>
            <option value="FINALIZADO">Finalizado</option>
            <option value="NEGADO">Negado</option>
          </select>
        </div>
      </template>

      <button @click="buscar" :disabled="cargando"
        class="text-white px-5 py-2 rounded-lg text-sm hover:opacity-90 disabled:opacity-50 font-medium" style="background-color:#1e3a5f;">
        {{ cargando ? "Buscando..." : "Buscar" }}
      </button>
      <button @click="limpiar" class="border rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
        Limpiar
      </button>
      <template v-if="datos.length > 0">
        <button @click="exportar('excel')" :disabled="exportando"
          class="flex items-center gap-1.5 border border-green-600 text-green-700 px-4 py-2 rounded-lg text-sm hover:bg-green-50 disabled:opacity-50">
          Excel
        </button>
        <button @click="exportar('pdf')" :disabled="exportando"
          class="flex items-center gap-1.5 border border-red-400 text-red-600 px-4 py-2 rounded-lg text-sm hover:bg-red-50 disabled:opacity-50">
          PDF
        </button>
      </template>
    </div>

    <div v-if="errorBuscar" class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
      {{ errorBuscar }}
    </div>

    <!-- Resumen -->
    <div v-if="datos.length > 0" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
      <template v-if="tabActivo === 'vales'">
        <div class="bg-white rounded-xl shadow px-4 py-3"><p class="text-xl font-bold text-gray-800">{{ resumen.total_vales }}</p><p class="text-xs text-gray-500">Total vales</p></div>
        <div class="bg-white rounded-xl shadow px-4 py-3"><p class="text-xl font-bold text-red-600">{{ resumen.total_anulados }}</p><p class="text-xs text-gray-500">Anulados</p></div>
        <div class="bg-white rounded-xl shadow px-4 py-3"><p class="text-xl font-bold text-gray-800">{{ resumen.total_glns_extra?.toLocaleString() }}</p><p class="text-xs text-gray-500">Gl. Extra</p></div>
        <div class="bg-white rounded-xl shadow px-4 py-3"><p class="text-xl font-bold text-gray-800">{{ resumen.total_glns_super?.toLocaleString() }}</p><p class="text-xs text-gray-500">Gl. Súper</p></div>
        <div class="bg-white rounded-xl shadow px-4 py-3"><p class="text-xl font-bold text-gray-800">{{ resumen.total_glns_diesel?.toLocaleString() }}</p><p class="text-xs text-gray-500">Gl. Diésel</p></div>
        <div class="rounded-xl shadow px-4 py-3" style="background-color:#eef2f6;"><p class="text-xl font-bold" style="color:#1e3a5f;">${{ resumen.total_valor?.toLocaleString(undefined, {minimumFractionDigits:2}) }}</p><p class="text-xs text-gray-500">Total gastado</p></div>
      </template>
      <template v-else-if="tabActivo === 'movilizacion'">
        <div class="bg-white rounded-xl shadow px-4 py-3"><p class="text-xl font-bold text-gray-800">{{ resumen.total_solicitudes }}</p><p class="text-xs text-gray-500">Total solicitudes</p></div>
        <div class="bg-white rounded-xl shadow px-4 py-3"><p class="text-xl font-bold text-yellow-600">{{ resumen.pendientes }}</p><p class="text-xs text-gray-500">Pendientes</p></div>
        <div class="bg-white rounded-xl shadow px-4 py-3"><p class="text-xl font-bold text-blue-600">{{ resumen.aprobadas }}</p><p class="text-xs text-gray-500">Aprobadas</p></div>
        <div class="bg-white rounded-xl shadow px-4 py-3"><p class="text-xl font-bold text-red-600">{{ resumen.negadas }}</p><p class="text-xs text-gray-500">Negadas</p></div>
        <div class="bg-white rounded-xl shadow px-4 py-3"><p class="text-xl font-bold text-green-600">{{ resumen.completadas }}</p><p class="text-xs text-gray-500">Completadas</p></div>
        <div class="rounded-xl shadow px-4 py-3" style="background-color:#eef2f6;"><p class="text-xl font-bold" style="color:#1e3a5f;">{{ resumen.km_totales?.toLocaleString() }} km</p><p class="text-xs text-gray-500">Recorridos (prom. {{ resumen.promedio_km }})</p></div>
      </template>
      <template v-else-if="tabActivo === 'mantenimiento'">
        <div class="bg-white rounded-xl shadow px-4 py-3"><p class="text-xl font-bold text-gray-800">{{ resumen.total }}</p><p class="text-xs text-gray-500">Total registros</p></div>
        <div class="bg-white rounded-xl shadow px-4 py-3"><p class="text-xl font-bold text-blue-600">{{ resumen.preventivos }}</p><p class="text-xs text-gray-500">Preventivos</p></div>
        <div class="bg-white rounded-xl shadow px-4 py-3"><p class="text-xl font-bold text-orange-600">{{ resumen.correctivos }}</p><p class="text-xs text-gray-500">Correctivos</p></div>
        <div class="bg-white rounded-xl shadow px-4 py-3"><p class="text-xl font-bold text-green-600">{{ resumen.finalizados }}</p><p class="text-xs text-gray-500">Finalizados</p></div>
        <div class="bg-white rounded-xl shadow px-4 py-3"><p class="text-xl font-bold text-amber-600">{{ resumen.en_proceso }}</p><p class="text-xs text-gray-500">En proceso</p></div>
        <div class="bg-white rounded-xl shadow px-4 py-3"><p class="text-xl font-bold text-red-600">{{ resumen.negados }}</p><p class="text-xs text-gray-500">Negados</p></div>
      </template>
    </div>

    <!-- Tabla Vales -->
    <div v-if="tabActivo === 'vales'" class="bg-white rounded-xl shadow overflow-hidden">
      <div class="px-6 py-4 border-b flex items-center justify-between">
        <h2 class="font-semibold text-gray-700">Vales de Combustible</h2>
        <span class="text-sm text-gray-400">{{ datos.length }} registros</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">N°</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Fecha</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Vehículo</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Conductor</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Gasolinera</th>
              <th class="text-center px-4 py-3 text-gray-600 font-medium">Total</th>
              <th class="text-center px-4 py-3 text-gray-600 font-medium">Estado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="cargando"><td colspan="7" class="text-center py-10 text-gray-400">Cargando...</td></tr>
            <tr v-else-if="datos.length === 0"><td colspan="7" class="text-center py-10 text-gray-400">Sin resultados. Seleccione un período y presione Buscar.</td></tr>
            <tr v-for="r in datos" :key="r.id" class="border-b hover:bg-gray-50" :class="r.estado === 'ANULADO' ? 'opacity-50' : ''">
              <td class="px-4 py-3 font-mono text-xs">{{ r.numero }}</td>
              <td class="px-4 py-3 font-mono text-xs whitespace-nowrap">{{ r.fecha?.substring(0,10) }}</td>
              <td class="px-4 py-3">{{ r.placa }} — {{ r.marca }} {{ r.modelo }}</td>
              <td class="px-4 py-3">{{ r.conductor }}</td>
              <td class="px-4 py-3 text-gray-500">{{ r.gasolinera }}</td>
              <td class="px-4 py-3 text-center font-semibold">${{ Number(r.valor_total).toFixed(2) }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="r.estado === 'ANULADO' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ r.estado }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Tabla Movilización -->
    <div v-if="tabActivo === 'movilizacion'" class="bg-white rounded-xl shadow overflow-hidden">
      <div class="px-6 py-4 border-b flex items-center justify-between">
        <h2 class="font-semibold text-gray-700">Movilización</h2>
        <span class="text-sm text-gray-400">{{ datos.length }} registros</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Fecha</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Solicitante</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Vehículo</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Conductor</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Destino</th>
              <th class="text-center px-4 py-3 text-gray-600 font-medium">Km Recorridos</th>
              <th class="text-center px-4 py-3 text-gray-600 font-medium">Estado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="cargando"><td colspan="7" class="text-center py-10 text-gray-400">Cargando...</td></tr>
            <tr v-else-if="datos.length === 0"><td colspan="7" class="text-center py-10 text-gray-400">Sin resultados. Seleccione un período y presione Buscar.</td></tr>
            <tr v-for="r in datos" :key="r.id" class="border-b hover:bg-gray-50">
              <td class="px-4 py-3 font-mono text-xs whitespace-nowrap">{{ r.fecha_movilizacion?.substring(0,10) }}</td>
              <td class="px-4 py-3 font-medium">{{ r.solicitante }}</td>
              <td class="px-4 py-3">{{ r.placa || '—' }}</td>
              <td class="px-4 py-3">{{ r.conductor || '—' }}</td>
              <td class="px-4 py-3 text-gray-500">{{ r.lugar_destino }}</td>
              <td class="px-4 py-3 text-center font-semibold">{{ r.km_recorridos !== null ? r.km_recorridos.toLocaleString() : '—' }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="estadoBadgeMov(r.estado)" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ r.estado }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Tabla Mantenimiento -->
    <div v-if="tabActivo === 'mantenimiento'" class="bg-white rounded-xl shadow overflow-hidden">
      <div class="px-6 py-4 border-b flex items-center justify-between">
        <h2 class="font-semibold text-gray-700">Mantenimiento Vehicular</h2>
        <span class="text-sm text-gray-400">{{ datos.length }} registros</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Fecha</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Vehículo</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Tipo</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Descripción / Plan</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Taller</th>
              <th class="text-center px-4 py-3 text-gray-600 font-medium">Km Actual / Final.</th>
              <th class="text-center px-4 py-3 text-gray-600 font-medium">Estado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="cargando"><td colspan="7" class="text-center py-10 text-gray-400">Cargando...</td></tr>
            <tr v-else-if="datos.length === 0"><td colspan="7" class="text-center py-10 text-gray-400">Sin resultados. Seleccione un período y presione Buscar.</td></tr>
            <tr v-for="r in datos" :key="r.id" class="border-b hover:bg-gray-50">
              <td class="px-4 py-3 font-mono text-xs whitespace-nowrap">{{ r.created_at?.substring(0,10) }}</td>
              <td class="px-4 py-3 font-medium">{{ r.placa }} — {{ r.marca }} {{ r.modelo }}</td>
              <td class="px-4 py-3">{{ r.tipo }}</td>
              <td class="px-4 py-3 text-gray-500">{{ r.plan_nombre || r.descripcion }}</td>
              <td class="px-4 py-3">{{ r.taller || '—' }}</td>
              <td class="px-4 py-3 text-center text-xs">{{ r.km_actual?.toLocaleString() || '—' }} / {{ r.km_finalizacion?.toLocaleString() || '—' }}</td>
              <td class="px-4 py-3 text-center">
                <span :class="estadoBadgeMtto(r.estado)" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ estadoLabelMtto(r.estado) }}</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const tabActivo   = ref('vales')
const datos       = ref([])
const resumen     = ref({})
const cargando    = ref(false)
const exportando  = ref(false)
const errorBuscar = ref('')

const vehiculos          = ref([])
const conductores        = ref([])
const talleres           = ref([])
const tiposMantenimiento = ref([])

const tabs = [
  { id: 'vales',         label: 'Vales de Combustible' },
  { id: 'movilizacion',  label: 'Movilización' },
  { id: 'mantenimiento', label: 'Mantenimiento Vehicular' },
]

const hoy = new Date().toISOString().substring(0, 10)
const primerDiaMes = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().substring(0, 10)

function filtrosVacios() {
  return {
    fecha_desde: primerDiaMes,
    fecha_hasta: hoy,
    vehiculo_id: '',
    id_emp_conductor: '',
    id_emp_solicitante: '',
    tipo_mantenimiento_id: '',
    taller_id: '',
    estado: '',
  }
}

const filtros = ref(filtrosVacios())

function estadoBadgeMov(e) {
  if (e === 'PENDIENTE')  return 'bg-yellow-100 text-yellow-700'
  if (e === 'APROBADO')   return 'bg-blue-100 text-blue-700'
  if (e === 'NEGADO')     return 'bg-red-100 text-red-700'
  return 'bg-green-100 text-green-700'
}

function estadoBadgeMtto(e) {
  if (e === 'PENDIENTE')      return 'bg-yellow-100 text-yellow-700'
  if (e === 'ORDEN_GENERADA') return 'bg-blue-100 text-blue-700'
  if (e === 'EN_TALLER')      return 'bg-orange-100 text-orange-700'
  if (e === 'FINALIZADO')     return 'bg-green-100 text-green-700'
  return 'bg-red-100 text-red-700'
}

function estadoLabelMtto(e) {
  if (e === 'ORDEN_GENERADA') return 'Orden generada'
  if (e === 'EN_TALLER')      return 'En taller'
  return e ? e.charAt(0) + e.slice(1).toLowerCase() : e
}

function endpointActivo() {
  if (tabActivo.value === 'vales')         return '/transporte/reportes/vales-combustible'
  if (tabActivo.value === 'movilizacion')  return '/transporte/reportes/movilizacion'
  return '/transporte/reportes/mantenimiento'
}

function buildParams() {
  const params = {
    fecha_desde: filtros.value.fecha_desde,
    fecha_hasta: filtros.value.fecha_hasta,
  }
  if (filtros.value.vehiculo_id) params.vehiculo_id = filtros.value.vehiculo_id
  if (filtros.value.estado)      params.estado      = filtros.value.estado

  if (tabActivo.value === 'vales' || tabActivo.value === 'movilizacion') {
    if (filtros.value.id_emp_conductor) params.id_emp_conductor = filtros.value.id_emp_conductor
  }
  if (tabActivo.value === 'movilizacion') {
    if (filtros.value.id_emp_solicitante) params.id_emp_solicitante = filtros.value.id_emp_solicitante
  }
  if (tabActivo.value === 'mantenimiento') {
    if (filtros.value.tipo_mantenimiento_id) params.tipo_mantenimiento_id = filtros.value.tipo_mantenimiento_id
    if (filtros.value.taller_id)             params.taller_id             = filtros.value.taller_id
  }
  return params
}

async function buscar() {
  if (!filtros.value.fecha_desde || !filtros.value.fecha_hasta) return
  cargando.value = true
  errorBuscar.value = ''
  datos.value = []
  resumen.value = {}
  try {
    const { data } = await api.get(endpointActivo(), { params: buildParams() })
    datos.value   = data.datos
    resumen.value = data.resumen
  } catch (e) {
    errorBuscar.value = e.response?.data?.message || 'Error al cargar el reporte. Revise los filtros e intente de nuevo.'
  } finally {
    cargando.value = false
  }
}

async function exportar(formato) {
  if (!filtros.value.fecha_desde || !filtros.value.fecha_hasta) return
  exportando.value = true
  try {
    const params = { ...buildParams(), formato }
    const resp = await api.get(endpointActivo(), { params, responseType: 'blob' })
    const ext  = formato === 'excel' ? 'xlsx' : 'pdf'
    const mime = formato === 'excel'
      ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
      : 'application/pdf'
    const blob = new Blob([resp.data], { type: mime })
    const url  = URL.createObjectURL(blob)
    const a    = document.createElement('a')
    a.href     = url
    a.download = `reporte_${tabActivo.value}_${filtros.value.fecha_desde}_${filtros.value.fecha_hasta}.${ext}`
    a.click()
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch (e) {
    alert('Error al generar el archivo')
  } finally {
    exportando.value = false
  }
}

function cambiarTab(id) {
  tabActivo.value = id
  datos.value = []
  resumen.value = {}
  errorBuscar.value = ''
  filtros.value = filtrosVacios()
}

function limpiar() {
  filtros.value = filtrosVacios()
  datos.value = []
  resumen.value = {}
}

onMounted(async () => {
  try {
    const [rVeh, rCond, rTal, rTipos] = await Promise.all([
      api.get('/transporte/vehiculos'),
      api.get('/transporte/conductores'),
      api.get('/transporte/talleres/activos'),
      api.get('/transporte/tipos-mantenimiento'),
    ])
    vehiculos.value          = rVeh.data
    conductores.value        = rCond.data
    talleres.value            = rTal.data
    tiposMantenimiento.value  = rTipos.data
  } catch (e) {}
})
</script>
