<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\{Usuario,Paciente,CitaMedica,PerfilClinico,RecetaMedica,DetalleReceta,Especialidad,HorarioAtencion,NotificacionCita,Permiso};
use App\Services\{CitaService,PagoService,ReporteService};
use Illuminate\Support\Facades\{DB,Hash,Http,Mail};
use Illuminate\Validation\ValidationException;
class ClinicaTest extends TestCase
{
    public function test_01_registro_atomico_crea_paciente_perfil_y_cuenta(): void
    {
        $r=$this->post('/registro',['ci'=>'REG-001','nombres'=>'Ana','apellidos'=>'Prueba','telefono'=>'00012345','sexo'=>'F','fecha_nacimiento'=>'1991-03-03','correo'=>'ana@example.test','password'=>'ClavePrueba123!','password_confirmation'=>'ClavePrueba123!']);
        $r->assertRedirect('/login')->assertSessionHasNoErrors();$p=Paciente::whereHas('persona',fn($q)=>$q->where('ci','REG-001'))->firstOrFail();$this->assertStringStartsWith('PAC-',$p->codigo_paciente);$this->assertNotNull($p->perfilClinico);$this->assertNull($p->perfilClinico->tipo_sangre);$this->assertTrue(Hash::check('ClavePrueba123!',$p->persona->usuario->password_hash));
    }
    public function test_02_ci_duplicado_rechazado_sin_registros_parciales(): void
    {
        $this->person(['ci'=>'DUPLICADO']);$before=Paciente::count();
        $this->post('/registro',['ci'=>'DUPLICADO','nombres'=>'Ana','apellidos'=>'Prueba','telefono'=>'12345','sexo'=>'F','fecha_nacimiento'=>'1990-01-01','correo'=>'dup@example.test','password'=>'ClavePrueba123!','password_confirmation'=>'ClavePrueba123!'])->assertSessionHasErrors('ci');$this->assertSame($before,Paciente::count());
    }
    public function test_03_reserva_valida_mediante_procedimiento(): void
    {
        $d=$this->appointmentData();$c=app(CitaService::class)->book($d,null);$this->assertSame('Programada',$c->estado);$this->assertSame('09:30:00',$c->hora_fin);$this->assertStringStartsWith('CM-',$c->codigo_cita);
    }
    public function test_04_colision_no_crea_segunda_cita(): void
    {
        $d=$this->appointmentData();$service=app(CitaService::class);$service->book($d,null);
        try {$service->book($d,null);$this->fail('Permitió colisión');} catch(ValidationException $e) {$this->assertArrayHasKey('hora_inicio',$e->errors());}
        $this->assertSame(1,CitaMedica::where('id_medico',$d['id_medico'])->count());
    }
    public function test_05_rechaza_fecha_pasada_fuera_de_turno_y_rango_etario(): void
    {
        $d=$this->appointmentData();$service=app(CitaService::class);
        foreach([['fecha_cita'=>today()->subDay()->toDateString()],['hora_inicio'=>'18:00'],['id_especialidad'=>Especialidad::where('nombre','Pediatría')->value('id_especialidad')]] as $change) {
            try {$service->book(array_replace($d,$change),null);$this->fail('Aceptó datos inválidos');}catch(ValidationException $e){$this->assertNotEmpty($e->errors());}
        }
        $this->assertSame(0,CitaMedica::where('id_medico',$d['id_medico'])->count());
    }
    public function test_06_cancelacion_libera_bloque_y_conserva_historial(): void
    {
        $d=$this->appointmentData();$service=app(CitaService::class);$c=$service->book($d,null);$service->cancel($c,'Cambio de planes');$this->assertSame('Cancelada',$c->fresh()->estado);$new=$service->book($d,null);$this->assertNotSame($c->id_cita,$new->id_cita);
    }
    public function test_07_kardex_incluye_canceladas_y_aisla_pacientes(): void
    {
        $p=$this->patient();$u=$this->user('Paciente',$p->persona);$d=$this->appointmentData($p);$c=app(CitaService::class)->book($d,null);app(CitaService::class)->cancel($c,'Prueba');$other=app(CitaService::class)->book($this->appointmentData(),null);
        $this->withSession($this->sessionFor($u))->get('/paciente/citas')->assertOk()->assertSee($c->codigo_cita)->assertSee('Cancelada')->assertDontSee($other->codigo_cita);
        $this->post('/citas/'.$other->id_cita.'/cancelar',['motivo'=>'Intrusión'])->assertForbidden();
    }
    public function test_08_agenda_solo_muestra_medico_asignado(): void
    {
        $d=$this->appointmentData();$m=\App\Models\Medico::find($d['id_medico']);$u=$this->user('Medico',$m->persona);$own=app(CitaService::class)->book($d,null);$other=app(CitaService::class)->book($this->appointmentData(),null);
        $this->withSession($this->sessionFor($u))->get('/medico/agenda?fecha='.$d['fecha_cita'])->assertOk()->assertSee($own->codigo_cita)->assertDontSee($other->codigo_cita);
    }
    public function test_09_todas_las_rutas_internas_exigen_sesion(): void
    {
        foreach(['/admin','/admin/usuarios','/recepcion/pacientes','/medico/agenda','/paciente/citas','/perfil/1','/pagos/1'] as $url) $this->get($url)->assertRedirect('/login');
    }
    public function test_10_validaciones_obligatorias_y_horas_invertidas(): void
    {
        $u=$this->user();$this->withSession($this->sessionFor($u))->post('/admin/usuarios',[])->assertSessionHasErrors(['ci','nombres','correo','password']);$m=$this->doctor();
        $this->post('/admin/horarios',['id_medico'=>$m->id_medico,'dia_semana'=>'Lunes','hora_inicio'=>'12:00','hora_fin'=>'08:00','duracion_minutos'=>30])->assertSessionHasErrors('hora_fin');
    }
    public function test_login_bloqueo_progresivo_y_cuenta_inactiva(): void
    {
        $u=$this->user();for($i=0;$i<3;$i++)$this->post('/login',['correo'=>$u->correo,'password'=>'incorrecta'])->assertSessionHasErrors('correo');
        $this->assertSame(3,$u->fresh()->intentos_fallidos);$this->assertTrue($u->fresh()->bloqueado_hasta->isFuture());
        $this->post('/login',['correo'=>$u->correo,'password'=>'ClavePrueba123!'])->assertSessionHasErrors('correo');
        $u->update(['bloqueado_hasta'=>null]);$this->post('/login',['correo'=>$u->correo,'password'=>'ClavePrueba123!'])->assertRedirect('/admin');$this->assertSame(0,$u->fresh()->intentos_fallidos);
        $u->update(['estado'=>'Inactivo']);$this->get('/admin')->assertRedirect('/login');
    }
    public function test_recuperacion_hash_expiracion_y_token_de_un_solo_uso(): void
    {
        Mail::fake();$u=$this->user();$this->post('/recuperar',['correo'=>$u->correo])->assertSessionHasNoErrors();$this->assertNotNull($u->fresh()->token_recuperacion);
        $token=str_repeat('a',64);$u->update(['token_recuperacion'=>hash('sha256',$token),'token_expiracion'=>now()->addMinutes(30)]);
        $body=['correo'=>$u->correo,'token'=>$token,'password'=>'OtraClave123!','password_confirmation'=>'OtraClave123!'];$this->post('/restablecer',$body)->assertRedirect('/login');$this->assertTrue(Hash::check('OtraClave123!',$u->fresh()->password_hash));$this->post('/restablecer',$body)->assertSessionHasErrors('token');
    }
    public function test_reprogramacion_atomica_y_trazabilidad(): void
    {
        $d=$this->appointmentData();$s=app(CitaService::class);$c=$s->book($d,null);$new=$s->reschedule($c,['fecha_cita'=>$d['fecha_cita'],'hora_inicio'=>'10:00','motivo'=>'Cambio'],null);$this->assertSame($c->id_cita,$new->id_cita_anterior);$this->assertSame('Cancelada',$c->fresh()->estado);
        try{$s->reschedule($new,['fecha_cita'=>$d['fecha_cita'],'hora_inicio'=>'18:00','motivo'=>'Fuera de turno'],null);$this->fail();}catch(ValidationException $e){}$this->assertSame('Programada',$new->fresh()->estado);
    }
    public function test_privacidad_perfil_chatbot_y_recetas(): void
    {
        $p=$this->patient();$other=$this->patient();$u=$this->user('Paciente',$p->persona);
        $this->withSession($this->sessionFor($u))->get('/perfil/'.$other->id_paciente)->assertForbidden();
        $this->postJson('/api/chatbot/mensaje',['mensaje'=>$other->persona->ci])->assertOk()->assertJsonFragment(['respuesta'=>'Solo puede consultar las citas vinculadas a su propia cuenta.']);
        $r=$this->user('Recepcionista');$this->withSession($this->sessionFor($r))->get('/perfil/'.$p->id_paciente)->assertForbidden();
    }
    public function test_alergia_rechaza_receta_completa(): void
    {
        $d=$this->appointmentData();$c=app(CitaService::class)->book($d,null);$c->update(['estado'=>'Confirmada']);$c->paciente->perfilClinico->update(['alergias'=>'Penicilina']);$u=$this->user('Medico',$c->medico->persona);
        $this->withSession($this->sessionFor($u))->post('/medico/citas/'.$c->id_cita.'/receta',['diagnostico'=>'Prueba','items'=>[['tipo_item'=>'Medicamento','medicamento_o_examen'=>'Penicilina','dosis'=>'1','frecuencia'=>'24 h','duracion'=>'1 día']]])->assertSessionHasErrors('db_error');$this->assertFalse(RecetaMedica::where('id_cita',$c->id_cita)->exists());
    }
    public function test_pago_idempotente_monto_y_comprobante(): void
    {
        $c=app(CitaService::class)->book($this->appointmentData(),null);$s=app(PagoService::class);$amount=PagoService::cents($c->especialidad->arancel_consulta);$p=$s->record($c->id_cita,$amount,'usd','pi_prueba');$again=$s->record($c->id_cita,$amount,'usd','pi_prueba');$this->assertSame($p->id_pago,$again->id_pago);$this->assertNotNull($p->comprobante);$this->assertSame('Confirmada',$c->fresh()->estado);
        $other=app(CitaService::class)->book($this->appointmentData(),null);try{$s->record($other->id_cita,1,'usd','pi_monto');$this->fail();}catch(ValidationException $e){}$this->assertNull($other->fresh()->pago);
    }
    public function test_webhook_firma_invalida_y_eventos_duplicados(): void
    {
        config(['services.stripe.webhook_secret'=>'whsec_prueba']);$this->postJson('/stripe/webhook',[])->assertStatus(400);$c=app(CitaService::class)->book($this->appointmentData(),null);
        $payload=json_encode(['id'=>'evt_prueba','object'=>'event','type'=>'checkout.session.completed','data'=>['object'=>['id'=>'cs_prueba','object'=>'checkout.session','payment_status'=>'paid','metadata'=>['id_cita'=>(string)$c->id_cita],'amount_total'=>10000,'currency'=>'usd','payment_intent'=>'pi_evento']]]);$time=time();$signature='t='.$time.',v1='.hash_hmac('sha256',$time.'.'.$payload,'whsec_prueba');
        for($i=0;$i<2;$i++)$this->call('POST','/stripe/webhook',[],[],[],['CONTENT_TYPE'=>'application/json','HTTP_STRIPE_SIGNATURE'=>$signature],$payload)->assertOk();
        $this->assertSame(1,\App\Models\Pago::where('id_cita',$c->id_cita)->count());
    }
    public function test_contingencia_traslada_y_notifica_sin_afirmar_envio(): void
    {
        $d=$this->appointmentData();$c=app(CitaService::class)->book($d,null);$this->user('Paciente',$c->paciente->persona);$u=$this->user('Recepcionista');
        $this->withSession($this->sessionFor($u))->post('/contingencias',['id_medico'=>$d['id_medico'],'fecha'=>$d['fecha_cita'],'hora_inicio'=>'08:00','hora_fin'=>'10:00','motivo'=>'Emergencia médica'])->assertSessionHasNoErrors();$this->assertSame('10:00:00',$c->fresh()->hora_inicio);$this->assertSame('Pendiente',NotificacionCita::where('id_cita',$c->id_cita)->where('tipo','Reprogramacion_Emergencia')->first()->estado_envio);
    }
    public function test_qbe_parametrizado_rechaza_identificadores_inyectados(): void
    {
        $s=app(ReporteService::class);$c=app(CitaService::class)->book($this->appointmentData(),null);
        $data=$s->qbe(['campos'=>['codigo','estado'],'filtros'=>[['campo'=>'especialidad','operador'=>'=','valor'=>"' OR 1=1 --"]],'orden'=>'fecha']);$this->assertCount(0,$data['rows']);$this->assertStringNotContainsString('OR 1=1',$data['sql']);
        $this->expectException(ValidationException::class);$s->qbe(['campos'=>['password_hash']]);
    }
    public function test_voz_plan_validado_y_aislamiento_medico(): void
    {
        $d=$this->appointmentData();$c=app(CitaService::class)->book($d,null);$other=app(CitaService::class)->book($this->appointmentData(),null);$u=$this->user('Medico',$c->medico->persona);config(['services.gemini.key'=>'fake']);Http::fake(['*'=>Http::response(['candidates'=>[['content'=>['parts'=>[['text'=>json_encode(['campos'=>['codigo','estado'],'orden'=>'fecha'])]]]]]])]);
        $this->withSession($this->sessionFor($u))->postJson('/reportes/voz',['texto'=>'Mis citas'])->assertOk()->assertJsonFragment(['codigo'=>$c->codigo_cita])->assertJsonMissing(['codigo'=>$other->codigo_cita]);$this->postJson('/reportes/voz',['texto'=>'Elimina citas'])->assertUnprocessable();
    }
    public function test_permisos_revocados_se_aplican_en_sesion_activa(): void
    {
        $u=$this->user('Recepcionista');$this->withSession($this->sessionFor($u))->get('/recepcion/pacientes')->assertOk();$u->rol->permisos()->detach(Permiso::where('nombre_permiso','pacientes')->value('id_permiso'));$this->get('/recepcion/pacientes')->assertForbidden();
    }
    public function test_paginas_principales_y_exportaciones_renderizan(): void
    {
        $c=app(CitaService::class)->book($this->appointmentData(),null);$admin=$this->user();$this->withSession($this->sessionFor($admin));
        foreach(['/admin','/admin/usuarios','/admin/usuarios/create','/admin/usuarios/'.$admin->id_usuario.'/edit','/admin/roles','/admin/bitacora','/admin/especialidades','/admin/medicos','/admin/horarios','/admin/reportes','/admin/qbe','/admin/voz'] as $url)$this->get($url)->assertOk();
        foreach(array_keys(ReporteService::TYPES) as $type)$this->get('/admin/reportes?tipo='.$type.'&desde='.today()->toDateString().'&hasta='.today()->addDays(10)->toDateString())->assertOk();
        foreach(['pdf','csv','xlsx'] as $format)$this->get('/admin/reportes?tipo=agenda&desde='.$c->fecha_cita.'&hasta='.$c->fecha_cita.'&formato='.$format)->assertOk();
        $u=$this->user('Recepcionista');$this->withSession($this->sessionFor($u));foreach(['/recepcion','/recepcion/pacientes','/recepcion/pacientes/create','/recepcion/citas','/recepcion/citas/nueva','/recepcion/agenda','/recepcion/contingencias','/pagos/'.$c->id_cita] as $url)$this->get($url)->assertOk();
        $u=$this->user('Medico',$c->medico->persona);$this->withSession($this->sessionFor($u));foreach(['/medico','/medico/contingencias','/medico/voz','/perfil/'.$c->id_paciente,'/medico/citas/'.$c->id_cita.'/receta','/medico/citas/'.$c->id_cita.'/derivacion'] as $url)$this->get($url)->assertOk();
    }
}
