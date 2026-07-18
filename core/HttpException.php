<?php

class HttpException extends RuntimeException
{
    protected $status;
    protected $details;
    protected $headers;

    public function __construct($status, $message = '', array $details = [], array $headers = [], Throwable $previous = null)
    {
        $this->status = max(400, min(599, (int) $status));
        $this->details = $details;
        $this->headers = $headers;
        parent::__construct((string) $message, $this->status, $previous);
    }

    public function status()
    {
        return $this->status;
    }

    public function details()
    {
        return $this->details;
    }

    public function headers()
    {
        return $this->headers;
    }
}
