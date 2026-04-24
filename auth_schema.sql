-- ============================================================
--  ADICIÓN AL SCHEMA: Sistema de Autenticación y Control
--  auth_schema.sql — ejecutar sobre la BD gestion_ti existente
-- ============================================================

USE gestion_ti;

-- ── Tabla de cuentas de acceso al sistema ─────────────────
CREATE TABLE IF NOT EXISTS system_users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT UNSIGNED NULL COMMENT 'Vinculado a tabla usuarios (para nivel 3)',
    username        VARCHAR(80)  NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    nombre_completo VARCHAR(200) NOT NULL,
    email           VARCHAR(150),
    rol             ENUM('ADMINISTRADOR','SUPERVISOR_TECNICO','USUARIO') NOT NULL DEFAULT 'USUARIO',
    activo          TINYINT(1)   DEFAULT 1,
    ultimo_acceso   DATETIME     NULL,
    created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── Log de auditoría ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS auditoria (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    suser_id     INT UNSIGNED NULL,
    accion       VARCHAR(100) NOT NULL,
    modulo       VARCHAR(60),
    registro_id  INT UNSIGNED NULL,
    descripcion  TEXT,
    ip_address   VARCHAR(45),
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (suser_id) REFERENCES system_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── Cambios pendientes de aprobación (Supervisor → Admin) ─
CREATE TABLE IF NOT EXISTS cambios_pendientes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    suser_id        INT UNSIGNED NOT NULL  COMMENT 'Supervisor que hizo el cambio',
    modulo          VARCHAR(60)  NOT NULL,  -- tickets, mantenimientos, equipos...
    registro_id     INT UNSIGNED NOT NULL,
    accion          VARCHAR(60)  NOT NULL,  -- UPDATE, INSERT
    datos_json      JSON         NOT NULL,  -- snapshot del cambio propuesto
    descripcion     TEXT,
    estado          ENUM('PENDIENTE','APROBADO','RECHAZADO') DEFAULT 'PENDIENTE',
    admin_id        INT UNSIGNED NULL       COMMENT 'Admin que aprobó/rechazó',
    admin_nota      TEXT,
    fecha_resolucion DATETIME    NULL,
    created_at      TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (suser_id)  REFERENCES system_users(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id)  REFERENCES system_users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── Columnas adicionales en tickets ──────────────────────
-- Quién asigna (solo admin), quién ejecuta (supervisor técnico)
ALTER TABLE tickets
    ADD COLUMN IF NOT EXISTS asignado_por      INT UNSIGNED NULL AFTER tecnico,
    ADD COLUMN IF NOT EXISTS asignado_a_suser  INT UNSIGNED NULL AFTER asignado_por,
    ADD COLUMN IF NOT EXISTS solicitante_suser INT UNSIGNED NULL AFTER asignado_a_suser,
    ADD CONSTRAINT fk_tickets_asig_por  FOREIGN KEY (asignado_por)     REFERENCES system_users(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_tickets_asig_a    FOREIGN KEY (asignado_a_suser)  REFERENCES system_users(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_tickets_solic     FOREIGN KEY (solicitante_suser) REFERENCES system_users(id) ON DELETE SET NULL;

-- ── Columna en mantenimientos: quién la hizo ─────────────
ALTER TABLE mantenimientos
    ADD COLUMN IF NOT EXISTS realizado_por_suser INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS aprobado_por_suser  INT UNSIGNED NULL,
    ADD CONSTRAINT fk_mant_realizado FOREIGN KEY (realizado_por_suser) REFERENCES system_users(id) ON DELETE SET NULL,
    ADD CONSTRAINT fk_mant_aprobado  FOREIGN KEY (aprobado_por_suser)  REFERENCES system_users(id) ON DELETE SET NULL;

-- ════════════════════════════════════════════════════════
--  CUENTAS INICIALES
--  IMPORTANTE: Ejecuta install.php para generar hashes correctos
--  O bien genera manualmente con PHP:
--  php -r "echo password_hash('tu_pass', PASSWORD_BCRYPT, ['cost'=>12]);"
--
--  Las claves de abajo son hash bcrypt de: admin123 / super123 / super123
-- ════════════════════════════════════════════════════════
INSERT IGNORE INTO system_users (username, password_hash, nombre_completo, email, rol) VALUES
('admin',
 '$2y$12$LRB9Q8CkRSZSNkr5OEJJ3.IHFjWyZ6N3d6CiDvP3Km4K9qF5/WZzm',
 'Administrador del Sistema', 'admin@org.gob.mx', 'ADMINISTRADOR'),
('supervisor',
 '$2y$12$X8yPMg5C1vVnM9dpyg6kO.3dDrO4IuiXkZm5aeRWKT6Qqb3LvFGgW',
 'Supervisor Técnico TI', 'supervisor@org.gob.mx', 'SUPERVISOR_TECNICO'),
('usuario1',
 '$2y$12$X8yPMg5C1vVnM9dpyg6kO.3dDrO4IuiXkZm5aeRWKT6Qqb3LvFGgW',
 'Usuario Normal (Demo)', 'usuario1@org.gob.mx', 'USUARIO');

-- ⚠ RECOMENDADO: Usar install.php para configurar contraseñas seguras
