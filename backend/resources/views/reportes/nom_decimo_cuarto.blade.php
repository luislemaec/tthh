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
  .estado-badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 7.5pt; font-weight: bold; }
  .borrador { background: #fef3c7; color: #b45309; }
  .cerrado  { background: #d1fae5; color: #065f46; }
</style>
</head>
<body>

@php
  $nombreInst = \App\Models\Configuracion::where('concepto','nombre_institucion')->value('valor') ?? 'CONSEJO DE COMUNICACIÓN';
  $totalValor = $registros->sum('valor');
  $totalEmp   = $registros->count();
@endphp

<table style="margin-bottom:8px;">
  <tr>
    <td style="width:18%; text-align:center; vertical-align:middle;">
      @if($logo)
        <img src="{{ $logo }}" style="max-height:110px; max-width:160px;">
      @endif
    </td>
    <td style="text-align:center; vertical-align:middle;">
      <div style="font-size:10pt; font-weight:bold; text-transform:uppercase;">{{ $nombreInst }}</div>
      <div class="title" style="margin-top:4px;">DÉCIMO CUARTO SUELDO</div>
      <div class="subtitle">
        Período: {{ $nombreMes }} {{ $anio }}
        &nbsp;&nbsp;|&nbsp;&nbsp;SBU {{ $anio }}: ${{ number_format($sbu, 2) }}
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <span class="estado-badge {{ strtolower($estado) }}">{{ $estado }}</span>
      </div>
    </td>
  </tr>
</table>

<table class="data-table">
  <thead>
    <tr>
      <th style="width:5%;">N°</th>
      <th style="width:32%;">Apellidos y Nombres</th>
      <th style="width:13%;">Cédula</th>
      <th style="width:22%;">Departamento</th>
      <th style="width:10%;">SBU</th>
      <th style="width:8%;">Días</th>
      <th style="width:10%;">Valor</th>
    </tr>
  </thead>
  <tbody>
    @foreach($registros as $i => $r)
    @php $e = $r->empleado; @endphp
    <tr>
      <td class="c">{{ $i + 1 }}</td>
      <td>{{ strtoupper(($e->apellido_emp ?? '') . ' ' . ($e->nombre_emp ?? '')) }}</td>
      <td class="c">{{ $e->identificacion ?? '' }}</td>
      <td>{{ $e->departamento->nombre_depto ?? '' }}</td>
      <td class="r">{{ number_format($r->sbu, 2) }}</td>
      <td class="c">{{ $r->dias }}</td>
      <td class="r">{{ number_format($r->valor, 2) }}</td>
    </tr>
    @endforeach
  </tbody>
  <tfoot>
    <tr class="total-row">
      <td colspan="4" style="text-align:right; padding-right:8px;">TOTAL ({{ $totalEmp }} servidores)</td>
      <td></td>
      <td></td>
      <td class="r">{{ number_format($totalValor, 2) }}</td>
    </tr>
  </tfoot>
</table>

<p style="font-size:7.5pt; color:#555; margin-top:8px; text-align:right;">
  Generado el {{ now()->format('d/m/Y H:i') }}
</p>

</body>
</html>
