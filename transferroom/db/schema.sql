-- Transfer Room — Esquema completo de base de datos
-- Generado concatenando db/migraciones/001..039 en orden, para crear
-- toda la estructura de una vez en una base de datos nueva y vacía.
-- Uso: importar este único fichero (phpMyAdmin, o mysql < schema.sql).
SET NAMES utf8mb4;


-- ============================================================
-- 001_nucleo.sql
-- ============================================================
-- Transfer Room · Migración 001 · Núcleo
-- Configuración, Autenticación, Permisos, Temporadas, Clubes, Participaciones, Auditoría
-- MariaDB 10.4+

CREATE TABLE IF NOT EXISTS configuracion (
    clave VARCHAR(100) NOT NULL PRIMARY KEY,
    valor TEXT NOT NULL,
    tipo ENUM('string','int','decimal','bool','json') NOT NULL DEFAULT 'string',
    categoria VARCHAR(50) NOT NULL DEFAULT 'general',
    descripcion VARCHAR(255) NULL,
    editable TINYINT(1) NOT NULL DEFAULT 1,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    rol ENUM('ADMINISTRADOR','PRESIDENTE') NOT NULL DEFAULT 'PRESIDENTE',
    estado ENUM('ACTIVO','BLOQUEADO') NOT NULL DEFAULT 'ACTIVO',
    ultimo_acceso DATETIME NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS clubes (
    id VARCHAR(64) NOT NULL PRIMARY KEY, -- id permanente del JSON oficial
    nombre VARCHAR(150) NOT NULL,
    escudo_url VARCHAR(500) NULL,
    ciudad VARCHAR(100) NULL,
    color1 VARCHAR(7) NULL,
    color2 VARCHAR(7) NULL,
    abreviatura VARCHAR(10) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS temporadas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    numero INT UNSIGNED NOT NULL UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    estado ENUM('CONFIGURACION','PRETEMPORADA','MERCADO_ABIERTO','COMPETICION','MERCADO_EXTRAORDINARIO','CIERRE','ARCHIVADA') NOT NULL DEFAULT 'CONFIGURACION',
    salary_cap DECIMAL(14,2) NOT NULL DEFAULT 250000000.00,
    max_franquicias INT UNSIGNED NOT NULL DEFAULT 4,
    fecha_inicio DATE NULL,
    fecha_fin DATE NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS participaciones_club (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    club_id VARCHAR(64) NOT NULL,
    temporada_id INT UNSIGNED NOT NULL,
    usuario_presidente_id INT UNSIGNED NULL,
    division ENUM('SUPERLIGA','ASCENSO') NOT NULL,
    estado ENUM('PENDIENTE','CONFIRMADA','ACTIVA','SUSPENDIDA','RETIRADA','FINALIZADA','ARCHIVADA') NOT NULL DEFAULT 'PENDIENTE',
    presupuesto_inicial DECIMAL(14,2) NOT NULL DEFAULT 10000000.00,
    salary_cap_override DECIMAL(14,2) NULL,
    origen_plantilla ENUM('CONTINUAR_ANTERIOR','IMPORTAR_JSON','VACIA','SNAPSHOT','CLON') NOT NULL,
    fecha_alta DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_baja DATETIME NULL,
    UNIQUE KEY uq_club_temporada (club_id, temporada_id),
    CONSTRAINT fk_part_club FOREIGN KEY (club_id) REFERENCES clubes(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_part_temporada FOREIGN KEY (temporada_id) REFERENCES temporadas(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_part_usuario FOREIGN KEY (usuario_presidente_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_participaciones_temporada ON participaciones_club (temporada_id, estado);
CREATE INDEX idx_participaciones_usuario ON participaciones_club (usuario_presidente_id);

CREATE TABLE IF NOT EXISTS auditoria (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NULL,
    ip VARCHAR(45) NULL,
    accion VARCHAR(100) NOT NULL,
    entidad VARCHAR(100) NOT NULL,
    entidad_id VARCHAR(64) NULL,
    valor_antes JSON NULL,
    valor_despues JSON NULL,
    resultado ENUM('OK','ERROR') NOT NULL DEFAULT 'OK',
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_auditoria_entidad ON auditoria (entidad, entidad_id);
CREATE INDEX idx_auditoria_usuario ON auditoria (usuario_id, creado_en);

INSERT INTO configuracion (clave, valor, tipo, categoria, descripcion, editable) VALUES
('salary_cap_defecto', '250000000', 'decimal', 'economia', 'Salary Cap por defecto para nuevas temporadas', 1),
('presupuesto_inicial_defecto', '10000000', 'decimal', 'economia', 'Presupuesto inicial por defecto para nuevas participaciones', 1),
('franquicias_defecto', '4', 'int', 'plantilla', 'Número de franquicias por defecto', 1),
('nombre_liga', 'Superliga Frontier', 'string', 'general', 'Nombre de la competición', 1)
ON DUPLICATE KEY UPDATE clave = clave;

-- ============================================================
-- 002_jugadores.sql
-- ============================================================
-- Transfer Room · Migración 002 · Jugadores
-- Identidad permanente del jugador (Ley 41), independiente de club/contrato.
-- Afinidad y Tier son tablas maestras, nunca ENUM (Ley 11: alta probabilidad de ampliación).
-- Posición sí es ENUM (Ley 11: conjunto prácticamente permanente).

CREATE TABLE IF NOT EXISTS afinidades (
id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
nombre VARCHAR(50) NOT NULL UNIQUE,
creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tiers (
id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
nombre VARCHAR(20) NOT NULL UNIQUE,
orden INT UNSIGNED NOT NULL,
creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS jugadores (
id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
nombre VARCHAR(150) NOT NULL,
posicion ENUM('POR','DEF','MED','DEL') NOT NULL,
afinidad_id INT UNSIGNED NULL,
tier_id INT UNSIGNED NULL,
foto_url VARCHAR(500) NULL,
estado ENUM('ACTIVO','AGENTE_LIBRE') NOT NULL DEFAULT 'AGENTE_LIBRE',
origen ENUM('JSON_OFICIAL','EXTERNO') NOT NULL DEFAULT 'JSON_OFICIAL',
participacion_actual_id INT UNSIGNED NULL,
creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
CONSTRAINT fk_jugador_afinidad FOREIGN KEY (afinidad_id) REFERENCES afinidades(id) ON UPDATE CASCADE ON DELETE SET NULL,
CONSTRAINT fk_jugador_tier FOREIGN KEY (tier_id) REFERENCES tiers(id) ON UPDATE CASCADE ON DELETE SET NULL,
CONSTRAINT fk_jugador_participacion FOREIGN KEY (participacion_actual_id) REFERENCES participaciones_club(id) ON UPDATE RESTRICT ON DELETE RESTRICT,
-- Ley 42: un jugador activo pertenece exactamente a un club; un agente libre a ninguno.
CONSTRAINT chk_jugador_estado_participacion CHECK (
(estado = 'ACTIVO' AND participacion_actual_id IS NOT NULL)
OR (estado = 'AGENTE_LIBRE' AND participacion_actual_id IS NULL)
)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_jugadores_participacion ON jugadores (participacion_actual_id);
CREATE INDEX idx_jugadores_estado ON jugadores (estado);

-- Escala de tiers por defecto. No procede del JSON oficial (no existe allí):
-- es una mecánica interna de Transfer Room. Editable desde configuración; confirmar con Alejandro.
INSERT INTO tiers (nombre, orden) VALUES
('D', 1), ('C', 2), ('B', 3), ('A', 4), ('S', 5)
ON DUPLICATE KEY UPDATE nombre = nombre;

-- ============================================================
-- 003_tiers_reales.sql
-- ============================================================
-- Transfer Room · Migración 003 · Escala real de tiers (mercado.md v4)
-- Sustituye el placeholder D/C/B/A/S sembrado en la migración 002 (ningún
-- jugador tenía tier_id asignado todavía, así que no hay referencias que romper).
-- El % del cap NUNCA se almacena (Ley 37/38): se calcula en tiempo real
-- como salario_base / temporadas.salary_cap.

ALTER TABLE tiers ADD COLUMN salario_base DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER nombre;

DELETE FROM tiers;

INSERT INTO tiers (nombre, salario_base, orden) VALUES
    ('C',   2000000.00,  1),
    ('B-',  5000000.00,  2),
    ('B',   6000000.00,  3),
    ('B+',  8000000.00,  4),
    ('A-', 14000000.00,  5),
    ('A',  18000000.00,  6),
    ('A+', 25000000.00,  7),
    ('S',  40000000.00,  8),
    ('S+', 60000000.00,  9),
    ('S++',75000000.00, 10);

-- ============================================================
-- 004_contratos.sql
-- ============================================================
-- Transfer Room · Migración 004 · Contratos
-- El salario se fija (snapshot) al firmar, copiado del tier en ese momento.
-- Un futuro cambio en tiers.salario_base NUNCA reescribe contratos ya firmados
-- (Ley 57-59: el historial nunca se sobrescribe).

CREATE TABLE IF NOT EXISTS contratos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    jugador_id INT UNSIGNED NOT NULL,
    participacion_id INT UNSIGNED NOT NULL,
    tier_id INT UNSIGNED NOT NULL,
    salario_anual DECIMAL(14,2) NOT NULL,
    duracion_temporadas TINYINT UNSIGNED NOT NULL,
    temporada_inicio_id INT UNSIGNED NOT NULL,
    estado ENUM('ACTIVO','FINALIZADO','RESCINDIDO') NOT NULL DEFAULT 'ACTIVO',
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_contrato_jugador FOREIGN KEY (jugador_id) REFERENCES jugadores(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_contrato_participacion FOREIGN KEY (participacion_id) REFERENCES participaciones_club(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_contrato_tier FOREIGN KEY (tier_id) REFERENCES tiers(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_contrato_temporada_inicio FOREIGN KEY (temporada_inicio_id) REFERENCES temporadas(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    -- mercado.md §5: nunca más de 2 temporadas, nunca menos de 1.
    CONSTRAINT chk_contrato_duracion CHECK (duracion_temporadas BETWEEN 1 AND 2)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_contratos_jugador ON contratos (jugador_id, estado);
CREATE INDEX idx_contratos_participacion ON contratos (participacion_id, estado);

-- ============================================================
-- 005_ofertas_agente_libre.sql
-- ============================================================
-- Transfer Room · Migración 005 · Ofertas por agente libre
-- mercado.md §6/§7: el salario base del tier es el suelo de la puja, no un
-- precio fijo. Gana la oferta más alta; en empate exacto, la registrada
-- primero (creado_en con precisión de microsegundos como prueba cronológica).

CREATE TABLE IF NOT EXISTS ofertas_agente_libre (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    jugador_id INT UNSIGNED NOT NULL,
    participacion_id INT UNSIGNED NOT NULL,
    salario_ofertado DECIMAL(14,2) NOT NULL,
    duracion_temporadas TINYINT UNSIGNED NOT NULL,
    estado ENUM('PENDIENTE','GANADORA','SUPERADA','CANCELADA') NOT NULL DEFAULT 'PENDIENTE',
    creado_en DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_oferta_jugador FOREIGN KEY (jugador_id) REFERENCES jugadores(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_oferta_participacion FOREIGN KEY (participacion_id) REFERENCES participaciones_club(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_oferta_duracion CHECK (duracion_temporadas BETWEEN 1 AND 2)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_ofertas_jugador ON ofertas_agente_libre (jugador_id, estado);
CREATE INDEX idx_ofertas_participacion ON ofertas_agente_libre (participacion_id, estado);

-- ============================================================
-- 006_contratos_pendientes_revision.sql
-- ============================================================
-- Transfer Room · Migración 006 · Fichajes de agentes libres sin mercado competitivo
-- Cubre dos casos: agentes libres oficiales del JSON (nunca tuvieron club) y
-- jugadores externos creados a mano. Ambos se fichan al instante por quien
-- llegue primero, sin aprobación previa, pero quedan marcados para que un
-- administrador revise después el tier/salario asignado.

ALTER TABLE contratos
    ADD COLUMN pendiente_revision TINYINT(1) NOT NULL DEFAULT 0 AFTER estado;

CREATE INDEX idx_contratos_pendiente_revision ON contratos (pendiente_revision);

-- ============================================================
-- 007_dinero_traspasos.sql
-- ============================================================
-- Transfer Room · Migración 007 · Dinero de traspasos (divisa separada del Salary Cap)
-- Corrige un error propio: "presupuesto_inicial" se sembró con 10M por
-- invención mía. Alejandro confirma que es una divisa real y separada:
-- 180M base, usada para pagar traspasos de jugadores con contrato en otro
-- club — nunca para fichajes de agentes libres, que solo cuentan contra el
-- Salary Cap. Además dinero_traspasos es un SALDO que sube y baja con cada
-- traspaso, no un tope fijo como el Salary Cap.

ALTER TABLE participaciones_club
    CHANGE COLUMN presupuesto_inicial dinero_traspasos DECIMAL(14,2) NOT NULL DEFAULT 180000000.00;

UPDATE configuracion
    SET clave = 'dinero_traspasos_defecto',
        valor = '180000000',
        descripcion = 'Dinero de traspasos por defecto para nuevas participaciones (divisa separada del Salary Cap)'
    WHERE clave = 'presupuesto_inicial_defecto';

-- Corrige los datos reales ya creados en la Temporada 4 con el valor erróneo.
UPDATE participaciones_club SET dinero_traspasos = 180000000.00 WHERE dinero_traspasos = 10000000.00;

-- ============================================================
-- 008_ofertas_traspaso.sql
-- ============================================================
-- Transfer Room · Migración 008 · Traspasos de jugadores con contrato en otro club
-- Cap. V Título X / Cap. XXV. Se paga con dinero_traspasos (divisa separada
-- del Salary Cap, confirmado por Alejandro). El vendedor decide aceptar o
-- rechazar; no es una puja competitiva como el mercado de agentes libres.

CREATE TABLE IF NOT EXISTS ofertas_traspaso (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    jugador_id INT UNSIGNED NOT NULL,
    contrato_id INT UNSIGNED NOT NULL,
    participacion_vendedora_id INT UNSIGNED NOT NULL,
    participacion_compradora_id INT UNSIGNED NOT NULL,
    importe_traspaso DECIMAL(14,2) NOT NULL,
    estado ENUM('PENDIENTE','ACEPTADA','RECHAZADA','CANCELADA') NOT NULL DEFAULT 'PENDIENTE',
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resuelto_en DATETIME NULL,
    CONSTRAINT fk_traspaso_jugador FOREIGN KEY (jugador_id) REFERENCES jugadores(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_traspaso_contrato FOREIGN KEY (contrato_id) REFERENCES contratos(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_traspaso_vendedora FOREIGN KEY (participacion_vendedora_id) REFERENCES participaciones_club(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_traspaso_compradora FOREIGN KEY (participacion_compradora_id) REFERENCES participaciones_club(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_traspasos_jugador ON ofertas_traspaso (jugador_id, estado);
CREATE INDEX idx_traspasos_vendedora ON ofertas_traspaso (participacion_vendedora_id, estado);

-- ============================================================
-- 009_rfa_ventana_igualacion.sql
-- ============================================================
-- Transfer Room · Migración 009 · RFA real: ventana de igualación de 48h
-- mercado.md §6.2/§6.3. Un jugador RFA (contrato de 1 temporada al terminar)
-- conserva, en su club de origen, el derecho a igualar la mejor oferta antes
-- de que se haga efectiva. Guardamos club de origen y tipo directamente en
-- el jugador porque describen SU estado de agente libre actual, no un hecho
-- histórico (eso ya vive en auditoria).

ALTER TABLE jugadores
    ADD COLUMN origen_club_agencia_libre VARCHAR(64) NULL AFTER estado,
    ADD COLUMN tipo_agencia_libre ENUM('RESTRINGIDA','NO_RESTRINGIDA') NULL AFTER origen_club_agencia_libre,
    ADD CONSTRAINT fk_jugador_origen_club_agencia_libre
        FOREIGN KEY (origen_club_agencia_libre) REFERENCES clubes(id) ON UPDATE CASCADE ON DELETE SET NULL;

ALTER TABLE ofertas_agente_libre
    MODIFY COLUMN estado ENUM('PENDIENTE','PENDIENTE_IGUALACION','GANADORA','IGUALADA','SUPERADA','CANCELADA') NOT NULL DEFAULT 'PENDIENTE',
    ADD COLUMN fecha_limite_igualacion DATETIME NULL AFTER estado;

-- ============================================================
-- 010_franquicia.sql
-- ============================================================
-- Transfer Room · Migración 010 · Jugadores franquicia y ruleta de retención
-- mercado.md §9: hasta 4 jugadores franquicia por club. Al terminar su
-- contrato, ruleta de retención en vez del circuito RFA/UFA estándar.

ALTER TABLE jugadores
    ADD COLUMN es_franquicia TINYINT(1) NOT NULL DEFAULT 0 AFTER tipo_agencia_libre;

-- ============================================================
-- 011_libro_mayor_traspasos.sql
-- ============================================================
-- Transfer Room · Migración 011 · Libro Mayor de dinero de traspasos
-- Ley 38: "todo cálculo económico será dinámico, nunca cacheado" — la misma
-- regla que ya rige el gasto salarial (siempre SUM(contratos.salario_anual)).
-- dinero_traspasos era una columna mutable con +=, sin historial de por qué
-- vale lo que vale. Se sustituye por un libro mayor: el saldo se calcula
-- siempre como SUM(movimientos_financieros.importe).

CREATE TABLE IF NOT EXISTS movimientos_financieros (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    participacion_id INT UNSIGNED NOT NULL,
    tipo ENUM('DOTACION_INICIAL','PAGO_TRASPASO','COBRO_TRASPASO','AJUSTE_ADMINISTRATIVO') NOT NULL,
    importe DECIMAL(14,2) NOT NULL, -- positivo = entrada, negativo = salida
    concepto VARCHAR(255) NOT NULL,
    referencia_tipo VARCHAR(50) NULL,
    referencia_id INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_movimiento_participacion FOREIGN KEY (participacion_id) REFERENCES participaciones_club(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_movimientos_participacion ON movimientos_financieros (participacion_id, creado_en);

-- Backfill: cada participación existente conserva su saldo actual como dotación inicial.
INSERT INTO movimientos_financieros (participacion_id, tipo, importe, concepto)
SELECT id, 'DOTACION_INICIAL', dinero_traspasos, 'Dotación inicial (migrada desde el saldo previo)'
FROM participaciones_club;

ALTER TABLE participaciones_club DROP COLUMN dinero_traspasos;

-- ============================================================
-- 012_notificaciones.sql
-- ============================================================
-- Transfer Room · Migración 012 · Notificaciones
-- Cap. III/XVIII: eventos dirigidos a un usuario concreto, con estado de lectura.
-- Nunca modifican negocio (Ley: "una notificación nunca modifica negocio, solo informa").

CREATE TABLE IF NOT EXISTS notificaciones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    mensaje VARCHAR(500) NOT NULL,
    enlace VARCHAR(255) NULL,
    leida TINYINT(1) NOT NULL DEFAULT 0,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notificacion_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_notificaciones_usuario ON notificaciones (usuario_id, leida, creado_en);

-- ============================================================
-- 013_snapshots_temporada.sql
-- ============================================================
-- Transfer Room · Migración 013 · Snapshot de temporada
-- Fotografía inmutable de cada club al cerrar una temporada (CONSTITUCION.md Cap. XXII).
-- MariaDB 10.4+

CREATE TABLE IF NOT EXISTS snapshots_temporada (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    temporada_id INT UNSIGNED NOT NULL,
    club_id VARCHAR(64) NOT NULL,
    club_nombre VARCHAR(150) NOT NULL,
    gasto_salarial DECIMAL(14,2) NOT NULL,
    dinero_traspasos DECIMAL(14,2) NOT NULL,
    fichas JSON NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_snapshot_club_temporada (temporada_id, club_id),
    CONSTRAINT fk_snap_temporada FOREIGN KEY (temporada_id) REFERENCES temporadas(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_snap_club FOREIGN KEY (club_id) REFERENCES clubes(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 014_congelar_mercado.sql
-- ============================================================
-- Estado CONGELADO (pausa temporal del mercado sin cerrarlo). Es una bandera
-- independiente del ciclo de vida principal de la temporada (Cap. XXII): no
-- se modela como un estado más del enum porque congelar no es una fase de la
-- liga, es una pausa reversible dentro de MERCADO_ABIERTO o
-- MERCADO_EXTRAORDINARIO. 05-transfer_room_docs/01_bloqueante_primer_mercado/
-- 09_controles_administrativos_fundamentales.md.
ALTER TABLE temporadas ADD COLUMN congelada TINYINT(1) NOT NULL DEFAULT 0 AFTER estado;

-- ============================================================
-- 015_procedencia_archivado.sql
-- ============================================================
-- Marca visual "procedente de club archivado" (05-transfer_room_docs/01_bloqueante_primer_mercado/04,
-- punto 5). No puede ser FK contra clubes: los clubes archivados del JSON
-- oficial nunca se sincronizan a la tabla clubes (ClubEngine::sincronizarDesdeJson
-- solo trae los activos), así que se guarda como texto simple, solo para mostrar.
ALTER TABLE jugadores ADD COLUMN procedencia_archivado VARCHAR(150) NULL AFTER origen_club_agencia_libre;

-- ============================================================
-- 016_peticiones.sql
-- ============================================================
-- Módulo de Peticiones (CONSTITUCION.md Título XVI): tablón de necesidades.
-- "Una petición representa una necesidad, nunca una negociación" — no ejecuta
-- fichajes ni traspasos por sí sola, es pura comunicación estructurada entre
-- presidentes. Aceptar una propuesta cierra automáticamente las demás.

CREATE TABLE peticiones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    participacion_id INT UNSIGNED NOT NULL,
    descripcion VARCHAR(500) NOT NULL,
    estado ENUM('ABIERTA', 'CERRADA', 'CADUCADA', 'CANCELADA') NOT NULL DEFAULT 'ABIERTA',
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resuelto_en DATETIME NULL,
    CONSTRAINT fk_peticion_participacion FOREIGN KEY (participacion_id) REFERENCES participaciones_club(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE peticion_propuestas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    peticion_id INT UNSIGNED NOT NULL,
    participacion_id INT UNSIGNED NOT NULL,
    jugador_id INT UNSIGNED NULL,
    mensaje VARCHAR(500) NOT NULL,
    estado ENUM('PENDIENTE', 'ACEPTADA', 'CERRADA_AUTOMATICAMENTE') NOT NULL DEFAULT 'PENDIENTE',
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_propuesta_peticion FOREIGN KEY (peticion_id) REFERENCES peticiones(id),
    CONSTRAINT fk_propuesta_participacion FOREIGN KEY (participacion_id) REFERENCES participaciones_club(id),
    CONSTRAINT fk_propuesta_jugador FOREIGN KEY (jugador_id) REFERENCES jugadores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 017_contraoferta_traspaso.sql
-- ============================================================
-- Contraoferta de traspaso (05-transfer_room_docs/01_bloqueante_primer_mercado/
-- 05_auditoria_cobertura_reglas_mercado_parte2.md, punto 13): "Contraoferta
-- como versión nueva, nunca edición". Se enlaza a la oferta original en vez
-- de mutarla, para que el historial completo quede visible.
ALTER TABLE ofertas_traspaso
    ADD COLUMN oferta_padre_id INT UNSIGNED NULL AFTER contrato_id,
    ADD CONSTRAINT fk_traspaso_padre FOREIGN KEY (oferta_padre_id) REFERENCES ofertas_traspaso(id),
    MODIFY COLUMN estado ENUM('PENDIENTE', 'ACEPTADA', 'RECHAZADA', 'CANCELADA', 'CONTRAOFERTADA') NOT NULL DEFAULT 'PENDIENTE';

-- ============================================================
-- 018_ventana_rfa_configurable.sql
-- ============================================================
-- Ventana de igualación RFA configurable (05-transfer_room_docs/01_bloqueante_primer_mercado/10):
-- antes era una constante fija en MarketEngine (48h), ahora vive en configuración.
INSERT INTO configuracion (clave, valor, tipo, categoria, descripcion, editable)
VALUES ('ventana_igualacion_rfa_horas', '48', 'int', 'mercado', 'Horas de la ventana de igualación RFA antes de que se pueda confirmar el traspaso sin igualar', 1);

-- ============================================================
-- 019_invalidar_sesion.sql
-- ============================================================
-- Cerrar sesión de un usuario a distancia (05-transfer_room_docs/01_bloqueante_primer_mercado/10).
-- PHP no lleva un registro de sesiones activas por usuario; se resuelve
-- marcando un instante de invalidación y comparándolo contra el instante de
-- login guardado en la propia sesión (AuthenticationEngine::requerirSesion()).
ALTER TABLE usuarios ADD COLUMN sesion_invalidada_desde DATETIME NULL AFTER ultimo_acceso;

-- ============================================================
-- 020_log_errores_tecnicos.sql
-- ============================================================
-- Log de errores técnicos, separado de la auditoría de negocio
-- (05-transfer_room_docs/01_bloqueante_primer_mercado/10): excepciones no
-- controladas y errores PHP, no acciones de usuario.
CREATE TABLE errores_tecnicos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nivel VARCHAR(20) NOT NULL,
    mensaje TEXT NOT NULL,
    archivo VARCHAR(500) NULL,
    linea INT UNSIGNED NULL,
    url VARCHAR(500) NULL,
    usuario_id INT UNSIGNED NULL,
    traza TEXT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 021_toggle_revision_oficiales.sql
-- ============================================================
-- Auditoría de cobertura parte 2, punto 10 (transfer_room_docs_full/01_bloqueante_primer_mercado/05):
-- los agentes libres externos SIEMPRE requieren aprobación admin (Título XII,
-- no configurable). Los oficiales del JSON recién importados hoy pasan por el
-- mismo camino sin tier (ficharAgenteLibreSinTier) y quedaban pendientes de
-- revisión sin excepción; este parámetro deja esa exigencia como configuración
-- de temporada, no como constante del código (Compromiso de Continuidad).
INSERT INTO configuracion (clave, valor, tipo, categoria, descripcion, editable) VALUES
('revision_obligatoria_agentes_oficiales', '1', 'bool', 'mercado', 'Si un agente libre oficial fichado sin tier queda pendiente de revisión admin (los externos siempre la requieren, esto no les afecta)', 1);

-- ============================================================
-- 022_historico_backups.sql
-- ============================================================
-- Histórico de backups (transfer_room_docs_full/01_bloqueante_primer_mercado/10,
-- 02_ux_diseno v3): backup.php solo generaba y descargaba al momento, sin dejar
-- rastro de los anteriores. Aquí se registra cada backup generado para poder
-- volver a descargarlo sin tener que generarlo de nuevo.
CREATE TABLE backups_generados (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre_archivo VARCHAR(150) NOT NULL,
    tamano_bytes INT UNSIGNED NOT NULL,
    generado_por INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_backups_generados_usuario FOREIGN KEY (generado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 023_recuperacion_password.sql
-- ============================================================
-- Recuperación de contraseña (transfer_room_docs_full/01_bloqueante_primer_mercado/11,
-- queja esperable #4: "se me fue la contraseña y no puedo entrar"). Alta de
-- usuarios sigue siendo 100% manual (sin registro público); esto solo cubre
-- el "he olvidado mi contraseña" de una cuenta que ya existe.
-- Se guarda el HASH del token, nunca el token en claro (igual que las
-- contraseñas): si la tabla se filtrara, no serviría para nada por sí sola.
CREATE TABLE tokens_recuperacion_password (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expira_en DATETIME NOT NULL,
    usado TINYINT(1) NOT NULL DEFAULT 0,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tokens_recuperacion_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_tokens_recuperacion_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 024_mensajes.sql
-- ============================================================
-- Módulo de Mensajes (CONSTITUCION.md Título XV: "los mensajes nunca forman
-- parte del negocio... son únicamente comunicación"). Dos tipos de
-- conversación, decididos con Franshu:
--   CON_ADMINISTRACION: un presidente y "la administración" (bandeja
--     compartida — cualquier admin la ve y puede responder, no un admin
--     concreto: la app ya trata "notificar a admins" como un grupo, no
--     como individuos, en el resto del código).
--   ENTRE_PRESIDENTES: dos presidentes concretos, sin relación con ninguna
--     oferta ni jugador (bandeja libre, decisión explícita de Franshu).
-- usuario_contraparte_id es NULL solo para CON_ADMINISTRACION; el
-- find-or-create vive en el motor (ConversacionRepository), no en una
-- restricción UNIQUE, porque MySQL no aplica unicidad entre NULLs.
CREATE TABLE conversaciones (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('CON_ADMINISTRACION', 'ENTRE_PRESIDENTES') NOT NULL,
    usuario_iniciador_id INT UNSIGNED NOT NULL,
    usuario_contraparte_id INT UNSIGNED NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_conversaciones_iniciador FOREIGN KEY (usuario_iniciador_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_conversaciones_contraparte FOREIGN KEY (usuario_contraparte_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY uq_conversacion_entre_presidentes (tipo, usuario_iniciador_id, usuario_contraparte_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE mensajes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    conversacion_id INT UNSIGNED NOT NULL,
    remitente_id INT UNSIGNED NOT NULL,
    cuerpo TEXT NOT NULL,
    -- Para CON_ADMINISTRACION es lectura compartida (cualquier admin que
    -- abre la conversación la marca vista para todo el equipo, igual que
    -- una bandeja de soporte compartida); para ENTRE_PRESIDENTES es lectura
    -- del destinatario concreto.
    leido TINYINT(1) NOT NULL DEFAULT 0,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mensajes_conversacion FOREIGN KEY (conversacion_id) REFERENCES conversaciones(id) ON DELETE CASCADE,
    CONSTRAINT fk_mensajes_remitente FOREIGN KEY (remitente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_mensajes_conversacion (conversacion_id, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 025_favoritos.sql
-- ============================================================
-- Favoritos del hub de navegación (Fase 2, 02_ux_diseno/05_rediseno_pantalla_inicio_hub_navegacion_v3.md,
-- §3): cualquier usuario puede fijar un panel del hub para acceso directo,
-- sin depender del icono de primer nivel que lo contiene.
CREATE TABLE usuario_favoritos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    ruta VARCHAR(150) NOT NULL,
    etiqueta VARCHAR(100) NOT NULL,
    orden INT UNSIGNED NOT NULL DEFAULT 0,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_favoritos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY uq_favorito_usuario_ruta (usuario_id, ruta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 026_mi_estrategia.sql
-- ============================================================
-- Mi Estrategia (Fase 2, hub v3): watchlist, kanban de objetivos, diario de
-- decisiones y simulador — sin especificación previa, diseño propio. El
-- simulador reutiliza la watchlist (columna en_simulador) en vez de una
-- cuarta tabla: seleccionar "para el simulador" es solo un estado más de un
-- jugador ya en seguimiento, no un concepto nuevo. El comparador no
-- necesita persistencia: compara al vuelo cualquier jugador ya existente.
CREATE TABLE estrategia_watchlist (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    jugador_id INT UNSIGNED NOT NULL,
    en_simulador TINYINT(1) NOT NULL DEFAULT 0,
    notas VARCHAR(500) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_watchlist_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_watchlist_jugador FOREIGN KEY (jugador_id) REFERENCES jugadores(id) ON DELETE CASCADE,
    UNIQUE KEY uq_watchlist_usuario_jugador (usuario_id, jugador_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE estrategia_objetivos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    descripcion VARCHAR(500) NULL,
    estado ENUM('PENDIENTE', 'EN_PROGRESO', 'COMPLETADO') NOT NULL DEFAULT 'PENDIENTE',
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_objetivos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE estrategia_diario (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NOT NULL,
    texto TEXT NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_diario_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 027_preferencias_usuario.sql
-- ============================================================
-- Preferencias de usuario (Fase 2, hub v3, ampliación de Cuenta): tabla
-- clave/valor genérica en vez de una columna por preferencia — cubre
-- notificaciones granulares (clave 'notif_<TIPO>'), perfil público
-- (claves 'perfil_publico' y 'perfil_bio') y accesibilidad persistida por
-- servidor si hiciera falta más adelante, sin migrar de nuevo cada vez que
-- se añada una preferencia. Ausencia de fila = valor por defecto (opt-out,
-- no opt-in: todo activado salvo que el usuario lo desactive explícitamente).
CREATE TABLE usuario_preferencias (
    usuario_id INT UNSIGNED NOT NULL,
    clave VARCHAR(60) NOT NULL,
    valor VARCHAR(255) NOT NULL,
    PRIMARY KEY (usuario_id, clave),
    CONSTRAINT fk_preferencias_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 028_plantillas_configuracion.sql
-- ============================================================
-- Plantillas guardadas de configuración (Fase 2, admin/Configuración y
-- Reglas): fotografías nombradas de los valores editables de
-- `configuracion`, para poder guardar una combinación y reaplicarla más
-- tarde (p. ej. "Salary Cap de pretemporada" vs "Salary Cap de playoffs").
-- Sin especificación previa — diseño propio.
CREATE TABLE configuracion_plantillas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    valores JSON NOT NULL,
    creado_por INT UNSIGNED NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_plantillas_creador FOREIGN KEY (creado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 029_anuncios.sql
-- ============================================================
-- Anuncios programados (Fase 2, admin/Bandeja de Pendientes): concepto
-- nuevo, sin tabla previa. Como el proyecto no tiene cron (CLAUDE.md §6),
-- "programar" un anuncio no dispara nada por sí solo: simplemente no se
-- muestra en el feed público hasta que publicar_en <= NOW(), comprobado al
-- vuelo en cada lectura, igual que la ventana RFA o el cierre de mercado.
CREATE TABLE anuncios (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    cuerpo TEXT NOT NULL,
    publicar_en DATETIME NOT NULL,
    creado_por INT UNSIGNED NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_anuncios_creador FOREIGN KEY (creado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 030_modo_mantenimiento.sql
-- ============================================================
-- Modo Mantenimiento (Fase 2, admin/Modo Mantenimiento): banner de
-- emergencia global, distinto de pausar el mercado (que es un estado de
-- temporada). Se reutiliza la tabla `configuracion` que ya existe en vez de
-- crear una tabla de dos filas: admin/configuracion.php ya renderiza
-- cualquier fila editable de esta tabla automáticamente, así que estas dos
-- claves no necesitan ninguna pantalla de administración nueva.
INSERT INTO configuracion (clave, valor, tipo, categoria, descripcion, editable) VALUES
('modo_mantenimiento_activo', '0', 'bool', 'general', 'Si está activo, muestra un banner de emergencia a todos los usuarios en toda la web', 1),
('modo_mantenimiento_mensaje', 'La liga está en mantenimiento. Algunas acciones pueden no estar disponibles temporalmente.', 'string', 'general', 'Mensaje mostrado en el banner de Modo Mantenimiento', 1)
ON DUPLICATE KEY UPDATE clave = clave;

-- ============================================================
-- 031_planificador_tactico.sql
-- ============================================================
-- Planificador táctico visual (Fase 2, Mi Estrategia): asignación de un
-- jugador (tuyo, de tu watchlist, o cualquiera de la liga) a un puesto fijo
-- de una formación 4-3-3 de referencia. Solo planificación — nunca ejecuta
-- ningún fichaje real. `slot` es un identificador fijo de puesto
-- (por, def1..def4, med1..med3, del1..del3), no una FK: la formación es la
-- misma para todos los usuarios, es la app quien la define.
CREATE TABLE estrategia_planificador_slots (
    usuario_id INT UNSIGNED NOT NULL,
    slot VARCHAR(10) NOT NULL,
    jugador_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (usuario_id, slot),
    CONSTRAINT fk_planificador_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_planificador_jugador FOREIGN KEY (jugador_id) REFERENCES jugadores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 032_peticiones_estructuradas.sql
-- ============================================================
-- Peticiones estructuradas (Fase 2, ronda de feedback): además del texto
-- libre ya existente, una petición puede llevar filtros concretos —
-- posiciones buscadas, tope salarial, afinidad y tier mínimo aceptable —
-- para que quien responde vea directamente qué jugadores de su plantilla
-- encajan, en vez de tener que leer una descripción y adivinar. Todo
-- opcional (columnas NULL): una petición 100% en texto libre sigue siendo
-- válida, esto es un complemento, no un reemplazo.
ALTER TABLE peticiones
    ADD COLUMN posiciones JSON NULL AFTER descripcion,
    ADD COLUMN tope_salarial DECIMAL(14,2) NULL AFTER posiciones,
    ADD COLUMN afinidad VARCHAR(60) NULL AFTER tope_salarial,
    ADD COLUMN tier_minimo_id INT UNSIGNED NULL AFTER afinidad,
    ADD CONSTRAINT fk_peticion_tier_minimo FOREIGN KEY (tier_minimo_id) REFERENCES tiers(id);

-- ============================================================
-- 033_planificador_suplentes_reservas.sql
-- ============================================================
-- Fase 2, ronda de feedback: el planificador táctico (migración 031) solo
-- tenía 11 titulares. Se añaden 5 suplentes (mismos slots con clave 's1'..'s5',
-- ya caben en estrategia_planificador_slots.slot VARCHAR(10), sin ALTER) y una
-- lista de reservas sin límite ni posición fija, que no encaja en el modelo de
-- "un jugador por slot fijo" — tabla nueva, sin concepto de slot.
CREATE TABLE estrategia_planificador_reservas (
    usuario_id INT UNSIGNED NOT NULL,
    jugador_id INT UNSIGNED NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, jugador_id),
    CONSTRAINT fk_planificador_reserva_jugador FOREIGN KEY (jugador_id) REFERENCES jugadores(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 034_wiki_jugadores.sql
-- ============================================================
-- Enlace automático de jugadores a su página de la wiki de Inazuma Eleven en
-- Fandom (ver ESPECIFICACION_CLAUDE_WIKI_INAZUMA.md). Opción A del §6: campos
-- planos en `jugadores`, no tabla separada — el modelo de jugadores es
-- sencillo y no existe ya una arquitectura de integraciones externas.
-- Aditiva, no destructiva: no toca ninguna columna existente.
ALTER TABLE jugadores
    ADD COLUMN wiki_provider VARCHAR(32) NULL AFTER foto_url,
    ADD COLUMN wiki_title VARCHAR(255) NULL AFTER wiki_provider,
    ADD COLUMN wiki_url VARCHAR(500) NULL AFTER wiki_title,
    -- pending | matched | needs_review | not_found | error | manual
    ADD COLUMN wiki_status VARCHAR(20) NOT NULL DEFAULT 'pending' AFTER wiki_url,
    ADD COLUMN wiki_confidence DECIMAL(4,3) NULL AFTER wiki_status,
    ADD COLUMN wiki_reason VARCHAR(64) NULL AFTER wiki_confidence,
    ADD COLUMN wiki_last_checked_at DATETIME NULL AFTER wiki_reason,
    ADD COLUMN wiki_last_error VARCHAR(255) NULL AFTER wiki_last_checked_at,
    ADD COLUMN wiki_resolved_at DATETIME NULL AFTER wiki_last_error,
    ADD INDEX idx_jugadores_wiki_status (wiki_status);

-- ============================================================
-- 035_intercambio_traspaso.sql
-- ============================================================
-- Intercambios de jugadores dentro de una oferta de traspaso (a petición de
-- Franshu): además de dinero, una oferta puede incluir uno o más jugadores
-- del propio comprador a cambio del jugador objetivo, o ambas cosas. No es
-- una tabla nueva de ofertas — es un anexo de `ofertas_traspaso`, que sigue
-- siendo la oferta real; esta tabla solo dice qué jugadores del comprador
-- van incluidos en el trato.
-- `contrato_id` se captura en el momento de crear la oferta (el contrato
-- activo del jugador ofrecido en ese instante) para poder recomprobar en la
-- aceptación que sigue siendo el mismo contrato activo, igual que ya hace
-- `ofertas_traspaso.contrato_id` con el jugador objetivo.
CREATE TABLE ofertas_traspaso_jugadores (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    oferta_traspaso_id INT UNSIGNED NOT NULL,
    jugador_id INT UNSIGNED NOT NULL,
    contrato_id INT UNSIGNED NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_otj_oferta FOREIGN KEY (oferta_traspaso_id) REFERENCES ofertas_traspaso(id),
    CONSTRAINT fk_otj_jugador FOREIGN KEY (jugador_id) REFERENCES jugadores(id),
    CONSTRAINT fk_otj_contrato FOREIGN KEY (contrato_id) REFERENCES contratos(id),
    UNIQUE KEY uq_otj_oferta_jugador (oferta_traspaso_id, jugador_id),
    INDEX idx_otj_oferta (oferta_traspaso_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 036_retencion_franquicia.sql
-- ============================================================
-- Transfer Room · Migración 036 · Cargo de dinero de traspasos por retención de franquicia
-- A petición de Franshu: cuando la ruleta de franquicia resuelve DESCUENTO (se
-- queda por menos salario), el club paga 40M de dinero de traspasos en ese
-- mismo momento; si resuelve MISMO_PRECIO, paga 20M. SALIDA_DIRECTA no paga
-- nada (el jugador se va, no hay retención que cobrar).

ALTER TABLE movimientos_financieros
    MODIFY COLUMN tipo ENUM('DOTACION_INICIAL','PAGO_TRASPASO','COBRO_TRASPASO','AJUSTE_ADMINISTRATIVO','RETENCION_FRANQUICIA') NOT NULL;

-- ============================================================
-- 037_temporadas_consecutivas.sql
-- ============================================================
-- Transfer Room · Migración 037 · Contador de temporadas consecutivas
-- mercado.md §5: "ningún jugador puede vestir la misma camiseta más de 3
-- años seguidos". Es global y acumulativo (no se resetea con un traspaso a
-- mitad de contrato), y solo se resetea cuando un club DISTINTO al que ya
-- lo tenía lo ficha en agencia libre (nueva camiseta = cuenta nueva).

ALTER TABLE jugadores
    ADD COLUMN temporadas_consecutivas SMALLINT UNSIGNED NOT NULL DEFAULT 0;

-- ============================================================
-- 038_supertecnicas_simuladas.sql
-- ============================================================
-- Simulador de supertécnicas de Mi Estrategia (antes localStorage, ahora
-- persistido por usuario). Dos tablas nuevas, aisladas a propósito de
-- jugadores/mercado real:
--   - jugadores_simulados: jugadores "de prueba" que el usuario inventa para
--     el simulador, sin ninguna relación con `jugadores` (tabla real).
--   - tecnicas_simuladas: técnicas asignadas a UNO de los dos — un jugador
--     real (jugador_id) o uno simulado (jugador_simulado_id) — nunca ambos ni
--     ninguno. El máximo de 4 por jugador se valida en PHP (EstrategiaEngine),
--     no aquí: MariaDB no puede expresar "como mucho 4 filas con esta pareja
--     de claves" como CHECK constraint.
CREATE TABLE jugadores_simulados (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    nombre VARCHAR(60) NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_jugador_simulado_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_jugador_simulado_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tecnicas_simuladas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    jugador_id INT UNSIGNED NULL,
    jugador_simulado_id INT UNSIGNED NULL,
    nombre VARCHAR(60) NOT NULL,
    afinidad VARCHAR(20) NOT NULL,
    tension SMALLINT UNSIGNED NOT NULL,
    categoria VARCHAR(20) NULL,
    subcategoria VARCHAR(20) NULL,
    hipertecnica VARCHAR(20) NULL,
    hipertecnica_variante VARCHAR(20) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_tecnica_simulada_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_tecnica_simulada_jugador FOREIGN KEY (jugador_id) REFERENCES jugadores(id) ON DELETE CASCADE,
    CONSTRAINT fk_tecnica_simulada_jugador_simulado FOREIGN KEY (jugador_simulado_id) REFERENCES jugadores_simulados(id) ON DELETE CASCADE,
    CONSTRAINT chk_tecnica_simulada_un_solo_dueno CHECK (
        (jugador_id IS NULL) <> (jugador_simulado_id IS NULL)
    ),
    INDEX idx_tecnica_simulada_usuario (usuario_id),
    INDEX idx_tecnica_simulada_jugador (jugador_id),
    INDEX idx_tecnica_simulada_jugador_simulado (jugador_simulado_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 039_contrato_transferible.sql
-- ============================================================
-- Transfer Room · Migración 039 · Marca de "transferible" en el contrato
-- Checklist de primer mercado (item A.3): un presidente puede marcar a un
-- jugador propio como no transferible, para que el resto de la liga sepa
-- que no está en venta antes de ofertar. Es solo informativo — no bloquea
-- ninguna oferta a nivel de reglas de negocio, el club sigue siendo libre
-- de aceptar o rechazar cualquier oferta igualmente.
ALTER TABLE contratos
    ADD COLUMN transferible TINYINT(1) NOT NULL DEFAULT 1 AFTER estado;
-- Resolución automática de la ventana de igualación RFA a las 48h (confirmado
-- por Franshu en la auditoría de mercado): antes solo un administrador podía
-- confirmarSinIgualacion() a mano; ahora publico/cron/resolver_rfa.php lo
-- hace solo, disparado por un cron externo (IONOS solo ofrece cron por URL en
-- hosting compartido, así que el script se protege con este secreto en vez
-- de con sesión de administrador).
INSERT INTO configuracion (clave, valor, tipo, categoria, descripcion, editable)
VALUES ('cron_secreto_rfa', SUBSTRING(MD5(RAND()), 1, 32), 'string', 'mercado', 'Token que debe llevar la URL del cron externo (IONOS) para poder disparar la resolución automática de ventanas RFA vencidas', 1);
