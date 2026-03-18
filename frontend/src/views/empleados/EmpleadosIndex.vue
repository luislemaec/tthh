<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-800">Empleados</h1>
      <router-link to="/empleados/crear"
        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm font-medium">
        + Nuevo Empleado
      </router-link>
      <router-link to="/empleados/importar"
        class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm font-medium">
        Importar CSV
      </router-link>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow p-4 flex flex-wrap gap-3">
      <input v-model="filtro.buscar" type="text" placeholder="Buscar por nombre o cédula..."
        class="border rounded-lg px-3 py-2 text-sm flex-1 min-w-[200px] focus:outline-none focus:ring-2 focus:ring-blue-500"
        @input="cargarEmpleados" />
      <select v-model="filtro.departamento" @change="cargarEmpleados"
        class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <option value="">Todos los departamentos</option>
        <option v-for="d in departamentos" :key="d.id_depto" :value="d.id_depto">{{ d.nombre_depto }}</option>
      </select>
      <select v-model="filtro.estado" @change="cargarEmpleados"
        class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        <option value="">Todos los estados</option>
        <option value="activo">Activo</option>
        <option value="inactivo">Inactivo</option>
      </select>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Empleado</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Cédula</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Departamento</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Cargo</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Estado</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando">
            <td colspan="6" class="text-center py-8 text-gray-400">Cargando...</td>
          </tr>
          <tr v-else-if="empleados.length === 0">
            <td colspan="6" class="text-center py-8 text-gray-400">No se encontraron empleados.</td>
          </tr>
          <tr v-for="emp in empleados" :key="emp.id_emp" class="border-b hover:bg-gray-50">
            <td class="px-6 py-3 font-medium text-gray-800">
              {{ emp.apellido_emp }}, {{ emp.nombre_emp }}
            </td>
            <td class="px-6 py-3 text-gray-600">{{ emp.identificacion }}</td>
            <td class="px-6 py-3 text-gray-600">{{ emp.departamento?.nombre_depto }}</td>
            <td class="px-6 py-3 text-gray-600">{{ emp.cargo_empleado }}</td>
            <td class="px-6 py-3">
              <span :class="emp.estado?.toUpperCase() === 'ACTIVO'
                ? 'bg-green-100 text-green-700'
                : 'bg-red-100 text-red-700'"
                class="px-2 py-1 rounded-full text-xs font-medium">
                {{ emp.estado }}
              </span>
            </td>
            <td class="px-6 py-3">
              <div class="flex gap-2">
                <router-link :to="`/empleados/${emp.id_emp}`"
                  class="text-blue-600 hover:underline text-xs">Ver</router-link>
                <router-link :to="`/empleados/${emp.id_emp}/editar`"
                  class="text-yellow-600 hover:underline text-xs">Editar</router-link>
                <button @click="eliminar(emp.id_emp)"
                  class="text-red-600 hover:underline text-xs">Eliminar</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Paginación -->
      <div class="px-6 py-4 flex items-center justify-between border-t text-sm text-gray-600">
        <span>Mostrando {{ empleados.length }} de {{ total }} empleados</span>
        <div class="flex gap-2">
          <button @click="pagina--" :disabled="pagina === 1"
            class="px-3 py-1 rounded border hover:bg-gray-100 disabled:opacity-40">Anterior</button>
          <span class="px-3 py-1">{{ pagina }}</span>
          <button @click="pagina++" :disabled="pagina * porPagina >= total"
            class="px-3 py-1 rounded border hover:bg-gray-100 disabled:opacity-40">Siguiente</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import api from '@/services/api'

const empleados    = ref([])
const departamentos = ref([])
const cargando     = ref(false)
const total        = ref(0)
const pagina       = ref(1)
const porPagina    = 10

const filtro = ref({ buscar: '', departamento: '', estado: '' })

const cargarEmpleados = async () => {
  cargando.value = true
  try {
    const { data } = await api.get('/empleados', {
      params: {
        page:            pagina.value,
        per_page:        porPagina,
        buscar:          filtro.value.buscar,
        departamento_id: filtro.value.departamento,
        estado:          filtro.value.estado,
      }
    })
    empleados.value = data.data
    total.value     = data.total
  } finally {
    cargando.value = false
  }
}

const eliminar = async (id) => {
  if (!confirm('¿Seguro que deseas desactivar este empleado?')) return
  await api.delete(`/empleados/${id}`)
  cargarEmpleados()
}

watch(pagina, cargarEmpleados)

onMounted(async () => {
  cargarEmpleados()
  const { data } = await api.get('/departamentos')
  departamentos.value = data
})
</script>
