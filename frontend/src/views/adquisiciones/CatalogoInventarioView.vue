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
    <div class="flex gap-3 mb-4">
      <input v-model="busqueda" @input="cargar" type="text" placeholder="Buscar por código o descripción..."
        class="border rounded-lg px-3 py-2 text-sm w-72 focus:ring-2 focus:ring-amber-300 outline-none" />
      <select v-model="filtroNivel1" @change="cargar"
        class="border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none">
        <option value="">Todos los niveles 1</option>
        <option v-for="n in nivel1s" :key="n" :value="n">{{ n }}</option>
      </select>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="text-left px-4 py-3 text-gray-600 font-medium w-16">Nivel 1</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium w-24">Nivel 2</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Descripción</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Asociación Presupuestaria</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium w-24">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando">
            <td colspan="5" class="text-center py-8 text-gray-400">Cargando...</td>
          </tr>
          <tr v-else-if="!items.length">
            <td colspan="5" class="text-center py-8 text-gray-400">Sin resultados</td>
          </tr>
          <tr v-for="item in items" :key="item.nivel2" class="border-b hover:bg-gray-50">
            <td class="px-4 py-3 font-mono font-bold text-amber-700">{{ item.nivel1 }}</td>
            <td class="px-4 py-3 font-mono text-xs">{{ item.nivel2 }}</td>
            <td class="px-4 py-3">{{ item.descripcion }}</td>
            <td class="px-4 py-3 text-xs text-gray-500">{{ item.asociacion_presupuestaria || '-' }}</td>
            <td class="px-4 py-3 flex gap-2">
              <button @click="abrirEditar(item)" class="text-xs text-blue-600 hover:text-blue-800">Editar</button>
              <button @click="eliminar(item.nivel2)" class="text-xs text-red-500 hover:text-red-700">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Paginación -->
      <div v-if="paginacion.last_page > 1" class="flex justify-between items-center px-4 py-3 border-t text-sm text-gray-600">
        <span>{{ paginacion.from }}–{{ paginacion.to }} de {{ paginacion.total }}</span>
        <div class="flex gap-2">
          <button :disabled="paginacion.current_page === 1" @click="pagina--; cargar()"
            class="px-3 py-1 rounded border disabled:opacity-40 hover:bg-gray-50">‹</button>
          <button :disabled="paginacion.current_page === paginacion.last_page" @click="pagina++; cargar()"
            class="px-3 py-1 rounded border disabled:opacity-40 hover:bg-gray-50">›</button>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="modal.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-bold mb-4">{{ modal.editando ? 'Editar' : 'Nuevo' }} ítem del catálogo</h2>
        <div class="space-y-3">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-gray-600 mb-1">Nivel 1 * (2 dígitos)</label>
              <input v-model="modal.form.nivel1" :disabled="modal.editando" type="text" maxlength="2"
                class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none disabled:bg-gray-50" />
            </div>
            <div>
              <label class="block text-xs text-gray-600 mb-1">Nivel 2 * (6 dígitos)</label>
              <input v-model="modal.form.nivel2" :disabled="modal.editando" type="text" maxlength="6"
                class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none disabled:bg-gray-50" />
            </div>
          </div>
          <div>
            <label class="block text-xs text-gray-600 mb-1">Descripción *</label>
            <input v-model="modal.form.descripcion" type="text" maxlength="300"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
          </div>
          <div>
            <label class="block text-xs text-gray-600 mb-1">Asociación Presupuestaria</label>
            <input v-model="modal.form.asociacion_presupuestaria" type="text" maxlength="150"
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
const paginacion = ref({})
const cargando   = ref(false)
const guardando  = ref(false)
const errorModal = ref('')

const modal = ref({ show: false, editando: false, form: {} })

async function cargar() {
  cargando.value = true
  try {
    const { data } = await api.get('/adquisiciones/catalogo-inventario', {
      params: { q: busqueda.value, nivel1: filtroNivel1.value, page: pagina.value }
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

onMounted(() => Promise.all([cargar(), cargarNivel1s()]))

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
