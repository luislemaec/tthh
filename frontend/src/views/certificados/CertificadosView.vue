<template>
  <div class="space-y-6">
    <h1 class="text-2xl font-bold text-gray-800">Certificados Laborales</h1>

    <!-- Panel principal: buscar empleado + generar -->
    <div class="bg-white rounded-xl shadow p-5">
      <h2 class="font-semibold text-gray-700 mb-4">Generar Nuevo Certificado</h2>
      <div class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-64">
          <label class="block text-xs text-gray-500 mb-1">Buscar empleado (nombre, apellido o cédula)</label>
          <input v-model="busquedaEmp" type="text" placeholder="Ingrese nombre o cédula..."
            @input="buscarEmpleado"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
        </div>
        <button @click="abrirModal" :disabled="!empleadoSeleccionado"
          class="bg-[#0b5447] text-white px-5 py-2 rounded-lg text-sm hover:bg-[#00372e] disabled:opacity-40 font-medium">
          Generar Certificado
        </button>
      </div>

      <!-- Resultados de búsqueda -->
      <div v-if="resultadosBusqueda.length > 0 && !empleadoSeleccionado"
        class="mt-2 border rounded-lg divide-y max-h-52 overflow-y-auto shadow-sm">
        <button v-for="emp in resultadosBusqueda" :key="emp.id_emp"
          @click="seleccionarEmpleado(emp)"
          class="w-full text-left px-4 py-3 hover:bg-[#f0faf8] text-sm transition">
          <span class="font-medium">{{ emp.apellido_emp }} {{ emp.nombre_emp }}</span>
          <span class="text-gray-400 ml-2 text-xs">{{ emp.identificacion }}</span>
          <span class="text-gray-500 text-xs block">{{ emp.cargo_empleado }} — {{ emp.nombre_depto }}</span>
        </button>
      </div>

      <!-- Empleado seleccionado -->
      <div v-if="empleadoSeleccionado"
        class="mt-3 flex items-center justify-between bg-[#f0faf8] border border-[#0b5447]/20 rounded-lg px-4 py-3">
        <div>
          <p class="font-semibold text-[#0b5447]">{{ empleadoSeleccionado.apellido_emp }} {{ empleadoSeleccionado.nombre_emp }}</p>
          <p class="text-sm text-gray-500">{{ empleadoSeleccionado.identificacion }} · {{ empleadoSeleccionado.cargo_empleado }}</p>
        </div>
        <button @click="limpiarSeleccion" class="text-gray-400 hover:text-gray-600 text-xs">Cambiar</button>
      </div>
    </div>

    <!-- Filtros historial -->
    <div class="bg-white rounded-xl shadow p-4 flex flex-wrap gap-3 items-end">
      <div>
        <label class="block text-xs text-gray-500 mb-1">Empleado</label>
        <input v-model="filtros.buscar" type="text" placeholder="Nombre, apellido o cédula..."
          class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186] w-56" />
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">Desde</label>
        <input v-model="filtros.fecha_desde" type="date"
          class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">Hasta</label>
        <input v-model="filtros.fecha_hasta" type="date"
          class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
      </div>
      <button @click="cargarHistorial"
        class="bg-[#0b5447] text-white px-5 py-2 rounded-lg text-sm hover:bg-[#00372e] font-medium">
        Buscar
      </button>
      <button @click="limpiarFiltros" class="border rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
        Limpiar
      </button>
    </div>

    <!-- Historial de certificados -->
    <div class="bg-white rounded-xl shadow overflow-hidden">
      <div class="px-6 py-4 border-b flex items-center justify-between">
        <h2 class="font-semibold text-gray-700">Historial de Certificados Emitidos</h2>
        <span class="text-sm text-gray-400">{{ total }} registros</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">N° Certificado</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Empleado</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Cargo</th>
              <th class="text-center px-4 py-3 text-gray-600 font-medium">Fecha Emisión</th>
              <th class="text-left px-4 py-3 text-gray-600 font-medium">Emitido por</th>
              <th class="text-center px-4 py-3 text-gray-600 font-medium">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="cargando">
              <td colspan="6" class="text-center py-10 text-gray-400">Cargando...</td>
            </tr>
            <tr v-else-if="certificados.length === 0">
              <td colspan="6" class="text-center py-10 text-gray-400">No hay certificados emitidos</td>
            </tr>
            <tr v-for="cert in certificados" :key="cert.id"
              class="border-b hover:bg-gray-50">
              <td class="px-4 py-3 font-mono text-xs font-medium text-[#0b5447]">
                {{ cert.numero }}
              </td>
              <td class="px-4 py-3 font-medium">
                {{ cert.empleado?.apellido_emp }} {{ cert.empleado?.nombre_emp }}
                <div class="text-xs text-gray-400">{{ cert.empleado?.identificacion }}</div>
              </td>
              <td class="px-4 py-3 text-gray-500 text-xs">{{ cert.empleado?.cargo_empleado || '—' }}</td>
              <td class="px-4 py-3 text-center text-xs font-mono">{{ cert.fecha_emision?.substring(0, 10) }}</td>
              <td class="px-4 py-3 text-gray-500 text-xs">
                {{ cert.emisor?.apellido_emp }} {{ cert.emisor?.nombre_emp }}
              </td>
              <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center gap-1">
                  <!-- Sin firmado: mostrar botón subir -->
                  <label v-if="!cert.alfresco_id"
                    class="inline-flex items-center gap-1 text-xs text-blue-600 border border-blue-300 px-2 py-1 rounded hover:bg-blue-50 cursor-pointer"
                    :class="{ 'opacity-50 pointer-events-none': subiendo === cert.id }">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                    </svg>
                    {{ subiendo === cert.id ? '...' : 'Subir firmado' }}
                    <input type="file" accept=".pdf" class="hidden"
                      @change="subirFirmado(cert, $event)" />
                  </label>
                  <!-- Con firmado: descargar -->
                  <button v-if="cert.alfresco_id" @click="descargar(cert)"
                    :disabled="descargando === cert.id"
                    class="inline-flex items-center gap-1 text-xs text-red-600 border border-red-300 px-2 py-1 rounded hover:bg-red-50 disabled:opacity-50">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    {{ descargando === cert.id ? '...' : 'PDF' }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Paginación -->
      <div v-if="totalPaginas > 1" class="px-4 py-3 border-t flex items-center gap-2 text-sm">
        <button @click="pagina--; cargarHistorial()" :disabled="pagina === 1"
          class="px-3 py-1 border rounded hover:bg-gray-50 disabled:opacity-40">‹</button>
        <span class="text-gray-500">Página {{ pagina }} de {{ totalPaginas }}</span>
        <button @click="pagina++; cargarHistorial()" :disabled="pagina >= totalPaginas"
          class="px-3 py-1 border rounded hover:bg-gray-50 disabled:opacity-40">›</button>
      </div>
    </div>

    <!-- Modal confirmación -->
    <div v-if="modalVisible"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4" style="background-color:#0b5447;">
          <h2 class="text-lg font-bold text-white">Confirmar emisión de certificado</h2>
          <button @click="modalVisible = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="px-6 py-5 space-y-3">
          <p class="text-sm text-gray-600">Se generará un certificado laboral para:</p>
          <div class="bg-[#f0faf8] rounded-lg p-4 space-y-1">
            <p class="font-bold text-[#0b5447]">
              {{ empleadoSeleccionado?.apellido_emp }} {{ empleadoSeleccionado?.nombre_emp }}
            </p>
            <p class="text-sm text-gray-600">{{ empleadoSeleccionado?.identificacion }}</p>
            <p class="text-sm text-gray-600">{{ empleadoSeleccionado?.cargo_empleado }}</p>
            <p class="text-sm text-gray-600">Sueldo: $ {{ formatSueldo(empleadoSeleccionado?.sueldo) }}</p>
          </div>
          <p class="text-xs text-gray-400">
            El PDF se generará, se subirá a Alfresco y se descargará automáticamente.
          </p>
          <div v-if="errorGenerar" class="bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2 rounded">
            {{ errorGenerar }}
          </div>
        </div>
        <div class="px-6 py-4 border-t flex justify-end gap-3">
          <button @click="modalVisible = false" :disabled="generando"
            class="border rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-50 disabled:opacity-50">
            Cancelar
          </button>
          <button @click="confirmarGenerar" :disabled="generando"
            class="bg-[#0b5447] text-white px-5 py-2 rounded-lg text-sm hover:bg-[#00372e] disabled:opacity-50 font-medium">
            {{ generando ? 'Generando...' : 'Generar y Descargar' }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed } from "vue"
import api from "@/services/api"

// --- Búsqueda de empleado ---
const busquedaEmp        = ref("")
const resultadosBusqueda = ref([])
const empleadoSeleccionado = ref(null)
let   busquedaTimer      = null

const buscarEmpleado = () => {
  if (empleadoSeleccionado.value) return
  clearTimeout(busquedaTimer)
  if (busquedaEmp.value.length < 2) { resultadosBusqueda.value = []; return }
  busquedaTimer = setTimeout(async () => {
    try {
      const { data } = await api.get("/empleados", {
        params: { buscar: busquedaEmp.value, per_page: 10 }
      })
      resultadosBusqueda.value = data.data ?? data
    } catch { resultadosBusqueda.value = [] }
  }, 300)
}

const seleccionarEmpleado = (emp) => {
  empleadoSeleccionado.value = emp
  busquedaEmp.value          = `${emp.apellido_emp} ${emp.nombre_emp}`
  resultadosBusqueda.value   = []
}

const limpiarSeleccion = () => {
  empleadoSeleccionado.value = null
  busquedaEmp.value          = ""
  resultadosBusqueda.value   = []
}

// --- Modal y generación ---
const modalVisible = ref(false)
const generando    = ref(false)
const errorGenerar = ref("")

const abrirModal = () => {
  errorGenerar.value = ""
  modalVisible.value = true
}

const formatSueldo = (v) => {
  if (!v) return "—"
  return Number(v).toFixed(2)
}

const confirmarGenerar = async () => {
  generando.value    = true
  errorGenerar.value = ""
  try {
    const resp = await api.post("/certificados-laborales",
      { id_emp: empleadoSeleccionado.value.id_emp },
      { responseType: "blob" }
    )
    const blob = new Blob([resp.data], { type: "application/pdf" })
    const url  = URL.createObjectURL(blob)
    const a    = document.createElement("a")
    a.href     = url
    const numero = resp.headers["x-numero"] || "certificado"
    a.download = `certificado_laboral_${numero}.pdf`
    a.click()
    setTimeout(() => URL.revokeObjectURL(url), 60000)
    modalVisible.value = false
    limpiarSeleccion()
    cargarHistorial()
  } catch (e) {
    try {
      // responseType blob: el error llega como Blob, hay que leerlo como texto
      const text = e.response?.data instanceof Blob
        ? await e.response.data.text()
        : null
      const json = text ? JSON.parse(text) : null
      errorGenerar.value = json?.message || e.response?.data?.message || "Error al generar el certificado"
    } catch {
      errorGenerar.value = "Error al generar el certificado"
    }
  } finally {
    generando.value = false
  }
}

// --- Historial ---
const certificados  = ref([])
const cargando      = ref(false)
const total         = ref(0)
const totalPaginas  = ref(1)
const pagina        = ref(1)
const descargando   = ref(null)
const subiendo      = ref(null)

const hoy           = new Date().toISOString().substring(0, 10)
const primerDiaMes  = new Date(new Date().getFullYear(), 0, 1).toISOString().substring(0, 10)

const filtros = ref({
  buscar: "",
  fecha_desde: primerDiaMes,
  fecha_hasta: hoy,
})

const cargarHistorial = async () => {
  cargando.value = true
  try {
    const params = { page: pagina.value }
    if (filtros.value.buscar)      params.id_emp      = filtros.value.buscar
    if (filtros.value.fecha_desde) params.fecha_desde = filtros.value.fecha_desde
    if (filtros.value.fecha_hasta) params.fecha_hasta = filtros.value.fecha_hasta
    const { data } = await api.get("/certificados-laborales", { params })
    certificados.value = data.data ?? data
    total.value       = data.total ?? certificados.value.length
    totalPaginas.value = data.last_page ?? 1
  } catch { certificados.value = [] }
  finally { cargando.value = false }
}

const limpiarFiltros = () => {
  filtros.value = { buscar: "", fecha_desde: primerDiaMes, fecha_hasta: hoy }
  pagina.value  = 1
  cargarHistorial()
}

const subirFirmado = async (cert, event) => {
  const file = event.target.files[0]
  if (!file) return
  subiendo.value = cert.id
  try {
    const form = new FormData()
    form.append("archivo", file)
    await api.post(`/certificados-laborales/${cert.id}/subir-firmado`, form, {
      headers: { "Content-Type": "multipart/form-data" }
    })
    await cargarHistorial()
  } catch (e) {
    alert(e.response?.data?.message || "No se pudo subir el archivo")
  } finally {
    subiendo.value = null
    event.target.value = ""
  }
}

const descargar = async (cert) => {
  descargando.value = cert.id
  try {
    const resp = await api.get(`/certificados-laborales/${cert.id}/descargar`, { responseType: "blob" })
    const blob = new Blob([resp.data], { type: "application/pdf" })
    const url  = URL.createObjectURL(blob)
    const a    = document.createElement("a")
    a.href     = url
    a.download = cert.nombre_archivo || `certificado_laboral_${cert.numero}.pdf`
    a.click()
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch { alert("No se pudo descargar el PDF") }
  finally { descargando.value = null }
}

// Cargar historial al montar
cargarHistorial()
</script>
