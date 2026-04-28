<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #111; }
  @page { margin: 10mm 15mm 10mm 15mm; }

  .header { text-align: center; margin-bottom: 6px; }
  .header-title { font-size: 11px; font-weight: bold; text-transform: uppercase; }
  .header-sub   { font-size: 9px; text-transform: uppercase; margin-top: 1px; }

  .info-block { margin-bottom: 6px; }
  .info-row { display: inline-block; margin-right: 20px; }
  .label { font-weight: bold; text-transform: uppercase; }

  table { border-collapse: collapse; width: 100%; table-layout: fixed; margin-top: 6px; }
  th {
    background-color: #4a5e3a;
    color: #fff;
    border: 1px solid #3b4a2e;
    padding: 4px 3px;
    text-align: center;
    font-size: 7px;
    text-transform: uppercase;
  }
  td { border: 1px solid #ccc; padding: 3px; font-size: 7.5px; }
  tr:nth-child(even) td { background-color: #f5f5f5; }

  .text-right { text-align: right; }
  .text-center { text-align: center; }
  .badge { display: inline-block; padding: 1px 4px; border-radius: 3px; font-weight: bold; font-size: 6.5px; }
  .badge-ingreso        { background: #d1fae5; color: #065f46; }
  .badge-egreso         { background: #fee2e2; color: #991b1b; }
  .badge-reverso-i      { background: #fef3c7; color: #92400e; }
  .badge-reverso-e      { background: #e0e7ff; color: #3730a3; }

  .footer { margin-top: 6px; font-size: 7px; color: #666; }
</style>
</head>
<body>

@php
  $logoPath = public_path('logo.png');
  $logoB64  = base64_encode(file_get_contents($logoPath));
@endphp

<div class="header">
  <img src="data:image/png;base64,{{ $logoB64 }}" style="height:30px; margin-bottom:3px;" /><br>
  <div class="header-title">Consejo de Comunicación</div>
  <div class="header-sub">Reporte Kardex de Inventario</div>
</div>

<div class="info-block">
  <span class="info-row"><span class="label">Artículo:</span> [{{ $articulo->codigo }}] {{ $articulo->nombre }}</span>
  <span class="info-row"><span class="label">Período:</span> {{ $request->desde }} al {{ $request->hasta }}</span>
  <span class="info-row"><span class="label">Stock Actual:</span> {{ number_format($articulo->stock_actual, 2) }}</span>
  <span class="info-row"><span class="label">Precio s/IVA:</span> $ {{ number_format($articulo->precio_unitario, 4) }}</span>
</div>

<table>
  <thead>
    <tr>
      <th style="width:11%">Fecha</th>
      <th style="width:10%">Tipo</th>
      <th style="width:13%">N° Documento</th>
      <th style="width:8%">Cant. Entrada</th>
      <th style="width:8%">Cant. Salida</th>
      <th style="width:8%">Stock Antes</th>
      <th style="width:8%">Stock Después</th>
      <th style="width:8%">Precio s/IVA</th>
      <th style="width:8%">Subtotal</th>
      <th style="width:7%">IVA</th>
      <th style="width:8%">Total</th>
      <th style="width:11%">Usuario</th>
    </tr>
  </thead>
  <tbody>
    @forelse($filas as $f)
    @php
      $badgeClass = match($f->tipo_movimiento) {
        'INGRESO'       => 'badge-ingreso',
        'EGRESO'        => 'badge-egreso',
        'REVERSO_INGRESO' => 'badge-reverso-i',
        'REVERSO_EGRESO'  => 'badge-reverso-e',
        default         => '',
      };
      $tipoLabel = match($f->tipo_movimiento) {
        'INGRESO'         => 'Ingreso',
        'EGRESO'          => 'Egreso',
        'REVERSO_INGRESO' => 'Rev. Ingreso',
        'REVERSO_EGRESO'  => 'Rev. Egreso',
        default           => $f->tipo_movimiento,
      };
    @endphp
    <tr>
      <td class="text-center">{{ \Carbon\Carbon::parse($f->fecha)->format('d/m/Y H:i') }}</td>
      <td class="text-center"><span class="badge {{ $badgeClass }}">{{ $tipoLabel }}</span></td>
      <td>{{ $f->numero_documento ?? '-' }}</td>
      <td class="text-right">{{ $f->cantidad_entrada > 0 ? number_format($f->cantidad_entrada, 2) : '-' }}</td>
      <td class="text-right">{{ $f->cantidad_salida > 0 ? number_format($f->cantidad_salida, 2) : '-' }}</td>
      <td class="text-right">{{ number_format($f->stock_antes, 2) }}</td>
      <td class="text-right">{{ number_format($f->stock_despues, 2) }}</td>
      <td class="text-right">{{ number_format($f->precio_movimiento, 4) }}</td>
      <td class="text-right">{{ number_format($f->subtotal, 2) }}</td>
      <td class="text-right">{{ number_format($f->iva_valor, 2) }}</td>
      <td class="text-right">{{ number_format($f->total_linea, 2) }}</td>
      <td class="text-center">{{ $f->usuario }}</td>
    </tr>
    @empty
    <tr>
      <td colspan="12" style="text-align:center; padding:8px; color:#888;">Sin movimientos en el período seleccionado</td>
    </tr>
    @endforelse
  </tbody>
</table>

<div class="footer">
  Generado el {{ \Carbon\Carbon::now()->format('d/m/Y H:i') }} &nbsp;|&nbsp; Total de movimientos: {{ count($filas) }}
</div>

</body>
</html>
