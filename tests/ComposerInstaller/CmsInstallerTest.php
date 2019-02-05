<?php

namespace Kirby\ComposerInstaller;

use PHPUnit\Framework\TestCase;

use Composer\Composer;
use Composer\Config;
use Composer\IO\NullIO;
use Composer\Package\Package;
use Composer\Package\RootPackage;
use Composer\Repository\InstalledArrayRepository;
use Composer\Util\Filesystem;

class CmsInstallerTest extends TestCase
{
    protected $composer;
    protected $installer;

    public function setUp()
    {
        // change to the test dir
        $this->testDir = dirname(__DIR__) . '/tmp';
        if (!is_dir($this->testDir)) {
            mkdir($this->testDir);
        }
        chdir($this->testDir);

        // initialize new Composer and Installer instances
        $io = new NullIO();
        $config = new Config();
        $config->merge([
            'config' => [
                'vendor-dir' => $this->testDir . '/vendor'
            ]
        ]);
        $this->filesystem = new Filesystem();
        $this->composer = new Composer();
        $this->composer->setConfig($config);
        $this->composer->setDownloadManager(new MockDownloadManager($io, false, $this->filesystem));
        $this->installer = new CmsInstaller($io, $this->composer);
    }

    public function tearDown()
    {
        $this->filesystem->removeDirectory($this->testDir);
    }

    public function testSupports()
    {
        $this->assertTrue($this->installer->supports('kirby-cms'));
        $this->assertFalse($this->installer->supports('kirby-plugin'));
        $this->assertFalse($this->installer->supports('amazing-cms'));
    }

    public function testGetInstallPathDefault()
    {
        $package = new Package('getkirby/cms', '1.0.0.0', '1.0.0');
        $package->setType('kirby-cms');
        $package->setInstallationSource('dist');
        $package->setDistType('mock');
        $this->assertEquals('kirby', $this->installer->getInstallPath($package));
    }

    public function testGetInstallPathCustomPaths()
    {
        $rootPackage = new RootPackage('getkirby/amazing-site', '1.0.0.0', '1.0.0');
        $rootPackage->setExtra([
            'kirby-cms-path' => 'cms'
        ]);
        $this->composer->setPackage($rootPackage);

        $package = new Package('getkirby/cms', '1.0.0.0', '1.0.0');
        $package->setType('kirby-cms');
        $package->setInstallationSource('dist');
        $package->setDistType('mock');
        $this->assertEquals('cms', $this->installer->getInstallPath($package));
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage The path . is an unsafe installation directory for getkirby/cms.
     */
    public function testGetInstallPathUnsafe1()
    {
        $rootPackage = new RootPackage('getkirby/amazing-site', '1.0.0.0', '1.0.0');
        $rootPackage->setExtra([
            'kirby-cms-path' => '.'
        ]);
        $this->composer->setPackage($rootPackage);

        $package = new Package('getkirby/cms', '1.0.0.0', '1.0.0');
        $package->setType('kirby-cms');
        $package->setInstallationSource('dist');
        $package->setDistType('mock');
        $this->installer->getInstallPath($package);
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage The path vendor is an unsafe installation directory for getkirby/cms.
     */
    public function testGetInstallPathUnsafe2()
    {
        $rootPackage = new RootPackage('getkirby/amazing-site', '1.0.0.0', '1.0.0');
        $rootPackage->setExtra([
            'kirby-cms-path' => 'vendor'
        ]);
        $this->composer->setPackage($rootPackage);

        $this->composer->getConfig()->merge([
            'config' => [
                'vendor-dir' => 'vendor'
            ]
        ]);

        $package = new Package('getkirby/cms', '1.0.0.0', '1.0.0');
        $package->setType('kirby-cms');
        $package->setInstallationSource('dist');
        $package->setDistType('mock');
        $this->installer->getInstallPath($package);
    }

    /**
     * @expectedException        InvalidArgumentException
     * @expectedExceptionMessage The path custom-vendor is an unsafe installation directory for getkirby/cms.
     */
    public function testGetInstallPathUnsafe3()
    {
        $rootPackage = new RootPackage('getkirby/amazing-site', '1.0.0.0', '1.0.0');
        $rootPackage->setExtra([
            'kirby-cms-path' => 'custom-vendor'
        ]);
        $this->composer->setPackage($rootPackage);

        $package = new Package('getkirby/cms', '1.0.0.0', '1.0.0');
        $package->setType('kirby-cms');
        $package->setInstallationSource('dist');
        $package->setDistType('mock');
        $this->assertEquals('custom-vendor', $this->installer->getInstallPath($package));

        $this->composer->getConfig()->merge([
            'config' => [
                'vendor-dir' => 'custom-vendor'
            ]
        ]);

        $package = new Package('getkirby/cms', '1.0.0.0', '1.0.0');
        $package->setType('kirby-cms');
        $package->setInstallationSource('dist');
        $package->setDistType('mock');
        $this->installer->getInstallPath($package);
    }

    public function testInstall()
    {
        $rootPackage = new RootPackage('getkirby/amazing-site', '1.0.0.0', '1.0.0');
        $this->composer->setPackage($rootPackage);

        $package = new Package('getkirby/cms', '1.0.0.0', '1.0.0');
        $package->setType('kirby-cms');
        $package->setInstallationSource('dist');
        $package->setDistType('mock');
        $package->setExtra([
            'with-vendor-dir' => true
        ]);
        $this->assertEquals('kirby', $this->installer->getInstallPath($package));
        $this->installer->install(new InstalledArrayRepository(), $package);
        $this->assertFileExists($this->testDir . '/kirby/index.php');
        $this->assertFileExists($this->testDir . '/kirby/vendor-created.txt');
        $this->assertDirectoryNotExists($this->testDir . '/kirby/vendor');
    }

    public function testUpdateCode()
    {
        $repo = new InstalledArrayRepository();

        $rootPackage = new RootPackage('getkirby/amazing-site', '1.0.0.0', '1.0.0');
        $this->composer->setPackage($rootPackage);

        $initial = new Package('getkirby/cms', '1.0.0.0', '1.0.0');
        $initial->setType('kirby-cms');
        $initial->setInstallationSource('dist');
        $initial->setDistType('mock');
        $initial->setExtra([
            'with-vendor-dir' => true
        ]);
        $this->assertEquals('kirby', $this->installer->getInstallPath($initial));
        $this->installer->install($repo, $initial);
        $repo->addPackage($initial);
        $this->assertFileExists($this->testDir . '/kirby/index.php');
        $this->assertFileExists($this->testDir . '/kirby/vendor-created.txt');
        $this->assertDirectoryNotExists($this->testDir . '/kirby/vendor');

        unlink($this->testDir . '/kirby/index.php');
        $this->assertFileNotExists($this->testDir . '/kirby/index.php');

        $target = new Package('getkirby/cms', '1.1.0.0', '1.1.0');
        $target->setType('kirby-cms');
        $target->setInstallationSource('dist');
        $target->setDistType('mock');
        $target->setExtra([
            'with-vendor-dir' => true
        ]);
        $this->assertEquals('kirby', $this->installer->getInstallPath($target));
        $this->installer->update($repo, $initial, $target);
        $this->assertFileExists($this->testDir . '/kirby/index.php');
        $this->assertFileExists($this->testDir . '/kirby/vendor-created.txt');
        $this->assertDirectoryNotExists($this->testDir . '/kirby/vendor');
    }
}
