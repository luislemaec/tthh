<template>
  <div class="space-y-5">
    <h1 class="text-2xl font-bold text-gray-800">Reporte Historial de Cargos y Remuneraciones</h1>

    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow p-4 space-y-3">
      <div class="flex flex-wrap gap-3 items-end">

        <!-- Buscar empleado -->
        <div class="relative">
          <label class="block text-xs text-gray-500 mb-1">Empleado</label>
          <input v-model="buscar" @input="onBuscar" placeholder="Nombre o cédula..."
            class="border rounded-lg px-3 py-2 text-sm w-60 focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          <div v-if="sugerencias.length"
            class="absolute z-10 w-full mt-1 bg-white border rounded-lg shadow-lg max-h-52 overflow-y-auto">
            <button v-for="e in sugerencias" :key="e.id_emp" @click="seleccionarEmp(e)"
              class="w-full text-left px-3 py-2 hover:bg-gray-50 border-b last:border-0 text-sm">
              <div class="font-medium">{{ e.apellido_emp }}, {{ e.nombre_emp }}</div>
              <div class="text-xs text-gray-400">{{ e.identificacion }}
                <span :class="e.estado === 'INACTIVO' ? 'text-red-500' : 'text-green-600'">· {{ e.estado }}</span>
              </div>
            </button>
          </div>
        </div>

        <!-- Fecha desde -->
        <div>
          <label class="block text-xs text-gray-500 mb-1">Fecha desde</label>
          <input type="date" v-model="fechaDesde"
            class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
        </div>

        <!-- Fecha hasta -->
        <div>
          <label class="block text-xs text-gray-500 mb-1">Fecha hasta</label>
          <input type="date" v-model="fechaHasta"
            class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
        </div>

        <button @click="consultar" :disabled="cargando"
          class="bg-[#0b5447] text-white px-5 py-2 rounded-lg text-sm hover:bg-[#00372e] disabled:opacity-50">
          {{ cargando ? 'Cargando...' : 'Consultar' }}
        </button>

        <template v-if="filas.length">
          <button @click="exportar('pdf')" :disabled="exportando === 'pdf'"
            class="border border-red-600 text-red-600 px-4 py-2 rounded-lg text-sm hover:bg-red-50 disabled:opacity-50">
            {{ exportando === 'pdf' ? 'Generando...' : 'PDF' }}
          </button>
          <button @click="exportar('excel')" :disabled="exportando === 'excel'"
            class="border border-green-700 text-green-700 px-4 py-2 rounded-lg text-sm hover:bg-green-50 disabled:opacity-50">
            {{ exportando === 'excel' ? 'Generando...' : 'Excel' }}
          </button>
        </template>
      </div>

      <!-- Tipos de acción -->
      <div class="flex flex-wrap gap-3 items-center pt-1">
        <span class="text-xs text-gray-500 font-medium">Tipos:</span>
        <label v-for="t in tiposDisponibles" :key="t.value" class="flex items-center gap-1.5 cursor-pointer">
          <input type="checkbox" v-model="tiposSeleccionados" :value="t.value" class="accent-[#0b5447]" />
          <span :class="t.badge" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ t.label }}</span>
        </label>
      </div>
    </div>

    <!-- Tabla -->
    <div v-if="filas.length" class="bg-white rounded-xl shadow overflow-x-auto">
      <table class="w-full text-sm min-w-[900px]">
        <thead class="bg-[#0b5447] text-white">
          <tr>
            <th class="px-3 py-2 text-left font-medium">N° Acción</th>
            <th class="px-3 py-2 text-left font-medium">Empleado</th>
            <th class="px-3 py-2 text-center font-medium">Tipo</th>
            <th class="px-3 py-2 text-center font-medium">Fecha Inicio</th>
            <th class="px-3 py-2 text-center font-medium">Fecha Fin</th>
            <th class="px-3 py-2 text-left font-medium">Cargo</th>
            <th class="px-3 py-2 text-right font-medium">Rem. en acción</th>
            <th class="px-3 py-2 text-right font-medium">Sueldo actual</th>
            <th class="px-3 py-2 text-right font-medium">Diferencia</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(f, i) in filas" :key="f.id_accion"
            class="border-b hover:bg-gray-50" :class="i % 2 === 0 ? '' : 'bg-gray-50/50'">
            <td class="px-3 py-2 font-mono text-xs text-gray-500">{{ f.numero_accion ?? '—' }}</td>
            <td class="px-3 py-2 font-medium text-gray-800">{{ f.empleado?.nombre_completo ?? '—' }}</td>
            <td class="px-3 py-2 text-center">
              <span :class="badgeTipo(f.tipo_accion)" class="px-2 py-0.5 rounded-full text-xs font-medium">
                {{ f.tipo_accion }}
              </span>
            </td>
            <td class="px-3 py-2 text-center text-gray-600">{{ fmtFecha(f.fecha_inicio) }}</td>
            <td class="px-3 py-2 text-center text-gray-600">
              {{ f.fecha_fin ? fmtFecha(f.fecha_fin) : 'Vigente' }}
            </td>
            <td class="px-3 py-2 text-gray-700 text-xs">{{ f.cargo ?? '—' }}</td>
            <td class="px-3 py-2 text-right font-semibold text-gray-700">
              {{ f.remuneracion != null ? fmtMonto(f.remuneracion) : '—' }}
            </td>
            <td class="px-3 py-2 text-right text-gray-600">{{ fmtMonto(f.sueldo_actual) }}</td>
            <td class="px-3 py-2 text-right font-bold" :class="classDif(f.diferencia)">
              {{ fmtDif(f.diferencia) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-else-if="consultado && !cargando"
      class="bg-white rounded-xl shadow p-8 text-center text-gray-400 text-sm">
      Sin registros para los filtros aplicados.
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import api from '@/services/api'

const buscar            = ref('')
const idEmpSeleccionado = ref(null)
const sugerencias       = ref([])
const fechaDesde        = ref('')
const fechaHasta        = ref('')
const tiposSeleccionados = ref(['INGRESO', 'ENCARGO', 'SUBROGACION', 'CESACION DE FUNCIONES', 'DESTITUCION'])
const filas             = ref([])
const cargando          = ref(false)
const exportando        = ref(null)
const consultado        = ref(false)

const tiposDisponibles = [
  { value: 'INGRESO',              label: 'INGRESO',              badge: 'bg-green-100 text-green-800' },
  { value: 'ENCARGO',              label: 'ENCARGO',              badge: 'bg-blue-100 text-blue-800'  },
  { value: 'SUBROGACION',          label: 'SUBROGACIÓN',          badge: 'bg-purple-100 text-purple-800' },
  { value: 'CESACION DE FUNCIONES', label: 'CESACIÓN',           badge: 'bg-yellow-100 text-yellow-800' },
  { value: 'DESTITUCION',          label: 'DESTITUCIÓN',          badge: 'bg-red-100 text-red-800'    },
]

let buscarTimer = null

function onBuscar() {
  clearTimeout(buscarTimer)
  idEmpSeleccionado.value = null
  sugerencias.value = []
  const q = buscar.value.trim()
  if (q.length < 2) return
  buscarTimer = setTimeout(async () => {
    try {
      const { data } = await api.get('/empleados', { params: { buscar: q, per_page: 8 } })
      sugerencias.value = (data.data ?? []).filter(e => parseInt(e.id_depto) !== 999)
    } catch {}
  }, 300)
}

function seleccionarEmp(emp) {
  idEmpSeleccionado.value = emp.id_emp
  buscar.value = `${emp.apellido_emp}, ${emp.nombre_emp}`
  sugerencias.value = []
}

function buildParams(formato = null) {
  const p = {}
  if (idEmpSeleccionado.value)   p.id_emp      = idEmpSeleccionado.value
  else if (buscar.value.trim())  p.buscar       = buscar.value.trim()
  if (fechaDesde.value)          p.fecha_desde  = fechaDesde.value
  if (fechaHasta.value)          p.fecha_hasta  = fechaHasta.value
  if (tiposSeleccionados.value.length < 5)
    p.tipos = tiposSeleccionados.value.join(',')
  if (formato) p.formato = formato
  return p
}

async function consultar() {
  cargando.value  = true
  consultado.value = false
  filas.value     = []
  try {
    const { data } = await api.get('/acciones-personal/historial-remuneraciones', { params: buildParams() })
    filas.value     = data
    consultado.value = true
  } catch { alert('Error al cargar el historial') }
  finally { cargando.value = false }
}

async function exportar(formato) {
  exportando.value = formato
  try {
    const resp = await api.get('/acciones-personal/historial-remuneraciones', {
      params: buildParams(formato),
      responseType: 'blob',
    })
    const ext  = formato === 'pdf' ? 'pdf' : 'xlsx'
    const mime = formato === 'pdf'
      ? 'application/pdf'
      : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    const url  = URL.createObjectURL(new Blob([resp.data], { type: mime }))
    const a    = document.createElement('a')
    a.href     = url
    a.download = `historial_remuneraciones.${ext}`
    a.click()
    URL.revokeObjectURL(url)
  } catch { alert('Error al exportar') }
  finally { exportando.value = null }
}

function fmtFecha(d) {
  if (!d) return '—'
  const [y, m, day] = String(d).substring(0, 10).split('-')
  return `${day}/${m}/${y}`
}

function fmtMonto(v) {
  return '$' + Number(v).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function fmtDif(v) {
  const n = Number(v)
  return (n >= 0 ? '+' : '') + fmtMonto(Math.abs(n)).replace('$', (n >= 0 ? '+$' : '-$')).replace('++', '+').replace('+-', '-')
}

function classDif(v) {
  const n = Number(v)
  return n > 0 ? 'text-green-700' : n < 0 ? 'text-red-600' : 'text-gray-400'
}

function badgeTipo(tipo) {
  const t = tipo?.toUpperCase() ?? ''
  if (t.includes('INGRESO'))     return 'bg-green-100 text-green-800'
  if (t.includes('ENCARGO'))     return 'bg-blue-100 text-blue-800'
  if (t.includes('SUBROGA'))     return 'bg-purple-100 text-purple-800'
  if (t.includes('CESACION'))    return 'bg-yellow-100 text-yellow-800'
  if (t.includes('DESTITUCION')) return 'bg-red-100 text-red-800'
  return 'bg-gray-100 text-gray-600'
}
</script>
