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

    <!-- Tabla -->
    <div v-if="detalles.length" class="bg-white rounded-xl shadow overflow-hidden">
      <div class="overflow-x-auto">
        <table class="text-xs whitespace-nowrap border-collapse w-full">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="px-3 py-2 text-center text-gray-600 font-medium border">N°</th>
              <th class="px-3 py-2 text-left text-gray-600 font-medium border">Cédula</th>
              <th class="px-3 py-2 text-left text-gray-600 font-medium border min-w-[160px]">Apellidos y Nombres</th>
              <th class="px-3 py-2 text-left text-gray-600 font-medium border min-w-[120px]">Departamento</th>
              <th class="px-3 py-2 text-center text-gray-600 font-medium border">Días</th>
              <th class="px-3 py-2 text-right text-gray-600 font-medium border">RMU $</th>
              <th class="px-3 py-2 text-right text-gray-600 font-medium border">{{ headerPatronal }}</th>
              <th class="px-3 py-2 text-right text-gray-600 font-medium border">{{ headerPersonal }}</th>
              <th class="px-3 py-2 text-right text-gray-600 font-medium border" :title="cab?.estado === 'BORRADOR' ? 'Click para editar' : ''">
                Quirogr.{{ cab?.estado === 'BORRADOR' ? ' ✎' : '' }}
              </th>
              <th class="px-3 py-2 text-right text-gray-600 font-medium border" :title="cab?.estado === 'BORRADOR' ? 'Click para editar' : ''">
                Hipotec.{{ cab?.estado === 'BORRADOR' ? ' ✎' : '' }}
              </th>
              <th class="px-3 py-2 text-right text-gray-600 font-medium border" :title="cab?.estado === 'BORRADOR' ? 'Click para editar' : ''">
                Imp.Renta{{ cab?.estado === 'BORRADOR' ? ' ✎' : '' }}
              </th>
              <th class="px-3 py-2 text-right text-gray-600 font-medium border" :title="cab?.estado === 'BORRADOR' ? 'Click para editar' : ''">
                SUPA{{ cab?.estado === 'BORRADOR' ? ' ✎' : '' }}
              </th>
              <th class="px-3 py-2 text-right text-gray-600 font-medium border">T.Desc $</th>
              <th class="px-3 py-2 text-right text-gray-600 font-medium border">Líquido $</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(r, i) in detallesPaginados" :key="r.id" class="border-b hover:bg-gray-50">
              <td class="px-3 py-2 text-center border">{{ (pagina - 1) * POR_PAGINA + i + 1 }}</td>
              <td class="px-3 py-2 font-mono border">{{ r.identificacion }}</td>
              <td class="px-3 py-2 border">{{ r.apellido_emp }} {{ r.nombre_emp }}</td>
              <td class="px-3 py-2 border text-gray-600">{{ r.nombre_depto }}</td>
              <td class="px-3 py-2 text-center border">{{ r.dias }}</td>
              <td class="px-3 py-2 text-right font-mono border">{{ fmt(r.valor_rmu) }}</td>
              <td class="px-3 py-2 text-right font-mono border">{{ fmt(r.aporte_patronal) }}</td>
              <td class="px-3 py-2 text-right font-mono border">{{ fmt(r.aporte_personal) }}</td>

              <!-- Campos editables -->
              <td v-for="campo in ['quirografario', 'hipotecario', 'impuesto_renta', 'supa']"
                :key="campo"
                class="px-1 py-1 text-right font-mono border"
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

              <td class="px-3 py-2 text-right font-mono border font-semibold">{{ fmt(r.total_descuentos) }}</td>
              <td class="px-3 py-2 text-right font-mono border font-semibold text-green-700">{{ fmt(r.liquido) }}</td>
            </tr>
          </tbody>
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

    <div v-else-if="!cargando && !cab" class="text-center py-12 text-gray-400 text-sm">
      Selecciona un período y haz clic en <strong>Calcular</strong> para generar el Rol de Pagos.
    </div>

    <div v-else-if="!cargando && cab && detalles.length === 0" class="text-center py-12 text-gray-400 text-sm">
      No hay empleados en el período calculado.
    </div>

    <div v-if="error" class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm text-red-700">{{ error }}</div>
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

const form       = ref({ mes: new Date().getMonth() + 1, anio: anioActual })
const cab        = ref(null)
const detalles   = ref([])
const cargando   = ref(false)
const calculando = ref(false)
const error      = ref('')
const pagina     = ref(1)
const editando   = ref(null)
const inputEdicion = ref(null)

const totalPaginas = computed(() => Math.ceil(detalles.value.length / POR_PAGINA))

const headerPatronal = computed(() => {
  const pcts = [...new Set(detalles.value.map(d => parseFloat(d.aporte_patronal_pct)))]
  return pcts.length === 1 ? `Ap. Patronal ${pcts[0]}%` : 'Ap. Patronal'
})
const headerPersonal = computed(() => {
  const pcts = [...new Set(detalles.value.map(d => parseFloat(d.aporte_personal_pct)))]
  return pcts.length === 1 ? `Ap. Personal ${pcts[0]}%` : 'Ap. Personal'
})
const detallesPaginados = computed(() => {
  const inicio = (pagina.value - 1) * POR_PAGINA
  return detalles.value.slice(inicio, inicio + POR_PAGINA)
})

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
  pagina.value = 1
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
    pagina.value   = 1
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
    URL.revokeObjectURL(url)
  } catch {
    alert('Error al generar el PDF.')
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
  const valor = parseFloat(editando.value.valor) || 0
  editando.value = null

  if (Math.abs(valor - (parseFloat(row[campo]) || 0)) < 0.001) return

  try {
    const { data } = await api.put(`/nomina/rol-pago/detalle/${row.id}`, { [campo]: valor })

    // Actualizar fila localmente (preservar campos de empleado)
    const idx = detalles.value.findIndex(d => d.id === row.id)
    if (idx !== -1) {
      Object.assign(detalles.value[idx], {
        quirografario:    data.quirografario,
        hipotecario:      data.hipotecario,
        impuesto_renta:   data.impuesto_renta,
        supa:             data.supa,
        total_descuentos: data.total_descuentos,
        liquido:          data.liquido,
      })
    }

    // Recalcular totales del cab localmente
    if (cab.value) {
      cab.value.total_descuentos = detalles.value.reduce((s, d) => s + parseFloat(d.total_descuentos || 0), 0)
      cab.value.total_liquido    = detalles.value.reduce((s, d) => s + parseFloat(d.liquido || 0), 0)
    }
  } catch (e) {
    alert(e.response?.data?.message || 'Error al guardar.')
    cargar()
  }
}

onMounted(cargar)
</script>
