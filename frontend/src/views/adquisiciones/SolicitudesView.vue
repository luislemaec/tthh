<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Solicitudes de Materiales</h1>
      <button @click="abrirCrear" class="text-white px-4 py-2 rounded-lg text-sm hover:opacity-90" style="background-color:#4a5e3a;">
        + Nueva solicitud
      </button>
    </div>

    <!-- Filtros -->
    <div class="flex gap-3 mb-4 flex-wrap">
      <select v-model="filtroEstado" @change="cargar" class="border rounded-lg px-3 py-2 text-sm">
        <option value="">Todos los estados</option>
        <option value="PENDIENTE">Pendiente</option>
        <option value="APROBADO">Aprobado</option>
        <option value="DESPACHADO">Despachado</option>
        <option value="DESPACHADO PARCIAL">Despachado Parcial</option>
        <option value="NEGADO">Negado</option>
      </select>
    </div>

    <div class="space-y-4">
      <div v-if="!solicitudes.length" class="bg-white rounded-xl shadow p-8 text-center text-gray-400">
        Sin solicitudes
      </div>

      <div v-for="s in solicitudes" :key="s.id" class="bg-white rounded-xl shadow overflow-hidden">
        <!-- Cabecera solicitud -->
        <div class="px-5 py-3 border-b flex justify-between items-start">
          <div>
            <p class="font-semibold text-gray-800">
              {{ s.empleado?.apellido_emp }} {{ s.empleado?.nombre_emp }}
              <span class="text-gray-400 text-xs ml-2">#{{ s.id }}</span>
            </p>
            <p class="text-xs text-gray-500">{{ s.fecha }} · Depto. {{ s.id_depto }}</p>
            <p v-if="s.justificacion" class="text-xs text-gray-500 mt-0.5 italic">{{ s.justificacion }}</p>
          </div>
          <div class="flex items-center gap-3">
            <span :class="estadoClase(s.estado)" class="px-2 py-0.5 rounded-full text-xs font-medium">
              {{ s.estado }}
            </span>
            <!-- Acciones según rol y estado -->
            <button v-if="s.estado === 'PENDIENTE' && esSupervisorLocal"
              @click="aprobar(s.id)"
              class="text-xs text-green-700 hover:text-green-900 font-medium border border-green-300 px-3 py-1 rounded-lg">
              Aprobar
            </button>
            <button v-if="s.estado === 'PENDIENTE' && esSupervisorLocal"
              @click="negar(s.id)"
              class="text-xs text-red-600 hover:text-red-800 font-medium border border-red-200 px-3 py-1 rounded-lg">
              Negar
            </button>
            <button v-if="s.estado === 'APROBADO' && esBienes"
              @click="abrirDespacho(s)"
              class="text-xs text-amber-700 hover:text-amber-900 font-medium border border-amber-300 px-3 py-1 rounded-lg">
              Despachar
            </button>
            <button v-if="s.estado === 'PENDIENTE' && s.empleado?.id_emp === miId"
              @click="eliminar(s.id)"
              class="text-xs text-red-400 hover:text-red-600">
              Eliminar
            </button>
          </div>
        </div>

        <!-- Detalle artículos -->
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-left px-5 py-2 text-gray-500 font-medium text-xs">Artículo</th>
              <th class="text-right px-5 py-2 text-gray-500 font-medium text-xs">Solicitado</th>
              <th v-if="s.estado !== 'PENDIENTE' && s.estado !== 'APROBADO'"
                class="text-right px-5 py-2 text-gray-500 font-medium text-xs">Entregado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="d in s.detalles" :key="d.id" class="border-b hover:bg-gray-50">
              <td class="px-5 py-2">{{ d.articulo?.nombre }}</td>
              <td class="px-5 py-2 text-right">{{ d.cantidad_solicitada }} {{ d.articulo?.unidad_medida }}</td>
              <td v-if="s.estado !== 'PENDIENTE' && s.estado !== 'APROBADO'"
                class="px-5 py-2 text-right">
                <span :class="d.cantidad_autorizada < d.cantidad_solicitada ? 'text-orange-600 font-semibold' : 'text-green-700'">
                  {{ d.cantidad_autorizada ?? '-' }} {{ d.articulo?.unidad_medida }}
                </span>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="s.observacion_despacho" class="px-5 py-2 text-xs text-gray-500 italic border-t">
          Observación Bienes: {{ s.observacion_despacho }}
        </div>
      </div>
    </div>

    <!-- Modal crear solicitud -->
    <div v-if="modalCrear.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-[95vh] overflow-hidden flex flex-col">
        <div class="px-6 py-4 flex-shrink-0" style="background-color:#4a5e3a;">
          <h2 class="text-lg font-bold text-white">Nueva Solicitud de Materiales</h2>
        </div>
        <div class="p-6 overflow-y-auto">
        <div class="mb-4">
          <label class="block text-xs text-gray-600 mb-1">Justificación</label>
          <textarea v-model="modalCrear.form.justificacion" rows="2" maxlength="500"
            class="w-full border rounded px-3 py-2 text-sm focus:ring-2 outline-none"></textarea>
        </div>

        <!-- Buscador artículos -->
        <div class="mb-3 relative">
          <label class="block text-xs text-gray-600 mb-1">Buscar artículo por código o descripción</label>
          <input v-model="busquedaArticulo" @input="filtrarArticulos" type="text"
            placeholder="Escribe código o nombre del artículo..."
            class="w-full border rounded px-3 py-2 text-sm focus:ring-2 outline-none" />
          <div v-if="articulosFiltrados.length && busquedaArticulo"
            class="absolute z-10 bg-white border rounded-lg shadow-lg w-full mt-1 max-h-48 overflow-y-auto">
            <div v-for="a in articulosFiltrados" :key="a.id"
              @click="agregarDetalle(a)"
              class="px-4 py-2 text-sm hover:bg-gray-100 cursor-pointer flex justify-between items-center">
              <div>
                <span class="font-mono text-xs text-gray-400 mr-2">{{ a.codigo }}</span>
                <span class="font-medium">{{ a.nombre }}</span>
                <span v-if="a.marca" class="text-gray-400 text-xs ml-1">({{ a.marca }})</span>
              </div>
              <span class="text-xs" :class="a.stock_actual > 0 ? 'text-green-600' : 'text-red-500'">
                Stock: {{ a.stock_actual }} {{ a.unidad_medida }}
              </span>
            </div>
          </div>
        </div>

        <!-- Tabla ítems -->
        <div class="border rounded-lg overflow-hidden mb-4">
          <table class="w-full text-sm">
            <thead class="text-white" style="background-color:#4a5e3a;">
              <tr>
                <th class="text-left px-3 py-2 font-medium">Código</th>
                <th class="text-left px-3 py-2 font-medium">Artículo</th>
                <th class="text-left px-3 py-2 font-medium">Marca</th>
                <th class="text-right px-3 py-2 font-medium">Stock disp.</th>
                <th class="text-right px-3 py-2 font-medium w-28">Cantidad</th>
                <th class="w-8"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="!modalCrear.form.detalles.length">
                <td colspan="6" class="text-center py-6 text-gray-400 italic">
                  Busca un artículo arriba para agregarlo
                </td>
              </tr>
              <tr v-for="(det, i) in modalCrear.form.detalles" :key="i" class="border-t hover:bg-gray-50">
                <td class="px-3 py-1.5 font-mono text-xs text-gray-500">{{ det.codigo }}</td>
                <td class="px-3 py-1.5 font-medium">{{ det.nombre }}</td>
                <td class="px-3 py-1.5 text-gray-400 text-xs">{{ det.marca || '-' }}</td>
                <td class="px-3 py-1.5 text-right text-xs" :class="det.stock_actual > 0 ? 'text-green-600' : 'text-red-500'">
                  {{ det.stock_actual }} {{ det.unidad_medida }}
                </td>
                <td class="px-3 py-1.5">
                  <input v-model="det.cantidad_solicitada" type="number" step="1" min="1"
                    class="w-full border rounded px-2 py-1 text-sm text-right focus:ring-1 outline-none" />
                </td>
                <td class="px-3 py-1.5 text-center">
                  <button @click="modalCrear.form.detalles.splice(i, 1)" class="text-red-400 hover:text-red-600 text-xs font-bold">✕</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <p v-if="errorModal" class="text-red-600 text-sm mb-3">{{ errorModal }}</p>
        <div class="flex justify-end gap-3">
          <button @click="modalCrear.show = false" class="px-4 py-2 text-sm text-gray-600">Cancelar</button>
          <button @click="guardarSolicitud" :disabled="guardando"
            class="text-white px-5 py-2 rounded-lg text-sm hover:opacity-90 disabled:opacity-50"
            style="background-color:#4a5e3a;">
            {{ guardando ? 'Enviando...' : 'Enviar solicitud' }}
          </button>
        </div>
        </div><!-- /p-6 overflow-y-auto -->
      </div>
    </div>

    <!-- Modal despachar (Bienes) -->
    <div v-if="modalDespacho.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-xl max-h-[90vh] overflow-hidden flex flex-col">
        <div class="px-6 py-4 flex-shrink-0" style="background-color:#4a5e3a;">
          <h2 class="text-lg font-bold text-white">Autorizar Cantidades — Despacho</h2>
        </div>
        <div class="p-6 overflow-y-auto">
        <p class="text-sm text-gray-500 mb-4">
          Solicitud de {{ modalDespacho.solicitud?.empleado?.apellido_emp }} {{ modalDespacho.solicitud?.empleado?.nombre_emp }}
        </p>

        <div class="space-y-3 mb-4">
          <div v-for="(det, i) in modalDespacho.detalles" :key="det.id" class="border rounded-lg p-3 bg-gray-50">
            <p class="text-sm font-medium text-gray-700 mb-2">{{ det.articulo?.nombre }}</p>
            <div class="flex items-center gap-4 text-sm">
              <span class="text-gray-500">Solicitado: <b>{{ det.cantidad_solicitada }}</b></span>
              <span class="text-gray-500">Stock disponible: <b :class="det.articulo?.stock_actual < det.cantidad_solicitada ? 'text-orange-600' : 'text-green-700'">
                {{ det.articulo?.stock_actual }}
              </b></span>
            </div>
            <div class="mt-2">
              <label class="block text-xs text-gray-500 mb-1">Cantidad a entregar *</label>
              <input v-model="modalDespacho.detalles[i].cantidad_autorizada" type="number" step="1" min="0"
                :max="det.articulo?.stock_actual"
                class="w-32 border rounded px-2 py-1.5 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
            </div>
          </div>
        </div>

        <div class="mb-4">
          <label class="block text-xs text-gray-600 mb-1">Observación</label>
          <textarea v-model="modalDespacho.observacion" rows="2" maxlength="500"
            class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none"></textarea>
        </div>

        <p v-if="errorModal" class="text-red-600 text-sm mb-3">{{ errorModal }}</p>
        <div class="flex justify-end gap-3">
          <button @click="modalDespacho.show = false" class="px-4 py-2 text-sm text-gray-600">Cancelar</button>
          <button @click="confirmarDespacho" :disabled="guardando"
            class="bg-amber-700 text-white px-5 py-2 rounded-lg text-sm hover:bg-amber-800 disabled:opacity-50">
            {{ guardando ? 'Guardando...' : 'Confirmar despacho' }}
          </button>
        </div>
        </div><!-- /p-6 overflow-y-auto -->
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import api from '@/services/api'

const auth         = useAuthStore()
const solicitudes  = ref([])
const articulos    = ref([])
const filtroEstado = ref('')
const guardando    = ref(false)
const errorModal   = ref('')
const busquedaArticulo   = ref('')
const articulosFiltrados = ref([])

const miId              = computed(() => auth.empleado?.id_emp)
const esBienes          = computed(() => auth.tieneRol('BIENES'))
const esSupervisorLocal = ref(false)

const modalCrear    = ref({ show: false, form: { justificacion: '', detalles: [] } })
const modalDespacho = ref({ show: false, solicitud: null, detalles: [], observacion: '' })

function filtrarArticulos() {
  const q = busquedaArticulo.value.toLowerCase().trim()
  if (!q) { articulosFiltrados.value = []; return }
  articulosFiltrados.value = articulos.value
    .filter(a => a.estado === 'ACTIVO' &&
      (a.codigo.toLowerCase().includes(q) || a.nombre.toLowerCase().includes(q) || (a.marca || '').toLowerCase().includes(q))
    ).slice(0, 10)
}

function estadoClase(estado) {
  return {
    'bg-yellow-100 text-yellow-700': estado === 'PENDIENTE',
    'bg-blue-100 text-blue-700':     estado === 'APROBADO',
    'bg-green-100 text-green-700':   estado === 'DESPACHADO',
    'bg-orange-100 text-orange-700': estado === 'DESPACHADO PARCIAL',
    'bg-red-100 text-red-700':       estado === 'NEGADO',
  }
}

async function cargar() {
  const params = filtroEstado.value ? { estado: filtroEstado.value } : {}
  const { data } = await api.get('/adquisiciones/solicitudes', { params })
  solicitudes.value = data
}

onMounted(async () => {
  const [, a, rol] = await Promise.all([
    cargar(),
    api.get('/adquisiciones/articulos'),
    api.get('/horas-extras/mi-rol'),
  ])
  articulos.value = a.data
  esSupervisorLocal.value = rol.data.es_supervisor || rol.data.es_admin_th
})

function abrirCrear() {
  modalCrear.value = { show: true, form: { justificacion: '', detalles: [] } }
  busquedaArticulo.value = ''
  articulosFiltrados.value = []
  errorModal.value = ''
}

function agregarDetalle(a) {
  const yaExiste = modalCrear.value.form.detalles.find(d => d.articulo_id === a.id)
  if (yaExiste) { yaExiste.cantidad_solicitada++; }
  else {
    modalCrear.value.form.detalles.push({
      articulo_id: a.id, codigo: a.codigo, nombre: a.nombre,
      marca: a.marca, unidad_medida: a.unidad_medida, stock_actual: a.stock_actual,
      cantidad_solicitada: 1,
    })
  }
  busquedaArticulo.value = ''
  articulosFiltrados.value = []
}

async function guardarSolicitud() {
  errorModal.value = ''
  if (!modalCrear.value.form.detalles.length) { errorModal.value = 'Agregue al menos un artículo.'; return }
  guardando.value = true
  try {
    await api.post('/adquisiciones/solicitudes', modalCrear.value.form)
    modalCrear.value.show = false
    await cargar()
  } catch (e) {
    errorModal.value = e.response?.data?.message || 'Error al enviar'
  } finally { guardando.value = false }
}

async function aprobar(id) {
  if (!confirm('¿Aprobar esta solicitud?')) return
  await api.patch(`/adquisiciones/solicitudes/${id}/aprobar`)
  await cargar()
}

async function negar(id) {
  if (!confirm('¿Negar esta solicitud?')) return
  await api.patch(`/adquisiciones/solicitudes/${id}/negar`)
  await cargar()
}

async function eliminar(id) {
  if (!confirm('¿Eliminar esta solicitud?')) return
  await api.delete(`/adquisiciones/solicitudes/${id}`)
  await cargar()
}

function abrirDespacho(s) {
  modalDespacho.value = {
    show: true,
    solicitud: s,
    detalles: s.detalles.map(d => ({ ...d, cantidad_autorizada: Math.min(d.cantidad_solicitada, d.articulo?.stock_actual ?? 0) })),
    observacion: '',
  }
  errorModal.value = ''
}

async function confirmarDespacho() {
  errorModal.value = ''
  guardando.value = true
  try {
    const payload = {
      detalles: modalDespacho.value.detalles.map(d => ({
        det_id: d.id,
        cantidad_autorizada: parseFloat(d.cantidad_autorizada) || 0,
      })),
      observacion_despacho: modalDespacho.value.observacion,
    }
    await api.patch(`/adquisiciones/solicitudes/${modalDespacho.value.solicitud.id}/despachar`, payload)
    modalDespacho.value.show = false
    await cargar()
  } catch (e) {
    errorModal.value = e.response?.data?.message || 'Error al despachar'
  } finally { guardando.value = false }
}
</script>
