<template>
  <div class="max-w-4xl mx-auto pb-8">
    <!-- Cabecera -->
    <div class="flex items-center gap-3 mb-6">
      <router-link to="/empleados" class="text-gray-400 hover:text-gray-600 transition-colors">
        ← Volver
      </router-link>
      <h1 class="text-2xl font-bold text-gray-800">
        {{ esEdicion ? "Editar Empleado" : "Nuevo Empleado" }}
      </h1>
    </div>

    <form @submit.prevent="guardar">

      <!-- Foto (solo edición) — compacta fuera de las pestañas -->
      <div v-if="esEdicion" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 mb-5 flex items-center gap-5">
        <div class="w-20 h-20 rounded-full overflow-hidden bg-gray-100 flex items-center justify-center flex-shrink-0 border-2 border-gray-200">
          <img v-if="fotoUrl" :src="fotoUrl" class="w-full h-full object-cover" alt="Foto empleado" />
          <svg v-else class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
          </svg>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold text-gray-700">
            {{ form.apellidos || '—' }} {{ form.nombres }}
          </p>
          <p class="text-xs text-gray-400 mb-2">{{ form.cedula }}</p>
          <div class="flex items-center gap-3 flex-wrap">
            <label class="cursor-pointer">
              <span class="text-xs bg-[#0b5447] text-white px-3 py-1.5 rounded-lg hover:bg-[#00372e] transition-colors">
                {{ subiendoFoto ? 'Subiendo...' : 'Cambiar foto' }}
              </span>
              <input type="file" accept="image/*" @change="subirFoto" :disabled="subiendoFoto" class="hidden" />
            </label>
            <button v-if="fotoUrl" type="button" @click="eliminarFoto" :disabled="subiendoFoto"
              class="text-xs text-red-500 hover:text-red-700 transition-colors">
              Eliminar foto
            </button>
            <span class="text-xs text-gray-400">JPG, PNG — máx. 2 MB</span>
          </div>
        </div>
      </div>

      <!-- Navegación de pestañas -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="flex border-b border-gray-100">
          <button v-for="tab in tabs" :key="tab.id" type="button"
            @click="tabActivo = tab.id"
            :class="[
              'flex items-center gap-2 px-5 py-4 text-sm font-medium transition-all duration-200 flex-1 justify-center',
              tabActivo === tab.id
                ? 'border-b-2 text-white'
                : 'text-gray-500 hover:text-gray-700 hover:bg-gray-50 border-b-2 border-transparent'
            ]"
            :style="tabActivo === tab.id ? `border-color: ${tab.color}; background: ${tab.bg}; color: ${tab.color}` : ''">
            <span v-html="tab.icon" class="w-4 h-4 flex-shrink-0"></span>
            <span class="hidden sm:inline">{{ tab.label }}</span>
          </button>
        </div>

        <!-- ─── Tab 1: Datos Personales ─── -->
        <div v-show="tabActivo === 'personal'" class="p-6 space-y-4">
          <div class="flex items-center gap-2 mb-4">
            <span class="w-1 h-5 rounded-full" style="background:#3b82f6"></span>
            <h2 class="text-base font-semibold text-gray-700">Datos Personales</h2>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="label-field">Nombres *</label>
              <input v-model="form.nombres" type="text" required class="input-field" />
            </div>
            <div>
              <label class="label-field">Apellidos *</label>
              <input v-model="form.apellidos" type="text" required class="input-field" />
            </div>
            <div>
              <label class="label-field">Cédula / Pasaporte *</label>
              <input v-model="form.cedula" type="text" required maxlength="20" class="input-field" />
            </div>
            <div>
              <label class="label-field">Teléfono</label>
              <input v-model="form.telefono" type="text" class="input-field" />
            </div>
            <div class="sm:col-span-2">
              <label class="label-field">Email</label>
              <input v-model="form.email" type="email" class="input-field" />
            </div>
            <div class="sm:col-span-2">
              <label class="label-field">Dirección</label>
              <input v-model="form.direccion" type="text" class="input-field" />
            </div>
            <div>
              <label class="label-field">Sexo</label>
              <select v-model="form.sexo" class="input-field">
                <option value="">— No especificado —</option>
                <option value="MASCULINO">Masculino</option>
                <option value="FEMENINO">Femenino</option>
              </select>
            </div>
            <div>
              <label class="label-field">Tipo de Sangre</label>
              <select v-model="form.tipo_sangre" class="input-field">
                <option value="">— No especificado —</option>
                <option value="A+">A+</option>
                <option value="A-">A-</option>
                <option value="B+">B+</option>
                <option value="B-">B-</option>
                <option value="AB+">AB+</option>
                <option value="AB-">AB-</option>
                <option value="O+">O+</option>
                <option value="O-">O-</option>
              </select>
            </div>
            <div>
              <label class="label-field">Grupo Vulnerable</label>
              <select v-model="form.grupo_vulnerable_id" class="input-field">
                <option :value="null">— Ninguno —</option>
                <option v-for="g in catalogos.grupos_vulnerables" :key="g.id" :value="g.id">{{ g.nombre }}</option>
              </select>
            </div>
            <div>
              <label class="label-field">Grupo Prioritario</label>
              <select v-model="form.grupo_prioritario_id" class="input-field">
                <option :value="null">— Ninguno —</option>
                <option v-for="g in catalogos.grupos_prioritarios" :key="g.id" :value="g.id">{{ g.nombre }}</option>
              </select>
            </div>
          </div>

          <!-- Discapacidad -->
          <div class="border rounded-xl p-4 space-y-3">
            <div class="flex items-center gap-3">
              <input type="checkbox" id="tiene_discapacidad" v-model="form.tiene_discapacidad" class="w-4 h-4 accent-[#0b5447]" />
              <label for="tiene_discapacidad" class="text-sm font-semibold text-gray-700 cursor-pointer select-none">Persona con Discapacidad</label>
            </div>
            <div v-if="form.tiene_discapacidad" class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
              <div>
                <label class="label-field">Tipo de Discapacidad</label>
                <select v-model="form.tipo_discapacidad_id" class="input-field">
                  <option :value="null">Seleccionar...</option>
                  <option v-for="t in catalogos.tipos_discapacidad" :key="t.id" :value="t.id">{{ t.nombre }}</option>
                </select>
              </div>
              <div>
                <label class="label-field">Porcentaje de Discapacidad (%)</label>
                <input v-model.number="form.porcentaje_discapacidad" type="number" min="0" max="100" class="input-field" placeholder="Ej: 45" />
              </div>
            </div>
          </div>

          <!-- Enfermedad Catastrófica -->
          <div class="border rounded-xl p-4 space-y-3">
            <div class="flex items-center gap-3">
              <input type="checkbox" id="tiene_enfermedad" v-model="form.tiene_enfermedad_catastrofica" class="w-4 h-4 accent-[#0b5447]" />
              <label for="tiene_enfermedad" class="text-sm font-semibold text-gray-700 cursor-pointer select-none">Enfermedad Catastrófica</label>
            </div>
            <div v-if="form.tiene_enfermedad_catastrofica" class="pt-1">
              <label class="label-field">Tipo de Enfermedad</label>
              <select v-model="form.enfermedad_catastrofica_id" class="input-field">
                <option :value="null">Seleccionar...</option>
                <option v-for="e in catalogos.enfermedades_catastroficas" :key="e.id" :value="e.id">{{ e.nombre }}</option>
              </select>
            </div>
          </div>

          <!-- Persona Sustituta -->
          <div class="border rounded-xl p-4 space-y-3">
            <div class="flex items-center gap-3">
              <input type="checkbox" id="tiene_sustituta" v-model="form.tiene_persona_sustituta" class="w-4 h-4 accent-[#0b5447]" />
              <label for="tiene_sustituta" class="text-sm font-semibold text-gray-700 cursor-pointer select-none">Tiene Persona Sustituta (curador)</label>
            </div>
            <div v-if="form.tiene_persona_sustituta" class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
              <div>
                <label class="label-field">Fecha de Caducidad del Documento</label>
                <input v-model="form.sustituta_fecha_caducidad" type="date" class="input-field" />
              </div>
              <div v-if="esEdicion">
                <label class="label-field">Documento (PDF)</label>
                <div v-if="form.sustituta_nombre_archivo" class="flex items-center gap-2 flex-wrap">
                  <span class="text-xs text-green-700 bg-green-50 border border-green-200 rounded px-2 py-1 truncate max-w-[180px]">
                    {{ form.sustituta_nombre_archivo }}
                  </span>
                  <button type="button" @click="descargarDocSustituta"
                    class="text-xs text-blue-600 hover:text-blue-800 font-medium">Ver</button>
                  <button type="button" @click="eliminarDocSustituta"
                    class="text-xs text-red-500 hover:text-red-700">Eliminar</button>
                </div>
                <label v-else class="cursor-pointer">
                  <span class="text-xs bg-[#0b5447] text-white px-3 py-1.5 rounded-lg hover:bg-[#00372e] transition-colors">
                    {{ subiendoDocSustituta ? 'Subiendo...' : 'Subir PDF' }}
                  </span>
                  <input type="file" accept=".pdf" @change="subirDocSustituta" :disabled="subiendoDocSustituta" class="hidden" />
                </label>
              </div>
              <div v-else class="text-xs text-gray-400 self-end pb-2">Guarda primero el empleado para subir el documento.</div>
            </div>
          </div>

          <!-- Hijos -->
          <div class="border rounded-xl p-4 space-y-4">
            <p class="text-sm font-semibold text-gray-700">Hijos</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="label-field">N° Hijos Mayores de Edad</label>
                <input v-model.number="form.num_hijos_mayores" type="number" min="0" class="input-field" />
              </div>
            </div>
            <!-- Hijos menores -->
            <div>
              <div class="flex items-center justify-between mb-2">
                <label class="text-sm font-medium text-gray-600">Hijos Menores de Edad</label>
                <button type="button" @click="agregarHijo"
                  class="text-xs bg-[#0b5447] text-white px-3 py-1 rounded-lg hover:bg-[#00372e] transition-colors">
                  + Agregar
                </button>
              </div>
              <div v-if="!form.hijos.length" class="text-xs text-gray-400 text-center py-3 border rounded-lg border-dashed">
                Sin hijos menores registrados
              </div>
              <div v-else class="space-y-2">
                <div v-for="(hijo, idx) in form.hijos" :key="hijo._key || hijo.id"
                  class="flex items-center gap-3 bg-gray-50 rounded-lg px-3 py-2">
                  <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <input v-model="hijo.nombre" type="text" placeholder="Nombre (opcional)"
                      class="border rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-[#579186]" />
                    <input v-model="hijo.fecha_nacimiento" type="date"
                      class="border rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-[#579186]"
                      @change="recalcularEdad(hijo)" />
                  </div>
                  <div class="flex items-center gap-2 flex-shrink-0">
                    <span v-if="hijo.guarderia" class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-medium">
                      Guardería
                    </span>
                    <span v-else-if="hijo.fecha_nacimiento" class="text-xs text-gray-400">
                      {{ hijo.anos }}a
                    </span>
                    <button v-if="hijo.id" type="button" @click="eliminarHijoDB(hijo, idx)"
                      class="text-red-400 hover:text-red-600 text-lg leading-none">&times;</button>
                    <button v-else type="button" @click="form.hijos.splice(idx, 1)"
                      class="text-red-400 hover:text-red-600 text-lg leading-none">&times;</button>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>

        <!-- ─── Tab 2: Cargo y Contrato ─── -->
        <div v-show="tabActivo === 'cargo'" class="p-6 space-y-4">
          <div class="flex items-center gap-2 mb-4">
            <span class="w-1 h-5 rounded-full" style="background:#10b981"></span>
            <h2 class="text-base font-semibold text-gray-700">Cargo y Contrato</h2>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="label-field">Departamento *</label>
              <select v-model="form.departamento_id" required class="input-field">
                <option value="">Seleccionar...</option>
                <option v-for="d in departamentos" :key="d.id_depto" :value="d.id_depto">
                  {{ d.nombre_depto }}
                </option>
              </select>
            </div>
            <div>
              <label class="label-field">Cargo *</label>
              <input v-model="form.cargo_empleado" type="text" placeholder="Ej: Analista de Sistemas" required class="input-field" />
            </div>
            <div>
              <label class="label-field">Tipo de Contrato</label>
              <select v-model="form.tipo_contrato" class="input-field">
                <option value="">Seleccionar...</option>
                <option value="LOSEP">LOSEP</option>
                <option value="CODIGO DEL TRABAJO">CÓDIGO DEL TRABAJO</option>
              </select>
            </div>
            <div>
              <label class="label-field">Modalidad Laboral *</label>
              <select v-model="form.modalidad_laboral" required class="input-field">
                <option value="">Seleccionar...</option>
                <option v-for="m in modalidadesLaborales" :key="m.id" :value="m.nombre">{{ m.nombre }}</option>
              </select>
            </div>
            <div>
              <label class="label-field">Jornada Laboral</label>
              <select v-model="form.id_jornada" class="input-field">
                <option value="">Seleccionar...</option>
                <option v-for="j in jornadas" :key="j.id_jornada" :value="j.id_jornada">
                  {{ j.descripcion }} ({{ j.normal }}h)
                </option>
              </select>
            </div>
            <div>
              <label class="label-field">Estado</label>
              <select v-model="form.estado" class="input-field">
                <option value="ACTIVO">Activo</option>
                <option value="INACTIVO">Inactivo</option>
              </select>
            </div>
            <div>
              <label class="label-field">Fecha de Ingreso *</label>
              <input v-model="form.fecha_ingreso" type="date" required class="input-field" />
            </div>
            <div v-if="form.estado === 'INACTIVO'">
              <label class="label-field">Fecha de Salida</label>
              <input v-model="form.fecha_salida" type="date" class="input-field" />
            </div>
            <!-- Motivo de salida — solo INACTIVO -->
            <div v-if="form.estado === 'INACTIVO'">
              <label class="label-field">Motivo de Salida</label>
              <select v-model="form.motivo_salida" class="input-field">
                <option value="">— Seleccione —</option>
                <option>COMISIÓN DE SERVICIOS</option>
                <option>FIN DE COMISIÓN DE SERVICIOS</option>
                <option>FIN DE CONTRATO</option>
                <option>RENUNCIA VOLUNTARIA</option>
                <option>JUBILACIÓN</option>
              </select>
            </div>
            <!-- Institución destino — solo cuando sale en comisión -->
            <div v-if="form.estado === 'INACTIVO' && form.motivo_salida === 'COMISIÓN DE SERVICIOS'">
              <label class="label-field">Institución Destino *</label>
              <input v-model="form.institucion_comision" type="text" placeholder="Nombre de la institución a donde se va"
                class="input-field" />
            </div>
            <!-- Institución origen — empleados que vienen en comisión -->
            <div v-if="form.modalidad_laboral === 'Comisión de Servicios'">
              <label class="label-field">Institución de Origen</label>
              <input v-model="form.institucion_comision" type="text" placeholder="Nombre de la institución de donde viene"
                class="input-field" />
            </div>
            <!-- Motivo de reactivación — solo ACTIVO con motivo_salida previo -->
            <div v-if="form.estado === 'ACTIVO' && form.motivo_salida">
              <label class="label-field">Motivo de Reactivación</label>
              <select v-model="form.motivo_reactivacion" class="input-field">
                <option value="">— Seleccione —</option>
                <option>RETORNO DE COMISIÓN DE SERVICIOS</option>
              </select>
            </div>
            <div>
              <label class="label-field">Salario Base *</label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm font-medium">$</span>
                <input v-model="form.salario" type="number" step="0.01" min="0" required
                  class="input-field" style="padding-left: 1.75rem;" />
              </div>
            </div>
          </div>
        </div>

        <!-- ─── Tab 3: Datos del Puesto ─── -->
        <div v-show="tabActivo === 'puesto'" class="p-6 space-y-4">
          <div class="flex items-center gap-2 mb-4">
            <span class="w-1 h-5 rounded-full" style="background:#8b5cf6"></span>
            <h2 class="text-base font-semibold text-gray-700">Datos del Puesto</h2>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="label-field">Grupo Ocupacional *</label>
              <input v-model="form.grupo_ocupacional" type="text" placeholder="Ej: SERVIDOR PUBLICO 7" required class="input-field" />
            </div>
            <div>
              <label class="label-field">Grado *</label>
              <input v-model="form.nivel" type="number" min="1" required class="input-field" />
            </div>
            <div>
              <label class="label-field">Proceso Institucional *</label>
              <select v-model="form.proceso_institucional" required class="input-field">
                <option value="">Seleccionar...</option>
                <option value="SUSTANTIVO">SUSTANTIVO</option>
                <option value="ADJETIVO">ADJETIVO</option>
                <option value="GOBERNANTE">GOBERNANTE</option>
              </select>
            </div>
            <div>
              <label class="label-field">Estado del Puesto</label>
              <select v-model="form.estado_puesto" class="input-field">
                <option value="OCUPADO">OCUPADO</option>
                <option value="VACANTE">VACANTE</option>
                <option value="DISPONIBLE">DISPONIBLE</option>
              </select>
            </div>
            <div class="sm:col-span-2">
              <label class="label-field">Partida Individual *</label>
              <div class="flex gap-2">
                <input v-model="form.partida_individual" type="text" required
                  placeholder="Escriba una nueva o use el botón para seleccionar una libre"
                  class="input-field flex-1" />
                <button type="button" @click="modalPartidas.show = true"
                  class="shrink-0 text-xs border border-[#00372e] text-[#00372e] px-3 py-2 rounded-lg hover:bg-[#00372e] hover:text-white whitespace-nowrap transition-colors">
                  Seleccionar libre
                </button>
              </div>
            </div>
            <div>
              <label class="label-field">Programa</label>
              <input v-model="form.programa" type="text" maxlength="4" placeholder="Ej: 55" class="input-field" />
            </div>
            <div>
              <label class="label-field">Actividad</label>
              <input v-model="form.actividad" type="text" maxlength="6" placeholder="Ej: 001" class="input-field" />
            </div>
            <div>
              <label class="label-field">Acumula Décimos (13° y 14°)</label>
              <select v-model="form.acumula_decimos" class="input-field">
                <option :value="true">Acumula</option>
                <option :value="false">Cobra mensualmente</option>
              </select>
            </div>
            <div>
              <label class="label-field">Fondos de Reserva</label>
              <select v-model="form.acumula_fondos_reserva" class="input-field">
                <option :value="0">No tiene derecho</option>
                <option :value="1">Cobra mensualmente</option>
                <option :value="2">Acumula</option>
              </select>
            </div>
            <div class="sm:col-span-2">
              <label class="label-field">Estructura Programática *</label>
              <input v-model="form.partida_presupuestaria" type="text" placeholder="Ej: 202622000000000..." required
                class="input-field font-mono text-xs" />
            </div>
            <div>
              <label class="label-field">N° Certificado SERCOP</label>
              <input v-model="form.num_sercop" type="text" maxlength="50" class="input-field" />
            </div>
            <div>
              <label class="label-field">Vigencia SERCOP</label>
              <input v-model="form.fecha_vence_sercop" type="date" class="input-field" />
            </div>
          </div>
        </div>

        <!-- ─── Tab 4: Control de Asistencia ─── -->
        <div v-show="tabActivo === 'asistencia'" class="p-6 space-y-5">
          <div class="flex items-center gap-2 mb-4">
            <span class="w-1 h-5 rounded-full" style="background:#f59e0b"></span>
            <h2 class="text-base font-semibold text-gray-700">Control de Asistencia</h2>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
              <label class="label-field">Modalidad de Marcación</label>
              <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-2">
                <label v-for="opt in opcionesModalidad" :key="opt.value"
                  :class="[
                    'flex items-start gap-3 p-4 rounded-xl border-2 cursor-pointer transition-all',
                    form.modalidad_marcacion === opt.value
                      ? 'border-[#0b5447] bg-[#0b5447]/5'
                      : 'border-gray-200 hover:border-gray-300'
                  ]">
                  <input type="radio" :value="opt.value" v-model="form.modalidad_marcacion" class="mt-0.5 accent-[#0b5447]" />
                  <div>
                    <p class="text-sm font-semibold" :class="form.modalidad_marcacion === opt.value ? 'text-[#0b5447]' : 'text-gray-700'">
                      {{ opt.label }}
                    </p>
                    <p class="text-xs text-gray-500 mt-0.5 leading-relaxed">{{ opt.desc }}</p>
                  </div>
                </label>
              </div>
            </div>
          </div>

          <div class="flex items-center gap-3 pt-2">
            <div :class="[
              'flex items-center gap-3 px-4 py-3 rounded-xl border-2 cursor-pointer transition-all w-full',
              form.puede_solicitar_vehiculo ? 'border-[#1e3a5f] bg-[#1e3a5f]/5' : 'border-gray-200 hover:border-gray-300'
            ]" @click="form.puede_solicitar_vehiculo = !form.puede_solicitar_vehiculo">
              <input id="puede_solicitar_vehiculo" type="checkbox" v-model="form.puede_solicitar_vehiculo"
                class="w-4 h-4 rounded accent-[#1e3a5f]" @click.stop />
              <div>
                <label for="puede_solicitar_vehiculo" class="text-sm font-medium text-gray-700 cursor-pointer select-none">
                  Puede solicitar vehículo institucional
                </label>
                <p class="text-xs text-gray-400">Aparecerá la opción de transporte en el lanzador de aplicaciones</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Error -->
      <div v-if="error" class="mt-4 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
        {{ error }}
      </div>

      <!-- Botones de acción -->
      <div class="mt-5 flex justify-end gap-3">
        <router-link to="/empleados"
          class="px-5 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50 transition-colors">
          Cancelar
        </router-link>
        <button type="submit" :disabled="guardando"
          class="px-5 py-2 rounded-lg bg-[#0b5447] text-white text-sm font-medium hover:bg-[#00372e] disabled:opacity-50 transition-colors">
          {{ guardando ? "Guardando..." : (esEdicion ? "Actualizar" : "Crear Empleado") }}
        </button>
      </div>

    </form>

    <!-- Modal: Seleccionar partida disponible -->
    <div v-if="modalPartidas.show"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl shadow-xl w-full max-w-2xl p-6">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-lg font-bold text-gray-800">Partidas individuales disponibles</h2>
          <button @click="modalPartidas.show = false" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <p class="text-xs text-gray-500 mb-3">Empleados inactivos con estado del puesto DISPONIBLE. Al seleccionar se llenarán ambas partidas.</p>
        <div v-if="!partidasVacantes.length" class="text-center py-8 text-gray-400 text-sm">
          No hay partidas disponibles en este momento.
        </div>
        <div v-else class="overflow-auto max-h-80">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 sticky top-0">
              <tr>
                <th class="text-left px-3 py-2 text-gray-600 font-medium">Empleado anterior</th>
                <th class="text-left px-3 py-2 text-gray-600 font-medium">Partida Individual</th>
                <th class="text-left px-3 py-2 text-gray-600 font-medium">Partida Presupuestaria</th>
                <th class="px-3 py-2"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in partidasVacantes" :key="p.id_emp"
                class="border-b hover:bg-green-50 cursor-pointer"
                @click="seleccionarPartida(p)">
                <td class="px-3 py-2">{{ p.apellido_emp }} {{ p.nombre_emp }}</td>
                <td class="px-3 py-2 font-mono text-xs">{{ p.partida_individual }}</td>
                <td class="px-3 py-2 font-mono text-xs">{{ p.partida_presupuestaria || '—' }}</td>
                <td class="px-3 py-2 text-right">
                  <span class="text-[#00372e] font-semibold text-xs">Seleccionar →</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="flex justify-end mt-4">
          <button @click="modalPartidas.show = false"
            class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">
            Cancelar
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue"

import { useRoute, useRouter } from "vue-router"
import api from "@/services/api"

const route  = useRoute()
const router = useRouter()

const esEdicion     = computed(() => !!route.params.id)
const guardando     = ref(false)
const error         = ref("")
const fotoUrl       = ref(null)
const subiendoFoto  = ref(false)
const tabActivo     = ref('personal')

const storageUrl = (path) => path ? `${import.meta.env.VITE_API_URL}/storage-file/${path}` : null
const departamentos        = ref([])
const jornadas             = ref([])
const partidasVacantes     = ref([])
const modalidadesLaborales = ref([])
const modalPartidas        = ref({ show: false })
const subiendoDocSustituta = ref(false)
const catalogos = ref({
  grupos_vulnerables: [], grupos_prioritarios: [],
  tipos_discapacidad: [], enfermedades_catastroficas: [],
})

const tabs = [
  {
    id: 'personal',
    label: 'Datos Personales',
    color: '#3b82f6',
    bg: '#eff6ff',
    icon: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path d="M10 8a3 3 0 100-6 3 3 0 000 6zM3.465 14.493a1.23 1.23 0 00.41 1.412A9.957 9.957 0 0010 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 00-13.074.003z"/></svg>',
  },
  {
    id: 'cargo',
    label: 'Cargo y Contrato',
    color: '#10b981',
    bg: '#ecfdf5',
    icon: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M6 3.75A2.75 2.75 0 018.75 1h2.5A2.75 2.75 0 0114 3.75v.443c.572.055 1.14.122 1.706.2C17.053 4.582 18 5.75 18 7.07v3.469c0 1.126-.694 2.191-1.83 2.54-1.952.599-4.024.921-6.17.921s-4.219-.322-6.17-.921C2.694 12.73 2 11.665 2 10.539V7.07c0-1.322.947-2.489 2.294-2.676A41.047 41.047 0 016 4.193V3.75zm6.5 0v.325a41.622 41.622 0 00-5 0V3.75c0-.69.56-1.25 1.25-1.25h2.5c.69 0 1.25.56 1.25 1.25zM10 10a1 1 0 00-1 1v.01a1 1 0 001 1h.01a1 1 0 001-1V11a1 1 0 00-1-1H10z" clip-rule="evenodd"/><path d="M3 15.055v-.188a9.916 9.916 0 005 1.383 9.916 9.916 0 005-1.383v.188C13 16.143 11.657 17 10 17s-3-.857-3-1.945z"/></svg>',
  },
  {
    id: 'puesto',
    label: 'Datos del Puesto',
    color: '#8b5cf6',
    bg: '#f5f3ff',
    icon: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M15.988 3.012A2.25 2.25 0 0118 5.25v6.5A2.25 2.25 0 0115.75 14H13.5V7A2.5 2.5 0 0011 4.5H8.128a2.252 2.252 0 011.884-1.488A2.25 2.25 0 0112.25 1h1.5a2.25 2.25 0 012.238 2.012zM11.5 3.25a.75.75 0 01.75-.75h1.5a.75.75 0 01.75.75v.25h-3v-.25z" clip-rule="evenodd"/><path fill-rule="evenodd" d="M2 7a1 1 0 011-1h8a1 1 0 011 1v10a1 1 0 01-1 1H3a1 1 0 01-1-1V7zm2 3.25a.75.75 0 01.75-.75h4.5a.75.75 0 010 1.5h-4.5a.75.75 0 01-.75-.75zm0 3.5a.75.75 0 01.75-.75h4.5a.75.75 0 010 1.5h-4.5a.75.75 0 01-.75-.75z" clip-rule="evenodd"/></svg>',
  },
  {
    id: 'asistencia',
    label: 'Asistencia',
    color: '#f59e0b',
    bg: '#fffbeb',
    icon: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-4 h-4"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 000-1.5h-3.25V5z" clip-rule="evenodd"/></svg>',
  },
]

const opcionesModalidad = [
  { value: 'PRESENCIAL',  label: 'Presencial',  desc: 'Solo puede timbrar desde las VLANs internas configuradas.' },
  { value: 'TEMPORAL',    label: 'Temporal',    desc: 'Puede timbrar desde cualquier IP (comisión, viaje temporal).' },
  { value: 'TELETRABAJO', label: 'Teletrabajo', desc: 'Marca como teletrabajo sin restricción de IP.' },
]

function seleccionarPartida(p) {
  form.value.partida_individual     = p.partida_individual    || ""
  form.value.partida_presupuestaria = p.partida_presupuestaria || ""
  modalPartidas.value.show = false
}

const form = ref({
  nombres:        "",
  apellidos:      "",
  cedula:         "",
  telefono:       "",
  email:          "",
  direccion:      "",
  sexo:               "",
  tipo_sangre:        "",
  num_sercop:         "",
  fecha_vence_sercop: "",
  grupo_vulnerable_id:           null,
  grupo_prioritario_id:          null,
  tiene_discapacidad:            false,
  tipo_discapacidad_id:          null,
  porcentaje_discapacidad:       null,
  tiene_enfermedad_catastrofica: false,
  enfermedad_catastrofica_id:    null,
  tiene_persona_sustituta:       false,
  sustituta_fecha_caducidad:     "",
  sustituta_nombre_archivo:      "",
  num_hijos_mayores:             0,
  hijos:                         [],
  departamento_id: null,
  cargo_empleado: "",
  tipo_contrato:     "",
  modalidad_laboral: "",
  id_jornada:        "",
  estado:            "ACTIVO",
  fecha_ingreso:  "",
  fecha_salida:          "",
  motivo_salida:         "",
  motivo_reactivacion:   "",
  institucion_comision:  "",
  salario:        "",
  nivel:                   "",
  grupo_ocupacional:       "",
  proceso_institucional:   "",
  estado_puesto:           "OCUPADO",
  partida_individual:      "",
  partida_presupuestaria:  "",
  acumula_fondos_reserva:    0,
  acumula_decimos:           false,
  programa:                  "",
  actividad:                 "",
  modalidad_marcacion:       "PRESENCIAL",
  puede_solicitar_vehiculo:  false,
})

const guardar = async () => {
  guardando.value = true
  error.value = ""
  try {
    const payload = {
      identificacion: form.value.cedula,
      nombre_emp:     form.value.nombres,
      apellido_emp:   form.value.apellidos,
      id_depto:       form.value.departamento_id,
      estado:         form.value.estado || "ACTIVO",
      cargo_empleado: form.value.cargo_empleado,
      telefono:       form.value.telefono,
      calle_y_numero: form.value.direccion,
      fecha_ingreso:  form.value.fecha_ingreso,
      fecha_salida:         form.value.fecha_salida        || null,
      motivo_salida:        form.value.motivo_salida        || null,
      motivo_reactivacion:  form.value.motivo_reactivacion  || null,
      institucion_comision: form.value.institucion_comision || null,
      sueldo:         form.value.salario,
      nivel:          form.value.nivel,
      tipo_contrato:     form.value.tipo_contrato,
      modalidad_laboral: form.value.modalidad_laboral,
      id_jornada:        form.value.id_jornada || null,
      email:             form.value.email,
      grupo_ocupacional:       form.value.grupo_ocupacional      || null,
      proceso_institucional:   form.value.proceso_institucional  || null,
      estado_puesto:           form.value.estado_puesto,
      partida_individual:      form.value.partida_individual     || null,
      partida_presupuestaria:  form.value.partida_presupuestaria || null,
      acumula_fondos_reserva:   form.value.acumula_fondos_reserva,
      acumula_decimo_tercero:   form.value.acumula_decimos,
      acumula_decimo_cuarto:    form.value.acumula_decimos,
      programa:                 form.value.programa  || null,
      actividad:                form.value.actividad || null,
      modalidad_marcacion:      form.value.modalidad_marcacion,
      puede_solicitar_vehiculo: form.value.puede_solicitar_vehiculo,
      sexo:                     form.value.sexo       || null,
      tipo_sangre:              form.value.tipo_sangre || null,
      num_sercop:               form.value.num_sercop         || null,
      fecha_vence_sercop:       form.value.fecha_vence_sercop || null,
      grupo_vulnerable_id:           form.value.grupo_vulnerable_id  || null,
      grupo_prioritario_id:          form.value.grupo_prioritario_id || null,
      tiene_discapacidad:            form.value.tiene_discapacidad,
      tipo_discapacidad_id:          form.value.tiene_discapacidad ? (form.value.tipo_discapacidad_id || null) : null,
      porcentaje_discapacidad:       form.value.tiene_discapacidad ? (form.value.porcentaje_discapacidad || null) : null,
      tiene_enfermedad_catastrofica: form.value.tiene_enfermedad_catastrofica,
      enfermedad_catastrofica_id:    form.value.tiene_enfermedad_catastrofica ? (form.value.enfermedad_catastrofica_id || null) : null,
      tiene_persona_sustituta:       form.value.tiene_persona_sustituta,
      sustituta_fecha_caducidad:     form.value.tiene_persona_sustituta ? (form.value.sustituta_fecha_caducidad || null) : null,
      num_hijos_mayores:             form.value.num_hijos_mayores || 0,
    }

    if (esEdicion.value) {
      await api.put("/empleados/" + route.params.id, payload)
      // Sincronizar hijos nuevos (los que no tienen id aún)
      const hijosNuevos = form.value.hijos.filter(h => !h.id && h.fecha_nacimiento)
      for (const h of hijosNuevos) {
        await api.post(`/empleados/${route.params.id}/hijos`, { nombre: h.nombre, fecha_nacimiento: h.fecha_nacimiento })
      }
    } else {
      const { data: nuevo } = await api.post("/empleados", payload)
      // Sincronizar hijos para nuevo empleado
      const id = nuevo.id_emp
      for (const h of form.value.hijos.filter(h => h.fecha_nacimiento)) {
        await api.post(`/empleados/${id}/hijos`, { nombre: h.nombre, fecha_nacimiento: h.fecha_nacimiento })
      }
    }
    router.push("/empleados")
  } catch (e) {
    const errors = e.response?.data?.errors
    if (errors) {
      error.value = Object.values(errors).flat().join(" | ")
    } else {
      error.value = e.response?.data?.message || "Error al guardar el empleado."
    }
  } finally {
    guardando.value = false
  }
}

async function subirFoto(e) {
  const file = e.target.files?.[0]
  if (!file) return
  subiendoFoto.value = true
  try {
    const fd = new FormData()
    fd.append('foto', file)
    const { data } = await api.post(`/empleados/${route.params.id}/foto`, fd)
    fotoUrl.value = storageUrl(data.foto)
  } catch (err) {
    const msg = err?.response?.data?.message || err?.response?.data?.errors?.foto?.[0] || 'Error al subir la foto.'
    alert(msg)
  } finally {
    subiendoFoto.value = false
    e.target.value = ''
  }
}

async function eliminarFoto() {
  if (!confirm('¿Eliminar la foto del empleado?')) return
  subiendoFoto.value = true
  try {
    await api.delete(`/empleados/${route.params.id}/foto`)
    fotoUrl.value = null
  } catch {
    alert('Error al eliminar la foto.')
  } finally {
    subiendoFoto.value = false
  }
}

function calcEdad(fechaNac) {
  if (!fechaNac) return null
  return Math.floor((Date.now() - new Date(fechaNac).getTime()) / (365.25 * 86400000))
}

function recalcularEdad(hijo) {
  const anos = calcEdad(hijo.fecha_nacimiento)
  hijo.anos      = anos
  hijo.guarderia = anos !== null && anos < 5
}

function agregarHijo() {
  form.value.hijos.push({ _key: Date.now(), id: null, nombre: '', fecha_nacimiento: '', anos: null, guarderia: false })
}

async function eliminarHijoDB(hijo, idx) {
  if (!confirm('¿Eliminar este hijo?')) return
  await api.delete(`/empleados/${route.params.id}/hijos/${hijo.id}`)
  form.value.hijos.splice(idx, 1)
}

async function subirDocSustituta(e) {
  const file = e.target.files?.[0]
  if (!file) return
  subiendoDocSustituta.value = true
  try {
    const fd = new FormData()
    fd.append('documento', file)
    if (form.value.sustituta_fecha_caducidad) fd.append('sustituta_fecha_caducidad', form.value.sustituta_fecha_caducidad)
    const { data } = await api.post(`/empleados/${route.params.id}/sustituta-doc`, fd)
    form.value.sustituta_nombre_archivo  = data.sustituta_nombre_archivo
    form.value.sustituta_fecha_caducidad = data.sustituta_fecha_caducidad?.substring(0, 10) ?? form.value.sustituta_fecha_caducidad
  } catch { alert('Error al subir el documento.') }
  finally { subiendoDocSustituta.value = false; e.target.value = '' }
}

async function descargarDocSustituta() {
  try {
    const resp = await api.get(`/empleados/${route.params.id}/sustituta-doc`, { responseType: 'blob' })
    const url  = URL.createObjectURL(new Blob([resp.data], { type: 'application/pdf' }))
    window.open(url, '_blank')
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch { alert('Error al descargar el documento.') }
}

async function eliminarDocSustituta() {
  if (!confirm('¿Eliminar el documento de persona sustituta?')) return
  await api.delete(`/empleados/${route.params.id}/sustituta-doc`)
  form.value.sustituta_nombre_archivo = ''
}

onMounted(async () => {
  const [{ data: deps }, { data: jors }, { data: partidas }, { data: mods }, { data: cats }] = await Promise.all([
    api.get("/departamentos"),
    api.get("/admin/jornadas"),
    api.get("/empleados/partidas-vacantes"),
    api.get("/admin/modalidades-laborales"),
    api.get("/empleados/catalogos-sociales"),
  ])
  departamentos.value        = deps
  jornadas.value             = jors
  partidasVacantes.value     = partidas
  modalidadesLaborales.value = mods.filter(m => m.estado === 'ACTIVO')
  catalogos.value            = cats

  if (esEdicion.value) {
    const { data } = await api.get("/empleados/" + route.params.id)
    form.value.nombres         = data.nombre_emp
    form.value.apellidos       = data.apellido_emp
    form.value.cedula          = data.identificacion
    form.value.departamento_id = data.id_depto
    form.value.cargo_empleado  = data.cargo_empleado || ""
    form.value.estado          = data.estado || "ACTIVO"
    form.value.fecha_ingreso   = data.fecha_ingreso?.substring(0, 10) || ""
    form.value.fecha_salida          = data.fecha_salida?.substring(0, 10) || ""
    form.value.motivo_salida         = data.motivo_salida        || ""
    form.value.motivo_reactivacion   = data.motivo_reactivacion  || ""
    form.value.institucion_comision  = data.institucion_comision || ""
    form.value.salario         = data.sueldo || ""
    form.value.nivel           = data.nivel || ""
    form.value.telefono        = data.telefono || ""
    form.value.direccion       = data.calle_y_numero || ""
    form.value.tipo_contrato     = data.tipo_contrato?.trim()     || ""
    form.value.modalidad_laboral = data.modalidad_laboral?.trim() || ""
    form.value.id_jornada        = data.id_jornada                || ""
    form.value.email             = data.emails?.[0]?.mail         || ""
    form.value.grupo_ocupacional      = data.grupo_ocupacional      || ""
    form.value.proceso_institucional  = data.proceso_institucional  || ""
    form.value.estado_puesto          = data.estado_puesto          || "OCUPADO"
    form.value.partida_individual     = data.partida_individual     || ""
    form.value.partida_presupuestaria = data.partida_presupuestaria || ""
    form.value.acumula_fondos_reserva   = data.acumula_fondos_reserva   ?? 0
    form.value.acumula_decimos          = data.acumula_decimo_tercero   ?? false
    form.value.programa                 = data.programa                 ?? ""
    form.value.actividad                = data.actividad                ?? ""
    form.value.modalidad_marcacion      = data.modalidad_marcacion      ?? "PRESENCIAL"
    form.value.puede_solicitar_vehiculo = data.puede_solicitar_vehiculo ?? false
    form.value.sexo                     = data.sexo                     ?? ""
    form.value.tipo_sangre              = data.tipo_sangre              ?? ""
    form.value.num_sercop               = data.num_sercop               ?? ""
    form.value.fecha_vence_sercop       = data.fecha_vence_sercop?.substring(0, 10) ?? ""
    // Campos sociales
    form.value.grupo_vulnerable_id           = data.grupo_vulnerable_id           ?? null
    form.value.grupo_prioritario_id          = data.grupo_prioritario_id          ?? null
    form.value.tiene_discapacidad            = data.tiene_discapacidad            ?? false
    form.value.tipo_discapacidad_id          = data.tipo_discapacidad_id          ?? null
    form.value.porcentaje_discapacidad       = data.porcentaje_discapacidad       ?? null
    form.value.tiene_enfermedad_catastrofica = data.tiene_enfermedad_catastrofica ?? false
    form.value.enfermedad_catastrofica_id    = data.enfermedad_catastrofica_id    ?? null
    form.value.tiene_persona_sustituta       = data.tiene_persona_sustituta       ?? false
    form.value.sustituta_fecha_caducidad     = data.sustituta_fecha_caducidad?.substring(0, 10) ?? ""
    form.value.sustituta_nombre_archivo      = data.sustituta_nombre_archivo      ?? ""
    form.value.num_hijos_mayores             = data.num_hijos_mayores             ?? 0
    form.value.hijos                         = (data.hijos || []).map(h => ({ ...h }))
    fotoUrl.value = storageUrl(data.foto)
  }
})
</script>

<style scoped>
@reference "tailwindcss";

.label-field {
  @apply block text-sm font-medium text-gray-600 mb-1;
}
.input-field {
  @apply w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#579186] focus:border-transparent transition-shadow bg-gray-50 focus:bg-white;
}
</style>
