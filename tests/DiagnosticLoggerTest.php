<?php

namespace LibLynx\Connect;

use PHPUnit\Framework\TestCase;

class DiagnosticLoggerTest extends TestCase
{
    public function testBasic()
    {
        $logger = new DiagnosticLogger;
        $logger->notice('test{num}', ['num' => 123]);

        $this->assertEquals(1, $logger->countLogs('notice'));
        $this->assertEquals(0, $logger->countLogs('debug'));

        ob_start();
        $logger->dumpConsole(false);
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertEquals("notice: test123\n", $output);


        ob_start();
        $logger->dumpHTML(true);
        $html = ob_get_contents();
        ob_end_clean();

        $expected = "<div class=\"liblynx-diagnostic-log\">" .
            "<table class=\"table\"><thead><tr><th>Level</th><th>Message</th></tr></thead><tbody>\n" .
            "<tr class=\"level-notice\"><td>notice</td><td>test123</td></tr>\n" .
            "</tbody></table></div>\n";
        $this->assertEquals($expected, $html);


        $logger->cleanLogs();
        $this->assertEquals(0, $logger->countLogs('notice'));
    }
}
