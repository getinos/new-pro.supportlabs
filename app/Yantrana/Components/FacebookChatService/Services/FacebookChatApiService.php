<?php

/**
 * FacebookChatApiService.php - Facebook Graph API service for chat
 *
 * This file handles Facebook Graph API interactions for chat functionality
 *-----------------------------------------------------------------------------*/

namespace App\Yantrana\Components\FacebookChatService\Services;

use App\Yantrana\Base\BaseEngine;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookChatApiService extends BaseEngine
{
    /**
     * Facebook Graph API base URL
     */
    protected $baseApiUrl = 'https://graph.facebook.com/v18.0/';

    /**
     * Vendor ID for configuration
     */
    protected $vendorId;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->vendorId = getVendorId();
    }

    /**
     * Get service configuration
     *
     * @param string $key
     * @return mixed
     */
    protected function getServiceConfiguration($key)
    {
        return getVendorSettings($key, null, null, $this->vendorId);
    }

    /**
     * Test Facebook API connection
     *
     * @return array
     */
    public function testConnection()
    {
        $pageId = $this->getServiceConfiguration('facebook_page_id');
        $accessToken = $this->getServiceConfiguration('facebook_page_access_token');

        if (empty($pageId) || empty($accessToken)) {
            return [
                'success' => false,
                'message' => 'Facebook API not configured'
            ];
        }

        try {
            $url = "https://graph.facebook.com/v18.0/{$pageId}";
            $params = [
                'fields' => 'id,name,category',
                'access_token' => $accessToken
            ];

            Log::info('Facebook Chat Test Connection API Request', [
                'url' => $url,
                'params' => $params
            ]);

            $response = Http::withOptions([
                'verify' => false,
                'timeout' => 30,
            ])->get($url, $params);

            Log::info('Facebook Chat Test Connection API Response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'data' => $data,
                    'message' => 'Facebook API connection successful'
                ];
            }

            // Parse error response to provide better error messages
            $errorData = $response->json();
            $errorMessage = 'Failed to connect to Facebook API';
            
            if (isset($errorData['error'])) {
                $error = $errorData['error'];
                $errorCode = $error['code'] ?? null;
                $errorSubcode = $error['error_subcode'] ?? null;
                $errorMsg = $error['message'] ?? 'Unknown error';
                
                if ($errorCode == 190) {
                    if ($errorSubcode == 460) {
                        $errorMessage = 'Your Facebook access token has been invalidated. This usually happens when you change your Facebook password or Facebook invalidates the session for security reasons. Please reconnect your Facebook account in the settings to generate a new access token.';
                    } else {
                        $errorMessage = 'Facebook access token error: ' . $errorMsg . '. Please check your Facebook API configuration and reconnect if necessary.';
                    }
                } else {
                    $errorMessage = 'Facebook API Error: ' . $errorMsg . ' (Code: ' . $errorCode . ')';
                }
            } else {
                $errorMessage = 'Failed to connect to Facebook API. Status: ' . $response->status() . '. Response: ' . $response->body();
            }

            return [
                'success' => false,
                'message' => $errorMessage,
                'error_code' => $errorCode ?? null,
                'error_subcode' => $errorSubcode ?? null,
                'requires_reconnect' => isset($errorCode) && $errorCode == 190
            ];

        } catch (\Exception $e) {
            Log::error('Facebook Chat Test Connection Error', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error testing Facebook connection: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get Facebook conversations
     *
     * @return array
     */
    public function getConversations()
    {
        $pageId = $this->getServiceConfiguration('facebook_page_id');
        $accessToken = $this->getServiceConfiguration('facebook_page_access_token');

        if (empty($pageId) || empty($accessToken)) {
            return [
                'success' => false,
                'message' => 'Facebook API not configured'
            ];
        }

        try {
            $url = "https://graph.facebook.com/v18.0/{$pageId}/conversations";
            $params = [
                'access_token' => $accessToken,
                'fields' => 'id,updated_time,participants{name,id},can_reply,is_supported,message_count,snippet'
            ];

            Log::info('Facebook Chat Conversations API Request', [
                'url' => $url,
                'params' => $params
            ]);

            $response = Http::withOptions([
                'verify' => false,
                'timeout' => 30,
            ])->get($url, $params);

            Log::info('Facebook Chat Conversations API Response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Facebook Conversations API Response Data', [
                    'has_data' => isset($data['data']),
                    'data_count' => isset($data['data']) ? count($data['data']) : 0,
                    'sample_conversation' => isset($data['data'][0]) ? $data['data'][0] : null
                ]);

                if (isset($data['data'])) {
                    return [
                        'success' => true,
                        'data' => $data['data'],
                        'paging' => $data['paging'] ?? null,
                        'message' => 'Conversations retrieved successfully'
                    ];
                } else {
                    // Return empty array if data key doesn't exist
                    Log::warning('Facebook Conversations API response missing data key', [
                        'response' => $data
                    ]);
                    return [
                        'success' => true,
                        'data' => [],
                        'paging' => null,
                        'message' => 'No conversations found'
                    ];
                }
            }

            // Parse error response to provide better error messages
            $errorData = $response->json();
            $errorMessage = 'Failed to retrieve conversations';
            
            if (isset($errorData['error'])) {
                $error = $errorData['error'];
                $errorCode = $error['code'] ?? null;
                $errorSubcode = $error['error_subcode'] ?? null;
                $errorMsg = $error['message'] ?? 'Unknown error';
                
                Log::error('Facebook Conversations API Error', [
                    'code' => $errorCode,
                    'subcode' => $errorSubcode,
                    'message' => $errorMsg,
                    'type' => $error['type'] ?? null
                ]);
                
                // Check for OAuth token invalidation errors
                if ($errorCode == 190) {
                    if ($errorSubcode == 460) {
                        $errorMessage = 'Your Facebook access token has been invalidated. This usually happens when you change your Facebook password or Facebook invalidates the session for security reasons. Please reconnect your Facebook account in the settings to generate a new access token.';
                    } else {
                        $errorMessage = 'Facebook access token error: ' . $errorMsg . '. Please check your Facebook API configuration and reconnect if necessary.';
                    }
                } else {
                    $errorMessage = 'Facebook API Error: ' . $errorMsg . ' (Code: ' . $errorCode . ')';
                }
            } else {
                $errorMessage = 'Failed to retrieve conversations. Status: ' . $response->status() . '. Response: ' . $response->body();
            }

            return [
                'success' => false,
                'message' => $errorMessage,
                'error_code' => $errorCode ?? null,
                'error_subcode' => $errorSubcode ?? null,
                'requires_reconnect' => isset($errorCode) && $errorCode == 190
            ];

        } catch (\Exception $e) {
            Log::error('Facebook Chat Conversations Error', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error retrieving conversations: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get messages for a specific conversation
     *
     * @param string $conversationId
     * @return array
     */
    public function getConversationMessages($conversationId)
    {
        $pageId = $this->getServiceConfiguration('facebook_page_id');
        $accessToken = $this->getServiceConfiguration('facebook_page_access_token');

        if (empty($pageId) || empty($accessToken)) {
            return [
                'success' => false,
                'message' => 'Facebook API not configured'
            ];
        }

        try {
            $url = "https://graph.facebook.com/v18.0/{$conversationId}/messages";
            $params = [
                'access_token' => $accessToken,
                'fields' => 'id,message,from{name,id},created_time'
            ];

            Log::info('Facebook Chat Conversation Messages API Request', [
                'url' => $url,
                'params' => $params,
                'conversation_id' => $conversationId
            ]);

            $response = Http::withOptions([
                'verify' => false,
                'timeout' => 30,
            ])->get($url, $params);

            Log::info('Facebook Chat Conversation Messages API Response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['data'])) {
                    return [
                        'success' => true,
                        'data' => $data['data'],
                        'paging' => $data['paging'] ?? null,
                        'message' => 'Messages retrieved successfully'
                    ];
                }
            }

            // Parse error response to provide better error messages
            $errorData = $response->json();
            $errorMessage = 'Failed to retrieve messages';
            
            if (isset($errorData['error'])) {
                $error = $errorData['error'];
                $errorCode = $error['code'] ?? null;
                $errorSubcode = $error['error_subcode'] ?? null;
                $errorMsg = $error['message'] ?? 'Unknown error';
                
                if ($errorCode == 190 && $errorSubcode == 460) {
                    $errorMessage = 'Your Facebook access token has been invalidated. Please reconnect your Facebook account in the settings.';
                } else {
                    $errorMessage = 'Facebook API Error: ' . $errorMsg;
                }
            } else {
                $errorMessage = 'Failed to retrieve messages. Status: ' . $response->status() . '. Response: ' . $response->body();
            }

            return [
                'success' => false,
                'message' => $errorMessage,
                'requires_reconnect' => isset($errorCode) && $errorCode == 190
            ];

        } catch (\Exception $e) {
            Log::error('Facebook Chat Conversation Messages Error', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversationId
            ]);

            return [
                'success' => false,
                'message' => 'Error retrieving messages: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send message to Facebook conversation using Messenger API
     *
     * @param string $conversationId
     * @param string $message
     * @param array $options Optional parameters for interactive messages
     * @return array
     */
    public function sendConversationMessage($conversationId, $message, $options = [])
    {
        $accessToken = $this->getServiceConfiguration('facebook_page_access_token');

        if (empty($accessToken)) {
            return [
                'success' => false,
                'message' => 'Facebook API not configured'
            ];
        }

        try {
            // FIXED: Get recipient ID - try multiple methods
            $recipientId = null;
            
            // Method 1: Check if recipient_id explicitly provided
            if (isset($options['recipient_id'])) {
                $recipientId = $options['recipient_id'];
                Log::info('Using recipient_id from options', ['recipient_id' => $recipientId]);
            }
            // Method 2: Check if facebook_id provided
            elseif (isset($options['facebook_id'])) {
                $recipientId = $options['facebook_id'];
                Log::info('Using facebook_id from options', ['facebook_id' => $recipientId]);
            }
            // Method 3: Try to extract from conversation
            else {
                $recipientId = $this->getRecipientIdFromConversation($conversationId);
                if ($recipientId) {
                    Log::info('Extracted recipient_id from conversation', ['recipient_id' => $recipientId]);
                }
            }
            
            // Method 4: Fallback - try conversationId directly if it's numeric (PSID format)
            if (!$recipientId) {
                if (is_numeric($conversationId) || preg_match('/^\d+$/', $conversationId)) {
                    $recipientId = $conversationId;
                    Log::info('Using conversationId as recipient ID (PSID)', ['conversation_id' => $conversationId]);
                } else {
                    Log::error('Could not determine recipient ID', [
                        'conversation_id' => $conversationId,
                        'options' => array_keys($options)
                    ]);
                    
                    return [
                        'success' => false,
                        'message' => 'Could not determine recipient ID. Please ensure the conversation includes facebook_id or pass recipient_id in options.',
                        'debug_info' => [
                            'conversation_id' => $conversationId,
                            'has_recipient_id' => isset($options['recipient_id']),
                            'has_facebook_id' => isset($options['facebook_id'])
                        ]
                    ];
                }
            }

            $url = "https://graph.facebook.com/v18.0/me/messages";
            
            // Extract message type data
            $mediaMessageData = $options['mediaMessageData'] ?? $options['media_message_data'] ?? null;
            $carouselData = $options['carousel'] ?? $options['carousel_data'] ?? null;
            $interactionData = $options['interaction_message'] ?? null;
            
            Log::info('Facebook Chat Message Build', [
                'recipient_id' => $recipientId,
                'has_media' => !empty($mediaMessageData),
                'has_carousel' => !empty($carouselData),
                'has_interaction' => !empty($interactionData),
                'message_preview' => substr($message, 0, 50)
            ]);
            
            $data = null;
            $sendCaptionSeparately = false;
            $captionText = null;
            
            // Priority 1: Handle media messages
            if ($mediaMessageData) {
                $mediaType = $mediaMessageData['header_type'] ?? $mediaMessageData['media_type'] ?? $mediaMessageData['type'] ?? 'image';
                $mediaUrl = $mediaMessageData['media_link'] ?? $mediaMessageData['link'] ?? $mediaMessageData['media_url'] ?? $mediaMessageData['url'] ?? null;
                
                Log::info('Processing media message', [
                    'media_type' => $mediaType,
                    'media_url' => $mediaUrl
                ]);
                
                if ($mediaUrl) {
                    // Map to Facebook attachment types
                    $attachmentType = 'image';
                    $mediaTypeLower = strtolower($mediaType);
                    
                    if (in_array($mediaTypeLower, ['video'])) {
                        $attachmentType = 'video';
                    } elseif (in_array($mediaTypeLower, ['audio'])) {
                        $attachmentType = 'audio';
                    } elseif (in_array($mediaTypeLower, ['document', 'file'])) {
                        $attachmentType = 'file';
                    }
                    
                    $data = [
                        'recipient' => ['id' => $recipientId],
                        'message' => [
                            'attachment' => [
                                'type' => $attachmentType,
                                'payload' => [
                                    'url' => $mediaUrl,
                                    'is_reusable' => true
                                ]
                            ]
                        ]
                    ];
                    
                    // Handle caption - send as separate message for better compatibility
                    $caption = $mediaMessageData['caption'] ?? '';
                    if (!empty($caption)) {
                        $sendCaptionSeparately = true;
                        $captionText = $caption;
                    } elseif (!empty($message) && $message !== $mediaUrl) {
                        $sendCaptionSeparately = true;
                        $captionText = $message;
                    }
                    
                    Log::info('Media message prepared', [
                        'type' => $attachmentType,
                        'url' => $mediaUrl,
                        'has_caption' => $sendCaptionSeparately
                    ]);
                }
            }
            // Priority 2: Handle carousel
            elseif ($carouselData && is_array($carouselData)) {
                $cards = $carouselData['cards'] ?? $carouselData ?? [];
                
                if (!empty($cards)) {
                    $elements = [];
                    
                    foreach ($cards as $card) {
                        $element = [];
                        
                        if (!empty($card['title'])) {
                            $element['title'] = substr($card['title'], 0, 80); // Facebook limit
                        }
                        if (!empty($card['subtitle'])) {
                            $element['subtitle'] = substr($card['subtitle'], 0, 80); // Facebook limit
                        }
                        if (!empty($card['image_url']) || !empty($card['image'])) {
                            $element['image_url'] = $card['image_url'] ?? $card['image'];
                        }
                        
                        // Add buttons (max 3 per card)
                        if (!empty($card['buttons']) && is_array($card['buttons'])) {
                            $element['buttons'] = [];
                            $buttonCount = 0;
                            
                            foreach ($card['buttons'] as $button) {
                                if ($buttonCount >= 3) break;
                                
                                $btn = [
                                    'type' => $button['type'] ?? (!empty($button['url']) ? 'web_url' : 'postback'),
                                    'title' => substr($button['title'] ?? 'Button', 0, 20)
                                ];
                                
                                if (!empty($button['url'])) {
                                    $btn['url'] = $button['url'];
                                }
                                if (!empty($button['payload'])) {
                                    $btn['payload'] = $button['payload'];
                                }
                                
                                $element['buttons'][] = $btn;
                                $buttonCount++;
                            }
                        }
                        
                        if (!empty($element['title'])) {
                            $elements[] = $element;
                        }
                        
                        if (count($elements) >= 10) break; // Facebook limit
                    }
                    
                    if (!empty($elements)) {
                        $data = [
                            'recipient' => ['id' => $recipientId],
                            'message' => [
                                'attachment' => [
                                    'type' => 'template',
                                    'payload' => [
                                        'template_type' => 'generic',
                                        'elements' => $elements
                                    ]
                                ]
                            ]
                        ];
                        
                        Log::info('Carousel prepared', ['cards' => count($elements)]);
                    }
                }
            }
            // Priority 3: Handle interactive messages
            elseif ($interactionData) {
                $interactiveType = $interactionData['interactive_type'] ?? $interactionData['interaction_type'] ?? null;
                
                // Handle button template
                if ($interactiveType === 'button' && !empty($interactionData['buttons'])) {
                    $buttons = $interactionData['buttons'] ?? [];
                    $facebookButtons = [];
                    
                    foreach ($buttons as $button) {
                        if (count($facebookButtons) >= 3) break;
                        
                        $buttonTitle = is_array($button) ? ($button['title'] ?? '') : $button;
                        
                        if (!empty($buttonTitle)) {
                            $btn = [
                                'type' => 'postback',
                                'title' => substr($buttonTitle, 0, 20),
                                'payload' => is_array($button) ? ($button['id'] ?? $buttonTitle) : $buttonTitle
                            ];
                            
                            if (is_array($button) && !empty($button['url'])) {
                                $btn['type'] = 'web_url';
                                $btn['url'] = $button['url'];
                                unset($btn['payload']);
                            }
                            
                            $facebookButtons[] = $btn;
                        }
                    }
                    
                    if (!empty($facebookButtons)) {
                        $data = [
                            'recipient' => ['id' => $recipientId],
                            'message' => [
                                'attachment' => [
                                    'type' => 'template',
                                    'payload' => [
                                        'template_type' => 'button',
                                        'text' => substr($message, 0, 640),
                                        'buttons' => $facebookButtons
                                    ]
                                ]
                            ]
                        ];
                        
                        Log::info('Button template prepared', ['buttons' => count($facebookButtons)]);
                    }
                }
                // Handle CTA URL
                elseif (isset($interactionData['cta_url']) || isset($interactionData['url'])) {
                    $ctaUrl = null;
                    $ctaText = null;
                    
                    if (isset($interactionData['cta_url']['url'])) {
                        $ctaUrl = $interactionData['cta_url']['url'];
                        $ctaText = $interactionData['cta_url']['display_text'] ?? 'Visit';
                    } elseif (isset($interactionData['url'])) {
                        $ctaUrl = $interactionData['url'];
                        $ctaText = $interactionData['display_text'] ?? 'Visit';
                    }
                    
                    if ($ctaUrl && filter_var($ctaUrl, FILTER_VALIDATE_URL)) {
                        $data = [
                            'recipient' => ['id' => $recipientId],
                            'message' => [
                                'attachment' => [
                                    'type' => 'template',
                                    'payload' => [
                                        'template_type' => 'button',
                                        'text' => substr($message, 0, 640),
                                        'buttons' => [
                                            [
                                                'type' => 'web_url',
                                                'url' => $ctaUrl,
                                                'title' => substr($ctaText, 0, 20)
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ];
                        
                        Log::info('CTA button prepared', ['url' => $ctaUrl]);
                    }
                }
                // Handle list (convert to generic template)
                elseif ($interactiveType === 'list' && isset($interactionData['list_data']['sections'])) {
                    $listData = $interactionData['list_data'];
                    $sections = $listData['sections'] ?? [];
                    
                    if (!empty($sections)) {
                        $elements = [];
                        
                        foreach ($sections as $section) {
                            $rows = $section['rows'] ?? [];
                            foreach ($rows as $row) {
                                if (count($elements) >= 10) break 2;
                                
                                $element = [
                                    'title' => substr($row['title'] ?? '', 0, 80),
                                    'subtitle' => substr($row['description'] ?? '', 0, 80)
                                ];
                                
                                if (!empty($row['image_url']) || !empty($row['image'])) {
                                    $element['image_url'] = $row['image_url'] ?? $row['image'];
                                }
                                
                                if (!empty($row['id'])) {
                                    $element['buttons'] = [
                                        [
                                            'type' => 'postback',
                                            'title' => 'Select',
                                            'payload' => $row['id']
                                        ]
                                    ];
                                }
                                
                                if (!empty($element['title'])) {
                                    $elements[] = $element;
                                }
                            }
                        }
                        
                        if (!empty($elements)) {
                            $data = [
                                'recipient' => ['id' => $recipientId],
                                'message' => [
                                    'attachment' => [
                                        'type' => 'template',
                                        'payload' => [
                                            'template_type' => 'generic',
                                            'elements' => $elements
                                        ]
                                    ]
                                ]
                            ];
                            
                            Log::info('List converted to generic template', ['items' => count($elements)]);
                        }
                    }
                }
            }
            
            // Default: Simple text message
            if (!$data) {
                $data = [
                    'recipient' => ['id' => $recipientId],
                    'message' => ['text' => $message]
                ];
                
                Log::info('Simple text message prepared');
            }

            $params = ['access_token' => $accessToken];

            Log::info('Sending Facebook message', [
                'recipient_id' => $recipientId,
                'message_type' => isset($data['message']['attachment']) ? 
                    ($data['message']['attachment']['type'] ?? 'attachment') : 'text'
            ]);

            $response = Http::withOptions([
                'verify' => false,
                'timeout' => 30,
            ])->post($url . '?' . http_build_query($params), $data);

            Log::info('Facebook message response', [
                'status' => $response->status(),
                'success' => $response->successful()
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                
                // Send caption as separate message if needed
                if ($sendCaptionSeparately && !empty($captionText)) {
                    sleep(1); // Small delay
                    $captionData = [
                        'recipient' => ['id' => $recipientId],
                        'message' => ['text' => $captionText]
                    ];
                    
                    $captionResponse = Http::withOptions([
                        'verify' => false,
                        'timeout' => 30,
                    ])->post($url . '?' . http_build_query($params), $captionData);
                    
                    Log::info('Caption sent separately', [
                        'success' => $captionResponse->successful()
                    ]);
                }
                
                return [
                    'success' => true,
                    'data' => $responseData,
                    'message' => 'Message sent successfully'
                ];
            }

            $errorBody = $response->json();
            $errorMsg = 'Failed to send message';
            $errorCode = null;
            $errorSubcode = null;
            
            if (isset($errorBody['error'])) {
                $error = $errorBody['error'];
                $errorCode = $error['code'] ?? null;
                $errorSubcode = $error['error_subcode'] ?? null;
                $errorMsg = $error['message'] ?? $errorMsg;
                
                Log::error('Facebook API error', [
                    'code' => $errorCode,
                    'subcode' => $errorSubcode,
                    'message' => $errorMsg,
                    'type' => $error['type'] ?? null
                ]);
                
                // Handle specific error code 551 - 24-hour messaging window closed
                if ($errorCode == 551) {
                    $errorMsg = 'The 24-hour messaging window has closed for this conversation. You can only send messages to users who have messaged you within the last 24 hours. To send a message, the user needs to initiate a new conversation.';
                }
            }

            return [
                'success' => false,
                'message' => $errorMsg,
                'error_code' => $errorCode,
                'error_subcode' => $errorSubcode,
                'error_details' => $errorBody,
                'is_24hour_window_error' => ($errorCode == 551)
            ];

        } catch (\Exception $e) {
            Log::error('Facebook send message exception', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversationId,
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'Error sending message: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get recipient ID from conversation messages
     *
     * @param string $conversationId
     * @return string|null
     */
    protected function getRecipientIdFromConversation($conversationId)
    {
        try {
            $accessToken = $this->getServiceConfiguration('facebook_page_access_token');
            $pageId = $this->getServiceConfiguration('facebook_page_id');

            if (empty($accessToken) || empty($pageId)) {
                return null;
            }

            // Get messages from the conversation to find the recipient ID
            $url = "https://graph.facebook.com/v18.0/{$conversationId}/messages";
            $params = [
                'access_token' => $accessToken,
                'fields' => 'from,to',
                'limit' => 1
            ];

            $response = Http::withOptions([
                'verify' => false,
                'timeout' => 30,
            ])->get($url, $params);

            if ($response->successful()) {
                $data = $response->json();
                $messages = $data['data'] ?? [];

                if (!empty($messages)) {
                    $message = $messages[0];

                    // Check the 'to' field for recipient data
                    if (isset($message['to']['data']) && is_array($message['to']['data'])) {
                        foreach ($message['to']['data'] as $recipient) {
                            // Return the first recipient that is not the page
                            if (isset($recipient['id']) && $recipient['id'] !== $pageId) {
                                return $recipient['id'];
                            }
                        }
                    }

                    // Fallback: check 'from' field if it's not from the page
                    if (isset($message['from']['id']) && $message['from']['id'] !== $pageId) {
                        return $message['from']['id'];
                    }
                }
            }

            return null;

        } catch (\Exception $e) {
            Log::error('Error getting recipient ID from conversation', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversationId
            ]);
            return null;
        }
    }

    /**
     * Get Facebook posts
     *
     * @return array
     */
    public function getPosts()
    {
        $pageId = $this->getServiceConfiguration('facebook_page_id');
        $accessToken = $this->getServiceConfiguration('facebook_page_access_token');

        if (empty($pageId) || empty($accessToken)) {
            return [
                'success' => false,
                'message' => 'Facebook API not configured'
            ];
        }

        try {
            // Use /published_posts endpoint for pages to get actual published posts
            // This excludes automatic story updates like profile/cover photo changes
             $url = "https://graph.facebook.com/v18.0/{$pageId}/published_posts";
            $params = [
                'access_token' => $accessToken,
                'fields' => 'id,message,created_time'
            ];
            
            // Note: We don't request reactions/comments here to keep the response lightweight
            // Comments are fetched separately when a post is selected

            Log::info('Facebook Chat Posts API Request', [
                'url' => $url,
                'params' => $params
            ]);

            $response = Http::withOptions([
                'verify' => false,
                'timeout' => 30,
            ])->get($url, $params);

            Log::info('Facebook Chat Posts API Response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Facebook Posts API Response Data', [
                    'has_data' => isset($data['data']),
                    'data_count' => isset($data['data']) ? count($data['data']) : 0,
                    'sample_post' => isset($data['data'][0]) ? $data['data'][0] : null
                ]);

                if (isset($data['data'])) {
                    $allPosts = $data['data'];
                    
                    // Handle pagination to get all posts
                    $paging = $data['paging'] ?? null;
                    $nextUrl = $paging['next'] ?? null;
                    
                    // Fetch additional pages if available (limit to prevent infinite loops)
                    $maxPages = 10; // Limit to 10 pages = 1000 posts max
                    $currentPage = 1;
                    
                    while ($nextUrl && $currentPage < $maxPages) {
                        try {
                            Log::info('Fetching next page of Facebook posts', [
                                'page' => $currentPage + 1,
                                'current_count' => count($allPosts)
                            ]);
                            
                            $nextResponse = Http::withOptions([
                                'verify' => false,
                                'timeout' => 30,
                            ])->get($nextUrl);
                            
                            if ($nextResponse->successful()) {
                                $nextData = $nextResponse->json();
                                if (isset($nextData['data']) && is_array($nextData['data'])) {
                                    $allPosts = array_merge($allPosts, $nextData['data']);
                                    $paging = $nextData['paging'] ?? null;
                                    $nextUrl = $paging['next'] ?? null;
                                    $currentPage++;
                                } else {
                                    break;
                                }
                            } else {
                                Log::warning('Failed to fetch next page of posts', [
                                    'status' => $nextResponse->status()
                                ]);
                                break;
                            }
                        } catch (\Exception $e) {
                            Log::error('Error fetching next page of posts', [
                                'error' => $e->getMessage(),
                                'page' => $currentPage + 1
                            ]);
                            break;
                        }
                    }
                    
                    Log::info('Facebook Posts fetched', [
                        'total_posts' => count($allPosts),
                        'pages_fetched' => $currentPage
                    ]);
                    
                    return [
                        'success' => true,
                        'data' => $allPosts,
                        'paging' => $paging,
                        'message' => 'Posts retrieved successfully'
                    ];
                } else {
                    // Return empty array if data key doesn't exist
                    Log::warning('Facebook Posts API response missing data key', [
                        'response' => $data
                    ]);
                    return [
                        'success' => true,
                        'data' => [],
                        'paging' => null,
                        'message' => 'No posts found'
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'Failed to retrieve posts. Status: ' . $response->status() . '. Response: ' . $response->body()
            ];

        } catch (\Exception $e) {
            Log::error('Facebook Chat Posts Error', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Error retrieving posts: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get comments for a specific post including nested replies
     *
     * @param string $postId
     * @return array
     */
    public function getPostComments($postId)
    {
        $accessToken = $this->getServiceConfiguration('facebook_page_access_token');

        if (empty($accessToken)) {
            return [
                'success' => false,
                'message' => 'Facebook API not configured'
            ];
        }

        try {
            $url = "https://graph.facebook.com/v18.0/{$postId}/comments";
            $params = [
                'access_token' => $accessToken,
                'fields' => 'message,from{name,id},created_time,comments{message,from{name,id},created_time}'
            ];

            Log::info('Facebook Chat Post Comments API Request', [
                'url' => $url,
                'params' => $params,
                'post_id' => $postId
            ]);

            $response = Http::withOptions([
                'verify' => false,
                'timeout' => 30,
            ])->get($url, $params);

            Log::info('Facebook Chat Post Comments API Response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Facebook Post Comments API Response Data', [
                    'post_id' => $postId,
                    'has_data' => isset($data['data']),
                    'data_count' => isset($data['data']) ? count($data['data']) : 0,
                    'sample_comment' => isset($data['data'][0]) ? $data['data'][0] : null
                ]);

                if (isset($data['data'])) {
                    return [
                        'success' => true,
                        'data' => $data['data'],
                        'paging' => $data['paging'] ?? null,
                        'message' => 'Comments with replies retrieved successfully'
                    ];
                } else {
                    // Return empty array if data key doesn't exist (post might have no comments)
                    Log::info('Facebook post has no comments', [
                        'post_id' => $postId,
                        'response' => $data
                    ]);
                    return [
                        'success' => true,
                        'data' => [],
                        'paging' => null,
                        'message' => 'No comments found for this post'
                    ];
                }
            }

            return [
                'success' => false,
                'message' => 'Failed to retrieve comments. Status: ' . $response->status() . '. Response: ' . $response->body()
            ];

        } catch (\Exception $e) {
            Log::error('Facebook Chat Post Comments Error', [
                'error' => $e->getMessage(),
                'post_id' => $postId
            ]);

            return [
                'success' => false,
                'message' => 'Error retrieving comments: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Reply to a Facebook comment via private message using comment_id
     *
     * @param string $commentId
     * @param string $message
     * @return array
     */
    public function replyToComment($commentId, $message)
    {
        $accessToken = $this->getServiceConfiguration('facebook_page_access_token');

        if (empty($accessToken)) {
            return [
                'success' => false,
                'message' => 'Facebook API not configured'
            ];
        }

        try {
            // Use the Messenger API to send a private message using comment_id
            $url = "https://graph.facebook.com/v18.0/me/messages";

            $data = [
                'recipient' => [
                    'comment_id' => $commentId
                ],
                'message' => [
                    'text' => $message
                ]
            ];

            $params = [
                'access_token' => $accessToken
            ];

            Log::info('Facebook Chat Comment Private Reply API Request', [
                'url' => $url,
                'data' => $data,
                'params' => $params,
                'comment_id' => $commentId
            ]);

            $response = Http::withOptions([
                'verify' => false,
                'timeout' => 30,
            ])->post($url . '?' . http_build_query($params), $data);

            Log::info('Facebook Chat Comment Private Reply API Response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                return [
                    'success' => true,
                    'data' => $responseData,
                    'message' => 'Private reply sent successfully',
                    'type' => 'private'
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to send private reply. Status: ' . $response->status() . '. Response: ' . $response->body()
            ];

        } catch (\Exception $e) {
            Log::error('Facebook Chat Comment Private Reply Error', [
                'error' => $e->getMessage(),
                'comment_id' => $commentId
            ]);

            return [
                'success' => false,
                'message' => 'Error sending private reply: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Reply to a Facebook comment publicly under the post
     *
     * @param string $commentId
     * @param string $message
     * @return array
     */
    public function replyToCommentPublicly($commentId, $message)
    {
        $accessToken = $this->getServiceConfiguration('facebook_page_access_token');

        if (empty($accessToken)) {
            return [
                'success' => false,
                'message' => 'Facebook API not configured'
            ];
        }

        try {
            // Use the Graph API to reply to a comment publicly
            $url = "https://graph.facebook.com/v18.0/{$commentId}/comments";

            $data = [
                'message' => $message
            ];

            $params = [
                'access_token' => $accessToken
            ];

            Log::info('Facebook Chat Comment Public Reply API Request', [
                'url' => $url,
                'data' => $data,
                'params' => $params,
                'comment_id' => $commentId
            ]);

            $response = Http::withOptions([
                'verify' => false,
                'timeout' => 30,
            ])->post($url . '?' . http_build_query($params), $data);

            Log::info('Facebook Chat Comment Public Reply API Response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                return [
                    'success' => true,
                    'data' => $responseData,
                    'message' => 'Public reply posted successfully',
                    'type' => 'public'
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to post public reply. Status: ' . $response->status() . '. Response: ' . $response->body()
            ];

        } catch (\Exception $e) {
            Log::error('Facebook Chat Comment Public Reply Error', [
                'error' => $e->getMessage(),
                'comment_id' => $commentId
            ]);

            return [
                'success' => false,
                'message' => 'Error posting public reply: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get comment data
     *
     * @param string $commentId
     * @return array
     */
    protected function getCommentData($commentId)
    {
        $accessToken = $this->getServiceConfiguration('facebook_page_access_token');

        if (empty($accessToken)) {
            return [
                'success' => false,
                'message' => 'Facebook API not configured'
            ];
        }

        try {
            $url = "https://graph.facebook.com/v18.0/{$commentId}";
            $params = [
                'access_token' => $accessToken,
                'fields' => 'from,message,created_time'
            ];

            $response = Http::withOptions([
                'verify' => false,
                'timeout' => 30,
            ])->get($url, $params);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'data' => $data
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to get comment data'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error getting comment data: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check if Facebook API is properly configured
     *
     * @return bool
     */
    public function isConfigured()
    {
        $requiredSettings = [
            'facebook_page_access_token',
            'facebook_page_id'
        ];

        foreach ($requiredSettings as $setting) {
            if (empty($this->getServiceConfiguration($setting))) {
                return false;
            }
        }

        return true;
    }
}
