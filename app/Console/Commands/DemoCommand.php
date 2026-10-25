<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB,Hash};
use App\Models\{Persona,PersonalAdministrativo,Usuario,Rol,Medico,Paciente,PerfilClinico,Especialidad,HorarioAtencion,Consultorio};
use App\Services\CitaService;
class DemoCommand extends Command
{
    protected $signature='clinica:demo';
    protected $description='Crear cuatro cuentas de demostración y agenda de ejemplo, solo en entorno local vacío';
    public function handle(): int
    {
        if(!app()->environment('local') || Usuario::exists()) {$this->error('Solo disponible en APP_ENV=local y sin usuarios existentes.');return 1;}
        DB::transaction(function() {
            $people=[];
            foreach(['Administrador'=>['admin','Administración','Demo'],'Recepcionista'=>['recepcion','Recepción','Demo'],'Medico'=>['medico','Gabriela','Mendoza (demo)'],'Paciente'=>['paciente','Daniela','Rojas (demo)']] as $role=>$v) {
                $p=Persona::create(['ci'=>'DEMO-'.strtoupper($v[0]),'nombres'=>$v[1],'apellidos'=>$v[2],'telefono'=>'0000000000','direccion'=>'Datos ficticios de demostración']);
                Usuario::create(['id_persona'=>$p->id_persona,'correo'=>$v[0].'@clinica.test','password_hash'=>Hash::make('DemoVida2026!'),'id_rol'=>Rol::where('nombre_rol',$role)->value('id_rol')]);
                if(in_array($role,['Administrador','Recepcionista'])) PersonalAdministrativo::create(['id_personal'=>$p->id_persona,'cargo'=>$role]);
                $people[$role]=$p;
            }
            $m=Medico::create(['id_medico'=>$people['Medico']->id_persona,'codigo_medico'=>'MED-DEMO','nro_matricula'=>'DEMO-0001']);
            $e=Especialidad::where('nombre','Medicina General')->firstOrFail();$m->especialidades()->attach($e->id_especialidad);
            $room=Consultorio::create(['numero'=>'101','bloque'=>'Central','piso'=>1]);
            foreach(['Lunes','Martes','Miercoles','Jueves','Viernes','Sabado'] as $day) HorarioAtencion::create(['id_medico'=>$m->id_medico,'id_consultorio'=>$room->id_consultorio,'dia_semana'=>$day,'hora_inicio'=>'08:00','hora_fin'=>'12:00','duracion_minutos'=>30]);
            $p=Paciente::create(['id_paciente'=>$people['Paciente']->id_persona,'codigo_paciente'=>'PAC-DEMO','sexo'=>'F','fecha_nacimiento'=>'1993-06-15']);
            PerfilClinico::create(['id_paciente'=>$p->id_paciente,'alergias'=>'Ninguna conocida']);
            $day=today()->addDay();if($day->isSunday())$day->addDay();
            app(CitaService::class)->book(['id_paciente'=>$p->id_paciente,'id_medico'=>$m->id_medico,'id_especialidad'=>$e->id_especialidad,'fecha_cita'=>$day->toDateString(),'hora_inicio'=>'09:00','motivo_consulta'=>'Consulta de demostración'],null);
        });
        $this->info('Cuentas locales: admin@clinica.test, recepcion@clinica.test, medico@clinica.test, paciente@clinica.test');
        $this->info('Contraseña de demostración: DemoVida2026!');return 0;
    }
}
