<?php

declare(strict_types=1);

final class AuthenticationEngine
{
    private const HORAS_VALIDEZ_TOKEN_RECUPERACION = 1;

    public function __construct(
        private UsuarioRepository $usuarios,
        private AuditEngine $auditoria,
        private TokenRecuperacionRepository $tokensRecuperacion
    ) {
    }

    public function iniciarSesion(string $email, string $password): array
    {
        $usuario = $this->usuarios->buscarPorEmail($email);

        if ($usuario === null || !password_verify($password, $usuario['password_hash'])) {
            $this->auditoria->registrar(null, 'LOGIN_FALLIDO', 'usuarios', $email, null, null, 'ERROR');
            throw new DomainException('El correo o la contraseña no son correctos.');
        }

        if ($usuario['estado'] !== 'ACTIVO') {
            $this->auditoria->registrar((int) $usuario['id'], 'LOGIN_BLOQUEADO', 'usuarios', (string) $usuario['id'], null, null, 'ERROR');
            throw new DomainException('Esta cuenta está bloqueada. Contacta con la administración.');
        }

        session_regenerate_id(true);
        $_SESSION['usuario_id'] = (int) $usuario['id'];
        $_SESSION['usuario_rol'] = $usuario['rol'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'];
        $_SESSION['autenticado_en'] = time();

        $this->usuarios->registrarAcceso((int) $usuario['id']);
        $this->auditoria->registrar((int) $usuario['id'], 'LOGIN', 'usuarios', (string) $usuario['id'], null, null, 'OK');

        return $usuario;
    }

    /** Alta manual por un administrador (Ley: sin registro público). */
    public function crearUsuario(string $email, string $nombre, string $password, string $rol, int $usuarioAdminId): int
    {
        $email = trim($email);
        $nombre = trim($nombre);

        if ($email === '' || $nombre === '') {
            throw new DomainException('El correo y el nombre son obligatorios.');
        }

        if (!in_array($rol, ['ADMINISTRADOR', 'PRESIDENTE'], true)) {
            throw new DomainException('Rol inválido.');
        }

        if (strlen($password) < 8) {
            throw new DomainException('La contraseña debe tener al menos 8 caracteres.');
        }

        if ($this->usuarios->buscarPorEmail($email) !== null) {
            throw new DomainException('Ya existe un usuario con ese correo.');
        }

        $usuarioId = $this->usuarios->crear($email, password_hash($password, PASSWORD_DEFAULT), $nombre, $rol);

        $this->auditoria->registrar($usuarioAdminId, 'CREAR_USUARIO', 'usuarios', (string) $usuarioId, null, [
            'email' => $email,
            'nombre' => $nombre,
            'rol' => $rol,
        ]);

        return $usuarioId;
    }

    public function listarUsuarios(): array
    {
        return $this->usuarios->listarTodos();
    }

    public function cerrarSesion(): void
    {
        $usuarioId = $_SESSION['usuario_id'] ?? null;

        if ($usuarioId !== null) {
            $this->auditoria->registrar((int) $usuarioId, 'LOGOUT', 'usuarios', (string) $usuarioId, null, null, 'OK');
        }

        $_SESSION = [];
        session_destroy();
    }

    public function usuarioActual(): ?array
    {
        if (!isset($_SESSION['usuario_id'])) {
            return null;
        }

        return $this->usuarios->buscarPorId((int) $_SESSION['usuario_id']);
    }

    public function requerirSesion(): array
    {
        $usuario = $this->usuarioActual();

        if ($usuario === null) {
            header('Location: /login.php'); // absoluta: relativa rompía desde subcarpetas como admin/
            exit;
        }

        // Cierre de sesión a distancia: si un admin invalidó la sesión de este
        // usuario DESPUÉS de que inició sesión, se le echa en la siguiente
        // petición. No hay registro de sesiones activas en PHP nativo, así
        // que se compara el instante de login (guardado en la propia sesión)
        // contra el instante de invalidación (guardado en la fila del usuario).
        if ($usuario['sesion_invalidada_desde'] !== null
            && strtotime((string) $usuario['sesion_invalidada_desde']) > (int) ($_SESSION['autenticado_en'] ?? 0)) {
            $this->cerrarSesion();
            header('Location: /login.php');
            exit;
        }

        return $usuario;
    }

    public function cerrarSesionDeUsuario(int $usuarioId, int $usuarioAdminId): void
    {
        $this->usuarios->invalidarSesion($usuarioId);
        $this->auditoria->registrar($usuarioAdminId, 'CERRAR_SESION_A_DISTANCIA', 'usuarios', (string) $usuarioId, null, null);
    }

    /**
     * Autoservicio: invalida cualquier otra sesión abierta con esta cuenta.
     * No hay registro de sesiones activas en PHP nativo (§6 CLAUDE.md), así
     * que técnicamente invalida TODA sesión, incluida la actual, y de
     * inmediato renueva 'autenticado_en' en la sesión actual para que
     * sobreviva: el efecto neto es "cierra las demás", no "ciérralas todas".
     */
    public function cerrarOtrasSesiones(int $usuarioId): void
    {
        $this->usuarios->invalidarSesion($usuarioId);
        $_SESSION['autenticado_en'] = time();
        $this->auditoria->registrar($usuarioId, 'CERRAR_OTRAS_SESIONES', 'usuarios', (string) $usuarioId, null, null);
    }

    /** Cambio de contraseña por el propio usuario logueado (distinto de la recuperación por token). */
    public function cambiarPasswordPropia(int $usuarioId, string $passwordActual, string $passwordNueva): void
    {
        $usuario = $this->usuarios->buscarPorId($usuarioId);
        if ($usuario === null || !password_verify($passwordActual, $usuario['password_hash'])) {
            throw new DomainException('La contraseña actual no es correcta.');
        }

        if (strlen($passwordNueva) < 8) {
            throw new DomainException('La contraseña nueva debe tener al menos 8 caracteres.');
        }

        $this->usuarios->actualizarPassword($usuarioId, password_hash($passwordNueva, PASSWORD_DEFAULT));
        $this->auditoria->registrar($usuarioId, 'CAMBIAR_PASSWORD_PROPIA', 'usuarios', (string) $usuarioId, null, null);
    }

    /**
     * Solicitud de recuperación: siempre "silenciosa" de cara al usuario (mismo
     * mensaje exista o no la cuenta) para no revelar qué correos están dados de
     * alta en la liga. El envío real de correo depende de que el hosting tenga
     * mail() configurado (habitual en IONOS; no en XAMPP local sin MTA) — si
     * falla el envío, el token queda igualmente creado y válido, nunca se
     * revela el fallo al usuario.
     */
    public function solicitarRecuperacion(string $email, string $urlBase): void
    {
        $usuario = $this->usuarios->buscarPorEmail(trim($email));
        if ($usuario === null || $usuario['estado'] !== 'ACTIVO') {
            return;
        }

        $this->tokensRecuperacion->invalidarPendientesDeUsuario((int) $usuario['id']);

        $token = bin2hex(random_bytes(32));
        $expiraEn = date('Y-m-d H:i:s', time() + self::HORAS_VALIDEZ_TOKEN_RECUPERACION * 3600);
        $this->tokensRecuperacion->crear((int) $usuario['id'], hash('sha256', $token), $expiraEn);

        $enlace = rtrim($urlBase, '/') . '/recuperar_password.php?token=' . $token;
        @mail(
            $usuario['email'],
            'Recuperación de contraseña — Superliga Frontier',
            "Hola {$usuario['nombre']},\n\nPara restablecer tu contraseña de Transfer Room, entra en este enlace (válido " . self::HORAS_VALIDEZ_TOKEN_RECUPERACION . " hora):\n\n{$enlace}\n\nSi no has sido tú, ignora este correo: tu contraseña actual sigue siendo válida.",
            'From: no-responder@superligafrontier.es'
        );

        $this->auditoria->registrar((int) $usuario['id'], 'SOLICITAR_RECUPERACION_PASSWORD', 'usuarios', (string) $usuario['id'], null, null);
    }

    public function restablecerPassword(string $token, string $passwordNueva): void
    {
        $fila = $this->tokensRecuperacion->buscarValidoPorHash(hash('sha256', $token));
        if ($fila === null) {
            throw new DomainException('Este enlace de recuperación no es válido o ya ha caducado.');
        }

        if (strlen($passwordNueva) < 8) {
            throw new DomainException('La contraseña nueva debe tener al menos 8 caracteres.');
        }

        $usuarioId = (int) $fila['usuario_id'];
        $this->usuarios->actualizarPassword($usuarioId, password_hash($passwordNueva, PASSWORD_DEFAULT));
        $this->tokensRecuperacion->marcarUsado((int) $fila['id']);
        // Un token de recuperación usado deja sin validez cualquier sesión ya
        // abierta con la contraseña antigua, igual que un cierre de sesión a distancia.
        $this->usuarios->invalidarSesion($usuarioId);

        $this->auditoria->registrar($usuarioId, 'RESTABLECER_PASSWORD_POR_TOKEN', 'usuarios', (string) $usuarioId, null, null);
    }
}
