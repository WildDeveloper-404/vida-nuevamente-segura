<?php
namespace App\Http\Controllers;
use App\Models\{CitaMedica,RecetaMedica};
use App\Support\Access;
use App\Services\RecetaService;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
class RecetaController extends Controller
{
    public function create(CitaMedica $cita)
    {
        Access::cita($cita,true);abort_unless(session('rol')==='Medico',403);
        return view('medico.receta_form',compact('cita'));
    }
    public function store(Request $r,CitaMedica $cita, RecetaService $service)
    {
        Access::cita($cita,true);abort_unless(session('rol')==='Medico',403);
        $d=$r->validate(['diagnostico'=>'required|string|max:8000','cie10'=>['required','regex:/^[A-Z][0-9]{2}(?:\.[0-9A-Z]{1,4})?$/i'],'items'=>'required|array|min:1|max:30','items.*.tipo_item'=>'required|in:Medicamento,Examen_Laboratorio','items.*.medicamento_o_examen'=>'required|string|max:150','items.*.principio_activo'=>'required_if:items.*.tipo_item,Medicamento|nullable|string|max:150','items.*.dosis'=>'nullable|string|max:80','items.*.frecuencia'=>'nullable|string|max:80','items.*.duracion'=>'nullable|string|max:60']);
        $service->emitir($cita, $d);
        return back()->with('success','Receta emitida. Disponible en el perfil clínico del paciente.');
    }
    public function pdf(RecetaMedica $receta, RecetaService $service)
    {
        Access::cita($receta->cita,true);
        abort_if($receta->huella_integridad !== null && !$service->esIntegra($receta), 422, 'No se pudo verificar la receta.');
        $receta->load(['detalles','cita.paciente.persona','cita.medico.persona','cita.especialidad']);
        return Pdf::loadView('recetas.pdf',compact('receta'))->download('receta-'.$receta->id_receta.'.pdf');
    }
    public function verificar(string $codigo, RecetaService $service)
    {
        $receta=RecetaMedica::where('codigo_qr',$codigo)->firstOrFail();
        $valid = $service->esIntegra($receta);
        abort_if($receta->huella_integridad !== null && !$valid, 404);
        return view('recetas.verificar',['fecha'=>$receta->fecha_emision,'numero'=>$receta->id_receta,'integra'=>$valid]);
    }
}
