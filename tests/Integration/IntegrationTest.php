<?php

namespace Dzava\Lighthouse\Tests\Integration;

use DMS\PHPUnitExtensions\ArraySubset\Assert;
use Dzava\Lighthouse\Exceptions\AuditFailedException;
use Dzava\Lighthouse\Lighthouse;
use PHPUnit\Framework\TestCase;

class IntegrationTest extends TestCase
{
    /** @var Lighthouse $lighthouse */
    protected $lighthouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lighthouse = (new Lighthouse())
            ->setChromePath('/usr/bin/google-chrome-stable')
            ->setLighthousePath('./node_modules/lighthouse/lighthouse-cli/index.js');
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
    public function runs_all_audits_by_default()
    {
        $report = $this->lighthouse
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
    public function categories_override_config()
    {
        $config = $this->createLighthouseConfig('performance');

        try {
            $report = $this->lighthouse
                ->withConfig($config)
                ->accessibility()
                ->performance(false)
                ->audit('http://example.com');
        } catch (AuditFailedException $e) {
            echo $e->getOutput();
        }

        file_put_contents('/tmp/report', $report);

        $this->assertReportIncludesCategory($report, 'Accessibility');
        $this->assertReportDoesNotIncludeCategory($report, 'Performance');
    }

    /** @test */
    public function throws_an_exception_when_the_audit_fails()
    {
        $this->expectException(AuditFailedException::class);

        $this->lighthouse
            ->performance()
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
            ->performance()
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
            ->performance()
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

    /** @test */
    public function accepts_an_array_with_the_config()
    {
        $report = $this->lighthouse
            ->withConfig([
                'extends' => 'lighthouse:default',
                'settings' => [
                    'onlyCategories' => ['pwa'],
                ],
            ])
            ->audit('http://example.com');

        $this->assertReportIncludesCategory($report, 'Progressive Web App');
        $this->assertReportDoesNotIncludeCategory($report, 'Performance');
    }

    /** @test */
    public function does_not_remove_the_provided_config_file()
    {
        $configPath = '/tmp/test-config.js';

        file_put_contents($configPath, 'module.exports = ' . json_encode([
                'extends' => 'lighthouse:default',
                'settings' => [
                    'onlyCategories' => ['performance'],
                ],
            ]));

        $this->lighthouse
            ->withConfig($configPath)
            ->audit('http://example.com');

        $this->lighthouse = null;

        $this->assertFileExists($configPath);
    }

    protected function assertReportIncludesCategory($report, $expectedCategory)
    {
        $report = json_decode($report, true);
        $categories = array_map(function ($category) {
            return $category['title'];
        }, $report['categories']);

        if (is_array($expectedCategory)) {
            sort($expectedCategory);
            sort($categories);
            Assert::assertArraySubset($expectedCategory, $categories);
        } else {
            $this->assertContains($expectedCategory, $categories);
        }
    }

    protected function assertReportDoesNotIncludeCategory($report, $expectedCategory)
    {
        $report = json_decode($report, true);
        $categories = array_map(function ($category) {
            return $category['title'];
        }, $report['categories']);

        $this->assertNotContains($expectedCategory, $categories);
    }

    protected function assertReportContainsHeader($report, $name, $value)
    {
        $report = json_decode($report, true);

        $headers = $report['configSettings']['extraHeaders'];
        $this->assertNotEmpty($headers, 'No extra headers found in report');
        $this->assertArrayHasKey($name, $headers, "Header '$name' is missing from report. [" . implode(', ', $headers) . ']');
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
            'json' => ['/tmp/report.json', '{'],
            'html' => ['/tmp/report.html', '<!--'],
        ];
    }

    private function createLighthouseConfig($categories)
    {
        if (!is_array($categories)) {
            $categories = [$categories];
        }

        return [
            'extends' => 'lighthouse:default',
            'settings' => [
                'onlyCategories' => $categories,
            ],
        ];
    }
}
