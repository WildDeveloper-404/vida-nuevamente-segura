-- Ampliación compatible con bases ya instaladas. Ejecutar en una transacción.
ALTER TABLE cita_medica ADD COLUMN IF NOT EXISTS arancel_reservado numeric(10,2);
ALTER TABLE cita_medica ADD COLUMN IF NOT EXISTS moneda_reservada varchar(3);
ALTER TABLE cita_medica ADD COLUMN IF NOT EXISTS id_consultorio integer REFERENCES consultorio(id_consultorio);
ALTER TABLE pago ADD COLUMN IF NOT EXISTS moneda varchar(3);
ALTER TABLE paciente ADD COLUMN IF NOT EXISTS correo_contacto varchar(120);
UPDATE pago SET moneda = COALESCE(NULLIF(current_setting('clinica.moneda_anterior', true), ''), 'usd')
WHERE moneda IS NULL;
UPDATE cita_medica c SET arancel_reservado = COALESCE(
    (SELECT p.monto FROM pago p WHERE p.id_cita = c.id_cita), e.arancel_consulta),
    moneda_reservada = COALESCE((SELECT p.moneda FROM pago p WHERE p.id_cita = c.id_cita),
        NULLIF(current_setting('clinica.moneda_anterior', true), ''), 'usd')
FROM especialidad e WHERE e.id_especialidad = c.id_especialidad AND c.arancel_reservado IS NULL;
ALTER TABLE cita_medica ALTER COLUMN arancel_reservado SET NOT NULL;
ALTER TABLE cita_medica ALTER COLUMN moneda_reservada SET NOT NULL;
ALTER TABLE pago ALTER COLUMN moneda SET NOT NULL;
ALTER TABLE pago ALTER COLUMN moneda SET DEFAULT 'usd';

CREATE OR REPLACE FUNCTION fn_validar_metodo_pago() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF TG_OP = 'UPDATE' AND NEW.metodo_pago IS NOT DISTINCT FROM OLD.metodo_pago THEN
        RETURN NEW;
    END IF;
    IF NEW.metodo_pago NOT IN ('Stripe', 'Efectivo') THEN
        RAISE EXCEPTION 'Seleccione tarjeta por Stripe o efectivo en caja.';
    END IF;
    RETURN NEW;
END $$;

DROP TRIGGER IF EXISTS trg_validar_metodo_pago ON pago;
CREATE TRIGGER trg_validar_metodo_pago BEFORE INSERT OR UPDATE OF metodo_pago ON pago
FOR EACH ROW EXECUTE FUNCTION fn_validar_metodo_pago();

-- Clave conservadora para variantes de escritura en español. Recibe el nombre sin acentos.
CREATE OR REPLACE FUNCTION fn_clave_fonetica_especialidad(nombre text) RETURNS text
LANGUAGE sql IMMUTABLE STRICT PARALLEL SAFE AS $$
    WITH reglas AS (
        SELECT replace(replace(regexp_replace(nombre, 'qu([ei])', 'k\1', 'g'), 'ch', chr(1)), 'll', 'y') AS valor
    ), consonantes AS (
        SELECT regexp_replace(regexp_replace(valor, 'c([ei])', 's\1', 'g'), 'g([ei])', 'j\1', 'g') AS valor
        FROM reglas
    )
    SELECT replace(regexp_replace(translate(valor, 'vzc', 'bsk'), '[h[:space:][:punct:]]+', '', 'g'), chr(1), 'ch')
    FROM consonantes
$$;

CREATE UNIQUE INDEX IF NOT EXISTS idx_especialidad_fonetica
ON especialidad (fn_clave_fonetica_especialidad(nombre_normalizado));

DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'cita_consultorio_exclusivo' AND conrelid = 'cita_medica'::regclass) THEN
        UPDATE cita_medica c SET id_consultorio = h.id_consultorio
        FROM horario_atencion h WHERE c.id_consultorio IS NULL AND c.id_medico = h.id_medico
            AND h.estado = 'Activo' AND h.id_consultorio IS NOT NULL
            AND h.dia_semana = (ARRAY['Lunes','Martes','Miercoles','Jueves','Viernes','Sabado','Domingo'])[extract(isodow FROM c.fecha_cita)::int]
            AND c.hora_inicio >= h.hora_inicio AND c.hora_fin <= h.hora_fin
            AND c.estado IN ('Programada','Confirmada') AND c.fecha_cita + c.hora_inicio > LOCALTIMESTAMP;
        INSERT INTO rol_permiso (id_rol, id_permiso)
        SELECT r.id_rol, p.id_permiso FROM rol r CROSS JOIN permiso p
        WHERE r.nombre_rol = 'Administrador' AND p.nombre_permiso IN ('pacientes', 'contingencias')
        ON CONFLICT DO NOTHING;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'horario_consultorio_exclusivo' AND conrelid = 'horario_atencion'::regclass) THEN
        ALTER TABLE horario_atencion ADD CONSTRAINT horario_consultorio_exclusivo EXCLUDE USING gist (
            int8range(id_consultorio::bigint, id_consultorio::bigint, '[]') WITH &&,
            numrange(
                array_position(ARRAY['Lunes','Martes','Miercoles','Jueves','Viernes','Sabado','Domingo'], dia_semana) * 86400 + extract(epoch FROM hora_inicio),
                array_position(ARRAY['Lunes','Martes','Miercoles','Jueves','Viernes','Sabado','Domingo'], dia_semana) * 86400 + extract(epoch FROM hora_fin)
            ) WITH &&
        ) WHERE (id_consultorio IS NOT NULL AND estado = 'Activo');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'cita_consultorio_exclusivo' AND conrelid = 'cita_medica'::regclass) THEN
        ALTER TABLE cita_medica ADD CONSTRAINT cita_consultorio_exclusivo EXCLUDE USING gist (
            int8range(id_consultorio::bigint, id_consultorio::bigint, '[]') WITH &&,
            tsrange(fecha_cita + hora_inicio, fecha_cita + hora_fin) WITH &&
        ) WHERE (id_consultorio IS NOT NULL AND estado <> 'Cancelada');
    END IF;
END $$;

ALTER TABLE notificacion_cita ADD COLUMN IF NOT EXISTS enviar_desde timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE notificacion_cita ADD COLUMN IF NOT EXISTS respuesta varchar(30);
ALTER TABLE notificacion_cita ADD COLUMN IF NOT EXISTS fecha_respuesta timestamp;
ALTER TABLE notificacion_cita DROP CONSTRAINT IF EXISTS notificacion_cita_estado_envio_check;
ALTER TABLE notificacion_cita ADD CONSTRAINT notificacion_cita_estado_envio_check
    CHECK (estado_envio IN ('Pendiente','Enviado','Fallo_Correo','Anulado'));
CREATE INDEX IF NOT EXISTS idx_notificacion_despacho ON notificacion_cita(enviar_desde, id_notificacion) WHERE estado_envio = 'Pendiente';

CREATE TABLE IF NOT EXISTS devolucion_pago (
    id_pago integer PRIMARY KEY REFERENCES pago(id_pago),
    estado varchar(24) NOT NULL DEFAULT 'Pendiente'
        CHECK (estado IN ('Pendiente','Procesando','Devuelto','Revisar')),
    solicitada_en timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    referencia varchar(120),
    actualizada_en timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE derivacion_medica ADD COLUMN IF NOT EXISTS id_cita_destino integer REFERENCES cita_medica(id_cita);
ALTER TABLE derivacion_medica DROP CONSTRAINT IF EXISTS derivacion_medica_prioridad_check;
ALTER TABLE derivacion_medica ALTER COLUMN prioridad SET DEFAULT 'Rutinaria';
UPDATE derivacion_medica SET prioridad = CASE prioridad WHEN 'Normal' THEN 'Rutinaria' ELSE 'Prioritaria' END
WHERE prioridad IN ('Normal','Preferente');
ALTER TABLE derivacion_medica ADD CONSTRAINT derivacion_medica_prioridad_check
    CHECK (prioridad IN ('Rutinaria','Prioritaria','Urgente'));
ALTER TABLE receta_medica ADD COLUMN IF NOT EXISTS cie10 varchar(8);
ALTER TABLE receta_medica ADD COLUMN IF NOT EXISTS huella_integridad varchar(64);
ALTER TABLE detalle_receta ADD COLUMN IF NOT EXISTS principio_activo varchar(150);

CREATE OR REPLACE FUNCTION fn_trg_verificar_alergia_receta() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.tipo_item = 'Medicamento' AND EXISTS (
        SELECT 1 FROM receta_medica r
        JOIN cita_medica c ON c.id_cita = r.id_cita
        JOIN perfil_clinico p ON p.id_paciente = c.id_paciente
        CROSS JOIN LATERAL regexp_split_to_table(unaccent(lower(p.alergias)), '[,;[:cntrl:]]+') AS a(nombre)
        CROSS JOIN LATERAL regexp_split_to_table(
            unaccent(lower(COALESCE(NEW.principio_activo, NEW.medicamento_o_examen))), '[,;+/]+'
        ) AS f(nombre)
        WHERE r.id_receta = NEW.id_receta
            AND btrim(f.nombre) <> '' AND btrim(a.nombre) = btrim(f.nombre)
    ) THEN
        RAISE EXCEPTION 'ALERTA CLÍNICA: El principio activo coincide con una alergia confirmada. Sustituya el medicamento.';
    END IF;
    RETURN NEW;
END $$;

CREATE OR REPLACE FUNCTION fn_proteger_receta_emitida() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE receta integer;
BEGIN
    IF TG_TABLE_NAME = 'receta_medica' THEN
        IF OLD.huella_integridad IS NOT NULL THEN
            RAISE EXCEPTION 'Una receta emitida no admite modificaciones.';
        END IF;
    ELSE
        receta := CASE WHEN TG_OP = 'INSERT' THEN NEW.id_receta ELSE OLD.id_receta END;
        IF EXISTS (SELECT 1 FROM receta_medica WHERE huella_integridad IS NOT NULL
            AND (id_receta = receta OR (TG_OP = 'UPDATE' AND id_receta = NEW.id_receta))) THEN
            RAISE EXCEPTION 'Una receta emitida no admite modificaciones.';
        END IF;
    END IF;
    IF TG_OP = 'DELETE' THEN RETURN OLD; END IF;
    RETURN NEW;
END $$;

DROP TRIGGER IF EXISTS trg_proteger_receta ON receta_medica;
CREATE TRIGGER trg_proteger_receta BEFORE UPDATE OR DELETE ON receta_medica
FOR EACH ROW EXECUTE FUNCTION fn_proteger_receta_emitida();
DROP TRIGGER IF EXISTS trg_proteger_detalle_receta ON detalle_receta;
CREATE TRIGGER trg_proteger_detalle_receta BEFORE INSERT OR UPDATE OR DELETE ON detalle_receta
FOR EACH ROW EXECUTE FUNCTION fn_proteger_receta_emitida();

CREATE OR REPLACE FUNCTION fn_trg_confirmar_cita_por_pago() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.estado_pago = 'Pagado' THEN
        UPDATE cita_medica SET estado = 'Confirmada' WHERE id_cita = NEW.id_cita AND estado = 'Programada';
        INSERT INTO bitacora (modulo, accion, descripcion) VALUES
            ('Pagos', 'PAGO_CONFIRMADO', 'Pago ' || NEW.id_pago || ' registrado para cita ' || NEW.id_cita);
    END IF;
    RETURN NEW;
END $$;

ALTER TABLE bitacora ADD COLUMN IF NOT EXISTS valores_anteriores jsonb;
ALTER TABLE bitacora ADD COLUMN IF NOT EXISTS valores_nuevos jsonb;

CREATE OR REPLACE FUNCTION fn_auditar_mutacion() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE
    antes jsonb := CASE WHEN TG_OP <> 'INSERT' THEN to_jsonb(OLD) END;
    despues jsonb := CASE WHEN TG_OP <> 'DELETE' THEN to_jsonb(NEW) END;
    claves text[] := ARRAY[
        'password_hash','token_recuperacion','codigo_qr','referencia_stripe','referencia',
        'diagnostico','cie10','huella_integridad','motivo_clinico','motivo_consulta','motivo_cancelacion','motivo',
        'alergias','tipo_sangre','antecedentes_relevantes','enfermedades_base','medicamentos_habituales',
        'medicamento_o_examen','principio_activo','dosis','frecuencia','duracion',
        'mensaje','texto_entrada','respuesta_emitida','sql_generado',
        'nombres','apellidos','ci','telefono','correo','correo_contacto','correo_destino','direccion',
        'fecha_nacimiento','sexo','nit_ci','razon_social','concepto',
        'contacto_emergencia_nombre','contacto_emergencia_parentesco','contacto_emergencia_telefono'
    ];
    actor integer;
BEGIN
    IF antes IS NOT DISTINCT FROM despues THEN RETURN NULL; END IF;
    actor := NULLIF(current_setting('clinica.actor', true), '')::integer;
    IF TG_OP = 'UPDATE' THEN
        antes := (SELECT jsonb_object_agg(key, value) FROM jsonb_each(antes) WHERE value IS DISTINCT FROM despues -> key);
        despues := (SELECT jsonb_object_agg(key, value) FROM jsonb_each(despues) WHERE antes ? key);
    END IF;
    -- Conservar qué campos cambiaron sin copiar secretos ni datos clínicos a la bitácora administrativa.
    antes := (SELECT jsonb_object_agg(key, CASE WHEN key = ANY(claves) THEN to_jsonb('[reservado]'::text) ELSE value END) FROM jsonb_each(antes));
    despues := (SELECT jsonb_object_agg(key, CASE WHEN key = ANY(claves) THEN to_jsonb('[reservado]'::text) ELSE value END) FROM jsonb_each(despues));
    INSERT INTO bitacora (id_usuario, modulo, accion, descripcion, direccion_ip, valores_anteriores, valores_nuevos)
    VALUES ((SELECT id_usuario FROM usuario WHERE id_usuario = actor), TG_TABLE_NAME, TG_OP,
        'Cambio registrado automáticamente en ' || TG_TABLE_NAME,
        COALESCE(NULLIF(current_setting('clinica.ip', true), ''), '127.0.0.1'), antes, despues);
    RETURN NULL;
END $$;

DO $$
DECLARE tabla text;
BEGIN
    FOREACH tabla IN ARRAY ARRAY['rol','modulo','permiso','rol_permiso','persona','personal_administrativo',
        'usuario','especialidad','medico','medico_especialidad','consultorio','horario_atencion','bloqueo_agenda',
        'paciente','perfil_clinico','cita_medica','notificacion_cita','receta_medica','detalle_receta',
        'derivacion_medica','pago','comprobante','consulta_asistente_ia','devolucion_pago']
    LOOP
        EXECUTE format('DROP TRIGGER IF EXISTS trg_auditar_mutacion ON %I', tabla);
        EXECUTE format('CREATE TRIGGER trg_auditar_mutacion AFTER INSERT OR UPDATE OR DELETE ON %I FOR EACH ROW EXECUTE FUNCTION fn_auditar_mutacion()', tabla);
    END LOOP;
END $$;

DROP TRIGGER IF EXISTS trg_bitacora_inmutable ON bitacora;
CREATE TRIGGER trg_bitacora_inmutable BEFORE UPDATE OR DELETE OR TRUNCATE ON bitacora
FOR EACH STATEMENT EXECUTE FUNCTION fn_trg_prohibir_borrado_fisico();

CREATE OR REPLACE FUNCTION fn_programar_recordatorio() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE
    correo_paciente varchar(120);
    turno timestamp := NEW.fecha_cita + NEW.hora_inicio;
BEGIN
    IF TG_OP = 'UPDATE' THEN
        IF (NEW.fecha_cita, NEW.hora_inicio, NEW.estado) IS NOT DISTINCT FROM (OLD.fecha_cita, OLD.hora_inicio, OLD.estado) THEN
            RETURN NULL;
        END IF;
        IF NEW.estado IN ('Programada','Confirmada') AND (NEW.fecha_cita, NEW.hora_inicio) = (OLD.fecha_cita, OLD.hora_inicio) THEN
            RETURN NULL;
        END IF;
    END IF;
    UPDATE notificacion_cita SET estado_envio = 'Anulado'
    WHERE id_cita = NEW.id_cita AND tipo = 'Recordatorio' AND estado_envio = 'Pendiente';
    IF NEW.estado IN ('Programada','Confirmada') AND turno > LOCALTIMESTAMP THEN
        SELECT COALESCE(u.correo, p.correo_contacto) INTO correo_paciente
        FROM paciente p LEFT JOIN usuario u ON u.id_persona = p.id_paciente
        WHERE p.id_paciente = NEW.id_paciente;
        IF correo_paciente IS NOT NULL THEN
            INSERT INTO notificacion_cita (id_cita, tipo, mensaje, correo_destino, estado_envio, enviar_desde)
            VALUES (NEW.id_cita, 'Recordatorio', 'Recordatorio de la cita ' || NEW.codigo_cita ||
                ' para el ' || NEW.fecha_cita || ' a las ' || NEW.hora_inicio,
                correo_paciente, 'Pendiente', turno - interval '24 hours');
        END IF;
    END IF;
    RETURN NULL;
END $$;

DROP TRIGGER IF EXISTS trg_recordatorio ON cita_medica;
CREATE TRIGGER trg_recordatorio AFTER INSERT OR UPDATE ON cita_medica
FOR EACH ROW EXECUTE FUNCTION fn_programar_recordatorio();

-- Conservar los avisos anteriores y poner los recordatorios pendientes en su ventana correcta.
UPDATE notificacion_cita n SET enviar_desde = c.fecha_cita + c.hora_inicio - interval '24 hours',
    estado_envio = CASE WHEN c.estado IN ('Programada','Confirmada') AND c.fecha_cita + c.hora_inicio > LOCALTIMESTAMP
        THEN 'Pendiente' ELSE 'Anulado' END
FROM cita_medica c WHERE n.id_cita = c.id_cita AND n.tipo = 'Recordatorio' AND n.estado_envio = 'Pendiente'
    AND (n.enviar_desde <> c.fecha_cita + c.hora_inicio - interval '24 hours' OR c.fecha_cita + c.hora_inicio <= LOCALTIMESTAMP
        OR c.estado NOT IN ('Programada','Confirmada'));
