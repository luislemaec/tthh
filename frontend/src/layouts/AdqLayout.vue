<template>
  <div class="flex h-screen bg-gray-100 overflow-hidden">

    <!-- Sidebar Adquisiciones -->
    <aside :class="['text-white transition-all duration-300 flex flex-col', sidebarOpen ? 'w-64' : 'w-16']"
           style="background-color: #4a5e3a;">

      <!-- Logo -->
      <div class="flex items-center gap-3 p-3 h-16 flex-shrink-0" style="border-bottom: 1px solid #3b4a2e;">
        <img src="@/assets/LOGOS-CONSEJOBLANCOH.png" alt="Logo"
             class="flex-shrink-0 object-contain"
             :class="sidebarOpen ? 'h-10 w-auto' : 'h-8 w-8'" />
        <span v-show="sidebarOpen" class="font-bold text-xs uppercase tracking-wide text-white truncate leading-tight">
          ADQUISICIONES
        </span>
      </div>

      <!-- Menú -->
      <nav class="flex-1 overflow-y-auto py-2">

        <!-- Dashboard -->
        <router-link to="/adquisiciones/dashboard"
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

        <!-- Grupos -->
        <template v-for="grupo in menuGrupos" :key="grupo.label">

          <!-- Encabezado de grupo -->
          <div class="mt-3 mb-0.5">
            <button v-if="sidebarOpen"
              @click="toggleGrupo(grupo.label)"
              class="w-full flex items-center justify-between px-4 py-1 select-none focus:outline-none group">
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

          <!-- Items con animación acordeón -->
          <Transition @enter="slideDown" @after-enter="afterSlideDown" @leave="slideUp">
            <div v-show="!sidebarOpen || gruposAbiertos[grupo.label]">
              <router-link v-for="item in grupo.items" :key="item.to" :to="item.to"
                class="flex items-center gap-3 px-4 py-2.5 text-sm transition-all duration-200 border-l-2"
                :style="$route.path.startsWith(item.to)
                  ? 'background-color:#3b4a2e; border-color:#8aad6a'
                  : 'border-color:transparent'"
                :class="$route.path.startsWith(item.to)
                  ? 'font-semibold text-white'
                  : 'text-green-100 hover:bg-[#3b4a2e] hover:border-green-500'"
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

              <p v-if="!grupo.items.length && sidebarOpen"
                class="px-4 py-2 text-xs italic"
                style="color:rgba(255,255,255,0.3)">
                Próximamente...
              </p>
            </div>
          </Transition>

        </template>

      </nav>

      <!-- Footer -->
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

    <!-- Contenido -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <header class="bg-white h-16 flex items-center justify-between px-6"
              style="border-bottom: 2px solid #5c7348;">
        <button @click="sidebarOpen = !sidebarOpen" class="text-gray-500 hover:text-gray-700">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
        <div class="flex items-center gap-4">
          <span class="text-sm text-gray-600">{{ auth.empleado?.apellido_emp }} {{ auth.empleado?.nombre_emp }}</span>
          <div class="w-9 h-9 rounded-full text-white flex items-center justify-center text-sm font-bold" style="background-color:#4a5e3a;">
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
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const router = useRouter()
const auth   = useAuthStore()
const sidebarOpen  = ref(true)
const alertasStock = ref(0)

const gruposAbiertos = ref({
  'ADMINISTRACIÓN': true,
  'MOVIMIENTOS':    true,
  'SOLICITUDES':    true,
  'REPORTES':       true,
})

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

const esBienes = computed(() => auth.tieneRol('BIENES'))
const esAdq    = computed(() => auth.tieneRol('ADQUISICIONES'))
const esAdmin  = computed(() => auth.tieneRol('ADMINISTRADOR') || auth.tieneRol('TALENTO HUMANO'))

const menuGrupos = computed(() => {
  const grupos = []

  if (esBienes.value || esAdq.value || esAdmin.value) {
    grupos.push({
      label: 'ADMINISTRACIÓN',
      items: [
        { to: '/adquisiciones/proveedores',          label: 'Proveedores',           icon: 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4' },
        { to: '/adquisiciones/articulos',            label: 'Inventario',            icon: 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4', badge: alertasStock.value },
        { to: '/adquisiciones/catalogo',             label: 'Catálogo MF',           icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 4h.01M9 12h.01M9 16h.01M13 8h3m-3 4h3m-3 4h3' },
        { to: '/adquisiciones/iva',                  label: 'Tasas IVA',             icon: 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z' },
        { to: '/adquisiciones/procesos-contratacion', label: 'Procesos Contratación', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
        { to: '/adquisiciones/unidades-medida',      label: 'Unidades de Medida',    icon: 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3' },
      ],
    })

    grupos.push({
      label: 'MOVIMIENTOS',
      items: [
        { to: '/adquisiciones/ingresos', label: 'Ingresos de Bienes', icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01' },
        { to: '/adquisiciones/egresos',  label: 'Egresos de Bienes',  icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 4h.01M9 12h.01M9 16h.01M13 8h3m-3 4h3m-3 4h3' },
      ],
    })
  }

  grupos.push({
    label: 'SOLICITUDES',
    items: [
      { to: '/adquisiciones/solicitudes', label: 'Solicitudes', icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
    ],
  })

  grupos.push({
    label: 'REPORTES',
    items: [
      { to: '/adquisiciones/reportes/kardex',        label: 'Kardex',             icon: 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
      { to: '/adquisiciones/reportes/libro-compras', label: 'Libro de Compras',   icon: 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z' },
      { to: '/adquisiciones/reportes/egresos',       label: 'Egresos Valorizados', icon: 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z' },
    ],
  })

  return grupos
})

const iniciales = computed(() => {
  const a = auth.empleado?.apellido_emp?.[0] || ''
  const n = auth.empleado?.nombre_emp?.[0] || ''
  return (a + n).toUpperCase()
})

onMounted(async () => {
  try {
    const { data } = await api.get('/adquisiciones/articulos/alertas')
    alertasStock.value = data.length
  } catch {}
})

async function handleLogout() {
  await auth.logout()
  router.push('/login')
}
</script>
