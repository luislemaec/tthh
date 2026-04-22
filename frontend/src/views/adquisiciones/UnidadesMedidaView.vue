<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Unidades de Medida</h1>
      <button @click="abrirCrear" class="bg-amber-700 text-white px-4 py-2 rounded-lg text-sm hover:bg-amber-800">
        + Nueva unidad
      </button>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden max-w-xl">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Nombre</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Abreviatura</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Estado</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!unidades.length">
            <td colspan="4" class="text-center py-8 text-gray-400">Sin unidades registradas</td>
          </tr>
          <tr v-for="u in unidades" :key="u.id" class="border-b hover:bg-gray-50">
            <td class="px-4 py-3 font-medium">{{ u.nombre }}</td>
            <td class="px-4 py-3 font-mono text-gray-600">{{ u.abreviatura }}</td>
            <td class="px-4 py-3">
              <span :class="u.activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                class="px-2 py-0.5 rounded-full text-xs font-medium">
                {{ u.activo ? 'Activo' : 'Inactivo' }}
              </span>
            </td>
            <td class="px-4 py-3 flex gap-3">
              <button @click="abrirEditar(u)" class="text-xs text-blue-600 hover:text-blue-800">Editar</button>
              <button @click="toggle(u)" class="text-xs"
                :class="u.activo ? 'text-red-500 hover:text-red-700' : 'text-green-600 hover:text-green-800'">
                {{ u.activo ? 'Inactivar' : 'Activar' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="modal.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
        <h2 class="text-lg font-bold mb-4">{{ modal.editando ? 'Editar' : 'Nueva' }} unidad de medida</h2>
        <div class="space-y-3">
          <div>
            <label class="block text-xs text-gray-600 mb-1">Nombre *</label>
            <input v-model="modal.form.nombre" type="text" maxlength="60"
              placeholder="ej: UNIDADES, RESMAS, LITROS"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
          </div>
          <div>
            <label class="block text-xs text-gray-600 mb-1">Abreviatura *</label>
            <input v-model="modal.form.abreviatura" type="text" maxlength="15"
              placeholder="ej: u, resma, L"
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

const unidades  = ref([])
const guardando = ref(false)
const error     = ref('')
const modal     = ref({ show: false, editando: false, id: null, form: {} })

async function cargar() {
  const { data } = await api.get('/adquisiciones/unidades-medida')
  unidades.value = data
}

onMounted(cargar)

function abrirCrear() {
  modal.value = { show: true, editando: false, id: null, form: { nombre: '', abreviatura: '' } }
  error.value = ''
}

function abrirEditar(u) {
  modal.value = { show: true, editando: true, id: u.id, form: { nombre: u.nombre, abreviatura: u.abreviatura } }
  error.value = ''
}

async function guardar() {
  error.value = ''
  if (!modal.value.form.nombre || !modal.value.form.abreviatura) {
    error.value = 'Nombre y abreviatura son requeridos.'; return
  }
  guardando.value = true
  try {
    if (modal.value.editando) {
      await api.put(`/adquisiciones/unidades-medida/${modal.value.id}`, modal.value.form)
    } else {
      await api.post('/adquisiciones/unidades-medida', modal.value.form)
    }
    modal.value.show = false
    await cargar()
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al guardar'
  } finally { guardando.value = false }
}

async function toggle(u) {
  await api.patch(`/adquisiciones/unidades-medida/${u.id}/toggle`)
  await cargar()
}
</script>
