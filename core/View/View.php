<?php

declare(strict_types=1);

namespace Agus\View;

/**
 * Renders plain PHP templates from app/Views with layouts and sections.
 *
 * In a view:
 *   <?php $this->layout('layouts/main', ['title' => 'Home']) ?>
 *   <h1>Hello <?= e($name) ?></h1>
 *
 *   <?php $this->start('scripts') ?> <script>...</script> <?php $this->end() ?>
 *
 * In the layout:
 *   <?= $this->section('content') ?>   // the view's main output
 *   <?= $this->section('scripts') ?>
 */
class View
{
    private ?string $layout = null;
    private array $layoutData = [];
    private array $sections = [];
    private array $sectionStack = [];

    /** Values every view can use, e.g. the current user. */
    private static array $shared = [];

    public function __construct(private string $viewsPath)
    {
    }

    public static function render(string $view, array $data = []): string
    {
        return (new self(BASE_PATH . '/app/Views'))->make($view, $data);
    }

    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public function make(string $view, array $data = []): string
    {
        $content = $this->renderFile($view, array_merge(self::$shared, $data));

        // A view may pick a layout; layouts may themselves extend another layout.
        $first = true;
        while ($this->layout !== null) {
            $layout = $this->layout;
            $this->layout = null;
            if ($first) {
                $this->sections['content'] ??= $content;   // a view may also fill 'content' explicitly
                $first = false;
            } else {
                $this->sections['content'] = $content;     // nested layout: wrap the inner layout
            }
            $content = $this->renderFile($layout, array_merge(self::$shared, $data, $this->layoutData));
        }
        return $content;
    }

    private function renderFile(string $view, array $data): string
    {
        $file = $this->viewsPath . '/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View [$view] not found at $file");
        }

        $level = ob_get_level();
        ob_start();
        try {
            (function (string $__file, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            })->call($this, $file, $data);
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
        return (string) ob_get_clean();
    }

    public function layout(string $layout, array $data = []): void
    {
        $this->layout = $layout;
        $this->layoutData = $data;
    }

    public function start(string $name): void
    {
        $this->sectionStack[] = $name;
        ob_start();
    }

    public function end(): void
    {
        $name = array_pop($this->sectionStack);
        if ($name === null) {
            throw new \LogicException('end() called without start().');
        }
        $this->sections[$name] = ($this->sections[$name] ?? '') . ob_get_clean();
    }

    public function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    /** Includes a partial view: <?= $this->insert('partials/flash') ?> */
    public function insert(string $view, array $data = []): string
    {
        return (new self($this->viewsPath))->make($view, array_merge(self::$shared, $data));
    }
}
