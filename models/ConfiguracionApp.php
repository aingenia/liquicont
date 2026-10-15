<?php
// models/ConfiguracionApp.php

require_once __DIR__ . '/../config/conexion.php';

class ConfiguracionApp
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Conexion::getConnection();
    }

    public function obtener(string $clave, ?string $porDefecto = null): ?string
    {
        $stmt = $this->db->prepare('SELECT valor FROM configuracion_app WHERE clave = :c LIMIT 1');
        $stmt->execute([':c' => $clave]);
        $row = $stmt->fetch();
        return $row ? $row['valor'] : $porDefecto;
    }

    public function obtenerFloat(string $clave, float $porDefecto = 0.0): float
    {
        $v = $this->obtener($clave);
        return ($v !== null && $v !== '') ? (float)$v : $porDefecto;
    }

    public function obtenerInt(string $clave, int $porDefecto = 0): int
    {
        $v = $this->obtener($clave);
        return ($v !== null && $v !== '') ? (int)$v : $porDefecto;
    }

    public function guardar(string $clave, string $valor, ?string $descripcion = null): bool
    {
        $sql = "INSERT INTO configuracion_app (clave, valor, descripcion)
                VALUES (:c, :v, :d)
                ON DUPLICATE KEY UPDATE valor = VALUES(valor)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':c' => $clave, ':v' => $valor, ':d' => $descripcion]);
    }
}