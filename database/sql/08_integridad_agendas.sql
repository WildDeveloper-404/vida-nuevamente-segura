-- Ejecutar después de 07, 05 y 06, dentro de la transacción del instalador.
-- Las exclusiones también protegen escrituras concurrentes hechas fuera de PHP.
DO $$ BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'horario_medico_exclusivo' AND conrelid = 'horario_atencion'::regclass) THEN
        ALTER TABLE horario_atencion ADD CONSTRAINT horario_medico_exclusivo EXCLUDE USING gist (
            int8range(id_medico::bigint, id_medico::bigint, '[]') WITH &&,
            numrange(
                array_position(ARRAY['Lunes','Martes','Miercoles','Jueves','Viernes','Sabado','Domingo'], dia_semana) * 86400 + extract(epoch FROM hora_inicio),
                array_position(ARRAY['Lunes','Martes','Miercoles','Jueves','Viernes','Sabado','Domingo'], dia_semana) * 86400 + extract(epoch FROM hora_fin)
            ) WITH &&
        ) WHERE (estado = 'Activo');
    END IF;
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'cita_medico_exclusivo' AND conrelid = 'cita_medica'::regclass) THEN
        ALTER TABLE cita_medica ADD CONSTRAINT cita_medico_exclusivo EXCLUDE USING gist (
            int8range(id_medico::bigint, id_medico::bigint, '[]') WITH &&,
            tsrange(fecha_cita + hora_inicio, fecha_cita + hora_fin) WITH &&
        ) WHERE (estado <> 'Cancelada');
    END IF;
END $$;

-- Consultas del Kardex, agenda, derivaciones y bitácora.
CREATE INDEX IF NOT EXISTS idx_cita_paciente_fecha ON cita_medica(id_paciente, fecha_cita DESC, hora_inicio);
CREATE INDEX IF NOT EXISTS idx_cita_medico_fecha ON cita_medica(id_medico, fecha_cita, hora_inicio);
CREATE INDEX IF NOT EXISTS idx_derivacion_cita_destino ON derivacion_medica(id_cita_destino) WHERE id_cita_destino IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_bitacora_fecha ON bitacora(fecha_hora DESC);
