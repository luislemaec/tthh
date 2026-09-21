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
        <button @click="abrirModalFeriados"
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
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Hora Desde</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Hora Hasta</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Ubicacion</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando">
            <td colspan="7" class="text-center py-8 text-gray-400">Cargando...</td>
          </tr>
          <tr v-else-if="fechas.length === 0">
            <td colspan="7" class="text-center py-8 text-gray-400">No hay fechas registradas para este año.</td>
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
            <td class="px-6 py-3 text-gray-600">{{ f.hora_desde }}</td>
            <td class="px-6 py-3 text-gray-600">{{ f.hora_hasta }}</td>
            <td class="px-6 py-3 text-gray-600">{{ f.ubicacion }}</td>
            <td class="px-6 py-3">
              <div class="flex gap-1 flex-wrap">
                <button @click="abrirModal(f)"
                  class="inline-flex items-center px-2.5 py-1 rounded-md border border-amber-300 text-xs text-amber-700 hover:bg-amber-50 font-medium transition-colors">
                  Editar
                </button>
                <button @click="abrirModalEliminar(f)"
                  class="inline-flex items-center px-2.5 py-1 rounded-md border border-red-200 text-xs text-red-600 hover:bg-red-50 font-medium transition-colors">
                  Eliminar
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal Cargar Feriados Ecuador -->
    <div v-if="modalFeriados.show" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-md space-y-4">
        <h2 class="text-lg font-semibold text-gray-700">Cargar Feriados Ecuador</h2>
        <p v-if="!modalFeriados.mensaje" class="text-sm text-gray-600">
          Cargar feriados nacionales de Ecuador para el año {{ anioSeleccionado }}
        </p>
        <div v-if="modalFeriados.mensaje" class="text-green-700 text-sm bg-green-50 rounded p-2">{{ modalFeriados.mensaje }}</div>
        <div v-if="modalFeriados.error" class="text-red-600 text-sm bg-red-50 rounded p-2">{{ modalFeriados.error }}</div>
        <div class="flex justify-end gap-3">
          <button @click="modalFeriados.show = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">
            {{ modalFeriados.mensaje ? "Cerrar" : "Cancelar" }}
          </button>
          <button v-if="!modalFeriados.mensaje" @click="confirmarFeriados" :disabled="modalFeriados.procesando"
            class="px-4 py-2 rounded-lg bg-green-600 text-white text-sm hover:bg-green-700 disabled:opacity-50">
            {{ modalFeriados.procesando ? "Cargando..." : "Confirmar Carga" }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Eliminar fecha -->
    <div v-if="modalEliminar.show" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-md space-y-4">
        <h2 class="text-lg font-semibold text-gray-700">Eliminar Fecha</h2>
        <p class="text-sm text-gray-500">
          {{ modalEliminar.fecha?.fecha }} &mdash; {{ modalEliminar.fecha?.tipo }}
        </p>
        <p class="text-sm text-gray-600">Confirmar eliminación</p>
        <div v-if="modalEliminar.error" class="text-red-600 text-sm bg-red-50 rounded p-2">{{ modalEliminar.error }}</div>
        <div class="flex justify-end gap-3">
          <button @click="modalEliminar.show = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
          <button @click="confirmarEliminar" :disabled="modalEliminar.procesando"
            class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm hover:bg-red-700 disabled:opacity-50">
            {{ modalEliminar.procesando ? "Procesando..." : "Confirmar Eliminación" }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="modal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-md overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4" style="background-color:#0b5447;">
          <h2 class="text-lg font-semibold text-white">
            {{ form.editando ? "Editar Fecha" : "Nueva Fecha" }}
          </h2>
          <button type="button" @click="modal = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-6 space-y-4">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Fecha *</label>
            <input v-model="form.fecha" type="date"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]"
              required />
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
  editando: false, fechaOriginal: "", fecha: "", tipo: "FERIADO",
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
    fechaOriginal: f.fecha,
    fecha: f.fecha, tipo: f.tipo.trim(),
    factor: f.factor, color: f.color.trim(),
    hora_desde: f.hora_desde, hora_hasta: f.hora_hasta,
    ubicacion: f.ubicacion.trim()
  } : {
    editando: false, fechaOriginal: "", fecha: "", tipo: "FERIADO",
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
      await api.put("/admin/calendario/" + form.value.fechaOriginal + "/" + form.value.ubicacion, form.value)
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

const modalEliminar = ref({ show: false, fecha: null, error: "", procesando: false })

const abrirModalEliminar = (f) => {
  modalEliminar.value = { show: true, fecha: f, error: "", procesando: false }
}

const confirmarEliminar = async () => {
  const f = modalEliminar.value.fecha
  modalEliminar.value.procesando = true
  modalEliminar.value.error = ""
  try {
    await api.delete("/admin/calendario/" + f.fecha + "/" + f.ubicacion)
    modalEliminar.value.show = false
    cargar()
  } catch (e) {
    modalEliminar.value.error = e.response?.data?.message || "Error al eliminar."
  } finally {
    modalEliminar.value.procesando = false
  }
}

const modalFeriados = ref({ show: false, mensaje: "", error: "", procesando: false })

const abrirModalFeriados = () => {
  modalFeriados.value = { show: true, mensaje: "", error: "", procesando: false }
}

const confirmarFeriados = async () => {
  modalFeriados.value.procesando = true
  modalFeriados.value.error = ""
  try {
    const { data } = await api.post("/admin/calendario/feriados-ecuador", {
      anio: anioSeleccionado.value,
      ubicacion: "Quito"
    })
    modalFeriados.value.mensaje = data.message || "Feriados cargados."
    cargar()
  } catch (e) {
    modalFeriados.value.error = e.response?.data?.message || "Error al cargar feriados."
  } finally {
    modalFeriados.value.procesando = false
  }
}

onMounted(() => {
  const actual = new Date().getFullYear()
  anios.value = [actual - 1, actual, actual + 1, actual + 2, actual + 3]
  cargar()
})
</script>