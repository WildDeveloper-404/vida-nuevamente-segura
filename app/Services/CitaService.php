<?php
namespace App\Services;
use App\Models\{CitaMedica,Medico,Paciente,Especialidad,DerivacionMedica,NotificacionCita};
use App\Support\Audit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class CitaService
{
    public function slots(int $doctor,string $date): array
    {
        return DB::select('SELECT hora_inicio_slot AS hora_inicio, hora_fin_slot AS hora_fin, estado_slot AS estado FROM fn_obtener_bloques_disponibles(?::int, ?::date)',[$doctor,$date]);
    }
    public function book(array $d,?int $operator): CitaMedica
    {
        return DB::transaction(function() use($d,$operator) {
            Medico::whereKey($d['id_medico'])->lockForUpdate()->firstOrFail();
            $patient=Paciente::whereKey($d['id_paciente'])->lockForUpdate()->firstOrFail();
            $specialty=Especialidad::findOrFail($d['id_especialidad']);
            $age=(int)Carbon::parse($patient->fecha_nacimiento)->diffInYears(Carbon::parse($d['fecha_cita']));
            if($patient->estado!=='Activo' || $specialty->estado!=='Activo' || $age<$specialty->edad_minima || $age>$specialty->edad_maxima) throw ValidationException::withMessages(['id_especialidad'=>'El paciente no cumple el rango de edad o el registro está inactivo.']);
            if(Carbon::parse($d['fecha_cita'].' '.$d['hora_inicio'])->isPast()) throw ValidationException::withMessages(['fecha_cita'=>'Seleccione una fecha y hora futuras.']);
            $slot=collect($this->slots($d['id_medico'],$d['fecha_cita']))->first(fn($s)=>substr($s->hora_inicio,0,5)===substr($d['hora_inicio'],0,5) && $s->estado==='Disponible');
            if(!$slot) throw ValidationException::withMessages(['hora_inicio'=>'El bloque seleccionado ya no está disponible.']);
            DB::selectOne("SELECT set_config('clinica.moneda', ?, true)", [config('services.billing.currency')]);
            $result=DB::select('CALL sp_programar_cita(?::int, ?::int, ?::int, ?::date, ?::time, ?::varchar, ?::int, NULL, NULL)',[$d['id_paciente'],$d['id_medico'],$d['id_especialidad'],$d['fecha_cita'],$d['hora_inicio'],$d['motivo_consulta'],$operator])[0];
            $c=CitaMedica::findOrFail($result->p_id_cita_creada);
            if(!empty($d['id_derivacion'])) {
                $der=DerivacionMedica::whereKey($d['id_derivacion'])->lockForUpdate()->firstOrFail();
                $origin=CitaMedica::findOrFail($der->id_cita_origen);
                if($origin->id_paciente!=$c->id_paciente || $der->id_especialidad_destino!=$c->id_especialidad || $der->estado!=='Pendiente de programar') throw ValidationException::withMessages(['derivacion'=>'La derivación no corresponde a esta reserva.']);
                $der->update(['estado'=>'Programada','id_cita_destino'=>$c->id_cita]);
            }
            return $c;
        });
    }
    public function cancel(CitaMedica $c,string $reason): void
    {
        DB::transaction(function() use($c,$reason) {
            Medico::whereKey($c->id_medico)->lockForUpdate()->firstOrFail();
            $c=CitaMedica::whereKey($c->id_cita)->lockForUpdate()->firstOrFail();
            $this->requireVoluntaryChange($c);
            $this->markCancelled($c, $reason);
            $this->queueRefund($c);
        });
    }

    private function requireVoluntaryChange(CitaMedica $c): void
    {
        if (! $c->permiteCambioVoluntario()) {
            throw ValidationException::withMessages(['estado'=>'Se requieren al menos 30 minutos de antelación y una cita programada o confirmada.']);
        }
    }

    private function markCancelled(CitaMedica $c, string $reason): void
    {
        $c->update(['estado'=>'Cancelada','motivo_cancelacion'=>$reason]);
        Audit::log('Citas','CANCELACION','Cita '.$c->codigo_cita);
        $email = $c->paciente->persona->usuario?->correo ?? $c->paciente->correo_contacto;
        if ($email) {
            NotificacionCita::create(['id_cita'=>$c->id_cita,'tipo'=>'Cancelacion','mensaje'=>'Se canceló su cita '.$c->codigo_cita.'.','correo_destino'=>$email,'estado_envio'=>'Pendiente']);
        }
    }

    private function queueRefund(CitaMedica $c): void
    {
        if ($payment = $c->pago()->where('estado_pago', 'Pagado')->first()) {
            app(DevolucionService::class)->queue($payment);
        }
        DerivacionMedica::where('id_cita_destino', $c->id_cita)->where('estado', 'Programada')
            ->update(['estado'=>'Pendiente de programar','id_cita_destino'=>null]);
    }

    public function answerContingency(CitaMedica $c, int $noticeId, string $answer): void
    {
        DB::transaction(function () use ($c, $noticeId, $answer) {
            Medico::whereKey($c->id_medico)->lockForUpdate()->firstOrFail();
            $c = CitaMedica::whereKey($c->id_cita)->lockForUpdate()->firstOrFail();
            $notice = $c->contingencia()->first();
            if (!$notice || (int) $notice->id_notificacion !== $noticeId || $notice->respuesta !== null
                || !in_array($c->estado, ['Programada','Confirmada'], true)
                || Carbon::parse($c->fecha_cita.' '.$c->hora_inicio)->isPast()) {
                throw ValidationException::withMessages(['estado'=>'El traslado ya no está disponible para responder.']);
            }
            if ($answer === 'Solicita devolución') {
                if (!$c->pago()->where('estado_pago', 'Pagado')->exists()) {
                    throw ValidationException::withMessages(['pago'=>'La cita no tiene un pago para devolver.']);
                }
                $this->markCancelled($c, 'Devolución solicitada por traslado de emergencia.');
                $this->queueRefund($c);
            }
            $notice->update(['respuesta'=>$answer,'fecha_respuesta'=>now()]);
        });
    }
    public function reschedule(CitaMedica $c,array $d,?int $operator): CitaMedica
    {
        return DB::transaction(function() use($c,$d,$operator) {
            Medico::whereKey($c->id_medico)->lockForUpdate()->firstOrFail();
            $c=CitaMedica::whereKey($c->id_cita)->lockForUpdate()->firstOrFail();
            $this->requireVoluntaryChange($c);
            $this->markCancelled($c,'Reprogramada: '.$d['motivo']);
            $new=$this->book(['id_paciente'=>$c->id_paciente,'id_medico'=>$c->id_medico,'id_especialidad'=>$c->id_especialidad,'fecha_cita'=>$d['fecha_cita'],'hora_inicio'=>$d['hora_inicio'],'motivo_consulta'=>$c->motivo_consulta],$operator);
            $new->update(['id_cita_anterior'=>$c->id_cita]);
            DerivacionMedica::where('id_cita_destino', $c->id_cita)->where('estado', 'Programada')
                ->update(['id_cita_destino'=>$new->id_cita]);
            if($payment=$c->pago()->first()) {
                $payment->update(['id_cita'=>$new->id_cita]);
                Audit::log('Pagos','PAGO_TRASLADADO','Pago '.$payment->id_pago.' de '.$c->codigo_cita.' a '.$new->codigo_cita);
            }
            Audit::log('Citas','REPROGRAMACION',$c->codigo_cita.' → '.$new->codigo_cita);
            return $new->refresh();
        });
    }
}
