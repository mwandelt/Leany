<?php

require_once 'leany.class.php';
$leany = new Leany( __DIR__ );

$headline = 'Compiler Example';
$intro = 'Learn how to make your life easier with a template compiler.';
$articles = array (
	array ( 'number' => '1001', 'name' => 'Article A' ),
	array ( 'number' => '1002', 'name' => 'Article B' ),
	array ( 'number' => '1003', 'name' => 'Article C' )
);

$code = $leany->compile('example.html');

// Show the generated code (for testing and debugging)
echo $code;

// Uncomment the following line to run the generated code
// eval( '?' . '>' . $code );

// end of file example.php
