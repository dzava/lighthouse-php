<?php

namespace Dzava\Lighthouse;

use Dzava\Lighthouse\Exceptions\AuditFailedException;
use Symfony\Component\Process\Process;

class Lighthouse
{
    protected $timeout = 60;
    protected $nodePath = null;
    protected $environmentVariables = [];
    protected $lighthousePath = './node_modules/lighthouse/lighthouse-cli/index.js';
    protected $configPath = null;
    /** @var resource $config */
    protected $config = null;
    protected $categories = [];
    protected $options = [];
    protected $outputFormat = ['--output=json'];
    protected $availableFormats = ['json', 'html'];
    protected $defaultFormat = 'json';
    protected $headers = [];

    public function __construct()
    {
        $this->setChromeFlags(['--headless', '--disable-gpu', '--no-sandbox']);
    }

    /**
     * @param string $url
     * @return string
     * @throws AuditFailedException
     */
    public function audit($url)
    {
        $process = new Process($this->getCommand($url));

        $process->setTimeout($this->timeout)->run(null, $this->environmentVariables);

        if (!$process->isSuccessful()) {
            throw new AuditFailedException($url, $process->getErrorOutput());
        }

        return $process->getOutput();
    }

    /**
     * Enable the accessibility audit
     *
     * @param bool $enable
     * @return $this
     */
    public function accessibility($enable = true)
    {
        $this->setCategory('accessibility', $enable);

        return $this;
    }

    /**
     * Enable the best practices audit
     *
     * @param bool $enable
     * @return $this
     */
    public function bestPractices($enable = true)
    {
        $this->setCategory('best-practices', $enable);

        return $this;
    }

    /**
     * Enable the best performance audit
     *
     * @param bool $enable
     * @return $this
     */
    public function performance($enable = true)
    {
        $this->setCategory('performance', $enable);

        return $this;
    }

    /**
     * Enable the progressive web app audit
     *
     * @param bool $enable
     * @return $this
     */
    public function pwa($enable = true)
    {
        $this->setCategory('pwa', $enable);

        return $this;
    }

    /**
     * Enable the search engine optimization audit
     *
     * @param bool $enable
     * @return $this
     */
    public function seo($enable = true)
    {
        $this->setCategory('seo', $enable);

        return $this;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function withConfig($path)
    {
        if ($this->config) {
            fclose($this->config);
        }

        $this->configPath = $path;
        $this->config = null;

        return $this;
    }

    /**
     * @param string $path
     * @param null|string|array $format
     * @return $this
     */
    public function setOutput($path, $format = null)
    {
        $this->setOption('--output-path', $path);

        if ($format === null) {
            $format = $this->guessOutputFormatFromFile($path);
        }

        if (!is_array($format)) {
            $format = [$format];
        }

        $format = array_intersect($this->availableFormats, $format);

        $this->outputFormat = array_map(function ($format) {
            return "--output=$format";
        }, $format);

        return $this;
    }

    /**
     * @param string $format
     * @return $this
     */
    public function setDefaultFormat($format)
    {
        $this->defaultFormat = $format;

        return $this;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function setNodePath($path)
    {
        $this->nodePath = $path;

        return $this;
    }

    /**
     * @param string $path
     * @return $this
     */
    public function setLighthousePath($path)
    {
        $this->lighthousePath = $path;

        return $this;
    }

    /**
     * @param string $path
     * @return Lighthouse
     */
    public function setChromePath($path)
    {
        $this->environmentVariables['CHROME_PATH'] = $path;

        return $this;
    }

    /**
     * Set the flags to pass to the spawned Chrome instance
     *
     * @param array|string $flags
     * @return $this
     */
    public function setChromeFlags($flags)
    {
        if (is_array($flags)) {
            $flags = implode(' ', $flags);
        }

        $this->setOption('--chrome-flags', "'$flags'");

        return $this;
    }

    /**
     * @param array $headers
     * @return $this
     */
    public function setHeaders($headers)
    {
        if (empty($headers)) {
            $this->headers = [];

            return $this;
        }

        $headers = json_encode($headers);

        $this->headers = ["--extra-headers", $headers];

        return $this;
    }

    /**
     * @param int $timeout
     * @return $this
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * @param string $option
     * @param mixed $value
     * @return $this
     */
    public function setOption($option, $value = null)
    {
        if (($foundIndex = array_search($option, $this->options)) !== false) {
            $this->options[$foundIndex] = $option;

            return $this;
        }

        if ($value === null) {
            $this->options[] = $option;
        } else {
            $this->options[$option] = $value;
        }

        return $this;
    }

    public function getCommand($url)
    {
        if ($this->configPath === null || $this->config !== null) {
            $this->buildConfig();
        }

        $command = array_merge([
            $this->nodePath,
            $this->lighthousePath,
            ...$this->outputFormat,
            ...$this->headers,
            '--quiet',
            "--config-path={$this->configPath}",
            $url,
        ], $this->processOptions());

        return array_filter($command);
    }

    /**
     * Enable or disable a category
     *
     * @param $category
     * @return $this
     */
    protected function setCategory($category, $enable)
    {
        $index = array_search($category, $this->categories);

        if ($index !== false) {
            if ($enable == false) {
                unset($this->categories[$index]);
            }
        } elseif ($enable) {
            $this->categories[] = $category;
        }

        return $this;
    }

    /**
     * Creates the config file used during the audit
     *
     * @return $this
     */
    protected function buildConfig()
    {
        $config = tmpfile();
        $this->withConfig(stream_get_meta_data($config)['uri']);
        $this->config = $config;

        $r = 'module.exports = ' . json_encode([
                'extends' => 'lighthouse:default',
                'settings' => [
                    'onlyCategories' => $this->categories,
                ],
            ]);
        fwrite($config, $r);

        return $this;
    }

    /**
     * Convert the options array to an array that can be used
     * to construct the command arguments
     *
     * @return array
     */
    protected function processOptions()
    {
        return array_map(function ($value, $option) {
            return is_numeric($option) ? $value : "$option=$value";
        }, $this->options, array_keys($this->options));
    }

    /**
     * @param $path
     * @return string
     */
    private function guessOutputFormatFromFile($path)
    {
        $format = pathinfo($path, PATHINFO_EXTENSION);

        if (!in_array($format, $this->availableFormats)) {
            $format = $this->defaultFormat;
        }

        return $format;
    }
}
