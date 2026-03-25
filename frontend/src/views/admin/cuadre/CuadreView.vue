<template>
  <div class="space-y-6">

    <!-- Encabezado -->
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-800">Cuadre de Marcaciones</h1>
      <button @click="procesarCuadre" :disabled="procesando"
        class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm font-medium
               disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
        <span v-if="procesando">Procesando...</span>
        <span v-else>▶ Procesar Cuadre</span>
      </button>
    </div>

    <!-- Mensaje resultado del proceso -->
    <div v-if="mensajeProceso" :class="['rounded-lg px-4 py-3 text-sm font-medium',
      errorProceso ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200']">
      {{ mensajeProceso }}
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow p-4 flex flex-wrap gap-3 items-end">
      <div class="flex flex-col gap-1">
        <label class="text-xs text-gray-500 font-medium">Fecha</label>
        <input v-model="filtros.fecha" type="date" @change="cargar"
          class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
      </div>
      <div class="flex flex-col gap-1">
        <label class="text-xs text-gray-500 font-medium">Departamento</label>
        <select v-model="filtros.departamento_id" @change="cargar"
          class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
          <option value="">Todos</option>
          <option v-for="d in departamentos" :key="d.id_depto" :value="d.id_depto">
            {{ d.nombre_depto }}
          </option>
        </select>
      </div>
      <div class="flex flex-col gap-1">
        <label class="text-xs text-gray-500 font-medium">Buscar</label>
        <input v-model="filtros.buscar" type="text" placeholder="Nombre o cédula..."
          @keyup.enter="cargar"
          class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186] w-48" />
      </div>
      <button @click="cargar"
        class="bg-[#0b5447] text-white px-4 py-2 rounded-lg hover:bg-[#00372e] text-sm font-medium">
        Buscar
      </button>
      <button @click="limpiarFiltros"
        class="border rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
        Limpiar
      </button>
    </div>

    <!-- Resumen -->
    <div v-if="registros.length > 0" class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <div class="bg-white rounded-xl shadow p-4 text-center">
        <p class="text-2xl font-bold text-gray-800">{{ registros.length }}</p>
        <p class="text-xs text-gray-500 mt-1">Empleados</p>
      </div>
      <div class="bg-white rounded-xl shadow p-4 text-center">
        <p class="text-2xl font-bold text-red-600">{{ totalFaltas }}</p>
        <p class="text-xs text-gray-500 mt-1">Faltas</p>
      </div>
      <div class="bg-white rounded-xl shadow p-4 text-center">
        <p class="text-2xl font-bold text-amber-600">{{ totalConAtraso }}</p>
        <p class="text-xs text-gray-500 mt-1">Con atraso</p>
      </div>
      <div class="bg-white rounded-xl shadow p-4 text-center">
        <p class="text-2xl font-bold text-[#0b5447]">{{ totalHorasDecto }}</p>
        <p class="text-xs text-gray-500 mt-1">Horas a descontar</p>
      </div>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl shadow overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-left px-3 py-3 text-gray-600 font-medium whitespace-nowrap">Empleado</th>
              <th class="text-left px-3 py-3 text-gray-600 font-medium whitespace-nowrap">Cédula</th>
              <th class="text-left px-3 py-3 text-gray-600 font-medium whitespace-nowrap">Departamento</th>
              <th class="text-center px-3 py-3 text-gray-600 font-medium">Falta</th>
              <th class="text-center px-3 py-3 text-gray-600 font-medium whitespace-nowrap">Entrada</th>
              <th class="text-center px-3 py-3 text-gray-600 font-medium whitespace-nowrap">Atraso entrada</th>
              <th class="text-center px-3 py-3 text-gray-600 font-medium whitespace-nowrap">Atraso lunch</th>
              <th class="text-center px-3 py-3 text-gray-600 font-medium whitespace-nowrap">Salida anticipada</th>
              <th class="text-center px-3 py-3 text-gray-600 font-medium whitespace-nowrap">H. totales</th>
              <th class="text-center px-3 py-3 text-gray-600 font-medium whitespace-nowrap">H. a descontar</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="cargando">
              <td colspan="10" class="text-center py-10 text-gray-400">Cargando...</td>
            </tr>
            <tr v-else-if="registros.length === 0">
              <td colspan="10" class="text-center py-10 text-gray-400">
                No hay registros para esta fecha. Ejecute el proceso primero.
              </td>
            </tr>
            <tr v-for="r in registros" :key="r.id_emp"
              :class="['border-b hover:bg-gray-50', r.falta === 'S' ? 'bg-red-50' : '']">
              <td class="px-3 py-2 font-medium whitespace-nowrap">
                {{ r.apellido }}, {{ r.nombre }}
              </td>
              <td class="px-3 py-2 text-gray-500">{{ r.identificacion }}</td>
              <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ r.nombre_depto }}</td>
              <td class="px-3 py-2 text-center">
                <span :class="r.falta === 'S'
                  ? 'bg-red-100 text-red-700 px-2 py-0.5 rounded-full text-xs font-medium'
                  : 'bg-green-100 text-green-700 px-2 py-0.5 rounded-full text-xs font-medium'">
                  {{ r.falta === 'S' ? 'FALTA' : 'OK' }}
                </span>
              </td>
              <td class="px-3 py-2 text-center font-mono text-gray-700">
                {{ decimalAHora(r.hora_real_entrada) }}
                <span v-if="r.hora_turno_entrada" class="block text-xs text-gray-400">
                  prog: {{ decimalAHora(r.hora_turno_entrada) }}
                </span>
              </td>
              <td class="px-3 py-2 text-center">
                <span v-if="r.atraso_entrada > 0" class="text-amber-700 font-medium">
                  {{ minutosATexto(r.atraso_entrada) }}
                </span>
                <span v-else class="text-gray-300">—</span>
              </td>
              <td class="px-3 py-2 text-center">
                <span v-if="r.atraso_lunch > 0" class="text-amber-700 font-medium">
                  {{ minutosATexto(r.atraso_lunch) }}
                </span>
                <span v-else class="text-gray-300">—</span>
              </td>
              <td class="px-3 py-2 text-center">
                <span v-if="r.atraso_salida > 0" class="text-[#0b5447] font-medium">
                  {{ minutosATexto(r.atraso_salida) }}
                </span>
                <span v-else class="text-gray-300">—</span>
              </td>
              <td class="px-3 py-2 text-center font-mono text-gray-700">
                {{ r.horas_totales != null ? decimalAHora(r.horas_totales) : '—' }}
              </td>
              <td class="px-3 py-2 text-center">
                <span v-if="r.horas_decto > 0" class="text-red-700 font-medium">
                  {{ minutosATexto(Math.round(r.horas_decto * 60)) }}
                </span>
                <span v-else class="text-gray-300">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

const hoy = new Date().toISOString().slice(0, 10)

const filtros = ref({ fecha: hoy, departamento_id: '', buscar: '' })
const registros     = ref([])
const departamentos = ref([])
const cargando      = ref(false)
const procesando    = ref(false)
const mensajeProceso = ref('')
const errorProceso   = ref(false)

// ── Helpers ──────────────────────────────────────────────────────────────────
function decimalAHora(val) {
  if (val == null) return '—'
  const horas   = Math.floor(val)
  const minutos = Math.round((val - horas) * 60)
  return `${String(horas).padStart(2, '0')}:${String(minutos).padStart(2, '0')}`
}

function minutosATexto(min) {
  if (!min || min === 0) return '—'
  const h = Math.floor(min / 60)
  const m = min % 60
  if (h > 0 && m > 0) return `${h}h ${m}min`
  if (h > 0)           return `${h}h`
  return `${m}min`
}

// ── Resumen ───────────────────────────────────────────────────────────────────
const totalFaltas     = computed(() => registros.value.filter(r => r.falta === 'S').length)
const totalConAtraso  = computed(() => registros.value.filter(r => r.atraso_entrada > 0 || r.atraso_lunch > 0 || r.atraso_salida > 0).length)
const totalHorasDecto = computed(() => {
  const totalMin = registros.value.reduce((acc, r) => acc + Math.round(Number(r.horas_decto || 0) * 60), 0)
  return minutosATexto(totalMin) || '0min'
})

// ── Cargar datos ──────────────────────────────────────────────────────────────
async function cargar() {
  cargando.value = true
  try {
    const params = {}
    if (filtros.value.fecha)          params.fecha          = filtros.value.fecha
    if (filtros.value.departamento_id) params.departamento_id = filtros.value.departamento_id
    if (filtros.value.buscar)         params.buscar         = filtros.value.buscar

    const { data } = await api.get('/cuadre/listado', { params })
    registros.value = data
  } catch (e) {
    registros.value = []
  } finally {
    cargando.value = false
  }
}

async function cargarDepartamentos() {
  try {
    const { data } = await api.get('/departamentos')
    departamentos.value = data
  } catch {}
}

function limpiarFiltros() {
  filtros.value = { fecha: hoy, departamento_id: '', buscar: '' }
  mensajeProceso.value = ''
  cargar()
}

// ── Procesar cuadre ───────────────────────────────────────────────────────────
async function procesarCuadre() {
  procesando.value    = true
  mensajeProceso.value = ''
  errorProceso.value   = false
  try {
    const { data } = await api.post('/cuadre/procesar', { fecha: filtros.value.fecha })
    mensajeProceso.value = data.message + (data.detalle ? ' — ' + data.detalle : '')
    await cargar()
  } catch (e) {
    errorProceso.value   = true
    mensajeProceso.value = e.response?.data?.message ?? 'Error al procesar el cuadre.'
  } finally {
    procesando.value = false
  }
}

onMounted(() => {
  cargarDepartamentos()
  cargar()
})
</script>
