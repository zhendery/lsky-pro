<?php

namespace App\Http;

use Illuminate\Http\Response;

trait Result
{
    public function success(string $message = 'success', $data = []): Response
    {
        return $this->response(true, $message, $data);
    }

    public function fail(string $message = 'fail', $data = [], int $statusCode = 400): Response
    {
        return $this->response(false, $message, $data, $statusCode);
    }

    public function response(bool $status, string $message = '', $data = []): Response
    {
        $data = $data ?: new \stdClass;
        return response(compact('status', 'message', 'data'));
    }
}
