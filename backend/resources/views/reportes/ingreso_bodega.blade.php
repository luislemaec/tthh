<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
  @page { margin: 12mm 20mm 12mm 20mm; }

  table { border-collapse: collapse; width: 100%; }
  td, th { vertical-align: middle; }

  .header-table td { border: 1px solid #555; padding: 4px 8px; }
  .header-title { text-align: center; font-size: 12px; font-weight: bold; text-transform: uppercase; }
  .header-sub   { text-align: center; font-size: 10px; text-transform: uppercase; margin-top: 2px; }

  .info-table { margin-top: 4px; }
  .info-table td { border: 1px solid #555; padding: 4px 8px; }
  .info-label { font-weight: bold; text-transform: uppercase; }

  .det-table { margin-top: 8px; table-layout: fixed; }
  .det-table th {
    background-color: #808080;
    color: #fff;
    border: 1px solid #555;
    padding: 5px 4px;
    text-align: center;
    font-size: 8px;
    text-transform: uppercase;
  }
  .det-table td { border: 1px solid #555; padding: 4px; }

  .totales-inner { float: right; width: 45%; border-collapse: collapse; }
  .totales-inner td { border: 1px solid #555; padding: 4px 8px; }
  .totales-label { font-weight: bold; text-align: right; font-size: 8px; text-transform: uppercase; background-color: #e5e7eb; }
  .totales-valor { text-align: right; font-family: monospace; width: 90px; }
  .totales-total { font-weight: bold; background-color: #d1d5db; }

  .clearfix::after { content: ""; display: table; clear: both; }
</style>
</head>
<body>

{{-- ══ CABECERA ══ --}}
<table class="header-table">
  <tr>
    <td style="width:18%; text-align:center; padding:6px;">
      <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('logo.png'))) }}"
           style="height:96px; width:auto;" />
    </td>
    <td style="width:64%;">
      <div class="header-title">Administración de Bienes</div>
      <div class="header-sub">Ingreso a Bodega</div>
    </td>
    <td style="width:18%;"></td>
  </tr>
</table>

{{-- ══ DATOS DEL INGRESO ══ --}}
<table class="info-table" style="margin-top:4px;">
  <tr>
    <td colspan="3">
      <span class="info-label">Proceso Contratación:</span>
      {{ $orden->proceso_contratacion ?? '—' }}
    </td>
  </tr>
  <tr>
    <td style="width:50%;">
      <span class="info-label">Secuencial Ingreso No.:</span>
      <strong>{{ $orden->numero_secuencial ?? $orden->id }}</strong>
    </td>
    <td colspan="2">
      <span class="info-label">eSBYE:</span>
    </td>
  </tr>
  <tr>
    <td colspan="3">
      <span class="info-label">Proveedor:</span>
      {{ $orden->proveedor?->nombre ?? '—' }}
    </td>
  </tr>
  <tr>
    <td colspan="3">
      <span class="info-label">{{ $orden->tipo_documento ?? 'Factura' }}:</span>
      {{ $orden->numero_documento ?? '—' }}
    </td>
  </tr>
  <tr>
    <td colspan="3">
      <span class="info-label">Fecha de {{ $orden->tipo_documento ?? 'Factura' }}:</span>
      @if($orden->fecha_documento)
        {{ \Carbon\Carbon::parse($orden->fecha_documento)->day }}
        de {{ ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'][\Carbon\Carbon::parse($orden->fecha_documento)->month - 1] }}
        de {{ \Carbon\Carbon::parse($orden->fecha_documento)->year }}
      @else —
      @endif
    </td>
  </tr>
</table>

{{-- ══ DETALLE ══ --}}
<table class="det-table" style="margin-top:8px;">
  <colgroup>
    <col style="width:11%">
    <col style="width:52%">
    <col style="width:5%">
    <col style="width:16%">
    <col style="width:16%">
  </colgroup>
  <thead>
    <tr>
      <th>Ítem</th>
      <th>Descripción</th>
      <th>Cant.</th>
      <th>Precio Unitario</th>
      <th>Valor Total</th>
    </tr>
  </thead>
  <tbody>
    @foreach($orden->detalles as $det)
    <tr>
      <td style="text-align:center; font-size:8px;">{{ $det->articulo->item_presupuestario ?? '—' }}</td>
      <td style="text-transform:uppercase;">{{ $det->articulo->nombre }}</td>
      <td style="text-align:center;">{{ intval($det->cantidad) == $det->cantidad ? intval($det->cantidad) : $det->cantidad }}</td>
      <td style="text-align:right; font-family:monospace;">{{ number_format($det->precio_unitario, 5) }}</td>
      <td style="text-align:right; font-family:monospace;">{{ number_format($det->subtotal, 2) }}</td>
    </tr>
    @endforeach
    @for($i = count($orden->detalles); $i < 3; $i++)
    <tr><td style="height:16px;">&nbsp;</td><td></td><td></td><td></td><td></td></tr>
    @endfor
  </tbody>
</table>

{{-- ══ TOTALES + FIRMA (misma fila) ══ --}}
@php
  $tarifa0  = $orden->detalles->where('iva_porcentaje', 0)->sum('subtotal');
  $tarifa15 = $orden->detalles->where('iva_porcentaje', '>', 0)->sum('subtotal');
@endphp
<table style="width:100%; margin-top:0; border-collapse:collapse;">
  <tr style="vertical-align:bottom;">
    <td style="width:53%; padding-right:6px; vertical-align:bottom;">
      <table style="width:100%; border-collapse:collapse;">
        <tr>
          <td style="border:1px solid #555; padding:36px 12px 4px 12px; text-align:center;">&nbsp;</td>
        </tr>
        <tr>
          <td style="border:1px solid #555; padding:4px 12px; text-align:center; font-weight:bold;
                     text-transform:uppercase; font-size:8px; background-color:#e5e7eb;">
            Recibido por la Unidad de Bienes
          </td>
        </tr>
      </table>
    </td>
    <td style="width:47%; vertical-align:bottom;">
      <table style="width:100%; border-collapse:collapse;">
        <tr>
          <td class="totales-label">Subtotal sin impuesto</td>
          <td class="totales-valor">{{ number_format($orden->subtotal, 2) }}</td>
        </tr>
        <tr>
          <td class="totales-label">Total descuento</td>
          <td class="totales-valor">{{ number_format($orden->descuento ?? 0, 2) }}</td>
        </tr>
        @php
          $descuento = (float)($orden->descuento ?? 0);
          $subtotal  = (float)$orden->subtotal;
          $factor    = $subtotal > 0 ? ($subtotal - $descuento) / $subtotal : 1;
          $tarifa0Net  = round($tarifa0  * $factor, 2);
          $tarifa15Net = round($tarifa15 * $factor, 2);
        @endphp
        <tr>
          <td class="totales-label">Subtotal 15%</td>
          <td class="totales-valor">{{ number_format($tarifa15Net, 2) }}</td>
        </tr>
        <tr>
          <td class="totales-label">Subtotal 0%</td>
          <td class="totales-valor">{{ number_format($tarifa0Net, 2) }}</td>
        </tr>
        <tr>
          <td class="totales-label">Base imponible</td>
          <td class="totales-valor">{{ number_format($subtotal - $descuento, 2) }}</td>
        </tr>
        <tr>
          <td class="totales-label">I.V.A.</td>
          <td class="totales-valor">{{ number_format($orden->iva_valor, 2) }}</td>
        </tr>
        <tr>
          <td class="totales-label totales-total">Valor Total</td>
          <td class="totales-valor totales-total">{{ number_format($orden->total, 2) }}</td>
        </tr>
      </table>
    </td>
  </tr>
</table>

</body>
</html>
