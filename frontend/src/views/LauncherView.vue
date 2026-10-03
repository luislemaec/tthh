<template>
  <div class="min-h-screen flex flex-col" style="background: linear-gradient(135deg, #0b5447 0%, #00372e 100%);">

    <!-- Layout principal -->
    <div class="flex flex-1">

      <!-- Panel lateral vertical (solo si hay avisos y dirección=vertical) -->
      <div v-if="avisos.length && direccion === 'vertical'"
           class="w-56 flex-shrink-0 flex flex-col justify-center overflow-hidden py-8 pl-6">
        <p class="text-xs font-semibold text-green-300 uppercase tracking-widest mb-4">Avisos</p>
        <div class="relative h-80 overflow-hidden">
          <div class="ticker-vertical" :style="{ '--n': avisos.length }">
            <div v-for="(a, i) in [...avisos, ...avisos]" :key="i"
                 class="py-3 pr-4 border-b border-white/10 last:border-0">
              <p class="text-white text-sm leading-relaxed">{{ a.texto }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Contenido central -->
      <div class="flex-1 flex flex-col items-center justify-center p-8">
        <!-- Avatar empleado + nombre institución -->
        <div class="text-center mb-10">
          <div class="w-24 h-24 rounded-full overflow-hidden mx-auto mb-4 border-4 border-white/30 flex items-center justify-center"
               style="background-color:#ffffff22;">
            <img v-if="fotoEmpleado" :src="fotoEmpleado" class="w-full h-full object-cover" />
            <span v-else class="text-white text-3xl font-bold">{{ inicialesEmpleado }}</span>         
          </div>
        
      <!-- Info usuario -->
          <div class="mt-10 text-center text-green-200 text-sm">
            <p>{{ store.empleado?.apellido }} {{ store.empleado?.nombre }}</p>
          </div>
         <!----- <h1 class="text-2xl font-bold text-white tracking-wide">SELECCIONE UNA DE LAS TARJETAS</h1> --->
        <!-------  <p class="text-green-200 text-sm mt-1">_______________________</p>----->*
        </div>

        <!-- Tarjetas de aplicativos -->
        <div class="flex flex-wrap justify-center gap-4 max-w-4xl w-full">

      <!-- Talento Humano -->
      <button @click="irA('/dashboard')" @animationend="onAnimEnd"
        :class="cardAnimClass"
        class="bg-white rounded-xl shadow-lg p-3 w-36 flex flex-col items-center gap-3 hover:scale-105 transition-transform cursor-pointer">
        <div class="w-10 h-10 rounded-full flex items-center justify-center"
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
          <p class="text-gray-500 text-xs mt-0.5">Control y Gestión</p>
        </div>
      </button>

      <!-- Adquisiciones -->
      <button v-if="tieneAccesoAdquisiciones" @click="irA('/adquisiciones')" @animationend="onAnimEnd"
        :class="cardAnimClass"
        class="bg-white rounded-xl shadow-lg p-3 w-36 flex flex-col items-center gap-3 hover:scale-105 transition-transform cursor-pointer">
        <div class="w-10 h-10 rounded-full flex items-center justify-center bg-amber-600">
          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
          </svg>
        </div>
        <div class="text-center">
          <p class="font-bold text-gray-800 text-sm">Inventario y Suministros</p>
          <p class="text-gray-500 text-xs mt-0.5">Inventario, ingresos, egresos</p>
          <span v-if="alertasStock > 0"
            class="mt-1 inline-block bg-red-100 text-red-700 text-xs font-semibold px-2 py-0.5 rounded-full">
            {{ alertasStock }} alerta{{ alertasStock > 1 ? 's' : '' }}
          </span>
        </div>
      </button>

      <!-- Transportes -->
      <button v-if="tieneAccesoTransportes" @click="irA(rutaTransportes)" @animationend="onAnimEnd"
        :class="cardAnimClass"
        class="bg-white rounded-xl shadow-lg p-3 w-36 flex flex-col items-center gap-3 hover:scale-105 transition-transform cursor-pointer">
        <div class="w-10 h-10 rounded-full flex items-center justify-center"
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

      <!-- Comisiones de Servicio -->
      <button v-if="tieneAccesoComisiones" @click="irA(rutaComisiones)" @animationend="onAnimEnd"
        :class="cardAnimClass"
        class="bg-white rounded-xl shadow-lg p-3 w-36 flex flex-col items-center gap-3 hover:scale-105 transition-transform cursor-pointer">
        <div class="w-10 h-10 rounded-full flex items-center justify-center"
             style="background-color: #5c4a6e;">
          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
        </div>
        <div class="text-center">
          <p class="font-bold text-gray-800 text-sm">Comisiones</p>
          <p class="text-gray-500 text-xs mt-0.5">Viajes interior y exterior</p>
        </div>
      </button>

      <!-- Inventario Tecnológico -->
      <button v-if="tieneAccesoTecnologia" @click="irA('/tecnologia/equipos')" @animationend="onAnimEnd"
        :class="cardAnimClass"
        class="bg-white rounded-xl shadow-lg p-3 w-36 flex flex-col items-center gap-3 hover:scale-105 transition-transform cursor-pointer">
        <div class="w-10 h-10 rounded-full flex items-center justify-center"
             style="background-color: #4d7c8a;">
          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
          </svg>
        </div>
        <div class="text-center">
          <p class="font-bold text-gray-800 text-sm">Tecnología</p>
          <p class="text-gray-500 text-xs mt-0.5">Inventario y mantenimiento de equipos</p>
        </div>
      </button>

      <!-- Solicitudes de materiales (solo rol SUMINISTROS) -->
      <button v-if="store.tieneRol('SUMINISTROS') && !tieneAccesoAdquisiciones" @click="irA('/adquisiciones/solicitudes')" @animationend="onAnimEnd"
        :class="cardAnimClass"
        class="bg-white rounded-xl shadow-lg p-3 w-36 flex flex-col items-center gap-3 hover:scale-105 transition-transform cursor-pointer">
        <div class="w-10 h-10 rounded-full flex items-center justify-center bg-amber-600">
          <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2
                 M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
          </svg>
        </div>
        <div class="text-center">
          <p class="font-bold text-gray-800 text-sm">Inventario y Suministros</p>
          <p class="text-gray-500 text-xs mt-0.5">Inventario y Suministros</p>
        </div>
      </button>

        </div>

        <!-- Boton Cerrar sesión -->
        <div class="mt-10 text-center text-green-200 text-sm">
          <button @click="logout" class="mt-2 text-green-300 hover:text-white underline text-xs">
            Cerrar sesión
          </button>
        </div>
      </div>
    </div>

    <!-- Ticker horizontal (barra inferior) -->
    <div v-if="avisos.length && direccion === 'horizontal'"
         class="w-full overflow-hidden flex-shrink-0"
         style="background-color: #0b5447; border-top: 2px solid #1a8a6f; padding: 8px 0;">
      <div class="ticker-horizontal whitespace-nowrap">
        <span v-for="(a, i) in avisos" :key="i" class="inline-block text-white text-lg font-semibold mx-16">
          <span style="color: #6ee7b7; margin-right: 8px;">&#9679;</span>{{ a.texto }}
        </span>
      </div>
    </div>

  </div>

  <ChatbotFAB />

  <!-- Modal pendientes por aprobar -->
  <div v-if="modalPendientes"
       class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm overflow-hidden">
      <div class="px-6 py-4 flex items-center gap-3" style="background-color:#0b5447;">
        <svg class="w-6 h-6 text-white flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        <h2 class="text-white font-bold text-base">Tiene pendientes por aprobar</h2>
      </div>
      <div class="px-6 py-5">
        <p class="text-sm text-gray-500 mb-4">Los siguientes trámites están esperando su aprobación:</p>
        <ul class="space-y-3">
          <li v-if="pendientes.permisos > 0" class="flex items-center gap-3">
            <span class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                  style="background-color:#0b5447;">{{ pendientes.permisos }}</span>
            <span class="text-sm font-medium text-gray-700">Permiso{{ pendientes.permisos > 1 ? 's' : '' }} pendiente{{ pendientes.permisos > 1 ? 's' : '' }}</span>
          </li>
          <li v-if="pendientes.vacaciones > 0" class="flex items-center gap-3">
            <span class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                  style="background-color:#0b5447;">{{ pendientes.vacaciones }}</span>
            <span class="text-sm font-medium text-gray-700">Solicitud{{ pendientes.vacaciones > 1 ? 'es' : '' }} de vacaciones pendiente{{ pendientes.vacaciones > 1 ? 's' : '' }}</span>
          </li>
          <li v-if="pendientes.horas_extras > 0" class="flex items-center gap-3">
            <span class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                  style="background-color:#0b5447;">{{ pendientes.horas_extras }}</span>
            <span class="text-sm font-medium text-gray-700">Planificación{{ pendientes.horas_extras > 1 ? 'es' : '' }} de horas extras pendiente{{ pendientes.horas_extras > 1 ? 's' : '' }}</span>
          </li>
          <li v-if="pendientes.materiales > 0" class="flex items-center gap-3">
            <span class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                  style="background-color:#0b5447;">{{ pendientes.materiales }}</span>
            <span class="text-sm font-medium text-gray-700">Solicitud{{ pendientes.materiales > 1 ? 'es' : '' }} de materiales pendiente{{ pendientes.materiales > 1 ? 's' : '' }}</span>
          </li>
        </ul>
        <div class="mt-6 flex justify-end">
          <button @click="modalPendientes = false"
            class="px-6 py-2 rounded-lg text-white text-sm font-semibold"
            style="background-color:#0b5447;">
            Aceptar
          </button>
        </div>
      </div>
    </div>
  </div>

</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'
import ChatbotFAB from '@/components/ChatbotFAB.vue'

const router    = useRouter()
const store     = useAuthStore()
const alertasStock = ref(0)
const cardAnimClass = ref('')
const avisos    = ref([])
const direccion = ref('horizontal')
const modalPendientes = ref(false)
const pendientes = ref({ permisos: 0, vacaciones: 0, horas_extras: 0, materiales: 0 })

const ANIMATIONS = [
  'anim-flip-scale-up-hor',
  'anim-flip-scale-up-diag-2',
  'anim-flip-scale-down-hor',
  'anim-flip-scale-down-diag-2',
  'anim-flip-scale-up-ver',
  'anim-flip-scale-down-ver',
  'anim-flip-scale-up-diag-1',
  'anim-flip-scale-down-diag-1',
]

function onAnimEnd() {
  cardAnimClass.value = ''
}

const fotoEmpleado = computed(() => {
  const foto = store.empleado?.foto
  return foto ? `${import.meta.env.VITE_API_URL}/storage-file/${foto}` : null
})

const inicialesEmpleado = computed(() => {
  const a = store.empleado?.apellido?.[0] || ''
  const n = store.empleado?.nombre?.[0] || ''
  return (a + n).toUpperCase()
})

const tieneAccesoAdquisiciones = store.tieneAdquisiciones

const tieneAccesoTransportes = computed(() =>
  store.tieneRol('TRANSPORTE') || store.tieneRol('CONDUCTOR') ||
  !!store.empleado?.puede_solicitar_vehiculo
)

const tieneAccesoComisiones = computed(() =>
  store.tieneRol('MAXIMA AUTORIDAD') ||
  store.tieneRol('CONTABILIDAD') ||
  store.tieneRol('PRESUPUESTO') ||
  store.tieneRol('DIRECTOR FINANCIERO') ||
  store.tieneRol('TESORERIA') ||
  store.tieneRol('ADMINISTRADOR') ||
  store.tieneRol('COMISIONES') ||
  store.tieneRol('COMISIONADO EXTERNO') ||
  store.tieneRol('COMISIONADO')
)

const tieneAccesoTecnologia = computed(() =>
  store.tieneRol('TECNOLOGIA') || store.tieneRol('ADMINISTRADOR')
)

const rutaTransportes = computed(() => {
  if (store.tieneRol('TRANSPORTE')) return '/transporte/vehiculos'
  return '/transporte/movilizacion'
})

// Roles financieros no tienen comisiones/solicitudes en su menú — solo comisiones/liquidaciones.
// Ver CLAUDE.md sección "Módulo Comisiones de Servicios" > "Opciones de menú".
const rutaComisiones = computed(() => {
  const esFinanciero = store.tieneRol('CONTABILIDAD') || store.tieneRol('PRESUPUESTO') ||
    store.tieneRol('DIRECTOR FINANCIERO') || store.tieneRol('TESORERIA')
  if (esFinanciero && !store.tieneRol('COMISIONES') && !store.tieneRol('ADMINISTRADOR')) {
    return '/comisiones/liquidaciones'
  }
  return '/comisiones/solicitudes'
})

onMounted(async () => {
  cardAnimClass.value = ANIMATIONS[Math.floor(Math.random() * ANIMATIONS.length)]
  try {
    const { data } = await api.get('/adquisiciones/articulos/alertas')
    alertasStock.value = data.length
  } catch {}
  try {
    const { data } = await api.get('/admin/avisos/activos')
    avisos.value    = data.avisos
    direccion.value = data.direccion
  } catch {}

  if (sessionStorage.getItem('show_pendientes')) {
    sessionStorage.removeItem('show_pendientes')
    try {
      const { data } = await api.get('/dashboard/pendientes')
      const total = data.permisos + data.vacaciones + data.horas_extras + data.materiales
      if (total > 0) {
        pendientes.value   = data
        modalPendientes.value = true
      }
    } catch {}
  }
})

function irA(ruta) {
  router.push(ruta)
}

async function logout() {
  await store.logout()
  router.push('/login')
}
</script>

<style>
/* ── Ticker horizontal ───────────────────────────────────────────────── */
.ticker-horizontal {
  display: inline-block;
  animation: scroll-left 30s linear infinite;
}
.ticker-horizontal:hover { animation-play-state: paused; }

@keyframes scroll-left {
  0%   { transform: translateX(100vw); }
  100% { transform: translateX(-100%); }
}

/* ── Ticker vertical ─────────────────────────────────────────────────── */
.ticker-vertical {
  animation: scroll-up 20s linear infinite;
}
.ticker-vertical:hover { animation-play-state: paused; }

@keyframes scroll-up {
  0%   { transform: translateY(0); }
  100% { transform: translateY(-50%); }
}

/* ── Animaciones tarjetas ────────────────────────────────────────────── */
.anim-flip-scale-up-hor    { animation: flip-scale-up-hor    0.5s linear both; }
.anim-flip-scale-up-diag-2 { animation: flip-scale-up-diag-2 0.5s linear both; }
.anim-flip-scale-down-hor  { animation: flip-scale-down-hor  0.5s linear both; }
.anim-flip-scale-down-diag-2{ animation: flip-scale-down-diag-2 0.5s linear both; }
.anim-flip-scale-up-ver    { animation: flip-scale-up-ver    0.5s linear both; }
.anim-flip-scale-down-ver  { animation: flip-scale-down-ver  0.5s linear both; }
.anim-flip-scale-up-diag-1 { animation: flip-scale-up-diag-1 0.5s linear both; }
.anim-flip-scale-down-diag-1{ animation: flip-scale-down-diag-1 0.5s linear both; }

@keyframes flip-scale-up-hor {
  0%   { transform: scale(1)   rotateX(0);      }
  50%  { transform: scale(2.5) rotateX(-90deg); }
  100% { transform: scale(1)   rotateX(-180deg);}
}
@keyframes flip-scale-up-diag-2 {
  0%   { transform: scale(1)   rotate3d(-1,1,0,0deg);   }
  50%  { transform: scale(2.5) rotate3d(-1,1,0,90deg);  }
  100% { transform: scale(1)   rotate3d(-1,1,0,180deg); }
}
@keyframes flip-scale-down-hor {
  0%   { transform: scale(1)   rotateX(0);     }
  50%  { transform: scale(0.4) rotateX(90deg); }
  100% { transform: scale(1)   rotateX(180deg);}
}
@keyframes flip-scale-down-diag-2 {
  0%   { transform: scale(1)   rotate3d(-1,1,0,0deg);    }
  50%  { transform: scale(0.4) rotate3d(-1,1,0,-90deg);  }
  100% { transform: scale(1)   rotate3d(-1,1,0,-180deg); }
}
@keyframes flip-scale-up-ver {
  0%   { transform: scale(1)   rotateY(0);      }
  50%  { transform: scale(2.5) rotateY(90deg);  }
  100% { transform: scale(1)   rotateY(180deg); }
}
@keyframes flip-scale-down-ver {
  0%   { transform: scale(1)   rotateY(0);       }
  50%  { transform: scale(0.4) rotateY(-90deg);  }
  100% { transform: scale(1)   rotateY(-180deg); }
}
@keyframes flip-scale-up-diag-1 {
  0%   { transform: scale(1)   rotate3d(1,1,0,0deg);   }
  50%  { transform: scale(2.5) rotate3d(1,1,0,90deg);  }
  100% { transform: scale(1)   rotate3d(1,1,0,180deg); }
}
@keyframes flip-scale-down-diag-1 {
  0%   { transform: scale(1)   rotate3d(1,1,0,0deg);    }
  50%  { transform: scale(0.4) rotate3d(1,1,0,-90deg);  }
  100% { transform: scale(1)   rotate3d(1,1,0,-180deg); }
}
</style>
