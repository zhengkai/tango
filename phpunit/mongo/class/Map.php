<?php
class Map extends Tango\Drive\Mongo {

	use Tango\Drive\MongoDebug;

	protected static $_oConn;

	protected static $_sKeyType = 'int';
	protected static $_mKey = '_id';

	protected static $_bSharding = FALSE;

	protected static $_bIncKey = FALSE;

	protected static $_lDiff = TRUE;
}
