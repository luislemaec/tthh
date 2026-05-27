<template>
  <div>
    <div class="flex justify-between items-center pb-4 mb-5 border-b border-gray-100">
      <h1 class="text-xl font-bold text-gray-800 tracking-tight">Inventario de Artículos</h1>
      <div class="flex gap-2">
        <button @click="abrirConfiguracion"
          class="inline-flex items-center gap-1.5 border border-[#4a5e3a] text-[#4a5e3a] px-3.5 py-2 rounded-lg text-sm hover:bg-green-50 transition-colors font-medium">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.559.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 0 1-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.398.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.527-.737c.25-.35.273-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 0 1 .12-1.45l.773-.773a1.125 1.125 0 0 1 1.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894Z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
          Stock mínimo ({{ porcentajeMinimo }}%)
        </button>
        <button @click="abrirModalCrear"
          class="inline-flex items-center gap-1.5 bg-[#4a5e3a] text-white px-3.5 py-2 rounded-lg text-sm hover:bg-[#3a4e2a] transition-colors font-medium">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"/></svg>
          Nuevo artículo
        </button>
      </div>
    </div>

    <!-- Filtros -->
    <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 mb-4">
      <div class="flex gap-3 flex-wrap items-end">
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Nivel 1</label>
          <select v-model="filtroNivel1" @change="filtroNivel2 = ''" class="border border-gray-200 bg-white rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#4a5e3a]/30 focus:border-[#4a5e3a] outline-none">
            <option value="">Todas las categorías</option>
            <option v-for="n in nivel1s" :key="n.nivel1" :value="n.nivel1">{{ n.nivel1 }} — {{ n.descripcion }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Nivel 2</label>
          <select v-model="filtroNivel2" :disabled="!filtroNivel1" class="border border-gray-200 bg-white rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#4a5e3a]/30 focus:border-[#4a5e3a] outline-none disabled:opacity-50">
            <option value="">Todos los subniveles</option>
            <option v-for="c in nivel2sParaBuscar" :key="c.nivel2" :value="c.nivel2">{{ c.nivel2 }} — {{ c.descripcion }}</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Texto</label>
          <input v-model="busqueda" type="text" placeholder="Nombre o código..."
            class="border border-gray-200 bg-white rounded-lg px-3 py-2 text-sm w-48 focus:ring-2 focus:ring-[#4a5e3a]/30 focus:border-[#4a5e3a] outline-none" />
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Estado</label>
          <select v-model="filtroEstado" class="border border-gray-200 bg-white rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#4a5e3a]/30 focus:border-[#4a5e3a] outline-none">
            <option value="">Todos</option>
            <option value="alerta">Solo alertas</option>
            <option value="ACTIVO">Activos</option>
            <option value="INACTIVO">Inactivos</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-500 mb-1">Condición</label>
          <select v-model="filtroFisico" class="border border-gray-200 bg-white rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-[#4a5e3a]/30 focus:border-[#4a5e3a] outline-none">
            <option value="">Todas</option>
            <option value="BUENO">Bueno</option>
            <option value="MALO">Malo</option>
            <option value="INSERVIBLE">Inservible</option>
          </select>
        </div>
        <button @click="buscar" class="inline-flex items-center gap-1.5 bg-[#4a5e3a] text-white px-5 py-2 rounded-lg text-sm hover:bg-[#3a4e2a] transition-colors font-medium">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
          Buscar
        </button>
        <div class="ml-auto self-center">
          <span v-if="!hasBuscado" class="inline-flex items-center gap-1 text-xs text-blue-600 bg-blue-50 border border-blue-100 px-3 py-1.5 rounded-lg">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            Use los filtros y presione Buscar
          </span>
          <span v-else-if="articulosFiltrados.length > 0" class="inline-flex items-center gap-1 text-xs text-[#4a5e3a] bg-green-50 border border-green-200 px-3 py-1.5 rounded-lg font-medium">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
            {{ articulosFiltrados.length }} de {{ articulos.length }} artículos
          </span>
          <span v-else class="inline-flex items-center gap-1 text-xs text-red-600 bg-red-50 border border-red-100 px-3 py-1.5 rounded-lg">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
            Sin resultados
          </span>
        </div>
      </div>
    </div>

    <div class="bg-white rounded-xl shadow overflow-x-auto">
      <table class="text-xs" style="min-width: 1200px; width: 100%;">
        <thead>
          <tr style="background-color: #4a5e3a;">
            <th class="text-left px-3 py-3 text-white/80 font-semibold whitespace-nowrap text-[11px] uppercase tracking-wide">#</th>
            <th class="text-left px-3 py-3 text-white/80 font-semibold whitespace-nowrap text-[11px] uppercase tracking-wide">Código</th>
            <th class="text-left px-3 py-3 text-white/80 font-semibold whitespace-nowrap text-[11px] uppercase tracking-wide">Niv.1</th>
            <th class="text-left px-3 py-3 text-white/80 font-semibold whitespace-nowrap text-[11px] uppercase tracking-wide">Niv.2</th>
            <th class="text-left px-3 py-3 text-white/80 font-semibold whitespace-nowrap text-[11px] uppercase tracking-wide">Ítem Presup.</th>
            <th class="text-left px-3 py-3 text-white/80 font-semibold whitespace-nowrap text-[11px] uppercase tracking-wide">Descripción</th>
            <th class="text-left px-3 py-3 text-white/80 font-semibold whitespace-nowrap text-[11px] uppercase tracking-wide">Unidad</th>
            <th class="text-right px-3 py-3 text-white/80 font-semibold whitespace-nowrap text-[11px] uppercase tracking-wide">Stock</th>
            <th class="text-right px-3 py-3 text-white/80 font-semibold whitespace-nowrap text-[11px] uppercase tracking-wide">Precio s/IVA</th>
            <th class="text-right px-3 py-3 text-white/80 font-semibold whitespace-nowrap text-[11px] uppercase tracking-wide">IVA%</th>
            <th class="text-right px-3 py-3 text-white/80 font-semibold whitespace-nowrap text-[11px] uppercase tracking-wide">IVA $</th>
            <th class="text-right px-3 py-3 text-white/80 font-semibold whitespace-nowrap text-[11px] uppercase tracking-wide">Total</th>
            <th class="text-left px-3 py-3 text-white/80 font-semibold whitespace-nowrap text-[11px] uppercase tracking-wide">Estado</th>
            <th class="text-left px-3 py-3 text-white/80 font-semibold whitespace-nowrap text-[11px] uppercase tracking-wide">Condición</th>
            <th class="text-left px-3 py-3 text-white/80 font-semibold whitespace-nowrap text-[11px] uppercase tracking-wide sticky right-0" style="background-color: #4a5e3a;">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="!articulosFiltrados.length">
            <td colspan="15" class="py-16 text-center">
              <div class="flex flex-col items-center gap-2 text-gray-300">
                <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/></svg>
                <p class="text-sm text-gray-400">Sin artículos para mostrar</p>
              </div>
            </td>
          </tr>
          <tr v-for="a in articulosPaginados" :key="a.id" class="border-b border-gray-100 hover:bg-green-50/60 transition-colors">
            <td class="px-3 py-2 text-gray-400 whitespace-nowrap">{{ a.id }}</td>
            <td class="px-3 py-2 font-mono whitespace-nowrap">{{ a.codigo }}</td>
            <td class="px-3 py-2 text-gray-500 whitespace-nowrap">{{ a.nivel1 || '-' }}</td>
            <td class="px-3 py-2 text-gray-500 whitespace-nowrap">{{ a.nivel2 || '-' }}</td>
            <td class="px-3 py-2 text-gray-500 whitespace-nowrap max-w-[130px] truncate" :title="a.item_presupuestario">
              {{ a.item_presupuestario || '-' }}
            </td>
            <td class="px-3 py-2 font-medium max-w-[220px]">{{ a.nombre }}</td>
            <td class="px-3 py-2 text-gray-500 whitespace-nowrap">{{ a.unidad_medida || '-' }}</td>
            <td class="px-3 py-2 text-right whitespace-nowrap">
              <span :class="a.bajo_minimo ? 'text-red-600 font-bold' : 'text-gray-800'">{{ a.stock_actual }}</span>
              <span v-if="a.bajo_minimo" class="ml-1 bg-red-100 text-red-600 px-1 py-0.5 rounded-full">⚠</span>
            </td>
            <td class="px-3 py-2 text-right font-mono whitespace-nowrap">${{ fmt(a.precio_unitario) }}</td>
            <td class="px-3 py-2 text-right whitespace-nowrap">{{ a.iva_porcentaje || 0 }}%</td>
            <td class="px-3 py-2 text-right font-mono whitespace-nowrap">${{ fmt(a.iva_valor) }}</td>
            <td class="px-3 py-2 text-right font-mono font-semibold whitespace-nowrap">${{ fmt(a.precio_total) }}</td>
            <td class="px-3 py-2 whitespace-nowrap">
              <span :class="a.estado === 'ACTIVO' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-gray-100 text-gray-500 border border-gray-200'"
                class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold">{{ a.estado }}</span>
            </td>
            <td class="px-3 py-2 whitespace-nowrap">
              <span :class="{
                'bg-green-100 text-green-700 border border-green-200':   a.estado_fisico === 'BUENO',
                'bg-amber-100 text-amber-700 border border-amber-200':   a.estado_fisico === 'MALO',
                'bg-red-100 text-red-700 border border-red-200':         a.estado_fisico === 'INSERVIBLE',
                'bg-green-100 text-green-700 border border-green-200':   !a.estado_fisico,
              }" class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold">
                {{ a.estado_fisico || 'BUENO' }}
              </span>
            </td>
            <td class="px-3 py-2 whitespace-nowrap sticky right-0 bg-white border-l border-gray-100">
              <div class="flex gap-1.5">
                <button @click="abrirEditar(a)" class="inline-flex items-center px-2.5 py-1 rounded-md border border-blue-200 text-xs text-blue-700 hover:bg-blue-50 font-medium transition-colors">Editar</button>
                <button v-if="a.estado === 'ACTIVO'" @click="inactivar(a.id)"
                  class="inline-flex items-center px-2.5 py-1 rounded-md border border-red-200 text-xs text-red-600 hover:bg-red-50 font-medium transition-colors">Inactivar</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Paginador -->
      <div class="flex items-center justify-between px-4 py-3 border-t text-sm text-gray-600 flex-wrap gap-2">
        <div class="flex items-center gap-2">
          <span>Filas por página:</span>
          <select v-model="porPagina" @change="paginaActual = 1"
            class="border rounded px-2 py-1 text-sm">
            <option :value="10">10</option>
            <option :value="25">25</option>
            <option :value="50">50</option>
            <option :value="100">100</option>
            <option :value="99999">Todos</option>
          </select>
        </div>
        <span class="text-gray-500">
          {{ (paginaActual - 1) * porPagina + 1 }}–{{ Math.min(paginaActual * porPagina, articulosFiltrados.length) }}
          de {{ articulosFiltrados.length }}
        </span>
        <div class="flex items-center gap-1">
          <button @click="paginaActual = 1" :disabled="paginaActual === 1"
            class="px-2 py-1 rounded border disabled:opacity-40 hover:bg-gray-50 text-xs">«</button>
          <button @click="paginaActual--" :disabled="paginaActual === 1"
            class="px-3 py-1 rounded border disabled:opacity-40 hover:bg-gray-50">‹</button>
          <span class="px-3 py-1 font-medium">{{ paginaActual }} / {{ totalPaginas }}</span>
          <button @click="paginaActual++" :disabled="paginaActual === totalPaginas"
            class="px-3 py-1 rounded border disabled:opacity-40 hover:bg-gray-50">›</button>
          <button @click="paginaActual = totalPaginas" :disabled="paginaActual === totalPaginas"
            class="px-2 py-1 rounded border disabled:opacity-40 hover:bg-gray-50 text-xs">»</button>
        </div>
      </div>
    </div>

    <!-- Modal crear/editar -->
    <div v-if="modal.show" class="fixed inset-0 bg-black/50 flex items-start justify-center z-50 p-4 overflow-y-auto">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl my-4 overflow-hidden">
        <div class="px-6 py-4" style="background-color:#4a5e3a;">
          <h2 class="text-lg font-bold text-white">{{ modal.editando ? 'Editar' : 'Nuevo' }} Artículo</h2>
        </div>
        <div class="p-6">

        <!-- Nav pestañas -->
        <div class="flex border-b mb-5">
          <button v-for="tab in tabsModal" :key="tab.key" @click="tabModal = tab.key"
            :class="tabModal === tab.key
              ? 'border-b-2 font-semibold text-white px-5 py-2 text-sm -mb-px'
              : 'px-5 py-2 text-sm text-gray-500 hover:text-gray-700'"
            :style="tabModal === tab.key ? 'border-color:#4a5e3a; background-color:#4a5e3a; border-radius:6px 6px 0 0;' : ''">
            {{ tab.label }}
          </button>
        </div>

        <!-- ── TAB CATÁLOGO MEF ── -->
        <div v-show="tabModal === 'catalogo'" class="space-y-3">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-gray-600 mb-1">Código *</label>
              <input v-model="modal.form.codigo" v-uppercase type="text" maxlength="30"
                class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
            </div>
            <div>
              <label class="block text-xs text-gray-600 mb-1">Unidad de medida</label>
              <select v-model="modal.form.unidad_medida"
                class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none">
                <option value="">Seleccionar...</option>
                <option v-for="u in unidades" :key="u.id" :value="u.nombre">{{ u.nombre }} ({{ u.abreviatura }})</option>
              </select>
            </div>
          </div>

          <div>
            <label class="block text-xs text-gray-600 mb-1">Nivel 1 (Categoría MF)</label>
            <select v-model="modal.form.nivel1" @change="onNivel1Change"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none">
              <option value="">Sin categoría</option>
              <option v-for="n in nivel1s" :key="n.nivel1" :value="n.nivel1">{{ n.nivel1 }} — {{ n.descripcion }}</option>
            </select>
          </div>

          <div>
            <label class="block text-xs text-gray-600 mb-1">Nivel 2 (Subcategoría MF)</label>
            <select v-model="modal.form.nivel2" @change="onNivel2Change"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none"
              :disabled="!modal.form.nivel1">
              <option value="">Sin subcategoría</option>
              <option v-for="n in nivel2sFiltrados" :key="n.nivel2" :value="n.nivel2">{{ n.nivel2 }} — {{ n.descripcion }}</option>
            </select>
          </div>

          <div>
            <label class="block text-xs text-gray-600 mb-1">Ítem Presupuestario (auto desde catálogo)</label>
            <input v-model="modal.form.item_presupuestario" type="text" maxlength="150"
              placeholder="Se llena automáticamente al seleccionar Nivel 2"
              class="w-full border rounded px-3 py-2 text-sm bg-gray-50 focus:ring-2 focus:ring-amber-300 outline-none" />
          </div>

          <div>
            <label class="block text-xs text-gray-600 mb-1">Nombre / Descripción *</label>
            <input v-model="modal.form.nombre" v-uppercase type="text" maxlength="200"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
          </div>

          <div>
            <label class="block text-xs text-gray-600 mb-1">Descripción adicional</label>
            <textarea v-model="modal.form.descripcion" rows="2" maxlength="500"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none"></textarea>
          </div>

          <!-- Imagen -->
          <div>
            <label class="block text-xs text-gray-600 mb-1">Imagen del bien (opcional)</label>
            <div class="flex gap-4 items-start">
              <div v-if="imagenPreview" class="flex-shrink-0">
                <img :src="imagenPreview" class="w-24 h-24 object-cover rounded-lg border" />
                <button @click="quitarImagen" class="block text-xs text-red-500 hover:text-red-700 mt-1 text-center w-full">Quitar</button>
              </div>
              <div class="flex-1">
                <input ref="inputImagen" type="file" accept="image/*" @change="onImagenChange"
                  class="w-full text-sm text-gray-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:font-medium file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 cursor-pointer" />
                <p class="text-xs text-gray-400 mt-1">JPG, PNG o WEBP. Máx 2 MB.</p>
              </div>
            </div>
          </div>
        </div>

        <!-- ── TAB BIEN ── -->
        <div v-show="tabModal === 'bien'" class="space-y-3">
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

          <div>
            <label class="block text-xs text-gray-600 mb-1">Condición del Artículo</label>
            <select v-model="modal.form.estado_fisico"
              class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none">
              <option value="BUENO">Bueno</option>
              <option value="MALO">Malo</option>
              <option value="INSERVIBLE">Inservible</option>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs text-gray-600 mb-1">Categoría</label>
              <input v-model="modal.form.categoria" v-uppercase type="text" maxlength="100"
                placeholder="ej: Papelería, Limpieza"
                class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
            </div>
            <div>
              <label class="block text-xs text-gray-600 mb-1">Marca</label>
              <input v-model="modal.form.marca" v-uppercase type="text" maxlength="100"
                placeholder="ej: HP, BIC"
                class="w-full border rounded px-3 py-2 text-sm focus:ring-2 focus:ring-amber-300 outline-none" />
            </div>
          </div>

          <div v-if="modal.editando" class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
            <p class="text-xs text-amber-700 font-medium mb-0.5">Último precio registrado (sin IVA)</p>
            <p class="text-xl font-bold font-mono" style="color:#92400e;">${{ fmt(modal.precioActual) }}</p>
            <p class="text-xs text-amber-600 mt-0.5">Se actualiza automáticamente al confirmar un ingreso.</p>
          </div>
        </div>

        <p v-if="errorModal" class="text-red-600 text-sm mt-4">{{ errorModal }}</p>
        <div class="flex justify-end gap-3 mt-5">
          <button @click="modal.show = false" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
          <button @click="guardar" :disabled="guardando"
            class="bg-green-700 text-white px-5 py-2 rounded-lg text-sm hover:bg-green-800 disabled:opacity-50">
            {{ guardando ? 'Guardando...' : 'Guardar' }}
          </button>
        </div>
        </div><!-- /p-6 -->
      </div>
    </div>

    <!-- Modal configuración porcentaje -->
    <div v-if="modalConfig.show" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm overflow-hidden">
        <div class="px-6 py-4" style="background-color:#4a5e3a;">
          <h2 class="text-lg font-bold text-white">Configurar Stock Mínimo</h2>
        </div>
        <div class="p-6">
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
        </div><!-- /p-6 -->
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import api from '@/services/api'

const articulos        = ref([])
const busqueda         = ref('')
const filtroEstado     = ref('')
const filtroFisico     = ref('')
const filtroNivel1     = ref('')
const filtroNivel2     = ref('')
const hasBuscado       = ref(false)
const porcentajeMinimo = ref(20)
const guardando        = ref(false)
const errorModal       = ref('')
const nivel1s          = ref([])
const catalogo         = ref([])
const ivasActivos      = ref([])
const unidades         = ref([])
const paginaActual     = ref(1)
const porPagina        = ref(25)

const modal       = ref({ show: false, editando: false, id: null, form: {}, precioActual: 0 })
const modalConfig = ref({ show: false, porcentaje: 20 })
const tabModal    = ref('catalogo')
const tabsModal   = [
  { key: 'catalogo', label: 'Catálogo MEF' },
  { key: 'bien',     label: 'Bien' },
]
const imagenFile    = ref(null)
const imagenPreview = ref(null)
const inputImagen   = ref(null)

const articulosFiltrados = computed(() => {
  return articulos.value.filter(a => {
    const q = busqueda.value.toLowerCase()
    const coincide = !q ||
      a.nombre?.toLowerCase().includes(q) ||
      a.codigo?.toLowerCase().includes(q) ||
      a.nivel1?.toLowerCase().includes(q) ||
      a.nivel2?.toLowerCase().includes(q) ||
      a.item_presupuestario?.toLowerCase().includes(q)
    const pasaFisico = !filtroFisico.value || (a.estado_fisico || 'BUENO') === filtroFisico.value
    if (filtroEstado.value === 'alerta') return coincide && pasaFisico && a.bajo_minimo
    if (filtroEstado.value) return coincide && pasaFisico && a.estado === filtroEstado.value
    return coincide && pasaFisico
  })
})

const totalPaginas = computed(() => Math.max(1, Math.ceil(articulosFiltrados.value.length / porPagina.value)))

const articulosPaginados = computed(() => {
  const inicio = (paginaActual.value - 1) * porPagina.value
  return articulosFiltrados.value.slice(inicio, inicio + porPagina.value)
})

watch([busqueda, filtroEstado, filtroFisico], () => { paginaActual.value = 1 })

const nivel2sFiltrados = computed(() => {
  if (!modal.value.form.nivel1) return []
  return catalogo.value.filter(c => c.nivel1 === modal.value.form.nivel1)
})

const nivel2sParaBuscar = computed(() =>
  filtroNivel1.value ? catalogo.value.filter(c => c.nivel1 === filtroNivel1.value) : []
)

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

async function buscar() {
  hasBuscado.value = true
  paginaActual.value = 1
  await cargar()
}

async function cargar() {
  const params = {}
  if (filtroNivel1.value) params.nivel1 = filtroNivel1.value
  if (filtroNivel2.value) params.nivel2 = filtroNivel2.value
  if (busqueda.value)     params.q      = busqueda.value
  const { data } = await api.get('/adquisiciones/articulos', { params })
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
  await Promise.all([cargarConfig(), cargarCatalogo()])
})

const formVacio = () => ({
  codigo: '', nombre: '', descripcion: '', unidad_medida: '',
  nivel1: '', nivel2: '', item_presupuestario: '',
  precio_unitario: 0, iva_id: null,
  categoria: '', marca: '', estado_fisico: 'BUENO',
})

function resetImagen() {
  imagenFile.value    = null
  imagenPreview.value = null
  if (inputImagen.value) inputImagen.value.value = ''
}

function onImagenChange(e) {
  const file = e.target.files?.[0]
  if (!file) return
  imagenFile.value    = file
  imagenPreview.value = URL.createObjectURL(file)
}

function quitarImagen() {
  resetImagen()
}

function abrirModalCrear() {
  modal.value = { show: true, editando: false, id: null, form: formVacio(), precioActual: 0 }
  tabModal.value  = 'catalogo'
  errorModal.value = ''
  resetImagen()
}

function abrirEditar(a) {
  modal.value = {
    show: true, editando: true, id: a.id,
    precioActual: parseFloat(a.precio_unitario || 0),
    form: {
      codigo: a.codigo, nombre: a.nombre, descripcion: a.descripcion,
      unidad_medida: a.unidad_medida, nivel1: a.nivel1 || '', nivel2: a.nivel2 || '',
      item_presupuestario: a.item_presupuestario || '',
      precio_unitario: parseFloat(a.precio_unitario || 0),
      iva_id: a.iva_id || null,
      categoria: a.categoria || '', marca: a.marca || '',
      estado_fisico: a.estado_fisico || 'BUENO',
    }
  }
  tabModal.value   = 'catalogo'
  errorModal.value = ''
  resetImagen()
  imagenPreview.value = a.imagen_url || null
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

    let articuloId = modal.value.id
    if (modal.value.editando) {
      await api.put(`/adquisiciones/articulos/${articuloId}`, payload)
    } else {
      const { data } = await api.post('/adquisiciones/articulos', payload)
      articuloId = data.id
    }

    if (imagenFile.value) {
      const fd = new FormData()
      fd.append('imagen', imagenFile.value)
      await api.post(`/adquisiciones/articulos/${articuloId}/imagen`, fd)
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
