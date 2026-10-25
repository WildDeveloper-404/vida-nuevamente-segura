<?php
namespace App\Http\Controllers;
use App\Models\{Paciente,Persona,Usuario};
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Hash};
use Illuminate\Validation\Rule;
class PacienteController extends Controller
{
    public function index(Request $r)
    {
        $r->validate(['q'=>'nullable|string|max:100']);
        $pacientes=Paciente::with('persona')->when($r->filled('q'),fn($q)=>$q->whereHas('persona',fn($p)=>$p->where('ci','ilike','%'.$r->q.'%')->orWhere('apellidos','ilike','%'.$r->q.'%')))->orderByDesc('id_paciente')->paginate(20)->withQueryString();
        return view('pacientes.index',compact('pacientes'));
    }
    public function create() { return view('pacientes.create',['paciente'=>null]); }
    public function store(Request $r)
    {
        $public=$r->routeIs('registro.store');
        $d=$r->validate(['ci'=>'required|string|max:20|unique:persona,ci','nombres'=>'required|string|max:80','apellidos'=>'required|string|max:100','telefono'=>'required|string|max:20','direccion'=>'nullable|string|max:200','sexo'=>'required|in:M,F,O','fecha_nacimiento'=>'required|date|before_or_equal:today','correo'=>($public?'required':'nullable').'|email|max:120|unique:usuario,correo','password'=>($public?'required':'nullable').'|string|min:10|max:128|confirmed|required_with:correo']);
        DB::transaction(fn()=>DB::select('CALL sp_registrar_paciente_completo(?::varchar, ?::varchar, ?::varchar, ?::char, ?::date, ?::varchar, ?::varchar, ?::varchar, ?::varchar, ?::varchar, NULL, NULL)',[$d['ci'],$d['nombres'],$d['apellidos'],$d['sexo'],$d['fecha_nacimiento'],$d['telefono'],isset($d['correo'])?mb_strtolower($d['correo']):null,$d['direccion']??null,!empty($d['password'])?Hash::make($d['password']):null,null]));
        return redirect()->route($public?'login':'recepcion.pacientes.index')->with('success','Paciente registrado correctamente.');
    }
    public function edit(Paciente $paciente) { return view('pacientes.create',compact('paciente')); }
    public function update(Request $r,Paciente $paciente)
    {
        $d=$r->validate(['ci'=>['required','string','max:20',Rule::unique('persona','ci')->ignore($paciente->id_paciente,'id_persona')],'nombres'=>'required|string|max:80','apellidos'=>'required|string|max:100','telefono'=>'required|string|max:20','direccion'=>'nullable|string|max:200','sexo'=>'required|in:M,F,O','fecha_nacimiento'=>'required|date|before_or_equal:today']);
        DB::transaction(function() use($paciente,$d) {$paciente->persona->update(collect($d)->only(['ci','nombres','apellidos','telefono','direccion'])->all());$paciente->update(collect($d)->only(['sexo','fecha_nacimiento'])->all()); Audit::log('Pacientes','EDICION','Paciente '.$paciente->codigo_paciente);});
        return redirect()->route('recepcion.pacientes.index')->with('success','Datos actualizados.');
    }
    public function destroy(Paciente $paciente)
    {
        DB::transaction(function() use($paciente) {$paciente->update(['estado'=>'Inactivo']);Usuario::where('id_persona',$paciente->id_paciente)->update(['estado'=>'Inactivo']);Audit::log('Pacientes','BAJA_LOGICA',$paciente->codigo_paciente);});
        return back()->with('success','Paciente desactivado. El historial se conserva.');
    }
    public function buscarPorCi(Request $r)
    {
        $r->validate(['q'=>'required|string|min:2|max:100']);
        return response()->json(Persona::join('paciente','id_paciente','=','id_persona')->where('paciente.estado','Activo')->where(fn($q)=>$q->where('ci','ilike','%'.$r->q.'%')->orWhere('apellidos','ilike','%'.$r->q.'%'))->limit(15)->get(['id_paciente','ci','nombres','apellidos']));
    }
}
