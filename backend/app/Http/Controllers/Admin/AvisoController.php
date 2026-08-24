<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aviso;
use App\Models\Configuracion;
use Illuminate\Http\Request;

class AvisoController extends Controller
{
    private const ROLES_ADMIN = ['ADMINISTRADOR', 'TALENTO HUMANO'];

    public function index(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $avisos    = Aviso::orderBy('orden')->orderBy('id')->get();
        $direccion = Configuracion::find('AVISOS_DIRECCION')?->valor ?? 'horizontal';
        return response()->json(['avisos' => $avisos, 'direccion' => $direccion]);
    }

    public function activos()
    {
        $avisos    = Aviso::where('activo', true)->orderBy('orden')->orderBy('id')->get(['id', 'texto']);
        $direccion = Configuracion::find('AVISOS_DIRECCION')?->valor ?? 'horizontal';
        return response()->json(['avisos' => $avisos, 'direccion' => $direccion]);
    }

    public function store(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $request->validate(['texto' => 'required|string|max:500']);
        $aviso = Aviso::create([
            'texto'  => $request->texto,
            'activo' => $request->boolean('activo', true),
            'orden'  => $request->input('orden', 0),
        ]);
        return response()->json($aviso, 201);
    }

    public function update(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $aviso = Aviso::findOrFail($id);
        $request->validate(['texto' => 'required|string|max:500']);
        $aviso->update([
            'texto'  => $request->texto,
            'activo' => $request->boolean('activo', $aviso->activo),
            'orden'  => $request->input('orden', $aviso->orden),
        ]);
        return response()->json($aviso);
    }

    public function destroy(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        Aviso::findOrFail($id)->delete();
        return response()->json(['ok' => true]);
    }

    public function setDireccion(Request $request)
    {
        $this->requireRole($request, self::ROLES_ADMIN);
        $request->validate(['direccion' => 'required|in:horizontal,vertical']);
        $config = Configuracion::find('AVISOS_DIRECCION');
        if ($config) {
            $config->update(['valor' => $request->direccion]);
        } else {
            Configuracion::create(['concepto' => 'AVISOS_DIRECCION', 'valor' => $request->direccion]);
        }
        return response()->json(['ok' => true]);
    }
}
