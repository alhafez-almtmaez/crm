<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SystemBrandAssetUploadRequest;
use App\Http\Requests\Admin\SystemSettingsUpdateRequest;
use App\Services\System\SystemSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SystemSettingsController extends Controller
{
    public function update(SystemSettingsUpdateRequest $request, SystemSettingsService $systemSettings): JsonResponse
    {
        $settings = $systemSettings->update($request->validated());
        app()->setLocale($settings['language']);

        return response()->json([
            'settings' => $settings,
        ]);
    }

    public function uploadBrandAsset(SystemBrandAssetUploadRequest $request): JsonResponse
    {
        $path = $request->file('file')->store('branding');

        try {
            $url = Storage::url($path);
        } catch (Throwable) {
            $url = $path;
        }

        return response()->json([
            'url' => $url,
            'message' => 'Image uploaded successfully.',
        ]);
    }
}
