<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-800">Reporte de Planificación de Vacaciones</h1>
    </div>

    <!-- Selector de año -->
    <div class="bg-white rounded-xl shadow p-4 flex flex-wrap gap-3 items-end">
      <div>
        <label class="block text-xs text-gray-500 mb-1">Año</label>
        <input v-model.number="anio" type="number"
          class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186] w-28" />
      </div>
      <button @click="cargarEstado" :disabled="cargando"
        class="bg-[#0b5447] text-white px-5 py-2 rounded-lg text-sm hover:bg-[#00372e] disabled:opacity-50">
        {{ cargando ? 'Cargando...' : 'Consultar' }}
      </button>
    </div>

    <template v-if="datos">
      <!-- Banner estado general -->
      <div :class="datos.todo_aprobado
          ? 'bg-green-50 border border-green-300 text-green-800'
          : 'bg-orange-50 border border-orange-300 text-orange-800'"
        class="rounded-xl p-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
          <span class="text-2xl">{{ datos.todo_aprobado ? '✔' : '⚠' }}</span>
          <div>
            <p class="font-semibold text-sm">
              {{ datos.todo_aprobado
                ? 'Todas las planificaciones están aprobadas. Puede generar el PDF.'
                : 'Faltan planificaciones por aprobar.' }}
            </p>
            <p class="text-xs mt-0.5">Año {{ datos.anio }}</p>
          </div>
        </div>

        <!-- Acciones PDF -->
        <div class="flex gap-2">
          <button @click="generarPdf" :disabled="!datos.todo_aprobado || generando"
            class="bg-[#0b5447] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#00372e] disabled:opacity-40">
            {{ generando ? 'Generando...' : 'Generar y descargar PDF' }}
          </button>
        </div>
      </div>

      <!-- Sección PDF firmado -->
      <div class="bg-white rounded-xl shadow p-5 space-y-4">
        <h2 class="font-semibold text-gray-700">PDF Firmado Digitalmente</h2>

        <div v-if="datos.reporte" class="bg-green-50 border border-green-200 rounded-lg p-3 flex items-center justify-between text-sm">
          <div>
            <p class="font-medium text-green-800">Archivo firmado almacenado</p>
            <p class="text-xs text-green-600 mt-0.5">
              Subido el {{ formatFecha(datos.reporte.fecha_subida) }}
              por {{ datos.reporte.subido_por }}
            </p>
          </div>
          <button @click="descargarFirmado"
            class="bg-green-700 text-white px-4 py-1.5 rounded-lg text-xs hover:bg-green-800">
            Descargar PDF Firmado
          </button>
        </div>
        <p v-else class="text-sm text-gray-400">No hay PDF firmado almacenado para este año.</p>

        <!-- Subir PDF firmado -->
        <div class="border-t pt-4">
          <label class="block text-sm font-medium text-gray-600 mb-2">
            {{ datos.reporte ? 'Reemplazar PDF firmado' : 'Subir PDF firmado' }}
          </label>
          <div class="flex gap-3 items-center">
            <input ref="inputFile" type="file" accept=".pdf"
              class="text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-sm file:bg-[#0b5447] file:text-white hover:file:bg-[#00372e]"
              @change="onFileChange" />
            <button @click="subirFirmado" :disabled="!archivoSeleccionado || subiendo"
              class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700 disabled:opacity-40">
              {{ subiendo ? 'Subiendo...' : 'Subir a Alfresco' }}
            </button>
          </div>
          <p v-if="mensajeSubida" :class="errorSubida ? 'text-red-600' : 'text-green-700'"
            class="text-xs mt-2">{{ mensajeSubida }}</p>
        </div>
      </div>

      <!-- Tabla estado por departamento -->
      <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="px-5 py-3 border-b bg-gray-50">
          <h2 class="font-semibold text-gray-700">Estado por Departamento</h2>
        </div>
        <table class="w-full text-sm">
          <thead class="bg-[#0b5447] text-white">
            <tr>
              <th class="px-4 py-2 text-left font-medium">Departamento</th>
              <th class="px-4 py-2 text-center font-medium">Total</th>
              <th class="px-4 py-2 text-center font-medium">Aprobados</th>
              <th class="px-4 py-2 text-center font-medium">Pendientes</th>
              <th class="px-4 py-2 text-center font-medium">Sin Planificación</th>
              <th class="px-4 py-2 text-center font-medium">Estado</th>
              <th class="px-4 py-2 text-center font-medium"></th>
            </tr>
          </thead>
          <tbody>
            <template v-for="depto in datos.departamentos" :key="depto.id_depto">
              <tr class="border-b hover:bg-gray-50 cursor-pointer"
                @click="toggleDepto(depto.id_depto)">
                <td class="px-4 py-2 font-medium text-gray-800">{{ depto.nombre_depto }}</td>
                <td class="px-4 py-2 text-center text-gray-600">{{ depto.total }}</td>
                <td class="px-4 py-2 text-center text-green-700 font-medium">{{ depto.aprobados }}</td>
                <td class="px-4 py-2 text-center text-yellow-700 font-medium">{{ depto.pendientes }}</td>
                <td class="px-4 py-2 text-center text-red-600 font-medium">{{ depto.sin_plan }}</td>
                <td class="px-4 py-2 text-center">
                  <span v-if="depto.completo"
                    class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700 font-medium">Completo</span>
                  <span v-else
                    class="px-2 py-0.5 rounded-full text-xs bg-orange-100 text-orange-700 font-medium">Pendiente</span>
                </td>
                <td class="px-4 py-2 text-center text-gray-400 text-xs">
                  {{ deptoExpandido === depto.id_depto ? '▲' : '▼' }}
                </td>
              </tr>
              <!-- Detalle empleados del departamento -->
              <template v-if="deptoExpandido === depto.id_depto">
                <tr v-for="emp in depto.empleados" :key="emp.id_emp"
                  class="border-b bg-gray-50">
                  <td class="px-8 py-1.5 text-gray-700 text-xs">{{ emp.nombre }}</td>
                  <td colspan="4"></td>
                  <td class="px-4 py-1.5 text-center">
                    <span v-if="emp.estado_plan === 'APROBADO'"
                      class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700">Aprobado</span>
                    <span v-else-if="emp.estado_plan === 'PENDIENTE'"
                      class="px-2 py-0.5 rounded-full text-xs bg-yellow-100 text-yellow-700">Pendiente</span>
                    <span v-else-if="emp.estado_plan"
                      class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{{ emp.estado_plan }}</span>
                    <span v-else
                      class="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-700">Sin planificación</span>
                  </td>
                  <td></td>
                </tr>
              </template>
            </template>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import api from '@/services/api'

const anio             = ref(new Date().getFullYear())
const cargando         = ref(false)
const datos            = ref(null)
const generando        = ref(false)
const subiendo         = ref(false)
const archivoSeleccionado = ref(null)
const mensajeSubida    = ref('')
const errorSubida      = ref(false)
const deptoExpandido   = ref(null)
const inputFile        = ref(null)

const formatFecha = (fecha) => {
  if (!fecha) return ''
  return new Date(fecha).toLocaleDateString('es-EC', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

const toggleDepto = (id) => {
  deptoExpandido.value = deptoExpandido.value === id ? null : id
}

const cargarEstado = async () => {
  cargando.value = true
  datos.value    = null
  try {
    const { data } = await api.get(`/reporte-planificacion/${anio.value}/estado`)
    datos.value = data
  } catch (e) {
    alert(e.response?.data?.message || 'Error al cargar el estado')
  } finally {
    cargando.value = false
  }
}

const generarPdf = async () => {
  generando.value = true
  try {
    const resp = await api.get(`/reporte-planificacion/${anio.value}/pdf`, {
      responseType: 'blob',
    })
    const url  = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href  = url
    link.download = `planificacion_vacaciones_${anio.value}.pdf`
    link.click()
    URL.revokeObjectURL(url)
  } catch (e) {
    alert('Error al generar el PDF')
  } finally {
    generando.value = false
  }
}

const onFileChange = (e) => {
  archivoSeleccionado.value = e.target.files[0] || null
  mensajeSubida.value = ''
  errorSubida.value   = false
}

const subirFirmado = async () => {
  if (!archivoSeleccionado.value) return
  subiendo.value = true
  mensajeSubida.value = ''
  errorSubida.value   = false
  try {
    const form = new FormData()
    form.append('archivo', archivoSeleccionado.value)
    await api.post(`/reporte-planificacion/${anio.value}/subir-firmado`, form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    mensajeSubida.value       = 'Archivo subido correctamente a Alfresco.'
    archivoSeleccionado.value = null
    if (inputFile.value) inputFile.value.value = ''
    await cargarEstado()
  } catch (e) {
    errorSubida.value   = true
    mensajeSubida.value = e.response?.data?.message || 'Error al subir el archivo'
  } finally {
    subiendo.value = false
  }
}

const descargarFirmado = async () => {
  try {
    const resp = await api.get(`/reporte-planificacion/${anio.value}/descargar-firmado`, {
      responseType: 'blob',
    })
    const url  = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href  = url
    link.download = `planificacion_${anio.value}_firmado.pdf`
    link.click()
    URL.revokeObjectURL(url)
  } catch {
    alert('No se pudo descargar el archivo desde Alfresco')
  }
}
</script>
