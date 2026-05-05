<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Consolidado Décimos 13° y 14°</h1>
    </div>

    <!-- Controles -->
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
        <button v-if="filas.length" @click="descargarPdf"
          class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700">
          Generar PDF
        </button>
      </div>
      <p v-if="error" class="text-red-600 text-sm mt-3">{{ error }}</p>
    </div>

    <!-- Avisos cuando falta uno de los dos cálculos -->
    <div v-if="buscado && (!tieneD13 || !tieneD14)" class="bg-amber-50 border border-amber-300 rounded-lg p-3 mb-4 text-sm text-amber-800">
      ⚠
      <span v-if="!tieneD13">No se ha calculado el <strong>Décimo Tercero</strong> para este período.</span>
      <span v-if="!tieneD13 && !tieneD14"> </span>
      <span v-if="!tieneD14">No se ha calculado el <strong>Décimo Cuarto</strong> para este período.</span>
    </div>

    <!-- Tabla -->
    <div v-if="filas.length" class="bg-white rounded-xl shadow overflow-hidden">
      <div class="px-5 py-3 border-b flex flex-wrap gap-6 items-center text-sm text-gray-600">
        <span>{{ filas.length }} servidores</span>
        <span>Décimo Tercero: <span class="font-semibold text-blue-700">${{ fmt(total13) }}</span></span>
        <span>Décimo Cuarto: <span class="font-semibold text-green-700">${{ fmt(total14) }}</span></span>
        <span class="ml-auto font-bold text-gray-800">Gran Total: ${{ fmt(granTotal) }}</span>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">N°</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Apellidos y Nombres</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Cédula</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Departamento</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-blue-600 uppercase bg-blue-50">Décimo Tercero $</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-green-600 uppercase bg-green-50">Décimo Cuarto $</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-yellow-700 uppercase bg-yellow-50">Total $</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="(f, i) in filasPaginadas" :key="f.id_emp" class="hover:bg-gray-50">
              <td class="px-4 py-2 text-gray-500">{{ (pagina - 1) * POR_PAGINA + i + 1 }}</td>
              <td class="px-4 py-2 font-medium text-gray-800">{{ nombreCompleto(f.empleado) }}</td>
              <td class="px-4 py-2 text-gray-600 font-mono">{{ f.empleado?.identificacion }}</td>
              <td class="px-4 py-2 text-gray-600">{{ f.empleado?.departamento?.nombre_depto }}</td>
              <td class="px-4 py-2 text-right font-mono bg-blue-50 text-blue-700">${{ fmt(f.valor_13) }}</td>
              <td class="px-4 py-2 text-right font-mono bg-green-50 text-green-700">${{ fmt(f.valor_14) }}</td>
              <td class="px-4 py-2 text-right font-mono font-bold bg-yellow-50 text-yellow-800">${{ fmt(f.total) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="bg-gray-100 font-bold">
              <td colspan="4" class="px-4 py-2 text-right text-gray-700">TOTAL</td>
              <td class="px-4 py-2 text-right font-mono bg-blue-100 text-blue-800">${{ fmt(total13) }}</td>
              <td class="px-4 py-2 text-right font-mono bg-green-100 text-green-800">${{ fmt(total14) }}</td>
              <td class="px-4 py-2 text-right font-mono bg-yellow-100 text-yellow-900">${{ fmt(granTotal) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
      <!-- Paginador -->
      <div v-if="totalPaginas > 1" class="px-5 py-3 border-t flex items-center justify-between text-sm text-gray-600">
        <span>Página {{ pagina }} de {{ totalPaginas }} ({{ filas.length }} servidores)</span>
        <div class="flex gap-1">
          <button @click="pagina--" :disabled="pagina === 1"
            class="px-3 py-1 border rounded text-sm hover:bg-gray-50 disabled:opacity-40">‹ Anterior</button>
          <button v-for="p in totalPaginas" :key="p" @click="pagina = p"
            class="px-3 py-1 border rounded text-sm"
            :class="p === pagina ? 'bg-blue-600 text-white border-blue-600' : 'hover:bg-gray-50'">
            {{ p }}
          </button>
          <button @click="pagina++" :disabled="pagina === totalPaginas"
            class="px-3 py-1 border rounded text-sm hover:bg-gray-50 disabled:opacity-40">Siguiente ›</button>
        </div>
      </div>
    </div>

    <div v-else-if="buscado" class="bg-white rounded-xl shadow p-8 text-center text-gray-400">
      No hay datos calculados para este período.
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

const form     = ref({ mes: new Date().getMonth() + 1, anio: anioActual })
const filas    = ref([])
const tieneD13 = ref(false)
const tieneD14 = ref(false)
const total13  = ref(0)
const total14  = ref(0)
const granTotal = ref(0)
const cargando = ref(false)
const buscado  = ref(false)
const error    = ref('')

const POR_PAGINA = 20
const pagina = ref(1)
const filasPaginadas = computed(() => {
  const ini = (pagina.value - 1) * POR_PAGINA
  return filas.value.slice(ini, ini + POR_PAGINA)
})
const totalPaginas = computed(() => Math.ceil(filas.value.length / POR_PAGINA))

const fmt = (v) => parseFloat(v || 0).toFixed(2)
const nombreCompleto = (e) => e ? `${e.apellido_emp ?? ''} ${e.nombre_emp ?? ''}`.trim().toUpperCase() : ''

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    const { data } = await api.get('/nomina/consolidado', {
      params: { anio: form.value.anio, mes: form.value.mes }
    })
    filas.value    = data.filas
    tieneD13.value = data.tiene_d13
    tieneD14.value = data.tiene_d14
    total13.value  = data.total_13
    total14.value  = data.total_14
    granTotal.value = data.gran_total
    pagina.value = 1
    buscado.value = true
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Error al cargar.'
  } finally {
    cargando.value = false
  }
}

async function descargarPdf() {
  try {
    const resp = await api.get('/nomina/consolidado/pdf', {
      params: { anio: form.value.anio, mes: form.value.mes },
      responseType: 'blob',
    })
    const url = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
    const a = document.createElement('a')
    a.href = url
    a.download = `consolidado_decimos_${form.value.anio}_${String(form.value.mes).padStart(2, '0')}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  } catch {
    error.value = 'Error al generar el PDF.'
  }
}
</script>
