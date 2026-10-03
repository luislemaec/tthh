<template>

  <!-- ── PANTALLA MANTENIMIENTO COMISIONES ── -->
  <div v-if="mantenimiento && !esAdminOCom"
    class="fixed inset-0 z-[9999] flex flex-col items-center justify-center"
    style="background: linear-gradient(135deg,#5c4a6e,#3d2f4a);">
    <div class="text-center px-8 max-w-md">
      <div class="bg-white/10 rounded-full w-24 h-24 flex items-center justify-center mx-auto mb-6">
        <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
        </svg>
      </div>
      <h1 class="text-2xl font-bold text-white mb-3">Sistema en Mantenimiento</h1>
      <p class="text-white/80 text-sm leading-relaxed mb-6">
        El módulo de Comisiones se encuentra temporalmente en mantenimiento.<br>
        Por favor intente nuevamente en unos minutos.
      </p>
      <button @click="handleLogout"
        class="bg-white text-[#5c4a6e] font-semibold text-sm px-6 py-2.5 rounded-lg hover:bg-gray-100 transition mb-4">
        Cerrar Sesión
      </button>
      <p class="text-white/50 text-xs">Consejo de Comunicación — Comisiones</p>
    </div>
  </div>

  <div v-if="mantenimiento && esAdminOCom"
    class="fixed top-0 left-0 right-0 z-[9998] bg-amber-500 text-white text-xs font-semibold text-center py-1.5 flex items-center justify-center gap-2">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
    </svg>
    MODO MANTENIMIENTO ACTIVO — Los usuarios no pueden acceder al módulo de Comisiones
  </div>

  <!-- ═══════════════════ MODO VERTICAL (sidebar) ═══════════════════ -->
  <div v-if="menuMode === 'vertical'" class="flex h-screen bg-gray-100 overflow-hidden">

    <aside :class="['text-white transition-all duration-300 flex flex-col', sidebarOpen ? 'w-64' : 'w-16']"
           style="background-color: #5c4a6e;">

      <div class="flex items-center gap-3 p-3 h-16 flex-shrink-0" style="border-bottom: 1px solid #4a3a5a;">
        <img src="@/assets/LOGOS-CONSEJOBLANCOH.png" alt="Logo"
             class="flex-shrink-0 object-contain"
             :class="sidebarOpen ? 'h-10 w-auto' : 'h-8 w-8'" />
        <span v-show="sidebarOpen" class="font-bold text-xs uppercase tracking-wide text-white truncate leading-tight">
          COMISIONES
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
                :style="$route.path.startsWith(item.to) ? 'background-color:#4a3a5a; border-color:#c4aee0' : 'border-color:transparent'"
                :class="$route.path.startsWith(item.to) ? 'font-semibold text-white' : 'text-purple-100 hover:bg-[#4a3a5a] hover:border-purple-300'"
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

      <div class="p-4 flex-shrink-0 space-y-2" style="border-top: 1px solid #4a3a5a;">
        <router-link to="/launcher" class="flex items-center gap-3 text-sm w-full hover:text-white transition text-purple-200">
          <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
          </svg>
          <span v-show="sidebarOpen">Inicio</span>
        </router-link>
        <button @click="handleLogout" class="flex items-center justify-center gap-2 w-full py-2 rounded-lg border border-white/30 bg-white/10 text-white text-sm font-medium transition hover:bg-white hover:text-[#5c4a6e]">
          <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
          </svg>
          <span v-show="sidebarOpen">Cerrar sesión</span>
        </button>
      </div>
    </aside>

    <div class="flex-1 flex flex-col overflow-hidden">
      <header class="bg-white h-16 flex items-center justify-between px-6" style="border-bottom: 2px solid #5c4a6e;">
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
          <div class="w-9 h-9 rounded-full overflow-hidden flex-shrink-0" style="background-color:#5c4a6e;">
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
        <span class="font-bold text-xs uppercase tracking-wide text-gray-700 hidden sm:block">COMISIONES</span>
      </div>
      <div class="flex items-center gap-3">
        <button @click="toggleMenuMode" title="Cambiar a menú vertical" class="text-gray-400 hover:text-gray-600 transition">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
          </svg>
        </button>
        <span class="text-sm text-gray-600">{{ auth.empleado?.apellido }} {{ auth.empleado?.nombre }}</span>
        <div class="w-8 h-8 rounded-full overflow-hidden flex-shrink-0" style="background-color:#5c4a6e;">
          <img v-if="fotoEmpleado" :src="fotoEmpleado" class="w-full h-full object-cover" />
          <span v-else class="w-full h-full flex items-center justify-center text-white text-sm font-bold">{{ iniciales }}</span>
        </div>
      </div>
    </header>

    <nav class="flex-shrink-0 flex items-stretch px-2 relative z-50" style="background-color:#5c4a6e; min-height:42px;">

      <div v-for="grupo in menuGrupos" :key="grupo.label" class="relative">
        <button @click="toggleDropdown(grupo.label)"
          class="flex items-center gap-1 px-3 h-full text-xs font-semibold transition whitespace-nowrap"
          :class="dropdownAbierto === grupo.label ? 'bg-[#4a3a5a] text-white' : 'text-purple-200 hover:bg-[#4a3a5a] hover:text-white'">
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
            style="background-color:#4a3a5a; border:1px solid #3d2f4a; border-top:none;">
            <router-link v-for="item in grupo.items" :key="item.to" :to="item.to"
              @click="dropdownAbierto = null"
              class="flex items-center gap-2.5 px-4 py-2.5 text-sm transition"
              :class="$route.path.startsWith(item.to) ? 'bg-[#3d2f4a] text-white font-semibold' : 'text-purple-100 hover:bg-[#5c4a6e]'"
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
          class="flex items-center px-3 text-xs text-purple-200 hover:bg-[#4a3a5a] hover:text-white transition whitespace-nowrap gap-1.5">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
          </svg>
          Inicio
        </router-link>
        <button @click="handleLogout"
          class="flex items-center px-3 text-xs text-purple-200 hover:bg-[#4a3a5a] hover:text-white transition whitespace-nowrap gap-1.5">
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

  <ChatbotFAB />

</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'
import ChatbotFAB from '@/components/ChatbotFAB.vue'

const router = useRouter()
const route  = useRoute()
const auth   = useAuthStore()

const sidebarOpen     = ref(true)
const gruposAbiertos  = ref({})
const dropdownAbierto = ref(null)
const menuMode        = ref(localStorage.getItem('com_menu_mode') || 'vertical')
const mantenimiento   = ref(false)
const esAdminOCom     = computed(() =>
  auth.tieneRol('ADMINISTRADOR') ||
  auth.tieneRol('CONTABILIDAD') ||
  auth.tieneRol('PRESUPUESTO') ||
  auth.tieneRol('DIRECTOR FINANCIERO') ||
  auth.tieneRol('TESORERIA') ||
  auth.tieneRol('MAXIMA AUTORIDAD') ||
  auth.tieneRol('DIRECCION ADMINISTRATIVA') ||
  auth.tieneRol('ASESORIA JURIDICA')
)

function toggleMenuMode() {
  menuMode.value = menuMode.value === 'vertical' ? 'horizontal' : 'vertical'
  localStorage.setItem('com_menu_mode', menuMode.value)
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
  'comisiones':            'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
  'comisiones/liquidaciones': 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
  'comisiones/tarifas':    'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z',
}

const menuGrupos = computed(() => {
  const grupos = []
  for (const [categoria, items] of Object.entries(auth.menuAgrupado)) {
    const comItems = items
      .filter(item => item.url.startsWith('comisiones'))
      .map(item => ({
        to: '/' + item.url,
        label: item.descripcion,
        icon: iconPorUrl[item.url] || ICON_DEFAULT,
      }))
    if (comItems.length > 0) grupos.push({ label: categoria, items: comItems })
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

onMounted(async () => {
  abrirGrupoActivo(route.path)
  try {
    const { data } = await api.get('/modo-mantenimiento', { params: { modulo: 'COM' } })
    mantenimiento.value = data.activo
  } catch {}
})
</script>
