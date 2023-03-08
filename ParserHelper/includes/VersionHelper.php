<?php

/**
 * Provides version-specific methods for those calls that differ substantially across versions.
 */
abstract class VersionHelper
{
	/**
	 * Instance variable for singleton.
	 *
	 * @var VersionHelper
	 */
	private static $instance;

	/**
	 * Gets the singleton instance for this class.
	 *
	 * @return VersionHelper The singleton instance.
	 *
	 */
	public static function getInstance(): VersionHelper
	{
		if (!self::$instance) {
			$version = constant('MW_VERSION');
			if (version_compare($version, '1.28', '>=')) {
				require_once(__DIR__ . '/VersionHelper28.php');
				self::$instance = new VersionHelper28();
			} elseif (version_compare($version, '1.35', '>=')) {
				require_once(__DIR__ . '/VersionHelper35.php');
				self::$instance = new VersionHelper35();
			} else {
				throw 'MediaWiki version could not be found or is too low.';
			}
		}

		return self::$instance;
	}

	/**
	 * Gets the magic word for the specified id.
	 *
	 * @param string $id The id of the magic word to get.
	 *
	 * @return MagicWord The magic word or null if not found.
	 *
	 */
	public abstract function getMagicWord(string $id): MagicWord;

	/**
	 * Retrieves the parser's strip state object.
	 *
	 * @param Parser $parser The parser in use.
	 *
	 * @return StripState
	 *
	 */
	public abstract function getStripState(Parser $parser): StripState;

	/**
	 * Calls $parser->replaceLinkHoldersText(), bypassing the private access modifier if needed.
	 *
	 * @param Parser $parser The parser in use.
	 * @param mixed $output The output text to replace in.
	 *
	 * @return string
	 *
	 */
	public abstract function replaceLinkHoldersText(Parser $parser, string $output): string;
}
