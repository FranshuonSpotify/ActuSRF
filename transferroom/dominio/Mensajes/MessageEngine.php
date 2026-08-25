<?php

declare(strict_types=1);

/**
 * Título XV de CONSTITUCION.md: "los mensajes nunca forman parte del
 * negocio... son únicamente comunicación". Este motor no valida cap, no
 * toca contratos ni ofertas — solo quién puede hablar con quién y quién
 * puede leer una conversación concreta.
 *
 * Dos tipos, decididos con Franshu: CON_ADMINISTRACION (un presidente y
 * "la administración" como bandeja compartida entre todos los admins,
 * igual que ya se notifica a "los admins" como grupo en el resto del
 * código) y ENTRE_PRESIDENTES (dos presidentes concretos, sin atar nada a
 * ninguna oferta ni jugador — bandeja libre).
 */
final class MessageEngine
{
    public function __construct(
        private ConversacionRepository $conversaciones,
        private MensajeRepository $mensajes,
        private UsuarioRepository $usuarios
    ) {
    }

    public function obtenerOCrearConversacionConAdmin(int $presidenteId): int
    {
        $existente = $this->conversaciones->buscarConAdministracion($presidenteId);
        if ($existente !== null) {
            return (int) $existente['id'];
        }

        return $this->conversaciones->crearConAdministracion($presidenteId);
    }

    public function obtenerOCrearConversacionEntrePresidentes(int $usuarioAId, int $usuarioBId): int
    {
        if ($usuarioAId === $usuarioBId) {
            throw new DomainException('No puedes iniciar una conversación contigo mismo.');
        }

        $otro = $this->usuarios->buscarPorId($usuarioBId);
        if ($otro === null || $otro['rol'] !== 'PRESIDENTE') {
            throw new DomainException('Ese usuario no está disponible para mensajes entre presidentes.');
        }

        $menor = min($usuarioAId, $usuarioBId);
        $mayor = max($usuarioAId, $usuarioBId);

        $existente = $this->conversaciones->buscarEntrePresidentes($menor, $mayor);
        if ($existente !== null) {
            return (int) $existente['id'];
        }

        return $this->conversaciones->crearEntrePresidentes($menor, $mayor);
    }

    /** Devuelve los mensajes y marca como leídos los que no envió $usuarioId, en la misma operación (abrir = leer). */
    public function abrirConversacion(int $conversacionId, array $usuario): array
    {
        $conversacion = $this->validarAcceso($conversacionId, $usuario);
        $this->mensajes->marcarLeidosParaUsuario($conversacionId, (int) $usuario['id']);

        return [
            'conversacion' => $conversacion,
            'mensajes' => $this->mensajes->listarPorConversacion($conversacionId),
        ];
    }

    public function enviarMensaje(int $conversacionId, int $remitenteId, string $cuerpo): int
    {
        $cuerpo = trim($cuerpo);
        if ($cuerpo === '') {
            throw new DomainException('El mensaje no puede estar vacío.');
        }
        if (mb_strlen($cuerpo) > 4000) {
            throw new DomainException('El mensaje es demasiado largo (máximo 4000 caracteres).');
        }

        $remitente = $this->usuarios->buscarPorId($remitenteId);
        if ($remitente === null) {
            throw new DomainException('El usuario no existe.');
        }
        $this->validarAcceso($conversacionId, $remitente);

        return $this->mensajes->crear($conversacionId, $remitenteId, $cuerpo);
    }

    public function listarConversacionesDePresidente(int $presidenteId): array
    {
        return $this->conversaciones->listarEntrePresidentesDeUsuario($presidenteId);
    }

    public function listarConversacionesParaAdministracion(): array
    {
        return $this->conversaciones->listarConAdministracionConMensajes();
    }

    public function contarNoLeidos(array $usuario): int
    {
        return $usuario['rol'] === 'ADMINISTRADOR'
            ? $this->mensajes->contarNoLeidosParaAdministracion()
            : $this->mensajes->contarNoLeidosDePresidente((int) $usuario['id']);
    }

    /** @return array la fila de la conversación, si el usuario tiene acceso */
    private function validarAcceso(int $conversacionId, array $usuario): array
    {
        $conversacion = $this->conversaciones->buscarPorId($conversacionId);
        if ($conversacion === null) {
            throw new DomainException('La conversación no existe.');
        }

        $esAdmin = $usuario['rol'] === 'ADMINISTRADOR';
        $esParticipante = (int) $conversacion['usuario_iniciador_id'] === (int) $usuario['id']
            || (int) ($conversacion['usuario_contraparte_id'] ?? 0) === (int) $usuario['id'];

        $tieneAcceso = $conversacion['tipo'] === 'CON_ADMINISTRACION'
            ? ($esAdmin || $esParticipante)
            : $esParticipante;

        if (!$tieneAcceso) {
            throw new DomainException('No tienes acceso a esta conversación.');
        }

        return $conversacion;
    }
}
