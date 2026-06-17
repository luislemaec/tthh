import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/LoginView.vue'),
    meta: { guest: true },
  },
  {
    path: '/launcher',
    name: 'Launcher',
    component: () => import('@/views/LauncherView.vue'),
    meta: { requiresAuth: true },
  },
  {
    path: '/',
    component: () => import('@/layouts/MainLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', redirect: '/dashboard' },
      { path: 'dashboard', name: 'Dashboard', component: () => import('@/views/DashboardView.vue') },
      { path: 'perfil', name: 'Perfil', component: () => import('@/views/PerfilView.vue') },
      { path: 'empleados', name: 'Empleados', component: () => import('@/views/empleados/EmpleadosIndex.vue') },
      { path: 'empleados/crear', name: 'EmpleadoCrear', component: () => import('@/views/empleados/EmpleadoForm.vue') },
      { path: 'empleados/reporte', name: 'ReporteEmpleados', component: () => import('@/views/empleados/ReporteEmpleadosView.vue') },
      { path: 'empleados/:id', name: 'EmpleadoDetalle', component: () => import('@/views/empleados/EmpleadoDetalle.vue') },
      { path: 'empleados/:id/editar', name: 'EmpleadoEditar', component: () => import('@/views/empleados/EmpleadoForm.vue') },
      { path: 'empleados/importar', name: 'EmpleadoImportar', component: () => import('@/views/empleados/ImportacionView.vue') },
      { path: 'empleados/distributivo', name: 'EmpleadoDistributivo', component: () => import('@/views/empleados/DistributivoView.vue') },
      { path: 'asistencia', name: 'Asistencia', component: () => import('@/views/AsistenciaView.vue') },
      { path: 'asistencia/sin-atrasos', name: 'ReporteSinAtrasos', component: () => import('@/views/asistencia/ReporteSinAtrasosView.vue') },
      { path: 'permisos', name: 'Permisos', component: () => import('@/views/PermisosView.vue') },
      { path: 'vacaciones', name: 'Vacaciones', component: () => import('@/views/VacacionesView.vue') },
      { path: 'admin/departamentos', name: 'AdminDepartamentos', component: () => import('@/views/admin/departamentos/DepartamentosView.vue') },
      { path: 'admin/razones', name: 'AdminRazones', component: () => import('@/views/admin/razones/RazonesView.vue') },
      { path: 'admin/turnos', name: 'AdminTurnos', component: () => import('@/views/admin/turnos/TurnosView.vue') },
      { path: 'admin/roles', name: 'AdminRoles', component: () => import('@/views/admin/RolesView.vue') },
      { path: 'admin/opciones', name: 'AdminOpciones', component: () => import('@/views/admin/opciones/OpcionesView.vue') },
      { path: 'admin/jornadas', name: 'AdminJornadas', component: () => import('@/views/admin/jornadas/JornadasView.vue') },
      { path: 'admin/calendario', name: 'AdminCalendario', component: () => import('@/views/admin/calendario/CalendarioView.vue') },
      { path: 'admin/configuracion', name: 'AdminConfiguracion', component: () => import('@/views/admin/configuracion/ConfiguracionView.vue') },
      { path: 'admin/aportes-iess', name: 'AdminAportesIess', component: () => import('@/views/admin/aportes/AportesIessView.vue') },
      { path: 'admin/sbu', name: 'AdminSbu', component: () => import('@/views/admin/SbuView.vue') },
      { path: 'admin/avisos', name: 'AdminAvisos', component: () => import('@/views/admin/AvisosView.vue') },
      { path: 'admin/auditoria', name: 'AdminAuditoria', component: () => import('@/views/admin/AuditoriaView.vue') },
      { path: 'admin/zkteco', name: 'AdminZkteco', component: () => import('@/views/admin/ZktecoView.vue') },
      { path: 'admin/funcionarios-externos', name: 'AdminFuncionariosExternos', component: () => import('@/views/comisiones/FuncionariosExternosView.vue') },
      { path: 'admin/modalidades-laborales', name: 'AdminModalidadesLaborales', component: () => import('@/views/admin/ModalidadLaboralView.vue') },
      { path: 'acciones-personal', name: 'AccionesPersonal', component: () => import('@/views/acciones/AccionesPersonalView.vue') },
      { path: 'acciones-personal/nueva', name: 'AccionPersonalNueva', component: () => import('@/views/acciones/AccionPersonalForm.vue') },
      { path: 'admin/cuadre', name: 'AdminCuadre', component: () => import('@/views/admin/cuadre/CuadreView.vue') },
      { path: 'admin/periodos-planificacion', name: 'AdminPeriodosPlanificacion', component: () => import('@/views/admin/periodos/PeriodosView.vue') },
      { path: 'supervisores', name: 'Supervisores', component: () => import('@/views/supervisores/SupervisoresView.vue') },
      { path: 'reportes', name: 'Reportes', component: () => import('@/views/reportes/ReportesView.vue') },
      { path: 'planificacion', name: 'Planificacion', component: () => import('@/views/planificacion/PlanificacionesView.vue') },
      { path: 'planificacion/reporte', name: 'ReportePlanificacion', component: () => import('@/views/planificacion/ReportePlanificacionView.vue') },
      { path: 'planificacion/reporte-saldo', name: 'ReporteSaldoVac', component: () => import('@/views/planificacion/ReporteSaldoVacView.vue') },
      { path: 'planificacion/liquidacion', name: 'LiquidacionVacaciones', component: () => import('@/views/planificacion/LiquidacionVacView.vue') },
      { path: 'horas-extras', name: 'HorasExtras', component: () => import('@/views/horasextras/HorasExtrasView.vue') },
      { path: 'nomina/decimos',        name: 'NominaDecimos',       component: () => import('@/views/nomina/DecimosView.vue') },
      { path: 'nomina/fondos-reserva', name: 'NominaFondosReserva', component: () => import('@/views/nomina/FondosReservaView.vue') },
      { path: 'nomina/rol-pago',       name: 'NominaRolPago',       component: () => import('@/views/nomina/RolPagoView.vue') },
      { path: 'certificados-laborales', name: 'CertificadosLaborales', component: () => import('@/views/certificados/CertificadosView.vue') },
    ],
  },
  // ── Adquisiciones ──────────────────────────────────────────────────────────
  {
    path: '/adquisiciones',
    component: () => import('@/layouts/AdqLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', redirect: '/adquisiciones/dashboard' },
      { path: 'dashboard',   name: 'AdqDashboard',   component: () => import('@/views/adquisiciones/AdqDashboardView.vue') },
      { path: 'proveedores', name: 'AdqProveedores', component: () => import('@/views/adquisiciones/ProveedoresView.vue') },
      { path: 'articulos',   name: 'AdqArticulos',   component: () => import('@/views/adquisiciones/ArticulosView.vue') },
      { path: 'ingresos',    name: 'AdqIngresos',    component: () => import('@/views/adquisiciones/IngresosBienesView.vue') },
      { path: 'solicitudes', name: 'AdqSolicitudes', component: () => import('@/views/adquisiciones/SolicitudesView.vue') },
      { path: 'catalogo',    name: 'AdqCatalogo',    component: () => import('@/views/adquisiciones/CatalogoInventarioView.vue') },
      { path: 'iva',                  name: 'AdqIva',      component: () => import('@/views/adquisiciones/IvaView.vue') },
      { path: 'procesos-contratacion', name: 'AdqProcesos', component: () => import('@/views/adquisiciones/ProcesoContratacionView.vue') },
      { path: 'unidades-medida', name: 'AdqUnidadesMedida', component: () => import('@/views/adquisiciones/UnidadesMedidaView.vue') },
      { path: 'egresos',        name: 'AdqEgresos',        component: () => import('@/views/adquisiciones/EgresosBienesView.vue') },
      { path: 'ajustes',        name: 'AdqAjustes',        component: () => import('@/views/adquisiciones/AjusteInventarioView.vue') },
      { path: 'reportes/kardex',         name: 'AdqReporteKardex',      component: () => import('@/views/adquisiciones/ReporteKardexView.vue') },
      { path: 'reportes/libro-compras',  name: 'AdqReporteLibroCompras', component: () => import('@/views/adquisiciones/ReporteLibroComprasView.vue') },
      { path: 'reportes/egresos',           name: 'AdqReporteEgresos',          component: () => import('@/views/adquisiciones/ReporteEgresosView.vue') },
      { path: 'reportes/inventario-mensual', name: 'AdqReporteInventarioMensual', component: () => import('@/views/adquisiciones/ReporteInventarioMensualView.vue') },
    ],
  },
  // ── Transportes ────────────────────────────────────────────────────────────
  {
    path: '/transporte',
    component: () => import('@/layouts/TransporteLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', redirect: '/transporte/movilizacion' },
      { path: 'vehiculos',          name: 'TransVehiculos',          component: () => import('@/views/transporte/VehiculosView.vue') },
      { path: 'mantenimiento',      name: 'TransMantenimiento',      component: () => import('@/views/transporte/MantenimientoView.vue') },
      { path: 'movilizacion',       name: 'TransMovilizacion',       component: () => import('@/views/transporte/MovilizacionView.vue') },
      { path: 'talleres',            name: 'TransTalleres',           component: () => import('@/views/transporte/TalleresView.vue') },
      { path: 'tipos-mantenimiento', name: 'TransTiposMantenimiento', component: () => import('@/views/transporte/TiposMantenimientoView.vue') },
      { path: 'plan-preventivo',    name: 'TransPlanPreventivo',     component: () => import('@/views/transporte/PlanPreventivoView.vue') },
      { path: 'vales-combustible',  name: 'TransValesCombustible',   component: () => import('@/views/transporte/ValesCombustibleView.vue') },
    ],
  },
  {
    path: '/comisiones',
    component: () => import('@/layouts/ComisionesLayout.vue'),
    children: [
      { path: '',               redirect: '/comisiones/solicitudes' },
      { path: 'solicitudes',           name: 'ComSolicitudes',          component: () => import('@/views/comisiones/ComisionesView.vue') },
      { path: 'liquidaciones',         name: 'ComLiquidaciones',        component: () => import('@/views/comisiones/LiquidacionesView.vue') },
      { path: 'tarifas',               name: 'ComTarifas',              component: () => import('@/views/admin/TarifasViaticosView.vue') },
      { path: 'funcionarios-externos', name: 'ComFuncionariosExternos', component: () => import('@/views/comisiones/FuncionariosExternosView.vue') },
    ],
  },
  { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to, _from, next) => {
  const auth = useAuthStore()
  if (to.meta.requiresAuth && !auth.isAuthenticated) return next('/login')
  if (to.meta.guest && auth.isAuthenticated) return next('/launcher')
  if (to.meta.rol && !auth.roles.includes(to.meta.rol.toUpperCase())) return next('/')
  next()
})

export default router
