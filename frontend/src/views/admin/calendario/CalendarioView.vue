<template>
  <div class="space-y-6">
    <!-- Encabezado -->
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-800">Calendario Laboral</h1>
      <div class="flex gap-2">
        <select v-model="anioSeleccionado" @change="cargar"
          class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
          <option v-for="a in anios" :key="a" :value="a">{{ a }}</option>
        </select>
        <button @click="cargarFeriados"
          class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm font-medium">
          Cargar Feriados Ecuador
        </button>
        <button @click="abrirModal()"
          class="bg-[#0b5447] text-white px-4 py-2 rounded-lg hover:bg-[#00372e] text-sm font-medium">
          + Nueva Fecha
        </button>
      </div>
    </div>

    <!-- Leyenda -->
    <div class="flex gap-4 flex-wrap">
      <div v-for="tipo in tipos" :key="tipo.valor" class="flex items-center gap-2">
        <span class="w-4 h-4 rounded" :style="{ backgroundColor: tipo.color }"></span>
        <span class="text-xs text-gray-600">{{ tipo.label }}</span>
      </div>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Fecha</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Día</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Tipo</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Factor</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Hora Desde</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Hora Hasta</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Ubicacion</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando">
            <td colspan="8" class="text-center py-8 text-gray-400">Cargando...</td>
          </tr>
          <tr v-else-if="fechas.length === 0">
            <td colspan="8" class="text-center py-8 text-gray-400">No hay fechas registradas para este año.</td>
          </tr>
          <tr v-for="f in fechas" :key="f.fecha" class="border-b hover:bg-gray-50">
            <td class="px-6 py-3 font-medium">{{ f.fecha }}</td>
            <td class="px-6 py-3 text-gray-500">{{ diaSemana(f.fecha) }}</td>
            <td class="px-6 py-3">
              <span class="px-2 py-1 rounded-full text-xs font-medium text-white"
                :style="{ backgroundColor: colorTipo(f.tipo) }">
                {{ f.tipo }}
              </span>
            </td>
            <td class="px-6 py-3 text-gray-600">{{ f.factor }}</td>
            <td class="px-6 py-3 text-gray-600">{{ f.hora_desde }}</td>
            <td class="px-6 py-3 text-gray-600">{{ f.hora_hasta }}</td>
            <td class="px-6 py-3 text-gray-600">{{ f.ubicacion }}</td>
            <td class="px-6 py-3 flex gap-2">
              <button @click="abrirModal(f)" class="text-[#0b5447] hover:underline text-xs">Editar</button>
              <button @click="eliminar(f.fecha, f.ubicacion)" class="text-red-600 hover:underline text-xs">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="modal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-md overflow-hidden">
        <div class="px-6 py-4" style="background-color:#0b5447;">
          <h2 class="text-lg font-semibold text-white">
            {{ form.editando ? "Editar Fecha" : "Nueva Fecha" }}
          </h2>
        </div>
        <div class="p-6 space-y-4">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Fecha *</label>
            <input v-model="form.fecha" type="date"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]"
              :disabled="form.editando" required />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Tipo *</label>
            <select v-model="form.tipo"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
              <option value="FERIADO">FERIADO</option>
              <option value="FIN SEMANA">FIN SEMANA</option>
              <option value="ESPECIAL">ESPECIAL</option>
            </select>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Factor *</label>
            <input v-model="form.factor" type="number" step="0.01" min="1"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Color</label>
            <select v-model="form.color"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
              <option value="red">Rojo (Feriado)</option>
              <option value="blue">Azul (Especial)</option>
              <option value="gray">Gris (Fin Semana)</option>
              <option value="green">Verde</option>
              <option value="orange">Naranja</option>
            </select>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Hora Desde *</label>
            <input v-model="form.hora_desde" type="time"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Hora Hasta *</label>
            <input v-model="form.hora_hasta" type="time"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Ubicacion *</label>
          <input v-model="form.ubicacion" type="text"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
        </div>
        <div v-if="error" class="text-red-600 text-sm bg-red-50 rounded p-2">{{ error }}</div>
        <div class="flex justify-end gap-3 pt-2">
          <button @click="modal = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
          <button @click="guardar" :disabled="guardando"
            class="px-4 py-2 rounded-lg bg-[#0b5447] text-white text-sm hover:bg-[#00372e] disabled:opacity-50">
            {{ guardando ? "Guardando..." : "Guardar" }}
          </button>
        </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue"
import api from "@/services/api"

const fechas           = ref([])
const cargando         = ref(false)
const modal            = ref(false)
const guardando        = ref(false)
const error            = ref("")
const anioSeleccionado = ref(new Date().getFullYear())
const anios            = ref([])

const tipos = [
  { valor: "FERIADO",    label: "Feriado Nacional", color: "#ef4444" },
  { valor: "FIN SEMANA", label: "Fin de Semana",    color: "#6b7280" },
  { valor: "ESPECIAL",   label: "Dia Especial",     color: "#3b82f6" },
]

const form = ref({
  editando: false, fecha: "", tipo: "FERIADO",
  factor: 2.00, color: "red", hora_desde: "00:00",
  hora_hasta: "23:59", ubicacion: "Quito"
})

const colorTipo = (tipo) => {
  const t = tipos.find(x => x.valor === tipo)
  return t ? t.color : "#6b7280"
}

const diaSemana = (fecha) => {
  const dias = ["Domingo","Lunes","Martes","Miercoles","Jueves","Viernes","Sabado"]
  return dias[new Date(fecha + "T00:00:00").getDay()]
}

const cargar = async () => {
  cargando.value = true
  try {
    const { data } = await api.get("/admin/calendario?anio=" + anioSeleccionado.value)
    fechas.value = data
  } catch (e) {
    console.error(e)
  } finally {
    cargando.value = false
  }
}

const abrirModal = (f = null) => {
  error.value = ""
  form.value = f ? {
    editando: true,
    fecha: f.fecha, tipo: f.tipo.trim(),
    factor: f.factor, color: f.color.trim(),
    hora_desde: f.hora_desde, hora_hasta: f.hora_hasta,
    ubicacion: f.ubicacion.trim()
  } : {
    editando: false, fecha: "", tipo: "FERIADO",
    factor: 2.00, color: "red", hora_desde: "00:00",
    hora_hasta: "23:59", ubicacion: "Quito"
  }
  modal.value = true
}

const guardar = async () => {
  if (!form.value.fecha) { error.value = "La fecha es requerida."; return }
  guardando.value = true
  error.value = ""
  try {
    if (form.value.editando) {
      await api.put("/admin/calendario/" + form.value.fecha + "/" + form.value.ubicacion, form.value)
    } else {
      await api.post("/admin/calendario", form.value)
    }
    modal.value = false
    cargar()
  } catch (e) {
    error.value = e.response?.data?.message || "Error al guardar."
  } finally {
    guardando.value = false
  }
}

const eliminar = async (fecha, ubicacion) => {
  if (!confirm("Seguro que deseas eliminar esta fecha?")) return
  try {
    await api.delete("/admin/calendario/" + fecha + "/" + ubicacion)
    cargar()
  } catch (e) {
    alert(e.response?.data?.message || "Error al eliminar.")
  }
}

const cargarFeriados = async () => {
  if (!confirm("Cargar feriados nacionales de Ecuador para el año " + anioSeleccionado.value + "?")) return
  try {
    const { data } = await api.post("/admin/calendario/feriados-ecuador", {
      anio: anioSeleccionado.value,
      ubicacion: "Quito"
    })
    alert(data.message)
    cargar()
  } catch (e) {
    alert("Error al cargar feriados.")
  }
}

onMounted(() => {
  const actual = new Date().getFullYear()
  anios.value = [actual - 1, actual, actual + 1, actual + 2, actual + 3]
  cargar()
})
</script>