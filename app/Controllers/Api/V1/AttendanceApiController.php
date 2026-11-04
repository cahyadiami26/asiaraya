<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\ApiController;
use App\Core\ApiRequest;
use App\Core\Audit;
use App\Core\Database;
use App\Models\AttendanceModel;

/**
 * Presensi online dari aplikasi Android.
 *
 * GET  /api/v1/attendance/today
 * POST /api/v1/attendance/punch        (Idempotency-Key didukung)
 * GET  /api/v1/attendance/history?month=YYYY-MM
 * GET  /api/v1/attendance/summary?month=YYYY-MM
 *
 * Semua aturan (geofence, selfie, toleransi keterlambatan, WFH) tetap
 * dijalankan AttendanceModel — API hanya membungkusnya dalam JSON.
 */
final class AttendanceApiController extends ApiController
{
    use ApiPresenter;

    private AttendanceModel $attendance;

    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
        $this->attendance = new AttendanceModel();
    }

    public function today(): void
    {
        $context = $this->attendance->context((int) $this->user['id']);
        $this->ok($this->attendanceTodayPayload($context));
    }

    public function punch(): void
    {
        $employeeId = (int) $this->user['id'];
        $body = $this->body();

        $type = strtolower((string) ($body['type'] ?? 'in'));
        if (!in_array($type, ['in', 'out'], true)) {
            $this->fail(422, 'Tipe presensi harus "in" atau "out".', ['type' => 'Gunakan in/out.'], 'VALIDATION_ERROR');
        }

        $lat = isset($body['lat']) && $body['lat'] !== '' ? (float) $body['lat'] : null;
        $lng = isset($body['lng']) && $body['lng'] !== '' ? (float) $body['lng'] : null;

        $payload = [
            'type'     => $type,
            'lat'      => $lat,
            'lng'      => $lng,
            'accuracy' => isset($body['accuracy']) ? (int) round((float) $body['accuracy']) : null,
            'photo'    => $this->photoInput(),
            'note'     => (string) ($body['note'] ?? ''),
            'device'   => (string) ($body['device'] ?? ApiRequest::userAgent()),
            'offline'  => ApiRequest::boolean($body['offline'] ?? false),
            'captured_at' => (string) ($body['captured_at'] ?? ''),
        ];

        $idempotencyKey = ApiRequest::idempotencyKey();
        $requestHash = hash('sha256', json_encode([$type, $lat, $lng, $payload['captured_at']]));

        if ($idempotencyKey !== null) {
            $replay = $this->replayResponse($employeeId, $idempotencyKey, $requestHash);
            if ($replay !== null) {
                $this->respond($replay, 200, ['idempotent_replay' => true]);
            }
        }

        $this->assertPunchRate($employeeId);

        $input = [
            'lat'      => $payload['lat'],
            'lng'      => $payload['lng'],
            'accuracy' => $payload['accuracy'],
            'photo'    => $payload['photo'],
            'note'     => $payload['note'],
            'device'   => $payload['device'],
        ];

        $result = $type === 'out'
            ? $this->attendance->checkOut($employeeId, $input)
            : $this->attendance->checkIn($employeeId, $input, null, 'ANDROID');

        if (!$result['ok']) {
            $this->fail(422, (string) $result['error'], [], 'PUNCH_REJECTED');
        }

        $context = $this->attendance->context($employeeId);
        $record = $this->attendance->forDay($employeeId, date('Y-m-d'));

        $response = [
            'punch' => [
                'type'           => $type,
                'status'         => $result['data']['status'] ?? null,
                'late_minutes'   => (int) ($result['data']['late_minutes'] ?? 0),
                'work_minutes'   => (int) ($result['data']['work_minutes'] ?? 0),
                'distance'       => $result['data']['distance'] ?? null,
                'geofence'       => $result['data']['message'] ?? null,
                'at'             => date('c'),
                'server_date'    => date('Y-m-d'),
            ],
            'attendance' => $this->attendancePayload($record, $context),
            'today'      => $this->attendanceTodayPayload($context),
            'message'    => (string) ($result['message'] ?? 'Presensi tercatat.'),
        ];

        if ($idempotencyKey !== null) {
            $this->storeIdempotent($employeeId, $idempotencyKey, $requestHash, $response);
        }

        Audit::log('api.attendance.punch', 'attendance', (int) ($record['id'] ?? 0), [
            'type'    => $type,
            'source'  => 'ANDROID',
            'offline' => $payload['offline'],
            'distance' => $result['data']['distance'] ?? null,
        ]);

        $this->ok($response);
    }

    public function history(): void
    {
        $employeeId = (int) $this->user['id'];
        $month = $this->monthParam();
        [$year, $monthNumber] = array_map('intval', explode('-', $month));

        $result = $this->attendance->mine($employeeId, $year, $monthNumber);

        $this->ok(
            array_map(fn (array $row): array => $this->attendancePayload($row), $result['rows']),
            [
                'month'  => $month,
                'totals' => [
                    'present'      => (int) $result['totals']['present'],
                    'late'         => (int) $result['totals']['late'],
                    'absent'       => (int) $result['totals']['absent'],
                    'leave'        => (int) $result['totals']['leave'],
                    'late_minutes' => (int) $result['totals']['late_minutes'],
                    'work_hours'   => round(((int) $result['totals']['work_minutes']) / 60, 2),
                ],
                'range' => ['from' => $result['start'], 'to' => $result['end']],
            ]
        );
    }

    public function summary(): void
    {
        $employeeId = (int) $this->user['id'];
        $month = $this->monthParam();

        $summary = $this->attendance->myMonthSummary($employeeId, $month);

        $this->ok($summary + ['month' => $month]);
    }

    /* ------------------------------------------------------------- internal */

    /**
     * Foto selfie: data URL base64 dari kamera Android, atau berkas multipart.
     * Model presensi yang memproses ulang (resize + cap waktu) via Upload::dataUrl.
     */
    private function photoInput(): ?string
    {
        $dataUrl = trim((string) ApiRequest::input('photo', ''));
        if ($dataUrl !== '') {
            return str_starts_with($dataUrl, 'data:') ? $dataUrl : 'data:image/jpeg;base64,' . $dataUrl;
        }

        $file = $_FILES['photo'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }
        if ((int) $file['size'] > 4 * 1024 * 1024) {
            $this->fail(422, 'Ukuran foto maksimal 4 MB.', ['photo' => 'Terlalu besar.'], 'UPLOAD_ERROR');
        }

        $info = @getimagesize((string) $file['tmp_name']);
        $mime = $info['mime'] ?? 'image/jpeg';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            $this->fail(422, 'Foto harus berupa JPG, PNG, atau WEBP.', ['photo' => 'Format tidak didukung.'], 'UPLOAD_ERROR');
        }

        $bytes = (string) @file_get_contents((string) $file['tmp_name']);
        if ($bytes === '') {
            return null;
        }

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    /** Maksimal 30 permintaan presensi per menit per karyawan. */
    private function assertPunchRate(int $employeeId): void
    {
        $attempts = (int) Database::value(
            "SELECT COUNT(*) FROM activity_logs
              WHERE action = 'api.attendance.punch' AND employee_id = ? AND created_at >= ?",
            [$employeeId, date('Y-m-d H:i:s', time() - 60)],
            0
        );

        if ($attempts >= 30) {
            $this->fail(429, 'Terlalu banyak permintaan presensi. Tunggu sebentar lalu coba lagi.', [], 'TOO_MANY_REQUESTS');
        }
    }

    /** Jawaban tersimpan untuk Idempotency-Key yang sama (presensi dobel). */
    private function replayResponse(int $employeeId, string $key, string $hash): ?array
    {
        try {
            $row = Database::one(
                "SELECT * FROM api_punch_logs WHERE employee_id = ? AND action = 'attendance.punch' AND idempotency_key = ?",
                [$employeeId, $key]
            );
        } catch (\Throwable) {
            return null;
        }

        if (!$row) {
            return null;
        }

        $decoded = json_decode((string) $row['response_body'], true);
        return is_array($decoded) ? $decoded : null;
    }

    private function storeIdempotent(int $employeeId, string $key, string $hash, array $response): void
    {
        try {
            $existing = Database::value(
                "SELECT id FROM api_punch_logs WHERE employee_id = ? AND action = 'attendance.punch' AND idempotency_key = ?",
                [$employeeId, $key]
            );
            if ($existing !== null) {
                return;
            }

            Database::insert('api_punch_logs', [
                'employee_id'     => $employeeId,
                'action'          => 'attendance.punch',
                'idempotency_key' => $key,
                'request_hash'    => $hash,
                'response_code'   => 200,
                'response_body'   => json_encode($response, JSON_UNESCAPED_UNICODE),
                'created_at'      => date('Y-m-d H:i:s'),
            ]);

            Database::delete('api_punch_logs', 'created_at < ?', [date('Y-m-d H:i:s', time() - 30 * 86400)]);
        } catch (\Throwable) {
            // Idempotensi bersifat pengaman tambahan; jangan gagalkan presensi.
        }
    }
}
