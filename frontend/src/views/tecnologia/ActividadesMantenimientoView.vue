<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Actividades del Checklist de Mantenimiento</h1>
      <button @click="abrirCrear" class="text-white px-4 py-2 rounded-lg text-sm hover:opacity-90"
        style="background-color:#4d7c8a;">
        + Nueva actividad
      </button>
    </div>

    <div class="space-y-2">
      <div v-if="!lista.length" class="bg-white rounded-xl shadow p-8 text-center text-gray-400">
        Sin actividades registradas
      </div>

      <div v-for="a in lista" :key="a.id"
           class="bg-white rounded-xl shadow px-5 py-3 flex justify-between items-center gap-3">
        <div class="min-w-0 flex-1">
          <p class="font-semibold text-gray-800">
            <span class="text-gray-400 mr-2">{{ a.orden }}.</span>{{ a.nombre }}
          </p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
          <span :class="a.estado ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
            class="px-2 py-0.5 rounded-full text-xs font-medium">
            {{ a.estado ? 'ACTIVO' : 'INACTIVO' }}
          </span>
          <button @click="abrirEditar(a)"
            class="text-xs text-gray-600 hover:text-gray-900 font-medium border border-gray-300 px-3 py-1 rounded-lg">
            Editar
          </button>
        </div>
      </div>
    </div>

    <!-- Modal crear/editar -->
    <div v-if="modal.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm overflow-hidden">
        <div class="px-6 py-4" style="background-color:#4d7c8a;">
          <h2 class="text-lg font-bold text-white">
            {{ modal.id ? 'Editar Actividad' : 'Nueva Actividad' }}
          </h2>
        </div>
        <div class="p-6">
          <div class="space-y-3">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Nombre *</label>
              <input v-model="form.nombre" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Orden *</label>
              <input v-model.number="form.orden" type="number" min="1" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
            <div v-if="modal.id">
              <label class="block text-xs font-semibold text-gray-600 mb-1">Estado</label>
              <select v-model="form.estado" class="w-full border rounded-lg px-3 py-2 text-sm">
                <option :value="true">ACTIVO</option>
                <option :value="false">INACTIVO</option>
              </select>
            </div>
          </div>
          <p v-if="error" class="text-red-600 text-sm mt-3">{{ error }}</p>
          <div class="flex justify-end gap-2 mt-5">
            <button @click="modal.show = false"
              class="px-4 py-2 text-sm text-gray-600 border rounded-lg">Cancelar</button>
            <button @click="guardar" :disabled="guardando"
              class="px-5 py-2 text-sm text-white rounded-lg hover:opacity-90 disabled:opacity-50"
              style="background-color:#4d7c8a;">
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

const lista     = ref([])
const guardando = ref(false)
const error     = ref('')
const modal     = ref({ show: false, id: null })
const form      = ref({})

async function cargar() {
  const { data } = await api.get('/tecnologia/actividades-mantenimiento')
  lista.value = data
}

function abrirCrear() {
  form.value = { nombre: '', orden: lista.value.length + 1 }
  modal.value = { show: true, id: null }
  error.value = ''
}

function abrirEditar(a) {
  form.value = { ...a }
  modal.value = { show: true, id: a.id }
  error.value = ''
}

async function guardar() {
  error.value = ''
  guardando.value = true
  try {
    if (modal.value.id) {
      await api.put(`/tecnologia/actividades-mantenimiento/${modal.value.id}`, form.value)
    } else {
      await api.post('/tecnologia/actividades-mantenimiento', form.value)
    }
    modal.value.show = false
    await cargar()
  } catch (e) {
    error.value = e.response?.data?.message || Object.values(e.response?.data?.errors || {})[0]?.[0] || 'Error al guardar'
  } finally {
    guardando.value = false
  }
}

onMounted(cargar)
</script>
