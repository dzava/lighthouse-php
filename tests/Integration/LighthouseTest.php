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
    public function can_specify_audits_using_an_array()
    {
        $report = $this->lighthouse
            ->addCategory(['best-practices', 'performance', 'pwa'])
            ->audit('http://example.com');

        $this->assertReportIncludesCategory($report, ['Best Practices', 'Performance', 'Progressive Web App']);
        $this->assertReportDoesNotIncludeCategory($report, 'Accessibility');
        $this->assertReportDoesNotIncludeCategory($report, 'SEO');
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
}
