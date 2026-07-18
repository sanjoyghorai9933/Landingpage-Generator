<?php
require_once __DIR__ . '/ThemeRegistry.php';

function loadTheme(string $requestedThemeId = ''): array {
    $themes = themeRegistry();
    $themeId = $requestedThemeId !== '' ? $requestedThemeId : getDefaultThemeId();

    if (!isset($themes[$themeId])) {
        $themeId = getDefaultThemeId();
    }

    $theme = $themes[$themeId];
    if (!is_callable($theme['renderer'] ?? null)) {
        fail('Selected theme is not renderable.', 500);
    }

    return $theme;
}
