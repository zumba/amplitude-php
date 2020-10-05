<?php

namespace Zumba\Amplitude;

use Throwable;

class AmplitudeException extends \Exception
{
    /**
     * @var int
     */
    private $httpCode;
    /**
     * @var array
     */
    private $postFields;

    public function __construct($message = "", $httpCode = 200, $postFields = [], $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->httpCode = $httpCode;
        $this->postFields = $postFields;
    }

    /**
     * @return int
     */
    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    /**
     * @return array
     */
    public function getPostFields(): array
    {
        return $this->postFields;
    }

}
