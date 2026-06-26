<template>
  <div>
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Inventario Valorizado</h1>

    <!-- Controles -->
    <div class="bg-white rounded-xl shadow p-5 mb-5">
      <div class="flex flex-wrap gap-5 items-end">
        <!-- Tipo de vista -->
        <div>
          <label class="block text-xs text-gray-500 mb-2 font-medium">Vista</label>
          <div class="flex gap-2">
            <button @click="tipo = 'agrupado'" :class="tipo === 'agrupado' ? btnActivo : btnInactivo">
              Por categoría
            </button>
            <button @click="tipo = 'plano'" :class="tipo === 'plano' ? btnActivo : btnInactivo">
              Por código
            </button>
          </div>
        </div>

        <!-- Filtro existencias -->
        <div>
          <label class="block text-xs text-gray-500 mb-2 font-medium">Artículos</label>
          <div class="flex gap-2">
            <button @click="soloExistencias = true" :class="soloExistencias ? btnActivo : btnInactivo">
              Con existencias
            </button>
            <button @click="soloExistencias = false" :class="!soloExistencias ? btnActivo : btnInactivo">
              Todos
            </button>
          </div>
        </div>

        <!-- Botones -->
        <div class="flex gap-2 ml-auto">
          <button @click="generar" :disabled="cargando"
            class="text-white px-5 py-2 rounded-lg text-sm font-medium hover:opacity-90 disabled:opacity-50"
            style="background-color:#4a5e3a;">
            {{ cargando ? 'Cargando...' : 'Generar' }}
          </button>
          <button v-if="datos" @click="exportar('excel')" :disabled="exportando"
            class="bg-emerald-700 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-emerald-800 disabled:opacity-50">
            Excel
          </button>
          <button v-if="datos" @click="exportar('pdf')" :disabled="exportando"
            class="bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-800 disabled:opacity-50">
            PDF
          </button>
        </div>
      </div>
    </div>

    <!-- Resultado -->
    <div v-if="datos" class="bg-white rounded-xl shadow overflow-hidden">

      <!-- Resumen rápido -->
      <div class="px-5 py-3 border-b flex items-center gap-6 text-sm" style="background-color:#f7f9f4;">
        <span class="text-gray-500">
          <b class="text-gray-800">{{ totalArticulos }}</b> artículos
        </span>
        <span v-if="tipo === 'agrupado'" class="text-gray-500">
          <b class="text-gray-800">{{ datos.grupos?.length }}</b> categorías
        </span>
        <span class="font-semibold" style="color:#4a5e3a;">
          Total: ${{ fmt(datos.total_general) }}
        </span>
      </div>

      <!-- VISTA AGRUPADA -->
      <template v-if="tipo === 'agrupado'">
        <div v-for="grupo in datos.grupos" :key="grupo.descripcion">
          <!-- Cabecera grupo -->
          <div class="px-4 py-2 text-xs font-bold text-white uppercase flex justify-between items-center"
            style="background-color:#4a5e3a;">
            <span>{{ grupo.descripcion }}</span>
            <span class="font-mono">$ {{ fmt(grupo.subtotal) }}</span>
          </div>
          <!-- Artículos del grupo -->
          <table class="w-full text-sm">
            <thead>
              <tr class="text-xs text-gray-500 border-b bg-gray-50">
                <th class="text-left px-4 py-1.5 font-medium w-24">Código</th>
                <th class="text-left px-3 py-1.5 font-medium">Descripción</th>
                <th class="text-center px-3 py-1.5 font-medium w-20">Unidad</th>
                <th class="text-right px-3 py-1.5 font-medium w-20">Stock</th>
                <th class="text-right px-3 py-1.5 font-medium w-28">Precio Unit.</th>
                <th class="text-right px-4 py-1.5 font-medium w-28">Valor Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="a in grupo.articulos" :key="a.id"
                class="border-b hover:bg-green-50 transition-colors">
                <td class="px-4 py-1.5 font-mono text-xs text-gray-500">{{ a.codigo }}</td>
                <td class="px-3 py-1.5 text-gray-800">{{ a.nombre }}</td>
                <td class="px-3 py-1.5 text-center text-gray-500 text-xs">{{ a.unidad_medida }}</td>
                <td class="px-3 py-1.5 text-right font-mono">{{ fmt(a.stock_actual) }}</td>
                <td class="px-3 py-1.5 text-right font-mono text-gray-600">{{ fmtP(a.precio_unitario) }}</td>
                <td class="px-4 py-1.5 text-right font-mono font-medium" style="color:#4a5e3a;">
                  $ {{ fmt(a.valor_total) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <!-- VISTA PLANA -->
      <template v-else>
        <table class="w-full text-sm">
          <thead>
            <tr class="text-xs text-white" style="background-color:#4a5e3a;">
              <th class="text-left px-4 py-2 font-medium w-24">Código</th>
              <th class="text-left px-3 py-2 font-medium">Descripción</th>
              <th class="text-center px-3 py-2 font-medium w-20">Unidad</th>
              <th class="text-right px-3 py-2 font-medium w-20">Stock</th>
              <th class="text-right px-3 py-2 font-medium w-28">Precio Unit.</th>
              <th class="text-right px-4 py-2 font-medium w-28">Valor Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(a, i) in datos.articulos" :key="a.id"
              :class="i % 2 === 0 ? 'bg-white' : 'bg-green-50/40'"
              class="border-b hover:bg-green-50 transition-colors">
              <td class="px-4 py-1.5 font-mono text-xs text-gray-500">{{ a.codigo }}</td>
              <td class="px-3 py-1.5 text-gray-800">{{ a.nombre }}</td>
              <td class="px-3 py-1.5 text-center text-gray-500 text-xs">{{ a.unidad_medida }}</td>
              <td class="px-3 py-1.5 text-right font-mono">{{ fmt(a.stock_actual) }}</td>
              <td class="px-3 py-1.5 text-right font-mono text-gray-600">{{ fmtP(a.precio_unitario) }}</td>
              <td class="px-4 py-1.5 text-right font-mono font-medium" style="color:#4a5e3a;">
                $ {{ fmt(a.valor_total) }}
              </td>
            </tr>
          </tbody>
        </table>
      </template>

      <!-- Total general -->
      <div class="px-5 py-3 flex justify-end items-center gap-4 text-sm font-bold text-white"
        style="background-color:#4a5e3a;">
        <span>TOTAL GENERAL</span>
        <span class="font-mono text-base">$ {{ fmt(datos.total_general) }}</span>
      </div>
    </div>

    <div v-else-if="!cargando" class="bg-white rounded-xl shadow p-10 text-center text-gray-400">
      Selecciona las opciones y presiona Generar
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import api from '@/services/api'

const tipo            = ref('agrupado')
const soloExistencias = ref(true)
const datos           = ref(null)
const cargando        = ref(false)
const exportando      = ref(false)

const btnActivo  = 'px-4 py-1.5 rounded-lg text-sm font-medium text-white bg-[#4a5e3a]'
const btnInactivo = 'px-4 py-1.5 rounded-lg text-sm font-medium border border-gray-300 text-gray-600 hover:bg-gray-50'

const fmt  = v => Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const fmtP = v => Number(v).toLocaleString('en-US', { minimumFractionDigits: 4, maximumFractionDigits: 4 })

const totalArticulos = computed(() => {
  if (!datos.value) return 0
  if (tipo.value === 'agrupado') return datos.value.grupos?.reduce((s, g) => s + g.articulos.length, 0) ?? 0
  return datos.value.articulos?.length ?? 0
})

async function generar() {
  cargando.value = true
  datos.value = null
  try {
    const { data } = await api.get('/adquisiciones/reportes/inventario-valorizado', {
      params: {
        tipo: tipo.value,
        solo_existencias: soloExistencias.value ? '1' : '0',
      }
    })
    datos.value = data
  } finally {
    cargando.value = false
  }
}

async function exportar(formato) {
  exportando.value = true
  try {
    const resp = await api.get('/adquisiciones/reportes/inventario-valorizado', {
      params: {
        tipo: tipo.value,
        solo_existencias: soloExistencias.value ? '1' : '0',
        formato,
      },
      responseType: 'blob',
    })
    const ext  = formato === 'pdf' ? 'pdf' : 'xlsx'
    const mime = formato === 'pdf'
      ? 'application/pdf'
      : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    const blob = new Blob([resp.data], { type: mime })
    const url  = URL.createObjectURL(blob)
    if (formato === 'pdf') {
      window.open(url, '_blank')
      setTimeout(() => URL.revokeObjectURL(url), 60000)
    } else {
      const a = document.createElement('a')
      a.href = url
      a.download = `inventario-valorizado.${ext}`
      a.click()
      setTimeout(() => URL.revokeObjectURL(url), 5000)
    }
  } finally {
    exportando.value = false
  }
}
</script>

<style scoped>
@reference "tailwindcss";
</style>
