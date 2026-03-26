<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-800">Permisos</h1>
      <div class="flex gap-2">
        <button v-if="esSupervisorOAdmin" @click="abrirEstadistica"
          class="bg-[#0b5447] text-white px-4 py-2 rounded-lg hover:bg-[#00372e] text-sm font-medium">
          📊 Estadística
        </button>
        <button @click="abrirModalNuevo"
          class="bg-[#0b5447] text-white px-4 py-2 rounded-lg hover:bg-[#00372e] text-sm font-medium">
          + Solicitar Permiso
        </button>
      </div>
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
      <label class="flex items-center gap-2 cursor-pointer select-none text-sm text-gray-700 border rounded-lg px-3 py-2 hover:bg-gray-50"
        :class="filtros.descontable === 'SI' ? 'border-[#579186] bg-[#f0faf8]' : ''">
        <input type="checkbox" :checked="filtros.descontable === 'SI'"
          @change="filtros.descontable = filtros.descontable === 'SI' ? '' : 'SI'; cargar()"
          class="accent-[#0b5447]" />
        Descontables
      </label>
      <label class="flex items-center gap-2 cursor-pointer select-none text-sm text-gray-700 border rounded-lg px-3 py-2 hover:bg-gray-50"
        :class="filtros.descontable === 'NO' ? 'border-[#579186] bg-[#f0faf8]' : ''">
        <input type="checkbox" :checked="filtros.descontable === 'NO'"
          @change="filtros.descontable = filtros.descontable === 'NO' ? '' : 'NO'; cargar()"
          class="accent-[#0b5447]" />
        No descontables
      </label>
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
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Razon</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Tipo</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Desde</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Hasta</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Todo el dia</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Estado</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando">
            <td colspan="7" class="text-center py-8 text-gray-400">Cargando...</td>
          </tr>
          <tr v-else-if="permisos.length === 0">
            <td colspan="7" class="text-center py-8 text-gray-400">No hay permisos registrados</td>
          </tr>
          <tr v-for="p in permisos" :key="p.secuencial_clave" class="border-b hover:bg-gray-50">
            <td class="px-4 py-3 font-medium">
              {{ p.empleado?.apellido_emp }}, {{ p.empleado?.nombre_emp }}
            </td>
            <td class="px-4 py-3 text-gray-600">{{ p.razon_permiso?.descripcion?.trim() || p.razon }}</td>
            <td class="px-4 py-3">
              <span v-if="p.tipo_horario" :class="colorTipoHorario(p.tipo_horario)"
                class="px-2 py-0.5 rounded-full text-xs font-medium">
                {{ p.tipo_horario }}
              </span>
              <span v-else class="text-gray-300 text-xs">—</span>
            </td>
            <td class="px-4 py-3 text-gray-600">{{ p.fecha_desde?.substring(0, 10) }}</td>
            <td class="px-4 py-3 text-gray-600">{{ p.fecha_hasta?.substring(0, 10) }}</td>
            <td class="px-4 py-3 text-center">
              <span v-if="p.todo_dia === 'SI'" class="text-green-600">✓</span>
              <span v-else class="text-gray-400">—</span>
            </td>
            <td class="px-4 py-3">
              <span :class="colorEstado(p.estado_permiso)"
                class="px-2 py-1 rounded-full text-xs font-medium">
                {{ p.estado_permiso }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex gap-2">
                <button @click="verPermiso(p)"
                  class="text-[#0b5447] hover:underline text-xs font-medium">Ver</button>
                <template v-if="esSupervisorOAdmin && p.estado_permiso === 'PENDIENTE' && p.empleado?.id_emp !== auth.empleado?.id_emp">
                  <button @click="aprobar(p.secuencial_clave)"
                    class="text-green-600 hover:underline text-xs font-medium">Aprobar</button>
                  <button @click="abrirModalNegar(p)"
                    class="text-red-500 hover:underline text-xs font-medium">Negar</button>
                  <button @click="abrirModalEliminar(p)"
                    class="text-gray-500 hover:underline text-xs font-medium">Eliminar</button>
                </template>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Paginacion -->
      <div class="flex justify-between items-center px-4 py-3 border-t text-sm text-gray-600">
        <span>Mostrando {{ permisos.length }} de {{ total }} permisos</span>
        <div class="flex gap-2">
          <button @click="pagina--; cargar()" :disabled="pagina === 1"
            class="px-3 py-1 border rounded-lg disabled:opacity-50 hover:bg-gray-50">Anterior</button>
          <span class="px-3 py-1">{{ pagina }}</span>
          <button @click="pagina++; cargar()" :disabled="pagina >= totalPaginas"
            class="px-3 py-1 border rounded-lg disabled:opacity-50 hover:bg-gray-50">Siguiente</button>
        </div>
      </div>
    </div>

    <!-- Modal Solicitar Permiso -->
    <div v-if="modalNuevo" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-lg space-y-4">
        <h2 class="text-lg font-semibold text-gray-700">Solicitar Permiso</h2>
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Razon *</label>
            <select v-model="formNuevo.sec_permiso"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
              <option value="">Seleccionar razon...</option>
              <option v-for="r in razones" :key="r.secuencial" :value="r.secuencial">
                {{ r.descripcion.trim() }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Tipo de permiso *</label>
            <select v-model="formNuevo.tipo_horario"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
              <option value="">Seleccionar tipo...</option>
              <option value="ENTRADA">Entrada — atraso a la entrada</option>
              <option value="ENTRE JORNADA">Entre jornada — lunch, cita médica, etc.</option>
              <option value="SALIDA">Salida — salida anticipada</option>
            </select>
          </div>
          <div class="flex items-center gap-2">
            <input v-model="formNuevo.todo_dia" type="checkbox" id="todo_dia"
              true-value="SI" false-value="NO" class="rounded" />
            <label for="todo_dia" class="text-sm text-gray-600">Todo el dia</label>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-600 mb-1">Fecha Desde *</label>
              <input v-model="formNuevo.fecha_desde" type="date"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-600 mb-1">Fecha Hasta *</label>
              <input v-model="formNuevo.fecha_hasta" type="date"
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
            <button type="button" @click="modalNuevo = false"
              class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
            <button type="button" @click="guardarPermiso" :disabled="guardando"
              class="px-4 py-2 rounded-lg bg-[#0b5447] text-white text-sm hover:bg-[#00372e] disabled:opacity-50">
              {{ guardando ? "Enviando..." : "Solicitar" }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Ver Permiso -->
    <div v-if="modalVer" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-lg space-y-4">
        <h2 class="text-lg font-semibold text-gray-700">Detalle del Permiso</h2>
        <dl class="grid grid-cols-2 gap-3 text-sm">
          <div>
            <dt class="text-gray-500">Empleado</dt>
            <dd class="font-medium">{{ permisoSeleccionado?.empleado?.apellido_emp }}, {{ permisoSeleccionado?.empleado?.nombre_emp }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Departamento</dt>
            <dd class="font-medium">{{ permisoSeleccionado?.empleado?.departamento?.nombre_depto }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Razon</dt>
            <dd class="font-medium">{{ permisoSeleccionado?.razon_permiso?.descripcion?.trim() }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Estado</dt>
            <dd>
              <span :class="colorEstado(permisoSeleccionado?.estado_permiso)"
                class="px-2 py-1 rounded-full text-xs font-medium">
                {{ permisoSeleccionado?.estado_permiso }}
              </span>
            </dd>
          </div>
          <div>
            <dt class="text-gray-500">Fecha Desde</dt>
            <dd class="font-medium">{{ permisoSeleccionado?.fecha_desde?.substring(0, 10) }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Fecha Hasta</dt>
            <dd class="font-medium">{{ permisoSeleccionado?.fecha_hasta?.substring(0, 10) }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Hora Desde</dt>
            <dd class="font-medium">{{ permisoSeleccionado?.hora_desde?.substring(11, 16) }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Hora Hasta</dt>
            <dd class="font-medium">{{ permisoSeleccionado?.hora_hasta?.substring(11, 16) }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Todo el dia</dt>
            <dd class="font-medium">{{ permisoSeleccionado?.todo_dia }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Descontable</dt>
            <dd class="font-medium">{{ permisoSeleccionado?.descontable }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Tipo de permiso</dt>
            <dd>
              <span v-if="permisoSeleccionado?.tipo_horario"
                :class="colorTipoHorario(permisoSeleccionado?.tipo_horario)"
                class="px-2 py-0.5 rounded-full text-xs font-medium">
                {{ permisoSeleccionado?.tipo_horario }}
              </span>
              <span v-else class="text-gray-400">—</span>
            </dd>
          </div>
          <div class="col-span-2">
            <dt class="text-gray-500">Observaciones</dt>
            <dd class="font-medium">{{ permisoSeleccionado?.observaciones || "—" }}</dd>
          </div>
          <div v-if="permisoSeleccionado?.observacion_negacion" class="col-span-2">
            <dt class="text-gray-500">
              {{ permisoSeleccionado?.estado_permiso === 'ELIMINADO' ? 'Motivo de eliminación' : 'Motivo de negación' }}
            </dt>
            <dd class="font-medium text-red-600">{{ permisoSeleccionado?.observacion_negacion }}</dd>
          </div>
        </dl>
        <div class="flex justify-end pt-2">
          <button @click="modalVer = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cerrar</button>
        </div>
      </div>
    </div>

    <!-- Modal Negar -->
    <div v-if="modalNegar" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-md space-y-4">
        <h2 class="text-lg font-semibold text-gray-700">Negar Permiso</h2>
        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Motivo de negacion</label>
          <textarea v-model="motivoNegacion" rows="3" maxlength="120"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]"></textarea>
        </div>
        <div class="flex justify-end gap-3">
          <button @click="modalNegar = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
          <button @click="confirmarNegar"
            class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm hover:bg-red-700">
            Confirmar Negacion
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Eliminar -->
    <div v-if="modalEliminar" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-md space-y-4">
        <h2 class="text-lg font-semibold text-gray-700">Eliminar Permiso</h2>
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

    <!-- Modal Estadística -->
    <div v-if="modalEstadistica" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-3xl space-y-4">
        <div class="flex justify-between items-center">
          <h2 class="text-lg font-semibold text-gray-700">📊 Estadística por Supervisor</h2>
          <button @click="modalEstadistica = false" class="text-gray-400 hover:text-gray-600 text-xl">✕</button>
        </div>
        <div class="flex gap-3 items-end">
          <div>
            <label class="block text-xs text-gray-500 mb-1">Fecha Desde</label>
            <input v-model="statFechas.desde" type="date" class="border rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs text-gray-500 mb-1">Fecha Hasta</label>
            <input v-model="statFechas.hasta" type="date" class="border rounded-lg px-3 py-2 text-sm" />
          </div>
          <button @click="cargarEstadistica" :disabled="cargandoStat"
            class="bg-[#0b5447] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#00372e] disabled:opacity-50">
            {{ cargandoStat ? "Buscando..." : "Buscar" }}
          </button>
        </div>
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-left px-4 py-3 text-gray-600">Supervisor</th>
              <th class="text-center px-4 py-3 text-green-600">Aprobados</th>
              <th class="text-center px-4 py-3 text-red-500">Negados</th>
              <th class="text-center px-4 py-3 text-gray-500">Eliminados</th>
              <th class="text-center px-4 py-3 text-[#0b5447]">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="cargandoStat">
              <td colspan="5" class="text-center py-6 text-gray-400">Cargando...</td>
            </tr>
            <tr v-else-if="estadisticas.length === 0">
              <td colspan="5" class="text-center py-6 text-gray-400">Sin datos para el rango seleccionado</td>
            </tr>
            <tr v-for="s in estadisticas" :key="s.id_supervisor" class="border-b hover:bg-gray-50">
              <td class="px-4 py-3 font-medium">{{ s.nombre_supervisor }}</td>
              <td class="px-4 py-3 text-center">
                <span class="bg-green-100 text-green-700 px-2 py-1 rounded-full text-xs">{{ s.aprobados }}</span>
              </td>
              <td class="px-4 py-3 text-center">
                <span class="bg-red-100 text-red-700 px-2 py-1 rounded-full text-xs">{{ s.negados }}</span>
              </td>
              <td class="px-4 py-3 text-center">
                <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded-full text-xs">{{ s.eliminados }}</span>
              </td>
              <td class="px-4 py-3 text-center">
                <span class="bg-blue-100 text-[#0b5447] px-2 py-1 rounded-full text-xs font-bold">{{ s.total }}</span>
              </td>
            </tr>
            <tr v-if="estadisticas.length > 0" class="bg-gray-50 font-semibold">
              <td class="px-4 py-3">TOTAL</td>
              <td class="px-4 py-3 text-center text-green-600">{{ estadisticas.reduce((a, s) => a + s.aprobados, 0) }}</td>
              <td class="px-4 py-3 text-center text-red-500">{{ estadisticas.reduce((a, s) => a + s.negados, 0) }}</td>
              <td class="px-4 py-3 text-center text-gray-500">{{ estadisticas.reduce((a, s) => a + s.eliminados, 0) }}</td>
              <td class="px-4 py-3 text-center text-[#0b5447]">{{ estadisticas.reduce((a, s) => a + s.total, 0) }}</td>
            </tr>
          </tbody>
        </table>
        <div class="flex justify-end pt-2">
          <button @click="modalEstadistica = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cerrar</button>
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

const permisos            = ref([])
const razones             = ref([])
const cargando            = ref(false)
const guardando           = ref(false)
const modalNuevo          = ref(false)
const modalVer            = ref(false)
const modalNegar          = ref(false)
const modalEliminar       = ref(false)
const permisoSeleccionado = ref(null)
const motivoNegacion      = ref("")
const motivoEliminacion   = ref("")
const errorEliminar       = ref("")
const errorNuevo          = ref("")
const total               = ref(0)
const pagina              = ref(1)
const totalPaginas        = ref(1)
const miRol               = ref({ es_supervisor: false, es_admin_th: false })

const filtros = ref({ estado: "", fecha_desde: "", fecha_hasta: "", descontable: "" })

const formNuevo = ref({
  sec_permiso: "", tipo_horario: "", fecha_desde: "", fecha_hasta: "",
  hora_desde: "08:00", hora_hasta: "17:00",
  todo_dia: "NO", observaciones: "",
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
    const { data } = await api.get("/permisos", { params })
    permisos.value     = data.data
    total.value        = data.total
    totalPaginas.value = data.last_page
  } catch (e) {
    console.error(e)
  } finally {
    cargando.value = false
  }
}

const abrirModalNuevo = async () => {
  errorNuevo.value = ""
  formNuevo.value = {
    sec_permiso: "", tipo_horario: "", fecha_desde: "", fecha_hasta: "",
    hora_desde: "08:00", hora_hasta: "17:00",
    todo_dia: "NO", observaciones: "",
  }
  if (razones.value.length === 0) {
    const { data } = await api.get("/permisos/razones")
    razones.value = data
  }
  modalNuevo.value = true
}

const guardarPermiso = async () => {
  if (!formNuevo.value.sec_permiso)   { errorNuevo.value = "Selecciona una razon"; return }
  if (!formNuevo.value.tipo_horario)  { errorNuevo.value = "Selecciona el tipo de permiso"; return }
  guardando.value  = true
  errorNuevo.value = ""
  try {
    await api.post("/permisos", formNuevo.value)
    modalNuevo.value = false
    cargar()
  } catch (e) {
    errorNuevo.value = e.response?.data?.message || "Error al solicitar permiso"
  } finally {
    guardando.value = false
  }
}

const verPermiso = (p) => {
  permisoSeleccionado.value = p
  modalVer.value = true
}

const aprobar = async (id) => {
  if (!confirm("¿Aprobar este permiso?")) return
  try {
    await api.patch("/permisos/" + id + "/aprobar")
    cargar()
  } catch (e) {
    alert(e.response?.data?.message || "Error al aprobar")
  }
}

const abrirModalNegar = (p) => {
  permisoSeleccionado.value = p
  motivoNegacion.value = ""
  modalNegar.value = true
}

const confirmarNegar = async () => {
  try {
    await api.patch("/permisos/" + permisoSeleccionado.value.secuencial_clave + "/negar", {
      observacion_negacion: motivoNegacion.value
    })
    modalNegar.value = false
    cargar()
  } catch (e) {
    alert(e.response?.data?.message || "Error al negar")
  }
}

const abrirModalEliminar = (p) => {
  permisoSeleccionado.value = p
  motivoEliminacion.value   = ""
  errorEliminar.value       = ""
  modalEliminar.value       = true
}

const confirmarEliminar = async () => {
  if (!motivoEliminacion.value.trim()) {
    errorEliminar.value = "El motivo de eliminación es obligatorio"
    return
  }
  try {
    await api.delete("/permisos/" + permisoSeleccionado.value.secuencial_clave, {
      data: { observacion_negacion: motivoEliminacion.value }
    })
    modalEliminar.value = false
    cargar()
  } catch (e) {
    alert(e.response?.data?.message || "Error al eliminar")
  }
}

const colorTipoHorario = (tipo) => {
  const colores = {
    "ENTRADA":       "bg-green-100 text-green-700",
    "ENTRE JORNADA": "bg-blue-100 text-[#0b5447]",
    "SALIDA":        "bg-orange-100 text-orange-700",
  }
  return colores[tipo] || "bg-gray-100 text-gray-700"
}

const limpiarFiltros = () => {
  filtros.value = { estado: "", fecha_desde: "", fecha_hasta: "", descontable: "" }
  pagina.value  = 1
  cargar()
}

const modalEstadistica = ref(false)
const estadisticas     = ref([])
const cargandoStat     = ref(false)
const statFechas       = ref({
  desde: new Date().toISOString().substring(0, 8) + "01",
  hasta: new Date().toISOString().substring(0, 10)
})

const abrirEstadistica = () => {
  estadisticas.value = []
  modalEstadistica.value = true
}

const cargarEstadistica = async () => {
  if (!statFechas.value.desde || !statFechas.value.hasta) return
  cargandoStat.value = true
  try {
    const { data } = await api.get("/permisos/estadistica", {
      params: { fecha_desde: statFechas.value.desde, fecha_hasta: statFechas.value.hasta }
    })
    estadisticas.value = data
  } catch (e) {
    alert(e.response?.data?.message || "Error")
  } finally {
    cargandoStat.value = false
  }
}

onMounted(async () => {
  const { data } = await api.get("/permisos/mi-rol")
  miRol.value = data
  cargar()
})
</script>
