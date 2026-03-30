<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-800">Planificación de Vacaciones</h1>
    </div>

    <!-- Tabs (solo supervisor/admin) -->
    <div v-if="esSupervisorOAdmin" class="flex border-b">
      <button @click="tabActivo = 'mia'"
        :class="tabActivo === 'mia' ? 'border-b-2 border-[#0b5447] text-[#0b5447] font-medium' : 'text-gray-500 hover:text-gray-700'"
        class="px-6 py-3 text-sm transition">Mi Planificación</button>
      <button @click="tabActivo = 'equipo'"
        :class="tabActivo === 'equipo' ? 'border-b-2 border-[#0b5447] text-[#0b5447] font-medium' : 'text-gray-500 hover:text-gray-700'"
        class="px-6 py-3 text-sm transition">Planificaciones del Equipo</button>
    </div>

    <!-- ── VISTA EMPLEADO ────────────────────────────────────────────────── -->
    <template v-if="!esSupervisorOAdmin || tabActivo === 'mia'">
      <!-- Sin período activo -->
      <div v-if="!periodoActivo" class="bg-white rounded-xl shadow p-8 text-center text-gray-400">
        <p class="text-lg font-medium">No hay un período de planificación activo en este momento.</p>
        <p class="text-sm mt-1">Talento Humano habilitará el período cuando corresponda.</p>
      </div>

      <!-- Con período activo y ya tiene planificación -->
      <template v-else-if="miPlanificacion">
        <div class="bg-white rounded-xl shadow p-6 space-y-4">
          <div class="flex items-center justify-between">
            <h2 class="font-semibold text-gray-700">Mi planificación {{ miPlanificacion.anio }}</h2>
            <span :class="colorEstado(miPlanificacion.estado)"
              class="px-3 py-1 rounded-full text-xs font-medium">
              {{ miPlanificacion.estado }}
            </span>
          </div>
          <div class="text-sm text-gray-500">
            Total planificado: <span class="font-semibold text-gray-700">{{ miPlanificacion.total_dias_planificados }} días</span>
          </div>
          <div v-if="miPlanificacion.observacion" class="bg-red-50 border border-red-200 rounded-lg p-3 text-sm text-red-700">
            <strong>Observación:</strong> {{ miPlanificacion.observacion }}
          </div>
          <table class="w-full text-sm mt-2">
            <thead class="bg-gray-50 border-b">
              <tr>
                <th class="text-left px-4 py-2 text-gray-600">Período</th>
                <th class="text-left px-4 py-2 text-gray-600">Fecha Inicial</th>
                <th class="text-left px-4 py-2 text-gray-600">Fecha Final</th>
                <th class="text-center px-4 py-2 text-gray-600">Días</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in miPlanificacion.periodos" :key="p.id"
                class="border-b">
                <td class="px-4 py-2 text-gray-600">Período {{ p.numero_periodo }}</td>
                <td class="px-4 py-2">{{ p.fecha_inicial || '—' }}</td>
                <td class="px-4 py-2">{{ p.fecha_final || '—' }}</td>
                <td class="px-4 py-2 text-center font-medium">{{ p.dias_calculados ?? '—' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <!-- Con período activo y sin planificación -->
      <template v-else-if="periodoActivo">
        <div class="bg-white rounded-xl shadow p-6 space-y-2">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="font-semibold text-gray-700">Planificar vacaciones {{ periodoActivo.anio }}</h2>
              <p class="text-sm text-gray-400 mt-0.5">
                Período habilitado: {{ periodoActivo.fecha_inicio }} al {{ periodoActivo.fecha_fin }}
              </p>
            </div>
            <button v-if="puedeplanificar" @click="modalPlanificar = true"
              class="bg-[#0b5447] text-white px-4 py-2 rounded-lg hover:bg-[#00372e] text-sm font-medium">
              Planificar mis vacaciones
            </button>
          </div>
          <!-- Saldo disponible (solo informativo) -->
          <div class="bg-[#f0faf8] border border-[#95d0c7] rounded-lg p-3 text-sm">
            Saldo disponible (informativo): <span class="font-bold text-[#0b5447]">{{ saldo }} días</span>
          </div>
          <!-- Sin meses suficientes -->
          <div v-if="!puedeplanificar" class="bg-orange-50 border border-orange-200 rounded-lg p-3 text-sm text-orange-700">
            No puede planificar vacaciones hasta completar 11 meses de servicio (actualmente: {{ mesesServicio }} meses).
            Los permisos con cargo a vacaciones están disponibles mientras tanto.
          </div>
        </div>
      </template>
    </template>

    <!-- ── VISTA SUPERVISOR / ADMIN ──────────────────────────────────────── -->
    <template v-if="esSupervisorOAdmin && tabActivo === 'equipo'">
      <!-- Filtros -->
      <div class="bg-white rounded-xl shadow p-4 flex flex-wrap gap-3 items-end">
        <div>
          <label class="block text-xs text-gray-500 mb-1">Año</label>
          <input v-model.number="filtros.anio" type="number"
            class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186] w-28" />
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Estado</label>
          <select v-model="filtros.estado"
            class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
            <option value="">Todos</option>
            <option value="PENDIENTE">Pendiente</option>
            <option value="APROBADO">Aprobado</option>
            <option value="NEGADO">Negado</option>
            <option value="ELIMINADO">Eliminado</option>
            <option value="REPLANIFICADO">Replanificado</option>
          </select>
        </div>
        <button @click="cargarPlanificaciones" :disabled="cargando"
          class="bg-[#0b5447] text-white px-5 py-2 rounded-lg text-sm hover:bg-[#00372e] disabled:opacity-50">
          {{ cargando ? 'Buscando...' : 'Buscar' }}
        </button>
      </div>

      <!-- Lista planificaciones -->
      <div class="space-y-4">
        <div v-if="cargando" class="text-center py-8 text-gray-400">Cargando...</div>
        <div v-else-if="planificaciones.length === 0" class="bg-white rounded-xl shadow p-8 text-center text-gray-400">
          No hay planificaciones para los filtros seleccionados
        </div>
        <div v-for="plan in planificaciones" :key="plan.id"
          class="bg-white rounded-xl shadow p-5 space-y-3">
          <!-- Encabezado -->
          <div class="flex items-start justify-between">
            <div>
              <p class="font-semibold text-gray-800">
                {{ plan.empleado?.apellido_emp }}, {{ plan.empleado?.nombre_emp }}
              </p>
              <p class="text-xs text-gray-400">
                {{ plan.empleado?.departamento?.nombre_depto }} · Año {{ plan.anio }} ·
                {{ plan.total_dias_planificados }} días planificados
              </p>
            </div>
            <span :class="colorEstado(plan.estado)" class="px-2 py-1 rounded-full text-xs font-medium">
              {{ plan.estado }}
            </span>
          </div>

          <!-- Períodos -->
          <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
            <div v-for="p in plan.periodos.filter(x => x.fecha_inicial)" :key="p.id"
              class="bg-gray-50 rounded-lg p-2 text-xs">
              <p class="text-gray-500 font-medium">Período {{ p.numero_periodo }}</p>
              <p class="text-gray-700">{{ p.fecha_inicial }} → {{ p.fecha_final }}</p>
              <p class="text-[#0b5447] font-semibold">{{ p.dias_calculados }} días</p>
            </div>
          </div>

          <!-- Observación (negado/eliminado) -->
          <div v-if="plan.observacion" class="bg-red-50 border border-red-200 rounded p-2 text-xs text-red-700">
            {{ plan.observacion }}
          </div>

          <!-- Acciones -->
          <div v-if="plan.estado === 'PENDIENTE'" class="flex gap-2">
            <button @click="aprobar(plan.id)"
              class="bg-green-600 text-white px-3 py-1.5 rounded-lg text-xs hover:bg-green-700">Aprobar</button>
            <button @click="abrirModalNegar(plan)"
              class="bg-red-500 text-white px-3 py-1.5 rounded-lg text-xs hover:bg-red-600">Negar</button>
            <button @click="abrirModalEliminar(plan)"
              class="bg-gray-500 text-white px-3 py-1.5 rounded-lg text-xs hover:bg-gray-600">Eliminar</button>
          </div>
          <div v-if="plan.estado === 'APROBADO' && plan.replanificada === 'NO'" class="flex gap-2">
            <button @click="abrirModalReplanificar(plan)"
              class="bg-[#0b5447] text-white px-3 py-1.5 rounded-lg text-xs hover:bg-[#00372e]">
              Replanificar
            </button>
          </div>
        </div>
      </div>
    </template>

    <!-- ── MODAL PLANIFICAR (empleado) ───────────────────────────────────── -->
    <div v-if="modalPlanificar" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-2xl space-y-4">
        <h2 class="text-lg font-semibold text-gray-700">
          Planificar vacaciones {{ periodoActivo?.anio }}
        </h2>

        <!-- Contador 30 días + saldo informativo -->
        <div class="bg-gray-50 rounded-lg p-3 text-sm flex items-center justify-between">
          <span class="text-gray-500">Saldo disponible (informativo): <strong class="text-[#0b5447]">{{ saldo }} días</strong></span>
          <span :class="totalPlanificado === 30 ? 'text-green-600 font-bold' : totalPlanificado > 30 ? 'text-red-600 font-bold' : 'text-gray-700 font-bold'">
            {{ totalPlanificado }} / 30 días
          </span>
        </div>
        <p v-if="totalPlanificado !== 30 && totalPlanificado > 0" class="text-xs text-orange-600">
          La planificación debe sumar exactamente 30 días.
        </p>

        <!-- 4 períodos -->
        <div class="space-y-3">
          <div v-for="(p, i) in formPeriodos" :key="i"
            class="grid grid-cols-3 gap-3 items-end border rounded-lg p-3">
            <div>
              <label class="block text-xs text-gray-500 mb-1">Período {{ i + 1 }} — Fecha inicial</label>
              <input v-model="p.fecha_inicial" type="date" @change="calcularDiasPeriodo(i)"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">Fecha final</label>
              <input v-model="p.fecha_final" type="date" @change="calcularDiasPeriodo(i)"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
            </div>
            <div class="text-center">
              <p class="text-xs text-gray-500 mb-1">Días</p>
              <p class="font-semibold text-[#0b5447] text-lg">{{ p.dias ?? '—' }}</p>
            </div>
          </div>
        </div>

        <div v-if="errorPlanificar" class="text-red-600 text-sm bg-red-50 rounded p-2">{{ errorPlanificar }}</div>

        <div class="flex justify-end gap-3 pt-2">
          <button @click="modalPlanificar = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
          <button @click="guardarPlanificacion" :disabled="guardandoPlan || totalPlanificado !== 30"
            class="px-4 py-2 rounded-lg bg-[#0b5447] text-white text-sm hover:bg-[#00372e] disabled:opacity-50">
            {{ guardandoPlan ? 'Enviando...' : 'Enviar planificación' }}
          </button>
        </div>
      </div>
    </div>

    <!-- ── MODAL NEGAR ────────────────────────────────────────────────────── -->
    <div v-if="modalNegar" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-md space-y-4">
        <h2 class="text-lg font-semibold text-gray-700">Negar Planificación</h2>
        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Motivo *</label>
          <textarea v-model="motivoAccion" rows="3" maxlength="250"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]"></textarea>
        </div>
        <div v-if="errorAccion" class="text-red-600 text-sm bg-red-50 rounded p-2">{{ errorAccion }}</div>
        <div class="flex justify-end gap-3">
          <button @click="modalNegar = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
          <button @click="confirmarNegar"
            class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm hover:bg-red-700">Confirmar</button>
        </div>
      </div>
    </div>

    <!-- ── MODAL ELIMINAR ─────────────────────────────────────────────────── -->
    <div v-if="modalEliminar" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-md space-y-4">
        <h2 class="text-lg font-semibold text-gray-700">Eliminar Planificación</h2>
        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Motivo *</label>
          <textarea v-model="motivoAccion" rows="3" maxlength="250"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]"></textarea>
        </div>
        <div v-if="errorAccion" class="text-red-600 text-sm bg-red-50 rounded p-2">{{ errorAccion }}</div>
        <div class="flex justify-end gap-3">
          <button @click="modalEliminar = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
          <button @click="confirmarEliminar"
            class="px-4 py-2 rounded-lg bg-gray-600 text-white text-sm hover:bg-gray-700">Confirmar</button>
        </div>
      </div>
    </div>

    <!-- ── MODAL REPLANIFICAR ─────────────────────────────────────────────── -->
    <div v-if="modalReplanificar" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-2xl space-y-4">
        <h2 class="text-lg font-semibold text-gray-700">
          Replanificar — {{ planSeleccionada?.empleado?.apellido_emp }},
          {{ planSeleccionada?.empleado?.nombre_emp }}
        </h2>
        <p class="text-xs text-orange-600 bg-orange-50 rounded p-2">
          Esta acción solo puede realizarse una vez. Una vez replanificado no podrá volver a modificarse.
        </p>

        <!-- Contador replanificación -->
        <div class="bg-gray-50 rounded-lg p-3 text-sm flex items-center justify-between">
          <span class="text-gray-500">La replanificación debe sumar exactamente 30 días</span>
          <span :class="totalReplan === 30 ? 'text-green-600 font-bold' : totalReplan > 30 ? 'text-red-600 font-bold' : 'text-gray-700 font-bold'">
            {{ totalReplan }} / 30 días
          </span>
        </div>

        <!-- Períodos replanificación -->
        <div class="space-y-3">
          <div v-for="(p, i) in formReplan" :key="i"
            class="grid grid-cols-3 gap-3 items-end border rounded-lg p-3">
            <div>
              <label class="block text-xs text-gray-500 mb-1">Período {{ i + 1 }} — Fecha inicial</label>
              <input v-model="p.fecha_inicial" type="date" @change="calcularDiasReplan(i)"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
            </div>
            <div>
              <label class="block text-xs text-gray-500 mb-1">Fecha final</label>
              <input v-model="p.fecha_final" type="date" @change="calcularDiasReplan(i)"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
            </div>
            <div class="text-center">
              <p class="text-xs text-gray-500 mb-1">Días</p>
              <p class="font-semibold text-[#0b5447] text-lg">{{ p.dias ?? '—' }}</p>
            </div>
          </div>
        </div>

        <div v-if="errorAccion" class="text-red-600 text-sm bg-red-50 rounded p-2">{{ errorAccion }}</div>
        <div class="flex justify-end gap-3 pt-2">
          <button @click="modalReplanificar = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
          <button @click="confirmarReplanificar" :disabled="guardandoReplan || totalReplan !== 30"
            class="px-4 py-2 rounded-lg bg-[#0b5447] text-white text-sm hover:bg-[#00372e] disabled:opacity-50">
            {{ guardandoReplan ? 'Guardando...' : 'Confirmar replanificación' }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

// Estado general
const cargando        = ref(false)
const periodoActivo   = ref(null)
const miPlanificacion = ref(null)
const saldo           = ref(0)
const mesesServicio   = ref(0)
const puedeplanificar = ref(true)
const planificaciones = ref([])
const miRol           = ref({ es_supervisor: false, es_admin_th: false })
const tabActivo       = ref('mia')

const esSupervisorOAdmin = computed(() => miRol.value.es_supervisor || miRol.value.es_admin_th)

const filtros = ref({ anio: new Date().getFullYear(), estado: 'PENDIENTE' })

// Modal planificar (empleado)
const modalPlanificar  = ref(false)
const guardandoPlan    = ref(false)
const errorPlanificar  = ref('')
const formPeriodos     = ref(Array.from({ length: 4 }, () => ({ fecha_inicial: '', fecha_final: '', dias: null })))

const totalPlanificado = computed(() =>
  formPeriodos.value.reduce((sum, p) => sum + (p.dias ?? 0), 0)
)

// Modal acciones supervisor
const modalNegar       = ref(false)
const modalEliminar    = ref(false)
const modalReplanificar= ref(false)
const planSeleccionada = ref(null)
const motivoAccion     = ref('')
const errorAccion      = ref('')
const guardandoReplan  = ref(false)
const formReplan       = ref(Array.from({ length: 4 }, () => ({ fecha_inicial: '', fecha_final: '', dias: null })))

const totalReplan = computed(() =>
  formReplan.value.reduce((sum, p) => sum + (p.dias ?? 0), 0)
)

// ── Helpers ────────────────────────────────────────────────────────────────

const colorEstado = (estado) => {
  const m = {
    PENDIENTE:     'bg-yellow-100 text-yellow-700',
    APROBADO:      'bg-green-100 text-green-700',
    NEGADO:        'bg-red-100 text-red-700',
    ELIMINADO:     'bg-gray-100 text-gray-600',
    REPLANIFICADO: 'bg-blue-100 text-[#0b5447]',
  }
  return m[estado] || 'bg-gray-100 text-gray-600'
}

const diasEntreFechas = (desde, hasta) => {
  if (!desde || !hasta) return null
  const d1 = new Date(desde)
  const d2 = new Date(hasta)
  if (d2 < d1) return null
  return Math.round((d2 - d1) / 86400000) + 1
}

const calcularDiasPeriodo = (i) => {
  const p = formPeriodos.value[i]
  p.dias = diasEntreFechas(p.fecha_inicial, p.fecha_final)
}

const calcularDiasReplan = (i) => {
  const p = formReplan.value[i]
  p.dias = diasEntreFechas(p.fecha_inicial, p.fecha_final)
}

// ── Carga inicial ──────────────────────────────────────────────────────────

const cargarMiPlanificacion = async () => {
  const { data } = await api.get('/planificacion/mi-planificacion')
  periodoActivo.value   = data.periodo
  miPlanificacion.value = data.planificacion
  saldo.value           = data.saldo
  mesesServicio.value   = data.meses_servicio ?? 0
  puedeplanificar.value = data.puede_planificar ?? true
}

const cargarPlanificaciones = async () => {
  cargando.value = true
  try {
    const params = { ...filtros.value }
    if (!params.estado) delete params.estado
    const { data } = await api.get('/planificacion', { params })
    planificaciones.value = data
  } finally {
    cargando.value = false
  }
}

// ── Acciones empleado ──────────────────────────────────────────────────────

const guardarPlanificacion = async () => {
  errorPlanificar.value = ''
  guardandoPlan.value   = true
  try {
    await api.post('/planificacion', {
      anio:     periodoActivo.value.anio,
      periodos: formPeriodos.value.map(p => ({
        fecha_inicial: p.fecha_inicial || null,
        fecha_final:   p.fecha_final   || null,
      })),
    })
    modalPlanificar.value = false
    await cargarMiPlanificacion()
  } catch (e) {
    errorPlanificar.value = e.response?.data?.message || 'Error al guardar'
  } finally {
    guardandoPlan.value = false
  }
}

// ── Acciones supervisor ────────────────────────────────────────────────────

const aprobar = async (id) => {
  if (!confirm('¿Aprobar esta planificación?')) return
  try {
    await api.patch(`/planificacion/${id}/aprobar`)
    cargarPlanificaciones()
  } catch (e) {
    alert(e.response?.data?.message || 'Error')
  }
}

const abrirModalNegar = (plan) => {
  planSeleccionada.value = plan
  motivoAccion.value     = ''
  errorAccion.value      = ''
  modalNegar.value       = true
}

const confirmarNegar = async () => {
  if (!motivoAccion.value.trim()) { errorAccion.value = 'El motivo es obligatorio'; return }
  try {
    await api.patch(`/planificacion/${planSeleccionada.value.id}/negar`, { observacion: motivoAccion.value })
    modalNegar.value = false
    cargarPlanificaciones()
  } catch (e) {
    errorAccion.value = e.response?.data?.message || 'Error'
  }
}

const abrirModalEliminar = (plan) => {
  planSeleccionada.value = plan
  motivoAccion.value     = ''
  errorAccion.value      = ''
  modalEliminar.value    = true
}

const confirmarEliminar = async () => {
  if (!motivoAccion.value.trim()) { errorAccion.value = 'El motivo es obligatorio'; return }
  try {
    await api.delete(`/planificacion/${planSeleccionada.value.id}`, { data: { observacion: motivoAccion.value } })
    modalEliminar.value = false
    cargarPlanificaciones()
  } catch (e) {
    errorAccion.value = e.response?.data?.message || 'Error'
  }
}

const abrirModalReplanificar = async (plan) => {
  planSeleccionada.value = plan
  errorAccion.value      = ''
  guardandoReplan.value  = false

  // Precargar períodos existentes
  formReplan.value = Array.from({ length: 4 }, (_, i) => {
    const p = plan.periodos[i]
    return {
      fecha_inicial: p?.fecha_inicial || '',
      fecha_final:   p?.fecha_final   || '',
      dias:          p?.dias_calculados ?? null,
    }
  })

  modalReplanificar.value = true
}

const confirmarReplanificar = async () => {
  errorAccion.value     = ''
  guardandoReplan.value = true
  try {
    await api.patch(`/planificacion/${planSeleccionada.value.id}/replanificar`, {
      periodos: formReplan.value.map(p => ({
        fecha_inicial: p.fecha_inicial || null,
        fecha_final:   p.fecha_final   || null,
      })),
    })
    modalReplanificar.value = false
    cargarPlanificaciones()
  } catch (e) {
    errorAccion.value = e.response?.data?.message || 'Error'
  } finally {
    guardandoReplan.value = false
  }
}

// ── Montaje ────────────────────────────────────────────────────────────────

onMounted(async () => {
  const { data } = await api.get('/vacaciones/mi-rol')
  miRol.value = data

  // Siempre carga la planificación propia
  await cargarMiPlanificacion()

  // Si es supervisor/admin también carga las del equipo en background
  if (esSupervisorOAdmin.value) {
    cargarPlanificaciones()
  }
})
</script>
