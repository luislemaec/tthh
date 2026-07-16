<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  @page { margin: 14px 18px; }
  * { box-sizing: border-box; }
  body { font-family: Arial, sans-serif; font-size: 9.5pt; color: #000; margin: 0; }
  table { width: 100%; border-collapse: collapse; table-layout: fixed; }
  .border-all td, .border-all th { border: 1px solid #000; }
  .header-table td { padding: 5px 8px; vertical-align: middle; }
  .titulo { font-size: 12pt; font-weight: bold; text-align: center; }
  .numero { text-align: right; font-size: 9pt; padding: 2px 6px; }
  .label { font-weight: bold; }
  .campo { border-bottom: 1px solid #000; min-height: 15px; display: inline-block; width: 78%; }
  .items-table th { background: #fff; font-size: 9pt; font-weight: bold; text-align: center; padding: 4px 4px; }
  .items-table td { font-size: 9pt; padding: 4px 4px; }
  .items-table .desc { width: 44%; }
  .items-table .num  { width: 19%; text-align: center; }
  .firmas-label td { text-align: center; font-size: 9pt; padding: 4px 8px 2px; }
  .firmas-firma td { text-align: center; font-size: 9pt; font-weight: bold; padding: 0 10px 6px 10px; vertical-align: bottom; height: 120px; }
</style>
</head>
<body>

<!-- Encabezado -->
<table class="border-all header-table">
  <tr>
    <td style="width:22%; text-align:center; padding:6px;">
      @if($logo)
        <img src="{{ $logo }}" style="height:48px; width:auto;">
      @endif
    </td>
    <td class="titulo">VALE DE COMBUSTIBLE</td>
  </tr>
</table>

<!-- No. y destinatario -->
<table class="border-all" style="margin-top:-1px;">
  <tr>
    <td style="padding:6px 10px;">
      <div class="numero">No. {{ str_pad($vale->numero, 4, '0', STR_PAD_LEFT) }}</div>
      <div style="margin-top:5px;">
        <span class="label">Sr. (es):</span>
        <span class="campo">{{ $vale->gasolinera }}</span>
      </div>
      <div style="margin-top:5px;">
        <span class="label">Conductor:</span>
        <span class="campo">{{ $vale->conductor?->apellido_emp }} {{ $vale->conductor?->nombre_emp }}</span>
      </div>
    </td>
  </tr>
</table>

<!-- Tabla de combustible -->
<table class="border-all items-table" style="margin-top:-1px;">
  <thead>
    <tr>
      <th class="desc">Descripción</th>
      <th class="num">Glns.</th>
      <th class="num">P/U</th>
      <th class="num">Valor en Venta</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td class="desc">Gasolina Extra</td>
      <td class="num">{{ $vale->glns_extra ? number_format($vale->glns_extra, 2) : '' }}</td>
      <td class="num">{{ $vale->pu_extra ? number_format($vale->pu_extra, 4) : '' }}</td>
      <td class="num">{{ $vale->valor_extra ? number_format($vale->valor_extra, 2) : '' }}</td>
    </tr>
    <tr>
      <td class="desc">Gasolina Super</td>
      <td class="num">{{ $vale->glns_super ? number_format($vale->glns_super, 2) : '' }}</td>
      <td class="num">{{ $vale->pu_super ? number_format($vale->pu_super, 4) : '' }}</td>
      <td class="num">{{ $vale->valor_super ? number_format($vale->valor_super, 2) : '' }}</td>
    </tr>
    <tr>
      <td class="desc">Diesel</td>
      <td class="num">{{ $vale->glns_diesel ? number_format($vale->glns_diesel, 2) : '' }}</td>
      <td class="num">{{ $vale->pu_diesel ? number_format($vale->pu_diesel, 4) : '' }}</td>
      <td class="num">{{ $vale->valor_diesel ? number_format($vale->valor_diesel, 2) : '' }}</td>
    </tr>
  </tbody>
</table>

<!-- Kilometraje, vehículo, fecha -->
<table class="border-all" style="margin-top:-1px;">
  <tr>
    <td style="padding:7px 10px;">
      <div><strong>KILOMETRAJE</strong> {{ $vale->kilometraje ? number_format($vale->kilometraje) : '________________' }}</div>
      <div style="margin-top:5px;">
        <strong>VEHÍCULO</strong> {{ $vale->vehiculo?->marca }} {{ $vale->vehiculo?->modelo }}
        &nbsp;&nbsp;&nbsp;
        <strong>PLACAS No.</strong> {{ $vale->vehiculo?->placa }}
      </div>
      <div style="margin-top:5px;">
        <strong>Fecha comprobante:</strong>
        @if($diaComp)
          <u>&nbsp;{{ $diaComp }}&nbsp;</u> de <u>&nbsp;{{ $mesComp }}&nbsp;</u> de <u>&nbsp;{{ $anioComp }}&nbsp;</u>
        @else
          <u>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</u>
        @endif
      </div>
      <div style="margin-top:5px;">
        <strong>Fecha elaboración:</strong>
        <u>&nbsp;{{ $dia }}&nbsp;</u> de <u>&nbsp;{{ $mes }}&nbsp;</u> de <u>&nbsp;{{ $anio }}&nbsp;</u>
      </div>
    </td>
  </tr>
</table>

<!-- Firmas -->
<table class="border-all" style="margin-top:-1px;">
  <tr class="firmas-label">
    <td style="border-right:1px solid #000;">Autorizado:</td>
    <td>Recibido:</td>
  </tr>
  <tr class="firmas-firma">
    <td style="border-right:1px solid #000; vertical-align:bottom;">
      <div style="border-top:1px solid #000; padding-top:3px;">Responsable de Transportes</div>
    </td>
    <td style="vertical-align:bottom;">
      <div style="border-top:1px solid #000; padding-top:3px;">CONDUCTOR/A</div>
    </td>
  </tr>
</table>

</body>
</html>
