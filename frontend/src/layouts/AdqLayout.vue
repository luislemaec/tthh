<template>

  <!-- ── PANTALLA MANTENIMIENTO ADQ ── -->
  <div v-if="mantenimiento && !esAdminOAdq"
    class="fixed inset-0 z-[9999] flex flex-col items-center justify-center"
    style="background: linear-gradient(135deg,#4a5e3a,#3b4a2e);">
    <div class="text-center px-8 max-w-md">
      <div class="bg-white/10 rounded-full w-24 h-24 flex items-center justify-center mx-auto mb-6">
        <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
            d="M11.42 15.17L17.25 21A2.652 2.652 0 0021 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 11-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 004.486-6.336l-3.276 3.277a3.004 3.004 0 01-2.25-2.25l3.276-3.276a4.5 4.5 0 00-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437l1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z"/>
        </svg>
      </div>
      <h1 class="text-2xl font-bold text-white mb-3">Sistema en Mantenimiento</h1>
      <p class="text-white/80 text-sm leading-relaxed mb-6">
        El módulo de Adquisiciones se encuentra temporalmente en mantenimiento.<br>
        Por favor intente nuevamente en unos minutos.
      </p>
      <button @click="handleLogout"
        class="bg-white text-[#4a5e3a] font-semibold text-sm px-6 py-2.5 rounded-lg hover:bg-gray-100 transition mb-4">
        Cerrar Sesión
      </button>
      <p class="text-white/50 text-xs">Consejo de Comunicación — Adquisiciones</p>
    </div>
  </div>

  <div v-if="mantenimiento && esAdminOAdq"
    class="fixed top-0 left-0 right-0 z-[9998] bg-amber-500 text-white text-xs font-semibold text-center py-1.5 flex items-center justify-center gap-2">
    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
    </svg>
    MODO MANTENIMIENTO ACTIVO — Los usuarios no pueden acceder al módulo de Adquisiciones
  </div>

  <!-- ═══════════════════ MODO VERTICAL (sidebar) ═══════════════════ -->
  <div v-if="menuMode === 'vertical'" class="flex h-screen bg-gray-100 overflow-hidden">

    <aside :class="['text-white transition-all duration-300 flex flex-col', sidebarOpen ? 'w-64' : 'w-16']"
           style="background-color: #4a5e3a;">

      <div class="flex items-center gap-3 p-3 h-16 flex-shrink-0" style="border-bottom: 1px solid #3b4a2e;">
        <img src="@/assets/LOGOS-CONSEJOBLANCOH.png" alt="Logo"
             class="flex-shrink-0 object-contain"
             :class="sidebarOpen ? 'h-10 w-auto' : 'h-8 w-8'" />
        <span v-show="sidebarOpen" class="font-bold text-xs uppercase tracking-wide text-white truncate leading-tight">
          ADQUISICIONES
        </span>
      </div>

      <nav class="flex-1 overflow-y-auto py-2">

        <router-link v-if="esAdqOBienes" to="/adquisiciones/dashboard"
          class="flex items-center gap-3 px-4 py-2.5 text-sm transition-all duration-200"
          :style="$route.path === '/adquisiciones/dashboard' ? 'background-color:#3b4a2e' : ''"
          :class="$route.path === '/adquisiciones/dashboard' ? 'font-semibold text-white' : 'text-green-100 hover:bg-[#3b4a2e]'"
          active-class="">
          <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
          </svg>
          <span v-show="sidebarOpen">Dashboard</span>
        </router-link>

        <template v-for="grupo in menuGrupos" :key="grupo.label">
          <div class="mt-3 mb-0.5">
            <button v-if="sidebarOpen"
              @click="toggleGrupo(grupo.label)"
              class="w-full flex items-center justify-between px-4 py-1 select-none focus:outline-none">
              <span class="text-xs font-bold tracking-widest uppercase transition-colors duration-300"
                :style="gruposAbiertos[grupo.label] ? 'color:rgba(255,255,255,0.8)' : 'color:rgba(255,255,255,0.4)'">
                {{ grupo.label }}
              </span>
              <svg class="w-3.5 h-3.5 flex-shrink-0 transition-all duration-300 ease-in-out"
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
                :style="$route.path.startsWith(item.to) ? 'background-color:#3b4a2e; border-color:#8aad6a' : 'border-color:transparent'"
                :class="$route.path.startsWith(item.to) ? 'font-semibold text-white' : 'text-green-100 hover:bg-[#3b4a2e] hover:border-green-500'"
                active-class="">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
                </svg>
                <span v-show="sidebarOpen">{{ item.label }}</span>
                <span v-if="item.badge && item.badge > 0 && sidebarOpen"
                  class="ml-auto bg-red-500 text-white text-xs rounded-full px-1.5 py-0.5 leading-none">
                  {{ item.badge }}
                </span>
              </router-link>
              <p v-if="!grupo.items.length && sidebarOpen" class="px-4 py-2 text-xs italic"
                style="color:rgba(255,255,255,0.3)">Próximamente...</p>
            </div>
          </Transition>
        </template>

      </nav>

      <div class="p-4 flex-shrink-0 space-y-2" style="border-top: 1px solid #3b4a2e;">
        <router-link to="/launcher"
          class="flex items-center gap-3 text-sm w-full hover:text-white transition text-green-200">
          <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
          </svg>
          <span v-show="sidebarOpen">Inicio</span>
        </router-link>
        <button @click="handleLogout"
          class="flex items-center gap-3 text-sm w-full hover:text-white transition text-green-200">
          <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
          </svg>
          <span v-show="sidebarOpen">Cerrar sesión</span>
        </button>
      </div>
    </aside>

    <div class="flex-1 flex flex-col overflow-hidden">
      <header class="bg-white h-16 flex items-center justify-between px-6"
              style="border-bottom: 2px solid #5c7348;">
        <div class="flex items-center gap-3">
          <button @click="sidebarOpen = !sidebarOpen" class="text-gray-500 hover:text-gray-700">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
          </button>
        </div>
        <div class="flex items-center gap-3">
          <button @click="toggleMenuMode" title="Cambiar a menú horizontal"
            class="text-gray-400 hover:text-gray-600 transition" >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
            </svg>
          </button>
          <span class="text-sm text-gray-600">{{ auth.empleado?.apellido }} {{ auth.empleado?.nombre }}</span>
          <div class="w-9 h-9 rounded-full overflow-hidden flex-shrink-0" style="background-color:#4a5e3a;">
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

    <!-- Header -->
    <header class="bg-white h-14 flex items-center justify-between px-5 flex-shrink-0"
            style="border-bottom: 1px solid #e5e7eb;">
      <div class="flex items-center gap-3">
        <img src="@/assets/LOGOS-CONSEJOBLANCOH.png" alt="Logo" class="h-8 w-auto object-contain" />
        <span class="font-bold text-xs uppercase tracking-wide text-gray-700 hidden sm:block">ADQUISICIONES</span>
      </div>
      <div class="flex items-center gap-3">
        <button @click="toggleMenuMode" title="Cambiar a menú vertical"
          class="text-gray-400 hover:text-gray-600 transition">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
          </svg>
        </button>
        <span class="text-sm text-gray-600">{{ auth.empleado?.apellido }} {{ auth.empleado?.nombre }}</span>
        <div class="w-8 h-8 rounded-full overflow-hidden flex-shrink-0" style="background-color:#4a5e3a;">
          <img v-if="fotoEmpleado" :src="fotoEmpleado" class="w-full h-full object-cover" />
          <span v-else class="w-full h-full flex items-center justify-center text-white text-sm font-bold">{{ iniciales }}</span>
        </div>
      </div>
    </header>

    <!-- Topnav -->
    <nav class="flex-shrink-0 flex items-stretch px-2 relative z-50" style="background-color:#4a5e3a; min-height:42px;">

      <!-- Dashboard -->
      <router-link v-if="esAdqOBienes" to="/adquisiciones/dashboard"
        class="flex items-center px-3 text-xs font-semibold text-green-100 hover:bg-[#3b4a2e] transition whitespace-nowrap"
        :class="$route.path === '/adquisiciones/dashboard' ? 'bg-[#3b4a2e] text-white' : ''"
        active-class="">
        Dashboard
      </router-link>

      <!-- Grupos como dropdowns -->
      <div v-for="grupo in menuGrupos" :key="grupo.label" class="relative">
        <button
          @click="toggleDropdown(grupo.label)"
          class="flex items-center gap-1 px-3 h-full text-xs font-semibold transition whitespace-nowrap"
          :class="dropdownAbierto === grupo.label ? 'bg-[#3b4a2e] text-white' : 'text-green-100 hover:bg-[#3b4a2e]'">
          {{ grupo.label }}
          <svg class="w-3 h-3 transition-transform duration-200"
            :class="dropdownAbierto === grupo.label ? 'rotate-180' : ''"
            fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
          </svg>
        </button>

        <!-- Dropdown panel -->
        <Transition
          enter-active-class="transition duration-150 ease-out"
          enter-from-class="opacity-0 translate-y-[-6px]"
          enter-to-class="opacity-100 translate-y-0"
          leave-active-class="transition duration-100 ease-in"
          leave-from-class="opacity-100 translate-y-0"
          leave-to-class="opacity-0 translate-y-[-6px]">
          <div v-if="dropdownAbierto === grupo.label"
            class="absolute left-0 top-full min-w-[200px] rounded-b-lg shadow-xl overflow-hidden"
            style="background-color:#3d5030; border: 1px solid #2e3d23; border-top:none;">
            <router-link v-for="item in grupo.items" :key="item.to" :to="item.to"
              @click="dropdownAbierto = null"
              class="flex items-center gap-2.5 px-4 py-2.5 text-sm transition"
              :class="$route.path.startsWith(item.to)
                ? 'bg-[#2e3d23] text-white font-semibold'
                : 'text-green-100 hover:bg-[#4a5e3a]'"
              active-class="">
              <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon" />
              </svg>
              {{ item.label }}
              <span v-if="item.badge && item.badge > 0"
                class="ml-auto bg-red-500 text-white text-xs rounded-full px-1.5 py-0.5 leading-none">
                {{ item.badge }}
              </span>
            </router-link>
          </div>
        </Transition>
      </div>

      <!-- Spacer + Inicio + Salir -->
      <div class="ml-auto flex items-stretch">
        <router-link to="/launcher"
          class="flex items-center px-3 text-xs text-green-200 hover:bg-[#3b4a2e] transition whitespace-nowrap gap-1.5">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
          </svg>
          Inicio
        </router-link>
        <button @click="handleLogout"
          class="flex items-center px-3 text-xs text-green-200 hover:bg-[#3b4a2e] transition whitespace-nowrap gap-1.5">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
          </svg>
          Salir
        </button>
      </div>
    </nav>

    <!-- Backdrop para cerrar dropdown -->
    <div v-if="dropdownAbierto" class="fixed inset-0 z-40" @click="dropdownAbierto = null" />

    <!-- Contenido -->
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

const sidebarOpen    = ref(true)
const alertasStock   = ref(0)
const gruposAbiertos = ref({})
const dropdownAbierto = ref(null)
const menuMode = ref(localStorage.getItem('adq_menu_mode') || 'vertical')
const mantenimiento  = ref(false)
const esAdminOAdq    = computed(() =>
  auth.tieneRol('ADMINISTRADOR') || auth.tieneRol('ADQUISICIONES')
)

function toggleMenuMode() {
  menuMode.value = menuMode.value === 'vertical' ? 'horizontal' : 'vertical'
  localStorage.setItem('adq_menu_mode', menuMode.value)
  dropdownAbierto.value = null
}

function toggleDropdown(label) {
  dropdownAbierto.value = dropdownAbierto.value === label ? null : label
}

function toggleGrupo(label) {
  gruposAbiertos.value[label] = !gruposAbiertos.value[label]
}

function slideDown(el) {
  el.style.overflow = 'hidden'
  el.style.height   = '0px'
  el.style.opacity  = '0'
  requestAnimationFrame(() => {
    el.style.transition = 'height 0.32s cubic-bezier(0.4,0,0.2,1), opacity 0.28s ease'
    el.style.height     = el.scrollHeight + 'px'
    el.style.opacity    = '1'
  })
}
function afterSlideDown(el) {
  el.style.height     = 'auto'
  el.style.overflow   = ''
  el.style.transition = ''
  el.style.opacity    = ''
}
function slideUp(el) {
  el.style.overflow = 'hidden'
  el.style.height   = el.scrollHeight + 'px'
  el.style.opacity  = '1'
  requestAnimationFrame(() => {
    el.style.transition = 'height 0.25s cubic-bezier(0.4,0,1,1), opacity 0.2s ease'
    el.style.height     = '0px'
    el.style.opacity    = '0'
  })
}

const esAdqOBienes = computed(() =>
  auth.tieneRol('ADQUISICIONES') || auth.tieneRol('BIENES') || auth.tieneRol('ADMINISTRADOR')
)

const ICON_DEFAULT = 'M4 6h16M4 12h16M4 18h16'
const iconPorUrl = {
  'adquisiciones/proveedores':            'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
  'adquisiciones/articulos':              'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4',
  'adquisiciones/catalogo':               'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 4h.01M9 12h.01M9 16h.01M13 8h3m-3 4h3m-3 4h3',
  'adquisiciones/iva':                    'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z',
  'adquisiciones/procesos-contratacion':  'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
  'adquisiciones/unidades-medida':        'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3',
  'adquisiciones/ingresos':               'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
  'adquisiciones/egresos':                'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 4h.01M9 12h.01M9 16h.01M13 8h3m-3 4h3m-3 4h3',
  'adquisiciones/ajustes':                'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
  'adquisiciones/solicitudes':            'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
  'adquisiciones/reportes/kardex':        'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
  'adquisiciones/reportes/libro-compras': 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
  'adquisiciones/reportes/egresos':       'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
}

const menuGrupos = computed(() => {
  const grupos = []
  for (const [categoria, items] of Object.entries(auth.menuAgrupado)) {
    const adqItems = items
      // El Dashboard ya tiene su enlace fijo arriba; si existe como opción de menú en BD
      // (la necesita el guard del router) no se repite dentro del grupo.
      .filter(item => item.url.startsWith('adquisiciones/') && item.url !== 'adquisiciones/dashboard')
      .map(item => ({
        to: '/' + item.url,
        label: item.descripcion,
        icon: iconPorUrl[item.url] || ICON_DEFAULT,
        badge: item.url === 'adquisiciones/articulos' ? alertasStock.value : 0,
      }))
    if (adqItems.length > 0) grupos.push({ label: categoria, items: adqItems })
  }
  return grupos
})

watch(menuGrupos, (grupos) => {
  for (const g of grupos) {
    if (!(g.label in gruposAbiertos.value)) gruposAbiertos.value[g.label] = false
  }
}, { immediate: true })

// Cerrar dropdown al navegar
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

onMounted(async () => {
  if (!esAdqOBienes.value && route.path === '/adquisiciones/dashboard') {
    router.replace('/adquisiciones/solicitudes')
  }
  abrirGrupoActivo(route.path)
  try {
    const { data } = await api.get('/adquisiciones/articulos/alertas')
    alertasStock.value = data.length
  } catch {}
  try {
    const { data } = await api.get('/modo-mantenimiento', { params: { modulo: 'ADQ' } })
    mantenimiento.value = data.activo
  } catch {}
})

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}
</script>
