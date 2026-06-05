<?php
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ReportesController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\CuadreController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\JornadaController;
use App\Http\Controllers\OpcionController;
use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\PermisosController;
use App\Http\Controllers\SupervisorController;
use App\Http\Controllers\VacacionesController;
use App\Http\Controllers\ImportacionController;
use App\Http\Controllers\PeriodoPlanificacionController;
use App\Http\Controllers\PlanificacionVacController;
use App\Http\Controllers\ReportePlanificacionController;
use App\Http\Controllers\LiquidacionVacController;
use App\Http\Controllers\HorasExtrasController;
use App\Http\Controllers\NominaController;
use App\Http\Controllers\RolPagoController;
use App\Http\Controllers\TransporteController;
use App\Http\Controllers\Adquisiciones\AdqDashboardController;
use App\Http\Controllers\Adquisiciones\ProveedorController;
use App\Http\Controllers\Adquisiciones\ArticuloController;
use App\Http\Controllers\Adquisiciones\OrdenCompraController;
use App\Http\Controllers\Adquisiciones\SolicitudMaterialController;
use App\Http\Controllers\Adquisiciones\ReporteAdqController;
use App\Http\Controllers\Adquisiciones\AjusteController;
use App\Http\Controllers\ZktecoController;
use Illuminate\Support\Facades\Route;

// Rutas PÚBLICAS
Route::post("/login", [AuthController::class, "login"])->name("login");

Route::get("/modo-mantenimiento", function () {
    $valor = \Illuminate\Support\Facades\DB::table('dbo.d2_configuracion')
        ->whereRaw("LOWER(concepto) = 'modo_mantenimiento'")
        ->value('valor');
    return response()->json(['activo' => $valor === '1']);
});

// Endpoints ADMS — reloj biométrico ZKTeco (sin autenticación)
Route::prefix("iclock")->group(function () {
    Route::post("cdata",                        [ZktecoController::class, "cdata"]);
    Route::get("getrequest",                    [ZktecoController::class, "getrequest"]);
    Route::match(["get", "post"], "registry",   [ZktecoController::class, "registry"]);
    Route::post("devicecmd",                    [ZktecoController::class, "devicecmd"]);
});

// Servir archivos del storage público a través del API (resuelve SPA catch-all)
Route::get("/storage-file/{path}", function (string $path) {
    $path = ltrim(str_replace('..', '', $path), '/');
    if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($path)) {
        abort(404);
    }
    return \Illuminate\Support\Facades\Storage::disk('public')->response($path);
})->where('path', '.*');

// Rutas PROTEGIDAS
Route::middleware("auth:sanctum")->group(function () {

    // Sesión
    Route::post("/logout", [AuthController::class, "logout"]);
    Route::get("/me",      [AuthController::class, "me"]);

    // Roles
    Route::get("/roles",          [RolController::class, "index"]);
    Route::post("/roles",         [RolController::class, "store"]);
    Route::get("/roles/{id}",     [RolController::class, "show"]);
    Route::put("/roles/{id}",     [RolController::class, "update"]);
    Route::delete("/roles/{id}",  [RolController::class, "destroy"]);

    // Administración
    Route::prefix("admin")->group(function () {
        Route::apiResource("departamentos", \App\Http\Controllers\Admin\DepartamentoController::class);
        Route::patch("departamentos/{id}/inactivar", [\App\Http\Controllers\Admin\DepartamentoController::class, "inactivar"]);
        Route::patch("departamentos/{id}/activar",   [\App\Http\Controllers\Admin\DepartamentoController::class, "activar"]);
        Route::apiResource("razones",       \App\Http\Controllers\Admin\RazonController::class)->except(['destroy']);
        Route::patch("razones/{id}/inactivar", [\App\Http\Controllers\Admin\RazonController::class, "inactivar"]);
        Route::apiResource("turnos",        \App\Http\Controllers\Admin\TurnoController::class);
        Route::post("turnos/{id}/horarios", [\App\Http\Controllers\Admin\TurnoController::class, "guardarHorarios"]);
        Route::apiResource("jornadas",      JornadaController::class);
        Route::apiResource("opciones",      OpcionController::class);
        Route::patch("opciones/{id}/toggle", [OpcionController::class, "toggleEstado"]);
        Route::get("opciones-categorias",    [OpcionController::class, "categorias"]);

        // Calendario
        Route::get("calendario",                        [\App\Http\Controllers\Admin\CalendarioController::class, "index"]);
        Route::post("calendario/feriados-ecuador",      [\App\Http\Controllers\Admin\CalendarioController::class, "cargarFeriadosEcuador"]);
        Route::post("calendario",                       [\App\Http\Controllers\Admin\CalendarioController::class, "store"]);
        Route::put("calendario/{fecha}/{ubicacion}",    [\App\Http\Controllers\Admin\CalendarioController::class, "update"]);
        Route::delete("calendario/{fecha}/{ubicacion}", [\App\Http\Controllers\Admin\CalendarioController::class, "destroy"]);

        // Configuración
        Route::post("configuracion/parametros-base",    [\App\Http\Controllers\Admin\ConfiguracionController::class, "cargarParametrosBase"]);
        Route::get("configuracion",                     [\App\Http\Controllers\Admin\ConfiguracionController::class, "index"]);
        Route::post("configuracion",                    [\App\Http\Controllers\Admin\ConfiguracionController::class, "store"]);
        Route::put("configuracion/{concepto}",          [\App\Http\Controllers\Admin\ConfiguracionController::class, "update"]);
        Route::delete("configuracion/{concepto}",       [\App\Http\Controllers\Admin\ConfiguracionController::class, "destroy"]);

        // Avisos (ticker launcher)
        Route::get("avisos/activos",              [\App\Http\Controllers\Admin\AvisoController::class, "activos"]);
        Route::get("avisos",                      [\App\Http\Controllers\Admin\AvisoController::class, "index"]);
        Route::post("avisos",                     [\App\Http\Controllers\Admin\AvisoController::class, "store"]);
        Route::put("avisos-direccion",            [\App\Http\Controllers\Admin\AvisoController::class, "setDireccion"]);
        Route::put("avisos/{id}",                 [\App\Http\Controllers\Admin\AvisoController::class, "update"]);
        Route::delete("avisos/{id}",              [\App\Http\Controllers\Admin\AvisoController::class, "destroy"]);

        // Modalidades Laborales
        Route::get("modalidades-laborales",       [\App\Http\Controllers\Admin\ModalidadLaboralController::class, "index"]);
        Route::post("modalidades-laborales",      [\App\Http\Controllers\Admin\ModalidadLaboralController::class, "store"]);
        Route::put("modalidades-laborales/{id}",  [\App\Http\Controllers\Admin\ModalidadLaboralController::class, "update"]);

        // Aportes IESS
        Route::get("aportes-iess",          [\App\Http\Controllers\Admin\AportesIessController::class, "index"]);
        Route::get("aportes-iess/vigentes", [\App\Http\Controllers\Admin\AportesIessController::class, "vigentes"]);
        Route::post("aportes-iess",         [\App\Http\Controllers\Admin\AportesIessController::class, "store"]);
        Route::put("aportes-iess/{id}",     [\App\Http\Controllers\Admin\AportesIessController::class, "update"]);
        Route::delete("aportes-iess/{id}", [\App\Http\Controllers\Admin\AportesIessController::class, "destroy"]);

        // Auditoría centralizada (solo ADMINISTRADOR)
        Route::get("auditoria", [\App\Http\Controllers\Admin\AuditoriaController::class, "index"]);

        // Dispositivos ZKTeco
        Route::get("zkteco",          [ZktecoController::class, "index"]);
        Route::put("zkteco/{id}",     [ZktecoController::class, "update"]);
        Route::delete("zkteco/{id}",  [ZktecoController::class, "destroy"]);
    });

    // Opciones de menú
    Route::get("/opciones",             [RolController::class, "opciones"]);
    Route::post("/roles/{id}/opciones", [RolController::class, "asignarOpciones"]);

    // Dashboard
    Route::get("/dashboard", [DashboardController::class, "index"]);
    Route::get("/dashboard/atrasos-coordinacion", [DashboardController::class, "atrasosCoordinacion"]);

    // Departamentos
    Route::get("/departamentos", [EmpleadoController::class, "departamentos"]);
    Route::get("/empleados/partidas-vacantes",    [EmpleadoController::class, "partidasVacantes"]);
    Route::get("/empleados/catalogos-sociales",   [EmpleadoController::class, "catalogosSociales"]);
    Route::get("/empleados/reporte/resumen",      [\App\Http\Controllers\ReporteEmpleadosController::class, "resumen"]);
    Route::get("/empleados/reporte",              [\App\Http\Controllers\ReporteEmpleadosController::class, "index"]);

    // Empleados
    Route::get("/empleados",         [EmpleadoController::class, "index"]);
    Route::post("/empleados",        [EmpleadoController::class, "store"]);
    Route::get("/empleados/{id}",    [EmpleadoController::class, "show"]);
    Route::put("/empleados/{id}",    [EmpleadoController::class, "update"]);
    Route::delete("/empleados/{id}", [EmpleadoController::class, "destroy"]);

    Route::post("/empleados/importar-distributivo", [EmpleadoController::class, "importarDistributivo"]);
    Route::post("/empleados/{id}/reset-password",  [EmpleadoController::class, "resetPassword"]);
    Route::post("/empleados/{id}/foto",            [EmpleadoController::class, "subirFoto"]);
    Route::delete("/empleados/{id}/foto",          [EmpleadoController::class, "eliminarFoto"]);
    Route::post("/cambiar-password",               [EmpleadoController::class, "cambiarPassword"]);

    Route::get("/empleados/{id}/hijos",                    [EmpleadoController::class, "hijoIndex"]);
    Route::post("/empleados/{id}/hijos",                   [EmpleadoController::class, "hijoStore"]);
    Route::delete("/empleados/{id}/hijos/{hijoId}",        [EmpleadoController::class, "hijoDestroy"]);
    Route::post("/empleados/{id}/sustituta-doc",           [EmpleadoController::class, "subirDocSustituta"]);
    Route::get("/empleados/{id}/sustituta-doc",            [EmpleadoController::class, "descargarDocSustituta"]);
    Route::delete("/empleados/{id}/sustituta-doc",         [EmpleadoController::class, "eliminarDocSustituta"]);

    // Asignación de roles a empleados
    Route::get("/empleados/{id_emp}/roles",             [RolController::class, "rolesEmpleado"]);
    Route::post("/empleados/{id_emp}/roles",            [RolController::class, "asignarRolEmpleado"]);
    Route::delete("/empleados/{id_emp}/roles/{id_rol}", [RolController::class, "quitarRolEmpleado"]);

    // Acciones de Personal
    Route::get("/acciones-personal/reporte/pdf",           [\App\Http\Controllers\AccionPersonalController::class, "reportePdf"]);
    Route::get("/acciones-personal/reporte/excel",         [\App\Http\Controllers\AccionPersonalController::class, "reporteExcel"]);
    Route::get("/acciones-personal",                       [\App\Http\Controllers\AccionPersonalController::class, "index"]);
    Route::post("/acciones-personal",                      [\App\Http\Controllers\AccionPersonalController::class, "store"]);
    Route::get("/acciones-personal/{id}",                  [\App\Http\Controllers\AccionPersonalController::class, "show"]);
    Route::patch("/acciones-personal/{id}/procesar",       [\App\Http\Controllers\AccionPersonalController::class, "procesar"]);
    Route::patch("/acciones-personal/{id}/editar-borrador",[\App\Http\Controllers\AccionPersonalController::class, "editarBorrador"]);
    Route::patch("/acciones-personal/{id}/estado",         [\App\Http\Controllers\AccionPersonalController::class, "cambiarEstado"]);
    Route::get("/acciones-personal/{id}/pdf",              [\App\Http\Controllers\AccionPersonalController::class, "pdf"]);
    Route::post("/acciones-personal/{id}/subir-firmado",   [\App\Http\Controllers\AccionPersonalController::class, "subirFirmado"]);
    Route::get("/acciones-personal/{id}/descargar-firmado",[\App\Http\Controllers\AccionPersonalController::class, "descargarFirmado"]);

    // Supervisores
    Route::get("/supervisores",                          [SupervisorController::class, "index"]);
    Route::post("/supervisores",                         [SupervisorController::class, "store"]);
    Route::delete("/supervisores/{id}",                  [SupervisorController::class, "destroy"]);
    Route::get("/supervisores/empleado/{id_emp}",        [SupervisorController::class, "supervisorDeEmpleado"]);
    Route::get("/supervisores/departamentos",        [SupervisorController::class, "departamentosConSupervisor"]);
    Route::get("/supervisores/empleado/{id_emp}",        [SupervisorController::class, "supervisorDeEmpleado"]);

    // Asistencia
    Route::get("/asistencia/mi-estado",   [AsistenciaController::class, "miEstado"]);
    Route::post("/asistencia/marcar",     [AsistenciaController::class, "marcar"]);
    Route::get("/asistencia/listado",          [AsistenciaController::class, "listado"]);
    Route::get("/asistencia/reporte",          [AsistenciaController::class, "reporte"]);
    Route::get("/asistencia/mi-reporte",       [AsistenciaController::class, "miReporte"]);
    Route::get("/asistencia/reporte-sin-atrasos", [AsistenciaController::class, "reporteSinAtrasos"]);

    // Importacion
    Route::get("/importacion/plantilla",  [ImportacionController::class, "plantilla"]);
    Route::post("/importacion/preview",   [ImportacionController::class, "preview"]);
    Route::post("/importacion/importar",  [ImportacionController::class, "importar"]);

    // Permisos
    Route::get("/permisos/razones",      [PermisosController::class, "razones"]);
    Route::get("/permisos/estadistica",     [PermisosController::class, "estadistica"]);
    Route::get("/permisos/mi-rol",           [PermisosController::class, "miRol"]);
    Route::get("/permisos",              [PermisosController::class, "index"]);
    Route::post("/permisos",             [PermisosController::class, "store"]);
    Route::get("/permisos/{id}",                                   [PermisosController::class, "show"]);
    Route::patch("/permisos/{id}/aprobar",                         [PermisosController::class, "aprobar"]);
    Route::patch("/permisos/{id}/negar",                           [PermisosController::class, "negar"]);
    Route::delete("/permisos/{id}",                                [PermisosController::class, "destroy"]);
    Route::get("/permisos/{id}/documentos",                        [PermisosController::class, "listarDocumentos"]);
    Route::post("/permisos/{id}/documentos",                       [PermisosController::class, "subirDocumento"]);
    Route::get("/permisos/{id}/documentos/{docId}/descargar",      [PermisosController::class, "descargarDocumento"]);
    Route::delete("/permisos/{id}/documentos/{docId}",             [PermisosController::class, "eliminarDocumento"]);

    // Cuadre de marcaciones
    Route::post("/cuadre/procesar",  [CuadreController::class, "procesar"]);
    Route::get("/cuadre/listado",    [CuadreController::class, "listado"]);

    // Reportes
    Route::get("/reportes/atrasos",               [ReportesController::class, "atrasos"]);
    Route::get("/reportes/marcaciones-faltantes", [ReportesController::class, "marcacionesFaltantes"]);

    // Períodos de planificación (TH admin)
    Route::get("/admin/periodos-planificacion",          [PeriodoPlanificacionController::class, "index"]);
    Route::get("/admin/periodos-planificacion/activo",   [PeriodoPlanificacionController::class, "activo"]);
    Route::post("/admin/periodos-planificacion",         [PeriodoPlanificacionController::class, "store"]);
    Route::put("/admin/periodos-planificacion/{id}",     [PeriodoPlanificacionController::class, "update"]);
    Route::delete("/admin/periodos-planificacion/{id}",  [PeriodoPlanificacionController::class, "destroy"]);

    // Planificación de vacaciones
    Route::get("/planificacion/mi-planificacion",        [PlanificacionVacController::class, "miPlanificacion"]);
    Route::post("/planificacion",                        [PlanificacionVacController::class, "store"]);
    Route::get("/planificacion",                         [PlanificacionVacController::class, "index"]);
    Route::patch("/planificacion/{id}/aprobar",          [PlanificacionVacController::class, "aprobar"]);
    Route::patch("/planificacion/{id}/negar",            [PlanificacionVacController::class, "negar"]);
    Route::delete("/planificacion/{id}",                 [PlanificacionVacController::class, "destroy"]);
    Route::patch("/planificacion/{id}/replanificar",     [PlanificacionVacController::class, "replanificar"]);

    // Reporte saldo de vacaciones (TH)
    Route::get("/reporte-vacaciones",                    [\App\Http\Controllers\ReporteVacacionesController::class, "index"]);
    Route::get("/reporte-vacaciones/pdf",               [\App\Http\Controllers\ReporteVacacionesController::class, "pdf"]);
    Route::post("/reporte-vacaciones/cargar-saldos",    [\App\Http\Controllers\ReporteVacacionesController::class, "cargarSaldos"]);
    Route::get("/reporte-vacaciones/{id_emp}",          [\App\Http\Controllers\ReporteVacacionesController::class, "detalle"]);

    // Reporte planificación de vacaciones (TH)
    Route::get("/reporte-planificacion/{anio}/estado",            [ReportePlanificacionController::class, "estado"]);
    Route::get("/reporte-planificacion/{anio}/pdf",               [ReportePlanificacionController::class, "generarPdf"]);
    Route::post("/reporte-planificacion/{anio}/subir-firmado",    [ReportePlanificacionController::class, "subirFirmado"]);
    Route::get("/reporte-planificacion/{anio}/descargar-firmado", [ReportePlanificacionController::class, "descargarFirmado"]);

    // Liquidación / comisión de vacaciones (solo TH)
    Route::get("/liquidacion/buscar",                         [LiquidacionVacController::class, "buscar"]);
    Route::get("/liquidacion/{id_emp}",                       [LiquidacionVacController::class, "consultar"]);
    Route::post("/liquidacion/{id_emp}/registrar",            [LiquidacionVacController::class, "registrar"]);
    Route::get("/liquidacion/certificado/{historico_id}",     [LiquidacionVacController::class, "generarCertificado"]);

    // Horas Extras
    Route::get("/horas-extras/calcular",                                         [HorasExtrasController::class, "calcular"]);
    Route::get("/horas-extras/mi-rol",                                           [HorasExtrasController::class, "miRol"]);
    Route::get("/horas-extras/mi-planificacion",                          [HorasExtrasController::class, "miPlanificacion"]);
    Route::post("/horas-extras/planificacion",                            [HorasExtrasController::class, "store"]);
    Route::put("/horas-extras/planificacion/{id}",                        [HorasExtrasController::class, "update"]);
    Route::delete("/horas-extras/planificacion/{id}",                     [HorasExtrasController::class, "destroy"]);
    Route::get("/horas-extras/planificacion",                             [HorasExtrasController::class, "index"]);
    Route::patch("/horas-extras/planificacion/{id}/autorizar",               [HorasExtrasController::class, "autorizar"]);
    Route::patch("/horas-extras/planificacion/{id}/aprobar",              [HorasExtrasController::class, "aprobar"]);
    Route::patch("/horas-extras/planificacion/{id}/negar",                [HorasExtrasController::class, "negar"]);
    Route::get("/horas-extras/planificacion/{id}/pdf",                    [HorasExtrasController::class, "pdf"]);
    Route::get("/horas-extras/planificacion/{id}/pdf-registros",          [HorasExtrasController::class, "pdfRegistros"]);
    Route::post("/horas-extras/planificacion/{id}/subir-firmado",         [HorasExtrasController::class, "subirFirmado"]);
    Route::get("/horas-extras/planificacion/{id}/descargar-firmado",      [HorasExtrasController::class, "descargarFirmado"]);
    Route::get("/horas-extras/mis-registros",                             [HorasExtrasController::class, "misHoras"]);
    Route::post("/horas-extras/registro",                                 [HorasExtrasController::class, "registrar"]);
    Route::get("/horas-extras/equipo-registros",                          [HorasExtrasController::class, "equipoHoras"]);
    Route::put("/horas-extras/registro/{id}",                             [HorasExtrasController::class, "actualizarRegistro"]);
    Route::patch("/horas-extras/registro/{id}/revisar",                   [HorasExtrasController::class, "revisarRegistro"]);
    Route::patch("/horas-extras/registro/{id}/confirmar",                 [HorasExtrasController::class, "confirmar"]);
    Route::patch("/horas-extras/registro/{id}/negar",                     [HorasExtrasController::class, "negarRegistro"]);

    // ── Nómina ────────────────────────────────────────────────────────────────
    Route::prefix('nomina')->group(function () {
        Route::get('auditoria',                   [NominaController::class, 'auditoria']);
        Route::get('sbu',                         [NominaController::class, 'sbuIndex']);
        Route::post('sbu',                        [NominaController::class, 'sbuStore']);

        Route::get('decimo-tercero',              [NominaController::class, 'index13']);
        Route::post('decimo-tercero/calcular',    [NominaController::class, 'calcular13']);
        Route::post('decimo-tercero/cerrar',      [NominaController::class, 'cerrar13']);
        Route::get('decimo-tercero/pdf',          [NominaController::class, 'pdf13']);

        Route::get('decimo-cuarto',               [NominaController::class, 'index14']);
        Route::post('decimo-cuarto/calcular',     [NominaController::class, 'calcular14']);
        Route::post('decimo-cuarto/cerrar',       [NominaController::class, 'cerrar14']);
        Route::get('decimo-cuarto/pdf',           [NominaController::class, 'pdf14']);

        Route::get('consolidado',                 [NominaController::class, 'consolidado']);
        Route::get('consolidado/pdf',             [NominaController::class, 'pdfConsolidado']);

        Route::get('fondos-reserva',              [NominaController::class, 'indexFR']);
        Route::post('fondos-reserva/calcular',    [NominaController::class, 'calcularFR']);
        Route::post('fondos-reserva/cerrar',      [NominaController::class, 'cerrarFR']);
        Route::get('fondos-reserva/pdf',          [NominaController::class, 'pdfFR']);

        Route::get('rol-pago',                    [RolPagoController::class, 'index']);
        Route::post('rol-pago/calcular',          [RolPagoController::class, 'calcular']);
        Route::put('rol-pago/detalle/{id}',       [RolPagoController::class, 'updateDetalle']);
        Route::post('rol-pago/cerrar',            [RolPagoController::class, 'cerrar']);
        Route::post('rol-pago/importar',          [RolPagoController::class, 'importar']);
        Route::get('rol-pago/pdf',                [RolPagoController::class, 'pdf']);
        Route::get('rol-pago/{cabId}/resumenes',     [RolPagoController::class, 'resumenes']);
        Route::get('rol-pago/{cabId}/resumenes/pdf', [RolPagoController::class, 'pdfResumenes']);
    });

    // ── Transportes ───────────────────────────────────────────────────────────
    Route::prefix('transporte')->group(function () {
        Route::get('vehiculos',              [TransporteController::class, 'index']);
        Route::post('vehiculos',             [TransporteController::class, 'store']);
        Route::put('vehiculos/{id}',         [TransporteController::class, 'update']);
        Route::get('conductores',            [TransporteController::class, 'conductores']);

        Route::get('mantenimiento',          [TransporteController::class, 'indexMtto']);
        Route::post('mantenimiento',         [TransporteController::class, 'storeMtto']);
        Route::put('mantenimiento/{id}',     [TransporteController::class, 'updateMtto']);
        Route::get('mantenimiento/{id}/pdf', [TransporteController::class, 'pdfMtto']);

        Route::get('movilizacion',                    [TransporteController::class, 'indexMov']);
        Route::post('movilizacion',                   [TransporteController::class, 'storeMov']);
        Route::put('movilizacion/{id}',               [TransporteController::class, 'updateMov']);
        Route::get('movilizacion/{id}/pdf',           [TransporteController::class, 'pdfMov']);
        Route::get('notificaciones-pendientes',       [TransporteController::class, 'notificacionesPendientes']);
    });

    // ── Adquisiciones ─────────────────────────────────────────────────────────
    Route::prefix('adquisiciones')->group(function () {
        Route::get('dashboard',                             [AdqDashboardController::class, 'index']);

        // Proveedores
        Route::get('proveedores',                           [ProveedorController::class, 'index']);
        Route::post('proveedores',                          [ProveedorController::class, 'store']);
        Route::get('proveedores/{id}',                      [ProveedorController::class, 'show']);
        Route::put('proveedores/{id}',                      [ProveedorController::class, 'update']);
        Route::patch('proveedores/{id}/inactivar',          [ProveedorController::class, 'inactivar']);
        Route::patch('proveedores/{id}/activar',            [ProveedorController::class, 'activar']);

        // Catálogo Inventario MF
        Route::get('catalogo-inventario',                   [\App\Http\Controllers\Adquisiciones\CatalogoInventarioController::class, 'index']);
        Route::get('catalogo-inventario/nivel1s',           [\App\Http\Controllers\Adquisiciones\CatalogoInventarioController::class, 'nivel1s']);
        Route::post('catalogo-inventario',                  [\App\Http\Controllers\Adquisiciones\CatalogoInventarioController::class, 'store']);
        Route::put('catalogo-inventario/{nivel2}',          [\App\Http\Controllers\Adquisiciones\CatalogoInventarioController::class, 'update']);
        Route::delete('catalogo-inventario/{nivel2}',       [\App\Http\Controllers\Adquisiciones\CatalogoInventarioController::class, 'destroy']);

        // Artículos / Inventario
        Route::get('articulos',                             [ArticuloController::class, 'index']);
        Route::post('articulos',                            [ArticuloController::class, 'store']);
        Route::get('articulos/alertas',                     [ArticuloController::class, 'alertas']);
        Route::get('articulos/catalogo',                    [ArticuloController::class, 'buscarCatalogo']);
        Route::get('articulos/{id}',                        [ArticuloController::class, 'show']);
        Route::put('articulos/{id}',                        [ArticuloController::class, 'update']);
        Route::patch('articulos/{id}/inactivar',            [ArticuloController::class, 'inactivar']);
        Route::post('articulos/{id}/imagen',                [ArticuloController::class, 'subirImagen']);
        Route::get('configuracion',                         [ArticuloController::class, 'configuracion']);
        Route::put('configuracion',                         [ArticuloController::class, 'actualizarConfiguracion']);

        // Unidades de medida
        Route::get('unidades-medida',                       [\App\Http\Controllers\Adquisiciones\UnidadMedidaController::class, 'index']);
        Route::post('unidades-medida',                      [\App\Http\Controllers\Adquisiciones\UnidadMedidaController::class, 'store']);
        Route::put('unidades-medida/{id}',                  [\App\Http\Controllers\Adquisiciones\UnidadMedidaController::class, 'update']);
        Route::patch('unidades-medida/{id}/toggle',         [\App\Http\Controllers\Adquisiciones\UnidadMedidaController::class, 'toggle']);

        // IVA
        Route::get('iva',                                   [\App\Http\Controllers\Adquisiciones\IvaController::class, 'index']);
        Route::post('iva',                                  [\App\Http\Controllers\Adquisiciones\IvaController::class, 'store']);
        Route::put('iva/{id}',                              [\App\Http\Controllers\Adquisiciones\IvaController::class, 'update']);
        Route::patch('iva/{id}/toggle',                     [\App\Http\Controllers\Adquisiciones\IvaController::class, 'toggle']);

        // Procesos de contratación
        Route::get('procesos-contratacion',                 [\App\Http\Controllers\Adquisiciones\ProcesoContratacionController::class, 'index']);
        Route::get('procesos-contratacion/activos',         [\App\Http\Controllers\Adquisiciones\ProcesoContratacionController::class, 'activos']);
        Route::post('procesos-contratacion',                [\App\Http\Controllers\Adquisiciones\ProcesoContratacionController::class, 'store']);
        Route::put('procesos-contratacion/{id}',            [\App\Http\Controllers\Adquisiciones\ProcesoContratacionController::class, 'update']);
        Route::patch('procesos-contratacion/{id}/toggle',   [\App\Http\Controllers\Adquisiciones\ProcesoContratacionController::class, 'toggle']);

        // Ingresos de Bienes (antes órdenes de compra)
        Route::get('ordenes',                               [OrdenCompraController::class, 'index']);
        Route::post('ordenes',                              [OrdenCompraController::class, 'store']);
        Route::get('ordenes/{id}',                          [OrdenCompraController::class, 'show']);
        Route::put('ordenes/{id}',                          [OrdenCompraController::class, 'update']);
        Route::patch('ordenes/{id}/confirmar',              [OrdenCompraController::class, 'confirmar']);
        Route::patch('ordenes/{id}/confirmar-con-egreso',   [OrdenCompraController::class, 'confirmarConEgreso']);
        Route::patch('ordenes/{id}/reversar',               [OrdenCompraController::class, 'reversar']);
        Route::get('ordenes/{id}/pdf',                      [OrdenCompraController::class, 'pdf']);
        Route::delete('ordenes/{id}',                       [OrdenCompraController::class, 'destroy']);

        // Egresos de bienes
        Route::get('egresos',                               [\App\Http\Controllers\Adquisiciones\EgresoController::class, 'index']);
        Route::post('egresos',                              [\App\Http\Controllers\Adquisiciones\EgresoController::class, 'store']);
        Route::get('egresos/{id}',                          [\App\Http\Controllers\Adquisiciones\EgresoController::class, 'show']);
        Route::put('egresos/{id}',                          [\App\Http\Controllers\Adquisiciones\EgresoController::class, 'update']);
        Route::patch('egresos/{id}/confirmar',              [\App\Http\Controllers\Adquisiciones\EgresoController::class, 'confirmar']);
        Route::patch('egresos/{id}/reversar',               [\App\Http\Controllers\Adquisiciones\EgresoController::class, 'reversar']);
        Route::get('egresos/{id}/pdf',                      [\App\Http\Controllers\Adquisiciones\EgresoController::class, 'pdf']);
        Route::delete('egresos/{id}',                       [\App\Http\Controllers\Adquisiciones\EgresoController::class, 'destroy']);
        Route::get('departamentos-activos',                 [\App\Http\Controllers\Adquisiciones\EgresoController::class, 'departamentos']);
        Route::get('empleados-activos',                     [\App\Http\Controllers\Adquisiciones\EgresoController::class, 'empleadosPorDepto']);

        // Ajuste de inventario
        Route::get('ajustes',                               [AjusteController::class, 'index']);
        Route::post('ajustes',                              [AjusteController::class, 'store']);
        Route::post('ajustes/importar-stock',               [AjusteController::class, 'importarStock']);

        // Reportes
        Route::get('reportes/kardex',                       [ReporteAdqController::class, 'kardex']);
        Route::get('reportes/libro-compras',                [ReporteAdqController::class, 'libroCompras']);
        Route::get('reportes/egresos-valorizados',          [ReporteAdqController::class, 'egresosValorizados']);
        Route::get('reportes/inventario-mensual',           [ReporteAdqController::class, 'inventarioMensual']);
        Route::get('reportes/articulos',                    [ReporteAdqController::class, 'articulosBuscar']);
        Route::get('reportes/analitica',                    [ReporteAdqController::class, 'analitica']);

        // Solicitudes de materiales
        Route::get('solicitudes',                           [SolicitudMaterialController::class, 'index']);
        Route::post('solicitudes',                          [SolicitudMaterialController::class, 'store']);
        Route::get('solicitudes/{id}',                      [SolicitudMaterialController::class, 'show']);
        Route::patch('solicitudes/{id}/aprobar',            [SolicitudMaterialController::class, 'aprobar']);
        Route::patch('solicitudes/{id}/negar',              [SolicitudMaterialController::class, 'negar']);
        Route::patch('solicitudes/{id}/despachar',          [SolicitudMaterialController::class, 'despachar']);
        Route::delete('solicitudes/{id}',                   [SolicitudMaterialController::class, 'destroy']);
    });

    // Vacaciones
    Route::get("/vacaciones/mi-rol",            [VacacionesController::class, "miRol"]);
    Route::get("/vacaciones/mi-saldo",          [VacacionesController::class, "miSaldo"]);
    Route::get("/vacaciones",                   [VacacionesController::class, "index"]);
    Route::post("/vacaciones",                  [VacacionesController::class, "store"]);
    Route::get("/vacaciones/{id}/empleados-depto", [VacacionesController::class, "empleadosDepto"]);
    Route::patch("/vacaciones/{id}/aprobar",    [VacacionesController::class, "aprobar"]);
    Route::patch("/vacaciones/{id}/negar",      [VacacionesController::class, "negar"]);
    Route::delete("/vacaciones/{id}",           [VacacionesController::class, "destroy"]);

    // Transporte — catálogos
    Route::prefix('transporte')->group(function () {
        // Vehículos
        Route::get('vehiculos',    [TransporteController::class, 'index']);
        Route::post('vehiculos',   [TransporteController::class, 'store']);
        Route::put('vehiculos/{id}', [TransporteController::class, 'update']);

        // Talleres
        Route::get('talleres',        [\App\Http\Controllers\Transporte\TallerController::class, 'index']);
        Route::get('talleres/activos', [\App\Http\Controllers\Transporte\TallerController::class, 'activos']);
        Route::post('talleres',       [\App\Http\Controllers\Transporte\TallerController::class, 'store']);
        Route::put('talleres/{id}',   [\App\Http\Controllers\Transporte\TallerController::class, 'update']);

        // Tipos de mantenimiento
        Route::get('tipos-mantenimiento',        [\App\Http\Controllers\Transporte\TipoMantenimientoController::class, 'index']);
        Route::get('tipos-mantenimiento/activos', [\App\Http\Controllers\Transporte\TipoMantenimientoController::class, 'activos']);
        Route::post('tipos-mantenimiento',       [\App\Http\Controllers\Transporte\TipoMantenimientoController::class, 'store']);
        Route::put('tipos-mantenimiento/{id}',   [\App\Http\Controllers\Transporte\TipoMantenimientoController::class, 'update']);

        // Plan preventivo
        Route::get('plan-preventivo',               [\App\Http\Controllers\Transporte\PlanPreventivoController::class, 'index']);
        Route::post('plan-preventivo',              [\App\Http\Controllers\Transporte\PlanPreventivoController::class, 'store']);
        Route::put('plan-preventivo/{id}',          [\App\Http\Controllers\Transporte\PlanPreventivoController::class, 'update']);
        Route::post('plan-preventivo/importar-csv', [\App\Http\Controllers\Transporte\PlanPreventivoController::class, 'importarCsv']);

        // Vales de combustible
        Route::get('vales-combustible',               [\App\Http\Controllers\Transporte\ValeController::class, 'index']);
        Route::post('vales-combustible',              [\App\Http\Controllers\Transporte\ValeController::class, 'store']);
        Route::get('vales-combustible/{id}/pdf',      [\App\Http\Controllers\Transporte\ValeController::class, 'pdf']);
        Route::patch('vales-combustible/{id}/anular', [\App\Http\Controllers\Transporte\ValeController::class, 'anular']);

        // Mantenimiento
        Route::get('mantenimiento',        [TransporteController::class, 'indexMtto']);
        Route::post('mantenimiento',       [TransporteController::class, 'storeMtto']);
        Route::put('mantenimiento/{id}',   [TransporteController::class, 'updateMtto']);
        Route::get('mantenimiento/{id}/pdf', [TransporteController::class, 'pdfMtto']);

        // Movilización
        Route::get('movilizacion',         [TransporteController::class, 'indexMov']);
        Route::post('movilizacion',        [TransporteController::class, 'storeMov']);
        Route::put('movilizacion/{id}',    [TransporteController::class, 'updateMov']);
        Route::get('movilizacion/{id}/pdf', [TransporteController::class, 'pdfMov']);

        Route::get('conductores', [TransporteController::class, 'conductores']);
    });
});
