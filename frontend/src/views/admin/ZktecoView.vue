<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Dispositivos ZKTeco</h1>
      <button @click="cargar" class="border rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">
        Actualizar
      </button>
    </div>

    <div class="bg-blue-50 border border-blue-200 rounded-xl px-5 py-3 mb-5 text-sm text-blue-800">
      Los dispositivos se registran automáticamente al conectarse. Activa el dispositivo para que pueda enviar marcaciones.
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead>
          <tr style="background-color:#0b5447;">
            <th class="text-left px-4 py-3 text-white font-medium text-xs uppercase tracking-wide">Nombre</th>
            <th class="text-left px-4 py-3 text-white font-medium text-xs uppercase tracking-wide">Serial</th>
            <th class="text-left px-4 py-3 text-white font-medium text-xs uppercase tracking-wide">IP detectada</th>
            <th class="text-left px-4 py-3 text-white font-medium text-xs uppercase tracking-wide">Último push</th>
            <th class="text-center px-4 py-3 text-white font-medium text-xs uppercase tracking-wide">Estado</th>
            <th class="text-left px-4 py-3 text-white font-medium text-xs uppercase tracking-wide">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando">
            <td colspan="6" class="text-center py-10 text-gray-400">Cargando...</td>
          </tr>
          <tr v-else-if="!dispositivos.length">
            <td colspan="6" class="text-center py-10 text-gray-400">
              Sin dispositivos registrados. El reloj ZKTeco aparecerá aquí al conectarse.
            </td>
          </tr>
          <tr v-for="d in dispositivos" :key="d.id"
            class="border-b hover:bg-gray-50 transition-colors">
            <td class="px-4 py-3 font-medium text-gray-800">
              {{ d.nombre || '—' }}
            </td>
            <td class="px-4 py-3 font-mono text-gray-600">{{ d.serial }}</td>
            <td class="px-4 py-3 text-gray-600">{{ d.ip || '—' }}</td>
            <td class="px-4 py-3 text-gray-500">{{ formatFecha(d.ultimo_push) }}</td>
            <td class="px-4 py-3 text-center">
              <span :class="d.activo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                class="px-2 py-0.5 rounded-full text-xs font-medium">
                {{ d.activo ? 'Activo' : 'Inactivo' }}
              </span>
            </td>
            <td class="px-4 py-3">
              <div class="flex gap-1">
                <button @click="abrirEditar(d)"
                  class="inline-flex items-center px-2.5 py-1 rounded-md border border-gray-300 text-xs text-gray-600 hover:bg-gray-50 font-medium transition-colors">
                  Editar
                </button>
                <button @click="toggleActivo(d)"
                  :class="d.activo
                    ? 'border-red-200 text-red-600 hover:bg-red-50'
                    : 'border-green-200 text-green-700 hover:bg-green-50'"
                  class="inline-flex items-center px-2.5 py-1 rounded-md border text-xs font-medium transition-colors">
                  {{ d.activo ? 'Desactivar' : 'Activar' }}
                </button>
                <button @click="eliminar(d)"
                  class="inline-flex items-center px-2.5 py-1 rounded-md border border-red-200 text-xs text-red-600 hover:bg-red-50 font-medium transition-colors">
                  Eliminar
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal editar -->
    <div v-if="modal.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4" style="background-color:#0b5447;">
          <h2 class="text-lg font-bold text-white">Editar dispositivo</h2>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Serial</label>
            <p class="text-sm font-mono text-gray-700">{{ modal.serial }}</p>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Nombre descriptivo</label>
            <input v-model="form.nombre" type="text" placeholder="Ej: Reloj Entrada Principal"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
          </div>
          <div class="flex items-center gap-2">
            <input type="checkbox" v-model="form.activo" id="chkActivo" class="w-4 h-4 rounded" />
            <label for="chkActivo" class="text-sm font-medium text-gray-700 cursor-pointer">
              Dispositivo activo (puede enviar marcaciones)
            </label>
          </div>
          <p v-if="error" class="text-red-600 text-sm">{{ error }}</p>
          <div class="flex justify-end gap-2 pt-2">
            <button @click="modal.show = false"
              class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">
              Cancelar
            </button>
            <button @click="guardar" :disabled="guardando"
              class="px-4 py-2 rounded-lg text-sm text-white font-medium disabled:opacity-50"
              style="background-color:#0b5447;">
              {{ guardando ? 'Guardando...' : 'Guardar' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue"
import api from "@/services/api"

const dispositivos = ref([])
const cargando     = ref(false)
const guardando    = ref(false)
const error        = ref("")

const modal = ref({ show: false, id: null, serial: "" })
const form  = ref({ nombre: "", activo: false })

const formatFecha = (f) => {
  if (!f) return "—"
  const d = new Date(f)
  return d.toLocaleDateString("es-EC") + " " + d.toLocaleTimeString("es-EC", { hour: "2-digit", minute: "2-digit" })
}

const cargar = async () => {
  cargando.value = true
  try {
    const { data } = await api.get("/admin/zkteco")
    dispositivos.value = data
  } catch {
    /* silencioso */
  } finally {
    cargando.value = false
  }
}

const abrirEditar = (d) => {
  modal.value = { show: true, id: d.id, serial: d.serial }
  form.value  = { nombre: d.nombre || "", activo: !!d.activo }
  error.value = ""
}

const guardar = async () => {
  guardando.value = true
  error.value     = ""
  try {
    await api.put(`/admin/zkteco/${modal.value.id}`, form.value)
    modal.value.show = false
    await cargar()
  } catch (e) {
    error.value = e.response?.data?.message || "Error al guardar"
  } finally {
    guardando.value = false
  }
}

const toggleActivo = async (d) => {
  const accion = d.activo ? "desactivar" : "activar"
  if (!confirm(`¿${accion.charAt(0).toUpperCase() + accion.slice(1)} el dispositivo "${d.serial}"?`)) return
  try {
    await api.put(`/admin/zkteco/${d.id}`, { nombre: d.nombre, activo: !d.activo })
    await cargar()
  } catch (e) {
    alert(e.response?.data?.message || "Error al actualizar")
  }
}

const eliminar = async (d) => {
  if (!confirm(`¿Eliminar el dispositivo "${d.serial}"? Esta acción no se puede deshacer.`)) return
  try {
    await api.delete(`/admin/zkteco/${d.id}`)
    await cargar()
  } catch (e) {
    alert(e.response?.data?.message || "Error al eliminar")
  }
}

onMounted(cargar)
</script>
