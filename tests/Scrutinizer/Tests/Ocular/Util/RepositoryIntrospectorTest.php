<?php

namespace Scrutinizer\Tests\Ocular\Util;

use Scrutinizer\Ocular\Util\RepositoryIntrospector;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class RepositoryInspectorTest extends \PHPUnit_Framework_TestCase
{
    private $tmpDirs = array();

    public function setUp()
    {
        $tmpDir = $this->getTempDir();
        $this->exec('git config --global user.email "scrutinizer-ci@github.com"', $tmpDir);
        $this->exec('git config --global user.name "Scrutinizer-CI"', $tmpDir);
    }

    public function testGetQualifiedName()
    {
        $tmpDir = $this->getTempDir();
        $this->installRepository('https://github.com/schmittjoh/metadata.git', $tmpDir);

        $introspector = new RepositoryIntrospector($tmpDir);
        $this->assertEquals('g/schmittjoh/metadata', $introspector->getQualifiedName());
    }

    public function testGetCurrentRevision()
    {
        $tmpDir = $this->getTempDir();
        mkdir($tmpDir, 0777, true);

        $this->exec('git init', $tmpDir);
        file_put_contents($tmpDir.'/foo', 'foo');
        $this->exec('git add . && git commit -m "adds foo"', $tmpDir);

        $expectedRev = $this->exec('git rev-parse HEAD', $tmpDir);

        $introspector = new RepositoryIntrospector($tmpDir);
        $headRev = $introspector->getCurrentRevision();

        $this->assertInternalType('string', $headRev);
        $this->assertEquals($expectedRev, $headRev);
    }

    /**
     * @expectedException Symfony\Component\Process\Exception\ProcessFailedException;
     */
    public function testGetCurrentRevisionFail()
    {
        $tmpDir = $this->getTempDir();
        mkdir($tmpDir, 0777, true);

        $this->exec('git init', $tmpDir);

        $expectedRev = $this->exec('git rev-parse HEAD', $tmpDir);

        $introspector = new RepositoryIntrospector($tmpDir);
        $headRev = $introspector->getCurrentRevision();
    }

    /**
     * @depends testGetCurrentRevision
     */
    public function testGetCurrentParents()
    {
        $tmpDir = $this->getTempDir();
        mkdir($tmpDir, 0777, true);

        $this->exec('git init', $tmpDir);
        file_put_contents($tmpDir.'/foo', 'foo');
        $this->exec('git add . && git commit -m "adds foo"', $tmpDir);

        $introspector = new RepositoryIntrospector($tmpDir);
        $headRev = $introspector->getCurrentRevision();

        file_put_contents($tmpDir.'/bar', 'bar');
        $this->exec('git add . && git commit -m "adds bar"', $tmpDir);
        $this->assertEquals(array($headRev), $introspector->getCurrentParents());
    }

    protected function tearDown()
    {
        parent::tearDown();

        $fs = new Filesystem();
        foreach ($this->tmpDirs as $dir) {
            $fs->remove($dir);
        }
    }

    private function exec($cmd, $dir)
    {
        $proc = new Process($cmd, $dir);
        if ($proc->run() !== 0) {
            throw new ProcessFailedException($proc);
        }

        return trim($proc->getOutput());
    }

    private function getTempDir()
    {
        $tmpDir = tempnam(sys_get_temp_dir(), 'ocular-intro');
        unlink($tmpDir);

        return $this->tmpDirs[] = $tmpDir;
    }

    private function installRepository($url, $dir)
    {
        $proc = new Process('git clone '.$url.' '.$dir);
        if (0 !== $proc->run()) {
            throw new ProcessFailedException($proc);
        }
    }
}