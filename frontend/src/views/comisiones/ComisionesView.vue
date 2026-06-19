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
      <button v-for="tab in tabsVisibles" :key="tab.key"
        @click="tabActivo = tab.key"
        :class="['px-4 py-2.5 text-sm font-medium transition border-b-2 -mb-px', tabActivo === tab.key
          ? 'border-[#5c4a6e] text-[#5c4a6e]'
          : 'border-transparent text-gray-500 hover:text-gray-700']">
        {{ tab.label }}
      </button>
    </div>

    <div v-if="cargando" class="flex items-center justify-center py-16">
      <div class="w-8 h-8 border-2 border-[#5c4a6e] border-t-transparent rounded-full animate-spin"></div>
    </div>

    <template v-else>
      <!-- ═══ TAB: Mis Comisiones ═══ -->
      <div v-if="tabActivo === 'mis'">
        <div v-if="errorCarga" class="mx-4 mt-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">
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
            class="bg-white rounded-lg shadow-sm border border-gray-100 p-4">
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
                </div>
                <p class="text-sm text-gray-600 mt-1 truncate">{{ sol.destino }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ formatFecha(sol.fecha_salida) }} → {{ formatFecha(sol.fecha_llegada) }}</p>
              </div>
              <div class="flex items-center gap-2 flex-shrink-0">
                <button @click="verDetalle(sol)" title="Ver detalle"
                  class="p-1.5 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                  </svg>
                </button>
                <!-- BORRADOR -->
                <template v-if="sol.estado === 'BORRADOR'">
                  <!-- Progreso de documentos -->
                  <span class="text-xs font-medium"
                    :class="sol.docs_count >= 3 ? 'text-green-600' : 'text-orange-500'">
                    {{ sol.docs_count }}/3 docs
                  </span>
                  <!-- Editar datos -->
                  <button @click="editarSolicitud(sol)" title="Editar datos"
                    class="p-1.5 rounded-md text-gray-400 hover:text-[#5c4a6e] hover:bg-purple-50 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                  </button>
                  <!-- Documentos -->
                  <button @click="abrirDocumentos(sol)" title="Gestionar documentos"
                    class="px-2.5 py-1.5 text-xs font-semibold border rounded-md transition"
                    style="border-color:#5c4a6e; color:#5c4a6e;">
                    Documentos
                  </button>
                  <!-- PROCESAR (solo cuando 3 docs listos) -->
                  <button v-if="sol.docs_count >= 3" @click="procesarDesdeCard(sol)"
                    class="px-2.5 py-1.5 text-xs font-semibold text-white rounded-md transition hover:opacity-90"
                    style="background-color:#5c4a6e;">
                    Procesar
                  </button>
                </template>
                <!-- PROCESADO -->
                <template v-if="sol.estado === 'PROCESADO'">
                  <button @click="abrirDocumentos(sol)"
                    class="px-3 py-1.5 text-xs font-semibold text-white rounded-md transition hover:opacity-90"
                    style="background-color:#5c4a6e;">
                    Subir PDF Firmado
                  </button>
                </template>
                <!-- APROBADO -->
                <template v-if="sol.estado === 'APROBADO'">
                  <button @click="descargarPdf(sol)" title="PDF solicitud"
                    class="p-1.5 rounded-md text-red-400 hover:text-red-600 hover:bg-red-50 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                  </button>
                  <button @click="abrirInforme(sol)"
                    class="px-3 py-1.5 text-xs font-semibold bg-green-600 text-white rounded-md transition hover:bg-green-700">
                    Informe
                  </button>
                </template>
                <!-- INFORME_APROBADO -->
                <template v-if="sol.estado === 'INFORME_APROBADO'">
                  <button @click="descargarPdf(sol)" title="PDF solicitud"
                    class="p-1.5 rounded-md text-red-400 hover:text-red-600 hover:bg-red-50 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                  </button>
                  <button @click="solicitarPago(sol)"
                    class="px-3 py-1.5 text-xs font-semibold bg-blue-600 text-white rounded-md transition hover:bg-blue-700">
                    Solicitar Pago
                  </button>
                </template>
                <!-- Otros estados con número -->
                <template v-if="sol.numero_solicitud && !['BORRADOR','APROBADO','INFORME_APROBADO'].includes(sol.estado)">
                  <button @click="descargarPdf(sol)" title="PDF solicitud"
                    class="p-1.5 rounded-md text-red-400 hover:text-red-600 hover:bg-red-50 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                  </button>
                </template>
              </div>
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
                <th class="px-4 py-3 text-left font-semibold"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="sol in solicitudesFiltradas" :key="sol.id"
                class="border-b border-gray-50 hover:bg-purple-50/30 transition">
                <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ sol.numero_solicitud || '—' }}</td>
                <td class="px-4 py-3">
                  <p class="font-medium text-gray-800">{{ sol.empleado?.apellido_emp }} {{ sol.empleado?.nombre_emp }}</p>
                  <p class="text-xs text-gray-400">{{ sol.unidad_nombre }}</p>
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
                <td class="px-4 py-3">
                  <button @click="verDetalle(sol)"
                    class="p-1.5 rounded text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>

  <!-- ══════════════════ MODAL NUEVA / EDITAR SOLICITUD ══════════════════ -->
  <div v-if="modalSolicitud" class="fixed inset-0 z-50 flex items-start justify-center pt-8 pb-4 px-4">
    <div class="fixed inset-0 bg-black/40" @click="cerrarModalSolicitud"/>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col z-10">
      <div class="flex items-center justify-between px-6 py-4 text-white" style="background-color:#5c4a6e">
        <h2 class="text-base font-bold">{{ modoEdicion ? 'Editar Comisión' : 'Nueva Comisión' }}</h2>
        <button @click="cerrarModalSolicitud" class="text-white/70 hover:text-white transition">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <div class="overflow-y-auto flex-1 p-5 space-y-4">
        <!-- Error visible al tope -->
        <div v-if="errorForm && formTab !== 'Documentos'"
          class="px-3 py-2 bg-red-50 border border-red-200 rounded-md text-red-700 text-xs font-medium">
          {{ errorForm }}
        </div>
        <!-- Tabs -->
        <div class="flex gap-0 border rounded-lg overflow-hidden mb-4">
          <button v-for="t in formTabs" :key="t"
            @click="formTab = t"
            :class="['flex-1 py-2 text-xs font-semibold transition', formTab === t
              ? 'text-white' : 'bg-gray-50 text-gray-500 hover:bg-gray-100']"
            :style="formTab === t ? 'background-color:#5c4a6e;' : ''">
            {{ t }}
          </button>
        </div>

        <!-- Tab: Datos Generales -->
        <div v-if="formTab === 'Datos Generales'" class="space-y-3">
          <!-- Fecha de solicitud (solo lectura, visible en modo edición) -->
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
            <!-- Destino INTERIOR: selects cascada -->
            <div v-if="form.tipo === 'INTERIOR'">
              <label class="text-xs font-semibold text-gray-600 mb-1 block">Destino *</label>
              <div class="grid grid-cols-2 gap-1">
                <select v-model="form_provinciaId" @change="onProvinciaChange"
                  class="text-sm border border-gray-300 rounded-md px-2 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]">
                  <option :value="null">Provincia...</option>
                  <option v-for="p in provincias" :key="p.id" :value="p.id">{{ p.nombre }}</option>
                </select>
                <select v-model="form_ciudadId" :disabled="!form_provinciaId"
                  @change="onCiudadChange"
                  class="text-sm border border-gray-300 rounded-md px-2 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e] disabled:bg-gray-50 disabled:text-gray-400">
                  <option :value="null">Ciudad...</option>
                  <option v-for="c in ciudadesDisponibles" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                </select>
              </div>
            </div>
            <!-- Destino EXTERIOR: texto libre -->
            <div v-else>
              <label class="text-xs font-semibold text-gray-600 mb-1 block">Destino *</label>
              <input v-model="form.destino" type="text" placeholder="País / Ciudad"
                class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]" style="text-transform:uppercase"/>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs font-semibold text-gray-600 mb-1 block">Fecha de Salida *</label>
              <input v-model="form.fecha_salida" type="date"
                class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]"/>
            </div>
            <div>
              <label class="text-xs font-semibold text-gray-600 mb-1 block">Hora de Salida *</label>
              <input v-model="form.hora_salida" type="time"
                class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]"/>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs font-semibold text-gray-600 mb-1 block">Fecha de Llegada *</label>
              <input v-model="form.fecha_llegada" type="date"
                class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]"/>
            </div>
            <div>
              <label class="text-xs font-semibold text-gray-600 mb-1 block">Hora de Llegada *</label>
              <input v-model="form.hora_llegada" type="time"
                class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]"/>
            </div>
          </div>
          <div>
            <label class="text-xs font-semibold text-gray-600 mb-1 block">Descripción de Actividades *</label>
            <textarea v-model="form.descripcion_actividades" rows="3" placeholder="Describa las actividades a realizar..."
              class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e] resize-none" style="text-transform:uppercase"/>
          </div>
          <div class="flex gap-4">
            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
              <input type="checkbox" v-model="form.tiene_viaticos" class="rounded accent-[#5c4a6e]"/>
              Viáticos
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
              <input type="checkbox" v-model="form.tiene_movilizaciones" class="rounded accent-[#5c4a6e]"/>
              Movilizaciones
            </label>
            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
              <input type="checkbox" v-model="form.tiene_anticipo" class="rounded accent-[#5c4a6e]"/>
              Anticipo
            </label>
          </div>
        </div>

        <!-- Tab: Servidores (lectura) -->
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
          <div v-for="(trn, i) in form.transportes" :key="i"
            class="border border-gray-100 rounded-lg p-3 space-y-2">
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
          <!-- Spinner mientras se auto-guarda -->
          <div v-if="guardando" class="flex flex-col items-center py-8 gap-3 text-gray-500">
            <div class="w-7 h-7 border-2 border-[#5c4a6e] border-t-transparent rounded-full animate-spin"></div>
            <p class="text-sm">Guardando formulario...</p>
          </div>

          <template v-if="!guardando && modoEdicionId">
          <p class="text-xs text-gray-600 font-medium">Adjunte los 3 documentos requeridos, luego genere y firme el PDF para aprobar la solicitud.</p>

          <div v-for="slot in slotsDocumento" :key="slot.tipo"
            class="flex items-center justify-between p-3 rounded-lg border transition"
            :class="docPorTipo(slot.tipo) ? 'border-green-300 bg-green-50' : 'border-gray-200 bg-white'">
            <div class="flex items-center gap-2 text-sm">
              <span v-if="docPorTipo(slot.tipo)" class="text-green-600 text-base font-bold leading-none">✓</span>
              <span v-else class="w-5 h-5 rounded-full border-2 border-gray-300 flex items-center justify-center text-xs text-gray-400 flex-shrink-0">!</span>
              <span :class="docPorTipo(slot.tipo) ? 'text-green-800 font-medium' : 'text-gray-700'">{{ slot.label }}</span>
            </div>
            <div class="flex gap-2 items-center">
              <button v-if="docPorTipo(slot.tipo)" @click="descargarDocumento(docPorTipo(slot.tipo))"
                class="text-xs text-blue-600 hover:underline">Ver</button>
              <button v-if="docPorTipo(slot.tipo)" @click="eliminarDocumento(docPorTipo(slot.tipo))"
                class="text-xs text-red-500 hover:underline">Eliminar</button>
              <label v-if="!docPorTipo(slot.tipo)" class="cursor-pointer">
                <input type="file" accept=".pdf" class="hidden" @change="subirDocumento($event, slot.tipo)"/>
                <span class="px-3 py-1 text-xs font-semibold text-white rounded"
                  :style="subiendoDoc === slot.tipo ? 'background-color:#9d8aae' : 'background-color:#5c4a6e'">
                  {{ subiendoDoc === slot.tipo ? 'Subiendo...' : 'Subir PDF' }}
                </span>
              </label>
            </div>
          </div>

          <!-- Info BORRADOR: cerrar modal y usar botón Procesar de la tarjeta -->
          <div v-if="modoEdicionEstado === 'BORRADOR'" class="border-t border-gray-200 pt-3">
            <p class="text-xs text-center text-gray-400">
              {{ todosDocSubidos ? '✓ Los 3 documentos están listos. Cierre este panel y presione "Procesar" en la tarjeta.' : `Suba los ${3 - docsSubidos} documento(s) restante(s).` }}
            </p>
          </div>

          <!-- Estado PROCESADO: generar PDF y subir firmado -->
          <div v-if="modoEdicionEstado === 'PROCESADO'" class="border-t border-gray-200 pt-3 space-y-3">
            <div class="p-3 bg-blue-50 rounded-lg border border-blue-200 text-blue-800 text-xs font-medium">
              ✓ Solicitud procesada — Genere el PDF, obtenga las firmas y suba el documento firmado.
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
          </div>

          <!-- Estado APROBADO -->
          <div v-if="modoEdicionEstado === 'APROBADO'"
            class="flex items-center gap-2 text-green-700 text-sm font-medium p-3 bg-green-50 rounded-lg border border-green-200">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            Solicitud APROBADA — en espera del regreso para el informe
          </div>
          </template>

          <!-- Mensaje cuando aún no se ha guardado el formulario -->
          <div v-if="!modoEdicionId && !guardando"
            class="p-6 bg-gray-50 rounded-lg border border-gray-200 text-center space-y-3">
            <svg class="w-10 h-10 mx-auto text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="text-sm text-gray-500">Complete el formulario en <strong>Datos Generales</strong> y presione <strong>Guardar</strong> para habilitar la carga de documentos.</p>
            <button @click="formTab = 'Datos Generales'"
              class="text-sm font-semibold underline" style="color:#5c4a6e;">
              Ir a Datos Generales →
            </button>
          </div>
        </div>

      </div>

      <!-- Footer -->
      <div class="p-5 border-t border-gray-100 flex justify-between items-center">
        <button @click="cerrarModalSolicitud" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">
          Cerrar
        </button>
        <div class="flex items-center gap-3">
          <!-- Progreso de docs en Tab Documentos -->
          <span v-if="formTab === 'Documentos' && modoEdicionId && !todosDocSubidos"
            class="text-xs text-orange-600 font-medium">
            {{ docsSubidos }}/3 documentos subidos
          </span>
          <!-- Botón Guardar: solo en Tabs 1-3 mientras esté en BORRADOR -->
          <button v-if="formTab !== 'Documentos' && modoEdicionEstado !== 'PROCESADO' && modoEdicionEstado !== 'APROBADO'"
            @click="guardarSolicitud" :disabled="guardando"
            class="px-5 py-2 text-sm font-semibold text-white rounded-lg transition hover:opacity-90 disabled:opacity-50"
            style="background-color:#5c4a6e;">
            {{ guardando ? 'Guardando...' : (modoEdicion ? 'Actualizar' : 'Guardar') }}
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════════════════ MODAL INFORME ══════════════════ -->
  <div v-if="modalInforme" class="fixed inset-0 z-50 flex items-start justify-center pt-8 pb-4 px-4">
    <div class="fixed inset-0 bg-black/40" @click="modalInforme = false"/>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col z-10">
      <div class="flex items-center justify-between px-6 py-4 text-white" style="background-color:#5c4a6e">
        <h2 class="text-base font-bold">Informe de Cumplimiento</h2>
        <button @click="modalInforme = false" class="text-white/70 hover:text-white">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
      <div class="overflow-y-auto flex-1 p-5 space-y-4">

        <!-- Informe APROBADO: solo descarga -->
        <div v-if="informeActual?.estado === 'APROBADO'"
          class="flex flex-col items-center py-8 text-center gap-4">
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
            class="px-5 py-2 text-sm font-semibold text-white rounded-lg hover:opacity-90"
            style="background-color:#5c4a6e;">
            Descargar PDF Firmado
          </button>
        </div>

        <!-- Formulario del informe -->
        <template v-else>
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

          <!-- Acciones post-guardado (cuando el informe existe en estado PRESENTADO) -->
          <div v-if="informeActual?.estado === 'PRESENTADO'" class="border-t border-gray-200 pt-3 space-y-2">
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
        </template>

        <p v-if="errorAccion" class="text-red-600 text-xs">{{ errorAccion }}</p>
      </div>

      <div v-if="informeActual?.estado !== 'APROBADO'" class="p-5 border-t border-gray-100 flex justify-end gap-3">
        <button @click="modalInforme = false" class="px-4 py-2 text-sm text-gray-600">Cancelar</button>
        <button @click="guardarInforme" :disabled="guardando"
          class="px-5 py-2 text-sm font-semibold text-white rounded-lg hover:opacity-90 disabled:opacity-50"
          style="background-color:#5c4a6e;">
          {{ guardando ? 'Guardando...' : (informeActual ? 'Actualizar Informe' : 'Guardar Informe') }}
        </button>
      </div>
      <div v-else class="p-5 border-t border-gray-100 flex justify-end">
        <button @click="modalInforme = false" class="px-4 py-2 text-sm text-gray-600">Cerrar</button>
      </div>
    </div>
  </div>

  <!-- ══════════════════ MODAL DETALLE ══════════════════ -->
  <div v-if="modalDetalle && detalleActual" class="fixed inset-0 z-50 flex items-start justify-center pt-8 pb-4 px-4">
    <div class="fixed inset-0 bg-black/40" @click="modalDetalle = false"/>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col z-10">
      <div class="flex items-center justify-between p-5 border-b border-gray-100">
        <div>
          <h2 class="text-lg font-bold text-gray-800">{{ detalleActual.numero_solicitud || 'Comisión sin número' }}</h2>
          <div class="flex gap-2 mt-1">
            <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full', badgeEstado(detalleActual.estado).class]">
              {{ badgeEstado(detalleActual.estado).label }}
            </span>
            <span class="text-xs px-2 py-0.5 rounded-full font-medium"
              :class="detalleActual.tipo === 'EXTERIOR' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'">
              {{ detalleActual.tipo }}
            </span>
          </div>
        </div>
        <button @click="modalDetalle = false" class="text-gray-400 hover:text-gray-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
      <div class="overflow-y-auto flex-1 p-5 space-y-4 text-sm">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <p class="text-xs text-gray-400 font-semibold mb-0.5">EMPLEADO</p>
            <p class="font-medium">{{ detalleActual.empleado?.apellido_emp }} {{ detalleActual.empleado?.nombre_emp }}</p>
            <p class="text-xs text-gray-500">{{ detalleActual.unidad_nombre }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-400 font-semibold mb-0.5">DESTINO</p>
            <p>{{ detalleActual.destino }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-400 font-semibold mb-0.5">FECHAS</p>
            <p>{{ formatFecha(detalleActual.fecha_salida) }} {{ detalleActual.hora_salida }}</p>
            <p>→ {{ formatFecha(detalleActual.fecha_llegada) }} {{ detalleActual.hora_llegada }}</p>
          </div>
          <div>
            <p class="text-xs text-gray-400 font-semibold mb-0.5">BENEFICIOS</p>
            <div class="flex gap-2 flex-wrap">
              <span v-if="detalleActual.tiene_viaticos" class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded">Viáticos</span>
              <span v-if="detalleActual.tiene_movilizaciones" class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded">Movilizaciones</span>
              <span v-if="detalleActual.tiene_anticipo" class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded">Anticipo</span>
            </div>
          </div>
        </div>
        <div>
          <p class="text-xs text-gray-400 font-semibold mb-0.5">ACTIVIDADES</p>
          <p class="text-gray-700 whitespace-pre-line">{{ detalleActual.descripcion_actividades }}</p>
        </div>
        <div v-if="detalleActual.servidores?.length">
          <p class="text-xs text-gray-400 font-semibold mb-1">SERVIDORES COMISIONADOS</p>
          <div v-for="srv in detalleActual.servidores" :key="srv.id"
            class="flex gap-3 text-xs text-gray-600 py-1 border-b border-gray-50">
            <span class="font-mono text-gray-400 w-24">{{ srv.id_emp }}</span>
            <span class="flex-1">{{ srv.unidad }}</span>
            <span class="flex-1">{{ srv.puesto }}</span>
          </div>
        </div>
        <div v-if="detalleActual.transportes?.length">
          <p class="text-xs text-gray-400 font-semibold mb-1">ITINERARIO DE TRANSPORTE</p>
          <div v-for="trn in detalleActual.transportes" :key="trn.id"
            class="text-xs text-gray-600 py-1.5 border-b border-gray-50">
            <span class="font-semibold">{{ trn.tipo }}</span> — {{ trn.nombre }} — {{ trn.ruta }}
            <span class="text-gray-400 ml-2">{{ trn.salida_fecha }} {{ trn.salida_hora }} → {{ trn.llegada_fecha }} {{ trn.llegada_hora }}</span>
          </div>
        </div>
        <div v-if="detalleActual.documentos?.length">
          <p class="text-xs text-gray-400 font-semibold mb-1">DOCUMENTOS ADJUNTOS</p>
          <div v-for="doc in detalleActual.documentos" :key="doc.id"
            class="flex items-center gap-2 text-xs text-gray-600 py-1 border-b border-gray-50">
            <span class="font-medium w-28 flex-shrink-0" style="color:#5c4a6e">{{ doc.tipo_doc }}</span>
            <span class="flex-1 truncate text-gray-500">{{ doc.nombre_archivo }}</span>
          </div>
        </div>
      </div>
      <div class="p-5 border-t border-gray-100 flex justify-between items-center">
        <button v-if="detalleActual.numero_solicitud" @click="descargarPdf(detalleActual)"
          class="flex items-center gap-1.5 text-sm text-red-600 hover:text-red-700 font-medium transition">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
          </svg>
          PDF Solicitud
        </button>
        <div v-else></div>
        <button @click="modalDetalle = false" class="px-4 py-2 text-sm text-gray-600">Cerrar</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const auth = useAuthStore()

// ── State ──────────────────────────────────────────────────
const cargando   = ref(true)
const errorCarga = ref('')
const solicitudes = ref([])
const miRol     = ref({})
const tabActivo = ref('mis')

const modalSolicitud    = ref(false)
const modoEdicion       = ref(false)
const modoEdicionId     = ref(null)
const modoEdicionEstado = ref('')
const guardando         = ref(false)
const errorForm         = ref('')
const formTab           = ref('Datos Generales')

const modalInforme           = ref(false)
const informeTarget          = ref(null)
const informeActual          = ref(null)
const subiendoInformeFirmado = ref(false)

const modalDetalle  = ref(false)
const detalleActual = ref(null)

const filtroEstado   = ref('')
const filtroTipo     = ref('')
const filtroBusqueda = ref('')
const errorAccion    = ref('')

// Province/city
const provincias      = ref([])
const form_provinciaId = ref(null)
const form_ciudadId    = ref(null)

// Documents
const documentos      = ref([])
const subiendoDoc     = ref('')
const subiendoFirmado = ref(false)
const procesando      = ref(false)

const slotsDocumento = [
  { tipo: 'AUTORIZACION',  label: 'Solicitud de Autorización y Aprobación' },
  { tipo: 'PASAJES',       label: 'Pasajes Aéreos / Terrestres' },
  { tipo: 'CERTIFICACION', label: 'Certificación Presupuestaria' },
]

const formVacio = () => ({
  tipo: 'INTERIOR',
  destino: '',
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
  fecha_informe: new Date().toISOString().slice(0,10),
  actividades: '',
  productos: '',
  fecha_salida: '',
  hora_salida: '',
  fecha_llegada: '',
  hora_llegada: '',
})

// ── Computed ───────────────────────────────────────────────
const idEmp = computed(() => auth.empleado?.id_emp)

const solicitudesMias = computed(() =>
  solicitudes.value.filter(s => s.id_emp?.trim() === idEmp.value?.trim())
)

const solicitudesFiltradas = computed(() => {
  let res = solicitudes.value
  if (filtroEstado.value) res = res.filter(s => s.estado === filtroEstado.value)
  if (filtroTipo.value) res = res.filter(s => s.tipo === filtroTipo.value)
  if (filtroBusqueda.value) {
    const q = filtroBusqueda.value.toLowerCase()
    res = res.filter(s =>
      (s.destino || '').toLowerCase().includes(q) ||
      (s.empleado?.apellido_emp || '').toLowerCase().includes(q) ||
      (s.empleado?.nombre_emp || '').toLowerCase().includes(q)
    )
  }
  return res
})

const tabsVisibles = computed(() => {
  const tabs = [{ key: 'mis', label: 'Mis Comisiones' }]
  if (miRol.value.es_admin || miRol.value.es_maxima_autoridad) {
    tabs.push({ key: 'todas', label: 'Todas las Comisiones' })
  }
  return tabs
})

const estadoOpciones = [
  { value: 'BORRADOR',         label: 'Borrador' },
  { value: 'APROBADO',         label: 'Aprobado' },
  { value: 'INFORME_APROBADO', label: 'Informe Aprobado' },
  { value: 'EN_PAGO',          label: 'En Pago' },
  { value: 'EN_LIQUIDACION',   label: 'En Liquidación' },
  { value: 'CERRADO',          label: 'Cerrado' },
  { value: 'POR_COBRAR',       label: 'Por Cobrar' },
  { value: 'NEGADO',           label: 'Negado' },
]

const formTabs = ['Datos Generales', 'Servidores', 'Transporte', 'Documentos']

const ciudadesDisponibles = computed(() =>
  provincias.value.find(p => p.id === form_provinciaId.value)?.ciudades ?? []
)

function docPorTipo(tipo) {
  return documentos.value.find(d => d.tipo_doc === tipo)
}

const docsSubidos = computed(() =>
  slotsDocumento.filter(s => documentos.value.some(d => d.tipo_doc === s.tipo)).length
)

const todosDocSubidos = computed(() => docsSubidos.value === slotsDocumento.length)

const docFirmadoSubido = computed(() =>
  documentos.value.some(d => d.tipo_doc === 'FIRMADO')
)

// ── Helpers ────────────────────────────────────────────────
function badgeEstado(estado) {
  const mapa = {
    BORRADOR:         { class: 'bg-gray-100 text-gray-600',    label: 'Borrador' },
    APROBADO:         { class: 'bg-green-100 text-green-700',   label: 'Aprobado' },
    INFORME_APROBADO: { class: 'bg-green-100 text-green-700',   label: 'Informe Aprobado' },
    EN_PAGO:          { class: 'bg-blue-100 text-blue-700',     label: 'En Pago' },
    EN_LIQUIDACION:   { class: 'bg-cyan-100 text-cyan-700',     label: 'En Liquidación' },
    POR_COBRAR:       { class: 'bg-orange-100 text-orange-700', label: 'Por Cobrar' },
    CERRADO:          { class: 'bg-emerald-100 text-emerald-800', label: 'Cerrado' },
    NEGADO:           { class: 'bg-red-100 text-red-700',       label: 'Negado' },
  }
  return mapa[estado] || { class: 'bg-gray-100 text-gray-600', label: estado }
}

function formatFecha(f) {
  if (!f) return '—'
  const [y, m, d] = f.split('-')
  return `${d}/${m}/${y}`
}

// ── Load ───────────────────────────────────────────────────
async function cargar() {
  cargando.value  = true
  errorCarga.value = ''
  try {
    const [rolResp, solResp] = await Promise.all([
      api.get('/comisiones/mi-rol'),
      api.get('/comisiones/solicitudes'),
    ])
    miRol.value       = rolResp.data
    solicitudes.value = solResp.data.data || solResp.data
  } catch (e) {
    console.error('Error cargando solicitudes:', e)
    errorCarga.value = e.response?.data?.message || `Error ${e.response?.status || ''}: no se pudieron cargar las solicitudes.`
  } finally {
    cargando.value = false
  }
  // Provincias se carga por separado para que un error en solicitudes no la bloquee
  try {
    const { data } = await api.get('/comisiones/provincias')
    provincias.value = data
  } catch (e) {
    console.error('Error cargando provincias:', e)
  }
}

onMounted(cargar)

// ── Modal Solicitud ────────────────────────────────────────
function abrirNueva() {
  form.value           = formVacio()
  modoEdicion.value    = false
  modoEdicionId.value  = null
  modoEdicionEstado.value = ''
  formTab.value        = 'Datos Generales'
  form_provinciaId.value = null
  form_ciudadId.value    = null
  documentos.value     = []
  errorForm.value      = ''
  modalSolicitud.value = true
}

function editarSolicitud(sol) {
  form.value = {
    tipo:                   sol.tipo,
    destino:                sol.destino,
    fecha_solicitud:        sol.fecha_solicitud,
    fecha_salida:           sol.fecha_salida,
    hora_salida:            sol.hora_salida,
    fecha_llegada:          sol.fecha_llegada,
    hora_llegada:           sol.hora_llegada,
    descripcion_actividades: sol.descripcion_actividades,
    tiene_viaticos:         sol.tiene_viaticos,
    tiene_movilizaciones:   sol.tiene_movilizaciones,
    tiene_anticipo:         sol.tiene_anticipo,
    transportes:            sol.transportes || [],
  }
  modoEdicion.value       = true
  modoEdicionId.value     = sol.id
  modoEdicionEstado.value = sol.estado
  formTab.value           = 'Datos Generales'
  errorForm.value         = ''
  documentos.value        = []
  form_provinciaId.value  = null
  form_ciudadId.value     = null

  // Pre-seleccionar provincia/ciudad si INTERIOR
  if (sol.tipo === 'INTERIOR' && sol.destino && provincias.value.length) {
    const parts = sol.destino.split(' - ')
    if (parts.length >= 2) {
      const prov = provincias.value.find(p => p.nombre === parts[0])
      if (prov) {
        form_provinciaId.value = prov.id
        const ciu = prov.ciudades?.find(c => c.nombre === parts[1])
        if (ciu) form_ciudadId.value = ciu.id
      }
    }
  }

  modalSolicitud.value = true
}

function abrirDocumentos(sol) {
  editarSolicitud(sol)
  formTab.value = 'Documentos'
  cargarDocumentos(sol.id)
}

function cerrarModalSolicitud() {
  modalSolicitud.value = false
  cargar()
}

// Al cambiar de tab: limpiar error; si llega a Documentos y ya hay ID, cargar docs
watch(formTab, (tab) => {
  errorForm.value = ''
  if (tab !== 'Documentos') return
  if (modoEdicionId.value) {
    cargarDocumentos(modoEdicionId.value)
  }
})

// ── Province/City ──────────────────────────────────────────
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

// ── Save Solicitud ─────────────────────────────────────────
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
    if (modoEdicion.value) {
      await api.put(`/comisiones/solicitudes/${modoEdicionId.value}`, form.value)
    } else {
      await api.post('/comisiones/solicitudes', form.value)
    }
    cerrarModalSolicitud()
  } catch (e) {
    errorForm.value = e.response?.data?.message || 'Error al guardar.'
  } finally {
    guardando.value = false
  }
}

// ── Documents ──────────────────────────────────────────────
async function cargarDocumentos(solicitudId) {
  try {
    const { data } = await api.get(`/comisiones/solicitudes/${solicitudId}`)
    documentos.value = data.documentos ?? []
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
    const url = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
    window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch { alert('Error al descargar.') }
}

async function generarPdfSolicitud() {
  try {
    const resp = await api.get(`/comisiones/solicitudes/${modoEdicionId.value}/pdf`, { responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
    window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch { alert('Error al generar PDF.') }
}

async function procesarDesdeCard(sol) {
  if (procesando.value) return
  if (!confirm(`¿Confirma procesar la solicitud "${sol.destino}"?\n\nYa no podrá editar los datos ni reemplazar documentos.`)) return
  procesando.value = true
  try {
    await api.patch(`/comisiones/solicitudes/${sol.id}/procesar`)
    await cargar()
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
    await cargar()
    // No cerrar — mostrar el banner verde de APROBADO
  } catch (e) {
    alert(e.response?.data?.message || 'Error al subir PDF firmado.')
  } finally {
    subiendoFirmado.value = false
    event.target.value    = ''
  }
}

// ── PDF solicitud ──────────────────────────────────────────
async function descargarPdf(sol) {
  try {
    const resp = await api.get(`/comisiones/solicitudes/${sol.id}/pdf`, { responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
    window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch { alert('Error al generar PDF.') }
}

// ── Detalle ────────────────────────────────────────────────
async function verDetalle(sol) {
  try {
    const { data } = await api.get(`/comisiones/solicitudes/${sol.id}`)
    detalleActual.value = data
    modalDetalle.value  = true
  } catch { alert('Error al cargar detalle.') }
}

// ── Informe ────────────────────────────────────────────────
async function abrirInforme(sol) {
  informeTarget.value          = sol
  informeActual.value          = null
  errorAccion.value            = ''
  subiendoInformeFirmado.value = false

  try {
    const { data } = await api.get(`/comisiones/solicitudes/${sol.id}`)
    if (data.informe) {
      informeActual.value = data.informe
      informeForm.value = {
        fecha_informe: data.informe.fecha_informe || new Date().toISOString().slice(0,10),
        actividades:   data.informe.actividades   || '',
        productos:     data.informe.productos      || '',
        fecha_salida:  data.informe.fecha_salida   || sol.fecha_salida,
        hora_salida:   data.informe.hora_salida    || sol.hora_salida,
        fecha_llegada: data.informe.fecha_llegada  || sol.fecha_llegada,
        hora_llegada:  data.informe.hora_llegada   || sol.hora_llegada,
      }
    } else {
      informeForm.value = {
        fecha_informe: new Date().toISOString().slice(0,10),
        actividades:   '',
        productos:     '',
        fecha_salida:  sol.fecha_salida,
        hora_salida:   sol.hora_salida,
        fecha_llegada: sol.fecha_llegada,
        hora_llegada:  sol.hora_llegada,
      }
    }
  } catch {
    informeForm.value = {
      fecha_informe: new Date().toISOString().slice(0,10),
      actividades:   '',
      productos:     '',
      fecha_salida:  sol.fecha_salida,
      hora_salida:   sol.hora_salida,
      fecha_llegada: sol.fecha_llegada,
      hora_llegada:  sol.hora_llegada,
    }
  }

  modalInforme.value = true
}

async function guardarInforme() {
  if (!informeForm.value.actividades.trim()) {
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
      resp = await api.post(`/comisiones/solicitudes/${informeTarget.value.id}/informe`, informeForm.value)
    }
    informeActual.value = resp.data
    await cargar()
  } catch (e) {
    errorAccion.value = e.response?.data?.message || 'Error al guardar informe.'
  } finally {
    guardando.value = false
  }
}

async function descargarPdfInforme() {
  try {
    const resp = await api.get(`/comisiones/solicitudes/${informeTarget.value.id}/informe/pdf`, { responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
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
    await api.post(`/comisiones/solicitudes/${informeTarget.value.id}/informe/subir-firmado`, fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    informeActual.value = { ...informeActual.value, estado: 'APROBADO' }
    await cargar()
  } catch (e) {
    errorAccion.value = e.response?.data?.message || 'Error al subir informe firmado.'
  } finally {
    subiendoInformeFirmado.value = false
    event.target.value           = ''
  }
}

async function descargarInformeFirmado() {
  try {
    const resp = await api.get(`/comisiones/solicitudes/${informeTarget.value.id}/informe/descargar-firmado`, { responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
    window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch { alert('Error al descargar.') }
}

// ── Solicitar Pago ─────────────────────────────────────────
async function solicitarPago(sol) {
  if (!confirm('¿Solicitar el trámite de pago de viáticos?')) return
  try {
    await api.patch(`/comisiones/solicitudes/${sol.id}/solicitar-pago`)
    await cargar()
  } catch (e) {
    alert(e.response?.data?.message || 'Error.')
  }
}
</script>
