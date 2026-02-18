<?php

/**
 * ConfigurationController.php - Controller file
 *
 * This file is part of the Configuration component.
 *-----------------------------------------------------------------------------*/

namespace App\Yantrana\Components\Configuration\Controllers;

use Artisan;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use App\Yantrana\Base\BaseRequest;
use App\Yantrana\Base\BaseController;
use App\Yantrana\Components\Configuration\ConfigurationEngine;
use App\Yantrana\Components\Configuration\Requests\ConfigurationRequest;
use App\Yantrana\Components\Auth\Models\AuthModel;
use App\Yantrana\Components\Vendor\Models\VendorModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ConfigurationController extends BaseController
{
    /**
     * @var ConfigurationEngine - Configuration Engine
     */
    protected $configurationEngine;

    /**
     * Constructor
     *

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
    /**
     * @param  ConfigurationEngine  $configurationEngine  - Configuration Engine
     * @return void
     *-----------------------------------------------------------------------*/
    public function __construct(ConfigurationEngine $configurationEngine)
    {
        $this->configurationEngine = $configurationEngine;
    }

    /**
     * Get Configuration Data.
     *
     * @param  string  $pageType
     * @return json object
     *---------------------------------------------------------------- */
    public function getConfiguration($pageType)
    {
        $processReaction = $this->configurationEngine->prepareConfigurations($pageType);
        if ($pageType === 'general') {
            $filters = $this->getDateRangeFilters();
            $tempUploadsData = $this->getAllTempUploads($filters);
            $vendorMediaFilters = $this->getVendorMediaDateRangeFilters();
            $vendorMediaData = $this->getAllVendorMediaUploads($vendorMediaFilters);
            $processReaction->updateData(null, array_merge($processReaction->data(), [
                'tempUploads' => $tempUploadsData['items'],
                'tempUploadsTotalSize' => $tempUploadsData['totalSizeReadable'],
                'tempUploadsFilters' => $filters,
                'vendorMediaUploads' => $vendorMediaData['items'],
                'vendorMediaTotalSize' => $vendorMediaData['totalSizeReadable'],
                'vendorMediaFilters' => $vendorMediaFilters,
                'vendorMediaPagination' => $vendorMediaData['pagination'],
            ]));
        }
        // check if settings available
        abortIf(!file_exists(resource_path("views/configuration/$pageType.blade.php")));
        // load view
        return $this->loadView('configuration.settings', $processReaction->data(), [
            'compress_page' => false
        ]);
    }

    /**
     * Get Configuration Data.
     *
     * @param  string  $pageType
     * @return json object
     *---------------------------------------------------------------- */
    public function processStoreConfiguration(ConfigurationRequest $request, $pageType)
    {
        /*         $validationRules = [
                    'pageType' => 'required',
                ]; */
        $request->validate($this->settingsValidationRules($request->pageType, [], $request->all()));
        $processReaction = $this->configurationEngine->processConfigurationsStore($pageType, $request->all());

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
        foreach ((array) config('__settings.items.' . $pageType) as $settingItemKey => $settingItemValue) {
            $settingsValidationRules = Arr::get($settingItemValue, 'validation_rules', []);
            $isValueHidden = Arr::get($settingItemValue, 'hide_value');
            if ($settingsValidationRules) {
                // skip validation if hidden value item and empty and the value is already set
                if (!array_key_exists($settingItemKey, $inputFields) or ($isValueHidden and !(Arr::has($inputFields, $settingItemKey)) and getAppSettings($settingItemKey))) {
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

    /**
     * Optimize system
     *
     * @param  BaseRequest  $request
     * @return void
     *---------------------------------------------------------------- */
    public function optimizeApp(BaseRequest $request)
    {
        Artisan::call('optimize');
        return $this->processResponse(21, [
            21 => __tr('Optimized')
        ], [
            'reloadPage' => true,
            'show_message' => true,
            'messageType' => 'success',
        ], true);
    }
    /**
     * Clear system cache
     *
     * @param  BaseRequest  $request
     * @return void
     *---------------------------------------------------------------- */
    public function clearOptimize(BaseRequest $request)
    {
        Artisan::call('optimize:clear');
        return $this->processResponse(21, [
            21 => __tr('Optimization Cleared')
        ], [
            'reloadPage' => true,
            'show_message' => true,
            'messageType' => 'success', 
        ], true);
    }

    /**
     * Register view
     *
     * @return void
     *---------------------------------------------------------------- */
    public function registerProductView()
    {
        return $this->loadView('configuration.licence-information');
    }

    /**
     * Process product registration
     *
     *
     * @return void
     *---------------------------------------------------------------- */
    public function processProductRegistration(ConfigurationRequest $request)
    {
        $processReaction = $this->configurationEngine->processProductRegistration($request->all());

        return $this->responseAction($this->processResponse($processReaction, [], [], true));
    }

    /**
     * Process product registration
     *
     *
     * @return void
     *---------------------------------------------------------------- */
    public function processProductRegistrationRemoval(ConfigurationRequest $request)
    {
        // remote removal
        $existingRegistrationId = getAppSettings('product_registration', 'registration_id');
        if (!$request->isMethod('post') and $existingRegistrationId and (!$request->registration_id or ($existingRegistrationId != $request->registration_id))) {
            abort(404, __tr('Invalid Request'));
        }

        $processReaction = $this->configurationEngine->processProductRegistrationRemoval();

        return $this->responseAction($this->processResponse($processReaction, [], [], true));
    }

    /**
     * Generate a short-lived, single-use migration token and log it.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateMigrationToken(Request $request)
    {
        $user = auth()->user();
        $token = Str::random(40);
        $expiresAt = now()->addMinutes(10);

        Cache::put('migration_one_time_token', $token, $expiresAt);

        Log::info('MANUAL_MIGRATION_TOKEN_GENERATED', [
            'token' => $token,
            'expires_at' => $expiresAt->toDateTimeString(),
            'user_id' => $user->id ?? null,
            'user_email' => $user->email ?? null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => __tr('Migration token generated and logged.'),
            'data' => [
                'token' => $token,
                'expires_at' => $expiresAt->toDateTimeString(),
            ],
        ]);
    }

    /**
     * Run database migrations using a validated token.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function runMigration(Request $request)
    {
        $user = auth()->user();
        $providedToken = $request->input('token');

        if (!$providedToken) {
            Log::warning('MIGRATION_ATTEMPT_NO_TOKEN', [
                'user_id' => $user->id ?? null,
                'user_email' => $user->email ?? null,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => __tr('Migration token is required.'),
            ], 400);
        }

        $storedToken = Cache::get('migration_one_time_token');

        if (!$storedToken || $storedToken !== $providedToken) {
            Log::warning('MIGRATION_ATTEMPT_INVALID_TOKEN', [
                'user_id' => $user->id ?? null,
                'user_email' => $user->email ?? null,
                'ip' => $request->ip(),
                'token_provided' => substr($providedToken, 0, 10) . '...',
            ]);

            return response()->json([
                'status' => 'error',
                'message' => __tr('Invalid or expired migration token. Please generate a new token.'),
            ], 403);
        }

        // Token is valid - mark as used and run migration
        Cache::forget('migration_one_time_token');

        Log::info('MIGRATION_STARTED', [
            'user_id' => $user->id ?? null,
            'user_email' => $user->email ?? null,
            'ip' => $request->ip(),
        ]);

        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();
            $outputLower = strtolower(trim($output));

            // Check if there was nothing to migrate
            $nothingToMigrate = (
                strpos($outputLower, 'nothing to migrate') !== false ||
                strpos($outputLower, 'nothing to migrate.') !== false ||
                empty(trim($output))
            );

            $message = $nothingToMigrate 
                ? __tr('Migration completed: Nothing to migrate. All migrations are up to date.')
                : __tr('Migration executed successfully.');

            Log::info('MIGRATION_COMPLETED', [
                'user_id' => $user->id ?? null,
                'user_email' => $user->email ?? null,
                'output' => $output,
                'nothing_to_migrate' => $nothingToMigrate,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'data' => [
                    'output' => $output,
                    'nothing_to_migrate' => $nothingToMigrate,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('MIGRATION_FAILED', [
                'user_id' => $user->id ?? null,
                'user_email' => $user->email ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => __tr('Migration failed: :error', ['error' => $e->getMessage()]),
            ], 500);
        }
    }

    /**
     * Delete selected temp uploads (central admin)
     *
     * @param BaseRequest $request
     * @return \Illuminate\Http\JsonResponse
     *---------------------------------------------------------------- */
    public function deleteTempUploads(BaseRequest $request)
    {
        $request->validate([
            'temp_uploads' => 'required|array|min:1',
            'temp_uploads.*' => 'string',
        ]);

        $basePath = $this->getTempUploadsBasePath();
        if (!File::isDirectory($basePath)) {
            return $this->processResponse(2, [
                2 => __tr('Temp uploads directory not found.')
            ], [], true);
        }

        $deleted = 0;
        $skipped = 0;
        foreach ($request->input('temp_uploads', []) as $item) {
            $parts = explode('|', $item, 2);
            if (count($parts) !== 2) {
                $skipped++;
                continue;
            }
            [$uid, $relativePath] = $parts;
            $uid = trim($uid);
            $relativePath = trim($relativePath);

            if (!preg_match('/^[A-Za-z0-9_-]+$/', $uid)) {
                $skipped++;
                continue;
            }
            if ($relativePath === '' || preg_match('~(^[\\/])|(\.\.)~', $relativePath)) {
                $skipped++;
                continue;
            }

            $tempDir = $basePath.DIRECTORY_SEPARATOR.$uid.DIRECTORY_SEPARATOR.'temp_uploads';
            $targetPath = $tempDir.DIRECTORY_SEPARATOR.$relativePath;
            $resolvedTarget = realpath($targetPath);
            $resolvedTempDir = realpath($tempDir);
            if (!$resolvedTarget || !$resolvedTempDir || !Str::startsWith($resolvedTarget, $resolvedTempDir)) {
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
            21 => __tr('Deleted __count__ item(s).', ['__count__' => $deleted])
        ], [
            'reloadPage' => true
        ], true));
    }

    /**
     * Delete selected vendor media files (central admin)
     *
     * @param BaseRequest $request
     * @return \Illuminate\Http\JsonResponse
     *---------------------------------------------------------------- */
    public function deleteVendorMedia(BaseRequest $request)
    {
        $request->validate([
            'vendor_media' => 'required|array|min:1',
            'vendor_media.*' => 'string',
        ]);

        $deleted = 0;
        $skipped = 0;
        foreach ($request->input('vendor_media', []) as $item) {
            $parts = explode('|', $item, 3);
            if (count($parts) !== 3) {
                $skipped++;
                continue;
            }
            [$vendorUid, $type, $relativePath] = $parts;
            $vendorUid = trim($vendorUid);
            $type = trim($type);
            $relativePath = trim($relativePath);

            if (!preg_match('/^[A-Za-z0-9_-]+$/', $vendorUid)) {
                $skipped++;
                continue;
            }
            if ($relativePath === '' || preg_match('~(^[\\/])|(\.\.)~', $relativePath)) {
                $skipped++;
                continue;
            }

            $mediaBasePaths = $this->getVendorMediaBasePaths($vendorUid);
            if (!isset($mediaBasePaths[$type])) {
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
            21 => __tr('Deleted __count__ vendor media file(s).', ['__count__' => $deleted])
        ], [
            'reloadPage' => true
        ], true));
    }

    /**
     * Get all temp uploads grouped by user UID
     *
     * @return array
     *---------------------------------------------------------------- */
    protected function getAllTempUploads(array $filters = []): array
    {
        [$fromTimestamp, $toTimestamp] = $this->parseDateRange($filters);
        $basePath = $this->getTempUploadsBasePath();
        if (!File::isDirectory($basePath)) {
            return [
                'items' => [],
                'totalSizeReadable' => $this->formatBytes(0),
            ];
        }

        $userDirs = File::directories($basePath);
        if (empty($userDirs)) {
            return [];
        }

        $uids = array_map(function ($dir) {
            return basename($dir);
        }, $userDirs);

        $users = AuthModel::whereIn('_uid', $uids)
            ->get(['_uid', 'first_name', 'last_name', 'email', 'status'])
            ->keyBy('_uid');

        $uploads = [];
        $totalBytes = 0;
        foreach ($userDirs as $dir) {
            $uid = basename($dir);
            $tempDir = $dir.DIRECTORY_SEPARATOR.'temp_uploads';
            if (!File::isDirectory($tempDir)) {
                continue;
            }

            $user = $users->get($uid);
            $isActive = $user && (int) $user->status === 1;
            $userName = null;
            if ($isActive) {
                $userName = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
                if ($userName === '' && !empty($user->email)) {
                    $userName = $user->email;
                }
            }

            foreach (File::allFiles($tempDir) as $file) {
                $size = $file->getSize();
                $modifiedAt = $file->getMTime();
                if ($fromTimestamp && $modifiedAt < $fromTimestamp) {
                    continue;
                }
                if ($toTimestamp && $modifiedAt > $toTimestamp) {
                    continue;
                }
                $totalBytes += $size;
                $relativePath = ltrim(str_replace($tempDir, '', $file->getPathname()), DIRECTORY_SEPARATOR);
                $uploads[] = [
                    'uid' => $uid,
                    'user_name' => $userName,
                    'user_active' => $isActive,
                    'file' => $relativePath,
                    'size' => $size,
                    'size_readable' => $this->formatBytes($size),
                    'modified_at' => $modifiedAt,
                    'modified' => date('Y-m-d H:i', $modifiedAt),
                ];
            }
        }

        usort($uploads, function ($a, $b) {
            if ($a['uid'] === $b['uid']) {
                return $b['modified_at'] <=> $a['modified_at'];
            }
            return strcmp($a['uid'], $b['uid']);
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
     * Get vendor media base paths for a vendor
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
     * Get all vendor media uploads list
     *
     * @param array $filters
     * @return array
     *---------------------------------------------------------------- */
    protected function getAllVendorMediaUploads(array $filters = []): array
    {
        [$fromTimestamp, $toTimestamp] = $this->parseDateRange($filters);
        $allowedPerPage = [10, 20, 50, 100, 500, 1000];
        $perPage = (int) ($filters['per_page'] ?? 10);
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }
        $page = max(1, (int) ($filters['page'] ?? 1));
        $vendorsBasePath = $this->resolveStoragePath(dirname(dirname(dirname(getPathByKey('whatsapp_image', ['{_uid}' => '__UID__'])))));
        if (!File::isDirectory($vendorsBasePath)) {
            return [
                'items' => [],
                'totalSizeReadable' => $this->formatBytes(0),
                'pagination' => [
                    'page' => 1,
                    'per_page' => $perPage,
                    'total_count' => 0,
                    'total_pages' => 1,
                ],
            ];
        }

        $vendorDirs = File::directories($vendorsBasePath);
        $vendorUids = array_map(function ($dir) {
            return basename($dir);
        }, $vendorDirs);

        $vendors = VendorModel::whereIn('_uid', $vendorUids)
            ->get(['_uid', 'title', 'status'])
            ->keyBy('_uid');

        $uploads = [];
        $totalBytes = 0;
        foreach ($vendorDirs as $dir) {
            $vendorUid = basename($dir);
            $vendor = $vendors->get($vendorUid);
            $isActive = $vendor && (int) $vendor->status === 1;
            $vendorName = $isActive ? ($vendor->title ?? null) : null;

            $mediaBasePaths = $this->getVendorMediaBasePaths($vendorUid);
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
                        'vendor_uid' => $vendorUid,
                        'vendor_name' => $vendorName,
                        'vendor_active' => $isActive,
                        'type' => $type,
                        'file' => $relativePath,
                        'size' => $size,
                        'size_readable' => $this->formatBytes($size),
                        'modified_at' => $modifiedAt,
                        'modified' => date('Y-m-d H:i', $modifiedAt),
                    ];
                }
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
     * Get base path for users temp uploads
     *
     * @return string
     *---------------------------------------------------------------- */
    protected function getTempUploadsBasePath(): string
    {
        $sample = getPathByKey('user_temp_uploads', ['{_uid}' => '__UID__']);
        $baseRelative = dirname(dirname($sample));

        return $this->resolveStoragePath($baseRelative);
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

    /**
     * Subscription Plans
     *
     * @return void
     *---------------------------------------------------------------- */
    public function subscriptionPlans()
    {
        return $this->loadView('configuration.subscription-plans', [
            'planDetails' => getPaidPlans(),
            'freePlan' => getFreePlan(),
            'planStructure' => getConfigPaidPlans(),
            'freePlanStructure' => getConfigFreePlan(),
        ]);
    }

    /**
     * Update Plan Settings
     *
     *
     * @return void
     *---------------------------------------------------------------- */
    public function subscriptionPlansProcess(BaseRequest $request)
    {
        // Handle duration tabs visibility separately - skip validation
        if ($request->config_plan_id == 'duration_tabs_visibility') {
            $processReaction = $this->configurationEngine->processSubscriptionPlans($request->all());
            return $this->responseAction($this->processResponse($processReaction, [], [], true));
        }
        
        // set as paid plan default
        $planType = 'paid';
        // get paid plan structure from config
        $plan = getConfigPaidPlans($request->config_plan_id);
        // if not found then it may be free plan
        if (__isEmpty($plan)) {
            // set it as free
            $planType = 'free';
        }

        $validationRules = [
            'title' => 'required|min:3',
        ];

        // if the plan is free get it & its features
        if ($planType == 'free') {
            $features = getConfigFreePlan('features');
            $planCharges = null;
        } else {
            // if the plan is paid get it & its features
            $features = getConfigPaidPlans("{$request->config_plan_id}.features");
            $planCharges = getConfigPaidPlans("{$request->config_plan_id}.charges");
        }

        $isPlanEnabled = ($request->enabled == 'on') or ($request->enabled == 1) or ($request->enabled == true);

        // if($request->enabled == 'on') {
        if (! __isEmpty($features)) {
            // go through each feature
            foreach ($features as $featureKey => $feature) {
                $validationRules[$featureKey.'_limit'] = 'required|integer|min:-1';
            }
        }

        $isChargesPresent = 0;

        if (! __isEmpty($planCharges)) {
            foreach ($planCharges as $chargeKey => $chargeItem) {
                if ($request->{$chargeKey.'_enabled'}) {
                    $isChargesPresent++;
                    $validationRules[$chargeKey.'_enabled'] = [
                        Rule::in(['on', 1]),
                    ];
                    $validationRules[$chargeKey.'_plan_price_id'] = 'nullable|starts_with:price_';
                    $validationRules[$chargeKey.'_charge'] = 'numeric|min:0.1';
                }
            }
        }

        if (! $isChargesPresent and ($planType != 'free') and $isPlanEnabled) {
            $validationRules['charges'] = 'required';
        }
        // }
        $request->validate($validationRules, [
            'charges.required' => __tr('You need to select at least one charge for the plan.'),
        ]);
        $processReaction = $this->configurationEngine->processSubscriptionPlans($request->all());

        return $this->responseAction($this->processResponse($processReaction, [], [], true));
    }

    public function createStripeWebhook()
    {
        if (!config('cashier.secret')) {
            return $this->processResponse(2, [], [
                'show_message' => true,
                'message' => __tr('Missing Stripe keys. First add keys & update and then ask to process to create webhook.'),
            ], true);
        }
        // webhook
        $webhookUrl = getViaSharedUrl(route('cashier.webhook'));
        try {
            $stripe = new \Stripe\StripeClient(config('cashier.secret')); // config('cashier.secret')
            $webhookCreated = $stripe->webhookEndpoints->create([
            // https://laravel.com/docs/10.x/billing#handling-stripe-webhooks
            // https://docs.stripe.com/api/webhook_endpoints/create
            // copied from /vendor/laravel/cashier/src/Console/WebhookCommand.php
            'enabled_events' => [
                'customer.subscription.created',
                'customer.subscription.updated',
                'customer.subscription.deleted',
                'customer.updated',
                'customer.deleted',
                'payment_method.automatically_updated',
                'invoice.payment_action_required',
                'invoice.payment_succeeded',
            ],
            'url' => $webhookUrl,
            ]);
            if ($webhookCreated and $webhookCreated['status'] == 'enabled') {
                $apiMode = $webhookCreated['livemode'] ? 'live' : 'testing';
                $now = now();
                // store webhook created info
                $this->configurationEngine->processConfigurationsStore('internals', [
                    'payment_gateway_info' => [
                        'auto_stripe_webhook_info' => [
                            $apiMode => [
                                'created_at' => $now,
                                'response' => $webhookCreated
                            ]
                        ]
                    ]
                ]);
                // store webhook created secret
                $this->configurationEngine->processConfigurationsStore('payment', [
                    'stripe_'. $apiMode .'_webhook_secret' => $webhookCreated['secret']
                ], true);

                if ($apiMode == 'testing') {
                    updateClientModels([
                        'lastTestWebhookCreatedAt' => formatDateTime($now),
                    ]);
                } else {
                    updateClientModels([
                        'lastLiveWebhookCreatedAt' => formatDateTime($now),
                    ]);
                }

                return $this->processResponse(1, [], [
                    'show_message' => true,
                    'message' => __tr('Stripe Webhook created successfully'),
                ], false);
            }
            return $this->processResponse(2, [], [
                'show_message' => true,
                'message' => __tr('Failed to create Stripe Webhook created, you may need to do it manually'),
            ], true);
        } catch (\Throwable $th) {
            return $this->processResponse(2, [], [
                'show_message' => true,
                'message' => $th->getMessage(),
            ], true);
        }
        return $this->processResponse(2, [
            2 => __tr('Failed to create Stripe Webhook created, you may need to do it manually')
        ], [
            'show_message' => true
        ], true);
    }
}
