<?php

namespace App\Console\Commands;

use App\Models\BloqueoAgenda;
use App\Models\ConsultaAsistenteIA;
use App\Models\Consultorio;
use App\Models\DerivacionMedica;
use App\Models\DetalleReceta;
use App\Models\Especialidad;
use App\Models\HorarioAtencion;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\PerfilClinico;
use App\Models\Persona;
use App\Models\RecetaMedica;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\CitaService;
use App\Services\PagoService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SampleDataCommand extends Command
{
    protected $signature = 'clinica:datos-ejemplo';

    protected $description = 'Agregar datos clínicos ficticios a la base local, sin modificar registros existentes';

    public function handle(CitaService $citas, PagoService $pagos): int
    {
        if (! app()->environment('local')) {
            $this->error('Este comando sólo se permite en APP_ENV=local.');

            return self::FAILURE;
        }

        if (Persona::where('ci', 'FIC-MED-01')->exists()) {
            $this->info('El conjunto de datos ficticios ya existe; no se duplicó.');

            return self::SUCCESS;
        }

        $roles = Rol::whereIn('nombre_rol', ['Medico', 'Paciente'])->pluck('id_rol', 'nombre_rol');
        $specialties = Especialidad::whereIn('nombre', ['Medicina General', 'Cardiología', 'Pediatría', 'Traumatología'])
            ->pluck('id_especialidad', 'nombre');
        if ($roles->count() !== 2 || $specialties->count() !== 4) {
            $this->error('Faltan roles o especialidades base. Instale los datos iniciales antes de continuar.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($citas, $pagos, $roles, $specialties): void {
            $password = Hash::make('DemoVida2026!');
            $doctorData = [
                ['Lucía', 'Rivas', 'Medicina General'],
                ['Mateo', 'Salcedo', 'Cardiología'],
                ['Elena', 'Duarte', 'Pediatría'],
                ['Rafael', 'Ibarra', 'Traumatología'],
            ];
            $doctors = [];
            foreach ($doctorData as $i => [$first, $last, $specialty]) {
                $n = $i + 1;
                $person = Persona::create([
                    'ci' => sprintf('FIC-MED-%02d', $n),
                    'nombres' => $first,
                    'apellidos' => $last,
                    'telefono' => sprintf('000000%04d', $n),
                    'direccion' => 'Dato ficticio de demostración',
                ]);
                Usuario::create([
                    'id_persona' => $person->id_persona,
                    'correo' => sprintf('medico%02d@clinica.test', $n),
                    'password_hash' => $password,
                    'id_rol' => $roles['Medico'],
                ]);
                $doctor = Medico::create([
                    'id_medico' => $person->id_persona,
                    'codigo_medico' => sprintf('MED-FIC-%02d', $n),
                    'nro_matricula' => sprintf('FIC-MAT-%04d', $n),
                ]);
                $doctor->especialidades()->attach($specialties[$specialty]);
                $room = Consultorio::create([
                    'numero' => (string) (201 + $i),
                    'bloque' => 'Bloque de demostración',
                    'piso' => 2,
                ]);
                foreach (['Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes'] as $day) {
                    HorarioAtencion::create([
                        'id_medico' => $doctor->id_medico,
                        'id_consultorio' => $room->id_consultorio,
                        'dia_semana' => $day,
                        'hora_inicio' => '08:00',
                        'hora_fin' => '12:00',
                        'duracion_minutos' => 30,
                        'cupo_maximo' => 8,
                    ]);
                }
                $doctors[] = $doctor;
            }

            $patientData = [
                ['Ana', 'Paredes', 'F', '1986-04-16', 'Ninguna conocida'],
                ['Carlos', 'Mejía', 'M', '1972-08-05', 'Penicilina'],
                ['Sofía', 'Torres', 'F', '2017-03-21', 'Ninguna conocida'],
                ['Daniel', 'Vega', 'M', '1991-12-09', 'Látex'],
                ['Mariana', 'Campos', 'F', '1980-06-18', 'Ninguna conocida'],
                ['Jorge', 'León', 'M', '1965-11-30', 'Ninguna conocida'],
                ['Valentina', 'Soto', 'F', '2019-09-14', 'Ninguna conocida'],
                ['Andrés', 'Farias', 'M', '2002-02-27', 'Ninguna conocida'],
            ];
            $patients = [];
            foreach ($patientData as $i => [$first, $last, $sex, $birth, $allergies]) {
                $n = $i + 1;
                $person = Persona::create([
                    'ci' => sprintf('FIC-PAC-%02d', $n),
                    'nombres' => $first,
                    'apellidos' => $last,
                    'telefono' => sprintf('000001%04d', $n),
                    'direccion' => 'Dato ficticio de demostración',
                ]);
                Usuario::create([
                    'id_persona' => $person->id_persona,
                    'correo' => sprintf('paciente%02d@clinica.test', $n),
                    'password_hash' => $password,
                    'id_rol' => $roles['Paciente'],
                ]);
                $patient = Paciente::create([
                    'id_paciente' => $person->id_persona,
                    'codigo_paciente' => sprintf('PAC-FIC-%02d', $n),
                    'sexo' => $sex,
                    'fecha_nacimiento' => $birth,
                ]);
                PerfilClinico::create([
                    'id_paciente' => $patient->id_paciente,
                    'tipo_sangre' => ['O+', 'A+', 'B+', 'O-', 'A-', 'AB+', 'B-', 'O+'][$i],
                    'alergias' => $allergies,
                    'enfermedades_base' => 'Ninguna registrada (dato ficticio)',
                    'medicamentos_habituales' => 'Ninguno registrado',
                    'contacto_emergencia_nombre' => 'Contacto ficticio '.sprintf('%02d', $n),
                    'contacto_emergencia_parentesco' => 'Familiar',
                    'contacto_emergencia_telefono' => sprintf('000002%04d', $n),
                ]);
                $patients[] = $patient;
            }

            $monday = CarbonImmutable::today()->next(CarbonImmutable::MONDAY);
            $bookings = [
                [0, 0, 0, '09:00', 'Control general de demostración'],
                [0, 1, 0, '10:00', 'Consulta preventiva de demostración'],
                [1, 1, 0, '09:00', 'Evaluación cardiovascular de demostración'],
                [2, 2, 0, '09:00', 'Control pediátrico de demostración'],
                [3, 3, 0, '09:00', 'Evaluación traumatológica de demostración'],
                [0, 4, 1, '09:00', 'Consulta general de demostración'],
                [1, 5, 1, '09:00', 'Control cardiológico de demostración'],
                [2, 6, 1, '09:00', 'Consulta pediátrica de demostración'],
                [3, 7, 1, '09:00', 'Control traumatológico de demostración'],
                [0, 3, 2, '09:00', 'Seguimiento general de demostración'],
                [1, 4, 2, '09:00', 'Seguimiento cardíaco de demostración'],
                [3, 0, 3, '09:00', 'Consulta traumatológica de demostración'],
            ];
            $created = [];
            foreach ($bookings as [$doctorIndex, $patientIndex, $dayOffset, $time, $reason]) {
                $specialty = $doctorData[$doctorIndex][2];
                $created[] = $citas->book([
                    'id_paciente' => $patients[$patientIndex]->id_paciente,
                    'id_medico' => $doctors[$doctorIndex]->id_medico,
                    'id_especialidad' => $specialties[$specialty],
                    'fecha_cita' => $monday->addDays($dayOffset)->toDateString(),
                    'hora_inicio' => $time,
                    'motivo_consulta' => $reason,
                ], null);
            }

            foreach ([0, 2, 3, 6] as $index) {
                $appointment = $created[$index];
                $amount = PagoService::cents((string) $appointment->especialidad->arancel_consulta);
                $pagos->record(
                    $appointment->id_cita,
                    $amount,
                    (string) config('services.stripe.currency'),
                    sprintf('FIC-PRES-%04d', $index + 1),
                    'Efectivo'
                );
            }

            $recipe = RecetaMedica::create([
                'id_cita' => $created[0]->id_cita,
                'diagnostico' => 'Evaluación general de demostración; información clínica ficticia.',
                'codigo_qr' => Str::random(48),
            ]);
            DetalleReceta::create([
                'id_receta' => $recipe->id_receta,
                'tipo_item' => 'Examen_Laboratorio',
                'medicamento_o_examen' => 'Hemograma completo (ejemplo)',
            ]);
            DetalleReceta::create([
                'id_receta' => $recipe->id_receta,
                'tipo_item' => 'Medicamento',
                'medicamento_o_examen' => 'Acetaminofén (ejemplo)',
                'dosis' => 'Según indicación médica de prueba',
                'frecuencia' => 'Según indicación médica de prueba',
                'duracion' => 'Sólo demostración',
            ]);
            DerivacionMedica::create([
                'codigo_derivacion' => 'DER-FIC-01',
                'id_cita_origen' => $created[0]->id_cita,
                'id_especialidad_destino' => $specialties['Cardiología'],
                'prioridad' => 'Normal',
                'motivo_clinico' => 'Derivación ficticia para demostrar el flujo de especialidades.',
            ]);

            $citas->cancel($created[9], 'Cancelación de demostración');
            $citas->reschedule($created[10], [
                'fecha_cita' => $monday->addDays(4)->toDateString(),
                'hora_inicio' => '09:30',
                'motivo' => 'Cambio de fecha de demostración',
            ], null);
            BloqueoAgenda::create([
                'id_medico' => $doctors[2]->id_medico,
                'fecha_bloqueo' => $monday->addDays(4)->toDateString(),
                'hora_inicio' => '10:00',
                'hora_fin' => '11:00',
                'tipo_bloqueo' => 'Permiso_Medico',
                'motivo' => 'Bloqueo ficticio para demostrar disponibilidad',
            ]);
            ConsultaAsistenteIA::create([
                'canal' => 'Chatbot_Angelica',
                'texto_entrada' => '¿Qué especialidades ofrece la clínica? (ejemplo)',
                'respuesta_emitida' => 'Consulta ficticia: medicina general, cardiología, pediatría y traumatología.',
            ]);
            ConsultaAsistenteIA::create([
                'canal' => 'Comando_Voz_IA',
                'texto_entrada' => 'Mostrar citas de esta semana (ejemplo)',
                'respuesta_emitida' => 'Solicitud ficticia registrada; no se envió a Gemini.',
            ]);
        });

        $this->info('Datos ficticios cargados: 4 médicos, 8 pacientes, 13 citas (incluida una reprogramación), 4 pagos y comprobantes, receta, derivación, bloqueo y 2 consultas del asistente.');
        $this->info('Cuentas nuevas: medico01..04@clinica.test y paciente01..08@clinica.test; contraseña local: DemoVida2026!');

        return self::SUCCESS;
    }
}
