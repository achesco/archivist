<?php

require('../data/archivist/Archivist.php');
require('params.php');

$arch = new Archivist($params);

$arch->run();