<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Egresos de Bienes</h1>
      <button @click="abrirCrear" class="text-white px-4 py-2 rounded-lg text-sm hover:opacity-90" style="background-color:#4a5e3a;">
        + Nuevo egreso
      </button>
    </div>

    <!-- Filtros -->
    <div class="flex gap-3 mb-3 flex-wrap">
      <select v-model="filtroEstado" @change="cargar" class="border rounded-lg px-3 py-2 text-sm">
        <option value="">Todos los estados</option>
        <option value="BORRADOR">Borrador</option>
        <option value="DESPACHADO">Despachado</option>
      </select>
    </div>

    <!-- Contador -->
    <div class="mb-3">
      <span v-if="egresos.length > 0"
        class="inline-block bg-green-100 text-green-800 text-xs font-semibold px-3 py-1 rounded-full border border-green-300">
        Se encontraron {{ egresos.length }} egreso{{ egresos.length !== 1 ? 's' : '' }}
      </span>
      <span v-else
        class="inline-block bg-red-100 text-red-700 text-xs font-semibold px-3 py-1 rounded-full border border-red-300">
        No se encontraron egresos registrados
      </span>
    </div>

    <!-- Lista -->
    <div class="bg-white rounded-xl shadow overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr style="background-color: #4a5e3a;">
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">#</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">Secuencial</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">Dirección</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">Servidor</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">Fecha</th>
            <th class="text-right px-4 py-3 text-white font-semibold whitespace-nowrap">Subtotal</th>
            <th class="text-right px-4 py-3 text-white font-semibold whitespace-nowrap">IVA</th>
            <th class="text-right px-4 py-3 text-white font-semibold whitespace-nowrap">Total</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">Estado</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!egresos.length">
            <td colspan="10" class="text-center py-8 text-gray-400">Sin egresos registrados</td>
          </tr>
          <tr v-for="e in egresos" :key="e.id" class="border-b hover:bg-green-50">
            <td class="px-4 py-3 text-gray-400 text-xs whitespace-nowrap">{{ e.id }}</td>
            <td class="px-4 py-3 font-mono font-semibold whitespace-nowrap">{{ e.numero_secuencial ?? '-' }}</td>
            <td class="px-4 py-3 text-gray-700 max-w-[180px] truncate" :title="e.direccion">{{ e.direccion || '-' }}</td>
            <td class="px-4 py-3 text-gray-700 whitespace-nowrap">{{ e.empleado_nombre || '-' }}</td>
            <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">
              {{ e.fecha_despacho ? e.fecha_despacho.slice(0, 10) : e.created_at?.slice(0, 10) }}
            </td>
            <td class="px-4 py-3 text-right font-mono text-xs whitespace-nowrap">${{ fmt(e.subtotal) }}</td>
            <td class="px-4 py-3 text-right font-mono text-xs whitespace-nowrap">${{ fmt(e.iva_valor) }}</td>
            <td class="px-4 py-3 text-right font-mono font-semibold whitespace-nowrap">${{ fmt(e.total) }}</td>
            <td class="px-4 py-3 whitespace-nowrap">
              <span :class="e.estado === 'BORRADOR' ? 'bg-gray-100 text-gray-600' : 'bg-green-100 text-green-700'"
                class="px-2 py-0.5 rounded-full text-xs font-medium">{{ e.estado }}</span>
            </td>
            <td class="px-4 py-3 whitespace-nowrap flex gap-2">
              <button @click="verDetalle(e)" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Ver</button>
              <button v-if="e.estado === 'DESPACHADO'" @click="descargarPdf(e.id)"
                class="text-xs text-purple-600 hover:text-purple-800 font-medium">PDF</button>
              <button v-if="e.estado === 'DESPACHADO'" @click="abrirReverso(e.id)"
                class="text-xs text-orange-600 hover:text-orange-800 font-medium">Reversar</button>
              <button v-if="e.estado === 'BORRADOR'" @click="abrirEditar(e)"
                class="text-xs text-amber-600 hover:text-amber-800 font-medium">Editar</button>
              <button v-if="e.estado === 'BORRADOR'" @click="confirmar(e.id)"
                class="text-xs text-green-600 hover:text-green-800 font-medium">Confirmar</button>
              <button v-if="e.estado === 'BORRADOR'" @click="eliminar(e.id)"
                class="text-xs text-red-500 hover:text-red-700">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ══ MODAL CREAR / EDITAR ══ -->
    <div v-if="modalForm.show" class="fixed inset-0 bg-black/50 flex items-start justify-center z-50 p-4 overflow-y-auto">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-7xl p-6 my-4">

        <div class="flex items-center justify-between mb-4">
          <h2 class="text-lg font-bold">{{ modalForm.editando ? 'Editar Egreso #' + modalForm.id : 'Nuevo Egreso de Bienes' }}</h2>
        </div>

        <!-- Nav pestañas -->
        <div class="flex border-b mb-5">
          <button v-for="tab in tabs" :key="tab.key" @click="tabActiva = tab.key"
            :class="tabActiva === tab.key
              ? 'border-b-2 font-semibold text-white px-5 py-2 text-sm -mb-px'
              : 'px-5 py-2 text-sm text-gray-500 hover:text-gray-700'"
            :style="tabActiva === tab.key ? 'border-color:#4a5e3a; background-color:#4a5e3a; border-radius:6px 6px 0 0;' : ''">
            {{ tab.label }}
            <span v-if="tab.key === 'bienes' && form.detalles.length"
              class="ml-1.5 text-xs font-bold rounded-full px-1.5"
              :style="tabActiva === 'bienes' ? 'background:white; color:#4a5e3a' : 'background:#e5e7eb; color:#4a5e3a'">
              {{ form.detalles.length }}
            </span>
          </button>
        </div>

        <!-- ── TAB DATOS ── -->
        <div v-show="tabActiva === 'datos'">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3 p-4 bg-gray-50 rounded-lg">

            <!-- Dirección -->
            <div>
              <label class="block text-xs text-gray-600 mb-1">Dirección *</label>
              <select v-model="form.direccion_id" @change="onDireccionChange"
                class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-green-300 outline-none">
                <option value="">Seleccionar dirección...</option>
                <option v-for="d in departamentos" :key="d.id_depto" :value="d.id_depto">
                  {{ d.nombre_depto }}
                </option>
              </select>
            </div>

            <!-- Empleado -->
            <div>
              <label class="block text-xs text-gray-600 mb-1">Servidor *</label>
              <select v-model="form.empleado_id" @change="onEmpleadoChange"
                class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-green-300 outline-none"
                :disabled="!form.direccion_id">
                <option value="">Seleccionar servidor...</option>
                <option v-for="emp in empleadosFiltrados" :key="emp.id_emp" :value="emp.id_emp">
                  {{ emp.nombre_completo }}
                </option>
              </select>
            </div>

            <!-- Observación -->
            <div>
              <label class="block text-xs text-gray-600 mb-1">Observación</label>
              <input v-model="form.observacion" type="text"
                class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-green-300 outline-none" />
            </div>
          </div>
        </div>

        <!-- ── TAB BIENES ── -->
        <div v-show="tabActiva === 'bienes'">
          <!-- Buscador -->
          <div class="mb-3 relative">
            <label class="block text-xs text-gray-600 mb-1">Buscar artículo por código o descripción</label>
            <input v-model="busquedaArticulo" @input="filtrarArticulos" @keydown.escape="articulosSugeridos = []"
              type="text" placeholder="Escribe para buscar..."
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-green-300 outline-none" />
            <div v-if="articulosSugeridos.length"
              class="absolute z-20 bg-white border rounded-lg shadow-lg w-full mt-1 max-h-52 overflow-y-auto">
              <div v-for="a in articulosSugeridos" :key="a.id" @click="agregarArticulo(a)"
                class="px-4 py-2 text-sm hover:bg-gray-100 cursor-pointer border-b last:border-0">
                <div class="flex justify-between items-center">
                  <div>
                    <span class="font-mono text-xs text-gray-400 mr-2">{{ a.codigo }}</span>
                    <span class="font-medium">{{ a.nombre }}</span>
                  </div>
                  <div class="text-xs text-gray-500 text-right">
                    <div>Stock: <span :class="a.stock_actual <= 0 ? 'text-red-600 font-bold' : 'text-green-700 font-semibold'">{{ a.stock_actual }}</span></div>
                    <div>${{ fmt(a.precio_unitario) }} | {{ a.iva_porcentaje }}% IVA</div>
                  </div>
                </div>
                <div class="text-xs text-gray-400 mt-0.5">{{ a.nivel1 }} / {{ a.nivel2 }}</div>
              </div>
            </div>
          </div>

          <!-- Tabla bienes + monetario -->
          <div class="border rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full text-xs">
                <thead style="background-color:#4a5e3a;" class="text-white">
                  <tr>
                    <th class="text-left px-3 py-2 font-semibold whitespace-nowrap">Código</th>
                    <th class="text-left px-3 py-2 font-semibold whitespace-nowrap">Niv.1</th>
                    <th class="text-left px-3 py-2 font-semibold whitespace-nowrap">Niv.2</th>
                    <th class="text-left px-3 py-2 font-semibold">Descripción</th>
                    <th class="text-right px-3 py-2 font-semibold whitespace-nowrap">Cantidad</th>
                    <th class="text-right px-3 py-2 font-semibold whitespace-nowrap">Precio s/IVA</th>
                    <th class="text-right px-3 py-2 font-semibold whitespace-nowrap">IVA %</th>
                    <th class="text-right px-3 py-2 font-semibold whitespace-nowrap">Subtotal</th>
                    <th class="text-right px-3 py-2 font-semibold whitespace-nowrap">IVA $</th>
                    <th class="text-right px-3 py-2 font-semibold whitespace-nowrap">Total</th>
                    <th class="w-8"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!form.detalles.length">
                    <td colspan="11" class="text-center py-8 text-gray-400 italic">Busca un artículo arriba para agregarlo</td>
                  </tr>
                  <tr v-for="(det, i) in form.detalles" :key="i" class="border-t hover:bg-green-50">
                    <td class="px-3 py-1.5 font-mono text-gray-500 whitespace-nowrap">{{ det.codigo }}</td>
                    <td class="px-3 py-1.5 text-gray-500 whitespace-nowrap">{{ det.nivel1 || '-' }}</td>
                    <td class="px-3 py-1.5 text-gray-500 whitespace-nowrap">{{ det.nivel2 || '-' }}</td>
                    <td class="px-3 py-1.5 font-medium">{{ det.nombre }}</td>
                    <td class="px-3 py-1.5">
                      <input v-model.number="det.cantidad" type="number" step="0.01" min="0.01"
                        @input="recalcularLinea(det)"
                        class="w-24 border rounded px-2 py-1 text-right focus:ring-1 outline-none text-xs" />
                    </td>
                    <td class="px-3 py-1.5 text-right font-mono text-gray-600 whitespace-nowrap bg-gray-50">${{ fmt(det.precio_unitario) }}</td>
                    <td class="px-3 py-1.5 text-right text-gray-600 whitespace-nowrap bg-gray-50">{{ det.iva_porcentaje }}%</td>
                    <td class="px-3 py-1.5 text-right font-mono whitespace-nowrap">${{ fmt(det._subtotal) }}</td>
                    <td class="px-3 py-1.5 text-right font-mono whitespace-nowrap">${{ fmt(det._iva_valor) }}</td>
                    <td class="px-3 py-1.5 text-right font-mono font-semibold whitespace-nowrap">${{ fmt(det._total_linea) }}</td>
                    <td class="px-2 py-1.5 text-center">
                      <button @click="form.detalles.splice(i, 1)" class="text-red-400 hover:text-red-600 font-bold text-base leading-none">✕</button>
                    </td>
                  </tr>
                </tbody>
                <tfoot v-if="form.detalles.length" style="background-color:#f0f4ed;" class="border-t-2">
                  <tr>
                    <td colspan="7" class="px-3 py-2 text-right font-semibold text-gray-600">TOTALES:</td>
                    <td class="px-3 py-2 text-right font-bold font-mono">${{ fmt(totales.subtotal) }}</td>
                    <td class="px-3 py-2 text-right font-bold font-mono">${{ fmt(totales.iva) }}</td>
                    <td class="px-3 py-2 text-right font-bold font-mono text-sm" style="color:#4a5e3a;">${{ fmt(totales.total) }}</td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>

        <p v-if="errorForm" class="text-red-600 text-sm mt-4">{{ errorForm }}</p>
        <div class="flex justify-end gap-3 mt-4">
          <button @click="modalForm.show = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
          <button @click="guardar" :disabled="guardando"
            class="text-white px-5 py-2 rounded-lg text-sm hover:opacity-90 disabled:opacity-50"
            style="background-color:#4a5e3a;">
            {{ guardando ? 'Guardando...' : 'Guardar egreso' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ══ MODAL VER DETALLE ══ -->
    <div v-if="modalDetalle.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-start mb-4">
          <h2 class="text-lg font-bold">Egreso #{{ modalDetalle.egreso?.numero_secuencial ?? modalDetalle.egreso?.id }}</h2>
          <button @click="modalDetalle.show = false" class="text-gray-400 hover:text-gray-600 text-xl">✕</button>
        </div>
        <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm mb-4">
          <div><span class="text-gray-500">Dirección:</span> <span class="font-medium">{{ modalDetalle.egreso?.direccion }}</span></div>
          <div><span class="text-gray-500">Servidor:</span> <span class="font-medium">{{ modalDetalle.egreso?.empleado_nombre }}</span></div>
          <div v-if="modalDetalle.egreso?.observacion"><span class="text-gray-500">Observación:</span> {{ modalDetalle.egreso?.observacion }}</div>
          <div><span class="text-gray-500">Estado:</span> <span class="font-medium">{{ modalDetalle.egreso?.estado }}</span></div>
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
              <tr v-for="d in modalDetalle.egreso?.detalles" :key="d.id" class="border-t">
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
                <td colspan="6" class="px-3 py-2 text-right text-gray-700">TOTAL:</td>
                <td class="px-3 py-2 text-right font-mono text-base" style="color:#4a5e3a;">
                  ${{ fmt(modalDetalle.egreso?.total) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    <!-- ══ MODAL REVERSO ══ -->
    <div v-if="modalReverso.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-bold mb-1">Reversar Egreso</h2>
        <p class="text-sm text-gray-500 mb-4">El stock de los artículos será restituido. El precio se restaura si quedó en cero.</p>
        <div>
          <label class="block text-xs text-gray-600 mb-1">Motivo del reverso *</label>
          <textarea v-model="modalReverso.motivo" rows="3" maxlength="500"
            placeholder="Describa el motivo por el que se reversa este egreso..."
            class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-orange-300 outline-none resize-none"></textarea>
          <p class="text-xs text-gray-400 text-right mt-0.5">{{ modalReverso.motivo.length }}/500</p>
        </div>
        <p v-if="modalReverso.error" class="text-red-600 text-sm mt-2">{{ modalReverso.error }}</p>
        <div class="flex justify-end gap-3 mt-4">
          <button @click="modalReverso.show = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
          <button @click="confirmarReverso" :disabled="modalReverso.guardando"
            class="bg-orange-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-orange-700 disabled:opacity-50">
            {{ modalReverso.guardando ? 'Reversando...' : 'Confirmar Reverso' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

const egresos            = ref([])
const filtroEstado       = ref('')
const guardando          = ref(false)
const errorForm          = ref('')
const articulos          = ref([])
const departamentos      = ref([])
const empleados          = ref([])
const busquedaArticulo   = ref('')
const articulosSugeridos = ref([])

const tabActiva = ref('datos')
const tabs = [
  { key: 'datos',  label: 'Datos' },
  { key: 'bienes', label: 'Bienes' },
]

const modalForm    = ref({ show: false, editando: false, id: null })
const modalDetalle = ref({ show: false, egreso: null })
const modalReverso = ref({ show: false, id: null, motivo: '', error: '', guardando: false })

const formInicial = () => ({
  direccion_id:    '',
  direccion:       '',
  empleado_id:     '',
  empleado_nombre: '',
  observacion:     '',
  detalles:        [],
})
const form = ref(formInicial())

const empleadosFiltrados = computed(() =>
  form.value.direccion_id
    ? empleados.value.filter(e => e.id_depto == form.value.direccion_id)
    : []
)

const totales = computed(() => {
  let subtotal = 0, iva = 0
  for (const d of form.value.detalles) {
    subtotal += d._subtotal || 0
    iva      += d._iva_valor || 0
  }
  return { subtotal, iva, total: subtotal + iva }
})

function fmt(v) { return parseFloat(v || 0).toFixed(2) }

function recalcularLinea(det) {
  const sub    = Math.round((det.cantidad || 0) * (det.precio_unitario || 0) * 100) / 100
  const ivaVal = Math.round(sub * (det.iva_porcentaje || 0) / 100 * 100) / 100
  det._subtotal    = sub
  det._iva_valor   = ivaVal
  det._total_linea = sub + ivaVal
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
    const precio = parseFloat(a.precio_unitario || 0)
    const ivaPct = parseFloat(a.iva_porcentaje || 0)
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
      iva_porcentaje:  ivaPct,
      _subtotal:       sub,
      _iva_valor:      ivaVal,
      _total_linea:    sub + ivaVal,
    })
  }
  busquedaArticulo.value   = ''
  articulosSugeridos.value = []
}

function onDireccionChange() {
  const depto = departamentos.value.find(d => d.id_depto == form.value.direccion_id)
  form.value.direccion     = depto?.nombre_depto || ''
  form.value.empleado_id   = ''
  form.value.empleado_nombre = ''
}

function onEmpleadoChange() {
  const emp = empleados.value.find(e => e.id_emp === form.value.empleado_id)
  form.value.empleado_nombre = emp?.nombre_completo || ''
}

async function cargar() {
  const params = {}
  if (filtroEstado.value) params.estado = filtroEstado.value
  const { data } = await api.get('/adquisiciones/egresos', { params })
  egresos.value = data
}

onMounted(async () => {
  const [a, d, emp] = await Promise.all([
    api.get('/adquisiciones/articulos'),
    api.get('/adquisiciones/departamentos-activos'),
    api.get('/adquisiciones/empleados-activos'),
  ])
  articulos.value    = a.data
  departamentos.value = d.data
  empleados.value    = emp.data
  await cargar()
})

function abrirCrear() {
  form.value       = formInicial()
  busquedaArticulo.value   = ''
  articulosSugeridos.value = []
  errorForm.value  = ''
  tabActiva.value  = 'datos'
  modalForm.value  = { show: true, editando: false, id: null }
}

async function abrirEditar(e) {
  const { data } = await api.get(`/adquisiciones/egresos/${e.id}`)
  const depto = departamentos.value.find(d => d.nombre_depto === data.direccion)
  form.value = {
    direccion_id:    depto?.id_depto || '',
    direccion:       data.direccion || '',
    empleado_id:     data.empleado_id || '',
    empleado_nombre: data.empleado_nombre || '',
    observacion:     data.observacion || '',
    detalles: data.detalles.map(d => {
      const precio = parseFloat(d.precio_unitario || 0)
      const ivaPct = parseFloat(d.iva_porcentaje || 0)
      const sub    = parseFloat(d.subtotal || 0)
      const ivaVal = parseFloat(d.iva_valor || 0)
      return {
        articulo_id:     d.articulo_id,
        codigo:          d.articulo?.codigo,
        nombre:          d.articulo?.nombre,
        nivel1:          d.articulo?.nivel1,
        nivel2:          d.articulo?.nivel2,
        cantidad:        parseFloat(d.cantidad),
        precio_unitario: precio,
        iva_porcentaje:  ivaPct,
        _subtotal:       sub,
        _iva_valor:      ivaVal,
        _total_linea:    parseFloat(d.total_linea || 0),
      }
    }),
  }
  busquedaArticulo.value   = ''
  articulosSugeridos.value = []
  errorForm.value  = ''
  tabActiva.value  = 'datos'
  modalForm.value  = { show: true, editando: true, id: e.id }
}

function verDetalle(e) {
  modalDetalle.value = { show: true, egreso: e }
}

async function guardar() {
  errorForm.value = ''
  if (!form.value.direccion_id) { errorForm.value = 'Seleccione la dirección.'; return }
  if (!form.value.empleado_id)  { errorForm.value = 'Seleccione el servidor.'; return }
  if (!form.value.detalles.length) { errorForm.value = 'Agregue al menos un artículo.'; return }

  const payload = {
    direccion:       form.value.direccion,
    empleado_id:     form.value.empleado_id,
    empleado_nombre: form.value.empleado_nombre,
    observacion:     form.value.observacion || null,
    detalles: form.value.detalles.map(d => ({
      articulo_id: d.articulo_id,
      cantidad:    d.cantidad,
    })),
  }

  guardando.value = true
  try {
    if (modalForm.value.editando) {
      await api.put(`/adquisiciones/egresos/${modalForm.value.id}`, payload)
    } else {
      await api.post('/adquisiciones/egresos', payload)
    }
    modalForm.value.show = false
    await cargar()
  } catch (e) {
    errorForm.value = e.response?.data?.message || 'Error al guardar'
    if (e.response?.data?.errors) {
      errorForm.value = Object.values(e.response.data.errors).flat().join(' | ')
    }
  } finally { guardando.value = false }
}

async function confirmar(id) {
  if (!confirm('¿Confirmar despacho? Se descontará el stock de los artículos.')) return
  try {
    await api.patch(`/adquisiciones/egresos/${id}/confirmar`)
    await cargar()
  } catch (e) {
    alert(e.response?.data?.message || 'Error al confirmar')
  }
}

function abrirReverso(id) {
  modalReverso.value = { show: true, id, motivo: '', error: '', guardando: false }
}

async function confirmarReverso() {
  modalReverso.value.error = ''
  if (!modalReverso.value.motivo.trim()) {
    modalReverso.value.error = 'Debe ingresar el motivo del reverso.'; return
  }
  modalReverso.value.guardando = true
  try {
    await api.patch(`/adquisiciones/egresos/${modalReverso.value.id}/reversar`, {
      motivo_reverso: modalReverso.value.motivo,
    })
    modalReverso.value.show = false
    await cargar()
  } catch (e) {
    modalReverso.value.error = e.response?.data?.message ||
      Object.values(e.response?.data?.errors || {}).flat().join(' ') ||
      'Error al reversar'
  } finally { modalReverso.value.guardando = false }
}

async function descargarPdf(id) {
  try {
    const { data } = await api.get(`/adquisiciones/egresos/${id}/pdf`, { responseType: 'blob' })
    const url  = window.URL.createObjectURL(new Blob([data], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href  = url
    link.setAttribute('download', `egreso-bodega-${id}.pdf`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch { alert('Error al generar el PDF') }
}

async function eliminar(id) {
  if (!confirm('¿Eliminar este egreso en borrador?')) return
  await api.delete(`/adquisiciones/egresos/${id}`)
  await cargar()
}
</script>
