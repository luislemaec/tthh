<template>
  <div>
    <div class="flex flex-wrap justify-between items-center gap-3 mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Mantenimiento</h1>
      <div>
        <label class="text-xs text-gray-500 mr-2">Año</label>
        <select v-model.number="anio" @change="cargar" class="border rounded-lg px-3 py-2 text-sm">
          <option v-for="a in anios" :key="a" :value="a">{{ a }}</option>
        </select>
      </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow-sm p-4 flex flex-wrap gap-3 items-end mb-4">
      <div class="flex-1 min-w-56">
        <label class="block text-xs text-gray-500 mb-1">Buscar</label>
        <input v-model="filtros.busqueda" @input="cargarDebounced" type="text"
          placeholder="Código, serie, marca, modelo, descripción..."
          class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2" style="--tw-ring-color:#4d7c8a55;" />
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">Tipo</label>
        <select v-model="filtros.tipo_equipo_id" @change="cargar" class="border rounded-lg px-3 py-2 text-sm">
          <option value="">Todos</option>
          <option v-for="t in tipos" :key="t.id" :value="t.id">{{ t.nombre }}</option>
        </select>
      </div>
    </div>

    <!-- Resumen visual -->
    <div class="bg-white rounded-xl shadow-sm p-5 mb-5 flex flex-wrap items-center gap-6">
      <div class="relative h-36 w-36 flex-shrink-0">
        <canvas ref="chartAvance"></canvas>
        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
          <span class="text-2xl font-bold text-gray-800">{{ porcentajeAvance }}%</span>
          <span class="text-[10px] text-gray-400 uppercase tracking-wide">Avance</span>
        </div>
      </div>
      <div class="flex-1 min-w-56 grid grid-cols-2 gap-4">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide" style="color:#16a34a;">Realizados {{ anio }}</p>
          <p class="text-2xl font-bold text-gray-800">{{ realizados.length }}</p>
        </div>
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide" style="color:#dc2626;">Pendientes {{ anio }}</p>
          <p class="text-2xl font-bold text-gray-800">{{ pendientes.length }}</p>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex flex-wrap justify-between items-center gap-2 mb-4">
      <div class="flex gap-2">
        <button @click="tab = 'pendientes'"
          class="px-4 py-2 text-sm font-medium rounded-lg"
          :style="tab === 'pendientes' ? 'background-color:#4d7c8a; color:#fff;' : 'background-color:#fff; color:#4d7c8a; border:1px solid #4d7c8a;'">
          Pendientes {{ anio }} ({{ pendientes.length }})
        </button>
        <button @click="tab = 'realizados'"
          class="px-4 py-2 text-sm font-medium rounded-lg"
          :style="tab === 'realizados' ? 'background-color:#4d7c8a; color:#fff;' : 'background-color:#fff; color:#4d7c8a; border:1px solid #4d7c8a;'">
          Realizados {{ anio }} ({{ realizados.length }})
        </button>
      </div>
      <button @click="abrirExterno" class="px-4 py-2 rounded-lg text-sm font-medium border hover:bg-[#4d7c8a]/5"
        style="color:#4d7c8a; border-color:#4d7c8a;">
        Registrar mantenimiento externo
      </button>
    </div>

    <!-- Pendientes -->
    <div v-if="tab === 'pendientes'" class="space-y-2">
      <div v-if="!pendientes.length" class="bg-white rounded-xl shadow p-8 text-center text-gray-400">
        Todos los equipos tienen mantenimiento registrado para {{ anio }}
      </div>
      <div v-for="e in pendientes" :key="e.id"
           class="bg-white rounded-xl shadow px-5 py-3 flex justify-between items-center gap-3">
        <div class="min-w-0">
          <p class="font-semibold text-gray-800">
            {{ e.codigo_bien }}
            <span class="text-gray-500 font-normal ml-2">{{ e.marca }} {{ e.descripcion }}</span>
          </p>
          <p class="text-xs text-gray-500 mt-0.5">
            {{ e.tipo_equipo?.nombre }}
            <span v-if="e.asignacion_activa"> · Custodio: {{ e.asignacion_activa.empleado?.apellido_emp }} {{ e.asignacion_activa.empleado?.nombre_emp }}</span>
          </p>
        </div>
        <button @click="abrirRegistro(e)" class="text-xs text-white font-medium px-3 py-1.5 rounded-lg flex-shrink-0"
          style="background-color:#4d7c8a;">
          Registrar mantenimiento
        </button>
      </div>
    </div>

    <!-- Realizados -->
    <div v-if="tab === 'realizados'" class="space-y-2">
      <div v-if="!realizados.length" class="bg-white rounded-xl shadow p-8 text-center text-gray-400">
        Sin mantenimientos registrados en {{ anio }}
      </div>

      <template v-for="grupo in realizadosAgrupados" :key="grupo.key">
        <!-- Mantenimiento interno (por equipo) -->
        <div v-if="!grupo.esLote"
             class="bg-white rounded-xl shadow px-5 py-3 flex flex-wrap justify-between items-center gap-3">
          <div class="min-w-0">
            <p class="font-semibold text-gray-800">
              {{ grupo.m.equipo?.codigo_bien }}
              <span class="text-gray-500 font-normal ml-2">{{ grupo.m.equipo?.marca }} {{ grupo.m.equipo?.descripcion }}</span>
            </p>
            <p class="text-xs text-gray-500 mt-0.5">
              {{ grupo.m.fecha_mantenimiento }} · {{ grupo.m.hora_inicio?.substring(0,5) }} - {{ grupo.m.hora_fin?.substring(0,5) }}
              · Técnico: {{ grupo.m.tecnico?.apellido_emp }} {{ grupo.m.tecnico?.nombre_emp }}
            </p>
          </div>
          <div class="flex items-center gap-2 flex-shrink-0">
            <button @click="descargarPdf(grupo.m)" class="text-xs text-white font-medium px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700">
              PDF Acta
            </button>
            <button v-if="!grupo.m.acta_alfresco_id" @click="abrirSubirFirmado(grupo.m)"
              class="text-xs text-white font-medium px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700">
              Subir firmado
            </button>
            <span v-else class="text-xs text-green-700 bg-green-100 px-2 py-1 rounded-full font-medium">Firmado</span>
          </div>
        </div>

        <!-- Mantenimiento externo (lote agrupado) -->
        <div v-else class="bg-white rounded-xl shadow px-5 py-3">
          <div class="flex flex-wrap justify-between items-center gap-3">
            <div class="min-w-0">
              <p class="font-semibold text-gray-800">
                <span class="text-[10px] font-semibold text-orange-700 bg-orange-100 px-1.5 py-0.5 rounded mr-1.5">EXTERNO</span>
                {{ grupo.proveedor }}
                <span class="text-gray-500 font-normal ml-2">{{ grupo.items[0].equipo?.tipo_equipo?.nombre }}</span>
              </p>
              <p class="text-xs text-gray-500 mt-0.5">
                {{ grupo.fecha_mantenimiento }} · {{ grupo.proceso_contratacion }} · OC {{ grupo.numero_orden_compra }}
                · {{ grupo.items.length }} equipo{{ grupo.items.length === 1 ? '' : 's' }}
              </p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
              <button @click="toggleExpandido(grupo.key)" class="text-xs text-gray-600 hover:text-gray-900 font-medium border border-gray-300 px-3 py-1 rounded-lg">
                {{ expandidos[grupo.key] ? 'Ocultar equipos' : 'Ver equipos' }}
              </button>
              <button @click="descargarPdfExterno(grupo)" class="text-xs text-white font-medium px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700">
                PDF Acta
              </button>
              <button v-if="!grupo.acta_alfresco_id" @click="abrirSubirFirmadoExterno(grupo)"
                class="text-xs text-white font-medium px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700">
                Subir firmado
              </button>
              <span v-else class="text-xs text-green-700 bg-green-100 px-2 py-1 rounded-full font-medium">Firmado</span>
            </div>
          </div>
          <div v-if="expandidos[grupo.key]" class="mt-3 pt-3 border-t grid grid-cols-2 sm:grid-cols-3 gap-2">
            <div v-for="it in grupo.items" :key="it.id" class="text-xs bg-gray-50 rounded-lg px-3 py-2">
              <p class="font-medium text-gray-700">{{ it.equipo?.codigo_bien }}</p>
              <p class="text-gray-500">{{ it.equipo?.marca }} {{ it.equipo?.modelo }}</p>
            </div>
          </div>
        </div>
      </template>
    </div>

    <!-- Modal registrar mantenimiento -->
    <div v-if="modal.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl overflow-hidden">
        <div class="px-6 py-4 flex items-start justify-between" style="background-color:#4d7c8a;">
          <div>
            <h2 class="text-lg font-bold text-white">Registrar Mantenimiento</h2>
            <p class="text-white/80 text-xs mt-0.5">{{ modal.equipo?.codigo_bien }} — {{ modal.equipo?.marca }} {{ modal.equipo?.modelo }}</p>
          </div>
          <button @click="modal.show = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-6 max-h-[75vh] overflow-y-auto">
          <p class="text-xs text-gray-500 mb-3">
            Registrado por: <span class="font-semibold text-gray-700">{{ auth.empleado?.apellido }} {{ auth.empleado?.nombre }}</span>
          </p>

          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Fecha *</label>
              <input v-model="form.fecha_mantenimiento" type="date" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Hora inicio *</label>
              <TimePicker24 v-model="form.hora_inicio" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Hora fin *</label>
              <TimePicker24 v-model="form.hora_fin" />
            </div>
          </div>

          <div class="mt-3">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Tipo</label>
            <select v-model="form.tipo" class="w-full border rounded-lg px-3 py-2 text-sm">
              <option value="PREVENTIVO">Preventivo</option>
              <option value="CORRECTIVO">Correctivo</option>
            </select>
          </div>

          <div class="mt-4">
            <label class="block text-xs font-semibold text-gray-600 mb-2">Checklist de Actividades *</label>
            <table class="w-full text-sm border rounded-lg overflow-hidden">
              <thead>
                <tr class="bg-gray-100 text-gray-600 text-xs">
                  <th class="text-left px-3 py-2">N°</th>
                  <th class="text-left px-3 py-2">Acciones Realizadas</th>
                  <th class="text-center px-3 py-2 w-16">SI</th>
                  <th class="text-center px-3 py-2 w-16">NO</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, i) in checklistForm" :key="item.actividad_id" class="border-t">
                  <td class="px-3 py-2 text-gray-500">{{ i + 1 }}</td>
                  <td class="px-3 py-2">{{ item.nombre }}</td>
                  <td class="text-center px-3 py-2">
                    <input type="radio" :name="'act_' + item.actividad_id" :checked="item.realizado" @change="item.realizado = true" />
                  </td>
                  <td class="text-center px-3 py-2">
                    <input type="radio" :name="'act_' + item.actividad_id" :checked="!item.realizado" @change="item.realizado = false" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="mt-4">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Observaciones</label>
            <textarea v-model="form.observaciones" rows="2" class="w-full border rounded-lg px-3 py-2 text-sm"></textarea>
          </div>

          <p v-if="error" class="text-red-600 text-sm mt-3">{{ error }}</p>
          <div class="flex justify-end gap-2 mt-5">
            <button @click="modal.show = false" class="px-4 py-2 text-sm text-gray-600 border rounded-lg">Cancelar</button>
            <button @click="guardar" :disabled="guardando"
              class="px-5 py-2 text-sm text-white rounded-lg hover:opacity-90 disabled:opacity-50"
              style="background-color:#4d7c8a;">
              {{ guardando ? 'Guardando...' : 'Guardar y generar acta' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal subir firmado -->
    <div v-if="modalFirmado.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 flex items-center justify-between" style="background-color:#4d7c8a;">
          <h2 class="text-lg font-bold text-white">Subir Acta Firmada</h2>
          <button @click="modalFirmado.show = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-6">
          <input type="file" accept="application/pdf" @change="e => modalFirmado.archivo = e.target.files[0]"
            class="w-full border rounded-lg px-3 py-2 text-sm" />
          <p v-if="error" class="text-red-600 text-sm mt-3">{{ error }}</p>
          <div class="flex justify-end gap-2 mt-5">
            <button @click="modalFirmado.show = false" class="px-4 py-2 text-sm text-gray-600 border rounded-lg">Cancelar</button>
            <button @click="confirmarSubirFirmado" :disabled="!modalFirmado.archivo || guardando"
              class="px-5 py-2 text-sm text-white rounded-lg hover:opacity-90 disabled:opacity-50"
              style="background-color:#4d7c8a;">
              {{ guardando ? 'Subiendo...' : 'Subir' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal registrar mantenimiento externo -->
    <div v-if="modalExterno.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">
        <div class="px-6 py-4 flex items-center justify-between" style="background-color:#4d7c8a;">
          <h2 class="text-lg font-bold text-white">Registrar Mantenimiento Externo</h2>
          <button @click="modalExterno.show = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-6">
          <p class="text-xs text-gray-500 mb-3">
            Se registrará el mantenimiento para <strong>todos los equipos</strong> de la categoría elegida que aún no
            tengan mantenimiento en el año de la fecha seleccionada.
          </p>

          <label class="block text-xs font-semibold text-gray-600 mb-1">Categoría de equipo *</label>
          <select v-model="formExterno.tipo_equipo_id" class="w-full border rounded-lg px-3 py-2 text-sm">
            <option value="">Seleccione...</option>
            <option v-for="t in tipos" :key="t.id" :value="t.id">{{ t.nombre }}</option>
          </select>

          <div class="grid grid-cols-2 gap-3 mt-3">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Fecha *</label>
              <input v-model="formExterno.fecha_mantenimiento" type="date" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Proceso de contratación *</label>
              <select v-model="formExterno.proceso_contratacion" class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="">Seleccione...</option>
                <option v-for="p in procesos" :key="p" :value="p">{{ p }}</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3 mt-3">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Proveedor *</label>
              <input v-model="formExterno.proveedor" v-uppercase class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">N° Orden de Compra *</label>
              <input v-model="formExterno.numero_orden_compra" v-uppercase class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
          </div>

          <div class="mt-3">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Observaciones</label>
            <textarea v-model="formExterno.observaciones" rows="2" class="w-full border rounded-lg px-3 py-2 text-sm"></textarea>
          </div>

          <p v-if="error" class="text-red-600 text-sm mt-3">{{ error }}</p>
          <div class="flex justify-end gap-2 mt-5">
            <button @click="modalExterno.show = false" class="px-4 py-2 text-sm text-gray-600 border rounded-lg">Cancelar</button>
            <button @click="guardarExterno" :disabled="guardando"
              class="px-5 py-2 text-sm text-white rounded-lg hover:opacity-90 disabled:opacity-50"
              style="background-color:#4d7c8a;">
              {{ guardando ? 'Guardando...' : 'Guardar y generar acta' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import TimePicker24 from '@/components/TimePicker24.vue'
import { Chart, DoughnutController, ArcElement, Tooltip, Legend } from 'chart.js'

Chart.register(DoughnutController, ArcElement, Tooltip, Legend)

const auth = useAuthStore()

const anioActual = new Date().getFullYear()
const anio  = ref(anioActual)
const anios = Array.from({ length: 5 }, (_, i) => anioActual - i)
const tab   = ref('pendientes')

const pendientes = ref([])
const realizados = ref([])
const checklistCatalogo = ref([])
const tipos    = ref([])
const procesos = ref([])
const filtros  = ref({ busqueda: '', tipo_equipo_id: '' })

let debounceTimer = null
function cargarDebounced() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(cargar, 300)
}

async function cargarTipos() {
  const { data } = await api.get('/tecnologia/tipos-equipo/activos')
  tipos.value = data
}

async function cargarProcesos() {
  const { data } = await api.get('/tecnologia/mantenimiento/procesos')
  procesos.value = data
}

const expandidos = ref({})
function toggleExpandido(key) {
  expandidos.value[key] = !expandidos.value[key]
}

const realizadosAgrupados = computed(() => {
  const grupos = []
  const porLote = {}
  for (const m of realizados.value) {
    if (m.origen === 'EXTERNO' && m.lote_externo) {
      if (!porLote[m.lote_externo]) {
        porLote[m.lote_externo] = {
          key: 'lote-' + m.lote_externo,
          esLote: true,
          lote: m.lote_externo,
          proveedor: m.proveedor,
          proceso_contratacion: m.proceso_contratacion,
          numero_orden_compra: m.numero_orden_compra,
          fecha_mantenimiento: m.fecha_mantenimiento,
          acta_alfresco_id: m.acta_alfresco_id,
          items: [],
        }
        grupos.push(porLote[m.lote_externo])
      }
      porLote[m.lote_externo].items.push(m)
    } else {
      grupos.push({ key: 'm-' + m.id, esLote: false, m })
    }
  }
  return grupos
})

const chartAvance = ref(null)
let   instAvance   = null

const porcentajeAvance = computed(() => {
  const totalEquipos = pendientes.value.length + realizados.value.length
  if (totalEquipos === 0) return 0
  return Math.round((realizados.value.length / totalEquipos) * 100)
})

async function renderChart() {
  await nextTick()
  if (instAvance) { instAvance.destroy(); instAvance = null }
  if (!chartAvance.value) return
  instAvance = new Chart(chartAvance.value, {
    type: 'doughnut',
    data: {
      labels: ['Realizados', 'Pendientes'],
      datasets: [{
        data: [realizados.value.length, pendientes.value.length],
        backgroundColor: ['#16a34a', '#e5e7eb'],
        borderWidth: 0,
      }],
    },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '72%',
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed}` } },
      },
    },
  })
}

const guardando = ref(false)
const error     = ref('')
const modal      = ref({ show: false, equipo: null })
const form        = ref({})
const checklistForm = ref([])

async function cargar() {
  const params = {
    anio: anio.value,
    busqueda: filtros.value.busqueda || undefined,
    tipo_equipo_id: filtros.value.tipo_equipo_id || undefined,
  }
  const [{ data: p }, { data: r }] = await Promise.all([
    api.get('/tecnologia/mantenimiento/pendientes', { params }),
    api.get('/tecnologia/mantenimiento/realizados', { params }),
  ])
  pendientes.value = p
  realizados.value = r
  await renderChart()
}

async function cargarChecklist() {
  const { data } = await api.get('/tecnologia/mantenimiento/checklist')
  checklistCatalogo.value = data
}

function abrirRegistro(equipo) {
  modal.value = { show: true, equipo }
  form.value = {
    fecha_mantenimiento: new Date().toISOString().substring(0, 10),
    hora_inicio: '', hora_fin: '', tipo: 'PREVENTIVO', observaciones: '',
  }
  checklistForm.value = checklistCatalogo.value.map(a => ({ actividad_id: a.id, nombre: a.nombre, realizado: false }))
  error.value = ''
}

async function guardar() {
  error.value = ''
  guardando.value = true
  try {
    const { data } = await api.post('/tecnologia/mantenimiento', {
      equipo_id: modal.value.equipo.id,
      fecha_mantenimiento: form.value.fecha_mantenimiento,
      hora_inicio: form.value.hora_inicio,
      hora_fin: form.value.hora_fin,
      tipo: form.value.tipo,
      observaciones: form.value.observaciones,
      checklist: checklistForm.value.map(c => ({ actividad_id: c.actividad_id, realizado: c.realizado })),
    })
    modal.value.show = false
    await cargar()
    await descargarPdf(data)
  } catch (e) {
    error.value = e.response?.data?.message || Object.values(e.response?.data?.errors || {})[0]?.[0] || 'Error al guardar'
  } finally {
    guardando.value = false
  }
}

async function descargarBlobPdf(url) {
  const resp = await api.get(url, { responseType: 'blob' })
  const blob = new Blob([resp.data], { type: 'application/pdf' })
  const blobUrl = URL.createObjectURL(blob)
  window.open(blobUrl, '_blank')
  setTimeout(() => URL.revokeObjectURL(blobUrl), 60000)
}

async function descargarPdf(m) {
  await descargarBlobPdf(`/tecnologia/mantenimiento/${m.id}/pdf`)
}

async function descargarPdfExterno(grupo) {
  await descargarBlobPdf(`/tecnologia/mantenimiento/externo/${grupo.lote}/pdf`)
}

// ─── Subir firmado ────────────────────────────────────────────────────────
const modalFirmado = ref({ show: false, mantenimiento: null, lote: null, archivo: null })

function abrirSubirFirmado(m) {
  modalFirmado.value = { show: true, mantenimiento: m, lote: null, archivo: null }
  error.value = ''
}

function abrirSubirFirmadoExterno(grupo) {
  modalFirmado.value = { show: true, mantenimiento: null, lote: grupo.lote, archivo: null }
  error.value = ''
}

async function confirmarSubirFirmado() {
  error.value = ''
  guardando.value = true
  try {
    const fd = new FormData()
    fd.append('archivo', modalFirmado.value.archivo)
    const url = modalFirmado.value.lote
      ? `/tecnologia/mantenimiento/externo/${modalFirmado.value.lote}/subir-firmado`
      : `/tecnologia/mantenimiento/${modalFirmado.value.mantenimiento.id}/subir-firmado`
    await api.post(url, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    modalFirmado.value.show = false
    await cargar()
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al subir el archivo'
  } finally {
    guardando.value = false
  }
}

// ─── Mantenimiento externo ────────────────────────────────────────────────
const modalExterno = ref({ show: false })
const formExterno  = ref({})

function abrirExterno() {
  modalExterno.value = { show: true }
  formExterno.value = {
    tipo_equipo_id: '', fecha_mantenimiento: new Date().toISOString().substring(0, 10),
    proveedor: '', proceso_contratacion: '', numero_orden_compra: '', observaciones: '',
  }
  error.value = ''
}

async function guardarExterno() {
  error.value = ''
  guardando.value = true
  try {
    const { data } = await api.post('/tecnologia/mantenimiento/externo', formExterno.value)
    modalExterno.value.show = false
    await cargar()
    await descargarBlobPdf(`/tecnologia/mantenimiento/externo/${data.lote_externo}/pdf`)
  } catch (e) {
    error.value = e.response?.data?.message || Object.values(e.response?.data?.errors || {})[0]?.[0] || 'Error al guardar'
  } finally {
    guardando.value = false
  }
}

onMounted(async () => {
  await Promise.all([cargarChecklist(), cargarTipos(), cargarProcesos()])
  await cargar()
})
</script>
