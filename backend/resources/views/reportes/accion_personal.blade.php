<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, sans-serif; font-size: 8.5pt; color: #000; }

  .page { width: 100%; padding: 10mm 12mm 8mm 12mm; }

  /* Encabezado */
  .header-table { width: 100%; border-collapse: collapse; margin-bottom: 3px; }
  .header-table td { vertical-align: middle; }
  .logo-cell { width: 28%; text-align: center; padding: 4px; border: 1px solid #000; }
  .logo-cell img { max-height: 42px; max-width: 100%; }
  .title-cell { width: 72%; border: 1px solid #000; border-left: none; }
  .title-main { background: #fff; text-align: center; padding: 4px; }
  .title-main h1 { font-size: 13pt; font-weight: bold; letter-spacing: 1px; }
  .title-meta { border-top: 1px solid #000; }
  .title-meta table { width: 100%; border-collapse: collapse; table-layout: fixed; }
  .title-meta td { padding: 2px 6px; font-size: 8pt; }
  .title-meta td:first-child { border-right: 1px solid #000; width: 40%; font-weight: bold; }

  /* Secciones */
  .section { border: 1px solid #000; margin-bottom: 3px; }
  .section-row { border-collapse: collapse; width: 100%; table-layout: fixed; }
  .section-row td { border: 1px solid #000; padding: 2px 5px; vertical-align: top; word-wrap: break-word; overflow: hidden; }
  .label { font-weight: bold; font-size: 7.5pt; color: #000; }
  .value { font-size: 8pt; }

  /* Checkboxes — tabla fija para evitar desbordamiento */
  .checks-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
  .checks-table td { padding: 1px 3px; font-size: 7.5pt; vertical-align: middle; word-wrap: break-word; overflow: hidden; }
  .checkbox { display: inline-block; width: 10px; height: 10px; border: 1px solid #000; margin-right: 2px; text-align: center; line-height: 10px; font-size: 8pt; font-weight: bold; vertical-align: middle; }
  .checked { background: #000; color: #fff; }

  /* Situación actual / propuesta */
  .sit-table { width: 100%; border-collapse: collapse; border: 1px solid #000; margin-bottom: 3px; table-layout: fixed; }
  .sit-header { background: #d0d0d0; text-align: center; font-weight: bold; font-size: 8pt; padding: 2px; border-bottom: 1px solid #000; }
  .sit-inner { width: 100%; border-collapse: collapse; table-layout: fixed; }
  .sit-inner td { border-bottom: 1px solid #ccc; padding: 2px 4px; font-size: 7.5pt; word-wrap: break-word; overflow: hidden; }
  .sit-inner td:first-child { font-weight: bold; border-right: 1px solid #000; width: 45%; }

  /* Motivación */
  .motivacion-box { min-height: 45px; padding: 3px 4px; font-size: 8pt; word-wrap: break-word; overflow: hidden; }

  /* Firmas */
  .firma-section { border: 1px solid #000; margin-top: 3px; }
  .firma-header { background: #d0d0d0; text-align: center; font-weight: bold; font-size: 8pt; padding: 2px; border-bottom: 1px solid #000; }
  .firma-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
  .firma-table td { border-right: 1px solid #000; padding: 6px; vertical-align: bottom; width: 50%; font-size: 7.5pt; }
  .firma-table td:last-child { border-right: none; }
  .firma-line { border-top: 1px solid #000; margin-top: 22px; padding-top: 2px; }

  .footer { font-size: 6.5pt; color: #555; text-align: center; margin-top: 4px; border-top: 1px solid #ccc; padding-top: 2px; }
  .gray-bg { background-color: #e0e0e0; }
  .bold { font-weight: bold; }
  .center { text-align: center; }

  /* Página 2 */
  .page-break { page-break-before: always; }

  /* Tabla de notificación */
  .notif-table { width: 100%; border-collapse: collapse; border: 1px solid #000; margin-bottom: 4px; table-layout: fixed; }
  .notif-table td { border: 1px solid #000; padding: 3px 5px; font-size: 8pt; word-wrap: break-word; }
  .notif-label { font-weight: bold; font-size: 7.5pt; width: 40%; }
  .notif-value { font-size: 8pt; }
  .notif-blank { border-bottom: 1px solid #555; min-height: 14px; display: inline-block; width: 90%; }

  @page { margin: 0; size: letter portrait; }
</style>
</head>
<body>

{{-- ==================== PÁGINA 1 ==================== --}}
<div class="page">

  {{-- ENCABEZADO --}}
  <table class="header-table">
    <tr>
      <td class="logo-cell">
        @if($logo)
          <img src="{{ $logo }}" alt="Logo">
        @else
          <div style="font-size:7pt;color:#777;">(LOGO)</div>
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

  {{-- DATOS DEL EMPLEADO --}}
  <div class="section">
    <table class="section-row">
      <tr>
        <td style="width:45%; border-right:1px solid #000;">
          <div class="label">APELLIDOS</div>
          <div class="value">{{ strtoupper($accion->empleado->apellido_emp ?? '') }}</div>
        </td>
        <td style="width:55%;">
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
      <span class="label">HASTA:</span>
      <span class="value">
        @if($accion->fecha_fin)
          {{ \Carbon\Carbon::parse($accion->fecha_fin)->format('d/m/Y') }}
        @else
          Hasta nueva orden
        @endif
      </span>
    </div>
  </div>

  {{-- TIPO DE ACCIÓN --}}
  <div class="section" style="padding: 3px 5px;">
    <div class="label" style="margin-bottom:3px;">Escoja una opción <span style="font-weight:normal;">(según lo estipulado en el artículo 21 del Reglamento General a la Ley Orgánica del Servicio Público)</span></div>
    @php
      $tipo = strtoupper($accion->tipo_accion);
      function chk($tipo, $val) { return $tipo === $val ? '&#10003;' : '&nbsp;'; }
      function chkClass($tipo, $val) { return $tipo === $val ? 'checkbox checked' : 'checkbox'; }
    @endphp
    {{-- 5 columnas iguales (20% cada una), sin nowrap --}}
    <table class="checks-table">
      <colgroup>
        <col style="width:20%"><col style="width:20%"><col style="width:22%"><col style="width:22%"><col style="width:16%">
      </colgroup>
      <tr>
        <td><span class="{{ chkClass($tipo,'INGRESO') }}">{!! chk($tipo,'INGRESO') !!}</span> INGRESO</td>
        <td><span class="{{ chkClass($tipo,'TRASPASO') }}">{!! chk($tipo,'TRASPASO') !!}</span> TRASPASO</td>
        <td><span class="{{ chkClass($tipo,'INCREMENTO RMU') }}">{!! chk($tipo,'INCREMENTO RMU') !!}</span> INCREMENTO RMU</td>
        <td><span class="{{ chkClass($tipo,'REVISION CLASI. PUESTO') }}">{!! chk($tipo,'REVISION CLASI. PUESTO') !!}</span> REVISIÓN CLASI. PUESTO</td>
        <td><span class="checkbox">&nbsp;</span> OTRO</td>
      </tr>
      <tr>
        <td><span class="{{ chkClass($tipo,'REINGRESO') }}">{!! chk($tipo,'REINGRESO') !!}</span> REINGRESO</td>
        <td><span class="{{ chkClass($tipo,'CAMBIO ADMINISTRATIVO') }}">{!! chk($tipo,'CAMBIO ADMINISTRATIVO') !!}</span> CAMBIO ADMINISTRATIVO</td>
        <td><span class="{{ chkClass($tipo,'SUBROGACION') }}">{!! chk($tipo,'SUBROGACION') !!}</span> SUBROGACIÓN</td>
        <td colspan="2"></td>
      </tr>
      <tr>
        <td><span class="{{ chkClass($tipo,'RESTITUCION') }}">{!! chk($tipo,'RESTITUCION') !!}</span> RESTITUCIÓN</td>
        <td><span class="{{ chkClass($tipo,'INTERCAMBIO VOLUNTARIO') }}">{!! chk($tipo,'INTERCAMBIO VOLUNTARIO') !!}</span> INTERCAMBIO VOLUNTARIO</td>
        <td><span class="{{ chkClass($tipo,'ENCARGO') }}">{!! chk($tipo,'ENCARGO') !!}</span> ENCARGO</td>
        <td colspan="2"></td>
      </tr>
      <tr>
        <td><span class="{{ chkClass($tipo,'REINTEGRO') }}">{!! chk($tipo,'REINTEGRO') !!}</span> REINTEGRO</td>
        <td><span class="{{ chkClass($tipo,'LICENCIA') }}">{!! chk($tipo,'LICENCIA') !!}</span> LICENCIA</td>
        <td><span class="{{ chkClass($tipo,'CESACION DE FUNCIONES') }}">{!! chk($tipo,'CESACION DE FUNCIONES') !!}</span> CESACIÓN DE FUNCIONES</td>
        <td colspan="2"></td>
      </tr>
      <tr>
        <td><span class="{{ chkClass($tipo,'ASCENSO') }}">{!! chk($tipo,'ASCENSO') !!}</span> ASCENSO</td>
        <td><span class="{{ chkClass($tipo,'COMISION DE SERVICIOS') }}">{!! chk($tipo,'COMISION DE SERVICIOS') !!}</span> COMISIÓN DE SERVICIOS</td>
        <td><span class="{{ chkClass($tipo,'DESTITUCION') }}">{!! chk($tipo,'DESTITUCION') !!}</span> DESTITUCIÓN</td>
        <td colspan="2"></td>
      </tr>
      <tr>
        <td><span class="{{ chkClass($tipo,'TRASLADO') }}">{!! chk($tipo,'TRASLADO') !!}</span> TRASLADO</td>
        <td><span class="{{ chkClass($tipo,'SANCIONES') }}">{!! chk($tipo,'SANCIONES') !!}</span> SANCIONES</td>
        <td><span class="{{ chkClass($tipo,'VACACIONES') }}">{!! chk($tipo,'VACACIONES') !!}</span> VACACIONES</td>
        <td colspan="2"></td>
      </tr>
    </table>
  </div>

  {{-- MOTIVACIÓN --}}
  <div class="section" style="padding: 2px 5px;">
    <div class="label">MOTIVACIÓN: <span style="font-weight:normal;">(adjuntar anexo si lo posee)</span></div>
    <div class="motivacion-box">{{ $accion->motivacion ?? '' }}</div>
  </div>

  {{-- SITUACIÓN ACTUAL vs PROPUESTA --}}
  <table class="sit-table">
    <tr>
      <td style="width:50%; border-right:1px solid #000; padding:0; vertical-align:top;">
        <div class="sit-header">SITUACIÓN ACTUAL</div>
        <table class="sit-inner">
          <tr><td>PROCESO INSTITUCIONAL:</td><td>{{ $accion->actual_proceso_inst ?? '' }}</td></tr>
          <tr><td>NIVEL DE GESTIÓN:</td><td>{{ $accion->empleado->departamento->nombre_depto ?? '' }}</td></tr>
          <tr><td>LUGAR DE TRABAJO:</td><td>Quito</td></tr>
          <tr><td>DENOMINACIÓN DEL PUESTO:</td><td>{{ $accion->actual_cargo ?? '' }}</td></tr>
          <tr><td>GRUPO OCUPACIONAL:</td><td>{{ $accion->actual_grupo_ocup ?? '' }}</td></tr>
          <tr><td>GRADO:</td><td>{{ $accion->actual_grado ?? '' }}</td></tr>
          <tr><td>REMUNERACIÓN MENSUAL:</td><td>${{ number_format($accion->actual_remuneracion ?? 0, 2) }}</td></tr>
          <tr><td>PARTIDA INDIVIDUAL:</td><td style="font-size:6.5pt; word-break:break-all;">{{ $accion->actual_partida ?? '' }}</td></tr>
        </table>
      </td>
      <td style="width:50%; padding:0; vertical-align:top;">
        <div class="sit-header">SITUACIÓN PROPUESTA</div>
        <table class="sit-inner">
          <tr><td>PROCESO INSTITUCIONAL:</td><td>{{ $accion->propuesto_proceso_inst ?? '' }}</td></tr>
          <tr><td>NIVEL DE GESTIÓN:</td><td></td></tr>
          <tr><td>LUGAR DE TRABAJO:</td><td>Quito</td></tr>
          <tr><td>DENOMINACIÓN DEL PUESTO:</td><td>{{ $accion->propuesto_cargo ?? '' }}</td></tr>
          <tr><td>GRUPO OCUPACIONAL:</td><td>{{ $accion->propuesto_grupo_ocup ?? '' }}</td></tr>
          <tr><td>GRADO:</td><td>{{ $accion->propuesto_grado ?? '' }}</td></tr>
          <tr><td>REMUNERACIÓN MENSUAL:</td><td>${{ number_format($accion->propuesto_remuneracion ?? 0, 2) }}</td></tr>
          <tr><td>PARTIDA INDIVIDUAL:</td><td style="font-size:6.5pt; word-break:break-all;">{{ $accion->propuesto_partida ?? '' }}</td></tr>
        </table>
      </td>
    </tr>
  </table>

  {{-- FIRMAS --}}
  <div class="firma-section">
    <div class="firma-header">RESPONSABLES DE APROBACIÓN</div>
    <table class="firma-table">
      <tr>
        <td style="border-right:1px solid #000;">
          <div style="font-size:7pt; font-weight:bold;">DIRECTOR (A) O RESPONSABLE DE TALENTO HUMANO</div>
          <div class="firma-line">
            <div class="label">FIRMA: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>
            <div style="font-size:7pt;">NOMBRE: {{ $config['DIRECTOR_TALENTO_HUMANO'] ?? '' }}</div>
            <div style="font-size:7pt;">PUESTO: Director/a de Talento Humano</div>
          </div>
        </td>
        <td>
          <div style="font-size:7pt; font-weight:bold;">AUTORIDAD NOMINADORA O SU DELEGADO</div>
          <div class="firma-line">
            <div class="label">FIRMA: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>
            <div style="font-size:7pt;">NOMBRE: {{ $config['PRESIDENTE_INSTITUCION'] ?? '' }}</div>
            <div style="font-size:7pt;">PUESTO: Presidente del Consejo</div>
          </div>
        </td>
      </tr>
    </table>
  </div>

  <div class="footer">
    Elaborado por el Ministerio del Trabajo &nbsp;|&nbsp; Versión: 01.1 &nbsp;|&nbsp; Fecha actualización formato: 2024-08-23 &nbsp;|&nbsp; Página 1 de 2
  </div>

</div>

{{-- ==================== PÁGINA 2 ==================== --}}
<div class="page page-break">

  {{-- ENCABEZADO --}}
  <table class="header-table">
    <tr>
      <td class="logo-cell">
        @if($logo)
          <img src="{{ $logo }}" alt="Logo">
        @else
          <div style="font-size:7pt;color:#777;">(LOGO)</div>
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

  {{-- REGISTRO DE NOTIFICACIÓN --}}
  <div class="section" style="padding: 3px 5px; margin-bottom: 6px;">
    <div class="gray-bg" style="text-align:center; font-weight:bold; font-size:9pt; padding:3px; margin-bottom:4px;">
      REGISTRO DE NOTIFICACIÓN AL SERVIDOR/A
    </div>

    <table class="notif-table" style="margin-bottom:6px;">
      <tr>
        <td class="notif-label">APELLIDOS Y NOMBRES:</td>
        <td class="notif-value">{{ strtoupper(($accion->empleado->apellido_emp ?? '') . ', ' . ($accion->empleado->nombre_emp ?? '')) }}</td>
      </tr>
      <tr>
        <td class="notif-label">NRO. DE IDENTIFICACIÓN:</td>
        <td class="notif-value">{{ $accion->empleado->identificacion ?? '' }}</td>
      </tr>
      <tr>
        <td class="notif-label">ACCIÓN DE PERSONAL Nro.:</td>
        <td class="notif-value">{{ $accion->numero_accion }}</td>
      </tr>
      <tr>
        <td class="notif-label">TIPO DE ACCIÓN:</td>
        <td class="notif-value">{{ $accion->tipo_accion }}</td>
      </tr>
      <tr>
        <td class="notif-label">VIGENCIA:</td>
        <td class="notif-value">
          Desde: {{ \Carbon\Carbon::parse($accion->fecha_inicio)->format('d/m/Y') }}
          &nbsp;&nbsp;
          Hasta: {{ $accion->fecha_fin ? \Carbon\Carbon::parse($accion->fecha_fin)->format('d/m/Y') : 'Hasta nueva orden' }}
        </td>
      </tr>
    </table>

    <p style="font-size:8pt; margin-bottom:6px;">
      Se notifica al servidor/a antes indicado/a sobre la presente Acción de Personal y su contenido,
      en cumplimiento de lo establecido en la normativa vigente del Servicio Público.
    </p>

    {{-- Firma del servidor --}}
    <table style="width:100%; border-collapse:collapse; table-layout:fixed; margin-bottom:10px;">
      <tr>
        <td style="width:50%; padding:4px 6px; border:1px solid #000; vertical-align:bottom;">
          <div style="font-size:7pt; font-weight:bold;">FECHA DE NOTIFICACIÓN:</div>
          <div style="min-height:20px; border-bottom:1px solid #555; margin-top:14px;"></div>
          <div style="font-size:7pt;">dd / mm / aaaa</div>
        </td>
        <td style="width:50%; padding:4px 6px; border:1px solid #000; border-left:none; vertical-align:bottom;">
          <div style="font-size:7pt; font-weight:bold;">FIRMA DEL SERVIDOR/A NOTIFICADO/A:</div>
          <div style="min-height:20px; border-bottom:1px solid #555; margin-top:14px;"></div>
          <div style="font-size:7pt;">{{ strtoupper(($accion->empleado->apellido_emp ?? '') . ', ' . ($accion->empleado->nombre_emp ?? '')) }}</div>
        </td>
      </tr>
    </table>

    {{-- Observaciones --}}
    <div style="margin-bottom:8px;">
      <div class="label" style="margin-bottom:2px;">OBSERVACIONES:</div>
      <div style="border:1px solid #999; min-height:40px; padding:3px;"></div>
    </div>

    {{-- Distribución de copias --}}
    <div>
      <div class="gray-bg" style="text-align:center; font-weight:bold; font-size:8pt; padding:2px; border:1px solid #000; border-bottom:none;">
        DISTRIBUCIÓN DE COPIAS
      </div>
      <table style="width:100%; border-collapse:collapse; table-layout:fixed; border:1px solid #000;">
        <tr>
          <td style="border:1px solid #000; padding:2px 5px; font-size:7.5pt; width:10%; font-weight:bold; text-align:center;">1</td>
          <td style="border:1px solid #000; padding:2px 5px; font-size:7.5pt; width:50%;">DIRECCIÓN DE ADMINISTRACIÓN DEL TALENTO HUMANO</td>
          <td style="border:1px solid #000; padding:2px 5px; font-size:7.5pt; width:40%;">ARCHIVO</td>
        </tr>
        <tr>
          <td style="border:1px solid #000; padding:2px 5px; font-size:7.5pt; text-align:center; font-weight:bold;">2</td>
          <td style="border:1px solid #000; padding:2px 5px; font-size:7.5pt;">SERVIDOR/A NOTIFICADO/A</td>
          <td style="border:1px solid #000; padding:2px 5px; font-size:7.5pt;"></td>
        </tr>
        <tr>
          <td style="border:1px solid #000; padding:2px 5px; font-size:7.5pt; text-align:center; font-weight:bold;">3</td>
          <td style="border:1px solid #000; padding:2px 5px; font-size:7.5pt;">ÁREA / UNIDAD RESPONSABLE</td>
          <td style="border:1px solid #000; padding:2px 5px; font-size:7.5pt;"></td>
        </tr>
      </table>
    </div>
  </div>

  {{-- Firma responsable de notificación --}}
  <div class="firma-section">
    <div class="firma-header">RESPONSABLE DE LA NOTIFICACIÓN</div>
    <table class="firma-table">
      <tr>
        <td style="border-right:1px solid #000; text-align:center;">
          <div style="font-size:7pt; font-weight:bold;">DIRECTOR (A) O RESPONSABLE DE TALENTO HUMANO</div>
          <div class="firma-line">
            <div class="label">FIRMA: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>
            <div style="font-size:7pt;">NOMBRE: {{ $config['DIRECTOR_TALENTO_HUMANO'] ?? '' }}</div>
            <div style="font-size:7pt;">PUESTO: Director/a de Talento Humano</div>
          </div>
        </td>
        <td style="text-align:center;">
          <div style="font-size:7pt; font-weight:bold;">FECHA:</div>
          <div style="min-height:30px; border-bottom:1px solid #555; margin-top:20px;"></div>
          <div style="font-size:7pt;">dd / mm / aaaa</div>
        </td>
      </tr>
    </table>
  </div>

  <div class="footer">
    Elaborado por el Ministerio del Trabajo &nbsp;|&nbsp; Versión: 01.1 &nbsp;|&nbsp; Fecha actualización formato: 2024-08-23 &nbsp;|&nbsp; Página 2 de 2
  </div>

</div>
</body>
</html>
