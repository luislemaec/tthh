<template>
  <div class="p-6 max-w-5xl mx-auto">
    <h1 class="text-2xl font-bold text-green-900 mb-6">Liquidación / Comisión de Vacaciones</h1>

    <!-- Buscador de empleado -->
    <div class="bg-white rounded-xl shadow p-4 mb-6">
      <label class="block text-sm font-semibold text-gray-700 mb-1">Buscar empleado (cédula o nombre)</label>
      <div class="flex gap-2">
        <input
          v-model="busqueda"
          @input="buscarEmpleado"
          type="text"
          placeholder="Ej: 1001967932 o Pérez Juan..."
          class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-600"
        />
      </div>
      <!-- Resultados de búsqueda -->
      <ul v-if="resultados.length" class="mt-2 border border-gray-200 rounded-lg divide-y text-sm">
        <li
          v-for="emp in resultados"
          :key="emp.id_emp"
          @click="seleccionarEmpleado(emp)"
          class="px-4 py-2 hover:bg-green-50 cursor-pointer flex justify-between items-center"
        >
          <span>{{ emp.apellido_emp }}, {{ emp.nombre_emp }} — {{ emp.identificacion }}</span>
          <span
            :class="emp.estado === 'ACTIVO' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-700'"
            class="text-xs px-2 py-0.5 rounded-full font-semibold"
          >{{ emp.estado }}</span>
        </li>
      </ul>
    </div>

    <!-- Panel del empleado seleccionado -->
    <template v-if="empleado">
      <!-- Info empleado -->
      <div class="bg-white rounded-xl shadow p-5 mb-4">
        <div class="flex items-start justify-between mb-3">
          <div>
            <p class="text-lg font-bold text-gray-800">{{ empleado.nombre }}</p>
            <p class="text-sm text-gray-500">{{ empleado.identificacion }} · {{ empleado.tipo_contrato }}</p>
          </div>
          <span
            :class="empleado.estado === 'ACTIVO' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-700'"
            class="text-sm px-3 py-1 rounded-full font-semibold"
          >{{ empleado.estado }}</span>
        </div>
        <div class="grid grid-cols-2 gap-2 text-sm text-gray-600">
          <div><span class="font-semibold">Ingreso:</span> {{ fmtFecha(empleado.fecha_ingreso) }}</div>
          <div><span class="font-semibold">Salida:</span> {{ fmtFecha(empleado.fecha_salida) || '—' }}</div>
          <div v-if="empleado.motivo_inactividad">
            <span class="font-semibold">Último motivo:</span>
            <span class="ml-1 px-2 py-0.5 rounded text-xs font-bold text-white" :class="badgeMotivo(empleado.motivo_inactividad)">
              {{ empleado.motivo_inactividad.replace('_', ' ') }}
            </span>
          </div>
        </div>
      </div>

      <!-- Saldo calculado -->
      <div class="bg-white rounded-xl shadow p-5 mb-4" v-if="saldo">
        <h2 class="text-sm font-bold text-gray-700 mb-3 uppercase tracking-wide">
          Saldo de Vacaciones
          <span class="text-xs text-gray-400 font-normal ml-1">(calculado hasta {{ fmtFecha(saldo.fecha_referencia) }})</span>
        </h2>
        <div class="grid grid-cols-4 gap-3 text-center">
          <div class="bg-blue-50 rounded-lg p-3">
            <p class="text-xs text-gray-500 mb-1">Saldo Inicial</p>
            <p class="text-xl font-bold text-blue-700">{{ saldo.saldo_inicial }}</p>
            <p class="text-xs text-gray-400">días</p>
          </div>
          <div class="bg-teal-50 rounded-lg p-3">
            <p class="text-xs text-gray-500 mb-1">Acumulado</p>
            <p class="text-xl font-bold text-teal-700">{{ saldo.acumulado }}</p>
            <p class="text-xs text-gray-400">días</p>
          </div>
          <div class="bg-amber-50 rounded-lg p-3">
            <p class="text-xs text-gray-500 mb-1">Tomados</p>
            <p class="text-xl font-bold text-amber-700">{{ saldo.tomados }}</p>
            <p class="text-xs text-gray-400">días</p>
          </div>
          <div class="bg-green-50 rounded-lg p-3 border-2 border-green-300">
            <p class="text-xs text-gray-500 mb-1">Saldo a Liquidar</p>
            <p class="text-2xl font-bold text-green-700">{{ saldo.saldo_liquidado }}</p>
            <p class="text-xs text-gray-400">días</p>
          </div>
        </div>
      </div>

      <!-- Registrar evento -->
      <div class="bg-white rounded-xl shadow p-5 mb-4">
        <h2 class="text-sm font-bold text-gray-700 mb-4 uppercase tracking-wide">Registrar Evento</h2>
        <div class="grid grid-cols-2 gap-4 mb-4">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Motivo</label>
            <select v-model="form.motivo" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-600">
              <option value="">-- Seleccione --</option>
              <option value="DESVINCULACION">Desvinculación</option>
              <option value="COMISION_SALIDA">Comisión de Servicios (Salida)</option>
              <option value="COMISION_RETORNO">Retorno de Comisión</option>
              <option value="NUEVO_INGRESO">Nuevo Ingreso</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Fecha del evento</label>
            <input v-model="form.fecha_evento" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-600" />
          </div>
        </div>
        <div class="mb-4">
          <label class="block text-xs font-semibold text-gray-600 mb-1">Observación (opcional)</label>
          <textarea v-model="form.observacion" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-600" placeholder="Ej: Resolución N° 001-2026..."></textarea>
        </div>
        <button
          @click="registrarEvento"
          :disabled="!form.motivo || !form.fecha_evento || guardando"
          class="bg-green-700 hover:bg-green-800 disabled:opacity-50 text-white text-sm font-semibold px-5 py-2 rounded-lg"
        >
          {{ guardando ? 'Guardando...' : 'Registrar y guardar histórico' }}
        </button>
        <p v-if="error" class="mt-2 text-red-600 text-xs">{{ error }}</p>
      </div>

      <!-- Historial de eventos -->
      <div class="bg-white rounded-xl shadow p-5" v-if="historial.length">
        <h2 class="text-sm font-bold text-gray-700 mb-3 uppercase tracking-wide">Historial de Eventos</h2>
        <table class="w-full text-sm border-collapse">
          <thead>
            <tr class="bg-green-800 text-white text-xs">
              <th class="px-3 py-2 text-left">Fecha</th>
              <th class="px-3 py-2 text-left">Motivo</th>
              <th class="px-3 py-2 text-right">Saldo Inicial</th>
              <th class="px-3 py-2 text-right">Acumulado</th>
              <th class="px-3 py-2 text-right">Tomados</th>
              <th class="px-3 py-2 text-right font-bold">Liquidado</th>
              <th class="px-3 py-2 text-center">PDF</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="h in historial" :key="h.id" class="border-b border-gray-100 hover:bg-gray-50">
              <td class="px-3 py-2">{{ fmtFecha(h.fecha_evento) }}</td>
              <td class="px-3 py-2">
                <span class="px-2 py-0.5 rounded text-xs font-bold text-white" :class="badgeMotivo(h.motivo)">
                  {{ h.motivo.replace('_', ' ') }}
                </span>
              </td>
              <td class="px-3 py-2 text-right">{{ h.saldo_inicial }}</td>
              <td class="px-3 py-2 text-right">{{ h.acumulado }}</td>
              <td class="px-3 py-2 text-right">{{ h.tomados }}</td>
              <td class="px-3 py-2 text-right font-bold text-green-700">{{ h.saldo_liquidado }}</td>
              <td class="px-3 py-2 text-center">
                <button @click="descargarPdf(h.id)" class="text-blue-600 hover:underline text-xs">Descargar</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import api from '@/services/api'

const busqueda  = ref('')
const resultados = ref([])
const empleado  = ref(null)
const saldo     = ref(null)
const historial = ref([])
const guardando = ref(false)
const error     = ref('')

const form = ref({ motivo: '', fecha_evento: '', observacion: '' })

let busquedaTimer = null
function buscarEmpleado() {
  clearTimeout(busquedaTimer)
  if (busqueda.value.length < 2) { resultados.value = []; return }
  busquedaTimer = setTimeout(async () => {
    const { data } = await api.get('/liquidacion/buscar', { params: { q: busqueda.value } })
    resultados.value = data
  }, 300)
}

async function seleccionarEmpleado(emp) {
  resultados.value = []
  busqueda.value   = `${emp.apellido_emp}, ${emp.nombre_emp}`
  const { data }   = await api.get(`/liquidacion/${emp.id_emp}`)
  empleado.value   = data.empleado
  saldo.value      = data.saldo
  historial.value  = data.historial
  error.value      = ''
  form.value       = { motivo: '', fecha_evento: '', observacion: '' }
}

async function registrarEvento() {
  error.value   = ''
  guardando.value = true
  try {
    const { data } = await api.post(`/liquidacion/${empleado.value.id_emp}/registrar`, form.value)
    historial.value.unshift(data.historico)
    saldo.value     = data.saldo
    empleado.value.motivo_inactividad = form.value.motivo
    form.value = { motivo: '', fecha_evento: '', observacion: '' }
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al registrar el evento'
  } finally {
    guardando.value = false
  }
}

async function descargarPdf(historicoId) {
  const resp = await api.get(`/liquidacion/certificado/${historicoId}`, { responseType: 'blob' })
  const url  = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
  const a    = document.createElement('a')
  a.href     = url
  a.download = `liquidacion_${historicoId}.pdf`
  a.click()
  URL.revokeObjectURL(url)
}

function fmtFecha(f) {
  if (!f) return null
  const [y, m, d] = f.split('T')[0].split('-')
  return `${d}/${m}/${y}`
}

function badgeMotivo(motivo) {
  return {
    'DESVINCULACION':   'bg-red-600',
    'COMISION_SALIDA':  'bg-amber-700',
    'COMISION_RETORNO': 'bg-teal-700',
    'NUEVO_INGRESO':    'bg-blue-700',
  }[motivo] || 'bg-gray-500'
}
</script>
