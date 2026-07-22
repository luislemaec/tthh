<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, sans-serif; font-size: 8pt; color: #000; }
  @page { margin: 10mm 12mm 10mm 12mm; size: a4 landscape; }
  table { border-collapse: collapse; width: 100%; table-layout: fixed; }

  .title { text-align: center; font-weight: bold; font-size: 11pt; text-transform: uppercase; padding: 4px 0; }
  .subtitle { text-align: center; font-size: 9pt; margin-bottom: 8px; }

  .datos th { border: 1px solid #555; padding: 4px 5px; background-color: #4d7c8a; color: #fff;
              text-transform: uppercase; font-size: 7.5pt; text-align: left; }
  .datos td { border: 1px solid #555; padding: 3px 5px; font-size: 7.5pt; word-wrap: break-word; }
</style>
</head>
<body>

<table style="margin-bottom:8px;">
  <tr>
    <td style="width:12%; text-align:center; vertical-align:middle;">
      @if($logo)
        <img src="{{ $logo }}" style="max-height:90px; max-width:130px;">
      @endif
    </td>
    <td style="text-align:center; vertical-align:middle;">
      <div style="font-size:10pt; font-weight:bold; text-transform:uppercase;">{{ $nombreInst }}</div>
      <div class="title" style="margin-top:4px;">REPORTE DE EQUIPOS TECNOLÓGICOS</div>
      <div class="subtitle">{{ count($filas) }} equipo{{ count($filas) === 1 ? '' : 's' }} encontrado{{ count($filas) === 1 ? '' : 's' }}</div>
    </td>
  </tr>
</table>

<table class="datos">
  <thead>
    <tr>
      <th style="width:8%;">Código</th>
      <th style="width:10%;">Tipo</th>
      <th style="width:8%;">Marca</th>
      <th style="width:14%;">Modelo</th>
      <th style="width:14%;">Descripción</th>
      <th style="width:10%;">Serie</th>
      <th style="width:6%;">Condición</th>
      <th style="width:7%;">Estado</th>
      <th style="width:7%;">Vida Útil</th>
      <th style="width:12%;">Custodio</th>
      <th style="width:4%;">Piezas</th>
    </tr>
  </thead>
  <tbody>
    @foreach($filas as $i => $f)
    <tr style="{{ $i % 2 === 1 ? 'background-color:#f9fafb;' : '' }}">
      <td>{{ $f['codigo_bien'] }}</td>
      <td>{{ $f['tipo'] }}</td>
      <td>{{ $f['marca'] }}</td>
      <td>{{ $f['modelo'] }}</td>
      <td>{{ $f['descripcion'] }}</td>
      <td>{{ $f['serie'] }}</td>
      <td>{{ $f['condicion'] }}</td>
      <td>{{ $f['estado'] }}</td>
      <td>{{ $f['vida_util'] }}</td>
      <td>{{ $f['custodio'] }}</td>
      <td style="text-align:center;">{{ $f['piezas'] }}</td>
    </tr>
    @endforeach
  </tbody>
</table>

<p style="font-size:7.5pt; color:#555; margin-top:12px; text-align:right;">
  Generado por: {{ strtoupper($generadoPor) }} &nbsp;&nbsp;|&nbsp;&nbsp; {{ now()->format('d/m/Y H:i') }}
</p>

</body>
</html>
