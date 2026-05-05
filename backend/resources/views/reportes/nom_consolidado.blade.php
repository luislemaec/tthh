<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, sans-serif; font-size: 9pt; color: #000; }
  @page { margin: 12mm 15mm 12mm 15mm; size: letter portrait; }
  table { border-collapse: collapse; width: 100%; table-layout: fixed; }

  .title { text-align: center; font-weight: bold; font-size: 11pt; text-transform: uppercase; padding: 4px 0; }
  .subtitle { text-align: center; font-size: 9pt; margin-bottom: 10px; }

  .aviso { font-size: 8pt; color: #b45309; background: #fef3c7; border: 1px solid #f59e0b;
           padding: 4px 8px; margin-bottom: 8px; border-radius: 3px; }

  .data-table th {
    background-color: #d0d0d0; border: 1px solid #000;
    padding: 5px 4px; font-size: 8pt; font-weight: bold; text-align: center;
  }
  .data-table td {
    border: 1px solid #000; padding: 4px 5px; font-size: 8pt; vertical-align: middle;
  }
  .data-table td.c { text-align: center; }
  .data-table td.r { text-align: right; }
  .data-table tr.total-row td { font-weight: bold; background-color: #efefef; }
  .col-d13 { background-color: #eff6ff; }
  .col-d14 { background-color: #f0fdf4; }
  .col-tot { background-color: #fef9c3; }
</style>
</head>
<body>

@php
  $nombreInst = \App\Models\Configuracion::where('concepto','nombre_institucion')->value('valor') ?? 'CONSEJO DE COMUNICACIÓN';
@endphp

<table style="margin-bottom:8px;">
  <tr>
    <td style="width:18%; text-align:center; vertical-align:middle;">
      @if($logo)
        <img src="{{ $logo }}" style="max-height:55px; max-width:80px;">
      @endif
    </td>
    <td style="text-align:center; vertical-align:middle;">
      <div style="font-size:10pt; font-weight:bold; text-transform:uppercase;">{{ $nombreInst }}</div>
      <div class="title" style="margin-top:4px;">CONSOLIDADO DÉCIMOS — 13° Y 14°</div>
      <div class="subtitle">Período: {{ $nombreMes }} {{ $anio }}</div>
    </td>
  </tr>
</table>

@if(!$tiene_d13 || !$tiene_d14)
<div class="aviso">
  ⚠ Aviso:
  @if(!$tiene_d13) No se ha calculado el Décimo Tercero para este período. @endif
  @if(!$tiene_d14) No se ha calculado el Décimo Cuarto para este período. @endif
</div>
@endif

<table class="data-table">
  <thead>
    <tr>
      <th style="width:5%;">N°</th>
      <th style="width:28%;">Apellidos y Nombres</th>
      <th style="width:12%;">Cédula</th>
      <th style="width:20%;">Departamento</th>
      <th style="width:11%;" class="col-d13">Décimo Tercero $</th>
      <th style="width:11%;" class="col-d14">Décimo Cuarto $</th>
      <th style="width:13%;" class="col-tot">Total $</th>
    </tr>
  </thead>
  <tbody>
    @foreach($filas as $i => $f)
    <tr>
      <td class="c">{{ $i + 1 }}</td>
      <td>{{ strtoupper(($f['empleado']->apellido_emp ?? '') . ' ' . ($f['empleado']->nombre_emp ?? '')) }}</td>
      <td class="c">{{ $f['empleado']->identificacion ?? '' }}</td>
      <td>{{ $f['empleado']->departamento->nombre_depto ?? '' }}</td>
      <td class="r col-d13">{{ number_format($f['valor_13'], 2) }}</td>
      <td class="r col-d14">{{ number_format($f['valor_14'], 2) }}</td>
      <td class="r col-tot" style="font-weight:bold;">{{ number_format($f['total'], 2) }}</td>
    </tr>
    @endforeach
  </tbody>
  <tfoot>
    <tr class="total-row">
      <td colspan="4" style="text-align:right; padding-right:8px;">TOTAL ({{ count($filas) }} servidores)</td>
      <td class="r col-d13">{{ number_format($total_13, 2) }}</td>
      <td class="r col-d14">{{ number_format($total_14, 2) }}</td>
      <td class="r col-tot">{{ number_format($gran_total, 2) }}</td>
    </tr>
  </tfoot>
</table>

<p style="font-size:7.5pt; color:#555; margin-top:8px; text-align:right;">
  Generado el {{ now()->format('d/m/Y H:i') }}
</p>

</body>
</html>
