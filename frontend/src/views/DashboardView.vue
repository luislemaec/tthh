<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Dashboard</h1>

    <!-- ── EMPLEADO SIN ROL ESPECIAL ─────────────────────────────────────── -->
    <template v-if="esEmpleadoSolo">
      <!-- Tarjetas -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

        <!-- Permisos pendientes -->
        <div class="rounded-2xl p-6 flex items-center gap-5 text-white"
          style="background: linear-gradient(135deg,#1e3a5f,#2d5f8a);">
          <div class="bg-white/20 p-4 rounded-2xl flex-shrink-0">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
          </div>
          <div>
            <p class="text-sm text-white/70 font-medium uppercase tracking-wide">Permisos pendientes</p>
            <p class="text-5xl font-extrabold leading-none mt-1">{{ stats.permisos_pendientes }}</p>
            <p class="text-xs text-white/60 mt-1">{{ stats.permisos_pendientes === 1 ? 'solicitud en espera' : 'solicitudes en espera' }}</p>
          </div>
        </div>

        <!-- Saldo de vacaciones -->
        <div class="rounded-2xl p-6 flex items-center gap-5 text-white"
          style="background: linear-gradient(135deg,#0b5447,#1a8a6f);">
          <div class="bg-white/20 p-4 rounded-2xl flex-shrink-0">
            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 3v1m0 16v1m8.66-9h-1M4.34 12h-1m15.07-6.07l-.71.71M6.34 17.66l-.71.71M17.66 17.66l-.71-.71M6.34 6.34l-.71-.71M12 7a5 5 0 100 10A5 5 0 0012 7z"/>
            </svg>
          </div>
          <div>
            <p class="text-sm text-white/70 font-medium uppercase tracking-wide">Saldo de vacaciones</p>
            <p class="text-5xl font-extrabold leading-none mt-1">{{ stats.datos_empleado?.saldo_vacaciones ?? 0 }}</p>
            <p class="text-xs text-white/60 mt-1">días disponibles</p>
          </div>
        </div>
      </div>

      <!-- Gráfico de atrasos por mes -->
      <div class="bg-white rounded-2xl shadow p-6">
        <h2 class="text-base font-semibold text-gray-700 mb-1">Días de atraso por mes</h2>
        <p class="text-xs text-gray-400 mb-5">Año {{ anioActual }}</p>
        <div class="flex items-end gap-2 h-36">
          <div v-for="(val, i) in atrasosMeses" :key="i"
            class="flex-1 flex flex-col items-center gap-1">
            <span class="text-xs font-bold text-gray-700" style="min-height:1rem;">
              {{ val > 0 ? val : '' }}
            </span>
            <div class="w-full rounded-t-lg transition-all"
              :style="{
                height: val > 0 ? Math.max(8, Math.round(val / maxAtraso * 100)) + 'px' : '4px',
                backgroundColor: val > 0 ? (val >= umbralAlto ? '#dc2626' : val >= umbralMedio ? '#f59e0b' : '#3b82f6') : '#e5e7eb'
              }">
            </div>
            <span class="text-xs text-gray-400">{{ MESES_CORTOS[i] }}</span>
          </div>
        </div>
        <!-- Leyenda -->
        <div class="flex gap-4 mt-4 text-xs text-gray-500">
          <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm inline-block bg-blue-500"></span>Pocos</span>
          <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm inline-block bg-amber-400"></span>Moderado</span>
          <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm inline-block bg-red-600"></span>Alto</span>
          <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-sm inline-block bg-gray-200"></span>Sin atrasos</span>
        </div>
      </div>
    </template>

    <!-- ── ADMIN / TH ──────────────────────────────────────────────────────── -->
    <div v-if="esAdmin" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div class="bg-white rounded-xl shadow p-6 flex items-center gap-4">
        <div class="bg-blue-100 p-3 rounded-full">
          <svg class="w-6 h-6 text-[#0b5447]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>
        </div>
        <div>
          <p class="text-sm text-gray-500">Empleados Activos</p>
          <p class="text-2xl font-bold text-gray-800">{{ stats.total_activos }}</p>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6 flex items-center gap-4">
        <div class="bg-green-100 p-3 rounded-full">
          <svg class="w-6 h-6 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
          </svg>
        </div>
        <div>
          <p class="text-sm text-gray-500">Departamentos</p>
          <p class="text-2xl font-bold text-gray-800">{{ stats.por_departamento.length }}</p>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6 flex items-center gap-4">
        <div class="bg-yellow-100 p-3 rounded-full">
          <svg class="w-6 h-6 text-yellow-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
        </div>
        <div>
          <p class="text-sm text-gray-500">Permisos Pendientes</p>
          <p class="text-2xl font-bold text-gray-800">{{ stats.permisos_pendientes }}</p>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow p-6 flex items-center gap-4">
        <div class="bg-purple-100 p-3 rounded-full">
          <svg class="w-6 h-6 text-purple-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
          </svg>
        </div>
        <div>
          <p class="text-sm text-gray-500">Vacaciones Pendientes</p>
          <p class="text-2xl font-bold text-gray-800">{{ stats.vacaciones_pendientes }}</p>
        </div>
      </div>
    </div>

    <!-- SECCIÓN SUPERVISOR -->
    <template v-if="stats.es_supervisor && !stats.es_admin_th">

      <!-- Pendientes de acción -->
      <div>
        <h2 class="text-lg font-semibold text-gray-700 mb-3">Pendientes de acción</h2>
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
          <div v-for="item in pendientes" :key="item.label"
            class="bg-white rounded-xl shadow p-5 flex items-center gap-4 border-l-4"
            :class="item.valor > 0 ? 'border-red-500' : 'border-green-400'">
            <div class="p-3 rounded-full" :class="item.valor > 0 ? 'bg-red-100' : 'bg-green-100'">
              <svg class="w-6 h-6" :class="item.valor > 0 ? 'text-red-600' : 'text-green-600'"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icono"/>
              </svg>
            </div>
            <div>
              <p class="text-xs text-gray-500 leading-tight">{{ item.label }}</p>
              <p class="text-3xl font-bold" :class="item.valor > 0 ? 'text-red-600' : 'text-gray-700'">
                {{ item.valor }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Mi equipo hoy + Atrasos del mes -->
      <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">

        <!-- Estado del equipo hoy -->
        <div class="lg:col-span-2 bg-white rounded-xl shadow p-6">
          <h2 class="text-lg font-semibold text-gray-700 mb-4">
            Mi equipo hoy
            <span class="text-sm font-normal text-gray-400 ml-1">({{ stats.datos_supervisor?.total_equipo }} empleados)</span>
          </h2>
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div v-for="item in equipoHoy" :key="item.label"
              class="rounded-xl p-4 text-center" :class="item.bg">
              <p class="text-3xl font-bold" :class="item.color">{{ item.valor }}</p>
              <p class="text-xs mt-1 font-medium" :class="item.color">{{ item.label }}</p>
            </div>
          </div>
          <!-- Barra proporcional -->
          <div class="mt-4 h-3 rounded-full bg-gray-100 overflow-hidden flex">
            <div class="bg-green-500 h-full transition-all"
              :style="{ width: pct(stats.datos_supervisor?.presentes_hoy) + '%' }" />
            <div class="bg-yellow-400 h-full transition-all"
              :style="{ width: pct(stats.datos_supervisor?.con_permiso_hoy) + '%' }" />
            <div class="bg-blue-400 h-full transition-all"
              :style="{ width: pct(stats.datos_supervisor?.con_vacaciones_hoy) + '%' }" />
            <div class="bg-red-300 h-full transition-all"
              :style="{ width: pct(ausentesHoy) + '%' }" />
          </div>
          <div class="flex gap-4 mt-2 text-xs text-gray-400">
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500 inline-block"/>Presentes</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-yellow-400 inline-block"/>Permiso</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-400 inline-block"/>Vacaciones</span>
            <span class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-300 inline-block"/>Sin marcar</span>
          </div>
        </div>

        <!-- Atrasos del mes -->
        <div class="bg-white rounded-xl shadow p-6 flex flex-col items-center justify-center text-center">
          <div class="bg-orange-100 p-4 rounded-full mb-3">
            <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <p class="text-5xl font-bold text-orange-600">{{ stats.datos_supervisor?.atrasos_mes ?? 0 }}</p>
          <p class="text-sm text-gray-500 mt-2">Días con atraso<br>en el equipo este mes</p>
        </div>

      </div>
    </template>

    <!-- Gráfico atrasos por unidad (solo roles TH) -->
    <div v-if="esThRol && atrasosData" class="bg-white rounded-xl shadow p-6">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h2 class="text-base font-semibold text-gray-700">
            Atrasos por unidad organizacional — {{ anioActual }}
          </h2>
          <p v-if="vistaHijos" class="text-sm text-gray-500 mt-0.5">
            {{ vistaHijos.padre.nombre }} — detalle por área
          </p>
          <p v-else class="text-sm text-gray-400 mt-0.5">
            Haz clic en una coordinación para ver el detalle de sus áreas
          </p>
        </div>
        <button v-if="vistaHijos" @click="volverAPadres"
          class="text-sm px-3 py-1.5 rounded-lg border border-gray-300 hover:bg-gray-50 transition">
          ← Volver
        </button>
      </div>
      <div class="flex gap-6">
        <!-- Canvas -->
        <div class="flex-1 relative h-72">
          <canvas ref="chartCanvas"></canvas>
        </div>
        <!-- Panel de checkboxes -->
        <div class="w-52 flex-shrink-0 border-l pl-4 flex flex-col gap-1.5 overflow-y-auto max-h-72">
          <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">
            {{ vistaHijos ? 'Áreas' : 'Unidades' }}
          </p>
          <label v-for="u in listaCheckboxes" :key="u.id_depto"
            class="flex items-center gap-2 cursor-pointer group">
            <input type="checkbox"
              :checked="seleccionActual.has(u.id_depto)"
              @change="toggleUnidad(u.id_depto)"
              class="rounded cursor-pointer accent-[#0b5447]" />
            <span class="w-3 h-3 rounded-sm flex-shrink-0"
              :style="{ backgroundColor: colorOriginal(u.id_depto) }"></span>
            <span class="text-xs text-gray-700 leading-tight group-hover:text-gray-900">{{ u.nombre }}</span>
          </label>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { Chart, BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend } from 'chart.js'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend)

const auth           = useAuthStore()
const esAdmin        = computed(() => auth.tieneRol('ADMINISTRADOR') || auth.tieneRol('TALENTO HUMANO'))
const esThRol        = computed(() => auth.tieneRol('TALENTO HUMANO') || auth.tieneRol('TH NOMINA') || auth.tieneRol('TH ACCIONES PERSONAL'))
const esEmpleadoSolo = computed(() => !stats.value.es_supervisor && !stats.value.es_admin_th)

// ── Chart.js ─────────────────────────────────────────────────────────────────
const chartCanvas          = ref(null)
const chartInstance        = ref(null)
const atrasosData          = ref(null)
const vistaHijos           = ref(null)
const seleccionados        = ref(new Set())
const seleccionadosHijos   = ref(new Set())
const unidadesRenderizadas = ref([])

const COLORES = ['#0b5447','#2563eb','#9333ea','#d97706','#dc2626','#059669','#db2777','#0891b2','#65a30d','#7c3aed']

const listaCheckboxes = computed(() =>
  vistaHijos.value ? vistaHijos.value.hijos : (atrasosData.value?.unidades ?? [])
)
const seleccionActual = computed(() =>
  vistaHijos.value ? seleccionadosHijos.value : seleccionados.value
)

function colorOriginal(idDepto) {
  const lista = vistaHijos.value ? vistaHijos.value.hijos : (atrasosData.value?.unidades ?? [])
  const idx = lista.findIndex(u => u.id_depto === idDepto)
  return COLORES[idx % COLORES.length]
}

function toggleUnidad(idDepto) {
  const enHijos = !!vistaHijos.value
  const set = enHijos ? seleccionadosHijos.value : seleccionados.value
  if (set.has(idDepto)) {
    set.delete(idDepto)
  } else {
    set.add(idDepto)
  }
  if (enHijos) {
    seleccionadosHijos.value = new Set(set)
    mostrarHijos()
  } else {
    seleccionados.value = new Set(set)
    mostrarPadres()
  }
}

function destruirChart() {
  if (chartInstance.value) { chartInstance.value.destroy(); chartInstance.value = null }
}

function renderChart(labels, datasets) {
  destruirChart()
  chartInstance.value = new Chart(chartCanvas.value, {
    type: 'bar',
    data: { labels, datasets },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: ctx => `${ctx.dataset.label}: ${ctx.parsed.y} días` } },
      },
      scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
      onClick: (_, elements) => {
        if (!elements.length || vistaHijos.value) return
        const padre = unidadesRenderizadas.value[elements[0].datasetIndex]
        if (!padre?.tiene_hijos) return
        const hijos = atrasosData.value.hijos[padre.id_depto]
        if (!hijos?.length) return
        seleccionadosHijos.value = new Set(hijos.map(h => h.id_depto))
        vistaHijos.value = { padre, hijos }
        mostrarHijos()
      },
    },
  })
}

function mostrarPadres() {
  const { meses, unidades } = atrasosData.value
  const filtradas = unidades.filter(u => seleccionados.value.has(u.id_depto))
  unidadesRenderizadas.value = filtradas
  renderChart(meses, filtradas.map(p => ({
    label: p.nombre,
    data: p.datos,
    backgroundColor: colorOriginal(p.id_depto),
    borderRadius: 4,
  })))
}

function mostrarHijos() {
  const { meses } = atrasosData.value
  const filtrados = vistaHijos.value.hijos.filter(h => seleccionadosHijos.value.has(h.id_depto))
  renderChart(meses, filtrados.map(h => ({
    label: h.nombre,
    data: h.datos,
    backgroundColor: colorOriginal(h.id_depto),
    borderRadius: 4,
  })))
}

function volverAPadres() {
  vistaHijos.value = null
  seleccionadosHijos.value = new Set()
  mostrarPadres()
}

const anioActual = new Date().getFullYear()
const MESES_CORTOS = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic']

const stats = ref({
  total_activos: 0,
  por_departamento: [],
  permisos_pendientes: 0,
  vacaciones_pendientes: 0,
  es_supervisor: false,
  es_admin_th: false,
  datos_supervisor: null,
  datos_empleado: null,
})

const atrasosMeses = computed(() => stats.value.datos_empleado?.atrasos_por_mes ?? Array(12).fill(0))
const maxAtraso    = computed(() => Math.max(1, ...atrasosMeses.value))
const umbralAlto   = computed(() => Math.ceil(maxAtraso.value * 0.66))
const umbralMedio  = computed(() => Math.ceil(maxAtraso.value * 0.33))

const ICONO_PERMISO    = 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'
const ICONO_VACACIONES = 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z'
const ICONO_HE         = 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'
const ICONO_MATERIAL   = 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'

const pendientes = computed(() => [
  { label: 'Permisos por aprobar',      valor: stats.value.permisos_pendientes,                          icono: ICONO_PERMISO },
  { label: 'Vacaciones por aprobar',    valor: stats.value.vacaciones_pendientes,                        icono: ICONO_VACACIONES },
  { label: 'Horas extras por aprobar',  valor: stats.value.datos_supervisor?.he_pendientes ?? 0,         icono: ICONO_HE },
  { label: 'Materiales por despachar',  valor: stats.value.datos_supervisor?.materiales_pendientes ?? 0, icono: ICONO_MATERIAL },
])

const ausentesHoy = computed(() => {
  const d = stats.value.datos_supervisor
  if (!d) return 0
  return Math.max(0, d.total_equipo - d.presentes_hoy - d.con_permiso_hoy - d.con_vacaciones_hoy)
})

const equipoHoy = computed(() => {
  const d = stats.value.datos_supervisor
  return [
    { label: 'Presentes',   valor: d?.presentes_hoy      ?? 0, bg: 'bg-green-50',  color: 'text-green-700' },
    { label: 'Con permiso', valor: d?.con_permiso_hoy    ?? 0, bg: 'bg-yellow-50', color: 'text-yellow-700' },
    { label: 'Vacaciones',  valor: d?.con_vacaciones_hoy ?? 0, bg: 'bg-blue-50',   color: 'text-blue-700' },
    { label: 'Sin marcar',  valor: ausentesHoy.value,          bg: 'bg-red-50',    color: 'text-red-700' },
  ]
})

function pct(val) {
  const total = stats.value.datos_supervisor?.total_equipo || 1
  return Math.min(100, Math.round(((val ?? 0) / total) * 100))
}

onMounted(async () => {
  const { data } = await api.get('/dashboard')
  stats.value = data

  if (esThRol.value) {
    try {
      const { data: dataAtrasos } = await api.get('/dashboard/atrasos-coordinacion')
      atrasosData.value = dataAtrasos
      seleccionados.value = new Set()
      await nextTick()
      mostrarPadres()
    } catch {}
  }
})
</script>
