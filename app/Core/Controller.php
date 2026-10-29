<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\View;

abstract class Controller
{
    protected function view(string $template, array $data = [], ?string $layout = 'layouts/app'): void
    {
        $data['currentUser'] = Auth::user();
        $data['pageTitle'] = $data['title'] ?? config('app_name');
        View::render($template, $data, $layout);
    }

    protected function redirect(string $path): never
    {
        redirect($path);
    }

    protected function json(mixed $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function abort(int $status, string $message = ''): never
    {
        http_response_code($status);
        $this->view('errors/error', ['code' => $status, 'message' => $message, 'title' => 'Terjadi Kesalahan']);
        exit;
    }

    protected function requireRole(string|array $roles): void
    {
        if (!Auth::hasRole($roles)) {
            $this->abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }
    }

    protected function requireManagerOrHr(int $employeeId): void
    {
        if (Auth::hasRole('HR') || Auth::isManagerOf($employeeId)) {
            return;
        }
        $this->abort(403, 'Hanya atasan langsung atau HR yang dapat melakukan aksi ini.');
    }

    /**
     * Validate, remember errors + old input, then redirect back.
     * Returns validated payload when it passes (caller continues).
     */
    protected function validateOrRedirect(callable $rules, string $fallback = '/'): array
    {
        $data = $_POST;
        $validator = new Validator($data);
        $rules($validator);

        if ($validator->fails()) {
            Session::set('_errors', $validator->errors());
            Session::set('_old', $data);
            flash('error', $validator->firstError() ?? 'Periksa kembali data yang diisi.');
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            redirect($referer !== '' ? $referer : $fallback);
        }

        Session::forget('_old');
        Session::forget('_errors');
        return $data;
    }

    /** Data for form re-render. */
    protected function withInput(array $extra = []): array
    {
        return [
            'errors' => Session::pull('_errors', []),
            'old'    => Session::pull('_old', []),
        ] + $extra;
    }

    protected function employee(): ?array
    {
        return Auth::user();
    }

    protected function employeeId(): int
    {
        $id = Auth::id();
        if (!$id) {
            $this->abort(401, 'Sesi berakhir.');
        }
        return $id;
    }
}
