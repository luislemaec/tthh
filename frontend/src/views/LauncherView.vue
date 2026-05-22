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
              <p class="text-white text-xs leading-relaxed">{{ a.texto }}</p>
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
         <!----- <h1 class="text-2xl font-bold text-white tracking-wide">SELECCIONE UNA DE LAS TARJETAS</h1> --->
        <!-------  <p class="text-green-200 text-sm mt-1">_______________________</p>----->*
        </div>

        <!-- Tarjetas de aplicativos -->
        <div class="flex flex-wrap justify-center gap-4 max-w-4xl w-full">

      <!-- Talento Humano -->
      <button @click="irA('/dashboard')" @animationend="onAnimEnd"
        :class="cardAnimClass"
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
      <button v-if="tieneAccesoAdquisiciones" @click="irA('/adquisiciones')" @animationend="onAnimEnd"
        :class="cardAnimClass"
        class="bg-white rounded-xl shadow-lg p-5 w-44 flex flex-col items-center gap-3 hover:scale-105 transition-transform cursor-pointer">
        <div class="w-12 h-12 rounded-full flex items-center justify-center bg-amber-600">
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
      <button v-if="!tieneAccesoAdquisiciones" @click="irA('/adquisiciones/solicitudes')" @animationend="onAnimEnd"
        :class="cardAnimClass"
        class="bg-white rounded-xl shadow-lg p-5 w-44 flex flex-col items-center gap-3 hover:scale-105 transition-transform cursor-pointer">
        <div class="w-12 h-12 rounded-full flex items-center justify-center bg-amber-600">
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

        <!-- Info usuario -->
        <div class="mt-10 text-center text-green-200 text-sm">
          <p>{{ store.empleado?.apellido }} {{ store.empleado?.nombre }}</p>
          <button @click="logout" class="mt-2 text-green-300 hover:text-white underline text-xs">
            Cerrar sesión
          </button>
        </div>
      </div>
    </div>

    <!-- Ticker horizontal (barra inferior) -->
    <div v-if="avisos.length && direccion === 'horizontal'"
         class="w-full overflow-hidden bg-black/25 py-2.5 flex-shrink-0">
      <div class="ticker-horizontal whitespace-nowrap">
        <span v-for="(a, i) in avisos" :key="i" class="inline-block text-white text-sm font-medium mx-12">
          <span class="text-green-300 mr-2">&#9679;</span>{{ a.texto }}
        </span>
        <!-- Duplicado para loop continuo -->
        <span v-for="(a, i) in avisos" :key="'d'+i" class="inline-block text-white text-sm font-medium mx-12">
          <span class="text-green-300 mr-2">&#9679;</span>{{ a.texto }}
        </span>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const router    = useRouter()
const store     = useAuthStore()
const alertasStock = ref(0)
const cardAnimClass = ref('')
const avisos    = ref([])
const direccion = ref('horizontal')

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

const rutaTransportes = computed(() => {
  if (store.tieneRol('TRANSPORTE')) return '/transporte/vehiculos'
  return '/transporte/movilizacion'
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
  0%   { transform: translateX(0); }
  100% { transform: translateX(-50%); }
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
