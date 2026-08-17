<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 15mm 12mm; }
body { font-family: Arial, sans-serif; font-size: 7.5pt; color: #222; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
th { background-color: #1e3a5f; color: #fff; padding: 5px 4px; text-align: center; font-size: 7pt; }
td { padding: 4px; border-bottom: 1px solid #e5e7eb; }
tr:nth-child(even) td { background-color: #f4f7fb; }
.center  { text-align: center; }
.bold    { font-weight: bold; }
.badge-pendiente { background:#fef9c3; color:#854d0e; padding:1px 5px; border-radius:3px; font-size:6.5pt; }
.badge-aprobado  { background:#dbeafe; color:#1e40af; padding:1px 5px; border-radius:3px; font-size:6.5pt; }
.badge-negado    { background:#fee2e2; color:#991b1b; padding:1px 5px; border-radius:3px; font-size:6.5pt; }
.badge-completado{ background:#dcfce7; color:#166534; padding:1px 5px; border-radius:3px; font-size:6.5pt; }
.resumen { margin-bottom:8px; }
.resumen td { border:none; padding:3px 8px; font-size:7.5pt; }
.footer  { margin-top: 12px; text-align: right; font-size: 7pt; color: #555; }
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
      <div style="font-size:9pt; font-weight:bold; text-transform:uppercase; margin-top:3px;">REPORTE DE MOVILIZACIÓN VEHICULAR</div>
      <div style="font-size:7.5pt; color:#555; margin-top:2px;">
        Período: {{ $request->fecha_desde }} al {{ $request->fecha_hasta }}
      </div>
    </td>
  </tr>
</table>

<table class="resumen">
  <tr>
    <td class="bold">Total solicitudes: {{ $resumen['total_solicitudes'] }}</td>
    <td class="bold">Pendientes: {{ $resumen['pendientes'] }}</td>
    <td class="bold">Aprobadas: {{ $resumen['aprobadas'] }}</td>
    <td class="bold">Negadas: {{ $resumen['negadas'] }}</td>
    <td class="bold">Completadas: {{ $resumen['completadas'] }}</td>
    <td class="bold" style="color:#1e3a5f;">Km recorridos: {{ number_format($resumen['km_totales']) }} (prom. {{ $resumen['promedio_km'] }} km/viaje)</td>
  </tr>
</table>

<table>
  <thead>
    <tr>
      <th style="width:7%">Fecha</th>
      <th style="width:13%">Solicitante</th>
      <th style="width:11%">Vehículo</th>
      <th style="width:12%">Conductor</th>
      <th style="width:13%">Destino</th>
      <th style="width:16%">Motivo</th>
      <th style="width:6%" class="center">H. Salida</th>
      <th style="width:6%" class="center">H. Retorno</th>
      <th style="width:7%" class="center">Km Recorridos</th>
      <th style="width:9%" class="center">Estado</th>
    </tr>
  </thead>
  <tbody>
    @forelse($datos as $r)
    <tr>
      <td>{{ substr($r->fecha_movilizacion, 0, 10) }}</td>
      <td class="bold">{{ $r->solicitante }}</td>
      <td>{{ $r->placa ?? '—' }}</td>
      <td>{{ $r->conductor ?? '—' }}</td>
      <td>{{ $r->lugar_destino }}</td>
      <td style="color:#555;">{{ $r->motivo }}</td>
      <td class="center">{{ substr($r->hora_salida, 0, 5) }}</td>
      <td class="center">{{ substr($r->hora_retorno, 0, 5) }}</td>
      <td class="center bold">{{ $r->km_recorridos !== null ? number_format($r->km_recorridos) : '—' }}</td>
      <td class="center">
        @if($r->estado === 'PENDIENTE')
          <span class="badge-pendiente">PENDIENTE</span>
        @elseif($r->estado === 'APROBADO')
          <span class="badge-aprobado">APROBADO</span>
        @elseif($r->estado === 'NEGADO')
          <span class="badge-negado">NEGADO</span>
        @else
          <span class="badge-completado">COMPLETADO</span>
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
