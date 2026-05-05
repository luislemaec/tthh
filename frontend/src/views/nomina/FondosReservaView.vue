<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Fondos de Reserva</h1>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4 text-sm text-blue-800">
      Solo se incluyen servidores con <strong>≥ 1 año de servicio</strong> y con el campo "Acumula Fondos de Reserva" activo.
      El porcentaje es <strong>8.33%</strong> del sueldo base proporcional a los días trabajados en el mes.
    </div>

    <!-- Controles período -->
    <div class="bg-white rounded-xl shadow p-5 mb-5">
      <div class="flex flex-wrap gap-3 items-end">
        <div>
          <label class="block text-xs text-gray-600 mb-1">Mes</label>
          <select v-model.number="form.mes" class="border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 outline-none">
            <option v-for="m in meses" :key="m.v" :value="m.v">{{ m.l }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-600 mb-1">Año</label>
          <select v-model.number="form.anio" class="border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 outline-none">
            <option v-for="a in anios" :key="a" :value="a">{{ a }}</option>
          </select>
        </div>
        <button @click="cargar" :disabled="cargando"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 disabled:opacity-50">
          {{ cargando ? 'Cargando...' : 'Buscar' }}
        </button>
        <button @click="calcular" :disabled="calculando || estadoPeriodo === 'CERRADO'"
          class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50">
          {{ calculando ? 'Calculando...' : (registros.length ? 'Recalcular' : 'Calcular') }}
        </button>
        <button v-if="estadoPeriodo === 'BORRADOR'" @click="cerrar" :disabled="cerrando"
          class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 disabled:opacity-50">
          {{ cerrando ? 'Cerrando...' : 'Cerrar Período' }}
        </button>
        <button v-if="registros.length" @click="descargarPdf"
          class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700">
          Generar PDF
        </button>

        <div>
          <label class="block text-xs text-gray-600 mb-1">Tipo</label>
          <select v-model="filtroTipo" class="border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 outline-none">
            <option value="">Todos</option>
            <option value="MENSUAL">Mensual</option>
            <option value="IESS">IESS</option>
          </select>
        </div>

        <span v-if="estadoPeriodo" class="ml-2 px-3 py-1 rounded-full text-xs font-bold"
          :class="estadoPeriodo === 'CERRADO' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'">
          {{ estadoPeriodo }}
        </span>
        <span v-else-if="buscado && !registros.length"
          class="ml-2 px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-600">Sin datos</span>
      </div>
      <p v-if="error" class="text-red-600 text-sm mt-3">{{ error }}</p>
    </div>

    <!-- Tabla de resultados -->
    <div v-if="registros.length" class="bg-white rounded-xl shadow overflow-hidden">
      <div class="px-5 py-3 border-b flex justify-between items-center text-sm text-gray-600">
        <span>{{ registrosFiltrados.length }} servidores{{ filtroTipo ? ` (${filtroTipo})` : '' }}</span>
        <span class="font-semibold text-gray-800">Total: ${{ fmt(registrosFiltrados.reduce((s,r) => s + parseFloat(r.valor), 0)) }}</span>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">N°</th>
              <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Apellidos y Nombres</th>
              <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Cédula</th>
              <th class="px-3 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Departamento</th>
              <th class="px-3 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Sueldo</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Días</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase">%</th>
              <th class="px-3 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Tipo</th>
              <th class="px-3 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Valor</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="(r, i) in registrosFiltrados" :key="r.id" class="hover:bg-gray-50">
              <td class="px-3 py-2 text-gray-500">{{ i + 1 }}</td>
              <td class="px-3 py-2 font-medium text-gray-800">{{ nombreCompleto(r.empleado) }}</td>
              <td class="px-3 py-2 text-gray-600 font-mono">{{ r.empleado?.identificacion }}</td>
              <td class="px-3 py-2 text-gray-600">{{ r.empleado?.departamento?.nombre_depto }}</td>
              <td class="px-3 py-2 text-right font-mono">${{ fmt(r.sueldo_base) }}</td>
              <td class="px-3 py-2 text-center text-gray-600">{{ r.dias }}</td>
              <td class="px-3 py-2 text-center text-gray-600">{{ r.porcentaje }}%</td>
              <td class="px-3 py-2 text-center">
                <span class="px-2 py-0.5 rounded text-xs font-semibold"
                  :class="r.tipo === 'MENSUAL' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'">
                  {{ r.tipo }}
                </span>
              </td>
              <td class="px-3 py-2 text-right font-mono font-semibold text-blue-700">${{ fmt(r.valor) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="bg-gray-100 font-bold">
              <td colspan="6" class="px-3 py-2 text-right text-gray-700">TOTAL</td>
              <td></td>
              <td></td>
              <td class="px-3 py-2 text-right font-mono text-blue-800">${{ fmt(registrosFiltrados.reduce((s,r) => s + parseFloat(r.valor), 0)) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- Modales -->
    <div v-if="modalConfirm" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl p-6 max-w-sm w-full mx-4">
        <h3 class="font-bold text-gray-800 mb-3">¿Recalcular período?</h3>
        <p class="text-sm text-gray-600 mb-5">
          Ya existen datos en BORRADOR para <strong>{{ meses[form.mes - 1]?.l }} {{ form.anio }}</strong>.
          Al recalcular se eliminarán los registros actuales.
        </p>
        <div class="flex gap-3 justify-end">
          <button @click="modalConfirm = false" class="px-4 py-2 border rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancelar</button>
          <button @click="confirmarCalculo" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700">Sí, recalcular</button>
        </div>
      </div>
    </div>

    <div v-if="modalCerrar" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl p-6 max-w-sm w-full mx-4">
        <h3 class="font-bold text-gray-800 mb-3">¿Cerrar período?</h3>
        <p class="text-sm text-gray-600 mb-5">
          Al cerrar no se podrá recalcular. Esta acción queda registrada en auditoría.
        </p>
        <div class="flex gap-3 justify-end">
          <button @click="modalCerrar = false" class="px-4 py-2 border rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancelar</button>
          <button @click="confirmarCierre" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700">Sí, cerrar</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import api from '@/services/api'

const meses = [
  { v: 1, l: 'Enero' }, { v: 2, l: 'Febrero' }, { v: 3, l: 'Marzo' },
  { v: 4, l: 'Abril' }, { v: 5, l: 'Mayo' }, { v: 6, l: 'Junio' },
  { v: 7, l: 'Julio' }, { v: 8, l: 'Agosto' }, { v: 9, l: 'Septiembre' },
  { v: 10, l: 'Octubre' }, { v: 11, l: 'Noviembre' }, { v: 12, l: 'Diciembre' },
]

const anioActual = new Date().getFullYear()
const anios = Array.from({ length: 5 }, (_, i) => anioActual - i)

const form = ref({ mes: new Date().getMonth() + 1, anio: anioActual })
const registros     = ref([])
const estadoPeriodo = ref(null)
const totalValor    = ref(0)
const filtroTipo    = ref('')

const registrosFiltrados = computed(() =>
  filtroTipo.value ? registros.value.filter(r => r.tipo === filtroTipo.value) : registros.value
)
const cargando      = ref(false)
const calculando    = ref(false)
const cerrando      = ref(false)
const buscado       = ref(false)
const error         = ref('')
const modalConfirm  = ref(false)
const modalCerrar   = ref(false)

const fmt = (v) => parseFloat(v || 0).toFixed(2)
const nombreCompleto = (e) => e ? `${e.apellido_emp ?? ''} ${e.nombre_emp ?? ''}`.trim().toUpperCase() : ''

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    const { data } = await api.get('/nomina/fondos-reserva', { params: { anio: form.value.anio, mes: form.value.mes } })
    registros.value    = data.registros
    estadoPeriodo.value = data.estado_periodo
    totalValor.value   = data.total_valor
    buscado.value = true
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Error al cargar.'
  } finally {
    cargando.value = false
  }
}

function calcular() {
  if (registros.value.length && estadoPeriodo.value === 'BORRADOR') { modalConfirm.value = true; return }
  confirmarCalculo()
}

async function confirmarCalculo() {
  modalConfirm.value = false
  calculando.value = true
  error.value = ''
  try {
    const { data } = await api.post('/nomina/fondos-reserva/calcular', { anio: form.value.anio, mes: form.value.mes })
    registros.value    = data.registros
    estadoPeriodo.value = data.estado_periodo
    totalValor.value   = data.total_valor
    buscado.value = true
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Error al calcular.'
  } finally {
    calculando.value = false
  }
}

function cerrar() { modalCerrar.value = true }

async function confirmarCierre() {
  modalCerrar.value = false
  cerrando.value = true
  error.value = ''
  try {
    const { data } = await api.post('/nomina/fondos-reserva/cerrar', { anio: form.value.anio, mes: form.value.mes })
    registros.value    = data.registros
    estadoPeriodo.value = data.estado_periodo
    totalValor.value   = data.total_valor
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Error al cerrar.'
  } finally {
    cerrando.value = false
  }
}

async function descargarPdf() {
  try {
    const resp = await api.get('/nomina/fondos-reserva/pdf', {
      params: { anio: form.value.anio, mes: form.value.mes },
      responseType: 'blob',
    })
    const url = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
    const a = document.createElement('a')
    a.href = url
    a.download = `fondos_reserva_${form.value.anio}_${String(form.value.mes).padStart(2, '0')}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    error.value = 'Error al generar el PDF.'
  }
}
</script>
