-- =============================================================================
-- SISTEMA WEB DE GESTIÓN DE CITAS MÉDICAS - CLÍNICA "VIDA NUEVAMENTE SEGURA"
-- MOTOR: PostgreSQL 15+
-- =============================================================================

BEGIN;

-- Extensiones útiles para normalización de texto
CREATE EXTENSION IF NOT EXISTS unaccent;

-- Secuencias para códigos correlativos automáticos (RN01, RN02, RF06)
CREATE SEQUENCE IF NOT EXISTS seq_codigo_paciente START 1;
CREATE SEQUENCE IF NOT EXISTS seq_codigo_medico START 1;
CREATE SEQUENCE IF NOT EXISTS seq_codigo_cita START 1;
CREATE SEQUENCE IF NOT EXISTS seq_codigo_derivacion START 1;
CREATE SEQUENCE IF NOT EXISTS seq_nro_comprobante START 1;

-- =============================================================================
-- PAQUETE 1: SEGURIDAD, ROLES, PERMISOS Y AUDITORÍA (CU01, CU02, CU03)
-- =============================================================================

CREATE TABLE rol (
    id_rol SERIAL PRIMARY KEY,
    nombre_rol VARCHAR(50) NOT NULL UNIQUE 
        CHECK (nombre_rol IN ('Administrador', 'Recepcionista', 'Medico', 'Paciente')),
    descripcion VARCHAR(200)
);

CREATE TABLE modulo (
    id_modulo SERIAL PRIMARY KEY,
    nombre_modulo VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE permiso (
    id_permiso SERIAL PRIMARY KEY,
    nombre_permiso VARCHAR(100) NOT NULL UNIQUE,
    id_modulo INT NOT NULL,
    CONSTRAINT fk_permiso_modulo FOREIGN KEY (id_modulo) 
        REFERENCES modulo(id_modulo) ON DELETE CASCADE
);

CREATE TABLE rol_permiso (
    id_rol INT NOT NULL,
    id_permiso INT NOT NULL,
    fecha_asignacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_rol, id_permiso),
    CONSTRAINT fk_rp_rol FOREIGN KEY (id_rol) REFERENCES rol(id_rol) ON DELETE CASCADE,
    CONSTRAINT fk_rp_permiso FOREIGN KEY (id_permiso) REFERENCES permiso(id_permiso) ON DELETE CASCADE
);

-- Superclase Persona (Evita redundancia de CI y datos demográficos - RN01)
CREATE TABLE persona (
    id_persona SERIAL PRIMARY KEY,
    ci VARCHAR(20) NOT NULL UNIQUE,
    nombres VARCHAR(80) NOT NULL,
    apellidos VARCHAR(100) NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    direccion VARCHAR(200)
);

CREATE TABLE personal_administrativo (
    id_personal INT PRIMARY KEY,
    cargo VARCHAR(60) NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'Activo' CHECK (estado IN ('Activo', 'Inactivo')),
    CONSTRAINT fk_personal_persona FOREIGN KEY (id_personal) REFERENCES persona(id_persona)
);

CREATE TABLE usuario (
    id_usuario SERIAL PRIMARY KEY,
    id_persona INT NOT NULL UNIQUE,
    correo VARCHAR(120) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'Activo' CHECK (estado IN ('Activo', 'Inactivo')),
    intentos_fallidos INT NOT NULL DEFAULT 0,
    bloqueado_hasta TIMESTAMP NULL,
    token_recuperacion VARCHAR(100) NULL,
    token_expiracion TIMESTAMP NULL,
    ultimo_acceso TIMESTAMP NULL,
    id_rol INT NOT NULL,
    fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_usuario_persona FOREIGN KEY (id_persona) REFERENCES persona(id_persona),
    CONSTRAINT fk_usuario_rol FOREIGN KEY (id_rol) REFERENCES rol(id_rol)
);

CREATE TABLE bitacora (
    id_bitacora SERIAL PRIMARY KEY,
    id_usuario INT NULL,
    modulo VARCHAR(80) NOT NULL,
    accion VARCHAR(100) NOT NULL,
    descripcion TEXT NOT NULL,
    direccion_ip VARCHAR(45) DEFAULT '127.0.0.1',
    fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bitacora_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
);

-- =============================================================================
-- PAQUETE 2: CATÁLOGOS CLÍNICOS, MÉDICOS Y HORARIOS (CU04, CU05)
-- =============================================================================

CREATE TABLE especialidad (
    id_especialidad SERIAL PRIMARY KEY,
    id_especialidad_padre INT NULL,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    nombre_normalizado VARCHAR(100) NOT NULL UNIQUE,
    descripcion VARCHAR(250),
    edad_minima INT NOT NULL DEFAULT 0 CHECK (edad_minima >= 0),
    edad_maxima INT NOT NULL DEFAULT 120 CHECK (edad_maxima >= edad_minima),
    arancel_consulta DECIMAL(10,2) NOT NULL DEFAULT 150.00 CHECK (arancel_consulta >= 0),
    estado VARCHAR(20) NOT NULL DEFAULT 'Activo' CHECK (estado IN ('Activo', 'Inactivo')),
    CONSTRAINT fk_especialidad_padre FOREIGN KEY (id_especialidad_padre) 
        REFERENCES especialidad(id_especialidad)
);

CREATE TABLE medico (
    id_medico INT PRIMARY KEY,
    codigo_medico VARCHAR(20) NOT NULL UNIQUE,
    nro_matricula VARCHAR(50) NOT NULL UNIQUE,
    estado VARCHAR(20) NOT NULL DEFAULT 'Activo' CHECK (estado IN ('Activo', 'Inactivo')),
    CONSTRAINT fk_medico_persona FOREIGN KEY (id_medico) REFERENCES persona(id_persona)
);

CREATE TABLE medico_especialidad (
    id_medico INT NOT NULL,
    id_especialidad INT NOT NULL,
    es_principal BOOLEAN NOT NULL DEFAULT TRUE,
    fecha_certificacion DATE DEFAULT CURRENT_DATE,
    PRIMARY KEY (id_medico, id_especialidad),
    CONSTRAINT fk_me_medico FOREIGN KEY (id_medico) REFERENCES medico(id_medico),
    CONSTRAINT fk_me_especialidad FOREIGN KEY (id_especialidad) REFERENCES especialidad(id_especialidad)
);

CREATE TABLE consultorio (
    id_consultorio SERIAL PRIMARY KEY,
    numero VARCHAR(20) NOT NULL UNIQUE,
    bloque VARCHAR(50) NOT NULL DEFAULT 'Bloque Central',
    piso INT NOT NULL DEFAULT 1,
    estado VARCHAR(20) NOT NULL DEFAULT 'Activo' CHECK (estado IN ('Activo', 'Inactivo'))
);

CREATE TABLE horario_atencion (
    id_horario SERIAL PRIMARY KEY,
    id_medico INT NOT NULL,
    id_consultorio INT NULL,
    dia_semana VARCHAR(15) NOT NULL 
        CHECK (dia_semana IN ('Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado', 'Domingo')),
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    duracion_minutos INT NOT NULL DEFAULT 30 CHECK (duracion_minutos IN (15, 20, 30, 45, 60)),
    cupo_maximo INT NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'Activo' CHECK (estado IN ('Activo', 'Inactivo')),
    CONSTRAINT chk_horario_coherente CHECK (hora_fin > hora_inicio),
    CONSTRAINT fk_horario_medico FOREIGN KEY (id_medico) REFERENCES medico(id_medico),
    CONSTRAINT fk_horario_consultorio FOREIGN KEY (id_consultorio) REFERENCES consultorio(id_consultorio)
);

CREATE TABLE bloqueo_agenda (
    id_bloqueo SERIAL PRIMARY KEY,
    id_medico INT NOT NULL,
    fecha_bloqueo DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    tipo_bloqueo VARCHAR(40) NOT NULL 
        CHECK (tipo_bloqueo IN ('Cirugia_Emergencia', 'Feriado', 'Permiso_Medico')),
    motivo VARCHAR(255) NOT NULL,
    fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_bloqueo_horas CHECK (hora_fin > hora_inicio),
    CONSTRAINT fk_bloqueo_medico FOREIGN KEY (id_medico) REFERENCES medico(id_medico)
);

-- =============================================================================
-- PAQUETE 3: PACIENTES E HISTORIA CLÍNICA (CU06, CU10)
-- =============================================================================

CREATE TABLE paciente (
    id_paciente INT PRIMARY KEY,
    codigo_paciente VARCHAR(20) NOT NULL UNIQUE,
    sexo CHAR(1) NOT NULL CHECK (sexo IN ('M', 'F', 'O')),
    fecha_nacimiento DATE NOT NULL CHECK (fecha_nacimiento <= CURRENT_DATE),
    estado VARCHAR(20) NOT NULL DEFAULT 'Activo' CHECK (estado IN ('Activo', 'Inactivo')),
    fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_paciente_persona FOREIGN KEY (id_paciente) REFERENCES persona(id_persona)
);

CREATE TABLE perfil_clinico (
    id_perfil SERIAL PRIMARY KEY,
    id_paciente INT NOT NULL UNIQUE,
    tipo_sangre VARCHAR(5) CHECK (tipo_sangre IN ('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-')),
    alergias TEXT DEFAULT 'Ninguna conocida',
    enfermedades_base TEXT DEFAULT 'Ninguna',
    medicamentos_habituales TEXT DEFAULT 'Ninguno',
    antecedentes_relevantes TEXT,
    contacto_emergencia_nombre VARCHAR(150),
    contacto_emergencia_parentesco VARCHAR(60),
    contacto_emergencia_telefono VARCHAR(20),
    ultima_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_perfil_paciente FOREIGN KEY (id_paciente) REFERENCES paciente(id_paciente)
);

-- =============================================================================
-- PAQUETE 4: CITAS, NOTIFICACIONES, CONSULTORIO Y PAGOS (CU07-CU09, CU11-CU14)
-- =============================================================================

CREATE TABLE cita_medica (
    id_cita SERIAL PRIMARY KEY,
    codigo_cita VARCHAR(20) NOT NULL UNIQUE,
    id_paciente INT NOT NULL,
    id_medico INT NOT NULL,
    id_especialidad INT NOT NULL,
    id_cita_anterior INT NULL,
    fecha_cita DATE NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin TIME NOT NULL,
    motivo_consulta VARCHAR(255) NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'Programada' 
        CHECK (estado IN ('Programada', 'Confirmada', 'Atendida', 'Cancelada', 'Inasistencia')),
    motivo_cancelacion VARCHAR(255),
    fecha_registro TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_cita_horas CHECK (hora_fin > hora_inicio),
    CONSTRAINT fk_cita_paciente FOREIGN KEY (id_paciente) REFERENCES paciente(id_paciente),
    CONSTRAINT fk_cita_medico FOREIGN KEY (id_medico) REFERENCES medico(id_medico),
    CONSTRAINT fk_cita_especialidad FOREIGN KEY (id_especialidad) REFERENCES especialidad(id_especialidad),
    CONSTRAINT fk_cita_reprogramada FOREIGN KEY (id_cita_anterior) REFERENCES cita_medica(id_cita)
);

-- Índice Único Parcial: Impide dos citas activas para el mismo médico en igual fecha/hora (RF07, RN07, RN09)
CREATE UNIQUE INDEX idx_unico_medico_fecha_hora_activa 
ON cita_medica (id_medico, fecha_cita, hora_inicio) 
WHERE estado <> 'Cancelada';

CREATE TABLE notificacion_cita (
    id_notificacion SERIAL PRIMARY KEY,
    id_cita INT NOT NULL,
    tipo VARCHAR(40) NOT NULL 
        CHECK (tipo IN ('Recordatorio', 'Reprogramacion_Emergencia', 'Confirmacion', 'Cancelacion')),
    mensaje TEXT NOT NULL,
    correo_destino VARCHAR(120) NOT NULL,
    estado_envio VARCHAR(20) NOT NULL DEFAULT 'Enviado' CHECK (estado_envio IN ('Pendiente', 'Enviado', 'Fallo_Correo')),
    fecha_envio TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notif_cita FOREIGN KEY (id_cita) REFERENCES cita_medica(id_cita)
);

CREATE TABLE receta_medica (
    id_receta SERIAL PRIMARY KEY,
    id_cita INT NOT NULL UNIQUE,
    diagnostico TEXT NOT NULL,
    codigo_qr VARCHAR(120) NOT NULL UNIQUE,
    fecha_emision TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_receta_cita FOREIGN KEY (id_cita) REFERENCES cita_medica(id_cita)
);

CREATE TABLE detalle_receta (
    id_detalle SERIAL PRIMARY KEY,
    id_receta INT NOT NULL,
    tipo_item VARCHAR(30) NOT NULL CHECK (tipo_item IN ('Medicamento', 'Examen_Laboratorio')),
    medicamento_o_examen VARCHAR(150) NOT NULL,
    dosis VARCHAR(80),
    frecuencia VARCHAR(80),
    duracion VARCHAR(60),
    CONSTRAINT fk_detalle_receta FOREIGN KEY (id_receta) 
        REFERENCES receta_medica(id_receta) ON DELETE CASCADE
);

CREATE TABLE derivacion_medica (
    id_derivacion SERIAL PRIMARY KEY,
    codigo_derivacion VARCHAR(20) NOT NULL UNIQUE,
    id_cita_origen INT NOT NULL,
    id_especialidad_destino INT NOT NULL,
    prioridad VARCHAR(20) NOT NULL DEFAULT 'Normal' 
        CHECK (prioridad IN ('Normal', 'Preferente', 'Urgente')),
    motivo_clinico TEXT NOT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'Pendiente de programar' 
        CHECK (estado IN ('Pendiente de programar', 'Programada', 'Completada')),
    fecha_emision TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_deriv_cita FOREIGN KEY (id_cita_origen) REFERENCES cita_medica(id_cita),
    CONSTRAINT fk_deriv_esp FOREIGN KEY (id_especialidad_destino) REFERENCES especialidad(id_especialidad)
);

CREATE TABLE pago (
    id_pago SERIAL PRIMARY KEY,
    id_cita INT NOT NULL UNIQUE,
    monto DECIMAL(10,2) NOT NULL CHECK (monto > 0),
    metodo_pago VARCHAR(40) NOT NULL DEFAULT 'Stripe',
    referencia_stripe VARCHAR(120) NOT NULL UNIQUE,
    estado_pago VARCHAR(20) NOT NULL DEFAULT 'Pagado' 
        CHECK (estado_pago IN ('Pendiente', 'Pagado', 'Rechazado')),
    fecha_pago TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pago_cita FOREIGN KEY (id_cita) REFERENCES cita_medica(id_cita)
);

CREATE TABLE comprobante (
    id_comprobante SERIAL PRIMARY KEY,
    id_pago INT NOT NULL UNIQUE,
    nro_comprobante VARCHAR(40) NOT NULL UNIQUE,
    nit_ci VARCHAR(20) NOT NULL,
    razon_social VARCHAR(150) NOT NULL,
    concepto VARCHAR(200) NOT NULL,
    fecha_emision TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_comprobante_pago FOREIGN KEY (id_pago) REFERENCES pago(id_pago)
);

-- =============================================================================
-- PAQUETE 5: REPORTES, ANALÍTICA E INTELIGENCIA ARTIFICIAL (CU15, CU16-CU18)
-- =============================================================================

CREATE TABLE consulta_asistente_ia (
    id_consulta_ia SERIAL PRIMARY KEY,
    id_usuario INT NULL,
    canal VARCHAR(30) NOT NULL CHECK (canal IN ('Chatbot_Angelica', 'Comando_Voz_IA')),
    texto_entrada TEXT NOT NULL,
    sql_generado TEXT NULL,
    respuesta_emitida TEXT NOT NULL,
    fecha_hora TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ia_usuario FOREIGN KEY (id_usuario) REFERENCES usuario(id_usuario)
);

COMMIT;