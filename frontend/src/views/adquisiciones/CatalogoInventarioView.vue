<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Catálogo de Inventarios MF</h1>
      <button @click="abrirCrear"
        class="bg-amber-700 text-white px-4 py-2 rounded-lg text-sm hover:bg-amber-800">
        + Nuevo ítem
      </button>
    </div>

    <!-- Filtros -->
    <div class="flex gap-3 mb-3 flex-wrap items-end">
      <div>
        <label class="block text-xs text-gray-500 mb-1">Categoría (Nivel 1)</label>
        <select v-model="filtroNivel1" class="border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none w-72">
          <option value="">Todas las categorías</option>
          <option v-for="n in nivel1s" :key="n.nivel1" :value="n.nivel1">{{ n.nivel1 }} — {{ n.descripcion }}</option>
        </select>
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">Texto</label>
        <input v-model="busqueda" type="text" placeholder="Buscar por código o descripción..."
          class="border rounded-lg px-3 py-2 text-sm w-72 focus:ring-2 focus:ring-amber-300 outline-none" />
      </div>
      <button @click="buscar" class="bg-amber-700 text-white px-5 py-2 rounded-lg text-sm hover:bg-amber-800">
        Buscar
      </button>
    </div>

    <!-- Contador -->
    <div class="mb-3">
      <span v-if="!hasBuscado"
        class="inline-block bg-blue-50 text-blue-700 text-xs font-semibold px-3 py-1 rounded-full border border-blue-200">
        Seleccione los filtros y presione Buscar
      </span>
      <span v-else-if="paginacion.total > 0"
        class="inline-block bg-green-100 text-green-800 text-xs font-semibold px-3 py-1 rounded-full border border-green-300">
        Se encontraron {{ paginacion.total }} ítems
        <template v-if="paginacion.last_page > 1"> — página {{ paginacion.current_page }} de {{ paginacion.last_page }}</template>
      </span>
      <span v-else-if="!cargando"
        class="inline-block bg-red-100 text-red-700 text-xs font-semibold px-3 py-1 rounded-full border border-red-300">
        No se encontraron ítems en el catálogo
      </span>
    </div>

    <div class="bg-white rounded-xl shadow overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr style="background-color: #4a5e3a;">
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap w-16">Niv. 1</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap w-24">Niv. 2</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">Categoría (Nivel 1)</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">Descripción (Nivel 2)</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">Asociación Presupuestaria</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap w-24">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando">
            <td colspan="6" class="text-center py-8 text-gray-400">Cargando...</td>
          </tr>
          <tr v-else-if="!items.length">
            <td colspan="6" class="text-center py-8 text-gray-400">Sin resultados</td>
          </tr>
          <tr v-for="item in items" :key="item.nivel2" class="border-b hover:bg-amber-50">
            <td class="px-4 py-3 font-mono font-bold text-amber-700 whitespace-nowrap">{{ item.nivel1 }}</td>
            <td class="px-4 py-3 font-mono text-xs whitespace-nowrap">{{ item.nivel2 }}</td>
            <td class="px-4 py-3 text-xs text-gray-500">{{ item.descripcion_nivel1 }}</td>
            <td class="px-4 py-3">{{ item.descripcion }}</td>
            <td class="px-4 py-3 text-xs text-gray-500">{{ item.asociacion_presupuestaria || '-' }}</td>
            <td class="px-4 py-3 whitespace-nowrap flex gap-2">
              <button @click="abrirEditar(item)" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Editar</button>
              <button @click="eliminar(item.nivel2)" class="text-xs text-red-500 hover:text-red-700">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Paginación -->
      <div class="flex items-center justify-between px-4 py-3 border-t text-sm text-gray-600 flex-wrap gap-2">
        <div class="flex items-center gap-2">
          <span>Filas por página:</span>
          <select v-model="porPagina" @change="pagina = 1; cargar()" class="border rounded px-2 py-1 text-sm">
            <option :value="10">10</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
            <option :value="99999">Todos</option>
          </select>
        </div>
        <span class="text-gray-500">
          {{ paginacion.from || 0 }}–{{ paginacion.to || 0 }} de {{ paginacion.total || 0 }}
        </span>
        <div class="flex items-center gap-1">
          <button @click="pagina = 1; cargar()" :disabled="paginacion.current_page === 1"
            class="px-2 py-1 rounded border disabled:opacity-40 hover:bg-gray-50 text-xs">«</button>
          <button @click="pagina--; cargar()" :disabled="paginacion.current_page === 1"
            class="px-3 py-1 rounded border disabled:opacity-40 hover:bg-gray-50">‹</button>
          <span class="px-3 py-1 font-medium">{{ paginacion.current_page || 1 }} / {{ paginacion.last_page || 1 }}</span>
          <button @click="pagina++; cargar()" :disabled="paginacion.current_page === paginacion.last_page"
            class="px-3 py-1 rounded border disabled:opacity-40 hover:bg-gray-50">›</button>
          <button @click="pagina = paginacion.last_page; cargar()" :disabled="paginacion.current_page === paginacion.last_page"
            class="px-2 py-1 rounded border disabled:opacity-40 hover:bg-gray-50 text-xs">»</button>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="modal.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4" style="background-color:#4a5e3a;">
          <h2 class="text-lg font-bold text-white">{{ modal.editando ? 'Editar' : 'Nuevo' }} ítem del catálogo</h2>
        </div>
        <div class="p-6">
        <div class="space-y-3">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-gray-600 mb-1">Nivel 1 * (2 dígitos)</label>
              <input v-model="modal.form.nivel1" v-uppercase :disabled="modal.editando" type="text" maxlength="2"
                class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none disabled:bg-gray-50" />
            </div>
            <div>
              <label class="block text-xs text-gray-600 mb-1">Nivel 2 * (6 dígitos)</label>
              <input v-model="modal.form.nivel2" v-uppercase :disabled="modal.editando" type="text" maxlength="6"
                class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none disabled:bg-gray-50" />
            </div>
          </div>
          <div>
            <label class="block text-xs text-gray-600 mb-1">Descripción *</label>
            <input v-model="modal.form.descripcion" v-uppercase type="text" maxlength="300"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
          </div>
          <div>
            <label class="block text-xs text-gray-600 mb-1">Asociación Presupuestaria</label>
            <input v-model="modal.form.asociacion_presupuestaria" v-uppercase type="text" maxlength="150"
              placeholder="ej: 530804-630804-730804"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
          </div>
        </div>
        <p v-if="errorModal" class="text-red-600 text-sm mt-3">{{ errorModal }}</p>
        <div class="flex justify-end gap-3 mt-5">
          <button @click="modal.show = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
          <button @click="guardar" :disabled="guardando"
            class="bg-amber-700 text-white px-5 py-2 rounded-lg text-sm hover:bg-amber-800 disabled:opacity-50">
            {{ guardando ? 'Guardando...' : 'Guardar' }}
          </button>
        </div>
        </div><!-- /p-6 -->
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const items      = ref([])
const nivel1s    = ref([])
const busqueda   = ref('')
const filtroNivel1 = ref('')
const pagina     = ref(1)
const porPagina  = ref(25)
const paginacion = ref({})
const cargando   = ref(false)
const guardando  = ref(false)
const errorModal = ref('')

const modal = ref({ show: false, editando: false, form: {} })

const hasBuscado = ref(false)

async function buscar() {
  hasBuscado.value = true
  pagina.value = 1
  await cargar()
}

async function cargar() {
  cargando.value = true
  try {
    const perPage = porPagina.value >= 99999 ? 99999 : porPagina.value
    const { data } = await api.get('/adquisiciones/catalogo-inventario', {
      params: { q: busqueda.value, nivel1: filtroNivel1.value, page: pagina.value, por_pagina: perPage }
    })
    items.value      = data.data
    paginacion.value = data
  } finally {
    cargando.value = false
  }
}

async function cargarNivel1s() {
  const { data } = await api.get('/adquisiciones/catalogo-inventario/nivel1s')
  nivel1s.value = data
}

onMounted(() => cargarNivel1s())

function abrirCrear() {
  modal.value = { show: true, editando: false, form: { nivel1: '', nivel2: '', descripcion: '', asociacion_presupuestaria: '' } }
  errorModal.value = ''
}

function abrirEditar(item) {
  modal.value = { show: true, editando: true, form: { ...item } }
  errorModal.value = ''
}

async function guardar() {
  errorModal.value = ''
  const { nivel1, nivel2, descripcion } = modal.value.form
  if (!nivel1 || nivel1.length !== 2) { errorModal.value = 'Nivel 1 debe tener exactamente 2 dígitos.'; return }
  if (!modal.value.editando && (!nivel2 || nivel2.length !== 6)) { errorModal.value = 'Nivel 2 debe tener exactamente 6 dígitos.'; return }
  if (!descripcion) { errorModal.value = 'La descripción es requerida.'; return }

  guardando.value = true
  try {
    if (modal.value.editando) {
      await api.put(`/adquisiciones/catalogo-inventario/${modal.value.form.nivel2}`, modal.value.form)
    } else {
      await api.post('/adquisiciones/catalogo-inventario', modal.value.form)
    }
    modal.value.show = false
    await Promise.all([cargar(), cargarNivel1s()])
  } catch (e) {
    errorModal.value = e.response?.data?.message || 'Error al guardar'
  } finally {
    guardando.value = false
  }
}

async function eliminar(nivel2) {
  if (!confirm(`¿Eliminar el ítem ${nivel2}?`)) return
  try {
    await api.delete(`/adquisiciones/catalogo-inventario/${nivel2}`)
    await cargar()
  } catch (e) {
    alert(e.response?.data?.message || 'Error al eliminar')
  }
}
</script>
