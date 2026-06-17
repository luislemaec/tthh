<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">Tarifas de Viáticos</h1>
        <p class="text-sm text-gray-500 mt-0.5">Valores diarios para comisiones de servicio</p>
      </div>
      <button @click="abrirNueva"
        class="flex items-center gap-2 px-4 py-2 text-white text-sm font-semibold rounded-lg shadow transition hover:opacity-90"
        style="background-color:#5c4a6e;">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        {{ tabActivo === 'interior' ? 'Nueva Tarifa' : 'Nuevo País' }}
      </button>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 mb-4 bg-gray-100 p-1 rounded-lg w-fit">
      <button v-for="t in tabs" :key="t.key" @click="tabActivo = t.key"
        :class="['px-4 py-2 text-sm font-medium rounded-md transition', tabActivo === t.key ? 'bg-white text-[#5c4a6e] shadow-sm' : 'text-gray-500 hover:text-gray-700']">
        {{ t.label }}
      </button>
    </div>

    <div v-if="cargando" class="flex items-center justify-center py-16">
      <div class="w-8 h-8 border-2 border-t-transparent rounded-full animate-spin" style="border-color:#5c4a6e; border-top-color:transparent;"></div>
    </div>

    <!-- Tab INTERIOR -->
    <div v-else-if="tabActivo === 'interior'" class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr class="text-xs text-gray-500 uppercase border-b border-gray-100" style="background-color:#f9f7fb;">
            <th class="px-4 py-3 text-left font-semibold">Descripción</th>
            <th class="px-4 py-3 text-left font-semibold">Aplica a</th>
            <th class="px-4 py-3 text-right font-semibold">Valor / Día</th>
            <th class="px-4 py-3 text-center font-semibold">Estado</th>
            <th class="px-4 py-3 text-left font-semibold">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="tarifasInterior.length === 0">
            <td colspan="5" class="px-4 py-12 text-center text-gray-400 text-sm">No hay tarifas de interior. Cree la primera.</td>
          </tr>
          <tr v-for="tarifa in tarifasInterior" :key="tarifa.id" class="border-b border-gray-50 hover:bg-purple-50/20 transition">
            <td class="px-4 py-3 font-medium text-gray-800">{{ tarifa.descripcion }}</td>
            <td class="px-4 py-3">
              <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                :class="tarifa.aplica_jerarquico ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-600'">
                {{ tarifa.aplica_jerarquico ? 'Nivel Jerárquico Superior' : 'Otros Funcionarios' }}
              </span>
            </td>
            <td class="px-4 py-3 text-right font-semibold text-gray-800">
              ${{ parseFloat(tarifa.valor_dia).toFixed(2) }}
            </td>
            <td class="px-4 py-3 text-center">
              <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full', tarifa.activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
                {{ tarifa.activo ? 'Activo' : 'Inactivo' }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <button @click="editar(tarifa)" class="p-1.5 text-gray-400 hover:text-[#5c4a6e] hover:bg-purple-50 rounded transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                  </svg>
                </button>
                <button @click="eliminar(tarifa)" class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Tab EXTERIOR (coeficientes por país) -->
    <div v-else-if="tabActivo === 'exterior'">
      <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-3 mb-4 text-sm text-blue-800">
        <strong>Base diaria: ${{ valorBase }}</strong> USD para todos los funcionarios.
        Fórmula: <em>${{ valorBase }} × coeficiente × días autorizados</em>.
        Para países no listados, usar el coeficiente del país geográficamente más cercano con el menor coeficiente.
      </div>
      <div v-for="(paises, region) in coeficientesAgrupados" :key="region" class="mb-4">
        <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 px-1">{{ region }}</h3>
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
          <table class="w-full text-sm">
            <tbody>
              <tr v-for="p in paises" :key="p.id" class="border-b border-gray-50 hover:bg-blue-50/20 transition">
                <td class="px-4 py-2.5 font-medium text-gray-800">{{ p.pais }}</td>
                <td class="px-4 py-2.5 text-right">
                  <span class="font-mono text-blue-700 font-semibold">{{ parseFloat(p.coeficiente).toFixed(4) }}</span>
                </td>
                <td class="px-4 py-2.5 text-right text-gray-500">
                  = ${{ (valorBase * p.coeficiente).toFixed(2) }}/día
                </td>
                <td class="px-4 py-2.5 text-center">
                  <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full', p.activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500']">
                    {{ p.activo ? 'Activo' : 'Inactivo' }}
                  </span>
                </td>
                <td class="px-4 py-2.5">
                  <button @click="editarCoeficiente(p)" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal tarifa interior -->
  <div v-if="modalAbierto" class="fixed inset-0 z-50 flex items-center justify-center px-4">
    <div class="fixed inset-0 bg-black/40" @click="cerrar"/>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md p-6 z-10">
      <h3 class="text-lg font-bold text-gray-800 mb-5">{{ editandoId ? 'Editar Tarifa' : 'Nueva Tarifa Interior' }}</h3>
      <div class="space-y-4">
        <div>
          <label class="text-xs font-semibold text-gray-600 mb-1 block">Descripción *</label>
          <input v-model="form.descripcion" type="text" class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]"/>
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-xs font-semibold text-gray-600 mb-1 block">Aplica a *</label>
            <select v-model="form.aplica_jerarquico" class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]">
              <option :value="true">Nivel Jerárquico Superior</option>
              <option :value="false">Otros Funcionarios</option>
            </select>
          </div>
          <div>
            <label class="text-xs font-semibold text-gray-600 mb-1 block">Valor por Día ($) *</label>
            <input v-model.number="form.valor_dia" type="number" step="0.01" min="0"
              class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]"/>
          </div>
        </div>
        <div v-if="editandoId">
          <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
            <input type="checkbox" v-model="form.activo" class="rounded"/>
            Tarifa activa
          </label>
        </div>
      </div>
      <p v-if="error" class="text-red-600 text-xs mt-3">{{ error }}</p>
      <div class="flex justify-end gap-3 mt-6">
        <button @click="cerrar" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
        <button @click="guardar" :disabled="guardando"
          class="px-5 py-2 text-sm font-semibold text-white rounded-lg hover:opacity-90 disabled:opacity-50"
          style="background-color:#5c4a6e;">
          {{ guardando ? 'Guardando...' : 'Guardar' }}
        </button>
      </div>
    </div>
  </div>

  <!-- Modal coeficiente exterior -->
  <div v-if="modalCoef.show" class="fixed inset-0 z-50 flex items-center justify-center px-4">
    <div class="fixed inset-0 bg-black/40" @click="modalCoef.show = false"/>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-sm p-6 z-10">
      <h3 class="text-lg font-bold text-gray-800 mb-5">{{ modalCoef.id ? 'Editar Coeficiente' : 'Nuevo País' }}</h3>
      <div class="space-y-4">
        <div>
          <label class="text-xs font-semibold text-gray-600 mb-1 block">País *</label>
          <input v-model="modalCoef.form.pais" type="text" :disabled="!!modalCoef.id"
            class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500 disabled:bg-gray-50"/>
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-600 mb-1 block">Región *</label>
          <select v-model="modalCoef.form.region" class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500">
            <option v-for="r in regiones" :key="r">{{ r }}</option>
          </select>
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-600 mb-1 block">Coeficiente *</label>
          <input v-model.number="modalCoef.form.coeficiente" type="number" step="0.0001" min="0"
            class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-blue-500"/>
          <p v-if="modalCoef.form.coeficiente" class="text-xs text-blue-600 mt-1">
            = ${{ (valorBase * modalCoef.form.coeficiente).toFixed(2) }}/día
          </p>
        </div>
        <div v-if="modalCoef.id">
          <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
            <input type="checkbox" v-model="modalCoef.form.activo" class="rounded"/>
            País activo
          </label>
        </div>
      </div>
      <p v-if="modalCoef.error" class="text-red-600 text-xs mt-3">{{ modalCoef.error }}</p>
      <div class="flex justify-end gap-3 mt-6">
        <button @click="modalCoef.show = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
        <button @click="guardarCoeficiente" :disabled="modalCoef.guardando"
          class="px-5 py-2 text-sm font-semibold text-white rounded-lg hover:opacity-90 disabled:opacity-50 bg-blue-600">
          {{ modalCoef.guardando ? 'Guardando...' : 'Guardar' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

const tabs = [
  { key: 'interior', label: 'Interior' },
  { key: 'exterior', label: 'Exterior — Coeficientes' },
]
const tabActivo   = ref('interior')
const cargando    = ref(true)
const tarifas     = ref([])
const coeficientes = ref([])
const valorBase   = ref(185)

const regiones = ['ÁFRICA', 'AMÉRICA CENTRAL', 'AMÉRICA DEL NORTE', 'AMÉRICA DEL SUR', 'ASIA', 'EUROPA', 'OCEANÍA']

const tarifasInterior = computed(() => tarifas.value.filter(t => t.tipo === 'INTERIOR'))

const coeficientesAgrupados = computed(() => {
  const grupos = {}
  for (const p of coeficientes.value) {
    if (!grupos[p.region]) grupos[p.region] = []
    grupos[p.region].push(p)
  }
  return grupos
})

const modalAbierto = ref(false)
const editandoId   = ref(null)
const guardando    = ref(false)
const error        = ref('')
const form = ref({ descripcion: '', tipo: 'INTERIOR', aplica_jerarquico: false, valor_dia: 0, activo: true })

const modalCoef = ref({ show: false, id: null, form: { pais: '', region: 'AMÉRICA DEL SUR', coeficiente: 1, activo: true }, error: '', guardando: false })

async function cargar() {
  cargando.value = true
  try {
    const [t, c] = await Promise.all([
      api.get('/comisiones/admin/tarifas-viaticos'),
      api.get('/comisiones/coeficientes-pais'),
    ])
    tarifas.value     = t.data
    coeficientes.value = c.data
  } finally {
    cargando.value = false
  }
}

onMounted(cargar)

function abrirNueva() {
  if (tabActivo.value === 'exterior') {
    modalCoef.value = { show: true, id: null, form: { pais: '', region: 'AMÉRICA DEL SUR', coeficiente: 1, activo: true }, error: '', guardando: false }
  } else {
    form.value = { descripcion: '', tipo: 'INTERIOR', aplica_jerarquico: false, valor_dia: 0, activo: true }
    editandoId.value = null
    error.value = ''
    modalAbierto.value = true
  }
}

function editar(tarifa) {
  form.value = { descripcion: tarifa.descripcion, tipo: tarifa.tipo, aplica_jerarquico: tarifa.aplica_jerarquico, valor_dia: parseFloat(tarifa.valor_dia), activo: tarifa.activo }
  editandoId.value = tarifa.id
  error.value = ''
  modalAbierto.value = true
}

function editarCoeficiente(p) {
  modalCoef.value = { show: true, id: p.id, form: { pais: p.pais, region: p.region, coeficiente: parseFloat(p.coeficiente), activo: p.activo }, error: '', guardando: false }
}

function cerrar() { modalAbierto.value = false }

async function guardar() {
  error.value = ''
  if (!form.value.descripcion.trim() || !form.value.valor_dia) {
    error.value = 'Complete la descripción y el valor por día.'
    return
  }
  guardando.value = true
  try {
    if (editandoId.value) {
      await api.put(`/comisiones/admin/tarifas-viaticos/${editandoId.value}`, form.value)
    } else {
      await api.post('/comisiones/admin/tarifas-viaticos', form.value)
    }
    modalAbierto.value = false
    await cargar()
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al guardar.'
  } finally {
    guardando.value = false
  }
}

async function guardarCoeficiente() {
  modalCoef.value.error = ''
  const { pais, region, coeficiente } = modalCoef.value.form
  if (!pais.trim() || !coeficiente) { modalCoef.value.error = 'Complete todos los campos.'; return }
  modalCoef.value.guardando = true
  try {
    if (modalCoef.value.id) {
      await api.put(`/comisiones/admin/coeficientes-pais/${modalCoef.value.id}`, modalCoef.value.form)
    } else {
      await api.post('/comisiones/admin/coeficientes-pais', modalCoef.value.form)
    }
    modalCoef.value.show = false
    await cargar()
  } catch (e) {
    modalCoef.value.error = e.response?.data?.message || 'Error al guardar.'
  } finally {
    modalCoef.value.guardando = false
  }
}

async function eliminar(tarifa) {
  if (!confirm(`¿Eliminar la tarifa "${tarifa.descripcion}"?`)) return
  try {
    await api.delete(`/comisiones/admin/tarifas-viaticos/${tarifa.id}`)
    await cargar()
  } catch (e) {
    alert(e.response?.data?.message || 'Error al eliminar.')
  }
}
</script>
