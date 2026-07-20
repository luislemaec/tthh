<template>
  <div>
    <div class="flex flex-wrap justify-between items-center gap-3 mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Piezas y Repuestos</h1>
      <button @click="abrirCrear" class="text-white px-4 py-2 rounded-lg text-sm font-medium hover:opacity-90 shadow-sm"
        style="background-color:#4d7c8a;">
        + Nueva pieza
      </button>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow-sm p-4 flex flex-wrap gap-3 items-end mb-4">
      <div class="flex-1 min-w-56">
        <label class="block text-xs text-gray-500 mb-1">Buscar</label>
        <input v-model="filtros.busqueda" @input="cargarDebounced" type="text"
          placeholder="Código, serie, descripción..."
          class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2" style="--tw-ring-color:#4d7c8a55;" />
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">Estado</label>
        <select v-model="filtros.estado" @change="pagina = 1; cargar()" class="border rounded-lg px-3 py-2 text-sm">
          <option value="">Todos</option>
          <option value="DISPONIBLE">Disponible</option>
          <option value="INSTALADA">Instalada</option>
          <option value="DE_BAJA">De baja</option>
        </select>
      </div>
      <span class="ml-auto text-xs font-semibold px-3 py-1.5 rounded-full" style="background-color:#4d7c8a1a; color:#4d7c8a;">
        {{ total }} registro{{ total === 1 ? '' : 's' }} encontrado{{ total === 1 ? '' : 's' }}
      </span>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <div v-if="!piezas.length" class="p-10 text-center text-gray-400">Sin piezas registradas</div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-white" style="background-color:#4d7c8a;">
              <th class="px-4 py-3">Código</th>
              <th class="px-4 py-3">Descripción</th>
              <th class="px-4 py-3">Serie</th>
              <th class="px-4 py-3">Estado</th>
              <th class="px-4 py-3">Equipo Actual</th>
              <th class="px-4 py-3 text-right">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="p in piezas" :key="p.id" class="hover:bg-gray-50 transition">
              <td class="px-4 py-3 font-mono text-xs font-medium text-gray-700">{{ p.codigo || '—' }}</td>
              <td class="px-4 py-3">
                <p class="font-medium text-gray-800">{{ p.descripcion }}</p>
                <p v-if="p.fecha_entrega" class="text-xs text-gray-400">Entregada por Bienes: {{ p.fecha_entrega }}</p>
              </td>
              <td class="px-4 py-3 text-gray-500 text-xs">{{ p.serie || '—' }}</td>
              <td class="px-4 py-3">
                <span :class="estadoBadge(p.estado)" class="px-2 py-0.5 rounded-full text-xs font-medium">
                  {{ estadoLabel(p.estado) }}
                </span>
              </td>
              <td class="px-4 py-3 text-xs">
                <span v-if="p.equipo" class="font-medium" style="color:#4d7c8a;">{{ p.equipo.codigo_bien }}</span>
                <span v-else class="text-gray-300">—</span>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-end gap-1.5 flex-wrap">
                  <button v-if="p.estado === 'DISPONIBLE'" @click="abrirInstalar(p)"
                    class="text-xs text-white font-medium px-3 py-1 rounded-lg" style="background-color:#4d7c8a;">
                    Instalar
                  </button>
                  <button v-if="p.estado === 'INSTALADA'" @click="abrirRetirar(p)"
                    class="text-xs text-white font-medium px-3 py-1 rounded-lg bg-amber-600 hover:bg-amber-700">
                    Retirar
                  </button>
                  <button v-if="p.estado === 'DE_BAJA'" @click="marcarDisponible(p)"
                    class="text-xs text-white font-medium px-3 py-1 rounded-lg bg-green-600 hover:bg-green-700">
                    Disponible
                  </button>
                  <button @click="abrirHistorial(p)"
                    class="text-xs text-gray-600 hover:text-gray-900 font-medium border border-gray-300 px-3 py-1 rounded-lg hover:bg-gray-50">
                    Historial
                  </button>
                  <button @click="abrirEditar(p)"
                    class="text-xs text-gray-600 hover:text-gray-900 font-medium border border-gray-300 px-3 py-1 rounded-lg hover:bg-gray-50">
                    Editar
                  </button>
                  <button v-if="p.estado === 'DISPONIBLE'" @click="marcarBaja(p)"
                    class="text-xs text-red-600 hover:text-red-800 font-medium border border-red-200 px-3 py-1 rounded-lg hover:bg-red-50">
                    Dar de baja
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Paginación -->
      <div v-if="totalPaginas > 1" class="px-4 py-3 border-t flex items-center justify-center gap-1 text-sm">
        <button @click="cambiarPagina(1)" :disabled="pagina === 1" class="px-2 py-1 rounded border disabled:opacity-40 hover:bg-gray-50">«</button>
        <button @click="cambiarPagina(pagina - 1)" :disabled="pagina === 1" class="px-3 py-1 rounded border disabled:opacity-40 hover:bg-gray-50">‹</button>
        <span class="px-3 font-medium text-gray-600">{{ pagina }} / {{ totalPaginas }}</span>
        <button @click="cambiarPagina(pagina + 1)" :disabled="pagina >= totalPaginas" class="px-3 py-1 rounded border disabled:opacity-40 hover:bg-gray-50">›</button>
        <button @click="cambiarPagina(totalPaginas)" :disabled="pagina >= totalPaginas" class="px-2 py-1 rounded border disabled:opacity-40 hover:bg-gray-50">»</button>
      </div>
    </div>

    <!-- Modal crear/editar -->
    <div v-if="modal.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 flex items-center justify-between" style="background-color:#4d7c8a;">
          <h2 class="text-lg font-bold text-white">{{ modal.id ? 'Editar Pieza' : 'Nueva Pieza' }}</h2>
          <button @click="modal.show = false" class="text-white/80 hover:text-white text-xl leading-none">&times;</button>
        </div>
        <div class="p-6">
          <label class="block text-xs font-semibold text-gray-600 mb-1">Descripción *</label>
          <input v-model="form.descripcion" placeholder="Ej. Disco SSD 500GB Kingston" class="w-full border rounded-lg px-3 py-2 text-sm" />
          <div class="grid grid-cols-2 gap-3 mt-3">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Código</label>
              <input v-model="form.codigo" v-uppercase class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Serie</label>
              <input v-model="form.serie" v-uppercase class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
          </div>
          <div class="mt-3">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Fecha de entrega (Bienes) *</label>
            <input v-model="form.fecha_entrega" type="date" class="w-full border rounded-lg px-3 py-2 text-sm" />
            <p class="text-xs text-gray-400 mt-1">Fecha en que la Unidad de Bienes entregó la pieza a Tecnología</p>
          </div>
          <div class="mt-3">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Observaciones</label>
            <textarea v-model="form.observaciones" rows="2" class="w-full border rounded-lg px-3 py-2 text-sm"></textarea>
          </div>
          <p v-if="error" class="text-red-600 text-sm mt-3">{{ error }}</p>
          <div class="flex justify-end gap-2 mt-5">
            <button @click="modal.show = false" class="px-4 py-2 text-sm text-gray-600 border rounded-lg">Cancelar</button>
            <button @click="guardar" :disabled="guardando"
              class="px-5 py-2 text-sm text-white rounded-lg hover:opacity-90 disabled:opacity-50"
              style="background-color:#4d7c8a;">
              {{ guardando ? 'Guardando...' : 'Guardar' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Instalar -->
    <div v-if="modalInstalar.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 flex items-start justify-between" style="background-color:#4d7c8a;">
          <div>
            <h2 class="text-lg font-bold text-white">Instalar Pieza</h2>
            <p class="text-white/80 text-xs mt-0.5">{{ modalInstalar.pieza?.descripcion }}</p>
          </div>
          <button @click="modalInstalar.show = false" class="text-white/80 hover:text-white text-xl leading-none">&times;</button>
        </div>
        <div class="p-6">
          <label class="block text-xs font-semibold text-gray-600 mb-1">Buscar equipo *</label>
          <input v-model="busquedaEquipo" @input="buscarEquipo" type="text"
            placeholder="Código, marca, modelo..." class="w-full border rounded-lg px-3 py-2 text-sm" />

          <div v-if="resultadosEquipo.length > 0 && !equipoSeleccionado"
            class="mt-2 border rounded-lg divide-y max-h-48 overflow-y-auto shadow-sm">
            <button v-for="e in resultadosEquipo" :key="e.id" @click="seleccionarEquipo(e)"
              class="w-full text-left px-4 py-2.5 hover:bg-gray-50 text-sm transition">
              <span class="font-medium">{{ e.codigo_bien }}</span>
              <span class="text-gray-400 ml-2 text-xs">{{ e.marca }} {{ e.modelo }}</span>
            </button>
          </div>

          <div v-if="equipoSeleccionado" class="mt-2 flex items-center justify-between bg-gray-50 border rounded-lg px-4 py-3">
            <div>
              <p class="font-semibold text-gray-800">{{ equipoSeleccionado.codigo_bien }}</p>
              <p class="text-xs text-gray-500">{{ equipoSeleccionado.marca }} {{ equipoSeleccionado.modelo }}</p>
            </div>
            <button @click="equipoSeleccionado = null; busquedaEquipo = ''" class="text-gray-400 hover:text-gray-600 text-xs">Cambiar</button>
          </div>

          <div class="mt-3">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Fecha de instalación *</label>
            <input v-model="formInstalar.fecha_instalacion" type="date" class="w-full border rounded-lg px-3 py-2 text-sm" />
          </div>
          <div class="mt-3">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Observación</label>
            <textarea v-model="formInstalar.observacion" rows="2" class="w-full border rounded-lg px-3 py-2 text-sm"></textarea>
          </div>

          <p v-if="error" class="text-red-600 text-sm mt-3">{{ error }}</p>
          <div class="flex justify-end gap-2 mt-5">
            <button @click="modalInstalar.show = false" class="px-4 py-2 text-sm text-gray-600 border rounded-lg">Cancelar</button>
            <button @click="confirmarInstalar" :disabled="guardando || !equipoSeleccionado"
              class="px-5 py-2 text-sm text-white rounded-lg hover:opacity-90 disabled:opacity-50"
              style="background-color:#4d7c8a;">
              {{ guardando ? 'Instalando...' : 'Instalar' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Retirar -->
    <div v-if="modalRetirar.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 flex items-start justify-between" style="background-color:#4d7c8a;">
          <div>
            <h2 class="text-lg font-bold text-white">Retirar Pieza</h2>
            <p class="text-white/80 text-xs mt-0.5">{{ modalRetirar.pieza?.descripcion }}</p>
          </div>
          <button @click="modalRetirar.show = false" class="text-white/80 hover:text-white text-xl leading-none">&times;</button>
        </div>
        <div class="p-6">
          <label class="block text-xs font-semibold text-gray-600 mb-1">Fecha de retiro *</label>
          <input v-model="formRetirar.fecha_retiro" type="date" class="w-full border rounded-lg px-3 py-2 text-sm" />

          <label class="block text-xs font-semibold text-gray-600 mb-1 mt-3">Motivo *</label>
          <select v-model="formRetirar.motivo_retiro" class="w-full border rounded-lg px-3 py-2 text-sm">
            <option value="">Seleccione...</option>
            <option value="REEMPLAZO">Reemplazo</option>
            <option value="DAÑO">Daño</option>
            <option value="DESINSTALACION">Desinstalación</option>
            <option value="OTRO">Otro</option>
          </select>
          <label class="block text-xs font-semibold text-gray-600 mb-1 mt-3">Observación</label>
          <textarea v-model="formRetirar.observacion" rows="3" class="w-full border rounded-lg px-3 py-2 text-sm"></textarea>

          <p v-if="error" class="text-red-600 text-sm mt-3">{{ error }}</p>
          <div class="flex justify-end gap-2 mt-5">
            <button @click="modalRetirar.show = false" class="px-4 py-2 text-sm text-gray-600 border rounded-lg">Cancelar</button>
            <button @click="confirmarRetirar" :disabled="guardando || !formRetirar.motivo_retiro"
              class="px-5 py-2 text-sm text-white rounded-lg hover:opacity-90 disabled:opacity-50 bg-amber-600 hover:bg-amber-700">
              {{ guardando ? 'Guardando...' : 'Confirmar retiro' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Historial -->
    <div v-if="modalHistorial.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">
        <div class="px-6 py-4 flex items-center justify-between" style="background-color:#4d7c8a;">
          <div>
            <h2 class="text-lg font-bold text-white">Historial de la Pieza</h2>
            <p class="text-white/80 text-xs mt-0.5">{{ modalHistorial.pieza?.descripcion }}</p>
          </div>
          <button @click="modalHistorial.show = false" class="text-white/80 hover:text-white text-xl leading-none">&times;</button>
        </div>
        <div class="p-6 max-h-[60vh] overflow-y-auto">
          <div v-if="!modalHistorial.datos.length" class="text-center text-gray-400 py-6">Sin movimientos registrados</div>
          <ol v-else class="relative border-l-2 ml-2" style="border-color:#4d7c8a33;">
            <li v-for="m in modalHistorial.datos" :key="m.id" class="mb-6 ml-5 last:mb-0">
              <span class="absolute -left-[9px] w-4 h-4 rounded-full border-2 border-white"
                :style="m.fecha_retiro ? 'background-color:#9ca3af;' : 'background-color:#22c55e;'"></span>
              <div class="bg-gray-50 rounded-lg px-4 py-3">
                <div class="flex items-center justify-between gap-2">
                  <p class="font-semibold text-gray-800">
                    {{ m.equipo?.codigo_bien }}
                    <span class="text-gray-500 font-normal">— {{ m.equipo?.descripcion }}</span>
                  </p>
                  <span v-if="!m.fecha_retiro" class="text-xs font-semibold text-green-600 bg-green-100 px-2 py-0.5 rounded-full">Instalada</span>
                </div>
                <p class="text-xs mt-1" style="color:#4d7c8a;">
                  <span v-if="m.equipo?.asignacion_activa">
                    Custodio actual: {{ m.equipo.asignacion_activa.empleado?.apellido_emp }} {{ m.equipo.asignacion_activa.empleado?.nombre_emp }}
                  </span>
                  <span v-else class="text-gray-400">Sin custodio asignado actualmente</span>
                </p>
                <p class="text-xs text-gray-500 mt-1">
                  Desde {{ m.fecha_instalacion }}
                  <span v-if="m.fecha_retiro"> hasta {{ m.fecha_retiro }} · {{ m.motivo_retiro }}</span>
                </p>
                <p v-if="m.observacion" class="text-xs text-gray-400 mt-1">{{ m.observacion }}</p>
              </div>
            </li>
          </ol>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

const piezas  = ref([])
const filtros = ref({ busqueda: '', estado: '' })

const pagina       = ref(1)
const totalPaginas  = ref(1)
const total         = ref(0)

const guardando = ref(false)
const error     = ref('')
const modal     = ref({ show: false, id: null })
const form      = ref({})

let debounceTimer = null
function cargarDebounced() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => { pagina.value = 1; cargar() }, 300)
}

async function cargar() {
  const { data } = await api.get('/tecnologia/piezas', {
    params: {
      busqueda: filtros.value.busqueda || undefined,
      estado: filtros.value.estado || undefined,
      page: pagina.value,
    },
  })
  piezas.value       = data.data ?? data
  total.value         = data.total ?? piezas.value.length
  totalPaginas.value  = data.last_page ?? 1
}

function cambiarPagina(p) {
  if (p < 1 || p > totalPaginas.value) return
  pagina.value = p
  cargar()
}

function estadoBadge(e) {
  if (e === 'DISPONIBLE') return 'bg-green-100 text-green-700'
  if (e === 'INSTALADA') return 'bg-blue-100 text-blue-700'
  return 'bg-gray-200 text-gray-600'
}
function estadoLabel(e) {
  const labels = { DISPONIBLE: 'Disponible', INSTALADA: 'Instalada', DE_BAJA: 'De baja' }
  return labels[e] || e
}

function abrirCrear() {
  form.value = { codigo: '', serie: '', descripcion: '', fecha_entrega: new Date().toISOString().substring(0, 10), observaciones: '' }
  modal.value = { show: true, id: null }
  error.value = ''
}

function abrirEditar(p) {
  form.value = { ...p }
  modal.value = { show: true, id: p.id }
  error.value = ''
}

async function guardar() {
  error.value = ''
  guardando.value = true
  try {
    if (modal.value.id) {
      await api.put(`/tecnologia/piezas/${modal.value.id}`, form.value)
    } else {
      await api.post('/tecnologia/piezas', form.value)
    }
    modal.value.show = false
    await cargar()
  } catch (e) {
    error.value = e.response?.data?.message || Object.values(e.response?.data?.errors || {})[0]?.[0] || 'Error al guardar'
  } finally {
    guardando.value = false
  }
}

async function marcarBaja(p) {
  if (!confirm(`¿Dar de baja la pieza "${p.descripcion}"?`)) return
  await api.patch(`/tecnologia/piezas/${p.id}/baja`)
  await cargar()
}

async function marcarDisponible(p) {
  await api.patch(`/tecnologia/piezas/${p.id}/disponible`)
  await cargar()
}

// ─── Instalar ─────────────────────────────────────────────────────────────
const modalInstalar    = ref({ show: false, pieza: null })
const formInstalar     = ref({ fecha_instalacion: '', observacion: '' })
const busquedaEquipo   = ref('')
const resultadosEquipo = ref([])
const equipoSeleccionado = ref(null)
let   busquedaEquipoTimer = null

function abrirInstalar(p) {
  modalInstalar.value = { show: true, pieza: p }
  formInstalar.value = { fecha_instalacion: new Date().toISOString().substring(0, 10), observacion: '' }
  busquedaEquipo.value = ''
  resultadosEquipo.value = []
  equipoSeleccionado.value = null
  error.value = ''
}

function buscarEquipo() {
  if (equipoSeleccionado.value) return
  clearTimeout(busquedaEquipoTimer)
  if (busquedaEquipo.value.length < 2) { resultadosEquipo.value = []; return }
  busquedaEquipoTimer = setTimeout(async () => {
    try {
      const { data } = await api.get('/tecnologia/equipos', { params: { busqueda: busquedaEquipo.value } })
      resultadosEquipo.value = data.data ?? data
    } catch { resultadosEquipo.value = [] }
  }, 300)
}

function seleccionarEquipo(e) {
  equipoSeleccionado.value = e
  busquedaEquipo.value = e.codigo_bien
  resultadosEquipo.value = []
}

async function confirmarInstalar() {
  error.value = ''
  guardando.value = true
  try {
    await api.patch(`/tecnologia/piezas/${modalInstalar.value.pieza.id}/instalar`, {
      equipo_id: equipoSeleccionado.value.id,
      fecha_instalacion: formInstalar.value.fecha_instalacion,
      observacion: formInstalar.value.observacion,
    })
    modalInstalar.value.show = false
    await cargar()
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al instalar'
  } finally {
    guardando.value = false
  }
}

// ─── Retirar ──────────────────────────────────────────────────────────────
const modalRetirar = ref({ show: false, pieza: null })
const formRetirar  = ref({ fecha_retiro: '', motivo_retiro: '', observacion: '' })

function abrirRetirar(p) {
  modalRetirar.value = { show: true, pieza: p }
  formRetirar.value = { fecha_retiro: new Date().toISOString().substring(0, 10), motivo_retiro: '', observacion: '' }
  error.value = ''
}

async function confirmarRetirar() {
  error.value = ''
  guardando.value = true
  try {
    await api.patch(`/tecnologia/piezas/${modalRetirar.value.pieza.id}/retirar`, formRetirar.value)
    modalRetirar.value.show = false
    await cargar()
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al retirar'
  } finally {
    guardando.value = false
  }
}

// ─── Historial ────────────────────────────────────────────────────────────
const modalHistorial = ref({ show: false, pieza: null, datos: [] })

async function abrirHistorial(p) {
  modalHistorial.value = { show: true, pieza: p, datos: [] }
  const { data } = await api.get(`/tecnologia/piezas/${p.id}/historial`)
  modalHistorial.value.datos = data
}

onMounted(cargar)
</script>
