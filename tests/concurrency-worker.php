<?php
putenv('APP_ENV=testing');
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!str_ends_with(config('database.connections.pgsql.database'),'_test')) exit(2);
[$file,$patient,$doctor,$specialty,$date,$barrier]=$argv;
while(microtime(true)<(float)$barrier) usleep(1000);
try {
    app(App\Services\CitaService::class)->book(['id_paciente'=>(int)$patient,'id_medico'=>(int)$doctor,'id_especialidad'=>(int)$specialty,'fecha_cita'=>$date,'hora_inicio'=>'10:00','motivo_consulta'=>'Concurrencia de prueba'],null);
    echo 'created';
} catch(Illuminate\Validation\ValidationException $e) { echo 'rejected'; }
