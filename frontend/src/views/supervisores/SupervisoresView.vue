<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-800">Jefes de Área</h1>
      <button @click="abrirModal"
        class="bg-[#0b5447] text-white px-4 py-2 rounded-lg hover:bg-[#00372e] text-sm font-medium">
        + Asignar Jefe de Área
      </button>
    </div>

    <!-- Alerta supervisores inactivos -->
    <div v-if="tieneInactivos"
      class="flex items-center gap-3 bg-red-50 border border-red-300 text-red-700 rounded-lg px-4 py-3 text-sm">
      <span class="text-lg">⚠</span>
      <span>Hay áreas con supervisores en estado <strong>INACTIVO</strong>. Se recomienda reasignar a un empleado activo.</span>
    </div>

    <!-- Filtro búsqueda -->
    <div class="bg-white rounded-xl shadow p-4">
      <input v-model="filtroBuscar" placeholder="Buscar por área o nombre del supervisor..."
        class="border rounded-lg px-3 py-2 text-sm w-80 focus:outline-none focus:ring-2 focus:ring-[#579186]" />
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Area / Departamento</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Supervisor Asignado</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Depto del Supervisor</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Fecha Registro</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando">
            <td colspan="5" class="text-center py-8 text-gray-400">Cargando...</td>
          </tr>
          <tr v-else-if="supervisoresFiltrados.length === 0">
            <td colspan="5" class="text-center py-8 text-gray-400">No hay supervisores asignados</td>
          </tr>
          <tr v-for="s in supervisoresFiltrados" :key="s.id"
            class="border-b"
            :class="s.supervisor?.estado === 'INACTIVO'
              ? 'bg-red-50 hover:bg-red-100'
              : 'hover:bg-gray-50'">
            <td class="px-4 py-3 font-medium">{{ s.departamento?.nombre_depto }}</td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <span :class="s.supervisor?.estado === 'INACTIVO' ? 'font-medium text-red-700' : 'font-medium text-[#0b5447]'">
                  {{ s.supervisor?.apellido_emp }}, {{ s.supervisor?.nombre_emp }}
                </span>
                <span v-if="s.supervisor?.estado === 'INACTIVO'"
                  class="text-xs font-bold bg-red-100 text-red-700 border border-red-300 px-2 py-0.5 rounded-full">
                  INACTIVO
                </span>
              </div>
              <p v-if="s.supervisor?.estado === 'INACTIVO'"
                class="text-xs text-red-500 mt-0.5">Reasignar a un jefe de área activo</p>
            </td>
            <td class="px-4 py-3 text-gray-500">
              {{ s.supervisor?.departamento?.nombre_depto }}
            </td>
            <td class="px-4 py-3 text-gray-500">{{ s.fecha_registro?.substring(0,10) }}</td>
            <td class="px-4 py-3">
              <div class="flex gap-1 flex-wrap">
                <button @click="editar(s)"
                  class="inline-flex items-center px-2.5 py-1 rounded-md border border-amber-300 text-xs text-amber-700 hover:bg-amber-50 font-medium transition-colors">
                  Editar
                </button>
                <button @click="abrirModalEliminar(s)"
                  class="inline-flex items-center px-2.5 py-1 rounded-md border border-red-200 text-xs text-red-600 hover:bg-red-50 font-medium transition-colors">
                  Eliminar
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal Eliminar -->
    <div v-if="modalEliminar.show" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-md space-y-4">
        <h2 class="text-lg font-semibold text-gray-700">Eliminar Jefe de Área</h2>
        <p class="text-sm text-gray-500">
          {{ modalEliminar.sup?.departamento?.nombre_depto }}
          &mdash; {{ modalEliminar.sup?.supervisor?.apellido_emp }}, {{ modalEliminar.sup?.supervisor?.nombre_emp }}
        </p>
        <p class="text-sm text-gray-600">Confirmar eliminación del jefe de esta área</p>
        <div v-if="modalEliminar.error" class="text-red-600 text-sm bg-red-50 rounded p-2">{{ modalEliminar.error }}</div>
        <div class="flex justify-end gap-3">
          <button @click="modalEliminar.show = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
          <button @click="confirmarEliminar" :disabled="modalEliminar.procesando"
            class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm hover:bg-red-700 disabled:opacity-50">
            {{ modalEliminar.procesando ? 'Procesando...' : 'Confirmar Eliminación' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="modal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-lg overflow-hidden">
        <div class="px-6 py-4" style="background-color:#0b5447;">
          <h2 class="text-lg font-semibold text-white">
            {{ form.editando ? "Editar Jefe de Área" : "Asignar Jefe de Área" }}
          </h2>
        </div>
        <div class="p-6 space-y-4">

        <!-- Departamento -->
        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Area / Departamento *</label>
          <select v-model="form.id_depto" required :disabled="form.editando"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
            <option value="">Seleccionar area...</option>
            <option v-for="d in departamentos" :key="d.id_depto" :value="d.id_depto">
              {{ d.nombre_depto }}
            </option>
          </select>
        </div>

        <!-- Jefe de Área -->
        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Jefe de Área *</label>
          <input v-model="buscarSup" type="text" placeholder="Buscar por nombre o cedula..."
            @input="filtrarEmpleados"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          <div v-if="resultadosSup.length" class="border rounded-lg mt-1 max-h-48 overflow-y-auto shadow-md">
            <div v-for="e in resultadosSup" :key="e.id_emp"
              @click="seleccionarSupervisor(e)"
              class="px-3 py-2 hover:bg-blue-50 cursor-pointer text-sm border-b last:border-0">
              <span class="font-medium">{{ e.apellido_emp }}, {{ e.nombre_emp }}</span>
              <span class="text-gray-400 ml-2 text-xs">{{ e.departamento?.nombre_depto }}</span>
            </div>
          </div>
          <p v-if="form.id_supervisor" class="text-xs text-green-600 mt-1 font-medium">
            Seleccionado: {{ form.nombreSupervisor }}
          </p>
        </div>

        <div v-if="error" class="text-red-600 text-sm bg-red-50 rounded p-2">{{ error }}</div>

        <div class="flex justify-end gap-3 pt-2">
          <button @click="modal = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">
            Cancelar
          </button>
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
import { ref, computed, onMounted } from "vue"
import api from "@/services/api"

const supervisores   = ref([])
const departamentos  = ref([])
const todosEmpleados = ref([])
const cargando       = ref(false)
const modal          = ref(false)
const guardando      = ref(false)
const error          = ref("")
const buscarSup      = ref("")
const resultadosSup  = ref([])
const filtroBuscar   = ref("")

const form = ref({
  editando: false, id: null,
  id_depto: "", id_supervisor: "", nombreSupervisor: ""
})

const tieneInactivos = computed(() =>
  supervisores.value.some(s => s.supervisor?.estado === 'INACTIVO')
)

const supervisoresFiltrados = computed(() => {
  const b = filtroBuscar.value.toLowerCase().trim()
  if (!b) return supervisores.value
  return supervisores.value.filter(s =>
    s.departamento?.nombre_depto?.toLowerCase().includes(b) ||
    s.supervisor?.apellido_emp?.toLowerCase().includes(b) ||
    s.supervisor?.nombre_emp?.toLowerCase().includes(b)
  )
})

const cargar = async () => {
  cargando.value = true
  try {
    const { data } = await api.get("/supervisores")
    supervisores.value = data
  } catch (e) {
    console.error(e)
  } finally {
    cargando.value = false
  }
}

const abrirModal = async () => {
  error.value = ""
  form.value = { editando: false, id: null, id_depto: "", id_supervisor: "", nombreSupervisor: "" }
  buscarSup.value = ""
  resultadosSup.value = []

  if (departamentos.value.length === 0) {
    const { data } = await api.get("/departamentos")
    departamentos.value = data
  }
  if (todosEmpleados.value.length === 0) {
    const { data } = await api.get("/empleados?per_page=500&estado=ACTIVO")
    todosEmpleados.value = data.data
  }
  modal.value = true
}

const editar = async (s) => {
  error.value = ""
  if (departamentos.value.length === 0) {
    const { data } = await api.get("/departamentos")
    departamentos.value = data
  }
  if (todosEmpleados.value.length === 0) {
    const { data } = await api.get("/empleados?per_page=500&estado=ACTIVO")
    todosEmpleados.value = data.data
  }
  form.value = {
    editando: true, id: s.id,
    id_depto: s.id_depto,
    id_supervisor: s.id_supervisor,
    nombreSupervisor: s.supervisor?.apellido_emp + ", " + s.supervisor?.nombre_emp
  }
  buscarSup.value = ""
  resultadosSup.value = []
  modal.value = true
}

const filtrarEmpleados = () => {
  const b = buscarSup.value.toLowerCase()
  if (!b || b.length < 2) { resultadosSup.value = []; return }
  resultadosSup.value = todosEmpleados.value.filter(e =>
    e.nombre_emp?.toLowerCase().includes(b) ||
    e.apellido_emp?.toLowerCase().includes(b) ||
    e.identificacion?.includes(b)
  ).slice(0, 8)
}

const seleccionarSupervisor = (e) => {
  form.value.id_supervisor    = e.id_emp
  form.value.nombreSupervisor = e.apellido_emp + ", " + e.nombre_emp
  buscarSup.value = ""
  resultadosSup.value = []
}

const guardar = async () => {
  if (!form.value.id_depto || !form.value.id_supervisor) {
    error.value = "Debes seleccionar area y supervisor"
    return
  }
  guardando.value = true
  error.value = ""
  try {
    await api.post("/supervisores", {
      id_depto:      form.value.id_depto,
      id_supervisor: form.value.id_supervisor,
    })
    modal.value = false
    cargar()
  } catch (e) {
    error.value = e.response?.data?.message || "Error al guardar"
  } finally {
    guardando.value = false
  }
}

const modalEliminar = ref({ show: false, sup: null, error: "", procesando: false })

const abrirModalEliminar = (s) => {
  modalEliminar.value = { show: true, sup: s, error: "", procesando: false }
}

const confirmarEliminar = async () => {
  modalEliminar.value.procesando = true
  modalEliminar.value.error = ""
  try {
    await api.delete("/supervisores/" + modalEliminar.value.sup.id)
    modalEliminar.value.show = false
    cargar()
  } catch (e) {
    modalEliminar.value.error = e.response?.data?.message || "Error al eliminar"
  } finally {
    modalEliminar.value.procesando = false
  }
}

onMounted(cargar)
</script>
