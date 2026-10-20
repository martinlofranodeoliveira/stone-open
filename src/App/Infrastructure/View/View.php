<?php
declare(strict_types=1);

namespace App\Infrastructure\View;

final class View
{
    public static function render(string $template, array $data = []): string
    {
        $path = __DIR__ . '/../../../views/' . $template . '.php';
        if (!is_file($path)) {
            return '<h1>Template not found</h1>';
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $path;
        return (string) ob_get_clean();
    }
}

