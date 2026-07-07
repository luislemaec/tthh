<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-800">Acciones de Personal</h1>
      <router-link to="/acciones-personal/nueva"
        class="bg-[#0b5447] text-white px-4 py-2 rounded-lg hover:bg-[#00372e] text-sm font-medium inline-flex items-center gap-1.5">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        Nueva Acción
      </router-link>
    </div>

    <!-- Filtros + exportar -->
    <div class="bg-white rounded-xl shadow p-4">
      <div class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-[200px]">
          <label class="block text-xs font-medium text-gray-500 mb-1">Buscar</label>
          <input v-model="filtro.buscar" type="text" placeholder="Empleado o Nro. acción..."
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]"
            @input="cargar" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Tipo</label>
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
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Estado</label>
          <select v-model="filtro.estado" @change="cargar"
            class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
            <option value="">Todos los estados</option>
            <option value="BORRADOR">Borrador</option>
            <option value="ACTIVO">Activo</option>
            <option value="FINALIZADO">Finalizado</option>
            <option value="ANULADO">Anulado</option>
          </select>
        </div>
        <div class="flex gap-2 ml-auto">
          <button @click="exportar('pdf')" :disabled="exportando"
            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg border border-red-200 text-red-700 text-sm hover:bg-red-50 font-medium transition-colors disabled:opacity-50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
            PDF
          </button>
          <button @click="exportar('excel')" :disabled="exportando"
            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg border border-green-200 text-green-700 text-sm hover:bg-green-50 font-medium transition-colors disabled:opacity-50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h1.5C5.496 19.5 6 18.996 6 18.375m-3.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-1.5A1.125 1.125 0 0 1 18 18.375M20.625 4.5H3.375m17.25 0c.621 0 1.125.504 1.125 1.125M20.625 4.5h-1.5C18.504 4.5 18 5.004 18 5.625m3.75 0v1.5c0 .621-.504 1.125-1.125 1.125M3.375 4.5c-.621 0-1.125.504-1.125 1.125M3.375 4.5h1.5C5.496 4.5 6 5.004 6 5.625m-3.75 0v1.5c0 .621.504 1.125 1.125 1.125m0 0h1.5m-1.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m1.5-3.75C5.496 8.25 6 8.754 6 9.375v1.5m0-5.25v5.25m0-5.25C6 5.004 6.504 4.5 7.125 4.5h9.75c.621 0 1.125.504 1.125 1.125m1.125 2.625h1.5m-1.5 0A1.125 1.125 0 0 1 18 7.875v1.5m1.125-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125M18 5.625v5.25M7.125 12h9.75m-9.75 0A1.125 1.125 0 0 1 6 10.875M7.125 12C6.504 12 6 12.504 6 13.125m0-2.25C6 11.496 5.496 12 4.875 12M18 10.875c0 .621-.504 1.125-1.125 1.125M18 10.875c0 .621.504 1.125 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-9.75 0h9.75"/></svg>
            Excel
          </button>
        </div>
      </div>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr style="background-color:#0b5447;">
            <th class="text-left px-4 py-3 text-white/80 font-semibold text-[11px] uppercase tracking-wide">Nro. Acción</th>
            <th class="text-left px-4 py-3 text-white/80 font-semibold text-[11px] uppercase tracking-wide">Tipo</th>
            <th class="text-left px-4 py-3 text-white/80 font-semibold text-[11px] uppercase tracking-wide">Empleado</th>
            <th class="text-left px-4 py-3 text-white/80 font-semibold text-[11px] uppercase tracking-wide">Vigencia</th>
            <th class="text-left px-4 py-3 text-white/80 font-semibold text-[11px] uppercase tracking-wide">Estado</th>
            <th class="text-left px-4 py-3 text-white/80 font-semibold text-[11px] uppercase tracking-wide">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando">
            <td colspan="6" class="text-center py-10 text-gray-400">Cargando...</td>
          </tr>
          <tr v-else-if="acciones.length === 0">
            <td colspan="6" class="py-16 text-center">
              <div class="flex flex-col items-center gap-2 text-gray-300">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z"/></svg>
                <p class="text-sm text-gray-400">No hay acciones registradas.</p>
              </div>
            </td>
          </tr>
          <tr v-for="a in acciones" :key="a.id_accion"
            :class="a.estado === 'BORRADOR' ? 'border-b border-amber-100 bg-amber-50/40' : 'border-b hover:bg-gray-50'">
            <td class="px-4 py-3">
              <span v-if="a.numero_accion" class="font-mono font-semibold text-[#0b5447]">{{ a.numero_accion }}</span>
              <span v-else class="text-xs text-amber-600 italic font-medium">Sin número — Borrador</span>
            </td>
            <td class="px-4 py-3">
              <span :class="tipoBadge(a.tipo_accion)"
                class="px-2 py-0.5 rounded-md text-xs font-semibold border">
                {{ a.tipo_accion }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="font-medium text-gray-800">{{ a.empleado?.apellido_emp }}, {{ a.empleado?.nombre_emp }}</div>
              <div class="text-xs text-gray-400">{{ a.empleado?.cargo_empleado }}</div>
            </td>
            <td class="px-4 py-3 text-xs text-gray-600">
              <div>Desde: {{ fmtFecha(a.fecha_inicio) }}</div>
              <div>Hasta: {{ a.fecha_fin ? fmtFecha(a.fecha_fin) : 'Indefinido' }}</div>
            </td>
            <td class="px-4 py-3">
              <span :class="estadoClase(a)" class="px-2 py-0.5 rounded-md text-xs font-semibold border">
                {{ estadoLabel(a) }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex gap-1.5 flex-wrap">
                <!-- Acciones BORRADOR -->
                <template v-if="a.estado === 'BORRADOR'">
                  <button @click="abrirEditarBorrador(a)"
                    class="inline-flex items-center px-2.5 py-1 rounded-md border border-amber-200 text-xs text-amber-700 hover:bg-amber-50 font-medium transition-colors">
                    Editar
                  </button>
                  <button @click="verPdfBorrador(a.id_accion)"
                    class="inline-flex items-center px-2.5 py-1 rounded-md border border-gray-300 text-xs text-gray-600 hover:bg-gray-50 font-medium transition-colors">
                    Vista previa
                  </button>
                  <button @click="procesar(a.id_accion)"
                    class="inline-flex items-center px-2.5 py-1 rounded-md border border-[#0b5447] text-xs text-[#0b5447] hover:bg-green-50 font-medium transition-colors">
                    Procesar
                  </button>
                  <button @click="cambiarEstado(a.id_accion, 'ANULADO')"
                    class="inline-flex items-center px-2.5 py-1 rounded-md border border-red-200 text-xs text-red-600 hover:bg-red-50 font-medium transition-colors">
                    Anular
                  </button>
                </template>

                <!-- Acciones PROCESADO (ACTIVO/FINALIZADO/ANULADO) -->
                <template v-else>
                  <button @click="descargarPdf(a.id_accion)"
                    class="inline-flex items-center px-2.5 py-1 rounded-md border border-[#0b5447] text-xs text-[#0b5447] hover:bg-green-50 font-medium transition-colors">
                    PDF
                  </button>
                  <button v-if="a.pdf_firmado" @click="descargarFirmado(a.id_accion)"
                    class="inline-flex items-center px-2.5 py-1 rounded-md border border-indigo-200 text-xs text-indigo-700 hover:bg-indigo-50 font-medium transition-colors">
                    Firmado
                  </button>
                  <label v-else class="inline-flex items-center px-2.5 py-1 rounded-md border border-gray-200 text-xs text-gray-500 hover:bg-gray-50 font-medium transition-colors cursor-pointer">
                    Subir firmado
                    <input type="file" accept=".pdf" class="hidden" @change="subirFirmado(a.id_accion, $event)" />
                  </label>
                  <button v-if="a.estado === 'ACTIVO'" @click="abrirModalFinalizar(a.id_accion)"
                    class="inline-flex items-center px-2.5 py-1 rounded-md border border-green-200 text-xs text-green-700 hover:bg-green-50 font-medium transition-colors">
                    Finalizar
                  </button>
                  <button v-if="a.estado === 'ACTIVO'" @click="cambiarEstado(a.id_accion, 'ANULADO')"
                    class="inline-flex items-center px-2.5 py-1 rounded-md border border-red-200 text-xs text-red-600 hover:bg-red-50 font-medium transition-colors">
                    Anular
                  </button>
                </template>
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
          class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
        <button @click="confirmarFinalizar"
          class="px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-medium hover:bg-green-700">Confirmar</button>
      </div>
    </div>
  </div>

  <!-- Modal Editar Borrador -->
  <div v-if="modalEditar.show" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-2xl space-y-4 overflow-y-auto max-h-[90vh]">
      <h3 class="text-lg font-semibold text-gray-800">Editar Borrador</h3>
      <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">Fecha de elaboración *</label>
        <input v-model="modalEditar.fecha_elaboracion" type="date"
          class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-600 mb-1">Motivación / Observaciones</label>
        <TipTapEditor v-model="modalEditar.motivacion" minHeight="120px" />
      </div>
      <!-- Firmantes -->
      <div class="border-t pt-4 space-y-3">
        <p class="text-sm font-medium text-gray-700">Responsables de Aprobación</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Nombre — Talento Humano</label>
            <input v-model="modalEditar.firmante_th_nombre" type="text"
              style="text-transform:uppercase"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Cargo — Talento Humano</label>
            <input v-model="modalEditar.firmante_th_cargo" type="text"
              style="text-transform:uppercase"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Nombre — Autoridad Nominadora</label>
            <input v-model="modalEditar.firmante_autoridad_nombre" type="text"
              style="text-transform:uppercase"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Cargo — Autoridad Nominadora</label>
            <input v-model="modalEditar.firmante_autoridad_cargo" type="text"
              style="text-transform:uppercase"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
        </div>
      </div>
      <div class="flex justify-end gap-3 pt-2">
        <button @click="modalEditar.show = false"
          class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
        <button @click="guardarBorrador"
          class="px-4 py-2 rounded-lg bg-[#0b5447] text-white text-sm font-medium hover:bg-[#00372e]">Guardar</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue"
import api from "@/services/api"
import TipTapEditor from "@/components/TipTapEditor.vue"

const acciones       = ref([])
const cargando       = ref(false)
const exportando     = ref(false)
const pagina         = ref(1)
const paginacion     = ref({ current_page: 1, last_page: 1 })
const filtro         = ref({ buscar: "", tipo_accion: "", estado: "" })
const modalFinalizar = ref({ show: false, id: null, fecha: "" })
const modalEditar    = ref({
  show: false, id: null,
  fecha_elaboracion: "", motivacion: "",
  firmante_th_nombre: "", firmante_th_cargo: "",
  firmante_autoridad_nombre: "", firmante_autoridad_cargo: "",
})

const fmtFecha = (f) => {
  if (!f) return "—"
  const d = f.substring(0, 10).split("-")
  return `${d[2]}/${d[1]}/${d[0]}`
}

const hoy = new Date().toISOString().substring(0, 10)

const estadoLabel = (a) => {
  if (a.estado === 'BORRADOR')   return 'Borrador'
  if (a.estado === 'ANULADO')    return 'Anulado'
  if (a.estado === 'FINALIZADO') return 'Finalizado'
  if (a.fecha_fin && a.fecha_fin.substring(0, 10) < hoy) return 'Vencido'
  return 'Vigente'
}

const estadoClase = (a) => {
  if (a.estado === 'BORRADOR')   return 'bg-amber-50 text-amber-700 border-amber-200'
  if (a.estado === 'ANULADO')    return 'bg-red-100 text-red-700 border-red-200'
  if (a.estado === 'FINALIZADO') return 'bg-gray-100 text-gray-600 border-gray-200'
  if (a.fecha_fin && a.fecha_fin.substring(0, 10) < hoy) return 'bg-orange-100 text-orange-700 border-orange-200'
  return 'bg-green-100 text-green-700 border-green-200'
}

const tipoBadge = (tipo) => ({
  'ENCARGO':               'bg-blue-100 text-blue-700 border-blue-200',
  'SUBROGACION':           'bg-purple-100 text-purple-700 border-purple-200',
  'INGRESO':               'bg-emerald-100 text-emerald-700 border-emerald-200',
  'VACACIONES':            'bg-sky-100 text-sky-700 border-sky-200',
  'DESTITUCION':           'bg-red-100 text-red-700 border-red-200',
  'CESACION DE FUNCIONES': 'bg-amber-100 text-amber-700 border-amber-200',
}[tipo] ?? 'bg-gray-100 text-gray-600 border-gray-200')

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
  modalFinalizar.value = { show: true, id, fecha: new Date().toISOString().substring(0, 10) }
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
  if (!confirm(`¿${estado === 'ANULADO' ? 'Anular' : 'Cambiar estado de'} esta acción de personal?`)) return
  try {
    await api.patch(`/acciones-personal/${id}/estado`, { estado })
    cargar()
  } catch (e) {
    alert(e.response?.data?.message || "Error al cambiar estado")
  }
}

const procesar = async (id) => {
  if (!confirm("¿Procesar esta acción? Se asignará el número correlativo y ya no se podrá editar.")) return
  try {
    const { data } = await api.patch(`/acciones-personal/${id}/procesar`)
    alert(data.message)
    cargar()
  } catch (e) {
    alert(e.response?.data?.message || "Error al procesar")
  }
}

const abrirEditarBorrador = (a) => {
  modalEditar.value = {
    show:                      true,
    id:                        a.id_accion,
    fecha_elaboracion:         a.fecha_elaboracion?.substring(0, 10) ?? "",
    motivacion:                a.motivacion ?? "",
    firmante_th_nombre:        a.firmante_th_nombre        ?? "",
    firmante_th_cargo:         a.firmante_th_cargo         ?? "",
    firmante_autoridad_nombre: a.firmante_autoridad_nombre ?? "",
    firmante_autoridad_cargo:  a.firmante_autoridad_cargo  ?? "",
  }
}

const guardarBorrador = async () => {
  if (!modalEditar.value.fecha_elaboracion) { alert("La fecha de elaboración es obligatoria."); return }
  try {
    await api.patch(`/acciones-personal/${modalEditar.value.id}/editar-borrador`, {
      fecha_elaboracion:         modalEditar.value.fecha_elaboracion,
      motivacion:                modalEditar.value.motivacion,
      firmante_th_nombre:        modalEditar.value.firmante_th_nombre,
      firmante_th_cargo:         modalEditar.value.firmante_th_cargo,
      firmante_autoridad_nombre: modalEditar.value.firmante_autoridad_nombre,
      firmante_autoridad_cargo:  modalEditar.value.firmante_autoridad_cargo,
    })
    modalEditar.value.show = false
    cargar()
  } catch (e) {
    alert(e.response?.data?.message || "Error al guardar")
  }
}

const verPdfBorrador = async (id) => {
  try {
    const response = await api.get(`/acciones-personal/${id}/pdf`, { responseType: "blob" })
    const url = URL.createObjectURL(new Blob([response.data], { type: "application/pdf" }))
    window.open(url, "_blank")
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch (e) {
    alert("Error al generar la vista previa")
  }
}

const exportar = async (formato) => {
  exportando.value = true
  try {
    const ruta = `/acciones-personal/reporte/${formato}`
    const response = await api.get(ruta, {
      params: { ...filtro.value },
      responseType: "blob",
    })
    const mime = formato === "pdf"
      ? "application/pdf"
      : "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
    const ext  = formato === "pdf" ? "pdf" : "xlsx"
    const url  = window.URL.createObjectURL(new Blob([response.data], { type: mime }))
    const link = document.createElement("a")
    link.href  = url
    link.setAttribute("download", `acciones_personal.${ext}`)
    document.body.appendChild(link)
    link.click()
    link.remove()
  } catch (e) {
    alert("Error al generar el reporte")
  } finally {
    exportando.value = false
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
