<?php
declare(strict_types=1);

namespace App\Core;

/** Plain PHP templating: view file is captured, then wrapped by a layout. */
final class View
{
    private static array $shared = [];

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function render(string $template, array $data = [], ?string $layout = 'layouts/app'): void
    {
        $data = array_merge(self::$shared, $data);
        $content = self::capture($template, $data);

        if ($layout === null) {
            echo $content;
            return;
        }

        $data['content'] = $content;
        echo self::capture($layout, $data);
    }

    public static function partial(string $template, array $data = []): string
    {
        return self::capture($template, array_merge(self::$shared, $data));
    }

    public static function exists(string $template): bool
    {
        return is_file(APP_PATH . '/Views/' . str_replace('.', '/', $template) . '.php');
    }

    private static function capture(string $template, array $data): string
    {
        $file = APP_PATH . '/Views/' . str_replace('.', '/', $template) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('View tidak ditemukan: ' . $template);
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
