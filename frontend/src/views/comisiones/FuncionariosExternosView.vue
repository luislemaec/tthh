<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">Funcionarios Externos</h1>
        <p class="text-sm text-gray-500 mt-0.5">Personal temporal con derecho a viáticos (ej: seguridad presidencial)</p>
      </div>
      <button @click="abrirNuevo"
        class="flex items-center gap-2 px-4 py-2 text-white text-sm font-semibold rounded-lg shadow transition hover:opacity-90"
        style="background-color:#5c4a6e;">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Nuevo Funcionario
      </button>
    </div>

    <div v-if="cargando" class="flex items-center justify-center py-16">
      <div class="w-8 h-8 border-2 border-t-transparent rounded-full animate-spin" style="border-color:#5c4a6e; border-top-color:transparent;"></div>
    </div>

    <div v-else class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-xs text-gray-500 uppercase border-b border-gray-100" style="background-color:#f9f7fb;">
            <th class="px-4 py-3 text-left font-semibold">Cédula</th>
            <th class="px-4 py-3 text-left font-semibold">Nombres</th>
            <th class="px-4 py-3 text-left font-semibold">Cargo</th>
            <th class="px-4 py-3 text-left font-semibold">Banco / Cuenta</th>
            <th class="px-4 py-3 text-center font-semibold">Estado</th>
            <th class="px-4 py-3 text-left font-semibold">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="lista.length === 0">
            <td colspan="6" class="px-4 py-12 text-center text-gray-400 text-sm">No hay funcionarios externos registrados.</td>
          </tr>
          <tr v-for="f in lista" :key="f.id" class="border-b border-gray-50 hover:bg-purple-50/20 transition">
            <td class="px-4 py-3 font-mono text-sm text-gray-700">{{ f.cedula }}</td>
            <td class="px-4 py-3 font-medium text-gray-800">{{ f.nombres }}</td>
            <td class="px-4 py-3 text-gray-600">{{ f.cargo }}</td>
            <td class="px-4 py-3 text-gray-600 text-xs">
              <span v-if="f.banco">{{ f.banco }} · {{ f.tipo_cuenta }} · {{ f.numero_cuenta }}</span>
              <span v-else class="text-gray-300 italic">Sin cuenta registrada</span>
            </td>
            <td class="px-4 py-3 text-center">
              <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full', f.activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
                {{ f.activo ? 'Activo' : 'Inactivo' }}
              </span>
            </td>
            <td class="px-4 py-3 flex gap-2">
              <button @click="editar(f)" class="p-1.5 text-gray-400 hover:text-[#5c4a6e] hover:bg-purple-50 rounded transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
              </button>
              <button @click="desactivar(f)" class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 715.636 5.636m12.728 12.728L5.636 5.636"/>
                </svg>
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="modal.show" class="fixed inset-0 z-50 flex items-center justify-center px-4">
      <div class="fixed inset-0 bg-black/40" @click="modal.show = false"/>
      <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-lg overflow-hidden z-10">
        <div class="px-6 py-4 text-white font-bold text-base" style="background-color:#5c4a6e">{{ modal.id ? 'Editar Funcionario' : 'Nuevo Funcionario Externo' }}</div>
        <div class="p-6">
        <div class="space-y-4">
          <!-- Datos personales -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs font-semibold text-gray-600 mb-1 block">Cédula *</label>
              <input v-model="modal.form.cedula" type="text" :disabled="!!modal.id" maxlength="20"
                class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e] disabled:bg-gray-50"/>
            </div>
            <div>
              <label class="text-xs font-semibold text-gray-600 mb-1 block">Cargo *</label>
              <input v-model="modal.form.cargo" type="text" maxlength="200"
                class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]"/>
            </div>
          </div>
          <div>
            <label class="text-xs font-semibold text-gray-600 mb-1 block">Nombres completos *</label>
            <input v-model="modal.form.nombres" type="text" maxlength="200"
              class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]"/>
          </div>

          <!-- Datos bancarios -->
          <div class="border-t border-gray-100 pt-3">
            <p class="text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">Datos Bancarios</p>
            <div class="space-y-2">
              <input v-model="modal.form.banco" type="text" placeholder="Nombre del banco"
                class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]"/>
              <div class="grid grid-cols-2 gap-2">
                <select v-model="modal.form.tipo_cuenta" class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none">
                  <option value="">Tipo de cuenta...</option>
                  <option value="AHORROS">Ahorros</option>
                  <option value="CORRIENTE">Corriente</option>
                </select>
                <input v-model="modal.form.numero_cuenta" type="text" placeholder="Número de cuenta"
                  class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]"/>
              </div>
            </div>
          </div>

          <div v-if="modal.id">
            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
              <input type="checkbox" v-model="modal.form.activo" class="rounded"/>
              Funcionario activo
            </label>
          </div>
        </div>
        <p v-if="modal.error" class="text-red-600 text-xs mt-3">{{ modal.error }}</p>
        <div class="flex justify-end gap-3 mt-6">
          <button @click="modal.show = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
          <button @click="guardar" :disabled="modal.guardando"
            class="px-5 py-2 text-sm font-semibold text-white rounded-lg hover:opacity-90 disabled:opacity-50"
            style="background-color:#5c4a6e;">
            {{ modal.guardando ? 'Guardando...' : 'Guardar' }}
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

const formVacio = () => ({ cedula: '', nombres: '', cargo: '', banco: '', tipo_cuenta: '', numero_cuenta: '', activo: true })

const cargando = ref(true)
const lista    = ref([])
const modal    = ref({ show: false, id: null, form: formVacio(), error: '', guardando: false })

async function cargar() {
  cargando.value = true
  try {
    const { data } = await api.get('/comisiones/admin/funcionarios-externos')
    lista.value = data
  } finally {
    cargando.value = false
  }
}

function abrirNuevo() {
  modal.value = { show: true, id: null, form: formVacio(), error: '', guardando: false }
}

function editar(f) {
  modal.value = {
    show: true, id: f.id,
    form: { cedula: f.cedula, nombres: f.nombres, cargo: f.cargo, banco: f.banco || '', tipo_cuenta: f.tipo_cuenta || '', numero_cuenta: f.numero_cuenta || '', activo: f.activo },
    error: '', guardando: false
  }
}

async function guardar() {
  modal.value.error = ''
  const { cedula, nombres, cargo } = modal.value.form
  if (!cedula.trim() || !nombres.trim() || !cargo.trim()) {
    modal.value.error = 'Complete los campos obligatorios: cédula, nombres y cargo.'
    return
  }
  modal.value.guardando = true
  try {
    if (modal.value.id) {
      await api.put(`/comisiones/admin/funcionarios-externos/${modal.value.id}`, modal.value.form)
    } else {
      await api.post('/comisiones/admin/funcionarios-externos', modal.value.form)
    }
    modal.value.show = false
    await cargar()
  } catch (e) {
    modal.value.error = e.response?.data?.message || 'Error al guardar.'
  } finally {
    modal.value.guardando = false
  }
}

async function desactivar(f) {
  if (!confirm(`¿Desactivar a ${f.nombres}?`)) return
  try {
    await api.delete(`/comisiones/admin/funcionarios-externos/${f.id}`)
    await cargar()
  } catch (e) {
    alert(e.response?.data?.message || 'Error.')
  }
}

onMounted(cargar)
</script>
