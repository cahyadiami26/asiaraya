<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Core\Validator;
use App\Models\SystemModel;

/**
 * Reset sistem (HR) — membersihkan seluruh data ujicoba dan menyisakan akun
 * administrator yang sedang login.
 */
final class SystemController extends Controller
{
    private SystemModel $system;

    public function __construct()
    {
        $this->system = new SystemModel();
    }

    public function reset(): void
    {
        $this->requireRole('HR');
        $admin = (array) $this->employee();

        $this->view('system/reset', [
            'title'     => 'Reset Sistem',
            'pageTitle' => 'Reset Sistem',
            'admin'     => $admin,
            'overview'  => $this->system->overview(),
            'uploadHuman' => $this->system->formatBytes((int) $this->system->overview()['upload_bytes']),
            'labels'    => SystemModel::LABELS,
            'lastReset' => [
                'at' => (string) (\App\Core\Settings::get('last_reset_at') ?: ''),
                'by' => (string) (\App\Core\Settings::get('last_reset_by') ?: ''),
            ],
        ] + $this->withInput());
    }

    public function runReset(): void
    {
        $this->requireRole('HR');
        $admin = (array) $this->employee();

        $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('confirm', 'Konfirmasi')->custom(
                strtoupper(trim((string) ($_POST['confirm'] ?? ''))) !== 'RESET',
                'confirm',
                'Ketik RESET (huruf kapital) untuk mengonfirmasi.'
            );
            $v->required('password', 'Kata sandi')->custom(
                !password_verify((string) ($_POST['password'] ?? ''), (string) (Auth::user()['password_hash'] ?? '')),
                'password',
                'Kata sandi tidak sesuai.'
            );
        }, '/system/reset');

        $options = [
            'data'      => !empty($_POST['data']),
            'master'    => !empty($_POST['master']),
            'employees' => !empty($_POST['employees']),
            'uploads'   => !empty($_POST['uploads']),
        ];
        if (!in_array(true, $options, true)) {
            flash('error', 'Pilih minimal satu kelompok data yang akan dihapus.');
            back('/system/reset');
        }

        // Jangan izinkan menghapus akun yang sedang dipakai.
        $adminId = (int) $admin['id'];
        $adminRow = Database::one('SELECT * FROM employees WHERE id = ?', [$adminId]);
        if (!$adminRow) {
            flash('error', 'Akun administrator tidak ditemukan.');
            back('/system/reset');
        }

        $result = $this->system->reset($options, $adminRow);

        // Setelah data dihapus, pastikan sesi tetap menunjuk akun yang masih ada.
        Database::update('employees', [
            'role'               => 'HR',
            'flag'               => 'ACTIVE',
            'employment_status'  => (string) ($adminRow['employment_status'] ?: 'PKWTT'),
            'updated_at'         => date('Y-m-d H:i:s'),
        ], 'id = ?', [$adminId]);

        Session::set('auth_id', $adminId);

        Audit::log('system.reset', 'system', null, [
            'options' => array_keys(array_filter($options)),
            'rows'    => $result['summary']['rows'],
            'employees_removed' => $result['summary']['employees'],
            'uploads' => $result['summary']['uploads'],
        ]);

        $message = 'Reset sistem selesai. ' . implode(' ', $result['log'])
            . ' Akun administrator ' . ($adminRow['email'] ?? '') . ' tetap aktif.';
        flash('success', $message);
        redirect('/system/reset');
    }
}
