<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Horas Extras</h1>
    </div>

    <!-- Pestañas -->
    <div class="flex border-b mb-6 gap-1 flex-wrap">
      <button
        v-for="tab in tabsVisibles"
        :key="tab.key"
        @click="tabActiva = tab.key"
        :class="tabActiva === tab.key
          ? 'border-b-2 border-[#00372e] text-[#00372e] font-semibold'
          : 'text-gray-500 hover:text-gray-700'"
        class="px-4 py-2 text-sm transition">
        {{ tab.label }}
      </button>
    </div>

    <!-- ══════════════════ TAB: MI PLANIFICACIÓN ══════════════════ -->
    <div v-if="tabActiva === 'mi-plan'">
      <!-- Selector mes/año -->
      <div class="flex gap-4 mb-4 items-end">
        <div>
          <label class="block text-xs text-gray-500 mb-1">Mes</label>
          <select v-model="filtro.mes" @change="cargarMiPlanificacion"
            class="border rounded-lg px-3 py-2 text-sm">
            <option v-for="m in meses" :key="m.v" :value="m.v">{{ m.l }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Año</label>
          <select v-model="filtro.anio" @change="cargarMiPlanificacion"
            class="border rounded-lg px-3 py-2 text-sm">
            <option v-for="a in anios" :key="a" :value="a">{{ a }}</option>
          </select>
        </div>
      </div>

      <!-- Cargando -->
      <div v-if="cargandoPlan" class="text-center py-10 text-gray-400 text-sm">
        Cargando...
      </div>

      <!-- Sin planificación -->
      <div v-else-if="!miPlan">
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-6 text-center">
          <p class="text-blue-700 mb-4">
            No tienes planificación de horas extras para {{ mesNombre(filtro.mes) }} {{ filtro.anio }}.
          </p>
          <button @click="abrirModalNuevaPlan"
            class="bg-[#00372e] text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-800">
            + Crear Planificación
          </button>
        </div>
      </div>

      <!-- Con planificación -->
      <div v-else-if="miPlan">
        <!-- Estado banner -->
        <div :class="{
          'bg-yellow-50 border-yellow-300 text-yellow-800':  miPlan.estado === 'PENDIENTE',
          'bg-green-50 border-green-300 text-green-800':    miPlan.estado === 'APROBADO',
          'bg-red-50 border-red-300 text-red-800':           miPlan.estado === 'NEGADO',
          'bg-purple-50 border-purple-300 text-purple-800': miPlan.estado === 'PROCESADO',
        }" class="border rounded-xl p-4 mb-4 flex justify-between items-start">
          <div>
            <span class="font-semibold">Estado: {{ miPlan.estado }}</span>
            <span v-if="miPlan.observacion" class="block text-sm mt-1">
              Observación: {{ miPlan.observacion }}
            </span>
          </div>
          <div class="flex gap-2 flex-wrap justify-end">
            <button v-if="miPlan.estado === 'PENDIENTE'" @click="abrirModalEditarPlan"
              class="text-xs bg-white border border-gray-300 px-3 py-1.5 rounded-lg hover:bg-gray-50">
              Editar
            </button>
            <button v-if="miPlan.estado === 'PENDIENTE'" @click="eliminarPlan"
              class="text-xs bg-white border border-red-300 text-red-600 px-3 py-1.5 rounded-lg hover:bg-red-50">
              Eliminar
            </button>
            <button v-if="['APROBADO','PROCESADO'].includes(miPlan.estado)" @click="descargarPdf"
              class="text-xs bg-white border border-gray-300 px-3 py-1.5 rounded-lg hover:bg-gray-50">
              Generar PDF
            </button>
            <button v-if="['APROBADO','PROCESADO'].includes(miPlan.estado)" @click="abrirSubirFirmado"
              class="text-xs bg-white border border-blue-300 text-blue-700 px-3 py-1.5 rounded-lg hover:bg-blue-50">
              Subir PDF Firmado
            </button>
            <button v-if="miPlan.pdf_aprobado" @click="descargarFirmado"
              class="text-xs bg-white border border-green-300 text-green-700 px-3 py-1.5 rounded-lg hover:bg-green-50">
              Descargar Firmado
            </button>
          </div>
        </div>

        <!-- Tabla de actividades -->
        <div class="bg-white rounded-xl shadow overflow-hidden mb-4">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
              <tr>
                <th class="text-left px-6 py-3 text-gray-600 font-medium">N°</th>
                <th class="text-left px-6 py-3 text-gray-600 font-medium">Actividad</th>
                <th class="text-right px-6 py-3 text-gray-600 font-medium">H. Extraordinarias</th>
                <th class="text-right px-6 py-3 text-gray-600 font-medium">H. Suplementarias</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(det, i) in miPlan.detalles" :key="det.id"
                class="border-b hover:bg-gray-50">
                <td class="px-6 py-3 text-gray-400">{{ i + 1 }}</td>
                <td class="px-6 py-3">{{ det.actividad }}</td>
                <td class="px-6 py-3 text-right">{{ hhmm(det.horas_extraordinarias) }}</td>
                <td class="px-6 py-3 text-right">{{ hhmm(det.horas_suplementarias) }}</td>
              </tr>
              <tr class="bg-gray-50 font-semibold">
                <td colspan="2" class="px-6 py-3 text-right">TOTAL</td>
                <td class="px-6 py-3 text-right">{{ hhmm(miPlan.total_extraordinarias) }}</td>
                <td class="px-6 py-3 text-right">{{ hhmm(miPlan.total_suplementarias) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ══════════════════ TAB: MIS HORAS TRABAJADAS ══════════════════ -->
    <div v-if="tabActiva === 'mis-horas'">
      <div class="flex gap-4 mb-4 items-end">
        <div>
          <label class="block text-xs text-gray-500 mb-1">Mes</label>
          <select v-model="filtro.mes" @change="cargarMisHoras"
            class="border rounded-lg px-3 py-2 text-sm">
            <option v-for="m in meses" :key="m.v" :value="m.v">{{ m.l }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Año</label>
          <select v-model="filtro.anio" @change="cargarMisHoras"
            class="border rounded-lg px-3 py-2 text-sm">
            <option v-for="a in anios" :key="a" :value="a">{{ a }}</option>
          </select>
        </div>
      </div>

      <!-- Resumen del plan -->
      <div v-if="misHorasData.planificacion"
        class="bg-green-50 border border-green-200 rounded-xl p-4 mb-4">
        <div class="flex justify-between items-start">
          <div>
            <p class="font-semibold text-green-800">Planificación {{ misHorasData.planificacion.estado }}</p>
            <p class="text-sm text-green-700 mt-1">
              Planificado: <b>{{ hhmm(misHorasData.planificacion.total_extraordinarias) }}</b> extraordinarias,
              <b>{{ hhmm(misHorasData.planificacion.total_suplementarias) }}</b> suplementarias
            </p>
            <p class="text-sm text-green-700">
              Declarado: <b>{{ hhmm(totalDeclarado.extra) }}</b> extraordinarias,
              <b>{{ hhmm(totalDeclarado.supl) }}</b> suplementarias
            </p>
            <p class="text-sm text-green-700">
              Disponible: <b>{{ hhmm(misHorasData.planificacion.total_extraordinarias - totalDeclarado.extra) }}</b> extraordinarias,
              <b>{{ hhmm(misHorasData.planificacion.total_suplementarias - totalDeclarado.supl) }}</b> suplementarias
            </p>
          </div>
          <div class="flex flex-col gap-2 items-end">
            <button
              v-if="misHorasData.planificacion.estado === 'PROCESADO' && esMesActual(misHorasData.planificacion) && !misHorasData.registros.some(r => r.estado === 'NEGADO')"
              @click="abrirModalRegistrar"
              class="bg-[#00372e] text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-800">
              + Registrar horas
            </button>
            <p v-else-if="misHorasData.planificacion.estado === 'PROCESADO' && misHorasData.registros.some(r => r.estado === 'NEGADO')"
              class="text-xs text-red-600 italic">
              Registro negado. Debe volver a planificar.
            </p>
            <p v-else-if="misHorasData.planificacion.estado === 'PROCESADO' && !esMesActual(misHorasData.planificacion)"
              class="text-xs text-gray-500 italic">
              Registro habilitado en {{ mesNombre(misHorasData.planificacion.mes) }} {{ misHorasData.planificacion.anio }}
            </p>
            <button
              v-if="misHorasData.registros.some(r => r.estado === 'APROBADO')"
              @click="descargarPdfRegistros"
              class="text-xs bg-white border border-gray-300 px-3 py-1.5 rounded-lg hover:bg-gray-50">
              Generar PDF
            </button>
          </div>
        </div>
      </div>
      <div v-else class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 mb-4 text-yellow-800 text-sm">
        No hay una planificación aprobada para este mes. Primero crea y aprueba tu planificación.
      </div>

      <!-- Tabla de registros -->
      <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-left px-6 py-3 text-gray-600 font-medium">Fecha</th>
              <th class="text-left px-6 py-3 text-gray-600 font-medium">Descripción</th>
              <th class="text-right px-6 py-3 text-gray-600 font-medium">H. Extra.</th>
              <th class="text-right px-6 py-3 text-gray-600 font-medium">H. Supl.</th>
              <th class="text-left px-6 py-3 text-gray-600 font-medium">Estado</th>
              <th class="text-left px-6 py-3 text-gray-600 font-medium"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!misHorasData.registros.length">
              <td colspan="6" class="px-6 py-8 text-center text-gray-400">
                No hay registros para este mes
              </td>
            </tr>
            <tr v-for="reg in misHorasData.registros" :key="reg.id"
              class="border-b hover:bg-gray-50">
              <td class="px-6 py-3">{{ formatFecha(reg.fecha) }}</td>
              <td class="px-6 py-3">{{ reg.descripcion || '-' }}</td>
              <td class="px-6 py-3 text-right">{{ hhmm(reg.horas_extraordinarias) }}</td>
              <td class="px-6 py-3 text-right">{{ hhmm(reg.horas_suplementarias) }}</td>
              <td class="px-6 py-3">
                <span :class="{
                  'bg-blue-100 text-blue-800':     reg.estado === 'EN REVISION',
                  'bg-yellow-100 text-yellow-800': reg.estado === 'PENDIENTE',
                  'bg-green-100 text-green-800':   reg.estado === 'APROBADO',
                  'bg-red-100 text-red-800':        reg.estado === 'NEGADO',
                }" class="px-2 py-0.5 rounded-full text-xs font-medium">
                  {{ reg.estado }}
                </span>
                <span v-if="reg.observacion" class="block text-xs text-red-500 mt-0.5 italic">
                  {{ reg.observacion }}
                </span>
              </td>
              <td class="px-6 py-3">
                <button v-if="reg.estado === 'EN REVISION'"
                  @click="abrirEditarRegistro(reg)"
                  class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                  Editar
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ══════════════════ TAB: PLANIFICACIONES DEL EQUIPO ══════════════════ -->
    <div v-if="tabActiva === 'equipo-plan'">
      <div class="flex gap-4 mb-4 items-end">
        <div>
          <label class="block text-xs text-gray-500 mb-1">Mes</label>
          <select v-model="filtro.mes" @change="cargarEquipoPlan"
            class="border rounded-lg px-3 py-2 text-sm">
            <option v-for="m in meses" :key="m.v" :value="m.v">{{ m.l }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Año</label>
          <select v-model="filtro.anio" @change="cargarEquipoPlan"
            class="border rounded-lg px-3 py-2 text-sm">
            <option v-for="a in anios" :key="a" :value="a">{{ a }}</option>
          </select>
        </div>
      </div>

      <div class="bg-white rounded-xl shadow overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-left px-6 py-3 text-gray-600 font-medium">Empleado</th>
              <th class="text-left px-6 py-3 text-gray-600 font-medium">Departamento</th>
              <th class="text-right px-6 py-3 text-gray-600 font-medium">H. Extra.</th>
              <th class="text-right px-6 py-3 text-gray-600 font-medium">H. Supl.</th>
              <th class="text-left px-6 py-3 text-gray-600 font-medium">Estado</th>
              <th class="text-left px-6 py-3 text-gray-600 font-medium">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="cargandoEquipo">
              <td colspan="6" class="px-6 py-8 text-center text-gray-400">Cargando...</td>
            </tr>
            <tr v-else-if="!equipoPlan.length">
              <td colspan="6" class="px-6 py-8 text-center text-gray-400">
                No hay planificaciones para este mes
              </td>
            </tr>
            <tr v-for="plan in equipoPlan" :key="plan.id"
              class="border-b hover:bg-gray-50">
              <td class="px-6 py-3 font-medium">
                {{ plan.empleado?.apellido_emp }} {{ plan.empleado?.nombre_emp }}
                <span class="block text-xs text-gray-400">{{ plan.empleado?.identificacion }}</span>
              </td>
              <td class="px-6 py-3 text-sm text-gray-600">
                {{ plan.empleado?.departamento?.nombre_depto || '-' }}
              </td>
              <td class="px-6 py-3 text-right">{{ plan.total_extraordinarias }}</td>
              <td class="px-6 py-3 text-right">{{ plan.total_suplementarias }}</td>
              <td class="px-6 py-3">
                <span :class="{
                  'bg-yellow-100 text-yellow-800':  plan.estado === 'PENDIENTE',
                  'bg-green-100 text-green-800':    plan.estado === 'APROBADO',
                  'bg-red-100 text-red-800':         plan.estado === 'NEGADO',
                  'bg-purple-100 text-purple-800':  plan.estado === 'PROCESADO',
                }" class="px-2 py-0.5 rounded-full text-xs font-medium">
                  {{ plan.estado }}
                </span>
              </td>
              <td class="px-6 py-3 flex gap-2">
                <button v-if="plan.estado === 'PENDIENTE'"
                  @click="aprobarPlan(plan.id)"
                  class="text-xs text-green-700 hover:text-green-900 font-medium">
                  Aprobar
                </button>
                <button v-if="plan.estado === 'PENDIENTE'"
                  @click="abrirModalNegarPlan(plan)"
                  class="text-xs text-red-600 hover:text-red-800 font-medium">
                  Negar
                </button>
                <button v-if="plan.estado === 'APROBADO' && esTHNomina"
                  @click="abrirModalAutorizar(plan)"
                  class="text-xs text-purple-700 hover:text-purple-900 font-medium">
                  Procesar
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ══════════════════ TAB: REGISTROS DEL EQUIPO ══════════════════ -->
    <div v-if="tabActiva === 'equipo-horas'">
      <!-- Filtros -->
      <div class="flex gap-3 mb-4 items-end flex-wrap">
        <div>
          <label class="block text-xs text-gray-500 mb-1">Mes</label>
          <select v-model="filtro.mes" @change="cargarEquipoHoras" class="border rounded-lg px-3 py-2 text-sm">
            <option v-for="m in meses" :key="m.v" :value="m.v">{{ m.l }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Año</label>
          <select v-model="filtro.anio" @change="cargarEquipoHoras" class="border rounded-lg px-3 py-2 text-sm">
            <option v-for="a in anios" :key="a" :value="a">{{ a }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs text-gray-500 mb-1">Estado</label>
          <select v-model="filtroEstadoReg" @change="cargarEquipoHoras" class="border rounded-lg px-3 py-2 text-sm">
            <option value="">Todos</option>
            <option value="EN REVISION">En Revisión</option>
            <option value="PENDIENTE">Pendiente</option>
            <option value="APROBADO">Aprobado</option>
            <option value="NEGADO">Negado</option>
          </select>
        </div>
        <div class="flex-1 min-w-[200px]">
          <label class="block text-xs text-gray-500 mb-1">Buscar empleado</label>
          <input v-model="filtroEmpleado" type="text" placeholder="Nombre o apellido..."
            class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#579186] outline-none" />
        </div>
      </div>

      <!-- Sin resultados -->
      <div v-if="!equipoHorasAgrupado.length" class="bg-white rounded-xl shadow p-8 text-center text-gray-400 text-sm">
        No hay registros para este mes
      </div>

      <!-- Grupos por empleado -->
      <div v-for="grupo in equipoHorasAgrupado" :key="grupo.id_emp" class="bg-white rounded-xl shadow overflow-hidden mb-4">
        <!-- Cabecera del empleado -->
        <div class="bg-[#00372e] text-white px-6 py-3">
          <div class="font-semibold text-sm mb-1">{{ grupo.nombre }}</div>
          <div class="text-xs flex flex-wrap gap-4 mt-1">
            <span>
              H. Extraordinarias:
              <b>{{ hhmm(grupo.registros.filter(r=>r.estado==='APROBADO').reduce((s,r)=>s+parseFloat(r.horas_extraordinarias||0),0)) }}</b>
              <span v-if="esTHNomina"> → <b>${{ grupo.registros.filter(r=>r.estado==='APROBADO').reduce((s,r)=>s+parseFloat(r.valor_extraordinarias||0),0).toFixed(2) }}</b></span>
            </span>
            <span>
              H. Suplementarias:
              <b>{{ hhmm(grupo.registros.filter(r=>r.estado==='APROBADO').reduce((s,r)=>s+parseFloat(r.horas_suplementarias||0),0)) }}</b>
              <span v-if="esTHNomina"> → <b>${{ grupo.registros.filter(r=>r.estado==='APROBADO').reduce((s,r)=>s+parseFloat(r.valor_suplementarias||0),0).toFixed(2) }}</b></span>
            </span>
            <span v-if="esTHNomina" class="text-green-300 font-bold">
              Total a Pagar: ${{ grupo.registros.filter(r=>r.estado==='APROBADO'&&r.valor_total!==undefined).reduce((s,r)=>s+parseFloat(r.valor_total||0),0).toFixed(2) }}
            </span>
          </div>
        </div>

        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b">
            <tr>
              <th class="text-left px-4 py-2 text-gray-600 font-medium">Fecha</th>
              <th class="text-left px-4 py-2 text-gray-600 font-medium">Descripción</th>
              <th class="text-right px-4 py-2 text-gray-600 font-medium">H. Extra.</th>
              <th class="text-right px-4 py-2 text-gray-600 font-medium">H. Supl.</th>
              <th class="text-left px-4 py-2 text-gray-600 font-medium">Estado</th>
              <th class="text-left px-4 py-2 text-gray-600 font-medium">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="reg in grupo.registros" :key="reg.id" class="border-b hover:bg-gray-50">
              <td class="px-4 py-2">{{ formatFecha(reg.fecha) }}</td>
              <td class="px-4 py-2 text-gray-600">{{ reg.descripcion || '-' }}</td>
              <td class="px-4 py-2 text-right">{{ hhmm(reg.horas_extraordinarias) }}</td>
              <td class="px-4 py-2 text-right">{{ hhmm(reg.horas_suplementarias) }}</td>
              <td class="px-4 py-2">
                <span :class="{
                  'bg-blue-100 text-blue-800':     reg.estado === 'EN REVISION',
                  'bg-yellow-100 text-yellow-800': reg.estado === 'PENDIENTE',
                  'bg-green-100 text-green-800':   reg.estado === 'APROBADO',
                  'bg-red-100 text-red-800':        reg.estado === 'NEGADO',
                }" class="px-2 py-0.5 rounded-full text-xs font-medium">{{ reg.estado }}</span>
                <div v-if="reg.estado === 'APROBADO' && esTHNomina && reg.valor_total !== undefined"
                  class="mt-1 text-xs space-y-0.5">
                  <div class="text-gray-500">Extra: <b>${{ reg.valor_extraordinarias }}</b> | Supl: <b>${{ reg.valor_suplementarias }}</b></div>
                  <div class="font-semibold text-green-700">Total: ${{ reg.valor_total }}</div>
                </div>
              </td>
              <td class="px-4 py-2 flex gap-2 flex-wrap">
                <button v-if="reg.estado === 'EN REVISION' && esTHNomina"
                  @click="aprobarRevision(reg.id)"
                  class="text-xs text-blue-700 hover:text-blue-900 font-medium">Aprobar revisión</button>
                <button v-if="reg.estado === 'EN REVISION' && esTHNomina"
                  @click="abrirModalDevolverRegistro(reg)"
                  class="text-xs text-orange-600 hover:text-orange-800 font-medium">Devolver</button>
                <button v-if="reg.estado === 'PENDIENTE' && !esTHNomina"
                  @click="confirmarRegistro(reg.id)"
                  class="text-xs text-green-700 hover:text-green-900 font-medium">Confirmar</button>
                <button v-if="reg.estado === 'PENDIENTE' && !esTHNomina"
                  @click="abrirModalNegarRegistro(reg)"
                  class="text-xs text-red-600 hover:text-red-800 font-medium">Negar</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Totales generales (TH NOMINA) -->
      <div v-if="esTHNomina && equipoHoras.length" class="bg-gray-100 rounded-xl p-4 flex justify-end gap-8 text-sm font-semibold">
        <span>Total H. Extraordinarias: <b>{{ hhmm(totalEquipoHoras.extra) }}</b></span>
        <span>Total H. Suplementarias: <b>{{ hhmm(totalEquipoHoras.supl) }}</b></span>
        <span class="text-green-700">Total a Pagar: <b>${{ totalEquipoHoras.valor.toFixed(2) }}</b></span>
      </div>
    </div>

    <!-- ══════════════════ MODALES ══════════════════ -->

    <!-- Modal: Crear/Editar planificación -->
    <div v-if="modalPlan.show"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
        <h2 class="text-lg font-bold mb-4">{{ modalPlan.editando ? 'Editar' : 'Nueva' }} Planificación de Horas Extras</h2>
        <p class="text-sm text-gray-500 mb-4">{{ mesNombre(filtro.mes) }} {{ filtro.anio }}</p>

        <div class="space-y-3 mb-4">
          <div v-for="(det, i) in modalPlan.detalles" :key="i"
            class="border rounded-lg p-3 bg-gray-50">
            <div class="flex justify-between items-start mb-2">
              <span class="text-xs font-semibold text-gray-600">Actividad {{ i + 1 }}</span>
              <button v-if="modalPlan.detalles.length > 1" @click="quitarDetalle(i)"
                class="text-red-400 hover:text-red-600 text-xs">✕ Quitar</button>
            </div>
            <div class="mb-2">
              <label class="block text-xs text-gray-600 mb-1">Actividad a realizar *</label>
              <input v-model="det.actividad" type="text" maxlength="300"
                placeholder="Describe la actividad de forma general"
                class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-[#579186] outline-none" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs text-gray-600 mb-1">H. Extraordinarias (hasta 11:59 PM)</label>
                <input v-model="det.horas_extraordinarias" type="number" step="0.5" min="0"
                  placeholder="0"
                  class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-[#579186] outline-none" />
              </div>
              <div>
                <label class="block text-xs text-gray-600 mb-1">H. Suplementarias (desde 00:00 / fines)</label>
                <input v-model="det.horas_suplementarias" type="number" step="0.5" min="0"
                  placeholder="0"
                  class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-[#579186] outline-none" />
              </div>
            </div>
          </div>
        </div>

        <button @click="agregarDetalle"
          class="text-sm text-[#00372e] hover:text-blue-800 font-medium mb-4">
          + Agregar otra actividad
        </button>

        <!-- Totales preview -->
        <div class="bg-gray-100 rounded-lg p-3 text-sm mb-4">
          <div class="flex justify-between">
            <span>Total H. Extraordinarias:</span>
            <strong>{{ hhmm(totalModalExtra) }}</strong>
          </div>
          <div class="flex justify-between mt-1">
            <span>Total H. Suplementarias:</span>
            <strong>{{ hhmm(totalModalSupl) }}</strong>
          </div>
        </div>

        <div v-if="errorModal" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm mb-4">
          {{ errorModal }}
        </div>

        <div class="flex justify-end gap-3">
          <button @click="modalPlan.show = false"
            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">
            Cancelar
          </button>
          <button @click="guardarPlan" :disabled="guardando"
            class="bg-[#00372e] text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-800 disabled:opacity-50">
            {{ guardando ? 'Guardando...' : 'Guardar' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal: Registrar horas reales -->
    <div v-if="modalRegistro.show"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-bold mb-4">Registrar Horas Trabajadas</h2>

        <div class="space-y-3 mb-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha *</label>
            <input v-model="modalRegistro.form.fecha" type="date"
              @change="calcularPreview"
              class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#579186] outline-none" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Hora inicio *</label>
              <input v-model="modalRegistro.form.hora_inicio" type="time"
                @change="calcularPreview"
                class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#579186] outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Hora fin *</label>
              <input v-model="modalRegistro.form.hora_fin" type="time"
                @change="calcularPreview"
                class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#579186] outline-none" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
            <textarea v-model="modalRegistro.form.descripcion" maxlength="300" rows="2"
              placeholder="Describe brevemente el trabajo realizado"
              class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#579186] outline-none text-sm">
            </textarea>
          </div>
        </div>

        <!-- Preview cálculo -->
        <div v-if="modalRegistro.preview" class="bg-green-50 border border-green-200 rounded-lg p-3 text-sm mb-3">
          <p class="font-medium text-green-800 mb-1">Desglose calculado:</p>
          <div class="flex justify-between text-green-700">
            <span>H. Extraordinarias:</span>
            <strong>{{ hhmm(modalRegistro.preview.horas_extraordinarias) }}</strong>
          </div>
          <div class="flex justify-between text-green-700">
            <span>H. Suplementarias:</span>
            <strong>{{ hhmm(modalRegistro.preview.horas_suplementarias) }}</strong>
          </div>
        </div>

        <!-- Disponible -->
        <div class="bg-blue-50 rounded-lg p-3 text-xs text-blue-700 mb-4">
          Disponible este mes:
          <b>{{ hhmm((misHorasData.planificacion?.total_extraordinarias || 0) - totalDeclarado.extra) }}</b> extraordinarias,
          <b>{{ hhmm((misHorasData.planificacion?.total_suplementarias || 0) - totalDeclarado.supl) }}</b> suplementarias
        </div>

        <div v-if="errorModal" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm mb-4">
          {{ errorModal }}
        </div>

        <div class="flex justify-end gap-3">
          <button @click="modalRegistro.show = false"
            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">
            Cancelar
          </button>
          <button @click="guardarRegistro" :disabled="guardando"
            class="bg-[#00372e] text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-800 disabled:opacity-50">
            {{ guardando ? 'Guardando...' : 'Registrar' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal: Negar planificación -->
    <div v-if="modalNegar.show"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-bold mb-4">Negar Planificación</h2>
        <label class="block text-sm font-medium text-gray-700 mb-1">Observación *</label>
        <textarea v-model="modalNegar.observacion" rows="3" maxlength="250"
          placeholder="Indique la razón"
          class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#579186] outline-none text-sm mb-4">
        </textarea>
        <div v-if="errorModal" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm mb-4">
          {{ errorModal }}
        </div>
        <div class="flex justify-end gap-3">
          <button @click="modalNegar.show = false"
            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
          <button @click="negarPlan" :disabled="guardando"
            class="bg-red-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-red-700 disabled:opacity-50">
            {{ guardando ? 'Negando...' : 'Negar' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal: Negar registro -->
    <div v-if="modalNegarReg.show"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-bold mb-4">Negar Registro</h2>
        <label class="block text-sm font-medium text-gray-700 mb-1">Observación *</label>
        <textarea v-model="modalNegarReg.observacion" rows="3" maxlength="250"
          placeholder="Indique la razón"
          class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#579186] outline-none text-sm mb-4">
        </textarea>
        <div v-if="errorModal" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm mb-4">
          {{ errorModal }}
        </div>
        <div class="flex justify-end gap-3">
          <button @click="modalNegarReg.show = false"
            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
          <button @click="negarRegistroAction" :disabled="guardando"
            class="bg-red-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-red-700 disabled:opacity-50">
            {{ guardando ? 'Negando...' : 'Negar' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal: Editar registro (empleado EN REVISION) -->
    <div v-if="modalEditarReg.show"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-bold mb-4">Editar Registro de Horas</h2>
        <div class="space-y-3 mb-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha *</label>
            <input v-model="modalEditarReg.form.fecha" type="date" @change="calcularPreviewEditar"
              class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#579186] outline-none" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Hora inicio *</label>
              <input v-model="modalEditarReg.form.hora_inicio" type="time" @change="calcularPreviewEditar"
                class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#579186] outline-none" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Hora fin *</label>
              <input v-model="modalEditarReg.form.hora_fin" type="time" @change="calcularPreviewEditar"
                class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#579186] outline-none" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
            <textarea v-model="modalEditarReg.form.descripcion" maxlength="300" rows="2"
              class="w-full border rounded-lg px-3 py-2 focus:ring-2 focus:ring-[#579186] outline-none text-sm"></textarea>
          </div>
        </div>
        <div v-if="modalEditarReg.preview" class="bg-green-50 border border-green-200 rounded-lg p-3 text-sm mb-3">
          <p class="font-medium text-green-800 mb-1">Desglose calculado:</p>
          <div class="flex justify-between text-green-700">
            <span>H. Extraordinarias:</span><strong>{{ hhmm(modalEditarReg.preview.horas_extraordinarias) }}</strong>
          </div>
          <div class="flex justify-between text-green-700">
            <span>H. Suplementarias:</span><strong>{{ hhmm(modalEditarReg.preview.horas_suplementarias) }}</strong>
          </div>
        </div>
        <div v-if="errorModal" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm mb-4">{{ errorModal }}</div>
        <div class="flex justify-end gap-3">
          <button @click="modalEditarReg.show = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
          <button @click="guardarEditarRegistro" :disabled="guardando"
            class="bg-[#00372e] text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-800 disabled:opacity-50">
            {{ guardando ? 'Guardando...' : 'Guardar' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal: Devolver registro (TH NOMINA) -->
    <div v-if="modalDevolverReg.show"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-bold mb-2">Devolver para Corrección</h2>
        <p class="text-sm text-gray-500 mb-4">Indique al empleado qué debe corregir</p>
        <textarea v-model="modalDevolverReg.observacion" rows="3" maxlength="250"
          placeholder="Ej: La hora de inicio no corresponde al rango autorizado..."
          class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-orange-400 outline-none resize-none mb-4"></textarea>
        <div v-if="errorModal" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm mb-4">{{ errorModal }}</div>
        <div class="flex justify-end gap-3">
          <button @click="modalDevolverReg.show = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
          <button @click="devolverRegistro" :disabled="guardando"
            class="bg-orange-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-orange-700 disabled:opacity-50">
            {{ guardando ? 'Enviando...' : 'Devolver' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal: Procesar planificación -->
    <div v-if="modalAutorizar.show"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6">
        <h2 class="text-lg font-bold mb-2">Procesar Planificación</h2>
        <p class="text-sm text-gray-500 mb-4">Ingrese la referencia del memorando de procesamiento</p>
        <textarea v-model="modalAutorizar.memorando" rows="3" maxlength="300"
          placeholder="Ej: Según Memorando nro. CDPIC-DATH-2026-0098-M se autorizó el pago de horas extras."
          class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-400 outline-none resize-none mb-4">
        </textarea>
        <div v-if="errorModal" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm mb-4">
          {{ errorModal }}
        </div>
        <div class="flex justify-end gap-3">
          <button @click="modalAutorizar.show = false"
            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
          <button @click="autorizarPlan" :disabled="guardando"
            class="bg-purple-700 text-white px-5 py-2 rounded-lg text-sm hover:bg-purple-800 disabled:opacity-50">
            {{ guardando ? 'Procesando...' : 'Procesar' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Modal: Subir PDF firmado -->
    <div v-if="modalFirmado.show"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
        <h2 class="text-lg font-bold mb-4">Subir PDF Firmado</h2>
        <input type="file" accept=".pdf" @change="onArchivoFirmado"
          class="w-full text-sm mb-4" />
        <div v-if="errorModal" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm mb-4">
          {{ errorModal }}
        </div>
        <div class="flex justify-end gap-3">
          <button @click="modalFirmado.show = false"
            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
          <button @click="subirFirmado" :disabled="guardando || !modalFirmado.archivo"
            class="bg-[#00372e] text-white px-5 py-2 rounded-lg text-sm hover:bg-blue-800 disabled:opacity-50">
            {{ guardando ? 'Subiendo...' : 'Subir' }}
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from "vue"
import api from "@/services/api"

const esSupervisorOAdmin = ref(false)
const esTHNomina         = ref(false)

const MESES = [
  { v: 1, l: "Enero" }, { v: 2, l: "Febrero" }, { v: 3, l: "Marzo" },
  { v: 4, l: "Abril" }, { v: 5, l: "Mayo" }, { v: 6, l: "Junio" },
  { v: 7, l: "Julio" }, { v: 8, l: "Agosto" }, { v: 9, l: "Septiembre" },
  { v: 10, l: "Octubre" }, { v: 11, l: "Noviembre" }, { v: 12, l: "Diciembre" },
]
const meses = MESES
const anioActual = new Date().getFullYear()
const anios = [anioActual - 1, anioActual, anioActual + 1]

const tabs = [
  { key: "mi-plan",    label: "Mi Planificación" },
  { key: "mis-horas",  label: "Mis Horas Trabajadas" },
  { key: "equipo-plan",  label: "Planificaciones del Equipo", soloSuperv: true },
  { key: "equipo-horas", label: "Registros del Equipo",        soloSuperv: true },
]
const tabsVisibles = computed(() =>
  tabs.filter(t => !t.soloSuperv || esSupervisorOAdmin.value)
)
const tabActiva = ref("mi-plan")

const filtro = ref({ mes: new Date().getMonth() + 1, anio: anioActual })
const filtroEstadoReg  = ref("")
const filtroEmpleado   = ref("")

// ── Estado ──────────────────────────────────────────────────────────────────
const miPlan         = ref(null)
const cargandoPlan   = ref(false)
const misHorasData   = ref({ planificacion: null, registros: [] })
const equipoPlan     = ref([])
const equipoHoras    = ref([])
const cargandoEquipo = ref(false)
const guardando      = ref(false)
const errorModal     = ref("")

// ── Modales ──────────────────────────────────────────────────────────────────
const modalPlan = ref({ show: false, editando: false, detalles: [] })
const modalRegistro  = ref({ show: false, form: {} })
const modalNegar     = ref({ show: false, planId: null, observacion: "" })
const modalAutorizar = ref({ show: false, planId: null, memorando: "" })
const modalNegarReg    = ref({ show: false, regId: null, observacion: "" })
const modalDevolverReg = ref({ show: false, regId: null, observacion: "" })
const modalEditarReg   = ref({ show: false, reg: null, preview: null, form: {} })
const modalFirmado   = ref({ show: false, archivo: null })

// ── Computed ──────────────────────────────────────────────────────────────────
const totalModalExtra = computed(() =>
  modalPlan.value.detalles.reduce((s, d) => s + (parseFloat(d.horas_extraordinarias) || 0), 0)
)
const totalModalSupl = computed(() =>
  modalPlan.value.detalles.reduce((s, d) => s + (parseFloat(d.horas_suplementarias) || 0), 0)
)
const totalDeclarado = computed(() => {
  const regs = misHorasData.value.registros.filter(r => r.estado !== "NEGADO")
  return {
    extra: regs.reduce((s, r) => s + (parseFloat(r.horas_extraordinarias) || 0), 0),
    supl:  regs.reduce((s, r) => s + (parseFloat(r.horas_suplementarias)  || 0), 0),
  }
})

const equipoHorasAgrupado = computed(() => {
  const busq = filtroEmpleado.value.toLowerCase().trim()
  const registros = equipoHoras.value.filter(r => {
    if (!busq) return true
    const nombre = `${r.empleado?.apellido_emp} ${r.empleado?.nombre_emp}`.toLowerCase()
    return nombre.includes(busq)
  })
  const grupos = {}
  for (const reg of registros) {
    const key = reg.id_emp
    if (!grupos[key]) {
      grupos[key] = {
        id_emp: key,
        nombre: `${reg.empleado?.apellido_emp} ${reg.empleado?.nombre_emp}`,
        registros: [],
      }
    }
    grupos[key].registros.push(reg)
  }
  return Object.values(grupos)
})

const totalEquipoHoras = computed(() => {
  const todos = equipoHoras.value
  return {
    extra: todos.filter(r => r.estado !== 'NEGADO').reduce((s, r) => s + parseFloat(r.horas_extraordinarias || 0), 0),
    supl:  todos.filter(r => r.estado !== 'NEGADO').reduce((s, r) => s + parseFloat(r.horas_suplementarias  || 0), 0),
    valor: todos.filter(r => r.estado === 'APROBADO' && r.valor_total !== undefined).reduce((s, r) => s + parseFloat(r.valor_total || 0), 0),
  }
})

// ── Helpers ──────────────────────────────────────────────────────────────────
function hhmm(decimal) {
  const total = Math.round(parseFloat(decimal || 0) * 60)
  const h = Math.floor(total / 60)
  const m = total % 60
  if (h === 0 && m === 0) return '-'
  if (m === 0) return `${h}h`
  if (h === 0) return `${m}m`
  return `${h}h ${m}m`
}

function mesNombre(v) {
  return MESES.find(m => m.v === v)?.l ?? v
}
function formatFecha(f) {
  if (!f) return "-"
  const d = new Date(f + "T12:00:00")
  return d.toLocaleDateString("es-EC", { day: "2-digit", month: "2-digit", year: "numeric" })
}
function detalleVacio() {
  return { actividad: "", horas_extraordinarias: 0, horas_suplementarias: 0 }
}
function esMesActual(plan) {
  const hoy = new Date()
  return hoy.getFullYear() === parseInt(plan.anio) && (hoy.getMonth() + 1) === parseInt(plan.mes)
}

// ── Carga de datos ──────────────────────────────────────────────────────────
async function cargarMiPlanificacion() {
  cargandoPlan.value = true
  try {
    const { data } = await api.get("/horas-extras/mi-planificacion", {
      params: { anio: filtro.value.anio, mes: filtro.value.mes },
    })
    miPlan.value = (data && data.id) ? data : null
  } catch { miPlan.value = null }
  finally { cargandoPlan.value = false }
}

async function cargarMisHoras() {
  try {
    const { data } = await api.get("/horas-extras/mis-registros", {
      params: { anio: filtro.value.anio, mes: filtro.value.mes },
    })
    misHorasData.value = data
  } catch { misHorasData.value = { planificacion: null, registros: [] } }
}

async function cargarEquipoPlan() {
  cargandoEquipo.value = true
  try {
    const { data } = await api.get("/horas-extras/planificacion", {
      params: { anio: filtro.value.anio, mes: filtro.value.mes },
    })
    equipoPlan.value = data
  } catch { equipoPlan.value = [] }
  finally { cargandoEquipo.value = false }
}

async function cargarEquipoHoras() {
  try {
    const params = { anio: filtro.value.anio, mes: filtro.value.mes }
    if (filtroEstadoReg.value) params.estado = filtroEstadoReg.value
    const { data } = await api.get("/horas-extras/equipo-registros", { params })
    equipoHoras.value = data
  } catch { equipoHoras.value = [] }
}

// ── Planificación CRUD ──────────────────────────────────────────────────────
function abrirModalNuevaPlan() {
  errorModal.value = ""
  modalPlan.value = { show: true, editando: false, detalles: [detalleVacio()] }
}
function abrirModalEditarPlan() {
  errorModal.value = ""
  modalPlan.value = {
    show: true,
    editando: true,
    detalles: miPlan.value.detalles.map(d => ({
      actividad: d.actividad,
      horas_extraordinarias: d.horas_extraordinarias,
      horas_suplementarias:  d.horas_suplementarias,
    })),
  }
}
function agregarDetalle() {
  modalPlan.value.detalles.push(detalleVacio())
}
function quitarDetalle(i) {
  modalPlan.value.detalles.splice(i, 1)
}

async function guardarPlan() {
  errorModal.value = ""

  const totalExtra = modalPlan.value.detalles.reduce((s, d) => s + parseFloat(d.horas_extraordinarias || 0), 0)
  const totalSupl  = modalPlan.value.detalles.reduce((s, d) => s + parseFloat(d.horas_suplementarias  || 0), 0)
  if (totalExtra > 20) { errorModal.value = "Las horas extraordinarias no pueden superar las 20 horas mensuales."; return }
  if (totalSupl  > 20) { errorModal.value = "Las horas suplementarias no pueden superar las 20 horas mensuales."; return }

  guardando.value  = true
  try {
    const payload = {
      anio:     filtro.value.anio,
      mes:      filtro.value.mes,
      detalles: modalPlan.value.detalles,
    }
    if (modalPlan.value.editando) {
      await api.put("/horas-extras/planificacion/" + miPlan.value.id, payload)
    } else {
      await api.post("/horas-extras/planificacion", payload)
    }
    modalPlan.value.show = false
    await cargarMiPlanificacion()
  } catch (e) {
    errorModal.value = e.response?.data?.message || "Error al guardar la planificación"
  } finally { guardando.value = false }
}

async function eliminarPlan() {
  if (!confirm("¿Está seguro de eliminar esta planificación?")) return
  try {
    await api.delete("/horas-extras/planificacion/" + miPlan.value.id)
    miPlan.value = null
  } catch (e) {
    alert(e.response?.data?.message || "Error al eliminar")
  }
}

// ── PDF ──────────────────────────────────────────────────────────────────────
async function descargarPdfRegistros() {
  const cab = misHorasData.value.planificacion
  if (!cab) return
  try {
    const resp = await api.get("/horas-extras/planificacion/" + cab.id + "/pdf-registros", {
      responseType: "blob",
    })
    const url = URL.createObjectURL(new Blob([resp.data], { type: "application/pdf" }))
    const a = document.createElement("a")
    a.href = url
    a.download = `horas_trabajadas_${filtro.value.anio}_${filtro.value.mes}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  } catch { alert("Error al generar el PDF") }
}

async function descargarPdf() {
  try {
    const resp = await api.get("/horas-extras/planificacion/" + miPlan.value.id + "/pdf", {
      responseType: "blob",
    })
    const url = URL.createObjectURL(new Blob([resp.data], { type: "application/pdf" }))
    const a = document.createElement("a")
    a.href = url
    a.download = `horas_extras_${filtro.value.anio}_${filtro.value.mes}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  } catch { alert("Error al generar el PDF") }
}

function abrirSubirFirmado() {
  errorModal.value = ""
  modalFirmado.value = { show: true, archivo: null }
}
function onArchivoFirmado(e) {
  modalFirmado.value.archivo = e.target.files[0] || null
}
async function subirFirmado() {
  errorModal.value = ""
  guardando.value  = true
  try {
    const fd = new FormData()
    fd.append("archivo", modalFirmado.value.archivo)
    await api.post("/horas-extras/planificacion/" + miPlan.value.id + "/subir-firmado", fd, {
      headers: { "Content-Type": "multipart/form-data" },
    })
    modalFirmado.value.show = false
    await cargarMiPlanificacion()
  } catch (e) {
    errorModal.value = e.response?.data?.message || "Error al subir el PDF"
  } finally { guardando.value = false }
}

async function descargarFirmado() {
  try {
    const resp = await api.get("/horas-extras/planificacion/" + miPlan.value.id + "/descargar-firmado", {
      responseType: "blob",
    })
    const url = URL.createObjectURL(new Blob([resp.data], { type: "application/pdf" }))
    const a = document.createElement("a")
    a.href = url
    a.download = `horas_extras_firmado_${filtro.value.anio}_${filtro.value.mes}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  } catch { alert("Error al descargar el PDF firmado") }
}

// ── Registro de horas ────────────────────────────────────────────────────────
function abrirModalRegistrar() {
  errorModal.value = ""
  modalRegistro.value = {
    show: true,
    preview: null,
    form: { fecha: "", hora_inicio: "", hora_fin: "", descripcion: "" },
  }
}
async function calcularPreview() {
  const { fecha, hora_inicio, hora_fin } = modalRegistro.value.form
  if (!fecha || !hora_inicio || !hora_fin) return
  try {
    const { data } = await api.get("/horas-extras/calcular", {
      params: { fecha, hora_inicio, hora_fin },
    })
    modalRegistro.value.preview = data
  } catch { modalRegistro.value.preview = null }
}
async function guardarRegistro() {
  errorModal.value = ""
  guardando.value  = true
  try {
    await api.post("/horas-extras/registro", {
      cab_id: misHorasData.value.planificacion.id,
      ...modalRegistro.value.form,
    })
    modalRegistro.value.show = false
    await cargarMisHoras()
  } catch (e) {
    errorModal.value = e.response?.data?.message || "Error al registrar las horas"
  } finally { guardando.value = false }
}

// ── Acciones supervisor: planificación ──────────────────────────────────────
async function aprobarPlan(id) {
  if (!confirm("¿Aprobar esta planificación?")) return
  try {
    await api.patch("/horas-extras/planificacion/" + id + "/aprobar")
    await cargarEquipoPlan()
  } catch (e) {
    alert(e.response?.data?.message || "Error al aprobar")
  }
}
function abrirModalNegarPlan(plan) {
  errorModal.value = ""
  modalNegar.value = { show: true, planId: plan.id, observacion: "" }
}
async function negarPlan() {
  if (!modalNegar.value.observacion.trim()) {
    errorModal.value = "Ingrese una observación"
    return
  }
  guardando.value = true
  errorModal.value = ""
  try {
    await api.patch("/horas-extras/planificacion/" + modalNegar.value.planId + "/negar", {
      observacion: modalNegar.value.observacion,
    })
    modalNegar.value.show = false
    await cargarEquipoPlan()
  } catch (e) {
    errorModal.value = e.response?.data?.message || "Error al negar"
  } finally { guardando.value = false }
}

// ── Acciones supervisor: registros ──────────────────────────────────────────
async function confirmarRegistro(id) {
  if (!confirm("¿Confirmar este registro de horas?")) return
  try {
    await api.patch("/horas-extras/registro/" + id + "/confirmar")
    await cargarEquipoHoras()
  } catch (e) {
    alert(e.response?.data?.message || "Error al confirmar")
  }
}
function abrirModalNegarRegistro(reg) {
  errorModal.value = ""
  modalNegarReg.value = { show: true, regId: reg.id, observacion: "" }
}
async function negarRegistroAction() {
  if (!modalNegarReg.value.observacion.trim()) {
    errorModal.value = "Ingrese una observación"
    return
  }
  guardando.value = true
  errorModal.value = ""
  try {
    await api.patch("/horas-extras/registro/" + modalNegarReg.value.regId + "/negar", {
      observacion: modalNegarReg.value.observacion,
    })
    modalNegarReg.value.show = false
    await cargarEquipoHoras()
  } catch (e) {
    errorModal.value = e.response?.data?.message || "Error al negar"
  } finally { guardando.value = false }
}

// ── Editar registro (empleado, EN REVISION) ──────────────────────────────────
function abrirEditarRegistro(reg) {
  errorModal.value = ""
  modalEditarReg.value = {
    show: true, reg, preview: null,
    form: { fecha: reg.fecha, hora_inicio: reg.hora_inicio || "", hora_fin: reg.hora_fin || "", descripcion: reg.descripcion || "" },
  }
}
async function calcularPreviewEditar() {
  const { fecha, hora_inicio, hora_fin } = modalEditarReg.value.form
  if (!fecha || !hora_inicio || !hora_fin) return
  try {
    const { data } = await api.get("/horas-extras/calcular", { params: { fecha, hora_inicio, hora_fin } })
    modalEditarReg.value.preview = data
  } catch { modalEditarReg.value.preview = null }
}
async function guardarEditarRegistro() {
  errorModal.value = ""
  guardando.value  = true
  try {
    await api.put("/horas-extras/registro/" + modalEditarReg.value.reg.id, modalEditarReg.value.form)
    modalEditarReg.value.show = false
    await cargarMisHoras()
  } catch (e) {
    errorModal.value = e.response?.data?.message || "Error al actualizar el registro"
  } finally { guardando.value = false }
}

// ── Revisión TH NOMINA ────────────────────────────────────────────────────────
async function aprobarRevision(id) {
  if (!confirm("¿Aprobar la revisión y enviar al supervisor?")) return
  try {
    await api.patch("/horas-extras/registro/" + id + "/revisar", { accion: "aprobar" })
    await cargarEquipoHoras()
  } catch (e) { alert(e.response?.data?.message || "Error") }
}
function abrirModalDevolverRegistro(reg) {
  errorModal.value = ""
  modalDevolverReg.value = { show: true, regId: reg.id, observacion: "" }
}
async function devolverRegistro() {
  if (!modalDevolverReg.value.observacion.trim()) {
    errorModal.value = "Ingrese la observación para el empleado"
    return
  }
  guardando.value = true
  errorModal.value = ""
  try {
    await api.patch("/horas-extras/registro/" + modalDevolverReg.value.regId + "/revisar", {
      accion: "devolver",
      observacion: modalDevolverReg.value.observacion,
    })
    modalDevolverReg.value.show = false
    await cargarEquipoHoras()
  } catch (e) {
    errorModal.value = e.response?.data?.message || "Error al devolver"
  } finally { guardando.value = false }
}

watch(tabActiva, (tab) => {
  if (tab === 'equipo-plan')  cargarEquipoPlan()
  if (tab === 'equipo-horas') cargarEquipoHoras()
  if (tab === 'mis-horas')    cargarMisHoras()
})

// ── Init ──────────────────────────────────────────────────────────────────────
onMounted(async () => {
  try {
    const { data } = await api.get("/horas-extras/mi-rol")
    esSupervisorOAdmin.value = data.es_supervisor || data.es_admin_th
    esTHNomina.value         = data.es_admin_th
  } catch { /* si falla, el usuario ve solo sus tabs */ }
  cargarMiPlanificacion()
})

function abrirModalAutorizar(plan) {
  errorModal.value = ""
  modalAutorizar.value = { show: true, planId: plan.id, memorando: "" }
}
async function autorizarPlan() {
  if (!modalAutorizar.value.memorando.trim()) {
    errorModal.value = "Ingrese el texto del memorando"
    return
  }
  guardando.value = true
  errorModal.value = ""
  try {
    await api.patch("/horas-extras/planificacion/" + modalAutorizar.value.planId + "/autorizar", {
      memorando: modalAutorizar.value.memorando,
    })
    modalAutorizar.value.show = false
    await cargarEquipoPlan()
  } catch (e) {
    errorModal.value = e.response?.data?.message || "Error al autorizar"
  } finally { guardando.value = false }
}
</script>
