<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Proveedores</h1>
      <button @click="abrirCrear" class="bg-amber-700 text-white px-4 py-2 rounded-lg text-sm hover:bg-amber-800">
        + Nuevo proveedor
      </button>
    </div>

    <div class="flex gap-3 mb-3 flex-wrap">
      <input v-model="busqueda" type="text" placeholder="Buscar por RUC o nombre..."
        class="border rounded-lg px-3 py-2 text-sm w-72 focus:ring-2 focus:ring-amber-300 outline-none" />
      <select v-model="filtroEstado" class="border rounded-lg px-3 py-2 text-sm">
        <option value="">Todos los estados</option>
        <option value="ACTIVO">Activos</option>
        <option value="INACTIVO">Inactivos</option>
      </select>
    </div>

    <!-- Contador -->
    <div class="mb-3">
      <span v-if="proveedoresFiltrados.length > 0"
        class="inline-block bg-green-100 text-green-800 text-xs font-semibold px-3 py-1 rounded-full border border-green-300">
        Se encontraron {{ proveedoresFiltrados.length }} de {{ proveedores.length }} proveedores
      </span>
      <span v-else
        class="inline-block bg-red-100 text-red-700 text-xs font-semibold px-3 py-1 rounded-full border border-red-300">
        No se encontraron proveedores (0 de {{ proveedores.length }})
      </span>
    </div>

    <div class="bg-white rounded-xl shadow overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr style="background-color: #4a5e3a;">
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">RUC</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">Nombre</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">Contacto</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">Email</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">Catálogo</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">Estado</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!proveedoresFiltrados.length">
            <td colspan="7" class="text-center py-8 text-gray-400">Sin proveedores</td>
          </tr>
          <tr v-for="p in proveedoresPaginados" :key="p.id" class="border-b hover:bg-amber-50">
            <td class="px-4 py-3 font-mono text-xs whitespace-nowrap">{{ p.ruc }}</td>
            <td class="px-4 py-3 font-medium">{{ p.nombre }}</td>
            <td class="px-4 py-3 text-gray-500">{{ p.contacto || '-' }}</td>
            <td class="px-4 py-3 text-gray-500">{{ p.email || '-' }}</td>
            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ p.catalogo?.length || 0 }} ítem(s)</td>
            <td class="px-4 py-3 whitespace-nowrap">
              <span :class="p.estado === 'ACTIVO' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                class="px-2 py-0.5 rounded-full text-xs font-medium">{{ p.estado }}</span>
            </td>
            <td class="px-4 py-3 whitespace-nowrap flex gap-2">
              <button @click="abrirEditar(p)" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Editar</button>
              <button v-if="p.estado === 'ACTIVO'" @click="inactivar(p.id)" class="text-xs text-red-500 hover:text-red-700">Inactivar</button>
              <button v-else @click="activar(p.id)" class="text-xs text-green-600 hover:text-green-800">Activar</button>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Paginador -->
      <div class="flex items-center justify-between px-4 py-3 border-t text-sm text-gray-600 flex-wrap gap-2">
        <div class="flex items-center gap-2">
          <span>Filas por página:</span>
          <select v-model="porPagina" @change="paginaActual = 1" class="border rounded px-2 py-1 text-sm">
            <option :value="10">10</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
            <option :value="99999">Todos</option>
          </select>
        </div>
        <span class="text-gray-500">
          {{ Math.min((paginaActual - 1) * porPagina + 1, proveedoresFiltrados.length) }}–{{ Math.min(paginaActual * porPagina, proveedoresFiltrados.length) }}
          de {{ proveedoresFiltrados.length }}
        </span>
        <div class="flex items-center gap-1">
          <button @click="paginaActual = 1" :disabled="paginaActual === 1"
            class="px-2 py-1 rounded border disabled:opacity-40 hover:bg-gray-50 text-xs">«</button>
          <button @click="paginaActual--" :disabled="paginaActual === 1"
            class="px-3 py-1 rounded border disabled:opacity-40 hover:bg-gray-50">‹</button>
          <span class="px-3 py-1 font-medium">{{ paginaActual }} / {{ totalPaginas }}</span>
          <button @click="paginaActual++" :disabled="paginaActual === totalPaginas"
            class="px-3 py-1 rounded border disabled:opacity-40 hover:bg-gray-50">›</button>
          <button @click="paginaActual = totalPaginas" :disabled="paginaActual === totalPaginas"
            class="px-2 py-1 rounded border disabled:opacity-40 hover:bg-gray-50 text-xs">»</button>
        </div>
      </div>
    </div>

    <!-- Modal proveedor -->
    <div v-if="modal.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
        <h2 class="text-lg font-bold mb-4" style="color:#4a5e3a">{{ modal.editando ? 'Editar' : 'Nuevo' }} Proveedor</h2>

        <div class="grid grid-cols-2 gap-3 mb-3">
          <div>
            <label class="block text-xs text-gray-600 mb-1">RUC *</label>
            <input v-model="modal.form.ruc" v-uppercase type="text" maxlength="20"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
          </div>
          <div>
            <label class="block text-xs text-gray-600 mb-1">Teléfono</label>
            <input v-model="modal.form.telefono" v-uppercase type="text" maxlength="20"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
          </div>
        </div>
        <div class="mb-3">
          <label class="block text-xs text-gray-600 mb-1">Nombre *</label>
          <input v-model="modal.form.nombre" v-uppercase type="text" maxlength="200"
            class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
        </div>
        <div class="grid grid-cols-2 gap-3 mb-3">
          <div>
            <label class="block text-xs text-gray-600 mb-1">Contacto</label>
            <input v-model="modal.form.contacto" v-uppercase type="text" maxlength="100"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
          </div>
          <div>
            <label class="block text-xs text-gray-600 mb-1">Email</label>
            <input v-model="modal.form.email" type="email" maxlength="100"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
          </div>
        </div>
        <div class="mb-4">
          <label class="block text-xs text-gray-600 mb-1">Dirección</label>
          <input v-model="modal.form.direccion" v-uppercase type="text" maxlength="300"
            class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
        </div>

        <!-- Catálogo -->
        <div class="border-t pt-4">
          <div class="flex justify-between items-center mb-3">
            <p class="text-sm font-semibold text-gray-700">Catálogo de productos/servicios</p>
            <button @click="agregarCatalogo" class="text-xs text-amber-700 hover:text-amber-900 font-medium">+ Agregar ítem</button>
          </div>
          <div v-for="(item, i) in modal.form.catalogo" :key="i" class="border rounded-lg p-3 bg-gray-50 mb-2">
            <div class="flex justify-between items-start mb-2">
              <span class="text-xs text-gray-500 font-medium">Ítem {{ i + 1 }}</span>
              <button @click="modal.form.catalogo.splice(i, 1)" class="text-red-400 hover:text-red-600 text-xs">✕</button>
            </div>
            <div class="grid grid-cols-3 gap-2">
              <div class="col-span-2">
                <label class="block text-xs text-gray-500 mb-1">Descripción *</label>
                <input v-model="item.descripcion" v-uppercase type="text" maxlength="300"
                  class="w-full border rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-amber-300 outline-none" />
              </div>
              <div>
                <label class="block text-xs text-gray-500 mb-1">Unidad</label>
                <input v-model="item.unidad_medida" v-uppercase type="text" maxlength="50"
                  class="w-full border rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-amber-300 outline-none" />
              </div>
              <div class="col-span-3">
                <label class="block text-xs text-gray-500 mb-1">Precio referencial ($)</label>
                <input v-model="item.precio_referencial" type="number" step="0.01" min="0"
                  class="w-40 border rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-amber-300 outline-none" />
              </div>
            </div>
          </div>
        </div>

        <p v-if="errorModal" class="text-red-600 text-sm mt-3">{{ errorModal }}</p>
        <div class="flex justify-end gap-3 mt-5">
          <button @click="modal.show = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
          <button @click="guardar" :disabled="guardando"
            class="bg-amber-700 text-white px-5 py-2 rounded-lg text-sm hover:bg-amber-800 disabled:opacity-50">
            {{ guardando ? 'Guardando...' : 'Guardar' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import api from '@/services/api'

const proveedores  = ref([])
const busqueda     = ref('')
const filtroEstado = ref('ACTIVO')
const guardando    = ref(false)
const errorModal   = ref('')
const paginaActual = ref(1)
const porPagina    = ref(25)
const modal = ref({ show: false, editando: false, id: null, form: { ruc: '', nombre: '', direccion: '', contacto: '', email: '', telefono: '', catalogo: [] } })

const proveedoresFiltrados = computed(() =>
  proveedores.value.filter(p => {
    const q = busqueda.value.toLowerCase()
    const coincide = !q || p.nombre.toLowerCase().includes(q) || p.ruc.includes(q)
    return coincide && (!filtroEstado.value || p.estado === filtroEstado.value)
  })
)

const totalPaginas = computed(() => Math.max(1, Math.ceil(proveedoresFiltrados.value.length / porPagina.value)))

const proveedoresPaginados = computed(() => {
  const inicio = (paginaActual.value - 1) * porPagina.value
  return proveedoresFiltrados.value.slice(inicio, inicio + porPagina.value)
})

watch([busqueda, filtroEstado], () => { paginaActual.value = 1 })

async function cargar() {
  const { data } = await api.get('/adquisiciones/proveedores')
  proveedores.value = data
}

onMounted(cargar)

function abrirCrear() {
  modal.value = { show: true, editando: false, id: null, form: { ruc: '', nombre: '', direccion: '', contacto: '', email: '', telefono: '', catalogo: [] } }
  errorModal.value = ''
}

function abrirEditar(p) {
  modal.value = { show: true, editando: true, id: p.id, form: { ruc: p.ruc, nombre: p.nombre, direccion: p.direccion, contacto: p.contacto, email: p.email, telefono: p.telefono, catalogo: (p.catalogo || []).map(c => ({ ...c })) } }
  errorModal.value = ''
}

function agregarCatalogo() {
  modal.value.form.catalogo.push({ descripcion: '', unidad_medida: '', precio_referencial: 0 })
}

async function guardar() {
  errorModal.value = ''
  if (!modal.value.form.ruc || !modal.value.form.nombre) { errorModal.value = 'RUC y nombre son requeridos.'; return }
  guardando.value = true
  try {
    if (modal.value.editando) {
      await api.put(`/adquisiciones/proveedores/${modal.value.id}`, modal.value.form)
    } else {
      await api.post('/adquisiciones/proveedores', modal.value.form)
    }
    modal.value.show = false
    await cargar()
  } catch (e) {
    errorModal.value = e.response?.data?.message || 'Error al guardar'
  } finally { guardando.value = false }
}

async function inactivar(id) {
  if (!confirm('¿Inactivar este proveedor?')) return
  await api.patch(`/adquisiciones/proveedores/${id}/inactivar`)
  await cargar()
}

async function activar(id) {
  await api.patch(`/adquisiciones/proveedores/${id}/activar`)
  await cargar()
}
</script>
