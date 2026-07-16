<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 7.5px; color: #111; }
  @page { margin: 8mm 12mm 8mm 12mm; }

  table { border-collapse: collapse; width: 100%; table-layout: fixed; }
  th {
    background-color: #4a5e3a;
    color: #fff;
    border: 1px solid #3b4a2e;
    padding: 4px 3px;
    text-align: center;
    font-size: 7px;
    text-transform: uppercase;
  }
  td { border: 1px solid #ccc; padding: 3px 4px; font-size: 7.5px; vertical-align: middle; }
  tr:nth-child(even) td { background-color: #f7f9f4; }
  .r  { text-align: right; font-family: 'Courier New', monospace; font-size: 7.5px; }
  .c  { text-align: center; }
  .total-row td { background-color: #e0e8d8 !important; font-weight: bold; border-top: 2px solid #4a5e3a; }
</style>
</head>
<body>

@php
  $mesNombre = fn($fecha) => $meses[(int)substr($fecha, 5, 2)] . ' ' . substr($fecha, 0, 4);

  $totalAnt  = $resultado->sum('saldo_anterior');
  $totalProc = $resultado->sum('ingreso_procesos');
  $totalCaja = $resultado->sum('ingreso_caja_chica');
  $totalEgr  = $resultado->sum('egreso_mes');
  $totalFin  = $resultado->sum('saldo_final');
@endphp

{{-- Encabezado estándar --}}
<table style="margin-bottom:8px; border:none;">
  <tr>
    <td style="width:12%; border:none; text-align:center; vertical-align:middle;">
      @if($logo)<img src="{{ $logo }}" style="max-height:70px; max-width:100px;">@endif
    </td>
    <td style="border:none; text-align:center; vertical-align:middle; padding:0 8px;">
      <div style="font-size:10pt; font-weight:bold; text-transform:uppercase;">{{ $nombreInst }}</div>
      <div style="font-size:9pt; font-weight:bold; text-transform:uppercase; margin-top:3px;">REPORTE DE INVENTARIO MENSUAL</div>
      <div style="font-size:8pt; margin-top:2px;">
        Período: {{ $mesNombre($desde) }}
        @if(substr($desde, 0, 7) !== substr($hasta, 0, 7))
          &nbsp;al&nbsp;{{ $mesNombre($hasta) }}
        @endif
        &nbsp;&nbsp;|&nbsp;&nbsp; {{ $desde }} al {{ $hasta }}
      </div>
    </td>
  </tr>
</table>

<table>
  <thead>
    <tr>
      <th style="width:7%;">Cuenta</th>
      <th style="width:22%;">Descripción Inventarios</th>
      <th style="width:11%;">Saldo Mes Anterior</th>
      <th style="width:11%;">Ingreso Mes Procesos</th>
      <th style="width:11%;">Ingreso Mes Caja Chica</th>
      <th style="width:11%;">Egreso Mes</th>
      <th style="width:11%;">Saldo Final Mes</th>
    </tr>
  </thead>
  <tbody>
    @foreach($resultado as $r)
    <tr>
      <td class="c" style="font-weight:bold;">{{ $r['cuenta'] }}</td>
      <td>{{ $r['descripcion'] }}</td>
      <td class="r">{{ number_format($r['saldo_anterior'],    2) }}</td>
      <td class="r">{{ number_format($r['ingreso_procesos'],  2) }}</td>
      <td class="r">{{ number_format($r['ingreso_caja_chica'],2) }}</td>
      <td class="r">{{ number_format($r['egreso_mes'],        2) }}</td>
      <td class="r" style="font-weight:bold;">{{ number_format($r['saldo_final'], 2) }}</td>
    </tr>
    @endforeach
  </tbody>
  <tfoot>
    <tr class="total-row">
      <td colspan="2" style="text-align:right; padding-right:6px;">TOTAL</td>
      <td class="r">{{ number_format($totalAnt,  2) }}</td>
      <td class="r">{{ number_format($totalProc, 2) }}</td>
      <td class="r">{{ number_format($totalCaja, 2) }}</td>
      <td class="r">{{ number_format($totalEgr,  2) }}</td>
      <td class="r">{{ number_format($totalFin,  2) }}</td>
    </tr>
  </tfoot>
</table>

<p style="font-size:6.5pt; color:#555; margin-top:8px; text-align:right;">
  Generado por: {{ strtoupper($generadoPor) }} &nbsp;|&nbsp; {{ now()->format('d/m/Y H:i') }}
</p>

</body>
</html>
