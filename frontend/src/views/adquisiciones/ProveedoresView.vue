<template>
  <div>
    <div class="flex justify-between items-center pb-4 mb-5 border-b border-gray-100">
      <h1 class="text-xl font-bold text-gray-800 tracking-tight">Proveedores</h1>
      <button @click="abrirCrear" class="inline-flex items-center gap-1.5 bg-[#4a5e3a] text-white px-3.5 py-2 rounded-lg text-sm hover:bg-[#3a4e2a] transition-colors font-medium">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        Nuevo proveedor
      </button>
    </div>

    <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 mb-4">
      <div class="flex gap-3 flex-wrap items-end">
        <div class="flex-1 min-w-[200px]">
          <label class="block text-xs font-medium text-gray-500 mb-1">Buscar</label>
          <input v-model="busqueda" type="text" placeholder="RUC o nombre del proveedor..."
            class="w-full border border-gray-200 bg-white rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#4a5e3a]/30 focus:border-[#4a5e3a] outline-none" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Estado</label>
          <select v-model="filtroEstado" class="border border-gray-200 bg-white rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#4a5e3a]/30 focus:border-[#4a5e3a] outline-none">
            <option value="">Todos los estados</option>
            <option value="ACTIVO">Activos</option>
            <option value="INACTIVO">Inactivos</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Tipo</label>
          <select v-model="filtroTipo" class="border border-gray-200 bg-white rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#4a5e3a]/30 focus:border-[#4a5e3a] outline-none">
            <option value="">Todos</option>
            <option value="bienes">Solo Bienes</option>
            <option value="taller">Solo Transporte</option>
          </select>
        </div>
        <div class="ml-auto self-center">
          <span v-if="proveedoresFiltrados.length > 0" class="inline-flex items-center gap-1 text-xs text-[#4a5e3a] bg-green-50 border border-green-200 px-3 py-1.5 rounded-lg font-medium">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            {{ proveedoresFiltrados.length }} de {{ proveedores.length }} proveedores
          </span>
          <span v-else class="inline-flex items-center gap-1 text-xs text-red-600 bg-red-50 border border-red-100 px-3 py-1.5 rounded-lg">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
            Sin resultados
          </span>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr style="background-color: #4a5e3a;">
            <th class="text-left px-4 py-3 text-white/80 font-semibold whitespace-nowrap text-[11px] uppercase tracking-wide">RUC</th>
            <th class="text-left px-4 py-3 text-white/80 font-semibold whitespace-nowrap text-[11px] uppercase tracking-wide">Nombre</th>
            <th class="text-left px-4 py-3 text-white/80 font-semibold whitespace-nowrap text-[11px] uppercase tracking-wide">Contacto</th>
            <th class="text-left px-4 py-3 text-white/80 font-semibold whitespace-nowrap text-[11px] uppercase tracking-wide">Email</th>
            <th class="text-left px-4 py-3 text-white/80 font-semibold whitespace-nowrap text-[11px] uppercase tracking-wide">Estado</th>
            <th class="text-left px-4 py-3 text-white/80 font-semibold whitespace-nowrap text-[11px] uppercase tracking-wide">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!proveedoresFiltrados.length">
            <td colspan="6" class="py-16 text-center">
              <div class="flex flex-col items-center gap-2 text-gray-300">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z"/></svg>
                <p class="text-sm text-gray-400">Sin proveedores registrados</p>
              </div>
            </td>
          </tr>
          <tr v-for="p in proveedoresPaginados" :key="p.id" class="border-b border-gray-100 hover:bg-green-50/60 transition-colors">
            <td class="px-4 py-3 font-mono text-xs whitespace-nowrap text-gray-600">{{ p.ruc }}</td>
            <td class="px-4 py-3 font-medium text-gray-800">{{ p.nombre }}</td>
            <td class="px-4 py-3 text-gray-500 text-sm">{{ p.contacto || '—' }}</td>
            <td class="px-4 py-3 text-gray-500 text-sm">{{ p.email || '—' }}</td>
            <td class="px-4 py-3 whitespace-nowrap">
              <div class="flex items-center gap-1.5 flex-wrap">
                <span v-if="p.es_taller"
                  class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-blue-100 text-blue-700 border border-blue-200">
                  Taller
                </span>
                <span :class="p.estado === 'ACTIVO' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-gray-100 text-gray-500 border border-gray-200'"
                  class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold">{{ p.estado }}</span>
              </div>
            </td>
            <td class="px-4 py-3 whitespace-nowrap">
              <div class="flex gap-1.5">
                <button @click="abrirEditar(p)" class="inline-flex items-center px-2.5 py-1 rounded-md border border-blue-200 text-xs text-blue-700 hover:bg-blue-50 font-medium transition-colors">Editar</button>
                <button v-if="p.estado === 'ACTIVO'" @click="inactivar(p.id)" class="inline-flex items-center px-2.5 py-1 rounded-md border border-red-200 text-xs text-red-600 hover:bg-red-50 font-medium transition-colors">Inactivar</button>
                <button v-else @click="activar(p.id)" class="inline-flex items-center px-2.5 py-1 rounded-md border border-green-200 text-xs text-green-700 hover:bg-green-50 font-medium transition-colors">Activar</button>
              </div>
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
      <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
        <div class="px-6 py-4 flex-shrink-0" style="background-color:#4a5e3a;">
          <h2 class="text-lg font-bold text-white">{{ modal.editando ? 'Editar' : 'Nuevo' }} Proveedor</h2>
        </div>
        <div class="p-6 overflow-y-auto">

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
        <div class="mb-3">
          <label class="block text-xs text-gray-600 mb-1">Dirección</label>
          <input v-model="modal.form.direccion" v-uppercase type="text" maxlength="300"
            class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
        </div>
        <label class="flex items-center gap-2 cursor-pointer select-none mb-4">
          <input type="checkbox" v-model="modal.form.es_taller" class="w-4 h-4 rounded" />
          <span class="text-xs text-gray-700 font-medium">También aparece como taller en Transportes</span>
        </label>

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
            class="bg-green-700 text-white px-5 py-2 rounded-lg text-sm hover:bg-green-800 disabled:opacity-50">
            {{ guardando ? 'Guardando...' : 'Guardar' }}
          </button>
        </div>
        </div><!-- /p-6 overflow-y-auto -->
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
const filtroTipo   = ref('bienes')
const guardando    = ref(false)
const errorModal   = ref('')
const paginaActual = ref(1)
const porPagina    = ref(25)
const modal = ref({ show: false, editando: false, id: null, form: { ruc: '', nombre: '', direccion: '', contacto: '', email: '', telefono: '', es_taller: false, catalogo: [] } })

const proveedoresFiltrados = computed(() =>
  proveedores.value.filter(p => {
    const q = busqueda.value.toLowerCase()
    const coincide = !q || p.nombre.toLowerCase().includes(q) || (p.ruc || '').toLowerCase().includes(q)
    const porEstado = !filtroEstado.value || p.estado === filtroEstado.value
    const porTipo = !filtroTipo.value
      || (filtroTipo.value === 'bienes'  && p.es_proveedor_bienes)
      || (filtroTipo.value === 'taller'  && p.es_taller)
    return coincide && porEstado && porTipo
  })
)

const totalPaginas = computed(() => Math.max(1, Math.ceil(proveedoresFiltrados.value.length / porPagina.value)))

const proveedoresPaginados = computed(() => {
  const inicio = (paginaActual.value - 1) * porPagina.value
  return proveedoresFiltrados.value.slice(inicio, inicio + porPagina.value)
})

watch([busqueda, filtroEstado, filtroTipo], () => { paginaActual.value = 1 })

async function cargar() {
  const { data } = await api.get('/adquisiciones/proveedores')
  proveedores.value = data
}

onMounted(cargar)

function abrirCrear() {
  modal.value = { show: true, editando: false, id: null, form: { ruc: '', nombre: '', direccion: '', contacto: '', email: '', telefono: '', es_taller: false, catalogo: [] } }
  errorModal.value = ''
}

function abrirEditar(p) {
  modal.value = { show: true, editando: true, id: p.id, form: { ruc: p.ruc, nombre: p.nombre, direccion: p.direccion, contacto: p.contacto, email: p.email, telefono: p.telefono, es_taller: p.es_taller, catalogo: (p.catalogo || []).map(c => ({ ...c })) } }
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
