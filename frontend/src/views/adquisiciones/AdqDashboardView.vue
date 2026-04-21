<template>
  <div>
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Dashboard — Adquisiciones</h1>

    <!-- KPIs -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
      <div class="bg-white rounded-xl shadow p-5">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Stock bajo mínimo</p>
        <p class="text-3xl font-bold mt-1" :class="stats.articulos_bajo_minimo > 0 ? 'text-red-600' : 'text-green-600'">
          {{ stats.articulos_bajo_minimo }}
        </p>
        <p class="text-xs text-gray-400 mt-1">artículos</p>
      </div>
      <div class="bg-white rounded-xl shadow p-5">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Solicitudes pendientes</p>
        <p class="text-3xl font-bold mt-1 text-yellow-600">{{ stats.solicitudes_pendientes }}</p>
        <p class="text-xs text-gray-400 mt-1">por aprobar</p>
      </div>
      <div class="bg-white rounded-xl shadow p-5">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Por despachar</p>
        <p class="text-3xl font-bold mt-1 text-blue-600">{{ stats.solicitudes_por_aprobar }}</p>
        <p class="text-xs text-gray-400 mt-1">solicitudes aprobadas</p>
      </div>
      <div class="bg-white rounded-xl shadow p-5">
        <p class="text-xs text-gray-500 uppercase tracking-wide">Órdenes en tránsito</p>
        <p class="text-3xl font-bold mt-1 text-purple-600">{{ stats.ordenes_en_transito }}</p>
        <p class="text-xs text-gray-400 mt-1">enviadas al proveedor</p>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <!-- Alertas de stock -->
      <div class="bg-white rounded-xl shadow p-5">
        <h2 class="font-semibold text-gray-700 mb-4 flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-red-500 inline-block"></span>
          Alertas de stock mínimo
        </h2>
        <div v-if="!stats.alertas?.length" class="text-gray-400 text-sm text-center py-4">
          Sin alertas de stock
        </div>
        <div v-else class="space-y-2">
          <div v-for="a in stats.alertas" :key="a.id"
            class="flex justify-between items-center border-b pb-2 text-sm">
            <div>
              <p class="font-medium text-gray-800">{{ a.nombre }}</p>
              <p class="text-xs text-gray-500">{{ a.categoria }}</p>
            </div>
            <div class="text-right">
              <span class="text-red-600 font-bold">{{ a.stock_actual }}</span>
              <span class="text-gray-400 text-xs"> / {{ a.unidad_medida }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Órdenes recientes -->
      <div class="bg-white rounded-xl shadow p-5">
        <h2 class="font-semibold text-gray-700 mb-4">Órdenes recientes</h2>
        <div v-if="!stats.ordenes_recientes?.length" class="text-gray-400 text-sm text-center py-4">
          Sin órdenes recientes
        </div>
        <div v-else class="space-y-2">
          <div v-for="o in stats.ordenes_recientes" :key="o.id"
            class="flex justify-between items-center border-b pb-2 text-sm">
            <div>
              <p class="font-medium text-gray-800">{{ o.proveedor?.nombre }}</p>
              <p class="text-xs text-gray-500">{{ o.fecha }}</p>
            </div>
            <span :class="{
              'bg-gray-100 text-gray-600':   o.estado === 'BORRADOR',
              'bg-blue-100 text-blue-700':   o.estado === 'ENVIADA',
              'bg-green-100 text-green-700': o.estado === 'RECIBIDA',
            }" class="text-xs px-2 py-0.5 rounded-full font-medium">{{ o.estado }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const stats = ref({
  articulos_bajo_minimo: 0, alertas: [],
  solicitudes_pendientes: 0, solicitudes_por_aprobar: 0,
  ordenes_en_transito: 0, ordenes_recientes: [],
})

onMounted(async () => {
  const { data } = await api.get('/adquisiciones/dashboard')
  stats.value = data
})
</script>
