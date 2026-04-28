<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Libro de Compras</h1>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow p-5 mb-5">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div>
          <label class="block text-xs text-gray-600 mb-1">Desde *</label>
          <input v-model="filtro.desde" type="date"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-300 outline-none" />
        </div>
        <div>
          <label class="block text-xs text-gray-600 mb-1">Hasta *</label>
          <input v-model="filtro.hasta" type="date"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-300 outline-none" />
        </div>
        <div>
          <label class="block text-xs text-gray-600 mb-1">Proceso de Contratación</label>
          <select v-model="filtro.proceso" class="w-full border rounded-lg px-3 py-2 text-sm">
            <option value="">Todos</option>
            <option v-for="p in procesos" :key="p" :value="p">{{ p }}</option>
          </select>
        </div>
        <div class="flex flex-col gap-2">
          <button @click="consultar" :disabled="cargando"
            class="bg-green-700 text-white px-5 py-2 rounded-lg text-sm hover:bg-green-800 disabled:opacity-50">
            {{ cargando ? 'Consultando...' : 'Consultar' }}
          </button>
          <button v-if="filas.length" @click="exportarPdf" :disabled="descargandoPdf"
            class="border border-green-700 text-green-700 px-5 py-2 rounded-lg text-sm hover:bg-green-50 disabled:opacity-50">
            {{ descargandoPdf ? 'Generando...' : 'Descargar PDF' }}
          </button>
        </div>
      </div>
      <p v-if="error" class="text-red-600 text-sm mt-2">{{ error }}</p>
    </div>

    <!-- Resumen -->
    <div v-if="filas.length" class="bg-white rounded-xl shadow p-4 mb-4 flex flex-wrap gap-6 text-sm">
      <div><span class="text-gray-500 text-xs uppercase font-semibold">Facturas</span><br>
        <span class="font-medium">{{ filas.length }}</span></div>
      <div><span class="text-gray-500 text-xs uppercase font-semibold">Subtotal</span><br>
        <span class="font-medium">$ {{ fmt2(totales.subtotal) }}</span></div>
      <div><span class="text-gray-500 text-xs uppercase font-semibold">IVA</span><br>
        <span class="font-medium">$ {{ fmt2(totales.iva) }}</span></div>
      <div><span class="text-gray-500 text-xs uppercase font-semibold">Total General</span><br>
        <span class="font-bold text-green-800 text-base">$ {{ fmt2(totales.total) }}</span></div>
    </div>

    <!-- Tabla -->
    <div v-if="filas.length" class="bg-white rounded-xl shadow overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr style="background-color:#4a5e3a;">
            <th class="text-center px-3 py-3 text-white font-semibold whitespace-nowrap text-xs">Fecha</th>
            <th class="text-left px-3 py-3 text-white font-semibold whitespace-nowrap text-xs">RUC</th>
            <th class="text-left px-3 py-3 text-white font-semibold whitespace-nowrap text-xs">Proveedor</th>
            <th class="text-left px-3 py-3 text-white font-semibold whitespace-nowrap text-xs">N° Factura</th>
            <th class="text-left px-3 py-3 text-white font-semibold whitespace-nowrap text-xs">Proceso Contratación</th>
            <th class="text-right px-3 py-3 text-white font-semibold whitespace-nowrap text-xs">Subtotal</th>
            <th class="text-right px-3 py-3 text-white font-semibold whitespace-nowrap text-xs">IVA</th>
            <th class="text-right px-3 py-3 text-white font-semibold whitespace-nowrap text-xs">Total</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="f in filas" :key="f.id" class="border-b hover:bg-gray-50">
            <td class="px-3 py-2 text-center whitespace-nowrap text-xs">{{ fmtFecha(f.fecha_documento) }}</td>
            <td class="px-3 py-2 font-mono text-xs whitespace-nowrap">{{ f.ruc }}</td>
            <td class="px-3 py-2 text-xs">{{ f.proveedor_nombre }}</td>
            <td class="px-3 py-2 font-mono text-xs">{{ f.numero_documento }}</td>
            <td class="px-3 py-2 text-xs">{{ f.proceso_contratacion || '-' }}</td>
            <td class="px-3 py-2 text-right text-xs font-mono">$ {{ fmt2(f.subtotal) }}</td>
            <td class="px-3 py-2 text-right text-xs font-mono">$ {{ fmt2(f.iva_valor) }}</td>
            <td class="px-3 py-2 text-right text-xs font-mono font-semibold">$ {{ fmt2(f.total) }}</td>
          </tr>
          <tr class="bg-green-50 font-semibold border-t-2 border-green-700">
            <td colspan="5" class="px-3 py-2 text-right text-xs">TOTALES</td>
            <td class="px-3 py-2 text-right text-xs font-mono">$ {{ fmt2(totales.subtotal) }}</td>
            <td class="px-3 py-2 text-right text-xs font-mono">$ {{ fmt2(totales.iva) }}</td>
            <td class="px-3 py-2 text-right text-xs font-mono">$ {{ fmt2(totales.total) }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="consultado && !filas.length"
      class="bg-white rounded-xl shadow p-8 text-center text-gray-400">
      Sin facturas en el período seleccionado
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

const filtro   = ref({ desde: '', hasta: '', proceso: '' })
const procesos = ref([])
const filas    = ref([])
const cargando       = ref(false)
const descargandoPdf = ref(false)
const consultado     = ref(false)
const error          = ref('')

const totales = computed(() => ({
  subtotal: filas.value.reduce((s, f) => s + +f.subtotal, 0),
  iva:      filas.value.reduce((s, f) => s + +f.iva_valor, 0),
  total:    filas.value.reduce((s, f) => s + +f.total, 0),
}))

onMounted(async () => {
  const { data } = await api.get('/adquisiciones/procesos-contratacion/activos')
  procesos.value = data
})

async function consultar() {
  error.value = ''
  if (!filtro.value.desde || !filtro.value.hasta) { error.value = 'Ingrese el rango de fechas.'; return }
  cargando.value = true
  try {
    const { data } = await api.get('/adquisiciones/reportes/libro-compras', { params: filtro.value })
    filas.value    = data
    consultado.value = true
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al consultar'
  } finally { cargando.value = false }
}

async function exportarPdf() {
  descargandoPdf.value = true
  try {
    const response = await api.get('/adquisiciones/reportes/libro-compras', {
      params: { ...filtro.value, formato: 'pdf' },
      responseType: 'blob',
    })
    const url = URL.createObjectURL(new Blob([response.data]))
    const a = document.createElement('a')
    a.href = url
    a.download = 'libro-compras.pdf'
    a.click()
    URL.revokeObjectURL(url)
  } finally { descargandoPdf.value = false }
}

function fmt2(v) { return (+v).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }
function fmtFecha(v) { return v ? new Date(v + 'T00:00:00').toLocaleDateString('es-EC') : '-' }
</script>
