<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, sans-serif; font-size: 10pt; color: #000; }
  @page { margin: 20mm 20mm 20mm 20mm; size: a4 portrait; }

  .header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
  .numero-cert  { text-align: right; font-size: 9pt; color: #444; margin-bottom: 30px; }
  .cuerpo       { line-height: 1.8; text-align: justify; margin-top: 20px; }
  .sello        { margin-top: 60px; text-align: center; }
  .firma-linea  { border-top: 1px solid #000; width: 220px; margin: 0 auto 4px; }
  .firma-nombre { font-weight: bold; font-size: 10pt; text-transform: uppercase; }
  .firma-cargo  { font-size: 9pt; }
  .footer       { margin-top: 40px; font-size: 7.5pt; color: #666; text-align: right; }
</style>
</head>
<body>

{{-- Encabezado institucional estándar --}}
<table class="header-table">
  <tr>
    <td style="width:13%; text-align:center; vertical-align:middle;">
      @if($logo)<img src="data:image/png;base64,{{ $logo }}" style="max-height:75px; max-width:105px;">@endif
    </td>
    <td style="text-align:center; vertical-align:middle; padding:0 10px;">
      <div style="font-size:11pt; font-weight:bold; text-transform:uppercase;">{{ $nombreInst }}</div>
      <div style="font-size:10pt; font-weight:bold; text-transform:uppercase; margin-top:4px;">CERTIFICADO LABORAL</div>
      <div style="font-size:9pt; color:#555; margin-top:2px;">{{ $numero }}</div>
    </td>
  </tr>
</table>

@php
  $sexo = strtoupper(trim($empleado->sexo ?? ''));
  $art   = $sexo === 'FEMENINO' ? 'la señora'   : 'el señor';
  $port  = $sexo === 'FEMENINO' ? 'portadora'   : 'portador';
  $inter = $sexo === 'FEMENINO' ? 'la interesada' : 'el interesado';

  $sueldo = number_format((float)$empleado->sueldo, 2);
  $cargo  = strtoupper(trim($empleado->cargo_empleado ?? ''));
  $nombre = strtoupper(trim($empleado->apellido_emp) . ' ' . trim($empleado->nombre_emp));
  $cedula = trim($empleado->identificacion);
@endphp

{{-- Encabezado del certificante --}}
<p style="margin-top:30px; font-size:10pt; font-weight:bold; text-transform:uppercase; text-align:center;">
  EL RESPONSABLE DE TALENTO HUMANO DEL {{ strtoupper($nombreInst) }},
</p>

<p style="margin-top:6px; font-size:10.5pt; font-weight:bold; text-align:center; letter-spacing:1px;">
  CERTIFICA:
</p>

{{-- Cuerpo del certificado --}}
<p class="cuerpo">
  Que {{ $art }} <strong>{{ $nombre }}</strong>, {{ $port }} de la cédula de identidad
  N° <strong>{{ $cedula }}</strong>, presta sus servicios en esta Institución desde el
  <strong>{{ $fechaIngStr }}</strong>, desempeñando el cargo de
  <strong>{{ $cargo }}</strong>, percibiendo una Remuneración Mensual Unificada de
  <strong>$ {{ $sueldo }}</strong>.
</p>

<p class="cuerpo" style="margin-top:18px;">
  El presente certificado se expide a petición de {{ $inter }}, para los fines que estime
  conveniente.
</p>

{{-- Lugar y fecha --}}
<p style="margin-top:30px; text-align:right; font-size:10pt;">
  {{ $ciudad }}, {{ $fechaStr }}
</p>

{{-- Firma --}}
<div class="sello">
  <div class="firma-linea"></div>
  <p class="firma-nombre">{{ strtoupper($firmanteNom) }}</p>
  <p class="firma-cargo">{{ strtoupper($firmanteCar) }}</p>
  <p class="firma-cargo">{{ strtoupper($nombreInst) }}</p>
</div>

{{-- Pie --}}
<p class="footer">
  Generado por: {{ strtoupper($generadoPor) }} &nbsp;&nbsp;|&nbsp;&nbsp; {{ now()->format('d/m/Y H:i') }}
</p>

</body>
</html>
