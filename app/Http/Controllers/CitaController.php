<?php
namespace App\Http\Controllers;
use App\Models\{CitaMedica,Especialidad,Medico,DerivacionMedica};
use App\Services\CitaService;
use App\Support\Access;
use Illuminate\Http\Request;
class CitaController extends Controller
{
    public function create(Request $r)
    {
        $derivaciones = collect();
        $derivacion = null;
        if($r->filled('derivacion')) {
            $derivacion=DerivacionMedica::with('citaOrigen.paciente.persona')->where('estado','Pendiente de programar')
                ->findOrFail($r->integer('derivacion'));
            Access::cita($derivacion->citaOrigen);
        }
        $patient = session('rol') === 'Paciente' ? session('id_persona') : $derivacion?->citaOrigen->id_paciente;
        if ($patient) {
            $derivaciones = $this->pendingReferrals((int) $patient)->get();
            $derivacion ??= $derivaciones->first();
        }
        return view('citas.create',['especialidades'=>Especialidad::where('estado','Activo')->orderBy('nombre')->get(),'derivacion'=>$derivacion,'derivaciones'=>$derivaciones]);
    }

    public function derivaciones(Request $r)
    {
        $d = $r->validate(['id_paciente'=>'required|integer|exists:paciente,id_paciente']);
        return response()->json($this->pendingReferrals((int) $d['id_paciente'])->get());
    }

    private function pendingReferrals(int $patient)
    {
        return DerivacionMedica::query()->where('estado','Pendiente de programar')
            ->whereHas('citaOrigen', fn ($q) => $q->where('id_paciente',$patient))
            ->whereIn('id_especialidad_destino', Especialidad::where('estado','Activo')->select('id_especialidad'))
            ->orderByRaw("CASE prioridad WHEN 'Urgente' THEN 0 WHEN 'Prioritaria' THEN 1 ELSE 2 END")
            ->orderByDesc('id_derivacion')->limit(30)
            ->select('id_derivacion','id_especialidad_destino','codigo_derivacion','prioridad');
    }
    public function medicos(Request $r)
    {
        $d=$r->validate(['id_especialidad'=>'required|integer|exists:especialidad,id_especialidad']);
        return response()->json(Medico::with('persona')->where('estado','Activo')->whereHas('especialidades',fn($q)=>$q->where('especialidad.id_especialidad',$d['id_especialidad'])->where('estado','Activo'))->get()->map(fn($m)=>['id_medico'=>$m->id_medico,'nombre'=>$m->persona->nombre_completo]));
    }
    public function getBloquesDisponibles(Request $r,CitaService $service)
    {
        $d=$r->validate(['id_medico'=>'required|integer|exists:medico,id_medico','fecha'=>'required|date|after_or_equal:today']);
        return response()->json($service->slots($d['id_medico'],$d['fecha']));
    }
    public function store(Request $r,CitaService $service)
    {
        if(session('rol')==='Paciente') $r->merge(['id_paciente'=>session('id_persona')]);
        $d=$r->validate(['id_paciente'=>'required|integer|exists:paciente,id_paciente','id_medico'=>'required|integer|exists:medico,id_medico','id_especialidad'=>'required|integer|exists:especialidad,id_especialidad','fecha_cita'=>'required|date|after_or_equal:today','hora_inicio'=>'required|date_format:H:i','motivo_consulta'=>'required|string|max:255','id_derivacion'=>'nullable|integer|exists:derivacion_medica,id_derivacion']);
        $c=$service->book($d,session('id_usuario'));
        return redirect()->route(session('rol')==='Paciente'?'paciente.citas':'recepcion.citas',['id_paciente'=>$c->id_paciente])->with('success','Reserva confirmada: '.$c->codigo_cita);
    }
    public function cancelar(Request $r,CitaMedica $cita,CitaService $service)
    {
        Access::cita($cita); $d=$r->validate(['motivo'=>'required|string|max:255']);
        $service->cancel($cita,$d['motivo']);
        $paid=$cita->pago()->whereHas('devolucion')->exists();
        return back()->with('success','Cita cancelada. Horario liberado.'.($paid?' Se registró una solicitud de devolución.':''));
    }
    public function reprogramar(Request $r,CitaMedica $cita,CitaService $service)
    {
        Access::cita($cita); $d=$r->validate(['fecha_cita'=>'required|date|after_or_equal:today','hora_inicio'=>'required|date_format:H:i','motivo'=>'required|string|max:230']);
        $c=$service->reschedule($cita,$d,session('id_usuario')); return back()->with('success','Nueva reserva: '.$c->codigo_cita);
    }

    public function responderContingencia(Request $r, CitaMedica $cita, int $aviso, CitaService $service)
    {
        Access::cita($cita);
        $d = $r->validate(['respuesta'=>'required|in:Confirma,Solicita devolución']);
        $service->answerContingency($cita, $aviso, $d['respuesta']);
        return back()->with('success', $d['respuesta'] === 'Confirma'
            ? 'Aceptó la nueva fecha.' : 'Se registró su solicitud de devolución.');
    }
}
