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

  .totales-wrap { width: 100%; margin-top: 0; }
  .totales-inner { float: right; width: 45%; border-collapse: collapse; }
  .totales-inner td { border: 1px solid #555; padding: 4px 8px; }
  .totales-label { font-weight: bold; text-align: right; font-size: 8px; text-transform: uppercase; background-color: #e5e7eb; }
  .totales-valor { text-align: right; font-family: monospace; width: 90px; }
  .totales-total  { font-weight: bold; background-color: #d1d5db; }

  .clearfix::after { content: ""; display: table; clear: both; }
</style>
</head>
<body>

{{-- ══ CABECERA ══ --}}
<table class="header-table">
  <tr>
    <td style="width:18%; text-align:center; padding:6px;">
      <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('logo.png'))) }}"
           style="height:48px; width:auto;" />
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
      @else
        —
      @endif
    </td>
  </tr>
</table>

{{-- ══ DETALLE ══ --}}
<table class="det-table" style="margin-top:8px;">
  <colgroup>
    <col style="width:11%">
    <col style="width:30%">
    <col style="width:5%">
    <col style="width:12%">
    <col style="width:6%">
    <col style="width:12%">
    <col style="width:12%">
    <col style="width:12%">
  </colgroup>
  <thead>
    <tr>
      <th>Ítem</th>
      <th>Descripción</th>
      <th>Cant.</th>
      <th>Precio Unit.<br>(s/IVA)</th>
      <th>IVA%</th>
      <th>Subtotal</th>
      <th>IVA $</th>
      <th>Total</th>
    </tr>
  </thead>
  <tbody>
    @foreach($orden->detalles as $det)
    <tr>
      <td style="text-align:center; font-size:8px;">{{ $det->articulo->item_presupuestario ?? '—' }}</td>
      <td style="text-transform:uppercase;">{{ $det->articulo->nombre }}</td>
      <td style="text-align:center;">{{ intval($det->cantidad) == $det->cantidad ? intval($det->cantidad) : $det->cantidad }}</td>
      <td style="text-align:right; font-family:monospace;">{{ number_format($det->precio_unitario, 5) }}</td>
      <td style="text-align:center;">{{ number_format($det->iva_porcentaje, 0) }}%</td>
      <td style="text-align:right; font-family:monospace;">{{ number_format($det->subtotal, 5) }}</td>
      <td style="text-align:right; font-family:monospace;">{{ number_format($det->iva_valor, 5) }}</td>
      <td style="text-align:right; font-family:monospace;">{{ number_format($det->total_linea, 5) }}</td>
    </tr>
    @endforeach
    {{-- Filas vacías para rellenar --}}
    @for($i = count($orden->detalles); $i < 6; $i++)
    <tr><td style="height:16px;">&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
    @endfor
  </tbody>
</table>

{{-- ══ TOTALES ══ --}}
@php
  $tarifa0  = $orden->detalles->where('iva_porcentaje', 0)->sum('subtotal');
  $tarifa15 = $orden->detalles->where('iva_porcentaje', '>', 0)->sum('subtotal');
@endphp
<div class="clearfix" style="margin-top:0;">
  {{-- Firma izquierda --}}
  <table style="float:left; width:50%; border-collapse:collapse;">
    <tr>
      <td style="border:1px solid #555; padding: 36px 12px 4px 12px; text-align:center;">&nbsp;</td>
    </tr>
    <tr>
      <td style="border:1px solid #555; padding:4px 12px; text-align:center; font-weight:bold;
                 text-transform:uppercase; font-size:8px; background-color:#e5e7eb;">
        Recibido por la Unidad de Bienes
      </td>
    </tr>
  </table>
  {{-- Totales derecha --}}
  <table class="totales-inner" style="float:right; width:45%; border-collapse:collapse;">
    <tr>
      <td class="totales-label">Subtotal</td>
      <td class="totales-valor">{{ number_format($orden->subtotal, 5) }}</td>
    </tr>
    <tr>
      <td class="totales-label">Otros Dsctos.</td>
      <td class="totales-valor">0.00000</td>
    </tr>
    <tr>
      <td class="totales-label">Tarifa 0%</td>
      <td class="totales-valor">{{ number_format($tarifa0, 5) }}</td>
    </tr>
    <tr>
      <td class="totales-label">Tarifa 15%</td>
      <td class="totales-valor">{{ number_format($tarifa15, 5) }}</td>
    </tr>
    <tr>
      <td class="totales-label">15 % IVA</td>
      <td class="totales-valor">{{ number_format($orden->iva_valor, 5) }}</td>
    </tr>
    <tr class="totales-total">
      <td class="totales-label totales-total">Total</td>
      <td class="totales-valor totales-total">{{ number_format($orden->total, 5) }}</td>
    </tr>
  </table>
</div>

</body>
</html>
