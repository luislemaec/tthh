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
  .text-right  { text-align: right; }
  .text-center { text-align: center; }
  .totales td  { background-color: #e8f0e0 !important; font-weight: bold; border-top: 2px solid #4a5e3a; }
  .footer { margin-top: 6px; font-size: 7px; color: #666; }
</style>
</head>
<body>

@php
  $logoPath = public_path('logo.png');
  $logoB64  = base64_encode(file_get_contents($logoPath));
  $totalSub = $filas->sum('subtotal');
  $totalIva = $filas->sum('iva_valor');
  $totalGen = $filas->sum('total_linea');
@endphp

<div class="header">
  <img src="data:image/png;base64,{{ $logoB64 }}" style="height:60px; margin-bottom:3px;" /><br>
  <div class="header-title">Consejo de Comunicación</div>
  <div class="header-sub">Reporte de Egresos Valorizados</div>
</div>

<div class="info-block">
  <span class="info-row"><span class="label">Período:</span> {{ $request->desde }} al {{ $request->hasta }}</span>
  @if($request->filled('direccion'))
    <span class="info-row"><span class="label">Dirección:</span> {{ $request->direccion }}</span>
  @endif
  <span class="info-row"><span class="label">Total líneas:</span> {{ count($filas) }}</span>
</div>

<table>
  <thead>
    <tr>
      <th style="width:7%">Fecha</th>
      <th style="width:6%">N° Egreso</th>
      <th style="width:16%">Dirección</th>
      <th style="width:14%">Servidor</th>
      <th style="width:7%">Código</th>
      <th style="width:18%">Artículo</th>
      <th style="width:6%">Cantidad</th>
      <th style="width:8%">Precio s/IVA</th>
      <th style="width:7%">Subtotal</th>
      <th style="width:5%">IVA</th>
      <th style="width:6%">Total</th>
    </tr>
  </thead>
  <tbody>
    @forelse($filas as $f)
    <tr>
      <td class="text-center">{{ \Carbon\Carbon::parse($f->fecha_despacho)->format('d/m/Y') }}</td>
      <td class="text-center">{{ str_pad($f->numero_secuencial, 4, '0', STR_PAD_LEFT) }}-{{ $f->anio }}</td>
      <td>{{ $f->direccion }}</td>
      <td>{{ $f->empleado_nombre }}</td>
      <td class="text-center">{{ $f->articulo_codigo }}</td>
      <td>{{ $f->articulo_nombre }}</td>
      <td class="text-right">{{ number_format($f->cantidad, 2) }}</td>
      <td class="text-right">$ {{ number_format($f->precio_unitario, 4) }}</td>
      <td class="text-right">$ {{ number_format($f->subtotal, 2) }}</td>
      <td class="text-right">$ {{ number_format($f->iva_valor, 2) }}</td>
      <td class="text-right">$ {{ number_format($f->total_linea, 2) }}</td>
    </tr>
    @empty
    <tr>
      <td colspan="11" style="text-align:center; padding:8px; color:#888;">Sin egresos en el período seleccionado</td>
    </tr>
    @endforelse
    @if(count($filas) > 0)
    <tr class="totales">
      <td colspan="8" class="text-right">TOTALES</td>
      <td class="text-right">$ {{ number_format($totalSub, 2) }}</td>
      <td class="text-right">$ {{ number_format($totalIva, 2) }}</td>
      <td class="text-right">$ {{ number_format($totalGen, 2) }}</td>
    </tr>
    @endif
  </tbody>
</table>

<div class="footer">
  Generado el {{ \Carbon\Carbon::now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
