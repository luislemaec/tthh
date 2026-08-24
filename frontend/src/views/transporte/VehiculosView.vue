<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Vehículos</h1>
      <button @click="abrirCrear" class="text-white px-4 py-2 rounded-lg text-sm hover:opacity-90"
        style="background-color:#1e3a5f;">
        + Nuevo vehículo
      </button>
    </div>

    <!-- Tarjetas resumen -->
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4">
      <button @click="filtroMtto = ''"
        class="rounded-xl shadow px-4 py-3 text-left"
        :class="filtroMtto === '' ? 'ring-2 ring-offset-1' : ''"
        :style="filtroMtto === '' ? 'background-color:#eef2f6; --tw-ring-color:#1e3a5f;' : 'background-color:#ffffff;'">
        <p class="text-2xl font-bold text-gray-800">{{ vehiculos.length }}</p>
        <p class="text-xs text-gray-500">Total vehículos</p>
      </button>
      <button @click="filtroMtto = 'vencido'"
        class="rounded-xl shadow px-4 py-3 text-left"
        :class="filtroMtto === 'vencido' ? 'ring-2 ring-offset-1 ring-red-500' : ''"
        style="background-color:#fef2f2;">
        <p class="text-2xl font-bold text-red-700">{{ conteoVencidos }}</p>
        <p class="text-xs text-red-600">⚠ Mantenimiento vencido</p>
      </button>
      <button @click="filtroMtto = 'proximo'"
        class="rounded-xl shadow px-4 py-3 text-left"
        :class="filtroMtto === 'proximo' ? 'ring-2 ring-offset-1 ring-amber-500' : ''"
        style="background-color:#fffbeb;">
        <p class="text-2xl font-bold text-amber-700">{{ conteoProximos }}</p>
        <p class="text-xs text-amber-600">Mantenimiento próximo</p>
      </button>
    </div>

    <!-- Lista -->
    <div class="space-y-2">
      <div v-if="!vehiculosFiltrados.length" class="bg-white rounded-xl shadow p-8 text-center text-gray-400">
        Sin vehículos {{ filtroMtto ? 'con ese estado de mantenimiento' : 'registrados' }}
      </div>

      <div v-for="v in vehiculosFiltrados" :key="v.id"
           class="bg-white rounded-xl shadow px-5 py-3 flex justify-between items-center gap-3">
        <div class="min-w-0">
          <p class="font-semibold text-gray-800">
            {{ v.placa }}
            <span class="text-gray-500 font-normal ml-2">{{ v.marca }} {{ v.modelo }} {{ v.anio }}</span>
          </p>
          <p class="text-xs text-gray-500 mt-0.5">
            Km: {{ v.kilometraje_actual?.toLocaleString() }}
            <span v-if="v.color"> · {{ v.color }}</span>
            <span v-if="v.chasis"> · Chasis: {{ v.chasis }}</span>
            <span v-if="v.numero_motor"> · Motor: {{ v.numero_motor }}</span>
          </p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
          <button v-if="v.mantenimiento_vencido || v.mantenimiento_proximo" @click="abrirAlertas(v)"
            :title="v.mantenimiento_vencido ? 'Tiene mantenimiento vencido — clic para ver detalle' : 'Tiene mantenimiento próximo — clic para ver detalle'"
            class="flex items-center justify-center w-7 h-7 rounded-full hover:opacity-80 transition"
            :class="v.mantenimiento_vencido ? 'bg-red-100' : 'bg-amber-100'">
            <svg class="w-4 h-4" :class="v.mantenimiento_vencido ? 'text-red-600' : 'text-amber-600'"
              fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l6.28 11.18c.75 1.334-.213 2.987-1.742 2.987H3.72c-1.53 0-2.493-1.653-1.743-2.987l6.28-11.18zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-.25-6.25a.75.75 0 00-1.5 0v3.5a.75.75 0 001.5 0v-3.5z" clip-rule="evenodd" />
            </svg>
          </button>
          <span :class="estadoBadge(v.estado)" class="px-2 py-0.5 rounded-full text-xs font-medium">
            {{ v.estado }}
          </span>
          <button @click="abrirEditar(v)"
            class="text-xs text-gray-600 hover:text-gray-900 font-medium border border-gray-300 px-3 py-1 rounded-lg">
            Editar
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Alertas de Mantenimiento -->
    <div v-if="modalAlertas.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden flex flex-col max-h-[85vh]">
        <div class="flex items-center justify-between px-6 py-4 flex-shrink-0" style="background-color:#1e3a5f;">
          <div>
            <h2 class="text-lg font-bold text-white">{{ modalAlertas.vehiculo?.placa }}</h2>
            <p class="text-xs text-blue-200 mt-0.5">
              {{ modalAlertas.vehiculo?.marca }} {{ modalAlertas.vehiculo?.modelo }} ·
              {{ modalAlertas.vehiculo?.kilometraje_actual?.toLocaleString() }} km actuales
            </p>
          </div>
          <button @click="modalAlertas.show = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-6 overflow-y-auto space-y-2">
          <div v-for="p in planesAlerta(modalAlertas.vehiculo)" :key="p.plan_id"
            class="rounded-lg px-4 py-2.5 flex justify-between items-center gap-3"
            :class="p.vencido ? 'bg-red-50' : 'bg-amber-50'">
            <div class="min-w-0">
              <p class="text-sm font-medium text-gray-800">{{ p.nombre }}</p>
              <p class="text-xs text-gray-500 mt-0.5">Hito: {{ p.km_hito.toLocaleString() }} km</p>
            </div>
            <span class="flex-shrink-0 px-2 py-0.5 rounded-full text-xs font-medium"
              :class="p.vencido ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'">
              {{ p.vencido ? '⚠ Vencido' : 'Próximo' }}
            </span>
          </div>
          <div class="flex justify-end mt-2">
            <button @click="modalAlertas.show = false"
              class="px-4 py-2 text-sm border rounded-lg text-gray-600 hover:text-gray-800">Cerrar</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal crear/editar -->
    <div v-if="modal.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4" style="background-color:#1e3a5f;">
          <h2 class="text-lg font-bold text-white">
            {{ modal.id ? 'Editar Vehículo' : 'Nuevo Vehículo' }}
          </h2>
          <button @click="modal.show = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-6">
        <div class="space-y-3">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Placa *</label>
              <input v-model="form.placa" v-uppercase class="w-full border rounded-lg px-3 py-2 text-sm"
                placeholder="ABC-1234" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Año *</label>
              <input v-model.number="form.anio" type="number" class="w-full border rounded-lg px-3 py-2 text-sm"
                placeholder="2020" min="1990" max="2100" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Marca *</label>
              <input v-model="form.marca" v-uppercase class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Toyota" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Modelo *</label>
              <input v-model="form.modelo" v-uppercase class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Hilux" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Color</label>
              <input v-model="form.color" v-uppercase class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Blanco" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Km Actual *</label>
              <input v-model.number="form.kilometraje_actual" type="number"
                class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="0"
                :min="modal.id ? kmMinimo : 0" />
              <p v-if="modal.id" class="text-[11px] text-gray-400 mt-0.5">
                Solo se puede corregir hacia arriba (mínimo {{ kmMinimo.toLocaleString() }})
              </p>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Chasis</label>
              <input v-model="form.chasis" v-uppercase class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">N° Motor</label>
              <input v-model="form.numero_motor" v-uppercase class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
          </div>
          <div v-if="modal.id">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Estado *</label>
            <select v-model="form.estado" class="w-full border rounded-lg px-3 py-2 text-sm">
              <option value="ACTIVO">ACTIVO</option>
              <option value="MANTENIMIENTO">MANTENIMIENTO</option>
              <option value="INACTIVO">INACTIVO</option>
            </select>
          </div>
        </div>

        <p v-if="error" class="text-red-600 text-sm mt-3">{{ error }}</p>

        <div class="flex justify-end gap-2 mt-5">
          <button @click="modal.show = false"
            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 border rounded-lg">
            Cancelar
          </button>
          <button @click="guardar" :disabled="guardando"
            class="px-5 py-2 text-sm text-white rounded-lg hover:opacity-90 disabled:opacity-50"
            style="background-color:#1e3a5f;">
            {{ guardando ? 'Guardando...' : 'Guardar' }}
          </button>
        </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

const vehiculos = ref([])
const guardando = ref(false)
const error = ref('')
const modal = ref({ show: false, id: null })
const modalAlertas = ref({ show: false, vehiculo: null })
const form = ref({})
const kmMinimo = ref(0)
const filtroMtto = ref('') // '' | 'vencido' | 'proximo'

const conteoVencidos = computed(() => vehiculos.value.filter(v => v.mantenimiento_vencido).length)
const conteoProximos = computed(() => vehiculos.value.filter(v => v.mantenimiento_proximo).length)

const vehiculosFiltrados = computed(() => {
  if (filtroMtto.value === 'vencido') return vehiculos.value.filter(v => v.mantenimiento_vencido)
  if (filtroMtto.value === 'proximo') return vehiculos.value.filter(v => v.mantenimiento_proximo)
  return vehiculos.value
})

function planesAlerta(v) {
  return (v?.planes_estado || []).filter(p => p.vencido || p.proximo)
}

function abrirAlertas(v) {
  modalAlertas.value = { show: true, vehiculo: v }
}

function estadoBadge(e) {
  if (e === 'ACTIVO') return 'bg-green-100 text-green-700'
  if (e === 'MANTENIMIENTO') return 'bg-yellow-100 text-yellow-700'
  return 'bg-red-100 text-red-700'
}

async function cargar() {
  const { data } = await api.get('/transporte/vehiculos')
  vehiculos.value = data
}

function abrirCrear() {
  form.value = { placa: '', marca: '', modelo: '', anio: new Date().getFullYear(),
    chasis: '', color: '', numero_motor: '', kilometraje_actual: 0 }
  modal.value = { show: true, id: null }
  error.value = ''
}

function abrirEditar(v) {
  form.value = { ...v }
  kmMinimo.value = v.kilometraje_actual || 0
  modal.value = { show: true, id: v.id }
  error.value = ''
}

async function guardar() {
  error.value = ''
  guardando.value = true
  try {
    if (modal.value.id) {
      await api.put(`/transporte/vehiculos/${modal.value.id}`, form.value)
    } else {
      await api.post('/transporte/vehiculos', form.value)
    }
    modal.value.show = false
    await cargar()
  } catch (e) {
    error.value = e.response?.data?.message || Object.values(e.response?.data?.errors || {})[0]?.[0] || 'Error al guardar'
  } finally {
    guardando.value = false
  }
}

onMounted(cargar)
</script>
