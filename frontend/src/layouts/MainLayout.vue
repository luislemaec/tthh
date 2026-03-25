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

      <!-- Menú dinámico por categoría -->
      <nav class="flex-1 overflow-y-auto py-4">
        <template v-for="(items, categoria) in auth.menuAgrupado" :key="categoria">
          <div v-show="sidebarOpen" class="px-4 pt-3 pb-1">
            <p class="text-xs font-semibold uppercase tracking-wider"
               style="color: #95d0c7;">
              {{ categoria }}
            </p>
          </div>
          <router-link
            v-for="item in items" :key="item.id"
            :to="'/' + item.url"
            class="flex items-center gap-3 px-4 py-2.5 transition text-sm text-white/80 hover:text-white"
            style="--hover-bg: #00372e;"
            active-class="text-white border-r-2"
            :style="''"
            @mouseenter="$event.currentTarget.style.backgroundColor='#00372e'"
            @mouseleave="$event.currentTarget.style.backgroundColor=''">
            <span class="w-5 h-5 flex-shrink-0 flex items-center justify-center">
              <span class="w-1.5 h-1.5 rounded-full bg-current opacity-70"></span>
            </span>
            <span v-show="sidebarOpen">{{ item.descripcion }}</span>
          </router-link>
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
