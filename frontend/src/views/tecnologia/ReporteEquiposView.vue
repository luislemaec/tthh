<template>
  <div>
    <div class="flex flex-wrap justify-between items-center gap-3 mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Reportes de Equipos</h1>
      <div class="flex items-center gap-2">
        <button @click="exportar('excel')" :disabled="exportando"
          class="px-4 py-2 rounded-lg text-sm font-medium border hover:bg-gray-50 disabled:opacity-50"
          style="color:#166534; border-color:#166534;">
          Exportar Excel
        </button>
        <button @click="exportar('pdf')" :disabled="exportando"
          class="px-4 py-2 rounded-lg text-sm font-medium border hover:bg-gray-50 disabled:opacity-50"
          style="color:#b91c1c; border-color:#b91c1c;">
          Exportar PDF
        </button>
      </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow-sm p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
      <div>
        <label class="block text-xs text-gray-500 mb-1">Custodio (empleado)</label>
        <input v-model="busquedaEmp" @input="buscarEmpleado" type="text"
          placeholder="Nombre, apellido o cédula..." class="w-full border rounded-lg px-3 py-2 text-sm" />
        <div v-if="resultadosEmp.length > 0 && !empleadoSeleccionado"
          class="mt-1 border rounded-lg divide-y max-h-40 overflow-y-auto shadow-sm bg-white relative z-10">
          <button v-for="emp in resultadosEmp" :key="emp.id_emp" @click="seleccionarEmpleado(emp)"
            class="w-full text-left px-3 py-2 hover:bg-gray-50 text-xs transition">
            {{ emp.apellido_emp }} {{ emp.nombre_emp }} <span class="text-gray-400">{{ emp.identificacion }}</span>
          </button>
        </div>
        <div v-if="empleadoSeleccionado" class="mt-1 flex items-center justify-between bg-gray-50 border rounded-lg px-2 py-1.5 text-xs">
          <span class="font-medium">{{ empleadoSeleccionado.apellido_emp }} {{ empleadoSeleccionado.nombre_emp }}</span>
          <button @click="limpiarEmpleado" class="text-gray-400 hover:text-gray-600">Quitar</button>
        </div>
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">Marca</label>
        <select v-model="filtros.marca" @change="cargar" class="w-full border rounded-lg px-3 py-2 text-sm">
          <option value="">Todas</option>
          <option v-for="m in filtrosDisponibles.marcas" :key="m" :value="m">{{ m }}</option>
        </select>
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">Modelo</label>
        <select v-model="filtros.modelo" @change="cargar" class="w-full border rounded-lg px-3 py-2 text-sm">
          <option value="">Todos</option>
          <option v-for="m in filtrosDisponibles.modelos" :key="m" :value="m">{{ m }}</option>
        </select>
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">Tipo de equipo</label>
        <select v-model="filtros.tipo_equipo_id" @change="cargar" class="w-full border rounded-lg px-3 py-2 text-sm">
          <option value="">Todos</option>
          <option v-for="t in tipos" :key="t.id" :value="t.id">{{ t.nombre }}</option>
        </select>
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">Vida útil</label>
        <select v-model="filtros.vida_util" @change="cargar" class="w-full border rounded-lg px-3 py-2 text-sm">
          <option value="">Todos</option>
          <option value="vencida">Vencida</option>
          <option value="vigente">Vigente</option>
        </select>
      </div>
    </div>

    <div class="flex justify-end mb-3">
      <span class="text-xs font-semibold px-3 py-1.5 rounded-full" style="background-color:#4d7c8a1a; color:#4d7c8a;">
        {{ equipos.length }} equipo{{ equipos.length === 1 ? '' : 's' }} encontrado{{ equipos.length === 1 ? '' : 's' }}
      </span>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <div v-if="cargando" class="p-10 text-center text-gray-400">Cargando...</div>
      <div v-else-if="!equipos.length" class="p-10 text-center text-gray-400">Sin resultados para estos filtros</div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-white" style="background-color:#4d7c8a;">
              <th class="px-4 py-3">Código</th>
              <th class="px-4 py-3">Equipo</th>
              <th class="px-4 py-3">Serie</th>
              <th class="px-4 py-3">Estado</th>
              <th class="px-4 py-3">Vida Útil</th>
              <th class="px-4 py-3">Custodio</th>
              <th class="px-4 py-3 text-center">Piezas</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="e in equiposPaginados" :key="e.id" class="hover:bg-gray-50 transition">
              <td class="px-4 py-3 font-mono text-xs font-medium text-gray-700">{{ e.codigo_bien }}</td>
              <td class="px-4 py-3">
                <p class="font-medium text-gray-800">{{ e.marca }} {{ e.modelo }}</p>
                <p class="text-xs text-gray-400">{{ e.tipo_equipo?.nombre }}<span v-if="e.descripcion"> · {{ e.descripcion }}</span></p>
              </td>
              <td class="px-4 py-3 text-gray-500 text-xs">{{ e.serie || '—' }}</td>
              <td class="px-4 py-3">
                <span :class="estadoBadge(e.estado)" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ estadoLabel(e.estado) }}</span>
              </td>
              <td class="px-4 py-3">
                <span v-if="e.vida_util_vencida" class="px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700">Vencida</span>
                <span v-else class="px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Vigente</span>
              </td>
              <td class="px-4 py-3 text-xs">
                <span v-if="e.asignacion_activa" class="font-medium" style="color:#4d7c8a;">
                  {{ e.asignacion_activa.empleado?.apellido_emp }} {{ e.asignacion_activa.empleado?.nombre_emp }}
                </span>
                <span v-else class="text-gray-300">—</span>
                <span v-if="e.custodio_inactivo" class="block mt-0.5 text-[10px] font-semibold text-red-700 bg-red-100 px-1.5 py-0.5 rounded w-fit">
                  ⚠ Empleado inactivo
                </span>
              </td>
              <td class="px-4 py-3 text-center">
                <button v-if="e.piezas_instaladas_count > 0" @click="abrirPiezas(e)"
                  class="text-xs font-semibold px-2 py-1 rounded-full hover:opacity-80" style="background-color:#4d7c8a1a; color:#4d7c8a;">
                  🔧 {{ e.piezas_instaladas_count }}
                </button>
                <span v-else class="text-gray-300 text-xs">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Paginación -->
      <div v-if="totalPaginas > 1" class="px-4 py-3 border-t flex items-center justify-center gap-1 text-sm">
        <button @click="pagina = Math.max(1, pagina - 1)" :disabled="pagina === 1" class="px-3 py-1 rounded border disabled:opacity-40 hover:bg-gray-50">‹</button>
        <span class="px-3 font-medium text-gray-600">{{ pagina }} / {{ totalPaginas }}</span>
        <button @click="pagina = Math.min(totalPaginas, pagina + 1)" :disabled="pagina >= totalPaginas" class="px-3 py-1 rounded border disabled:opacity-40 hover:bg-gray-50">›</button>
      </div>
    </div>

    <!-- Modal Piezas Instaladas -->
    <div v-if="modalPiezas.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 flex items-center justify-between" style="background-color:#4d7c8a;">
          <div>
            <h2 class="text-lg font-bold text-white">Piezas Instaladas</h2>
            <p class="text-white/80 text-xs mt-0.5">{{ modalPiezas.equipo?.codigo_bien }}</p>
          </div>
          <button @click="modalPiezas.show = false" class="text-white/80 hover:text-white text-xl leading-none">&times;</button>
        </div>
        <div class="p-6 max-h-[60vh] overflow-y-auto">
          <div v-if="!modalPiezas.datos.length" class="text-center text-gray-400 py-6">Sin piezas instaladas actualmente</div>
          <div v-for="m in modalPiezas.datos.filter(x => !x.fecha_retiro)" :key="m.id" class="border-b py-3 last:border-0">
            <p class="font-medium text-gray-800">{{ m.pieza?.descripcion }}</p>
            <p class="text-xs text-gray-500 mt-0.5">
              {{ m.pieza?.codigo }} <span v-if="m.pieza?.serie"> · Serie: {{ m.pieza.serie }}</span>
            </p>
            <p class="text-xs text-gray-400 mt-0.5">Instalada desde {{ m.fecha_instalacion }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

const equipos    = ref([])
const cargando   = ref(false)
const exportando = ref(false)
const tipos      = ref([])
const filtrosDisponibles = ref({ marcas: [], modelos: [] })

const filtros = ref({ marca: '', modelo: '', tipo_equipo_id: '', vida_util: '' })

const pagina = ref(1)
const porPagina = 30
const totalPaginas = computed(() => Math.max(1, Math.ceil(equipos.value.length / porPagina)))
const equiposPaginados = computed(() => {
  const s = (pagina.value - 1) * porPagina
  return equipos.value.slice(s, s + porPagina)
})

// ─── Custodio (empleado) ────────────────────────────────────────────────────
const busquedaEmp = ref('')
const resultadosEmp = ref([])
const empleadoSeleccionado = ref(null)
let busquedaEmpTimer = null

function buscarEmpleado() {
  if (empleadoSeleccionado.value) return
  clearTimeout(busquedaEmpTimer)
  if (busquedaEmp.value.length < 2) { resultadosEmp.value = []; return }
  busquedaEmpTimer = setTimeout(async () => {
    try {
      const { data } = await api.get('/empleados', { params: { buscar: busquedaEmp.value, per_page: 10 } })
      resultadosEmp.value = data.data ?? data
    } catch { resultadosEmp.value = [] }
  }, 300)
}

function seleccionarEmpleado(emp) {
  empleadoSeleccionado.value = emp
  busquedaEmp.value = `${emp.apellido_emp} ${emp.nombre_emp}`
  resultadosEmp.value = []
  cargar()
}

function limpiarEmpleado() {
  empleadoSeleccionado.value = null
  busquedaEmp.value = ''
  cargar()
}

// ─── Carga de datos ─────────────────────────────────────────────────────────
async function cargarFiltros() {
  const [{ data: f }, { data: t }] = await Promise.all([
    api.get('/tecnologia/reportes/equipos/filtros'),
    api.get('/tecnologia/tipos-equipo/activos'),
  ])
  filtrosDisponibles.value = f
  tipos.value = t
}

function paramsActuales() {
  return {
    id_emp: empleadoSeleccionado.value?.id_emp || undefined,
    marca: filtros.value.marca || undefined,
    modelo: filtros.value.modelo || undefined,
    tipo_equipo_id: filtros.value.tipo_equipo_id || undefined,
    vida_util: filtros.value.vida_util || undefined,
  }
}

async function cargar() {
  cargando.value = true
  pagina.value = 1
  try {
    const { data } = await api.get('/tecnologia/reportes/equipos', { params: paramsActuales() })
    equipos.value = data
  } finally {
    cargando.value = false
  }
}

async function exportar(formato) {
  exportando.value = true
  try {
    const params = { ...paramsActuales(), formato }
    const resp = await api.get('/tecnologia/reportes/equipos', { params, responseType: 'blob' })
    const tipo = formato === 'excel'
      ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
      : 'application/pdf'
    const blob = new Blob([resp.data], { type: tipo })
    const url = URL.createObjectURL(blob)
    if (formato === 'pdf') {
      window.open(url, '_blank')
      setTimeout(() => URL.revokeObjectURL(url), 60000)
    } else {
      const a = document.createElement('a')
      a.href = url
      a.download = `reporte_equipos_${Date.now()}.xlsx`
      a.click()
      setTimeout(() => URL.revokeObjectURL(url), 5000)
    }
  } finally {
    exportando.value = false
  }
}

function estadoBadge(e) {
  if (e === 'DISPONIBLE') return 'bg-green-100 text-green-700'
  if (e === 'ASIGNADO') return 'bg-blue-100 text-blue-700'
  if (e === 'DAÑADO') return 'bg-red-100 text-red-700'
  return 'bg-gray-200 text-gray-600'
}
function estadoLabel(e) {
  const labels = { DISPONIBLE: 'Disponible', ASIGNADO: 'Asignado', 'DAÑADO': 'Dañado', DE_BAJA: 'De baja' }
  return labels[e] || e
}

// ─── Piezas instaladas ──────────────────────────────────────────────────────
const modalPiezas = ref({ show: false, equipo: null, datos: [] })

async function abrirPiezas(e) {
  modalPiezas.value = { show: true, equipo: e, datos: [] }
  const { data } = await api.get(`/tecnologia/equipos/${e.id}/piezas`)
  modalPiezas.value.datos = data
}

onMounted(async () => {
  await cargarFiltros()
  await cargar()
})
</script>
