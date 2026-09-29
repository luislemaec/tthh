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

  .check-td { text-align: center; padding: 6px 4px; font-size: 8.5pt; }
  .check-box { display: inline-block; width: 12px; height: 12px; border: 1.5px solid #555;
    text-align: center; line-height: 11px; font-size: 9pt; margin-right: 3px; vertical-align: middle; }

  .firmas td { border: 1px solid #555; padding: 50px 8px 6px 8px; text-align: center;
    font-weight: bold; font-size: 8pt; text-transform: uppercase; }

  .gen-line { font-size: 7.5pt; color: #555; margin-top: 12px; text-align: right; }
</style>
</head>
<body>

@php
  $meses = ['','enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
  $fechaSol = $solicitud->fecha_solicitud ? \Carbon\Carbon::parse($solicitud->fecha_solicitud) : null;
  $firmanteTh   = \Illuminate\Support\Facades\DB::table('dbo.d2_configuracion')->whereRaw("LOWER(concepto) = 'firmante_th_nombre'")->value('valor') ?? 'RESPONSABLE TALENTO HUMANO';
  $firmanteAut  = \Illuminate\Support\Facades\DB::table('dbo.d2_configuracion')->whereRaw("LOWER(concepto) = 'firmante_autoridad_nombre'")->value('valor') ?? 'MÁXIMA AUTORIDAD';
  $firmanteAutCargo = \Illuminate\Support\Facades\DB::table('dbo.d2_configuracion')->whereRaw("LOWER(concepto) = 'firmante_autoridad_cargo'")->value('valor') ?? 'AUTORIDAD NOMINADORA';
@endphp

{{-- ENCABEZADO --}}
<table style="width:100%; margin-bottom:10px; border-collapse:collapse;">
  <tr>
    <td style="width:15%; vertical-align:middle; text-align:center;">
      @if($logo)<img src="data:image/png;base64,{{ $logo }}" style="max-height:80px; max-width:110px;">@endif
    </td>
    <td style="text-align:center; vertical-align:middle; padding:0 10px;">
      <div style="font-size:11pt; font-weight:bold; text-transform:uppercase;">{{ $nombreInst }}</div>
      <div style="font-size:10pt; font-weight:bold; text-transform:uppercase; margin-top:3px;">SOLICITUD DE COMISIÓN DE SERVICIOS AL INTERIOR</div>
      <div style="font-size:9pt; margin-top:2px;">
        N° {{ $solicitud->numero_solicitud ?? '___________________' }}
        &nbsp;&nbsp;|&nbsp;&nbsp;
        @if($fechaSol) {{ $fechaSol->day }} de {{ $meses[$fechaSol->month] }} de {{ $fechaSol->year }} @endif
      </div>
    </td>
  </tr>
</table>

<div class="section-title">Datos del Servidor / Servidores Públicos</div>
<table class="info-table" style="margin-bottom:6px;">
  @foreach($solicitud->servidores as $srv)
  <tr>
    <td class="info-label">Apellidos y Nombres</td>
    <td colspan="3">{{ strtoupper(($srv->empleado->apellido_emp ?? '') . ' ' . ($srv->empleado->nombre_emp ?? '')) }}</td>
  </tr>
  <tr>
    <td class="info-label">Unidad Administrativa</td>
    <td>{{ strtoupper($srv->unidad ?? '') }}</td>
    <td class="info-label" style="width:20%;">Puesto</td>
    <td>{{ strtoupper($srv->puesto ?? '') }}</td>
  </tr>
  @endforeach
</table>

{{-- Beneficios --}}
<div class="section-title">Beneficios Solicitados</div>
<table class="info-table" style="margin-bottom:6px;">
  <tr>
    <td class="check-td" style="width:33%;">
      <span class="check-box">{!! $solicitud->tiene_viaticos ? 'X' : '&nbsp;' !!}</span> Viáticos
    </td>
    <td class="check-td" style="width:33%;">
      <span class="check-box">{!! $solicitud->tiene_movilizaciones ? 'X' : '&nbsp;' !!}</span> Movilizaciones
    </td>
    <td class="check-td" style="width:34%;">
      <span class="check-box">{!! $solicitud->tiene_anticipo ? 'X' : '&nbsp;' !!}</span> Anticipo de Viáticos
    </td>
  </tr>
</table>

<div class="section-title">Datos de la Comisión</div>
<table class="info-table" style="margin-bottom:6px;">
  <tr>
    <td class="info-label">Destino</td>
    <td colspan="3">{{ strtoupper($solicitud->destino) }}</td>
  </tr>
  <tr>
    <td class="info-label">Fecha de Salida</td>
    <td>{{ \Carbon\Carbon::parse($solicitud->fecha_salida)->format('d/m/Y') }} {{ $solicitud->hora_salida }}</td>
    <td class="info-label" style="width:25%;">Fecha de Regreso</td>
    <td>{{ \Carbon\Carbon::parse($solicitud->fecha_llegada)->format('d/m/Y') }} {{ $solicitud->hora_llegada }}</td>
  </tr>
  <tr>
    <td class="info-label">Actividades a Realizar</td>
    <td colspan="3" style="white-space:pre-line;">{{ $solicitud->descripcion_actividades }}</td>
  </tr>
</table>

{{-- Transporte --}}
@if($solicitud->transportes->isNotEmpty())
<div class="section-title">Itinerario de Transporte</div>
<table class="info-table" style="margin-bottom:6px;">
  <thead>
    <tr style="background-color:#ede9f3; font-weight:bold; text-align:center;">
      <td style="width:15%;">Tipo</td>
      <td style="width:20%;">Empresa / N° Vuelo</td>
      <td style="width:25%;">Ruta</td>
      <td style="width:20%;">Salida</td>
      <td style="width:20%;">Llegada</td>
    </tr>
  </thead>
  <tbody>
    @foreach($solicitud->transportes as $trn)
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

{{-- Datos bancarios --}}
@if($solicitud->banco || $solicitud->numero_cuenta)
<div class="section-title">Datos Bancarios para Pago</div>
<table class="info-table" style="margin-bottom:6px;">
  <tr>
    <td class="info-label">Banco</td>
    <td>{{ $solicitud->banco }}</td>
    <td class="info-label" style="width:20%;">Tipo de Cuenta</td>
    <td>{{ $solicitud->tipo_cuenta }}</td>
  </tr>
  <tr>
    <td class="info-label">Número de Cuenta</td>
    <td colspan="3">{{ $solicitud->numero_cuenta }}</td>
  </tr>
</table>
@endif

{{-- Firmas --}}
<div style="margin-top: 30px;">
<table class="firmas">
  <tr>
    <td style="width:40%;">{{ strtoupper($solicitud->empleado->apellido_emp ?? '') }} {{ strtoupper($solicitud->empleado->nombre_emp ?? '') }}<br>SERVIDOR COMISIONADO</td>
    <td style="width:2%;border:none;"></td>
    <td style="width:40%;">{{ strtoupper($firmanteAut) }}<br>{{ strtoupper($firmanteAutCargo) }}</td>
  </tr>
</table>
</div>

<p class="gen-line">Generado por: {{ strtoupper($generadoPor) }} &nbsp;&nbsp;|&nbsp;&nbsp; {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
