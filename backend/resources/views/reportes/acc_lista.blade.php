<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  @page { margin: 15mm 12mm; size: A4 landscape; }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; font-size: 8.5pt; color: #222; }

  /* Encabezado */
  .header { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
  .header td { vertical-align: middle; padding: 0 8px; }
  .header .logo-cell { width: 13%; text-align: center; }
  .header img { max-height: 70px; max-width: 100px; }
  .header .title-cell { text-align: center; }
  .inst-name { font-size: 11pt; font-weight: bold; text-transform: uppercase; }
  .rep-title  { font-size: 10pt; font-weight: bold; text-transform: uppercase; margin-top: 3px; }
  .rep-sub    { font-size: 8pt; color: #555; margin-top: 2px; }

  /* Filtros */
  .filtros { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 4px; padding: 5px 10px; margin-bottom: 8px; font-size: 7.5pt; color: #166534; }
  .filtros strong { margin-right: 4px; }

  /* Tabla */
  table.datos { width: 100%; border-collapse: collapse; table-layout: fixed; }
  table.datos thead tr { background-color: #1a4731; }
  table.datos thead th { color: #fff; font-weight: bold; padding: 5px 6px; text-align: left; font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.04em; }
  table.datos tbody tr:nth-child(even) { background: #f0fdf4; }
  table.datos tbody tr:nth-child(odd)  { background: #ffffff; }
  table.datos td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; font-size: 8pt; }

  /* Widths */
  .col-num  { width: 11%; }
  .col-tipo { width: 9%; }
  .col-emp  { width: 18%; }
  .col-id   { width: 8%; }
  .col-cargo{ width: 14%; }
  .col-fech { width: 7%; }
  .col-est  { width: 6%; }
  .col-mot  { width: 20%; }

  /* Badges */
  .badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 7pt; font-weight: bold; }
  .badge-activo    { background:#dcfce7; color:#166534; }
  .badge-finalizado{ background:#f3f4f6; color:#374151; }
  .badge-anulado   { background:#fee2e2; color:#991b1b; }
  .badge-borrador  { background:#fef9c3; color:#854d0e; }
  .badge-encargo   { background:#dbeafe; color:#1e40af; }
  .badge-subrog    { background:#ede9fe; color:#5b21b6; }
  .badge-ingreso   { background:#d1fae5; color:#065f46; }
  .badge-vacac     { background:#e0f2fe; color:#0c4a6e; }
  .badge-dest      { background:#fee2e2; color:#991b1b; }
  .badge-cesac     { background:#fef3c7; color:#92400e; }

  .num-accion { font-family: monospace; font-size: 7.5pt; color: #1a4731; font-weight: bold; }
  .emp-nombre { font-weight: bold; }
  .emp-id     { font-size: 7pt; color: #666; }

  .footer { margin-top: 12px; text-align: right; font-size: 7pt; color: #6b7280; }
</style>
</head>
<body>

<!-- Encabezado -->
<table class="header">
  <tr>
    <td class="logo-cell">
      @if($logo)<img src="{{ $logo }}">@endif
    </td>
    <td class="title-cell">
      <div class="inst-name">{{ $config['nombre_institucion'] ?? 'Consejo de Comunicación' }}</div>
      <div class="rep-title">Listado de Acciones de Personal</div>
      <div class="rep-sub">Fecha de generación: {{ $fecha }}</div>
    </td>
  </tr>
</table>

<!-- Filtros aplicados -->
<div class="filtros">
  <strong>Filtros aplicados:</strong> {{ $filtros }}
  &nbsp;&nbsp;|&nbsp;&nbsp; <strong>Total:</strong> {{ count($acciones) }} registro(s)
</div>

<!-- Tabla -->
<table class="datos">
  <thead>
    <tr>
      <th class="col-num">Nro. Acción</th>
      <th class="col-tipo">Tipo</th>
      <th class="col-emp">Empleado</th>
      <th class="col-id">Cédula</th>
      <th class="col-cargo">Cargo</th>
      <th class="col-fech">F. Elabor.</th>
      <th class="col-fech">F. Inicio</th>
      <th class="col-fech">F. Fin</th>
      <th class="col-est">Estado</th>
      <th class="col-mot">Motivación</th>
    </tr>
  </thead>
  <tbody>
    @forelse($acciones as $a)
    @php
      $emp = $a->empleado;
      $meses = [1=>'ene',2=>'feb',3=>'mar',4=>'abr',5=>'may',6=>'jun',7=>'jul',8=>'ago',9=>'sep',10=>'oct',11=>'nov',12=>'dic'];
      $fmtFecha = function($f) use ($meses) {
        if (!$f) return '—';
        $d = explode('-', substr($f,0,10));
        return "{$d[2]}/{$d[1]}/{$d[0]}";
      };
      $tipoBadge = match($a->tipo_accion) {
        'ENCARGO'             => 'badge-encargo',
        'SUBROGACION'         => 'badge-subrog',
        'INGRESO'             => 'badge-ingreso',
        'VACACIONES'          => 'badge-vacac',
        'DESTITUCION'         => 'badge-dest',
        'CESACION DE FUNCIONES' => 'badge-cesac',
        default               => '',
      };
      $estBadge = match($a->estado) {
        'ACTIVO'     => 'badge-activo',
        'FINALIZADO' => 'badge-finalizado',
        'ANULADO'    => 'badge-anulado',
        'BORRADOR'   => 'badge-borrador',
        default      => '',
      };
    @endphp
    <tr>
      <td class="num-accion">{{ $a->numero_accion ?? '— BORRADOR —' }}</td>
      <td><span class="badge {{ $tipoBadge }}">{{ $a->tipo_accion }}</span></td>
      <td>
        <div class="emp-nombre">{{ $emp ? strtoupper(trim($emp->apellido_emp)) . ', ' . ucwords(strtolower(trim($emp->nombre_emp))) : '—' }}</div>
      </td>
      <td class="emp-id">{{ $emp?->identificacion ?? '—' }}</td>
      <td style="font-size:7.5pt;">{{ $emp?->cargo_empleado ?? '—' }}</td>
      <td>{{ $fmtFecha($a->fecha_elaboracion) }}</td>
      <td>{{ $fmtFecha($a->fecha_inicio) }}</td>
      <td>{{ $a->fecha_fin ? $fmtFecha($a->fecha_fin) : '—' }}</td>
      <td><span class="badge {{ $estBadge }}">{{ $a->estado }}</span></td>
      <td style="font-size:7.5pt; color:#444;">{{ $a->motivacion ? \Illuminate\Support\Str::limit($a->motivacion, 120) : '—' }}</td>
    </tr>
    @empty
    <tr>
      <td colspan="10" style="text-align:center; padding:16px; color:#9ca3af;">Sin acciones registradas con los filtros aplicados.</td>
    </tr>
    @endforelse
  </tbody>
</table>

<p class="footer">
  Generado por: {{ strtoupper($generadoPor) }} &nbsp;|&nbsp; {{ $fecha }}
</p>

</body>
</html>
