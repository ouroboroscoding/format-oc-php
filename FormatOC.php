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
$_specialName = '/^' . _specialSyntax . '$/';
$_specialKey = '/^__' . _specialSyntax . '__$/';
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
 * @name _child
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
				return Parent($details)
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
		throw InvalidArgumentException('details');
	}
}

/**
 * Node interface
 *
 * All Node instances must be built off this set of methods
 */
interface _Node {
	public function clean($value);
	public function fromFile($filename, $override);
	public function toArray();
	public function toJSON();
	public function className();
	public function valid($value, $level);
}

/**
 * Base Node class
 *
 * Represents shared functionality amongst Nodes and Parents
 */
class _BaseNode implements _Node {

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
	 * @throws InvalidArgumentException
	 * @param array $details		Details describing the type of values allowed for the node
	 * @param string $_class		The class of the child
	 * @return _BaseNode
	 */
	public function __construct(array $details, /*string*/ $_class) {

		// If details is not an array
		if(!is_array($details)) {
			throw InvalidArgumentException('details in ' . $_class . ' must be an associative array');
		}

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
	 * @param string $filename		The filename to load
	 * @param bool $override		Optional, if set, values 'override' sections will be used
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
	 * @throws InvalidArgumentException
	 * @param string $name			The name of the value to either set or get
	 * @param mixed $value			The value to set, must be able to convert to JSON
	 * @return mixed | void
	 */
	public function special(/*string*/ $name, /*mixed*/ $value = null) {

		// Check the name is a string
		if(!is_string($name)) {
			throw InvalidArgumentException('name must be a string');
		}

		// Check the name is valid
		if(!preg_match($_specialName, $name)) {
			throw InvalidArgumentException('special name must match "' . _specialSyntax . '"');
		}

		// If the value is not set, this is a getter
		if($value == null) {

			// Return the value or null
			return (isset($this->_special[$name]) ? $this->_special[$name] : null;
		}

		// Else, this is a setter
		else {

			// If we can't convert the value to JSON
			if(json_encode($value) === false) {
				throw InvalidArgumentException('value can not be encoded to JSON');
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
	 * @throws InvalidArgumentException
	 * @param array $details		Details describing the type of values allowed for the node
	 * @return ArrayNode
	 */
	public function __construct(array $details) {

		// If details is not an array instance
		if(!is_array($details)) {
			throw InvalidArgumentException('details');
		}

		// If the array config is not found
		if(!isset($details['__array__'])) {
			throw InvalidArgumentException('details missing "__array__"');
		}

		// If __array__ is not an array
		if(!is_array($details['__array__'])) {
			$details['__array__'] = array(
				"type": $details['__array__']
			);
		}

		// If the type is missing
		if(!isset($details['__array__']['type'])) {
			$this->_type = 'unique';
		}

		// Or if the type is invalid
		else if(!in_array($details['__array__']['type'], self::$_VALID_ARRAY)) {
			$this->_type = 'unique';
			fwrite(STDERR, '"' . strval($details['__array__']['type']) . '" is not a valid type for __array__, assuming "unique"')
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
		else if(isset($details['__array__']['optional']) {
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
	 * @throws InvalidArgumentException
	 * @param uint $minimum			The minimum value to set
	 * @param uint $maximum			The maximum value to set
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
					throw InvalidArgumentException('minimum')
				}
			}

			// If it's below zero
			if($minimum < 0) {
				throw InvalidArgumentException('minimum')
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
					throw InvalidArgumentException('minimum');
				}
			}

			// It's below zero
			if($maximum < 0) {
				throw InvalidArgumentException('maximum');
			}

			// If we also have a minimum and the max is somehow below it
			if($this->_minimum && $maximum < $this->_minimum) {
				throw InvalidArgumentException('maximum');
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
	 * @throws InvalidArgumentException
	 * @param array $value			The value to clean
	 * @return array
	 */
	public function clean(array $value) {

		// If the value is null and it's optional, return as is
		if($value == null && $this->_optional) {
			return null;
		}

		// If the value is not an array
		if(!is_array($value)) {
			throw InvalidArgumentException('value');
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

		// Get the parents array, and the child nodes array, and them to the
		//	return
		$aRet = array_merge($aRet, parent::toArray(), $this->_node.toArray());

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
	 * @param array $value			The value to validate
	 * @return bool
	 */
	public function valid(array $value, array level = array()) {

		// Reset validation failures
		$this->validation_failures = []

		// If the value is null and it's optional, we're good
		if $value is None and $this->_optional:
			return true;

		// If the value isn't a list
		if(!is_array($value, list)) {
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
					continue
				}

				// Add the value to the array and continue
				lItems.append($value[i])
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
				$this->validation_failures[] array(implode('.', $level), 'exceeds maximum');
				$bRet = false;
			}
		}

		// Return whatever the result was
		return $bRet;
	}
}
