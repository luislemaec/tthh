<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1a1a1a; }

  .titulo {
    text-align: center;
    font-size: 13px;
    font-weight: bold;
    text-transform: uppercase;
    margin-bottom: 4px;
    color: #0b5447;
  }

  .subtitulo {
    text-align: center;
    font-size: 10px;
    color: #374151;
    margin-bottom: 20px;
  }

  .fecha-emision {
    text-align: right;
    font-style: italic;
    color: #6b7280;
    margin-bottom: 16px;
    font-size: 8px;
  }

  .seccion {
    margin-bottom: 16px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    overflow: hidden;
  }

  .seccion-titulo {
    background-color: #0b5447;
    color: #fff;
    font-weight: bold;
    padding: 5px 10px;
    font-size: 9px;
    text-transform: uppercase;
  }

  .seccion-body {
    padding: 10px;
  }

  table.datos {
    width: 100%;
    border-collapse: collapse;
  }

  table.datos td {
    padding: 4px 8px;
    border-bottom: 1px solid #f3f4f6;
    vertical-align: top;
  }

  table.datos td.label {
    font-weight: bold;
    color: #374151;
    width: 35%;
  }

  table.saldo {
    width: 100%;
    border-collapse: collapse;
    margin-top: 4px;
  }

  table.saldo th {
    background-color: #1d4ed8;
    color: #fff;
    padding: 5px 10px;
    text-align: center;
    font-size: 9px;
  }

  table.saldo td {
    padding: 5px 10px;
    text-align: center;
    border: 1px solid #d1d5db;
  }

  table.saldo td.total {
    font-weight: bold;
    font-size: 11px;
    color: #0b5447;
  }

  .motivo-badge {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 3px;
    font-weight: bold;
    font-size: 9px;
    color: #fff;
  }

  .badge-DESVINCULACION  { background-color: #dc2626; }
  .badge-COMISION_SALIDA { background-color: #b45309; }
  .badge-COMISION_RETORNO { background-color: #0f766e; }
  .badge-NUEVO_INGRESO   { background-color: #1d4ed8; }

  .observacion {
    margin-top: 8px;
    padding: 6px 10px;
    background-color: #f9fafb;
    border-left: 3px solid #0b5447;
    font-style: italic;
    color: #374151;
  }

  .pie {
    margin-top: 40px;
    text-align: center;
  }

  .pie .linea {
    display: inline-block;
    width: 260px;
    border-top: 1px solid #374151;
    margin-bottom: 4px;
  }
</style>
</head>
<body>

<p class="titulo">
  @if($historico->motivo === 'DESVINCULACION')
    Reporte de Liquidación de Vacaciones
  @elseif($historico->motivo === 'COMISION_SALIDA')
    Certificado de Saldo de Vacaciones — Comisión de Servicios
  @elseif($historico->motivo === 'COMISION_RETORNO')
    Constancia de Saldo Inicial — Retorno de Comisión
  @else
    Constancia de Vacaciones
  @endif
</p>
<p class="subtitulo">Talento Humano</p>
<p class="fecha-emision">Fecha de emisión: {{ $fechaHoy }}</p>

{{-- Datos del empleado --}}
<div class="seccion">
  <div class="seccion-titulo">Datos del Empleado</div>
  <div class="seccion-body">
    <table class="datos">
      <tr>
        <td class="label">Cédula:</td>
        <td>{{ $empleado->identificacion }}</td>
        <td class="label">Estado:</td>
        <td>{{ $empleado->estado }}</td>
      </tr>
      <tr>
        <td class="label">Apellidos y Nombres:</td>
        <td colspan="3">{{ $empleado->apellido_emp }}, {{ $empleado->nombre_emp }}</td>
      </tr>
      <tr>
        <td class="label">Departamento:</td>
        <td colspan="3">{{ $empleado->departamento?->nombre_depto ?? '—' }}</td>
      </tr>
      <tr>
        <td class="label">Tipo de contrato:</td>
        <td>{{ trim($empleado->tipo_contrato) }}</td>
        <td class="label">Fecha de ingreso:</td>
        <td>{{ $empleado->fecha_ingreso ? \Carbon\Carbon::parse($empleado->fecha_ingreso)->format('d/m/Y') : '—' }}</td>
      </tr>
      @if($empleado->fecha_salida)
      <tr>
        <td class="label">Fecha de salida:</td>
        <td colspan="3">{{ \Carbon\Carbon::parse($empleado->fecha_salida)->format('d/m/Y') }}</td>
      </tr>
      @endif
    </table>
  </div>
</div>

{{-- Motivo y fecha del evento --}}
<div class="seccion">
  <div class="seccion-titulo">Motivo del Evento</div>
  <div class="seccion-body">
    <table class="datos">
      <tr>
        <td class="label">Motivo:</td>
        <td>
          <span class="motivo-badge badge-{{ $historico->motivo }}">
            {{ str_replace('_', ' ', $historico->motivo) }}
          </span>
        </td>
        <td class="label">Fecha del evento:</td>
        <td>{{ \Carbon\Carbon::parse($historico->fecha_evento)->format('d/m/Y') }}</td>
      </tr>
      <tr>
        <td class="label">Fecha de corte usada:</td>
        <td>{{ \Carbon\Carbon::parse($historico->fecha_corte_usada)->format('d/m/Y') }}</td>
        <td class="label">Procesado por:</td>
        <td>{{ $historico->usuario_proceso }}</td>
      </tr>
    </table>
    @if($historico->observacion)
    <div class="observacion">{{ $historico->observacion }}</div>
    @endif
  </div>
</div>

{{-- Saldo de vacaciones --}}
<div class="seccion">
  <div class="seccion-titulo">Saldo de Vacaciones a la Fecha del Evento</div>
  <div class="seccion-body">
    <table class="saldo">
      <thead>
        <tr>
          <th>Saldo Inicial (corte)</th>
          <th>Días Acumulados</th>
          <th>Días Tomados</th>
          <th>Saldo a Liquidar</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>{{ number_format($historico->saldo_inicial, 2) }}</td>
          <td>{{ number_format($historico->acumulado, 2) }}</td>
          <td>{{ number_format($historico->tomados, 2) }}</td>
          <td class="total">{{ number_format($historico->saldo_liquidado, 2) }} días</td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<div class="pie">
  <div class="linea"></div><br>
  <strong>{{ $aprobador }}</strong><br>
  <span style="font-size:8px; color:#6b7280;">Responsable de Talento Humano</span>
</div>

</body>
</html>
