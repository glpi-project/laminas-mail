<?php

/**
 * @see       https://github.com/laminas/laminas-mail for the canonical source repository
 * @copyright https://github.com/laminas/laminas-mail/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-mail/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Mail\Storage;

use Laminas\Config;
use Laminas\Mail\Storage;
use Laminas\Mail\Storage\Exception;
use PHPUnit\Framework\TestCase;

/**
 * @group      Laminas_Mail
 */
class MaildirTest extends TestCase
{
    protected $maildir;
    protected $tmpdir;

    public function setUp()
    {
        if (\strtoupper(\substr(PHP_OS, 0, 3)) == 'WIN') {
            $this->markTestSkipped('This test does not work on Windows');
            return;
        }

        $originalMaildir = __DIR__ . '/../_files/test.maildir/';

        if ($this->tmpdir == null) {
            if (getenv('TESTS_LAMINAS_MAIL_TEMPDIR') != null) {
                $this->tmpdir = getenv('TESTS_LAMINAS_MAIL_TEMPDIR');
            } else {
                $this->tmpdir = __DIR__ . '/../_files/test.tmp/';
            }
            if (! file_exists($this->tmpdir)) {
                mkdir($this->tmpdir);
            }
            $count = 0;
            $dh = opendir($this->tmpdir);
            while (readdir($dh) !== false) {
                ++$count;
            }
            closedir($dh);
            if ($count != 2) {
                $this->markTestSkipped('Are you sure your tmp dir is a valid empty dir?');
                return;
            }
        }

        if (! \file_exists($originalMaildir . 'maildirsize') && \class_exists('PharData')) {
            try {
                $phar = new \PharData($originalMaildir . 'maildir.tar');
                $phar->extractTo($originalMaildir);
            } catch (\Exception $e) {
                // intentionally empty catch block
            }
        }

        if (! \file_exists($originalMaildir . 'maildirsize')) {
            $this->markTestSkipped('You have to unpack maildir.tar in '
            . 'Laminas/Mail/_files/test.maildir/ directory to run the maildir tests');
            return;
        }

        $this->maildir = $this->tmpdir;

        foreach (['cur', 'new'] as $dir) {
            mkdir($this->tmpdir . $dir);
            $dh = opendir($originalMaildir . $dir);
            while (($entry = readdir($dh)) !== false) {
                $entry = $dir . '/' . $entry;
                if (! is_file($originalMaildir . $entry)) {
                    continue;
                }
                copy($originalMaildir . $entry, $this->tmpdir . $entry);
            }
            closedir($dh);
        }
    }

    public function tearDown()
    {
        foreach (['cur', 'new'] as $dir) {
            if (! is_dir($this->tmpdir . $dir)) {
                if (\is_dir($this->tmpdir . $dir . '-isFileTest')) {
                    \unlink($this->tmpdir . $dir);
                    \rename($this->tmpdir . $dir . '-isFileTest', $this->tmpdir . $dir);
                } else {
                    continue;
                }
            }
            \chmod($this->tmpdir . $dir, 0700);
            $dh = opendir($this->tmpdir . $dir);
            while (($entry = readdir($dh)) !== false) {
                $entry = $this->tmpdir . $dir . '/' . $entry;
                if (! is_file($entry)) {
                    continue;
                }
                unlink($entry);
            }
            closedir($dh);
            rmdir($this->tmpdir . $dir);
        }

        if (\file_exists($this->tmpdir . 'tmp')) {
            \unlink($this->tmpdir . 'tmp');
        }
    }

    public function testLoadOk()
    {
        $mail = new Storage\Maildir(['dirname' => $this->maildir]);
        $this->assertSame(Storage\Maildir::class, \get_class($mail));
    }

    public function testLoadConfig()
    {
        $mail = new Storage\Maildir(new Config\Config(['dirname' => $this->maildir]));
        $this->assertSame(Storage\Maildir::class, \get_class($mail));
    }

    public function testLoadFailure()
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('no valid dirname given in params');
        new Storage\Maildir(['dirname' => '/This/Dir/Does/Not/Exist']);
    }

    public function testLoadInvalid()
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid maildir given');
        new Storage\Maildir(['dirname' => __DIR__]);
    }

    public function testClose()
    {
        $mail = new Storage\Maildir(['dirname' => $this->maildir]);

        $this->assertNull($mail->close());
    }

    public function testHasFlags()
    {
        $mail = new Storage\Maildir(['dirname' => $this->maildir]);
        $this->assertTrue($mail->hasFlags);
    }

    public function testHasTop()
    {
        $mail = new Storage\Maildir(['dirname' => $this->maildir]);

        $this->assertTrue($mail->hasTop);
    }

    public function testHasCreate()
    {
        $mail = new Storage\Maildir(['dirname' => $this->maildir]);

        $this->assertFalse($mail->hasCreate);
    }

    public function testNoop()
    {
        $mail = new Storage\Maildir(['dirname' => $this->maildir]);

        $this->assertTrue($mail->noop());
    }

    public function testCount()
    {
        $mail = new Storage\Maildir(['dirname' => $this->maildir]);

        $count = $mail->countMessages();
        $this->assertEquals(5, $count);
    }

    public function testSize()
    {
        $mail = new Storage\Maildir(['dirname' => $this->maildir]);
        $shouldSizes = [1 => 397, 89, 694, 452, 497];


        $sizes = $mail->getSize();
        $this->assertEquals($shouldSizes, $sizes);
    }

    public function testSingleSize()
    {
        $mail = new Storage\Maildir(['dirname' => $this->maildir]);

        $size = $mail->getSize(2);
        $this->assertEquals(89, $size);
    }

    public function testFetchHeader()
    {
        $mail = new Storage\Maildir(['dirname' => $this->maildir]);

        $subject = $mail->getMessage(1)->subject;
        $this->assertEquals('Simple Message', $subject);
    }

/*
    public function testFetchTopBody()
    {
        $mail = new Storage\Maildir(array('dirname' => $this->maildir));

        $content = $mail->getHeader(3, 1)->getContent();
        $this->assertEquals('Fair river! in thy bright, clear flow', trim($content));
    }
*/
    public function testFetchMessageHeader()
    {
        $mail = new Storage\Maildir(['dirname' => $this->maildir]);

        $subject = $mail->getMessage(1)->subject;
        $this->assertEquals('Simple Message', $subject);
    }

    public function testFetchMessageBody()
    {
        $mail = new Storage\Maildir(['dirname' => $this->maildir]);

        $content = $mail->getMessage(3)->getContent();
        list($content) = explode("\n", $content, 2);
        $this->assertEquals('Fair river! in thy bright, clear flow', trim($content));
    }

    public function testFetchWrongSize()
    {
        $mail = new Storage\Maildir(['dirname' => $this->maildir]);

        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('id does not exist');
        $mail->getSize(0);
    }

    public function testFetchWrongMessageBody()
    {
        $mail = new Storage\Maildir(['dirname' => $this->maildir]);

        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('id does not exist');
        $mail->getMessage(0);
    }

    public function testFailedRemove()
    {
        $mail = new Storage\Maildir(['dirname' => $this->maildir]);

        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessage('maildir is (currently) read-only');
        $mail->removeMessage(1);
    }

    public function testHasFlag()
    {
        $mail = new Storage\Maildir(['dirname' => $this->maildir]);

        $this->assertFalse($mail->getMessage(5)->hasFlag(Storage::FLAG_SEEN));
        $this->assertTrue($mail->getMessage(5)->hasFlag(Storage::FLAG_RECENT));
        $this->assertTrue($mail->getMessage(2)->hasFlag(Storage::FLAG_FLAGGED));
        $this->assertFalse($mail->getMessage(2)->hasFlag(Storage::FLAG_ANSWERED));
    }

    public function testGetFlags()
    {
        $mail = new Storage\Maildir(['dirname' => $this->maildir]);

        $flags = $mail->getMessage(1)->getFlags();
        $this->assertTrue(isset($flags[Storage::FLAG_SEEN]));
        $this->assertContains(Storage::FLAG_SEEN, $flags);
    }

    public function testUniqueId()
    {
        $mail = new Storage\Maildir(['dirname' => $this->maildir]);

        $this->assertTrue($mail->hasUniqueId);
        $this->assertEquals(1, $mail->getNumberByUniqueId($mail->getUniqueId(1)));

        $ids = $mail->getUniqueId();
        $should_ids = [1 => '1000000000.P1.example.org', '1000000001.P1.example.org', '1000000002.P1.example.org',
                            '1000000003.P1.example.org', '1000000004.P1.example.org'];
        foreach ($ids as $num => $id) {
            $this->assertEquals($id, $should_ids[$num]);

            if ($mail->getNumberByUniqueId($id) != $num) {
                $this->fail('reverse lookup failed');
            }
        }
    }

    public function testWrongUniqueId()
    {
        $mail = new Storage\Maildir(['dirname' => $this->maildir]);

        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('unique id not found');
        $mail->getNumberByUniqueId('this_is_an_invalid_id');
    }

    public function testCurIsFile()
    {
        \rename($this->maildir . 'cur', $this->maildir . 'cur-isFileTest');
        \touch($this->maildir . 'cur');

        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid maildir given');
        new Storage\Maildir(['dirname' => $this->maildir]);
    }

    public function testNewIsFile()
    {
        \rename($this->maildir . 'new', $this->maildir . 'new-isFileTest');
        \touch($this->maildir . 'new');

        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid maildir given');
        new Storage\Maildir(['dirname' => $this->maildir]);
    }

    public function testTmpIsFile()
    {
        \touch($this->maildir . 'tmp');

        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid maildir given');
        new Storage\Maildir(['dirname' => $this->maildir]);
    }

    public function testNotReadableCur()
    {
        \chmod($this->maildir . 'cur', 0);

        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessage('cannot open maildir');
        new Storage\Maildir(['dirname' => $this->maildir]);
    }

    public function testNotReadableNew()
    {
        \chmod($this->maildir . 'new', 0);

        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessage('cannot read recent mails in maildir');
        new Storage\Maildir(['dirname' => $this->maildir]);
    }

    public function testCountFlags()
    {
        $mail = new Storage\Maildir(['dirname' => $this->maildir]);
        $this->assertEquals($mail->countMessages(Storage::FLAG_DELETED), 0);
        $this->assertEquals($mail->countMessages(Storage::FLAG_RECENT), 1);
        $this->assertEquals($mail->countMessages(Storage::FLAG_FLAGGED), 1);
        $this->assertEquals($mail->countMessages(Storage::FLAG_SEEN), 4);
        $this->assertEquals($mail->countMessages([Storage::FLAG_SEEN, Storage::FLAG_FLAGGED]), 1);
        $this->assertEquals($mail->countMessages([Storage::FLAG_SEEN, Storage::FLAG_RECENT]), 0);
    }

    public function testFetchPart()
    {
        $mail = new Storage\Maildir(['dirname' => $this->maildir]);
        $this->assertEquals($mail->getMessage(4)->getPart(2)->contentType, 'text/x-vertical');
    }

    public function testPartSize()
    {
        $mail = new Storage\Maildir(['dirname' => $this->maildir]);
        $this->assertEquals($mail->getMessage(4)->getPart(2)->getSize(), 88);
    }

    public function testSizePlusPlus()
    {
        rename(
            $this->maildir . '/cur/1000000000.P1.example.org:2,S',
            $this->maildir . '/cur/1000000000.P1.example.org,S=123:2,S'
        );
        rename(
            $this->maildir . '/cur/1000000001.P1.example.org:2,FS',
            $this->maildir . '/cur/1000000001.P1.example.org,S=456:2,FS'
        );
        $mail = new Storage\Maildir(['dirname' => $this->maildir]);
        $shouldSizes = [1 => 123, 456, 694, 452, 497];


        $sizes = $mail->getSize();
        $this->assertEquals($shouldSizes, $sizes);
    }

    public function testSingleSizePlusPlus()
    {
        rename(
            $this->maildir . '/cur/1000000001.P1.example.org:2,FS',
            $this->maildir . '/cur/1000000001.P1.example.org,S=456:2,FS'
        );
        $mail = new Storage\Maildir(['dirname' => $this->maildir]);

        $size = $mail->getSize(2);
        $this->assertEquals(456, $size);
    }
}
