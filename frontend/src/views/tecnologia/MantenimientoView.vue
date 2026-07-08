<template>
  <div>
    <div class="flex flex-wrap justify-between items-center gap-3 mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Mantenimiento</h1>
      <div>
        <label class="text-xs text-gray-500 mr-2">Año</label>
        <select v-model.number="anio" @change="cargar" class="border rounded-lg px-3 py-2 text-sm">
          <option v-for="a in anios" :key="a" :value="a">{{ a }}</option>
        </select>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-2 mb-4">
      <button @click="tab = 'pendientes'"
        class="px-4 py-2 text-sm font-medium rounded-lg"
        :style="tab === 'pendientes' ? 'background-color:#4d7c8a; color:#fff;' : 'background-color:#fff; color:#4d7c8a; border:1px solid #4d7c8a;'">
        Pendientes {{ anio }} ({{ pendientes.length }})
      </button>
      <button @click="tab = 'realizados'"
        class="px-4 py-2 text-sm font-medium rounded-lg"
        :style="tab === 'realizados' ? 'background-color:#4d7c8a; color:#fff;' : 'background-color:#fff; color:#4d7c8a; border:1px solid #4d7c8a;'">
        Realizados {{ anio }} ({{ realizados.length }})
      </button>
    </div>

    <!-- Pendientes -->
    <div v-if="tab === 'pendientes'" class="space-y-2">
      <div v-if="!pendientes.length" class="bg-white rounded-xl shadow p-8 text-center text-gray-400">
        Todos los equipos tienen mantenimiento registrado para {{ anio }}
      </div>
      <div v-for="e in pendientes" :key="e.id"
           class="bg-white rounded-xl shadow px-5 py-3 flex justify-between items-center gap-3">
        <div class="min-w-0">
          <p class="font-semibold text-gray-800">
            {{ e.codigo_bien }}
            <span class="text-gray-500 font-normal ml-2">{{ e.marca }} {{ e.modelo }}</span>
          </p>
          <p class="text-xs text-gray-500 mt-0.5">
            {{ e.tipo_equipo?.nombre }}
            <span v-if="e.asignacion_activa"> · Custodio: {{ e.asignacion_activa.empleado?.apellido_emp }} {{ e.asignacion_activa.empleado?.nombre_emp }}</span>
          </p>
        </div>
        <button @click="abrirRegistro(e)" class="text-xs text-white font-medium px-3 py-1.5 rounded-lg flex-shrink-0"
          style="background-color:#4d7c8a;">
          Registrar mantenimiento
        </button>
      </div>
    </div>

    <!-- Realizados -->
    <div v-if="tab === 'realizados'" class="space-y-2">
      <div v-if="!realizados.length" class="bg-white rounded-xl shadow p-8 text-center text-gray-400">
        Sin mantenimientos registrados en {{ anio }}
      </div>
      <div v-for="m in realizados" :key="m.id"
           class="bg-white rounded-xl shadow px-5 py-3 flex flex-wrap justify-between items-center gap-3">
        <div class="min-w-0">
          <p class="font-semibold text-gray-800">
            {{ m.equipo?.codigo_bien }}
            <span class="text-gray-500 font-normal ml-2">{{ m.equipo?.marca }} {{ m.equipo?.modelo }}</span>
          </p>
          <p class="text-xs text-gray-500 mt-0.5">
            {{ m.fecha_mantenimiento }} · {{ m.hora_inicio?.substring(0,5) }} - {{ m.hora_fin?.substring(0,5) }}
            · Técnico: {{ m.tecnico?.apellido_emp }} {{ m.tecnico?.nombre_emp }}
          </p>
        </div>
        <div class="flex items-center gap-2 flex-shrink-0">
          <button @click="descargarPdf(m)" class="text-xs text-white font-medium px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700">
            PDF Acta
          </button>
          <button v-if="!m.acta_alfresco_id" @click="abrirSubirFirmado(m)"
            class="text-xs text-white font-medium px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700">
            Subir firmado
          </button>
          <span v-else class="text-xs text-green-700 bg-green-100 px-2 py-1 rounded-full font-medium">Firmado</span>
        </div>
      </div>
    </div>

    <!-- Modal registrar mantenimiento -->
    <div v-if="modal.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl overflow-hidden">
        <div class="px-6 py-4" style="background-color:#4d7c8a;">
          <h2 class="text-lg font-bold text-white">Registrar Mantenimiento</h2>
          <p class="text-white/80 text-xs mt-0.5">{{ modal.equipo?.codigo_bien }} — {{ modal.equipo?.marca }} {{ modal.equipo?.modelo }}</p>
        </div>
        <div class="p-6 max-h-[75vh] overflow-y-auto">
          <p class="text-xs text-gray-500 mb-3">
            Registrado por: <span class="font-semibold text-gray-700">{{ auth.empleado?.apellido }} {{ auth.empleado?.nombre }}</span>
          </p>

          <div class="grid grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Fecha *</label>
              <input v-model="form.fecha_mantenimiento" type="date" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Hora inicio *</label>
              <TimePicker24 v-model="form.hora_inicio" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Hora fin *</label>
              <TimePicker24 v-model="form.hora_fin" />
            </div>
          </div>

          <div class="mt-3">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Tipo</label>
            <select v-model="form.tipo" class="w-full border rounded-lg px-3 py-2 text-sm">
              <option value="PREVENTIVO">Preventivo</option>
              <option value="CORRECTIVO">Correctivo</option>
            </select>
          </div>

          <div class="mt-4">
            <label class="block text-xs font-semibold text-gray-600 mb-2">Checklist de Actividades *</label>
            <table class="w-full text-sm border rounded-lg overflow-hidden">
              <thead>
                <tr class="bg-gray-100 text-gray-600 text-xs">
                  <th class="text-left px-3 py-2">N°</th>
                  <th class="text-left px-3 py-2">Acciones Realizadas</th>
                  <th class="text-center px-3 py-2 w-16">SI</th>
                  <th class="text-center px-3 py-2 w-16">NO</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, i) in checklistForm" :key="item.actividad_id" class="border-t">
                  <td class="px-3 py-2 text-gray-500">{{ i + 1 }}</td>
                  <td class="px-3 py-2">{{ item.nombre }}</td>
                  <td class="text-center px-3 py-2">
                    <input type="radio" :name="'act_' + item.actividad_id" :checked="item.realizado" @change="item.realizado = true" />
                  </td>
                  <td class="text-center px-3 py-2">
                    <input type="radio" :name="'act_' + item.actividad_id" :checked="!item.realizado" @change="item.realizado = false" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="mt-4">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Observaciones</label>
            <textarea v-model="form.observaciones" rows="2" class="w-full border rounded-lg px-3 py-2 text-sm"></textarea>
          </div>

          <p v-if="error" class="text-red-600 text-sm mt-3">{{ error }}</p>
          <div class="flex justify-end gap-2 mt-5">
            <button @click="modal.show = false" class="px-4 py-2 text-sm text-gray-600 border rounded-lg">Cancelar</button>
            <button @click="guardar" :disabled="guardando"
              class="px-5 py-2 text-sm text-white rounded-lg hover:opacity-90 disabled:opacity-50"
              style="background-color:#4d7c8a;">
              {{ guardando ? 'Guardando...' : 'Guardar y generar acta' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal subir firmado -->
    <div v-if="modalFirmado.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4" style="background-color:#4d7c8a;">
          <h2 class="text-lg font-bold text-white">Subir Acta Firmada</h2>
        </div>
        <div class="p-6">
          <input type="file" accept="application/pdf" @change="e => modalFirmado.archivo = e.target.files[0]"
            class="w-full border rounded-lg px-3 py-2 text-sm" />
          <p v-if="error" class="text-red-600 text-sm mt-3">{{ error }}</p>
          <div class="flex justify-end gap-2 mt-5">
            <button @click="modalFirmado.show = false" class="px-4 py-2 text-sm text-gray-600 border rounded-lg">Cancelar</button>
            <button @click="confirmarSubirFirmado" :disabled="!modalFirmado.archivo || guardando"
              class="px-5 py-2 text-sm text-white rounded-lg hover:opacity-90 disabled:opacity-50"
              style="background-color:#4d7c8a;">
              {{ guardando ? 'Subiendo...' : 'Subir' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import TimePicker24 from '@/components/TimePicker24.vue'

const auth = useAuthStore()

const anioActual = new Date().getFullYear()
const anio  = ref(anioActual)
const anios = Array.from({ length: 5 }, (_, i) => anioActual - i)
const tab   = ref('pendientes')

const pendientes = ref([])
const realizados = ref([])
const checklistCatalogo = ref([])

const guardando = ref(false)
const error     = ref('')
const modal      = ref({ show: false, equipo: null })
const form        = ref({})
const checklistForm = ref([])

async function cargar() {
  const [{ data: p }, { data: r }] = await Promise.all([
    api.get('/tecnologia/mantenimiento/pendientes', { params: { anio: anio.value } }),
    api.get('/tecnologia/mantenimiento/realizados', { params: { anio: anio.value } }),
  ])
  pendientes.value = p
  realizados.value = r
}

async function cargarChecklist() {
  const { data } = await api.get('/tecnologia/mantenimiento/checklist')
  checklistCatalogo.value = data
}

function abrirRegistro(equipo) {
  modal.value = { show: true, equipo }
  form.value = {
    fecha_mantenimiento: new Date().toISOString().substring(0, 10),
    hora_inicio: '', hora_fin: '', tipo: 'PREVENTIVO', observaciones: '',
  }
  checklistForm.value = checklistCatalogo.value.map(a => ({ actividad_id: a.id, nombre: a.nombre, realizado: false }))
  error.value = ''
}

async function guardar() {
  error.value = ''
  guardando.value = true
  try {
    const { data } = await api.post('/tecnologia/mantenimiento', {
      equipo_id: modal.value.equipo.id,
      fecha_mantenimiento: form.value.fecha_mantenimiento,
      hora_inicio: form.value.hora_inicio,
      hora_fin: form.value.hora_fin,
      tipo: form.value.tipo,
      observaciones: form.value.observaciones,
      checklist: checklistForm.value.map(c => ({ actividad_id: c.actividad_id, realizado: c.realizado })),
    })
    modal.value.show = false
    await cargar()
    await descargarPdf(data)
  } catch (e) {
    error.value = e.response?.data?.message || Object.values(e.response?.data?.errors || {})[0]?.[0] || 'Error al guardar'
  } finally {
    guardando.value = false
  }
}

async function descargarPdf(m) {
  const resp = await api.get(`/tecnologia/mantenimiento/${m.id}/pdf`, { responseType: 'blob' })
  const blob = new Blob([resp.data], { type: 'application/pdf' })
  const url  = URL.createObjectURL(blob)
  window.open(url, '_blank')
  setTimeout(() => URL.revokeObjectURL(url), 60000)
}

// ─── Subir firmado ────────────────────────────────────────────────────────
const modalFirmado = ref({ show: false, mantenimiento: null, archivo: null })

function abrirSubirFirmado(m) {
  modalFirmado.value = { show: true, mantenimiento: m, archivo: null }
  error.value = ''
}

async function confirmarSubirFirmado() {
  error.value = ''
  guardando.value = true
  try {
    const fd = new FormData()
    fd.append('archivo', modalFirmado.value.archivo)
    await api.post(`/tecnologia/mantenimiento/${modalFirmado.value.mantenimiento.id}/subir-firmado`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    modalFirmado.value.show = false
    await cargar()
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al subir el archivo'
  } finally {
    guardando.value = false
  }
}

onMounted(async () => {
  await cargarChecklist()
  await cargar()
})
</script>
