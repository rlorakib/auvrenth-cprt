<?php
/**
 * PHPUnit bootstrap for unit tests that don't load WordPress.
 *
 * @package Munafa
 */

define( 'ABSPATH', __DIR__ . '/' );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
