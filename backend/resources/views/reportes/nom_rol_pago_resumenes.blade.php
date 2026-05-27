<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, sans-serif; font-size: 8pt; color: #000; }
  @page { margin: 12mm 12mm 12mm 12mm; size: a4 portrait; }
  table { border-collapse: collapse; width: 100%; }

  .title    { text-align: center; font-weight: bold; font-size: 10pt; text-transform: uppercase; }
  .subtitle { text-align: center; font-size: 8pt; margin-top: 3px; }

  .data-table th {
    background-color: #0b5447; color: #fff;
    border: 1px solid #000; padding: 4px 6px;
    font-size: 7.5pt; font-weight: bold; text-align: center;
  }
  .data-table td {
    border: 1px solid #000; padding: 3px 6px; font-size: 8pt; vertical-align: middle;
  }
  .data-table td.c { text-align: center; }
  .data-table td.r { text-align: right; }
  .data-table tr.total-row td {
    font-weight: bold; background-color: #efefef; border-top: 2px solid #000;
  }
  .estado-badge { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 7pt; font-weight: bold; }
  .borrador { background: #fef3c7; color: #b45309; }
  .cerrado  { background: #d1fae5; color: #065f46; }
</style>
</head>
<body>

@php
  $nombreInst = \App\Models\Configuracion::where('concepto','nombre_institucion')->value('valor') ?? 'CONSEJO DE COMUNICACIÓN';

  $totalEmpleados = $filas->sum('empleados');
  $totalRmu       = $filas->sum('total_rmu');
  $totalPatronal  = $filas->sum('total_patronal');
  $totalPersonal  = $filas->sum('total_personal');
  $totalDesc      = $filas->sum('total_descuentos');
  $totalLiquido   = $filas->sum('total_liquido');
@endphp

<table style="margin-bottom:8px;">
  <tr>
    <td style="width:15%; text-align:center; vertical-align:middle;">
      @if($logo)
        <img src="{{ $logo }}" style="max-height:80px; max-width:110px;">
      @endif
    </td>
    <td style="text-align:center; vertical-align:middle; padding: 0 10px;">
      <div style="font-size:9.5pt; font-weight:bold; text-transform:uppercase;">{{ $nombreInst }}</div>
      <div class="title" style="margin-top:3px;">ROL DE PAGOS — RESUMEN POR PROGRAMA / ACTIVIDAD</div>
      <div class="subtitle">
        Período: {{ $nombreMes }} {{ $anio }}
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <span class="estado-badge {{ strtolower($cab->estado) }}">{{ $cab->estado }}</span>
        &nbsp;&nbsp;|&nbsp;&nbsp;{{ $totalEmpleados }} servidores
      </div>
    </td>
  </tr>
</table>

<table class="data-table">
  <thead>
    <tr>
      <th style="width:8%;">Programa</th>
      <th style="width:10%;">Actividad</th>
      <th style="width:8%;">Servidores</th>
      <th style="width:18%;">Total RMU $</th>
      <th style="width:18%;">Total Ap. Patronal $</th>
      <th style="width:18%;">Total Descuentos $</th>
      <th style="width:20%;">Total Líquido $</th>
    </tr>
  </thead>
  <tbody>
    @foreach($filas as $f)
    <tr>
      <td class="c">{{ $f->programa }}</td>
      <td class="c">{{ $f->actividad }}</td>
      <td class="c">{{ $f->empleados }}</td>
      <td class="r">{{ number_format($f->total_rmu, 2) }}</td>
      <td class="r">{{ number_format($f->total_patronal, 2) }}</td>
      <td class="r">{{ number_format($f->total_descuentos, 2) }}</td>
      <td class="r">{{ number_format($f->total_liquido, 2) }}</td>
    </tr>
    @endforeach
  </tbody>
  <tfoot>
    <tr class="total-row">
      <td colspan="2" style="text-align:right; padding-right:8px;">TOTAL</td>
      <td class="c">{{ $totalEmpleados }}</td>
      <td class="r">{{ number_format($totalRmu, 2) }}</td>
      <td class="r">{{ number_format($totalPatronal, 2) }}</td>
      <td class="r">{{ number_format($totalDesc, 2) }}</td>
      <td class="r">{{ number_format($totalLiquido, 2) }}</td>
    </tr>
  </tfoot>
</table>

<p style="font-size:7pt; color:#555; text-align:right; margin-top:10px;">
  Generado por: {{ strtoupper($generadoPor) }} &nbsp;|&nbsp; {{ now()->format('d/m/Y H:i') }}
</p>

</body>
</html>
