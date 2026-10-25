TURNO 3: CONTROLADORES, JAVASCRIPT Y VISTAS BLADE PARA CU06 AL CU09 (NÚCLEO TRANSACCIONAL)

Genera el código PHP, JavaScript y Blade completo para el núcleo transaccional del Ciclo 1, cumpliendo rigurosamente los casos de uso CU06 al CU09, las Reglas de Negocio RN01 a RN10 y las Pruebas Mínimas #1 a #7:

1. CU06: Gestionar pacientes y auto-registro
   - `app/Http/Controllers/PacienteController.php`:
     * store(Request $request): Ejecuta el procedimiento almacenado en PostgreSQL `sp_registrar_paciente_completo` para crear atómicamente la Persona, Paciente (código correlativo PAC-XXXX), PerfilClinico base y la cuenta de Usuario si incluye contraseña.
     * Validación de CI único en el cliente y captura de la excepción del servidor (Pruebas #1 y #2, RN01, E1).
     * Cálculo automático de la edad en backend a partir de fecha_nacimiento.
     * buscarPorCi(Request $request): Endpoint AJAX para autocompletar datos del paciente en recepción mediante búsqueda por CI o apellido.
     * destroy(): Baja lógica pasando estado = 'Inactivo' para preservar el historial (RN11, E4).
   - `resources/views/pacientes/create.blade.php`: Formulario responsivo (col-12 col-md-6) utilizado tanto en recepción como en la vista pública de auto-registro.
   - `resources/views/pacientes/index.blade.php`: Directorio de pacientes con barra de búsqueda y tabla responsiva.

2. CU07: Programar, reprogramar y cancelar citas médicas
   - `app/Http/Controllers/CitaController.php`:
     * getBloquesDisponibles(Request $request): Invoca la función SQL `fn_obtener_bloques_disponibles(id_medico, fecha)` y devuelve en JSON los bloques horarios (hora_inicio, hora_fin, estado: 'Disponible' u 'Ocupado').
     * store(Request $request): Valida en backend la regla de rango de edad de la especialidad contra la edad del paciente (ej. rechaza si un adulto intenta reservar en Pediatría o un joven en Geriatría). Ejecuta el procedimiento almacenado `sp_programar_cita` con bloqueo pesimista FOR UPDATE para garantizar que no existan colisiones ni citas superpuestas (Pruebas #3, #4 y #5, RN05, RN06, RN07, RN13).
     * reprogramar(Request $request, $id): Valida disponibilidad en el nuevo horario, actualiza la cita y libera el bloque previo manteniendo el id_cita_anterior para trazabilidad (RF08).
     * cancelar(Request $request, $id): Cambia el estado a 'Cancelada', registra el motivo de cancelación, libera inmediatamente el horario para otros pacientes y conserva el registro en el historial (Prueba #6, RF09, RN09, RN10).
   - `resources/views/citas/create.blade.php`: Formulario de reserva responsivo por pasos:
     1. Selección de Paciente (buscador por CI para recepción o preseleccionado para el paciente).
     2. Selector de Especialidad Médica (muestra arancel y rango etario).
     3. Selector de Médico Activo de esa especialidad.
     4. Calendario interactivo (bloquea fechas pasadas según RN06).
     5. Contenedor de Bloques Horarios: generado dinámicamente con JavaScript Vanilla (Fetch API) mostrando una cuadrícula táctil de botones tipo chip (disponibles en verde/azul, ocupados deshabilitados en gris).
     6. Campo de motivo de consulta y botón de confirmación.
   - Modales interactivos para reprogramación y cancelación.

3. CU08: Consultar historial / Kardex de citas del paciente
   - `app/Http/Controllers/HistorialCitaController.php`:
     * index(Request $request): Consulta el Kardex cronológico completo de todas las citas del paciente (atendidas, programadas, canceladas e inasistencias - Prueba #7, RN10) mediante INNER/LEFT JOIN con médicos, especialidades y pagos.
     * Filtros rápidos por rango de fechas, especialidad y estado de consulta. Restricción de acceso para que un paciente solo vea su propio historial (RN12, E2).
   - `resources/views/citas/kardex.blade.php`: Tabla responsiva con badges de colores según estado de la cita, visualización de motivos y botón para ver receta médica si ya fue atendida.

4. CU09: Gestionar recordatorios y reprogramación masiva por emergencia médica
   - `app/Http/Controllers/EmergenciaController.php`:
     * Formulario de contingencia: el médico o la recepcionista seleccionan al profesional afectado, fecha, turno y motivo de ausencia ("Cirugía de Urgencia" o "Emergencia Médica").
     * Ejecuta el procedimiento almacenado `sp_reprogramar_masivo_emergencia`, el cual bloquea el horario en `bloqueo_agenda`, traslada automáticamente todas las citas afectadas al siguiente turno disponible del médico sin sobreposiciones e inserta las alertas en `notificacion_cita`.
     * Generación de alertas para pacientes cuyo correo no esté disponible (E3).
   - `resources/views/contingencias/index.blade.php`: Panel de gestión de emergencias con listado de citas trasladadas y estado de notificaciones enviadas.

Entrega todos los archivos completos, con el código JavaScript integrado para los bloques de horas y la captura de errores de PostgreSQL.