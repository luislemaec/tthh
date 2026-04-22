<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Inventario de Artículos</h1>
      <div class="flex gap-3">
        <button @click="abrirConfiguracion"
          class="border border-amber-600 text-amber-700 px-4 py-2 rounded-lg text-sm hover:bg-amber-50">
          ⚙ Stock mínimo ({{ porcentajeMinimo }}%)
        </button>
        <button @click="abrirModalCrear"
          class="bg-amber-700 text-white px-4 py-2 rounded-lg text-sm hover:bg-amber-800">
          + Nuevo artículo
        </button>
      </div>
    </div>

    <!-- Filtros -->
    <div class="flex gap-3 mb-4 flex-wrap">
      <input v-model="busqueda" type="text" placeholder="Buscar por nombre, código o nivel..."
        class="border rounded-lg px-3 py-2 text-sm w-64 focus:ring-2 focus:ring-amber-300 outline-none" />
      <select v-model="filtroEstado" class="border rounded-lg px-3 py-2 text-sm">
        <option value="">Todos</option>
        <option value="alerta">Solo alertas de stock</option>
        <option value="ACTIVO">Activos</option>
        <option value="INACTIVO">Inactivos</option>
      </select>
    </div>

    <div class="bg-white rounded-xl shadow overflow-hidden overflow-x-auto">
      <table class="w-full text-xs">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="text-left px-3 py-3 text-gray-600 font-medium">#</th>
            <th class="text-left px-3 py-3 text-gray-600 font-medium">Código</th>
            <th class="text-left px-3 py-3 text-gray-600 font-medium">Niv.1</th>
            <th class="text-left px-3 py-3 text-gray-600 font-medium">Niv.2</th>
            <th class="text-left px-3 py-3 text-gray-600 font-medium">Ítem Presup.</th>
            <th class="text-left px-3 py-3 text-gray-600 font-medium">Descripción</th>
            <th class="text-left px-3 py-3 text-gray-600 font-medium">Unidad</th>
            <th class="text-right px-3 py-3 text-gray-600 font-medium">Stock</th>
            <th class="text-right px-3 py-3 text-gray-600 font-medium">Precio s/IVA</th>
            <th class="text-right px-3 py-3 text-gray-600 font-medium">IVA%</th>
            <th class="text-right px-3 py-3 text-gray-600 font-medium">IVA $</th>
            <th class="text-right px-3 py-3 text-gray-600 font-medium">Total</th>
            <th class="text-left px-3 py-3 text-gray-600 font-medium">Estado</th>
            <th class="text-left px-3 py-3 text-gray-600 font-medium">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!articulosFiltrados.length">
            <td colspan="14" class="text-center py-8 text-gray-400">Sin artículos</td>
          </tr>
          <tr v-for="a in articulosFiltrados" :key="a.id" class="border-b hover:bg-gray-50">
            <td class="px-3 py-2 text-gray-400">{{ a.id }}</td>
            <td class="px-3 py-2 font-mono">{{ a.codigo }}</td>
            <td class="px-3 py-2 text-gray-500">{{ a.nivel1 || '-' }}</td>
            <td class="px-3 py-2 text-gray-500">{{ a.nivel2 || '-' }}</td>
            <td class="px-3 py-2 text-gray-500 max-w-[120px] truncate" :title="a.item_presupuestario">
              {{ a.item_presupuestario || '-' }}
            </td>
            <td class="px-3 py-2 font-medium max-w-[200px]">{{ a.nombre }}</td>
            <td class="px-3 py-2 text-gray-500">{{ a.unidad_medida || '-' }}</td>
            <td class="px-3 py-2 text-right">
              <span :class="a.bajo_minimo ? 'text-red-600 font-bold' : 'text-gray-800'">{{ a.stock_actual }}</span>
              <span v-if="a.bajo_minimo" class="ml-1 bg-red-100 text-red-600 px-1 py-0.5 rounded-full">⚠</span>
            </td>
            <td class="px-3 py-2 text-right font-mono">${{ fmt(a.precio_unitario) }}</td>
            <td class="px-3 py-2 text-right">{{ a.iva_porcentaje || 0 }}%</td>
            <td class="px-3 py-2 text-right font-mono">${{ fmt(a.iva_valor) }}</td>
            <td class="px-3 py-2 text-right font-mono font-semibold">${{ fmt(a.precio_total) }}</td>
            <td class="px-3 py-2">
              <span :class="a.estado === 'ACTIVO' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                class="px-2 py-0.5 rounded-full font-medium">{{ a.estado }}</span>
            </td>
            <td class="px-3 py-2 flex gap-2">
              <button @click="abrirEditar(a)" class="text-blue-600 hover:text-blue-800">Editar</button>
              <button v-if="a.estado === 'ACTIVO'" @click="inactivar(a.id)"
                class="text-red-500 hover:text-red-700">Inactivar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal crear/editar -->
    <div v-if="modal.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl p-6 max-h-[95vh] overflow-y-auto">
        <h2 class="text-lg font-bold mb-4">{{ modal.editando ? 'Editar' : 'Nuevo' }} Artículo</h2>
        <div class="space-y-3">

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-gray-600 mb-1">Código *</label>
              <input v-model="modal.form.codigo" type="text" maxlength="30"
                class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
            </div>
            <div>
              <label class="block text-xs text-gray-600 mb-1">Unidad de medida</label>
              <select v-model="modal.form.unidad_medida"
                class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none">
                <option value="">Seleccionar...</option>
                <option v-for="u in unidades" :key="u.id" :value="u.nombre">
                  {{ u.nombre }} ({{ u.abreviatura }})
                </option>
              </select>
            </div>
          </div>

          <!-- Nivel 1 -->
          <div>
            <label class="block text-xs text-gray-600 mb-1">Nivel 1 (Categoría MF)</label>
            <select v-model="modal.form.nivel1" @change="onNivel1Change"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none">
              <option value="">Sin categoría</option>
              <option v-for="n in nivel1s" :key="n.nivel1" :value="n.nivel1">
                {{ n.nivel1 }} — {{ n.descripcion }}
              </option>
            </select>
          </div>

          <!-- Nivel 2 -->
          <div>
            <label class="block text-xs text-gray-600 mb-1">Nivel 2 (Subcategoría MF)</label>
            <select v-model="modal.form.nivel2" @change="onNivel2Change"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none"
              :disabled="!modal.form.nivel1">
              <option value="">Sin subcategoría</option>
              <option v-for="n in nivel2sFiltrados" :key="n.nivel2" :value="n.nivel2">
                {{ n.nivel2 }} — {{ n.descripcion }}
              </option>
            </select>
          </div>

          <!-- Ítem presupuestario (auto) -->
          <div>
            <label class="block text-xs text-gray-600 mb-1">Ítem Presupuestario (auto desde catálogo)</label>
            <input v-model="modal.form.item_presupuestario" type="text" maxlength="150"
              placeholder="Se llena automáticamente al seleccionar Nivel 2"
              class="w-full border rounded px-3 py-2 text-sm bg-gray-50 focus:ring-2 focus:ring-amber-300 outline-none" />
          </div>

          <div>
            <label class="block text-xs text-gray-600 mb-1">Nombre / Descripción *</label>
            <input v-model="modal.form.nombre" type="text" maxlength="200"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
          </div>

          <div>
            <label class="block text-xs text-gray-600 mb-1">Descripción adicional</label>
            <textarea v-model="modal.form.descripcion" rows="2" maxlength="500"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none"></textarea>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-gray-600 mb-1">Precio unitario (sin IVA) $</label>
              <input v-model.number="modal.form.precio_unitario" type="number" step="0.0001" min="0"
                class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
            </div>
            <div>
              <label class="block text-xs text-gray-600 mb-1">Tasa IVA</label>
              <select v-model="modal.form.iva_id"
                class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none">
                <option value="">Seleccionar...</option>
                <option v-for="iva in ivasActivos" :key="iva.id" :value="iva.id">
                  {{ iva.descripcion }} ({{ iva.porcentaje }}%)
                </option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-gray-600 mb-1">Categoría</label>
              <input v-model="modal.form.categoria" type="text" maxlength="100"
                placeholder="ej: Papelería, Limpieza"
                class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
            </div>
            <div>
              <label class="block text-xs text-gray-600 mb-1">Marca</label>
              <input v-model="modal.form.marca" type="text" maxlength="100"
                placeholder="ej: HP, BIC"
                class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
            </div>
          </div>
        </div>

        <p v-if="errorModal" class="text-red-600 text-sm mt-3">{{ errorModal }}</p>
        <div class="flex justify-end gap-3 mt-5">
          <button @click="modal.show = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
          <button @click="guardar" :disabled="guardando"
            class="bg-amber-700 text-white px-5 py-2 rounded-lg text-sm hover:bg-amber-800 disabled:opacity-50">
            {{ guardando ? 'Guardando...' : 'Guardar' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal configuración porcentaje -->
    <div v-if="modalConfig.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
        <h2 class="text-lg font-bold mb-4">Configurar Stock Mínimo</h2>
        <p class="text-sm text-gray-600 mb-4">
          Se genera alerta cuando el stock actual es menor o igual al
          <b>{{ modalConfig.porcentaje }}%</b> del stock máximo histórico del artículo.
        </p>
        <div>
          <label class="block text-xs text-gray-600 mb-1">Porcentaje mínimo (%)</label>
          <input v-model="modalConfig.porcentaje" type="number" min="1" max="100"
            class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
        </div>
        <div class="flex justify-end gap-3 mt-5">
          <button @click="modalConfig.show = false" class="px-4 py-2 text-sm text-gray-600">Cancelar</button>
          <button @click="guardarConfiguracion"
            class="bg-amber-700 text-white px-5 py-2 rounded-lg text-sm hover:bg-amber-800">
            Guardar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

const articulos        = ref([])
const busqueda         = ref('')
const filtroEstado     = ref('')
const porcentajeMinimo = ref(20)
const guardando        = ref(false)
const errorModal       = ref('')
const nivel1s          = ref([])
const catalogo         = ref([])
const ivasActivos      = ref([])
const unidades         = ref([])

const modal      = ref({ show: false, editando: false, id: null, form: {} })
const modalConfig = ref({ show: false, porcentaje: 20 })

const articulosFiltrados = computed(() => {
  return articulos.value.filter(a => {
    const q = busqueda.value.toLowerCase()
    const coincide = !q ||
      a.nombre?.toLowerCase().includes(q) ||
      a.codigo?.toLowerCase().includes(q) ||
      a.nivel1?.toLowerCase().includes(q) ||
      a.nivel2?.toLowerCase().includes(q) ||
      a.item_presupuestario?.toLowerCase().includes(q)
    if (filtroEstado.value === 'alerta') return coincide && a.bajo_minimo
    if (filtroEstado.value) return coincide && a.estado === filtroEstado.value
    return coincide
  })
})

const nivel2sFiltrados = computed(() => {
  if (!modal.value.form.nivel1) return []
  return catalogo.value.filter(c => c.nivel1 === modal.value.form.nivel1)
})

function fmt(v) { return parseFloat(v || 0).toFixed(2) }

function onNivel1Change() {
  modal.value.form.nivel2 = ''
  modal.value.form.item_presupuestario = ''
}

function onNivel2Change() {
  const n2 = modal.value.form.nivel2
  if (!n2) { modal.value.form.item_presupuestario = ''; return }
  const item = catalogo.value.find(c => c.nivel2 === n2)
  modal.value.form.item_presupuestario = item?.asociacion_presupuestaria || ''
}

async function cargar() {
  const { data } = await api.get('/adquisiciones/articulos')
  articulos.value = data
}

async function cargarConfig() {
  const { data } = await api.get('/adquisiciones/configuracion')
  porcentajeMinimo.value = data.porcentaje_stock_minimo?.valor || 20
}

async function cargarCatalogo() {
  const [n1, cat, iv, um] = await Promise.all([
    api.get('/adquisiciones/catalogo-inventario/nivel1s'),
    api.get('/adquisiciones/catalogo-inventario', { params: { por_pagina: 1000 } }),
    api.get('/adquisiciones/iva'),
    api.get('/adquisiciones/unidades-medida'),
  ])
  nivel1s.value    = n1.data
  catalogo.value   = cat.data?.data || cat.data
  ivasActivos.value = iv.data.filter(i => i.activo)
  unidades.value   = um.data
}

onMounted(async () => {
  await Promise.all([cargar(), cargarConfig(), cargarCatalogo()])
})

const formVacio = () => ({
  codigo: '', nombre: '', descripcion: '', unidad_medida: '',
  nivel1: '', nivel2: '', item_presupuestario: '',
  precio_unitario: 0, iva_id: null,
  categoria: '', marca: '',
})

function abrirModalCrear() {
  modal.value = { show: true, editando: false, id: null, form: formVacio() }
  errorModal.value = ''
}

function abrirEditar(a) {
  modal.value = {
    show: true, editando: true, id: a.id,
    form: {
      codigo: a.codigo, nombre: a.nombre, descripcion: a.descripcion,
      unidad_medida: a.unidad_medida, nivel1: a.nivel1 || '', nivel2: a.nivel2 || '',
      item_presupuestario: a.item_presupuestario || '',
      precio_unitario: parseFloat(a.precio_unitario || 0),
      iva_id: a.iva_id || null,
      categoria: a.categoria || '', marca: a.marca || '',
    }
  }
  errorModal.value = ''
}

async function guardar() {
  errorModal.value = ''
  if (!modal.value.form.codigo || !modal.value.form.nombre) {
    errorModal.value = 'Código y nombre son requeridos.'; return
  }
  guardando.value = true
  try {
    const payload = { ...modal.value.form }
    if (!payload.nivel1) { payload.nivel1 = null; payload.nivel2 = null }
    if (!payload.nivel2) payload.nivel2 = null
    if (!payload.iva_id) payload.iva_id = null

    if (modal.value.editando) {
      await api.put(`/adquisiciones/articulos/${modal.value.id}`, payload)
    } else {
      await api.post('/adquisiciones/articulos', payload)
    }
    modal.value.show = false
    await cargar()
  } catch (e) {
    errorModal.value = e.response?.data?.message || 'Error al guardar'
    if (e.response?.data?.errors) {
      errorModal.value = Object.values(e.response.data.errors).flat().join(' | ')
    }
  } finally { guardando.value = false }
}

async function inactivar(id) {
  if (!confirm('¿Inactivar este artículo?')) return
  await api.patch(`/adquisiciones/articulos/${id}/inactivar`)
  await cargar()
}

function abrirConfiguracion() {
  modalConfig.value = { show: true, porcentaje: porcentajeMinimo.value }
}

async function guardarConfiguracion() {
  await api.put('/adquisiciones/configuracion', { porcentaje_stock_minimo: modalConfig.value.porcentaje })
  porcentajeMinimo.value = modalConfig.value.porcentaje
  modalConfig.value.show = false
  await cargar()
}
</script>
