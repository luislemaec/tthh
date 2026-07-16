<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, sans-serif; font-size: 9pt; color: #000; margin: 2cm; }
  @page { size: a4 portrait; margin: 0; }
  table { border-collapse: collapse; width: 100%; table-layout: fixed; }

  .section-title { font-weight: bold; font-size: 9pt; text-transform: uppercase;
    background-color: #1e5c8a; color: #fff; padding: 4px 8px; margin: 10px 0 4px 0; }

  .info-table td { border: 1px solid #555; padding: 5px 8px; font-size: 8.5pt; vertical-align: middle; }
  .info-label { font-weight: bold; background-color: #e5f0f7; }
  .num-td { text-align: right; font-weight: bold; }

  .total-row td { background-color: #1e5c8a; color: #fff; font-weight: bold; font-size: 10pt; }
  .subtotal-row td { background-color: #cce0ee; font-weight: bold; }

  .firmas td { border: 1px solid #555; padding: 40px 8px 6px 8px; text-align: center;
    font-weight: bold; font-size: 7.5pt; text-transform: uppercase; background-color: #e5f0f7; }

  .gen-line { font-size: 7.5pt; color: #555; margin-top: 12px; text-align: right; }
</style>
</head>
<body>

@php
  $fmt = fn($v) => '$' . number_format((float)$v, 2);
  $firmanteTh = \Illuminate\Support\Facades\DB::table('dbo.d2_configuracion')->whereRaw("LOWER(concepto) = 'firmante_th_nombre'")->value('valor') ?? 'RESPONSABLE TALENTO HUMANO';
  $firmanteThCargo = \Illuminate\Support\Facades\DB::table('dbo.d2_configuracion')->whereRaw("LOWER(concepto) = 'firmante_th_cargo'")->value('valor') ?? 'DIRECCIÓN DE TALENTO HUMANO';
  $firmanteAut = \Illuminate\Support\Facades\DB::table('dbo.d2_configuracion')->whereRaw("LOWER(concepto) = 'firmante_autoridad_nombre'")->value('valor') ?? 'MÁXIMA AUTORIDAD';
  $firmanteAutCargo = \Illuminate\Support\Facades\DB::table('dbo.d2_configuracion')->whereRaw("LOWER(concepto) = 'firmante_autoridad_cargo'")->value('valor') ?? 'AUTORIDAD NOMINADORA';
@endphp

<table style="width:100%; margin-bottom:10px; border-collapse:collapse;">
  <tr>
    <td style="width:15%; vertical-align:middle; text-align:center;">
      @if($logo)<img src="data:image/png;base64,{{ $logo }}" style="max-height:80px; max-width:110px;">@endif
    </td>
    <td style="text-align:center; vertical-align:middle; padding:0 10px;">
      <div style="font-size:11pt; font-weight:bold; text-transform:uppercase;">{{ $nombreInst }}</div>
      <div style="font-size:10pt; font-weight:bold; text-transform:uppercase; margin-top:3px;">FICHA DE LIQUIDACIÓN DE VIÁTICOS AL EXTERIOR</div>
      <div style="font-size:9pt; margin-top:2px;">N° {{ $solicitud->numero_solicitud ?? '___________________' }}</div>
    </td>
  </tr>
</table>

<div class="section-title">Datos del Servidor</div>
<table class="info-table" style="margin-bottom:6px;">
  <tr>
    <td class="info-label" style="width:30%;">Apellidos y Nombres</td>
    <td colspan="3">{{ strtoupper(($solicitud->empleado->apellido_emp ?? '') . ' ' . ($solicitud->empleado->nombre_emp ?? '')) }}</td>
  </tr>
  <tr>
    <td class="info-label">Cargo</td>
    <td>{{ strtoupper($solicitud->empleado->cargo_empleado ?? '') }}</td>
    <td class="info-label" style="width:22%;">Unidad</td>
    <td>{{ strtoupper($solicitud->unidad_nombre ?? '') }}</td>
  </tr>
  <tr>
    <td class="info-label">Destino (Ciudad - País)</td>
    <td>{{ strtoupper($solicitud->destino) }}</td>
    <td class="info-label">N° Solicitud</td>
    <td>{{ $solicitud->numero_solicitud }}</td>
  </tr>
  <tr>
    <td class="info-label">Fecha Salida</td>
    <td>{{ \Carbon\Carbon::parse($solicitud->fecha_salida)->format('d/m/Y') }}</td>
    <td class="info-label">Fecha Regreso</td>
    <td>{{ \Carbon\Carbon::parse($solicitud->fecha_llegada)->format('d/m/Y') }}</td>
  </tr>
  @if($solicitud->resolucion_juridica)
  <tr>
    <td class="info-label">N° Resolución Jurídica</td>
    <td colspan="3">{{ $solicitud->resolucion_juridica }}</td>
  </tr>
  @endif
</table>

<div class="section-title">Liquidación de Viáticos Internacionales</div>
<table class="info-table" style="margin-bottom:4px;">
  <tr>
    <td class="info-label" style="width:55%;">Valor por día (tarifa internacional)</td>
    <td class="num-td">{{ $fmt($ficha->valor_por_dia) }}</td>
  </tr>
  <tr>
    <td class="info-label">Número de días</td>
    <td class="num-td">{{ $ficha->dias_viaticos }}</td>
  </tr>
  <tr class="subtotal-row">
    <td>TOTAL VIÁTICO</td>
    <td class="num-td">{{ $fmt($ficha->total_viatico) }}</td>
  </tr>

  <tr><td colspan="2" style="padding:2px;border:none;"></td></tr>

  <tr>
    <td class="info-label">Anticipo viático recibido</td>
    <td class="num-td">{{ $fmt($ficha->anticipo_viatico) }}</td>
  </tr>
  <tr class="subtotal-row">
    <td>TOTAL ANTICIPO</td>
    <td class="num-td">{{ $fmt($ficha->total_anticipo) }}</td>
  </tr>

  <tr>
    <td class="info-label" style="padding-left:12px;">Viáticos por pagar (Total viático − Anticipo)</td>
    <td class="num-td">{{ $fmt($ficha->viaticos_por_pagar) }}</td>
  </tr>

  <tr><td colspan="2" style="padding:2px;border:none;"></td></tr>

  <tr>
    <td colspan="2" style="background-color:#cce0ee; font-weight:bold; padding:4px 8px;">JUSTIFICATIVOS CON COMPROBANTES — EXTERIOR (Código 530304)</td>
  </tr>
  <tr>
    <td class="info-label" style="padding-left:12px;">Alimentación</td>
    <td class="num-td">{{ $fmt($ficha->justif_alimentacion) }}</td>
  </tr>
  <tr>
    <td class="info-label" style="padding-left:12px;">Alojamiento</td>
    <td class="num-td">{{ $fmt($ficha->justif_alojamiento) }}</td>
  </tr>
  <tr class="subtotal-row">
    <td>TOTAL JUSTIFICACIÓN</td>
    <td class="num-td">{{ $fmt($ficha->total_justificacion) }}</td>
  </tr>

  <tr><td colspan="2" style="padding:2px;border:none;"></td></tr>

  <tr>
    <td colspan="2" style="background-color:#cce0ee; font-weight:bold; padding:4px 8px;">MOVILIZACIÓN INTERNACIONAL (Código 530302)</td>
  </tr>
  <tr>
    <td class="info-label" style="padding-left:12px;">Movilización</td>
    <td class="num-td">{{ $fmt($ficha->movilizacion) }}</td>
  </tr>
  <tr>
    <td class="info-label" style="padding-left:12px;">Peajes / Parqueaderos</td>
    <td class="num-td">{{ $fmt($ficha->peajes_parqueaderos) }}</td>
  </tr>
  <tr class="subtotal-row">
    <td>DEVOLUCIÓN MOVILIZACIÓN</td>
    <td class="num-td">{{ $fmt($ficha->devolucion_movilizacion) }}</td>
  </tr>

  <tr>
    <td class="info-label">Otros Gastos</td>
    <td class="num-td">{{ $fmt($ficha->otros_gastos) }}</td>
  </tr>

  <tr class="total-row">
    <td>{{ $ficha->tipo_resultado === 'POR_COBRAR' ? 'VALOR A DEVOLVER POR EL EMPLEADO' : 'TOTAL A PAGAR' }}</td>
    <td class="num-td" style="color:#fff;">{{ $fmt(abs($ficha->total_a_pagar)) }}</td>
  </tr>
</table>

@if($ficha->cur_compromiso || $ficha->cur_devengado)
<div class="section-title">Referencias eSIGEF</div>
<table class="info-table" style="margin-bottom:6px;">
  @if($ficha->cur_compromiso)
  <tr>
    <td class="info-label" style="width:40%;">N° CUR Compromiso</td>
    <td>{{ $ficha->cur_compromiso }}</td>
  </tr>
  @endif
  @if($ficha->cur_devengado)
  <tr>
    <td class="info-label">N° CUR Devengado</td>
    <td>{{ $ficha->cur_devengado }}</td>
  </tr>
  @endif
  @if($ficha->comprobante_devolucion)
  <tr>
    <td class="info-label">Comprobante Devolución</td>
    <td>{{ $ficha->comprobante_devolucion }} &nbsp; Fecha: {{ $ficha->fecha_devolucion }}</td>
  </tr>
  @endif
</table>
@endif

<div style="margin-top: 20px;">
<table class="firmas">
  <tr>
    <td style="width:31%;">
      {{ strtoupper(($solicitud->empleado->apellido_emp ?? '') . ' ' . ($solicitud->empleado->nombre_emp ?? '')) }}<br>
      {{ strtoupper($solicitud->empleado->cargo_empleado ?? '') }}<br>SERVIDOR COMISIONADO
    </td>
    <td style="width:2%;border:none;"></td>
    <td style="width:31%;">{{ strtoupper($firmanteTh) }}<br>{{ strtoupper($firmanteThCargo) }}</td>
    <td style="width:2%;border:none;"></td>
    <td style="width:31%;">{{ strtoupper($firmanteAut) }}<br>{{ strtoupper($firmanteAutCargo) }}</td>
  </tr>
</table>
</div>

<p class="gen-line">Generado por: {{ strtoupper($generadoPor) }} &nbsp;&nbsp;|&nbsp;&nbsp; {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
