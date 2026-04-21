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
use App\Http\Controllers\Adquisiciones\AdqDashboardController;
use App\Http\Controllers\Adquisiciones\ProveedorController;
use App\Http\Controllers\Adquisiciones\ArticuloController;
use App\Http\Controllers\Adquisiciones\OrdenCompraController;
use App\Http\Controllers\Adquisiciones\SolicitudMaterialController;
use Illuminate\Support\Facades\Route;

// Rutas PÚBLICAS
Route::post("/login", [AuthController::class, "login"])->name("login");

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
        Route::apiResource("razones",       \App\Http\Controllers\Admin\RazonController::class);
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

        // Aportes IESS
        Route::get("aportes-iess",          [\App\Http\Controllers\Admin\AportesIessController::class, "index"]);
        Route::get("aportes-iess/vigentes", [\App\Http\Controllers\Admin\AportesIessController::class, "vigentes"]);
        Route::post("aportes-iess",         [\App\Http\Controllers\Admin\AportesIessController::class, "store"]);
    });

    // Opciones de menú
    Route::get("/opciones",             [RolController::class, "opciones"]);
    Route::post("/roles/{id}/opciones", [RolController::class, "asignarOpciones"]);

    // Dashboard
    Route::get("/dashboard", [DashboardController::class, "index"]);

    // Departamentos
    Route::get("/departamentos", [EmpleadoController::class, "departamentos"]);
    Route::get("/empleados/partidas-vacantes", [EmpleadoController::class, "partidasVacantes"]);

    // Empleados
    Route::get("/empleados",         [EmpleadoController::class, "index"]);
    Route::post("/empleados",        [EmpleadoController::class, "store"]);
    Route::get("/empleados/{id}",    [EmpleadoController::class, "show"]);
    Route::put("/empleados/{id}",    [EmpleadoController::class, "update"]);
    Route::delete("/empleados/{id}", [EmpleadoController::class, "destroy"]);

    Route::post("/empleados/importar-distributivo", [EmpleadoController::class, "importarDistributivo"]);
    Route::post("/empleados/{id}/reset-password",  [EmpleadoController::class, "resetPassword"]);
    Route::post("/cambiar-password",               [EmpleadoController::class, "cambiarPassword"]);

    // Asignación de roles a empleados
    Route::get("/empleados/{id_emp}/roles",             [RolController::class, "rolesEmpleado"]);
    Route::post("/empleados/{id_emp}/roles",            [RolController::class, "asignarRolEmpleado"]);
    Route::delete("/empleados/{id_emp}/roles/{id_rol}", [RolController::class, "quitarRolEmpleado"]);

    // Acciones de Personal
    Route::get("/acciones-personal",                    [\App\Http\Controllers\AccionPersonalController::class, "index"]);
    Route::post("/acciones-personal",                   [\App\Http\Controllers\AccionPersonalController::class, "store"]);
    Route::get("/acciones-personal/{id}",               [\App\Http\Controllers\AccionPersonalController::class, "show"]);
    Route::patch("/acciones-personal/{id}/estado",      [\App\Http\Controllers\AccionPersonalController::class, "cambiarEstado"]);
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
    Route::get("/asistencia/listado",     [AsistenciaController::class, "listado"]);
    Route::get("/asistencia/reporte",     [AsistenciaController::class, "reporte"]);
    Route::get("/asistencia/mi-reporte",  [AsistenciaController::class, "miReporte"]);

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
    Route::get("/permisos/{id}",         [PermisosController::class, "show"]);
    Route::patch("/permisos/{id}/aprobar", [PermisosController::class, "aprobar"]);
    Route::patch("/permisos/{id}/negar",   [PermisosController::class, "negar"]);
    Route::delete("/permisos/{id}",          [PermisosController::class, "destroy"]);

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
    Route::post("/horas-extras/planificacion/{id}/subir-firmado",         [HorasExtrasController::class, "subirFirmado"]);
    Route::get("/horas-extras/planificacion/{id}/descargar-firmado",      [HorasExtrasController::class, "descargarFirmado"]);
    Route::get("/horas-extras/mis-registros",                             [HorasExtrasController::class, "misHoras"]);
    Route::post("/horas-extras/registro",                                 [HorasExtrasController::class, "registrar"]);
    Route::get("/horas-extras/equipo-registros",                          [HorasExtrasController::class, "equipoHoras"]);
    Route::put("/horas-extras/registro/{id}",                             [HorasExtrasController::class, "actualizarRegistro"]);
    Route::patch("/horas-extras/registro/{id}/revisar",                   [HorasExtrasController::class, "revisarRegistro"]);
    Route::patch("/horas-extras/registro/{id}/confirmar",                 [HorasExtrasController::class, "confirmar"]);
    Route::patch("/horas-extras/registro/{id}/negar",                     [HorasExtrasController::class, "negarRegistro"]);

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

        // Artículos / Inventario
        Route::get('articulos',                             [ArticuloController::class, 'index']);
        Route::post('articulos',                            [ArticuloController::class, 'store']);
        Route::get('articulos/alertas',                     [ArticuloController::class, 'alertas']);
        Route::get('articulos/catalogo',                    [ArticuloController::class, 'buscarCatalogo']);
        Route::get('articulos/{id}',                        [ArticuloController::class, 'show']);
        Route::put('articulos/{id}',                        [ArticuloController::class, 'update']);
        Route::patch('articulos/{id}/inactivar',            [ArticuloController::class, 'inactivar']);
        Route::get('configuracion',                         [ArticuloController::class, 'configuracion']);
        Route::put('configuracion',                         [ArticuloController::class, 'actualizarConfiguracion']);

        // Órdenes de compra
        Route::get('ordenes',                               [OrdenCompraController::class, 'index']);
        Route::post('ordenes',                              [OrdenCompraController::class, 'store']);
        Route::get('ordenes/{id}',                          [OrdenCompraController::class, 'show']);
        Route::patch('ordenes/{id}/enviar',                 [OrdenCompraController::class, 'enviar']);
        Route::patch('ordenes/{id}/recibir',                [OrdenCompraController::class, 'recibir']);
        Route::delete('ordenes/{id}',                       [OrdenCompraController::class, 'destroy']);

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
    Route::patch("/vacaciones/{id}/aprobar",    [VacacionesController::class, "aprobar"]);
    Route::patch("/vacaciones/{id}/negar",      [VacacionesController::class, "negar"]);
    Route::delete("/vacaciones/{id}",           [VacacionesController::class, "destroy"]);
});
