<?php
namespace Tango\Core;

class Schema {

	static protected $_aSchema = [];

	static protected $_a = [];

	function __construct($aData) {

		$retriever = new JsonSchema\Uri\UriRetriever;
		$schema = $retriever->retrieve('file://' . realpath('schema.json'));
		$data = json_decode(file_get_contents('data.json'));

		$refResolver = new JsonSchema\RefResolver($retriever);
		$refResolver->resolve($schema, 'file://' . __DIR__);

		$validator = new JsonSchema\Validator();
		$validator->check($data, $schema);

		if ($validator->isValid()) {
			echo "The supplied JSON validates against the schema.\n";
		} else {
			echo "JSON does not validate. Violations:\n";
			foreach ($validator->getErrors() as $error) {
				echo sprintf("[%s] %s\n", $error['property'], $error['message']);
			}
		}

	}
}
