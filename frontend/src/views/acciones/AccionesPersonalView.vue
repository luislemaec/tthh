<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-800">Acciones de Personal</h1>
      <router-link to="/acciones-personal/nueva"
        class="bg-[#0b5447] text-white px-4 py-2 rounded-lg hover:bg-[#00372e] text-sm font-medium">
        + Nueva Acción
      </router-link>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow p-4 flex flex-wrap gap-3">
      <input v-model="filtro.buscar" type="text" placeholder="Buscar por empleado o Nro. acción..."
        class="border rounded-lg px-3 py-2 text-sm flex-1 min-w-[200px] focus:outline-none focus:ring-2 focus:ring-[#579186]"
        @input="cargar" />
      <select v-model="filtro.tipo_accion" @change="cargar"
        class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
        <option value="">Todos los tipos</option>
        <option value="INGRESO">Ingreso</option>
        <option value="ENCARGO">Encargo</option>
        <option value="SUBROGACION">Subrogación</option>
        <option value="VACACIONES">Vacaciones</option>
        <option value="DESTITUCION">Destitución</option>
        <option value="CESACION DE FUNCIONES">Cesación de Funciones</option>
      </select>
      <select v-model="filtro.estado" @change="cargar"
        class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
        <option value="">Todos los estados</option>
        <option value="ACTIVO">Activo</option>
        <option value="FINALIZADO">Finalizado</option>
        <option value="ANULADO">Anulado</option>
      </select>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Nro. Acción</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Tipo</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Empleado</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Vigencia</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Estado</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando">
            <td colspan="6" class="text-center py-8 text-gray-400">Cargando...</td>
          </tr>
          <tr v-else-if="acciones.length === 0">
            <td colspan="6" class="text-center py-8 text-gray-400">No hay acciones registradas.</td>
          </tr>
          <tr v-for="a in acciones" :key="a.id_accion" class="border-b hover:bg-gray-50">
            <td class="px-4 py-3 font-mono font-medium text-[#0b5447]">{{ a.numero_accion }}</td>
            <td class="px-4 py-3">
              <span :class="a.tipo_accion === 'ENCARGO' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'"
                class="px-2 py-0.5 rounded-full text-xs font-medium">
                {{ a.tipo_accion }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="font-medium">{{ a.empleado?.apellido_emp }}, {{ a.empleado?.nombre_emp }}</div>
              <div class="text-xs text-gray-400">{{ a.empleado?.cargo_empleado }}</div>
            </td>
            <td class="px-4 py-3 text-xs">
              <div>Desde: {{ fmtFecha(a.fecha_inicio) }}</div>
              <div>Hasta: {{ a.fecha_fin ? fmtFecha(a.fecha_fin) : 'Indefinido' }}</div>
            </td>
            <td class="px-4 py-3">
              <span :class="estadoClase(a)" class="px-2 py-0.5 rounded-full text-xs font-medium">
                {{ estadoLabel(a) }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex gap-2 flex-wrap">
                <button @click="descargarPdf(a.id_accion)"
                  class="text-[#0b5447] hover:underline text-xs font-medium">PDF</button>
                <button v-if="a.pdf_firmado" @click="descargarFirmado(a.id_accion)"
                  class="text-indigo-600 hover:underline text-xs font-medium">Firmado</button>
                <label v-else class="text-gray-400 hover:text-indigo-600 cursor-pointer text-xs font-medium">
                  Subir firmado
                  <input type="file" accept=".pdf" class="hidden" @change="subirFirmado(a.id_accion, $event)" />
                </label>
                <button v-if="a.estado === 'ACTIVO'" @click="abrirModalFinalizar(a.id_accion)"
                  class="text-green-600 hover:underline text-xs font-medium">Finalizar</button>
                <button v-if="a.estado === 'ACTIVO'" @click="cambiarEstado(a.id_accion, 'ANULADO')"
                  class="text-red-500 hover:underline text-xs font-medium">Anular</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Paginación -->
      <div v-if="paginacion.last_page > 1" class="flex justify-center gap-2 p-4">
        <button v-for="p in paginacion.last_page" :key="p" @click="pagina = p; cargar()"
          :class="p === paginacion.current_page ? 'bg-[#0b5447] text-white' : 'bg-gray-100 text-gray-600'"
          class="px-3 py-1 rounded text-sm">{{ p }}</button>
      </div>
    </div>
  </div>

  <!-- Modal Finalizar Encargo -->
  <div v-if="modalFinalizar.show" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-sm space-y-4">
      <h3 class="text-lg font-semibold text-gray-800">Finalizar encargo</h3>
      <p class="text-sm text-gray-500">Ingrese la fecha en que finaliza el encargo de funciones.</p>
      <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">Fecha de fin *</label>
        <input v-model="modalFinalizar.fecha" type="date" required
          class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
      </div>
      <div class="flex justify-end gap-3 pt-2">
        <button @click="modalFinalizar.show = false"
          class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">
          Cancelar
        </button>
        <button @click="confirmarFinalizar"
          class="px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-medium hover:bg-green-700">
          Confirmar
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue"
import api from "@/services/api"

const acciones      = ref([])
const cargando      = ref(false)
const pagina        = ref(1)
const paginacion    = ref({ current_page: 1, last_page: 1 })
const filtro        = ref({ buscar: "", tipo_accion: "", estado: "" })
const modalFinalizar = ref({ show: false, id: null, fecha: "" })

const fmtFecha = (f) => {
  if (!f) return "—"
  const d = f.substring(0, 10).split("-")
  return `${d[2]}/${d[1]}/${d[0]}`
}

const hoy = new Date().toISOString().substring(0, 10)

const estadoLabel = (a) => {
  if (a.estado === 'ANULADO') return 'Anulado'
  if (a.estado === 'FINALIZADO') return 'Finalizado'
  if (a.fecha_fin && a.fecha_fin.substring(0, 10) < hoy) return 'Vencido'
  return 'Activo'
}

const estadoClase = (a) => {
  if (a.estado === 'ANULADO') return 'bg-red-100 text-red-700'
  if (a.estado === 'FINALIZADO') return 'bg-gray-100 text-gray-600'
  if (a.fecha_fin && a.fecha_fin.substring(0, 10) < hoy) return 'bg-orange-100 text-orange-700'
  return 'bg-green-100 text-green-700'
}

const cargar = async () => {
  cargando.value = true
  try {
    const { data } = await api.get("/acciones-personal", {
      params: { ...filtro.value, page: pagina.value }
    })
    acciones.value   = data.data
    paginacion.value = data
  } finally {
    cargando.value = false
  }
}

const abrirModalFinalizar = (id) => {
  modalFinalizar.value = {
    show: true,
    id,
    fecha: new Date().toISOString().substring(0, 10),
  }
}

const confirmarFinalizar = async () => {
  if (!modalFinalizar.value.fecha) { alert("Ingrese la fecha de fin."); return }
  try {
    await api.patch(`/acciones-personal/${modalFinalizar.value.id}/estado`, {
      estado: "FINALIZADO",
      fecha_fin: modalFinalizar.value.fecha,
    })
    modalFinalizar.value.show = false
    cargar()
  } catch (e) {
    alert(e.response?.data?.message || "Error al finalizar")
  }
}

const cambiarEstado = async (id, estado) => {
  if (!confirm('¿Anular esta acción de personal?')) return
  try {
    await api.patch(`/acciones-personal/${id}/estado`, { estado })
    cargar()
  } catch (e) {
    alert(e.response?.data?.message || "Error al cambiar estado")
  }
}

const subirFirmado = async (id, event) => {
  const file = event.target.files[0]
  if (!file) return
  const formData = new FormData()
  formData.append("archivo", file)
  try {
    await api.post(`/acciones-personal/${id}/subir-firmado`, formData, {
      headers: { "Content-Type": "multipart/form-data" }
    })
    cargar()
  } catch (e) {
    alert(e.response?.data?.message || "Error al subir el PDF firmado")
  }
  event.target.value = ""
}

const descargarFirmado = async (id) => {
  try {
    const response = await api.get(`/acciones-personal/${id}/descargar-firmado`, { responseType: "blob" })
    const url  = window.URL.createObjectURL(new Blob([response.data], { type: "application/pdf" }))
    const link = document.createElement("a")
    link.href  = url
    link.setAttribute("download", `accion_firmada_${id}.pdf`)
    document.body.appendChild(link)
    link.click()
    link.remove()
  } catch (e) {
    alert("Error al descargar el PDF firmado")
  }
}

const descargarPdf = async (id) => {
  try {
    const response = await api.get(`/acciones-personal/${id}/pdf`, { responseType: "blob" })
    const url  = window.URL.createObjectURL(new Blob([response.data], { type: "application/pdf" }))
    const link = document.createElement("a")
    link.href  = url
    link.setAttribute("download", `accion_personal_${id}.pdf`)
    document.body.appendChild(link)
    link.click()
    link.remove()
  } catch (e) {
    alert("Error al generar el PDF")
  }
}

onMounted(cargar)
</script>
