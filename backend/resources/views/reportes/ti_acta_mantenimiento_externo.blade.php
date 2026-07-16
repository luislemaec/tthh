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

  .equipos th { border: 1px solid #555; padding: 4px 6px; background-color: #e5e7eb; text-align: left; }
  .equipos td { border: 1px solid #555; padding: 3px 6px; }

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
      <div class="title" style="margin-top:4px;">ACTA DE MANTENIMIENTO EXTERNO</div>
      <div class="subtitle">
        Fecha: {{ \Carbon\Carbon::parse($primero->fecha_mantenimiento)->format('d/m/Y') }}
      </div>
    </td>
  </tr>
</table>

<div class="section-title">Datos del Proveedor</div>
<table class="info-table" style="margin-bottom:6px;">
  <tr>
    <td class="info-label">Proveedor</td>
    <td colspan="3">{{ $primero->proveedor }}</td>
  </tr>
  <tr>
    <td class="info-label">Proceso de Contratación</td>
    <td>{{ $primero->proceso_contratacion }}</td>
    <td class="info-label">N° Orden de Compra</td>
    <td>{{ $primero->numero_orden_compra }}</td>
  </tr>
  <tr>
    <td class="info-label">Categoría de Equipos</td>
    <td colspan="3">{{ $primero->equipo->tipoEquipo->nombre ?? '—' }}</td>
  </tr>
  @if($primero->observaciones)
  <tr>
    <td class="info-label">Observaciones</td>
    <td colspan="3">{{ $primero->observaciones }}</td>
  </tr>
  @endif
</table>

<div class="section-title">Equipos Atendidos ({{ $registros->count() }})</div>
<table class="equipos" style="margin-bottom:6px; font-size:8.5pt;">
  <thead>
    <tr>
      <th style="width:6%;">N°</th>
      <th style="width:18%;">Código</th>
      <th>Marca / Modelo</th>
      <th style="width:22%;">Serie</th>
    </tr>
  </thead>
  <tbody>
    @foreach($registros as $i => $r)
    <tr style="{{ $loop->even ? 'background-color:#f9fafb;' : '' }}">
      <td style="text-align:center;">{{ $i + 1 }}</td>
      <td>{{ $r->equipo->codigo_bien }}</td>
      <td>{{ $r->equipo->marca }} {{ $r->equipo->modelo }}</td>
      <td>{{ $r->equipo->serie ?? '—' }}</td>
    </tr>
    @endforeach
  </tbody>
</table>

<div class="section-title">Firmas</div>
<table class="firmas" style="margin-top:4px;">
  <tr>
    <td>Responsable Dirección de Tecnología</td>
    <td>Proveedor</td>
  </tr>
</table>

<p style="font-size:7pt; color:#555; margin-top:10px; text-align:right;">
  Generado el {{ now()->format('d/m/Y H:i') }}
</p>

</body>
</html>
