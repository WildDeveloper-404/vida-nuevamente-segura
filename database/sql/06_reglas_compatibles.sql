CREATE OR REPLACE FUNCTION fn_trg_validar_cita_medica()
RETURNS TRIGGER AS $$
DECLARE
    v_estado_paciente VARCHAR(20);
    v_fecha_nac DATE;
    v_edad_paciente INT;
    v_estado_medico VARCHAR(20);
    v_edad_min INT;
    v_edad_max INT;
    v_nom_esp VARCHAR(100);
    v_dia_nombre VARCHAR(15);
    v_duracion INT;
BEGIN
    -- 1. Generar código único correlativo si no se envía (RF06)
    IF NEW.codigo_cita IS NULL OR NEW.codigo_cita = '' THEN
        NEW.codigo_cita := 'CM-' || LPAD(nextval('seq_codigo_cita')::TEXT, 4, '0');
    END IF;

    -- Los cambios de estado no deben revalidar horarios históricos ni impedir cancelar tras una baja.
    IF TG_OP = 'UPDATE' AND NEW.id_paciente = OLD.id_paciente AND NEW.id_medico = OLD.id_medico
       AND NEW.id_especialidad = OLD.id_especialidad AND NEW.fecha_cita = OLD.fecha_cita
       AND NEW.hora_inicio = OLD.hora_inicio AND NEW.hora_fin = OLD.hora_fin THEN
        RETURN NEW;
    END IF;

    -- 2. Validar estado activo del paciente y obtener su fecha de nacimiento
    SELECT estado, fecha_nacimiento INTO v_estado_paciente, v_fecha_nac
    FROM paciente WHERE id_paciente = NEW.id_paciente;

    IF v_estado_paciente <> 'Activo' THEN
        RAISE EXCEPTION 'El paciente seleccionado se encuentra Inactivo y no puede programar citas.';
    END IF;

    PERFORM 1 FROM medico WHERE id_medico = NEW.id_medico FOR UPDATE;

    -- 3. Validar estado activo del médico y pertenencia a la especialidad (RN04)
    SELECT estado INTO v_estado_medico FROM medico WHERE id_medico = NEW.id_medico;
    IF v_estado_medico <> 'Activo' THEN
        RAISE EXCEPTION 'RN04: El médico seleccionado se encuentra Inactivo y no recibe nuevas citas.';
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM medico_especialidad 
        WHERE id_medico = NEW.id_medico AND id_especialidad = NEW.id_especialidad
    ) THEN
        RAISE EXCEPTION 'El médico seleccionado no pertenece a la especialidad indicada.';
    END IF;

    -- 4. Validar coherencia etaria del paciente con la especialidad (Pediatría / Geriatría)
    SELECT nombre, edad_minima, edad_maxima INTO v_nom_esp, v_edad_min, v_edad_max
    FROM especialidad WHERE id_especialidad = NEW.id_especialidad;

    v_edad_paciente := EXTRACT(YEAR FROM age(NEW.fecha_cita, v_fecha_nac));
    IF v_edad_paciente < v_edad_min OR v_edad_paciente > v_edad_max THEN
        RAISE EXCEPTION 'Edad incompatible: El paciente tiene % años y la especialidad % admite pacientes de % a % años.', 
            v_edad_paciente, v_nom_esp, v_edad_min, v_edad_max;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM especialidad WHERE id_especialidad=NEW.id_especialidad AND estado='Activo') THEN
        RAISE EXCEPTION 'La especialidad se encuentra inactiva.';
    END IF;

    -- 5. Validar no programar en fechas pasadas al crear o reprogramar fecha (RN06 - Prueba #5)
    IF (TG_OP = 'INSERT') OR (TG_OP = 'UPDATE' AND NEW.fecha_cita <> OLD.fecha_cita) THEN
        IF NEW.fecha_cita < CURRENT_DATE THEN
            RAISE EXCEPTION 'RN06 (Prueba #5): No se pueden programar ni reprogramar citas en fechas pasadas.';
        END IF;
    END IF;

    -- 6. Determinar el día de la semana de la fecha elegida
    v_dia_nombre := CASE EXTRACT(ISODOW FROM NEW.fecha_cita)
        WHEN 1 THEN 'Lunes'
        WHEN 2 THEN 'Martes'
        WHEN 3 THEN 'Miercoles'
        WHEN 4 THEN 'Jueves'
        WHEN 5 THEN 'Viernes'
        WHEN 6 THEN 'Sabado'
        WHEN 7 THEN 'Domingo'
    END;

    -- 7. Verificar que la cita esté dentro del horario de disponibilidad del médico (RN05 - Prueba #5)
    SELECT duracion_minutos INTO v_duracion
    FROM horario_atencion
    WHERE id_medico = NEW.id_medico
      AND dia_semana = v_dia_nombre
      AND estado = 'Activo'
      AND NEW.hora_inicio >= hora_inicio
      AND NEW.hora_fin <= hora_fin
    LIMIT 1;

    IF v_duracion IS NULL THEN
        RAISE EXCEPTION 'RN05 (Prueba #5): La cita está fuera del horario de atención configurado para el médico en día %.', v_dia_nombre;
    END IF;

    -- 8. Verificar que el turno no esté bloqueado por cirugía de emergencia o feriado (CU05 / CU09)
    IF EXISTS (
        SELECT 1 FROM bloqueo_agenda
        WHERE id_medico = NEW.id_medico
          AND fecha_bloqueo = NEW.fecha_cita
          AND (NEW.hora_inicio < hora_fin AND NEW.hora_fin > hora_inicio)
    ) THEN
        RAISE EXCEPTION 'Agenda bloqueada: El médico no atiende en ese horario por feriado o cirugía de emergencia.';
    END IF;

    -- 9. Verificar que no exista colisión ni superposición de horario para el médico (RF07, RN07 - Prueba #4)
    IF NEW.estado <> 'Cancelada' THEN
        IF EXISTS (
            SELECT 1 FROM cita_medica
            WHERE id_medico = NEW.id_medico
              AND fecha_cita = NEW.fecha_cita
              AND estado <> 'Cancelada'
              AND id_cita <> COALESCE(NEW.id_cita, -1)
              AND (NEW.hora_inicio < hora_fin AND NEW.hora_fin > hora_inicio)
        ) THEN
            RAISE EXCEPTION 'RF07 / RN07 (Prueba #4): El médico seleccionado ya tiene una cita activa programada en ese horario.';
        END IF;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;


CREATE OR REPLACE FUNCTION fn_trg_maquina_estados_cita() RETURNS TRIGGER AS $$
BEGIN
    IF OLD.estado IN ('Atendida','Cancelada','Inasistencia') AND
       (NEW.estado <> OLD.estado OR NEW.fecha_cita <> OLD.fecha_cita OR NEW.hora_inicio <> OLD.hora_inicio OR NEW.hora_fin <> OLD.hora_fin OR NEW.id_medico <> OLD.id_medico OR NEW.id_paciente <> OLD.id_paciente OR NEW.id_especialidad <> OLD.id_especialidad) THEN
        RAISE EXCEPTION 'Una cita finalizada no admite cambios de estado ni reprogramación.';
    END IF;
    IF NEW.estado <> OLD.estado AND NOT (
       (OLD.estado='Programada' AND NEW.estado IN ('Confirmada','Cancelada','Inasistencia')) OR
       (OLD.estado='Confirmada' AND NEW.estado IN ('Atendida','Cancelada','Inasistencia'))
    ) THEN RAISE EXCEPTION 'La transición de estado solicitada no está permitida.'; END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
