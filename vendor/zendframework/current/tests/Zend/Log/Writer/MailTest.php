<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_Log
 * @subpackage UnitTests
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @version    $Id: MailTest.php 17363 2009-08-03 07:40:18Z bkarwin $
 */

// Call Zend_Log_Writer_MailTest::main() if this source file is executed directly.
if (!defined("PHPUnit_MAIN_METHOD")) {
    define("PHPUnit_MAIN_METHOD", "Zend_Log_Writer_MailTest::main");
}

/**
 * Test helper
 */
require_once realpath(dirname(__FILE__) . '/../../..') . '/TestHelper.php'; 

/** Zend_Layout */
require_once 'Zend/Layout.php';

/** Zend_Log */
require_once 'Zend/Log.php';

/** Zend_Log_Writer_Mail */
require_once 'Zend/Log/Writer/Mail.php';

/** Zend_Mail */
require_once 'Zend/Mail.php';

/** Zend_Mail_Transport_Exception */
require_once 'Zend/Mail/Transport/Exception.php';

/** Zend_View_Exception */
require_once 'Zend/View/Exception.php';

/**
 * @category   Zend
 * @package    Zend_Log
 * @subpackage UnitTests
 * @copyright  Copyright (c) 2005-2009 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 * @group      Zend_Log
 */
class Zend_Log_Writer_MailTest extends PHPUnit_Framework_TestCase
{
    /**
     * Runs the test methods of this class.
     *
     * @return void
     */
    public static function main()
    {
        require_once "PHPUnit/TextUI/TestRunner.php";
        $suite = new PHPUnit_Framework_TestSuite("Zend_Log_Writer_MailTest");
        $result = PHPUnit_TextUI_TestRunner::run($suite);
    }

    /**
     * Tests normal logging, but with multiple messages for a level.
     *
     * @return void
     */
    public function testNormalLoggingMultiplePerLevel()
    {
        list(, , $log) = $this->_getSimpleLogger();
        $log->info('an info message');
        $log->info('a second info message');
    }

    /**
     * Tests normal logging without use of Zend_Layout.
     *
     * @return void
     */
    public function testNormalLoggingNoLayout()
    {
        list(, , $log) = $this->_getSimpleLogger();
        $log->info('an info message');
        $log->warn('a warning message');
    }

    /**
     * Tests normal logging with Zend_Layout usage.
     *
     * @return void
     */
    public function testNormalLoggingWithLayout()
    {
        list(, , $log) = $this->_getSimpleLogger(true);
        $log->info('an info message');
        $log->warn('a warning message');
    }

    /**
     * Tests normal logging with Zend_Layout and a custom formatter for it.
     *
     * @return void
     */
    public function testNormalLoggingWithLayoutAndItsFormatter()
    {
        list(, $writer, $log) = $this->_getSimpleLogger(true);

        // Since I'm using Zend_Layout, I should be able to set a formatter
        // for it.
        $writer->setLayoutFormatter(new Zend_Log_Formatter_Simple());

        // Log some messages to cover those cases.
        $log->info('an info message');
        $log->warn('a warning message');
    }

    /**
     * Tests normal logging with use of Zend_Layout, a custom formatter, and
     * subject prepend text.
     *
     * @return void
     */
    public function testNormalLoggingWithLayoutFormatterAndSubjectPrependText()
    {
        list(, $writer, $log) = $this->_getSimpleLogger(true);
        $writer->setLayoutFormatter(new Zend_Log_Formatter_Simple());
        $return = $writer->setSubjectPrependText('foo');

        $this->assertSame($writer, $return);

        // Log some messages to cover those cases.
        $log->info('an info message');
        $log->warn('a warning message');
    }

    /**
     * Tests setting of subject prepend text.
     *
     * @return void
     */
    public function testSetSubjectPrependTextNormal()
    {
        list($mail, $writer, $log) = $this->_getSimpleLogger();

        $return = $writer->setSubjectPrependText('foo');

        // Ensure that fluent interface is present.
        $this->assertSame($writer, $return);
    }

    /**
     * Tests that the subject prepend text can't be set if the Zend_Mail
     * object already has a subject line set.
     *
     * @return void
     */
    public function testSetSubjectPrependTextPreExisting()
    {
        list($mail, $writer, $log) = $this->_getSimpleLogger();

        // Expect a Zend_Log_Exception because the subject prepend text cannot
        // be set of the Zend_Mail object already has a subject line set.
        $this->setExpectedException('Zend_Log_Exception');

        // Set a subject line so the setSubjectPrependText() call triggers an
        // exception.
        $mail->setSubject('a pre-existing subject line');

        $writer->setSubjectPrependText('foo');
    }

    /**
     * Tests basic fluent interface for setting layout formatter.
     *
     * @return void
     */
    public function testSetLayoutFormatter()
    {
        list(, $writer) = $this->_getSimpleLogger(true);
        $return = $writer->setLayoutFormatter(new Zend_Log_Formatter_Simple());
        $this->assertSame($writer, $return);
    }

    /**
     * Tests that the layout formatter can be set and retrieved.
     *
     * @return void
     */
    public function testGetLayoutFormatter()
    {
        list(, $writer) = $this->_getSimpleLogger(true);
        $formatter = new Zend_Log_Formatter_Simple();

        // Ensure that fluent interface is present.
        $returnedWriter = $writer->setLayoutFormatter($formatter);
        $this->assertSame($writer, $returnedWriter);

        // Ensure that the getter returns the same formatter.
        $returnedFormatter = $writer->getLayoutFormatter();
        $this->assertSame($formatter, $returnedFormatter);
    }

    /**
     * Tests setting of the layout formatter when Zend_Layout is not being
     * used.
     *
     * @return void
     */
    public function testSetLayoutFormatterWithoutLayout()
    {
        list(, $writer) = $this->_getSimpleLogger();

        // If Zend_Layout is not being used, a formatter cannot be set for it.
        $this->setExpectedException('Zend_Log_Exception');
        $writer->setLayoutFormatter(new Zend_Log_Formatter_Simple());
    }

    /**
     * Tests destruction of the Zend_Log instance when an error message entry
     * is in place, but the mail can't be sent.  Should result in a warning,
     * which we test for here.
     *
     * @return void
     */
    public function testDestructorMailError()
    {
        list($mail, $writer, $log) = $this->_getSimpleLogger(false);

        // Force the send() method to throw the same exception that would be
        // thrown if, say, the SMTP server couldn't be contacted.
        $mail->expects($this->any())
             ->method('send')
             ->will($this->throwException(new Zend_Mail_Transport_Exception()));

        // Log an error message so that there's something to send via email.
        $log->err('a bogus error message to force mail sending');

        $this->setExpectedException('PHPUnit_Framework_Error');
        unset($log);
    }

    /**
     * Tests destruction of the Zend_Log instance when an error message entry
     * is in place, but the layout can't be rendered.  Should result in a
     * notice, which we test for here.
     *
     * @return void
     */
    public function testDestructorLayoutError()
    {
        list($mail, $writer, $log, $layout) = $this->_getSimpleLogger(true);

        // Force the render() method to throw the same exception that would
        // be thrown if, say, the layout template file couldn't be found.
        $layout->expects($this->any())
               ->method('render')
               ->will($this->throwException(new Zend_View_Exception('bogus message')));

        // Log an error message so that there's something to send via email.
        $log->err('a bogus error message to force mail sending');

        $this->setExpectedException('PHPUnit_Framework_Error');
        unset($log);
    }

    /**
     * Returns an array of the Zend_Mail mock object, Zend_Log_Writer_Mail
     * object, and Zend_Log objects.
     *
     * This is just a helper function for the various test methods above.
     *
     * @return array Numerically indexed array of Zend_Mail,
     *               Zend_Log_Writer_Mail, Zend_Log, and Zend_Layout objects,
     *               in that order.
     */
    protected function _getSimpleLogger($useLayout = false)
    {
        // Get a mock object for Zend_Mail so that no emails are actually
        // sent.
        $mail = $this->getMock('Zend_Mail', array('send'));

        // The send() method can be called any number of times.
        $mail->expects($this->any())
             ->method('send');

        $mail->addTo('zend_log_writer_mail_test@example.org');
        $mail->setFrom('zend_log_writer_mail_test@example.org');

        // Setup a mock object for Zend_Layout because we can't rely on any
        // layout files being in place.
        if ($useLayout) {
            $layout = $this->getMock('Zend_Layout', array('render'));
            $writer = new Zend_Log_Writer_Mail($mail, $layout);
        } else {
            $writer = new Zend_Log_Writer_Mail($mail);
            $layout = null;
        }

        $log = new Zend_Log();
        $log->addWriter($writer);

        return array($mail, $writer, $log, $layout);
    }
}

// Call Zend_Log_Writer_MailTest::main() if this source file is executed directly.
if (PHPUnit_MAIN_METHOD == "Zend_Log_Writer_MailTest::main") {
    Zend_Log_Writer_MailTest::main();
}
