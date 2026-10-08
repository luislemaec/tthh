// Cada operación tiene un cierre idempotente: las peticiones concurrentes no se
// dan por terminadas cuando finaliza solamente la primera.
export function crearContadorOperaciones(notificar = () => {}) {
  let pendientes = 0
  return {
    iniciar() {
      pendientes += 1
      notificar(pendientes)
      let finalizada = false
      return () => {
        if (finalizada) return
        finalizada = true
        pendientes -= 1
        notificar(pendientes)
      }
    },
    get pendientes() { return pendientes },
  }
}

export function segundosRestantes(fecha, ahora = Date.now()) {
  const fin = Date.parse(fecha)
  return Number.isFinite(fin) ? Math.max(0, Math.ceil((fin - ahora) / 1000)) : null
}
