<?php

function uglify($code){
    require './parse-js.php';
    require './--process.php';

    $ast = $parse($code);
    $ast = $ast_mangle($ast);
    $ast = $ast_squeeze($ast, [ 'no_warnings' => true ]);
    $ast = $ast_squeeze_more($ast);
    $final_code = $strip_lines($gen_code($ast), 0); // compressed code here
    return $final_code;
}
