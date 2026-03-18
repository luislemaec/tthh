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
          :class="[
            btn.disponible
              ? btn.color + ' cursor-pointer hover:opacity-90'
              : 'bg-gray-100 text-gray-400 cursor-not-allowed',
            'w-full py-6 rounded-xl text-sm font-semibold transition flex flex-col items-center gap-2'
          ]">
          <span class="text-2xl">{{ btn.icono }}</span>
          <span>{{ btn.label }}</span>
          <span v-if="getMarcacion(btn.concepto)" class="text-xs font-normal opacity-80">
            {{ formatHora(getMarcacion(btn.concepto)?.fecha_hora) }}
          </span>
          <span v-else-if="btn.disponible" class="text-xs font-normal opacity-80">
            {{ marcando && estado.siguiente === btn.concepto ? "Registrando..." : "Pendiente" }}
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

    <!-- Panel administrador: listado del dia -->
    <div v-if="esAdmin" class="bg-white rounded-xl shadow p-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold text-gray-800">Marcaciones del Dia</h2>
        <div class="flex gap-2">
          <input v-model="filtroFecha" type="date"
            @change="cargarListado"
            class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          <input v-model="filtroBuscar" type="text" placeholder="Buscar empleado..."
            @input="cargarListado"
            class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
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
                <span :class="m.tipo_marcacion === 'WEB' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'"
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

let intervalo = null

const botones = computed(() => [
  {
    concepto: "ENTRADA",
    label: "Marcar Entrada",
    icono: "🟢",
    color: "bg-green-500 text-white",
    disponible: estado.value.siguiente === "ENTRADA",
  },
  {
    concepto: "SALIDA AL LUNCH",
    label: "Salida a Lunch",
    icono: "🍽️",
    color: "bg-yellow-500 text-white",
    disponible: estado.value.siguiente === "SALIDA AL LUNCH",
  },
  {
    concepto: "ENTRADA DEL LUNCH",
    label: "Regreso de Lunch",
    icono: "🔄",
    color: "bg-blue-500 text-white",
    disponible: estado.value.siguiente === "ENTRADA DEL LUNCH",
  },
  {
    concepto: "SALIDA",
    label: "Marcar Salida",
    icono: "🔴",
    color: "bg-red-500 text-white",
    disponible: estado.value.siguiente === "SALIDA",
  },
])

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
    "ENTRADA DEL LUNCH": "bg-blue-100 text-blue-700",
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
  if (esAdmin.value) await cargarListado()
})

onUnmounted(() => {
  if (intervalo) clearInterval(intervalo)
})
</script>