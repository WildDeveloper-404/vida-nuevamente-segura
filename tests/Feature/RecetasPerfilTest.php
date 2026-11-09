<?php

namespace Tests\Feature;

use App\Services\{CitaService,RecetaService};
use Tests\TestCase;

class RecetasPerfilTest extends TestCase
{
    public function test_perfil_muestra_recetas_propias_y_pdf_sin_qr_y_aisla_accesos(): void
    {
        $paciente=$this->patient();
        $medico=$this->doctor();
        $recetas=[];
        foreach([[$paciente,$medico,'Receta propia'],[$paciente,$this->doctor(),'Otro profesional'],[$this->patient(),$medico,'Paciente ajeno']] as [$p,$m,$diagnostico]) {
            $datos=$this->appointmentData($p,$m);
            $datos['hora_inicio']=sprintf('%02d:00',9+count($recetas));
            $cita=app(CitaService::class)->book($datos,null);
            $cita->update(['estado'=>'Confirmada']);$cita->update(['estado'=>'Atendida']);
            $recetas[]=app(RecetaService::class)->emitir($cita,['cie10'=>'Z00.0','diagnostico'=>$diagnostico,
                'items'=>[['tipo_item'=>'Examen_Laboratorio','medicamento_o_examen'=>'Hemograma de control']]]);
        }
        $this->withSession($this->sessionFor($this->user('Paciente',$paciente->persona)))
            ->get('/perfil/'.$paciente->id_paciente)->assertOk()->assertSee('Receta propia')
            ->assertSee('Otro profesional')->assertSee('Hemograma de control')->assertSee('Descargar receta PDF')
            ->assertDontSee('Paciente ajeno')->assertViewHas('recetas',fn($rows)=>$rows->total()===2);
        $pdf=$this->get('/recetas/'.$recetas[0]->id_receta.'/pdf')->assertOk();
        $this->assertStringStartsWith('%PDF',$pdf->getContent());
        $html=view('recetas.pdf',['receta'=>$recetas[0]->load('detalles','cita.paciente.persona','cita.medico.persona','cita.especialidad')])->render();
        $this->assertStringNotContainsString('Escanee',$html);
        $this->assertStringNotContainsString('Código QR',$html);
        $this->get('/recetas/'.$recetas[2]->id_receta.'/pdf')->assertForbidden();
        $this->withSession($this->sessionFor($this->user('Medico',$medico->persona)))
            ->get('/perfil/'.$paciente->id_paciente)->assertOk()->assertSee('Receta propia')->assertDontSee('Otro profesional')
            ->assertViewHas('recetas',fn($rows)=>$rows->total()===1);
        $this->withSession($this->sessionFor($this->user('Recepcionista')))->get('/perfil/'.$paciente->id_paciente)->assertForbidden();
    }

    public function test_perfil_sin_recetas_informa_sin_error(): void
    {
        $paciente=$this->patient();
        $this->withSession($this->sessionFor($this->user('Paciente',$paciente->persona)))
            ->get('/perfil/'.$paciente->id_paciente)->assertOk()->assertSee('Todavía no hay recetas emitidas para mostrar.');
    }
}
