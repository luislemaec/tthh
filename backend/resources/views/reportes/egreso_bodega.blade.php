<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
  @page { margin: 15mm 12mm 15mm 12mm; }

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

  .firma-table { margin-top: 30px; border-collapse: collapse; width: 100%; }
  .firma-table td { border: 1px solid #555; padding: 30px 12px 8px 12px; text-align: center;
                    font-weight: bold; text-transform: uppercase; font-size: 9px; }
  .firma-nombre td { border: 1px solid #555; padding: 4px 12px; text-align: center; font-size: 9px; }
  .firma-rol td { border: 1px solid #555; padding: 4px 12px; text-align: center;
                  font-weight: bold; text-transform: uppercase; font-size: 8px;
                  background-color: #e5e7eb; }
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
      <div class="header-sub">Egreso de Bodega</div>
    </td>
    <td style="width:18%;"></td>
  </tr>
</table>

{{-- ══ DATOS DEL EGRESO ══ --}}
@php
  $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
  $fecha = $egreso->fecha_despacho ? \Carbon\Carbon::parse($egreso->fecha_despacho) : now();
@endphp
<table class="info-table" style="margin-top:4px;">
  <tr>
    <td style="width:50%;">
      <span class="info-label">Secuencial Egreso No.:</span>
      <strong style="font-size:13px;">&nbsp;{{ $egreso->numero_secuencial ?? $egreso->id }}</strong>
    </td>
    <td colspan="2">
      <span class="info-label">eSBYE:</span>
    </td>
  </tr>
  <tr>
    <td colspan="3">
      <span class="info-label">Dirección:</span>
      {{ strtoupper($egreso->direccion ?? '—') }}
    </td>
  </tr>
  <tr>
    <td colspan="3">
      <span class="info-label">Servidor:</span>
      {{ strtoupper($egreso->empleado_nombre ?? '—') }}
    </td>
  </tr>
  <tr>
    <td colspan="3">
      <span class="info-label">Fecha de Registro:</span>
      {{ $fecha->day }} de {{ $meses[$fecha->month - 1] }} de {{ $fecha->year }}
    </td>
  </tr>
</table>

{{-- ══ DETALLE ══ --}}
<table class="det-table" style="margin-top:8px;">
  <colgroup>
    <col style="width:6%">
    <col style="width:48%">
    <col style="width:10%">
    <col style="width:18%">
    <col style="width:18%">
  </colgroup>
  <thead>
    <tr>
      <th>Ord.</th>
      <th>Descripción</th>
      <th>Cantidad</th>
      <th>Precio Unitario<br>(Incluido el IVA)</th>
      <th>Total</th>
    </tr>
  </thead>
  <tbody>
    @foreach($egreso->detalles as $i => $det)
    @php
      $precioConIva = $det->cantidad > 0 ? round($det->total_linea / $det->cantidad, 4) : 0;
    @endphp
    <tr>
      <td style="text-align:center;">{{ $i + 1 }}</td>
      <td style="text-transform:uppercase;">{{ $det->articulo->nombre }}</td>
      <td style="text-align:center;">{{ intval($det->cantidad) == $det->cantidad ? intval($det->cantidad) : $det->cantidad }}</td>
      <td style="text-align:right; font-family:monospace;">{{ number_format($precioConIva, 5) }}</td>
      <td style="text-align:right; font-family:monospace;">{{ number_format($det->total_linea, 5) }}</td>
    </tr>
    @endforeach
    @for($i = count($egreso->detalles); $i < 6; $i++)
    <tr><td style="height:16px;">&nbsp;</td><td></td><td></td><td></td><td></td></tr>
    @endfor
    <tr>
      <td colspan="2" style="text-align:right; font-weight:bold; text-transform:uppercase;">Total:</td>
      <td style="text-align:center; font-weight:bold;">{{ $egreso->detalles->sum(fn($d) => intval($d->cantidad)) }}</td>
      <td></td>
      <td style="text-align:right; font-family:monospace; font-weight:bold;">{{ number_format($egreso->total, 5) }}</td>
    </tr>
  </tbody>
</table>

{{-- ══ FIRMAS ══ --}}
<table class="firma-table" style="margin-top:30px;">
  <tr>
    <td style="width:50%; border:1px solid #555; padding: 30px 12px 4px 12px; text-align:center;">
      &nbsp;
    </td>
    <td style="width:50%; border:1px solid #555; padding: 30px 12px 4px 12px; text-align:center;">
      {{ strtoupper($egreso->empleado_nombre ?? '') }}
    </td>
  </tr>
  <tr>
    <td style="border:1px solid #555; padding:4px 12px; text-align:center; font-weight:bold; text-transform:uppercase; font-size:8px; background-color:#e5e7eb;">
      Unidad de Bienes
    </td>
    <td style="border:1px solid #555; padding:4px 12px; text-align:center; font-weight:bold; text-transform:uppercase; font-size:8px; background-color:#e5e7eb;">
      Servidor de la Unidad Requirente
    </td>
  </tr>
</table>

</body>
</html>
