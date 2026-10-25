<?php
namespace App\Http\Controllers;
use App\Models\{CitaMedica,RecetaMedica};
use App\Support\{Access,Audit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\{QrCode,Writer\PngWriter};
class RecetaController extends Controller
{
    public function create(CitaMedica $cita)
    {
        Access::cita($cita,true);abort_unless(session('rol')==='Medico',403);
        return view('medico.receta_form',compact('cita'));
    }
    public function store(Request $r,CitaMedica $cita)
    {
        Access::cita($cita,true);abort_unless(session('rol')==='Medico',403);
        $d=$r->validate(['diagnostico'=>'required|string|max:8000','items'=>'required|array|min:1|max:30','items.*.tipo_item'=>'required|in:Medicamento,Examen_Laboratorio','items.*.medicamento_o_examen'=>'required|string|max:150','items.*.dosis'=>'nullable|string|max:80','items.*.frecuencia'=>'nullable|string|max:80','items.*.duracion'=>'nullable|string|max:60']);
        DB::transaction(function() use($cita,$d) {
            $c=CitaMedica::whereKey($cita->id_cita)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($c->estado,['Confirmada','Atendida']),422,'La cita debe estar confirmada o atendida.');
            $rx=RecetaMedica::create(['id_cita'=>$c->id_cita,'diagnostico'=>$d['diagnostico'],'codigo_qr'=>Str::random(48)]);
            $rx->detalles()->createMany($d['items']);
            Audit::log('Consultorio','RECETA','Receta '.$rx->id_receta.' para '.$c->codigo_cita);
        });return back()->with('success','Receta emitida. Disponible en el historial.');
    }
    public function pdf(RecetaMedica $receta)
    {
        Access::cita($receta->cita,true);
        $receta->load(['detalles','cita.paciente.persona','cita.medico.persona','cita.especialidad']);
        $qr=(new PngWriter)->write(QrCode::create(route('recetas.verificar',$receta->codigo_qr))->setSize(180))->getDataUri();
        return Pdf::loadView('recetas.pdf',compact('receta','qr'))->download('receta-'.$receta->id_receta.'.pdf');
    }
    public function verificar(string $codigo)
    {
        $receta=RecetaMedica::where('codigo_qr',$codigo)->firstOrFail();
        return view('recetas.verificar',['fecha'=>$receta->fecha_emision,'numero'=>$receta->id_receta]);
    }
}
