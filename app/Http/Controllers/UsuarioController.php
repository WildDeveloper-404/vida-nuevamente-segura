<?php
namespace App\Http\Controllers;
use App\Models\{Usuario,Persona,Rol,PersonalAdministrativo,Paciente,Medico};
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Hash};
use Illuminate\Validation\{Rule,ValidationException};
class UsuarioController extends Controller
{
    public function index(Request $r)
    {
        $r->validate(['q'=>'nullable|string|max:120','rol'=>'nullable|integer']);
        $usuarios=Usuario::with(['persona','rol'])->when($r->filled('q'),fn($q)=>$q->where('correo','ilike','%'.$r->q.'%'))->when($r->filled('rol'),fn($q)=>$q->where('id_rol',$r->rol))->orderByDesc('id_usuario')->paginate(20)->withQueryString();
        return view('admin.usuarios.index',['usuarios'=>$usuarios,'roles'=>Rol::all()]);
    }
    public function create() { return view('admin.usuarios.create',['usuario'=>null,'roles'=>Rol::all()]); }
    public function edit(Usuario $usuario) { return view('admin.usuarios.create',['usuario'=>$usuario,'roles'=>Rol::all()]); }
    public function store(Request $r) { return $this->save($r,null); }
    public function update(Request $r,Usuario $usuario) { return $this->save($r,$usuario); }
    private function save(Request $r,?Usuario $u)
    {
        $r->merge(['correo'=>mb_strtolower(trim((string)$r->correo))]);
        $d=$r->validate(['ci'=>['required','string','max:20',Rule::unique('persona','ci')->ignore($u?->id_persona,'id_persona')],'nombres'=>'required|string|max:80','apellidos'=>'required|string|max:100','telefono'=>'required|string|max:20','direccion'=>'nullable|string|max:200','correo'=>['required','email','max:120',Rule::unique('usuario','correo')->ignore($u?->id_usuario,'id_usuario')],'password'=>($u?'nullable':'required').'|string|min:10|max:128|confirmed','id_rol'=>'required|integer|exists:rol,id_rol','cargo'=>'nullable|string|max:60','estado'=>'required|in:Activo,Inactivo']);
        DB::transaction(function() use($d,$u) {
            $role=Rol::findOrFail($d['id_rol'])->nombre_rol;
            if(!$u && !in_array($role,['Administrador','Recepcionista'])) throw ValidationException::withMessages(['id_rol'=>'Registre mÃ©dicos desde MÃ©dicos y pacientes desde Pacientes para crear su ficha y cuenta.']);
            if($u && $role==='Medico' && !Medico::whereKey($u->id_persona)->exists()) throw ValidationException::withMessages(['id_rol'=>'La persona debe tener una ficha mÃ©dica.']);
            if($u && $role==='Paciente' && !Paciente::whereKey($u->id_persona)->exists()) throw ValidationException::withMessages(['id_rol'=>'La persona debe tener una ficha de paciente.']);
            if($u && $u->id_usuario==session('id_usuario') && ($role!=='Administrador' || $d['estado']!=='Activo')) throw ValidationException::withMessages(['estado'=>'No puede retirar su propio acceso administrativo.']);
            $person=collect($d)->only(['ci','nombres','apellidos','telefono','direccion'])->all();
            $p=$u?->persona ?? new Persona; $p->fill($person)->save();
            if(in_array($role,['Administrador','Recepcionista'])) PersonalAdministrativo::updateOrCreate(['id_personal'=>$p->id_persona],['cargo'=>$d['cargo'] ?? $role,'estado'=>$d['estado']]);
            $values=['id_persona'=>$p->id_persona,'correo'=>$d['correo'],'id_rol'=>$d['id_rol'],'estado'=>$d['estado']];
            if(!empty($d['password'])) $values['password_hash']=Hash::make($d['password']);
            if($u) $u->update($values); else $u=Usuario::create($values);
            Audit::log('Usuarios','GUARDAR','Usuario '.$u->id_usuario);
        });
        return redirect()->route('admin.usuarios.index')->with('success','Usuario guardado.');
    }
    public function destroy(Usuario $usuario)
    {
        abort_if($usuario->id_usuario==session('id_usuario'),422,'No puede desactivar su propia cuenta.');
        DB::transaction(function() use($usuario) {$usuario->update(['estado'=>'Inactivo']);Audit::log('Usuarios','BAJA_LOGICA','Usuario '.$usuario->id_usuario);});
        return back()->with('success','Usuario desactivado.');
    }
}
