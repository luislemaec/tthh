<template>
  <div class="space-y-6 max-w-4xl mx-auto">
    <div class="flex items-center gap-3">
      <router-link to="/empleados" class="text-gray-400 hover:text-gray-600">
        <- Volver
      </router-link>
      <h1 class="text-2xl font-bold text-gray-800">
        {{ esEdicion ? "Editar Empleado" : "Nuevo Empleado" }}
      </h1>
    </div>

    <form @submit.prevent="guardar" class="space-y-6">

      <!-- Foto del empleado (solo en edición) -->
      <div v-if="esEdicion" class="bg-white rounded-xl shadow p-6">
        <h2 class="text-lg font-semibold text-gray-700 border-b pb-2 mb-4">Fotografía</h2>
        <div class="flex items-center gap-6">
          <div class="w-28 h-28 rounded-full overflow-hidden bg-gray-100 flex items-center justify-center flex-shrink-0 border-2 border-gray-200">
            <img v-if="fotoUrl" :src="fotoUrl" class="w-full h-full object-cover" alt="Foto empleado" />
            <svg v-else class="w-14 h-14 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
          </div>
          <div class="space-y-2">
            <label class="block">
              <span class="sr-only">Seleccionar foto</span>
              <input type="file" accept="image/*" @change="subirFoto" :disabled="subiendoFoto"
                class="block w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-[#0b5447] file:text-white hover:file:bg-[#00372e] disabled:opacity-50 cursor-pointer" />
            </label>
            <p class="text-xs text-gray-400">JPG, PNG o GIF — máximo 2 MB</p>
            <button v-if="fotoUrl" type="button" @click="eliminarFoto" :disabled="subiendoFoto"
              class="text-xs text-red-500 hover:text-red-700 transition">
              Eliminar foto
            </button>
            <p v-if="subiendoFoto" class="text-xs text-blue-500">Subiendo...</p>
          </div>
        </div>
      </div>

      <!-- Datos Personales -->
      <div class="bg-white rounded-xl shadow p-6 space-y-4">
        <h2 class="text-lg font-semibold text-gray-700 border-b pb-2">Datos Personales</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Nombres *</label>
            <input v-model="form.nombres" type="text" required
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Apellidos *</label>
            <input v-model="form.apellidos" type="text" required
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Cédula / Pasaporte *</label>
            <input v-model="form.cedula" type="text" required maxlength="20"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Telefono</label>
            <input v-model="form.telefono" type="text"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-600 mb-1">Email</label>
            <input v-model="form.email" type="email"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-600 mb-1">Direccion</label>
            <input v-model="form.direccion" type="text"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
        </div>
      </div>

      <!-- Cargo y Departamento -->
      <div class="bg-white rounded-xl shadow p-6 space-y-4">
        <h2 class="text-lg font-semibold text-gray-700 border-b pb-2">Cargo y Departamento</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Departamento *</label>
            <select v-model="form.departamento_id" required
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
              <option value="">Seleccionar...</option>
              <option v-for="d in departamentos" :key="d.id_depto" :value="d.id_depto">
                {{ d.nombre_depto }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Cargo *</label>
            <input v-model="form.cargo_empleado" type="text" placeholder="Ej: Analista de Sistemas" required
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Tipo de Contrato</label>
            <select v-model="form.tipo_contrato"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
              <option value="">Seleccionar...</option>
              <option value="LOSEP">LOSEP</option>
              <option value="CODIGO DEL TRABAJO">CÓDIGO DEL TRABAJO</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Modalidad Laboral *</label>
            <select v-model="form.modalidad_laboral" required
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
              <option value="">Seleccionar...</option>
              <option v-for="m in modalidadesLaborales" :key="m.id" :value="m.nombre">{{ m.nombre }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Jornada Laboral</label>
            <select v-model="form.id_jornada"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
              <option value="">Seleccionar...</option>
              <option v-for="j in jornadas" :key="j.id_jornada" :value="j.id_jornada">
                {{ j.descripcion }} ({{ j.normal }}h)
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Estado</label>
            <select v-model="form.estado"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
              <option value="ACTIVO">Activo</option>
              <option value="INACTIVO">Inactivo</option>
            </select>
          </div>
        </div>
      </div>

      <!-- Contrato y Salario -->
      <div class="bg-white rounded-xl shadow p-6 space-y-4">
        <h2 class="text-lg font-semibold text-gray-700 border-b pb-2">Contrato y Salario</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Fecha de Ingreso *</label>
            <input v-model="form.fecha_ingreso" type="date" required
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div v-if="form.estado === 'INACTIVO'">
           <label class="block text-sm font-medium text-gray-600 mb-1">Fecha de Salida *</label>
            <input v-model="form.fecha_salida" type="date"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Salario Base *</label>
            <input v-model="form.salario" type="number" step="0.01" min="0" required
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
        </div>
      </div>

      <!-- Datos del Puesto -->
      <div class="bg-white rounded-xl shadow p-6 space-y-4">
        <h2 class="text-lg font-semibold text-gray-700 border-b pb-2">Datos del Puesto</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Grupo Ocupacional *</label>
            <input v-model="form.grupo_ocupacional" type="text" placeholder="Ej: SERVIDOR PUBLICO 7" required
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Grado *</label>
            <input v-model="form.nivel" type="number" min="1" required
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Proceso Institucional *</label>
            <select v-model="form.proceso_institucional" required
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
              <option value="">Seleccionar...</option>
              <option value="SUSTANTIVO">SUSTANTIVO</option>
              <option value="ADJETIVO">ADJETIVO</option>
              <option value="GOBERNANTE">GOBERNANTE</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Estado del Puesto</label>
            <select v-model="form.estado_puesto"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
              <option value="OCUPADO">OCUPADO</option>
              <option value="VACANTE">VACANTE</option>
              <option value="DISPONIBLE">DISPONIBLE</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Partida Individual *</label>
            <div class="flex gap-2">
              <input v-model="form.partida_individual" type="text" required
                placeholder="Escriba una nueva o use el botón para seleccionar una libre"
                class="flex-1 border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
              <button type="button" @click="modalPartidas.show = true"
                title="Ver partidas disponibles de empleados inactivos"
                class="shrink-0 text-xs border border-[#00372e] text-[#00372e] px-3 py-2 rounded-lg hover:bg-[#00372e] hover:text-white whitespace-nowrap transition-colors">
                Seleccionar libre
              </button>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Fondos de Reserva</label>
            <select v-model="form.acumula_fondos_reserva"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
              <option :value="0">No tiene derecho</option>
              <option :value="1">Cobra mensualmente</option>
              <option :value="2">Acumula</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Acumula Décimos (13° y 14°)</label>
            <select v-model="form.acumula_decimos"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
              <option :value="true">Acumula</option>
              <option :value="false">Cobra mensualmente</option>
            </select>
          </div>
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-600 mb-1">Partida Presupuestaria *</label>
            <input v-model="form.partida_presupuestaria" type="text" placeholder="Ej: 202622000000000..." required
              class="w-full border rounded-lg px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
        </div>
      </div>

      <!-- Control de Asistencia -->
      <div class="bg-white rounded-xl shadow p-6 space-y-4">
        <h2 class="text-lg font-semibold text-gray-700 border-b pb-2">Control de Asistencia</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Modalidad de Marcación</label>
            <select v-model="form.modalidad_marcacion"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
              <option value="PRESENCIAL">PRESENCIAL — solo desde red interna</option>
              <option value="REMOTO">REMOTO — desde cualquier IP (comisión, viaje)</option>
              <option value="TELETRABAJO">TELETRABAJO — marca como teletrabajo</option>
            </select>
          </div>
          <div class="flex items-end pb-1">
            <p class="text-xs text-gray-400 leading-relaxed">
              <b class="text-gray-500">PRESENCIAL:</b> solo puede timbrar desde las VLANs internas configuradas.<br>
              <b class="text-gray-500">REMOTO:</b> puede timbrar desde cualquier IP (viaje, comisión).<br>
              <b class="text-gray-500">TELETRABAJO:</b> marca como teletrabajo sin restricción de IP.
            </p>
          </div>
        </div>
        <div class="flex items-center gap-3 pt-1">
          <input id="puede_solicitar_vehiculo" type="checkbox" v-model="form.puede_solicitar_vehiculo"
            class="w-4 h-4 rounded accent-[#1e3a5f]" />
          <label for="puede_solicitar_vehiculo" class="text-sm text-gray-700 select-none cursor-pointer">
            Puede solicitar vehículo institucional
          </label>
        </div>
      </div>

      <!-- Error -->
      <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
        {{ error }}
      </div>

      <!-- Botones -->
      <div class="flex justify-end gap-3">
        <router-link to="/empleados"
          class="px-5 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">
          Cancelar
        </router-link>
        <button type="submit" :disabled="guardando"
          class="px-5 py-2 rounded-lg bg-[#0b5447] text-white text-sm font-medium hover:bg-[#00372e] disabled:opacity-50">
          {{ guardando ? "Guardando..." : (esEdicion ? "Actualizar" : "Crear Empleado") }}
        </button>
      </div>
    </form>

    <!-- Modal: Seleccionar partida disponible -->
    <div v-if="modalPartidas.show"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl p-6">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-lg font-bold text-gray-800">Partidas individuales disponibles</h2>
          <button @click="modalPartidas.show = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <p class="text-xs text-gray-500 mb-3">Empleados inactivos con estado del puesto DISPONIBLE. Al seleccionar se llenarán ambas partidas.</p>
        <div v-if="!partidasVacantes.length" class="text-center py-8 text-gray-400 text-sm">
          No hay partidas disponibles en este momento.
        </div>
        <div v-else class="overflow-auto max-h-80">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 sticky top-0">
              <tr>
                <th class="text-left px-3 py-2 text-gray-600 font-medium">Empleado anterior</th>
                <th class="text-left px-3 py-2 text-gray-600 font-medium">Partida Individual</th>
                <th class="text-left px-3 py-2 text-gray-600 font-medium">Partida Presupuestaria</th>
                <th class="px-3 py-2"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in partidasVacantes" :key="p.id_emp"
                class="border-b hover:bg-green-50 cursor-pointer"
                @click="seleccionarPartida(p)">
                <td class="px-3 py-2">{{ p.apellido_emp }} {{ p.nombre_emp }}</td>
                <td class="px-3 py-2 font-mono text-xs">{{ p.partida_individual }}</td>
                <td class="px-3 py-2 font-mono text-xs">{{ p.partida_presupuestaria || '—' }}</td>
                <td class="px-3 py-2 text-right">
                  <span class="text-[#00372e] font-semibold text-xs">Seleccionar →</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="flex justify-end mt-4">
          <button @click="modalPartidas.show = false"
            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">
            Cancelar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue"
import { useRoute, useRouter } from "vue-router"
import api from "@/services/api"

const route  = useRoute()
const router = useRouter()

const esEdicion     = computed(() => !!route.params.id)
const guardando     = ref(false)
const error         = ref("")
const fotoUrl       = ref(null)
const subiendoFoto  = ref(false)

const baseUrl = import.meta.env.VITE_API_URL.replace(/\/api\/?$/, '')
const storageUrl = (path) => path ? `${baseUrl}/storage/${path}` : null
const departamentos       = ref([])
const jornadas            = ref([])
const partidasVacantes    = ref([])
const modalidadesLaborales = ref([])
const modalPartidas    = ref({ show: false })

function seleccionarPartida(p) {
  form.value.partida_individual    = p.partida_individual    || ""
  form.value.partida_presupuestaria = p.partida_presupuestaria || ""
  modalPartidas.value.show = false
}

const form = ref({
  nombres:        "",
  apellidos:      "",
  cedula:         "",
  telefono:       "",
  email:          "",
  direccion:      "",
  departamento_id: null,
  cargo_empleado: "",
  tipo_contrato:     "",
  modalidad_laboral: "",
  id_jornada:        "",
  estado:            "ACTIVO",
  fecha_ingreso:  "",
  fecha_salida:   "",
  salario:        "",
  // Datos del puesto
  nivel:                   "",
  grupo_ocupacional:       "",
  proceso_institucional:   "",
  estado_puesto:           "OCUPADO",
  partida_individual:      "",
  partida_presupuestaria:  "",
  acumula_fondos_reserva:    0,
  acumula_decimos:           false,
  modalidad_marcacion:       'PRESENCIAL',
  puede_solicitar_vehiculo:  false,
})

const guardar = async () => {
  guardando.value = true
  error.value = ""
  try {
    const payload = {
      identificacion: form.value.cedula,
      nombre_emp:     form.value.nombres,
      apellido_emp:   form.value.apellidos,
      id_depto:       form.value.departamento_id,
      estado:         form.value.estado || "ACTIVO",
      cargo_empleado: form.value.cargo_empleado,
      telefono:       form.value.telefono,
      calle_y_numero: form.value.direccion,
      fecha_ingreso:  form.value.fecha_ingreso,
      fecha_salida:   form.value.fecha_salida || null,
      sueldo:         form.value.salario,
      nivel:          form.value.nivel,
      tipo_contrato:     form.value.tipo_contrato,
      modalidad_laboral: form.value.modalidad_laboral,
      id_jornada:        form.value.id_jornada || null,
      email:             form.value.email,
      grupo_ocupacional:       form.value.grupo_ocupacional      || null,
      proceso_institucional:   form.value.proceso_institucional  || null,
      estado_puesto:           form.value.estado_puesto,
      partida_individual:      form.value.partida_individual     || null,
      partida_presupuestaria:  form.value.partida_presupuestaria || null,
      acumula_fondos_reserva:   form.value.acumula_fondos_reserva,
      acumula_decimo_tercero:   form.value.acumula_decimos,
      acumula_decimo_cuarto:    form.value.acumula_decimos,
      modalidad_marcacion:      form.value.modalidad_marcacion,
      puede_solicitar_vehiculo: form.value.puede_solicitar_vehiculo,
    }

    if (esEdicion.value) {
      await api.put("/empleados/" + route.params.id, payload)
    } else {
      await api.post("/empleados", payload)
    }
    router.push("/empleados")
  } catch (e) {
    const errors = e.response?.data?.errors
    if (errors) {
      error.value = Object.values(errors).flat().join(" | ")
    } else {
      error.value = e.response?.data?.message || "Error al guardar el empleado."
    }
  } finally {
    guardando.value = false
  }
}

async function subirFoto(e) {
  const file = e.target.files?.[0]
  if (!file) return
  subiendoFoto.value = true
  try {
    const fd = new FormData()
    fd.append('foto', file)
    const { data } = await api.post(`/empleados/${route.params.id}/foto`, fd)
    fotoUrl.value = storageUrl(data.foto)
  } catch (err) {
    const msg = err?.response?.data?.message || err?.response?.data?.errors?.foto?.[0] || 'Error al subir la foto.'
    alert(msg)
  } finally {
    subiendoFoto.value = false
    e.target.value = ''
  }
}

async function eliminarFoto() {
  if (!confirm('¿Eliminar la foto del empleado?')) return
  subiendoFoto.value = true
  try {
    await api.delete(`/empleados/${route.params.id}/foto`)
    fotoUrl.value = null
  } catch {
    alert('Error al eliminar la foto.')
  } finally {
    subiendoFoto.value = false
  }
}

onMounted(async () => {
  const [{ data: deps }, { data: jors }, { data: partidas }, { data: mods }] = await Promise.all([
    api.get("/departamentos"),
    api.get("/admin/jornadas"),
    api.get("/empleados/partidas-vacantes"),
    api.get("/admin/modalidades-laborales"),
  ])
  departamentos.value        = deps
  jornadas.value             = jors
  partidasVacantes.value     = partidas
  modalidadesLaborales.value = mods.filter(m => m.estado === 'ACTIVO')

  if (esEdicion.value) {
    const { data } = await api.get("/empleados/" + route.params.id)
    form.value.nombres         = data.nombre_emp
    form.value.apellidos       = data.apellido_emp
    form.value.cedula          = data.identificacion
    form.value.departamento_id = data.id_depto
    form.value.cargo_empleado  = data.cargo_empleado || ""
    form.value.estado          = data.estado || "ACTIVO"
    form.value.fecha_ingreso   = data.fecha_ingreso?.substring(0, 10) || ""
    form.value.fecha_salida    = data.fecha_salida?.substring(0, 10) || ""
    form.value.salario         = data.sueldo || ""
    form.value.nivel           = data.nivel || ""
    form.value.telefono        = data.telefono || ""
    form.value.direccion       = data.calle_y_numero || ""
    form.value.tipo_contrato     = data.tipo_contrato?.trim()     || ""
    form.value.modalidad_laboral = data.modalidad_laboral?.trim() || ""
    form.value.id_jornada        = data.id_jornada                || ""
    form.value.email             = data.emails?.[0]?.mail         || ""
    // Datos del puesto
    form.value.grupo_ocupacional      = data.grupo_ocupacional      || ""
    form.value.proceso_institucional  = data.proceso_institucional  || ""
    form.value.estado_puesto          = data.estado_puesto          || "OCUPADO"
    form.value.partida_individual     = data.partida_individual     || ""
    form.value.partida_presupuestaria = data.partida_presupuestaria || ""
    form.value.acumula_fondos_reserva   = data.acumula_fondos_reserva   ?? 0
    form.value.acumula_decimos          = data.acumula_decimo_tercero   ?? false
    form.value.modalidad_marcacion      = data.modalidad_marcacion      ?? 'PRESENCIAL'
    form.value.puede_solicitar_vehiculo = data.puede_solicitar_vehiculo ?? false
    fotoUrl.value = storageUrl(data.foto)
  }
})
</script>
