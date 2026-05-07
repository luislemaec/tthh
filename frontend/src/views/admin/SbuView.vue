<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Salario Básico Unificado (SBU)</h1>
    </div>

    <div class="bg-white rounded-xl shadow p-5 max-w-md">
      <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide mb-4">Registrar / Actualizar SBU</h2>
      <div class="flex gap-3 items-end mb-2">
        <div>
          <label class="block text-xs text-gray-600 mb-1">Año</label>
          <input v-model.number="form.anio" type="number" min="2020" max="2100"
            class="border rounded-lg px-3 py-2 text-sm w-24 focus:ring-2 focus:ring-blue-300 outline-none" />
        </div>
        <div>
          <label class="block text-xs text-gray-600 mb-1">Valor $</label>
          <input v-model.number="form.valor" type="number" step="0.01" min="1"
            class="border rounded-lg px-3 py-2 text-sm w-32 focus:ring-2 focus:ring-blue-300 outline-none" />
        </div>
        <button @click="guardar" :disabled="guardando"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 disabled:opacity-50">
          {{ guardando ? 'Guardando...' : 'Guardar' }}
        </button>
      </div>
      <p class="text-xs text-gray-500 mb-3">Si el año ya existe, actualiza el valor.</p>
      <p v-if="error" class="text-red-600 text-xs mb-2">{{ error }}</p>
      <p v-if="ok" class="text-green-600 text-xs mb-2">{{ ok }}</p>

      <table v-if="lista.length" class="w-full text-sm border-collapse mt-4">
        <thead>
          <tr class="bg-gray-50">
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 border">Año</th>
            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 border">SBU $</th>
            <th class="px-4 py-2 text-center text-xs font-semibold text-gray-600 border">Acción</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in lista" :key="s.id" class="border-b hover:bg-gray-50">
            <td class="px-4 py-2 border font-medium">{{ s.anio }}</td>
            <td class="px-4 py-2 border text-right font-mono">
              <span v-if="editando?.id !== s.id">${{ parseFloat(s.valor).toFixed(2) }}</span>
              <input v-else v-model.number="editando.valor" type="number" step="0.01"
                class="border rounded px-2 py-1 text-sm w-28 text-right focus:ring-2 focus:ring-blue-300 outline-none" />
            </td>
            <td class="px-4 py-2 border text-center">
              <template v-if="editando?.id !== s.id">
                <button @click="iniciarEdicion(s)" class="text-blue-600 hover:underline text-xs">Editar</button>
              </template>
              <template v-else>
                <button @click="guardarEdicion" class="text-green-600 hover:underline text-xs mr-2">Guardar</button>
                <button @click="editando = null" class="text-gray-400 hover:underline text-xs">Cancelar</button>
              </template>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const lista    = ref([])
const form     = ref({ anio: new Date().getFullYear(), valor: '' })
const guardando = ref(false)
const error    = ref('')
const ok       = ref('')
const editando = ref(null)

onMounted(cargar)

async function cargar() {
  try {
    const { data } = await api.get('/nomina/sbu')
    lista.value = data
  } catch {}
}

async function guardar() {
  error.value = ''
  ok.value = ''
  if (!form.value.anio || !form.value.valor) { error.value = 'Ingrese año y valor.'; return }
  guardando.value = true
  try {
    await api.post('/nomina/sbu', form.value)
    ok.value = `SBU ${form.value.anio} guardado correctamente.`
    form.value.valor = ''
    await cargar()
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Error al guardar.'
  } finally {
    guardando.value = false
  }
}

function iniciarEdicion(s) {
  editando.value = { id: s.id, anio: s.anio, valor: parseFloat(s.valor) }
}

async function guardarEdicion() {
  error.value = ''
  ok.value = ''
  try {
    await api.post('/nomina/sbu', { anio: editando.value.anio, valor: editando.value.valor })
    ok.value = `SBU ${editando.value.anio} actualizado.`
    editando.value = null
    await cargar()
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Error al actualizar.'
  }
}
</script>
