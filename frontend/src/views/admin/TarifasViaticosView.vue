<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">Tarifas de Viáticos</h1>
        <p class="text-sm text-gray-500 mt-0.5">Valores diarios para comisiones de servicio</p>
      </div>
      <button @click="abrirNueva"
        class="flex items-center gap-2 px-4 py-2 bg-[#5c4a6e] text-white text-sm font-semibold rounded-lg shadow transition hover:opacity-90">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Nueva Tarifa
      </button>
    </div>

    <div v-if="cargando" class="flex items-center justify-center py-16">
      <div class="w-8 h-8 border-2 border-[#5c4a6e] border-t-transparent rounded-full animate-spin"></div>
    </div>

    <div v-else class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-xs text-gray-500 uppercase border-b border-gray-100" style="background-color:#f9f7fb;">
            <th class="px-4 py-3 text-left font-semibold">Descripción</th>
            <th class="px-4 py-3 text-left font-semibold">Tipo</th>
            <th class="px-4 py-3 text-right font-semibold">Valor / Día</th>
            <th class="px-4 py-3 text-center font-semibold">Estado</th>
            <th class="px-4 py-3 text-left font-semibold">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="tarifas.length === 0">
            <td colspan="5" class="px-4 py-12 text-center text-gray-400 text-sm">
              No hay tarifas registradas. Cree la primera tarifa.
            </td>
          </tr>
          <tr v-for="tarifa in tarifas" :key="tarifa.id"
            class="border-b border-gray-50 hover:bg-purple-50/20 transition">
            <td class="px-4 py-3 font-medium text-gray-800">{{ tarifa.descripcion }}</td>
            <td class="px-4 py-3">
              <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                :class="{
                  'bg-blue-100 text-blue-700': tarifa.tipo === 'EXTERIOR',
                  'bg-green-100 text-green-700': tarifa.tipo === 'INTERIOR',
                  'bg-purple-100 text-purple-700': tarifa.tipo === 'AMBOS',
                }">
                {{ tarifa.tipo }}
              </span>
            </td>
            <td class="px-4 py-3 text-right font-semibold text-gray-800">
              ${{ parseFloat(tarifa.valor_dia).toFixed(2) }}
            </td>
            <td class="px-4 py-3 text-center">
              <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full', tarifa.activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
                {{ tarifa.activo ? 'Activo' : 'Inactivo' }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <button @click="editar(tarifa)"
                  class="p-1.5 text-gray-400 hover:text-[#5c4a6e] hover:bg-purple-50 rounded transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                  </svg>
                </button>
                <button @click="eliminar(tarifa)"
                  class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Modal crear/editar -->
  <div v-if="modalAbierto" class="fixed inset-0 z-50 flex items-center justify-center px-4">
    <div class="fixed inset-0 bg-black/40" @click="cerrar"/>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md p-6 z-10">
      <h3 class="text-lg font-bold text-gray-800 mb-5">
        {{ editandoId ? 'Editar Tarifa' : 'Nueva Tarifa' }}
      </h3>
      <div class="space-y-4">
        <div>
          <label class="text-xs font-semibold text-gray-600 mb-1 block">Descripción *</label>
          <input v-model="form.descripcion" type="text" placeholder="Ej: Viáticos Interior Nacional"
            class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]"/>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-xs font-semibold text-gray-600 mb-1 block">Tipo *</label>
            <select v-model="form.tipo" class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]">
              <option value="INTERIOR">Interior</option>
              <option value="EXTERIOR">Exterior</option>
              <option value="AMBOS">Ambos</option>
            </select>
          </div>
          <div>
            <label class="text-xs font-semibold text-gray-600 mb-1 block">Valor por Día ($) *</label>
            <input v-model.number="form.valor_dia" type="number" step="0.01" min="0"
              class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]"/>
          </div>
        </div>
        <div v-if="editandoId">
          <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
            <input type="checkbox" v-model="form.activo" class="rounded accent-[#5c4a6e]"/>
            Tarifa activa
          </label>
        </div>
      </div>
      <p v-if="error" class="text-red-600 text-xs mt-3">{{ error }}</p>
      <div class="flex justify-end gap-3 mt-6">
        <button @click="cerrar" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">Cancelar</button>
        <button @click="guardar" :disabled="guardando"
          class="px-5 py-2 text-sm font-semibold text-white rounded-lg hover:opacity-90 transition disabled:opacity-50"
          style="background-color:#5c4a6e;">
          {{ guardando ? 'Guardando...' : 'Guardar' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const cargando    = ref(true)
const tarifas     = ref([])
const modalAbierto = ref(false)
const editandoId  = ref(null)
const guardando   = ref(false)
const error       = ref('')

const form = ref({ descripcion: '', tipo: 'INTERIOR', valor_dia: 0, activo: true })

async function cargar() {
  cargando.value = true
  try {
    const { data } = await api.get('/comisiones/admin/tarifas-viaticos')
    tarifas.value = data
  } catch (e) {
    console.error(e)
  } finally {
    cargando.value = false
  }
}

onMounted(cargar)

function abrirNueva() {
  form.value = { descripcion: '', tipo: 'INTERIOR', valor_dia: 0, activo: true }
  editandoId.value = null
  error.value = ''
  modalAbierto.value = true
}

function editar(tarifa) {
  form.value = { descripcion: tarifa.descripcion, tipo: tarifa.tipo, valor_dia: parseFloat(tarifa.valor_dia), activo: tarifa.activo }
  editandoId.value = tarifa.id
  error.value = ''
  modalAbierto.value = true
}

function cerrar() {
  modalAbierto.value = false
}

async function guardar() {
  error.value = ''
  if (!form.value.descripcion.trim() || !form.value.valor_dia) {
    error.value = 'Complete la descripción y el valor por día.'
    return
  }
  guardando.value = true
  try {
    if (editandoId.value) {
      await api.put(`/comisiones/admin/tarifas-viaticos/${editandoId.value}`, form.value)
    } else {
      await api.post('/comisiones/admin/tarifas-viaticos', form.value)
    }
    modalAbierto.value = false
    await cargar()
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al guardar.'
  } finally {
    guardando.value = false
  }
}

async function eliminar(tarifa) {
  if (!confirm(`¿Eliminar la tarifa "${tarifa.descripcion}"?`)) return
  try {
    await api.delete(`/comisiones/admin/tarifas-viaticos/${tarifa.id}`)
    await cargar()
  } catch (e) {
    alert(e.response?.data?.message || 'Error al eliminar.')
  }
}
</script>
