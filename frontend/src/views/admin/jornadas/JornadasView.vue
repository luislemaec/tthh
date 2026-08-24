<template>
  <div>
    <!-- Encabezado -->
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Jornadas Laborales</h1>
      <button @click="abrirModalNuevo"
        class="bg-[#00372e] text-white px-4 py-2 rounded-lg hover:bg-blue-800 transition text-sm">
        + Nueva Jornada
      </button>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">ID</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Descripción</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Horas Máximas</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Horas Normales</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Recargo %</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">H. Extra. %</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">H. Supl. %</th>
            <th class="text-left px-6 py-3 text-gray-600 font-medium">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando">
            <td colspan="6" class="px-6 py-8 text-center text-gray-400">
              Cargando...
            </td>
          </tr>
          <tr v-else-if="!jornadas.length">
            <td colspan="6" class="px-6 py-8 text-center text-gray-400">
              No hay jornadas registradas
            </td>
          </tr>
          <tr v-for="jornada in jornadas" :key="jornada.id_jornada"
            class="border-b hover:bg-gray-50">
            <td class="px-6 py-3 text-gray-500">{{ jornada.id_jornada }}</td>
            <td class="px-6 py-3 font-medium">{{ jornada.descripcion }}</td>
            <td class="px-6 py-3">{{ jornada.jornada_ordinaria_maxima ?? "-" }}</td>
            <td class="px-6 py-3">{{ jornada.normal ?? "-" }}</td>
            <td class="px-6 py-3">{{ jornada.recargo ?? "-" }}</td>
            <td class="px-6 py-3">{{ jornada.porc_extraordinaria ?? "-" }}</td>
            <td class="px-6 py-3">{{ jornada.porc_suplementaria ?? "-" }}</td>
            <td class="px-6 py-3 flex gap-3">
              <button @click="editarJornada(jornada)"
                class="text-[#0b5447] hover:text-blue-800 text-xs font-medium">
                Editar
              </button>
              <button @click="eliminarJornada(jornada.id_jornada)"
                class="text-red-500 hover:text-red-700 text-xs font-medium">
                Eliminar
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="modal.show"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4" style="background-color:#0b5447;">
          <h2 class="text-lg font-bold text-white">{{ modal.titulo }}</h2>
          <button type="button" @click="cerrarModal" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-6">
        <form @submit.prevent="guardarJornada" class="space-y-4">

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              ID Jornada
            </label>
            <input v-model="modal.form.id_jornada" type="number"
              class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#579186] outline-none"
              :disabled="modal.editando" required />
            <p class="text-xs text-gray-400 mt-1">
              Número único que identifica la jornada
            </p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Descripción
            </label>
            <input v-model="modal.form.descripcion" type="text" maxlength="50"
              placeholder="Ej: Jornada Completa"
              class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#579186] outline-none"
              required />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Horas Máximas
              </label>
              <input v-model="modal.form.jornada_ordinaria_maxima" type="number"
                placeholder="Ej: 8"
                class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#579186] outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Horas Normales
              </label>
              <input v-model="modal.form.normal" type="number"
                placeholder="Ej: 8"
                class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#579186] outline-none" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Recargo %
              </label>
              <input v-model="modal.form.recargo" type="number" step="0.01"
                placeholder="Ej: 25.00"
                class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#579186] outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                Porcentaje 25%
              </label>
              <input v-model="modal.form.porc_25" type="number"
                placeholder="Ej: 25"
                class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#579186] outline-none" />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                H. Extraordinarias %
              </label>
              <input v-model="modal.form.porc_extraordinaria" type="number" step="0.01"
                placeholder="Ej: 50 (CdT) / 25 (LOSEP)"
                class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#579186] outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">
                H. Suplementarias %
              </label>
              <input v-model="modal.form.porc_suplementaria" type="number" step="0.01"
                placeholder="Ej: 100 (CdT) / 60 (LOSEP)"
                class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#579186] outline-none" />
            </div>
          </div>

          <!-- Mensaje de error -->
          <div v-if="error"
            class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
            {{ error }}
          </div>

          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="cerrarModal"
              class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">
              Cancelar
            </button>
            <button type="submit" :disabled="guardando"
              class="bg-[#00372e] text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-800 disabled:opacity-50">
              {{ guardando ? "Guardando..." : "Guardar" }}
            </button>
          </div>
        </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue"
import api from "@/services/api"

const jornadas = ref([])
const cargando = ref(false)
const guardando = ref(false)
const error = ref("")

const modal = ref({
  show: false,
  titulo: "",
  editando: false,
  form: {
    id_jornada: "",
    descripcion: "",
    jornada_ordinaria_maxima: "",
    normal: "",
    recargo: "",
    porc_25: "",
  }
})

// Cargar jornadas al entrar
async function cargar() {
  cargando.value = true
  try {
    const { data } = await api.get("/admin/jornadas")
    jornadas.value = data
  } catch (e) {
    error.value = "Error al cargar jornadas"
  } finally {
    cargando.value = false
  }
}

// Abrir modal para nueva jornada
function abrirModalNuevo() {
  error.value = ""
  modal.value = {
    show: true,
    titulo: "Nueva Jornada",
    editando: false,
    form: {
      id_jornada: "",
      descripcion: "",
      jornada_ordinaria_maxima: "",
      normal: "",
      recargo: "",
      porc_25: "",
      porc_extraordinaria: "",
      porc_suplementaria: "",
    }
  }
}

// Abrir modal para editar
function editarJornada(jornada) {
  error.value = ""
  modal.value = {
    show: true,
    titulo: "Editar Jornada",
    editando: true,
    form: { ...jornada }
  }
}

// Cerrar modal
function cerrarModal() {
  modal.value.show = false
  error.value = ""
}

// Guardar (crear o editar)
async function guardarJornada() {
  guardando.value = true
  error.value = ""
  try {
    if (modal.value.editando) {
      await api.put("/admin/jornadas/" + modal.value.form.id_jornada, modal.value.form)
    } else {
      await api.post("/admin/jornadas", modal.value.form)
    }
    cerrarModal()
    cargar()
  } catch (e) {
    error.value = e.response?.data?.message || "Error al guardar la jornada"
  } finally {
    guardando.value = false
  }
}

// Eliminar jornada
async function eliminarJornada(id) {
  if (!confirm("¿Está seguro de eliminar esta jornada?")) return
  try {
    await api.delete("/admin/jornadas/" + id)
    cargar()
  } catch (e) {
    alert("Error al eliminar la jornada")
  }
}

onMounted(cargar)
</script>
