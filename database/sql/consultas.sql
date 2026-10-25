-- =============================================================================
-- CATÁLOGO COMPLETO DE CONSULTAS SQL (SIMPLES, MÚLTIPLES Y SUBCONSULTAS)
-- SISTEMA DE GESTIÓN DE CITAS MÉDICAS - CLÍNICA "VIDA NUEVAMENTE SEGURA"
-- =============================================================================


-- =============================================================================
-- 1. CONSULTAS SIMPLES (UNA SOLA TABLA)
-- =============================================================================

-- 1.1. Listar especialidades médicas activas con su rango de edad y arancel (CU04, CU07 - RF04)
SELECT 
    id_especialidad,
    nombre,
    descripcion,
    edad_minima,
    edad_maxima,
    arancel_consulta
FROM especialidad
WHERE estado = 'Activo'
ORDER BY nombre ASC;

-- 1.2. Verificar cuenta de usuario y estado de bloqueo por correo electrónico (CU01 - RF01)
SELECT 
    id_usuario,
    id_persona,
    correo,
    password_hash,
    estado,
    intentos_fallidos,
    bloqueado_hasta,
    id_rol
FROM usuario
WHERE LOWER(correo) = LOWER(:correo);

-- 1.3. Consultar horarios semanales configurados de un médico específico (CU05 - RF05)
SELECT 
    id_horario,
    dia_semana,
    hora_inicio,
    hora_fin,
    duracion_minutos,
    cupo_maximo,
    estado
FROM horario_atencion
WHERE id_medico = :id_medico
  AND estado = 'Activo'
ORDER BY 
    CASE dia_semana
        WHEN 'Lunes' THEN 1
        WHEN 'Martes' THEN 2
        WHEN 'Miercoles' THEN 3
        WHEN 'Jueves' THEN 4
        WHEN 'Viernes' THEN 5
        WHEN 'Sabado' THEN 6
        WHEN 'Domingo' THEN 7
    END,
    hora_inicio ASC;

-- 1.4. Verificar bloqueos de agenda por feriado o cirugía de emergencia en una fecha (CU05, CU09 - RF05)
SELECT 
    id_bloqueo,
    fecha_bloqueo,
    hora_inicio,
    hora_fin,
    tipo_bloqueo,
    motivo
FROM bloqueo_agenda
WHERE id_medico = :id_medico
  AND fecha_bloqueo = :fecha_consulta
ORDER BY hora_inicio ASC;

-- 1.5. Resumen rápido de citas agrupadas por estado en un rango de fechas (CU16 - RF13)
SELECT 
    estado,
    COUNT(id_cita) AS total_citas
FROM cita_medica
WHERE fecha_cita BETWEEN :fecha_inicio AND :fecha_fin
GROUP BY estado
ORDER BY total_citas DESC;

-- 1.6. Auditar eventos de la bitácora por módulo y rango de fechas (CU03 - RN14)
SELECT 
    id_bitacora,
    id_usuario,
    modulo,
    accion,
    descripcion,
    direccion_ip,
    fecha_hora
FROM bitacora
WHERE fecha_hora BETWEEN :fecha_inicio AND :fecha_fin
  AND modulo = :modulo
ORDER BY fecha_hora DESC;


-- =============================================================================
-- 2. CONSULTAS MÚLTIPLES / MULTITABLA (INNER JOIN Y LEFT JOIN)
-- =============================================================================

-- 2.1. Obtener perfil completo del usuario autenticado y su rol (CU01, CU02 - RF01)
SELECT 
    u.id_usuario,
    u.correo,
    u.ultimo_acceso,
    p.id_persona,
    p.ci,
    p.nombres,
    p.apellidos,
    p.telefono,
    r.id_rol,
    r.nombre_rol
FROM usuario u
INNER JOIN persona p ON u.id_persona = p.id_persona
INNER JOIN rol r ON u.id_rol = r.id_rol
WHERE u.id_usuario = :id_usuario
  AND u.estado = 'Activo';

-- 2.2. Cargar menú dinámico de módulos y permisos según el rol (CU01, CU02 - RF01, RN12)
SELECT 
    m.id_modulo,
    m.nombre_modulo,
    p.id_permiso,
    p.nombre_permiso
FROM rol r
INNER JOIN rol_permiso rp ON r.id_rol = rp.id_rol
INNER JOIN permiso p ON rp.id_permiso = p.id_permiso
INNER JOIN modulo m ON p.id_modulo = m.id_modulo
WHERE r.id_rol = :id_rol
ORDER BY m.id_modulo ASC, p.nombre_permiso ASC;

-- 2.3. Buscador de pacientes con cálculo automático de edad y perfil clínico (CU06, CU10 - RF02)
SELECT 
    pac.id_paciente,
    pac.codigo_paciente,
    per.ci,
    per.nombres,
    per.apellidos,
    pac.sexo,
    pac.fecha_nacimiento,
    EXTRACT(YEAR FROM age(CURRENT_DATE, pac.fecha_nacimiento))::INT AS edad_actual,
    per.telefono,
    u.correo,
    pc.tipo_sangre,
    pc.alergias,
    pc.enfermedades_base,
    pc.contacto_emergencia_nombre,
    pc.contacto_emergencia_telefono,
    pac.estado
FROM paciente pac
INNER JOIN persona per ON pac.id_paciente = per.id_persona
LEFT JOIN usuario u ON per.id_persona = u.id_persona
LEFT JOIN perfil_clinico pc ON pac.id_paciente = pc.id_paciente
WHERE per.ci ILIKE :termino
   OR per.nombres ILIKE :termino
   OR per.apellidos ILIKE :termino
   OR COALESCE(u.correo, '') ILIKE :termino
ORDER BY per.apellidos ASC, per.nombres ASC;

-- 2.4. Listar médicos activos por especialidad con sus horarios y consultorio (CU04, CU05, CU18 - RF03, RF05)
SELECT 
    m.id_medico,
    m.codigo_medico,
    m.nro_matricula,
    per.nombres || ' ' || per.apellidos AS nombre_medico,
    e.nombre AS especialidad,
    h.dia_semana,
    h.hora_inicio,
    h.hora_fin,
    h.duracion_minutos,
    h.cupo_maximo,
    COALESCE(c.numero, 'Sin asignar') AS consultorio
FROM medico m
INNER JOIN persona per ON m.id_medico = per.id_persona
INNER JOIN medico_especialidad me ON m.id_medico = me.id_medico
INNER JOIN especialidad e ON me.id_especialidad = e.id_especialidad
INNER JOIN horario_atencion h ON m.id_medico = h.id_medico
LEFT JOIN consultorio c ON h.id_consultorio = c.id_consultorio
WHERE m.estado = 'Activo'
  AND e.estado = 'Activo'
  AND h.estado = 'Activo'
  AND e.id_especialidad = :id_especialidad
ORDER BY nombre_medico ASC, h.hora_inicio ASC;

-- 2.5. Consultar Agenda Médica del día con antecedentes críticos del paciente (CU11, CU16 - RF11, Prueba #8)
SELECT 
    cm.id_cita,
    cm.codigo_cita,
    cm.fecha_cita,
    cm.hora_inicio,
    cm.hora_fin,
    cm.estado AS estado_cita,
    cm.motivo_consulta,
    pac.codigo_paciente,
    per_pac.ci AS ci_paciente,
    per_pac.nombres || ' ' || per_pac.apellidos AS nombre_paciente,
    EXTRACT(YEAR FROM age(cm.fecha_cita, pac.fecha_nacimiento))::INT AS edad_paciente,
    pc.tipo_sangre,
    pc.alergias,
    e.nombre AS especialidad
FROM cita_medica cm
INNER JOIN paciente pac ON cm.id_paciente = pac.id_paciente
INNER JOIN persona per_pac ON pac.id_paciente = per_pac.id_persona
LEFT JOIN perfil_clinico pc ON pac.id_paciente = pc.id_paciente
INNER JOIN especialidad e ON cm.id_especialidad = e.id_especialidad
WHERE cm.id_medico = :id_medico
  AND cm.fecha_cita = :fecha_agenda
  AND (:estado_filtro IS NULL OR cm.estado = :estado_filtro)
ORDER BY cm.hora_inicio ASC;

-- 2.6. Consultar Historial / Kardex completo de citas de un paciente (CU08 - RF12, RN10, Prueba #7)
SELECT 
    cm.id_cita,
    cm.codigo_cita,
    cm.fecha_cita,
    cm.hora_inicio,
    cm.hora_fin,
    e.nombre AS especialidad,
    per_med.nombres || ' ' || per_med.apellidos AS medico_tratante,
    cm.motivo_consulta,
    cm.estado,
    cm.motivo_cancelacion,
    cm.fecha_registro,
    COALESCE(pg.estado_pago, 'Sin pago') AS estado_pago,
    rm.id_receta,
    rm.codigo_qr AS qr_receta
FROM cita_medica cm
INNER JOIN especialidad e ON cm.id_especialidad = e.id_especialidad
INNER JOIN medico m ON cm.id_medico = m.id_medico
INNER JOIN persona per_med ON m.id_medico = per_med.id_persona
LEFT JOIN pago pg ON cm.id_cita = pg.id_cita
LEFT JOIN receta_medica rm ON cm.id_cita = rm.id_cita
WHERE cm.id_paciente = :id_paciente
ORDER BY cm.fecha_cita DESC, cm.hora_inicio DESC;

-- 2.7. Consulta en tiempo real de citas activas por CI para el Chatbot "Angélica" (CU15)
SELECT 
    cm.codigo_cita,
    per_pac.nombres || ' ' || per_pac.apellidos AS paciente,
    e.nombre AS especialidad,
    per_med.nombres || ' ' || per_med.apellidos AS medico,
    cm.fecha_cita,
    cm.hora_inicio,
    cm.estado
FROM cita_medica cm
INNER JOIN paciente pac ON cm.id_paciente = pac.id_paciente
INNER JOIN persona per_pac ON pac.id_paciente = per_pac.id_persona
INNER JOIN medico med ON cm.id_medico = med.id_medico
INNER JOIN persona per_med ON med.id_medico = per_med.id_persona
INNER JOIN especialidad e ON cm.id_especialidad = e.id_especialidad
WHERE per_pac.ci = :ci_paciente
  AND cm.fecha_cita >= CURRENT_DATE
  AND cm.estado IN ('Programada', 'Confirmada')
ORDER BY cm.fecha_cita ASC, cm.hora_inicio ASC;

-- 2.8. Reporte administrativo de productividad y estados por Médico (CU16 - RF13 Formato 1)
SELECT 
    m.codigo_medico,
    per.nombres || ' ' || per.apellidos AS medico,
    COUNT(cm.id_cita) AS total_citas,
    COUNT(cm.id_cita) FILTER (WHERE cm.estado = 'Atendida') AS total_atendidas,
    COUNT(cm.id_cita) FILTER (WHERE cm.estado = 'Cancelada') AS total_canceladas,
    COUNT(cm.id_cita) FILTER (WHERE cm.estado = 'Inasistencia') AS total_inasistencias,
    COALESCE(SUM(pg.monto) FILTER (WHERE pg.estado_pago = 'Pagado'), 0.00) AS total_recaudado
FROM medico m
INNER JOIN persona per ON m.id_medico = per.id_persona
LEFT JOIN cita_medica cm ON m.id_medico = cm.id_medico 
    AND cm.fecha_cita BETWEEN :fecha_inicio AND :fecha_fin
LEFT JOIN pago pg ON cm.id_cita = pg.id_cita
GROUP BY m.id_medico, m.codigo_medico, per.nombres, per.apellidos
ORDER BY total_atendidas DESC;


-- =============================================================================
-- 3. SUBCONSULTAS (EXISTS, NOT EXISTS, IN, ESCALARES Y TABLAS DERIVADAS)
-- =============================================================================

-- 3.1. Subconsulta con NOT EXISTS: Verificar si un bloque horario está libre antes de reservar (CU07 - RF07, Prueba #4)
SELECT 
    m.id_medico,
    m.codigo_medico,
    per.nombres || ' ' || per.apellidos AS medico
FROM medico m
INNER JOIN persona per ON m.id_medico = per.id_persona
WHERE m.id_medico = :id_medico
  AND m.estado = 'Activo'
  -- Subconsulta 1: No existe cita activa que se cruce en ese horario
  AND NOT EXISTS (
      SELECT 1 
      FROM cita_medica cm
      WHERE cm.id_medico = m.id_medico
        AND cm.fecha_cita = :fecha_cita
        AND cm.estado <> 'Cancelada'
        AND (:hora_inicio < cm.hora_fin AND :hora_fin > cm.hora_inicio)
  )
  -- Subconsulta 2: No existe bloqueo por cirugía de emergencia o feriado
  AND NOT EXISTS (
      SELECT 1 
      FROM bloqueo_agenda ba
      WHERE ba.id_medico = m.id_medico
        AND ba.fecha_bloqueo = :fecha_cita
        AND (:hora_inicio < ba.hora_fin AND :hora_fin > ba.hora_inicio)
  );

-- 3.2. Subconsulta con EXISTS e IN: Médicos disponibles para una especialidad acorde a la edad del paciente (CU07)
SELECT 
    m.id_medico,
    m.codigo_medico,
    per.nombres || ' ' || per.apellidos AS nombre_medico,
    m.nro_matricula
FROM medico m
INNER JOIN persona per ON m.id_medico = per.id_persona
WHERE m.estado = 'Activo'
  AND m.id_medico IN (
      SELECT me.id_medico
      FROM medico_especialidad me
      INNER JOIN especialidad e ON me.id_especialidad = e.id_especialidad
      WHERE e.id_especialidad = :id_especialidad
        AND e.estado = 'Activo'
        AND (
            SELECT EXTRACT(YEAR FROM age(CURRENT_DATE, pac.fecha_nacimiento))::INT
            FROM paciente pac
            WHERE pac.id_paciente = :id_paciente
        ) BETWEEN e.edad_minima AND e.edad_maxima
  )
  AND EXISTS (
      SELECT 1 
      FROM horario_atencion h
      WHERE h.id_medico = m.id_medico
        AND h.estado = 'Activo'
  );

-- 3.3. Subconsultas Escalares Correlacionadas en SELECT: Padrón de pacientes con resumen de comportamiento (CU08, CU16 - RF13)
SELECT 
    pac.codigo_paciente,
    per.ci,
    per.nombres || ' ' || per.apellidos AS paciente,
    per.telefono,
    (
        SELECT COUNT(*) 
        FROM cita_medica cm 
        WHERE cm.id_paciente = pac.id_paciente AND cm.estado = 'Atendida'
    ) AS citas_atendidas,
    (
        SELECT COUNT(*) 
        FROM cita_medica cm 
        WHERE cm.id_paciente = pac.id_paciente AND cm.estado = 'Inasistencia'
    ) AS total_inasistencias,
    (
        SELECT MAX(cm.fecha_cita) 
        FROM cita_medica cm 
        WHERE cm.id_paciente = pac.id_paciente AND cm.estado = 'Atendida'
    ) AS ultima_fecha_atencion
FROM paciente pac
INNER JOIN persona per ON pac.id_paciente = per.id_persona
WHERE pac.estado = 'Activo'
ORDER BY citas_atendidas DESC;

-- 3.4. Subconsulta en HAVING con Tabla Derivada: Especialidades con demanda superior al promedio de la clínica (CU16, CU17)
SELECT 
    e.id_especialidad,
    e.nombre AS especialidad,
    COUNT(cm.id_cita) AS total_citas_especialidad
FROM especialidad e
INNER JOIN cita_medica cm ON e.id_especialidad = cm.id_especialidad
WHERE cm.estado <> 'Cancelada'
GROUP BY e.id_especialidad, e.nombre
HAVING COUNT(cm.id_cita) > (
    SELECT AVG(conteo_por_esp.total)
    FROM (
        SELECT COUNT(id_cita) AS total
        FROM cita_medica
        WHERE estado <> 'Cancelada'
        GROUP BY id_especialidad
    ) AS conteo_por_esp
)
ORDER BY total_citas_especialidad DESC;

-- 3.5. Subconsulta con NOT IN: Citas programadas pendientes de pago electrónico en Stripe (CU14)
SELECT 
    cm.id_cita,
    cm.codigo_cita,
    cm.fecha_cita,
    cm.hora_inicio,
    e.nombre AS especialidad,
    e.arancel_consulta AS monto_a_pagar
FROM cita_medica cm
INNER JOIN especialidad e ON cm.id_especialidad = e.id_especialidad
WHERE cm.id_paciente = :id_paciente
  AND cm.estado = 'Programada'
  AND cm.id_cita NOT IN (
      SELECT pg.id_cita 
      FROM pago pg 
      WHERE pg.estado_pago = 'Pagado'
  )
ORDER BY cm.fecha_cita ASC;

-- 3.6. Subconsulta para detectar citas afectadas por una Cirugía/Emergencia Médica (CU09 - RF12)
SELECT 
    cm.id_cita,
    cm.codigo_cita,
    cm.fecha_cita,
    cm.hora_inicio,
    per.nombres || ' ' || per.apellidos AS paciente,
    u.correo AS correo_notificacion
FROM cita_medica cm
INNER JOIN persona per ON cm.id_paciente = per.id_persona
LEFT JOIN usuario u ON per.id_persona = u.id_persona
WHERE cm.id_medico = :id_medico
  AND cm.fecha_cita = :fecha_emergencia
  AND cm.estado IN ('Programada', 'Confirmada')
  AND EXISTS (
      SELECT 1
      FROM bloqueo_agenda ba
      WHERE ba.id_medico = cm.id_medico
        AND ba.fecha_bloqueo = cm.fecha_cita
        AND ba.tipo_bloqueo = 'Cirugia_Emergencia'
        AND (cm.hora_inicio < ba.hora_fin AND cm.hora_fin > ba.hora_inicio)
  )
ORDER BY cm.hora_inicio ASC;