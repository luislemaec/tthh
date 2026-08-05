<template>
  <div class="space-y-6 max-w-5xl mx-auto">
    <div class="flex items-center gap-3">
      <router-link to="/acciones-personal" class="text-gray-400 hover:text-gray-600">← Volver</router-link>
      <h1 class="text-2xl font-bold text-gray-800">Nueva Acción de Personal</h1>
    </div>

    <form @submit.prevent="guardar" class="space-y-6">

      <!-- Cabecera -->
      <div class="bg-white rounded-xl shadow p-6 space-y-4">
        <h2 class="text-lg font-semibold text-gray-700 border-b pb-2">Datos de la Acción</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Tipo de Acción *</label>
            <select v-model="form.tipo_accion" required
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
              <option value="">Seleccionar...</option>
              <option value="INGRESO">Ingreso</option>
              <option value="ENCARGO">Encargo de Funciones</option>
              <option value="SUBROGACION">Subrogación</option>
              <option value="VACACIONES">Vacaciones</option>
              <option value="DESTITUCION">Destitución</option>
              <option value="CESACION DE FUNCIONES">Cesación de Funciones</option>
              <option value="COMISION DE SERVICIOS">Comisión de Servicios</option>
              <option value="REINGRESO">Reingreso</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Fecha de Elaboración *</label>
            <input v-model="form.fecha_elaboracion" type="date" required
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Nro. Acción</label>
            <input type="text" disabled placeholder="Se genera automáticamente"
              class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-400" />
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">{{ labelFechaInicio }}</label>
            <input v-model="form.fecha_inicio" type="date" required
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>

          <div v-if="conFechaFin">
            <template v-if="form.tipo_accion === 'ENCARGO'">
              <div class="flex items-center justify-between mb-1">
                <label class="block text-sm font-medium text-gray-600">Vigente hasta</label>
                <label class="flex items-center gap-1.5 text-xs text-amber-700 cursor-pointer select-none">
                  <input type="checkbox" v-model="hastaNuevaOrden" class="rounded" />
                  Hasta nueva orden
                </label>
              </div>
              <input v-if="!hastaNuevaOrden" v-model="form.fecha_fin" type="date"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
              <div v-else class="w-full border border-amber-300 bg-amber-50 rounded-lg px-3 py-2 text-sm text-amber-700">
                Sin fecha definida — se cierra manualmente
              </div>
            </template>
            <template v-else>
              <label class="block text-sm font-medium text-gray-600 mb-1">{{ labelFechaFin }} *</label>
              <input v-model="form.fecha_fin" type="date" :required="fechaFinRequerida"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
            </template>
          </div>
        </div>
      </div>

      <!-- Empleado -->
      <div class="bg-white rounded-xl shadow p-6 space-y-4">
        <h2 class="text-lg font-semibold text-gray-700 border-b pb-2">{{ tituloEmpleado }}</h2>
        <div class="flex gap-3">
          <input v-model="busquedaEmp" type="text" placeholder="Buscar por nombre o cédula..."
            class="flex-1 border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]"
            @input="buscarEmpleados" />
        </div>

        <div v-if="resultadosEmp.length" class="border rounded-lg overflow-hidden">
          <div v-for="e in resultadosEmp" :key="e.id_emp"
            @click="seleccionarEmpleado(e)"
            class="px-4 py-2 hover:bg-blue-50 cursor-pointer border-b last:border-b-0 text-sm">
            <span class="font-medium">{{ e.apellido_emp }}, {{ e.nombre_emp }}</span>
            <span class="text-gray-400 ml-2">{{ e.identificacion }}</span>
            <span class="text-gray-500 ml-2">— {{ e.cargo_empleado }}</span>
          </div>
        </div>

        <!-- INGRESO / REINGRESO: solo muestra nombre/CI, no situación actual -->
        <div v-if="empleadoSeleccionado && sinActual" class="bg-green-50 border border-green-200 rounded-lg p-4">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm font-semibold text-green-800">{{ empleadoSeleccionado.apellido_emp }}, {{ empleadoSeleccionado.nombre_emp }}</p>
              <p class="text-xs text-green-600 mt-0.5">CI: {{ empleadoSeleccionado.identificacion }} — situación propuesta cargada desde la ficha</p>
            </div>
            <button type="button" @click="limpiarEmpleado" class="text-red-400 hover:text-red-600 text-xs">✕ Quitar</button>
          </div>
        </div>

        <!-- Otros tipos: muestra situación actual completa -->
        <div v-else-if="empleadoSeleccionado && !sinActual" class="bg-gray-50 rounded-lg p-4">
          <div class="flex items-center justify-between mb-3">
            <p class="text-sm font-semibold text-gray-700">Situación Actual — cargada automáticamente</p>
            <button type="button" @click="limpiarEmpleado" class="text-red-400 hover:text-red-600 text-xs">✕ Quitar</button>
          </div>
          <dl class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
            <div><dt class="text-gray-500 text-xs">Empleado</dt><dd class="font-medium">{{ empleadoSeleccionado.apellido_emp }}, {{ empleadoSeleccionado.nombre_emp }}</dd></div>
            <div><dt class="text-gray-500 text-xs">Cargo actual</dt><dd class="font-medium">{{ empleadoSeleccionado.cargo_empleado || '—' }}</dd></div>
            <div><dt class="text-gray-500 text-xs">Grupo ocupacional</dt><dd class="font-medium">{{ empleadoSeleccionado.grupo_ocupacional || '—' }}</dd></div>
            <div><dt class="text-gray-500 text-xs">Grado</dt><dd class="font-medium">{{ empleadoSeleccionado.nivel || '—' }}</dd></div>
            <div><dt class="text-gray-500 text-xs">Remuneración</dt><dd class="font-medium">${{ Number(empleadoSeleccionado.sueldo || 0).toFixed(2) }}</dd></div>
            <div><dt class="text-gray-500 text-xs">Proceso institucional</dt><dd class="font-medium">{{ empleadoSeleccionado.proceso_institucional || '—' }}</dd></div>
            <div class="sm:col-span-3"><dt class="text-gray-500 text-xs">Partida presupuestaria</dt><dd class="font-mono text-xs font-medium">{{ empleadoSeleccionado.partida_presupuestaria ? (empleadoSeleccionado.partida_presupuestaria + (empleadoSeleccionado.partida_individual ? `-${empleadoSeleccionado.partida_individual}` : '')) : '—' }}</dd></div>
          </dl>
        </div>
      </div>

      <!-- Situación Propuesta -->
      <div v-if="conPropuesta" class="bg-white rounded-xl shadow p-6 space-y-4">
        <h2 class="text-lg font-semibold text-gray-700 border-b pb-2">{{ tituloPropuesta }}</h2>

        <!-- Buscador titular (solo ENCARGO / SUBROGACION) -->
        <div v-if="conTitular">
          <label class="block text-sm font-medium text-gray-600 mb-1">
            Buscar titular del cargo <span class="text-gray-400 font-normal">(opcional — autocompleta los campos)</span>
          </label>
          <input v-model="busquedaTitular" type="text" placeholder="Buscar empleado titular por nombre o cédula..."
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]"
            @input="buscarTitular" />
          <div v-if="resultadosTitular.length" class="border rounded-lg overflow-hidden mt-1">
            <div v-for="e in resultadosTitular" :key="e.id_emp"
              @click="seleccionarTitular(e)"
              class="px-4 py-2 hover:bg-purple-50 cursor-pointer border-b last:border-b-0 text-sm">
              <span class="font-medium">{{ e.apellido_emp }}, {{ e.nombre_emp }}</span>
              <span class="text-gray-400 ml-2">{{ e.identificacion }}</span>
              <span class="text-gray-500 ml-2">— {{ e.cargo_empleado }}</span>
            </div>
          </div>
          <div v-if="titularSeleccionado" class="mt-2 flex items-center gap-2 text-xs text-purple-700 bg-purple-50 px-3 py-2 rounded-lg">
            Datos cargados desde: <strong>{{ titularSeleccionado.apellido_emp }}, {{ titularSeleccionado.nombre_emp }}</strong>
            <button type="button" @click="limpiarTitular" class="ml-auto text-red-400 hover:text-red-600">✕ Limpiar</button>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-600 mb-1">Denominación del Puesto *</label>
            <input v-model="form.propuesto_cargo" type="text" required
              placeholder="Ej: Director de Tecnologías de la Información y Comunicación"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Grupo Ocupacional</label>
            <input v-model="form.propuesto_grupo_ocup" type="text"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Grado</label>
            <input v-model="form.propuesto_grado" type="number" min="1"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Remuneración Mensual *</label>
            <input v-model="form.propuesto_remuneracion" type="number" step="0.01" min="0" required
              @input="calcularDiferencial"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Proceso Institucional</label>
            <select v-model="form.propuesto_proceso_inst"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
              <option value="">Seleccionar...</option>
              <option value="SUSTANTIVO">SUSTANTIVO</option>
              <option value="ADJETIVO">ADJETIVO</option>
              <option value="GOBERNANTE">GOBERNANTE</option>
            </select>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-600 mb-1">Partida Presupuestaria</label>
            <input v-model="form.propuesto_partida" type="text"
              class="w-full border rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
        </div>

        <!-- Diferencial y monto proporcional -->
        <div v-if="diferencial !== null" class="space-y-2">
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 flex items-center gap-3">
            <span class="text-blue-700 text-sm font-medium">Diferencial salarial mensual:</span>
            <span class="text-blue-900 font-bold text-lg">${{ diferencial.toFixed(2) }}</span>
            <span v-if="diferencial === 0" class="text-xs text-blue-500">(no aplica diferencial)</span>
          </div>
          <div v-if="montoProporacional" class="bg-green-50 border border-green-200 rounded-lg p-3 flex flex-wrap items-center gap-3">
            <span class="text-green-700 text-sm font-medium">Monto proporcional:</span>
            <span class="text-green-900 font-bold text-lg">${{ montoProporacional.monto.toFixed(2) }}</span>
            <span class="text-xs text-green-600">
              ({{ montoProporacional.dias }} días × ${{ (diferencial / 30).toFixed(4) }}/día)
            </span>
          </div>
        </div>
      </div>

      <!-- Motivación -->
      <div class="bg-white rounded-xl shadow p-6 space-y-3">
        <h2 class="text-lg font-semibold text-gray-700 border-b pb-2">Motivación / Resolución</h2>
        <p class="text-xs text-gray-400">Ingrese el texto completo de la resolución. Use el botón <strong>N</strong> para aplicar negrita al texto seleccionado.</p>
        <TipTapEditor v-model="form.motivacion" minHeight="180px" />
      </div>

      <!-- Especificación (visible cuando aplica) -->
      <div v-if="conEspecificacion" class="bg-white rounded-xl shadow p-6 space-y-3">
        <h2 class="text-lg font-semibold text-gray-700 border-b pb-2">Especificación</h2>
        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">
            En caso de requerir especificación de lo seleccionado
          </label>
          <input v-model="form.especificacion" type="text"
            style="text-transform:uppercase"
            placeholder="Ej: COMISIÓN DE SERVICIOS SIN REMUNERACIÓN"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
        </div>
      </div>

      <!-- Firmantes -->
      <div class="bg-white rounded-xl shadow p-6 space-y-4">
        <h2 class="text-lg font-semibold text-gray-700 border-b pb-2">Responsables de Aprobación</h2>
        <p class="text-xs text-gray-400">Pre-llenado desde la configuración global. Puede modificar para esta acción específica.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Nombre — Responsable de Talento Humano</label>
            <input v-model="form.firmante_th_nombre" type="text"
              style="text-transform:uppercase"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Cargo — Responsable de Talento Humano</label>
            <input v-model="form.firmante_th_cargo" type="text"
              style="text-transform:uppercase"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Nombre — Autoridad Nominadora</label>
            <input v-model="form.firmante_autoridad_nombre" type="text"
              style="text-transform:uppercase"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Cargo — Autoridad Nominadora</label>
            <input v-model="form.firmante_autoridad_cargo" type="text"
              style="text-transform:uppercase"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Medio</label>
            <select v-model="form.medio"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
              <option value="DIGITAL">DIGITAL</option>
              <option value="MANUAL">MANUAL</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Error -->
      <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
        {{ error }}
      </div>

      <!-- Botones -->
      <div class="flex justify-end gap-3">
        <router-link to="/acciones-personal"
          class="px-5 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">
          Cancelar
        </router-link>
        <button type="submit" :disabled="guardando || !form.id_emp"
          class="px-5 py-2 rounded-lg bg-[#0b5447] text-white text-sm font-medium hover:bg-[#00372e] disabled:opacity-50">
          {{ guardando ? "Guardando..." : "Crear Acción de Personal" }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from "vue"
import { useRouter } from "vue-router"
import api from "@/services/api"
import TipTapEditor from "@/components/TipTapEditor.vue"

const router = useRouter()

// Reglas por tipo
const TIPOS_CON_PROPUESTA    = ['ENCARGO', 'SUBROGACION', 'INGRESO', 'REINGRESO']
const TIPOS_SIN_ACTUAL       = ['INGRESO', 'REINGRESO']
const TIPOS_CON_TITULAR      = ['ENCARGO', 'SUBROGACION']
const TIPOS_CON_FECHA_FIN    = ['ENCARGO', 'SUBROGACION', 'VACACIONES', 'COMISION DE SERVICIOS']
const TIPOS_FECHA_FIN_REQ    = ['SUBROGACION', 'VACACIONES', 'COMISION DE SERVICIOS']
const TIPOS_CON_ESPECIFICACION = ['COMISION DE SERVICIOS', 'REINGRESO']

const form = ref({
  tipo_accion:               "",
  fecha_elaboracion:         new Date().toISOString().substring(0, 10),
  id_emp:                    "",
  id_emp_titular:            "",
  fecha_inicio:              "",
  fecha_fin:                 "",
  motivacion:                "",
  propuesto_cargo:           "",
  propuesto_grupo_ocup:      "",
  propuesto_grado:           "",
  propuesto_remuneracion:    "",
  propuesto_partida:         "",
  propuesto_proceso_inst:    "",
  firmante_th_nombre:        "",
  firmante_th_cargo:         "",
  firmante_autoridad_nombre: "",
  firmante_autoridad_cargo:  "",
  medio:                     "DIGITAL",
  especificacion:            "",
})

const hastaNuevaOrden      = ref(false)
const busquedaEmp          = ref("")
const resultadosEmp        = ref([])
const empleadoSeleccionado = ref(null)
const busquedaTitular      = ref("")
const resultadosTitular    = ref([])
const titularSeleccionado  = ref(null)
const diferencial          = ref(null)
const guardando            = ref(false)
const error                = ref("")
let busquedaTimer          = null
let titularTimer           = null

// Computed: reglas por tipo
const conPropuesta      = computed(() => TIPOS_CON_PROPUESTA.includes(form.value.tipo_accion))
const sinActual         = computed(() => TIPOS_SIN_ACTUAL.includes(form.value.tipo_accion))
const conTitular        = computed(() => TIPOS_CON_TITULAR.includes(form.value.tipo_accion))
const conFechaFin       = computed(() => TIPOS_CON_FECHA_FIN.includes(form.value.tipo_accion))
const fechaFinRequerida = computed(() => TIPOS_FECHA_FIN_REQ.includes(form.value.tipo_accion))
const conEspecificacion = computed(() => TIPOS_CON_ESPECIFICACION.includes(form.value.tipo_accion))

// Labels dinámicos
const labelFechaInicio = computed(() => {
  switch (form.value.tipo_accion) {
    case 'INGRESO':               return 'Fecha de Posesión *'
    case 'DESTITUCION':           return 'Fecha de Destitución *'
    case 'CESACION DE FUNCIONES': return 'Fecha de Cesación *'
    case 'COMISION DE SERVICIOS': return 'Fecha de Inicio de Comisión *'
    case 'REINGRESO':             return 'Fecha de Reingreso *'
    default:                      return 'Vigente desde *'
  }
})

const labelFechaFin = computed(() =>
  form.value.tipo_accion === 'VACACIONES' ? 'Fecha de retorno' : 'Vigente hasta'
)

const tituloEmpleado = computed(() => {
  switch (form.value.tipo_accion) {
    case 'INGRESO':               return 'Servidor público que ingresa'
    case 'DESTITUCION':
    case 'CESACION DE FUNCIONES': return 'Servidor público afectado'
    case 'VACACIONES':            return 'Empleado que sale de vacaciones'
    case 'COMISION DE SERVICIOS': return 'Servidor en comisión'
    case 'REINGRESO':             return 'Servidor que regresa'
    default:                      return 'Empleado que recibe el encargo / subrogación'
  }
})

const tituloPropuesta = computed(() => {
  if (form.value.tipo_accion === 'INGRESO')   return 'Situación Propuesta (cargo de ingreso)'
  if (form.value.tipo_accion === 'REINGRESO') return 'Situación Propuesta (cargo de reingreso)'
  return 'Situación Propuesta (cargo a encargar/subrogar)'
})

// Monto proporcional: solo con diferencial > 0 y ambas fechas
const montoProporacional = computed(() => {
  if (!diferencial.value || diferencial.value <= 0) return null
  if (!form.value.fecha_inicio || !form.value.fecha_fin) return null
  const inicio = new Date(form.value.fecha_inicio)
  const fin    = new Date(form.value.fecha_fin)
  const dias   = Math.round((fin - inicio) / (1000 * 60 * 60 * 24)) + 1
  if (dias <= 0) return null
  return { monto: (diferencial.value / 30) * dias, dias }
})

// Al cambiar tipo: resetear campos que no aplican
watch(() => form.value.tipo_accion, () => {
  hastaNuevaOrden.value  = false
  form.value.fecha_fin   = ""
  if (!TIPOS_CON_PROPUESTA.includes(form.value.tipo_accion)) {
    form.value.propuesto_cargo        = ""
    form.value.propuesto_grupo_ocup   = ""
    form.value.propuesto_grado        = ""
    form.value.propuesto_remuneracion = ""
    form.value.propuesto_partida      = ""
    form.value.propuesto_proceso_inst = ""
    diferencial.value = null
  }
  if (!TIPOS_CON_TITULAR.includes(form.value.tipo_accion)) {
    titularSeleccionado.value = null
    form.value.id_emp_titular = ""
    busquedaTitular.value     = ""
    resultadosTitular.value   = []
  }
  if (!TIPOS_CON_ESPECIFICACION.includes(form.value.tipo_accion)) {
    form.value.especificacion = ""
  }
})

watch(hastaNuevaOrden, (val) => {
  if (val) form.value.fecha_fin = ""
})

const buscarEmpleados = () => {
  clearTimeout(busquedaTimer)
  if (busquedaEmp.value.length < 2) { resultadosEmp.value = []; return }
  busquedaTimer = setTimeout(async () => {
    const requiereInactivo = ['DESTITUCION', 'CESACION DE FUNCIONES'].includes(form.value.tipo_accion)
    const { data } = await api.get("/empleados", {
      params: { buscar: busquedaEmp.value, estado: requiereInactivo ? "INACTIVO" : "ACTIVO", per_page: 8 }
    })
    resultadosEmp.value = data.data
  }, 300)
}

const seleccionarEmpleado = (e) => {
  empleadoSeleccionado.value = e
  form.value.id_emp = e.id_emp
  busquedaEmp.value   = ""
  resultadosEmp.value = []

  // INGRESO / REINGRESO: auto-llenar propuesta desde la ficha del empleado
  if (TIPOS_SIN_ACTUAL.includes(form.value.tipo_accion)) {
    form.value.propuesto_cargo        = e.cargo_empleado         || ""
    form.value.propuesto_grupo_ocup   = e.grupo_ocupacional      || ""
    form.value.propuesto_grado        = e.nivel                  || ""
    form.value.propuesto_remuneracion = e.sueldo                 || ""
    form.value.propuesto_partida      = e.partida_presupuestaria
      ? (e.partida_presupuestaria + (e.partida_individual ? `-${e.partida_individual}` : ""))
      : ""
    form.value.propuesto_proceso_inst = e.proceso_institucional  || ""
  }

  calcularDiferencial()
}

const limpiarEmpleado = () => {
  empleadoSeleccionado.value = null
  form.value.id_emp = ""
  diferencial.value = null
}

const buscarTitular = () => {
  clearTimeout(titularTimer)
  if (busquedaTitular.value.length < 2) { resultadosTitular.value = []; return }
  titularTimer = setTimeout(async () => {
    const { data } = await api.get("/empleados", {
      params: { buscar: busquedaTitular.value, estado: "ACTIVO", per_page: 8 }
    })
    resultadosTitular.value = data.data
  }, 300)
}

const seleccionarTitular = (e) => {
  titularSeleccionado.value = e
  form.value.id_emp_titular         = e.id_emp
  form.value.propuesto_cargo        = e.cargo_empleado         || ""
  form.value.propuesto_grupo_ocup   = e.grupo_ocupacional      || ""
  form.value.propuesto_grado        = e.nivel                  || ""
  form.value.propuesto_remuneracion = e.sueldo                 || ""
  form.value.propuesto_partida      = e.partida_presupuestaria
    ? (e.partida_presupuestaria + (e.partida_individual ? `-${e.partida_individual}` : ""))
    : ""
  form.value.propuesto_proceso_inst = e.proceso_institucional  || ""
  busquedaTitular.value   = ""
  resultadosTitular.value = []
  calcularDiferencial()
}

const limpiarTitular = () => {
  titularSeleccionado.value         = null
  form.value.id_emp_titular         = ""
  form.value.propuesto_cargo        = ""
  form.value.propuesto_grupo_ocup   = ""
  form.value.propuesto_grado        = ""
  form.value.propuesto_remuneracion = ""
  form.value.propuesto_partida      = ""
  form.value.propuesto_proceso_inst = ""
  diferencial.value = null
}

const calcularDiferencial = () => {
  if (!empleadoSeleccionado.value || !form.value.propuesto_remuneracion) {
    diferencial.value = null
    return
  }
  const propuesto = parseFloat(form.value.propuesto_remuneracion) || 0
  const actual    = parseFloat(empleadoSeleccionado.value.sueldo) || 0
  diferencial.value = Math.max(0, propuesto - actual)
}

const guardar = async () => {
  if (!form.value.id_emp) { error.value = "Debe seleccionar un empleado."; return }
  guardando.value = true
  error.value = ""
  try {
    const payload = {
      ...form.value,
      fecha_fin: hastaNuevaOrden.value ? null : (form.value.fecha_fin || null),
      propuesto_cargo:        conPropuesta.value ? form.value.propuesto_cargo        : null,
      propuesto_grupo_ocup:   conPropuesta.value ? form.value.propuesto_grupo_ocup   : null,
      propuesto_grado:        conPropuesta.value ? form.value.propuesto_grado        : null,
      propuesto_remuneracion: conPropuesta.value ? form.value.propuesto_remuneracion : 0,
      propuesto_partida:      conPropuesta.value ? form.value.propuesto_partida      : null,
      propuesto_proceso_inst: conPropuesta.value ? form.value.propuesto_proceso_inst : null,
    }
    await api.post("/acciones-personal", payload)
    router.push("/acciones-personal")
  } catch (e) {
    const errors = e.response?.data?.errors
    error.value = errors
      ? Object.values(errors).flat().join(" | ")
      : e.response?.data?.message || "Error al guardar."
  } finally {
    guardando.value = false
  }
}

onMounted(async () => {
  try {
    const { data } = await api.get("/configuracion/firmantes")
    form.value.firmante_th_nombre        = data.firmante_th_nombre        || ""
    form.value.firmante_th_cargo         = data.firmante_th_cargo         || ""
    form.value.firmante_autoridad_nombre = data.firmante_autoridad_nombre || ""
    form.value.firmante_autoridad_cargo  = data.firmante_autoridad_cargo  || ""
  } catch (_) { /* si falla, quedan vacíos */ }
})
</script>
