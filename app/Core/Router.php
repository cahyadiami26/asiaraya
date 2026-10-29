<?php
declare(strict_types=1);

namespace App\Core;

/** Dependency free router with role middleware, CSRF enforcement and named routes. */
final class Router
{
    private array $routes = [];
    private array $named = [];
    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function add(string $methods, string $path, array $handler, array $options = []): self
    {
        $methods = array_map('strtoupper', explode('|', $methods));
        $route = [
            'methods' => $methods,
            'path'    => $path,
            'handler' => $handler,
            'roles'   => $options['roles'] ?? [],
            'auth'    => $options['auth'] ?? true,
            'name'    => $options['name'] ?? null,
            'csrf'    => $options['csrf'] ?? true,
            'params'  => $options['params'] ?? [],
        ];
        $this->routes[] = $route;
        if ($route['name']) {
            $this->named[$route['name']] = $path;
        }
        return $this;
    }

    public function get(string $path, array $handler, array $options = []): self
    {
        return $this->add('GET', $path, $handler, $options);
    }

    public function post(string $path, array $handler, array $options = []): self
    {
        return $this->add('POST', $path, $handler, $options);
    }

    public function any(string $methods, string $path, array $handler, array $options = []): self
    {
        return $this->add($methods, $path, $handler, $options);
    }

    public function dispatch(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'POST' && isset($_POST['_method'])) {
            $spoof = strtoupper((string) $_POST['_method']);
            if (in_array($spoof, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $spoof;
            }
        }
        $path = current_path();
        $path = rtrim($path, '/') ?: '/';

        foreach ($this->routes as $route) {
            if (!in_array($method, $route['methods'], true) && !in_array('ANY', $route['methods'], true)) {
                continue;
            }
            $params = $this->match($route['path'], $path);
            if ($params === null) {
                continue;
            }

            // CSRF for state changing requests
            if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && $route['csrf']) {
                $token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
                if (!hash_equals(csrf_token(), (string) $token)) {
                    if ($this->wantsJson()) {
                        $this->json(['message' => 'Sesi kedaluwarsa. Muat ulang halaman.'], 419);
                    }
                    flash('error', 'Sesi formulir kedaluwarsa. Silakan coba lagi.');
                    back('/');
                }
            }

            if ($route['auth'] && !Auth::check()) {
                if ($this->wantsJson()) {
                    $this->json(['message' => 'Unauthorized'], 401);
                }
                Session::set('intended', $path);
                redirect('/login');
            }

            if ($route['roles'] && !Auth::hasRole($route['roles'])) {
                http_response_code(403);
                View::render('errors/403', ['title' => 'Akses Ditolak'], 'layouts/app');
                return;
            }

            if ($route['params']) {
                $_GET = array_merge($route['params'], $_GET);
            }

            [$class, $action] = $route['handler'];
            $controller = new $class();
            $response = $controller->{$action}(...array_values($params));
            if (is_string($response)) {
                echo $response;
            }
            return;
        }

        http_response_code(404);
        View::render('errors/404', ['title' => 'Halaman Tidak Ditemukan'], Auth::check() ? 'layouts/app' : 'layouts/auth');
    }

    private function match(string $pattern, string $path): ?array
    {
        $regex = preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';
        if (!preg_match($regex, $path, $m)) {
            return null;
        }
        $params = [];
        foreach ($m as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }
        return $params;
    }

    private function wantsJson(): bool
    {
        return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')
            || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');
    }

    private function json(array $payload, int $status): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }

    public function path(string $name): string
    {
        return $this->named[$name] ?? '/';
    }

    public function has(string $name): bool
    {
        return isset($this->named[$name]);
    }

    public static function route(string $name, array $params = []): string
    {
        $path = self::instance()->named[$name] ?? '/';
        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', (string) $value, $path);
        }
        return url($path);
    }
}
