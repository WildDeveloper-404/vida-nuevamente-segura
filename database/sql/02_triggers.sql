-- =============================================================================
-- SECCIÓN DE TRIGGERS: REGLAS DE NEGOCIO Y VALIDACIONES EN EL SERVIDOR
-- =============================================================================

-- -----------------------------------------------------------------------------
-- TRIGGER 1: Normalizar nombre de Especialidad y evitar duplicados (RF04)
-- -----------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_trg_normalizar_especialidad()
RETURNS TRIGGER AS $$
BEGIN
    NEW.nombre_normalizado := LOWER(TRIM(unaccent(NEW.nombre)));
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_normalizar_especialidad
BEFORE INSERT OR UPDATE ON especialidad
FOR EACH ROW EXECUTE FUNCTION fn_trg_normalizar_especialidad();

-- -----------------------------------------------------------------------------
-- TRIGGER 2: Calcular cupos automáticamente y evitar solapamiento de horarios (CU05, RF05, RN08)
-- -----------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_trg_validar_horario_medico()
RETURNS TRIGGER AS $$
DECLARE
    v_estado_medico VARCHAR(20);
    v_minutos_totales INT;
BEGIN
    -- Verificar que el médico esté Activo (RN04)
    SELECT estado INTO v_estado_medico FROM medico WHERE id_medico = NEW.id_medico;
    IF v_estado_medico <> 'Activo' THEN
        RAISE EXCEPTION 'RN04: No se pueden configurar horarios para un médico en estado Inactivo.';
    END IF;

    -- Verificar que no se cruce con otro horario del mismo médico en el mismo día
    IF EXISTS (
        SELECT 1 FROM horario_atencion
        WHERE id_medico = NEW.id_medico
          AND dia_semana = NEW.dia_semana
          AND estado = 'Activo'
          AND id_horario <> COALESCE(NEW.id_horario, -1)
          AND (NEW.hora_inicio < hora_fin AND NEW.hora_fin > hora_inicio)
    ) THEN
        RAISE EXCEPTION 'RF05: El médico seleccionado ya tiene un turno configurado que se superpone en ese día y horario.';
    END IF;

    -- Calcular automáticamente el cupo_maximo según la duración en minutos (RN08)
    v_minutos_totales := EXTRACT(EPOCH FROM (NEW.hora_fin - NEW.hora_inicio)) / 60;
    NEW.cupo_maximo := FLOOR(v_minutos_totales / NEW.duracion_minutos);

    IF NEW.cupo_maximo <= 0 THEN
        RAISE EXCEPTION 'RF05: El rango horario es insuficiente para la duración de cita seleccionada.';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_validar_horario_medico
BEFORE INSERT OR UPDATE ON horario_atencion
FOR EACH ROW EXECUTE FUNCTION fn_trg_validar_horario_medico();

-- -----------------------------------------------------------------------------
-- TRIGGER 3: Prohibición estricta de eliminación física (RN11 y RN10)
-- -----------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_trg_prohibir_borrado_fisico()
RETURNS TRIGGER AS $$
BEGIN
    RAISE EXCEPTION 'RN11 / RN10: Prohibido eliminar físicamente registros de la tabla [%]. Utilice la baja lógica (estado = Inactivo o Cancelada) para conservar la trazabilidad histórica.', TG_TABLE_NAME;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_no_delete_usuario BEFORE DELETE ON usuario FOR EACH ROW EXECUTE FUNCTION fn_trg_prohibir_borrado_fisico();
CREATE TRIGGER trg_no_delete_paciente BEFORE DELETE ON paciente FOR EACH ROW EXECUTE FUNCTION fn_trg_prohibir_borrado_fisico();
CREATE TRIGGER trg_no_delete_medico BEFORE DELETE ON medico FOR EACH ROW EXECUTE FUNCTION fn_trg_prohibir_borrado_fisico();
CREATE TRIGGER trg_no_delete_cita BEFORE DELETE ON cita_medica FOR EACH ROW EXECUTE FUNCTION fn_trg_prohibir_borrado_fisico();
CREATE TRIGGER trg_no_delete_bitacora BEFORE DELETE ON bitacora FOR EACH ROW EXECUTE FUNCTION fn_trg_prohibir_borrado_fisico();

-- -----------------------------------------------------------------------------
-- TRIGGER 4: Validación integral de Citas Médicas (CU07, RF06, RF07, RN03-RN09)
-- Valida: Estado activo, Rango de edad por especialidad, Fecha futura, 
--         Horario del médico, Bloqueos por emergencia/feriado y No colisión.
-- -----------------------------------------------------------------------------
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

    -- 2. Validar estado activo del paciente y obtener su fecha de nacimiento
    SELECT estado, fecha_nacimiento INTO v_estado_paciente, v_fecha_nac
    FROM paciente WHERE id_paciente = NEW.id_paciente;

    IF v_estado_paciente <> 'Activo' THEN
        RAISE EXCEPTION 'El paciente seleccionado se encuentra Inactivo y no puede programar citas.';
    END IF;

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

CREATE TRIGGER trg_validar_cita_medica
BEFORE INSERT OR UPDATE ON cita_medica
FOR EACH ROW EXECUTE FUNCTION fn_trg_validar_cita_medica();

-- -----------------------------------------------------------------------------
-- TRIGGER 5: Control de Máquina de Estados de la Cita (CU11, RF08, RF09, RF10)
-- -----------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_trg_maquina_estados_cita()
RETURNS TRIGGER AS $$
BEGIN
    -- Una cita Cancelada no puede pasar a Atendida ni Confirmada (RF10)
    IF OLD.estado = 'Cancelada' AND NEW.estado <> 'Cancelada' THEN
        RAISE EXCEPTION 'RF10: Transición inválida. Una cita Cancelada no puede marcarse como %.', NEW.estado;
    END IF;

    -- Una cita ya Atendida no puede cancelarse ni reprogramarse (RF08, RF09, RN10)
    IF OLD.estado = 'Atendida' AND (NEW.estado = 'Cancelada' OR NEW.fecha_cita <> OLD.fecha_cita OR NEW.hora_inicio <> OLD.hora_inicio) THEN
        RAISE EXCEPTION 'RN10: Una cita que ya fue Atendida no puede cancelarse ni reprogramarse.';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_maquina_estados_cita
BEFORE UPDATE ON cita_medica
FOR EACH ROW EXECUTE FUNCTION fn_trg_maquina_estados_cita();

-- -----------------------------------------------------------------------------
-- TRIGGER 6: Alerta de alergia medicamentosa al emitir Receta (CU12)
-- -----------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_trg_verificar_alergia_receta()
RETURNS TRIGGER AS $$
DECLARE
    v_alergias TEXT;
BEGIN
    IF NEW.tipo_item = 'Medicamento' THEN
        SELECT pc.alergias INTO v_alergias
        FROM receta_medica rm
        INNER JOIN cita_medica cm ON rm.id_cita = cm.id_cita
        INNER JOIN perfil_clinico pc ON cm.id_paciente = pc.id_paciente
        WHERE rm.id_receta = NEW.id_receta;

        IF v_alergias IS NOT NULL AND v_alergias <> '' AND v_alergias <> 'Ninguna conocida' THEN
            IF POSITION(LOWER(TRIM(NEW.medicamento_o_examen)) IN LOWER(v_alergias)) > 0 THEN
                RAISE EXCEPTION 'ALERTA CLÍNICA (CU12): El paciente registra alergia a [%]. Sustituya el medicamento prescrito.', NEW.medicamento_o_examen;
            END IF;
        END IF;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_verificar_alergia_receta
BEFORE INSERT OR UPDATE ON detalle_receta
FOR EACH ROW EXECUTE FUNCTION fn_trg_verificar_alergia_receta();

-- -----------------------------------------------------------------------------
-- TRIGGER 7: Confirmación automática de cita tras Pago en Stripe y Bitácora (CU03, CU14)
-- -----------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fn_trg_confirmar_cita_por_pago()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.estado_pago = 'Pagado' THEN
        UPDATE cita_medica 
        SET estado = 'Confirmada' 
        WHERE id_cita = NEW.id_cita AND estado = 'Programada';

        INSERT INTO bitacora (modulo, accion, descripcion)
        VALUES ('Pagos', 'PAGO_STRIPE_CONFIRMADO', 
                'Pago confirmado ref: ' || NEW.referencia_stripe || ' para cita ID: ' || NEW.id_cita);
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_confirmar_cita_por_pago
AFTER INSERT OR UPDATE ON pago
FOR EACH ROW EXECUTE FUNCTION fn_trg_confirmar_cita_por_pago();