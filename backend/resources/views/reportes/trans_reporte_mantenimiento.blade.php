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
.badge-orden     { background:#dbeafe; color:#1e40af; padding:1px 5px; border-radius:3px; font-size:6.5pt; }
.badge-taller    { background:#ffedd5; color:#9a3412; padding:1px 5px; border-radius:3px; font-size:6.5pt; }
.badge-finalizado{ background:#dcfce7; color:#166534; padding:1px 5px; border-radius:3px; font-size:6.5pt; }
.badge-negado    { background:#fee2e2; color:#991b1b; padding:1px 5px; border-radius:3px; font-size:6.5pt; }
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
      <div style="font-size:9pt; font-weight:bold; text-transform:uppercase; margin-top:3px;">REPORTE DE MANTENIMIENTO VEHICULAR</div>
      <div style="font-size:7.5pt; color:#555; margin-top:2px;">
        Período: {{ $request->fecha_desde }} al {{ $request->fecha_hasta }}
      </div>
    </td>
  </tr>
</table>

<table class="resumen">
  <tr>
    <td class="bold">Total: {{ $resumen['total'] }}</td>
    <td class="bold">Preventivos: {{ $resumen['preventivos'] }}</td>
    <td class="bold">Correctivos: {{ $resumen['correctivos'] }}</td>
    <td class="bold" style="color:#166534;">Finalizados: {{ $resumen['finalizados'] }}</td>
    <td class="bold" style="color:#9a3412;">En proceso: {{ $resumen['en_proceso'] }}</td>
    <td class="bold" style="color:#991b1b;">Negados: {{ $resumen['negados'] }}</td>
  </tr>
</table>

<table>
  <thead>
    <tr>
      <th style="width:7%">Fecha</th>
      <th style="width:15%">Vehículo</th>
      <th style="width:9%">Tipo</th>
      <th style="width:22%">Descripción / Plan</th>
      <th style="width:13%">Taller</th>
      <th style="width:8%" class="center">N° Orden</th>
      <th style="width:8%" class="center">Km Actual</th>
      <th style="width:8%" class="center">Km Final.</th>
      <th style="width:10%" class="center">Estado</th>
    </tr>
  </thead>
  <tbody>
    @forelse($datos as $r)
    <tr>
      <td>{{ substr($r->created_at, 0, 10) }}</td>
      <td class="bold">{{ $r->placa }} — {{ $r->marca }} {{ $r->modelo }}</td>
      <td>{{ $r->tipo }}</td>
      <td style="color:#555;">{{ $r->plan_nombre ?? $r->descripcion }}</td>
      <td>{{ $r->taller ?? '—' }}</td>
      <td class="center">{{ $r->numero_orden ?? '—' }}</td>
      <td class="center">{{ $r->km_actual ? number_format($r->km_actual) : '—' }}</td>
      <td class="center">{{ $r->km_finalizacion ? number_format($r->km_finalizacion) : '—' }}</td>
      <td class="center">
        @if($r->estado === 'PENDIENTE')
          <span class="badge-pendiente">PENDIENTE</span>
        @elseif($r->estado === 'ORDEN_GENERADA')
          <span class="badge-orden">ORDEN GEN.</span>
        @elseif($r->estado === 'EN_TALLER')
          <span class="badge-taller">EN TALLER</span>
        @elseif($r->estado === 'FINALIZADO')
          <span class="badge-finalizado">FINALIZADO</span>
        @else
          <span class="badge-negado">NEGADO</span>
        @endif
      </td>
    </tr>
    @empty
    <tr><td colspan="9" style="text-align:center; color:#aaa; padding:12px;">Sin registros para el período</td></tr>
    @endforelse
  </tbody>
</table>

<p class="footer">
  Total: <strong>{{ count($datos) }} registros</strong> &nbsp;&nbsp;|&nbsp;&nbsp;
  Generado por: {{ strtoupper($generadoPor) }} &nbsp;&nbsp;|&nbsp;&nbsp; {{ now()->format('d/m/Y H:i') }}
</p>
</body>
</html>
