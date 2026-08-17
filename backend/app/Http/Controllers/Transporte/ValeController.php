<?php
namespace App\Http\Controllers\Transporte;

use App\Http\Controllers\Controller;
use App\Models\Transporte\ValeCombustible;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class ValeController extends Controller
{
    private const ROLES_VALES = ['ADMINISTRADOR', 'TRANSPORTE', 'CONDUCTOR'];
    private const ROLES_TRANSPORTE = ['ADMINISTRADOR', 'TRANSPORTE'];

    private function emp(Request $request)
    {
        return $request->user();
    }

    public function index(Request $request)
    {
        $this->requireRole($request, self::ROLES_VALES);
        $emp = $this->emp($request);

        $query = ValeCombustible::with(['conductor', 'vehiculo'])
            ->orderBy('created_at', 'desc');

        $esTransporte = DB::table('dbo.admin_usuario_rol as ur')
            ->join('dbo.admin_rol as r', 'ur.id_rol', '=', 'r.id')
            ->where('ur.id_emp', $emp->id_emp)
            ->where('r.descripcion', 'TRANSPORTE')
            ->exists();

        if (!$esTransporte) {
            $query->where('id_emp_conductor', $emp->id_emp);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $this->requireRole($request, self::ROLES_VALES);
        $request->validate([
            'gasolinera'  => 'required|string|max:200',
            'vehiculo_id' => 'required|exists:pgsql.dbo.trans_vehiculo,id',
            'kilometraje' => 'required|integer|min:0',
            'fecha'            => 'required|date',
            'fecha_comprobante'=> 'nullable|date',
            'glns_extra'  => 'nullable|numeric|min:0',
            'pu_extra'    => 'nullable|numeric|min:0',
            'glns_super'  => 'nullable|numeric|min:0',
            'pu_super'    => 'nullable|numeric|min:0',
            'glns_diesel' => 'nullable|numeric|min:0',
            'pu_diesel'   => 'nullable|numeric|min:0',
        ]);

        // Número correlativo: MAX(numero) + 1; si no hay registros usa VALE_COMBUSTIBLE_INICIO
        $inicio  = (int) DB::table('dbo.d2_configuracion')
            ->whereRaw("LOWER(concepto) = 'vale_combustible_inicio'")
            ->value('valor') ?? 0;
        $maxNum  = ValeCombustible::max('numero') ?? $inicio;
        $numero  = $maxNum + 1;

        $vale = ValeCombustible::create([
            'numero'           => $numero,
            'gasolinera'       => $request->gasolinera,
            'id_emp_conductor' => $this->emp($request)->id_emp,
            'vehiculo_id'      => $request->vehiculo_id,
            'kilometraje'       => $request->kilometraje,
            'fecha'             => $request->fecha,
            'fecha_comprobante' => $request->fecha_comprobante ?? null,
            'glns_extra'       => $request->glns_extra,
            'pu_extra'         => $request->pu_extra,
            'valor_extra'      => $request->glns_extra && $request->pu_extra
                                    ? round($request->glns_extra * $request->pu_extra, 2) : null,
            'glns_super'       => $request->glns_super,
            'pu_super'         => $request->pu_super,
            'valor_super'      => $request->glns_super && $request->pu_super
                                    ? round($request->glns_super * $request->pu_super, 2) : null,
            'glns_diesel'      => $request->glns_diesel,
            'pu_diesel'        => $request->pu_diesel,
            'valor_diesel'     => $request->glns_diesel && $request->pu_diesel
                                    ? round($request->glns_diesel * $request->pu_diesel, 2) : null,
            'estado'           => 'EMITIDO',
        ]);

        return response()->json($vale->load(['conductor', 'vehiculo']), 201);
    }

    public function anular(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_TRANSPORTE);
        $vale = ValeCombustible::findOrFail($id);

        if ($vale->estado !== 'EMITIDO') {
            return response()->json(['message' => 'Solo se pueden anular vales en estado EMITIDO.'], 422);
        }

        $vale->update(['estado' => 'ANULADO']);

        return response()->json($vale);
    }

    public function pdf(Request $request, $id)
    {
        $this->requireRole($request, self::ROLES_VALES);
        $vale = ValeCombustible::with(['conductor', 'vehiculo'])->findOrFail($id);
        $logo = $this->logoBase64();

        $meses = ['', 'enero','febrero','marzo','abril','mayo','junio',
                  'julio','agosto','septiembre','octubre','noviembre','diciembre'];

        $fechaElab = $vale->fecha ? \Carbon\Carbon::parse($vale->fecha) : now();
        $dia  = $fechaElab->day;
        $mes  = $meses[$fechaElab->month];
        $anio = $fechaElab->year;

        $fechaComp = $vale->fecha_comprobante
            ? \Carbon\Carbon::parse($vale->fecha_comprobante)
            : null;
        $diaComp  = $fechaComp?->day;
        $mesComp  = $fechaComp ? $meses[$fechaComp->month] : null;
        $anioComp = $fechaComp?->year;

        $pdf = Pdf::loadView('reportes.trans_vale_combustible',
            compact('vale', 'logo', 'dia', 'mes', 'anio', 'diaComp', 'mesComp', 'anioComp'))
            ->setPaper('a4', 'portrait'); // media carta

        return $pdf->stream('vale_combustible_' . str_pad($vale->numero, 4, '0', STR_PAD_LEFT) . '.pdf');
    }

    private function logoBase64(): ?string
    {
        $path = public_path('logo.png');
        return file_exists($path) ? 'data:image/png;base64,' . base64_encode(file_get_contents($path)) : null;
    }
}
