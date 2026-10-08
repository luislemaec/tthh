const ambiente = (import.meta.env.VITE_APP_AMBIENTE || '').trim().toLowerCase()
export const mostrarAmbiente = !!ambiente && !['produccion', 'production'].includes(ambiente)
export const etiquetaAmbiente = `AMBIENTE DE ${ambiente.toUpperCase()}`
