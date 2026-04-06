<template>
  <div class="p-6 max-w-5xl mx-auto">
    <h1 class="text-2xl font-bold text-green-900 mb-6">Liquidación / Comisión de Vacaciones</h1>

    <!-- Buscador de empleado -->
    <div class="bg-white rounded-xl shadow p-4 mb-6">
      <label class="block text-sm font-semibold text-gray-700 mb-1">Buscar empleado (cédula o nombre)</label>
      <input
        v-model="busqueda"
        @input="buscarEmpleado"
        type="text"
        placeholder="Ej: 1001967932 o Pérez Juan..."
        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-600"
      />
      <ul v-if="resultados.length" class="mt-2 border border-gray-200 rounded-lg divide-y text-sm">
        <li
          v-for="emp in resultados"
          :key="emp.id_emp"
          @click="seleccionarEmpleado(emp)"
          class="px-4 py-2 hover:bg-green-50 cursor-pointer flex justify-between items-center"
        >
          <span>{{ emp.apellido_emp }}, {{ emp.nombre_emp }} — {{ emp.identificacion }}</span>
          <div class="flex gap-2 items-center">
            <span class="text-xs text-gray-500">{{ emp.modalidad_laboral }}</span>
            <span
              :class="emp.estado === 'ACTIVO' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-700'"
              class="text-xs px-2 py-0.5 rounded-full font-semibold"
            >{{ emp.estado }}</span>
          </div>
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
            <p class="text-sm text-gray-500">{{ empleado.departamento }}</p>
          </div>
          <div class="text-right">
            <span
              :class="empleado.estado === 'ACTIVO' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-700'"
              class="text-sm px-3 py-1 rounded-full font-semibold block mb-1"
            >{{ empleado.estado }}</span>
            <span class="text-xs text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full">{{ empleado.modalidad_laboral || 'Sin modalidad' }}</span>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-2 text-sm text-gray-600">
          <div><span class="font-semibold">Fecha ingreso:</span> {{ fmtFecha(empleado.fecha_ingreso) || '—' }}</div>
          <div><span class="font-semibold">Fecha salida:</span> {{ fmtFecha(empleado.fecha_salida) || '—' }}</div>
        </div>
      </div>

      <!-- Saldo calculado -->
      <div class="bg-white rounded-xl shadow p-5 mb-4" v-if="saldo">
        <h2 class="text-sm font-bold text-gray-700 mb-1 uppercase tracking-wide">Saldo de Vacaciones</h2>
        <p class="text-xs text-gray-400 mb-3">
          Calculado hasta {{ fmtFecha(saldo.fecha_referencia) }}
          <span v-if="empleado.estado !== 'ACTIVO'" class="text-amber-600 font-semibold">(fecha de salida del empleado)</span>
        </p>
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
            <p class="text-xs text-gray-500 mb-1">Saldo Total</p>
            <p class="text-2xl font-bold text-green-700">{{ saldo.saldo_liquidado }}</p>
            <p class="text-xs text-gray-400">días</p>
          </div>
        </div>
      </div>

      <!-- Registrar evento -->
      <div class="bg-white rounded-xl shadow p-5 mb-4">
        <h2 class="text-sm font-bold text-gray-700 mb-4 uppercase tracking-wide">Registrar Evento</h2>

        <!-- Sin modalidad laboral -->
        <div v-if="!motivosDisponibles.length" class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3">
          Este empleado no tiene modalidad laboral asignada. Asígnela en la ficha del empleado para registrar un evento.
        </div>

        <template v-else>
          <div class="grid grid-cols-2 gap-4 mb-4">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Motivo</label>
              <select v-model="form.motivo" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-600">
                <option value="">-- Seleccione --</option>
                <option v-for="m in motivosDisponibles" :key="m" :value="m">{{ labelMotivo(m) }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Fecha del evento</label>
              <input v-model="form.fecha_evento" type="date" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-600" />
            </div>
          </div>

          <!-- Aviso si el estado del empleado no es compatible con el motivo -->
          <div v-if="avisoEstadoIncompatible" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            {{ avisoEstadoIncompatible }}
          </div>

          <!-- Campo días a cargar (solo para motivos de carga de saldo) -->
          <div v-if="requiereCargaSaldo" class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
            <label class="block text-xs font-semibold text-blue-700 mb-1">
              Días de vacaciones según certificado externo
            </label>
            <input
              v-model="form.dias_a_cargar"
              type="number"
              min="0"
              step="0.01"
              placeholder="Ej: 15.50"
              class="w-40 border border-blue-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <p class="text-xs text-blue-600 mt-1">Este valor reemplazará el saldo actual del empleado.</p>
          </div>

          <div class="mb-4">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Observación (opcional)</label>
            <textarea v-model="form.observacion" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-600" placeholder="Ej: Resolución N° 001-2026, acción de personal..."></textarea>
          </div>

          <div class="flex items-center gap-3">
            <button
              @click="registrarEvento"
              :disabled="!form.motivo || !form.fecha_evento || guardando || !!avisoEstadoIncompatible"
              class="bg-green-700 hover:bg-green-800 disabled:opacity-50 text-white text-sm font-semibold px-5 py-2 rounded-lg"
            >
              {{ guardando ? 'Guardando...' : 'Registrar y guardar histórico' }}
            </button>
            <p v-if="ultimoRegistro && ultimoRegistro.genera_certificado" class="text-sm text-green-700">
              ✓ Evento registrado —
              <button @click="descargarPdf(ultimoRegistro.historico.id)" class="underline font-semibold">
                Descargar certificado PDF
              </button>
            </p>
          </div>
          <p v-if="error" class="mt-2 text-red-600 text-xs">{{ error }}</p>
        </template>
      </div>

      <!-- Historial de eventos -->
      <div class="bg-white rounded-xl shadow p-5" v-if="historial.length">
        <h2 class="text-sm font-bold text-gray-700 mb-3 uppercase tracking-wide">Historial de Eventos</h2>
        <table class="w-full text-sm border-collapse">
          <thead>
            <tr class="bg-green-800 text-white text-xs">
              <th class="px-3 py-2 text-left">Fecha</th>
              <th class="px-3 py-2 text-left">Motivo</th>
              <th class="px-3 py-2 text-right">S. Inicial</th>
              <th class="px-3 py-2 text-right">Acumulado</th>
              <th class="px-3 py-2 text-right">Tomados</th>
              <th class="px-3 py-2 text-right font-bold">Saldo</th>
              <th class="px-3 py-2 text-center">PDF</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="h in historial" :key="h.id" class="border-b border-gray-100 hover:bg-gray-50">
              <td class="px-3 py-2">{{ fmtFecha(h.fecha_evento) }}</td>
              <td class="px-3 py-2">
                <span class="px-2 py-0.5 rounded text-xs font-bold text-white" :class="badgeMotivo(h.motivo)">
                  {{ labelMotivo(h.motivo) }}
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
import { ref, computed } from 'vue'
import api from '@/services/api'

const busqueda    = ref('')
const resultados  = ref([])
const empleado    = ref(null)
const saldo       = ref(null)
const historial   = ref([])
const guardando   = ref(false)
const error       = ref('')
const ultimoRegistro = ref(null)
const motivosDisponibles = ref([])
const motivosCargaSaldo  = ref([])

const form = ref({ motivo: '', fecha_evento: '', dias_a_cargar: '', observacion: '' })

const requiereCargaSaldo = computed(() =>
  form.value.motivo && motivosCargaSaldo.value.includes(form.value.motivo)
)

const ESTADO_REQUERIDO = {
  INICIO_COMISION:      'INACTIVO',
  FIN_COMISION_RETORNO: 'INACTIVO',
  COMISION_ENTRANTE:    'ACTIVO',
  FIN_COMISION_SALIDA:  'INACTIVO',
  DESVINCULACION:       'INACTIVO',
}

const avisoEstadoIncompatible = computed(() => {
  if (!form.value.motivo || !empleado.value) return null
  const requerido = ESTADO_REQUERIDO[form.value.motivo]
  if (!requerido) return null
  if (empleado.value.estado !== requerido) {
    if (requerido === 'INACTIVO') {
      return 'Para este motivo el empleado debe estar INACTIVO con fecha de salida registrada en su ficha.'
    } else {
      return 'Para este motivo el empleado debe estar ACTIVO en su ficha.'
    }
  }
  return null
})

const LABELS_MOTIVO = {
  INICIO_COMISION:      'Inicio de comisión de servicios',
  FIN_COMISION_RETORNO: 'Retorno de comisión (regresa a la institución)',
  COMISION_ENTRANTE:    'Comisión de servicios entrante (viene de otra institución)',
  FIN_COMISION_SALIDA:  'Fin de comisión (regresa a su institución de origen)',
  NUEVO_INGRESO:        'Nuevo ingreso',
  DESVINCULACION:       'Desvinculación',
}

function labelMotivo(m) {
  return LABELS_MOTIVO[m] || m.replace(/_/g, ' ')
}

function badgeMotivo(m) {
  return {
    INICIO_COMISION:      'bg-amber-700',
    FIN_COMISION_RETORNO: 'bg-teal-700',
    COMISION_ENTRANTE:    'bg-blue-700',
    FIN_COMISION_SALIDA:  'bg-amber-700',
    NUEVO_INGRESO:        'bg-blue-700',
    DESVINCULACION:       'bg-red-600',
  }[m] || 'bg-gray-500'
}

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
  empleado.value           = data.empleado
  saldo.value              = data.saldo
  historial.value          = data.historial
  motivosDisponibles.value = data.motivos_disponibles
  motivosCargaSaldo.value  = data.motivos_carga_saldo
  error.value              = ''
  ultimoRegistro.value     = null
  form.value               = { motivo: '', fecha_evento: '', dias_a_cargar: '', observacion: '' }
}

async function registrarEvento() {
  error.value      = ''
  guardando.value  = true
  ultimoRegistro.value = null
  try {
    const payload = { ...form.value }
    if (!requiereCargaSaldo.value) delete payload.dias_a_cargar
    const { data } = await api.post(`/liquidacion/${empleado.value.id_emp}/registrar`, payload)
    historial.value.unshift(data.historico)
    saldo.value          = data.saldo
    ultimoRegistro.value = data
    form.value = { motivo: '', fecha_evento: '', dias_a_cargar: '', observacion: '' }
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
  const d = f.split('T')[0].split('-')
  return `${d[2]}/${d[1]}/${d[0]}`
}
</script>
