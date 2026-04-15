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
              <option value="ENCARGO">Encargo de Funciones</option>
              <option value="SUBROGACION">Subrogación</option>
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
            <label class="block text-sm font-medium text-gray-600 mb-1">Vigente desde *</label>
            <input v-model="form.fecha_inicio" type="date" required
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Vigente hasta <span class="text-gray-400 font-normal">(opcional)</span></label>
            <input v-model="form.fecha_fin" type="date"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
        </div>
      </div>

      <!-- Empleado -->
      <div class="bg-white rounded-xl shadow p-6 space-y-4">
        <h2 class="text-lg font-semibold text-gray-700 border-b pb-2">Empleado que recibe el encargo / subrogación</h2>
        <div class="flex gap-3">
          <input v-model="busquedaEmp" type="text" placeholder="Buscar por nombre o cédula..."
            class="flex-1 border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]"
            @input="buscarEmpleados" />
        </div>

        <!-- Resultados búsqueda -->
        <div v-if="resultadosEmp.length" class="border rounded-lg overflow-hidden">
          <div v-for="e in resultadosEmp" :key="e.id_emp"
            @click="seleccionarEmpleado(e)"
            class="px-4 py-2 hover:bg-blue-50 cursor-pointer border-b last:border-b-0 text-sm">
            <span class="font-medium">{{ e.apellido_emp }}, {{ e.nombre_emp }}</span>
            <span class="text-gray-400 ml-2">{{ e.identificacion }}</span>
            <span class="text-gray-500 ml-2">— {{ e.cargo_empleado }}</span>
          </div>
        </div>

        <!-- Situación actual (carga automático) -->
        <div v-if="empleadoSeleccionado" class="bg-gray-50 rounded-lg p-4">
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
          </dl>
        </div>
      </div>

      <!-- Situación Propuesta -->
      <div class="bg-white rounded-xl shadow p-6 space-y-4">
        <h2 class="text-lg font-semibold text-gray-700 border-b pb-2">Situación Propuesta <span class="text-sm font-normal text-gray-400">(cargo a encargar/subrogar)</span></h2>
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
              placeholder="Ej: NIVEL JERARQUICO SUPERIOR 5"
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

        <!-- Diferencial calculado -->
        <div v-if="diferencial !== null" class="bg-blue-50 border border-blue-200 rounded-lg p-3 flex items-center gap-3">
          <span class="text-blue-700 text-sm font-medium">Diferencial salarial:</span>
          <span class="text-blue-900 font-bold text-lg">${{ diferencial.toFixed(2) }}</span>
          <span v-if="diferencial === 0" class="text-xs text-blue-500">(no aplica diferencial)</span>
        </div>
      </div>

      <!-- Motivación -->
      <div class="bg-white rounded-xl shadow p-6 space-y-3">
        <h2 class="text-lg font-semibold text-gray-700 border-b pb-2">Motivación / Resolución</h2>
        <p class="text-xs text-gray-400">Ingrese el texto completo de la resolución tal como debe aparecer en el documento oficial.</p>
        <textarea v-model="form.motivacion" rows="8"
          placeholder="Ej: El Presidente del Consejo..., en ejercicio de sus facultades, RESUELVE: Autorizar la subrogación..."
          class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186] resize-none"></textarea>
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
import { ref, computed } from "vue"
import { useRouter } from "vue-router"
import api from "@/services/api"

const router = useRouter()

const form = ref({
  tipo_accion:            "",
  fecha_elaboracion:      new Date().toISOString().substring(0, 10),
  id_emp:                 "",
  fecha_inicio:           "",
  fecha_fin:              "",
  motivacion:             "",
  propuesto_cargo:        "",
  propuesto_grupo_ocup:   "",
  propuesto_grado:        "",
  propuesto_remuneracion: "",
  propuesto_partida:      "",
  propuesto_proceso_inst: "",
})

const busquedaEmp       = ref("")
const resultadosEmp     = ref([])
const empleadoSeleccionado = ref(null)
const diferencial       = ref(null)
const guardando         = ref(false)
const error             = ref("")
let busquedaTimer       = null

const buscarEmpleados = () => {
  clearTimeout(busquedaTimer)
  if (busquedaEmp.value.length < 2) { resultadosEmp.value = []; return }
  busquedaTimer = setTimeout(async () => {
    const { data } = await api.get("/empleados", {
      params: { buscar: busquedaEmp.value, estado: "ACTIVO", per_page: 8 }
    })
    resultadosEmp.value = data.data
  }, 300)
}

const seleccionarEmpleado = (e) => {
  empleadoSeleccionado.value = e
  form.value.id_emp = e.id_emp
  busquedaEmp.value = ""
  resultadosEmp.value = []
  calcularDiferencial()
}

const limpiarEmpleado = () => {
  empleadoSeleccionado.value = null
  form.value.id_emp = ""
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
    await api.post("/acciones-personal", form.value)
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
</script>
