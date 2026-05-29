<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-800">Rol de Pagos</h1>
      <span v-if="cab" :class="cab.estado === 'BORRADOR'
          ? 'bg-yellow-100 text-yellow-700'
          : 'bg-green-100 text-green-700'"
        class="px-3 py-1 rounded-full text-sm font-semibold">
        {{ cab.estado }}
      </span>
    </div>

    <!-- Controles período -->
    <div class="bg-white rounded-xl shadow p-4 flex flex-wrap gap-4 items-end">
      <div>
        <label class="block text-xs text-gray-500 mb-1">Mes</label>
        <select v-model="form.mes" @change="cargar"
          class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
          <option v-for="(n, i) in meses" :key="i + 1" :value="i + 1">{{ n }}</option>
        </select>
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">Año</label>
        <select v-model="form.anio" @change="cargar"
          class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
          <option v-for="a in anios" :key="a" :value="a">{{ a }}</option>
        </select>
      </div>

      <button @click="calcular" :disabled="calculando"
        class="bg-[#0b5447] text-white px-4 py-2 rounded-lg hover:bg-[#00372e] text-sm font-medium disabled:opacity-50">
        {{ calculando ? 'Calculando...' : 'Calcular' }}
      </button>

      <button v-if="cab && cab.estado === 'BORRADOR'" @click="abrirImportar"
        class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 text-sm font-medium">
        Importar CSV
      </button>

      <button v-if="cab && cab.estado === 'BORRADOR'" @click="cerrar"
        class="bg-orange-600 text-white px-4 py-2 rounded-lg hover:bg-orange-700 text-sm font-medium">
        Cerrar Período
      </button>

      <button v-if="cab" @click="generarPdf"
        class="bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800 text-sm font-medium">
        Generar PDF
      </button>

      <div v-if="cargando" class="text-sm text-gray-400">Cargando...</div>
    </div>

    <!-- Totales resumen -->
    <div v-if="cab" class="grid grid-cols-2 md:grid-cols-4 gap-4">
      <div class="bg-white rounded-xl shadow p-4">
        <p class="text-xs text-gray-500">Servidores</p>
        <p class="text-xl font-bold text-gray-800">{{ cab.total_empleados }}</p>
      </div>
      <div class="bg-white rounded-xl shadow p-4">
        <p class="text-xs text-gray-500">Total RMU</p>
        <p class="text-xl font-bold text-gray-800">{{ fmt(cab.total_bruto) }}</p>
      </div>
      <div class="bg-white rounded-xl shadow p-4">
        <p class="text-xs text-gray-500">Total Descuentos</p>
        <p class="text-xl font-bold text-red-600">{{ fmt(cab.total_descuentos) }}</p>
      </div>
      <div class="bg-white rounded-xl shadow p-4">
        <p class="text-xs text-gray-500">Total Líquido</p>
        <p class="text-xl font-bold text-green-700">{{ fmt(cab.total_liquido) }}</p>
      </div>
    </div>

    <!-- Tabs -->
    <div v-if="cab && detalles.length" class="bg-white rounded-xl shadow overflow-hidden">
      <div class="flex border-b">
        <button @click="tabActivo = 'detalle'"
          :class="tabActivo === 'detalle' ? 'border-b-2 border-[#0b5447] text-[#0b5447] font-semibold' : 'text-gray-500 hover:text-gray-700'"
          class="px-6 py-3 text-sm transition-colors">
          Detalle por Empleado
        </button>
        <button @click="cargarResumenes"
          :class="tabActivo === 'resumenes' ? 'border-b-2 border-[#0b5447] text-[#0b5447] font-semibold' : 'text-gray-500 hover:text-gray-700'"
          class="px-6 py-3 text-sm transition-colors">
          Resúmenes
        </button>
      </div>

      <!-- TAB 1: Detalle -->
      <div v-show="tabActivo === 'detalle'">
        <div class="overflow-auto max-h-[calc(100vh-22rem)]">
          <table class="text-xs whitespace-nowrap border-collapse w-full">
            <thead class="sticky top-0 z-20">
              <tr>
                <th class="px-2 py-2 text-center text-gray-600 font-medium border bg-gray-50 sticky left-0 z-30 w-8">N°</th>
                <th class="px-2 py-2 text-left text-gray-600 font-medium border bg-gray-50 sticky left-8 z-30 w-24">Cédula</th>
                <th class="px-2 py-2 text-left text-gray-600 font-medium border bg-gray-50 min-w-[160px] sticky left-32 z-30">Apellidos y Nombres</th>
                <th class="px-2 py-2 text-left text-gray-600 font-medium border bg-gray-50 min-w-[110px]">Departamento</th>
                <th class="px-2 py-2 text-center text-gray-600 font-medium border bg-gray-50">Prog.</th>
                <th class="px-2 py-2 text-center text-gray-600 font-medium border bg-gray-50">Act.</th>
                <th class="px-2 py-2 text-center text-gray-600 font-medium border bg-gray-50">Días</th>
                <th class="px-2 py-2 text-right text-gray-600 font-medium border bg-gray-50">RMU $</th>
                <!-- Aportes patronales -->
                <th class="px-2 py-2 text-right text-blue-700 font-medium border bg-blue-50">IECE ({{ pctIece }}%)</th>
                <th class="px-2 py-2 text-right text-blue-700 font-medium border bg-blue-50">SECAP<br><span class="text-[10px] font-normal">LOSEP 0% / CdT {{ pctSecap }}%</span></th>
                <th class="px-2 py-2 text-right text-blue-700 font-medium border bg-blue-50">{{ headerPatronal }}</th>
                <th class="px-2 py-2 text-right text-blue-800 font-semibold border bg-blue-100">Total Patronal</th>
                <!-- Descuentos -->
                <th class="px-2 py-2 text-right text-orange-700 font-medium border bg-orange-50">{{ headerPersonal }}</th>
                <th class="px-2 py-2 text-right text-orange-700 font-medium border bg-orange-50"
                  :title="cab?.estado === 'BORRADOR' ? 'Click para editar' : ''">
                  Quirogr.{{ cab?.estado === 'BORRADOR' ? ' ✎' : '' }}
                </th>
                <th class="px-2 py-2 text-right text-orange-700 font-medium border bg-orange-50"
                  :title="cab?.estado === 'BORRADOR' ? 'Click para editar' : ''">
                  Hipotec.{{ cab?.estado === 'BORRADOR' ? ' ✎' : '' }}
                </th>
                <th class="px-2 py-2 text-right text-orange-700 font-medium border bg-orange-50"
                  :title="cab?.estado === 'BORRADOR' ? 'Click para editar' : ''">
                  Imp.Renta{{ cab?.estado === 'BORRADOR' ? ' ✎' : '' }}
                </th>
                <th class="px-2 py-2 text-right text-orange-700 font-medium border bg-orange-50"
                  :title="cab?.estado === 'BORRADOR' ? 'Click para editar' : ''">
                  SUPA{{ cab?.estado === 'BORRADOR' ? ' ✎' : '' }}
                </th>
                <th class="px-2 py-2 text-right text-orange-700 font-medium border bg-orange-50"
                  :title="cab?.estado === 'BORRADOR' ? 'Click para editar' : ''">
                  Póliza Blanket{{ cab?.estado === 'BORRADOR' ? ' ✎' : '' }}
                </th>
                <th class="px-2 py-2 text-right text-orange-700 font-medium border bg-orange-50"
                  :title="cab?.estado === 'BORRADOR' ? 'Click para editar' : ''">
                  Sanciones{{ cab?.estado === 'BORRADOR' ? ' ✎' : '' }}
                </th>
                <th class="px-2 py-2 text-right text-orange-700 font-medium border bg-orange-50"
                  :title="cab?.estado === 'BORRADOR' ? 'Click para editar' : ''">
                  Otros Desc.{{ cab?.estado === 'BORRADOR' ? ' ✎' : '' }}
                </th>
                <th class="px-2 py-2 text-left text-orange-700 font-medium border bg-orange-50"
                  :title="cab?.estado === 'BORRADOR' ? 'Click para editar' : ''">
                  Observaciones{{ cab?.estado === 'BORRADOR' ? ' ✎' : '' }}
                </th>
                <th class="px-2 py-2 text-right text-gray-700 font-semibold border bg-gray-50">T.Desc $</th>
                <th class="px-2 py-2 text-right text-gray-700 font-semibold border bg-gray-50">Líquido $</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(r, i) in detallesPaginados" :key="r.id" class="border-b hover:bg-gray-50 group">
                <td class="px-2 py-2 text-center border sticky left-0 z-10 bg-white group-hover:bg-gray-50">{{ (pagina - 1) * POR_PAGINA + i + 1 }}</td>
                <td class="px-2 py-2 font-mono border sticky left-8 z-10 bg-white group-hover:bg-gray-50">{{ r.identificacion }}</td>
                <td class="px-2 py-2 border sticky left-32 z-10 bg-white group-hover:bg-gray-50">{{ r.apellido_emp }} {{ r.nombre_emp }}</td>
                <td class="px-2 py-2 border text-gray-600">{{ r.nombre_depto }}</td>
                <td class="px-2 py-2 text-center border text-gray-500">{{ r.programa || '—' }}</td>
                <td class="px-2 py-2 text-center border text-gray-500">{{ r.actividad || '—' }}</td>
                <td class="px-2 py-2 text-center border">{{ r.dias }}</td>
                <td class="px-2 py-2 text-right font-mono border">{{ fmt(r.valor_rmu) }}</td>
                <!-- Patronal -->
                <td class="px-2 py-2 text-right font-mono border bg-blue-50/40">{{ fmt(r.iece) }}</td>
                <td class="px-2 py-2 text-right font-mono border bg-blue-50/40">{{ fmt(r.secap) }}</td>
                <td class="px-2 py-2 text-right font-mono border bg-blue-50/40">{{ fmt(r.aporte_patronal) }}</td>
                <td class="px-2 py-2 text-right font-mono border bg-blue-100/50 font-semibold">{{ fmt(totalPatronalFila(r)) }}</td>
                <!-- Descuentos editables -->
                <td class="px-2 py-2 text-right font-mono border bg-orange-50/40">{{ fmt(r.aporte_personal) }}</td>
                <td v-for="campo in camposEditables" :key="campo"
                  class="px-1 py-1 text-right font-mono border bg-orange-50/40"
                  :class="cab?.estado === 'BORRADOR' ? 'cursor-pointer hover:bg-blue-50' : ''"
                  @click="iniciarEdicion(r, campo)">
                  <input v-if="editando?.id === r.id && editando?.campo === campo"
                    v-model.number="editando.valor"
                    type="number" step="0.01" min="0"
                    class="w-20 border border-blue-400 rounded px-1 py-0.5 text-right text-xs focus:outline-none"
                    @blur="guardarEdicion(r)"
                    @keyup.enter="guardarEdicion(r)"
                    @keyup.escape="editando = null"
                    @click.stop
                    ref="inputEdicion" />
                  <span v-else>{{ fmt(r[campo]) }}</span>
                </td>
                <!-- Observaciones — editable inline como texto -->
                <td class="px-1 py-1 text-left border bg-orange-50/40 max-w-[120px]"
                  :class="cab?.estado === 'BORRADOR' ? 'cursor-pointer hover:bg-blue-50' : ''"
                  @click="iniciarEdicion(r, 'observaciones')">
                  <input v-if="editando?.id === r.id && editando?.campo === 'observaciones'"
                    v-model="editando.valor"
                    type="text" maxlength="300"
                    class="w-full border border-blue-400 rounded px-1 py-0.5 text-xs focus:outline-none"
                    @blur="guardarEdicion(r)"
                    @keyup.enter="guardarEdicion(r)"
                    @keyup.escape="editando = null"
                    @click.stop
                    ref="inputEdicion" />
                  <span v-else class="text-xs truncate block">{{ r.observaciones || '—' }}</span>
                </td>
                <td class="px-2 py-2 text-right font-mono border font-semibold">{{ fmt(r.total_descuentos) }}</td>
                <td class="px-2 py-2 text-right font-mono border font-semibold text-green-700">{{ fmt(r.liquido) }}</td>
              </tr>
            </tbody>
            <!-- Totales -->
            <tfoot>
              <tr class="bg-gray-100 font-semibold text-xs">
                <td colspan="7" class="px-2 py-2 text-right border bg-gray-100 sticky left-0 z-10">TOTAL ({{ detalles.length }})</td>
                <td class="px-2 py-2 text-right font-mono border">{{ fmt(sumCol('valor_rmu')) }}</td>
                <td class="px-2 py-2 text-right font-mono border bg-blue-50">{{ fmt(sumCol('iece')) }}</td>
                <td class="px-2 py-2 text-right font-mono border bg-blue-50">{{ fmt(sumCol('secap')) }}</td>
                <td class="px-2 py-2 text-right font-mono border bg-blue-50">{{ fmt(sumCol('aporte_patronal')) }}</td>
                <td class="px-2 py-2 text-right font-mono border bg-blue-100">{{ fmt(sumCol('iece') + sumCol('secap') + sumCol('aporte_patronal')) }}</td>
                <td class="px-2 py-2 text-right font-mono border bg-orange-50">{{ fmt(sumCol('aporte_personal')) }}</td>
                <td class="px-2 py-2 text-right font-mono border bg-orange-50">{{ fmt(sumCol('quirografario')) }}</td>
                <td class="px-2 py-2 text-right font-mono border bg-orange-50">{{ fmt(sumCol('hipotecario')) }}</td>
                <td class="px-2 py-2 text-right font-mono border bg-orange-50">{{ fmt(sumCol('impuesto_renta')) }}</td>
                <td class="px-2 py-2 text-right font-mono border bg-orange-50">{{ fmt(sumCol('supa')) }}</td>
                <td class="px-2 py-2 text-right font-mono border bg-orange-50">{{ fmt(sumCol('poliza_blanket')) }}</td>
                <td class="px-2 py-2 text-right font-mono border bg-orange-50">{{ fmt(sumCol('sanciones')) }}</td>
                <td class="px-2 py-2 text-right font-mono border bg-orange-50">{{ fmt(sumCol('otros_descuentos')) }}</td>
                <td class="px-2 py-2 border bg-orange-50"></td>
                <td class="px-2 py-2 text-right font-mono border">{{ fmt(sumCol('total_descuentos')) }}</td>
                <td class="px-2 py-2 text-right font-mono border text-green-700">{{ fmt(sumCol('liquido')) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Paginador -->
        <div v-if="totalPaginas > 1" class="flex items-center justify-between px-6 py-3 border-t text-sm text-gray-600">
          <span>Mostrando {{ (pagina - 1) * POR_PAGINA + 1 }}–{{ Math.min(pagina * POR_PAGINA, detalles.length) }} de {{ detalles.length }}</span>
          <div class="flex gap-2">
            <button @click="pagina--" :disabled="pagina === 1"
              class="px-3 py-1 border rounded hover:bg-gray-100 disabled:opacity-40">‹ Anterior</button>
            <span class="px-3 py-1">{{ pagina }} / {{ totalPaginas }}</span>
            <button @click="pagina++" :disabled="pagina === totalPaginas"
              class="px-3 py-1 border rounded hover:bg-gray-100 disabled:opacity-40">Siguiente ›</button>
          </div>
        </div>
      </div>

      <!-- TAB 2: Resúmenes -->
      <div v-show="tabActivo === 'resumenes'" class="p-4">
        <div class="flex justify-end mb-3">
          <button v-if="resumenes.length" @click="generarPdfResumen"
            class="bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800 text-sm font-medium">
            Generar PDF Resumen
          </button>
        </div>
        <div v-if="cargandoResumenes" class="text-center py-8 text-gray-400 text-sm">Cargando resúmenes...</div>
        <div v-else-if="resumenes.length" class="overflow-x-auto">
          <table class="text-sm border-collapse w-full">
            <thead>
              <tr style="background-color:#0b5447;">
                <th class="px-4 py-2 text-left text-white font-medium text-xs uppercase">Programa</th>
                <th class="px-4 py-2 text-left text-white font-medium text-xs uppercase">Actividad</th>
                <th class="px-4 py-2 text-center text-white font-medium text-xs uppercase">Empleados</th>
                <th class="px-4 py-2 text-right text-white font-medium text-xs uppercase">Total RMU</th>
                <th class="px-4 py-2 text-right text-white font-medium text-xs uppercase">Total Patronal</th>
                <th class="px-4 py-2 text-right text-white font-medium text-xs uppercase">Total Descuentos</th>
                <th class="px-4 py-2 text-right text-white font-medium text-xs uppercase">Total Líquido</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(r, i) in resumenes" :key="i"
                class="border-b hover:bg-gray-50 transition-colors"
                :class="i % 2 === 0 ? 'bg-white' : 'bg-gray-50/50'">
                <td class="px-4 py-2 font-mono text-gray-700">{{ r.programa }}</td>
                <td class="px-4 py-2 font-mono text-gray-700">{{ r.actividad }}</td>
                <td class="px-4 py-2 text-center text-gray-700">{{ r.empleados }}</td>
                <td class="px-4 py-2 text-right font-mono">{{ fmt(r.total_rmu) }}</td>
                <td class="px-4 py-2 text-right font-mono text-blue-700">{{ fmt(r.total_patronal) }}</td>
                <td class="px-4 py-2 text-right font-mono text-orange-700">{{ fmt(r.total_descuentos) }}</td>
                <td class="px-4 py-2 text-right font-mono text-green-700 font-semibold">{{ fmt(r.total_liquido) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="bg-gray-100 font-semibold border-t-2 border-gray-400">
                <td colspan="2" class="px-4 py-2 text-right text-gray-700">TOTAL</td>
                <td class="px-4 py-2 text-center">{{ resumenesTotales.empleados }}</td>
                <td class="px-4 py-2 text-right font-mono">{{ fmt(resumenesTotales.rmu) }}</td>
                <td class="px-4 py-2 text-right font-mono text-blue-700">{{ fmt(resumenesTotales.patronal) }}</td>
                <td class="px-4 py-2 text-right font-mono text-orange-700">{{ fmt(resumenesTotales.descuentos) }}</td>
                <td class="px-4 py-2 text-right font-mono text-green-700">{{ fmt(resumenesTotales.liquido) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
        <div v-else class="text-center py-8 text-gray-400 text-sm">Sin datos de resúmenes.</div>
      </div>
    </div>

    <div v-else-if="!cargando && !cab" class="text-center py-12 text-gray-400 text-sm">
      Selecciona un período y haz clic en <strong>Calcular</strong> para generar el Rol de Pagos.
    </div>

    <div v-else-if="!cargando && cab && detalles.length === 0" class="text-center py-12 text-gray-400 text-sm">
      No hay empleados en el período calculado.
    </div>

    <div v-if="error" class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700">{{ error }}</div>

    <!-- Modal importar CSV -->
    <div v-if="modalImportar" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-lg space-y-4">
        <h2 class="text-lg font-semibold text-gray-700">Importar descuentos desde CSV</h2>

        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-xs text-blue-700 space-y-1">
          <p>El archivo CSV debe tener las siguientes columnas (con encabezado):</p>
          <p class="font-mono">cedula, quirografario, hipotecario, impuesto_renta, poliza_blanket, sanciones, otros_descuentos</p>
          <p>Las columnas que no incluyas no se modifican.</p>
        </div>

        <input type="file" accept=".csv" @change="leerCsv"
          class="block w-full text-sm text-gray-600 border border-gray-300 rounded-lg px-3 py-2 cursor-pointer" />

        <!-- Preview -->
        <div v-if="csvFilas.length" class="max-h-48 overflow-y-auto border rounded-lg">
          <table class="w-full text-xs">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-3 py-2 text-left">Cédula</th>
                <th class="px-3 py-2 text-right">Quirografario</th>
                <th class="px-3 py-2 text-right">Hipotecario</th>
                <th class="px-3 py-2 text-right">Imp. Renta</th>
                <th class="px-3 py-2 text-right">Póliza Blanket</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(f, i) in csvFilas" :key="i" class="border-t">
                <td class="px-3 py-1 font-mono">{{ f.cedula }}</td>
                <td class="px-3 py-1 text-right">{{ f.quirografario ?? '—' }}</td>
                <td class="px-3 py-1 text-right">{{ f.hipotecario ?? '—' }}</td>
                <td class="px-3 py-1 text-right">{{ f.impuesto_renta ?? '—' }}</td>
                <td class="px-3 py-1 text-right">{{ f.poliza_blanket ?? '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <p v-if="csvError" class="text-red-600 text-xs">{{ csvError }}</p>

        <div v-if="csvResultado" class="text-sm space-y-1">
          <p class="text-green-700 font-medium">✓ {{ csvResultado.actualizados }} empleados actualizados.</p>
          <div v-if="csvResultado.no_encontrados.length" class="text-red-600">
            <p class="font-medium">No encontrados ({{ csvResultado.no_encontrados.length }}):</p>
            <p class="font-mono text-xs">{{ csvResultado.no_encontrados.join(', ') }}</p>
          </div>
        </div>

        <div class="flex justify-end gap-3 pt-2">
          <button @click="cerrarImportar" class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">
            {{ csvResultado ? 'Cerrar' : 'Cancelar' }}
          </button>
          <button v-if="!csvResultado" @click="enviarCsv" :disabled="!csvFilas.length || importando"
            class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700 disabled:opacity-50">
            {{ importando ? 'Importando...' : 'Importar' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, nextTick, onMounted } from 'vue'
import api from '@/services/api'

const POR_PAGINA = 20
const meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
               'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre']
const anioActual = new Date().getFullYear()
const anios = Array.from({ length: anioActual - 2023 }, (_, i) => anioActual - i)

const camposEditables = ['quirografario', 'hipotecario', 'impuesto_renta', 'supa', 'poliza_blanket', 'sanciones', 'otros_descuentos']

const form       = ref({ mes: new Date().getMonth() + 1, anio: anioActual })
const cab        = ref(null)
const detalles   = ref([])
const cargando   = ref(false)
const calculando = ref(false)
const error      = ref('')
const pagina     = ref(1)
const editando     = ref(null)
const inputEdicion = ref(null)
const modalImportar = ref(false)
const tabActivo     = ref('detalle')
const resumenes       = ref([])
const cargandoResumenes = ref(false)

const csvFilas     = ref([])
const csvError     = ref('')
const csvResultado = ref(null)
const importando   = ref(false)

const totalPaginas = computed(() => Math.ceil(detalles.value.length / POR_PAGINA))

const pctIece = computed(() => {
  const d = detalles.value.find(d => parseFloat(d.iece_pct) > 0)
  return d ? parseFloat(d.iece_pct) : 0.5
})
const pctSecap = computed(() => {
  const d = detalles.value.find(d => parseFloat(d.secap) > 0)
  return d ? parseFloat(d.secap_pct) : 0.5
})

const headerPatronal = computed(() => {
  const losep = detalles.value.find(d => d.tipo_contrato?.includes('LOSEP'))?.aporte_patronal_pct
  const ct    = detalles.value.find(d => d.tipo_contrato?.includes('CODIGO'))?.aporte_patronal_pct
  if (losep && ct) return `AP. Patronal (LOSEP ${losep}% / CdT ${ct}%)`
  if (losep)       return `AP. Patronal (${losep}%)`
  if (ct)          return `AP. Patronal (${ct}%)`
  return 'AP. Patronal'
})
const headerPersonal = computed(() => {
  const pcts = [...new Set(detalles.value.map(d => parseFloat(d.aporte_personal_pct)))].sort((a,b) => a-b)
  return `AP. Personal (${pcts.map(p => p + '%').join(' / ')})`
})

const detallesPaginados = computed(() => {
  const inicio = (pagina.value - 1) * POR_PAGINA
  return detalles.value.slice(inicio, inicio + POR_PAGINA)
})

const totalPatronalFila = (r) => parseFloat(r.iece || 0) + parseFloat(r.secap || 0) + parseFloat(r.aporte_patronal || 0)

const sumCol = (col) => detalles.value.reduce((s, d) => s + parseFloat(d[col] || 0), 0)

const resumenesTotales = computed(() => ({
  empleados:  resumenes.value.reduce((s, r) => s + parseInt(r.empleados || 0), 0),
  rmu:        resumenes.value.reduce((s, r) => s + parseFloat(r.total_rmu || 0), 0),
  patronal:   resumenes.value.reduce((s, r) => s + parseFloat(r.total_patronal || 0), 0),
  descuentos: resumenes.value.reduce((s, r) => s + parseFloat(r.total_descuentos || 0), 0),
  liquido:    resumenes.value.reduce((s, r) => s + parseFloat(r.total_liquido || 0), 0),
}))

const fmt = (v) => {
  const n = parseFloat(v)
  if (isNaN(n) || n === 0) return '—'
  return n.toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const cargar = async () => {
  cargando.value = true
  error.value = ''
  cab.value = null
  detalles.value = []
  resumenes.value = []
  pagina.value = 1
  tabActivo.value = 'detalle'
  editando.value = null
  try {
    const { data } = await api.get('/nomina/rol-pago', { params: form.value })
    cab.value      = data.cab
    detalles.value = data.detalles
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al cargar.'
  } finally {
    cargando.value = false
  }
}

const calcular = async () => {
  if (cab.value?.estado === 'CERRADO') {
    alert('El período está cerrado y no puede recalcularse.')
    return
  }
  if (cab.value?.estado === 'BORRADOR') {
    if (!confirm('Ya existe un cálculo en BORRADOR para este período. ¿Desea recalcular y reemplazarlo?')) return
  }
  calculando.value = true
  error.value = ''
  try {
    const { data } = await api.post('/nomina/rol-pago/calcular', form.value)
    cab.value      = data.cab
    detalles.value = data.detalles
    resumenes.value = []
    pagina.value   = 1
    tabActivo.value = 'detalle'
    editando.value = null
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al calcular.'
  } finally {
    calculando.value = false
  }
}

const cerrar = async () => {
  if (!confirm('¿Cerrar el período? Una vez cerrado no podrá recalcularse ni editarse.')) return
  try {
    const { data } = await api.post('/nomina/rol-pago/cerrar', form.value)
    cab.value = data
    editando.value = null
  } catch (e) {
    alert(e.response?.data?.message || 'Error al cerrar.')
  }
}

const generarPdf = async () => {
  try {
    const resp = await api.get('/nomina/rol-pago/pdf', {
      params: { anio: form.value.anio, mes: form.value.mes },
      responseType: 'blob',
    })
    const url = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
    const a = document.createElement('a')
    a.href = url
    a.download = `rol_pago_${form.value.anio}_${String(form.value.mes).padStart(2, '0')}.pdf`
    a.click()
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch {
    alert('Error al generar el PDF.')
  }
}

const generarPdfResumen = async () => {
  try {
    const resp = await api.get(`/nomina/rol-pago/${cab.value.id}/resumenes/pdf`, { responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
    const a = document.createElement('a')
    a.href = url
    a.download = `rol-pago-resumen-${form.value.anio}-${String(form.value.mes).padStart(2, '0')}.pdf`
    a.click()
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch {
    alert('Error al generar el PDF de resúmenes.')
  }
}

const cargarResumenes = async () => {
  tabActivo.value = 'resumenes'
  if (resumenes.value.length || !cab.value) return
  cargandoResumenes.value = true
  try {
    const { data } = await api.get(`/nomina/rol-pago/${cab.value.id}/resumenes`)
    resumenes.value = data
  } catch {
    /* silencioso */
  } finally {
    cargandoResumenes.value = false
  }
}

const iniciarEdicion = (row, campo) => {
  if (cab.value?.estado !== 'BORRADOR') return
  editando.value = { id: row.id, campo, valor: parseFloat(row[campo]) || 0 }
  nextTick(() => {
    if (inputEdicion.value) {
      const el = Array.isArray(inputEdicion.value) ? inputEdicion.value[0] : inputEdicion.value
      el?.focus()
      el?.select()
    }
  })
}

const guardarEdicion = async (row) => {
  if (!editando.value || editando.value.id !== row.id) return
  const campo = editando.value.campo
  const esTexto = campo === 'observaciones'
  const valor = esTexto ? (editando.value.valor ?? '') : (parseFloat(editando.value.valor) || 0)
  editando.value = null

  if (!esTexto && Math.abs(valor - (parseFloat(row[campo]) || 0)) < 0.001) return
  if (esTexto && valor === (row[campo] || '')) return

  try {
    const { data } = await api.put(`/nomina/rol-pago/detalle/${row.id}`, { [campo]: valor })

    const idx = detalles.value.findIndex(d => d.id === row.id)
    if (idx !== -1) {
      Object.assign(detalles.value[idx], {
        quirografario:    data.quirografario,
        hipotecario:      data.hipotecario,
        impuesto_renta:   data.impuesto_renta,
        supa:             data.supa,
        poliza_blanket:   data.poliza_blanket,
        sanciones:        data.sanciones,
        otros_descuentos: data.otros_descuentos,
        observaciones:    data.observaciones,
        total_descuentos: data.total_descuentos,
        liquido:          data.liquido,
      })
    }

    if (cab.value) {
      cab.value.total_descuentos = detalles.value.reduce((s, d) => s + parseFloat(d.total_descuentos || 0), 0)
      cab.value.total_liquido    = detalles.value.reduce((s, d) => s + parseFloat(d.liquido || 0), 0)
    }
    // Invalidar resúmenes para que se recarguen
    resumenes.value = []
  } catch (e) {
    alert(e.response?.data?.message || 'Error al guardar.')
    cargar()
  }
}

const abrirImportar = () => {
  csvFilas.value = []
  csvError.value = ''
  csvResultado.value = null
  modalImportar.value = true
}

const cerrarImportar = () => {
  modalImportar.value = false
  if (csvResultado.value?.actualizados > 0) cargar()
}

const leerCsv = (e) => {
  csvError.value = ''
  csvFilas.value = []
  csvResultado.value = null
  const file = e.target.files[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = (ev) => {
    const lines = ev.target.result.split(/\r?\n/).filter(l => l.trim())
    if (lines.length < 2) { csvError.value = 'El archivo está vacío.'; return }
    const headers = lines[0].split(',').map(h => h.trim().toLowerCase())
    if (!headers.includes('cedula')) { csvError.value = 'Falta la columna "cedula".'; return }
    const filas = []
    for (let i = 1; i < lines.length; i++) {
      const cols = lines[i].split(',').map(c => c.trim())
      const row = {}
      headers.forEach((h, idx) => { row[h] = cols[idx] ?? '' })
      if (!row.cedula) continue
      const parseOpt = (key) => row[key] !== undefined && row[key] !== '' ? parseFloat(row[key]) : undefined
      filas.push({
        cedula:           row.cedula,
        quirografario:    parseOpt('quirografario'),
        hipotecario:      parseOpt('hipotecario'),
        impuesto_renta:   parseOpt('impuesto_renta'),
        poliza_blanket:   parseOpt('poliza_blanket'),
        sanciones:        parseOpt('sanciones'),
        otros_descuentos: parseOpt('otros_descuentos'),
      })
    }
    if (!filas.length) { csvError.value = 'No se encontraron filas válidas.'; return }
    csvFilas.value = filas
  }
  reader.readAsText(file)
}

const enviarCsv = async () => {
  importando.value = true
  try {
    const { data } = await api.post('/nomina/rol-pago/importar', {
      anio:  form.value.anio,
      mes:   form.value.mes,
      filas: csvFilas.value,
    })
    csvResultado.value = data
  } catch (e) {
    csvError.value = e.response?.data?.message || 'Error al importar.'
  } finally {
    importando.value = false
  }
}

onMounted(cargar)
</script>
