<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #1a1a1a; }

  .titulo {
    text-align: center;
    font-size: 11px;
    font-weight: bold;
    text-transform: uppercase;
    margin-bottom: 12px;
    color: #0b5447;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 0;
  }

  thead tr.header-main th {
    background-color: #0b5447;
    color: #fff;
    font-weight: bold;
    padding: 5px 4px;
    text-align: center;
    border: 1px solid #579186;
  }

  thead tr.header-sub th {
    color: #fff;
    font-weight: normal;
    padding: 3px 4px;
    text-align: center;
    border: 1px solid rgba(255,255,255,0.3);
  }

  th.p1 { background-color: #1d4ed8; }
  th.p2 { background-color: #0f766e; }
  th.p3 { background-color: #b45309; }
  th.p4 { background-color: #c2410c; }

  tbody tr.depto-header td {
    background-color: #e8f5f2;
    font-weight: bold;
    color: #0b5447;
    font-size: 8.5px;
    padding: 4px 6px;
    border: 1px solid #95d0c7;
  }

  tbody tr.fila-emp td {
    padding: 3px 4px;
    border: 1px solid #d1d5db;
    text-align: center;
    vertical-align: middle;
  }

  tbody tr.fila-emp td.nombre {
    text-align: left;
  }

  tbody tr.fila-emp:nth-child(even) {
    background-color: #f9fafb;
  }

  tbody tr.separador td {
    padding: 4px;
    border: none;
    background: white;
  }

  .total-cell {
    font-weight: bold;
    color: #0b5447;
  }

  .pie {
    margin-top: 24px;
    font-size: 8px;
    color: #374151;
  }

  .pie-firma {
    margin-top: 36px;
    text-align: center;
  }

  .pie-firma .linea {
    display: inline-block;
    width: 260px;
    border-top: 1px solid #374151;
    margin-bottom: 4px;
  }

  .pie-fecha {
    text-align: right;
    margin-bottom: 12px;
    font-style: italic;
    color: #6b7280;
  }
</style>
</head>
<body>

<p class="titulo">Planificación Anual de Vacaciones &mdash; Período {{ $anio }}</p>
<p class="pie-fecha">Fecha de emisión: {{ $fechaHoy }}</p>

<table>
  <thead>
    <tr class="header-main">
      <th rowspan="2" style="width:3%">N°</th>
      <th rowspan="2" style="width:22%; text-align:left">Apellidos y Nombres</th>
      <th colspan="3" class="p1">1er Período</th>
      <th colspan="3" class="p2">2do Período</th>
      <th colspan="3" class="p3">3er Período</th>
      <th colspan="3" class="p4">4to Período</th>
      <th rowspan="2" style="width:5%">Total</th>
    </tr>
    <tr class="header-sub">
      <th class="p1">Desde</th><th class="p1">Hasta</th><th class="p1">Días</th>
      <th class="p2">Desde</th><th class="p2">Hasta</th><th class="p2">Días</th>
      <th class="p3">Desde</th><th class="p3">Hasta</th><th class="p3">Días</th>
      <th class="p4">Desde</th><th class="p4">Hasta</th><th class="p4">Días</th>
    </tr>
  </thead>
  <tbody>
    @foreach($grupos as $grupo)
      <tr class="depto-header">
        <td colspan="15">{{ $grupo['nombre_depto'] }}</td>
      </tr>
      @foreach($grupo['filas'] as $i => $fila)
        <tr class="fila-emp">
          <td>{{ $i + 1 }}</td>
          <td class="nombre">{{ $fila['nombre'] }}</td>
          @for($n = 0; $n < 4; $n++)
            @php $p = $fila['periodos'][$n]; @endphp
            <td>{{ ($p && $p->fecha_inicial) ? \Carbon\Carbon::parse($p->fecha_inicial)->format('d/m/Y') : '—' }}</td>
            <td>{{ ($p && $p->fecha_final) ? \Carbon\Carbon::parse($p->fecha_final)->format('d/m/Y') : '—' }}</td>
            <td>{{ ($p && $p->dias_calculados) ? (int)$p->dias_calculados : '—' }}</td>
          @endfor
          <td class="total-cell">{{ (int)$fila['total'] }}</td>
        </tr>
      @endforeach
      <tr class="separador"><td colspan="15">&nbsp;</td></tr>
    @endforeach
  </tbody>
</table>

<div class="pie">
  <div class="pie-firma" style="margin-top: 40px;">
    <div class="linea"></div><br>
    <strong>{{ $coordinador }}</strong>
  </div>
</div>

</body>
</html>
