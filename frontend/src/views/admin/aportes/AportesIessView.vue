<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-800">Aportes IESS</h1>
      <button @click="abrirModal"
        class="bg-[#0b5447] text-white px-4 py-2 rounded-lg hover:bg-[#00372e] text-sm font-medium">
        + Registrar Cambio
      </button>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-700">
      Historial de tasas de aporte al IESS por modalidad laboral. Cuando el gobierno cambie los porcentajes,
      registra el nuevo valor con su fecha de vigencia — el sistema cerrará automáticamente la tasa anterior.
      Los valores de IECE y SECAP en cada fila controlan qué se calcula para esa modalidad; si el valor es 0, no se calcula.
    </div>

    <!-- Tabla historial -->
    <div class="bg-white rounded-xl shadow overflow-x-auto">
      <table class="w-full text-sm whitespace-nowrap">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Modalidad</th>
            <th class="text-right px-4 py-3 text-gray-600 font-medium">Ap. Individual</th>
            <th class="text-right px-4 py-3 text-gray-600 font-medium">Ap. Patronal</th>
            <th class="text-right px-4 py-3 text-blue-600 font-medium">IECE Patronal</th>
            <th class="text-right px-4 py-3 text-blue-600 font-medium">IECE Personal</th>
            <th class="text-right px-4 py-3 text-blue-600 font-medium">SECAP Patronal</th>
            <th class="text-right px-4 py-3 text-blue-600 font-medium">SECAP Personal</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Desde</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Hasta</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Estado</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando">
            <td colspan="11" class="text-center py-8 text-gray-400">Cargando...</td>
          </tr>
          <tr v-else-if="aportes.length === 0">
            <td colspan="11" class="text-center py-8 text-gray-400">Sin registros.</td>
          </tr>
          <tr v-for="a in aportes" :key="a.id_aporte" class="border-b hover:bg-gray-50">
            <td class="px-4 py-3 font-medium text-gray-800">{{ a.modalidad }}</td>
            <td class="px-4 py-3 text-right font-mono">{{ a.aporte_individual }}%</td>
            <td class="px-4 py-3 text-right font-mono">{{ a.aporte_patronal }}%</td>
            <td class="px-4 py-3 text-right font-mono text-blue-700">{{ pct(a.iece_patronal) }}</td>
            <td class="px-4 py-3 text-right font-mono text-blue-700">{{ pct(a.iece_personal) }}</td>
            <td class="px-4 py-3 text-right font-mono text-blue-700">{{ pct(a.secap_patronal) }}</td>
            <td class="px-4 py-3 text-right font-mono text-blue-700">{{ pct(a.secap_personal) }}</td>
            <td class="px-4 py-3">{{ fmtFecha(a.fecha_desde) }}</td>
            <td class="px-4 py-3">{{ a.fecha_hasta ? fmtFecha(a.fecha_hasta) : '—' }}</td>
            <td class="px-4 py-3">
              <span v-if="!a.fecha_hasta"
                class="bg-green-100 text-green-700 px-2 py-0.5 rounded-full text-xs font-medium">Vigente</span>
              <span v-else
                class="bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full text-xs">Histórico</span>
            </td>
            <td class="px-4 py-3">
              <button @click="abrirEdicion(a)" class="text-blue-600 hover:underline text-xs font-medium mr-3">Editar</button>
              <button @click="eliminar(a)" class="text-red-500 hover:underline text-xs font-medium">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="modal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-lg overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4" style="background-color:#0b5447;">
          <h2 class="text-lg font-semibold text-white">{{ editandoId ? 'Editar Aporte' : 'Registrar Cambio de Tasas' }}</h2>
          <button @click="modal = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-6 space-y-4">
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-xs text-yellow-700">
          Al guardar, la tasa vigente anterior de la misma modalidad se cerrará automáticamente.
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Modalidad *</label>
          <input v-model="form.modalidad" list="modalidades-list" placeholder="Seleccionar o escribir nueva..."
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          <datalist id="modalidades-list">
            <option v-for="m in modalidadesUnicas" :key="m" :value="m" />
          </datalist>
        </div>

        <!-- Base -->
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Aporte Individual (%) *</label>
            <input v-model="form.aporte_individual" type="number" step="0.01" min="0" max="100"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Aporte Patronal (%) *</label>
            <input v-model="form.aporte_patronal" type="number" step="0.01" min="0" max="100"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
        </div>

        <!-- IECE -->
        <p class="text-xs font-semibold text-blue-700 uppercase tracking-wide pt-1">IECE</p>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">IECE Patronal (%)</label>
            <input v-model="form.iece_patronal" type="number" step="0.01" min="0" max="100"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">IECE Personal (%)</label>
            <input v-model="form.iece_personal" type="number" step="0.01" min="0" max="100"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
        </div>

        <!-- SECAP -->
        <p class="text-xs font-semibold text-blue-700 uppercase tracking-wide pt-1">SECAP</p>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">SECAP Patronal (%)</label>
            <input v-model="form.secap_patronal" type="number" step="0.01" min="0" max="100"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">SECAP Personal (%)</label>
            <input v-model="form.secap_personal" type="number" step="0.01" min="0" max="100"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Vigente desde *</label>
          <input v-model="form.fecha_desde" type="date"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
        </div>

        <div v-if="error" class="text-red-600 text-sm bg-red-50 rounded p-2">{{ error }}</div>

        <div class="flex justify-end gap-3 pt-2">
          <button @click="modal = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
          <button @click="guardar" :disabled="guardando"
            class="px-4 py-2 rounded-lg bg-[#0b5447] text-white text-sm hover:bg-[#00372e] disabled:opacity-50">
            {{ guardando ? "Guardando..." : (editandoId ? "Actualizar" : "Guardar") }}
          </button>
        </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue"
import api from "@/services/api"

const aportes    = ref([])
const cargando   = ref(false)
const modal      = ref(false)
const guardando  = ref(false)
const error      = ref("")
const editandoId = ref(null)

const emptyForm = () => ({
  modalidad: "", aporte_individual: "", aporte_patronal: "",
  iece_patronal: 0, iece_personal: 0, secap_patronal: 0, secap_personal: 0,
  fecha_desde: "",
})
const form = ref(emptyForm())

const modalidadesUnicas = computed(() => [...new Set(aportes.value.map(a => a.modalidad))])

const fmtFecha = (f) => {
  if (!f) return "—"
  const d = f.substring(0, 10).split("-")
  return `${d[2]}/${d[1]}/${d[0]}`
}
const pct = (v) => parseFloat(v) > 0 ? parseFloat(v) + '%' : '—'

const cargar = async () => {
  cargando.value = true
  try {
    const { data } = await api.get("/admin/aportes-iess")
    aportes.value = data
  } finally {
    cargando.value = false
  }
}

const abrirModal = () => {
  error.value = ""
  editandoId.value = null
  form.value = emptyForm()
  modal.value = true
}

const abrirEdicion = (a) => {
  error.value = ""
  editandoId.value = a.id_aporte
  form.value = {
    modalidad:         a.modalidad,
    aporte_individual: a.aporte_individual,
    aporte_patronal:   a.aporte_patronal,
    iece_patronal:     parseFloat(a.iece_patronal)  || 0,
    iece_personal:     parseFloat(a.iece_personal)  || 0,
    secap_patronal:    parseFloat(a.secap_patronal) || 0,
    secap_personal:    parseFloat(a.secap_personal) || 0,
    fecha_desde:       a.fecha_desde?.substring(0, 10) ?? "",
  }
  modal.value = true
}

const guardar = async () => {
  if (!form.value.modalidad?.trim() || form.value.aporte_individual === '' || form.value.aporte_patronal === '' || !form.value.fecha_desde) {
    error.value = "Modalidad, aportes base y fecha son requeridos."
    return
  }
  guardando.value = true
  error.value = ""
  try {
    if (editandoId.value) {
      await api.put(`/admin/aportes-iess/${editandoId.value}`, form.value)
    } else {
      await api.post("/admin/aportes-iess", form.value)
    }
    modal.value = false
    cargar()
  } catch (e) {
    error.value = e.response?.data?.message || "Error al guardar."
  } finally {
    guardando.value = false
  }
}

const eliminar = async (a) => {
  if (!confirm(`¿Eliminar el aporte ${a.modalidad}?`)) return
  try {
    await api.delete(`/admin/aportes-iess/${a.id_aporte}`)
    cargar()
  } catch (e) {
    alert(e.response?.data?.message || 'Error al eliminar.')
  }
}

onMounted(cargar)
</script>
