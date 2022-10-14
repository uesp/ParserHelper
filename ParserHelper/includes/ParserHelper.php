<?php

/**
 * Provides a number of library routines, mostly related to the parser along with a few generic global methods.
 */
abstract class ParserHelper
{
	const AV_ANY = 'parserhelper-any';
	const AV_ALWAYS = 'parserhelper-always';

	const NA_ALLOWEMPTY = 'parserhelper-allowempty';
	const NA_CASE = 'parserhelper-case';
	const NA_DEBUG = 'parserhelper-debug';
	const NA_IF = 'parserhelper-if';
	const NA_IFNOT = 'parserhelper-ifnot';
	const NA_SEPARATOR = 'parserhelper-separator';

	/**
	 * Instance variable for singleton.
	 *
	 * @var ParserHelper
	 *
	 * 	/**
	 * Cache for localized magic words.
	 *
	 * @var MagicWordArray
	 *
	 */
	private static $instance;

	/**
	 * Cache for localized magic words.
	 *
	 * @var MagicWordArray
	 *
	 */
	private $mwArray;

	/**
	 * Singleton instance.
	 *
	 * @return ParserHelper
	 *
	 */
	public static function getInstance(): ParserHelper
	{
		if (!self::$instance) {
			$useNew = defined('MW_VERSION') && version_compare(constant('MW_VERSION'), '1.35', '>=');
			if ($useNew) {
				require_once(__DIR__ . '/ParserHelper35.php');
				self::$instance = new ParserHelper35();
			} else {
				require_once(__DIR__ . '/ParserHelper28.php');
				self::$instance = new ParserHelper28();
			}

			self::$instance->init();
		}

		return self::$instance;
	}

	/**
	 * Caches magic words in a static MagicWordArray. This should include any named arguments or argument values that
	 * need to be localized, along with any other magic words not already registered with the parser by other means,
	 * such as parser functions, tags, and so forth.
	 *
	 * @param array $magicWords The magic words to cache.
	 *
	 * @return void
	 *
	 */
	public function cacheMagicWords(array $magicWords): void
	{
		// TODO: Move most of the calls to this to something more appropriate. Magic Words should be unique and not
		// appropriated for values. This should be straight-up localization and nothing more. Check how image options
		// work, as these are likely to be similar.
		if (!isset($this->mwArray)) {
			// RHshow($magicWords);
			$this->mwArray = new MagicWordArray($magicWords);
		} else {
			$this->mwArray->addArray($magicWords);
		}
	}

	/**
	 * Checks the `case` parameter to see if matches `case=any` or any of the localized equivalents.
	 *
	 * @param array $magicArgs The list of arguments to search.
	 *
	 * @return boolean True if `case=any` or any localized equivalent was found in the argument list.
	 */
	public function checkAnyCase(array $magicArgs): bool
	{
		return $this->magicKeyEqualsValue($magicArgs, self::NA_CASE, self::AV_ANY);
	}

	/**
	 * Checks the debug argument to see if it's boolean or 'always'. This variant of checkDebug expects the keys to be
	 * magic word values rather than magic word IDs.
	 *
	 * @param Parser $parser The parser in use.
	 * @param array|null $magicArgs The magic word arguments as created by getMagicArgs().
	 *
	 * @return boolean
	 *
	 */
	public function checkDebugMagic(Parser $parser, PPFrame $frame, array $magicArgs): bool
	{
		$debug = $frame->expand($magicArgs[self::NA_DEBUG] ?? false);
		// RHshow('Debug param: ', boolval($debug) ? 'Yes' : 'No', "\nIs preview: ", $parser->getOptions()->getIsPreview(), "\nDebug word: ", $this->getMagicWord(self::AV_ALWAYS)->matchStartToEnd($debug));
		return $parser->getOptions()->getIsPreview()
			? boolval($debug)
			: $this->getMagicWord(self::AV_ALWAYS)->matchStartToEnd($debug);
	}

	/**
	 * Checks whether both the `if=` and `ifnot=` conditions have been satisfied.
	 *
	 * @param array $magicArgs The magic word array containing the arguments.
	 *
	 * @return boolean True if both conditions (if applicable) have been satisfied; otherwise, false.
	 */
	public function checkIfs(PPFrame $frame, array $magicArgs): bool
	{
		return
			$frame->expand($magicArgs[self::NA_IF] ?? true) &&
			$frame->expand($magicArgs[self::NA_IFNOT] ?? false);
	}

	/**
	 * Expands an entire array of values using the MediaWiki pre-processor.
	 * This is useful when parsing arguments to parser functions.
	 *
	 * @param PPFrame $frame The expansion frame to use.
	 * @param array $values The values to expand.
	 * @param bool $trim Whether the results should be trimmed prior to being returned.
	 * @return array
	 */
	public function expandArray(PPFrame $frame, array $values, bool $trim = false): array
	{
		$retval = [];
		if ($trim) {
			foreach ($values as $value) {
				$retval[] = trim($frame->expand($value));
			}
		} else {
			foreach ($values as $value) {
				$retval[] = $frame->expand($value);
			}
		}

		return $retval;
	}

	/**
	 * Finds the magic word ID that corresponds to the value provided.
	 *
	 * @param string $value The value to look up.
	 * @param ?string $default The value to use if $value is not found.
	 *
	 * @return [type]
	 *
	 */
	public function findMagicID($value, ?string $default = null): ?string
	{
		$match = $this->mwArray->matchStartToEnd($value);
		return $match === false ? $default : $match;
	}

	/**
	 * Standardizes debug text formatting for parser functions.
	 *
	 * @param string $output The original text being output.
	 * @param bool $debug Whether to return debug or regular text.
	 * @param bool $noparse If this falls through to regular output, whether or not to parse that output.
	 *
	 * @return array The modified text.
	 *
	 */
	public function formatPFForDebug(string $output, bool $debug = false, bool $noparse = false): array
	{
		if (strlen($output) == 0) {
			return '';
		}

		return $debug
			? ['<pre>' . htmlspecialchars($output) . '</pre>', 'noparse' => false]
			: [$output, 'noparse' => $noparse];
	}

	/**
	 * Standardizes debug text formatting for tags.
	 *
	 * @param string $output The original text being output.
	 * @param bool $debug Whether to return debug or regular text.
	 * @param bool $noparse If this falls through to regular output, whether or not to parse that output.
	 *
	 * @return string The modified text.
	 *
	 */
	public function formatTagForDebug(string $output, bool $debug = false, bool $noparse = false): array
	{
		// It ended up that for both the cases of this so far, we needed to process the debug value before getting
		// here, so I made the debug check a simple boolean.
		return $debug
			? ['<pre>' . htmlspecialchars($output) . '</pre>', 'markerType' => 'nowiki', 'noparse' => $noparse]
			: [$output, 'markerType' => 'none', 'noparse' => $noparse];
	}

	/**
	 * Returns an associative array of the named arguments that are allowed for a magic word or parser function along
	 * with their values. The function checks all localized variants for a named argument and returns their associated
	 * values under a single unified key.
	 *
	 * @param PPFrame $frame The expansion frame to use.
	 * @param array $args The arguments to search.
	 * @param mixed ...$allowedArgs A list of arguments that should be expanded and returned in the `$magic` portion of
	 * the returned array. All other arguments will be returned in the `$values` portion of the returned array.
	 *
	 * @return array The return value consists of three arrays:
	 * - The first array contains the list of any named arguments from $args where the key appears in $allowedArgs. All
	 *   keys and values in this array will have been expanded before being returned.
	 * - The second array will contain any arguments that are anonymous or were not specified in $allowedArgs.
	 * - The final array will contain any duplicate keys from $allowedArgs. This allows custom handling of duplicates,
	 *   such as is needed by #splitargs in Riven.
	 *
	 */
	public function getMagicArgs(PPFrame $frame, array $args = [], string ...$allowedArgs): array
	{
		if (!count($args) || !count($allowedArgs)) {
			return [[], $args, []];
		}

		$magic = [];
		$values = [];
		$dupes = [];
		$allowedArray = new MagicWordArray($allowedArgs);

		// TODO: Should be doable in a forwards direction, with duplicates only added if a key already exists.
		// However, this changes a guaranteed array_reverse (and possible a second at the end) for having to check
		// isset() for every parameter. Might want to time this and see which performs better.
		$args = array_reverse($args); // Make sure last value gets processed and any others go to $dupes.
		foreach ($args as $arg) {
			list($name, $value) = $this->getKeyValue($frame, $arg);
			if (is_null($name)) {
				// RHDebug::show('Add anon: ', $frame->expand($value));
				$values[] = $value;
			} else {
				$name = trim($name);
				$magKey = $allowedArray->matchStartToEnd($name);
				if ($magKey) {
					if (isset($magic[$magKey])) {
						// If a key already exists and is one of the allowed keys, add it to the dupes list. This
						// allows for the possibility of merging the duplicates back into values later on for cases
						// like #splitargs where it may be desirable to have keys for the called template
						// (e.g., separator) that overlap with those of the template.
						if (!isset($dupes[$name])) {
							// TODO: change this to `$dupes[$name][] = $value` to allow for multiple duplicates.
							$dupes[$name] = $value;
						}
					} else {
						$magic[$magKey] = trim($frame->expand($value));
					}
				} else {
					// RHDebug::show('Add fake k=v: ', $frame->expand($arg));
					$values[] = $arg;
				}
			}
		}

		$values = array_reverse($values);
		return [$magic, $values, $dupes];
	}

	/**
	 * Parse separator string for C-like character entities and surrounding quotes.
	 *
	 * @param array $magicArgs
	 *
	 * @return string The parsed string.
	 *
	 */
	public function getSeparator(array $magicArgs): string
	{
		$separator = $magicArgs[self::NA_SEPARATOR] ?? '';
		if (strlen($separator) > 1) {
			$separator = stripcslashes($separator);
			$first = $separator[0];
			if (in_array($first, ['\'', '`', '"']) && $first === substr($separator, -1, 1)) {
				return substr($separator, 1, -1);
			}
		}

		return $separator;
	}

	/**
	 * Initializes ParserHelper, caching all required magic words.
	 *
	 * @return void
	 */
	public function init(): void
	{
		require_once(__DIR__ . '/RHDebug.php');
		$this->cacheMagicWords([
			self::NA_ALLOWEMPTY,
			self::NA_CASE,
			self::NA_DEBUG,
			self::NA_IF,
			self::NA_IFNOT,
			self::NA_SEPARATOR,
		]);
	}

	/**
	 * Determines if the word at a specific key matches a certain value after everything's converted to their
	 * respective IDs.
	 *
	 * @param array $magicArguments The arguments the key can be found in.
	 * @param string $key The key to search for.
	 * @param string $value The value to match with.
	 *
	 * @return bool True if the value at the specifc key was the same as the value specified.
	 *
	 */
	public function magicKeyEqualsValue(array $magicArguments, string $key, string $value): bool
	{
		$arrayValue = $magicArguments[$key];
		return
			!is_null($arrayValue) &&
			$this->getMagicWord($value)->matchStartToEnd($arrayValue);
	}

	/**
	 * Calls setHook() for all synonyms of a tag.
	 *
	 * @param Parser $parser The parser to register the tag names with.
	 * @param string $id The magic word ID whose synonyms should be registered.
	 * @param callable $callback The function to call when the tag is used.
	 *
	 * @return void
	 *
	 */
	public function setHookSynonyms(Parser $parser, string $id, callable $callback)
	{
		foreach ($this->getMagicWord($id)->getSynonyms() as $synonym) {
			$parser->setHook($synonym, $callback);
		}
	}

	/**
	 * Transforms tag arguments so that only wanted elements are present and are represented by their qqq key rather
	 * than the language-specific word.
	 *
	 * @param array $args The arguments to filter.
	 *
	 * @return array The filtered array.
	 *
	 */
	public function transformArgs(array $args): array
	{
		$retval = [];
		foreach ($args as $key => $value) {
			$match = $this->mwArray->matchStartToEnd($key);
			if ($match) {
				$retval[$match] = $value;
			}
		}

		return $retval;
	}

	/**
	 * Returns a string or part node split into a key/value pair, with the key expanded, if necessary, into a string.
	 * The return value is always an array. If the argument is of the wrong type, or isn't a key/value pair, the key
	 * will be returned as null and the value will be the original argument.
	 *
	 * @param PPFrame $frame The frame in use.
	 * @param string|PPNode_Hash_Tree $arg The argument to work on.
	 *
	 * @return array
	 */
	public abstract function getKeyValue(PPFrame $frame, mixed $arg): array;

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
	 * @param Parser $parser THe parser in use.
	 *
	 * @return StripState
	 *
	 */
	public abstract function getStripState(Parser $parser): StripState;

	/**
	 * Calls $parser->replaceLinkHoldersText(), bypassing the private access modifier if needed.
	 *
	 * @param Parser $parser The parser in use;
	 * @param mixed $output The output text to replace in.
	 *
	 * @return stroing
	 *
	 */
	public abstract function replaceLinkHoldersText(Parser $parser, string $output): string;
}
