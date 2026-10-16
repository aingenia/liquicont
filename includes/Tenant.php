<?php
/**
 * includes/Tenant.php
 * 
 * Detección y gestión del tenant actual.
 * 
 * En producción: el tenant se identifica por subdominio (demo.liquicont.app).
 * En desarrollo: si no hay subdominio (localhost), usa tenant_id = 1.
 * 
 * El tenant actual se guarda en memoria tras el bootstrap y queda
 * disponible en toda la app vía Tenant::id(), Tenant::current(), etc.
 */

class Tenant
{
    private static ?array $current = null;
    private static bool $bootstrapped = false;

    /**
     * Punto de entrada. Se llama una sola vez, desde index.php.
     */
    public static function bootstrap(): void
    {
        if (self::$bootstrapped) {
            return;
        }
        self::$bootstrapped = true;

        $host = $_SERVER['HTTP_HOST'] ?? '';
        $subdomain = self::extractSubdomain($host);

        // Sin subdominio → modo desarrollo o dominio central
        if ($subdomain === null) {
            if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
                $tenant = self::findById(1);
                if (!$tenant) {
                    self::fail('No hay tenant configurado para desarrollo. Ejecutá la migración.');
                }
                self::$current = $tenant;
                return;
            }
            // En producción sin subdominio: es el dominio central, no hay tenant
            return;
        }

        // Con subdominio → buscar el tenant
        $tenant = self::findBySubdomain($subdomain);
        if (!$tenant) {
            self::fail('Cliente no encontrado.');
        }

        self::$current = $tenant;
    }

    /**
     * Extrae el subdominio del host.
     * Devuelve null si:
     *  - Es localhost/127.0.0.1
     *  - Es un dominio sin subdominio (ej: liquicont.app)
     *  - Es un subdominio central (admin, api, app, www)
     */
    private static function extractSubdomain(string $host): ?string
    {
        // Quitar puerto (ej: localhost:8080 → localhost)
        $host = explode(':', $host)[0];

        if ($host === '') {
            return null;
        }

        // Hosts locales
        $localHosts = ['localhost', '127.0.0.1', '::1'];
        if (in_array($host, $localHosts, true)) {
            return null;
        }

        // Sacar www.
        $host = preg_replace('/^www\./', '', $host);

        $parts = explode('.', $host);

        // Necesita al menos 3 partes para tener subdominio (demo.liquicont.app)
        if (count($parts) < 3) {
            return null;
        }

        $subdomain = $parts[0];

        // Subdominios centrales que no son tenants
        $centralSubs = ['admin', 'api', 'app'];
        if (in_array($subdomain, $centralSubs, true)) {
            return null;
        }

        return $subdomain;
    }

    private static function findBySubdomain(string $subdomain): ?array
    {
        try {
            $db = Conexion::getConnection();
            $stmt = $db->prepare(
                "SELECT * FROM tenants WHERE subdomain = :s AND estado = 'ACTIVO' LIMIT 1"
            );
            $stmt->execute([':s' => $subdomain]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log('[Tenant::findBySubdomain] ' . $e->getMessage());
            return null;
        }
    }

    private static function findById(int $id): ?array
    {
        try {
            $db = Conexion::getConnection();
            $stmt = $db->prepare(
                "SELECT * FROM tenants WHERE id = :id AND estado = 'ACTIVO' LIMIT 1"
            );
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (PDOException $e) {
            error_log('[Tenant::findById] ' . $e->getMessage());
            return null;
        }
    }

    private static function fail(string $message): void
    {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        die($message);
    }

    /* ==========================================================
       API pública
       ========================================================== */

    public static function current(): ?array
    {
        return self::$current;
    }

    public static function id(): ?int
    {
        return isset(self::$current['id']) ? (int)self::$current['id'] : null;
    }

    public static function name(): ?string
    {
        return self::$current['name'] ?? null;
    }

    public static function subdomain(): ?string
    {
        return self::$current['subdomain'] ?? null;
    }

    public static function isLoaded(): bool
    {
        return self::$current !== null;
    }
}