<?php

namespace Tests\Feature;

use App\Services\CitaService;
use Illuminate\Support\Facades\{Artisan,Http};
use Tests\TestCase;

class ReportesAccesoTest extends TestCase
{
    public function test_reporte_por_fecha_incluye_canceladas_y_respeta_filtro_y_exportaciones(): void
    {
        $service=app(CitaService::class);
        $canceled=$service->book($this->appointmentData(),null);
        $service->cancel($canceled,'Prueba de reporte');
        $scheduled=$service->book($this->appointmentData(),null);
        $this->withSession($this->sessionFor($this->user()));
        $url='/admin/reportes?'.http_build_query(['tipo'=>'fecha','desde'=>$canceled->fecha_cita,'hasta'=>$canceled->fecha_cita]);
        $this->get($url)->assertOk()->assertSee($canceled->codigo_cita)->assertSee($scheduled->codigo_cita);
        $this->get($url.'&estado=Cancelada')->assertOk()->assertSee($canceled->codigo_cita)->assertDontSee($scheduled->codigo_cita);
        $pdf=$this->get($url.'&estado=Cancelada&formato=pdf')->assertOk()->assertDownload('reporte.pdf');
        $this->assertStringStartsWith('%PDF',$pdf->getContent());
        $excel=$this->get($url.'&estado=Cancelada&formato=xlsx')->assertOk()->assertDownload('reporte.xlsx');
        $this->assertStringStartsWith('PK',$excel->streamedContent());
    }

    public function test_administrador_y_recepcion_consultan_y_exportan_voz_y_medico_solo_sus_citas(): void
    {
        $this->assertSame(0,Artisan::call('clinica:habilitar-voz-recepcion'));
        $cita=app(CitaService::class)->book($this->appointmentData(),null);
        $other=app(CitaService::class)->book($this->appointmentData(),null);
        config(['services.gemini.key'=>'fake']);
        Http::preventStrayRequests();
        Http::fake(['*'=>Http::response(['candidates'=>[['content'=>['parts'=>[['text'=>json_encode([
            'campos'=>['codigo','estado'],'orden'=>'fecha',
        ])]]]]]])]);
        foreach (['Administrador'=>'admin','Recepcionista'=>'recepcion'] as $role=>$prefix) {
            $user=$this->user($role);
            $this->withSession($this->sessionFor($user))->get('/'.$prefix.'/voz')->assertOk()->assertSee('Reportes por voz');
            $result=$this->postJson('/reportes/voz',['texto'=>'Mostrar citas'])->assertOk();
            $codes=array_column($result->json('rows'),'codigo');
            $this->assertContains($cita->codigo_cita,$codes);
            $this->assertContains($other->codigo_cita,$codes);
            $this->get('/reportes/voz/exportar?formato=pdf')->assertOk()->assertDownload('reporte.pdf');
            $this->get('/reportes/voz/exportar?formato=xlsx')->assertOk()->assertDownload('reporte.xlsx');
        }
        $doctor=$this->user('Medico',$cita->medico->persona);
        $this->withSession($this->sessionFor($doctor))->postJson('/reportes/voz',['texto'=>'Mostrar citas'])
            ->assertOk()->assertJsonCount(1,'rows')->assertJsonPath('rows.0.codigo',$cita->codigo_cita);
        $patient=$this->user('Paciente',$cita->paciente->persona);
        $this->withSession($this->sessionFor($patient))->postJson('/reportes/voz',['texto'=>'Mostrar citas'])->assertForbidden();
        $this->get('/reportes/voz/exportar?formato=pdf')->assertForbidden();
    }

    public function test_revocar_voz_a_recepcion_bloquea_consulta_exportacion_y_pantalla(): void
    {
        Artisan::call('clinica:habilitar-voz-recepcion');
        $user=$this->user('Recepcionista');
        $this->withSession($this->sessionFor($user))->get('/recepcion/voz')->assertOk();
        $user->rol->permisos()->detach(\App\Models\Permiso::where('nombre_permiso','voz')->value('id_permiso'));
        $this->get('/recepcion/voz')->assertForbidden();
        $this->postJson('/reportes/voz',['texto'=>'Mostrar citas'])->assertForbidden();
        $this->get('/reportes/voz/exportar?formato=pdf')->assertForbidden();
    }
}
