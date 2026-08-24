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
.right   { text-align: right; }
.bold    { font-weight: bold; }
.anulado { text-decoration: line-through; color: #999; }
.badge-anulado { background:#fee2e2; color:#991b1b; padding:1px 5px; border-radius:3px; font-size:6.5pt; }
.badge-emitido { background:#dcfce7; color:#166534; padding:1px 5px; border-radius:3px; font-size:6.5pt; }
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
      <div style="font-size:9pt; font-weight:bold; text-transform:uppercase; margin-top:3px;">REPORTE DE VALES DE COMBUSTIBLE</div>
      <div style="font-size:7.5pt; color:#555; margin-top:2px;">
        Período: {{ $request->fecha_desde }} al {{ $request->fecha_hasta }}
      </div>
    </td>
  </tr>
</table>

<table class="resumen">
  <tr>
    <td class="bold">Total vales: {{ $resumen['total_vales'] }}</td>
    <td class="bold">Anulados: {{ $resumen['total_anulados'] }}</td>
    <td class="bold">Gl. Extra: {{ number_format($resumen['total_glns_extra'], 2) }}</td>
    <td class="bold">Gl. Súper: {{ number_format($resumen['total_glns_super'], 2) }}</td>
    <td class="bold">Gl. Diésel: {{ number_format($resumen['total_glns_diesel'], 2) }}</td>
    <td class="bold" style="color:#1e3a5f;">Total gastado: ${{ number_format($resumen['total_valor'], 2) }}</td>
  </tr>
</table>

<table>
  <thead>
    <tr>
      <th style="width:5%">N°</th>
      <th style="width:7%">Fecha</th>
      <th style="width:16%">Vehículo</th>
      <th style="width:15%">Conductor</th>
      <th style="width:13%">Gasolinera</th>
      <th style="width:8%" class="center">Gl. Extra</th>
      <th style="width:8%" class="center">Gl. Súper</th>
      <th style="width:8%" class="center">Gl. Diésel</th>
      <th style="width:9%" class="center">Total</th>
      <th style="width:11%" class="center">Estado</th>
    </tr>
  </thead>
  <tbody>
    @forelse($datos as $r)
    <tr class="{{ $r->estado === 'ANULADO' ? 'anulado' : '' }}">
      <td class="center">{{ $r->numero }}</td>
      <td>{{ substr($r->fecha, 0, 10) }}</td>
      <td>{{ $r->placa }} — {{ $r->marca }} {{ $r->modelo }}</td>
      <td>{{ $r->conductor }}</td>
      <td>{{ $r->gasolinera }}</td>
      <td class="center">{{ $r->glns_extra ? number_format($r->glns_extra, 2) : '—' }}</td>
      <td class="center">{{ $r->glns_super ? number_format($r->glns_super, 2) : '—' }}</td>
      <td class="center">{{ $r->glns_diesel ? number_format($r->glns_diesel, 2) : '—' }}</td>
      <td class="center bold">${{ number_format($r->valor_total, 2) }}</td>
      <td class="center">
        @if($r->estado === 'ANULADO')
          <span class="badge-anulado">ANULADO</span>
        @else
          <span class="badge-emitido">EMITIDO</span>
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
