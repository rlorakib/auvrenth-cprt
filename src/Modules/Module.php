<?php
/**
 * Base class for Munafa modules.
 *
 * @package Munafa
 */

namespace Munafa\Modules;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * A feature the seller can turn on or off on the Modules tab of Munafa Settings.
 *
 * Its hooks are added only while it is on, and its settings are edited on its own
 * tab of the same screen. Add-ons add their modules with the `munafa_modules` filter.
 */
abstract class Module {

	/**
	 * Option names are this prefix plus the module ID.
	 */
	const OPTION_PREFIX = 'munafa_module_';

	/**
	 * Unique ID, lowercase letters, numbers, dashes and underscores (e.g. `checkout`).
	 */
	abstract public function id(): string;

	/**
	 * Name shown on the Modules tab.
	 */
	abstract public function name(): string;

	/**
	 * One sentence on what the module does.
	 */
	abstract public function description(): string;

	/**
	 * Add the module's hooks. Called on `plugins_loaded`, only while the module is on.
	 */
	abstract public function register(): void;

	/**
	 * Whether the module is on before the seller has chosen.
	 */
	public function default_enabled(): bool {
		return false;
	}

	/**
	 * Settings a new install starts with.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array();
	}

	/**
	 * Validate settings from the settings screen.
	 *
	 * @param array $input Settings in the shape of settings().
	 * @return array|WP_Error Clean settings, or the first problem found.
	 */
	public function sanitize( array $input ) {
		unset( $input );
		return array();
	}

	/**
	 * Extra data the settings tab needs besides the settings, such as lists to choose from.
	 *
	 * @return array<string, mixed>
	 */
	public function choices(): array {
		return array();
	}

	/**
	 * Saved settings, with defaults for anything not saved yet.
	 *
	 * @return array<string, mixed>
	 */
	final public function settings(): array {
		$saved = get_option( self::OPTION_PREFIX . $this->id(), array() );
		return array_merge( $this->defaults(), is_array( $saved ) ? $saved : array() );
	}

	/**
	 * Store settings that went through sanitize().
	 *
	 * @param array $settings Clean settings.
	 */
	final public function save_settings( array $settings ): void {
		update_option( self::OPTION_PREFIX . $this->id(), $settings, false );
	}
}
