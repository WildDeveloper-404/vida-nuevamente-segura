<?php

namespace Tests\Feature;

use App\Models\Especialidad;
use Tests\TestCase;

class ChatbotOrientationTest extends TestCase
{
    public function test_offers_guided_choices_and_uses_the_active_catalog(): void
    {
        $this->get('/login')->assertOk()->assertSee('Ayúdame a elegir una especialidad');

        $this->postJson('/api/chatbot/mensaje', ['mensaje' => 'Ayúdame a elegir una especialidad'])
            ->assertOk()->assertJsonPath('opciones.0', 'Consulta dental');

        $result = $this->postJson('/api/chatbot/mensaje', ['mensaje' => 'Consulta dental'])
            ->assertOk()->assertJsonPath('enlace.url', '/login');
        $this->assertStringContainsString('Odontología', $result->json('respuesta'));

        Especialidad::where('nombre', 'Odontología')->update(['estado' => 'Inactivo']);
        $result = $this->postJson('/api/chatbot/mensaje', ['mensaje' => 'Consulta dental'])
            ->assertOk()->assertJsonMissingPath('enlace');
        $this->assertStringContainsString('Recepción', $result->json('respuesta'));
    }

    public function test_patient_can_follow_the_booking_link_from_general_orientation(): void
    {
        $patient = $this->patient();
        $user = $this->user('Paciente', $patient->persona);

        $result = $this->withSession($this->sessionFor($user))
            ->postJson('/api/chatbot/mensaje', ['mensaje' => 'Consulta general o no sé'])
            ->assertOk()->assertJsonPath('enlace.url', '/paciente/citas/nueva');
        $this->assertStringContainsString('Medicina General', $result->json('respuesta'));
    }

    public function test_urgent_language_takes_priority_over_a_booking_request(): void
    {
        $message = 'Quiero una cita, me duele el pecho';
        $result = $this->postJson('/api/chatbot/mensaje', ['mensaje' => $message])
            ->assertOk()->assertJsonMissingPath('enlace');
        $this->assertStringContainsString('atención urgente', $result->json('respuesta'));
        $this->assertDatabaseMissing('consulta_asistente_ia', ['texto_entrada' => $message]);
    }
}
