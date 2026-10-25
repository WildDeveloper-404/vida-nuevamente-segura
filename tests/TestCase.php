<?php
namespace Tests;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\{DB,Artisan,Hash};
use App\Models\{Persona,Usuario,Rol,Paciente,Medico,Especialidad,HorarioAtencion,PerfilClinico};
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if(!str_ends_with(config('database.connections.pgsql.database'),'_test')) throw new \RuntimeException('Las pruebas requieren una base separada con sufijo _test.');
        if(!DB::selectOne("SELECT to_regclass('public.persona') AS tabla")->tabla) Artisan::call('clinica:instalar',['--datos'=>true]);
        DB::beginTransaction();
    }
    protected function tearDown(): void
    {
        while(DB::transactionLevel()>0) DB::rollBack();
        parent::tearDown();
    }
    protected function person(array $data=[]): Persona
    {
        return Persona::create($data+['ci'=>'T'.bin2hex(random_bytes(5)),'nombres'=>'Persona','apellidos'=>'Prueba','telefono'=>'0000000000']);
    }
    protected function user(string $role='Administrador',?Persona $person=null): Usuario
    {
        $person??=$this->person();
        return Usuario::create(['id_persona'=>$person->id_persona,'correo'=>'test'.$person->id_persona.'@example.test','password_hash'=>Hash::make('ClavePrueba123!'),'id_rol'=>Rol::where('nombre_rol',$role)->value('id_rol')]);
    }
    protected function sessionFor(Usuario $u): array {return ['id_usuario'=>$u->id_usuario,'id_persona'=>$u->id_persona,'correo'=>$u->correo,'id_rol'=>$u->id_rol,'rol'=>$u->rol->nombre_rol,'nombre_rol'=>$u->rol->nombre_rol];}
    protected function patient(string $birthday='1990-01-01'): Paciente
    {
        $p=$this->person();$patient=Paciente::create(['id_paciente'=>$p->id_persona,'codigo_paciente'=>'PAC-T'.$p->id_persona,'sexo'=>'O','fecha_nacimiento'=>$birthday]);PerfilClinico::create(['id_paciente'=>$patient->id_paciente]);return $patient;
    }
    protected function doctor(): Medico
    {
        $p=$this->person();$m=Medico::create(['id_medico'=>$p->id_persona,'codigo_medico'=>'MED-T'.$p->id_persona,'nro_matricula'=>'MAT-T'.$p->id_persona]);$m->especialidades()->attach(Especialidad::where('nombre','Medicina General')->value('id_especialidad'));
        foreach(['Lunes','Martes','Miercoles','Jueves','Viernes','Sabado','Domingo'] as $day) HorarioAtencion::create(['id_medico'=>$m->id_medico,'dia_semana'=>$day,'hora_inicio'=>'08:00','hora_fin'=>'12:00','duracion_minutos'=>30]);return $m;
    }
    protected function appointmentData(?Paciente $p=null,?Medico $m=null): array
    {
        $p??=$this->patient();$m??=$this->doctor();return ['id_paciente'=>$p->id_paciente,'id_medico'=>$m->id_medico,'id_especialidad'=>$m->especialidades->first()->id_especialidad,'fecha_cita'=>today()->addDays(2)->toDateString(),'hora_inicio'=>'09:00','motivo_consulta'=>'Consulta de prueba'];
    }
}
