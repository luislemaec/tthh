<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, sans-serif; font-size: 9pt; color: #000; }
  .page { width: 100%; }

  @page { margin: 12mm 15mm 12mm 15mm; size: letter portrait; }

  table { border-collapse: collapse; width: 100%; table-layout: fixed; }

  .title-section {
    text-align: center;
    font-weight: bold;
    font-size: 11pt;
    text-transform: uppercase;
    padding: 4px 0;
  }
  .subtitle {
    text-align: center;
    font-size: 9pt;
    margin-bottom: 10px;
  }

  .info-table td {
    padding: 3px 6px;
    font-size: 8.5pt;
    vertical-align: top;
  }
  .info-label { font-weight: bold; width: 28%; }
  .info-value { width: 22%; }

  .he-table th {
    background-color: #d8d8d8;
    border: 1px solid #000;
    padding: 5px 4px;
    font-size: 8.5pt;
    font-weight: bold;
    text-align: center;
  }
  .he-table td {
    border: 1px solid #000;
    padding: 4px 6px;
    font-size: 8.5pt;
    vertical-align: top;
  }
  .he-table td.num { text-align: center; }
  .he-table td.r   { text-align: right; }
  .he-table tr.total-row td {
    font-weight: bold;
    background-color: #efefef;
    text-align: center;
  }
  .he-table tr.total-row td:first-child {
    text-align: right;
  }
  .badge-aprobado { color: #166534; font-weight: bold; }
  .badge-pendiente { color: #92400e; }
  .badge-revision { color: #1e3a5f; }
  .badge-negado { color: #991b1b; }

  .nota { font-size: 7.5pt; color: #444; margin-top: 6px; }
</style>
</head>
<body>

@php
  $nombreMes  = $meses[$cab->mes] ?? $cab->mes;
  $nombreInst = $config['nombre_institucion'] ?? 'CONSEJO DE COMUNICACIÓN';
  $nombreEmp  = strtoupper(($emp->apellido_emp ?? '') . ' ' . ($emp->nombre_emp ?? ''));
  $cargo      = $emp->cargo_empleado ?? '';
  $depto      = $emp->departamento->nombre_depto ?? '';

  $aprobados = $registros->where('estado', 'APROBADO');
  $totalExtra = $aprobados->sum('horas_extraordinarias');
  $totalSupl  = $aprobados->sum('horas_suplementarias');

  $diasMes = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
  $mesesEs = ['','enero','febrero','marzo','abril','mayo','junio',
              'julio','agosto','septiembre','octubre','noviembre','diciembre'];
@endphp

<div class="page">

{{-- ENCABEZADO --}}
<table style="margin-bottom:8px;">
  <tr>
    <td style="width:18%; text-align:center; vertical-align:middle;">
      @if($logo)
        <img src="{{ $logo }}" style="max-height:55px; max-width:80px;">
      @endif
    </td>
    <td style="width:64%; text-align:center; vertical-align:middle; padding:4px;">
      <div style="font-size:9pt; font-weight:bold; text-transform:uppercase;">{{ $nombreInst }}</div>
      <div style="font-size:8pt; margin-top:2px;">DIRECCIÓN ADMINISTRATIVA DE TALENTO HUMANO</div>
      <div class="title-section" style="margin-top:5px;">HORAS EXTRAS TRABAJADAS</div>
      <div class="subtitle">Mes: {{ $nombreMes }} &nbsp;|&nbsp; Año: {{ $cab->anio }}</div>
    </td>
    <td style="width:18%; text-align:right; vertical-align:top; padding:4px; font-size:7.5pt;">
      <div><b>Estado plan:</b> {{ $cab->estado }}</div>
      @if($cab->memorando)
      <div style="margin-top:2px;"><b>Memorando:</b> {{ $cab->memorando }}</div>
      @endif
    </td>
  </tr>
</table>

{{-- DATOS DEL EMPLEADO --}}
<table class="info-table" style="border: 1px solid #000; margin-bottom:10px;">
  <tr>
    <td style="background:#d8d8d8; font-weight:bold; font-size:8pt;" colspan="4">DATOS DEL SERVIDOR/A PÚBLICO/A</td>
  </tr>
  <tr>
    <td class="info-label">Apellidos y Nombres:</td>
    <td class="info-value" colspan="3">{{ $nombreEmp }}</td>
  </tr>
  <tr>
    <td class="info-label">Cédula de Identidad:</td>
    <td class="info-value">{{ $emp->identificacion }}</td>
    <td class="info-label">Cargo:</td>
    <td class="info-value">{{ $cargo }}</td>
  </tr>
  <tr>
    <td class="info-label">Unidad / Departamento:</td>
    <td class="info-value" colspan="3">{{ $depto }}</td>
  </tr>
</table>

{{-- TABLA DE REGISTROS --}}
<table class="he-table" style="margin-bottom:10px;">
  <thead>
    <tr>
      <th style="width:5%;">N°</th>
      <th style="width:14%;">Fecha</th>
      <th style="width:10%;">Inicio</th>
      <th style="width:10%;">Fin</th>
      <th style="width:35%;">Descripción</th>
      <th style="width:13%;">H. Extra.</th>
      <th style="width:13%;">H. Supl.</th>
    </tr>
  </thead>
  <tbody>
    @forelse($registros->where('estado', 'APROBADO') as $i => $reg)
    @php
      $fecha = \Carbon\Carbon::parse($reg->fecha);
    @endphp
    <tr>
      <td class="num">{{ $i + 1 }}</td>
      <td class="num">{{ $fecha->format('d/m/Y') }}</td>
      <td class="num">{{ substr($reg->hora_inicio, 0, 5) }}</td>
      <td class="num">{{ substr($reg->hora_fin, 0, 5) }}</td>
      <td>{{ $reg->descripcion ?? '-' }}</td>
      <td class="num">{{ $reg->horas_extraordinarias > 0 ? number_format($reg->horas_extraordinarias, 2) : '-' }}</td>
      <td class="num">{{ $reg->horas_suplementarias > 0 ? number_format($reg->horas_suplementarias, 2) : '-' }}</td>
    </tr>
    @empty
    <tr>
      <td colspan="7" style="text-align:center; padding:8px;">No hay registros aprobados</td>
    </tr>
    @endforelse
    <tr class="total-row">
      <td colspan="5">TOTAL HORAS TRABAJADAS</td>
      <td>{{ number_format($totalExtra, 2) }}</td>
      <td>{{ number_format($totalSupl, 2) }}</td>
    </tr>
  </tbody>
</table>

{{-- NOTA --}}
<p class="nota">
  * Este reporte incluye únicamente los registros en estado <b>APROBADO</b>.<br>
  Planificado: {{ number_format($cab->total_extraordinarias, 2) }} h. extraordinarias &nbsp;|&nbsp;
  {{ number_format($cab->total_suplementarias, 2) }} h. suplementarias
</p>

</div>
</body>
</html>
