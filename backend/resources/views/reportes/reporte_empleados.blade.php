<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 12mm 10mm; }
* { box-sizing: border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 7pt; color: #1a1a1a; margin: 0; }

.header-table { width: 100%; margin-bottom: 8px; border-collapse: collapse; }
.header-table td { padding: 2px 6px; vertical-align: middle; }
.inst-name { font-size: 10pt; font-weight: bold; text-transform: uppercase; text-align: center; }
.report-title { font-size: 9pt; font-weight: bold; text-transform: uppercase; text-align: center; margin-top: 2px; color: #0b5447; }
.report-sub { font-size: 7pt; text-align: center; color: #555; margin-top: 2px; }

.divider { border-top: 2px solid #0b5447; margin-bottom: 6px; }

table.main { width: 100%; border-collapse: collapse; table-layout: fixed; }
table.main thead tr { background-color: #0b5447; color: #fff; }
table.main thead th { padding: 4px 3px; font-size: 6.5pt; font-weight: bold; text-align: center; border: 1px solid #0b5447; }
table.main tbody tr:nth-child(even) { background-color: #edf7f4; }
table.main tbody tr:nth-child(odd)  { background-color: #ffffff; }
table.main tbody td { padding: 3px 3px; border: 1px solid #d1d5db; font-size: 6.5pt; vertical-align: top; }

.badge-red    { background: #fee2e2; color: #991b1b; padding: 1px 4px; border-radius: 3px; font-size: 6pt; }
.badge-green  { background: #d1fae5; color: #065f46; padding: 1px 4px; border-radius: 3px; font-size: 6pt; }
.badge-yellow { background: #fef3c7; color: #92400e; padding: 1px 4px; border-radius: 3px; font-size: 6pt; }
.text-center  { text-align: center; }
.text-right   { text-align: right; }
.fw-bold      { font-weight: bold; }

.footer { margin-top: 8px; font-size: 7pt; color: #555; text-align: right; }
.total-row { background-color: #e8f5f2 !important; font-weight: bold; }
</style>
</head>
<body>

{{-- Encabezado estándar --}}
<table class="header-table">
  <tr>
    <td style="width:12%; text-align:center;">
      @if($logo)<img src="data:image/png;base64,{{ $logo }}" style="max-height:70px; max-width:100px;">@endif
    </td>
    <td>
      <div class="inst-name">{{ $nombreInst }}</div>
      <div class="report-title">Nómina de Personal</div>
      <div class="report-sub">Generado: {{ now()->format('d/m/Y H:i') }} &nbsp;|&nbsp; Total: {{ count($empleados) }} empleado(s)</div>
    </td>
  </tr>
</table>
<div class="divider"></div>

<table class="main">
  <thead>
    <tr>
      <th style="width:3%">#</th>
      <th style="width:7%">Cédula</th>
      <th style="width:16%">Apellidos y Nombres</th>
      <th style="width:13%">Departamento</th>
      <th style="width:11%">Cargo</th>
      <th style="width:8%">Contrato</th>
      <th style="width:5%">Sexo</th>
      <th style="width:5%">T.Sangre</th>
      <th style="width:6%">Discap.</th>
      <th style="width:9%">SERCOP Vigencia</th>
      <th style="width:9%">Sust. Vence</th>
      <th style="width:4%">H&lt;5</th>
      <th style="width:4%">Veh.</th>
    </tr>
  </thead>
  <tbody>
    @forelse($empleados as $i => $e)
    <tr>
      <td class="text-center">{{ $i + 1 }}</td>
      <td class="text-center">{{ $e->cedula }}</td>
      <td class="fw-bold">{{ $e->apellido_emp }} {{ $e->nombre_emp }}</td>
      <td>{{ $e->nombre_depto ?? '—' }}</td>
      <td>{{ $e->cargo_empleado ?? '—' }}</td>
      <td class="text-center" style="font-size:6pt;">{{ trim($e->tipo_contrato ?? '—') }}</td>
      <td class="text-center">{{ $e->sexo ? substr($e->sexo,0,1) : '—' }}</td>
      <td class="text-center">{{ $e->tipo_sangre ?? '—' }}</td>
      <td class="text-center">
        @if($e->tiene_discapacidad)
          <span class="badge-yellow">{{ $e->porcentaje_discapacidad ?? '' }}%</span>
        @else —
        @endif
      </td>
      <td class="text-center">
        @if($e->fecha_vence_sercop)
          @php $vence = \Carbon\Carbon::parse($e->fecha_vence_sercop); @endphp
          <span class="{{ $vence->isPast() ? 'badge-red' : ($vence->diffInDays() <= 30 ? 'badge-yellow' : 'badge-green') }}">
            {{ $vence->format('d/m/Y') }}
          </span>
        @else —
        @endif
      </td>
      <td class="text-center">
        @if($e->sustituta_fecha_caducidad)
          @php $cad = \Carbon\Carbon::parse($e->sustituta_fecha_caducidad); @endphp
          <span class="{{ $cad->isPast() ? 'badge-red' : ($cad->diffInDays() <= 30 ? 'badge-yellow' : 'badge-green') }}">
            {{ $cad->format('d/m/Y') }}
          </span>
        @else —
        @endif
      </td>
      <td class="text-center">{{ ($e->hijos_menores_5 ?? 0) > 0 ? $e->hijos_menores_5 : '—' }}</td>
      <td class="text-center">{{ $e->puede_solicitar_vehiculo ? 'Sí' : 'No' }}</td>
    </tr>
    @empty
    <tr><td colspan="13" style="text-align:center; padding:12px; color:#888;">Sin resultados</td></tr>
    @endforelse
    @if(count($empleados))
    <tr class="total-row">
      <td colspan="13" style="text-align:right; padding:3px 6px;">
        Total: {{ count($empleados) }} empleado(s)
      </td>
    </tr>
    @endif
  </tbody>
</table>

<p class="footer">
  Generado por: {{ strtoupper($generadoPor) }} &nbsp;&nbsp;|&nbsp;&nbsp; {{ now()->format('d/m/Y H:i') }}
</p>
</body>
</html>
