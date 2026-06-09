<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, sans-serif; font-size: 10.5pt; color: #000; }
  @page { margin: 22mm 22mm 22mm 22mm; size: a4 portrait; }

  .header-table { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
  p { text-align: justify; line-height: 1.8; }
  .footer { margin-top: 30px; font-size: 7.5pt; color: #555; text-align: right; }
</style>
</head>
<body>

{{-- Encabezado institucional --}}
<table class="header-table">
  <tr>
    <td style="width:13%; text-align:center; vertical-align:middle;">
      @if($logo)<img src="data:image/png;base64,{{ $logo }}" style="max-height:75px; max-width:105px;">@endif
    </td>
    <td style="text-align:center; vertical-align:middle; padding:0 12px;">
      <div style="font-size:11pt; font-weight:bold; text-transform:uppercase;">{{ strtoupper($nombreInst) }}</div>
      <div style="font-size:10.5pt; font-weight:bold; text-transform:uppercase; margin-top:4px;">CERTIFICADO LABORAL</div>
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

{{-- Fecha --}}
<p style="margin-top:22px; margin-bottom:22px; text-align:left;">
  {{ $ciudad }}, {{ $fechaStr }}
</p>

{{-- Certifica quién --}}
<p style="font-weight:bold; text-transform:uppercase; margin-bottom:8px;">
  EL RESPONSABLE DE TALENTO HUMANO DEL {{ strtoupper($nombreInst) }},
</p>

<p style="font-weight:bold; font-size:11pt; text-align:left; margin-bottom:18px;">
  CERTIFICA:
</p>

{{-- Párrafo principal --}}
<p style="margin-bottom:16px;">
  Que {{ $art }} <strong>{{ $nombre }}</strong>, {{ $port }} de la cédula de identidad
  N° <strong>{{ $cedula }}</strong>, de conformidad a la información que reposa en los
  expedientes de la Dirección de Administración del Talento Humano, {{ $verbo }} sus servicios
  en esta Institución desde el <strong>{{ $fechaIngStr }}</strong>@if(!$activo && $fechaSalStr) hasta el <strong>{{ $fechaSalStr }}</strong>@endif,
  desempeñando el cargo de <strong>{{ $cargo }}</strong>, percibiendo una Remuneración
  Mensual Unificada de <strong>$ {{ $sueldo }}</strong>.
</p>

{{-- Cierre --}}
<p>
  El presente certificado se expide a petición {{ $inter }}, para los fines que estime
  conveniente.
</p>

{{-- Firma --}}
<div style="margin-top:65px; text-align:center;">
  <div style="border-top:1px solid #000; width:280px; margin:0 auto 5px;"></div>
  <p style="font-weight:bold; text-align:center; text-transform:uppercase; line-height:1.5;">{{ strtoupper($firmanteNom) }}</p>
  <p style="text-align:center; text-transform:uppercase; line-height:1.5;">{{ strtoupper($firmanteCar) }}</p>
  <p style="text-align:center; text-transform:uppercase; line-height:1.5;">{{ strtoupper($nombreInst) }}</p>
</div>

{{-- Pie --}}
<p class="footer">
  Generado por: {{ strtoupper($generadoPor) }} &nbsp;&nbsp;|&nbsp;&nbsp; {{ now()->format('d/m/Y H:i') }}
</p>

</body>
</html>
