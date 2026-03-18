<!-- ============================================================ -->
<!-- src/views/admin/RolesView.vue -->
<!-- ============================================================ -->
<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Gestión de Roles</h1>
      <button @click="abrirModalNuevo"
        class="bg-blue-700 text-white px-4 py-2 rounded-lg hover:bg-blue-800 transition text-sm">
        + Nuevo Rol
      </button>
    </div>

    <!-- Tabla de roles -->
    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Rol</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Estado</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="rol in roles" :key="rol.id" class="border-b hover:bg-gray-50">
            <td class="px-6 py-3 font-medium">{{ rol.descripcion }}</td>
            <td class="px-6 py-3">
              <span :class="rol.estado
                ? 'bg-green-100 text-green-700'
                : 'bg-red-100 text-red-700'"
                class="px-2 py-1 rounded-full text-xs font-medium">
                {{ rol.estado ? 'Activo' : 'Inactivo' }}
              </span>
            </td>
            <td class="px-6 py-3 flex gap-2">
              <button @click="editarRol(rol)"
                class="text-blue-600 hover:text-blue-800 text-xs font-medium">Editar</button>
              <button @click="gestionarOpciones(rol)"
                class="text-purple-600 hover:text-purple-800 text-xs font-medium">Opciones</button>
              <button @click="desactivarRol(rol.id)"
                class="text-red-500 hover:text-red-700 text-xs font-medium">Desactivar</button>
            </td>
          </tr>
          <tr v-if="!roles.length">
            <td colspan="3" class="px-6 py-8 text-center text-gray-400">Sin roles registrados</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal Rol -->
    <div v-if="modal.show"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-bold mb-4">{{ modal.titulo }}</h2>
        <form @submit.prevent="guardarRol" class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
            <input v-model="modal.form.descripcion" type="text" maxlength="20"
              class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 outline-none"
              required />
          </div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="modal.show = false"
              class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
            <button type="submit"
              class="bg-blue-700 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-800">
              Guardar
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Opciones del Rol -->
    <div v-if="modalOpciones.show"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl p-6 max-h-[80vh] flex flex-col">
        <h2 class="text-lg font-bold mb-4">
          Opciones del Rol: {{ modalOpciones.rol?.descripcion }}
        </h2>

        <div class="flex-1 overflow-y-auto space-y-2">
          <template v-for="(items, cat) in opcionesAgrupadas" :key="cat">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider pt-2">{{ cat }}</p>
            <label v-for="op in items" :key="op.id"
              class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-gray-50 cursor-pointer">
              <input type="checkbox" :value="op.id"
                v-model="modalOpciones.seleccionadas"
                class="rounded text-blue-700" />
              <span class="text-sm">{{ op.descripcion }}</span>
              <span class="text-xs text-gray-400 ml-auto">{{ op.url }}</span>
            </label>
          </template>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t mt-4">
          <button @click="modalOpciones.show = false"
            class="px-4 py-2 text-sm text-gray-600">Cancelar</button>
          <button @click="guardarOpciones"
            class="bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
            Guardar permisos
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

const roles    = ref([])
const opciones = ref([])

const modal = ref({ show: false, titulo: '', form: { id: null, descripcion: '' } })
const modalOpciones = ref({ show: false, rol: null, seleccionadas: [] })

const opcionesAgrupadas = computed(() =>
  opciones.value.reduce((acc, o) => {
    if (!acc[o.categoria]) acc[o.categoria] = []
    acc[o.categoria].push(o)
    return acc
  }, {})
)

async function cargar() {
  const [r, o] = await Promise.all([api.get('/roles'), api.get('/opciones')])
  roles.value   = r.data
  opciones.value = o.data
}

function abrirModalNuevo() {
  modal.value = { show: true, titulo: 'Nuevo Rol', form: { id: null, descripcion: '' } }
}

function editarRol(rol) {
  modal.value = { show: true, titulo: 'Editar Rol',
                  form: { id: rol.id, descripcion: rol.descripcion } }
}

async function guardarRol() {
  if (modal.value.form.id) {
    await api.put(`/roles/${modal.value.form.id}`, modal.value.form)
  } else {
    await api.post('/roles', modal.value.form)
  }
  modal.value.show = false
  cargar()
}

async function desactivarRol(id) {
  if (confirm('¿Desactivar este rol?')) {
    await api.delete(`/roles/${id}`)
    cargar()
  }
}

async function gestionarOpciones(rol) {
  // Cargar opciones actuales del rol
  const { data } = await api.get(`/roles/${rol.id}`)
  modalOpciones.value = {
    show: true, rol,
    seleccionadas: data.opciones?.map(o => o.id_opcion) || [],
  }
}

async function guardarOpciones() {
  await api.post(`/roles/${modalOpciones.value.rol.id}/opciones`, {
    opciones: modalOpciones.value.seleccionadas,
  })
  modalOpciones.value.show = false
}

onMounted(cargar)
</script>
