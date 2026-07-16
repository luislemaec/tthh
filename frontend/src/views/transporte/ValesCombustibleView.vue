<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Vales de Combustible</h1>
      <button @click="abrirCrear" class="text-white px-4 py-2 rounded-lg text-sm hover:opacity-90"
        style="background-color:#1e3a5f;">
        + Nuevo vale
      </button>
    </div>

    <!-- Lista -->
    <div class="space-y-2">
      <div v-if="!listaPaginada.length" class="bg-white rounded-xl shadow p-8 text-center text-gray-400">
        Sin vales registrados
      </div>

      <div v-for="v in listaPaginada" :key="v.id"
           class="bg-white rounded-xl shadow px-5 py-3 flex justify-between items-center gap-3">
        <div class="min-w-0 flex-1">
          <p class="font-semibold text-gray-800">
            Vale N° {{ String(v.numero).padStart(4, '0') }}
            <span class="text-gray-400 font-normal text-xs ml-2">{{ formatFecha(v.fecha) }}</span>
          </p>
          <p class="text-xs text-gray-500 mt-0.5">
            {{ v.vehiculo?.placa }} · {{ v.vehiculo?.marca }} {{ v.vehiculo?.modelo }}
            · {{ v.gasolinera }}
          </p>
          <p class="text-xs text-gray-500">
            <span v-if="v.glns_extra">Extra: {{ v.glns_extra }} Glns</span>
            <span v-if="v.glns_super"> &nbsp;Super: {{ v.glns_super }} Glns</span>
            <span v-if="v.glns_diesel"> &nbsp;Diesel: {{ v.glns_diesel }} Glns</span>
          </p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
          <span v-if="v.estado === 'ANULADO'"
            class="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">ANULADO</span>
          <button v-if="v.estado !== 'ANULADO'" @click="verPdf(v.id)"
            class="text-xs text-red-600 hover:text-red-800 font-medium border border-red-200 px-3 py-1 rounded-lg">
            PDF
          </button>
          <button v-if="v.estado === 'EMITIDO'" @click="anularVale(v)"
            class="text-xs text-gray-500 hover:text-red-700 font-medium border border-gray-300 hover:border-red-300 px-3 py-1 rounded-lg">
            Anular
          </button>
        </div>
      </div>
    </div>

    <!-- Paginador -->
    <div v-if="lista.length > 0" class="flex items-center justify-between mt-4">
      <div class="flex items-center gap-2 text-sm text-gray-600">
        <span>Mostrar</span>
        <select v-model="porPagina" @change="pagina = 1" class="border rounded px-2 py-1 text-sm">
          <option :value="10">10</option>
          <option :value="25">25</option>
          <option :value="50">50</option>
        </select>
        <span>por página · {{ lista.length }} total</span>
      </div>
      <div class="flex gap-1">
        <button @click="pagina--" :disabled="pagina === 1"
          class="px-3 py-1 text-sm border rounded disabled:opacity-40">‹</button>
        <span class="px-3 py-1 text-sm text-gray-600">{{ pagina }} / {{ totalPaginas }}</span>
        <button @click="pagina++" :disabled="pagina === totalPaginas"
          class="px-3 py-1 text-sm border rounded disabled:opacity-40">›</button>
      </div>
    </div>

    <!-- Modal Nuevo Vale -->
    <div v-if="modal.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
        <div class="px-6 py-4 flex-shrink-0" style="background-color:#1e3a5f;">
          <h2 class="text-lg font-bold text-white">Nuevo Vale de Combustible</h2>
        </div>
        <div class="p-6 overflow-y-auto">
        <div class="space-y-3">
          <!-- Gasolinera -->
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Gasolinera / Sr.(es) *</label>
            <input v-model="form.gasolinera" v-uppercase class="w-full border rounded-lg px-3 py-2 text-sm"
              placeholder="Nombre de la gasolinera" />
          </div>

          <!-- Conductor (solo lectura) -->
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Conductor</label>
            <input :value="nombreConductor" disabled
              class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-500" />
          </div>

          <!-- Vehículo -->
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Vehículo *</label>
            <select v-model="form.vehiculo_id" class="w-full border rounded-lg px-3 py-2 text-sm">
              <option value="">Seleccione...</option>
              <option v-for="veh in vehiculos" :key="veh.id" :value="veh.id">
                {{ veh.placa }} — {{ veh.marca }} {{ veh.modelo }}
              </option>
            </select>
          </div>

          <!-- Km -->
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Kilometraje *</label>
            <input v-model.number="form.kilometraje" type="number" min="0"
              class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Km actuales" />
          </div>

          <!-- Fechas -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Fecha comprobante</label>
              <input v-model="form.fecha_comprobante" type="date" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Fecha elaboración *</label>
              <input v-model="form.fecha" type="date" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
          </div>

          <!-- Tabla combustible -->
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-2">Combustible</label>
            <table class="w-full text-sm border border-gray-200 rounded-lg overflow-hidden">
              <thead class="bg-gray-50">
                <tr>
                  <th class="text-left px-3 py-2 text-xs font-semibold text-gray-600 w-2/5">Descripción</th>
                  <th class="text-center px-2 py-2 text-xs font-semibold text-gray-600">Glns.</th>
                  <th class="text-center px-2 py-2 text-xs font-semibold text-gray-600">P/U</th>
                  <th class="text-center px-2 py-2 text-xs font-semibold text-gray-600">Total</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="tipo in combustibles" :key="tipo.key" class="border-t border-gray-100">
                  <td class="px-3 py-1.5 text-gray-700">{{ tipo.label }}</td>
                  <td class="px-2 py-1">
                    <input v-model.number="form[tipo.key + '_glns']" type="number" min="0" step="0.01"
                      @input="calcular(tipo.key)"
                      class="w-full border rounded px-2 py-1 text-xs text-center" placeholder="0.00" />
                  </td>
                  <td class="px-2 py-1">
                    <input v-model.number="form[tipo.key + '_pu']" type="number" min="0" step="0.0001"
                      @input="calcular(tipo.key)"
                      class="w-full border rounded px-2 py-1 text-xs text-center" placeholder="0.0000" />
                  </td>
                  <td class="px-2 py-1.5 text-center text-xs font-semibold text-gray-700">
                    {{ form[tipo.key + '_valor'] ? '$' + form[tipo.key + '_valor'].toFixed(2) : '—' }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <p v-if="error" class="text-red-600 text-sm mt-3">{{ error }}</p>

        <div class="flex justify-end gap-2 mt-5">
          <button @click="modal.show = false"
            class="px-4 py-2 text-sm text-gray-600 border rounded-lg">Cancelar</button>
          <button @click="guardar" :disabled="guardando"
            class="px-5 py-2 text-sm text-white rounded-lg hover:opacity-90 disabled:opacity-50"
            style="background-color:#1e3a5f;">
            {{ guardando ? 'Guardando...' : 'Guardar y generar PDF' }}
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

const auth     = useAuthStore()
const lista    = ref([])
const vehiculos = ref([])
const pagina   = ref(1)
const porPagina = ref(10)
const guardando = ref(false)
const error    = ref('')
const modal    = ref({ show: false })
const form     = ref({})

const combustibles = [
  { key: 'extra',  label: 'Gasolina Extra' },
  { key: 'super',  label: 'Gasolina Super' },
  { key: 'diesel', label: 'Diesel' },
]

const nombreConductor = computed(() => {
  const e = auth.empleado
  return e ? `${e.apellido} ${e.nombre}` : ''
})

const totalPaginas = computed(() => Math.max(1, Math.ceil(lista.value.length / porPagina.value)))
const listaPaginada = computed(() => {
  const ini = (pagina.value - 1) * porPagina.value
  return lista.value.slice(ini, ini + porPagina.value)
})

function formatFecha(d) {
  if (!d) return '—'
  return d.slice(0, 10).split('-').reverse().join('/')
}

function calcular(key) {
  const glns = form.value[key + '_glns'] || 0
  const pu   = form.value[key + '_pu']   || 0
  form.value[key + '_valor'] = glns && pu ? Math.round(glns * pu * 100) / 100 : null
}

async function cargar() {
  const [r1, r2] = await Promise.all([
    api.get('/transporte/vales-combustible'),
    api.get('/transporte/vehiculos'),
  ])
  lista.value    = r1.data
  vehiculos.value = r2.data.filter(v => v.estado === 'ACTIVO')
}

function abrirCrear() {
  const hoy = new Date().toISOString().slice(0, 10)
  form.value = {
    gasolinera: '', vehiculo_id: '', kilometraje: null, fecha: hoy, fecha_comprobante: '',
    extra_glns: null, extra_pu: null, extra_valor: null,
    super_glns: null, super_pu: null, super_valor: null,
    diesel_glns: null, diesel_pu: null, diesel_valor: null,
  }
  error.value = ''
  modal.value = { show: true }
}

async function guardar() {
  error.value = ''
  guardando.value = true
  try {
    const payload = {
      gasolinera:        form.value.gasolinera,
      vehiculo_id:       form.value.vehiculo_id,
      kilometraje:       form.value.kilometraje,
      fecha:             form.value.fecha,
      fecha_comprobante: form.value.fecha_comprobante || null,
      glns_extra:   form.value.extra_glns  || null,
      pu_extra:     form.value.extra_pu    || null,
      glns_super:   form.value.super_glns  || null,
      pu_super:     form.value.super_pu    || null,
      glns_diesel:  form.value.diesel_glns || null,
      pu_diesel:    form.value.diesel_pu   || null,
    }
    const { data } = await api.post('/transporte/vales-combustible', payload)
    modal.value.show = false
    await cargar()
    await verPdf(data.id)
  } catch (e) {
    error.value = e.response?.data?.message || Object.values(e.response?.data?.errors || {})[0]?.[0] || 'Error al guardar'
  } finally {
    guardando.value = false
  }
}

async function anularVale(v) {
  if (!confirm(`¿Anular el vale N° ${String(v.numero).padStart(4, '0')}? Esta acción no se puede deshacer.`)) return
  try {
    await api.patch(`/transporte/vales-combustible/${v.id}/anular`)
    await cargar()
  } catch (e) {
    alert('Error al anular el vale')
  }
}

async function verPdf(id) {
  try {
    const { data } = await api.get(`/transporte/vales-combustible/${id}/pdf`, { responseType: 'blob' })
    const url  = window.URL.createObjectURL(new Blob([data], { type: 'application/pdf' }))
    window.open(url, '_blank')
    setTimeout(() => window.URL.revokeObjectURL(url), 10000)
  } catch (e) {
    alert('Error al generar el PDF')
  }
}

onMounted(cargar)
</script>
