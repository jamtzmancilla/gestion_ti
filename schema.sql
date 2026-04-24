-- ============================================================
--  SISTEMA DE GESTIÓN DE EQUIPOS TI
--  schema.sql  — ejecutar una sola vez
-- ============================================================

CREATE DATABASE IF NOT EXISTS gestion_ti
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gestion_ti;

-- ── Áreas / Departamentos ─────────────────────────────────
CREATE TABLE IF NOT EXISTS areas (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre     VARCHAR(150) NOT NULL,
    descripcion TEXT,
    activo     TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Usuarios ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS usuarios (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    area_id        INT UNSIGNED,
    nombre         VARCHAR(200) NOT NULL,
    apellidos      VARCHAR(200),
    email          VARCHAR(150),
    telefono       VARCHAR(30),
    puesto         VARCHAR(150),
    usuario_windows VARCHAR(80),
    contrasena_ref  VARCHAR(200) COMMENT 'Referencia/hash, no texto claro',
    activo         TINYINT(1) DEFAULT 1,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── Tipos de equipo ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS tipos_equipo (
    id     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL,   -- COMPUTADORA, ESCANER, TELEFONO, CAMARA, IMPRESORA, SERVIDOR, etc.
    icono  VARCHAR(40) DEFAULT 'bi-pc-display'
) ENGINE=InnoDB;

-- ── Equipos ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS equipos (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo_id         INT UNSIGNED,
    area_id         INT UNSIGNED,
    usuario_id      INT UNSIGNED,
    folio           VARCHAR(50) UNIQUE,
    marca           VARCHAR(80),
    modelo          VARCHAR(120),
    serie           VARCHAR(120),
    descripcion     TEXT,
    ip              VARCHAR(45),
    mac_address     VARCHAR(20),
    nodo            VARCHAR(50),
    conexion_medio  VARCHAR(80),   -- CABLE, WIFI, FIBRA...
    ubicacion       VARCHAR(200),
    estado          ENUM('ACTIVO','INACTIVO','BAJA','MANTENIMIENTO') DEFAULT 'ACTIVO',
    fecha_adquisicion DATE,
    foto            VARCHAR(255),
    activo          TINYINT(1) DEFAULT 1,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tipo_id)    REFERENCES tipos_equipo(id) ON DELETE SET NULL,
    FOREIGN KEY (area_id)    REFERENCES areas(id)        ON DELETE SET NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)     ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── Inventario de periféricos/accesorios ─────────────────
CREATE TABLE IF NOT EXISTS inventario (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipo_id   INT UNSIGNED,
    usuario_id  INT UNSIGNED,
    area_id     INT UNSIGNED,
    tipo        VARCHAR(80)  NOT NULL,  -- MONITOR, TECLADO, MOUSE, HEADSET...
    marca       VARCHAR(80),
    modelo      VARCHAR(120),
    serie       VARCHAR(120),
    cantidad    SMALLINT DEFAULT 1,
    estado      ENUM('BUENO','REGULAR','MALO','BAJA') DEFAULT 'BUENO',
    notas       TEXT,
    activo      TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (equipo_id)  REFERENCES equipos(id)  ON DELETE SET NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (area_id)    REFERENCES areas(id)    ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── Tickets / Soporte ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS tickets (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    folio_ticket   VARCHAR(20) UNIQUE,
    equipo_id      INT UNSIGNED,
    usuario_id     INT UNSIGNED,
    tipo_servicio  ENUM('MANTENIMIENTO_PREVENTIVO','MANTENIMIENTO_CORRECTIVO','SOPORTE','INSTALACION','OTRO') DEFAULT 'SOPORTE',
    prioridad      ENUM('BAJA','MEDIA','ALTA','URGENTE') DEFAULT 'MEDIA',
    estado         ENUM('ABIERTO','EN_PROCESO','RESUELTO','CANCELADO') DEFAULT 'ABIERTO',
    descripcion    TEXT,
    tecnico        VARCHAR(150),
    fecha_apertura DATETIME DEFAULT CURRENT_TIMESTAMP,
    fecha_cierre   DATETIME,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (equipo_id)  REFERENCES equipos(id)  ON DELETE SET NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── Checklist de mantenimiento (cabecera) ─────────────────
CREATE TABLE IF NOT EXISTS mantenimientos (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id       INT UNSIGNED,
    equipo_id       INT UNSIGNED NOT NULL,
    usuario_id      INT UNSIGNED,
    tecnico         VARCHAR(150),
    tipo            ENUM('PREVENTIVO','CORRECTIVO') DEFAULT 'PREVENTIVO',
    fecha           DATE NOT NULL,
    observaciones   TEXT,
    firma_tecnico   VARCHAR(200),
    firma_usuario   VARCHAR(200),
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id)  REFERENCES tickets(id)  ON DELETE SET NULL,
    FOREIGN KEY (equipo_id)  REFERENCES equipos(id)  ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── Checklist items de mantenimiento ─────────────────────
CREATE TABLE IF NOT EXISTS mantenimiento_checks (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mantenimiento_id  INT UNSIGNED NOT NULL,
    categoria         VARCHAR(80)  NOT NULL,  -- LIMPIEZA_FISICA, LIMPIEZA_LOGICA, HARDWARE, SOFTWARE
    item              VARCHAR(200) NOT NULL,
    resultado         ENUM('OK','REQUIERE_ATENCION','NO_APLICA','MAL') DEFAULT 'NO_APLICA',
    notas             VARCHAR(500),
    FOREIGN KEY (mantenimiento_id) REFERENCES mantenimientos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── Índices útiles ────────────────────────────────────────
CREATE INDEX idx_equipos_ip  ON equipos(ip);
CREATE INDEX idx_equipos_mac ON equipos(mac_address);
CREATE INDEX idx_tickets_estado ON tickets(estado);

-- ══════════════════════════════════════════════════════════
--  DATOS DE EJEMPLO
-- ══════════════════════════════════════════════════════════

INSERT INTO areas (nombre) VALUES
('DIRECCIÓN GENERAL'),('SUBDIRECCIÓN DE EVALUACIÓN'),
('DEPARTAMENTO DE PSICOLOGÍA'),('DEPARTAMENTO DE POLIGRAFÍA'),
('ENLACE ADMINISTRATIVO'),('DEPARTAMENTO DE MEDICINA Y TOXICOLOGÍA'),
('DEPARTAMENTO DE INVESTIGACIÓN SOCIOECONÓMICA'),('SOPORTE TI');

INSERT INTO tipos_equipo (nombre, icono) VALUES
('COMPUTADORA',  'bi-pc-display'),
('LAPTOP',       'bi-laptop'),
('SERVIDOR',     'bi-server'),
('IMPRESORA',    'bi-printer'),
('ESCÁNER',      'bi-upc-scan'),
('TELÉFONO IP',  'bi-telephone'),
('CÁMARA',       'bi-camera-video'),
('SWITCH/ROUTER','bi-router'),
('CHECADOR',     'bi-fingerprint'),
('PERIFÉRICO',   'bi-mouse2');

INSERT INTO usuarios (area_id, nombre, apellidos, puesto, usuario_windows, email) VALUES
(1,'HILDA','SANTILLANO LARES','DIRECTORA GENERAL','hsantillano','hsantillano@org.gob.mx'),
(2,'RAQUEL','CASTELÁN GARCÍA','SUBDIRECTORA DE EVALUACIÓN','rcastelan','rcastelan@org.gob.mx'),
(3,'JOSÉ JUAN','GONZÁLEZ GONZÁLEZ','ENCARGADO PSICOLOGÍA','jgonzalez','jgonzalez@org.gob.mx'),
(5,'KARINA','ALONSO HERNANDEZ','DELEGADA ADMINISTRATIVA','kalonso','kalonso@org.gob.mx'),
(8,'HUGO','CORTES CHANTRES','TÉCNICO TI','hcortes','hcortes@org.gob.mx');

INSERT INTO equipos (tipo_id,area_id,usuario_id,folio,marca,modelo,serie,ip,mac_address,nodo,conexion_medio,ubicacion,estado) VALUES
(1,1,1,'EQ-0001','DELL','OPTIPLEX 7090','DL2023A001','192.168.1.10','AA:BB:CC:DD:EE:01','NODO-01','CABLE','DIRECCIÓN GENERAL','ACTIVO'),
(1,2,2,'EQ-0002','HP','PRODESK 400 G7','HP2023B001','192.168.1.11','AA:BB:CC:DD:EE:02','NODO-01','CABLE','SUBDIRECCIÓN EVALUACIÓN','ACTIVO'),
(1,3,3,'EQ-0003','LENOVO','THINKCENTRE M70Q','LV2023C001','192.168.1.12','AA:BB:CC:DD:EE:03','NODO-02','CABLE','DEPTO. PSICOLOGÍA','ACTIVO'),
(4,5,NULL,'EQ-0004','HP','LASERJET PRO M404','HP2022P001','192.168.1.50','AA:BB:CC:DD:EE:10','NODO-03','CABLE','ENLACE ADMINISTRATIVO','ACTIVO'),
(6,1,NULL,'EQ-0005','CISCO','IP PHONE 7945','CS2021T001','192.168.1.101','AA:BB:CC:DD:EE:20','NODO-01','CABLE','DIRECCIÓN GENERAL','ACTIVO'),
(3,8,NULL,'EQ-0006','HP','PROLIANT DL380','HP2020S001','192.168.1.2','AA:BB:CC:DD:EE:FF','NODO-CORE','FIBRA','SITE - RACK PRINCIPAL','ACTIVO');

INSERT INTO inventario (equipo_id,usuario_id,area_id,tipo,marca,modelo,estado) VALUES
(1,1,1,'MONITOR','DELL','P2422H','BUENO'),
(1,1,1,'TECLADO','DELL','KB216','BUENO'),
(1,1,1,'MOUSE','DELL','MS116','BUENO'),
(2,2,2,'MONITOR','HP','P24h G4','BUENO'),
(3,3,3,'MONITOR','LENOVO','ThinkVision T24i','REGULAR');

INSERT INTO tickets (folio_ticket,equipo_id,usuario_id,tipo_servicio,prioridad,estado,descripcion,tecnico) VALUES
('TKT-2024-001',1,1,'MANTENIMIENTO_PREVENTIVO','MEDIA','RESUELTO','Mantenimiento preventivo trimestral','HUGO CORTES CHANTRES'),
('TKT-2024-002',2,2,'SOPORTE','ALTA','EN_PROCESO','Equipo lento, posible virus','HUGO CORTES CHANTRES'),
('TKT-2024-003',3,3,'MANTENIMIENTO_CORRECTIVO','URGENTE','ABIERTO','Falla en disco duro, ruidos extraños','HUGO CORTES CHANTRES');
