<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Talleres</h1>
      <button @click="abrirCrear" class="text-white px-4 py-2 rounded-lg text-sm hover:opacity-90"
        style="background-color:#1e3a5f;">
        + Nuevo taller
      </button>
    </div>

    <div class="space-y-2">
      <div v-if="!lista.length" class="bg-white rounded-xl shadow p-8 text-center text-gray-400">
        Sin talleres registrados
      </div>

      <div v-for="t in lista" :key="t.id"
           class="bg-white rounded-xl shadow px-5 py-3 flex justify-between items-center gap-3">
        <div class="min-w-0 flex-1">
          <p class="font-semibold text-gray-800">{{ t.nombre }}
            <span v-if="t.ruc" class="text-gray-400 font-normal text-xs ml-2">RUC: {{ t.ruc }}</span>
          </p>
          <p class="text-xs text-gray-500 mt-0.5 truncate">
            <span v-if="t.direccion">{{ t.direccion }}</span>
            <span v-if="t.telefono"> · {{ t.telefono }}</span>
            <span v-if="t.email"> · {{ t.email }}</span>
          </p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
          <span v-if="t.es_proveedor_bienes"
            class="px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
            Bienes
          </span>
          <span :class="t.estado === 'ACTIVO' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
            class="px-2 py-0.5 rounded-full text-xs font-medium">
            {{ t.estado }}
          </span>
          <button @click="abrirEditar(t)"
            class="text-xs text-gray-600 hover:text-gray-900 font-medium border border-gray-300 px-3 py-1 rounded-lg">
            Editar
          </button>
        </div>
      </div>
    </div>

    <!-- Modal crear/editar -->
    <div v-if="modal.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4" style="background-color:#1e3a5f;">
          <h2 class="text-lg font-bold text-white">
            {{ modal.id ? 'Editar Taller' : 'Nuevo Taller' }}
          </h2>
        </div>
        <div class="p-6">
        <div class="space-y-3">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Nombre *</label>
            <input v-model="form.nombre" class="w-full border rounded-lg px-3 py-2 text-sm" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">RUC</label>
              <input v-model="form.ruc" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Teléfono</label>
              <input v-model="form.telefono" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Dirección</label>
            <input v-model="form.direccion" class="w-full border rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Correo</label>
            <input v-model="form.email" type="email" class="w-full border rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">N° Orden de Compra</label>
            <input v-model="form.orden_compra" class="w-full border rounded-lg px-3 py-2 text-sm" />
          </div>
          <div v-if="modal.id">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Estado</label>
            <select v-model="form.estado" class="w-full border rounded-lg px-3 py-2 text-sm">
              <option value="ACTIVO">ACTIVO</option>
              <option value="INACTIVO">INACTIVO</option>
            </select>
          </div>
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input type="checkbox" v-model="form.es_proveedor_bienes" class="w-4 h-4 rounded" />
            <span class="text-xs text-gray-700 font-medium">También aparece como proveedor en Bienes</span>
          </label>
        </div>
        <p v-if="error" class="text-red-600 text-sm mt-3">{{ error }}</p>
        <div class="flex justify-end gap-2 mt-5">
          <button @click="modal.show = false"
            class="px-4 py-2 text-sm text-gray-600 border rounded-lg">Cancelar</button>
          <button @click="guardar" :disabled="guardando"
            class="px-5 py-2 text-sm text-white rounded-lg hover:opacity-90 disabled:opacity-50"
            style="background-color:#1e3a5f;">
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

const lista    = ref([])
const guardando = ref(false)
const error    = ref('')
const modal    = ref({ show: false, id: null })
const form     = ref({})

async function cargar() {
  const { data } = await api.get('/transporte/talleres')
  lista.value = data
}

function abrirCrear() {
  form.value = { nombre: '', ruc: '', telefono: '', direccion: '', email: '', orden_compra: '', es_proveedor_bienes: false }
  modal.value = { show: true, id: null }
  error.value = ''
}

function abrirEditar(t) {
  form.value = {
    nombre: t.nombre, ruc: t.ruc, telefono: t.telefono,
    direccion: t.direccion, email: t.email, orden_compra: t.orden_compra,
    estado: t.estado, es_proveedor_bienes: t.es_proveedor_bienes,
  }
  modal.value = { show: true, id: t.id }
  error.value = ''
}

async function guardar() {
  error.value = ''
  guardando.value = true
  try {
    if (modal.value.id) {
      await api.put(`/transporte/talleres/${modal.value.id}`, form.value)
    } else {
      await api.post('/transporte/talleres', form.value)
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
