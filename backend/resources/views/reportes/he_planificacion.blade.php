<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, sans-serif; font-size: 9pt; color: #000; }
  .page { width: 100%; }

  @page { margin: 12mm 15mm 12mm 15mm; size: letter portrait; }

  table { border-collapse: collapse; width: 100%; table-layout: fixed; }

  .title-section {
    text-align: center;
    font-weight: bold;
    font-size: 11pt;
    text-transform: uppercase;
    padding: 4px 0;
  }
  .subtitle {
    text-align: center;
    font-size: 9pt;
    margin-bottom: 10px;
  }

  .info-table td {
    padding: 3px 6px;
    font-size: 8.5pt;
    vertical-align: top;
  }
  .info-label { font-weight: bold; width: 28%; }
  .info-value { width: 22%; }

  .he-table th {
    background-color: #d8d8d8;
    border: 1px solid #000;
    padding: 5px 4px;
    font-size: 8.5pt;
    font-weight: bold;
    text-align: center;
  }
  .he-table td {
    border: 1px solid #000;
    padding: 4px 6px;
    font-size: 8.5pt;
    vertical-align: top;
  }
  .he-table td.num {
    text-align: center;
  }
  .he-table tr.total-row td {
    font-weight: bold;
    background-color: #efefef;
    text-align: center;
  }
  .he-table tr.total-row td:first-child {
    text-align: right;
  }

  .firma-table td {
    padding: 6px 10px;
    font-size: 8pt;
    text-align: center;
    vertical-align: bottom;
  }
  .firma-linea {
    border-top: 1px solid #000;
    margin: 0 10px;
    padding-top: 3px;
    font-weight: bold;
    font-size: 8pt;
  }
  .nota { font-size: 7.5pt; color: #444; margin-top: 6px; }
</style>
</head>
<body>

@php
  $nombreMes   = $meses[$cab->mes] ?? $cab->mes;
  $nombreInst  = $config['nombre_institucion'] ?? 'CONSEJO DE COMUNICACIÓN';
  $directorTH  = $config['DIRECTOR_TALENTO_HUMANO'] ?? '';

  $nombreEmp = strtoupper(($emp->apellido_emp ?? '') . ' ' . ($emp->nombre_emp ?? ''));
  $cargo     = $emp->cargo_empleado ?? '';
  $depto     = $emp->departamento->nombre_depto ?? '';
  $tipoContr = $emp->tipo_contrato ?? '';

  $porcExtra = $jornada ? (float)$jornada->porc_extraordinaria : 0;
  $porcSupl  = $jornada ? (float)$jornada->porc_suplementaria  : 0;

  $sueldo       = (float)($emp->sueldo ?? 0);
  $tarifaHora   = $sueldo > 0 ? $sueldo / 240 : 0;
  $valorExtra   = $tarifaHora * (1 + $porcExtra / 100);
  $valorSupl    = $tarifaHora * (1 + $porcSupl  / 100);
  $pagoExtra    = $cab->total_extraordinarias * $valorExtra;
  $pagoSupl     = $cab->total_suplementarias  * $valorSupl;
  $pagoTotal    = $pagoExtra + $pagoSupl;
@endphp

<div class="page">

{{-- ENCABEZADO --}}
<table style="margin-bottom:8px;">
  <tr>
    <td style="width:18%; text-align:center; vertical-align:middle;">
      @if($logo)
        <img src="{{ $logo }}" style="max-height:55px; max-width:80px;">
      @endif
    </td>
    <td style="width:64%; text-align:center; vertical-align:middle; padding:4px;">
      <div style="font-size:9pt; font-weight:bold; text-transform:uppercase;">
        {{ $nombreInst }}
      </div>
      <div style="font-size:8pt; margin-top:2px;">DIRECCIÓN ADMINISTRATIVA DE TALENTO HUMANO</div>
      <div class="title-section" style="margin-top:5px;">PLANIFICACIÓN DE HORAS EXTRAS</div>
      <div class="subtitle">Mes: {{ $nombreMes }} &nbsp;|&nbsp; Año: {{ $cab->anio }}</div>
    </td>
    <td style="width:18%; text-align:right; vertical-align:top; padding:4px; font-size:7.5pt;">
      <div><b>Estado:</b> {{ $cab->estado }}</div>
      @if($cab->fecha_registro)
      <div style="margin-top:2px;"><b>Fecha:</b> {{ \Carbon\Carbon::parse($cab->fecha_registro)->format('d/m/Y') }}</div>
      @endif
    </td>
  </tr>
</table>

{{-- DATOS DEL EMPLEADO --}}
<table class="info-table" style="border: 1px solid #000; margin-bottom:10px;">
  <tr>
    <td style="background:#d8d8d8; font-weight:bold; font-size:8pt;" colspan="4">DATOS DEL SERVIDOR/A PÚBLICO/A</td>
  </tr>
  <tr>
    <td class="info-label">Apellidos y Nombres:</td>
    <td class="info-value" colspan="3">{{ $nombreEmp }}</td>
  </tr>
  <tr>
    <td class="info-label">Cédula de Identidad:</td>
    <td class="info-value">{{ $emp->identificacion }}</td>
    <td class="info-label">Cargo:</td>
    <td class="info-value">{{ $cargo }}</td>
  </tr>
  <tr>
    <td class="info-label">Unidad / Departamento:</td>
    <td class="info-value">{{ $depto }}</td>
    <td class="info-label">Tipo de Contrato:</td>
    <td class="info-value">{{ $tipoContr }}</td>
  </tr>
  <tr>
    <td class="info-label">Remuneración Mensual:</td>
    <td class="info-value">$ {{ number_format($sueldo, 2) }}</td>
    <td class="info-label">Tarifa por Hora:</td>
    <td class="info-value">$ {{ number_format($tarifaHora, 4) }}</td>
  </tr>
</table>

{{-- TABLA DE ACTIVIDADES --}}
<table class="he-table" style="margin-bottom:10px;">
  <thead>
    <tr>
      <th style="width:5%;">N°</th>
      <th style="width:55%;">Actividad a Realizar</th>
      <th style="width:20%;">Horas Extraordinarias</th>
      <th style="width:20%;">Horas Suplementarias</th>
    </tr>
  </thead>
  <tbody>
    @forelse($cab->detalles as $i => $det)
    <tr>
      <td class="num">{{ $i + 1 }}</td>
      <td>{{ $det->actividad }}</td>
      <td class="num">{{ $det->horas_extraordinarias > 0 ? number_format($det->horas_extraordinarias, 2) : '-' }}</td>
      <td class="num">{{ $det->horas_suplementarias > 0 ? number_format($det->horas_suplementarias, 2) : '-' }}</td>
    </tr>
    @empty
    <tr>
      <td class="num">-</td>
      <td colspan="3" style="text-align:center;">Sin actividades registradas</td>
    </tr>
    @endforelse
    <tr class="total-row">
      <td colspan="2">TOTAL HORAS PLANIFICADAS</td>
      <td>{{ number_format($cab->total_extraordinarias, 2) }}</td>
      <td>{{ number_format($cab->total_suplementarias, 2) }}</td>
    </tr>
  </tbody>
</table>

{{-- PORCENTAJES Y CÁLCULO REFERENCIAL --}}
<table style="border:1px solid #ccc; margin-bottom:10px; font-size:8pt;">
  <tr>
    <td style="background:#efefef; font-weight:bold; padding:4px 8px;" colspan="4">
      PORCENTAJES APLICABLES ({{ $tipoContr }})
    </td>
  </tr>
  <tr>
    <td style="padding:3px 8px; width:40%;"><b>H. Extraordinarias:</b> Fuera de jornada hasta 11:59 PM → {{ $porcExtra }}%</td>
    <td style="padding:3px 8px; width:25%;"><b>Tarifa c/recargo:</b> $ {{ number_format($valorExtra, 4) }}/h</td>
    <td style="padding:3px 8px; width:20%;"><b>Horas planif.:</b> {{ number_format($cab->total_extraordinarias, 2) }} h</td>
    <td style="padding:3px 8px; width:15%;"><b>Subtotal:</b> $ {{ number_format($pagoExtra, 2) }}</td>
  </tr>
  <tr>
    <td style="padding:3px 8px;"><b>H. Suplementarias:</b> Desde 00:00 hasta inicio jornada / fines → {{ $porcSupl }}%</td>
    <td style="padding:3px 8px;"><b>Tarifa c/recargo:</b> $ {{ number_format($valorSupl, 4) }}/h</td>
    <td style="padding:3px 8px;"><b>Horas planif.:</b> {{ number_format($cab->total_suplementarias, 2) }} h</td>
    <td style="padding:3px 8px;"><b>Subtotal:</b> $ {{ number_format($pagoSupl, 2) }}</td>
  </tr>
  <tr>
    <td colspan="3" style="text-align:right; font-weight:bold; padding:4px 8px;">TOTAL REFERENCIAL A PAGAR:</td>
    <td style="font-weight:bold; padding:4px 8px;">$ {{ number_format($pagoTotal, 2) }}</td>
  </tr>
</table>

<p class="nota">* El total referencial es un valor estimado basado en las horas planificadas. El valor real depende de las horas efectivamente trabajadas y confirmadas.</p>
<p class="nota">* Base de cálculo: Remuneración mensual / 240 horas (30 días × 8 horas)</p>

{{-- FIRMAS --}}
<table class="firma-table" style="margin-top:30px;">
  <tr>
    <td style="width:50%;">
      <div style="margin-bottom:25px;">&nbsp;</div>
      <div class="firma-linea">
        {{ $nombreEmp }}<br>
        {{ $cargo }}<br>
        <span style="font-weight:normal;">Servidor/a que solicita</span>
      </div>
    </td>
    <td style="width:50%;">
      <div style="margin-bottom:25px;">&nbsp;</div>
      <div class="firma-linea">
        {{ strtoupper($directorTH) }}<br>
        DIRECTOR/A DE TALENTO HUMANO<br>
        <span style="font-weight:normal;">Autoriza</span>
      </div>
    </td>
  </tr>
</table>

</div>
</body>
</html>
