<?php
/**
 * ownCloud
 *
 * @author Robin Appelman
 * @copyright 2012 Robin Appelman icewind@owncloud.com
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Files\Storage;

abstract class Storage extends \PHPUnit_Framework_TestCase {
	/**
	 * @var \OC\Files\Storage\Storage instance
	 */
	protected $instance;

	/**
	 * the root folder of the storage should always exist, be readable and be recognized as a directory
	 */
	public function testRoot() {
		$this->assertTrue($this->instance->file_exists('/'), 'Root folder does not exist');
		$this->assertTrue($this->instance->isReadable('/'), 'Root folder is not readable');
		$this->assertTrue($this->instance->is_dir('/'), 'Root folder is not a directory');
		$this->assertFalse($this->instance->is_file('/'), 'Root folder is a file');
		$this->assertEquals('dir', $this->instance->filetype('/'));

		//without this, any further testing would be useless, not an actual requirement for filestorage though
		$this->assertTrue($this->instance->isUpdatable('/'), 'Root folder is not writable');
	}

	public function testDirectories() {
		$this->assertFalse($this->instance->file_exists('/folder'));

		$this->assertTrue($this->instance->mkdir('/folder'));

		$this->assertTrue($this->instance->file_exists('/folder'));
		$this->assertTrue($this->instance->is_dir('/folder'));
		$this->assertFalse($this->instance->is_file('/folder'));
		$this->assertEquals('dir', $this->instance->filetype('/folder'));
		$this->assertEquals(0, $this->instance->filesize('/folder'));
		$this->assertTrue($this->instance->isReadable('/folder'));
		$this->assertTrue($this->instance->isUpdatable('/folder'));

		$dh = $this->instance->opendir('/');
		$content = array();
		while ($file = readdir($dh)) {
			if ($file != '.' and $file != '..') {
				$content[] = $file;
			}
		}
		$this->assertEquals(array('folder'), $content);

		$this->assertFalse($this->instance->mkdir('/folder')); //cant create existing folders
		$this->assertTrue($this->instance->rmdir('/folder'));

		$this->assertFalse($this->instance->file_exists('/folder'));

		$this->assertFalse($this->instance->rmdir('/folder')); //cant remove non existing folders

		$dh = $this->instance->opendir('/');
		$content = array();
		while ($file = readdir($dh)) {
			if ($file != '.' and $file != '..') {
				$content[] = $file;
			}
		}
		$this->assertEquals(array(), $content);
	}

	/**
	 * test the various uses of file_get_contents and file_put_contents
	 */
	public function testGetPutContents() {
		$sourceFile = \OC::$SERVERROOT . '/tests/data/lorem.txt';
		$sourceText = file_get_contents($sourceFile);

		//fill a file with string data
		$this->instance->file_put_contents('/lorem.txt', $sourceText);
		$this->assertFalse($this->instance->is_dir('/lorem.txt'));
		$this->assertEquals($sourceText, $this->instance->file_get_contents('/lorem.txt'), 'data returned from file_get_contents is not equal to the source data');

		//empty the file
		$this->instance->file_put_contents('/lorem.txt', '');
		$this->assertEquals('', $this->instance->file_get_contents('/lorem.txt'), 'file not emptied');
	}

	/**
	 * test various known mimetypes
	 */
	public function testMimeType() {
		$this->assertEquals('httpd/unix-directory', $this->instance->getMimeType('/'));
		$this->assertEquals(false, $this->instance->getMimeType('/non/existing/file'));

		$textFile = \OC::$SERVERROOT . '/tests/data/lorem.txt';
		$this->instance->file_put_contents('/lorem.txt', file_get_contents($textFile, 'r'));
		$this->assertEquals('text/plain', $this->instance->getMimeType('/lorem.txt'));

		$pngFile = \OC::$SERVERROOT . '/tests/data/logo-wide.png';
		$this->instance->file_put_contents('/logo-wide.png', file_get_contents($pngFile, 'r'));
		$this->assertEquals('image/png', $this->instance->getMimeType('/logo-wide.png'));

		$svgFile = \OC::$SERVERROOT . '/tests/data/logo-wide.svg';
		$this->instance->file_put_contents('/logo-wide.svg', file_get_contents($svgFile, 'r'));
		$this->assertEquals('image/svg+xml', $this->instance->getMimeType('/logo-wide.svg'));
	}

	public function testCopyAndMove() {
		$textFile = \OC::$SERVERROOT . '/tests/data/lorem.txt';
		$this->instance->file_put_contents('/source.txt', file_get_contents($textFile));
		$this->instance->copy('/source.txt', '/target.txt');
		$this->assertTrue($this->instance->file_exists('/target.txt'));
		$this->assertEquals($this->instance->file_get_contents('/source.txt'), $this->instance->file_get_contents('/target.txt'));

		$this->instance->rename('/source.txt', '/target2.txt');
		$this->assertTrue($this->instance->file_exists('/target2.txt'));
		$this->assertFalse($this->instance->file_exists('/source.txt'));
		$this->assertEquals(file_get_contents($textFile), $this->instance->file_get_contents('/target.txt'));
	}

	public function testLocal() {
		$textFile = \OC::$SERVERROOT . '/tests/data/lorem.txt';
		$this->instance->file_put_contents('/lorem.txt', file_get_contents($textFile));
		$localFile = $this->instance->getLocalFile('/lorem.txt');
		$this->assertTrue(file_exists($localFile));
		$this->assertEquals(file_get_contents($localFile), file_get_contents($textFile));

		$this->instance->mkdir('/folder');
		$this->instance->file_put_contents('/folder/lorem.txt', file_get_contents($textFile));
		$this->instance->file_put_contents('/folder/bar.txt', 'asd');
		$this->instance->mkdir('/folder/recursive');
		$this->instance->file_put_contents('/folder/recursive/file.txt', 'foo');
		$localFolder = $this->instance->getLocalFolder('/folder');

		$this->assertTrue(is_dir($localFolder));

		// test below require to use instance->getLocalFile because the physical storage might be different
		$localFile = $this->instance->getLocalFile('/folder/lorem.txt');
		$this->assertTrue(file_exists($localFile));
		$this->assertEquals(file_get_contents($localFile), file_get_contents($textFile));

		$localFile = $this->instance->getLocalFile('/folder/bar.txt');
		$this->assertTrue(file_exists($localFile));
		$this->assertEquals(file_get_contents($localFile), 'asd');

		$localFile = $this->instance->getLocalFile('/folder/recursive/file.txt');
		$this->assertTrue(file_exists($localFile));
		$this->assertEquals(file_get_contents($localFile), 'foo');
	}

	public function testStat() {
		$textFile = \OC::$SERVERROOT . '/tests/data/lorem.txt';
		$ctimeStart = time();
		$this->instance->file_put_contents('/lorem.txt', file_get_contents($textFile));
		$this->assertTrue($this->instance->isReadable('/lorem.txt'));
		$ctimeEnd = time();
		$mTime = $this->instance->filemtime('/lorem.txt');
		$this->assertTrue($this->instance->hasUpdated('/lorem.txt', $ctimeStart - 1));
		$this->assertTrue($this->instance->hasUpdated('/', $ctimeStart - 1));

		$this->assertTrue(($ctimeStart - 1) <= $mTime);
		$this->assertTrue($mTime <= ($ctimeEnd + 1));
		$this->assertEquals(filesize($textFile), $this->instance->filesize('/lorem.txt'));

		$stat = $this->instance->stat('/lorem.txt');
		//only size and mtime are requered in the result
		$this->assertEquals($stat['size'], $this->instance->filesize('/lorem.txt'));
		$this->assertEquals($stat['mtime'], $mTime);

		$mtimeStart = time();
		$supportsTouch = $this->instance->touch('/lorem.txt');
		$mtimeEnd = time();
		if ($supportsTouch !== false) {
			$mTime = $this->instance->filemtime('/lorem.txt');
			$this->assertTrue(($mtimeStart - 1) <= $mTime);
			$this->assertTrue($mTime <= ($mtimeEnd + 1));

			$this->assertTrue($this->instance->hasUpdated('/lorem.txt', $mtimeStart - 1));

			if ($this->instance->touch('/lorem.txt', 100) !== false) {
				$mTime = $this->instance->filemtime('/lorem.txt');
				$this->assertEquals($mTime, 100);
			}
		}

		$mtimeStart = time();
		$fh = $this->instance->fopen('/lorem.txt', 'a');
		fwrite($fh, ' ');
		fclose($fh);
		clearstatcache();
		$mtimeEnd = time();
		$mTime = $this->instance->filemtime('/lorem.txt');
		$this->assertTrue(($mtimeStart - 1) <= $mTime);
		$this->assertTrue($mTime <= ($mtimeEnd + 1));

		$this->instance->unlink('/lorem.txt');
		$this->assertTrue($this->instance->hasUpdated('/', $mtimeStart - 1));
	}

	public function testSearch() {
		$textFile = \OC::$SERVERROOT . '/tests/data/lorem.txt';
		$this->instance->file_put_contents('/lorem.txt', file_get_contents($textFile, 'r'));
		$pngFile = \OC::$SERVERROOT . '/tests/data/logo-wide.png';
		$this->instance->file_put_contents('/logo-wide.png', file_get_contents($pngFile, 'r'));
		$svgFile = \OC::$SERVERROOT . '/tests/data/logo-wide.svg';
		$this->instance->file_put_contents('/logo-wide.svg', file_get_contents($svgFile, 'r'));
		$result = $this->instance->search('logo');
		$this->assertEquals(2, count($result));
		$this->assertContains('/logo-wide.svg', $result);
		$this->assertContains('/logo-wide.png', $result);
	}

	public function testFOpen() {
		$textFile = \OC::$SERVERROOT . '/tests/data/lorem.txt';

		$fh = @$this->instance->fopen('foo', 'r');
		if ($fh) {
			fclose($fh);
		}
		$this->assertFalse($fh);
		$this->assertFalse($this->instance->file_exists('foo'));

		$fh = $this->instance->fopen('foo', 'w');
		fwrite($fh, file_get_contents($textFile));
		fclose($fh);
		$this->assertTrue($this->instance->file_exists('foo'));

		$fh = $this->instance->fopen('foo', 'r');
		$content = stream_get_contents($fh);
		$this->assertEquals(file_get_contents($textFile), $content);
	}
}
