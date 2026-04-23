<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Ingresos de Bienes</h1>
      <button @click="abrirCrear" class="text-white px-4 py-2 rounded-lg text-sm hover:opacity-90" style="background-color:#4a5e3a;">
        + Nuevo ingreso
      </button>
    </div>

    <!-- Filtros -->
    <div class="flex gap-3 mb-3 flex-wrap">
      <select v-model="filtroEstado" @change="cargar" class="border rounded-lg px-3 py-2 text-sm">
        <option value="">Todos los estados</option>
        <option value="BORRADOR">Borrador</option>
        <option value="RECIBIDO">Recibido</option>
      </select>
      <select v-model="filtroTipo" @change="cargar" class="border rounded-lg px-3 py-2 text-sm">
        <option value="">Todos los tipos</option>
        <option value="COMPRA">Compra</option>
        <option value="DONACION">Donación</option>
      </select>
    </div>

    <!-- Contador -->
    <div class="mb-3">
      <span v-if="ordenes.length > 0"
        class="inline-block bg-green-100 text-green-800 text-xs font-semibold px-3 py-1 rounded-full border border-green-300">
        Se encontraron {{ ordenes.length }} ingreso{{ ordenes.length !== 1 ? 's' : '' }}
      </span>
      <span v-else
        class="inline-block bg-red-100 text-red-700 text-xs font-semibold px-3 py-1 rounded-full border border-red-300">
        No se encontraron ingresos registrados
      </span>
    </div>

    <!-- Lista -->
    <div class="bg-white rounded-xl shadow overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr style="background-color: #4a5e3a;">
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">#</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">Tipo</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">Documento</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">Proveedor</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">Fecha Doc.</th>
            <th class="text-right px-4 py-3 text-white font-semibold whitespace-nowrap">Subtotal</th>
            <th class="text-right px-4 py-3 text-white font-semibold whitespace-nowrap">IVA</th>
            <th class="text-right px-4 py-3 text-white font-semibold whitespace-nowrap">Total</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">Estado</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!ordenes.length">
            <td colspan="10" class="text-center py-8 text-gray-400">Sin ingresos registrados</td>
          </tr>
          <tr v-for="o in ordenes" :key="o.id" class="border-b hover:bg-amber-50">
            <td class="px-4 py-3 text-gray-400 text-xs whitespace-nowrap">{{ o.id }}</td>
            <td class="px-4 py-3 whitespace-nowrap">
              <span :class="o.tipo_ingreso === 'COMPRA' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'"
                class="px-2 py-0.5 rounded-full text-xs font-medium">{{ o.tipo_ingreso }}</span>
            </td>
            <td class="px-4 py-3 whitespace-nowrap">
              <div class="text-xs text-gray-500">{{ o.tipo_documento || '-' }}</div>
              <div class="font-mono text-xs">{{ o.numero_documento || '-' }}</div>
            </td>
            <td class="px-4 py-3 text-gray-700">{{ o.proveedor?.nombre || '-' }}</td>
            <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">{{ o.fecha_documento || '-' }}</td>
            <td class="px-4 py-3 text-right font-mono text-xs whitespace-nowrap">${{ fmt(o.subtotal) }}</td>
            <td class="px-4 py-3 text-right font-mono text-xs whitespace-nowrap">${{ fmt(o.iva_valor) }}</td>
            <td class="px-4 py-3 text-right font-mono font-semibold whitespace-nowrap">${{ fmt(o.total) }}</td>
            <td class="px-4 py-3 whitespace-nowrap">
              <span :class="o.estado === 'BORRADOR' ? 'bg-gray-100 text-gray-600' : 'bg-green-100 text-green-700'"
                class="px-2 py-0.5 rounded-full text-xs font-medium">{{ o.estado }}</span>
            </td>
            <td class="px-4 py-3 whitespace-nowrap flex gap-2">
              <button @click="verDetalle(o)" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Ver</button>
              <button v-if="o.estado === 'RECIBIDO'" @click="descargarPdf(o.id)"
                class="text-xs text-purple-600 hover:text-purple-800 font-medium">PDF</button>
              <button v-if="o.estado === 'BORRADOR'" @click="confirmar(o.id)"
                class="text-xs text-green-600 hover:text-green-800 font-medium">Confirmar</button>
              <button v-if="o.estado === 'BORRADOR'" @click="eliminar(o.id)"
                class="text-xs text-red-500 hover:text-red-700">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ══ MODAL CREAR / EDITAR ══ -->
    <div v-if="modalForm.show" class="fixed inset-0 bg-black/50 flex items-start justify-center z-50 p-4 overflow-y-auto">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-6xl p-6 my-4">
        <h2 class="text-lg font-bold mb-4">Nuevo Ingreso de Bienes</h2>

        <!-- Cabecera del ingreso -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5 p-4 bg-gray-50 rounded-lg">
          <!-- Tipo ingreso -->
          <div>
            <label class="block text-xs text-gray-600 mb-1">Tipo de Ingreso *</label>
            <select v-model="form.tipo_ingreso" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-green-300 outline-none">
              <option value="COMPRA">COMPRA</option>
              <option value="DONACION">DONACIÓN</option>
            </select>
          </div>

          <!-- Proceso contratación (solo COMPRA) -->
          <div v-if="form.tipo_ingreso === 'COMPRA'">
            <label class="block text-xs text-gray-600 mb-1">Proceso de Contratación *</label>
            <select v-model="form.proceso_contratacion" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-green-300 outline-none">
              <option value="">Seleccionar...</option>
              <option value="CATALOGO ELECTRONICO">CATÁLOGO ELECTRÓNICO</option>
              <option value="SUBASTA INVERSA ELECTRONICA">SUBASTA INVERSA ELECTRÓNICA</option>
              <option value="CAJA CHICA">CAJA CHICA</option>
            </select>
          </div>

          <!-- Tipo documento -->
          <div>
            <label class="block text-xs text-gray-600 mb-1">Tipo Documento</label>
            <select v-model="form.tipo_documento" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-green-300 outline-none">
              <option value="">Sin documento</option>
              <option value="FACTURA">FACTURA</option>
              <option value="NOTA DE ENTREGA">NOTA DE ENTREGA</option>
            </select>
          </div>

          <!-- Número documento -->
          <div>
            <label class="block text-xs text-gray-600 mb-1">Número Documento</label>
            <input v-model="form.numero_documento" type="text" maxlength="50"
              placeholder="ej: 001-001-000001234"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-green-300 outline-none" />
          </div>

          <!-- Fecha documento -->
          <div>
            <label class="block text-xs text-gray-600 mb-1">Fecha Documento</label>
            <input v-model="form.fecha_documento" type="date"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-green-300 outline-none" />
          </div>

          <!-- Proveedor (autocomplete) -->
          <div class="relative">
            <label class="block text-xs text-gray-600 mb-1">Proveedor{{ form.tipo_ingreso === 'COMPRA' ? ' *' : '' }}</label>
            <input v-model="busquedaProveedor" @input="filtrarProveedores" @keydown.escape="sugerenciasProveedor = []"
              type="text" placeholder="Buscar por RUC o nombre..."
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-green-300 outline-none" />
            <div v-if="sugerenciasProveedor.length"
              class="absolute z-30 bg-white border rounded-lg shadow-lg w-full mt-1 max-h-48 overflow-y-auto">
              <div v-for="p in sugerenciasProveedor" :key="p.id"
                @click="seleccionarProveedor(p)"
                class="px-3 py-2 text-sm hover:bg-gray-100 cursor-pointer">
                <div class="font-medium text-gray-800">{{ p.nombre }}</div>
                <div class="text-xs text-gray-400 font-mono">RUC: {{ p.ruc }}</div>
              </div>
            </div>
            <div v-if="form.proveedor_id && proveedorSeleccionado"
              class="mt-1 flex items-center gap-2 text-xs text-green-700 bg-green-50 rounded px-2 py-1">
              <span>✓ {{ proveedorSeleccionado.nombre }}</span>
              <button @click="limpiarProveedor" class="text-red-400 hover:text-red-600 ml-auto">✕</button>
            </div>
          </div>

          <!-- Observación -->
          <div class="md:col-span-2">
            <label class="block text-xs text-gray-600 mb-1">Observación</label>
            <input v-model="form.observacion" type="text"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-green-300 outline-none" />
          </div>
        </div>

        <!-- Buscador de artículos -->
        <div class="mb-3 relative">
          <label class="block text-xs text-gray-600 mb-1">Agregar artículo (buscar por código o descripción)</label>
          <input v-model="busquedaArticulo" @input="filtrarArticulos" @keydown.escape="articulosSugeridos = []" type="text"
            placeholder="Escribe para buscar..."
            class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-green-300 outline-none" />
          <div v-if="articulosSugeridos.length"
            class="absolute z-20 bg-white border rounded-lg shadow-lg w-full mt-1 max-h-52 overflow-y-auto">
            <div v-for="a in articulosSugeridos" :key="a.id"
              @click="agregarArticulo(a)"
              class="px-4 py-2 text-sm hover:bg-gray-100 cursor-pointer">
              <div class="flex justify-between items-center">
                <div>
                  <span class="font-mono text-xs text-gray-400 mr-2">{{ a.codigo }}</span>
                  <span class="font-medium">{{ a.nombre }}</span>
                </div>
                <div class="text-xs text-gray-500 text-right">
                  <div>Stock: {{ a.stock_actual }}</div>
                  <div>${{ fmt(a.precio_unitario) }} | {{ a.iva_porcentaje }}% IVA</div>
                </div>
              </div>
              <div class="text-xs text-gray-400 mt-0.5">{{ a.nivel1 }} / {{ a.nivel2 }}</div>
            </div>
          </div>
        </div>

        <!-- Tabla de detalle estilo Excel -->
        <div class="border rounded-lg overflow-hidden mb-4">
          <div class="overflow-x-auto">
            <table class="w-full text-xs">
              <thead style="background-color:#4a5e3a;" class="text-white">
                <tr>
                  <th class="text-left px-2 py-2 font-medium w-24">Código</th>
                  <th class="text-left px-2 py-2 font-medium w-16">Niv.1</th>
                  <th class="text-left px-2 py-2 font-medium w-20">Niv.2</th>
                  <th class="text-left px-2 py-2 font-medium">Descripción</th>
                  <th class="text-right px-2 py-2 font-medium w-20">Cantidad</th>
                  <th class="text-right px-2 py-2 font-medium w-24">Precio</th>
                  <th class="text-right px-2 py-2 font-medium w-20">IVA%</th>
                  <th class="text-right px-2 py-2 font-medium w-24">Subtotal</th>
                  <th class="text-right px-2 py-2 font-medium w-20">IVA $</th>
                  <th class="text-right px-2 py-2 font-medium w-24">Total</th>
                  <th class="w-6"></th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="!form.detalles.length">
                  <td colspan="11" class="text-center py-8 text-gray-400 italic">
                    Busca un artículo arriba para agregarlo al ingreso
                  </td>
                </tr>
                <tr v-for="(det, i) in form.detalles" :key="i" class="border-t hover:bg-gray-50">
                  <td class="px-2 py-1 font-mono text-gray-500">{{ det.codigo }}</td>
                  <td class="px-2 py-1 text-gray-500">{{ det.nivel1 || '-' }}</td>
                  <td class="px-2 py-1 text-gray-500">{{ det.nivel2 || '-' }}</td>
                  <td class="px-2 py-1 font-medium">{{ det.nombre }}</td>
                  <td class="px-2 py-1">
                    <input v-model.number="det.cantidad" type="number" step="0.01" min="0.01"
                      @input="recalcularLinea(det)"
                      class="w-full border rounded px-1.5 py-1 text-right focus:ring-1 outline-none text-xs" />
                  </td>
                  <td class="px-2 py-1">
                    <div class="relative">
                      <span class="absolute left-1.5 top-1/2 -translate-y-1/2 text-gray-400">$</span>
                      <input v-model.number="det.precio_unitario" type="number" step="0.0001" min="0"
                        @input="recalcularLinea(det)"
                        class="w-full border rounded pl-4 pr-1 py-1 text-right focus:ring-1 outline-none text-xs" />
                    </div>
                  </td>
                  <td class="px-2 py-1">
                    <select v-model="det.iva_id" @change="onIvaChange(det)"
                      class="w-full border rounded px-1.5 py-1 text-right focus:ring-1 outline-none text-xs">
                      <option value="">Sin IVA</option>
                      <option v-for="iva in ivasActivos" :key="iva.id" :value="iva.id">
                        {{ iva.porcentaje }}%
                      </option>
                    </select>
                  </td>
                  <td class="px-2 py-1 text-right font-mono">${{ fmt(det._subtotal) }}</td>
                  <td class="px-2 py-1 text-right font-mono">${{ fmt(det._iva_valor) }}</td>
                  <td class="px-2 py-1 text-right font-mono font-semibold">${{ fmt(det._total_linea) }}</td>
                  <td class="px-2 py-1 text-center">
                    <button @click="form.detalles.splice(i, 1)" class="text-red-400 hover:text-red-600 font-bold">✕</button>
                  </td>
                </tr>
              </tbody>
              <tfoot v-if="form.detalles.length" class="bg-gray-50 border-t-2">
                <tr>
                  <td colspan="7" class="px-2 py-2 text-right font-semibold text-gray-600 text-xs">SUBTOTAL:</td>
                  <td class="px-2 py-2 text-right font-bold font-mono">${{ fmt(totales.subtotal) }}</td>
                  <td class="px-2 py-2 text-right font-bold font-mono">${{ fmt(totales.iva) }}</td>
                  <td class="px-2 py-2 text-right font-bold font-mono text-sm" style="color:#4a5e3a;">${{ fmt(totales.total) }}</td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <p v-if="errorForm" class="text-red-600 text-sm mb-3">{{ errorForm }}</p>
        <div class="flex justify-end gap-3">
          <button @click="modalForm.show = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
          <button @click="guardar" :disabled="guardando"
            class="text-white px-5 py-2 rounded-lg text-sm hover:opacity-90 disabled:opacity-50"
            style="background-color:#4a5e3a;">
            {{ guardando ? 'Guardando...' : 'Guardar ingreso' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ══ MODAL VER DETALLE ══ -->
    <div v-if="modalDetalle.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-start mb-4">
          <h2 class="text-lg font-bold">Ingreso #{{ modalDetalle.orden?.id }}</h2>
          <button @click="modalDetalle.show = false" class="text-gray-400 hover:text-gray-600 text-xl">✕</button>
        </div>

        <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm mb-4">
          <div><span class="text-gray-500">Tipo:</span>
            <span class="ml-2 font-medium">{{ modalDetalle.orden?.tipo_ingreso }}</span></div>
          <div v-if="modalDetalle.orden?.proceso_contratacion">
            <span class="text-gray-500">Proceso:</span>
            <span class="ml-2 font-medium">{{ modalDetalle.orden?.proceso_contratacion }}</span></div>
          <div v-if="modalDetalle.orden?.tipo_documento">
            <span class="text-gray-500">Documento:</span>
            <span class="ml-2 font-medium">{{ modalDetalle.orden?.tipo_documento }} {{ modalDetalle.orden?.numero_documento }}</span></div>
          <div><span class="text-gray-500">Proveedor:</span>
            <span class="ml-2 font-medium">{{ modalDetalle.orden?.proveedor?.nombre || '—' }}</span></div>
          <div v-if="modalDetalle.orden?.fecha_documento">
            <span class="text-gray-500">Fecha doc.:</span>
            <span class="ml-2">{{ modalDetalle.orden?.fecha_documento }}</span></div>
          <div v-if="modalDetalle.orden?.observacion">
            <span class="text-gray-500">Observación:</span>
            <span class="ml-2">{{ modalDetalle.orden?.observacion }}</span></div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm border rounded-lg overflow-hidden">
            <thead style="background-color:#4a5e3a;" class="text-white">
              <tr>
                <th class="text-left px-3 py-2">Artículo</th>
                <th class="text-right px-3 py-2">Cantidad</th>
                <th class="text-right px-3 py-2">Precio</th>
                <th class="text-right px-3 py-2">IVA%</th>
                <th class="text-right px-3 py-2">Subtotal</th>
                <th class="text-right px-3 py-2">IVA $</th>
                <th class="text-right px-3 py-2">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="d in modalDetalle.orden?.detalles" :key="d.id" class="border-t">
                <td class="px-3 py-2">
                  <div class="font-medium">{{ d.articulo?.nombre }}</div>
                  <div class="text-xs text-gray-400 font-mono">{{ d.articulo?.codigo }}</div>
                </td>
                <td class="px-3 py-2 text-right">{{ d.cantidad }}</td>
                <td class="px-3 py-2 text-right font-mono">${{ fmt(d.precio_unitario) }}</td>
                <td class="px-3 py-2 text-right">{{ d.iva_porcentaje }}%</td>
                <td class="px-3 py-2 text-right font-mono">${{ fmt(d.subtotal) }}</td>
                <td class="px-3 py-2 text-right font-mono">${{ fmt(d.iva_valor) }}</td>
                <td class="px-3 py-2 text-right font-mono font-semibold">${{ fmt(d.total_linea) }}</td>
              </tr>
            </tbody>
            <tfoot class="bg-gray-50 border-t-2 font-bold text-sm">
              <tr>
                <td colspan="4" class="px-3 py-2 text-right text-gray-600">Subtotal:</td>
                <td class="px-3 py-2 text-right font-mono">${{ fmt(modalDetalle.orden?.subtotal) }}</td>
                <td class="px-3 py-2 text-right font-mono">${{ fmt(modalDetalle.orden?.iva_valor) }}</td>
                <td></td>
              </tr>
              <tr>
                <td colspan="6" class="px-3 py-2 text-right text-gray-700">TOTAL:</td>
                <td class="px-3 py-2 text-right font-mono text-base" style="color:#4a5e3a;">
                  ${{ fmt(modalDetalle.orden?.total) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

const ordenes        = ref([])
const filtroEstado   = ref('')
const filtroTipo     = ref('')
const guardando      = ref(false)
const errorForm      = ref('')
const proveedores    = ref([])
const articulos      = ref([])
const ivasActivos    = ref([])
const busquedaArticulo   = ref('')
const articulosSugeridos = ref([])
const busquedaProveedor  = ref('')
const sugerenciasProveedor = ref([])
const proveedorSeleccionado = ref(null)

const modalForm    = ref({ show: false })
const modalDetalle = ref({ show: false, orden: null })

const formInicial = () => ({
  tipo_ingreso: 'COMPRA',
  proceso_contratacion: '',
  tipo_documento: '',
  proveedor_id: '',
  numero_documento: '',
  fecha_documento: '',
  observacion: '',
  detalles: [],
})
const form = ref(formInicial())

const proveedoresActivos = computed(() => proveedores.value.filter(p => p.estado === 'ACTIVO'))

const totales = computed(() => {
  let subtotal = 0, iva = 0
  for (const d of form.value.detalles) {
    subtotal += d._subtotal || 0
    iva      += d._iva_valor || 0
  }
  return { subtotal, iva, total: subtotal + iva }
})

function fmt(v) {
  return parseFloat(v || 0).toFixed(2)
}

function recalcularLinea(det) {
  const sub = Math.round((det.cantidad || 0) * (det.precio_unitario || 0) * 100) / 100
  const ivaPct = det._iva_pct || 0
  const ivaVal = Math.round(sub * ivaPct / 100 * 100) / 100
  det._subtotal   = sub
  det._iva_valor  = ivaVal
  det._total_linea = sub + ivaVal
}

function onIvaChange(det) {
  if (!det.iva_id || det.iva_id === '') {
    det._iva_pct = 0
    det.iva_id   = null
  } else {
    const iva = ivasActivos.value.find(i => i.id === det.iva_id)
    det._iva_pct = iva ? parseFloat(iva.porcentaje) : 0
  }
  recalcularLinea(det)
}

function filtrarArticulos() {
  const q = busquedaArticulo.value.toLowerCase().trim()
  if (!q) { articulosSugeridos.value = []; return }
  articulosSugeridos.value = articulos.value
    .filter(a => a.estado === 'ACTIVO' &&
      (a.codigo?.toLowerCase().includes(q) ||
       a.nombre?.toLowerCase().includes(q) ||
       a.nivel2?.toLowerCase().includes(q)))
    .slice(0, 12)
}

function agregarArticulo(a) {
  const existe = form.value.detalles.find(d => d.articulo_id === a.id)
  if (existe) { existe.cantidad++; recalcularLinea(existe) }
  else {
    const ivaPct = parseFloat(a.iva_porcentaje || 0)
    const precio = parseFloat(a.precio_unitario || 0)
    const sub    = Math.round(1 * precio * 100) / 100
    const ivaVal = Math.round(sub * ivaPct / 100 * 100) / 100
    form.value.detalles.push({
      articulo_id:    a.id,
      codigo:         a.codigo,
      nombre:         a.nombre,
      nivel1:         a.nivel1,
      nivel2:         a.nivel2,
      cantidad:       1,
      precio_unitario: precio,
      iva_id:         a.iva_id || null,
      _iva_pct:       ivaPct,
      _subtotal:      sub,
      _iva_valor:     ivaVal,
      _total_linea:   sub + ivaVal,
    })
  }
  busquedaArticulo.value = ''
  articulosSugeridos.value = []
}

async function cargar() {
  const params = {}
  if (filtroEstado.value) params.estado = filtroEstado.value
  if (filtroTipo.value)   params.tipo   = filtroTipo.value
  const { data } = await api.get('/adquisiciones/ordenes', { params })
  ordenes.value = data
}

onMounted(async () => {
  const [p, a, iv] = await Promise.all([
    api.get('/adquisiciones/proveedores'),
    api.get('/adquisiciones/articulos'),
    api.get('/adquisiciones/iva'),
  ])
  proveedores.value = p.data
  articulos.value   = a.data
  ivasActivos.value = iv.data.filter(i => i.activo)
  await cargar()
})

function filtrarProveedores() {
  const q = busquedaProveedor.value.toLowerCase().trim()
  if (!q) { sugerenciasProveedor.value = []; return }
  sugerenciasProveedor.value = proveedoresActivos.value
    .filter(p => p.nombre.toLowerCase().includes(q) || p.ruc.includes(q))
    .slice(0, 10)
}

function seleccionarProveedor(p) {
  form.value.proveedor_id  = p.id
  proveedorSeleccionado.value = p
  busquedaProveedor.value  = ''
  sugerenciasProveedor.value = []
}

function limpiarProveedor() {
  form.value.proveedor_id  = ''
  proveedorSeleccionado.value = null
  busquedaProveedor.value  = ''
}

async function descargarPdf(id) {
  try {
    const { data } = await api.get(`/adquisiciones/ordenes/${id}/pdf`, { responseType: 'blob' })
    const url  = window.URL.createObjectURL(new Blob([data], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href  = url
    link.setAttribute('download', `ingreso-bodega-${id}.pdf`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch { alert('Error al generar el PDF') }
}

function abrirCrear() {
  form.value = formInicial()
  form.value.fecha_documento = new Date().toISOString().split('T')[0]
  busquedaArticulo.value = ''
  articulosSugeridos.value = []
  busquedaProveedor.value = ''
  sugerenciasProveedor.value = []
  proveedorSeleccionado.value = null
  errorForm.value = ''
  modalForm.value = { show: true }
}

function verDetalle(o) {
  modalDetalle.value = { show: true, orden: o }
}

async function guardar() {
  errorForm.value = ''
  if (!form.value.tipo_ingreso) { errorForm.value = 'Seleccione el tipo de ingreso.'; return }
  if (form.value.tipo_ingreso === 'COMPRA' && !form.value.proceso_contratacion) {
    errorForm.value = 'Seleccione el proceso de contratación.'; return
  }
  if (!form.value.detalles.length) { errorForm.value = 'Agregue al menos un artículo.'; return }

  const payload = {
    tipo_ingreso:         form.value.tipo_ingreso,
    proceso_contratacion: form.value.proceso_contratacion || null,
    tipo_documento:       form.value.tipo_documento || null,
    proveedor_id:         form.value.proveedor_id || null,
    numero_documento:     form.value.numero_documento || null,
    fecha_documento:      form.value.fecha_documento || null,
    observacion:          form.value.observacion || null,
    detalles: form.value.detalles.map(d => ({
      articulo_id:     d.articulo_id,
      cantidad:        d.cantidad,
      precio_unitario: d.precio_unitario,
      iva_id:          d.iva_id || null,
    })),
  }

  guardando.value = true
  try {
    await api.post('/adquisiciones/ordenes', payload)
    modalForm.value.show = false
    await cargar()
  } catch (e) {
    errorForm.value = e.response?.data?.message || 'Error al guardar'
    if (e.response?.data?.errors) {
      const errs = Object.values(e.response.data.errors).flat()
      errorForm.value = errs.join(' | ')
    }
  } finally { guardando.value = false }
}

async function confirmar(id) {
  if (!confirm('¿Confirmar recepción? Se actualizará el stock y precio promedio de los artículos.')) return
  try {
    await api.patch(`/adquisiciones/ordenes/${id}/confirmar`)
    await cargar()
  } catch (e) {
    alert(e.response?.data?.message || 'Error al confirmar')
  }
}

async function eliminar(id) {
  if (!confirm('¿Eliminar este ingreso en borrador?')) return
  await api.delete(`/adquisiciones/ordenes/${id}`)
  await cargar()
}
</script>
