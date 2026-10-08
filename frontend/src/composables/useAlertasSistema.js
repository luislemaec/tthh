import { computed, ref, watch } from 'vue'
import api from '@/services/api'

export function useAlertasSistema(auth, router) {
  const alertasStock = ref(0)
  const puedeVerStock = computed(() => auth.opcionesMenu.some(item => item.url === 'adquisiciones/articulos'))

  watch(puedeVerStock, async (permitido, _anterior, onCleanup) => {
    const controller = new AbortController()
    onCleanup(() => controller.abort())
    alertasStock.value = 0
    if (!permitido) return
    try {
      const { data } = await api.get('/adquisiciones/articulos/alertas', { signal: controller.signal })
      if (!controller.signal.aborted) alertasStock.value = data.length
    } catch {}
  }, { immediate: true })

  const puedeNotificarTransporte = computed(() =>
    auth.roles.includes('TRANSPORTE') && auth.opcionesMenu.some(item => item.url.startsWith('transporte/'))
  )
  watch(puedeNotificarTransporte, (permitido, _anterior, onCleanup) => {
    if (!permitido) return
    let referenciaInicial = false
    let ultimaAt = null
    let consultando = false
    const controller = new AbortController()

    async function verificarPendientes() {
      if (consultando || controller.signal.aborted) return
      consultando = true
      try {
        const { data } = await api.get('/transporte/notificaciones-pendientes', { signal: controller.signal, sitBackground: true })
        if (controller.signal.aborted) return
        if (!referenciaInicial || !data.pendientes) {
          referenciaInicial = true
          ultimaAt = data.ultima_at
          return
        }
        if (!data.ultima_at || data.ultima_at === ultimaAt) return
        ultimaAt = data.ultima_at
        if (!('Notification' in window) || Notification.permission !== 'granted') return
        const destino = data.items?.[0]?.lugar_destino
        const notif = new Notification('Pedido de Vehículo', {
          body: data.pendientes === 1 ? `Nueva solicitud de movilización pendiente.${destino ? ' Destino: ' + destino : ''}` : `${data.pendientes} solicitudes de movilización pendientes.`,
          icon: '/favicon.ico', tag: 'movilizacion-pendiente',
        })
        notif.onclick = () => { window.focus(); router.push('/transporte/movilizacion'); notif.close() }
      } catch {} finally { consultando = false }
    }

    // Se consulta también fuera de Transporte, sin pedir permiso del navegador al login.
    // El permiso se solicita al abrir la sección, como en el layout anterior.
    const stopRoute = watch(() => router.currentRoute.value.meta.modulo, modulo => {
      if (modulo === 'TRANS' && 'Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission().catch(() => {})
      }
    }, { immediate: true })
    verificarPendientes()
    const intervalo = setInterval(verificarPendientes, 30000)
    onCleanup(() => { controller.abort(); clearInterval(intervalo); stopRoute() })
  }, { immediate: true })

  return { alertasStock }
}
