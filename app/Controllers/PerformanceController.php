<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Paginator;
use App\Core\Settings;
use App\Core\Validator;
use App\Models\OrgModel;
use App\Models\PerformanceModel;

/**
 * Penilaian kinerja.
 *  - /performance            : monitor & kalibrasi (HR)
 *  - /performance/periods    : periode penilaian (HR)
 *  - /performance/indicators : indikator & bobot (HR)
 *  - /performance/team       : penilaian bawahan langsung (atasan)
 *  - /me/performance         : self assessment & hasil (karyawan)
 */
final class PerformanceController extends Controller
{
    private PerformanceModel $performance;
    private OrgModel $org;

    public function __construct()
    {
        $this->performance = new PerformanceModel();
        $this->org = new OrgModel();
    }

    /* ----------------------------------------------------------------- HR */

    public function index(): void
    {
        $this->requireRole('HR');

        $filters = [
            'period' => (int) ($_GET['period'] ?? 0),
            'status' => (string) ($_GET['status'] ?? ''),
            'unit'   => (int) ($_GET['unit'] ?? 0),
            'rating' => (string) ($_GET['rating'] ?? ''),
            'q'      => trim((string) ($_GET['q'] ?? '')),
        ];
        if (!isset(PerformanceModel::STATUSES[$filters['status']])) {
            $filters['status'] = '';
        }
        if (!isset(PerformanceModel::RATING_LABELS[$filters['rating']])) {
            $filters['rating'] = '';
        }

        $paginator = Paginator::fromRequest($this->performance->countReviews($filters), 15);

        $this->view('performance/index', [
            'title'       => 'Penilaian Kinerja',
            'pageTitle'   => 'Penilaian Kinerja',
            'reviews'     => $this->performance->reviews($filters, $paginator->perPage, $paginator->offset()),
            'paginator'   => $paginator,
            'filters'     => $filters,
            'stats'       => $this->performance->stats(),
            'periods'     => $this->performance->periods(),
            'units'       => $this->org->units(),
            'statuses'    => PerformanceModel::STATUSES,
            'statusTones' => PerformanceModel::STATUS_TONES,
            'ratings'     => PerformanceModel::RATING_LABELS,
            'ratingTones' => PerformanceModel::RATING_TONES,
            'pageLink'    => static function (int $page) use ($filters): string {
                $query = array_filter([
                    'period' => $filters['period'] ?: null,
                    'status' => $filters['status'] ?: null,
                    'unit'   => $filters['unit'] ?: null,
                    'rating' => $filters['rating'] ?: null,
                    'q'      => $filters['q'] ?: null,
                    'page'   => $page > 1 ? $page : null,
                ], static fn ($v) => $v !== null && $v !== '');
                return url('performance' . ($query ? '?' . http_build_query($query) : ''));
            },
        ]);
    }

    public function periods(): void
    {
        $this->requireRole('HR');

        $editing = null;
        $editId = (int) ($_GET['edit'] ?? 0);
        if ($editId > 0) {
            $editing = $this->performance->findPeriod($editId);
        }

        $this->view('performance/periods', [
            'title'       => 'Periode Penilaian',
            'pageTitle'   => 'Periode Penilaian',
            'periods'     => $this->performance->periods(),
            'editing'     => $editing,
            'units'       => $this->org->units(),
            'types'       => PerformanceModel::PERIOD_TYPES,
            'statuses'    => PerformanceModel::PERIOD_STATUSES,
            'statusTones' => PerformanceModel::PERIOD_TONES,
            'weightTotal' => $this->performance->weightTotal(),
            'indicators'  => $this->performance->indicators(true),
            'defaultSelfWeight' => (int) Settings::get('performance_self_weight', 30),
        ] + $this->withInput());
    }

    public function storePeriod(): void
    {
        $this->requireRole('HR');
        $data = $this->validatedPeriod();

        $id = $this->performance->createPeriod($data, $this->employeeId());
        Audit::log('performance.period.create', 'review_period', $id, ['name' => $data['name']]);

        flash('success', 'Periode "' . $data['name'] . '" dibuat sebagai draf. Buka periode untuk mengirim self assessment ke karyawan.');
        redirect('/performance/periods');
    }

    public function updatePeriod(string $id): void
    {
        $this->requireRole('HR');
        $period = $this->performance->findPeriod((int) $id);
        if (!$period) {
            flash('error', 'Periode tidak ditemukan.');
            redirect('/performance/periods');
        }

        $data = $this->validatedPeriod();
        $this->performance->updatePeriod((int) $id, $data);
        Audit::log('performance.period.update', 'review_period', (int) $id, ['name' => $data['name']]);

        flash('success', 'Periode penilaian diperbarui.');
        redirect('/performance/periods');
    }

    public function openPeriod(string $id): void
    {
        $this->requireRole('HR');
        $result = $this->performance->openPeriod((int) $id);

        if ($result['error'] !== null) {
            flash('error', $result['error']);
        } else {
            Audit::log('performance.period.open', 'review_period', (int) $id, [
                'created' => $result['created'],
                'skipped' => $result['skipped'],
            ]);
            flash('success', 'Periode dibuka. ' . $result['created'] . ' penilaian dibuat'
                . ($result['skipped'] > 0 ? ', ' . $result['skipped'] . ' peserta dilewati karena sudah ada.' : '.'));
        }
        redirect('/performance/periods');
    }

    public function calibration(string $id): void
    {
        $this->requireRole('HR');
        $pending = $this->performance->markCalibration((int) $id);
        Audit::log('performance.period.calibration', 'review_period', (int) $id, ['pending' => $pending]);
        flash('success', 'Periode masuk tahap kalibrasi HR. ' . $pending . ' penilaian menunggu penetapan hasil.');
        redirect('/performance?period=' . (int) $id);
    }

    public function closePeriod(string $id): void
    {
        $this->requireRole('HR');
        $pending = $this->performance->closePeriod((int) $id);
        Audit::log('performance.period.close', 'review_period', (int) $id, ['not_final' => $pending]);
        flash($pending > 0 ? 'warning' : 'success', $pending > 0
            ? 'Periode ditutup, namun ' . $pending . ' penilaian belum final dan tidak dapat diubah lagi.'
            : 'Periode penilaian ditutup. Semua penilaian sudah final.');
        redirect('/performance/periods');
    }

    public function destroyPeriod(string $id): void
    {
        $this->requireRole('HR');
        $period = $this->performance->findPeriod((int) $id);
        if (!$period) {
            flash('error', 'Periode tidak ditemukan.');
            redirect('/performance/periods');
        }
        if ($period['status'] !== 'DRAFT') {
            flash('error', 'Hanya periode berstatus draf yang dapat dihapus. Tutup periode bila sudah selesai.');
            redirect('/performance/periods');
        }

        $this->performance->deletePeriod((int) $id);
        Audit::log('performance.period.delete', 'review_period', (int) $id, ['name' => $period['name']]);
        flash('success', 'Periode penilaian dihapus.');
        redirect('/performance/periods');
    }

    /* ----------------------------------------------------------- indikator */

    public function indicators(): void
    {
        $this->requireRole('HR');

        $editing = null;
        $editId = (int) ($_GET['edit'] ?? 0);
        if ($editId > 0) {
            $editing = $this->performance->findIndicator($editId);
        }

        $this->view('performance/indicators', [
            'title'       => 'Indikator Penilaian',
            'pageTitle'   => 'Indikator Penilaian',
            'indicators'  => $this->performance->indicators(),
            'editing'     => $editing,
            'categories'  => PerformanceModel::CATEGORIES,
            'catTones'    => PerformanceModel::CATEGORY_TONES,
            'weightTotal' => $this->performance->weightTotal(),
        ] + $this->withInput());
    }

    public function storeIndicator(): void
    {
        $this->requireRole('HR');
        $data = $this->validatedIndicator();

        $exists = Database::value('SELECT id FROM review_indicators WHERE code = ?', [$data['code']]);
        if ($exists) {
            flash('error', 'Kode indikator "' . $data['code'] . '" sudah dipakai.');
            back('/performance/indicators');
        }

        $id = $this->performance->createIndicator($data);
        Audit::log('performance.indicator.create', 'review_indicator', $id, ['code' => $data['code']]);
        flash('success', 'Indikator "' . $data['name'] . '" ditambahkan.');
        redirect('/performance/indicators');
    }

    public function updateIndicator(string $id): void
    {
        $this->requireRole('HR');
        $indicator = $this->performance->findIndicator((int) $id);
        if (!$indicator) {
            flash('error', 'Indikator tidak ditemukan.');
            redirect('/performance/indicators');
        }

        $data = $this->validatedIndicator();
        $exists = Database::value('SELECT id FROM review_indicators WHERE code = ? AND id <> ?', [$data['code'], (int) $id]);
        if ($exists) {
            flash('error', 'Kode indikator "' . $data['code'] . '" sudah dipakai indikator lain.');
            back('/performance/indicators');
        }

        $this->performance->updateIndicator((int) $id, $data);
        Audit::log('performance.indicator.update', 'review_indicator', (int) $id, ['code' => $data['code']]);
        flash('success', 'Indikator diperbarui.');
        redirect('/performance/indicators');
    }

    public function destroyIndicator(string $id): void
    {
        $this->requireRole('HR');
        if ($this->performance->indicatorUsed((int) $id) > 0) {
            flash('error', 'Indikator sudah dipakai pada penilaian — nonaktifkan saja agar riwayat tetap utuh.');
            redirect('/performance/indicators');
        }

        $this->performance->deleteIndicator((int) $id);
        Audit::log('performance.indicator.delete', 'review_indicator', (int) $id);
        flash('success', 'Indikator dihapus.');
        redirect('/performance/indicators');
    }

    /* -------------------------------------------------------------- detail */

    public function show(string $id): void
    {
        $user = $this->employee();
        $review = $this->performance->review((int) $id);
        if (!$review) {
            $this->abort(404, 'Penilaian tidak ditemukan.');
        }

        $isHr = Auth::hasRole('HR');
        $isManager = (int) ($review['manager_id'] ?? 0) === (int) $user['id'];
        $isOwner = (int) $review['employee_id'] === (int) $user['id'];

        if (!$isHr && !$isManager && !$isOwner) {
            $this->abort(403, 'Anda tidak memiliki akses ke penilaian ini.');
        }

        $this->view('performance/show', [
            'title'       => 'Penilaian ' . $review['full_name'],
            'pageTitle'   => 'Detail Penilaian',
            'review'      => $review,
            'scores'      => $this->performance->scores((int) $review['id']),
            'isHr'        => $isHr,
            'isManager'   => $isManager,
            'isOwner'     => $isOwner,
            'statuses'    => PerformanceModel::STATUSES,
            'statusTones' => PerformanceModel::STATUS_TONES,
            'ratings'     => PerformanceModel::RATING_LABELS,
            'ratingTones' => PerformanceModel::RATING_TONES,
            'scale'       => PerformanceModel::SCALE_LABELS,
            'periodStatuses' => PerformanceModel::PERIOD_STATUSES,
            'periodTones' => PerformanceModel::PERIOD_TONES,
        ]);
    }

    public function calibrate(string $id): void
    {
        $this->requireRole('HR');

        $raw = trim((string) input('final_score', ''));
        $override = null;
        if ($raw !== '') {
            if (!is_numeric($raw) || (float) $raw < 1 || (float) $raw > 5) {
                flash('error', 'Nilai akhir manual harus berupa angka 1 sampai 5.');
                back('/performance/' . (int) $id);
            }
            $override = (float) $raw;
        }

        $result = $this->performance->finalize((int) $id, $override, (string) input('note', ''), $this->employeeId());
        if ($result['error'] !== null) {
            flash('error', $result['error']);
            back('/performance/' . (int) $id);
        }

        Audit::log('performance.finalize', 'performance_review', (int) $id, [
            'final'  => $result['review']['final_score'] ?? null,
            'rating' => $result['review']['rating'] ?? null,
        ]);
        flash('success', 'Hasil penilaian ditetapkan: nilai '
            . PerformanceModel::scoreLabel($result['review']['final_score'] ?? null)
            . ' (mutu ' . ($result['review']['rating'] ?? '-') . '). Karyawan telah diberi notifikasi.');
        back('/performance/' . (int) $id);
    }

    public function export(): void
    {
        $this->requireRole('HR');

        $filters = [
            'period' => (int) ($_GET['period'] ?? 0),
            'status' => (string) ($_GET['status'] ?? ''),
            'unit'   => (int) ($_GET['unit'] ?? 0),
            'rating' => (string) ($_GET['rating'] ?? ''),
            'q'      => trim((string) ($_GET['q'] ?? '')),
        ];
        $rows = $this->performance->reviews($filters, 5000, 0);

        $filename = 'penilaian-kinerja-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            'Periode', 'NIK', 'Karyawan', 'Unit', 'Departemen', 'Atasan',
            'Nilai Self', 'Nilai Atasan', 'Nilai Akhir', 'Mutu', 'Status', 'Self Terkirim', 'Final',
        ], ',', '"', '');

        foreach ($rows as $row) {
            fputcsv($out, [
                $row['period_name'],
                $row['employee_code'],
                $row['full_name'],
                $row['unit_name'] ?? '-',
                $row['department_name'] ?? '-',
                $row['manager_name'] ?? '-',
                $row['self_score'] !== null ? number_format((float) $row['self_score'], 2, '.', '') : '',
                $row['manager_score'] !== null ? number_format((float) $row['manager_score'], 2, '.', '') : '',
                $row['final_score'] !== null ? number_format((float) $row['final_score'], 2, '.', '') : '',
                $row['rating'] ?? '',
                PerformanceModel::STATUSES[$row['status']] ?? $row['status'],
                $row['self_submitted_at'] ?? '',
                $row['calibrated_at'] ?? '',
            ], ',', '"', '');
        }
        fclose($out);
        exit;
    }

    /* -------------------------------------------------------------- atasan */

    public function team(): void
    {
        $user = $this->employee();
        $employeeId = (int) $user['id'];

        $reports = (int) Database::value(
            'SELECT COUNT(*) FROM employees WHERE direct_manager_id = ? AND flag = ?',
            [$employeeId, 'ACTIVE'],
            0
        );
        if ($reports === 0 && !Auth::hasRole('HR')) {
            flash('info', 'Anda belum memiliki bawahan langsung pada struktur organisasi.');
        }

        $this->view('performance/team', [
            'title'       => 'Penilaian Tim',
            'pageTitle'   => 'Penilaian Tim',
            'reviews'     => $this->performance->teamReviews($employeeId),
            'periods'     => $this->performance->periods(),
            'pending'     => $this->performance->teamPendingCount($employeeId),
            'statuses'    => PerformanceModel::STATUSES,
            'statusTones' => PerformanceModel::STATUS_TONES,
            'ratings'     => PerformanceModel::RATING_LABELS,
            'ratingTones' => PerformanceModel::RATING_TONES,
        ]);
    }

    public function assess(string $id): void
    {
        $user = $this->employee();
        $review = $this->performance->review((int) $id);
        if (!$review) {
            $this->abort(404, 'Penilaian tidak ditemukan.');
        }

        $isHr = Auth::hasRole('HR');
        $isManager = (int) ($review['manager_id'] ?? 0) === (int) $user['id'];
        if (!$isHr && !$isManager) {
            $this->abort(403, 'Hanya atasan langsung atau HR yang dapat menilai karyawan ini.');
        }
        if ($review['status'] === 'FINAL') {
            flash('info', 'Penilaian sudah final. Gunakan halaman detail untuk melihat hasil.');
            redirect('/performance/' . (int) $review['id']);
        }
        if ($review['status'] === 'PENDING') {
            flash('error', 'Karyawan belum mengirim self assessment.');
            redirect('/performance/team');
        }

        $this->view('performance/assess', [
            'title'       => 'Nilai ' . $review['full_name'],
            'pageTitle'   => 'Penilaian Atasan',
            'review'      => $review,
            'scores'      => $this->performance->scores((int) $review['id']),
            'isHr'        => $isHr,
            'scale'       => PerformanceModel::SCALE_LABELS,
            'categories'  => PerformanceModel::CATEGORIES,
            'catTones'    => PerformanceModel::CATEGORY_TONES,
        ] + $this->withInput());
    }

    public function submitAssessment(string $id): void
    {
        $user = $this->employee();
        $scores = $this->collectScores();

        $result = $this->performance->submitManager(
            (int) $id,
            (int) $user['id'],
            Auth::hasRole('HR'),
            $scores,
            trim((string) input('note', ''))
        );

        if ($result['error'] !== null) {
            flash('error', $result['error']);
            back('/performance/team/' . (int) $id);
        }

        Audit::log('performance.manager_submit', 'performance_review', (int) $id, [
            'score' => $result['review']['manager_score'] ?? null,
        ]);
        flash('success', 'Penilaian atasan tersimpan. Menunggu kalibrasi HR.');
        redirect('/performance/team');
    }

    /* ------------------------------------------------------------ karyawan */

    public function mine(): void
    {
        $user = $this->employee();
        $employeeId = (int) $user['id'];

        $current = $this->performance->currentReview($employeeId);
        $currentScores = $current ? $this->performance->scores((int) $current['id']) : [];
        $history = $this->performance->employeeReviews($employeeId);

        $this->view('performance/mine', [
            'title'       => 'Penilaian Kinerja Saya',
            'pageTitle'   => 'Penilaian Kinerja',
            'current'     => $current,
            'scores'      => $currentScores,
            'history'     => $history,
            'statuses'    => PerformanceModel::STATUSES,
            'statusTones' => PerformanceModel::STATUS_TONES,
            'ratings'     => PerformanceModel::RATING_LABELS,
            'ratingTones' => PerformanceModel::RATING_TONES,
            'scale'       => PerformanceModel::SCALE_LABELS,
            'categories'  => PerformanceModel::CATEGORIES,
            'catTones'    => PerformanceModel::CATEGORY_TONES,
        ] + $this->withInput());
    }

    public function submitSelf(string $id): void
    {
        $user = $this->employee();

        $result = $this->performance->submitSelf(
            (int) $id,
            (int) $user['id'],
            $this->collectScores(),
            trim((string) input('self_note', ''))
        );

        if ($result['error'] !== null) {
            flash('error', $result['error']);
            redirect('/me/performance');
        }

        Audit::log('performance.self_submit', 'performance_review', (int) $id, [
            'score' => $result['review']['self_score'] ?? null,
        ]);
        flash('success', 'Self assessment terkirim. Atasan langsung Anda akan menilai pada tahap berikutnya.');
        redirect('/me/performance');
    }

    public function acknowledge(string $id): void
    {
        $result = $this->performance->acknowledge((int) $id, (int) $this->employeeId());
        if ($result['error'] !== null) {
            flash('error', $result['error']);
        } else {
            Audit::log('performance.acknowledge', 'performance_review', (int) $id);
            flash('success', 'Terima kasih, hasil penilaian telah Anda konfirmasi.');
        }
        redirect('/me/performance');
    }

    /* ---------------------------------------------------------------- util */

    /** @return array<int,int> indicator_id => nilai */
    private function collectScores(): array
    {
        $raw = $_POST['scores'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $scores = [];
        foreach ($raw as $indicatorId => $value) {
            $scores[(int) $indicatorId] = (int) $value;
        }
        return $scores;
    }

    /** @return array<string,mixed> */
    private function validatedPeriod(): array
    {
        $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('name', 'Nama periode')
              ->custom(mb_strlen((string) ($_POST['name'] ?? '')) > 120, 'name', 'Nama periode maksimal 120 karakter.')
              ->required('start_date', 'Tanggal mulai')
              ->date('start_date', 'Tanggal mulai')
              ->required('end_date', 'Tanggal selesai')
              ->date('end_date', 'Tanggal selesai')
              ->in('type', 'Jenis periode', array_keys(PerformanceModel::PERIOD_TYPES));
        }, '/performance/periods');

        $start = (string) input('start_date', '');
        $end = (string) input('end_date', '');
        if ($start !== '' && $end !== '' && $end < $start) {
            flash('error', 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.');
            back('/performance/periods');
        }

        $selfWeight = (int) input('self_weight', Settings::get('performance_self_weight', 30));
        if ($selfWeight < 0 || $selfWeight > 70) {
            flash('error', 'Bobot self assessment harus antara 0 dan 70 persen.');
            back('/performance/periods');
        }

        $unitId = (int) input('business_unit_id', 0);

        return [
            'name'             => trim((string) input('name', '')),
            'type'             => (string) input('type', 'TAHUNAN'),
            'start_date'       => $start,
            'end_date'         => $end,
            'self_due'         => $this->nullableDate((string) input('self_due', '')),
            'manager_due'      => $this->nullableDate((string) input('manager_due', '')),
            'business_unit_id' => $unitId > 0 ? $unitId : null,
            'self_weight'      => $selfWeight,
            'note'             => mb_substr(trim((string) input('note', '')), 0, 255) ?: null,
        ];
    }

    /** @return array<string,mixed> */
    private function validatedIndicator(): array
    {
        $this->validateOrRedirect(static function (Validator $v): void {
            $v->required('code', 'Kode indikator')
              ->custom(!preg_match('/^[A-Z0-9_-]{2,30}$/', (string) ($_POST['code'] ?? '')), 'code', 'Kode 2–30 karakter: huruf kapital, angka, garis bawah, atau tanda hubung.')
              ->required('name', 'Nama indikator')
              ->custom(mb_strlen((string) ($_POST['name'] ?? '')) > 150, 'name', 'Nama indikator maksimal 150 karakter.')
              ->in('category', 'Kategori', array_keys(PerformanceModel::CATEGORIES))
              ->required('weight', 'Bobot')
              ->numeric('weight', 'Bobot', 1);
        }, '/performance/indicators');

        $weight = (float) input('weight', 0);
        if ($weight > 100) {
            flash('error', 'Bobot satu indikator maksimal 100.');
            back('/performance/indicators');
        }

        return [
            'code'        => strtoupper(trim((string) input('code', ''))),
            'name'        => trim((string) input('name', '')),
            'description' => mb_substr(trim((string) input('description', '')), 0, 255),
            'category'    => (string) input('category', 'KOMPETENSI'),
            'weight'      => $weight,
            'is_active'   => (int) input('is_active', 0) === 1 ? 1 : 0,
            'sort_order'  => (int) input('sort_order', 0),
        ];
    }

    private function nullableDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || strtotime($value) === false) {
            return null;
        }
        return date('Y-m-d', (int) strtotime($value));
    }
}
