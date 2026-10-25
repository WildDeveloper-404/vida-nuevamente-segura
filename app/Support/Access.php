<?php
namespace App\Support;
use App\Models\CitaMedica;
final class Access
{
    public static function cita(CitaMedica $cita, bool $clinical = false): void
    {
        $role = session('rol');
        $own = ($role === 'Paciente' && (int)$cita->id_paciente === (int)session('id_persona'))
            || ($role === 'Medico' && (int)$cita->id_medico === (int)session('id_persona'));
        abort_unless($own || (!$clinical && $role === 'Recepcionista'), 403);
    }
    public static function perfil(int $patient): void
    {
        $own = session('rol') === 'Paciente' && $patient === (int)session('id_persona');
        $assigned = session('rol') === 'Medico' && CitaMedica::where('id_paciente',$patient)
            ->where('id_medico',session('id_persona'))->whereIn('estado',['Programada','Confirmada','Atendida'])->exists();
        abort_unless($own || $assigned, 403);
    }
}
