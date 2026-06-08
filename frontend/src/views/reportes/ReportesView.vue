<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Reportes</h1>

    <!-- Tabs -->
    <div class="flex border-b overflow-x-auto">
      <button
        v-for="tab in tabs" :key="tab.id"
        @click="cambiarTab(tab.id)"
        :class="tabActivo === tab.id
          ? 'border-b-2 border-[#0b5447] text-[#0b5447] font-medium'
          : 'text-gray-500 hover:text-gray-700'"
        class="px-5 py-3 text-sm transition whitespace-nowrap">
        {{ tab.label }}
      </button>
    </div>

    <!-- Filtros comunes -->
    <div class="bg-white rounded-xl shadow p-4 flex flex-wrap gap-3 items-end">
      <div>
        <label class="block text-xs text-gray-500 mb-1">Fecha Desde</label>
        <input v-model="filtros.fecha_desde" type="date"
          class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">Fecha Hasta</label>
        <input v-model="filtros.fecha_hasta" type="date"
          class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
      </div>
      <template v-if="tabActivo !== 'sin-atrasos'">
        <div>
          <label class="block text-xs text-gray-500 mb-1">Departamento</label>
          <select v-model="filtros.id_depto"
            class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186] min-w-48">
            <option value="">Todos los departamentos</option>
            <option v-for="d in departamentos" :key="d.id_depto" :value="d.id_depto">
              {{ d.nombre_depto }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Empleado</label>
          <input v-model="filtros.id_emp" type="text" placeholder="Nombre, apellido o cédula..."
            class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186] w-56" />
        </div>
        <!-- Filtro por tipo solo en movimientos -->
        <div v-if="tabActivo === 'movimientos'">
          <label class="block text-xs text-gray-500 mb-1">Tipos de movimiento</label>
          <div class="flex gap-2 flex-wrap">
            <label v-for="t in tiposMovimiento" :key="t.value"
              class="flex items-center gap-1.5 text-xs cursor-pointer border rounded px-2 py-1.5"
              :class="filtros.tipos.includes(t.value) ? 'border-[#0b5447] bg-[#f0faf8]' : 'border-gray-200'">
              <input type="checkbox" :value="t.value" v-model="filtros.tipos" class="accent-[#0b5447]" />
              <span :class="t.clase">{{ t.label }}</span>
            </label>
          </div>
        </div>
      </template>
      <button @click="buscar" :disabled="cargando"
        class="bg-[#0b5447] text-white px-5 py-2 rounded-lg text-sm hover:bg-[#00372e] disabled:opacity-50 font-medium">
        {{ cargando ? "Buscando..." : "Buscar" }}
      </button>
      <button @click="limpiar"
        class="border rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
        Limpiar
      </button>
      <!-- Botones exportar (visibles solo si hay datos) -->
      <template v-if="datos.length > 0 && tabActivo !== 'sin-atrasos'">
        <button @click="exportar('excel')" :disabled="exportando"
          class="flex items-center gap-1.5 border border-green-600 text-green-700 px-4 py-2 rounded-lg text-sm hover:bg-green-50 disabled:opacity-50">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
          </svg>
          {{ exportando ? 'Generando...' : 'Excel' }}
        </button>
        <button @click="exportar('pdf')" :disabled="exportando"
          class="flex items-center gap-1.5 border border-red-400 text-red-600 px-4 py-2 rounded-lg text-sm hover:bg-red-50 disabled:opacity-50">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
          </svg>
          {{ exportando ? 'Generando...' : 'PDF' }}
        </button>
      </template>
    </div>

    <!-- Reporte Atrasos -->
    <div v-if="tabActivo === 'atrasos'" class="bg-white rounded-xl shadow overflow-hidden">
      <div class="px-6 py-4 border-b flex items-center justify-between">
        <h2 class="font-semibold text-gray-700">Reporte de Atrasos</h2>
        <span class="text-sm text-gray-400">{{ datos.length }} registros</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Fecha</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Empleado</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Departamento</th>
              <th class="text-center px-4 py-3 text-gray-600 font-medium">H. Prog.</th>
              <th class="text-center px-4 py-3 text-gray-600 font-medium">H. Real</th>
              <th class="text-center px-4 py-3 text-amber-600 font-medium">Atr. Entrada</th>
              <th class="text-center px-4 py-3 text-amber-600 font-medium">Atr. Lunch</th>
              <th class="text-center px-4 py-3 text-[#0b5447] font-medium">Sal. Anticipada</th>
              <th class="text-center px-4 py-3 text-red-600 font-medium">H. a Descontar</th>
              <th class="text-center px-4 py-3 text-gray-600 font-medium">Justificación</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="cargando">
              <td colspan="10" class="text-center py-10 text-gray-400">Cargando...</td>
            </tr>
            <tr v-else-if="datos.length === 0">
              <td colspan="10" class="text-center py-10 text-gray-400">Sin resultados para el período seleccionado</td>
            </tr>
            <tr v-for="(r, i) in datos" :key="i" class="border-b hover:bg-gray-50">
              <td class="px-4 py-3 font-mono text-xs whitespace-nowrap">{{ r.fecha?.substring(0, 10) }}</td>
              <td class="px-4 py-3 font-medium">{{ r.nombre_completo }}</td>
              <td class="px-4 py-3 text-gray-500 text-xs">{{ r.nombre_depto }}</td>
              <td class="px-4 py-3 text-center font-mono text-xs text-gray-400">{{ decimalAHora(r.hora_turno_entrada) }}</td>
              <td class="px-4 py-3 text-center font-mono text-xs">{{ decimalAHora(r.hora_real_entrada) }}</td>
              <td class="px-4 py-3 text-center">
                <span v-if="r.atraso_entrada > 0" class="text-amber-700 font-medium">{{ minATexto(r.atraso_entrada) }}</span>
                <span v-else class="text-gray-300">—</span>
              </td>
              <td class="px-4 py-3 text-center">
                <span v-if="r.atraso_lunch > 0" class="text-amber-700 font-medium">{{ minATexto(r.atraso_lunch) }}</span>
                <span v-else class="text-gray-300">—</span>
              </td>
              <td class="px-4 py-3 text-center">
                <span v-if="r.atraso_salida > 0" class="text-[#0b5447] font-medium">{{ minATexto(r.atraso_salida) }}</span>
                <span v-else class="text-gray-300">—</span>
              </td>
              <td class="px-4 py-3 text-center">
                <span v-if="r.horas_decto > 0" class="text-red-700 font-medium">{{ minATexto(Math.round(r.horas_decto * 60)) }}</span>
                <span v-else class="text-gray-300">—</span>
              </td>
              <td class="px-4 py-3 text-center">
                <span v-if="r.justificacion === 'PARCIAL'"
                  class="bg-orange-100 text-orange-700 px-2 py-0.5 rounded text-xs font-medium"
                  :title="'Pendiente: ' + minATexto(r.minutos_pendientes)">
                  Parcial ({{ minATexto(r.minutos_pendientes) }} pend.)
                </span>
                <span v-else class="bg-red-100 text-red-700 px-2 py-0.5 rounded text-xs font-medium">
                  Sin justificar
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Reporte Sin Atrasos -->
    <div v-if="tabActivo === 'sin-atrasos'" class="bg-white rounded-xl shadow overflow-hidden">
      <div class="px-6 py-4 border-b flex items-center justify-between">
        <h2 class="font-semibold text-gray-700">Personal Sin Atrasos</h2>
        <span class="text-sm text-gray-400">{{ datos.length }} empleado(s)</span>
      </div>
      <div v-if="datos.length === 0 && !cargando" class="text-center py-12 text-gray-400 text-sm">
        Sin resultados para el período seleccionado
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
            <tr v-if="cargando">
              <td colspan="4" class="text-center py-10 text-gray-400">Cargando...</td>
            </tr>
            <template v-else v-for="(grupo, depto) in agrupados" :key="depto">
              <tr class="bg-[#0b5447]/5 border-t">
                <td colspan="4" class="px-4 py-2 text-xs font-bold text-[#0b5447] uppercase tracking-wide">
                  {{ depto }} ({{ grupo.length }})
                </td>
              </tr>
              <tr v-for="(emp, idx) in grupo" :key="emp.id_emp" class="border-t hover:bg-gray-50">
                <td class="px-4 py-2 text-gray-400 text-xs">{{ idx + 1 }}</td>
                <td class="px-4 py-2 text-gray-500 text-xs">{{ emp.nombre_depto }}</td>
                <td class="px-4 py-2 font-medium">{{ emp.apellido_emp }} {{ emp.nombre_emp }}</td>
                <td class="px-4 py-2 text-gray-600 text-xs">{{ emp.cargo_empleado || '—' }}</td>
              </tr>
            </template>
          </tbody>
        </table>
        <div v-if="datos.length > 0" class="border-t bg-gray-50 px-4 py-3 text-xs text-gray-500">
          Total: <strong class="text-gray-700">{{ datos.length }} empleados</strong> sin atrasos
          del {{ filtros.fecha_desde }} al {{ filtros.fecha_hasta }}
        </div>
      </template>
    </div>

    <!-- Reporte Marcaciones Faltantes -->
    <div v-if="tabActivo === 'faltantes'" class="bg-white rounded-xl shadow overflow-hidden">
      <div class="px-6 py-4 border-b flex items-center justify-between">
        <h2 class="font-semibold text-gray-700">Marcaciones No Realizadas</h2>
        <span class="text-sm text-gray-400">{{ datos.length }} registros</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Fecha</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Empleado</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Departamento</th>
              <th class="text-center px-4 py-3 text-gray-600 font-medium">Entrada</th>
              <th class="text-center px-4 py-3 text-gray-600 font-medium">Sal. Lunch</th>
              <th class="text-center px-4 py-3 text-gray-600 font-medium">Ent. Lunch</th>
              <th class="text-center px-4 py-3 text-gray-600 font-medium">Salida</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="cargando">
              <td colspan="7" class="text-center py-10 text-gray-400">Cargando...</td>
            </tr>
            <tr v-else-if="datos.length === 0">
              <td colspan="7" class="text-center py-10 text-gray-400">Sin resultados para el período seleccionado</td>
            </tr>
            <tr v-for="(r, i) in datos" :key="i" class="border-b hover:bg-gray-50">
              <td class="px-4 py-3 font-mono text-xs whitespace-nowrap">{{ r.fecha?.substring(0, 10) }}</td>
              <td class="px-4 py-3 font-medium">{{ r.nombre_completo }}</td>
              <td class="px-4 py-3 text-gray-500 text-xs">{{ r.nombre_depto }}</td>
              <td class="px-4 py-3 text-center">
                <span v-if="r.tiene_entrada > 0" class="text-green-600">✓</span>
                <span v-else class="bg-red-100 text-red-700 px-2 py-0.5 rounded text-xs font-medium">No registró</span>
              </td>
              <td class="px-4 py-3 text-center">
                <span v-if="r.tiene_sal_lunch > 0" class="text-green-600">✓</span>
                <span v-else class="bg-red-100 text-red-700 px-2 py-0.5 rounded text-xs font-medium">No registró</span>
              </td>
              <td class="px-4 py-3 text-center">
                <span v-if="r.tiene_ent_lunch > 0" class="text-green-600">✓</span>
                <span v-else class="bg-red-100 text-red-700 px-2 py-0.5 rounded text-xs font-medium">No registró</span>
              </td>
              <td class="px-4 py-3 text-center">
                <span v-if="r.tiene_salida > 0" class="text-green-600">✓</span>
                <span v-else class="bg-red-100 text-red-700 px-2 py-0.5 rounded text-xs font-medium">No registró</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Reporte Movimientos de Personal -->
    <div v-if="tabActivo === 'movimientos'" class="bg-white rounded-xl shadow overflow-hidden">
      <div class="px-6 py-4 border-b flex items-center justify-between">
        <h2 class="font-semibold text-gray-700">Movimientos de Personal</h2>
        <div class="flex items-center gap-4">
          <!-- Resumen por tipo -->
          <div class="flex gap-2" v-if="datos.length > 0">
            <span v-for="t in tiposMovimiento" :key="t.value"
              v-if="contarTipo(t.value) > 0"
              :class="t.badgeClase"
              class="px-2 py-0.5 rounded-full text-xs font-medium">
              {{ t.label }}: {{ contarTipo(t.value) }}
            </span>
          </div>
          <span class="text-sm text-gray-400">{{ datos.length }} registros</span>
        </div>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-left px-4 py-3 text-gray-600 font-medium w-24">Tipo</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Empleado</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Cargo</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Departamento</th>
              <th class="text-center px-4 py-3 text-gray-600 font-medium">Fecha Desde</th>
              <th class="text-center px-4 py-3 text-gray-600 font-medium">Fecha Hasta</th>
              <th class="text-center px-4 py-3 text-gray-600 font-medium">Días</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Detalle</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="cargando">
              <td colspan="8" class="text-center py-10 text-gray-400">Cargando...</td>
            </tr>
            <tr v-else-if="datos.length === 0">
              <td colspan="8" class="text-center py-10 text-gray-400">Sin resultados. Seleccione un período y presione Buscar.</td>
            </tr>
            <tr v-for="(r, i) in datos" :key="i" class="border-b hover:bg-gray-50">
              <td class="px-4 py-3">
                <span :class="badgeMovimiento(r.tipo)"
                  class="px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap">
                  {{ r.tipo }}
                </span>
              </td>
              <td class="px-4 py-3 font-medium">{{ r.nombre_completo }}</td>
              <td class="px-4 py-3 text-gray-500 text-xs">{{ r.cargo_empleado || '—' }}</td>
              <td class="px-4 py-3 text-gray-500 text-xs">{{ r.nombre_depto }}</td>
              <td class="px-4 py-3 text-center text-xs font-mono">{{ r.fecha_desde }}</td>
              <td class="px-4 py-3 text-center text-xs font-mono">{{ r.fecha_hasta }}</td>
              <td class="px-4 py-3 text-center">
                <span v-if="r.dias" class="font-medium">{{ r.dias }}</span>
                <span v-else class="text-gray-300">—</span>
              </td>
              <td class="px-4 py-3 text-gray-600 text-xs">{{ r.detalle }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue"
import api from "@/services/api"

const tabActivo    = ref("atrasos")
const datos        = ref([])
const cargando     = ref(false)
const exportando   = ref(false)
const departamentos = ref([])

const tabs = [
  { id: "atrasos",      label: "Atrasos" },
  { id: "faltantes",    label: "Marcaciones No Realizadas" },
  { id: "sin-atrasos",  label: "Sin Atrasos" },
  { id: "movimientos",  label: "Movimientos de Personal" },
]

const tiposMovimiento = [
  { value: "VACACIONES", label: "Vacaciones", clase: "text-emerald-700 font-medium", badgeClase: "bg-emerald-100 text-emerald-700" },
  { value: "PERMISO",    label: "Permisos",   clase: "text-yellow-700 font-medium",  badgeClase: "bg-yellow-100 text-yellow-700" },
  { value: "LICENCIA",   label: "Licencias",  clase: "text-indigo-700 font-medium",  badgeClase: "bg-indigo-100 text-indigo-700" },
  { value: "COMISIÓN",   label: "Comisiones", clase: "text-pink-700 font-medium",    badgeClase: "bg-pink-100 text-pink-700" },
]

const hoy = new Date().toISOString().substring(0, 10)
const primerDiaMes = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().substring(0, 10)

const filtros = ref({
  fecha_desde: primerDiaMes,
  fecha_hasta: hoy,
  id_depto: "",
  id_emp: "",
  tipos: ["VACACIONES","PERMISO","LICENCIA","COMISIÓN"],
})

const decimalAHora = (val) => {
  if (!val && val !== 0) return "—"
  const h = Math.floor(val)
  const m = Math.round((val - h) * 60)
  return `${String(h).padStart(2, "0")}:${String(m).padStart(2, "0")}`
}

const minATexto = (min) => {
  if (!min || min <= 0) return "—"
  const h = Math.floor(min / 60)
  const m = min % 60
  if (h > 0 && m > 0) return `${h}h ${m}min`
  if (h > 0) return `${h}h`
  return `${m}min`
}

const agrupados = computed(() => {
  if (tabActivo.value !== 'sin-atrasos') return {}
  const grupos = {}
  for (const emp of datos.value) {
    const d = emp.nombre_depto || 'SIN DEPARTAMENTO'
    if (!grupos[d]) grupos[d] = []
    grupos[d].push(emp)
  }
  return grupos
})

const badgeMovimiento = (tipo) => {
  const clases = {
    "VACACIONES": "bg-emerald-100 text-emerald-700",
    "PERMISO":    "bg-yellow-100 text-yellow-700",
    "LICENCIA":   "bg-indigo-100 text-indigo-700",
    "COMISIÓN":   "bg-pink-100 text-pink-700",
  }
  return clases[tipo] || "bg-gray-100 text-gray-700"
}

const contarTipo = (tipo) => datos.value.filter(r => r.tipo === tipo).length

const buildParams = () => {
  const params = {
    fecha_desde: filtros.value.fecha_desde,
    fecha_hasta: filtros.value.fecha_hasta,
  }
  if (filtros.value.id_depto) params.id_depto = filtros.value.id_depto
  if (filtros.value.id_emp)   params.id_emp   = filtros.value.id_emp
  if (tabActivo.value === 'movimientos') params.tipos = filtros.value.tipos.join(',')
  return params
}

const endpointActivo = () => {
  if (tabActivo.value === "atrasos")     return "/reportes/atrasos"
  if (tabActivo.value === "faltantes")   return "/reportes/marcaciones-faltantes"
  if (tabActivo.value === "movimientos") return "/reportes/movimientos-personal"
  return "/asistencia/reporte-sin-atrasos"
}

const buscar = async () => {
  if (!filtros.value.fecha_desde || !filtros.value.fecha_hasta) return
  cargando.value = true
  datos.value = []
  try {
    const { data } = await api.get(endpointActivo(), { params: buildParams() })
    datos.value = data
  } catch (e) {
    console.error(e)
  } finally {
    cargando.value = false
  }
}

const exportar = async (formato) => {
  if (!filtros.value.fecha_desde || !filtros.value.fecha_hasta) return
  exportando.value = true
  try {
    const params = { ...buildParams(), formato }
    const resp = await api.get(endpointActivo(), { params, responseType: 'blob' })
    const ext  = formato === 'excel' ? 'xlsx' : 'pdf'
    const mime = formato === 'excel'
      ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
      : 'application/pdf'
    const blob = new Blob([resp.data], { type: mime })
    const url  = URL.createObjectURL(blob)
    const a    = document.createElement('a')
    a.href     = url
    a.download = `reporte_${tabActivo.value}_${filtros.value.fecha_desde}_${filtros.value.fecha_hasta}.${ext}`
    a.click()
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch (e) {
    alert("Error al generar el archivo")
    console.error(e)
  } finally {
    exportando.value = false
  }
}

const cambiarTab = (id) => {
  tabActivo.value = id
  datos.value     = []
  filtros.value   = {
    fecha_desde: primerDiaMes,
    fecha_hasta: hoy,
    id_depto: "",
    id_emp: "",
    tipos: ["VACACIONES","PERMISO","LICENCIA","COMISIÓN"],
  }
}

const limpiar = () => {
  filtros.value = {
    fecha_desde: primerDiaMes,
    fecha_hasta: hoy,
    id_depto: "",
    id_emp: "",
    tipos: ["VACACIONES","PERMISO","LICENCIA","COMISIÓN"],
  }
  datos.value = []
}

onMounted(async () => {
  try {
    const { data } = await api.get("/departamentos")
    departamentos.value = data
  } catch (e) {}
})
</script>
