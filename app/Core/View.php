<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $view, array $data = [], string $layout = 'layouts/app'): string
    {
        $viewFile = app_path('app/Views/' . $view . '.php');
        if (!is_file($viewFile)) throw new \RuntimeException("View not found: {$view}");
        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // Views often use generic local variable names such as $title while rendering
        // cards/lists. Restore the original controller data before loading the layout
        // so page metadata always uses the intended page-level values.
        extract($data, EXTR_OVERWRITE);

        $layoutFile = app_path('app/Views/' . $layout . '.php');
        ob_start();
        require $layoutFile;
        return ob_get_clean();
    }

    public static function partial(string $view, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require app_path('app/Views/' . $view . '.php');
        return ob_get_clean();
    }
}
