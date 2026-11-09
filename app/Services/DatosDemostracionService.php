<?php

namespace App\Services;

use App\Models\{Persona,Paciente,PerfilClinico,Medico,Especialidad,Consultorio,HorarioAtencion,CitaMedica,NotificacionCita,DerivacionMedica};
use App\Services\{PagoService,RecetaService};
use Illuminate\Support\Facades\DB;

class DatosDemostracionService
{
    public function run(): array
    {
        return DB::transaction(function() {
            DB::select('SELECT pg_advisory_xact_lock(180020)');
            DB::selectOne("SELECT set_config('TimeZone', ?, true)",[config('app.timezone')]);
            $patients=[];
            foreach(['Ana','Luis','María','Carlos','Elena','Pedro','Sofía','Andrés','Valeria','Diego','Carmen','Gabriel','Paula','José','Isabel','Javier','Rosa','Mateo'] as $i=>$nombre) {
                $person=Persona::firstOrCreate(['ci'=>'DEMO-RPT-P'.($i+1)],['nombres'=>$nombre,'apellidos'=>'Reporte Simulado '.($i+1),'telefono'=>'0000000000']);
                $p=Paciente::firstOrCreate(['id_paciente'=>$person->id_persona],['codigo_paciente'=>'PAC-RPT-'.($i+1),'sexo'=>$i%2?'M':'F','fecha_nacimiento'=>(1955+$i*2).'-06-15']);
                PerfilClinico::firstOrCreate(['id_paciente'=>$p->id_paciente],['tipo_sangre'=>$i%2?'O+':'A+','alergias'=>'Ninguna','enfermedades_base'=>'Sin antecedentes (simulado)','medicamentos_habituales'=>'Ninguno','antecedentes_relevantes'=>'Datos ficticios para reportes y presentación.']);
                $patients[]=$p;
            }
            $lucia=Paciente::where('codigo_paciente','PAC-VNS-DEMO')->first();
            if($lucia) $patients[0]=$lucia;
            $doctors=[];
            foreach(['Medicina General','Cardiología','Oftalmología','Traumatología'] as $i=>$especialidad) {
                $medico=$i===0?Medico::where('codigo_medico','MED-VNS-DEMO')->first():null;
                if(!$medico) {
                    $person=Persona::firstOrCreate(['ci'=>'DEMO-RPT-M'.$i],['nombres'=>['Daniel','Camila','Martín','Fernanda'][$i],'apellidos'=>'Médico Simulado','telefono'=>'0000000000']);
                    $medico=Medico::firstOrCreate(['id_medico'=>$person->id_persona],['codigo_medico'=>'MED-RPT-'.$i,'nro_matricula'=>'MAT-RPT-'.$i]);
                    $room=Consultorio::firstOrCreate(['numero'=>'RPT-'.$i],['bloque'=>'Demostración','piso'=>1]);
                    foreach(['Lunes','Martes','Miercoles','Jueves','Viernes','Sabado','Domingo'] as $day) {
                        HorarioAtencion::firstOrCreate(['id_medico'=>$medico->id_medico,'dia_semana'=>$day,'hora_inicio'=>'09:00:00'],['hora_fin'=>'12:00','duracion_minutos'=>30,'cupo_maximo'=>6,'id_consultorio'=>$room->id_consultorio]);
                    }
                }
                $e=Especialidad::where('nombre',$especialidad)->where('estado','Activo')->firstOrFail();
                $medico->especialidades()->syncWithoutDetaching([$e->id_especialidad]);
                $doctors[]=[$medico,$e];
            }
            foreach($doctors as $i=>[$medico,$especialidad]) {
                for($j=0;$j<12;$j++) {
                    $code='DEMO-RPT-'.$i.'-'.str_pad((string)$j,2,'0',STR_PAD_LEFT);
                    if(CitaMedica::where('codigo_cita',$code)->exists()) continue;
                    $historica=$j<6;
                    if($historica && now()->lt(today()->setTime(12,0))) throw new \RuntimeException('La carga de atenciones de hoy requiere ejecutarse después de las 12:00. No se modificaron datos.');
                    $date=$historica?today():today()->addDays([1,3,7,12,20,35][$j-6]);
                    $hora=sprintf('%02d:%02d',9+intdiv($j%6,2),($j%2)*30);
                    $patient=$patients[($j+$i*3)%count($patients)];
                    $c=CitaMedica::create(['codigo_cita'=>$code,'id_paciente'=>$patient->id_paciente,'id_medico'=>$medico->id_medico,
                        'id_especialidad'=>$especialidad->id_especialidad,'fecha_cita'=>$date->toDateString(),'hora_inicio'=>$hora,
                        'hora_fin'=>\Carbon\Carbon::parse($hora)->addMinutes(30)->format('H:i'),'motivo_consulta'=>'DEMOSTRACIÓN: consulta simulada para reportes']);
                    $estado=$historica?($j===4?'Inasistencia':($j===5?'Cancelada':'Atendida')):($j%3===0?'Cancelada':($j%2?'Confirmada':'Programada'));
                    if(in_array($estado,['Atendida','Confirmada'],true)) {
                        $c->refresh();
                        app(PagoService::class)->record($c->id_cita,PagoService::cents($c->arancel_reservado),$c->moneda_reservada,'DEMO-CAJA-'.$code,'Efectivo');
                        $c->refresh();
                    }
                    if($estado==='Atendida') {
                        if($c->estado==='Programada') $c->update(['estado'=>'Confirmada']);
                        $c->update(['estado'=>'Atendida']);
                        app(RecetaService::class)->emitir($c,['cie10'=>'Z00.0','diagnostico'=>'Control de demostración. Información clínica ficticia.',
                            'items'=>[['tipo_item'=>'Examen_Laboratorio','medicamento_o_examen'=>'Hemograma (orden de demostración)']]]);
                        if($j===0) DerivacionMedica::create(['codigo_derivacion'=>'DER-RPT-'.$i,'id_cita_origen'=>$c->id_cita,
                            'id_especialidad_destino'=>$doctors[($i+1)%4][1]->id_especialidad,'prioridad'=>'Rutinaria','motivo_clinico'=>'Derivación ficticia para presentación.']);
                    } elseif($estado!==$c->estado) {
                        $c->update(['estado'=>$estado]+($estado==='Cancelada'?['motivo_cancelacion'=>'Cancelación simulada de demostración']:[]));
                    }
                }
            }
            // No despachar recordatorios a los buzones ficticios de esta carga.
            NotificacionCita::whereIn('id_cita',CitaMedica::where('codigo_cita','like','DEMO-RPT-%')->select('id_cita'))
                ->where('estado_envio','Pendiente')->update(['estado_envio'=>'Anulado']);
            return ['pacientes_simulados'=>18,'medicos_en_reportes'=>4,
                'citas'=>CitaMedica::where('codigo_cita','like','DEMO-RPT-%')->count(),
                'estados'=>CitaMedica::where('codigo_cita','like','DEMO-RPT-%')->selectRaw('estado,count(*) AS total')->groupBy('estado')->pluck('total','estado')->all(),
                'recetas'=>DB::table('receta_medica')->join('cita_medica','cita_medica.id_cita','=','receta_medica.id_cita')->where('codigo_cita','like','DEMO-RPT-%')->count(),
                'pagos_simulados'=>DB::table('pago')->where('referencia_stripe','like','DEMO-CAJA-DEMO-RPT-%')->count()];
        });
    }
}
