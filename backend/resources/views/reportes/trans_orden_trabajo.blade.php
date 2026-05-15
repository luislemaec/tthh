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
  $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
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
      <div class="title" style="margin-top:4px;">ORDEN DE TRABAJO — MANTENIMIENTO VEHICULAR</div>
      <div class="subtitle">
        N° Orden: <strong>{{ $m->numero_orden ?? '—' }}</strong>
        &nbsp;&nbsp;|&nbsp;&nbsp;
        Fecha: {{ $m->fecha_orden ? \Carbon\Carbon::parse($m->fecha_orden)->format('d/m/Y') : '—' }}
      </div>
    </td>
  </tr>
</table>

<div class="section-title">Datos del Vehículo</div>
<table class="info-table" style="margin-bottom:6px;">
  <tr>
    <td class="info-label">Placa</td>
    <td>{{ strtoupper($m->vehiculo->placa ?? '') }}</td>
    <td class="info-label">Marca / Modelo</td>
    <td>{{ $m->vehiculo->marca ?? '' }} {{ $m->vehiculo->modelo ?? '' }}</td>
  </tr>
  <tr>
    <td class="info-label">Año</td>
    <td>{{ $m->vehiculo->anio ?? '' }}</td>
    <td class="info-label">Color</td>
    <td>{{ $m->vehiculo->color ?? '' }}</td>
  </tr>
  <tr>
    <td class="info-label">Kilometraje Actual</td>
    <td colspan="3">{{ number_format($m->vehiculo->kilometraje_actual ?? 0) }} km</td>
  </tr>
</table>

<div class="section-title">Datos del Requerimiento</div>
<table class="info-table" style="margin-bottom:6px;">
  <tr>
    <td class="info-label">Conductor</td>
    <td colspan="3">{{ strtoupper(($m->conductor->apellido_emp ?? '') . ' ' . ($m->conductor->nombre_emp ?? '')) }}</td>
  </tr>
  <tr>
    <td class="info-label">Tipo</td>
    <td>{{ $m->tipo }}</td>
    <td class="info-label">Estado</td>
    <td>{{ $m->estado }}</td>
  </tr>
  <tr>
    <td class="info-label">Descripción / Problema</td>
    <td colspan="3" style="min-height:40px;">{{ $m->descripcion }}</td>
  </tr>
</table>

<div class="section-title">Datos de la Orden</div>
<table class="info-table" style="margin-bottom:6px;">
  <tr>
    <td class="info-label">Taller</td>
    <td colspan="3">{{ strtoupper($m->taller ?? '—') }}</td>
  </tr>
  <tr>
    <td class="info-label">Responsable de Transportes</td>
    <td colspan="3">{{ strtoupper(($m->responsable->apellido_emp ?? '') . ' ' . ($m->responsable->nombre_emp ?? '')) }}</td>
  </tr>
  @if($m->observacion_responsable)
  <tr>
    <td class="info-label">Observaciones</td>
    <td colspan="3">{{ $m->observacion_responsable }}</td>
  </tr>
  @endif
  @if($m->fecha_finalizacion)
  <tr>
    <td class="info-label">Fecha Finalización</td>
    <td colspan="3">{{ \Carbon\Carbon::parse($m->fecha_finalizacion)->format('d/m/Y') }}</td>
  </tr>
  @endif
</table>

@php
  $actPreventivas = $m->actividades->where('tipo', 'PREVENTIVO');
  $actCorrectivas = $m->actividades->where('tipo', 'CORRECTIVO');
  $tipoLabels = ['MO' => 'Mano de Obra', 'RE' => 'Repuesto', 'CL' => 'Comb./Lubricante'];
@endphp

@if($m->actividades->count() > 0)
<div class="section-title">Actividades a Realizar</div>
<table style="border-collapse:collapse; width:100%; margin-bottom:6px; font-size:8.5pt;">
  <thead>
    <tr style="background-color:#e5e7eb;">
      <th style="border:1px solid #555; padding:4px 6px; width:5%; text-align:center;">N°</th>
      <th style="border:1px solid #555; padding:4px 6px; width:12%; text-align:center;">Cód.</th>
      <th style="border:1px solid #555; padding:4px 6px; width:23%; text-align:center;">Tipo</th>
      <th style="border:1px solid #555; padding:4px 6px; text-align:left;">Actividad</th>
      <th style="border:1px solid #555; padding:4px 6px; width:15%; text-align:center;">Categoría</th>
    </tr>
  </thead>
  <tbody>
    @foreach($m->actividades->sortBy([['tipo','desc'],['orden','asc']]) as $i => $act)
    <tr style="{{ $loop->even ? 'background-color:#f9fafb;' : '' }}">
      <td style="border:1px solid #555; padding:3px 6px; text-align:center;">{{ $loop->iteration }}</td>
      <td style="border:1px solid #555; padding:3px 6px; text-align:center; font-weight:bold;">
        {{ $act->tipo_actividad ?? '—' }}
      </td>
      <td style="border:1px solid #555; padding:3px 6px; text-align:center;">
        {{ isset($tipoLabels[$act->tipo_actividad]) ? $tipoLabels[$act->tipo_actividad] : '' }}
      </td>
      <td style="border:1px solid #555; padding:3px 6px;">{{ $act->actividad }}</td>
      <td style="border:1px solid #555; padding:3px 6px; text-align:center;">{{ $act->tipo }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
@endif

<div class="section-title">Firmas</div>
<table class="firmas" style="margin-top:4px;">
  <tr>
    <td>Conductor</td>
    <td>Responsable de Transportes</td>
    <td>Taller / Responsable</td>
  </tr>
</table>

<p style="font-size:7pt; color:#555; margin-top:10px; text-align:right;">
  Generado el {{ now()->format('d/m/Y H:i') }}
</p>

</body>
</html>
