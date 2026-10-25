TURNO 2: CONTROLADORES, SERVICIOS Y VISTAS BLADE PARA CU01 AL CU05 (CICLO 1)

Genera el código PHP y Blade completo y responsivo para los casos de uso CU01 al CU05, implementando cada paso del flujo y las excepciones E1, E2, E3, E4 y E5:

1. CU01: Autenticar usuario, cargar vistas por rol y recuperar contraseña
   - `app/Http/Controllers/AuthController.php`:
     * showLogin(): Despliega la pantalla de acceso. Si ya hay sesión activa, redirige al panel del rol.
     * login(Request $request): Valida que correo y password no estén vacíos. Invoca la función SQL `fn_registrar_intento_login(correo, exito, ip)` para verificar bloqueos progresivos e intentos fallidos. Si es exitoso, establece las variables de sesión (id_usuario, id_persona, correo, id_rol, nombre_rol), registra en bitácora (CU03) y redirige al panel según rol. Maneja excepciones E1 (inválidas), E2 (inactivo), E3 (bloqueo vigente) y E4 (Prueba #9: acceso sin sesión).
     * logout(): Invalida y destruye la sesión, asienta el evento en bitácora y redirige al login con mensaje.
     * showRecuperar() y enviarTokenRecuperacion(): Genera token temporal con vigencia de 30 minutos y simula/despacha el correo de restablecimiento.
     * cambiarPasswordConToken(): Registra la nueva clave cifrada con Hash::make().
   - `resources/views/auth/login.blade.php`: Tarjeta de acceso responsiva centrada, validación cliente y enlace a recuperación.
   - `resources/views/auth/recuperar.blade.php` y `reset.blade.php`.

2. CU02: Administrar usuarios, roles, módulos y permisos
   - `app/Http/Controllers/UsuarioController.php`:
     * index(): Lista usuarios con buscador por correo y filtro por rol dentro de una tabla responsiva.
     * create() y store(): Formulario en grid (col-12 col-md-6) para registrar PersonalAdministrativo vinculado a Persona y crear Usuario con contraseña cifrada (Hash::make). Valida correo y CI únicos (RN01, E1).
     * edit() y update(): Actualización de datos y reasignación de rol.
     * destroy(): Prohibición de borrado físico por RN11 (E3). Aplica exclusivamente la baja lógica pasando estado = 'Inactivo'. Registra en bitácora.
   - Vistas `resources/views/admin/usuarios/index.blade.php` y `create.blade.php`.

3. CU03: Consultar y auditar bitácora de eventos del sistema
   - `app/Http/Controllers/BitacoraController.php`:
     * index(Request $request): Consulta parametrizada sobre la tabla `bitacora` con filtros por: rango de fechas (fecha_inicio a fecha_fin), correo del usuario, rol y módulo. Valida que fecha_fin >= fecha_inicio (E1). Paginación de resultados.
   - `resources/views/admin/bitacora/index.blade.php`: Tabla responsiva con badges por tipo de acción (LOGIN, ALTA, CANCELACION, etc.) y modal para inspeccionar el detalle técnico del registro.

4. CU04: Gestionar médicos y especialidades
   - `app/Http/Controllers/EspecialidadController.php`:
     * index() y store(): Registro de especialidades médicas con nombre, descripción, arancel, edad_minima y edad_maxima (ej. Pediatría: 0-13 años; Geriatría: 60-120 años). El trigger `trg_normalizar_especialidad` evita duplicados normalizados (E1).
   - `app/Http/Controllers/MedicoController.php`:
     * Registro de médicos vinculados a Persona (ci, nombres, apellidos, teléfono), matrícula profesional única (RN02/E2) y asignación múltiple de especialidades en `medico_especialidad`.
     * Baja lógica (estado = 'Inactivo', RN04/RN11/E3) sin borrado físico.
   - Vistas `resources/views/admin/especialidades/index.blade.php` y `resources/views/admin/medicos/index.blade.php`.

5. CU05: Configurar horarios de atención y disponibilidad médica
   - `app/Http/Controllers/HorarioController.php`:
     * Asignación de turnos semanales a médicos activos (día de la semana, hora_inicio, hora_fin, duración de 15, 20 o 30 min). Captura las excepciones de negocio del trigger `trg_validar_horario_medico` (hora_fin > hora_inicio, no superposición de turnos para el mismo médico y cálculo automático de cupos).
     * Registro de feriados o bloqueos especiales en la tabla `bloqueo_agenda`.
   - `resources/views/admin/horarios/index.blade.php`: Visualización semanal de agenda con formulario interactivo y selector de intervalos.

Entrega el código íntegro de controladores y vistas con clases Bootstrap 5 responsivas.