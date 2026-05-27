<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, sans-serif; font-size: 6.5pt; color: #000; }
  @page { margin: 10mm 10mm 10mm 10mm; size: a4 landscape; }
  table { border-collapse: collapse; width: 100%; table-layout: fixed; }

  .title { text-align: center; font-weight: bold; font-size: 9.5pt; text-transform: uppercase; padding: 3px 0; }
  .subtitle { text-align: center; font-size: 7.5pt; margin-bottom: 8px; }

  .data-table th {
    background-color: #d0d0d0; border: 1px solid #000;
    padding: 3px 2px; font-size: 6pt; font-weight: bold; text-align: center;
  }
  .data-table td {
    border: 1px solid #000; padding: 2px 3px; font-size: 6.5pt; vertical-align: middle;
  }
  .data-table td.c { text-align: center; }
  .data-table td.r { text-align: right; }
  .data-table tr.total-row td { font-weight: bold; background-color: #efefef; }
  .estado-badge { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 6pt; font-weight: bold; }
  .borrador { background: #fef3c7; color: #b45309; }
  .cerrado  { background: #d1fae5; color: #065f46; }
  .th-patronal { background-color: #c6d9f1; }
  .th-descuento { background-color: #fce4d6; }
</style>
</head>
<body>

@php
  $nombreInst = \App\Models\Configuracion::where('concepto','nombre_institucion')->value('valor') ?? 'CONSEJO DE COMUNICACIÓN';

  // Totales simples
  $totalBruto      = $detalles->sum('valor_rmu');
  $totalIece       = $detalles->sum('iece');
  $totalSecap      = $detalles->sum('secap');
  $totalPatBase    = $detalles->sum('aporte_patronal');
  $totalPatronal   = $totalIece + $totalSecap + $totalPatBase;
  $totalPersonal   = $detalles->sum('aporte_personal');
  $totalQuirogr    = $detalles->sum('quirografario');
  $totalHipotec    = $detalles->sum('hipotecario');
  $totalImpRenta   = $detalles->sum('impuesto_renta');
  $totalSupa       = $detalles->sum('supa');
  $totalPolBlanket = $detalles->sum('poliza_blanket');
  $totalDescuentos = $detalles->sum('total_descuentos');
  $totalLiquido    = $detalles->sum('liquido');

  // Encabezados dinámicos aporte patronal base (LOSEP/CT)
  $pctPatLosep = $detalles->where('tipo_contrato', 'LOSEP')->first()?->aporte_patronal_pct ?? 0;
  $pctPatCT    = $detalles->where('tipo_contrato', 'CODIGO DEL TRABAJO')->first()?->aporte_patronal_pct ?? 0;
  $lblPatBase  = 'AP. PATRONAL' . PHP_EOL;
  if ($pctPatLosep && $pctPatCT)  $lblPatBase .= "LOSEP {$pctPatLosep}% / CdT {$pctPatCT}%";
  elseif ($pctPatLosep)           $lblPatBase .= "LOSEP {$pctPatLosep}%";
  elseif ($pctPatCT)              $lblPatBase .= "CdT {$pctPatCT}%";

  $pctIece    = $detalles->first()?->iece_pct ?? 0;
  $pctSecapCT = $detalles->where('secap', '>', 0)->first()?->secap_pct ?? 0;

  // Encabezado aporte personal
  $pctPersonalUnicos = $detalles->pluck('aporte_personal_pct')->unique()->values();
  $lblPersonal = 'AP. PERSONAL (' . $pctPersonalUnicos->sort()->map(fn($p) => $p . '%')->implode(' / ') . ')';
@endphp

<table style="margin-bottom:6px;">
  <tr>
    <td style="width:13%; text-align:center; vertical-align:middle;">
      @if($logo)
        <img src="{{ $logo }}" style="max-height:90px; max-width:130px;">
      @endif
    </td>
    <td style="text-align:center; vertical-align:middle;">
      <div style="font-size:8.5pt; font-weight:bold; text-transform:uppercase;">{{ $nombreInst }}</div>
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
      <th style="width:2%;">N°</th>
      <th style="width:6%;">Cédula</th>
      <th style="width:11%;">Apellidos y Nombres</th>
      <th style="width:7.5%;">Departamento</th>
      <th style="width:2.5%;">Prog.</th>
      <th style="width:3%;">Act.</th>
      <th style="width:2.5%;">Días</th>
      <th style="width:5.5%;">RMU $</th>
      {{-- Aportes patronales --}}
      <th class="th-patronal" style="width:5%;">IECE<br>({{ $pctIece }}%)</th>
      <th class="th-patronal" style="width:5.5%;">SECAP<br>LOSEP 0% / CdT {{ $pctSecapCT }}%</th>
      <th class="th-patronal" style="width:7%;">{!! nl2br(e($lblPatBase)) !!}</th>
      <th class="th-patronal" style="width:5.5%;">TOTAL<br>AP. PATRONAL</th>
      {{-- Descuentos --}}
      <th class="th-descuento" style="width:6%;">{{ $lblPersonal }}</th>
      <th class="th-descuento" style="width:4.5%;">Quirogr.</th>
      <th class="th-descuento" style="width:4.5%;">Hipotec.</th>
      <th class="th-descuento" style="width:4.5%;">Imp.Renta</th>
      <th class="th-descuento" style="width:3.5%;">SUPA</th>
      <th class="th-descuento" style="width:5%;">Póliza Blanket</th>
      <th style="width:5%;">T.Desc. $</th>
      <th style="width:5.5%;">Líquido $</th>
    </tr>
  </thead>
  <tbody>
    @foreach($detalles as $i => $r)
    <tr>
      <td class="c">{{ $i + 1 }}</td>
      <td class="c">{{ $r->identificacion ?? '' }}</td>
      <td>{{ strtoupper(($r->apellido_emp ?? '') . ' ' . ($r->nombre_emp ?? '')) }}</td>
      <td>{{ $r->nombre_depto ?? '' }}</td>
      <td class="c">{{ $r->programa ?? '—' }}</td>
      <td class="c">{{ $r->actividad ?? '—' }}</td>
      <td class="c">{{ $r->dias }}</td>
      <td class="r">{{ number_format($r->valor_rmu, 2) }}</td>
      <td class="r">{{ number_format($r->iece, 2) }}</td>
      <td class="r">{{ number_format($r->secap, 2) }}</td>
      <td class="r">{{ number_format($r->aporte_patronal, 2) }}</td>
      <td class="r" style="font-weight:bold;">{{ number_format($r->iece + $r->secap + $r->aporte_patronal, 2) }}</td>
      <td class="r">{{ number_format($r->aporte_personal, 2) }}</td>
      <td class="r">{{ number_format($r->quirografario, 2) }}</td>
      <td class="r">{{ number_format($r->hipotecario, 2) }}</td>
      <td class="r">{{ number_format($r->impuesto_renta, 2) }}</td>
      <td class="r">{{ number_format($r->supa, 2) }}</td>
      <td class="r">{{ number_format($r->poliza_blanket, 2) }}</td>
      <td class="r">{{ number_format($r->total_descuentos, 2) }}</td>
      <td class="r">{{ number_format($r->liquido, 2) }}</td>
    </tr>
    @endforeach
  </tbody>
  <tfoot>
    <tr class="total-row">
      <td colspan="7" style="text-align:right; padding-right:6px;">TOTAL ({{ $detalles->count() }} servidores)</td>
      <td class="r">{{ number_format($totalBruto, 2) }}</td>
      <td class="r">{{ number_format($totalIece, 2) }}</td>
      <td class="r">{{ number_format($totalSecap, 2) }}</td>
      <td class="r">{{ number_format($totalPatBase, 2) }}</td>
      <td class="r" style="font-weight:bold;">{{ number_format($totalPatronal, 2) }}</td>
      <td class="r">{{ number_format($totalPersonal, 2) }}</td>
      <td class="r">{{ number_format($totalQuirogr, 2) }}</td>
      <td class="r">{{ number_format($totalHipotec, 2) }}</td>
      <td class="r">{{ number_format($totalImpRenta, 2) }}</td>
      <td class="r">{{ number_format($totalSupa, 2) }}</td>
      <td class="r">{{ number_format($totalPolBlanket, 2) }}</td>
      <td class="r">{{ number_format($totalDescuentos, 2) }}</td>
      <td class="r">{{ number_format($totalLiquido, 2) }}</td>
    </tr>
  </tfoot>
</table>

<p style="font-size:6pt; color:#555; text-align:right; margin-top:8px;">
  Generado el: {{ now()->format('d/m/Y H:i') }} &nbsp;|&nbsp; Generado por: {{ strtoupper($generadoPor) }}
</p>

</body>
</html>
