<?php
require_once __DIR__ . '/ThemeInterface.php';

function themeRegistry(): array {
    static $themes = null;
    if ($themes !== null) return $themes;

    $defaultTheme = require __DIR__ . '/../themes/default/config.php';
    $themes = [
        $defaultTheme['id'] => $defaultTheme,
    ];

    return $themes;
}

function getDefaultThemeId(): string {
    return 'default';
}
