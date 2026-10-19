<?php

declare(strict_types=1);

namespace App\View;

use Symfony\Component\HttpFoundation\Response;

/** Symfony view service: keeps the original HTML while controllers own behavior. */
final class PhpTemplateRenderer
{
    public function __construct(
        private readonly ViewContext $context,
        private readonly string $projectDir,
    ) {
    }

    public function render(string $template, array $variables = [], int $status = 200): Response
    {
        if (!preg_match('~^[a-zA-Z0-9_/-]+$~', $template) || str_contains($template, '..')) {
            throw new \InvalidArgumentException('Invalid template name.');
        }
        $file = $this->projectDir.'/templates/'.$template.'.php';
        if (!is_file($file)) {
            throw new \RuntimeException('Missing template: '.$template);
        }

        $level = ob_get_level();
        ob_start();
        try {
            (static function (string $file, array $variables, ViewContext $view): void {
                extract($variables, EXTR_SKIP);
                require $file;
            })($file, $variables, $this->context);
            $html = ob_get_clean();
        } catch (\Throwable $error) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $error;
        }

        return new Response($html, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
