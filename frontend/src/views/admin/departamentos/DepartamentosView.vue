<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-800">Departamentos</h1>
      <button @click="abrirModal()"
        class="bg-[#0b5447] text-white px-4 py-2 rounded-lg hover:bg-[#00372e] text-sm font-medium">
        + Nuevo Departamento
      </button>
    </div>

    <!-- Filtro estado -->
    <div class="flex gap-3">
      <button @click="filtroEstado = 'ACTIVO'"
        :class="filtroEstado === 'ACTIVO' ? 'bg-[#0b5447] text-white' : 'bg-white text-gray-600 border'"
        class="px-4 py-1.5 rounded-lg text-sm font-medium">Activos</button>
      <button @click="filtroEstado = 'INACTIVO'"
        :class="filtroEstado === 'INACTIVO' ? 'bg-gray-600 text-white' : 'bg-white text-gray-600 border'"
        class="px-4 py-1.5 rounded-lg text-sm font-medium">Inactivos</button>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">#</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Nombre</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Área Padre</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Siglas</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Estado</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando">
            <td colspan="6" class="text-center py-8 text-gray-400">Cargando...</td>
          </tr>
          <tr v-else-if="departamentosFiltrados.length === 0">
            <td colspan="6" class="text-center py-8 text-gray-400">No hay departamentos registrados.</td>
          </tr>
          <tr v-for="dep in departamentosFiltrados" :key="dep.id_depto"
            class="border-b hover:bg-gray-50"
            :class="dep.estado === 'INACTIVO' ? 'opacity-60' : ''">
            <td class="px-6 py-3 text-gray-500">{{ dep.id_depto }}</td>
            <td class="px-6 py-3 font-medium text-gray-800">
              <span v-if="dep.padre_id" class="text-gray-400 mr-1">↳</span>
              {{ dep.nombre_depto }}
            </td>
            <td class="px-6 py-3 text-gray-500 text-xs">{{ dep.padre?.nombre_depto || '—' }}</td>
            <td class="px-6 py-3 text-gray-600">{{ dep.centro_de_costo || '—' }}</td>
            <td class="px-6 py-3">
              <span :class="dep.estado === 'ACTIVO' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                class="px-2 py-0.5 rounded-full text-xs font-medium">
                {{ dep.estado }}
              </span>
            </td>
            <td class="px-6 py-3">
              <div class="flex gap-1 flex-wrap">
                <button @click="abrirModal(dep)"
                  class="inline-flex items-center px-2.5 py-1 rounded-md border border-amber-300 text-xs text-amber-700 hover:bg-amber-50 font-medium transition-colors">
                  Editar
                </button>
                <button v-if="dep.estado === 'ACTIVO'" @click="abrirModalEstado(dep, 'inactivar')"
                  class="inline-flex items-center px-2.5 py-1 rounded-md border border-red-200 text-xs text-red-600 hover:bg-red-50 font-medium transition-colors">
                  Inactivar
                </button>
                <button v-else @click="abrirModalEstado(dep, 'activar')"
                  class="inline-flex items-center px-2.5 py-1 rounded-md border border-green-300 text-xs text-green-700 hover:bg-green-50 font-medium transition-colors">
                  Activar
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal Activar / Inactivar -->
    <div v-if="modalEstado.show" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-md space-y-4">
        <h2 class="text-lg font-semibold text-gray-700">
          {{ modalEstado.accion === 'inactivar' ? 'Inactivar Departamento' : 'Activar Departamento' }}
        </h2>
        <p class="text-sm text-gray-500">{{ modalEstado.dep?.nombre_depto }}</p>
        <p class="text-sm text-gray-600">
          {{ modalEstado.accion === 'inactivar' ? 'Confirmar inactivación' : 'Confirmar activación' }}
        </p>
        <div v-if="modalEstado.error" class="text-red-600 text-sm bg-red-50 rounded p-2">{{ modalEstado.error }}</div>
        <div class="flex justify-end gap-3">
          <button @click="modalEstado.show = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
          <button @click="confirmarEstado" :disabled="modalEstado.procesando"
            :class="modalEstado.accion === 'inactivar' ? 'bg-red-600 hover:bg-red-700' : 'bg-green-600 hover:bg-green-700'"
            class="px-4 py-2 rounded-lg text-white text-sm disabled:opacity-50">
            {{ modalEstado.procesando ? 'Procesando...'
               : (modalEstado.accion === 'inactivar' ? 'Confirmar Inactivación' : 'Confirmar Activación') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="modal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-md overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4" style="background-color:#0b5447;">
          <h2 class="text-lg font-semibold text-white">{{ form.id_depto ? 'Editar Departamento' : 'Nuevo Departamento' }}</h2>
          <button @click="modal = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-6 space-y-4">
        <div v-if="!form.editando">
          <label class="block text-sm font-medium text-gray-600 mb-1">ID (opcional)</label>
          <input v-model.number="form.id_nuevo" type="number" placeholder="Ej: 74 — vacío = automático"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          <p class="text-xs text-gray-400 mt-1">Si lo dejas vacío se asigna automáticamente.</p>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Nombre *</label>
          <input v-model="form.nombre_depto" type="text" placeholder="Ej: RECURSOS HUMANOS"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Área Padre</label>
          <select v-model="form.padre_id"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
            <option :value="null">— Sin área padre (nivel raíz) —</option>
            <option v-for="dep in padresDisponibles" :key="dep.id_depto" :value="dep.id_depto">
              {{ dep.nombre_depto }}
            </option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Siglas</label>
          <input v-model="form.centro_de_costo" type="text" placeholder="Ej: RRH"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
        </div>

        <div v-if="error" class="text-red-600 text-sm bg-red-50 rounded p-2">{{ error }}</div>

        <div class="flex justify-end gap-3 pt-2">
          <button @click="modal = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
          <button @click="guardar" :disabled="guardando"
            class="px-4 py-2 rounded-lg bg-[#0b5447] text-white text-sm hover:bg-[#00372e] disabled:opacity-50">
            {{ guardando ? 'Guardando...' : 'Guardar' }}
          </button>
        </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

const departamentos  = ref([])
const cargando       = ref(false)
const modal          = ref(false)
const guardando      = ref(false)
const error          = ref('')
const filtroEstado   = ref('ACTIVO')
const form           = ref({ id_depto: null, nombre_depto: '', centro_de_costo: '', padre_id: null })

const departamentosFiltrados = computed(() =>
  departamentos.value.filter(d => d.estado === filtroEstado.value)
)

// Solo departamentos activos como opciones de padre, excluyendo el que se edita
const padresDisponibles = computed(() =>
  departamentos.value.filter(d => d.estado === 'ACTIVO' && d.id_depto !== form.value.id_depto)
)

const cargar = async () => {
  cargando.value = true
  const { data } = await api.get('/admin/departamentos')
  departamentos.value = data
  cargando.value = false
}

const abrirModal = (dep = null) => {
  error.value = ''
  form.value = dep
    ? { editando: true,  id_depto: dep.id_depto, nombre_depto: dep.nombre_depto, centro_de_costo: dep.centro_de_costo, padre_id: dep.padre_id ?? null }
    : { editando: false, id_depto: null, id_nuevo: null, nombre_depto: '', centro_de_costo: '', padre_id: null }
  modal.value = true
}

const guardar = async () => {
  if (!form.value.nombre_depto) { error.value = 'El nombre es requerido.'; return }
  guardando.value = true
  error.value = ''
  try {
    if (form.value.editando) {
      await api.put(`/admin/departamentos/${form.value.id_depto}`, form.value)
    } else {
      await api.post('/admin/departamentos', { ...form.value, id_depto: form.value.id_nuevo || undefined })
    }
    modal.value = false
    cargar()
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al guardar.'
  } finally {
    guardando.value = false
  }
}

const modalEstado = ref({ show: false, dep: null, accion: '', error: '', procesando: false })

const abrirModalEstado = (dep, accion) => {
  modalEstado.value = { show: true, dep, accion, error: '', procesando: false }
}

const confirmarEstado = async () => {
  const { dep, accion } = modalEstado.value
  modalEstado.value.procesando = true
  modalEstado.value.error = ''
  try {
    await api.patch(`/admin/departamentos/${dep.id_depto}/${accion}`)
    modalEstado.value.show = false
    cargar()
  } catch (e) {
    modalEstado.value.error = e.response?.data?.message || `Error al ${accion}.`
  } finally {
    modalEstado.value.procesando = false
  }
}

onMounted(cargar)
</script>
