<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\{CitaMedica,Usuario,Medico,Especialidad,HorarioAtencion,BloqueoAgenda,RecetaMedica,NotificacionCita};
use App\Services\{CitaService,PagoService,ReporteService};
use Illuminate\Support\Facades\DB;
class AdditionalFlowsTest extends TestCase
{
    public function test_pago_se_traslada_al_reprogramar_y_webhook_anterior_sigue_idempotente(): void
    {
        $s=app(CitaService::class);$c=$s->book($this->appointmentData(),null);$payment=app(PagoService::class)->record($c->id_cita,10000,'usd','pi_traslado');
        $new=$s->reschedule($c,['fecha_cita'=>$c->fecha_cita,'hora_inicio'=>'11:00','motivo'=>'Cambio'],null);
        $this->assertSame('Confirmada',$new->estado);$this->assertSame($new->id_cita,$payment->fresh()->id_cita);
        $again=app(PagoService::class)->record($c->id_cita,10000,'usd','pi_traslado');$this->assertSame($payment->id_pago,$again->id_pago);
    }
    public function test_receta_pdf_qr_y_comprobante_pdf(): void
    {
        $c=app(CitaService::class)->book($this->appointmentData(),null);$pay=app(PagoService::class)->record($c->id_cita,10000,'usd','pi_pdf');$u=$this->user('Medico',$c->medico->persona);
        $this->withSession($this->sessionFor($u))->post('/medico/citas/'.$c->id_cita.'/receta',['diagnostico'=>'Diagnóstico de prueba','items'=>[['tipo_item'=>'Examen_Laboratorio','medicamento_o_examen'=>'Hemograma']]])->assertSessionHasNoErrors();
        $rx=RecetaMedica::where('id_cita',$c->id_cita)->firstOrFail();$r=$this->get('/recetas/'.$rx->id_receta.'/pdf')->assertOk();$this->assertStringStartsWith('%PDF',$r->getContent());
        $this->get('/receta/verificar/'.$rx->codigo_qr)->assertOk()->assertDontSee('Diagnóstico de prueba');
        $u=$this->user('Paciente',$c->paciente->persona);$this->withSession($this->sessionFor($u))->get('/comprobantes/'.$pay->id_pago.'?pdf=1')->assertOk();
    }
    public function test_administracion_crea_medico_y_usuario_y_bajas_logicas(): void
    {
        $u=$this->user();$this->withSession($this->sessionFor($u));
        $this->post('/admin/medicos',['ci'=>'MED-CONTROL','nombres'=>'Luis','apellidos'=>'Prueba','telefono'=>'1234','correo'=>'med-control@example.test','nro_matricula'=>'MAT-CONTROL','password'=>'ClavePrueba123!','password_confirmation'=>'ClavePrueba123!','especialidades'=>[Especialidad::where('nombre','Medicina General')->value('id_especialidad')]])->assertSessionHasNoErrors();
        $med=Medico::where('nro_matricula','MAT-CONTROL')->firstOrFail();$this->assertNotNull($med->persona->usuario);
        $this->delete('/admin/medicos/'.$med->id_medico)->assertSessionHasNoErrors();$this->assertSame('Inactivo',$med->fresh()->estado);$this->assertSame('Inactivo',$med->persona->usuario->fresh()->estado);
        $this->post('/admin/usuarios',['ci'=>'REC-CONTROL','nombres'=>'Laura','apellidos'=>'Prueba','telefono'=>'1234','correo'=>'rec-control@example.test','password'=>'ClavePrueba123!','password_confirmation'=>'ClavePrueba123!','id_rol'=>\App\Models\Rol::where('nombre_rol','Recepcionista')->value('id_rol'),'cargo'=>'Recepción','estado'=>'Activo'])->assertSessionHasNoErrors();$this->assertNotNull(Usuario::where('correo','rec-control@example.test')->first());
    }
    public function test_estado_atendida_no_se_puede_reabrir_y_no_borrado_fisico(): void
    {
        $c=app(CitaService::class)->book($this->appointmentData(),null);$c->update(['estado'=>'Confirmada']);$c->update(['estado'=>'Atendida']);
        foreach([fn()=> $c->update(['estado'=>'Programada']),fn()=> $c->delete()] as $action) {
            try {DB::transaction($action);$this->fail('Permitió modificar historial');} catch(\Illuminate\Database\QueryException $e) {$this->assertSame('P0001',$e->errorInfo[0]);}
        }
    }
    public function test_duracion_correcta_cuando_los_dias_tienen_intervalos_distintos(): void
    {
        $d=$this->appointmentData();$day=(int)\Carbon\Carbon::parse($d['fecha_cita'])->isoWeekday();$days=['Lunes','Martes','Miercoles','Jueves','Viernes','Sabado','Domingo'];
        HorarioAtencion::where('id_medico',$d['id_medico'])->where('dia_semana',$days[$day-1])->update(['duracion_minutos'=>20]);
        $c=app(CitaService::class)->book($d,null);$this->assertSame('09:20:00',$c->hora_fin);
    }
    public function test_contingencia_sin_cupo_revierte_bloqueo_y_todos_los_traslados(): void
    {
        $d=$this->appointmentData();$c=app(CitaService::class)->book($d,null);$u=$this->user('Recepcionista');
        for($i=1;$i<=30;$i++)BloqueoAgenda::create(['id_medico'=>$d['id_medico'],'fecha_bloqueo'=>\Carbon\Carbon::parse($d['fecha_cita'])->addDays($i)->toDateString(),'hora_inicio'=>'00:00','hora_fin'=>'23:59','tipo_bloqueo'=>'Feriado','motivo'=>'Prueba sin cupos']);
        $count=BloqueoAgenda::count();$this->withSession($this->sessionFor($u))->post('/contingencias',['id_medico'=>$d['id_medico'],'fecha'=>$d['fecha_cita'],'hora_inicio'=>'08:00','hora_fin'=>'12:00','motivo'=>'Emergencia'])->assertSessionHasErrors('db_error');$this->assertSame($count,BloqueoAgenda::count());$this->assertSame('09:00:00',$c->fresh()->hora_inicio);
    }
    public function test_derivacion_reserva_destino_y_actualiza_estado(): void
    {
        $c=app(CitaService::class)->book($this->appointmentData(),null);$c->update(['estado'=>'Confirmada']);$u=$this->user('Medico',$c->medico->persona);
        $this->withSession($this->sessionFor($u))->post('/medico/citas/'.$c->id_cita.'/derivacion',['id_especialidad_destino'=>$c->id_especialidad,'prioridad'=>'Preferente','motivo_clinico'=>'Seguimiento'])->assertSessionHasNoErrors();
        $der=$c->derivaciones()->firstOrFail();$u=$this->user('Paciente',$c->paciente->persona);$this->withSession($this->sessionFor($u))->get('/paciente/citas/nueva?derivacion='.$der->id_derivacion)->assertOk()->assertSee($der->codigo_derivacion);
        $data=$this->appointmentData($c->paciente,$c->medico);$data['hora_inicio']='11:00';$data['id_derivacion']=$der->id_derivacion;$this->post('/citas',$data)->assertSessionHasNoErrors();$this->assertSame('Programada',$der->fresh()->estado);
    }
}
