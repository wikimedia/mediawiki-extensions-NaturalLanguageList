<?php
class NaturalLanguageList {

	/**
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook(
			'list',
			[ __CLASS__, 'render' ],
			Parser::SFH_OBJECT_ARGS
		);
		$parser->setFunctionHook(
			'rawlist',
			[ __CLASS__, 'renderRaw' ],
			Parser::SFH_OBJECT_ARGS
		);
		$parser->setFunctionHook(
			'rangelist',
			[ __CLASS__, 'renderRange' ],
			Parser::SFH_OBJECT_ARGS
		);
	}

	/**
	 * Render {{#list:}}
	 *
	 * @param Parser $parser
	 * @param PPFrame_DOM $frame
	 * @param array $args
	 * @return string Parsed wikitext
	 */
	public static function render( $parser, $frame, $args ) {
		if ( count( $args ) == 0 ) {
			return '';
		}
		$obj = new self( $parser, $frame, $args );
		$obj->readOptions( false );
		$obj->readArgs();

		return $obj->outputList();
	}

	/**
	 * Render {{#rawlist:}}
	 *
	 * @param Parser $parser
	 * @param PPFrame_DOM $frame
	 * @param array $args
	 * @return string Parsed wikitext
	 */
	public static function renderRaw( $parser, $frame, $args ) {
		if ( count( $args ) == 0 ) {
			return '';
		}
		$obj = new self( $parser, $frame, $args );
		# get separator between data
		$separator = $obj->mArgs[0];
		$obj->readOptions( true, $separator );
		$obj->readArgs( $separator );

		return $obj->outputList();
	}

	/**
	 * Render {{#rangelist:}}
	 *
	 * @param Parser $parser
	 * @param PPFrame_DOM $frame
	 * @param array $args
	 * @return string Parsed wikitext
	 */
	public static function renderRange( $parser, $frame, $args ) {
		if ( count( $args ) == 0 ) {
			return '';
		}
		$obj = new self( $parser, $frame, $args );
		$obj->readOptions( false );
		$obj->readRange();

		return $obj->outputList();
	}

	private $mParser;
	private $mFrame;
	public $mArgs;
	private $mSeparator = null;
	private $mOptions = [
		'fieldsperitem' => -1,     # size of pairs
		'duplicates' => true,      # allow same elements to appear
		'blanks' => false,         # allow blank elements to appear
		'length' => -1,            # length, default no limit
		'itemoutput' => null,      # the format for each element
		'outputseparator' => null, # the separator between output elements
		'lastseparator' => null,   # the separator between the last two elements
	];
	private $mReaditems = [];
	public $mParams = [];
	private $mIgnores = [];

	/**
	 * Constructor
	 * @param Parser &$parser
	 * @param PPFrame_DOM &$frame
	 * @param array &$args
	 */
	public function __construct( &$parser, &$frame, &$args ) {
		$this->mParser = $parser;
		$this->mFrame = $frame;
		$this->mArgs = $args;
	}

	/**
	 * Return $this->mParams formatted as a list according to $this->mOptions
	 *
	 * @return string
	 */
	private function outputList() {
		# Convert each item from an array into a string according to the format.
		$items = array_map( [ $this, 'formatOutputItem' ], $this->mParams );

		# If there are no items, there is nothing
		if ( count( $items ) === 0 ) {
			return '';
		}

		# If there's only one item, there are no separators
		if ( count( $items ) === 1 ) {
			return $items[0];
		}

		# Otherwise remove the last from the list so that we can implode() the remainder
		$last = array_pop( $items );

		return implode( $this->mOptions['outputseparator'], $items ) . $this->mOptions['lastseparator'] . $last;
	}

	/**
	 * Format the input pairs that make up each output item using the given format
	 *
	 * @param array|string $pair
	 * @return string formatted output
	 */
	private function formatOutputItem( $pair ) {
		return wfMsgReplaceArgs( $this->mOptions['itemoutput'], $pair );
	}

	/**
	 * Create $this->mParams from $this->mReaditems using $this->mOptions,
	 * but treating it as a range.
	 */
	private function readRange() {
		# now this system follows a basic syntax:
		# Element 1 is the start
		# Element 2 is the end
		# Element 3 is the optional step, if not set, guess either 1 or -1
		#   depending on the difference between start and end.
		# If they are the same (start and end) just assume no range.

		if ( !isset( $this->mReaditems[0] ) || !is_numeric( $this->mReaditems[0] ) ) {
			return; # bail if the syntax is not upheld
		}
		$start = $this->mReaditems[0];
		if ( !isset( $this->mReaditems[1] ) || !is_numeric( $this->mReaditems[1] ) ) {
			return; # bail if the syntax is not upheld
		}
		$end = $this->mReaditems[1];
		if ( isset( $this->mReaditems[2] ) && is_numeric( $this->mReaditems[2] ) ) {
			$step = $this->mReaditems[2];
			if ( $start < $end && $step < 0 ) { # a postive list with a
				$step = 1;                    # negative step; overwite
			} elseif ( $start > $end && $step > 0 ) { # the reverse!  Overwite
				$step = -1;
			}
			# otherwise fine
		} elseif ( $start > $end ) {
			# negative range
			$step = -1;
		} elseif ( $start < $end ) {
			# positive range
			$step = 1;
		} else {
			# no range, 0 indicates just to bail
			$step = 0;
		}

		$items = []; # array of args to include
		if ( $step != 0 ) {
			# eh, might could be done prettier, I guess.
			if ( $step < 0 ) {
				for ( $i = $start; $i >= $end; $i += $step ) {
					if ( !self::parseArrayItem( $items, $i ) ) {
						break;
					}
				}
			} else {
				for ( $i = $start; $i <= $end; $i += $step ) {
					if ( !self::parseArrayItem( $items, $i ) ) {
						break;
					}
				}
			}
		} else {
			$items = [ $start ];
		}

		$this->treatParams( $items );
	}

	/**
	 * Create $this->mParams from $this->mReaditems using $this->mOptions.
	 *
	 * @param string|null $separator [default:null] Input separator (e.g. ',')
	 */
	private function readArgs( $separator = null ) {
		$items = []; # array of args to include

		# strip read items of duplicate elements if not permitted
		$args = $this->mOptions['duplicates']
			? $this->mReaditems
			: array_unique( $this->mReaditems );

		foreach ( $args as $arg ) {
			if ( !$this->mOptions['blanks'] && $arg === '' ) {
				continue;
			}
			if ( !self::parseArrayItem( $items, $arg, $separator ) ) {
				break;
			}
		}

		# Remove the ignored elements from the array
		$items = array_diff( $items, $this->mIgnores );

		$this->treatParams( $items );
	}

	/**
	 * Treat certain features in $this->mParams after it has been created,
	 * as a help function for not to rewrite the same things in two functions.
	 *
	 * @param array $items The items
	 */
	private function treatParams( $items ) {
		global $wgNllMaxListLength;

		# Split the array into smaller arrays, one for each output item.
		$this->mParams = array_chunk( $items, $this->mOptions['fieldsperitem'] );

		# Disgard any leftovers, hrm...
		if ( $this->mParams !== [] && count( end( $this->mParams ) ) != $this->mOptions['fieldsperitem'] ) {
			array_pop( $this->mParams );
		}

		# Remove anything over the set length, if set
		if ( $this->mOptions['length'] != -1
			&& count( $this->mParams ) > $this->mOptions['length'] ) {
			$this->mParams = array_slice( $this->mParams, 0, $this->mOptions['length'] );
		}

		# Remove anything over the allowed limit
		$this->mParams = array_slice( $this->mParams, 0, $wgNllMaxListLength );
	}

	/**
	 * Create $this->mOptions and $this->mReaditems from $this->mArgs using $this->mFrame.
	 *
	 * @param bool $ignorefirst Ignore first element in case of {{#rawlist:}}
	 * @param string|null $separator [default:null] Input separator
	 */
	private function readOptions( $ignorefirst, $separator = null ) {
		global $wgNllMaxListLength;
		$args = $this->mArgs;

		# an array of items not options
		$this->mReaditems = [];

		# first input is a bit different than the rest,
		# so we'll treat that differently
		$primary = trim( $this->mFrame->expand( array_shift( $args ) ) );
		if ( !$ignorefirst ) {
			$primary = $this->handleInputItem( $primary );
			if ( $primary !== false ) {
				$this->mReaditems[] = $primary;
			}
		}
		# check the rest for options
		foreach ( $args as $arg ) {
			$item = $this->handleInputItem( $arg, $separator );
			if ( $item !== false ) {
				$this->mReaditems[] = $item;
			}
		}

		# if fieldsperitem is not set it should be 1, unless itemoutput contains
		# $2 or higher. Do we actually want to continue beyond 9? --conrad
		if ( $this->mOptions['fieldsperitem'] == -1 ) {
			$this->maxDollar = 1;
			if ( $this->mOptions['itemoutput'] !== null ) {
				# set $this->maxDollar to the maxmimum found
				preg_replace_callback( '/\$([1-9][0-9]*)/',
					[ $this, 'callbackMaxDollar' ],
					$this->mOptions['itemoutput'] );
			}
			$this->mOptions['fieldsperitem'] = $this->maxDollar;
		}

		# get default values for lastseparator from outputseparator (if set) or message
		if ( $this->mOptions['outputseparator'] === null ) {

			$this->mOptions['outputseparator'] = wfMessage( 'nll-separator' )->plain();

			if ( $this->mOptions['lastseparator'] === null ) {
				$this->mOptions['lastseparator'] = wfMessage( 'nll-lastseparator' )->plain();
			}
		# set the last separator to the regular separator if the separator is
		# set and the last separator isn't set specifically
		} elseif ( $this->mOptions['lastseparator'] === null ) {
			$this->mOptions['lastseparator'] = $this->mOptions['outputseparator'];
		}

		# use the default format if format not set
		if ( $this->mOptions['itemoutput'] === null ) {
			$this->mOptions['itemoutput'] = wfMessage( 'nll-itemoutput' )->plain();
		}

		# don't permit a length larger than the allowed
		if ( $this->mOptions['length'] != -1
			&& $this->mOptions['length'] > $wgNllMaxListLength ) {
			$this->mOptions['length'] = -1;
		}
	}

	/**
	 * Find the highest $n in a string
	 *
	 * @param array $m (object, number)
	 * @return stdClass
	 */
	private function callbackMaxDollar( $m ) {
		$this->maxDollar = max( $this->maxDollar, $m[1] );
		return $m[0];
	}

	/**
	 * This functions handles individual items found in the arguments,
	 * and decides whether it is an option or not.
	 * If it is, then it handles the option (and applies it).
	 * If it isn't, then it just returns the string it found.
	 *
	 * @param string $arg Argument
	 * @param string|null $separator Input separator
	 * @return string if element, else return false
	 */
	private function handleInputItem( $arg, $separator = null ) {
		if ( $arg instanceof PPNode_DOM ) {
			$bits = $arg->splitArg();
			$index = $bits['index'];
			if ( $index === '' ) { # Found
				$var = trim( $this->mFrame->expand( $bits['name'] ) );
				$value = trim( $this->mFrame->expand( $bits['value'] ) );
			} else { # Not found
				return trim( $this->mFrame->expand( $arg ) );
			}
		} else {
			$parts = array_map( 'trim', explode( '=', $arg, 2 ) );
			if ( count( $parts ) == 2 ) { # Found "="
				$var = $parts[0];
				$value = $parts[1];
			} else { # Not found
				return $arg;
			}
		}
		# Still here?  Then it must be an option
		switch ( $name = self::parseOptionName( $var ) ) {
			case 'duplicates':
			case 'blanks':
				$this->mOptions[$name] = self::parseBoolean( $value );
				break;
			case 'outputseparator':
			case 'lastseparator':
			case 'itemoutput':
				$this->mOptions[$name] = self::parseString( $value );
				break;
			case 'fieldsperitem':
			case 'length':
				$this->mOptions[$name] = self::parseNumeral( $value );
				break;
			case 'ignore':
				self::parseArrayItem( $this->mIgnores, $value, $separator, true );
				break;
			case 'data':
				# just strip the parameter and make the $arg
				# the value, let the following case handle its
				# output.
				$arg = $value;
			default:
				# Wasn't an option after all
				return $arg instanceof PPNode_DOM
					? trim( $this->mFrame->expand( $arg ) )
					: $arg;
		}
		return false;
	}

	/**
	 * Using magic to store all known names for each option
	 *
	 * @param string $value
	 * @return The option found; otherwise false
	 */
	private static function parseOptionName( $value ) {
		static $magicWords = null;
		if ( $magicWords === null ) {
			$magicWords = new MagicWordArray( [
				'nll_blanks', 'nll_duplicates',
				'nll_fieldsperitem', 'nll_itemoutput',
				'nll_lastseparator', 'nll_outputseparator',
				'nll_ignore', 'nll_data', 'nll_length',
			] );
		}

		$name = $magicWords->matchStartToEnd( trim( $value ) );
		if ( $name ) {
			return str_replace( 'nll_', '', $name );
		}

		# blimey, so not an option!?
		return false;
	}

	/**
	 * Insert a new element into an array.
	 *
	 * @param array &$array The array in question
	 * @param mixed $value The element to be inserted
	 * @param string|null $separator Input separator
	 * @param bool|false $ignorelength Whether to ignore the limit
	 *                                 on the length. Be careful!
	 * @return bool True on success, false on failure.
	 */
	private static function parseArrayItem( &$array, $value, $separator = null, $ignorelength = false ) {
		global $wgNllMaxListLength;
		# if the maximum length has been reached; don't bother.
		if ( count( $array ) > $wgNllMaxListLength && !$ignorelength ) {
			return false;
		}
		# if no separator, just assume the value can be appended,
		# simple as that
		if ( $separator === null ) {
			$array[] = (string)$value;
		} else {
			# else, let's break the value up and append
			# each 'subvalue' to the array.
			$tmp = explode( $separator, $value );
			foreach ( $tmp as $v ) {
				$array[] = (string)$v;
				if ( count( $array ) > $wgNllMaxListLength ) {
					break;
				}
			}
		}
		return true;
	}

	/**
	 * Parse numeral
	 *
	 * @param int $value
	 * @param int $default [default:1]
	 * @return int The integer if integer and above 0, otherwise $default
	 */
	private static function parseNumeral( $value, $default = 1 ) {
		if ( is_numeric( $value ) && $value > 0 ) {
			return floor( $value ); # only integers
		}
		return $default;
	}

	/**
	 * Parse string
	 *
	 * @param string $value
	 * @param string|null $default
	 * @return string If none found, return $default
	 */
	private static function parseString( $value, $default = null ) {
		if ( $value !== '' ) {
			return $value;
		}
		return $default;
	}

	/**
	 * Parse boolean
	 *
	 * @param string $value
	 * @return true if truth value found; otherwise false
	 */
	private static function parseBoolean( $value ) {
		return in_array( $value, [ 1, true, '1', 'true' ], true );
	}
}
