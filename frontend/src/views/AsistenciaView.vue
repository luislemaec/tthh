<template>
  <div class="space-y-6">

    <!-- Tarjeta de timbrada del empleado -->
    <div class="bg-white rounded-xl shadow p-6">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">Control de Asistencia</h1>
          <p class="text-gray-500 text-sm mt-1">{{ fechaHoy }} | {{ horaActual }}</p>
        </div>
        <div class="text-right">
          <p class="text-sm font-medium text-gray-700">{{ estado.empleado?.apellido }} {{ estado.empleado?.nombre }}</p>
          <p class="text-xs text-gray-500">{{ estado.empleado?.departamento }}</p>
        </div>
      </div>

      <!-- Botones de marcacion -->
      <div class="grid grid-cols-2 gap-4 mb-6">
        <button
          v-for="btn in botones" :key="btn.concepto"
          @click="marcar(btn.concepto)"
          :disabled="!btn.disponible || marcando"
          :style="btnStyle(btn.estadoBtn)"
          :class="[
            btn.disponible ? 'cursor-pointer hover:opacity-90' : 'cursor-not-allowed opacity-80',
            'w-full py-6 rounded-xl text-sm font-semibold transition flex flex-col items-center gap-2'
          ]">
          <img :src="btn.icono" class="w-28 h-28 object-contain" />
          <span>{{ btn.label }}</span>
          <span v-if="getMarcacion(btn.concepto)" class="text-xs font-normal opacity-70">
            {{ formatHora(getMarcacion(btn.concepto)?.fecha_hora) }}
          </span>
          <span v-else-if="btn.disponible" class="text-xs font-normal opacity-80">
            {{ marcando && estado.value.siguiente === btn.concepto ? "Registrando..." : "Pendiente" }}
          </span>
        </button>
      </div>

      <!-- Mensaje de exito -->
      <div v-if="mensajeExito"
        class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm text-center">
        {{ mensajeExito }}
      </div>

      <!-- Mensaje de error -->
      <div v-if="mensajeError"
        class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm text-center">
        {{ mensajeError }}
      </div>
    </div>

    <!-- Historial personal de marcaciones -->
    <div class="bg-white rounded-xl shadow p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold text-gray-800">Mis Marcaciones</h2>
        <div class="flex flex-wrap gap-2 items-center">
          <input v-model="histFechaDesde" type="date" @change="cargarHistorial"
            class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          <input v-model="histFechaHasta" type="date" @change="cargarHistorial"
            class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          <select v-model="histTipo" @change="cargarHistorial"
            class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
            <option value="todos">Todos</option>
            <option value="justificados">Atrasos justificados</option>
            <option value="injustificados">Atrasos injustificados</option>
          </select>
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-left px-3 py-3 text-gray-600 font-medium">Fecha</th>
              <th class="text-left px-3 py-3 text-gray-600 font-medium">Concepto</th>
              <th class="text-center px-3 py-3 text-gray-600 font-medium">Hora</th>
              <th class="text-center px-3 py-3 text-gray-600 font-medium">Atraso</th>
              <th class="text-center px-3 py-3 text-gray-600 font-medium">Justificación</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="cargandoHistorial">
              <td colspan="4" class="text-center py-8 text-gray-400">Cargando...</td>
            </tr>
            <tr v-else-if="historial.length === 0">
              <td colspan="4" class="text-center py-8 text-gray-400">No hay registros en este período</td>
            </tr>
            <tr v-for="(r, i) in historial" :key="i"
              class="border-b hover:bg-gray-50">
              <td class="px-3 py-2 font-medium whitespace-nowrap">{{ r.fecha }}</td>
              <td class="px-3 py-2">
                <span :class="colorConcepto(r.concepto)"
                  class="px-2 py-0.5 rounded-full text-xs font-medium">
                  {{ r.concepto }}
                </span>
              </td>
              <td class="px-3 py-2 text-center font-mono text-gray-700">{{ r.hora }}</td>
              <td class="px-3 py-2 text-center">
                <span v-if="r.atraso > 0" class="text-red-700 font-medium">
                  {{ minATexto(r.atraso) }}
                </span>
                <span v-else class="text-gray-300">—</span>
              </td>
              <td class="px-3 py-2 text-center">
                <span v-if="r.justificado === 'TOTAL'" class="bg-green-100 text-green-700 px-2 py-0.5 rounded text-xs">Justificado</span>
                <span v-else-if="r.justificado === 'PARCIAL'" class="bg-orange-100 text-orange-700 px-2 py-0.5 rounded text-xs">Parcial</span>
                <span v-else-if="r.justificado === 'NO'" class="bg-red-100 text-red-700 px-2 py-0.5 rounded text-xs">Sin justificar</span>
                <span v-else class="text-gray-300">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Panel administrador: listado del dia -->
    <div v-if="esAdmin" class="bg-white rounded-xl shadow p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold text-gray-800">Marcaciones del Dia</h2>
        <div class="flex gap-2">
          <input v-model="filtroFecha" type="date"
            @change="cargarListado"
            class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          <input v-model="filtroBuscar" type="text" placeholder="Buscar empleado..."
            @input="cargarListado"
            class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
        </div>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Empleado</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Departamento</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Concepto</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Hora</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Tipo</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">IP</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="cargandoListado">
              <td colspan="6" class="text-center py-8 text-gray-400">Cargando...</td>
            </tr>
            <tr v-else-if="listado.length === 0">
              <td colspan="6" class="text-center py-8 text-gray-400">No hay marcaciones para esta fecha</td>
            </tr>
            <tr v-for="m in listado" :key="m.secuencial" class="border-b hover:bg-gray-50">
              <td class="px-4 py-3 font-medium">
                {{ m.empleado?.apellido_emp }} {{ m.empleado?.nombre_emp }}
              </td>
              <td class="px-4 py-3 text-gray-500">{{ m.empleado?.departamento?.nombre_depto }}</td>
              <td class="px-4 py-3">
                <span :class="colorConcepto(m.concepto)"
                  class="px-2 py-1 rounded-full text-xs font-medium">
                  {{ m.concepto }}
                </span>
              </td>
              <td class="px-4 py-3 font-mono text-xs">{{ formatHora(m.fecha_hora) }}</td>
              <td class="px-4 py-3">
                <span :class="m.tipo_marcacion === 'WEB' ? 'bg-blue-100 text-[#0b5447]' : 'bg-purple-100 text-purple-700'"
                  class="px-2 py-1 rounded-full text-xs font-medium">
                  {{ m.tipo_marcacion || "BIO" }}
                </span>
              </td>
              <td class="px-4 py-3 text-gray-400 text-xs">{{ m.ip }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue"
import { useAuthStore } from "@/stores/auth"
import api from "@/services/api"

const auth         = useAuthStore()
const esAdmin      = computed(() => auth.tieneRol("ADMINISTRADOR"))
const estado       = ref({ empleado: null, marcaciones: [], siguiente: null })
const listado      = ref([])
const marcando     = ref(false)
const mensajeExito = ref("")
const mensajeError = ref("")
const cargandoListado = ref(false)
const filtroFecha  = ref(new Date().toISOString().substring(0, 10))
const filtroBuscar = ref("")
const horaActual   = ref("")
const fechaHoy     = ref("")

// Historial personal
const historial         = ref([])
const cargandoHistorial = ref(false)
const histTipo          = ref("todos")
const histFechaDesde    = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().substring(0, 10))
const histFechaHasta    = ref(new Date().toISOString().substring(0, 10))

const minATexto = (min) => {
  if (!min || min === 0) return "—"
  const h = Math.floor(min / 60)
  const m = min % 60
  if (h > 0 && m > 0) return `${h}h ${m}min`
  if (h > 0) return `${h}h`
  return `${m}min`
}

const cargarHistorial = async () => {
  cargandoHistorial.value = true
  try {
    const { data } = await api.get("/asistencia/mi-reporte", {
      params: {
        fecha_desde: histFechaDesde.value,
        fecha_hasta: histFechaHasta.value,
        tipo: histTipo.value,
      }
    })
    historial.value = data
  } catch (e) {
    historial.value = []
  } finally {
    cargandoHistorial.value = false
  }
}

let intervalo = null

const ICONOS = {
  'ENTRADA':           '/marcacion/marcacion_entrada.png',
  'SALIDA AL LUNCH':   '/marcacion/marcacion_salida_almuerzo.png',
  'ENTRADA DEL LUNCH': '/marcacion/marcacion_entrada_almuerzo.png',
  'SALIDA':            '/marcacion/marcacion_salida.png',
}

const LABELS = {
  'ENTRADA':           'Marcar Entrada',
  'SALIDA AL LUNCH':   'Salida a Lunch',
  'ENTRADA DEL LUNCH': 'Regreso de Lunch',
  'SALIDA':            'Marcar Salida',
}

const btnStyle = (estadoBtn) => {
  if (estadoBtn === 'apagado')       return 'background-color:#c3dbd7; color:#3a6b63;'
  if (estadoBtn === 'activo')        return 'background-color:#0b5547; color:#ffffff;'
  if (estadoBtn === 'por_activarse') return 'background-color:#068174; color:#ffffff;'
  return ''
}

const botones = computed(() => {
  const secuencia = ['ENTRADA', 'SALIDA AL LUNCH', 'ENTRADA DEL LUNCH', 'SALIDA']
  return secuencia.map(concepto => {
    const yaMarcado = !!getMarcacion(concepto)
    const esActivo  = estado.value.siguiente === concepto
    const estadoBtn = yaMarcado ? 'apagado' : esActivo ? 'activo' : 'por_activarse'
    return {
      concepto,
      label:      LABELS[concepto],
      icono:      ICONOS[concepto],
      disponible: esActivo,
      estadoBtn,
    }
  })
})

const getMarcacion = (concepto) => {
  return estado.value.marcaciones?.find(m => m.concepto === concepto)
}

const formatHora = (fechaHora) => {
  if (!fechaHora) return ""
  return fechaHora.toString().substring(11, 19)
}

const colorConcepto = (concepto) => {
  const colores = {
    "ENTRADA":           "bg-green-100 text-green-700",
    "SALIDA AL LUNCH":   "bg-yellow-100 text-yellow-700",
    "ENTRADA DEL LUNCH": "bg-blue-100 text-[#0b5447]",
    "SALIDA":            "bg-red-100 text-red-700",
  }
  return colores[concepto] || "bg-gray-100 text-gray-700"
}

const actualizarHora = () => {
  const ahora = new Date()
  horaActual.value = ahora.toLocaleTimeString("es-EC")
  fechaHoy.value   = ahora.toLocaleDateString("es-EC", {
    weekday: "long", year: "numeric", month: "long", day: "numeric"
  })
}

const cargarEstado = async () => {
  try {
    const { data } = await api.get("/asistencia/mi-estado")
    estado.value = data
  } catch (e) {
    console.error(e)
  }
}

const cargarListado = async () => {
  if (!esAdmin.value) return
  cargandoListado.value = true
  try {
    const { data } = await api.get("/asistencia/listado", {
      params: { fecha: filtroFecha.value, buscar: filtroBuscar.value }
    })
    listado.value = data
  } catch (e) {
    console.error(e)
  } finally {
    cargandoListado.value = false
  }
}

const marcar = async (concepto) => {
  if (concepto === 'SALIDA') {
    const ahora = new Date()
    const minutos = ahora.getHours() * 60 + ahora.getMinutes()
    if (minutos < 16 * 60 + 30) {
      if (!window.confirm('¿Está seguro de realizar esta marcación? La hora de salida es antes de las 16:30.')) return
    }
  }
  marcando.value     = true
  mensajeExito.value = ""
  mensajeError.value = ""
  try {
    const { data } = await api.post("/asistencia/marcar", { concepto })
    mensajeExito.value = data.message + " a las " + data.hora
    await cargarEstado()
    if (esAdmin.value) await cargarListado()
    setTimeout(() => { mensajeExito.value = "" }, 5000)
  } catch (e) {
    mensajeError.value = e.response?.data?.message || "Error al registrar marcacion"
    setTimeout(() => { mensajeError.value = "" }, 5000)
  } finally {
    marcando.value = false
  }
}

onMounted(async () => {
  actualizarHora()
  intervalo = setInterval(actualizarHora, 1000)
  await cargarEstado()
  await cargarHistorial()
  if (esAdmin.value) await cargarListado()
})

onUnmounted(() => {
  if (intervalo) clearInterval(intervalo)
})
</script>