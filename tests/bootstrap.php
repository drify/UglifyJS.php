<?php

function uglify($code) {
    $DIGITS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ$_0123456789';

    require __DIR__ . '/../src/parse-js.php';
    require __DIR__ . '/../src/process.php';

    $ast = $parse($code);
    $ast = $ast_mangle($ast);
    $ast = $ast_squeeze($ast, [ 'no_warnings' => true ]);
    $ast = $ast_squeeze_more($ast);
    $final_code = $strip_lines($gen_code($ast)); // compressed code here
    return $final_code;
}

if (!class_exists('\PHPUnit_Framework_TestCase') && class_exists('\PHPUnit\Framework\TestCase')) {
    class_alias('\PHPUnit\Framework\TestCase', '\PHPUnit_Framework_TestCase');
}
