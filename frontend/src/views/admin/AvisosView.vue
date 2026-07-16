<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Avisos del Launcher</h1>
      <button @click="abrirCrear" class="text-white px-4 py-2 rounded-lg text-sm hover:opacity-90"
        style="background-color:#0b5447;">
        + Nuevo aviso
      </button>
    </div>

    <!-- Config dirección -->
    <div class="bg-white rounded-xl shadow px-5 py-4 mb-4 flex items-center gap-4">
      <span class="text-sm font-semibold text-gray-600">Dirección del ticker:</span>
      <label class="flex items-center gap-2 cursor-pointer text-sm">
        <input type="radio" v-model="direccion" value="horizontal" @change="guardarDireccion" />
        Horizontal (izquierda)
      </label>
      <label class="flex items-center gap-2 cursor-pointer text-sm">
        <input type="radio" v-model="direccion" value="vertical" @change="guardarDireccion" />
        Vertical (hacia arriba)
      </label>
      <span v-if="msgDir" class="text-xs text-green-700 font-medium">{{ msgDir }}</span>
    </div>

    <!-- Lista -->
    <div class="space-y-2">
      <div v-if="!avisos.length" class="bg-white rounded-xl shadow p-8 text-center text-gray-400">
        Sin avisos registrados
      </div>

      <div v-for="a in avisos" :key="a.id"
           class="bg-white rounded-xl shadow px-5 py-3 flex justify-between items-center gap-3">
        <div class="flex items-center gap-3 min-w-0 flex-1">
          <span class="text-xs font-bold text-gray-400 w-6 text-center flex-shrink-0">{{ a.orden }}</span>
          <span class="text-sm text-gray-800 truncate">{{ a.texto }}</span>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
          <span :class="a.activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
            class="px-2 py-0.5 rounded-full text-xs font-medium">
            {{ a.activo ? 'Activo' : 'Inactivo' }}
          </span>
          <button @click="abrirEditar(a)"
            class="text-xs text-gray-600 hover:text-gray-900 font-medium border border-gray-300 px-3 py-1 rounded-lg">
            Editar
          </button>
          <button @click="eliminar(a)"
            class="text-xs text-red-500 hover:text-red-700 font-medium border border-red-200 px-3 py-1 rounded-lg">
            Eliminar
          </button>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="modal.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">
        <div class="px-6 py-4" style="background-color:#0b5447;">
          <h2 class="text-lg font-bold text-white">{{ modal.id ? 'Editar aviso' : 'Nuevo aviso' }}</h2>
        </div>
        <div class="p-6 space-y-3">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Texto del aviso *</label>
            <textarea v-model="form.texto" rows="3"
              class="w-full border rounded-lg px-3 py-2 text-sm resize-none"
              placeholder="Ej: Recuerda que la planificación de vacaciones cierra el 30 de junio." />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Orden</label>
              <input v-model.number="form.orden" type="number" min="0"
                class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="0" />
            </div>
            <div class="flex items-end pb-2">
              <label class="flex items-center gap-2 cursor-pointer text-sm select-none">
                <input type="checkbox" v-model="form.activo" class="w-4 h-4 rounded" />
                <span class="font-medium text-gray-700">Activo (visible)</span>
              </label>
            </div>
          </div>
          <p v-if="error" class="text-red-600 text-sm">{{ error }}</p>
          <div class="flex justify-end gap-2 mt-2">
            <button @click="modal.show = false"
              class="px-4 py-2 text-sm text-gray-600 border rounded-lg">Cancelar</button>
            <button @click="guardar" :disabled="guardando"
              class="px-5 py-2 text-sm text-white rounded-lg hover:opacity-90 disabled:opacity-50"
              style="background-color:#0b5447;">
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

const avisos   = ref([])
const direccion = ref('horizontal')
const msgDir   = ref('')
const guardando = ref(false)
const error    = ref('')
const modal    = ref({ show: false, id: null })
const form     = ref({})

async function cargar() {
  const { data } = await api.get('/admin/avisos')
  avisos.value   = data.avisos
  direccion.value = data.direccion
}

function abrirCrear() {
  form.value  = { texto: '', orden: 0, activo: true }
  modal.value = { show: true, id: null }
  error.value = ''
}

function abrirEditar(a) {
  form.value  = { texto: a.texto, orden: a.orden, activo: a.activo }
  modal.value = { show: true, id: a.id }
  error.value = ''
}

async function guardar() {
  error.value    = ''
  guardando.value = true
  try {
    if (modal.value.id) {
      await api.put(`/admin/avisos/${modal.value.id}`, form.value)
    } else {
      await api.post('/admin/avisos', form.value)
    }
    modal.value.show = false
    await cargar()
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al guardar'
  } finally {
    guardando.value = false
  }
}

async function eliminar(a) {
  if (!confirm(`¿Eliminar el aviso "${a.texto.slice(0, 50)}..."?`)) return
  await api.delete(`/admin/avisos/${a.id}`)
  await cargar()
}

async function guardarDireccion() {
  await api.put('/admin/avisos-direccion', { direccion: direccion.value })
  msgDir.value = 'Guardado'
  setTimeout(() => { msgDir.value = '' }, 2000)
}

onMounted(cargar)
</script>
