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

    protected function createdResponse($data, $message = "Data Berhasil Dibuat")
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], 201);
    }

    protected function errorResponse($message = "Terjadi Kesalahan", $code = 400)
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'data' => null
        ], $code);
    }

    protected function unauthorizedResponse($message = "Unauthorized", $code = 401)
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'data' => null
        ], $code);
    }
}