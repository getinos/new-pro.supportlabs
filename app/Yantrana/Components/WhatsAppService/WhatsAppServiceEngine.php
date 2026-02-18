<?php
/**
* WhatsAppServiceEngine.php - Main component file
*
* This file is part of the WhatsAppService component.
*-----------------------------------------------------------------------------*/

namespace App\Yantrana\Components\WhatsAppService;

use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Http\Client\Pool; 
use App\Yantrana\Base\BaseEngine;
use Illuminate\Support\Collection;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use App\Events\VendorChannelBroadcast;
use App\Jobs\ProcessCampaignMessagesJob;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Http\Client\RequestException;
use App\Yantrana\Components\Media\MediaEngine;
use Illuminate\Http\Client\ConnectionException;
use App\Yantrana\Components\Vendor\VendorSettingsEngine;
use App\Yantrana\Components\User\Repositories\UserRepository;
use App\Yantrana\Components\Configuration\ConfigurationEngine;
use App\Yantrana\Components\Contact\Repositories\LabelRepository;
use App\Yantrana\Components\Contact\Repositories\ContactRepository;
use App\Yantrana\Components\WhatsAppService\Services\OpenAiService;
use App\Yantrana\Components\BotReply\Repositories\BotReplyRepository;
use App\Yantrana\Components\BotReply\Repositories\BotFlowRepository;
use App\Yantrana\Components\Campaign\Repositories\CampaignRepository;
use App\Yantrana\Components\Contact\Repositories\ContactGroupRepository;
use App\Yantrana\Components\Contact\Repositories\GroupContactRepository;
use App\Yantrana\Components\WhatsAppService\Services\WhatsAppApiService;
use App\Yantrana\Components\Contact\Repositories\ContactCustomFieldRepository;
use App\Yantrana\Components\WhatsAppService\Services\WhatsAppConnectApiService;
use App\Yantrana\Components\WhatsAppService\Repositories\WhatsAppTemplateRepository;
use App\Yantrana\Components\WhatsAppService\Interfaces\WhatsAppServiceEngineInterface;
use App\Yantrana\Components\WhatsAppService\Repositories\WhatsAppMessageLogRepository;
use App\Yantrana\Components\WhatsAppService\Repositories\WhatsAppMessageQueueRepository;
use App\Yantrana\Components\WhatsAppService\Models\WhatsAppMessageLogModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Yantrana\Components\Flows\Services\WhatsAppFlowService;
use App\Yantrana\Components\BotReply\Services\FlowIntegrationService;
use App\Models\UserActiveFlow;

class WhatsAppServiceEngine extends BaseEngine implements WhatsAppServiceEngineInterface
{
    /**
     * @var ContactRepository - Contact Repository
     */
    protected $contactRepository;

    /**
     * @var ContactGroupRepository - ContactGroup Repository
     */
    protected $contactGroupRepository;

    /**
     * @var GroupContactRepository - ContactGroup Repository
     */
    protected $groupContactRepository;

    /**
     * @var WhatsAppTemplateRepository - WhatsApp Template Repository
     */
    protected $whatsAppTemplateRepository;

    /**
     * @var WhatsAppApiService - WhatsApp API Service
     */
    protected $whatsAppApiService;

    /**
     * @var MediaEngine - Media Engine
     */
    protected $mediaEngine;

    /**
     * @var WhatsAppMessageLogRepository - Status repository
     */
    protected $whatsAppMessageLogRepository;

    /**
     * @var WhatsAppMessageQueueRepository - WhatsApp Message Queue repository
     */
    protected $whatsAppMessageQueueRepository;
    /**
     * @var CampaignRepository - Campaign repository
     */
    protected $campaignRepository;

    /**
     * @var BotReplyRepository - Bot Reply repository
     */
    protected $botReplyRepository;

    /**
     * @var BotFlowRepository - Bot Flow repository
     */
    protected $botFlowRepository;

    /**
     * @var VendorSettingsEngine - Vendor Settings Engine
     */
    protected $vendorSettingsEngine;

    /**
     * @var UserRepository - UserRepository
     */
    protected $userRepository;

    /**
     * @var ConfigurationEngine - configurationEngine
     */
    protected $configurationEngine;

    /**
     * @var ContactCustomFieldRepository - ContactGroup Repository
     */
    protected $contactCustomFieldRepository;

    /**
     * @var WhatsAppConnectApiService - WhatsApp Connect Service
     */
    protected $whatsAppConnectApiService;

    /**
     * @var LabelRepository - Label Repository
     */
    protected $labelRepository;

    /**
     * Constructor
     *
     * @param  ContactRepository  $contactRepository  - Contact Repository
     * @param  ContactGroupRepository  $contactGroupRepository  - ContactGroup Repository
     * @param  GroupContactRepository  $groupContactRepository  - Group Contacts Repository
     * @param  WhatsAppTemplateRepository  $whatsAppTemplateRepository  - WhatsApp Templates Repository
     * @param  WhatsAppApiService  $whatsAppApiService  - WhatsApp API Service
     * @param  WhatsAppMessageQueueRepository  $whatsAppMessageQueueRepository  - WhatsApp Message Queue
     * @param  CampaignRepository  $campaignRepository  - Campaign repository
     * @param  BotReplyRepository  $botReplyRepository  - Bot Reply repository
     * @param  BotFlowRepository  $botFlowRepository  - Bot Flow repository
     * @param  VendorSettingsEngine  $vendorSettingsEngine  - Configuration Engine
     * @param  UserRepository  $userRepository  - Users Repository
     * @param  ConfigurationEngine  $configurationEngine  - Configuration Engine
     * @param  WhatsAppConnectApiService  $whatsAppConnectApiService  - WhatsApp Connect Service
     * @param  ContactCustomFieldRepository  $contactCustomFieldRepository  - Contacts Custom  Fields Repository
     * @param  LabelRepository  $labelRepository  - Label Repository
     *
     * @return void
     *-----------------------------------------------------------------------*/
    public function __construct(
        ContactRepository $contactRepository,
        ContactGroupRepository $contactGroupRepository,
        GroupContactRepository $groupContactRepository,
        WhatsAppTemplateRepository $whatsAppTemplateRepository,
        WhatsAppApiService $whatsAppApiService,
        MediaEngine $mediaEngine,
        WhatsAppMessageLogRepository $whatsAppMessageLogRepository,
        WhatsAppMessageQueueRepository $whatsAppMessageQueueRepository,
        CampaignRepository $campaignRepository,
        BotReplyRepository $botReplyRepository,
        BotFlowRepository $botFlowRepository,
        VendorSettingsEngine $vendorSettingsEngine,
        UserRepository $userRepository,
        ConfigurationEngine $configurationEngine,
        WhatsAppConnectApiService $whatsAppConnectApiService,
        ContactCustomFieldRepository $contactCustomFieldRepository,
        LabelRepository $labelRepository
    ) {
        $this->contactRepository = $contactRepository;
        $this->contactGroupRepository = $contactGroupRepository;
        $this->groupContactRepository = $groupContactRepository;
        $this->whatsAppTemplateRepository = $whatsAppTemplateRepository;
        $this->whatsAppApiService = $whatsAppApiService;
        $this->mediaEngine = $mediaEngine;
        $this->whatsAppMessageLogRepository = $whatsAppMessageLogRepository;
        $this->whatsAppMessageQueueRepository = $whatsAppMessageQueueRepository;
        $this->campaignRepository = $campaignRepository;
        $this->botReplyRepository = $botReplyRepository;
        $this->botFlowRepository = $botFlowRepository;
        $this->vendorSettingsEngine = $vendorSettingsEngine;
        $this->userRepository = $userRepository;
        $this->configurationEngine = $configurationEngine;
        $this->whatsAppConnectApiService = $whatsAppConnectApiService;
        $this->contactCustomFieldRepository = $contactCustomFieldRepository;
        $this->labelRepository = $labelRepository;
    }

    /**
     * Get Contact Info
     *
     * @param  string  $contactUid
     * @return EngineResponse
     */
   public function sendMessageData($contactUid)
    {
        $vendorId = getVendorId();
        $contact = $this->contactRepository->getVendorContact($contactUid, $vendorId);
        abortIf(__isEmpty($contact));
        $whatsAppApprovedTemplates = $this->whatsAppTemplateRepository->getApprovedTemplatesByNewest();

        return $this->engineSuccessResponse([
            'contact' => $contact,
            'whatsAppTemplates' => $whatsAppApprovedTemplates,
            'template' => '',
            'templatePreview' => '',
        ]);
    }

    /**
     * Get Contact Info
     *
     * @param  string  $contactUid
     * @return EngineResponse
     */
    public function campaignRequiredData()
    {
        $vendorId = getVendorId();
        // templates
        $whatsAppApprovedTemplates = $this->whatsAppTemplateRepository->getApprovedTemplatesByNewest();
        
        // Filter out ORDER_DETAILS templates from campaign selection
        $whatsAppApprovedTemplates = $whatsAppApprovedTemplates->filter(function ($template) {
            $templateData = $template->__data ?? [];
            $metaTemplate = $templateData['template'] ?? [];
            $displayFormat = $metaTemplate['display_format'] ?? 'none';
            
            // Check if template has ORDER_DETAILS button
            $hasOrderDetailsButton = false;
            $templateComponents = $metaTemplate['components'] ?? [];
            foreach ($templateComponents as $component) {
                if (isset($component['type']) && $component['type'] === 'BUTTONS') {
                    if (isset($component['buttons'])) {
                        foreach ($component['buttons'] as $button) {
                            if (isset($button['type']) && $button['type'] === 'ORDER_DETAILS') {
                                $hasOrderDetailsButton = true;
                                break 2;
                            }
                        }
                    }
                }
            }
            
            // Exclude if it's an ORDER_DETAILS template
            return !($hasOrderDetailsButton || $displayFormat === 'ORDER_DETAILS');
        });
        
        // contact groups
        $vendorContactGroups = $this->contactGroupRepository->getActiveGroups($vendorId);
        
        // Add contact counts to each group
        $vendorContactGroupsWithCounts = [];
        $totalContactsCount = 0;
        
        // Debug: Log original contact groups data
        \Log::info('Original vendorContactGroups data:', [
            'count' => count($vendorContactGroups),
            'data' => $vendorContactGroups
        ]);
        
        // Debug: Log group names specifically
        $groupNames = [];
        foreach ($vendorContactGroups as $group) {
            $groupNames[] = $group['title'] ?? 'No title';
        }
        \Log::info('Contact group names found:', [
            'group_names' => $groupNames,
            'total_groups' => count($groupNames)
        ]);
        
        // Debug: Check database structure and data
        $allGroupContacts = \DB::table('group_contacts')->get();
        $allContacts = \DB::table('contacts')->where('vendors__id', $vendorId)->get();
        $allContactGroups = \DB::table('contact_groups')->where('vendors__id', $vendorId)->get();
        
        \Log::info('Database structure check:', [
            'total_group_contacts_records' => $allGroupContacts->count(),
            'total_vendor_contacts' => $allContacts->count(),
            'total_vendor_contact_groups' => $allContactGroups->count(),
            'sample_group_contacts' => $allGroupContacts->take(5)->toArray(),
            'sample_contacts' => $allContacts->take(5)->toArray(),
            'sample_contact_groups' => $allContactGroups->take(5)->toArray()
        ]);
        
        foreach ($vendorContactGroups as $group) {
            // Debug: Check what's actually in the group_contacts table
            $groupContacts = $this->groupContactRepository->fetchItAll([
                'contact_groups__id' => $group['_id']
            ]);
            
            // Get contact count for this group
            $contactCount = $this->groupContactRepository->countIt([
                'contact_groups__id' => $group['_id']
            ]);
            
            // Alternative query to check if the issue is with the repository method
            $alternativeCount = \DB::table('group_contacts')
                ->where('contact_groups__id', $group['_id'])
                ->count();
            
            // Check total contacts for this vendor
            $totalVendorContacts = \DB::table('contacts')
                ->where('vendors__id', $vendorId)
                ->count();
            
            $group['contacts_count'] = $contactCount;
            $vendorContactGroupsWithCounts[] = $group;
            $totalContactsCount += $contactCount;
            
            // Debug: Log each group processing with detailed info
            \Log::info('Processing contact group:', [
                'group_id' => $group['_id'],
                'group_title' => $group['title'] ?? 'No title',
                'contact_count_repository' => $contactCount,
                'contact_count_direct_query' => $alternativeCount,
                'group_contacts_found' => $groupContacts->count(),
                'group_contacts_data' => $groupContacts->toArray(),
                'total_vendor_contacts' => $totalVendorContacts
            ]);
        }
        
        // Debug: Log final processed data
        \Log::info('Final contact groups with counts:', [
            'total_groups' => count($vendorContactGroupsWithCounts),
            'total_contacts' => $totalContactsCount,
            'groups_data' => $vendorContactGroupsWithCounts
        ]);

        // Calculate total contacts for "All Contacts" option
        $totalVendorContacts = \DB::table('contacts')
            ->where('vendors__id', $vendorId)
            ->count();
        
        \Log::info('Final counts:', [
            'total_contacts_in_groups' => $totalContactsCount,
            'total_vendor_contacts' => $totalVendorContacts,
            'using_total_vendor_contacts_for_all_contacts' => $totalVendorContacts
        ]);

        return $this->engineSuccessResponse([
            'contact' => null,
            'whatsAppTemplates' => $whatsAppApprovedTemplates,
            'vendorContactGroups' => $vendorContactGroupsWithCounts,
            'totalContactsCount' => $totalVendorContacts, // Use total vendor contacts for "All Contacts"
            'template' => '',
            'templatePreview' => '',
        ]);
    }

    /**
     * Process the template change
     *
     * @param  string|int  $whatsAppTemplateId
     * @return EngineResponse
     */
    public function processTemplateChange($whatsAppTemplateId)
    {
        $preparedTemplateData = $this->prepareTemplate($whatsAppTemplateId);

        return $this->engineSuccessResponse([
            'template' => $preparedTemplateData['template'],
            'templateData' => $preparedTemplateData['templateData'],
        ]);
    }

    /**
     * Prepare Template with required parameters
     *
     * @param  string|int  $whatsAppTemplateId
     * @param  array  $options
     * @return array
     */
    protected function prepareTemplate($whatsAppTemplateId, array $options = [])
    {
        $options = array_merge([
            'templateComponents' => null,
        ], $options);
        // useful for message
        if ($whatsAppTemplateId == 'for_message') {
            $whatsAppTemplate = null;
            $templateComponents = &$options['templateComponents'];
        } else {
            $whatsAppTemplate = $this->whatsAppTemplateRepository->fetchIt($whatsAppTemplateId);
            abortIf(__isEmpty($whatsAppTemplate), null, __tr('Template not found'));
            $templateComponents = Arr::get($whatsAppTemplate->toArray(), '__data.template.components');
        }

        $bodyComponentText = '';
        $headerComponentText = '';
        $componentButtonText = '';
        $buttonItems = [];
        $headerParameters = [];
        $headerFormat = null;
        $btnIndex = 0;
        $buttonParameters = [];
        $carouselCards = [];
        $isCarouselTemplate = false;
        foreach ($templateComponents as $templateComponent) {
            if ($templateComponent['type'] == 'HEADER') {
                $headerFormat = $templateComponent['format'];
                if ($templateComponent['format'] == 'TEXT') {
                    $headerComponentText = $templateComponent['text'];
                }
            } elseif ($templateComponent['type'] == 'BODY') {
                $bodyComponentText = $templateComponent['text'];
            } elseif ($templateComponent['type'] == 'BUTTONS') {
                foreach ($templateComponent['buttons'] as $templateComponentButton) {
                    if ($templateComponentButton['type'] == 'URL' and (Str::contains($templateComponentButton['url'], '{{1}}'))) {
                        $buttonItems[] = [
                            'type' => $templateComponentButton['type'],
                            'url' => $templateComponentButton['url'],
                            'text' => $templateComponentButton['text'],
                        ];
                        $buttonParameters[] = "button_$btnIndex";
                    } elseif ($templateComponentButton['type'] == 'COPY_CODE') {
                        $buttonItems['COPY_CODE'] = [
                            'type' => $templateComponentButton['type'],
                            'text' => $templateComponentButton['text'],
                        ];
                    } elseif ($templateComponentButton['type'] == 'CATALOG') {
                        // Add catalog button to buttonItems for form display
                        $buttonItems['CATALOG'] = [
                            'type' => 'CATALOG',
                            'text' => $templateComponentButton['text'] ?? 'View catalog',
                        ];
                    }
                    $btnIndex++;
                }
            } elseif ($templateComponent['type'] == 'CAROUSEL') {
                $isCarouselTemplate = true;
                $carouselCards = $templateComponent['cards'];
            }
        }
        // Regular expression to match {{number}}
        $pattern = '/{{\d+}}/';
        // Find matches
        preg_match_all($pattern, $headerComponentText, $headerVariableMatches);
        // $templateParameters = $matches[0]; // will contain all matched patterns
        $headerParameters = array_map(function ($item) {
            return 'header_field_' . strtr($item, [
                '{{' => '',
                '}}' => '',
            ]);
        }, $headerVariableMatches[0]); // will contain all matched patterns
        // Find matches
        preg_match_all($pattern, $bodyComponentText, $matches);
        // $templateParameters = $matches[0]; // will contain all matched patterns
        $bodyParameters = array_map(function ($item) {
            return 'field_' . strtr($item, [
                '{{' => '',
                '}}' => '',
            ]);
        }, $matches[0]); // will contain all matched patterns

        $templateDataPrepared = [
            'buttonItems' => $buttonItems,
            'templateComponents' => $templateComponents,
            'headerParameters' => $headerParameters,
            'buttonParameters' => $buttonParameters,
            'bodyParameters' => $bodyParameters,
            // 'buttonParameters' => $buttonParameters,
            'template' => $whatsAppTemplate,
            'headerFormat' => $headerFormat,
            // for preview
            'bodyComponentText' => $bodyComponentText,
            'contactDataMaps' => getContactDataMaps(),
            // carousel support
            'isCarouselTemplate' => $isCarouselTemplate,
            'carouselCards' => $carouselCards,
        ];

        if ($options['templateComponents']) {
            return $templateDataPrepared;
        }

        return [
            'template' => view('whatsapp-service.message-preparation', $templateDataPrepared)->render(),
            'templateData' => $templateDataPrepared,
        ];
    }

    /**
     * Send message for selected contact
     *
     * @param  BaseRequestTwo  $request
     * @return EngineResponse
     */
    public function processSendMessageForContact($request)
    {
        $contact = $this->contactRepository->getVendorContact($request->get('contact_uid'));
        if (__isEmpty($contact)) {
            if (isExternalApiRequest()) {
                $contact = $this->createAContactForApiRequest($request);
            } else {
                return $this->engineFailedResponse([], __tr('Requested contact does not found'));
            }
        }

        // Check if contact has opted out of campaigns/templates
        if ($contact->campaign_opt_out == 1) {
            return $this->engineFailedResponse([], __tr('This contact has opted out of receiving campaign and template messages.'));
        }

        // check if vendor has active plan
        $vendorPlanDetails = vendorPlanDetails(null, null, $contact->vendors__id);
        if (!$vendorPlanDetails->hasActivePlan()) {
            return $this->engineResponse(22, null, $vendorPlanDetails['message']);
        }

        return $this->sendTemplateMessageProcess($request, $contact);
    }

    /**
     * Create contact if does not exist
     *
     * @param BaseRequestTwo $request
     * @return void
     */
    protected function createAContactForApiRequest($request)
    {
        $vendorId = getVendorId();
        // check the feature limit
        $vendorPlanDetails = vendorPlanDetails('contacts', $this->contactRepository->countIt([
            'vendors__id' => $vendorId
        ]), $vendorId);

        abortIf(!$vendorPlanDetails['is_limit_available'], null, $vendorPlanDetails['message']);

        $request->validate([
            'contact.first_name' => [
                'nullable',
                'max:150',
            ],
            'contact.last_name' => [
                'nullable',
                'max:150',
            ],
            'contact.country' => 'nullable',
            'contact.language_code' => 'nullable|alpha_dash',
            "phone_number" => [
                'required',
                'numeric',
                'min_digits:9',
                'min:1',
            ],
            'contact.email' => 'nullable|email',
        ]);

        $dataForContact = Arr::only(($request->contact ?? []), [
            'first_name',
            'last_name',
            'language_code',
            'email',
            'country',
        ]);
        // abortIf(str_starts_with($request->phone_number, '0') or str_starts_with($request->phone_number, '+'), null, 'phone number should be numeric value without prefixing 0 or +');

        // create contact
        if ($contactCreated = $this->contactRepository->storeContact([
            'first_name' => $dataForContact['first_name'] ?? '',
            'last_name' => $dataForContact['last_name'] ?? '',
            'email' => $dataForContact['email'] ?? '',
            'language_code' => $dataForContact['language_code'] ?? '',
            'phone_number' => cleanDisplayPhoneNumber($request->phone_number),
            'country' => getCountryIdByName($dataForContact['country'] ?? null),
        ], $vendorId)) {
            // prepare group ids needs to be assign to the contact
            $contactGroupsTitles = array_filter(array_unique(explode(',', $request->contact['groups'] ?? '') ?? []));
            if (!empty($contactGroupsTitles)) {
                // prepare group titles needs to be assign to the contact
                $groupsToBeAdded = $this->contactGroupRepository->fetchItAll($contactGroupsTitles, [], 'title', [
                    'where' => [
                        'vendors__id' => $vendorId
                    ]
                ]);
                $groupsToBeCreatedTitles = array_diff($contactGroupsTitles, $groupsToBeAdded->pluck('title')->toArray());
                $groupsToBeCreated = [];
                if (!empty($groupsToBeCreatedTitles)) {
                    foreach ($groupsToBeCreatedTitles as $groupsToBeCreatedTitle) {
                        if (strlen($groupsToBeCreatedTitle) > 255) {
                            abortIf(strlen($groupsToBeCreatedTitle) > 1, null, __tr('Group title should not be greater than 255 characters'));
                        }
                        $groupsToBeCreated[] = [
                            'title' => $groupsToBeCreatedTitle,
                            'vendors__id' => $vendorId,
                            'status' => 1,
                        ];
                    }
                    if (!empty($groupsToBeCreated)) {
                        $newlyCreatedGroupIds = $this->contactGroupRepository->storeItAll($groupsToBeCreated, true);
                        if (!empty($newlyCreatedGroupIds)) {
                            $newlyCreatedGroups = $this->contactGroupRepository->fetchItAll(array_values($newlyCreatedGroupIds));
                            if (!__isEmpty($groupsToBeAdded)) {
                                $groupsToBeAdded->merge($newlyCreatedGroups);
                            }
                        }
                    }
                }
                $assignGroups = [];
                // prepare to assign if needed
                if (! empty($groupsToBeAdded)) {
                    foreach ($groupsToBeAdded as $groupToBeAdded) {
                        if ($groupToBeAdded->vendors__id != $vendorId) {
                            continue;
                        }
                        $assignGroups[] = [
                            'contact_groups__id' => $groupToBeAdded->_id,
                            'contacts__id' => $contactCreated->_id,
                        ];
                    }
                    $this->groupContactRepository->storeItAll($assignGroups);
                }
            }
        }

        return $contactCreated;
    }

    /**
     * get Current Billing Cycle
     *
     * @param string $subscriptionStartDate
     * @return array
     */
    public function getCurrentBillingCycleDates($subscriptionStartDate)
    {
        $today = Carbon::now();
        $startOfMonth = new Carbon($subscriptionStartDate);
        // Adjust the start date to the current period
        $startOfMonth->year($today->year)->month($today->month);
        if ($today->day < $startOfMonth->day) {
            // If today is before the subscription day this month, start from last month
            $startOfMonth->subMonth();
        }
        $endOfMonth = (clone $startOfMonth)->addMonth()->subDay(); // End of this billing cycle
        return [
            'start' => $startOfMonth->startOfDay(), // Ensure time part is zeroed out
            'end' => $endOfMonth->endOfDay(), // Include the entire last day
        ];
    }

    /**
     * Process the message for Campaign creation
     *
     * @param  Request  $request
     * @return EngineResponse
     */
    public function processCampaignCreate($request)
    {
        $vendorId = getVendorId();
        // support both array payload (from duplicate) and Request instance
        $isArrayRequest = is_array($request);
        $getInput = function($key, $default = null) use ($request, $isArrayRequest) {
            return $isArrayRequest ? ($request[$key] ?? $default) : $request->get($key, $default);
        };
        $hasInput = function($key) use ($request, $isArrayRequest) {
            return $isArrayRequest ? array_key_exists($key, $request) && $request[$key] !== null && $request[$key] !== '' : $request->has($key);
        };
        // check the feature limit
        $subscription = getVendorCurrentActiveSubscription($vendorId);
        $currentBillingCycle = $this->getCurrentBillingCycleDates($subscription->created_at ?? getUserAuthInfo('vendor_created_at'));
        $vendorPlanDetails = vendorPlanDetails('campaigns', $this->campaignRepository->countIt([
            'vendors__id' => $vendorId,
            [
                'created_at', '>=', $currentBillingCycle['start'],
            ], [
                'created_at', '<=', $currentBillingCycle['end'],
            ]
        ]), $vendorId);

        if (!$vendorPlanDetails['is_limit_available']) {
            return $this->engineResponse(22, null, $vendorPlanDetails['message']);
        }

        // preserve original schedule input & flags for duplication payload
        $scheduleAtInput = $getInput('schedule_at');
        $scheduleNow = $hasInput('schedule_now');

        $scheduleAt = $getInput('schedule_at');
        $timezone = $getInput('timezone');
        // if seconds missing, complete required date time format
        if (strlen($scheduleAt) == 16) {
            $scheduleAt = $scheduleAt . ':00';
        }
        if ($scheduleAt) {
            try {
                $rawTime = Carbon::createFromFormat('Y-m-d\TH:i:s', $scheduleAt, $timezone);
                $scheduleAt = $rawTime->setTimezone('UTC');
            } catch (\Throwable $th) {
                return $this->engineFailedResponse([], __tr('Failed to recognize the datetime, please reload and try again.'));
            }
        } else {
            $scheduleAt = now();
        }
        $templateUid = $isArrayRequest ? ($request['template_uid'] ?? null) : $request->template_uid;
        $whatsAppTemplate = $this->whatsAppTemplateRepository->fetchIt($templateUid);
        abortIf(__isEmpty($whatsAppTemplate), null, __tr('Template not found in the system'));
        
        // Block ORDER_DETAILS templates from campaigns
        $templateData = $whatsAppTemplate->__data ?? [];
        $metaTemplate = $templateData['template'] ?? [];
        $displayFormat = $metaTemplate['display_format'] ?? 'none';
        
        // Check if template has ORDER_DETAILS button
        $hasOrderDetailsButton = false;
        $templateComponents = $metaTemplate['components'] ?? [];
        foreach ($templateComponents as $component) {
            if (isset($component['type']) && $component['type'] === 'BUTTONS') {
                if (isset($component['buttons'])) {
                    foreach ($component['buttons'] as $button) {
                        if (isset($button['type']) && $button['type'] === 'ORDER_DETAILS') {
                            $hasOrderDetailsButton = true;
                            break 2;
                        }
                    }
                }
            }
        }
        
        if ($hasOrderDetailsButton || $displayFormat === 'ORDER_DETAILS') {
            \Log::warning('🚨 ORDER_DETAILS template blocked from campaign', [
                'template_name' => $whatsAppTemplate->template_name,
                'template_language' => $whatsAppTemplate->language,
                'display_format' => $displayFormat,
                'has_order_details_button' => $hasOrderDetailsButton
            ]);
            
            return $this->engineFailedResponse([], __tr('Cannot send ORDER_DETAILS type template through campaign. ORDER_DETAILS templates require individual order data and cannot be sent in bulk campaigns.'));
        }
        
        $contactGroupId = $getInput('contact_group');
        $restrictByTemplateContactLanguage = ($getInput('restrict_by_templated_contact_language') == 'on') || ($getInput('restrict_by_templated_contact_language') === true);
        $contactsWhereClause = [
            'vendors__id' => $vendorId,
        ];
        $isOnlyForOptedContacts = false;
        //if its Marketing campaign and user is opted out don't process it further
        if (($whatsAppTemplate->category == 'MARKETING')) {
            $contactsWhereClause['whatsapp_opt_out'] = null;
            $isOnlyForOptedContacts = true;
        }

        // Exclude contacts who have opted out of campaigns/templates
        $contactsWhereClause['campaign_opt_out'] = 0;

        if ($restrictByTemplateContactLanguage) {
            $contactsWhereClause['language_code'] = $whatsAppTemplate->language;
        }
        $groupContactIds = [];
        $phoneNumbers = [];

        // Determine if selected template has variables in BODY
        $templateComponents = Arr::get($whatsAppTemplate->toArray(), '__data.template.components', []);
        $bodyHasVariables = false;
        foreach ($templateComponents as $component) {
            if (($component['type'] ?? null) === 'BODY') {
                $text = $component['text'] ?? '';
                if (is_string($text) && preg_match('/\{\{\d+\}\}/', $text)) {
                    $bodyHasVariables = true;
                }
                break;
            }
        }
        
        // Process phone numbers if provided
        if ($hasInput('phone_numbers') && !empty($getInput('phone_numbers'))) {
            // If template BODY has variables, disallow individual numbers
            if ($bodyHasVariables) {
                return $this->engineFailedResponse([], __tr('This template contains body variables. Sending to individual phone numbers is disabled. Please select a contact group instead.'));
            }
            $phoneNumbers = array_map('trim', explode(',', $getInput('phone_numbers')));
            $phoneNumbers = array_filter($phoneNumbers, function($number) {
                return !empty($number);
            });
            
            // Format and validate phone numbers
            $formattedPhoneNumbers = [];
            foreach ($phoneNumbers as $number) {
                $formattedNumber = $this->formatPhoneNumberWithCountryCode($number);
                if (!$formattedNumber) {
                    return $this->engineFailedResponse([], __tr('Invalid phone number format: ' . $number));
                }
                $formattedPhoneNumbers[] = $formattedNumber;
            }
            $phoneNumbers = $formattedPhoneNumbers;
        }
        
        // Validation: Require either contact group or phone numbers
        if (empty($contactGroupId) && empty($phoneNumbers)) {
            return $this->engineFailedResponse([], __tr('Please select a contact group or enter phone numbers.'));
        }
        
        // Process contact group if selected
        if (!empty($contactGroupId)) {
            if ($contactGroupId == 'all_contacts') {
                // Handle "all_contacts" case - process all contacts from database
                $groupContactIds = []; // Empty array means process all contacts
            } else {
                // Handle specific contact group
                $contactGroup = $this->contactGroupRepository->fetchIt([
                    '_id' => $contactGroupId,
                    'vendors__id' => $vendorId,
                ]);
                if (__isEmpty($contactGroup)) {
                    return $this->engineFailedResponse([], __tr('Invalid Group'));
                }
                $groupContacts = $this->groupContactRepository->fetchItAll([
                    'contact_groups__id' => $contactGroupId
                ]);
                if (__isEmpty($groupContacts)) {
                    return $this->engineFailedResponse([], __tr('Group Contact does not found'));
                }
                $groupContactIds = $groupContacts->pluck('contacts__id')->toArray();
            }
        }
        
        // Count contacts from groups (only if contact group is selected)
        $totalContacts = 0;
        if (!empty($contactGroupId)) {
            $totalContacts = $this->contactRepository->countContactsForCampaign($contactsWhereClause, $groupContactIds);
        }
        
        // Add phone numbers count
        $totalContacts += count($phoneNumbers);
        
        if ($totalContacts === 0) {
            return $this->engineFailedResponse([], __tr('No contacts found. Please select a group or enter phone numbers.'));
        }
        // demo account restrictions
        if (isDemo() and ($totalContacts > 3)) {
            return $this->engineFailedResponse([], __tr('DEMO LIMIT: For the demo purposes you can not send campaign messages to more than 3 contacts.'));
        }
        $testContactUid = getVendorSettings('test_recipient_contact');
        if (!$testContactUid) {
            return $this->engineFailedResponse([], __tr('Test Contact missing, You need to set the Test Contact first, do it under the WhatsApp Settings'));
        }
        $contact = $this->contactRepository->getVendorContact($testContactUid);
        if (__isEmpty($contact)) {
            return $this->engineFailedResponse([], __tr('Test contact does not found'));
        }
        // send test message
        $isTestMessageProcessed = $this->sendTemplateMessageProcess($request, $contact, false, null, $vendorId, $whatsAppTemplate);
        if ($isTestMessageProcessed->failed()) {
            return $this->engineFailedResponse([], __tr('Failed to send test message'));
        }
        // remove test message log entry
        $messageLogId = $isTestMessageProcessed->data('messageUid');
        if ($messageLogId) {
            $this->whatsAppMessageLogRepository->deleteIt($messageLogId);
        }
        $contactGroup = null;

        // create campaign data
        $campaignTitle = $getInput('title');
        $campaignData = [
            'title' => $campaignTitle,
            'scheduled_at' => $scheduleAt,
            'vendors__id' => $vendorId,
            'users__id' => getUserID(), // Set the current user ID
            'whatsapp_templates__id' => $whatsAppTemplate->_id,
            'status' => 1, // Set status to 1 (active) for scheduled campaigns
            '__data' => [
                'total_contacts' => $totalContacts,
                'is_all_contacts' => $contactGroupId == 'all_contacts',
                'is_for_template_language_only' => $restrictByTemplateContactLanguage,
                'selected_groups' => $contactGroupId == 'all_contacts' ? [] : [$contactGroupId],
                'phone_numbers' => $phoneNumbers,
                'is_only_for_opted_contacts' => $isOnlyForOptedContacts,
                'group_info' => [
                    'title' => $contactGroup && isset($contactGroup->title) ? $contactGroup->title : null,
                    'description' => $contactGroup && isset($contactGroup->description) ? $contactGroup->description : null,
                    'total_group_contacts' => $totalContacts - count($phoneNumbers)
                ],
                // append recreate payload for duplication
                'campaign_recreate_payload' => [
                    'title' => $campaignTitle,
                    'template' => [
                        'whatsapp_templates__id' => $whatsAppTemplate->_id,
                        'template_uid' => $templateUid,
                        'name' => $whatsAppTemplate->template_name,
                        'language' => $whatsAppTemplate->language,
                    ],
                    'sender' => [
                        'from_phone_number_id' => (is_array($request) ? ($request['from_phone_number_id'] ?? null) : $request->from_phone_number_id),
                    ],
                    'audience' => [
                        'contact_group' => $contactGroupId ?: null,
                        'phone_numbers' => $phoneNumbers,
                    ],
                    'schedule' => [
                        'schedule_now' => (bool) $scheduleNow,
                        'timezone' => $timezone,
                        'scheduled_at' => $scheduleAtInput,
                    ],
                    'options' => [
                        'restrict_by_templated_contact_language' => (bool) $restrictByTemplateContactLanguage,
                        'is_only_for_opted_contacts' => (bool) $isOnlyForOptedContacts,
                    ],
                    // inputs captured from test message process (if available below)
                    'inputs' => ($isTestMessageProcessed->data('inputs') ?? []),
                ]
            ]
        ];
        
        // Store the campaign
        $campaign = $this->campaignRepository->storeIt($campaignData);
        $isSucceed = false;
        
        // Process individual phone numbers if provided
        if (!empty($phoneNumbers)) {
            $queueData = [];
            foreach ($phoneNumbers as $phoneNumber) {
                // Create a dummy contact object for individual phone numbers
                $dummyContact = (object) [
                    'whatsappNumber' => $phoneNumber,
                    'wa_id' => $phoneNumber,
                    '_id' => null,
                    '_uid' => null,
                    'first_name' => 'Individual',
                    'last_name' => 'Contact',
                    'countries__id' => null,
                ];
                
                $templateMessageSentProcess = $this->sendTemplateMessageProcess($request, $dummyContact, true, $campaign->_id, $vendorId, $whatsAppTemplate, $isTestMessageProcessed->data('inputs'));
                $queueData[] = [
                    'vendors__id' => $vendorId,
                    'status' => 1, // queue
                    'scheduled_at' => $scheduleAt,
                    'phone_with_country_code' => $phoneNumber,
                    'campaigns__id' => $campaign->_id,
                    'contacts__id' => null, // No contact ID for individual numbers
                    '__data' => [
                        'contact_data' => [
                            '_id' => null,
                            '_uid' => null,
                            'first_name' => 'Individual',
                            'last_name' => 'Contact',
                            'countries__id' => null,
                        ],
                        'campaign_data' => array_merge($templateMessageSentProcess->data(), [
                            'whatsAppTemplateName' => $whatsAppTemplate->template_name,
                            'whatsAppTemplateLanguage' => $whatsAppTemplate->language,
                            'fromPhoneNumberId' => is_array($request) ? ($request['from_phone_number_id'] ?? null) : $request->from_phone_number_id
                        ])
                    ]
                ];
            }
            if ($this->whatsAppMessageQueueRepository->storeItAll($queueData)) {
                $isSucceed = true;
            }
        }
        
        // Process contact groups if a contact group is selected
        if (!empty($contactGroupId)) {
            $this->contactRepository->getContactsForCampaignInChunks($contactsWhereClause, $groupContactIds, function (Collection $contacts) use (&$request, &$isTestMessageProcessed, &$vendorId, &$whatsAppTemplate, &$scheduleAt, &$campaign, &$isSucceed) {
            $queueData = [];
            foreach ($contacts as $contact) {
                // if number is missing don't process it further
                if (!$contact->wa_id) {
                    continue;
                }
                // Skip contacts who have opted out of campaigns/templates
                if ($contact->campaign_opt_out == 1) {
                    continue;
                }
                $templateMessageSentProcess = $this->sendTemplateMessageProcess($request, $contact, true, $campaign->_id, $vendorId, $whatsAppTemplate, $isTestMessageProcessed->data('inputs'));
                // large contacts simulation
                // for ($i=0; $i < 2000; $i++) {
                $queueData[] = [
                    'vendors__id' => $vendorId,
                    'status' => 1, // queue
                    'scheduled_at' => $scheduleAt,
                    'phone_with_country_code' => $contact->wa_id,
                    'campaigns__id' => $campaign->_id,
                    'contacts__id' => $contact->_id,
                    '__data' => [
                        'contact_data' => [
                            '_id' => $contact->_id,
                            '_uid' => $contact->_uid,
                            'first_name' => $contact->first_name,
                            'last_name' => $contact->last_name,
                            'countries__id' => $contact->countries__id,
                        ],
                        'campaign_data' => array_merge($templateMessageSentProcess->data(), [
                            'whatsAppTemplateName' => $whatsAppTemplate->template_name,
                            'whatsAppTemplateLanguage' => $whatsAppTemplate->language,
                            'fromPhoneNumberId' => is_array($request) ? ($request['from_phone_number_id'] ?? null) : $request->from_phone_number_id
                        ])
                    ]
                ];
                // }
            }
            if ($this->whatsAppMessageQueueRepository->storeItAll($queueData)) {
                $isSucceed = true;
            }
        });
        }

        if ($isSucceed) {
            if (getAppSettings('enable_queue_jobs_for_campaigns')) {
                if ($totalContacts) {
                    $numberOfJobs = ceil($totalContacts / getAppSettings('cron_process_messages_per_lot'));
                    $numberOfJobs = $numberOfJobs + ceil($numberOfJobs * 0.1);
                    for ($i = 0; $i < $numberOfJobs; $i++) {
                        try {
                            ProcessCampaignMessagesJob::dispatch()->delay($scheduleAt);
                        } catch (\Exception $e) {
                            \Log::error('Failed to dispatch campaign job: ' . $e->getMessage());
                            // Continue with campaign creation even if job dispatch fails
                        }
                    }
                }
            }
            return $this->engineSuccessResponse([
                'campaignUid' => $campaign->_uid
            ], __tr('Campaign created successfully and queued for sending'));
        }
        return $this->engineFailedResponse([
            'campaignUid' => $campaign->_uid
        ], __tr('Failed to queue messages for campaign'));
    }

    /**
     * Process the queued messages
     *
     * @return EngineResponse
     */
    public function processCampaignSchedule()
    {
        // set that cron job is done
        if (!getAppSettings('cron_setup_using_artisan_at') and app()->runningConsoleCommand('schedule:run')) {
            $this->configurationEngine->processConfigurationsStore('internals', [
                'cron_setup_using_artisan_at' => now()
            ]);
        }
        if (!getAppSettings('queue_setup_using_artisan_at') and app()->runningConsoleCommand('queue:work')) {
            $this->configurationEngine->processConfigurationsStore('internals', [
                'queue_setup_using_artisan_at' => now()
            ]);
        }
        $queuedMessages = $this->whatsAppMessageQueueRepository->getQueueItemsForProcess();
        if (__isEmpty($queuedMessages)) {
            return $this->engineSuccessResponse([], __tr('Nothing to process'));
        }
        $poolData = [];
        foreach ($queuedMessages as $queuedMessage) {
            // try {
            // fetch the latest record
            $queuedMessage = $this->whatsAppMessageQueueRepository->fetchIt($queuedMessage->_id);
            // if record not found or if its already in process
            if (__isEmpty($queuedMessage) || ($queuedMessage->status == 3)) {
                continue;
            }
            $contactsData = $queuedMessage->__data['contact_data'];
            $campaignData = $queuedMessage->__data['campaign_data'];
            $currentPhoneNumberId = ($campaignData['fromPhoneNumberId'] ?? null) ?: getVendorSettings('current_phone_number_id', null, null, $queuedMessage->vendors__id);
            $poolData[$queuedMessage->_uid] = [
                'queueUid' => $queuedMessage->_uid,
                'retries' => $queuedMessage->retries ?: 1,
                'campaignId' => $queuedMessage->campaigns__id,
                'campaignData' => $campaignData,
                'contactsData' => $contactsData,
                'whatsAppTemplateName' => $campaignData['whatsAppTemplateName'] ?? null,
                'whatsAppTemplateLanguage' => $campaignData['whatsAppTemplateLanguage'] ?? null,
                'phoneNumber' => $queuedMessage->phone_with_country_code,
                'messageComponents' => $campaignData['messageComponents'] ?? [],
                'vendorId' => $queuedMessage->vendors__id,
                'currentPhoneNumberId' => $currentPhoneNumberId,
            ];
        }
        $responses = Http::pool(function (Pool $pool) use ($poolData) {
            // Map each request to a pool get request
            $index = 1;
            return array_map(function ($poolRequestItem) use (&$pool, &$index) {
                if (!$poolRequestItem['queueUid']) {
                    return;
                }
                $this->whatsAppMessageQueueRepository->updateIt($poolRequestItem['queueUid'], [
                    'status' => 3, // processing
                ]);
                // set the from number
                fromPhoneNumberIdForRequest($poolRequestItem['currentPhoneNumberId']);
                // prepare the request for pool
                if (!$poolRequestItem['whatsAppTemplateName'] || !$poolRequestItem['whatsAppTemplateLanguage']) {
                    // Mark as failed if template information is missing
                    $this->whatsAppMessageQueueRepository->updateIt($poolRequestItem['queueUid'], [
                        'status' => 2, // error - do not requeue
                        '__data' => [
                            'process_response' => [
                                'error_message' => 'Template information missing',
                                'error_status' => 'template_info_missing',
                            ]
                        ]
                    ]);
                    return null;
                }
                return $this->whatsAppApiService->sendTemplateMessageViaPool($pool, $poolRequestItem['queueUid'], $poolRequestItem['whatsAppTemplateName'], $poolRequestItem['whatsAppTemplateLanguage'], $poolRequestItem['phoneNumber'], $poolRequestItem['messageComponents'], $poolRequestItem['vendorId']);
            }, $poolData);
        });
        $errorMessage = '';
        foreach ($responses as $responseKey => $response) {
            $errorMessage = '';
            $poolRequestItem = $poolData[$responseKey] ?? null;
            // skip it if empty
            if (!$poolRequestItem) {
                continue;
            }
            try {

            // Ensure we have a valid HTTP response object
            if (!$response instanceof Response) {
                $errorMessage = '';
                if ($response instanceof \Exception) {
                    $errorMessage = $response->getMessage();
                } elseif (is_object($response) && method_exists($response, 'getMessage')) {
                    $errorMessage = $response->getMessage();
                } else {
                    $errorMessage = 'Invalid response from HTTP pool';
                }

                if ($poolRequestItem['retries'] > 5) {
                    $this->whatsAppMessageQueueRepository->updateIt($responseKey, [
                        'status' => 2, // error - do not requeue
                        '__data' => [
                            'process_response' => [
                                'error_message' => $errorMessage,
                                'error_status' => 'error_occurred',
                            ]
                        ]
                    ]);
                } elseif (($response instanceof ConnectException) || ($response instanceof ConnectionException)) {
                    // Connection issues - requeue for retry
                    $this->whatsAppMessageQueueRepository->updateIt($poolRequestItem['queueUid'], [
                        'status' => 1, // re queue
                        'retries' => $poolRequestItem['retries'] + 1,
                        'scheduled_at' => now()->addMinute(), // try in next one minute
                        '__data' => [
                            'process_response' => [
                                'error_status' => 'requeued_connection_error',
                                'error_message' => $errorMessage
                            ]
                        ]
                    ]);
                }
                // Skip to next item
                continue;
            }

            if ($response && !$response->ok()) {
                    $response->throw(function (Response $response, $exception) use (&$poolRequestItem, &$errorMessage) {
                        $getContents = $response->getBody()->getContents();
                        $getContentsDecoded = json_decode($getContents, true);
                        $errorMessage = Arr::get($getContentsDecoded, 'error.message', '');
                        return $exception;
                    });
                }
            } catch (\Throwable $th) {
                $consolidatedErrorMessage =  $errorMessage ?: $th->getMessage();
                if ($poolRequestItem['retries'] > 5) {
                    $this->whatsAppMessageQueueRepository->updateIt($responseKey, [
                        'status' => 2, // error - don not requeue
                        '__data' => [
                            'process_response' => [
                                'error_message' => $consolidatedErrorMessage,
                                'error_status' => 'error_occurred',
                            ]
                        ]
                    ]);
                } elseif (($th instanceof ConnectionException) or ($th instanceof ConnectException)) { // if its connection issue we need to retry
                    // requeue the message if connection error
                    $this->whatsAppMessageQueueRepository->updateIt($poolRequestItem['queueUid'], [
                        'status' => 1, // re queue
                        'retries' => $poolRequestItem['retries'] + 1,
                        'scheduled_at' => now()->addMinute(), // try in next one minute
                        '__data' => [
                            'process_response' => [
                                'error_status' => 'requeued_connection_error',
                                'error_message' => $consolidatedErrorMessage
                            ]
                        ]
                    ]);
                } else {
                    /**
                     * @link https://developers.facebook.com/docs/whatsapp/cloud-api/support/error-codes/
                     */
                    // If Cloud API message throughput has been reached we will try to process it again in 1 minute
                    if (Str::contains($consolidatedErrorMessage, '130429')) {
                        $this->whatsAppMessageQueueRepository->updateIt($poolRequestItem['queueUid'], [
                            'status' => 1, // re queue
                            'retries' => $poolRequestItem['retries'] + 1,
                            'scheduled_at' => now()->addMinute(), // try in next one minute
                            '__data' => [
                                'process_response' => [
                                    'error_message' => $consolidatedErrorMessage,
                                    'error_status' => 'requeued_rate_limit_hit',
                                ]
                            ]
                        ]);
                    } else {
                        $this->whatsAppMessageQueueRepository->updateIt($responseKey, [
                            'status' => 2, // error - don not requeue
                            '__data' => [
                                'process_response' => [
                                    'error_message' => $consolidatedErrorMessage,
                                    'error_status' => 'error_occurred',
                                ]
                            ]
                        ]);
                    }
                }
                // as the error occurred no further process is required
                continue;
            }
            if (!$poolRequestItem['queueUid'] or __isEmpty($this->whatsAppMessageQueueRepository->fetchIt($poolRequestItem['queueUid']))) {
                continue;
            }
            $campaignData = $poolRequestItem['campaignData'];
            $contactsData = $poolRequestItem['contactsData'];
            // as the message already sent via pool we have sent result of it to following method
            // as the result of sending already give it won't try to send message again
            $processedResponse = $this->sendActualWhatsAppTemplateMessage(
                $poolRequestItem['vendorId'],
                $contactsData['_id'] ?? null,
                $poolRequestItem['phoneNumber'],
                $contactsData['_uid'] ?? '',
                $campaignData['whatsAppTemplateName'] ?? '',
                $campaignData['whatsAppTemplateLanguage'] ?? '',
                $campaignData['templateProforma'] ?? [],
                $campaignData['templateComponents'] ?? [],
                $campaignData['messageComponents'] ?? [],
                $poolRequestItem['campaignId'],
                $contactsData,
                ($campaignData['fromPhoneNumberId'] ?? null),
                $response->json() // sent message result
            );
            if ($processedResponse->success()) {
                $this->whatsAppMessageQueueRepository->deleteIt($poolRequestItem['queueUid']);
                $campaignUid = viaFlashCache('campaign_details_' . $poolRequestItem['campaignId'], function () use (&$poolRequestItem) {
                    $campaign = $this->campaignRepository->fetchIt($poolRequestItem['campaignId']);
                    if (!__isEmpty($campaign)) {
                        return $campaign->_uid;
                    }
                    return null;
                });
                $vendorUid = getPublicVendorUid($poolRequestItem['vendorId']);
                // Dispatch event for message
                event(new VendorChannelBroadcast($vendorUid, [
                    'contactUid' => $contactsData['_uid'] ?? '',
                    'isNewIncomingMessage' => null,
                    'campaignUid' => $campaignUid,
                    'lastMessageUid' => null,
                    'formatted_last_message_time' => null,
                ]));
            }
        }
        return $this->engineSuccessResponse([], __tr('Message processed'));
    }

    /**
     * Template Message Sending Process
     *
     * @param Request $request
     * @param object $contact
     * @param boolean $isForCampaign
     * @param int $campaignId
     * @param int $vendorId
     * @param object $whatsAppTemplate
     * @param array $inputs
     * @return EngineResponse
     */
    public function sendTemplateMessageProcess($request, $contact, $isForCampaign = false, $campaignId = null, $vendorId = null, $whatsAppTemplate = null, $inputs = null)
    {
        $vendorId = $vendorId ?: getVendorId();
        // check if vendor has active plan
        $vendorPlanDetails = vendorPlanDetails(null, null, $vendorId);
        if (!$vendorPlanDetails->hasActivePlan()) {
            return $this->engineResponse(22, null, $vendorPlanDetails['message']);
        }

        $inputs = $inputs ?: (is_array($request) ? $request : $request->all());
        if ((is_array($request) ? ($request['template_name'] ?? false) : $request->template_name) and isExternalApiRequest()) {
            $whatsAppTemplate = $whatsAppTemplate ?: $this->whatsAppTemplateRepository->fetchIt([
                'template_name' => is_array($request) ? $request['template_name'] : $request->template_name,
                'language' => is_array($request) ? $request['template_language'] : $request->template_language
            ]);
        } else {
            $whatsAppTemplate = $whatsAppTemplate ?: $this->whatsAppTemplateRepository->fetchIt($inputs['template_uid']);
        }

        abortIf(__isEmpty($whatsAppTemplate), null, __tr('Template for the selected language not found in the system, if you have created template recently on Facebook please sync templates again.'));

        // Extract display_format from __data
        $templateData = $whatsAppTemplate->__data ?? [];
        $metaTemplate = $templateData['template'] ?? [];
        $displayFormat = $metaTemplate['display_format'] ?? 'none';
        $metaTemplateId = $whatsAppTemplate->template_id ?? null;  // template_id stores Meta template ID
        
        \Log::info('🔍 [ORDER_DETAILS DEBUG] Step 1: Template validation started', [
            'template_name' => $whatsAppTemplate->template_name,
            'template_language' => $whatsAppTemplate->language,
            'template_uid' => $whatsAppTemplate->_uid,
            'template_id' => $metaTemplateId,
            'display_format' => $displayFormat,
            'status' => $whatsAppTemplate->status ?? 'unknown'
        ]);

        // FIX #1: HARD STOP if template not synced with Meta (especially for ORDER_DETAILS)
        // template_id is the Meta template ID - if it's null or "not_set", template is not synced
        
        if (empty($metaTemplateId) || $metaTemplateId === 'not_set' || $metaTemplateId === null) {
            // Quick check for ORDER_DETAILS button in template structure
            $templateProforma = Arr::get($whatsAppTemplate->toArray(), '__data.template');
            $templateComponents = Arr::get($templateProforma, 'components');
            $hasOrderDetailsButton = false;
            foreach ($templateComponents as $templateComponent) {
                if ($templateComponent['type'] == 'BUTTONS') {
                    foreach ($templateComponent['buttons'] ?? [] as $button) {
                        if (($button['type'] ?? null) === 'ORDER_DETAILS') {
                            $hasOrderDetailsButton = true;
                            break 2;
                        }
                    }
                }
            }
            $isOrderDetailsTemplate = $hasOrderDetailsButton || $displayFormat === 'ORDER_DETAILS';
            
            \Log::error('🚨 [ORDER_DETAILS DEBUG] Step 1 FAILED: TEMPLATE NOT SYNCED WITH META - BLOCKING SEND', [
                'template_name' => $whatsAppTemplate->template_name,
                'template_language' => $whatsAppTemplate->language,
                'template_id' => $metaTemplateId,
                'display_format' => $displayFormat,
                'is_order_details_template' => $isOrderDetailsTemplate,
                'has_order_details_button' => $hasOrderDetailsButton
            ]);
            
            // Provide specific guidance for ORDER_DETAILS templates
            if ($isOrderDetailsTemplate) {
                return $this->engineFailedResponse([], __tr('ORDER_DETAILS template "__templateName__" (__language__) is not synced with Meta. This template must be created in Meta Business Manager with display_format: "ORDER_DETAILS" and an ORDER_DETAILS button. After creation and approval, sync templates from Meta. Current status: template_id=__templateId__, display_format=__displayFormat__', [
                    '__templateName__' => $whatsAppTemplate->template_name,
                    '__language__' => $whatsAppTemplate->language,
                    '__templateId__' => $metaTemplateId ?? 'null',
                    '__displayFormat__' => $displayFormat
                ]));
            }
            
            return $this->engineFailedResponse([], __tr('Template "__templateName__" (__language__) is not synced with Meta. Please sync templates from Meta Business Manager before sending.', [
                '__templateName__' => $whatsAppTemplate->template_name,
                '__language__' => $whatsAppTemplate->language
            ]));
        }
        
        \Log::info('✅ [ORDER_DETAILS DEBUG] Step 1 PASSED: Template is synced with Meta', [
            'template_id' => $metaTemplateId
        ]);

        $contactWhatsappNumber = $contact->whatsappNumber;
        $templateProforma = Arr::get($whatsAppTemplate->toArray(), '__data.template');
        $templateComponents = Arr::get($templateProforma, 'components');
        
        \Log::info('🔍 [ORDER_DETAILS DEBUG] Step 2: Analyzing template components', [
            'template_components_count' => count($templateComponents),
            'template_components_types' => array_column($templateComponents, 'type')
        ]);
        
        // Check if template has ORDER_DETAILS button
        $hasOrderDetailsButton = false;
        $orderDetailsButtonIndex = null;
        $orderDetailsButtonText = null;
        
        foreach ($templateComponents as $templateComponent) {
            if ($templateComponent['type'] == 'BUTTONS') {
                \Log::info('🔍 [ORDER_DETAILS DEBUG] Step 2.1: Found BUTTONS component', [
                    'buttons_count' => count($templateComponent['buttons'] ?? []),
                    'button_types' => array_column($templateComponent['buttons'] ?? [], 'type')
                ]);
                
                foreach ($templateComponent['buttons'] ?? [] as $buttonIndex => $button) {
                    \Log::info('🔍 [ORDER_DETAILS DEBUG] Step 2.2: Checking button', [
                        'button_index' => $buttonIndex,
                        'button_type' => $button['type'] ?? 'unknown',
                        'button_text' => $button['text'] ?? 'no text'
                    ]);
                    
                    if ($button['type'] === 'ORDER_DETAILS') {
                        $hasOrderDetailsButton = true;
                        $orderDetailsButtonIndex = $buttonIndex;
                        $orderDetailsButtonText = $button['text'] ?? 'Review and Pay';
                        \Log::info('✅ [ORDER_DETAILS DEBUG] Step 2.3: ORDER_DETAILS button found in template', [
                            'button_index' => $orderDetailsButtonIndex,
                            'button_text' => $orderDetailsButtonText
                        ]);
                        break 2;
                    }
                }
            }
        }
        
        \Log::info('🔍 [ORDER_DETAILS DEBUG] Step 3: ORDER_DETAILS detection summary', [
            'has_order_details_button' => $hasOrderDetailsButton,
            'display_format' => $displayFormat,
            'is_order_details_template' => $hasOrderDetailsButton || $displayFormat === 'ORDER_DETAILS',
            'order_details_button_index' => $orderDetailsButtonIndex
        ]);
        
        // Additional validation: ORDER_DETAILS templates must have correct display_format in Meta
        $isOrderDetailsTemplate = $hasOrderDetailsButton || $displayFormat === 'ORDER_DETAILS';
        if ($isOrderDetailsTemplate && $displayFormat !== 'ORDER_DETAILS') {
            \Log::error('🚨 [ORDER_DETAILS DEBUG] Step 3 FAILED: ORDER_DETAILS TEMPLATE HAS INVALID DISPLAY_FORMAT', [
                'template_name' => $whatsAppTemplate->template_name,
                'template_language' => $whatsAppTemplate->language,
                'template_id' => $metaTemplateId,
                'display_format' => $displayFormat,
                'expected_display_format' => 'ORDER_DETAILS',
                'has_order_details_button' => $hasOrderDetailsButton
            ]);
            
            return $this->engineFailedResponse([], __tr('Template "__templateName__" (__language__) has ORDER_DETAILS button but display_format is "__displayFormat__" instead of "ORDER_DETAILS". This template must be recreated in Meta Business Manager with display_format: "ORDER_DETAILS". Meta templates cannot be upgraded - you must create a new template.', [
                '__templateName__' => $whatsAppTemplate->template_name,
                '__language__' => $whatsAppTemplate->language,
                '__displayFormat__' => $displayFormat
            ]));
        }
        
        // FIX #2: Validate ORDER_DETAILS template requirements
        if ($isOrderDetailsTemplate) {
            \Log::info('🔍 [ORDER_DETAILS DEBUG] Step 4: Validating ORDER_DETAILS requirements', [
                'order_data_provided' => !empty($inputs['order_data']),
                'order_data_type' => gettype($inputs['order_data'] ?? null),
                'order_data_keys' => is_array($inputs['order_data'] ?? null) ? array_keys($inputs['order_data']) : 'not_array'
            ]);
            
            if (empty($inputs['order_data'])) {
                \Log::error('❌ [ORDER_DETAILS DEBUG] Step 4 FAILED: order_data is missing', []);
                return $this->engineFailedResponse([], __tr('ORDER_DETAILS template requires order_data. Please provide order information.'));
            }
            
            \Log::info('✅ [ORDER_DETAILS DEBUG] Step 4 PASSED: order_data is provided', [
                'order_data_preview' => is_array($inputs['order_data']) 
                    ? ['order_id' => $inputs['order_data']['order_id'] ?? 'missing', 'items_count' => count($inputs['order_data']['items'] ?? [])]
                    : 'not_array'
            ]);
        } else {
            \Log::info('ℹ️ [ORDER_DETAILS DEBUG] Step 4 SKIPPED: Not an ORDER_DETAILS template', []);
        }
        $componentValidations = [];
        $bodyComponentText = '';
        $headerComponentText = '';
        $componentButtonText = '';
        $pattern = '/{{\d+}}/';
        foreach ($templateComponents as $templateComponent) {
            if ($templateComponent['type'] == 'HEADER') {
                $headerFormat = $templateComponent['format'];
                if ($headerFormat == 'TEXT') {
                    $headerComponentText = $templateComponent['text'];
                    // Find matches
                    preg_match_all($pattern, $headerComponentText, $headerMatches);
                    array_map(function ($item) use (&$componentValidations) {
                        $item = 'header_field_' . strtr($item, [
                            '{{' => '',
                            '}}' => '',
                        ]);
                        $componentValidations[$item] = [
                            'required',
                        ];

                        return $item;
                    }, $headerMatches[0]); // will contain all matched patterns
                } elseif ($headerFormat == 'LOCATION') {
                    $componentValidations['location_latitude'] = [
                        'required',
                        'regex:/^[-]?(([0-8]?[0-9])\.(\d+))|(90(\.0+)?)$/',
                    ];
                    $componentValidations['location_longitude'] = [
                        'required',
                        'regex:/^[-]?((((1[0-7][0-9])|([0-9]?[0-9]))\.(\d+))|180(\.0+)?)$/',
                    ];
                    $componentValidations['location_name'] = [
                        'required',
                        'string',
                    ];
                    $componentValidations['location_address'] = [
                        'required',
                        'string',
                    ];
                } elseif ($headerFormat == 'IMAGE') {
                    $componentValidations['header_image'] = [
                        'required',
                    ];
                } elseif ($headerFormat == 'VIDEO') {
                    $componentValidations['header_video'] = [
                        'required',
                    ];
                } elseif ($headerFormat == 'DOCUMENT') {
                    $componentValidations['header_document'] = [
                        'required',
                    ];
                    $componentValidations['header_document_name'] = [
                        'required',
                    ];
                }
            } elseif ($templateComponent['type'] == 'BODY') {
                $bodyComponentText = $templateComponent['text'];
                // Find matches
                preg_match_all($pattern, $bodyComponentText, $matches);
                array_map(function ($item) use (&$componentValidations) {
                    $item = 'field_' . strtr($item, [
                        '{{' => '',
                        '}}' => '',
                    ]);
                    $componentValidations[$item] = [
                        'required',
                    ];

                    return $item;
                }, $matches[0]); // will contain all matched patterns
            } elseif ($templateComponent['type'] == 'BUTTONS') {
                $btnIndex = 0;
                foreach ($templateComponent['buttons'] as $templateComponentButton) {
                    if ($templateComponentButton['type'] == 'URL' and (Str::contains($templateComponentButton['url'], '{{1}}'))) {
                        $componentValidations["button_$btnIndex"] = [
                            'required',
                        ];
                    } elseif ($templateComponentButton['type'] == 'COPY_CODE') {
                        $componentValidations['copy_code'] = [
                            'required',
                            'alpha_dash',
                        ];
                    }
                    $btnIndex++;
                }
            }
        }
        if (!$isForCampaign) {
            if (!is_array($request)) {
                // Debug: Log validation rules and request data
                \Log::info('Template validation rules:', $componentValidations);
                \Log::info('Request data for validation:', $request->all());
                
                try {
                    $request->validate($componentValidations);
                } catch (\Illuminate\Validation\ValidationException $e) {
                    \Log::error('Template validation failed:', [
                        'errors' => $e->errors(),
                        'request_data' => $request->all(),
                        'validation_rules' => $componentValidations
                    ]);
                    throw $e;
                }
            }
        }
        unset($componentValidations);

        // process the data
        // Regular expression to match {{number}}
        $pattern = '/{{\d+}}/';
        // Find matches
        preg_match_all($pattern, $headerComponentText, $headerVariableMatches);
        // $templateParameters = $matches[0]; // will contain all matched patterns
        $headerParameters = array_map(function ($item) {
            return 'header_field_' . strtr($item, [
                '{{' => '',
                '}}' => '',
            ]);
        }, $headerVariableMatches[0]); // will contain all matched patterns

        preg_match_all($pattern, $componentButtonText, $buttonWordsMatches);
        $buttonParameters = array_map(function ($item) {
            return 'button_' . strtr($item, [
                '{{' => '',
                '}}' => '',
            ]);
        }, $buttonWordsMatches[0]); // will contain all matched patterns

        $componentBodyIndex = 0;
        $mainIndex = 0;
        $componentBody = [];
        $componentBody[$mainIndex] = [
            'type' => 'body',
            'parameters' => [],
        ];
        foreach ($inputs as $inputItemKey => $inputItemValue) {
            if (Str::startsWith($inputItemKey, 'field_')) {
                $valueKeyName = str_replace('field_', '', $inputItemKey);
                $componentBody[$mainIndex]['parameters']["{{{$valueKeyName}}}"] = [
                    'type' => 'text',
                    'text' => $this->setParameterValue($contact, $inputs, $inputItemKey),
                ];
            }
            $componentBodyIndex++;
        }
        $componentButtons = [];
        $parametersComponentsCreations = [
            'COPY_CODE',
        ];
        foreach ($templateComponents as $templateComponent) {
            // @link https://developers.facebook.com/docs/whatsapp/cloud-api/reference/messages/#media-messages
            // @link https://developers.facebook.com/docs/whatsapp/cloud-api/reference/media#supported-media-types
            if ($templateComponent['type'] == 'HEADER') {
                if ($templateComponent['format'] == 'VIDEO') {
                    $mainIndex++;
                    $headerVideoId = $inputs['header_video_id'] ?? null;
                    $headerVideoLink = null;
                    if (!$headerVideoId) {
                        if (isset($inputs['header_video']) && isValidUrl($inputs['header_video'])) {
                            $uploadResult = $this->whatsAppApiService->uploadMedia($inputs['header_video']);
                            if (is_array($uploadResult) && isset($uploadResult['id'])) {
                                $headerVideoId = $uploadResult['id'];
                                $inputs['header_video_id'] = $headerVideoId;
                            } else {
                                \Log::warning('WhatsApp media upload (video URL) failed, falling back to link.', [
                                    'result' => $uploadResult
                                ]);
                                $headerVideoLink = $inputs['header_video'];
                            }
                        } elseif (!isset($inputs['whatsapp_video'])) {
                            $inputHeaderVideo = $inputs['header_video'];
                            $localVideoPath = getTempUploadedFile($inputHeaderVideo);
                            $uploadResult = $this->whatsAppApiService->uploadMedia($localVideoPath);
                            if (is_array($uploadResult) && isset($uploadResult['id'])) {
                                $headerVideoId = $uploadResult['id'];
                                $inputs['header_video_id'] = $headerVideoId;
                            } else {
                                \Log::warning('WhatsApp media upload (video file) failed, falling back to link.', [
                                    'result' => $uploadResult
                                ]);
                                $isProcessed = $this->mediaEngine->whatsappMediaUploadProcess(['filepond' => $inputHeaderVideo], 'whatsapp_video');
                                if ($isProcessed->failed()) {
                                    return $isProcessed;
                                }
                                $headerVideoLink = $isProcessed->data('path');
                            }
                        } else {
                            $headerVideoLink = $inputs['whatsapp_video'];
                        }
                    }

                    $videoPayload = $headerVideoId
                        ? ['id' => $headerVideoId]
                        : ['link' => $headerVideoLink];

                    $componentBody[$mainIndex] = [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'video',
                                'video' => $videoPayload,
                            ],
                        ],
                    ];
                } elseif ($templateComponent['format'] == 'IMAGE') {
                    $mainIndex++;
                    $headerImageId = $inputs['header_image_id'] ?? null;
                    $headerImageLink = null;
                    if (!$headerImageId) {
                        if (isset($inputs['header_image']) && isValidUrl($inputs['header_image'])) {
                            $uploadResult = $this->whatsAppApiService->uploadMedia($inputs['header_image']);
                            if (is_array($uploadResult) && isset($uploadResult['id'])) {
                                $headerImageId = $uploadResult['id'];
                                $inputs['header_image_id'] = $headerImageId;
                            } else {
                                \Log::warning('WhatsApp media upload (image URL) failed, falling back to link.', [
                                    'result' => $uploadResult
                                ]);
                                $headerImageLink = $inputs['header_image'];
                            }
                        } elseif (!isset($inputs['whatsapp_image'])) {
                            $inputHeaderImage = $inputs['header_image'];
                            $localImagePath = getTempUploadedFile($inputHeaderImage);
                            $uploadResult = $this->whatsAppApiService->uploadMedia($localImagePath);
                            if (is_array($uploadResult) && isset($uploadResult['id'])) {
                                $headerImageId = $uploadResult['id'];
                                $inputs['header_image_id'] = $headerImageId;
                            } else {
                                \Log::warning('WhatsApp media upload (image file) failed, falling back to link.', [
                                    'result' => $uploadResult
                                ]);
                                $isProcessed = $this->mediaEngine->whatsappMediaUploadProcess(['filepond' => $inputHeaderImage], 'whatsapp_image');
                                if ($isProcessed->failed()) {
                                    return $isProcessed;
                                }
                                $headerImageLink = $isProcessed->data('path');
                            }
                        } else {
                            $headerImageLink = $inputs['whatsapp_image'];
                        }
                    }

                    $imagePayload = $headerImageId
                        ? ['id' => $headerImageId]
                        : ['link' => $headerImageLink];

                    $componentBody[$mainIndex] = [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'image',
                                'image' => $imagePayload,
                            ],
                        ],
                    ];
                } elseif ($templateComponent['format'] == 'DOCUMENT') {
                    $mainIndex++;
                    $headerDocumentId = $inputs['header_document_id'] ?? null;
                    $headerDocumentLink = null;
                    $documentFileName = $this->setParameterValue($contact, $inputs, 'header_document_name');
                    if (!$headerDocumentId) {
                        $inputHeaderDocument = $inputs['header_document'];
                        if (isset($inputs['header_document']) && isValidUrl($inputs['header_document'])) {
                            $uploadResult = $this->whatsAppApiService->uploadMedia($inputs['header_document']);
                            if (is_array($uploadResult) && isset($uploadResult['id'])) {
                                $headerDocumentId = $uploadResult['id'];
                                $inputs['header_document_id'] = $headerDocumentId;
                            } else {
                                \Log::warning('WhatsApp media upload (document URL) failed, falling back to link.', [
                                    'result' => $uploadResult
                                ]);
                                $headerDocumentLink = $inputs['header_document'];
                            }
                        } elseif (!isset($inputs['whatsapp_document'])) {
                            $localDocumentPath = getTempUploadedFile($inputHeaderDocument);
                            $uploadResult = $this->whatsAppApiService->uploadMedia($localDocumentPath);
                            if (is_array($uploadResult) && isset($uploadResult['id'])) {
                                $headerDocumentId = $uploadResult['id'];
                                $inputs['header_document_id'] = $headerDocumentId;
                            } else {
                                \Log::warning('WhatsApp media upload (document file) failed, falling back to link.', [
                                    'result' => $uploadResult
                                ]);
                                $isProcessed = $this->mediaEngine->whatsappMediaUploadProcess(['filepond' => $inputHeaderDocument], 'whatsapp_document');
                                if ($isProcessed->failed()) {
                                    return $isProcessed;
                                }
                                $headerDocumentLink = $isProcessed->data('path');
                            }
                        } else {
                            $headerDocumentLink = $inputs['whatsapp_document'];
                        }
                    }

                    $documentPayload = $headerDocumentId
                        ? array_filter([
                            'id' => $headerDocumentId,
                            'filename' => $documentFileName,
                        ])
                        : [
                            'filename' => $documentFileName,
                            'link' => $headerDocumentLink,
                        ];

                    $componentBody[$mainIndex] = [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'document',
                                'document' => $documentPayload,
                            ],
                        ],
                    ];
                } elseif ($templateComponent['format'] == 'LOCATION') {
                    // @link https://developers.facebook.com/docs/whatsapp/cloud-api/guides/send-message-templates/#location
                    $mainIndex++;
                    $componentBody[$mainIndex] = [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'location',
                                'location' => [
                                    'latitude' => $this->setParameterValue($contact, $inputs, 'location_latitude'),
                                    'longitude' => $this->setParameterValue($contact, $inputs, 'location_longitude'),
                                    'name' => $this->setParameterValue($contact, $inputs, 'location_name'),
                                    'address' => $this->setParameterValue($contact, $inputs, 'location_address'),
                                ],
                            ],
                        ],
                    ];
                } elseif (($templateComponent['format'] == 'TEXT') and Str::contains($templateComponent['text'], '{{1}}')) {
                    // @link https://developers.facebook.com/docs/whatsapp/cloud-api/guides/send-message-templates
                    $mainIndex++;
                    $componentBody[$mainIndex] = [
                        'type' => 'header',
                        'parameters' => [
                            [
                                'type' => 'text',
                                'text' => $this->setParameterValue($contact, $inputs, 'header_field_1'),
                            ],
                        ],
                    ];
                }
            }  elseif ($templateComponent['type'] == 'CAROUSEL') {
                $__hasCarousel = true;
                // Handle carousel template message components
                // For carousel templates, we need to structure it as a single component with proper indexing
                // According to WhatsApp API, carousel should be structured as a single component
                // The carousel component should be structured as a template component, not a message component
                // For carousel templates, we need to structure it as a single component with proper card structure
                // Ensure carousel component is structured correctly for WhatsApp API
                $carouselComponent = [
                    'type' => 'CAROUSEL',
                    'cards' => []
                ];

                // Process carousel cards in order to ensure proper indexing
                // Ensure cards are processed in the correct order to avoid duplicate indices
                $carouselCards = [];
                foreach ($templateComponent['cards'] as $cardIndex => $card) {
                    $cardComponent = [
                        'components' => []
                    ];

                    // Resolve possible nested carousel inputs (new UI) or flat inputs (legacy UI)
                    $nestedCarouselInput = $inputs['carousel_cards'][$cardIndex] ?? null;
                    $hasHeaderForCard = false;

                    // Add header component for each card
                    foreach ($card['components'] as $cardComponentData) {
                        if ($cardComponentData['type'] == 'HEADER') {
                            if ($cardComponentData['format'] == 'PRODUCT') {
                                // For product headers, require a valid product_retailer_id from input
                                $productRetailerId = $nestedCarouselInput['product_retailer_id'] ?? ($inputs["carousel_card_{$cardIndex}_product_id"] ?? null);
                                if (empty($productRetailerId)) {
                                    return $this->engineFailedResponse([], __tr('Carousel card __index__ requires a product selection', ['__index__' => $cardIndex + 1]));
                                }
                                $cardComponent['components'][] = [
                                    'type' => 'HEADER',
                                    'parameters' => [
                                        [
                                            'type' => 'product',
                                            'product' => [
                                                'product_retailer_id' => (string) $productRetailerId
                                            ]
                                        ]
                                    ]
                                ];
                                $hasHeaderForCard = true;
                            } elseif (in_array($cardComponentData['format'], ['IMAGE', 'VIDEO'])) {
                                // For image/video headers - process uploaded media
                                $mediaType = strtolower($cardComponentData['format']);
                                $legacyInputKey = "carousel_card_{$cardIndex}_{$mediaType}";
                                $mediaUrl = null;

                                if (!empty($inputs[$legacyInputKey])) {
                                    // Legacy flat input: process upload
                                    $isProcessed = $this->mediaEngine->whatsappMediaUploadProcess(['filepond' => $inputs[$legacyInputKey]], "whatsapp_{$mediaType}");
                                    if ($isProcessed->failed()) {
                                        return $isProcessed;
                                    }
                                    $mediaUrl = $isProcessed->data('path');
                                } elseif ($nestedCarouselInput && !empty($nestedCarouselInput['uploaded_media_file_name'])) {
                                    // New nested input already contains stored path/url
                                    $mediaUrl = $nestedCarouselInput['uploaded_media_file_name'];
                                }

                                if (empty($mediaUrl)) {
                                    // Media is required per carousel card
                                    return $this->engineFailedResponse([], __tr('Carousel card __index__ requires a header media', ['__index__' => $cardIndex + 1]));
                                }

                                $cardComponent['components'][] = [
                                    'type' => 'HEADER',
                                    'parameters' => [
                                        [
                                            'type' => $mediaType,
                                            $mediaType => [
                                                'link' => $mediaUrl
                                            ]
                                        ]
                                    ]
                                ];
                                $hasHeaderForCard = true;
                            }
                        } elseif ($cardComponentData['type'] == 'BODY') {
                            // Card body may contain variables; pass parameters if required
                            $cardBodyText = $cardComponentData['text'] ?? '';
                            if ($cardBodyText && Str::contains($cardBodyText, '{{1}}')) {
                                $bodyParam = $nestedCarouselInput['body_text'] ?? ($inputs["carousel_card_{$cardIndex}_body_text"] ?? null);
                                if (empty($bodyParam)) {
                                    return $this->engineFailedResponse([], __tr('Carousel card __index__ requires body text', ['__index__' => $cardIndex + 1]));
                                }
                                $cardComponent['components'][] = [
                                    'type' => 'BODY',
                                    'parameters' => [
                                        [
                                            'type' => 'text',
                                            'text' => $bodyParam
                                        ]
                                    ]
                                ];
                            }
                        } elseif ($cardComponentData['type'] == 'BUTTONS') {
                            // Handle card buttons - include only when parameters required, only one URL button per card
                            $addedUrlButton = false;
                            foreach ($cardComponentData['buttons'] as $buttonIndex => $button) {
                                if ($addedUrlButton) { break; }
                                if ($button['type'] == 'URL' && isset($button['url']) && Str::contains($button['url'], '{{1}}')) {
                                    $cardComponent['components'][] = [
                                        'type' => 'BUTTON',
                                        'sub_type' => 'URL',
                                        'index' => '0',
                                        'parameters' => [
                                            [
                                                'type' => 'text',
                                                'text' => 'default_param'
                                            ]
                                        ]
                                    ];
                                    $addedUrlButton = true;
                                }
                                // SPM and QUICK_REPLY buttons don't need parameters
                            }
                        }
                    }

                    if (!$hasHeaderForCard) {
                        return $this->engineFailedResponse([], __tr('Carousel card __index__ is missing required header', ['__index__' => $cardIndex + 1]));
                    }

                    $carouselCards[] = $cardComponent;
                }

                if (count($carouselCards) < 2) {
                    return $this->engineFailedResponse([], __tr('Carousel templates require at least 2 cards'));
                }

                // Assign cards to carousel component in order
                $carouselComponent['cards'] = $carouselCards;




                // For carousel templates, we need to structure it correctly for WhatsApp API
                // The carousel should be the main component without explicit indices
                // According to WhatsApp API, carousel templates should be sent as a single component
                // The carousel component should be the only component in the message
                // Ensure carousel is structured correctly for the API
                $messageComponents = [$carouselComponent];
                $contactId = $contact->_id;
                $contactUid = $contact->_uid;

                if ($isForCampaign) {
                    return $this->engineSuccessResponse([
                        'whatsAppTemplateName' => $whatsAppTemplate->template_name,
                        'whatsAppTemplateLanguage' => $whatsAppTemplate->language,
                        'templateProforma' => $templateProforma,
                        'templateComponents' => $templateComponents,
                        'messageComponents' => $messageComponents,
                        'inputs' => $inputs,
                        'fromPhoneNumberId' => is_array($request) ? ($request['from_phone_number_id'] ?? null) : $request->from_phone_number_id,
                    ], __tr('Message prepared for WhatsApp campaign'));
                }

                $contactsData = [
                    '_id' => $contact->_id,
                    '_uid' => $contact->_uid,
                    'first_name' => $contact->first_name,
                    'last_name' => $contact->last_name,
                    'countries__id' => $contact->countries__id,
                    'is_template_test_contact' => is_array($request) ? ($request['is_template_test_contact'] ?? false) : $request->is_template_test_contact
                ];
                $processedResponse = $this->sendActualWhatsAppTemplateMessage(
                    $vendorId,
                    $contactId,
                    $contactWhatsappNumber,
                    $contactUid,
                    $whatsAppTemplate->template_name,
                    $whatsAppTemplate->language,
                    $templateProforma,
                    $templateComponents,
                    $messageComponents,
                    $campaignId,
                    $contactsData,
                    is_array($request) ? ($request['from_phone_number_id'] ?? null) : $request->from_phone_number_id
                );
                $processedResponse->updateData('inputs', $inputs);
                return $processedResponse;
            } elseif ($templateComponent['type'] == 'BUTTONS') {
                $componentButtonIndex = 0;
                $skipComponentsCreations = [
                    // 'URL',
                    'PHONE_NUMBER',
                    // ORDER_DETAILS buttons MUST be processed - they need button parameters with action.order_details
                ];
                foreach ($templateComponent['buttons'] as $templateButtonArrayIndex => $templateComponentButton) {
                    // Button index MUST match template button position (not processed button count)
                    // Use the array index from the template buttons array
                    $templateButtonIndex = $templateButtonArrayIndex;
                    
                    // or check if this type is skipped from components creations
                    if (! in_array($templateComponentButton['type'], $skipComponentsCreations)) {
                        // create component block
                        $componentButtons[$mainIndex] = [
                            'type' => 'button',
                            'sub_type' => $templateComponentButton['type'] === 'CATALOG' ? 'CATALOG' : 
                                         ($templateComponentButton['type'] === 'ORDER_DETAILS' ? 'order_details' : $templateComponentButton['type']),
                            'index' => $templateButtonIndex,  // Use template button position, not processed count
                            'parameters' => [],
                        ];
                        
                        // ORDER_DETAILS button parameters - NEW format with action.order_details
                        if ($templateComponentButton['type'] === 'ORDER_DETAILS') {
                            \Log::info('🔍 [ORDER_DETAILS DEBUG] Step 5.1: Processing ORDER_DETAILS button', [
                                'template_button_index' => $templateButtonIndex,
                                'component_button_index' => $componentButtonIndex,
                                'has_order_data' => !empty($inputs['order_data'])
                            ]);
                            
                            if (!empty($inputs['order_data'])) {
                                $orderData = is_array($inputs['order_data']) 
                                    ? $inputs['order_data'] 
                                    : json_decode($inputs['order_data'], true);
                                
                                \Log::info('🔍 [ORDER_DETAILS DEBUG] Step 5.2: Order data extracted', [
                                    'order_data_type' => gettype($orderData),
                                    'order_id' => $orderData['order_id'] ?? $orderData['id'] ?? 'missing',
                                    'currency' => $orderData['currency'] ?? 'missing',
                                    'total_amount' => $orderData['total_amount'] ?? $orderData['final_amount'] ?? 'missing',
                                    'items_count' => count($orderData['items'] ?? $orderData['line_items'] ?? []),
                                    'payment_type' => $orderData['payment_type'] ?? 'missing'
                                ]);
                                
                                if (!empty($orderData)) {
                                    \Log::info('🔍 [ORDER_DETAILS DEBUG] Step 5.3: Building ORDER_DETAILS action payload', []);
                                    $orderDetailsPayload = $this->buildOrderDetailsActionPayload($orderData, $vendorId);
                                    
                                    if ($orderDetailsPayload) {
                                        \Log::info('✅ [ORDER_DETAILS DEBUG] Step 5.4: ORDER_DETAILS payload built successfully', [
                                            'reference_id' => $orderDetailsPayload['reference_id'] ?? 'missing',
                                            'type' => $orderDetailsPayload['type'] ?? 'missing',
                                            'payment_type' => $orderDetailsPayload['payment_type'] ?? 'missing',
                                            'currency' => $orderDetailsPayload['currency'] ?? 'missing',
                                            'total_amount_value' => $orderDetailsPayload['total_amount']['value'] ?? 'missing',
                                            'items_count' => count($orderDetailsPayload['order']['items'] ?? []),
                                            'order_status' => $orderDetailsPayload['order']['status'] ?? 'missing'
                                        ]);
                                        
                                        $componentButtons[$mainIndex]['parameters'][] = [
                                            'type' => 'action',
                                            'action' => [
                                                'order_details' => $orderDetailsPayload
                                            ]
                                        ];
                                        
                                        \Log::info('✅ [ORDER_DETAILS DEBUG] Step 5.5: ORDER_DETAILS button component created', [
                                            'button_component' => [
                                                'type' => $componentButtons[$mainIndex]['type'],
                                                'sub_type' => $componentButtons[$mainIndex]['sub_type'],
                                                'index' => $componentButtons[$mainIndex]['index'],
                                                'has_parameters' => !empty($componentButtons[$mainIndex]['parameters']),
                                                'parameters_count' => count($componentButtons[$mainIndex]['parameters'])
                                            ]
                                        ]);
                                    } else {
                                        \Log::error('❌ [ORDER_DETAILS DEBUG] Step 5.4 FAILED: buildOrderDetailsActionPayload returned null', []);
                                    }
                                } else {
                                    \Log::error('❌ [ORDER_DETAILS DEBUG] Step 5.3 FAILED: orderData is empty after parsing', []);
                                }
                            } else {
                                \Log::error('❌ [ORDER_DETAILS DEBUG] Step 5.2 FAILED: order_data is empty in inputs', []);
                            }
                        } elseif // create coupon code parameters
                        (in_array($templateComponentButton['type'], $parametersComponentsCreations)) {
                            $componentButtons[$mainIndex]['parameters'][] = [
                                'type' => 'COUPON_CODE',
                                'coupon_code' => $this->setParameterValue($contact, $inputs, 'copy_code'),
                            ];
                        } elseif // create url parameters
                        (in_array($templateComponentButton['type'], ['URL']) and Str::contains($templateComponentButton['url'], '{{1}}')) {
                            $componentButtons[$mainIndex]['parameters'][] = [
                                'type' => 'text',
                                'text' => $this->setParameterValue($contact, $inputs, "button_$templateButtonIndex"),
                            ];
                        } elseif // flow button parameters
                        (in_array($templateComponentButton['type'], ['FLOW'])) {
                            $componentButtons[$mainIndex]['parameters'][] = [
                                "type" => "action",
                                'action' => [
                                ]
                            ];
                        } elseif // catalog button parameters
                        ($templateComponentButton['type'] === 'CATALOG') {
                            // Randomly select a product from catalog for thumbnail
                            $thumbnailProductRetailerId = $this->getRandomCatalogProductId($vendorId);
                            
                            // Build catalog button action
                            $catalogAction = [];
                            if (!empty($thumbnailProductRetailerId)) {
                                $catalogAction['thumbnail_product_retailer_id'] = (string) $thumbnailProductRetailerId;
                            }
                            
                            // Add parameters - even if empty, WhatsApp will use default catalog thumbnail
                            $componentButtons[$mainIndex]['parameters'][] = [
                                'type' => 'action',
                                'action' => $catalogAction
                            ];
                        }
                        $componentButtonIndex++;
                    }
                    $mainIndex++;
                }
            }
        }
        // remove static links buttons (but keep catalog buttons)
        foreach ($componentButtons as $componentButtonKey => $componentButton) {
            // Keep catalog buttons even if parameters are empty (they use default catalog thumbnail)
            if (empty($componentButton['parameters']) && ($componentButton['sub_type'] ?? '') !== 'CATALOG') {
                unset($componentButtons[$componentButtonKey]);
            }
        }
        $messageComponents = array_merge($componentBody, $componentButtons);
        $contactId = $contact->_id;
        $contactUid = $contact->_uid;

        if ($isForCampaign) {
            return $this->engineSuccessResponse([
                'whatsAppTemplateName' => $whatsAppTemplate->template_name,
                'whatsAppTemplateLanguage' => $whatsAppTemplate->language,
                'templateProforma' => $templateProforma,
                'templateComponents' => $templateComponents,
                'messageComponents' => $messageComponents,
                'inputs' => $inputs,
                'fromPhoneNumberId' => is_array($request) ? ($request['from_phone_number_id'] ?? null) : $request->from_phone_number_id,
            ], __tr('Message prepared for WhatsApp campaign'));
        }

        $contactsData = [
            '_id' => $contact->_id,
            '_uid' => $contact->_uid,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'countries__id' => $contact->countries__id,
            'is_template_test_contact' => is_array($request) ? ($request['is_template_test_contact'] ?? false) : $request->is_template_test_contact
        ];
        $processedResponse = $this->sendActualWhatsAppTemplateMessage(
            $vendorId,
            $contactId,
            $contactWhatsappNumber,
            $contactUid,
            $whatsAppTemplate->template_name,
            $whatsAppTemplate->language,
            $templateProforma,
            $templateComponents,
            $messageComponents,
            $campaignId,
            $contactsData,
            is_array($request) ? ($request['from_phone_number_id'] ?? null) : $request->from_phone_number_id
        );
        $processedResponse->updateData('inputs', $inputs);
        return $processedResponse;
    }



    /**
     * Send Interactive Message Process
     *
     * @param Request $request
     * @param boolean $isMediaMessage
     * @param integer $vendorId
     * @param array $options
     * @return EngineResponse
     */
    public function sendInteractiveMessageProcess($request, bool $isMediaMessage = false, $vendorId = null, $options = [])
    {
        $vendorId = $vendorId ?: getVendorId();
        // check if vendor has active plan
        $vendorPlanDetails = vendorPlanDetails(null, null, $vendorId);
        if (!$vendorPlanDetails->hasActivePlan()) {
            return $this->engineResponse(22, null, $vendorPlanDetails['message']);
        }

        if (is_array($request) === true) {
            $messageBody = $request['messageBody'];
            $contactUid = $request['contactUid'];
        } else {
            $messageBody = $request->message_body;
            $contactUid = $request->contact_uid;
        }
        $contact = $this->contactRepository->getVendorContact($contactUid, $vendorId);
        abortIf(__isEmpty($contact));
        // mark unread chats as read if any
        $this->markAsReadProcess($contact, $vendorId);
        $mediaData = [];
        $serviceName = getAppSettings('name');

        // Get interactive message data from request or options
        $interactiveData = [];
        if (is_array($request) && isset($request['interactive_data'])) {
            $interactiveData = $request['interactive_data'];
        } elseif (!is_array($request) && $request->has('interactive_data')) {
            $interactiveData = $request->interactive_data;
        }

        // Determine interactive type based on data structure
        $interactiveType = null;
        
        // PRIORITY 1: Check explicit interactive_type (from flow handlers)
        if (isset($interactiveData['interactive_type']) && $interactiveData['interactive_type'] === 'list') {
            $interactiveType = 'list';
        }
        // PRIORITY 2: Check for list data in sections
        elseif (isset($interactiveData['list_data']['sections']) && !empty($interactiveData['list_data']['sections'])) {
            $interactiveType = 'list';
        }
        // PRIORITY 3: Check for sections directly
        elseif (isset($interactiveData['sections']) && !empty($interactiveData['sections'])) {
            $interactiveType = 'list';
        }
        // PRIORITY 4: Check explicit type (legacy)
        elseif (isset($interactiveData['type']) && $interactiveData['type'] === 'list') {
            $interactiveType = 'list';
        }
        // FALLBACK: Default to button type
        else {
            $interactiveType = 'button';
        }

        // Log the type determination
        \Illuminate\Support\Facades\Log::info('Determined interactive message type', [
            'determined_type' => $interactiveType,
            'has_list_data' => isset($interactiveData['list_data']),
            'has_list_data_sections' => isset($interactiveData['list_data']['sections']) && !empty($interactiveData['list_data']['sections']),
            'has_sections' => isset($interactiveData['sections']) && !empty($interactiveData['sections']),
            'explicit_interactive_type' => $interactiveData['interactive_type'] ?? 'not_set',
            'explicit_type' => $interactiveData['type'] ?? 'not_set'
        ]);

        // Prepare interactive message payload according to WhatsApp API structure
        $messagePayload = [
            'type' => 'interactive',
            'interactive' => [
                'type' => $interactiveType
            ]
        ];

        // Add header if provided
        if (!empty($interactiveData['header_text'])) {
            $messagePayload['interactive']['header'] = [
                'type' => 'text',
                'text' => $interactiveData['header_text']
            ];
        }

        // Add body if provided
        if (!empty($interactiveData['body_text'])) {
            $messagePayload['interactive']['body'] = [
                'text' => $interactiveData['body_text']
            ];
        }

        // Add footer if provided
        if (!empty($interactiveData['footer_text'])) {
            $messagePayload['interactive']['footer'] = [
                'text' => $interactiveData['footer_text']
            ];
        }

        // Initialize sections variable for scope
        $sections = [];

        // Handle different interactive types
        if ($interactiveType === 'list') {
            // Process sections for list type
            $sourceSections = [];
            
            // Check both possible locations for sections data
            if (!empty($interactiveData['list_data']['sections'])) {
                $sourceSections = $interactiveData['list_data']['sections'];
            } elseif (!empty($interactiveData['sections'])) {
                $sourceSections = $interactiveData['sections'];
            }

            // Process sections and rows
            foreach ($sourceSections as $section) {
                $processedSection = [
                    'title' => $section['title'] ?? ''
                ];

                $rows = [];
                foreach ($section['rows'] ?? [] as $row) {
                    // Ensure row has required fields
                    if (!empty($row['title'])) {
                        $rows[] = [
                            'id' => (string)($row['id'] ?? $row['row_id'] ?? uniqid()),
                            'title' => $row['title'],
                            'description' => $row['description'] ?? null
                        ];
                    }
                }

                if (!empty($rows)) {
                    $processedSection['rows'] = $rows;
                    $sections[] = $processedSection;
                }
            }

            if (!empty($sections)) {
                // For list type, add action with button and sections
                $messagePayload['interactive']['action'] = [
                    'button' => $interactiveData['list_data']['button_text'] ?? 
                               $interactiveData['button_text'] ?? 
                               'Select an option',
                    'sections' => $sections
                ];

                // Log the final payload for debugging
                \Illuminate\Support\Facades\Log::info('Sending list message payload', [
                    'sections_count' => count($sections),
                    'total_rows' => array_sum(array_map(function($section) { 
                        return count($section['rows'] ?? []); 
                    }, $sections)),
                    'button_text' => $messagePayload['interactive']['action']['button']
                ]);

                // Log list processing for debugging
                \Illuminate\Support\Facades\Log::info('Sending WhatsApp list message', [
                    'sections_count' => count($sections),
                    'total_rows' => array_sum(array_map(function($section) { 
                        return count($section['rows']); 
                    }, $sections)),
                    'button_text' => $messagePayload['interactive']['action']['button']
                ]);

                // Log list processing for debugging
                \Illuminate\Support\Facades\Log::info('Processing list-type interactive message', [
                    'sections_count' => count($sections),
                    'total_rows' => array_sum(array_map(function($section) { 
                        return count($section['rows']); 
                    }, $sections)),
                    'button_text' => $interactiveData['list_data']['button_text'] ?? $interactiveData['button_text'] ?? 'not_set'
                ]);
            } else {
                // Fallback to button type if no valid sections
                // CRITICAL FIX: Update both the variable and the payload type
                $interactiveType = 'button';
                $messagePayload['interactive']['type'] = 'button';
                \Illuminate\Support\Facades\Log::warning('Fallback to button type - no valid sections found', [
                    'original_type' => 'list',
                    'updated_type' => 'button',
                    'sections_processed' => count($sections),
                    'has_list_data' => isset($interactiveData['list_data']),
                    'has_sections_in_data' => isset($interactiveData['sections'])
                ]);
            }
        

        } elseif ($interactiveType === 'button' && isset($interactiveData['buttons']) && is_array($interactiveData['buttons'])) {
            $messagePayload['interactive']['action'] = [
                'buttons' => array_map(function($button) {
                    $buttonText = is_array($button) ? ($button['title'] ?? $button['text'] ?? '') : $button;
                    return [
                        'type' => 'reply',
                        'reply' => [
                            'id' => Str::random(10),
                            'title' => $buttonText
                        ]
                    ];
                }, $interactiveData['buttons'])
            ];

            // Log button processing
            \Illuminate\Support\Facades\Log::info('Sending WhatsApp button message', [
                'button_count' => count($interactiveData['buttons'])
            ]);
        }

        // Send the interactive message using the WhatsApp API service
        // For list messages, we need to pass the data in the format expected by WhatsAppApiService
        if ($interactiveType === 'list') {
            // Always use the proper list format for WhatsAppApiService, regardless of sections
            $listMessageData = [
                'interactive_type' => 'list',
                'header_text' => $interactiveData['header_text'] ?? '',
                'body_text' => $interactiveData['body_text'] ?? $messageBody ?? '',
                'footer_text' => $interactiveData['footer_text'] ?? '',
                'list_data' => [
                    'button_text' => $interactiveData['list_data']['button_text'] ??
                                   $interactiveData['button_text'] ??
                                   'Select an option',
                    'sections' => $sections
                ]
            ];

            \Illuminate\Support\Facades\Log::info('Sending list message with proper format', [
                'user' => $contact->wa_id,
                'sections_count' => count($sections),
                'button_text' => $listMessageData['list_data']['button_text'],
                'list_data' => $listMessageData['list_data']
            ]);

            // Log final payload before sending to ensure type is correct
            \Illuminate\Support\Facades\Log::info('Final list message payload before sending', [
                'interactive_type' => $listMessageData['interactive_type'],
                'sections_count' => count($listMessageData['list_data']['sections']),
                'has_sections' => !empty($listMessageData['list_data']['sections'])
            ]);

            $sendMessageResult = $this->whatsAppApiService->sendInteractiveMessage($contact->wa_id, $listMessageData, $vendorId);
        } elseif ($interactiveType === 'button' && isset($interactiveData['buttons'])) {
            // Handle button messages with proper format for WhatsAppApiService
            $buttonMessageData = [
                'interactive_type' => 'button',
                'header_text' => $interactiveData['header_text'] ?? '',
                'body_text' => $interactiveData['body_text'] ?? $messageBody ?? '',
                'footer_text' => $interactiveData['footer_text'] ?? '',
                'buttons' => array_map(function($button) {
                    return is_array($button) ? ($button['title'] ?? $button['text'] ?? '') : $button;
                }, $interactiveData['buttons'])
            ];

            \Illuminate\Support\Facades\Log::info('Sending button message with proper format', [
                'user' => $contact->wa_id,
                'buttons_count' => count($buttonMessageData['buttons']),
                'buttons' => $buttonMessageData['buttons']
            ]);

            $sendMessageResult = $this->whatsAppApiService->sendInteractiveMessage($contact->wa_id, $buttonMessageData, $vendorId);
        } else {
            // Fallback for other message types
            $sendMessageResult = $this->whatsAppApiService->sendInteractiveMessage($contact->wa_id, $messagePayload, $vendorId);
        }
        $messageWamid = Arr::get($sendMessageResult, 'messages.0.id');
        if (! $messageWamid) {
            return $this->engineFailedResponse([
                'contact' => $contact,
            ], __tr('Failed to send message'));
        }
        $this->whatsAppMessageLogRepository->updateOrCreateWhatsAppMessageFromWebhook(
            getVendorSettings('current_phone_number_id', null, null, $vendorId),
            $contact->_id,
            $vendorId,
            $contact->wa_id,
            $messageWamid,
            'accepted',
            $sendMessageResult,
            $messageBody,
            null,
            $mediaData,
            false,
            $options
        );
        // update the client models by existing it
        updateClientModels([
            'whatsappMessageLogs' => $this->contactChatData($contact->_id)->data('whatsappMessageLogs'),
        ], 'prepend');

        return $this->engineSuccessResponse([
            'contact' => $contact,
        ], __tr('Message processed'));
    }

    /**
     * Actual Template Message Process
     *
     * @param integer $vendorId
     * @param integer $contactId
     * @param integer|string $contactWhatsappNumber
     * @param string $contactUid
     * @param string $whatsAppTemplateName
     * @param string $whatsAppTemplateLanguage
     * @param array $templateProforma
     * @param array $templateComponents
     * @param array $messageComponents
     * @param integer|null $campaignId
     * @param array|null $contactsData
     * @return EngineResponse
     */
    protected function sendActualWhatsAppTemplateMessage(
        int $vendorId,
        ?int $contactId,
        int|string $contactWhatsappNumber,
        string $contactUid,
        string $whatsAppTemplateName,
        string $whatsAppTemplateLanguage,
        array $templateProforma,
        array $templateComponents,
        array $messageComponents,
        ?int $campaignId = null,
        ?array $contactsData = null,
        $fromPhoneNumberId = null,
        $sendMessageResult = null,
    ) {
        $currentPhoneNumberId = $fromPhoneNumberId ?: getVendorSettings('current_phone_number_id', null, null, $vendorId);
        fromPhoneNumberIdForRequest($currentPhoneNumberId);
        if (!$sendMessageResult) {
            // Debug: Log what we're sending to WhatsApp API
            \Log::info('Sending to WhatsApp API', [
                'template_name' => $whatsAppTemplateName,
                'template_language' => $whatsAppTemplateLanguage,
                'contact_number' => $contactWhatsappNumber,
                'vendor_id' => $vendorId,
                'phone_number_id' => $currentPhoneNumberId
            ]);
            
            // Debug: Check available templates in database
            try {
                $availableTemplates = $this->whatsAppTemplateRepository->fetchItAll([
                    'vendors__id' => $vendorId,
                    'status' => 'APPROVED'
                ]);
                
                $templateNames = [];
                $templateLanguages = [];
                foreach ($availableTemplates as $template) {
                    $templateNames[] = $template->template_name;
                    $templateLanguages[] = $template->language;
                }
                
                // Check which templates are synced with Meta
                $syncedTemplates = [];
                $unsyncedTemplates = [];
                foreach ($availableTemplates as $template) {
                    // template_id is the Meta template ID
                    if (!empty($template->template_id) && $template->template_id !== 'not_set') {
                        $syncedTemplates[] = $template->template_name . ' (' . $template->language . ')';
                    } else {
                        $unsyncedTemplates[] = $template->template_name . ' (' . $template->language . ')';
                    }
                }
                
                \Log::info('Available templates in database', [
                    'all_template_names' => array_unique($templateNames),
                    'all_template_languages' => array_unique($templateLanguages),
                    'requested_template_name' => $whatsAppTemplateName,
                    'requested_template_language' => $whatsAppTemplateLanguage,
                    'template_name_exists' => in_array($whatsAppTemplateName, $templateNames),
                    'template_language_exists' => in_array($whatsAppTemplateLanguage, $templateLanguages),
                    'synced_with_meta' => $syncedTemplates,
                    'not_synced_with_meta' => $unsyncedTemplates
                ]);
                
                // Debug: Check specific template details
                $specificTemplate = $this->whatsAppTemplateRepository->fetchIt([
                    'vendors__id' => $vendorId,
                    'template_name' => $whatsAppTemplateName,
                    'language' => $whatsAppTemplateLanguage
                ]);
                
                if ($specificTemplate) {
                    \Log::info('Specific template found', [
                        'template_name' => $specificTemplate->template_name,
                        'template_language' => $specificTemplate->language,
                        'template_status' => $specificTemplate->status,
                        'template_category' => $specificTemplate->category,
                        'template_uid' => $specificTemplate->_uid,
                        'template_id' => $specificTemplate->template_id ?? 'not_set',
                        'is_approved' => $specificTemplate->status === 'APPROVED'
                    ]);
                    
                    // FIX #1: HARD STOP if template not synced with Meta
                    // template_id is the Meta template ID
                    if (empty($specificTemplate->template_id) || $specificTemplate->template_id === 'not_set') {
                        \Log::error('🚨 TEMPLATE NOT SYNCED WITH META - BLOCKING SEND', [
                            'template_name' => $specificTemplate->template_name,
                            'template_language' => $specificTemplate->language,
                            'template_id' => $specificTemplate->template_id,
                            'display_format' => $specificTemplate->display_format ?? 'none',
                            'message' => 'Template exists locally but not in Meta Business Manager. WhatsApp API will reject this template.'
                        ]);
                        
                        // Return error response - DO NOT CONTINUE
                        return [
                            'error' => [
                                'message' => 'Template is not synced with Meta. Please sync templates from Meta Business Manager before sending.',
                                'type' => 'TEMPLATE_NOT_SYNCED',
                                'code' => 'TEMPLATE_NOT_SYNCED'
                            ]
                        ];
                    }
                } else {
                    \Log::error('Specific template NOT found in database', [
                        'searched_template_name' => $whatsAppTemplateName,
                        'searched_template_language' => $whatsAppTemplateLanguage,
                        'vendor_id' => $vendorId
                    ]);
                }
            } catch (\Exception $e) {
                \Log::error('Could not fetch available templates', ['error' => $e->getMessage()]);
            }
            
            // FIX #3: Log final payload before API call
            $finalPayload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $contactWhatsappNumber,
                'type' => 'template',
                'template' => [
                    'name' => $whatsAppTemplateName,
                    'language' => [
                        'policy' => 'deterministic',
                        'code' => $whatsAppTemplateLanguage,
                    ],
                    'components' => array_values($messageComponents),
                ],
            ];
            
            \Log::info('🚀 About to call WhatsApp API', [
                'template_name' => $whatsAppTemplateName,
                'template_language' => $whatsAppTemplateLanguage,
                'contact_number' => $contactWhatsappNumber,
                'vendor_id' => $vendorId,
                'message_components_count' => count($messageComponents),
                'message_components_type' => $messageComponents[0]['type'] ?? 'unknown',
                'components_detail' => array_map(function($comp) {
                    return [
                        'type' => $comp['type'] ?? 'unknown',
                        'sub_type' => $comp['sub_type'] ?? null,
                        'index' => $comp['index'] ?? null,
                        'has_parameters' => !empty($comp['parameters']),
                        'parameters_count' => count($comp['parameters'] ?? [])
                    ];
                }, $messageComponents)
            ]);
            
            // FIX #2 (continued): Validate ORDER_DETAILS button is present if template requires it
            \Log::info('🔍 [ORDER_DETAILS DEBUG] Step 6: Validating ORDER_DETAILS button in payload', [
                'message_components_count' => count($messageComponents)
            ]);
            
            $hasOrderDetailsButtonInPayload = false;
            $orderDetailsButtonComponent = null;
            foreach ($messageComponents as $componentIndex => $component) {
                \Log::info('🔍 [ORDER_DETAILS DEBUG] Step 6.1: Checking component', [
                    'component_index' => $componentIndex,
                    'component_type' => $component['type'] ?? 'unknown',
                    'component_sub_type' => $component['sub_type'] ?? null
                ]);
                
                $componentType = $component['type'] ?? null;
                $componentSubType = $component['sub_type'] ?? null;
                
                if (null !== $componentType && $componentType === 'button' && 
                    null !== $componentSubType && $componentSubType === 'order_details') {
                    $hasOrderDetailsButtonInPayload = true;
                    $orderDetailsButtonComponent = $component;
                    $firstParameter = $component['parameters'][0] ?? null;
                    $action = $firstParameter['action'] ?? null;
                    
                    \Log::info('✅ [ORDER_DETAILS DEBUG] Step 6.2: ORDER_DETAILS button found in payload', [
                        'button_index' => $component['index'] ?? null,
                        'has_parameters' => !empty($component['parameters']),
                        'parameters_count' => count($component['parameters'] ?? []),
                        'has_action' => null !== $action,
                        'has_order_details' => null !== ($action['order_details'] ?? null)
                    ]);
                    break;
                }
            }
            
            if (!$hasOrderDetailsButtonInPayload) {
                \Log::warning('⚠️ [ORDER_DETAILS DEBUG] Step 6.2: No ORDER_DETAILS button found in payload', [
                    'message_components_types' => array_column($messageComponents, 'type')
                ]);
            }
            
            // Get template to check if it has ORDER_DETAILS
            \Log::info('🔍 [ORDER_DETAILS DEBUG] Step 6.3: Fetching template for validation', [
                'template_name' => $whatsAppTemplateName,
                'template_language' => $whatsAppTemplateLanguage
            ]);
            
            $templateForValidation = $this->whatsAppTemplateRepository->fetchIt([
                'vendors__id' => $vendorId,
                'template_name' => $whatsAppTemplateName,
                'language' => $whatsAppTemplateLanguage
            ]);
            
            if ($templateForValidation) {
                $templateData = $templateForValidation->toArray();
                $templateComponents = Arr::get($templateData, '__data.template.components', []);
                $templateHasOrderDetails = false;
                
                \Log::info('🔍 [ORDER_DETAILS DEBUG] Step 6.4: Checking template for ORDER_DETAILS button', [
                    'template_components_count' => count($templateComponents)
                ]);
                
                foreach ($templateComponents as $templateComponent) {
                    if ($templateComponent['type'] == 'BUTTONS') {
                        foreach ($templateComponent['buttons'] ?? [] as $button) {
                            if ($button['type'] === 'ORDER_DETAILS') {
                                $templateHasOrderDetails = true;
                                \Log::info('✅ [ORDER_DETAILS DEBUG] Step 6.5: Template has ORDER_DETAILS button', [
                                    'button_text' => $button['text'] ?? 'no text'
                                ]);
                                break 2;
                            }
                        }
                    }
                }
                
                \Log::info('🔍 [ORDER_DETAILS DEBUG] Step 6.6: Validation summary', [
                    'template_has_order_details' => $templateHasOrderDetails,
                    'payload_has_order_details_button' => $hasOrderDetailsButtonInPayload,
                    'validation_passed' => !$templateHasOrderDetails || $hasOrderDetailsButtonInPayload
                ]);
                
                if ($templateHasOrderDetails && !$hasOrderDetailsButtonInPayload) {
                    \Log::error('❌ [ORDER_DETAILS DEBUG] Step 6.7 FAILED: ORDER_DETAILS BUTTON MISSING IN PAYLOAD', [
                        'template_name' => $whatsAppTemplateName,
                        'template_has_order_details' => true,
                        'payload_has_order_details_button' => false,
                        'message_components' => $messageComponents
                    ]);
                    
                    return $this->engineFailedResponse([], __tr('ORDER_DETAILS template requires order_data and button component. Please provide order information.'));
                }
                
                \Log::info('✅ [ORDER_DETAILS DEBUG] Step 6.7 PASSED: ORDER_DETAILS validation successful', []);
            } else {
                \Log::warning('⚠️ [ORDER_DETAILS DEBUG] Step 6.4: Template not found for validation', []);
            }
            
            \Log::info('FINAL WHATSAPP PAYLOAD', [
                'payload' => $finalPayload,
                'components' => $finalPayload['template']['components']
            ]);
            
            try {
                $sendMessageResult = $this->whatsAppApiService->sendTemplateMessage($whatsAppTemplateName, $whatsAppTemplateLanguage, $contactWhatsappNumber, $messageComponents, $vendorId);
                
                \Log::info('✅ WhatsApp API call completed', [
                    'result_type' => gettype($sendMessageResult),
                    'result_keys' => is_array($sendMessageResult) ? array_keys($sendMessageResult) : 'not_array',
                    'has_error' => isset($sendMessageResult['error']),
                    'has_messages' => isset($sendMessageResult['messages'])
                ]);
            } catch (\Exception $e) {
                \Log::error('🚨 WhatsApp API call failed with exception', [
                    'exception_message' => $e->getMessage(),
                    'exception_file' => $e->getFile(),
                    'exception_line' => $e->getLine(),
                    'exception_trace' => $e->getTraceAsString()
                ]);
                $sendMessageResult = ['error' => ['message' => $e->getMessage(), 'code' => 'EXCEPTION']];
            }
            
            // Debug: Log the response from WhatsApp API
            \Log::info('WhatsApp API Response', [
                'response' => $sendMessageResult,
                'has_error' => isset($sendMessageResult['error']),
                'error_code' => $sendMessageResult['error']['code'] ?? 'no_error',
                'error_message' => $sendMessageResult['error']['message'] ?? 'no_error',
                'error_type' => $sendMessageResult['error']['type'] ?? 'no_error'
            ]);
            
            // Debug: Check for specific button index errors
            $errorMessage = $sendMessageResult['error']['message'] ?? null;
            if (null !== $errorMessage && 
                strpos($errorMessage, 'multiple') !== false) {
                \Log::error('🚨 BUTTON INDEX ERROR DETECTED', [
                    'error_message' => $sendMessageResult['error']['message'],
                    'error_code' => $sendMessageResult['error']['code'],
                    'full_response' => $sendMessageResult
                ]);
            }
        }
        $messageResponseStatus = Arr::get($sendMessageResult, 'messages.0.id') ? 'accepted' : null;
        // store it into db
        $recordCreated = $this->whatsAppMessageLogRepository->storeIt([
            'vendors__id' => $vendorId,
            'status' => $messageResponseStatus,
            'contacts__id' => $contactId,
            'campaigns__id' => $campaignId,
            'wab_phone_number_id' => $currentPhoneNumberId,
            'is_incoming_message' => 0,
            'contact_wa_id' => Arr::get($sendMessageResult, 'contacts.0.wa_id'),
            'wamid' => Arr::get($sendMessageResult, 'messages.0.id'),
            '__data' => [
                'contact_data' => $contactsData,
                'initial_response' => $sendMessageResult,
                'template_proforma' => $templateProforma,
                'template_components' => $templateComponents,
                'template_component_values' => $messageComponents,
            ],
        ]);
        if ($messageResponseStatus == 'accepted') {
            $contact = null;
            if ($contactUid) {
                $contact = $this->contactRepository->fetchIt($contactUid);
            }
            
            return $this->engineSuccessResponse([
                'messageUid' => $recordCreated->_uid,
                'contactUid' => $contactUid,
                'log_message' => $recordCreated,
                'contact' => $contact,
            ], __tr('Message processed for WhatsApp contact'));
        }

        return $this->engineFailedResponse([], __tr('Failed to process Message for WhatsApp contact. Status: __messageStatus__', [
            '__messageStatus__' => $messageResponseStatus,
        ]));
    }


    /**
     * Mark unread messages as read
     *
     * @param  eloquent  $contact
     * @return void
     */
    public function markAsReadProcess($contact, $vendorId)
    {
        if (!__isEmpty($contact)) {
            if ($contact->lastUnreadMessage) {
                ignoreFacebookApiError(true);
                try {
                    $this->whatsAppApiService->markAsRead($contact->lastUnreadMessage->wa_id, $contact->lastUnreadMessage->wamid, $vendorId);
                } catch (\Throwable $th) {
                    //throw $th;
                }
                ignoreFacebookApiError(false);
            }
            $this->whatsAppMessageLogRepository->markAsRead($contact, $vendorId);
            // update unread count
            $this->updateUnreadCount();
        }
    }

    /**
     * Prepare chat window data
     *
     * @return EngineResponse
     */
    public function chatData(string|null $contactUid, string|null $assigned)
    {
        $vendorId = getVendorId();
        if (isDemo() and isDemoVendorAccount() and !$contactUid) {
            $contactUid = getVendorSettings('test_recipient_contact');
        }
        $this->hasMoreMessages = 0;
        $currentPaginatedPage = request()->page;

        $contact = $this->contactRepository->getVendorContactWithUnreadDetails($contactUid, $vendorId, $assigned);
        // if(!__isEmpty($contact)) {
        // mark unread chats as read
        $this->markAsReadProcess($contact, $vendorId);
        // }
        $dataToSend = [
            // check if received incoming message from contact in last 24 hours
            // the direct message won't be delivered if not received any message by user in last 24 hours
            'isDirectMessageDeliveryWindowOpened' => null,
            'directMessageDeliveryWindowOpenedTillMessage' => '',
            'contact' => null,
            'contacts' => [],
            'whatsappMessageLogs' => [],
            'assigned' => $assigned,
            'currentlyAssignedUserUid' => '',
            'isAiChatBotEnabled' => null,
            // 'assignedLabelIds' => [],
        ];
        if (!request()->ajax()) {
            // labels
            $allLabels = $this->labelRepository->fetchItAll([
                'vendors__id' => $vendorId
            ]);
            // contact custom fields
            $vendorContactCustomFields = $this->contactCustomFieldRepository->fetchItAll([
                'vendors__id' => $vendorId,
            ]);
            // contact groups
            $vendorContactGroups = $this->contactGroupRepository->fetchItAll([
                'vendors__id' => $vendorId,
            ]);
            // Load filter texts from vendor_settings
            $filterTextsSetting = getVendorSettings('filter_texts', null, null, $vendorId);
            $allFilterTexts = [];
            if ($filterTextsSetting) {
                if (is_string($filterTextsSetting)) {
                    $decoded = json_decode($filterTextsSetting, true);
                    $allFilterTexts = is_array($decoded) ? $decoded : [];
                } elseif (is_array($filterTextsSetting)) {
                    $allFilterTexts = $filterTextsSetting;
                }
            }
            
            $dataToSend = array_merge($dataToSend, [
                // get the vendor users having messaging permission
                'vendorMessagingUsers' => $this->userRepository->getVendorMessagingUsers($vendorId),
                'vendorContactGroups' => $vendorContactGroups,
                'vendorContactCustomFields' => $vendorContactCustomFields,
                'allLabels' => $allLabels,
                'allFilterTexts' => $allFilterTexts,
                // 'assignedLabelIds' => $contact?->labels->pluck('_id')->toArray() ?? [],
            ]);
        }
        if (__isEmpty($contact)) {
            return $this->engineSuccessResponse($dataToSend);
        }
        // $contactsData = $this->contactsData($contact)->data();
        updateClientModels([
            'assignedLabelIds' => $contact->labels->pluck('_id')->toArray() ?? [],
            // 'contacts' =>  $this->singleAsContactsData($contact->_uid)->data('contacts'),
            '@contacts' => 'extend'
        ]);
        return $this->engineSuccessResponse(array_merge($dataToSend, [
            // check if received incoming message from contact in last 24 hours
            // the direct message won't be delivered if not received any message by user in last 24 hours
            'isDirectMessageDeliveryWindowOpened' => (! __isEmpty($contact->lastIncomingMessage) and ($contact->lastIncomingMessage?->messaged_at?->diffInHours() < 24)),
            'directMessageDeliveryWindowOpenedTillMessage' => __tr('Reply window open for __availableTimeForReply__', [
                '__availableTimeForReply__' => $contact->lastIncomingMessage?->messaged_at?->addHours(24)->diffForHumans([
                    'parts' => 2,
                    'join' => true,
                ])
            ]),
            'contact' => $contact,
            // 'contacts' => $contactsData['contacts'],
            'contacts' =>  $this->singleAsContactsData($contact->_uid)->data('contacts'),
            // 'contactsPaginatePage' => $contactsData['contactsPaginatePage'],
            'whatsappMessageLogs' => $this->getContactMessagesForChatBox($contact->_id),
            // get the vendor users having messaging permission
            // 'vendorMessagingUsers' => $this->userRepository->getVendorMessagingUsers($vendorId),
            // 'assigned' => $assigned,
            'currentlyAssignedUserUid' => $contact->assignedUser->_uid ?? '',
            'isAiChatBotEnabled' => !$contact->disable_ai_bot,
            // 'vendorContactGroups' => $vendorContactGroups,
            // 'vendorContactCustomFields' => $vendorContactCustomFields,
            // 'allLabels' => $allLabels,
            // 'assignedLabelIds' => $contact->labels->pluck('_id')->toArray() ?? [],
            'messagePaginatePage' => ($this->hasMoreMessages ? ($currentPaginatedPage ? ($currentPaginatedPage + 1) : 2) : 0)
        ]));
    }

    /**
     * Prepare the contact messages for the chat box
     *
     * @param integer $contactId
     * @param boolean $onlyRecent
     * @return object
     */
    protected $hasMoreMessages = false;
    protected function getContactMessagesForChatBox(int $contactId, bool $onlyRecent = false)
    {
        if (! $onlyRecent) {
            $resultOfMessages = $this->whatsAppMessageLogRepository->allMessagesOfContact($contactId);
        } else {
            $resultOfMessages = $this->whatsAppMessageLogRepository->recentMessagesOfContact($contactId);
        }
        $this->hasMoreMessages = $resultOfMessages->hasMorePages();
        return $resultOfMessages->keyBy('_uid')->transform(function ($item, string $key) {
            $item->message = $this->formatWhatsAppText($item->message);
            $item->template_message = null;
            if (! $item->message || Arr::get($item->__data, 'interaction_message_data')) {
                $item->template_message = $this->compileMessageWithValues($item->__data);
            }
            return $item;
        });
    }

    /**
     * Prepare Single chat logs for current selected user
     *
     * @return EngineResponse
     */
    public function contactChatData(string|int $contactIdOrUid)
    {
        $contactId = is_string($contactIdOrUid) ? $this->contactRepository->getVendorContact($contactIdOrUid)->_id : $contactIdOrUid;

        return $this->engineSuccessResponse([
            'whatsappMessageLogs' => $this->getContactMessagesForChatBox($contactId, true),
        ]);
    }

    /**
     * Prepare Single chat logs for current selected user
     *
     * @param  string|int  $contactUid
     * @param  string|null  $assigned
     * @return EngineResponse
     */
    protected $hasMoreContacts = false;
    public function contactsData($contactUid, $assigned = null)
    {
        $vendorId = getVendorId();
        if (is_string($contactUid)) {
            $contact = $this->contactRepository->getVendorContactWithUnreadDetails($contactUid, $vendorId, $assigned);
        } else {
            $contact = $contactUid;
        }
        $this->hasMoreContacts = 0;
        $currentPaginatedPage = request()->page;

        $vendorContactsWithUnreadDetails = $this->contactRepository->getVendorContactsWithUnreadDetails($vendorId, $assigned);
        $this->hasMoreContacts = $vendorContactsWithUnreadDetails->hasMorePages();
        if (!__isEmpty($contact)) {
            $isContactInTheList = $vendorContactsWithUnreadDetails->where('_id', $contact->_id)->count();
            if (!$isContactInTheList) {
                $vendorContactsWithUnreadDetails = $vendorContactsWithUnreadDetails->toBase()->merge([$contact]);
            }
        }
        $responseEngineData = [
            'contacts' => $vendorContactsWithUnreadDetails->keyBy('_uid'),
        ];
        if (!request()->request_contact) {
            $responseEngineData['contactsPaginatePage'] = ($this->hasMoreContacts ? ($currentPaginatedPage ? ($currentPaginatedPage + 1) : 2) : 0);
        }
        return $this->engineSuccessResponse($responseEngineData);
    }
    /**
     * Prepare Single chat logs for current selected user
     *
     * @param  string|int  $contactUid
     * @param  string|null  $assigned
     * @return EngineResponse
     */
    protected function singleAsContactsData($contactUid, $assigned = null)
    {
        $vendorId = getVendorId();
        if (is_string($contactUid)) {
            $contact = $this->contactRepository->getVendorContactWithUnreadDetails($contactUid, $vendorId, $assigned);
        } else {
            $contact = $contactUid;
        }
        // Get contacts data - use actual vendor ID
        $vendorContactsWithUnreadDetails = $this->contactRepository->getVendorContactsWithUnreadDetails($vendorId, $assigned);
        $this->hasMoreContacts = $vendorContactsWithUnreadDetails->hasMorePages();
        if (!__isEmpty($contact)) {
            $isContactInTheList = $vendorContactsWithUnreadDetails->where('_id', $contact->_id)->count();
            if (!$isContactInTheList) {
                $vendorContactsWithUnreadDetails = $vendorContactsWithUnreadDetails->toBase()->merge([$contact]);
            }
        }
        return $this->engineSuccessResponse([
            'contacts' => $vendorContactsWithUnreadDetails->keyBy('_uid')
        ]);
    }

    /**
     * Compile Message with required values
     *
     * @param array $messageData
     * @return view|string
     */
    protected function compileMessageWithValues($messageData)
    {
        if (isset($messageData['interaction_message_data'])) {
            return view('whatsapp-service.interaction-message-partial', [
                'mediaValues' => array_merge([
                    'media_link' => '',
                    'header_type' => '', // "text", "image", or "video"
                    'header_text' => '',
                    'body_text' => '',
                    'footer_text' => '',
                    'buttons' => [
                    ],
                ], $messageData['interaction_message_data']),
            ])->render();
        } elseif (isset($messageData['media_values'])) {
            $messageData['media_values']['caption'] = $this->formatWhatsAppText($messageData['media_values']['caption'] ?? '');
            return view('whatsapp-service.media-message-partial', [
                'mediaValues' => array_merge([
                    'link' => null,
                    'type' => null,
                    'caption' => null,
                    'file_name' => null,
                    'original_filename' => null,
                ], $messageData['media_values']),
            ])->render();
        }

        if (! isset($messageData['template_components']) or ! isset($messageData['template_component_values'])) {
            return null;
        }
        $templateComponents = $messageData['template_components'];
        $templateComponentValues = $messageData['template_component_values'];
        $bodyItemValues = [];
        $headerItemValues = [
            'image' => null,
            'video' => null,
            'document' => null,
            'location' => null,
            'text' => [],
        ];
        $buttonIndex = 1;
        $buttonValues = [];
        foreach ($templateComponentValues as $templateComponentValue) {
            if ($templateComponentValue['type'] == 'body') {
                if (isset($templateComponentValue['parameters']) && is_array($templateComponentValue['parameters'])) {
                    foreach ($templateComponentValue['parameters'] as $templateComponentValueParameterKey => $templateComponentValueParameter) {
                        $bodyItemValues[$templateComponentValueParameterKey] = $templateComponentValueParameter['text'] ?? '';
                    }
                }
            } elseif ($templateComponentValue['type'] == 'header') {
                if (isset($templateComponentValue['parameters']) && is_array($templateComponentValue['parameters'])) {
                    foreach ($templateComponentValue['parameters'] as $templateComponentValueParameterKey => $templateComponentValueParameter) {
                        if ($templateComponentValueParameter['type'] == 'image') {
                            $headerItemValues['image'] = $templateComponentValueParameter['image']['link'] ?? '';
                        } elseif ($templateComponentValueParameter['type'] == 'video') {
                            $headerItemValues['video'] = $templateComponentValueParameter['video']['link'] ?? '';
                        } elseif ($templateComponentValueParameter['type'] == 'document') {
                            $headerItemValues['document'] = $templateComponentValueParameter['document']['link'] ?? '';
                        } elseif ($templateComponentValueParameter['type'] == 'location') {
                            $headerItemValues['location'] = $templateComponentValueParameter['location'] ?? [
                                'name' => '',
                                'address' => '',
                                'latitude' => null,
                                'longitude' => null,
                            ];
                        } elseif ($templateComponentValueParameter['type'] == 'text') {
                            $headerItemValues['text'][] = $templateComponentValueParameter['text'] ?? '';
                        }
                    }
                }
            } elseif ($templateComponentValue['type'] == 'button') {
                if (isset($templateComponentValue['parameters']) && is_array($templateComponentValue['parameters'])) {
                    if ($templateComponentValue['sub_type'] == 'URL') {
                        $firstParam = $templateComponentValue['parameters'][0] ?? null;
                        $textValue = $firstParam['text'] ?? null;
                        if (null !== $textValue) {
                            $buttonValues["{{{$buttonIndex}}}"] = $textValue;
                            $buttonIndex++;
                        }
                    } elseif ($templateComponentValue['sub_type'] == 'COPY_CODE') {
                        $buttonValues['COPY_CODE'] = $templateComponentValue['parameters'][0]['coupon_code'] ?? null;
                    } elseif ($templateComponentValue['sub_type'] == 'CATALOG') {
                        // Catalog button - store thumbnail product ID if provided
                        $firstParam = $templateComponentValue['parameters'][0] ?? null;
                        $action = $firstParam['action'] ?? null;
                        $thumbnailId = $action['thumbnail_product_retailer_id'] ?? null;
                        if (null !== $thumbnailId) {
                            $buttonValues['CATALOG_THUMBNAIL'] = $thumbnailId;
                        } else {
                            $buttonValues['CATALOG_THUMBNAIL'] = null; // Will use default catalog thumbnail
                        }
                    }
                }
            }
        }

        return view('whatsapp-service.message-template-partial', array_merge($this->prepareTemplate('for_message', [
            'templateComponents' => $templateComponents,
        ]), [
            'templateComponentValues' => $templateComponentValues,
            'headerItemValues' => $headerItemValues,
            'bodyItemValues' => $bodyItemValues,
            'buttonValues' => $buttonValues,
        ]))->render();
    }

    /**
     * Clear Chat history for the Contact
     *
     * @param string $contactUid
     * @return void
     */
    public function processClearChatHistory($contactUid)
    {
        $vendorId = getVendorId();
        $contact = $this->contactRepository->getVendorContact($contactUid, $vendorId);
        abortIf(__isEmpty($contact));
        if ($this->whatsAppMessageLogRepository->clearChatHistory($contact->_id, $contact->vendors__id)) {
            updateClientModels([
                'whatsappMessageLogs' => $this->getContactMessagesForChatBox($contact->_id, true),
            ]);

            return $this->engineSuccessResponse([], __tr('Chat history has been cleared for __contactFullName__', [
                '__contactFullName__' => $contact->full_name,
            ]));
        }

        return $this->engineFailedResponse([], __tr('No chat history to clear for __contactFullName__', [
            '__contactFullName__' => $contact->full_name,
        ]));
    }

    /**
     * Send Chat Message Process
     *
     * @param Request $request
     * @param boolean $isMediaMessage
     * @param integer $vendorId
     * @param array $options
     * @return EngineResponse
     */
    public function processSendChatMessage($request, bool $isMediaMessage = false, $vendorId = null, $options = [])
    {
        $vendorId = $vendorId ?: getVendorId();
        // check if vendor has active plan
        $vendorPlanDetails = vendorPlanDetails(null, null, $vendorId);
        if (!$vendorPlanDetails->hasActivePlan()) {
            return $this->engineResponse(22, null, $vendorPlanDetails['message']);
        }
        $interactionMessageData = null;
        $mediaMessageData = null;
        $fromPhoneNumberId = null;
        if (is_array($request) === true) {
            $messageBody = $request['messageBody'];
            $contactUid = $request['contactUid'];
            if (isset($options['interaction_message_data']) and !empty($options['interaction_message_data'])) {
                $interactionMessageData = $options['interaction_message_data'];
            }
            if (isset($options['media_message_data']) and !empty($options['media_message_data'])) {
                $mediaMessageData = $options['media_message_data'];
            }
        } else {
            $messageBody = $request->message_body;
            $contactUid = $request->contact_uid;
            $fromPhoneNumberId = $request->from_phone_number_id;
        }
        // options extend
        $options = array_merge([
            'from_phone_number_id' => $fromPhoneNumberId,
            'messageWamid' => null,
        ], $options);

        $contact = $this->contactRepository->getVendorContact($contactUid, $vendorId);
        if (__isEmpty($contact)) {
            if (isExternalApiRequest()) {
                $contact = $this->createAContactForApiRequest($request);
            } else {
                return $this->engineFailedResponse([], __tr('Requested contact does not found'));
            }
        }

        // Check if contact has opted out of messaging (block all OUTGOING messages including bot replies)
        // Note: INCOMING messages from users are always stored and visible regardless of opt-out status
        $isBotReply = $options['bot_reply'] ?? false;
        if ($contact->campaign_opt_out == 1) {
            // If opted out, don't send any OUTGOING messages (including bot replies) and don't show them in chat
            // Incoming messages from users are handled separately in processWebhook() and are always stored
            \Log::info('Outgoing message blocked due to opt-out', [
                'contact_uid' => $contact->_uid,
                'wa_id' => $contact->wa_id,
                'is_bot_reply' => $isBotReply,
                'message_body' => substr($messageBody ?? '', 0, 100)
            ]);
            return $this->engineFailedResponse([], __tr('This contact has opted out of receiving messages.'));
        }
        $currentBusinessPhoneNumber = $options['from_phone_number_id'] ?: ($contact->lastMessage->wab_phone_number_id ?? getVendorSettings('current_phone_number_id', null, null, $vendorId));
        fromPhoneNumberIdForRequest($currentBusinessPhoneNumber);
        $initializeLogMessage = null;
        // to show message in chat instantly we have created first log entry
        // only for chat box
        // Note: This is only created if contact hasn't opted out (checked above)
        if (!isExternalApiRequest() and $messageBody) {
            $initializeLogMessage = $this->whatsAppMessageLogRepository->storeIt([
                'is_incoming_message' => 0,
                'status' => 'initialize',
                'contact_wa_id' => $contact->wa_id,
                'contacts__id' => $contact->_id,
                'vendors__id' => $vendorId,
                'wab_phone_number_id' => $currentBusinessPhoneNumber,
                'message' => $messageBody,
                'messaged_at' => now(),
                '__data' => [
                    'options' => Arr::only($options, [
                        'bot_reply',
                        'ai_bot_reply',
                    ]),
                ],
            ]);
            // update message list
            updateClientModelsViaEvent([
                'whatsappMessageLogs' => $this->contactChatData($contact->_id)->data('whatsappMessageLogs'),
            ], 'prepend');
            // useful for scrolling
            dispatchStreamEventData('onChatBoxMessageSubmit', []);
            $options['message_log_id'] = $initializeLogMessage->_id;
        }
        // do not mark messages as unread if bot replies sent or ai triggered
        if ((Arr::get($options, 'ai_error_triggered') != true) and (Arr::get($options, 'bot_reply') != true)) {
            // mark unread chats as read if any
            $this->markAsReadProcess($contact, $vendorId);
        }

        $mediaData = [];
        $serviceName = getAppSettings('name');
        $contact = $this->contactRepository->getVendorContact($contactUid, $vendorId);
        if (__isEmpty($contact)) {
            if (isExternalApiRequest()) {
                $contact = $this->createAContactForApiRequest($request);
            } else {
                return $this->engineFailedResponse([], __tr('Requested contact does not found'));
            }
        }

        // Log interaction message data
        \Illuminate\Support\Facades\Log::info('Interactive Message Data', [
            'interaction_data' => $interactionMessageData
        ]);

        if ($interactionMessageData) {
            $serviceName = getAppSettings('name');

            // Format the message data for WhatsApp API
            $messagePayload = [ 
                'type' => 'interactive'
            ];

            // Handle WhatsApp Flow interactive payload
            if (($interactionMessageData['interaction_type'] ?? null) === 'flow' || isset($interactionMessageData['flow_id'])) {
                $flowId = $interactionMessageData['flow_id'] ?? null;
                $flowVersion = $interactionMessageData['flow_version'] ?? null;
                $headerText = $interactionMessageData['header_text'] ?? '';
                $bodyText = $interactionMessageData['body_text'] ?? $messageBody;
                $footerText = $interactionMessageData['footer_text'] ?? '';

                $messagePayload['interactive'] = [
                    'type' => 'flow',
                    'action' => [
                        'name' => 'flow',
                        'parameters' => [
                            'flow_id' => $flowId,
                            'flow_version' => $flowVersion,
                            'flow_action' => 'navigate'
                        ]
                    ],
                    'body' => [
                        'text' => isDemo() ? "{$serviceName} DEMO - " . $bodyText : $bodyText
                    ]
                ];

                if (!empty($headerText)) {
                    $messagePayload['interactive']['header'] = [
                        'type' => 'text',
                        'text' => $headerText
                    ];
                }

                if (!empty($footerText)) {
                    $messagePayload['interactive']['footer'] = [
                        'text' => $footerText
                    ];
                }

                // Clean up missing header/footer
                if (empty($headerText)) {
                    unset($messagePayload['interactive']['header']);
                }
                if (empty($footerText)) {
                    unset($messagePayload['interactive']['footer']);
                }

                // Build the complete payload for sendInteractiveMessage
                $flowMessageData = [
                    'interactive_type' => 'flow',
                    'flow_id' => $flowId,
                    'flow_version' => $flowVersion,
                    'header_text' => $headerText,
                    'body_text' => $bodyText,
                    'footer_text' => $footerText,
                ];

                \Illuminate\Support\Facades\Log::info('Sending Flow Message via sendInteractiveMessage', [
                    'to' => $contact->wa_id,
                    'flow_id' => $flowId,
                    'flow_version' => $flowVersion
                ]);

                $response = $this->whatsAppApiService->sendInteractiveMessage($contact->wa_id, $flowMessageData, $vendorId);

                // Check for errors in the response
                if (isset($response['error']) || (isset($response['success']) && $response['success'] === false)) {
                    if (!empty($initializeLogMessage)) {
                        $this->whatsAppMessageLogRepository->updateIt($initializeLogMessage->_id, [
                            'status' => 'failed'
                        ]);
                    }
                    return $response;
                }

                // update log status
                if (!empty($initializeLogMessage)) {
                    $this->whatsAppMessageLogRepository->updateIt($initializeLogMessage->_id, [
                        'status' => 'accepted'
                    ]);
                }

                return $response;
            }

            // Handle list type messages
            // PRIORITY 1: Check explicit interactive_type (from flow handlers)
            // PRIORITY 2: Check for list_data with sections (most common case from flows)
            // PRIORITY 3: Check explicit interaction_type (legacy)
            $isListType = (isset($interactionMessageData['interactive_type']) && $interactionMessageData['interactive_type'] === 'list') ||
                          (isset($interactionMessageData['list_data']['sections']) && !empty($interactionMessageData['list_data']['sections'])) ||
                          (isset($interactionMessageData['interaction_type']) && $interactionMessageData['interaction_type'] === 'list');
            
            // Log type determination for debugging
            \Illuminate\Support\Facades\Log::info('Determined interactive message type (second path)', [
                'is_list_type' => $isListType,
                'has_interactive_type' => isset($interactionMessageData['interactive_type']),
                'interactive_type_value' => $interactionMessageData['interactive_type'] ?? 'not_set',
                'has_list_data' => isset($interactionMessageData['list_data']),
                'has_list_data_sections' => isset($interactionMessageData['list_data']['sections']) && !empty($interactionMessageData['list_data']['sections']),
                'has_interaction_type' => isset($interactionMessageData['interaction_type']),
                'interaction_type_value' => $interactionMessageData['interaction_type'] ?? 'not_set'
            ]);
            
            if ($isListType) {
                $messagePayload['interactive'] = [
                    'type' => 'list',
                    'body' => [
                        'text' => isDemo() ? "{$serviceName} DEMO - " . $interactionMessageData['body_text'] : $interactionMessageData['body_text']
                    ],
                    'action' => [
                        'button' => $interactionMessageData['list_data']['button_text'] ?? 'Select an option',
                        'sections' => []
                    ]
                ];

                // Add header if present
                if (!empty($interactionMessageData['header_text'])) {
                    $messagePayload['interactive']['header'] = [
                        'type' => 'text',
                        'text' => $interactionMessageData['header_text']
                    ];
                }

                // Add footer if present
                if (!empty($interactionMessageData['footer_text'])) {
                    $messagePayload['interactive']['footer'] = [
                        'text' => $interactionMessageData['footer_text']
                    ];
                }

                // Process sections
                if (isset($interactionMessageData['list_data']['sections'])) {
                    foreach ($interactionMessageData['list_data']['sections'] as $section) {
                        $processedSection = [
                            'title' => $section['title']
                        ];

                        $rows = [];
                        foreach ($section['rows'] as $row) {
                            // Handle both 'row_id' and 'id' fields for backward compatibility
                            $rowId = $row['row_id'] ?? $row['id'] ?? '';

                            $rows[] = [
                                'id' => (string)$rowId,
                                'title' => $row['title'] ?? '',
                                'description' => $row['description'] ?? ''
                            ];
                        }

                        if (!empty($rows)) {
                            $processedSection['rows'] = $rows;
                            $messagePayload['interactive']['action']['sections'][] = $processedSection;
                        }
                    }
                }

                // Log the final payload
                \Illuminate\Support\Facades\Log::info('Sending List Message Payload', [
                    'payload' => $messagePayload
                ]);

                // Convert messagePayload to the format expected by WhatsAppApiService
                $apiServiceData = [
                    'interactive_type' => 'list',
                    'header_text' => $messagePayload['interactive']['header']['text'] ?? '',
                    'body_text' => $messagePayload['interactive']['body']['text'] ?? '',
                    'footer_text' => $messagePayload['interactive']['footer']['text'] ?? '',
                    'list_data' => [
                        'button_text' => $messagePayload['interactive']['action']['button'] ?? 'Select an option',
                        'sections' => $messagePayload['interactive']['action']['sections'] ?? []
                    ]
                ];

                // 🔥 Inject node media into apiServiceData if extracted previously
                $node = $options['node'] ?? null;
                if (isset($node) && isset($node['media_url'])) {
                    $resolvedMedia = $this->resolveBotFlowMediaPayload(
                        $node['media_url'],
                        $node['media_type'] ?? 'image',
                        $node['media_id'] ?? null
                    );

                    $apiServiceData['media_url'] = $resolvedMedia['media_url'] ?? $node['media_url'];
                    $apiServiceData['media_type'] = $resolvedMedia['media_type'] ?? ($node['media_type'] ?? 'image');
                    if (!empty($resolvedMedia['media_id'])) {
                        $apiServiceData['media_id'] = $resolvedMedia['media_id'];
                    }
                    
                    if (!empty($node['caption']) && $apiServiceData['media_type'] !== 'document') {
                        $apiServiceData['caption'] = $node['caption'];
                    }
                    
                    if ($apiServiceData['media_type'] === 'document' && !empty($node['filename'])) {
                        $apiServiceData['filename'] = $node['filename'];
                    }
                }
                // ---- APPLY MEDIA HEADER IF PRESENT (fallback to interactionMessageData) ----
                elseif (!empty($interactionMessageData['media_url'])) {
                    $resolvedMedia = $this->resolveBotFlowMediaPayload(
                        $interactionMessageData['media_url'],
                        $interactionMessageData['media_type'] ?? 'image',
                        $interactionMessageData['media_id'] ?? null
                    );
                    
                    $apiServiceData['media_url'] = $resolvedMedia['media_url'] ?? $interactionMessageData['media_url'];
                    $apiServiceData['media_type'] = $resolvedMedia['media_type'] ?? ($interactionMessageData['media_type'] ?? 'image');
                    if (!empty($resolvedMedia['media_id'])) {
                        $apiServiceData['media_id'] = $resolvedMedia['media_id'];
                    }
                    
                    if (!empty($interactionMessageData['caption']) && $apiServiceData['media_type'] !== 'document') {
                        $apiServiceData['caption'] = $interactionMessageData['caption'];
                    }
                    
                    if ($apiServiceData['media_type'] === 'document' && !empty($interactionMessageData['filename'])) {
                        $apiServiceData['filename'] = $interactionMessageData['filename'];
                    }
                }

                \Illuminate\Support\Facades\Log::info('Converted list message for API service', [
                    'api_service_data' => $apiServiceData
                ]);

                // Build final payload for logging
                $payload = [
                    'messaging_product' => 'whatsapp',
                    'to' => $contact->wa_id,
                    'type' => 'interactive',
                    'interactive' => [
                        'type' => 'list',
                        'body' => ['text' => $apiServiceData['body_text']],
                        'action' => [
                            'button' => $apiServiceData['list_data']['button_text'],
                            'sections' => $apiServiceData['list_data']['sections']
                        ]
                    ]
                ];

                // 🔥 Inject node media into payload if extracted previously
                $node = $options['node'] ?? null;
                if (isset($node) && isset($node['media_url'])) {
                    $resolvedMedia = $this->resolveBotFlowMediaPayload(
                        $node['media_url'],
                        $node['media_type'] ?? 'image',
                        $node['media_id'] ?? null
                    );
                    $mType = $resolvedMedia['media_type'] ?? ($node['media_type'] ?? 'image');
                    $mKey = ($mType === 'document') ? 'document' : $mType;

                    $payload['interactive']['header'] = [
                        'type' => $mType,
                        $mKey => !empty($resolvedMedia['media_id'])
                            ? ['id' => $resolvedMedia['media_id']]
                            : ['link' => ($resolvedMedia['media_url'] ?? $node['media_url'])]
                    ];

                    if (!empty($node['caption']) && $mType !== 'document') {
                        $payload['interactive']['header'][$mKey]['caption'] = $node['caption'];
                    }

                    if ($mType === 'document' && !empty($node['filename'])) {
                        $payload['interactive']['header'][$mKey]['filename'] = $node['filename'];
                    }

                    \Illuminate\Support\Facades\Log::info('🔥 INTERACTIVE MEDIA MERGED SUCCESSFULLY', [
                        'type' => $mType,
                        'url' => $resolvedMedia['media_url'] ?? $node['media_url'],
                        'media_id' => $resolvedMedia['media_id'] ?? null,
                        'payload' => $payload
                    ]);
                }
                // ---- APPLY MEDIA HEADER IF PRESENT (fallback to interactionMessageData) ----
                elseif (!empty($interactionMessageData['media_url'])) {
                    $resolvedMedia = $this->resolveBotFlowMediaPayload(
                        $interactionMessageData['media_url'],
                        $interactionMessageData['media_type'] ?? 'image',
                        $interactionMessageData['media_id'] ?? null
                    );
                    $mediaType = $resolvedMedia['media_type'] ?? ($interactionMessageData['media_type'] ?? 'image');
                    $mediaKey = $mediaType;
                    
                    $payload['interactive']['header'] = [
                        'type' => $mediaType,
                        $mediaKey => !empty($resolvedMedia['media_id'])
                            ? ['id' => $resolvedMedia['media_id']]
                            : ['link' => ($resolvedMedia['media_url'] ?? $interactionMessageData['media_url'])]
                    ];

                    if (!empty($interactionMessageData['caption']) && $mediaType != 'document') {
                        $payload['interactive']['header'][$mediaKey]['caption'] = $interactionMessageData['caption'];
                    }

                    if ($mediaType == 'document' && !empty($interactionMessageData['filename'])) {
                        $payload['interactive']['header']['document']['filename'] = $interactionMessageData['filename'];
                    }
                }

                \Illuminate\Support\Facades\Log::info('MEDIA HEADER ATTACHED TO INTERACTIVE', [
                    'media_type' => $node['media_type'] ?? $interactionMessageData['media_type'] ?? null,
                    'url' => $node['media_url'] ?? $interactionMessageData['media_url'] ?? null,
                    'final_payload' => $payload
                ]);

                $sendMessageResult = $this->whatsAppApiService->sendInteractiveMessage(
                    $contact->wa_id,
                    $apiServiceData,
                    $contact->vendors__id
                );
            } else {
                // Handle other interactive message types (buttons, etc.)
                $interactionMessageData['body_text'] = isDemo() ? "{$serviceName} DEMO - " . $interactionMessageData['body_text'] : $interactionMessageData['body_text'];
                
                // Normalize interaction_type to interactive_type for API service compatibility
                if (isset($interactionMessageData['interaction_type']) && !isset($interactionMessageData['interactive_type'])) {
                    $interactionMessageData['interactive_type'] = $interactionMessageData['interaction_type'];
                }

                // Determine interaction type for payload building
                $interactionType = $interactionMessageData['interactive_type'] ?? $interactionMessageData['interaction_type'] ?? 'button';
                $bodyText = $interactionMessageData['body_text'] ?? '';

                // Build action payload based on type
                $actionPayload = null;
                if ($interactionType === 'cta_url' && isset($interactionMessageData['cta_url'])) {
                    $actionPayload = [
                        'name' => 'cta_url',
                        'parameters' => [
                            'display_text' => substr($interactionMessageData['cta_url']['display_text'] ?? 'Visit', 0, 20),
                            'url' => $interactionMessageData['cta_url']['url'] ?? ''
                        ]
                    ];
                } elseif (isset($interactionMessageData['buttons']) && !empty($interactionMessageData['buttons'])) {
                    $buttons = [];
                    $buttonIndex = 1;
                    foreach ($interactionMessageData['buttons'] as $button) {
                        $buttonTitle = is_array($button) ? ($button['title'] ?? $button['text'] ?? '') : $button;
                        if (!empty($buttonTitle)) {
                            $buttons[] = [
                                'type' => 'reply',
                                'reply' => [
                                    'id' => is_array($button) ? ($button['id'] ?? 'button-id' . $buttonIndex) : 'button-id' . $buttonIndex,
                                    'title' => $buttonTitle
                                ]
                            ];
                            $buttonIndex++;
                        }
                    }
                    if (!empty($buttons)) {
                        $actionPayload = ['buttons' => $buttons];
                    }
                }

                // Build final payload for logging
                $payload = [
                    'messaging_product' => 'whatsapp',
                    'to' => $contact->wa_id,
                    'type' => 'interactive',
                    'interactive' => [
                        'type' => $interactionType,
                        'body' => ['text' => $bodyText],
                        'action' => $actionPayload
                    ]
                ];

                // Add footer if present
                if (!empty($interactionMessageData['footer_text'])) {
                    $payload['interactive']['footer'] = [
                        'text' => $interactionMessageData['footer_text']
                    ];
                }

                // 🔥 Inject node media into payload if extracted previously
                $node = $options['node'] ?? null;
                if (isset($node) && isset($node['media_url'])) {
                    $resolvedMedia = $this->resolveBotFlowMediaPayload(
                        $node['media_url'],
                        $node['media_type'] ?? 'image',
                        $node['media_id'] ?? null
                    );
                    $mType = $resolvedMedia['media_type'] ?? ($node['media_type'] ?? 'image');
                    $mKey = ($mType === 'document') ? 'document' : $mType;

                    if (!empty($resolvedMedia['media_id'])) {
                        $interactionMessageData['media_id'] = $resolvedMedia['media_id'];
                    }
                    $interactionMessageData['media_url'] = $resolvedMedia['media_url'] ?? $node['media_url'];
                    $interactionMessageData['media_type'] = $mType;

                    $payload['interactive']['header'] = [
                        'type' => $mType,
                        $mKey => !empty($resolvedMedia['media_id'])
                            ? ['id' => $resolvedMedia['media_id']]
                            : ['link' => ($resolvedMedia['media_url'] ?? $node['media_url'])]
                    ];

                    if (!empty($node['caption']) && $mType !== 'document') {
                        $payload['interactive']['header'][$mKey]['caption'] = $node['caption'];
                        $interactionMessageData['caption'] = $node['caption'];
                    }

                    if ($mType === 'document' && !empty($node['filename'])) {
                        $payload['interactive']['header'][$mKey]['filename'] = $node['filename'];
                        $interactionMessageData['filename'] = $node['filename'];
                    }

                    \Illuminate\Support\Facades\Log::info('🔥 INTERACTIVE MEDIA MERGED SUCCESSFULLY', [
                        'type' => $mType,
                        'url' => $resolvedMedia['media_url'] ?? $node['media_url'],
                        'media_id' => $resolvedMedia['media_id'] ?? null,
                        'payload' => $payload
                    ]);
                }
                // ---- APPLY MEDIA HEADER IF PRESENT (fallback to interactionMessageData) ----
                elseif (!empty($interactionMessageData['media_url'])) {
                    $resolvedMedia = $this->resolveBotFlowMediaPayload(
                        $interactionMessageData['media_url'],
                        $interactionMessageData['media_type'] ?? 'image',
                        $interactionMessageData['media_id'] ?? null
                    );
                    $mediaType = $resolvedMedia['media_type'] ?? ($interactionMessageData['media_type'] ?? 'image');
                    $mediaKey = $mediaType;

                    if (!empty($resolvedMedia['media_id'])) {
                        $interactionMessageData['media_id'] = $resolvedMedia['media_id'];
                    }
                    $interactionMessageData['media_url'] = $resolvedMedia['media_url'] ?? $interactionMessageData['media_url'];
                    $interactionMessageData['media_type'] = $mediaType;
                    
                    $payload['interactive']['header'] = [
                        'type' => $mediaType,
                        $mediaKey => !empty($resolvedMedia['media_id'])
                            ? ['id' => $resolvedMedia['media_id']]
                            : ['link' => ($resolvedMedia['media_url'] ?? $interactionMessageData['media_url'])]
                    ];

                    if (!empty($interactionMessageData['caption']) && $mediaType != 'document') {
                        $payload['interactive']['header'][$mediaKey]['caption'] = $interactionMessageData['caption'];
                    }

                    if ($mediaType == 'document' && !empty($interactionMessageData['filename'])) {
                        $payload['interactive']['header']['document']['filename'] = $interactionMessageData['filename'];
                    }
                }

                \Illuminate\Support\Facades\Log::info('MEDIA HEADER ATTACHED TO INTERACTIVE', [
                    'media_type' => $node['media_type'] ?? $interactionMessageData['media_type'] ?? null,
                    'url' => $node['media_url'] ?? $interactionMessageData['media_url'] ?? null,
                    'final_payload' => $payload
                ]);

                \Illuminate\Support\Facades\Log::info('Sending non-list interactive message', [
                    'interaction_data' => $interactionMessageData
                ]);

                $sendMessageResult = $this->whatsAppApiService->sendInteractiveMessage(
                    $contact->wa_id,
                    $interactionMessageData,
                    $contact->vendors__id
                );
            }
        } elseif ($isMediaMessage) {
            $fileUrl = $fileName = $fileOriginalName = null;
            $rawUploadData = [];
            $caption = $request->caption ?? '';
            $mediaType = $request->media_type ?? '';
            if ($mediaMessageData) {
                // Extract media data from bot flow response
                $fileName = $mediaMessageData['file_name'] ?? '';
                $fileUrl = $mediaMessageData['media_url'] ?? $mediaMessageData['link'] ?? null; // Support both media_url and legacy 'link'
                $fileOriginalName = $mediaMessageData['file_name'] ?? '';
                $caption = $mediaMessageData['caption'] ?? '';
                $mediaType = $mediaMessageData['type'] ?? $mediaMessageData['header_type'] ?? 'image'; // Support both 'type' and legacy 'header_type'
                
                // Validate required fields
                if (empty($fileUrl)) {
                    \Log::error('Media message missing URL', [
                        'media_message_data' => $mediaMessageData,
                        'contact' => $contact->wa_id
                    ]);
                    return $this->engineFailedResponse([], __tr('Media URL is required'));
                }
                
                if (empty($mediaType)) {
                    $mediaType = 'image'; // Default to image if not specified
                }
            } elseif (!isValidUrl($request->media_url)) {
                $rawUploadData = json_decode($request->raw_upload_data, true);
                $isProcessed = $this->mediaEngine->whatsappMediaUploadProcess(['filepond' => $request->uploaded_media_file_name], 'whatsapp_' . $mediaType);
                if ($isProcessed->failed()) {
                    return $isProcessed;
                }
                $fileUrl = $isProcessed->data('path');
                $fileName = $isProcessed->data('fileName');
                $fileOriginalName = Arr::get($rawUploadData, 'original_filename');
            } else {
                $fileName = $request->file_name;
                $fileUrl = $request->media_url;
                $fileOriginalName = $fileName;
            }
            
            // Prepare WhatsApp payload based on media type
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $contact->wa_id,
                'type' => $mediaType,
            ];
            
            // Build media-specific payload structure
            if ($mediaType === 'document') {
                $payload['document'] = [
                    'link' => $fileUrl,
                    'filename' => $fileOriginalName ?: $fileName ?: 'file.pdf',
                ];
                if (!empty($caption)) {
                    $payload['document']['caption'] = isDemo() ? "{$serviceName} DEMO - " . $caption : $caption;
                }
            } elseif ($mediaType === 'image') {
                $payload['image'] = [
                    'link' => $fileUrl,
                ];
                if (!empty($caption)) {
                    $payload['image']['caption'] = isDemo() ? "{$serviceName} DEMO - " . $caption : $caption;
                }
            } elseif ($mediaType === 'video') {
                $payload['video'] = [
                    'link' => $fileUrl,
                ];
                if (!empty($caption)) {
                    $payload['video']['caption'] = isDemo() ? "{$serviceName} DEMO - " . $caption : $caption;
                }
            } elseif ($mediaType === 'audio') {
                $payload['audio'] = [
                    'link' => $fileUrl,
                ];
                // Audio doesn't support caption
            } else {
                // Fallback to generic media message
                $payload[$mediaType] = [
                    'link' => $fileUrl,
                ];
                if (!empty($caption) && !in_array($mediaType, ['audio', 'sticker'])) {
                    $payload[$mediaType]['caption'] = isDemo() ? "{$serviceName} DEMO - " . $caption : $caption;
                }
            }
            
            // Log the payload before sending
            \Log::info('Sending WhatsApp media message payload', [
                'payload' => $payload,
                'media_type' => $mediaType,
                'contact' => $contact->wa_id,
                'vendor_id' => $vendorId,
                'media_id_resolved' => $resolvedMedia['media_id'] ?? null,
                'media_url_resolved' => $resolvedMedia['media_url'] ?? null,
            ]);
            
            // Resolve media so we prefer a WhatsApp media_id (avoid link fetches by fwdproxy)
            $resolvedMedia = $this->resolveBotFlowMediaPayload($fileUrl, $mediaType, null);
            // prefer id, fallback to resolved URL or original file URL
            $mediaToSend = $resolvedMedia['media_id'] ?? ($resolvedMedia['media_url'] ?? $fileUrl);

            // Send via API service (sendMediaMessage accepts either a link or a WhatsApp media id)
            $sendMessageResult = $this->whatsAppApiService->sendMediaMessage(
                $contact->wa_id,
                $mediaType,
                $mediaToSend,
                (isDemo() ? "{$serviceName} DEMO - " . $caption : '' . $caption),
                $fileOriginalName,
                $vendorId
            );

            $mediaData = [
                'type' => $mediaType,
                'link' => $resolvedMedia['media_url'] ?? $fileUrl,
                'media_id' => $resolvedMedia['media_id'] ?? null,
                'caption' => $caption,
                'mime_type' => Arr::get($rawUploadData, 'fileMimeType'),
                'file_name' => $fileName,
                'original_filename' => $fileOriginalName,
            ];
        } else {
            $sendMessageResult = $this->whatsAppApiService->sendMessage($contact->wa_id, (isDemo() ? "`{$serviceName} DEMO`\n\r\n\r " . $messageBody : '' . $messageBody), $vendorId, [
                'repliedToMessageWamid' => $options['messageWamid']
            ]);
        }
        $messageWamid = Arr::get($sendMessageResult, 'messages.0.id');
        if (! $messageWamid) {
            if ($initializeLogMessage) {
                $initializeLogMessage->status =  'failed';
                $initializeLogMessage->save();
            }
            return $this->engineFailedResponse([
                'contact' => $contact,
            ], __tr('Failed to send message'));
        }
        if ($initializeLogMessage) {
            $initializeLogMessage->wamid =  $messageWamid;
            $initializeLogMessage->save();
        }
        $logMessage = $this->whatsAppMessageLogRepository->updateOrCreateWhatsAppMessageFromWebhook(
            $currentBusinessPhoneNumber,
            $contact->_id,
            $vendorId,
            $contact->wa_id,
            $messageWamid,
            'accepted',
            $sendMessageResult,
            $messageBody,
            null,
            $mediaData,
            false,
            $options
        );
        // update the client models by existing it
        updateClientModels([
            'whatsappMessageLogs' => $this->contactChatData($contact->_id)->data('whatsappMessageLogs'),
        ], 'prepend');

        return $this->engineSuccessResponse([
            'contact' => $contact,
            'log_message' => $logMessage,
        ], isExternalApiRequest() ? __tr('Message processed') : '');
    }

    /**
     * Set the Parameters to to concerned template dynamic values
     *
     * @param object $contact
     * @param array $inputs
     * @param mixed $item
     * @return mixed
     */
    protected function setParameterValue(&$contact, &$inputs, $item)
    {
        $inputValue = $inputs[$item];

        if (isExternalApiRequest()) {
            return $this->dynamicValuesReplacement($inputValue, $contact);
        }
        // for any internal requests
        if (Str::startsWith($inputValue, 'dynamic_contact_')) {
            // assign phone value
            if ($inputValue == 'dynamic_contact_phone_number') {
                $inputValue = 'dynamic_contact_wa_id';
            }
            // check if value permitted
            if (!array_key_exists($inputValue, configItem('contact_data_mapping'))) {
                return null;
            }
            // correct the name
            $fieldName = str_replace('dynamic_contact_', '', $inputValue);
            // country value
            switch ($fieldName) {
                case 'country':
                    return $contact->country?->name ?: '-';
                    break;
            }
            return $contact->{$fieldName} ?: '-';
            // custom field values
        } elseif (Str::startsWith($inputValue, 'contact_custom_field_')) {
            $fieldName = str_replace('contact_custom_field_', '', $inputValue);
            // for api external request find value by field name
            if (isExternalApiRequest()) {
                return $contact->valueWithField?->firstWhere('customField.input_name', $fieldName)?->field_value ?: '-';
            }
            return $contact->customFieldValues?->firstWhere('contact_custom_fields__id', $fieldName)?->field_value ?: '-';
        }
        return $inputs[$item] ?: '-';
    }

    /**
     * Get random product ID from catalog for thumbnail
     *
     * @param int|null $vendorId
     * @return string|null
     */
    protected function getRandomCatalogProductId($vendorId = null)
    {
        try {
            $vendorId = $vendorId ?: getVendorId();
            
            // Fetch products from catalog (limit to 100 for random selection)
            $productsResponse = $this->whatsAppApiService->getCatalogProducts(null, [
                'fields' => 'id',
                'limit' => 100
            ], $vendorId);
            
            if (isset($productsResponse['data']) && is_array($productsResponse['data']) && !empty($productsResponse['data'])) {
                $products = $productsResponse['data'];
                
                // Get random product
                $randomProduct = $products[array_rand($products)];
                
                // Return product ID (retailer_id)
                return $randomProduct['id'] ?? null;
            }
            
            // If no products found or API fails, return null (WhatsApp will use default)
            return null;
            
        } catch (\Exception $e) {
            // Log error but don't fail - WhatsApp will use default catalog thumbnail
            \Log::warning('Failed to get random catalog product for thumbnail', [
                'vendor_id' => $vendorId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Prepare string with values replacements
     *
     * @param string $inputValue
     * @param Eloquent $contact
     * @return string
     */
    protected function dynamicValuesReplacement($inputValue, &$contact)
    {
        $dynamicFieldsToReplace = [
            '{first_name}' => $contact->first_name,
            '{last_name}' => $contact->last_name,
            '{full_name}' => $contact->first_name . ' ' . $contact->last_name,
            '{phone_number}' => $contact->wa_id,
            '{email}' => $contact->email,
            '{country}' => $contact->country?->name,
            '{language_code}' => $contact->language_code,
        ];
        // Review this code and make the appropriate changes
        $valueWithFields = $contact->valueWithField;
        foreach ($valueWithFields as $valueWithField) {
            if ($valueWithField?->customField?->input_name) {
                $dynamicFieldsToReplace["{{$valueWithField->customField->input_name}}"] = $valueWithFields?->firstWhere('customField.input_name', $valueWithField->customField->input_name)?->field_value;
            }
        }
        // assign dynamic values
        return strtr($inputValue, $dynamicFieldsToReplace);
    }

    /**
     * Send message processed by bot reply
     *
     * @param string $contactUid
     * @param string $replyText
     * @param int $vendorId
     * @param array|null $interactionMessageData
     * @return void
     */
    protected function sendReplyBotMessage($contactUid, $replyText, $vendorId, $interactionMessageData = null, $options = [])
    {
        $options = array_merge([
            'from_phone_number_id' => null,
            'messageWamid' => null,
            'flow_id' => null,
        ], $options);

        // Persist button map for later button clicks (independent of active flow)
        // Handle both buttons and list_data sections
        if (!empty($interactionMessageData['buttons']) || !empty($interactionMessageData['list_data']['sections'])) {
            $this->persistButtonMapForContact($contactUid, $vendorId, $interactionMessageData, $options['flow_id']);
        }

        $mediaMessageData = $options['mediaMessageData'] ?? null;
        return $this->processSendChatMessage(
            [
            'contactUid' => $contactUid,
            'messageBody' => $replyText,
        ],
            $mediaMessageData ? true : false,
            $vendorId,
            [
            'bot_reply' => true,
            'ai_bot_reply' => $options['ai_bot_reply'] ?? false,
            'ai_error_triggered' => $options['ai_error_triggered'] ?? false,
            'media_message_data' => $mediaMessageData,
            'interaction_message_data' => $interactionMessageData,
            'from_phone_number_id' => $options['from_phone_number_id'],
            'messageWamid' => $options['messageWamid'],
        ]
        );
    }

    /**
     * Validate Bot Reply
     *
     * @param integer $testBotId
     * @return EngineResponse
     */
    public function validateTestBotReply(int $testBotId)
    {
        $testContactUid = getVendorSettings('test_recipient_contact');
        if (!$testContactUid) {
            return $this->engineFailedResponse([], __tr('Test Contact missing, You need to set the Test Contact first, do it under the WhatsApp Settings'));
        }
        $contact = $this->contactRepository->getVendorContact($testContactUid);
        if (__isEmpty($contact)) {
            return $this->engineFailedResponse([], __tr('Test contact does not found'));
        }
        return $this->processReplyBot($contact, '', $testBotId);
    }

    /**
     * Process the bot reply if required
     *
     * @param Eloquent Object $contact
     * @param string $messageBody
     * @return void
     */
    public function processReplyBot($contact, $messageBody, $testBotId = null, $options = [])
    {
        if (__isEmpty($contact)) {
            return false;
        }
        $options = array_merge([
            'fromPhoneNumberId' => null,
            'messageWamid' => null,
        ], $options);
        // check if vendor has active plan
        $vendorPlanDetails = vendorPlanDetails(null, null, $contact->vendors__id);
        if (!$vendorPlanDetails->hasActivePlan()) {
            return false;
        }
        $messageBody = strtolower(trim($messageBody));
        
        // ============================================
        // PRIORITY: Check for active flow waiting for input BEFORE checking bot reply triggers
        // This ensures question answers and flow inputs are processed immediately
        // ============================================
        if (!$testBotId && !empty(trim($messageBody))) {
            $activeFlow = UserActiveFlow::getActiveFlow($contact->wa_id);
            if ($activeFlow && isset($activeFlow->__data['waiting_for_input']) && $activeFlow->__data['waiting_for_input']) {
                \Illuminate\Support\Facades\Log::info('Active flow waiting for input detected in processReplyBot, processing flow', [
                    'user' => $contact->wa_id,
                    'flow_id' => $activeFlow->flow_id,
                    'current_node' => $activeFlow->__data['current_node_id'] ?? 'unknown',
                    'message' => $messageBody
                ]);
                
                // Get the bot reply associated with this flow
                $flowBotReply = $this->botReplyRepository->fetchIt([
                    'bot_flows__id' => $activeFlow->flow_id,
                    'vendors__id' => $contact->vendors__id
                ]);
                
                if ($flowBotReply) {
                    // Process flow using FlowIntegrationService
                    $flowIntegrationService = new FlowIntegrationService();
                    $flowResult = $flowIntegrationService->processNodeBasedFlow($contact, $messageBody, $flowBotReply, $options);
                    
                    if ($flowResult && isset($flowResult['processed_by_new_flow']) && $flowResult['processed_by_new_flow']) {
                        Log::info('Active flow processed successfully in processReplyBot', [
                            'user' => $contact->wa_id,
                            'response_count' => count($flowResult['responses'] ?? []),
                            'is_complete' => $flowResult['is_complete'] ?? false,
                            'ai_reply_sent' => $flowResult['ai_reply_sent'] ?? false
                        ]);
                        
                        // Process flow responses using the same logic as regular flow processing
                        // Only process if there are responses (flow may have completed with no next node)
                        if (!empty($flowResult['responses'])) {
                            foreach ($flowResult['responses'] as $response) {
                                $responseText = '';
                                $interactionData = null;
                                $mediaMsgData = null;

                                // Set response text if it exists (check both 'text' and 'body_text' for interactive messages)
                                if (isset($response['text'])) {
                                    $responseText = $response['text'];
                                } elseif (isset($response['body_text'])) {
                                    $responseText = $response['body_text'];
                                }

                                // Handle interactive responses
                                if (isset($response['type']) && $response['type'] === 'interactive') {
                                    // Extract node data from response if available
                                    $node = $response['node'] ?? null;
                                    
                                    // If node doesn't exist but media_url exists in response, construct node structure
                                    if (!isset($node) && isset($response['media_url']) && !empty($response['media_url'])) {
                                        $node = [
                                            'media_url' => $response['media_url'],
                                            'media_type' => $response['media_type'] ?? 'image',
                                        ];
                                        if (isset($response['caption'])) {
                                            $node['caption'] = $response['caption'];
                                        }
                                        if (isset($response['filename'])) {
                                            $node['filename'] = $response['filename'];
                                        }
                                    }
                                    
                                    $interactionData = [];
                                    
                                    // Pass node through interactionData so it's available in WhatsAppApiService
                                    if (isset($node)) {
                                        $interactionData['node'] = $node;
                                        $interactionData['node_id'] = $response['node_id'] ?? $node['id'] ?? null;
                                    }
                                    
                                    // Also pass media data directly to interactionData for compatibility
                                    if (isset($response['media_url']) && !empty($response['media_url'])) {
                                        $interactionData['media_url'] = $response['media_url'];
                                        $interactionData['media_type'] = $response['media_type'] ?? 'image';
                                        if (isset($response['caption'])) {
                                            $interactionData['caption'] = $response['caption'];
                                        }
                                        if (isset($response['filename'])) {
                                            $interactionData['filename'] = $response['filename'];
                                        }
                                    }
                                    
                                    if (isset($response['interaction_type'])) {
                                        $interactionData['interaction_type'] = $response['interaction_type'];
                                    }
                                    
                                    // Get body_text for interactive messages (prioritize body_text from response)
                                    $bodyText = $response['body_text'] ?? $responseText;
                                    
                                    // Handle CTA URL type
                                    if (isset($response['cta_url']) && !empty($response['cta_url'])) {
                                        $interactionData['interaction_type'] = 'cta_url';
                                        $interactionData['cta_url'] = $response['cta_url'];
                                        $interactionData['body_text'] = $bodyText;
                                        // Ensure responseText is set for sendReplyBotMessage
                                        if (empty($responseText) && !empty($bodyText)) {
                                            $responseText = $bodyText;
                                        }
                                    }
                                    
                                    if (isset($response['buttons'])) {
                                        $interactionData['buttons'] = $response['buttons'];
                                        $interactionData['body_text'] = $bodyText;
                                        // Ensure responseText is set for sendReplyBotMessage
                                        if (empty($responseText) && !empty($bodyText)) {
                                            $responseText = $bodyText;
                                        }
                                    }
                                    
                                    if (isset($response['list_data'])) {
                                        $interactionData['list_data'] = $response['list_data'];
                                        $interactionData['body_text'] = $bodyText;
                                        // Ensure responseText is set for sendReplyBotMessage
                                        if (empty($responseText) && !empty($bodyText)) {
                                            $responseText = $bodyText;
                                        }
                                    }
                                    
                                    if (isset($response['header_text'])) {
                                        $interactionData['header_text'] = $response['header_text'];
                                    }
                                    
                                    if (isset($response['footer_text'])) {
                                        $interactionData['footer_text'] = $response['footer_text'];
                                    }
                                }
                                
                                // Handle media message responses
                                if (isset($response['type']) && $response['type'] === 'media_message') {
                                    $mediaMsgData = [
                                        'type' => $response['media_type'] ?? 'image',
                                        'media_url' => $response['media_url'] ?? '',
                                        'caption' => $response['caption'] ?? '',
                                        'file_name' => $response['filename'] ?? ''
                                    ];
                                }

                                // Send the response
                                if ($responseText || $interactionData || $mediaMsgData) {
                                    try {
                                        $this->sendReplyBotMessage($contact->_uid, $responseText, $contact->vendors__id, $interactionData, [
                                            'mediaMessageData' => $mediaMsgData,
                                            'from_phone_number_id' => $options['fromPhoneNumberId'],
                                            'messageWamid' => $options['messageWamid'],
                                            'flow_id' => $flowBotReply->bot_flows__id ?? null,
                                        ]);
                                    } catch (\Exception $e) {
                                        Log::error('Exception while sending active flow response message', [
                                            'user' => $contact->wa_id,
                                            'error' => $e->getMessage(),
                                            'trace' => $e->getTraceAsString()
                                        ]);
                                    }
                                }
                            }
                        }
                        
                        // Return true to indicate message was processed
                        // Note: AI reply was already sent in FlowIntegrationService if flow node was submitted
                        return true;
                    }
                }
            }
        }
        
        $dataFetchConditions = [
            'vendors__id' => $contact->vendors__id,
        ];
        if ($testBotId) {
            $dataFetchConditions['_id'] = $testBotId;
        } else {
            if (!$messageBody) {
                return $this->engineFailedResponse([], __tr('Message body is empty'));
            }
        }

        $isBotTimingsEnabled = getVendorSettings('enable_bot_timing_restrictions', null, null, $contact->vendors__id);
        $isAiBotTimingsEnabled = getVendorSettings('enable_ai_bot_timing_restrictions', null, null, $contact->vendors__id);
        $isBotTimingsInTime = $this->isInAllowedBotTiming($contact->vendors__id);
        $selectedOtherBotsForTimingRestrictions = getVendorSettings('enable_selected_other_bot_timing_restrictions', null, null, $contact->vendors__id) ?: [];
        // search for only likewise records or welcome messages
        $allBotReplies = $this->botReplyRepository->getRelatedOrWelcomeBots($dataFetchConditions)->sortBy('priority_index');
        $isBotMatched = false;
        if (!__isEmpty($allBotReplies)) {
            // check if we already have incoming message 2 days
            $isIncomingMessageExists = $this->whatsAppMessageLogRepository->countIt([
                'vendors__id' => $contact->vendors__id,
                'contacts__id' => $contact->_id,
                'is_incoming_message' => 1,
                [
                    // send welcome message again after a day if there is no incoming response
                    'created_at', '>', now()->subHours(24)
                ],
            ]) > 1;
            foreach ($allBotReplies as $botReply) {
                // if time restrictions
                if ($isBotTimingsEnabled and !$isBotTimingsInTime and array_key_exists($botReply->trigger_type, $selectedOtherBotsForTimingRestrictions)) {
                    continue;
                }
                // get reply triggers
                $replyTriggers = strtolower($botReply->reply_trigger);
                if (!$testBotId and ($botReply->trigger_type != 'welcome') and $replyTriggers == '') {
                    continue;
                }
                // testing the bot reply
                if ($testBotId and ($testBotId != $botReply->_id)) {
                    continue;
                } elseif ($testBotId) {
                    $messageBody = $replyTriggers;
                }
                // create array of comma separated triggers
                $replyTriggers = array_filter(explode(',', $replyTriggers) ?? []);
                if ($testBotId or (empty($replyTriggers) and ($botReply->trigger_type == 'welcome'))) {
                    $replyTriggers = [
                        'dummy'
                    ];
                }
                foreach ($replyTriggers as $replyTrigger) {
                    $replyTrigger = trim($replyTrigger);
                    if (!$testBotId and ($botReply->trigger_type != 'welcome') and $replyTrigger == '') {
                        continue;
                    }
                    if ($testBotId) {
                        $messageBody = $replyTrigger;
                    }
                    // if not the test bot
                    if (!$testBotId) {
                        // if normal bot and it is inactive
                        if (!$botReply->botFlow and ($botReply->status == 2)) {
                            continue;
                        }
                        // if bot flow inactive
                        if ($botReply->botFlow?->status == 2) {
                            continue;
                        }
                    }

                    $replyText = null;
                    $isBotMatchedForThisReply = false;
                    $interactionMessageData = $botReply->__data['interaction_message'] ?? null;
                    $mediaMessageData = $botReply->__data['media_message'] ?? null;
                    if ($botReply->trigger_type == 'welcome') {
                        if (!$isIncomingMessageExists or $testBotId) {
                            $replyText = $botReply->reply_text;
                            $isBotMatchedForThisReply = true;
                        }
                    }
                    if ($botReply->trigger_type == 'start_promotional') {
                        if (Str::is($replyTrigger, $messageBody)) {
                            if ($contact->whatsapp_opt_out) {
                                $this->contactRepository->updateIt($contact, [
                                    'whatsapp_opt_out' => null
                                ]);
                            }
                            $replyText = $botReply->reply_text;
                            $isBotMatchedForThisReply = true;
                        }
                    } elseif ($botReply->trigger_type == 'stop_promotional') {
                        if (Str::is($replyTrigger, $messageBody)) {
                            if (!$contact->whatsapp_opt_out) {
                                $this->contactRepository->updateIt($contact, [
                                    'whatsapp_opt_out' => 1
                                ]);
                            }
                            $replyText = $botReply->reply_text;
                            $isBotMatchedForThisReply = true;
                        }
                    } elseif ($botReply->trigger_type == 'start_ai_bot') {
                        if (Str::is($replyTrigger, $messageBody)) {
                            if ($contact->disable_ai_bot) {
                                $this->contactRepository->updateIt($contact, [
                                    'disable_ai_bot' => 0
                                ]);
                            }
                            $replyText = $botReply->reply_text;
                            $isBotMatchedForThisReply = true;
                        }
                    } elseif ($botReply->trigger_type == 'stop_ai_bot') {
                        if (Str::is($replyTrigger, $messageBody)) {
                            if (!$contact->disable_ai_bot) {
                                $this->contactRepository->updateIt($contact, [
                                    'disable_ai_bot' => 1
                                ]);
                            }
                            $replyText = $botReply->reply_text;
                            $isBotMatchedForThisReply = true;
                        }
                    } elseif ($botReply->trigger_type == 'is') {
                        if (Str::is($replyTrigger, $messageBody)) {
                            $replyText = $botReply->reply_text;
                            $isBotMatchedForThisReply = true;
                        }
                    } elseif ($botReply->trigger_type == 'starts_with') {
                        if (Str::startsWith($messageBody, $replyTrigger)) {
                            $replyText = $botReply->reply_text;
                            $isBotMatchedForThisReply = true;
                        }
                    } elseif ($botReply->trigger_type == 'ends_with') {
                        if (Str::endsWith($messageBody, $replyTrigger)) {
                            $replyText = $botReply->reply_text;
                            $isBotMatchedForThisReply = true;
                        }
                    } elseif ($botReply->trigger_type == 'contains_word') {
                        // Prepare the pattern to search for the whole word
                        // \b represents a word boundary in regex
                        $pattern = '/\b' . preg_quote($replyTrigger, '/') . '\b/u';
                        // Use preg_match to search the haystack for the whole word
                        if (preg_match($pattern, $messageBody) > 0) {
                            $replyText = $botReply->reply_text;
                            $isBotMatchedForThisReply = true;
                        }
                    } elseif ($botReply->trigger_type == 'contains') {
                        if (Str::contains($messageBody, $replyTrigger)) {
                            $replyText = $botReply->reply_text;
                            $isBotMatchedForThisReply = true;
                        }
                    }
                    // if reply text is ready
                    if ($isBotMatchedForThisReply) {
                        $isBotMatched = true;
                        
                        // Check if this bot reply is connected to a flow
                        // If it has a flow, process the flow separately and skip regular bot reply entirely
                        if (!$testBotId && $botReply->bot_flows__id) {
                            Log::info('Bot reply has flow connected, processing flow instead of regular bot reply', [
                                'user' => $contact->wa_id,
                                'bot_reply_id' => $botReply->_uid,
                                'flow_id' => $botReply->bot_flows__id,
                                'message' => $messageBody,
                                'trigger' => $botReply->reply_trigger
                            ]);
                            
                            // Process flow using FlowIntegrationService
                            $flowIntegrationService = new FlowIntegrationService();
                            $flowResult = $flowIntegrationService->processNodeBasedFlow($contact, $messageBody, $botReply, $options);
                            
                            if ($flowResult && isset($flowResult['processed_by_new_flow']) && $flowResult['processed_by_new_flow']) {
                                Log::info('Flow processed successfully, skipping regular bot reply', [
                                    'user' => $contact->wa_id,
                                    'response_count' => count($flowResult['responses'] ?? []),
                                    'is_complete' => $flowResult['is_complete'] ?? false
                                ]);
                                
                                // Process flow responses
                                foreach ($flowResult['responses'] as $response) {
                                    $responseText = '';
                                    $interactionData = null;
                                    $mediaMsgData = null;

                                    // Set response text if it exists (check both 'text' and 'body_text' for interactive messages)
                                    if (isset($response['text'])) {
                                        $responseText = $response['text'];
                                    } elseif (isset($response['body_text'])) {
                                        $responseText = $response['body_text'];
                                    }

                                    // Handle interactive responses
                                    if (isset($response['type']) && $response['type'] === 'interactive') {
                                        // Extract node data from response if available
                                        $node = $response['node'] ?? null;
                                        
                                        // If node doesn't exist but media_url exists in response, construct node structure
                                        if (!isset($node) && isset($response['media_url']) && !empty($response['media_url'])) {
                                            $node = [
                                                'media_url' => $response['media_url'],
                                                'media_type' => $response['media_type'] ?? 'image',
                                            ];
                                            if (isset($response['caption'])) {
                                                $node['caption'] = $response['caption'];
                                            }
                                            if (isset($response['filename'])) {
                                                $node['filename'] = $response['filename'];
                                            }
                                        }
                                        
                                        Log::info("=== FLOW RESPONSE ENTRY ===", [
                                            "node_exists" => isset($node),
                                            "node_data" => $node ?? null,
                                            "response_media_url" => $response['media_url'] ?? null,
                                            "response_media_type" => $response['media_type'] ?? null,
                                            "interaction_data" => $interactionData ?? null,
                                            "flow_response_trigger" => true,
                                        ]);
                                        
                                        $interactionData = [];
                                        
                                        // Pass node through interactionData so it's available in WhatsAppApiService
                                        if (isset($node)) {
                                            $interactionData['node'] = $node;
                                        }
                                        
                                        // Also pass media data directly to interactionData for compatibility
                                        if (isset($response['media_url']) && !empty($response['media_url'])) {
                                            $interactionData['media_url'] = $response['media_url'];
                                            $interactionData['media_type'] = $response['media_type'] ?? 'image';
                                            if (isset($response['caption'])) {
                                                $interactionData['caption'] = $response['caption'];
                                            }
                                            if (isset($response['filename'])) {
                                                $interactionData['filename'] = $response['filename'];
                                            }
                                        }
                                        
                                        if (isset($response['interaction_type'])) {
                                            $interactionData['interaction_type'] = $response['interaction_type'];
                                        }
                                        
                                        // Get body_text for interactive messages (prioritize body_text from response)
                                        $bodyText = $response['body_text'] ?? $responseText;
                                        
                                        // Handle CTA URL type
                                        if (isset($response['cta_url']) && !empty($response['cta_url'])) {
                                            $interactionData['interaction_type'] = 'cta_url';
                                            $interactionData['cta_url'] = $response['cta_url'];
                                            $interactionData['body_text'] = $bodyText;
                                            // Ensure responseText is set for sendReplyBotMessage
                                            if (empty($responseText) && !empty($bodyText)) {
                                                $responseText = $bodyText;
                                            }
                                        }
                                        
                                        if (isset($response['buttons'])) {
                                            $interactionData['buttons'] = $response['buttons'];
                                            $interactionData['body_text'] = $bodyText;
                                            // Ensure responseText is set for sendReplyBotMessage
                                            if (empty($responseText) && !empty($bodyText)) {
                                                $responseText = $bodyText;
                                            }
                                        }
                                        
                                        if (isset($response['list_data'])) {
                                            $interactionData['list_data'] = $response['list_data'];
                                            $interactionData['body_text'] = $bodyText;
                                            // Ensure responseText is set for sendReplyBotMessage
                                            if (empty($responseText) && !empty($bodyText)) {
                                                $responseText = $bodyText;
                                            }
                                        }
                                        
                                        if (isset($response['header_text'])) {
                                            $interactionData['header_text'] = $response['header_text'];
                                        }
                                        
                                        if (isset($response['footer_text'])) {
                                            $interactionData['footer_text'] = $response['footer_text'];
                                        }
                                    }
                                    
                                    // Handle flow message responses
                                    if (isset($response['type']) && $response['type'] === 'flow') {
                                        $interactionData = [
                                            'interaction_type' => 'flow',
                                            'flow_id' => $response['whatsapp_flow_id'] ?? $response['flow_id'] ?? null,
                                            'flow_version' => $response['flow_version'] ?? null,
                                            'header_text' => $response['header_text'] ?? '',
                                            'body_text' => $response['body_text'] ?? $responseText,
                                            'footer_text' => $response['footer_text'] ?? '',
                                            'flow_name' => $response['flow_name'] ?? '',
                                            'flow_status' => $response['flow_status'] ?? '',
                                        ];
                                        
                                        // Ensure responseText is set for sendReplyBotMessage
                                        if (empty($responseText) && !empty($interactionData['body_text'])) {
                                            $responseText = $interactionData['body_text'];
                                        }
                                        
                                        Log::info('Flow response interaction data built', [
                                            'user' => $contact->wa_id,
                                            'flow_id' => $interactionData['flow_id'],
                                            'flow_version' => $interactionData['flow_version'],
                                            'has_header' => !empty($interactionData['header_text']),
                                            'has_body' => !empty($interactionData['body_text']),
                                            'has_footer' => !empty($interactionData['footer_text'])
                                        ]);
                                    }
                                    
                                    // Handle media message responses
                                    if (isset($response['type']) && $response['type'] === 'media_message') {
                                        $mediaMsgData = [
                                            'type' => $response['media_type'] ?? 'image',
                                            'media_url' => $response['media_url'] ?? '',
                                            'caption' => $response['caption'] ?? '',
                                            'file_name' => $response['filename'] ?? ''
                                        ];
                                    }

                                    // Send the response
                                    if ($responseText || $interactionData || $mediaMsgData) {
                                        Log::info('Sending flow response message', [
                                            'user' => $contact->wa_id,
                                            'response_type' => $response['type'] ?? 'unknown',
                                            'has_text' => !empty($responseText),
                                            'has_interaction_data' => !empty($interactionData),
                                            'has_media' => !empty($mediaMsgData),
                                            'interaction_type' => $interactionData['interaction_type'] ?? null
                                        ]);
                                        
                                        try {
                                            $sendResult = $this->sendReplyBotMessage($contact->_uid, $responseText, $contact->vendors__id, $interactionData, [
                                                'mediaMessageData' => $mediaMsgData,
                                                'from_phone_number_id' => $options['fromPhoneNumberId'],
                                                'messageWamid' => $options['messageWamid'],
                                                'flow_id' => $flowBotReply->bot_flows__id ?? null,
                                            ]);
                                            
                                            Log::info('Flow response message send result', [
                                                'user' => $contact->wa_id,
                                                'send_result_type' => gettype($sendResult),
                                                'send_success' => !empty($sendResult),
                                                'has_error' => (is_array($sendResult) && (isset($sendResult['error']) || (isset($sendResult['status']) && $sendResult['status'] === 'error'))),
                                                'result_keys' => is_array($sendResult) ? array_keys($sendResult) : []
                                            ]);
                                        } catch (\Exception $e) {
                                            Log::error('Exception while sending flow response message', [
                                                'user' => $contact->wa_id,
                                                'error' => $e->getMessage(),
                                                'trace' => $e->getTraceAsString()
                                            ]);
                                        }
                                    } else {
                                        Log::warning('Flow response not sent - all data empty', [
                                            'user' => $contact->wa_id,
                                            'response_type' => $response['type'] ?? 'unknown',
                                            'has_text' => !empty($responseText),
                                            'has_interaction_data' => !empty($interactionData),
                                            'has_media' => !empty($mediaMsgData)
                                        ]);
                                    }
                                }
                            } else {
                                Log::warning('Flow processing returned null or did not process, but still skipping regular bot reply since flow is connected', [
                                    'user' => $contact->wa_id,
                                    'bot_reply_id' => $botReply->_uid,
                                    'flow_id' => $botReply->bot_flows__id
                                ]);
                            }
                            
                            // Flow connected - always skip regular bot reply, even if flow processing failed
                            break;
                        }
                        
                        // Only process regular bot reply if no flow is connected
                        // Regular bot replies work independently without flows
                        if (!$botReply->bot_flows__id || $testBotId) {
                            if ($replyText) {
                                $replyText = $this->dynamicValuesReplacement($replyText, $contact);
                            }
                            // if interaction message
                            if ($interactionMessageData) {
                                // body text
                                $interactionMessageData['body_text'] = $replyText;
                                // header text assignments
                                if ($interactionMessageData['header_text']) {
                                    $interactionMessageData['header_text'] = $this->dynamicValuesReplacement($interactionMessageData['header_text'], $contact);
                                }
                                // footer text assignments
                                if ($interactionMessageData['footer_text']) {
                                    $interactionMessageData['footer_text'] = $this->dynamicValuesReplacement($interactionMessageData['footer_text'], $contact);
                                }
                            } elseif ($mediaMessageData) {
                                // caption text
                                $mediaMessageData['caption'] = $this->dynamicValuesReplacement($mediaMessageData['caption'], $contact);
                            }
                            $sendReplyBotMessageResponse = $this->sendReplyBotMessage($contact->_uid, $replyText, $contact->vendors__id, $interactionMessageData, [
                                'mediaMessageData' => $mediaMessageData,
                                'from_phone_number_id' => $options['fromPhoneNumberId'],
                                'messageWamid' => $options['messageWamid'],
                            ]);
                            if ($testBotId) {
                                return $sendReplyBotMessageResponse;
                            }
                            break;
                        }
                    }
                }
            }
        }
        if ($testBotId) {
            return $this->engineFailedResponse([], __tr('Bot Validation Failed due to unmatched'));
        }
        // if time restriction
        if ($isBotTimingsEnabled and !$isBotTimingsInTime and $isAiBotTimingsEnabled) {
            return false;
        }
        // initial ai bot only if manual bot didn't replied
        // ai bot is enabled
        // bot url has been set
        // contact ai bot replies is not disabled
        // has in subscription plan
        $vendorPlanDetails = vendorPlanDetails('ai_chat_bot', 0, $contact->vendors__id);
        if (!$isBotMatched and $vendorPlanDetails['is_limit_available'] and !$contact->disable_ai_bot) {
            $aiBotReplyText = null;
            // open ai
            if (!$aiBotReplyText and getVendorSettings('enable_open_ai_bot', null, null, $contact->vendors__id) and getVendorSettings('open_ai_access_key', null, null, $contact->vendors__id)) {
                try {
                    $aiBotReplyText = app()->make(OpenAiService::class)->generateAnswerFromMultipleSections($messageBody, $contact->_uid, $contact->vendors__id);
                    // check if got the reply
                    if ($aiBotReplyText) {
                        $botName = getVendorSettings('open_ai_bot_name', null, null, $contact->vendors__id);
                        $aiBotReplyText =  $botName ? ($botName . ":\n\n" . $aiBotReplyText) : $aiBotReplyText ;
                        $this->sendReplyBotMessage($contact->_uid, $aiBotReplyText, $contact->vendors__id, null, [
                            'ai_bot_reply' => true,
                            'open_ai_reply' => true,
                            'from_phone_number_id' => $options['fromPhoneNumberId'],
                            'messageWamid' => $options['messageWamid'],
                        ]);
                    }
                } catch (\Throwable $e) {
                    __logDebug($e->getMessage());
                    // send error message to the customers
                    if (getVendorSettings('open_ai_failed_message', null, null, $contact->vendors__id)) {
                        $this->sendReplyBotMessage($contact->_uid, getVendorSettings('open_ai_failed_message', null, null, $contact->vendors__id), $contact->vendors__id, null, [
                            'ai_error_triggered' => true,
                            'from_phone_number_id' => $options['fromPhoneNumberId'],
                            'messageWamid' => $options['messageWamid'],
                        ]);
                    }
                }
            }
            // flowise ai
            if (!$aiBotReplyText and getVendorSettings('enable_flowise_ai_bot', null, null, $contact->vendors__id) and getVendorSettings('flowise_url', null, null, $contact->vendors__id)) {
                try {
                    // base request start
                    $botRequest = Http::throw(function ($response, $e) {
                        __logDebug($e->getMessage());
                    });
                    // set the token if required
                    if ($bearerToken = getVendorSettings('flowise_access_token', null, null, $contact->vendors__id)) {
                        $botRequest->withToken($bearerToken);
                    }
                    $aiBotReplyText = $botRequest->post(getVendorSettings('flowise_url', null, null, $contact->vendors__id), [
                        'question' => $messageBody,
                        'overrideConfig' => [
                            'sessionId' => $contact->_uid
                        ],
                    ])->json('text');
                    // check if got the reply
                    if ($aiBotReplyText) {
                        $this->sendReplyBotMessage($contact->_uid, $aiBotReplyText, $contact->vendors__id, null, [
                            'ai_bot_reply' => true,
                            'open_flowise_ai_reply' => true,
                            'from_phone_number_id' => $options['fromPhoneNumberId'],
                            'messageWamid' => $options['messageWamid'],
                        ]);
                    }
                } catch (\Throwable $e) {
                    __logDebug($e->getMessage());
                    // send error message to the customers
                    if (getVendorSettings('flowise_failed_message', null, null, $contact->vendors__id)) {
                        $this->sendReplyBotMessage($contact->_uid, getVendorSettings('flowise_failed_message', null, null, $contact->vendors__id), $contact->vendors__id, null, [
                            'ai_error_triggered' => true,
                            'from_phone_number_id' => $options['fromPhoneNumberId'],
                            'messageWamid' => $options['messageWamid'],
                        ]);
                    }
                }
            }
        }
    }
    /**
     * Formate json data to display
     *
     * @param string $data
     * @param integer $indentLevel
     * @return string
     */
    public function jsonToListRecursive($data, $indentLevel = 0)
    {
        if (is_string($data)) {
            $decodedData = json_decode($data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return "Invalid JSON format.";
            }
        } else {
            $decodedData = $data;
        }
        // Initialize an empty string to hold the list output
        $listOutput = '';
        // Create indentation for nested levels
        $indent = str_repeat('&nbsp;', $indentLevel * 4);
        // Iterate over each key-value pair to build the list format
        foreach ($decodedData as $key => $value) {
            // If the value is an array or object, call the function recursively to list nested data
            if (is_array($value)) {
                $listOutput .= $indent . $key . ":<br>";
                $listOutput .= $this->jsonToListRecursive($value, $indentLevel + 1);
            } else {
                // For simple key-value pairs, append each item to the list output
                $listOutput .= $indent . $key . ': ' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "<br>";
            }
        }
        return $listOutput;
    }
    /**
     * Webhook for message handling
     *
     * @param Request $request
     * @param string $vendorUid
     * @return void
     */
    public function processWebhook($request, $vendorUid)
    {
        Log::info('=== WhatsApp Webhook Received ===', [
            'vendor_uid' => $vendorUid,
            'method' => $request->method(),
            'has_entry' => $request->has('entry'),
            'raw_data_keys' => array_keys($request->all())
        ]);

        $vendorId = getPublicVendorId($vendorUid);
        if (! $vendorId) {
            Log::warning('Webhook: Invalid vendor UID', ['vendor_uid' => $vendorUid]);
            return false;
        }
        // check if vendor has active plan
        $vendorPlanDetails = vendorPlanDetails(null, null, $vendorId);
        if (!$vendorPlanDetails->hasActivePlan()) {
            Log::warning('Webhook: Vendor has no active plan', ['vendor_id' => $vendorId]);
            abort(403, 'no active subscription');
        }
        $messageEntry = $request->get('entry');
        $phoneNumberId = Arr::get($messageEntry, '0.changes.0.value.metadata.phone_number_id');
        $messageStatusObject = Arr::get($messageEntry, '0.changes.0.value.statuses');
        $messageObject = Arr::get($messageEntry, '0.changes.0.value.messages');
        $fieldType = Arr::get($messageEntry, '0.changes.0.field');

        Log::info('Webhook: Parsed webhook data', [
            'vendor_id' => $vendorId,
            'phone_number_id' => $phoneNumberId,
            'field_type' => $fieldType,
            'has_status_object' => !empty($messageStatusObject),
            'has_message_object' => !empty($messageObject),
            'status_count' => is_array($messageStatusObject) ? count($messageStatusObject) : 0,
            'message_count' => is_array($messageObject) ? count($messageObject) : 0
        ]);
        
        // Handle WhatsApp Business App Coexistence webhooks
        if ($fieldType === 'smb_app_state_sync') {
            return $this->processSmbAppStateSyncWebhook($messageEntry, $vendorId, $phoneNumberId);
        } elseif ($fieldType === 'smb_message_echoes') {
            return $this->processSmbMessageEchoesWebhook($messageEntry, $vendorId, $phoneNumberId);
        } elseif ($fieldType === 'history') {
            return $this->processHistoryWebhook($messageEntry, $vendorId, $phoneNumberId);
        } elseif ($fieldType === 'account_update') {
            return $this->processAccountUpdateWebhook($messageEntry, $vendorId);
        }
        $messageStatus = null;
        $contactStatus = 'existing';
        // set the webhook messages field as configured if not already done
        if (!getVendorSettings('webhook_messages_field_verified_at', null, null, $vendorUid)
           and (Arr::get($messageEntry, '0.changes.0.field') == 'messages')) {
            $this->vendorSettingsEngine->updateProcess('whatsapp_cloud_api_setup', [
                'webhook_messages_field_verified_at' => now()
            ], $vendorId);
            // messages
            updateModelsViaVendorBroadcast($vendorUid, [
                'isWebhookMessagesFieldVerified' => true
            ]);
            // if its test message notification then get back
            if (Arr::get($messageObject, '0.text.body') == 'this is a text message') {
                return false;
            }
        }
        $mediaData = null;
        $waId = null;
        $contactUid = null;
        $campaignUid = null;
        $messageWamid = null;
        // mainly for incoming message
        $messageBody = null;
        $buttonReplyId = null;
        $isNewIncomingMessage = false;
        $repliedToWamid = null;
        if ($messageStatusObject) {
            Log::info('Webhook: Processing status update', [
                'status_object' => $messageStatusObject
            ]);

            $waId = Arr::get($messageStatusObject, '0.recipient_id'); // recipient
            $messageWamid = Arr::get($messageStatusObject, '0.id');
            $messageStatus = Arr::get($messageStatusObject, '0.status');
            $timestamp = Arr::get($messageStatusObject, '0.timestamp');

            Log::info('Webhook: Status update details', [
                'wa_id' => $waId,
                'message_wamid' => $messageWamid,
                'status' => $messageStatus,
                'timestamp' => $timestamp
            ]);

            $contact = $this->contactRepository->getVendorContactByWaId($waId, $vendorId);
            if (__isEmpty($contact)) {
                Log::warning('Webhook: Contact not found for status update', [
                    'wa_id' => $waId,
                    'vendor_id' => $vendorId,
                    'message_wamid' => $messageWamid
                ]);
                return false;
            }
            $contactUid = $contact->_uid;

            Log::info('Webhook: Updating message status', [
                'contact_uid' => $contactUid,
                'message_wamid' => $messageWamid,
                'status' => $messageStatus
            ]);

            // Update Record for sent message
            $updateResult = $this->whatsAppMessageLogRepository->updateOrCreateWhatsAppMessageFromWebhook(
                $phoneNumberId,
                $contact->_id,
                $vendorId,
                $waId,
                $messageWamid,
                $messageStatus,
                $messageEntry,
                null,
                $timestamp,
                null,
                true // do not create new record if not found
            );

            Log::info('Webhook: Status update result', [
                'contact_uid' => $contactUid,
                'message_wamid' => $messageWamid,
                'status' => $messageStatus,
                'update_result' => $updateResult ? 'success' : 'failed'
            ]);
        }
        // incoming message
        elseif ($messageObject) {
            Log::info('Webhook: Processing incoming message', [
                'message_object' => $messageObject
            ]);

            // verify the phone number id assigned with is account
            $findPhoneNumber = Arr::first((getVendorSettings('whatsapp_phone_numbers', null, null, $vendorId) ?: []), function ($value, $key) use (&$phoneNumberId) {
                return $value['id'] == $phoneNumberId;
            });
            if (getVendorSettings('current_phone_number_id', null, null, $vendorId) != $phoneNumberId) {
                if (empty($findPhoneNumber)) {
                    Log::warning('Webhook: Phone number ID not found in vendor settings', [
                        'phone_number_id' => $phoneNumberId,
                        'vendor_id' => $vendorId
                    ]);
                    return false;
                }
            }
            $waId = Arr::get($messageObject, '0.from');
            $messageWamid = Arr::get($messageObject, '0.id');
            $messageType = Arr::get($messageObject, '0.type');

            Log::info('Webhook: Incoming message details', [
                'wa_id' => $waId,
                'message_wamid' => $messageWamid,
                'message_type' => $messageType
            ]);
            // welcome trigger
            if ($messageType == 'request_welcome') {
                return false;
            }
            // deleted message
            /**
             * @link https://developers.facebook.com/docs/whatsapp/cloud-api/webhooks/payload-examples#status--message-deleted
             */
            if (Arr::get($messageObject, '0.errors.code') == 131051) {
                return false;
            }
            $messageBody = null;
            $isNewIncomingMessage = true;
            $mediaData = [];
            $isOptOutButton = false;
            $optOutMappingResult = null;
            if (in_array($messageType, [
                'text',
            ])) {
                $messageBody = Arr::get($messageObject, '0.text.body');
            } elseif (in_array($messageType, [
               'interactive',
            ])) {
                $buttonReplyId = Arr::get($messageObject, '0.interactive.button_reply.id')
                    ?? Arr::get($messageObject, '0.interactive.list_reply.id');
                $messageBody = Arr::get($messageObject, '0.interactive.button_reply.title');
                if (!$messageBody) {
                    $messageBody = Arr::get($messageObject, '0.interactive.list_reply.title');
                }
                if (!$messageBody) {
                    $messageBody = $this->jsonToListRecursive(Arr::get($messageObject, '0.interactive.nfm_reply.response_json'));
                }

                Log::info('BUTTON_MAP_INTERACTIVE_EXTRACTED', [
                    'wa_id' => $waId,
                    'button_id' => $buttonReplyId,
                    'has_button_reply' => !empty(Arr::get($messageObject, '0.interactive.button_reply')),
                    'has_list_reply' => !empty(Arr::get($messageObject, '0.interactive.list_reply')),
                    'message_type' => $messageType,
                    'message_wamid' => $messageWamid,
                ]);

                // Map button click to opt-out flag if it's a QUICK_REPLY button
                // Store this flag to check later after message is stored
                $isOptOutButton = false;
                $optOutMappingResult = null;
                if (!empty($messageBody) && !empty(Arr::get($messageObject, '0.interactive.button_reply'))) {
                    $optOutMappingResult = $this->mapButtonReplyToOptOutFlag($messageBody, $messageObject, $vendorId);
                    if ($optOutMappingResult && isset($optOutMappingResult['is_opt_out']) && $optOutMappingResult['is_opt_out'] === true) {
                        $isOptOutButton = true;
                    }
                }
            } elseif (in_array($messageType, [
                'button',
            ])) {
                $messageBody = Arr::get($messageObject, '0.button.text');
                
                // Map button click to opt-out flag if it's a QUICK_REPLY button (legacy button format)
                // Only check if there's a context.id (replied-to message), indicating it's a reply to a template
                // Store this flag to check later after message is stored
                if (!empty($messageBody) && !empty(Arr::get($messageObject, '0.context.id'))) {
                    $optOutMappingResult = $this->mapButtonReplyToOptOutFlag($messageBody, $messageObject, $vendorId);
                    if ($optOutMappingResult && isset($optOutMappingResult['is_opt_out']) && $optOutMappingResult['is_opt_out'] === true) {
                        $isOptOutButton = true;
                    }
                }
            } elseif (in_array($messageType, [
                'image',
                'video',
                'audio',
                'document',
            ])) {
                $downloadedFileInfo = $this->mediaEngine->downloadAndStoreMediaFile($this->whatsAppApiService->downloadMedia(Arr::get($messageObject, "0.$messageType.id"), $vendorId), $vendorUid, $messageType);
                $mediaData = [
                    'type' => $messageType,
                    'link' => Arr::get($downloadedFileInfo, 'path'),
                    'caption' => Arr::get($messageObject, "0.$messageType.caption"),
                    'mime_type' => Arr::get($messageObject, "0.$messageType.mime_type"),
                    'file_name' => Arr::get($downloadedFileInfo, 'fileName'),
                    'original_filename' => Arr::get($downloadedFileInfo, 'fileName'),
                ];
            } elseif ($messageType === 'order') {
                // Handle catalog order messages
                $this->processCatalogOrderMessage($messageObject[0], $waId, $vendorId);
                $messageBody = 'Order placed from catalog';
            }
            $timestamp = Arr::get($messageObject, '0.timestamp');
            $reactionMessage = Arr::get($messageObject, '0.reaction.emoji');
            if ($reactionMessage) {
                $repliedToMessage = $repliedToWamid = Arr::get($messageObject, '0.reaction.message_id');
                $messageBody = $reactionMessage;
            } else {
                // replied message
                $repliedToMessage = $repliedToWamid = Arr::get($messageObject, '0.context.id');
            }
            if ($repliedToMessage) {
                $repliedToMessage = $this->whatsAppMessageLogRepository->fetchIt([
                    'wamid' => $repliedToMessage,
                    'vendors__id' => $vendorId,
                ]);
                if (! __isEmpty($repliedToMessage)) {
                    $repliedToMessage = $repliedToMessage->_uid;
                } else {
                    $repliedToMessage = null;
                }
            }
            $isForwarded = Arr::get($messageObject, '0.context.forwarded');
            $contact = $this->contactRepository->getVendorContactByWaId($waId, $vendorId);
            if (!__isEmpty($contact)) {
                // update contact name
                if (!$contact->first_name) {
                    $profileName = Arr::get($messageEntry, '0.changes.0.value.contacts.0.profile.name');
                    $firstName = Arr::get(explode(' ', $profileName), '0');
                    $contact = $this->contactRepository->updateIt($contact, [
                        'first_name' => $firstName,
                        'last_name' => str_replace($firstName, ' ', $profileName),
                    ], $vendorId);
                    $contactStatus = 'updated';
                }
            } else {
                // check the feature limit
                $vendorPlanDetails = vendorPlanDetails('contacts', $this->contactRepository->countIt([
                    'vendors__id' => $vendorId
                ]), $vendorId);
                if (!$vendorPlanDetails['is_limit_available']) {
                    return false;
                }
                $profileName = Arr::get($messageEntry, '0.changes.0.value.contacts.0.profile.name');
                $firstName = Arr::get(explode(' ', $profileName), '0');
                $contact = $this->contactRepository->storeContact([
                    'first_name' => $firstName,
                    'last_name' => str_replace($firstName, ' ', $profileName),
                    'phone_number' => $waId,
                ], $vendorId);
                $contactStatus = 'new';
            }
            $contactUid = $contact->_uid;
            $hasLogEntryOfMessage = false;
            if ($messageWamid) {
                $hasLogEntryOfMessage = $this->whatsAppMessageLogRepository->countIt([
                    'wamid' => $messageWamid,
                    'vendors__id' => $vendorId,
                ]);
            }
            // prevent repeated message creation
            if ($hasLogEntryOfMessage) {
                Log::info('Webhook: Message already exists, skipping duplicate', [
                    'message_wamid' => $messageWamid,
                    'wa_id' => $waId
                ]);
                return false;
            }

            Log::info('Webhook: Storing incoming message', [
                'wa_id' => $waId,
                'message_wamid' => $messageWamid,
                'message_body' => $messageBody,
                'message_type' => $messageType,
                'has_media' => !empty($mediaData),
                'contact_id' => $contact->_id
            ]);

            // create Record for sent message
            $messageLog = $this->whatsAppMessageLogRepository->storeIncomingMessage(
                $phoneNumberId,
                $contact->_id,
                $vendorId,
                $waId, // sender
                $messageWamid,
                $messageEntry,
                $messageBody,
                $timestamp,
                $mediaData,
                $repliedToMessage,
                $isForwarded
            );

            Log::info('Webhook: Incoming message stored', [
                'message_log_id' => $messageLog->_id ?? null,
                'message_log_uid' => $messageLog->_uid ?? null,
                'message_wamid' => $messageWamid,
                'is_incoming' => $messageLog->is_incoming_message ?? null,
                'contact_id' => $contact->_id,
                'contact_uid' => $contactUid
            ]);

            // Ensure contactUid is set for incoming messages
            if (!$contactUid && $contact) {
                $contactUid = $contact->_uid;
            }
        }
        if ($messageWamid) {
            $messageLogEntry = $this->whatsAppMessageLogRepository->fetchIt([
                'wamid' => $messageWamid,
                'vendors__id' => $vendorId,
            ]);
            // get the campaign if required
            if (!__isEmpty($messageLogEntry) and $messageLogEntry->campaigns__id) {
                $campaign = $this->campaignRepository->fetchIt([
                    'vendors__id' => $vendorId,
                    '_id' => $messageLogEntry->campaigns__id,
                ]);
                if (!__isEmpty($campaign)) {
                    $campaignUid = $campaign->_uid;
                }
            }
        }

        // Ensure we have contactUid before broadcasting - refresh contact to get latest lastMessage
        if ($contactUid || (isset($waId) && $waId)) {
            // Always refresh contact to get the latest lastMessage after storing
            $contact = $this->contactRepository->with('lastMessage')->getVendorContactByWaId($waId, $vendorId);
            if (!__isEmpty($contact)) {
                $contactUid = $contact->_uid;
            }
        }

        if ($contactUid) {

            Log::info('Webhook: Broadcasting event', [
                'contact_uid' => $contactUid,
                'message_status' => $messageStatus ?? null,
                'is_new_incoming_message' => $isNewIncomingMessage,
                'last_message_uid' => $contact->lastMessage?->_uid,
                'has_message_body' => !empty($messageBody)
            ]);

            // Dispatch event for message
            event(new VendorChannelBroadcast($vendorUid, [
                'message_status' => $messageStatus ?? null,
                'contactUid' => $contactUid,
                'isNewIncomingMessage' => $isNewIncomingMessage,
                'campaignUid' => $campaignUid,
                'lastMessageUid' => $contact->lastMessage?->_uid,
                'assignedUserId' => $contact->assigned_users__id,
                'formatted_last_message_time' => $contact->lastMessage?->formatted_message_time,
            ]));

            Log::info('Webhook: Broadcast event dispatched', [
                'contact_uid' => $contactUid,
                'vendor_uid' => $vendorUid
            ]);

            // Process opt-out button AFTER message is stored and broadcasted
            if ($isOptOutButton && $optOutMappingResult) {
                $contact = $this->contactRepository->getVendorContactByWaId($waId, $vendorId);
                
                if (!__isEmpty($contact)) {
                    // Update contact's campaign_opt_out to 1
                    $this->contactRepository->updateIt($contact, [
                        'campaign_opt_out' => 1
                    ]);
                    
                    \Log::info('Contact opted out via Quick Reply button', [
                        'contact_uid' => $contact->_uid,
                        'wa_id' => $waId,
                        'button_text' => $messageBody,
                        'template_uid' => $optOutMappingResult['template_uid'] ?? 'N/A',
                        'button_index' => $optOutMappingResult['button_index'] ?? 'N/A',
                        'is_opt_out' => true,
                        'message_type' => $messageType ?? 'interactive'
                    ]);
                    
                    // Do NOT send any reply, trigger bot/AI logic, or continue flows
                    // Return early to stop further processing
                    return true;
                } else {
                    \Log::warning('Opt-out button clicked but contact not found', [
                        'wa_id' => $waId,
                        'button_text' => $messageBody,
                        'template_uid' => $optOutMappingResult['template_uid'] ?? 'N/A',
                        'button_index' => $optOutMappingResult['button_index'] ?? 'N/A',
                        'message_type' => $messageType ?? 'interactive'
                    ]);
                    // Still return early even if contact not found to prevent further processing
                    return true;
                }
            }

            if ($buttonReplyId) {
                Log::info('BUTTON_MAP_WEBHOOK_RECEIVED', [
                    'wa_id' => $waId,
                    'button_id' => $buttonReplyId,
                    'message_type' => $messageType,
                    'message_wamid' => $messageWamid
                ]);

                // Diagnostic: capture incoming interactive payload & cached map snapshot
                $cacheKey = $this->getButtonMapCacheKey($vendorId, $waId);
                $debugButtonMap = Cache::get($cacheKey, []);
                Log::info('BUTTON_MAP_WEBHOOK_DEBUG', [
                    'wa_id' => $waId,
                    'button_id' => $buttonReplyId,
                    'message_type' => $messageType,
                    'message_wamid' => $messageWamid,
                    'cache_key' => $cacheKey,
                    'map_keys' => array_keys($debugButtonMap),
                    'map_entry' => $debugButtonMap[strtolower(trim($buttonReplyId))] ?? null,
                    'raw_interactive' => Arr::get($messageObject, '0.interactive'),
                    'raw_context' => Arr::get($messageObject, '0.context'),
                ]);

                $handled = $this->handleButtonMapTrigger($contact, $vendorId, $buttonReplyId, [
                    'fromPhoneNumberId' => $phoneNumberId,
                    'messageWamid' => $messageWamid,
                ]);

                if ($handled) {
                    Log::info('BUTTON_MAP_WEBHOOK_HANDLED', [
                        'wa_id' => $waId,
                        'button_id' => $buttonReplyId
                    ]);
                    return true;
                }

                Log::info('BUTTON_MAP_WEBHOOK_NOT_HANDLED_FALLBACK', [
                    'wa_id' => $waId,
                    'button_id' => $buttonReplyId
                ]);

                // Secondary direct lookup & execution (defensive)
                $cacheKey = $this->getButtonMapCacheKey($vendorId, $waId);
                $buttonMap = Cache::get($cacheKey, []);
                $normalizedButtonId = strtolower(trim($buttonReplyId));
                $now = now();

                Log::info('BUTTON_MAP_DIRECT_DEBUG', [
                    'wa_id' => $waId,
                    'button_id' => $buttonReplyId,
                    'cache_key' => $cacheKey,
                    'map_keys' => array_keys($buttonMap),
                    'map_entry' => $buttonMap[$normalizedButtonId] ?? null,
                    'raw_interactive' => Arr::get($messageObject, '0.interactive'),
                    'raw_context' => Arr::get($messageObject, '0.context'),
                ]);

                Log::info('BUTTON_MAP_DIRECT_CHECK', [
                    'wa_id' => $waId,
                    'button_id' => $buttonReplyId,
                    'normalized_id' => $normalizedButtonId,
                    'has_map' => isset($buttonMap[$normalizedButtonId]),
                    'map_keys' => array_keys($buttonMap)
                ]);

                // Try direct lookup first
                $mapping = $buttonMap[$normalizedButtonId] ?? null;
                
                // If not found and it looks like a raw numeric ID, try with "button_" prefix for backward compatibility
                if (!$mapping && is_numeric($normalizedButtonId)) {
                    $prefixedId = 'button_' . $normalizedButtonId;
                    $mapping = $buttonMap[$prefixedId] ?? null;
                    
                    if ($mapping) {
                        Log::info('BUTTON_MAP_DIRECT_FOUND_WITH_PREFIX', [
                            'wa_id' => $waId,
                            'raw_id' => $normalizedButtonId,
                            'prefixed_id' => $prefixedId,
                            'has_mapping' => true,
                        ]);
                    }
                }
                
                if ($mapping) {
                    // Expiry check: 10-day validity
                    if (!empty($mapping['expires_at']) && $now->greaterThanOrEqualTo(\Carbon\Carbon::parse($mapping['expires_at']))) {
                        unset($buttonMap[$normalizedButtonId]);
                        Cache::put($cacheKey, $buttonMap, now()->addDays(10));

                        Log::info('BUTTON_MAP_EXPIRED', [
                            'wa_id' => $waId,
                            'button_id' => $buttonReplyId,
                            'expires_at' => $mapping['expires_at']
                        ]);

                        $this->processSendChatMessage([
                            'contactUid' => $contact->_uid,
                            'messageBody' => 'This option has expired. Please send the keyword again to continue.',
                        ], false, $vendorId, [
                            'bot_reply' => true,
                            'from_phone_number_id' => $phoneNumberId,
                        ]);

                        return true;
                    }

                    // If flow_id missing, treat as invalid and ask restart
                    if (empty($mapping['flow_id'])) {
                        unset($buttonMap[$normalizedButtonId]);
                        Cache::put($cacheKey, $buttonMap, now()->addDays(10));

                        Log::warning('BUTTON_MAP_INVALID_FLOW_ID', [
                            'wa_id' => $waId,
                            'button_id' => $buttonReplyId,
                            'mapping' => $mapping
                        ]);

                        $this->processSendChatMessage([
                            'contactUid' => $contact->_uid,
                            'messageBody' => 'This option has expired. Please send the keyword again to continue.',
                        ], false, $vendorId, [
                            'bot_reply' => true,
                            'from_phone_number_id' => $phoneNumberId,
                        ]);

                        return true;
                    }

                    Log::info('BUTTON_MAP_DIRECT_TRIGGER', [
                        'wa_id' => $waId,
                        'button_id' => $buttonReplyId,
                        'target_flow' => $mapping['flow_id'] ?? null,
                        'target_node' => $mapping['node_id'] ?? null
                    ]);

                    $flowIntegrationService = new FlowIntegrationService();
                    $flowResult = $flowIntegrationService->startFlowFromButtonTrigger(
                        $contact,
                        $mapping['flow_id'] ?? null,
                        $mapping['node_id'] ?? null,
                        [
                            'fromPhoneNumberId' => $phoneNumberId,
                            'messageWamid' => $messageWamid,
                        ]
                    );

                    if ($flowResult) {
                        $this->dispatchFlowResponses($flowResult, $contact, [
                            'fromPhoneNumberId' => $phoneNumberId,
                            'messageWamid' => $messageWamid,
                            'flow_id' => $mapping['flow_id'] ?? null,
                        ]);
                        return true;
                    }
                }
            }

            if ($messageBody) {
                fromPhoneNumberIdForRequest($phoneNumberId);

                // Handle order flow interactions first
                if ($this->handleOrderFlowInteractions($contact, $messageBody, $messageType, $messageObject[0] ?? [], $vendorId)) {
                    // Order flow handled, skip bot processing
                } else {
                    // process the bot if needed any
                    $this->processReplyBot($contact, $messageBody, null, [
                        'fromPhoneNumberId' => $phoneNumberId,
                        'messageWamid' => $messageWamid,
                    ]);
                }
            }
            // call webhook
            dispatchVendorWebhook($vendorId, [
                'contact' => array_merge([
                    'status' => $contactStatus,
                    'phone_number' => $contact->wa_id,
                    'uid' => $contact->_uid,
                ], $contact->only([
                    'first_name',
                    'last_name',
                    'email',
                    'language_code',
                ]), [
                    'country' => $contact->country?->name,
                ]),
                'message' => [
                    'whatsapp_business_phone_number_id' => $phoneNumberId ?? null,
                    'whatsapp_message_id' => $messageWamid ?? null,
                    'replied_to_whatsapp_message_id' => $repliedToWamid,
                    'is_new_message' => $isNewIncomingMessage,
                    'body' => $messageBody,
                    'status' => $messageStatus ?? null,
                    'media' => $mediaData,
                ],
                'whatsapp_webhook_payload' => $request->all()
            ]);
        }
        return true;
    }
    /**
     * Update the unread count via client model updates
     *
     * @return EngineResponse
     */
    public function updateUnreadCount()
    {
        $updateData = [
            'unreadMessagesCount' => 0,
            'myUnassignedUnreadMessagesCount' => 0,
            'myAssignedUnreadMessagesCount' => $this->whatsAppMessageLogRepository->getMyAssignedUnreadMessagesCount()
        ];
        if (isVendorAdmin(getVendorId()) or !hasVendorAccess('assigned_chats_only')) {
            $updateData['unreadMessagesCount'] = $this->whatsAppMessageLogRepository->getUnreadCount();
            $updateData['myUnassignedUnreadMessagesCount'] = $this->whatsAppMessageLogRepository->getMyAssignedUnreadMessagesCount(null, null, null);
        } else {
            $updateData['unreadMessagesCount'] = $updateData['myAssignedUnreadMessagesCount'];
        }
        updateClientModels($updateData);
        return $this->engineSuccessResponse([]);
    }

    /**
     * Update the unread count via client model updates
     *
     * @return EngineResponse
     */
    public function refreshHealthStatus()
    {
        $healthStatus = $this->whatsAppApiService->healthStatus();
        $whatsAppBusinessAccountId = getVendorSettings('whatsapp_business_account_id');
        if (!$whatsAppBusinessAccountId) {
            return $this->engineFailedResponse([], __tr('WhatsApp Business Account ID not found'));
        }
        $now = now();
        $healthData = [
            'whatsapp_health_status_data' => [
                $whatsAppBusinessAccountId => [
                    'whatsapp_business_account_id' => $whatsAppBusinessAccountId,
                    'health_status_updated_at' => $now,
                    'health_status_updated_at_formatted' => formatDateTime($now),
                    'health_data' => $healthStatus,
                ]
            ]
        ];
        // store information
        $this->vendorSettingsEngine->updateProcess('internals', $healthData);
        // update models
        updateClientModels([
            'healthStatusData' => $healthData['whatsapp_health_status_data'][$whatsAppBusinessAccountId]
        ]);
        return $this->engineSuccessResponse([], __tr('WhatsApp Business Health Data Refreshed'));
    }
    /**
     * Update the unread count via client model updates
     *
     * @return EngineResponse
     */
    public function processSyncPhoneNumbers()
    {
        $phoneNumbers = $this->whatsAppApiService->phoneNumbers()['data'] ?? [];
        if (empty($phoneNumbers)) {
            return $this->engineFailedResponse([], __tr('Phone numbers not available'));
        }
        $whatsAppBusinessAccountId = getVendorSettings('whatsapp_business_account_id');
        if (!$whatsAppBusinessAccountId) {
            return $this->engineFailedResponse([], __tr('WhatsApp Business Account ID not found'));
        }
        $vendorId = getVendorId();
        $phoneNumberRecord = Arr::first(($phoneNumbers ?? []), function ($value, $key) {
            return $value['id'] == getVendorSettings('current_phone_number_id');
        });
        if (!$phoneNumberRecord) {
            $phoneNumberRecord = $phoneNumbers[0];
        }
        
        // Fetch messaging limit for each phone number
        foreach ($phoneNumbers as &$phoneNumber) {
            try {
                $messagingLimitData = $this->whatsAppApiService->getMessagingLimit($phoneNumber['id']);
                $phoneNumber['messaging_limit'] = $messagingLimitData['whatsapp_business_manager_messaging_limit'] ?? null;
                
            } catch (\Exception $e) {
                // If fetching messaging limit fails, log the error but continue
                \Log::warning('Failed to fetch messaging limit for phone number', [
                    'phone_number_id' => $phoneNumber['id'],
                    'error' => $e->getMessage()
                ]);
                $phoneNumber['messaging_limit'] = null;
            }
        }
        
        if (!$this->vendorSettingsEngine->updateProcess('whatsapp_cloud_api_setup', [
            'whatsapp_phone_numbers' => $phoneNumbers,
            'current_phone_number_number' => cleanDisplayPhoneNumber($phoneNumberRecord['display_phone_number']),
            'current_phone_number_id' => $phoneNumberRecord['id'],
        ], $vendorId)) {
            return $this->engineFailedResponse(['show_message' => true], __tr('Failed to update Phone Numbers.'));
        };
        // update models
        updateClientModels([
            'whatsAppPhoneNumbers' => $phoneNumbers
        ]);
        return $this->engineSuccessResponse([], __tr('WhatsApp Business Phone Numbers Synced'));
    }

    /**
     * Format the message like whatsapp do
     *
     * @param string $text
     * @return string
     */
    protected function formatWhatsAppText($text)
    {
        return formatWhatsAppText($text);

    }

    /**
     * Build cache key for button map.
     */
    protected function getButtonMapCacheKey($vendorId, $waId)
    {
        return "button_map:vendor:{$vendorId}:wa:{$waId}";
    }

    /**
     * Persist button map for a contact so buttons remain usable without active session.
     */
    protected function persistButtonMapForContact($contactUid, $vendorId, $interactionMessageData, $flowId = null)
    {
        // Check if we have buttons or list_data sections to persist
        $hasButtons = !empty($interactionMessageData['buttons']);
        $hasListData = !empty($interactionMessageData['list_data']['sections']);
        
        if (!$hasButtons && !$hasListData) {
            return;
        }

        $contact = $this->contactRepository->getVendorContact($contactUid, $vendorId);
        if (!$contact) {
            return;
        }

        $waId = $contact->wa_id;
        $cacheKey = $this->getButtonMapCacheKey($vendorId, $waId);
        $existingMap = Cache::get($cacheKey, []);
        $now = now();
        $expiresAt = $now->copy()->addDays(10)->toDateTimeString();
        $startedAt = $now->toDateTimeString();

        // Fallback: if flowId not supplied, pull from active flow (so mapping is usable later)
        if (!$flowId) {
            $activeFlow = UserActiveFlow::getActiveFlow($waId);
            if ($activeFlow) {
                $flowId = $activeFlow->flow_id;
            }
        }

        // Process buttons (these may have "button_" prefix from BotFlowEngine)
        if ($hasButtons) {
            foreach ($interactionMessageData['buttons'] as $button) {
                $buttonId = strtolower(trim((string)($button['id'] ?? $button['title'] ?? '')));
                $nextNodeId = $button['next_node'] ?? null;

                if ($buttonId !== '' && $nextNodeId) {
                    if (empty($flowId)) {
                        Log::warning('BUTTON_MAP_FLOW_ID_MISSING_AT_PERSIST', [
                            'wa_id' => $waId,
                            'vendor_id' => $vendorId,
                            'button_id' => $buttonId,
                            'next_node' => $nextNodeId,
                        ]);
                        // Skip storing entries without a flow id, as they cannot be resumed
                        continue;
                    }

                    $existingMap[$buttonId] = [
                        'flow_id' => $flowId,
                        'node_id' => $nextNodeId,
                        'updated_at' => $startedAt,
                        'started_at' => $startedAt,
                        'expires_at' => $expiresAt,
                    ];
                }
            }
        }

        // Process list_data sections (store row IDs exactly as given, no prefix)
        if ($hasListData) {
            foreach ($interactionMessageData['list_data']['sections'] as $section) {
                foreach ($section['rows'] ?? [] as $row) {
                    // CRITICAL: Store list row IDs exactly as they are (no "button_" prefix)
                    // This matches how WhatsApp sends list_reply.id (e.g., "1", "2", "3")
                    $rowId = strtolower(trim((string)($row['id'] ?? $row['row_id'] ?? '')));
                    $nextNodeId = $row['next_node'] ?? null;

                    if ($rowId !== '' && $nextNodeId) {
                        if (empty($flowId)) {
                            Log::warning('BUTTON_MAP_FLOW_ID_MISSING_AT_PERSIST_LIST', [
                                'wa_id' => $waId,
                                'vendor_id' => $vendorId,
                                'row_id' => $rowId,
                                'next_node' => $nextNodeId,
                            ]);
                            // Skip storing entries without a flow id, as they cannot be resumed
                            continue;
                        }

                        $existingMap[$rowId] = [
                            'flow_id' => $flowId,
                            'node_id' => $nextNodeId,
                            'updated_at' => $startedAt,
                            'started_at' => $startedAt,
                            'expires_at' => $expiresAt,
                        ];
                    }
                }
            }
        }

        Cache::put($cacheKey, $existingMap, now()->addDays(10));

        Log::info('BUTTON_MAP_CREATED', [
            'wa_id' => $waId,
            'vendor_id' => $vendorId,
            'flow_id' => $flowId,
            'button_map_keys' => array_keys($existingMap),
            'has_buttons' => $hasButtons,
            'has_list_data' => $hasListData,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * Handle button-map driven triggers without requiring active flow session.
     */
    protected function handleButtonMapTrigger($contact, $vendorId, $buttonId, $options = [])
    {
        if (!$contact || !$buttonId) {
            return false;
        }

        $waId = $contact->wa_id;
        $cacheKey = $this->getButtonMapCacheKey($vendorId, $waId);
        $buttonMap = Cache::get($cacheKey, []);
        $normalizedId = strtolower(trim($buttonId));
        
        // Try direct lookup first (for list replies with raw IDs like "1", "2", "3")
        $mapping = $buttonMap[$normalizedId] ?? null;
        
        // If not found and it looks like a raw numeric ID, try with "button_" prefix for backward compatibility
        // This handles old caches that have "button_1", "button_2", etc.
        if (!$mapping && is_numeric($normalizedId)) {
            $prefixedId = 'button_' . $normalizedId;
            $mapping = $buttonMap[$prefixedId] ?? null;
            
            if ($mapping) {
                Log::info('BUTTON_MAP_LOOKUP_FOUND_WITH_PREFIX', [
                    'wa_id' => $waId,
                    'raw_id' => $normalizedId,
                    'prefixed_id' => $prefixedId,
                    'has_mapping' => true,
                ]);
            }
        }
        
        $now = now();

        Log::info('BUTTON_MAP_LOOKUP', [
            'wa_id' => $waId,
            'button_id' => $buttonId,
            'normalized_id' => $normalizedId,
            'has_mapping' => !is_null($mapping),
            'available_keys' => array_keys($buttonMap),
            'expires_at' => $mapping['expires_at'] ?? null,
        ]);

        if (!$mapping || empty($mapping['node_id'])) {
            return false;
        }

        // If flow_id missing, treat as invalid and ask user to restart
        if (empty($mapping['flow_id'])) {
            unset($buttonMap[$normalizedId]);
            Cache::put($cacheKey, $buttonMap, now()->addDays(10));

            Log::warning('BUTTON_MAP_INVALID_FLOW_ID', [
                'wa_id' => $waId,
                'button_id' => $buttonId,
                'mapping' => $mapping
            ]);

            $this->processSendChatMessage([
                'contactUid' => $contact->_uid,
                'messageBody' => 'This option has expired. Please send the keyword again to continue.',
            ], false, $vendorId, [
                'bot_reply' => true,
                'from_phone_number_id' => $options['fromPhoneNumberId'] ?? null,
            ]);

            return true;
        }

        // Expiry check: 10-day validity
        if (!empty($mapping['expires_at']) && $now->greaterThanOrEqualTo(\Carbon\Carbon::parse($mapping['expires_at']))) {
            unset($buttonMap[$normalizedId]);
            Cache::put($cacheKey, $buttonMap, now()->addDays(10));

            Log::info('BUTTON_MAP_EXPIRED', [
                'wa_id' => $waId,
                'button_id' => $buttonId,
                'expires_at' => $mapping['expires_at']
            ]);

            $this->processSendChatMessage([
                'contactUid' => $contact->_uid,
                'messageBody' => 'This option has expired. Please send the keyword again to continue.',
            ], false, $vendorId, [
                'bot_reply' => true,
                'from_phone_number_id' => $options['fromPhoneNumberId'] ?? null,
            ]);

            return true;
        }

        $flowIntegrationService = new FlowIntegrationService();
        $flowResult = $flowIntegrationService->startFlowFromButtonTrigger(
            $contact,
            $mapping['flow_id'],
            $mapping['node_id'],
            $options
        );

        if (!$flowResult) {
            Log::warning('BUTTON_MAP_FLOW_FAILED', [
                'wa_id' => $waId,
                'button_id' => $buttonId,
                'mapping' => $mapping
            ]);
            return false;
        }

        $this->dispatchFlowResponses($flowResult, $contact, $options + ['flow_id' => $mapping['flow_id']]);

        Log::info('BUTTON_MAP_FLOW_COMPLETED', [
            'wa_id' => $waId,
            'button_id' => $buttonId,
            'flow_id' => $mapping['flow_id'],
            'node_id' => $mapping['node_id'],
            'is_complete' => $flowResult['is_complete'] ?? null
        ]);

        return true;
    }

    /**
     * Dispatch flow responses (messages/interactive) for button-map triggered flows.
     */
    protected function dispatchFlowResponses($flowResult, $contact, $options = [])
    {
        if (empty($flowResult['responses'])) {
            return;
        }

        foreach ($flowResult['responses'] as $response) {
            $responseText = '';
            $interactionData = null;
            $mediaMsgData = null;

            if (isset($response['text'])) {
                $responseText = $response['text'];
            } elseif (isset($response['body_text'])) {
                $responseText = $response['body_text'];
            }

            // Handle Flow message responses explicitly
            if (isset($response['type']) && $response['type'] === 'flow') {
                $interactionData = [
                    'interaction_type' => 'flow',
                    'flow_id' => $response['whatsapp_flow_id'] ?? $response['flow_id'] ?? null,
                    'flow_version' => $response['flow_version'] ?? null,
                    'header_text' => $response['header_text'] ?? '',
                    'body_text' => $response['body_text'] ?? $responseText,
                    'footer_text' => $response['footer_text'] ?? '',
                ];
                // Ensure we have text for logging/UI
                if (empty($responseText) && !empty($interactionData['body_text'])) {
                    $responseText = $interactionData['body_text'];
                }
            }

            if (isset($response['type']) && $response['type'] === 'interactive') {
                $interactionData = [];
                if (isset($response['interaction_type'])) {
                    $interactionData['interaction_type'] = $response['interaction_type'];
                }
                if (isset($response['buttons'])) {
                    $interactionData['buttons'] = $response['buttons'];
                    $interactionData['body_text'] = $response['body_text'] ?? $responseText;
                }
                if (isset($response['list_data'])) {
                    $interactionData['list_data'] = $response['list_data'];
                    $interactionData['body_text'] = $response['body_text'] ?? $responseText;
                }
                if (isset($response['cta_url'])) {
                    $interactionData['cta_url'] = $response['cta_url'];
                    $interactionData['interaction_type'] = 'cta_url';
                    $interactionData['body_text'] = $response['body_text'] ?? $responseText;
                }
                if (isset($response['media_url'])) {
                    $interactionData['media_url'] = $response['media_url'];
                    $interactionData['media_type'] = $response['media_type'] ?? 'image';
                    if (isset($response['caption'])) {
                        $interactionData['caption'] = $response['caption'];
                    }
                    if (isset($response['filename'])) {
                        $interactionData['filename'] = $response['filename'];
                    }
                }

                // Ensure response text fallback
                if (empty($responseText) && isset($interactionData['body_text'])) {
                    $responseText = $interactionData['body_text'];
                }
            }

            if (isset($response['media_url']) && empty($interactionData)) {
                $mediaMsgData = [
                    'type' => $response['media_type'] ?? 'image',
                    'link' => $response['media_url'],
                    'caption' => $response['caption'] ?? null,
                    'file_name' => $response['filename'] ?? null,
                ];
            }

            $sendOptions = [
                'from_phone_number_id' => $options['fromPhoneNumberId'] ?? null,
                'messageWamid' => $options['messageWamid'] ?? null,
                'mediaMessageData' => $mediaMsgData,
                'flow_id' => $options['flow_id'] ?? null,
            ];

            $this->sendReplyBotMessage(
                $contact->_uid,
                $responseText,
                $contact->vendors__id,
                $interactionData,
                $sendOptions
            );
        }
    }

    public function setupWhatsAppEmbeddedSignUpProcess($request)
    {
        $processedResponse = $this->whatsAppConnectApiService->processEmbeddedSignUp($request);
        if ($processedResponse->success()) {
            $this->refreshHealthStatus();
        }
        return $processedResponse;
    }
    
    /**
     * Process SMB App State Sync webhook (contacts sync)
     *
     * @param array $messageEntry
     * @param int $vendorId
     * @param string $phoneNumberId
     * @return mixed
     */
    protected function processSmbAppStateSyncWebhook($messageEntry, $vendorId, $phoneNumberId)
    {
        Log::info('Processing SMB App State Sync webhook', [
            'vendor_id' => $vendorId,
            'phone_number_id' => $phoneNumberId
        ]);
        
        $stateSync = Arr::get($messageEntry, '0.changes.0.value.state_sync', []);
        
        foreach ($stateSync as $syncItem) {
            if (($syncItem['type'] ?? null) !== 'contact') {
                continue;
            }
            
            $contactData = $syncItem['contact'] ?? [];
            $action = $syncItem['action'] ?? 'add';
            $phoneNumber = $contactData['phone_number'] ?? null;
            
            if (!$phoneNumber) {
                continue;
            }
            
            try {
                if ($action === 'add') {
                    // Add or update contact
                    $existingContact = $this->contactRepository->fetchIt([
                        'wa_id' => $phoneNumber,
                        'vendors__id' => $vendorId
                    ]);
                    
                    if ($existingContact) {
                        // Update existing contact
                        $this->contactRepository->updateIt($existingContact, [
                            'first_name' => $contactData['first_name'] ?? $existingContact->first_name,
                            'last_name' => $contactData['last_name'] ?? $existingContact->last_name,
                            'is_on_biz_app' => true,
                            'platform_type' => 'CLOUD_API',
                            'smb_synced_at' => now(),
                        ]);
                        
                        Log::info('Updated contact from SMB sync', [
                            'contact_id' => $existingContact->_id,
                            'phone_number' => $phoneNumber
                        ]);
                    } else {
                        // Create new contact
                        $this->contactRepository->storeContact([
                            'first_name' => $contactData['first_name'] ?? '',
                            'last_name' => $contactData['last_name'] ?? '',
                            'phone_number' => $phoneNumber,
                            'wa_id' => $phoneNumber,
                            'is_on_biz_app' => true,
                            'platform_type' => 'CLOUD_API',
                            'smb_synced_at' => now(),
                        ], $vendorId);
                        
                        Log::info('Created contact from SMB sync', [
                            'phone_number' => $phoneNumber
                        ]);
                    }
                } elseif ($action === 'remove') {
                    // Mark contact as removed from SMB app
                    $existingContact = $this->contactRepository->fetchIt([
                        'wa_id' => $phoneNumber,
                        'vendors__id' => $vendorId
                    ]);
                    
                    if ($existingContact) {
                        $this->contactRepository->updateIt($existingContact, [
                            'is_on_biz_app' => false,
                            'smb_synced_at' => now(),
                        ]);
                        
                        Log::info('Removed contact from SMB sync', [
                            'contact_id' => $existingContact->_id,
                            'phone_number' => $phoneNumber
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to process SMB contact sync', [
                    'phone_number' => $phoneNumber,
                    'action' => $action,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Update sync log
        DB::table('whatsapp_smb_sync_logs')
            ->where('vendors__id', $vendorId)
            ->where('sync_type', 'contacts')
            ->where('status', 'processing')
            ->update([
                'status' => 'completed',
                'completed_at' => now(),
                'total_items' => count($stateSync),
                'updated_at' => now(),
            ]);
        
        return response()->json(['status' => 'success'], 200);
    }
    
    /**
     * Process SMB Message Echoes webhook (messages sent from WhatsApp Business App)
     *
     * @param array $messageEntry
     * @param int $vendorId
     * @param string $phoneNumberId
     * @return mixed
     */
    protected function processSmbMessageEchoesWebhook($messageEntry, $vendorId, $phoneNumberId)
    {
        Log::info('Processing SMB Message Echoes webhook', [
            'vendor_id' => $vendorId,
            'phone_number_id' => $phoneNumberId
        ]);
        
        $messageEchoes = Arr::get($messageEntry, '0.changes.0.value.message_echoes', []);
        
        foreach ($messageEchoes as $messageEcho) {
            $from = $messageEcho['from'] ?? null;
            $to = $messageEcho['to'] ?? null;
            $messageId = $messageEcho['id'] ?? null;
            $messageType = $messageEcho['type'] ?? null;
            $timestamp = $messageEcho['timestamp'] ?? null;
            
            if (!$to || !$messageId) {
                continue;
            }
            
            try {
                // Find or create contact
                $contact = $this->contactRepository->fetchIt([
                    'wa_id' => $to,
                    'vendors__id' => $vendorId
                ]);
                
                if (!$contact) {
                    $contact = $this->contactRepository->storeContact([
                        'wa_id' => $to,
                        'phone_number' => $to,
                        'first_name' => '',
                        'last_name' => '',
                    ], $vendorId);
                }
                
                // Store message log
                $messageContent = $this->extractMessageContent($messageEcho, $messageType);
                
                $this->whatsAppMessageLogRepository->storeIt([
                    'vendors__id' => $vendorId,
                    'contacts__id' => $contact->_id,
                    'wamid' => $messageId,
                    'type' => 'smb_echo',
                    'status' => 'sent',
                    '__data' => [
                        'message_type' => $messageType,
                        'message_content' => $messageContent,
                        'from' => $from,
                        'to' => $to,
                        'timestamp' => $timestamp,
                        'source' => 'whatsapp_business_app',
                    ]
                ]);
                
                Log::info('Stored SMB message echo', [
                    'message_id' => $messageId,
                    'contact_id' => $contact->_id,
                    'type' => $messageType
                ]);
                
            } catch (\Exception $e) {
                Log::error('Failed to process SMB message echo', [
                    'message_id' => $messageId,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return response()->json(['status' => 'success'], 200);
    }
    
    /**
     * Process History webhook (message history sync)
     *
     * @param array $messageEntry
     * @param int $vendorId
     * @param string $phoneNumberId
     * @return mixed
     */
    protected function processHistoryWebhook($messageEntry, $vendorId, $phoneNumberId)
    {
        Log::info('Processing History webhook', [
            'vendor_id' => $vendorId,
            'phone_number_id' => $phoneNumberId
        ]);
        
        $historyData = Arr::get($messageEntry, '0.changes.0.value.history', []);
        
        foreach ($historyData as $historyItem) {
            // Check for errors (history sharing declined)
            if (isset($historyItem['errors'])) {
                foreach ($historyItem['errors'] as $error) {
                    if (($error['code'] ?? null) == 2593109) {
                        Log::warning('History sync declined by business', [
                            'vendor_id' => $vendorId,
                            'error_message' => $error['message'] ?? 'Unknown'
                        ]);
                        
                        // Update sync log
                        DB::table('whatsapp_smb_sync_logs')
                            ->where('vendors__id', $vendorId)
                            ->where('sync_type', 'history')
                            ->where('status', 'processing')
                            ->update([
                                'status' => 'failed',
                                'error_message' => 'History sharing declined by business',
                                'completed_at' => now(),
                                'updated_at' => now(),
                            ]);
                        
                        return response()->json(['status' => 'declined'], 200);
                    }
                }
                continue;
            }
            
            $metadata = $historyItem['metadata'] ?? [];
            $phase = $metadata['phase'] ?? 0;
            $chunkOrder = $metadata['chunk_order'] ?? 0;
            $progress = $metadata['progress'] ?? 0;
            $threads = $historyItem['threads'] ?? [];
            
            $totalMessages = 0;
            
            foreach ($threads as $thread) {
                $threadId = $thread['id'] ?? null; // WhatsApp user phone number
                $messages = $thread['messages'] ?? [];
                
                if (!$threadId) {
                    continue;
                }
                
                foreach ($messages as $message) {
                    try {
                        $from = $message['from'] ?? null;
                        $to = $message['to'] ?? null;
                        $messageId = $message['id'] ?? null;
                        $messageType = $message['type'] ?? null;
                        $timestamp = $message['timestamp'] ?? null;
                        $historyContext = $message['history_context'] ?? [];
                        
                        if (!$messageId) {
                            continue;
                        }
                        
                        // Skip if message already exists
                        $existingMessage = $this->whatsAppMessageLogRepository->fetchIt([
                            'wamid' => $messageId
                        ]);
                        
                        if ($existingMessage) {
                            continue;
                        }
                        
                        // Find or create contact
                        $contact = $this->contactRepository->fetchIt([
                            'wa_id' => $threadId,
                            'vendors__id' => $vendorId
                        ]);
                        
                        if (!$contact) {
                            $contact = $this->contactRepository->storeContact([
                                'wa_id' => $threadId,
                                'phone_number' => $threadId,
                                'first_name' => '',
                                'last_name' => '',
                            ], $vendorId);
                        }
                        
                        // Determine message direction
                        $isFromBusiness = ($from != $threadId);
                        
                        // Extract message content
                        $messageContent = $this->extractMessageContent($message, $messageType);
                        
                        // Store message
                        $this->whatsAppMessageLogRepository->storeIt([
                            'vendors__id' => $vendorId,
                            'contacts__id' => $contact->_id,
                            'wamid' => $messageId,
                            'type' => $isFromBusiness ? 'sent' : 'received',
                            'status' => strtolower($historyContext['status'] ?? 'delivered'),
                            '__data' => [
                                'message_type' => $messageType,
                                'message_content' => $messageContent,
                                'from' => $from,
                                'to' => $to,
                                'timestamp' => $timestamp,
                                'source' => 'history_sync',
                                'history_status' => $historyContext['status'] ?? null,
                            ]
                        ]);
                        
                        $totalMessages++;
                        
                    } catch (\Exception $e) {
                        Log::error('Failed to process history message', [
                            'message_id' => $messageId ?? 'unknown',
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
            
            // Update sync log
            DB::table('whatsapp_smb_sync_logs')
                ->where('vendors__id', $vendorId)
                ->where('sync_type', 'history')
                ->where('status', 'processing')
                ->update([
                    'phase' => $phase,
                    'chunk_order' => $chunkOrder,
                    'progress' => $progress,
                    'total_items' => DB::raw("total_items + $totalMessages"),
                    'updated_at' => now(),
                ]);
            
            // Mark as completed if progress is 100%
            if ($progress >= 100) {
                DB::table('whatsapp_smb_sync_logs')
                    ->where('vendors__id', $vendorId)
                    ->where('sync_type', 'history')
                    ->where('status', 'processing')
                    ->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                        'updated_at' => now(),
                    ]);
                
                Log::info('History sync completed', [
                    'vendor_id' => $vendorId,
                    'total_messages' => $totalMessages
                ]);
            }
        }
        
        return response()->json(['status' => 'success'], 200);
    }
    
    /**
     * Process Account Update webhook
     *
     * @param array $messageEntry
     * @param int $vendorId
     * @return mixed
     */
    protected function processAccountUpdateWebhook($messageEntry, $vendorId)
    {
        Log::info('Processing Account Update webhook', [
            'vendor_id' => $vendorId
        ]);
        
        $event = Arr::get($messageEntry, '0.changes.0.value.event');
        $phoneNumber = Arr::get($messageEntry, '0.changes.0.value.phone_number');
        
        if ($event === 'PARTNER_REMOVED') {
            Log::warning('Business disconnected from Cloud API', [
                'vendor_id' => $vendorId,
                'phone_number' => $phoneNumber
            ]);
            
            // Update vendor settings
            $this->vendorSettingsEngine->updateProcess('whatsapp_cloud_api_setup', [
                'partner_removed_at' => now(),
                'partner_removed_phone' => $phoneNumber,
            ], $vendorId);
        }
        
        return response()->json(['status' => 'success'], 200);
    }
    
    /**
     * Extract message content from message object
     *
     * @param array $message
     * @param string $messageType
     * @return mixed
     */
    protected function extractMessageContent($message, $messageType)
    {
        if (!$messageType) {
            return null;
        }
        
        // Return the content based on message type
        return $message[$messageType] ?? null;
    }
    
    /**
     * Initiate SMB data sync after WhatsApp Business App onboarding
     *
     * @return void
     */
    public function initiateSmbDataSync()
    {
        $vendorId = getVendorId();
        $phoneNumberId = getVendorSettings('current_phone_number_id', null, null, $vendorId);
        
        if (!$phoneNumberId) {
            \Log::error('Cannot initiate SMB sync - phone number ID not found');
            return;
        }
        
        try {
            // Step 1: Sync contacts first
            $contactsSyncResult = $this->whatsAppConnectApiService->syncSmbContacts($phoneNumberId);
            
            if (isset($contactsSyncResult['request_id'])) {
                // Store sync log
                DB::table('whatsapp_smb_sync_logs')->insert([
                    '_uid' => Str::uuid(),
                    'vendors__id' => $vendorId,
                    'phone_number_id' => $phoneNumberId,
                    'sync_type' => 'contacts',
                    'status' => 'processing',
                    'request_id' => $contactsSyncResult['request_id'],
                    'started_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                \Log::info('SMB contacts sync initiated', [
                    'request_id' => $contactsSyncResult['request_id']
                ]);
            }
            
            // Step 2: Sync message history
            $historySyncResult = $this->whatsAppConnectApiService->syncSmbHistory($phoneNumberId);
            
            if (isset($historySyncResult['request_id'])) {
                // Store sync log
                DB::table('whatsapp_smb_sync_logs')->insert([
                    '_uid' => Str::uuid(),
                    'vendors__id' => $vendorId,
                    'phone_number_id' => $phoneNumberId,
                    'sync_type' => 'history',
                    'status' => 'processing',
                    'request_id' => $historySyncResult['request_id'],
                    'started_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                \Log::info('SMB history sync initiated', [
                    'request_id' => $historySyncResult['request_id']
                ]);
            }
            
            // Clear the pending flag
            $this->vendorSettingsEngine->updateProcess('whatsapp_cloud_api_setup', [
                'smb_sync_pending' => false,
            ], $vendorId);
            
        } catch (\Exception $e) {
            \Log::error('SMB sync initiation failed', [
                'error' => $e->getMessage(),
                'vendor_id' => $vendorId,
            ]);
            throw $e;
        }
    }
    
    /**
     * Process SMB contacts sync
     *
     * @return EngineResponse
     */
    public function processSyncSmbContacts()
    {
        $vendorId = getVendorId();
        $phoneNumberId = getVendorSettings('current_phone_number_id', null, null, $vendorId);
        
        if (!$phoneNumberId) {
            return $this->engineFailedResponse([], __tr('Phone number ID not found'));
        }
        
        try {
            $result = $this->whatsAppConnectApiService->syncSmbContacts($phoneNumberId);
            
            if (isset($result['request_id'])) {
                DB::table('whatsapp_smb_sync_logs')->insert([
                    '_uid' => Str::uuid(),
                    'vendors__id' => $vendorId,
                    'phone_number_id' => $phoneNumberId,
                    'sync_type' => 'contacts',
                    'status' => 'processing',
                    'request_id' => $result['request_id'],
                    'started_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                return $this->engineSuccessResponse([], __tr('Contacts sync initiated successfully. You will receive contacts via webhooks.'));
            }
            
            return $this->engineFailedResponse([], __tr('Failed to initiate contacts sync'));
        } catch (\Exception $e) {
            return $this->engineFailedResponse([], $e->getMessage());
        }
    }
    
    /**
     * Process SMB history sync
     *
     * @return EngineResponse
     */
    public function processSyncSmbHistory()
    {
        $vendorId = getVendorId();
        $phoneNumberId = getVendorSettings('current_phone_number_id', null, null, $vendorId);
        
        if (!$phoneNumberId) {
            return $this->engineFailedResponse([], __tr('Phone number ID not found'));
        }
        
        try {
            $result = $this->whatsAppConnectApiService->syncSmbHistory($phoneNumberId);
            
            if (isset($result['request_id'])) {
                DB::table('whatsapp_smb_sync_logs')->insert([
                    '_uid' => Str::uuid(),
                    'vendors__id' => $vendorId,
                    'phone_number_id' => $phoneNumberId,
                    'sync_type' => 'history',
                    'status' => 'processing',
                    'request_id' => $result['request_id'],
                    'started_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                return $this->engineSuccessResponse([], __tr('Message history sync initiated successfully. You will receive messages via webhooks.'));
            }
            
            return $this->engineFailedResponse([], __tr('Failed to initiate history sync'));
        } catch (\Exception $e) {
            return $this->engineFailedResponse([], $e->getMessage());
        }
    }
    /**
     * Disconnect Account
     *
     * @return EngineResponse
     */
    public function processDisconnectAccount($vendorId = null)
    {
        $vendorId  = $vendorId ?: getVendorId();
        if (!isWhatsAppBusinessAccountReady($vendorId)) {
            return $this->engineFailedResponse([], __tr('Account should be ready in order to disconnect.'));
        }
        // remove webhooks
        if (!getVendorSettings('embedded_setup_done_at', null, null, $vendorId)) {
            $this->processDisconnectWebhook($vendorId);
        } else {
            $this->whatsAppConnectApiService->removeExistingWebhooks(getVendorSettings('whatsapp_business_account_id', null, null, $vendorId), getVendorSettings('whatsapp_access_token', null, null, $vendorId));
        }
        if ($this->vendorSettingsEngine->deleteItemProcess([
            'embedded_setup_done_at',
            'facebook_app_id',
            'facebook_app_secret',
            'whatsapp_access_token',
            'whatsapp_business_account_id',
            'current_phone_number_number',
            'current_phone_number_id',
            'webhook_verified_at',
            'webhook_messages_field_verified_at',
            'whatsapp_phone_numbers_data',
            'whatsapp_onboarding_raw_data',
        ], $vendorId)->success()) {
            return $this->engineSuccessResponse([], __tr('Account has been disconnected successfully'));
        }
        return $this->engineFailedResponse([], __tr('Failed to disconnect account'));
    }
    /**
     * Disconnect Webhook
     *
     * @return EngineResponse
     */
    public function processDisconnectWebhook($vendorId = null)
    {
        $vendorId  = $vendorId ?: getVendorId();
        if (!getVendorSettings('facebook_app_secret', null, null, $vendorId)) {
            return $this->engineFailedResponse([], __tr('Missing App Secret'));
        }
        $processedResponse = $this->whatsAppConnectApiService->disconnectBaseWebhook(getVendorSettings('facebook_app_id', null, null, $vendorId), getVendorSettings('facebook_app_secret', null, null, $vendorId), getVendorSettings('whatsapp_business_account_id', null, null, $vendorId));
        if (isset($processedResponse['success']) and $processedResponse['success']) {
            if ($this->vendorSettingsEngine->deleteItemProcess([
                'webhook_verified_at',
                'webhook_messages_field_verified_at',
            ], $vendorId)->success()) {
                // messages
                updateClientModels([
                    'isWebhookMessagesFieldVerified' => false,
                    'isWebhookVerified' => false,
                ]);
                return $this->engineSuccessResponse([], __tr('Webhook Disconnected'));
            }
        }
        return $this->engineFailedResponse([], __tr('Nothing to disconnect'));
    }
    /**
     * Connect Webhook
     *
     * @return void
     */
    public function processConnectWebhook()
    {
        $vendorUid = getVendorUid();
        $processedResponse = $this->whatsAppConnectApiService->connectBaseWebhook(getVendorSettings('facebook_app_id'), getVendorSettings('facebook_app_secret'), $vendorUid);
        // Note: We use override webhook urls only for embedded signup
        // and as if vendor connected via Embedded signup they don't have facility to disconnect webhooks
        // thats why commented out the url
        // $processedResponse = $this->whatsAppConnectApiService->connectWebhookOverrides($vendorUid, getVendorSettings('whatsapp_business_account_id'));
        if (isset($processedResponse['success']) and $processedResponse['success']) {
            return $this->engineSuccessResponse([], __tr('Webhook Connected'));
        }
        return $this->engineFailedResponse([], __tr('Nothing to connect'));
    }

    /**
     * Requeue the failed messages
     *
     * @param BaseRequestTwo $request
     * @param sting $campaignUid
     * @return EngineResponse
     */
    public function processRequeueFailedMessages($request, $campaignUid)
    {

        $campaign = $this->campaignRepository->fetchIt([
            '_uid' => $campaignUid,
            'vendors__id' => getVendorId(),
        ]);

        if (__isEmpty($campaign)) {
            return $this->engineFailedResponse([], __tr('Campaign not found'));
        }

        // Only requeue failed messages (status 2) from this specific campaign and vendor
        if ($this->whatsAppMessageQueueRepository->updateItAll([
            'campaigns__id' => $campaign->_id, // Only this campaign
            'vendors__id' => $campaign->vendors__id, // Only this vendor
            'status' => 2, // Only failed messages
        ], [
            'status' => 1, // Set to queued status
            'retries' => 0, // Reset retry count
            'scheduled_at' => now(), // Schedule for immediate processing
        ])) {
            return $this->engineSuccessResponse([], __tr('Requeued the failed messages'));
        };

        return $this->engineFailedResponse([], __tr('Nothing to requeue'));
    }

    /**
     * Request Business Profile Information
     *
     * @param int $phoneNumberId
     * @return EngineResponse
     */
    public function requestBusinessProfile($phoneNumberId)
    {
        $businessProfile = $this->whatsAppApiService->businessProfile($phoneNumberId);
        return $this->engineSuccessResponse([
            'phoneNumberId' => $phoneNumberId,
            'businessProfile' => $businessProfile['data'][0] ?? []
        ]);
    }

    /**
     * Update WhatsApp Business Number Profile
     *
     * @param BaseRequestTwo $request
     * @return EngineResponse
     */
    public function requestUpdateBusinessProfile($request)
    {
        $dataToUpdate = array_filter($request->only([
          'address',
          'description',
          'vertical',
          'about',
          'email',
          'websites',
        ]));
        if ($request->uploaded_media_file_name) {
            $resumableUploadFileId = $this->whatsAppApiService->uploadResumableMedia($request->uploaded_media_file_name, [
                'binary' => true
            ]);
            $dataToUpdate['profile_picture_handle'] = $resumableUploadFileId;
        }
        $businessProfile = $this->whatsAppApiService->updateBusinessProfile($request->phoneNumberId, $dataToUpdate);
        if ($businessProfile['success'] ?? null) {
            return $this->engineSuccessResponse([], __tr('Business Profile Updated'));
        }
        return $this->engineFailedResponse([], __tr('Failed to update Business Profile'));
    }

    protected function isInAllowedBotTiming($vendorId)
    {
        if (getVendorSettings('enable_bot_timing_restrictions', null, null, $vendorId)) {
            $now = Carbon::now(getVendorSettings('bot_timing_timezone'));
            $botStartTime = Carbon::createFromFormat('H:i', getVendorSettings('bot_start_timing', null, null, $vendorId), getVendorSettings('bot_timing_timezone', null, null, $vendorId));
            $botEndTime = Carbon::createFromFormat('H:i', getVendorSettings('bot_end_timing', null, null, $vendorId), getVendorSettings('bot_timing_timezone', null, null, $vendorId));
            if ($botEndTime->lte($botStartTime)) {
                $botEndTime = $botEndTime->addDay();
            }
            if ($now->between($botStartTime, $botEndTime)) {
                return true;
            }
            return false;
        }
        return true;
    }

    /**
     * Process catalog order message
     *
     * @param array $orderMessage
     * @param string $customerPhone
     * @param int $vendorId
     * @return void
     */
    protected function processCatalogOrderMessage(array $orderMessage, string $customerPhone, int $vendorId): void
    {
        try {
            // Check if order processing is enabled
            if (!getVendorSettings('enable_whatsapp_orders', false, null, $vendorId)) {
                return;
            }

            $orderData = $orderMessage['order'] ?? [];

            if (empty($orderData['product_items'])) {
                Log::warning('Empty order items received', [
                    'customer_phone' => $customerPhone,
                    'vendor_id' => $vendorId,
                ]);
                return;
            }

            // Process the order using the order service
            $orderService = app(\App\Yantrana\Components\WhatsAppService\Services\WhatsAppOrderService::class);
            $result = $orderService->processCatalogOrder($orderData, $customerPhone, $vendorId);

            if (!$result['success']) {
                // Send error message to customer
                $this->whatsAppApiService->sendMessage(
                    $customerPhone,
                    "❌ Sorry, we couldn't process your order. Please try again or contact support.",
                    $vendorId
                );
            }

        } catch (\Exception $e) {
            Log::error('Failed to process catalog order message', [
                'error' => $e->getMessage(),
                'customer_phone' => $customerPhone,
                'vendor_id' => $vendorId,
                'order_message' => $orderMessage,
            ]);

            // Send error message to customer
            $this->whatsAppApiService->sendMessage(
                $customerPhone,
                "❌ Sorry, we encountered an error processing your order. Please try again later.",
                $vendorId
            );
        }
    }

    /**
     * Handle order flow interactions
     *
     * @param object $contact
     * @param string $messageBody
     * @param string $messageType
     * @param array $messageData
     * @param int $vendorId
     * @return bool
     */
    protected function handleOrderFlowInteractions($contact, string $messageBody, string $messageType, array $messageData, int $vendorId): bool
    {
        try {
            // Check if order processing is enabled
            if (!getVendorSettings('enable_whatsapp_orders', false, null, $vendorId)) {
                return false;
            }

            $userStateRepository = app(\App\Yantrana\Components\WhatsAppService\Repositories\WhatsAppUserStateRepository::class);
            $userState = $userStateRepository->fetchByPhone($contact->wa_id, $vendorId);

            if (!$userState) {
                return false;
            }

            // Handle text messages for address input
            if ($messageType === 'text' && $userState->isState('awaiting_address')) {
                $orderService = app(\App\Yantrana\Components\WhatsAppService\Services\WhatsAppOrderService::class);
                $result = $orderService->handleAddressInput($contact->wa_id, $messageBody, $vendorId);

                if (!$result['success']) {
                    $this->whatsAppApiService->sendMessage(
                        $contact->wa_id,
                        "❌ Sorry, we couldn't process your address. Please try again.",
                        $vendorId
                    );
                }

                return true;
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Failed to handle order flow interaction', [
                'error' => $e->getMessage(),
                'contact_phone' => $contact->wa_id,
                'vendor_id' => $vendorId,
                'message_type' => $messageType,
            ]);

            return false;
        }
    }

    /**
     * Check if message matches trigger based on trigger type
     *
     * @param string $messageBody
     * @param string $trigger
     * @param string $triggerType
     * @return bool
     */
    private function checkTriggerMatch($messageBody, $trigger, $triggerType)
    {
        // Handle welcome trigger type
        if ($triggerType === 'welcome') {
            // Welcome triggers should always match for first-time contacts
            // This will be handled in the main bot processing logic
            return true;
        }

        // Handle new_message trigger type
        if ($triggerType === 'new_message') {
            // New message triggers should always match when there's no active flow
            // This will be handled in the main bot processing logic
            return true;
        }

        if (!$trigger) {
            return false;
        }

        // Split trigger by commas and trim each trigger word
        $triggerWords = array_map('trim', explode(',', $trigger));
        $messageBody = strtolower(trim($messageBody));

        // Check if message matches any of the trigger words based on trigger type
        foreach ($triggerWords as $triggerWord) {
            $triggerWord = strtolower(trim($triggerWord));
            $isMatch = false;

            switch ($triggerType) {
                case 'is':
                    $isMatch = $triggerWord === $messageBody;
                    break;
                case 'starts_with':
                    $isMatch = str_starts_with($messageBody, $triggerWord);
                    break;
                case 'ends_with':
                    $isMatch = str_ends_with($messageBody, $triggerWord);
                    break;
                case 'contains_word':
                    // Match whole words only
                    $isMatch = preg_match('/\b' . preg_quote($triggerWord, '/') . '\b/i', $messageBody);
                    break;
                case 'contains':
                    $isMatch = str_contains($messageBody, $triggerWord);
                    break;
                case 'stop_promotional':
                    // Handle stop promotional logic if needed
                    $isMatch = $triggerWord === $messageBody;
                    break;
                default:
                    $isMatch = $triggerWord === $messageBody;
                    break;
            }

            if ($isMatch) {
                Log::info('Trigger match found', [
                    'messageBody' => $messageBody,
                    'triggerWord' => $triggerWord,
                    'triggerType' => $triggerType,
                    'trigger' => $trigger
                ]);
                return true;
            }
        }

        return false;
    }

    /**
     * Format phone number with country code
     * Takes the last 10 digits as phone number and the rest as country code
     *
     * @param string $phoneNumber
     * @return string|false
     */
    private function formatPhoneNumberWithCountryCode($phoneNumber)
    {
        // Remove any non-digit characters
        $cleanNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Check if the number has at least 10 digits
        if (strlen($cleanNumber) < 10) {
            return false;
        }
        
        // Check if the number has more than 15 digits (too long)
        if (strlen($cleanNumber) > 15) {
            return false;
        }
        
        // If exactly 10 digits, assume it's a local number (add default country code if needed)
        if (strlen($cleanNumber) == 10) {
            // You can modify this to add a default country code if needed
            // For now, return as is
            return $cleanNumber;
        }
        
        // If more than 10 digits, separate country code and phone number
        $phoneNumberOnly = substr($cleanNumber, -10); // Last 10 digits
        $countryCode = substr($cleanNumber, 0, -10); // Everything before the last 10 digits
        
        // Validate that country code is reasonable (1-5 digits)
        if (strlen($countryCode) < 1 || strlen($countryCode) > 5) {
            return false;
        }
        
        // Return formatted number: country code + phone number
        return $countryCode . $phoneNumberOnly;
    }

    /**
     * Send a WhatsApp Flow message for node-based flow responses.
     *
     * @param  object $contact
     * @param  array  $response
     * @return void
     */
    protected function sendFlowResponse($contact, array $response)
    {
        $flowId = $response['whatsapp_flow_id'] ?? null;

        if (!$flowId) {
            Log::warning('Flow response skipped due to missing flow id', [
                'contact' => $contact->wa_id,
                'response' => $response,
            ]);

            return;
        }

        try {
            $flowService = app(WhatsAppFlowService::class);
            $flowOptions = [
                'header_text' => $response['header_text'] ?? '',
                'body_text' => $response['body_text'] ?? ($response['text'] ?? ''),
                'footer_text' => $response['footer_text'] ?? '',
                'flow_version' => $response['flow_version'] ?? null,
            ];

            $sendResult = $flowService->sendFlowMessage(
                $flowId,
                $contact->wa_id,
                $flowOptions,
                $contact->vendors__id
            );

            if (!$sendResult['success']) {
                Log::error('Failed to send WhatsApp flow response', [
                    'contact' => $contact->wa_id,
                    'flow_id' => $flowId,
                    'error' => $sendResult['error'] ?? 'unknown',
                ]);
            } else {
                Log::info('WhatsApp flow response sent', [
                    'contact' => $contact->wa_id,
                    'flow_id' => $flowId,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Exception while sending WhatsApp flow response', [
                'contact' => $contact->wa_id,
                'flow_id' => $flowId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Extract media URL from upload response
     *
     * @param mixed $responseData
     * @return string|null
     */
    private function extractMediaUrlFromResponse($responseData)
    {
        // If it's already a string URL, validate and return it
        if (is_string($responseData)) {
            // Check if it's a valid URL or a valid path
            if (isValidUrl($responseData) || !empty($responseData)) {
                return $responseData;
            }
            return null;
        }
        
        // If it's an array, look for the 'path' field
        if (is_array($responseData)) {
            $possibleKeys = ['path', 'file_url', 'url', 'media_url', 'link'];
            foreach ($possibleKeys as $key) {
                if (isset($responseData[$key]) && is_string($responseData[$key]) && !empty($responseData[$key])) {
                    $url = $responseData[$key];
                    // Validate it's not an error message
                    if (!in_array($url, ['Over 9 levels deep, aborting normalization', 'no_value', ''])) {
                        return $url;
                    }
                }
            }
        }
        
        // If it's an object, try to get the path property
        if (is_object($responseData)) {
            $possibleKeys = ['path', 'file_url', 'url', 'media_url', 'link'];
            foreach ($possibleKeys as $key) {
                if (isset($responseData->$key) && is_string($responseData->$key) && !empty($responseData->$key)) {
                    $url = $responseData->$key;
                    // Validate it's not an error message
                    if (!in_array($url, ['Over 9 levels deep, aborting normalization', 'no_value', ''])) {
                        return $url;
                    }
                }
            }
        }
        
        return null;
    }

    /**
     * Resolve bot flow media to WhatsApp media id with link fallback
     *
     * @param string|null $mediaUrl
     * @param string|null $mediaType
     * @param string|null $mediaId
     * @return array
     */
    private function resolveBotFlowMediaPayload($mediaUrl, $mediaType = null, $mediaId = null)
    {
        $normalizedType = in_array($mediaType, ['image', 'video', 'document']) ? $mediaType : 'image';

        $result = [
            'media_id' => $mediaId,
            'media_url' => $mediaUrl,
            'media_type' => $normalizedType,
        ];

        if (!empty($mediaId) || empty($mediaUrl)) {
            return $result;
        }

        // Priority 1: Upload from URL
        if (isValidUrl($mediaUrl)) {
            $uploadResult = $this->whatsAppApiService->uploadMedia($mediaUrl);
            if (is_array($uploadResult) && isset($uploadResult['id'])) {
                $result['media_id'] = $uploadResult['id'];
                return $result;
            }

            // Fallback to original link if upload fails
            return $result;
        }

        // Priority 2: Upload from local temp file (FilePond)
        $localPath = getTempUploadedFile($mediaUrl);
        if (!empty($localPath)) {
            $uploadResult = $this->whatsAppApiService->uploadMedia($localPath);
            if (is_array($uploadResult) && isset($uploadResult['id'])) {
                $result['media_id'] = $uploadResult['id'];
                return $result;
            }
        }

        // Priority 3: Upload to server storage and use link fallback
        $storageKey = $normalizedType === 'video'
            ? 'whatsapp_video'
            : ($normalizedType === 'document' ? 'whatsapp_document' : 'whatsapp_image');

        $isProcessed = $this->mediaEngine->whatsappMediaUploadProcess(['filepond' => $mediaUrl], $storageKey);
        if (!$isProcessed->failed()) {
            $result['media_url'] = $isProcessed->data('path');
        }

        return $result;
    }

    /**
     * Map button reply to opt-out flag
     *
     * @param string $buttonText The clicked button text
     * @param array $messageObject The webhook message object
     * @param int $vendorId
     * @return array|null Returns mapping result or null if template not found
     */
    protected function mapButtonReplyToOptOutFlag($buttonText, $messageObject, $vendorId)
    {
        try {
            // Get the replied-to message WAMID from context
            $repliedToWamid = Arr::get($messageObject, '0.context.id');
            
            if (!$repliedToWamid) {
                Log::info('Opt-out button mapping: No replied-to message found', [
                    'button_text' => $buttonText,
                ]);
                return null;
            }

            // Load the replied-to message log
            $repliedToMessageLog = $this->whatsAppMessageLogRepository->fetchIt([
                'wamid' => $repliedToWamid,
                'vendors__id' => $vendorId,
            ]);

            if (__isEmpty($repliedToMessageLog)) {
                Log::info('Opt-out button mapping: Replied-to message log not found, trying fallback via contact', [
                    'button_text' => $buttonText,
                    'replied_to_wamid' => $repliedToWamid,
                ]);
                
                // Fallback: Get contact from button reply message and find last outgoing template message
                $waId = Arr::get($messageObject, '0.from');
                if ($waId) {
                    $contact = $this->contactRepository->getVendorContactByWaId($waId, $vendorId);
                    if (!__isEmpty($contact)) {
                        $lastTemplateMessage = WhatsAppMessageLogModel::where([
                            'contacts__id' => $contact->_id,
                            'vendors__id' => $vendorId,
                            'is_incoming_message' => 0, // Outgoing message
                        ])
                        ->whereNotNull('__data') // Must have __data (template messages have this)
                        ->orderBy('messaged_at', 'desc')
                        ->orderBy('created_at', 'desc')
                        ->first();
                        
                        if (!__isEmpty($lastTemplateMessage) && !empty($lastTemplateMessage->__data)) {
                            $lastMessageData = $lastTemplateMessage->__data;
                            
                            // Try template_uid first
                            $templateUid = $lastMessageData['template_uid'] ?? null;
                            if ($templateUid) {
                                $template = $this->whatsAppTemplateRepository->fetchIt([
                                    'vendors__id' => $vendorId,
                                    '_uid' => $templateUid,
                                ]);
                            }
                            
                            // Try template_proforma
                            if (__isEmpty($template) && !empty($lastMessageData['template_proforma'])) {
                                $templateProforma = $lastMessageData['template_proforma'];
                                $templateName = $templateProforma['name'] ?? null;
                                $templateLanguage = $templateProforma['language'] ?? null;
                                
                                if ($templateName && $templateLanguage) {
                                    $template = $this->whatsAppTemplateRepository->fetchIt([
                                        'vendors__id' => $vendorId,
                                        'template_name' => $templateName,
                                        'language' => $templateLanguage,
                                    ]);
                                    
                                    Log::info('Opt-out button mapping: Found template via contact fallback', [
                                        'button_text' => $buttonText,
                                        'template_name' => $templateName,
                                        'template_language' => $templateLanguage,
                                        'contact_id' => $contact->_id,
                                        'last_message_id' => $lastTemplateMessage->_id ?? null,
                                        'template_uid' => $template->_uid ?? null,
                                    ]);
                                    
                                    // Use this template for mapping
                                    if (!__isEmpty($template)) {
                                        $whatsAppTemplateEngine = app()->make(\App\Yantrana\Components\WhatsAppService\WhatsAppTemplateEngine::class);
                                        $mappingResult = $whatsAppTemplateEngine->mapButtonToOptOutFlag($buttonText, $template);
                                        
                                        if ($mappingResult && !__isEmpty($template)) {
                                            $mappingResult['template_uid'] = $template->_uid ?? null;
                                        }
                                        
                                        return $mappingResult;
                                    }
                                }
                            }
                        }
                    }
                }
                
                return null;
            }

            // Try to get template from campaign if message was sent via campaign
            $template = null;
            if ($repliedToMessageLog->campaigns__id) {
                $campaign = $this->campaignRepository->fetchIt([
                    'vendors__id' => $vendorId,
                    '_id' => $repliedToMessageLog->campaigns__id,
                ]);
                
                if (!__isEmpty($campaign) && $campaign->whatsapp_templates__id) {
                    $template = $this->whatsAppTemplateRepository->fetchIt([
                        'vendors__id' => $vendorId,
                        '_id' => $campaign->whatsapp_templates__id,
                    ]);
                }
            }

            // If template not found via campaign, try to get from message log __data
            if (__isEmpty($template) && !empty($repliedToMessageLog->__data)) {
                $messageData = $repliedToMessageLog->__data;
                $templateUid = $messageData['template_uid'] ?? null;
                
                if ($templateUid) {
                    $template = $this->whatsAppTemplateRepository->fetchIt([
                        'vendors__id' => $vendorId,
                        '_uid' => $templateUid,
                    ]);
                }
                
                // Fallback: Try to get template from template_proforma (template name + language)
                if (__isEmpty($template) && !empty($messageData['template_proforma'])) {
                    $templateProforma = $messageData['template_proforma'];
                    $templateName = $templateProforma['name'] ?? null;
                    $templateLanguage = $templateProforma['language'] ?? null;
                    
                    if ($templateName && $templateLanguage) {
                        $template = $this->whatsAppTemplateRepository->fetchIt([
                            'vendors__id' => $vendorId,
                            'template_name' => $templateName,
                            'language' => $templateLanguage,
                        ]);
                        
                        Log::info('Opt-out button mapping: Found template via template_proforma', [
                            'button_text' => $buttonText,
                            'template_name' => $templateName,
                            'template_language' => $templateLanguage,
                            'template_uid' => $template->_uid ?? null,
                        ]);
                    }
                }
            }

            // Final fallback: Find the last outgoing template message to this contact
            if (__isEmpty($template) && $repliedToMessageLog->contacts__id) {
                $lastTemplateMessage = WhatsAppMessageLogModel::where([
                    'contacts__id' => $repliedToMessageLog->contacts__id,
                    'vendors__id' => $vendorId,
                    'is_incoming_message' => 0, // Outgoing message
                ])
                ->whereNotNull('__data') // Must have __data (template messages have this)
                ->orderBy('messaged_at', 'desc')
                ->orderBy('created_at', 'desc')
                ->first();
                
                if (!__isEmpty($lastTemplateMessage) && !empty($lastTemplateMessage->__data)) {
                    $lastMessageData = $lastTemplateMessage->__data;
                    
                    // Try template_uid first
                    $templateUid = $lastMessageData['template_uid'] ?? null;
                    if ($templateUid) {
                        $template = $this->whatsAppTemplateRepository->fetchIt([
                            'vendors__id' => $vendorId,
                            '_uid' => $templateUid,
                        ]);
                    }
                    
                    // Try template_proforma
                    if (__isEmpty($template) && !empty($lastMessageData['template_proforma'])) {
                        $templateProforma = $lastMessageData['template_proforma'];
                        $templateName = $templateProforma['name'] ?? null;
                        $templateLanguage = $templateProforma['language'] ?? null;
                        
                        if ($templateName && $templateLanguage) {
                            $template = $this->whatsAppTemplateRepository->fetchIt([
                                'vendors__id' => $vendorId,
                                'template_name' => $templateName,
                                'language' => $templateLanguage,
                            ]);
                            
                            Log::info('Opt-out button mapping: Found template via last outgoing message', [
                                'button_text' => $buttonText,
                                'template_name' => $templateName,
                                'template_language' => $templateLanguage,
                                'last_message_id' => $lastTemplateMessage->_id ?? null,
                                'template_uid' => $template->_uid ?? null,
                            ]);
                        }
                    }
                }
            }

            if (__isEmpty($template)) {
                Log::info('Opt-out button mapping: Template not found for replied-to message', [
                    'button_text' => $buttonText,
                    'replied_to_wamid' => $repliedToWamid,
                    'message_log_id' => $repliedToMessageLog->_id ?? null,
                    'campaign_id' => $repliedToMessageLog->campaigns__id ?? null,
                    'contact_id' => $repliedToMessageLog->contacts__id ?? null,
                ]);
                return null;
            }

            // Use WhatsAppTemplateEngine to map button to opt-out flag
            $whatsAppTemplateEngine = app()->make(\App\Yantrana\Components\WhatsAppService\WhatsAppTemplateEngine::class);
            $mappingResult = $whatsAppTemplateEngine->mapButtonToOptOutFlag($buttonText, $template);

            // Add template_uid to the result for logging
            if ($mappingResult && !__isEmpty($template)) {
                $mappingResult['template_uid'] = $template->_uid ?? null;
            }

            return $mappingResult;

        } catch (\Exception $e) {
            Log::error('Opt-out button mapping error', [
                'button_text' => $buttonText,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Build ORDER_DETAILS action payload for button parameters
     * 
     * This builds the NEW format: action.order_details (NOT the deprecated ORDER component)
     * 
     * @param array $orderData Order data array
     * @param int|null $vendorId
     * @return array|null Order details payload structure or null if invalid
     */
    protected function buildOrderDetailsActionPayload($orderData, $vendorId = null)
    {
        try {
            \Log::info('🔍 [ORDER_DETAILS DEBUG] Step 5.3.1: buildOrderDetailsActionPayload started', [
                'order_data_keys' => array_keys($orderData),
                'vendor_id' => $vendorId
            ]);
            
            $orderId = $orderData['order_id'] ?? $orderData['id'] ?? '';
            $currency = strtoupper($orderData['currency'] ?? 'USD');
            $totalAmount = (float)($orderData['total_amount'] ?? $orderData['final_amount'] ?? $orderData['amount'] ?? 0);
            $paymentType = $orderData['payment_type'] ?? 'br';
            
            // Validate required fields
            if (empty($orderId)) {
                \Log::error('❌ [ORDER_DETAILS DEBUG] Step 5.3.2 FAILED: Missing order_id', [
                    'order_data_keys' => array_keys($orderData)
                ]);
                return null;
            }
            
            if ($totalAmount <= 0) {
                \Log::error('❌ [ORDER_DETAILS DEBUG] Step 5.3.2 FAILED: Invalid total_amount', [
                    'total_amount' => $totalAmount,
                    'order_id' => $orderId
                ]);
                return null;
            }
            
            \Log::info('🔍 [ORDER_DETAILS DEBUG] Step 5.3.2: Extracted order basic info', [
                'order_id' => $orderId,
                'currency' => $currency,
                'total_amount' => $totalAmount,
                'payment_type' => $paymentType
            ]);
            
            // Validate payment_type and currency match
            $currencyCountryMap = [
                'br' => 'BRL',
                'us' => 'USD',
                'in' => 'INR',
            ];
            $expectedCurrency = $currencyCountryMap[$paymentType] ?? null;
            
            if ($expectedCurrency && $currency !== $expectedCurrency) {
                \Log::warning('⚠️ [ORDER_DETAILS DEBUG] Step 5.3.3: Payment type and currency mismatch', [
                    'payment_type' => $paymentType,
                    'expected_currency' => $expectedCurrency,
                    'actual_currency' => $currency
                ]);
                // Don't throw here - let the calling method handle validation
            } else {
                \Log::info('✅ [ORDER_DETAILS DEBUG] Step 5.3.3: Payment type and currency match', [
                    'payment_type' => $paymentType,
                    'currency' => $currency
                ]);
            }
            
            // Get order items
            $items = [];
            $orderItems = $orderData['items'] ?? $orderData['line_items'] ?? [];
            
            \Log::info('🔍 [ORDER_DETAILS DEBUG] Step 5.3.4: Processing order items', [
                'order_items_count' => count($orderItems),
                'order_items_source' => isset($orderData['items']) ? 'items' : (isset($orderData['line_items']) ? 'line_items' : 'none')
            ]);
            
            foreach ($orderItems as $itemIndex => $item) {
                // Get item price
                $itemPrice = (float)($item['price'] ?? $item['item_price'] ?? $item['unit_price'] ?? 0);
                
                if ($itemPrice <= 0) {
                    \Log::warning('⚠️ [ORDER_DETAILS DEBUG] Step 5.3.5: Item with invalid price skipped', [
                        'item_index' => $itemIndex,
                        'item' => $item
                    ]);
                    continue;
                }
                
                // Convert to cents (value) and offset (for decimals)
                $priceInCents = (int)($itemPrice * 100);
                
                // Get item name
                $itemName = $item['product_name'] ?? $item['name'] ?? $item['display_name'] ?? 'Product';
                
                // Get retailer ID
                $retailerId = $item['product_retailer_id'] ?? $item['retailer_id'] ?? $item['product_id'] ?? '';
                
                if (empty($retailerId)) {
                    \Log::warning('⚠️ [ORDER_DETAILS DEBUG] Step 5.3.5: Item missing retailer_id', [
                        'item_index' => $itemIndex,
                        'item_name' => $itemName
                    ]);
                }
                
                $processedItem = [
                    'retailer_id' => (string)$retailerId,
                    'name' => $itemName,
                    'quantity' => (int)($item['quantity'] ?? 1),
                    'amount' => [
                        'value' => $priceInCents,
                        'offset' => 100  // 2 decimal places
                    ]
                ];
                
                $items[] = $processedItem;
                
                \Log::info('🔍 [ORDER_DETAILS DEBUG] Step 5.3.5: Processed item', [
                    'item_index' => $itemIndex,
                    'retailer_id' => $processedItem['retailer_id'],
                    'name' => $processedItem['name'],
                    'quantity' => $processedItem['quantity'],
                    'amount_value' => $processedItem['amount']['value'],
                    'amount_offset' => $processedItem['amount']['offset']
                ]);
            }
            
            // Validate that we have at least one item
            if (empty($items)) {
                \Log::error('❌ [ORDER_DETAILS DEBUG] Step 5.3.6 FAILED: No valid items found in order data', [
                    'order_data_keys' => array_keys($orderData),
                    'order_items_source' => isset($orderData['items']) ? 'items' : (isset($orderData['line_items']) ? 'line_items' : 'none'),
                    'order_items_count' => count($orderItems)
                ]);
                return null;
            }
            
            \Log::info('✅ [ORDER_DETAILS DEBUG] Step 5.3.6: Items validation passed', [
                'items_count' => count($items),
                'valid_items' => count($items)
            ]);
            
            // Convert total amount to cents
            $totalAmountInCents = (int)($totalAmount * 100);
            
            \Log::info('🔍 [ORDER_DETAILS DEBUG] Step 5.3.7: Amount conversion', [
                'total_amount_original' => $totalAmount,
                'total_amount_cents' => $totalAmountInCents
            ]);
            
            // Ensure reference_id is unique (add timestamp for reconciliation)
            $referenceId = (string)$orderId;
            if (empty($referenceId)) {
                $referenceId = 'ORD-' . time() . '-' . rand(1000, 9999);
                \Log::info('🔍 [ORDER_DETAILS DEBUG] Step 5.3.8: Generated new reference_id', [
                    'reference_id' => $referenceId
                ]);
            } else {
                // Add timestamp to ensure uniqueness for Meta reconciliation
                $referenceId = $referenceId . '-' . time();
                \Log::info('🔍 [ORDER_DETAILS DEBUG] Step 5.3.8: Added timestamp to reference_id', [
                    'original_order_id' => $orderId,
                    'reference_id' => $referenceId
                ]);
            }
            
            // Build order details payload according to Meta's NEW specification
            $orderDetailsPayload = [
                'reference_id' => $referenceId,
                'type' => $orderData['order_type'] ?? 'digital-goods',  // digital-goods, physical-goods, etc.
                'payment_type' => $paymentType,
                'currency' => $currency,
                'total_amount' => [
                    'value' => $totalAmountInCents,
                    'offset' => 100  // 2 decimal places
                ],
                'order' => [
                    'status' => $orderData['status'] ?? 'pending',
                    'items' => $items,
                    'subtotal' => [
                        'value' => $totalAmountInCents,
                        'offset' => 100
                    ]
                ]
            ];
            
            \Log::info('🔍 [ORDER_DETAILS DEBUG] Step 5.3.9: Base payload structure created', [
                'reference_id' => $orderDetailsPayload['reference_id'],
                'type' => $orderDetailsPayload['type'],
                'payment_type' => $orderDetailsPayload['payment_type'],
                'currency' => $orderDetailsPayload['currency'],
                'total_amount_value' => $orderDetailsPayload['total_amount']['value'],
                'order_status' => $orderDetailsPayload['order']['status'],
                'items_count' => count($orderDetailsPayload['order']['items'])
            ]);
            
            // Add tax if provided
            if (isset($orderData['tax']) && $orderData['tax'] > 0) {
                $taxAmount = (float)$orderData['tax'];
                $taxInCents = (int)($taxAmount * 100);
                $orderDetailsPayload['order']['tax'] = [
                    'value' => $taxInCents,
                    'offset' => 100,
                    'description' => $orderData['tax_description'] ?? 'Tax'
                ];
                \Log::info('🔍 [ORDER_DETAILS DEBUG] Step 5.3.10: Tax added to payload', [
                    'tax_value' => $taxInCents,
                    'tax_description' => $orderDetailsPayload['order']['tax']['description']
                ]);
            } else {
                \Log::info('ℹ️ [ORDER_DETAILS DEBUG] Step 5.3.10: No tax provided', []);
            }
            
            // Add discount if provided
            if (isset($orderData['discount']) && $orderData['discount'] > 0) {
                $discountAmount = (float)$orderData['discount'];
                $discountInCents = (int)($discountAmount * 100);
                $orderDetailsPayload['order']['discount'] = [
                    'value' => $discountInCents,
                    'offset' => 100,
                    'description' => $orderData['discount_description'] ?? 'Discount'
                ];
                \Log::info('🔍 [ORDER_DETAILS DEBUG] Step 5.3.11: Discount added to payload', [
                    'discount_value' => $discountInCents,
                    'discount_description' => $orderDetailsPayload['order']['discount']['description']
                ]);
            } else {
                \Log::info('ℹ️ [ORDER_DETAILS DEBUG] Step 5.3.11: No discount provided', []);
            }
            
            // Add shipping if provided
            if (isset($orderData['shipping']) && $orderData['shipping'] > 0) {
                $shippingAmount = (float)$orderData['shipping'];
                $shippingInCents = (int)($shippingAmount * 100);
                $orderDetailsPayload['order']['shipping'] = [
                    'value' => $shippingInCents,
                    'offset' => 100,
                    'description' => $orderData['shipping_description'] ?? 'Shipping'
                ];
                \Log::info('🔍 [ORDER_DETAILS DEBUG] Step 5.3.12: Shipping added to payload', [
                    'shipping_value' => $shippingInCents,
                    'shipping_description' => $orderDetailsPayload['order']['shipping']['description']
                ]);
            } else {
                \Log::info('ℹ️ [ORDER_DETAILS DEBUG] Step 5.3.12: No shipping provided', []);
            }
            
            // Add payment settings if provided (PIX, Payment Links, etc.)
            if (!empty($orderData['payment_settings'])) {
                $orderDetailsPayload['payment_settings'] = $orderData['payment_settings'];
                \Log::info('🔍 [ORDER_DETAILS DEBUG] Step 5.3.13: Payment settings added', [
                    'payment_settings_count' => count($orderData['payment_settings']),
                    'payment_settings_type' => array_column($orderData['payment_settings'], 'type') ?? 'unknown'
                ]);
            } else {
                \Log::info('ℹ️ [ORDER_DETAILS DEBUG] Step 5.3.13: No payment settings provided', []);
            }
            
            \Log::info('✅ [ORDER_DETAILS DEBUG] Step 5.3.14: buildOrderDetailsActionPayload completed successfully', [
                'payload_keys' => array_keys($orderDetailsPayload),
                'final_reference_id' => $orderDetailsPayload['reference_id'],
                'has_tax' => isset($orderDetailsPayload['order']['tax']),
                'has_discount' => isset($orderDetailsPayload['order']['discount']),
                'has_shipping' => isset($orderDetailsPayload['order']['shipping']),
                'has_payment_settings' => !empty($orderDetailsPayload['payment_settings'])
            ]);
            
            return $orderDetailsPayload;
            
        } catch (\Exception $e) {
            \Log::error('❌ [ORDER_DETAILS DEBUG] Step 5.3 FAILED: Exception in buildOrderDetailsActionPayload', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Search contacts by message text filter
     * 
     * @param string $filterText - The text to search for in message logs
     * @return EngineResponse
     */
    public function searchContactsByMessageText($filterText)
    {
        $vendorId = getVendorId();
        
        \Log::info('🔍 [Search Message Logs] Starting search', [
            'vendor_id' => $vendorId,
            'filter_text' => $filterText
        ]);
        
        // If no filter text provided, return empty array
        if (empty($filterText) || trim($filterText) === '') {
            \Log::info('⚠️ [Search Message Logs] No filter text provided, returning empty');
            return $this->engineSuccessResponse([
                'contact_ids' => [],
                'filter_text' => $filterText
            ]);
        }
        
        // Search whatsapp_message_logs table for matching messages
        $matchingContactIds = WhatsAppMessageLogModel::where('vendors__id', $vendorId)
            ->where('message', 'LIKE', '%' . $filterText . '%')
            ->whereNotNull('contacts__id')
            ->distinct()
            ->pluck('contacts__id')
            ->toArray();
        
        \Log::info('✅ [Search Message Logs] Search completed', [
            'filter_text' => $filterText,
            'matching_contact_ids' => $matchingContactIds,
            'count' => count($matchingContactIds),
            'sample_ids' => array_slice($matchingContactIds, 0, 5)
        ]);
        
        return $this->engineSuccessResponse([
            'contact_ids' => $matchingContactIds,
            'filter_text' => $filterText,
            'count' => count($matchingContactIds)
        ]);
    }

}