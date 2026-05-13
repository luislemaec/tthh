<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Solicitudes de Movilización</h1>
      <button v-if="puedeCrear" @click="abrirCrear"
        class="text-white px-4 py-2 rounded-lg text-sm hover:opacity-90" style="background-color:#1e3a5f;">
        + Nueva solicitud
      </button>
    </div>

    <!-- Filtro estado -->
    <div class="flex gap-3 mb-4">
      <select v-model="filtroEstado" @change="pagina = 1" class="border rounded-lg px-3 py-2 text-sm">
        <option value="">Todos los estados</option>
        <option value="PENDIENTE">Pendiente</option>
        <option value="APROBADO">Aprobado</option>
        <option value="NEGADO">Negado</option>
        <option value="COMPLETADO">Completado</option>
      </select>
    </div>

    <!-- Lista -->
    <div class="space-y-2">
      <div v-if="!listaFiltrada.length" class="bg-white rounded-xl shadow p-8 text-center text-gray-400">
        Sin solicitudes
      </div>

      <div v-for="s in listaPaginada" :key="s.id"
           class="bg-white rounded-xl shadow px-5 py-3 flex justify-between items-center gap-3">
        <div class="min-w-0 flex-1">
          <p class="font-semibold text-gray-800 truncate">
            {{ s.solicitante?.apellido_emp }} {{ s.solicitante?.nombre_emp }}
            <span class="text-gray-400 text-xs ml-2">#{{ s.id }}</span>
          </p>
          <p class="text-xs text-gray-500">
            {{ formatFechaCorta(s.fecha_movilizacion) }} · {{ s.hora_salida?.slice(0,5) }}–{{ s.hora_retorno?.slice(0,5) }}
            · {{ s.lugar_destino }}
          </p>
          <p class="text-xs text-gray-400 italic mt-0.5 truncate">{{ s.motivo }}</p>
        </div>

        <div class="flex items-center gap-2 flex-shrink-0">
          <span :class="estadoBadge(s.estado)" class="px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap">
            {{ s.estado }}
          </span>
          <button @click="abrirVer(s)"
            class="text-xs text-gray-600 hover:text-gray-900 font-medium border border-gray-300 px-3 py-1 rounded-lg">
            Ver
          </button>
          <button v-if="esTransporte && s.estado === 'PENDIENTE'" @click="abrirAprobar(s)"
            class="text-xs text-green-700 hover:text-green-900 font-medium border border-green-300 px-3 py-1 rounded-lg">
            Aprobar
          </button>
          <button v-if="esTransporte && s.estado === 'PENDIENTE'" @click="abrirNegar(s)"
            class="text-xs text-red-600 hover:text-red-800 font-medium border border-red-200 px-3 py-1 rounded-lg">
            Negar
          </button>
          <button v-if="esConductor && s.estado === 'APROBADO'" @click="abrirHojaRuta(s)"
            class="text-xs text-amber-700 hover:text-amber-900 font-medium border border-amber-300 px-3 py-1 rounded-lg">
            Hoja de ruta
          </button>
          <button v-if="s.estado === 'APROBADO' || s.estado === 'COMPLETADO'" @click="verPdf(s.id)"
            class="text-xs text-red-600 hover:text-red-800 font-medium border border-red-200 px-3 py-1 rounded-lg">
            PDF
          </button>
        </div>
      </div>
    </div>

    <!-- Paginador -->
    <div v-if="listaFiltrada.length > 0" class="flex items-center justify-between mt-4">
      <div class="flex items-center gap-2 text-sm text-gray-600">
        <span>Mostrar</span>
        <select v-model="porPagina" @change="pagina = 1" class="border rounded px-2 py-1 text-sm">
          <option :value="10">10</option>
          <option :value="25">25</option>
          <option :value="50">50</option>
        </select>
        <span>por página · {{ listaFiltrada.length }} total</span>
      </div>
      <div class="flex gap-1">
        <button @click="pagina--" :disabled="pagina === 1"
          class="px-3 py-1 text-sm border rounded disabled:opacity-40">‹</button>
        <span class="px-3 py-1 text-sm text-gray-600">{{ pagina }} / {{ totalPaginas }}</span>
        <button @click="pagina++" :disabled="pagina === totalPaginas"
          class="px-3 py-1 text-sm border rounded disabled:opacity-40">›</button>
      </div>
    </div>

    <!-- Modal Ver -->
    <div v-if="modalVer.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-lg font-bold text-gray-800">Solicitud #{{ modalVer.s?.id }}</h2>
          <button @click="modalVer.show = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
        </div>
        <template v-if="modalVer.s">
          <table class="w-full text-sm">
            <tbody>
              <tr class="border-b"><td class="py-2 font-semibold text-gray-500 w-2/5">Solicitante</td>
                <td class="py-2">{{ modalVer.s.solicitante?.apellido_emp }} {{ modalVer.s.solicitante?.nombre_emp }}</td></tr>
              <tr class="border-b"><td class="py-2 font-semibold text-gray-500">Motivo</td>
                <td class="py-2">{{ modalVer.s.motivo }}</td></tr>
              <tr class="border-b"><td class="py-2 font-semibold text-gray-500">Fecha</td>
                <td class="py-2">{{ formatFechaCorta(modalVer.s.fecha_movilizacion) }}</td></tr>
              <tr class="border-b"><td class="py-2 font-semibold text-gray-500">Horario</td>
                <td class="py-2">{{ modalVer.s.hora_salida?.slice(0,5) }} — {{ modalVer.s.hora_retorno?.slice(0,5) }}</td></tr>
              <tr class="border-b"><td class="py-2 font-semibold text-gray-500">Lugar salida</td>
                <td class="py-2">{{ modalVer.s.lugar_salida || '—' }}</td></tr>
              <tr class="border-b"><td class="py-2 font-semibold text-gray-500">Lugar destino</td>
                <td class="py-2">{{ modalVer.s.lugar_destino }}</td></tr>
              <tr class="border-b"><td class="py-2 font-semibold text-gray-500">N° Personas</td>
                <td class="py-2">{{ modalVer.s.num_personas }}</td></tr>
              <tr class="border-b"><td class="py-2 font-semibold text-gray-500">Estado</td>
                <td class="py-2">
                  <span :class="estadoBadge(modalVer.s.estado)" class="px-2 py-0.5 rounded-full text-xs font-medium">
                    {{ modalVer.s.estado }}
                  </span>
                </td></tr>
              <template v-if="modalVer.s.vehiculo">
                <tr class="border-b"><td class="py-2 font-semibold text-gray-500">Vehículo</td>
                  <td class="py-2">{{ modalVer.s.vehiculo?.placa }} · {{ modalVer.s.vehiculo?.marca }} {{ modalVer.s.vehiculo?.modelo }}</td></tr>
              </template>
              <template v-if="modalVer.s.conductor">
                <tr class="border-b"><td class="py-2 font-semibold text-gray-500">Conductor</td>
                  <td class="py-2">{{ modalVer.s.conductor?.apellido_emp }} {{ modalVer.s.conductor?.nombre_emp }}</td></tr>
              </template>
              <tr v-if="modalVer.s.observacion" class="border-b">
                <td class="py-2 font-semibold text-gray-500">Observación</td>
                <td class="py-2">{{ modalVer.s.observacion }}</td></tr>
              <template v-if="modalVer.s.estado === 'COMPLETADO'">
                <tr class="border-b"><td class="py-2 font-semibold text-gray-500">Km Salida</td>
                  <td class="py-2">{{ modalVer.s.km_salida?.toLocaleString() }}</td></tr>
                <tr class="border-b"><td class="py-2 font-semibold text-gray-500">Km Retorno</td>
                  <td class="py-2">{{ modalVer.s.km_retorno?.toLocaleString() }}</td></tr>
                <tr class="border-b"><td class="py-2 font-semibold text-gray-500">Km Recorridos</td>
                  <td class="py-2 font-semibold">{{ ((modalVer.s.km_retorno || 0) - (modalVer.s.km_salida || 0)).toLocaleString() }} km</td></tr>
                <tr v-if="modalVer.s.hoja_ruta_observacion" class="border-b">
                  <td class="py-2 font-semibold text-gray-500">Obs. Hoja Ruta</td>
                  <td class="py-2">{{ modalVer.s.hoja_ruta_observacion }}</td></tr>
              </template>
            </tbody>
          </table>
        </template>
        <div class="flex justify-end mt-4">
          <button @click="modalVer.show = false"
            class="px-4 py-2 text-sm border rounded-lg text-gray-600 hover:text-gray-800">Cerrar</button>
        </div>
      </div>
    </div>

    <!-- Modal Nueva Solicitud -->
    <div v-if="modalCrear.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Nueva Solicitud de Movilización</h2>
        <div class="space-y-3">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Motivo *</label>
            <textarea v-model="formCrear.motivo" rows="2"
              class="w-full border rounded-lg px-3 py-2 text-sm resize-none"></textarea>
          </div>
          <div class="grid grid-cols-3 gap-3">
            <div class="col-span-1">
              <label class="block text-xs font-semibold text-gray-600 mb-1">Fecha *</label>
              <input v-model="formCrear.fecha_movilizacion" type="date"
                class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Hora salida *</label>
              <input v-model="formCrear.hora_salida" type="time"
                class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Hora retorno *</label>
              <input v-model="formCrear.hora_retorno" type="time"
                class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Lugar de salida</label>
              <input v-model="formCrear.lugar_salida" class="w-full border rounded-lg px-3 py-2 text-sm"
                placeholder="Instalaciones CONSEJO" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Lugar de destino *</label>
              <input v-model="formCrear.lugar_destino" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
          </div>
          <div class="w-28">
            <label class="block text-xs font-semibold text-gray-600 mb-1">N° Personas *</label>
            <input v-model.number="formCrear.num_personas" type="number" min="1"
              class="w-full border rounded-lg px-3 py-2 text-sm" />
          </div>
        </div>
        <p v-if="errorCrear" class="text-red-600 text-sm mt-3">{{ errorCrear }}</p>
        <div class="flex justify-end gap-2 mt-5">
          <button @click="modalCrear.show = false"
            class="px-4 py-2 text-sm text-gray-600 border rounded-lg">Cancelar</button>
          <button @click="guardarCrear" :disabled="guardando"
            class="px-5 py-2 text-sm text-white rounded-lg hover:opacity-90 disabled:opacity-50"
            style="background-color:#1e3a5f;">
            {{ guardando ? 'Enviando...' : 'Solicitar' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Aprobar -->
    <div v-if="modalAprobar.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-1">Aprobar Solicitud #{{ modalAprobar.s?.id }}</h2>
        <p class="text-sm text-gray-500 mb-4">
          {{ modalAprobar.s?.solicitante?.apellido_emp }} · {{ formatFechaCorta(modalAprobar.s?.fecha_movilizacion) }}
          · {{ modalAprobar.s?.hora_salida?.slice(0,5) }}–{{ modalAprobar.s?.hora_retorno?.slice(0,5) }}
        </p>
        <div class="space-y-3">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Vehículo *</label>
            <select v-model="formAprobar.vehiculo_id" class="w-full border rounded-lg px-3 py-2 text-sm">
              <option value="">Seleccione...</option>
              <option v-for="v in vehiculosActivos" :key="v.id" :value="v.id">
                {{ v.placa }} — {{ v.marca }} {{ v.modelo }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Conductor *</label>
            <select v-model="formAprobar.id_emp_conductor" class="w-full border rounded-lg px-3 py-2 text-sm">
              <option value="">Seleccione...</option>
              <option v-for="c in conductores" :key="c.id_emp" :value="c.id_emp">
                {{ c.apellido_emp }} {{ c.nombre_emp }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Observación</label>
            <input v-model="formAprobar.observacion" class="w-full border rounded-lg px-3 py-2 text-sm" />
          </div>
        </div>
        <p v-if="errorAprobar" class="text-red-600 text-sm mt-3">{{ errorAprobar }}</p>
        <div class="flex justify-end gap-2 mt-5">
          <button @click="modalAprobar.show = false"
            class="px-4 py-2 text-sm text-gray-600 border rounded-lg">Cancelar</button>
          <button @click="guardarAprobar" :disabled="guardando"
            class="px-5 py-2 text-sm text-white rounded-lg hover:opacity-90 disabled:opacity-50"
            style="background-color:#1e3a5f;">
            {{ guardando ? 'Aprobando...' : 'Aprobar' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Negar -->
    <div v-if="modalNegar.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Negar Solicitud #{{ modalNegar.id }}</h2>
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Motivo de negación</label>
          <textarea v-model="formNegar.observacion" rows="3"
            class="w-full border rounded-lg px-3 py-2 text-sm resize-none"></textarea>
        </div>
        <div class="flex justify-end gap-2 mt-5">
          <button @click="modalNegar.show = false"
            class="px-4 py-2 text-sm text-gray-600 border rounded-lg">Cancelar</button>
          <button @click="guardarNegar" :disabled="guardando"
            class="px-5 py-2 text-sm text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50">
            {{ guardando ? 'Negando...' : 'Negar' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Hoja de Ruta -->
    <div v-if="modalHojaRuta.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-1">Hoja de Ruta</h2>
        <p class="text-sm text-gray-500 mb-4">Solicitud #{{ modalHojaRuta.s?.id }} — {{ modalHojaRuta.s?.lugar_destino }}</p>
        <div class="space-y-3">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Km Salida *</label>
              <input v-model.number="formHojaRuta.km_salida" type="number" min="0"
                class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Km Retorno *</label>
              <input v-model.number="formHojaRuta.km_retorno" type="number" min="0"
                class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
          </div>
          <div v-if="formHojaRuta.km_retorno > 0 && formHojaRuta.km_salida >= 0"
               class="text-sm text-gray-600 bg-gray-50 rounded-lg px-3 py-2">
            Km recorridos: <strong>{{ (formHojaRuta.km_retorno - formHojaRuta.km_salida).toLocaleString() }} km</strong>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Observaciones</label>
            <textarea v-model="formHojaRuta.hoja_ruta_observacion" rows="2"
              class="w-full border rounded-lg px-3 py-2 text-sm resize-none"></textarea>
          </div>
        </div>
        <p v-if="errorHojaRuta" class="text-red-600 text-sm mt-3">{{ errorHojaRuta }}</p>
        <div class="flex justify-end gap-2 mt-5">
          <button @click="modalHojaRuta.show = false"
            class="px-4 py-2 text-sm text-gray-600 border rounded-lg">Cancelar</button>
          <button @click="guardarHojaRuta" :disabled="guardando"
            class="px-5 py-2 text-sm text-white rounded-lg hover:opacity-90 disabled:opacity-50"
            style="background-color:#1e3a5f;">
            {{ guardando ? 'Guardando...' : 'Completar' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const auth = useAuthStore()
const esTransporte = computed(() => auth.tieneRol('TRANSPORTE'))
const esConductor  = computed(() => auth.tieneRol('CONDUCTOR'))
const puedeCrear   = computed(() =>
  auth.empleado?.puede_solicitar_vehiculo || esTransporte.value
)

const lista      = ref([])
const vehiculosActivos = ref([])
const conductores = ref([])
const pagina     = ref(1)
const porPagina  = ref(10)
const filtroEstado = ref('')
const guardando  = ref(false)

const listaFiltrada = computed(() =>
  filtroEstado.value ? lista.value.filter(s => s.estado === filtroEstado.value) : lista.value
)
const totalPaginas = computed(() => Math.max(1, Math.ceil(listaFiltrada.value.length / porPagina.value)))
const listaPaginada = computed(() => {
  const ini = (pagina.value - 1) * porPagina.value
  return listaFiltrada.value.slice(ini, ini + porPagina.value)
})

const modalVer      = ref({ show: false, s: null })
const modalCrear    = ref({ show: false })
const modalAprobar  = ref({ show: false, s: null })
const modalNegar    = ref({ show: false, id: null })
const modalHojaRuta = ref({ show: false, s: null })
const formCrear     = ref({})
const formAprobar   = ref({})
const formNegar     = ref({})
const formHojaRuta  = ref({})
const errorCrear    = ref('')
const errorAprobar  = ref('')
const errorHojaRuta = ref('')

function estadoBadge(e) {
  if (e === 'PENDIENTE')  return 'bg-yellow-100 text-yellow-700'
  if (e === 'APROBADO')   return 'bg-blue-100 text-blue-700'
  if (e === 'NEGADO')     return 'bg-red-100 text-red-700'
  if (e === 'COMPLETADO') return 'bg-green-100 text-green-700'
  return 'bg-gray-100 text-gray-600'
}
function formatFechaCorta(d) {
  if (!d) return '—'
  return String(d).slice(0, 10).split('-').reverse().join('/')
}

async function cargar() {
  const [r1, r2, r3] = await Promise.all([
    api.get('/transporte/movilizacion'),
    api.get('/transporte/vehiculos'),
    api.get('/transporte/conductores'),
  ])
  lista.value = r1.data
  vehiculosActivos.value = r2.data.filter(v => v.estado === 'ACTIVO')
  conductores.value = r3.data
}

function abrirVer(s) { modalVer.value = { show: true, s } }

function abrirCrear() {
  const hoy = new Date().toISOString().slice(0, 10)
  formCrear.value = { motivo: '', fecha_movilizacion: hoy, hora_salida: '08:00',
    hora_retorno: '17:00', lugar_salida: '', lugar_destino: '', num_personas: 1 }
  errorCrear.value = ''
  modalCrear.value = { show: true }
}

function abrirAprobar(s) {
  formAprobar.value = { vehiculo_id: '', id_emp_conductor: '', observacion: '' }
  errorAprobar.value = ''
  modalAprobar.value = { show: true, s }
}

function abrirNegar(s) {
  formNegar.value = { observacion: '' }
  modalNegar.value = { show: true, id: s.id }
}

function abrirHojaRuta(s) {
  formHojaRuta.value = {
    km_salida: s.vehiculo?.kilometraje_actual || 0,
    km_retorno: 0,
    hoja_ruta_observacion: '',
  }
  errorHojaRuta.value = ''
  modalHojaRuta.value = { show: true, s }
}

async function guardarCrear() {
  errorCrear.value = ''
  guardando.value = true
  try {
    await api.post('/transporte/movilizacion', formCrear.value)
    modalCrear.value.show = false
    await cargar()
  } catch (e) {
    errorCrear.value = e.response?.data?.message || 'Error al enviar'
  } finally { guardando.value = false }
}

async function guardarAprobar() {
  errorAprobar.value = ''
  guardando.value = true
  try {
    await api.put(`/transporte/movilizacion/${modalAprobar.value.s.id}`,
      { accion: 'aprobar', ...formAprobar.value })
    modalAprobar.value.show = false
    await cargar()
  } catch (e) {
    errorAprobar.value = e.response?.data?.message || 'Error al aprobar'
  } finally { guardando.value = false }
}

async function guardarNegar() {
  guardando.value = true
  try {
    await api.put(`/transporte/movilizacion/${modalNegar.value.id}`,
      { accion: 'negar', ...formNegar.value })
    modalNegar.value.show = false
    await cargar()
  } catch {} finally { guardando.value = false }
}

async function guardarHojaRuta() {
  errorHojaRuta.value = ''
  guardando.value = true
  try {
    await api.put(`/transporte/movilizacion/${modalHojaRuta.value.s.id}`,
      { accion: 'hoja_ruta', ...formHojaRuta.value })
    modalHojaRuta.value.show = false
    await cargar()
  } catch (e) {
    errorHojaRuta.value = e.response?.data?.message || 'Error al completar'
  } finally { guardando.value = false }
}

async function verPdf(id) {
  try {
    const { data } = await api.get(`/transporte/movilizacion/${id}/pdf`, { responseType: 'blob' })
    const url  = window.URL.createObjectURL(new Blob([data], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href  = url
    link.setAttribute('download', `orden_movilizacion_${id}.pdf`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (e) {
    alert('Error al generar el PDF: ' + (e.response?.data?.message || e.message))
  }
}

onMounted(cargar)
</script>
