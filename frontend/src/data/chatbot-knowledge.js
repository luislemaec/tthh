// Base de conocimiento del asistente del sistema
// Cada entrada: { keywords: string[], answer: string }
// Keywords en minúsculas sin tildes para matching normalizado

export const FALLBACK =
  'Para esta consulta comuníquese con la Dirección de Tecnologías de la Información.'

export const knowledge = [
  // ── LOGIN / ACCESO ────────────────────────────────────────────────────────
  {
    keywords: ['contrasena', 'clave', 'password', 'olvide', 'recuperar', 'cambiar contrasena'],
    answer:
      'Si olvidó o necesita cambiar su contraseña, comuníquese con la Dirección de Tecnologías de la Información para que la restablezcan.',
  },
  {
    keywords: ['login', 'iniciar sesion', 'ingresar', 'acceder', 'como ingreso', 'no puedo entrar'],
    answer:
      'Para ingresar al sistema:\n1. Abra el navegador y vaya a la URL del sistema.\n2. Ingrese su número de cédula en el campo "Identificación".\n3. Ingrese su contraseña.\n4. Haga clic en "Iniciar sesión".\n\nSi tiene problemas de acceso, contacte a TI.',
  },
  {
    keywords: ['sesion', 'cerrar sesion', 'logout', 'salir'],
    answer:
      'Para cerrar sesión, haga clic en el ícono de usuario (esquina superior derecha del menú) y seleccione "Cerrar sesión". La sesión también se cierra automáticamente al cerrar el navegador.',
  },

  // ── MÓDULO / MENÚ ─────────────────────────────────────────────────────────
  {
    keywords: ['no veo menu', 'no aparece', 'no tengo acceso', 'rol', 'permiso sistema', 'asignar rol'],
    answer:
      'Los ítems del menú se muestran según su rol asignado. Si no ve una opción que necesita, comuníquese con el Administrador del sistema para que revise los roles de su usuario.',
  },
  {
    keywords: ['launcher', 'seleccionar modulo', 'pantalla inicio', 'modulos', 'tarjeta modulo'],
    answer:
      'Al iniciar sesión verá la pantalla de selección de módulos (Launcher). Haga clic en la tarjeta del módulo que desea usar: Talento Humano, Adquisiciones o Transportes. Solo verá los módulos a los que tiene acceso según su rol.',
  },

  // ── MARCACIÓN DE ASISTENCIA ───────────────────────────────────────────────
  {
    keywords: ['marcar', 'marcacion', 'marco', 'timbre', 'timbrar', 'asistencia', 'entrada', 'salida almuerzo', 'entrada almuerzo', 'salida del trabajo'],
    answer:
      'Cada día laboral debe registrar 4 marcaciones en orden:\n1. ENTRADA — al llegar\n2. SALIDA AL LUNCH — al salir al almuerzo\n3. ENTRADA DEL LUNCH — al regresar\n4. SALIDA — al terminar la jornada\n\nHaga clic en el botón activo (verde oscuro). Si intenta marcar SALIDA antes de las 16:30, el sistema pedirá confirmación.',
  },
  {
    keywords: ['no puedo marcar', 'error marcacion', 'ip', 'red', 'vlan', 'marcacion presencial'],
    answer:
      'Si tiene modalidad PRESENCIAL y no puede marcar, verifique que esté conectado a la red institucional. La marcación PRESENCIAL solo se permite desde las VLANs internas autorizadas.\n\nSi trabaja fuera de la oficina temporalmente, solicite a TH que cambie su modalidad a TEMPORAL.',
  },
  {
    keywords: ['modalidad marcacion', 'teletrabajo', 'remoto', 'temporal', 'presencial'],
    answer:
      'Existen 3 modalidades de marcación:\n• PRESENCIAL: solo desde la red institucional interna.\n• TEMPORAL: desde cualquier red (para comisiones o viajes).\n• TELETRABAJO: desde cualquier red (trabajo permanente desde casa).\n\nSolicite el cambio de modalidad a Talento Humano.',
  },
  {
    keywords: ['biometrico', 'reloj', 'zkteco', 'reconocimiento facial', 'huella'],
    answer:
      'El reloj biométrico ZKTeco registra marcaciones automáticamente. El sistema asigna el concepto (ENTRADA, SALIDA AL LUNCH, etc.) por la secuencia del día. Las marcaciones del reloj y las marcaciones web se combinan libremente.',
  },
  {
    keywords: ['historial asistencia', 'mis marcaciones', 'ver marcaciones', 'reporte personal asistencia'],
    answer:
      'En el menú "Asistencia" → "Mis Marcaciones" puede ver su historial diario de marcaciones con los atrasos calculados y su estado de justificación:\n• Verde "Justificado": el atraso está cubierto por un permiso.\n• Naranja "Parcial": el permiso cubre solo parte del atraso.\n• Rojo "Sin justificar": no hay permiso que lo cubra.',
  },

  // ── PERMISOS Y LICENCIAS ──────────────────────────────────────────────────
  {
    keywords: ['solicitar permiso', 'pedir permiso', 'nuevo permiso', 'permiso', 'licencia', 'ausencia', 'solicito permiso', 'pido permiso'],
    answer:
      'Para solicitar un permiso:\n1. Vaya al menú "Permisos".\n2. Haga clic en "Nuevo Permiso".\n3. Seleccione el tipo, fechas y horario (entrada/salida/entre jornada).\n4. Si el permiso es no descontable, puede adjuntar documentos.\n5. Haga clic en "Solicitar".\n\nEl permiso queda en estado PENDIENTE hasta que el supervisor lo apruebe.',
  },
  {
    keywords: ['aprobar permiso', 'negar permiso', 'pendientes permiso', 'revisar permiso'],
    answer:
      'Para aprobar o negar permisos (Supervisor/TH/Admin):\n1. En la lista de permisos, los PENDIENTES aparecen destacados.\n2. Haga clic en el permiso para ver el detalle.\n3. Seleccione "Aprobar" o "Negar" (con motivo si niega).\n\nAl aprobar un permiso descontable, el saldo de vacaciones se descuenta automáticamente.',
  },
  {
    keywords: ['anular permiso', 'anulado', 'reversar permiso', 'permiso no utilizado'],
    answer:
      'Un permiso aprobado puede anularse si el empleado no lo utilizó (llegó a su hora normal).\nSolo TH/Admin puede anularlo:\n1. Busque el permiso APROBADO.\n2. Haga clic en "Anular".\n3. Ingrese el motivo.\n4. Confirme.\n\nAl anular, el saldo de vacaciones descontado se devuelve automáticamente.',
  },
  {
    keywords: ['tipo permiso', 'tipo horario', 'que cubre permiso', 'justifica atraso'],
    answer:
      'Los permisos tienen tipo de horario:\n• ENTRADA: justifica llegada tarde (cubre el atraso de entrada).\n• SALIDA: justifica salida anticipada.\n• ENTRE JORNADA: justifica atraso en el regreso del almuerzo.\n\nLos permisos descontables descuentan días del saldo de vacaciones; los no descontables no.',
  },

  // ── VACACIONES ────────────────────────────────────────────────────────────
  {
    keywords: ['saldo vacaciones', 'cuantos dias vacaciones', 'dias disponibles', 'vacaciones disponibles'],
    answer:
      'Su saldo de vacaciones se calcula según su tipo de contrato:\n• LOSEP: 30 días/año (2.50 días/mes).\n• Código del Trabajo: 15 días/año base. Desde el 6° año completo se suma 1 día adicional por cada año extra (hasta máximo 30 días/año).\n\nVer saldo: en el Dashboard, tarjeta "Saldo Vacaciones", o al abrir el formulario de nueva solicitud.',
  },
  {
    keywords: ['solicitar vacacion', 'pedir vacacion', 'vacacion', 'solicitud vacacion', 'solicito vacacion', 'pido vacacion'],
    answer:
      'Para solicitar vacaciones:\n1. Vaya al menú "Vacaciones".\n2. Haga clic en "Solicitar Vacaciones".\n3. Seleccione las fechas de inicio y fin.\n4. Si tiene planificación aprobada, el sistema la mostrará como referencia.\n5. Agregue observación (opcional) y haga clic en "Solicitar".\n\nQueda PENDIENTE hasta que el supervisor apruebe.',
  },
  {
    keywords: ['aprobar vacacion', 'backup', 'reemplazo vacacion'],
    answer:
      'Al aprobar una solicitud de vacaciones, el supervisor debe seleccionar un empleado de backup del mismo departamento. El sistema mostrará automáticamente los empleados disponibles del departamento.',
  },
  {
    keywords: ['planificacion vacaciones', 'planificar vacacion', 'planificacion anual'],
    answer:
      'La planificación anual de vacaciones permite organizar los períodos del año. Estados:\n• PLANIFICADO: período programado.\n• APROBADO: confirmado.\n• REPLANIFICADO: modificado.\n• NEGADO/ELIMINADO: el empleado puede volver a planificar ese período.',
  },
  {
    keywords: ['liquidacion vacaciones', 'liquidar vacacion', 'comision servicios', 'desvinculacion'],
    answer:
      'La liquidación de vacaciones aplica en casos de comisión de servicios o desvinculación. Vaya al menú "Planificación" → "Liquidación de Vacaciones". El motivo válido depende de la modalidad laboral del empleado.',
  },
  {
    keywords: ['reporte saldo vacacion', 'kardex vacacion', 'saldo de todos'],
    answer:
      'El Reporte de Saldo de Vacaciones (Planificación → Reporte de Saldo) muestra el saldo actual de todos los empleados. Haga clic en una fila para expandir el kardex completo del empleado. Solo accesible para TH y Administrador.',
  },

  // ── ACCIONES DE PERSONAL ──────────────────────────────────────────────────
  {
    keywords: ['accion personal', 'encargo', 'subrogacion', 'destitucion', 'cesacion', 'accion de personal'],
    answer:
      'Las acciones de personal registran cambios en la situación laboral (encargo, subrogación, ingreso, destitución, cesación). Solo TH ACCIONES PERSONAL y Administrador pueden crearlas.\n\nFlujo: crear en BORRADOR → revisar → "Procesar" (asigna número y genera PDF oficial).',
  },
  {
    keywords: ['firmantes pdf', 'nombre firmante', 'responsable th', 'autoridad nominadora'],
    answer:
      'Los firmantes del PDF de acciones de personal y certificados laborales se configuran en Admin → Configuración:\n• FIRMANTE_TH_NOMBRE / FIRMANTE_TH_CARGO\n• FIRMANTE_AUTORIDAD_NOMBRE / FIRMANTE_AUTORIDAD_CARGO\n\nSolo el Administrador puede modificarlos.',
  },

  // ── HORAS EXTRAS ──────────────────────────────────────────────────────────
  {
    keywords: ['horas extras', 'horas extra', 'extraordinarias', 'suplementarias', 'sobretiempo', 'he planificacion'],
    answer:
      'El módulo de Horas Extras permite planificar y registrar horas trabajadas fuera de la jornada. Flujo:\n1. Empleado crea planificación → PENDIENTE.\n2. Supervisor aprueba → APROBADO.\n3. TH NOMINA asigna memorando → PROCESADO.\n4. Empleado registra horas reales → EN REVISION.\n5. TH NOMINA revisa y supervisor confirma → APROBADO.\n\nLas horas se clasifican automáticamente como extraordinarias o suplementarias según el horario y el día.',
  },
  {
    keywords: ['clasificacion horas', 'tipo hora extra', 'cuando es extraordinaria', 'cuando es suplementaria'],
    answer:
      'Clasificación automática:\n• Lunes a Viernes 00:00–06:00 → Extraordinarias.\n• Lunes a Viernes 06:00–08:00 y 16:30–24:00 → Suplementarias.\n• Fin de semana o feriado (todo el día) → Extraordinarias.\n• Lunes a Viernes 08:00–16:30 → Jornada normal (no cuenta).',
  },

  // ── CERTIFICADOS LABORALES ────────────────────────────────────────────────
  {
    keywords: ['certificado laboral', 'certificado de trabajo', 'constancia laboral', 'certificacion'],
    answer:
      'Los certificados laborales solo los emiten TH y Administrador:\n1. Menú "Certificados Laborales".\n2. Busque el empleado por nombre o cédula.\n3. Clic en "Generar Certificado" → confirme.\n4. El PDF se descarga automáticamente (numeración DATH-CL-NNN-YYYY).\n5. Después de firmarlo físicamente, súbalo con el botón azul "Subir firmado".',
  },

  // ── REPORTES ASISTENCIA ───────────────────────────────────────────────────
  {
    keywords: ['reporte atrasos', 'informe atrasos', 'reporte asistencia', 'marcaciones faltantes', 'sin atrasos', 'movimientos personal'],
    answer:
      'Desde el menú "Reportes" (TH/Admin) puede generar:\n• Atrasos: empleados con atrasos pendientes en el período.\n• Marcaciones No Realizadas: días con marcaciones incompletas.\n• Sin Atrasos: empleados sin ningún atraso.\n• Movimientos de Personal: vacaciones, permisos, licencias y comisiones.\n\nTodos los reportes se exportan a Excel o PDF.',
  },
  {
    keywords: ['reporte personal', 'listado empleados', 'distributivo', 'sercop vencido', 'guarderia'],
    answer:
      'El Reporte de Personal (Empleados → Reporte) permite filtrar empleados por hasta 16 criterios y exportar a Excel o PDF. Muestra alertas de SERCOP vencido o próximo a vencer, persona sustituta y hijos en edad de guardería.',
  },

  // ── EMPLEADOS ─────────────────────────────────────────────────────────────
  {
    keywords: ['crear empleado', 'nuevo empleado', 'registrar empleado', 'ingresar empleado'],
    answer:
      'Para crear un empleado (TH/Admin):\n1. Menú "Empleados" → "Nuevo Empleado".\n2. Complete las 4 pestañas: Datos Personales, Cargo y Contrato, Datos del Puesto, Asistencia.\n3. Haga clic en "Guardar".\n\nLa cédula es el identificador único y no puede modificarse.',
  },
  {
    keywords: ['inactivar empleado', 'dar de baja', 'estado inactivo', 'desvinculacion empleado'],
    answer:
      'Los empleados nunca se eliminan del sistema. Para inactivar un empleado:\n1. Abra la ficha del empleado.\n2. Cambie "Estado" a "INACTIVO".\n3. Seleccione el motivo de salida (renuncia, fin de contrato, comisión, jubilación).\n4. Guarde.',
  },
  {
    keywords: ['datos sociales', 'discapacidad', 'grupo vulnerable', 'enfermedad catastrofica', 'persona sustituta', 'hijos'],
    answer:
      'Los datos sociales se registran en la pestaña "Datos Personales" de la ficha del empleado:\n• Grupo vulnerable y prioritario.\n• Discapacidad (tipo CONADIS + porcentaje).\n• Enfermedad catastrófica (tipo MSP).\n• Persona sustituta (con fecha de caducidad y PDF en Alfresco).\n• Hijos menores: el sistema muestra "Guardería" si el hijo tiene menos de 5 años.',
  },

  // ── NÓMINA ────────────────────────────────────────────────────────────────
  {
    keywords: ['decimo tercero', 'decimo cuarto', 'decimotercero', 'decimocuarto', 'decimos'],
    answer:
      'El módulo de Décimos calcula el Décimo Tercero y Cuarto mensual proporcional (rol TH NOMINA).\n• Décimo Tercero: (Sueldo ÷ 12 ÷ 30) × días laborados.\n• Décimo Cuarto: (SBU ÷ 12 ÷ 30) × días laborados.\n\nFlujo: Calcular → revisar → Cerrar período. Un período cerrado no puede recalcularse.',
  },
  {
    keywords: ['fondos reserva', 'fondo de reserva'],
    answer:
      'Los Fondos de Reserva se calculan para empleados con más de 12 meses de servicio. Fórmula: (Sueldo × 8.33% ÷ 30) × días. Tipos: MENSUAL (pago al empleado) o IESS (depósito al IESS). Solo visible para TH NOMINA.',
  },
  {
    keywords: ['rol de pagos', 'rol pago', 'nomina', 'sueldo', 'aportes iess', 'iece', 'secap', 'quirografario', 'hipotecario'],
    answer:
      'El Rol de Pagos (TH NOMINA) incluye:\n• Sueldo proporcional al mes.\n• Aportes IESS (personal y patronal), IECE, SECAP.\n• Descuentos editables: quirografario, hipotecario, impuesto a la renta, póliza blanket.\n\nHaga clic en la celda del descuento para editarlo. También puede importar descuentos desde CSV.',
  },

  // ── ADQUISICIONES ─────────────────────────────────────────────────────────
  {
    keywords: ['ingreso bienes', 'orden compra', 'compra', 'proveedor', 'factura', 'recibir bienes'],
    answer:
      'Para registrar un ingreso de bienes:\n1. Menú "Ingresos de Bienes" → "Nuevo Ingreso".\n2. Seleccione proveedor, proceso de contratación, factura y fecha.\n3. Agregue los artículos con cantidad y precio.\n4. Guarde en BORRADOR o confirme directamente.\n\nAl confirmar se actualiza el stock con precio promedio ponderado.',
  },
  {
    keywords: ['egreso bienes', 'despacho', 'sacar bodega', 'salida bienes', 'entregar material'],
    answer:
      'Para registrar un egreso de bienes:\n1. Menú "Egresos de Bienes" → "Nuevo Egreso".\n2. Seleccione la dirección/área y empleado receptor.\n3. Agregue artículos y cantidades.\n4. Confirme para descontar del stock.',
  },
  {
    keywords: ['solicitud material', 'solicitar material', 'pedir suministro', 'materiales de oficina'],
    answer:
      'Para solicitar materiales:\n1. Menú "Solicitudes de Materiales" → "Nueva Solicitud".\n2. Agregue los artículos y cantidades que necesita.\n3. Envíe la solicitud.\n4. El supervisor la revisa y aprueba con las cantidades autorizadas.\n5. El área de Bienes realiza el despacho.',
  },
  {
    keywords: ['kardex', 'nic 2', 'movimientos inventario', 'historial inventario'],
    answer:
      'El Kardex NIC 2 (Adquisiciones → Reportes → Kardex) muestra todos los movimientos valorizados de un artículo. Puede filtrar por artículo individual, Nivel 1 MEF o Nivel 2 MEF. El resultado incluye entradas, salidas, precio unitario y saldo valorizado. Disponible en PDF.',
  },
  {
    keywords: ['inventario', 'stock', 'existencia', 'articulo', 'ver stock'],
    answer:
      'El inventario actual se consulta en Adquisiciones → "Artículos". La lista muestra cada artículo con su stock actual, precio unitario, IVA y categoría MEF. Puede buscar por nombre o código.',
  },
  {
    keywords: ['ajuste inventario', 'toma fisica', 'corregir stock', 'diferencia inventario'],
    answer:
      'Para corregir el stock con una toma física:\n1. Menú "Ajuste de Inventario".\n2. Busque el artículo.\n3. Ingrese la cantidad física contada.\n4. El sistema calcula automáticamente si es ajuste positivo o negativo.\n5. Confirme. Se registra en el kardex.',
  },
  {
    keywords: ['caja chica', 'proceso caja chica'],
    answer:
      'El proceso CAJA CHICA tiene un comportamiento especial: al confirmar el ingreso, el sistema pregunta si confirma con egreso simultáneo o sin egreso. Además, no aplica precio promedio ponderado — el precio del artículo siempre se actualiza al último precio de compra.',
  },
  {
    keywords: ['libro compras', 'reporte compras', 'proveedores reporte'],
    answer:
      'El Libro de Compras (Adquisiciones → Reportes → Libro de Compras) lista todas las compras del período con datos del proveedor y factura. Incluye el Top 5 de proveedores por monto. Disponible en PDF formato SRI.',
  },
  {
    keywords: ['inventario mensual', 'reporte mensual bienes', 'partida presupuestaria inventario'],
    answer:
      'El Inventario Mensual (Adquisiciones → Reportes → Inventario Mensual) muestra saldo anterior, ingresos, egresos y saldo final del mes agrupado por partida presupuestaria MEF. Seleccione mes y año, luego haga clic en "Generar". Disponible en PDF landscape.',
  },

  // ── TRANSPORTES ───────────────────────────────────────────────────────────
  {
    keywords: ['mantenimiento vehicular', 'vehiculo reparacion', 'taller', 'requerimiento mantenimiento', 'orden trabajo'],
    answer:
      'Flujo de mantenimiento vehicular:\n1. Conductor crea requerimiento (tipo, km, actividades) → PENDIENTE.\n2. TRANSPORTE genera la orden de trabajo (asigna taller y fecha) → ORDEN_GENERADA.\n3. Vehículo ingresa al taller → EN_TALLER.\n4. TRANSPORTE finaliza y actualiza km del vehículo → FINALIZADO.\n\nEl PDF de la orden está disponible desde ORDEN_GENERADA.',
  },
  {
    keywords: ['solicitar vehiculo', 'movilizacion', 'pedir auto', 'transporte institucional', 'viaje institucional', 'orden movilizacion', 'solicito vehiculo', 'movilizacion', 'solicitar movilizacion', 'solicito movilizacion'],
    answer:
      'Para solicitar movilización:\n1. Vaya al módulo Transportes → "Movilización".\n2. Haga clic en "Nueva Solicitud".\n3. Complete fecha, hora de salida y retorno, origen, destino y motivo.\n4. Envíe → PENDIENTE.\n5. TRANSPORTE asignará vehículo y conductor → APROBADO.\n\nNota: debe tener activo el permiso "puede solicitar vehículo" en su ficha de empleado.',
  },
  {
    keywords: ['hoja de ruta', 'km salida', 'km retorno', 'completar movilizacion'],
    answer:
      'Al finalizar el viaje, el conductor completa la hoja de ruta:\n1. En la lista de movilizaciones APROBADAS, seleccione la asignada.\n2. Ingrese los km de salida y km de retorno.\n3. Confirme. El estado cambia a COMPLETADO y se actualiza el km del vehículo.',
  },
  {
    keywords: ['vale combustible', 'gasolina', 'diesel', 'combustible', 'vale'],
    answer:
      'Para emitir un vale de combustible (CONDUCTOR / TRANSPORTE):\n1. Menú "Vales de Combustible" → "Nuevo Vale".\n2. Seleccione vehículo, gasolinera y fecha.\n3. Ingrese los combustibles con cantidad y precio (el valor se calcula automáticamente).\n4. Guarde. El PDF (formato FR05-PRO.GA-TR.001) se abre automáticamente para imprimir.',
  },
  {
    keywords: ['plan preventivo', 'mantenimiento preventivo', 'km hito', 'actividad preventiva'],
    answer:
      'El Plan Preventivo define las actividades de mantenimiento programadas por vehículo según los hitos de km (ej: cada 5.000 km). Se administra en Transportes → "Plan Preventivo". Puede cargarse masivamente mediante importación CSV.',
  },

  // ── DOCUMENTOS / ALFRESCO ─────────────────────────────────────────────────
  {
    keywords: ['alfresco', 'documento firmado', 'subir pdf firmado', 'pdf firmado', 'subir documento'],
    answer:
      'Para subir un documento firmado a Alfresco:\n1. Primero descargue el PDF desde el sistema (botón "PDF" o "Descargar").\n2. Imprímalo y obtenga las firmas físicas.\n3. Escanéelo como PDF.\n4. En el sistema, haga clic en el botón azul "Subir firmado".\n5. Seleccione el archivo escaneado.\n\nUna vez subido, el botón cambia a rojo "PDF" para descargarlo desde Alfresco.',
  },
  {
    keywords: ['descargar pdf', 'generar pdf', 'imprimir', 'como descargo'],
    answer:
      'Los PDFs se generan desde el módulo correspondiente:\n• Acciones de Personal: botón "PDF" en el listado.\n• Horas Extras: botón "PDF" en estados APROBADO/PROCESADO.\n• Certificados: se descarga automáticamente al generar.\n• Movilización: botón "PDF" desde estado APROBADO.\n• Vales combustible: se abre automáticamente al guardar.',
  },

  // ── ADMINISTRACIÓN ────────────────────────────────────────────────────────
  {
    keywords: ['configuracion sistema', 'parametros', 'admin configuracion', 'vlans permitidas', 'modo mantenimiento'],
    answer:
      'La configuración del sistema (Admin → Configuración) permite ajustar parámetros globales como VLANs permitidas para marcación, texto del artículo de atrasos, firmantes de PDFs, modo mantenimiento por módulo, y más. Solo accesible para ADMINISTRADOR.',
  },
  {
    keywords: ['modo mantenimiento sistema', 'sistema bloqueado', 'no puedo acceder pantalla verde', 'pantalla mantenimiento'],
    answer:
      'Cuando ve la pantalla de mantenimiento, el sistema está en mantenimiento programado. Solo el Administrador puede desactivarlo desde Admin → Configuración (parámetros MODO_MANTENIMIENTO_TH, MODO_MANTENIMIENTO_ADQ o MODO_MANTENIMIENTO_TRANS según el módulo).',
  },
  {
    keywords: ['departamento', 'estructura organizacional', 'crear departamento'],
    answer:
      'Los departamentos se gestionan en Admin → Departamentos. La numeración recomendada:\n• Departamentos padre: múltiplos de 10 (10, 50, 60, 70, 80, 90).\n• Departamentos hijo: número del padre +1 a +9 (ej: 51, 52, 53 para hijos del 50).\n\nNota: el departamento 999 es un placeholder del sistema y no aparece en las listas.',
  },
  {
    keywords: ['auditoria', 'log sistema', 'historial cambios', 'quien hizo', 'trazabilidad'],
    answer:
      'El log de auditoría (Admin → Auditoría) registra todas las acciones críticas: quién las realizó, cuándo, desde qué IP y qué datos cambiaron. Haga clic en cualquier fila para ver el detalle completo con datos anteriores y nuevos. Solo para ADMINISTRADOR y TALENTO HUMANO.',
  },
  {
    keywords: ['aviso ticker', 'aviso informativo', 'mensaje launcher', 'publicidad sistema'],
    answer:
      'Los avisos informativos del Launcher se gestionan en Admin → Avisos. Puede crear, editar, ordenar y activar/desactivar mensajes. La dirección (horizontal o vertical) se configura en Admin → Configuración → AVISOS_DIRECCION.',
  },
  {
    keywords: ['zkteco', 'reloj biometrico', 'dispositivo', 'activar reloj'],
    answer:
      'Los relojes biométricos se gestionan en Admin → ZKTeco. Al conectar un nuevo reloj, este se registra automáticamente. Desde la vista puede:\n• Activar o desactivar el dispositivo.\n• Asignarle un nombre descriptivo.\n• Ver su última conexión y dirección IP.',
  },
  {
    keywords: ['sbu', 'salario basico', 'salario basico unificado'],
    answer:
      'El SBU (Salario Básico Unificado) se registra en Admin → SBU. Debe actualizar el valor cada año cuando el Ministerio de Trabajo lo publique, ya que se usa en el cálculo del Décimo Cuarto.',
  },
  {
    keywords: ['aportes iess', 'porcentaje iess', 'tasa aporte', 'aporte patronal', 'aporte personal'],
    answer:
      'Los porcentajes de aporte al IESS se configuran en Admin → Aportes IESS. Existen tasas diferentes para LOSEP y Código del Trabajo. Solo el Administrador puede modificarlos cuando el Ministerio de Trabajo actualice los porcentajes.',
  },

  // ── DASHBOARD ─────────────────────────────────────────────────────────────
  {
    keywords: ['dashboard', 'pantalla principal', 'pantalla inicio modulo', 'que veo al entrar'],
    answer:
      'El Dashboard muestra información según su rol:\n• Empleado: saldo de vacaciones, permisos pendientes y próximo período de vacaciones.\n• Supervisor: pendientes del equipo, estado del equipo hoy y vacaciones próximas.\n• Admin/TH: métricas globales y gráfico de atrasos por coordinación.',
  },
]
