<!-- ============================================================ -->
<!-- src/layouts/MainLayout.vue -->
<!-- ============================================================ -->
<template>
  <div class="flex h-screen bg-gray-100 overflow-hidden">

    <!-- Sidebar -->
    <aside :class="['bg-blue-900 text-white transition-all duration-300 flex flex-col',
                    sidebarOpen ? 'w-64' : 'w-16']">

      <!-- Logo -->
      <div class="flex items-center gap-3 p-4 border-b border-blue-800 h-16">
        <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center flex-shrink-0">
          <span class="text-blue-900 font-bold text-xs">TH</span>
        </div>
        <span v-show="sidebarOpen" class="font-bold text-sm truncate">CONSEJO - RRHH</span>
      </div>

      <!-- Menú dinámico por categoría -->
      <nav class="flex-1 overflow-y-auto py-4">
        <template v-for="(items, categoria) in auth.menuAgrupado" :key="categoria">
          <div v-show="sidebarOpen" class="px-4 py-2">
            <p class="text-blue-300 text-xs font-semibold uppercase tracking-wider">
              {{ categoria }}
            </p>
          </div>
          <router-link
            v-for="item in items" :key="item.id"
            :to="'/' + item.url"
            class="flex items-center gap-3 px-4 py-2.5 hover:bg-blue-800 transition
                   text-sm text-blue-100 hover:text-white"
            active-class="bg-blue-800 text-white border-r-2 border-white">
            <span class="w-5 h-5 flex-shrink-0 text-center text-xs">●</span>
            <span v-show="sidebarOpen">{{ item.descripcion }}</span>
          </router-link>
        </template>
      </nav>

      <!-- Footer sidebar -->
      <div class="border-t border-blue-800 p-4">
        <button @click="handleLogout"
          class="flex items-center gap-3 text-blue-200 hover:text-white text-sm w-full">
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
      <header class="bg-white shadow-sm h-16 flex items-center justify-between px-6">
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
          <div class="w-9 h-9 rounded-full bg-blue-700 text-white flex items-center
                      justify-center text-sm font-bold">
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
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router     = useRouter()
const auth       = useAuthStore()
const sidebarOpen = ref(true)

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
