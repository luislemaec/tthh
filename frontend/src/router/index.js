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
    path: '/',
    component: () => import('@/layouts/MainLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', redirect: '/dashboard' },
      { path: 'dashboard', name: 'Dashboard', component: () => import('@/views/DashboardView.vue') },
      { path: 'perfil', name: 'Perfil', component: () => import('@/views/PerfilView.vue') },
      { path: 'empleados', name: 'Empleados', component: () => import('@/views/empleados/EmpleadosIndex.vue') },
      { path: 'empleados/crear', name: 'EmpleadoCrear', component: () => import('@/views/empleados/EmpleadoForm.vue') },
      { path: 'empleados/:id', name: 'EmpleadoDetalle', component: () => import('@/views/empleados/EmpleadoDetalle.vue') },
      { path: 'empleados/:id/editar', name: 'EmpleadoEditar', component: () => import('@/views/empleados/EmpleadoForm.vue') },
      { path: 'empleados/importar', name: 'EmpleadoImportar', component: () => import('@/views/empleados/ImportacionView.vue') },
      { path: 'asistencia', name: 'Asistencia', component: () => import('@/views/AsistenciaView.vue') },
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
      { path: 'admin/cuadre', name: 'AdminCuadre', component: () => import('@/views/admin/cuadre/CuadreView.vue') },
      { path: 'admin/periodos-planificacion', name: 'AdminPeriodosPlanificacion', component: () => import('@/views/admin/periodos/PeriodosView.vue') },
      { path: 'supervisores', name: 'Supervisores', component: () => import('@/views/supervisores/SupervisoresView.vue') },
      { path: 'reportes', name: 'Reportes', component: () => import('@/views/reportes/ReportesView.vue') },
      { path: 'planificacion', name: 'Planificacion', component: () => import('@/views/planificacion/PlanificacionesView.vue') },
      { path: 'planificacion/reporte', name: 'ReportePlanificacion', component: () => import('@/views/planificacion/ReportePlanificacionView.vue') },
      { path: 'planificacion/liquidacion', name: 'LiquidacionVacaciones', component: () => import('@/views/planificacion/LiquidacionVacView.vue') },
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
  if (to.meta.guest && auth.isAuthenticated) return next('/')
  if (to.meta.rol && !auth.roles.includes(to.meta.rol.toUpperCase())) return next('/')
  next()
})

export default router
