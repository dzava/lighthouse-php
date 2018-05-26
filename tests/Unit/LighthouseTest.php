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

        $this->lighthouse = new Lighthouse();
    }

    /** @test */
    public function it_constructs_the_correct_command()
    {
        $command = $this->lighthouse
            ->withConfig('/my/config')
            ->getCommand('http://example.com');

        $this->assertEquals(implode(' ', [
            'lighthouse',
            '--output=json',
            '--quiet',
            "--config-path=/my/config",
            "http://example.com",
            "--chrome-flags='--headless --disable-gpu --no-sandbox'",
        ]), $command);
    }

    /** @test */
    public function can_set_a_custom_node_binary()
    {
        $this->lighthouse->setNodePath('/my/node/binary');

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
    public function can_set_chrome_path()
    {
        $this->lighthouse->setChromePath('/chrome');

        $command = $this->lighthouse->getCommand('http://example.com');

        $this->assertContains("CHROME_PATH=/chrome lighthouse", $command);
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

    /** @test */
    public function it_can_guess_the_output_format_from_the_file_extension()
    {
        $this->lighthouse->setOutput('/tmp/report.json');
        $command = $this->lighthouse->getCommand('http://example.com');
        $this->assertContains("--output=json", $command);
        $this->assertNotContains("--output=html", $command);

        $this->lighthouse->setOutput('/tmp/report.html');
        $command = $this->lighthouse->getCommand('http://example.com');
        $this->assertContains("--output=html", $command);
        $this->assertNotContains("--output=json", $command);

        $this->lighthouse->setOutput('/tmp/report.md');
        $command = $this->lighthouse->getCommand('http://example.com');
        $this->assertContains("--output=json", $command);
        $this->assertNotContains("--output=html", $command);

        $this->lighthouse->setOutput('/tmp/report');
        $command = $this->lighthouse->getCommand('http://example.com');
        $this->assertContains("--output=json", $command);
        $this->assertNotContains("--output=html", $command);
    }

    /** @test */
    public function can_override_the_output_format()
    {
        $this->lighthouse->setOutput('/tmp/report.json', 'html');
        $command = $this->lighthouse->getCommand('http://example.com');
        $this->assertContains("--output=html", $command);
        $this->assertNotContains("--output=json", $command);

        $this->lighthouse->setOutput('/tmp/report.md', ['html', 'json']);
        $command = $this->lighthouse->getCommand('http://example.com');
        $this->assertContains("--output=html", $command);
        $this->assertContains("--output=json", $command);

        $this->lighthouse->setOutput('/tmp/report.md', ['html', 'json', 'md']);
        $command = $this->lighthouse->getCommand('http://example.com');
        $this->assertContains("--output=html", $command);
        $this->assertContains("--output=json", $command);
        $this->assertNotContains("--output=md", $command);
    }

    /**
     * @test
     * @dataProvider reportCategoriesProvider
     */
    public function cannot_add_the_same_category_multiple_times($category, $method = null)
    {
        $method = $method ?? $category;
        $lighthouse = new MockLighthouse();

        $lighthouse->$method();
        $lighthouse->$method();
        $this->assertEquals(1, array_count_values($lighthouse->getCategories())[$category]);
    }

    /**
     * @test
     * @dataProvider reportCategoriesProvider
     */
    public function can_disable_a_category($category, $method = null)
    {
        $method = $method ?? $category;
        $lighthouse = new MockLighthouse();

        $lighthouse->$method();
        $this->assertContains($category, $lighthouse->getCategories());

        $lighthouse->$method(false);
        $this->assertNotContains($category, $lighthouse->getCategories());
    }

    /** @test */
    public function can_set_the_headers_using_an_array()
    {
        $lighthouse = new MockLighthouse();

        $lighthouse->setHeaders([
            'Cookie' => 'monster=blue',
            'Authorization' => 'Bearer: ring',
        ]);

        $this->assertContains('--extra-headers "{\"Cookie\":\"monster=blue\",\"Authorization\":\"Bearer: ring\"}"', $lighthouse->getCommand(''));
    }

    /**
     * @test
     * @dataProvider emptyHeadersProvider
     */
    public function does_not_pass_headers_when_empty()
    {
        $lighthouse = new MockLighthouse();

        $lighthouse->setHeaders(['Cookie' => 'monster=blue']);
        $this->assertContains('--extra-headers', $lighthouse->getCommand(''));

        $lighthouse->setHeaders([]);
        $this->assertNotContains('--extra-headers', $lighthouse->getCommand(''));
    }

    public function reportCategoriesProvider()
    {
        return [
            ['accessibility'],
            ['performance'],
            ['best-practices', 'bestPractices'],
            ['seo'],
            ['pwa'],
        ];
    }

    public function emptyHeadersProvider()
    {
        return [
            [null],
            [false],
            [[]],
        ];
    }
}

class MockLighthouse extends Lighthouse
{
    public function getCategories()
    {
        return $this->categories;
    }
}
