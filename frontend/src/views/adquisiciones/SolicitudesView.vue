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
      <select v-model="filtroEstado" @change="onFiltroChange" class="border rounded-lg px-3 py-2 text-sm">
        <option value="">Todos los estados</option>
        <option value="PENDIENTE">Pendiente</option>
        <option value="APROBADO">Aprobado</option>
        <option value="DESPACHADO">Despachado</option>
        <option value="DESPACHADO PARCIAL">Despachado Parcial</option>
        <option value="NEGADO">Negado</option>
      </select>
    </div>

    <!-- Lista -->
    <div class="space-y-2">
      <div v-if="!solicitudes.length" class="bg-white rounded-xl shadow p-8 text-center text-gray-400">
        Sin solicitudes
      </div>

      <div v-for="s in solicitudesPaginadas" :key="s.id"
           class="bg-white rounded-xl shadow px-5 py-3 flex justify-between items-center gap-3">
        <div class="min-w-0">
          <p class="font-semibold text-gray-800 truncate">
            {{ s.empleado?.apellido_emp }} {{ s.empleado?.nombre_emp }}
            <span class="text-gray-400 text-xs ml-2">#{{ s.id }}</span>
          </p>
          <p class="text-xs text-gray-500">{{ s.fecha }} · Depto. {{ s.id_depto }}</p>
          <p v-if="s.justificacion" class="text-xs text-gray-500 italic mt-0.5">{{ s.justificacion }}</p>
        </div>

        <div class="flex items-center gap-2 flex-shrink-0">
          <span :class="estadoClase(s.estado)" class="px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap">
            {{ s.estado }}
          </span>
          <button @click="abrirVer(s)"
            class="text-xs text-gray-600 hover:text-gray-900 font-medium border border-gray-300 px-3 py-1 rounded-lg">
            Ver
          </button>
          <button v-if="s.estado === 'PENDIENTE' && esSupervisorLocal"
            @click="abrirAprobacion(s)"
            class="text-xs text-green-700 hover:text-green-900 font-medium border border-green-300 px-3 py-1 rounded-lg">
            Revisar
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
          <button v-if="['DESPACHADO','DESPACHADO PARCIAL'].includes(s.estado)"
            @click="descargarPdf(s.id)"
            class="text-xs text-white font-medium px-3 py-1 rounded-lg hover:opacity-90"
            style="background-color:#4a5e3a;">
            PDF
          </button>
          <button v-if="s.estado === 'PENDIENTE' && s.empleado?.id_emp === miId"
            @click="eliminar(s.id)"
            class="text-xs text-red-400 hover:text-red-600">
            Eliminar
          </button>
        </div>
      </div>
    </div>

    <!-- Paginador -->
    <div v-if="solicitudes.length > 0" class="flex items-center justify-between mt-4">
      <div class="flex items-center gap-2 text-sm text-gray-600">
        <span>Mostrar</span>
        <select v-model="porPagina" @change="pagina = 1" class="border rounded px-2 py-1 text-sm">
          <option :value="10">10</option>
          <option :value="25">25</option>
          <option :value="50">50</option>
        </select>
        <span>por página · {{ solicitudes.length }} total</span>
      </div>
      <div class="flex items-center gap-1">
        <button @click="pagina--" :disabled="pagina === 1"
          class="px-3 py-1 rounded border text-sm disabled:opacity-40 hover:bg-gray-50">‹</button>
        <span class="px-3 py-1 text-sm text-gray-700">{{ pagina }} / {{ totalPaginas }}</span>
        <button @click="pagina++" :disabled="pagina >= totalPaginas"
          class="px-3 py-1 rounded border text-sm disabled:opacity-40 hover:bg-gray-50">›</button>
      </div>
    </div>

    <!-- Modal Ver detalle -->
    <div v-if="modalVer.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-xl max-h-[90vh] overflow-hidden flex flex-col">
        <div class="px-6 py-4 flex-shrink-0 flex justify-between items-center" style="background-color:#4a5e3a;">
          <h2 class="text-lg font-bold text-white">Detalle Solicitud #{{ modalVer.solicitud?.id }}</h2>
          <button @click="modalVer.show = false" class="text-white/70 hover:text-white text-xl leading-none">✕</button>
        </div>
        <div class="p-5 overflow-y-auto">
          <div class="mb-3 text-sm text-gray-600 space-y-0.5">
            <p><b>Solicitante:</b> {{ modalVer.solicitud?.empleado?.apellido_emp }} {{ modalVer.solicitud?.empleado?.nombre_emp }}</p>
            <p><b>Fecha solicitud:</b> {{ modalVer.solicitud?.fecha }} · Depto. {{ modalVer.solicitud?.id_depto }}</p>
            <p v-if="modalVer.solicitud?.aprobador">
              <b>Aprobado por:</b>
              {{ modalVer.solicitud.aprobador.apellido_emp }} {{ modalVer.solicitud.aprobador.nombre_emp }}
              <span v-if="modalVer.solicitud.fecha_aprobacion" class="text-gray-400 text-xs ml-1">· {{ modalVer.solicitud.fecha_aprobacion }}</span>
            </p>
            <p v-if="modalVer.solicitud?.justificacion"><b>Justificación:</b> {{ modalVer.solicitud?.justificacion }}</p>
            <p v-if="modalVer.solicitud?.observacion_despacho" class="italic text-gray-500">
              <b>Obs. despacho:</b> {{ modalVer.solicitud.observacion_despacho }}
            </p>
          </div>
          <table class="w-full text-sm border-collapse">
            <thead>
              <tr class="bg-gray-100 text-gray-600 text-xs">
                <th class="text-left px-3 py-2 font-medium border-b">Artículo</th>
                <th class="text-right px-3 py-2 font-medium border-b">Solicitado</th>
                <th v-if="modalVer.solicitud?.estado !== 'PENDIENTE' && modalVer.solicitud?.estado !== 'APROBADO'"
                  class="text-right px-3 py-2 font-medium border-b">Entregado</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="d in modalVer.solicitud?.detalles" :key="d.id" class="border-b hover:bg-gray-50">
                <td class="px-3 py-2">{{ d.articulo?.nombre }}</td>
                <td class="px-3 py-2 text-right">{{ d.cantidad_solicitada }} {{ d.articulo?.unidad_medida }}</td>
                <td v-if="modalVer.solicitud?.estado !== 'PENDIENTE' && modalVer.solicitud?.estado !== 'APROBADO'"
                  class="px-3 py-2 text-right">
                  <span :class="d.cantidad_autorizada < d.cantidad_solicitada ? 'text-orange-600 font-semibold' : 'text-green-700'">
                    {{ d.cantidad_autorizada ?? '-' }} {{ d.articulo?.unidad_medida }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal crear solicitud -->
    <div v-if="modalCrear.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl h-[90vh] overflow-hidden flex flex-col">
        <div class="px-6 py-4 flex-shrink-0 flex items-center justify-between" style="background-color:#4a5e3a;">
          <h2 class="text-lg font-bold text-white">Nueva Solicitud de Materiales</h2>
          <button @click="modalCrear.show = false" class="text-white/70 hover:text-white text-xl leading-none">&times;</button>
        </div>
        <div class="p-6 overflow-y-auto flex-1">
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
            class="absolute z-10 w-full mt-1 rounded-lg shadow-xl overflow-hidden border-2"
            style="border-color:#4a5e3a;">
            <!-- Cabecera del dropdown -->
            <div class="px-3 py-1.5 text-xs font-semibold text-white flex items-center justify-between"
              style="background-color:#4a5e3a;">
              <span>{{ articulosFiltrados.length }} artículo{{ articulosFiltrados.length !== 1 ? 's' : '' }} encontrado{{ articulosFiltrados.length !== 1 ? 's' : '' }}</span>
              <span class="opacity-75 font-normal">clic para agregar →</span>
            </div>
            <!-- Lista de resultados -->
            <div class="bg-white max-h-64 overflow-y-auto">
              <div v-for="a in articulosFiltrados" :key="a.id"
                @click="agregarDetalle(a)"
                class="px-4 py-2.5 text-sm cursor-pointer border-b border-gray-100 last:border-0 hover:bg-green-50 transition flex items-center gap-3">
                <span class="font-mono text-xs font-bold flex-shrink-0" style="color:#4a5e3a;">{{ a.codigo }}</span>
                <span class="font-medium text-gray-800 flex-1">{{ a.nombre }}</span>
                <span v-if="a.marca" class="text-gray-400 text-xs flex-shrink-0">({{ a.marca }})</span>
              </div>
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
        </div>
      </div>
    </div>

    <!-- Modal aprobar (Supervisor) -->
    <div v-if="modalAprobacion.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-xl max-h-[90vh] overflow-hidden flex flex-col">
        <div class="px-6 py-4 flex-shrink-0 bg-blue-700">
          <h2 class="text-lg font-bold text-white">Revisar y Aprobar Solicitud</h2>
        </div>
        <div class="p-6 overflow-y-auto">
          <p class="text-sm text-gray-500 mb-4">
            Solicitud de <b>{{ modalAprobacion.solicitud?.empleado?.apellido_emp }} {{ modalAprobacion.solicitud?.empleado?.nombre_emp }}</b>
            — {{ modalAprobacion.solicitud?.fecha }}
          </p>
          <p v-if="modalAprobacion.solicitud?.justificacion" class="text-xs italic text-gray-500 mb-3">
            "{{ modalAprobacion.solicitud.justificacion }}"
          </p>

          <div class="space-y-3 mb-4">
            <div v-for="(det, i) in modalAprobacion.detalles" :key="det.id" class="border rounded-lg p-3 bg-gray-50">
              <p class="text-sm font-medium text-gray-700 mb-2">{{ det.articulo?.nombre }}</p>
              <div class="flex items-center gap-4 text-sm flex-wrap">
                <span class="text-gray-500">Solicitado: <b>{{ det.cantidad_solicitada }}</b></span>
              </div>
              <div class="mt-2">
                <label class="block text-xs text-gray-500 mb-1">Cantidad a aprobar (puede modificar)</label>
                <input v-model="modalAprobacion.detalles[i].cantidad_nueva" type="number" step="1" min="0.01"
                  class="w-32 border rounded px-2 py-1.5 text-sm focus:ring-2 focus:ring-blue-300 outline-none" />
              </div>
            </div>
          </div>

          <p v-if="errorModal" class="text-red-600 text-sm mb-3">{{ errorModal }}</p>
          <div class="flex justify-end gap-3">
            <button @click="modalAprobacion.show = false" class="px-4 py-2 text-sm text-gray-600">Cancelar</button>
            <button @click="confirmarAprobacion" :disabled="guardando"
              class="bg-blue-700 text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-800 disabled:opacity-50">
              {{ guardando ? 'Guardando...' : 'Aprobar solicitud' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal despachar -->
    <div v-if="modalDespacho.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-xl max-h-[90vh] overflow-hidden flex flex-col">
        <div class="px-6 py-4 flex-shrink-0 flex items-center justify-between" style="background-color:#4a5e3a;">
          <h2 class="text-lg font-bold text-white">Autorizar Cantidades — Despacho</h2>
          <button @click="modalDespacho.show = false" class="text-white/70 hover:text-white text-xl leading-none">&times;</button>
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
              <span class="text-gray-500">Stock disponible: <b :class="Number(det.articulo?.stock_actual) < Number(det.cantidad_autorizada ?? det.cantidad_solicitada) ? 'text-orange-600' : 'text-green-700'">
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
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const auth         = useAuthStore()
const solicitudes  = ref([])
const articulos    = ref([])
const filtroEstado = ref('')
const guardando    = ref(false)
const errorModal   = ref('')
const busquedaArticulo   = ref('')
const articulosFiltrados = ref([])

const pagina    = ref(1)
const porPagina = ref(10)

const totalPaginas = computed(() => Math.max(1, Math.ceil(solicitudes.value.length / porPagina.value)))
const solicitudesPaginadas = computed(() => {
  const inicio = (pagina.value - 1) * porPagina.value
  return solicitudes.value.slice(inicio, inicio + porPagina.value)
})

const miId              = computed(() => auth.empleado?.id_emp)
const esBienes          = computed(() => auth.tieneRol('BIENES') || auth.tieneRol('ADQUISICIONES'))
const esSupervisorLocal = ref(false)

const modalVer        = ref({ show: false, solicitud: null })
const modalCrear      = ref({ show: false, form: { justificacion: '', detalles: [] } })
const modalAprobacion = ref({ show: false, solicitud: null, detalles: [] })
const modalDespacho   = ref({ show: false, solicitud: null, detalles: [], observacion: '' })

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
  pagina.value = 1
}

function onFiltroChange() {
  pagina.value = 1
  cargar()
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

function abrirVer(s) {
  modalVer.value = { show: true, solicitud: s }
}

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

function abrirAprobacion(s) {
  modalAprobacion.value = {
    show: true,
    solicitud: s,
    detalles: s.detalles.map(d => ({ ...d, cantidad_nueva: d.cantidad_solicitada })),
  }
  errorModal.value = ''
}

async function confirmarAprobacion() {
  errorModal.value = ''
  guardando.value = true
  try {
    const payload = {
      detalles: modalAprobacion.value.detalles.map(d => ({
        det_id: d.id,
        cantidad_solicitada: parseFloat(d.cantidad_nueva) || d.cantidad_solicitada,
      })),
    }
    await api.patch(`/adquisiciones/solicitudes/${modalAprobacion.value.solicitud.id}/aprobar`, payload)
    modalAprobacion.value.show = false
    await cargar()
  } catch (e) {
    errorModal.value = e.response?.data?.message || 'Error al aprobar'
  } finally { guardando.value = false }
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

async function descargarPdf(id) {
  try {
    const resp = await api.get(`/adquisiciones/solicitudes/${id}/pdf`, { responseType: 'blob' })
    const url  = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
    window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch {
    alert('Error al generar el PDF')
  }
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
