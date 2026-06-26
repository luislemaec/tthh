<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 7.5px; color: #111; }
  @page { margin: 8mm 12mm 8mm 12mm; }

  table { border-collapse: collapse; width: 100%; table-layout: fixed; }
  th {
    background-color: #4a5e3a;
    color: #fff;
    border: 1px solid #3b4a2e;
    padding: 4px 3px;
    text-align: center;
    font-size: 7px;
    text-transform: uppercase;
  }
  td { border: 1px solid #ccc; padding: 3px 4px; font-size: 7.5px; vertical-align: middle; }
  .r  { text-align: right; font-family: 'Courier New', monospace; }
  .c  { text-align: center; }
  .grupo-header td {
    background-color: #4a5e3a !important;
    color: #fff;
    font-weight: bold;
    font-size: 7.5px;
    text-transform: uppercase;
    border: 1px solid #3b4a2e;
  }
  .subtotal-row td {
    background-color: #d0e0c8 !important;
    font-weight: bold;
    border-top: 1px solid #4a5e3a;
  }
  .total-row td {
    background-color: #4a5e3a !important;
    color: #fff;
    font-weight: bold;
    font-size: 8px;
    border-top: 2px solid #2e3d22;
  }
  tr:nth-child(even) td { background-color: #f7f9f4; }
</style>
</head>
<body>

{{-- Encabezado estándar --}}
<table style="margin-bottom:8px; border:none;">
  <tr>
    <td style="width:13%; text-align:center; vertical-align:middle; border:none;">
      @if($logo)<img src="{{ $logo }}" style="max-height:60px; max-width:90px;">@endif
    </td>
    <td style="text-align:center; vertical-align:middle; border:none; padding:0 10px;">
      <div style="font-size:10pt; font-weight:bold; text-transform:uppercase;">{{ $nombreInst }}</div>
      <div style="font-size:9pt; font-weight:bold; text-transform:uppercase; margin-top:3px;">INVENTARIO VALORIZADO</div>
      <div style="font-size:8pt; margin-top:2px;">
        {{ $solo_existencias ? 'Solo artículos con existencias' : 'Todos los artículos' }}
        &nbsp;·&nbsp;
        {{ $tipo === 'agrupado' ? 'Vista agrupada por categoría' : 'Vista por código' }}
        &nbsp;·&nbsp;
        {{ now()->format('d/m/Y H:i') }}
      </div>
    </td>
  </tr>
</table>

@php
  $fmtNum  = fn($v) => number_format((float)$v, 2, '.', ',');
  $fmtPric = fn($v) => number_format((float)$v, 4, '.', ',');
@endphp

@if($tipo === 'agrupado')

{{-- ====== VISTA AGRUPADA ====== --}}
<table>
  <thead>
    <tr>
      <th style="width:10%">Código</th>
      <th style="width:44%">Descripción</th>
      <th style="width:8%">Unidad</th>
      <th style="width:9%" class="r">Stock</th>
      <th style="width:13%" class="r">Precio Unit.</th>
      <th style="width:16%" class="r">Valor Total</th>
    </tr>
  </thead>
  <tbody>
    @foreach($grupos as $grupo)
      <tr class="grupo-header">
        <td colspan="6">{{ $grupo['descripcion'] }}</td>
      </tr>
      @foreach($grupo['articulos'] as $a)
      <tr>
        <td class="c" style="font-family:monospace;">{{ $a->codigo }}</td>
        <td>{{ $a->nombre }}</td>
        <td class="c">{{ $a->unidad_medida }}</td>
        <td class="r">{{ $fmtNum($a->stock_actual) }}</td>
        <td class="r">{{ $fmtPric($a->precio_unitario) }}</td>
        <td class="r">{{ $fmtNum($a->valor_total) }}</td>
      </tr>
      @endforeach
      <tr class="subtotal-row">
        <td colspan="5" style="text-align:right; padding-right:8px;">Subtotal</td>
        <td class="r">{{ $fmtNum($grupo['subtotal']) }}</td>
      </tr>
    @endforeach
    <tr class="total-row">
      <td colspan="5" style="text-align:right; padding-right:8px;">TOTAL GENERAL</td>
      <td class="r">{{ $fmtNum($total_general) }}</td>
    </tr>
  </tbody>
</table>

@else

{{-- ====== VISTA PLANA ====== --}}
<table>
  <thead>
    <tr>
      <th style="width:10%">Código</th>
      <th style="width:44%">Descripción</th>
      <th style="width:8%">Unidad</th>
      <th style="width:9%" class="r">Stock</th>
      <th style="width:13%" class="r">Precio Unit.</th>
      <th style="width:16%" class="r">Valor Total</th>
    </tr>
  </thead>
  <tbody>
    @foreach($articulos as $a)
    <tr>
      <td class="c" style="font-family:monospace;">{{ $a->codigo }}</td>
      <td>{{ $a->nombre }}</td>
      <td class="c">{{ $a->unidad_medida }}</td>
      <td class="r">{{ $fmtNum($a->stock_actual) }}</td>
      <td class="r">{{ $fmtPric($a->precio_unitario) }}</td>
      <td class="r">{{ $fmtNum($a->valor_total) }}</td>
    </tr>
    @endforeach
    <tr class="total-row">
      <td colspan="5" style="text-align:right; padding-right:8px;">TOTAL GENERAL</td>
      <td class="r">{{ $fmtNum($total_general) }}</td>
    </tr>
  </tbody>
</table>

@endif

<p style="font-size:7pt; color:#555; margin-top:10px; text-align:right;">
  Generado por: {{ strtoupper($generadoPor) }} &nbsp;&nbsp;|&nbsp;&nbsp; {{ now()->format('d/m/Y H:i') }}
</p>

</body>
</html>
