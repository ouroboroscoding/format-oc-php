<?php
/**
 * FormatOC
 *
 * Contains several classes used to represent documents and their nodes
 *
 * @author Chris Nasr
 * @copyright OurobotosCoding
 * @created 2016-02-20
 */

namespace FormatOC;

/**#@+
 * Special
 *
 * Holds regexes to match special hash elements
 */
$_specialSyntax = '[a-z0-9_-]+';
$_specialName = '/^' . $_specialSyntax . '$/';
$_specialKey = '/^__' . $_specialSyntax . '__$/';
$_specialSet = '__%s__';
/**#@-*/

/**
 * Standard
 *
 * Holds a regex to match any standard named fields. These are limited in order
 * to ease the ability to plugin additional data stores
 */
$_standardField = '/^_?[a-zA-Z][a-zA-Z0-9_]*$/';

/**
 * Type to Regex
 *
 * Halds a hash of type values to the regular expression used to validate them
 */
$_typeToRegex = array(
	'base64'	=> '/^(?:[A-Za-z0-9+/]{4})+(?:[A-Za-z0-9+/]{2}==|[A-Za-z0-9+/]{3}=)?$/',
	'date'		=> '/^\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\d|3[01])$/',
	'datetime'	=> '/^\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\d|3[01]) (?:[01]\d|2[0-3])(?::[0-5]\d){2}$/',
	'int'		=> '/^(?:0|[+-]?[1-9]\d*|0x[0-9a-f]+|0[0-7]+)$/',
	'ip'		=> '/^(?:25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9][0-9]|[1-9])(?:\.(?:25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9]?[0-9])){2}\.(?:25[0-5]|2[0-4][0-9]|1[0-9][0-9]|[1-9][0-9]|[1-9])$/',
	'md5'		=> '/^[a-fA-F0-9]{32}$/',
	'price'		=> '/^-?(?:[1-9]\d+|\d)(?:\.\d{1,2})?$/',
	'time'		=> '/^(?:[01]\d|2[0-3])(?::[0-5]\d){2}$/',
	'uuid'		=> '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89aAbB][a-f0-9]{3}-[a-f0-9]{12}$/'
);

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
				if(is_array($details)) {
					return _child($details['__type__']);
				}

				// Else it's just a Node
				else {
					return new Node($details);
				}
			}

			// Else it's most likely a parent
			else {
				return Parent($details);
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
		throw Exception('details');
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
	public static function fromFile($filename, $override);
	public function toArray();
	public function toJSON();
	public function className();
	public function valid($value, array $level);
}

/**
 * Base Node class
 *
 * Represents shared functionality amongst Nodes and Parents
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
	 * List of special fields associated with the node
	 * @var array
	 */
	protected $_special;

	/**
	 * @var array Holds a list of the last errors from failed valid() calls
	 * @access public
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

			// If key is special
			if(preg_match(_specialKey, k, $aMatch)) {

				// Store it with the other specials then remove it
				$this->_special[$aMatch[1]] = $details[k];
				unset($details[k]);
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
	 * @param bool $override			Optional, if set, values 'override' sections will be used
	 * @return _BasicNode
	 */
	public static function fromFile(/*string*/ $filename, /*bool*/ $override = false) {

		// Get the contents of the file
		$details = file_get_contents($filename);

		// Decode the contents
		$details = json_decode($details);

		// Create and return the new instance
		return new static($details);
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
			throw Exception('name must be a string');
		}

		// Check the name is valid
		if(!preg_match($_specialName, $name)) {
			throw Exception('special name must match "' . _specialSyntax . '"');
		}

		// If the value is not set, this is a getter
		if($value == null) {

			// Return the value or null
			return isset($this->_special[$name]) ? $this->_special[$name] : null;
		}

		// Else, this is a setter
		else {

			// If we can't convert the value to JSON
			if(json_encode($value) === false) {
				throw Exception('value can not be encoded to JSON');
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
			$aRet[$k] = $v;
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

		// If __array__ is not an array
		if(!is_array($details['__array__'])) {
			$details['__array__'] = array(
				"type" => $details['__array__']
			);
		}

		// If the type is missing
		if(!isset($details['__array__']['type'])) {
			$this->_type = 'unique';
		}

		// Or if the type is invalid
		else if(!in_array($details['__array__']['type'], self::$_VALID_ARRAY)) {
			$this->_type = 'unique';
			fwrite(STDERR, '"' . strval($details['__array__']['type']) . '" is not a valid type for __array__, assuming "unique"');
		}

		// Else, store it
		else {
			$this->_type = $details['__array__']['type'];
		}

		// Init the min/max values
		$this->_minimum = null;
		$this->_maximum = null;

		// If there's a minimum or maximum present
		$bMin = isset($details['__array__']['minimum']);
		$bMax = isset($details['__array__']['maximum']);
		if($bMin || $bMax) {
			this.minmax(
				$bMin ? $details['__array__']['minimum'] : null,
				$bMax ? $details['__array__']['maximum'] : null
			);
		}

		// If there's an optional flag somewhere in the mix
		if(isset($details['__optional__'])) {
			$bOptional = $details['__optional__'];
			unset($details['__optional__']);
		}
		else if(isset($details['__array__']['optional'])) {
			$bOptional = $details['__array__']['optional'];
		}
		else {
			$bOptional = null;
		}

		// Remove the __array__ field from details
		unset($details['__array__']);

		// Store the child
		$this->_node = _child($details);

		// If we had an optional flag, add it for the parent constructor
		if($bOptional) {
			$details['__optional__'] = $bOptional;
		}

		// Call parent constructor
		parent::__construct($details, 'ArrayNode');
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
					preg_match($_typeToRegex['int'], $minimum)) {

					// Convert it
					$minimum = intval($minimum, 0);
				}

				// Else, throw an error
				else {
					throw Exception('__minimum__');
				}
			}

			// If it's below zero
			if($minimum < 0) {
				throw Exception('__minimum__');
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
					preg_match($_typeToRegex['int'], $maximum)) {

					// Convert it
					$maximum = intval($maximum, 0);
				}

				// Else, throw an error
				else {
					throw Exception('__minimum__');
				}
			}

			// It's below zero
			if($maximum < 0) {
				throw Exception('__maximum__');
			}

			// If we also have a minimum and the max is somehow below it
			if($this->_minimum && $maximum < $this->_minimum) {
				throw Exception('__maximum__');
			}

			// Store the maximum
			$this->_maximum = $maximum;
		}
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
		if($value == null && $this->_optional) {
			return null;
		}

		// If the value is not an array
		if(!is_array($value)) {
			throw Exception('value');
		}

		// Recurse and return it
		$aRet = array();
		for($i = 0; $i < count($value); ++$i) {
			$aRet[] = $this->_node.clean($value[$i]);
		}
		return $aRet;
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
	public function valid($value, array $level = array()) {

		// Reset validation failures
		$this->validation_failures = array();

		// If the value is null and it's optional, we're good
		if($value == null && $this->_optional) {
			return true;
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
			$lLevel[] = '[' . i . ']';

			// If the element isn't valid, return false
			if(!$this->_node.valid($value[i], $lLevel)) {
				$this->validation_failures = array_merge($this->validation_failures, $this->_node.validation_failures);
				$bRet = false;
				continue;
			}

			// If we need to check for duplicates
			if($this->_type == 'unique') {

				// If the value already exists, add the error to the list
				if(($iIndex = array_search($value[$i], $aItems)) === false) {
					$this->validation_failures[] = array(implode('.', $lLevel), 'duplicate of ' . implode('.', $level) . '[' . $iIndex . ']');
					$bRet = false;
					continue;
				}

				// Add the value to the array and continue
				$lItems[] = $value[i];
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
			throw Exception('__hash__ not in details');
		}

		// If there's an optional flag somewhere in the mix
		if(isset($details['__optional__'])) {
			$bOptional = $details['__optional__'];
			unset($details['__optional__']);
		}
		else {
			$bOptional = null;
		}

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

		// If we had an optional flag, add it for the parent constructor
		if($bOptional) {
			$details['__optional__'] = $bOptional;
		}

		// Call the parent constructor
		parent::__construct($details, 'HashNode');
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
		if($value == null && $this->_optional) {
			return null;
		}

		// If the value is not a dict
		if(!is_array($value)) {
			throw Exception('value');
		}

		// Recurse and return it
		$aRet = array();
		foreach($value as $k => $v) {
			$aRet[$this->_key.clean($k)] = $this->_node.clean($v);
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
	public function valid($value, array $level = array()) {

		// Reset validation failures
		$this->validation_failures = array();

		// If the value is null and it's optional, we're good
		if($value == null && $this->_optional) {
			return true;
		}

		// If the value isn't an array
		if(!is_array($value)) {
			$this->validation_failures[] = array(implode('.', level), strval($value));
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
			if(!$this->_key.valid(k)) {
				$this->validation_failures[] = array(implode('.', lLevel), 'invalid key: ' + strval($k));
				$bRet = false;
				continue;
			}

			// Check the value
			if(!$this->_node.valid($v, $lLevel)) {
				$this->validation_failures = array_merge($this->validation_failures, $this->_node.validation_failures);
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
			throw Exception('details');
		}

		// If the type is not found or is invalid
		if(!isset($details['__type__']) || !in_array($details['__type__'], $this->_VALID_TYPES)) {
			throw Exception('__type__ not in details');
		}

		// Store the type and remove it from the details
		$this->_type = $details['__type__'];
		unset($details['__type__']);

		// Init the value types
		$this->_regex = null;
		$this->_options = null;
		$this->_minimum = null;
		$this->_maximum = null;

		// If there's a regex string available
		if(isset($details['__regex__'])) {
			$this->regex($details['__regex__']);
			unset($details['__regex__']);
		}

		// Else if there's a list of options
		else if(isset($details['__options__'])) {
			$this->options($details['__options__']);
			unset($details['__options__']);
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

			if($bMin) unset($details['__minimum__']);
			if($bMax) unset($details['__maximum__']);
		}

		// Call the parent constructor
		parent::__construct($details, 'Node');
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
		if(value == null && $this->_optional) {
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
			if($value instanceof DateTime) {
				$value = $value.format('Y-m-d');
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
			if($value instanceof DateTime) {
				$value = $value.format('Y-m-d H:i:s');
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
			if($value instanceof DateTime) {
				$value = $value.format('H:i:s');
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
			throw Exception($this->_type . ' has not been added to .clean()');
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
	public function minmax($minimum = null, $maxium = null) {

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
					!preg_match($_typeToRegex[$this->_type], $minimum)) {
					throw Exception('__minimum__');
				}
			}

			// Else if the type is an int (unsigned, timestamp), or a string in
			// 	which the min/max are lengths
			else if(in_array($this->_type, array('int', 'string', 'timestamp', 'uint'))) {

				// If the value is not a valid int or long
				if(!is_int($minimum)) {

					// If it's a valid representation of an integer
					if(is_string($minimum) &&
						preg_match($_typeToRegex['int'], $minimum)) {

						// Convert it
						$minimum = intval($minimum, 0);
					}

					// Else, throw an error
					else {
						throw Exception('__minimum__');
					}

					// If the type is meant to be unsigned
					if(in_array($this->_type, array('string', 'timestamp', 'uint'))) {

						// And it's below zero
						if($minimum < 0) {
							throw Exception('__minimum__');
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
				if(!preg_match($_typeToRegex['decimal'], $minimum)) {
					throw Exception('__minimum__');
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
					!preg_match($_typeToRegex['price'], $minimum)) {
					throw Exception('__minimum__');
				}
			}

			// Else we can't have a minimum
			else {
				throw Exception('can not set __minimum__ for ' . $this->_type);
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
					!preg_match($_typeToRegex[$this->_type], $maximum)) {
					throw Exception('__maximum__');
				}
			}

			// Else if the type is an int (unsigned, timestamp), or a string in
			// 	which the min/max are lengths
			else if(in_array($this->_type, array('int', 'string', 'timestamp', 'uint'))) {

				// If the value is not a valid int or long
				if(!is_int($maximum)) {

					// If it's a valid representation of an integer
					if(!is_string($maximum) &&
						!preg_match($_typeToRegex['int'], $maximum)) {

						// Convert it
						$maximum = intval($maximum, 0);
					}

					// Else, throw an error
					else {
						throw Exception('__maximum__');
					}

					// If the type is meant to be unsigned
					if(in_array($this->_type, array('string', 'timestamp', 'uint'))) {

						// And it's below zero
						if($maximum < 0) {
							throw Exception('__maximum__');
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
				if(!preg_match($_typeToRegex['decimal'], $maximum)) {
					throw Exception('__maximum__');
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
					!preg_match($_typeToRegex['price'], $maximum)) {
					throw Exception('__maximum__');
				}
			}

			// Else we can't have a maximum
			else {
				throw Exception('can not set __maximum__ for ' . $this->_type);
			}

			// If we also have a minimum
			if($this->_minimum != null) {

				// If the type is an IP
				if($this->_type == 'ip') {

					// If the min is above the max, we have a problem
					if(_compare_ips($this->_minimum, $maximum) == 1) {
						throw Exception('__maximum__');
					}
				}

				// Else any other data type
				else {

					// If the min is above the max, we have a problem
					if($this->_minimum > $maximum) {
						throw Exception('__maximum__');
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
	public function options($opts = null) {

		// If opts aren't set, this is a getter
		if(options == null) {
			return $this->_options;
		}

		// If the options are not a list
		if(!is_array($options) || !isset($options[0])) {
			throw Exception('options');
		}

		// If the type is not one that can have options
		if(!in_array($this->_type, array(
				'base64', 'date', 'datetime', 'decimal', 'float',
				'int', 'ip', 'md5', 'price', 'string', 'time',
				'timestamp', 'uint', 'uuid'))) {
			throw Exception('can not set __options__ for ' . $this->_type);
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
				if(is_string($options[$i]) ||
					!preg_match($_typeToRegex[$this->_type], $options[$i])) {
					throw Exception('__options__[' . $i . ']');
				}
			}

			// Else if it's decimal
			else if($this->_type == 'decimal') {

				// If it's not a string, convert it
				if(!is_string($options[$i])) {
					$options[$i] = strval($options[$i]);
				}

				// If it doesn't fit the regex
				if(!preg_match($_typeToRegex['decimal'], $options[$i])) {
					throw Exception('__options__[' . $i . ']');
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
						throw Exception('__options__[' . $i . ']');
					}

					// Convert it
					$options[$i] = intval($options[$i], 0);
				}

				// If the type is unsigned and negative, throw an error
				if(in_array($this->_type, array('timestamp', 'uint')) && $options[$i] < 0) {
					throw Exception('__options__[' . $i . ']');
				}
			}

			// Else if it's a price
			else if($this->_type == 'price') {

				// If it's not a valid representation of a price
				if(!is_string($options[$i]) ||
					!preg_match($_typeToRegex['price'], $options[$i])) {
					throw Exception('__options__[' . $i . ']');
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
				throw Exception('can not set __options__ for ' . $this->_type);
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
			throw Exception('__regex__');
		}

		// Store the regex
		$this->_regex = $regex;
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

	public function valid($value, array $level = array()) {

	}
}
