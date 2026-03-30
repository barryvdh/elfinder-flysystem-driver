<?php

namespace Barryvdh\elFinderFlysystemDriver\Tests;

use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use League\Flysystem\Filesystem;

class DriverTest extends TestCase
{
    /** @var Filesystem  */
    protected $filesystem;

    protected function setUp(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $adapter = new InMemoryFilesystemAdapter();
        $filesystem = new Filesystem($adapter);

        // Set a fake file
        $filesystem->write('dir1/file1.txt', 'Hello!');

        $this->filesystem = $filesystem;
    }

    public function testScanDirRoot()
    {
        $driver = new TestDriver();
        $driver->mount([
            'path' => '/',
            'filesystem' => $this->filesystem,
        ]);

        $this->assertEmpty($driver->error(), implode(', ', $driver->error()));

        $result = $driver->scandir($driver->encode('/'));

        $this->assertEmpty($driver->error(), implode(', ', $driver->error()));
        $this->assertCount(1, $result);
        $this->assertEquals('dir1', $result[0]['name']);
    }

    public function testScanDir1()
    {
        $driver = new TestDriver();
        $driver->mount([
            'path' => '/',
            'filesystem' => $this->filesystem,
        ]);

        $this->assertEmpty($driver->error(), implode(', ', $driver->error()));

        $result = $driver->scandir($driver->encode('dir1'));

        $this->assertEmpty($driver->error(), implode(', ', $driver->error()));
        $this->assertCount(1, $result);
        $this->assertEquals('file1.txt', $result[0]['name']);
    }

    public function testFile1()
    {
        $driver = new TestDriver();
        $driver->mount([
            'path' => '/',
            'filesystem' => $this->filesystem,
        ]);

        $this->assertEmpty($driver->error(), implode(', ', $driver->error()));

        $result = $driver->fstat($driver->encode('dir1/file1.txt'));

        $this->assertEmpty($driver->error(), implode(', ', $driver->error()));
        $this->assertEquals('file1.txt', $result['name']);
        $this->assertEquals('text/plain', $result['mime']);

        $content = $driver->getContents($driver->encode('dir1/file1.txt'));
        $this->assertEquals('Hello!', $content);
    }

    public function testResize()
    {
        $driver = new TestDriver();
        $driver->mount([
            'path' => '/',
            'filesystem' => $this->filesystem,
        ]);

        // see https://upload.wikimedia.org/wikipedia/commons/e/e1/White_Pixel_1x1.jpg
        $this->filesystem->write('dir1/file1.jpeg', base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wgARCAABAAEDAREAAhEBAxEB/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQBAQAAAAAAAAAAAAAAAAAAAAD/2gAMAwEAAhADEAAAAX8P/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABAf/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPxB//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPxB//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxB//9k='));

        $this->assertEmpty($driver->error(), implode(', ', $driver->error()));

        $result = $driver->resize($driver->encode('dir1/file1.jpeg'), 10, 10, 0, 0);

        $this->assertEmpty($driver->error(), implode(', ', $driver->error()));
        $this->assertEquals('file1.jpeg', $result['name']);
        $this->assertEquals('image/jpeg', $result['mime']);
        $this->assertEquals(10, $result['height']);
        $this->assertEquals(10, $result['width']);
    }
}
