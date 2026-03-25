<template>
  <div class="space-y-6">
    <!-- Encabezado -->
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-800">Configuracion del Sistema</h1>
      <div class="flex gap-2">
        <button @click="cargarParametrosBase"
          class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 text-sm font-medium">
          Cargar Parametros Base
        </button>
        <button @click="abrirModal()"
          class="bg-[#0b5447] text-white px-4 py-2 rounded-lg hover:bg-[#00372e] text-sm font-medium">
          + Nuevo Parametro
        </button>
      </div>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Concepto</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Valor</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando">
            <td colspan="3" class="text-center py-8 text-gray-400">Cargando...</td>
          </tr>
          <tr v-else-if="configuraciones.length === 0">
            <td colspan="3" class="text-center py-8 text-gray-400">
              No hay parametros registrados.
              <button @click="cargarParametrosBase" class="text-[#0b5447] hover:underline ml-2">
                Cargar parametros base
              </button>
            </td>
          </tr>
          <tr v-for="c in configuraciones" :key="c.concepto" class="border-b hover:bg-gray-50">
            <td class="px-6 py-3 font-medium text-gray-800">{{ c.concepto }}</td>
            <td class="px-6 py-3">
              <span class="bg-blue-50 text-[#0b5447] px-3 py-1 rounded-lg text-xs font-medium">
                {{ c.valor }}
              </span>
            </td>
            <td class="px-6 py-3 flex gap-3">
              <button @click="abrirModal(c)"
                class="text-[#0b5447] hover:underline text-xs font-medium">
                Editar
              </button>
              <button @click="eliminar(c.concepto)"
                class="text-red-600 hover:underline text-xs font-medium">
                Eliminar
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="modal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-md space-y-4">
        <h2 class="text-lg font-semibold text-gray-700">
          {{ form.editando ? "Editar Parametro" : "Nuevo Parametro" }}
        </h2>
        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Concepto *</label>
          <input v-model="form.concepto" type="text" maxlength="120"
            placeholder="Ej: tolerancia_entrada"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]"
            :disabled="form.editando" required />
          <p class="text-xs text-gray-400 mt-1">Nombre unico del parametro</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Valor *</label>
          <input v-model="form.valor" type="text" maxlength="150"
            placeholder="Ej: 5"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]"
            required />
        </div>
        <div v-if="error" class="text-red-600 text-sm bg-red-50 rounded p-2">{{ error }}</div>
        <div class="flex justify-end gap-3 pt-2">
          <button @click="modal = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">
            Cancelar
          </button>
          <button @click="guardar" :disabled="guardando"
            class="px-4 py-2 rounded-lg bg-[#0b5447] text-white text-sm hover:bg-[#00372e] disabled:opacity-50">
            {{ guardando ? "Guardando..." : "Guardar" }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue"
import api from "@/services/api"

const configuraciones = ref([])
const cargando        = ref(false)
const modal           = ref(false)
const guardando       = ref(false)
const error           = ref("")
const form            = ref({ editando: false, concepto: "", valor: "" })

async function cargar() {
  cargando.value = true
  try {
    const { data } = await api.get("/admin/configuracion")
    configuraciones.value = data
  } catch (e) {
    console.error(e)
  } finally {
    cargando.value = false
  }
}

function abrirModal(c = null) {
  error.value = ""
  form.value = c
    ? { editando: true, concepto: c.concepto, valor: c.valor }
    : { editando: false, concepto: "", valor: "" }
  modal.value = true
}

async function guardar() {
  if (!form.value.concepto || !form.value.valor) {
    error.value = "Todos los campos son requeridos."
    return
  }
  guardando.value = true
  error.value = ""
  try {
    if (form.value.editando) {
      await api.put("/admin/configuracion/" + form.value.concepto, { valor: form.value.valor })
    } else {
      await api.post("/admin/configuracion", form.value)
    }
    modal.value = false
    cargar()
  } catch (e) {
    error.value = e.response?.data?.message || "Error al guardar."
  } finally {
    guardando.value = false
  }
}

async function eliminar(concepto) {
  if (!confirm("Seguro que deseas eliminar el parametro: " + concepto + "?")) return
  try {
    await api.delete("/admin/configuracion/" + concepto)
    cargar()
  } catch (e) {
    alert(e.response?.data?.message || "Error al eliminar.")
  }
}

async function cargarParametrosBase() {
  if (!confirm("Cargar parametros base del sistema?")) return
  try {
    const { data } = await api.post("/admin/configuracion/parametros-base")
    alert(data.message)
    cargar()
  } catch (e) {
    alert("Error al cargar parametros base.")
  }
}

onMounted(cargar)
</script>