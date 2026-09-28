<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-800">Empleados</h1>
      <router-link to="/empleados/crear"
        class="bg-[#0b5447] text-white px-4 py-2 rounded-lg hover:bg-[#00372e] text-sm font-medium">
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
        class="border rounded-lg px-3 py-2 text-sm flex-1 min-w-[200px] focus:outline-none focus:ring-2 focus:ring-[#579186]"
        @input="cargarEmpleados" />
      <select v-model="filtro.departamento" @change="cargarEmpleados"
        class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
        <option value="">Todos los departamentos</option>
        <option v-for="d in departamentos" :key="d.id_depto" :value="d.id_depto">{{ d.nombre_depto }}</option>
      </select>
      <select v-model="filtro.estado" @change="cargarEmpleados"
        class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
        <option value="">Todos los estados</option>
        <option value="activo">Activo</option>
        <option value="inactivo">Inactivo</option>
      </select>
      <select v-model="filtro.tipo_contrato" @change="cargarEmpleados"
        class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
        <option value="">Todos los contratos</option>
        <option value="LOSEP">LOSEP</option>
        <option value="CODIGO DEL TRABAJO">CÓDIGO DEL TRABAJO</option>
      </select>
      <select v-model="filtro.modalidad_laboral" @change="cargarEmpleados"
        class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
        <option value="">Todas las modalidades</option>
        <option v-for="m in modalidadesLaborales" :key="m.id" :value="m.nombre">{{ m.nombre }}</option>
      </select>
      <select v-model="filtro.es_comisionado_entrante" @change="cargarEmpleados"
        class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
        <option value="">Todas las comisiones</option>
        <option value="1">Viene de comisión</option>
        <option value="0">No viene de comisión</option>
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
              <div class="flex gap-1 flex-wrap">
                <router-link :to="`/empleados/${emp.id_emp}`"
                  class="inline-flex items-center px-2.5 py-1 rounded-md border border-[#0b5447] text-xs text-[#0b5447] hover:bg-[#f0faf8] font-medium transition-colors">
                  Ver
                </router-link>
                <router-link :to="`/empleados/${emp.id_emp}/editar`"
                  class="inline-flex items-center px-2.5 py-1 rounded-md border border-amber-300 text-xs text-amber-700 hover:bg-amber-50 font-medium transition-colors">
                  Editar
                </router-link>
                <button @click="abrirModalDesactivar(emp)"
                  class="inline-flex items-center px-2.5 py-1 rounded-md border border-red-200 text-xs text-red-600 hover:bg-red-50 font-medium transition-colors">
                  Eliminar
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Paginación -->
      <div class="px-6 py-4 flex items-center justify-between border-t text-sm text-gray-600">
        <span>Mostrando {{ rangoDesde }}-{{ rangoHasta }} de {{ total }} empleados</span>
        <div class="flex gap-2">
          <button @click="pagina--" :disabled="pagina === 1"
            class="px-3 py-1 rounded border hover:bg-gray-100 disabled:opacity-40">Anterior</button>
          <span class="px-3 py-1">{{ pagina }}</span>
          <button @click="pagina++" :disabled="pagina * porPagina >= total"
            class="px-3 py-1 rounded border hover:bg-gray-100 disabled:opacity-40">Siguiente</button>
        </div>
      </div>
    </div>

    <!-- Modal Desactivar empleado -->
    <div v-if="modalDesactivar.show" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-md space-y-4">
        <h2 class="text-lg font-semibold text-gray-700">Desactivar Empleado</h2>
        <p class="text-sm text-gray-500">
          {{ modalDesactivar.emp?.apellido_emp }}, {{ modalDesactivar.emp?.nombre_emp }}
          &mdash; {{ modalDesactivar.emp?.identificacion }}
        </p>
        <p class="text-sm text-gray-600">Confirmar desactivación</p>
        <div v-if="modalDesactivar.error" class="text-red-600 text-sm bg-red-50 rounded p-2">{{ modalDesactivar.error }}</div>
        <div class="flex justify-end gap-3">
          <button @click="modalDesactivar.show = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
          <button @click="confirmarDesactivar" :disabled="modalDesactivar.procesando"
            class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm hover:bg-red-700 disabled:opacity-50">
            {{ modalDesactivar.procesando ? 'Procesando...' : 'Confirmar Desactivación' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import api from '@/services/api'

const empleados         = ref([])
const departamentos      = ref([])
const modalidadesLaborales = ref([])
const cargando          = ref(false)
const total             = ref(0)
const pagina            = ref(1)
const porPagina         = 10

const filtro = ref({ buscar: '', departamento: '', estado: '', tipo_contrato: '', modalidad_laboral: '', es_comisionado_entrante: '' })

// "Mostrando X-Y de Z" con rango real (no el largo de la página actual, que
// siempre es 10 en cualquier página completa) — mismo patrón que Permisos/Vacaciones.
const rangoDesde = computed(() => total.value === 0 ? 0 : (pagina.value - 1) * porPagina + 1)
const rangoHasta = computed(() => Math.min(pagina.value * porPagina, total.value))

const cargarEmpleados = async () => {
  cargando.value = true
  try {
    const { data } = await api.get('/empleados', {
      params: {
        page:            pagina.value,
        per_page:        porPagina,
        buscar:            filtro.value.buscar,
        departamento_id:   filtro.value.departamento,
        estado:            filtro.value.estado,
        tipo_contrato:     filtro.value.tipo_contrato,
        modalidad_laboral:       filtro.value.modalidad_laboral,
        es_comisionado_entrante: filtro.value.es_comisionado_entrante,
      }
    })
    empleados.value = data.data
    total.value     = data.total
  } finally {
    cargando.value = false
  }
}

const modalDesactivar = ref({ show: false, emp: null, error: '', procesando: false })

const abrirModalDesactivar = (emp) => {
  modalDesactivar.value = { show: true, emp, error: '', procesando: false }
}

const confirmarDesactivar = async () => {
  modalDesactivar.value.procesando = true
  modalDesactivar.value.error = ''
  try {
    await api.delete(`/empleados/${modalDesactivar.value.emp.id_emp}`)
    modalDesactivar.value.show = false
    cargarEmpleados()
  } catch (e) {
    modalDesactivar.value.error = e.response?.data?.message || 'Error al desactivar el empleado.'
  } finally {
    modalDesactivar.value.procesando = false
  }
}

watch(pagina, cargarEmpleados)

onMounted(async () => {
  cargarEmpleados()
  const [{ data: deptos }, { data: mods }] = await Promise.all([
    api.get('/departamentos'),
    api.get('/admin/modalidades-laborales'),
  ])
  departamentos.value      = deptos
  modalidadesLaborales.value = mods.filter(m => m.estado === 'ACTIVO')
})
</script>
