<?php
require_once __DIR__ . '/wpay_helper.php';
$h = new WPayHelper();
$result = $h->createPayIn(1, 100, 'GCASH');
var_export($result);
