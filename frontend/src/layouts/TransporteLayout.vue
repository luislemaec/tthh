<template>
  <div class="flex h-screen bg-gray-100 overflow-hidden">

    <!-- Sidebar Transportes -->
    <aside :class="['text-white transition-all duration-300 flex flex-col', sidebarOpen ? 'w-64' : 'w-16']"
           style="background-color: #1e3a5f;">

      <!-- Logo -->
      <div class="flex items-center gap-3 p-3 h-16 flex-shrink-0" style="border-bottom: 1px solid #162d4a;">
        <img src="@/assets/LOGOS-CONSEJOBLANCOH.png" alt="Logo"
             class="flex-shrink-0 object-contain"
             :class="sidebarOpen ? 'h-10 w-auto' : 'h-8 w-8'" />
        <span v-show="sidebarOpen" class="font-bold text-xs uppercase tracking-wide text-white truncate leading-tight">
          TRANSPORTES
        </span>
      </div>

      <!-- Menú -->
      <nav class="flex-1 overflow-y-auto py-2">

        <!-- Grupos dinámicos desde admin_opcion -->
        <template v-for="grupo in menuGrupos" :key="grupo.label">
          <div class="mt-3 mb-0.5">
            <button v-if="sidebarOpen"
              @click="toggleGrupo(grupo.label)"
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
                :style="$route.path.startsWith(item.to)
                  ? 'background-color:#162d4a; border-color:#6fa3d8'
                  : 'border-color:transparent'"
                :class="$route.path.startsWith(item.to)
                  ? 'font-semibold text-white'
                  : 'text-blue-100 hover:bg-[#162d4a] hover:border-blue-400'"
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

      <!-- Footer -->
      <div class="p-4 flex-shrink-0 space-y-2" style="border-top: 1px solid #162d4a;">
        <router-link to="/launcher"
          class="flex items-center gap-3 text-sm w-full hover:text-white transition text-blue-200">
          <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
          </svg>
          <span v-show="sidebarOpen">Inicio</span>
        </router-link>
        <button @click="handleLogout"
          class="flex items-center gap-3 text-sm w-full hover:text-white transition text-blue-200">
          <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
          </svg>
          <span v-show="sidebarOpen">Cerrar sesión</span>
        </button>
      </div>
    </aside>

    <!-- Contenido -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <header class="bg-white h-16 flex items-center justify-between px-6"
              style="border-bottom: 2px solid #1e3a5f;">
        <button @click="sidebarOpen = !sidebarOpen" class="text-gray-500 hover:text-gray-700">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
        <div class="flex items-center gap-4">
          <span class="text-sm text-gray-600">{{ auth.empleado?.apellido }} {{ auth.empleado?.nombre }}</span>
          <div class="w-9 h-9 rounded-full text-white flex items-center justify-center text-sm font-bold"
               style="background-color:#1e3a5f;">
            {{ iniciales }}
          </div>
        </div>
      </header>
      <main class="flex-1 overflow-auto p-6">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const route  = useRoute()
const auth   = useAuthStore()
const sidebarOpen = ref(true)
const gruposAbiertos = ref({})

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
  'transporte/vehiculos':    'M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z',
  'transporte/mantenimiento':'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
  'transporte/movilizacion': 'M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7',
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

const iniciales = computed(() => {
  const a = auth.empleado?.apellido?.[0] || ''
  const n = auth.empleado?.nombre?.[0] || ''
  return (a + n).toUpperCase()
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
onMounted(() => abrirGrupoActivo(route.path))

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}
</script>
