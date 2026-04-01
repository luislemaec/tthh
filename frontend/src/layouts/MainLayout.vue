<!-- ============================================================ -->
<!-- src/layouts/MainLayout.vue -->
<!-- ============================================================ -->
<template>
  <div class="flex h-screen bg-gray-100 overflow-hidden">

    <!-- Sidebar -->
    <aside :class="['text-white transition-all duration-300 flex flex-col',
                    sidebarOpen ? 'w-64' : 'w-16']"
           style="background-color: #0b5447;">

      <!-- Logo -->
      <div class="flex items-center gap-3 p-3 h-16 flex-shrink-0"
           style="border-bottom: 1px solid #00372e;">
        <img src="@/assets/LOGOS-CONSEJOBLANCOH.png"
             alt="CORDICOM"
             class="flex-shrink-0 object-contain"
             :class="sidebarOpen ? 'h-10 w-auto' : 'h-8 w-8'" />
        <span v-show="sidebarOpen"
              class="font-bold text-xs uppercase tracking-wide text-white truncate leading-tight">
          TALENTO HUMANO
        </span>
      </div>

      <!-- Menú acordeón -->
      <nav class="flex-1 overflow-y-auto py-3 space-y-0.5">
        <template v-for="(items, categoria) in auth.menuAgrupado" :key="categoria">

          <!-- Cabecera de categoría (solo visible con sidebar abierto) -->
          <div v-if="sidebarOpen">
            <button @click="toggleCategoria(categoria)"
              class="w-full flex items-center justify-between px-4 py-2 text-xs font-bold uppercase tracking-widest transition-all duration-200 rounded-none group"
              :style="categoriasAbiertas[categoria]
                ? 'color:#ffffff; background:rgba(255,255,255,0.08);'
                : 'color:#95d0c7; background:transparent;'">
              <div class="flex items-center gap-2">
                <!-- Indicador de categoría -->
                <span class="w-1.5 h-1.5 rounded-full transition-all duration-200"
                  :style="categoriasAbiertas[categoria] ? 'background:#95d0c7;' : 'background:#579186;'">
                </span>
                {{ categoria }}
              </div>
              <!-- Chevron animado -->
              <svg class="w-3.5 h-3.5 transition-transform duration-300 flex-shrink-0"
                :style="categoriasAbiertas[categoria] ? 'transform:rotate(180deg); opacity:1;' : 'opacity:0.5;'"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>

            <!-- Items con animación slide -->
            <div class="overflow-hidden transition-all duration-300 ease-in-out"
              :style="categoriasAbiertas[categoria]
                ? 'max-height:500px; opacity:1;'
                : 'max-height:0px; opacity:0;'">
              <router-link
                v-for="item in items" :key="item.id"
                :to="'/' + item.url"
                class="flex items-center gap-3 pl-7 pr-4 py-2 text-sm transition-all duration-150 relative group/item"
                :class="isActive(item.url)
                  ? 'text-white font-medium'
                  : 'text-white/65 hover:text-white'"
                :style="isActive(item.url)
                  ? 'background:rgba(255,255,255,0.12);'
                  : ''"
                @mouseenter="e => { if (!isActive(item.url)) e.currentTarget.style.background='rgba(255,255,255,0.06)' }"
                @mouseleave="e => { if (!isActive(item.url)) e.currentTarget.style.background='' }">
                <!-- Barra activa izquierda -->
                <span v-if="isActive(item.url)"
                  class="absolute left-0 top-1 bottom-1 w-0.5 rounded-full"
                  style="background:#95d0c7;"></span>
                <!-- Punto -->
                <span class="w-1 h-1 rounded-full flex-shrink-0 transition-all duration-150"
                  :style="isActive(item.url) ? 'background:#95d0c7; width:6px; height:6px;' : 'background:currentColor; opacity:0.5;'">
                </span>
                {{ item.descripcion }}
              </router-link>
            </div>
          </div>

          <!-- Sidebar colapsado: solo puntos/iconos sin categoría -->
          <template v-else>
            <router-link
              v-for="item in items" :key="item.id"
              :to="'/' + item.url"
              class="flex items-center justify-center w-full py-2.5 transition"
              :title="item.descripcion"
              :style="isActive(item.url) ? 'background:rgba(255,255,255,0.12);' : ''"
              @mouseenter="e => e.currentTarget.style.background='rgba(255,255,255,0.08)'"
              @mouseleave="e => { if (!isActive(item.url)) e.currentTarget.style.background='' }">
              <span class="w-2 h-2 rounded-full"
                :style="isActive(item.url) ? 'background:#95d0c7;' : 'background:rgba(255,255,255,0.4);'">
              </span>
            </router-link>
          </template>

        </template>
      </nav>

      <!-- Footer sidebar -->
      <div class="p-4 flex-shrink-0" style="border-top: 1px solid #00372e;">
        <button @click="handleLogout"
          class="flex items-center gap-3 text-sm w-full hover:text-white transition"
          style="color: #95d0c7;">
          <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7
                 a3 3 0 013-3h4a3 3 0 013 3v1"/>
          </svg>
          <span v-show="sidebarOpen">Cerrar sesión</span>
        </button>
      </div>
    </aside>

    <!-- Contenido principal -->
    <div class="flex-1 flex flex-col overflow-hidden">

      <!-- Topbar -->
      <header class="bg-white h-16 flex items-center justify-between px-6"
              style="border-bottom: 2px solid #95d0c7;">
        <button @click="sidebarOpen = !sidebarOpen"
          class="text-gray-500 hover:text-gray-700">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>

        <div class="flex items-center gap-4">
          <span class="text-sm text-gray-600">
            {{ auth.empleado?.apellido }} {{ auth.empleado?.nombre }}
          </span>
          <div class="w-9 h-9 rounded-full text-white flex items-center
                      justify-center text-sm font-bold"
               style="background-color: #0b5447;">
            {{ iniciales }}
          </div>
        </div>
      </header>

      <!-- Vista activa -->
      <main class="flex-1 overflow-auto p-6">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router      = useRouter()
const route       = useRoute()
const auth        = useAuthStore()
const sidebarOpen = ref(true)

// Estado del acordeón — qué categorías están abiertas
const categoriasAbiertas = ref({})

// Abre automáticamente la categoría que contiene la ruta activa
const abrirCategoriaActiva = () => {
  const currentPath = route.path.replace(/^\//, '')
  for (const [categoria, items] of Object.entries(auth.menuAgrupado || {})) {
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

const isActive = (url) => {
  return route.path.replace(/^\//, '').startsWith(url)
}

// Re-evaluar cuando cambia la ruta o el menú
watch(() => route.path, abrirCategoriaActiva)
watch(() => auth.menuAgrupado, abrirCategoriaActiva, { immediate: true })

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
