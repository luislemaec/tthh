<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, sans-serif; font-size: 9pt; color: #000; }
  @page { margin: 12mm 15mm 12mm 15mm; size: letter portrait; }
  table { border-collapse: collapse; width: 100%; }

  .title { text-align: center; font-weight: bold; font-size: 11pt; text-transform: uppercase; padding: 4px 0; }
  .subtitle { text-align: center; font-size: 9pt; margin-bottom: 10px; }

  .info-table td { border: 1px solid #555; padding: 5px 8px; font-size: 8.5pt; }
  .info-label { font-weight: bold; background-color: #e5e7eb; width: 35%; }

  .section-title { font-weight: bold; font-size: 9pt; text-transform: uppercase;
                   background-color: #1e3a5f; color: #fff; padding: 4px 8px; margin: 10px 0 4px 0; }

  .firmas td { border: 1px solid #555; padding: 40px 8px 6px 8px; text-align: center;
               font-weight: bold; font-size: 8pt; text-transform: uppercase;
               background-color: #e5e7eb; width: 33%; }
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
        <img src="{{ $logo }}" style="max-height:110px; max-width:160px;">
      @endif
    </td>
    <td style="text-align:center; vertical-align:middle;">
      <div style="font-size:10pt; font-weight:bold; text-transform:uppercase;">{{ $nombreInst }}</div>
      <div class="title" style="margin-top:4px;">ORDEN DE MOVILIZACIÓN</div>
      <div class="subtitle">N° {{ $s->id }} &nbsp;&nbsp;|&nbsp;&nbsp; Estado: {{ $s->estado }}</div>
    </td>
  </tr>
</table>

<div class="section-title">Datos de la Solicitud</div>
<table class="info-table" style="margin-bottom:6px;">
  <tr>
    <td class="info-label">Solicitante</td>
    <td colspan="3">{{ strtoupper(($s->solicitante->apellido_emp ?? '') . ' ' . ($s->solicitante->nombre_emp ?? '')) }}</td>
  </tr>
  <tr>
    <td class="info-label">Motivo</td>
    <td colspan="3">{{ $s->motivo }}</td>
  </tr>
  <tr>
    <td class="info-label">Fecha de Movilización</td>
    <td>{{ \Carbon\Carbon::parse($s->fecha_movilizacion)->format('d/m/Y') }}</td>
    <td class="info-label">N° Personas</td>
    <td>{{ $s->num_personas }}</td>
  </tr>
  <tr>
    <td class="info-label">Hora de Salida</td>
    <td>{{ substr($s->hora_salida, 0, 5) }}</td>
    <td class="info-label">Hora de Retorno</td>
    <td>{{ substr($s->hora_retorno, 0, 5) }}</td>
  </tr>
  <tr>
    <td class="info-label">Lugar de Salida</td>
    <td>{{ $s->lugar_salida ?? '—' }}</td>
    <td class="info-label">Lugar de Destino</td>
    <td>{{ $s->lugar_destino }}</td>
  </tr>
</table>

<div class="section-title">Vehículo y Conductor Asignado</div>
<table class="info-table" style="margin-bottom:6px;">
  <tr>
    <td class="info-label">Placa</td>
    <td>{{ strtoupper($s->vehiculo->placa ?? '—') }}</td>
    <td class="info-label">Vehículo</td>
    <td>{{ ($s->vehiculo->marca ?? '') . ' ' . ($s->vehiculo->modelo ?? '') . ' ' . ($s->vehiculo->anio ?? '') }}</td>
  </tr>
  <tr>
    <td class="info-label">Conductor</td>
    <td colspan="3">{{ strtoupper(($s->conductor->apellido_emp ?? '—') . ' ' . ($s->conductor->nombre_emp ?? '')) }}</td>
  </tr>
  @if($s->observacion)
  <tr>
    <td class="info-label">Observación</td>
    <td colspan="3">{{ $s->observacion }}</td>
  </tr>
  @endif
</table>

@if($s->estado === 'COMPLETADO')
<div class="section-title">Hoja de Ruta</div>
<table class="info-table" style="margin-bottom:6px;">
  <tr>
    <td class="info-label">Km Salida</td>
    <td>{{ number_format($s->km_salida) }}</td>
    <td class="info-label">Km Retorno</td>
    <td>{{ number_format($s->km_retorno) }}</td>
  </tr>
  <tr>
    <td class="info-label">Km Recorridos</td>
    <td colspan="3"><strong>{{ number_format($s->km_retorno - $s->km_salida) }} km</strong></td>
  </tr>
  @if($s->hoja_ruta_observacion)
  <tr>
    <td class="info-label">Observaciones</td>
    <td colspan="3">{{ $s->hoja_ruta_observacion }}</td>
  </tr>
  @endif
  <tr>
    <td class="info-label">Fecha Completado</td>
    <td colspan="3">{{ $s->fecha_completado ? \Carbon\Carbon::parse($s->fecha_completado)->format('d/m/Y H:i') : '—' }}</td>
  </tr>
</table>
@endif

<div class="section-title">Firmas</div>
<table class="firmas" style="margin-top:4px;">
  <tr>
    <td>Solicitante</td>
    <td>Responsable de Transportes</td>
    <td>Conductor</td>
  </tr>
</table>

<p style="font-size:7pt; color:#555; margin-top:10px; text-align:right;">
  Generado el {{ now()->format('d/m/Y H:i') }}
</p>

</body>
</html>
