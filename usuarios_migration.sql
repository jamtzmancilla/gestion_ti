-- ============================================================
--  MIGRACIÓN: Módulo Usuarios Extendido + Directorio IP
--  usuarios_migration.sql — ejecutar sobre gestion_ti existente
-- ============================================================

USE gestion_ti;

-- ── Catálogo de Puestos ───────────────────────────────────
CREATE TABLE IF NOT EXISTS cat_puestos (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(200) NOT NULL,
    descripcion TEXT,
    nivel       ENUM('DIRECTIVO','SUBDIRECTIVO','JEFATURA','COORDINACION','OPERATIVO','APOYO') DEFAULT 'OPERATIVO',
    activo      TINYINT(1) DEFAULT 1,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Extender tabla usuarios ───────────────────────────────
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS puesto_id        INT UNSIGNED NULL          AFTER area_id,
    ADD COLUMN IF NOT EXISTS cubículo         VARCHAR(100) DEFAULT NULL  AFTER puesto,
    ADD COLUMN IF NOT EXISTS oficina          VARCHAR(100) DEFAULT NULL  AFTER cubículo,
    ADD COLUMN IF NOT EXISTS extension_ip     VARCHAR(20)  DEFAULT NULL  AFTER oficina,
    ADD COLUMN IF NOT EXISTS telefono_directo VARCHAR(50)  DEFAULT NULL  AFTER extension_ip,
    ADD COLUMN IF NOT EXISTS foto             VARCHAR(255) DEFAULT NULL  AFTER contrasena_ref;

ALTER TABLE usuarios
    ADD CONSTRAINT IF NOT EXISTS fk_usuarios_puesto
    FOREIGN KEY (puesto_id) REFERENCES cat_puestos(id) ON DELETE SET NULL;

-- ── Directorio telefónico IP ──────────────────────────────
CREATE TABLE IF NOT EXISTS directorio (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    area_id          INT UNSIGNED NOT NULL,
    usuario_id       INT UNSIGNED NULL     COMMENT 'Si está vinculado a un usuario del sistema',
    extension        VARCHAR(20)  NOT NULL,
    nombre_completo  VARCHAR(200) DEFAULT NULL COMMENT 'Para entradas sin usuario vinculado',
    puesto_cargo     VARCHAR(200) DEFAULT NULL,
    telefono_directo VARCHAR(100) DEFAULT NULL COMMENT 'Puede tener varios separados por coma',
    ubicacion        VARCHAR(150) DEFAULT NULL COMMENT 'Cubículo, oficina o sala',
    notas            TEXT,
    color_area       VARCHAR(7)   DEFAULT '#c0392b',
    orden            SMALLINT     DEFAULT 0,
    activo           TINYINT(1)   DEFAULT 1,
    created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (area_id)    REFERENCES areas(id)    ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ════════════════════════════════════════════════════════
--  DATOS: Catálogo de Puestos
-- ════════════════════════════════════════════════════════
INSERT IGNORE INTO cat_puestos (nombre, nivel) VALUES
('DIRECTOR/A GENERAL',                          'DIRECTIVO'),
('SUBDIRECTOR/A DE EVALUACIÓN',                 'SUBDIRECTIVO'),
('SUBDIRECTOR/A DE CONTROL Y SEGUIMIENTO',      'SUBDIRECTIVO'),
('ENCARGADO/A DE DEPARTAMENTO',                 'JEFATURA'),
('DELEGADO/A ADMINISTRATIVO/A',                 'JEFATURA'),
('ENLACE JURÍDICO',                             'COORDINACION'),
('ENLACE ADMINISTRATIVO',                       'COORDINACION'),
('SUPERVISOR/A DE EVALUACIÓN',                  'COORDINACION'),
('COORDINADOR/A DE PROCESO',                    'COORDINACION'),
('ENCARGADO/A DE OFICINA',                      'OPERATIVO'),
('TÉCNICO/A EN INFORMÁTICA',                    'OPERATIVO'),
('SECRETARIO/A',                                'APOYO'),
('RECEPCIONISTA',                               'APOYO'),
('TÉCNICO/A EN RECURSOS FINANCIEROS',           'OPERATIVO'),
('TÉCNICO/A EN RECURSOS HUMANOS',               'OPERATIVO'),
('LICENCIADO/A EN PSICOLOGÍA',                  'OPERATIVO'),
('MÉDICO/A',                                    'OPERATIVO'),
('QUÍMICO/A',                                   'OPERATIVO'),
('VIGILANTE / ARCO SUR',                        'APOYO');

-- ════════════════════════════════════════════════════════
--  DATOS: Directorio Telefónico IP (datos de la imagen)
-- ════════════════════════════════════════════════════════

-- Actualizar colores de áreas existentes
UPDATE areas SET nombre='DIRECCIÓN GENERAL'                               WHERE nombre='DIRECCIÓN GENERAL';
UPDATE areas SET nombre='SUBDIRECCIÓN DE EVALUACIÓN'                      WHERE nombre='SUBDIRECCIÓN DE EVALUACIÓN';
UPDATE areas SET nombre='SUBDIRECCIÓN DE CONTROL Y SEGUIMIENTO'           WHERE nombre='SUBDIRECCIÓN DE CONTROL Y SEGUIMIENTO';
UPDATE areas SET nombre='DEPARTAMENTO DE PROTECCIÓN DE LA INFORMACIÓN'    WHERE nombre LIKE '%PROTECCIÓN%';
UPDATE areas SET nombre='DEPARTAMENTO DE PROGRAMACIÓN Y SEGUIMIENTO INTERINSTITUCIONAL' WHERE nombre LIKE '%PROGRAMACIÓN%';
UPDATE areas SET nombre='ENLACE JURÍDICO'                                 WHERE nombre='ENLACE JURÍDICO';
UPDATE areas SET nombre='VIGILANCIA'                                      WHERE nombre='VIGILANCIA';
UPDATE areas SET nombre='DEPARTAMENTO DE INTEGRACIÓN DE RESULTADOS Y CERTIFICACIÓN' WHERE nombre LIKE '%INTEGRACIÓN%';
UPDATE areas SET nombre='DEPARTAMENTO DE PSICOLOGÍA'                      WHERE nombre LIKE '%PSICOLOGÍA%';
UPDATE areas SET nombre='DEPARTAMENTO DE POLIGRAFÍA'                      WHERE nombre LIKE '%POLIGRAFÍA%';
UPDATE areas SET nombre='ENLACE ADMINISTRATIVO'                           WHERE nombre='ENLACE ADMINISTRATIVO';
UPDATE areas SET nombre='DEPARTAMENTO DE MEDICINA Y TOXICOLOGÍA'          WHERE nombre LIKE '%MEDICINA%';
UPDATE areas SET nombre='DEPARTAMENTO DE INVESTIGACIÓN SOCIOECONÓMICA'    WHERE nombre LIKE '%INVESTIGACIÓN SOCIO%';

-- Insertar áreas que puedan faltar
INSERT IGNORE INTO areas (nombre, descripcion) VALUES
('DIRECCIÓN GENERAL',''),
('SUBDIRECCIÓN DE EVALUACIÓN',''),
('SUBDIRECCIÓN DE CONTROL Y SEGUIMIENTO',''),
('DEPARTAMENTO DE PROTECCIÓN DE LA INFORMACIÓN',''),
('DEPARTAMENTO DE PROGRAMACIÓN Y SEGUIMIENTO INTERINSTITUCIONAL',''),
('ENLACE JURÍDICO',''),
('VIGILANCIA',''),
('DEPARTAMENTO DE INTEGRACIÓN DE RESULTADOS Y CERTIFICACIÓN',''),
('DEPARTAMENTO DE PSICOLOGÍA',''),
('DEPARTAMENTO DE POLIGRAFÍA',''),
('ENLACE ADMINISTRATIVO',''),
('DEPARTAMENTO DE MEDICINA Y TOXICOLOGÍA',''),
('DEPARTAMENTO DE INVESTIGACIÓN SOCIOECONÓMICA','');

-- Vaciar y repoblar directorio
TRUNCATE TABLE directorio;

-- ── DIRECCIÓN GENERAL ────────────────────────────────────
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, telefono_directo, color_area, orden)
SELECT a.id,'19050','LIC. HILDA SANTILLANO LARES','DIRECTORA GENERAL','2288423661','#c0392b',1 FROM areas a WHERE a.nombre='DIRECCIÓN GENERAL' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, color_area, orden)
SELECT a.id,'19044','DR. JULIO ROBERTO GUTIERREZ BAXIN','SECRETARÍA','#c0392b',2 FROM areas a WHERE a.nombre='DIRECCIÓN GENERAL' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, color_area, orden)
SELECT a.id,'19051','C. CAROLINA PEREZ ARAUJO','RECEPCION','#c0392b',3 FROM areas a WHERE a.nombre='DIRECCIÓN GENERAL' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, color_area, orden)
SELECT a.id,'19049','SALA DE JUNTAS',NULL,'#c0392b',4 FROM areas a WHERE a.nombre='DIRECCIÓN GENERAL' LIMIT 1;

-- ── SUBDIRECCIÓN DE CONTROL Y SEGUIMIENTO ────────────────
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, telefono_directo, color_area, orden)
SELECT a.id,'19048','LIC. BIANCA VANESSA RIOS ALVAREZ','ENCARGADA DE LA SUBDIRECCIÓN DE CONTROL Y SEGUIMIENTO','2288423663','#c0392b',1 FROM areas a WHERE a.nombre='SUBDIRECCIÓN DE CONTROL Y SEGUIMIENTO' LIMIT 1;

-- ── DEPARTAMENTO DE PROTECCIÓN DE LA INFORMACIÓN ─────────
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, telefono_directo, color_area, orden)
SELECT a.id,'19040','LSCA. FLOR DEYSI VILLARREAL COLLINS','ENCARGADA DPI','2288423665','#c0392b',1 FROM areas a WHERE a.nombre='DEPARTAMENTO DE PROTECCIÓN DE LA INFORMACIÓN' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, ubicacion, color_area, orden)
SELECT a.id,'19056','ARCHIVO GENERAL','DPI',NULL,'#c0392b',2 FROM areas a WHERE a.nombre='DEPARTAMENTO DE PROTECCIÓN DE LA INFORMACIÓN' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, color_area, orden)
SELECT a.id,'19025','SITE','DPI','#c0392b',3 FROM areas a WHERE a.nombre='DEPARTAMENTO DE PROTECCIÓN DE LA INFORMACIÓN' LIMIT 1;

-- ── DEPARTAMENTO DE PROGRAMACIÓN Y SEGUIMIENTO ───────────
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, telefono_directo, color_area, orden)
SELECT a.id,'19047','MTRA. MARA HENEY HERNÁNDEZ MÁRQUEZ','ENC. DEPTO. DE PROG. Y SEG. INTERINSTITUCIONAL','2288423666','#c0392b',1 FROM areas a WHERE a.nombre='DEPARTAMENTO DE PROGRAMACIÓN Y SEGUIMIENTO INTERINSTITUCIONAL' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, telefono_directo, color_area, orden)
SELECT a.id,'19058','ENLACE 1','','2288423667','#c0392b',2 FROM areas a WHERE a.nombre='DEPARTAMENTO DE PROGRAMACIÓN Y SEGUIMIENTO INTERINSTITUCIONAL' LIMIT 1;

-- ── ENLACE JURÍDICO ───────────────────────────────────────
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, color_area, orden)
SELECT a.id,'19046','LIC. OLIVIA HERNANDEZ CRUZ','ENLACE JURÍDICO','#c0392b',1 FROM areas a WHERE a.nombre='ENLACE JURÍDICO' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, color_area, orden)
SELECT a.id,'19060',NULL,'ENLACE JURÍDICO','#c0392b',2 FROM areas a WHERE a.nombre='ENLACE JURÍDICO' LIMIT 1;

-- ── VIGILANCIA ────────────────────────────────────────────
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, telefono_directo, ubicacion, color_area, orden)
SELECT a.id,'19001','ARCO SUR','CONMUTADOR','2288423660, 2288191073, 2288191008 (DIRECTOS)',NULL,'#c0392b',1 FROM areas a WHERE a.nombre='VIGILANCIA' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, color_area, orden)
SELECT a.id,'10031',NULL,'CASETA DE VIGILANCIA C4','#c0392b',2 FROM areas a WHERE a.nombre='VIGILANCIA' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, color_area, orden)
SELECT a.id,'11049',NULL,'CASETA CONSEJO','#c0392b',3 FROM areas a WHERE a.nombre='VIGILANCIA' LIMIT 1;

-- ── SUBDIRECCIÓN DE EVALUACIÓN ────────────────────────────
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, color_area, orden)
SELECT a.id,'19075','LIC. RAQUEL CASTELÁN GARCÍA','SUBDIRECCIÓN DE EVALUACIÓN','#c0392b',1 FROM areas a WHERE a.nombre='SUBDIRECCIÓN DE EVALUACIÓN' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, color_area, orden)
SELECT a.id,'19092',NULL,'SUBDIRECCIÓN DE EVALUACIÓN','#c0392b',2 FROM areas a WHERE a.nombre='SUBDIRECCIÓN DE EVALUACIÓN' LIMIT 1;

-- ── DEPTO. INTEGRACIÓN DE RESULTADOS ─────────────────────
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, telefono_directo, color_area, orden)
SELECT a.id,'19052','LIC. OCTAVIO ADOLFO GONZÁLEZ CÉLIS','ENC. DEPTO. DE INTEGRACIÓN DE RESULTADOS Y CERTIFICACIÓN','2288423668','#c0392b',1 FROM areas a WHERE a.nombre='DEPARTAMENTO DE INTEGRACIÓN DE RESULTADOS Y CERTIFICACIÓN' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, color_area, orden)
SELECT a.id,'19054','INTEGRACIÓN 2','ENC. DEPTO. DE INTEGRACIÓN DE RESULTADOS Y CERTIFICACIÓN','#c0392b',2 FROM areas a WHERE a.nombre='DEPARTAMENTO DE INTEGRACIÓN DE RESULTADOS Y CERTIFICACIÓN' LIMIT 1;

-- ── DEPARTAMENTO DE PSICOLOGÍA ────────────────────────────
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, color_area, orden)
SELECT a.id,'19032','PSIC. JOSÉ JUAN GONZÁLEZ GONZÁLEZ','ENCARGADO DEPARTAMENTO DE PSICOLOGÍA','#c0392b',1 FROM areas a WHERE a.nombre='DEPARTAMENTO DE PSICOLOGÍA' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, color_area, orden)
SELECT a.id,'19033',NULL,'SUPERVISORES PSICOLOGÍA — DEPARTAMENTO DE PSICOLOGÍA','#c0392b',2 FROM areas a WHERE a.nombre='DEPARTAMENTO DE PSICOLOGÍA' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, color_area, orden)
SELECT a.id,'19020',NULL,'APLICACIÓN DE PRUEBAS PSICOMÉTRICAS 1','#c0392b',3 FROM areas a WHERE a.nombre='DEPARTAMENTO DE PSICOLOGÍA' LIMIT 1;

-- ── DEPARTAMENTO DE POLIGRAFÍA ────────────────────────────
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, color_area, orden)
SELECT a.id,'19073','LIC. FRANCISCA ARIZBHÉ BERNAL MATUZ','ENCARGADA DEPTO. DE POLIGRAFÍA','#c0392b',1 FROM areas a WHERE a.nombre='DEPARTAMENTO DE POLIGRAFÍA' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, color_area, orden)
SELECT a.id,'19080',NULL,'MONITOREO — DEPTO. DE POLIGRAFÍA','#c0392b',2 FROM areas a WHERE a.nombre='DEPARTAMENTO DE POLIGRAFÍA' LIMIT 1;

-- ── ENLACE ADMINISTRATIVO ─────────────────────────────────
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, telefono_directo, color_area, orden)
SELECT a.id,'19072','LIC. KARINA ALONSO HERNANDEZ','DELEGACIÓN ADMINISTRATIVA','2288423672','#c0392b',1 FROM areas a WHERE a.nombre='ENLACE ADMINISTRATIVO' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, color_area, orden)
SELECT a.id,'19023','LIC. JOSE LUIS CARMONA GUZMAN','RECURSOS MATERIALES Y SERVICIOS GRALES.','#c0392b',2 FROM areas a WHERE a.nombre='ENLACE ADMINISTRATIVO' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, telefono_directo, color_area, orden)
SELECT a.id,'19024','TEC. LETICIA AGUILAR HERNÁNDEZ','ENC. OFNA. RECURSOS FINANCIEROS','2288423669','#c0392b',3 FROM areas a WHERE a.nombre='ENLACE ADMINISTRATIVO' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, telefono_directo, color_area, orden)
SELECT a.id,'19043','LIC. HUGO ENRIQUE CORTES CHANTRES','OFNA. RECURSOS HUMANOS','2288423670','#c0392b',4 FROM areas a WHERE a.nombre='ENLACE ADMINISTRATIVO' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, telefono_directo, color_area, orden)
SELECT a.id,'19045','MTRO. CARLOS DIDIER POOT HERÁNDEZ','ENC. OFNA. RECURSOS HUMANOS','2288423664','#c0392b',5 FROM areas a WHERE a.nombre='ENLACE ADMINISTRATIVO' LIMIT 1;

-- ── DEPARTAMENTO DE MEDICINA Y TOXICOLOGÍA ────────────────
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, color_area, orden)
SELECT a.id,'19002','Q.C. CARMELITA CÓRDOBA MENDOZA','ENCARGADA DEPTO. DE MEDICINA Y TOXICOLOGÍA','#c0392b',1 FROM areas a WHERE a.nombre='DEPARTAMENTO DE MEDICINA Y TOXICOLOGÍA' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, ubicacion, color_area, orden)
SELECT a.id,'19003',NULL,'ÁREA COMÚN','OFICINA DE EVALUACIÓN TOXICOLÓGICA','#c0392b',2 FROM areas a WHERE a.nombre='DEPARTAMENTO DE MEDICINA Y TOXICOLOGÍA' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, ubicacion, color_area, orden)
SELECT a.id,'19004',NULL,'ÁREA COMÚN','OFICINA DE EVALUACIÓN TOXICOLÓGICA','#c0392b',3 FROM areas a WHERE a.nombre='DEPARTAMENTO DE MEDICINA Y TOXICOLOGÍA' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, color_area, orden)
SELECT a.id,'19005',NULL,'ENFERMERÍA',NULL,'#c0392b',4 FROM areas a WHERE a.nombre='DEPARTAMENTO DE MEDICINA Y TOXICOLOGÍA' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, color_area, orden)
SELECT a.id,'19006','DRA. GPE. DE MONSERRAT MUÑOZ RUIZ','ENC. OFICINA DE EVALUACIÓN MÉDICA','#c0392b',5 FROM areas a WHERE a.nombre='DEPARTAMENTO DE MEDICINA Y TOXICOLOGÍA' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, color_area, orden)
SELECT a.id,'19019',NULL,'COORDINACIÓN PROCESO DE EVALUACIÓN','#c0392b',6 FROM areas a WHERE a.nombre='DEPARTAMENTO DE MEDICINA Y TOXICOLOGÍA' LIMIT 1;

-- ── DEPARTAMENTO DE INVESTIGACIÓN SOCIOECONÓMICA ─────────
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, color_area, orden)
SELECT a.id,'19071','LIC. ALFREDO BASURTO PERDOMO','ENC. DEPTO. DE INVESTIGACIÓN SOCIOECONÓMICA','#c0392b',1 FROM areas a WHERE a.nombre='DEPARTAMENTO DE INVESTIGACIÓN SOCIOECONÓMICA' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, color_area, orden)
SELECT a.id,'19074','LIC. BERTHA YESENIA CONTRERAS PAREDES','SUPERVISORA DE EVALUACIÓN DE ISE','#c0392b',2 FROM areas a WHERE a.nombre='DEPARTAMENTO DE INVESTIGACIÓN SOCIOECONÓMICA' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, ubicacion, color_area, orden)
SELECT a.id,'19022','LIC. PERLA CORAL MARTINEZ SANCHEZ',NULL,'CUB 1 ENTORNO','#c0392b',3 FROM areas a WHERE a.nombre='DEPARTAMENTO DE INVESTIGACIÓN SOCIOECONÓMICA' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, ubicacion, color_area, orden)
SELECT a.id,'19085','LIC. IVAN ULISES GARCIA QUITANO',NULL,'CUB 2 ENTORNO','#c0392b',4 FROM areas a WHERE a.nombre='DEPARTAMENTO DE INVESTIGACIÓN SOCIOECONÓMICA' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, ubicacion, color_area, orden)
SELECT a.id,'19027','LIC. AURA YUTZIN SANTOS GARCIA',NULL,'CUB 3 ENTORNO','#c0392b',5 FROM areas a WHERE a.nombre='DEPARTAMENTO DE INVESTIGACIÓN SOCIOECONÓMICA' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, ubicacion, color_area, orden)
SELECT a.id,'19030','LIC. GUADALUPE IVAN ESCALANTE ARGUELLES',NULL,'CUB 4 ENTORNO','#c0392b',6 FROM areas a WHERE a.nombre='DEPARTAMENTO DE INVESTIGACIÓN SOCIOECONÓMICA' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, ubicacion, color_area, orden)
SELECT a.id,'19081','LIC. DULCE BERENICE VAZQUEZ PRADO',NULL,'CUB 5 ENTORNO','#c0392b',7 FROM areas a WHERE a.nombre='DEPARTAMENTO DE INVESTIGACIÓN SOCIOECONÓMICA' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, ubicacion, color_area, orden)
SELECT a.id,'19086','LIC. LIZBETH DELFIN MORA',NULL,'CUB 6 ENTORNO','#c0392b',8 FROM areas a WHERE a.nombre='DEPARTAMENTO DE INVESTIGACIÓN SOCIOECONÓMICA' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, ubicacion, color_area, orden)
SELECT a.id,'19089','LIC. ROSENDO DAVID SALVADOR VALDES',NULL,'CUB 7 ENTORNO','#c0392b',9 FROM areas a WHERE a.nombre='DEPARTAMENTO DE INVESTIGACIÓN SOCIOECONÓMICA' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, ubicacion, color_area, orden)
SELECT a.id,'19083','LIC. IRAN LAURA PADILLA DOMINGUEZ',NULL,'CUB 8 ENTORNO','#c0392b',10 FROM areas a WHERE a.nombre='DEPARTAMENTO DE INVESTIGACIÓN SOCIOECONÓMICA' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, ubicacion, color_area, orden)
SELECT a.id,'19077','LIC. KAREN ABIGAIL MENDOZA HERNANDEZ',NULL,'CUB 9 ENTORNO','#c0392b',11 FROM areas a WHERE a.nombre='DEPARTAMENTO DE INVESTIGACIÓN SOCIOECONÓMICA' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, ubicacion, color_area, orden)
SELECT a.id,'19084','LIC. MARIA ISABEL MARTINEZ ZAMORA',NULL,'CUB 10 ENTORNO','#c0392b',12 FROM areas a WHERE a.nombre='DEPARTAMENTO DE INVESTIGACIÓN SOCIOECONÓMICA' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, ubicacion, color_area, orden)
SELECT a.id,'19082','LIC. ANA PATRICIA CONTRERAS VIRUES',NULL,'CUB 11 ENTORNO','#c0392b',13 FROM areas a WHERE a.nombre='DEPARTAMENTO DE INVESTIGACIÓN SOCIOECONÓMICA' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, ubicacion, color_area, orden)
SELECT a.id,'19021','LIC. LETICIA LANDA ISLAS',NULL,'CUB 12 ENTORNO','#c0392b',14 FROM areas a WHERE a.nombre='DEPARTAMENTO DE INVESTIGACIÓN SOCIOECONÓMICA' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, color_area, orden)
SELECT a.id,'19028/19029',NULL,'OFICINA DE VALIDACIÓN DOCUMENTAL',NULL,'#c0392b',15 FROM areas a WHERE a.nombre='DEPARTAMENTO DE INVESTIGACIÓN SOCIOECONÓMICA' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, ubicacion, color_area, orden)
SELECT a.id,'19059',NULL,'OFICINA DE INVESTIGACIÓN DE ANTECEDENTES','ARCO SUR','#c0392b',16 FROM areas a WHERE a.nombre='DEPARTAMENTO DE INVESTIGACIÓN SOCIOECONÓMICA' LIMIT 1;
INSERT INTO directorio (area_id, extension, nombre_completo, puesto_cargo, ubicacion, color_area, orden)
SELECT a.id,'19030',NULL,'OFICINA DE INVESTIGACIÓN DE ANTECEDENTES','ARCO SUR','#c0392b',17 FROM areas a WHERE a.nombre='DEPARTAMENTO DE INVESTIGACIÓN SOCIOECONÓMICA' LIMIT 1;
