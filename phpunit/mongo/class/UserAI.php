<?php
class UserAI extends Tango\Drive\MongoAI {

	use Tango\Drive\MongoDebug;

	protected static $_sKeyType = 'int';
	protected static $_mKey = '_id';
}
