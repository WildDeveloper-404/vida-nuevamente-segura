<?php
namespace App\Http\Controllers;
use App\Models\{Paciente,PerfilClinico};
use App\Support\{Access,Audit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class PerfilClinicoController extends Controller
{
    public function show(int $paciente)
    {
        Access::perfil($paciente);
        return view('pacientes.perfil_clinico',['paciente'=>Paciente::with('persona','perfilClinico')->findOrFail($paciente)]);
    }
    public function update(Request $r,int $paciente)
    {
        Access::perfil($paciente);
        $d=$r->validate(['tipo_sangre'=>'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-','alergias'=>'required|string|max:4000','enfermedades_base'=>'required|string|max:4000','medicamentos_habituales'=>'required|string|max:4000','antecedentes_relevantes'=>'nullable|string|max:8000','contacto_emergencia_nombre'=>'nullable|string|max:150','contacto_emergencia_parentesco'=>'nullable|string|max:60','contacto_emergencia_telefono'=>'nullable|string|max:20']);
        DB::transaction(function() use($d,$paciente) {PerfilClinico::updateOrCreate(['id_paciente'=>$paciente],$d+['ultima_actualizacion'=>now()]);Audit::log('PerfilClinico','EDICION','Perfil del paciente '.$paciente);});
        return back()->with('success','Antecedentes actualizados.');
    }
}
