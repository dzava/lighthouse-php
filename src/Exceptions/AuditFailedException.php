<?php

namespace Dzava\Lighthouse\Exceptions;

class AuditFailedException extends \Exception
{
    public $originalException;

    protected $output;

    /**
     * @param string $url
     * @param string|\Exception $originalException
     */
    public function __construct($url, $originalException)
    {
        $error = "Audit of '$url' failed: ";

        if (is_object($originalException)) {
            $this->originalException = $originalException;
            $error .= $originalException->getMessage();
        } else {
            $error .= $originalException;
        }

        parent::__construct($error);
    }
}
