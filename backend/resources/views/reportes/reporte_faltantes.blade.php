<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 15mm 12mm; }
body { font-family: Arial, sans-serif; font-size: 7.5pt; color: #222; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
th { background-color: #0b5447; color: #fff; padding: 5px 4px; text-align: center; font-size: 7pt; }
td { padding: 4px; border-bottom: 1px solid #e5e7eb; }
tr:nth-child(even) td { background-color: #f4fbf8; }
.ok    { color: #16a34a; font-weight: bold; }
.falta { background:#fee2e2; color:#991b1b; padding:1px 5px; border-radius:3px; font-size:6.5pt; }
.center { text-align: center; }
.bold   { font-weight: bold; }
.footer { margin-top: 12px; text-align: right; font-size: 7pt; color: #555; }
</style>
</head>
<body>

<table style="margin-bottom:10px;">
  <tr>
    <td style="width:13%; text-align:center; vertical-align:middle;">
      @if($logo)<img src="data:image/png;base64,{{ $logo }}" style="max-height:70px; max-width:100px;">@endif
    </td>
    <td style="text-align:center; vertical-align:middle;">
      <div style="font-size:10pt; font-weight:bold; text-transform:uppercase;">{{ $nombreInst }}</div>
      <div style="font-size:9pt; font-weight:bold; text-transform:uppercase; margin-top:3px;">REPORTE DE MARCACIONES NO REALIZADAS</div>
      <div style="font-size:7.5pt; color:#555; margin-top:2px;">
        Período: {{ $request->fecha_desde }} al {{ $request->fecha_hasta }}
      </div>
    </td>
  </tr>
</table>

<table>
  <thead>
    <tr>
      <th style="width:8%">Fecha</th>
      <th style="width:22%">Empleado</th>
      <th style="width:20%">Departamento</th>
      <th style="width:12%" class="center">Entrada</th>
      <th style="width:13%" class="center">Sal. Almuerzo</th>
      <th style="width:13%" class="center">Ent. Almuerzo</th>
      <th style="width:12%" class="center">Salida</th>
    </tr>
  </thead>
  <tbody>
    @forelse($datos as $r)
    <tr>
      <td>{{ $r['fecha'] }}</td>
      <td class="bold">{{ $r['nombre_completo'] }}</td>
      <td style="color:#555;">{{ $r['nombre_depto'] }}</td>
      <td class="center">
        @if($r['tiene_entrada']) <span class="ok">✓</span>
        @else <span class="falta">No registró</span> @endif
      </td>
      <td class="center">
        @if($r['tiene_sal_lunch']) <span class="ok">✓</span>
        @else <span class="falta">No registró</span> @endif
      </td>
      <td class="center">
        @if($r['tiene_ent_lunch']) <span class="ok">✓</span>
        @else <span class="falta">No registró</span> @endif
      </td>
      <td class="center">
        @if($r['tiene_salida']) <span class="ok">✓</span>
        @else <span class="falta">No registró</span> @endif
      </td>
    </tr>
    @empty
    <tr><td colspan="7" style="text-align:center; color:#aaa; padding:12px;">Sin registros para el período</td></tr>
    @endforelse
  </tbody>
</table>

<p class="footer">
  Total: <strong>{{ count($datos) }} registros</strong> &nbsp;&nbsp;|&nbsp;&nbsp;
  Generado por: {{ strtoupper($generadoPor) }} &nbsp;&nbsp;|&nbsp;&nbsp; {{ now()->format('d/m/Y H:i') }}
</p>
</body>
</html>
