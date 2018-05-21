<?php

namespace Dzava\Lighthouse\Exceptions;

class AuditFailedException extends \Exception
{
    protected $output;

    public function __construct($url, $output = null)
    {
        parent::__construct("Audit of '{$url}' failed");

        $this->output = $output;
    }

    public function getOutput()
    {
        return $this->output;
    }
}
