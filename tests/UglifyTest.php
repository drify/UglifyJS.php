<?php

use PHPUnit\Framework\TestCase;

class UglifyTest extends TestCase
{
    /**
     * @dataProvider getTester
     */
    public function testJs($file)
    {
        $test = file_get_contents('./test/' . $file);
        $expected = file_get_contents('./expected/' . $file);

        $test = uglify($test);
        $expected = trim($expected);

        $this->assertSame($expected, $test);
    }

    public function getTester()
    {
        $tests = [];

        $t = opendir('./test/');
        while (false !== ($file = readdir($t))) $tests[] = (array)$file;
        closedir($t);

        return $tests;
    }
}
