<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Mantenimiento Vehicular</h1>
      <button v-if="esConductor" @click="abrirCrear"
        class="text-white px-4 py-2 rounded-lg text-sm hover:opacity-90" style="background-color:#1e3a5f;">
        + Nuevo requerimiento
      </button>
    </div>

    <!-- Filtro estado -->
    <div class="flex gap-3 mb-4">
      <select v-model="filtroEstado" @change="pagina = 1" class="border rounded-lg px-3 py-2 text-sm">
        <option value="">Todos los estados</option>
        <option value="PENDIENTE">Pendiente</option>
        <option value="ORDEN_GENERADA">Orden generada</option>
        <option value="EN_TALLER">En taller</option>
        <option value="FINALIZADO">Finalizado</option>
        <option value="NEGADO">Negado</option>
      </select>
    </div>

    <!-- Lista -->
    <div class="space-y-2">
      <div v-if="!listaFiltrada.length" class="bg-white rounded-xl shadow p-8 text-center text-gray-400">
        Sin registros
      </div>

      <div v-for="m in listaPaginada" :key="m.id"
           class="bg-white rounded-xl shadow px-5 py-3 flex justify-between items-center gap-3">
        <div class="min-w-0 flex-1">
          <p class="font-semibold text-gray-800 truncate">
            {{ m.vehiculo?.placa }} — {{ m.vehiculo?.marca }} {{ m.vehiculo?.modelo }}
            <span class="text-gray-400 text-xs ml-2">#{{ m.id }}</span>
          </p>
          <p class="text-xs text-gray-500">
            {{ m.tipo }} · {{ m.conductor?.apellido_emp }} {{ m.conductor?.nombre_emp }}
            · {{ formatFecha(m.created_at) }}
            <span v-if="m.km_actual"> · {{ m.km_actual?.toLocaleString() }} km</span>
          </p>
          <p class="text-xs text-gray-500 italic mt-0.5 truncate">{{ m.descripcion }}</p>
        </div>

        <div class="flex items-center gap-2 flex-shrink-0">
          <span :class="estadoBadge(m.estado)" class="px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap">
            {{ estadoLabel(m.estado) }}
          </span>
          <button @click="abrirVer(m)"
            class="text-xs text-gray-600 hover:text-gray-900 font-medium border border-gray-300 px-3 py-1 rounded-lg">
            Ver
          </button>
          <button v-if="esTransporte && m.estado === 'PENDIENTE'" @click="abrirOrden(m)"
            class="text-xs text-blue-700 hover:text-blue-900 font-medium border border-blue-300 px-3 py-1 rounded-lg">
            Generar orden
          </button>
          <button v-if="esTransporte && m.estado === 'PENDIENTE'" @click="abrirNegar(m)"
            class="text-xs text-red-600 hover:text-red-800 font-medium border border-red-200 px-3 py-1 rounded-lg">
            Negar
          </button>
          <button v-if="esTransporte && m.estado === 'ORDEN_GENERADA'" @click="cambiarEstado(m.id, 'en_taller')"
            class="text-xs text-amber-700 hover:text-amber-900 font-medium border border-amber-300 px-3 py-1 rounded-lg">
            En taller
          </button>
          <button v-if="esTransporte && m.estado === 'EN_TALLER'" @click="abrirFinalizar(m)"
            class="text-xs text-green-700 hover:text-green-900 font-medium border border-green-300 px-3 py-1 rounded-lg">
            Finalizar
          </button>
          <button v-if="m.numero_orden" @click="verPdf(m.id)"
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
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
        <div class="flex items-center justify-between px-6 py-4 flex-shrink-0" style="background-color:#1e3a5f;">
          <h2 class="text-lg font-bold text-white">Detalle Requerimiento #{{ modalVer.m?.id }}</h2>
          <button @click="modalVer.show = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-6 overflow-y-auto">
        <template v-if="modalVer.m">
          <table class="w-full text-sm">
            <tbody>
              <tr class="border-b"><td class="py-2 font-semibold text-gray-500 w-2/5">Vehículo</td>
                <td class="py-2">{{ modalVer.m.vehiculo?.placa }} · {{ modalVer.m.vehiculo?.marca }} {{ modalVer.m.vehiculo?.modelo }}</td></tr>
              <tr class="border-b"><td class="py-2 font-semibold text-gray-500">Conductor</td>
                <td class="py-2">{{ modalVer.m.conductor?.apellido_emp }} {{ modalVer.m.conductor?.nombre_emp }}</td></tr>
              <tr class="border-b"><td class="py-2 font-semibold text-gray-500">Tipo</td>
                <td class="py-2">{{ modalVer.m.tipo }}</td></tr>
              <tr v-if="modalVer.m.km_actual" class="border-b"><td class="py-2 font-semibold text-gray-500">Km actual</td>
                <td class="py-2">{{ modalVer.m.km_actual?.toLocaleString() }} km</td></tr>
              <tr class="border-b"><td class="py-2 font-semibold text-gray-500">Descripción</td>
                <td class="py-2">{{ modalVer.m.descripcion }}</td></tr>
              <tr class="border-b"><td class="py-2 font-semibold text-gray-500">Estado</td>
                <td class="py-2">
                  <span :class="estadoBadge(modalVer.m.estado)" class="px-2 py-0.5 rounded-full text-xs font-medium">
                    {{ estadoLabel(modalVer.m.estado) }}
                  </span>
                </td></tr>
              <template v-if="modalVer.m.numero_orden">
                <tr class="border-b"><td class="py-2 font-semibold text-gray-500">N° Orden</td>
                  <td class="py-2">{{ modalVer.m.numero_orden }}</td></tr>
                <tr class="border-b"><td class="py-2 font-semibold text-gray-500">Taller</td>
                  <td class="py-2">{{ modalVer.m.taller }}</td></tr>
                <tr class="border-b"><td class="py-2 font-semibold text-gray-500">Fecha Orden</td>
                  <td class="py-2">{{ formatFechaCorta(modalVer.m.fecha_orden) }}</td></tr>
              </template>
              <tr v-if="modalVer.m.observacion_responsable" class="border-b">
                <td class="py-2 font-semibold text-gray-500">Observación</td>
                <td class="py-2">{{ modalVer.m.observacion_responsable }}</td></tr>
              <tr v-if="modalVer.m.motivo_negacion" class="border-b">
                <td class="py-2 font-semibold text-gray-500">Motivo negación</td>
                <td class="py-2 text-red-700">{{ modalVer.m.motivo_negacion }}</td></tr>
              <tr v-if="modalVer.m.fecha_finalizacion" class="border-b">
                <td class="py-2 font-semibold text-gray-500">Fecha Fin</td>
                <td class="py-2">{{ formatFechaCorta(modalVer.m.fecha_finalizacion) }}</td></tr>
              <tr v-if="modalVer.m.km_finalizacion" class="border-b">
                <td class="py-2 font-semibold text-gray-500">Km al finalizar</td>
                <td class="py-2">{{ modalVer.m.km_finalizacion?.toLocaleString() }} km</td></tr>
              <tr v-if="modalVer.m.responsable" class="border-b">
                <td class="py-2 font-semibold text-gray-500">Responsable</td>
                <td class="py-2">{{ modalVer.m.responsable?.apellido_emp }} {{ modalVer.m.responsable?.nombre_emp }}</td></tr>
            </tbody>
          </table>

          <!-- Actividades -->
          <div v-if="modalVer.m.actividades?.length" class="mt-4">
            <p class="text-xs font-semibold text-gray-500 mb-2">Actividades</p>
            <ol class="space-y-1">
              <li v-for="a in modalVer.m.actividades" :key="a.id"
                class="flex gap-2 text-sm text-gray-700">
                <span class="flex-shrink-0 w-5 h-5 rounded-full text-xs font-bold flex items-center justify-center text-white"
                  :class="a.tipo === 'PREVENTIVO' ? 'bg-blue-500' : 'bg-orange-500'">
                  {{ a.orden }}
                </span>
                <span>
                  <span v-if="a.cantidad" class="font-semibold">{{ a.cantidad }}x</span>
                  {{ a.actividad }}
                  <span class="text-xs text-gray-400 ml-1">[{{ a.tipo }}]</span>
                </span>
              </li>
            </ol>
          </div>
        </template>
        <div class="flex justify-end mt-4">
          <button @click="modalVer.show = false"
            class="px-4 py-2 text-sm border rounded-lg text-gray-600 hover:text-gray-800">Cerrar</button>
        </div>
        </div>
      </div>
    </div>

    <!-- Modal Nuevo Requerimiento -->
    <div v-if="modalCrear.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
        <div class="flex items-center justify-between px-6 py-4 flex-shrink-0" style="background-color:#1e3a5f;">
          <h2 class="text-lg font-bold text-white">Nuevo Requerimiento de Mantenimiento</h2>
          <button @click="modalCrear.show = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-6 overflow-y-auto">
        <div class="space-y-3">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Vehículo *</label>
            <select v-model="formCrear.vehiculo_id" class="w-full border rounded-lg px-3 py-2 text-sm">
              <option value="">Seleccione...</option>
              <option v-for="v in vehiculos" :key="v.id" :value="v.id">
                {{ v.placa }} — {{ v.marca }} {{ v.modelo }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Tipo de mantenimiento *</label>
            <select v-model="formCrear.tipo_mantenimiento_id" @change="onTipoChange"
              class="w-full border rounded-lg px-3 py-2 text-sm">
              <option value="">Seleccione...</option>
              <option v-for="t in tiposActivos" :key="t.id" :value="t.id">
                {{ t.nombre }}
              </option>
            </select>
          </div>
          <div v-if="formCrear.vehiculo_id">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Kilometraje actual</label>
            <input :value="kmVehiculoSeleccionado?.toLocaleString()" type="text" readonly disabled
              class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-100 text-gray-600" />
            <p class="text-[11px] text-gray-400 mt-0.5">Kilometraje acumulado del vehículo — no editable</p>
          </div>

          <!-- Plan preventivo (solo si tipo incluye PREVENTIVO) -->
          <div v-if="tipoEsPreventivo">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Plan preventivo *</label>
            <select v-model="formCrear.plan_preventivo_id" class="w-full border rounded-lg px-3 py-2 text-sm">
              <option value="">Seleccione un plan...</option>
              <option v-for="p in planesDelVehiculo" :key="p.id" :value="p.id">
                {{ p.nombre }} · {{ p.km_hito?.toLocaleString() }} km ({{ p.actividades?.length || 0 }} actividades)
              </option>
            </select>
            <p v-if="!planesDelVehiculo.length && formCrear.vehiculo_id"
              class="text-xs text-amber-600 mt-1">No hay planes preventivos para este vehículo.</p>
          </div>

          <!-- Actividades correctivas (solo si tipo incluye CORRECTIVO) -->
          <div v-if="tipoEsCorrectivo">
            <div class="flex justify-between items-center mb-2">
              <label class="text-xs font-semibold text-gray-600">Actividades correctivas *</label>
              <button @click="agregarActCorr" type="button"
                class="text-xs text-orange-700 hover:text-orange-900 font-medium border border-orange-200 px-2 py-0.5 rounded">
                + Agregar
              </button>
            </div>
            <div class="space-y-2">
              <div v-for="(act, i) in formCrear.actividades_correctivas" :key="i"
                   class="flex items-start gap-2">
                <span class="flex-shrink-0 mt-2 w-5 h-5 rounded-full text-xs font-bold flex items-center justify-center bg-orange-500 text-white">
                  {{ i + 1 }}
                </span>
                <textarea v-model="act.actividad" v-uppercase rows="2"
                  class="flex-1 border rounded-lg px-3 py-2 text-sm resize-none"
                  :placeholder="'Actividad correctiva ' + (i + 1)"></textarea>
                <button @click="eliminarActCorr(i)" type="button"
                  class="flex-shrink-0 mt-1.5 text-red-400 hover:text-red-600 text-lg leading-none">&times;</button>
              </div>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Descripción / Problema</label>
            <textarea v-model="formCrear.descripcion" v-uppercase rows="2"
              class="w-full border rounded-lg px-3 py-2 text-sm resize-none"
              placeholder="Descripción adicional (opcional)..."></textarea>
          </div>
        </div>
        <p v-if="errorCrear" class="text-red-600 text-sm mt-3">{{ errorCrear }}</p>
        <div class="flex justify-end gap-2 mt-5">
          <button @click="modalCrear.show = false"
            class="px-4 py-2 text-sm text-gray-600 border rounded-lg">Cancelar</button>
          <button @click="guardarCrear" :disabled="guardando"
            class="px-5 py-2 text-sm text-white rounded-lg hover:opacity-90 disabled:opacity-50"
            style="background-color:#1e3a5f;">
            {{ guardando ? 'Enviando...' : 'Enviar' }}
          </button>
        </div>
        </div>
      </div>
    </div>

    <!-- Modal Generar Orden -->
    <div v-if="modalOrden.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4" style="background-color:#1e3a5f;">
          <div>
            <h2 class="text-lg font-bold text-white">Generar Orden de Trabajo</h2>
            <p class="text-xs text-blue-200 mt-0.5">El número de orden se asigna automáticamente por tipo (ej. 0001-2026).</p>
          </div>
          <button @click="modalOrden.show = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-6">
        <div class="space-y-3">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Taller *</label>
            <select v-model="formOrden.taller_id" class="w-full border rounded-lg px-3 py-2 text-sm">
              <option value="">Seleccione un taller...</option>
              <option v-for="t in talleresActivos" :key="t.id" :value="t.id">
                {{ t.nombre }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Fecha de Orden *</label>
            <input v-model="formOrden.fecha_orden" type="date" class="w-full border rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Observaciones</label>
            <textarea v-model="formOrden.observacion_responsable" v-uppercase rows="2"
              class="w-full border rounded-lg px-3 py-2 text-sm resize-none"></textarea>
          </div>
        </div>
        <p v-if="errorOrden" class="text-red-600 text-sm mt-3">{{ errorOrden }}</p>
        <div class="flex justify-end gap-2 mt-5">
          <button @click="modalOrden.show = false"
            class="px-4 py-2 text-sm text-gray-600 border rounded-lg">Cancelar</button>
          <button @click="guardarOrden" :disabled="guardando"
            class="px-5 py-2 text-sm text-white rounded-lg hover:opacity-90 disabled:opacity-50"
            style="background-color:#1e3a5f;">
            {{ guardando ? 'Guardando...' : 'Generar' }}
          </button>
        </div>
        </div>
      </div>
    </div>

    <!-- Modal Negar -->
    <div v-if="modalNegar.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4" style="background-color:#1e3a5f;">
          <h2 class="text-lg font-bold text-white">Negar Requerimiento</h2>
          <button @click="modalNegar.show = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-6">
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Motivo de negación *</label>
          <textarea v-model="formNegar.motivo_negacion" v-uppercase rows="3"
            class="w-full border rounded-lg px-3 py-2 text-sm resize-none"
            placeholder="Indique el motivo por el que no procede el requerimiento..."></textarea>
        </div>
        <p v-if="errorNegar" class="text-red-600 text-sm mt-3">{{ errorNegar }}</p>
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
    </div>

    <!-- Modal Finalizar -->
    <div v-if="modalFinalizar.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4" style="background-color:#1e3a5f;">
          <h2 class="text-lg font-bold text-white">Finalizar Mantenimiento</h2>
          <button @click="modalFinalizar.show = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-6">
        <div class="space-y-3">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Fecha de Finalización *</label>
            <input v-model="formFinalizar.fecha_finalizacion" type="date"
              class="w-full border rounded-lg px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">
              Km Actual del Vehículo <span v-if="modalFinalizar.esPreventivo">*</span>
            </label>
            <input v-model.number="formFinalizar.km_finalizacion" type="number"
              class="w-full border rounded-lg px-3 py-2 text-sm"
              :placeholder="modalFinalizar.esPreventivo ? 'Obligatorio para mantenimiento preventivo' : 'Dejar vacío si no cambió'"
              :min="modalFinalizar.kmVehiculo || 0" />
            <p v-if="modalFinalizar.esPreventivo" class="text-[11px] text-gray-400 mt-0.5">
              Necesario para calcular cuándo toca el próximo mantenimiento de este plan.
            </p>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Observaciones</label>
            <textarea v-model="formFinalizar.observacion_responsable" v-uppercase rows="2"
              class="w-full border rounded-lg px-3 py-2 text-sm resize-none"></textarea>
          </div>
        </div>
        <p v-if="errorFinalizar" class="text-red-600 text-sm mt-3">{{ errorFinalizar }}</p>
        <div class="flex justify-end gap-2 mt-5">
          <button @click="modalFinalizar.show = false"
            class="px-4 py-2 text-sm text-gray-600 border rounded-lg">Cancelar</button>
          <button @click="guardarFinalizar" :disabled="guardando"
            class="px-5 py-2 text-sm text-white rounded-lg hover:opacity-90 disabled:opacity-50"
            style="background-color:#1e3a5f;">
            {{ guardando ? 'Guardando...' : 'Finalizar' }}
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

const auth = useAuthStore()
const esTransporte = computed(() => auth.tieneRol('TRANSPORTE'))
const esConductor  = computed(() => auth.tieneRol('CONDUCTOR') || esTransporte.value)

const lista           = ref([])
const vehiculos       = ref([])
const tiposActivos    = ref([])
const talleresActivos = ref([])
const planesPreventivos = ref([])
const pagina          = ref(1)
const porPagina       = ref(10)
const filtroEstado    = ref('')
const guardando       = ref(false)

const listaFiltrada = computed(() =>
  filtroEstado.value ? lista.value.filter(m => m.estado === filtroEstado.value) : lista.value
)
const totalPaginas = computed(() => Math.max(1, Math.ceil(listaFiltrada.value.length / porPagina.value)))
const listaPaginada = computed(() => {
  const ini = (pagina.value - 1) * porPagina.value
  return listaFiltrada.value.slice(ini, ini + porPagina.value)
})

const modalVer       = ref({ show: false, m: null })
const modalCrear     = ref({ show: false })
const modalOrden     = ref({ show: false, id: null })
const modalNegar     = ref({ show: false, id: null })
const modalFinalizar = ref({ show: false, id: null })
const formCrear      = ref({})
const formOrden      = ref({})
const formNegar      = ref({})
const formFinalizar  = ref({})
const errorCrear     = ref('')
const errorOrden     = ref('')
const errorNegar     = ref('')
const errorFinalizar = ref('')

const tipoSeleccionado = computed(() =>
  tiposActivos.value.find(t => t.id == formCrear.value.tipo_mantenimiento_id) || null
)

const tipoEsPreventivo = computed(() =>
  !!tipoSeleccionado.value?.nombre?.includes('PREVENTIVO')
)
const tipoEsCorrectivo = computed(() =>
  !!tipoSeleccionado.value?.nombre?.includes('CORRECTIVO')
)

const planesDelVehiculo = computed(() =>
  planesPreventivos.value.filter(p => p.vehiculo_id == formCrear.value.vehiculo_id && p.estado === 'ACTIVO')
)

const kmVehiculoSeleccionado = computed(() =>
  vehiculos.value.find(v => v.id == formCrear.value.vehiculo_id)?.kilometraje_actual ?? 0
)

function onTipoChange() {
  formCrear.value.plan_preventivo_id = ''
  formCrear.value.actividades_correctivas = []
  if (tipoEsCorrectivo.value) {
    formCrear.value.actividades_correctivas = [{ actividad: '' }]
  }
}

function agregarActCorr() {
  formCrear.value.actividades_correctivas.push({ actividad: '' })
}

function eliminarActCorr(i) {
  formCrear.value.actividades_correctivas.splice(i, 1)
}

function estadoBadge(e) {
  if (e === 'PENDIENTE')      return 'bg-yellow-100 text-yellow-700'
  if (e === 'ORDEN_GENERADA') return 'bg-blue-100 text-blue-700'
  if (e === 'EN_TALLER')      return 'bg-orange-100 text-orange-700'
  if (e === 'FINALIZADO')     return 'bg-green-100 text-green-700'
  if (e === 'NEGADO')         return 'bg-red-100 text-red-700'
  return 'bg-gray-100 text-gray-600'
}
function estadoLabel(e) {
  if (e === 'ORDEN_GENERADA') return 'Orden generada'
  if (e === 'EN_TALLER')      return 'En taller'
  if (e === 'NEGADO')         return 'Negado'
  if (e === 'FINALIZADO')     return 'Finalizado'
  return e.charAt(0) + e.slice(1).toLowerCase()
}
function formatFecha(dt) {
  if (!dt) return '—'
  return dt.slice(0, 10).split('-').reverse().join('/')
}
function formatFechaCorta(d) {
  if (!d) return '—'
  return d.slice(0, 10).split('-').reverse().join('/')
}

async function cargar() {
  const [r1, r2, r3, r4, r5] = await Promise.all([
    api.get('/transporte/mantenimiento'),
    api.get('/transporte/vehiculos'),
    api.get('/transporte/tipos-mantenimiento/activos'),
    api.get('/transporte/talleres/activos'),
    api.get('/transporte/plan-preventivo'),
  ])
  lista.value           = r1.data
  vehiculos.value       = r2.data.filter(v => v.estado === 'ACTIVO' || v.estado === 'MANTENIMIENTO')
  tiposActivos.value    = r3.data
  talleresActivos.value = r4.data
  planesPreventivos.value = r5.data
}

function abrirVer(m) { modalVer.value = { show: true, m } }

function abrirCrear() {
  formCrear.value = {
    vehiculo_id: '',
    tipo_mantenimiento_id: '',
    plan_preventivo_id: '',
    actividades_correctivas: [],
    descripcion: '',
  }
  errorCrear.value = ''
  modalCrear.value = { show: true }
}

function abrirNegar(m) {
  formNegar.value = { motivo_negacion: '' }
  errorNegar.value = ''
  modalNegar.value = { show: true, id: m.id }
}

function abrirOrden(m) {
  const hoy = new Date().toISOString().slice(0, 10)
  formOrden.value = { taller_id: '', fecha_orden: hoy, observacion_responsable: '' }
  errorOrden.value = ''
  modalOrden.value = { show: true, id: m.id }
}

function abrirFinalizar(m) {
  const hoy = new Date().toISOString().slice(0, 10)
  formFinalizar.value = { fecha_finalizacion: hoy, km_finalizacion: null, observacion_responsable: m.observacion_responsable || '' }
  errorFinalizar.value = ''
  modalFinalizar.value = {
    show: true,
    id: m.id,
    esPreventivo: !!m.tipo?.includes('PREVENTIVO'),
    kmVehiculo: m.vehiculo?.kilometraje_actual || 0,
  }
}

async function guardarCrear() {
  errorCrear.value = ''
  guardando.value = true
  try {
    await api.post('/transporte/mantenimiento', formCrear.value)
    modalCrear.value.show = false
    await cargar()
  } catch (e) {
    errorCrear.value = e.response?.data?.message || Object.values(e.response?.data?.errors || {})[0]?.[0] || 'Error al enviar'
  } finally { guardando.value = false }
}

async function guardarNegar() {
  if (!formNegar.value.motivo_negacion?.trim()) {
    errorNegar.value = 'Indique el motivo de negación'
    return
  }
  errorNegar.value = ''
  guardando.value = true
  try {
    await api.put(`/transporte/mantenimiento/${modalNegar.value.id}`,
      { accion: 'negar', ...formNegar.value })
    modalNegar.value.show = false
    await cargar()
  } catch (e) {
    errorNegar.value = e.response?.data?.message || 'Error al negar'
  } finally { guardando.value = false }
}

async function guardarOrden() {
  errorOrden.value = ''
  guardando.value = true
  try {
    await api.put(`/transporte/mantenimiento/${modalOrden.value.id}`,
      { accion: 'orden', ...formOrden.value })
    modalOrden.value.show = false
    await cargar()
  } catch (e) {
    errorOrden.value = e.response?.data?.message || 'Error al generar orden'
  } finally { guardando.value = false }
}

async function cambiarEstado(id, accion) {
  try {
    await api.put(`/transporte/mantenimiento/${id}`, { accion })
    await cargar()
  } catch {}
}

async function guardarFinalizar() {
  errorFinalizar.value = ''
  guardando.value = true
  try {
    await api.put(`/transporte/mantenimiento/${modalFinalizar.value.id}`,
      { accion: 'finalizar', ...formFinalizar.value })
    modalFinalizar.value.show = false
    await cargar()
  } catch (e) {
    errorFinalizar.value = e.response?.data?.message || 'Error al finalizar'
  } finally { guardando.value = false }
}

async function verPdf(id) {
  try {
    const { data } = await api.get(`/transporte/mantenimiento/${id}/pdf`, { responseType: 'blob' })
    const url  = window.URL.createObjectURL(new Blob([data], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href  = url
    link.setAttribute('download', `orden_trabajo_${id}.pdf`)
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
