<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Paginator;
use App\Core\Upload;
use App\Core\Validator;
use App\Models\DocumentModel;
use App\Models\OrgModel;

/**
 * Dokumen karyawan (Batch 19).
 *
 * HR mengelola seluruh dokumen (unggah, verifikasi, unduh, hapus); karyawan
 * hanya melihat dokumennya sendiri yang tidak bersifat rahasia dan boleh
 * mengunggah dokumen baru untuk diverifikasi HR.
 */
final class DocumentController extends Controller
{
    private DocumentModel $documents;
    private OrgModel $org;

    public function __construct()
    {
        $this->documents = new DocumentModel();
        $this->org = new OrgModel();
    }

    /* ============================================================ daftar HR */

    public function index(): void
    {
        $filters = $this->documents->filtersFromRequest();
        $perPage = 15;
        $total = $this->documents->count($filters);
        $paginator = new Paginator($total, $perPage, min(
            max(1, (int) ($_GET['page'] ?? 1)),
            max(1, (int) ceil($total / $perPage))
        ));

        $this->view('documents/index', [
            'title'     => 'Dokumen Karyawan',
            'pageTitle' => 'Dokumen Karyawan',
            'rows'      => $this->documents->paginate($filters, $perPage, $paginator->offset()),
            'paginator' => $paginator,
            'filters'   => $filters,
            'stats'     => $this->documents->stats(),
            'byType'    => $this->documents->byType(),
            'expiring'  => $this->documents->expiringSoon(6),
            'types'     => DocumentModel::TYPES,
            'statuses'  => DocumentModel::STATUSES,
            'units'     => $this->org->units(),
            'employees' => $this->documents->employeeOptions(),
        ] + $this->withInput());
    }

    /* ============================================================ unggah HR */

    public function create(): void
    {
        $employeeId = (int) ($_GET['employee_id'] ?? 0);
        $employee = $employeeId > 0
            ? Database::one('SELECT id, full_name, employee_code FROM employees WHERE id = ?', [$employeeId])
            : null;

        $this->view('documents/form', [
            'title'     => 'Unggah Dokumen',
            'pageTitle' => 'Unggah Dokumen',
            'document'  => null,
            'employee'  => $employee,
            'types'     => DocumentModel::TYPES,
            'employees' => $this->documents->employeeOptions(),
            'backUrl'   => $employee ? 'employees/' . $employee['id'] : 'documents',
        ] + $this->withInput());
    }

    public function store(): void
    {
        $data = $this->validateOrRedirect(function (Validator $v): void {
            $v->required('employee_id', 'Karyawan');
            $v->required('doc_type', 'Jenis dokumen');
            $v->in('doc_type', 'Jenis dokumen', array_keys(DocumentModel::TYPES));
            $v->required('title', 'Judul dokumen');
            $v->custom(mb_strlen((string) ($_POST['title'] ?? '')) > 160, 'title', 'Judul maksimal 160 karakter.');
            $v->custom(mb_strlen((string) ($_POST['document_number'] ?? '')) > 80, 'document_number', 'Nomor dokumen maksimal 80 karakter.');
            $v->date('issued_at', 'Tanggal terbit');
            $v->date('expires_at', 'Tanggal kedaluwarsa');
            $v->custom(
                !empty($_POST['issued_at']) && !empty($_POST['expires_at'])
                    && strtotime((string) $_POST['expires_at']) < strtotime((string) $_POST['issued_at']),
                'expires_at',
                'Tanggal kedaluwarsa tidak boleh sebelum tanggal terbit.'
            );
            $v->custom(
                empty($_FILES['file']['name'] ?? ''),
                'file',
                'Berkas dokumen wajib dipilih (PDF, JPG, atau PNG).'
            );
        }, '/documents/create');

        $employeeId = (int) $data['employee_id'];
        $employee = Database::one('SELECT id, full_name, employee_code FROM employees WHERE id = ?', [$employeeId]);
        if (!$employee) {
            flash('error', 'Karyawan tidak ditemukan.');
            redirect('/documents');
        }

        $upload = Upload::document('file', 'documents/' . $employeeId);
        if ($upload['error'] || !$upload['path']) {
            flash('error', $upload['error'] ?: 'Berkas gagal diunggah.');
            back('/documents/create?employee_id=' . $employeeId);
        }

        $id = $this->documents->create([
            'employee_id'     => $employeeId,
            'doc_type'        => (string) $data['doc_type'],
            'title'           => trim((string) $data['title']),
            'document_number' => trim((string) ($data['document_number'] ?? '')),
            'file_path'       => (string) $upload['path'],
            'file_name'       => (string) ($_FILES['file']['name'] ?? ''),
            'file_size'       => (int) ($_FILES['file']['size'] ?? 0),
            'mime_type'       => (string) ($_FILES['file']['type'] ?? ''),
            'issued_at'       => $data['issued_at'] ?? null,
            'expires_at'      => $data['expires_at'] ?? null,
            'status'          => !empty($_POST['verify_now']) ? 'VERIFIED' : 'UNVERIFIED',
            'is_confidential' => !empty($_POST['is_confidential']),
            'note'            => trim((string) ($data['note'] ?? '')),
            'uploaded_by'     => $this->employeeId(),
        ]);

        if (!empty($_POST['verify_now'])) {
            Database::update('employee_documents', [
                'verified_by' => $this->employeeId(),
                'verified_at' => date('Y-m-d H:i:s'),
            ], 'id = ?', [$id]);
        }

        Audit::log('document.create', 'employee_document', $id, [
            'employee' => $employee['employee_code'],
            'type'     => $data['doc_type'],
            'title'    => $data['title'],
        ]);

        flash('success', 'Dokumen "' . $data['title'] . '" tersimpan untuk ' . $employee['full_name'] . '.');
        redirect('/employees/' . $employeeId . '#documents');
    }

    /* ============================================================= karyawan */

    public function mine(): void
    {
        $employeeId = $this->employeeId();

        $this->view('documents/mine', [
            'title'     => 'Dokumen Saya',
            'pageTitle' => 'Dokumen Saya',
            'documents' => $this->documents->forEmployee($employeeId, false),
            'summary'   => $this->documents->summaryFor($employeeId),
            'types'     => DocumentModel::TYPES,
            'statuses'  => DocumentModel::STATUSES,
        ] + $this->withInput());
    }

    /** Karyawan mengunggah dokumen sendiri (status belum diverifikasi). */
    public function storeMine(): void
    {
        $employeeId = $this->employeeId();

        $data = $this->validateOrRedirect(function (Validator $v): void {
            $v->required('doc_type', 'Jenis dokumen');
            $v->in('doc_type', 'Jenis dokumen', array_keys(DocumentModel::TYPES));
            $v->required('title', 'Judul dokumen');
            $v->custom(mb_strlen((string) ($_POST['title'] ?? '')) > 160, 'title', 'Judul maksimal 160 karakter.');
            $v->date('issued_at', 'Tanggal terbit');
            $v->date('expires_at', 'Tanggal kedaluwarsa');
            $v->custom(empty($_FILES['file']['name'] ?? ''), 'file', 'Berkas dokumen wajib dipilih.');
        }, '/me/documents');

        $upload = Upload::document('file', 'documents/' . $employeeId);
        if ($upload['error'] || !$upload['path']) {
            flash('error', $upload['error'] ?: 'Berkas gagal diunggah.');
            redirect('/me/documents');
        }

        $id = $this->documents->create([
            'employee_id'     => $employeeId,
            'doc_type'        => (string) $data['doc_type'],
            'title'           => trim((string) $data['title']),
            'document_number' => trim((string) ($data['document_number'] ?? '')),
            'file_path'       => (string) $upload['path'],
            'file_name'       => (string) ($_FILES['file']['name'] ?? ''),
            'file_size'       => (int) ($_FILES['file']['size'] ?? 0),
            'mime_type'       => (string) ($_FILES['file']['type'] ?? ''),
            'issued_at'       => $data['issued_at'] ?? null,
            'expires_at'      => $data['expires_at'] ?? null,
            'status'          => 'UNVERIFIED',
            'is_confidential' => false,
            'note'            => trim((string) ($data['note'] ?? '')),
            'uploaded_by'     => $employeeId,
        ]);

        Audit::log('document.upload_self', 'employee_document', $id, ['type' => $data['doc_type']]);

        // Beri tahu HR bahwa ada dokumen baru menunggu verifikasi.
        foreach (Database::all("SELECT id FROM employees WHERE role = 'HR' AND flag = 'ACTIVE'") as $hr) {
            try {
                Database::insert('notifications', [
                    'employee_id' => (int) $hr['id'],
                    'title'       => 'Dokumen baru menunggu verifikasi',
                    'message'     => mb_substr((string) $data['title'], 0, 120) . ' diunggah oleh karyawan.',
                    'link'        => '/documents?status=UNVERIFIED',
                    'type'        => 'info',
                    'created_at'  => date('Y-m-d H:i:s'),
                ]);
            } catch (\Throwable) {
                // best-effort
            }
        }

        flash('success', 'Dokumen terkirim. HR akan memverifikasi dalam 1–2 hari kerja.');
        redirect('/me/documents');
    }

    /* ========================================================== verifikasi */

    public function verify(string $id): void
    {
        $document = $this->documents->find((int) $id);
        if (!$document) {
            flash('error', 'Dokumen tidak ditemukan.');
            redirect('/documents');
        }

        $status = strtoupper((string) ($_POST['status'] ?? 'VERIFIED'));
        if (!in_array($status, ['VERIFIED', 'REJECTED'], true)) {
            $status = 'VERIFIED';
        }
        $note = trim((string) ($_POST['note'] ?? ''));

        if ($status === 'REJECTED' && $note === '') {
            flash('error', 'Alasan penolakan wajib diisi agar karyawan tahu apa yang harus diperbaiki.');
            redirect('/documents/' . (int) $id . '/edit');
        }

        $this->documents->verify((int) $id, $this->employeeId(), $status, $note);
        Audit::log('document.verify', 'employee_document', (int) $id, ['status' => $status]);

        // Kabari karyawan lewat lonceng.
        try {
            Database::insert('notifications', [
                'employee_id' => (int) $document['employee_id'],
                'title'       => $status === 'VERIFIED' ? 'Dokumen terverifikasi' : 'Dokumen perlu diperbaiki',
                'message'     => mb_substr((string) $document['title'], 0, 120)
                    . ($note !== '' ? ' — ' . mb_substr($note, 0, 100) : ''),
                'link'        => '/me/documents',
                'type'        => $status === 'VERIFIED' ? 'success' : 'warning',
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // best-effort
        }

        flash('success', $status === 'VERIFIED'
            ? 'Dokumen "' . $document['title'] . '" ditandai terverifikasi.'
            : 'Dokumen ditolak dan karyawan sudah diberi tahu.');
        redirect('/documents?status=' . ($status === 'VERIFIED' ? 'VERIFIED' : 'REJECTED'));
    }

    /* ============================================================== ubah */

    public function edit(string $id): void
    {
        $document = $this->documents->find((int) $id);
        if (!$document) {
            $this->abort(404, 'Dokumen tidak ditemukan.');
        }

        $this->view('documents/form', [
            'title'     => 'Ubah Dokumen',
            'pageTitle' => 'Ubah Dokumen',
            'document'  => $document,
            'employee'  => [
                'id'            => (int) $document['employee_id'],
                'full_name'     => $document['full_name'],
                'employee_code' => $document['employee_code'],
            ],
            'types'     => DocumentModel::TYPES,
            'employees' => $this->documents->employeeOptions(),
            'backUrl'   => 'documents',
        ] + $this->withInput());
    }

    public function update(string $id): void
    {
        $document = $this->documents->find((int) $id);
        if (!$document) {
            $this->abort(404, 'Dokumen tidak ditemukan.');
        }

        $data = $this->validateOrRedirect(function (Validator $v): void {
            $v->required('doc_type', 'Jenis dokumen');
            $v->in('doc_type', 'Jenis dokumen', array_keys(DocumentModel::TYPES));
            $v->required('title', 'Judul dokumen');
            $v->date('issued_at', 'Tanggal terbit');
            $v->date('expires_at', 'Tanggal kedaluwarsa');
        }, '/documents/' . (int) $id . '/edit');

        $this->documents->updateMeta((int) $id, [
            'doc_type'        => (string) $data['doc_type'],
            'title'           => trim((string) $data['title']),
            'document_number' => trim((string) ($data['document_number'] ?? '')),
            'issued_at'       => $data['issued_at'] ?? null,
            'expires_at'      => $data['expires_at'] ?? null,
            'is_confidential' => !empty($_POST['is_confidential']),
            'note'            => trim((string) ($data['note'] ?? '')),
        ]);

        if (!empty($_FILES['file']['name'] ?? '')) {
            $upload = Upload::document('file', 'documents/' . (int) $document['employee_id']);
            if ($upload['error'] || !$upload['path']) {
                flash('error', $upload['error'] ?: 'Berkas pengganti gagal diunggah.');
                redirect('/documents/' . (int) $id . '/edit');
            }
            $this->documents->replaceFile(
                (int) $id,
                (string) $upload['path'],
                (string) ($_FILES['file']['name'] ?? ''),
                (int) ($_FILES['file']['size'] ?? 0),
                (string) ($_FILES['file']['type'] ?? '')
            );
            $old = (string) ($document['file_path'] ?? '');
            if ($old !== '' && $old !== $upload['path']) {
                @unlink(rtrim((string) config('upload_path'), '/') . '/' . $old);
            }
            flash('success', 'Dokumen diperbarui dan berkas baru diunggah (status kembali belum diverifikasi).');
        } else {
            flash('success', 'Metadata dokumen diperbarui.');
        }

        Audit::log('document.update', 'employee_document', (int) $id, ['title' => $data['title']]);
        redirect('/documents/' . (int) $id . '/edit');
    }

    /* ============================================================== unduh */

    public function download(string $id): void
    {
        $document = $this->documents->find((int) $id);
        if (!$document) {
            $this->abort(404, 'Dokumen tidak ditemukan.');
        }

        $isOwner = (int) ($document['employee_id'] ?? 0) === (int) Auth::id();
        $isHr = Auth::hasRole('HR');
        if (!$isHr && (!$isOwner || (int) $document['is_confidential'] === 1)) {
            $this->abort(403, 'Anda tidak berhak mengunduh dokumen ini.');
        }

        $root = realpath((string) config('upload_path'));
        $path = realpath(rtrim((string) config('upload_path'), '/') . '/' . (string) $document['file_path']);
        if ($root === false || $path === false || !str_starts_with($path, $root) || !is_file($path)) {
            $this->abort(404, 'Berkas dokumen tidak ditemukan pada penyimpanan.');
        }

        $name = (string) ($document['file_name'] ?: basename($path));
        $name = preg_replace('/[^A-Za-z0-9._ -]/', '_', $name) ?: basename($path);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'pdf'  => 'application/pdf',
            'png'  => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'application/octet-stream',
        };

        Audit::log('document.download', 'employee_document', (int) $id, ['file' => $name]);

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($path));
        header('Content-Disposition: inline; filename="' . $name . '"');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    /* ============================================================== hapus */

    public function destroy(string $id): void
    {
        $document = $this->documents->find((int) $id);
        if (!$document) {
            flash('error', 'Dokumen tidak ditemukan.');
            redirect('/documents');
        }

        $path = $this->documents->delete((int) $id);
        if ($path && $path !== '') {
            @unlink(rtrim((string) config('upload_path'), '/') . '/' . $path);
        }

        Audit::log('document.delete', 'employee_document', (int) $id, [
            'employee' => $document['employee_code'],
            'title'    => $document['title'],
        ]);

        flash('success', 'Dokumen "' . $document['title'] . '" dihapus.');
        redirect('/documents');
    }
}
