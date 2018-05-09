<?php

namespace Dzava\Lighthouse;

use Dzava\Lighthouse\Exceptions\AuditFailedException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class Lighthouse
{
    protected $timeout = 60;
    protected $nodePath = null;
    protected $chromePath = null;
    protected $lighthousePath = 'lighthouse';
    protected $configPath = null;
    /** @var resource $config */
    protected $config = null;
    protected $categories = [];
    protected $options = [];
    protected $outputFormat = '--output=json';
    protected $availableFormats = ['json', 'html'];
    protected $defaultFormat = 'json';

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

        try {
            $process->setTimeout($this->timeout)->run();
        } catch (ProcessFailedException|ProcessTimedOutException $e) {
            throw new AuditFailedException($url, $e->getMessage());
        }

        if (!$process->isSuccessful()) {
            throw new AuditFailedException($url, $process->getErrorOutput());
        }

        return $process->getOutput();
    }

    /**
     * Enable the accessibility audit
     *
     * @return $this
     */
    public function accessibility()
    {
        $this->addCategory('accessibility');

        return $this;
    }

    /**
     * Enable the best practices audit
     *
     * @return $this
     */
    public function bestPractices()
    {
        $this->addCategory('best-practices');

        return $this;
    }

    /**
     * Enable the best performance audit
     *
     * @return $this
     */
    public function performance()
    {
        $this->addCategory('performance');

        return $this;
    }

    /**
     * Enable the progressive web app audit
     *
     * @return $this
     */
    public function pwa()
    {
        $this->addCategory('pwa');

        return $this;
    }

    /**
     * Enable the search engine optimization audit
     *
     * @return $this
     */
    public function seo()
    {
        $this->addCategory('seo');

        return $this;
    }

    /**
     * Disable Nexus 5X emulation
     *
     * @return $this
     */
    public function disableDeviceEmulation()
    {
        $this->setOption('--disable-device-emulation');

        return $this;
    }

    public function disableCpuThrottling()
    {
        $this->setOption('--disable-cpu-throttling');

        return $this;
    }

    public function disableNetworkThrottling()
    {
        $this->setOption('--disable-network-throttling');

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

        $this->outputFormat = implode(' ', array_map(function ($format) {
            return "--output=$format";
        }, $format));

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
        $this->chromePath = "CHROME_PATH=$path";

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
     * Enable a category of audits to use
     *
     * @param $category
     * @return $this
     */
    public function addCategory($category)
    {
        if (is_array($category)) {
            array_walk($category, [$this, 'addCategory']);

            return $this;
        }

        if (!in_array($category, $this->categories)) {
            $this->categories[] = $category;
        }

        return $this;
    }

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
        if ($this->configPath === null) {
            $this->buildConfig();
        }

        $command = array_merge([
            $this->chromePath,
            $this->nodePath,
            $this->lighthousePath,
            $this->outputFormat,
            '--quiet',
            "--config-path={$this->configPath}",
            $url,
        ], $this->processOptions());

        return escapeshellcmd(implode(' ', array_filter($command)));
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

    private function guessOutputFormatFromFile($path)
    {
        $format = pathinfo($path, PATHINFO_EXTENSION);

        if (!in_array($format, $this->availableFormats)) {
            $format = $this->defaultFormat;
        }

        return $format;
    }
}
