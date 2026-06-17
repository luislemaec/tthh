<template>
  <div>
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-800">Liquidaciones de Viáticos</h1>
        <p class="text-sm text-gray-500 mt-0.5">Gestión financiera de comisiones</p>
      </div>
    </div>

    <!-- Tabs financieras -->
    <div class="flex gap-0 rounded-xl overflow-hidden border border-gray-200 mb-4">
      <button v-for="tab in tabsVisibles" :key="tab.key"
        @click="tabActivo = tab.key"
        :class="['flex-1 py-2.5 text-sm font-semibold transition', tabActivo === tab.key ? 'text-white' : 'bg-gray-50 text-gray-500 hover:bg-gray-100']"
        :style="tabActivo === tab.key ? 'background-color:#5c4a6e' : ''">
        {{ tab.label }}
        <span v-if="tab.badge" class="ml-1.5 bg-amber-400 text-white text-xs font-bold px-1.5 py-0.5 rounded-full">{{ tab.badge }}</span>
      </button>
    </div>

    <div v-if="cargando" class="flex items-center justify-center py-16">
      <div class="w-8 h-8 border-2 border-[#5c4a6e] border-t-transparent rounded-full animate-spin"></div>
    </div>

    <template v-else>

      <!-- ═══ TAB: Anticipos ═══ -->
      <div v-if="tabActivo === 'anticipos'">
        <div v-if="solicitudesConAnticipo.length === 0" class="text-center py-16 text-gray-400 text-sm">
          No hay anticipos pendientes.
        </div>
        <div v-else class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-xs text-gray-500 uppercase border-b border-gray-100" style="background-color:#f9f7fb;">
                <th class="px-4 py-3 text-left font-semibold">Comisión</th>
                <th class="px-4 py-3 text-left font-semibold">Empleado</th>
                <th class="px-4 py-3 text-right font-semibold">Monto</th>
                <th class="px-4 py-3 text-left font-semibold">Estado Anticipo</th>
                <th class="px-4 py-3 text-left font-semibold">CUR Compromiso</th>
                <th class="px-4 py-3 text-left font-semibold">CUR Devengado</th>
                <th class="px-4 py-3 text-left font-semibold">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="sol in solicitudesConAnticipo" :key="sol.id"
                class="border-b border-gray-50 hover:bg-purple-50/20 transition">
                <td class="px-4 py-3">
                  <p class="font-mono text-xs text-gray-600">{{ sol.numero_solicitud || '—' }}</p>
                  <p class="text-xs text-gray-400">{{ sol.tipo }}</p>
                </td>
                <td class="px-4 py-3">
                  <p class="font-medium text-gray-800">{{ sol.empleado?.apellido_emp }} {{ sol.empleado?.nombre_emp }}</p>
                </td>
                <td class="px-4 py-3 text-right font-semibold text-gray-800">
                  ${{ formatMonto(sol.anticipo?.monto_solicitado) }}
                </td>
                <td class="px-4 py-3">
                  <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full', badgeAnticipo(sol.anticipo?.estado).class]">
                    {{ badgeAnticipo(sol.anticipo?.estado).label }}
                  </span>
                </td>
                <td class="px-4 py-3 text-xs text-gray-600">
                  <span v-if="sol.anticipo?.cur_compromiso" class="font-mono">{{ sol.anticipo.cur_compromiso }}</span>
                  <button v-else-if="miRol.es_presupuesto || miRol.es_admin" @click="abrirCurAnticipo(sol, 'cur-compromiso')"
                    class="text-xs text-[#5c4a6e] hover:underline font-medium">Registrar</button>
                  <span v-else class="text-gray-300">—</span>
                </td>
                <td class="px-4 py-3 text-xs text-gray-600">
                  <span v-if="sol.anticipo?.cur_devengado" class="font-mono">{{ sol.anticipo.cur_devengado }}</span>
                  <button v-else-if="(miRol.es_contabilidad || miRol.es_admin) && sol.anticipo?.cur_compromiso" @click="abrirCurAnticipo(sol, 'cur-devengado')"
                    class="text-xs text-[#5c4a6e] hover:underline font-medium">Registrar</button>
                  <span v-else class="text-gray-300">—</span>
                </td>
                <td class="px-4 py-3">
                  <button v-if="(miRol.es_tesoreria || miRol.es_admin) && sol.anticipo?.cur_devengado && sol.anticipo?.estado !== 'PAGADO'"
                    @click="confirmarPagoAnticipo(sol)"
                    class="px-2.5 py-1 text-xs font-semibold bg-green-600 text-white rounded transition hover:bg-green-700">
                    Confirmar Pago
                  </button>
                  <span v-else-if="sol.anticipo?.estado === 'PAGADO'" class="text-xs text-green-600 font-semibold">
                    Pagado {{ formatFecha(sol.anticipo?.fecha_pago) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ═══ TAB: En Pago / Liquidación ═══ -->
      <div v-if="tabActivo === 'enPago'">
        <div v-if="solicitudesEnPago.length === 0" class="text-center py-16 text-gray-400 text-sm">
          No hay comisiones pendientes de liquidación.
        </div>
        <div v-else class="space-y-4">
          <div v-for="sol in solicitudesEnPago" :key="sol.id"
            class="bg-white rounded-lg shadow-sm border border-gray-100 p-5">
            <div class="flex items-start justify-between gap-4 mb-4">
              <div>
                <div class="flex items-center gap-2 mb-1">
                  <span class="font-mono text-sm font-semibold text-gray-700">{{ sol.numero_solicitud }}</span>
                  <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full', badgeEstadoSol(sol.estado).class]">
                    {{ badgeEstadoSol(sol.estado).label }}
                  </span>
                </div>
                <p class="text-sm font-medium text-gray-800">{{ sol.empleado?.apellido_emp }} {{ sol.empleado?.nombre_emp }}</p>
                <p class="text-xs text-gray-500">{{ sol.destino }} — {{ formatFecha(sol.fecha_salida) }} al {{ formatFecha(sol.fecha_llegada) }}</p>
              </div>
              <div class="flex gap-2">
                <button @click="descargarPdfSolicitud(sol)" class="p-2 text-red-400 hover:text-red-600 hover:bg-red-50 rounded transition">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                  </svg>
                </button>
              </div>
            </div>

            <!-- Ficha de Liquidación -->
            <div v-if="!sol.ficha_liquidacion && (miRol.es_contabilidad || miRol.es_admin)" class="border-t border-gray-100 pt-4">
              <button @click="abrirFicha(sol)"
                class="px-4 py-2 text-sm font-semibold text-white rounded-lg hover:opacity-90 transition"
                style="background-color:#5c4a6e;">
                + Crear Ficha de Liquidación
              </button>
            </div>

            <div v-else-if="sol.ficha_liquidacion" class="border-t border-gray-100 pt-4 space-y-3">
              <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="bg-gray-50 rounded-lg p-3">
                  <p class="text-xs text-gray-400 font-semibold mb-0.5">DÍAS VIÁTICOS</p>
                  <p class="font-bold text-gray-800">{{ sol.ficha_liquidacion.dias_viaticos }} días × ${{ formatMonto(sol.ficha_liquidacion.valor_por_dia) }}</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-3">
                  <p class="text-xs text-gray-400 font-semibold mb-0.5">TOTAL VIÁTICO</p>
                  <p class="font-bold text-gray-800">${{ formatMonto(sol.ficha_liquidacion.total_viatico) }}</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-3">
                  <p class="text-xs text-gray-400 font-semibold mb-0.5">ANTICIPO</p>
                  <p class="font-bold text-gray-800">${{ formatMonto(sol.ficha_liquidacion.total_anticipo) }}</p>
                </div>
                <div class="rounded-lg p-3"
                  :class="sol.ficha_liquidacion.tipo_resultado === 'POR_COBRAR' ? 'bg-orange-50' : 'bg-green-50'">
                  <p class="text-xs font-semibold mb-0.5" :class="sol.ficha_liquidacion.tipo_resultado === 'POR_COBRAR' ? 'text-orange-500' : 'text-green-600'">
                    {{ sol.ficha_liquidacion.tipo_resultado === 'POR_COBRAR' ? 'VALOR A DEVOLVER' : 'VALOR A PAGAR' }}
                  </p>
                  <p class="font-bold text-lg" :class="sol.ficha_liquidacion.tipo_resultado === 'POR_COBRAR' ? 'text-orange-600' : 'text-green-700'">
                    ${{ formatMonto(Math.abs(sol.ficha_liquidacion.total_a_pagar)) }}
                  </p>
                </div>
              </div>

              <!-- CURs y pago -->
              <div class="flex items-center gap-4 flex-wrap text-sm">
                <div class="flex items-center gap-2">
                  <span class="text-xs text-gray-500 font-semibold">CUR Compromiso:</span>
                  <span v-if="sol.ficha_liquidacion.cur_compromiso" class="font-mono text-xs text-gray-700">{{ sol.ficha_liquidacion.cur_compromiso }}</span>
                  <button v-else-if="miRol.es_presupuesto || miRol.es_admin" @click="abrirCurLiq(sol, 'cur-compromiso')"
                    class="text-xs font-medium" style="color:#5c4a6e;">Registrar</button>
                  <span v-else class="text-xs text-gray-300">Pendiente</span>
                </div>
                <div class="flex items-center gap-2">
                  <span class="text-xs text-gray-500 font-semibold">CUR Devengado:</span>
                  <span v-if="sol.ficha_liquidacion.cur_devengado" class="font-mono text-xs text-gray-700">{{ sol.ficha_liquidacion.cur_devengado }}</span>
                  <button v-else-if="(miRol.es_contabilidad || miRol.es_admin) && sol.ficha_liquidacion.cur_compromiso" @click="abrirCurLiq(sol, 'cur-devengado')"
                    class="text-xs font-medium" style="color:#5c4a6e;">Registrar</button>
                  <span v-else class="text-xs text-gray-300">Pendiente</span>
                </div>
                <div class="ml-auto flex gap-2">
                  <button v-if="sol.ficha_liquidacion" @click="descargarPdfFicha(sol)"
                    class="flex items-center gap-1 px-3 py-1.5 text-xs font-semibold bg-red-50 text-red-600 rounded transition hover:bg-red-100">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    PDF Ficha
                  </button>
                  <button
                    v-if="(miRol.es_tesoreria || miRol.es_admin) && sol.ficha_liquidacion.cur_devengado && !['PAGADO','POR_COBRAR','CERRADO'].includes(sol.estado)"
                    @click="confirmarPagoFinal(sol)"
                    class="px-3 py-1.5 text-xs font-semibold bg-green-600 text-white rounded transition hover:bg-green-700">
                    Confirmar Pago Final
                  </button>
                  <button
                    v-if="(miRol.es_tesoreria || miRol.es_admin) && sol.estado === 'POR_COBRAR'"
                    @click="abrirDevolucion(sol)"
                    class="px-3 py-1.5 text-xs font-semibold bg-orange-500 text-white rounded transition hover:bg-orange-600">
                    Registrar Devolución
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ═══ TAB: Cerradas ═══ -->
      <div v-if="tabActivo === 'cerradas'">
        <div v-if="solicitudesCerradas.length === 0" class="text-center py-16 text-gray-400 text-sm">
          No hay comisiones cerradas.
        </div>
        <div v-else class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-xs text-gray-500 uppercase border-b border-gray-100" style="background-color:#f9f7fb;">
                <th class="px-4 py-3 text-left font-semibold">N°</th>
                <th class="px-4 py-3 text-left font-semibold">Empleado</th>
                <th class="px-4 py-3 text-left font-semibold">Destino</th>
                <th class="px-4 py-3 text-right font-semibold">Total Pagado</th>
                <th class="px-4 py-3 text-left font-semibold">Estado</th>
                <th class="px-4 py-3 text-left font-semibold"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="sol in solicitudesCerradas" :key="sol.id"
                class="border-b border-gray-50 hover:bg-gray-50 transition">
                <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ sol.numero_solicitud }}</td>
                <td class="px-4 py-3 font-medium text-gray-800">{{ sol.empleado?.apellido_emp }} {{ sol.empleado?.nombre_emp }}</td>
                <td class="px-4 py-3 text-gray-600">{{ sol.destino }}</td>
                <td class="px-4 py-3 text-right font-semibold text-gray-800">
                  ${{ formatMonto(sol.ficha_liquidacion?.total_a_pagar ?? 0) }}
                </td>
                <td class="px-4 py-3">
                  <span :class="['text-xs font-semibold px-2 py-0.5 rounded-full', badgeEstadoSol(sol.estado).class]">
                    {{ badgeEstadoSol(sol.estado).label }}
                  </span>
                </td>
                <td class="px-4 py-3">
                  <button v-if="sol.ficha_liquidacion" @click="descargarPdfFicha(sol)"
                    class="p-1.5 text-red-400 hover:text-red-600 hover:bg-red-50 rounded transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
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

  <!-- ══════════════════ MODAL FICHA DE LIQUIDACIÓN ══════════════════ -->
  <div v-if="modalFicha" class="fixed inset-0 z-50 flex items-start justify-center pt-8 pb-4 px-4">
    <div class="fixed inset-0 bg-black/40" @click="modalFicha = false"/>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-xl max-h-[90vh] flex flex-col z-10 overflow-hidden">
      <div class="flex items-center justify-between px-5 py-4 text-white" style="background-color:#5c4a6e;">
        <h2 class="text-base font-bold">Ficha de Liquidación</h2>
        <button @click="modalFicha = false" class="text-white/80 hover:text-white">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
      <div class="overflow-y-auto flex-1 p-5 space-y-3">
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="text-xs font-semibold text-gray-600 mb-1 block">Valor por Día ($) *</label>
            <input v-model.number="fichaForm.valor_por_dia" type="number" step="0.01" min="0"
              class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]"/>
          </div>
          <div>
            <label class="text-xs font-semibold text-gray-600 mb-1 block">Días de Viáticos *</label>
            <input v-model.number="fichaForm.dias_viaticos" type="number" min="0"
              class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]"/>
          </div>
          <div>
            <label class="text-xs font-semibold text-gray-600 mb-1 block">Anticipo Viático ($)</label>
            <input v-model.number="fichaForm.anticipo_viatico" type="number" step="0.01" min="0"
              class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none"/>
          </div>
          <div v-if="fichaTarget?.tipo === 'INTERIOR'">
            <label class="text-xs font-semibold text-gray-600 mb-1 block">Anticipo Combustible ($)</label>
            <input v-model.number="fichaForm.anticipo_combustible" type="number" step="0.01" min="0"
              class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none"/>
          </div>
        </div>

        <div class="border-t border-gray-100 pt-3">
          <p class="text-xs font-semibold text-gray-500 mb-2">JUSTIFICATIVOS CON COMPROBANTES</p>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs text-gray-600 mb-1 block">Alimentación ($)</label>
              <input v-model.number="fichaForm.justif_alimentacion" type="number" step="0.01" min="0"
                class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none"/>
            </div>
            <div>
              <label class="text-xs text-gray-600 mb-1 block">Alojamiento ($)</label>
              <input v-model.number="fichaForm.justif_alojamiento" type="number" step="0.01" min="0"
                class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none"/>
            </div>
          </div>
        </div>

        <div class="border-t border-gray-100 pt-3">
          <p class="text-xs font-semibold text-gray-500 mb-2">MOVILIZACIÓN</p>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="text-xs text-gray-600 mb-1 block">Movilización ($)</label>
              <input v-model.number="fichaForm.movilizacion" type="number" step="0.01" min="0"
                class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none"/>
            </div>
            <div>
              <label class="text-xs text-gray-600 mb-1 block">Peajes / Parqueaderos ($)</label>
              <input v-model.number="fichaForm.peajes_parqueaderos" type="number" step="0.01" min="0"
                class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none"/>
            </div>
            <div v-if="fichaTarget?.tipo === 'INTERIOR'">
              <label class="text-xs text-gray-600 mb-1 block">Combustibles ($)</label>
              <input v-model.number="fichaForm.combustibles" type="number" step="0.01" min="0"
                class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none"/>
            </div>
            <div>
              <label class="text-xs text-gray-600 mb-1 block">Otros Gastos ($)</label>
              <input v-model.number="fichaForm.otros_gastos" type="number" step="0.01" min="0"
                class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none"/>
            </div>
          </div>
        </div>

        <!-- Preview cálculo -->
        <div class="bg-purple-50 rounded-lg p-3 text-sm space-y-1" v-if="fichaForm.valor_por_dia > 0">
          <div class="flex justify-between"><span class="text-gray-600">Total Viático:</span><span class="font-semibold">${{ calcularPreview.totalViatico }}</span></div>
          <div class="flex justify-between"><span class="text-gray-600">Total Anticipo:</span><span class="font-semibold">- ${{ calcularPreview.totalAnticipo }}</span></div>
          <div class="flex justify-between border-t border-purple-100 pt-1 mt-1">
            <span class="font-bold" :class="calcularPreview.esNegativo ? 'text-orange-600' : 'text-green-700'">
              {{ calcularPreview.esNegativo ? 'Empleado devuelve:' : 'Institución paga:' }}
            </span>
            <span class="font-bold text-lg" :class="calcularPreview.esNegativo ? 'text-orange-600' : 'text-green-700'">
              ${{ calcularPreview.total }}
            </span>
          </div>
        </div>

        <p v-if="errorFicha" class="text-red-600 text-xs">{{ errorFicha }}</p>
      </div>
      <div class="p-5 border-t border-gray-100 flex justify-end gap-3">
        <button @click="modalFicha = false" class="px-4 py-2 text-sm text-gray-600">Cancelar</button>
        <button @click="guardarFicha" :disabled="guardando"
          class="px-5 py-2 text-sm font-semibold text-white rounded-lg hover:opacity-90 disabled:opacity-50"
          style="background-color:#5c4a6e;">
          {{ guardando ? 'Guardando...' : 'Guardar Ficha' }}
        </button>
      </div>
    </div>
  </div>

  <!-- ══════════════════ MODAL CUR ══════════════════ -->
  <div v-if="modalCur" class="fixed inset-0 z-50 flex items-center justify-center px-4">
    <div class="fixed inset-0 bg-black/40" @click="modalCur = false"/>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden z-10">
      <div class="px-6 py-4 text-white font-bold text-base" style="background-color:#5c4a6e">{{ curTitulo }}</div>
      <div class="p-6">
        <label class="text-xs font-semibold text-gray-600 mb-1 block">N° CUR (eSIGEF) *</label>
        <input v-model="curValor" type="text" placeholder="Ej: 2026-CUR-00123"
          class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]"/>
        <p v-if="errorCur" class="text-red-600 text-xs mt-2">{{ errorCur }}</p>
        <div class="flex justify-end gap-3 mt-4">
          <button @click="modalCur = false" class="px-4 py-2 text-sm text-gray-600">Cancelar</button>
          <button @click="guardarCur" :disabled="guardando"
            class="px-5 py-2 text-sm font-semibold text-white rounded-lg hover:opacity-90 transition disabled:opacity-50"
            style="background-color:#5c4a6e;">
            {{ guardando ? 'Guardando...' : 'Registrar' }}
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════════════════ MODAL DEVOLUCIÓN ══════════════════ -->
  <div v-if="modalDevolucion" class="fixed inset-0 z-50 flex items-center justify-center px-4">
    <div class="fixed inset-0 bg-black/40" @click="modalDevolucion = false"/>
    <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden z-10">
      <div class="px-6 py-4 text-white font-bold text-base" style="background-color:#5c4a6e">Registrar Devolución</div>
      <div class="p-6">
        <div class="space-y-3">
          <div>
            <label class="text-xs font-semibold text-gray-600 mb-1 block">N° Comprobante de Depósito *</label>
            <input v-model="devolucionForm.comprobante" type="text" placeholder="N° del comprobante"
              class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]"/>
          </div>
          <div>
            <label class="text-xs font-semibold text-gray-600 mb-1 block">Fecha de Devolución *</label>
            <input v-model="devolucionForm.fecha" type="date"
              class="w-full text-sm border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#5c4a6e]"/>
          </div>
        </div>
        <p v-if="errorCur" class="text-red-600 text-xs mt-2">{{ errorCur }}</p>
        <div class="flex justify-end gap-3 mt-4">
          <button @click="modalDevolucion = false" class="px-4 py-2 text-sm text-gray-600">Cancelar</button>
          <button @click="guardarDevolucion" :disabled="guardando"
            class="px-5 py-2 text-sm font-semibold bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition disabled:opacity-50">
            {{ guardando ? 'Guardando...' : 'Registrar' }}
          </button>
        </div>
      </div>
    </div>
  </div>

</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const auth = useAuthStore()

const cargando    = ref(true)
const solicitudes = ref([])
const miRol       = ref({})
const tabActivo   = ref('enPago')

const modalFicha    = ref(false)
const fichaTarget   = ref(null)
const errorFicha    = ref('')
const guardando     = ref(false)

const modalCur   = ref(false)
const curTitulo  = ref('')
const curValor   = ref('')
const curCampo   = ref('')
const curTarget  = ref(null)
const curTipo    = ref('') // 'anticipo' | 'liquidacion'
const errorCur   = ref('')

const modalDevolucion = ref(false)
const devolucionTarget = ref(null)
const devolucionForm   = ref({ comprobante: '', fecha: new Date().toISOString().slice(0,10) })

const fichaFormVacio = () => ({
  valor_por_dia: 0,
  dias_viaticos: 0,
  anticipo_viatico: 0,
  anticipo_combustible: 0,
  justif_alimentacion: 0,
  justif_alojamiento: 0,
  movilizacion: 0,
  peajes_parqueaderos: 0,
  combustibles: 0,
  otros_gastos: 0,
})
const fichaForm = ref(fichaFormVacio())

const solicitudesConAnticipo = computed(() =>
  solicitudes.value.filter(s => s.tiene_anticipo && s.anticipo)
)

const solicitudesEnPago = computed(() =>
  solicitudes.value.filter(s => ['EN_PAGO', 'EN_LIQUIDACION', 'POR_COBRAR'].includes(s.estado))
)

const solicitudesCerradas = computed(() =>
  solicitudes.value.filter(s => s.estado === 'CERRADO')
)

const pendAnticipo = computed(() =>
  solicitudesConAnticipo.value.filter(s => s.anticipo?.estado !== 'PAGADO').length
)

const pendEnPago = computed(() =>
  solicitudesEnPago.value.length
)

const tabsVisibles = computed(() => [
  { key: 'enPago',   label: 'En Proceso', badge: pendEnPago.value > 0 ? pendEnPago.value : null },
  { key: 'anticipos', label: 'Anticipos', badge: pendAnticipo.value > 0 ? pendAnticipo.value : null },
  { key: 'cerradas', label: 'Cerradas',   badge: null },
])

const calcularPreview = computed(() => {
  const f = fichaForm.value
  const totalViatico = Math.round(f.valor_por_dia * f.dias_viaticos * 100) / 100
  const totalAnticipo = Math.round(((f.anticipo_viatico || 0) + (f.anticipo_combustible || 0)) * 100) / 100
  const justifTotal = (f.justif_alimentacion || 0) + (f.justif_alojamiento || 0)
  const movTotal = (f.movilizacion || 0) + (f.peajes_parqueaderos || 0) + (f.combustibles || 0)
  const viaticoPagar = totalViatico - totalAnticipo
  const total = Math.round((viaticoPagar + movTotal + (f.otros_gastos || 0)) * 100) / 100
  return {
    totalViatico: totalViatico.toFixed(2),
    totalAnticipo: totalAnticipo.toFixed(2),
    total: Math.abs(total).toFixed(2),
    esNegativo: total < 0,
  }
})

function formatMonto(v) {
  if (v === null || v === undefined) return '0.00'
  return parseFloat(v).toFixed(2)
}

function formatFecha(f) {
  if (!f) return '—'
  const [y, m, d] = f.split('-')
  return `${d}/${m}/${y}`
}

function badgeAnticipo(estado) {
  const mapa = {
    PENDIENTE: { class: 'bg-amber-100 text-amber-700', label: 'Pendiente' },
    APROBADO: { class: 'bg-blue-100 text-blue-700', label: 'Aprobado' },
    EN_PROCESO: { class: 'bg-indigo-100 text-indigo-700', label: 'En Proceso' },
    PAGADO: { class: 'bg-green-100 text-green-700', label: 'Pagado' },
  }
  return mapa[estado] || { class: 'bg-gray-100 text-gray-600', label: estado || '—' }
}

function badgeEstadoSol(estado) {
  const mapa = {
    EN_PAGO: { class: 'bg-blue-100 text-blue-700', label: 'En Pago' },
    EN_LIQUIDACION: { class: 'bg-cyan-100 text-cyan-700', label: 'En Liquidación' },
    POR_COBRAR: { class: 'bg-orange-100 text-orange-700', label: 'Por Cobrar' },
    CERRADO: { class: 'bg-emerald-100 text-emerald-800', label: 'Cerrado' },
  }
  return mapa[estado] || { class: 'bg-gray-100 text-gray-600', label: estado }
}

async function cargar() {
  cargando.value = true
  try {
    const [rolResp, solResp] = await Promise.all([
      api.get('/comisiones/mi-rol'),
      api.get('/comisiones/solicitudes', { params: { incluir: 'ficha,anticipo' } }),
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

function abrirFicha(sol) {
  fichaTarget.value = sol
  fichaForm.value = fichaFormVacio()
  errorFicha.value = ''
  modalFicha.value = true
}

async function guardarFicha() {
  errorFicha.value = ''
  if (!fichaForm.value.valor_por_dia || !fichaForm.value.dias_viaticos) {
    errorFicha.value = 'Ingrese el valor por día y los días de viáticos.'
    return
  }
  guardando.value = true
  try {
    await api.post(`/comisiones/solicitudes/${fichaTarget.value.id}/liquidacion`, fichaForm.value)
    modalFicha.value = false
    await cargar()
  } catch (e) {
    errorFicha.value = e.response?.data?.message || 'Error al guardar.'
  } finally {
    guardando.value = false
  }
}

function abrirCurAnticipo(sol, campo) {
  curTarget.value = sol.anticipo
  curCampo.value = campo
  curTipo.value = 'anticipo'
  curTitulo.value = campo === 'cur-compromiso' ? 'CUR de Compromiso (Anticipo)' : 'CUR de Devengado (Anticipo)'
  curValor.value = ''
  errorCur.value = ''
  modalCur.value = true
}

function abrirCurLiq(sol, campo) {
  curTarget.value = sol.ficha_liquidacion
  curCampo.value = campo
  curTipo.value = 'liquidacion'
  curTitulo.value = campo === 'cur-compromiso' ? 'CUR de Compromiso (Liquidación)' : 'CUR de Devengado (Liquidación)'
  curValor.value = ''
  errorCur.value = ''
  modalCur.value = true
}

async function guardarCur() {
  if (!curValor.value.trim()) {
    errorCur.value = 'Ingrese el número CUR.'
    return
  }
  guardando.value = true
  errorCur.value = ''
  try {
    const campo = curCampo.value === 'cur-compromiso' ? 'cur_compromiso' : 'cur_devengado'
    const endpoint = curTipo.value === 'anticipo'
      ? `/comisiones/anticipos/${curTarget.value.id}/${curCampo.value}`
      : `/comisiones/liquidaciones/${curTarget.value.id}/${curCampo.value}`
    await api.patch(endpoint, { [campo]: curValor.value })
    modalCur.value = false
    await cargar()
  } catch (e) {
    errorCur.value = e.response?.data?.message || 'Error.'
  } finally {
    guardando.value = false
  }
}

async function confirmarPagoAnticipo(sol) {
  const fecha = prompt('Fecha de pago (YYYY-MM-DD):')
  if (!fecha) return
  try {
    await api.patch(`/comisiones/anticipos/${sol.anticipo.id}/confirmar-pago`, { fecha_pago: fecha })
    await cargar()
  } catch (e) {
    alert(e.response?.data?.message || 'Error.')
  }
}

async function confirmarPagoFinal(sol) {
  if (!confirm('¿Confirmar el pago final de viáticos?')) return
  try {
    await api.patch(`/comisiones/liquidaciones/${sol.ficha_liquidacion.id}/confirmar-pago`)
    await cargar()
  } catch (e) {
    alert(e.response?.data?.message || 'Error.')
  }
}

function abrirDevolucion(sol) {
  devolucionTarget.value = sol
  devolucionForm.value = { comprobante: '', fecha: new Date().toISOString().slice(0,10) }
  errorCur.value = ''
  modalDevolucion.value = true
}

async function guardarDevolucion() {
  if (!devolucionForm.value.comprobante || !devolucionForm.value.fecha) {
    errorCur.value = 'Complete todos los campos.'
    return
  }
  guardando.value = true
  try {
    await api.patch(`/comisiones/liquidaciones/${devolucionTarget.value.ficha_liquidacion.id}/registrar-devolucion`, {
      comprobante_devolucion: devolucionForm.value.comprobante,
      fecha_devolucion: devolucionForm.value.fecha,
    })
    modalDevolucion.value = false
    await cargar()
  } catch (e) {
    errorCur.value = e.response?.data?.message || 'Error.'
  } finally {
    guardando.value = false
  }
}

async function descargarPdfSolicitud(sol) {
  try {
    const resp = await api.get(`/comisiones/solicitudes/${sol.id}/pdf`, { responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
    window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch { alert('Error al generar PDF.') }
}

async function descargarPdfFicha(sol) {
  try {
    const resp = await api.get(`/comisiones/solicitudes/${sol.id}/liquidacion/pdf`, { responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
    window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch { alert('Error al generar PDF.') }
}
</script>
