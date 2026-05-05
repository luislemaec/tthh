<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Décimo Cuarto Sueldo</h1>
    </div>

    <!-- Panel SBU -->
    <div class="bg-white rounded-xl shadow p-5 mb-4">
      <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-3">Salario Básico Unificado (SBU) por Año</h2>
      <div class="flex flex-wrap gap-3 items-end mb-4">
        <div>
          <label class="block text-xs text-gray-600 mb-1">Año</label>
          <input v-model.number="sbuForm.anio" type="number" min="2020" max="2100"
            class="border rounded-lg px-3 py-2 text-sm w-24 focus:ring-2 focus:ring-blue-300 outline-none" />
        </div>
        <div>
          <label class="block text-xs text-gray-600 mb-1">Valor SBU $</label>
          <input v-model.number="sbuForm.valor" type="number" step="0.01" min="1"
            class="border rounded-lg px-3 py-2 text-sm w-32 focus:ring-2 focus:ring-blue-300 outline-none" />
        </div>
        <button @click="guardarSbu" :disabled="guardandoSbu"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 disabled:opacity-50">
          {{ guardandoSbu ? 'Guardando...' : 'Guardar SBU' }}
        </button>
      </div>
      <p v-if="sbuError" class="text-red-600 text-xs mb-2">{{ sbuError }}</p>

      <table v-if="sbuHistorico.length" class="text-sm border-collapse">
        <thead>
          <tr class="bg-gray-50">
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 border">Año</th>
            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 border">SBU $</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in sbuHistorico" :key="s.id" class="border-b hover:bg-gray-50">
            <td class="px-4 py-1 border font-medium">{{ s.anio }}</td>
            <td class="px-4 py-1 border text-right font-mono">${{ parseFloat(s.valor).toFixed(2) }}</td>
          </tr>
        </tbody>
      </table>
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

        <span v-if="estadoPeriodo" class="ml-2 px-3 py-1 rounded-full text-xs font-bold"
          :class="estadoPeriodo === 'CERRADO' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'">
          {{ estadoPeriodo }}
        </span>
        <span v-else-if="buscado && !registros.length"
          class="ml-2 px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-600">Sin datos</span>
      </div>

      <!-- SBU del año del período -->
      <div v-if="sbuDelAnio" class="mt-3 text-sm text-gray-600">
        SBU {{ form.anio }}: <span class="font-semibold text-gray-800">${{ parseFloat(sbuDelAnio.valor).toFixed(2) }}</span>
      </div>
      <div v-else-if="buscado" class="mt-3 text-sm text-amber-600 font-medium">
        ⚠ No hay SBU registrado para {{ form.anio }}. Ingrese el SBU en el panel superior antes de calcular.
      </div>

      <p v-if="error" class="text-red-600 text-sm mt-3">{{ error }}</p>
    </div>

    <!-- Tabla de resultados -->
    <div v-if="registros.length" class="bg-white rounded-xl shadow overflow-hidden">
      <div class="px-5 py-3 border-b flex justify-between items-center text-sm text-gray-600">
        <span>{{ registros.length }} servidores</span>
        <span class="font-semibold text-gray-800">Total: ${{ fmt(totalValor) }}</span>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">N°</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Apellidos y Nombres</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Cédula</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Departamento</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">SBU</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Días</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Valor</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="(r, i) in registros" :key="r.id" class="hover:bg-gray-50">
              <td class="px-4 py-2 text-gray-500">{{ i + 1 }}</td>
              <td class="px-4 py-2 font-medium text-gray-800">{{ nombreCompleto(r.empleado) }}</td>
              <td class="px-4 py-2 text-gray-600 font-mono">{{ r.empleado?.identificacion }}</td>
              <td class="px-4 py-2 text-gray-600">{{ r.empleado?.departamento?.nombre_depto }}</td>
              <td class="px-4 py-2 text-right font-mono">${{ fmt(r.sbu) }}</td>
              <td class="px-4 py-2 text-center text-gray-600">{{ r.dias }}</td>
              <td class="px-4 py-2 text-right font-mono font-semibold text-blue-700">${{ fmt(r.valor) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="bg-gray-100 font-bold">
              <td colspan="5" class="px-4 py-2 text-right text-gray-700">TOTAL</td>
              <td></td>
              <td class="px-4 py-2 text-right font-mono text-blue-800">${{ fmt(totalValor) }}</td>
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
import { ref, computed, onMounted } from 'vue'
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
const registros      = ref([])
const estadoPeriodo  = ref(null)
const totalValor     = ref(0)
const sbuDelAnio     = ref(null)
const cargando       = ref(false)
const calculando     = ref(false)
const cerrando       = ref(false)
const buscado        = ref(false)
const error          = ref('')
const modalConfirm   = ref(false)
const modalCerrar    = ref(false)

const sbuHistorico   = ref([])
const sbuForm        = ref({ anio: anioActual, valor: '' })
const guardandoSbu   = ref(false)
const sbuError       = ref('')

const fmt = (v) => parseFloat(v || 0).toFixed(2)
const nombreCompleto = (e) => e ? `${e.apellido_emp ?? ''} ${e.nombre_emp ?? ''}`.trim().toUpperCase() : ''

onMounted(cargarSbu)

async function cargarSbu() {
  try {
    const { data } = await api.get('/nomina/sbu')
    sbuHistorico.value = data
  } catch {}
}

async function guardarSbu() {
  sbuError.value = ''
  if (!sbuForm.value.anio || !sbuForm.value.valor) { sbuError.value = 'Ingrese año y valor.'; return }
  guardandoSbu.value = true
  try {
    await api.post('/nomina/sbu', sbuForm.value)
    await cargarSbu()
    sbuForm.value.valor = ''
  } catch (e) {
    sbuError.value = e.response?.data?.message ?? 'Error al guardar.'
  } finally {
    guardandoSbu.value = false
  }
}

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    const { data } = await api.get('/nomina/decimo-cuarto', { params: { anio: form.value.anio, mes: form.value.mes } })
    registros.value    = data.registros
    estadoPeriodo.value = data.estado_periodo
    totalValor.value   = data.total_valor
    sbuDelAnio.value   = data.sbu_anio
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
    const { data } = await api.post('/nomina/decimo-cuarto/calcular', { anio: form.value.anio, mes: form.value.mes })
    registros.value    = data.registros
    estadoPeriodo.value = data.estado_periodo
    totalValor.value   = data.total_valor
    sbuDelAnio.value   = data.sbu_anio
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
    const { data } = await api.post('/nomina/decimo-cuarto/cerrar', { anio: form.value.anio, mes: form.value.mes })
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
    const resp = await api.get('/nomina/decimo-cuarto/pdf', {
      params: { anio: form.value.anio, mes: form.value.mes },
      responseType: 'blob',
    })
    const url = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
    const a = document.createElement('a')
    a.href = url
    a.download = `decimo_cuarto_${form.value.anio}_${String(form.value.mes).padStart(2, '0')}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    error.value = 'Error al generar el PDF.'
  }
}
</script>
