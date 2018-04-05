<?php

namespace Dzava\Lighthouse\Tests\Unit;

use Dzava\Lighthouse\Lighthouse;
use PHPUnit\Framework\TestCase;

class LighthouseTest extends TestCase
{
    /** @var Lighthouse $lighthouse */
    protected $lighthouse;

    protected function setUp()
    {
        parent::setUp();

        $this->lighthouse = (new Lighthouse())->setLighthousePath('./node_modules/lighthouse/lighthouse-cli/index.js');
    }

    /** @test */
    public function it_constructs_the_correct_command()
    {
        $command = $this->lighthouse
            ->withConfig('/my/config')
            ->getCommand('http://example.com');

        $this->assertEquals(implode(' ', [
            'node',
            './node_modules/lighthouse/lighthouse-cli/index.js',
            '--quiet',
            '--output=json',
            "--config-path=/my/config",
            "http://example.com",
            "--chrome-flags='--headless --disable-gpu --no-sandbox'",
        ]), $command);
    }

    /** @test */
    public function can_set_a_custom_node_binary()
    {
        $this->lighthouse->setNodeBinary('/my/node/binary');

        $command = $this->lighthouse->getCommand('http://example.com');

        $this->assertContains('/my/node/binary', $command);
    }

    /** @test */
    public function can_set_a_custom_lighthouse_script()
    {
        $this->lighthouse->setLighthousePath('/my/lighthouse.js');

        $command = $this->lighthouse->getCommand('http://example.com');

        $this->assertContains('/my/lighthouse.js', $command);
    }

    /** @test */
    public function can_set_chrome_flags()
    {
        $this->lighthouse->setChromeFlags('--my-flag');
        $command = $this->lighthouse->getCommand('http://example.com');
        $this->assertContains("--chrome-flags='--my-flag'", $command);

        $this->lighthouse->setChromeFlags(['--my-flag', '--second-flag']);
        $command = $this->lighthouse->getCommand('http://example.com');
        $this->assertContains("--chrome-flags='--my-flag --second-flag'", $command);
    }

    /** @test */
    public function can_set_the_output_file()
    {
        $this->lighthouse->setOutput('/tmp/report.json');

        $command = $this->lighthouse->getCommand('http://example.com');

        $this->assertContains("--output-path=/tmp/report.json", $command);
    }

    /** @test */
    public function can_disable_device_emulation()
    {
        $this->lighthouse->disableDeviceEmulation();

        $command = $this->lighthouse->getCommand('http://example.com');

        $this->assertContains('--disable-device-emulation', $command);
    }

    /** @test */
    public function can_disable_cpu_throttling()
    {
        $this->lighthouse->disableCpuThrottling();

        $command = $this->lighthouse->getCommand('http://example.com');

        $this->assertContains('--disable-cpu-throttling', $command);
    }

    /** @test */
    public function can_disable_network_throttling()
    {
        $this->lighthouse->disableNetworkThrottling();

        $command = $this->lighthouse->getCommand('http://example.com');

        $this->assertContains('--disable-network-throttling', $command);
    }
}
