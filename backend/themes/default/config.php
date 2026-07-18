<?php
require_once __DIR__ . '/renderer.php';

return [
    'id' => 'default',
    'name' => 'Default Theme',
    'paths' => [
        'indexTemplate' => __DIR__ . '/templates/index-template.html',
    ],
    'schema' => require __DIR__ . '/schemas/schema.php',
    'styles' => require __DIR__ . '/styles/styles.php',
    'renderer' => 'renderDefaultThemeTokens',
];
