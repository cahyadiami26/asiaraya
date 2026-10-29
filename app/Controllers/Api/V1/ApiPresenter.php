<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

/**
 * Pemetaan baris basis data → bentuk JSON yang stabil untuk aplikasi Android.
 *
 * Semua tanggal dikirim ISO-8601 (2026-10-30 / 2026-10-30T08:12:00+07:00),
 * nominal dalam rupiah (float), dan URL media dibuat absolut.
 */
trait ApiPresenter
{
    protected function employeePayload(array $user): array
    {
        return [
            'id'            => (int) $user['id'],
            'employee_code' => (string) ($user['employee_code'] ?? ''),
            'full_name'     => (string) ($user['full_name'] ?? ''),
            'email'         => (string) ($user['email'] ?? ''),
            'username'      => $user['username'] ?? null,
            'phone'         => $user['phone'] ?? null,
            'role'          => (string) ($user['role'] ?? 'KARYAWAN'),
            'flag'          => (string) ($user['flag'] ?? ''),
            'join_date'     => $this->isoDate($user['join_date'] ?? null),
            'position'      => $user['position_name'] ?? null,
            'department'    => $user['department_name'] ?? null,
            'business_unit' => $user['business_unit_name'] ?? null,
            'manager'       => [
                'id'   => isset($user['direct_manager_id']) ? (int) $user['direct_manager_id'] : null,
                'name' => $user['manager_name'] ?? null,
            ],
            'photo_url'     => $this->mediaUrl($user['avatar'] ?? null) ?: $this->mediaUrl($user['photo'] ?? null),
        ];
    }

    protected function attendancePayload(?array $row, ?array $context = null): array
    {
        if (!$row) {
            return [
                'date'         => $context['date'] ?? date('Y-m-d'),
                'checked_in'   => false,
                'checked_out'  => false,
                'status'       => null,
                'check_in_at'  => null,
                'check_out_at' => null,
            ];
        }

        return [
            'id'                  => (int) $row['id'],
            'date'                => (string) $row['work_date'],
            'status'              => (string) $row['status'],
            'checked_in'          => !empty($row['check_in_at']),
            'checked_out'         => !empty($row['check_out_at']),
            'check_in_at'         => $this->isoDateTime($row['check_in_at'] ?? null),
            'check_out_at'        => $this->isoDateTime($row['check_out_at'] ?? null),
            'check_in_status'     => $row['check_in_status'] ?? null,
            'late_minutes'        => (int) ($row['late_minutes'] ?? 0),
            'early_leave_minutes' => (int) ($row['early_leave_minutes'] ?? 0),
            'work_minutes'        => (int) ($row['work_minutes'] ?? 0),
            'work_hours'          => round(((int) ($row['work_minutes'] ?? 0)) / 60, 2),
            'shift_id'            => isset($row['shift_id']) ? (int) $row['shift_id'] : null,
            'shift_name'          => $row['shift_name'] ?? null,
            'operational_type'    => $row['operational_type'] ?? null,
            'check_in_photo_url'  => $this->mediaUrl($row['check_in_photo'] ?? null),
            'check_out_photo_url' => $this->mediaUrl($row['check_out_photo'] ?? null),
            'check_in_distance'   => isset($row['check_in_distance']) ? (int) $row['check_in_distance'] : null,
            'check_out_distance'  => isset($row['check_out_distance']) ? (int) $row['check_out_distance'] : null,
            'note'                => $row['check_in_note'] ?? null,
            'source'              => $row['source'] ?? null,
        ];
    }

    /** Ringkasan "hari ini" yang dipakai halaman beranda aplikasi. */
    protected function attendanceTodayPayload(array $context): array
    {
        $row = $context['attendance'] ?? null;

        return [
            'date'            => (string) ($context['date'] ?? date('Y-m-d')),
            'shift'           => $this->shiftPayload($context['schedule'] ?? null, (string) ($context['date'] ?? date('Y-m-d'))),
            'shift_start'     => $context['shift_start'] ?? null,
            'shift_end'       => $context['shift_end'] ?? null,
            'late_tolerance'  => (int) ($context['tolerance'] ?? 0),
            'operational_type'=> $context['operational'] ?? 'NORMAL',
            'can_check_in'    => (bool) ($context['can_check_in'] ?? false),
            'can_check_out'   => (bool) ($context['can_check_out'] ?? false),
            'attendance'      => $this->attendancePayload($row, $context),
            'geofence'        => [
                'required' => (bool) ($context['settings']['geofence_required'] ?? true),
                'selfie_required' => (bool) ($context['settings']['selfie_required'] ?? true),
                'unit'     => $context['unit']['name'] ?? null,
                'lat'      => $context['unit']['lat'] ?? null,
                'lng'      => $context['unit']['lng'] ?? null,
                'radius'   => $context['unit']['radius'] ?? 0,
            ],
        ];
    }

    protected function shiftPayload(?array $shift, ?string $date = null): ?array
    {
        if (!$shift || empty($shift['shift_id'])) {
            return null;
        }

        return [
            'id'              => (int) ($shift['shift_id'] ?? 0),
            'code'            => $shift['shift_code'] ?? ($shift['code'] ?? null),
            'name'            => $shift['shift_name'] ?? ($shift['name'] ?? null),
            'date'            => $date ?? $this->isoDate($shift['work_date'] ?? ($shift['date'] ?? null)),
            'start_time'      => $this->hhmm($shift['check_in_time'] ?? null),
            'end_time'        => $this->hhmm($shift['check_out_time'] ?? null),
            'break_minutes'   => (int) ($shift['break_minutes'] ?? 0),
            'late_tolerance'  => (int) ($shift['late_tolerance_minutes'] ?? 0),
            'operational_type'=> $shift['operational_type'] ?? 'NORMAL',
        ];
    }

    protected function leavePayload(array $row): array
    {
        return [
            'id'             => (int) $row['id'],
            'request_number' => $row['request_number'] ?? null,
            'type'           => [
                'id'   => (int) ($row['leave_type_id'] ?? 0),
                'name' => $row['type_name'] ?? ($row['leave_type_name'] ?? null),
                'code' => $row['type_code'] ?? null,
            ],
            'start_date'     => $this->isoDate($row['start_date'] ?? null),
            'end_date'       => $this->isoDate($row['end_date'] ?? null),
            'days'           => (float) ($row['days'] ?? 0),
            'reason'         => $row['reason'] ?? null,
            'status'         => (string) ($row['status'] ?? ''),
            'status_label'   => $this->statusLabel((string) ($row['status'] ?? '')),
            'attachment_url' => $this->mediaUrl($row['attachment'] ?? null),
            'created_at'     => $this->isoDateTime($row['created_at'] ?? null),
            'updated_at'     => $this->isoDateTime($row['updated_at'] ?? null),
            'decision'       => [
                'manager' => [
                    'name' => $row['manager_name'] ?? null,
                    'at'   => $this->isoDateTime($row['manager_approved_at'] ?? null),
                    'note' => $row['manager_note'] ?? null,
                ],
                'hr' => [
                    'name' => $row['hr_name'] ?? null,
                    'at'   => $this->isoDateTime($row['hr_approved_at'] ?? null),
                    'note' => $row['hr_note'] ?? null,
                ],
            ],
        ];
    }

    protected function overtimePayload(array $row): array
    {
        return [
            'id'                  => (int) $row['id'],
            'work_date'           => $this->isoDate($row['work_date'] ?? null),
            'planned_start'       => $this->hhmm($row['planned_start'] ?? null),
            'planned_end'         => $this->hhmm($row['planned_end'] ?? null),
            'planned_hours'       => (float) ($row['planned_hours'] ?? 0),
            'actual_hours'        => $row['actual_hours'] !== null ? (float) $row['actual_hours'] : null,
            'type'                => (string) ($row['type'] ?? 'REQUEST'),
            'reason'              => $row['reason'] ?? null,
            'status'              => (string) ($row['status'] ?? ''),
            'status_label'        => $this->statusLabel((string) ($row['status'] ?? '')),
            'compensation_amount' => $this->money($row['compensation_amount'] ?? 0),
            'created_at'          => $this->isoDateTime($row['created_at'] ?? null),
            'decision'            => [
                'manager' => [
                    'name' => $row['manager_name'] ?? null,
                    'at'   => $this->isoDateTime($row['manager_approved_at'] ?? null),
                    'note' => $row['manager_note'] ?? null,
                ],
                'hr' => [
                    'name' => $row['hr_name'] ?? null,
                    'at'   => $this->isoDateTime($row['hr_approved_at'] ?? null),
                    'note' => $row['hr_note'] ?? null,
                ],
            ],
        ];
    }

    protected function scheduleRequestPayload(array $row): array
    {
        return [
            'id'             => (int) $row['id'],
            'start_date'     => $this->isoDate($row['start_date'] ?? null),
            'end_date'       => $this->isoDate($row['end_date'] ?? null),
            'reason'         => $row['reason'] ?? null,
            'status'         => (string) ($row['status'] ?? ''),
            'status_label'   => $this->statusLabel((string) ($row['status'] ?? '')),
            'current_shift'  => [
                'id'   => isset($row['current_shift_id']) ? (int) $row['current_shift_id'] : null,
                'code' => $row['current_shift_code'] ?? null,
                'name' => $row['current_shift_name'] ?? null,
            ],
            'requested_shift' => [
                'id'        => (int) ($row['requested_shift_id'] ?? 0),
                'code'      => $row['requested_shift_code'] ?? ($row['shift_code'] ?? null),
                'name'      => $row['requested_shift_name'] ?? ($row['shift_name'] ?? null),
                'start_time'=> $this->hhmm($row['requested_start'] ?? null),
                'end_time'  => $this->hhmm($row['requested_end'] ?? null),
            ],
            'created_at'     => $this->isoDateTime($row['created_at'] ?? null),
        ];
    }

    protected function payslipPayload(array $row, bool $withComponents = false, array $components = []): array
    {
        $payload = [
            'id'            => (int) $row['id'],
            'slip_number'   => $row['slip_number'] ?? null,
            'period'        => [
                'year'  => (int) ($row['period_year'] ?? 0),
                'month' => (int) ($row['period_month'] ?? 0),
                'label' => $this->monthLabel((int) ($row['period_month'] ?? 0), (int) ($row['period_year'] ?? 0)),
                'pay_date' => $this->isoDate($row['pay_date'] ?? null),
                'status'   => $row['period_status'] ?? null,
            ],
            'business_unit' => $row['unit_name'] ?? null,
            'gross_salary'  => $this->money($row['gross_salary'] ?? 0),
            'total_deduction' => $this->money($row['total_deduction'] ?? 0),
            'net_salary'    => $this->money($row['net_salary'] ?? 0),
            'pph_amount'    => $this->money($row['pph_amount'] ?? 0),
            'take_home_pay' => $this->money($row['net_salary'] ?? 0),
            'download_url'  => url('api/v1/payslips/' . (int) $row['id'] . '/pdf'),
        ];

        if ($withComponents) {
            $payload['components'] = array_map(static fn (array $c): array => [
                'name'   => (string) ($c['name'] ?? ''),
                'type'   => (string) ($c['type'] ?? ''),
                'amount' => round((float) ($c['amount'] ?? 0), 2),
            ], $components);
        }

        return $payload;
    }

    protected function claimPayload(array $row): array
    {
        return [
            'id'           => (int) $row['id'],
            'claim_number' => (string) ($row['claim_number'] ?? ''),
            'category'     => [
                'id'   => (int) ($row['category_id'] ?? 0),
                'name' => $row['category_name'] ?? null,
                'code' => $row['category_code'] ?? null,
            ],
            'claim_date'   => $this->isoDate($row['claim_date'] ?? null),
            'amount'       => $this->money($row['amount'] ?? 0),
            'description'  => $row['description'] ?? null,
            'status'       => (string) ($row['status'] ?? ''),
            'status_label' => $this->claimStatusLabel((string) ($row['status'] ?? '')),
            'receipt_url'  => $this->mediaUrl($row['receipt'] ?? null),
            'created_at'   => $this->isoDateTime($row['created_at'] ?? null),
            'updated_at'   => $this->isoDateTime($row['updated_at'] ?? null),
        ];
    }

    protected function announcementPayload(array $row, bool $withBody = false): array
    {
        $payload = [
            'id'          => (int) $row['id'],
            'title'       => (string) ($row['title'] ?? ''),
            'category'    => (string) ($row['category'] ?? ''),
            'audience'    => (string) ($row['audience'] ?? ''),
            'is_pinned'   => (int) ($row['is_pinned'] ?? 0) === 1,
            'author'      => $row['author_name'] ?? null,
            'excerpt'     => $this->excerpt((string) ($row['body'] ?? '')),
            'published_at'=> $this->isoDateTime($row['publish_at'] ?? ($row['created_at'] ?? null)),
            'attachment_url' => $this->mediaUrl($row['attachment'] ?? null),
            'is_read'     => !empty($row['my_read_at']),
            'read_at'     => $this->isoDateTime($row['my_read_at'] ?? null),
        ];

        if ($withBody) {
            $payload['body'] = (string) ($row['body'] ?? '');
        }

        return $payload;
    }

    protected function notificationPayload(array $row): array
    {
        return [
            'id'         => (int) $row['id'],
            'title'      => (string) ($row['title'] ?? ''),
            'message'    => (string) ($row['message'] ?? ''),
            'type'       => (string) ($row['type'] ?? 'info'),
            'link'       => $row['link'] ?? null,
            'is_read'    => !empty($row['read_at']),
            'read_at'    => $this->isoDateTime($row['read_at'] ?? null),
            'created_at' => $this->isoDateTime($row['created_at'] ?? null),
        ];
    }

    protected function taxSlipPayload(array $slip): array
    {
        return [
            'id'              => (int) ($slip['id'] ?? 0),
            'tax_year'        => (int) ($slip['tax_year'] ?? 0),
            'slip_number'     => $slip['slip_number'] ?? null,
            'months_count'    => (int) ($slip['months_count'] ?? 0),
            'employee_name'   => $slip['employee_name'] ?? null,
            'npwp'            => $slip['npwp'] ?? null,
            'tax_status'      => $slip['tax_status'] ?? null,
            'gross_income'    => $this->money($slip['gross'] ?? 0),
            'netto'           => $this->money($slip['netto'] ?? 0),
            'pkp'             => $this->money($slip['pkp'] ?? 0),
            'tax_due'         => $this->money($slip['tax_due'] ?? 0),
            'tax_withheld'    => $this->money($slip['tax_withheld'] ?? 0),
            'tax_diff'        => $this->money($slip['tax_diff'] ?? 0),
            'issued_at'       => $this->isoDateTime($slip['issued_at'] ?? null),
            'download_url'    => url('api/v1/tax-slips/' . (int) ($slip['tax_year'] ?? 0) . '/pdf'),
        ];
    }

    /* ------------------------------------------------------------- formatting */

    protected function isoDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $time = strtotime((string) $value);
        return $time === false ? null : date('Y-m-d', $time);
    }

    protected function isoDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $time = strtotime((string) $value);
        return $time === false ? null : date('c', $time);
    }

    protected function hhmm(mixed $value): ?string
    {
        $value = (string) ($value ?? '');
        return $value === '' ? null : substr($value, 0, 5);
    }

    protected function mediaUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        if (preg_match('#^https?://#', $path)) {
            return $path;
        }
        return url('media/' . ltrim($path, '/'));
    }

    protected function excerpt(string $body, int $limit = 160): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '');
        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit) . '…' : $text;
    }

    protected function statusLabel(string $status): string
    {
        return [
            'PENDING'          => 'Menunggu atasan',
            'MANAGER_APPROVED' => 'Menunggu HR',
            'APPROVED'         => 'Disetujui',
            'REJECTED'         => 'Ditolak',
            'CANCELLED'        => 'Dibatalkan',
        ][$status] ?? $status;
    }

    protected function claimStatusLabel(string $status): string
    {
        return [
            'PENDING'          => 'Menunggu atasan',
            'MANAGER_APPROVED' => 'Menunggu HR/Finance',
            'APPROVED'         => 'Disetujui',
            'SCHEDULED'        => 'Dijadwalkan payroll',
            'PAID'             => 'Dibayarkan',
            'REJECTED'         => 'Ditolak',
            'CANCELLED'        => 'Dibatalkan',
        ][$status] ?? $status;
    }

    protected function monthLabel(int $month, int $year): string
    {
        $names = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        return ($names[$month] ?? (string) $month) . ' ' . $year;
    }
}
