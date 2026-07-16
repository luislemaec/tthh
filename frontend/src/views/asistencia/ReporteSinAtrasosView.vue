<template>
  <div class="space-y-6 max-w-5xl mx-auto">
    <div class="flex items-center gap-3">
      <router-link to="/asistencia" class="text-gray-400 hover:text-gray-600">← Volver</router-link>
      <h1 class="text-2xl font-bold text-gray-800">Reporte: Personal Sin Atrasos</h1>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow p-5">
      <div class="flex flex-wrap gap-4 items-end">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Desde</label>
          <input v-model="filtros.fecha_desde" type="date"
            class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Hasta</label>
          <input v-model="filtros.fecha_hasta" type="date"
            class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
        </div>
        <button @click="generar" :disabled="cargando"
          class="px-5 py-2 bg-[#0b5447] text-white rounded-lg text-sm font-medium hover:bg-[#00372e] disabled:opacity-50 transition-colors">
          {{ cargando ? 'Cargando...' : 'Generar' }}
        </button>
        <span v-if="generado" class="text-sm text-gray-500 ml-auto">
          {{ empleados.length }} empleado(s) sin atrasos en el período
        </span>
      </div>

      <div v-if="errorMsg" class="mt-3 text-sm text-red-600">{{ errorMsg }}</div>
    </div>

    <!-- Tabla -->
    <div v-if="generado" class="bg-white rounded-xl shadow overflow-hidden">
      <div v-if="empleados.length === 0" class="text-center py-12 text-gray-400 text-sm">
        No se encontraron empleados sin atrasos en el período seleccionado.
      </div>
      <template v-else>
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">#</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Departamento</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Apellidos y Nombres</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Cargo</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="(grupo, depto) in agrupados" :key="depto">
              <tr class="bg-[#0b5447]/5 border-t">
                <td colspan="4" class="px-4 py-2 text-xs font-bold text-[#0b5447] uppercase tracking-wide">
                  {{ depto }} ({{ grupo.length }})
                </td>
              </tr>
              <tr v-for="(emp, idx) in grupo" :key="emp.id_emp"
                class="border-t hover:bg-gray-50">
                <td class="px-4 py-2 text-gray-400 text-xs">{{ idx + 1 }}</td>
                <td class="px-4 py-2 text-gray-500 text-xs">{{ emp.nombre_depto }}</td>
                <td class="px-4 py-2 font-medium">{{ emp.apellido_emp }} {{ emp.nombre_emp }}</td>
                <td class="px-4 py-2 text-gray-600 text-xs">{{ emp.cargo_empleado || '—' }}</td>
              </tr>
            </template>
          </tbody>
        </table>

        <!-- Resumen -->
        <div class="border-t bg-gray-50 px-4 py-3 text-xs text-gray-500">
          Total: <strong class="text-gray-700">{{ empleados.length }} empleados</strong> sin atrasos
          del {{ filtros.fecha_desde }} al {{ filtros.fecha_hasta }}
        </div>
      </template>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import api from '@/services/api'

const hoy = new Date()
const primerDiaMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1).toISOString().substring(0, 10)
const hoyStr = hoy.toISOString().substring(0, 10)

const filtros  = ref({ fecha_desde: primerDiaMes, fecha_hasta: hoyStr })
const empleados = ref([])
const cargando  = ref(false)
const generado  = ref(false)
const errorMsg  = ref('')

const agrupados = computed(() => {
  const grupos = {}
  for (const emp of empleados.value) {
    const d = emp.nombre_depto || 'SIN DEPARTAMENTO'
    if (!grupos[d]) grupos[d] = []
    grupos[d].push(emp)
  }
  return grupos
})

async function generar() {
  if (!filtros.value.fecha_desde || !filtros.value.fecha_hasta) {
    errorMsg.value = 'Seleccione ambas fechas.'
    return
  }
  cargando.value = true
  errorMsg.value = ''
  try {
    const { data } = await api.get('/asistencia/reporte-sin-atrasos', { params: filtros.value })
    empleados.value = data
    generado.value  = true
  } catch (e) {
    errorMsg.value = e.response?.data?.message || 'Error al generar el reporte.'
  } finally {
    cargando.value = false
  }
}
</script>
