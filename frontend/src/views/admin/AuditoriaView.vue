<template>
  <div class="p-4 max-w-7xl mx-auto">
    <h1 class="text-xl font-bold mb-4">Auditoría del Aplicativo</h1>

    <!-- Filtros -->
    <div class="bg-white border rounded p-4 mb-4 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
      <div>
        <label class="block text-xs font-medium mb-1">Módulo</label>
        <select v-model="filtros.modulo" class="w-full border rounded px-2 py-1.5 text-sm">
          <option value="">Todos</option>
          <option value="talento">Talento Humano</option>
          <option value="adquisiciones">Adquisiciones</option>
          <option value="transportes">Transportes</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium mb-1">Acción</label>
        <select v-model="filtros.accion" class="w-full border rounded px-2 py-1.5 text-sm">
          <option value="">Todas</option>
          <option v-for="a in acciones" :key="a" :value="a">{{ a }}</option>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium mb-1">Usuario (cédula)</label>
        <input v-model="filtros.usuario_id" type="text" class="w-full border rounded px-2 py-1.5 text-sm" placeholder="Ej: 1234567890" />
      </div>
      <div>
        <label class="block text-xs font-medium mb-1">Desde</label>
        <input v-model="filtros.fecha_desde" type="date" class="w-full border rounded px-2 py-1.5 text-sm" />
      </div>
      <div>
        <label class="block text-xs font-medium mb-1">Hasta</label>
        <input v-model="filtros.fecha_hasta" type="date" class="w-full border rounded px-2 py-1.5 text-sm" />
      </div>
      <div>
        <label class="block text-xs font-medium mb-1">Descripción</label>
        <input v-model="filtros.descripcion" type="text" class="w-full border rounded px-2 py-1.5 text-sm" placeholder="Buscar..." />
      </div>
    </div>

    <div class="flex gap-2 mb-4">
      <button @click="buscar" class="bg-blue-600 text-white px-4 py-1.5 rounded text-sm hover:bg-blue-700">Buscar</button>
      <button @click="limpiar" class="bg-gray-200 text-gray-700 px-4 py-1.5 rounded text-sm hover:bg-gray-300">Limpiar</button>
      <span class="ml-auto text-sm text-gray-500">{{ total }} registros</span>
    </div>

    <!-- Tabla -->
    <div class="bg-white border rounded overflow-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-100 text-xs uppercase text-gray-600">
          <tr>
            <th class="px-3 py-2 text-left">Fecha/Hora</th>
            <th class="px-3 py-2 text-left">Usuario</th>
            <th class="px-3 py-2 text-left">Tabla</th>
            <th class="px-3 py-2 text-left">Acción</th>
            <th class="px-3 py-2 text-left">Descripción</th>
            <th class="px-3 py-2 text-left">IP</th>
            <th class="px-3 py-2 text-center">Detalle</th>
          </tr>
        </thead>
        <tbody>
          <template v-if="registros.length === 0">
            <tr>
              <td colspan="7" class="text-center py-8 text-gray-400">
                {{ cargando ? 'Cargando...' : 'No hay registros' }}
              </td>
            </tr>
          </template>
          <template v-for="r in registros" :key="r.id">
            <tr class="border-t hover:bg-gray-50 cursor-pointer" @click="toggleDetalle(r.id)">
              <td class="px-3 py-2 whitespace-nowrap text-xs">{{ formatFecha(r.created_at) }}</td>
              <td class="px-3 py-2 text-xs">{{ r.nombre_usuario }}</td>
              <td class="px-3 py-2 text-xs font-mono text-gray-500">{{ r.tabla }}</td>
              <td class="px-3 py-2">
                <span :class="badgeAccion(r.accion)" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ r.accion }}</span>
              </td>
              <td class="px-3 py-2 text-xs max-w-xs truncate">{{ r.descripcion }}</td>
              <td class="px-3 py-2 text-xs text-gray-500">{{ r.ip_origen }}</td>
              <td class="px-3 py-2 text-center">
                <span class="text-blue-500 text-xs">{{ expandido === r.id ? '▲' : '▼' }}</span>
              </td>
            </tr>
            <tr v-if="expandido === r.id" class="bg-blue-50 border-t">
              <td colspan="7" class="px-4 py-3">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <p class="text-xs font-semibold text-gray-500 mb-1">Datos anteriores</p>
                    <pre class="text-xs bg-white border rounded p-2 overflow-auto max-h-48 whitespace-pre-wrap">{{ formatJson(r.datos_anteriores) }}</pre>
                  </div>
                  <div>
                    <p class="text-xs font-semibold text-gray-500 mb-1">Datos nuevos</p>
                    <pre class="text-xs bg-white border rounded p-2 overflow-auto max-h-48 whitespace-pre-wrap">{{ formatJson(r.datos_nuevos) }}</pre>
                  </div>
                </div>
              </td>
            </tr>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Paginación -->
    <div v-if="lastPage > 1" class="flex justify-center gap-2 mt-4">
      <button @click="irPagina(paginaActual - 1)" :disabled="paginaActual === 1"
        class="px-3 py-1 border rounded text-sm disabled:opacity-40 hover:bg-gray-100">←</button>
      <span class="px-3 py-1 text-sm">Página {{ paginaActual }} / {{ lastPage }}</span>
      <button @click="irPagina(paginaActual + 1)" :disabled="paginaActual === lastPage"
        class="px-3 py-1 border rounded text-sm disabled:opacity-40 hover:bg-gray-100">→</button>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import axios from 'axios'

const registros     = ref([])
const total         = ref(0)
const lastPage      = ref(1)
const paginaActual  = ref(1)
const cargando      = ref(false)
const expandido     = ref(null)

const acciones = [
  'CREAR', 'ACTUALIZAR', 'ASIGNAR_ROL', 'REVOCAR_ROL',
  'APROBAR', 'NEGAR', 'ELIMINAR', 'AUTORIZAR', 'CONFIRMAR', 'LIQUIDAR',
  'CONFIRMAR_INGRESO', 'REVERSAR_INGRESO', 'CONFIRMAR_EGRESO', 'REVERSAR_EGRESO',
  'APROBAR_MOV', 'NEGAR_MOV', 'ORDEN_TRABAJO', 'EN_TALLER', 'FINALIZAR_MANT',
  'AJUSTE_POSITIVO', 'AJUSTE_NEGATIVO', 'DESPACHAR',
]

const filtros = reactive({
  modulo: '', accion: '', usuario_id: '',
  fecha_desde: '', fecha_hasta: '', descripcion: '',
})

async function buscar(pagina = 1) {
  cargando.value = true
  expandido.value = null
  try {
    const params = { ...filtros, page: pagina, per_page: 50 }
    Object.keys(params).forEach(k => { if (!params[k]) delete params[k] })
    const { data } = await axios.get('/api/admin/auditoria', { params })
    registros.value    = data.data
    total.value        = data.total
    lastPage.value     = data.last_page
    paginaActual.value = pagina
  } catch (e) {
    registros.value = []
  } finally {
    cargando.value = false
  }
}

function limpiar() {
  Object.assign(filtros, { modulo: '', accion: '', usuario_id: '', fecha_desde: '', fecha_hasta: '', descripcion: '' })
  buscar()
}

function irPagina(p) {
  if (p >= 1 && p <= lastPage.value) buscar(p)
}

function toggleDetalle(id) {
  expandido.value = expandido.value === id ? null : id
}

function formatFecha(f) {
  if (!f) return ''
  const d = new Date(f)
  return d.toLocaleString('es-EC', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function formatJson(val) {
  if (!val) return '—'
  try {
    const obj = typeof val === 'string' ? JSON.parse(val) : val
    return JSON.stringify(obj, null, 2)
  } catch {
    return val
  }
}

function badgeAccion(accion) {
  const mapa = {
    CREAR: 'bg-green-100 text-green-800',
    ACTUALIZAR: 'bg-blue-100 text-blue-800',
    APROBAR: 'bg-emerald-100 text-emerald-800',
    CONFIRMAR_INGRESO: 'bg-emerald-100 text-emerald-800',
    CONFIRMAR_EGRESO: 'bg-emerald-100 text-emerald-800',
    NEGAR: 'bg-red-100 text-red-800',
    ELIMINAR: 'bg-red-100 text-red-800',
    REVERSAR_INGRESO: 'bg-orange-100 text-orange-800',
    REVERSAR_EGRESO: 'bg-orange-100 text-orange-800',
    ASIGNAR_ROL: 'bg-purple-100 text-purple-800',
    REVOCAR_ROL: 'bg-purple-100 text-purple-800',
  }
  return mapa[accion] ?? 'bg-gray-100 text-gray-700'
}

// Cargar al montar
buscar()
</script>
