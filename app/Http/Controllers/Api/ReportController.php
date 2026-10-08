<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use Illuminate\Http\JsonResponse;

/**
 * Pelaporan konten oleh pengguna — pengganti pramoderasi (PRD §4.1).
 * Semua pengguna terautentikasi boleh melaporkan.
 */
class ReportController extends Controller
{
    public function store(StoreReportRequest $request): JsonResponse
    {
        $class = StoreReportRequest::TYPES[$request->string('reportable_type')->toString()];

        $reportable = $class::query()->findOrFail($request->integer('reportable_id'));

        $report = Report::query()->create([
            'reporter_id' => $request->user()->getKey(),
            'reportable_type' => $reportable->getMorphClass(),
            'reportable_id' => $reportable->getKey(),
            'reason' => $request->string('reason')->toString(),
            'status' => Report::STATUS_OPEN,
        ]);

        return (new ReportResource($report->load(['reporter', 'reportable'])))
            ->additional(['message' => 'Laporan dikirim. Terima kasih.'])
            ->response()
            ->setStatusCode(201);
    }
}
