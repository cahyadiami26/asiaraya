<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Mailer;
use App\Core\Paginator;
use App\Core\Scheduler;
use App\Models\MailModel;

/**
 * Antrean email & konfigurasi SMTP (Batch 18).
 *
 * Halaman ini adalah "kotak keluar" HRIS: setiap email otomatis (pengingat
 * kontrak, sisa cuti, pengumuman, reset kata sandi) tercatat di sini beserta
 * status pengirimannya sehingga bisa diaudit atau dikirim ulang.
 */
final class MailController extends Controller
{
    private MailModel $mail;

    public function __construct()
    {
        $this->mail = new MailModel();
    }

    /* ------------------------------------------------------------- antrean */

    public function index(): void
    {
        $filters = $this->mail->filtersFromRequest();
        $perPage = 20;
        $total = $this->mail->countQueue($filters);
        $paginator = new Paginator(
            $total,
            $perPage,
            min(max(1, (int) ($_GET['page'] ?? 1)), max(1, (int) ceil($total / $perPage)))
        );

        $this->view('mail/index', [
            'title'      => 'Antrean Email',
            'pageTitle'  => 'Antrean Email',
            'rows'       => $this->mail->queue($filters, $perPage, $paginator->offset()),
            'paginator'  => $paginator,
            'filters'    => $filters,
            'stats'      => $this->mail->stats(),
            'templates'  => $this->mail->templates(),
            'statuses'   => Mailer::STATUSES,
            'maxAttempt' => Mailer::MAX_ATTEMPTS,
            'settings'   => $this->mail->settings(),
            'byTemplate' => $this->mail->byTemplate(5),
        ] + $this->withInput());
    }

    public function show(string $id): void
    {
        $row = $this->mail->find((int) $id);
        if (!$row) {
            $this->abort(404, 'Email tidak ditemukan pada antrean.');
        }

        $this->view('mail/show', [
            'title'    => 'Detail Email',
            'pageTitle' => 'Detail Email',
            'row'      => $row,
            'statuses' => Mailer::STATUSES,
            'settings' => $this->mail->settings(),
        ] + $this->withInput());
    }

    /** Kirim ulang satu email. */
    public function retry(string $id): void
    {
        $row = $this->mail->find((int) $id);
        if (!$row) {
            flash('error', 'Email tidak ditemukan.');
            redirect('/mail');
        }

        $result = $this->mail->retry((int) $id);
        Audit::log('mail.retry', 'email_queue', (int) $id, [
            'to'     => $row['recipient_email'],
            'status' => $result['ok'] ? 'ok' : 'gagal',
        ]);

        flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('/mail/' . (int) $id);
    }

    /** Batalkan email yang masih menunggu. */
    public function cancel(string $id): void
    {
        if ($this->mail->cancel((int) $id)) {
            Audit::log('mail.cancel', 'email_queue', (int) $id);
            flash('success', 'Email dibatalkan dan tidak akan dikirim.');
        } else {
            flash('warning', 'Hanya email berstatus menunggu yang dapat dibatalkan.');
        }
        redirect('/mail');
    }

    /** Proses antrean sekarang (tombol "Kirim sekarang"). */
    public function process(): void
    {
        $result = Scheduler::run('email_queue', 'MANUAL');

        if ($result['status'] === 'ERROR') {
            flash('error', 'Proses antrean gagal: ' . $result['message']);
        } elseif ($result['processed'] > 0) {
            flash('success', $result['message']);
        } else {
            flash('info', $result['message']);
        }
        redirect('/mail');
    }

    /** Hapus riwayat email lama. */
    public function prune(): void
    {
        $days = (int) ($_POST['days'] ?? 90);
        $days = max(7, min(730, $days ?: 90));
        $deleted = $this->mail->prune($days);

        Audit::log('mail.prune', 'email_queue', null, ['days' => $days, 'deleted' => $deleted]);
        flash($deleted > 0 ? 'success' : 'info', $deleted > 0
            ? $deleted . ' baris riwayat email lebih tua dari ' . $days . ' hari dihapus.'
            : 'Tidak ada riwayat email yang melewati ' . $days . ' hari.');
        redirect('/mail');
    }
}
