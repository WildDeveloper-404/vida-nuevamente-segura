<?php
namespace App\Http\Controllers;
use App\Models\{HorarioAtencion,Medico,Consultorio,BloqueoAgenda,CitaMedica};
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class HorarioController extends Controller
{
    public function index() { return view('admin.horarios.index',['horarios'=>HorarioAtencion::with('medico.persona')->orderBy('id_medico')->get(),'medicos'=>Medico::with('persona')->where('estado','Activo')->get(),'consultorios'=>Consultorio::where('estado','Activo')->get(),'bloqueos'=>BloqueoAgenda::with('medico.persona')->orderByDesc('fecha_bloqueo')->limit(50)->get()]); }
    public function store(Request $r)
    {
        $d=$r->validate(['id_medico'=>'required|integer|exists:medico,id_medico','id_consultorio'=>'nullable|integer|exists:consultorio,id_consultorio','dia_semana'=>'required|in:Lunes,Martes,Miercoles,Jueves,Viernes,Sabado,Domingo','hora_inicio'=>'required|date_format:H:i','hora_fin'=>'required|date_format:H:i|after:hora_inicio','duracion_minutos'=>'required|in:15,20,30']);
        DB::transaction(function() use($d) {Medico::whereKey($d['id_medico'])->lockForUpdate()->firstOrFail();HorarioAtencion::create($d);Audit::log('Horarios','ALTA','Horario del médico '.$d['id_medico']);});return back()->with('success','Horario creado.');
    }
    public function destroy(HorarioAtencion $horario)
    {
        DB::transaction(function() use($horario) {
            Medico::whereKey($horario->id_medico)->lockForUpdate()->firstOrFail();
            $days=['Lunes'=>1,'Martes'=>2,'Miercoles'=>3,'Jueves'=>4,'Viernes'=>5,'Sabado'=>6,'Domingo'=>7];
            if(CitaMedica::where('id_medico',$horario->id_medico)->whereDate('fecha_cita','>=',today())->whereIn('estado',['Programada','Confirmada'])->whereRaw('extract(isodow from fecha_cita) = ?',[$days[$horario->dia_semana]])->where('hora_inicio','<',$horario->hora_fin)->where('hora_fin','>',$horario->hora_inicio)->exists()) throw ValidationException::withMessages(['horario'=>'Gestione primero las citas activas de este turno.']);
            $horario->update(['estado'=>'Inactivo']);Audit::log('Horarios','BAJA_LOGICA','Horario '.$horario->id_horario);
        });return back()->with('success','Horario desactivado.');
    }
    public function bloqueo(Request $r)
    {
        $d=$r->validate(['id_medico'=>'required|integer|exists:medico,id_medico','fecha_bloqueo'=>'required|date|after_or_equal:today','hora_inicio'=>'required|date_format:H:i','hora_fin'=>'required|date_format:H:i|after:hora_inicio','tipo_bloqueo'=>'required|in:Feriado,Permiso_Medico','motivo'=>'required|string|max:255']);
        DB::transaction(function() use($d) {
            Medico::whereKey($d['id_medico'])->lockForUpdate()->firstOrFail();
            if(CitaMedica::where('id_medico',$d['id_medico'])->where('fecha_cita',$d['fecha_bloqueo'])->whereIn('estado',['Programada','Confirmada'])->where('hora_inicio','<',$d['hora_fin'])->where('hora_fin','>',$d['hora_inicio'])->exists()) throw ValidationException::withMessages(['bloqueo'=>'Hay citas reservadas. Utilice Contingencias para trasladarlas.']);
            BloqueoAgenda::create($d);Audit::log('Horarios','BLOQUEO',$d['motivo']);
        });return back()->with('success','Bloqueo registrado.');
    }
    public function consultorio(Request $r) { $d=$r->validate(['numero'=>'required|string|max:20|unique:consultorio,numero','bloque'=>'required|string|max:50','piso'=>'required|integer|min:0|max:100']);DB::transaction(function() use($d) {Consultorio::create($d);Audit::log('Horarios','CONSULTORIO',$d['numero']);});return back()->with('success','Consultorio creado.'); }
}
