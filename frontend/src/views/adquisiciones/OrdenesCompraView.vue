<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Órdenes de Compra</h1>
      <button @click="abrirCrear" class="bg-amber-700 text-white px-4 py-2 rounded-lg text-sm hover:bg-amber-800">
        + Nueva orden
      </button>
    </div>

    <div class="flex gap-3 mb-4">
      <select v-model="filtroEstado" @change="cargar" class="border rounded-lg px-3 py-2 text-sm">
        <option value="">Todos los estados</option>
        <option value="BORRADOR">Borrador</option>
        <option value="ENVIADA">Enviada</option>
        <option value="RECIBIDA">Recibida</option>
      </select>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">#</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Proveedor</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Fecha</th>
            <th class="text-right px-4 py-3 text-gray-600 font-medium">Ítems</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Estado</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!ordenes.length">
            <td colspan="6" class="text-center py-8 text-gray-400">Sin órdenes</td>
          </tr>
          <tr v-for="o in ordenes" :key="o.id" class="border-b hover:bg-gray-50">
            <td class="px-4 py-3 text-gray-400 text-xs">{{ o.id }}</td>
            <td class="px-4 py-3 font-medium">{{ o.proveedor?.nombre }}</td>
            <td class="px-4 py-3 text-gray-500">{{ o.fecha }}</td>
            <td class="px-4 py-3 text-right text-gray-500">{{ o.detalles?.length }}</td>
            <td class="px-4 py-3">
              <span :class="{
                'bg-gray-100 text-gray-600':   o.estado === 'BORRADOR',
                'bg-blue-100 text-blue-700':   o.estado === 'ENVIADA',
                'bg-green-100 text-green-700': o.estado === 'RECIBIDA',
              }" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ o.estado }}</span>
            </td>
            <td class="px-4 py-3 flex gap-2 flex-wrap">
              <button @click="verDetalle(o)" class="text-xs text-blue-600 hover:text-blue-800">Ver</button>
              <button v-if="o.estado === 'BORRADOR'" @click="enviar(o.id)" class="text-xs text-amber-600 hover:text-amber-800">Enviar</button>
              <button v-if="o.estado === 'ENVIADA'" @click="recibir(o.id)" class="text-xs text-green-600 hover:text-green-800">Marcar recibida</button>
              <button v-if="o.estado === 'BORRADOR'" @click="eliminar(o.id)" class="text-xs text-red-500 hover:text-red-700">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal crear -->
    <div v-if="modalCrear.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
        <h2 class="text-lg font-bold mb-4">Nueva Orden de Compra</h2>
        <div class="grid grid-cols-2 gap-3 mb-4">
          <div>
            <label class="block text-xs text-gray-600 mb-1">Proveedor *</label>
            <select v-model="modalCrear.form.proveedor_id" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none">
              <option value="">Seleccionar...</option>
              <option v-for="p in proveedoresActivos" :key="p.id" :value="p.id">{{ p.nombre }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-gray-600 mb-1">Fecha *</label>
            <input v-model="modalCrear.form.fecha" type="date"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
          </div>
        </div>
        <div class="mb-4">
          <label class="block text-xs text-gray-600 mb-1">Observación</label>
          <textarea v-model="modalCrear.form.observacion" rows="2"
            class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none"></textarea>
        </div>

        <!-- Ítems -->
        <div class="border-t pt-4">
          <div class="flex justify-between items-center mb-3">
            <p class="text-sm font-semibold text-gray-700">Artículos a comprar</p>
            <button @click="agregarItem" class="text-xs text-amber-700 hover:text-amber-900 font-medium">+ Agregar</button>
          </div>
          <div v-for="(det, i) in modalCrear.form.detalles" :key="i" class="border rounded-lg p-3 bg-gray-50 mb-2">
            <div class="flex justify-between mb-2">
              <span class="text-xs text-gray-500">Ítem {{ i + 1 }}</span>
              <button @click="modalCrear.form.detalles.splice(i, 1)" class="text-red-400 text-xs">✕</button>
            </div>
            <div class="grid grid-cols-3 gap-2">
              <div class="col-span-2">
                <label class="block text-xs text-gray-500 mb-1">Artículo *</label>
                <select v-model="det.articulo_id" class="w-full border rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-amber-300 outline-none">
                  <option value="">Seleccionar...</option>
                  <option v-for="a in articulosActivos" :key="a.id" :value="a.id">{{ a.nombre }}</option>
                </select>
              </div>
              <div>
                <label class="block text-xs text-gray-500 mb-1">Cantidad *</label>
                <input v-model="det.cantidad" type="number" step="0.01" min="0.01"
                  class="w-full border rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-amber-300 outline-none" />
              </div>
              <div>
                <label class="block text-xs text-gray-500 mb-1">Precio unitario ($)</label>
                <input v-model="det.precio_unitario" type="number" step="0.01" min="0"
                  class="w-full border rounded px-2 py-1.5 text-sm focus:ring-1 focus:ring-amber-300 outline-none" />
              </div>
            </div>
          </div>
        </div>

        <p v-if="errorModal" class="text-red-600 text-sm mt-3">{{ errorModal }}</p>
        <div class="flex justify-end gap-3 mt-5">
          <button @click="modalCrear.show = false" class="px-4 py-2 text-sm text-gray-600">Cancelar</button>
          <button @click="guardarOrden" :disabled="guardando"
            class="bg-amber-700 text-white px-5 py-2 rounded-lg text-sm hover:bg-amber-800 disabled:opacity-50">
            {{ guardando ? 'Guardando...' : 'Guardar' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal ver detalle -->
    <div v-if="modalDetalle.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-xl p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-start mb-4">
          <h2 class="text-lg font-bold">Orden #{{ modalDetalle.orden?.id }}</h2>
          <button @click="modalDetalle.show = false" class="text-gray-400 hover:text-gray-600">✕</button>
        </div>
        <p class="text-sm text-gray-600 mb-1"><b>Proveedor:</b> {{ modalDetalle.orden?.proveedor?.nombre }}</p>
        <p class="text-sm text-gray-600 mb-4"><b>Fecha:</b> {{ modalDetalle.orden?.fecha }}</p>
        <table class="w-full text-sm border">
          <thead class="bg-gray-50">
            <tr>
              <th class="text-left px-3 py-2">Artículo</th>
              <th class="text-right px-3 py-2">Cantidad</th>
              <th class="text-right px-3 py-2">Precio Unit.</th>
              <th class="text-right px-3 py-2">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="d in modalDetalle.orden?.detalles" :key="d.id" class="border-t">
              <td class="px-3 py-2">{{ d.articulo?.nombre }}</td>
              <td class="px-3 py-2 text-right">{{ d.cantidad }}</td>
              <td class="px-3 py-2 text-right">${{ parseFloat(d.precio_unitario).toFixed(2) }}</td>
              <td class="px-3 py-2 text-right">${{ (d.cantidad * d.precio_unitario).toFixed(2) }}</td>
            </tr>
          </tbody>
          <tfoot class="bg-gray-50 border-t-2 font-semibold">
            <tr>
              <td colspan="3" class="px-3 py-2 text-right">Total:</td>
              <td class="px-3 py-2 text-right">
                ${{ (modalDetalle.orden?.detalles || []).reduce((s, d) => s + d.cantidad * d.precio_unitario, 0).toFixed(2) }}
              </td>
            </tr>
          </tfoot>
        </table>
        <p v-if="modalDetalle.orden?.observacion" class="text-sm text-gray-600 mt-3">
          <b>Observación:</b> {{ modalDetalle.orden.observacion }}
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

const ordenes       = ref([])
const filtroEstado  = ref('')
const guardando     = ref(false)
const errorModal    = ref('')
const proveedores   = ref([])
const articulos     = ref([])
const modalCrear    = ref({ show: false, form: { proveedor_id: '', fecha: '', observacion: '', detalles: [] } })
const modalDetalle  = ref({ show: false, orden: null })

const proveedoresActivos = computed(() => proveedores.value.filter(p => p.estado === 'ACTIVO'))
const articulosActivos   = computed(() => articulos.value.filter(a => a.estado === 'ACTIVO'))

async function cargar() {
  const params = filtroEstado.value ? { estado: filtroEstado.value } : {}
  const { data } = await api.get('/adquisiciones/ordenes', { params })
  ordenes.value = data
}

onMounted(async () => {
  const [p, a] = await Promise.all([
    api.get('/adquisiciones/proveedores'),
    api.get('/adquisiciones/articulos'),
  ])
  proveedores.value = p.data
  articulos.value   = a.data
  await cargar()
})

function abrirCrear() {
  const hoy = new Date().toISOString().split('T')[0]
  modalCrear.value = { show: true, form: { proveedor_id: '', fecha: hoy, observacion: '', detalles: [] } }
  errorModal.value = ''
}

function agregarItem() {
  modalCrear.value.form.detalles.push({ articulo_id: '', cantidad: 1, precio_unitario: 0 })
}

function verDetalle(o) {
  modalDetalle.value = { show: true, orden: o }
}

async function guardarOrden() {
  errorModal.value = ''
  if (!modalCrear.value.form.proveedor_id || !modalCrear.value.form.fecha) { errorModal.value = 'Proveedor y fecha requeridos.'; return }
  if (!modalCrear.value.form.detalles.length) { errorModal.value = 'Agregue al menos un artículo.'; return }
  guardando.value = true
  try {
    await api.post('/adquisiciones/ordenes', modalCrear.value.form)
    modalCrear.value.show = false
    await cargar()
  } catch (e) {
    errorModal.value = e.response?.data?.message || 'Error al guardar'
  } finally { guardando.value = false }
}

async function enviar(id) {
  if (!confirm('¿Marcar como enviada al proveedor?')) return
  await api.patch(`/adquisiciones/ordenes/${id}/enviar`)
  await cargar()
}

async function recibir(id) {
  if (!confirm('¿Confirmar recepción de esta orden? Se actualizará el stock de los artículos.')) return
  await api.patch(`/adquisiciones/ordenes/${id}/recibir`)
  await cargar()
}

async function eliminar(id) {
  if (!confirm('¿Eliminar esta orden?')) return
  await api.delete(`/adquisiciones/ordenes/${id}`)
  await cargar()
}
</script>
