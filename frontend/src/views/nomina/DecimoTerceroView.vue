<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Décimo Tercer Sueldo</h1>
    </div>

    <!-- Controles período -->
    <div class="bg-white rounded-xl shadow p-5 mb-5">
      <div class="flex flex-wrap gap-3 items-end">
        <div>
          <label class="block text-xs text-gray-600 mb-1">Mes</label>
          <select v-model.number="form.mes" class="border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 outline-none">
            <option v-for="m in meses" :key="m.v" :value="m.v">{{ m.l }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-600 mb-1">Año</label>
          <select v-model.number="form.anio" class="border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-300 outline-none">
            <option v-for="a in anios" :key="a" :value="a">{{ a }}</option>
          </select>
        </div>
        <div class="relative">
          <label class="block text-xs text-gray-600 mb-1">Empleado (opcional)</label>
          <input v-model="busquedaEmp" @input="filtrarEmpleados" @keydown.escape="sugerenciasEmp = []"
            type="text" placeholder="Todos o buscar nombre..."
            class="border rounded-lg px-3 py-2 text-sm w-52 focus:ring-2 focus:ring-blue-300 outline-none" />
          <button v-if="form.id_emp" @click="limpiarEmp" title="Quitar filtro"
            class="absolute right-2 top-7 text-gray-400 hover:text-gray-600 text-xs">✕</button>
          <div v-if="sugerenciasEmp.length"
            class="absolute z-20 bg-white border rounded-lg shadow-lg w-64 mt-1 max-h-48 overflow-y-auto">
            <div v-for="e in sugerenciasEmp" :key="e.id_emp" @click="seleccionarEmp(e)"
              class="px-3 py-2 text-sm hover:bg-gray-100 cursor-pointer border-b last:border-0">
              <div class="font-medium">{{ e.apellido_emp }} {{ e.nombre_emp }}</div>
              <div class="text-xs text-gray-400">{{ e.identificacion }}</div>
            </div>
          </div>
        </div>
        <button @click="cargar" :disabled="cargando"
          class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 disabled:opacity-50">
          {{ cargando ? 'Cargando...' : 'Buscar' }}
        </button>
        <button @click="calcular" :disabled="calculando || estadoPeriodo === 'CERRADO'"
          class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700 disabled:opacity-50">
          {{ calculando ? 'Calculando...' : (registros.length ? 'Recalcular' : 'Calcular') }}
        </button>
        <button v-if="estadoPeriodo === 'BORRADOR'" @click="cerrar" :disabled="cerrando"
          class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 disabled:opacity-50">
          {{ cerrando ? 'Cerrando...' : 'Cerrar Período' }}
        </button>
        <button v-if="registros.length" @click="descargarPdf"
          class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700">
          Generar PDF
        </button>

        <!-- Badge estado -->
        <span v-if="estadoPeriodo" class="ml-2 px-3 py-1 rounded-full text-xs font-bold"
          :class="estadoPeriodo === 'CERRADO' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'">
          {{ estadoPeriodo }}
        </span>
        <span v-else-if="buscado && !registros.length"
          class="ml-2 px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-600">
          Sin datos
        </span>
      </div>
      <p v-if="error" class="text-red-600 text-sm mt-3">{{ error }}</p>
    </div>

    <!-- Tabla de resultados -->
    <div v-if="registros.length" class="bg-white rounded-xl shadow overflow-hidden">
      <div class="px-5 py-3 border-b flex justify-between items-center text-sm text-gray-600">
        <span>{{ registros.length }} servidores</span>
        <span class="font-semibold text-gray-800">Total: ${{ fmt(totalValor) }}</span>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">N°</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Apellidos y Nombres</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Cédula</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Departamento</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Sueldo</th>
              <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Días</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Valor</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="(r, i) in registrosPaginados" :key="r.id" class="hover:bg-gray-50">
              <td class="px-4 py-2 text-gray-500">{{ (pagina - 1) * POR_PAGINA + i + 1 }}</td>
              <td class="px-4 py-2 font-medium text-gray-800">
                {{ nombreCompleto(r.empleado) }}
              </td>
              <td class="px-4 py-2 text-gray-600 font-mono">{{ r.empleado?.identificacion }}</td>
              <td class="px-4 py-2 text-gray-600">{{ r.empleado?.departamento?.nombre_depto }}</td>
              <td class="px-4 py-2 text-right font-mono">${{ fmt(r.sueldo_base) }}</td>
              <td class="px-4 py-2 text-center text-gray-600">{{ r.dias }}</td>
              <td class="px-4 py-2 text-right font-mono font-semibold text-blue-700">${{ fmt(r.valor) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="bg-gray-100 font-bold">
              <td colspan="5" class="px-4 py-2 text-right text-gray-700">TOTAL</td>
              <td></td>
              <td class="px-4 py-2 text-right font-mono text-blue-800">${{ fmt(totalValor) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
      <!-- Paginador -->
      <div v-if="totalPaginas > 1" class="px-5 py-3 border-t flex items-center justify-between text-sm text-gray-600">
        <span>Página {{ pagina }} de {{ totalPaginas }} ({{ registros.length }} servidores)</span>
        <div class="flex gap-1">
          <button @click="pagina--" :disabled="pagina === 1"
            class="px-3 py-1 border rounded text-sm hover:bg-gray-50 disabled:opacity-40">‹ Anterior</button>
          <button v-for="p in totalPaginas" :key="p" @click="pagina = p"
            class="px-3 py-1 border rounded text-sm"
            :class="p === pagina ? 'bg-blue-600 text-white border-blue-600' : 'hover:bg-gray-50'">
            {{ p }}
          </button>
          <button @click="pagina++" :disabled="pagina === totalPaginas"
            class="px-3 py-1 border rounded text-sm hover:bg-gray-50 disabled:opacity-40">Siguiente ›</button>
        </div>
      </div>
    </div>

    <!-- Aviso empleado acumula -->
    <div v-if="buscado && !registros.length && form.id_emp"
      class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-800">
      Este empleado <strong>acumula el Décimo Tercero</strong>, no recibe pago mensual. El valor se paga en diciembre.
    </div>

    <!-- Modal confirmación recalcular -->
    <div v-if="modalConfirm" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full mx-4 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4" style="background-color:#0b5447;">
          <h3 class="text-lg font-bold text-white">¿Recalcular período?</h3>
          <button type="button" @click="modalConfirm = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-6">
          <p class="text-sm text-gray-600 mb-5">
            Ya existen datos en BORRADOR para <strong>{{ meses[form.mes - 1]?.l }} {{ form.anio }}</strong>.
            Al recalcular se eliminarán los registros actuales y se generarán nuevos.
          </p>
          <div class="flex gap-3 justify-end">
            <button @click="modalConfirm = false" class="px-4 py-2 border rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancelar</button>
            <button @click="confirmarCalculo" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-semibold hover:bg-indigo-700">
              Sí, recalcular
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal confirmación cerrar -->
    <div v-if="modalCerrar" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white rounded-2xl shadow-xl max-w-sm w-full mx-4 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4" style="background-color:#0b5447;">
          <h3 class="text-lg font-bold text-white">¿Cerrar período?</h3>
          <button type="button" @click="modalCerrar = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-6">
          <p class="text-sm text-gray-600 mb-5">
            Al cerrar el período de <strong>{{ meses[form.mes - 1]?.l }} {{ form.anio }}</strong>
            no se podrá recalcular. Esta acción queda registrada en auditoría.
          </p>
          <div class="flex gap-3 justify-end">
            <button @click="modalCerrar = false" class="px-4 py-2 border rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancelar</button>
            <button @click="confirmarCierre" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700">
              Sí, cerrar
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import api from '@/services/api'

const meses = [
  { v: 1, l: 'Enero' }, { v: 2, l: 'Febrero' }, { v: 3, l: 'Marzo' },
  { v: 4, l: 'Abril' }, { v: 5, l: 'Mayo' }, { v: 6, l: 'Junio' },
  { v: 7, l: 'Julio' }, { v: 8, l: 'Agosto' }, { v: 9, l: 'Septiembre' },
  { v: 10, l: 'Octubre' }, { v: 11, l: 'Noviembre' }, { v: 12, l: 'Diciembre' },
]

const anioActual = new Date().getFullYear()
const anios = Array.from({ length: 5 }, (_, i) => anioActual - i)

const form = ref({ mes: new Date().getMonth() + 1, anio: anioActual, id_emp: null })
const registros     = ref([])
const estadoPeriodo = ref(null)
const totalValor    = ref(0)
const cargando      = ref(false)
const calculando    = ref(false)
const cerrando      = ref(false)
const buscado       = ref(false)
const error         = ref('')
const modalConfirm  = ref(false)
const modalCerrar   = ref(false)

const busquedaEmp   = ref('')
const sugerenciasEmp = ref([])
let todosEmpleados  = []

const POR_PAGINA = 20
const pagina = ref(1)
const registrosPaginados = computed(() => {
  const ini = (pagina.value - 1) * POR_PAGINA
  return registros.value.slice(ini, ini + POR_PAGINA)
})
const totalPaginas = computed(() => Math.ceil(registros.value.length / POR_PAGINA))

const fmt = (v) => parseFloat(v || 0).toFixed(2)
const nombreCompleto = (e) => e ? `${e.apellido_emp ?? ''} ${e.nombre_emp ?? ''}`.trim().toUpperCase() : ''

async function cargarListaEmpleados() {
  if (todosEmpleados.length) return
  try {
    const { data } = await api.get('/empleados', { params: { per_page: 500, estado: 'ACTIVO' } })
    todosEmpleados = data.data ?? data
  } catch {}
}

function filtrarEmpleados() {
  sugerenciasEmp.value = []
  form.value.id_emp = null
  if (!busquedaEmp.value.trim()) return
  cargarListaEmpleados().then(() => {
    const q = busquedaEmp.value.toLowerCase()
    sugerenciasEmp.value = todosEmpleados.filter(e =>
      `${e.nombre_emp} ${e.apellido_emp} ${e.identificacion}`.toLowerCase().includes(q)
    ).slice(0, 8)
  })
}

function seleccionarEmp(e) {
  form.value.id_emp = e.id_emp
  busquedaEmp.value = `${e.apellido_emp} ${e.nombre_emp}`
  sugerenciasEmp.value = []
}

function limpiarEmp() {
  form.value.id_emp = null
  busquedaEmp.value = ''
}

async function cargar() {
  cargando.value = true
  error.value = ''
  try {
    const params = { anio: form.value.anio, mes: form.value.mes }
    if (form.value.id_emp) params.id_emp = form.value.id_emp
    const { data } = await api.get('/nomina/decimo-tercero', { params })
    registros.value     = data.registros
    estadoPeriodo.value = data.estado_periodo
    totalValor.value    = data.total_valor
    pagina.value = 1
    buscado.value = true
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Error al cargar los datos.'
  } finally {
    cargando.value = false
  }
}

function calcular() {
  if (registros.value.length && estadoPeriodo.value === 'BORRADOR') {
    modalConfirm.value = true
    return
  }
  confirmarCalculo()
}

async function confirmarCalculo() {
  modalConfirm.value = false
  calculando.value = true
  error.value = ''
  try {
    const { data } = await api.post('/nomina/decimo-tercero/calcular', { anio: form.value.anio, mes: form.value.mes })
    registros.value    = data.registros
    estadoPeriodo.value = data.estado_periodo
    totalValor.value   = data.total_valor
    pagina.value = 1
    buscado.value = true
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Error al calcular.'
  } finally {
    calculando.value = false
  }
}

function cerrar() {
  modalCerrar.value = true
}

async function confirmarCierre() {
  modalCerrar.value = false
  cerrando.value = true
  error.value = ''
  try {
    const { data } = await api.post('/nomina/decimo-tercero/cerrar', { anio: form.value.anio, mes: form.value.mes })
    registros.value    = data.registros
    estadoPeriodo.value = data.estado_periodo
    totalValor.value   = data.total_valor
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Error al cerrar el período.'
  } finally {
    cerrando.value = false
  }
}

async function descargarPdf() {
  try {
    const resp = await api.get('/nomina/decimo-tercero/pdf', {
      params: { anio: form.value.anio, mes: form.value.mes },
      responseType: 'blob',
    })
    const url = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
    const a = document.createElement('a')
    a.href = url
    a.download = `decimo_tercero_${form.value.anio}_${String(form.value.mes).padStart(2, '0')}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    error.value = 'Error al generar el PDF.'
  }
}
</script>
