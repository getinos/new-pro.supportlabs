<?php

/**
 * VendorSettingsController.php - Controller file
 *
 * This file is part of the Vendor component.
 *-----------------------------------------------------------------------------*/

namespace App\Yantrana\Components\Vendor\Controllers;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\File;
use App\Yantrana\Base\BaseRequest;
use App\Yantrana\Base\BaseController;
use App\Yantrana\Components\Vendor\VendorEngine;
use App\Yantrana\Components\BotReply\BotReplyEngine;
use App\Yantrana\Components\Vendor\VendorSettingsEngine;
use App\Yantrana\Components\Vendor\Requests\VendorSettingsRequest;

class VendorSettingsController extends BaseController
{
    /**
     * @var VendorSettingsEngine - VendorSettings Engine
     */
    protected $vendorSettingsEngine;

    /**
     * @var VendorEngine - VendorSettings Engine
     */

    /**
     * Resolve storage path based on configured filesystem root
     *
     * @param string $relativePath
     * @return string
     *---------------------------------------------------------------- */
    protected function resolveStoragePath(string $relativePath): string
    {
        $relativePath = ltrim($relativePath, '/\\');
        if (stripos($relativePath, 'public'.DIRECTORY_SEPARATOR) === 0 || stripos($relativePath, 'public/') === 0) {
            $relativePath = ltrim(substr($relativePath, 6), '/\\');
        }
        $diskRoot = config('filesystems.disks.'.config('filesystems.default', 'public-media-storage').'.root');

        $candidates = [];
        if ($diskRoot) {
            $candidates[] = rtrim($diskRoot, '/\\').DIRECTORY_SEPARATOR.$relativePath;
        }
        $candidates[] = public_path($relativePath);
        $candidates[] = storage_path('app'.DIRECTORY_SEPARATOR.$relativePath);

        foreach ($candidates as $path) {
            if (File::isDirectory($path)) {
                return $path;
            }
        }

        return $candidates[0] ?? public_path($relativePath);
    }
    protected $vendorEngine;

    /**
     * @var  BotReplyEngine $botReplyEngine - BotReply Engine
     */
    protected $botReplyEngine;

    /**
     * Constructor
     *
     * @param  VendorSettingsEngine  $vendorSettingsEngine  - VendorSettings Engine
     * @param  BotReplyEngine $botReplyEngine - BotReply Engine

     * @return void
     *-----------------------------------------------------------------------*/
    public function __construct(
        VendorSettingsEngine $vendorSettingsEngine,
        VendorEngine $vendorEngine,
        BotReplyEngine $botReplyEngine)
    {
        $this->vendorSettingsEngine = $vendorSettingsEngine;
        $this->vendorEngine = $vendorEngine;
        $this->botReplyEngine = $botReplyEngine;
    }

    /**
     * Vendor Settings View
     *
     * @return view
     *---------------------------------------------------------------- */
    public function index($pageType = 'general')
    {
        validateVendorAccess('administrative');
        $basicSettings = $this->vendorEngine->getBasicSettings();
        $processReaction = $this->vendorSettingsEngine->prepareConfigurations(Str::of($pageType)->slug('_'));
        $otherData = [];
        if($pageType == 'api-access') {
            // dynamicFields
            $otherData['dynamicFields'] = $this->botReplyEngine->preDataForBots()->data('dynamicFields');
        }
        if ($pageType == 'general') {
            $filters = $this->getDateRangeFilters();
            $tempUploadsData = $this->getUserTempUploads($filters);
            $otherData['tempUploads'] = $tempUploadsData['items'];
            $otherData['tempUploadsTotalSize'] = $tempUploadsData['totalSizeReadable'];
            $otherData['tempUploadsFilters'] = $filters;
            $vendorMediaFilters = $this->getVendorMediaDateRangeFilters();
            $vendorMediaData = $this->getVendorMediaUploads($vendorMediaFilters);
            $otherData['vendorMediaUploads'] = $vendorMediaData['items'];
            $otherData['vendorMediaTotalSize'] = $vendorMediaData['totalSizeReadable'];
            $otherData['vendorMediaFilters'] = $vendorMediaFilters;
            $otherData['vendorMediaPagination'] = $vendorMediaData['pagination'];
        }
        // check if settings available
        abortIf(!file_exists(resource_path("views/vendors/settings/$pageType.blade.php")));
        // load view
        return $this->loadView('vendors.settings.index', array_merge([
            'pageType' => $pageType,
            'basicSettings' => $basicSettings,
        ], $processReaction['data'], $otherData), [
            'compress_page' => false
        ]);
    }

    /**
     * Get Configuration Data.
     *
     * @param  BaseRequest  $request
     * @return json object
     *---------------------------------------------------------------- */
    public function update(VendorSettingsRequest $request)
    {
        validateVendorAccess('administrative');
        // restrict demo user
        if(isDemo() and isDemoVendorAccount()) {
            return $this->processResponse(22, [
                22 => __tr('Functionality is disabled in this demo.')
            ], [], true);
        }

        $validationRules = [
            'pageType' => 'required',
        ];
        $request->validate($this->settingsValidationRules($request->pageType, $validationRules,$request->all()));
        $processReaction = $this->vendorSettingsEngine->updateProcess($request->pageType, $request->all());

        return $this->responseAction($this->processResponse($processReaction, [], [], true));
    }

    /**
     * Get Configuration Data.
     *
     *
     * @return json object
     *---------------------------------------------------------------- */
    public function updateBasicSettings(BaseRequest $request)
    {
        validateVendorAccess('administrative');
        // restrict demo user
        if(isDemo() and isDemoVendorAccount()) {
            return $this->processResponse(22, [
                22 => __tr('Functionality is disabled in this demo.')
            ], [], true);
        }

        $validationRules = [
            'store_name' => [
                'required',
                'max:200',
            ],
        ];
        $request->validate($this->settingsValidationRules($request->pageType, $validationRules,$request->all()));
        $processReaction = $this->vendorSettingsEngine->updateBasicSettingsProcess($request->all());

        return $this->responseAction($this->processResponse($processReaction, [], [], true));
    }

    /**
     * Setup validation array
     *
     * @param  string  $pageType
     * @param  array  $validationRules
     * @param  array  $inputFields
     * @return mixed
     */
    protected function settingsValidationRules($pageType, $validationRules = [], $inputFields = [])
    {
        if (! $pageType) {
            return $validationRules;
        }
        foreach (config('__vendor-settings.items.' . $pageType) as $settingItemKey => $settingItemValue) {
            $settingsValidationRules = Arr::get($settingItemValue, 'validation_rules', []);
            $isValueHidden = Arr::get($settingItemValue, 'hide_value');
            if ($settingsValidationRules) {
                // skip validation if hidden value item and empty and the value is already set
                if(!array_key_exists($settingItemKey, $inputFields) or ($isValueHidden and empty(Arr::get($inputFields, $settingItemKey)) and getVendorSettings($settingItemKey))) {
                    continue;
                }
                $existingItemRules = Arr::get($validationRules, $settingItemKey, []);
                $validationRules[$settingItemKey] = array_merge(
                    ! is_array($existingItemRules) ? [$existingItemRules] : $existingItemRules,
                    $settingsValidationRules
                );
            }
        }
        return $validationRules;
    }



    public function disableSoundForMessageNotification() {
        // as it cones from flash memory so it won't be fresh data in single request
        // thats why we have applied reverse logic here
        $isSoundDisabled = getVendorSettings('is_disabled_message_sound_notification');
        $this->vendorSettingsEngine->updateProcess('internals', [
            'is_disabled_message_sound_notification' => $isSoundDisabled ? false : true
        ]);
        updateClientModels([
            'disableSoundForMessageNotification' =>  !$isSoundDisabled
        ]);
        return $this->processResponse(1, [
            1 => $isSoundDisabled ? __tr('Sound for message notification enabled') : __tr('Sound for message notification disabled')
        ], [], true);
    }

    /**
     * Delete selected user temp uploads
     *
     * @param BaseRequest $request
     * @return \Illuminate\Http\JsonResponse
     *---------------------------------------------------------------- */
    public function deleteUserTempUploads(BaseRequest $request)
    {
        validateVendorAccess('administrative');
        // restrict demo user
        if(isDemo() and isDemoVendorAccount()) {
            return $this->processResponse(22, [
                22 => __tr('Functionality is disabled in this demo.')
            ], [], true);
        }

        $request->validate([
            'temp_uploads' => 'required|array|min:1',
            'temp_uploads.*' => 'string',
        ]);

        $tempUploadRelativePath = getPathByKey('user_temp_uploads', ['{_uid}' => authUID()]);
        $tempUploadPath = $this->resolveStoragePath($tempUploadRelativePath);
        $deleted = 0;
        $skipped = 0;

        foreach ($request->input('temp_uploads', []) as $file) {
            $fileName = basename($file);
            if ($fileName !== $file) {
                $skipped++;
                continue;
            }

            $filePath = $tempUploadPath.DIRECTORY_SEPARATOR.$fileName;
            if (File::exists($filePath) && File::isFile($filePath)) {
                if (File::delete($filePath)) {
                    $deleted++;
                } else {
                    $skipped++;
                }
            } else {
                $skipped++;
            }
        }

        return $this->responseAction($this->processResponse(21, [
            21 => __tr('Deleted __count__ temp file(s).', ['__count__' => $deleted])
        ], [
            'reloadPage' => true
        ], true));
    }

    /**
     * Delete selected vendor media files
     *
     * @param BaseRequest $request
     * @return \Illuminate\Http\JsonResponse
     *---------------------------------------------------------------- */
    public function deleteVendorMedia(BaseRequest $request)
    {
        validateVendorAccess('administrative');
        // restrict demo user
        if(isDemo() and isDemoVendorAccount()) {
            return $this->processResponse(22, [
                22 => __tr('Functionality is disabled in this demo.')
            ], [], true);
        }

        $request->validate([
            'vendor_media' => 'required|array|min:1',
            'vendor_media.*' => 'string',
        ]);

        $vendorUid = getVendorUid();
        $mediaBasePaths = $this->getVendorMediaBasePaths($vendorUid);
        $deleted = 0;
        $skipped = 0;

        foreach ($request->input('vendor_media', []) as $item) {
            $parts = explode('|', $item, 2);
            if (count($parts) !== 2) {
                $skipped++;
                continue;
            }
            [$type, $relativePath] = $parts;
            $type = trim($type);
            $relativePath = trim($relativePath);

            if (!isset($mediaBasePaths[$type])) {
                $skipped++;
                continue;
            }
            if ($relativePath === '' || preg_match('~(^[\\/])|(\.\.)~', $relativePath)) {
                $skipped++;
                continue;
            }

            $basePath = $mediaBasePaths[$type];
            $targetPath = $basePath.DIRECTORY_SEPARATOR.$relativePath;
            $resolvedTarget = realpath($targetPath);
            $resolvedBase = realpath($basePath);
            if (!$resolvedTarget || !$resolvedBase || !Str::startsWith($resolvedTarget, $resolvedBase)) {
                $skipped++;
                continue;
            }

            if (File::isDirectory($resolvedTarget)) {
                if (File::deleteDirectory($resolvedTarget)) {
                    $deleted++;
                } else {
                    $skipped++;
                }
            } elseif (File::isFile($resolvedTarget)) {
                if (File::delete($resolvedTarget)) {
                    $deleted++;
                } else {
                    $skipped++;
                }
            } else {
                $skipped++;
            }
        }

        return $this->responseAction($this->processResponse(21, [
            21 => __tr('Deleted __count__ media file(s).', ['__count__' => $deleted])
        ], [
            'reloadPage' => true
        ], true));
    }

    /**
     * Get temp uploads list for current user
     *
     * @return array
     *---------------------------------------------------------------- */
    protected function getUserTempUploads(array $filters = []): array
    {
        [$fromTimestamp, $toTimestamp] = $this->parseDateRange($filters);
        $tempUploadRelativePath = getPathByKey('user_temp_uploads', ['{_uid}' => authUID()]);
        $tempUploadPath = $this->resolveStoragePath($tempUploadRelativePath);
        if (!File::isDirectory($tempUploadPath)) {
            return [];
        }

        $files = File::files($tempUploadPath);
        $totalBytes = 0;
        $uploads = array_map(function ($file) use (&$totalBytes) {
            $size = $file->getSize();
            $modifiedAt = $file->getMTime();
            $totalBytes += $size;
            return [
                'name' => $file->getFilename(),
                'size' => $size,
                'size_readable' => $this->formatBytes($size),
                'modified_at' => $modifiedAt,
                'modified' => date('Y-m-d H:i', $modifiedAt),
            ];
        }, $files);

        if ($fromTimestamp || $toTimestamp) {
            $totalBytes = 0;
            $uploads = array_values(array_filter($uploads, function ($item) use ($fromTimestamp, $toTimestamp, &$totalBytes) {
                if ($fromTimestamp && $item['modified_at'] < $fromTimestamp) {
                    return false;
                }
                if ($toTimestamp && $item['modified_at'] > $toTimestamp) {
                    return false;
                }
                $totalBytes += $item['size'];
                return true;
            }));
        }

        usort($uploads, function ($a, $b) {
            return $b['modified_at'] <=> $a['modified_at'];
        });

        return [
            'items' => $uploads,
            'totalSizeReadable' => $this->formatBytes($totalBytes),
        ];
    }

    /**
     * Get date range filters from request
     *
     * @return array
     *---------------------------------------------------------------- */
    protected function getDateRangeFilters(): array
    {
        return [
            'from_date' => request()->query('from_date'),
            'to_date' => request()->query('to_date'),
        ];
    }

    /**
     * Get date range filters for vendor media
     *
     * @return array
     *---------------------------------------------------------------- */
    protected function getVendorMediaDateRangeFilters(): array
    {
        return [
            'from_date' => request()->query('vendor_media_from_date'),
            'to_date' => request()->query('vendor_media_to_date'),
            'per_page' => request()->query('vendor_media_per_page'),
            'page' => request()->query('vendor_media_page'),
        ];
    }

    /**
     * Parse date range filters
     *
     * @param array $filters
     * @return array
     *---------------------------------------------------------------- */
    protected function parseDateRange(array $filters): array
    {
        $fromTimestamp = null;
        $toTimestamp = null;

        $fromDate = $filters['from_date'] ?? null;
        $toDate = $filters['to_date'] ?? null;

        if ($fromDate) {
            $parsed = strtotime($fromDate . ' 00:00:00');
            if ($parsed !== false) {
                $fromTimestamp = $parsed;
            }
        }
        if ($toDate) {
            $parsed = strtotime($toDate . ' 23:59:59');
            if ($parsed !== false) {
                $toTimestamp = $parsed;
            }
        }

        return [$fromTimestamp, $toTimestamp];
    }

    /**
     * Get vendor media base paths
     *
     * @param string $vendorUid
     * @return array
     *---------------------------------------------------------------- */
    protected function getVendorMediaBasePaths($vendorUid): array
    {
        return [
            'images' => $this->resolveStoragePath(getPathByKey('whatsapp_image', ['{_uid}' => $vendorUid])),
            'videos' => $this->resolveStoragePath(getPathByKey('whatsapp_video', ['{_uid}' => $vendorUid])),
            'documents' => $this->resolveStoragePath(getPathByKey('whatsapp_document', ['{_uid}' => $vendorUid])),
            'audios' => $this->resolveStoragePath(getPathByKey('whatsapp_audio', ['{_uid}' => $vendorUid])),
        ];
    }

    /**
     * Get vendor media uploads list
     *
     * @param array $filters
     * @return array
     *---------------------------------------------------------------- */
    protected function getVendorMediaUploads(array $filters = []): array
    {
        [$fromTimestamp, $toTimestamp] = $this->parseDateRange($filters);
        $allowedPerPage = [10, 20, 50, 100, 500, 1000];
        $perPage = (int) ($filters['per_page'] ?? 10);
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }
        $page = max(1, (int) ($filters['page'] ?? 1));
        $vendorUid = getVendorUid();
        $mediaBasePaths = $this->getVendorMediaBasePaths($vendorUid);

        $uploads = [];
        $totalBytes = 0;
        foreach ($mediaBasePaths as $type => $basePath) {
            if (!File::isDirectory($basePath)) {
                continue;
            }
            foreach (File::allFiles($basePath) as $file) {
                $size = $file->getSize();
                $modifiedAt = $file->getMTime();
                if ($fromTimestamp && $modifiedAt < $fromTimestamp) {
                    continue;
                }
                if ($toTimestamp && $modifiedAt > $toTimestamp) {
                    continue;
                }
                $totalBytes += $size;
                $relativePath = ltrim(str_replace($basePath, '', $file->getPathname()), DIRECTORY_SEPARATOR);
                $uploads[] = [
                    'type' => $type,
                    'file' => $relativePath,
                    'size' => $size,
                    'size_readable' => $this->formatBytes($size),
                    'modified_at' => $modifiedAt,
                    'modified' => date('Y-m-d H:i', $modifiedAt),
                ];
            }
        }

        usort($uploads, function ($a, $b) {
            return $b['modified_at'] <=> $a['modified_at'];
        });

        $totalCount = count($uploads);
        $totalPages = (int) ceil($totalCount / $perPage);
        if ($totalPages < 1) {
            $totalPages = 1;
        }
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $uploads = array_slice($uploads, $offset, $perPage);

        return [
            'items' => $uploads,
            'totalSizeReadable' => $this->formatBytes($totalBytes),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total_count' => $totalCount,
                'total_pages' => $totalPages,
            ],
        ];
    }

    /**
     * Format bytes to human readable
     *
     * @param int $bytes
     * @return string
     *---------------------------------------------------------------- */
    protected function formatBytes($bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        if ($bytes > 1) {
            return $bytes . ' bytes';
        }
        if ($bytes == 1) {
            return '1 byte';
        }
        return '0 bytes';
    }
}
