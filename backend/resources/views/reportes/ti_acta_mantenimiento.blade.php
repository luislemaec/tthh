<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, sans-serif; font-size: 9pt; color: #000; }
  @page { margin: 12mm 15mm 12mm 15mm; size: a4 portrait; }
  table { border-collapse: collapse; width: 100%; }

  .title { text-align: center; font-weight: bold; font-size: 11pt; text-transform: uppercase; padding: 4px 0; }
  .subtitle { text-align: center; font-size: 9pt; margin-bottom: 10px; }

  .info-table td { border: 1px solid #555; padding: 5px 8px; font-size: 8.5pt; }
  .info-label { font-weight: bold; background-color: #e5e7eb; width: 35%; }

  .section-title { font-weight: bold; font-size: 9pt; text-transform: uppercase;
                   background-color: #4d7c8a; color: #fff; padding: 4px 8px; margin: 10px 0 4px 0; }

  .checklist th { border: 1px solid #555; padding: 4px 6px; background-color: #e5e7eb; text-align: center; }
  .checklist td { border: 1px solid #555; padding: 3px 6px; }
  .check-mark { text-align: center; font-weight: bold; }

  .firmas td { border: 1px solid #555; padding: 70px 8px 6px 8px; text-align: center;
               font-weight: bold; font-size: 8pt; text-transform: uppercase;
               background-color: #e5e7eb; width: 50%; }
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
      <div class="title" style="margin-top:4px;">ACTA DE MANTENIMIENTO — EQUIPO TECNOLÓGICO</div>
      <div class="subtitle">
        Fecha: {{ \Carbon\Carbon::parse($m->fecha_mantenimiento)->format('d/m/Y') }}
        &nbsp;&nbsp;|&nbsp;&nbsp;
        Hora: {{ substr($m->hora_inicio,0,5) }} - {{ substr($m->hora_fin,0,5) }}
      </div>
    </td>
  </tr>
</table>

<div class="section-title">Datos del Equipo</div>
<table class="info-table" style="margin-bottom:6px;">
  <tr>
    <td class="info-label">Código de Bien</td>
    <td>{{ $m->equipo->codigo_bien }}</td>
    <td class="info-label">Tipo</td>
    <td>{{ $m->equipo->tipoEquipo->nombre ?? '—' }}</td>
  </tr>
  <tr>
    <td class="info-label">Marca / Modelo</td>
    <td>{{ $m->equipo->marca }} {{ $m->equipo->modelo }}</td>
    <td class="info-label">Serie</td>
    <td>{{ $m->equipo->serie ?? '—' }}</td>
  </tr>
</table>

<div class="section-title">Checklist de Actividades</div>
<table class="checklist" style="margin-bottom:6px; font-size:8.5pt;">
  <thead>
    <tr>
      <th style="width:8%;">N°</th>
      <th>Acciones Realizadas</th>
      <th style="width:12%;">SI</th>
      <th style="width:12%;">NO</th>
    </tr>
  </thead>
  <tbody>
    @foreach($detalle as $i => $d)
    <tr style="{{ $loop->even ? 'background-color:#f9fafb;' : '' }}">
      <td style="text-align:center;">{{ $i + 1 }}</td>
      <td>{{ $d->actividad->nombre ?? '' }}</td>
      <td class="check-mark">{{ $d->realizado ? 'X' : '' }}</td>
      <td class="check-mark">{{ $d->realizado ? '' : 'X' }}</td>
    </tr>
    @endforeach
  </tbody>
</table>

@if($m->observaciones)
<div class="section-title">Observaciones</div>
<table class="info-table" style="margin-bottom:6px;">
  <tr><td colspan="4">{{ $m->observaciones }}</td></tr>
</table>
@endif

<div class="section-title">Firmas</div>
<table class="firmas" style="margin-top:4px;">
  <tr>
    <td>
      Técnico que realizó<br>
      {{ strtoupper(($m->tecnico->apellido_emp ?? '') . ' ' . ($m->tecnico->nombre_emp ?? '')) }}
    </td>
    <td>
      Responsable del equipo<br>
      {{ $m->custodio ? strtoupper($m->custodio->apellido_emp . ' ' . $m->custodio->nombre_emp) : 'Sin custodio asignado' }}
    </td>
  </tr>
</table>

<p style="font-size:7pt; color:#555; margin-top:10px; text-align:right;">
  Generado el {{ now()->format('d/m/Y H:i') }}
</p>

</body>
</html>
