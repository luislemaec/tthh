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

      <!-- Datos Personales -->
      <div class="bg-white rounded-xl shadow p-6 space-y-4">
        <h2 class="text-lg font-semibold text-gray-700 border-b pb-2">Datos Personales</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Nombres *</label>
            <input v-model="form.nombres" type="text" required
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Apellidos *</label>
            <input v-model="form.apellidos" type="text" required
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Cedula *</label>
            <input v-model="form.cedula" type="text" required maxlength="10"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Telefono</label>
            <input v-model="form.telefono" type="text"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-600 mb-1">Email</label>
            <input v-model="form.email" type="email"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-gray-600 mb-1">Direccion</label>
            <input v-model="form.direccion" type="text"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
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
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">Seleccionar...</option>
              <option v-for="d in departamentos" :key="d.id_depto" :value="d.id_depto">
                {{ d.nombre_depto }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Cargo</label>
            <input v-model="form.cargo_empleado" type="text" placeholder="Ej: Analista de Sistemas"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Tipo de Contrato</label>
            <select v-model="form.tipo_contrato"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">Seleccionar...</option>
              <option value="LOSEP">LOSEP</option>
              <option value="CODIGO DEL TRABAJO">CÓDIGO DEL TRABAJO</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Modalidad Laboral</label>
            <select v-model="form.modalidad_laboral"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">Seleccionar...</option>
              <option value="Nombramiento Definitivo">Nombramiento Definitivo</option>
              <option value="Nombramiento Provisional">Nombramiento Provisional</option>
              <option value="Contrato Ocasional">Contrato Ocasional</option>
              <option value="Comisión de Servicios">Comisión de Servicios</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Jornada Laboral</label>
            <select v-model="form.id_jornada"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="">Seleccionar...</option>
              <option v-for="j in jornadas" :key="j.id_jornada" :value="j.id_jornada">
                {{ j.descripcion }} ({{ j.normal }}h)
              </option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Estado</label>
            <select v-model="form.estado"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
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
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div v-if="form.estado === 'INACTIVO'">
           <label class="block text-sm font-medium text-gray-600 mb-1">Fecha de Salida *</label>
            <input v-model="form.fecha_salida" type="date"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Salario Base</label>
            <input v-model="form.salario" type="number" step="0.01" min="0"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Nivel</label>
            <input v-model="form.nivel" type="number" min="1"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
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
          class="px-5 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
          {{ guardando ? "Guardando..." : (esEdicion ? "Actualizar" : "Crear Empleado") }}
        </button>
      </div>
    </form>
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
const departamentos = ref([])
const jornadas      = ref([])

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
  nivel:          "",
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

onMounted(async () => {
  const { data: deps } = await api.get("/departamentos")
  departamentos.value = deps
  const { data: jors } = await api.get("/admin/jornadas")
  jornadas.value = jors

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
  }
})
</script>
