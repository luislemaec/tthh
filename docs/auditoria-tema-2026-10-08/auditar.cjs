// Inventario estático reproducible. Solo lee código; escribe resultados de auditoría.
const fs = require('node:fs')
const path = require('node:path')
const root = path.resolve(__dirname, '../..')
const out = __dirname
const roots = ['frontend/src', 'frontend/public', 'backend/resources', 'backend/app', 'backend/routes']
const excluded = new Set(['node_modules', 'vendor', 'dist', '.git'])
const extensions = /\.(vue|css|scss|sass|js|ts|html|xhtml|php|svg)$/i
const files = []
function walk(dir) {
  if (!fs.existsSync(dir)) return
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const p = path.join(dir, entry.name)
    if (entry.isDirectory() && !excluded.has(entry.name)) walk(p)
    else if (entry.isFile() && extensions.test(entry.name)) files.push(p)
  }
}
roots.forEach(p => walk(path.join(root, p)))
files.push(path.join(root, 'frontend/index.html'))
const relative = p => path.relative(root, p).replaceAll('\\', '/')
const texts = new Map(files.map(p => [relative(p), fs.readFileSync(p, 'utf8')]))
const active = new Set()
function resolveImport(source, target) {
  let p = target.startsWith('@/') ? 'frontend/src/' + target.slice(2) : target.startsWith('.') ? path.posix.join(path.posix.dirname(source), target) : null
  if (!p) return null
  for (const candidate of [p, p + '.js', p + '.vue', p + '.css', p + '/index.js']) if (texts.has(candidate)) return candidate
  return null
}
function visit(file) {
  if (active.has(file)) return
  active.add(file)
  const text = texts.get(file) || ''
  for (const match of text.matchAll(/(?:\bfrom\s*|\bimport\s*\(\s*|\bimport\s+|@import\s+)(['"])([^'"]+)\1/g)) {
    const imported = resolveImport(file, match[2]); if (imported) visit(imported)
  }
}
visit('frontend/src/main.js')
active.add('frontend/index.html')
function scope(file) {
  if (file.startsWith('backend/resources/views/reportes/')) return 'Documento/PDF'
  if (file.endsWith('.svg')) return 'Recurso SVG'
  if (active.has(file)) return 'Frontend activo'
  if (file.startsWith('frontend/')) return 'Frontend sin importación detectada'
  if (file.endsWith('welcome.blade.php')) return 'Página Laravel inicial'
  if (file.startsWith('backend/resources/')) return 'Recursos backend'
  return 'Código backend'
}
const rows = [], metrics = [], variables = [], rangesByFile = new Map()
const colorPrefix = '(?:bg|text|border(?:-[xytrblse])?|ring(?:-offset)?|outline|divide|placeholder|accent|decoration|caret|fill|stroke|from|via|to|shadow)'
const palette = '(?:slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose)'
const tw = new RegExp('(?<![\\w-])(?:[\\w-]+:)*!?' + colorPrefix + '-(?:white|black|' + palette + '-\\d{2,3})(?:/\\d+)?(?![\\w-])', 'g')
const arbitrary = new RegExp('(?<![\\w-])(?:[\\w-]+:)*' + colorPrefix + '-\\[(?:#[^\\]]+|rgba?\\([^\\]]+|hsla?\\([^\\]]+|oklch\\([^\\]]+|var\\([^\\]]+)\\](?:/\\d+)?', 'g')
function proposal(kind, value, line) {
  if (kind === 'Variable') return value
  if (/--sit-[\w-]+\s*:/.test(line)) return 'Definición de token: conservar valor inicial'
  if (/backgroundColor|borderColor|new Chart|fillStyle|strokeStyle/.test(line)) return 'Paleta de gráficos: analizar independencia del tema'
  if (/focus:|focus-within:|ring-|outline-/.test(value)) return '--sit-field-focus / --sit-primary-focus'
  if (/red|#dc2626|#ef4444|#d32f2f/i.test(value)) return '--sit-danger / -soft / -foreground según contexto'
  if (/amber|yellow|orange/.test(value)) return '--sit-warn / -soft / -foreground según contexto'
  if (/green|emerald/.test(value)) return 'Distinguir acción principal heredada de --sit-success'
  if (/purple|violet|indigo|pink|fuchsia|rose|cyan|sky/.test(value)) return 'Estado o categoría: requiere revisión semántica'
  if (/^border|:border|divide/.test(value)) return '--sit-border / --sit-field-border'
  if (/^text|:text/.test(value)) return '--sit-text / --sit-text-muted / --sit-text-strong'
  if (/white|gray|slate|zinc|#fff\b|#ffffff\b|#f[0-9a-f]{5}/i.test(value)) return '--sit-surface / --sit-ground / --sit-hover según contexto'
  if (kind === 'Inline') return 'Revisar contenido: dimensiones pueden permanecer dinámicas'
  return 'Clasificar primario heredado / estado / superficie antes de convertir'
}
for (const [file, original] of texts) {
  const text = original.replace(/<!--[\s\S]*?-->|\/\*[\s\S]*?\*\/|^\s*\/\/[^\r\n]*/gm, m => m.replace(/[^\r\n]/g, ' '))
  const area = scope(file), lines = original.split(/\r?\n/)
  const row = { archivo:file,ambito:area,hex:0,rgb:0,hsl:0,oklch:0,nombres:0,tailwindFisico:0,tailwindArbitrario:0,variables:0,inline:0,styleBlocks:0,tables:0,forms:0,overlays:0 }
  const ranges = []
  function collect(kind, regex, metric) {
    for (const m of text.matchAll(regex)) {
      const line = text.slice(0, m.index).split('\n').length
      const context = (lines[line - 1] || '').trim().slice(0, 300)
      let classification = kind === 'Variable' || /var\(--/.test(m[0]) ? 'Tokenizado' : kind.startsWith('Tailwind') ? 'Hardcoded Tailwind' : 'Hardcoded CSS'
      let action = 'Debe convertirse a variable'
      if (kind === 'Variable' || /--sit-[\w-]+\s*:/.test(context)) { classification = 'Tokenizado'; action = 'Conservar: token o definición central' }
      else if (kind === 'Nombre de color' && /currentColor|inherit/i.test(m[0])) { classification = 'Tokenizado'; action = 'Conservar herencia: depende del color del padre' }
      else if (kind === 'Nombre de color' && /transparent/i.test(m[0])) { classification = 'Excepción justificada'; action = 'Conservar transparencia' }
      else if (area === 'Documento/PDF' || area === 'Recurso SVG') { classification = 'Excepción justificada'; action = 'Debe permanecer fijo: documento/recurso independiente del tema de pantalla' }
      else if (area !== 'Frontend activo' || kind === 'Inline' || /backgroundColor|borderColor/.test(context)) { classification = 'Requiere análisis'; action = 'Requiere análisis del contexto antes de modificar' }
      if (kind === 'Tailwind físico' && /purple|violet|indigo|pink|cyan|sky/.test(m[0])) action = 'Requiere análisis: estado, categoría o decoración'
      rows.push({archivo:file,linea:line,posicion:m.index,ambito:area,tipo:kind,valor:m[0],clasificacion:classification,variablePropuesta:proposal(kind,m[0],context),impacto:area === 'Frontend activo' ? 'Interfaz de pantalla' : area,accion:action,contexto:context})
      row[metric]++
      if (kind.startsWith('Tailwind')) ranges.push([m.index,m.index+m[0].length])
    }
  }
  collect('Tailwind físico', tw, 'tailwindFisico')
  collect('Tailwind arbitrario', arbitrary, 'tailwindArbitrario')
  collect('HEX', /#[\da-fA-F]{3,8}\b/g, 'hex')
  collect('RGB/RGBA', /rgba?\([^)]*\)/g, 'rgb')
  collect('HSL/HSLA', /hsla?\([^)]*\)/g, 'hsl')
  collect('OKLCH', /oklch\([^)]*\)/g, 'oklch')
  collect('Nombre de color', /\b(?:color|background(?:-color)?|border(?:-color)?|fill|stroke)\s*[:=]\s*['"]?(?:white|black|blue|red|green|yellow|gray|grey|orange|purple|teal|navy|silver|transparent|currentColor|inherit)\b/g, 'nombres')
  collect('Variable', /var\(--[\w-]+(?:\s*,[^)]*)?\)/g, 'variables')
  collect('Inline', /(?<![\w-])(?:v-bind:style|:style|style)\s*=\s*(['"])[\s\S]*?\1/g, 'inline')
  rangesByFile.set(file, ranges)
  row.styleBlocks = (text.match(/<style\b/g)||[]).length
  row.tables = (text.match(/<table\b/g)||[]).length
  row.forms = (text.match(/<form\b/g)||[]).length
  row.overlays = (text.match(/fixed\s+inset-0/g)||[]).length
  row.literalColorDistinct = row.hex+row.rgb+row.hsl+row.oklch+row.nombres
  row.visual = /\.(vue|css|scss|sass|html|xhtml|svg)$/.test(file)||file.includes('/views/')||row.literalColorDistinct+row.tailwindFisico+row.inline>0
  metrics.push(row)
  for (const m of text.matchAll(/(--[\w-]+)\s*:\s*([^;{}]+);/g)) {
    const line = text.slice(0,m.index).split('\n').length
    variables.push({archivo:file,linea:line,ambito:area,nombre:m[1],valor:m[2].trim(),referenciasDirectas:0})
  }
}
for(const v of variables) v.referenciasDirectas=rows.filter(r=>r.ambito===v.ambito && r.tipo==='Variable' && new RegExp('^var\\(' + v.nombre + '(?:\\s*[,\\)])').test(r.valor)).length
function csv(name, data) {
  if(!data.length)return
  const columns=Object.keys(data[0]), quote=v=>'"'+String(v??'').replaceAll('"','""')+'"'
  fs.writeFileSync(path.join(out,name),'\ufeff'+[columns.map(quote).join(','),...data.map(r=>columns.map(k=>quote(r[k])).join(','))].join('\n')+'\n')
}
csv('inventario.csv',rows)
csv('archivos.csv',metrics)
csv('variables.csv',variables)
const groups = {}
for(const m of metrics){ const g=groups[m.ambito] ||= {archivos:0,visuales:0,hex:0,rgb:0,hsl:0,oklch:0,nombres:0,tailwindFisico:0,tailwindArbitrario:0,variables:0,inline:0,tables:0,forms:0,overlays:0};g.archivos++;if(m.visual)g.visuales++;for(const k of Object.keys(g))if(!['archivos','visuales'].includes(k))g[k]+=m[k] }
const colors = {}
for(const r of rows.filter(r=>['HEX','RGB/RGBA','HSL/HSLA','OKLCH','Tailwind físico','Tailwind arbitrario','Nombre de color'].includes(r.tipo))){const key=r.valor.toLowerCase();const c=colors[key] ||= {valor:key,tipo:r.tipo,usos:0,usosActivo:0,archivos:new Set(),archivosActivo:new Set(),ambitos:new Set()};c.usos++;c.archivos.add(r.archivo);c.ambitos.add(r.ambito);if(r.ambito==='Frontend activo'){c.usosActivo++;c.archivosActivo.add(r.archivo)}}
csv('colores.csv',Object.values(colors).sort((a,b)=>b.usosActivo-a.usosActivo||b.usos-a.usos).map(c=>({...c,archivos:[...c.archivos].join(' | '),archivosActivo:[...c.archivosActivo].join(' | '),ambitos:[...c.ambitos].join(' | ')})))
const lock=JSON.parse(fs.readFileSync(path.join(root,'frontend/package-lock.json'),'utf8'))
const versions=Object.fromEntries(['tailwindcss','@tailwindcss/vite','vite','vue','vue-router','pinia','@tiptap/vue-3','chart.js'].map(k=>[k,lock.packages['node_modules/'+k]?.version]))
const activeColors=rows.filter(r=>r.ambito==='Frontend activo'&&['Tailwind físico','Tailwind arbitrario','HEX','RGB/RGBA','HSL/HSLA','OKLCH','Nombre de color','Variable'].includes(r.tipo))
// Métrica sintáctica: excluye definiciones centrales y HEX ya incluidos en Tailwind arbitrario.
const normalized=activeColors.filter(r=>r.tipo==='Variable').length
const fixed=activeColors.filter(r=>!['Tokenizado','Excepción justificada'].includes(r.clasificacion) && !(['HEX','RGB/RGBA','HSL/HSLA','OKLCH'].includes(r.tipo) && rangesByFile.get(r.archivo).some(([a,b])=>r.posicion>=a && r.posicion<b))).length
const summary={fecha:'2026-10-08',versions,groups,totalFiles:files.length,visualFiles:metrics.filter(m=>m.visual).length,activeFiles:active.size,activeVue:metrics.filter(m=>m.ambito==='Frontend activo'&&m.archivo.endsWith('.vue')).length,views:metrics.filter(m=>m.ambito==='Frontend activo'&&m.archivo.includes('/views/')&&m.archivo.endsWith('.vue')).length,customVariables:variables.filter(v=>v.ambito==='Frontend activo'),coverage:{normalized,fixed,percentage:+(100*normalized/(normalized+fixed)).toFixed(2)},topActive:metrics.filter(m=>m.ambito==='Frontend activo').sort((a,b)=>(b.hex+b.tailwindFisico)-(a.hex+a.tailwindFisico)).slice(0,15),topColors:Object.values(colors).filter(c=>c.ambitos.has('Frontend activo')).sort((a,b)=>b.usosActivo-a.usosActivo).slice(0,25).map(c=>({valor:c.valor,usos:c.usosActivo,archivos:c.archivosActivo.size}))}
fs.writeFileSync(path.join(out,'resumen.json'),JSON.stringify(summary,null,2)+'\n')
console.log(JSON.stringify({totalFiles:summary.totalFiles,visualFiles:summary.visualFiles,activeFiles:summary.activeFiles,views:summary.views,groups:summary.groups,variablesActivas:summary.customVariables.length,coverage:summary.coverage},null,2))
