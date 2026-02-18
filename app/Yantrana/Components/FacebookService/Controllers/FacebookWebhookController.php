<?php

namespace App\Yantrana\Components\FacebookService\Controllers;

use App\Yantrana\Base\BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Yantrana\Components\Contact\Repositories\ContactRepository;
use App\Yantrana\Components\BotReply\Repositories\BotReplyRepository;
use App\Yantrana\Components\BotReply\Services\FlowIntegrationService;
use App\Yantrana\Components\FacebookChatService\FacebookChatServiceEngine;

class FacebookWebhookController extends BaseController
{
    /**
     * @var ContactRepository
     */
    protected $contactRepository;

    /**
     * @var BotReplyRepository
     */
    protected $botReplyRepository;

    /**
     * @var FacebookChatServiceEngine
     */
    protected $facebookChatServiceEngine;

    /**
     * Constructor
     */
    public function __construct(
        ContactRepository $contactRepository,
        BotReplyRepository $botReplyRepository,
        FacebookChatServiceEngine $facebookChatServiceEngine
    ) {
        $this->contactRepository = $contactRepository;
        $this->botReplyRepository = $botReplyRepository;
        $this->facebookChatServiceEngine = $facebookChatServiceEngine;
    }
    /**
     * Handle Facebook webhook requests
     *
     * @param Request $request
     * @param string $vendorUid
     * @return \Illuminate\Http\Response
     */
    public function handle(Request $request, $vendorUid)
    {
        // Verify webhook for GET requests (Facebook verification)
        if ($request->isMethod('GET')) {
            return $this->verifyWebhook($request, $vendorUid);
        }

        // Process webhook events for POST requests
        if ($request->isMethod('POST')) {
            return $this->processWebhook($request, $vendorUid);
        }

        return response('Method not allowed', 405);
    }

    /**
     * Verify Facebook webhook
     *
     * @param Request $request
     * @param string $vendorUid
     * @return \Illuminate\Http\Response
     */
    protected function verifyWebhook(Request $request, $vendorUid)
    {
        try {
            // Get vendor by UID
            $vendor = \App\Yantrana\Components\Vendor\Models\VendorModel::where('_uid', $vendorUid)->first();
            
            if (!$vendor) {
                Log::error("Facebook webhook verification failed: Vendor not found for UID: {$vendorUid}");
                return response('Vendor not found', 404);
            }

            // Set vendor context
            setVendorId($vendor->_id);

            $mode = $request->get('hub_mode');
            $token = $request->get('hub_verify_token');
            $challenge = $request->get('hub_challenge');

            // Get the expected verify token from vendor settings
            $expectedToken = getVendorSettings('facebook_webhook_verify_token');

            if ($mode === 'subscribe' && $token === $expectedToken) {
                Log::info("Facebook webhook verified successfully for vendor: {$vendorUid}");
                
                // Update webhook verification status
                $vendorSettingsEngine = app(\App\Yantrana\Components\Vendor\VendorSettingsEngine::class);
                $vendorSettingsEngine->updateProcess('facebook_api_setup', [
                    'facebook_webhook_verified_at' => now()
                ], $vendor->_id);

                return response($challenge, 200);
            }

            Log::error("Facebook webhook verification failed for vendor: {$vendorUid}. Mode: {$mode}, Token match: " . ($token === $expectedToken ? 'yes' : 'no'));
            return response('Forbidden', 403);

        } catch (\Exception $e) {
            Log::error("Facebook webhook verification error: " . $e->getMessage());
            return response('Internal Server Error', 500);
        }
    }

    /**
     * Process Facebook webhook events
     *
     * @param Request $request
     * @param string $vendorUid
     * @return \Illuminate\Http\Response
     */
    protected function processWebhook(Request $request, $vendorUid)
    {
        try {
            // Get vendor by UID
            $vendor = \App\Yantrana\Components\Vendor\Models\VendorModel::where('_uid', $vendorUid)->first();
            
            if (!$vendor) {
                Log::error("Facebook webhook processing failed: Vendor not found for UID: {$vendorUid}");
                return response('Vendor not found', 404);
            }

            // Set vendor context
            setVendorId($vendor->_id);

            // Verify webhook signature
            if (!$this->verifySignature($request)) {
                Log::error("Facebook webhook signature verification failed for vendor: {$vendorUid}");
                return response('Forbidden', 403);
            }

            $data = $request->json()->all();
            
            Log::info("Facebook webhook received for vendor: {$vendorUid}", ['data' => $data]);

            // Process each entry in the webhook
            foreach ($data['entry'] ?? [] as $entry) {
                $this->processEntry($entry, $vendor);
            }

            return response('OK', 200);

        } catch (\Exception $e) {
            Log::error("Facebook webhook processing error: " . $e->getMessage());
            return response('Internal Server Error', 500);
        }
    }

    /**
     * Verify webhook signature
     *
     * @param Request $request
     * @return bool
     */
    protected function verifySignature(Request $request)
    {
        $signature = $request->header('X-Hub-Signature-256');
        
        if (!$signature) {
            return false;
        }

        $appSecret = getVendorSettings('facebook_app_secret');
        
        if (!$appSecret) {
            return false;
        }

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $request->getContent(), $appSecret);
        
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Process a single webhook entry
     *
     * @param array $entry
     * @param object $vendor
     * @return void
     */
    protected function processEntry($entry, $vendor)
    {
        try {
            // Process messaging events
            foreach ($entry['messaging'] ?? [] as $messaging) {
                $this->processMessagingEvent($messaging, $vendor);
            }

        } catch (\Exception $e) {
            Log::error("Error processing Facebook webhook entry: " . $e->getMessage(), ['entry' => $entry]);
        }
    }

    /**
     * Process a messaging event
     *
     * @param array $messaging
     * @param object $vendor
     * @return void
     */
    protected function processMessagingEvent($messaging, $vendor)
    {
        try {
            $senderId = $messaging['sender']['id'] ?? null;
            $recipientId = $messaging['recipient']['id'] ?? null;
            $timestamp = $messaging['timestamp'] ?? null;

            // Skip if this is a message sent by the page (outgoing)
            $pageId = getVendorSettings('facebook_page_id');
            if ($senderId === $pageId) {
                return;
            }

            // Process incoming message
            if (isset($messaging['message'])) {
                $this->processIncomingMessage($messaging, $vendor);
            }

            // Process delivery confirmation
            if (isset($messaging['delivery'])) {
                $this->processDeliveryConfirmation($messaging, $vendor);
            }

            // Process read confirmation
            if (isset($messaging['read'])) {
                $this->processReadConfirmation($messaging, $vendor);
            }

        } catch (\Exception $e) {
            Log::error("Error processing Facebook messaging event: " . $e->getMessage(), ['messaging' => $messaging]);
        }
    }

    /**
     * Process incoming message
     *
     * @param array $messaging
     * @param object $vendor
     * @return void
     */
    protected function processIncomingMessage($messaging, $vendor)
    {
        try {
        $message = $messaging['message'];
        $senderId = $messaging['sender']['id'];
            $recipientId = $messaging['recipient']['id'] ?? null;
            $messageId = $message['mid'] ?? $message['id'] ?? null;
            $messageText = $message['text'] ?? '';
            
            Log::info("Facebook incoming message from {$senderId}: " . ($messageText ?: 'Media message'));

            // Find or create contact
            $contact = $this->findOrCreateContact($senderId, $messaging, $vendor);
            
            if (!$contact) {
                Log::error("Failed to find or create contact for Facebook sender: {$senderId}");
                return;
            }

            // Get conversation ID - for Facebook, we can use sender ID or get from entry
            // The conversation ID format for Facebook is typically the page-scoped ID
            // For now, we'll use a format that works with the API
            $conversationId = $this->getConversationId($senderId, $recipientId);

            // Process bot replies
            $this->processBotReplies($contact, $messageText, $conversationId, $vendor);
            
        } catch (\Exception $e) {
            Log::error("Error processing Facebook incoming message: " . $e->getMessage(), [
                'messaging' => $messaging,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Find or create contact for Facebook sender
     *
     * @param string $senderId
     * @param array $messagingEvent
     * @param object $vendor
     * @return object|null
     */
    protected function findOrCreateContact($senderId, $messagingEvent, $vendor)
    {
        try {
            // Try to find existing contact by wa_id (Facebook user ID) and platform
            $contact = $this->contactRepository->fetchIt([
                'wa_id' => (string) $senderId,
                'vendors__id' => $vendor->_id,
                'platform' => 'facebook'
            ]);

            if ($contact) {
                // Ensure facebook_id is stored in __data for existing contacts
                $contactData = $contact->__data ?? [];
                if (empty($contactData['facebook_id'])) {
                    $contactData['facebook_id'] = $senderId;
                    $contact->__data = $contactData;
                    $contact->save();
                    
                    Log::info('Updated existing Facebook contact with facebook_id', [
                        'contact_id' => $contact->_id,
                        'facebook_id' => $senderId
                    ]);
                }
                
                Log::info('Facebook contact found', [
                    'contact_id' => $contact->_id,
                    'sender_id' => $senderId,
                    'facebook_id' => $contactData['facebook_id'] ?? null
                ]);
                return $contact;
            }

            // Extract name from messaging event if available
            $senderName = 'Facebook User';
            if (isset($messagingEvent['sender']['name'])) {
                $senderName = $messagingEvent['sender']['name'];
            }

            // Create new contact
            $contactData = [
                'vendors__id' => $vendor->_id,
                'platform' => 'facebook',
                'wa_id' => (string) $senderId,
                'first_name' => $senderName,
                'last_name' => '',
                'full_name' => $senderName,
                'countries__id' => 1, // Default country
                'disable_ai_bot' => false,
                '__data' => [
                    'facebook_id' => $senderId,
                    'first_contact_data' => $messagingEvent,
                ]
            ];

            Log::info('Facebook contact created', [
                'sender_id' => $senderId,
                'name' => $senderName,
                'contact_data' => $contactData
            ]);

            return $this->contactRepository->storeIt($contactData);
            
        } catch (\Exception $e) {
            Log::error("Error finding or creating Facebook contact: " . $e->getMessage(), [
                'sender_id' => $senderId
            ]);
            return null;
        }
    }

    /**
     * Get conversation ID from sender and recipient
     *
     * @param string $senderId
     * @param string|null $recipientId
     * @return string
     */
    protected function getConversationId($senderId, $recipientId = null)
    {
        // For Facebook Messenger, conversation IDs are typically in format: t_<PSID>
        // But we can also try to get it from the conversations API
        // For now, we'll try to construct it or use sender ID
        // The sendConversationMessage method will try to get recipient ID from conversation
        // If that fails, we might need to modify the API service
        
        // Try to get conversation ID from Facebook API
        // For now, return sender ID - we'll need to handle this in the API service
        // or get actual conversation ID from conversations endpoint
        return $senderId;
    }

    /**
     * Process bot replies for Facebook contact
     *
     * @param object $contact
     * @param string $messageBody
     * @param string $conversationId
     * @param object $vendor
     * @return void
     */
    protected function processBotReplies($contact, $messageBody, $conversationId, $vendor)
    {
        try {
            // Check if vendor has active plan
            $vendorPlanDetails = vendorPlanDetails(null, null, $contact->vendors__id);
            if (!$vendorPlanDetails->hasActivePlan()) {
                return;
            }

            $messageBody = strtolower(trim($messageBody));
            if (!$messageBody) {
                return;
            }

            // Get bot replies for this vendor
            $allBotReplies = $this->botReplyRepository->getRelatedOrWelcomeBots([
                'vendors__id' => $contact->vendors__id,
            ])->sortBy('priority_index');

            if (__isEmpty($allBotReplies)) {
                return;
            }

            $isBotMatched = false;

            foreach ($allBotReplies as $botReply) {
                // Skip inactive bot replies (unless it's a flow)
                if (!$botReply->botFlow && $botReply->status == 2) {
                    continue;
                }

                // Skip inactive bot flows
                if ($botReply->botFlow && $botReply->botFlow->status == 2) {
                    continue;
                }

                $replyTriggers = strtolower($botReply->reply_trigger ?? '');
                
                // Handle welcome messages
                if ($botReply->trigger_type == 'welcome') {
                    // Check if we should send welcome message
                    // For now, send it if contact is new or hasn't received welcome recently
                    $this->sendBotReply($contact, $botReply, $conversationId, $messageBody);
                    $isBotMatched = true;
                    break;
                }

                // Check if message matches trigger
                if ($botReply->trigger_type != 'welcome' && $replyTriggers == '') {
                    continue;
                }

                $replyTriggersArray = array_filter(explode(',', $replyTriggers) ?? []);

                foreach ($replyTriggersArray as $replyTrigger) {
                    $replyTrigger = trim($replyTrigger);
                    if (empty($replyTrigger)) {
                        continue;
                    }

                    // Check if message matches trigger
                    if (strpos($messageBody, $replyTrigger) !== false) {
                        $this->sendBotReply($contact, $botReply, $conversationId, $messageBody);
                        $isBotMatched = true;
                        break 2; // Break out of both loops
                    }
                }
            }

        } catch (\Exception $e) {
            Log::error("Error processing Facebook bot replies: " . $e->getMessage(), [
                'contact_id' => $contact->_id ?? null,
                'message' => $messageBody,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Send bot reply message
     *
     * @param object $contact
     * @param object $botReply
     * @param string $conversationId
     * @param string $messageBody
     * @return void
     */
    protected function sendBotReply($contact, $botReply, $conversationId, $messageBody)
    {
        try {
            // Check if bot reply is connected to a flow
            if ($botReply->bot_flows__id) {
                Log::info('Bot reply has flow connected, processing flow instead of regular bot reply', [
                    'user' => $contact->wa_id,
                    'bot_reply_id' => $botReply->_uid,
                    'flow_id' => $botReply->bot_flows__id,
                    'message' => $messageBody,
                    'trigger' => $botReply->reply_trigger
                ]);

                // Process flow using FlowIntegrationService
                $flowIntegrationService = new FlowIntegrationService();
                $flowResult = $flowIntegrationService->processNodeBasedFlow($contact, $messageBody, $botReply, []);

                if ($flowResult && isset($flowResult['processed_by_new_flow']) && $flowResult['processed_by_new_flow']) {
                    Log::info('Flow processed successfully', [
                        'user' => $contact->wa_id,
                        'response_count' => count($flowResult['responses'] ?? []),
                        'is_complete' => $flowResult['is_complete'] ?? false
                    ]);

                    // Process flow responses
                    foreach ($flowResult['responses'] ?? [] as $response) {
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
                            $interactionData = [];
                            
                            if (isset($response['interaction_type'])) {
                                $interactionData['interaction_type'] = $response['interaction_type'];
                            }
                            
                            // Get body_text for interactive messages (prioritize body_text from response)
                            $bodyText = $response['body_text'] ?? $responseText;
                            
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
                                'link' => $response['media_url'] ?? '',
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
                                // CRITICAL: Extract facebook_id from contact for proper message delivery
                                $facebookId = $contact->__data['facebook_id'] ?? $contact->wa_id ?? null;
                                
                                Log::info('Sending flow response with facebook_id', [
                                    'user' => $contact->wa_id,
                                    'facebook_id' => $facebookId,
                                    'conversation_id' => $conversationId,
                                    'has_media' => !empty($mediaMsgData),
                                    'has_interaction' => !empty($interactionData)
                                ]);
                                
                                $sendResult = $this->facebookChatServiceEngine->sendReplyBotMessage(
                                    $conversationId,
                                    $responseText,
                                    $contact->vendors__id,
                                    $interactionData,
                                    [
                                        'facebook_id' => $facebookId, // CRITICAL: Pass facebook_id
                                        'mediaMessageData' => $mediaMsgData
                                    ]
                                );
                                
                                Log::info('Flow response message send result', [
                                    'user' => $contact->wa_id,
                                    'send_result_type' => gettype($sendResult),
                                    'send_success' => $sendResult->success() ?? false,
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
                    Log::warning('Flow processing returned null or did not process', [
                        'user' => $contact->wa_id,
                        'bot_reply_id' => $botReply->_uid,
                        'flow_id' => $botReply->bot_flows__id
                    ]);
                }
            } else {
                // Regular bot reply (not a flow)
                $replyText = $botReply->reply_text ?? '';
                $interactionMessageData = $botReply->__data['interaction_message'] ?? null;
                $mediaMessageData = $botReply->__data['media_message'] ?? null;

                if ($replyText || $interactionMessageData || $mediaMessageData) {
                    // Format media message data if present
                    $formattedMediaData = null;
                    if ($mediaMessageData) {
                        $formattedMediaData = [
                            'type' => $mediaMessageData['header_type'] ?? 'image',
                            'link' => $mediaMessageData['media_link'] ?? '',
                            'caption' => $mediaMessageData['caption'] ?? '',
                            'file_name' => $mediaMessageData['file_name'] ?? ''
                        ];
                    }

                    try {
                        // CRITICAL: Extract facebook_id from contact for proper message delivery
                        $facebookId = $contact->__data['facebook_id'] ?? $contact->wa_id ?? null;
                        
                        Log::info('Sending regular bot reply with facebook_id', [
                            'user' => $contact->wa_id,
                            'facebook_id' => $facebookId,
                            'conversation_id' => $conversationId,
                            'has_media' => !empty($formattedMediaData),
                            'has_interaction' => !empty($interactionMessageData)
                        ]);
                        
                        $this->facebookChatServiceEngine->sendReplyBotMessage(
                            $conversationId,
                            $replyText,
                            $contact->vendors__id,
                            $interactionMessageData,
                            [
                                'facebook_id' => $facebookId, // CRITICAL: Pass facebook_id
                                'mediaMessageData' => $formattedMediaData
                            ]
                        );
                    } catch (\Exception $e) {
                        Log::error('Exception while sending regular bot reply', [
                            'user' => $contact->wa_id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Error sending bot reply: " . $e->getMessage(), [
                'contact_id' => $contact->_id ?? null,
                'bot_reply_id' => $botReply->_uid ?? null,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Process delivery confirmation
     *
     * @param array $messaging
     * @param object $vendor
     * @return void
     */
    protected function processDeliveryConfirmation($messaging, $vendor)
    {
        $delivery = $messaging['delivery'];
        Log::info("Facebook message delivery confirmed", ['delivery' => $delivery]);
    }

    /**
     * Process read confirmation
     *
     * @param array $messaging
     * @param object $vendor
     * @return void
     */
    protected function processReadConfirmation($messaging, $vendor)
    {
        $read = $messaging['read'];
        Log::info("Facebook message read confirmed", ['read' => $read]);
    }
}
