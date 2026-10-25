<?php
use Illuminate\Support\Facades\{Artisan,DB,Hash,Mail,Schedule};
use App\Models\{Persona,PersonalAdministrativo,Usuario,Rol,Permiso,Modulo,NotificacionCita};

Artisan::command('clinica:instalar {--datos : Cargar catálogos iniciales}', function() {
    if(DB::selectOne("SELECT to_regclass('public.persona') AS tabla")->tabla) {$this->error('Ya existe el esquema. Use clinica:compatibilidad para actualizar únicamente funciones.');return 1;}
    DB::unprepared(file_get_contents(database_path('sql/01_tablas.sql')));
    foreach(['02_triggers.sql','05_procedimientos_compatibles.sql','06_reglas_compatibles.sql'] as $file) DB::unprepared(file_get_contents(database_path('sql/'.$file)));
    if($this->option('datos')) DB::unprepared(file_get_contents(database_path('sql/04_datos.sql')));
    $this->call('clinica:permisos');$this->info('Esquema instalado. Cree el administrador con clinica:admin.');
})->purpose('Instalar en una base vacía, sin migraciones');
Artisan::command('clinica:compatibilidad', function() {
    DB::transaction(function() {foreach(['05_procedimientos_compatibles.sql','06_reglas_compatibles.sql'] as $file) DB::unprepared(file_get_contents(database_path('sql/'.$file)));});
    $this->info('Funciones actualizadas. Las tablas y sus datos se conservan.');
});
Artisan::command('clinica:permisos', function() {
    $matrix=['usuarios'=>['Administrador'],'catalogos'=>['Administrador'],'auditoria'=>['Administrador'],'pacientes'=>['Recepcionista'],'citas'=>['Recepcionista','Paciente'],'agenda'=>['Recepcionista','Medico'],'contingencias'=>['Recepcionista','Medico'],'clinico'=>['Medico','Paciente'],'pagos'=>['Recepcionista','Paciente'],'reportes'=>['Administrador','Recepcionista'],'qbe'=>['Administrador'],'voz'=>['Administrador','Medico']];
    DB::transaction(function() use($matrix) {
        $module=Modulo::firstOrCreate(['nombre_modulo'=>'Operaciones de la clínica']);
        foreach($matrix as $name=>$roles) {
            $permission=Permiso::firstOrCreate(['nombre_permiso'=>$name],['id_modulo'=>$module->id_modulo]);
            foreach($roles as $nameRole) {$role=Rol::firstOrCreate(['nombre_rol'=>$nameRole]);$role->permisos()->syncWithoutDetaching([$permission->id_permiso]);}
        }
    });$this->info('Permisos iniciales configurados. No ejecute este comando para sobrescribir ajustes posteriores.');
});
Artisan::command('clinica:admin', function() {
    $data=['ci'=>$this->ask('CI'),'nombres'=>$this->ask('Nombres'),'apellidos'=>$this->ask('Apellidos'),'telefono'=>$this->ask('Teléfono'),'correo'=>mb_strtolower($this->ask('Correo')),'password'=>$this->secret('Contraseña (mínimo 10 caracteres)')];
    $validator=validator($data,['ci'=>'required|max:20|unique:persona,ci','nombres'=>'required|max:80','apellidos'=>'required|max:100','telefono'=>'required|max:20','correo'=>'required|email|max:120|unique:usuario,correo','password'=>'required|min:10|max:128']);
    if($validator->fails()) {$this->error($validator->errors()->first());return 1;}
    DB::transaction(function() use($data) {$p=Persona::create(collect($data)->only(['ci','nombres','apellidos','telefono'])->all());PersonalAdministrativo::create(['id_personal'=>$p->id_persona,'cargo'=>'Administrador']);Usuario::create(['id_persona'=>$p->id_persona,'correo'=>$data['correo'],'password_hash'=>Hash::make($data['password']),'id_rol'=>Rol::where('nombre_rol','Administrador')->firstOrFail()->id_rol]);});$this->info('Administrador creado.');
});
Artisan::command('clinica:notificaciones', function() {
    if(config('mail.default')==='log') {$this->warn('MAIL_MAILER=log: no se marcarán correos como enviados. Configure SMTP para el despacho real.');return 0;}
    NotificacionCita::where('estado_envio','Pendiente')->orderBy('id_notificacion')->chunkById(50,function($rows) {
        foreach($rows as $row) DB::transaction(function() use($row) {$n=NotificacionCita::whereKey($row->id_notificacion)->lockForUpdate()->first();if($n->estado_envio!=='Pendiente') return;try {Mail::raw($n->mensaje,fn($m)=>$m->to($n->correo_destino)->subject('Aviso de su cita médica'));$n->update(['estado_envio'=>'Enviado','fecha_envio'=>now()]);}catch(\Throwable $e){$n->update(['estado_envio'=>'Fallo_Correo']);report($e);}});
    },'id_notificacion');$this->info('Despacho finalizado.');
});
Schedule::command('clinica:notificaciones')->everyFiveMinutes()->withoutOverlapping();
