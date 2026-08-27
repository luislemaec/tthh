<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, sans-serif; font-size: 9pt; color: #000; }
  @page { margin: 12mm 15mm 12mm 15mm; size: A4 portrait; }
  table { border-collapse: collapse; width: 100%; table-layout: fixed; }

  .section-title { font-weight: bold; font-size: 9pt; text-transform: uppercase;
                   background-color: #0b5447; color: #fff; padding: 4px 8px; margin: 10px 0 4px 0; }

  .data-table th { background-color: #0b5447; color: #fff; padding: 5px 8px;
                   font-size: 8pt; font-weight: bold; text-align: center; border: 1px solid #aaa; }
  .data-table td { border: 1px solid #ccc; padding: 4px 8px; font-size: 8pt; vertical-align: middle; }
  .data-table tr:nth-child(even) td { background-color: #f5f5f5; }

  .saldo-ok   { color: #1a7a2e; font-weight: bold; }
  .saldo-warn { color: #b45309; font-weight: bold; }
  .saldo-bad  { color: #c0392b; font-weight: bold; }

  .footer { font-size: 7.5pt; color: #555; margin-top: 14px; text-align: right; }
</style>
</head>
<body>

{{-- ── Encabezado estándar ── --}}
<table style="margin-bottom:10px;">
  <tr>
    <td style="width:15%; vertical-align:middle; text-align:center;">
      @if($logo)
        <img src="{{ $logo }}" style="max-height:80px; max-width:110px;">
      @endif
    </td>
    <td style="text-align:center; vertical-align:middle; padding:0 10px;">
      <div style="font-size:11pt; font-weight:bold; text-transform:uppercase;">{{ $nombreInst }}</div>
      <div style="font-size:10pt; font-weight:bold; text-transform:uppercase; margin-top:3px;">REPORTE DE SALDO DE VACACIONES</div>
      @if($departamento)
      <div style="font-size:8.5pt; color:#444; margin-top:2px;">Departamento: {{ strtoupper($departamento) }}</div>
      @endif
      <div style="font-size:8pt; color:#666; margin-top:2px;">Al {{ now()->format('d/m/Y') }}</div>
    </td>
  </tr>
</table>

{{-- ── Tabla de datos ── --}}
<table class="data-table">
  <thead>
    <tr>
      <th style="width:32%; text-align:left;">Empleado</th>
      <th style="width:28%; text-align:left;">Departamento</th>
      <th style="width:18%;">Contrato</th>
      <th style="width:11%;">Tomados</th>
      <th style="width:11%;">Saldo</th>
    </tr>
  </thead>
  <tbody>
    @foreach($filas as $f)
    <tr>
      <td>{{ strtoupper($f['nombre_completo']) }}</td>
      <td>{{ strtoupper($f['departamento']) }}</td>
      <td style="text-align:center;">{{ $f['tipo_contrato'] }}</td>
      <td style="text-align:center;">{{ number_format($f['tomados'], 2) }}</td>
      <td style="text-align:center;">
        @php
          // Nombramiento Definitivo con saldo real negativo (migración 000100): se muestra el
          // real, no el 0 con piso — mismo criterio que Dashboard y VacacionesView.vue.
          $esNegativoReal = ($f['modalidad_laboral'] ?? null) === 'Nombramiento Definitivo' && ($f['saldo_actual_real'] ?? 0) < 0;
          $s = $esNegativoReal ? $f['saldo_actual_real'] : $f['saldo_actual'];
          $cls = $s <= 0 ? 'saldo-bad' : ($s < 5 ? 'saldo-warn' : 'saldo-ok');
        @endphp
        <span class="{{ $cls }}">{{ number_format($s, 2) }}</span>
      </td>
    </tr>
    @endforeach
  </tbody>
  <tfoot>
    <tr>
      <td colspan="3" style="font-weight:bold; text-align:right; padding:5px 8px; border:1px solid #ccc;">
        Total empleados: {{ count($filas) }}
      </td>
      <td style="text-align:center; font-weight:bold; border:1px solid #ccc;">
        {{ number_format($filas->sum('tomados'), 2) }}
      </td>
      <td style="text-align:center; font-weight:bold; border:1px solid #ccc;">
        {{ number_format($filas->sum('saldo_actual'), 2) }}
      </td>
    </tr>
  </tfoot>
</table>

{{-- ── Pie estándar (sin firma, solo generado por) ── --}}
<p class="footer">
  Generado por: {{ strtoupper($generadoPor) }} &nbsp;&nbsp;|&nbsp;&nbsp; {{ now()->format('d/m/Y H:i') }}
</p>

</body>
</html>
