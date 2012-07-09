<?

require('../data/archivist/Archivist.php');

$arch = new Archivist(array(
	'rootUrl' => 'http://fxtrend/',
	'pagesList' => array(
		'/registration-new/',
	),
	'gzipped' => false,
));

$arch->run();