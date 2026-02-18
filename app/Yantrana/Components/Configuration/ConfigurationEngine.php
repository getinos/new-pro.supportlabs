<?php

/**
 * ConfigurationEngine.php - Main component file
 *
 * This file is part of the Configuration component.
 *-----------------------------------------------------------------------------*/

namespace App\Yantrana\Components\Configuration;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Yantrana\Base\BaseEngine;
use App\Yantrana\Support\CommonTrait;
use Illuminate\Support\Facades\Artisan;
use App\Yantrana\Components\Media\MediaEngine;
use App\Yantrana\Components\Configuration\Repositories\ConfigurationRepository;
use App\Yantrana\Components\WhatsAppService\Services\WhatsAppConnectApiService;
use App\Yantrana\Components\Configuration\Interfaces\ConfigurationEngineInterface;


class ConfigurationEngine extends BaseEngine implements ConfigurationEngineInterface
{
    /**
     * @var CommonTrait - Common Trait
     */
    use CommonTrait;

    /**
     * @var ConfigurationRepository - Configuration Repository
     */
    protected $configurationRepository;

    /**
     * @var MediaEngine - Media Engine
     */
    protected $mediaEngine;
    /**
     * @var WhatsAppConnectApiService - WhatsApp Connect Api Service
     */
    protected $whatsAppConnectApiService;

    /**
     * Constructor
     *
     * @param  ConfigurationRepository  $configurationRepository  - Configuration Repository
     * @param  MediaEngine  $mediaEngine  - Media Engine
     * @param  WhatsAppConnectApiService  $whatsAppConnectApiService  - WhatsAppConnectApiService
     * @return void
     *-----------------------------------------------------------------------*/
    public function __construct(
        ConfigurationRepository $configurationRepository,
        MediaEngine $mediaEngine,
        WhatsAppConnectApiService $whatsAppConnectApiService
        )
    {
        $this->configurationRepository = $configurationRepository;
        $this->mediaEngine = $mediaEngine;
        $this->whatsAppConnectApiService = $whatsAppConnectApiService;
    }

    /**
     * Prepare Configuration.
     *
     * @param  string  $pageType
     * @return object
     *---------------------------------------------------------------- */
    public function prepareConfigurations($pageType)
    {
        // Get settings from config
        $defaultSettings = $this->getDefaultSettings(config('__settings.items.'.$pageType));
        // check if default settings exists
        if (__isEmpty($defaultSettings)) {
            return $this->engineResponse(18, null, __tr('Invalid page type.'));
        }
        $configurationSettings = $dbConfigurationSettings = [];
        // Check if default settings exists
        if (! __isEmpty($defaultSettings)) {
            // Get selected default settings
            $configurationCollection = $this->configurationRepository->fetchByNames(array_keys($defaultSettings));
            // check if configuration collection exists
            if (! __isEmpty($configurationCollection)) {
                foreach ($configurationCollection as $configuration) {
                    $dbConfigurationSettings[$configuration->name] = $this->castValue($configuration->data_type, $configuration->value);
                }
            }
            // Loop over the default settings
            foreach ($defaultSettings as $defaultSetting) {
                $configurationSettings[$defaultSetting['key']] = $this->prepareDataForConfiguration($dbConfigurationSettings, $defaultSetting);
            }
        }
        //check page type is currency
        if ($pageType == 'general') {
            $configurationSettings['timezone_list'] = $this->getTimeZone();
            $languages = getAppSettings('translation_languages');
            //set default language
            $languageList[] = [
                'id' => 'en',
                'name' => __tr('System Language (English)'),
                'status' => true,
            ];

            //check is not empty
            if (! __isEmpty($languages)) {
                foreach ($languages as $key => $language) {
                    if ($language['status']) {
                        $languageList[] = [
                            'id' => $language['id'],
                            'name' => $language['name'],
                            'status' => $language['status'],
                        ];
                    }
                }
            }
            $configurationSettings['languageList'] = $languageList;
        } elseif ($pageType == 'currency') {
            $configurationSettings['currencies'] = config('__currencies.currencies');
            $configurationSettings['currency_options'] = $this->generateCurrenciesArray($configurationSettings['currencies']['details']);
        } elseif ($pageType == 'premium-plans') {
            $defaultPlanDuration = $defaultSettings['plan_duration']['default'];
            $dbPlanDuration = $configurationSettings['plan_duration'];
            $configurationSettings['plan_duration'] = combineArray($defaultPlanDuration, $dbPlanDuration);
        } elseif ($pageType == 'premium-feature') {
            $defaultFeaturePlans = $defaultSettings['feature_plans']['default'];
            $dbFeaturePlans = $configurationSettings['feature_plans'];
            $configurationSettings['feature_plans'] = combineArray($defaultFeaturePlans, $dbFeaturePlans);
        } elseif ($pageType == 'email') {
            $configurationSettings['mail_drivers'] = configItem('mail_drivers');
            $configurationSettings['mail_encryption_types'] = configItem('mail_encryption_types');
        } elseif ($pageType == 'user') {
            $configurationSettings['admin_choice_display_mobile_number'] = configItem('admin_choice_display_mobile_number');
        }

        return $this->engineSuccessResponse([
            'configurationData' => $configurationSettings,
        ]);
    }

    /**
     * Process Configuration Store.
     *
     * @param  string  $pageType
     * @param  array  $inputData
     * @return object
     *---------------------------------------------------------------- */
    public function processConfigurationsStore($pageType, $inputData, $ignoreOtherFields = false)
    {
        \Log::info('=== CONFIGURATION STORE START ===');
        \Log::info('Step B1: Configuration store called', [
            'page_type' => $pageType,
            'input_keys' => array_keys($inputData ?? []),
            'has_hero_image' => isset($inputData['hero_image']),
            'hero_image_value' => $inputData['hero_image'] ?? 'N/A'
        ]);
        
        $dataForStoreOrUpdate = $configurationKeysForDelete = [];
        $isDataAddedOrUpdated = false;

        // Get settings from config
        $defaultSettings = $this->getDefaultSettings(config('__settings.items.'.$pageType));
        \Log::info('Step B2: Default settings retrieved', [
            'has_default_settings' => !__isEmpty($defaultSettings),
            'default_keys' => array_keys($defaultSettings ?? []),
            'hero_image_in_defaults' => isset($defaultSettings['hero_image'])
        ]);

        // check if default settings exists
        if (__isEmpty($defaultSettings)) {
            \Log::error('Step B2: Invalid page type - no default settings');
            return $this->engineResponse(18, ['show_message' => true], __tr('Invalid page type.'));
        }

        $isExtendedLicense = (getAppSettings('product_registration', 'licence') === 'dee257a8c3a2656b7d7fbe9a91dd8c7c41d90dc9');
        // Check if input data exists
        if (! __isEmpty($inputData)) {
            // Get selected default settings
            $configurationCollection = $this->configurationRepository->fetchByNames(array_keys($defaultSettings))->pluck('value', 'name')->toArray();
            \Log::info('Step B3: Current configuration from DB', [
                'current_hero_image' => $configurationCollection['hero_image'] ?? 'N/A'
            ]);
            
            //  loop through all the default items
            foreach ($defaultSettings as $defaultInputKey => $defaultInputValue) {
                $inputKey = $defaultInputKey;

                $inputValue = Arr::get($inputData, $inputKey);
                
                // Debug hero_image and logo fields specifically
                if ($inputKey === 'hero_image' || in_array($inputKey, ['logo_name', 'small_logo_name', 'favicon_name'])) {
                    \Log::info("Step B4: Processing {$inputKey}", [
                        'input_key' => $inputKey,
                        'input_value' => $inputValue,
                        'is_in_input_data' => array_key_exists($inputKey, $inputData),
                        'ignore_other_fields' => $ignoreOtherFields
                    ]);
                }
                // check if want to ignore other default fields so it can not be set as blank
                // ($ignoreOtherFields === true) and 
                if ((array_key_exists($inputKey, $inputData) === false)) {
                    if ($inputKey === 'hero_image') {
                        \Log::info('Step B5: Skipping hero_image - not in input data');
                    }
                    continue;
                }
                    
                // Special handling for logo fields: if empty string is explicitly sent, delete the image
                // This happens when user clicks the cross (X) icon to remove the image
                if (in_array($inputKey, ['logo_name', 'small_logo_name', 'favicon_name'])) {
                    \Log::info("Step B4a: Processing {$inputKey}", [
                        'input_key' => $inputKey,
                        'input_value' => $inputValue,
                        'is_empty' => __isEmpty($inputValue),
                        'existing_value' => $configurationCollection[$inputKey] ?? 'N/A'
                    ]);
                    
                    // If empty string is sent, check if it's an explicit deletion or just missing from form
                    // If there's an existing value and form sends empty, preserve it (similar to hero_image)
                    if (__isEmpty($inputValue)) {
                        $existingValue = $configurationCollection[$inputKey] ?? '';
                        // Only delete if there's no existing value (true deletion)
                        // OR if the field was explicitly set to empty in a separate upload request
                        // For form submissions, preserve existing value if upload just happened
                        if (__isEmpty($existingValue)) {
                            // No existing value, so empty is fine (delete)
                            \Log::info("Step B4b: {$inputKey} - No existing value, allowing deletion");
                            $castValues = '';
                            $dataForStoreOrUpdate[] = [
                                'name' => $inputKey,
                                'value' => $castValues,
                                'data_type' => $defaultSettings[$inputKey]['data_type'],
                            ];
                            continue; // Skip the rest of the processing for this field
                        } else {
                            // Existing value found, preserve it (form submission without upload)
                            \Log::info("Step B4c: {$inputKey} - Preserving existing value", [
                                'existing_value' => $existingValue,
                                'input_value' => $inputValue,
                                'input_value_after_preserve' => $existingValue
                            ]);
                            $inputValue = $existingValue;
                            \Log::info("Step B4d: {$inputKey} - Value preserved, continuing processing", [
                                'new_input_value' => $inputValue
                            ]);
                            // Continue processing with existing value
                        }
                    }
                }
                
                // Special handling for hero_image and whatsapp_qr_image: preserve existing value if form sends empty
                // This prevents overwriting the value when the form is submitted without the upload field being set
                if (in_array($inputKey, ['hero_image', 'whatsapp_qr_image'])) {
                    if (__isEmpty($inputValue)) {
                        $existingValue = $configurationCollection[$inputKey] ?? '';
                        if (!__isEmpty($existingValue)) {
                            \Log::info("Step B5a: Preserving existing {$inputKey} value", [
                                'existing_value' => $existingValue,
                                'input_value' => $inputValue
                            ]);
                            // Use existing value instead of empty value to preserve it
                            $inputValue = $existingValue;
                            // Continue processing with the existing value
                        }
                    }
                }
                
                // ignore the item for saving/updating if sent the empty values sent
                // BUT: Don't ignore if it's a logo field and we're explicitly setting it to empty (deletion)
                $ignoreEmptyCheck = Arr::get($defaultSettings, "$inputKey.ignore_empty");
                $isEmptyValue = !$inputValue;
                $isLogoField = in_array($inputKey, ['logo_name', 'small_logo_name', 'favicon_name']);
                
                if (in_array($inputKey, ['logo_name', 'small_logo_name', 'favicon_name'])) {
                    \Log::info("Step B5a-check: {$inputKey} - ignore_empty check", [
                        'ignore_empty' => $ignoreEmptyCheck,
                        'is_empty_value' => $isEmptyValue,
                        'input_value' => $inputValue,
                        'will_skip' => ($ignoreEmptyCheck && $isEmptyValue && !$isLogoField)
                    ]);
                }
                
                if ($ignoreEmptyCheck and $isEmptyValue and (!Str::startsWith($inputKey, [
                    'enable_' , 'allow_'
                ]))) {
                    // Skip only if it's not a logo field being explicitly deleted
                    if (!$isLogoField) {
                        if ($isLogoField) {
                            \Log::info("Step B5b: Skipping {$inputKey} - ignore_empty=true and value is empty");
                        }
                        continue;
                    } else {
                        \Log::info("Step B5c: {$inputKey} - ignore_empty=true but is logo field, continuing");
                    }
                }
                
                // Log before casting for logo fields to track flow
                if (in_array($inputKey, ['logo_name', 'small_logo_name', 'favicon_name'])) {
                    \Log::info("Step B5d: {$inputKey} - About to cast value", [
                        'input_value' => $inputValue,
                        'ignore_empty' => $ignoreEmptyCheck,
                        'hide_value' => array_get($defaultSettings[$inputKey], 'hide_value')
                    ]);
                }

                $castValues = $this->castValue(
                    ($defaultSettings[$inputKey]['data_type'] == 4)
                        ? 5 : $defaultSettings[$inputKey]['data_type'], // for Encode purpose only
                    $inputValue
                );
                
                // Log for hero_image and logo fields
                if ($inputKey === 'hero_image' || in_array($inputKey, ['logo_name', 'small_logo_name', 'favicon_name'])) {
                    \Log::info("Step B6: Casting {$inputKey} value", [
                        'original_value' => $inputValue,
                        'casted_value' => $castValues,
                        'data_type' => $defaultSettings[$inputKey]['data_type'],
                        'hide_value' => array_get($defaultSettings[$inputKey], 'hide_value')
                    ]);
                }
                
                if (array_get($defaultSettings[$inputKey], 'hide_value') and ! __isEmpty($inputValue)) {
                    $dataForStoreOrUpdate[] = [
                        'name' => $inputKey,
                        'value' => ($castValues and is_string($castValues) or is_numeric($castValues)) ? encrypt($castValues) : $castValues,
                        'data_type' => $defaultSettings[$inputKey]['data_type'],
                    ];
                    if ($inputKey === 'hero_image') {
                        \Log::info('Step B7: Added hero_image to store (hide_value=true, not empty)', [
                            'final_value' => ($castValues and is_string($castValues) or is_numeric($castValues)) ? '[ENCRYPTED]' : $castValues
                        ]);
                    }
                } elseif (! array_get($defaultSettings[$inputKey], 'hide_value')) {
                    $dataForStoreOrUpdate[] = [
                        'name' => $inputKey,
                        'value' => $castValues,
                        'data_type' => $defaultSettings[$inputKey]['data_type'],
                    ];
                    // Log for hero_image and logo fields
                    if ($inputKey === 'hero_image' || in_array($inputKey, ['logo_name', 'small_logo_name', 'favicon_name'])) {
                        \Log::info("Step B7: Added {$inputKey} to store (hide_value=false)", [
                            'final_value' => $castValues
                        ]);
                    }
                } elseif (array_get($defaultSettings[$inputKey], 'hide_value') and __isEmpty($inputValue)) {
                    $dataForStoreOrUpdate[] = [
                        'name' => $inputKey,
                        'value' => $castValues,
                        'data_type' => $defaultSettings[$inputKey]['data_type'],
                    ];
                    if ($inputKey === 'hero_image') {
                        \Log::info('Step B7: Added hero_image to store (hide_value=true, empty)', [
                            'final_value' => $castValues
                        ]);
                    }
                }

                if (in_array($inputKey, [
                    'stripe_test_secret_key',
                    'stripe_test_publishable_key',
                    ]) and $inputValue) {
                        if(!Str::contains($inputValue, '_test_')) {
                            return $this->engineFailedResponse([
                                'show_message' => true
                            ], __tr('Only test keys are accepted.'));
                    }
                }
                if (in_array($inputKey, [
                    'stripe_live_secret_key',
                    'stripe_live_publishable_key',
                    ]) and $inputValue) {
                        if(!Str::contains($inputValue, '_live_')) {
                            return $this->engineFailedResponse([
                                'show_message' => true
                            ], __tr('Only live keys are accepted.'));
                    }
                }

                 if (!$isExtendedLicense and in_array($inputKey, [
                        'stripe_live_secret_key',
                        'stripe_live_publishable_key',
                        ]) and $inputValue) {
                    return $this->engineFailedResponse([
                        'show_message' => true
                    ], __tr('You need to purchase extended license to use live keys.'));
                }

                 if (!$isExtendedLicense and in_array($inputKey, [
                        'embedded_signup_app_id',
                        'embedded_signup_app_secret',
                        'embedded_signup_config_id',
                        ]) and $inputValue) {
                    return $this->engineFailedResponse([
                        'show_message' => true
                    ], __tr('You need to purchase extended license to use Embedded Signup.'));
                }
                 if ($isExtendedLicense and in_array($inputKey, [
                        'embedded_signup_app_id',
                        'embedded_signup_config_id',
                        'embedded_signup_app_secret',
                        ]) and $inputValue) {
                    // connect webhook
                    $connectedWebhookSetup = $this->whatsAppConnectApiService->connectBaseWebhook(
                        $inputData['embedded_signup_app_id'],
                        $inputData['embedded_signup_app_secret']);
                    if(!isset($connectedWebhookSetup['success']) or !$connectedWebhookSetup['success']) {
                        return $this->engineFailedResponse([
                            'show_message' => true
                        ], __tr('Failed to register Webhook.'));
                    }
                }
            }
            // Send data for store or update
            // Always update if there's data to update, even if values haven't changed
            if (! __isEmpty($dataForStoreOrUpdate)) {
                \Log::info('Step B8: Preparing to save to database', [
                    'items_count' => count($dataForStoreOrUpdate),
                    'hero_image_in_data' => collect($dataForStoreOrUpdate)->contains('name', 'hero_image')
                ]);
                
                // Find hero_image in the data array for logging
                $heroImageData = collect($dataForStoreOrUpdate)->firstWhere('name', 'hero_image');
                if ($heroImageData) {
                    \Log::info('Step B8a: Hero image data to save', [
                        'name' => $heroImageData['name'],
                        'value' => $heroImageData['value'],
                        'data_type' => $heroImageData['data_type']
                    ]);
                }
                
                // Attempt to update the database
                $updateResult = $this->configurationRepository->storeOrUpdate($dataForStoreOrUpdate);
                
                \Log::info('Step B9: Database update result', [
                    'update_result' => $updateResult,
                    'hero_image_saved' => $updateResult ? 'Yes' : 'No'
                ]);
                
                // Consider it successful if we have data to update, regardless of whether values changed
                // This ensures the "Save" button always works even if no values were modified
                if ($updateResult) {
                    activityLog('Site configuration settings stored / updated.');
                    
                    // Verify hero_image was saved
                    $savedHeroImage = getAppSettings('hero_image');
                    \Log::info('Step B10: Verifying saved hero_image', [
                        'saved_value' => $savedHeroImage,
                        'matches_input' => $savedHeroImage === ($heroImageData['value'] ?? null)
                    ]);
                }
                // Always set as updated if we have data, even if storeOrUpdate returned false
                // (which might happen if values are identical, but we still want to show success)
                $isDataAddedOrUpdated = true;
            } else {
                \Log::warning('Step B8: No data to store/update', [
                    'input_data_keys' => array_keys($inputData ?? [])
                ]);
            }

            // Check if deleted keys deleted successfully
            if (
                ! __isEmpty($configurationKeysForDelete)
                and $this->configurationRepository->deleteConfiguration($configurationKeysForDelete)
            ) {
                $isDataAddedOrUpdated = true;
            }

            // Check if data added / updated or deleted
            if ($isDataAddedOrUpdated) {
                \Log::info('Step B11: Configuration save successful', [
                    'is_data_added_or_updated' => $isDataAddedOrUpdated,
                    'final_hero_image_value' => getAppSettings('hero_image')
                ]);
                \Log::info('=== CONFIGURATION STORE SUCCESS ===');
                return $this->engineResponse(21,[
                    'show_message' => true,
                    'messageType' => 'success',
                    'reloadPage' => true
                ], __tr('Settings updated successfully ... reloading'));
            }

            \Log::warning('Step B11: Nothing updated', [
                'is_data_added_or_updated' => $isDataAddedOrUpdated
            ]);
            return $this->engineResponse(14, ['show_message' => true], __tr('Nothing updated.'));
        }

        \Log::error('=== CONFIGURATION STORE FAILED ===', [
            'is_data_added_or_updated' => $isDataAddedOrUpdated,
            'input_data_empty' => __isEmpty($inputData)
        ]);
        return $this->engineFailedResponse(['show_message' => true], __tr('Something went wrong on server.'));
    }

    /**
     * Process product registration
     *
     * @param  array  $inputData
     * @return void
     *---------------------------------------------------------------- */
    public function processProductRegistration($inputData = [])
    {
        return $this->processConfigurationsStore('product_registration', [
            'product_registration' => [
                'registration_id' => array_get($inputData, 'registration_id', ''),
                'email' => array_get($inputData, 'your_email', ''),
                'licence' => array_get($inputData, 'licence_type', ''),
                'supported_until' => array_get($inputData, 'supported_until', ''),
                'registered_at' => now(),
                'signature' => sha1(
                    array_get($_SERVER, 'HTTP_HOST', '').
                        array_get($inputData, 'registration_id', '') . '4.5+'
                ),
            ],
        ]);
    }

    /**
     * Process product registration removal
     *
     * @return void
     *---------------------------------------------------------------- */
    public function processProductRegistrationRemoval()
    {
        try {
            // Initialize a cURL session
            $curl = curl_init();
            // Define the URL where you want to send the POST request
            $url = config('lwSystem.app_update_url') . "/api/app-update/deactivate-license"; // Replace with the actual URL
            // Define the POST fields, including the 'registration_id' parameter
            $postData = [
                'registration_id' => getAppSettings('product_registration', 'registration_id'), // Replace with the actual registration ID
            ];
            // Set the Origin header
            $headers = [
                'Origin: ' . array_get($_SERVER, 'HTTP_ORIGIN', ''), // Replace with your actual origin
            ];
            // Set cURL options
            curl_setopt($curl, CURLOPT_URL, $url); // Set the URL
            curl_setopt($curl, CURLOPT_POST, true); // Specify the request method as POST
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postData)); // Attach the POST fields
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers); // Attach the Origin header
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // Return the response as a string
            // Execute the cURL request
            $response = curl_exec($curl);
            // Check for errors
            if ($response === false) {
                $error = curl_error($curl);
                // echo "cURL Error: $error";
            } else {
                // Handle the response as needed
                // echo "Response: $response";
                // __logDebug($response);
            }
            // Close the cURL session
            curl_close($curl);
        } catch (\Throwable $th) {
            //throw $th;
        }

        return $this->processConfigurationsStore('product_registration', [
            'product_registration' => [
                'registration_id' => '',
                'email' => '',
                'licence' => '',
                'registered_at' => now(),
                'signature' => '',
            ],
        ]);
    }

    /**
     * Process and Save Subscription Plans
     *
     * @param  array  $inputData
     * @return object
     */
    public function processSubscriptionPlans($inputData)
    {
        // get plan for the request is made
        $configPlanId = array_get($inputData, 'config_plan_id');
        
        // Handle duration tabs visibility separately
        if ($configPlanId == 'duration_tabs_visibility') {
            $storedSubscriptionPlans = getAppSettings('subscription_plans');
            $durationTabsVisibility = [
                'monthly' => isset($inputData['monthly']) && ($inputData['monthly'] == 'on' || $inputData['monthly'] == 1),
                '6_months' => isset($inputData['6_months']) && ($inputData['6_months'] == 'on' || $inputData['6_months'] == 1),
                '12_months' => isset($inputData['12_months']) && ($inputData['12_months'] == 'on' || $inputData['12_months'] == 1),
                '18_months' => isset($inputData['18_months']) && ($inputData['18_months'] == 'on' || $inputData['18_months'] == 1),
            ];
            $storedSubscriptionPlans['duration_tabs_visibility'] = $durationTabsVisibility;
            
            if ($this->configurationRepository->storeOrUpdate([
                [
                    'name' => 'subscription_plans',
                    'value' => $storedSubscriptionPlans,
                    'data_type' => 4, // json
                ],
            ])) {
                return $this->engineSuccessResponse(['show_message' => true], __tr('Duration tabs visibility updated.'));
            }
            
            return $this->engineResponse(14, ['show_message' => true], __tr('Nothing updated.'));
        }
        
        // set as paid plan default
        $planType = 'paid';
        // get paid plan structure from config
        $plan = getConfigPaidPlans($configPlanId);
        // if not found then it may be free plan
        if (__isEmpty($plan)) {
            // set it as free
            $planType = 'free';
        }
        // get the stored subscription plan
        $storedSubscriptionPlans = getAppSettings('subscription_plans');
        // get the extended subscription plan with store settings
        $existingSubscriptionPlans = getPlans();
        // if the plan is free get it & its features
        if ($planType == 'free') {
            $existingSubscriptionPlan = $existingSubscriptionPlans[$planType] ?? [];
            $features = getConfigFreePlan('features');
            $planCharges = null;
        } else {
            // if the plan is paid get it & its features
            $existingSubscriptionPlan = $existingSubscriptionPlans[$planType][$configPlanId] ?? [];
            $features = getConfigPaidPlans("$configPlanId.features");
            $planCharges = getConfigPaidPlans("$configPlanId.charges");
        }
        // if the enabled is not sent by request then it must be disabled
        if (! isset($inputData['enabled'])) {
            $inputData['enabled'] = 0; // false
        }
        // collect & build features
        $featuresArray = [];
        if (! __isEmpty($features)) {
            // go through each feature
            foreach ($features as $featureKey => $feature) {
                $featuresArray[$featureKey] = [
                    // feature description
                    'description' => $featureKey.'_description',
                    // feature limit
                    'limit' => $featureKey.'_limit',
                ];
            }
        }
        // assign inputs to plan array to update
        arraySetAndGet($existingSubscriptionPlan, $inputData, [
            // plan title
            'title' => 'title',
            // plan enable or disable
            'enabled' => 'enabled',
            // plan features
            'features' => $featuresArray,
        ]);
        // Get charges off particular plan
        if (! __isEmpty($planCharges)) {
            foreach ($planCharges as $chargeKey => $chargeItem) {
                // if the enabled is not sent by request then it must be disabled
                if (! isset($inputData[$chargeKey.'_enabled'])) {
                    $inputData[$chargeKey.'_enabled'] = 0; // false
                }
                // assign charges inputs to plan array to update
                $inputData[$chargeKey.'_charge'] = (float) $inputData[$chargeKey.'_charge'];
                arraySetAndGet($existingSubscriptionPlan, $inputData, [
                    // is charge enabled
                    "charges.$chargeKey.enabled" => $chargeKey.'_enabled',
                    // price id
                    "charges.$chargeKey.price_id" => $chargeKey.'_plan_price_id',
                    // charges
                    "charges.$chargeKey.charge" => $chargeKey.'_charge',
                ]);
            }
        }
        // assign it to the existing data based on Plan Type
        if ($planType == 'free') {
            $storedSubscriptionPlans[$planType] = $existingSubscriptionPlan;
        } else {
            $storedSubscriptionPlans[$planType][$configPlanId] = $existingSubscriptionPlan;
        }
        // ask to store the updated data
        if ($this->configurationRepository->storeOrUpdate([
            [
                'name' => 'subscription_plans',
                'value' => $storedSubscriptionPlans,
                'data_type' => 4, // json
            ],
        ])) {
            return $this->engineSuccessResponse(['show_message' => true], __tr('Plan info updated.'));
        }

        return $this->engineResponse(14, ['show_message' => true], __tr('Nothing updated.'));
    }
}
