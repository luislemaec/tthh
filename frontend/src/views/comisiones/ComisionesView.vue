<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">Comisiones de Servicio</h1>
        <p class="text-sm text-gray-500 mt-0.5">Gestión de viajes al interior y exterior</p>
      </div>
      <button v-if="puedeCrear" @click="abrirNueva"
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
        <span v-if="tab.badge" class="ml-1.5 bg-amber-400 text-white text-xs font-bold px-1.5 py-0.5 rounded-full">{{ tab.badge }}</span>
      </button>
    </div>

    <!-- Loading -->
    <div v-if="cargando" class="flex items-center justify-center py-16">
      <div class="w-8 h-8 border-2 border-[#5c4a6e] border-t-transparent rounded-full animate-spin"></div>
    </div>

    <template v-else>

      <!-- ═══ TAB: Mis Comisiones ═══ -->
      <div v-if="tabActivo === 'mis'">
        <div v-if="solicitudesMias.length === 0" class="text-center py-16 text-gray-400">
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
                  <span class="font-semibold text-gray-800 text-sm">
                    {{ sol.numero_solicitud || 'Sin número' }}
                  </span>
                  <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full', badgeEstado(sol.estado).class]">
                    {{ badgeEstado(sol.estado).label }}
                  </span>
                  <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                    :class="sol.tipo === 'EXTERIOR' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'">
                    {{ sol.tipo }}
                  </span>
                </div>
                <p class="text-sm text-gray-600 mt-1 truncate">{{ sol.destino }}</p>
                <p class="text-xs text-gray-400 mt-0.5">
                  {{ formatFecha(sol.fecha_salida) }} → {{ formatFecha(sol.fecha_llegada) }}
                </p>
                <p v-if="sol.observacion && ['NEGADO'].includes(sol.estado)" class="text-xs text-red-600 mt-1">
                  <span class="font-semibold">Motivo:</span> {{ sol.observacion }}
                </p>
              </div>
              <div class="flex items-center gap-2 flex-shrink-0">
                <button @click="verDetalle(sol)" title="Ver detalle"
                  class="p-1.5 rounded-md text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                  </svg>
                </button>
                <button v-if="sol.estado === 'BORRADOR'" @click="editarSolicitud(sol)" title="Editar"
                  class="p-1.5 rounded-md text-gray-400 hover:text-[#5c4a6e] hover:bg-purple-50 transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                  </svg>
                </button>
                <button v-if="sol.estado === 'BORRADOR'" @click="enviarSolicitud(sol)" title="Enviar"
                  class="px-3 py-1.5 text-xs font-semibold text-white rounded-md transition hover:opacity-90"
                  style="background-color:#5c4a6e;">
                  Enviar
                </button>
                <button v-if="puedeCrearInforme(sol)" @click="abrirInforme(sol)"
                  class="px-3 py-1.5 text-xs font-semibold bg-green-600 text-white rounded-md transition hover:bg-green-700">
                  Crear Informe
                </button>
                <button v-if="puedeSolicitarPago(sol)" @click="solicitarPago(sol)"
                  class="px-3 py-1.5 text-xs font-semibold bg-blue-600 text-white rounded-md transition hover:bg-blue-700">
                  Solicitar Pago
                </button>
                <button v-if="sol.numero_solicitud" @click="descargarPdf(sol)"
                  title="Descargar PDF"
                  class="p-1.5 rounded-md text-red-400 hover:text-red-600 hover:bg-red-50 transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══ TAB: Pendientes de Aprobación ═══ -->
      <div v-if="tabActivo === 'revision'">
        <div v-if="solicitudesRevision.length === 0" class="text-center py-16 text-gray-400">
          <p class="text-sm">No hay solicitudes pendientes de revisión.</p>
        </div>
        <div v-else class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-xs text-gray-500 uppercase border-b border-gray-100" style="background-color:#f9f7fb;">
                <th class="px-4 py-3 text-left font-semibold">Empleado</th>
                <th class="px-4 py-3 text-left font-semibold">Tipo</th>
                <th class="px-4 py-3 text-left font-semibold">Destino</th>
                <th class="px-4 py-3 text-left font-semibold">Fechas</th>
                <th class="px-4 py-3 text-left font-semibold">Estado</th>
                <th class="px-4 py-3 text-left font-semibold">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="sol in solicitudesRevision" :key="sol.id"
                class="border-b border-gray-50 hover:bg-purple-50/30 transition">
                <td class="px-4 py-3">
                  <p class="font-medium text-gray-800">{{ sol.empleado?.apellido_emp }} {{ sol.empleado?.nombre_emp }}</p>
                  <p class="text-xs text-gray-400">{{ sol.empleado?.cargo_empleado }}</p>
                </td>
                <td class="px-4 py-3">
                  <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                    :class="sol.tipo === 'EXTERIOR' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'">
                    {{ sol.tipo }}
                  </span>
                </td>
                <td class="px-4 py-3 text-gray-600 max-w-[180px] truncate">{{ sol.destino }}</td>
                <td class="px-4 py-3 text-gray-500 text-xs">
                  {{ formatFecha(sol.fecha_salida) }} → {{ formatFecha(sol.fecha_llegada) }}
                </td>
                <td class="px-4 py-3">
                  <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full', badgeEstado(sol.estado).class]">
                    {{ badgeEstado(sol.estado).label }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-1.5">
                    <button @click="verDetalle(sol)" title="Ver detalle"
                      class="p-1.5 rounded text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                      </svg>
                    </button>
                    <template v-if="accionDisponible(sol) === 'dir_adm'">
                      <button @click="confirmarAccion(sol, 'aprobar-dir-adm', 'Aprobar (Dir. Administrativa)')"
                        class="px-2.5 py-1 text-xs font-semibold bg-green-600 text-white rounded transition hover:bg-green-700">Aprobar</button>
                      <button @click="abrirNegar(sol, 'negar-dir-adm')"
                        class="px-2.5 py-1 text-xs font-semibold bg-red-500 text-white rounded transition hover:bg-red-600">Negar</button>
                    </template>
                    <template v-else-if="accionDisponible(sol) === 'jefe'">
                      <button @click="confirmarAccion(sol, 'aprobar-jefe', 'Aprobar solicitud')"
                        class="px-2.5 py-1 text-xs font-semibold bg-green-600 text-white rounded transition hover:bg-green-700">Aprobar</button>
                      <button @click="abrirNegar(sol, 'negar-jefe')"
                        class="px-2.5 py-1 text-xs font-semibold bg-red-500 text-white rounded transition hover:bg-red-600">Negar</button>
                    </template>
                    <template v-else-if="accionDisponible(sol) === 'autoridad'">
                      <button @click="confirmarAccion(sol, 'aprobar-autoridad', 'Autorizar comisión')"
                        class="px-2.5 py-1 text-xs font-semibold bg-green-600 text-white rounded transition hover:bg-green-700">Autorizar</button>
                      <button @click="abrirNegar(sol, 'negar-autoridad')"
                        class="px-2.5 py-1 text-xs font-semibold bg-red-500 text-white rounded transition hover:bg-red-600">Negar</button>
                    </template>
                    <template v-else-if="accionDisponible(sol) === 'juridica'">
                      <button @click="abrirResolucion(sol)"
                        class="px-2.5 py-1 text-xs font-semibold bg-[#5c4a6e] text-white rounded transition hover:opacity-90">Emitir Resolución</button>
                    </template>
                    <template v-else-if="accionDisponible(sol) === 'registro_ext'">
                      <button @click="abrirRegistroExt(sol)"
                        class="px-2.5 py-1 text-xs font-semibold bg-[#5c4a6e] text-white rounded transition hover:opacity-90">Registrar Código Ext.</button>
                    </template>
                    <!-- Revisión de informe -->
                    <template v-if="puedeRevisarInforme(sol)">
                      <button @click="revisarInforme(sol)"
                        class="px-2.5 py-1 text-xs font-semibold bg-indigo-600 text-white rounded transition hover:bg-indigo-700">Revisar Informe</button>
                    </template>
                    <template v-if="puedeAprobarInforme(sol)">
                      <button @click="aprobarInforme(sol)"
                        class="px-2.5 py-1 text-xs font-semibold bg-green-600 text-white rounded transition hover:bg-green-700">Aprobar Informe</button>
                    </template>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ═══ TAB: Todas las Comisiones (admin/autoridad) ═══ -->
      <div v-if="tabActivo === 'todas'">
        <!-- Filtros -->
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
                  <button @click="verDetalle(sol)" title="Ver"
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
      <div class="flex items-center justify-between p-5 border-b border-gray-100">
        <h2 class="text-lg font-bold text-gray-800">{{ modoEdicion ? 'Editar Comisión' : 'Nueva Comisión' }}</h2>
        <button @click="cerrarModalSolicitud" class="text-gray-400 hover:text-gray-600 transition">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <div class="overflow-y-auto flex-1 p-5 space-y-4">
        <!-- Tabs del formulario -->
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
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs font-semibold text-gray-600 mb-1 block">Tipo *</label>
              <select v-model="form.tipo" class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]">
                <option value="INTERIOR">Interior (Nacional)</option>
                <option value="EXTERIOR">Exterior (Internacional)</option>
              </select>
            </div>
            <div>
              <label class="text-xs font-semibold text-gray-600 mb-1 block">Destino *</label>
              <input v-model="form.destino" type="text" placeholder="Ciudad - Provincia / País"
                class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]"/>
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
              class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e] resize-none"/>
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

        <!-- Tab: Servidores -->
        <div v-if="formTab === 'Servidores'" class="space-y-3">
          <p class="text-xs text-gray-500">Empleados que integran la comisión (incluido usted)</p>
          <div v-for="(srv, i) in form.servidores" :key="i"
            class="flex gap-2 items-start border border-gray-100 rounded-lg p-3">
            <div class="flex-1 grid grid-cols-3 gap-2">
              <input v-model="srv.id_emp" type="text" placeholder="Cédula"
                class="text-sm border border-gray-300 rounded px-2 py-1.5 focus:outline-none"/>
              <input v-model="srv.unidad" type="text" placeholder="Unidad"
                class="text-sm border border-gray-300 rounded px-2 py-1.5 focus:outline-none"/>
              <input v-model="srv.puesto" type="text" placeholder="Cargo"
                class="text-sm border border-gray-300 rounded px-2 py-1.5 focus:outline-none"/>
            </div>
            <button @click="form.servidores.splice(i,1)" class="text-red-400 hover:text-red-600 mt-1.5">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
          <button @click="form.servidores.push({id_emp:'',unidad:'',puesto:''})"
            class="text-sm font-medium" style="color:#5c4a6e;">
            + Agregar servidor
          </button>
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
                class="text-sm border border-gray-300 rounded px-2 py-1.5 focus:outline-none"/>
              <input v-model="trn.nombre" type="text" placeholder="Empresa / Vuelo"
                class="text-sm border border-gray-300 rounded px-2 py-1.5 focus:outline-none"/>
            </div>
            <input v-model="trn.ruta" type="text" placeholder="Ruta (Ej: Quito - Guayaquil)"
              class="w-full text-sm border border-gray-300 rounded px-2 py-1.5 focus:outline-none"/>
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

        <!-- Tab: Datos Bancarios -->
        <div v-if="formTab === 'Datos Bancarios'" class="space-y-3">
          <p class="text-xs text-gray-500">Cuenta para el pago de viáticos (si aplica)</p>
          <div>
            <label class="text-xs font-semibold text-gray-600 mb-1 block">Banco</label>
            <input v-model="form.banco" type="text" placeholder="Nombre del banco"
              class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]"/>
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs font-semibold text-gray-600 mb-1 block">Tipo de Cuenta</label>
              <select v-model="form.tipo_cuenta" class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none">
                <option value="">Seleccionar...</option>
                <option value="AHORROS">Ahorros</option>
                <option value="CORRIENTE">Corriente</option>
              </select>
            </div>
            <div>
              <label class="text-xs font-semibold text-gray-600 mb-1 block">Número de Cuenta</label>
              <input v-model="form.numero_cuenta" type="text" placeholder="Número de cuenta"
                class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]"/>
            </div>
          </div>
        </div>

        <p v-if="errorForm" class="text-red-600 text-xs">{{ errorForm }}</p>
      </div>

      <div class="p-5 border-t border-gray-100 flex justify-end gap-3">
        <button @click="cerrarModalSolicitud" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">Cancelar</button>
        <button @click="guardarSolicitud" :disabled="guardando"
          class="px-5 py-2 text-sm font-semibold text-white rounded-lg transition hover:opacity-90 disabled:opacity-50"
          style="background-color:#5c4a6e;">
          {{ guardando ? 'Guardando...' : (modoEdicion ? 'Actualizar' : 'Guardar') }}
        </button>
      </div>
    </div>
  </div>

  <!-- ══════════════════ MODAL NEGAR ══════════════════ -->
  <div v-if="modalNegar" class="fixed inset-0 z-50 flex items-center justify-center px-4">
    <div class="fixed inset-0 bg-black/40" @click="modalNegar = false"/>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md p-6 z-10">
      <h3 class="text-lg font-bold text-gray-800 mb-4">Negar Comisión</h3>
      <label class="text-xs font-semibold text-gray-600 mb-1 block">Motivo / Observación *</label>
      <textarea v-model="negarObservacion" rows="3" placeholder="Indique el motivo..."
        class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-red-400 resize-none"/>
      <p v-if="errorAccion" class="text-red-600 text-xs mt-2">{{ errorAccion }}</p>
      <div class="flex justify-end gap-3 mt-4">
        <button @click="modalNegar = false" class="px-4 py-2 text-sm text-gray-600">Cancelar</button>
        <button @click="ejecutarNegar" :disabled="guardando"
          class="px-5 py-2 text-sm font-semibold bg-red-600 text-white rounded-lg hover:bg-red-700 transition disabled:opacity-50">
          {{ guardando ? 'Procesando...' : 'Negar' }}
        </button>
      </div>
    </div>
  </div>

  <!-- ══════════════════ MODAL RESOLUCIÓN JURÍDICA ══════════════════ -->
  <div v-if="modalResolucion" class="fixed inset-0 z-50 flex items-center justify-center px-4">
    <div class="fixed inset-0 bg-black/40" @click="modalResolucion = false"/>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md p-6 z-10">
      <h3 class="text-lg font-bold text-gray-800 mb-4">Emitir Resolución Jurídica</h3>
      <label class="text-xs font-semibold text-gray-600 mb-1 block">N° Resolución / Referencia *</label>
      <input v-model="resolucionJuridica" type="text" placeholder="Ej: RES-JUR-2026-001"
        class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]"/>
      <p v-if="errorAccion" class="text-red-600 text-xs mt-2">{{ errorAccion }}</p>
      <div class="flex justify-end gap-3 mt-4">
        <button @click="modalResolucion = false" class="px-4 py-2 text-sm text-gray-600">Cancelar</button>
        <button @click="ejecutarResolucion" :disabled="guardando"
          class="px-5 py-2 text-sm font-semibold text-white rounded-lg hover:opacity-90 transition disabled:opacity-50"
          style="background-color:#5c4a6e;">
          {{ guardando ? 'Procesando...' : 'Emitir' }}
        </button>
      </div>
    </div>
  </div>

  <!-- ══════════════════ MODAL REGISTRO EXT. ══════════════════ -->
  <div v-if="modalRegistroExt" class="fixed inset-0 z-50 flex items-center justify-center px-4">
    <div class="fixed inset-0 bg-black/40" @click="modalRegistroExt = false"/>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md p-6 z-10">
      <h3 class="text-lg font-bold text-gray-800 mb-4">Registrar en Sistema Exterior</h3>
      <label class="text-xs font-semibold text-gray-600 mb-1 block">Código / Número del Sistema *</label>
      <input v-model="numSistemaExt" type="text" placeholder="Código asignado en el sistema"
        class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]"/>
      <p v-if="errorAccion" class="text-red-600 text-xs mt-2">{{ errorAccion }}</p>
      <div class="flex justify-end gap-3 mt-4">
        <button @click="modalRegistroExt = false" class="px-4 py-2 text-sm text-gray-600">Cancelar</button>
        <button @click="ejecutarRegistroExt" :disabled="guardando"
          class="px-5 py-2 text-sm font-semibold text-white rounded-lg hover:opacity-90 transition disabled:opacity-50"
          style="background-color:#5c4a6e;">
          {{ guardando ? 'Procesando...' : 'Registrar' }}
        </button>
      </div>
    </div>
  </div>

  <!-- ══════════════════ MODAL INFORME ══════════════════ -->
  <div v-if="modalInforme" class="fixed inset-0 z-50 flex items-start justify-center pt-8 pb-4 px-4">
    <div class="fixed inset-0 bg-black/40" @click="modalInforme = false"/>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col z-10">
      <div class="flex items-center justify-between p-5 border-b border-gray-100">
        <h2 class="text-lg font-bold text-gray-800">Informe de Cumplimiento</h2>
        <button @click="modalInforme = false" class="text-gray-400 hover:text-gray-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
      <div class="overflow-y-auto flex-1 p-5 space-y-4">
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
            class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none resize-none"/>
        </div>
        <div>
          <label class="text-xs font-semibold text-gray-600 mb-1 block">Productos / Resultados</label>
          <textarea v-model="informeForm.productos" rows="2" placeholder="Resultados obtenidos..."
            class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none resize-none"/>
        </div>
        <p v-if="errorAccion" class="text-red-600 text-xs">{{ errorAccion }}</p>
      </div>
      <div class="p-5 border-t border-gray-100 flex justify-end gap-3">
        <button @click="modalInforme = false" class="px-4 py-2 text-sm text-gray-600">Cancelar</button>
        <button @click="guardarInforme" :disabled="guardando"
          class="px-5 py-2 text-sm font-semibold text-white rounded-lg hover:opacity-90 disabled:opacity-50"
          style="background-color:#5c4a6e;">
          {{ guardando ? 'Guardando...' : 'Presentar Informe' }}
        </button>
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
        <div v-if="detalleActual.observacion" class="bg-red-50 border border-red-100 rounded-md p-3">
          <p class="text-xs font-semibold text-red-600 mb-0.5">OBSERVACIÓN</p>
          <p class="text-xs text-red-700">{{ detalleActual.observacion }}</p>
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
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const auth = useAuthStore()

// ── State ──────────────────────────────────────────────────
const cargando  = ref(true)
const solicitudes = ref([])
const miRol     = ref({})
const tabActivo = ref('mis')

const modalSolicitud  = ref(false)
const modoEdicion     = ref(false)
const guardando       = ref(false)
const errorForm       = ref('')
const formTab         = ref('Datos Generales')
const formTabs        = ['Datos Generales', 'Servidores', 'Transporte', 'Datos Bancarios']

const modalNegar      = ref(false)
const negarAccion     = ref('')
const negarTarget     = ref(null)
const negarObservacion = ref('')
const errorAccion     = ref('')

const modalResolucion  = ref(false)
const resolucionJuridica = ref('')

const modalRegistroExt = ref(false)
const numSistemaExt    = ref('')

const modalInforme    = ref(false)
const informeTarget   = ref(null)

const modalDetalle    = ref(false)
const detalleActual   = ref(null)

const filtroEstado    = ref('')
const filtroTipo      = ref('')
const filtroBusqueda  = ref('')

const accionTarget    = ref(null)

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
  banco: '',
  tipo_cuenta: '',
  numero_cuenta: '',
  servidores: [],
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

const puedeCrear = computed(() => true) // todos los empleados pueden solicitar

const solicitudesMias = computed(() =>
  solicitudes.value.filter(s => s.id_emp === idEmp.value)
)

const pendientesRevision = computed(() =>
  solicitudes.value.filter(s =>
    ['PENDIENTE_DIR_ADM','PENDIENTE_JEFE','PENDIENTE_AUTORIDAD','PENDIENTE_JURIDICA','PENDIENTE_SISTEMA_EXT',
     'INFORME_PRESENTADO','INFORME_REVISADO'].includes(s.estado)
  )
)

const solicitudesRevision = computed(() => {
  if (miRol.value.es_admin) return pendientesRevision.value
  return pendientesRevision.value.filter(s => {
    if (miRol.value.es_dir_adm && s.estado === 'PENDIENTE_DIR_ADM') return true
    if (miRol.value.es_supervisor && s.estado === 'PENDIENTE_JEFE') return true
    if (miRol.value.es_maxima_autoridad && ['PENDIENTE_AUTORIDAD','INFORME_REVISADO'].includes(s.estado)) return true
    if (miRol.value.es_juridica && s.estado === 'PENDIENTE_JURIDICA') return true
    if (miRol.value.es_admin && s.estado === 'PENDIENTE_SISTEMA_EXT') return true
    if (miRol.value.es_supervisor && s.estado === 'INFORME_PRESENTADO') return true
    return false
  })
})

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
  const tabs = [{ key: 'mis', label: 'Mis Comisiones', badge: null }]
  const pend = solicitudesRevision.value.length
  if (miRol.value.es_supervisor || miRol.value.es_dir_adm || miRol.value.es_maxima_autoridad ||
      miRol.value.es_juridica || miRol.value.es_admin) {
    tabs.push({ key: 'revision', label: 'Pendientes de Revisión', badge: pend > 0 ? pend : null })
  }
  if (miRol.value.es_admin || miRol.value.es_maxima_autoridad) {
    tabs.push({ key: 'todas', label: 'Todas las Comisiones', badge: null })
  }
  return tabs
})

const estadoOpciones = [
  { value: 'BORRADOR', label: 'Borrador' },
  { value: 'PENDIENTE_DIR_ADM', label: 'Pend. Dir. Adm.' },
  { value: 'PENDIENTE_JEFE', label: 'Pend. Jefe' },
  { value: 'PENDIENTE_AUTORIDAD', label: 'Pend. Autoridad' },
  { value: 'PENDIENTE_JURIDICA', label: 'Pend. Jurídica' },
  { value: 'PENDIENTE_SISTEMA_EXT', label: 'Pend. Sistema Ext.' },
  { value: 'AUTORIZADO', label: 'Autorizado' },
  { value: 'INFORME_PRESENTADO', label: 'Informe Presentado' },
  { value: 'INFORME_REVISADO', label: 'Informe Revisado' },
  { value: 'INFORME_APROBADO', label: 'Informe Aprobado' },
  { value: 'EN_PAGO', label: 'En Pago' },
  { value: 'EN_LIQUIDACION', label: 'En Liquidación' },
  { value: 'CERRADO', label: 'Cerrado' },
  { value: 'NEGADO', label: 'Negado' },
  { value: 'POR_COBRAR', label: 'Por Cobrar' },
]

// ── Helpers ────────────────────────────────────────────────
function badgeEstado(estado) {
  const mapa = {
    BORRADOR: { class: 'bg-gray-100 text-gray-600', label: 'Borrador' },
    PENDIENTE_DIR_ADM: { class: 'bg-amber-100 text-amber-700', label: 'Pend. Dir. Adm.' },
    PENDIENTE_JEFE: { class: 'bg-amber-100 text-amber-700', label: 'Pend. Jefe' },
    PENDIENTE_AUTORIDAD: { class: 'bg-amber-100 text-amber-700', label: 'Pend. Autoridad' },
    PENDIENTE_JURIDICA: { class: 'bg-amber-100 text-amber-700', label: 'Pend. Jurídica' },
    PENDIENTE_SISTEMA_EXT: { class: 'bg-amber-100 text-amber-700', label: 'Pend. Sistema Ext.' },
    AUTORIZADO: { class: 'bg-green-100 text-green-700', label: 'Autorizado' },
    INFORME_PENDIENTE: { class: 'bg-blue-100 text-blue-700', label: 'Informe Pendiente' },
    INFORME_PRESENTADO: { class: 'bg-indigo-100 text-indigo-700', label: 'Informe Presentado' },
    INFORME_REVISADO: { class: 'bg-violet-100 text-violet-700', label: 'Informe Revisado' },
    INFORME_APROBADO: { class: 'bg-green-100 text-green-700', label: 'Informe Aprobado' },
    EN_PAGO: { class: 'bg-blue-100 text-blue-700', label: 'En Pago' },
    EN_LIQUIDACION: { class: 'bg-cyan-100 text-cyan-700', label: 'En Liquidación' },
    POR_COBRAR: { class: 'bg-orange-100 text-orange-700', label: 'Por Cobrar' },
    CERRADO: { class: 'bg-emerald-100 text-emerald-800', label: 'Cerrado' },
    NEGADO: { class: 'bg-red-100 text-red-700', label: 'Negado' },
  }
  return mapa[estado] || { class: 'bg-gray-100 text-gray-600', label: estado }
}

function formatFecha(f) {
  if (!f) return '—'
  const [y, m, d] = f.split('-')
  return `${d}/${m}/${y}`
}

function accionDisponible(sol) {
  if (miRol.value.es_dir_adm && sol.estado === 'PENDIENTE_DIR_ADM') return 'dir_adm'
  if (miRol.value.es_supervisor && sol.estado === 'PENDIENTE_JEFE') return 'jefe'
  if (miRol.value.es_maxima_autoridad && sol.estado === 'PENDIENTE_AUTORIDAD') return 'autoridad'
  if (miRol.value.es_juridica && sol.estado === 'PENDIENTE_JURIDICA') return 'juridica'
  if (miRol.value.es_admin && sol.estado === 'PENDIENTE_SISTEMA_EXT') return 'registro_ext'
  if (miRol.value.es_admin) {
    if (sol.estado === 'PENDIENTE_DIR_ADM') return 'dir_adm'
    if (sol.estado === 'PENDIENTE_JEFE') return 'jefe'
  }
  return null
}

function puedeCrearInforme(sol) {
  return sol.id_emp === idEmp.value &&
    ['AUTORIZADO', 'INFORME_PENDIENTE'].includes(sol.estado) &&
    !sol.informe
}

function puedeSolicitarPago(sol) {
  return sol.id_emp === idEmp.value && sol.estado === 'INFORME_APROBADO'
}

function puedeRevisarInforme(sol) {
  return (miRol.value.es_supervisor || miRol.value.es_admin) &&
    sol.estado === 'INFORME_PRESENTADO'
}

function puedeAprobarInforme(sol) {
  return (miRol.value.es_maxima_autoridad || miRol.value.es_admin) &&
    sol.estado === 'INFORME_REVISADO'
}

// ── Load ───────────────────────────────────────────────────
async function cargar() {
  cargando.value = true
  try {
    const [rolResp, solResp] = await Promise.all([
      api.get('/comisiones/mi-rol'),
      api.get('/comisiones/solicitudes'),
    ])
    miRol.value = rolResp.data
    solicitudes.value = solResp.data.data || solResp.data
  } catch (e) {
    console.error(e)
  } finally {
    cargando.value = false
  }
}

onMounted(cargar)

// ── Acciones Modal Solicitud ───────────────────────────────
function abrirNueva() {
  form.value = formVacio()
  modoEdicion.value = false
  formTab.value = 'Datos Generales'
  errorForm.value = ''
  modalSolicitud.value = true
}

function editarSolicitud(sol) {
  form.value = {
    tipo: sol.tipo,
    destino: sol.destino,
    fecha_salida: sol.fecha_salida,
    hora_salida: sol.hora_salida,
    fecha_llegada: sol.fecha_llegada,
    hora_llegada: sol.hora_llegada,
    descripcion_actividades: sol.descripcion_actividades,
    tiene_viaticos: sol.tiene_viaticos,
    tiene_movilizaciones: sol.tiene_movilizaciones,
    tiene_anticipo: sol.tiene_anticipo,
    banco: sol.banco || '',
    tipo_cuenta: sol.tipo_cuenta || '',
    numero_cuenta: sol.numero_cuenta || '',
    servidores: sol.servidores || [],
    transportes: sol.transportes || [],
  }
  modoEdicion.value = true
  modoEdicionId.value = sol.id
  formTab.value = 'Datos Generales'
  errorForm.value = ''
  modalSolicitud.value = true
}

const modoEdicionId = ref(null)

function cerrarModalSolicitud() {
  modalSolicitud.value = false
}

async function guardarSolicitud() {
  errorForm.value = ''
  if (!form.value.destino || !form.value.fecha_salida || !form.value.fecha_llegada || !form.value.descripcion_actividades) {
    errorForm.value = 'Complete los campos obligatorios (Destino, Fechas, Actividades).'
    return
  }
  guardando.value = true
  try {
    if (modoEdicion.value) {
      await api.put(`/comisiones/solicitudes/${modoEdicionId.value}`, form.value)
    } else {
      await api.post('/comisiones/solicitudes', form.value)
    }
    modalSolicitud.value = false
    await cargar()
  } catch (e) {
    errorForm.value = e.response?.data?.message || 'Error al guardar.'
  } finally {
    guardando.value = false
  }
}

async function enviarSolicitud(sol) {
  if (!confirm('¿Enviar esta solicitud para revisión de la Dirección Administrativa?')) return
  try {
    await api.patch(`/comisiones/solicitudes/${sol.id}/enviar`)
    await cargar()
  } catch (e) {
    alert(e.response?.data?.message || 'Error al enviar.')
  }
}

async function descargarPdf(sol) {
  try {
    const resp = await api.get(`/comisiones/solicitudes/${sol.id}/pdf`, { responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
    window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch { alert('Error al generar PDF.') }
}

async function verDetalle(sol) {
  try {
    const { data } = await api.get(`/comisiones/solicitudes/${sol.id}`)
    detalleActual.value = data
    modalDetalle.value = true
  } catch { alert('Error al cargar detalle.') }
}

// ── Acciones de Revisión ───────────────────────────────────
async function confirmarAccion(sol, endpoint, titulo) {
  if (!confirm(`¿${titulo}?`)) return
  guardando.value = true
  errorAccion.value = ''
  try {
    await api.patch(`/comisiones/solicitudes/${sol.id}/${endpoint}`)
    await cargar()
  } catch (e) {
    alert(e.response?.data?.message || 'Error al procesar.')
  } finally {
    guardando.value = false
  }
}

function abrirNegar(sol, accion) {
  negarTarget.value = sol
  negarAccion.value = accion
  negarObservacion.value = ''
  errorAccion.value = ''
  modalNegar.value = true
}

async function ejecutarNegar() {
  if (!negarObservacion.value.trim()) {
    errorAccion.value = 'Indique el motivo.'
    return
  }
  guardando.value = true
  try {
    await api.patch(`/comisiones/solicitudes/${negarTarget.value.id}/${negarAccion.value}`, {
      observacion: negarObservacion.value,
    })
    modalNegar.value = false
    await cargar()
  } catch (e) {
    errorAccion.value = e.response?.data?.message || 'Error al negar.'
  } finally {
    guardando.value = false
  }
}

function abrirResolucion(sol) {
  accionTarget.value = sol
  resolucionJuridica.value = ''
  errorAccion.value = ''
  modalResolucion.value = true
}

async function ejecutarResolucion() {
  if (!resolucionJuridica.value.trim()) {
    errorAccion.value = 'Ingrese el número de resolución.'
    return
  }
  guardando.value = true
  try {
    await api.patch(`/comisiones/solicitudes/${accionTarget.value.id}/emitir-resolucion`, {
      resolucion_juridica: resolucionJuridica.value,
    })
    modalResolucion.value = false
    await cargar()
  } catch (e) {
    errorAccion.value = e.response?.data?.message || 'Error.'
  } finally {
    guardando.value = false
  }
}

function abrirRegistroExt(sol) {
  accionTarget.value = sol
  numSistemaExt.value = ''
  errorAccion.value = ''
  modalRegistroExt.value = true
}

async function ejecutarRegistroExt() {
  if (!numSistemaExt.value.trim()) {
    errorAccion.value = 'Ingrese el código del sistema.'
    return
  }
  guardando.value = true
  try {
    await api.patch(`/comisiones/solicitudes/${accionTarget.value.id}/registrar-ext`, {
      num_sistema_exterior: numSistemaExt.value,
    })
    modalRegistroExt.value = false
    await cargar()
  } catch (e) {
    errorAccion.value = e.response?.data?.message || 'Error.'
  } finally {
    guardando.value = false
  }
}

// ── Informe ───────────────────────────────────────────────
function abrirInforme(sol) {
  informeTarget.value = sol
  informeForm.value = {
    fecha_informe: new Date().toISOString().slice(0,10),
    actividades: '',
    productos: '',
    fecha_salida: sol.fecha_salida,
    hora_salida: sol.hora_salida,
    fecha_llegada: sol.fecha_llegada,
    hora_llegada: sol.hora_llegada,
  }
  errorAccion.value = ''
  modalInforme.value = true
}

async function guardarInforme() {
  if (!informeForm.value.actividades.trim()) {
    errorAccion.value = 'Las actividades son obligatorias.'
    return
  }
  guardando.value = true
  try {
    await api.post(`/comisiones/solicitudes/${informeTarget.value.id}/informe`, informeForm.value)
    modalInforme.value = false
    await cargar()
  } catch (e) {
    errorAccion.value = e.response?.data?.message || 'Error al guardar informe.'
  } finally {
    guardando.value = false
  }
}

async function revisarInforme(sol) {
  if (!confirm('¿Marcar el informe como revisado?')) return
  try {
    const { data } = await api.get(`/comisiones/solicitudes/${sol.id}`)
    await api.patch(`/comisiones/informes/${data.informe.id}/revisar`)
    await cargar()
  } catch (e) {
    alert(e.response?.data?.message || 'Error.')
  }
}

async function aprobarInforme(sol) {
  if (!confirm('¿Aprobar el informe de cumplimiento?')) return
  try {
    const { data } = await api.get(`/comisiones/solicitudes/${sol.id}`)
    await api.patch(`/comisiones/informes/${data.informe.id}/aprobar`)
    await cargar()
  } catch (e) {
    alert(e.response?.data?.message || 'Error.')
  }
}

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
