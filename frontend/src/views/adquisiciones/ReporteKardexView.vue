<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Reporte Kardex</h1>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow p-5 mb-5">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <!-- Artículo autocomplete -->
        <div class="md:col-span-2 relative">
          <label class="block text-xs text-gray-600 mb-1">Artículo *</label>
          <input v-model="busquedaArticulo" @input="buscarArticulos" @blur="cerrarSugerencias"
            type="text" placeholder="Buscar por código o nombre..."
            class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-300 outline-none" />
          <ul v-if="sugerencias.length"
            class="absolute z-10 bg-white border rounded-lg shadow-lg w-full mt-1 max-h-48 overflow-y-auto">
            <li v-for="a in sugerencias" :key="a.id"
              @mousedown.prevent="seleccionarArticulo(a)"
              class="px-3 py-2 text-sm hover:bg-green-50 cursor-pointer border-b last:border-0">
              <span class="font-mono text-xs text-gray-500 mr-2">{{ a.codigo }}</span>{{ a.nombre }}
            </li>
          </ul>
          <p v-if="articuloSeleccionado" class="text-xs text-green-700 mt-1 font-medium">
            Seleccionado: [{{ articuloSeleccionado.codigo }}] {{ articuloSeleccionado.nombre }}
          </p>
        </div>
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
      </div>
      <div class="flex gap-3 mt-4">
        <button @click="consultar" :disabled="cargando"
          class="bg-green-700 text-white px-5 py-2 rounded-lg text-sm hover:bg-green-800 disabled:opacity-50">
          {{ cargando ? 'Consultando...' : 'Consultar' }}
        </button>
        <button v-if="filas.length" @click="exportarPdf" :disabled="descargandoPdf"
          class="border border-green-700 text-green-700 px-5 py-2 rounded-lg text-sm hover:bg-green-50 disabled:opacity-50">
          {{ descargandoPdf ? 'Generando PDF...' : 'Descargar PDF' }}
        </button>
      </div>
      <p v-if="error" class="text-red-600 text-sm mt-2">{{ error }}</p>
    </div>

    <!-- Resumen artículo -->
    <div v-if="articulo" class="bg-white rounded-xl shadow p-4 mb-4 flex flex-wrap gap-6 text-sm">
      <div><span class="text-gray-500 text-xs uppercase font-semibold">Artículo</span><br>
        <span class="font-medium">[{{ articulo.codigo }}] {{ articulo.nombre }}</span></div>
      <div><span class="text-gray-500 text-xs uppercase font-semibold">Stock Actual</span><br>
        <span class="font-medium">{{ fmt2(articulo.stock_actual) }}</span></div>
      <div><span class="text-gray-500 text-xs uppercase font-semibold">Precio s/IVA</span><br>
        <span class="font-medium">$ {{ fmt4(articulo.precio_unitario) }}</span></div>
      <div><span class="text-gray-500 text-xs uppercase font-semibold">Movimientos</span><br>
        <span class="font-medium">{{ filas.length }}</span></div>
    </div>

    <!-- Tabla -->
    <div v-if="filas.length" class="bg-white rounded-xl shadow overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr style="background-color:#4a5e3a;">
            <th class="text-center px-3 py-3 text-white font-semibold whitespace-nowrap text-xs">Fecha</th>
            <th class="text-center px-3 py-3 text-white font-semibold whitespace-nowrap text-xs">Tipo</th>
            <th class="text-left px-3 py-3 text-white font-semibold whitespace-nowrap text-xs">N° Documento</th>
            <th class="text-right px-3 py-3 text-white font-semibold whitespace-nowrap text-xs">Cant. Entrada</th>
            <th class="text-right px-3 py-3 text-white font-semibold whitespace-nowrap text-xs">Cant. Salida</th>
            <th class="text-right px-3 py-3 text-white font-semibold whitespace-nowrap text-xs">Stock Antes</th>
            <th class="text-right px-3 py-3 text-white font-semibold whitespace-nowrap text-xs">Stock Después</th>
            <th class="text-right px-3 py-3 text-white font-semibold whitespace-nowrap text-xs">Precio s/IVA</th>
            <th class="text-right px-3 py-3 text-white font-semibold whitespace-nowrap text-xs">Subtotal</th>
            <th class="text-right px-3 py-3 text-white font-semibold whitespace-nowrap text-xs">IVA</th>
            <th class="text-right px-3 py-3 text-white font-semibold whitespace-nowrap text-xs">Total</th>
            <th class="text-center px-3 py-3 text-white font-semibold whitespace-nowrap text-xs">Usuario</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="f in filas" :key="f.id" class="border-b hover:bg-gray-50">
            <td class="px-3 py-2 text-center whitespace-nowrap text-xs">{{ fmtFecha(f.fecha) }}</td>
            <td class="px-3 py-2 text-center whitespace-nowrap">
              <span :class="badgeClass(f.tipo_movimiento)"
                class="inline-block text-xs px-2 py-0.5 rounded-full font-medium">
                {{ tipoLabel(f.tipo_movimiento) }}
              </span>
            </td>
            <td class="px-3 py-2 font-mono text-xs">{{ f.numero_documento || '-' }}</td>
            <td class="px-3 py-2 text-right text-xs">{{ +f.cantidad_entrada > 0 ? fmt2(f.cantidad_entrada) : '-' }}</td>
            <td class="px-3 py-2 text-right text-xs">{{ +f.cantidad_salida > 0 ? fmt2(f.cantidad_salida) : '-' }}</td>
            <td class="px-3 py-2 text-right text-xs">{{ fmt2(f.stock_antes) }}</td>
            <td class="px-3 py-2 text-right font-semibold text-xs">{{ fmt2(f.stock_despues) }}</td>
            <td class="px-3 py-2 text-right text-xs font-mono">$ {{ fmt4(f.precio_movimiento) }}</td>
            <td class="px-3 py-2 text-right text-xs font-mono">$ {{ fmt2(f.subtotal) }}</td>
            <td class="px-3 py-2 text-right text-xs font-mono">$ {{ fmt2(f.iva_valor) }}</td>
            <td class="px-3 py-2 text-right text-xs font-mono font-semibold">$ {{ fmt2(f.total_linea) }}</td>
            <td class="px-3 py-2 text-center text-xs">{{ f.usuario }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="consultado && !filas.length"
      class="bg-white rounded-xl shadow p-8 text-center text-gray-400">
      Sin movimientos en el período seleccionado
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import api from '@/services/api'

const filtro              = ref({ desde: '', hasta: '' })
const busquedaArticulo    = ref('')
const sugerencias         = ref([])
const articuloSeleccionado = ref(null)
const articulo            = ref(null)
const filas               = ref([])
const cargando            = ref(false)
const descargandoPdf      = ref(false)
const consultado          = ref(false)
const error               = ref('')

let debounceTimer = null

function buscarArticulos() {
  articuloSeleccionado.value = null
  sugerencias.value = []
  clearTimeout(debounceTimer)
  if (busquedaArticulo.value.trim().length < 2) return
  debounceTimer = setTimeout(async () => {
    try {
      const { data } = await api.get('/adquisiciones/reportes/articulos', { params: { q: busquedaArticulo.value } })
      sugerencias.value = data
    } catch {}
  }, 300)
}

function cerrarSugerencias() {
  setTimeout(() => { sugerencias.value = [] }, 200)
}

function seleccionarArticulo(a) {
  articuloSeleccionado.value = a
  busquedaArticulo.value     = `[${a.codigo}] ${a.nombre}`
  sugerencias.value          = []
}

async function consultar() {
  error.value = ''
  if (!articuloSeleccionado.value) { error.value = 'Seleccione un artículo.'; return }
  if (!filtro.value.desde || !filtro.value.hasta) { error.value = 'Ingrese el rango de fechas.'; return }
  cargando.value = true
  try {
    const { data } = await api.get('/adquisiciones/reportes/kardex', {
      params: { articulo_id: articuloSeleccionado.value.id, ...filtro.value },
    })
    articulo.value = data.articulo
    filas.value    = data.filas
    consultado.value = true
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al consultar'
  } finally { cargando.value = false }
}

async function exportarPdf() {
  descargandoPdf.value = true
  try {
    const response = await api.get('/adquisiciones/reportes/kardex', {
      params: { articulo_id: articuloSeleccionado.value.id, ...filtro.value, formato: 'pdf' },
      responseType: 'blob',
    })
    const url = URL.createObjectURL(new Blob([response.data]))
    const a = document.createElement('a')
    a.href = url
    a.download = `kardex-${articuloSeleccionado.value.codigo}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  } finally { descargandoPdf.value = false }
}

function fmt2(v) { return (+v).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }
function fmt4(v) { return (+v).toLocaleString('es-EC', { minimumFractionDigits: 4, maximumFractionDigits: 4 }) }
function fmtFecha(v) {
  const d = new Date(v)
  return d.toLocaleDateString('es-EC') + ' ' + d.toLocaleTimeString('es-EC', { hour: '2-digit', minute: '2-digit' })
}
function tipoLabel(t) {
  return { INGRESO: 'Ingreso', EGRESO: 'Egreso', REVERSO_INGRESO: 'Rev. Ingreso', REVERSO_EGRESO: 'Rev. Egreso' }[t] || t
}
function badgeClass(t) {
  return {
    INGRESO:         'bg-green-100 text-green-700',
    EGRESO:          'bg-red-100 text-red-700',
    REVERSO_INGRESO: 'bg-yellow-100 text-yellow-700',
    REVERSO_EGRESO:  'bg-indigo-100 text-indigo-700',
  }[t] || 'bg-gray-100 text-gray-600'
}
</script>
