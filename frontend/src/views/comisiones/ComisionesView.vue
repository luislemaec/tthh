<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">Comisiones de Servicio</h1>
        <p class="text-sm text-gray-500 mt-0.5">Gestión de viajes al interior y exterior</p>
      </div>
      <button @click="abrirNueva"
        class="flex items-center gap-2 px-4 py-2 text-white text-sm font-semibold rounded-lg shadow transition hover:opacity-90"
        style="background-color:#5c4a6e;">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Nueva Comisión
      </button>
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 mb-4 border-b border-gray-200">
      <button v-for="tab in tabsVisibles" :key="tab.key" @click="tabActivo = tab.key"
        :class="['px-4 py-2.5 text-sm font-medium transition border-b-2 -mb-px', tabActivo === tab.key
          ? 'border-[#5c4a6e] text-[#5c4a6e]'
          : 'border-transparent text-gray-500 hover:text-gray-700']">
        {{ tab.label }}
      </button>
    </div>

    <div v-if="cargando" class="flex items-center justify-center py-16">
      <div class="w-8 h-8 border-2 border-[#5c4a6e] border-t-transparent rounded-full animate-spin"/>
    </div>

    <template v-else>
      <!-- ═══ TAB: Mis Comisiones ═══ -->
      <div v-if="tabActivo === 'mis'">
        <div v-if="errorCarga" class="p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm mb-4">
          Error al cargar: {{ errorCarga }}
        </div>
        <div v-if="solicitudesMias.length === 0 && !errorCarga" class="text-center py-16 text-gray-400">
          <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
          <p class="text-sm">No tiene comisiones registradas.</p>
          <button @click="abrirNueva" class="mt-3 text-sm font-medium" style="color:#5c4a6e;">+ Nueva Comisión</button>
        </div>
        <div v-else class="space-y-3">
          <div v-for="sol in solicitudesMias" :key="sol.id"
            @click="abrirStepper(sol)"
            class="bg-white rounded-lg shadow-sm border p-4 cursor-pointer transition hover:shadow-md"
            :class="sol.estado === 'DEVUELTO' ? 'border-red-300 hover:border-red-400' : 'border-gray-100 hover:border-[#5c4a6e]/30'">
            <div class="flex items-start justify-between gap-3">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-semibold text-gray-800 text-sm">{{ sol.numero_solicitud || 'Sin número' }}</span>
                  <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full', badgeEstado(sol.estado).class]">
                    {{ badgeEstado(sol.estado).label }}
                  </span>
                  <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                    :class="sol.tipo === 'EXTERIOR' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'">
                    {{ sol.tipo }}
                  </span>
                  <span v-if="sol.estado === 'BORRADOR' || sol.estado === 'DEVUELTO'"
                    class="text-xs font-medium"
                    :class="sol.docs_count >= 3 ? 'text-green-600' : 'text-orange-500'">
                    {{ sol.docs_count }}/3 docs
                  </span>
                </div>
                <p class="text-sm text-gray-600 mt-1 truncate">{{ sol.destino }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ formatFecha(sol.fecha_salida) }} → {{ formatFecha(sol.fecha_llegada) }}</p>
                <!-- Mini stepper dots -->
                <div class="flex items-center gap-1 mt-2">
                  <template v-for="(p, i) in miniPasos(sol)" :key="i">
                    <div class="w-2 h-2 rounded-full flex-shrink-0"
                      :class="{
                        'bg-green-500': p === 'completo',
                        'bg-[#5c4a6e]': p === 'activo',
                        'bg-red-500':   p === 'devuelto',
                        'bg-gray-200':  p === 'pendiente',
                      }"/>
                    <div v-if="i < 3" class="w-5 h-px flex-shrink-0"
                      :class="p === 'completo' ? 'bg-green-300' : 'bg-gray-200'"/>
                  </template>
                  <span class="text-xs text-gray-400 ml-1">{{ miniPasoLabel(sol) }}</span>
                </div>
              </div>
              <svg class="w-4 h-4 text-gray-300 flex-shrink-0 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══ TAB: Todas las Comisiones ═══ -->
      <div v-if="tabActivo === 'todas'">
        <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-4 mb-4 flex gap-3 flex-wrap">
          <select v-model="filtroEstado" class="text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none">
            <option value="">Todos los estados</option>
            <option v-for="e in estadoOpciones" :key="e.value" :value="e.value">{{ e.label }}</option>
          </select>
          <select v-model="filtroTipo" class="text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none">
            <option value="">Interior y Exterior</option>
            <option value="INTERIOR">Interior</option>
            <option value="EXTERIOR">Exterior</option>
          </select>
          <input v-model="filtroBusqueda" type="text" placeholder="Buscar empleado o destino..."
            class="flex-1 min-w-[180px] text-sm border border-gray-300 rounded-md px-3 py-1.5 focus:outline-none"/>
        </div>
        <div v-if="solicitudesFiltradas.length === 0" class="text-center py-12 text-gray-400 text-sm">
          No hay comisiones que coincidan con los filtros.
        </div>
        <div v-else class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-xs text-gray-500 uppercase border-b border-gray-100" style="background-color:#f9f7fb;">
                <th class="px-4 py-3 text-left font-semibold">N°</th>
                <th class="px-4 py-3 text-left font-semibold">Empleado</th>
                <th class="px-4 py-3 text-left font-semibold">Tipo</th>
                <th class="px-4 py-3 text-left font-semibold">Destino</th>
                <th class="px-4 py-3 text-left font-semibold">Salida</th>
                <th class="px-4 py-3 text-left font-semibold">Estado</th>
                <th class="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="sol in solicitudesFiltradas" :key="sol.id"
                @click="abrirStepper(sol)"
                class="border-b border-gray-50 hover:bg-purple-50/30 transition cursor-pointer">
                <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ sol.numero_solicitud || '—' }}</td>
                <td class="px-4 py-3">
                  <p class="font-medium text-gray-800">{{ sol.nombre_empleado }}</p>
                </td>
                <td class="px-4 py-3">
                  <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                    :class="sol.tipo === 'EXTERIOR' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'">
                    {{ sol.tipo }}
                  </span>
                </td>
                <td class="px-4 py-3 text-gray-600 max-w-[160px] truncate">{{ sol.destino }}</td>
                <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">{{ formatFecha(sol.fecha_salida) }}</td>
                <td class="px-4 py-3">
                  <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full', badgeEstado(sol.estado).class]">
                    {{ badgeEstado(sol.estado).label }}
                  </span>
                </td>
                <td class="px-4 py-3 text-gray-300">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                  </svg>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>

  <!-- ══════════════════ MODAL STEPPER ══════════════════ -->
  <div v-if="modalStepper" class="fixed inset-0 z-50 flex items-start justify-center pt-3 pb-3 px-2">
    <div class="fixed inset-0 bg-black/40" @click="cerrarStepper"/>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-3xl max-h-[96vh] flex flex-col z-10">

      <!-- Header -->
      <div class="flex items-center justify-between px-5 py-3 rounded-t-xl text-white flex-shrink-0" style="background-color:#5c4a6e">
        <div class="min-w-0">
          <p class="text-sm font-bold truncate">
            {{ stepperData?.numero_solicitud || (stepperData ? 'Comisión en Borrador' : 'Nueva Comisión') }}
          </p>
          <p v-if="stepperData?.destino" class="text-xs text-white/70 truncate">{{ stepperData.destino }}</p>
        </div>
        <button @click="cerrarStepper" class="text-white hover:text-gray-200 text-xl font-bold leading-none ml-4 flex-shrink-0">×</button>
      </div>

      <!-- STEPPER BAR -->
      <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/60 flex-shrink-0">
        <div class="flex items-start justify-between">
          <template v-for="(paso, i) in pasos" :key="paso.id">
            <button @click="navegarPaso(paso)"
              :disabled="paso.estado === 'pendiente'"
              class="flex flex-col items-center gap-1 flex-1"
              :class="paso.estado === 'pendiente' ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'">
              <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold border-2 transition"
                :class="{
                  'border-[#5c4a6e] bg-[#5c4a6e] text-white shadow': paso.estado === 'activo' && stepperPasoVisible === paso.id,
                  'border-[#5c4a6e]/40 bg-[#5c4a6e]/10 text-[#5c4a6e]': paso.estado === 'activo' && stepperPasoVisible !== paso.id,
                  'border-green-500 bg-green-500 text-white': paso.estado === 'completo',
                  'border-red-500 bg-red-500 text-white': paso.estado === 'devuelto',
                  'border-gray-200 bg-white text-gray-400': paso.estado === 'pendiente',
                }">
                <svg v-if="paso.estado === 'completo'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <svg v-else-if="paso.estado === 'devuelto'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                <span v-else>{{ paso.num }}</span>
              </div>
              <span class="text-xs font-medium text-center leading-tight px-1"
                :class="{
                  'text-[#5c4a6e] font-bold': paso.estado === 'activo',
                  'text-green-600': paso.estado === 'completo',
                  'text-red-600 font-bold': paso.estado === 'devuelto',
                  'text-gray-400': paso.estado === 'pendiente',
                }">{{ paso.label }}</span>
            </button>
            <!-- Conector -->
            <div v-if="i < pasos.length - 1" class="flex-none w-8 h-0.5 mt-4 mx-0.5"
              :class="pasos[i].estado === 'completo' ? 'bg-green-400' : 'bg-gray-200'"/>
          </template>
        </div>
      </div>

      <!-- Contenido -->
      <div class="overflow-y-auto flex-1 p-5">

        <!-- Loading -->
        <div v-if="stepperCargando" class="flex justify-center py-12">
          <div class="w-8 h-8 border-2 border-[#5c4a6e] border-t-transparent rounded-full animate-spin"/>
        </div>

        <template v-if="!stepperCargando">

          <!-- BANNER DEVUELTO -->
          <div v-if="stepperData?.estado === 'DEVUELTO' && stepperPasoVisible === 'solicitud'"
            class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg flex gap-2">
            <svg class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <div>
              <p class="text-sm font-semibold text-red-700">Solicitud devuelta para corrección</p>
              <p class="text-xs text-red-600 mt-0.5">{{ stepperData.observacion_devolucion }}</p>
              <p class="text-xs text-red-400 mt-1">Corrija lo necesario en cualquier pestaña y vuelva a procesar.</p>
            </div>
          </div>

          <!-- ═══ PASO 1 — SOLICITUD (editable: BORRADOR / PROCESADO / DEVUELTO / nueva) ═══ -->
          <template v-if="stepperPasoVisible === 'solicitud' && paso1Editable">
            <!-- Sub-tabs -->
            <div class="flex gap-0 border rounded-lg overflow-hidden mb-4">
              <button v-for="t in formTabs" :key="t" @click="formTab = t"
                :class="['flex-1 py-2 text-xs font-semibold transition', formTab === t ? 'text-white' : 'bg-gray-50 text-gray-500 hover:bg-gray-100']"
                :style="formTab === t ? 'background-color:#5c4a6e;' : ''">
                {{ t }}
              </button>
            </div>

            <!-- Tab: Datos Generales -->
            <div v-if="formTab === 'Datos Generales'" class="space-y-3">
              <div v-if="modoEdicionId" class="bg-gray-50 border border-gray-200 rounded-md px-3 py-2 flex items-center gap-2">
                <span class="text-xs font-semibold text-gray-500">Fecha de Solicitud:</span>
                <span class="text-sm text-gray-700">{{ form.fecha_solicitud || '—' }}</span>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="text-xs font-semibold text-gray-600 mb-1 block">Tipo *</label>
                  <select v-model="form.tipo" @change="onTipoCambio"
                    class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]">
                    <option value="INTERIOR">Interior (Nacional)</option>
                    <option value="EXTERIOR">Exterior (Internacional)</option>
                  </select>
                </div>
                <div v-if="form.tipo === 'INTERIOR'">
                  <label class="text-xs font-semibold text-gray-600 mb-1 block">Destino *</label>
                  <div class="grid grid-cols-2 gap-1">
                    <select v-model="form_provinciaId" @change="onProvinciaChange"
                      class="text-sm border border-gray-300 rounded-md px-2 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]">
                      <option :value="null">Provincia...</option>
                      <option v-for="p in provincias" :key="p.id" :value="p.id">{{ p.nombre }}</option>
                    </select>
                    <select v-model="form_ciudadId" :disabled="!form_provinciaId" @change="onCiudadChange"
                      class="text-sm border border-gray-300 rounded-md px-2 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e] disabled:bg-gray-50 disabled:text-gray-400">
                      <option :value="null">Ciudad...</option>
                      <option v-for="c in ciudadesDisponibles" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                    </select>
                  </div>
                </div>
                <div v-else>
                  <label class="text-xs font-semibold text-gray-600 mb-1 block">Destino *</label>
                  <input v-model="form.destino" type="text" placeholder="País / Ciudad"
                    class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]" style="text-transform:uppercase"/>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="text-xs font-semibold text-gray-600 mb-1 block">Fecha de Salida *</label>
                  <input v-model="form.fecha_salida" type="date" class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]"/>
                </div>
                <div>
                  <label class="text-xs font-semibold text-gray-600 mb-1 block">Hora de Salida *</label>
                  <input v-model="form.hora_salida" type="time" class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]"/>
                </div>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="text-xs font-semibold text-gray-600 mb-1 block">Fecha de Llegada *</label>
                  <input v-model="form.fecha_llegada" type="date" class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]"/>
                </div>
                <div>
                  <label class="text-xs font-semibold text-gray-600 mb-1 block">Hora de Llegada *</label>
                  <input v-model="form.hora_llegada" type="time" class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]"/>
                </div>
              </div>
              <div>
                <label class="text-xs font-semibold text-gray-600 mb-1 block">Descripción de Actividades *</label>
                <textarea v-model="form.descripcion_actividades" rows="3" placeholder="Describa las actividades a realizar..."
                  class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e] resize-none" style="text-transform:uppercase"/>
              </div>
              <div class="flex gap-4">
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                  <input type="checkbox" v-model="form.tiene_viaticos" class="rounded accent-[#5c4a6e]"/>Viáticos
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                  <input type="checkbox" v-model="form.tiene_movilizaciones" class="rounded accent-[#5c4a6e]"/>Movilizaciones
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                  <input type="checkbox" v-model="form.tiene_anticipo" class="rounded accent-[#5c4a6e]"/>Anticipo
                </label>
              </div>
              <div v-if="errorForm" class="px-3 py-2 bg-red-50 border border-red-200 rounded-md text-red-700 text-xs">{{ errorForm }}</div>
            </div>

            <!-- Tab: Servidores -->
            <div v-if="formTab === 'Servidores'" class="space-y-3">
              <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Servidor Comisionado</p>
                <div class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                  <span class="text-gray-500">Nombres:</span>
                  <span class="font-medium text-gray-800">{{ auth.empleado?.apellido }} {{ auth.empleado?.nombre }}</span>
                  <span class="text-gray-500">Cédula:</span>
                  <span class="font-mono text-gray-700">{{ auth.empleado?.identificacion }}</span>
                  <span class="text-gray-500">Cargo:</span>
                  <span class="text-gray-700">{{ auth.empleado?.cargo || '—' }}</span>
                  <span class="text-gray-500">Unidad:</span>
                  <span class="text-gray-700">{{ auth.empleado?.departamento || '—' }}</span>
                  <span class="text-gray-500">Banco:</span>
                  <span class="text-gray-700">{{ auth.empleado?.banco || '—' }}</span>
                  <span class="text-gray-500">Tipo / N° Cuenta:</span>
                  <span class="text-gray-700">{{ auth.empleado?.tipo_cuenta || '—' }} / {{ auth.empleado?.numero_cuenta || '—' }}</span>
                </div>
                <p class="text-xs text-gray-400 mt-3 italic">Para actualizar datos bancarios, edite su ficha de empleado.</p>
              </div>
            </div>

            <!-- Tab: Transporte -->
            <div v-if="formTab === 'Transporte'" class="space-y-3">
              <p class="text-xs text-gray-500">Itinerario de transporte (ida y regreso)</p>
              <div v-for="(trn, i) in form.transportes" :key="i" class="border border-gray-100 rounded-lg p-3 space-y-2">
                <div class="flex justify-between items-center">
                  <span class="text-xs font-semibold text-gray-500">Tramo {{ i+1 }}</span>
                  <button @click="form.transportes.splice(i,1)" class="text-red-400 hover:text-red-600">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                  </button>
                </div>
                <div class="grid grid-cols-2 gap-2">
                  <input v-model="trn.tipo" type="text" placeholder="Tipo (Aéreo, Terrestre...)"
                    class="text-sm border border-gray-300 rounded px-2 py-1.5 focus:outline-none" style="text-transform:uppercase"/>
                  <input v-model="trn.nombre" type="text" placeholder="Empresa / Vuelo"
                    class="text-sm border border-gray-300 rounded px-2 py-1.5 focus:outline-none" style="text-transform:uppercase"/>
                </div>
                <input v-model="trn.ruta" type="text" placeholder="Ruta (Ej: Quito - Guayaquil)"
                  class="w-full text-sm border border-gray-300 rounded px-2 py-1.5 focus:outline-none" style="text-transform:uppercase"/>
                <div class="grid grid-cols-4 gap-2">
                  <input v-model="trn.salida_fecha" type="date" class="text-sm border border-gray-300 rounded px-2 py-1.5 focus:outline-none"/>
                  <input v-model="trn.salida_hora" type="time" class="text-sm border border-gray-300 rounded px-2 py-1.5 focus:outline-none"/>
                  <input v-model="trn.llegada_fecha" type="date" class="text-sm border border-gray-300 rounded px-2 py-1.5 focus:outline-none"/>
                  <input v-model="trn.llegada_hora" type="time" class="text-sm border border-gray-300 rounded px-2 py-1.5 focus:outline-none"/>
                </div>
              </div>
              <button @click="form.transportes.push({tipo:'',nombre:'',ruta:'',salida_fecha:'',salida_hora:'',llegada_fecha:'',llegada_hora:''})"
                class="text-sm font-medium" style="color:#5c4a6e;">
                + Agregar tramo
              </button>
            </div>

            <!-- Tab: Documentos -->
            <div v-if="formTab === 'Documentos'" class="space-y-3">
              <div v-if="!modoEdicionId" class="p-6 bg-gray-50 rounded-lg border border-gray-200 text-center space-y-3">
                <p class="text-sm text-gray-500">Complete el formulario en <strong>Datos Generales</strong> y presione <strong>Guardar</strong>.</p>
                <button @click="formTab = 'Datos Generales'" class="text-sm font-semibold underline" style="color:#5c4a6e;">← Ir a Datos Generales</button>
              </div>
              <template v-else>
                <p class="text-xs text-gray-600 font-medium">Adjunte los 3 documentos requeridos, luego genere el PDF y suba el firmado.</p>
                <div v-for="slot in slotsDocumento" :key="slot.tipo"
                  class="flex items-center justify-between p-3 rounded-lg border transition"
                  :class="docPorTipo(slot.tipo) ? 'border-green-300 bg-green-50' : 'border-gray-200 bg-white'">
                  <div class="flex items-center gap-2 text-sm">
                    <span v-if="docPorTipo(slot.tipo)" class="text-green-600 font-bold text-base leading-none">✓</span>
                    <span v-else class="w-5 h-5 rounded-full border-2 border-gray-300 flex items-center justify-center text-xs text-gray-400 flex-shrink-0">!</span>
                    <span :class="docPorTipo(slot.tipo) ? 'text-green-800 font-medium' : 'text-gray-700'">{{ slot.label }}</span>
                  </div>
                  <div class="flex gap-2 items-center">
                    <button v-if="docPorTipo(slot.tipo)" @click="descargarDocumento(docPorTipo(slot.tipo))" class="text-xs text-blue-600 hover:underline">Ver</button>
                    <button v-if="docPorTipo(slot.tipo) && ['BORRADOR','DEVUELTO'].includes(stepperData?.estado)" @click="eliminarDocumento(docPorTipo(slot.tipo))" class="text-xs text-red-500 hover:underline">Eliminar</button>
                    <label v-if="!docPorTipo(slot.tipo) && ['BORRADOR','DEVUELTO'].includes(stepperData?.estado)" class="cursor-pointer">
                      <input type="file" accept=".pdf" class="hidden" @change="subirDocumento($event, slot.tipo)"/>
                      <span class="px-3 py-1 text-xs font-semibold text-white rounded"
                        :style="subiendoDoc === slot.tipo ? 'background-color:#9d8aae' : 'background-color:#5c4a6e'">
                        {{ subiendoDoc === slot.tipo ? 'Subiendo...' : 'Subir PDF' }}
                      </span>
                    </label>
                  </div>
                </div>

                <!-- BORRADOR / DEVUELTO: procesar cuando 3 docs listos -->
                <div v-if="['BORRADOR','DEVUELTO'].includes(stepperData?.estado)" class="border-t border-gray-200 pt-3 space-y-2">
                  <p v-if="!todosDocSubidos" class="text-xs text-center text-orange-500">
                    Faltan {{ 3 - docsSubidos }} documento(s) requerido(s).
                  </p>
                  <button v-if="todosDocSubidos" @click="procesarDesdeCard({id: modoEdicionId, destino: form.destino || stepperData?.destino})"
                    :disabled="procesando"
                    class="w-full py-2 text-sm font-semibold text-white rounded-lg disabled:opacity-50 hover:opacity-90"
                    style="background-color:#5c4a6e;">
                    {{ procesando ? 'Procesando...' : 'Procesar Solicitud →' }}
                  </button>
                </div>

                <!-- PROCESADO: generar PDF + subir firmado -->
                <div v-if="stepperData?.estado === 'PROCESADO'" class="border-t border-gray-200 pt-3 space-y-3">
                  <div class="p-3 bg-blue-50 rounded-lg border border-blue-200 text-blue-800 text-xs font-medium">
                    ✓ Solicitud procesada — Número: {{ stepperData.numero_solicitud }}<br/>
                    Genere el PDF, obtenga las firmas y suba el documento firmado.
                  </div>
                  <button @click="generarPdfSolicitud"
                    class="w-full py-2 text-sm font-semibold rounded border-2 border-[#5c4a6e] text-[#5c4a6e] hover:bg-purple-50 transition">
                    Generar PDF de Solicitud
                  </button>
                  <div v-if="!docFirmadoSubido">
                    <p class="text-xs text-gray-500 mb-2">Una vez firmado, suba el PDF:</p>
                    <label class="cursor-pointer block">
                      <input type="file" accept=".pdf" class="hidden" @change="subirPdfFirmado($event)" :disabled="subiendoFirmado"/>
                      <span class="block w-full py-2 text-center text-sm font-semibold text-white rounded"
                        :style="subiendoFirmado ? 'background-color:#9d8aae; cursor:not-allowed' : 'background-color:#5c4a6e; cursor:pointer'">
                        {{ subiendoFirmado ? 'Procesando...' : 'Subir PDF Firmado → APROBADO' }}
                      </span>
                    </label>
                  </div>
                  <div v-else class="flex items-center gap-2 text-green-700 text-sm font-medium p-3 bg-green-50 rounded-lg border border-green-200">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    PDF Firmado subido correctamente
                  </div>
                </div>
              </template>
            </div>
          </template>

          <!-- ═══ PASO 1 — SOLICITUD (lectura: cuando el usuario navega desde un paso posterior) ═══ -->
          <template v-if="stepperPasoVisible === 'solicitud' && !paso1Editable && stepperData">
            <div class="space-y-4 text-sm">
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <p class="text-xs text-gray-400 font-semibold mb-1">EMPLEADO</p>
                  <p class="font-medium">{{ stepperData.nombre_empleado }}</p>
                  <p class="text-xs text-gray-500">{{ stepperData.unidad_nombre }}</p>
                </div>
                <div>
                  <p class="text-xs text-gray-400 font-semibold mb-1">N° SOLICITUD</p>
                  <p class="font-mono font-semibold" style="color:#5c4a6e">{{ stepperData.numero_solicitud }}</p>
                </div>
                <div>
                  <p class="text-xs text-gray-400 font-semibold mb-1">DESTINO</p>
                  <p>{{ stepperData.destino }}</p>
                </div>
                <div>
                  <p class="text-xs text-gray-400 font-semibold mb-1">FECHAS</p>
                  <p>{{ formatFecha(stepperData.fecha_salida) }} {{ stepperData.hora_salida }}</p>
                  <p class="text-gray-500">→ {{ formatFecha(stepperData.fecha_llegada) }} {{ stepperData.hora_llegada }}</p>
                </div>
              </div>
              <div>
                <p class="text-xs text-gray-400 font-semibold mb-1">ACTIVIDADES PLANIFICADAS</p>
                <p class="text-gray-700 whitespace-pre-line">{{ stepperData.descripcion_actividades }}</p>
              </div>
              <div v-if="stepperData.transportes?.length">
                <p class="text-xs text-gray-400 font-semibold mb-2">ITINERARIO</p>
                <div v-for="trn in stepperData.transportes" :key="trn.id"
                  class="text-xs text-gray-600 py-1.5 border-b border-gray-50">
                  <span class="font-semibold">{{ trn.tipo }}</span>
                  <span v-if="trn.nombre"> — {{ trn.nombre }}</span>
                  <span v-if="trn.ruta"> — {{ trn.ruta }}</span>
                  <span class="text-gray-400 ml-2">{{ trn.salida_fecha }} {{ trn.salida_hora }} → {{ trn.llegada_fecha }} {{ trn.llegada_hora }}</span>
                </div>
              </div>
              <button @click="descargarPdf(stepperData)"
                class="flex items-center gap-2 text-sm text-red-600 hover:text-red-700 font-medium transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
                Descargar PDF Solicitud
              </button>
            </div>
          </template>

          <!-- ═══ PASO 2 — INFORME ═══ -->
          <template v-if="stepperPasoVisible === 'informe'">

            <!-- Informe APROBADO -->
            <div v-if="stepperData?.informe?.estado === 'APROBADO' && !informeReabrible" class="flex flex-col items-center py-8 text-center gap-4">
              <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
              </div>
              <div>
                <p class="font-semibold text-gray-800 text-lg">Informe Aprobado</p>
                <p class="text-sm text-gray-500 mt-1">El PDF firmado fue archivado correctamente.</p>
              </div>
              <button @click="descargarInformeFirmado"
                class="px-5 py-2 text-sm font-semibold text-white rounded-lg hover:opacity-90" style="background-color:#5c4a6e;">
                Descargar PDF Firmado
              </button>
            </div>

            <!-- Formulario informe -->
            <template v-else>
              <div class="space-y-3">
                <div v-if="informeReabrible" class="text-sm bg-red-50 border border-red-200 text-red-700 rounded-lg p-3">
                  Este informe ya estaba aprobado, pero la solicitud fue devuelta para corrección.
                  Ajuste los datos que correspondan y vuelva a generar y firmar el PDF.
                  <span v-if="stepperData?.observacion_devolucion" class="block mt-1 font-medium">
                    Motivo: {{ stepperData.observacion_devolucion }}
                  </span>
                </div>
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <label class="text-xs font-semibold text-gray-600 mb-1 block">Fecha del Informe *</label>
                    <input v-model="informeForm.fecha_informe" type="date"
                      class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none"/>
                  </div>
                  <div></div>
                  <div>
                    <label class="text-xs font-semibold text-gray-600 mb-1 block">Fecha Real de Salida *</label>
                    <input v-model="informeForm.fecha_salida" type="date"
                      class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none"/>
                  </div>
                  <div>
                    <label class="text-xs font-semibold text-gray-600 mb-1 block">Hora de Salida *</label>
                    <input v-model="informeForm.hora_salida" type="time"
                      class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none"/>
                  </div>
                  <div>
                    <label class="text-xs font-semibold text-gray-600 mb-1 block">Fecha Real de Llegada *</label>
                    <input v-model="informeForm.fecha_llegada" type="date"
                      class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none"/>
                  </div>
                  <div>
                    <label class="text-xs font-semibold text-gray-600 mb-1 block">Hora de Llegada *</label>
                    <input v-model="informeForm.hora_llegada" type="time"
                      class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none"/>
                  </div>
                </div>
                <div>
                  <label class="text-xs font-semibold text-gray-600 mb-1 block">Actividades Realizadas *</label>
                  <textarea v-model="informeForm.actividades" rows="4" placeholder="Describa las actividades realizadas..."
                    class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none resize-none" style="text-transform:uppercase"/>
                </div>
                <div>
                  <label class="text-xs font-semibold text-gray-600 mb-1 block">Productos / Resultados</label>
                  <textarea v-model="informeForm.productos" rows="2" placeholder="Resultados obtenidos..."
                    class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none resize-none" style="text-transform:uppercase"/>
                </div>

                <!-- Acciones post-guardado (informe PRESENTADO) -->
                <div v-if="stepperData?.informe?.estado === 'PRESENTADO'" class="border-t border-gray-200 pt-3 space-y-2">
                  <p class="text-xs text-gray-500 font-medium">Genere el PDF, obtenga las firmas y suba el PDF firmado:</p>
                  <div class="flex gap-3">
                    <button @click="descargarPdfInforme"
                      class="flex-1 py-2 text-sm font-semibold rounded border-2 border-[#5c4a6e] text-[#5c4a6e] hover:bg-purple-50 transition">
                      Generar PDF Informe
                    </button>
                    <label class="flex-1 cursor-pointer">
                      <input type="file" accept=".pdf" class="hidden" @change="subirInformeFirmado($event)" :disabled="subiendoInformeFirmado"/>
                      <span class="block w-full py-2 text-center text-sm font-semibold text-white rounded"
                        :style="subiendoInformeFirmado ? 'background-color:#9d8aae; cursor:not-allowed' : 'background-color:#5c4a6e; cursor:pointer'">
                        {{ subiendoInformeFirmado ? 'Procesando...' : 'Subir PDF Firmado → APROBADO' }}
                      </span>
                    </label>
                  </div>
                </div>
                <p v-if="errorAccion" class="text-red-600 text-xs">{{ errorAccion }}</p>
              </div>
            </template>
          </template>

          <!-- ═══ PASO 3 — SOLICITUD DE PAGO ═══ -->
          <template v-if="stepperPasoVisible === 'pago'">
            <div v-if="stepperData?.estado === 'INFORME_APROBADO'" class="text-center py-8 space-y-4">
              <div class="w-16 h-16 mx-auto bg-blue-100 rounded-full flex items-center justify-center">
                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
              </div>
              <div>
                <p class="font-semibold text-gray-800">Solicitud de Pago de Viáticos</p>
                <p class="text-sm text-gray-500 mt-1">El informe fue aprobado. Puede solicitar el pago.</p>
              </div>
              <button @click="solicitarPago(stepperData)"
                class="px-6 py-2.5 text-sm font-semibold text-white rounded-lg hover:opacity-90" style="background-color:#5c4a6e;">
                Solicitar Pago de Viáticos
              </button>
            </div>
            <div v-else class="p-4 bg-blue-50 rounded-lg border border-blue-200 text-blue-700 text-sm">
              ✓ Pago solicitado — En proceso de liquidación por el área financiera.
            </div>
          </template>

          <!-- ═══ PASO 4 — LIQUIDACIÓN ═══ -->
          <template v-if="stepperPasoVisible === 'liquidacion'">
            <div class="space-y-3">
              <div class="p-4 bg-gray-50 rounded-lg border border-gray-100">
                <p class="text-sm font-semibold text-gray-700 mb-2">Estado actual</p>
                <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full', badgeEstado(stepperData?.estado).class]">
                  {{ badgeEstado(stepperData?.estado).label }}
                </span>
              </div>
              <p class="text-xs text-gray-500">El departamento financiero está procesando su liquidación de viáticos.</p>
              <div v-if="stepperData?.estado === 'CERRADO'" class="flex items-center gap-2 text-green-700 text-sm font-medium p-3 bg-green-50 rounded-lg border border-green-200">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Comisión cerrada — Pago completado
              </div>
            </div>
          </template>

        </template>
      </div>

      <!-- Footer -->
      <div class="p-4 border-t border-gray-100 flex justify-between items-center flex-shrink-0">
        <div class="flex items-center gap-2">
          <button @click="cerrarStepper" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">
            Cerrar
          </button>
          <!-- Botón Devolver (para roles financieros, cuando la solicitud ya está en algún estado posterior a BORRADOR) -->
          <button v-if="stepperData && !['BORRADOR','PROCESADO','DEVUELTO','CERRADO'].includes(stepperData.estado) && esFinanciero"
            @click="abrirModalDevolver"
            class="px-4 py-2 text-sm font-semibold text-red-600 border border-red-300 rounded-lg hover:bg-red-50 transition">
            Devolver
          </button>
        </div>
        <div class="flex items-center gap-3">
          <!-- Paso 1 editable, no en tab Documentos: Guardar -->
          <button v-if="stepperPasoVisible === 'solicitud' && paso1Editable && formTab !== 'Documentos'"
            @click="guardarSolicitud" :disabled="guardando"
            class="px-5 py-2 text-sm font-semibold text-white rounded-lg disabled:opacity-50 hover:opacity-90 transition"
            style="background-color:#5c4a6e;">
            {{ guardando ? 'Guardando...' : (modoEdicionId ? 'Actualizar' : 'Guardar') }}
          </button>
          <!-- Paso 2: Guardar Informe (cuando no está APROBADO, o se reabrió tras una devolución) -->
          <button v-if="stepperPasoVisible === 'informe' && (stepperData?.informe?.estado !== 'APROBADO' || informeReabrible)"
            @click="guardarInforme" :disabled="guardando"
            class="px-5 py-2 text-sm font-semibold text-white rounded-lg disabled:opacity-50 hover:opacity-90 transition"
            style="background-color:#5c4a6e;">
            {{ guardando ? 'Guardando...' : (stepperData?.informe ? 'Actualizar Informe' : 'Guardar Informe') }}
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL DEVOLVER -->
  <div v-if="modalDevolver" class="fixed inset-0 z-[60] flex items-center justify-center px-4">
    <div class="fixed inset-0 bg-black/50" @click="modalDevolver = false"/>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md z-10 overflow-hidden">
      <div class="flex items-center justify-between px-6 py-4" style="background-color:#dc2626;">
        <h3 class="text-lg font-bold text-white">Devolver solicitud</h3>
        <button type="button" @click="modalDevolver = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
      </div>
      <div class="p-6">
      <p class="text-sm text-gray-500 mb-4">Indique el motivo de la devolución. El empleado verá este mensaje y podrá corregir su solicitud desde el inicio.</p>
      <textarea v-model="observacionDevolucion" rows="4" placeholder="Describa qué debe corregirse..."
        class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-red-400 resize-none" style="text-transform:uppercase"/>
      <p v-if="errorDevolucion" class="text-red-600 text-xs mt-1">{{ errorDevolucion }}</p>
      <div class="flex justify-end gap-3 mt-4">
        <button @click="modalDevolver = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
        <button @click="confirmarDevolucion" :disabled="devolviendo"
          class="px-5 py-2 text-sm font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-50">
          {{ devolviendo ? 'Devolviendo...' : 'Confirmar Devolución' }}
        </button>
      </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const auth = useAuthStore()

// ── State ────────────────────────────────────────────────────
const cargando    = ref(true)
const errorCarga  = ref('')
const solicitudes = ref([])
const miRol       = ref({})
const tabActivo   = ref('mis')

const filtroEstado   = ref('')
const filtroTipo     = ref('')
const filtroBusqueda = ref('')

// Stepper
const modalStepper       = ref(false)
const stepperData        = ref(null)   // datos completos de la solicitud activa
const stepperPasoVisible = ref('solicitud')
const stepperCargando    = ref(false)

// Formulario (paso 1)
const modoEdicionId = ref(null)
const guardando     = ref(false)
const errorForm     = ref('')
const formTab       = ref('Datos Generales')
const procesando    = ref(false)

// Provincia / ciudad
const provincias       = ref([])
const form_provinciaId = ref(null)
const form_ciudadId    = ref(null)

// Documentos
const documentos      = ref([])
const subiendoDoc     = ref('')
const subiendoFirmado = ref(false)

// Informe
const informeActual          = ref(null)
const subiendoInformeFirmado = ref(false)
const errorAccion            = ref('')

// Devolver
const modalDevolver        = ref(false)
const observacionDevolucion = ref('')
const errorDevolucion      = ref('')
const devolviendo          = ref(false)

const slotsDocumento = [
  { tipo: 'AUTORIZACION',  label: 'Solicitud de Autorización y Aprobación' },
  { tipo: 'PASAJES',       label: 'Pasajes Aéreos / Terrestres' },
  { tipo: 'CERTIFICACION', label: 'Certificación Presupuestaria' },
]

const formVacio = () => ({
  tipo: 'INTERIOR',
  destino: '',
  fecha_solicitud: '',
  fecha_salida: '',
  hora_salida: '08:00',
  fecha_llegada: '',
  hora_llegada: '18:00',
  descripcion_actividades: '',
  tiene_viaticos: true,
  tiene_movilizaciones: false,
  tiene_anticipo: false,
  transportes: [],
})
const form = ref(formVacio())

const informeForm = ref({
  fecha_informe: new Date().toISOString().slice(0, 10),
  actividades: '',
  productos: '',
  fecha_salida: '',
  hora_salida: '',
  fecha_llegada: '',
  hora_llegada: '',
})

const formTabs = ['Datos Generales', 'Servidores', 'Transporte', 'Documentos']

// ── Computed ─────────────────────────────────────────────────
const idEmp = computed(() => auth.empleado?.id_emp)

const solicitudesMias = computed(() =>
  solicitudes.value.filter(s => s.id_emp?.trim() === idEmp.value?.trim())
)

const solicitudesFiltradas = computed(() => {
  let res = solicitudes.value
  if (filtroEstado.value)   res = res.filter(s => s.estado === filtroEstado.value)
  if (filtroTipo.value)     res = res.filter(s => s.tipo === filtroTipo.value)
  if (filtroBusqueda.value) {
    const q = filtroBusqueda.value.toLowerCase()
    res = res.filter(s =>
      (s.destino || '').toLowerCase().includes(q) ||
      (s.nombre_empleado || '').toLowerCase().includes(q)
    )
  }
  return res
})

const tabsVisibles = computed(() => {
  const tabs = [{ key: 'mis', label: 'Mis Comisiones' }]
  if (miRol.value.es_admin || miRol.value.es_maxima_autoridad || miRol.value.es_contabilidad
      || miRol.value.es_presupuesto || miRol.value.es_dir_financiero || miRol.value.es_tesoreria) {
    tabs.push({ key: 'todas', label: 'Todas las Comisiones' })
  }
  return tabs
})

const estadoOpciones = [
  { value: 'BORRADOR',         label: 'Borrador' },
  { value: 'PROCESADO',        label: 'Procesado' },
  { value: 'DEVUELTO',         label: 'Devuelto' },
  { value: 'APROBADO',         label: 'Aprobado' },
  { value: 'INFORME_APROBADO', label: 'Informe Aprobado' },
  { value: 'EN_PAGO',          label: 'En Pago' },
  { value: 'EN_LIQUIDACION',   label: 'En Liquidación' },
  { value: 'CERRADO',          label: 'Cerrado' },
  { value: 'POR_COBRAR',       label: 'Por Cobrar' },
]

const ciudadesDisponibles = computed(() =>
  provincias.value.find(p => p.id === form_provinciaId.value)?.ciudades ?? []
)

const docPorTipo = (tipo) => documentos.value.find(d => d.tipo_doc === tipo)

const docsSubidos = computed(() =>
  slotsDocumento.filter(s => documentos.value.some(d => d.tipo_doc === s.tipo)).length
)
const todosDocSubidos = computed(() => docsSubidos.value === slotsDocumento.length)
const docFirmadoSubido = computed(() => documentos.value.some(d => d.tipo_doc === 'FIRMADO'))

// Rol financiero (puede devolver)
const esFinanciero = computed(() =>
  miRol.value.es_contabilidad || miRol.value.es_presupuesto
  || miRol.value.es_dir_financiero || miRol.value.es_tesoreria
  || miRol.value.es_admin
)

// La solicitud regresó a estos estados por una devolución financiera después de que el
// informe ya había sido APROBADO — únicos casos donde tiene sentido reabrirlo sin pasar
// de nuevo por todo el Paso 1 (procesar + volver a firmar la solicitud).
const informeReabrible = computed(() =>
  stepperData.value?.informe?.estado === 'APROBADO' &&
  ['DEVUELTO', 'PROCESADO', 'APROBADO'].includes(stepperData.value?.estado)
)

// Stepper pasos
const pasoActivoPorEstado = computed(() => {
  const e = stepperData.value?.estado
  if (informeReabrible.value) return 'informe'
  if (!e || ['BORRADOR', 'PROCESADO', 'DEVUELTO'].includes(e)) return 'solicitud'
  if (e === 'APROBADO') return 'informe'
  if (e === 'INFORME_APROBADO') return 'pago'
  return 'liquidacion'
})

const paso1Editable = computed(() =>
  !stepperData.value || ['BORRADOR', 'PROCESADO', 'DEVUELTO'].includes(stepperData.value?.estado)
)

const pasos = computed(() => {
  const e = stepperData.value?.estado

  const estadoPaso1 = e === 'DEVUELTO' ? 'devuelto'
    : !e || ['BORRADOR', 'PROCESADO'].includes(e) ? 'activo'
    : 'completo'

  const estadoPaso2 = informeReabrible.value ? 'activo'
    : !e || ['BORRADOR', 'PROCESADO', 'DEVUELTO'].includes(e) ? 'pendiente'
    : e === 'APROBADO' ? 'activo'
    : 'completo'

  const estadoPaso3 = ['EN_PAGO', 'EN_LIQUIDACION', 'POR_COBRAR', 'CERRADO'].includes(e) ? 'completo'
    : e === 'INFORME_APROBADO' ? 'activo'
    : 'pendiente'

  const estadoPaso4 = ['EN_LIQUIDACION', 'POR_COBRAR', 'CERRADO'].includes(e) ? 'activo'
    : 'pendiente'

  return [
    { id: 'solicitud',    num: 1, label: 'Solicitud',    estado: estadoPaso1 },
    { id: 'informe',      num: 2, label: 'Informe',      estado: estadoPaso2 },
    { id: 'pago',         num: 3, label: 'Pago',         estado: estadoPaso3 },
    { id: 'liquidacion',  num: 4, label: 'Liquidación',  estado: estadoPaso4 },
  ]
})

// Mini pasos para las tarjetas de la lista
function miniPasos(sol) {
  const e = sol.estado
  const p1 = e === 'DEVUELTO' ? 'devuelto'
    : ['BORRADOR', 'PROCESADO'].includes(e) ? 'activo' : 'completo'
  const p2 = ['BORRADOR', 'PROCESADO', 'DEVUELTO'].includes(e) ? 'pendiente'
    : e === 'APROBADO' ? 'activo' : 'completo'
  const p3 = ['EN_PAGO', 'EN_LIQUIDACION', 'POR_COBRAR', 'CERRADO'].includes(e) ? 'completo'
    : e === 'INFORME_APROBADO' ? 'activo' : 'pendiente'
  const p4 = ['EN_LIQUIDACION', 'POR_COBRAR', 'CERRADO'].includes(e) ? 'activo' : 'pendiente'
  return [p1, p2, p3, p4]
}

function miniPasoLabel(sol) {
  const mapa = {
    BORRADOR: 'Completar solicitud',
    PROCESADO: 'Pendiente firma',
    DEVUELTO: 'Requiere corrección',
    APROBADO: 'Elaborar informe',
    INFORME_APROBADO: 'Solicitar pago',
    EN_PAGO: 'En liquidación',
    EN_LIQUIDACION: 'En liquidación',
    POR_COBRAR: 'Devolución pendiente',
    CERRADO: 'Completado',
  }
  return mapa[sol.estado] || sol.estado
}

// ── Helpers ──────────────────────────────────────────────────
function badgeEstado(estado) {
  const mapa = {
    BORRADOR:         { class: 'bg-gray-100 text-gray-600',        label: 'Borrador' },
    PROCESADO:        { class: 'bg-yellow-100 text-yellow-700',     label: 'Procesado' },
    DEVUELTO:         { class: 'bg-red-100 text-red-700',           label: 'Devuelto' },
    APROBADO:         { class: 'bg-green-100 text-green-700',       label: 'Aprobado' },
    INFORME_APROBADO: { class: 'bg-teal-100 text-teal-700',         label: 'Informe Aprobado' },
    EN_PAGO:          { class: 'bg-blue-100 text-blue-700',         label: 'En Pago' },
    EN_LIQUIDACION:   { class: 'bg-cyan-100 text-cyan-700',         label: 'En Liquidación' },
    POR_COBRAR:       { class: 'bg-orange-100 text-orange-700',     label: 'Por Cobrar' },
    CERRADO:          { class: 'bg-emerald-100 text-emerald-800',   label: 'Cerrado' },
  }
  return mapa[estado] || { class: 'bg-gray-100 text-gray-600', label: estado }
}

function formatFecha(f) {
  if (!f) return '—'
  const [y, m, d] = f.split('-')
  return `${d}/${m}/${y}`
}

// ── Cargar lista ─────────────────────────────────────────────
async function cargar() {
  cargando.value   = true
  errorCarga.value = ''
  try {
    const [rolResp, solResp] = await Promise.all([
      api.get('/comisiones/mi-rol'),
      api.get('/comisiones/solicitudes'),
    ])
    miRol.value       = rolResp.data
    solicitudes.value = solResp.data.data || solResp.data
  } catch (e) {
    errorCarga.value = e.response?.data?.message || `Error ${e.response?.status || ''}: no se pudieron cargar las solicitudes.`
  } finally {
    cargando.value = false
  }
  try {
    const { data } = await api.get('/comisiones/provincias')
    provincias.value = data
  } catch { /* silencioso */ }
}

onMounted(cargar)

// ── Stepper ──────────────────────────────────────────────────
function abrirNueva() {
  form.value           = formVacio()
  modoEdicionId.value  = null
  formTab.value        = 'Datos Generales'
  form_provinciaId.value = null
  form_ciudadId.value    = null
  errorForm.value      = ''
  documentos.value     = []
  stepperData.value    = null
  stepperPasoVisible.value = 'solicitud'
  stepperCargando.value = false
  modalStepper.value   = true
}

async function abrirStepper(sol) {
  form.value           = formVacio()
  modoEdicionId.value  = null
  formTab.value        = 'Datos Generales'
  form_provinciaId.value = null
  form_ciudadId.value    = null
  errorForm.value      = ''
  errorAccion.value    = ''
  documentos.value     = []
  informeActual.value  = null
  stepperData.value    = null
  stepperCargando.value = true
  modalStepper.value   = true

  try {
    const { data } = await api.get(`/comisiones/solicitudes/${sol.id}`)
    stepperData.value   = data
    modoEdicionId.value = data.id

    // Poblar formulario
    form.value = {
      tipo:                    data.tipo,
      destino:                 data.destino,
      fecha_solicitud:         data.fecha_solicitud,
      fecha_salida:            data.fecha_salida,
      hora_salida:             data.hora_salida,
      fecha_llegada:           data.fecha_llegada,
      hora_llegada:            data.hora_llegada,
      descripcion_actividades: data.descripcion_actividades,
      tiene_viaticos:          data.tiene_viaticos  ?? true,
      tiene_movilizaciones:    data.tiene_movilizaciones ?? false,
      tiene_anticipo:          data.tiene_anticipo  ?? false,
      transportes: (data.transportes ?? []).map(t => ({
        tipo:          t.tipo          || '',
        nombre:        t.nombre        || '',
        ruta:          t.ruta          || '',
        salida_fecha:  t.salida_fecha  || '',
        salida_hora:   t.salida_hora   || '',
        llegada_fecha: t.llegada_fecha || '',
        llegada_hora:  t.llegada_hora  || '',
      })),
    }

    // Pre-seleccionar provincia/ciudad si INTERIOR
    if (data.tipo === 'INTERIOR' && data.destino && provincias.value.length) {
      const parts = data.destino.split(' - ')
      if (parts.length >= 2) {
        const prov = provincias.value.find(p => p.nombre === parts[0])
        if (prov) {
          form_provinciaId.value = prov.id
          const ciu = prov.ciudades?.find(c => c.nombre === parts[1])
          if (ciu) form_ciudadId.value = ciu.id
        }
      }
    }

    // Documentos
    documentos.value = data.documentos ?? []

    // Informe
    informeActual.value = data.informe || null
    if (data.informe) {
      informeForm.value = {
        fecha_informe: data.informe.fecha_informe || new Date().toISOString().slice(0, 10),
        actividades:   data.informe.actividades   || '',
        productos:     data.informe.productos      || '',
        fecha_salida:  data.informe.fecha_salida   || data.fecha_salida,
        hora_salida:   data.informe.hora_salida    || data.hora_salida,
        fecha_llegada: data.informe.fecha_llegada  || data.fecha_llegada,
        hora_llegada:  data.informe.hora_llegada   || data.hora_llegada,
      }
    } else {
      informeForm.value = {
        fecha_informe: new Date().toISOString().slice(0, 10),
        actividades:   '',
        productos:     '',
        fecha_salida:  data.fecha_salida,
        hora_salida:   data.hora_salida,
        fecha_llegada: data.fecha_llegada,
        hora_llegada:  data.hora_llegada,
      }
    }

    // Paso activo
    stepperPasoVisible.value = pasoActivoPorEstado.value
  } catch (e) {
    alert('Error al cargar la solicitud.')
    modalStepper.value = false
  } finally {
    stepperCargando.value = false
  }
}

function cerrarStepper() {
  modalStepper.value = false
  stepperData.value  = null
  cargar()
}

function navegarPaso(paso) {
  if (paso.estado === 'pendiente') return
  stepperPasoVisible.value = paso.id
}

// Al cambiar a tab Documentos y hay un ID, recargar docs
watch(formTab, (tab) => {
  errorForm.value = ''
  if (tab === 'Documentos' && modoEdicionId.value) {
    cargarDocumentos(modoEdicionId.value)
  }
})

// ── Provincia / Ciudad ────────────────────────────────────────
function onTipoCambio() {
  form.value.destino   = ''
  form_provinciaId.value = null
  form_ciudadId.value    = null
}

function onProvinciaChange() {
  form_ciudadId.value = null
  form.value.destino  = ''
}

function onCiudadChange() {
  const prov = provincias.value.find(p => p.id === form_provinciaId.value)
  const ciu  = prov?.ciudades?.find(c => c.id === form_ciudadId.value)
  if (prov && ciu) form.value.destino = `${prov.nombre} - ${ciu.nombre}`
}

// ── Guardar Solicitud ─────────────────────────────────────────
function validarFormulario() {
  if (!form.value.destino) return 'Seleccione el destino.'
  if (!form.value.fecha_salida) return 'Ingrese la fecha de salida.'
  if (!form.value.hora_salida) return 'Ingrese la hora de salida.'
  if (!form.value.fecha_llegada) return 'Ingrese la fecha de llegada.'
  if (!form.value.hora_llegada) return 'Ingrese la hora de llegada.'
  if (!form.value.descripcion_actividades?.trim()) return 'Describa las actividades.'
  return null
}

async function guardarSolicitud() {
  errorForm.value = ''
  const error = validarFormulario()
  if (error) { errorForm.value = error; return }
  guardando.value = true
  try {
    if (modoEdicionId.value) {
      const { data } = await api.put(`/comisiones/solicitudes/${modoEdicionId.value}`, form.value)
      stepperData.value = data
    } else {
      const { data } = await api.post('/comisiones/solicitudes', form.value)
      modoEdicionId.value = data.id
      // Cargar datos completos
      const { data: detalle } = await api.get(`/comisiones/solicitudes/${data.id}`)
      stepperData.value = detalle
      documentos.value  = detalle.documentos ?? []
    }
  } catch (e) {
    errorForm.value = e.response?.data?.message || 'Error al guardar.'
  } finally {
    guardando.value = false
  }
}

// ── Documentos ────────────────────────────────────────────────
async function cargarDocumentos(solicitudId) {
  try {
    const { data } = await api.get(`/comisiones/solicitudes/${solicitudId}`)
    documentos.value  = data.documentos ?? []
    stepperData.value = data
  } catch {
    documentos.value = []
  }
}

async function subirDocumento(event, tipo) {
  const archivo = event.target.files?.[0]
  if (!archivo) return
  subiendoDoc.value = tipo
  const fd = new FormData()
  fd.append('archivo', archivo)
  fd.append('tipo_doc', tipo)
  try {
    await api.post(`/comisiones/solicitudes/${modoEdicionId.value}/documentos`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    await cargarDocumentos(modoEdicionId.value)
  } catch (e) {
    alert(e.response?.data?.message || 'Error al subir documento.')
  } finally {
    subiendoDoc.value  = ''
    event.target.value = ''
  }
}

async function eliminarDocumento(doc) {
  if (!confirm(`¿Eliminar documento ${doc.tipo_doc}?`)) return
  try {
    await api.delete(`/comisiones/solicitudes/${modoEdicionId.value}/documentos/${doc.id}`)
    await cargarDocumentos(modoEdicionId.value)
  } catch (e) {
    alert(e.response?.data?.message || 'Error al eliminar.')
  }
}

async function descargarDocumento(doc) {
  try {
    const resp = await api.get(`/comisiones/solicitudes/${modoEdicionId.value}/documentos/${doc.id}/descargar`, { responseType: 'blob' })
    const url  = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
    window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch { alert('Error al descargar.') }
}

async function generarPdfSolicitud() {
  try {
    const resp = await api.get(`/comisiones/solicitudes/${modoEdicionId.value}/pdf`, { responseType: 'blob' })
    const url  = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
    window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch { alert('Error al generar PDF.') }
}

async function procesarDesdeCard(sol) {
  if (procesando.value) return
  if (!confirm(`¿Confirma procesar la solicitud?\n\nYa no podrá editar los datos ni reemplazar documentos.`)) return
  procesando.value = true
  try {
    await api.patch(`/comisiones/solicitudes/${sol.id}/procesar`)
    await cargarDocumentos(sol.id)
  } catch (e) {
    alert(e.response?.data?.message || 'Error al procesar la solicitud.')
  } finally {
    procesando.value = false
  }
}

async function subirPdfFirmado(event) {
  const archivo = event.target.files?.[0]
  if (!archivo) return
  subiendoFirmado.value = true
  const fd = new FormData()
  fd.append('archivo', archivo)
  try {
    await api.post(`/comisiones/solicitudes/${modoEdicionId.value}/subir-firmado`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    await cargarDocumentos(modoEdicionId.value)
    // Actualizar paso visible al Informe
    stepperPasoVisible.value = 'informe'
  } catch (e) {
    alert(e.response?.data?.message || 'Error al subir PDF firmado.')
  } finally {
    subiendoFirmado.value = false
    event.target.value    = ''
  }
}

// ── PDF solicitud ─────────────────────────────────────────────
async function descargarPdf(sol) {
  try {
    const resp = await api.get(`/comisiones/solicitudes/${sol.id}/pdf`, { responseType: 'blob' })
    const url  = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
    window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch { alert('Error al generar PDF.') }
}

// ── Informe ───────────────────────────────────────────────────
async function guardarInforme() {
  if (!informeForm.value.actividades?.trim()) {
    errorAccion.value = 'Las actividades son obligatorias.'
    return
  }
  guardando.value   = true
  errorAccion.value = ''
  try {
    let resp
    if (informeActual.value) {
      resp = await api.put(`/comisiones/informes/${informeActual.value.id}`, informeForm.value)
    } else {
      resp = await api.post(`/comisiones/solicitudes/${modoEdicionId.value}/informe`, informeForm.value)
    }
    // Actualizar stepperData con el informe guardado
    const { data } = await api.get(`/comisiones/solicitudes/${modoEdicionId.value}`)
    stepperData.value   = data
    informeActual.value = data.informe || resp.data
  } catch (e) {
    errorAccion.value = e.response?.data?.message || 'Error al guardar informe.'
  } finally {
    guardando.value = false
  }
}

async function descargarPdfInforme() {
  try {
    const resp = await api.get(`/comisiones/solicitudes/${modoEdicionId.value}/informe/pdf`, { responseType: 'blob' })
    const url  = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
    window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch { alert('Error al generar PDF del informe.') }
}

async function subirInformeFirmado(event) {
  const archivo = event.target.files?.[0]
  if (!archivo) return
  subiendoInformeFirmado.value = true
  errorAccion.value            = ''
  const fd = new FormData()
  fd.append('archivo', archivo)
  try {
    await api.post(`/comisiones/solicitudes/${modoEdicionId.value}/informe/subir-firmado`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    // Recargar datos completos
    const { data } = await api.get(`/comisiones/solicitudes/${modoEdicionId.value}`)
    stepperData.value   = data
    informeActual.value = data.informe
    stepperPasoVisible.value = 'pago'
  } catch (e) {
    errorAccion.value = e.response?.data?.message || 'Error al subir informe firmado.'
  } finally {
    subiendoInformeFirmado.value = false
    event.target.value           = ''
  }
}

async function descargarInformeFirmado() {
  try {
    const resp = await api.get(`/comisiones/solicitudes/${modoEdicionId.value}/informe/descargar-firmado`, { responseType: 'blob' })
    const url  = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
    window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch { alert('Error al descargar.') }
}

// ── Solicitar Pago ────────────────────────────────────────────
async function solicitarPago(sol) {
  if (!confirm('¿Solicitar el trámite de pago de viáticos?')) return
  try {
    await api.patch(`/comisiones/solicitudes/${sol.id}/solicitar-pago`)
    const { data } = await api.get(`/comisiones/solicitudes/${sol.id}`)
    stepperData.value        = data
    stepperPasoVisible.value = 'liquidacion'
    await cargar()
  } catch (e) {
    alert(e.response?.data?.message || 'Error.')
  }
}

// ── Devolver ──────────────────────────────────────────────────
function abrirModalDevolver() {
  observacionDevolucion.value = ''
  errorDevolucion.value       = ''
  modalDevolver.value         = true
}

async function confirmarDevolucion() {
  if (!observacionDevolucion.value.trim()) {
    errorDevolucion.value = 'Ingrese el motivo de la devolución.'
    return
  }
  devolviendo.value = true
  try {
    await api.patch(`/comisiones/solicitudes/${stepperData.value.id}/devolver`, {
      observacion: observacionDevolucion.value,
    })
    modalDevolver.value = false
    // Recargar datos del stepper
    const { data } = await api.get(`/comisiones/solicitudes/${stepperData.value.id}`)
    stepperData.value        = data
    stepperPasoVisible.value = 'solicitud'
    await cargar()
  } catch (e) {
    errorDevolucion.value = e.response?.data?.message || 'Error al devolver.'
  } finally {
    devolviendo.value = false
  }
}
</script>
