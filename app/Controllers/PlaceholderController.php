<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Router;
use App\Models\DashboardModel;

/**
 * Friendly "module in progress" screen.
 *
 * The system is delivered batch by batch; instead of dead links every module
 * that is scheduled for a later batch renders this page with its scope.
 */
final class PlaceholderController extends Controller
{
    public function show(): void
    {
        $user = $this->employee();
        $module = (string) ($_GET['module'] ?? 'Modul');
        $batch = (string) ($_GET['batch'] ?? '');
        $model = new DashboardModel();

        $this->view('dashboard/placeholder', [
            'title'     => $module,
            'pageTitle' => $module,
            'user'      => $user,
            'module'    => $module,
            'batch'     => $batch,
            'badges'    => $model->badges($user),
        ]);
    }
}
