<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, sans-serif; font-size: 8.5pt; color: #000; }
  .page { width: 100%; }

  .lbl  { font-weight: bold; font-size: 7.5pt; }
  .val  { font-size: 8pt; }
  .gray { background-color: #d8d8d8; }

  /* Checkboxes */
  .cb    { display: inline-block; width: 9px; height: 9px; border: 1px solid #000;
           margin-right: 2px; text-align: center; line-height: 9px; font-size: 7pt;
           font-weight: bold; vertical-align: middle; }
  .cb-on { background: #000; color: #fff; }

  /* Situación actual/propuesta */
  .sit-lbl { font-weight: bold; font-size: 7.5pt; width: 46%; padding: 1px 4px;
             border-right: 1px solid #000; border-bottom: 1px solid #ddd; vertical-align: top; }
  .sit-val { font-size: 7.5pt; padding: 1px 4px; border-bottom: 1px solid #ddd;
             word-wrap: break-word; overflow: hidden; }

  .pg2 { page-break-before: always; }

  @page { margin: 14mm 18mm 10mm 18mm; size: a4 portrait; }
</style>
</head>
<body>


@php
  $tipo = strtoupper($accion->tipo_accion);
  function cb($t, $v) { return $t === $v ? 'X' : '&nbsp;'; }
  function cbcls($t, $v) { return $t === $v ? 'cb cb-on' : 'cb'; }
  $deptActual    = $accion->empleado->departamento->nombre_depto ?? '';
  $deptPropuesto = $accion->titular->departamento->nombre_depto ?? '';
  $meses = ['','enero','febrero','marzo','abril','mayo','junio',
            'julio','agosto','septiembre','octubre','noviembre','diciembre'];
  $fe = \Carbon\Carbon::parse($accion->fecha_elaboracion);
  $fechaElabStr = $fe->day . ' de ' . $meses[$fe->month] . ' de ' . $fe->year;
  $creadorNombre = $creador ? strtoupper(($creador->apellido_emp ?? '') . ', ' . ($creador->nombre_emp ?? '')) : '';
  $creadorPuesto = $creador->cargo_empleado ?? '';
  $directorTH    = $config['DIRECTOR_TALENTO_HUMANO'] ?? '';

  // Reglas por tipo de acción
  $declaracionSI = in_array($tipo, ['INGRESO', 'DESTITUCION', 'CESACION DE FUNCIONES']);
  $showActual    = !in_array($tipo, ['INGRESO', 'REINGRESO']);
  $showPropuesta = !in_array($tipo, ['DESTITUCION', 'CESACION DE FUNCIONES', 'VACACIONES', 'COMISION DE SERVICIOS']);
  $fillPosesion  = !in_array($tipo, ['COMISION DE SERVICIOS', 'REINGRESO', 'CESACION DE FUNCIONES']);
  $ubicacion     = strtoupper($config['UBICACION_DEFAULT'] ?? 'QUITO');
  // INGRESO y REINGRESO: la unidad propuesta es el propio departamento del empleado (no hay titular)
  $deptPropuestoFinal = in_array($tipo, ['INGRESO', 'REINGRESO']) ? $deptActual : $deptPropuesto;
@endphp

{{-- ==================== PÁGINA 1 ==================== --}}
<div class="page">

@if($accion->estado === 'BORRADOR')
<div style="border:2px solid #000; color:#000; text-align:center; font-size:9pt;
            font-weight:bold; letter-spacing:3px; padding:3px 0; margin-bottom:4px;">
  ** BORRADOR — NO VÁLIDO — BORRADOR — NO VÁLIDO — BORRADOR **
</div>
@endif

{{-- ENCABEZADO --}}
<table style="width:100%; border-collapse:collapse; margin-bottom:3px;">
  <tr>
    <td style="width:40%; border:1px solid #000; text-align:center; padding:6px; vertical-align:middle;">
      @if($logo)
        <img src="{{ $logo }}" style="max-height:124px; max-width:95%;">
      @else
        <div style="font-size:9pt; color:#777; padding:8px;">(LOGO INSTITUCIONAL)</div>
      @endif
    </td>
    <td style="width:60%; border:1px solid #000; border-left:none; vertical-align:top; padding:0;">
      <div style="text-align:center; padding:5px 4px; border-bottom:1px solid #000;">
        <span style="font-size:14pt; font-weight:bold; letter-spacing:1px;">ACCIÓN DE PERSONAL</span>
      </div>
      <table style="width:100%; border-collapse:collapse; table-layout:fixed;">
        <tr>
          <td style="border-right:1px solid #000; border-bottom:1px solid #000; padding:2px 6px; width:42%; font-weight:bold; font-size:7.5pt;">Nro.</td>
          <td style="border-bottom:1px solid #000; padding:2px 6px; font-size:9pt; font-weight:bold;">{{ $accion->numero_accion }}</td>
        </tr>
        <tr>
          <td style="border-right:1px solid #000; padding:2px 6px; font-weight:bold; font-size:7.5pt;">FECHA DE ELABORACIÓN</td>
          <td style="padding:2px 6px; font-size:8pt;">{{ $fechaElabStr }}</td>
        </tr>
      </table>
    </td>
  </tr>
</table>

{{-- DATOS DEL EMPLEADO --}}
<table style="width:100%; border-collapse:collapse; border:1px solid #000; margin-bottom:3px; table-layout:fixed;">
  <tr>
    <td style="width:42%; border-right:1px solid #000; border-bottom:1px solid #000; padding:3px 6px; text-align:center; vertical-align:middle;">
      <div style="font-size:10pt; font-weight:bold;">{{ strtoupper($accion->empleado->apellido_emp ?? '') }}</div>
      <div class="lbl" style="border-top:1px solid #ccc; margin-top:3px; padding-top:1px;">APELLIDOS</div>
    </td>
    <td style="width:58%; border-bottom:1px solid #000; padding:0; vertical-align:top;">
      <table style="width:100%; border-collapse:collapse; table-layout:fixed;">
        <tr>
          <td colspan="2" style="border-bottom:1px solid #000; padding:3px 6px; text-align:center;">
            <div style="font-size:10pt; font-weight:bold;">{{ strtoupper($accion->empleado->nombre_emp ?? '') }}</div>
            <div class="lbl" style="border-top:1px solid #ccc; margin-top:3px; padding-top:1px;">NOMBRES</div>
          </td>
        </tr>
        <tr>
          <td style="border-right:1px solid #000; padding:2px 5px; width:50%; vertical-align:top;">
            <div class="lbl">RIGE: &nbsp; DESDE (dd-mm-aaaa)</div>
            <div class="val">{{ \Carbon\Carbon::parse($accion->fecha_inicio)->format('d-m-Y') }}</div>
          </td>
          <td style="padding:2px 5px; width:50%; vertical-align:top;">
            <div class="lbl">HASTA (dd-mm-aaaa) (cuando aplica)</div>
            <div class="val">
              @if($accion->fecha_fin)
                {{ \Carbon\Carbon::parse($accion->fecha_fin)->format('d-m-Y') }}
              @else
                Hasta nueva orden
              @endif
            </div>
          </td>
        </tr>
      </table>
    </td>
  </tr>
  <tr>
    <td colspan="2" style="padding:0;">
      <table style="width:100%; border-collapse:collapse; table-layout:fixed;">
        <tr>
          <td style="border-right:1px solid #000; padding:2px 6px; width:38%; vertical-align:top;">
            <div class="lbl">DOCUMENTO DE IDENTIFICACIÓN</div>
            <div class="val">CÉDULA DE CIUDADANÍA</div>
          </td>
          <td style="padding:2px 6px; vertical-align:top;">
            <div class="lbl">NRO. DE IDENTIFICACIÓN</div>
            <div class="val">{{ $accion->empleado->identificacion ?? '' }}</div>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>

{{-- TIPO DE ACCIÓN --}}
<div style="border:1px solid #000; padding:3px 5px; margin-bottom:3px;">
  <div style="font-size:7.5pt; margin-bottom:3px;">
    <b>Escoja una opción</b> (según lo estipulado en el artículo 21 del Reglamento General a la Ley Orgánica del Servicio Público)
  </div>
  <table style="width:100%; border-collapse:collapse; table-layout:fixed;">
    <colgroup><col style="width:22%"><col style="width:24%"><col style="width:25%"><col style="width:29%"></colgroup>
    <tr>
      <td style="padding:1px 2px; font-size:7.5pt;"><span class="{{ cbcls($tipo,'INGRESO') }}">{!! cb($tipo,'INGRESO') !!}</span> INGRESO</td>
      <td style="padding:1px 2px; font-size:7.5pt;"><span class="{{ cbcls($tipo,'TRASPASO') }}">{!! cb($tipo,'TRASPASO') !!}</span> TRASPASO</td>
      <td style="padding:1px 2px; font-size:7.5pt;"><span class="{{ cbcls($tipo,'INCREMENTO RMU') }}">{!! cb($tipo,'INCREMENTO RMU') !!}</span> INCREMENTO RMU</td>
      <td style="padding:1px 2px; font-size:7.5pt;"><span class="{{ cbcls($tipo,'REVISION CLASI. PUESTO') }}">{!! cb($tipo,'REVISION CLASI. PUESTO') !!}</span> REVISIÓN CLASI. PUESTO</td>
    </tr>
    <tr>
      <td style="padding:1px 2px; font-size:7.5pt;"><span class="{{ cbcls($tipo,'REINGRESO') }}">{!! cb($tipo,'REINGRESO') !!}</span> REINGRESO</td>
      <td style="padding:1px 2px; font-size:7.5pt;"><span class="{{ cbcls($tipo,'CAMBIO ADMINISTRATIVO') }}">{!! cb($tipo,'CAMBIO ADMINISTRATIVO') !!}</span> CAMBIO ADMINISTRATIVO</td>
      <td style="padding:1px 2px; font-size:7.5pt;"><span class="{{ cbcls($tipo,'SUBROGACION') }}">{!! cb($tipo,'SUBROGACION') !!}</span> SUBROGACIÓN</td>
      <td style="padding:1px 2px; font-size:7.5pt;"><span class="cb">&nbsp;</span> OTRO (DETALLAR)</td>
    </tr>
    <tr>
      <td style="padding:1px 2px; font-size:7.5pt;"><span class="{{ cbcls($tipo,'RESTITUCION') }}">{!! cb($tipo,'RESTITUCION') !!}</span> RESTITUCIÓN</td>
      <td style="padding:1px 2px; font-size:7.5pt;"><span class="{{ cbcls($tipo,'INTERCAMBIO VOLUNTARIO') }}">{!! cb($tipo,'INTERCAMBIO VOLUNTARIO') !!}</span> INTERCAMBIO VOLUNTARIO</td>
      <td style="padding:1px 2px; font-size:7.5pt;"><span class="{{ cbcls($tipo,'ENCARGO') }}">{!! cb($tipo,'ENCARGO') !!}</span> ENCARGO</td>
      <td></td>
    </tr>
    <tr>
      <td style="padding:1px 2px; font-size:7.5pt;"><span class="{{ cbcls($tipo,'REINTEGRO') }}">{!! cb($tipo,'REINTEGRO') !!}</span> REINTEGRO</td>
      <td style="padding:1px 2px; font-size:7.5pt;"><span class="{{ cbcls($tipo,'LICENCIA') }}">{!! cb($tipo,'LICENCIA') !!}</span> LICENCIA</td>
      <td style="padding:1px 2px; font-size:7.5pt;"><span class="{{ cbcls($tipo,'CESACION DE FUNCIONES') }}">{!! cb($tipo,'CESACION DE FUNCIONES') !!}</span> CESACIÓN DE FUNCIONES</td>
      <td></td>
    </tr>
    <tr>
      <td style="padding:1px 2px; font-size:7.5pt;"><span class="{{ cbcls($tipo,'ASCENSO') }}">{!! cb($tipo,'ASCENSO') !!}</span> ASCENSO</td>
      <td style="padding:1px 2px; font-size:7.5pt;"><span class="{{ cbcls($tipo,'COMISION DE SERVICIOS') }}">{!! cb($tipo,'COMISION DE SERVICIOS') !!}</span> COMISIÓN DE SERVICIOS</td>
      <td style="padding:1px 2px; font-size:7.5pt;"><span class="{{ cbcls($tipo,'DESTITUCION') }}">{!! cb($tipo,'DESTITUCION') !!}</span> DESTITUCIÓN</td>
      <td></td>
    </tr>
    <tr>
      <td style="padding:1px 2px; font-size:7.5pt;"><span class="{{ cbcls($tipo,'TRASLADO') }}">{!! cb($tipo,'TRASLADO') !!}</span> TRASLADO</td>
      <td style="padding:1px 2px; font-size:7.5pt;"><span class="{{ cbcls($tipo,'SANCIONES') }}">{!! cb($tipo,'SANCIONES') !!}</span> SANCIONES</td>
      <td style="padding:1px 2px; font-size:7.5pt;"><span class="{{ cbcls($tipo,'VACACIONES') }}">{!! cb($tipo,'VACACIONES') !!}</span> VACACIONES</td>
      <td></td>
    </tr>
  </table>
  <div style="font-size:7.5pt; border-top:1px solid #ccc; margin-top:2px; padding-top:2px;">
    EN CASO DE REQUERIR ESPECIFICACIÓN DE LO SELECCIONADO:
    <span style="border-bottom:1px solid #000; display:inline-block; width:55%;">{{ $accion->especificacion ?? '' }}</span>
  </div>
  <div style="font-size:7.5pt; margin-top:2px;">
    <b>* PRESENTÓ LA DECLARACIÓN JURADA</b> (número 2 del art. 3 RLOSEP) &nbsp;
    SI <span class="{{ $declaracionSI ? 'cb cb-on' : 'cb' }}">{!! $declaracionSI ? 'X' : '&nbsp;' !!}</span> &nbsp;&nbsp;
    NO APLICA <span class="{{ !$declaracionSI ? 'cb cb-on' : 'cb' }}">{!! !$declaracionSI ? 'X' : '&nbsp;' !!}</span>
  </div>
</div>

{{-- MOTIVACIÓN --}}
@php
  $motivacion = $accion->motivacion ?? '';
  $esHtml = str_contains($motivacion, '<p>') || str_contains($motivacion, '<strong>') || str_contains($motivacion, '<em>');
@endphp
<div style="border:1px solid #000; padding:3px 5px; margin-bottom:3px;">
  <div class="lbl">MOTIVACIÓN: <span style="font-weight:normal;">(adjuntar anexo si lo posee)</span></div>
  @if($esHtml)
    <div style="min-height:52px; font-size:8pt; word-wrap:break-word; overflow:hidden; margin-top:2px; text-align:justify;">{!! $motivacion !!}</div>
  @else
    <div style="min-height:52px; font-size:8pt; word-wrap:break-word; overflow:hidden; margin-top:2px; white-space:pre-wrap; text-align:justify;">{{ $motivacion }}</div>
  @endif
</div>

{{-- SITUACIÓN ACTUAL vs PROPUESTA --}}
<table style="width:100%; border-collapse:collapse; border:1px solid #000; margin-bottom:3px; table-layout:fixed;">
  <tr>
    <td style="width:50%; border-right:1px solid #000; padding:0; vertical-align:top;">
      <div class="gray" style="text-align:center; font-weight:bold; font-size:8pt; padding:2px; border-bottom:1px solid #000;">SITUACION ACTUAL</div>
      <table style="width:100%; border-collapse:collapse; table-layout:fixed;">
        <tr><td class="sit-lbl">PROCESO INSTITUCIONAL:</td><td class="sit-val">{{ $showActual ? ($accion->actual_proceso_inst ?? '') : '' }}</td></tr>
        <tr><td class="sit-lbl">NIVEL DE GESTIÓN:</td><td class="sit-val">&nbsp;</td></tr>
        <tr><td class="sit-lbl">UNIDAD ADMINISTRATIVA:</td><td class="sit-val">{{ $showActual ? $deptActual : '' }}</td></tr>
        <tr><td class="sit-lbl">LUGAR DE TRABAJO:</td><td class="sit-val">{{ $showActual ? $ubicacion : '' }}</td></tr>
        <tr><td class="sit-lbl">DENOMINACIÓN DEL PUESTO:</td><td class="sit-val">{{ $showActual ? ($accion->actual_cargo ?? '') : '' }}</td></tr>
        <tr><td class="sit-lbl">GRUPO OCUPACIONAL:</td><td class="sit-val">{{ $showActual ? ($accion->actual_grupo_ocup ?? '') : '' }}</td></tr>
        <tr><td class="sit-lbl">GRADO:</td><td class="sit-val">{{ $showActual ? ($accion->actual_grado ?? '') : '' }}</td></tr>
        <tr><td class="sit-lbl">REMUNERACIÓN MENSUAL:</td><td class="sit-val">{{ $showActual ? '$'.number_format($accion->actual_remuneracion ?? 0, 2) : '' }}</td></tr>
        <tr>
          <td class="sit-lbl" style="border-bottom:none;">PARTIDA INDIVIDUAL:</td>
          <td style="font-size:6.5pt; word-break:break-all; padding:1px 4px; border-bottom:none; vertical-align:top;">{{ $showActual ? ($accion->actual_partida ?? '') : '' }}</td>
        </tr>
      </table>
    </td>
    <td style="width:50%; padding:0; vertical-align:top;">
      <div class="gray" style="text-align:center; font-weight:bold; font-size:8pt; padding:2px; border-bottom:1px solid #000;">SITUACION PROPUESTA</div>
      <table style="width:100%; border-collapse:collapse; table-layout:fixed;">
        <tr><td class="sit-lbl">PROCESO INSTITUCIONAL:</td><td class="sit-val">{{ $showPropuesta ? ($accion->propuesto_proceso_inst ?? '') : '' }}</td></tr>
        <tr><td class="sit-lbl">NIVEL DE GESTIÓN:</td><td class="sit-val">&nbsp;</td></tr>
        <tr><td class="sit-lbl">UNIDAD ADMINISTRATIVA:</td><td class="sit-val">{{ $showPropuesta ? $deptPropuestoFinal : '' }}</td></tr>
        <tr><td class="sit-lbl">LUGAR DE TRABAJO:</td><td class="sit-val">{{ $showPropuesta ? $ubicacion : '' }}</td></tr>
        <tr><td class="sit-lbl">DENOMINACIÓN DEL PUESTO:</td><td class="sit-val">{{ $showPropuesta ? ($accion->propuesto_cargo ?? '') : '' }}</td></tr>
        <tr><td class="sit-lbl">GRUPO OCUPACIONAL:</td><td class="sit-val">{{ $showPropuesta ? ($accion->propuesto_grupo_ocup ?? '') : '' }}</td></tr>
        <tr><td class="sit-lbl">GRADO:</td><td class="sit-val">{{ $showPropuesta ? ($accion->propuesto_grado ?? '') : '' }}</td></tr>
        <tr><td class="sit-lbl">REMUNERACIÓN MENSUAL:</td><td class="sit-val">{{ $showPropuesta ? '$'.number_format($accion->propuesto_remuneracion ?? 0, 2) : '' }}</td></tr>
        <tr>
          <td class="sit-lbl" style="border-bottom:none;">PARTIDA INDIVIDUAL:</td>
          <td style="font-size:6.5pt; word-break:break-all; padding:1px 4px; border-bottom:none; vertical-align:top;">{{ $showPropuesta ? ($accion->propuesto_partida ?? '') : '' }}</td>
        </tr>
      </table>
    </td>
  </tr>
</table>

{{-- POSESIÓN DEL PUESTO --}}
<div style="border:1px solid #000; margin-bottom:3px;">
  <div style="font-weight:bold; font-size:7.5pt; padding:2px 5px; border-bottom:1px solid #000;">POSESIÓN DEL PUESTO</div>
  <table style="width:100%; border-collapse:collapse; table-layout:fixed; padding:0;">
    <tr>
      <td style="width:55%; padding:4px 6px; vertical-align:top; font-size:7.5pt; border-right:1px solid #000;">
        YO, <span style="border-bottom:1px solid #000; display:inline-block; width:58%;">{{ $fillPosesion ? strtoupper(($accion->empleado->apellido_emp ?? '') . ' ' . ($accion->empleado->nombre_emp ?? '')) : '' }}</span><br>
        JURO LEALTAD AL ESTADO ECUATORIANO.<br>
        LUGAR: <span style="border-bottom:1px solid #000; display:inline-block; width:26%;">{{ $fillPosesion ? $ubicacion : '' }}</span>
        &nbsp; FECHA: <span style="border-bottom:1px solid #000; display:inline-block; width:26%;">{{ $fillPosesion ? $fechaElabStr : '' }}</span><br><br>
        <div style="font-size:7pt;">** (EN CASO DE GANADOR DE CONCURSO DE MÉRITOS Y OPOSICIÓN)</div>
        <table style="border-collapse:collapse; margin-top:3px; width:75%;">
          <tr>
            <td style="border:1px solid #000; padding:2px 5px; font-weight:bold; font-size:7.5pt; width:50%;">NRO. ACTA FINAL</td>
            <td style="border:1px solid #000; border-left:none; padding:2px 5px; font-weight:bold; font-size:7.5pt;">FECHA</td>
          </tr>
          <tr>
            <td style="border:1px solid #000; border-top:none; padding:5px;">&nbsp;</td>
            <td style="border:1px solid #000; border-left:none; border-top:none; padding:5px;">&nbsp;</td>
          </tr>
        </table>
      </td>
      <td style="width:45%; padding:4px 6px; vertical-align:top; font-size:7.5pt;">
        CON NRO. DE DOCUMENTO DE IDENTIFICACIÓN:
        <span style="border-bottom:1px solid #000; display:inline-block; width:40%;">{{ $fillPosesion ? ($accion->empleado->identificacion ?? '') : '' }}</span><br><br><br>
        FIRMA: <span style="border-bottom:1px solid #000; display:inline-block; width:62%;">&nbsp;</span><br>
        <div style="text-align:right; padding-right:5%; font-weight:bold; margin-top:2px;">SERVIDOR PÚBLICO</div>
      </td>
    </tr>
  </table>
</div>

{{-- RESPONSABLES DE APROBACIÓN --}}
<div style="border:1px solid #000; margin-bottom:3px;">
  <div class="gray" style="text-align:center; font-weight:bold; font-size:8pt; padding:2px; border-bottom:1px solid #000;">RESPONSABLES DE APROBACIÓN</div>
  <table style="width:100%; border-collapse:collapse; table-layout:fixed;">
    <tr>
      <td style="width:50%; border-right:1px solid #000; padding:5px 6px; vertical-align:bottom;">
        <div class="lbl">DIRECTOR (A) O RESPONSABLE DE TALENTO HUMANO</div>
        <div style="min-height:26px;"></div>
        <div style="font-size:7.5pt;">FIRMA: <span style="border-bottom:1px solid #000; display:inline-block; width:68%;">&nbsp;</span></div>
        <div style="font-size:7.5pt; margin-top:1px;">NOMBRE: <span style="border-bottom:1px solid #000; display:inline-block; width:62%;">{{ $firmanteThNombre }}</span></div>
        <div style="font-size:7.5pt; margin-top:1px;">PUESTO: <span style="border-bottom:1px solid #000; display:inline-block; width:64%;">{{ $firmanteThCargo }}</span></div>
      </td>
      <td style="width:50%; padding:5px 6px; vertical-align:bottom;">
        <div class="lbl">AUTORIDAD NOMINADORA O SU DELEGADO</div>
        <div style="min-height:26px;"></div>
        <div style="font-size:7.5pt;">FIRMA: <span style="border-bottom:1px solid #000; display:inline-block; width:68%;">&nbsp;</span></div>
        <div style="font-size:7.5pt; margin-top:1px;">NOMBRE: <span style="border-bottom:1px solid #000; display:inline-block; width:62%;">{{ $firmanteAutNombre }}</span></div>
        <div style="font-size:7.5pt; margin-top:1px;">PUESTO: <span style="border-bottom:1px solid #000; display:inline-block; width:64%;">{{ $firmanteAutCargo }}</span></div>
      </td>
    </tr>
  </table>
</div>

{{-- FOOTER P1 --}}
<table style="width:100%; border-collapse:collapse; margin-top:3px;">
  <tr>
    <td style="font-size:6.5pt; color:#555;">Elaborado por el Ministerio del Trabajo</td>
    <td style="font-size:6.5pt; color:#555; text-align:right;">Fecha de actualización de formato: 2024-08-23 &nbsp;/&nbsp; Versión: 01.1 &nbsp;/&nbsp; Página 1 de 2</td>
  </tr>
</table>

</div>

{{-- ==================== PÁGINA 2 ==================== --}}
<div class="page pg2">

{{-- RESPONSABLES DE FIRMAS --}}
<div style="border:1px solid #000; margin-bottom:4px;">
  <div class="gray" style="text-align:center; font-weight:bold; font-size:8pt; padding:2px; border-bottom:1px solid #000;">RESPONSABLES DE FIRMAS</div>
  <table style="width:100%; border-collapse:collapse; table-layout:fixed;">
    <tr>
      <td style="width:50%; border-right:1px solid #000; padding:0; vertical-align:top;">
        <div class="gray" style="text-align:center; font-weight:bold; font-size:7.5pt; padding:2px; border-bottom:1px solid #000;">ACEPTACIÓN Y/O RECEPCIÓN DEL SERVIDOR PÚBLICO</div>
        <div style="padding:5px 6px;">
          <div style="min-height:55px;"></div>
          <div style="font-size:7.5pt;">FIRMA <span style="border-bottom:1px solid #000; display:inline-block; width:74%;">&nbsp;</span></div>
          <div style="font-size:7.5pt; margin-top:2px;">NOMBRE: <span style="border-bottom:1px solid #000; display:inline-block; width:68%;">{{ $tipo !== 'INGRESO' ? strtoupper(($accion->empleado->apellido_emp ?? '') . ', ' . ($accion->empleado->nombre_emp ?? '')) : '' }}</span></div>
          <div style="font-size:7.5pt; margin-top:2px;">FECHA: <span style="border-bottom:1px solid #000; display:inline-block; width:71%;">{{ $tipo !== 'INGRESO' ? 'CONSTA EN FIRMA ELECTRÓNICA' : '' }}</span></div>
          <div style="font-size:7.5pt; margin-top:2px;">HORA: <span style="border-bottom:1px solid #000; display:inline-block; width:73%;">{{ $tipo !== 'INGRESO' ? 'CONSTA EN FIRMA ELECTRÓNICA' : '' }}</span></div>
        </div>
      </td>
      <td style="width:50%; padding:0; vertical-align:top;">
        <div class="gray" style="text-align:center; font-weight:bold; font-size:7.5pt; padding:2px; border-bottom:1px solid #000;">EN CASO DE NEGATIVA DE LA RECEPCIÓN (TESTIGO)</div>
        <div style="padding:5px 6px;">
          <div style="min-height:55px;"></div>
          <div style="font-size:7.5pt;">FIRMA: <span style="border-bottom:1px solid #000; display:inline-block; width:73%;">&nbsp;</span></div>
          <div style="font-size:7.5pt; margin-top:2px;">NOMBRE: <span style="border-bottom:1px solid #000; display:inline-block; width:68%;">&nbsp;</span></div>
          <div style="font-size:7.5pt; margin-top:2px;">FECHA: <span style="border-bottom:1px solid #000; display:inline-block; width:71%;">&nbsp;</span></div>
          @if($tipo !== 'INGRESO')
          <div style="font-size:7.5pt; margin-top:4px;">
            <b>RAZÓN:</b> En presencia del testigo se deja constancia de que la o el servidor
            público tiene la negativa de recibir la comunicación de registro de esta acción de personal.
          </div>
          @endif
        </div>
      </td>
    </tr>
  </table>
</div>

{{-- TRES RESPONSABLES --}}
<div style="border:1px solid #000; margin-bottom:12px;">
  <table style="width:100%; border-collapse:collapse; table-layout:fixed;">
    <tr>
      <td style="width:33.3%; border-right:1px solid #000; padding:0; vertical-align:top;">
        <div class="gray" style="text-align:center; font-weight:bold; font-size:7.5pt; padding:2px; border-bottom:1px solid #000;">RESPONSABLE DE ELABORACIÓN</div>
        <div style="padding:5px 6px;">
          <div style="min-height:55px;"></div>
          <div style="font-size:7.5pt;">FIRMA: <span style="border-bottom:1px solid #000; display:inline-block; width:68%;">&nbsp;</span></div>
          <div style="font-size:7.5pt; margin-top:2px;">NOMBRE: <span style="border-bottom:1px solid #000; display:inline-block; width:62%;">{{ $creadorNombre }}</span></div>
          <div style="font-size:7.5pt; margin-top:2px;">PUESTO: <span style="border-bottom:1px solid #000; display:inline-block; width:62%;">{{ $creadorPuesto }}</span></div>
        </div>
      </td>
      @php
        $cargoAccion = strtoupper(trim($accion->actual_cargo ?? $accion->propuesto_cargo ?? ''));
        $esDirectorTH = str_contains($cargoAccion, 'DIRECTOR') && str_contains($cargoAccion, 'TALENTO HUMANO');
      @endphp
      <td style="width:33.3%; border-right:1px solid #000; padding:0; vertical-align:top;">
        <div class="gray" style="text-align:center; font-weight:bold; font-size:7.5pt; padding:2px; border-bottom:1px solid #000;">RESPONSABLE DE REVISIÓN</div>
        <div style="padding:5px 6px;">
          <div style="min-height:55px;"></div>
          <div style="font-size:7.5pt;">FIRMA: <span style="border-bottom:1px solid #000; display:inline-block; width:68%;">&nbsp;</span></div>
          <div style="font-size:7.5pt; margin-top:2px;">NOMBRE: <span style="border-bottom:1px solid #000; display:inline-block; width:62%;">{{ $esDirectorTH ? '' : $directorTH }}</span></div>
          <div style="font-size:7.5pt; margin-top:2px;">PUESTO: <span style="border-bottom:1px solid #000; display:inline-block; width:62%;">{{ $esDirectorTH ? '' : 'DIRECTOR(A) DE ADMINISTRACIÓN DEL TALENTO HUMANO' }}</span></div>
        </div>
      </td>
      <td style="width:33.4%; padding:0; vertical-align:top;">
        <div class="gray" style="text-align:center; font-weight:bold; font-size:7.5pt; padding:2px; border-bottom:1px solid #000;">RESPONSABLE DE REGISTRO Y CONTROL</div>
        <div style="padding:5px 6px;">
          <div style="min-height:55px;"></div>
          <div style="font-size:7.5pt;">FIRMA: <span style="border-bottom:1px solid #000; display:inline-block; width:68%;">&nbsp;</span></div>
          <div style="font-size:7.5pt; margin-top:2px;">NOMBRE: <span style="border-bottom:1px solid #000; display:inline-block; width:62%;">{{ $creadorNombre }}</span></div>
          <div style="font-size:7.5pt; margin-top:2px;">PUESTO: <span style="border-bottom:1px solid #000; display:inline-block; width:62%;">{{ $creadorPuesto }}</span></div>
        </div>
      </td>
    </tr>
  </table>
</div>

{{-- USO EXCLUSIVO TH --}}
<div style="border-top:2px dashed #000; border-bottom:2px dashed #000; padding:5px 0; margin-bottom:8px; text-align:center;">
  <span style="font-size:13pt; font-weight:bold;">** USO EXCLUSIVO PARA TALENTO HUMANO</span>
</div>

{{-- REGISTRO DE NOTIFICACIÓN --}}
<div style="border:1px solid #000; padding:6px 8px;">
  <div style="font-size:8pt; margin-bottom:6px;">
    <b>REGISTRO DE NOTIFICACIÓN AL SERVIDOR PÚBLICO DE LA ACCIÓN DE PERSONAL</b>
    <span style="font-size:7pt; font-weight:normal;"> (primer inciso del art. 22 RGLOSEP, art. 101 COA, art. 66 y 126 ERJAFE)</span>
  </div>

  <div style="font-size:8pt; margin-bottom:6px;">
    COMUNICACIÓN ELECTRÓNICA: &nbsp; <span class="cb cb-on">X</span>
  </div>

  <table style="width:100%; border-collapse:collapse; table-layout:fixed; margin-bottom:4px;">
    <tr>
      <td style="width:50%; font-size:8pt; padding:2px 0;">
        FECHA: <span style="border-bottom:1px solid #000; display:inline-block; width:65%;">CONSTA EN CORREO ELECTRÓNICO</span>
      </td>
      <td style="width:50%; font-size:8pt; padding:2px 0;">
        HORA: <span style="border-bottom:1px solid #000; display:inline-block; width:65%;">CONSTA EN CORREO ELECTRÓNICO</span>
      </td>
    </tr>
  </table>

  <div style="font-size:8pt; margin-bottom:8px;">
    ** MEDIO: <span style="border-bottom:1px solid #000; display:inline-block; width:38%;">{{ $accion->medio ?? 'DIGITAL' }}</span>
  </div>

  <div style="border-bottom:1px solid #000; margin-bottom:5px; width:55%;">&nbsp;</div>
  <div style="border-bottom:1px solid #000; margin-bottom:5px; width:55%;">&nbsp;</div>
  <div style="border-bottom:1px solid #000; margin-bottom:5px; width:55%;">&nbsp;</div>

  <div style="min-height:35px;"></div>

  <div style="text-align:center; margin-bottom:4px;">
    <span style="border-top:1px solid #000; display:inline-block; width:48%; padding-top:3px; font-size:7.5pt; font-weight:bold; text-align:center;">
      FIRMA DEL RESPONSABLE QUE NOTIFICÓ
    </span>
  </div>

  <div style="font-size:8pt; margin-bottom:2px;">
    NOMBRE: <span style="border-bottom:1px solid #000; display:inline-block; width:65%;">{{ $creadorNombre }}</span>
  </div>
  <div style="font-size:8pt; margin-bottom:8px;">
    PUESTO: <span style="border-bottom:1px solid #000; display:inline-block; width:65%;">{{ $creadorPuesto }}</span>
  </div>

  <div style="font-size:7pt; color:#333;">
    ** Si la comunicación fue electrónica se deberá colocar el medio por el cual se notificó al servidor; así como, el número del documento.
  </div>
</div>

{{-- FOOTER P2 --}}
<table style="width:100%; border-collapse:collapse; margin-top:4px;">
  <tr>
    <td style="font-size:6.5pt; color:#555;">Elaborado por el Ministerio del Trabajo</td>
    <td style="font-size:6.5pt; color:#555; text-align:right;">Fecha de actualización de formato: 2024-08-23 &nbsp;/&nbsp; Versión: 01.1 &nbsp;/&nbsp; Página 1 de 2</td>
  </tr>
</table>

</div>
</body>
</html>
