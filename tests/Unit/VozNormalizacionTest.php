<?php

namespace Tests\Unit;

use App\Services\VozService;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class VozNormalizacionTest extends TestCase
{
    public function test_normaliza_estados_y_moneda_sin_modificar_fechas(): void
    {
        config(['services.gemini.key'=>'fake']);
        $plan = [
            'campos'=>['codigo','fecha'],
            'orden'=>'fecha',
            'filtros'=>[
                ['campo'=>'estado','operador'=>'=','valor'=>' CONFIRMADA '],
                ['campo'=>'estado_pago','operador'=>'=','valor'=>'pagado'],
                ['campo'=>'estado_devolucion','operador'=>'!=','valor'=>'DEVUELTO'],
                ['campo'=>'moneda','operador'=>'=','valor'=>'BOB'],
                ['campo'=>'fecha','operador'=>'>=','valor'=>'2026-11-01'],
            ],
        ];
        Http::preventStrayRequests();
        Http::fake(['*'=>Http::response([
            'candidates'=>[['content'=>['parts'=>[['text'=>json_encode($plan)]]]]],
        ])]);

        $result = app(VozService::class)->plan('Mostrar citas confirmadas');

        $this->assertSame(['Confirmada','Pagado','Devuelto','bob','2026-11-01'],
            array_column($result['filtros'],'valor'));
        Http::assertSentCount(1);
    }

    public function test_normaliza_plural_y_genero_de_estados_sin_permitir_valores_arbitrarios(): void
    {
        config(['services.gemini.key'=>'fake']);
        $filters = [
            ['campo'=>'estado','operador'=>'=','valor'=>'canceladas'],
            ['campo'=>'estado','operador'=>'!=','valor'=>'CONFIRMADOS'],
            ['campo'=>'estado','operador'=>'=','valor'=>'atendido'],
            ['campo'=>'estado_pago','operador'=>'=','valor'=>'pendientes'],
            ['campo'=>'estado_devolucion','operador'=>'=','valor'=>'devueltos'],
        ];
        Http::preventStrayRequests();
        Http::fake(['*'=>Http::response([
            'candidates'=>[['content'=>['parts'=>[['text'=>json_encode([
                'campos'=>['codigo'], 'orden'=>'fecha', 'filtros'=>$filters,
            ])]]]]],
        ])]);
        $result = app(VozService::class)->plan('Mostrar citas canceladas');
        $this->assertSame(['Cancelada','Confirmada','Atendida','Pendiente','Devuelto'], array_column($result['filtros'],'valor'));
    }

    public function test_rechaza_estados_inventados_por_el_modelo(): void
    {
        config(['services.gemini.key'=>'fake']);
        Http::preventStrayRequests();
        Http::fake(['*'=>Http::response([
            'candidates'=>[['content'=>['parts'=>[['text'=>json_encode([
                'campos'=>['codigo'],
                'orden'=>'fecha',
                'filtros'=>[['campo'=>'estado','operador'=>'=','valor'=>'Inventada']],
            ])]]]]],
        ])]);

        $this->expectException(ValidationException::class);
        app(VozService::class)->plan('Mostrar citas');
    }
}
