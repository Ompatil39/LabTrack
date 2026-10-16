<?php

declare(strict_types=1);

namespace LabTrack\View;

use RuntimeException;

class View
{
    private static string $templatesPath = '';

    public static function getTemplatesPath(): string
    {
        if (self::$templatesPath === '') {
            self::$templatesPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'templates';
        }
        return self::$templatesPath;
    }

    public static function setTemplatesPath(string $path): void
    {
        self::$templatesPath = rtrim($path, '/\\');
    }

    public static function render(string $template, array $data = [], ?string $layout = 'layout/base'): string
    {
        $baseDir = self::getTemplatesPath();
        $templateFile = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $template) . '.php';

        if (!file_exists($templateFile)) {
            throw new RuntimeException("Template not found: {$templateFile}");
        }

        $content = self::renderPhpFile($templateFile, $data);

        if ($layout === null || $layout === '') {
            return $content;
        }

        $layoutFile = $baseDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $layout) . '.php';
        if (!file_exists($layoutFile)) {
            throw new RuntimeException("Layout not found: {$layoutFile}");
        }

        $layoutData = array_merge($data, ['content' => $content]);
        return self::renderPhpFile($layoutFile, $layoutData);
    }

    private static function renderPhpFile(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }

    public static function csrf(): string
    {
        if (function_exists('csrfInputField')) {
            return csrfInputField();
        }
        $token = $_SESSION['csrf_token'] ?? '';
        return '<input type="hidden" name="csrf_token" value="' . self::e($token) . '">';
    }
}
