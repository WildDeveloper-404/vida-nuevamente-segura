TURNO 4: PERFIL CLÍNICO, CONSULTORIO, RECETAS CON QR, INTERCONSULTAS Y PAGOS STRIPE (CU10 A CU14)

Genera el código PHP, Blade y librerías completas para la primera mitad del Ciclo 2 (CU10 al CU14), integrando las validaciones clínicas y pasarelas de pago:

1. CU10: Administrar perfil clínico y antecedentes del paciente
   - `app/Http/Controllers/PerfilClinicoController.php`:
     * Formulario estructurado en tarjetas responsivas: tipo de sangre (A+, O-, etc.), alergias a medicamentos/alimentos, enfermedades crónicas de base, medicación habitual y contacto de emergencia (nombre, parentesco, teléfono).
     * Actualización segura y control de privacidad (RN12, E2): los antecedentes médicos sensibles solo son visibles para el médico asignado y el propio paciente titular.
   - `resources/views/pacientes/perfil_clinico.blade.php`.

2. CU11: Consultar agenda médica y controlar estados de atención
   - `app/Http/Controllers/AgendaMedicaController.php`:
     * Vista de agenda diaria/semanal para el médico y recepción con filtros por fecha (por defecto hoy) y estado de cita (Prueba #8, RF11).
     * Transición de estados (RF10):
       - Recepción: cambia de 'Programada' a 'Confirmada' al presentarse el paciente.
       - Médico: cambia de 'Confirmada' a 'Atendida' al concluir la consulta en consultorio.
       - Registro de 'Inasistencia' si el paciente no acude al finalizar el turno.
     * Captura la excepción del trigger `trg_maquina_estados_cita` si se intenta alterar una cita cancelada o atendida de forma indebida (E1).
   - `resources/views/medico/agenda.blade.php`: Interfaz interactiva de consultorio con acceso directo para ver antecedentes clínicos del paciente citado y botones de acción rápida.

3. CU12: Emitir receta médica digital y orden de exámenes
   - `app/Http/Controllers/RecetaController.php`:
     * Formulario de atención médica: diagnóstico clínico, prescripción de medicamentos (nombre, dosis, frecuencia, duración) y órdenes de laboratorio/imagenología.
     * Validación de Alergias: Captura la excepción de negocio del trigger `trg_verificar_alergia_receta` si un fármaco prescrito coincide con una alergia registrada en el perfil del paciente (E1), alertando inmediatamente en pantalla.
     * Generación de Receta en PDF con Dompdf o TCPDF, incorporando datos de la clínica, matrícula profesional del médico y código QR único de verificación para el paciente.
     * Descarga de receta habilitada desde el historial del paciente (CU08).
   - `resources/views/medico/receta_form.blade.php` y vista de impresión PDF `resources/views/recetas/pdf.blade.php`.

4. CU13: Registrar derivación médica / interconsulta a otra especialidad
   - `app/Http/Controllers/DerivacionController.php`:
     * Registro de orden de interconsulta vinculada a la cita actual (`id_cita_origen`): selección de especialidad destino, nivel de prioridad ('Normal', 'Preferente', 'Urgente') y motivo clínico detallado.
     * Asignación de código correlativo DER-XXXX y estado 'Pendiente de programar'.
     * Precarga automática de la especialidad al momento en que el paciente o recepción procedan a agendar la nueva cita (CU07).
   - `resources/views/medico/derivacion_form.blade.php`.

5. CU14: Procesar pago electrónico de reserva médica con Stripe
   - `app/Http/Controllers/PagoController.php`:
     * iniciarPago(Request $request, $id_cita): Crea una sesión de cobro con Stripe Checkout o PaymentIntent utilizando el SDK oficial de Stripe en PHP con el monto exacto del arancel de la especialidad.
     * webhookStripe(Request $request): Endpoint Webhook que valida la firma criptográfica de Stripe. Al recibir el evento 'checkout.session.completed' o 'payment_intent.succeeded', inserta el registro en la tabla `pago`, crea el `comprobante` fiscal y dispara el trigger `trg_confirmar_cita_por_pago` que actualiza automáticamente la cita de 'Programada' a 'Confirmada' (E1, E2, E3).
     * comprobante($id_pago): Muestra y permite descargar el comprobante digital de pago.
   - `resources/views/pagos/checkout.blade.php` y comprobante digital responsivo.

Genera cada controlador, vista y script sin omitir detalles de implementación.