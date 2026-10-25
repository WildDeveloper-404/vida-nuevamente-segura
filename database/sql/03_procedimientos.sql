-- =============================================================================
-- SECCIÓN DE PROCEDIMIENTOS Y FUNCIONES ALMACENADAS POR CASO DE USO
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. FUNCIÓN: Control de intentos de Login y bloqueo progresivo (CU01)
-- -----------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_registrar_intento_login(
    p_correo VARCHAR,
    p_exito BOOLEAN,
    p_ip VARCHAR DEFAULT '127.0.0.1'
) RETURNS TABLE (
    permitido BOOLEAN,
    mensaje VARCHAR,
    id_usuario_out INT,
    id_rol_out INT,
    nombre_rol_out VARCHAR
) AS $$
DECLARE
    v_user RECORD;
    v_minutos_bloqueo INT;
BEGIN
    SELECT u.*, r.nombre_rol INTO v_user
    FROM usuario u
    INNER JOIN rol r ON u.id_rol = r.id_rol
    WHERE LOWER(u.correo) = LOWER(TRIM(p_correo));

    IF NOT FOUND THEN
        RETURN QUERY SELECT FALSE, 'El correo o la contraseña son incorrectos'::VARCHAR, NULL::INT, NULL::INT, NULL::VARCHAR;
        RETURN;
    END IF;

    IF v_user.estado <> 'Activo' THEN
        RETURN QUERY SELECT FALSE, 'Cuenta desactivada, contacte al administrador'::VARCHAR, NULL::INT, NULL::INT, NULL::VARCHAR;
        RETURN;
    END IF;

    IF v_user.bloqueado_hasta IS NOT NULL AND v_user.bloqueado_hasta > CURRENT_TIMESTAMP THEN
        RETURN QUERY SELECT FALSE, ('Demasiados intentos fallidos. Intente nuevamente después de ' || TO_CHAR(v_user.bloqueado_hasta, 'HH24:MI:SS'))::VARCHAR, NULL::INT, NULL::INT, NULL::VARCHAR;
        RETURN;
    END IF;

    IF p_exito THEN
        UPDATE usuario 
        SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_acceso = CURRENT_TIMESTAMP
        WHERE id_usuario = v_user.id_usuario;

        INSERT INTO bitacora (id_usuario, modulo, accion, descripcion, direccion_ip)
        VALUES (v_user.id_usuario, 'Seguridad', 'LOGIN_EXITOSO', 'Inicio de sesión exitoso con correo ' || p_correo, p_ip);

        RETURN QUERY SELECT TRUE, 'Inicio de sesión exitoso'::VARCHAR, v_user.id_usuario, v_user.id_rol, v_user.nombre_rol;
    ELSE
        v_user.intentos_fallidos := v_user.intentos_fallidos + 1;
        IF (v_user.intentos_fallidos % 3) = 0 THEN
            -- Bloqueo progresivo (Nivel 1: 1 min, Nivel 2: 5 min, Nivel 3+: 15 min)
            v_minutos_bloqueo := CASE 
                WHEN v_user.intentos_fallidos = 3 THEN 1
                WHEN v_user.intentos_fallidos = 6 THEN 5
                ELSE 15
            END;
            UPDATE usuario 
            SET intentos_fallidos = v_user.intentos_fallidos,
                bloqueado_hasta = CURRENT_TIMESTAMP + (v_minutos_bloqueo || ' minutes')::INTERVAL
            WHERE id_usuario = v_user.id_usuario;
        ELSE
            UPDATE usuario SET intentos_fallidos = v_user.intentos_fallidos WHERE id_usuario = v_user.id_usuario;
        END IF;

        INSERT INTO bitacora (id_usuario, modulo, accion, descripcion, direccion_ip)
        VALUES (v_user.id_usuario, 'Seguridad', 'LOGIN_FALLIDO', 'Intento fallido #' || v_user.intentos_fallidos, p_ip);

        RETURN QUERY SELECT FALSE, 'El correo o la contraseña son incorrectos'::VARCHAR, NULL::INT, NULL::INT, NULL::VARCHAR;
    END IF;
END;
$$ LANGUAGE plpgsql;

-- -----------------------------------------------------------------------------
-- 2. PROCEDIMIENTO: Registrar Paciente y Auto-registro con Perfil Clínico (CU06, CU10)
-- -----------------------------------------------------------------------------
CREATE OR REPLACE PROCEDURE sp_registrar_paciente_completo(
    IN p_ci VARCHAR,
    IN p_nombres VARCHAR,
    IN p_apellidos VARCHAR,
    IN p_sexo CHAR(1),
    IN p_fecha_nacimiento DATE,
    IN p_telefono VARCHAR,
    IN p_correo VARCHAR,
    IN p_direccion VARCHAR,
    IN p_password_hash VARCHAR DEFAULT NULL,
    IN p_tipo_sangre VARCHAR DEFAULT 'O+',
    OUT p_id_paciente_creado INT,
    OUT p_codigo_generado VARCHAR
)
LANGUAGE plpgsql AS $$
DECLARE
    v_id_persona INT;
    v_id_rol_paciente INT;
BEGIN
    -- Validar unicidad de CI (Prueba #2 y RN01)
    IF EXISTS (SELECT 1 FROM persona WHERE ci = TRIM(p_ci)) THEN
        RAISE EXCEPTION 'RN01 (Prueba #2): Ya existe un paciente registrado con el documento de identidad (CI) %.', p_ci;
    END IF;

    -- Insertar en superclase persona
    INSERT INTO persona (ci, nombres, apellidos, telefono, direccion)
    VALUES (TRIM(p_ci), TRIM(p_nombres), TRIM(p_apellidos), TRIM(p_telefono), p_direccion)
    RETURNING id_persona INTO v_id_persona;

    -- Generar código único PAC-XXXX
    p_codigo_generado := 'PAC-' || LPAD(nextval('seq_codigo_paciente')::TEXT, 4, '0');

    -- Insertar en subclase paciente
    INSERT INTO paciente (id_paciente, codigo_paciente, sexo, fecha_nacimiento, estado)
    VALUES (v_id_persona, p_codigo_generado, p_sexo, p_fecha_nacimiento, 'Activo');

    -- Crear su expediente de perfil clínico básico (CU10)
    INSERT INTO perfil_clinico (id_paciente, tipo_sangre)
    VALUES (v_id_persona, p_tipo_sangre);

    -- Si se proporciona password_hash, crear su cuenta de acceso por correo electrónico
    IF p_password_hash IS NOT NULL AND p_correo IS NOT NULL THEN
        SELECT id_rol INTO v_id_rol_paciente FROM rol WHERE nombre_rol = 'Paciente';
        INSERT INTO usuario (id_persona, correo, password_hash, id_rol, estado)
        VALUES (v_id_persona, LOWER(TRIM(p_correo)), p_password_hash, v_id_rol_paciente, 'Activo');
    END IF;

    p_id_paciente_creado := v_id_persona;

    INSERT INTO bitacora (modulo, accion, descripcion)
    VALUES ('Pacientes', 'ALTA_PACIENTE', 'Paciente registrado con código ' || p_codigo_generado || ' y CI ' || p_ci);
END;
$$;

-- -----------------------------------------------------------------------------
-- 3. FUNCIÓN: Consultar Bloques Horarios Disponibles de un Médico (CU05, CU07)
-- -----------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_obtener_bloques_disponibles(
    p_id_medico INT,
    p_fecha DATE
) RETURNS TABLE (
    hora_inicio_slot TIME,
    hora_fin_slot TIME,
    estado_slot VARCHAR
) AS $$
DECLARE
    v_dia_nombre VARCHAR(15);
    r_horario RECORD;
    v_slot_actual TIME;
    v_slot_fin TIME;
    v_ocupado BOOLEAN;
BEGIN
    v_dia_nombre := CASE EXTRACT(ISODOW FROM p_fecha)
        WHEN 1 THEN 'Lunes'
        WHEN 2 THEN 'Martes'
        WHEN 3 THEN 'Miercoles'
        WHEN 4 THEN 'Jueves'
        WHEN 5 THEN 'Viernes'
        WHEN 6 THEN 'Sabado'
        WHEN 7 THEN 'Domingo'
    END;

    FOR r_horario IN 
        SELECT hora_inicio, hora_fin, duracion_minutos
        FROM horario_atencion
        WHERE id_medico = p_id_medico AND dia_semana = v_dia_nombre AND estado = 'Activo'
        ORDER BY hora_inicio
    LOOP
        v_slot_actual := r_horario.hora_inicio;
        WHILE (v_slot_actual + (r_horario.duracion_minutos || ' minutes')::INTERVAL)::TIME <= r_horario.hora_fin LOOP
            v_slot_fin := (v_slot_actual + (r_horario.duracion_minutos || ' minutes')::INTERVAL)::TIME;

            -- Verificar si el bloque está ocupado por cita activa o bloqueo de emergencia
            v_ocupado := EXISTS (
                SELECT 1 FROM cita_medica
                WHERE id_medico = p_id_medico AND fecha_cita = p_fecha
                  AND estado <> 'Cancelada'
                  AND (v_slot_actual < hora_fin AND v_slot_fin > hora_inicio)
            ) OR EXISTS (
                SELECT 1 FROM bloqueo_agenda
                WHERE id_medico = p_id_medico AND fecha_bloqueo = p_fecha
                  AND (v_slot_actual < hora_fin AND v_slot_fin > hora_inicio)
            );

            hora_inicio_slot := v_slot_actual;
            hora_fin_slot := v_slot_fin;
            estado_slot := CASE WHEN v_ocupado THEN 'Ocupado' ELSE 'Disponible' END;
            RETURN NEXT;

            v_slot_actual := v_slot_fin;
        END LOOP;
    END LOOP;
END;
$$ LANGUAGE plpgsql;

-- -----------------------------------------------------------------------------
-- 4. PROCEDIMIENTO: Programar Cita con Bloqueo Transaccional Anti-Colisión (CU07, RN13)
-- -----------------------------------------------------------------------------
CREATE OR REPLACE PROCEDURE sp_programar_cita(
    IN p_id_paciente INT,
    IN p_id_medico INT,
    IN p_id_especialidad INT,
    IN p_fecha_cita DATE,
    IN p_hora_inicio TIME,
    IN p_motivo_consulta VARCHAR,
    IN p_id_usuario_operador INT,
    OUT p_id_cita_creada INT,
    OUT p_codigo_cita_out VARCHAR
)
LANGUAGE plpgsql AS $$
DECLARE
    v_duracion INT;
    v_hora_fin TIME;
    v_correo_pac VARCHAR(120);
BEGIN
    -- Bloquear fila del médico para evitar condiciones de carrera simultáneas (RN13)
    PERFORM 1 FROM medico WHERE id_medico = p_id_medico FOR UPDATE;

    -- Obtener duración configurada para calcular hora_fin
    SELECT duracion_minutos INTO v_duracion
    FROM horario_atencion
    WHERE id_medico = p_id_medico AND estado = 'Activo'
      AND p_hora_inicio >= hora_inicio AND p_hora_inicio < hora_fin
    LIMIT 1;

    v_duracion := COALESCE(v_duracion, 30);
    v_hora_fin := (p_hora_inicio + (v_duracion || ' minutes')::INTERVAL)::TIME;

    p_codigo_cita_out := 'CM-' || LPAD(nextval('seq_codigo_cita')::TEXT, 4, '0');

    -- El Trigger trg_validar_cita_medica verificará edad, horario, bloqueos y no solapamiento
    INSERT INTO cita_medica (
        codigo_cita, id_paciente, id_medico, id_especialidad, 
        fecha_cita, hora_inicio, hora_fin, motivo_consulta, estado
    ) VALUES (
        p_codigo_cita_out, p_id_paciente, p_id_medico, p_id_especialidad,
        p_fecha_cita, p_hora_inicio, v_hora_fin, p_motivo_consulta, 'Programada'
    ) RETURNING id_cita INTO p_id_cita_creada;

    -- Registrar notificación de confirmación/recordatorio si el paciente tiene correo
    SELECT u.correo INTO v_correo_pac 
    FROM usuario u WHERE u.id_persona = p_id_paciente;

    IF v_correo_pac IS NOT NULL THEN
        INSERT INTO notificacion_cita (id_cita, tipo, mensaje, correo_destino)
        VALUES (p_id_cita_creada, 'Recordatorio', 
                'Cita programada con código ' || p_codigo_cita_out || ' para el ' || p_fecha_cita || ' a las ' || p_hora_inicio,
                v_correo_pac);
    END IF;

    INSERT INTO bitacora (id_usuario, modulo, accion, descripcion)
    VALUES (p_id_usuario_operador, 'Citas', 'PROGRAMAR_CITA', 'Cita creada: ' || p_codigo_cita_out);
END;
$$;

-- -----------------------------------------------------------------------------
-- 5. PROCEDIMIENTO: Reprogramación Masiva por Cirugía o Emergencia Médica (CU09)
--    Bloquea el turno afectado, traslada las fichas al siguiente turno libre
--    del médico y genera las notificaciones por correo electrónico.
-- -----------------------------------------------------------------------------
CREATE OR REPLACE PROCEDURE sp_reprogramar_masivo_emergencia(
    IN p_id_medico INT,
    IN p_fecha_emergencia DATE,
    IN p_hora_inicio_emergencia TIME,
    IN p_hora_fin_emergencia TIME,
    IN p_motivo_emergencia VARCHAR,
    IN p_id_usuario_operador INT,
    OUT p_total_citas_trasladadas INT
)
LANGUAGE plpgsql AS $$
DECLARE
    r_cita RECORD;
    v_fecha_busqueda DATE;
    r_slot_libre RECORD;
    v_encontrado BOOLEAN;
    v_correo_pac VARCHAR(120);
BEGIN
    p_total_citas_trasladadas := 0;

    -- 1. Registrar el bloqueo de agenda por cirugía/emergencia para impedir nuevas reservas
    INSERT INTO bloqueo_agenda (id_medico, fecha_bloqueo, hora_inicio, hora_fin, tipo_bloqueo, motivo)
    VALUES (p_id_medico, p_fecha_emergencia, p_hora_inicio_emergencia, p_hora_fin_emergencia, 'Cirugia_Emergencia', p_motivo_emergencia);

    -- 2. Recorrer todas las citas activas (Programadas o Confirmadas) afectadas en ese turno
    FOR r_cita IN
        SELECT * FROM cita_medica
        WHERE id_medico = p_id_medico
          AND fecha_cita = p_fecha_emergencia
          AND estado IN ('Programada', 'Confirmada')
          AND (hora_inicio < p_hora_fin_emergencia AND hora_fin > p_hora_inicio_emergencia)
        ORDER BY hora_inicio
    LOOP
        v_encontrado := FALSE;
        v_fecha_busqueda := p_fecha_emergencia;

        -- Buscar el siguiente bloque disponible del médico (desde el mismo día más tarde o días siguientes)
        WHILE NOT v_encontrado AND v_fecha_busqueda <= (p_fecha_emergencia + INTERVAL '30 days')::DATE LOOP
            SELECT hora_inicio_slot, hora_fin_slot INTO r_slot_libre
            FROM fn_obtener_bloques_disponibles(p_id_medico, v_fecha_busqueda)
            WHERE estado_slot = 'Disponible'
              AND (v_fecha_busqueda > p_fecha_emergencia OR hora_inicio_slot >= p_hora_fin_emergencia)
            ORDER BY hora_inicio_slot
            LIMIT 1;

            IF FOUND THEN
                v_encontrado := TRUE;
                -- Actualizar la cita trasladándola al nuevo turno libre
                UPDATE cita_medica
                SET fecha_cita = v_fecha_busqueda,
                    hora_inicio = r_slot_libre.hora_inicio_slot,
                    hora_fin = r_slot_libre.hora_fin_slot
                WHERE id_cita = r_cita.id_cita;

                -- Obtener correo del paciente y registrar notificación urgente (CU09)
                SELECT correo INTO v_correo_pac FROM usuario WHERE id_persona = r_cita.id_paciente;

                INSERT INTO notificacion_cita (id_cita, tipo, mensaje, correo_destino, estado_envio)
                VALUES (
                    r_cita.id_cita,
                    'Reprogramacion_Emergencia',
                    'Aviso importante: Por motivos de cirugía/emergencia médica (' || p_motivo_emergencia || 
                    '), su cita ' || r_cita.codigo_cita || ' fue trasladada al día ' || v_fecha_busqueda || 
                    ' a las ' || r_slot_libre.hora_inicio_slot,
                    COALESCE(v_correo_pac, 'sin-correo@clinica.bo'),
                    CASE WHEN v_correo_pac IS NOT NULL THEN 'Enviado' ELSE 'Fallo_Correo' END
                );

                p_total_citas_trasladadas := p_total_citas_trasladadas + 1;
            ELSE
                v_fecha_busqueda := v_fecha_busqueda + 1;
            END IF;
        END LOOP;
    END LOOP;

    -- 3. Auditar en bitácora (CU03)
    INSERT INTO bitacora (id_usuario, modulo, accion, descripcion)
    VALUES (p_id_usuario_operador, 'Contingencias', 'REPROGRAMACION_MASIVA_EMERGENCIA',
            'Médico ID ' || p_id_medico || ' en emergencia. Citas trasladadas: ' || p_total_citas_trasladadas);
END;
$$;

-- -----------------------------------------------------------------------------
-- 6. FUNCIÓN: Reporte Estadístico Consolidado por Período (CU16 - RF13 Formato 1)
-- -----------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_reporte_consolidado_citas(
    p_fecha_inicio DATE,
    p_fecha_fin DATE
) RETURNS TABLE (
    especialidad_nombre VARCHAR,
    total_programadas BIGINT,
    total_confirmadas BIGINT,
    total_atendidas BIGINT,
    total_canceladas BIGINT,
    total_inasistencias BIGINT,
    total_general BIGINT
) AS $$
BEGIN
    IF p_fecha_fin < p_fecha_inicio THEN
        RAISE EXCEPTION 'RF13: La fecha final no puede ser anterior a la fecha inicial.';
    END IF;

    RETURN QUERY
    SELECT 
        e.nombre AS especialidad_nombre,
        COUNT(*) FILTER (WHERE c.estado = 'Programada') AS total_programadas,
        COUNT(*) FILTER (WHERE c.estado = 'Confirmada') AS total_confirmadas,
        COUNT(*) FILTER (WHERE c.estado = 'Atendida') AS total_atendidas,
        COUNT(*) FILTER (WHERE c.estado = 'Cancelada') AS total_canceladas,
        COUNT(*) FILTER (WHERE c.estado = 'Inasistencia') AS total_inasistencias,
        COUNT(c.id_cita) AS total_general
    FROM especialidad e
    LEFT JOIN cita_medica c ON e.id_especialidad = c.id_especialidad 
        AND c.fecha_cita BETWEEN p_fecha_inicio AND p_fecha_fin
    GROUP BY e.id_especialidad, e.nombre
    ORDER BY total_general DESC;
END;
$$ LANGUAGE plpgsql;