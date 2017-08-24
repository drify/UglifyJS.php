UglifyJS.php
============

[![Build Status](https://travis-ci.org/drify/UglifyJS.php.svg?branch=master)](https://travis-ci.org/drify/UglifyJS.php)

PHP port of [UglifyJS](https://github.com/mishoo/UglifyJS). Since PHP supports [closures](https://secure.php.net/manual/en/functions.anonymous.php), it's like some kind of "transliteration".

It supports PHP >= 5.4, but using PHP >= 7.0 is recommended.

It runs 1.5x - 4x slower than the original Node version, depending on the PHP environment. With PHP7, the performance would be acceptable.

Features
--------

* Purely written in PHP, does not need Node.js installed
* Does not require non-default extensions
* Evaluates simple constant expressions correctly

Known limitations
-----------------

* PHP has no native support of Unicode, and the `mb_*` functions are unbearably slow, so your code cannot contain non-ASCII characters (except in comments). However, Unicode escape sequences are supported.
* No support for `ast_lift_variables` and `ast_consolidate`

Usage
-----

The APIs should be almost identical to the original ones.

```php
require 'parse-js.php';
require 'process.php';

$orig_code = '... JS code here';
$ast = $parse($orig_code); // parse code and get the initial AST
$ast = $ast_mangle($ast); // get a new AST with mangled names
$ast = $ast_squeeze($ast); // get an AST with compression optimizations
$final_code = $strip_lines($gen_code($ast)); // compressed code here
```
