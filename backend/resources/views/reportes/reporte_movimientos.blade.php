<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 15mm 12mm; }
body { font-family: Arial, sans-serif; font-size: 7.5pt; color: #222; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
th { background-color: #0b5447; color: #fff; padding: 5px 4px; text-align: center; font-size: 7pt; }
td { padding: 4px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
.badge { padding:1px 6px; border-radius:3px; font-size:6.5pt; font-weight:bold; white-space:nowrap; }
.vac  { background:#d1fae5; color:#065f46; }
.perm { background:#fef9c3; color:#713f12; }
.lic  { background:#e0e7ff; color:#3730a3; }
.com  { background:#fce7f3; color:#831843; }
.center { text-align: center; }
.bold   { font-weight: bold; }
.footer { margin-top: 12px; text-align: right; font-size: 7pt; color: #555; }
</style>
</head>
<body>

<table style="margin-bottom:10px;">
  <tr>
    <td style="width:13%; text-align:center; vertical-align:middle;">
      @if($logo)<img src="data:image/png;base64,{{ $logo }}" style="max-height:70px; max-width:100px;">@endif
    </td>
    <td style="text-align:center; vertical-align:middle;">
      <div style="font-size:10pt; font-weight:bold; text-transform:uppercase;">{{ $nombreInst }}</div>
      <div style="font-size:9pt; font-weight:bold; text-transform:uppercase; margin-top:3px;">REPORTE DE MOVIMIENTOS DE PERSONAL</div>
      <div style="font-size:7.5pt; color:#555; margin-top:2px;">
        Período: {{ $request->fecha_desde }} al {{ $request->fecha_hasta }}
      </div>
    </td>
  </tr>
</table>

@php
  $claseTipo = ['VACACIONES'=>'vac','PERMISO'=>'perm','LICENCIA'=>'lic','COMISION'=>'com'];
@endphp

<table>
  <thead>
    <tr>
      <th style="width:9%">Tipo</th>
      <th style="width:20%">Empleado</th>
      <th style="width:16%">Cargo</th>
      <th style="width:16%">Departamento</th>
      <th style="width:8%" class="center">Fecha Desde</th>
      <th style="width:8%" class="center">Fecha Hasta</th>
      <th style="width:5%" class="center">Días</th>
      <th style="width:18%">Detalle</th>
    </tr>
  </thead>
  <tbody>
    @forelse($datos as $r)
    <tr>
      <td class="center">
        <span class="badge {{ $claseTipo[$r->tipo] ?? '' }}">{{ $r->tipo }}</span>
      </td>
      <td class="bold">{{ $r->nombre_completo }}</td>
      <td style="color:#555; font-size:7pt;">{{ $r->cargo_empleado ?? '—' }}</td>
      <td style="color:#555;">{{ $r->nombre_depto }}</td>
      <td class="center">{{ $r->fecha_desde }}</td>
      <td class="center">{{ $r->fecha_hasta }}</td>
      <td class="center">{{ $r->dias ?? '—' }}</td>
      <td style="font-size:7pt; color:#444;">{{ $r->detalle }}</td>
    </tr>
    @empty
    <tr><td colspan="8" style="text-align:center; color:#aaa; padding:12px;">Sin registros para el período</td></tr>
    @endforelse
  </tbody>
</table>

@php
  $totales = collect($datos)->groupBy('tipo')->map->count();
@endphp
<table style="margin-top:8px; width:auto; float:right;">
  @foreach($totales as $tipo => $cant)
  <tr>
    <td style="padding:2px 8px; font-size:7pt; color:#555;">{{ $tipo }}:</td>
    <td style="padding:2px 8px; font-size:7pt; font-weight:bold;">{{ $cant }}</td>
  </tr>
  @endforeach
  <tr style="border-top:1px solid #ccc;">
    <td style="padding:2px 8px; font-size:7pt; font-weight:bold;">TOTAL:</td>
    <td style="padding:2px 8px; font-size:7pt; font-weight:bold;">{{ count($datos) }}</td>
  </tr>
</table>

<p class="footer" style="clear:both;">
  Generado por: {{ strtoupper($generadoPor) }} &nbsp;&nbsp;|&nbsp;&nbsp; {{ now()->format('d/m/Y H:i') }}
</p>
</body>
</html>
