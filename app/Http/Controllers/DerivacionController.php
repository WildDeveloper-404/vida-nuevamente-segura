<?php
namespace App\Http\Controllers;
use App\Models\{CitaMedica,Especialidad,DerivacionMedica};
use App\Support\{Access,Audit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class DerivacionController extends Controller
{
    public function create(CitaMedica $cita)
    {
        Access::cita($cita,true);return view('medico.derivacion_form',['cita'=>$cita,'especialidades'=>Especialidad::where('estado','Activo')->get()]);
    }
    public function store(Request $r,CitaMedica $cita)
    {
        Access::cita($cita,true);abort_unless(session('rol')==='Medico',403);
        $d=$r->validate(['id_especialidad_destino'=>'required|integer|exists:especialidad,id_especialidad','prioridad'=>'required|in:Normal,Preferente,Urgente','motivo_clinico'=>'required|string|max:8000']);
        DB::transaction(function() use($cita,$d) {
            $c=CitaMedica::whereKey($cita->id_cita)->lockForUpdate()->firstOrFail();abort_unless(in_array($c->estado,['Confirmada','Atendida']),422,'La cita debe estar confirmada o atendida.');
            $code=DB::selectOne("SELECT 'DER-' || lpad(nextval('seq_codigo_derivacion')::text,4,'0') AS codigo")->codigo;
            DerivacionMedica::create($d+['id_cita_origen'=>$c->id_cita,'codigo_derivacion'=>$code]);Audit::log('Consultorio','DERIVACION',$code);
        });
        $available=Especialidad::findOrFail($d['id_especialidad_destino'])->medicos()->where('medico.estado','Activo')->exists();
        return back()->with('success','Derivación registrada.'.($available?'':' No hay médicos activos en el destino; queda pendiente.'));
    }
}
