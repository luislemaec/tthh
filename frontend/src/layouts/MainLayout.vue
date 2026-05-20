<!-- src/layouts/MainLayout.vue -->
<template>

  <!-- ═══════════════════ MODO VERTICAL (sidebar) ═══════════════════ -->
  <div v-if="menuMode === 'vertical'" class="flex h-screen bg-gray-100 overflow-hidden">

    <aside :class="['text-white transition-all duration-300 flex flex-col', sidebarOpen ? 'w-64' : 'w-16']"
           style="background-color: #0b5447;">

      <div class="flex items-center gap-3 p-3 h-16 flex-shrink-0" style="border-bottom: 1px solid #00372e;">
        <img src="@/assets/LOGOS-CONSEJOBLANCOH.png" alt="CORDICOM"
             class="flex-shrink-0 object-contain"
             :class="sidebarOpen ? 'h-10 w-auto' : 'h-8 w-8'" />
        <span v-show="sidebarOpen" class="font-bold text-xs uppercase tracking-wide text-white truncate leading-tight">
          TALENTO HUMANO
        </span>
      </div>

      <nav class="flex-1 overflow-y-auto py-3 space-y-0.5">
        <template v-for="(items, categoria) in menuFiltrado" :key="categoria">

          <div v-if="sidebarOpen">
            <button @click="toggleCategoria(categoria)"
              class="w-full flex items-center justify-between px-4 py-2 text-xs font-bold uppercase tracking-widest transition-all duration-200 rounded-none group"
              :style="categoriasAbiertas[categoria]
                ? 'color:#ffffff; background:rgba(255,255,255,0.08);'
                : 'color:#95d0c7; background:transparent;'">
              <div class="flex items-center gap-2">
                <span class="w-1.5 h-1.5 rounded-full transition-all duration-200"
                  :style="categoriasAbiertas[categoria] ? 'background:#95d0c7;' : 'background:#579186;'"></span>
                {{ categoria }}
              </div>
              <svg class="w-3.5 h-3.5 transition-transform duration-300 flex-shrink-0"
                :style="categoriasAbiertas[categoria] ? 'transform:rotate(180deg); opacity:1;' : 'opacity:0.5;'"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>

            <div class="overflow-hidden transition-all duration-300 ease-in-out"
              :style="categoriasAbiertas[categoria] ? 'max-height:500px;' : 'max-height:0px;'">
              <router-link
                v-for="(item, idx) in items" :key="item.id"
                :to="'/' + item.url"
                class="menu-item flex items-center gap-3 pl-7 pr-4 py-2 text-sm relative"
                :class="[
                  isActive(item.url) ? 'text-white font-medium' : 'text-white/65 hover:text-white',
                  categoriasAbiertas[categoria] ? 'menu-item-enter' : ''
                ]"
                :style="[
                  isActive(item.url) ? 'background:rgba(255,255,255,0.12);' : '',
                  categoriasAbiertas[categoria] ? `animation-delay:${idx * 60}ms` : ''
                ]"
                @mouseenter="e => { if (!isActive(item.url)) e.currentTarget.style.background='rgba(255,255,255,0.06)' }"
                @mouseleave="e => { if (!isActive(item.url)) e.currentTarget.style.background='' }">
                <span v-if="isActive(item.url)" class="absolute left-0 top-1 bottom-1 w-0.5 rounded-full"
                  style="background:#95d0c7;"></span>
                <span class="rounded-full flex-shrink-0 transition-all duration-150"
                  :style="isActive(item.url)
                    ? 'background:#95d0c7; width:6px; height:6px;'
                    : 'background:currentColor; opacity:0.5; width:4px; height:4px;'"></span>
                {{ item.descripcion }}
              </router-link>
            </div>
          </div>

          <template v-else>
            <router-link v-for="item in items" :key="item.id" :to="'/' + item.url"
              class="flex items-center justify-center w-full py-2.5 transition"
              :title="item.descripcion"
              :style="isActive(item.url) ? 'background:rgba(255,255,255,0.12);' : ''"
              @mouseenter="e => e.currentTarget.style.background='rgba(255,255,255,0.08)'"
              @mouseleave="e => { if (!isActive(item.url)) e.currentTarget.style.background='' }">
              <span class="w-2 h-2 rounded-full"
                :style="isActive(item.url) ? 'background:#95d0c7;' : 'background:rgba(255,255,255,0.4);'"></span>
            </router-link>
          </template>

        </template>
      </nav>

      <div class="p-4 flex-shrink-0 space-y-2" style="border-top: 1px solid #00372e;">
        <router-link to="/launcher" class="flex items-center gap-3 text-sm w-full hover:text-white transition" style="color:#95d0c7;">
          <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
          </svg>
          <span v-show="sidebarOpen">Inicio</span>
        </router-link>
        <router-link to="/perfil" class="flex items-center gap-3 text-sm w-full hover:text-white transition" style="color:#95d0c7;">
          <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
          </svg>
          <span v-show="sidebarOpen">Mi Perfil</span>
        </router-link>
        <button @click="handleLogout" class="flex items-center gap-3 text-sm w-full hover:text-white transition" style="color:#95d0c7;">
          <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
          </svg>
          <span v-show="sidebarOpen">Cerrar sesión</span>
        </button>
      </div>
    </aside>

    <div class="flex-1 flex flex-col overflow-hidden">
      <header class="bg-white h-16 flex items-center justify-between px-6" style="border-bottom: 2px solid #95d0c7;">
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
          <div class="w-9 h-9 rounded-full text-white flex items-center justify-center text-sm font-bold" style="background-color:#0b5447;">
            {{ iniciales }}
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
        <span class="font-bold text-xs uppercase tracking-wide text-gray-700 hidden sm:block">TALENTO HUMANO</span>
      </div>
      <div class="flex items-center gap-3">
        <button @click="toggleMenuMode" title="Cambiar a menú vertical" class="text-gray-400 hover:text-gray-600 transition">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
          </svg>
        </button>
        <span class="text-sm text-gray-600">{{ auth.empleado?.apellido }} {{ auth.empleado?.nombre }}</span>
        <div class="w-8 h-8 rounded-full text-white flex items-center justify-center text-sm font-bold" style="background-color:#0b5447;">
          {{ iniciales }}
        </div>
      </div>
    </header>

    <nav class="flex-shrink-0 flex items-stretch px-2 relative z-50" style="background-color:#0b5447; min-height:42px;">

      <div v-for="grupo in menuGruposArray" :key="grupo.label" class="relative">
        <button @click="toggleDropdown(grupo.label)"
          class="flex items-center gap-1 px-3 h-full text-xs font-semibold transition whitespace-nowrap"
          :class="dropdownAbierto === grupo.label ? 'text-white' : 'hover:text-white'"
          :style="dropdownAbierto === grupo.label ? 'background:rgba(255,255,255,0.12)' : 'color:#95d0c7'">
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
            class="absolute left-0 top-full min-w-[200px] rounded-b-lg shadow-xl overflow-hidden"
            style="background-color:#083d31; border:1px solid #00372e; border-top:none;">
            <router-link v-for="item in grupo.items" :key="item.id" :to="'/' + item.url"
              @click="dropdownAbierto = null"
              class="flex items-center gap-2.5 px-4 py-2.5 text-sm transition"
              :class="isActive(item.url) ? 'text-white font-semibold' : 'hover:text-white'"
              :style="isActive(item.url) ? 'background:rgba(255,255,255,0.12)' : 'color:#95d0c7'"
              active-class="">
              <span class="w-1.5 h-1.5 rounded-full flex-shrink-0"
                :style="isActive(item.url) ? 'background:#95d0c7' : 'background:rgba(149,208,199,0.5)'"></span>
              {{ item.descripcion }}
            </router-link>
          </div>
        </Transition>
      </div>

      <div class="ml-auto flex items-stretch">
        <router-link to="/perfil"
          class="flex items-center px-3 text-xs transition whitespace-nowrap gap-1.5 hover:text-white"
          style="color:#95d0c7">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
          </svg>
          Perfil
        </router-link>
        <router-link to="/launcher"
          class="flex items-center px-3 text-xs transition whitespace-nowrap gap-1.5 hover:text-white"
          style="color:#95d0c7">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
          </svg>
          Inicio
        </router-link>
        <button @click="handleLogout"
          class="flex items-center px-3 text-xs transition whitespace-nowrap gap-1.5 hover:text-white"
          style="color:#95d0c7">
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
import { ref, computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const route  = useRoute()
const auth   = useAuthStore()

const sidebarOpen     = ref(true)
const dropdownAbierto = ref(null)
const menuMode        = ref(localStorage.getItem('th_menu_mode') || 'vertical')

function toggleMenuMode() {
  menuMode.value = menuMode.value === 'vertical' ? 'horizontal' : 'vertical'
  localStorage.setItem('th_menu_mode', menuMode.value)
  dropdownAbierto.value = null
}

function toggleDropdown(label) {
  dropdownAbierto.value = dropdownAbierto.value === label ? null : label
}

const menuFiltrado = computed(() => {
  const result = {}
  for (const [cat, items] of Object.entries(auth.menuAgrupado || {})) {
    const filtered = items.filter(item =>
      !item.url.startsWith('adquisiciones/') && !item.url.startsWith('transporte/')
    )
    if (filtered.length > 0) result[cat] = filtered
  }
  return result
})

const menuGruposArray = computed(() =>
  Object.entries(menuFiltrado.value).map(([label, items]) => ({ label, items }))
)

const categoriasAbiertas = ref({})

const abrirCategoriaActiva = () => {
  const currentPath = route.path.replace(/^\//, '')
  for (const [categoria, items] of Object.entries(menuFiltrado.value || {})) {
    const tieneActivo = items.some(item => currentPath.startsWith(item.url))
    if (tieneActivo) {
      categoriasAbiertas.value[categoria] = true
    } else if (!(categoria in categoriasAbiertas.value)) {
      categoriasAbiertas.value[categoria] = false
    }
  }
}

const toggleCategoria = (categoria) => {
  categoriasAbiertas.value[categoria] = !categoriasAbiertas.value[categoria]
}

const isActive = (url) => route.path.replace(/^\//, '').startsWith(url)

watch(() => route.path, () => { dropdownAbierto.value = null })
watch(() => route.path, abrirCategoriaActiva)
watch(() => menuFiltrado.value, abrirCategoriaActiva, { immediate: true })

const iniciales = computed(() => {
  const n = auth.empleado?.nombre?.[0] || ''
  const a = auth.empleado?.apellido?.[0] || ''
  return (n + a).toUpperCase()
})

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}
</script>

<style scoped>
@keyframes menuSlideIn {
  0%   { opacity: 0; transform: translateX(-14px) scale(0.95); }
  60%  { transform: translateX(3px) scale(1.01); }
  100% { opacity: 1; transform: translateX(0) scale(1); }
}
.menu-item-enter { animation: menuSlideIn 0.28s ease-out both; }
</style>
