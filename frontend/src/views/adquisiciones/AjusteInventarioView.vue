<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Ajuste de Inventario</h1>
    </div>

    <!-- Formulario de ajuste -->
    <div class="bg-white rounded-xl shadow p-5 mb-5">
      <h2 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wide">Registrar Ajuste (Toma Física)</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

        <!-- Buscador artículo -->
        <div class="md:col-span-2 relative">
          <label class="block text-xs text-gray-600 mb-1">Artículo *</label>
          <input v-model="busqueda" @input="filtrarArticulos" @keydown.escape="sugerencias = []"
            type="text" placeholder="Buscar por código o nombre..."
            class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-300 outline-none" />
          <div v-if="sugerencias.length"
            class="absolute z-20 bg-white border rounded-lg shadow-lg w-full mt-1 max-h-52 overflow-y-auto">
            <div v-for="a in sugerencias" :key="a.id" @click="seleccionarArticulo(a)"
              class="px-4 py-2 text-sm hover:bg-gray-100 cursor-pointer border-b last:border-0">
              <div class="flex justify-between items-center">
                <div>
                  <span class="font-mono text-xs text-gray-400 mr-2">{{ a.codigo }}</span>
                  <span class="font-medium">{{ a.nombre }}</span>
                </div>
                <span class="text-xs text-gray-500 font-mono">Stock: {{ a.stock_actual }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Stock sistema (readonly) -->
        <div v-if="articulo">
          <label class="block text-xs text-gray-600 mb-1">Stock en sistema</label>
          <input :value="articulo.stock_actual" type="text" readonly
            class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50 font-mono font-bold text-gray-700" />
        </div>

        <!-- Cantidad física contada -->
        <div v-if="articulo">
          <label class="block text-xs text-gray-600 mb-1">Cantidad física contada *</label>
          <input v-model.number="form.cantidad_fisica" type="number" step="0.01" min="0"
            @input="calcularDiferencia"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-300 outline-none font-mono" />
        </div>

        <!-- Diferencia calculada -->
        <div v-if="articulo && form.cantidad_fisica !== ''">
          <label class="block text-xs text-gray-600 mb-1">Diferencia</label>
          <div class="flex items-center gap-2 px-3 py-2 border rounded-lg text-sm font-mono font-bold"
            :class="diferencia > 0 ? 'bg-green-50 text-green-700 border-green-300' :
                    diferencia < 0 ? 'bg-red-50 text-red-700 border-red-300' :
                    'bg-gray-50 text-gray-500'">
            <span>{{ diferencia > 0 ? '+' : '' }}{{ fmt(diferencia) }}</span>
            <span class="text-xs font-normal ml-1">
              {{ diferencia > 0 ? '→ AJUSTE POSITIVO' : diferencia < 0 ? '→ AJUSTE NEGATIVO' : '→ Sin diferencia' }}
            </span>
          </div>
        </div>

        <!-- N° Acta (opcional) -->
        <div v-if="articulo">
          <label class="block text-xs text-gray-600 mb-1">N° Acta / Documento (opcional)</label>
          <input v-model="form.numero_documento" v-uppercase type="text" maxlength="50"
            placeholder="ej: ACTA-2026-001"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-300 outline-none" />
        </div>

        <!-- Motivo -->
        <div v-if="articulo" class="md:col-span-2">
          <label class="block text-xs text-gray-600 mb-1">Motivo del ajuste *</label>
          <textarea v-model="form.motivo" rows="2" maxlength="500"
            placeholder="Describa el motivo del ajuste (toma física, daño, merma, etc.)..."
            class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-300 outline-none resize-none" />
          <p class="text-xs text-gray-400 text-right">{{ form.motivo.length }}/500</p>
        </div>
      </div>

      <p v-if="error" class="text-red-600 text-sm mt-3">{{ error }}</p>
      <p v-if="exito" class="text-green-700 text-sm mt-3 font-medium">{{ exito }}</p>

      <div v-if="articulo" class="flex justify-end mt-4">
        <button @click="guardar" :disabled="guardando || diferencia === 0"
          class="text-white px-6 py-2 rounded-lg text-sm hover:opacity-90 disabled:opacity-50"
          style="background-color:#4a5e3a;">
          {{ guardando ? 'Registrando...' : 'Registrar Ajuste' }}
        </button>
      </div>
    </div>

    <!-- Historial de ajustes -->
    <div class="bg-white rounded-xl shadow overflow-x-auto">
      <div class="px-5 py-3 border-b flex justify-between items-center">
        <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Historial de Ajustes</h2>
        <button @click="cargarHistorial"
          class="text-xs text-green-700 hover:underline">Actualizar</button>
      </div>
      <table class="w-full text-sm">
        <thead>
          <tr style="background-color:#4a5e3a;">
            <th class="text-left px-4 py-3 text-white font-semibold text-xs whitespace-nowrap">Fecha</th>
            <th class="text-left px-4 py-3 text-white font-semibold text-xs whitespace-nowrap">N° Doc</th>
            <th class="text-left px-4 py-3 text-white font-semibold text-xs">Artículo</th>
            <th class="text-center px-4 py-3 text-white font-semibold text-xs whitespace-nowrap">Tipo</th>
            <th class="text-right px-4 py-3 text-white font-semibold text-xs whitespace-nowrap">Stock Antes</th>
            <th class="text-right px-4 py-3 text-white font-semibold text-xs whitespace-nowrap">Diferencia</th>
            <th class="text-right px-4 py-3 text-white font-semibold text-xs whitespace-nowrap">Stock Después</th>
            <th class="text-left px-4 py-3 text-white font-semibold text-xs">Motivo</th>
            <th class="text-left px-4 py-3 text-white font-semibold text-xs whitespace-nowrap">Usuario</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!historial.length">
            <td colspan="9" class="text-center py-8 text-gray-400">Sin ajustes registrados</td>
          </tr>
          <tr v-for="h in historial" :key="h.id" class="border-b hover:bg-gray-50">
            <td class="px-4 py-2 text-xs whitespace-nowrap">{{ fmtFecha(h.fecha) }}</td>
            <td class="px-4 py-2 text-xs font-mono whitespace-nowrap">{{ h.numero_documento || '—' }}</td>
            <td class="px-4 py-2 text-xs">
              <div class="font-medium">{{ h.articulo_nombre }}</div>
              <div class="text-gray-400 font-mono">{{ h.articulo_codigo }}</div>
            </td>
            <td class="px-4 py-2 text-center">
              <span :class="h.tipo_movimiento === 'AJUSTE_POSITIVO'
                  ? 'bg-green-100 text-green-700'
                  : 'bg-red-100 text-red-700'"
                class="px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap">
                {{ h.tipo_movimiento === 'AJUSTE_POSITIVO' ? '▲ Positivo' : '▼ Negativo' }}
              </span>
            </td>
            <td class="px-4 py-2 text-right font-mono text-xs">{{ fmt(h.stock_antes) }}</td>
            <td class="px-4 py-2 text-right font-mono text-xs font-bold"
              :class="h.tipo_movimiento === 'AJUSTE_POSITIVO' ? 'text-green-700' : 'text-red-700'">
              {{ h.tipo_movimiento === 'AJUSTE_POSITIVO' ? '+' : '-' }}{{ fmt(h.tipo_movimiento === 'AJUSTE_POSITIVO' ? h.cantidad_entrada : h.cantidad_salida) }}
            </td>
            <td class="px-4 py-2 text-right font-mono text-xs">{{ fmt(h.stock_despues) }}</td>
            <td class="px-4 py-2 text-xs text-gray-600 max-w-xs truncate" :title="h.observacion">{{ h.observacion }}</td>
            <td class="px-4 py-2 text-xs font-mono text-gray-500">{{ h.usuario }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

const busqueda  = ref('')
const sugerencias = ref([])
const articulos = ref([])
const articulo  = ref(null)
const historial = ref([])
const guardando = ref(false)
const error     = ref('')
const exito     = ref('')

const form = ref({ cantidad_fisica: '', motivo: '', numero_documento: '' })

const diferencia = computed(() => {
  if (!articulo.value || form.value.cantidad_fisica === '') return 0
  return Math.round((parseFloat(form.value.cantidad_fisica) - parseFloat(articulo.value.stock_actual)) * 100000) / 100000
})

onMounted(async () => {
  const { data } = await api.get('/adquisiciones/articulos')
  articulos.value = data
  await cargarHistorial()
})

function filtrarArticulos() {
  const q = busqueda.value.toLowerCase().trim()
  if (!q) { sugerencias.value = []; return }
  sugerencias.value = articulos.value
    .filter(a => a.estado === 'ACTIVO' &&
      (a.codigo?.toLowerCase().includes(q) || a.nombre?.toLowerCase().includes(q)))
    .slice(0, 12)
}

function seleccionarArticulo(a) {
  articulo.value = a
  busqueda.value = `${a.codigo} — ${a.nombre}`
  sugerencias.value = []
  form.value = { cantidad_fisica: '', motivo: '', numero_documento: '' }
  error.value = ''
  exito.value = ''
}

async function guardar() {
  error.value = ''
  exito.value = ''
  if (!form.value.motivo.trim()) { error.value = 'El motivo es obligatorio.'; return }
  if (form.value.cantidad_fisica === '' || form.value.cantidad_fisica < 0) {
    error.value = 'Ingrese la cantidad física contada.'; return
  }
  guardando.value = true
  try {
    const { data } = await api.post('/adquisiciones/ajustes', {
      articulo_id:      articulo.value.id,
      cantidad_fisica:  form.value.cantidad_fisica,
      motivo:           form.value.motivo,
      numero_documento: form.value.numero_documento || null,
    })
    exito.value = `Ajuste registrado. Stock: ${data.stock_antes} → ${data.stock_despues}`
    articulo.value.stock_actual = data.stock_despues
    form.value = { cantidad_fisica: '', motivo: '', numero_documento: '' }
    await cargarHistorial()
    const idx = articulos.value.findIndex(a => a.id === articulo.value.id)
    if (idx !== -1) articulos.value[idx].stock_actual = data.stock_despues
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al registrar el ajuste'
  } finally { guardando.value = false }
}

async function cargarHistorial() {
  const { data } = await api.get('/adquisiciones/ajustes')
  historial.value = data
}

function fmt(v) { return parseFloat(v || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 5 }) }
function fmtFecha(v) {
  if (!v) return '—'
  return new Date(v).toLocaleString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>
