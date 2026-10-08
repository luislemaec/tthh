<!-- ============================================================ -->
<!-- src/views/LoginView.vue -->
<!-- ============================================================ -->
<template>
  <div class="sit-login min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8">

      <!-- Logo / Institución -->
      <div class="text-center mb-8">
        <div class="sit-login__logo inline-flex items-center justify-center w-24 h-24 rounded-full mb-4">
          <img src="@/assets/LOGOS-CONSEJOBLANCOH.png" alt="CONSEJO"
               class="w-20 h-20 object-contain" />
        </div>
        <h1 class="text-2xl font-bold text-gray-800">CONSEJO DE COMUNICACIÓN</h1>
        <p class="text-gray-500 text-sm mt-1">Sistema Integral Tecnológico- SIT</p>
      </div>

      <!-- Formulario -->
      <form @submit.prevent="handleLogin" class="space-y-5">
        <div>
          <label for="login-identificacion" class="block text-sm font-medium text-gray-700 mb-1">
            Cédula / Identificación
          </label>
          <input
            v-model="form.identificacion"
            id="login-identificacion"
            autocomplete="username"
            type="text"
            placeholder="Ingrese su cédula"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg outline-none transition text-base"
            :disabled="loading"
            required
          />
        </div>

        <div>
          <label for="login-password" class="block text-sm font-medium text-gray-700 mb-1">Contraseña</label>
          <div class="relative">
            <input
              v-model="form.password"
              id="login-password"
              autocomplete="current-password"
              :type="showPass ? 'text' : 'password'"
              placeholder="Ingrese su contraseña"
              class="w-full px-4 py-3 border border-gray-300 rounded-lg outline-none transition pr-12 text-base"
              :disabled="loading"
              required
            />
            <button type="button" @click="showPass = !showPass"
              :aria-label="showPass ? 'Ocultar contraseña' : 'Mostrar contraseña'"
              :aria-pressed="showPass"
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
        <div v-if="error" role="alert" class="sit-message sit-message--danger">
          {{ error }}
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="sit-login__submit w-full text-base font-semibold py-3 rounded-lg transition duration-200
                 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2">
          <svg v-if="loading" class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor"
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
          </svg>
          {{ loading ? 'Ingresando...' : 'Ingresar' }}
        </button>
      </form>

      <p class="text-center text-xs text-gray-400 mt-6">
        © {{ new Date().getFullYear() }} CONSEJO DE COMUNICACIÓN
      </p>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router  = useRouter()
const auth    = useAuthStore()
const loading = ref(false)
const error   = ref('')
const showPass = ref(false)
const form    = ref({ identificacion: '', password: '' })
onMounted(() => { document.title = 'Ingresar · SIT' })

async function handleLogin() {
  loading.value = true
  error.value   = ''
  try {
    await auth.login(form.value.identificacion, form.value.password)
    sessionStorage.setItem('show_pendientes', '1')
    router.replace('/dashboard')
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al iniciar sesión'
  } finally {
    loading.value = false
  }
}
</script>
