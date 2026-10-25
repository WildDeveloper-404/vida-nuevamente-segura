TURNO 1: ARQUITECTURA BASE, CONFIGURACIÓN, MODELOS ELOQUENT Y LAYOUT MAESTRO RESPONSIVO

Basándote en el script SQL y en los 18 casos de uso adjuntos, genera el código completo, funcional y sin abreviaciones de la base arquitectónica del proyecto:

1. ARCHIVO .env.example:
   - Configuración para PostgreSQL (DB_CONNECTION=pgsql, DB_HOST=127.0.0.1, DB_PORT=5432, DB_DATABASE=clinica_db, DB_USERNAME=postgres, DB_PASSWORD=secret).
   - Variables de entorno para correo SMTP, pasarela Stripe (STRIPE_KEY, STRIPE_SECRET, STRIPE_WEBHOOK_SECRET) y servicio de IA (GEMINI_API_KEY).

2. MIDDLEWARE Y MANEJADOR GLOBAL DE EXCEPCIONES:
   - `app/Http/Middleware/MiddlewareRol.php`: Verifica si existe la sesión activa del usuario y valida si su rol ('Administrador', 'Recepcionista', 'Medico', 'Paciente') tiene autorización para la ruta solicitada. Si no hay sesión, redirige a login con mensaje (Prueba #9); si el rol no coincide, aborta con código HTTP 403.
   - `app/Exceptions/Handler.php` (o Middleware de excepciones SQL): Método para capturar QueryException con código SQLSTATE 'P0001' (excepciones lanzadas por los triggers de PostgreSQL) y redirigir hacia atrás con `back()->withErrors(['db_error' => $mensajeLimpio])` sin exponer credenciales ni trazas técnicas.

3. RUTAS COMPLETAS (`routes/web.php`):
   - Definición de todas las rutas de los 18 Casos de Uso (CU01 al CU18) usando controladores dedicados.
   - Rutas públicas: login, logout, formulario de auto-registro de paciente, recuperación de contraseña y webhook de Stripe.
   - Grupos de rutas protegidos por MiddlewareRol organizados por prefijos y nombres:
     * Prefijo `/admin`: usuarios, roles, bitácora, especialidades, médicos, horarios, reportes estáticos, QBE y comando de voz con IA.
     * Prefijo `/recepcion`: pacientes, citas médicas, agenda general, confirmación de asistencia, pagos en recepción y contingencias.
     * Prefijo `/medico`: agenda médica del profesional, consultorio, atención, recetas con QR, derivaciones e interconsultas.
     * Prefijo `/paciente`: portal personal, mis citas, reprogramación/cancelación propia, perfil clínico y pago en línea.
     * Endpoint API `/api/chatbot/mensaje`: para procesar las consultas asíncronas del asistente virtual "Angélica".

4. MODELOS ELOQUENT COMPLETOS (`app/Models/`):
   Genera los modelos mapeados exactamente a las 24 tablas del script SQL:
   - `Rol` ($table = 'rol', $primaryKey = 'id_rol', timestamps = false)
   - `Modulo` ($table = 'modulo', $primaryKey = 'id_modulo', timestamps = false)
   - `Permiso` ($table = 'permiso', $primaryKey = 'id_permiso', timestamps = false)
   - `Persona` ($table = 'persona', $primaryKey = 'id_persona', timestamps = false)
   - `PersonalAdministrativo` ($table = 'personal_administrativo', $primaryKey = 'id_personal', timestamps = false)
   - `Usuario` ($table = 'usuario', $primaryKey = 'id_usuario', timestamps = false, con relación a Persona y Rol)
   - `Bitacora` ($table = 'bitacora', $primaryKey = 'id_bitacora', timestamps = false)
   - `Especialidad` ($table = 'especialidad', $primaryKey = 'id_especialidad', timestamps = false, con autorreferencia recursiva de subespecialidades)
   - `Medico` ($table = 'medico', $primaryKey = 'id_medico', timestamps = false, con relación a Persona y Horarios)
   - `MedicoEspecialidad` ($table = 'medico_especialidad', timestamps = false)
   - `Consultorio` ($table = 'consultorio', $primaryKey = 'id_consultorio', timestamps = false)
   - `HorarioAtencion` ($table = 'horario_atencion', $primaryKey = 'id_horario', timestamps = false)
   - `BloqueoAgenda` ($table = 'bloqueo_agenda', $primaryKey = 'id_bloqueo', timestamps = false)
   - `Paciente` ($table = 'paciente', $primaryKey = 'id_paciente', timestamps = false, con relación a Persona, PerfilClinico y Citas)
   - `PerfilClinico` ($table = 'perfil_clinico', $primaryKey = 'id_perfil', timestamps = false)
   - `CitaMedica` ($table = 'cita_medica', $primaryKey = 'id_cita', timestamps = false, con relación a Paciente, Medico, Especialidad, Pago y autorreferencia de reprogramación)
   - `NotificacionCita` ($table = 'notificacion_cita', $primaryKey = 'id_notificacion', timestamps = false)
   - `RecetaMedica` ($table = 'receta_medica', $primaryKey = 'id_receta', timestamps = false)
   - `DetalleReceta` ($table = 'detalle_receta', $primaryKey = 'id_detalle', timestamps = false)
   - `DerivacionMedica` ($table = 'derivacion_medica', $primaryKey = 'id_derivacion', timestamps = false)
   - `Pago` ($table = 'pago', $primaryKey = 'id_pago', timestamps = false)
   - `Comprobante` ($table = 'comprobante', $primaryKey = 'id_comprobante', timestamps = false)
   - `ConsultaAsistenteIA` ($table = 'consulta_asistente_ia', $primaryKey = 'id_consulta_ia', timestamps = false)
   Cada modelo debe incluir atributos $fillable completos y sus relaciones hasMany, belongsTo o belongsToMany.

5. LAYOUT MAESTRO RESPONSIVO (`resources/views/layouts/app.blade.php`):
   - Diseño limpio con paleta médica profesional (#1A5276, #F4F6F7, #2E86C1).
   - Inclusión de Bootstrap 5.3 CDN, Bootstrap Icons y meta viewport.
   - Sidebar responsivo: fija en escritorio y menú Offcanvas deslizante para móviles y tabletas con menús condicionados por `@if(session('rol') == '...')`.
   - Navbar superior con botón de menú hamburguesa para móviles, visualización del correo del usuario conectado, rol y botón de cierre de sesión.
   - Contenedor de alertas estandarizado para mostrar mensajes de éxito (`session('success')`) y mensajes de error de validación o triggers de PostgreSQL (`$errors->all()`).
   - Widget flotante en JavaScript del Chatbot "Angélica" ubicado en la esquina inferior derecha, colapsable y con diseño adaptado a pantallas táctiles.

Entrega todo el código sin omitir líneas ni resumir archivos.