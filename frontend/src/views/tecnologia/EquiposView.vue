<template>
  <div>
    <div class="flex flex-wrap justify-between items-center gap-3 mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Inventario de Equipos</h1>
      <div class="flex items-center gap-2">
        <button @click="abrirImportar" class="px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#4d7c8a]/5 border"
          style="color:#4d7c8a; border-color:#4d7c8a;">
          Importar CSV
        </button>
        <button @click="abrirCrear" class="text-white px-4 py-2 rounded-lg text-sm font-medium hover:opacity-90 shadow-sm"
          style="background-color:#4d7c8a;">
          + Nuevo equipo
        </button>
      </div>
    </div>

    <!-- Tarjetas de resumen -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-3 mb-5">
      <button v-for="c in statCards" :key="c.key" @click="toggleStatCard(c)"
        class="rounded-xl p-4 text-left border-l-4 bg-white shadow-sm transition hover:shadow-md hover:-translate-y-0.5"
        :class="[c.borde, activoStatCard(c) ? 'ring-2 ring-offset-1' : '']"
        :style="activoStatCard(c) ? `--tw-ring-color:${c.color}` : ''">
        <p class="text-xs font-semibold uppercase tracking-wide" :style="`color:${c.color}`">{{ c.titulo }}</p>
        <p class="text-2xl font-bold text-gray-800 mt-1">{{ resumen ? resumen[c.key] : '—' }}</p>
      </button>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow-sm p-4 flex flex-wrap gap-3 items-end mb-4">
      <div class="flex-1 min-w-56">
        <label class="block text-xs text-gray-500 mb-1">Buscar</label>
        <input v-model="filtros.busqueda" @input="cargarDebounced" type="text"
          placeholder="Código, serie, marca, modelo, descripción..."
          class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2" style="--tw-ring-color:#4d7c8a55;" />
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">Tipo</label>
        <select v-model="filtros.tipo_equipo_id" @change="pagina = 1; cargar()" class="border rounded-lg px-3 py-2 text-sm">
          <option value="">Todos</option>
          <option v-for="t in tipos" :key="t.id" :value="t.id">{{ t.nombre }}</option>
        </select>
      </div>
      <div>
        <label class="block text-xs text-gray-500 mb-1">Estado</label>
        <select v-model="filtros.estado" @change="pagina = 1; cargar()" class="border rounded-lg px-3 py-2 text-sm">
          <option value="">Todos</option>
          <option value="DISPONIBLE">Disponible</option>
          <option value="ASIGNADO">Asignado</option>
          <option value="DAÑADO">Dañado</option>
          <option value="DE_BAJA">De baja</option>
        </select>
      </div>
      <span class="ml-auto text-xs font-semibold px-3 py-1.5 rounded-full"
        style="background-color:#4d7c8a1a; color:#4d7c8a;">
        {{ total }} registro{{ total === 1 ? '' : 's' }} encontrado{{ total === 1 ? '' : 's' }}
      </span>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <div v-if="!equipos.length" class="p-10 text-center text-gray-400">
        Sin equipos registrados
      </div>
      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-white" style="background-color:#4d7c8a;">
              <th class="px-4 py-3">Código</th>
              <th class="px-4 py-3">Equipo</th>
              <th class="px-4 py-3">Serie</th>
              <th class="px-4 py-3">Estado</th>
              <th class="px-4 py-3">Custodio</th>
              <th class="px-4 py-3 text-right">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="e in equipos" :key="e.id" class="hover:bg-gray-50 transition">
              <td class="px-4 py-3 font-mono text-xs font-medium text-gray-700">{{ e.codigo_bien }}</td>
              <td class="px-4 py-3">
                <p class="font-medium text-gray-800">
                  {{ e.marca }} {{ e.modelo }}
                  <span v-if="e.vida_util_vencida" class="ml-1.5 text-[10px] font-semibold text-orange-700 bg-orange-100 px-1.5 py-0.5 rounded">
                    Vida útil vencida
                  </span>
                </p>
                <p class="text-xs text-gray-400">
                  {{ e.tipo_equipo?.nombre }}<span v-if="e.descripcion"> · {{ e.descripcion }}</span>
                </p>
              </td>
              <td class="px-4 py-3 text-gray-500 text-xs">{{ e.serie || '—' }}</td>
              <td class="px-4 py-3">
                <span :class="estadoBadge(e.estado)" class="px-2 py-0.5 rounded-full text-xs font-medium">
                  {{ estadoLabel(e.estado) }}
                </span>
              </td>
              <td class="px-4 py-3 text-xs">
                <span v-if="e.asignacion_activa" class="font-medium" style="color:#4d7c8a;">
                  {{ e.asignacion_activa.empleado?.apellido_emp }} {{ e.asignacion_activa.empleado?.nombre_emp }}
                </span>
                <span v-else class="text-gray-300">—</span>
                <span v-if="e.custodio_inactivo" class="block mt-0.5 text-[10px] font-semibold text-red-700 bg-red-100 px-1.5 py-0.5 rounded w-fit">
                  ⚠ Empleado inactivo
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-end gap-1.5 flex-wrap">
                  <button v-if="e.estado === 'DISPONIBLE'" @click="abrirAsignar(e)"
                    class="text-xs text-white font-medium px-3 py-1 rounded-lg" style="background-color:#4d7c8a;">
                    Asignar
                  </button>
                  <button v-if="e.estado === 'ASIGNADO'" @click="abrirDevolver(e)"
                    class="text-xs text-white font-medium px-3 py-1 rounded-lg bg-amber-600 hover:bg-amber-700">
                    Devolver
                  </button>
                  <button v-if="e.estado === 'DAÑADO'" @click="marcarDisponible(e)"
                    class="text-xs text-white font-medium px-3 py-1 rounded-lg bg-green-600 hover:bg-green-700">
                    Disponible
                  </button>
                  <button @click="abrirHistorial(e)"
                    class="text-xs text-gray-600 hover:text-gray-900 font-medium border border-gray-300 px-3 py-1 rounded-lg hover:bg-gray-50">
                    Historial
                  </button>
                  <button @click="abrirEditar(e)"
                    class="text-xs text-gray-600 hover:text-gray-900 font-medium border border-gray-300 px-3 py-1 rounded-lg hover:bg-gray-50">
                    Editar
                  </button>
                  <button v-if="e.estado !== 'ASIGNADO' && e.estado !== 'DE_BAJA'" @click="marcarBaja(e)"
                    class="text-xs text-red-600 hover:text-red-800 font-medium border border-red-200 px-3 py-1 rounded-lg hover:bg-red-50">
                    Dar de baja
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Paginación -->
      <div v-if="totalPaginas > 1" class="px-4 py-3 border-t flex items-center justify-center gap-1 text-sm">
        <button @click="cambiarPagina(1)" :disabled="pagina === 1" class="px-2 py-1 rounded border disabled:opacity-40 hover:bg-gray-50">«</button>
        <button @click="cambiarPagina(pagina - 1)" :disabled="pagina === 1" class="px-3 py-1 rounded border disabled:opacity-40 hover:bg-gray-50">‹</button>
        <span class="px-3 font-medium text-gray-600">{{ pagina }} / {{ totalPaginas }}</span>
        <button @click="cambiarPagina(pagina + 1)" :disabled="pagina >= totalPaginas" class="px-3 py-1 rounded border disabled:opacity-40 hover:bg-gray-50">›</button>
        <button @click="cambiarPagina(totalPaginas)" :disabled="pagina >= totalPaginas" class="px-2 py-1 rounded border disabled:opacity-40 hover:bg-gray-50">»</button>
      </div>
    </div>

    <!-- Modal crear/editar -->
    <div v-if="modal.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">
        <div class="px-6 py-4 flex items-center justify-between" style="background-color:#4d7c8a;">
          <h2 class="text-lg font-bold text-white">{{ modal.id ? 'Editar Equipo' : 'Nuevo Equipo' }}</h2>
          <button @click="modal.show = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-6 max-h-[70vh] overflow-y-auto">
          <div class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Código de Bien *</label>
                <input v-model="form.codigo_bien" v-uppercase class="w-full border rounded-lg px-3 py-2 text-sm" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Tipo</label>
                <select v-model="form.tipo_equipo_id" class="w-full border rounded-lg px-3 py-2 text-sm">
                  <option value="">Seleccione...</option>
                  <option v-for="t in tipos" :key="t.id" :value="t.id">{{ t.nombre }}</option>
                </select>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Marca</label>
                <input v-model="form.marca" v-uppercase class="w-full border rounded-lg px-3 py-2 text-sm" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Modelo</label>
                <input v-model="form.modelo" v-uppercase class="w-full border rounded-lg px-3 py-2 text-sm" />
              </div>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Descripción</label>
              <input v-model="form.descripcion" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Serie</label>
                <input v-model="form.serie" v-uppercase class="w-full border rounded-lg px-3 py-2 text-sm" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Condición</label>
                <select v-model="form.condicion" class="w-full border rounded-lg px-3 py-2 text-sm">
                  <option value="">Seleccione...</option>
                  <option value="BUENO">Bueno</option>
                  <option value="REGULAR">Regular</option>
                  <option value="MALO">Malo</option>
                </select>
              </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Fecha de Ingreso</label>
                <input v-model="form.fecha_ingreso" type="date" class="w-full border rounded-lg px-3 py-2 text-sm" />
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Vida Útil (años)</label>
                <input v-model.number="form.vida_util_anios" type="number" min="0"
                  class="w-full border rounded-lg px-3 py-2 text-sm" />
              </div>
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Ubicación</label>
              <input v-model="form.ubicacion" placeholder="Ej. Bodega TI" class="w-full border rounded-lg px-3 py-2 text-sm" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Observaciones</label>
              <textarea v-model="form.observaciones" rows="2" class="w-full border rounded-lg px-3 py-2 text-sm"></textarea>
            </div>
          </div>
          <p v-if="error" class="text-red-600 text-sm mt-3">{{ error }}</p>
          <div class="flex justify-end gap-2 mt-5">
            <button @click="modal.show = false" class="px-4 py-2 text-sm text-gray-600 border rounded-lg">Cancelar</button>
            <button @click="guardar" :disabled="guardando"
              class="px-5 py-2 text-sm text-white rounded-lg hover:opacity-90 disabled:opacity-50"
              style="background-color:#4d7c8a;">
              {{ guardando ? 'Guardando...' : 'Guardar' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Asignar -->
    <div v-if="modalAsignar.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 flex items-start justify-between" style="background-color:#4d7c8a;">
          <div>
            <h2 class="text-lg font-bold text-white">Asignar Equipo</h2>
            <p class="text-white/80 text-xs mt-0.5">{{ modalAsignar.equipo?.codigo_bien }}</p>
          </div>
          <button @click="modalAsignar.show = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-6">
          <label class="block text-xs font-semibold text-gray-600 mb-1">Buscar empleado *</label>
          <input v-model="busquedaEmp" @input="buscarEmpleado" type="text"
            placeholder="Nombre, apellido o cédula..."
            class="w-full border rounded-lg px-3 py-2 text-sm" />

          <div v-if="resultadosBusqueda.length > 0 && !empleadoSeleccionado"
            class="mt-2 border rounded-lg divide-y max-h-48 overflow-y-auto shadow-sm">
            <button v-for="emp in resultadosBusqueda" :key="emp.id_emp" @click="seleccionarEmpleado(emp)"
              class="w-full text-left px-4 py-2.5 hover:bg-gray-50 text-sm transition flex items-center justify-between gap-2">
              <span>
                <span class="font-medium">{{ emp.apellido_emp }} {{ emp.nombre_emp }}</span>
                <span class="text-gray-400 ml-2 text-xs">{{ emp.identificacion }}</span>
              </span>
              <span :class="emp.estado === 'ACTIVO' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full flex-shrink-0">
                {{ emp.estado }}
              </span>
            </button>
          </div>

          <div v-if="empleadoSeleccionado" class="mt-2 flex items-center justify-between bg-gray-50 border rounded-lg px-4 py-3">
            <div>
              <p class="font-semibold text-gray-800">{{ empleadoSeleccionado.apellido_emp }} {{ empleadoSeleccionado.nombre_emp }}</p>
              <p class="text-xs text-gray-500">{{ empleadoSeleccionado.identificacion }}</p>
              <span v-if="empleadoSeleccionado.estado !== 'ACTIVO'" class="inline-block mt-1 text-[10px] font-semibold text-red-700 bg-red-100 px-1.5 py-0.5 rounded-full">
                ⚠ Empleado {{ empleadoSeleccionado.estado }}
              </span>
            </div>
            <button @click="empleadoSeleccionado = null; busquedaEmp = ''" class="text-gray-400 hover:text-gray-600 text-xs">Cambiar</button>
          </div>

          <div class="mt-3">
            <label class="block text-xs font-semibold text-gray-600 mb-1">Fecha de asignación *</label>
            <input v-model="formAsignar.fecha_asignacion" type="date" class="w-full border rounded-lg px-3 py-2 text-sm" />
          </div>

          <p v-if="error" class="text-red-600 text-sm mt-3">{{ error }}</p>
          <div class="flex justify-end gap-2 mt-5">
            <button @click="modalAsignar.show = false" class="px-4 py-2 text-sm text-gray-600 border rounded-lg">Cancelar</button>
            <button @click="confirmarAsignar" :disabled="guardando || !empleadoSeleccionado"
              class="px-5 py-2 text-sm text-white rounded-lg hover:opacity-90 disabled:opacity-50"
              style="background-color:#4d7c8a;">
              {{ guardando ? 'Asignando...' : 'Asignar' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Devolver -->
    <div v-if="modalDevolver.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 flex items-start justify-between" style="background-color:#4d7c8a;">
          <div>
            <h2 class="text-lg font-bold text-white">Devolver Equipo</h2>
            <p class="text-white/80 text-xs mt-0.5">{{ modalDevolver.equipo?.codigo_bien }}</p>
          </div>
          <button @click="modalDevolver.show = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-6">
          <label class="block text-xs font-semibold text-gray-600 mb-1">Fecha de devolución *</label>
          <input v-model="formDevolver.fecha_devolucion" type="date" class="w-full border rounded-lg px-3 py-2 text-sm" />

          <label class="block text-xs font-semibold text-gray-600 mb-1 mt-3">Motivo *</label>
          <select v-model="formDevolver.motivo_devolucion" class="w-full border rounded-lg px-3 py-2 text-sm">
            <option value="">Seleccione...</option>
            <option value="REASIGNACION">Reasignación</option>
            <option value="SALIDA_EMPLEADO">Salida del empleado</option>
            <option value="DAÑO">Daño</option>
            <option value="OTRO">Otro</option>
          </select>
          <label class="block text-xs font-semibold text-gray-600 mb-1 mt-3">Observación</label>
          <textarea v-model="formDevolver.observacion" rows="3" class="w-full border rounded-lg px-3 py-2 text-sm"></textarea>

          <p v-if="error" class="text-red-600 text-sm mt-3">{{ error }}</p>
          <div class="flex justify-end gap-2 mt-5">
            <button @click="modalDevolver.show = false" class="px-4 py-2 text-sm text-gray-600 border rounded-lg">Cancelar</button>
            <button @click="confirmarDevolver" :disabled="guardando || !formDevolver.motivo_devolucion"
              class="px-5 py-2 text-sm text-white rounded-lg hover:opacity-90 disabled:opacity-50 bg-amber-600 hover:bg-amber-700">
              {{ guardando ? 'Guardando...' : 'Confirmar devolución' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Historial (timeline) -->
    <div v-if="modalHistorial.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg overflow-hidden">
        <div class="px-6 py-4 flex items-center justify-between" style="background-color:#4d7c8a;">
          <div>
            <h2 class="text-lg font-bold text-white">Historial de Custodia</h2>
            <p class="text-white/80 text-xs mt-0.5">{{ modalHistorial.equipo?.codigo_bien }}</p>
          </div>
          <button @click="modalHistorial.show = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-6 max-h-[60vh] overflow-y-auto">
          <div v-if="!modalHistorial.datos.length" class="text-center text-gray-400 py-6">Sin asignaciones registradas</div>
          <ol v-else class="relative border-l-2 ml-2" style="border-color:#4d7c8a33;">
            <li v-for="a in modalHistorial.datos" :key="a.id" class="mb-6 ml-5 last:mb-0">
              <span class="absolute -left-[9px] w-4 h-4 rounded-full border-2 border-white"
                :style="a.fecha_devolucion ? 'background-color:#9ca3af;' : 'background-color:#22c55e;'"></span>
              <div class="bg-gray-50 rounded-lg px-4 py-3">
                <div class="flex items-center justify-between gap-2">
                  <p class="font-semibold text-gray-800">{{ a.empleado?.apellido_emp }} {{ a.empleado?.nombre_emp }}</p>
                  <span v-if="!a.fecha_devolucion" class="text-xs font-semibold text-green-600 bg-green-100 px-2 py-0.5 rounded-full">Activa</span>
                </div>
                <p class="text-xs text-gray-500 mt-1">
                  Desde {{ a.fecha_asignacion }}
                  <span v-if="a.fecha_devolucion"> hasta {{ a.fecha_devolucion }} · {{ a.motivo_devolucion }}</span>
                </p>
                <p v-if="a.observacion" class="text-xs text-gray-400 mt-1">{{ a.observacion }}</p>
              </div>
            </li>
          </ol>
        </div>
      </div>
    </div>

    <!-- Modal Importar CSV -->
    <div v-if="modalImportar.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 flex items-center justify-between" style="background-color:#4d7c8a;">
          <h2 class="text-lg font-bold text-white">Importar Equipos (CSV)</h2>
          <button @click="modalImportar.show = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-6">
          <p class="text-xs text-gray-500 mb-3">
            Columnas: codigo_bien, tipo_equipo, marca, modelo, descripcion, serie, estado(condición), fecha_ingreso, vida_util_anios
          </p>
          <input type="file" accept=".csv,.txt" @change="onArchivoSeleccionado"
            class="w-full border rounded-lg px-3 py-2 text-sm" />

          <div v-if="modalImportar.errores.length" class="mt-3 bg-red-50 border border-red-200 rounded-lg p-3 max-h-40 overflow-y-auto">
            <p class="text-red-700 text-xs font-semibold mb-1">Errores encontrados:</p>
            <ul class="text-red-600 text-xs list-disc list-inside">
              <li v-for="(err, i) in modalImportar.errores" :key="i">{{ err }}</li>
            </ul>
          </div>
          <p v-if="modalImportar.mensaje" class="text-green-600 text-sm mt-3">{{ modalImportar.mensaje }}</p>

          <div class="flex justify-end gap-2 mt-5">
            <button @click="modalImportar.show = false" class="px-4 py-2 text-sm text-gray-600 border rounded-lg">Cerrar</button>
            <button @click="importarCsv" :disabled="!modalImportar.archivo || guardando"
              class="px-5 py-2 text-sm text-white rounded-lg hover:opacity-90 disabled:opacity-50"
              style="background-color:#4d7c8a;">
              {{ guardando ? 'Importando...' : 'Importar' }}
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

const equipos  = ref([])
const tipos    = ref([])
const filtros  = ref({ busqueda: '', tipo_equipo_id: '', estado: '', vida_util_vencida: false, custodio_inactivo: false })

const pagina      = ref(1)
const totalPaginas = ref(1)
const total        = ref(0)

const resumen = ref(null)
const statCards = [
  { key: 'total',              titulo: 'Total',              tipo: 'estado', estado: '',          color: '#4d7c8a', borde: 'border-[#4d7c8a]' },
  { key: 'disponible',          titulo: 'Disponibles',        tipo: 'estado', estado: 'DISPONIBLE', color: '#16a34a', borde: 'border-green-500' },
  { key: 'asignado',            titulo: 'Asignados',          tipo: 'estado', estado: 'ASIGNADO',   color: '#2563eb', borde: 'border-blue-500' },
  { key: 'danado',              titulo: 'Dañados',            tipo: 'estado', estado: 'DAÑADO',    color: '#dc2626', borde: 'border-red-500' },
  { key: 'de_baja',             titulo: 'De baja',            tipo: 'estado', estado: 'DE_BAJA',   color: '#6b7280', borde: 'border-gray-400' },
  { key: 'vida_util_vencida',   titulo: 'Vida útil vencida',  tipo: 'flag', filtroKey: 'vida_util_vencida', color: '#ea580c', borde: 'border-orange-500' },
  { key: 'custodio_inactivo',   titulo: 'Custodio inactivo',  tipo: 'flag', filtroKey: 'custodio_inactivo', color: '#dc2626', borde: 'border-red-400' },
]

function activoStatCard(c) {
  return c.tipo === 'estado' ? filtros.value.estado === c.estado : filtros.value[c.filtroKey]
}

function toggleStatCard(c) {
  if (c.tipo === 'estado') {
    filtros.value.estado = filtros.value.estado === c.estado ? '' : c.estado
  } else {
    filtros.value[c.filtroKey] = !filtros.value[c.filtroKey]
  }
  pagina.value = 1
  cargar()
}

const guardando = ref(false)
const error     = ref('')
const modal     = ref({ show: false, id: null })
const form      = ref({})

let debounceTimer = null
function cargarDebounced() {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => { pagina.value = 1; cargar() }, 300)
}

async function cargar() {
  const { data } = await api.get('/tecnologia/equipos', {
    params: {
      busqueda: filtros.value.busqueda || undefined,
      tipo_equipo_id: filtros.value.tipo_equipo_id || undefined,
      estado: filtros.value.estado || undefined,
      vida_util_vencida: filtros.value.vida_util_vencida ? 1 : undefined,
      custodio_inactivo: filtros.value.custodio_inactivo ? 1 : undefined,
      page: pagina.value,
    },
  })
  equipos.value      = data.data ?? data
  total.value         = data.total ?? equipos.value.length
  totalPaginas.value  = data.last_page ?? 1
}

async function cargarResumen() {
  const { data } = await api.get('/tecnologia/equipos/resumen')
  resumen.value = data
}

function cambiarPagina(p) {
  if (p < 1 || p > totalPaginas.value) return
  pagina.value = p
  cargar()
}

async function cargarTipos() {
  const { data } = await api.get('/tecnologia/tipos-equipo/activos')
  tipos.value = data
}

function estadoBadge(e) {
  if (e === 'DISPONIBLE') return 'bg-green-100 text-green-700'
  if (e === 'ASIGNADO') return 'bg-blue-100 text-blue-700'
  if (e === 'DAÑADO') return 'bg-red-100 text-red-700'
  return 'bg-gray-200 text-gray-600'
}
function estadoLabel(e) {
  const labels = { DISPONIBLE: 'Disponible', ASIGNADO: 'Asignado', 'DAÑADO': 'Dañado', DE_BAJA: 'De baja' }
  return labels[e] || e
}

function abrirCrear() {
  form.value = { codigo_bien: '', tipo_equipo_id: '', marca: '', modelo: '', descripcion: '',
    serie: '', condicion: '', fecha_ingreso: '', vida_util_anios: null, ubicacion: '', observaciones: '' }
  modal.value = { show: true, id: null }
  error.value = ''
}

function abrirEditar(e) {
  form.value = { ...e, tipo_equipo_id: e.tipo_equipo_id || '' }
  modal.value = { show: true, id: e.id }
  error.value = ''
}

async function guardar() {
  error.value = ''
  guardando.value = true
  try {
    if (modal.value.id) {
      await api.put(`/tecnologia/equipos/${modal.value.id}`, form.value)
    } else {
      await api.post('/tecnologia/equipos', form.value)
    }
    modal.value.show = false
    await Promise.all([cargar(), cargarResumen()])
  } catch (e) {
    error.value = e.response?.data?.message || Object.values(e.response?.data?.errors || {})[0]?.[0] || 'Error al guardar'
  } finally {
    guardando.value = false
  }
}

async function marcarBaja(e) {
  if (!confirm(`¿Dar de baja el equipo ${e.codigo_bien}?`)) return
  await api.patch(`/tecnologia/equipos/${e.id}/baja`)
  await Promise.all([cargar(), cargarResumen()])
}

async function marcarDisponible(e) {
  await api.patch(`/tecnologia/equipos/${e.id}/disponible`)
  await Promise.all([cargar(), cargarResumen()])
}

// ─── Asignar ──────────────────────────────────────────────────────────────
const modalAsignar        = ref({ show: false, equipo: null })
const formAsignar         = ref({ fecha_asignacion: '' })
const busquedaEmp         = ref('')
const resultadosBusqueda  = ref([])
const empleadoSeleccionado = ref(null)
let   busquedaTimer       = null

function abrirAsignar(e) {
  modalAsignar.value = { show: true, equipo: e }
  formAsignar.value = { fecha_asignacion: new Date().toISOString().substring(0, 10) }
  busquedaEmp.value = ''
  resultadosBusqueda.value = []
  empleadoSeleccionado.value = null
  error.value = ''
}

function buscarEmpleado() {
  if (empleadoSeleccionado.value) return
  clearTimeout(busquedaTimer)
  if (busquedaEmp.value.length < 2) { resultadosBusqueda.value = []; return }
  busquedaTimer = setTimeout(async () => {
    try {
      const { data } = await api.get('/empleados', { params: { buscar: busquedaEmp.value, per_page: 10 } })
      resultadosBusqueda.value = data.data ?? data
    } catch { resultadosBusqueda.value = [] }
  }, 300)
}

function seleccionarEmpleado(emp) {
  empleadoSeleccionado.value = emp
  busquedaEmp.value = `${emp.apellido_emp} ${emp.nombre_emp}`
  resultadosBusqueda.value = []
}

async function confirmarAsignar() {
  error.value = ''
  guardando.value = true
  try {
    await api.patch(`/tecnologia/equipos/${modalAsignar.value.equipo.id}/asignar`, {
      id_emp: empleadoSeleccionado.value.id_emp,
      fecha_asignacion: formAsignar.value.fecha_asignacion,
    })
    modalAsignar.value.show = false
    await Promise.all([cargar(), cargarResumen()])
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al asignar'
  } finally {
    guardando.value = false
  }
}

// ─── Devolver ─────────────────────────────────────────────────────────────
const modalDevolver = ref({ show: false, equipo: null })
const formDevolver   = ref({ motivo_devolucion: '', observacion: '' })

function abrirDevolver(e) {
  modalDevolver.value = { show: true, equipo: e }
  formDevolver.value = { fecha_devolucion: new Date().toISOString().substring(0, 10), motivo_devolucion: '', observacion: '' }
  error.value = ''
}

async function confirmarDevolver() {
  error.value = ''
  guardando.value = true
  try {
    await api.patch(`/tecnologia/equipos/${modalDevolver.value.equipo.id}/devolver`, formDevolver.value)
    modalDevolver.value.show = false
    await Promise.all([cargar(), cargarResumen()])
  } catch (e) {
    error.value = e.response?.data?.message || 'Error al devolver'
  } finally {
    guardando.value = false
  }
}

// ─── Historial ────────────────────────────────────────────────────────────
const modalHistorial = ref({ show: false, equipo: null, datos: [] })

async function abrirHistorial(e) {
  modalHistorial.value = { show: true, equipo: e, datos: [] }
  const { data } = await api.get(`/tecnologia/equipos/${e.id}/historial`)
  modalHistorial.value.datos = data
}

// ─── Importar CSV ─────────────────────────────────────────────────────────
const modalImportar = ref({ show: false, archivo: null, errores: [], mensaje: '' })

function abrirImportar() {
  modalImportar.value = { show: true, archivo: null, errores: [], mensaje: '' }
}

function onArchivoSeleccionado(e) {
  modalImportar.value.archivo = e.target.files[0] || null
  modalImportar.value.errores = []
  modalImportar.value.mensaje = ''
}

async function importarCsv() {
  guardando.value = true
  modalImportar.value.errores = []
  modalImportar.value.mensaje = ''
  try {
    const fd = new FormData()
    fd.append('archivo', modalImportar.value.archivo)
    const { data } = await api.post('/tecnologia/equipos/importar-csv', fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    modalImportar.value.mensaje = data.message
    await Promise.all([cargar(), cargarResumen()])
  } catch (e) {
    modalImportar.value.errores = e.response?.data?.errores || [e.response?.data?.message || 'Error al importar']
  } finally {
    guardando.value = false
  }
}

onMounted(async () => {
  await cargarTipos()
  await Promise.all([cargar(), cargarResumen()])
})
</script>
