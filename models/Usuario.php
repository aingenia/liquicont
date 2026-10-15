<?php
// models/Usuario.php

require_once __DIR__ . '/../config/conexion.php';

class Usuario
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Conexion::getConnection();
    }

    /* ==========================================================
       CONSULTAS DE USUARIO
       ========================================================== */

    /**
     * Obtiene un usuario por su email, junto con el nombre de su rol.
     *
     * NOTA: NO filtra por estado acá. El controlador decide si un
     * usuario inactivo debe ver un mensaje específico o un genérico.
     *
     * @return array|null
     */
    public function obtenerPorEmail(string $email): ?array
    {
        $sql = "SELECT u.*, t.nombre AS nombre_rol
                FROM usuario u
                INNER JOIN tipo_usuario t ON u.rol = t.id
                WHERE u.email = :email 
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Obtiene un usuario por su ID (usado por el middleware de sesión
     * para revalidar contra la BD en cada request autenticado).
     *
     * @return array|null
     */
    public function obtenerPorId(int $id): ?array
    {
        $sql = "SELECT u.*, t.nombre AS nombre_rol
                FROM usuario u
                INNER JOIN tipo_usuario t ON u.rol = t.id
                WHERE u.id = :id
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /* ==========================================================
       RESET DE CONTRASEÑA
       ========================================================== */

    /**
     * Guarda un token de reseteo para el usuario indicado.
     * Antes de insertar:
     *   - purga tokens expirados de cualquier usuario (limpieza oportunista),
     *   - borra tokens previos del mismo usuario (un solo token activo por user).
     */
    public function guardarTokenReset(int $userId, string $token, string $expiracion): bool
    {
        try {
            // 1) Limpieza oportunista: tokens expirados de cualquier usuario
            $this->db
                ->prepare("DELETE FROM password_resets WHERE expira_en < NOW()")
                ->execute();

            // 2) Un solo token activo por usuario
            $stmtDel = $this->db->prepare("DELETE FROM password_resets WHERE user_id = :user_id");
            $stmtDel->execute([':user_id' => $userId]);

            // 3) Insertar el nuevo
            $sql = "INSERT INTO password_resets (user_id, token, expira_en)
                    VALUES (:user_id, :token, :expira_en)";
            $stmt = $this->db->prepare($sql);

            return $stmt->execute([
                ':user_id'   => $userId,
                ':token'     => $token,
                ':expira_en' => $expiracion,
            ]);
        } catch (PDOException $e) {
            error_log('[Usuario::guardarTokenReset] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Devuelve el registro de reset si el token existe y NO expiró.
     * Si expiró o no existe, devuelve null.
     *
     * @return array|null
     */
    public function obtenerResetPorToken(string $token): ?array
    {
        $sql = "SELECT * FROM password_resets
                WHERE token = :token
                  AND expira_en > NOW()
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':token' => $token]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Actualiza la contraseña del usuario y borra sus tokens de reset.
     * Todo dentro de una transacción: o se hace completo, o no se hace.
     */
    public function actualizarPassword(int $userId, string $passwordHash): bool
    {
        try {
            $this->db->beginTransaction();

            $sql = "UPDATE usuario
                    SET password = :password,
                        actualizado_el = NOW()
                    WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':password' => $passwordHash,
                ':id'       => $userId,
            ]);

            // Borrar tokens del usuario (el reset ya se consumió)
            $stmtDel = $this->db->prepare("DELETE FROM password_resets WHERE user_id = :user_id");
            $stmtDel->execute([':user_id' => $userId]);

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('[Usuario::actualizarPassword] ' . $e->getMessage());
            return false;
        }
    }

        /* ==========================================================
       LISTADO Y CRUD (solo admin/developer)
       ========================================================== */

    /**
     * Lista todos los usuarios con el nombre del rol.
     * Ordena activos primero, luego por nombre.
     */
       /**
     * Lista usuarios aplicando filtros opcionales.
     *
     * @param string $search  Búsqueda libre sobre nombre, email o teléfono.
     * @param string $status  'ACTIVO' | 'INACTIVO' | '' (todos).
     */
    public function listar(string $search = '', string $status = ''): array
    {
        $sql = "SELECT u.*, t.nombre AS nombre_rol
                FROM usuario u
                INNER JOIN tipo_usuario t ON u.rol = t.id
                WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (u.nombre LIKE :search1
                        OR u.email  LIKE :search2
                        OR u.tel    LIKE :search3)";
            $like = '%' . $search . '%';
            $params[':search1'] = $like;
            $params[':search2'] = $like;
            $params[':search3'] = $like;
        }
        if ($status === 'ACTIVO') {
            $sql .= " AND u.estado = 1";
        } elseif ($status === 'INACTIVO') {
            $sql .= " AND u.estado = 0";
        }

        $sql .= " AND u.rol <> 1 ORDER BY u.estado DESC, u.nombre ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Verifica si un email ya existe. Opcionalmente excluye un ID
     * (útil al editar: no colisiona con uno mismo).
     */
    public function emailExiste(string $email, ?int $exceptId = null): bool
    {
        $sql = "SELECT id FROM usuario WHERE email = :email";
        $params = [':email' => $email];

        if ($exceptId !== null) {
            $sql .= " AND id <> :id";
            $params[':id'] = $exceptId;
        }
        $sql .= " LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Lista los roles disponibles para el select.
     * Por defecto excluye los marcados como "oculto" (Desarrollador).
     */
    public function listarRoles(bool $incluirOcultos = false): array
    {
        $sql = "SELECT id, nombre FROM tipo_usuario";
        if (!$incluirOcultos) {
            $sql .= " WHERE oculto = 0";
        }
        $sql .= " ORDER BY id ASC";
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Crea un usuario nuevo. Devuelve el ID insertado o false.
     * $data['password'] debe venir YA hasheado.
     */
    public function crear(array $data): int|false
    {
        if ($this->emailExiste($data['email'])) {
            return false;
        }

        $sql = "INSERT INTO usuario (nombre, email, password, tel, rol, estado)
                VALUES (:nombre, :email, :password, :tel, :rol, :estado)";
        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([
            ':nombre'   => $data['nombre'],
            ':email'    => $data['email'],
            ':password' => $data['password'],
            ':tel'      => $data['tel']   ?? null,
            ':rol'      => $data['rol'],
            ':estado'   => $data['estado'] ?? 1,
        ]);

        return $ok ? (int)$this->db->lastInsertId() : false;
    }

    /**
     * Actualiza un usuario. Si $data['password'] viene vacío, no toca
     * la contraseña actual.
     */
    public function actualizar(int $id, array $data): bool
    {
        if ($this->emailExiste($data['email'], $id)) {
            return false;
        }

        $sql = "UPDATE usuario
                SET nombre = :nombre,
                    email  = :email,
                    tel    = :tel,
                    rol    = :rol,
                    estado = :estado,
                    actualizado_el = NOW()";
        $params = [
            ':nombre' => $data['nombre'],
            ':email'  => $data['email'],
            ':tel'    => $data['tel']   ?? null,
            ':rol'    => $data['rol'],
            ':estado' => $data['estado'],
            ':id'     => $id,
        ];

        if (!empty($data['password'])) {
            $sql .= ", password = :password";
            $params[':password'] = $data['password'];
        }

        $sql .= " WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Soft delete: marca estado = 0.
     */
    public function cambiarEstado(int $id, int $estado): bool
    {
        $sql = "UPDATE usuario SET estado = :estado, actualizado_el = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':estado' => $estado, ':id' => $id]);
    }

    /* ==========================================================
       PERFIL (todos los roles)
       ========================================================== */

    /**
     * Actualiza SOLO los campos propios del usuario.
     * Nunca toca rol ni estado — los ignora si vienen en $data.
     */
    public function actualizarPerfil(int $id, array $data): bool
    {
        if ($this->emailExiste($data['email'], $id)) {
            return false;
        }

        $sql = "UPDATE usuario
                SET nombre = :nombre,
                    email  = :email,
                    tel    = :tel,
                    actualizado_el = NOW()";
        $params = [
            ':nombre' => $data['nombre'],
            ':email'  => $data['email'],
            ':tel'    => $data['tel'] ?? null,
            ':id'     => $id,
        ];

        if (!empty($data['password'])) {
            $sql .= ", password = :password";
            $params[':password'] = $data['password'];
        }

        $sql .= " WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
}