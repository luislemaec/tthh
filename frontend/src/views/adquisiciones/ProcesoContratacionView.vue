<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Procesos de Contratación</h1>
      <button @click="abrirCrear" class="bg-green-700 text-white px-4 py-2 rounded-lg text-sm hover:bg-green-800">
        + Nuevo proceso
      </button>
    </div>

    <div class="mb-3">
      <span v-if="procesos.length > 0"
        class="inline-block bg-green-100 text-green-800 text-xs font-semibold px-3 py-1 rounded-full border border-green-300">
        {{ procesos.length }} proceso{{ procesos.length !== 1 ? 's' : '' }} registrado{{ procesos.length !== 1 ? 's' : '' }}
      </span>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden max-w-2xl">
      <table class="w-full text-sm">
        <thead>
          <tr style="background-color: #4a5e3a;">
            <th class="text-left px-4 py-3 text-white font-semibold">Nombre</th>
            <th class="text-left px-4 py-3 text-white font-semibold">Estado</th>
            <th class="text-left px-4 py-3 text-white font-semibold">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!procesos.length">
            <td colspan="3" class="text-center py-8 text-gray-400">Sin procesos registrados</td>
          </tr>
          <tr v-for="p in procesos" :key="p.id" class="border-b hover:bg-amber-50">
            <td class="px-4 py-3 font-medium">{{ p.nombre }}</td>
            <td class="px-4 py-3">
              <span :class="p.activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                class="px-2 py-0.5 rounded-full text-xs font-medium">
                {{ p.activo ? 'Activo' : 'Inactivo' }}
              </span>
            </td>
            <td class="px-4 py-3 flex gap-3">
              <button @click="abrirEditar(p)" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Editar</button>
              <button @click="toggle(p)" class="text-xs"
                :class="p.activo ? 'text-red-500 hover:text-red-700' : 'text-green-600 hover:text-green-800'">
                {{ p.activo ? 'Desactivar' : 'Activar' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="modal.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm overflow-hidden">
        <div class="px-6 py-4" style="background-color:#4a5e3a;">
          <h2 class="text-lg font-bold text-white">{{ modal.editando ? 'Editar' : 'Nuevo' }} proceso de contratación</h2>
        </div>
        <div class="p-6">
        <div>
          <label class="block text-xs text-gray-600 mb-1">Nombre *</label>
          <input v-model="modal.nombre" v-uppercase type="text" maxlength="100"
            placeholder="ej: ÍNFIMA CUANTÍA"
            class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
        </div>
        <p v-if="error" class="text-red-600 text-sm mt-3">{{ error }}</p>
        <div class="flex justify-end gap-3 mt-5">
          <button @click="modal.show = false" class="px-4 py-2 text-sm text-gray-600">Cancelar</button>
          <button @click="guardar" :disabled="guardando"
            class="bg-green-700 text-white px-5 py-2 rounded-lg text-sm hover:bg-green-800 disabled:opacity-50">
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

const procesos  = ref([])
const guardando = ref(false)
const error     = ref('')
const modal     = ref({ show: false, editando: false, id: null, nombre: '' })

async function cargar() {
  const { data } = await api.get('/adquisiciones/procesos-contratacion')
  procesos.value = data
}

onMounted(cargar)

function abrirCrear() {
  modal.value = { show: true, editando: false, id: null, nombre: '' }
  error.value = ''
}

function abrirEditar(p) {
  modal.value = { show: true, editando: true, id: p.id, nombre: p.nombre }
  error.value = ''
}

async function guardar() {
  error.value = ''
  if (!modal.value.nombre.trim()) { error.value = 'El nombre es requerido.'; return }
  guardando.value = true
  try {
    if (modal.value.editando) {
      await api.put(`/adquisiciones/procesos-contratacion/${modal.value.id}`, { nombre: modal.value.nombre })
    } else {
      await api.post('/adquisiciones/procesos-contratacion', { nombre: modal.value.nombre })
    }
    modal.value.show = false
    await cargar()
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al guardar'
  } finally { guardando.value = false }
}

async function toggle(p) {
  await api.patch(`/adquisiciones/procesos-contratacion/${p.id}/toggle`)
  await cargar()
}
</script>
