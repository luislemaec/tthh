<template>
  <div class="space-y-5">
    <h1 class="text-2xl font-bold text-gray-800">Reporte de Saldo de Vacaciones</h1>

    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow p-4 flex flex-wrap gap-3 items-end">
      <div>
        <label class="block text-xs text-gray-500 mb-1">Departamento</label>
        <select v-model="filtroDep" @change="pagina = 1"
          class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186] w-52">
          <option value="">Todos</option>
          <option v-for="d in departamentos" :key="d.id_depto" :value="d.id_depto">{{ d.nombre_depto }}</option>
        </select>
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">Buscar empleado</label>
        <input v-model="buscar" @input="pagina = 1" placeholder="Nombre o cédula"
          class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186] w-52" />
      </div>
      <button @click="cargar" :disabled="cargando"
        class="bg-[#0b5447] text-white px-5 py-2 rounded-lg text-sm hover:bg-[#00372e] disabled:opacity-50">
        {{ cargando ? 'Cargando...' : 'Consultar' }}
      </button>
      <button v-if="lista.length" @click="descargarPdf" :disabled="generandoPdf"
        class="border border-red-600 text-red-600 px-5 py-2 rounded-lg text-sm hover:bg-red-50 disabled:opacity-50">
        {{ generandoPdf ? 'Generando...' : 'Descargar PDF' }}
      </button>
      <button @click="abrirCargaSaldos"
        class="border border-[#0b5447] text-[#0b5447] px-5 py-2 rounded-lg text-sm hover:bg-[#f0f9f7]">
        Cargar Saldos
      </button>
      <!-- Toggle vista -->
      <div class="ml-auto flex items-center gap-2">
        <label class="text-xs text-gray-500">Vista:</label>
        <label class="flex items-center gap-1 cursor-pointer">
          <input type="radio" v-model="vista" value="resumido" class="accent-[#0b5447]" />
          <span class="text-sm">Resumido</span>
        </label>
        <label class="flex items-center gap-1 cursor-pointer">
          <input type="radio" v-model="vista" value="detallado" class="accent-[#0b5447]" />
          <span class="text-sm">Detallado</span>
        </label>
      </div>
    </div>

    <!-- Tabla -->
    <div v-if="lista.length" class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-[#0b5447] text-white">
          <tr>
            <th class="px-4 py-2 text-left font-medium w-6" v-if="vista === 'detallado'"></th>
            <th class="px-4 py-2 text-left font-medium">Empleado</th>
            <th class="px-4 py-2 text-left font-medium">Departamento</th>
            <th class="px-4 py-2 text-center font-medium">Contrato</th>
            <th class="px-4 py-2 text-center font-medium">Días tomados</th>
            <th class="px-4 py-2 text-center font-medium">Saldo disponible</th>
          </tr>
        </thead>
        <tbody>
          <template v-for="emp in listaPaginada" :key="emp.id_emp">
            <!-- Fila resumen del empleado -->
            <tr class="border-b hover:bg-gray-50"
              :class="vista === 'detallado' ? 'cursor-pointer' : ''"
              @click="vista === 'detallado' && toggleDetalle(emp.id_emp)">
              <td class="px-4 py-2 text-center text-gray-400 text-xs" v-if="vista === 'detallado'">
                {{ expandido === emp.id_emp ? '▲' : '▼' }}
              </td>
              <td class="px-4 py-2 font-medium text-gray-800">{{ emp.nombre_completo }}</td>
              <td class="px-4 py-2 text-gray-600 text-xs">{{ emp.departamento }}</td>
              <td class="px-4 py-2 text-center text-xs text-gray-500">{{ emp.tipo_contrato }}</td>
              <td class="px-4 py-2 text-center text-gray-700">{{ emp.tomados }}</td>
              <td class="px-4 py-2 text-center">
                <span class="font-bold text-lg"
                  :class="emp.saldo_actual <= 0 ? 'text-red-600' : emp.saldo_actual < 5 ? 'text-amber-600' : 'text-green-700'">
                  {{ emp.saldo_actual }}
                </span>
                <span class="text-xs text-gray-400 ml-1">días</span>
              </td>
            </tr>

            <!-- Kardex expandido (solo modo detallado) -->
            <template v-if="vista === 'detallado' && expandido === emp.id_emp">
              <tr v-if="cargandoKardex" class="bg-gray-50">
                <td colspan="6" class="px-8 py-3 text-xs text-gray-400 italic">Cargando detalle...</td>
              </tr>
              <template v-else-if="kardex[emp.id_emp]">
                <tr class="bg-gray-50 border-b">
                  <td></td>
                  <td colspan="5" class="px-4 py-1">
                    <table class="w-full text-xs border border-gray-200 rounded-lg overflow-hidden">
                      <thead>
                        <tr class="bg-gray-200 text-gray-600">
                          <th class="px-3 py-1.5 text-left font-semibold w-24">Fecha</th>
                          <th class="px-3 py-1.5 text-left font-semibold">Concepto</th>
                          <th class="px-3 py-1.5 text-right font-semibold w-20">Entrada</th>
                          <th class="px-3 py-1.5 text-right font-semibold w-20">Salida</th>
                          <th class="px-3 py-1.5 text-right font-semibold w-20">Saldo</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr v-for="(m, i) in kardex[emp.id_emp]" :key="i"
                          :class="rowClass(m.tipo)">
                          <td class="px-3 py-1 text-gray-500">{{ m.fecha ? fmtFecha(m.fecha) : '—' }}</td>
                          <td class="px-3 py-1">{{ m.descripcion }}</td>
                          <td class="px-3 py-1 text-right text-green-700 font-medium">
                            {{ m.entrada != null ? m.entrada : '—' }}
                          </td>
                          <td class="px-3 py-1 text-right text-red-600 font-medium">
                            {{ m.salida != null ? m.salida : '—' }}
                          </td>
                          <td class="px-3 py-1 text-right font-bold"
                            :class="m.tipo === 'TOTAL' ? 'text-[#0b5447]' : 'text-gray-700'">
                            {{ m.saldo }}
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </td>
                </tr>
              </template>
            </template>
          </template>
        </tbody>
      </table>

      <!-- Paginador -->
      <div class="flex items-center justify-between px-4 py-3 border-t bg-gray-50">
        <div class="flex items-center gap-2 text-sm text-gray-600">
          <span>Mostrar</span>
          <select v-model="porPagina" @change="pagina = 1" class="border rounded px-2 py-1 text-sm">
            <option :value="10">10</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
          </select>
          <span>por página · {{ listaFiltrada.length }} empleados</span>
        </div>
        <div class="flex gap-1">
          <button @click="pagina--" :disabled="pagina === 1"
            class="px-3 py-1 text-sm border rounded disabled:opacity-40">‹</button>
          <span class="px-3 py-1 text-sm text-gray-600">{{ pagina }} / {{ totalPaginas }}</span>
          <button @click="pagina++" :disabled="pagina === totalPaginas"
            class="px-3 py-1 text-sm border rounded disabled:opacity-40">›</button>
        </div>
      </div>
    </div>

    <div v-else-if="consultado && !cargando"
      class="bg-white rounded-xl shadow p-8 text-center text-gray-400 text-sm">
      Sin resultados para los filtros aplicados.
    </div>

    <!-- Modal Cargar Saldos -->
    <div v-if="modalCarga" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between px-6 py-4 border-b">
          <h2 class="text-lg font-bold text-gray-800">Cargar Saldos de Vacaciones</h2>
          <button @click="modalCarga = false" class="text-gray-400 hover:text-gray-600 text-xl">✕</button>
        </div>

        <div class="p-6 space-y-4 overflow-y-auto flex-1">
          <!-- Fecha de corte -->
          <div>
            <label class="block text-xs text-gray-500 mb-1">Nueva fecha de corte <span class="text-red-500">*</span></label>
            <input type="date" v-model="cargaFecha"
              class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186] w-52" />
          </div>

          <!-- Instrucciones + plantilla -->
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-xs text-blue-700 space-y-1">
            <p class="font-semibold">Formato del archivo CSV:</p>
            <p>Columnas requeridas: <code class="bg-blue-100 px-1 rounded">cedula</code> y <code class="bg-blue-100 px-1 rounded">saldo</code></p>
            <p>Ejemplo: <code class="bg-blue-100 px-1 rounded">1234567890,25.50</code></p>
            <p class="text-amber-700 font-medium">Al confirmar: se resetea total_dias_tomados a 0 para todos los empleados del archivo y se actualiza la fecha de corte global.</p>
          </div>

          <div class="flex gap-3 items-center">
            <button @click="descargarPlantilla"
              class="border border-gray-400 text-gray-600 px-4 py-2 rounded-lg text-sm hover:bg-gray-50">
              Descargar plantilla
            </button>
            <label class="bg-[#0b5447] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#00372e] cursor-pointer">
              Seleccionar CSV
              <input type="file" accept=".csv,.txt" class="hidden" @change="parsearCsv" />
            </label>
            <span v-if="cargaFilas.length" class="text-xs text-gray-500">{{ cargaFilas.length }} empleados cargados</span>
          </div>

          <!-- Preview -->
          <div v-if="cargaFilas.length" class="border rounded-lg overflow-hidden">
            <table class="w-full text-xs">
              <thead class="bg-gray-100">
                <tr>
                  <th class="px-3 py-2 text-left">#</th>
                  <th class="px-3 py-2 text-left">Cédula</th>
                  <th class="px-3 py-2 text-right">Saldo (días)</th>
                  <th class="px-3 py-2 text-center">Estado</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(f, i) in cargaFilas.slice(0, 10)" :key="i" class="border-t">
                  <td class="px-3 py-1 text-gray-400">{{ i + 1 }}</td>
                  <td class="px-3 py-1">{{ f.cedula }}</td>
                  <td class="px-3 py-1 text-right font-medium">{{ f.saldo }}</td>
                  <td class="px-3 py-1 text-center">
                    <span v-if="f.error" class="text-red-500">{{ f.error }}</span>
                    <span v-else class="text-green-600">OK</span>
                  </td>
                </tr>
              </tbody>
            </table>
            <p v-if="cargaFilas.length > 10" class="text-xs text-gray-400 px-3 py-2 border-t">
              ... y {{ cargaFilas.length - 10 }} más
            </p>
          </div>

          <!-- Resultado post-carga -->
          <div v-if="cargaResultado" class="rounded-lg p-3 text-sm space-y-1"
            :class="cargaResultado.error ? 'bg-red-50 text-red-700' : 'bg-green-50 text-green-700'">
            <p v-if="!cargaResultado.error">
              <strong>{{ cargaResultado.actualizados }}</strong> empleados actualizados correctamente.
            </p>
            <p v-if="cargaResultado.no_encontrados?.length">
              No encontrados: {{ cargaResultado.no_encontrados.join(', ') }}
            </p>
            <p v-if="cargaResultado.error">{{ cargaResultado.error }}</p>
          </div>
        </div>

        <div class="px-6 py-4 border-t flex justify-end gap-3">
          <button @click="modalCarga = false" class="border px-4 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50">
            Cerrar
          </button>
          <button @click="confirmarCarga" :disabled="!cargaFecha || !cargaFilasValidas.length || cargando2"
            class="bg-[#0b5447] text-white px-5 py-2 rounded-lg text-sm hover:bg-[#00372e] disabled:opacity-50">
            {{ cargando2 ? 'Procesando...' : `Confirmar carga (${cargaFilasValidas.length})` }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

const departamentos  = ref([])
const lista          = ref([])
const kardex         = ref({})
const filtroDep      = ref('')
const buscar         = ref('')
const vista          = ref('resumido')
const cargando       = ref(false)
const cargandoKardex = ref(false)
const consultado     = ref(false)
const pagina         = ref(1)
const porPagina      = ref(10)
const expandido      = ref(null)
const generandoPdf   = ref(false)

// Carga masiva de saldos
const modalCarga     = ref(false)
const cargaFecha     = ref('')
const cargaFilas     = ref([])
const cargaResultado = ref(null)
const cargando2      = ref(false)

const cargaFilasValidas = computed(() => cargaFilas.value.filter(f => !f.error))

onMounted(async () => {
  const { data } = await api.get('/departamentos')
  departamentos.value = data
})

const listaFiltrada = computed(() => {
  if (!buscar.value) return lista.value
  const q = buscar.value.toLowerCase()
  return lista.value.filter(e =>
    e.nombre_completo.toLowerCase().includes(q)
  )
})

const totalPaginas = computed(() => Math.max(1, Math.ceil(listaFiltrada.value.length / porPagina.value)))

const listaPaginada = computed(() => {
  const ini = (pagina.value - 1) * porPagina.value
  return listaFiltrada.value.slice(ini, ini + porPagina.value)
})

async function cargar() {
  cargando.value = true
  consultado.value = false
  lista.value = []
  kardex.value = {}
  expandido.value = null
  pagina.value = 1
  try {
    const params = {}
    if (filtroDep.value) params.id_depto = filtroDep.value
    const { data } = await api.get('/reporte-vacaciones', { params })
    lista.value = data
    consultado.value = true
  } catch {
    alert('Error al cargar el reporte')
  } finally {
    cargando.value = false
  }
}

async function descargarPdf() {
  generandoPdf.value = true
  try {
    const params = {}
    if (filtroDep.value) params.id_depto = filtroDep.value
    const resp = await api.get('/reporte-vacaciones/pdf', { params, responseType: 'blob' })
    const url  = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href  = url
    link.download = 'reporte_saldo_vacaciones.pdf'
    link.click()
    URL.revokeObjectURL(url)
  } catch {
    alert('Error al generar el PDF')
  } finally {
    generandoPdf.value = false
  }
}

async function toggleDetalle(id_emp) {
  if (expandido.value === id_emp) {
    expandido.value = null
    return
  }
  expandido.value = id_emp
  if (kardex.value[id_emp]) return
  cargandoKardex.value = true
  try {
    const { data } = await api.get(`/reporte-vacaciones/${id_emp}`)
    kardex.value[id_emp] = data.movimientos
  } catch {
    kardex.value[id_emp] = []
  } finally {
    cargandoKardex.value = false
  }
}

function fmtFecha(f) {
  if (!f) return '—'
  return String(f).slice(0, 10).split('-').reverse().join('/')
}

function rowClass(tipo) {
  if (tipo === 'INICIAL')    return 'bg-blue-50 border-b border-blue-100'
  if (tipo === 'DEVENGADO')  return 'bg-green-50 border-b border-green-100 font-semibold'
  if (tipo === 'VACACION')   return 'bg-amber-50 border-b border-amber-100'
  if (tipo === 'LIQUIDACION') return 'bg-purple-50 border-b border-purple-100 italic'
  if (tipo === 'TOMADOS')    return 'bg-orange-50 border-b border-orange-200 font-semibold'
  if (tipo === 'TOTAL')      return 'bg-gray-100 border-b font-bold'
  return 'border-b'
}

function abrirCargaSaldos() {
  cargaFecha.value     = ''
  cargaFilas.value     = []
  cargaResultado.value = null
  modalCarga.value     = true
}

function parsearCsv(e) {
  const file = e.target.files[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = (ev) => {
    const lineas = ev.target.result.split('\n').map(l => l.trim()).filter(l => l)
    const resultado = []
    for (const linea of lineas) {
      const partes = linea.split(',')
      const cedula = partes[0]?.trim()
      const saldo  = parseFloat(partes[1]?.trim())
      if (!cedula || cedula.toLowerCase() === 'cedula') continue
      const fila = { cedula, saldo: isNaN(saldo) ? null : saldo, error: null }
      if (!cedula) fila.error = 'Cédula vacía'
      else if (fila.saldo === null || fila.saldo < 0) fila.error = 'Saldo inválido'
      resultado.push(fila)
    }
    cargaFilas.value     = resultado
    cargaResultado.value = null
  }
  reader.readAsText(file)
  e.target.value = ''
}

function descargarPlantilla() {
  const contenido = 'cedula,saldo\n1234567890,25.50\n0987654321,12.00'
  const blob = new Blob([contenido], { type: 'text/csv' })
  const url  = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href  = url
  link.download = 'plantilla_saldos_vacaciones.csv'
  link.click()
  URL.revokeObjectURL(url)
}

async function confirmarCarga() {
  if (!cargaFecha.value || !cargaFilasValidas.value.length) return
  cargando2.value      = true
  cargaResultado.value = null
  try {
    const { data } = await api.post('/reporte-vacaciones/cargar-saldos', {
      fecha_corte: cargaFecha.value,
      saldos: cargaFilasValidas.value.map(f => ({ cedula: f.cedula, saldo: f.saldo })),
    })
    cargaResultado.value = data
    if (lista.value.length) cargar()
  } catch (err) {
    cargaResultado.value = { error: err.response?.data?.message ?? 'Error al procesar la carga' }
  } finally {
    cargando2.value = false
  }
}
</script>
