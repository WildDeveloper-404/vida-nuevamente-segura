<?php
use Illuminate\Support\Facades\{Artisan,DB,Hash,Mail,Schedule};
use App\Models\{Persona,PersonalAdministrativo,Usuario,Rol,Permiso,Modulo,NotificacionCita,CitaMedica};
use App\Services\DevolucionService;

Artisan::command('clinica:instalar {--datos : Cargar catálogos iniciales}', function() {
    $installed = DB::transaction(function () {
        DB::select('SELECT pg_advisory_xact_lock(180018)');
        if (DB::selectOne("SELECT to_regclass('public.persona') AS tabla")->tabla) return false;
        foreach (['01_tablas.sql','02_triggers.sql','07_requisitos_funcionales.sql','05_procedimientos_compatibles.sql','06_reglas_compatibles.sql','08_integridad_agendas.sql'] as $file) {
            DB::unprepared(file_get_contents(database_path('sql/'.$file)));
        }
        if ($this->option('datos')) DB::unprepared(file_get_contents(database_path('sql/04_catalogos_base.sql')));
        if ($this->call('clinica:permisos') !== 0) throw new \RuntimeException('No se pudieron configurar los permisos.');
        return true;
    });
    if (!$installed) {$this->error('Ya existe el esquema. Use clinica:compatibilidad para actualizarlo.');return 1;}
    $this->info('Esquema instalado. Cree el administrador con clinica:admin.');
})->purpose('Instalar en una base vacía, sin migraciones');
Artisan::command('clinica:compatibilidad {--moneda-anterior=usd : Moneda de los registros antiguos que todavía no la guardan}', function() {
    $currency = strtolower((string) $this->option('moneda-anterior'));
    if (!in_array($currency, ['usd','bob'], true)) {$this->error('La moneda anterior debe ser usd o bob.');return 1;}
    DB::transaction(function () use ($currency) {
        DB::select('SELECT pg_advisory_xact_lock(180018)');
        DB::selectOne("SELECT set_config('clinica.moneda_anterior', ?, true)", [$currency]);
        foreach (['07_requisitos_funcionales.sql','05_procedimientos_compatibles.sql','06_reglas_compatibles.sql','08_integridad_agendas.sql'] as $file) {
            DB::unprepared(file_get_contents(database_path('sql/'.$file)));
        }
    });
    $this->info('Esquema y funciones actualizados. Los registros existentes se conservaron.');
});
Artisan::command('clinica:permisos', function() {
    $matrix=['usuarios'=>['Administrador'],'catalogos'=>['Administrador'],'auditoria'=>['Administrador'],'pacientes'=>['Administrador','Recepcionista'],'citas'=>['Recepcionista','Paciente'],'agenda'=>['Recepcionista','Medico'],'contingencias'=>['Administrador','Recepcionista','Medico'],'clinico'=>['Medico','Paciente'],'pagos'=>['Recepcionista','Paciente'],'reportes'=>['Administrador','Recepcionista'],'qbe'=>['Administrador'],'voz'=>['Administrador','Medico']];
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
    NotificacionCita::where('estado_envio','Pendiente')->where('enviar_desde','<=',now())->chunkById(50,function($rows) {
        foreach ($rows as $row) {
            DB::transaction(function () use ($row) {
                $cita = CitaMedica::whereKey($row->id_cita)->lockForUpdate()->firstOrFail();
                $notice = NotificacionCita::whereKey($row->id_notificacion)->lockForUpdate()->firstOrFail();
                if ($notice->estado_envio !== 'Pendiente' || now()->lt($notice->enviar_desde)) {
                    return;
                }
                if ($notice->tipo === 'Recordatorio' && (!in_array($cita->estado, ['Programada','Confirmada'], true)
                    || now()->gte($cita->fecha_cita.' '.$cita->hora_inicio))) {
                    $notice->update(['estado_envio'=>'Anulado']);
                    return;
                }
                $message = $notice->mensaje."\n\n".route('paciente.citas');
                try {
                    Mail::raw($message, fn ($m) => $m->to($notice->correo_destino)->subject('Aviso de su cita médica'));
                    $notice->update(['estado_envio'=>'Enviado','fecha_envio'=>now()]);
                } catch (\Throwable $e) {
                    $notice->update(['estado_envio'=>'Fallo_Correo']);
                    report($e);
                }
            });
        }
    },'id_notificacion');$this->info('Despacho finalizado.');
});
Artisan::command('clinica:devoluciones', function (DevolucionService $service) {
    $service->processStripe();
})->purpose('Procesar devoluciones pendientes de Stripe en modo de prueba');
Schedule::command('clinica:notificaciones')->everyMinute()->withoutOverlapping();
Schedule::command('clinica:devoluciones')->everyMinute()->withoutOverlapping();
