<template>
  <div class="space-y-6 max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-gray-800">Mi Perfil</h1>

    <!-- Datos del empleado -->
    <div class="bg-white rounded-xl shadow p-6 space-y-3">
      <div class="flex items-center gap-4">
        <div class="bg-[#0b5447] rounded-full w-14 h-14 flex items-center justify-center text-xl font-bold text-white">
          {{ iniciales }}
        </div>
        <div>
          <p class="font-semibold text-gray-800">{{ auth.empleado?.apellido }}, {{ auth.empleado?.nombre }}</p>
          <p class="text-sm text-gray-500">{{ auth.empleado?.identificacion }}</p>
          <p class="text-sm text-gray-500">{{ auth.empleado?.cargo || '—' }}</p>
        </div>
      </div>
    </div>

    <!-- Cambiar contraseña -->
    <div class="bg-white rounded-xl shadow p-6 space-y-4">
      <h2 class="text-md font-semibold text-gray-700 border-b pb-2">Cambiar Contraseña</h2>

      <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">Contraseña actual *</label>
        <input v-model="form.password_actual" type="password"
          class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">Nueva contraseña *</label>
        <input v-model="form.password_nuevo" type="password"
          class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
        <p class="text-xs text-gray-400 mt-1">Mínimo 6 caracteres</p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">Confirmar nueva contraseña *</label>
        <input v-model="form.password_confirmar" type="password"
          class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
      </div>

      <div v-if="error" class="text-red-600 text-sm bg-red-50 rounded p-2">{{ error }}</div>
      <div v-if="exito" class="text-green-600 text-sm bg-green-50 rounded p-2">{{ exito }}</div>

      <button @click="cambiarPassword" :disabled="guardando"
        class="w-full bg-[#0b5447] text-white py-2 rounded-lg text-sm font-medium hover:bg-[#00372e] disabled:opacity-50">
        {{ guardando ? 'Guardando...' : 'Cambiar Contraseña' }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const auth     = useAuthStore()
const guardando = ref(false)
const error     = ref('')
const exito     = ref('')
const form      = ref({ password_actual: '', password_nuevo: '', password_confirmar: '' })

const iniciales = computed(() => {
  const n = auth.empleado?.nombre?.[0] || ''
  const a = auth.empleado?.apellido?.[0] || ''
  return (a + n).toUpperCase()
})

const cambiarPassword = async () => {
  error.value = ''
  exito.value = ''

  if (!form.value.password_actual || !form.value.password_nuevo || !form.value.password_confirmar) {
    error.value = 'Todos los campos son requeridos.'
    return
  }
  if (form.value.password_nuevo !== form.value.password_confirmar) {
    error.value = 'La nueva contraseña y la confirmación no coinciden.'
    return
  }
  if (form.value.password_nuevo.length < 6) {
    error.value = 'La nueva contraseña debe tener al menos 6 caracteres.'
    return
  }

  guardando.value = true
  try {
    const { data } = await api.post('/cambiar-password', form.value)
    exito.value = data.message
    form.value = { password_actual: '', password_nuevo: '', password_confirmar: '' }
    setTimeout(() => { exito.value = '' }, 4000)
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al cambiar contraseña.'
  } finally {
    guardando.value = false
  }
}
</script>
