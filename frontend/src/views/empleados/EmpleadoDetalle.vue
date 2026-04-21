<template>
  <div class="space-y-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <router-link to="/empleados" class="text-gray-400 hover:text-gray-600">← Volver</router-link>
        <h1 class="text-2xl font-bold text-gray-800">Detalle del Empleado</h1>
      </div>
      <router-link :to="'/empleados/' + route.params.id + '/editar'"
        class="bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600 text-sm font-medium">
        Editar
      </router-link>
    </div>

    <div v-if="cargando" class="text-center py-12 text-gray-400">Cargando...</div>

    <template v-else>
      <!-- Encabezado -->
      <div class="bg-white rounded-xl shadow p-6 flex items-center gap-6">
        <div class="bg-blue-100 rounded-full w-16 h-16 flex items-center justify-center text-2xl font-bold text-[#0b5447]">
          {{ iniciales }}
        </div>
        <div>
          <h2 class="text-xl font-bold text-gray-800">{{ emp.apellido_emp }}, {{ emp.nombre_emp }}</h2>
          <p class="text-gray-500 text-sm">{{ emp.cargo_empleado || "Sin cargo" }} — {{ emp.departamento?.nombre_depto }}</p>
          <span :class="emp.estado === 'ACTIVO' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
            class="mt-1 inline-block px-2 py-0.5 rounded-full text-xs font-medium">
            {{ emp.estado }}
          </span>
        </div>
      </div>

      <!-- Datos Personales -->
      <div class="bg-white rounded-xl shadow p-6">
        <h3 class="text-md font-semibold text-gray-700 border-b pb-2 mb-4">Datos Personales</h3>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
          <div><dt class="text-gray-500">Cedula</dt><dd class="font-medium">{{ emp.identificacion }}</dd></div>
          <div><dt class="text-gray-500">Telefono</dt><dd class="font-medium">{{ emp.telefono || "—" }}</dd></div>
          <div><dt class="text-gray-500">Email</dt><dd class="font-medium">{{ emp.emails?.[0]?.mail || "—" }}</dd></div>
          <div><dt class="text-gray-500">Extension</dt><dd class="font-medium">{{ emp.extension || "—" }}</dd></div>
          <div class="sm:col-span-2"><dt class="text-gray-500">Direccion</dt><dd class="font-medium">{{ emp.calle_y_numero || "—" }}</dd></div>
        </dl>
      </div>

      <!-- Contrato -->
      <div class="bg-white rounded-xl shadow p-6">
        <h3 class="text-md font-semibold text-gray-700 border-b pb-2 mb-4">Contrato y Salario</h3>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
          <div><dt class="text-gray-500">Fecha de Ingreso</dt><dd class="font-medium">{{ emp.fecha_ingreso?.substring(0,10) || "—" }}</dd></div>
          <div><dt class="text-gray-500">Fecha de Salida</dt><dd class="font-medium">{{ emp.fecha_salida?.substring(0,10) || "—" }}</dd></div>
          <div><dt class="text-gray-500">Salario Base</dt><dd class="font-medium">${{ Number(emp.sueldo || 0).toFixed(2) }}</dd></div>
          <div><dt class="text-gray-500">Nivel</dt><dd class="font-medium">{{ emp.nivel || "—" }}</dd></div>
          <div><dt class="text-gray-500">Antiguedad</dt><dd class="font-medium">{{ antiguedad }}</dd></div>
        </dl>
      </div>

      <!-- Datos del Puesto (Distributivo) -->
      <div class="bg-white rounded-xl shadow p-6">
        <h3 class="text-md font-semibold text-gray-700 border-b pb-2 mb-4">Datos del Puesto</h3>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
          <div><dt class="text-gray-500">Grupo Ocupacional</dt><dd class="font-medium">{{ emp.grupo_ocupacional || "—" }}</dd></div>
          <div><dt class="text-gray-500">Grado</dt><dd class="font-medium">{{ emp.nivel || "—" }}</dd></div>
          <div><dt class="text-gray-500">Proceso Institucional</dt><dd class="font-medium">{{ emp.proceso_institucional || "—" }}</dd></div>
          <div><dt class="text-gray-500">Estado del Puesto</dt>
            <dd class="font-medium">
              <span :class="emp.estado_puesto === 'OCUPADO' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'"
                class="px-2 py-0.5 rounded-full text-xs">
                {{ emp.estado_puesto || "—" }}
              </span>
            </dd>
          </div>
          <div><dt class="text-gray-500">Partida Individual</dt><dd class="font-medium">{{ emp.partida_individual || "—" }}</dd></div>
          <div><dt class="text-gray-500">Fondos de Reserva</dt>
            <dd class="font-medium">{{ fondosReservaLabel }}</dd>
          </div>
          <div><dt class="text-gray-500">Décimo Tercero</dt>
            <dd class="font-medium">
              <span :class="emp.acumula_decimo_tercero ? 'text-green-600' : 'text-gray-400'">
                {{ emp.acumula_decimo_tercero ? 'Acumula' : 'No acumula' }}
              </span>
            </dd>
          </div>
          <div><dt class="text-gray-500">Décimo Cuarto</dt>
            <dd class="font-medium">
              <span :class="emp.acumula_decimo_cuarto ? 'text-green-600' : 'text-gray-400'">
                {{ emp.acumula_decimo_cuarto ? 'Acumula' : 'No acumula' }}
              </span>
            </dd>
          </div>
          <div class="sm:col-span-2">
            <dt class="text-gray-500">Partida Presupuestaria</dt>
            <dd class="font-medium font-mono text-xs text-gray-600 break-all">{{ emp.partida_presupuestaria || "—" }}</dd>
          </div>
        </dl>
      </div>

      <!-- Roles y Acceso -->
      <div class="bg-white rounded-xl shadow p-6">
        <h3 class="text-md font-semibold text-gray-700 border-b pb-2 mb-4">Roles y Acceso al Sistema</h3>

        <!-- Roles asignados -->
        <div class="mb-4">
          <p class="text-sm text-gray-500 mb-2">Roles asignados:</p>
          <div v-if="rolesAsignados.length === 0" class="text-sm text-gray-400">
            Sin roles asignados
          </div>
          <div class="flex flex-wrap gap-2">
            <div v-for="r in rolesAsignados" :key="r.id_rol"
              class="flex items-center gap-2 bg-blue-50 text-[#0b5447] px-3 py-1 rounded-full text-sm">
              <span>{{ r.rol?.descripcion }}</span>
              <button @click="quitarRol(r.id_rol)"
                class="text-blue-400 hover:text-red-500 font-bold text-xs">✕</button>
            </div>
          </div>
        </div>

        <!-- Reset contraseña -->
        <div class="mt-4 pt-4 border-t">
          <p class="text-sm text-gray-500 mb-2">Contraseña de acceso:</p>
          <button @click="resetPassword" :disabled="reseteando"
            class="bg-red-50 text-red-600 border border-red-200 px-4 py-2 rounded-lg text-sm hover:bg-red-100 disabled:opacity-50">
            {{ reseteando ? 'Reseteando...' : 'Resetear contraseña a cédula' }}
          </button>
          <p class="text-xs text-gray-400 mt-1">La contraseña volverá a ser el número de cédula del empleado.</p>
          <div v-if="mensajeReset" class="mt-2 text-sm text-green-600 bg-green-50 px-3 py-2 rounded-lg">
            {{ mensajeReset }}
          </div>
        </div>

        <!-- Asignar nuevo rol -->
        <div class="flex gap-2">
          <select v-model="rolSeleccionado"
            class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
            <option value="">Seleccionar rol...</option>
            <option v-for="r in rolesDisponibles" :key="r.id" :value="r.id">
              {{ r.descripcion }}
            </option>
          </select>
          <button @click="asignarRol" :disabled="!rolSeleccionado || asignando"
            class="bg-[#0b5447] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#00372e] disabled:opacity-50">
            {{ asignando ? "Asignando..." : "Asignar Rol" }}
          </button>
        </div>

        <div v-if="mensajeRol"
          class="mt-3 text-sm text-green-600 bg-green-50 px-3 py-2 rounded-lg">
          {{ mensajeRol }}
        </div>
      </div>

    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue"
import { useRoute } from "vue-router"
import api from "@/services/api"

const route    = useRoute()
const emp      = ref({})
const cargando = ref(true)
const rolesAsignados  = ref([])
const rolesDisponibles = ref([])
const rolSeleccionado  = ref("")
const asignando    = ref(false)
const mensajeRol   = ref("")
const reseteando   = ref(false)
const mensajeReset = ref("")

const iniciales = computed(() => {
  const n = emp.value.nombre_emp?.[0] || ""
  const a = emp.value.apellido_emp?.[0] || ""
  return (n + a).toUpperCase()
})

const fondosReservaLabel = computed(() => {
  const v = emp.value.acumula_fondos_reserva
  if (v === 0 || v === '0') return 'No tiene derecho'
  if (v === 1 || v === '1') return 'Cobra mensualmente'
  if (v === 2 || v === '2') return 'Acumula'
  return '—'
})

const antiguedad = computed(() => {
  if (!emp.value.fecha_ingreso) return "—"
  const ingreso = new Date(emp.value.fecha_ingreso)
  const hoy = new Date()
  const totalMeses = (hoy.getFullYear() - ingreso.getFullYear()) * 12 + (hoy.getMonth() - ingreso.getMonth())
  if (totalMeses < 12) return totalMeses + " mes(es)"
  return Math.floor(totalMeses / 12) + " año(s) y " + (totalMeses % 12) + " mes(es)"
})

const cargarRoles = async () => {
  const [asignados, todos] = await Promise.all([
    api.get("/empleados/" + route.params.id + "/roles"),
    api.get("/roles"),
  ])
  rolesAsignados.value  = asignados.data
  rolesDisponibles.value = todos.data
}

const asignarRol = async () => {
  if (!rolSeleccionado.value) return
  asignando.value = true
  try {
    await api.post("/empleados/" + emp.value.id_emp + "/roles", {
      id_rol: rolSeleccionado.value,
      identificacion: emp.value.identificacion,
    })
    mensajeRol.value = "Rol asignado correctamente"
    rolSeleccionado.value = ""
    await cargarRoles()
    setTimeout(() => { mensajeRol.value = "" }, 3000)
  } catch (e) {
    mensajeRol.value = e.response?.data?.message || "Error al asignar rol"
  } finally {
    asignando.value = false
  }
}

const resetPassword = async () => {
  if (!confirm(`¿Resetear la contraseña de ${emp.value.apellido_emp}, ${emp.value.nombre_emp} a su cédula?`)) return
  reseteando.value = true
  mensajeReset.value = ""
  try {
    const { data } = await api.post(`/empleados/${emp.value.id_emp}/reset-password`)
    mensajeReset.value = data.message
    setTimeout(() => { mensajeReset.value = "" }, 4000)
  } catch (e) {
    alert(e.response?.data?.message || "Error al resetear contraseña")
  } finally {
    reseteando.value = false
  }
}

const quitarRol = async (id_rol) => {
  if (!confirm("Seguro que deseas quitar este rol?")) return
  try {
    await api.delete("/empleados/" + emp.value.id_emp + "/roles/" + id_rol)
    await cargarRoles()
  } catch (e) {
    alert("Error al quitar el rol")
  }
}

onMounted(async () => {
  const { data } = await api.get("/empleados/" + route.params.id)
  emp.value = data
  cargando.value = false
  await cargarRoles()
})
</script>