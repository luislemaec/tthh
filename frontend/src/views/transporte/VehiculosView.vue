<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Vehículos</h1>
      <button @click="abrirCrear" class="text-white px-4 py-2 rounded-lg text-sm hover:opacity-90"
        style="background-color:#1e3a5f;">
        + Nuevo vehículo
      </button>
    </div>

    <!-- Lista -->
    <div class="space-y-2">
      <div v-if="!vehiculos.length" class="bg-white rounded-xl shadow p-8 text-center text-gray-400">
        Sin vehículos registrados
      </div>

      <div v-for="v in vehiculos" :key="v.id"
           class="bg-white rounded-xl shadow px-5 py-3 flex justify-between items-center gap-3">
        <div class="min-w-0">
          <p class="font-semibold text-gray-800">
            {{ v.placa }}
            <span class="text-gray-500 font-normal ml-2">{{ v.marca }} {{ v.modelo }} {{ v.anio }}</span>
          </p>
          <p class="text-xs text-gray-500 mt-0.5">
            Km: {{ v.kilometraje_actual?.toLocaleString() }}
            <span v-if="v.color"> · {{ v.color }}</span>
            <span v-if="v.chasis"> · Chasis: {{ v.chasis }}</span>
            <span v-if="v.numero_motor"> · Motor: {{ v.numero_motor }}</span>
          </p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
          <span :class="estadoBadge(v.estado)" class="px-2 py-0.5 rounded-full text-xs font-medium">
            {{ v.estado }}
          </span>
          <button @click="abrirEditar(v)"
            class="text-xs text-gray-600 hover:text-gray-900 font-medium border border-gray-300 px-3 py-1 rounded-lg">
            Editar
          </button>
        </div>
      </div>
    </div>

    <!-- Modal crear/editar -->
    <div v-if="modal.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">
          {{ modal.id ? 'Editar Vehículo' : 'Nuevo Vehículo' }}
        </h2>

        <div class="space-y-3">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Placa *</label>
              <input v-model="form.placa" class="w-full border rounded-lg px-3 py-2 text-sm"
                placeholder="ABC-1234" style="text-transform:uppercase" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Año *</label>
              <input v-model.number="form.anio" type="number" class="w-full border rounded-lg px-3 py-2 text-sm"
                placeholder="2020" min="1990" max="2100" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Marca *</label>
              <input v-model="form.marca" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Toyota" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Modelo *</label>
              <input v-model="form.modelo" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Hilux" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Color</label>
              <input v-model="form.color" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Blanco" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Km Actual *</label>
              <input v-model.number="form.kilometraje_actual" type="number"
                class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="0" min="0" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Chasis</label>
              <input v-model="form.chasis" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">N° Motor</label>
              <input v-model="form.numero_motor" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
          </div>
          <div v-if="modal.id">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Estado *</label>
            <select v-model="form.estado" class="w-full border rounded-lg px-3 py-2 text-sm">
              <option value="ACTIVO">ACTIVO</option>
              <option value="MANTENIMIENTO">MANTENIMIENTO</option>
              <option value="INACTIVO">INACTIVO</option>
            </select>
          </div>
        </div>

        <p v-if="error" class="text-red-600 text-sm mt-3">{{ error }}</p>

        <div class="flex justify-end gap-2 mt-5">
          <button @click="modal.show = false"
            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 border rounded-lg">
            Cancelar
          </button>
          <button @click="guardar" :disabled="guardando"
            class="px-5 py-2 text-sm text-white rounded-lg hover:opacity-90 disabled:opacity-50"
            style="background-color:#1e3a5f;">
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

const vehiculos = ref([])
const guardando = ref(false)
const error = ref('')
const modal = ref({ show: false, id: null })
const form = ref({})

function estadoBadge(e) {
  if (e === 'ACTIVO') return 'bg-green-100 text-green-700'
  if (e === 'MANTENIMIENTO') return 'bg-yellow-100 text-yellow-700'
  return 'bg-red-100 text-red-700'
}

async function cargar() {
  const { data } = await api.get('/transporte/vehiculos')
  vehiculos.value = data
}

function abrirCrear() {
  form.value = { placa: '', marca: '', modelo: '', anio: new Date().getFullYear(),
    chasis: '', color: '', numero_motor: '', kilometraje_actual: 0 }
  modal.value = { show: true, id: null }
  error.value = ''
}

function abrirEditar(v) {
  form.value = { ...v }
  modal.value = { show: true, id: v.id }
  error.value = ''
}

async function guardar() {
  error.value = ''
  guardando.value = true
  try {
    if (modal.value.id) {
      await api.put(`/transporte/vehiculos/${modal.value.id}`, form.value)
    } else {
      await api.post('/transporte/vehiculos', form.value)
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
