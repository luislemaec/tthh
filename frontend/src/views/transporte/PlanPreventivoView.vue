<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Planes Preventivos</h1>
      <div class="flex gap-2">
        <button @click="descargarPlantilla"
          class="px-4 py-2 rounded-lg text-sm border border-gray-300 text-gray-600 hover:bg-gray-50">
          Plantilla CSV
        </button>
        <label class="px-4 py-2 rounded-lg text-sm border border-green-600 text-green-700 hover:bg-green-50 cursor-pointer">
          Importar CSV
          <input type="file" accept=".csv" class="hidden" @change="importarCsv" />
        </label>
        <button @click="abrirCrear()" class="text-white px-4 py-2 rounded-lg text-sm hover:opacity-90"
          style="background-color:#1e3a5f;">
          + Nuevo plan
        </button>
      </div>
    </div>

    <!-- Mensaje importación -->
    <div v-if="msgImport.texto" class="mb-4 px-4 py-3 rounded-lg text-sm font-medium"
      :class="msgImport.ok ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'">
      {{ msgImport.texto }}
      <ul v-if="msgImport.errores?.length" class="mt-1 list-disc pl-4 text-xs">
        <li v-for="e in msgImport.errores" :key="e">{{ e }}</li>
      </ul>
    </div>

    <!-- Buscador de vehículo + expandir/colapsar -->
    <div class="flex flex-wrap items-center gap-3 mb-4">
      <input v-model="busqueda" type="text" placeholder="Buscar vehículo por placa, marca o modelo..."
        class="border rounded-lg px-3 py-2 text-sm flex-1 min-w-[220px]" />
      <label class="flex items-center gap-1.5 text-xs text-gray-600 cursor-pointer select-none">
        <input type="checkbox" v-model="soloConPlanes" class="rounded" />
        Solo con planes
      </label>
      <button @click="expandirTodo" class="text-xs text-gray-600 hover:text-gray-900 border border-gray-300 px-3 py-1.5 rounded-lg">
        Expandir todo
      </button>
      <button @click="colapsarTodo" class="text-xs text-gray-600 hover:text-gray-900 border border-gray-300 px-3 py-1.5 rounded-lg">
        Colapsar todo
      </button>
    </div>

    <!-- Acordeón por vehículo -->
    <div class="space-y-2">
      <div v-if="!gruposFiltrados.length" class="bg-white rounded-xl shadow p-8 text-center text-gray-400">
        Sin vehículos que coincidan
      </div>

      <div v-for="g in gruposFiltrados" :key="g.vehiculo.id"
           class="bg-white rounded-xl shadow overflow-hidden">
        <!-- Cabecera vehículo -->
        <div class="flex items-center justify-between px-5 py-3 cursor-pointer hover:bg-gray-50"
             @click="toggleVehiculo(g.vehiculo.id)">
          <div class="flex items-center gap-3 min-w-0">
            <svg class="w-4 h-4 text-gray-400 flex-shrink-0 transition-transform"
              :class="abiertos.has(g.vehiculo.id) ? 'rotate-90' : ''"
              fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            <span class="font-semibold text-gray-800 truncate">{{ g.vehiculo.placa }}</span>
            <span class="text-gray-500 text-sm truncate">{{ g.vehiculo.marca }} {{ g.vehiculo.modelo }}</span>
          </div>
          <div class="flex items-center gap-2 flex-shrink-0">
            <span :class="g.planes.length ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500'"
              class="px-2 py-0.5 rounded-full text-xs font-medium">
              {{ g.planes.length }} plan{{ g.planes.length !== 1 ? 'es' : '' }}
            </span>
            <button @click.stop="abrirCrear(g.vehiculo.id)"
              class="text-xs text-blue-700 hover:text-blue-900 font-medium border border-blue-200 px-2 py-1 rounded-lg">
              + Plan
            </button>
          </div>
        </div>

        <!-- Planes del vehículo (expandible) -->
        <div v-if="abiertos.has(g.vehiculo.id)" class="border-t px-5 py-3 space-y-2">
          <p v-if="!g.planes.length" class="text-xs text-gray-400 italic">
            Sin planes registrados para este vehículo.
          </p>
          <div v-for="p in g.planes" :key="p.id"
               class="bg-gray-50 rounded-lg px-4 py-2.5 flex justify-between items-center gap-3">
            <div class="min-w-0 flex-1">
              <p class="font-medium text-gray-800 text-sm">{{ p.nombre }}</p>
              <p class="text-xs text-gray-500 mt-0.5">
                Hito: {{ p.km_hito?.toLocaleString() }} km ·
                {{ p.actividades?.length || 0 }} actividad{{ (p.actividades?.length || 0) !== 1 ? 'es' : '' }}
              </p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
              <span v-if="p.ejecutado" title="Ya se registró un mantenimiento finalizado para este hito"
                class="px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                ✓ Ejecutado
              </span>
              <span :class="p.estado === 'ACTIVO' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                class="px-2 py-0.5 rounded-full text-xs font-medium">
                {{ p.estado }}
              </span>
              <button @click="abrirVer(p)"
                class="text-xs text-gray-600 hover:text-gray-900 font-medium border border-gray-300 px-3 py-1 rounded-lg bg-white">
                Ver
              </button>
              <button @click="abrirEditar(p)"
                class="text-xs text-gray-600 hover:text-gray-900 font-medium border border-gray-300 px-3 py-1 rounded-lg bg-white">
                Editar
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Ver actividades -->
    <div v-if="modalVer.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden flex flex-col max-h-[85vh]">
        <div class="px-6 py-4 flex justify-between items-center flex-shrink-0" style="background-color:#1e3a5f;">
          <h2 class="text-lg font-bold text-white">{{ modalVer.plan?.nombre }}</h2>
          <button @click="modalVer.show = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-6 overflow-y-auto">
        <p class="text-xs text-gray-500 mb-4">
          {{ modalVer.plan?.vehiculo?.placa }} · {{ modalVer.plan?.vehiculo?.marca }} {{ modalVer.plan?.vehiculo?.modelo }}
          · Hito {{ modalVer.plan?.km_hito?.toLocaleString() }} km
        </p>
        <table class="w-full text-sm">
          <thead>
            <tr class="text-xs text-gray-500 border-b">
              <th class="text-left py-1 w-8">#</th>
              <th class="text-left py-1 w-10">Tipo</th>
              <th class="text-left py-1 w-12">Cant.</th>
              <th class="text-left py-1">Descripción</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="a in modalVer.plan?.actividades" :key="a.id" class="border-b last:border-0">
              <td class="py-1.5 text-gray-400 text-xs">{{ a.orden }}</td>
              <td class="py-1.5">
                <span class="text-xs font-bold px-1.5 py-0.5 rounded"
                  :class="a.tipo_actividad === 'MO' ? 'bg-blue-100 text-blue-700' : a.tipo_actividad === 'RE' ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700'">
                  {{ a.tipo_actividad }}
                </span>
              </td>
              <td class="py-1.5 text-gray-600 text-center">{{ a.cantidad }}</td>
              <td class="py-1.5 text-gray-700">{{ a.actividad }}</td>
            </tr>
          </tbody>
        </table>
        <div class="flex justify-end mt-5">
          <button @click="modalVer.show = false"
            class="px-4 py-2 text-sm border rounded-lg text-gray-600">Cerrar</button>
        </div>
        </div>
      </div>
    </div>

    <!-- Modal crear/editar -->
    <div v-if="modal.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl overflow-hidden flex flex-col max-h-[90vh]">
        <div class="flex items-center justify-between px-6 py-4 flex-shrink-0" style="background-color:#1e3a5f;">
          <h2 class="text-lg font-bold text-white">
            {{ modal.id ? 'Editar Plan Preventivo' : 'Nuevo Plan Preventivo' }}
          </h2>
          <button @click="modal.show = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-6 overflow-y-auto">
        <div class="space-y-3">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Vehículo *</label>
            <select v-model="form.vehiculo_id" class="w-full border rounded-lg px-3 py-2 text-sm">
              <option value="">Seleccione...</option>
              <option v-for="v in vehiculos" :key="v.id" :value="v.id">
                {{ v.placa }} — {{ v.marca }} {{ v.modelo }}
              </option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Km Hito *</label>
              <input v-model.number="form.km_hito" type="number" min="1"
                class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="5000" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Nombre del plan *</label>
              <input v-model="form.nombre" v-uppercase class="w-full border rounded-lg px-3 py-2 text-sm"
                placeholder="Ej: Mantenimiento 5000km" />
            </div>
          </div>
          <div v-if="modal.id">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Estado</label>
            <select v-model="form.estado" class="w-full border rounded-lg px-3 py-2 text-sm">
              <option value="ACTIVO">ACTIVO</option>
              <option value="INACTIVO">INACTIVO</option>
            </select>
          </div>

          <!-- Actividades -->
          <div>
            <div class="flex justify-between items-center mb-2">
              <label class="text-xs font-semibold text-gray-600">Actividades *</label>
              <button @click="agregarActividad" type="button"
                class="text-xs text-blue-700 hover:text-blue-900 font-medium border border-blue-200 px-2 py-0.5 rounded">
                + Agregar
              </button>
            </div>
            <div ref="listaActRef" class="space-y-2 max-h-72 overflow-y-auto">
              <div v-for="(act, i) in form.actividades" :key="i"
                   class="flex items-start gap-2 pr-2">
                <span class="flex-shrink-0 mt-2 w-5 h-5 rounded-full text-xs font-bold flex items-center justify-center text-white"
                  style="background-color:#1e3a5f;">{{ i + 1 }}</span>
                <select v-model="act.tipo_actividad"
                  class="flex-shrink-0 border rounded-lg px-2 py-2 text-xs w-20">
                  <option value="MO">MO</option>
                  <option value="RE">RE</option>
                  <option value="CL">CL</option>
                </select>
                <input v-model.number="act.cantidad" type="number" min="1"
                  class="flex-shrink-0 border rounded-lg px-2 py-2 text-xs w-16 text-center"
                  placeholder="Cant." />
                <textarea v-model="act.actividad" v-uppercase rows="2"
                  class="flex-1 border rounded-lg px-3 py-2 text-sm resize-none"
                  :placeholder="'Descripción ' + (i + 1)"></textarea>
                <button @click="eliminarActividad(i)" type="button"
                  class="flex-shrink-0 mt-1.5 w-6 h-6 flex items-center justify-center rounded-full hover:bg-red-100 text-red-400 hover:text-red-600 text-lg leading-none">&times;</button>
              </div>
            </div>
            <p v-if="form.actividades?.length === 0" class="text-xs text-gray-400 mt-1">
              Agregue al menos una actividad.
            </p>
          </div>
        </div>
        <p v-if="error" class="text-red-600 text-sm mt-3">{{ error }}</p>
        <div class="flex justify-end gap-2 mt-5">
          <button @click="modal.show = false"
            class="px-4 py-2 text-sm text-gray-600 border rounded-lg">Cancelar</button>
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
import { ref, computed, nextTick, onMounted } from 'vue'
import api from '@/services/api'

const lista         = ref([])
const vehiculos     = ref([])
const msgImport     = ref({ texto: '', ok: true, errores: [] })
const guardando     = ref(false)
const error         = ref('')
const busqueda      = ref('')
const soloConPlanes = ref(false)
const abiertos      = ref(new Set())
const modal         = ref({ show: false, id: null })
const modalVer      = ref({ show: false, plan: null })
const listaActRef   = ref(null)
const form          = ref({ actividades: [] })

// Agrupa los planes por vehículo — así no hay que buscar plan por plan en una lista plana.
const grupos = computed(() =>
  vehiculos.value.map(v => ({
    vehiculo: v,
    planes: lista.value.filter(p => p.vehiculo_id === v.id),
  }))
)

const gruposFiltrados = computed(() => {
  const q = busqueda.value.trim().toLowerCase()
  return grupos.value.filter(g => {
    if (soloConPlanes.value && !g.planes.length) return false
    if (!q) return true
    return `${g.vehiculo.placa} ${g.vehiculo.marca} ${g.vehiculo.modelo}`.toLowerCase().includes(q)
  })
})

function toggleVehiculo(id) {
  if (abiertos.value.has(id)) abiertos.value.delete(id)
  else abiertos.value.add(id)
  abiertos.value = new Set(abiertos.value)
}
function expandirTodo() {
  abiertos.value = new Set(gruposFiltrados.value.map(g => g.vehiculo.id))
}
function colapsarTodo() {
  abiertos.value = new Set()
}

function descargarPlantilla() {
  const contenido = [
    'placa,km_hito,nombre,tipo_actividad,actividad,cantidad',
    'GEA-2502,5000,Mantenimiento 5000km,MO,CAMBIAR ACEITE Y FILTRO MOTOR,1',
    'GEA-2502,5000,Mantenimiento 5000km,RE,FILTRO DE ACEITE,1',
    'GEA-2502,5000,Mantenimiento 5000km,CL,ACEITE 15W40,8',
    'GEA-2502,10000,Mantenimiento 10000km,MO,CAMBIAR FILTRO AIRE,1',
  ].join('\n')
  const blob = new Blob([contenido], { type: 'text/csv;charset=utf-8;' })
  const url  = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href  = url
  link.setAttribute('download', 'plantilla_plan_preventivo.csv')
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}

async function importarCsv(e) {
  const archivo = e.target.files[0]
  e.target.value = ''
  if (!archivo) return
  msgImport.value = { texto: '', ok: true, errores: [] }
  const formData = new FormData()
  formData.append('archivo', archivo)
  try {
    const { data } = await api.post('/transporte/plan-preventivo/importar-csv', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    msgImport.value = { texto: data.message, ok: true, errores: [] }
    await cargar()
  } catch (err) {
    const d = err.response?.data
    msgImport.value = { texto: d?.message || 'Error al importar.', ok: false, errores: d?.errores || [] }
  }
}

async function cargar() {
  const [r1, r2] = await Promise.all([
    api.get('/transporte/plan-preventivo'),
    api.get('/transporte/vehiculos'),
  ])
  lista.value = r1.data
  vehiculos.value = r2.data
}

function abrirVer(p) {
  modalVer.value = { show: true, plan: p }
}

function abrirCrear(vehiculoId = '') {
  form.value = { vehiculo_id: vehiculoId, km_hito: null, nombre: '', actividades: [{ tipo_actividad: 'MO', cantidad: 1, actividad: '' }] }
  modal.value = { show: true, id: null }
  error.value = ''
}

function abrirEditar(p) {
  form.value = {
    vehiculo_id: p.vehiculo_id,
    km_hito:     p.km_hito,
    nombre:      p.nombre,
    estado:      p.estado,
    actividades: (p.actividades || []).map(a => ({
      tipo_actividad: a.tipo_actividad || 'MO',
      cantidad:       a.cantidad || 1,
      actividad:      a.actividad,
    })),
  }
  modal.value = { show: true, id: p.id }
  error.value = ''
}

async function agregarActividad() {
  if (form.value.actividades.length < 20) {
    form.value.actividades.push({ tipo_actividad: 'MO', cantidad: 1, actividad: '' })
    await nextTick()
    if (listaActRef.value) {
      listaActRef.value.scrollTop = listaActRef.value.scrollHeight
    }
  }
}

function eliminarActividad(i) {
  form.value.actividades.splice(i, 1)
}

async function guardar() {
  error.value = ''
  guardando.value = true
  try {
    if (modal.value.id) {
      await api.put(`/transporte/plan-preventivo/${modal.value.id}`, form.value)
    } else {
      await api.post('/transporte/plan-preventivo', form.value)
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
