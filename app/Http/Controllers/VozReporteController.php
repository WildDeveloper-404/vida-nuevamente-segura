<?php
namespace App\Http\Controllers;
use App\Models\ConsultaAsistenteIA;
use App\Services\{VozService,ReporteService,ExportService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Validator};
use Illuminate\Validation\Rule;
class VozReporteController extends Controller
{
    public function index() { return view('admin.reportes.voz'); }
    public function procesarVoz(Request $r,VozService $voice,ReporteService $reports)
    {
        $d=$r->validate(['texto'=>'required|string|max:500']);$plan=$voice->plan($d['texto']);$doctor=session('rol')==='Medico'?(int)session('id_persona'):null;
        if(($plan['tipo']??null)==='horarios') {
            Validator::make($plan,['especialidad'=>'nullable|string|max:100'])->validate();
            $q=DB::table('horario_atencion as h')->join('medico as m','m.id_medico','=','h.id_medico')->join('persona as p','p.id_persona','=','m.id_medico')->join('medico_especialidad as me','me.id_medico','=','m.id_medico')->join('especialidad as e','e.id_especialidad','=','me.id_especialidad')->where('h.estado','Activo')->where('m.estado','Activo')->where('e.estado','Activo')->selectRaw("p.nombres || ' ' || p.apellidos AS medico,e.nombre AS especialidad,h.dia_semana,h.hora_inicio,h.hora_fin");
            if($doctor) $q->where('m.id_medico',$doctor);
            if(!empty($plan['especialidad'])) $q->where('e.nombre','ilike','%'.$plan['especialidad'].'%');
            $report=['columns'=>['medico'=>'Médico','especialidad'=>'Especialidad','dia_semana'=>'Día','hora_inicio'=>'Inicio','hora_fin'=>'Fin'],'rows'=>$q->limit(1000)->get()->all(),'totals'=>[],'sql'=>$q->toSql()];
        } else {
            $validated=Validator::make($plan,['campos'=>'required|array|min:1|max:20','campos.*'=>['required',Rule::in(array_keys(ReporteService::FIELDS))],'filtros'=>'nullable|array|max:10','filtros.*.campo'=>['required',Rule::in(array_keys(ReporteService::FIELDS))],'filtros.*.operador'=>['required',Rule::in(['=','!=','>','>=','<','<=','ilike'])],'filtros.*.valor'=>'required|string|max:200','orden'=>['required',Rule::in(array_keys(ReporteService::FIELDS))]])->validate();
            $report=$reports->qbe($validated,$doctor);
        }
        $summary='Se encontraron '.count($report['rows']).' registros'.($doctor?' correspondientes a su actividad médica.':'.');
        ConsultaAsistenteIA::create(['id_usuario'=>session('id_usuario'),'canal'=>'Comando_Voz_IA','texto_entrada'=>$d['texto'],'sql_generado'=>$report['sql'],'respuesta_emitida'=>$summary]);
        $r->session()->put('voice_report',array_intersect_key($report,array_flip(['columns','rows','totals'])));
        return response()->json($report+['resumen'=>$summary]);
    }
    public function export(Request $r,ExportService $export)
    {
        $d=$r->validate(['formato'=>'required|in:pdf,xlsx,csv']);abort_unless(session()->has('voice_report'),422,'Genere primero un reporte.');return $export->download(session('voice_report'),$d['formato'],'Reporte por voz');
    }
}
