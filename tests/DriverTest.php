<?php

namespace Barryvdh\elFinderFlysystemDriver\Tests;

use Barryvdh\elFinderFlysystemDriver\Driver;
use League\Flysystem\Adapter\Local;
use PHPUnit\Framework\TestCase;
use League\Flysystem\Filesystem;
use League\Flysystem\Memory\MemoryAdapter;

class DriverTest extends TestCase
{
    /** @var Filesystem  */
    protected $filesystem;

    protected function setUp(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $adapter = new MemoryAdapter();
        $filesystem = new Filesystem($adapter);

        // Set a fake file
        $filesystem->put('dir1/file1.txt', 'Hello!');

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

}
