<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">Provincias y Ciudades</h1>
        <p class="text-sm text-gray-500 mt-0.5">Destinos disponibles para comisiones de servicio interior</p>
      </div>
      <button @click="abrirNuevaProvincia"
        class="flex items-center gap-2 px-4 py-2 bg-[#5c4a6e] text-white text-sm font-semibold rounded-lg shadow hover:opacity-90 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Nueva Provincia
      </button>
    </div>

    <div v-if="cargando" class="flex justify-center py-16">
      <div class="w-8 h-8 border-2 border-[#5c4a6e] border-t-transparent rounded-full animate-spin"></div>
    </div>

    <div v-else class="space-y-3">
      <div v-for="prov in provincias" :key="prov.id"
        class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
        <!-- Cabecera provincia -->
        <div class="flex items-center justify-between px-4 py-3 cursor-pointer hover:bg-gray-50 transition"
          @click="toggleProvincia(prov.id)">
          <div class="flex items-center gap-3">
            <svg class="w-4 h-4 text-gray-400 transition-transform"
              :class="abiertos.has(prov.id) ? 'rotate-90' : ''"
              fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            <span class="font-semibold text-gray-800">{{ prov.nombre }}</span>
            <span class="text-xs text-gray-400">({{ prov.ciudades.length }} ciudades)</span>
          </div>
          <div class="flex gap-2" @click.stop>
            <button @click="abrirEditarProvincia(prov)"
              class="p-1.5 text-gray-400 hover:text-[#5c4a6e] hover:bg-purple-50 rounded transition">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
              </svg>
            </button>
            <button @click="eliminarProvincia(prov)"
              class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded transition">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
              </svg>
            </button>
          </div>
        </div>

        <!-- Ciudades (expandible) -->
        <div v-if="abiertos.has(prov.id)" class="border-t border-gray-100 px-4 py-3">
          <div class="flex flex-wrap gap-2 mb-3">
            <div v-for="ciu in prov.ciudades" :key="ciu.id"
              class="flex items-center gap-1.5 bg-gray-100 rounded-full px-3 py-1 text-sm text-gray-700">
              <span>{{ ciu.nombre }}</span>
              <button @click="abrirEditarCiudad(prov, ciu)"
                class="text-gray-400 hover:text-[#5c4a6e] transition">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
              </button>
              <button @click="eliminarCiudad(prov, ciu)"
                class="text-gray-400 hover:text-red-500 transition">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
            <div v-if="prov.ciudades.length === 0"
              class="text-xs text-gray-400 italic">Sin ciudades registradas</div>
          </div>
          <!-- Inline agregar ciudad -->
          <div class="flex gap-2 items-center">
            <input v-model="nuevaCiudad[prov.id]" type="text" placeholder="Nueva ciudad..."
              @keyup.enter="agregarCiudad(prov)"
              class="text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e] flex-1 max-w-[220px]" style="text-transform:uppercase"/>
            <button @click="agregarCiudad(prov)"
              class="text-sm font-semibold text-white px-3 py-1.5 rounded bg-[#5c4a6e] hover:opacity-90 transition">
              + Agregar
            </button>
          </div>
        </div>
      </div>

      <div v-if="provincias.length === 0" class="text-center py-16 text-gray-400 text-sm">
        No hay provincias registradas.
      </div>
    </div>

    <!-- Modal Provincia -->
    <div v-if="modalProv.show" class="fixed inset-0 z-50 flex items-center justify-center px-4">
      <div class="fixed inset-0 bg-black/40" @click="modalProv.show = false"/>
      <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-sm overflow-hidden z-10">
        <div class="flex items-center justify-between px-5 py-4" style="background-color:#5c4a6e;">
          <h2 class="text-sm font-bold text-white">{{ modalProv.id ? 'Editar Provincia' : 'Nueva Provincia' }}</h2>
          <button type="button" @click="modalProv.show = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-5 space-y-3">
          <div>
            <label class="text-xs font-semibold text-gray-600 mb-1 block">Nombre *</label>
            <input v-model="modalProv.nombre" type="text" maxlength="100"
              @keyup.enter="guardarProvincia"
              class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]" style="text-transform:uppercase"/>
          </div>
          <p v-if="modalProv.error" class="text-red-600 text-xs">{{ modalProv.error }}</p>
          <div class="flex justify-end gap-3 pt-1">
            <button @click="modalProv.show = false" class="px-4 py-2 text-sm text-gray-600">Cancelar</button>
            <button @click="guardarProvincia" :disabled="modalProv.guardando"
              class="px-4 py-2 text-sm font-semibold text-white rounded-lg bg-[#5c4a6e] hover:opacity-90 disabled:opacity-50">
              {{ modalProv.guardando ? 'Guardando...' : 'Guardar' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Ciudad (editar) -->
    <div v-if="modalCiu.show" class="fixed inset-0 z-50 flex items-center justify-center px-4">
      <div class="fixed inset-0 bg-black/40" @click="modalCiu.show = false"/>
      <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-sm overflow-hidden z-10">
        <div class="flex items-center justify-between px-5 py-4" style="background-color:#5c4a6e;">
          <h2 class="text-sm font-bold text-white">Editar Ciudad — {{ modalCiu.provNombre }}</h2>
          <button type="button" @click="modalCiu.show = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-5 space-y-3">
          <div>
            <label class="text-xs font-semibold text-gray-600 mb-1 block">Nombre *</label>
            <input v-model="modalCiu.nombre" type="text" maxlength="100"
              @keyup.enter="guardarCiudad"
              class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]" style="text-transform:uppercase"/>
          </div>
          <p v-if="modalCiu.error" class="text-red-600 text-xs">{{ modalCiu.error }}</p>
          <div class="flex justify-end gap-3 pt-1">
            <button @click="modalCiu.show = false" class="px-4 py-2 text-sm text-gray-600">Cancelar</button>
            <button @click="guardarCiudad" :disabled="modalCiu.guardando"
              class="px-4 py-2 text-sm font-semibold text-white rounded-lg bg-[#5c4a6e] hover:opacity-90 disabled:opacity-50">
              {{ modalCiu.guardando ? 'Guardando...' : 'Guardar' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, reactive } from 'vue'
import api from '@/services/api'

const cargando  = ref(true)
const provincias = ref([])
const abiertos  = ref(new Set())
const nuevaCiudad = reactive({})

const modalProv = ref({ show: false, id: null, nombre: '', error: '', guardando: false })
const modalCiu  = ref({ show: false, id: null, provId: null, provNombre: '', nombre: '', error: '', guardando: false })

async function cargar() {
  cargando.value = true
  try {
    const { data } = await api.get('/comisiones/admin/provincias')
    provincias.value = data
  } finally {
    cargando.value = false
  }
}

function toggleProvincia(id) {
  if (abiertos.value.has(id)) abiertos.value.delete(id)
  else abiertos.value.add(id)
  abiertos.value = new Set(abiertos.value)
}

// ── Provincia ──────────────────────────────────────────────
function abrirNuevaProvincia() {
  modalProv.value = { show: true, id: null, nombre: '', error: '', guardando: false }
}
function abrirEditarProvincia(prov) {
  modalProv.value = { show: true, id: prov.id, nombre: prov.nombre, error: '', guardando: false }
}
async function guardarProvincia() {
  if (!modalProv.value.nombre.trim()) { modalProv.value.error = 'El nombre es obligatorio.'; return }
  modalProv.value.guardando = true
  modalProv.value.error = ''
  try {
    if (modalProv.value.id) {
      await api.put(`/comisiones/admin/provincias/${modalProv.value.id}`, { nombre: modalProv.value.nombre })
    } else {
      await api.post('/comisiones/admin/provincias', { nombre: modalProv.value.nombre })
    }
    modalProv.value.show = false
    await cargar()
  } catch (e) {
    modalProv.value.error = e.response?.data?.message || 'Error al guardar.'
  } finally {
    modalProv.value.guardando = false
  }
}
async function eliminarProvincia(prov) {
  if (!confirm(`¿Eliminar la provincia "${prov.nombre}" y todas sus ciudades?`)) return
  try {
    await api.delete(`/comisiones/admin/provincias/${prov.id}`)
    await cargar()
  } catch (e) {
    alert(e.response?.data?.message || 'Error al eliminar.')
  }
}

// ── Ciudad ─────────────────────────────────────────────────
async function agregarCiudad(prov) {
  const nombre = (nuevaCiudad[prov.id] || '').trim()
  if (!nombre) return
  try {
    await api.post(`/comisiones/admin/provincias/${prov.id}/ciudades`, { nombre })
    nuevaCiudad[prov.id] = ''
    await cargar()
  } catch (e) {
    alert(e.response?.data?.message || 'Error al agregar ciudad.')
  }
}
function abrirEditarCiudad(prov, ciu) {
  modalCiu.value = { show: true, id: ciu.id, provId: prov.id, provNombre: prov.nombre, nombre: ciu.nombre, error: '', guardando: false }
}
async function guardarCiudad() {
  if (!modalCiu.value.nombre.trim()) { modalCiu.value.error = 'El nombre es obligatorio.'; return }
  modalCiu.value.guardando = true
  modalCiu.value.error = ''
  try {
    await api.put(`/comisiones/admin/ciudades/${modalCiu.value.id}`, { nombre: modalCiu.value.nombre })
    modalCiu.value.show = false
    await cargar()
  } catch (e) {
    modalCiu.value.error = e.response?.data?.message || 'Error al guardar.'
  } finally {
    modalCiu.value.guardando = false
  }
}
async function eliminarCiudad(prov, ciu) {
  if (!confirm(`¿Eliminar "${ciu.nombre}" de ${prov.nombre}?`)) return
  try {
    await api.delete(`/comisiones/admin/ciudades/${ciu.id}`)
    await cargar()
  } catch (e) {
    alert(e.response?.data?.message || 'Error al eliminar.')
  }
}

onMounted(cargar)
</script>
