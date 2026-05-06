<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, sans-serif; font-size: 7pt; color: #000; }
  @page { margin: 10mm 12mm 10mm 12mm; size: letter landscape; }
  table { border-collapse: collapse; width: 100%; table-layout: fixed; }

  .title { text-align: center; font-weight: bold; font-size: 10pt; text-transform: uppercase; padding: 3px 0; }
  .subtitle { text-align: center; font-size: 8pt; margin-bottom: 8px; }

  .data-table th {
    background-color: #d0d0d0; border: 1px solid #000;
    padding: 4px 3px; font-size: 7pt; font-weight: bold; text-align: center;
  }
  .data-table td {
    border: 1px solid #000; padding: 3px 4px; font-size: 7pt; vertical-align: middle;
  }
  .data-table td.c { text-align: center; }
  .data-table td.r { text-align: right; }
  .data-table tr.total-row td { font-weight: bold; background-color: #efefef; }
  .estado-badge { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 6.5pt; font-weight: bold; }
  .borrador { background: #fef3c7; color: #b45309; }
  .cerrado  { background: #d1fae5; color: #065f46; }
</style>
</head>
<body>

@php
  $nombreInst = \App\Models\Configuracion::where('concepto','nombre_institucion')->value('valor') ?? 'CONSEJO DE COMUNICACIÓN';
  $totalBruto      = $detalles->sum('valor_rmu');
  $totalPatronal   = $detalles->sum('aporte_patronal');
  $totalPersonal   = $detalles->sum('aporte_personal');
  $totalQuirogr    = $detalles->sum('quirografario');
  $totalHipotec    = $detalles->sum('hipotecario');
  $totalImpRenta   = $detalles->sum('impuesto_renta');
  $totalSupa       = $detalles->sum('supa');
  $totalDescuentos = $detalles->sum('total_descuentos');
  $totalLiquido    = $detalles->sum('liquido');
  $pctPatronalUnicos = $detalles->pluck('aporte_patronal_pct')->unique()->values();
  $pctPersonalUnicos = $detalles->pluck('aporte_personal_pct')->unique()->values();
  $lblPatronal = 'Ap.Pat (' . $pctPatronalUnicos->sort()->map(fn($p) => $p . '%')->implode(' / ') . ')';
  $lblPersonal = 'Ap.Pers (' . $pctPersonalUnicos->sort()->map(fn($p) => $p . '%')->implode(' / ') . ')';
@endphp

<table style="margin-bottom:6px;">
  <tr>
    <td style="width:15%; text-align:center; vertical-align:middle;">
      @if($logo)
        <img src="{{ $logo }}" style="max-height:50px; max-width:75px;">
      @endif
    </td>
    <td style="text-align:center; vertical-align:middle;">
      <div style="font-size:9pt; font-weight:bold; text-transform:uppercase;">{{ $nombreInst }}</div>
      <div class="title" style="margin-top:3px;">ROL DE PAGOS</div>
      <div class="subtitle">
        Período: {{ $nombreMes }} {{ $anio }}
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <span class="estado-badge {{ strtolower($cab->estado) }}">{{ $cab->estado }}</span>
        &nbsp;&nbsp;|&nbsp;&nbsp;{{ $detalles->count() }} servidores
      </div>
    </td>
  </tr>
</table>

<table class="data-table">
  <thead>
    <tr>
      <th style="width:2.5%;">N°</th>
      <th style="width:7%;">Cédula</th>
      <th style="width:13%;">Apellidos y Nombres</th>
      <th style="width:9%;">Departamento</th>
      <th style="width:3%;">Días</th>
      <th style="width:6.5%;">RMU $</th>
      <th style="width:8%;">{{ $lblPatronal }}</th>
      <th style="width:8%;">{{ $lblPersonal }}</th>
      <th style="width:5.5%;">Quirogr.</th>
      <th style="width:5.5%;">Hipotec.</th>
      <th style="width:5.5%;">Imp.Renta</th>
      <th style="width:5%;">SUPA</th>
      <th style="width:7%;">T.Desc. $</th>
      <th style="width:7%;">Líquido $</th>
    </tr>
  </thead>
  <tbody>
    @foreach($detalles as $i => $r)
    <tr>
      <td class="c">{{ $i + 1 }}</td>
      <td class="c">{{ $r->identificacion ?? '' }}</td>
      <td>{{ strtoupper(($r->apellido_emp ?? '') . ' ' . ($r->nombre_emp ?? '')) }}</td>
      <td>{{ $r->nombre_depto ?? '' }}</td>
      <td class="c">{{ $r->dias }}</td>
      <td class="r">{{ number_format($r->valor_rmu, 2) }}</td>
      <td class="r">{{ number_format($r->aporte_patronal, 2) }}</td>
      <td class="r">{{ number_format($r->aporte_personal, 2) }}</td>
      <td class="r">{{ number_format($r->quirografario, 2) }}</td>
      <td class="r">{{ number_format($r->hipotecario, 2) }}</td>
      <td class="r">{{ number_format($r->impuesto_renta, 2) }}</td>
      <td class="r">{{ number_format($r->supa, 2) }}</td>
      <td class="r">{{ number_format($r->total_descuentos, 2) }}</td>
      <td class="r">{{ number_format($r->liquido, 2) }}</td>
    </tr>
    @endforeach
  </tbody>
  <tfoot>
    <tr class="total-row">
      <td colspan="5" style="text-align:right; padding-right:6px;">TOTAL ({{ $detalles->count() }} servidores)</td>
      <td class="r">{{ number_format($totalBruto, 2) }}</td>
      <td class="r">{{ number_format($totalPatronal, 2) }}</td>
      <td class="r">{{ number_format($totalPersonal, 2) }}</td>
      <td class="r">{{ number_format($totalQuirogr, 2) }}</td>
      <td class="r">{{ number_format($totalHipotec, 2) }}</td>
      <td class="r">{{ number_format($totalImpRenta, 2) }}</td>
      <td class="r">{{ number_format($totalSupa, 2) }}</td>
      <td class="r">{{ number_format($totalDescuentos, 2) }}</td>
      <td class="r">{{ number_format($totalLiquido, 2) }}</td>
    </tr>
  </tfoot>
</table>

<p style="font-size:6.5pt; color:#555; text-align:right; margin-top:8px;">
  Generado el {{ now()->format('d/m/Y H:i') }} &nbsp;|&nbsp; Generado por: {{ $generadoPor }}
</p>

</body>
</html>
