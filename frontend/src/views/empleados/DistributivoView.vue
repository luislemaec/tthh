<template>
  <div class="space-y-6 max-w-3xl mx-auto">
    <div class="flex items-center gap-3">
      <router-link to="/empleados" class="text-gray-400 hover:text-gray-600">← Volver</router-link>
      <h1 class="text-2xl font-bold text-gray-800">Importar Distributivo</h1>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-sm text-blue-700">
      Actualiza los campos del puesto (grado, grupo ocupacional, proceso institucional, partida presupuestaria
      y fondos de reserva) para los empleados activos desde el archivo CSV del distributivo institucional.
      El archivo debe tener el mismo formato que el distributivo oficial.
    </div>

    <!-- Subir archivo -->
    <div class="bg-white rounded-xl shadow p-6 space-y-4">
      <h2 class="text-lg font-semibold text-gray-700 border-b pb-2">Seleccionar archivo CSV</h2>

      <div class="border-2 border-dashed border-gray-300 rounded-xl p-8 text-center hover:border-[#579186] transition cursor-pointer"
        @click="inputArchivo.click()">
        <input type="file" ref="inputArchivo" accept=".csv,.txt" @change="seleccionarArchivo" class="hidden" />
        <div v-if="!archivo">
          <p class="text-4xl mb-3">📂</p>
          <p class="text-gray-500 text-sm">Clic para seleccionar el archivo CSV del distributivo</p>
        </div>
        <div v-else>
          <p class="text-4xl mb-3">✅</p>
          <p class="font-medium text-gray-800">{{ archivo.name }}</p>
          <p class="text-gray-400 text-sm">{{ (archivo.size / 1024).toFixed(1) }} KB</p>
          <button @click.stop="limpiar" class="text-red-500 hover:underline text-sm mt-2">Quitar</button>
        </div>
      </div>

      <button @click="importar" :disabled="!archivo || importando"
        class="w-full bg-[#0b5447] text-white py-3 rounded-lg hover:bg-[#00372e] disabled:opacity-50 font-medium">
        {{ importando ? "Importando..." : "Importar Distributivo" }}
      </button>
    </div>

    <!-- Resultado -->
    <div v-if="resultado" class="bg-white rounded-xl shadow p-6 space-y-4">
      <h2 class="text-lg font-semibold text-gray-700 border-b pb-2">Resultado</h2>

      <div class="grid grid-cols-3 gap-4">
        <div class="bg-green-50 rounded-lg p-4 text-center">
          <p class="text-3xl font-bold text-green-700">{{ resultado.actualizados }}</p>
          <p class="text-sm text-green-600">Actualizados</p>
        </div>
        <div class="bg-yellow-50 rounded-lg p-4 text-center">
          <p class="text-3xl font-bold text-yellow-700">{{ resultado.no_encontrados?.length || 0 }}</p>
          <p class="text-sm text-yellow-600">No encontrados</p>
        </div>
        <div class="bg-red-50 rounded-lg p-4 text-center">
          <p class="text-3xl font-bold text-red-700">{{ resultado.errores?.length || 0 }}</p>
          <p class="text-sm text-red-600">Con errores</p>
        </div>
      </div>

      <div v-if="resultado.no_encontrados?.length"
        class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
        <p class="text-yellow-700 font-medium text-sm mb-2">
          Cédulas no encontradas en el sistema ({{ resultado.no_encontrados.length }}):
        </p>
        <p class="text-yellow-600 text-xs font-mono">{{ resultado.no_encontrados.join(', ') }}</p>
      </div>

      <div v-if="resultado.errores?.length"
        class="bg-red-50 border border-red-200 rounded-lg p-4">
        <p class="text-red-700 font-medium text-sm mb-2">Errores:</p>
        <ul class="text-red-600 text-xs space-y-1">
          <li v-for="(e, i) in resultado.errores" :key="i">• {{ e }}</li>
        </ul>
      </div>

      <div class="bg-green-50 border border-green-200 rounded-lg p-3 text-center text-green-700 text-sm font-medium">
        {{ resultado.message }}
      </div>

      <button @click="limpiar"
        class="w-full border border-gray-300 text-gray-600 py-2 rounded-lg hover:bg-gray-50 text-sm">
        Importar otro archivo
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref } from "vue"
import api from "@/services/api"

const inputArchivo = ref(null)
const archivo      = ref(null)
const resultado    = ref(null)
const importando   = ref(false)

const seleccionarArchivo = (e) => {
  archivo.value  = e.target.files[0]
  resultado.value = null
}

const limpiar = () => {
  archivo.value   = null
  resultado.value = null
  if (inputArchivo.value) inputArchivo.value.value = ""
}

const importar = async () => {
  if (!archivo.value) return
  if (!confirm(`¿Importar el distributivo desde "${archivo.value.name}"? Se actualizarán los datos del puesto de los empleados encontrados.`)) return

  importando.value = true
  try {
    const formData = new FormData()
    formData.append("archivo", archivo.value)
    const { data } = await api.post("/empleados/importar-distributivo", formData, {
      headers: { "Content-Type": "multipart/form-data" }
    })
    resultado.value = data
    archivo.value   = null
    if (inputArchivo.value) inputArchivo.value.value = ""
  } catch (e) {
    alert(e.response?.data?.message || "Error al importar el archivo")
  } finally {
    importando.value = false
  }
}
</script>
