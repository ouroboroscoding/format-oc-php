<?php

use PHPUnit\Framework\TestCase;

require "FormatOC.php";

// The tests
class FormatTest extends TestCase {

	public function test_Array_Valid() {

		// Create an array that only allows uniques
		$a = new FormatOC\ArrayNode(array(
			"__array__" => "unique",
			"__type__" => "decimal"
		));

		// Check for true
		$this->assertTrue($a->valid(array('0.3','2.4','3.5','4.6')), '["0.3","2.4","3.5","4.6"] is not a valid unique decimal array: ' . print_r($a->validation_failures, true));
		$this->assertTrue($a->valid(array('0.1','0.11','0.111','0.1111')), '["0.1","0.11","0.111","0.1111"] is not a valid unique decimal array: ' . print_r($a->validation_failures, true));

		// Check for false
		$this->assertFalse($a->valid(array('2','2','3')), '["2","2","3"] is a valid unique decimal array');
		$this->assertTrue($a->validation_failures[0][0] == '[1]', 'fail name is not correct: ' . $a->validation_failures[0][0]);
		$this->assertTrue($a->validation_failures[0][1] == 'duplicate of [0]', 'fail value is not correct: ' . $a->validation_failures[0][1]);

		// Create an array that allows duplicates
		$a = new FormatOC\ArrayNode(array(
			"__array__" => "duplicates",
			"__type__" => "decimal"
		));

		// Check for true
		$this->assertTrue($a->valid(array('0.3','2.4','0.3','4.6')), '["0.3","2.4","0.3","4.6"] is not a valid decimal array');
		$this->assertTrue($a->valid(array('0.1','0.11','0.1','0.1111')), '["0.1","0.11","0.1","0.1111"] is not a valid decimal array');

		// Check for false
		$this->assertFalse($a->valid(array('Hello',2,3)), '["Hello",2,3] is a valid unique decimal array');
		$this->assertTrue($a->validation_failures[0][0] == '[0]', 'fail name is not correct: ' . strval($a->validation_failures[0][0]));
		$this->assertTrue($a->validation_failures[0][1] == 'failed regex (internal)', 'fail value is not correct: ' . strval($a->validation_failures[0][1]));
	}

	public function test_Hash_Hash_Valid() {

		// Create a hash of hashes
		$oHash = new FormatOC\HashNode(array(
			"__hash__" => "string",
			"__type__" => array(
				"__hash__" => "string",
				"__type__" => "uint"
			)
		));

		// Check for true
		$this->assertTrue($oHash->valid(array(
			"test" => array(
				"un" => 1,
				"deux" => 2,
				"trois" => 3
			),
			"this" => array(
				"one" => 1,
				"two" => 2,
				"three" => 3
			),
		)), "hash of hashes failed validation");

		// Check for false
		$this->assertFalse($oHash->valid(array(
			"test" => array(
				"un" => 1,
				"deux" => 2,
				"trois" => 3
			),
			"me" => 1
		)), "Invalid hash of hashes deemed valid");
	}

	public function test_Node_Clean() {

		// Create a basic any Node
		$oNode = new FormatOC\Node(array(
			"__type__" => "any"
		));

		// Check for true
		$this->assertTrue($oNode->clean(0) == 0, '0 does not equal 0');
		$this->assertTrue($oNode->clean(0.1) == 0.1, '0.1 does not equal 0.1');
		$this->assertTrue($oNode->clean('0') == '0', '"0" does not equal "0"');
		$this->assertTrue($oNode->clean(true) == true, 'true does not equal true');
		$this->assertTrue(json_encode($oNode->clean(array())) == json_encode(array()), 'array() does not equal array()');

		// Create a basic bool Node
		$oNode = new FormatOC\Node(array(
			"__type__" => "bool"
		));

		// Check for true
		$this->assertTrue($oNode->clean(true) == true, 'true does not equal true');
		$this->assertTrue($oNode->clean(false) == false, 'false does not equal false');
		$this->assertTrue($oNode->clean('1') == true, '"1" does not equal true');
		$this->assertTrue($oNode->clean('0') == false, '"0" does not equal false');
		$this->assertTrue($oNode->clean('Y') == true, '"Y" does not equal true');
		$this->assertTrue($oNode->clean('') == false, '"" does not equal false');
		$this->assertTrue($oNode->clean(1) == true, '1 does not equal true');
		$this->assertTrue($oNode->clean(0) == false, '0 does not equal false');
		$this->assertTrue($oNode->clean(0.1) == true, '0.1 does not equal true');
		$this->assertTrue($oNode->clean(0.0) == false, '0.0 does not equal false');

		// Create a basic date Node
		$oNode = new FormatOC\Node(array(
			"__type__" => "date"
		));

		// Make a DateTime
		$dt = new \DateTime();
		$dt->setDate(1981,5,2);
		$dt->setTime(0,0,0);

		// Check for true
		$this->assertTrue($oNode->clean('0000-00-00') == '0000-00-00', '"0000-00-00" does not equal "0000-00-00"');
		$this->assertTrue($oNode->clean('1981-05-02') == '1981-05-02', '"1981-05-02" does not equal "1981-05-02"');
		$this->assertTrue($oNode->clean($dt) == '1981-05-02', 'new DateTime(1981,5,2) does not equal "1981-05-02"');
		$dt->setTime(12,23,0);
		$this->assertTrue($oNode->clean($dt) == '1981-05-02', 'new DateTime(1981,5,2,12,23,0) does not equal "1981-05-02"');

		// Create a basic datetime Node
		$oNode = new FormatOC\Node(array(
			"__type__" => "datetime"
		));

		// Check for true
		$this->assertTrue($oNode->clean('0000-00-00 00:00:00') == '0000-00-00 00:00:00', '"0000-00-00 00:00:00" does not equal "0000-00-00 00:00:00"');
		$this->assertTrue($oNode->clean('1981-05-02 12:23:00') == '1981-05-02 12:23:00', '"1981-05-02 12:23:00" does not equal "1981-05-02 12:23:00"');
		$dt->setTime(0,0,0);
		$this->assertTrue($oNode->clean($dt) == '1981-05-02 00:00:00', 'new DateTime(1981,5,2) does not equal "1981-05-02 00:00:00"');
		$dt->setTime(12,23,0);
		$this->assertTrue($oNode->clean($dt) == '1981-05-02 12:23:00', 'new DateTime(1981,5,2,12,23,0) does not equal "1981-05-02 12:23:00"');

		// Create a basic decimal Node
		$oNode = new FormatOC\Node(array(
			"__type__" => "decimal"
		));

		$this->assertTrue($oNode->clean('0.0') == '0.0', '"0.0" does not equal "0.0"');
		$this->assertTrue($oNode->clean('3.14') == '3.14', '"3.14" does not equal "3.14"');
		$this->assertTrue($oNode->clean('-3.14') == '-3.14', '"-3.14" does not equal "-3.14"');
		$this->assertTrue($oNode->clean(3) == '3', '3 does not equal "3.0"');
		$this->assertTrue($oNode->clean('3') == '3', '"3" does not equal "3.0"');

		// Create a basic float Node
		$oNode = new FormatOC\Node(array(
			"__type__" => "float"
		));

		$this->assertTrue($oNode->clean(0.0) == 0.0, '0.0 does not equal 0.0');
		$this->assertTrue($oNode->clean(3.14) == 3.14, '3.14 does not equal 3.14');
		$this->assertTrue($oNode->clean(-3.14) == -3.14, '-3.14 does not equal -3.14');
		$this->assertTrue($oNode->clean('0.0') == 0.0, '"0.0" does not equal 0.0');
		$this->assertTrue($oNode->clean('3.14') == 3.14, '"3.14" does not equal 3.14');
		$this->assertTrue($oNode->clean('-3.14') == -3.14, '"-3.14" does not equal -3.14');
		$this->assertTrue($oNode->clean(3) == 3.0, '3 does not equal 3.0');
		$this->assertTrue($oNode->clean('3') == 3.0, '"3" does not equal 3.0');

		// Create a basic int Node
		$oNode = new FormatOC\Node(array(
			"__type__" => "int"
		));

		// Check for true
		$this->assertTrue($oNode->clean(0) == 0, '0 does not equal 0');
		$this->assertTrue($oNode->clean(1) == 1, '1 does not equal 1');
		$this->assertTrue($oNode->clean(-1) == -1, '-1 does not equal -1');
		$this->assertTrue($oNode->clean(3.14) == 3, '3.14 does not equal 3');
		$this->assertTrue($oNode->clean(3) == 3, '3 does not equal 3');
		$this->assertTrue($oNode->clean('3') == 3, '"3" does not equal 3');
		$this->assertTrue($oNode->clean('-3') == -3, '"-3" does not equal -3');

		// Create a basic ip Node
		$oNode = new FormatOC\Node(array(
			"__type__" => "ip"
		));

		// Check for true
		$this->assertTrue($oNode->clean('127.0.0.1') == '127.0.0.1', '"127.0.0.1" does not equal "127.0.0.1"');
		$this->assertTrue($oNode->clean('10.0.0.1') == '10.0.0.1', '"10.0.0.1" does not equal "10.0.0.1"');
		$this->assertTrue($oNode->clean('255.255.255.255') == '255.255.255.255', '"255.255.255.255" does not equal "255.255.255.255"');

		// Create a basic json Node
		$oNode = new FormatOC\Node(array(
			"__type__" => "json"
		));

		// Check for true
		$this->assertTrue($oNode->clean('{"hello":"there"}') == '{"hello":"there"}', '"{"hello":"there"}" does not equal "{"hello":"there"}"');
		$this->assertTrue($oNode->clean('"hello"') == '"hello"', '"hello" does not equal "hello"');
		$this->assertTrue($oNode->clean(array("Hello" => "there")) == '{"Hello":"there"}', '{"Hello":"there"} does not equal "{"Hello":"there"}"');
		$this->assertTrue($oNode->clean(array(1,2,34)) == '[1,2,34]', '[1,2,34] does not equal "[1,2,34]"');

		// Create a basic md5 Node
		$oNode = new FormatOC\Node(array(
			"__type__" => "md5"
		));

		// Check for true
		$this->assertTrue($oNode->clean('65a8e27d8879283831b664bd8b7f0ad4') == '65a8e27d8879283831b664bd8b7f0ad4', '"65a8e27d8879283831b664bd8b7f0ad4" does not equal "65a8e27d8879283831b664bd8b7f0ad4"');

		// Create a basic price Node
		$oNode = new FormatOC\Node(array(
			"__type__" => "price"
		));

		$this->assertTrue($oNode->clean(0.0) == '0.00', '0.0 does not equal "0.00"');
		$this->assertTrue($oNode->clean('0.0') == '0.00', '"0.0" does not equal "0.00"');
		$this->assertTrue($oNode->clean('3.1') == '3.10', '"3.1" does not equal "3.10"');
		$this->assertTrue($oNode->clean('-3.1') == '-3.10', '"-3.1" does not equal "-3.10"');
		$this->assertTrue($oNode->clean('3.14') == '3.14', '"3.14" does not equal "3.14"');
		$this->assertTrue($oNode->clean('-3.14') == '-3.14', '"-3.14" does not equal "-3.14"');
		$this->assertTrue($oNode->clean(3) == '3.00', '3 does not equal "3.00"');
		$this->assertTrue($oNode->clean('3') == '3.00', '"3" does not equal "3.00"');

		// Create a basic string Node
		$oNode = new FormatOC\Node(array(
			"__type__" => "string"
		));

		// Check for true
		$this->assertTrue($oNode->clean('127.0.0.1') == '127.0.0.1', '"127.0.0.1" does not equal "127.0.0.1"');
		$this->assertTrue($oNode->clean('10.0.0.1') == '10.0.0.1', '"10.0.0.1" does not equal "10.0.0.1"');
		$this->assertTrue($oNode->clean('255.255.255.255') == '255.255.255.255', '"255.255.255.255" does not equal "255.255.255.255"');
		$this->assertTrue($oNode->clean(0) == '0', '0 does not equal "0"');
		$this->assertTrue($oNode->clean(3.14) == '3.14', '3.14 does not equal "3.14"');

		// Create a basic time Node
		$oNode = new FormatOC\Node(array(
			"__type__" => "time"
		));

		// Check for true
		$this->assertTrue($oNode->clean('00:00:00') == '00:00:00', '"0000-00-00 00:00:00" does not equal "00:00:00"');
		$this->assertTrue($oNode->clean('12:23:00') == '12:23:00', '"1981-05-02 12:23:00" does not equal "12:23:00"');
		$dt->setTime(12,23,0);
		$this->assertTrue($oNode->clean($dt) == '12:23:00', 'new DateTime(1981,5,2,12,23,0) does not equal "12:23:00"');

		// Create a basic timestamp Node
		$oNode = new FormatOC\Node(array(
			"__type__" => "timestamp"
		));

		// Check for true
		$this->assertTrue($oNode->clean(0) == 0, '0 does not equal 0');
		$this->assertTrue($oNode->clean(1) == 1, '1 does not equal 1');
		$this->assertTrue($oNode->clean(3.14) == 3, '3.14 does not equal 3');
		$this->assertTrue($oNode->clean(3) == 3, '3 does not equal 3');
		$this->assertTrue($oNode->clean('3') == 3, '"3" does not equal 3');

		// Create a basic uint Node
		$oNode = new FormatOC\Node(array(
			"__type__" => "uint"
		));

		// Check for true
		$this->assertTrue($oNode->clean(0) == 0, '0 does not equal 0');
		$this->assertTrue($oNode->clean(1) == 1, '1 does not equal 1');
		$this->assertTrue($oNode->clean(3.14) == 3, '3.14 does not equal 3');
		$this->assertTrue($oNode->clean(3) == 3, '3 does not equal 3');
		$this->assertTrue($oNode->clean('3') == 3, '"3" does not equal 3');

		// Create a basic uuid Node
		$oNode = new FormatOC\Node(array(
			"__type__" => "uuid"
		));

		$this->assertTrue($oNode->clean('52cd4b20-ca32-4433-9516-0c8684ec57c2') == '52cd4b20-ca32-4433-9516-0c8684ec57c2', '"52cd4b20-ca32-4433-9516-0c8684ec57c2" does not equal "52cd4b20-ca32-4433-9516-0c8684ec57c2"');
		$this->assertTrue($oNode->clean('3b44c5ed-0fea-4478-9f1b-939ae6ec0721') == '3b44c5ed-0fea-4478-9f1b-939ae6ec0721', '"3b44c5ed-0fea-4478-9f1b-939ae6ec0721" does not equal "3b44c5ed-0fea-4478-9f1b-939ae6ec0721"');
		$this->assertTrue($oNode->clean('6432b16a-7e27-47cd-8360-82d82ac70078') == '6432b16a-7e27-47cd-8360-82d82ac70078', '"6432b16a-7e27-47cd-8360-82d82ac70078" does not equal "6432b16a-7e27-47cd-8360-82d82ac70078"');
	}

	public function test_Node_Valid_Basic() {

		// Make a DateTime
		$dt = new \DateTime();
		$dt->setDate(1981,5,2);
		$dt->setTime(0,0,0);

		// Create a new basic any Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'any'
		));

		// Check for true
		$this->assertTrue($oNode->valid(1), '1 is not a valid any');
		$this->assertTrue($oNode->valid(0), '0 is not a valid any');
		$this->assertTrue($oNode->valid(-1), '-1 is not a valid any');
		$this->assertTrue($oNode->valid('0'), '"0" is not a valid any');
		$this->assertTrue($oNode->valid('1'), '"1" is not a valid any');
		$this->assertTrue($oNode->valid('-1'), '"-1" is not a valid any');
		$this->assertTrue($oNode->valid('0xff'), '"0xff" is not a valid any');
		$this->assertTrue($oNode->valid('07'), '"07" is not a valid any');
		$this->assertTrue($oNode->valid('Hello'), '"Hello" is not a valid any');
		$this->assertTrue($oNode->valid(true), 'true is not a valid any');
		$this->assertTrue($oNode->valid(0.1), '0.1 is not a valid any');
		$this->assertTrue($oNode->valid('0.1'), '"0.1" is not a valid any');
		$this->assertTrue($oNode->valid('192.168.0.1'), '"192.168.0.1" is not a valid any');
		$this->assertTrue($oNode->valid('2016-03-05'), '"2016-03-05" is not a valid any');
		$this->assertTrue($oNode->valid('13:50:00'), '"13:50:00" is not a valid any');
		$this->assertTrue($oNode->valid('2016-03-05 13:50:00'), '"2016-03-05 13:50:00" is not a valid any');
		$this->assertTrue($oNode->valid(array()), 'array() is not a valid any');

		// Create a new basic base64 Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'base64'
		));

		// Check for True
		$this->assertTrue($oNode->valid('SGVsbG8sIHRoaXMgaXMgYSB0ZXN0IQ=='), '"SGVsbG8sIHRoaXMgaXMgYSB0ZXN0IQ==" is not a valid base64');
		$this->assertTrue($oNode->valid('WW8gWW8gWW8='), '"WW8gWW8gWW8=" is not a valid base64');
		$this->assertTrue($oNode->valid('RG92ZXRhaWwgaXMgdGhlIHNoaXQu'), '"RG92ZXRhaWwgaXMgdGhlIHNoaXQu" is not a valid base64');

		// Check for False
		$this->assertFalse($oNode->valid('Hello'), '"Hello" is a valid base64');
		$this->assertFalse($oNode->valid('WW8gWW8gWW8==='), '"WW8gWW8gWW8===" is a valid base64');
		$this->assertFalse($oNode->valid(''), '"" is a valid base64');

		// Create a new basic bool Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'bool'
		));

		// Check for true
		$this->assertTrue($oNode->valid(true), 'true is not a valid bool');
		$this->assertTrue($oNode->valid(false), 'false is not a valid bool');
		$this->assertTrue($oNode->valid(1), '1 is not a valid bool');
		$this->assertTrue($oNode->valid(0), '0 is not a valid bool');
		$aBoolStrings = array('true', 'true', 'TRUE', 't', 'T', '1', 'false', 'false', 'FALSE', 'f', 'F', '0');
		for($i = 0; $i < count($aBoolStrings); ++$i) {
			$this->assertTrue($oNode->valid($aBoolStrings[$i]), '"' . $aBoolStrings[$i] . '" is not a valid bool');
		}

		// Check for false
		$this->assertFalse($oNode->valid('Hello'), '"Hello" is a valid bool');
		$this->assertFalse($oNode->valid(2), '2 is a valid bool');
		$this->assertFalse($oNode->valid(1.2), '1.2 is a valid bool');
		$this->assertFalse($oNode->valid('192.168.0.1'), '"192.168.0.1" is a valid bool');
		$this->assertFalse($oNode->valid('2016-03-05'), '"2016-03-05" is a valid bool');
		$this->assertFalse($oNode->valid('13:50:00'), '"13:50:00" is a valid bool');
		$this->assertFalse($oNode->valid('2016-03-05 13:50:00'), '"2016-03-05 13:50:00" is a valid bool');
		$this->assertFalse($oNode->valid(array()), 'array() is a valid bool');

		// Create a new basic date Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'date'
		));

		// Check for true
		$this->assertTrue($oNode->valid('2016-03-05'), '"2016-03-05" is not a valid date');
		$this->assertTrue($oNode->valid('2020-12-25'), '"2020-12-25" is not a valid date');
		$this->assertTrue($oNode->valid('1970-01-01'), '"1970-01-01" is not a valid date');
		$this->assertTrue($oNode->valid($dt), 'new DateTime(1981,5,2) is not a valid date');
		$dt->setTime(12,23,0);
		$this->assertTrue($oNode->valid($dt), 'new DateTime(1981,5,2,12,23,0) is not a valid date');

		// Check for false
		$this->assertFalse($oNode->valid('70-01-01'), '"70-01-01" is a valid date');
		$this->assertFalse($oNode->valid('10000-01-01'), '"10000-01-01" is a valid date');
		$this->assertFalse($oNode->valid('1970-00-01'), '"1970-00-01" is a valid date');
		$this->assertFalse($oNode->valid('2000-12-00'), '"2000-12-00" is a valid date');
		$this->assertFalse($oNode->valid('2000-12-32'), '"2000-12-32" is a valid date');
		$this->assertFalse($oNode->valid('2000-13-10'), '"2000-13-10" is a valid date');
		$this->assertFalse($oNode->valid('Hello'), '"Hello" is a valid date');
		$this->assertFalse($oNode->valid(true), 'true is a valid date');
		$this->assertFalse($oNode->valid(2), '2 is a valid date');
		$this->assertFalse($oNode->valid(1.2), '1.2 is a valid date');
		$this->assertFalse($oNode->valid('192.168.0.1'), '"192.168.0.1" is a valid date');
		$this->assertFalse($oNode->valid('13:50:00'), '"13:50:00" is a valid date');
		$this->assertFalse($oNode->valid('2016-03-05 13:50:00'), '"2016-03-05 13:50:00" is a valid date');
		$this->assertFalse($oNode->valid(array()), 'array() is a valid date');

		// Create a new basic datetime Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'datetime'
		));

		// Check for true
		$this->assertTrue($oNode->valid('2016-03-05 10:04:00'), '"10:04:00" is not a valid datetime');
		$this->assertTrue($oNode->valid('2020-12-25 00:00:00'), '"00:00:00" is not a valid datetime');
		$this->assertTrue($oNode->valid('2020-12-25 12:23:34'), '"12:23:34" is not a valid datetime');
		$this->assertTrue($oNode->valid('1970-01-01 02:56:12'), '"02:56:12" is not a valid datetime');
		$dt->setTime(0,0,0);
		$this->assertTrue($oNode->valid($dt), 'new DateTime(1981,5,2) is not a valid datetime');
		$dt->setTime(12,23,0);
		$this->assertTrue($oNode->valid($dt), 'new DateTime(1981,5,2,12,23,0) is not a valid datetime');

		// Check for false
		$this->assertFalse($oNode->valid('2016-03-05 1:00:00'), '"2016-03-05 1:00:00" is a valid datetime');
		$this->assertFalse($oNode->valid('2016-03-05 100:01:00'), '"2016-03-05 100:01:00" is a valid datetime');
		$this->assertFalse($oNode->valid('2016-03-05 24:00:00'), '"2016-03-05 24:00:00" is a valid datetime');
		$this->assertFalse($oNode->valid('2016-03-05 00:0:00'), '"2016-03-05 00:0:00" is a valid datetime');
		$this->assertFalse($oNode->valid('2016-03-05 00:00:0'), '"2016-03-05 00:00:0" is a valid datetime');
		$this->assertFalse($oNode->valid('2016-03-05 23:60:00'), '"2016-03-05 23:60:00" is a valid datetime');
		$this->assertFalse($oNode->valid('2016-03-05 23:00:60'), '"2016-03-05 23:00:60" is a valid datetime');
		$this->assertFalse($oNode->valid('70-01-01 00:00:00'), '"70-01-01 00:00:00" is a valid datetime');
		$this->assertFalse($oNode->valid('10000-01-01 00:00:00'), '"10000-01-01 00:00:00" is a valid datetime');
		$this->assertFalse($oNode->valid('1970-00-01 00:00:00'), '"1970-00-01 00:00:00" is a valid datetime');
		$this->assertFalse($oNode->valid('2000-12-00 00:00:00'), '"2000-12-00 00:00:00" is a valid datetime');
		$this->assertFalse($oNode->valid('2000-12-32 00:00:00'), '"2000-12-00 00:00:00" is a valid datetime');
		$this->assertFalse($oNode->valid('2000-13-10 00:00:00'), '"2000-12-00 00:00:00" is a valid datetime');
		$this->assertFalse($oNode->valid('Hello'), '"Hello" is a valid datetime');
		$this->assertFalse($oNode->valid(true), 'true is a valid datetime');
		$this->assertFalse($oNode->valid(2), '2 is a valid datetime');
		$this->assertFalse($oNode->valid(1.2), '1.2 is a valid datetime');
		$this->assertFalse($oNode->valid('192.168.0.1'), '"192.168.0.1" is a valid datetime');
		$this->assertFalse($oNode->valid('2016-03-05'), '"2016-03-05" is a valid datetime');
		$this->assertFalse($oNode->valid('13:50:00'), '"13:50:00" is a valid datetime');
		$this->assertFalse($oNode->valid(array()), 'array() is a valid datetime');

		// Create a new basic decimal Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'decimal'
		));

		// Check for true
		$this->assertTrue($oNode->valid('1.0'), '"0" is not a valid decimal');
		$this->assertTrue($oNode->valid('1.1'), '"0" is not a valid decimal');
		$this->assertTrue($oNode->valid('-0.1'), '"0" is not a valid decimal');
		$this->assertTrue($oNode->valid('0'), '"0" is not a valid decimal');
		$this->assertTrue($oNode->valid('1'), '"1" is not a valid decimal');
		$this->assertTrue($oNode->valid('-1'), '"-1" is not a valid decimal');

		// Check for false
		$this->assertFalse($oNode->valid('Hello'), '"Hello" is a valid decimal');
		$this->assertFalse($oNode->valid(true), 'true is a valid decimal');
		$this->assertFalse($oNode->valid('192.168.0.1'), '"192.168.0.1" is a valid decimal');
		$this->assertFalse($oNode->valid('2016-03-05'), '"2016-03-05" is a valid decimal');
		$this->assertFalse($oNode->valid('13:50:00'), '"13:50:00" is a valid decimal');
		$this->assertFalse($oNode->valid('2016-03-05 13:50:00'), '"2016-03-05 13:50:00" is a valid decimal');
		$this->assertFalse($oNode->valid(array()), 'array() is a valid decimal');

		// Create a new basic float Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'float'
		));

		// Check for true
		$this->assertTrue($oNode->valid(1.0), '1.0 is not a valid float');
		$this->assertTrue($oNode->valid(0.0), '0.0 is not a valid float');
		$this->assertTrue($oNode->valid(-1.0), '-1.0 is not a valid float');
		$this->assertTrue($oNode->valid('1.0'), '"1.0" is not a valid float');
		$this->assertTrue($oNode->valid('0.0'), '"0.0" is not a valid float');
		$this->assertTrue($oNode->valid('-1.0'), '"-1.0" is not a valid float');
		$this->assertTrue($oNode->valid(1), '1 is not a valid float');
		$this->assertTrue($oNode->valid(0), '0 is not a valid float');
		$this->assertTrue($oNode->valid(-1), '-1 is not a valid float');
		$this->assertTrue($oNode->valid('0'), '"0" is not a valid float');
		$this->assertTrue($oNode->valid('1'), '"1" is not a valid float');
		$this->assertTrue($oNode->valid('-1'), '"-1" is not a valid float');

		// Check for false
		$this->assertFalse($oNode->valid('Hello'), '"Hello" is a valid float');
		$this->assertFalse($oNode->valid(true), 'true is a valid float');
		$this->assertFalse($oNode->valid('0xff'), '"0xff" is a valid float');
		$this->assertFalse($oNode->valid('192.168.0.1'), '"192.168.0.1" is a valid float');
		$this->assertFalse($oNode->valid('2016-03-05'), '"2016-03-05" is a valid float');
		$this->assertFalse($oNode->valid('13:50:00'), '"13:50:00" is a valid float');
		$this->assertFalse($oNode->valid('2016-03-05 13:50:00'), '"2016-03-05 13:50:00" is a valid float');
		$this->assertFalse($oNode->valid(array()), 'array() is a valid float');

		// Create a new basic int Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'int'
		));

		// Check for true
		$this->assertTrue($oNode->valid(1), '1 is not a valid int');
		$this->assertTrue($oNode->valid(0), '0 is not a valid int');
		$this->assertTrue($oNode->valid(-1), '-1 is not a valid int');
		$this->assertTrue($oNode->valid('0'), '"0" is not a valid int');
		$this->assertTrue($oNode->valid('1'), '"1" is not a valid int');
		$this->assertTrue($oNode->valid('-1'), '"-1" is not a valid int');
		$this->assertTrue($oNode->valid('0xff'), '"0xff" is not a valid int');
		$this->assertTrue($oNode->valid('07'), '"07" is not a valid int');

		// Check for false
		$this->assertFalse($oNode->valid('Hello'), '"Hello" is a valid int');
		$this->assertFalse($oNode->valid(true), 'true is a valid int');
		$this->assertFalse($oNode->valid(0.1), '0.1 is a valid int');
		$this->assertFalse($oNode->valid('192.168.0.1'), '"192.168.0.1" is a valid int');
		$this->assertFalse($oNode->valid('2016-03-05'), '"2016-03-05" is a valid int');
		$this->assertFalse($oNode->valid('13:50:00'), '"13:50:00" is a valid int');
		$this->assertFalse($oNode->valid('2016-03-05 13:50:00'), '"2016-03-05 13:50:00" is a valid int');
		$this->assertFalse($oNode->valid(array()), 'array() is a valid int');

		// Create a new basic IP Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'ip'
		));

		// Check for true
		$this->assertTrue($oNode->valid('192.168.0.1'), '"192.168.0.1" is not a valid ip');
		$this->assertTrue($oNode->valid('10.13.13.1'), '"10.13.13.1" is not a valid ip');
		$this->assertTrue($oNode->valid('255.255.255.255'), '"255.255.255.255" is not a valid ip');
		$this->assertTrue($oNode->valid('8.8.8.8'), '"8.8.8.8" is not a valid ip');
		$this->assertTrue($oNode->valid('66.36.159.171'), '"66.36.159.171" is not a valid ip');
		$this->assertTrue($oNode->valid('255.255.255.0'), '"255.255.255.0" is not a valid ip');

		// Check for false
		$this->assertFalse($oNode->valid('Hello'), '"Hello" is a valid ip');
		$this->assertFalse($oNode->valid(true), 'true is a valid ip');
		$this->assertFalse($oNode->valid(0), '0 is a valid ip');
		$this->assertFalse($oNode->valid(0.1), '0.1 is a valid ip');
		$this->assertFalse($oNode->valid('2016-03-05'), '"2016-03-05" is a valid ip');
		$this->assertFalse($oNode->valid('13:50:00'), '"13:50:00" is a valid ip');
		$this->assertFalse($oNode->valid('2016-03-05 13:50:00'), '"2016-03-05 13:50:00" is a valid ip');
		$this->assertFalse($oNode->valid(array()), 'array() is a valid ip');

		// Create a new basic JSON Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'json'
		));

		// Check for true
		$this->assertTrue($oNode->valid('{"hello":"there","my":1,"true":3.14}'), '{"hello":"there","my":1,"true":3.14} is not valid json');
		$this->assertTrue($oNode->valid('{"hello":[1,2,34],"my":1,"true":true}'), '{"hello":[1,2,34],"my":1,"true":true} is not valid json');
		$this->assertTrue($oNode->valid('["a","b","c","d"]'), '["a","b","c","d"] is not valid json');
		$this->assertTrue($oNode->valid('"Hello"'), '"Hello" is not valid json');
		$this->assertTrue($oNode->valid('true'), 'true is not valid json');
		$this->assertTrue($oNode->valid(1), '1 is not valid json');
		$this->assertTrue($oNode->valid(0), '0 is not valid json');
		$this->assertTrue($oNode->valid(-1), '-1 is not valid json');
		$this->assertTrue($oNode->valid('0'), '"0" is not valid json');
		$this->assertTrue($oNode->valid('1'), '"1" is not valid json');
		$this->assertTrue($oNode->valid('-1'), '"-1" is not valid json');
		$this->assertTrue($oNode->valid(true), 'true is not valid json');
		$this->assertTrue($oNode->valid(0.1), '0.1 is not valid json');
		$this->assertTrue($oNode->valid(array()), 'array() is not valid json');

		// Check for false
		$this->assertFalse($oNode->valid('{\'hello\':\'there\'}'), '{\'hello\':\'there\'} is valid json');
		$this->assertFalse($oNode->valid('{hello:[1,2,34]}'), '{hello:[1,2,34]} is valid json');
		$this->assertFalse($oNode->valid('"a","b","c","d"'), '"a","b","c","d" is valid json');
		$this->assertFalse($oNode->valid('Hello'), '"Hello" is valid json');
		$this->assertFalse($oNode->valid('192.168.0.1'), '"192.168.0.1" is valid json');
		$this->assertFalse($oNode->valid('2016-03-05'), '"2016-03-05" is valid json');
		$this->assertFalse($oNode->valid('13:50:00'), '"13:50:00" is valid json');
		$this->assertFalse($oNode->valid('2016-03-05 13:50:00'), '"2016-03-05 13:50:00" is valid json');

		// Create a new basic md5 Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'md5'
		));

		// Check for true
		$this->assertTrue($oNode->valid('7b967af699a0a18b1f2bdc9704537a3e'), '"7b967af699a0a18b1f2bdc9704537a3e" is not a valid md5');
		$this->assertTrue($oNode->valid('889ffd8cc409445187c4258d138192b6'), '"889ffd8cc409445187c4258d138192b6" is not a valid md5');
		$this->assertTrue($oNode->valid('49c0d2aef0ab2634b0051544cdbf2415'), '"49c0d2aef0ab2634b0051544cdbf2415" is not a valid md5');
		$this->assertTrue($oNode->valid('65a8e27d8879283831b664bd8b7f0ad4'), '"65a8e27d8879283831b664bd8b7f0ad4" is not a valid md5');
		$this->assertTrue($oNode->valid('746b975324b133ceb2e211af41c049e8'), '"746b975324b133ceb2e211af41c049e8" is not a valid md5');

		// Check for false
		$this->assertFalse($oNode->valid('Hello'), '"Hello" is a valid md5');
		$this->assertFalse($oNode->valid(true), '"Hello" is a valid md5');
		$this->assertFalse($oNode->valid(0), '0 is a valid md5');
		$this->assertFalse($oNode->valid(0.1), '0.1 is a valid md5');
		$this->assertFalse($oNode->valid('192.168.0.1'), '"192.168.0.1" is a valid md5');
		$this->assertFalse($oNode->valid('2016-03-05'), '"2016-03-05" is a valid md5');
		$this->assertFalse($oNode->valid('13:50:00'), '"13:50:00" is a valid md5');
		$this->assertFalse($oNode->valid('2016-03-05 13:50:00'), '"2016-03-05 13:50:00" is a valid md5');
		$this->assertFalse($oNode->valid(array()), 'array() is a valid md5');

		// Create a new basic price Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'price'
		));

		// Check for true
		$this->assertTrue($oNode->valid(1), '1 is not a valid price');
		$this->assertTrue($oNode->valid(0), '0 is not a valid price');
		$this->assertTrue($oNode->valid(-1), '-1 is not a valid price');
		$this->assertTrue($oNode->valid('1.0'), '"1.0" is not a valid price');
		$this->assertTrue($oNode->valid('1.1'), '"1.1" is not a valid price');
		$this->assertTrue($oNode->valid('-0.1'), '"-0.1" is not a valid price');
		$this->assertTrue($oNode->valid('0'), '"0" is not a valid price');
		$this->assertTrue($oNode->valid('1'), '"1" is not a valid price');
		$this->assertTrue($oNode->valid('-1'), '"-1" is not a valid price');

		// Check for false
		$this->assertFalse($oNode->valid(1.234), '1.234 is a valid price');
		$this->assertFalse($oNode->valid('0.234'), '"0.234" is a valid price');
		$this->assertFalse($oNode->valid('Hello'), '"Hello" is a valid price');
		$this->assertFalse($oNode->valid(true), 'true is a valid price');
		$this->assertFalse($oNode->valid('192.168.0.1'), '"192.168.0.1" is a valid price');
		$this->assertFalse($oNode->valid('2016-03-05'), '"2016-03-05" is a valid price');
		$this->assertFalse($oNode->valid('13:50:00'), '"13:50:00" is a valid price');
		$this->assertFalse($oNode->valid('2016-03-05 13:50:00'), '"2016-03-05 13:50:00" is a valid price');
		$this->assertFalse($oNode->valid(array()), 'array() is a valid price');

		// Create a new basic string Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'string'
		));

		// Check for true
		$this->assertTrue($oNode->valid('Hello, World!'), '"Hello, World!" is not a valid string');
		$this->assertTrue($oNode->valid('0000000'), '"0000000" is not a valid string');
		$this->assertTrue($oNode->valid('       '), '"       " is not a valid string');
		$this->assertTrue($oNode->valid('Why\nShould\nThis\nWork\n?'), '"Why\nShould\nThis\nWork\n?" is not a valid string');
		$this->assertTrue($oNode->valid('192.168.0.1'), '"192.168.0.1" is not a valid string');
		$this->assertTrue($oNode->valid('2016-03-05'), '"2016-03-05" is not a valid string');
		$this->assertTrue($oNode->valid('13:50:00'), '"13:50:00" is not a valid string');
		$this->assertTrue($oNode->valid('2016-03-05 13:50:00'), '"2016-03-05 13:50:00" is not a valid string');

		// Check for false
		$this->assertFalse($oNode->valid(true), '"Hello" is a valid md5');
		$this->assertFalse($oNode->valid(0), '"Hello" is a valid md5');
		$this->assertFalse($oNode->valid(0.1), '0.1 is a valid md5');
		$this->assertFalse($oNode->valid(array()), 'array() is a valid md5');

		// Create a new basic time Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'time'
		));

		// Check for true
		$this->assertTrue($oNode->valid('10:04:00'), '"10:04:00" is not a valid time');
		$this->assertTrue($oNode->valid('00:00:00'), '"00:00:00" is not a valid time');
		$this->assertTrue($oNode->valid('12:23:34'), '"12:23:34" is not a valid time');
		$this->assertTrue($oNode->valid('02:56:12'), '"02:56:12" is not a valid time');
		$this->assertTrue($oNode->valid($dt), 'new DateTime(1981,5,2,12,23,0) is not a valid time');

		// Check for false
		$this->assertFalse($oNode->valid('1:00:00'), '"1:00:00" is a valid time');
		$this->assertFalse($oNode->valid('100:01:00'), '"100:01:00" is a valid time');
		$this->assertFalse($oNode->valid('24:00:00'), '"24:00:00" is a valid time');
		$this->assertFalse($oNode->valid('00:0:00'), '"00:0:00" is a valid time');
		$this->assertFalse($oNode->valid('00:00:0'), '"00:00:0" is a valid time');
		$this->assertFalse($oNode->valid('23:60:00'), '"23:60:00" is a valid time');
		$this->assertFalse($oNode->valid('23:00:60'), '"23:00:60" is a valid time');
		$this->assertFalse($oNode->valid('Hello'), '"Hello" is a valid time');
		$this->assertFalse($oNode->valid(true), 'true is a valid time');
		$this->assertFalse($oNode->valid(2), '2 is a valid time');
		$this->assertFalse($oNode->valid(1.2), '1.2 is a valid time');
		$this->assertFalse($oNode->valid('192.168.0.1'), '"192.168.0.1" is a valid time');
		$this->assertFalse($oNode->valid('2016-03-05'), '"2016-03-05" is a valid time');
		$this->assertFalse($oNode->valid('2016-03-05 13:50:00'), '"2016-03-05 13:50:00" is a valid time');
		$this->assertFalse($oNode->valid(array()), 'array() is a valid time');

		// Create a new basic timestamp Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'timestamp'
		));

		// Check for true
		$this->assertTrue($oNode->valid(1), '1 is not a valid timestamp');
		$this->assertTrue($oNode->valid(0), '0 is not a valid timestamp');

		// Check for false
		$this->assertFalse($oNode->valid(-1), '-1 is a valid timestamp');
		$this->assertFalse($oNode->valid('-1'), '"-1" is a valid timestamp');

		// Create a new basic unsigned int Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'uint'
		));

		// Check for true
		$this->assertTrue($oNode->valid(1), '1 is not a valid unsigned int');
		$this->assertTrue($oNode->valid(0), '0 is not a valid unsigned int');

		// Check for false
		$this->assertFalse($oNode->valid(-1), '-1 is a valid unsigned int');
		$this->assertFalse($oNode->valid('-1'), '"-1" is a valid unsigned int');

		// Create a new basic uuid Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'uuid'
		));

		// Check for true
		$this->assertTrue($oNode->valid('52cd4b20-ca32-4433-9516-0c8684ec57c2'), '"52cd4b20-ca32-4433-9516-0c8684ec57c2" is not a valid uuid');
		$this->assertTrue($oNode->valid('3b44c5ed-0fea-4478-9f1b-939ae6ec0721'), '"3b44c5ed-0fea-4478-9f1b-939ae6ec0721" is not a valid uuid');
		$this->assertTrue($oNode->valid('6432b16a-7e27-47cd-8360-82d82ac70078'), '"6432b16a-7e27-47cd-8360-82d82ac70078" is not a valid uuid');

		// Check for false
		$this->assertFalse($oNode->valid('Hello'), '"Hello" is a valid uuid');
		$this->assertFalse($oNode->valid(true), '"Hello" is a valid uuid');
		$this->assertFalse($oNode->valid(0), '0 is a valid uuid');
		$this->assertFalse($oNode->valid(0.1), '0.1 is a valid uuid');
		$this->assertFalse($oNode->valid('192.168.0.1'), '"192.168.0.1" is a valid uuid');
		$this->assertFalse($oNode->valid('2016-03-05'), '"2016-03-05" is a valid uuid');
		$this->assertFalse($oNode->valid('13:50:00'), '"13:50:00" is a valid uuid');
		$this->assertFalse($oNode->valid('2016-03-05 13:50:00'), '"2016-03-05 13:50:00" is a valid uuid');
		$this->assertFalse($oNode->valid(array()), 'array() is a valid uuid');
	}

	public function test_Node_Valid_MinMax() {

		// Create a new minmax date Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'date',
			'__minimum__' => '2016-01-01',
			'__maximum__' => '2016-12-31'
		));

		// Check for True
		$this->assertTrue($oNode->valid('2016-01-01'), '"2016-01-01" is not between "2016-01-01" and "2016-12-31"');
		$this->assertTrue($oNode->valid('2016-05-02'), '"2016-05-02" is not between "2016-01-01" and "2016-12-31"');
		$this->assertTrue($oNode->valid('2016-10-05'), '"2016-10-05" is not between "2016-01-01" and "2016-12-31"');
		$this->assertTrue($oNode->valid('2016-12-31'), '"2016-12-31" is not between "2016-01-01" and "2016-12-31"');

		// Check for False
		$this->assertFalse($oNode->valid('2015-12-31'), '"2015-12-31" is between "2016-01-01" and "2016-12-31"');
		$this->assertFalse($oNode->valid('2017-01-01'), '"2017-01-01" is between "2016-01-01" and "2016-12-31"');
		$this->assertFalse($oNode->valid('3010-01-01'), '"3010-01-01" is between "2016-01-01" and "2016-12-31"');
		$this->assertFalse($oNode->valid('1970-01-01'), '"1970-01-01" is between "2016-01-01" and "2016-12-31"');

		// Create a new minmax datetime Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'datetime',
			'__minimum__' => '2016-01-01 10:00:00',
			'__maximum__' => '2016-12-31 12:00:00'
		));

		// Check for True
		$this->assertTrue($oNode->valid('2016-01-01 12:00:00'), '"2016-01-01 12:00:00" is not between "2016-01-01 10:00:00" and "2016-12-31 12:00:00"');
		$this->assertTrue($oNode->valid('2016-05-02 12:23:34'), '"2016-05-02 12:23:34" is not between "2016-01-01 10:00:00" and "2016-12-31 12:00:00"');
		$this->assertTrue($oNode->valid('2016-10-05 09:12:23'), '"2016-10-05 09:12:23" is not between "2016-01-01 10:00:00" and "2016-12-31 12:00:00"');
		$this->assertTrue($oNode->valid('2016-12-31 10:00:00'), '"2016-12-31 10:00:00" is not between "2016-01-01 10:00:00" and "2016-12-31 12:00:00"');

		// Check for False
		$this->assertFalse($oNode->valid('2016-12-31 12:00:01'), '"2015-12-31 12:00:01" is between "2016-01-01 10:00:00" and "2016-12-31 12:00:00"');
		$this->assertFalse($oNode->valid('2017-01-01 00:00:00'), '"2017-01-01 00:00:00" is between "2016-01-01 10:00:00" and "2016-12-31 12:00:00"');
		$this->assertFalse($oNode->valid('3010-01-01 00:00:00'), '"3010-01-01 00:00:00" is between "2016-01-01 10:00:00" and "2016-12-31 12:00:00"');
		$this->assertFalse($oNode->valid('2016-01-01 09:59:59'), '"1970-01-01 09:59:59" is between "2016-01-01 10:00:00" and "2016-12-31 12:00:00"');

		// Create a new minmax decimal Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'decimal',
			'__minimum__' => '-10.0',
			'__maximum__' => '10.0'
		));

		// Check for True
		$this->assertTrue($oNode->valid('-10'), '-10 is not between -10.0 and 10.0');
		$this->assertTrue($oNode->valid('-5.61'), '-5.61 is not between -10.0 and 10.0');
		$this->assertTrue($oNode->valid('0.1'), '0.1 is not between -10.0 and 10.0');
		$this->assertTrue($oNode->valid('6.20982'), '6.20982 is not between -10.0 and 10.0');

		// Check for False
		$this->assertFalse($oNode->valid('-10.00001'), '-10.00001 is between -10.0 and 10.0');
		$this->assertFalse($oNode->valid('-2000.01'), '-2000.01 is between -10.0 and 10.0');
		$this->assertFalse($oNode->valid('13.314'), '13 is between -10.0 and 10.0');
		$this->assertFalse($oNode->valid('11'), '11 is between -10.0 and 10.0');

		// Create a new minmax int Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'int',
			'__minimum__' => '-10',
			'__maximum__' => 10
		));

		// Check for True
		$this->assertTrue($oNode->valid(-10), '-10 is not between -10 and 10');
		$this->assertTrue($oNode->valid(-5), '-5 is not between -10 and 10');
		$this->assertTrue($oNode->valid(0), '0 is not between -10 and 10');
		$this->assertTrue($oNode->valid(6), '6 is not between -10 and 10');

		// Check for False
		$this->assertFalse($oNode->valid(-11), '-11 is between -10 and 10');
		$this->assertFalse($oNode->valid(-2000), '-2000 is between -10 and 10');
		$this->assertFalse($oNode->valid(13), '13 is between -10 and 10');
		$this->assertFalse($oNode->valid(11), '11 is between -10 and 10');

		// Create a new minmax ip Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'ip',
			'__minimum__' => '192.168.0.1',
			'__maximum__' => '192.168.1.1'
		));

		// Check for True
		$this->assertTrue($oNode->valid('192.168.1.0'), '"192.168.1.0" is not between "192.168.0.1" and "192.168.1.1"');
		$this->assertTrue($oNode->valid('192.168.0.1'), '"192.168.0.1" is not between "192.168.0.1" and "192.168.1.1"');
		$this->assertTrue($oNode->valid('192.168.1.1'), '"192.168.1.1" is not between "192.168.0.1" and "192.168.1.1"');
		$this->assertTrue($oNode->valid('192.168.0.246'), '"192.168.0.246" is not between "192.168.0.1" and "192.168.1.1"');
		$this->assertTrue($oNode->valid('192.168.0.13'), '"192.168.0.13" is not between "192.168.0.1" and "192.168.1.1"');

		// Check for False
		$this->assertFalse($oNode->valid('192.169.0.1'), '"192.169.0.1" is between "192.168.0.1" and "192.168.1.1"');
		$this->assertFalse($oNode->valid('193.168.0.1'), '"193.168.0.1" is between "192.168.0.1" and "192.168.1.1"');
		$this->assertFalse($oNode->valid('192.0.0.1'), '"192.0.0.1" is between "192.168.0.1" and "192.168.1.1"');

		// Create a new minmax string Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'string',
			'__minimum__' => 3,
			'__maximum__' => 12
		));

		// Check for True
		$this->assertTrue($oNode->valid('hello'), 'the length of "hello" is not between 3 and 12 characters');
		$this->assertTrue($oNode->valid('1234'), 'the length of "1234" is not between 3 and 12 characters');
		$this->assertTrue($oNode->valid('Wonderful'), 'the length of "Wonderful" is not between 3 and 12 characters');
		$this->assertTrue($oNode->valid('            '), 'the length of "            " is not between 3 and 12 characters');

		// Check for False
		$this->assertFalse($oNode->valid(''), 'the length of "" is between 3 and 12 characters');
		$this->assertFalse($oNode->valid('me'), 'the length of "me" is between 3 and 12 characters');
		$this->assertFalse($oNode->valid('Hello, World!'), 'the length of "Hello, World!" is between 3 and 12 characters');
		$this->assertFalse($oNode->valid('             '), 'the length of "             " is between 3 and 12 characters');


		// Create a new minmax time Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'time',
			'__minimum__' => '10:00:00',
			'__maximum__' => '12:00:00'
		));

		// Check for True
		$this->assertTrue($oNode->valid('12:00:00'), '"12:00:00" is not between "10:00:00" and "12:00:00"');
		$this->assertTrue($oNode->valid('11:23:34'), '"11:23:34" is not between "10:00:00" and "12:00:00"');
		$this->assertTrue($oNode->valid('10:12:23'), '"10:12:23" is not between "10:00:00" and "12:00:00"');
		$this->assertTrue($oNode->valid('10:00:00'), '"10:00:00" is not between "10:00:00" and "12:00:00"');

		// Check for False
		$this->assertFalse($oNode->valid('12:00:01'), '"12:00:01" is between "10:00:00" and "12:00:00"');
		$this->assertFalse($oNode->valid('00:00:00'), '"00:00:00" is between "10:00:00" and "12:00:00"');
		$this->assertFalse($oNode->valid('23:59:59'), '"23:59:59" is between "10:00:00" and "12:00:00"');
		$this->assertFalse($oNode->valid('09:59:59'), '"09:59:59" is between "10:00:00" and "12:00:00"');

		// Create a new minmax timestamp Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'timestamp',
			'__minimum__' => '10',
			'__maximum__' => 10000
		));

		// Check for True
		$this->assertTrue($oNode->valid(10), '10 is not between 10 and 10000');
		$this->assertTrue($oNode->valid(100), '100 is not between 10 and 10000');
		$this->assertTrue($oNode->valid(1000), '1000 is not between 10 and 10000');
		$this->assertTrue($oNode->valid(9999), '9999 is not between 10 and 10000');

		// Check for False
		$this->assertFalse($oNode->valid(-11), '-11 is not between 10 and 10000');
		$this->assertFalse($oNode->valid(-2000), '-2000 is not between 10 and 10000');
		$this->assertFalse($oNode->valid(10013), '10013 is not between 10 and 10000');
		$this->assertFalse($oNode->valid(9), '9 is not between 10 and 10000');

		// Create a new minmax uint Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'uint',
			'__minimum__' => '10',
			'__maximum__' => 10000
		));

		// Check for True
		$this->assertTrue($oNode->valid(10), '10 is not between 10 and 10000');
		$this->assertTrue($oNode->valid(100), '100 is not between 10 and 10000');
		$this->assertTrue($oNode->valid(1000), '1000 is not between 10 and 10000');
		$this->assertTrue($oNode->valid(9999), '9999 is not between 10 and 10000');

		// Check for False
		$this->assertFalse($oNode->valid(-11), '-11 is not between 10 and 10000');
		$this->assertFalse($oNode->valid(-2000), '-2000 is not between 10 and 10000');
		$this->assertFalse($oNode->valid(10013), '10013 is not between 10 and 10000');
		$this->assertFalse($oNode->valid(9), '9 is not between 10 and 10000');
	}

	public function test_Node_Valid_Options() {

		// Create a new basic base64 Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'base64',
			'__options__' => array('SGVsbG8sIHRoaXMgaXMgYSB0ZXN0IQ==', 'WW8gWW8gWW8=', 'RG92ZXRhaWwgaXMgdGhlIHNoaXQu')
		));

		// Check for True
		$this->assertTrue($oNode->valid('SGVsbG8sIHRoaXMgaXMgYSB0ZXN0IQ=='), '"SGVsbG8sIHRoaXMgaXMgYSB0ZXN0IQ==" is not in ["SGVsbG8sIHRoaXMgaXMgYSB0ZXN0IQ==", "WW8gWW8gWW8=", "RG92ZXRhaWwgaXMgdGhlIHNoaXQu"]');
		$this->assertTrue($oNode->valid('WW8gWW8gWW8='), '"WW8gWW8gWW8=" is not in ["SGVsbG8sIHRoaXMgaXMgYSB0ZXN0IQ==", "WW8gWW8gWW8=", "RG92ZXRhaWwgaXMgdGhlIHNoaXQu"]');
		$this->assertTrue($oNode->valid('RG92ZXRhaWwgaXMgdGhlIHNoaXQu'), '"RG92ZXRhaWwgaXMgdGhlIHNoaXQu" is not in ["SGVsbG8sIHRoaXMgaXMgYSB0ZXN0IQ==", "WW8gWW8gWW8=", "RG92ZXRhaWwgaXMgdGhlIHNoaXQu"]');

		// Check for False
		$this->assertFalse($oNode->valid('SPVsbG8sIHRoaXMgaXMgYSB0ZXN0IQ=='), '"SGVsbG8sIHRoaXMgaXMgYSB0ZXN0IQ==" is in ["SGVsbG8sIHRoaXMgaXMgYSB0ZXN0IQ==", "WW8gWW8gWW8=", "RG92ZXRhaWwgaXMgdGhlIHNoaXQu"]');
		$this->assertFalse($oNode->valid('WW8gWW8gWW8==='), '"WW8gWW8gWW8===" is in ["SGVsbG8sIHRoaXMgaXMgYSB0ZXN0IQ==", "WW8gWW8gWW8=", "RG92ZXRhaWwgaXMgdGhlIHNoaXQu"]');
		$this->assertFalse($oNode->valid('RG92ZXRhaWwgaXMgdGhlIHNo'), '"RG92ZXRhaWwgaXMgdGhlIHNo" is in ["SGVsbG8sIHRoaXMgaXMgYSB0ZXN0IQ==", "WW8gWW8gWW8=", "RG92ZXRhaWwgaXMgdGhlIHNoaXQu"]');

		// Create a new options date Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'date',
			'__options__' => array('2016-03-06', '2016-03-07', '2016-03-08')
		));

		// Check for True
		$this->assertTrue($oNode->valid('2016-03-06'), '"2016-03-06" is not in ["2016-03-06", "2016-03-07", "2016-03-08"]');
		$this->assertTrue($oNode->valid('2016-03-07'), '"2016-03-07" is not in ["2016-03-06", "2016-03-07", "2016-03-08"]');
		$this->assertTrue($oNode->valid('2016-03-08'), '"2016-03-08" is not in ["2016-03-06", "2016-03-07", "2016-03-08"]');

		// Check for True
		$this->assertFalse($oNode->valid('2016-03-05'), '"2016-03-05" is in ["2016-03-06", "2016-03-07", "2016-03-08"]');
		$this->assertFalse($oNode->valid('2016-03-09'), '"2016-03-09" is in ["2016-03-06", "2016-03-07", "2016-03-08"]');
		$this->assertFalse($oNode->valid('2015-03-07'), '"2015-03-07" is in ["2016-03-06", "2016-03-07", "2016-03-08"]');

		// Create a new options datetime Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'datetime',
			'__options__' => array('2016-03-06 02:00:00', '2016-03-07 00:02:00', '2016-03-08 00:00:02')
		));

		// Check for True
		$this->assertTrue($oNode->valid('2016-03-06 02:00:00'), '"2016-03-06 02:00:00" is not in ["2016-03-06 02:00:00", "2016-03-07 00:02:00", "2016-03-08 00:00:02"]');
		$this->assertTrue($oNode->valid('2016-03-07 00:02:00'), '"2016-03-07 00:02:00" is not in ["2016-03-06 02:00:00", "2016-03-07 00:02:00", "2016-03-08 00:00:02"]');
		$this->assertTrue($oNode->valid('2016-03-08 00:00:02'), '"2016-03-08 00:00:02" is not in ["2016-03-06 02:00:00", "2016-03-07 00:02:00", "2016-03-08 00:00:02"]');

		// Check for True
		$this->assertFalse($oNode->valid('2016-03-05 02:00:00'), '"2016-03-05 02:00:00" is in ["2016-03-06 02:00:00", "2016-03-07 00:02:00", "2016-03-08 00:00:02"]');
		$this->assertFalse($oNode->valid('2016-03-09 00:02:00'), '"2016-03-09 00:02:00" is in ["2016-03-06 02:00:00", "2016-03-07 00:02:00", "2016-03-08 00:00:02"]');
		$this->assertFalse($oNode->valid('2015-03-07 00:00:02'), '"2015-03-07 00:00:02" is in ["2016-03-06 02:00:00", "2016-03-07 00:02:00", "2016-03-08 00:00:02"]');

		// Create a new options decimal Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'decimal',
			'__options__' => array('0.0', '2.0', '123.345', '0.6')
		));

		// Check for True
		$this->assertTrue($oNode->valid('0.0'), '0.0 is not in [0.0, 2.0, 123.345, 0.6]');
		$this->assertTrue($oNode->valid('2.0'), '2.0 is not in [0.0, 2.0, 123.345, 0.6]');
		$this->assertTrue($oNode->valid('123.345'), '123.345 is not in [0.0, 2.0, 123.345, 0.6]');
		$this->assertTrue($oNode->valid('0.6'), ' is not in [0.0, 2.0, 123.345, 0.6]');

		// Check for False
		$this->assertFalse($oNode->valid('1'), '0 is in [0.0, 2.0, 123.345, 0.6]');
		$this->assertFalse($oNode->valid('2.1'), '2.1 is in [0.0, 2.0, 123.345, 0.6]');
		$this->assertFalse($oNode->valid('123.45'), '123.45 is in [0.0, 2.0, 123.345, 0.6]');
		$this->assertFalse($oNode->valid('0.06'), '0.06 is in [0.0, 2.0, 123.345, 0.6]');

		// Create a new options int Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'int',
			'__options__' => array(-1, 0, 2, 4)
		));

		// Check for True
		$this->assertTrue($oNode->valid(-1), '-1 is not in [-1, 0, 2, 4]');
		$this->assertTrue($oNode->valid(0), '0 is not in [-1, 0, 2, 4]');
		$this->assertTrue($oNode->valid(2), '2 is not in [-1, 0, 2, 4]');
		$this->assertTrue($oNode->valid(4), '4 is not in [-1, 0, 2, 4]');

		// Check for False
		$this->assertFalse($oNode->valid(1), '1 is in [-1, 0, 2, 4]');
		$this->assertFalse($oNode->valid(-2), '-2 is in [-1, 0, 2, 4]');
		$this->assertFalse($oNode->valid(3), '3 is in [-1, 0, 2, 4]');
		$this->assertFalse($oNode->valid(-100), '-100 is in [-1, 0, 2, 4]');

		// Create a new options ip Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'ip',
			'__options__' => array('10.0.0.1', '192.168.0.1', '127.0.0.1')
		));

		// Check for True
		$this->assertTrue($oNode->valid('10.0.0.1'), '"10.0.0.1" is not in ["10.0.0.1", "192.168.0.1", "127.0.0.1"]');
		$this->assertTrue($oNode->valid('192.168.0.1'), '"192.168.0.1" is not in ["10.0.0.1", "192.168.0.1", "127.0.0.1"]');
		$this->assertTrue($oNode->valid('127.0.0.1'), '"127.0.0.1" is not in ["10.0.0.1", "192.168.0.1", "127.0.0.1"]');

		// Check for False
		$this->assertFalse($oNode->valid('11.0.0.1'), '"11.0.0.1" is in ["10.0.0.1", "192.168.0.1", "127.0.0.1"]');
		$this->assertFalse($oNode->valid('192.169.1.1'), '"192.169.1.1" is in ["10.0.0.1", "192.168.0.1", "127.0.0.1"]');
		$this->assertFalse($oNode->valid('0.0.0.0'), '"0.0.0.0" is in ["10.0.0.1", "192.168.0.1", "127.0.0.1"]');

		// Create a new options md5 Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'md5',
			'__options__' => array('7b967af699a0a18b1f2bdc9704537a3e', '889ffd8cc409445187c4258d138192b6', '49c0d2aef0ab2634b0051544cdbf2415')
		));

		// Check for True
		$this->assertTrue($oNode->valid('7b967af699a0a18b1f2bdc9704537a3e'), '"7b967af699a0a18b1f2bdc9704537a3e" is not in ["7b967af699a0a18b1f2bdc9704537a3e", "889ffd8cc409445187c4258d138192b6", "49c0d2aef0ab2634b0051544cdbf2415"]');
		$this->assertTrue($oNode->valid('889ffd8cc409445187c4258d138192b6'), '"889ffd8cc409445187c4258d138192b6" is not in ["7b967af699a0a18b1f2bdc9704537a3e", "889ffd8cc409445187c4258d138192b6", "49c0d2aef0ab2634b0051544cdbf2415"]');
		$this->assertTrue($oNode->valid('49c0d2aef0ab2634b0051544cdbf2415'), '"49c0d2aef0ab2634b0051544cdbf2415" is not in ["7b967af699a0a18b1f2bdc9704537a3e", "889ffd8cc409445187c4258d138192b6", "49c0d2aef0ab2634b0051544cdbf2415"]');

		// Check for False
		$this->assertFalse($oNode->valid('49c0d2aef0ab2634b1051544cdbf2415'), '"49c0d2aef0ab2634b1051544cdbf2415" is in ["7b967af699a0a18b1f2bdc9704537a3e", "889ffd8cc409445187c4258d138192b6", "49c0d2aef0ab2634b0051544cdbf2415"]');
		$this->assertFalse($oNode->valid('889ffd8cc409445287c4258d138192b6'), '"889ffd8cc409445287c4258d138192b6" is in ["7b967af699a0a18b1f2bdc9704537a3e", "889ffd8cc409445187c4258d138192b6", "49c0d2aef0ab2634b0051544cdbf2415"]');
		$this->assertFalse($oNode->valid('49c0d2aee0ab2634b0051544cdbf2415'), '"49c0d2aee0ab2634b0051544cdbf2415" is in ["7b967af699a0a18b1f2bdc9704537a3e", "889ffd8cc409445187c4258d138192b6", "49c0d2aef0ab2634b0051544cdbf2415"]');

		// Create a new options string Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'string',
			'__options__' => array('hello', 'there', 'my', '00000')
		));

		// Check for True
		$this->assertTrue($oNode->valid('hello'), '"hello" is not in ["hello", "there", "my", "00000"]');
		$this->assertTrue($oNode->valid('there'), '"there" is not in ["hello", "there", "my", "00000"]');
		$this->assertTrue($oNode->valid('my'), '"my" is not in ["hello", "there", "my", "00000"]');
		$this->assertTrue($oNode->valid('00000'), '"00000" is not in ["hello", "there", "my", "00000"]');

		// Check for False
		$this->assertFalse($oNode->valid('49c0d2aef0ab2634b1051544cdbf2415'), '"49c0d2aef0ab2634b1051544cdbf2415" is in ["hello", "there", "my", "00000"]');
		$this->assertFalse($oNode->valid('889ffd8cc409445287c4258d138192b6'), '"889ffd8cc409445287c4258d138192b6" is in ["hello", "there", "my", "00000"]');
		$this->assertFalse($oNode->valid('49c0d2aee0ab2634b0051544cdbf2415'), '"49c0d2aee0ab2634b0051544cdbf2415" is in ["hello", "there", "my", "00000"]');
		$this->assertFalse($oNode->valid('0000'), '"0000" is in ["hello", "there", "my", "00000"]');

		// Create a new options time Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'time',
			'__options__' => array('12:00:12', '00:00:00', '12:23:00')
		));

		// Check for True
		$this->assertTrue($oNode->valid('12:00:12'), '"12:00:12" is not in ["12:00:12", "00:00:00", "12:23:00"]');
		$this->assertTrue($oNode->valid('00:00:00'), '"00:00:00" is not in ["12:00:12", "00:00:00", "12:23:00"]');
		$this->assertTrue($oNode->valid('12:23:00'), '"12:23:00" is not in ["12:00:12", "00:00:00", "12:23:00"]');

		// Check for True
		$this->assertFalse($oNode->valid('00:12:00'), '"00:12:00" is in ["12:00:12", "00:00:00", "12:23:00"]');
		$this->assertFalse($oNode->valid('23:59:59'), '"23:59:59" is in ["12:00:12", "00:00:00", "12:23:00"]');
		$this->assertFalse($oNode->valid('00:12:23'), '"00:12:23" is in ["12:00:12", "00:00:00", "12:23:00"]');

		// Create a new options timestamp Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'timestamp',
			'__options__' => array(0, 1, 2, 3)
		));

		// Check for True
		$this->assertTrue($oNode->valid(0), '0 is not in [0, 1, 2, 3]');
		$this->assertTrue($oNode->valid(1), '1 is not in [0, 1, 2, 3]');
		$this->assertTrue($oNode->valid(2), '2 is not in [0, 1, 2, 3]');
		$this->assertTrue($oNode->valid(3), '3 is not in [0, 1, 2, 3]');

		// Check for False
		$this->assertFalse($oNode->valid(4), '4 is in [0, 1, 2, 3]');
		$this->assertFalse($oNode->valid(-2), '-2 is in [0, 1, 2, 3]');
		$this->assertFalse($oNode->valid(10000), '10000 is in [0, 1, 2, 3]');
		$this->assertFalse($oNode->valid(-100), '-100 is in [0, 1, 2, 3]');

		// Create a new options uint Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'uint',
			'__options__' => array(0, 1, 2, 3)
		));

		// Check for True
		$this->assertTrue($oNode->valid(0), '0 is not in [0, 1, 2, 3]');
		$this->assertTrue($oNode->valid(1), '1 is not in [0, 1, 2, 3]');
		$this->assertTrue($oNode->valid(2), '2 is not in [0, 1, 2, 3]');
		$this->assertTrue($oNode->valid(3), '3 is not in [0, 1, 2, 3]');

		// Check for False
		$this->assertFalse($oNode->valid(4), '4 is in [0, 1, 2, 3]');
		$this->assertFalse($oNode->valid(-2), '-2 is in [0, 1, 2, 3]');
		$this->assertFalse($oNode->valid(10000), '10000 is in [0, 1, 2, 3]');
		$this->assertFalse($oNode->valid(-100), '-100 is in [0, 1, 2, 3]');
	}

	public function test_Node_Valid_Regex() {

		// Create a new options any Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'string',
			'__regex__' => '^\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\d|3[01])$'
		));

		// Check for True
		$this->assertTrue($oNode->valid('2016-03-05'), '"2016-03-05" is not in /^\\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\\d|3[01])$/');
		$this->assertTrue($oNode->valid('2020-12-25'), '"2020-12-25" is not in /^\\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\\d|3[01])$/');
		$this->assertTrue($oNode->valid('1970-01-01'), '"1970-01-01" is not in /^\\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\\d|3[01])$/');

		// Check for False
		$this->assertFalse($oNode->valid('70-01-01'), '"70-01-01" is in /^\\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\\d|3[01])$/');
		$this->assertFalse($oNode->valid('10000-01-01'), '"10000-01-01" is in /^\\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\\d|3[01])$/');
		$this->assertFalse($oNode->valid('1970-00-01'), '"1970-00-01" is in /^\\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\\d|3[01])$/');
		$this->assertFalse($oNode->valid('2000-12-00'), '"2000-12-00" is in /^\\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\\d|3[01])$/');
		$this->assertFalse($oNode->valid('2000-12-32'), '"2000-12-32" is in /^\\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\\d|3[01])$/');
		$this->assertFalse($oNode->valid('2000-13-10'), '"2000-13-10" is in /^\\d{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12]\\d|3[01])$/');

		// Create a new options any Node module
		$oNode = new FormatOC\Node(array(
			'__type__' => 'string',
			'__regex__' => '^(?:hello|there|my|friend)$'
		));

		// Check for True
		$this->assertTrue($oNode->valid('hello'), '"hello" is not in /^(?:hello|there|my|friend)$/');
		$this->assertTrue($oNode->valid('there'), '"there" is not in /^(?:hello|there|my|friend)$/');
		$this->assertTrue($oNode->valid('my'), '"my" is not in /^(?:hello|there|my|friend)$/');
		$this->assertTrue($oNode->valid('friend'), '"friend" is not in /^(?:hello|there|my|friend)$/');

		// Check for False
		$this->assertFalse($oNode->valid('suck it'), '"suck it" is in /^(?:hello|there|my|friend)$/');
		$this->assertFalse($oNode->valid('HELLO'), '"HELLO" is in /^(?:hello|there|my|friend)$/');
		$this->assertFalse($oNode->valid('WhatWhat'), '"WhatWhat" is in /^(?:hello|there|my|friend)$/');
		$this->assertFalse($oNode->valid('2309 r gjvhjw0e9f'), '"2309 r gjvhjw0e9f" is in /^(?:hello|there|my|friend)$/');
	}

	public function test_Option_Clean() {

		$oOption = new FormatOC\OptionsNode(array(
			array("__type__" => "uint"),
			array("__type__" => "string","__options__" => array("hello", "there"))
		));

		$this->assertTrue($oOption->clean(0) == 0, '0 does not equal 0');
		$this->assertTrue($oOption->clean('0') == 0, '"0" does not equal 0');
		$this->assertTrue($oOption->clean(1) == 1, '1 does not equal 1');
		$this->assertTrue($oOption->clean('1') == 1, '"1" does not equal 1');
		$this->assertTrue($oOption->clean('hello') == 'hello', '"hello" does not equal "hello"');
		$this->assertTrue($oOption->clean('there') == 'there', '"hello" does not equal "there"');
	}

	public function test_Option_Iterate() {

		$a = array(
			array("__type__" => "uint"),
			array("__type__" => "string","__options__" => array("hello", "there"))
		);

		$oOption = new FormatOC\OptionsNode(array(
			array("__type__" => "uint"),
			array("__type__" => "string","__options__" => array("hello", "there"))
		));

		$this->assertTrue(count($oOption) == 2, 'Count is not 2');

		for($i = 0; $i < count($oOption); ++$i) {
			$this->assertTrue($oOption[$i]->toArray() == $a[$i], 'structure doesn\'t match');
		}
	}

	public function test_Option_Valid() {

		$oOption = new FormatOC\OptionsNode(array(
			array("__type__" => "uint"),
			array("__type__" => "string","__options__" => array("hello", "there"))
		));

		// Test for true
		$this->assertTrue($oOption->valid(0), '0 does not equal 0');
		$this->assertTrue($oOption->valid('0'), '"0" does not equal 0');
		$this->assertTrue($oOption->valid(1), '1 does not equal 1');
		$this->assertTrue($oOption->valid('1'), '"1" does not equal 1');
		$this->assertTrue($oOption->valid('hello'), '"hello" does not equal "hello"');
		$this->assertTrue($oOption->valid('there'), '"hello" does not equal "there"');

		// Test for false
		$this->assertFalse($oOption->valid(-1), '-1 is a valid option');
		$this->assertFalse($oOption->valid('-1'), '"-1" is a valid option');
		$this->assertFalse($oOption->valid('something'), '"something" is a valid option');
		$this->assertFalse($oOption->valid('else'), '"else" is a valid option');
	}

	public function test_Tree_toJSON() {

		$o = new FormatOC\Tree(array("__name__"=>"hello","field1"=>array("__type__"=>"uint"),"field2"=>array("field2_1"=>array("__type__"=>"string","__regex__"=>'^\S+$'),"field2_2"=>array("__type__"=>"uint","__options__"=>array(0,1,2,34))),"field3"=>array("__array__"=>"unique","__type__"=>"decimal"),"field4"=>array("__array__"=>"duplicates","__ui__"=>array("ui"=>"information"),"field4_1"=>array("__type__"=>"md5"),"field4_2"=>array("field4_2_1"=>array("__type__"=>"date","__mysql__"=>"MySQL information")))));

		// Test for true
		$json = '{"__name__":"hello","field1":{"__type__":"uint"},"field2":{"field2_1":{"__type__":"string","__regex__":"^\\\\S+$"},"field2_2":{"__type__":"uint","__options__":[0,1,2,34]}},"field3":{"__array__":"unique","__type__":"decimal"},"field4":{"__array__":"duplicates","__ui__":{"ui":"information"},"field4_1":{"__type__":"md5"},"field4_2":{"field4_2_1":{"__mysql__":"MySQL information","__type__":"date"}}}}';
		$this->assertTrue($o->toJSON() == $json, 'toJSON failed: ' . $o->toJSON());
	}

	public function test_Tree_Valid() {

		// Build a Tree
		$o = new FormatOC\Tree(array("__name__"=>"hello","field1"=>array("__type__"=>"uint"),"field2"=>array("field2_1"=>array("__type__"=>"string","__regex__"=>"^\\S+$"),"field2_2"=>array("__type__"=>"uint","__options__"=>array(0,1,2,34))),"field3"=>array("__array__"=>"unique","__type__"=>"decimal"),"field4"=>array("__array__"=>"duplicates","field4_1"=>array("__type__"=>"md5"),"field4_2"=>array("field4_2_1"=>array("__type__"=>"date")))));

		// Check for True
		$this->assertTrue($o['field2']['field2_1']->valid('Hello'), '"Hello" is not a valid value for hello.field2.field2_1');
		$this->assertTrue($o['field2']->valid(array("field2_1"=>"Hello","field2_2"=>34)), '{"field2_1":"Hello","field2_2":34} is not a valid value for hello.field2');
		$this->assertTrue($o->valid(array("field1"=>2,"field2"=>array("field2_1"=>"ThisString","field2_2"=>34),"field3"=>array(0.3,10.3,20.3),"field4"=>array(array("field4_1"=>"49c0d2aef0ab2634b0051544cdbf2415","field4_2"=>array("field4_2_1"=>"2016-03-05")),array("field4_1"=>"49c0d2aef0ab2634b0051544cdbf2415","field4_2"=>array("field4_2_1"=>"2016-03-05"))))), '{"field1":2,"field2":{"field2_1":"ThisString","field2_2":34},"field3":[0.3,10.3,20.3],"field4":[{"field4_1":"49c0d2aef0ab2634b0051544cdbf2415","field4_2":{"field4_2_1":"2016-03-05"},},{"field4_1":"49c0d2aef0ab2634b0051544cdbf2415","field4_2":{"field4_2_1":"2016-03-05"}}]} is not a valid value for hello');

		// Check for False
		$this->assertFalse($o['field2']['field2_1']->valid('    '), '"    " is not a valid value for hello.field2.field2_1');
		$this->assertTrue($o['field2']['field2_1']->validation_failures[0][0] == '', 'error name is not correct: "' . strval($o['field2']['field2_1']->validation_failures[0][0]) . '"');
		$this->assertTrue($o['field2']['field2_1']->validation_failures[0][1] == 'failed regex (custom)', 'error value is not correct: "' . strval($o['field2']['field2_1']->validation_failures[0][1]) . '"');

		$this->assertFalse($o['field2']->valid(array("field2_1"=>"Hello","field2_2"=>4)), '{"field2_1":"Hello","field2_2":4} is not a valid value for hello.field2');
		$this->assertTrue($o['field2']->validation_failures[0][0] == 'field2_2', 'error name is not correct: "' . strval($o['field2']->validation_failures[0][0]) . '"');
		$this->assertTrue($o['field2']->validation_failures[0][1] == 'not in options', 'error value is not correct: "' . strval($o['field2']->validation_failures[0][1]) . '"');

		$this->assertFalse($o['field2']->valid(array("field2_1"=>"   ","field2_2"=>2)), '{"field2_1":"   ","field2_2":2} is not a valid value for hello.field2');
		$this->assertTrue($o['field2']->validation_failures[0][0] == 'field2_1', 'error name is not correct: "' . strval($o['field2']->validation_failures[0][0]) . '"');
		$this->assertTrue($o['field2']->validation_failures[0][1] == 'failed regex (custom)', 'error value is not correct: "' . strval($o['field2']->validation_failures[0][1]) . '"');

		$this->assertFalse($o->valid(array("field1"=>"NotAnINTEGER","field2"=>array("field2_1"=>"ThisString","field2_2"=>34),"field3"=>array(0.3,10.3,20.3),"field4"=>array(array("field4_1"=>"49c0d2aef0ab2634b0051544cdbf2415","field4_2"=>array("field4_2_1"=>"2016-03-05")),array("field4_1"=>"49c0d2aef0ab2634b0051544cdbf2415","field4_2"=>array("field4_2_1"=>"2016-03-05"))))), '{"field1":"NotAnINTEGER","field2":{"field2_1":"ThisString","field2_2":34},"field3":[0.3,10.3,20.3],"field4":[{"field4_1":"49c0d2aef0ab2634b0051544cdbf2415","field4_2":{"field4_2_1":"2016-03-05"},},{"field4_1":"49c0d2aef0ab2634b0051544cdbf2415","field4_2":{"field4_2_1":"2016-03-05"}}]} is not a valid value for hello');
		$this->assertTrue($o->validation_failures[0][0] == 'hello.field1', 'error name is not correct: "' . strval($o->validation_failures[0][0]) . '"');
		$this->assertTrue($o->validation_failures[0][1] == 'not an integer', 'error value is not correct: "' . strval($o->validation_failures[0][1]) . '"');

		$this->assertFalse($o->valid(array("field1"=>"NotAnINTEGER","field2"=>array("field2_1"=>"This String","field2_2"=>3),"field3"=>array(0.3,10.3,20.3),"field4"=>array(array("field4_1"=>"49c0d2aef0ab2634b0051544cdbf2415","field4_2"=>array("field4_2_1"=>"2016-03-05")),array("field4_1"=>"49c0d2aef0ab2634b0051544cdbf2415","field4_2"=>array("field4_2_1"=>"2016-03-05")))), false), '{"field1":"NotAnINTEGER","field2":{"field2_1":"ThisString","field2_2":34},"field3":[0.3,10.3,20.3],"field4":[{"field4_1":"49c0d2aef0ab2634b0051544cdbf2415","field4_2":{"field4_2_1":"2016-03-05"},},{"field4_1":"49c0d2aef0ab2634b0051544cdbf2415","field4_2":{"field4_2_1":"2016-03-05"}}]} is not a valid value for hello');
		$this->assertTrue($o->validation_failures[0][0] == 'field1', 'error name is not correct: "' . strval($o->validation_failures[0][0]) . '"');
		$this->assertTrue($o->validation_failures[0][1] == 'not an integer', 'error value is not correct: "' . strval($o->validation_failures[0][1]) . '"');
		$this->assertTrue($o->validation_failures[1][0] == 'field2.field2_1', 'error name is not correct: "' . strval($o->validation_failures[1][0]) . '"');
		$this->assertTrue($o->validation_failures[1][1] == 'failed regex (custom)', 'error value is not correct: "' . strval($o->validation_failures[1][1]) . '"');
		$this->assertTrue($o->validation_failures[2][0] == 'field2.field2_2', 'error name is not correct: "' . strval($o->validation_failures[2][0]) . '"');
		$this->assertTrue($o->validation_failures[2][1] == 'not in options', 'error value is not correct: "' . strval($o->validation_failures[2][1]) . '"');
	}
}

