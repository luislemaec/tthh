<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// Nombres oficiales de categorías Nivel 1 - Catálogo MF actualizado al 31-jul-2024
// Uso: php artisan db:seed --class=CatalogoNivel1Seeder
class CatalogoNivel1Seeder extends Seeder
{
    public function run(): void
    {
        DB::table('adq.catalogo_nivel1')->truncate();

        $data = [
            ['nivel1' => '01', 'descripcion' => 'ALIMENTOS'],
            ['nivel1' => '02', 'descripcion' => 'BEBIDAS'],
            ['nivel1' => '03', 'descripcion' => 'VESTUARIO'],
            ['nivel1' => '04', 'descripcion' => 'PRENDAS DE PROTECCION'],
            ['nivel1' => '05', 'descripcion' => 'VIGILANCIA Y SEGURIDAD'],
            ['nivel1' => '06', 'descripcion' => 'LUBRICANTES Y ADITIVOS'],
            ['nivel1' => '07', 'descripcion' => 'MATERIAL DE OFICINA'],
            ['nivel1' => '08', 'descripcion' => 'MATERIAL DE ASEO'],
            ['nivel1' => '09', 'descripcion' => 'MATERIAL DE IMPRESION'],
            ['nivel1' => '10', 'descripcion' => 'MATERIAL DE FOTOGRAFIA'],
            ['nivel1' => '11', 'descripcion' => 'MATERIAL DE REPRODUCCION DOCUMENTAL'],
            ['nivel1' => '12', 'descripcion' => 'MATERIAL DE PUBLICACIONES'],
            ['nivel1' => '13', 'descripcion' => 'INSTRUMENTAL MEDICO MENOR'],
            ['nivel1' => '14', 'descripcion' => 'MEDICINAS'],
            ['nivel1' => '15', 'descripcion' => 'DISPOSITIVOS MEDICOS PARA LABORATORIO CLINICO Y PATOLOGIA'],
            ['nivel1' => '16', 'descripcion' => 'MATERIAL DE LABORATORIO'],
            ['nivel1' => '17', 'descripcion' => 'INSUMOS - MATERIALES - SUMINISTROS PARA LA CONSTRUCCION'],
            ['nivel1' => '18', 'descripcion' => 'INSUMOS - MATERIALES - SUMINISTROS ELECTRICOS'],
            ['nivel1' => '19', 'descripcion' => 'INSUMOS - MATERIALES - SUMINISTROS PARA PLOMERIA'],
            ['nivel1' => '20', 'descripcion' => 'INSUMOS - MATERIALES - SUMINISTROS PARA CARPINTERIA'],
            ['nivel1' => '21', 'descripcion' => 'INSUMOS - MATERIALES - SUMINISTROS PARA SENALIZACION VIAL Y PLACAS'],
            ['nivel1' => '22', 'descripcion' => 'INSUMOS - MATERIALES - SUMINISTROS PARA NAVEGACION'],
            ['nivel1' => '23', 'descripcion' => 'INSUMOS - MATERIALES - SUMINISTROS CONTRA INCENDIOS'],
            ['nivel1' => '24', 'descripcion' => 'INSUMOS - MATERIALES - SUMINISTROS DIDACTICOS'],
            ['nivel1' => '25', 'descripcion' => 'ACCESORIOS PARA MAQUINARIAS - EQUIPOS Y HERRAMIENTAS'],
            ['nivel1' => '26', 'descripcion' => 'REPUESTOS PARA VEHICULOS'],
            ['nivel1' => '27', 'descripcion' => 'REPUESTOS PARA EQUIPO MEDICO'],
            ['nivel1' => '28', 'descripcion' => 'REPUESTOS PARA EQUIPO ODONTOLOGICO'],
            ['nivel1' => '29', 'descripcion' => 'SUMINISTROS PARA ACTIVIDADES AGROPECUARIAS'],
            ['nivel1' => '30', 'descripcion' => 'SUMINISTROS PARA ACTIVIDADES DE CAZA'],
            ['nivel1' => '31', 'descripcion' => 'SUMINISTROS PARA ACTIVIDADES DE PESCA'],
            ['nivel1' => '32', 'descripcion' => 'DERIVADOS DE HIDROCARBUROS PARA LA COMERCIALIZACION INTERNA'],
            ['nivel1' => '33', 'descripcion' => 'PRODUCTOS AGRICOLAS POR EXCEDENTE O ESCASEZ DE PRODUCCION'],
            ['nivel1' => '34', 'descripcion' => 'PRODUCTOS E INSUMOS ORGANICOS'],
            ['nivel1' => '35', 'descripcion' => 'PRODUCTOS E INSUMOS QUIMICOS'],
            ['nivel1' => '36', 'descripcion' => 'MENAJE PARA HOGAR Y COCINA Y ACCESORIOS DESCARTABLES'],
            ['nivel1' => '37', 'descripcion' => 'ARTICULOS PARA SITUACIONES DE EMERGENCIA'],
            ['nivel1' => '38', 'descripcion' => 'CONDECORACIONES EN ACTOS PROTOCOLARIOS'],
            ['nivel1' => '39', 'descripcion' => 'ALIMENTOS PARA ANIMALES'],
            ['nivel1' => '40', 'descripcion' => 'INSUMOS - MATERIALES Y SUMINISTROS PARA SANIDAD Y ASEO AGROPECUARIOS'],
            ['nivel1' => '41', 'descripcion' => 'MEDICINAS Y DISPOSITIVOS MEDICOS PARA ANIMALES'],
            ['nivel1' => '42', 'descripcion' => 'MATERIAL Y SUMINISTROS PARA LA PRODUCCION DE PROGRAMAS DE RADIO Y TELEVISION'],
            ['nivel1' => '43', 'descripcion' => 'MATERIALES Y SUMINISTROS PARA EVENTOS CULTURALES Y ARTISTICOS'],
            ['nivel1' => '44', 'descripcion' => 'ACCESORIOS E INSUMOS PARA COMPENSAR DISCAPACIDADES'],
            ['nivel1' => '45', 'descripcion' => 'MATERIAL DE USO MEDICO'],
            ['nivel1' => '46', 'descripcion' => 'INSUMOS PARA PROCEDIMIENTOS MEDICOS'],
            ['nivel1' => '47', 'descripcion' => 'DISPOSITIVOS MEDICOS DE USO GENERAL'],
            ['nivel1' => '48', 'descripcion' => 'INSUMOS - MATERIALES Y SUMINISTROS PARA INVESTIGACION'],
            ['nivel1' => '49', 'descripcion' => 'DISPOSITIVOS MEDICOS PARA ODONTOLOGIA'],
            ['nivel1' => '50', 'descripcion' => 'DISPOSITIVOS MEDICOS PARA IMAGEN'],
            ['nivel1' => '51', 'descripcion' => 'PROTESIS - ENDOPROTESIS E IMPLANTES CORPORALES'],
            ['nivel1' => '52', 'descripcion' => 'MUESTRAS DE PRODUCTOS PARA FERIAS - EXPOSICIONES Y NEGOCIACIONES'],
            ['nivel1' => '53', 'descripcion' => 'PRODUCTOS HOMEOPATICOS'],
            ['nivel1' => '54', 'descripcion' => 'INSUMOS PARA MEDICINA ALTERNATIVA'],
            ['nivel1' => '55', 'descripcion' => 'MATERIA PRIMA AGROPECUARIA'],
            ['nivel1' => '56', 'descripcion' => 'MATERIA PRIMA MINEROS'],
            ['nivel1' => '57', 'descripcion' => 'PRODUCTOS EN PROCESO AGROPECUARIOS'],
            ['nivel1' => '58', 'descripcion' => 'PRODUCTOS EN PROCESO PRIMA MINEROS'],
            ['nivel1' => '59', 'descripcion' => 'PRODUCTOS TERMINADOS AGROPECUARIOS'],
            ['nivel1' => '60', 'descripcion' => 'PRODUCTOS TERMINADOS MINEROS'],
            ['nivel1' => '61', 'descripcion' => 'BIENES BIOLOGICOS PARA PRODUCCION'],
            ['nivel1' => '64', 'descripcion' => 'REPUESTOS PARA MAQUINARIAS'],
            ['nivel1' => '65', 'descripcion' => 'MATERIALES DE PELUQUERIA'],
            ['nivel1' => '66', 'descripcion' => 'UNIFORMES DEPORTIVOS'],
            ['nivel1' => '67', 'descripcion' => 'REPUESTOS PARA EQUIPOS DE LABORATORIO'],
            ['nivel1' => '68', 'descripcion' => 'INSUMOS Y MATERIALES'],
            ['nivel1' => '69', 'descripcion' => 'REPUESTOS PARA EQUIPOS INFORMATICOS'],
        ];

        DB::table('adq.catalogo_nivel1')->insert($data);
        $this->command->info('Nivel 1 cargado: ' . count($data) . ' categorías.');
    }
}
