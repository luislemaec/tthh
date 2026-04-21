<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Inventario de Artículos</h1>
      <div class="flex gap-3">
        <button @click="abrirConfiguracion"
          class="border border-amber-600 text-amber-700 px-4 py-2 rounded-lg text-sm hover:bg-amber-50">
          ⚙ Stock mínimo ({{ porcentajeMinimo }}%)
        </button>
        <button @click="abrirModalCrear"
          class="bg-amber-700 text-white px-4 py-2 rounded-lg text-sm hover:bg-amber-800">
          + Nuevo artículo
        </button>
      </div>
    </div>

    <!-- Filtro -->
    <div class="flex gap-3 mb-4">
      <input v-model="busqueda" type="text" placeholder="Buscar por nombre o código..."
        class="border rounded-lg px-3 py-2 text-sm w-64 focus:ring-2 focus:ring-amber-300 outline-none" />
      <select v-model="filtroEstado" class="border rounded-lg px-3 py-2 text-sm">
        <option value="">Todos</option>
        <option value="alerta">Solo alertas de stock</option>
        <option value="ACTIVO">Activos</option>
        <option value="INACTIVO">Inactivos</option>
      </select>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Código</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Nombre</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Categoría</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Marca</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Unidad</th>
            <th class="text-right px-4 py-3 text-gray-600 font-medium">Stock actual</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Estado</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!articulosFiltrados.length">
            <td colspan="7" class="text-center py-8 text-gray-400">Sin artículos</td>
          </tr>
          <tr v-for="a in articulosFiltrados" :key="a.id" class="border-b hover:bg-gray-50">
            <td class="px-4 py-3 font-mono text-xs">{{ a.codigo }}</td>
            <td class="px-4 py-3 font-medium">{{ a.nombre }}</td>
            <td class="px-4 py-3 text-gray-500">{{ a.categoria || '-' }}</td>
            <td class="px-4 py-3 text-gray-500">{{ a.marca || '-' }}</td>
            <td class="px-4 py-3 text-gray-500">{{ a.unidad_medida || '-' }}</td>
            <td class="px-4 py-3 text-right">
              <span :class="a.bajo_minimo ? 'text-red-600 font-bold' : 'text-gray-800'">
                {{ a.stock_actual }}
              </span>
              <span v-if="a.bajo_minimo" class="ml-1 text-xs bg-red-100 text-red-600 px-1.5 py-0.5 rounded-full">
                ⚠ bajo
              </span>
            </td>
            <td class="px-4 py-3">
              <span :class="a.estado === 'ACTIVO' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                class="px-2 py-0.5 rounded-full text-xs font-medium">{{ a.estado }}</span>
            </td>
            <td class="px-4 py-3 flex gap-2">
              <button @click="abrirEditar(a)" class="text-xs text-blue-600 hover:text-blue-800">Editar</button>
              <button v-if="a.estado === 'ACTIVO'" @click="inactivar(a.id)"
                class="text-xs text-red-500 hover:text-red-700">Inactivar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal crear/editar -->
    <div v-if="modal.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
        <h2 class="text-lg font-bold mb-4">{{ modal.editando ? 'Editar' : 'Nuevo' }} Artículo</h2>
        <div class="space-y-3">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-gray-600 mb-1">Código *</label>
              <input v-model="modal.form.codigo" type="text" maxlength="30"
                class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
            </div>
            <div>
              <label class="block text-xs text-gray-600 mb-1">Unidad de medida</label>
              <input v-model="modal.form.unidad_medida" type="text" maxlength="50"
                placeholder="ej: unidades, resmas, cajas"
                class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
            </div>
          </div>
          <div>
            <label class="block text-xs text-gray-600 mb-1">Nombre *</label>
            <input v-model="modal.form.nombre" type="text" maxlength="200"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
          </div>
          <div>
            <label class="block text-xs text-gray-600 mb-1">Categoría</label>
            <input v-model="modal.form.categoria" type="text" maxlength="100"
              placeholder="ej: Papelería, Limpieza, Informática"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
          </div>
          <div>
            <label class="block text-xs text-gray-600 mb-1">Marca</label>
            <input v-model="modal.form.marca" type="text" maxlength="100"
              placeholder="ej: HP, BIC, 3M"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
          </div>
          <div>
            <label class="block text-xs text-gray-600 mb-1">Descripción</label>
            <textarea v-model="modal.form.descripcion" rows="2" maxlength="500"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none"></textarea>
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

    <!-- Modal configuración porcentaje -->
    <div v-if="modalConfig.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
        <h2 class="text-lg font-bold mb-4">Configurar Stock Mínimo</h2>
        <p class="text-sm text-gray-600 mb-4">
          Se genera alerta cuando el stock actual es menor o igual al
          <b>{{ modalConfig.porcentaje }}%</b> del stock máximo histórico del artículo.
        </p>
        <div>
          <label class="block text-xs text-gray-600 mb-1">Porcentaje mínimo (%)</label>
          <input v-model="modalConfig.porcentaje" type="number" min="1" max="100"
            class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
        </div>
        <div class="flex justify-end gap-3 mt-5">
          <button @click="modalConfig.show = false" class="px-4 py-2 text-sm text-gray-600">Cancelar</button>
          <button @click="guardarConfiguracion"
            class="bg-amber-700 text-white px-5 py-2 rounded-lg text-sm hover:bg-amber-800">
            Guardar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

const articulos      = ref([])
const busqueda       = ref('')
const filtroEstado   = ref('')
const porcentajeMinimo = ref(20)
const guardando      = ref(false)
const errorModal     = ref('')

const modal = ref({ show: false, editando: false, id: null, form: {} })
const modalConfig = ref({ show: false, porcentaje: 20 })

const articulosFiltrados = computed(() => {
  return articulos.value.filter(a => {
    const q = busqueda.value.toLowerCase()
    const coincide = !q || a.nombre.toLowerCase().includes(q) || a.codigo.toLowerCase().includes(q)
    if (filtroEstado.value === 'alerta') return coincide && a.bajo_minimo
    if (filtroEstado.value) return coincide && a.estado === filtroEstado.value
    return coincide
  })
})

async function cargar() {
  const { data } = await api.get('/adquisiciones/articulos')
  articulos.value = data
}

async function cargarConfig() {
  const { data } = await api.get('/adquisiciones/configuracion')
  porcentajeMinimo.value = data.porcentaje_stock_minimo?.valor || 20
}

onMounted(async () => { await Promise.all([cargar(), cargarConfig()]) })

function abrirModalCrear() {
  modal.value = { show: true, editando: false, id: null, form: { codigo: '', nombre: '', descripcion: '', unidad_medida: '', categoria: '', marca: '' } }
  errorModal.value = ''
}

function abrirEditar(a) {
  modal.value = { show: true, editando: true, id: a.id, form: { codigo: a.codigo, nombre: a.nombre, descripcion: a.descripcion, unidad_medida: a.unidad_medida, categoria: a.categoria, marca: a.marca } }
  errorModal.value = ''
}

async function guardar() {
  errorModal.value = ''
  if (!modal.value.form.codigo || !modal.value.form.nombre) { errorModal.value = 'Código y nombre son requeridos.'; return }
  guardando.value = true
  try {
    if (modal.value.editando) {
      await api.put(`/adquisiciones/articulos/${modal.value.id}`, modal.value.form)
    } else {
      await api.post('/adquisiciones/articulos', modal.value.form)
    }
    modal.value.show = false
    await cargar()
  } catch (e) {
    errorModal.value = e.response?.data?.message || 'Error al guardar'
  } finally { guardando.value = false }
}

async function inactivar(id) {
  if (!confirm('¿Inactivar este artículo?')) return
  await api.patch(`/adquisiciones/articulos/${id}/inactivar`)
  await cargar()
}

function abrirConfiguracion() {
  modalConfig.value = { show: true, porcentaje: porcentajeMinimo.value }
}

async function guardarConfiguracion() {
  await api.put('/adquisiciones/configuracion', { porcentaje_stock_minimo: modalConfig.value.porcentaje })
  porcentajeMinimo.value = modalConfig.value.porcentaje
  modalConfig.value.show = false
  await cargar()
}
</script>
