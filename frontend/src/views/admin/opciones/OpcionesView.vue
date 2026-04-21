<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Opciones de Menu</h1>
      <button @click="abrirModalNuevo" class="bg-[#00372e] text-white px-4 py-2 rounded-lg hover:bg-blue-800 transition text-sm">
        + Nueva Opcion
      </button>
    </div>
    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">ID</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Descripcion</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">URL</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Categoria</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Orden</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Estado</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando">
            <td colspan="7" class="px-6 py-8 text-center text-gray-400">Cargando...</td>
          </tr>
          <tr v-else-if="!opciones.length">
            <td colspan="7" class="px-6 py-8 text-center text-gray-400">No hay opciones registradas</td>
          </tr>
          <tr v-for="opcion in opciones" :key="opcion.id" class="border-b hover:bg-gray-50">
            <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ opcion.id }}</td>
            <td class="px-4 py-3 font-medium">{{ opcion.descripcion }}</td>
            <td class="px-4 py-3 text-gray-500">{{ opcion.url }}</td>
            <td class="px-4 py-3">
              <span class="bg-blue-100 text-[#0b5447] px-2 py-1 rounded-full text-xs font-medium">{{ opcion.categoria }}</span>
            </td>
            <td class="px-4 py-3 text-gray-500">{{ opcion.orden_categoria }}-{{ opcion.secuencia }}</td>
            <td class="px-4 py-3">
              <span class="px-2 py-1 rounded-full text-xs font-medium" :class="opcion.estado ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                {{ opcion.estado ? "Activo" : "Inactivo" }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex gap-2">
                <button @click="editarOpcion(opcion)" class="text-[#0b5447] hover:text-blue-800 text-xs font-medium">Editar</button>
                <button @click="toggleEstado(opcion)" class="text-yellow-600 hover:text-yellow-800 text-xs font-medium">
                  {{ opcion.estado ? "Desactivar" : "Activar" }}
                </button>
                <button @click="eliminarOpcion(opcion.id)" class="text-red-500 hover:text-red-700 text-xs font-medium">Eliminar</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-if="modal.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
        <h2 class="text-lg font-bold mb-4">{{ modal.titulo }}</h2>
        <form @submit.prevent="guardarOpcion" class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">ID *</label>
              <input v-model="modal.form.id" type="text" maxlength="10" placeholder="Ej: OPC007"
                class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#579186] outline-none"
                :disabled="modal.editando" required />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Descripcion *</label>
              <input v-model="modal.form.descripcion" type="text" maxlength="50"
                class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#579186] outline-none" required />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">URL *</label>
            <input v-model="modal.form.url" type="text" maxlength="50" placeholder="Ej: admin/jornadas"
              class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#579186] outline-none" required />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Categoria *</label>
              <input v-model="modal.form.categoria" type="text" maxlength="20" list="cat-list"
                class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#579186] outline-none" required />
              <datalist id="cat-list">
                <option v-for="cat in categorias" :key="cat" :value="cat" />
              </datalist>
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Padre</label>
              <select v-model="modal.form.padre" class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#579186] outline-none">
                <option value="">Sin padre</option>
                <option v-for="op in opciones" :key="op.id" :value="op.id">{{ op.descripcion }}</option>
              </select>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Orden Categoria *</label>
              <input v-model="modal.form.orden_categoria" type="number" min="1"
                class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#579186] outline-none" required />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Secuencia *</label>
              <input v-model="modal.form.secuencia" type="number" min="1"
                class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#579186] outline-none" required />
            </div>
          </div>
          <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">{{ error }}</div>
          <div class="flex justify-end gap-3 pt-2">
            <button type="button" @click="cerrarModal" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
            <button type="submit" :disabled="guardando" class="bg-[#00372e] text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-800 disabled:opacity-50">
              {{ guardando ? "Guardando..." : "Guardar" }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
<script setup>
import { ref, onMounted } from "vue"
import api from "@/services/api"
const opciones = ref([])
const categorias = ref([])
const cargando = ref(false)
const guardando = ref(false)
const error = ref("")
const modal = ref({ show: false, titulo: "", editando: false, form: { id: "", descripcion: "", url: "", categoria: "", orden_categoria: "", secuencia: "", padre: "" } })
async function cargar() {
  cargando.value = true
  try {
    const [op, cat] = await Promise.all([api.get("/admin/opciones"), api.get("/admin/opciones-categorias")])
    opciones.value = op.data
    categorias.value = cat.data
  } catch (e) { error.value = "Error al cargar" } finally { cargando.value = false }
}
function abrirModalNuevo() {
  error.value = ""
  modal.value = { show: true, titulo: "Nueva Opcion", editando: false, form: { id: "", descripcion: "", url: "", categoria: "", orden_categoria: "", secuencia: "", padre: "" } }
}
function editarOpcion(opcion) {
  error.value = ""
  modal.value = { show: true, titulo: "Editar Opcion", editando: true, form: { ...opcion, padre: opcion.padre || "" } }
}
function cerrarModal() { modal.value.show = false; error.value = "" }
async function guardarOpcion() {
  guardando.value = true; error.value = ""
  try {
    if (modal.value.editando) { await api.put("/admin/opciones/" + modal.value.form.id, modal.value.form) }
    else { await api.post("/admin/opciones", modal.value.form) }
    cerrarModal(); cargar()
  } catch (e) { error.value = e.response?.data?.message || "Error al guardar" } finally { guardando.value = false }
}
async function toggleEstado(opcion) {
  try { await api.patch("/admin/opciones/" + opcion.id + "/toggle"); cargar() } catch (e) { alert("Error") }
}
async function eliminarOpcion(id) {
  if (!confirm("Seguro de eliminar?")) return
  try { await api.delete("/admin/opciones/" + id); cargar() } catch (e) { alert("Error") }
}
onMounted(cargar)
</script>