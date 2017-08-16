<?php

class UglifyTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getTester
     */
    public function testJs($file)
    {
        $test = file_get_contents(__DIR__ . '/test/' . $file);
        $expected = file_get_contents(__DIR__ . '/expected/' . $file);

        $test = uglify($test);
        $expected = trim($expected);

        $this->assertSame($expected, $test);
    }

    public function getTester()
    {
        $tests = [];

        $t = opendir(__DIR__ . '/test/');
        while (false !== ($file = readdir($t))) $tests[] = (array)$file;
        closedir($t);

        return $tests;
    }
}
