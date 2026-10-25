<?php
namespace App\Http\Controllers;
use App\Models\{Medico,Especialidad};
use App\Services\{ReporteService,ExportService};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class ReporteController extends Controller
{
    public function index(Request $r,ReporteService $service,ExportService $export)
    {
        $r->merge(['tipo'=>$r->input('tipo','fecha'),'desde'=>$r->input('desde',today()->toDateString()),'hasta'=>$r->input('hasta',today()->toDateString())]);
        $d=$r->validate(['tipo'=>['required',Rule::in(array_keys(ReporteService::TYPES))],'desde'=>'required|date','hasta'=>'required|date|after_or_equal:desde','id_medico'=>'nullable|integer|exists:medico,id_medico','id_especialidad'=>'nullable|integer|exists:especialidad,id_especialidad','estado'=>'nullable|in:Programada,Confirmada,Atendida,Cancelada,Inasistencia','formato'=>'nullable|in:pdf,csv,xlsx']);
        $report=$service->static($d);
        if($r->filled('formato')) return $export->download($report,$d['formato'],ReporteService::TYPES[$d['tipo']]);
        return view('admin.reportes.estaticos',$report+['tipos'=>ReporteService::TYPES,'medicos'=>Medico::with('persona')->get(),'especialidades'=>Especialidad::all()]);
    }
}
