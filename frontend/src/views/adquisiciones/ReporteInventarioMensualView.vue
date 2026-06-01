<template>
  <div class="space-y-5 max-w-6xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-800">Reporte de Inventario Mensual</h1>

    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow p-5">
      <div class="flex flex-wrap gap-4 items-end">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Mes</label>
          <select v-model="filtros.mes"
            class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#4a5e3a]">
            <option v-for="(nombre, idx) in meses" :key="idx" :value="idx">{{ nombre }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Año</label>
          <input v-model.number="filtros.anio" type="number" min="2020" max="2099"
            class="border rounded-lg px-3 py-2 text-sm w-24 focus:outline-none focus:ring-2 focus:ring-[#4a5e3a]" />
        </div>
        <button @click="generar" :disabled="cargando"
          class="px-5 py-2 bg-[#4a5e3a] text-white rounded-lg text-sm font-medium hover:bg-[#3b4a2e] disabled:opacity-50 transition-colors">
          {{ cargando ? 'Cargando...' : 'Generar' }}
        </button>
        <button v-if="filas.length" @click="descargarPdf" :disabled="descargando"
          class="px-5 py-2 border border-[#4a5e3a] text-[#4a5e3a] rounded-lg text-sm font-medium hover:bg-[#4a5e3a] hover:text-white disabled:opacity-50 transition-colors">
          {{ descargando ? 'Generando PDF...' : '↓ Descargar PDF' }}
        </button>
        <span v-if="generado" class="ml-auto text-sm text-gray-500">
          {{ filas.length }} grupo(s) con movimiento
        </span>
      </div>
      <div v-if="errorMsg" class="mt-3 text-sm text-red-600">{{ errorMsg }}</div>
    </div>

    <!-- Tabla -->
    <div v-if="generado" class="bg-white rounded-xl shadow overflow-hidden">
      <div v-if="filas.length === 0" class="text-center py-12 text-gray-400 text-sm">
        No hay movimientos de inventario en el período seleccionado.
      </div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-xs uppercase text-white" style="background-color:#4a5e3a;">
              <th class="px-3 py-3 text-center">Cuenta</th>
              <th class="px-3 py-3 text-left">Descripción Inventarios</th>
              <th class="px-3 py-3 text-right">Saldo Mes Anterior</th>
              <th class="px-3 py-3 text-right">Ingreso Mes Procesos</th>
              <th class="px-3 py-3 text-right">Ingreso Mes Caja Chica</th>
              <th class="px-3 py-3 text-right">Egreso Mes</th>
              <th class="px-3 py-3 text-right">Saldo Final Mes</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(r, i) in filas" :key="r.nivel2"
              :class="i % 2 === 0 ? 'bg-white' : 'bg-[#f7f9f4]'"
              class="border-b hover:bg-green-50 transition-colors">
              <td class="px-3 py-2 text-center font-bold text-gray-700">{{ r.cuenta }}</td>
              <td class="px-3 py-2">{{ r.descripcion }}</td>
              <td class="px-3 py-2 text-right font-mono">{{ fmt(r.saldo_anterior) }}</td>
              <td class="px-3 py-2 text-right font-mono">{{ fmt(r.ingreso_procesos) }}</td>
              <td class="px-3 py-2 text-right font-mono">{{ fmt(r.ingreso_caja_chica) }}</td>
              <td class="px-3 py-2 text-right font-mono">{{ fmt(r.egreso_mes) }}</td>
              <td class="px-3 py-2 text-right font-mono font-semibold">{{ fmt(r.saldo_final) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="text-sm font-bold" style="background-color:#e0e8d8; border-top:2px solid #4a5e3a;">
              <td colspan="2" class="px-3 py-2 text-right">TOTAL</td>
              <td class="px-3 py-2 text-right font-mono">{{ fmt(total.saldo_anterior) }}</td>
              <td class="px-3 py-2 text-right font-mono">{{ fmt(total.ingreso_procesos) }}</td>
              <td class="px-3 py-2 text-right font-mono">{{ fmt(total.ingreso_caja_chica) }}</td>
              <td class="px-3 py-2 text-right font-mono">{{ fmt(total.egreso_mes) }}</td>
              <td class="px-3 py-2 text-right font-mono">{{ fmt(total.saldo_final) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import api from '@/services/api'

const hoy = new Date()
const meses = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
               'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']

const filtros    = ref({ mes: hoy.getMonth() || 12, anio: hoy.getMonth() ? hoy.getFullYear() : hoy.getFullYear() - 1 })
const filas      = ref([])
const cargando   = ref(false)
const descargando= ref(false)
const generado   = ref(false)
const errorMsg   = ref('')

const total = computed(() => ({
  saldo_anterior:     filas.value.reduce((s, r) => s + r.saldo_anterior,     0),
  ingreso_procesos:   filas.value.reduce((s, r) => s + r.ingreso_procesos,   0),
  ingreso_caja_chica: filas.value.reduce((s, r) => s + r.ingreso_caja_chica, 0),
  egreso_mes:         filas.value.reduce((s, r) => s + r.egreso_mes,         0),
  saldo_final:        filas.value.reduce((s, r) => s + r.saldo_final,        0),
}))

function fmt(val) {
  return new Intl.NumberFormat('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val ?? 0)
}

function periodoParams() {
  const mes  = filtros.value.mes
  const anio = filtros.value.anio
  const ultimoDia = new Date(anio, mes, 0).getDate()
  const mm = String(mes).padStart(2, '0')
  return {
    desde: `${anio}-${mm}-01`,
    hasta: `${anio}-${mm}-${String(ultimoDia).padStart(2, '0')}`,
  }
}

async function generar() {
  if (!filtros.value.mes || !filtros.value.anio) { errorMsg.value = 'Seleccione mes y año.'; return }
  cargando.value  = true
  errorMsg.value  = ''
  generado.value  = false
  try {
    const { data } = await api.get('/adquisiciones/reportes/inventario-mensual', { params: periodoParams() })
    filas.value   = data
    generado.value = true
  } catch (e) {
    errorMsg.value = e.response?.data?.message || 'Error al generar el reporte.'
  } finally {
    cargando.value = false
  }
}

async function descargarPdf() {
  descargando.value = true
  try {
    const resp = await api.get('/adquisiciones/reportes/inventario-mensual', {
      params: { ...periodoParams(), formato: 'pdf' },
      responseType: 'blob',
    })
    const blob = new Blob([resp.data], { type: 'application/pdf' })
    const url  = URL.createObjectURL(blob)
    window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch {
    errorMsg.value = 'Error al generar el PDF.'
  } finally {
    descargando.value = false
  }
}
</script>
