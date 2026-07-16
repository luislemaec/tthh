<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 15mm 12mm; }
body { font-family: Arial, sans-serif; font-size: 7.5pt; color: #222; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
th { background-color: #0b5447; color: #fff; padding: 5px 4px; text-align: center; font-size: 7pt; }
td { padding: 4px; border-bottom: 1px solid #e5e7eb; }
tr:nth-child(even) td { background-color: #f4fbf8; }
.badge-parcial { background:#fef3c7; color:#92400e; padding:1px 5px; border-radius:3px; font-size:6.5pt; }
.badge-sin     { background:#fee2e2; color:#991b1b; padding:1px 5px; border-radius:3px; font-size:6.5pt; }
.center        { text-align: center; }
.right         { text-align: right; }
.bold          { font-weight: bold; }
.footer        { margin-top: 12px; text-align: right; font-size: 7pt; color: #555; }
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
      <div style="font-size:9pt; font-weight:bold; text-transform:uppercase; margin-top:3px;">REPORTE DE ATRASOS</div>
      <div style="font-size:7.5pt; color:#555; margin-top:2px;">
        Período: {{ $request->fecha_desde }} al {{ $request->fecha_hasta }}
        @if($request->filled('id_depto')) &nbsp;|&nbsp; Departamento filtrado @endif
      </div>
    </td>
  </tr>
</table>

<table>
  <thead>
    <tr>
      <th style="width:7%">Fecha</th>
      <th style="width:18%">Empleado</th>
      <th style="width:14%">Departamento</th>
      <th style="width:7%" class="center">H. Prog.</th>
      <th style="width:7%" class="center">H. Real</th>
      <th style="width:8%" class="center">Atr. Entrada</th>
      <th style="width:8%" class="center">Atr. Lunch</th>
      <th style="width:9%" class="center">Sal. Anticipada</th>
      <th style="width:9%" class="center">H. Descuento</th>
      <th style="width:13%" class="center">Justificación</th>
    </tr>
  </thead>
  <tbody>
    @php
      $minTexto = function($min) {
          if (!$min || $min <= 0) return '—';
          $h = intdiv($min, 60); $m = $min % 60;
          return $h > 0 ? ($m > 0 ? "{$h}h {$m}min" : "{$h}h") : "{$m}min";
      };
      $decHora = function($v) {
          if ($v === null) return '—';
          $h = floor($v); $m = round(($v - $h) * 60);
          return sprintf('%02d:%02d', $h, $m);
      };
    @endphp
    @forelse($datos as $r)
    <tr>
      <td>{{ substr($r->fecha, 0, 10) }}</td>
      <td class="bold">{{ $r->nombre_completo }}</td>
      <td style="color:#555;">{{ $r->nombre_depto }}</td>
      <td class="center" style="color:#aaa;">{{ $decHora($r->hora_turno_entrada) }}</td>
      <td class="center">{{ $decHora($r->hora_real_entrada) }}</td>
      <td class="center" style="color:#b45309;">{{ $r->atraso_entrada > 0 ? $minTexto($r->atraso_entrada) : '—' }}</td>
      <td class="center" style="color:#b45309;">{{ $r->atraso_lunch   > 0 ? $minTexto($r->atraso_lunch)   : '—' }}</td>
      <td class="center" style="color:#0b5447;">{{ $r->atraso_salida  > 0 ? $minTexto($r->atraso_salida)  : '—' }}</td>
      <td class="center" style="color:#dc2626; font-weight:bold;">{{ $r->horas_decto > 0 ? $minTexto(round($r->horas_decto * 60)) : '—' }}</td>
      <td class="center">
        @if($r->justificacion === 'PARCIAL')
          <span class="badge-parcial">Parcial ({{ $minTexto($r->minutos_pendientes) }} pend.)</span>
        @else
          <span class="badge-sin">Sin justificar</span>
        @endif
      </td>
    </tr>
    @empty
    <tr><td colspan="10" style="text-align:center; color:#aaa; padding:12px;">Sin registros para el período</td></tr>
    @endforelse
  </tbody>
</table>

<p class="footer">
  Total: <strong>{{ count($datos) }} registros</strong> &nbsp;&nbsp;|&nbsp;&nbsp;
  Generado por: {{ strtoupper($generadoPor) }} &nbsp;&nbsp;|&nbsp;&nbsp; {{ now()->format('d/m/Y H:i') }}
</p>
</body>
</html>
