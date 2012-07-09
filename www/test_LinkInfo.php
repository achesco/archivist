<?php

require '../data/archivist/Resource.php';

echo "<pre>\n";

test('http://sitem.com/path/to/page/', 'http://google.com/file.css');

test('http://sitem.com/path/to/page/', '../../file.css?fadk=1#aed');

test('http://sitem.com/path/to/page/', '/f/1/global/file.css?fadk=1#aed');

test('http://sitem.com', '/file.css?fadk=1#aed');

function test($baseUrl, $link) {
	echo $baseUrl . "\n";
	echo $link  . "\n";
	print_r(new LinkInfo($baseUrl, $link));	
}