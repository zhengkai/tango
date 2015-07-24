<?php
class IdGen extends Tango\Drive\Mongo {

	use Tango\Drive\MongoDebug;

	protected static $_sKeyType = 'int';
	protected static $_mKey = '_id';

	protected static $_lDiff = [];
}
