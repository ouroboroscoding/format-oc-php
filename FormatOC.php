<?php
/**
 * FormatOC
 *
 * Contains several classes used to represent documents and their nodes
 *
 * @author Chris Nasr
 * @copyright OurobotosCoding
 * @version 1.5.3
 * @created 2016-02-20
 */

namespace FormatOC;

define("_SPECIAL_SYNTAX", '[a-z0-9_-]+');

/**
 * Because having to declare global everywhere is bullshit
 */
abstract class _Types {

	/**#@+
	 * Special
	 *
	 * Holds regexes to match special hash elements
	 */
	public static $special = array(
		"name" => '/^' . _SPECIAL_SYNTAX . '$/',
		"key" => '/^__(' . _SPECIAL_SYNTAX . ')__$/',
		"reserved" => array(
			'__array__', '__hash__', '__maximum__', '__minimum__', '__name__',
			'__options__', '__regex__', '__require__', '__type__'
		)
	);

	/**#@-*/

	/**
	 * Standard
	 *
	 * Holds a regex to match any standard named fields. These are limited in order
	 * to ease the ability to plugin additional data stores
	 */
	public static $standard = '/^_?[a-zA-Z0-9][a-zA-Z0-9_]*$/';

	/**
	 * Type to Regex
	 *
	 * Halds a hash of type values to the regular expression used to validate them
	 */
	public static $regex = array(
		'base64'	=> '/^(?:[A-Za-z0-9+\/]{4})+(?:[A-Za-z0-9+\/]{2}==|[A-Za-z0-9+\/]{3}=)?$/',
		'date'		=> '/^\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\d|3[01])$/',
		'datetime'	=> '/^\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\d|3[01]) (?:[01]\d|2[0-3])(?::[0-5]\d){2}$/',
		'decimal'	=> '/^-?(?:[1-9]\d+|\d)(?:\.\d+)?$/',
		'int'		=> '/^(?:0|[+-]?[1-9]\d*|0x[0-9a-f]+|0[0-7]+)$/',
		'ip'		=> '/^(?:25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9][0-9]|[1-9])(?:\.(?:25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])){2}\.(?:25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9][0-9]|[1-9])$/',
		'md5'		=> '/^[a-fA-F0-9]{32}$/',
		'price'		=> '/^-?(?:[1-9]\d+|\d)(?:\.(\d{1,2}))?$/',
		'time'		=> '/^(?:[01]\d|2[0-3])(?::[0-5]\d){2}$/',
		'uuid'		=> '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89aAbB][a-f0-9]{3}-[a-f0-9]{12}$/'
	);
}

/**
 * Child
 *
 * A private function to figure out the child node type
 *
 * @name __child
 * @param array $details			An array describing a data point
 * @return _NodeInterface
 */
function _child($details) {

	// If we got an array
	if(is_array($details)) {

		// If we got a list
		if(isset($details[0])) {

			// Create a list of options for the key
			return new OptionsNode($details);
		}

		// Else we got a dictionary
		else {

			// If array is present
			if(isset($details['__array__'])) {
				return new ArrayNode($details);
			}

			// Else if we have a hash
			else if(isset($details['__hash__'])) {
				return new HashNode($details);
			}

			// Else if we have a type
			else if(isset($details['__type__'])) {

				// If the type is an array, this is a complex type
				if(is_array($details['__type__'])) {
					return _child($details['__type__']);
				}

				// Else it's just a Node
				else {
					return new Node($details);
				}
			}

			// Else it's most likely a parent
			else {
				return new ParentNode($details);
			}
		}
	}

	// Else if we got a string
	else if(is_string($details)) {

		// Use the value as the type
		return new Node($details);
	}

	// Else throw an error
	else {
		throw new \Exception('details');
	}
}

/**
 * Compare IPs
 *
 * Compares two IPs and returns a status based on which is greater
 * If first is less than second: -1
 * If first is equal to second: 0
 * If first is greater than second: 1
 *
 * @name _compare_ips
 * @param string $first				A string representing an IP address
 * @param string $second			A string representing an IP address
 * @return int
 */
function _compare_ips($first, $second) {

	// If the two IPs are the same, return 0
	if($first == $second) {
		return 0;
	}

	// Create arrays from the split of each IP, store them as ints
	$lFirst = array_map('intval', explode('.', $first));
	$lSecond = array_map('intval', explode('.', $second));

	// Go through each part from left to right until we find the
	// 	difference
	for($i = 0; $i <= 4; ++$i) {

		// If the part of x is greater than the part of y
		if($lFirst[$i] > $lSecond[$i]) {
			return 1;
		}

		// Else if the part of x is less than the part of y
		else if($lFirst[$i] < $lSecond[$i]) {
			return -1;
		}
	}
}

/**
 * Node interface
 *
 * All Node instances must be built off this set of methods
 */
interface _NodeInterface {
	public function clean($value);
	public static function fromFile($filename);
	public function toArray();
	public function toJSON();
	public function className();
	public function valid($value, $level);
}

/**
 * Base Node class
 *
 * Represents shared functionality amongst Nodes and Parents
 *
 * @implements _NodeInterface
 */
abstract class _BaseNode implements _NodeInterface {

	/**
	 * The name of the child class
	 * @var string
	 */
	protected $_class;

	/**
	 * Flag for whether the node is optional or not
	 * @var bool
	 */
	protected $_optional;

	/**
	 * Associated array of special fields in the node
	 * @var array
	 */
	protected $_special;

	/**
	 * Holds a list of the last errors from failed valid() calls
	 * @var string[][]
	 */
	public $validation_failures;

	/**
	 * Constructor
	 *
	 * Initialises the instance
	 *
	 * @name _BaseNode
	 * @access public
	 * @throws Exception
	 * @param array $details			Details describing the type of values allowed for the node
	 * @param string $_class			The class of the child
	 * @return _BaseNode
	 */
	public function __construct(array $details, /*string*/ $_class) {

		// Init the variables used to identify the last falure in validation
		$this->validation_failures = array();

		// Store the class name
		$this->_class = $_class;

		// Init the optional flag, assume all nodes are necessary
		$this->_optional = false;

		// If the details contains an optional flag
		if(isset($details['__optional__'])) {

			// If it's a valid bool, store it
			if(is_bool($details['__optional__'])) {
				$this->_optional = $details['__optional__'];
			}

			// Else, write a warning to stderr
			else {
				fwrite(STDERR, '"' . strval($details['__optional__']) . '" is not a valid value for __optional__, assuming false');
			}

			// Remove it from details
			unset($details['__optional__']);
		}

		// Init the special dict
		$this->_special = array();

		// If there are any other special fields in the details
		foreach($details as $k => $v) {

			// If the key is used by the child
			if(in_array($k, _Types::$special['reserved'])) {
				continue;
			}

			// If key is special
			if(preg_match(_Types::$special['key'], $k, $aMatch)) {

				// Store it with the other specials then remove it
				$this->_special[$aMatch[1]] = $details[$k];
				unset($details[$k]);
			}
		}
	}

	/**
	 * Class Name
	 *
	 * Returns a string representation of the name of the child class
	 *
	 * name className
	 * @access public
	 * @return string
	 */
	public function className() {
		return $this->_class;
	}

	/**
	 * From File
	 *
	 * Loads a JSON file and creates a Node instance from it
	 *
	 * @name fromFile
	 * @access public
	 * @static
	 * @param string $filename			The filename to load
	 * @return _BasicNode
	 */
	public static function fromFile(/*string*/ $filename) {

		// Get the contents of the file
		$details = file_get_contents($filename);

		// Decode the contents
		$details = json_decode($details);

		// Create and return the new instance
		return new static($details);
	}

	/**
	 * Optional
	 *
	 * Getter/Setter method for optional flag
	 *
	 * @name optional
	 * @access public
	 * @param bool $value				If set, the method is a setter
	 * @return bool | void
	 */
	public function optional($value = null) {

		// If there's no value, this is a getter
		if(is_null($value)) {
			return $this->_optional;
		}

		// Else, set the flag
		else {
			$this->_optional = $value ? true : false;
		}
	}

	/**
	 * Special
	 *
	 * Getter/Setter method for special values associated with nodes that are
	 * not fields
	 *
	 * @name special
	 * @access public
	 * @throws Exception
	 * @param string $name				The name of the value to either set or get
	 * @param mixed $value				The value to set, must be able to convert to JSON
	 * @return mixed | void
	 */
	public function special(/*string*/ $name, /*mixed*/ $value = null) {

		// Check the name is a string
		if(!is_string($name)) {
			throw new \Exception('name must be a string');
		}

		// Check the name is valid
		if(!preg_match(_Types::$special['name'], $name)) {
			throw new \Exception('special name must match "' . _specialSyntax . '"');
		}

		// If the value is not set, this is a getter
		if(is_null($value)) {

			// Return the value or null
			return isset($this->_special[$name]) ? $this->_special[$name] : null;
		}

		// Else, this is a setter
		else {

			// If we can't convert the value to JSON
			if(json_encode($value) === false) {
				throw new \Exception('value can not be encoded to JSON');
			}

			// Save it
			$this->_special[$name] = $value;
		}
	}

	/**
	 * To Array
	 *
	 * Returns the basic node as an array in the same format as is used in
	 * constructing it
	 *
	 * @name toArray
	 * @access public
	 * @return array
	 */
	public function toArray() {

		// Create the array we will return
		$aRet = array();

		// If the optional flag is set
		if($this->_optional) {
			$aRet['__optional__'] = true;
		}

		// Add all the special fields found
		foreach($this->_special as $k => $v) {
			$aRet['__' . $k . '__'] = $v;
		}

		// Return
		return $aRet;
	}

	/**
	 * To JSON
	 *
	 * Returns a JSON string representation of the instance
	 *
	 * @name toJSON
	 * @access public
	 * @return string
	 */
	public function toJSON() {
		return json_encode($this->toArray());
	}
}

/**
 * ArrayNode class
 *
 * Represents a node which is actually an array containing lists of another node
 *
 * @extends _BaseNode
 */
class ArrayNode extends _BaseNode {

	/**#@+
	 * Min/Max values
	 * @var uint
	 */
	protected $_maximum;
	protected $_minimum;
	/**#@-*/

	/**
	 * The child node of the array
	 * @var _NodeInterface
	 */
	protected $_node;

	/**
	 * The type of array
	 * @var string
	 */
	protected $_type;

	/**
	 * Valid values for type
	 * @var string[]
	 */
	protected static $_VALID_ARRAY = array('unique', 'duplicates');

	/**
	 * Constructor
	 *
	 * Initialises the instance
	 *
	 * @name ArrayNode
	 * @access public
	 * @throws Exception
	 * @param array $details			Details describing the type of values allowed for the node
	 * @return ArrayNode
	 */
	public function __construct(array $details) {

		// If the array config is not found
		if(!isset($details['__array__'])) {
			throw new \Exception('missing "__array__" in details');
		}

		// If __array__ is not an array
		if(!is_array($details['__array__'])) {
			$details['__array__'] = array(
				"type" => $details['__array__']
			);
		}

		// Call parent constructor
		parent::__construct($details, 'ArrayNode');

		// Init the protect vars
		$this->_minimum = null;
		$this->_maximum = null;
		$this->_type = 'unique';
		$this->_node = null;

		if(!in_array($details['__array__']['type'], self::$_VALID_ARRAY)) {
			fwrite(STDERR, '"' . strval($details['__array__']['type']) . '" is not a valid type for __array__, assuming "unique"');
		}

		// Else, store it
		else {
			$this->_type = $details['__array__']['type'];
		}

		// If there's a minimum or maximum present
		$bMin = isset($details['__array__']['minimum']);
		$bMax = isset($details['__array__']['maximum']);
		if($bMin || $bMax) {
			$this->minmax(
				$bMin ? $details['__array__']['minimum'] : null,
				$bMax ? $details['__array__']['maximum'] : null
			);
		}

		// Remove the __array__ field from details
		unset($details['__array__']);

		// Store the child
		$this->_node = _child($details);
	}

	/**
	 * Child
	 *
	 * Returns the child node associated with the array
	 *
	 * @name child
	 * @access public
	 * @return object
	 */
	public function child() {
		return $this->_node;
	}

	/**
	 * Clean
	 *
	 * Goes through each of the values in the list, cleans it, stores it, and
	 * returns a new list
	 *
	 * @name clean
	 * @access public
	 * @throws Exception
	 * @param array $value				The value to clean
	 * @return array
	 */
	public function clean($value) {

		// If the value is null and it's optional, return as is
		if(is_null($value) && $this->_optional) {
			return null;
		}

		// If the value is not an array
		if(!is_array($value)) {
			throw new \Exception('value');
		}

		// Recurse and return it
		$aRet = array();
		for($i = 0; $i < count($value); ++$i) {
			$aRet[] = $this->_node->clean($value[$i]);
		}
		return $aRet;
	}

	/**
	 * Min/Max
	 *
	 * Sets or gets the minimum and maximum number of items for the Array
	 *
	 * @name minmax
	 * @access public
	 * @throws Exception
	 * @param uint $minimum				The minimum value to set
	 * @param uint $maximum				The maximum value to set
	 * @return array | void
	 */
	public function minmax(/*uint*/ $minimum = null, /*uint*/ $maximum = null) {

		// If neither minimum or maximum is set, this is a getter
		if($minimum == null and maximum == null) {
			return array("minimum" => $this->_minimum, "maximum" => $this->_maximum);
		}

		// If the minimum is set
		if($minimum != null) {

			// If the value is not a valid int or long
			if(!is_int($minimum)) {

				// If it's a valid representation of an integer
				if(is_string($minimum) &&
					preg_match(_Types::$regex['int'], $minimum)) {

					// Convert it
					$minimum = intval($minimum, 0);
				}

				// Else, throw an error
				else {
					throw new \Exception('__minimum__');
				}
			}

			// If it's below zero
			if($minimum < 0) {
				throw new \Exception('__minimum__');
			}

			// Store the minimum
			$this->_minimum = $minimum;
		}

		// If the maximum is set
		if($maximum != null) {

			// If the value is not a valid int or long
			if(!is_int($maximum)) {

				// If it's a valid representation of an integer
				if(is_string($maximum) &&
					preg_match(_Types::$regex['int'], $maximum)) {

					// Convert it
					$maximum = intval($maximum, 0);
				}

				// Else, throw an error
				else {
					throw new \Exception('__minimum__');
				}
			}

			// It's below zero
			if($maximum < 0) {
				throw new \Exception('__maximum__');
			}

			// If we also have a minimum and the max is somehow below it
			if($this->_minimum && $maximum < $this->_minimum) {
				throw new \Exception('__maximum__');
			}

			// Store the maximum
			$this->_maximum = $maximum;
		}
	}

	/**
	 * To Array
	 *
	 * Returns the ArrayNode as an array in the same format as is used in
	 * constructing it
	 *
	 * @name toArray
	 * @access public
	 * @return array
	 */
	public function toArray() {

		// Init the array we will return
		$aRet = array();

		// If either a min or a max is set
		if($this->_minimum || $this->_maximum) {

			// Set the array element as it's own dict
			$aRet['__array__'] = array(
				'type' => $this->_type
			);

			// If there is a minimum
			if($this->_minimum) {
				$aRet['__array__']['minimum'] = $this->_minimum;
			}

			// If there is a maximum
			if($this->_maximum) {
				$aRet['__array__']['maximum'] = $this->_maximum;
			}
		}

		// Else, just add the type as the array element
		else {
			$aRet['__array__'] = $this->_type;
		}

		// Add the parents and child array data and return
		return array_merge(
			$aRet,
			parent::toArray(),
			$this->_node->toArray()
		);
	}

	/**
	 * Valid
	 *
	 * Checks if a value is valid based on the instance's values
	 *
	 * @name valid
	 * @access public
	 * @param array $value				The value to validate
	 * @return bool
	 */
	public function valid($value, $level = array()) {

		// Reset validation failures
		$this->validation_failures = array();

		// If the value is null and it's optional, we're good
		if(is_null($value) && $this->_optional) {
			return true;
		}

		// If the level is not an array
		if(!is_array($level)) {
			throw new \Exception('level');
		}

		// If the value isn't a list
		if(!is_array($value)) {
			$this->validation_failures[] = array(implode('.', $level), strval($value));
			return false;
		}

		// Init the return, assume valid
		$bRet = true;

		// Keep track of duplicates
		if($this->_type == 'unique') {
			$aItems	= array();
		}

		// Go through each item in the list
		for($i = 0; $i < count($value); ++$i) {

			// Add the field to the level
			$lLevel = $level;
			$lLevel[] = '[' . $i . ']';

			// If the element isn't valid, return false
			if(!$this->_node->valid($value[$i], $lLevel)) {
				$this->validation_failures = array_merge($this->validation_failures, $this->_node->validation_failures);
				$bRet = false;
				continue;
			}

			// If we need to check for duplicates
			if($this->_type == 'unique') {

				// If the value already exists, add the error to the list
				if(($iIndex = array_search($value[$i], $aItems)) !== false) {
					$this->validation_failures[] = array(implode('.', $lLevel), 'duplicate of ' . implode('.', $level) . '[' . $iIndex . ']');
					$bRet = false;
					continue;
				}

				// Add the value to the array and continue
				$aItems[] = $value[$i];
			}
		}

		// If there's a minumum
		if($this->_minimum != null) {

			// If we don't have enough
			if(count($value) < $this->_minimum) {
				$this->validation_failures[] = array(implode('.', $level), 'did not meet minimum');
				$bRet = false;
			}
		}

		// If there's a maximum
		if($this->_maximum != null) {

			// If we have too many
			if(count($value) > $this->_maximum) {
				$this->validation_failures[] = array(implode('.', $level), 'exceeds maximum');
				$bRet = false;
			}
		}

		// Return whatever the result was
		return $bRet;
	}
}

/**
 * HashNode class
 *
 * Handles objects similar to parents except where the keys are dynamic instead
 * of static, and the values are all of one node definition
 *
 * @extends _BaseNode
 */
class HashNode extends _BaseNode {

	/**
	 * Constructor
	 *
	 * Initialises the instance
	 *
	 * @name HashNode
	 * @access public
	 * @throws Exception
	 * @param array $details			Details describing the type of values allowed for the node
	 * @return HashNode
	 */
	public function __construct(array $details) {

		// If the hash config is not found
		if(!isset($details['__hash__'])) {
			throw new \Exception('missing "__hash__" in details');
		}

		// Call the parent constructor
		parent::__construct($details, 'HashNode');

		// If the hash is simply set to True, make it a string with no
		//	requirements
		if($details['__hash__'] === true) {
			$details['__hash__'] = array('__type__' => 'string');
		}

		// Store the key using the hash value
		$this->_key = new Node($details['__hash__']);

		// Remove it from details
		unset($details['__hash__']);

		// Store the child
		$this->_node = _child($details);
	}

	/**
	 * Child
	 *
	 * Returns the child node associated with the hash
	 *
	 * @name child
	 * @access public
	 * @return object
	 */
	public function child() {
		return $this->_node;
	}

	/**
	 * Clean
	 *
	 * Makes sure both the key and value are properly stored in their correct
	 * representation
	 *
	 * @name clean
	 * @access public
	 * @throws Exception
	 * @param array $value				The value to clean
	 * @return array
	 */
	public function clean($value) {

		// If the value is null and it's optional, return as is
		if(is_null($value) && $this->_optional) {
			return null;
		}

		// If the value is not a dict
		if(!is_array($value)) {
			throw new \Exception('value');
		}

		// Recurse and return it
		$aRet = array();
		foreach($value as $k => $v) {
			$aRet[$this->_key->clean($k)] = $this->_node->clean($v);
		}
		return $aRet;
	}

	/**
	 * To Array
	 *
	 * Returns the Hashed Node as a dictionary in the same format as is used in
	 * constructing it
	 *
	 * @name toArray
	 * @access public
	 * @return array
	 */
	public function toArray() {

		// Merge the key and node then return
		return array_merge(
			array('__hash__' => $this->_key.toArray()),
			parent::toArray(),
			$this->_node.toArray()
		);
	}

	/**
	 * Valid
	 *
	 * Checks if a value is valid based on the instance's values
	 *
	 * @name valid
	 * @access public
	 * @param array $value				The value to validate
	 * @return bool
	 */
	public function valid($value, $level = array()) {

		// Reset validation failures
		$this->validation_failures = array();

		// If the value is null and it's optional, we're good
		if(is_null($value) && $this->_optional) {
			return true;
		}

		// If the level is not an array
		if(!is_array($level)) {
			throw new \Exception('level');
		}

		// If the value isn't an array
		if(!is_array($value)) {
			$this->validation_failures[] = array(implode('.', $level), strval($value));
			return false;
		}

		// Init the return, assume valid
		$bRet = true;

		// Go through each key and value
		foreach($value as $k => $v) {

			// Add the field to the level
			$lLevel = $level;
			$lLevel[] = $k;

			// If the key isn't valid
			if(!$this->_key->valid($k)) {
				$this->validation_failures[] = array(implode('.', $lLevel), 'invalid key: ' + strval($k));
				$bRet = false;
				continue;
			}

			// Check the value
			if(!$this->_node->valid($v, $lLevel)) {
				$this->validation_failures = array_merge($this->validation_failures, $this->_node->validation_failures);
				$bRet = false;
				continue;
			}
		}

		// Return whatever the result was
		return $bRet;
	}
}

/**
 * Node class
 *
 * Represents a single node of data, an immutable type like an int or a string
 *
 * @extends _BaseNode
 */
class Node extends _BaseNode {

	/**#@+
	 * Min/Max values
	 * @var uint
	 */
	protected $_maximum;
	protected $_minimum;
	/**#@-*/

	/**
	 * Options for the Node
	 * @var mixed[]
	 */
	protected $_options;

	/**
	 * Regular expresssion to validate a string
	 * @var string
	 */
	protected $_regex;

	/**
	 * The type of node
	 * @var string
	 */
	protected $_type;

	/**
	 * Valid node types
	 * @var string[]
	 */
	protected static $_VALID_TYPES = array(
		'any', 'base64', 'bool', 'date', 'datetime', 'decimal',
		'float', 'int', 'ip', 'json', 'md5', 'price', 'string',
		'time', 'timestamp', 'uint', 'uuid');

	/**
	 * Constructor
	 *
	 * @name Node
	 * @access public
	 * @throws Exception
	 * @param array|string $details		Details describing the type of value allowed for the node
	 * @return Node
	 */
	public function __construct($details) {

		// If we got a string
		if(is_string($details)) {
			$details = array('__type__' => $details);
		}

		// If details is not an array
		else if(!is_array($details)) {
			throw new \Exception('details must be an array');
		}

		// If the type is not found
		if(!isset($details['__type__'])) {
			throw new \Exception('missing "__type__" in details');
		}

		// If the type is invalid
		if(!in_array($details['__type__'], self::$_VALID_TYPES)) {
			throw new \Exception('invalid "__type__" in details');
		}

		// Call the parent constructor
		parent::__construct($details, 'Node');

		// Store the type and remove it from the details
		$this->_type = $details['__type__'];

		// Init the value types
		$this->_regex = null;
		$this->_options = null;
		$this->_minimum = null;
		$this->_maximum = null;

		// If there's a regex string available
		if(isset($details['__regex__'])) {
			$this->regex('/' . $details['__regex__'] . '/');
		}

		// Else if there's a list of options
		else if(isset($details['__options__'])) {
			$this->options($details['__options__']);
		}

		// Else
		else {

			// If there's a min or max
			$bMin = isset($details['__minimum__']);
			$bMax = isset($details['__maximum__']);

			if($bMin || $bMax) {
				$this->minmax(
					$bMin ? $details['__minimum__'] : null,
					$bMax ? $details['__maximum__'] : null
				);
			}
		}
	}

	/**
	 * Clean
	 *
	 * Cleans and returns the new value
	 *
	 * @name clean
	 * @access public
	 * @param mixed $value				The value to clean
	 * @return mixed
	 */
	public function clean($value) {

		// If the value is null and it's optional, return as is
		if(is_null($value) && $this->_optional) {
			return null;
		}

		// If it's an ANY, there is no reasonable expectation that we know what
		//	the value should be, so we return it as is
		if($this->_type == 'any') {
			//pass
		}

		// Else if it's a basic string type
		else if(in_array($this->_type, array('base64', 'ip', 'string', 'uuid'))) {

			// And not already a string
			if(!is_string($value)) {
				$value = strval($value);
			}
		}

		// Else if it's a BOOL just check if the value flags as positive
		else if($this->_type == 'bool') {

			// If it's specifically a string, it needs to match a specific
			//	pattern to be true
			if(is_string($value)) {
				$value = in_array($value, array('true', 'True', 'TRUE', 't', 'T', 'yes', 'Yes', 'YES', 'y', 'Y', 'x', '1'));
			}

			// Else
			else {
				$value = $value ? true : False;
			}
		}

		// Else if it's a date type
		else if($this->_type == 'date') {

			// If it's a PHP type, call format on it
			if($value instanceof \DateTime) {
				$value = $value->format('Y-m-d');
			}

			// Else if it's already a string
			else if(is_string($value)) {
				//pass
			}

			// Else convert it to a string
			else {
				$value = strval($value);
			}
		}

		// Else if it's a datetime type
		else if($this->_type == 'datetime') {

			// If it's a PHP type, call format on it
			if($value instanceof \DateTime) {
				$value = $value->format('Y-m-d H:i:s');
			}

			// Else if it's already a string
			else if(is_string($value)) {
				//pass
			}

			// Else convert it to a string
			else {
				$value = strval($value);
			}
		}

		// Else if it's a decimal
		else if($this->_type == 'decimal') {

			// If it's not a string
			if(!is_string($value)) {
				$value = strval($value);
			}
		}

		// Else if it's a float
		else if($this->_type == 'float') {

			// If it's not a float
			if(!is_float($value)) {
				$value = floatval($value);
			}
		}

		// Else if it's an int type
		else if(in_array($this->_type, array('int', 'timestamp', 'uint'))) {

			// If the value is a string
			if(is_string($value)) {

				// If it starts with 0
				if($value[0] == '0' && strlen($value) > 1) {

					// If it's followed by X or x, it's hex
					if(in_array($value[1], array('x', 'X')) && strlen($value) > 2) {
						$value = intval($value, 16);
					}

					// Else it's octal
					else {
						$value = intval($value, 8);
					}
				}

				// Else it's base 10
				else {
					$value = intval($value, 10);
				}
			}

			// Else if it's not an int already
			else if(!is_int($value)) {
				$value = intval($value);
			}
		}

		// Else if it's a JSON type
		else if($this->_type == 'json') {

			// If it's already a string
			if(is_string($value)) {
				//pass
			}

			// Else, encode it
			else {
				$value = json_encode($value);
			}
		}

		// Else if it's an md5 type
		else if($this->_type == 'md5') {

			// If it's a string
			if(is_string($value)) {
				//pass
			}

			// Else, try to convert it to a string
			else {
				$value = strval($value);
			}
		}

		// Else if it's a price type
		else if($this->_type == 'price') {

			// If it's not a string
			if(!is_string($value)) {
				$value = number_format((float)$value, 2, '.', '');
			}
		}

		// Else if it's a time type
		else if($this->_type == 'time') {

			// If it's a PHP type, use format on it
			if($value instanceof \DateTime) {
				$value = $value->format('H:i:s');
			}

			// Else if it's already a string
			else if(is_string($value)) {
				//pass
			}

			// Else convert it to a string
			else {
				$value = strval($value);
			}
		}

		// Else we probably forgot to add a new type
		else {
			throw new \Exception($this->_type . ' has not been added to ->clean()');
		}

		// Return the cleaned value
		return $value;
	}

	/**
	 * Min/Max
	 *
	 * Sets or gets the minimum and/or maximum values for the Node. For
	 * getting, returns array('minimum' => mixed, 'maximum' => mixed)
	 *
	 * @name minmax
	 * @access public
	 * @throws Exception
	 * @param mixed $minimum			The minimum value
	 * @param mixed $maxium				The maximum value
	 * @return array | void
	 */
	public function minmax($minimum = null, $maximum = null) {

		// If neither min or max is set, this is a getter
		if($minimum == null && $maximum == null) {
			return array('minimum' => $this->_minimum, 'maximum' => $this->_maximum);
		}

		// If the minimum is set
		if($minimum != null) {

			// If the current type is a date, datetime, ip, or time
			if(in_array($this->_type, array('date', 'datetime', 'ip', 'time'))) {

				// Make sure the value is valid for the type
				if(!is_string($minimum) ||
					!preg_match(_Types::$regex[$this->_type], $minimum)) {
					throw new \Exception('__minimum__');
				}
			}

			// Else if the type is an int (unsigned, timestamp), or a string in
			// 	which the min/max are lengths
			else if(in_array($this->_type, array('int', 'string', 'timestamp', 'uint'))) {

				// If the value is not a valid int or long
				if(!is_int($minimum)) {

					// If it's a valid representation of an integer
					if(is_string($minimum) &&
						preg_match(_Types::$regex['int'], $minimum)) {

						// Convert it
						$minimum = intval($minimum, 0);
					}

					// Else, throw an error
					else {
						throw new \Exception('__minimum__');
					}

					// If the type is meant to be unsigned
					if(in_array($this->_type, array('string', 'timestamp', 'uint'))) {

						// And it's below zero
						if($minimum < 0) {
							throw new \Exception('__minimum__');
						}
					}
				}
			}

			// Else if the type is decimal
			else if($this->_type == 'decimal') {

				// If it's not a string, convert it
				if(!is_string($minimum)) {
					$minimum = strval($minimum);
				}

				// If it doesn't fit the regex
				if(!preg_match(_Types::$regex['decimal'], $minimum)) {
					throw new \Exception('__minimum__');
				}
			}

			// Else if the type is float
			else if($this->_type == 'float') {

				// If it's not already a float, convert it
				if(!is_float($minimum)) {
					$minimum = floatval($minimum);
				}
			}

			// Else if the type is price
			else if($this->_type == 'price') {

				// If it's not a valid representation of a price
				if(!is_string($minimum) ||
					!preg_match(_Types::$regex['price'], $minimum)) {
					throw new \Exception('__minimum__');
				}
			}

			// Else we can't have a minimum
			else {
				throw new \Exception('can not set __minimum__ for ' . $this->_type);
			}

			// Store the minimum
			$this->_minimum = $minimum;
		}

		// If the maximum is set
		if($maximum != null) {

			// If the current type is a date, datetime, ip, or time
			if(in_array($this->_type, array('date', 'datetime', 'ip', 'time'))) {

				// Make sure the value is valid for the type
				if(!is_string($maximum) ||
					!preg_match(_Types::$regex[$this->_type], $maximum)) {
					throw new \Exception('__maximum__');
				}
			}

			// Else if the type is an int (unsigned, timestamp), or a string in
			// 	which the min/max are lengths
			else if(in_array($this->_type, array('int', 'string', 'timestamp', 'uint'))) {

				// If the value is not a valid int or long
				if(!is_int($maximum)) {

					// If it's a valid representation of an integer
					if(!is_string($maximum) &&
						!preg_match(_Types::$regex['int'], $maximum)) {

						// Convert it
						$maximum = intval($maximum, 0);
					}

					// Else, throw an error
					else {
						throw new \Exception('__maximum__');
					}

					// If the type is meant to be unsigned
					if(in_array($this->_type, array('string', 'timestamp', 'uint'))) {

						// And it's below zero
						if($maximum < 0) {
							throw new \Exception('__maximum__');
						}
					}
				}
			}

			// Else if the type is decimal
			else if($this->_type == 'decimal') {

				// If it's not a string, convert it
				if(!is_string($maximum)) {
					$maximum = strval($maximum);
				}

				// If it doesn't fit the regex
				if(!preg_match(_Types::$regex['decimal'], $maximum)) {
					throw new \Exception('__maximum__');
				}
			}

			// Else if the type is float
			else if($this->_type == 'float') {

				// If it's not already a float, convert it
				if(!is_float($maximum)) {
					$maximum = floatval($maximum);
				}
			}

			// Else if the type is price
			else if($this->_type == 'price') {

				// If it's not a valid representation of a price
				if(!is_string($maximum) ||
					!preg_match(_Types::$regex['price'], $maximum)) {
					throw new \Exception('__maximum__');
				}
			}

			// Else we can't have a maximum
			else {
				throw new \Exception('can not set __maximum__ for ' . $this->_type);
			}

			// If we also have a minimum
			if($this->_minimum != null) {

				// If the type is an IP
				if($this->_type == 'ip') {

					// If the min is above the max, we have a problem
					if(_compare_ips($this->_minimum, $maximum) == 1) {
						throw new \Exception('__maximum__');
					}
				}

				// Else any other data type
				else {

					// If the min is above the max, we have a problem
					if($this->_minimum > $maximum) {
						throw new \Exception('__maximum__');
					}
				}
			}

			// Store the maximum
			$this->_maximum = $maximum;
		}
	}

	/**
	 * Options
	 *
	 * Sets or gets the list of acceptable values for the Node
	 *
	 * @name options
	 * @access public
	 * @throws Exception
	 * @param array $opts				An array of valid values for the Node
	 * @return array | void
	 */
	public function options($options = null) {

		// If opts aren't set, this is a getter
		if($options == null) {
			return $this->_options;
		}

		// If the options are not a list
		if(!is_array($options) || !isset($options[0])) {
			throw new \Exception('options');
		}

		// If the type is not one that can have options
		if(!in_array($this->_type, array(
				'base64', 'date', 'datetime', 'decimal', 'float',
				'int', 'ip', 'md5', 'price', 'string', 'time',
				'timestamp', 'uint', 'uuid'))) {
			throw new \Exception('can not set __options__ for ' . $this->_type);
		}

		// Init the list of options to be saved
		$aOpts = array();

		// Go through each item and make sure it's unique and valid
		for($i = 0; $i < count($options); ++$i) {

			// Convert the value based on the type
			// If the type is a string one that we can validate
			if(in_array($this->_type, array('base64', 'date', 'datetime', 'ip', 'md5', 'time', 'uuid'))) {

				// If the value is not a string or doesn't match its regex, throw
				//	an error
				if(!is_string($options[$i]) ||
					!preg_match(_Types::$regex[$this->_type], $options[$i])) {
					throw new \Exception('__options__[' . $i . ']');
				}
			}

			// Else if it's decimal
			else if($this->_type == 'decimal') {

				// If it's not a string, convert it
				if(!is_string($options[$i])) {
					$options[$i] = strval($options[$i]);
				}

				// If it doesn't fit the regex
				if(!preg_match(_Types::$regex['decimal'], $options[$i])) {
					throw new \Exception('__options__[' . $i . ']');
				}
			}

			// Else if it's a float
			else if($this->_type == 'float') {

				// If it's not already a float, convert it
				if(!is_float($options[$i])) {
					$options[$i] = floatval($options[$i]);
				}
			}

			// Else if it's an integer
			else if(in_array($this->_type, array('int', 'timestamp', 'uint'))) {

				// If we don't already have an int
				if(!is_int($options[$i])) {

					// And we don't have a string
					if(!is_string($options[$i])) {
						throw new \Exception('__options__[' . $i . ']');
					}

					// Convert it
					$options[$i] = intval($options[$i], 0);
				}

				// If the type is unsigned and negative, throw an error
				if(in_array($this->_type, array('timestamp', 'uint')) && $options[$i] < 0) {
					throw new \Exception('__options__[' . $i . ']');
				}
			}

			// Else if it's a price
			else if($this->_type == 'price') {

				// If it's not a valid representation of a price
				if(!is_string($options[$i]) ||
					!preg_match(_Types::$regex['price'], $options[$i])) {
					throw new \Exception('__options__[' . $i . ']');
				}
			}

			// Else if the type is a string
			else if($this->_type == 'string') {

				// If the value is not a string
				if(!is_string($options[$i])) {
					$options[$i] = strval($options[$i]);
				}
			}

			// Else we have no validation for the type
			else {
				throw new \Exception('can not set __options__ for ' . $this->_type);
			}

			// If it's already in the list, throw an error
			if(in_array($options[$i], $aOpts)) {
				fwrite(STDERR, '__options__[' . $i . '] is a duplicate');
			}

			// Store the option
			else {
				$aOpts[] = $options[$i];
			}
		}

		// Store the list of options
		$this->_options = $aOpts;
	}

	/**
	 * Regex
	 *
	 * Sets or gets the regular expression used to validate the Node
	 *
	 * @name regex
	 * @access public
	 * @throws Exception
	 * @param string $regex				A standard regular expression string
	 * @return string | void
	 */
	public function regex($regex = null) {

		// If regex was not set, this is a getter
		if($regex == null) {
			return $this->_regex;
		}

		// If the type is not a string
		if($this->_type != 'string') {
			fwrite(STDERR, 'can not set __regex__ for ' . $this->_type);
			return;
		}

		// If it's not a valid string or regex
		if(!is_string($regex)) {
			throw new \Exception('__regex__');
		}

		// Store the regex
		$this->_regex = $regex;
	}

	/**
	 * To Array
	 *
	 * Returns the basic node as an array in the same format as is used in
	 * constructing it
	 *
	 * @name toArray
	 * @access public
	 * @return array
	 */
	public function toArray() {

		// Call the parent method
		$aRet = parent::toArray();

		// Add the type
		$aRet['__type__'] = $this->_type;

		// If there's a regex
		if(!is_null($this->_regex)) {
			$aRet['__regex__'] = substr($this->_regex, 1, -1);
		}

		// If there are options
		if(!is_null($this->_options)) {
			$aRet['__options__'] = $this->_options;
		}

		// If there's a minimum
		if(!is_null($this->_minimum)) {
			$aRet['__minimum__'] = $this->_minimum;
		}

		// If there's a maximum
		if(!is_null($this->_maximum)) {
			$aRet['__maximum__'] = $this->_maximum;
		}

		// Return
		return $aRet;
	}

	/**
	 * Type
	 *
	 * Returns the type of Node
	 *
	 * @name type
	 * @access public
	 * @return string
	 */
	public function type() {
		return $this->_type;
	}

	/**
	 * Valid
	 *
	 * Checks if a value is valid based on the instance's values
	 *
	 * @name valid
	 * @access public
	 * @param mixed $value				The value to validate
	 * @return bool
	 */
	public function valid($value, $level = array()) {

		// Reset validation failures
		$this->validation_failures = array();

		// If the value is null and it's optional, we're good
		if(is_null($value) && $this->_optional) {
			return true;
		}

		// If the level is not an array
		if(!is_array($level)) {
			throw new \Exception('level');
		}

		// If we are validating an ANY field, immediately return true
		if($this->_type == 'any') {
			//pass
		}

		// If we are validating a DATE, DATETIME, IP or TIME data point
		else if(in_array($this->_type, array('base64', 'date', 'datetime', 'ip', 'md5', 'time', 'uuid'))) {

			// If it's a date or datetime type and the value is a python type
			if($this->_type == 'date' && $value instanceof \DateTime) {
				$value = $value->format('Y-m-d');
			}

			else if($this->_type == 'datetime' && $value instanceof \DateTime) {
				$value = $value->format('Y-m-d H:i:s');
			}

			// If it's a time type and the value is a python type
			else if($this->_type == 'time' && $value instanceof \DateTime) {
				$value = $value->format('H:i:s');
			}

			// If the value is not a string
			else if(!is_string($value)) {
				$this->validation_failures[] = array(implode('.', $level), 'not a string');
				return false;
			}

			// If there's no match
			if(!preg_match(_Types::$regex[$this->_type], $value)) {
				$this->validation_failures[] = array(implode('.', $level), 'failed regex (internal)');
				return false;
			}

			// If we are checking an IP
			if($this->_type == 'ip') {

				// If there's a min or a max
				if($this->_minimum != null || $this->_maximum != null) {

					// If the IP is greater than the maximum
					if($this->_maximum != null && _compare_ips($value, $this->_maximum) == 1) {
						$this->validation_failures[] = array(implode('.', $level), 'exceeds maximum');
						return false;
					}

					// If the IP is less than the minimum
					if($this->_minimum != null && _compare_ips($value, $this->_minimum) == -1) {
						$this->validation_failures[] = array(implode('.', $level), 'did not meet minimum');
						return false;
					}

					// Return OK
					return true;
				}
			}
		}

		// Else if we are validating some sort of integer
		else if(in_array($this->_type, array('int', 'timestamp', 'uint'))) {

			// If the type is a bool, fail immediately
			if(is_bool($value)) {
				$this->validation_failures[] = array(implode('.', $level), 'is a bool');
				return false;
			}

			// If it's not an int
			if(!is_int($value)) {

				// And it's a valid representation of an int
				if(is_string($value) &&
					preg_match(_Types::$regex['int'], $value)) {

					// If it starts with 0
					if($value[0] == '0' && strlen($value) > 1) {

						// If it's followed by X or x, it's hex
						if(in_array($value[1], array('x', 'X')) && strlen($value) > 2) {
							$value = intval($value, 16);
						}

						// Else it's octal
						else {
							$value = intval($value, 8);
						}
					}

					// Else it's base 10
					else {
						$value = intval($value, 10);
					}
				}

				// Else, return false
				else {
					$this->validation_failures[] = array(implode('.', $level), 'not an integer');
					return false;
				}
			}

			// If it's not signed
			if(in_array($this->_type, array('timestamp', 'uint'))) {

				// If the value is below 0
				if($value < 0) {
					$this->validation_failures[] = array(implode('.', $level), 'signed');
					return false;
				}
			}
		}

		// Else if we are validating a bool
		else if($this->_type == 'bool') {

			// If it's already a bool
			if(is_bool($value)) {
				return true;
			}

			// If it's an int or long at 0 or 1
			if(is_int($value) && ($value == 0 || $value == 1)) {
				return true;
			}

			// Else if it's a string
			else if(is_string($value)) {

				// If it's t, T, 1, f, F, or 0
				if(in_array($value, array('true', 'True', 'TRUE', 't', 'T', '1', 'false', 'False', 'FALSE', 'f', 'F', '0'))) {
					return true;
				}
				else {
					$this->validation_failures[] = array(implode('.', $level), 'not a valid string representation of a bool');
					return false;
				}
			}

			// Else it's no valid type
			else {
				$this->validation_failures[] = array(implode('.', $level), 'not valid bool replacement');
				return false;
			}
		}

		// Else if we are validating a decimal value
		else if($this->_type == 'decimal') {

			// If the type is a bool, fail immediately
			if(is_bool($value)) {
				$this->validation_failures[] = array(implode('.', $level), 'is a bool');
				return false;
			}

			// If it's numeric, convert it to a string
			if(is_float($value) || is_int($value)) {
				$value = strval($value);
			}

			// Else if it's a string
			else if(is_string($value)) {

				// If the format is wrong
				if(!preg_match(_Types::$regex['decimal'], $value)) {
					$this->validation_failures[] = array(implode('.', $level), 'failed regex (internal)');
					return false;
				}
			}

			// Else we can't convert it
			else {
				$this->validation_failures[] = array(implode('.', $level), 'can not be converted to decimal');
				return false;
			}

			// If there's options
			if($this->_options) {

				// Go through each one
				foreach($this->_options as $d) {

					// If they match, return OK
					if(bccomp($value, $d, 17) == 0) {
						return true;
					}
				}
			}

			// Else if there's a min or max
			else if($this->_minimum != null || $this->_maximum != null) {

				// If there's a minimum and we don't reach it
				if($this->_minimum != null && bccomp($value, $this->_minimum, 17) == -1) {
					$this->validation_failures[] = array(implode('.', $level), 'not long enough');
					return false;
				}

				// If there's a maximum and we surpass it
				if($this->_maximum != null && bccomp($value, $this->_maximum, 17) == 1) {
					$this->validation_failures[] = array(implode('.', $level), 'too long');
					return false;
				}

				// Return OK
				return true;
			}
		}

		// Else if we are validating a floating point value
		else if($this->_type == 'float') {

			// If the type is a bool, fail immediately
			if(is_bool($value)) {
				$this->validation_failures[] = array(implode('.', $level), 'is a bool');
				return false;
			}

			// If it's already a float
			if(is_float($value)) {
				//pass
			}

			// If it's an int or a valid representation of a float
			else if(is_int($value) ||
				(is_string($value) && preg_match(_Types::$regex['decimal'], $value))) {
				$value = floatval($value);
			}

			// Else it can't be converted
			else {
				$this->validation_failures[] = array(implode('.', $level), 'can not be converted to float');
				return false;
			}
		}

		// Else if we are validating a JSON string
		else if($this->_type == 'json') {

			// If it's already a string
			if(is_string($value)) {

				// Try to decode it
				if(json_decode($value) === null) {
					$this->validation_failures[] = array(implode('.', $level), 'Can not be decoded from JSON');
					return false;
				}
			}

			// Else
			else {

				// Try to encode it
				if(json_encode($value) === false) {
					$this->validation_failures[] = array(implode('.', $level), 'Can not be encoded to JSON');
					return false;
				}
			}
		}

		// Else if we are validating a price value
		else if($this->_type == 'price') {

			// If the type is a bool, fail immediately
			if(is_bool($value)) {
				$this->validation_failures[] = array(implode('.', $level), 'is a bool');
				return false;
			}

			// If it's a float
			if(is_float($value)) {

				// Convert it to a string
				$value = strval($value);

				// If it has too many decimal places
				if(!preg_match(_Types::$regex['price'], $value, $aMatches)) {
					$this->validation_failures[] = array(implode('.', $level), 'too many decimal points');
					return false;
				}
			}

			// If it's an int, convert it to a string
			else if(is_int($value)) {
				$value = strval($value);
			}

			// Else if it's a string
			else if(is_string($value)) {

				// If the format is wrong
				if(!preg_match(_Types::$regex['price'], $value)) {
					$this->validation_failures[] = array(implode('.', $level), 'failed regex (internal)');
					return false;
				}
			}

			// Else we can't convert it
			else {
				$this->validation_failures[] = array(implode('.', $level), 'can not be converted to decimal');
				return false;
			}

			// If there's options
			if($this->_options) {

				// Go through each one
				foreach($this->_options as $d) {

					// If they match, return OK
					if(bccomp($value, $d, 17) == 0) {
						return true;
					}
				}
			}

			// Else if there's a min or max
			else if($this->_minimum != null || $this->_maximum != null) {

				// If there's a minimum and we don't reach it
				if($this->_minimum != null && bccomp($value, $this->_minimum, 17) == -1) {
					$this->validation_failures[] = array(implode('.', $level), 'not long enough');
					return false;
				}

				// If there's a maximum and we surpass it
				if($this->_maximum != null && bccomp($value, $this->_maximum, 17) == 1) {
					$this->validation_failures[] = array(implode('.', $level), 'too long');
					return false;
				}

				// Return OK
				return true;
			}
		}

		// Else if we are validating a string value
		else if($this->_type == 'string') {

			// If the value is not some form of string
			if(!is_string($value)) {
				$this->validation_failures[] = array(implode('.', $level), 'is not a string');
				return false;
			}

			// If we have a regex
			if($this->_regex) {

				// If it doesn't match the regex
				if(!preg_match($this->_regex, $value)) {
					$this->validation_failures[] = array(implode('.', $level), 'failed regex (custom)');
					return false;
				}
			}

			// Else
			else if($this->_minimum != null || $this->_maximum != null) {

				// If there's a minimum length and we don't reach it
				if($this->_minimum != null && strlen($value) < $this->_minimum) {
					$this->validation_failures[] = array(implode('.', $level), 'not long enough');
					return false;
				}

				// If there's a maximum length and we surpass it
				if($this->_maximum != null && strlen($value) > $this->_maximum) {
					$this->validation_failures[] = array(implode('.', $level), 'too long');
					return false;
				}

				// Return OK
				return true;
			}
		}

		// If there's a list of options
		if($this->_options != null) {

			// Returns based on the option's existance
			for($i = 0; $i < count($this->_options); ++$i) {
				if($value === $this->_options[$i]) {
					return true;
				}
			}

			$this->validation_failures[] = array(implode('.', $level), 'not in options');
			return false;
		}

		// Else check for basic min/max
		else {

			// If the value is less than the minimum
			if($this->_minimum != null && $value < $this->_minimum) {
				$this->validation_failures[] = array(implode('.', $level), 'did not meet minimum');
				return false;
			}

			// If the value is greater than the maximum
			if($this->_maximum != null && $value > $this->_maximum) {
				$this->validation_failures[] = array(implode('.', $level), 'exceeds maximum');
				return false;
			}
		}

		// Value has no issues
		return true;
	}
}

/**
 * OptionsNode class
 *
 * Represents a node which can have several different types of values/Nodes and
 * still be valid
 *
 * @implements _NodeInterface
 */
class OptionsNode implements _NodeInterface {

	/**
	 * List of valid Nodes
	 * @var _NodeInterface[]
	 */
	protected $_nodes;

	/**
	 * Flag for whether the node is optional or not
	 * @var bool
	 */
	protected $_optional;

	/**
	 * Holds a list of the last errors from failed valid() calls
	 * @var string[][]
	 */
	public $validation_failures;

	/**
	 * Constructor
	 *
	 * Initialises the instance
	 *
	 * @name OptionsNode
	 * @access public
	 * @throws Exception
	 * @param array $details			Details describing the type of vallues allowed for the node
	 * @return OptionsNode
	 */
	public function __construct(array $details) {

		// If details is not a true array
		if(!isset($details[0])) {
			throw new \Exception('details in OptionsNode must be an array (not associative)');
		}

		// Init the variable used to identify the last falures in validation
		$this->validation_failures = array();

		// Init the optional flag, assume all nodes are optional until we find
		//	one that isn't
		$this->_optional = true;

		// Init the internal list
		$this->_nodes = array();

		// Go through each element in the list
		for($i = 0; $i < count($details); ++$i) {

			// If it's another Node instance
			if($details[$i] instanceof _BaseNode) {
				$this->_nodes[] = $details[$i];
				continue;
			}

			// If the element is a dict instance
			else if(is_array($details[$i])) {

				// Store the child
				$this->_nodes[] = _child($details[$i]);
			}

			// Whatever was sent is invalid
			else {
				throw new \Exception('details[' . $i .'] in OptionsNode must be an array');
			}

			// If the element is not optional, then the entire object can't be
			//	optional
			if(!$this->_nodes[$i]->optional()) {
				$this->_optional = false;
			}
		}
	}

	/**
	 * Class Name
	 *
	 * Returns a string representation of the name of the child class
	 *
	 * name className
	 * @access public
	 * @return string
	 */
	public function className() {
		return 'OptionsNode';
	}

	/**
	 * Clean
	 *
	 * Uses the valid method to check which type the value is, and then
	 * calls the correct version of clean on that node
	 *
	 * @name clean
	 * @access public
	 * @throws Exception
	 * @param mixed $value				The value to clean
	 * @return mixed
	 */
	public function clean($value) {

		// If the value is null and it's optional, return as is
		if(is_null($value) && $this->_optional) {
			return null;
		}

		// Go through each of the nodes
		for($i = 0; $i < count($this->_nodes); ++$i) {

			// If it's valid
			if($this->_nodes[$i]->valid($value)) {

				// Use its clean
				return $this->_nodes[$i]->clean($value);
			}
		}

		// Something went wrong
		throw new \Exception('invalid value');
	}

	/**
	 * From File
	 *
	 * Loads a JSON file and creates an OptionsNode instance from it
	 *
	 * @name fromFile
	 * @access public
	 * @static
	 * @param string $filename			The filename to load
	 * @return OptionsNode
	 */
	public static function fromFile(/*string*/ $filename) {

		// Get the contents of the file
		$details = file_get_contents($filename);

		// Decode the contents
		$details = json_decode($details);

		// Create and return the new instance
		return new self($details);
	}

	/**
	 * To Array
	 *
	 * Returns the Nodes as a list of dictionaries in the same format as is used
	 * in constructing them
	 *
	 * @name toArray
	 * @access public
	 * @return array
	 */
	public function toArray() {

		// Init the return array
		$aRet = array();

		// Go through each node and call toArray on it
		for($i = 0; $i < count($this->_nodes); ++$i) {
			$aRet[] = $this->_nodes[$i]->toArray();
		}

		// Return
		return $aRet;
	}

	/**
	 * To JSON
	 *
	 * Returns a JSON string representation of the instance
	 *
	 * @name toJSON
	 * @access public
	 * @return string
	 */
	public function toJSON() {
		return json_encode($this->toArray());
	}

	/**
	 * Valid
	 *
	 * Checks if a value is valid based on the instance's values
	 *
	 * @name valid
	 * @access public
	 * @param mixed $value				The value to validate
	 * @return bool
	 */
	public function valid($value, $level = array()) {

		// Reset validation failures
		$this->validation_failures = array();

		// If the value is null and it's optional, we're good
		if(is_null($value) && $this->_optional) {
			return true;
		}

		// If the level is not an array
		if(!is_array($level)) {
			throw new \Exception('level');
		}

		// Go through each of the nodes
		for($i = 0; $i < count($this->_nodes); ++$i) {

			// If it's valid
			if($this->_nodes[$i]->valid($value)) {
				return true;
			}
		}

		// Not valid for anything
		$this->validation_failures[] = array(implode('.', $level), 'no valid option');
		return false;
	}
}

/**
 * ParentNode class
 *
 * Represents defined keys mapped to other Nodes which themselves could be
 * Parents
 *
 * @extends _BaseNode
 */
class ParentNode extends _BaseNode implements \ArrayAccess {

	/**
	 * Associative array of keys to _NodeInterface
	 * @var array
	 */
	protected $_nodes;

	/**
	 * Associative array of fields required by other fields
	 * @var array
	 */
	protected $_requires;

	/**
	 * Constructor
	 *
	 * Initialises the instance
	 *
	 * @name Parent
	 * @access public
	 * @throws Exception
	 * @param array $details			Details describing the type of values allowed for the nodes
	 * @return Parent
	 */
	public function __construct(array $details) {

		// Call the parent constructor
		parent::__construct($details, 'Parent');

		// Init the nodes and requires arrays
		$this->_nodes = array();
		$this->_requires = array();

		// Go through the keys in the details
		foreach($details as $k => $v) {

			// If key is standard
			if(preg_match(_Types::$standard, $k)) {

				// If it's a Node
				if($v instanceof _NodeInterface) {

					// Store it as is
					$this->_nodes[$k] = $v;
				}

				// Else
				else {
					$this->_nodes[$k] = _child($v);
				}
			}
		}

		// If there's a require hash available
		if(isset($details['__require__'])) {
			$this->requires($details['__require__']);
		}
	}

	/**
	 * Clean
	 *
	 * Goes through each of the values in the array, cleans it, stores it, and
	 * returns a new array
	 *
	 * @name clean
	 * @access public
	 * @throws Exception
	 * @param array $value				The value to clean
	 * @return array
	 */
	public function clean($value) {

		// If the value is null and it's optional, return as is
		if(is_null($value) && $this->_optional) {
			return null;
		}

		// If the value is not a dict
		if(!is_array($value)) {
			throw new \Exception('value');
		}

		// Init the return value
		$aRet = array();

		// Go through each value and clean it using the associated node
		foreach($value as $k => $v) {

			// If the key doesn't exist
			if(!isset($this->_nodes[$k])) {
				throw new \Exception(strval($k) . ' is not a valid node in the parent');
			}

			// Clean the value
			$aRet[$k] = $this->_nodes[$k]->clean($value[$k]);
		}

		// Return the cleaned values
		return $aRet;
	}

	/**
	 * Contains
	 *
	 * Returns whether a specific key exists in the Parent
	 *
	 * @name contains
	 * @access public
	 * @param string $key				The key to check for
	 * @return bool
	 */
	public function contains($key) {
		return isset($this->_nodes[$key]);
	}

	/**
	 * Get
	 *
	 * Returns the node of a specific key from the Parent
	 *
	 * @name get
	 * @access public
	 * @param string $key				The name of the key to return
	 * @param mixed $default			The value to return if the key isn't found
	 * @return _NodeInterface
	 */
	public function get($key, $default = null) {
		if(isset($this->_nodes[$key])) return $this->_nodes[$key];
		else return $default;
	}

	/**
	 * Keys
	 *
	 * Returns an array of the Node names in the Parent
	 *
	 * @name keys
	 * @access public
	 * @return string[]
	 */
	public function keys() {
		return array_keys($this->_nodes);
	}

	/**
	 * Offset Exists (magic method)
	 *
	 * Returns if the key exists in the Parent
	 *
	 * @name offsetExists
	 * @access public
	 * @param string $k					The key to check for in the Parent
	 * @return bool
	 */
	public function offsetExists($k) {
		return isset($this->_nodes[$k]);
	}

	/**
	 * Offset Get (magic method)
	 *
	 * Returns the Node at the given key
	 *
	 * @name offsetGet
	 * @access public
	 * @param string $k					The key to return the Node at in the Parent
	 * @return _NodeInterface
	 */
	public function offsetGet($k) {
		return $this->_nodes[$k];
	}

	public function offsetSet($k, $v) {
		throw new \Exception('Not allowed to set Parent key');
	}

	public function offsetUnset($k) {
		throw new \Exception('Not allowed to unset Parent key');
	}

	/**
	 * Requires
	 *
	 * Sets or gets the require rules used to validate the Parent
	 *
	 * @name requires
	 * @access public
	 * @param array $require			An array of fields to fields it requires
	 * @return array | void
	 */
	public function requires($require = null) {

		// If require is null, this is a getter
		if($require == null) {
			return $this->_requires;
		}

		// If it's not a valid array
		if(!is_array($require)) {
			throw new \Exception('__require__');
		}

		// Go through each key and make sure it goes with a field
		foreach($require as $k => $v) {

			// If the field doesn't exist
			if(!isset($this->_nodes[$k])) {
				throw new \Exception('__require__[' . strval($k) . ']');
			}

			// If the value is a string
			if(is_string($v)) {
				$v = [$v];
			}

			// Else if it's not a non-associative array
			else if(!is_array($v) || !isset($v[0])) {
				throw new \Exception('__require__[' . strval($k) . ']');
			}

			// Make sure each required field also exists
			for($i = 0; $i < count($v); ++$i) {
				if(!isset($this->_nodes[$v[$i]])) {
					throw new \Exception('__require__[' . strval($k) . ']: ' . implode(',', $v));
				}
			}

			// If it's all good
			$this->_requires[$k] = $v;
		}
	}

	/**
	 * To Array
	 *
	 * Returns the Parent as an associative array in the same format as is used
	 * in constructing it
	 *
	 * @name toArray
	 * @access public
	 * @return array
	 */
	public function toArray() {

		// Get the parents array
		$aRet = parent::toArray();

		// Go through each field and add it to the return
		foreach($this->_nodes as $k => $v) {
			$aRet[$k] = $v->toArray();
		}

		// Return
		return $aRet;
	}

	/**
	 * Valid
	 *
	 * Checks if a value is valid based on the instance's values
	 *
	 * @name valid
	 * @access public
	 * @param array $value				The value to validate
	 * @return bool
	 */
	public function valid($value, $level = array()) {

		// Reset validation failures
		$this->validation_failures = array();

		// If the value is null and it's optional, we're good
		if(is_null($value) && $this->_optional) {
			return true;
		}

		// If the level is not an array
		if(!is_array($level)) {
			throw new \Exception('level');
		}

		// If the value isn't a dictionary
		if(!is_array($value)) {
			$this->validation_failures[] = array(implode('.', $level), strval($value));
			return false;
		}

		// Init the return, assume valid
		$bRet = true;

		// Go through each node in the instance
		foreach($this->_nodes as $k => $n) {

			// Add the field to the level
			$lLevel = $level;
			$lLevel[] = $k;

			// If we are missing a node
			if(!isset($value[$k])) {

				// If the value is not optional
				if(!$n->_optional) {
					$this->validation_failures[] = array(implode('.', $lLevel), 'missing');
					$bRet = false;
				}

				// Continue to next node
				continue;
			}

			// If the element isn't valid, return false
			if(!$n->valid($value[$k], $lLevel)) {
				$this->validation_failures = array_merge(
					$this->validation_failures,
					$n->validation_failures
				);
				$bRet = false;
				continue;
			}

			// If the element requires others
			if(isset($this->_requires[$k])) {

				// Go through each required field
				foreach($this->_requires[$k] as $f) {

					// If the field doesn't exist in the value
					if(!isset($value[$f]) || in_array($value[$f], array('0000-00-00','',null))) {
						$this->validation_failures[] = array(implode('.', $lLevel), 'requires "' . strval($f) . '" to also be set');
						$bRet = false;
					}
				}
			}
		}

		// Return whatever the result was
		return $bRet;
	}
}

/**
 * Tree class
 *
 * Represents the master parent of a record, holds special data to represent
 * how the entire tree is stored
 *
 * @extends ParentNode
 */
class Tree extends ParentNode {

	/**
	 * The name of the tree
	 * @var string
	 */
	protected $_name;

	/**
	 * Constructor
	 *
	 * Initialises the instance
	 *
	 * @name Tree
	 * @access public
	 * @throws Exception
	 * @param array $details			Details describing the type of values allowed for the nodes
	 * @return Tree
	 */
	public function __construct(array $details) {

		// If the name is not set
		if(!isset($details['__name__'])) {
			throw new \Exception('"__name__" not found in details');
		}

		// If the name is not valid
		if(!preg_match(_Types::$standard, $details['__name__'])) {
			throw new \Exception('"__name__" not a valid value for Tree');
		}

		// If for some reason the array flag is set, remove it
		if(isset($details['__array__'])) {
			unset($details['__array__']);
		}

		// Call the parent constructor
		parent::__construct($details);

		// Store the name then delete it
		$this->_name = $details['__name__'];

		// Overwrite classname
		$this->_class = 'Tree';
	}

	/**
	 * To Array
	 *
	 * Returns the Tree as an array in the same format as is used in
	 * constructing it
	 *
	 * @name toArray
	 * @access public
	 * @return array
	 */
	public function toArray() {

		// Merge the initial array with the name to the parent and return
		return array_merge(
			array('__name__' => $this->_name),
			parent::toArray()
		);
	}

	/**
	 * Valid
	 *
	 * Checks if a value is valid based on the instances values
	 *
	 * @name valid
	 * @access public
	 * @param array $value				The value to validate
	 * @param bool $include_name		If true, Tree's name will be prepended to all error keys
	 * @return bool
	 */
	public function valid($value, $include_name = true) {

		// Include name?
		$aLevel = array();
		if($include_name) {
			$aLevel[] = $this->_name;
		}

		// Call the parent valid method and return the result
		return parent::valid($value, $aLevel);
	}
}
