<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-800">Vacaciones</h1>
      <button v-if="!saldo.inactivo && (!esSupervisorOAdmin || tabActivo === 'mia')" @click="abrirModalNuevo"
        class="bg-[#0b5447] text-white px-4 py-2 rounded-lg hover:bg-[#00372e] text-sm font-medium">
        + Solicitar Vacaciones
      </button>
    </div>

    <!-- Tabs (solo supervisor/admin) -->
    <div v-if="esSupervisorOAdmin" class="flex gap-0 rounded-xl overflow-hidden border border-gray-200 mb-1">
      <button @click="cambiarTab('mia')"
        :class="['flex-1 py-2.5 text-sm font-semibold transition', tabActivo === 'mia' ? 'text-white' : 'bg-gray-50 text-gray-500 hover:bg-gray-100']"
        :style="tabActivo === 'mia' ? 'background-color:#0b5447' : ''">Mis Vacaciones</button>
      <button @click="cambiarTab('equipo')"
        :class="['flex-1 py-2.5 text-sm font-semibold transition', tabActivo === 'equipo' ? 'text-white' : 'bg-gray-50 text-gray-500 hover:bg-gray-100']"
        :style="tabActivo === 'equipo' ? 'background-color:#0b5447' : ''">{{ miRol.es_admin_th ? 'Vacaciones Institucionales' : 'Vacaciones Equipo' }}</button>
    </div>

    <!-- Empleado inactivo -->
    <div v-if="saldo.inactivo" class="bg-yellow-50 border border-yellow-300 rounded-xl p-4 text-yellow-800 text-sm">
      Tu cuenta está inactiva. No puedes consultar saldo ni solicitar vacaciones.
    </div>

    <!-- Saldo -->
    <div v-if="saldo.saldo_calculado" class="bg-white rounded-xl shadow p-4 space-y-3">
      <div class="flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-700">Saldo de Vacaciones</h2>
      </div>
      <div class="flex gap-6">
        <div class="text-center">
          <p :class="esNombramiento && saldoReal < 0 ? 'text-3xl font-bold text-red-600' : 'text-3xl font-bold text-[#0b5447]'">
            {{ esNombramiento ? saldoReal : (saldo.saldo_calculado.dias_disponibles ?? 0) }}
          </p>
          <p class="text-xs mt-1" :class="esNombramiento && saldoReal < 0 ? 'text-red-500' : 'text-gray-500'">
            Días disponibles<template v-if="esNombramiento && saldoReal < 0"> (negativo)</template>
          </p>
        </div>
        <!-- Días tomados / Saldo inicial (Excel) / Acumulado a hoy: ocultos para empleados sin
             rol especial (a pedido de TH) — solo visibles para supervisor/TH/admin. Solo se
             oculta en pantalla, el cálculo y los datos siguen igual por detrás. -->
        <div v-if="esSupervisorOAdmin" class="text-center">
          <p class="text-3xl font-bold text-gray-400">{{ saldo.saldo_calculado.tomados ?? 0 }}</p>
          <p class="text-xs text-gray-500 mt-1">Días tomados</p>
        </div>
        <div v-if="esSupervisorOAdmin" class="text-center">
          <p class="text-xl font-semibold text-gray-500">{{ saldo.saldo_calculado.saldo_inicial ?? 0 }}</p>
          <p class="text-xs text-gray-500 mt-1">Saldo inicial (Excel)</p>
        </div>
        <div v-if="esSupervisorOAdmin" class="text-center">
          <p class="text-xl font-semibold text-green-600">+{{ saldo.saldo_calculado.acumulado_a_hoy ?? 0 }}</p>
          <p class="text-xs text-gray-500 mt-1">Acumulado a hoy</p>
        </div>
        <div v-if="saldo.saldo_calculado.dias_adicionales_antiguedad > 0" class="text-center">
          <p class="text-xl font-semibold text-blue-600">+{{ saldo.saldo_calculado.dias_adicionales_antiguedad }}</p>
          <p class="text-xs text-gray-500 mt-1">Días adicionales<br>por antigüedad/año</p>
        </div>
      </div>
      <div v-if="saldo.saldo_calculado.dias_adicionales_antiguedad > 0"
        class="text-xs text-blue-700 bg-blue-50 border border-blue-200 rounded-lg px-3 py-1.5">
        Código del Trabajo — {{ saldo.saldo_calculado.dias_anuales }} días/año
        (15 base + {{ saldo.saldo_calculado.dias_adicionales_antiguedad }} por antigüedad)
      </div>

    </div>

    <div v-else-if="cargandoSaldo" class="bg-white rounded-xl shadow p-4 text-center text-gray-400 text-sm">
      Cargando saldo...
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-xl shadow p-4 flex flex-wrap gap-3">
      <select v-model="filtros.estado" @change="pagina = 1; cargar()"
        class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
        <option value="">Todos los estados</option>
        <option value="PENDIENTE">Pendiente</option>
        <option value="APROBADO">Aprobado</option>
        <option value="NEGADO">Negado</option>
        <option value="ELIMINADO">Eliminado</option>
      </select>
      <input v-model="filtros.fecha_desde" type="date" @change="pagina = 1; cargar()"
        class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
      <input v-model="filtros.fecha_hasta" type="date" @change="pagina = 1; cargar()"
        class="border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
      <button @click="limpiarFiltros"
        class="border rounded-lg px-3 py-2 text-sm text-gray-600 hover:bg-gray-50">
        Limpiar
      </button>
    </div>

    <!-- Tabla -->
    <div class="bg-white rounded-xl shadow overflow-hidden">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b">
          <tr>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Empleado</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Fecha Inicio</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Fecha Fin</th>
            <th class="text-center px-4 py-3 text-gray-600 font-medium">Días</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Estado</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Aprobado por</th>
            <th class="text-left px-4 py-3 text-gray-600 font-medium">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="cargando">
            <td colspan="7" class="text-center py-8 text-gray-400">Cargando...</td>
          </tr>
          <tr v-else-if="vacaciones.length === 0">
            <td colspan="7" class="text-center py-8 text-gray-400">No hay solicitudes registradas</td>
          </tr>
          <tr v-for="v in vacaciones" :key="v.secuencial_clave" class="border-b hover:bg-gray-50">
            <td class="px-4 py-3 font-medium">
              {{ v.empleado?.apellido_emp }}, {{ v.empleado?.nombre_emp }}
            </td>
            <td class="px-4 py-3 text-gray-600">{{ v.fecha_inicial?.substring(0, 10) }}</td>
            <td class="px-4 py-3 text-gray-600">{{ v.fecha_final?.substring(0, 10) }}</td>
            <td class="px-4 py-3 text-center font-semibold text-[#0b5447]">
              {{ diasVac(v.fecha_inicial, v.fecha_final) }}
            </td>
            <td class="px-4 py-3">
              <span :class="colorEstado(v.estado_permiso)"
                class="px-2 py-1 rounded-full text-xs font-medium">
                {{ v.estado_permiso }}
              </span>
              <span v-if="v.requiere_informe && v.estado_permiso === 'PENDIENTE' && v.informe_estado !== 'FAVORABLE'"
                class="ml-1 px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700">
                Requiere informe TH
              </span>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">
              <template v-if="v.aprobador">
                {{ v.aprobador.apellido_emp }}, {{ v.aprobador.nombre_emp }}
              </template>
              <span v-else class="text-gray-300">—</span>
            </td>
            <td class="px-4 py-3">
              <div class="flex gap-1 flex-wrap">
                <button @click="verVacacion(v)"
                  class="inline-flex items-center px-2.5 py-1 rounded-md border border-[#0b5447] text-xs text-[#0b5447] hover:bg-[#f0faf8] font-medium transition-colors">
                  Ver
                </button>
                <template v-if="esSupervisorOAdmin && tabActivo === 'equipo' && v.estado_permiso === 'PENDIENTE'">
                  <button v-if="miRol.es_admin_th && v.requiere_informe && v.informe_estado !== 'FAVORABLE'"
                    @click="abrirModalInforme(v)"
                    class="inline-flex items-center px-2.5 py-1 rounded-md border border-orange-300 text-xs text-orange-700 hover:bg-orange-50 font-medium transition-colors">
                    Informe
                  </button>
                  <button @click="v.requiere_informe && v.informe_estado !== 'FAVORABLE' ? null : abrirModalBackup(v.secuencial_clave)"
                    :class="v.requiere_informe && v.informe_estado !== 'FAVORABLE'
                      ? 'inline-flex items-center px-2.5 py-1 rounded-md border border-gray-200 text-xs text-gray-300 cursor-not-allowed font-medium'
                      : 'inline-flex items-center px-2.5 py-1 rounded-md border border-green-300 text-xs text-green-700 hover:bg-green-50 font-medium transition-colors'"
                    :title="v.requiere_informe && v.informe_estado !== 'FAVORABLE' ? 'Requiere informe favorable de TH' : ''">
                    Aprobar
                  </button>
                  <button @click="abrirModalNegar(v)"
                    class="inline-flex items-center px-2.5 py-1 rounded-md border border-red-200 text-xs text-red-600 hover:bg-red-50 font-medium transition-colors">
                    Negar
                  </button>
                  <button @click="abrirModalEliminar(v)"
                    class="inline-flex items-center px-2.5 py-1 rounded-md border border-gray-200 text-xs text-gray-500 hover:bg-gray-50 font-medium transition-colors">
                    Eliminar
                  </button>
                </template>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Paginación -->
      <div class="flex justify-between items-center px-4 py-3 border-t text-sm text-gray-600">
        <span>Mostrando {{ rangoDesde }}-{{ rangoHasta }} de {{ total }} solicitudes</span>
        <div class="flex gap-2">
          <button @click="pagina--; cargar()" :disabled="pagina === 1"
            class="px-3 py-1 border rounded-lg disabled:opacity-50 hover:bg-gray-50">Anterior</button>
          <span class="px-3 py-1">{{ pagina }}</span>
          <button @click="pagina++; cargar()" :disabled="pagina >= totalPaginas"
            class="px-3 py-1 border rounded-lg disabled:opacity-50 hover:bg-gray-50">Siguiente</button>
        </div>
      </div>
    </div>

    <!-- Modal Solicitar -->
    <div v-if="modalNuevo" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-lg overflow-hidden">
        <div class="px-6 py-4" style="background-color:#0b5447;">
          <h2 class="text-lg font-semibold text-white">Solicitar Vacaciones</h2>
        </div>
        <div class="p-6 space-y-4">

        <!-- Recordatorio períodos planificados -->
        <div v-if="periodosPlani.length" class="bg-[#0b5447]/5 border border-[#0b5447]/20 rounded-lg px-4 py-3">
          <p class="text-xs font-semibold text-[#0b5447] uppercase tracking-wide mb-2">
            📅 Tus períodos planificados {{ anioActual }}
          </p>
          <div class="flex flex-wrap gap-2">
            <span v-for="(p, i) in periodosPlani" :key="i"
              class="inline-flex items-center gap-1 bg-white border border-[#0b5447]/30 text-[#0b5447] text-xs font-medium px-2.5 py-1 rounded-full">
              Per. {{ i + 1 }}: {{ fmtFechaPlan(p.fecha_inicial) }} — {{ fmtFechaPlan(p.fecha_final) }}
            </span>
          </div>
        </div>

        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-gray-600 mb-1">Fecha Inicio *</label>
              <input v-model="formNuevo.fecha_inicial" type="date"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-600 mb-1">Fecha Fin *</label>
              <input v-model="formNuevo.fecha_final" type="date"
                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]" />
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-600 mb-1">Observaciones</label>
            <textarea v-model="formNuevo.observaciones" rows="3" maxlength="250"
              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]"></textarea>
          </div>
          <div v-if="errorNuevo" class="text-red-600 text-sm bg-red-50 rounded p-2">{{ errorNuevo }}</div>
          <div class="flex justify-end gap-3 pt-2">
            <button @click="modalNuevo = false"
              class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
            <button @click="guardar" :disabled="guardando"
              class="px-4 py-2 rounded-lg bg-[#0b5447] text-white text-sm hover:bg-[#00372e] disabled:opacity-50">
              {{ guardando ? "Enviando..." : "Solicitar" }}
            </button>
          </div>
        </div>
        </div>
      </div>
    </div>

    <!-- Modal Ver -->
    <div v-if="modalVer" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-lg overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4" style="background-color:#0b5447;">
          <h2 class="text-lg font-semibold text-white">Detalle de Vacación</h2>
          <button @click="modalVer = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-6 space-y-4">
        <dl class="grid grid-cols-2 gap-3 text-sm">
          <div>
            <dt class="text-gray-500">Empleado</dt>
            <dd class="font-medium">{{ seleccionado?.empleado?.apellido_emp }}, {{ seleccionado?.empleado?.nombre_emp }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Departamento</dt>
            <dd class="font-medium">{{ seleccionado?.empleado?.departamento?.nombre_depto }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Fecha de solicitud</dt>
            <dd class="font-medium">{{ seleccionado?.fecha_hora?.substring(0, 16)?.replace('T', ' ') }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Fecha de aprobación</dt>
            <dd class="font-medium">{{ seleccionado?.aprobado_en?.substring(0, 16)?.replace('T', ' ') || '—' }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Fecha Inicio</dt>
            <dd class="font-medium">{{ seleccionado?.fecha_inicial?.substring(0, 10) }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Fecha Fin</dt>
            <dd class="font-medium">{{ seleccionado?.fecha_final?.substring(0, 10) }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Hora Desde</dt>
            <dd class="font-medium">{{ seleccionado?.hora_desde?.substring(11, 16) }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Hora Hasta</dt>
            <dd class="font-medium">{{ seleccionado?.hora_hasta?.substring(11, 16) }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Todo el día</dt>
            <dd class="font-medium">{{ seleccionado?.todo_dia }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Estado</dt>
            <dd>
              <span :class="colorEstado(seleccionado?.estado_permiso)"
                class="px-2 py-1 rounded-full text-xs font-medium">
                {{ seleccionado?.estado_permiso }}
              </span>
            </dd>
          </div>
          <div v-if="seleccionado?.requiere_informe" class="col-span-2">
            <dt class="text-gray-500">Informe TH (saldo insuficiente)</dt>
            <dd>
              <span v-if="seleccionado?.informe_estado === 'FAVORABLE'"
                class="px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                Favorable
              </span>
              <span v-else-if="seleccionado?.informe_estado === 'DESFAVORABLE'"
                class="px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">
                Desfavorable
              </span>
              <span v-else class="px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-700">
                Pendiente
              </span>
              <span v-if="seleccionado?.informador" class="text-xs text-gray-500 ml-2">
                {{ seleccionado.informador.apellido_emp }}, {{ seleccionado.informador.nombre_emp }}
                <template v-if="seleccionado?.informe_fecha">— {{ seleccionado.informe_fecha.substring(0, 10) }}</template>
              </span>
            </dd>
          </div>
          <div class="col-span-2">
            <dt class="text-gray-500">Observaciones</dt>
            <dd class="font-medium">{{ seleccionado?.observaciones || "—" }}</dd>
          </div>
          <div v-if="seleccionado?.observacion_negacion" class="col-span-2">
            <dt class="text-gray-500">
              {{ seleccionado?.estado_permiso === 'ELIMINADO' ? 'Motivo de eliminación' : 'Motivo de negación' }}
            </dt>
            <dd class="font-medium text-red-600">{{ seleccionado?.observacion_negacion }}</dd>
          </div>
        </dl>
        <div class="flex justify-end pt-2">
          <button @click="modalVer = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cerrar</button>
        </div>
        </div>
      </div>
    </div>

    <!-- Modal Eliminar -->
    <div v-if="modalEliminar" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-md space-y-4">
        <h2 class="text-lg font-semibold text-gray-700">Eliminar Solicitud</h2>
        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Motivo de eliminación <span class="text-red-500">*</span></label>
          <textarea v-model="motivoEliminacion" rows="3" maxlength="120"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]"></textarea>
          <p v-if="errorEliminar" class="text-red-500 text-xs mt-1">{{ errorEliminar }}</p>
        </div>
        <div class="flex justify-end gap-3">
          <button @click="modalEliminar = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
          <button @click="confirmarEliminar"
            class="px-4 py-2 rounded-lg bg-gray-600 text-white text-sm hover:bg-gray-700">
            Confirmar Eliminación
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Negar -->
    <div v-if="modalNegar" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-md space-y-4">
        <h2 class="text-lg font-semibold text-gray-700">Negar Vacación</h2>
        <div>
          <label class="block text-sm font-medium text-gray-600 mb-1">Motivo de negación</label>
          <textarea v-model="motivoNegacion" rows="3" maxlength="120"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]"></textarea>
        </div>
        <div v-if="errorNegar" class="text-red-600 text-sm bg-red-50 rounded p-2">{{ errorNegar }}</div>
        <div class="flex justify-end gap-3">
          <button @click="modalNegar = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
          <button @click="confirmarNegar"
            class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm hover:bg-red-700">
            Confirmar Negación
          </button>
        </div>
      </div>
    </div>

    <!-- Modal Backup al Aprobar -->
    <div v-if="modalBackup.show" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-md space-y-4">
        <h2 class="text-lg font-semibold text-gray-700">Aprobar Vacación</h2>
        <p class="text-sm text-gray-500">Puede seleccionar una persona de respaldo (backup) para cubrir al solicitante durante su ausencia.</p>

        <div v-if="modalBackup.cargando" class="text-center py-4 text-sm text-gray-400">Cargando empleados...</div>
        <div v-else>
          <label class="block text-sm font-medium text-gray-600 mb-1">Persona de respaldo</label>
          <select v-model="modalBackup.seleccionado"
            class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186]">
            <option :value="null">— Sin backup —</option>
            <option v-for="emp in modalBackup.empleados" :key="emp.id_emp" :value="emp.id_emp">
              {{ emp.apellido_emp }} {{ emp.nombre_emp }}
            </option>
          </select>
        </div>

        <div v-if="errorBackup" class="text-red-600 text-sm bg-red-50 rounded p-2">{{ errorBackup }}</div>

        <div class="flex justify-end gap-3">
          <button @click="modalBackup.show = false"
            class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
          <button @click="confirmarAprobar(false)"
            class="px-4 py-2 rounded-lg border border-green-600 text-green-700 text-sm hover:bg-green-50">
            Aprobar sin backup
          </button>
          <button @click="confirmarAprobar(true)" :disabled="!modalBackup.seleccionado"
            class="px-4 py-2 rounded-lg bg-[#579186] text-white text-sm hover:bg-[#46786f] disabled:opacity-40 disabled:cursor-not-allowed">
            Aprobar con backup
          </button>
        </div>
      </div>
    </div>
    <!-- Modal Informe TH -->
    <div v-if="modalInforme" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-md overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4" style="background-color:#0b5447;">
          <h2 class="text-lg font-semibold text-white">Informe de Vacaciones</h2>
          <button @click="modalInforme = false" class="text-white hover:text-gray-200 text-xl font-bold leading-none">×</button>
        </div>
        <div class="p-6 space-y-4">
          <p class="text-sm text-gray-600">
            El empleado <strong>{{ vacInforme?.nombre_emp }}</strong> solicita
            <strong>{{ diasVac(vacInforme?.fecha_inicial, vacInforme?.fecha_final) }} días</strong>
            ({{ vacInforme?.fecha_inicial?.substring(0,10) }} al {{ vacInforme?.fecha_final?.substring(0,10) }})
            con saldo insuficiente. Registra el resultado del informe de Talento Humano.
          </p>
          <div v-if="errorInforme" class="text-red-600 text-sm bg-red-50 rounded p-2">{{ errorInforme }}</div>
          <div class="flex justify-end gap-3 pt-2">
            <button @click="modalInforme = false"
              class="px-4 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
            <button @click="confirmarInforme('DESFAVORABLE')" :disabled="guardandoInforme"
              class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm hover:bg-red-700 disabled:opacity-50">
              Desfavorable (Negar)
            </button>
            <button @click="confirmarInforme('FAVORABLE')" :disabled="guardandoInforme"
              class="px-4 py-2 rounded-lg text-white text-sm disabled:opacity-50"
              style="background-color:#0b5447;">
              Favorable
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue"
import api from "@/services/api"
import TimePicker24 from "@/components/TimePicker24.vue"

const vacaciones   = ref([])
const cargando     = ref(false)
const guardando    = ref(false)
const total        = ref(0)
const pagina       = ref(1)
const totalPaginas = ref(1)
const miRol        = ref({ es_supervisor: false, es_admin_th: false })
const tabActivo    = ref("mia")

const saldo               = ref({ cabecera: null, detalle: [] })
const cargandoSaldo       = ref(false)
const mostrarDetalleSaldo = ref(false)

const periodosPlani  = ref([])
const anioActual     = new Date().getFullYear()
const modalNuevo     = ref(false)
const modalVer       = ref(false)
const modalNegar          = ref(false)
const modalEliminar       = ref(false)
const modalBackup         = ref({ show: false, vacId: null, empleados: [], seleccionado: null, cargando: false })
const seleccionado        = ref(null)
const motivoNegacion      = ref("")
const motivoEliminacion   = ref("")
const errorEliminar       = ref("")
const errorNuevo          = ref("")
const errorNegar          = ref("")
const errorBackup         = ref("")
const modalInforme        = ref(false)
const vacInforme          = ref(null)
const guardandoInforme    = ref(false)
const errorInforme        = ref("")

const filtros = ref({ estado: "", fecha_desde: "", fecha_hasta: "" })

const hoy = new Date().toISOString().split('T')[0]

const formNuevo = ref({
  fecha_inicial: hoy, fecha_final: hoy,
  hora_desde: "08:00", hora_hasta: "17:00",
  todo_dia: "SI", observaciones: "",
})

const esSupervisorOAdmin = computed(() =>
  miRol.value.es_supervisor || miRol.value.es_admin_th
)

// Rango mostrado en el pie de la tabla (ej. "16-30 de 70") — antes se mostraba solo
// vacaciones.length, que es igual (15) en cualquier página completa y daba la falsa
// impresión de que el contador no avanzaba al paginar.
const rangoDesde = computed(() => total.value === 0 ? 0 : (pagina.value - 1) * 15 + 1)
const rangoHasta = computed(() => (pagina.value - 1) * 15 + vacaciones.value.length)

const esNombramiento = computed(() => !!saldo.value?.es_nombramiento_definitivo)

const saldoReal = computed(() =>
  saldo.value?.saldo_calculado?.dias_disponibles_real ?? 0
)

const colorEstado = (estado) => {
  const colores = {
    "PENDIENTE": "bg-yellow-100 text-yellow-700",
    "APROBADO":  "bg-green-100 text-green-700",
    "NEGADO":    "bg-red-100 text-red-700",
    "ELIMINADO": "bg-gray-100 text-gray-700",
  }
  return colores[estado] || "bg-gray-100 text-gray-700"
}

const cargar = async () => {
  cargando.value = true
  try {
    const vista  = esSupervisorOAdmin.value ? tabActivo.value : ""
    const params = { page: pagina.value, per_page: 15, ...filtros.value, ...(vista ? { vista } : {}) }
    const { data } = await api.get("/vacaciones", { params })
    vacaciones.value   = data.data
    total.value        = data.total
    totalPaginas.value = data.last_page
  } catch (e) {
    console.error(e)
  } finally {
    cargando.value = false
  }
}

const cambiarTab = (tab) => {
  tabActivo.value = tab
  pagina.value    = 1
  filtros.value   = { estado: "", fecha_desde: "", fecha_hasta: "" }
  cargar()
}

const cargarSaldo = async () => {
  cargandoSaldo.value = true
  try {
    const { data } = await api.get("/vacaciones/mi-saldo")
    saldo.value = data
  } catch (e) {
    console.error(e)
  } finally {
    cargandoSaldo.value = false
  }
}

const diasVac = (desde, hasta) => {
  if (!desde || !hasta) return '—'
  const d1 = new Date(desde.substring(0, 10) + 'T00:00:00')
  const d2 = new Date(hasta.substring(0, 10) + 'T00:00:00')
  return Math.round((d2 - d1) / 86400000) + 1
}

const fmtFechaPlan = (fecha) => {
  if (!fecha) return '—'
  const meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic']
  const [, m, d] = fecha.split('-')
  return `${parseInt(d)} ${meses[parseInt(m) - 1]}`
}

const abrirModalNuevo = async () => {
  errorNuevo.value = ""
  periodosPlani.value = []
  formNuevo.value = {
    fecha_inicial: hoy, fecha_final: hoy,
    hora_desde: "08:00", hora_hasta: "17:00",
    todo_dia: "SI", observaciones: "",
  }
  modalNuevo.value = true
  try {
    const { data } = await api.get('/planificacion/mi-planificacion', { params: { anio: anioActual } })
    const plani = data.planificacion
    if (plani && ['APROBADO', 'REPLANIFICADO'].includes(plani.estado)) {
      periodosPlani.value = (plani.periodos ?? []).filter(p => p.fecha_inicial && p.fecha_final)
    }
  } catch {}
}

const guardar = async () => {
  if (!formNuevo.value.fecha_inicial || !formNuevo.value.fecha_final) {
    errorNuevo.value = "Las fechas son requeridas"
    return
  }
  guardando.value  = true
  errorNuevo.value = ""
  try {
    await api.post("/vacaciones", formNuevo.value)
    modalNuevo.value = false
    cargar()
    cargarSaldo()
  } catch (e) {
    errorNuevo.value = e.response?.data?.message || "Error al solicitar vacaciones"
  } finally {
    guardando.value = false
  }
}

const verVacacion = (v) => {
  seleccionado.value = v
  modalVer.value = true
}

const abrirModalBackup = async (id) => {
  errorBackup.value = ""
  modalBackup.value = { show: true, vacId: id, empleados: [], seleccionado: null, cargando: true }
  try {
    const { data } = await api.get("/vacaciones/" + id + "/empleados-depto")
    modalBackup.value.empleados = data
  } catch {
    modalBackup.value.empleados = []
  } finally {
    modalBackup.value.cargando = false
  }
}

const confirmarAprobar = async (conBackup) => {
  const payload = {}
  if (conBackup && modalBackup.value.seleccionado) {
    const emp = modalBackup.value.empleados.find(e => e.id_emp === modalBackup.value.seleccionado)
    payload.backup_id     = emp.id_emp
    payload.backup_nombre = (emp.apellido_emp + " " + emp.nombre_emp).trim()
  }
  errorBackup.value = ""
  try {
    await api.patch("/vacaciones/" + modalBackup.value.vacId + "/aprobar", payload)
    modalBackup.value.show = false
    cargar()
  } catch (e) {
    errorBackup.value = e.response?.data?.message || "Error al aprobar"
  }
}

const abrirModalNegar = (v) => {
  seleccionado.value   = v
  motivoNegacion.value = ""
  errorNegar.value     = ""
  modalNegar.value     = true
}

const confirmarNegar = async () => {
  errorNegar.value = ""
  try {
    await api.patch("/vacaciones/" + seleccionado.value.secuencial_clave + "/negar", {
      observacion_negacion: motivoNegacion.value
    })
    modalNegar.value = false
    cargar()
  } catch (e) {
    errorNegar.value = e.response?.data?.message || "Error al negar"
  }
}

const abrirModalEliminar = (v) => {
  seleccionado.value      = v
  motivoEliminacion.value = ""
  errorEliminar.value     = ""
  modalEliminar.value     = true
}

const confirmarEliminar = async () => {
  if (!motivoEliminacion.value.trim()) {
    errorEliminar.value = "El motivo de eliminación es obligatorio"
    return
  }
  try {
    await api.delete("/vacaciones/" + seleccionado.value.secuencial_clave, {
      data: { observacion_negacion: motivoEliminacion.value }
    })
    modalEliminar.value = false
    cargar()
  } catch (e) {
    errorEliminar.value = e.response?.data?.message || "Error al eliminar"
  }
}

const abrirModalInforme = (v) => {
  vacInforme.value    = v
  errorInforme.value  = ""
  modalInforme.value  = true
}

const confirmarInforme = async (estado) => {
  guardandoInforme.value = true
  errorInforme.value     = ""
  try {
    await api.patch("/vacaciones/" + vacInforme.value.secuencial_clave + "/marcar-informe", {
      informe_estado: estado
    })
    modalInforme.value = false
    cargar()
    cargarSaldo()
  } catch (e) {
    errorInforme.value = e.response?.data?.message || "Error al registrar el informe"
  } finally {
    guardandoInforme.value = false
  }
}

const limpiarFiltros = () => {
  filtros.value = { estado: "", fecha_desde: "", fecha_hasta: "" }
  pagina.value  = 1
  cargar()
}

onMounted(async () => {
  const { data } = await api.get("/vacaciones/mi-rol")
  miRol.value = data
  cargar()
  cargarSaldo()
})
</script>
