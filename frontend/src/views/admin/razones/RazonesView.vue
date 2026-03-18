<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-800">Razones de Permiso</h1>
      <button @click="abrirModal()"
        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm font-medium">
        + Nueva Razón
      </button>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">#</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Descripción</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Tipo</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Descontable</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Nomenclatura</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando">
            <td colspan="6" class="text-center py-8 text-gray-400">Cargando...</td>
          </tr>
          <tr v-else-if="razones.length === 0">
            <td colspan="6" class="text-center py-8 text-gray-400">No hay razones registradas.</td>
          </tr>
          <tr v-for="r in razones" :key="r.secuencial" class="border-b hover:bg-gray-50">
            <td class="px-6 py-3 text-gray-500">{{ r.secuencial }}</td>
            <td class="px-6 py-3 font-medium text-gray-800">{{ r.descripcion }}</td>
            <td class="px-6 py-3 text-gray-600">{{ r.tipo_razon || '—' }}</td>
            <td class="px-6 py-3">
              <span :class="r.descontable === 'SI' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                class="px-2 py-0.5 rounded-full text-xs font-medium">{{ r.descontable?.trim() }}</span>
            </td>
            <td class="px-6 py-3 text-gray-600">{{ r.nomenclatura || '—' }}</td>
            <td class="px-6 py-3 flex gap-3">
              <button @click="abrirModal(r)" class="text-yellow-600 hover:underline text-xs">Editar</button>
              <button @click="eliminar(r.secuencial)" class="text-red-600 hover:underline text-xs">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="modal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-md space-y-4">
        <h2 class="text-lg font-semibold text-gray-700">
          {{ form.secuencial ? 'Editar Razón' : 'Nueva Razón' }}
        </h2>
        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Descripción *</label>
          <input v-model="form.descripcion" type="text" placeholder="Ej: ENFERMEDAD"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Tipo de Razón</label>
            <select v-model="form.tipo_razon"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">Seleccionar...</option>
              <option value="PERMISO">PERMISO</option>
              <option value="VACACION">VACACION</option>
              <option value="LICENCIA">LICENCIA</option>
              <option value="OTRO">OTRO</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">¿Descontable? *</label>
            <select v-model="form.descontable"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="SI">SI</option>
              <option value="NO">NO</option>
            </select>
          </div>
        </div>
	<div v-if="form.descontable === 'NO'">
          <label class="block text-sm font-medium text-gray-600 mb-1">Leyenda de Justificacion</label>
          <input v-model="form.leyenda_justificacion" type="text" maxlength="250"
           placeholder="Ej: CERTIFICADO MEDICO"
           class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Nomenclatura</label>
          <input v-model="form.nomenclatura" type="text" placeholder="Ej: ENF"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
        </div>
        <div v-if="error" class="text-red-600 text-sm bg-red-50 rounded p-2">{{ error }}</div>
        <div class="flex justify-end gap-3 pt-2">
          <button @click="modal = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
          <button @click="guardar" :disabled="guardando"
            class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700 disabled:opacity-50">
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

const razones   = ref([])
const cargando  = ref(false)
const modal     = ref(false)
const guardando = ref(false)
const error     = ref('')
const form      = ref({ secuencial: null, descripcion: '', descontable: 'SI', tipo_razon: '', nomenclatura: '', leyenda_justificacion: '' })

const cargar = async () => {
  cargando.value = true
  const { data } = await api.get('/admin/razones')
  razones.value = data
  cargando.value = false
}

const abrirModal = (r = null) => {
  error.value = ''
  form.value = r
    ? { secuencial: r.secuencial, descripcion: r.descripcion, descontable: r.descontable, tipo_razon: r.tipo_razon ? r.tipo_razon.trim() : '', nomenclatura: r.nomenclatura ? r.nomenclatura.trim() : '', leyenda_justificacion: r.leyenda_justificacion || '' }
    : { secuencial: null, descripcion: '', descontable: 'SI', tipo_razon: '', nomenclatura: '', leyenda_justificacion: '' }
  modal.value = true
}

const guardar = async () => {
  if (!form.value.descripcion) { error.value = 'La descripción es requerida.'; return }
  guardando.value = true
  error.value = ''
  try {
    if (form.value.secuencial) {
      await api.put(`/admin/razones/${form.value.secuencial}`, form.value)
    } else {
      await api.post('/admin/razones', form.value)
    }
    modal.value = false
    cargar()
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al guardar.'
  } finally {
    guardando.value = false
  }
}

const eliminar = async (id) => {
  if (!confirm('¿Seguro que deseas eliminar esta razón?')) return
  try {
    await api.delete(`/admin/razones/${id}`)
    cargar()
  } catch (e) {
    alert(e.response?.data?.message || 'Error al eliminar.')
  }
}

onMounted(cargar)
</script>
