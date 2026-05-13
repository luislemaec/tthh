<template>
  <div class="min-h-screen flex flex-col items-center justify-center p-8"
       style="background: linear-gradient(135deg, #0b5447 0%, #00372e 100%);">

    <!-- Logo + nombre institución -->
    <div class="text-center mb-10">
      <img src="@/assets/LOGOS-CONSEJOBLANCOH.png" alt="Logo" class="h-16 mx-auto mb-4 object-contain" />
      <h1 class="text-2xl font-bold text-white tracking-wide">CONSEJO DE COMUNICACIÓN</h1>
      <p class="text-green-200 text-sm mt-1">Sistema Integral Tecnológico - SIT</p>
    </div>

    <!-- Tarjetas de aplicativos -->
    <div class="flex flex-wrap justify-center gap-4 max-w-4xl w-full">

      <!-- Talento Humano -->
      <button @click="irA('/dashboard')"
        class="bg-white rounded-xl shadow-lg p-5 w-44 flex flex-col items-center gap-3 hover:scale-105 transition-transform cursor-pointer">
        <div class="w-12 h-12 rounded-full flex items-center justify-center"
             style="background-color: #0b5447;">
          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857
                 M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857
                 m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>
        </div>
        <div class="text-center">
          <p class="font-bold text-gray-800 text-sm">Talento Humano</p>
          <p class="text-gray-500 text-xs mt-0.5">Empleados, asistencia, vacaciones</p>
        </div>
      </button>

      <!-- Adquisiciones -->
      <button v-if="tieneAccesoAdquisiciones" @click="irA('/adquisiciones')"
        class="bg-white rounded-xl shadow-lg p-5 w-44 flex flex-col items-center gap-3 hover:scale-105 transition-transform cursor-pointer">
        <div class="w-12 h-12 rounded-full flex items-center justify-center bg-amber-600">
          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
          </svg>
        </div>
        <div class="text-center">
          <p class="font-bold text-gray-800 text-sm">Administrativo</p>
          <p class="text-gray-500 text-xs mt-0.5">Inventario, ingresos, egresos</p>
          <span v-if="alertasStock > 0"
            class="mt-1 inline-block bg-red-100 text-red-700 text-xs font-semibold px-2 py-0.5 rounded-full">
            {{ alertasStock }} alerta{{ alertasStock > 1 ? 's' : '' }}
          </span>
        </div>
      </button>

      <!-- Transportes -->
      <button v-if="tieneAccesoTransportes" @click="irA(rutaTransportes)"
        class="bg-white rounded-xl shadow-lg p-5 w-44 flex flex-col items-center gap-3 hover:scale-105 transition-transform cursor-pointer">
        <div class="w-12 h-12 rounded-full flex items-center justify-center"
             style="background-color: #1e3a5f;">
          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
          </svg>
        </div>
        <div class="text-center">
          <p class="font-bold text-gray-800 text-sm">Transporte</p>
          <p class="text-gray-500 text-xs mt-0.5">Vehículos, mantenimiento, movilización</p>
        </div>
      </button>

      <!-- Solicitudes de materiales (todos los empleados) -->
      <button v-if="!tieneAccesoAdquisiciones" @click="irA('/adquisiciones/solicitudes')"
        class="bg-white rounded-xl shadow-lg p-5 w-44 flex flex-col items-center gap-3 hover:scale-105 transition-transform cursor-pointer">
        <div class="w-12 h-12 rounded-full flex items-center justify-center bg-amber-600">
          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2
                 M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
          </svg>
        </div>
        <div class="text-center">
          <p class="font-bold text-gray-800 text-sm">Administrativo</p>
          <p class="text-gray-500 text-xs mt-0.5">Bienes y Suministros</p>
        </div>
      </button>

    </div>

    <!-- Info usuario -->
    <div class="mt-10 text-center text-green-200 text-sm">
      <p>{{ store.empleado?.apellido }} {{ store.empleado?.nombre }}</p>
      <button @click="logout" class="mt-2 text-green-300 hover:text-white underline text-xs">
        Cerrar sesión
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const router = useRouter()
const store  = useAuthStore()
const alertasStock = ref(0)

const tieneAccesoAdquisiciones = store.tieneAdquisiciones

const tieneAccesoTransportes = computed(() =>
  store.tieneRol('TRANSPORTE') || store.tieneRol('CONDUCTOR') ||
  !!store.empleado?.puede_solicitar_vehiculo
)

const rutaTransportes = computed(() => {
  if (store.tieneRol('TRANSPORTE')) return '/transporte/vehiculos'
  return '/transporte/movilizacion'
})

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
