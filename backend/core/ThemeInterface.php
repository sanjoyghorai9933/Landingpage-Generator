<?php
/**
 * ThemeInterface documents the contract every landing-page theme must expose.
 *
 * A theme is a plain PHP configuration array so it remains compatible with the
 * shared-hosting PHP versions supported by this project.
 * Required keys:
 * - id: unique machine-readable theme id.
 * - name: human-readable theme name.
 * - paths: absolute paths to theme template files.
 * - schema: field/default definitions consumed by the core generator.
 * - styles: style defaults consumed by the core generator.
 * - renderer: callable that receives the prepared generation context and returns template tokens.
 */
