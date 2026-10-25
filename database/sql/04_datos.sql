-- =============================================================================
-- DATOS SEMILLA OBLIGATORIOS (ROLES, MÓDULOS Y ESPECIALIDADES BASE)
-- =============================================================================

INSERT INTO rol (nombre_rol, descripcion) VALUES
('Administrador', 'Control total de usuarios, médicos, especialidades, horarios y reportes.'),
('Recepcionista', 'Gestión de pacientes, programación/reprogramación de citas, pagos y asistencia.'),
('Medico', 'Consulta de agenda médica, antecedentes clínicos, atención, recetas y derivaciones.'),
('Paciente', 'Auto-registro, reserva de citas, pagos en línea, historial y asistente virtual.');

INSERT INTO modulo (nombre_modulo) VALUES
('Seguridad y Auditoria'),
('Catalogos Clinicos y Horarios'),
('Pacientes e Historia Clinica'),
('Citas, Consultorio y Pagos'),
('Reportes e Inteligencia Artificial');

INSERT INTO especialidad (nombre, descripcion, edad_minima, edad_maxima, arancel_consulta) VALUES
('Medicina General', 'Atención primaria integral para todas las edades', 0, 120, 100.00),
('Pediatría', 'Atención médica exclusiva para niños y adolescentes', 0, 13, 150.00),
('Geriatría', 'Atención especializada para adultos mayores', 60, 120, 180.00),
('Cardiología', 'Diagnóstico y tratamiento de enfermedades cardiovasculares', 14, 120, 250.00),
('Ginecología', 'Salud integral del sistema reproductor femenino', 12, 120, 200.00),
('Traumatología', 'Lesiones del sistema músculo-esquelético', 0, 120, 220.00),
('Oftalmología', 'Salud visual y patologías oculares', 0, 120, 180.00),
('Odontología', 'Salud bucodental general y preventiva', 2, 120, 120.00);