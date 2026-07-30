<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">LOTAIP</h1>

    <!-- Sub-tabs -->
    <div class="bg-white rounded-xl shadow overflow-hidden">
      <div class="flex border-b">
        <button
          v-for="lt in tabs" :key="lt.id"
          @click="cambiarTab(lt.id)"
          :class="tabActivo === lt.id
            ? 'border-b-2 border-[#0b5447] text-[#0b5447] font-medium bg-[#f0faf8]'
            : 'text-gray-500 hover:text-gray-700'"
          class="px-5 py-3 text-sm transition whitespace-nowrap">
          {{ lt.label }}
        </button>
      </div>

      <!-- Barra de acciones -->
      <div class="px-5 py-4 flex items-center gap-3 border-b bg-gray-50">
        <button @click="cargar" :disabled="cargando"
          class="bg-[#0b5447] text-white px-5 py-2 rounded-lg text-sm hover:bg-[#00372e] disabled:opacity-50 font-medium">
          {{ cargando ? 'Cargando...' : 'Generar' }}
        </button>
        <button v-if="datos.length > 0" @click="exportarExcel" :disabled="exportando"
          class="flex items-center gap-1.5 border border-green-600 text-green-700 px-4 py-2 rounded-lg text-sm hover:bg-green-50 disabled:opacity-50">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
          </svg>
          {{ exportando ? 'Generando...' : 'Exportar Excel' }}
        </button>
        <span v-if="datos.length > 0" class="text-sm text-gray-400 ml-auto">
          {{ datos.length }} registros
        </span>
      </div>

      <div v-if="error" class="px-5 py-3 text-sm text-red-600 bg-red-50">{{ error }}</div>

      <!-- Tabla Directorio y Distributivo -->
      <div v-if="tabActivo === 'directorio'" class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-center px-3 py-3 text-gray-600 font-medium w-10">Nro</th>
              <th class="text-left px-3 py-3 text-gray-600 font-medium">Apellidos y Nombres</th>
              <th class="text-left px-3 py-3 text-gray-600 font-medium">Dirección / Área</th>
              <th class="text-left px-3 py-3 text-gray-600 font-medium">Dirección Institucional</th>
              <th class="text-left px-3 py-3 text-gray-600 font-medium">Ciudad</th>
              <th class="text-center px-3 py-3 text-gray-600 font-medium">Teléfono</th>
              <th class="text-center px-3 py-3 text-gray-600 font-medium">Ext.</th>
              <th class="text-left px-3 py-3 text-gray-600 font-medium">Correo Electrónico</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="cargando">
              <td colspan="8" class="text-center py-10 text-gray-400">Cargando...</td>
            </tr>
            <tr v-else-if="datos.length === 0">
              <td colspan="8" class="text-center py-10 text-gray-400">Presione "Generar" para cargar el directorio</td>
            </tr>
            <tr v-for="r in datos" :key="r.nro" class="border-b hover:bg-gray-50">
              <td class="px-3 py-2 text-center text-gray-400">{{ r.nro }}</td>
              <td class="px-3 py-2 font-medium">{{ r.nombres }}</td>
              <td class="px-3 py-2 text-gray-600 text-xs">{{ r.direccion }}</td>
              <td class="px-3 py-2 text-gray-600 text-xs">{{ r.direccion_institucional }}</td>
              <td class="px-3 py-2 text-gray-600 text-xs">{{ r.ciudad }}</td>
              <td class="px-3 py-2 text-center text-gray-600">{{ r.telefono || '—' }}</td>
              <td class="px-3 py-2 text-center text-gray-600">{{ r.extension || '—' }}</td>
              <td class="px-3 py-2 text-gray-600 text-xs">{{ r.email || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Tabla Remuneraciones e Ingresos Adicionales -->
      <div v-if="tabActivo === 'remuneraciones'" class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-center px-3 py-3 text-gray-600 font-medium w-10">Nro</th>
              <th class="text-left px-3 py-3 text-gray-600 font-medium">Cargo / Denominación del Puesto</th>
              <th class="text-left px-3 py-3 text-gray-600 font-medium">Tipo Contrato</th>
              <th class="text-left px-3 py-3 text-gray-600 font-medium">Partida Individual</th>
              <th class="text-center px-3 py-3 text-gray-600 font-medium">Grado</th>
              <th class="text-right px-3 py-3 text-gray-600 font-medium">Salario Base</th>
              <th class="text-right px-3 py-3 text-gray-600 font-medium">Rem. Anual Unificada</th>
              <th class="text-right px-3 py-3 text-gray-600 font-medium">Décimo Tercero</th>
              <th class="text-right px-3 py-3 text-gray-600 font-medium">Décimo Cuarto</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="cargando">
              <td colspan="9" class="text-center py-10 text-gray-400">Cargando...</td>
            </tr>
            <tr v-else-if="datos.length === 0">
              <td colspan="9" class="text-center py-10 text-gray-400">Presione "Generar" para cargar el reporte</td>
            </tr>
            <tr v-for="r in datos" :key="r.nro" class="border-b hover:bg-gray-50">
              <td class="px-3 py-2 text-center text-gray-400">{{ r.nro }}</td>
              <td class="px-3 py-2 font-medium">{{ r.cargo }}</td>
              <td class="px-3 py-2 text-gray-600 text-xs">{{ r.tipo_contrato }}</td>
              <td class="px-3 py-2 text-gray-600 text-xs">{{ r.partida_individual || '—' }}</td>
              <td class="px-3 py-2 text-center text-gray-600">{{ r.grado || '—' }}</td>
              <td class="px-3 py-2 text-right font-mono text-gray-700">{{ r.salario_base?.toFixed(2) }}</td>
              <td class="px-3 py-2 text-right font-mono text-gray-700">{{ r.remuneracion_anual?.toFixed(2) }}</td>
              <td class="px-3 py-2 text-right text-gray-300">—</td>
              <td class="px-3 py-2 text-right text-gray-300">—</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import api from '@/services/api'

const tabActivo  = ref('directorio')
const datos      = ref([])
const cargando   = ref(false)
const exportando = ref(false)
const error      = ref('')

const tabs = [
  { id: 'directorio',     label: 'Directorio y Distributivo' },
  { id: 'remuneraciones', label: 'Remuneraciones e Ingresos Adicionales' },
]

const cambiarTab = (id) => {
  tabActivo.value = id
  datos.value     = []
  error.value     = ''
}

const cargar = async () => {
  cargando.value = true
  error.value    = ''
  datos.value    = []
  try {
    const { data } = await api.get(`/reportes/lotaip/${tabActivo.value}`)
    datos.value = data
  } catch {
    error.value = 'Error al cargar los datos'
  } finally {
    cargando.value = false
  }
}

const exportarExcel = async () => {
  exportando.value = true
  try {
    const resp = await api.get(`/reportes/lotaip/${tabActivo.value}`, {
      params: { formato: 'excel' },
      responseType: 'blob',
    })
    const blob = new Blob([resp.data], {
      type: resp.headers['content-type'] || 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    })
    const url = URL.createObjectURL(blob)
    const a   = document.createElement('a')
    a.href     = url
    a.download = `LOTAIP_${tabActivo.value}_${new Date().toISOString().substring(0, 10)}.xlsx`
    a.click()
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch {
    error.value = 'Error al exportar'
  } finally {
    exportando.value = false
  }
}
</script>
