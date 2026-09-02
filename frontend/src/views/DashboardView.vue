<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Datos Informativos</h1>

    <!-- ── EMPLEADO SIN ROL ESPECIAL ─────────────────────────────────────── -->
    <template v-if="esEmpleadoSolo">
      <!-- Tarjetas — 3 en una sola fila -->
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2" :class="proximoPeriodo ? 'lg:grid-cols-3' : 'lg:grid-cols-2'">

        <!-- Permisos pendientes -->
        <div class="rounded-xl p-4 flex items-center gap-3 text-white"
          style="background: linear-gradient(135deg,#1e3a5f,#2d5f8a);">
          <div class="bg-white/20 p-2.5 rounded-xl flex-shrink-0">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
          </div>
          <div>
            <p class="text-xs text-white/70 font-medium uppercase tracking-wide">Permisos pendientes</p>
            <p class="text-3xl font-extrabold leading-none mt-0.5">{{ stats.permisos_pendientes }}</p>
            <p class="text-xs text-white/60 mt-0.5">{{ stats.permisos_pendientes === 1 ? 'solicitud en espera' : 'solicitudes en espera' }}</p>
          </div>
        </div>

        <!-- Saldo de vacaciones -->
        <div class="rounded-xl p-4 flex items-center gap-3 text-white"
          :style="saldoRealNegativo
            ? 'background: linear-gradient(135deg,#a13a26,#c9573e);'
            : 'background: linear-gradient(135deg,#0b5447,#1a8a6f);'">
          <div class="bg-white/20 p-2.5 rounded-xl flex-shrink-0">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 3v1m0 16v1m8.66-9h-1M4.34 12h-1m15.07-6.07l-.71.71M6.34 17.66l-.71.71M17.66 17.66l-.71-.71M6.34 6.34l-.71-.71M12 7a5 5 0 100 10A5 5 0 0012 7z"/>
            </svg>
          </div>
          <div>
            <p class="text-xs text-white/70 font-medium uppercase tracking-wide">Saldo de vacaciones</p>
            <p class="text-3xl font-extrabold leading-none mt-0.5">
              {{ saldoRealNegativo ? stats.datos_empleado.saldo_vacaciones_real : (stats.datos_empleado?.saldo_vacaciones ?? 0) }}
            </p>
            <p class="text-xs text-white/60 mt-0.5">
              {{ saldoRealNegativo ? 'días — saldo en negativo, requiere atención' : 'días disponibles' }}
            </p>
            <span v-if="(stats.datos_empleado?.dias_adicionales_antiguedad ?? 0) > 0"
              class="inline-block bg-white/20 text-white text-xs px-2 py-0.5 rounded-full mt-1">
              +{{ stats.datos_empleado.dias_adicionales_antiguedad }} días/año por antigüedad
            </span>
          </div>
        </div>

        <!-- Próximo período / Vacaciones en curso -->
        <div v-if="proximoPeriodo" class="rounded-xl p-4 text-white"
          :style="enCurso
            ? 'background: linear-gradient(135deg,#92400e,#d97706)'
            : 'background: linear-gradient(135deg,#1e3a5f,#2d5f8a)'">
          <div class="flex items-center gap-2 mb-2">
            <div class="bg-white/20 p-2 rounded-lg flex-shrink-0">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
            </div>
            <div class="flex-1 min-w-0">
              <p class="text-xs text-white/70 uppercase tracking-wide truncate">
                {{ enCurso ? 'Vacaciones en curso' : 'Próximo período de vacaciones' }}
              </p>
              <p class="text-sm font-bold leading-tight">Per. {{ proximoPeriodo.numero_periodo }} · {{ proximoPeriodo.dias }} días</p>
            </div>
            <span v-if="enCurso" class="bg-white/30 text-white text-xs font-bold px-2 py-0.5 rounded-full flex-shrink-0">EN CURSO</span>
            <div v-else class="text-right flex-shrink-0">
              <p class="text-2xl font-extrabold leading-none">{{ diasParaVacaciones }}</p>
              <p class="text-xs text-white/60 leading-tight">días</p>
            </div>
          </div>
          <div class="flex gap-4 text-xs border-t border-white/20 pt-2">
            <div>
              <p class="text-white/60">Desde</p>
              <p class="font-medium mt-0.5">{{ formatFecha(proximoPeriodo.fecha_inicial) }}</p>
            </div>
            <div>
              <p class="text-white/60">Hasta</p>
              <p class="font-medium mt-0.5">{{ formatFecha(proximoPeriodo.fecha_final) }}</p>
            </div>
          </div>
        </div>

      </div>

    </template>

    <!-- Alerta: procesar:cuadre no corrió en las últimas ~26h (cron schedule:run caído
         o nunca configurado en el servidor) — solo visible para Admin/TH -->
    <div v-if="esAdmin && stats.cuadre_alerta?.atrasado"
      class="rounded-xl p-4 flex items-start gap-3 bg-amber-50 border border-amber-300">
      <svg class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
      </svg>
      <div>
        <p class="text-sm font-semibold text-amber-800">El cuadre de marcaciones no se ha procesado recientemente</p>
        <p class="text-xs text-amber-700 mt-0.5">
          {{ stats.cuadre_alerta.ultimo_at ? `Última corrida: ${formatFechaHora(stats.cuadre_alerta.ultimo_at)}` : 'No hay registro de ninguna corrida.' }}
          Revisar que el cron <code class="bg-amber-100 px-1 rounded">schedule:run</code> esté activo en el servidor.
        </p>
      </div>
    </div>

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
      <!-- Vacaciones próximas del equipo -->
      <div v-if="(stats.datos_supervisor?.vacaciones_proximas_count ?? 0) > 0"
        class="bg-white rounded-xl shadow p-6">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="bg-teal-100 p-3 rounded-xl">
              <svg class="w-6 h-6 text-teal-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
              </svg>
            </div>
            <div>
              <p class="font-semibold text-gray-700">Vacaciones próximas del equipo</p>
              <p class="text-xs text-gray-400">En curso o en los próximos 60 días</p>
            </div>
          </div>
          <div class="flex items-center gap-4">
            <span class="text-3xl font-bold text-teal-700">
              {{ stats.datos_supervisor.vacaciones_proximas_count }}
            </span>
            <button @click="mostrarVacProximas = !mostrarVacProximas"
              class="text-sm text-teal-700 border border-teal-200 px-3 py-1 rounded-lg hover:bg-teal-50 transition">
              {{ mostrarVacProximas ? 'Ocultar' : 'Ver quiénes' }}
            </button>
          </div>
        </div>
        <div v-if="mostrarVacProximas" class="mt-4 border-t pt-3 space-y-1">
          <div v-for="v in stats.datos_supervisor.vacaciones_proximas"
            :key="v.nombre + v.fecha_inicial"
            class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
            <div>
              <p class="text-sm font-medium text-gray-800">{{ v.nombre }}</p>
              <p class="text-xs text-gray-400 mt-0.5">
                {{ formatFecha(v.fecha_inicial) }} — {{ formatFecha(v.fecha_final) }} · {{ v.dias }} días
              </p>
            </div>
            <span :class="v.en_curso ? 'bg-amber-100 text-amber-700' : 'bg-teal-100 text-teal-700'"
              class="text-xs px-2 py-0.5 rounded-full font-medium flex-shrink-0 ml-4">
              {{ v.en_curso ? 'En curso' : 'Próximo' }}
            </span>
          </div>
        </div>
      </div>

    </template>

    <!-- Gráfico personal: mis trámites personales no justificados -->
    <div v-if="atrasosPersonales" class="bg-white rounded-xl shadow p-6">
      <h2 class="text-base font-semibold text-gray-700 mb-1">Mis trámites personales no justificados por mes</h2>
      <p class="text-xs text-gray-400 mb-4">Año {{ anioActual }}</p>
      <div class="relative h-64">
        <canvas ref="chartCanvas"></canvas>
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

const esEmpleadoSolo = computed(() => !stats.value.es_supervisor && !stats.value.es_admin_th)

// ── Chart.js ─────────────────────────────────────────────────────────────────
const chartCanvas       = ref(null)
const chartInstance     = ref(null)
const atrasosPersonales = ref(null)

function destruirChart() {
  if (chartInstance.value) { chartInstance.value.destroy(); chartInstance.value = null }
}

function renderChartPersonal(meses, datos) {
  destruirChart()
  chartInstance.value = new Chart(chartCanvas.value, {
    type: 'bar',
    data: {
      labels: meses,
      datasets: [{
        label: 'Días sin justificar',
        data: datos,
        backgroundColor: datos.map(v => v === 0 ? '#d1fae5' : v <= 2 ? '#fbbf24' : '#dc2626'),
        borderRadius: 4,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: ctx => `${ctx.parsed.y} día(s) sin justificar` } },
      },
      scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
    },
  })
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
  cuadre_alerta: null,
})


const ICONO_PERMISO    = 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'
const ICONO_VACACIONES = 'M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z'
const ICONO_HE         = 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'

const pendientes = computed(() => [
  { label: 'Permisos por aprobar',      valor: stats.value.permisos_pendientes,  icono: ICONO_PERMISO },
  { label: 'Vacaciones por aprobar',    valor: stats.value.vacaciones_pendientes, icono: ICONO_VACACIONES },
  // { label: 'Horas extras por aprobar',  valor: stats.value.datos_supervisor?.he_pendientes ?? 0, icono: ICONO_HE },
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

const mostrarVacProximas = ref(false)
// Nombramiento Definitivo puede quedar con saldo real negativo (informe TH favorable) —
// mismo criterio que VacacionesView.vue: se muestra en rojo en vez de esconderlo como 0.
const saldoRealNegativo = computed(() => {
  const d = stats.value.datos_empleado
  return !!d && d.modalidad_laboral === 'Nombramiento Definitivo' && (d.saldo_vacaciones_real ?? 0) < 0
})
const proximoPeriodo     = computed(() => stats.value.datos_empleado?.proximo_periodo ?? null)
const enCurso            = computed(() => proximoPeriodo.value?.en_curso === true)
const diasParaVacaciones = computed(() => {
  const p = proximoPeriodo.value
  if (!p || p.en_curso) return 0
  const [iy, im, id] = p.fecha_inicial.split('-').map(Number)
  const hoy = new Date()
  const inicio = new Date(iy, im - 1, id)
  return Math.max(0, Math.ceil((inicio - hoy) / 86400000))
})

function formatFecha(fecha) {
  if (!fecha) return '—'
  const meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre']
  const [, m, d] = fecha.split('-')
  return `${parseInt(d)} de ${meses[parseInt(m) - 1]}`
}

// Fecha+hora tipo "2026-09-02 23:55:00" (ULTIMO_CUADRE_PROCESADO) — formatFecha() de
// arriba solo sirve para fechas puras (fecha_inicial/fecha_final), pierde la hora.
function formatFechaHora(fechaHora) {
  if (!fechaHora) return '—'
  const [fecha, hora] = fechaHora.split(' ')
  const [y, m, d] = fecha.split('-')
  return `${d}/${m}/${y} ${hora ? hora.substring(0, 5) : ''}`
}

onMounted(async () => {
  const { data } = await api.get('/dashboard')
  stats.value = data

  try {
    const { data: dataAtrasos } = await api.get('/dashboard/atrasos-coordinacion')
    atrasosPersonales.value = dataAtrasos
    await nextTick()
    renderChartPersonal(dataAtrasos.meses, dataAtrasos.datos)
  } catch {}
})
</script>
