<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; font-size: 9pt; margin: 1.5cm; }
  table { width: 100%; border-collapse: collapse; table-layout: fixed; }
  .header-table { margin-bottom: 10px; }
  .info-table td { padding: 2px 5px; font-size: 9pt; }
  .items-table th { background-color: #4a5e3a; color: white; padding: 5px 6px; text-align: left; border: 1px solid #3a4e2a; }
  .items-table td { border: 1px solid #ccc; padding: 4px 6px; }
  .items-table tr:nth-child(even) td { background-color: #f5f8f3; }
</style>
</head>
<body>

{{-- Encabezado institucional --}}
<table class="header-table">
  <tr>
    <td style="width:15%; vertical-align:middle; text-align:center;">
      @if($logo)<img src="{{ $logo }}" style="max-height:70px; max-width:100px;">@endif
    </td>
    <td style="text-align:center; vertical-align:middle; padding:0 10px;">
      <div style="font-size:11pt; font-weight:bold; text-transform:uppercase;">{{ $nombreInst }}</div>
      <div style="font-size:10pt; font-weight:bold; text-transform:uppercase; margin-top:3px;">DESPACHO DE MATERIALES</div>
      <div style="font-size:8.5pt; margin-top:3px; color:#444;">
        Solicitud N°&nbsp;<b>{{ $solicitud->id }}</b>
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <span style="background-color:{{ $solicitud->estado === 'DESPACHADO' ? '#d1fae5' : '#ffedd5' }}; color:{{ $solicitud->estado === 'DESPACHADO' ? '#065f46' : '#9a3412' }}; padding:1px 8px; border-radius:10px;">{{ $solicitud->estado }}</span>
      </div>
    </td>
  </tr>
</table>
<hr style="border:1px solid #4a5e3a; margin-bottom:10px;">

{{-- Datos de la solicitud --}}
<table class="info-table" style="margin-bottom:12px;">
  <tr>
    <td style="width:18%; color:#555; font-weight:bold;">Solicitante:</td>
    <td style="width:32%;">{{ strtoupper(trim($solicitud->empleado->apellido_emp . ' ' . $solicitud->empleado->nombre_emp)) }}</td>
    <td style="width:18%; color:#555; font-weight:bold;">Departamento:</td>
    <td style="width:32%;">{{ $depto }}</td>
  </tr>
  <tr>
    <td style="color:#555; font-weight:bold;">Fecha solicitud:</td>
    <td>{{ $solicitud->fecha }}</td>
    <td style="color:#555; font-weight:bold;">Fecha despacho:</td>
    <td>{{ $fechaDespacho }}</td>
  </tr>
  @if($solicitud->justificacion)
  <tr>
    <td style="color:#555; font-weight:bold; vertical-align:top;">Justificación:</td>
    <td colspan="3" style="font-style:italic; color:#444;">{{ $solicitud->justificacion }}</td>
  </tr>
  @endif
  @if($solicitud->observacion_despacho)
  <tr>
    <td style="color:#555; font-weight:bold; vertical-align:top;">Obs. despacho:</td>
    <td colspan="3" style="font-style:italic; color:#444;">{{ $solicitud->observacion_despacho }}</td>
  </tr>
  @endif
</table>

{{-- Tabla de artículos --}}
<table class="items-table" style="margin-bottom:30px;">
  <thead>
    <tr>
      <th style="width:12%;">Código</th>
      <th style="width:46%;">Descripción del Artículo</th>
      <th style="width:12%; text-align:center;">U.M.</th>
      <th style="width:15%; text-align:right;">Solicitado</th>
      <th style="width:15%; text-align:right;">Entregado</th>
    </tr>
  </thead>
  <tbody>
    @foreach($solicitud->detalles as $det)
    @php $parcial = (float)($det->cantidad_autorizada ?? 0) < (float)$det->cantidad_solicitada; @endphp
    <tr>
      <td style="font-family:monospace; font-size:8pt; color:#555;">{{ $det->articulo->codigo ?? '' }}</td>
      <td>{{ $det->articulo->nombre ?? '' }}</td>
      <td style="text-align:center;">{{ $det->articulo->unidad_medida ?? '' }}</td>
      <td style="text-align:right;">{{ number_format((float)$det->cantidad_solicitada, 2) }}</td>
      <td style="text-align:right; font-weight:bold; color:{{ $parcial ? '#9a3412' : '#065f46' }};">
        {{ number_format((float)($det->cantidad_autorizada ?? 0), 2) }}
      </td>
    </tr>
    @endforeach
  </tbody>
</table>

{{-- Firmas --}}
<table style="width:100%; margin-top:50px;">
  <tr>
    <td style="width:45%; text-align:center;">
      <div style="border-top:1px solid #333; padding-top:6px;">
        <div style="font-weight:bold; text-transform:uppercase; font-size:9pt;">{{ $nombreDespachador }}</div>
        <div style="color:#555; font-size:8.5pt;">{{ $cargoDespachador }}</div>
        <div style="color:#777; font-size:8pt;">Responsable — Bienes y Suministros</div>
      </div>
    </td>
    <td style="width:10%;"></td>
    <td style="width:45%; text-align:center;">
      <div style="border-top:1px solid #333; padding-top:6px;">
        <div style="font-weight:bold; text-transform:uppercase; font-size:9pt;">{{ $nombreSolicitante }}</div>
        <div style="color:#555; font-size:8.5pt;">{{ $cargoSolicitante }}</div>
        <div style="color:#777; font-size:8pt;">Servidor Solicitante</div>
      </div>
    </td>
  </tr>
</table>

<p style="font-size:7.5pt; color:#555; margin-top:12px; text-align:right;">
  Generado por: {{ strtoupper($generadoPor) }} &nbsp;&nbsp;|&nbsp;&nbsp; {{ now()->format('d/m/Y H:i') }}
</p>

</body>
</html>
