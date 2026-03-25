<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Reportes</h1>

    <!-- Tabs -->
    <div class="flex border-b">
      <button
        v-for="tab in tabs" :key="tab.id"
        @click="tabActivo = tab.id"
        :class="tabActivo === tab.id
          ? 'border-b-2 border-blue-600 text-[#0b5447] font-medium'
          : 'text-gray-500 hover:text-gray-700'"
        class="px-6 py-3 text-sm transition">
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
        <label class="block text-xs text-gray-500 mb-1">Empleado (cédula)</label>
        <input v-model="filtros.id_emp" type="text" placeholder="Opcional..."
          class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186] w-36" />
      </div>
      <button @click="buscar" :disabled="cargando"
        class="bg-[#0b5447] text-white px-5 py-2 rounded-lg text-sm hover:bg-[#00372e] disabled:opacity-50 font-medium">
        {{ cargando ? "Buscando..." : "Buscar" }}
      </button>
      <button @click="limpiar"
        class="border rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
        Limpiar
      </button>
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

  </div>
</template>

<script setup>
import { ref, onMounted } from "vue"
import api from "@/services/api"

const tabActivo    = ref("atrasos")
const datos        = ref([])
const cargando     = ref(false)
const departamentos = ref([])

const tabs = [
  { id: "atrasos",   label: "Atrasos" },
  { id: "faltantes", label: "Marcaciones No Realizadas" },
]

const hoy = new Date().toISOString().substring(0, 10)
const primerDiaMes = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().substring(0, 10)

const filtros = ref({
  fecha_desde: primerDiaMes,
  fecha_hasta: hoy,
  id_depto: "",
  id_emp: "",
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

const buscar = async () => {
  if (!filtros.value.fecha_desde || !filtros.value.fecha_hasta) return
  cargando.value = true
  datos.value = []
  try {
    const endpoint = tabActivo.value === "atrasos"
      ? "/reportes/atrasos"
      : "/reportes/marcaciones-faltantes"
    const params = { ...filtros.value }
    if (!params.id_depto) delete params.id_depto
    if (!params.id_emp)   delete params.id_emp
    const { data } = await api.get(endpoint, { params })
    datos.value = data
  } catch (e) {
    console.error(e)
  } finally {
    cargando.value = false
  }
}

const limpiar = () => {
  filtros.value = { fecha_desde: primerDiaMes, fecha_hasta: hoy, id_depto: "", id_emp: "" }
  datos.value = []
}

onMounted(async () => {
  try {
    const { data } = await api.get("/departamentos")
    departamentos.value = data
  } catch (e) {}
})
</script>
