<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-800">Departamentos</h1>
      <button @click="abrirModal()"
        class="bg-[#0b5447] text-white px-4 py-2 rounded-lg hover:bg-[#00372e] text-sm font-medium">
        + Nuevo Departamento
      </button>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">#</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Nombre</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Área Padre</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Centro de Costo</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Empleados</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando">
            <td colspan="6" class="text-center py-8 text-gray-400">Cargando...</td>
          </tr>
          <tr v-else-if="departamentos.length === 0">
            <td colspan="6" class="text-center py-8 text-gray-400">No hay departamentos registrados.</td>
          </tr>
          <tr v-for="dep in departamentos" :key="dep.id_depto" class="border-b hover:bg-gray-50">
            <td class="px-6 py-3 text-gray-500">{{ dep.id_depto }}</td>
            <td class="px-6 py-3 font-medium text-gray-800">
              <span v-if="dep.padre_id" class="text-gray-400 mr-1">↳</span>
              {{ dep.nombre_depto }}
            </td>
            <td class="px-6 py-3 text-gray-500 text-xs">{{ dep.padre?.nombre_depto || '—' }}</td>
            <td class="px-6 py-3 text-gray-600">{{ dep.centro_de_costo || '—' }}</td>
            <td class="px-6 py-3 text-gray-600">{{ dep.empleados_count ?? '—' }}</td>
            <td class="px-6 py-3 flex gap-3">
              <button @click="abrirModal(dep)" class="text-yellow-600 hover:underline text-xs">Editar</button>
              <button @click="eliminar(dep.id_depto)" class="text-red-600 hover:underline text-xs">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="modal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-md space-y-4">
        <h2 class="text-lg font-semibold text-gray-700">
          {{ form.id_depto ? 'Editar Departamento' : 'Nuevo Departamento' }}
        </h2>

        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Nombre *</label>
          <input v-model="form.nombre_depto" type="text" placeholder="Ej: RECURSOS HUMANOS"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Área Padre</label>
          <select v-model="form.padre_id"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
            <option :value="null">— Sin área padre (nivel raíz) —</option>
            <option v-for="dep in padresDisponibles" :key="dep.id_depto" :value="dep.id_depto">
              {{ dep.nombre_depto }}
            </option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Centro de Costo</label>
          <input v-model="form.centro_de_costo" type="text" placeholder="Ej: RRH"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
        </div>

        <div v-if="error" class="text-red-600 text-sm bg-red-50 rounded p-2">{{ error }}</div>

        <div class="flex justify-end gap-3 pt-2">
          <button @click="modal = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
          <button @click="guardar" :disabled="guardando"
            class="px-4 py-2 rounded-lg bg-[#0b5447] text-white text-sm hover:bg-[#00372e] disabled:opacity-50">
            {{ guardando ? 'Guardando...' : 'Guardar' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

const departamentos = ref([])
const cargando      = ref(false)
const modal         = ref(false)
const guardando     = ref(false)
const error         = ref('')
const form          = ref({ id_depto: null, nombre_depto: '', centro_de_costo: '', padre_id: null })

// Departamentos disponibles como padre: excluir el que se está editando
const padresDisponibles = computed(() =>
  departamentos.value.filter(d => d.id_depto !== form.value.id_depto)
)

const cargar = async () => {
  cargando.value = true
  const { data } = await api.get('/admin/departamentos')
  departamentos.value = data
  cargando.value = false
}

const abrirModal = (dep = null) => {
  error.value = ''
  form.value = dep
    ? { id_depto: dep.id_depto, nombre_depto: dep.nombre_depto, centro_de_costo: dep.centro_de_costo, padre_id: dep.padre_id ?? null }
    : { id_depto: null, nombre_depto: '', centro_de_costo: '', padre_id: null }
  modal.value = true
}

const guardar = async () => {
  if (!form.value.nombre_depto) { error.value = 'El nombre es requerido.'; return }
  guardando.value = true
  error.value = ''
  try {
    if (form.value.id_depto) {
      await api.put(`/admin/departamentos/${form.value.id_depto}`, form.value)
    } else {
      await api.post('/admin/departamentos', form.value)
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
  if (!confirm('¿Seguro que deseas eliminar este departamento?')) return
  try {
    await api.delete(`/admin/departamentos/${id}`)
    cargar()
  } catch (e) {
    alert(e.response?.data?.message || 'Error al eliminar.')
  }
}

onMounted(cargar)
</script>
