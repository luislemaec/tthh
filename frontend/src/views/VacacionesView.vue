<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-800">Vacaciones</h1>
      <div class="flex gap-2">
        <router-link to="/planificacion"
          class="border border-[#0b5447] text-[#0b5447] px-4 py-2 rounded-lg hover:bg-[#f0faf8] text-sm font-medium">
          Planificación
        </router-link>
        <button v-if="!saldo.inactivo" @click="abrirModalNuevo"
          class="bg-[#0b5447] text-white px-4 py-2 rounded-lg hover:bg-[#00372e] text-sm font-medium">
          + Solicitar Vacaciones
        </button>
      </div>
    </div>

    <!-- Empleado inactivo -->
    <div v-if="saldo.inactivo" class="bg-yellow-50 border border-yellow-300 rounded-xl p-4 text-yellow-800 text-sm">
      Tu cuenta está inactiva. No puedes consultar saldo ni solicitar vacaciones.
    </div>

    <!-- Saldo -->
    <div v-if="saldo.saldo_calculado" class="bg-white rounded-xl shadow p-4 space-y-3">
      <div class="flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-700">Saldo de Vacaciones</h2>
        <button @click="mostrarDetalleSaldo = !mostrarDetalleSaldo"
          class="text-xs text-[#0b5447] hover:underline">
          {{ mostrarDetalleSaldo ? "Ocultar detalle" : "Ver detalle por período" }}
        </button>
      </div>
      <div class="flex gap-6">
        <div class="text-center">
          <p class="text-3xl font-bold text-[#0b5447]">{{ saldo.saldo_calculado.dias_disponibles ?? 0 }}</p>
          <p class="text-xs text-gray-500 mt-1">Días disponibles</p>
        </div>
        <div class="text-center">
          <p class="text-3xl font-bold text-gray-400">{{ saldo.saldo_calculado.tomados ?? 0 }}</p>
          <p class="text-xs text-gray-500 mt-1">Días tomados</p>
        </div>
        <div class="text-center">
          <p class="text-xl font-semibold text-gray-500">{{ saldo.saldo_calculado.saldo_inicial ?? 0 }}</p>
          <p class="text-xs text-gray-500 mt-1">Saldo inicial (Excel)</p>
        </div>
        <div class="text-center">
          <p class="text-xl font-semibold text-green-600">+{{ saldo.saldo_calculado.acumulado_a_hoy ?? 0 }}</p>
          <p class="text-xs text-gray-500 mt-1">Acumulado a hoy</p>
        </div>
      </div>

      <!-- Detalle por período -->
      <div v-if="mostrarDetalleSaldo && saldo.detalle.length > 0" class="border-t pt-3">
        <table class="w-full text-xs">
          <thead class="bg-gray-50">
            <tr>
              <th class="text-left px-3 py-2 text-gray-500">Período</th>
              <th class="text-right px-3 py-2 text-gray-500">Por tomar</th>
              <th class="text-right px-3 py-2 text-gray-500">Tomados</th>
              <th class="text-right px-3 py-2 text-gray-500">Disponibles</th>
              <th class="text-right px-3 py-2 text-gray-500">Acumulado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="d in saldo.detalle" :key="d.secuencial" class="border-b">
              <td class="px-3 py-2 font-medium">{{ d.periodo }}</td>
              <td class="px-3 py-2 text-right">{{ d.dias_por_tomar }}</td>
              <td class="px-3 py-2 text-right">{{ d.tomados_normal ?? 0 }}</td>
              <td class="px-3 py-2 text-right font-semibold text-[#0b5447]">{{ d.disponible_normal ?? 0 }}</td>
              <td class="px-3 py-2 text-right">{{ d.acumulado }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else-if="mostrarDetalleSaldo" class="border-t pt-3 text-xs text-gray-400 text-center">
        Sin detalle de períodos registrado
      </div>
    </div>

    <div v-else-if="cargandoSaldo" class="bg-white rounded-xl shadow p-4 text-center text-gray-400 text-sm">
      Cargando saldo...
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow p-4 flex flex-wrap gap-3">
      <select v-model="filtros.estado" @change="cargar"
        class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
        <option value="">Todos los estados</option>
        <option value="PENDIENTE">Pendiente</option>
        <option value="APROBADO">Aprobado</option>
        <option value="NEGADO">Negado</option>
        <option value="ELIMINADO">Eliminado</option>
      </select>
      <input v-model="filtros.fecha_desde" type="date" @change="cargar"
        class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
      <input v-model="filtros.fecha_hasta" type="date" @change="cargar"
        class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
      <button @click="limpiarFiltros"
        class="border rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">
        Limpiar
      </button>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Empleado</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Fecha Inicio</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Fecha Fin</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Todo el día</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Estado</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando">
            <td colspan="6" class="text-center py-8 text-gray-400">Cargando...</td>
          </tr>
          <tr v-else-if="vacaciones.length === 0">
            <td colspan="6" class="text-center py-8 text-gray-400">No hay solicitudes registradas</td>
          </tr>
          <tr v-for="v in vacaciones" :key="v.secuencial_clave" class="border-b hover:bg-gray-50">
            <td class="px-4 py-3 font-medium">
              {{ v.empleado?.apellido_emp }}, {{ v.empleado?.nombre_emp }}
            </td>
            <td class="px-4 py-3 text-gray-600">{{ v.fecha_inicial?.substring(0, 10) }}</td>
            <td class="px-4 py-3 text-gray-600">{{ v.fecha_final?.substring(0, 10) }}</td>
            <td class="px-4 py-3 text-center">
              <span v-if="v.todo_dia === 'SI'" class="text-green-600">✓</span>
              <span v-else class="text-gray-400">—</span>
            </td>
            <td class="px-4 py-3">
              <span :class="colorEstado(v.estado_permiso)"
                class="px-2 py-1 rounded-full text-xs font-medium">
                {{ v.estado_permiso }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex gap-2">
                <button @click="verVacacion(v)"
                  class="text-[#0b5447] hover:underline text-xs font-medium">Ver</button>
                <template v-if="esSupervisorOAdmin && v.estado_permiso === 'PENDIENTE' && v.empleado?.id_emp !== auth.empleado?.id_emp">
                  <button @click="aprobar(v.secuencial_clave)"
                    class="text-green-600 hover:underline text-xs font-medium">Aprobar</button>
                  <button @click="abrirModalNegar(v)"
                    class="text-red-500 hover:underline text-xs font-medium">Negar</button>
                  <button @click="abrirModalEliminar(v)"
                    class="text-gray-500 hover:underline text-xs font-medium">Eliminar</button>
                </template>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Paginación -->
      <div class="flex justify-between items-center px-4 py-3 border-t text-sm text-gray-600">
        <span>Mostrando {{ vacaciones.length }} de {{ total }} solicitudes</span>
        <div class="flex gap-2">
          <button @click="pagina--; cargar()" :disabled="pagina === 1"
            class="px-3 py-1 border rounded-lg disabled:opacity-50 hover:bg-gray-50">Anterior</button>
          <span class="px-3 py-1">{{ pagina }}</span>
          <button @click="pagina++; cargar()" :disabled="pagina >= totalPaginas"
            class="px-3 py-1 border rounded-lg disabled:opacity-50 hover:bg-gray-50">Siguiente</button>
        </div>
      </div>
    </div>

    <!-- Modal Solicitar -->
    <div v-if="modalNuevo" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-lg space-y-4">
        <h2 class="text-lg font-semibold text-gray-700">Solicitar Vacaciones</h2>
        <div class="space-y-4">
          <div class="flex items-center gap-2">
            <input v-model="formNuevo.todo_dia" type="checkbox" id="todo_dia_vac"
              true-value="SI" false-value="NO" class="rounded" />
            <label for="todo_dia_vac" class="text-sm text-gray-600">Todo el día</label>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-600 mb-1">Fecha Inicio *</label>
              <input v-model="formNuevo.fecha_inicial" type="date"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-600 mb-1">Fecha Fin *</label>
              <input v-model="formNuevo.fecha_final" type="date"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
            </div>
          </div>
          <div v-if="formNuevo.todo_dia !== 'SI'" class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-600 mb-1">Hora Desde *</label>
              <input v-model="formNuevo.hora_desde" type="time"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-600 mb-1">Hora Hasta *</label>
              <input v-model="formNuevo.hora_hasta" type="time"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Observaciones</label>
            <textarea v-model="formNuevo.observaciones" rows="3" maxlength="250"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]"></textarea>
          </div>
          <div v-if="errorNuevo" class="text-red-600 text-sm bg-red-50 rounded p-2">{{ errorNuevo }}</div>
          <div class="flex justify-end gap-3 pt-2">
            <button @click="modalNuevo = false"
              class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
            <button @click="guardar" :disabled="guardando"
              class="px-4 py-2 rounded-lg bg-[#0b5447] text-white text-sm hover:bg-[#00372e] disabled:opacity-50">
              {{ guardando ? "Enviando..." : "Solicitar" }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Ver -->
    <div v-if="modalVer" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-lg space-y-4">
        <h2 class="text-lg font-semibold text-gray-700">Detalle de Vacación</h2>
        <dl class="grid grid-cols-2 gap-3 text-sm">
          <div>
            <dt class="text-gray-500">Empleado</dt>
            <dd class="font-medium">{{ seleccionado?.empleado?.apellido_emp }}, {{ seleccionado?.empleado?.nombre_emp }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Departamento</dt>
            <dd class="font-medium">{{ seleccionado?.empleado?.departamento?.nombre_depto }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Fecha Inicio</dt>
            <dd class="font-medium">{{ seleccionado?.fecha_inicial?.substring(0, 10) }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Fecha Fin</dt>
            <dd class="font-medium">{{ seleccionado?.fecha_final?.substring(0, 10) }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Hora Desde</dt>
            <dd class="font-medium">{{ seleccionado?.hora_desde?.substring(11, 16) }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Hora Hasta</dt>
            <dd class="font-medium">{{ seleccionado?.hora_hasta?.substring(11, 16) }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Todo el día</dt>
            <dd class="font-medium">{{ seleccionado?.todo_dia }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Estado</dt>
            <dd>
              <span :class="colorEstado(seleccionado?.estado_permiso)"
                class="px-2 py-1 rounded-full text-xs font-medium">
                {{ seleccionado?.estado_permiso }}
              </span>
            </dd>
          </div>
          <div class="col-span-2">
            <dt class="text-gray-500">Observaciones</dt>
            <dd class="font-medium">{{ seleccionado?.observaciones || "—" }}</dd>
          </div>
          <div v-if="seleccionado?.observacion_negacion" class="col-span-2">
            <dt class="text-gray-500">
              {{ seleccionado?.estado_permiso === 'ELIMINADO' ? 'Motivo de eliminación' : 'Motivo de negación' }}
            </dt>
            <dd class="font-medium text-red-600">{{ seleccionado?.observacion_negacion }}</dd>
          </div>
        </dl>
        <div class="flex justify-end pt-2">
          <button @click="modalVer = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cerrar</button>
        </div>
      </div>
    </div>

    <!-- Modal Eliminar -->
    <div v-if="modalEliminar" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-md space-y-4">
        <h2 class="text-lg font-semibold text-gray-700">Eliminar Solicitud</h2>
        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Motivo de eliminación <span class="text-red-500">*</span></label>
          <textarea v-model="motivoEliminacion" rows="3" maxlength="120"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]"></textarea>
          <p v-if="errorEliminar" class="text-red-500 text-xs mt-1">{{ errorEliminar }}</p>
        </div>
        <div class="flex justify-end gap-3">
          <button @click="modalEliminar = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
          <button @click="confirmarEliminar"
            class="px-4 py-2 rounded-lg bg-gray-600 text-white text-sm hover:bg-gray-700">
            Confirmar Eliminación
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Negar -->
    <div v-if="modalNegar" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-md space-y-4">
        <h2 class="text-lg font-semibold text-gray-700">Negar Vacación</h2>
        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Motivo de negación</label>
          <textarea v-model="motivoNegacion" rows="3" maxlength="120"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]"></textarea>
        </div>
        <div class="flex justify-end gap-3">
          <button @click="modalNegar = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
          <button @click="confirmarNegar"
            class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm hover:bg-red-700">
            Confirmar Negación
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue"
import { useAuthStore } from "@/stores/auth"
import api from "@/services/api"

const auth = useAuthStore()

const vacaciones   = ref([])
const cargando     = ref(false)
const guardando    = ref(false)
const total        = ref(0)
const pagina       = ref(1)
const totalPaginas = ref(1)
const miRol        = ref({ es_supervisor: false, es_admin_th: false })

const saldo               = ref({ cabecera: null, detalle: [] })
const cargandoSaldo       = ref(false)
const mostrarDetalleSaldo = ref(false)

const modalNuevo     = ref(false)
const modalVer       = ref(false)
const modalNegar          = ref(false)
const modalEliminar       = ref(false)
const seleccionado        = ref(null)
const motivoNegacion      = ref("")
const motivoEliminacion   = ref("")
const errorEliminar       = ref("")
const errorNuevo          = ref("")

const filtros = ref({ estado: "", fecha_desde: "", fecha_hasta: "" })

const formNuevo = ref({
  fecha_inicial: "", fecha_final: "",
  hora_desde: "08:00", hora_hasta: "17:00",
  todo_dia: "SI", observaciones: "",
})

const esSupervisorOAdmin = computed(() =>
  miRol.value.es_supervisor || miRol.value.es_admin_th
)

const colorEstado = (estado) => {
  const colores = {
    "PENDIENTE": "bg-yellow-100 text-yellow-700",
    "APROBADO":  "bg-green-100 text-green-700",
    "NEGADO":    "bg-red-100 text-red-700",
    "ELIMINADO": "bg-gray-100 text-gray-700",
  }
  return colores[estado] || "bg-gray-100 text-gray-700"
}

const cargar = async () => {
  cargando.value = true
  try {
    const params = { page: pagina.value, per_page: 15, ...filtros.value }
    const { data } = await api.get("/vacaciones", { params })
    vacaciones.value   = data.data
    total.value        = data.total
    totalPaginas.value = data.last_page
  } catch (e) {
    console.error(e)
  } finally {
    cargando.value = false
  }
}

const cargarSaldo = async () => {
  cargandoSaldo.value = true
  try {
    const { data } = await api.get("/vacaciones/mi-saldo")
    saldo.value = data
  } catch (e) {
    console.error(e)
  } finally {
    cargandoSaldo.value = false
  }
}

const abrirModalNuevo = () => {
  errorNuevo.value = ""
  formNuevo.value = {
    fecha_inicial: "", fecha_final: "",
    hora_desde: "08:00", hora_hasta: "17:00",
    todo_dia: "SI", observaciones: "",
  }
  modalNuevo.value = true
}

const guardar = async () => {
  if (!formNuevo.value.fecha_inicial || !formNuevo.value.fecha_final) {
    errorNuevo.value = "Las fechas son requeridas"
    return
  }
  guardando.value  = true
  errorNuevo.value = ""
  try {
    await api.post("/vacaciones", formNuevo.value)
    modalNuevo.value = false
    cargar()
    cargarSaldo()
  } catch (e) {
    errorNuevo.value = e.response?.data?.message || "Error al solicitar vacaciones"
  } finally {
    guardando.value = false
  }
}

const verVacacion = (v) => {
  seleccionado.value = v
  modalVer.value = true
}

const aprobar = async (id) => {
  if (!confirm("¿Aprobar esta solicitud de vacaciones?")) return
  try {
    await api.patch("/vacaciones/" + id + "/aprobar")
    cargar()
  } catch (e) {
    alert(e.response?.data?.message || "Error al aprobar")
  }
}

const abrirModalNegar = (v) => {
  seleccionado.value   = v
  motivoNegacion.value = ""
  modalNegar.value     = true
}

const confirmarNegar = async () => {
  try {
    await api.patch("/vacaciones/" + seleccionado.value.secuencial_clave + "/negar", {
      observacion_negacion: motivoNegacion.value
    })
    modalNegar.value = false
    cargar()
  } catch (e) {
    alert(e.response?.data?.message || "Error al negar")
  }
}

const abrirModalEliminar = (v) => {
  seleccionado.value      = v
  motivoEliminacion.value = ""
  errorEliminar.value     = ""
  modalEliminar.value     = true
}

const confirmarEliminar = async () => {
  if (!motivoEliminacion.value.trim()) {
    errorEliminar.value = "El motivo de eliminación es obligatorio"
    return
  }
  try {
    await api.delete("/vacaciones/" + seleccionado.value.secuencial_clave, {
      data: { observacion_negacion: motivoEliminacion.value }
    })
    modalEliminar.value = false
    cargar()
  } catch (e) {
    alert(e.response?.data?.message || "Error al eliminar")
  }
}

const limpiarFiltros = () => {
  filtros.value = { estado: "", fecha_desde: "", fecha_hasta: "" }
  pagina.value  = 1
  cargar()
}

onMounted(async () => {
  const { data } = await api.get("/vacaciones/mi-rol")
  miRol.value = data
  cargar()
  cargarSaldo()
})
</script>
