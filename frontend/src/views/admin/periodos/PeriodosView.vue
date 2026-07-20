<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-800">Períodos de Planificación de Vacaciones</h1>
      <button @click="abrirModalNuevo"
        class="bg-[#0b5447] text-white px-4 py-2 rounded-lg hover:bg-[#00372e] text-sm font-medium">
        + Nuevo Período
      </button>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Año Vacaciones</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Habilitado Desde</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Habilitado Hasta</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Estado</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando">
            <td colspan="5" class="text-center py-8 text-gray-400">Cargando...</td>
          </tr>
          <tr v-else-if="periodos.length === 0">
            <td colspan="5" class="text-center py-8 text-gray-400">No hay períodos registrados</td>
          </tr>
          <tr v-for="p in periodos" :key="p.id" class="border-b hover:bg-gray-50">
            <td class="px-4 py-3 font-medium">{{ p.anio }}</td>
            <td class="px-4 py-3 text-gray-600">{{ p.fecha_inicio }}</td>
            <td class="px-4 py-3 text-gray-600">{{ p.fecha_fin }}</td>
            <td class="px-4 py-3">
              <span :class="p.estado === 'ACTIVO' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
                class="px-2 py-1 rounded-full text-xs font-medium">
                {{ p.estado }}
              </span>
            </td>
            <td class="px-4 py-3">
              <button @click="abrirModalEditar(p)"
                class="text-[#0b5447] hover:underline text-xs font-medium">Editar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal Nuevo / Editar -->
    <div v-if="modalForm" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-md overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4" style="background-color:#0b5447;">
          <h2 class="text-lg font-semibold text-white">{{ editando ? 'Editar Período' : 'Nuevo Período de Planificación' }}</h2>
          <button @click="modalForm = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-6 space-y-4">
        <div class="space-y-3">
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Año de vacaciones *</label>
            <input v-model.number="form.anio" type="number" min="2024" max="2100"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
            <p class="text-xs text-gray-400 mt-1">Año al que corresponden las vacaciones a planificar</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Habilitado desde *</label>
            <input v-model="form.fecha_inicio" type="date"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Habilitado hasta *</label>
            <input v-model="form.fecha_fin" type="date"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div v-if="editando">
            <label class="block text-sm font-medium text-gray-600 mb-1">Estado</label>
            <select v-model="form.estado"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
              <option value="ACTIVO">ACTIVO</option>
              <option value="INACTIVO">INACTIVO</option>
            </select>
          </div>
          <div v-if="errorForm" class="text-red-600 text-sm bg-red-50 rounded p-2">{{ errorForm }}</div>
        </div>
        <div class="flex justify-end gap-3 pt-2">
          <button @click="modalForm = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
          <button @click="guardar" :disabled="guardando"
            class="px-4 py-2 rounded-lg bg-[#0b5447] text-white text-sm hover:bg-[#00372e] disabled:opacity-50">
            {{ guardando ? 'Guardando...' : 'Guardar' }}
          </button>
        </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const periodos  = ref([])
const cargando  = ref(false)
const guardando = ref(false)
const modalForm = ref(false)
const editando  = ref(null)
const errorForm = ref('')

const form = ref({ anio: new Date().getFullYear() + 1, fecha_inicio: '', fecha_fin: '', estado: 'ACTIVO' })

const cargar = async () => {
  cargando.value = true
  try {
    const { data } = await api.get('/admin/periodos-planificacion')
    periodos.value = data
  } finally {
    cargando.value = false
  }
}

const abrirModalNuevo = () => {
  editando.value = null
  errorForm.value = ''
  form.value = { anio: new Date().getFullYear() + 1, fecha_inicio: '', fecha_fin: '', estado: 'ACTIVO' }
  modalForm.value = true
}

const abrirModalEditar = (p) => {
  editando.value  = p.id
  errorForm.value = ''
  form.value = { anio: p.anio, fecha_inicio: p.fecha_inicio, fecha_fin: p.fecha_fin, estado: p.estado }
  modalForm.value = true
}

const guardar = async () => {
  errorForm.value = ''
  guardando.value = true
  try {
    if (editando.value) {
      await api.put(`/admin/periodos-planificacion/${editando.value}`, form.value)
    } else {
      await api.post('/admin/periodos-planificacion', form.value)
    }
    modalForm.value = false
    cargar()
  } catch (e) {
    errorForm.value = e.response?.data?.message || 'Error al guardar'
  } finally {
    guardando.value = false
  }
}

onMounted(cargar)
</script>
