<?php

namespace Tests\Feature;

use App\Models\{Consultorio,Especialidad,HorarioAtencion,Permiso,Rol};
use App\Services\{CitaService,PagoService};
use App\Support\Audit;
use Carbon\Carbon;
use Tests\TestCase;

class CasosDeUsoAceptacionTest extends TestCase
{
    public function test_cu02_asignacion_y_revocacion_desde_la_ruta_administrativa(): void
    {
        $admin=$this->user();
        $recepcion=$this->user('Recepcionista');
        $rol=$recepcion->rol;
        $permiso=Permiso::where('nombre_permiso','pacientes')->value('id_permiso');
        $permisos=$rol->permisos->modelKeys();
        $this->withSession($this->sessionFor($admin))
            ->put('/admin/roles/'.$rol->id_rol,['permisos'=>array_values(array_diff($permisos,[$permiso]))])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->withSession($this->sessionFor($recepcion))->get('/recepcion/pacientes')->assertForbidden();
        $this->withSession($this->sessionFor($admin))
            ->put('/admin/roles/'.$rol->id_rol,['permisos'=>$permisos])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->withSession($this->sessionFor($recepcion))->get('/recepcion/pacientes')->assertOk();
        $this->withSession($this->sessionFor($admin))
            ->put('/admin/roles/'.Rol::where('nombre_rol','Administrador')->value('id_rol'),['permisos'=>[]])
            ->assertStatus(422);
    }

    public function test_cu03_filtra_actor_modulo_accion_y_exporta_bitacora(): void
    {
        $admin=$this->user();
        $this->withSession($this->sessionFor($admin));
        Audit::log('Aceptacion CU03','ENSAYO_CU03','Evidencia controlada CU03');
        Audit::log('Aceptacion CU03','OTRA_ACCION','Evento excluido CU03');
        $url='/admin/bitacora?'.http_build_query([
            'correo'=>$admin->correo,'rol'=>'Administrador','modulo'=>'Aceptacion CU03',
            'accion'=>'ENSAYO_CU03','fecha_inicio'=>today()->toDateString(),'fecha_fin'=>today()->toDateString(),
        ]);
        $this->get($url)->assertOk()->assertSee('Evidencia controlada CU03')->assertDontSee('Evento excluido CU03');
        $xlsx=$this->get($url.'&formato=xlsx')->assertOk()->assertDownload('reporte.xlsx');
        $this->assertStringStartsWith('PK',$xlsx->streamedContent());
        $this->withSession($this->sessionFor($this->user('Recepcionista')))->get($url)->assertForbidden();
    }

    public function test_cu04_edita_catalogo_y_rechaza_rango_etario_invertido(): void
    {
        $this->withSession($this->sessionFor($this->user()));
        $especialidad=Especialidad::where('nombre','Medicina General')->firstOrFail();
        $datos=['nombre'=>$especialidad->nombre,'descripcion'=>'Control de aceptación',
            'edad_minima'=>18,'edad_maxima'=>90,'arancel_consulta'=>135.50,'estado'=>'Activo'];
        $url='/admin/especialidades/'.$especialidad->id_especialidad;
        $this->put($url,$datos)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(135.50,$especialidad->fresh()->arancel_consulta);
        $this->putJson($url,array_replace($datos,['edad_minima'=>91]))->assertUnprocessable()->assertJsonValidationErrors('edad_maxima');
        $this->assertSame(18,(int)$especialidad->fresh()->edad_minima);
    }

    public function test_cu05_alta_consultorio_turno_y_baja_protegida_por_reserva(): void
    {
        $this->withSession($this->sessionFor($this->user()));
        $numero='T-'.bin2hex(random_bytes(3));
        $this->post('/admin/consultorios',['numero'=>$numero,'bloque'=>'Pruebas','piso'=>1])
            ->assertRedirect()->assertSessionHasNoErrors();
        $consultorio=Consultorio::where('numero',$numero)->firstOrFail();
        $medico=$this->doctor();
        $this->post('/admin/horarios',['id_medico'=>$medico->id_medico,'id_consultorio'=>$consultorio->id_consultorio,
            'dia_semana'=>'Lunes','hora_inicio'=>'13:00','hora_fin'=>'14:00','duracion_minutos'=>20])
            ->assertRedirect()->assertSessionHasNoErrors();
        $turno=HorarioAtencion::where('id_medico',$medico->id_medico)->where('hora_inicio','13:00')->firstOrFail();
        $this->delete('/admin/horarios/'.$turno->id_horario)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Inactivo',$turno->fresh()->estado);
        $datos=$this->appointmentData(null,$medico);
        app(CitaService::class)->book($datos,null);
        $dias=['Lunes','Martes','Miercoles','Jueves','Viernes','Sabado','Domingo'];
        $turnoOcupado=HorarioAtencion::where('id_medico',$medico->id_medico)
            ->where('dia_semana',$dias[Carbon::parse($datos['fecha_cita'])->isoWeekday()-1])->where('estado','Activo')->firstOrFail();
        $this->deleteJson('/admin/horarios/'.$turnoOcupado->id_horario)->assertUnprocessable()->assertJsonValidationErrors('horario');
        $this->assertSame('Activo',$turnoOcupado->fresh()->estado);
    }

    public function test_cu11_inasistencia_solo_desde_el_minuto_treinta(): void
    {
        $cita=app(CitaService::class)->book($this->appointmentData(),null);
        $this->withSession($this->sessionFor($this->user('Recepcionista')));
        try {
            Carbon::setTestNow(Carbon::parse($cita->fecha_cita.' '.$cita->hora_inicio)->addMinutes(29));
            $this->postJson('/agenda/'.$cita->id_cita.'/estado',['estado'=>'Inasistencia'])
                ->assertUnprocessable()->assertJsonValidationErrors('estado');
            $this->assertSame('Programada',$cita->fresh()->estado);
            Carbon::setTestNow(Carbon::parse($cita->fecha_cita.' '.$cita->hora_inicio)->addMinutes(30));
            $this->post('/agenda/'.$cita->id_cita.'/estado',['estado'=>'Inasistencia'])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame('Inasistencia',$cita->fresh()->estado);
        } finally { Carbon::setTestNow(); }
    }

    public function test_cu13_derivacion_completada_al_atender_cita_de_destino(): void
    {
        $service=app(CitaService::class);
        $origen=$service->book($this->appointmentData(),null);
        $origen->update(['estado'=>'Confirmada']);
        $medico=$this->user('Medico',$origen->medico->persona);
        $this->withSession($this->sessionFor($medico))->post('/medico/citas/'.$origen->id_cita.'/derivacion',[
            'id_especialidad_destino'=>$origen->id_especialidad,'prioridad'=>'Prioritaria','motivo_clinico'=>'Seguimiento de aceptación',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $derivacion=$origen->derivaciones()->firstOrFail();
        $datos=$this->appointmentData($origen->paciente,$origen->medico);
        $datos['hora_inicio']='11:00';$datos['id_derivacion']=$derivacion->id_derivacion;
        $this->withSession($this->sessionFor($this->user('Paciente',$origen->paciente->persona)))
            ->post('/citas',$datos)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Programada',$derivacion->fresh()->estado);
        $destino=\App\Models\CitaMedica::findOrFail($derivacion->fresh()->id_cita_destino);
        $this->withSession($this->sessionFor($this->user('Recepcionista')))
            ->post('/agenda/'.$destino->id_cita.'/estado',['estado'=>'Confirmada'])->assertRedirect()->assertSessionHasNoErrors();
        $this->withSession($this->sessionFor($medico))->postJson('/agenda/'.$destino->id_cita.'/estado',['estado'=>'Atendida'])
            ->assertUnprocessable()->assertJsonValidationErrors('estado');
        try {
            Carbon::setTestNow(Carbon::parse($destino->fecha_cita.' '.$destino->hora_inicio)->addMinute());
            $this->post('/agenda/'.$destino->id_cita.'/estado',['estado'=>'Atendida'])->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame('Atendida',$destino->fresh()->estado);
            $this->assertSame('Completada',$derivacion->fresh()->estado);
        } finally { Carbon::setTestNow(); }
    }

    public function test_cu16_totales_separan_monedas_y_excluyen_el_efectivo_devuelto(): void
    {
        $medico=$this->doctor();
        $citas=[];
        foreach(['09:00'=>'bob','10:00'=>'usd','11:00'=>'bob'] as $hora=>$moneda) {
            config(['services.billing.currency'=>$moneda]);
            $datos=$this->appointmentData(null,$medico);$datos['hora_inicio']=$hora;
            $cita=app(CitaService::class)->book($datos,null);
            $pago=app(PagoService::class)->record($cita->id_cita,10000,$moneda,'CU16-'.bin2hex(random_bytes(8)),'Efectivo');
            $citas[]=[$cita,$pago];
        }
        [$cancelada,$devuelto]=$citas[2];
        app(CitaService::class)->cancel($cancelada,'Aceptación de totales');
        $this->withSession($this->sessionFor($this->user('Recepcionista')))
            ->post('/comprobantes/'.$devuelto->id_pago.'/devolucion',['referencia'=>'Devolución CU16'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $url='/recepcion/reportes?'.http_build_query(['tipo'=>'consolidado',
            'desde'=>$cancelada->fecha_cita,'hasta'=>$cancelada->fecha_cita,'id_medico'=>$medico->id_medico]);
        $this->get($url)->assertOk()->assertViewHas('totals',fn($totales)=>
            (int)$totales['total']===3 && (int)$totales['canceladas']===1
            && (float)$totales['recaudado_bob']===100.0 && (float)$totales['recaudado_usd']===100.0);
    }

    public function test_cu17_qbe_filtra_columnas_exporta_y_maneja_resultado_vacio(): void
    {
        $cita=app(CitaService::class)->book($this->appointmentData(),null);
        app(CitaService::class)->book($this->appointmentData(),null);
        $this->withSession($this->sessionFor($this->user()));
        $parametros=['generar'=>1,'campos'=>['codigo','estado','fecha'],'orden'=>'fecha',
            'filtros'=>[['campo'=>'codigo','operador'=>'=','valor'=>$cita->codigo_cita]]];
        $url='/admin/qbe?'.http_build_query($parametros);
        $this->get($url)->assertOk()->assertViewHas('rows',fn($rows)=>count($rows)===1 && $rows[0]->codigo===$cita->codigo_cita)
            ->assertViewHas('columns',fn($columns)=>array_keys($columns)===['codigo','estado','fecha']);
        $this->assertStringStartsWith('%PDF',$this->get($url.'&formato=pdf')->assertOk()->assertDownload('reporte.pdf')->getContent());
        $this->assertStringStartsWith('PK',$this->get($url.'&formato=xlsx')->assertOk()->assertDownload('reporte.xlsx')->streamedContent());
        $csv=$this->get($url.'&formato=csv')->assertOk()->assertDownload('reporte.csv');
        $this->assertStringContainsString($cita->codigo_cita,$csv->streamedContent());
        $parametros['filtros'][0]['valor']='CU17-INEXISTENTE';
        $this->get('/admin/qbe?'.http_build_query($parametros))->assertOk()->assertViewHas('rows',fn($rows)=>count($rows)===0);
        $this->get('/admin/qbe?'.http_build_query($parametros+['formato'=>'pdf']))->assertUnprocessable();
        $this->withSession($this->sessionFor($this->user('Recepcionista')))->get($url)->assertForbidden();
    }
}
