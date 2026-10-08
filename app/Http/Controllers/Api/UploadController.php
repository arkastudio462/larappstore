<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Developer\StoreUploadRequest;
use App\Services\UploadService;
use Illuminate\Http\JsonResponse;

class UploadController extends Controller
{
    public function store(StoreUploadRequest $request, UploadService $uploads): JsonResponse
    {
        $data = $uploads->store(
            $request->file('file'),
            $request->string('kind')->toString(),
        );

        return response()->json([
            'message' => 'Berkas terunggah.',
            'data' => $data,
        ]);
    }
}
