TURNO 5: CHATBOT ASISTENTE VIRTUAL Y LOS 3 FORMATOS OBLIGATORIOS DE REPORTES (CU15 A CU18)

Genera el código completo en PHP, JavaScript y Blade para los módulos finales del sistema, cumpliendo estrictamente con los casos de uso CU15 al CU18 y las exigencias de reportería de la clínica:

1. CU15: Interactuar con Chatbot Asistente Virtual ("Angélica")
   - `app/Http/Controllers/ChatbotController.php`:
     * procesarMensaje(Request $request): Endpoint API que recibe el texto del paciente vía JSON.
     * Flujo conversacional guiado: responde sobre especialidades médicas activas, horarios y médicos disponibles, y requisitos de atención.
     * Consulta en tiempo real de citas activas: si el paciente solicita ver sus citas o envía su número de cédula de identidad (CI), consulta la base de datos y retorna sus turnos en estado 'Programada' o 'Confirmada' con código, médico, fecha y hora.
     * Guarda la trazabilidad en la tabla `consulta_asistente_ia` con canal 'Chatbot_Angelica'.
   - `public/js/chatbot.js`: Lógica del widget flotante (botón circular en esquina inferior, apertura/cierre responsivo, scroll automático, envío asíncrono con Fetch API y burbujas de diálogo accesibles en móviles).

2. CU16: Generar reportes estáticos administrativos (RF13 - Formato 1)
   - `app/Http/Controllers/ReporteController.php`:
     * Implementación de los 7 reportes obligatorios con filtros de fecha (desde/hasta), médico y especialidad:
       1. Citas programadas por fecha.
       2. Citas agrupadas por médico.
       3. Citas agrupadas por especialidad médica.
       4. Citas clasificadas por estado (Programada, Confirmada, Atendida, Cancelada, Inasistencia).
       5. Padrón demográfico general de pacientes.
       6. Cantidad consolidada de citas atendidas, canceladas e inasistencias ejecutando la función SQL `fn_reporte_consolidado_citas(fecha_inicio, fecha_fin)`.
       7. Agenda diaria de cada médico de la clínica.
     * Cálculo de subtotales y fila final de totales acumulados.
     * Exportación de los datos a PDF (vista maquetada para impresión), CSV y Excel.
   - `resources/views/admin/reportes/estaticos.blade.php`: Interfaz con filtros, selectores y tabla de resultados responsiva.

3. CU17: Generar reportes dinámicos mediante Query By Example - QBE (RF13 - Formato 2)
   - `app/Http/Controllers/QbeController.php`:
     * Constructor interactivo visual: despliega casillas de verificación (checkboxes) agrupadas por entidad (Paciente, Médico, Especialidad, Cita, Pago) para que el Administrador elija las columnas de su informe.
     * Filtros por ejemplo: inputs para establecer criterios combinados (ej. Especialidad = 'Cardiología' AND Estado = 'Atendida' AND Monto > 100).
     * Seguridad backend: validación estricta mediante lista blanca de columnas y tablas permitidas para evitar inyecciones SQL. Construcción dinámica de la sentencia SELECT parametrizada con PDO sobre PostgreSQL.
     * Opción de exportación del reporte dinámico a PDF o CSV.
   - `resources/views/admin/reportes/qbe.blade.php`: Interfaz visual en rejilla con casillas de selección y visor dinámico de resultados.

4. CU18: Generar reportes mediante comando de voz con IA (RF13 - Formato 3)
   - `app/Http/Controllers/VozReporteController.php`:
     * procesarVoz(Request $request): Recibe la transcripción en texto del comando de voz enviado desde el navegador.
     * Integración con servicio de IA (Google Gemini API / OpenAI API): envía el prompt de traducción Text-to-SQL incluyendo el texto dictado por el usuario y el esquema de tablas del sistema (médicos, especialidades, citas, pacientes, horarios).
     * Blindaje de Seguridad en Servidor: inspecciona la consulta SQL devuelta por la IA asegurando estrictamente que comience con 'SELECT' y bloqueando cualquier comando destructivo o de modificación (DROP, DELETE, UPDATE, INSERT, ALTER).
     * Ejecuta la consulta de solo lectura mediante `DB::select($sqlSeguro)` y guarda el registro en `consulta_asistente_ia` con canal 'Comando_Voz_IA'.
     * Retorna el conjunto de datos tabulares y una síntesis en lenguaje natural.
   - `resources/views/admin/reportes/voz.blade.php`:
     * Botón táctil de micrófono con animación de ondas mientras escucha.
     * Captura fonética en tiempo real con `Web Speech API` (webkitSpeechRecognition) mostrando el texto dictado en pantalla.
     * Renderizado de la tabla de resultados con opción de exportación a PDF o Excel.

Entrega el código íntegro, sin resúmenes y totalmente listo para producción.