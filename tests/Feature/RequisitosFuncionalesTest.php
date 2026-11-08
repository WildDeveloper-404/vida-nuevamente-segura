<?php

namespace Tests\Feature;

use App\Models\Consultorio;
use App\Models\HorarioAtencion;
use App\Models\NotificacionCita;
use App\Services\CitaService;
use App\Services\DevolucionService;
use App\Services\PagoService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

class RequisitosFuncionalesTest extends TestCase
{
    public function test_paciente_sin_cuenta_conserva_correo_y_recibe_avisos_de_reserva(): void
    {
        $patient = $this->patient();
        $patient->update(['correo_contacto'=>'contacto@example.test']);
        $this->assertSame('contacto@example.test', $patient->fresh()->correo_contacto);
        $cita = app(CitaService::class)->book($this->appointmentData($patient), null);
        $this->assertSame(2, NotificacionCita::where('id_cita',$cita->id_cita)
            ->where('correo_destino','contacto@example.test')->where('estado_envio','Pendiente')->count());
    }

    public function test_paciente_actualiza_contacto_y_solo_medico_asignado_edita_alergias(): void
    {
        $cita = app(CitaService::class)->book($this->appointmentData(), null);
        $paciente = $cita->paciente;
        $user = $this->user('Paciente', $paciente->persona);
        $url = '/perfil/'.$paciente->id_paciente;
        $this->withSession($this->sessionFor($user))->put($url, ['alergias'=>'Alterada'])->assertForbidden();
        $this->put($url, ['telefono'=>'12345678','contacto_emergencia_nombre'=>'Familiar'])->assertSessionHasNoErrors();
        $this->assertSame('12345678', $paciente->persona->fresh()->telefono);
        $this->assertSame('Ninguna conocida', $paciente->perfilClinico->alergias);

        $medico = $this->user('Medico', $cita->medico->persona);
        $this->withSession($this->sessionFor($medico))->put($url, [
            'alergias'=>'Alergia de prueba','enfermedades_base'=>'Ninguna','medicamentos_habituales'=>'Ninguno',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Alergia de prueba', $paciente->perfilClinico->fresh()->alergias);
    }

    public function test_caja_cobra_el_arancel_pactado_y_registra_su_devolucion(): void
    {
        $cita = app(CitaService::class)->book($this->appointmentData(), null);
        $cita->especialidad()->update(['arancel_consulta'=>135]);
        $this->assertSame('100.00', $cita->fresh()->arancel_reservado);
        $this->withSession($this->sessionFor($this->user('Recepcionista')));
        $this->get('/pagos/'.$cita->id_cita)->assertOk()->assertSee('Registrar efectivo en caja')->assertDontSee('QR bancario');
        $cash = ['monto'=>'100.00','referencia'=>(string) \Illuminate\Support\Str::uuid()];
        $this->post('/pagos/'.$cita->id_cita.'/presencial', $cash + ['metodo'=>'QR Manual'])->assertSessionHasErrors('metodo');
        $this->assertFalse($cita->pago()->exists());
        $this->post('/pagos/'.$cita->id_cita.'/presencial', $cash)->assertSessionHasNoErrors();
        $pago = $cita->pago()->firstOrFail();
        $this->assertSame('Efectivo', $pago->metodo_pago);
        $this->assertStringStartsWith('REC-01-', $pago->comprobante->nro_comprobante);
        $this->post('/citas/'.$cita->id_cita.'/cancelar', ['motivo'=>'Prueba'])->assertSessionHasNoErrors();
        $this->assertSame('Pendiente', $pago->devolucion()->firstOrFail()->estado);
        $this->post('/comprobantes/'.$pago->id_pago.'/devolucion', ['referencia'=>'Caja de prueba'])->assertSessionHasNoErrors();
        $this->assertSame('Devuelto', $pago->devolucion()->firstOrFail()->estado);
    }

    public function test_limite_de_30_minutos_y_recordatorio_programado(): void
    {
        $data = $this->appointmentData();
        $patient = \App\Models\Paciente::findOrFail($data['id_paciente']);
        $user = $this->user('Paciente', $patient->persona);
        $cita = app(CitaService::class)->book($data, null);
        $start = Carbon::parse($cita->fecha_cita.' '.$cita->hora_inicio);
        $reminder = NotificacionCita::where('id_cita', $cita->id_cita)->where('tipo', 'Recordatorio')->sole();
        $this->assertEquals($start->copy()->subDay(), Carbon::parse($reminder->enviar_desde));

        $this->withSession($this->sessionFor($user));
        $this->travelTo($start->copy()->subMinutes(29), function () use ($cita) {
            $this->post('/citas/'.$cita->id_cita.'/cancelar', ['motivo'=>'Prueba'])->assertSessionHasErrors('estado');
        });
        $this->assertSame('Programada', $cita->fresh()->estado);
        $this->travelTo($start->copy()->subMinutes(30), function () use ($cita) {
            $this->post('/citas/'.$cita->id_cita.'/cancelar', ['motivo'=>'Prueba'])->assertSessionHasNoErrors();
        });
        $this->assertSame('Anulado', $reminder->fresh()->estado_envio);
    }

    public function test_postgresql_rechaza_turnos_solapados_en_el_mismo_consultorio(): void
    {
        $room = Consultorio::create(['numero'=>'TEST-RF08','piso'=>2]);
        $one = $this->doctor();
        $two = $this->doctor();
        $schedule = fn ($doctor) => HorarioAtencion::where('id_medico',$doctor->id_medico)->where('dia_semana','Lunes')->firstOrFail();
        $schedule($one)->update(['id_consultorio'=>$room->id_consultorio]);
        try {
            DB::transaction(fn () => $schedule($two)->update(['id_consultorio'=>$room->id_consultorio]));
            $this->fail('Se asignó un mismo consultorio a dos médicos en el mismo horario.');
        } catch (QueryException $e) {
            $this->assertSame('23P01', $e->errorInfo[0]);
        }
        $this->assertNull($schedule($two)->id_consultorio);
    }

    public function test_catalogo_impide_variantes_foneticas_de_especialidades_existentes(): void
    {
        $this->withSession($this->sessionFor($this->user()))->post('/admin/especialidades', [
            'nombre'=>'Kardiología','edad_minima'=>14,'edad_maxima'=>120,'arancel_consulta'=>100,'estado'=>'Activo',
        ])->assertSessionHasErrors('db_error');
        $this->assertDatabaseMissing('especialidad', ['nombre'=>'Kardiología']);
    }

    public function test_despacho_respeta_ventana_y_no_repite_recordatorio(): void
    {
        $data = $this->appointmentData();
        $user = $this->user('Paciente', \App\Models\Paciente::findOrFail($data['id_paciente'])->persona);
        $cita = app(CitaService::class)->book($data, null);
        // Enviar únicamente los mensajes de esta cita en la base de pruebas.
        NotificacionCita::where('id_cita', '<>', $cita->id_cita)->where('estado_envio','Pendiente')->update(['estado_envio'=>'Anulado']);
        $reminder = $cita->id_cita;
        $start = Carbon::parse($cita->fecha_cita.' '.$cita->hora_inicio);
        $this->travelTo($start->copy()->subDay()->subMinute(), function () {
            $this->artisan('clinica:notificaciones')->assertSuccessful();
        });
        $query = NotificacionCita::where('id_cita',$reminder)->where('tipo','Recordatorio');
        $this->assertSame('Pendiente',$query->sole()->estado_envio);
        $this->travelTo($start->copy()->subDay(), function () {
            $this->artisan('clinica:notificaciones')->assertSuccessful();
            $this->artisan('clinica:notificaciones')->assertSuccessful();
        });
        $this->assertSame('Enviado',$query->sole()->estado_envio);
        $messages = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages->filter(fn ($message) => str_contains($message->getOriginalMessage()->getTextBody(), 'Recordatorio')));
        $this->assertSame($user->correo, $messages->last()->getEnvelope()->getRecipients()[0]->getAddress());
    }

    public function test_solicitud_de_devolucion_stripe_se_procesa_una_vez(): void
    {
        config(['services.stripe.secret'=>'sk_test_rf']);
        $cita = app(CitaService::class)->book($this->appointmentData(), null);
        $pago = app(PagoService::class)->record($cita->id_cita, 10000, 'usd', 'pi_test_rf');
        app(CitaService::class)->cancel($cita, 'Cancelación de prueba');
        $mock = \Mockery::mock(ClientInterface::class);
        $mock->shouldReceive('request')->once()->withArgs(
            fn ($method, $url, $headers, $params) => $method === 'post' && str_ends_with($url, '/v1/refunds')
                && $params['payment_intent'] === 'pi_test_rf' && $params['amount'] === 10000
                && in_array('Idempotency-Key: clinica-refund-pi_test_rf', $headers, true)
        )->andReturn(['{"id":"re_test_rf","object":"refund","status":"succeeded"}', 200, []]);
        ApiRequestor::setHttpClient($mock);
        try {
            $service = app(DevolucionService::class);
            $service->processStripe();
            $service->processStripe();
        } finally {
            ApiRequestor::setHttpClient(null);
        }
        $this->assertSame('Devuelto',$pago->devolucion()->sole()->estado);
        $this->assertSame('re_test_rf',$pago->devolucion()->sole()->referencia);
    }

    public function test_checkout_solicita_solo_tarjeta_en_la_moneda_de_la_reserva(): void
    {
        config(['services.stripe.secret'=>'sk_test_rf','services.stripe.currency'=>'bob','services.billing.currency'=>'bob']);
        $cita = app(CitaService::class)->book($this->appointmentData(), null);
        $user = $this->user('Paciente', $cita->paciente->persona);
        $mock = \Mockery::mock(ClientInterface::class);
        $mock->shouldReceive('request')->once()->withArgs(
            fn ($method, $url, $headers, $params) => $method === 'post' && str_ends_with($url, '/v1/checkout/sessions')
                && $params['payment_method_types'] === ['card']
                && $params['line_items'][0]['price_data']['currency'] === 'bob'
                && $params['line_items'][0]['price_data']['unit_amount'] === 10000
        )->andReturn(['{"id":"cs_test_rf","object":"checkout.session","url":"https://checkout.stripe.com/pay/cs_test_rf"}', 200, []]);
        ApiRequestor::setHttpClient($mock);
        try {
            $this->withSession($this->sessionFor($user))->post('/pagos/'.$cita->id_cita.'/stripe')
                ->assertRedirect('https://checkout.stripe.com/pay/cs_test_rf');
        } finally {
            ApiRequestor::setHttpClient(null);
        }
        $this->assertFalse($cita->pago()->exists());
    }

    public function test_postgresql_rechaza_nuevos_pagos_con_metodos_retirados(): void
    {
        $cita = app(CitaService::class)->book($this->appointmentData(), null);
        try {
            DB::transaction(fn () => DB::table('pago')->insert([
                'id_cita'=>$cita->id_cita,'monto'=>100,'moneda'=>'usd','metodo_pago'=>'QR Manual',
                'referencia_stripe'=>'qr-rechazado-prueba',
            ]));
            $this->fail('PostgreSQL permitió registrar un nuevo pago por QR.');
        } catch (QueryException $e) {
            $this->assertSame('P0001', $e->errorInfo[0]);
        }
        $this->assertFalse($cita->pago()->exists());
    }

    public function test_bitacora_registra_actor_y_diferencial_y_rechaza_su_edicion(): void
    {
        $cita = app(CitaService::class)->book($this->appointmentData(), null);
        $user = $this->user('Recepcionista');
        $this->withSession($this->sessionFor($user))->post('/agenda/'.$cita->id_cita.'/estado', [
            'estado'=>'Confirmada',
        ])->assertSessionHasNoErrors();
        $log = DB::table('bitacora')->where('id_usuario',$user->id_usuario)->where('modulo','cita_medica')
            ->where('accion','UPDATE')->sole();
        $this->assertSame(['estado'=>'Programada'], json_decode($log->valores_anteriores, true));
        $this->assertSame(['estado'=>'Confirmada'], json_decode($log->valores_nuevos, true));
        try {
            DB::transaction(fn () => DB::table('bitacora')->where('id_bitacora',$log->id_bitacora)->update(['descripcion'=>'Alterado']));
            $this->fail('Se alteró la bitácora.');
        } catch (QueryException $e) {
            $this->assertSame('P0001', $e->errorInfo[0]);
        }
    }

    public function test_paciente_afectado_puede_solicitar_devolucion_del_traslado(): void
    {
        $data = $this->appointmentData();
        $user = $this->user('Paciente', \App\Models\Paciente::findOrFail($data['id_paciente'])->persona);
        $cita = app(CitaService::class)->book($data, null);
        $pago = app(PagoService::class)->record($cita->id_cita, 10000, 'usd', 'pi_traslado_rf');
        $this->withSession($this->sessionFor($this->user()))->post('/contingencias', [
            'id_medico'=>$data['id_medico'],'fecha'=>$data['fecha_cita'],'hora_inicio'=>'08:00','hora_fin'=>'10:00',
            'motivo'=>'Emergencia de prueba',
        ])->assertSessionHasNoErrors();
        $notice = $cita->contingencia()->firstOrFail();
        $this->withSession($this->sessionFor($user))->get('/paciente/citas')->assertOk()->assertSee('Aceptar nueva fecha');
        $this->post('/citas/'.$cita->id_cita.'/contingencia/'.$notice->id_notificacion, [
            'respuesta'=>'Solicita devolución',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Cancelada', $cita->fresh()->estado);
        $this->assertSame('Pendiente', $pago->devolucion()->firstOrFail()->estado);
        $this->assertSame('Solicita devolución', $notice->fresh()->respuesta);
    }
}
