<!-- ============================================================ -->
<!-- src/views/LoginView.vue -->
<!-- ============================================================ -->
<template>
  <div class="min-h-screen flex items-center justify-center p-4"
       style="background: linear-gradient(135deg, #00372e 0%, #0b5447 50%, #579186 100%);">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8">

      <!-- Logo / Institución -->
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-24 h-24 rounded-full mb-4"
             style="background-color: #0b5447;">
          <img src="@/assets/LOGOS-CONSEJOBLANCOH.png" alt="CORDICOM"
               class="w-20 h-20 object-contain" />
        </div>
        <h1 class="text-2xl font-bold text-gray-800">CONSEJO DE COMUNICACIÓN</h1>
        <p class="text-gray-500 text-sm mt-1">Sistema Integral Tecnológico- SIT</p>
      </div>

      <!-- Formulario -->
      <form @submit.prevent="handleLogin" class="space-y-5">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">
            Cédula / Identificación
          </label>
          <input
            v-model="form.identificacion"
            type="text"
            placeholder="Ingrese su cédula"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2
                   focus:ring-[#579186] focus:border-transparent outline-none transition"
            :disabled="loading"
            required
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
          <div class="relative">
            <input
              v-model="form.password"
              :type="showPass ? 'text' : 'password'"
              placeholder="Ingrese su contraseña"
              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2
                     focus:ring-[#579186] focus:border-transparent outline-none transition pr-12"
              :disabled="loading"
              required
            />
            <button type="button" @click="showPass = !showPass"
              class="absolute right-3 top-3.5 text-gray-400 hover:text-gray-600">
              <svg v-if="!showPass" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7
                     -1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
              <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7
                     a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243
                     M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29
                     M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7
                     a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
              </svg>
            </button>
          </div>
        </div>

        <!-- Error -->
        <div v-if="error"
          class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
          {{ error }}
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="w-full text-white font-semibold py-3 rounded-lg transition duration-200
                 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2
                 bg-[#0b5447] hover:bg-[#00372e]">
          <svg v-if="loading" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor"
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
          </svg>
          {{ loading ? 'Ingresando...' : 'Ingresar' }}
        </button>
      </form>

      <p class="text-center text-xs text-gray-400 mt-6">
        © {{ new Date().getFullYear() }} CONSEJO — Talento Humano
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router  = useRouter()
const auth    = useAuthStore()
const loading = ref(false)
const error   = ref('')
const showPass = ref(false)
const form    = ref({ identificacion: '', password: '' })

async function handleLogin() {
  loading.value = true
  error.value   = ''
  try {
    await auth.login(form.value.identificacion, form.value.password)
    router.push('/launcher')
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al iniciar sesión'
  } finally {
    loading.value = false
  }
}
</script>
