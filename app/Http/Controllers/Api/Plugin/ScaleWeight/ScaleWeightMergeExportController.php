<?php

namespace App\Http\Controllers\Api\Plugin\ScaleWeight;

use App\Exports\ScaleWeightMergeExport;
use App\Http\Controllers\Controller;
use App\Model\CloudStorage;
use App\Model\Project\Project;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ScaleWeightMergeExportController extends Controller
{
    public function export(Request $request)
    {
        $tenant = strtolower($request->header('Tenant'));
        $key = Str::random(16);
        $fileName = strtoupper($tenant)
            .' - Scale Weight Merge Report - '
            .date('dMY', strtotime($request->get('date_from')))
            .'-'
            .date('dMY', strtotime($request->get('date_to')));
        $fileExt = 'xlsx';
        $path = 'tmp/'.$tenant.'/'.$key.'.'.$fileExt;
        $cat = ($request->has('cat')) ? $request->get('cat') : [];
        $result = Excel::store(
            new ScaleWeightMergeExport(
                $request->get('date_from'),
                $request->get('date_to'),
                $request->get('header'),
                $cat
            ),
            $path,
            env('STORAGE_DISK')
        );

        if (! $result) {
            return response()->json([
                'message' => 'Failed to export',
            ], 422);
        }

        $cloudStorage = new CloudStorage();
        $cloudStorage->file_name = $fileName;
        $cloudStorage->file_ext = $fileExt;
        $cloudStorage->feature = 'scale weight merge';
        $cloudStorage->key = $key;
        $cloudStorage->path = $path;
        $cloudStorage->disk = env('STORAGE_DISK');
        $cloudStorage->project_id = Project::where('code', strtolower($tenant))->first()->id;
        $cloudStorage->owner_id = auth()->user()->id;
        $cloudStorage->expired_at = Carbon::now()->addDay(1);
        $cloudStorage->download_url = env('API_URL').'/download?key='.$key;
        $cloudStorage->save();

        return response()->json([
            'data' => [
                'url' => $cloudStorage->download_url,
            ],
        ], 200);
    }
}
