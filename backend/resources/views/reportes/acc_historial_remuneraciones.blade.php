<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  @reference "tailwindcss";
  body { font-family: Arial, sans-serif; font-size: 8pt; color: #1a1a1a; margin: 0; padding: 0; }
  @page { margin: 1.2cm 1.5cm; size: A4 landscape; }

  .header-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
  .header-table td { vertical-align: middle; padding: 2px 6px; }

  h2 { font-size: 11pt; font-weight: bold; text-align: center; text-transform: uppercase; margin: 0; }
  h3 { font-size: 9pt; text-align: center; margin: 2px 0 0; color: #444; }

  table { width: 100%; border-collapse: collapse; table-layout: fixed; }
  thead th {
    background: #0b5447; color: #fff; font-weight: bold;
    padding: 5px 4px; text-align: center; font-size: 7.5pt;
    border: 1px solid #0b5447;
  }
  tbody td { padding: 4px 4px; border: 1px solid #d1d5db; font-size: 7.5pt; vertical-align: top; }
  tbody tr:nth-child(even) { background: #f0fdf4; }

  .badge { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 6.5pt; font-weight: bold; }
  .badge-ingreso    { background: #dcfce7; color: #166534; }
  .badge-encargo    { background: #dbeafe; color: #1e40af; }
  .badge-subroga    { background: #ede9fe; color: #5b21b6; }
  .badge-cesacion   { background: #fef9c3; color: #854d0e; }
  .badge-destitucion{ background: #fee2e2; color: #991b1b; }

  .dif-pos { color: #166534; font-weight: bold; }
  .dif-neg { color: #991b1b; font-weight: bold; }
  .dif-zero{ color: #6b7280; }

  .text-right { text-align: right; }
  .text-center{ text-align: center; }
  .footer { margin-top: 10px; font-size: 7pt; color: #555; text-align: right; }
</style>
</head>
<body>

{{-- Encabezado --}}
<table class="header-table">
  <tr>
    <td style="width:12%; text-align:center;">
      @if($logo)<img src="{{ $logo }}" style="max-height:70px; max-width:100px;">@endif
    </td>
    <td style="text-align:center;">
      <h2>{{ strtoupper($nombreInst) }}</h2>
      <h2>HISTORIAL DE CARGOS Y REMUNERACIONES</h2>
      @if($filtroEmp)<h3>Empleado: {{ strtoupper($filtroEmp) }}</h3>@endif
      @if($periodoLabel)<h3>Período: {{ $periodoLabel }}</h3>@endif
    </td>
  </tr>
</table>

{{-- Tabla --}}
<table>
  <colgroup>
    <col style="width:10%">  {{-- N° Acción --}}
    <col style="width:17%">  {{-- Empleado --}}
    <col style="width:9%">   {{-- Tipo --}}
    <col style="width:8%">   {{-- Fecha inicio --}}
    <col style="width:8%">   {{-- Fecha fin --}}
    <col style="width:23%">  {{-- Cargo --}}
    <col style="width:8%">   {{-- Rem. acción --}}
    <col style="width:8%">   {{-- Sueldo actual --}}
    <col style="width:9%">   {{-- Diferencia --}}
  </colgroup>
  <thead>
    <tr>
      <th>N° Acción</th>
      <th>Empleado</th>
      <th>Tipo</th>
      <th>Fecha Inicio</th>
      <th>Fecha Fin</th>
      <th>Cargo</th>
      <th>Rem. en acción</th>
      <th>Sueldo actual</th>
      <th>Diferencia</th>
    </tr>
  </thead>
  <tbody>
  @forelse($filas as $f)
    @php
      $tipo = strtoupper($f['tipo_accion'] ?? '');
      $badgeClass = match(true) {
          str_contains($tipo, 'INGRESO')    => 'badge-ingreso',
          str_contains($tipo, 'ENCARGO')    => 'badge-encargo',
          str_contains($tipo, 'SUBROGA')    => 'badge-subroga',
          str_contains($tipo, 'CESACION')   => 'badge-cesacion',
          str_contains($tipo, 'DESTITUCION')=> 'badge-destitucion',
          default => '',
      };
      $dif = (float)($f['diferencia'] ?? 0);
      $difClass = $dif > 0 ? 'dif-pos' : ($dif < 0 ? 'dif-neg' : 'dif-zero');
      $difStr   = ($dif >= 0 ? '+' : '') . '$' . number_format(abs($dif), 2);
      $meses = [1=>'ene',2=>'feb',3=>'mar',4=>'abr',5=>'may',6=>'jun',
                7=>'jul',8=>'ago',9=>'sep',10=>'oct',11=>'nov',12=>'dic'];
      $fmtFecha = function($d) use ($meses) {
          if (!$d) return '—';
          [$y,$m,$day] = explode('-', substr($d,0,10));
          return "{$day}/{$meses[(int)$m]}/{$y}";
      };
    @endphp
    <tr>
      <td class="text-center" style="font-family:monospace; font-size:7pt;">{{ $f['numero_accion'] ?? '—' }}</td>
      <td>{{ $f['empleado']['nombre_completo'] ?? '—' }}</td>
      <td class="text-center"><span class="badge {{ $badgeClass }}">{{ $f['tipo_accion'] }}</span></td>
      <td class="text-center">{{ $fmtFecha($f['fecha_inicio']) }}</td>
      <td class="text-center">{{ $f['fecha_fin'] ? $fmtFecha($f['fecha_fin']) : 'Vigente' }}</td>
      <td>{{ $f['cargo'] ?? '—' }}</td>
      <td class="text-right">${{ $f['remuneracion'] !== null ? number_format($f['remuneracion'], 2) : '—' }}</td>
      <td class="text-right">${{ number_format($f['sueldo_actual'], 2) }}</td>
      <td class="text-right {{ $difClass }}">{{ $difStr }}</td>
    </tr>
  @empty
    <tr><td colspan="9" class="text-center" style="padding:12px; color:#9ca3af;">Sin registros para los filtros aplicados.</td></tr>
  @endforelse
  </tbody>
</table>

<p class="footer">
  Generado por: {{ strtoupper($generadoPor) }} &nbsp;|&nbsp; {{ now()->format('d/m/Y H:i') }}
</p>
</body>
</html>
