<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, sans-serif; font-size: 8.5pt; color: #000; }

  .page { width: 100%; padding: 12mm 12mm 8mm 12mm; }

  /* Encabezado */
  .header-table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
  .header-table td { vertical-align: middle; }
  .logo-cell { width: 30%; text-align: center; padding: 4px; border: 1px solid #000; }
  .logo-cell img { max-height: 40px; max-width: 100%; }
  .title-cell { width: 70%; border: 1px solid #000; border-left: none; }
  .title-inner { display: table; width: 100%; }
  .title-main { background: #fff; text-align: center; padding: 4px; }
  .title-main h1 { font-size: 14pt; font-weight: bold; letter-spacing: 1px; }
  .title-meta { border-top: 1px solid #000; }
  .title-meta table { width: 100%; border-collapse: collapse; }
  .title-meta td { padding: 2px 6px; font-size: 8pt; }
  .title-meta td:first-child { border-right: 1px solid #000; width: 20%; font-weight: bold; }

  /* Secciones */
  .section { border: 1px solid #000; margin-bottom: 3px; }
  .section-row { border-collapse: collapse; width: 100%; }
  .section-row td { border: 1px solid #000; padding: 2px 5px; vertical-align: top; }
  .label { font-weight: bold; font-size: 7.5pt; color: #000; }
  .value { font-size: 8pt; }
  .field-line { border-bottom: 1px solid #555; min-height: 14px; display: inline-block; width: 100%; }

  /* Checkboxes */
  .checks-table { width: 100%; border-collapse: collapse; }
  .checks-table td { padding: 1px 4px; font-size: 7.5pt; vertical-align: middle; white-space: nowrap; }
  .checkbox { display: inline-block; width: 10px; height: 10px; border: 1px solid #000; margin-right: 3px; text-align: center; line-height: 10px; font-size: 8pt; font-weight: bold; vertical-align: middle; }
  .checked { background: #000; color: #fff; }

  /* Situación */
  .sit-header { background: #d0d0d0; text-align: center; font-weight: bold; font-size: 8pt; padding: 2px; border-bottom: 1px solid #000; }
  .sit-row td { border-bottom: 1px solid #ccc; padding: 2px 5px; font-size: 7.5pt; }
  .sit-row td:first-child { font-weight: bold; border-right: 1px solid #000; width: 35%; }
  .sit-label { font-weight: bold; font-size: 7.5pt; padding: 2px 5px; }

  /* Firmas */
  .firma-section { border: 1px solid #000; margin-top: 3px; }
  .firma-header { background: #d0d0d0; text-align: center; font-weight: bold; font-size: 8pt; padding: 2px; border-bottom: 1px solid #000; }
  .firma-table { width: 100%; border-collapse: collapse; }
  .firma-table td { border-right: 1px solid #000; padding: 4px 6px; vertical-align: bottom; width: 50%; text-align: center; font-size: 7.5pt; }
  .firma-table td:last-child { border-right: none; }
  .firma-line { border-top: 1px solid #000; margin-top: 20px; padding-top: 2px; }

  .footer { font-size: 6.5pt; color: #555; text-align: center; margin-top: 4px; border-top: 1px solid #ccc; padding-top: 2px; }

  .motivacion-box { border: 1px solid #ccc; min-height: 50px; padding: 4px; font-size: 8pt; white-space: pre-wrap; word-wrap: break-word; }
  .gray-bg { background-color: #e8e8e8; }
  .bold { font-weight: bold; }
  .center { text-align: center; }
  .small { font-size: 7pt; color: #555; }

  @page { margin: 0; size: letter portrait; }
</style>
</head>
<body>
<div class="page">

  <!-- ENCABEZADO -->
  <table class="header-table">
    <tr>
      <td class="logo-cell">
        @if($logo)
          <img src="{{ $logo }}" alt="Logo">
        @else
          <div style="font-size:7pt; color:#777;">(LOGO INSTITUCIONAL)</div>
        @endif
      </td>
      <td class="title-cell">
        <div class="title-main"><h1>ACCIÓN DE PERSONAL</h1></div>
        <div class="title-meta">
          <table>
            <tr>
              <td class="label">Nro.</td>
              <td class="value">{{ $accion->numero_accion }}</td>
            </tr>
            <tr>
              <td class="label">FECHA DE ELABORACIÓN</td>
              <td class="value">{{ \Carbon\Carbon::parse($accion->fecha_elaboracion)->format('d/m/Y') }}</td>
            </tr>
          </table>
        </div>
      </td>
    </tr>
  </table>

  <!-- DATOS DEL EMPLEADO -->
  <div class="section">
    <table class="section-row">
      <tr>
        <td style="width:40%; border-right:1px solid #000;">
          <div class="label">APELLIDOS</div>
          <div class="value">{{ strtoupper($accion->empleado->apellido_emp ?? '') }}</div>
        </td>
        <td style="width:60%;">
          <div class="label">NOMBRES</div>
          <div class="value">{{ strtoupper($accion->empleado->nombre_emp ?? '') }}</div>
        </td>
      </tr>
      <tr>
        <td style="border-right:1px solid #000; border-top:1px solid #000;">
          <div class="label">DOCUMENTO DE IDENTIFICACIÓN</div>
          <div class="value">CÉDULA DE CIUDADANÍA</div>
        </td>
        <td style="border-top:1px solid #000;">
          <div class="label">NRO. DE IDENTIFICACIÓN</div>
          <div class="value">{{ $accion->empleado->identificacion ?? '' }}</div>
        </td>
      </tr>
    </table>
    <div style="border-top:1px solid #000; padding: 2px 5px;">
      <span class="label">RIGE: &nbsp;</span>
      <span class="label">DESDE:</span> <span class="value">{{ \Carbon\Carbon::parse($accion->fecha_inicio)->format('d/m/Y') }}</span>
      &nbsp;&nbsp;&nbsp;
      <span class="label">HASTA:</span> <span class="value">{{ $accion->fecha_fin ? \Carbon\Carbon::parse($accion->fecha_fin)->format('d/m/Y') : '(cuando aplica)' }}</span>
    </div>
  </div>

  <!-- TIPO DE ACCIÓN -->
  <div class="section" style="padding: 3px 5px;">
    <div class="label" style="margin-bottom:3px;">Escoja una opción <span style="font-weight:normal;">(según lo estipulado en el artículo 21 del Reglamento General a la Ley Orgánica del Servicio Público)</span></div>
    @php
      $tipo = strtoupper($accion->tipo_accion);
      function chk($tipo, $val) { return $tipo === $val ? '✓' : ''; }
      function chkClass($tipo, $val) { return $tipo === $val ? 'checkbox checked' : 'checkbox'; }
    @endphp
    <table class="checks-table">
      <tr>
        <td><span class="{{ chkClass($tipo,'INGRESO') }}">{{ chk($tipo,'INGRESO') }}</span> INGRESO</td>
        <td><span class="{{ chkClass($tipo,'TRASPASO') }}">{{ chk($tipo,'TRASPASO') }}</span> TRASPASO</td>
        <td><span class="{{ chkClass($tipo,'INCREMENTO RMU') }}">{{ chk($tipo,'INCREMENTO RMU') }}</span> INCREMENTO RMU</td>
        <td><span class="{{ chkClass($tipo,'REVISION CLASI. PUESTO') }}">{{ chk($tipo,'REVISION CLASI. PUESTO') }}</span> REVISIÓN CLASI. PUESTO</td>
        <td><span class="checkbox"></span> OTRO (DETALLAR)</td>
      </tr>
      <tr>
        <td><span class="{{ chkClass($tipo,'REINGRESO') }}">{{ chk($tipo,'REINGRESO') }}</span> REINGRESO</td>
        <td><span class="{{ chkClass($tipo,'CAMBIO ADMINISTRATIVO') }}">{{ chk($tipo,'CAMBIO ADMINISTRATIVO') }}</span> CAMBIO ADMINISTRATIVO</td>
        <td><span class="{{ chkClass($tipo,'SUBROGACION') }}">{{ chk($tipo,'SUBROGACION') }}</span> SUBROGACIÓN</td>
        <td colspan="2"></td>
      </tr>
      <tr>
        <td><span class="{{ chkClass($tipo,'RESTITUCION') }}">{{ chk($tipo,'RESTITUCION') }}</span> RESTITUCIÓN</td>
        <td><span class="{{ chkClass($tipo,'INTERCAMBIO VOLUNTARIO') }}">{{ chk($tipo,'INTERCAMBIO VOLUNTARIO') }}</span> INTERCAMBIO VOLUNTARIO</td>
        <td><span class="{{ chkClass($tipo,'ENCARGO') }}">{{ chk($tipo,'ENCARGO') }}</span> ENCARGO</td>
        <td colspan="2"></td>
      </tr>
      <tr>
        <td><span class="{{ chkClass($tipo,'REINTEGRO') }}">{{ chk($tipo,'REINTEGRO') }}</span> REINTEGRO</td>
        <td><span class="{{ chkClass($tipo,'LICENCIA') }}">{{ chk($tipo,'LICENCIA') }}</span> LICENCIA</td>
        <td><span class="{{ chkClass($tipo,'CESACION DE FUNCIONES') }}">{{ chk($tipo,'CESACION DE FUNCIONES') }}</span> CESACIÓN DE FUNCIONES</td>
        <td colspan="2"></td>
      </tr>
      <tr>
        <td><span class="{{ chkClass($tipo,'ASCENSO') }}">{{ chk($tipo,'ASCENSO') }}</span> ASCENSO</td>
        <td><span class="{{ chkClass($tipo,'COMISION DE SERVICIOS') }}">{{ chk($tipo,'COMISION DE SERVICIOS') }}</span> COMISIÓN DE SERVICIOS</td>
        <td><span class="{{ chkClass($tipo,'DESTITUCION') }}">{{ chk($tipo,'DESTITUCION') }}</span> DESTITUCIÓN</td>
        <td colspan="2"></td>
      </tr>
      <tr>
        <td><span class="{{ chkClass($tipo,'TRASLADO') }}">{{ chk($tipo,'TRASLADO') }}</span> TRASLADO</td>
        <td><span class="{{ chkClass($tipo,'SANCIONES') }}">{{ chk($tipo,'SANCIONES') }}</span> SANCIONES</td>
        <td><span class="{{ chkClass($tipo,'VACACIONES') }}">{{ chk($tipo,'VACACIONES') }}</span> VACACIONES</td>
        <td colspan="2"></td>
      </tr>
    </table>
  </div>

  <!-- MOTIVACIÓN -->
  <div class="section" style="padding: 3px 5px;">
    <div class="label">MOTIVACIÓN: <span style="font-weight:normal;">(adjuntar anexo si lo posee)</span></div>
    <div class="motivacion-box">{{ $accion->motivacion ?? '' }}</div>
  </div>

  <!-- SITUACIÓN ACTUAL vs PROPUESTA -->
  <table style="width:100%; border-collapse:collapse; border:1px solid #000; margin-bottom:3px;">
    <tr>
      <td style="width:50%; border-right:1px solid #000; padding:0; vertical-align:top;">
        <div class="sit-header">SITUACIÓN ACTUAL</div>
        <table style="width:100%; border-collapse:collapse;">
          <tr class="sit-row"><td class="sit-label">PROCESO INSTITUCIONAL:</td><td style="padding:2px 5px; font-size:7.5pt;">{{ $accion->actual_proceso_inst ?? '' }}</td></tr>
          <tr class="sit-row"><td class="sit-label">NIVEL DE GESTIÓN:</td><td style="padding:2px 5px; font-size:7.5pt;">{{ $accion->empleado->departamento->nombre_depto ?? '' }}</td></tr>
          <tr class="sit-row"><td class="sit-label">LUGAR DE TRABAJO:</td><td style="padding:2px 5px; font-size:7.5pt;">Quito</td></tr>
          <tr class="sit-row"><td class="sit-label">DENOMINACIÓN DEL PUESTO:</td><td style="padding:2px 5px; font-size:7.5pt;">{{ $accion->actual_cargo ?? '' }}</td></tr>
          <tr class="sit-row"><td class="sit-label">GRUPO OCUPACIONAL:</td><td style="padding:2px 5px; font-size:7.5pt;">{{ $accion->actual_grupo_ocup ?? '' }}</td></tr>
          <tr class="sit-row"><td class="sit-label">GRADO:</td><td style="padding:2px 5px; font-size:7.5pt;">{{ $accion->actual_grado ?? '' }}</td></tr>
          <tr class="sit-row"><td class="sit-label">REMUNERACIÓN MENSUAL:</td><td style="padding:2px 5px; font-size:7.5pt;">${{ number_format($accion->actual_remuneracion ?? 0, 2) }}</td></tr>
          <tr class="sit-row"><td class="sit-label">PARTIDA INDIVIDUAL:</td><td style="padding:2px 5px; font-size:7pt; word-break:break-all;">{{ $accion->actual_partida ?? '' }}</td></tr>
        </table>
      </td>
      <td style="width:50%; padding:0; vertical-align:top;">
        <div class="sit-header">SITUACIÓN PROPUESTA</div>
        <table style="width:100%; border-collapse:collapse;">
          <tr class="sit-row"><td class="sit-label">PROCESO INSTITUCIONAL:</td><td style="padding:2px 5px; font-size:7.5pt;">{{ $accion->propuesto_proceso_inst ?? '' }}</td></tr>
          <tr class="sit-row"><td class="sit-label">NIVEL DE GESTIÓN:</td><td style="padding:2px 5px; font-size:7.5pt;"></td></tr>
          <tr class="sit-row"><td class="sit-label">LUGAR DE TRABAJO:</td><td style="padding:2px 5px; font-size:7.5pt;">Quito</td></tr>
          <tr class="sit-row"><td class="sit-label">DENOMINACIÓN DEL PUESTO:</td><td style="padding:2px 5px; font-size:7.5pt;">{{ $accion->propuesto_cargo ?? '' }}</td></tr>
          <tr class="sit-row"><td class="sit-label">GRUPO OCUPACIONAL:</td><td style="padding:2px 5px; font-size:7.5pt;">{{ $accion->propuesto_grupo_ocup ?? '' }}</td></tr>
          <tr class="sit-row"><td class="sit-label">GRADO:</td><td style="padding:2px 5px; font-size:7.5pt;">{{ $accion->propuesto_grado ?? '' }}</td></tr>
          <tr class="sit-row"><td class="sit-label">REMUNERACIÓN MENSUAL:</td><td style="padding:2px 5px; font-size:7.5pt;">${{ number_format($accion->propuesto_remuneracion ?? 0, 2) }}</td></tr>
          <tr class="sit-row"><td class="sit-label">PARTIDA INDIVIDUAL:</td><td style="padding:2px 5px; font-size:7pt; word-break:break-all;">{{ $accion->propuesto_partida ?? '' }}</td></tr>
        </table>
      </td>
    </tr>
  </table>

  @if(round($accion->diferencial, 2) > 0)
  <div class="section" style="padding: 2px 5px;">
    <span class="label">DIFERENCIAL SALARIAL: </span>
    <span class="value">${{ number_format($accion->diferencial, 2) }}</span>
  </div>
  @endif

  <!-- FIRMAS DE APROBACIÓN -->
  <div class="firma-section">
    <div class="firma-header">RESPONSABLES DE APROBACIÓN</div>
    <table class="firma-table">
      <tr>
        <td style="border-right:1px solid #000; text-align:center; padding: 8px 6px;">
          <div style="font-size:7pt; font-weight:bold; text-align:left;">DIRECTOR (A) O RESPONSABLE DE TALENTO HUMANO</div>
          <div style="min-height:30px;"></div>
          <div class="firma-line">
            <div class="label">FIRMA:</div>
            <div class="value">{{ $config['DIRECTOR_TALENTO_HUMANO'] ?? '' }}</div>
            <div class="small">NOMBRE:</div>
            <div class="small">PUESTO: Director/a de Talento Humano</div>
          </div>
        </td>
        <td style="text-align:center; padding: 8px 6px;">
          <div style="font-size:7pt; font-weight:bold; text-align:left;">AUTORIDAD NOMINADORA O SU DELEGADO</div>
          <div style="min-height:30px;"></div>
          <div class="firma-line">
            <div class="label">FIRMA:</div>
            <div class="value">{{ $config['PRESIDENTE_INSTITUCION'] ?? '' }}</div>
            <div class="small">NOMBRE:</div>
            <div class="small">PUESTO: Presidente del Consejo</div>
          </div>
        </td>
      </tr>
    </table>
  </div>

  <!-- PIE DE PÁGINA -->
  <div class="footer">
    Elaborado por el Ministerio del Trabajo &nbsp;|&nbsp;
    Fecha de actualización de formato: 2024-08-23 / Versión: 01.1 / Página 1 de 2
  </div>

</div>
</body>
</html>
