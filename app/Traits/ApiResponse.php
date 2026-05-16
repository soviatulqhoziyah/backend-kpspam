<?php

namespace App\Traits;

trait ApiResponse
{
    protected function successResponse($data, $message = "Operasi Berhasil", $code = 200)
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $code);
    }

    protected function errorResponse($message = "Terjadi Kesalahan", $code = 400)
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'data' => null
        ], $code);
    }
}