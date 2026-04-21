<template>
  <div class="min-h-screen flex flex-col items-center justify-center p-8"
       style="background: linear-gradient(135deg, #0b5447 0%, #00372e 100%);">

    <!-- Logo + nombre institución -->
    <div class="text-center mb-10">
      <img src="@/assets/LOGOS-CONSEJOBLANCOH.png" alt="Logo" class="h-16 mx-auto mb-4 object-contain" />
      <h1 class="text-2xl font-bold text-white tracking-wide">CONSEJO DE COMUNICACIÓN</h1>
      <p class="text-green-200 text-sm mt-1">Sistema Integrado de Gestión Institucional</p>
    </div>

    <!-- Tarjetas de aplicativos -->
    <div class="flex flex-wrap justify-center gap-6 max-w-3xl w-full">

      <!-- Talento Humano -->
      <button @click="irA('/dashboard')"
        class="bg-white rounded-2xl shadow-xl p-8 w-64 flex flex-col items-center gap-4 hover:scale-105 transition-transform cursor-pointer group">
        <div class="w-16 h-16 rounded-full flex items-center justify-center"
             style="background-color: #0b5447;">
          <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857
                 M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857
                 m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>
        </div>
        <div class="text-center">
          <p class="font-bold text-gray-800 text-lg">Talento Humano</p>
          <p class="text-gray-500 text-xs mt-1">Empleados, vacaciones, permisos, asistencia</p>
        </div>
      </button>

      <!-- Adquisiciones -->
      <button v-if="tieneAccesoAdquisiciones" @click="irA('/adquisiciones')"
        class="bg-white rounded-2xl shadow-xl p-8 w-64 flex flex-col items-center gap-4 hover:scale-105 transition-transform cursor-pointer group">
        <div class="w-16 h-16 rounded-full flex items-center justify-center bg-amber-600">
          <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
          </svg>
        </div>
        <div class="text-center">
          <p class="font-bold text-gray-800 text-lg">Adquisiciones</p>
          <p class="text-gray-500 text-xs mt-1">Inventario, compras, solicitudes de materiales</p>
          <span v-if="alertasStock > 0"
            class="mt-2 inline-block bg-red-100 text-red-700 text-xs font-semibold px-2 py-0.5 rounded-full">
            {{ alertasStock }} alerta{{ alertasStock > 1 ? 's' : '' }} de stock
          </span>
        </div>
      </button>

      <!-- Solicitudes de materiales (todos los empleados) -->
      <button v-if="!tieneAccesoAdquisiciones" @click="irA('/adquisiciones/solicitudes')"
        class="bg-white rounded-2xl shadow-xl p-8 w-64 flex flex-col items-center gap-4 hover:scale-105 transition-transform cursor-pointer group">
        <div class="w-16 h-16 rounded-full flex items-center justify-center bg-amber-600">
          <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2
                 M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
          </svg>
        </div>
        <div class="text-center">
          <p class="font-bold text-gray-800 text-lg">Solicitud de Materiales</p>
          <p class="text-gray-500 text-xs mt-1">Solicita materiales y suministros</p>
        </div>
      </button>

    </div>

    <!-- Info usuario -->
    <div class="mt-10 text-center text-green-200 text-sm">
      <p>{{ store.empleado?.apellido_emp }} {{ store.empleado?.nombre_emp }}</p>
      <button @click="logout" class="mt-2 text-green-300 hover:text-white underline text-xs">
        Cerrar sesión
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const router = useRouter()
const store  = useAuthStore()
const alertasStock = ref(0)

const tieneAccesoAdquisiciones = store.tieneAdquisiciones

onMounted(async () => {
  try {
    const { data } = await api.get('/adquisiciones/articulos/alertas')
    alertasStock.value = data.length
  } catch {}
})

function irA(ruta) {
  router.push(ruta)
}

async function logout() {
  await store.logout()
  router.push('/login')
}
</script>
