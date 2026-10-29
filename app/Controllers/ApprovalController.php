<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ApprovalEngine;
use App\Core\Auth;
use App\Core\Controller;

/**
 * Persetujuan dua tahap (atasan langsung → HR) untuk lembur, cuti, dan
 * perubahan jadwal. Logika keputusan berada di App\Core\ApprovalEngine agar
 * sama persis dengan yang dipakai REST API /api/v1 (aplikasi Android).
 */
final class ApprovalController extends Controller
{
    public function decideOvertime(string $id): void
    {
        $this->decide('overtime', $id, '/overtime');
    }

    public function decideLeave(string $id): void
    {
        $this->decide('leave', $id, '/leave');
    }

    public function decideShiftChange(string $id): void
    {
        $this->decide('schedule', $id, '/schedules/requests');
    }

    private function decide(string $type, string $id, string $fallback): void
    {
        $engine = new ApprovalEngine();
        $request = $engine->find($type, (int) $id);
        if (!$request) {
            $this->abort(404, 'Pengajuan tidak ditemukan.');
        }

        $result = $engine->decide(
            $type,
            $request,
            (string) input('decision', 'APPROVED'),
            (string) input('note', ''),
            Auth::user() ?? []
        );

        if (!$result['ok']) {
            flash('error', (string) $result['error']);
            back($fallback);
        }

        $status = (string) $result['status'];
        $label = match (true) {
            $status === 'APPROVED'         => 'disetujui (final)',
            $status === 'MANAGER_APPROVED' => 'disetujui atasan, menunggu HR',
            default                        => 'ditolak',
        };

        flash($status === 'REJECTED' ? 'warning' : 'success', 'Pengajuan ' . $label . '.');
        back($fallback);
    }
}
