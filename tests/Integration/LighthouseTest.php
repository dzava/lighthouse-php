<?php

namespace Dzava\Lighthouse\Tests\Integration;

use Dzava\Lighthouse\Exceptions\AuditFailedException;
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
    public function can_run_only_one_audit()
    {
        $report = $this->lighthouse
            ->performance()
            ->audit('http://example.com');

        $this->assertReportIncludesCategory($report, 'Performance');
        $this->assertReportDoesNotIncludeCategory($report, 'Progressive Web App');
    }

    /** @test */
    public function can_run_all_audits()
    {
        $report = $this->lighthouse
            ->accessibility()
            ->bestPractices()
            ->performance()
            ->pwa()
            ->seo()
            ->audit('http://example.com');

        $this->assertReportIncludesCategory($report, [
            'Accessibility', 'Best Practices', 'Performance', 'Progressive Web App', 'SEO',
        ]);
    }

    /** @test */
    public function updates_the_config_when_a_category_is_added_or_removed()
    {
        $report = $this->lighthouse
            ->performance()
            ->audit('http://example.com');

        $this->assertReportIncludesCategory($report, 'Performance');
        $this->assertReportDoesNotIncludeCategory($report, 'Accessibility');

        $report = $this->lighthouse
            ->accessibility()
            ->audit('http://example.com');

        $this->assertReportIncludesCategory($report, 'Performance');
        $this->assertReportIncludesCategory($report, 'Accessibility');

        $report = $this->lighthouse
            ->accessibility(false)
            ->audit('http://example.com');

        $this->assertReportIncludesCategory($report, 'Performance');
        $this->assertReportDoesNotIncludeCategory($report, 'Accessibility');
    }

    /** @test */
    public function does_not_override_the_user_provided_config()
    {
        $config = $this->createLighthouseConfig('performance');
        $configPath = stream_get_meta_data($config)['uri'];

        $report = $this->lighthouse
            ->withConfig($configPath)
            ->accessibility()
            ->performance(false)
            ->audit('http://example.com');

        file_put_contents('/tmp/report', $report);

        $this->assertReportIncludesCategory($report, 'Performance');
        $this->assertReportDoesNotIncludeCategory($report, 'Accessibility');
    }

    /** @test */
    public function throws_an_exception_when_the_audit_fails()
    {
        $this->expectException(AuditFailedException::class);

        $this->lighthouse
            ->seo()
            ->audit('not-a-valid-url');
    }

    /**
     * @test
     * @dataProvider fileOutputDataProvider
     */
    public function outputs_to_a_file($outputPath, $content)
    {
        $this->removeTempFile($outputPath);

        $this->lighthouse
            ->setOutput($outputPath)
            ->seo()
            ->audit('http://example.com');

        $this->assertFileExists($outputPath);
        $this->assertFileStartsWith($content, $outputPath);
    }

    /** @test */
    public function outputs_both_json_and_html_reports_at_the_same_time()
    {
        $this->removeTempFile('/tmp/example.report.json')->removeTempFile('/tmp/example.report.html');

        $this->lighthouse
            ->setOutput('/tmp/example', ['json', 'html'])
            ->seo()
            ->audit('http://example.com');

        $this->assertFileExists('/tmp/example.report.html');
        $this->assertFileExists('/tmp/example.report.json');
    }

    /** @test */
    public function passes_the_http_headers_to_the_requests()
    {
        $report = $this->lighthouse
            ->setHeaders(['Cookie' => 'monster:blue', 'Authorization' => 'Bearer: ring'])
            ->performance()
            ->audit('http://example.com');

        $this->assertReportContainsHeader($report, 'Cookie', 'monster:blue');
        $this->assertReportContainsHeader($report, 'Authorization', 'Bearer: ring');
    }

    protected function assertReportIncludesCategory($report, $expectedCategory)
    {
        $report = json_decode($report, true);
        $categories = array_map(function ($category) {
            return $category['name'];
        }, $report['reportCategories']);

        if (is_array($expectedCategory)) {
            sort($expectedCategory);
            sort($categories);
            $this->assertArraySubset($expectedCategory, $categories);
        } else {
            $this->assertContains($expectedCategory, $categories);
        }
    }

    protected function assertReportDoesNotIncludeCategory($report, $expectedCategory)
    {
        $report = json_decode($report, true);
        $categories = array_map(function ($category) {
            return $category['name'];
        }, $report['reportCategories']);

        $this->assertNotContains($expectedCategory, $categories);
    }

    protected function assertReportContainsHeader($report, $name, $value)
    {
        $report = json_decode($report, true);

        $headers = $report['runtimeConfig']['extraHeaders'];
        $this->assertNotNull($headers, 'No extra headers found in report');
        $this->assertArrayHasKey($name, $headers, "Header '$name' is missing from report. [" . implode($headers, ', ') . ']');
        $this->assertEquals($value, $headers[$name]);
    }

    protected function removeTempFile($path)
    {
        if (file_exists($path)) {
            unlink($path);
        }

        return $this;
    }

    private function assertFileStartsWith($prefix, $outputPath)
    {
        $this->assertStringStartsWith(
            $prefix,
            file_get_contents($outputPath),
            "Failed asserting that the file '$outputPath' starts with '$prefix'"
        );

        return $this;
    }

    public function fileOutputDataProvider()
    {
        return [
            ['/tmp/report.json', '{'],
            ['/tmp/report.html', '<!--'],
        ];
    }

    private function createLighthouseConfig($categories)
    {
        if (!is_array($categories)) {
            $categories = [$categories];
        }

        $config = tmpfile();

        $r = 'module.exports = ' . json_encode([
                'extends' => 'lighthouse:default',
                'settings' => [
                    'onlyCategories' => $categories,
                ],
            ]);

        fwrite($config, $r);

        return $config;
    }
}
