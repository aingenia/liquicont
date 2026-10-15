<?php
/**
 * controllers/DashboardController.php
 * Página principal del sistema — requiere sesión activa.
 */

class DashboardController
{
    public function index(): void
    {
        // El middleware ya validó, pero por defensa doble chequeamos.
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . BASE_URL . 'login');
            exit;
        }

        // Variables que consume el layout
        $page_title     = 'Escritorio';
        $headerGreeting = 'HOLA. ' . strtoupper($_SESSION['user_nombre'] ?? 'USUARIO');

       // Cheques que vencen mañana
        $calModel = new CalendarioCheque();
        $chequesManana = $calModel->listarManana();
        $totalManana = array_sum(array_column($chequesManana, 'importe'));

        require_once ROOT_PATH . 'views' . DS . 'dashboard.php';
    }
}