<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Órdenes de Compra</h1>
      <button @click="abrirCrear" class="text-white px-4 py-2 rounded-lg text-sm hover:opacity-90" style="background-color:#4a5e3a;">
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
            <th class="text-right px-4 py-3 text-gray-600 font-medium">Total</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Estado</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!ordenes.length">
            <td colspan="7" class="text-center py-8 text-gray-400">Sin órdenes</td>
          </tr>
          <tr v-for="o in ordenes" :key="o.id" class="border-b hover:bg-gray-50">
            <td class="px-4 py-3 text-gray-400 text-xs">{{ o.id }}</td>
            <td class="px-4 py-3 font-medium">{{ o.proveedor?.nombre }}</td>
            <td class="px-4 py-3 text-gray-500">{{ o.fecha }}</td>
            <td class="px-4 py-3 text-right text-gray-500">{{ o.detalles?.length }}</td>
            <td class="px-4 py-3 text-right font-semibold">
              ${{ (o.detalles || []).reduce((s,d) => s + d.cantidad * d.precio_unitario, 0).toFixed(2) }}
            </td>
            <td class="px-4 py-3">
              <span :class="{
                'bg-gray-100 text-gray-600':   o.estado === 'BORRADOR',
                'bg-blue-100 text-blue-700':   o.estado === 'ENVIADA',
                'bg-green-100 text-green-700': o.estado === 'RECIBIDA',
              }" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ o.estado }}</span>
            </td>
            <td class="px-4 py-3 flex gap-2 flex-wrap">
              <button @click="verDetalle(o)" class="text-xs text-blue-600 hover:text-blue-800">Ver</button>
              <button v-if="o.estado === 'BORRADOR'" @click="enviar(o.id)" class="text-xs text-[#4a5e3a] hover:opacity-70 font-medium">Enviar</button>
              <button v-if="o.estado === 'ENVIADA'" @click="recibir(o.id)" class="text-xs text-green-600 hover:text-green-800 font-medium">Marcar recibida</button>
              <button v-if="o.estado === 'BORRADOR'" @click="eliminar(o.id)" class="text-xs text-red-500 hover:text-red-700">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ══ MODAL CREAR — estilo facturación ══ -->
    <div v-if="modalCrear.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl p-6 max-h-[95vh] overflow-y-auto">
        <h2 class="text-lg font-bold mb-4">Nueva Orden de Compra</h2>

        <!-- Cabecera -->
        <div class="grid grid-cols-3 gap-3 mb-5">
          <div>
            <label class="block text-xs text-gray-600 mb-1">Proveedor *</label>
            <select v-model="modalCrear.form.proveedor_id" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 outline-none" style="--tw-ring-color:#4a5e3a;">
              <option value="">Seleccionar...</option>
              <option v-for="p in proveedoresActivos" :key="p.id" :value="p.id">{{ p.nombre }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs text-gray-600 mb-1">Fecha *</label>
            <input v-model="modalCrear.form.fecha" type="date"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 outline-none" />
          </div>
          <div>
            <label class="block text-xs text-gray-600 mb-1">Observación</label>
            <input v-model="modalCrear.form.observacion" type="text"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 outline-none" />
          </div>
        </div>

        <!-- Buscador de artículos -->
        <div class="mb-3 relative">
          <label class="block text-xs text-gray-600 mb-1">Buscar artículo por código o descripción</label>
          <input v-model="busquedaArticulo" @input="filtrarArticulos" type="text"
            placeholder="Escribe código o nombre del artículo..."
            class="w-full border rounded px-3 py-2 text-sm focus:ring-2 outline-none" />
          <div v-if="articulosFiltrados.length && busquedaArticulo"
            class="absolute z-10 bg-white border rounded-lg shadow-lg w-full mt-1 max-h-48 overflow-y-auto">
            <div v-for="a in articulosFiltrados" :key="a.id"
              @click="agregarArticulo(a)"
              class="px-4 py-2 text-sm hover:bg-gray-100 cursor-pointer flex justify-between items-center">
              <div>
                <span class="font-mono text-xs text-gray-400 mr-2">{{ a.codigo }}</span>
                <span class="font-medium">{{ a.nombre }}</span>
                <span v-if="a.marca" class="text-gray-400 text-xs ml-1">({{ a.marca }})</span>
              </div>
              <span class="text-xs text-gray-400">Stock: {{ a.stock_actual }} {{ a.unidad_medida }}</span>
            </div>
          </div>
        </div>

        <!-- Tabla de ítems -->
        <div class="border rounded-lg overflow-hidden mb-4">
          <table class="w-full text-sm">
            <thead style="background-color:#4a5e3a;" class="text-white">
              <tr>
                <th class="text-left px-3 py-2 font-medium">Código</th>
                <th class="text-left px-3 py-2 font-medium">Descripción</th>
                <th class="text-left px-3 py-2 font-medium">Marca</th>
                <th class="text-right px-3 py-2 font-medium w-24">Cantidad</th>
                <th class="text-right px-3 py-2 font-medium w-28">P. Unitario</th>
                <th class="text-right px-3 py-2 font-medium w-24">Subtotal</th>
                <th class="w-8"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!modalCrear.form.detalles.length">
                <td colspan="7" class="text-center py-6 text-gray-400 text-sm italic">
                  Busca un artículo arriba para agregarlo
                </td>
              </tr>
              <tr v-for="(det, i) in modalCrear.form.detalles" :key="i"
                class="border-t hover:bg-gray-50">
                <td class="px-3 py-1.5 font-mono text-xs text-gray-500">{{ det.codigo }}</td>
                <td class="px-3 py-1.5">{{ det.nombre }}</td>
                <td class="px-3 py-1.5 text-gray-500 text-xs">{{ det.marca || '-' }}</td>
                <td class="px-3 py-1.5">
                  <input v-model="det.cantidad" type="number" step="1" min="1"
                    class="w-full border rounded px-2 py-1 text-sm text-right focus:ring-1 outline-none" />
                </td>
                <td class="px-3 py-1.5">
                  <div class="relative">
                    <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-xs">$</span>
                    <input v-model="det.precio_unitario" type="number" step="0.01" min="0"
                      class="w-full border rounded pl-5 pr-2 py-1 text-sm text-right focus:ring-1 outline-none" />
                  </div>
                </td>
                <td class="px-3 py-1.5 text-right font-semibold text-gray-700">
                  ${{ (parseFloat(det.cantidad || 0) * parseFloat(det.precio_unitario || 0)).toFixed(2) }}
                </td>
                <td class="px-3 py-1.5 text-center">
                  <button @click="modalCrear.form.detalles.splice(i, 1)"
                    class="text-red-400 hover:text-red-600 text-xs font-bold">✕</button>
                </td>
              </tr>
            </tbody>
            <tfoot v-if="modalCrear.form.detalles.length" class="bg-gray-50 border-t-2">
              <tr>
                <td colspan="5" class="px-3 py-2 text-right font-bold text-gray-700">TOTAL:</td>
                <td class="px-3 py-2 text-right font-bold text-lg" style="color:#4a5e3a;">
                  ${{ totalOrden.toFixed(2) }}
                </td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>

        <p v-if="errorModal" class="text-red-600 text-sm mb-3">{{ errorModal }}</p>
        <div class="flex justify-end gap-3">
          <button @click="modalCrear.show = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
          <button @click="guardarOrden" :disabled="guardando"
            class="text-white px-5 py-2 rounded-lg text-sm hover:opacity-90 disabled:opacity-50"
            style="background-color:#4a5e3a;">
            {{ guardando ? 'Guardando...' : 'Guardar orden' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal ver detalle -->
    <div v-if="modalDetalle.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-xl p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-start mb-4">
          <h2 class="text-lg font-bold">Orden #{{ modalDetalle.orden?.id }}</h2>
          <button @click="modalDetalle.show = false" class="text-gray-400 hover:text-gray-600 text-xl">✕</button>
        </div>
        <p class="text-sm text-gray-600 mb-1"><b>Proveedor:</b> {{ modalDetalle.orden?.proveedor?.nombre }}</p>
        <p class="text-sm text-gray-600 mb-4"><b>Fecha:</b> {{ modalDetalle.orden?.fecha }}</p>
        <table class="w-full text-sm border rounded-lg overflow-hidden">
          <thead style="background-color:#4a5e3a;" class="text-white">
            <tr>
              <th class="text-left px-3 py-2">Artículo</th>
              <th class="text-right px-3 py-2">Cantidad</th>
              <th class="text-right px-3 py-2">P. Unit.</th>
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
          <tfoot class="bg-gray-50 border-t-2 font-bold">
            <tr>
              <td colspan="3" class="px-3 py-2 text-right">Total:</td>
              <td class="px-3 py-2 text-right" style="color:#4a5e3a;">
                ${{ (modalDetalle.orden?.detalles || []).reduce((s,d) => s + d.cantidad * d.precio_unitario, 0).toFixed(2) }}
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

const ordenes         = ref([])
const filtroEstado    = ref('')
const guardando       = ref(false)
const errorModal      = ref('')
const proveedores     = ref([])
const articulos       = ref([])
const busquedaArticulo = ref('')
const articulosFiltrados = ref([])

const modalCrear   = ref({ show: false, form: { proveedor_id: '', fecha: '', observacion: '', detalles: [] } })
const modalDetalle = ref({ show: false, orden: null })

const proveedoresActivos = computed(() => proveedores.value.filter(p => p.estado === 'ACTIVO' && p.es_proveedor_bienes))

const totalOrden = computed(() =>
  modalCrear.value.form.detalles.reduce((s, d) =>
    s + parseFloat(d.cantidad || 0) * parseFloat(d.precio_unitario || 0), 0)
)

function filtrarArticulos() {
  const q = busquedaArticulo.value.toLowerCase().trim()
  if (!q) { articulosFiltrados.value = []; return }
  articulosFiltrados.value = articulos.value
    .filter(a => a.estado === 'ACTIVO' &&
      (a.codigo.toLowerCase().includes(q) || a.nombre.toLowerCase().includes(q) || (a.marca || '').toLowerCase().includes(q))
    ).slice(0, 10)
}

function agregarArticulo(a) {
  const yaExiste = modalCrear.value.form.detalles.find(d => d.articulo_id === a.id)
  if (yaExiste) { yaExiste.cantidad++; }
  else {
    modalCrear.value.form.detalles.push({
      articulo_id: a.id, codigo: a.codigo, nombre: a.nombre, marca: a.marca,
      cantidad: 1, precio_unitario: 0,
    })
  }
  busquedaArticulo.value = ''
  articulosFiltrados.value = []
}

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
  busquedaArticulo.value = ''
  articulosFiltrados.value = []
  errorModal.value = ''
}

function verDetalle(o) { modalDetalle.value = { show: true, orden: o } }

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
  if (!confirm('¿Confirmar recepción? Se actualizará el stock de los artículos.')) return
  await api.patch(`/adquisiciones/ordenes/${id}/recibir`)
  await cargar()
}

async function eliminar(id) {
  if (!confirm('¿Eliminar esta orden?')) return
  await api.delete(`/adquisiciones/ordenes/${id}`)
  await cargar()
}
</script>
