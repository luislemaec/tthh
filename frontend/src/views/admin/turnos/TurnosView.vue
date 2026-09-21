<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-800">Horarios</h1>
      <!-- <button @click="abrirModal()"
        class="bg-[#0b5447] text-white px-4 py-2 rounded-lg hover:bg-[#00372e] text-sm font-medium">
        + Nuevo Turno
      </button> -->
    </div>

    <!-- Lista de turnos -->
    <div class="grid grid-cols-1 gap-4">
      <div v-if="cargando" class="bg-white rounded-xl shadow p-8 text-center text-gray-400">Cargando...</div>
      <div v-else-if="turnos.length === 0" class="bg-white rounded-xl shadow p-8 text-center text-gray-400">No hay turnos registrados.</div>
      <div v-for="t in turnos" :key="t.id_turno" class="bg-white rounded-xl shadow overflow-hidden">
        <!-- Cabecera del turno -->
        <div class="flex items-center justify-between px-6 py-4 border-b"
          :style="{ borderLeftColor: t.color || '#3b82f6', borderLeftWidth: '4px' }">
          <div class="flex items-center gap-3">
            <span class="w-3 h-3 rounded-full" :style="{ backgroundColor: t.color || '#3b82f6' }"></span>
            <div>
              <h3 class="font-bold text-gray-800">{{ t.descripcion }}</h3>
              <p class="text-xs text-gray-500">{{ t.horas_normales ?? 8 }} horas normales</p>
            </div>
          </div>
          <div class="flex gap-2">
            <button @click="abrirModalHorarios(t)"
              class="bg-purple-100 text-purple-700 px-3 py-1 rounded-lg text-xs font-medium hover:bg-purple-200">
              Horarios
            </button>
            <button @click="abrirModal(t)"
              class="bg-blue-100 text-[#0b5447] px-3 py-1 rounded-lg text-xs font-medium hover:bg-blue-200">
              Editar
            </button>
            <button @click="abrirModalEliminar(t)"
              class="bg-red-100 text-red-700 px-3 py-1 rounded-lg text-xs font-medium hover:bg-red-200">
              Eliminar
            </button>
          </div>
        </div>
        <!-- Detalle de horarios -->
        <div class="px-6 py-3">
          <div v-if="!t.horarios || t.horarios.length === 0"
            class="text-xs text-gray-400 py-2">
            Sin horarios definidos. Haz clic en "Horarios" para agregar.
          </div>
          <div v-else class="flex flex-wrap gap-3">
            <div v-for="h in t.horarios" :key="h.concepto"
              class="flex items-center gap-2 bg-gray-50 rounded-lg px-3 py-2">
              <span class="text-xs font-medium text-gray-500">{{ h.concepto }}</span>
              <span class="text-sm font-bold text-gray-800">
                {{ formatHora(h.hora) }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Eliminar turno -->
    <div v-if="modalEliminar.show" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-md space-y-4">
        <h2 class="text-lg font-semibold text-gray-700">Eliminar Horario</h2>
        <p class="text-sm text-gray-500">{{ modalEliminar.turno?.descripcion }}</p>
        <p class="text-sm text-gray-600">Se eliminará el horario y todas sus horas definidas. Confirmar eliminación</p>
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

    <!-- Modal Turno -->
    <div v-if="modal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-md overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4" style="background-color:#0b5447;">
          <h2 class="text-lg font-semibold text-white">{{ form.id_turno ? "Editar Turno" : "Nuevo Turno" }}</h2>
          <button @click="modal = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Descripcion *</label>
          <input v-model="form.descripcion" type="text" placeholder="Ej: TURNO MANANA"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Horas Normales</label>
            <input v-model="form.horas_normales" type="number" min="1" max="24"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Color</label>
            <select v-model="form.color"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
              <option value="#3b82f6">Azul</option>
              <option value="#10b981">Verde</option>
              <option value="#f59e0b">Amarillo</option>
              <option value="#ef4444">Rojo</option>
              <option value="#8b5cf6">Morado</option>
              <option value="#6b7280">Gris</option>
            </select>
          </div>
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

    <!-- Modal Horarios -->
    <div v-if="modalHorarios" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-lg overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4" style="background-color:#0b5447;">
          <h2 class="text-lg font-semibold text-white">Horarios: {{ turnoSeleccionado?.descripcion }}</h2>
          <button @click="modalHorarios = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-6 space-y-4">
        <div class="space-y-3">
          <div v-for="(h, idx) in horarios" :key="idx"
            class="grid grid-cols-3 gap-3 items-center">
            <div>
              <label class="block text-xs font-medium text-gray-500 mb-1">Concepto</label>
              <select v-model="h.concepto"
                class="w-full border rounded-lg px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
                <option value="ENTRADA">ENTRADA</option>
                <option value="SALIDA AL LUNCH">SALIDA AL LUNCH</option>
                <option value="ENTRADA DEL LUNCH">ENTRADA DEL LUNCH</option>
                <option value="SALIDA">SALIDA</option>
                <option value="MINUTOS LUNCH">MINUTOS LUNCH</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-medium text-gray-500 mb-1">Hora</label>
              <input v-model="h.hora" type="time"
                class="w-full border rounded-lg px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
            </div>
            <div class="flex items-end pb-1">
              <button @click="quitarHorario(idx)"
                class="bg-red-100 text-red-600 px-3 py-2 rounded-lg text-xs hover:bg-red-200">
                Quitar
              </button>
            </div>
          </div>
        </div>
        <button @click="agregarHorario"
          class="w-full border-2 border-dashed border-gray-300 text-gray-500 py-2 rounded-lg text-sm hover:border-blue-400 hover:text-blue-500">
          + Agregar horario
        </button>
        <div v-if="errorHorario" class="text-red-600 text-sm bg-red-50 rounded p-2">{{ errorHorario }}</div>
        <div class="flex justify-end gap-3 pt-2">
          <button @click="modalHorarios = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
          <button @click="guardarHorarios" :disabled="guardandoHorarios"
            class="px-4 py-2 rounded-lg bg-purple-600 text-white text-sm hover:bg-purple-700 disabled:opacity-50">
            {{ guardandoHorarios ? "Guardando..." : "Guardar Horarios" }}
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

const turnos           = ref([])
const cargando         = ref(false)
const modal            = ref(false)
const modalHorarios    = ref(false)
const guardando        = ref(false)
const guardandoHorarios = ref(false)
const error            = ref("")
const errorHorario     = ref("")
const turnoSeleccionado = ref(null)
const horarios         = ref([])
const form             = ref({ id_turno: null, descripcion: "", horas_normales: 8, color: "#3b82f6" })

const cargar = async () => {
  cargando.value = true
  try {
    const { data } = await api.get("/admin/turnos")
    turnos.value = data
  } catch (e) {
    console.error(e)
  } finally {
    cargando.value = false
  }
}

const formatHora = (hora) => {
  if (!hora) return ""
  return hora.toString().substring(11, 16)
}

const abrirModal = (t = null) => {
  error.value = ""
  form.value = t
    ? { id_turno: t.id_turno, descripcion: t.descripcion, horas_normales: t.horas_normales, color: t.color }
    : { id_turno: null, descripcion: "", horas_normales: 8, color: "#3b82f6" }
  modal.value = true
}

const abrirModalHorarios = (t) => {
  errorHorario.value = ""
  turnoSeleccionado.value = t
  horarios.value = t.horarios ? t.horarios.map(h => ({
    concepto: h.concepto,
    hora: formatHora(h.hora),
    id_jornada: h.id_jornada,
  })) : []
  modalHorarios.value = true
}

const agregarHorario = () => {
  horarios.value.push({ concepto: "ENTRADA", hora: "08:00", id_jornada: null })
}

const quitarHorario = (idx) => {
  horarios.value.splice(idx, 1)
}

const guardar = async () => {
  if (!form.value.descripcion) { error.value = "La descripcion es requerida."; return }
  guardando.value = true
  error.value = ""
  try {
    if (form.value.id_turno) {
      await api.put("/admin/turnos/" + form.value.id_turno, form.value)
    } else {
      await api.post("/admin/turnos", form.value)
    }
    modal.value = false
    cargar()
  } catch (e) {
    error.value = e.response?.data?.message || "Error al guardar."
  } finally {
    guardando.value = false
  }
}

const guardarHorarios = async () => {
  if (horarios.value.length === 0) { errorHorario.value = "Debe agregar al menos un horario."; return }
  guardandoHorarios.value = true
  errorHorario.value = ""
  try {
    await api.post("/admin/turnos/" + turnoSeleccionado.value.id_turno + "/horarios", {
      horarios: horarios.value
    })
    modalHorarios.value = false
    cargar()
  } catch (e) {
    errorHorario.value = e.response?.data?.message || "Error al guardar horarios."
  } finally {
    guardandoHorarios.value = false
  }
}

const modalEliminar = ref({ show: false, turno: null, error: "", procesando: false })

const abrirModalEliminar = (t) => {
  modalEliminar.value = { show: true, turno: t, error: "", procesando: false }
}

const confirmarEliminar = async () => {
  modalEliminar.value.procesando = true
  modalEliminar.value.error = ""
  try {
    await api.delete("/admin/turnos/" + modalEliminar.value.turno.id_turno)
    modalEliminar.value.show = false
    cargar()
  } catch (e) {
    modalEliminar.value.error = e.response?.data?.message || "Error al eliminar."
  } finally {
    modalEliminar.value.procesando = false
  }
}

onMounted(cargar)
</script>