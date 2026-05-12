<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Dashboard</h1>

    <!-- Tarjetas (solo admin/TH y empleados sin rol especial) -->
    <div v-if="!stats.es_supervisor || stats.es_admin_th" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
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

    <!-- SECCIÓN SUPERVISOR -->
    <template v-if="stats.es_supervisor && !stats.es_admin_th">

      <!-- Pendientes de acción -->
      <div>
        <h2 class="text-lg font-semibold text-gray-700 mb-3">Pendientes de acción</h2>
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
          <div v-for="item in pendientes" :key="item.label"
            class="bg-white rounded-xl shadow p-5 flex items-center gap-4 border-l-4"
            :class="item.valor > 0 ? 'border-red-500' : 'border-green-400'">
            <div class="p-3 rounded-full" :class="item.valor > 0 ? 'bg-red-100' : 'bg-green-100'">
              <svg class="w-6 h-6" :class="item.valor > 0 ? 'text-red-600' : 'text-green-600'"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icono"/>
              </svg>
            </div>
            <div>
              <p class="text-xs text-gray-500 leading-tight">{{ item.label }}</p>
              <p class="text-3xl font-bold" :class="item.valor > 0 ? 'text-red-600' : 'text-gray-700'">
                {{ item.valor }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Mi equipo hoy + Atrasos del mes -->
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

        <!-- Estado del equipo hoy -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow p-6">
          <h2 class="text-lg font-semibold text-gray-700 mb-4">
            Mi equipo hoy
            <span class="text-sm font-normal text-gray-400 ml-1">({{ stats.datos_supervisor?.total_equipo }} empleados)</span>
          </h2>
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div v-for="item in equipoHoy" :key="item.label"
              class="rounded-xl p-4 text-center" :class="item.bg">
              <p class="text-3xl font-bold" :class="item.color">{{ item.valor }}</p>
              <p class="text-xs mt-1 font-medium" :class="item.color">{{ item.label }}</p>
            </div>
          </div>
          <!-- Barra proporcional -->
          <div class="mt-4 h-3 rounded-full bg-gray-100 overflow-hidden flex">
            <div class="bg-green-500 h-full transition-all"
              :style="{ width: pct(stats.datos_supervisor?.presentes_hoy) + '%' }" />
            <div class="bg-yellow-400 h-full transition-all"
              :style="{ width: pct(stats.datos_supervisor?.con_permiso_hoy) + '%' }" />
            <div class="bg-blue-400 h-full transition-all"
              :style="{ width: pct(stats.datos_supervisor?.con_vacaciones_hoy) + '%' }" />
            <div class="bg-red-300 h-full transition-all"
              :style="{ width: pct(ausentesHoy) + '%' }" />
          </div>
          <div class="flex gap-4 mt-2 text-xs text-gray-400">
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500 inline-block"/>Presentes</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-yellow-400 inline-block"/>Permiso</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-400 inline-block"/>Vacaciones</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-300 inline-block"/>Sin marcar</span>
          </div>
        </div>

        <!-- Atrasos del mes -->
        <div class="bg-white rounded-xl shadow p-6 flex flex-col items-center justify-center text-center">
          <div class="bg-orange-100 p-4 rounded-full mb-3">
            <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <p class="text-5xl font-bold text-orange-600">{{ stats.datos_supervisor?.atrasos_mes ?? 0 }}</p>
          <p class="text-sm text-gray-500 mt-2">Días con atraso<br>en el equipo este mes</p>
        </div>

      </div>
    </template>

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
  es_supervisor: false,
  es_admin_th: false,
  datos_supervisor: null,
})

const ICONO_PERMISO    = 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'
const ICONO_VACACIONES = 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z'
const ICONO_HE         = 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'
const ICONO_MATERIAL   = 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'

const pendientes = computed(() => [
  { label: 'Permisos por aprobar',      valor: stats.value.permisos_pendientes,                       icono: ICONO_PERMISO },
  { label: 'Vacaciones por aprobar',    valor: stats.value.vacaciones_pendientes,                     icono: ICONO_VACACIONES },
  { label: 'Horas extras por aprobar',  valor: stats.value.datos_supervisor?.he_pendientes ?? 0,      icono: ICONO_HE },
  { label: 'Materiales por despachar',  valor: stats.value.datos_supervisor?.materiales_pendientes ?? 0, icono: ICONO_MATERIAL },
])

const ausentesHoy = computed(() => {
  const d = stats.value.datos_supervisor
  if (!d) return 0
  return Math.max(0, d.total_equipo - d.presentes_hoy - d.con_permiso_hoy - d.con_vacaciones_hoy)
})

const equipoHoy = computed(() => {
  const d = stats.value.datos_supervisor
  return [
    { label: 'Presentes',   valor: d?.presentes_hoy      ?? 0, bg: 'bg-green-50',  color: 'text-green-700' },
    { label: 'Con permiso', valor: d?.con_permiso_hoy    ?? 0, bg: 'bg-yellow-50', color: 'text-yellow-700' },
    { label: 'Vacaciones',  valor: d?.con_vacaciones_hoy ?? 0, bg: 'bg-blue-50',   color: 'text-blue-700' },
    { label: 'Sin marcar',  valor: ausentesHoy.value,          bg: 'bg-red-50',    color: 'text-red-700' },
  ]
})

function pct(val) {
  const total = stats.value.datos_supervisor?.total_equipo || 1
  return Math.min(100, Math.round(((val ?? 0) / total) * 100))
}

onMounted(async () => {
  const { data } = await api.get('/dashboard')
  stats.value = data
})
</script>
