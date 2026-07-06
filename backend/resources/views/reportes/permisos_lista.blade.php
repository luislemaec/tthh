<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; font-size: 8pt; margin: 0; }
  @page { margin: 1.2cm 1cm; }
  table { width: 100%; border-collapse: collapse; table-layout: fixed; }
  th, td { border: 1px solid #ccc; padding: 3px 5px; vertical-align: middle; }
  th { background-color: #0b5447; color: #fff; font-weight: bold; text-align: center; }
  tr:nth-child(even) td { background-color: #f4fbf8; }
  .header-table { border: none; margin-bottom: 10px; }
  .header-table td { border: none; }
  .generado { font-size: 7.5pt; color: #555; margin-top: 12px; text-align: right; }
  .badge { display: inline-block; padding: 1px 5px; border-radius: 3px; font-size: 7.5pt; font-weight: bold; }
  .badge-aprobado  { background: #d1fae5; color: #065f46; }
  .badge-pendiente { background: #fef3c7; color: #92400e; }
  .badge-negado    { background: #fee2e2; color: #991b1b; }
  .badge-eliminado { background: #f3f4f6; color: #374151; }
  .badge-anulado   { background: #ffedd5; color: #9a3412; }
</style>
</head>
<body>

{{-- Encabezado --}}
<table class="header-table">
  <tr>
    <td style="width:12%; text-align:center; vertical-align:middle;">
      @if($logo)<img src="data:image/png;base64,{{ $logo }}" style="max-height:70px; max-width:100px;">@endif
    </td>
    <td style="text-align:center; vertical-align:middle; padding:0 10px;">
      <div style="font-size:11pt; font-weight:bold; text-transform:uppercase;">{{ $nombreInst }}</div>
      <div style="font-size:10pt; font-weight:bold; margin-top:3px;">REPORTE DE PERMISOS Y LICENCIAS</div>
    </td>
  </tr>
</table>

{{-- Tabla de datos --}}
<table>
  <thead>
    <tr>
      <th style="width:4%;">N°</th>
      <th style="width:20%;">Empleado</th>
      <th style="width:17%;">Departamento</th>
      <th style="width:17%;">Razón</th>
      <th style="width:9%;">Tipo Horario</th>
      <th style="width:8%;">Fecha Desde</th>
      <th style="width:8%;">Fecha Hasta</th>
      <th style="width:6%;">Todo el Día</th>
      <th style="width:6%;">Descontable</th>
      <th style="width:8%;">Estado</th>
    </tr>
  </thead>
  <tbody>
    @forelse($filas as $i => $f)
    <tr>
      <td style="text-align:center;">{{ $i + 1 }}</td>
      <td>{{ $f['empleado'] }}</td>
      <td>{{ $f['depto'] }}</td>
      <td>{{ $f['razon'] }}</td>
      <td style="text-align:center;">{{ $f['tipo_horario'] }}</td>
      <td style="text-align:center;">{{ $f['fecha_desde'] }}</td>
      <td style="text-align:center;">{{ $f['fecha_hasta'] }}</td>
      <td style="text-align:center;">{{ $f['todo_dia'] }}</td>
      <td style="text-align:center;">{{ $f['descontable'] }}</td>
      <td style="text-align:center;">
        @php
          $clase = match(strtolower($f['estado'])) {
            'aprobado'  => 'badge-aprobado',
            'pendiente' => 'badge-pendiente',
            'negado'    => 'badge-negado',
            'eliminado' => 'badge-eliminado',
            'anulado'   => 'badge-anulado',
            default     => 'badge-eliminado',
          };
        @endphp
        <span class="badge {{ $clase }}">{{ $f['estado'] }}</span>
      </td>
    </tr>
    @empty
    <tr><td colspan="10" style="text-align:center; color:#888;">No hay registros</td></tr>
    @endforelse
  </tbody>
</table>

<p class="generado">
  Generado por: {{ strtoupper($generadoPor) }} &nbsp;&nbsp;|&nbsp;&nbsp; {{ now()->format('d/m/Y H:i') }}
</p>
</body>
</html>
