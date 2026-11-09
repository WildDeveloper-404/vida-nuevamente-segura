<?php
namespace App\Http\Controllers;
use App\Models\{Paciente,PerfilClinico,RecetaMedica};
use App\Support\{Access,Audit};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class PerfilClinicoController extends Controller
{
    public function show(int $paciente)
    {
        Access::perfil($paciente);
        $recetas=RecetaMedica::with(['detalles','cita.medico.persona','cita.especialidad'])
            ->whereHas('cita',function($q) use($paciente) {
                $q->where('id_paciente',$paciente);
                if(session('rol')==='Medico') $q->where('id_medico',session('id_persona'));
            })->orderByDesc('fecha_emision')->orderByDesc('id_receta')->paginate(10, ['*'], 'recetas');
        return view('pacientes.perfil_clinico',['paciente'=>Paciente::with('persona','perfilClinico')->findOrFail($paciente),'recetas'=>$recetas]);
    }
    public function update(Request $r,int $paciente)
    {
        Access::perfil($paciente);
        $clinical = [
            'tipo_sangre' => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'alergias' => 'required|string|max:4000',
            'enfermedades_base' => 'required|string|max:4000',
            'medicamentos_habituales' => 'required|string|max:4000',
            'antecedentes_relevantes' => 'nullable|string|max:8000',
        ];
        $emergency = [
            'contacto_emergencia_nombre' => 'nullable|string|max:150',
            'contacto_emergencia_parentesco' => 'nullable|string|max:60',
            'contacto_emergencia_telefono' => 'nullable|string|max:20',
        ];
        $self = session('rol') === 'Paciente';
        if ($self) {
            abort_if(count(array_intersect(array_keys($clinical), array_keys($r->all()))) > 0, 403);
        }
        $d = $r->validate($emergency + ($self
            ? ['telefono' => 'sometimes|required|string|max:20', 'direccion' => 'nullable|string|max:200']
            : $clinical));

        DB::transaction(function () use ($d, $paciente, $self) {
            $patient = Paciente::whereKey($paciente)->lockForUpdate()->firstOrFail();
            if ($self) {
                $patient->persona->update(array_intersect_key($d, array_flip(['telefono', 'direccion'])));
            }
            PerfilClinico::updateOrCreate(
                ['id_paciente' => $paciente],
                array_diff_key($d, array_flip(['telefono', 'direccion'])) + ['ultima_actualizacion' => now()]
            );
            Audit::log('PerfilClinico', $self ? 'CONTACTO' : 'EDICION', 'Perfil del paciente '.$paciente);
        });
        return back()->with('success', $self ? 'Datos de contacto actualizados.' : 'Antecedentes actualizados.');
    }
}
