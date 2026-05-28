<template>

  <!-- ═══════════════════ MODO VERTICAL (sidebar) ═══════════════════ -->
  <div v-if="menuMode === 'vertical'" class="flex h-screen bg-gray-100 overflow-hidden">

    <aside :class="['text-white transition-all duration-300 flex flex-col', sidebarOpen ? 'w-64' : 'w-16']"
           style="background-color: #1e3a5f;">

      <div class="flex items-center gap-3 p-3 h-16 flex-shrink-0" style="border-bottom: 1px solid #162d4a;">
        <img src="@/assets/LOGOS-CONSEJOBLANCOH.png" alt="Logo"
             class="flex-shrink-0 object-contain"
             :class="sidebarOpen ? 'h-10 w-auto' : 'h-8 w-8'" />
        <span v-show="sidebarOpen" class="font-bold text-xs uppercase tracking-wide text-white truncate leading-tight">
          TRANSPORTE
        </span>
      </div>

      <nav class="flex-1 overflow-y-auto py-2">
        <template v-for="grupo in menuGrupos" :key="grupo.label">
          <div class="mt-3 mb-0.5">
            <button v-if="sidebarOpen" @click="toggleGrupo(grupo.label)"
              class="w-full flex items-center justify-between px-4 py-1 select-none focus:outline-none">
              <span class="text-xs font-bold tracking-widest uppercase transition-colors duration-300"
                :style="gruposAbiertos[grupo.label] ? 'color:rgba(255,255,255,0.8)' : 'color:rgba(255,255,255,0.4)'">
                {{ grupo.label }}
              </span>
              <svg class="w-3.5 h-3.5 flex-shrink-0 transition-all duration-300"
                :class="gruposAbiertos[grupo.label] ? 'rotate-90' : 'rotate-0'"
                :style="gruposAbiertos[grupo.label] ? 'color:rgba(255,255,255,0.65)' : 'color:rgba(255,255,255,0.3)'"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
              </svg>
            </button>
            <div v-else class="mx-3 border-t" style="border-color:rgba(255,255,255,0.2)"></div>
          </div>

          <Transition @enter="slideDown" @after-enter="afterSlideDown" @leave="slideUp">
            <div v-show="!sidebarOpen || gruposAbiertos[grupo.label]">
              <router-link v-for="item in grupo.items" :key="item.to" :to="item.to"
                class="flex items-center gap-3 px-4 py-2.5 text-sm transition-all duration-200 border-l-2"
                :style="$route.path.startsWith(item.to) ? 'background-color:#162d4a; border-color:#6fa3d8' : 'border-color:transparent'"
                :class="$route.path.startsWith(item.to) ? 'font-semibold text-white' : 'text-blue-100 hover:bg-[#162d4a] hover:border-blue-400'"
                active-class="">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                </svg>
                <span v-show="sidebarOpen">{{ item.label }}</span>
              </router-link>
            </div>
          </Transition>
        </template>
      </nav>

      <div class="p-4 flex-shrink-0 space-y-2" style="border-top: 1px solid #162d4a;">
        <router-link to="/launcher" class="flex items-center gap-3 text-sm w-full hover:text-white transition text-blue-200">
          <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
          </svg>
          <span v-show="sidebarOpen">Inicio</span>
        </router-link>
        <button @click="handleLogout" class="flex items-center gap-3 text-sm w-full hover:text-white transition text-blue-200">
          <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
          </svg>
          <span v-show="sidebarOpen">Cerrar sesión</span>
        </button>
      </div>
    </aside>

    <div class="flex-1 flex flex-col overflow-hidden">
      <header class="bg-white h-16 flex items-center justify-between px-6" style="border-bottom: 2px solid #1e3a5f;">
        <button @click="sidebarOpen = !sidebarOpen" class="text-gray-500 hover:text-gray-700">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
        <div class="flex items-center gap-4">
          <button @click="toggleMenuMode" title="Cambiar a menú horizontal" class="text-gray-400 hover:text-gray-600 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
            </svg>
          </button>
          <span class="text-sm text-gray-600">{{ auth.empleado?.apellido }} {{ auth.empleado?.nombre }}</span>
          <div class="w-9 h-9 rounded-full overflow-hidden flex-shrink-0" style="background-color:#1e3a5f;">
            <img v-if="fotoEmpleado" :src="fotoEmpleado" class="w-full h-full object-cover" />
            <span v-else class="w-full h-full flex items-center justify-center text-white text-sm font-bold">{{ iniciales }}</span>
          </div>
        </div>
      </header>
      <main class="flex-1 overflow-auto p-6">
        <router-view />
      </main>
    </div>
  </div>

  <!-- ═══════════════════ MODO HORIZONTAL (topnav) ═══════════════════ -->
  <div v-else class="flex flex-col h-screen bg-gray-100 overflow-hidden">

    <header class="bg-white h-14 flex items-center justify-between px-5 flex-shrink-0"
            style="border-bottom: 1px solid #e5e7eb;">
      <div class="flex items-center gap-3">
        <img src="@/assets/LOGOS-CONSEJOBLANCOH.png" alt="Logo" class="h-8 w-auto object-contain" />
        <span class="font-bold text-xs uppercase tracking-wide text-gray-700 hidden sm:block">TRANSPORTE</span>
      </div>
      <div class="flex items-center gap-3">
        <button @click="toggleMenuMode" title="Cambiar a menú vertical" class="text-gray-400 hover:text-gray-600 transition">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
          </svg>
        </button>
        <span class="text-sm text-gray-600">{{ auth.empleado?.apellido }} {{ auth.empleado?.nombre }}</span>
        <div class="w-8 h-8 rounded-full overflow-hidden flex-shrink-0" style="background-color:#1e3a5f;">
          <img v-if="fotoEmpleado" :src="fotoEmpleado" class="w-full h-full object-cover" />
          <span v-else class="w-full h-full flex items-center justify-center text-white text-sm font-bold">{{ iniciales }}</span>
        </div>
      </div>
    </header>

    <nav class="flex-shrink-0 flex items-stretch px-2 relative z-50" style="background-color:#1e3a5f; min-height:42px;">

      <div v-for="grupo in menuGrupos" :key="grupo.label" class="relative">
        <button @click="toggleDropdown(grupo.label)"
          class="flex items-center gap-1 px-3 h-full text-xs font-semibold transition whitespace-nowrap"
          :class="dropdownAbierto === grupo.label ? 'bg-[#162d4a] text-white' : 'text-blue-200 hover:bg-[#162d4a] hover:text-white'">
          {{ grupo.label }}
          <svg class="w-3 h-3 transition-transform duration-200"
            :class="dropdownAbierto === grupo.label ? 'rotate-180' : ''"
            fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
          </svg>
        </button>

        <Transition
          enter-active-class="transition duration-150 ease-out"
          enter-from-class="opacity-0 translate-y-[-6px]"
          enter-to-class="opacity-100 translate-y-0"
          leave-active-class="transition duration-100 ease-in"
          leave-from-class="opacity-100 translate-y-0"
          leave-to-class="opacity-0 translate-y-[-6px]">
          <div v-if="dropdownAbierto === grupo.label"
            class="absolute left-0 top-full min-w-[210px] rounded-b-lg shadow-xl overflow-hidden"
            style="background-color:#162d4a; border:1px solid #0f2035; border-top:none;">
            <router-link v-for="item in grupo.items" :key="item.to" :to="item.to"
              @click="dropdownAbierto = null"
              class="flex items-center gap-2.5 px-4 py-2.5 text-sm transition"
              :class="$route.path.startsWith(item.to) ? 'bg-[#0f2035] text-white font-semibold' : 'text-blue-100 hover:bg-[#1e3a5f]'"
              active-class="">
              <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
              </svg>
              {{ item.label }}
            </router-link>
          </div>
        </Transition>
      </div>

      <div class="ml-auto flex items-stretch">
        <router-link to="/launcher"
          class="flex items-center px-3 text-xs text-blue-200 hover:bg-[#162d4a] hover:text-white transition whitespace-nowrap gap-1.5">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
          </svg>
          Inicio
        </router-link>
        <button @click="handleLogout"
          class="flex items-center px-3 text-xs text-blue-200 hover:bg-[#162d4a] hover:text-white transition whitespace-nowrap gap-1.5">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
          </svg>
          Salir
        </button>
      </div>
    </nav>

    <div v-if="dropdownAbierto" class="fixed inset-0 z-40" @click="dropdownAbierto = null" />

    <main class="flex-1 overflow-auto p-6">
      <router-view />
    </main>
  </div>

</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const router = useRouter()
const route  = useRoute()
const auth   = useAuthStore()

const sidebarOpen     = ref(true)
const gruposAbiertos  = ref({})
const dropdownAbierto = ref(null)
const menuMode        = ref(localStorage.getItem('trans_menu_mode') || 'vertical')

function toggleMenuMode() {
  menuMode.value = menuMode.value === 'vertical' ? 'horizontal' : 'vertical'
  localStorage.setItem('trans_menu_mode', menuMode.value)
  dropdownAbierto.value = null
}

function toggleDropdown(label) {
  dropdownAbierto.value = dropdownAbierto.value === label ? null : label
}

function toggleGrupo(label) {
  gruposAbiertos.value[label] = !gruposAbiertos.value[label]
}

function slideDown(el) {
  el.style.overflow = 'hidden'; el.style.height = '0px'; el.style.opacity = '0'
  requestAnimationFrame(() => {
    el.style.transition = 'height 0.32s cubic-bezier(0.4,0,0.2,1), opacity 0.28s ease'
    el.style.height = el.scrollHeight + 'px'; el.style.opacity = '1'
  })
}
function afterSlideDown(el) {
  el.style.height = 'auto'; el.style.overflow = ''; el.style.transition = ''; el.style.opacity = ''
}
function slideUp(el) {
  el.style.overflow = 'hidden'; el.style.height = el.scrollHeight + 'px'; el.style.opacity = '1'
  requestAnimationFrame(() => {
    el.style.transition = 'height 0.25s cubic-bezier(0.4,0,1,1), opacity 0.2s ease'
    el.style.height = '0px'; el.style.opacity = '0'
  })
}

const ICON_DEFAULT = 'M4 6h16M4 12h16M4 18h16'
const iconPorUrl = {
  'transporte/vehiculos':          'M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z',
  'transporte/mantenimiento':      'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
  'transporte/movilizacion':       'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7',
  'transporte/talleres':           'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
  'transporte/tipos-mantenimiento':'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
  'transporte/plan-preventivo':    'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
  'transporte/vales-combustible':  'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
}

const menuGrupos = computed(() => {
  const grupos = []
  for (const [categoria, items] of Object.entries(auth.menuAgrupado)) {
    const transItems = items
      .filter(item => item.url.startsWith('transporte/'))
      .map(item => ({
        to: '/' + item.url,
        label: item.descripcion,
        icon: iconPorUrl[item.url] || ICON_DEFAULT,
      }))
    if (transItems.length > 0) grupos.push({ label: categoria, items: transItems })
  }
  return grupos
})

watch(menuGrupos, (grupos) => {
  for (const g of grupos) {
    if (!(g.label in gruposAbiertos.value)) gruposAbiertos.value[g.label] = false
  }
}, { immediate: true })

watch(() => route.path, () => { dropdownAbierto.value = null })

const iniciales = computed(() => {
  const a = auth.empleado?.apellido?.[0] || ''
  const n = auth.empleado?.nombre?.[0] || ''
  return (a + n).toUpperCase()
})

const fotoEmpleado = computed(() => {
  const foto = auth.empleado?.foto
  return foto ? `${import.meta.env.VITE_API_URL}/storage-file/${foto}` : null
})

function abrirGrupoActivo(path) {
  for (const grupo of menuGrupos.value) {
    if (grupo.items.some(item => path.startsWith(item.to))) {
      gruposAbiertos.value[grupo.label] = true
      break
    }
  }
}

watch(() => route.path, abrirGrupoActivo)

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}

// ─── Notificaciones de movilización pendiente ────────────────────────────────
let pollingInterval = null
let ultimaAt = null

async function solicitarPermisoNotificaciones() {
  if (!('Notification' in window)) return
  if (Notification.permission === 'default') {
    await Notification.requestPermission()
  }
}

async function verificarPendientes() {
  try {
    const { data } = await api.get('/transporte/notificaciones-pendientes')
    if (data.pendientes === 0) { ultimaAt = data.ultima_at; return }

    // Primera vez: solo guardar referencia, no notificar
    if (ultimaAt === null) { ultimaAt = data.ultima_at; return }

    // Si llegó una solicitud más nueva que la última registrada → notificar
    if (data.ultima_at && data.ultima_at !== ultimaAt) {
      ultimaAt = data.ultima_at
      mostrarNotificacion(data.pendientes, data.items?.[0])
    }
  } catch {
    // Sin conexión o sin sesión — se ignora silenciosamente
  }
}

function mostrarNotificacion(total, ultima) {
  if (!('Notification' in window) || Notification.permission !== 'granted') return

  const destino = ultima?.lugar_destino ? `Destino: ${ultima.lugar_destino}` : ''
  const cuerpo  = total === 1
    ? `Nueva solicitud de movilización pendiente. ${destino}`
    : `${total} solicitudes de movilización pendientes.`

  const notif = new Notification('🚗 Pedido de Vehículo', {
    body: cuerpo,
    icon: '/favicon.ico',
    tag:  'movilizacion-pendiente',
    requireInteraction: false,
  })

  notif.onclick = () => {
    window.focus()
    router.push('/transporte/movilizacion')
    notif.close()
  }
}

onMounted(async () => {
  abrirGrupoActivo(route.path)
  await solicitarPermisoNotificaciones()
  await verificarPendientes()
  pollingInterval = setInterval(verificarPendientes, 30000)
})

onUnmounted(() => {
  if (pollingInterval) clearInterval(pollingInterval)
})
</script>
