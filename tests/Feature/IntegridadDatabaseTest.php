<?php

namespace Tests\Feature;

use App\Services\CitaService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IntegridadDatabaseTest extends TestCase
{
    public function test_actualizacion_repetida_preserva_la_reserva_y_su_moneda(): void
    {
        $cita = app(CitaService::class)->book($this->appointmentData(), null);
        $before = $cita->getAttributes();
        for ($i = 0; $i < 2; $i++) {
            $this->assertSame(0, Artisan::call('clinica:compatibilidad', ['--moneda-anterior'=>'bob']));
        }
        $this->assertSame($before, $cita->fresh()->getAttributes());
    }

    public function test_exclusion_de_citas_protege_incluso_sin_el_validador_plpgsql(): void
    {
        $cita = app(CitaService::class)->book($this->appointmentData(), null);
        $this->assertExclusion('cita_medico_exclusivo', function () use ($cita) {
            // Simula una escritura fuera del servicio sin depender del SELECT del trigger.
            // El savepoint revierte también la desactivación al rechazar la colisión.
            DB::statement('ALTER TABLE cita_medica DISABLE TRIGGER trg_validar_cita_medica');
            DB::table('cita_medica')->insert([
                'codigo_cita'=>'SQL-'.bin2hex(random_bytes(5)),
                'id_paciente'=>$cita->id_paciente, 'id_medico'=>$cita->id_medico,
                'id_especialidad'=>$cita->id_especialidad, 'fecha_cita'=>$cita->fecha_cita,
                'hora_inicio'=>'09:15', 'hora_fin'=>'09:45', 'motivo_consulta'=>'Prueba SQL',
                'arancel_reservado'=>$cita->arancel_reservado, 'moneda_reservada'=>$cita->moneda_reservada,
            ]);
        });
    }

    public function test_exclusion_de_turnos_protege_incluso_sin_el_validador_plpgsql(): void
    {
        $doctor = $this->doctor();
        $this->assertExclusion('horario_medico_exclusivo', function () use ($doctor) {
            DB::statement('ALTER TABLE horario_atencion DISABLE TRIGGER trg_validar_horario_medico');
            DB::table('horario_atencion')->insert([
                'id_medico'=>$doctor->id_medico, 'dia_semana'=>'Lunes',
                'hora_inicio'=>'09:15', 'hora_fin'=>'10:15', 'duracion_minutos'=>30, 'cupo_maximo'=>2,
            ]);
        });
    }

    private function assertExclusion(string $constraint, callable $write): void
    {
        try {
            DB::transaction($write);
            $this->fail('Se permitió una superposición.');
        } catch (QueryException $e) {
            $this->assertSame('23P01', $e->errorInfo[0]);
            $this->assertStringContainsString($constraint, $e->getMessage());
        }
    }
}
