<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, sans-serif; font-size: 9pt; color: #000; margin: 2cm; }
  @page { size: a4 portrait; margin: 0; }
  table { border-collapse: collapse; width: 100%; table-layout: fixed; }

  .section-title { font-weight: bold; font-size: 9pt; text-transform: uppercase;
    background-color: #5c4a6e; color: #fff; padding: 4px 8px; margin: 10px 0 4px 0; }

  .info-table td { border: 1px solid #555; padding: 5px 8px; font-size: 8.5pt; vertical-align: top; }
  .info-label { font-weight: bold; background-color: #ede9f3; width: 30%; }

  .firmas td { border: 1px solid #555; padding: 50px 8px 6px 8px; text-align: center;
    font-weight: bold; font-size: 8pt; text-transform: uppercase; background-color: #ede9f3; }

  .gen-line { font-size: 7.5pt; color: #555; margin-top: 12px; text-align: right; }
</style>
</head>
<body>

@php
  $meses = ['','enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
  $firmanteAut = \Illuminate\Support\Facades\DB::table('dbo.d2_configuracion')->whereRaw("LOWER(concepto) = 'firmante_autoridad_nombre'")->value('valor') ?? 'MÁXIMA AUTORIDAD';
  $firmanteAutCargo = \Illuminate\Support\Facades\DB::table('dbo.d2_configuracion')->whereRaw("LOWER(concepto) = 'firmante_autoridad_cargo'")->value('valor') ?? 'AUTORIDAD NOMINADORA';
  $supervisorDepto = \Illuminate\Support\Facades\DB::table('dbo.supervisor_area as sa')
    ->join('dbo.ad_empleado as e', 'e.id_emp', '=', 'sa.id_supervisor')
    ->where('sa.id_depto', $solicitud->id_depto)
    ->orderBy('sa.id')   // áreas con 2 supervisores: el principal (primero registrado)
    ->select('e.apellido_emp', 'e.nombre_emp', 'e.cargo_empleado')
    ->first();
@endphp

<table style="width:100%; margin-bottom:10px; border-collapse:collapse;">
  <tr>
    <td style="width:15%; vertical-align:middle; text-align:center;">
      @if($logo)<img src="data:image/png;base64,{{ $logo }}" style="max-height:80px; max-width:110px;">@endif
    </td>
    <td style="text-align:center; vertical-align:middle; padding:0 10px;">
      <div style="font-size:11pt; font-weight:bold; text-transform:uppercase;">{{ $nombreInst }}</div>
      <div style="font-size:10pt; font-weight:bold; text-transform:uppercase; margin-top:3px;">INFORME DE CUMPLIMIENTO DE COMISIÓN DE SERVICIOS AL INTERIOR</div>
      <div style="font-size:9pt; margin-top:2px;">N° {{ $solicitud->numero_solicitud ?? '___________________' }}</div>
    </td>
  </tr>
</table>

<div class="section-title">Datos del Servidor</div>
<table class="info-table" style="margin-bottom:6px;">
  <tr>
    <td class="info-label">Apellidos y Nombres</td>
    <td colspan="3">{{ strtoupper(($solicitud->empleado->apellido_emp ?? '') . ' ' . ($solicitud->empleado->nombre_emp ?? '')) }}</td>
  </tr>
  <tr>
    <td class="info-label">Unidad Administrativa</td>
    <td>{{ strtoupper($solicitud->unidad_nombre ?? '') }}</td>
    <td class="info-label" style="width:25%;">Fecha del Informe</td>
    <td>{{ \Carbon\Carbon::parse($informe->fecha_informe)->format('d/m/Y') }}</td>
  </tr>
</table>

<div class="section-title">Datos del Viaje</div>
<table class="info-table" style="margin-bottom:6px;">
  <tr>
    <td class="info-label">Destino</td>
    <td colspan="3">{{ strtoupper($informe->destino ?? $solicitud->destino) }}</td>
  </tr>
  <tr>
    <td class="info-label">Fecha y Hora de Salida</td>
    <td>{{ \Carbon\Carbon::parse($informe->fecha_salida)->format('d/m/Y') }} {{ $informe->hora_salida }}</td>
    <td class="info-label" style="width:25%;">Fecha y Hora de Regreso</td>
    <td>{{ \Carbon\Carbon::parse($informe->fecha_llegada)->format('d/m/Y') }} {{ $informe->hora_llegada }}</td>
  </tr>
</table>

@if($informe->transportes->isNotEmpty())
<div class="section-title">Itinerario de Transporte Utilizado</div>
<table class="info-table" style="margin-bottom:6px;">
  <thead>
    <tr style="background-color:#ede9f3; font-weight:bold; text-align:center;">
      <td style="width:15%;">Tipo</td>
      <td style="width:20%;">Empresa</td>
      <td style="width:25%;">Ruta</td>
      <td style="width:20%;">Salida</td>
      <td style="width:20%;">Llegada</td>
    </tr>
  </thead>
  <tbody>
    @foreach($informe->transportes as $trn)
    <tr>
      <td style="text-align:center;">{{ $trn->tipo }}</td>
      <td>{{ $trn->nombre }}</td>
      <td>{{ $trn->ruta }}</td>
      <td style="text-align:center;">{{ $trn->salida_fecha }} {{ $trn->salida_hora }}</td>
      <td style="text-align:center;">{{ $trn->llegada_fecha }} {{ $trn->llegada_hora }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
@endif

<div class="section-title">Actividades Realizadas</div>
<table class="info-table" style="margin-bottom:6px;">
  <tr>
    <td style="white-space:pre-line; min-height:80px;">{{ $informe->actividades }}</td>
  </tr>
</table>

@if($informe->productos)
<div class="section-title">Productos / Resultados Obtenidos</div>
<table class="info-table" style="margin-bottom:6px;">
  <tr>
    <td style="white-space:pre-line;">{{ $informe->productos }}</td>
  </tr>
</table>
@endif

@if($informe->observacion)
<div class="section-title">Observaciones</div>
<table class="info-table" style="margin-bottom:6px;">
  <tr>
    <td style="white-space:pre-line;">{{ $informe->observacion }}</td>
  </tr>
</table>
@endif

<div style="margin-top: 30px;">
<table class="firmas">
  <tr>
    <td style="width:40%;">
      {{ strtoupper(($solicitud->empleado->apellido_emp ?? '') . ' ' . ($solicitud->empleado->nombre_emp ?? '')) }}<br>
      {{ strtoupper($solicitud->empleado->cargo_empleado ?? '') }}<br>SERVIDOR COMISIONADO
    </td>
    <td style="width:2%;border:none;"></td>
    <td style="width:40%;">
      @if($supervisorDepto) {{ strtoupper($supervisorDepto->apellido_emp . ' ' . $supervisorDepto->nombre_emp) }}<br>{{ strtoupper($supervisorDepto->cargo_empleado ?? '') }}<br> @endif
      JEFE INMEDIATO
    </td>
  </tr>
</table>
</div>

<p class="gen-line">Generado por: {{ strtoupper($generadoPor) }} &nbsp;&nbsp;|&nbsp;&nbsp; {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
