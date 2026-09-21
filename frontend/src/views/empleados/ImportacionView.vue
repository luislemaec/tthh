<template>
  <div class="space-y-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between">
      <div class="flex items-center gap-3">
        <router-link to="/empleados"
          class="inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 transition-colors">
          ← Volver a Empleados
        </router-link>
        <h1 class="text-2xl font-bold text-gray-800">Importacion Masiva de Empleados</h1>
      </div>
      <button @click="descargarPlantilla"
        class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm font-medium">
        Descargar Plantilla CSV
      </button>
    </div>

    <!-- Paso 1: Subir archivo -->
    <div class="bg-white rounded-xl shadow p-6 space-y-4">
      <h2 class="text-lg font-semibold text-gray-700 border-b pb-2">
        Paso 1 — Seleccionar archivo CSV
      </h2>
      <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-blue-400 transition">
        <input type="file" ref="inputArchivo" accept=".csv,.txt"
          @change="seleccionarArchivo" class="hidden" />
        <div v-if="!archivo">
          <p class="text-4xl mb-3">📁</p>
          <p class="text-gray-500 text-sm mb-3">Arrastra tu archivo CSV aqui o haz clic para seleccionar</p>
          <button @click="inputArchivo.click()"
            class="bg-[#0b5447] text-white px-4 py-2 rounded-lg text-sm hover:bg-[#00372e]">
            Seleccionar archivo
          </button>
        </div>
        <div v-else>
          <p class="text-4xl mb-3">✅</p>
          <p class="font-medium text-gray-800">{{ archivo.name }}</p>
          <p class="text-gray-500 text-sm">{{ (archivo.size / 1024).toFixed(2) }} KB</p>
          <button @click="limpiar" class="text-red-500 hover:underline text-sm mt-2">Quitar archivo</button>
        </div>
      </div>

      <button @click="verPreview" :disabled="!archivo || cargandoPreview"
        class="w-full bg-[#0b5447] text-white py-3 rounded-lg hover:bg-[#00372e] disabled:opacity-50 font-medium">
        {{ cargandoPreview ? "Analizando archivo..." : "Ver Vista Previa" }}
      </button>
    </div>

    <!-- Paso 2: Vista previa -->
    <div v-if="preview" class="bg-white rounded-xl shadow p-6 space-y-4">
      <h2 class="text-lg font-semibold text-gray-700 border-b pb-2">
        Paso 2 — Vista Previa ({{ preview.total }} registros)
      </h2>

      <!-- Errores de validacion -->
      <div v-if="preview.errores.length"
        class="bg-red-50 border border-red-200 rounded-lg p-4">
        <p class="text-red-700 font-medium text-sm mb-2">
          ⚠️ Se encontraron {{ preview.errores.length }} advertencias:
        </p>
        <ul class="text-red-600 text-xs space-y-1">
          <li v-for="(e, i) in preview.errores" :key="i">• {{ e }}</li>
        </ul>
      </div>

      <!-- Tabla de preview -->
      <div class="overflow-x-auto">
        <table class="w-full text-xs">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-left px-3 py-2 text-gray-600">#</th>
              <th class="text-left px-3 py-2 text-gray-600">Cédula</th>
              <th class="text-left px-3 py-2 text-gray-600">Apellidos</th>
              <th class="text-left px-3 py-2 text-gray-600">Nombres</th>
              <th class="text-left px-3 py-2 text-gray-600">Depto</th>
              <th class="text-left px-3 py-2 text-gray-600">Contrato</th>
              <th class="text-left px-3 py-2 text-gray-600">Sueldo</th>
              <th class="text-left px-3 py-2 text-gray-600">Estado</th>
              <th class="text-left px-3 py-2 text-gray-600">F. Ingreso</th>
              <th class="text-left px-3 py-2 text-gray-600">Marcación</th>
              <th class="text-left px-3 py-2 text-gray-600">Acción</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(f, i) in preview.filas" :key="i"
              :class="f._existe ? 'bg-yellow-50' : 'hover:bg-gray-50'"
              class="border-b">
              <td class="px-3 py-2 text-gray-400">{{ i + 1 }}</td>
              <td class="px-3 py-2 font-mono">{{ f.identificacion }}</td>
              <td class="px-3 py-2">{{ f.apellido_emp }}</td>
              <td class="px-3 py-2">{{ f.nombre_emp }}</td>
              <td class="px-3 py-2">{{ f.id_depto }}</td>
              <td class="px-3 py-2">{{ f.tipo_contrato }}</td>
              <td class="px-3 py-2 text-right">{{ f.sueldo }}</td>
              <td class="px-3 py-2">
                <span :class="f.estado === 'ACTIVO' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                  class="px-2 py-0.5 rounded-full">{{ f.estado }}</span>
              </td>
              <td class="px-3 py-2">{{ f.fecha_ingreso }}</td>
              <td class="px-3 py-2">{{ f.modalidad_marcacion || 'PRESENCIAL' }}</td>
              <td class="px-3 py-2">
                <span v-if="f._existe" class="bg-yellow-100 text-yellow-700 px-2 py-0.5 rounded-full">
                  Actualizar
                </span>
                <span v-else class="bg-blue-100 text-[#0b5447] px-2 py-0.5 rounded-full">
                  Nuevo
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Resumen -->
      <div class="grid grid-cols-3 gap-4 pt-2">
        <div class="bg-blue-50 rounded-lg p-4 text-center">
          <p class="text-2xl font-bold text-[#0b5447]">{{ nuevos }}</p>
          <p class="text-sm text-[#0b5447]">Nuevos empleados</p>
        </div>
        <div class="bg-yellow-50 rounded-lg p-4 text-center">
          <p class="text-2xl font-bold text-yellow-700">{{ existentes }}</p>
          <p class="text-sm text-yellow-600">Se actualizaran</p>
        </div>
        <div class="bg-gray-50 rounded-lg p-4 text-center">
          <p class="text-2xl font-bold text-gray-700">{{ preview.total }}</p>
          <p class="text-sm text-gray-600">Total registros</p>
        </div>
      </div>

      <button @click="importar" :disabled="importando || preview.errores.length > 0"
        class="w-full bg-green-600 text-white py-3 rounded-lg hover:bg-green-700 disabled:opacity-50 font-medium">
        {{ importando ? "Importando..." : "Confirmar e Importar" }}
      </button>
    </div>

    <!-- Resultado de importacion -->
    <div v-if="resultado" class="bg-white rounded-xl shadow p-6 space-y-4">
      <h2 class="text-lg font-semibold text-gray-700 border-b pb-2">
        Resultado de la Importacion
      </h2>
      <div class="grid grid-cols-3 gap-4">
        <div class="bg-green-50 rounded-lg p-4 text-center">
          <p class="text-2xl font-bold text-green-700">{{ resultado.importados }}</p>
          <p class="text-sm text-green-600">Importados</p>
        </div>
        <div class="bg-yellow-50 rounded-lg p-4 text-center">
          <p class="text-2xl font-bold text-yellow-700">{{ resultado.actualizados }}</p>
          <p class="text-sm text-yellow-600">Actualizados</p>
        </div>
        <div class="bg-red-50 rounded-lg p-4 text-center">
          <p class="text-2xl font-bold text-red-700">{{ resultado.errores.length }}</p>
          <p class="text-sm text-red-600">Con errores</p>
        </div>
      </div>

      <div v-if="resultado.errores.length"
        class="bg-red-50 border border-red-200 rounded-lg p-4">
        <p class="text-red-700 font-medium text-sm mb-2">Errores durante la importacion:</p>
        <ul class="text-red-600 text-xs space-y-1">
          <li v-for="(e, i) in resultado.errores" :key="i">• {{ e }}</li>
        </ul>
      </div>

      <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">
        <p class="text-green-700 font-medium">{{ resultado.message }}</p>
      </div>

      <button @click="limpiar"
        class="w-full border border-gray-300 text-gray-600 py-2 rounded-lg hover:bg-gray-50 text-sm">
        Realizar otra importacion
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from "vue"
import api from "@/services/api"

const inputArchivo   = ref(null)
const archivo        = ref(null)
const preview        = ref(null)
const resultado      = ref(null)
const cargandoPreview = ref(false)
const importando     = ref(false)

const nuevos     = computed(() => preview.value?.filas.filter(f => !f._existe).length || 0)
const existentes = computed(() => preview.value?.filas.filter(f => f._existe).length || 0)

const seleccionarArchivo = (e) => {
  archivo.value  = e.target.files[0]
  preview.value  = null
  resultado.value = null
}

const limpiar = () => {
  archivo.value   = null
  preview.value   = null
  resultado.value = null
  if (inputArchivo.value) inputArchivo.value.value = ""
}

const descargarPlantilla = async () => {
  try {
    const response = await api.get("/importacion/plantilla", { responseType: "blob" })
    const url  = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement("a")
    link.href  = url
    link.setAttribute("download", "plantilla_empleados.csv")
    document.body.appendChild(link)
    link.click()
    link.remove()
  } catch (e) {
    alert("Error al descargar la plantilla")
  }
}

const verPreview = async () => {
  if (!archivo.value) return
  cargandoPreview.value = true
  try {
    const formData = new FormData()
    formData.append("archivo", archivo.value)
    const { data } = await api.post("/importacion/preview", formData, {
      headers: { "Content-Type": "multipart/form-data" }
    })
    preview.value = data
  } catch (e) {
    alert(e.response?.data?.message || "Error al analizar el archivo")
  } finally {
    cargandoPreview.value = false
  }
}

const importar = async () => {
  if (!archivo.value) return
  if (!confirm("Confirmar importacion de " + preview.value.total + " empleados?")) return
  importando.value = true
  try {
    const formData = new FormData()
    formData.append("archivo", archivo.value)
    const { data } = await api.post("/importacion/importar", formData, {
      headers: { "Content-Type": "multipart/form-data" }
    })
    resultado.value = data
    preview.value   = null
  } catch (e) {
    alert(e.response?.data?.message || "Error durante la importacion")
  } finally {
    importando.value = false
  }
}
</script>