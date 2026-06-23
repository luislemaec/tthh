<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Kardex de Inventario</h1>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow p-5 mb-5">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <!-- Nivel 1 -->
        <div>
          <label class="block text-xs text-gray-600 mb-1">Nivel 1 MEF</label>
          <select v-model="filtro.nivel1" @change="onNivel1Change"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-300 outline-none">
            <option value="">Todos los niveles</option>
            <option v-for="n in nivel1s" :key="n.nivel1" :value="n.nivel1">
              {{ n.nivel1 }} — {{ n.descripcion }}
            </option>
          </select>
        </div>
        <!-- Nivel 2 -->
        <div>
          <label class="block text-xs text-gray-600 mb-1">Nivel 2 MEF</label>
          <select v-model="filtro.nivel2" :disabled="!filtro.nivel1"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-300 outline-none disabled:bg-gray-50">
            <option value="">Todos los sub-niveles</option>
            <option v-for="n in nivel2s" :key="n.nivel2" :value="n.nivel2">
              {{ n.nivel2 }} — {{ n.descripcion }}
            </option>
          </select>
        </div>
        <!-- Artículo -->
        <div class="md:col-span-2 relative">
          <label class="block text-xs text-gray-600 mb-1">Artículo (opcional)</label>
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
          <div class="flex items-center justify-between mt-1">
            <p v-if="articuloSeleccionado" class="text-xs text-green-700 font-medium">
              Seleccionado: [{{ articuloSeleccionado.codigo }}] {{ articuloSeleccionado.nombre }}
            </p>
            <button v-if="articuloSeleccionado" @click="limpiarArticulo"
              class="text-xs text-red-500 hover:text-red-700 ml-2">✕ Quitar</button>
          </div>
        </div>
        <!-- Fechas -->
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
        <button v-if="resultados.length" @click="exportarPdf" :disabled="descargandoPdf"
          class="border border-green-700 text-green-700 px-5 py-2 rounded-lg text-sm hover:bg-green-50 disabled:opacity-50">
          {{ descargandoPdf ? 'Generando PDF...' : 'Descargar PDF' }}
        </button>
        <button v-if="resultados.length" @click="exportarExcel" :disabled="descargandoExcel"
          class="border border-blue-700 text-blue-700 px-5 py-2 rounded-lg text-sm hover:bg-blue-50 disabled:opacity-50">
          {{ descargandoExcel ? 'Generando Excel...' : 'Descargar Excel' }}
        </button>
      </div>
      <p v-if="error" class="text-red-600 text-sm mt-2">{{ error }}</p>
    </div>

    <!-- Secciones por artículo -->
    <div v-for="item in resultados" :key="item.articulo.id" class="mb-6">

      <!-- Resumen artículo -->
      <div class="bg-white rounded-xl shadow p-4 mb-2 flex flex-wrap gap-6 text-sm">
        <div><span class="text-gray-500 text-xs uppercase font-semibold">Artículo</span><br>
          <span class="font-medium">[{{ item.articulo.codigo }}] {{ item.articulo.nombre }}</span></div>
        <div><span class="text-gray-500 text-xs uppercase font-semibold">Niv. MEF</span><br>
          <span class="font-medium">{{ item.articulo.nivel1 }} / {{ item.articulo.nivel2 }}</span></div>
        <div><span class="text-gray-500 text-xs uppercase font-semibold">Stock Actual</span><br>
          <span class="font-bold">{{ fmt2(item.articulo.stock_actual) }}</span></div>
        <div><span class="text-gray-500 text-xs uppercase font-semibold">Costo Promedio</span><br>
          <span class="font-bold font-mono">$ {{ fmt5(item.articulo.precio_unitario) }}</span></div>
        <div><span class="text-gray-500 text-xs uppercase font-semibold">Valor Inventario</span><br>
          <span class="font-bold font-mono text-green-800">$ {{ fmt2(item.articulo.stock_actual * item.articulo.precio_unitario) }}</span></div>
        <div><span class="text-gray-500 text-xs uppercase font-semibold">Movimientos</span><br>
          <span class="font-medium">{{ item.filas.length }}</span></div>
      </div>

      <!-- Tabla Kardex NIC 2 -->
      <div class="bg-white rounded-xl shadow overflow-x-auto">
        <table class="w-full text-xs border-collapse">
          <thead>
            <tr>
              <th rowspan="2" class="px-2 py-2 text-white text-center border border-gray-600 whitespace-nowrap" style="background-color:#4a5e3a;">Fecha</th>
              <th rowspan="2" class="px-2 py-2 text-white text-center border border-gray-600 whitespace-nowrap" style="background-color:#4a5e3a;">N° Doc.</th>
              <th rowspan="2" class="px-2 py-2 text-white text-center border border-gray-600 whitespace-nowrap" style="background-color:#4a5e3a;">Detalle</th>
              <th colspan="3" class="px-2 py-2 text-white text-center border border-gray-600" style="background-color:#1a5c2a;">INGRESO</th>
              <th colspan="3" class="px-2 py-2 text-white text-center border border-gray-600" style="background-color:#7b1d1d;">EGRESO</th>
              <th colspan="3" class="px-2 py-2 text-white text-center border border-gray-600" style="background-color:#1e3a5f;">SALDO</th>
              <th rowspan="2" class="px-2 py-2 text-white text-center border border-gray-600 whitespace-nowrap" style="background-color:#4a5e3a;">Usuario</th>
            </tr>
            <tr>
              <th class="px-2 py-1 text-white text-right border border-gray-600" style="background-color:#1a5c2a;">Cant.</th>
              <th class="px-2 py-1 text-white text-right border border-gray-600" style="background-color:#1a5c2a;">P.Unit</th>
              <th class="px-2 py-1 text-white text-right border border-gray-600" style="background-color:#1a5c2a;">Total</th>
              <th class="px-2 py-1 text-white text-right border border-gray-600" style="background-color:#7b1d1d;">Cant.</th>
              <th class="px-2 py-1 text-white text-right border border-gray-600" style="background-color:#7b1d1d;">P.Unit</th>
              <th class="px-2 py-1 text-white text-right border border-gray-600" style="background-color:#7b1d1d;">Total</th>
              <th class="px-2 py-1 text-white text-right border border-gray-600" style="background-color:#1e3a5f;">Cant.</th>
              <th class="px-2 py-1 text-white text-right border border-gray-600" style="background-color:#1e3a5f;">P.Unit</th>
              <th class="px-2 py-1 text-white text-right border border-gray-600" style="background-color:#1e3a5f;">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(f, i) in item.filas" :key="f.id"
              :class="i % 2 === 0 ? 'bg-white' : 'bg-gray-50'"
              class="border-b hover:bg-yellow-50">
              <td class="px-2 py-1.5 text-center border border-gray-200 whitespace-nowrap">{{ fmtFecha(f.fecha) }}</td>
              <td class="px-2 py-1.5 text-center border border-gray-200 font-mono whitespace-nowrap">{{ f.numero_documento || '—' }}</td>
              <td class="px-2 py-1.5 border border-gray-200 whitespace-nowrap">
                <span :class="badgeClass(f.tipo_movimiento)" class="px-2 py-0.5 rounded-full font-medium">
                  {{ tipoLabel(f.tipo_movimiento) }}
                </span>
              </td>
              <!-- INGRESO -->
              <td class="px-2 py-1.5 text-right border border-gray-200 font-mono" style="background-color:#f0faf3;">
                {{ esIngreso(f) ? fmt2(f.cantidad_entrada) : '' }}
              </td>
              <td class="px-2 py-1.5 text-right border border-gray-200 font-mono" style="background-color:#f0faf3;">
                {{ esIngreso(f) ? fmt5(f.precio_movimiento) : '' }}
              </td>
              <td class="px-2 py-1.5 text-right border border-gray-200 font-mono font-semibold" style="background-color:#f0faf3;">
                {{ esIngreso(f) ? fmt2(f.cantidad_entrada * f.precio_movimiento) : '' }}
              </td>
              <!-- EGRESO -->
              <td class="px-2 py-1.5 text-right border border-gray-200 font-mono" style="background-color:#fff5f5;">
                {{ esEgreso(f) ? fmt2(f.cantidad_salida) : '' }}
              </td>
              <td class="px-2 py-1.5 text-right border border-gray-200 font-mono" style="background-color:#fff5f5;">
                {{ esEgreso(f) ? fmt5(f.precio_movimiento) : '' }}
              </td>
              <td class="px-2 py-1.5 text-right border border-gray-200 font-mono font-semibold" style="background-color:#fff5f5;">
                {{ esEgreso(f) ? fmt2(f.cantidad_salida * f.precio_movimiento) : '' }}
              </td>
              <!-- SALDO -->
              <td class="px-2 py-1.5 text-right border border-gray-200 font-mono font-bold" style="background-color:#eff6ff;">
                {{ fmt2(f.stock_despues) }}
              </td>
              <td class="px-2 py-1.5 text-right border border-gray-200 font-mono" style="background-color:#eff6ff;">
                {{ fmt5(f.precio_despues) }}
              </td>
              <td class="px-2 py-1.5 text-right border border-gray-200 font-mono font-bold" style="background-color:#eff6ff; color:#1e3a5f;">
                {{ fmt2(f.valor_saldo) }}
              </td>
              <td class="px-2 py-1.5 text-center border border-gray-200 text-gray-500">{{ f.usuario }}</td>
            </tr>
          </tbody>
          <!-- Totales por artículo -->
          <tfoot>
            <tr class="font-bold text-xs" style="background-color:#e8f0e0;">
              <td colspan="3" class="px-2 py-2 text-right border border-gray-400">TOTALES</td>
              <td class="px-2 py-2 text-right border border-gray-400 font-mono">{{ fmt2(totIngCant(item.filas)) }}</td>
              <td class="px-2 py-2 border border-gray-400"></td>
              <td class="px-2 py-2 text-right border border-gray-400 font-mono">{{ fmt2(totIngVal(item.filas)) }}</td>
              <td class="px-2 py-2 text-right border border-gray-400 font-mono">{{ fmt2(totEgrCant(item.filas)) }}</td>
              <td class="px-2 py-2 border border-gray-400"></td>
              <td class="px-2 py-2 text-right border border-gray-400 font-mono">{{ fmt2(totEgrVal(item.filas)) }}</td>
              <td class="px-2 py-2 text-right border border-gray-400 font-mono">{{ fmt2(item.filas[item.filas.length-1]?.stock_despues) }}</td>
              <td class="px-2 py-2 text-right border border-gray-400 font-mono">{{ fmt5(item.filas[item.filas.length-1]?.precio_despues) }}</td>
              <td class="px-2 py-2 text-right border border-gray-400 font-mono font-bold" style="color:#1e3a5f;">
                {{ fmt2(item.filas[item.filas.length-1]?.valor_saldo) }}
              </td>
              <td class="border border-gray-400"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <div v-if="consultado && !resultados.length"
      class="bg-white rounded-xl shadow p-8 text-center text-gray-400">
      Sin movimientos en el período seleccionado para los filtros indicados
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const filtro               = ref({ nivel1: '', nivel2: '', desde: '', hasta: '' })
const busquedaArticulo     = ref('')
const sugerencias          = ref([])
const articuloSeleccionado = ref(null)
const resultados           = ref([])
const nivel1s              = ref([])
const nivel2s              = ref([])
const cargando             = ref(false)
const descargandoPdf       = ref(false)
const descargandoExcel     = ref(false)
const consultado           = ref(false)
const error                = ref('')

let debounceTimer = null

const TIPOS_INGRESO = ['INGRESO', 'REVERSO_EGRESO', 'AJUSTE_POSITIVO', 'SALDO_INICIAL']
const TIPOS_EGRESO  = ['EGRESO', 'REVERSO_INGRESO', 'AJUSTE_NEGATIVO']

function esIngreso(f) { return TIPOS_INGRESO.includes(f.tipo_movimiento) }
function esEgreso(f)  { return TIPOS_EGRESO.includes(f.tipo_movimiento) }

function totIngCant(filas) { return filas.filter(esIngreso).reduce((s, f) => s + +f.cantidad_entrada, 0) }
function totIngVal(filas)  { return filas.filter(esIngreso).reduce((s, f) => s + +f.cantidad_entrada * +f.precio_movimiento, 0) }
function totEgrCant(filas) { return filas.filter(esEgreso).reduce((s, f) => s + +f.cantidad_salida, 0) }
function totEgrVal(filas)  { return filas.filter(esEgreso).reduce((s, f) => s + +f.cantidad_salida * +f.precio_movimiento, 0) }

onMounted(async () => {
  try {
    const { data } = await api.get('/adquisiciones/catalogo-inventario/nivel1s')
    nivel1s.value = data
  } catch {}
})

async function onNivel1Change() {
  filtro.value.nivel2 = ''
  nivel2s.value = []
  if (!filtro.value.nivel1) return
  try {
    const { data } = await api.get('/adquisiciones/catalogo-inventario', {
      params: { nivel1: filtro.value.nivel1, por_pagina: 500 },
    })
    nivel2s.value = data.data ?? data
  } catch {}
}

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

function cerrarSugerencias() { setTimeout(() => { sugerencias.value = [] }, 200) }

function seleccionarArticulo(a) {
  articuloSeleccionado.value = a
  busquedaArticulo.value     = `[${a.codigo}] ${a.nombre}`
  sugerencias.value          = []
}

function limpiarArticulo() {
  articuloSeleccionado.value = null
  busquedaArticulo.value     = ''
}

function buildParams() {
  const params = { ...filtro.value }
  if (articuloSeleccionado.value) params.articulo_id = articuloSeleccionado.value.id
  if (!params.nivel1) delete params.nivel1
  if (!params.nivel2) delete params.nivel2
  return params
}

async function consultar() {
  error.value = ''

  // Si hay texto escrito sin selección, buscar coincidencia exacta por código
  if (!articuloSeleccionado.value && busquedaArticulo.value.trim() && !filtro.value.nivel1 && !filtro.value.nivel2) {
    try {
      const { data } = await api.get('/adquisiciones/reportes/articulos', { params: { q: busquedaArticulo.value.trim() } })
      const texto  = busquedaArticulo.value.trim().toLowerCase()
      const exacto = data.find(a => a.codigo.toLowerCase() === texto)
      const match  = exacto ?? (data.length === 1 ? data[0] : null)
      if (match) seleccionarArticulo(match)
      else { error.value = 'Artículo no encontrado. Selecciónelo del desplegable.'; return }
    } catch { error.value = 'Seleccione un artículo o un Nivel MEF.'; return }
  }

  if (!articuloSeleccionado.value && !filtro.value.nivel1 && !filtro.value.nivel2) {
    error.value = 'Seleccione un artículo o un Nivel MEF.'; return
  }
  if (!filtro.value.desde || !filtro.value.hasta) { error.value = 'Ingrese el rango de fechas.'; return }
  cargando.value = true
  try {
    const { data } = await api.get('/adquisiciones/reportes/kardex', { params: buildParams() })
    resultados.value = data
    consultado.value = true
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al consultar'
  } finally { cargando.value = false }
}

async function exportarExcel() {
  descargandoExcel.value = true
  error.value = ''
  try {
    const response = await api.get('/adquisiciones/reportes/kardex', {
      params: { ...buildParams(), formato: 'excel' },
      responseType: 'blob',
    })
    const filename = filtro.value.nivel2 ? `kardex-${filtro.value.nivel2}.xlsx`
                   : filtro.value.nivel1  ? `kardex-nivel-${filtro.value.nivel1}.xlsx`
                   : `kardex-${articuloSeleccionado.value?.codigo}.xlsx`
    const url = URL.createObjectURL(new Blob([response.data],
      { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }))
    const a   = document.createElement('a')
    a.href    = url
    a.download = filename
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch (e) {
    error.value = 'Error al generar Excel. Revise los filtros e intente nuevamente.'
  } finally { descargandoExcel.value = false }
}

async function exportarPdf() {
  descargandoPdf.value = true
  try {
    const response = await api.get('/adquisiciones/reportes/kardex', {
      params: { ...buildParams(), formato: 'pdf' },
      responseType: 'blob',
    })
    const filename = filtro.value.nivel2 ? `kardex-${filtro.value.nivel2}.pdf`
                   : filtro.value.nivel1  ? `kardex-nivel-${filtro.value.nivel1}.pdf`
                   : `kardex-${articuloSeleccionado.value?.codigo}.pdf`
    const url = URL.createObjectURL(new Blob([response.data]))
    const a   = document.createElement('a')
    a.href    = url
    a.download = filename
    a.click()
    URL.revokeObjectURL(url)
  } finally { descargandoPdf.value = false }
}

function fmt2(v) { return (+v || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }
function fmt5(v) { return (+v || 0).toLocaleString('es-EC', { minimumFractionDigits: 5, maximumFractionDigits: 5 }) }
function fmtFecha(v) {
  const d = new Date(v)
  return d.toLocaleDateString('es-EC') + ' ' + d.toLocaleTimeString('es-EC', { hour: '2-digit', minute: '2-digit' })
}
function tipoLabel(t) {
  return {
    INGRESO:          'Ingreso',
    EGRESO:           'Egreso',
    REVERSO_INGRESO:  'Rev. Ingreso',
    REVERSO_EGRESO:   'Rev. Egreso',
    AJUSTE_POSITIVO:  'Ajuste (+)',
    AJUSTE_NEGATIVO:  'Ajuste (-)',
    SALDO_INICIAL:    'Saldo Inicial',
  }[t] || t
}
function badgeClass(t) {
  return {
    INGRESO:         'bg-green-100 text-green-800',
    EGRESO:          'bg-red-100 text-red-800',
    REVERSO_INGRESO: 'bg-yellow-100 text-yellow-800',
    REVERSO_EGRESO:  'bg-indigo-100 text-indigo-800',
    AJUSTE_POSITIVO: 'bg-teal-100 text-teal-800',
    AJUSTE_NEGATIVO: 'bg-orange-100 text-orange-800',
    SALDO_INICIAL:   'bg-gray-100 text-gray-700',
  }[t] || 'bg-gray-100 text-gray-600'
}
</script>
