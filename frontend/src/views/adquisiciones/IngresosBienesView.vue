<template>
  <div>
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Ingresos de Bienes</h1>
      <button @click="abrirCrear" class="text-white px-4 py-2 rounded-lg text-sm hover:opacity-90" style="background-color:#4a5e3a;">
        + Nuevo ingreso
      </button>
    </div>

    <!-- Filtros -->
    <div class="flex gap-3 mb-3 flex-wrap items-end">
      <select v-model="filtroEstado" class="border rounded-lg px-3 py-2 text-sm">
        <option value="">Todos los estados</option>
        <option value="BORRADOR">Borrador</option>
        <option value="RECIBIDO">Recibido</option>
      </select>
      <select v-model="filtroTipo" class="border rounded-lg px-3 py-2 text-sm">
        <option value="">Todos los tipos</option>
        <option value="COMPRA">Compra</option>
        <option value="DONACION">Donación</option>
      </select>
      <button @click="buscar" class="text-white px-5 py-2 rounded-lg text-sm hover:opacity-90" style="background-color:#4a5e3a;">
        Buscar
      </button>
    </div>

    <!-- Contador -->
    <div class="mb-3">
      <span v-if="!hasBuscado"
        class="inline-block bg-blue-50 text-blue-700 text-xs font-semibold px-3 py-1 rounded-full border border-blue-200">
        Seleccione los filtros y presione Buscar
      </span>
      <span v-else-if="ordenes.length > 0"
        class="inline-block bg-green-100 text-green-800 text-xs font-semibold px-3 py-1 rounded-full border border-green-300">
        Se encontraron {{ ordenes.length }} ingreso{{ ordenes.length !== 1 ? 's' : '' }}
      </span>
      <span v-else
        class="inline-block bg-red-100 text-red-700 text-xs font-semibold px-3 py-1 rounded-full border border-red-300">
        No se encontraron ingresos registrados
      </span>
    </div>

    <!-- Lista -->
    <div class="bg-white rounded-xl shadow overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr style="background-color: #4a5e3a;">
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">#</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">Tipo</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">Documento</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">Proveedor</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">Fecha Doc.</th>
            <th class="text-right px-4 py-3 text-white font-semibold whitespace-nowrap">Subtotal</th>
            <th class="text-right px-4 py-3 text-white font-semibold whitespace-nowrap">IVA</th>
            <th class="text-right px-4 py-3 text-white font-semibold whitespace-nowrap">Total</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">Estado</th>
            <th class="text-left px-4 py-3 text-white font-semibold whitespace-nowrap">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!ordenes.length">
            <td colspan="10" class="text-center py-8 text-gray-400">Sin ingresos registrados</td>
          </tr>
          <tr v-for="o in ordenes" :key="o.id" class="border-b hover:bg-green-50">
            <td class="px-4 py-3 text-gray-400 text-xs whitespace-nowrap">{{ o.id }}</td>
            <td class="px-4 py-3 whitespace-nowrap">
              <span :class="o.tipo_ingreso === 'COMPRA' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'"
                class="px-2 py-0.5 rounded-full text-xs font-medium">{{ o.tipo_ingreso }}</span>
            </td>
            <td class="px-4 py-3 whitespace-nowrap">
              <div class="text-xs text-gray-500">{{ o.tipo_documento || '-' }}</div>
              <div class="font-mono text-xs">{{ o.numero_documento || '-' }}</div>
            </td>
            <td class="px-4 py-3 text-gray-700">{{ o.proveedor?.nombre || '-' }}</td>
            <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">{{ o.fecha_documento || '-' }}</td>
            <td class="px-4 py-3 text-right font-mono text-xs whitespace-nowrap">${{ fmt(o.subtotal) }}</td>
            <td class="px-4 py-3 text-right font-mono text-xs whitespace-nowrap">${{ fmt(o.iva_valor) }}</td>
            <td class="px-4 py-3 text-right font-mono font-semibold whitespace-nowrap">${{ fmt(o.total) }}</td>
            <td class="px-4 py-3 whitespace-nowrap">
              <span :class="o.estado === 'BORRADOR' ? 'bg-gray-100 text-gray-600' : 'bg-green-100 text-green-700'"
                class="px-2 py-0.5 rounded-full text-xs font-medium">{{ o.estado }}</span>
            </td>
            <td class="px-4 py-3 whitespace-nowrap flex gap-2">
              <button @click="verDetalle(o)" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Ver</button>
              <button v-if="o.estado === 'RECIBIDO'" @click="descargarPdf(o.id)"
                class="text-xs text-purple-600 hover:text-purple-800 font-medium">PDF</button>
              <button v-if="o.estado === 'RECIBIDO'" @click="abrirReverso(o.id)"
                class="text-xs text-red-600 hover:text-red-800 font-medium">Reversar</button>
              <button v-if="o.estado === 'BORRADOR'" @click="abrirEditar(o)"
                class="text-xs text-amber-600 hover:text-amber-800 font-medium">Editar</button>
              <button v-if="o.estado === 'BORRADOR'" @click="confirmar(o.id)"
                class="text-xs text-green-600 hover:text-green-800 font-medium">Confirmar</button>
              <button v-if="o.estado === 'BORRADOR'" @click="eliminar(o.id)"
                class="text-xs text-red-500 hover:text-red-700">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ══ MODAL CREAR / EDITAR ══ -->
    <div v-if="modalForm.show" class="fixed inset-0 bg-black/50 flex items-start justify-center z-50 p-4 overflow-y-auto">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-7xl my-4 overflow-hidden">

        <!-- Título -->
        <div class="px-6 py-4" style="background-color:#4a5e3a;">
          <h2 class="text-lg font-bold text-white">{{ modalForm.editando ? 'Editar Ingreso #' + modalForm.id : 'Nuevo Ingreso de Bienes' }}</h2>
        </div>

        <div class="p-6">

        <!-- Nav pestañas -->
        <div class="flex border-b mb-5">
          <button v-for="tab in tabs" :key="tab.key" @click="tabActiva = tab.key"
            :class="tabActiva === tab.key
              ? 'border-b-2 font-semibold text-white px-5 py-2 text-sm -mb-px'
              : 'px-5 py-2 text-sm text-gray-500 hover:text-gray-700'"
            :style="tabActiva === tab.key ? 'border-color:#4a5e3a; background-color:#4a5e3a; border-radius:6px 6px 0 0;' : ''">
            {{ tab.label }}
            <span v-if="tab.key === 'bienes' && form.detalles.length"
              class="ml-1.5 bg-white text-xs font-bold rounded-full px-1.5"
              :style="tabActiva === 'bienes' ? 'color:#4a5e3a' : 'color:#4a5e3a; background-color:#e5e7eb'">
              {{ form.detalles.length }}
            </span>
          </button>
        </div>

        <!-- ── TAB DATOS ── -->
        <div v-show="tabActiva === 'datos'">
          <div class="grid grid-cols-2 md:grid-cols-4 gap-3 p-4 bg-gray-50 rounded-lg">
            <div v-if="form.tipo_ingreso === 'COMPRA'">
              <label class="block text-xs text-gray-600 mb-1">Proceso de Contratación *</label>
              <select v-model="form.proceso_contratacion" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-green-300 outline-none">
                <option value="">Seleccionar...</option>
                <option v-for="p in procesosActivos" :key="p" :value="p">{{ p }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-600 mb-1">Tipo Documento</label>
              <select v-model="form.tipo_documento" class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-green-300 outline-none">
                <option value="">Sin documento</option>
                <option value="FACTURA">FACTURA</option>
                <option value="NOTA DE ENTREGA">NOTA DE ENTREGA</option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-gray-600 mb-1">
                Número Documento{{ form.tipo_documento === 'FACTURA' ? ' *' : '' }}
              </label>
              <input v-model="form.numero_documento" v-uppercase type="text" maxlength="50"
                placeholder="ej: 001-001-000001234"
                class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-green-300 outline-none" />
            </div>
            <!-- Proveedor -->
            <div class="md:col-span-2 relative">
              <div class="flex gap-2 items-end">
                <div class="relative flex-1">
                  <label class="block text-xs text-gray-600 mb-1">Proveedor{{ form.tipo_ingreso === 'COMPRA' ? ' *' : '' }}</label>
                  <input v-model="busquedaProveedor"
                    @input="filtrarProveedores" @keydown.escape="sugerenciasProveedor = []"
                    type="text" placeholder="Buscar por RUC o nombre..."
                    class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-green-300 outline-none" />
                  <div v-if="sugerenciasProveedor.length"
                    class="absolute z-30 bg-white border rounded-lg shadow-lg w-full mt-1 max-h-48 overflow-y-auto">
                    <div v-for="p in sugerenciasProveedor" :key="p.id"
                      @click="seleccionarProveedor(p)"
                      class="px-3 py-2 text-sm hover:bg-amber-50 cursor-pointer border-b last:border-0">
                      <div class="font-medium text-gray-800">{{ p.nombre }}</div>
                      <div class="text-xs text-gray-400 font-mono">RUC: {{ p.ruc }}</div>
                    </div>
                  </div>
                </div>
                <div class="w-36">
                  <label class="block text-xs text-gray-600 mb-1">RUC</label>
                  <input :value="proveedorSeleccionado?.ruc || ''" type="text" readonly
                    class="w-full border rounded px-3 py-2 text-sm bg-gray-50 text-gray-600 font-mono" />
                </div>
              </div>
            </div>
            <div class="md:col-span-3">
              <label class="block text-xs text-gray-600 mb-1">Observación</label>
              <input v-model="form.observacion" v-uppercase type="text"
                class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-green-300 outline-none" />
            </div>
          </div>
        </div>

        <!-- ── TAB BIENES ── -->
        <div v-show="tabActiva === 'bienes'">
          <!-- Buscador -->
          <div class="mb-3 relative">
            <label class="block text-xs text-gray-600 mb-1">Buscar artículo por código o descripción</label>
            <input v-model="busquedaArticulo" @input="filtrarArticulos" @keydown.escape="articulosSugeridos = []"
              type="text" placeholder="Escribe para buscar..."
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-green-300 outline-none" />
            <div v-if="articulosSugeridos.length"
              class="absolute z-20 bg-white border rounded-lg shadow-lg w-full mt-1 max-h-52 overflow-y-auto">
              <div v-for="a in articulosSugeridos" :key="a.id" @click="agregarArticulo(a)"
                class="px-4 py-2 text-sm hover:bg-gray-100 cursor-pointer border-b last:border-0">
                <div class="flex justify-between items-center">
                  <div>
                    <span class="font-mono text-xs text-gray-400 mr-2">{{ a.codigo }}</span>
                    <span class="font-medium">{{ a.nombre }}</span>
                  </div>
                  <div class="text-xs text-gray-500 text-right">
                    <div>Stock: {{ a.stock_actual }}</div>
                    <div>${{ fmt(a.precio_unitario) }} | {{ a.iva_porcentaje }}% IVA</div>
                  </div>
                </div>
                <div class="text-xs text-gray-400 mt-0.5">{{ a.nivel1 }} / {{ a.nivel2 }}</div>
              </div>
            </div>
          </div>
          <!-- Tabla bienes + monetario unificada -->
          <div class="border rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
              <table class="w-full text-xs">
                <thead style="background-color:#4a5e3a;" class="text-white">
                  <tr>
                    <th class="text-left px-3 py-2 font-semibold whitespace-nowrap">Código</th>
                    <th class="text-left px-3 py-2 font-semibold whitespace-nowrap">Niv.1</th>
                    <th class="text-left px-3 py-2 font-semibold whitespace-nowrap">Niv.2</th>
                    <th class="text-left px-3 py-2 font-semibold">Descripción</th>
                    <th class="text-right px-3 py-2 font-semibold whitespace-nowrap">Cantidad</th>
                    <th class="text-right px-3 py-2 font-semibold whitespace-nowrap">Precio <span class="font-normal opacity-70 text-xs">(c/IVA o s/IVA)</span></th>
                    <th class="text-right px-3 py-2 font-semibold whitespace-nowrap">IVA %</th>
                    <th class="text-right px-3 py-2 font-semibold whitespace-nowrap">Subtotal</th>
                    <th class="text-right px-3 py-2 font-semibold whitespace-nowrap">IVA $</th>
                    <th class="text-right px-3 py-2 font-semibold whitespace-nowrap">Total</th>
                    <th class="w-8"></th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-if="!form.detalles.length">
                    <td colspan="11" class="text-center py-8 text-gray-400 italic">Busca un artículo arriba para agregarlo</td>
                  </tr>
                  <tr v-for="(det, i) in form.detalles" :key="i" class="border-t hover:bg-amber-50">
                    <td class="px-3 py-1.5 font-mono text-gray-500 whitespace-nowrap">{{ det.codigo }}</td>
                    <td class="px-3 py-1.5 text-gray-500 whitespace-nowrap">{{ det.nivel1 || '-' }}</td>
                    <td class="px-3 py-1.5 text-gray-500 whitespace-nowrap">{{ det.nivel2 || '-' }}</td>
                    <td class="px-3 py-1.5 font-medium">{{ det.nombre }}</td>
                    <td class="px-3 py-1.5">
                      <input v-model.number="det.cantidad" type="number" step="0.01" min="0.01"
                        @input="recalcularLinea(det)"
                        class="w-24 border rounded px-2 py-1 text-right focus:ring-1 outline-none text-xs" />
                    </td>
                    <td class="px-3 py-1.5">
                      <div class="flex items-center gap-1.5">
                        <div class="relative">
                          <span class="absolute left-1.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs">$</span>
                          <input v-model.number="det.precio_unitario" type="number" step="0.00001" min="0"
                            @input="recalcularLinea(det)"
                            class="w-24 border rounded pl-4 pr-1 py-1 text-right focus:ring-1 outline-none text-xs" />
                        </div>
                        <label class="flex items-center gap-1 cursor-pointer whitespace-nowrap text-xs text-gray-600">
                          <input type="checkbox" v-model="det.precio_incluye_iva" @change="recalcularLinea(det)"
                            class="rounded accent-green-700" />
                          c/IVA
                        </label>
                      </div>
                    </td>
                    <td class="px-3 py-1.5 text-center text-xs text-gray-600 font-medium">
                      {{ det._iva_pct || 0 }}%
                    </td>
                    <td class="px-3 py-1.5 text-right font-mono whitespace-nowrap">${{ fmt(det._subtotal) }}</td>
                    <td class="px-3 py-1.5 text-right font-mono whitespace-nowrap">${{ fmt(det._iva_valor) }}</td>
                    <td class="px-3 py-1.5 text-right font-mono font-semibold whitespace-nowrap">${{ fmt(det._total_linea) }}</td>
                    <td class="px-2 py-1.5 text-center">
                      <button @click="form.detalles.splice(i, 1)" class="text-red-400 hover:text-red-600 font-bold text-base leading-none">✕</button>
                    </td>
                  </tr>
                </tbody>
                <tfoot v-if="form.detalles.length" style="background-color:#f0f4ed;" class="border-t-2">
                  <tr>
                    <td colspan="7" class="px-3 py-2 text-right font-semibold text-gray-600">TOTALES:</td>
                    <td class="px-3 py-2 text-right font-bold font-mono">${{ fmt(totales.subtotal) }}</td>
                    <td class="px-3 py-2 text-right font-bold font-mono">${{ fmt(totales.iva) }}</td>
                    <td class="px-3 py-2 text-right font-bold font-mono text-sm" style="color:#4a5e3a;">${{ fmt(totales.total) }}</td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>

        <p v-if="errorForm" class="text-red-600 text-sm mt-4">{{ errorForm }}</p>
        <div class="flex justify-end gap-3 mt-4">
          <button @click="modalForm.show = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
          <button @click="guardar" :disabled="guardando"
            class="text-white px-5 py-2 rounded-lg text-sm hover:opacity-90 disabled:opacity-50"
            style="background-color:#4a5e3a;">
            {{ guardando ? 'Guardando...' : 'Guardar ingreso' }}
          </button>
        </div>
        </div><!-- /p-6 -->
      </div>
    </div>

    <!-- ══ MODAL VER DETALLE ══ -->
    <div v-if="modalDetalle.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl p-6 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-start mb-4">
          <h2 class="text-lg font-bold">Ingreso #{{ modalDetalle.orden?.id }}</h2>
          <button @click="modalDetalle.show = false" class="text-gray-400 hover:text-gray-600 text-xl">✕</button>
        </div>

        <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm mb-4">
          <div><span class="text-gray-500">Tipo:</span>
            <span class="ml-2 font-medium">{{ modalDetalle.orden?.tipo_ingreso }}</span></div>
          <div v-if="modalDetalle.orden?.proceso_contratacion">
            <span class="text-gray-500">Proceso:</span>
            <span class="ml-2 font-medium">{{ modalDetalle.orden?.proceso_contratacion }}</span></div>
          <div v-if="modalDetalle.orden?.tipo_documento">
            <span class="text-gray-500">Documento:</span>
            <span class="ml-2 font-medium">{{ modalDetalle.orden?.tipo_documento }} {{ modalDetalle.orden?.numero_documento }}</span></div>
          <div><span class="text-gray-500">Proveedor:</span>
            <span class="ml-2 font-medium">{{ modalDetalle.orden?.proveedor?.nombre || '—' }}</span></div>
          <div v-if="modalDetalle.orden?.fecha_documento">
            <span class="text-gray-500">Fecha doc.:</span>
            <span class="ml-2">{{ modalDetalle.orden?.fecha_documento }}</span></div>
          <div v-if="modalDetalle.orden?.observacion">
            <span class="text-gray-500">Observación:</span>
            <span class="ml-2">{{ modalDetalle.orden?.observacion }}</span></div>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm border rounded-lg overflow-hidden">
            <thead style="background-color:#4a5e3a;" class="text-white">
              <tr>
                <th class="text-left px-3 py-2">Artículo</th>
                <th class="text-right px-3 py-2">Cantidad</th>
                <th class="text-right px-3 py-2">Precio</th>
                <th class="text-right px-3 py-2">IVA%</th>
                <th class="text-right px-3 py-2">Subtotal</th>
                <th class="text-right px-3 py-2">IVA $</th>
                <th class="text-right px-3 py-2">Total</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="d in modalDetalle.orden?.detalles" :key="d.id" class="border-t">
                <td class="px-3 py-2">
                  <div class="font-medium">{{ d.articulo?.nombre }}</div>
                  <div class="text-xs text-gray-400 font-mono">{{ d.articulo?.codigo }}</div>
                </td>
                <td class="px-3 py-2 text-right">{{ d.cantidad }}</td>
                <td class="px-3 py-2 text-right font-mono">${{ fmt(d.precio_unitario) }}</td>
                <td class="px-3 py-2 text-right">{{ d.iva_porcentaje }}%</td>
                <td class="px-3 py-2 text-right font-mono">${{ fmt(d.subtotal) }}</td>
                <td class="px-3 py-2 text-right font-mono">${{ fmt(d.iva_valor) }}</td>
                <td class="px-3 py-2 text-right font-mono font-semibold">${{ fmt(d.total_linea) }}</td>
              </tr>
            </tbody>
            <tfoot class="bg-gray-50 border-t-2 font-bold text-sm">
              <tr>
                <td colspan="4" class="px-3 py-2 text-right text-gray-600">Subtotal:</td>
                <td class="px-3 py-2 text-right font-mono">${{ fmt(modalDetalle.orden?.subtotal) }}</td>
                <td class="px-3 py-2 text-right font-mono">${{ fmt(modalDetalle.orden?.iva_valor) }}</td>
                <td></td>
              </tr>
              <tr>
                <td colspan="6" class="px-3 py-2 text-right text-gray-700">TOTAL:</td>
                <td class="px-3 py-2 text-right font-mono text-base" style="color:#4a5e3a;">
                  ${{ fmt(modalDetalle.orden?.total) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    <!-- ══ MODAL REVERSO ══ -->
    <div v-if="modalReverso.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4" style="background-color:#dc2626;">
          <h2 class="text-lg font-bold text-white">Reversar Ingreso</h2>
        </div>
        <div class="p-6">
        <p class="text-sm text-gray-500 mb-4">El stock y precio promedio de los artículos serán revertidos al estado anterior.</p>
        <div>
          <label class="block text-xs text-gray-600 mb-1">Motivo del reverso *</label>
          <textarea v-model="modalReverso.motivo" rows="3" maxlength="500"
            placeholder="Describa el motivo por el que se reversa este ingreso..."
            class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-orange-300 outline-none resize-none"></textarea>
          <p class="text-xs text-gray-400 text-right mt-0.5">{{ modalReverso.motivo.length }}/500</p>
        </div>
        <p v-if="modalReverso.error" class="text-red-600 text-sm mt-2">{{ modalReverso.error }}</p>
        <div class="flex justify-end gap-3 mt-4">
          <button @click="modalReverso.show = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
          <button @click="confirmarReverso" :disabled="modalReverso.guardando"
            class="bg-red-600 text-white px-5 py-2 rounded-lg text-sm hover:bg-red-700 disabled:opacity-50">
            {{ modalReverso.guardando ? 'Reversando...' : 'Confirmar Reverso' }}
          </button>
        </div>
        </div><!-- /p-6 -->
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/services/api'

const ordenes        = ref([])
const procesosActivos = ref([])
const hasBuscado     = ref(false)
const filtroEstado   = ref('')
const filtroTipo     = ref('')
const guardando      = ref(false)
const errorForm      = ref('')
const proveedores    = ref([])
const articulos      = ref([])
const ivasActivos    = ref([])
const busquedaArticulo   = ref('')
const articulosSugeridos = ref([])
const busquedaProveedor  = ref('')
const sugerenciasProveedor = ref([])
const proveedorSeleccionado = ref(null)

const tabActiva = ref('datos')
const tabs = [
  { key: 'datos',  label: 'Datos' },
  { key: 'bienes', label: 'Bienes' },
]

const modalForm    = ref({ show: false, editando: false, id: null })
const modalDetalle = ref({ show: false, orden: null })
const modalReverso = ref({ show: false, id: null, motivo: '', error: '', guardando: false })

const formInicial = () => ({
  tipo_ingreso: 'COMPRA',
  proceso_contratacion: '',
  tipo_documento: '',
  proveedor_id: '',
  numero_documento: '',
  fecha_documento: '',
  observacion: '',
  detalles: [],
})
const form = ref(formInicial())

const proveedoresActivos = computed(() => proveedores.value.filter(p => p.estado === 'ACTIVO'))

const totales = computed(() => {
  let subtotal = 0, iva = 0
  for (const d of form.value.detalles) {
    subtotal += d._subtotal || 0
    iva      += d._iva_valor || 0
  }
  return { subtotal, iva, total: subtotal + iva }
})

function fmt(v)       { return parseFloat(v || 0).toFixed(2) }
function fmtPrecio(v) { return parseFloat(v || 0).toFixed(5) }

function recalcularLinea(det) {
  const qty    = det.cantidad || 0
  const precio = det.precio_unitario || 0
  const ivaPct = det._iva_pct || 0

  if (det.precio_incluye_iva && ivaPct > 0) {
    const totalCents    = Math.round(qty * precio * 100)
    const subtotalCents = Math.round(totalCents / (1 + ivaPct / 100))
    const ivaCents      = totalCents - subtotalCents
    det._subtotal    = subtotalCents / 100
    det._iva_valor   = ivaCents / 100
    det._total_linea = totalCents / 100
  } else {
    const subCents  = Math.round(qty * precio * 100)
    const ivaCents  = Math.round(subCents * ivaPct / 100)
    det._subtotal    = subCents / 100
    det._iva_valor   = ivaCents / 100
    det._total_linea = (subCents + ivaCents) / 100
  }
}

function onIvaChange(det) {
  if (!det.iva_id || det.iva_id === '') {
    det._iva_pct = 0
    det.iva_id   = null
  } else {
    const iva = ivasActivos.value.find(i => i.id === det.iva_id)
    det._iva_pct = iva ? parseFloat(iva.porcentaje) : 0
  }
  recalcularLinea(det)
}

function filtrarArticulos() {
  const q = busquedaArticulo.value.toLowerCase().trim()
  if (!q) { articulosSugeridos.value = []; return }
  articulosSugeridos.value = articulos.value
    .filter(a => a.estado === 'ACTIVO' &&
      (a.codigo?.toLowerCase().includes(q) ||
       a.nombre?.toLowerCase().includes(q) ||
       a.nivel2?.toLowerCase().includes(q)))
    .slice(0, 12)
}

function agregarArticulo(a) {
  const existe = form.value.detalles.find(d => d.articulo_id === a.id)
  if (existe) { existe.cantidad++; recalcularLinea(existe) }
  else {
    const ivaPct = parseFloat(a.iva_porcentaje || 0)
    const precio = parseFloat(a.precio_unitario || 0)
    const sub    = Math.round(1 * precio * 100000) / 100000
    const ivaVal = Math.round(sub * ivaPct / 100 * 100000) / 100000
    form.value.detalles.push({
      articulo_id:       a.id,
      codigo:            a.codigo,
      nombre:            a.nombre,
      nivel1:            a.nivel1,
      nivel2:            a.nivel2,
      cantidad:          1,
      precio_unitario:   precio,
      precio_incluye_iva: false,
      iva_id:            a.iva_id || null,
      _iva_pct:          ivaPct,
      _subtotal:         sub,
      _iva_valor:        ivaVal,
      _total_linea:      sub + ivaVal,
    })
  }
  busquedaArticulo.value = ''
  articulosSugeridos.value = []
}

async function buscar() {
  hasBuscado.value = true
  await cargar()
}

async function cargar() {
  const params = {}
  if (filtroEstado.value) params.estado = filtroEstado.value
  if (filtroTipo.value)   params.tipo   = filtroTipo.value
  const { data } = await api.get('/adquisiciones/ordenes', { params })
  ordenes.value = data
}

onMounted(async () => {
  const [p, a, iv, pr] = await Promise.all([
    api.get('/adquisiciones/proveedores'),
    api.get('/adquisiciones/articulos'),
    api.get('/adquisiciones/iva'),
    api.get('/adquisiciones/procesos-contratacion/activos'),
  ])
  proveedores.value = p.data
  procesosActivos.value = pr.data
  articulos.value   = a.data
  ivasActivos.value = iv.data.filter(i => i.activo)
})

function filtrarProveedores() {
  // Si el usuario escribe de nuevo, limpia la selección anterior
  if (proveedorSeleccionado.value) {
    form.value.proveedor_id = ''
    proveedorSeleccionado.value = null
  }
  const q = busquedaProveedor.value.toLowerCase().trim()
  if (!q) { sugerenciasProveedor.value = []; return }
  sugerenciasProveedor.value = proveedoresActivos.value
    .filter(p => p.nombre.toLowerCase().includes(q) || p.ruc.includes(q))
    .slice(0, 10)
}

function seleccionarProveedor(p) {
  form.value.proveedor_id   = p.id
  proveedorSeleccionado.value = p
  busquedaProveedor.value   = p.nombre
  sugerenciasProveedor.value = []
}

async function descargarPdf(id) {
  try {
    const { data } = await api.get(`/adquisiciones/ordenes/${id}/pdf`, { responseType: 'blob' })
    const url  = window.URL.createObjectURL(new Blob([data], { type: 'application/pdf' }))
    const link = document.createElement('a')
    link.href  = url
    link.setAttribute('download', `ingreso-bodega-${id}.pdf`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch { alert('Error al generar el PDF') }
}

function abrirCrear() {
  form.value = formInicial()
  form.value.fecha_documento = new Date().toISOString().split('T')[0]
  busquedaArticulo.value = ''
  articulosSugeridos.value = []
  busquedaProveedor.value = ''
  sugerenciasProveedor.value = []
  proveedorSeleccionado.value = null
  errorForm.value = ''
  tabActiva.value = 'datos'
  modalForm.value = { show: true, editando: false, id: null }
}

async function abrirEditar(o) {
  const { data } = await api.get(`/adquisiciones/ordenes/${o.id}`)
  form.value = {
    tipo_ingreso:         data.tipo_ingreso,
    proceso_contratacion: data.proceso_contratacion || '',
    tipo_documento:       data.tipo_documento || '',
    proveedor_id:         data.proveedor_id || '',
    numero_documento:     data.numero_documento || '',
    fecha_documento:      data.fecha_documento || '',
    observacion:          data.observacion || '',
    detalles: data.detalles.map(d => {
      const ivaPct = parseFloat(d.iva_porcentaje || 0)
      const sub    = parseFloat(d.subtotal || 0)
      const ivaVal = parseFloat(d.iva_valor || 0)
      return {
        articulo_id:     d.articulo_id,
        codigo:          d.articulo?.codigo,
        nombre:          d.articulo?.nombre,
        nivel1:          d.articulo?.nivel1,
        nivel2:          d.articulo?.nivel2,
        cantidad:        parseFloat(d.cantidad),
        precio_unitario: parseFloat(d.precio_unitario),
        iva_id:          d.iva_id || null,
        _iva_pct:        ivaPct,
        _subtotal:       sub,
        _iva_valor:      ivaVal,
        _total_linea:    parseFloat(d.total_linea || 0),
      }
    }),
  }
  if (data.proveedor) {
    proveedorSeleccionado.value = data.proveedor
    busquedaProveedor.value = data.proveedor.nombre
  } else {
    proveedorSeleccionado.value = null
    busquedaProveedor.value = ''
  }
  busquedaArticulo.value = ''
  articulosSugeridos.value = []
  sugerenciasProveedor.value = []
  errorForm.value = ''
  tabActiva.value = 'datos'
  modalForm.value = { show: true, editando: true, id: o.id }
}

function verDetalle(o) {
  modalDetalle.value = { show: true, orden: o }
}

async function guardar() {
  errorForm.value = ''
  if (!form.value.tipo_ingreso) { errorForm.value = 'Seleccione el tipo de ingreso.'; return }
  if (form.value.tipo_ingreso === 'COMPRA' && !form.value.proceso_contratacion) {
    errorForm.value = 'Seleccione el proceso de contratación.'; return
  }
  if (form.value.tipo_documento === 'FACTURA' && !form.value.numero_documento) {
    errorForm.value = 'El número de factura es obligatorio cuando el tipo de documento es FACTURA.'; return
  }
  if (!form.value.detalles.length) { errorForm.value = 'Agregue al menos un artículo.'; return }

  const payload = {
    tipo_ingreso:         form.value.tipo_ingreso,
    proceso_contratacion: form.value.proceso_contratacion || null,
    tipo_documento:       form.value.tipo_documento || null,
    proveedor_id:         form.value.proveedor_id || null,
    numero_documento:     form.value.numero_documento || null,
    fecha_documento:      form.value.fecha_documento || null,
    observacion:          form.value.observacion || null,
    detalles: form.value.detalles.map(d => ({
      articulo_id:        d.articulo_id,
      cantidad:           d.cantidad,
      precio_unitario:    d.precio_unitario,
      precio_incluye_iva: d.precio_incluye_iva ? 1 : 0,
      iva_id:             d.iva_id || null,
    })),
  }

  guardando.value = true
  try {
    if (modalForm.value.editando) {
      await api.put(`/adquisiciones/ordenes/${modalForm.value.id}`, payload)
    } else {
      await api.post('/adquisiciones/ordenes', payload)
    }
    modalForm.value.show = false
    await cargar()
  } catch (e) {
    errorForm.value = e.response?.data?.message || 'Error al guardar'
    if (e.response?.data?.errors) {
      const errs = Object.values(e.response.data.errors).flat()
      errorForm.value = errs.join(' | ')
    }
  } finally { guardando.value = false }
}

async function confirmar(id) {
  if (!confirm('¿Confirmar recepción? Se actualizará el stock y precio promedio de los artículos.')) return
  try {
    await api.patch(`/adquisiciones/ordenes/${id}/confirmar`)
    await cargar()
  } catch (e) {
    alert(e.response?.data?.message || 'Error al confirmar')
  }
}

function abrirReverso(id) {
  modalReverso.value = { show: true, id, motivo: '', error: '', guardando: false }
}

async function confirmarReverso() {
  modalReverso.value.error = ''
  if (!modalReverso.value.motivo.trim()) {
    modalReverso.value.error = 'Debe ingresar el motivo del reverso.'; return
  }
  modalReverso.value.guardando = true
  try {
    await api.patch(`/adquisiciones/ordenes/${modalReverso.value.id}/reversar`, {
      motivo_reverso: modalReverso.value.motivo,
    })
    modalReverso.value.show = false
    await cargar()
  } catch (e) {
    modalReverso.value.error = e.response?.data?.message ||
      Object.values(e.response?.data?.errors || {}).flat().join(' ') ||
      'Error al reversar'
  } finally { modalReverso.value.guardando = false }
}

async function eliminar(id) {
  if (!confirm('¿Eliminar este ingreso en borrador?')) return
  await api.delete(`/adquisiciones/ordenes/${id}`)
  await cargar()
}
</script>
