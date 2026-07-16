<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 7.5px; color: #111; }
  @page { margin: 8mm 12mm 8mm 12mm; }

  .header { text-align: center; margin-bottom: 5px; }
  .header-title { font-size: 10px; font-weight: bold; text-transform: uppercase; }
  .header-sub   { font-size: 8.5px; text-transform: uppercase; margin-top: 1px; }

  .info-block { margin-bottom: 5px; border: 1px solid #ccc; padding: 4px 6px; background: #f9f9f9; }
  .info-row { display: inline-block; margin-right: 18px; }
  .label { font-weight: bold; }

  .articulo-bloque { margin-bottom: 10px; page-break-inside: avoid; }

  table { border-collapse: collapse; width: 100%; table-layout: fixed; margin-top: 4px; }

  th {
    border: 1px solid #555;
    padding: 3px 2px;
    text-align: center;
    font-size: 6.5px;
    text-transform: uppercase;
    font-weight: bold;
  }
  th.th-base    { background-color: #4a5e3a; color: #fff; }
  th.th-ingreso { background-color: #166534; color: #fff; }
  th.th-egreso  { background-color: #991b1b; color: #fff; }
  th.th-saldo   { background-color: #1e3a5f; color: #fff; }

  td { border: 1px solid #ccc; padding: 2.5px 2px; font-size: 7px; }
  tr:nth-child(even) td { background-color: #f5f5f5; }

  .text-right  { text-align: right; }
  .text-center { text-align: center; }

  .badge { display: inline-block; padding: 1px 3px; border-radius: 2px; font-weight: bold; font-size: 6px; white-space: nowrap; }
  .badge-ingreso   { background: #d1fae5; color: #065f46; }
  .badge-egreso    { background: #fee2e2; color: #991b1b; }
  .badge-reverso-i { background: #fef3c7; color: #92400e; }
  .badge-reverso-e { background: #e0e7ff; color: #3730a3; }
  .badge-ajuste-p  { background: #d1fae5; color: #065f46; }
  .badge-ajuste-n  { background: #fee2e2; color: #991b1b; }
  .badge-saldo-ini { background: #dbeafe; color: #1e3a5f; }

  .td-ing { background-color: #f0fdf4 !important; }
  .td-egr { background-color: #fff5f5 !important; }
  .td-sal { background-color: #eff6ff !important; }

  .dash { color: #bbb; text-align: center; }

  tfoot td {
    font-weight: bold;
    background-color: #e5e7eb !important;
    border: 1px solid #999;
    font-size: 7px;
  }

  .footer { margin-top: 5px; font-size: 6.5px; color: #666; }
  .separador { height: 6px; }
</style>
</head>
<body>

@php
  $logoPath = public_path('logo.png');
  $logoB64  = base64_encode(file_get_contents($logoPath));

  $tiposIngreso = ['INGRESO', 'REVERSO_EGRESO', 'AJUSTE_POSITIVO', 'SALDO_INICIAL'];
  $tiposEgreso  = ['EGRESO', 'REVERSO_INGRESO', 'AJUSTE_NEGATIVO'];

  $badgeMap = [
    'INGRESO'          => ['badge-ingreso',   'Ingreso'],
    'EGRESO'           => ['badge-egreso',    'Egreso'],
    'REVERSO_INGRESO'  => ['badge-reverso-i', 'Rev. Ingreso'],
    'REVERSO_EGRESO'   => ['badge-reverso-e', 'Rev. Egreso'],
    'AJUSTE_POSITIVO'  => ['badge-ajuste-p',  'Aj. Positivo'],
    'AJUSTE_NEGATIVO'  => ['badge-ajuste-n',  'Aj. Negativo'],
    'SALDO_INICIAL'    => ['badge-saldo-ini', 'Saldo Inicial'],
  ];
@endphp

{{-- Encabezado global --}}
<table style="width:100%; margin-bottom:5px; border-collapse:collapse;">
  <tr>
    <td style="width:15%; vertical-align:middle;">
      <img src="data:image/png;base64,{{ $logoB64 }}" style="height:56px; width:auto;" />
    </td>
    <td style="text-align:center; vertical-align:middle;">
      <div class="header-title">Consejo de Comunicación</div>
      <div class="header-sub">Tarjeta Kardex — Método Promedio Ponderado (NIC 2)</div>
      <div style="font-size:7.5px; margin-top:2px; color:#555;">
        Período: {{ \Carbon\Carbon::parse($request->desde)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($request->hasta)->format('d/m/Y') }}
      </div>
    </td>
  </tr>
</table>

@foreach($resultados as $idx => $item)
@php
  $articulo = (object) $item['articulo'];
  $filas    = $item['filas'];

  $totalIngCant = 0; $totalIngMonto = 0;
  $totalEgrCant = 0; $totalEgrMonto = 0;
  $ultimoSaldo  = 0; $ultimoPrecio  = 0; $ultimoValorSaldo = 0;

  foreach ($filas as $f) {
    if (in_array($f->tipo_movimiento, $tiposIngreso)) {
      $totalIngCant  += $f->cantidad_entrada;
      $totalIngMonto += $f->subtotal;
    } else {
      $totalEgrCant  += $f->cantidad_salida;
      $totalEgrMonto += $f->subtotal;
    }
    $ultimoSaldo      = $f->stock_despues;
    $ultimoPrecio     = $f->precio_despues;
    $ultimoValorSaldo = $f->valor_saldo ?? ($f->stock_despues * $f->precio_despues);
  }
@endphp

@if($idx > 0)
<div class="separador"></div>
@endif

<div class="articulo-bloque">
<div class="info-block">
  <span class="info-row"><span class="label">Artículo:</span> [{{ $articulo->codigo }}] {{ $articulo->nombre }}</span>
  <span class="info-row"><span class="label">Stock actual:</span> {{ number_format($articulo->stock_actual, 2) }}</span>
  <span class="info-row"><span class="label">Precio s/IVA:</span> $ {{ number_format($articulo->precio_unitario, 4) }}</span>
  <span class="info-row"><span class="label">Valor inventario:</span> $ {{ number_format($articulo->stock_actual * $articulo->precio_unitario, 2) }}</span>
</div>

<table>
  <thead>
    <tr>
      <th class="th-base" rowspan="2" style="width:9%">Fecha</th>
      <th class="th-base" rowspan="2" style="width:10%">N° Documento</th>
      <th class="th-base" rowspan="2" style="width:14%">Detalle</th>
      <th class="th-ingreso" colspan="3" style="width:21%">INGRESO</th>
      <th class="th-egreso"  colspan="3" style="width:21%">EGRESO</th>
      <th class="th-saldo"   colspan="3" style="width:21%">SALDO</th>
      <th class="th-base" rowspan="2" style="width:4%">Usuario</th>
    </tr>
    <tr>
      <th class="th-ingreso" style="width:7%">Cant.</th>
      <th class="th-ingreso" style="width:7%">P. Unit.</th>
      <th class="th-ingreso" style="width:7%">Total</th>
      <th class="th-egreso"  style="width:7%">Cant.</th>
      <th class="th-egreso"  style="width:7%">P. Unit.</th>
      <th class="th-egreso"  style="width:7%">Total</th>
      <th class="th-saldo"   style="width:7%">Cant.</th>
      <th class="th-saldo"   style="width:7%">P. Unit.</th>
      <th class="th-saldo"   style="width:7%">Total</th>
    </tr>
  </thead>
  <tbody>
    @forelse($filas as $f)
    @php
      $esIng = in_array($f->tipo_movimiento, $tiposIngreso);
      [$badgeClass, $tipoLabel] = $badgeMap[$f->tipo_movimiento] ?? ['', $f->tipo_movimiento];
      $valorSaldo = $f->valor_saldo ?? round($f->stock_despues * $f->precio_despues, 2);
    @endphp
    <tr>
      <td class="text-center">{{ \Carbon\Carbon::parse($f->fecha)->format('d/m/Y') }}<br><span style="font-size:6px;color:#666">{{ \Carbon\Carbon::parse($f->fecha)->format('H:i') }}</span></td>
      <td class="text-center" style="font-size:6.5px">{{ $f->numero_documento ?? '—' }}</td>
      <td><span class="badge {{ $badgeClass }}">{{ $tipoLabel }}</span>@if($f->observacion)<br><span style="font-size:5.5px;color:#555">{{ mb_substr($f->observacion, 0, 40) }}{{ strlen($f->observacion) > 40 ? '…' : '' }}</span>@endif</td>

      @if($esIng)
        <td class="td-ing text-right">{{ number_format($f->cantidad_entrada, 2) }}</td>
        <td class="td-ing text-right">{{ number_format($f->precio_movimiento, 4) }}</td>
        <td class="td-ing text-right">{{ number_format($f->subtotal, 2) }}</td>
        <td class="td-egr dash">—</td>
        <td class="td-egr dash">—</td>
        <td class="td-egr dash">—</td>
      @else
        <td class="td-ing dash">—</td>
        <td class="td-ing dash">—</td>
        <td class="td-ing dash">—</td>
        <td class="td-egr text-right">{{ number_format($f->cantidad_salida, 2) }}</td>
        <td class="td-egr text-right">{{ number_format($f->precio_movimiento, 4) }}</td>
        <td class="td-egr text-right">{{ number_format($f->subtotal, 2) }}</td>
      @endif

      <td class="td-sal text-right">{{ number_format($f->stock_despues, 2) }}</td>
      <td class="td-sal text-right">{{ number_format($f->precio_despues, 4) }}</td>
      <td class="td-sal text-right">{{ number_format($valorSaldo, 2) }}</td>

      <td class="text-center" style="font-size:6px">{{ $f->usuario }}</td>
    </tr>
    @empty
    <tr>
      <td colspan="13" style="text-align:center; padding:10px; color:#888;">Sin movimientos en el período</td>
    </tr>
    @endforelse
  </tbody>
  @if(count($filas) > 0)
  <tfoot>
    <tr>
      <td colspan="3" class="text-right" style="font-size:7px; text-transform:uppercase; letter-spacing:0.3px;">Totales del período</td>
      <td class="text-right">{{ number_format($totalIngCant, 2) }}</td>
      <td></td>
      <td class="text-right">{{ number_format($totalIngMonto, 2) }}</td>
      <td class="text-right">{{ number_format($totalEgrCant, 2) }}</td>
      <td></td>
      <td class="text-right">{{ number_format($totalEgrMonto, 2) }}</td>
      <td class="text-right">{{ number_format($ultimoSaldo, 2) }}</td>
      <td class="text-right">{{ number_format($ultimoPrecio, 4) }}</td>
      <td class="text-right">{{ number_format($ultimoValorSaldo, 2) }}</td>
      <td></td>
    </tr>
  </tfoot>
  @endif
</table>
</div>
@endforeach

<div class="footer">
  Generado el: {{ \Carbon\Carbon::now()->format('d/m/Y H:i') }}
  &nbsp;|&nbsp; Artículos: {{ count($resultados) }}
</div>

</body>
</html>
