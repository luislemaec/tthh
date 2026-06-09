<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, sans-serif; font-size: 10.5pt; color: #000; }
  @page { margin: 22mm 25mm 22mm 25mm; size: a4 portrait; }

  .header-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
  .footer { font-size: 7.5pt; color: #555; text-align: right; margin-top: 30px; }
</style>
</head>
<body>

{{-- Encabezado con logo --}}
<table class="header-table">
  <tr>
    <td style="width:13%; text-align:center; vertical-align:middle;">
      @if($logo)<img src="data:image/png;base64,{{ $logo }}" style="max-height:75px; max-width:105px;">@endif
    </td>
    <td style="text-align:center; vertical-align:middle; padding:0 10px;">
      <div style="font-size:11pt; font-weight:bold; text-transform:uppercase; line-height:1.4;">{{ strtoupper($nombreInst) }}</div>
      <div style="font-size:10.5pt; font-weight:bold; text-transform:uppercase; margin-top:5px;">CERTIFICADO LABORAL</div>
      <div style="font-size:9pt; color:#444; margin-top:3px;">Nro. {{ $numero }}</div>
    </td>
  </tr>
</table>

@php
  $sexo  = strtoupper(trim($empleado->sexo ?? ''));
  $art   = $sexo === 'FEMENINO' ? 'la señora'        : 'el señor';
  $port  = $sexo === 'FEMENINO' ? 'portadora'        : 'portador';
  $inter = $sexo === 'FEMENINO' ? 'de la interesada' : 'del interesado';
  $verbo = $activo              ? 'presta'           : 'prestó';

  $sueldo = number_format((float)($empleado->sueldo ?? 0), 2, '.', ',');
  $cargo  = strtoupper(trim($empleado->cargo_empleado ?? ''));
  $nombre = strtoupper(trim($empleado->apellido_emp) . ' ' . trim($empleado->nombre_emp));
  $cedula = trim($empleado->identificacion);
@endphp

<div style="padding-left:3cm; padding-right:3cm;">

{{-- Fecha alineada a la derecha --}}
<p style="text-align:right; margin-bottom:25px;">
  {{ $ciudad }}, {{ $fechaStr }}
</p>

{{-- Certifica quién — centrado --}}
<p style="text-align:center; font-weight:bold; text-transform:uppercase; line-height:1.6; margin-bottom:12px;">
  EL RESPONSABLE DE TALENTO HUMANO DEL {{ strtoupper($nombreInst) }},
</p>

{{-- CERTIFICA — centrado --}}
<p style="text-align:center; font-weight:bold; font-size:11pt; margin-bottom:18px;">
  CERTIFICA:
</p>

{{-- Párrafo principal — justificado --}}
<p style="text-align:justify; line-height:1.8; margin-bottom:16px;">
  Que {{ $art }} <strong>{{ $nombre }}</strong>, {{ $port }} de la cédula de
  identidad N° {{ $cedula }}, de conformidad a la información que reposa en los expedientes de la
  Dirección de Administración del Talento Humano, {{ $verbo }} sus servicios en esta Institución
  desde el {{ $fechaIngStr }}@if(!$activo && $fechaSalStr) hasta el {{ $fechaSalStr }}@endif,
  desempeñando el cargo de <strong>{{ $cargo }}</strong>, percibiendo una Remuneración Mensual
  Unificada de <strong>$ {{ $sueldo }}</strong>.
</p>

{{-- Cierre — justificado --}}
<p style="text-align:justify; line-height:1.8;">
  El presente certificado se expide a petición {{ $inter }}, para los fines que estime
  conveniente.
</p>

{{-- Firma — centrada --}}
<div style="margin-top:70px; text-align:center;">
  <div style="border-top:1px solid #000; width:280px; margin:0 auto 6px;"></div>
  <p style="font-weight:bold; text-transform:uppercase; line-height:1.6; text-align:center;">{{ strtoupper($firmanteNom) }}</p>
  <p style="text-transform:uppercase; line-height:1.5; text-align:center;">{{ strtoupper($firmanteCar) }}</p>
  <p style="text-transform:uppercase; line-height:1.5; text-align:center;">{{ strtoupper($nombreInst) }}</p>
</div>

{{-- Pie --}}
<p class="footer">
  Generado por: {{ strtoupper($generadoPor) }} &nbsp;&nbsp;|&nbsp;&nbsp; {{ now()->format('d/m/Y H:i') }}
</p>

</div>

</body>
</html>
