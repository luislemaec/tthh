<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Tasas de IVA</h1>
      <button @click="abrirCrear" class="bg-amber-700 text-white px-4 py-2 rounded-lg text-sm hover:bg-amber-800">
        + Nueva tasa
      </button>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden max-w-xl">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Descripción</th>
            <th class="text-right px-4 py-3 text-gray-600 font-medium">Porcentaje</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Estado</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!tasas.length">
            <td colspan="4" class="text-center py-8 text-gray-400">Sin tasas registradas</td>
          </tr>
          <tr v-for="t in tasas" :key="t.id" class="border-b hover:bg-gray-50">
            <td class="px-4 py-3 font-medium">{{ t.descripcion }}</td>
            <td class="px-4 py-3 text-right font-mono">{{ t.porcentaje }}%</td>
            <td class="px-4 py-3">
              <span :class="t.activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                class="px-2 py-0.5 rounded-full text-xs font-medium">
                {{ t.activo ? 'Activo' : 'Inactivo' }}
              </span>
            </td>
            <td class="px-4 py-3 flex gap-3">
              <button @click="abrirEditar(t)" class="text-xs text-blue-600 hover:text-blue-800">Editar</button>
              <button @click="toggle(t)" class="text-xs" :class="t.activo ? 'text-red-500 hover:text-red-700' : 'text-green-600 hover:text-green-800'">
                {{ t.activo ? 'Desactivar' : 'Activar' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="modal.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
        <h2 class="text-lg font-bold mb-4">{{ modal.editando ? 'Editar' : 'Nueva' }} tasa de IVA</h2>
        <div class="space-y-3">
          <div>
            <label class="block text-xs text-gray-600 mb-1">Descripción *</label>
            <input v-model="modal.form.descripcion" type="text" maxlength="50"
              placeholder="ej: IVA 15%, IVA 0% (Exento)"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
          </div>
          <div>
            <label class="block text-xs text-gray-600 mb-1">Porcentaje (%) *</label>
            <input v-model="modal.form.porcentaje" type="number" min="0" max="100" step="0.01"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
          </div>
        </div>
        <p v-if="error" class="text-red-600 text-sm mt-3">{{ error }}</p>
        <div class="flex justify-end gap-3 mt-5">
          <button @click="modal.show = false" class="px-4 py-2 text-sm text-gray-600">Cancelar</button>
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

const tasas    = ref([])
const guardando = ref(false)
const error    = ref('')
const modal    = ref({ show: false, editando: false, id: null, form: {} })

async function cargar() {
  const { data } = await api.get('/adquisiciones/iva')
  tasas.value = data
}

onMounted(cargar)

function abrirCrear() {
  modal.value = { show: true, editando: false, id: null, form: { descripcion: '', porcentaje: '' } }
  error.value = ''
}

function abrirEditar(t) {
  modal.value = { show: true, editando: true, id: t.id, form: { descripcion: t.descripcion, porcentaje: t.porcentaje } }
  error.value = ''
}

async function guardar() {
  error.value = ''
  if (!modal.value.form.descripcion || modal.value.form.porcentaje === '') { error.value = 'Todos los campos son requeridos.'; return }
  guardando.value = true
  try {
    if (modal.value.editando) {
      await api.put(`/adquisiciones/iva/${modal.value.id}`, modal.value.form)
    } else {
      await api.post('/adquisiciones/iva', modal.value.form)
    }
    modal.value.show = false
    await cargar()
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al guardar'
  } finally { guardando.value = false }
}

async function toggle(t) {
  await api.patch(`/adquisiciones/iva/${t.id}/toggle`)
  await cargar()
}
</script>
