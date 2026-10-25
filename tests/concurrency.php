<?php
putenv('APP_ENV=testing');
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\{Persona,Medico,Paciente,Especialidad,HorarioAtencion,CitaMedica};
use Illuminate\Support\Facades\DB;
if(!str_ends_with(config('database.connections.pgsql.database'),'_test')) throw new RuntimeException('Solo se permite una base con sufijo _test.');
$token=bin2hex(random_bytes(6));
$ids=DB::transaction(function() use($token) {
    $p=Persona::create(['ci'=>'CON-P'.$token,'nombres'=>'Paciente','apellidos'=>'Concurrencia','telefono'=>'000']);
    $m=Persona::create(['ci'=>'CON-M'.$token,'nombres'=>'Médico','apellidos'=>'Concurrencia','telefono'=>'000']);
    $patient=Paciente::create(['id_paciente'=>$p->id_persona,'codigo_paciente'=>'C'.$token,'sexo'=>'O','fecha_nacimiento'=>'1990-01-01']);
    $doctor=Medico::create(['id_medico'=>$m->id_persona,'codigo_medico'=>'C'.$token,'nro_matricula'=>'C'.$token]);
    $e=Especialidad::where('nombre','Medicina General')->firstOrFail();$doctor->especialidades()->attach($e->id_especialidad);
    $date=today()->addDays(3);$days=['Lunes','Martes','Miercoles','Jueves','Viernes','Sabado','Domingo'];
    HorarioAtencion::create(['id_medico'=>$doctor->id_medico,'dia_semana'=>$days[$date->isoWeekday()-1],'hora_inicio'=>'08:00','hora_fin'=>'12:00','duracion_minutos'=>30]);
    return [$patient->id_paciente,$doctor->id_medico,$e->id_especialidad,$date->toDateString()];
});
$barrier=microtime(true)+2;$workers=[];
for($i=0;$i<2;$i++) {
    $cmd=array_merge([PHP_BINARY,__DIR__.'/concurrency-worker.php'],array_map('strval',$ids),[(string)$barrier]);
    $process=proc_open($cmd,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,dirname(__DIR__));fclose($pipes[0]);$workers[]=[$process,$pipes];
}
$results=[];foreach($workers as [$process,$pipes]) {$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);if($exit!==0)throw new RuntimeException($error);$results[]=$out;}
sort($results);$count=CitaMedica::where('id_medico',$ids[1])->where('fecha_cita',$ids[3])->count();
if($results!==['created','rejected'] || $count!==1) throw new RuntimeException('Falló la exclusión concurrente: '.json_encode([$results,$count]));
echo "OK: dos procesos simultáneos; una reserva creada, una rechazada; una sola cita persistida.\n";
