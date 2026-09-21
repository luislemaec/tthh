<template>
  <div class="space-y-5 pb-8">

    <!-- ── HEADER ──────────────────────────────────────────────────── -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">Reporte Distributivo de Personal</h1>
        <p class="text-sm text-gray-400 mt-0.5">
          {{ resumen?.total_activos ?? '—' }} empleados activos
        </p>
      </div>
      <div class="flex gap-2 flex-wrap">
        <button @click="exportar('excel')" :disabled="exportando || !puedeExportar"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium border border-green-700 text-green-700 hover:bg-green-50 transition disabled:opacity-50 disabled:cursor-not-allowed">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
          Excel
        </button>
        <button @click="exportar('pdf')" :disabled="exportando || !puedeExportar"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium border border-red-600 text-red-600 hover:bg-red-50 transition disabled:opacity-50 disabled:cursor-not-allowed">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
          </svg>
          PDF
        </button>
      </div>
    </div>

    <!-- ── ALERTAS ──────────────────────────────────────────────────── -->
    <div v-if="resumen" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
      <button v-for="a in alertaCards" :key="a.key"
        @click="aplicarAlerta(a.filtro)"
        class="rounded-xl p-4 text-left transition hover:scale-[1.02] hover:shadow-md border-l-4 w-full"
        :class="a.bg">
        <div class="flex items-center justify-between mb-1">
          <span class="text-xs font-semibold uppercase tracking-wide" :class="a.label">{{ a.titulo }}</span>
          <span class="text-2xl font-extrabold" :class="a.num">{{ resumen.alertas[a.key] }}</span>
        </div>
        <p class="text-xs" :class="a.label">{{ a.desc }}</p>
      </button>
    </div>

    <!-- ── ESTADÍSTICAS + GRÁFICOS ──────────────────────────────────── -->
    <div v-if="resumen" class="grid grid-cols-1 gap-4 md:grid-cols-3">

      <!-- Sexo — donut -->
      <div class="bg-white rounded-xl shadow p-5">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Distribución por Sexo</p>
        <div class="relative h-44">
          <canvas ref="chartSexo"></canvas>
        </div>
      </div>

      <!-- Tipo contrato — barras horizontales -->
      <div class="bg-white rounded-xl shadow p-5">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Tipo de Contrato</p>
        <div class="relative h-44">
          <canvas ref="chartContrato"></canvas>
        </div>
      </div>

      <!-- Modalidad marcación — barras horizontales -->
      <div class="bg-white rounded-xl shadow p-5">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Modalidad de Marcación</p>
        <div class="relative h-44">
          <canvas ref="chartModalidad"></canvas>
        </div>
      </div>

      <!-- Antigüedad — barras horizontales (fila completa) -->
      <div class="bg-white rounded-xl shadow p-5 md:col-span-3">
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Distribución por Antigüedad (años de servicio)</p>
        <div class="relative h-36">
          <canvas ref="chartAntiguedad"></canvas>
        </div>
      </div>
    </div>

    <!-- ── FILTROS ──────────────────────────────────────────────────── -->
    <div class="bg-white rounded-xl shadow overflow-hidden">
      <button @click="filtrosAbiertos = !filtrosAbiertos"
        class="w-full flex items-center justify-between px-5 py-4 hover:bg-gray-50 transition">
        <div class="flex items-center gap-3">
          <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
          </svg>
          <span class="font-semibold text-gray-700">Filtros</span>
          <span v-if="filtrosActivos > 0"
            class="bg-[#0b5447] text-white text-xs font-bold px-2 py-0.5 rounded-full">
            {{ filtrosActivos }} activo{{ filtrosActivos > 1 ? 's' : '' }}
          </span>
        </div>
        <svg class="w-5 h-5 text-gray-400 transition-transform" :class="filtrosAbiertos ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
      </button>

      <div v-show="filtrosAbiertos" class="px-5 pb-5 border-t border-gray-100 pt-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">

          <!-- General -->
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Búsqueda</label>
            <input v-model="filtros.busqueda" type="text" placeholder="Nombre, apellido o cédula..."
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#579186] focus:border-transparent bg-gray-50 focus:bg-white outline-none" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Departamento</label>
            <select v-model="filtros.id_depto"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#579186] bg-gray-50 focus:bg-white outline-none">
              <option value="">Todos</option>
              <option v-for="d in departamentos" :key="d.id_depto" :value="d.id_depto">{{ d.nombre_depto }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Estado</label>
            <select v-model="filtros.estado"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#579186] bg-gray-50 focus:bg-white outline-none">
              <option value="ACTIVO">Activo</option>
              <option value="INACTIVO">Inactivo</option>
              <option value="TODOS">Todos</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Tipo Contrato</label>
            <select v-model="filtros.tipo_contrato"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#579186] bg-gray-50 focus:bg-white outline-none">
              <option value="">Todos</option>
              <option value="LOSEP">LOSEP</option>
              <option value="CODIGO DEL TRABAJO">Código del Trabajo</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Modalidad Marcación</label>
            <select v-model="filtros.modalidad_marcacion"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#579186] bg-gray-50 focus:bg-white outline-none">
              <option value="">Todas</option>
              <option value="PRESENCIAL">Presencial</option>
              <option value="TEMPORAL">Temporal</option>
              <option value="TELETRABAJO">Teletrabajo</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Sexo</label>
            <select v-model="filtros.sexo"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#579186] bg-gray-50 focus:bg-white outline-none">
              <option value="">Todos</option>
              <option value="MASCULINO">Masculino</option>
              <option value="FEMENINO">Femenino</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Tipo de Sangre</label>
            <select v-model="filtros.tipo_sangre"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#579186] bg-gray-50 focus:bg-white outline-none">
              <option value="">Todos</option>
              <option v-for="ts in tiposSangre" :key="ts" :value="ts">{{ ts }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Discapacidad</label>
            <select v-model="filtros.tiene_discapacidad"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#579186] bg-gray-50 focus:bg-white outline-none">
              <option value="">Todos</option>
              <option value="1">Con discapacidad</option>
              <option value="0">Sin discapacidad</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Enf. Catastrófica</label>
            <select v-model="filtros.tiene_enfermedad"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#579186] bg-gray-50 focus:bg-white outline-none">
              <option value="">Todos</option>
              <option value="1">Con enfermedad catastrófica</option>
              <option value="0">Sin enfermedad catastrófica</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Grupo Vulnerable</label>
            <select v-model="filtros.grupo_vulnerable_id"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#579186] bg-gray-50 focus:bg-white outline-none">
              <option value="">Todos</option>
              <option v-for="g in catalogos.grupos_vulnerables" :key="g.id" :value="g.id">{{ g.nombre }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Grupo Prioritario</label>
            <select v-model="filtros.grupo_prioritario_id"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#579186] bg-gray-50 focus:bg-white outline-none">
              <option value="">Todos</option>
              <option v-for="g in catalogos.grupos_prioritarios" :key="g.id" :value="g.id">{{ g.nombre }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Hijos &lt; 5 años (Guardería)</label>
            <select v-model="filtros.con_guarderia"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#579186] bg-gray-50 focus:bg-white outline-none">
              <option value="">Todos</option>
              <option value="1">Con hijos menores de 5 años</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Doc. Persona Sustituta</label>
            <select v-model="filtros.sustituta_filter"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#579186] bg-gray-50 focus:bg-white outline-none">
              <option value="">Todos</option>
              <option value="tiene">Con documento sustituta</option>
              <option value="vencida">Doc. vencido</option>
              <option value="proxima">Vence en 30 días</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">SERCOP</label>
            <select v-model="filtros.sercop_filter"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#579186] bg-gray-50 focus:bg-white outline-none">
              <option value="">Todos</option>
              <option value="con">Con SERCOP</option>
              <option value="sin">Sin SERCOP</option>
              <option value="vencido">SERCOP vencido</option>
              <option value="proximo">Vence en 30 días</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Solicita Vehículo</label>
            <select v-model="filtros.puede_vehiculo"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#579186] bg-gray-50 focus:bg-white outline-none">
              <option value="">Todos</option>
              <option value="1">Sí puede solicitar</option>
              <option value="0">No puede solicitar</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Motivo de Salida</label>
            <select v-model="filtros.motivo_salida"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#579186] bg-gray-50 focus:bg-white outline-none">
              <option value="">Todos</option>
              <option value="COMISIÓN DE SERVICIOS">Comisión de servicios (saliente)</option>
              <option value="FIN DE COMISIÓN DE SERVICIOS">Fin de comisión de servicios</option>
              <option value="FIN DE CONTRATO">Fin de contrato</option>
              <option value="RENUNCIA VOLUNTARIA">Renuncia voluntaria</option>
              <option value="JUBILACIÓN">Jubilación</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Comisión Entrante</label>
            <select v-model="filtros.es_comisionado_entrante"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#579186] bg-gray-50 focus:bg-white outline-none">
              <option value="">Todos</option>
              <option value="1">Viene de comisión de otra institución</option>
              <option value="0">No viene de comisión</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Antigüedad</label>
            <select v-model="filtros.antiguedad"
              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#579186] bg-gray-50 focus:bg-white outline-none">
              <option value="">Todos</option>
              <option value="menos5">Menos de 5 años</option>
              <option value="5a10">5 – 10 años</option>
              <option value="10a15">10 – 15 años</option>
              <option value="15a20">15 – 20 años</option>
              <option value="mas20">20 o más años</option>
            </select>
          </div>
        </div>

        <p v-if="errorBuscar" class="mt-3 text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg px-4 py-2">
          {{ errorBuscar }}
        </p>
        <div class="flex gap-3 mt-4 pt-4 border-t border-gray-100">
          <button @click="buscar" :disabled="cargando"
            class="bg-[#0b5447] text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-[#00372e] disabled:opacity-50 transition flex items-center gap-2">
            <svg v-if="cargando" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            {{ cargando ? 'Buscando...' : 'Buscar' }}
          </button>
          <button @click="limpiar"
            class="border border-gray-300 text-gray-600 px-5 py-2 rounded-lg text-sm hover:bg-gray-50 transition">
            Limpiar filtros
          </button>
        </div>
      </div>
    </div>

    <!-- ── RESULTADOS ───────────────────────────────────────────────── -->
    <div v-if="buscado" class="bg-white rounded-xl shadow overflow-hidden">
      <div class="px-5 py-4 border-b flex items-center justify-between">
        <h2 class="font-semibold text-gray-700">
          Resultados
          <span class="ml-2 bg-[#0b5447] text-white text-xs font-bold px-2 py-0.5 rounded-full">
            {{ empleadosPaginados.length ? (paginaActual-1)*porPagina+1 + '–' + Math.min(paginaActual*porPagina, empleados.length) : 0 }} de {{ empleados.length }}
          </span>
        </h2>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-xs" style="min-width:900px;">
          <thead style="background-color:#0b5447;">
            <tr>
              <th class="px-3 py-3 text-left text-white/80 font-semibold">#</th>
              <th class="px-3 py-3 text-left text-white/80 font-semibold">Cédula</th>
              <th class="px-3 py-3 text-left text-white/80 font-semibold">Apellidos y Nombres</th>
              <th class="px-3 py-3 text-left text-white/80 font-semibold">Departamento</th>
              <th class="px-3 py-3 text-left text-white/80 font-semibold">Cargo</th>
              <th class="px-3 py-3 text-center text-white/80 font-semibold">Contrato</th>
              <th class="px-3 py-3 text-center text-white/80 font-semibold">Sexo</th>
              <th class="px-3 py-3 text-center text-white/80 font-semibold">T.Sangre</th>
              <th class="px-3 py-3 text-center text-white/80 font-semibold">Discap.</th>
              <th class="px-3 py-3 text-center text-white/80 font-semibold">SERCOP</th>
              <th class="px-3 py-3 text-center text-white/80 font-semibold">Sustituta</th>
              <th class="px-3 py-3 text-center text-white/80 font-semibold">H&lt;5</th>
              <th class="px-3 py-3 text-left text-white/80 font-semibold">F. Ingreso</th>
              <th class="px-3 py-3 text-center text-white/80 font-semibold cursor-pointer select-none hover:text-white"
                @click="toggleSort">
                Años Serv. {{ sortDir === 'desc' ? '↓' : sortDir === 'asc' ? '↑' : '↕' }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="cargando">
              <td colspan="14" class="text-center py-12 text-gray-400">Cargando...</td>
            </tr>
            <tr v-else-if="!empleados.length">
              <td colspan="14" class="text-center py-12 text-gray-400">Sin resultados para los filtros seleccionados</td>
            </tr>
            <tr v-for="(e, i) in empleadosPaginados" :key="e.id_emp"
              class="border-b border-gray-100 hover:bg-green-50/40 transition">
              <td class="px-3 py-2 text-gray-400">{{ (paginaActual-1)*porPagina + i + 1 }}</td>
              <td class="px-3 py-2 font-mono">{{ e.identificacion }}</td>
              <td class="px-3 py-2 font-medium">{{ e.apellido_emp }} {{ e.nombre_emp }}</td>
              <td class="px-3 py-2 text-gray-500">{{ e.nombre_depto || '—' }}</td>
              <td class="px-3 py-2 text-gray-600">{{ e.cargo_empleado || '—' }}</td>
              <td class="px-3 py-2 text-center">
                <span class="px-1.5 py-0.5 rounded text-xs" :class="e.tipo_contrato?.includes('LOSEP') ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'">
                  {{ (e.tipo_contrato || '').trim().substring(0,10) || '—' }}
                </span>
              </td>
              <td class="px-3 py-2 text-center">{{ e.sexo ? e.sexo.substring(0,1) : '—' }}</td>
              <td class="px-3 py-2 text-center font-medium">{{ e.tipo_sangre || '—' }}</td>
              <td class="px-3 py-2 text-center">
                <span v-if="e.tiene_discapacidad" class="bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded text-xs">
                  {{ e.porcentaje_discapacidad || '' }}%
                </span>
                <span v-else class="text-gray-300">—</span>
              </td>
              <td class="px-3 py-2 text-center">
                <span v-if="e.fecha_vence_sercop"
                  :class="sercop_badge(e.fecha_vence_sercop)"
                  class="px-1.5 py-0.5 rounded text-xs font-medium">
                  {{ e.fecha_vence_sercop?.substring(0,10) }}
                </span>
                <span v-else class="text-gray-300">—</span>
              </td>
              <td class="px-3 py-2 text-center">
                <span v-if="e.sustituta_fecha_caducidad"
                  :class="sercop_badge(e.sustituta_fecha_caducidad)"
                  class="px-1.5 py-0.5 rounded text-xs font-medium">
                  {{ e.sustituta_fecha_caducidad?.substring(0,10) }}
                </span>
                <span v-else class="text-gray-300">—</span>
              </td>
              <td class="px-3 py-2 text-center">
                <span v-if="e.hijos_menores_5 > 0" class="bg-teal-100 text-teal-700 px-1.5 py-0.5 rounded text-xs font-bold">
                  {{ e.hijos_menores_5 }}
                </span>
                <span v-else class="text-gray-300">—</span>
              </td>
              <td class="px-3 py-2 text-gray-500 font-mono text-xs whitespace-nowrap">
                {{ e.fecha_ingreso?.substring(0,10) || '—' }}
              </td>
              <td class="px-3 py-2 text-center">
                <span v-if="e.anios_servicio != null"
                  class="font-semibold text-xs px-2 py-0.5 rounded"
                  :class="e.anios_servicio >= 20 ? 'bg-purple-100 text-purple-700' :
                          e.anios_servicio >= 15 ? 'bg-blue-100 text-blue-700' :
                          e.anios_servicio >= 10 ? 'bg-teal-100 text-teal-700' :
                          e.anios_servicio >= 5  ? 'bg-green-100 text-green-700' :
                                                   'bg-gray-100 text-gray-600'">
                  {{ e.anios_servicio }} a.
                </span>
                <span v-else class="text-gray-300">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Paginación -->
      <div v-if="totalPaginas > 1" class="flex items-center justify-between px-5 py-3 border-t text-sm text-gray-500 flex-wrap gap-2">
        <div class="flex items-center gap-2">
          Filas:
          <select v-model="porPagina" @change="paginaActual=1" class="border rounded px-2 py-1 text-sm">
            <option :value="25">25</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
            <option :value="9999">Todos</option>
          </select>
        </div>
        <div class="flex items-center gap-1">
          <button @click="paginaActual=1" :disabled="paginaActual===1" class="px-2 py-1 rounded border disabled:opacity-40 hover:bg-gray-50">«</button>
          <button @click="paginaActual--" :disabled="paginaActual===1" class="px-3 py-1 rounded border disabled:opacity-40 hover:bg-gray-50">‹</button>
          <span class="px-3 font-medium">{{ paginaActual }} / {{ totalPaginas }}</span>
          <button @click="paginaActual++" :disabled="paginaActual===totalPaginas" class="px-3 py-1 rounded border disabled:opacity-40 hover:bg-gray-50">›</button>
          <button @click="paginaActual=totalPaginas" :disabled="paginaActual===totalPaginas" class="px-2 py-1 rounded border disabled:opacity-40 hover:bg-gray-50">»</button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue'
import api from '@/services/api'
import {
  Chart, DoughnutController, ArcElement,
  BarController, BarElement, CategoryScale, LinearScale,
  Tooltip, Legend
} from 'chart.js'

Chart.register(DoughnutController, ArcElement, BarController, BarElement, CategoryScale, LinearScale, Tooltip, Legend)

const resumen        = ref(null)
const empleados      = ref([])
const departamentos  = ref([])
const catalogos      = ref({ grupos_vulnerables: [], grupos_prioritarios: [] })
const cargando       = ref(false)
const exportando     = ref(false)
const buscado        = ref(false)
const puedeExportar  = ref(false)
const errorBuscar    = ref('')
const filtrosAbiertos = ref(true)
const paginaActual   = ref(1)
const porPagina      = ref(25)

const chartSexo       = ref(null)
const chartContrato   = ref(null)
const chartModalidad  = ref(null)
const chartAntiguedad = ref(null)
let instSexo = null, instContrato = null, instModalidad = null, instAntiguedad = null

const sortDir = ref(null) // null | 'desc' | 'asc'
function toggleSort() {
  sortDir.value = sortDir.value === 'desc' ? 'asc' : sortDir.value === 'asc' ? null : 'desc'
}

const tiposSangre = ['A+','A-','B+','B-','AB+','AB-','O+','O-']

const filtrosIniciales = () => ({
  busqueda: '', id_depto: '', estado: 'ACTIVO',
  tipo_contrato: '', modalidad_laboral: '', modalidad_marcacion: '',
  sexo: '', tipo_sangre: '',
  grupo_vulnerable_id: '', grupo_prioritario_id: '',
  tiene_discapacidad: '', tiene_enfermedad: '',
  con_guarderia: '', sustituta_filter: '', sercop_filter: '',
  puede_vehiculo: '',
  motivo_salida: '',
  es_comisionado_entrante: '',
  antiguedad: '',
})
const filtros = ref(filtrosIniciales())

const filtrosActivos = computed(() => {
  const base = filtrosIniciales()
  return Object.keys(filtros.value).filter(k => filtros.value[k] !== base[k] && filtros.value[k] !== '').length
})

const totalPaginas      = computed(() => Math.max(1, Math.ceil(empleados.value.length / porPagina.value)))
const empleadosOrdenados = computed(() => {
  if (!sortDir.value) return empleados.value
  return [...empleados.value].sort((a, b) => {
    const va = a.anios_servicio ?? -1
    const vb = b.anios_servicio ?? -1
    return sortDir.value === 'desc' ? vb - va : va - vb
  })
})

const empleadosPaginados = computed(() => {
  const s = (paginaActual.value - 1) * porPagina.value
  return empleadosOrdenados.value.slice(s, s + porPagina.value)
})

watch(porPagina, () => paginaActual.value = 1)
watch(filtros, () => { puedeExportar.value = false }, { deep: true })

const alertaCards = computed(() => {
  if (!resumen.value) return []
  return [
    { key: 'sercop_vencido',    titulo: 'SERCOP Vencido',    desc: 'Certificado expirado',         bg: 'bg-red-50 border-red-500',    label: 'text-red-700',    num: 'text-red-600',    filtro: { sercop_filter: 'vencido' } },
    { key: 'sercop_proximo',    titulo: 'SERCOP Próximo',    desc: 'Vence en 30 días',              bg: 'bg-amber-50 border-amber-500', label: 'text-amber-700',  num: 'text-amber-600',  filtro: { sercop_filter: 'proximo' } },
    { key: 'sustituta_vencida', titulo: 'Sustituta Vencida', desc: 'Doc. de sustituta expirado',   bg: 'bg-red-50 border-red-500',    label: 'text-red-700',    num: 'text-red-600',    filtro: { sustituta_filter: 'vencida' } },
    { key: 'sustituta_proxima', titulo: 'Sustituta Próxima', desc: 'Vence en 30 días',              bg: 'bg-amber-50 border-amber-500', label: 'text-amber-700',  num: 'text-amber-600',  filtro: { sustituta_filter: 'proxima' } },
    { key: 'guarderia',         titulo: 'Derecho Guardería', desc: 'Hijos menores de 5 años',      bg: 'bg-teal-50 border-teal-500',  label: 'text-teal-700',   num: 'text-teal-600',   filtro: { con_guarderia: '1' } },
  ]
})

function aplicarAlerta(filtro) {
  filtros.value = { ...filtrosIniciales(), ...filtro }
  buscar()
}

function limpiar() {
  filtros.value = filtrosIniciales()
  empleados.value = []
  buscado.value = false
  puedeExportar.value = false
  paginaActual.value = 1
}

async function buscar() {
  cargando.value   = true
  buscado.value    = false
  puedeExportar.value = false
  errorBuscar.value = ''
  paginaActual.value = 1
  try {
    const params = {}
    Object.entries(filtros.value).forEach(([k, v]) => { if (v !== '') params[k] = v })
    const { data } = await api.get('/empleados/reporte', { params })
    empleados.value = data
    buscado.value   = true
    puedeExportar.value = data.length > 0
  } catch (e) {
    errorBuscar.value = e.response?.data?.message || 'Error al consultar. Revisa la consola.'
    console.error(e)
  } finally {
    cargando.value = false
  }
}

async function exportar(formato) {
  exportando.value = true
  try {
    const params = { formato }
    Object.entries(filtros.value).forEach(([k, v]) => { if (v !== '') params[k] = v })
    const { data } = await api.get('/empleados/reporte', { params, responseType: 'blob' })
    const tipo = formato === 'excel'
      ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
      : 'application/pdf'
    const blob = new Blob([data], { type: tipo })
    const url  = URL.createObjectURL(blob)
    if (formato === 'pdf') {
      window.open(url, '_blank')
      setTimeout(() => URL.revokeObjectURL(url), 60000)
    } else {
      const a = document.createElement('a')
      a.href = url; a.download = 'nomina_personal.xlsx'; a.click()
      setTimeout(() => URL.revokeObjectURL(url), 5000)
    }
  } catch (e) {
    console.error(e)
  } finally {
    exportando.value = false
  }
}

function sercop_badge(fecha) {
  if (!fecha) return ''
  const hoy   = new Date()
  const vence = new Date(fecha + 'T00:00:00')
  const diff  = Math.ceil((vence - hoy) / 86400000)
  if (diff < 0)   return 'bg-red-100 text-red-700'
  if (diff <= 30) return 'bg-amber-100 text-amber-700'
  return 'bg-green-100 text-green-700'
}

function destruir(inst) { if (inst) { inst.destroy(); return null } return null }

function crearChartBarHorizontal(canvas, datos, colores) {
  return new Chart(canvas, {
    type: 'bar',
    data: {
      labels: datos.map(d => d.label),
      datasets: [{ data: datos.map(d => d.total), backgroundColor: colores, borderRadius: 4 }],
    },
    options: {
      indexAxis: 'y',
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: ctx => ` ${ctx.parsed.x} empleado(s)` } },
      },
      scales: {
        x: { beginAtZero: true, ticks: { stepSize: 1 } },
        y: { ticks: { font: { size: 11 } } },
      },
    },
  })
}

function crearChartDonut(canvas, datos, colores) {
  return new Chart(canvas, {
    type: 'doughnut',
    data: {
      labels: datos.map(d => d.label),
      datasets: [{ data: datos.map(d => d.total), backgroundColor: colores, borderWidth: 2 }],
    },
    options: {
      responsive: true, maintainAspectRatio: false, cutout: '65%',
      plugins: {
        legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 10 } },
        tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed}` } },
      },
    },
  })
}

function crearChartBar(canvas, datos, color) {
  return new Chart(canvas, {
    type: 'bar',
    data: {
      labels: datos.map(d => d.label),
      datasets: [{ data: datos.map(d => d.total), backgroundColor: color, borderRadius: 4 }],
    },
    options: {
      indexAxis: 'y',
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => ` ${ctx.parsed.x} empleados` } } },
      scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } }, y: { ticks: { font: { size: 10 } } } },
    },
  })
}

async function renderCharts() {
  if (!resumen.value) return
  await nextTick()
  instSexo      = destruir(instSexo)
  instContrato  = destruir(instContrato)
  instModalidad = destruir(instModalidad)
  instAntiguedad= destruir(instAntiguedad)
  const COLORES_SEXO = ['#0b5447','#d97706','#6b7280']
  const COLORES_ANT  = ['#9ca3af','#4ade80','#2dd4bf','#3b82f6','#8b5cf6']
  if (chartSexo.value)       instSexo       = crearChartDonut(chartSexo.value,           resumen.value.stats.por_sexo,       COLORES_SEXO)
  if (chartContrato.value)   instContrato   = crearChartBar(chartContrato.value,        resumen.value.stats.por_contrato,   '#2563eb')
  if (chartModalidad.value)  instModalidad  = crearChartBar(chartModalidad.value,       resumen.value.stats.por_modalidad,  '#0b5447')
  if (chartAntiguedad.value) instAntiguedad = crearChartBarHorizontal(chartAntiguedad.value, resumen.value.stats.por_antiguedad, COLORES_ANT)
}

onMounted(async () => {
  const [{ data: res }, { data: deptos }, { data: cats }] = await Promise.all([
    api.get('/empleados/reporte/resumen'),
    api.get('/departamentos'),
    api.get('/empleados/catalogos-sociales'),
  ])
  resumen.value       = res
  departamentos.value = deptos
  catalogos.value     = cats
  await renderCharts()
})
</script>
