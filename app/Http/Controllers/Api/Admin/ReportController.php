<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResolveReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    private const STATUSES = [
        Report::STATUS_OPEN,
        Report::STATUS_RESOLVED,
        Report::STATUS_DISMISSED,
    ];

    public function index(Request $request): JsonResponse
    {
        $query = Report::query()
            ->with(['reporter', 'reportable'])
            ->latest('id');

        $status = $request->string('status')->toString();

        if (in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }

        return $this->paginated($query->paginate(20), ReportResource::class);
    }

    public function resolve(ResolveReportRequest $request, Report $report): JsonResponse
    {
        $report->forceFill([
            'status' => $request->string('status')->toString(),
            'resolved_by' => $request->user()->getKey(),
        ])->save();

        return (new ReportResource($report->refresh()->load(['reporter', 'reportable'])))
            ->additional(['message' => 'Laporan diperbarui.'])
            ->response();
    }
}
