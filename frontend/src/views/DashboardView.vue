<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Dashboard</h1>

    <!-- Tarjetas -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div class="bg-white rounded-xl shadow p-6 flex items-center gap-4">
        <div class="bg-blue-100 p-3 rounded-full">
          <svg class="w-6 h-6 text-[#0b5447]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>
        </div>
        <div>
          <p class="text-sm text-gray-500">Empleados Activos</p>
          <p class="text-2xl font-bold text-gray-800">{{ stats.total_activos }}</p>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6 flex items-center gap-4">
        <div class="bg-green-100 p-3 rounded-full">
          <svg class="w-6 h-6 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
          </svg>
        </div>
        <div>
          <p class="text-sm text-gray-500">Departamentos</p>
          <p class="text-2xl font-bold text-gray-800">{{ stats.por_departamento.length }}</p>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6 flex items-center gap-4">
        <div class="bg-yellow-100 p-3 rounded-full">
          <svg class="w-6 h-6 text-yellow-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
        </div>
        <div>
          <p class="text-sm text-gray-500">Permisos Pendientes</p>
          <p class="text-2xl font-bold text-gray-800">{{ stats.permisos_pendientes }}</p>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6 flex items-center gap-4">
        <div class="bg-purple-100 p-3 rounded-full">
          <svg class="w-6 h-6 text-purple-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
          </svg>
        </div>
        <div>
          <p class="text-sm text-gray-500">Vacaciones Pendientes</p>
          <!-- ✅ CORREGIDO: era text="gray-800" -->
          <p class="text-2xl font-bold text-gray-800">{{ stats.vacaciones_pendientes }}</p>
        </div>
      </div>
    </div>

    <!-- Tabla por departamento (solo Admin y TH) -->
    <div v-if="esAdmin" class="bg-white rounded-xl shadow overflow-hidden">
      <div class="px-6 py-4 border-b">
        <h2 class="text-lg font-semibold text-gray-700">Empleados por Departamento</h2>
      </div>
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Departamento</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Total</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Proporción</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="dep in stats.por_departamento" :key="dep.nombre_depto"
            class="border-b hover:bg-gray-50">
            <td class="px-6 py-3 font-medium">{{ dep.nombre_depto }}</td>
            <td class="px-6 py-3">{{ dep.total }}</td>
            <td class="px-6 py-3 w-48">
              <div class="flex items-center gap-2">
                <div class="flex-1 bg-gray-200 rounded-full h-2">
                  <div class="bg-[#0b5447] h-2 rounded-full"
                    :style="{ width: (dep.total / stats.total_activos * 100) + '%' }">
                  </div>
                </div>
                <span class="text-xs text-gray-500">
                  {{ Math.round(dep.total / stats.total_activos * 100) }}%
                </span>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const auth    = useAuthStore()
const esAdmin = computed(() => auth.tieneRol('ADMINISTRADOR') || auth.tieneRol('TALENTO HUMANO'))

const stats = ref({
  total_activos: 0,
  por_departamento: [],
  permisos_pendientes: 0,
  vacaciones_pendientes: 0,
})

onMounted(async () => {
  const { data } = await api.get('/dashboard')
  stats.value = data
})
</script>
