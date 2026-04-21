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

  .seccion-body { padding: 10px; }

  table.datos { width: 100%; border-collapse: collapse; }
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

  table.saldo { width: 100%; border-collapse: collapse; margin-top: 4px; }
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
    font-size: 12px;
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
  .badge-INICIO_COMISION    { background-color: #b45309; }
  .badge-FIN_COMISION_RETORNO { background-color: #0f766e; }
  .badge-COMISION_ENTRANTE  { background-color: #1d4ed8; }
  .badge-FIN_COMISION_SALIDA { background-color: #b45309; }
  .badge-NUEVO_INGRESO      { background-color: #1d4ed8; }
  .badge-DESVINCULACION     { background-color: #dc2626; }

  .observacion {
    margin-top: 8px;
    padding: 6px 10px;
    background-color: #f9fafb;
    border-left: 3px solid #0b5447;
    font-style: italic;
    color: #374151;
  }

  .pie { margin-top: 50px; text-align: center; }
  .pie .linea {
    display: inline-block;
    width: 260px;
    border-top: 1px solid #374151;
    margin-bottom: 4px;
  }
</style>
</head>
<body>

@php
  $logoPath   = public_path('logo.png');
  $logoBase64 = file_exists($logoPath)
      ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
      : null;

  $titulos = [
    'INICIO_COMISION'     => 'Certificado de Saldo de Vacaciones — Inicio de Comisión de Servicios',
    'FIN_COMISION_RETORNO'=> 'Constancia de Carga de Saldo — Retorno de Comisión de Servicios',
    'COMISION_ENTRANTE'   => 'Constancia de Carga de Saldo — Comisión de Servicios Entrante',
    'FIN_COMISION_SALIDA' => 'Certificado de Saldo de Vacaciones — Fin de Comisión de Servicios',
    'NUEVO_INGRESO'       => 'Constancia de Inicio de Acumulación de Vacaciones',
    'DESVINCULACION'      => 'Reporte de Liquidación de Vacaciones',
  ];
  $titulo = $titulos[$historico->motivo] ?? 'Reporte de Vacaciones';
@endphp

{{-- Encabezado con logo --}}
<table style="width:100%; margin-bottom:8px;">
  <tr>
    <td style="width:80px; vertical-align:middle;">
      @if($logoBase64)
        <img src="{{ $logoBase64 }}" style="height:55px; width:auto;">
      @endif
    </td>
    <td style="vertical-align:middle; text-align:center;">
      <p class="titulo" style="margin-bottom:2px;">{{ $titulo }}</p>
      <p class="subtitulo" style="margin-bottom:0;">DIRECCIÓN DE ADMINISTRACIÓN DEL TALENTO HUMANO</p>
    </td>
    <td style="width:80px;"></td>
  </tr>
</table>
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
        <td class="label">Modalidad laboral:</td>
        <td>{{ trim($empleado->modalidad_laboral ?? '—') }}</td>
        <td class="label">Tipo de contrato:</td>
        <td>{{ trim($empleado->tipo_contrato) }}</td>
      </tr>
      <tr>
        <td class="label">Fecha de ingreso:</td>
        <td>{{ $empleado->fecha_ingreso ? \Carbon\Carbon::parse($empleado->fecha_ingreso)->format('d/m/Y') : '—' }}</td>
        <td class="label">Fecha de salida:</td>
        <td>{{ $empleado->fecha_salida ? \Carbon\Carbon::parse($empleado->fecha_salida)->format('d/m/Y') : '—' }}</td>
      </tr>
    </table>
  </div>
</div>

{{-- Motivo y fecha del evento --}}
<div class="seccion">
  <div class="seccion-titulo">Detalle del Evento</div>
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
        <td colspan="3">{{ \Carbon\Carbon::parse($historico->fecha_corte_usada)->format('d/m/Y') }}</td>
      </tr>
    </table>
    @if($historico->observacion)
    <div class="observacion">{{ $historico->observacion }}</div>
    @endif
  </div>
</div>

{{-- Saldo de vacaciones --}}
<div class="seccion">
  <div class="seccion-titulo">
    @if(in_array($historico->motivo, ['FIN_COMISION_RETORNO', 'COMISION_ENTRANTE']))
      Saldo Cargado desde Certificado Externo
    @else
      Saldo de Vacaciones a la Fecha del Evento
    @endif
  </div>
  <div class="seccion-body">
    <table class="saldo">
      <thead>
        <tr>
          <th>Saldo Inicial (corte)</th>
          <th>Días Acumulados</th>
          <th>Días Tomados</th>
          <th>
            @if($historico->motivo === 'DESVINCULACION')
              Días a Liquidar
            @elseif(in_array($historico->motivo, ['INICIO_COMISION', 'FIN_COMISION_SALIDA']))
              Días Certificados
            @else
              Saldo Cargado
            @endif
          </th>
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
  <br><br>
  <span style="font-size:8px; color:#6b7280;">Generado por: <strong>{{ $generadoPor }}</strong></span>
</div>

</body>
</html>
