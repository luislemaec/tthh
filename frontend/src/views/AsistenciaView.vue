<template>
  <div class="space-y-6">

    <!-- Tarjeta de timbrada del empleado -->
    <div class="bg-white rounded-xl shadow p-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800 text-center">Control de Asistencia</h1>
        <div class="text-center mt-3">
          <p class="text-sm font-medium text-gray-500 uppercase tracking-wide">{{ fechaHoy }}</p>
          <p class="text-5xl font-bold text-gray-800 mt-1 tabular-nums">{{ horaActual }}</p>
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
            'w-full py-2 rounded-xl text-sm font-semibold transition flex flex-col items-center gap-1'
          ]">
          <img :src="btn.icono" class="w-28 h-28 md:w-44 md:h-44 object-contain" decoding="async" />
          <span>{{ btn.label }}</span>
          <span v-if="getMarcacion(btn.concepto)" class="text-xs font-normal opacity-70">
            {{ formatHora(getMarcacion(btn.concepto)?.fecha_hora) }}
          </span>
          <span v-else-if="btn.disponible" class="text-xs font-normal opacity-80">
            {{ marcando ? "Registrando..." : "Pendiente" }}
          </span>
        </button>
      </div>

      <!-- Banner de bloqueo por modalidad BIOMETRICO o TELETRABAJO vencido -->
      <div v-if="estado.puede_marcar === false"
        class="flex items-center gap-3 bg-amber-100 border-2 border-amber-400 text-amber-900 px-5 py-4 rounded-xl text-base font-bold mb-4 text-center justify-center">
        <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
        </svg>
        <span>{{ estado.mensaje_bloqueo }}</span>
      </div>

    </div>

    <!-- Toast flotante: fijo en la parte superior, siempre visible sin importar el scroll -->
    <Teleport to="body">
      <div v-if="mensajeExito || mensajeError"
        class="fixed top-4 inset-x-0 z-[9985] flex justify-center px-4 pointer-events-none">
        <div :class="mensajeError ? 'bg-red-600' : 'bg-green-600'"
          class="pointer-events-auto max-w-md w-full sm:w-auto flex items-center gap-3 text-white px-5 py-3 rounded-xl shadow-lg text-sm font-medium">
          <svg v-if="mensajeError" class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
          </svg>
          <svg v-else class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
          </svg>
          <span class="flex-1">{{ mensajeError || mensajeExito }}</span>
          <button @click="mensajeExito = ''; mensajeError = ''"
            class="text-white/80 hover:text-white text-lg leading-none flex-shrink-0">×</button>
        </div>
      </div>
    </Teleport>

    <!-- Modal confirmar salida antes de las 16:30 -->
    <div v-if="modalSalidaTemprana" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-md space-y-4">
        <h2 class="text-lg font-semibold text-gray-700">Marcar Salida</h2>
        <p class="text-sm text-gray-600">La hora de salida es antes de las 16:30. ¿Está seguro de realizar esta marcación?</p>
        <div class="flex justify-end gap-3">
          <button @click="modalSalidaTemprana = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
          <button @click="confirmarSalidaTemprana" :disabled="marcando"
            class="px-4 py-2 rounded-lg text-white text-sm disabled:opacity-50"
            style="background-color:#0b5447;">
            {{ marcando ? "Registrando..." : "Confirmar" }}
          </button>
        </div>
      </div>
    </div>

    <!-- Historial personal de marcaciones -->
    <div class="bg-white rounded-xl shadow p-6">
      <div class="mb-4 space-y-3">
        <h2 class="text-lg font-bold text-gray-800">Mis Marcaciones</h2>
        <p v-if="estado.articulo_atrasos"
          class="text-sm font-medium text-white px-4 py-2 rounded-lg"
          style="background-color:#0b5447;">
          {{ estado.articulo_atrasos }}
        </p>
        <div class="flex flex-wrap gap-2 items-center">
          <input v-model="histFechaDesde" type="date" @change="cargarHistorial"
            class="w-full sm:w-auto border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          <input v-model="histFechaHasta" type="date" @change="cargarHistorial"
            class="w-full sm:w-auto border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          <select v-model="histTipo" @change="cargarHistorial"
            class="w-full sm:w-auto border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
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
                <template v-if="r.atraso > 0">
                  <span class="text-red-700 font-medium">{{ minATexto(r.atraso) }}</span>
                  <div v-if="r.concepto === 'ENTRADA DEL LUNCH' && horaDebiRegresarLunch(r.fecha)"
                       class="text-xs text-amber-600 mt-0.5">
                    Debió: {{ horaDebiRegresarLunch(r.fecha) }}
                  </div>
                </template>
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

const horaDebiRegresarLunch = (fecha) => {
  const salidaLunch = historial.value.find(
    r => r.fecha === fecha && r.concepto === 'SALIDA AL LUNCH'
  )
  if (!salidaLunch) return null
  const [h, m] = salidaLunch.hora.split(':').map(Number)
  // Minutos de tolerancia del almuerzo — vienen de d2_configuracion (TIEMPO_CASTIGO_LUNCH),
  // mismo valor que usa el backend en ProcesarCuadre para calcular el atraso real. Antes
  // era un 30 fijo acá, sin relación con el cálculo real del servidor.
  const totalMin = h * 60 + m + (estado.value.tiempo_castigo_lunch ?? 30)
  const hh = String(Math.floor(totalMin / 60) % 24).padStart(2, '0')
  const mm = String(totalMin % 60).padStart(2, '0')
  return `${hh}:${mm}`
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
  const bloqueado = estado.value.puede_marcar === false
  return secuencia.map(concepto => {
    const yaMarcado = !!getMarcacion(concepto)
    const esActivo  = !bloqueado && estado.value.siguiente === concepto
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

const modalSalidaTemprana = ref(false)

const marcar = async (concepto) => {
  if (concepto === 'SALIDA') {
    const ahora = new Date()
    const minutos = ahora.getHours() * 60 + ahora.getMinutes()
    if (minutos < 16 * 60 + 30) {
      modalSalidaTemprana.value = true
      return
    }
  }
  await ejecutarMarcar(concepto)
}

const confirmarSalidaTemprana = async () => {
  await ejecutarMarcar('SALIDA')
  modalSalidaTemprana.value = false
}

const ejecutarMarcar = async (concepto) => {
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
  // Precarga las imágenes para evitar el blank al primer clic
  Object.values(ICONOS).forEach(src => { const i = new Image(); i.src = src })

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