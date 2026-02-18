<?php
/**
* WhatsAppApiService.php -
*
*-----------------------------------------------------------------------------*/

namespace App\Yantrana\Components\WhatsAppService\Services;

use App\Yantrana\Base\BaseEngine;
use App\Yantrana\Components\WhatsAppService\Interfaces\WhatsAppServiceEngineInterface;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WhatsAppApiService extends BaseEngine implements WhatsAppServiceEngineInterface
{
    protected $baseApiRequestEndpoint = 'https://graph.facebook.com/v24.0/'; // Base Request endpoint (use v24.0)

    protected $waAccountId; // WhatsApp Business Account ID
    protected $whatsAppPhoneNumberId; // Phone number ID
    protected $accessToken; // Access token
    protected $vendorId = null;

    /**
     * Constructor
     *
     *
     * @return void
     *-----------------------------------------------------------------------*/
    public function __construct(
    ) {
    }

    /**
     * Configure settings based on vendor id
     *
     * @param string $serviceItem
     * @return mixed
     */
    protected function getServiceConfiguration($serviceItem)
    {
        if ($serviceItem == 'current_phone_number_id') {
            return fromPhoneNumberIdForRequest() ?: getVendorSettings($serviceItem, null, null, $this->vendorId ?: getVendorId());
        }
        return getVendorSettings($serviceItem, null, null, $this->vendorId ?: getVendorId());
    }

    /**
     * Fetch All the templates of the account
     *
     * @return array
     */
    public function getTemplates()
    {
        return $this->apiGetRequest("{$this->getServiceConfiguration('whatsapp_business_account_id')}/message_templates", [
            'limit' => 500
        ])['data'];
    }
    /**
     * Fetch template details
     * @link https://developers.facebook.com/docs/graph-api/reference/whats-app-business-hsm/
     * @return array
     */
    public function getTemplate($templateId)
    {
        return $this->apiGetRequest("$templateId", [
            // 'fields' =>'rejected_reason,status,quality_score'
        ]);
    }
    /**
     * Get template rejected reason and status
     *
     * @param int $templateId
     * @return json
     */
    public function getTemplateRejectionReason($templateId)
    {
        return $this->apiGetRequest("$templateId", [
            'fields' => 'rejected_reason,status'
        ]);
    }

    /**
     * Delete template
     *
     * @link https://developers.facebook.com/docs/graph-api/reference/whats-app-business-hsm/#Deleting
     *
     * @return void
     */
    public function deleteTemplate($whatsAppTemplateName, $whatsAppTemplateId)
    {
        return $this->apiDeleteRequest("{$this->getServiceConfiguration('whatsapp_business_account_id')}/message_templates", [
            'name' => $whatsAppTemplateName,
            'hsm_id' => $whatsAppTemplateId,
        ]);
    }

    /**
     * Send Template Message
     *
     * @param  object  $whatsAppTemplate
     * @param  int  $toNumber
     * @param  array  $components
     *
     * @link https://developers.facebook.com/docs/whatsapp/cloud-api/guides/send-message-templates
     *
     * @return array
     */
    public function sendTemplateMessage($whatsAppTemplateName, $whatsAppTemplateLanguage, $toNumber, $components = [], $vendorId = null)
    {
        if ($vendorId) {
            $this->vendorId = $vendorId;
        }
        // Pre-upload header media if present and replace link with id
        foreach ($components as &$component) {
            if (
                isset($component['type']) && $component['type'] === 'header' &&
                isset($component['parameters']) && is_array($component['parameters'])
            ) {
                foreach ($component['parameters'] as &$param) {
                    // Only process image, video, document types
                    if (
                        isset($param['type']) && in_array($param['type'], ['image', 'video', 'document']) &&
                        isset($param[$param['type']]['link']) && !isset($param[$param['type']]['id'])
                    ) {
                        $uploadResult = $this->uploadMedia($param[$param['type']]['link']);
                        if (is_array($uploadResult) && isset($uploadResult['id'])) {
                            $param[$param['type']] = ['id' => $uploadResult['id']];
                        }
                    }
                }
            }
        }
        unset($component, $param); // break reference
        return $this->apiPostRequest("{$this->getServiceConfiguration('current_phone_number_id')}/messages", [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toNumber,
            'type' => 'template',
            'template' => [
                'name' => $whatsAppTemplateName,
                'language' => [
                    'policy' => 'deterministic',
                    'code' => $whatsAppTemplateLanguage,
                ],
                'components' => array_values($components),
            ],
        ]);
    }

    /**
     * Send Order Details Template Message
     *
     * This method sends a WhatsApp template message with order details using the ORDER component.
     * The template must have an ORDER_DETAILS button type configured.
     *
     * @param  string  $whatsAppTemplateName Template name (must be approved and active)
     * @param  string  $whatsAppTemplateLanguage Template language code (e.g., 'en', 'en_US')
     * @param  string  $toNumber Recipient phone number in E.164 format (e.g., '1234567890')
     * @param  object|array  $order Order object or array with order data
     * @param  array  $additionalComponents Additional template components (header parameters, body parameters, etc.)
     * @param  int|null  $vendorId Vendor ID for multi-tenant support
     *
     * @link https://developers.facebook.com/docs/whatsapp/cloud-api/guides/send-message-templates#order-details-message-templates
     *
     * @return array API response with message ID or error details
     */
    public function sendOrderDetailsTemplateMessage($whatsAppTemplateName, $whatsAppTemplateLanguage, $toNumber, $order, $additionalComponents = [], $vendorId = null)
    {
        if ($vendorId) {
            $this->vendorId = $vendorId;
        }

        // Validate required parameters
        if (empty($whatsAppTemplateName)) {
            throw new Exception(__tr('Template name is required'), 400);
        }

        if (empty($toNumber)) {
            throw new Exception(__tr('Recipient phone number is required'), 400);
        }

        if (empty($order)) {
            throw new Exception(__tr('Order data is required'), 400);
        }

        // Extract order data from object or array
        $orderId = is_object($order) ? ($order->order_id ?? $order->id ?? '') : ($order['order_id'] ?? $order['id'] ?? '');
        $currency = is_object($order) ? ($order->currency ?? 'USD') : ($order['currency'] ?? 'USD');
        $totalAmount = 0;
        
        // Try to get total amount from various possible methods/properties
        if (is_object($order)) {
            if (method_exists($order, 'getFinalAmount')) {
                $totalAmount = $order->getFinalAmount();
            } elseif (method_exists($order, 'getTotalAmount')) {
                $totalAmount = $order->getTotalAmount();
            } elseif (isset($order->final_amount)) {
                $totalAmount = $order->final_amount;
            } elseif (isset($order->total_amount)) {
                $totalAmount = $order->total_amount;
            } elseif (isset($order->amount)) {
                $totalAmount = $order->amount;
            }
        } else {
            $totalAmount = $order['final_amount'] ?? $order['total_amount'] ?? $order['amount'] ?? 0;
        }
        
        // Get order items
        $rawOrderItems = [];
        if (is_object($order)) {
            // Try different methods to get order items
            if (method_exists($order, 'getItemsWithProductNames')) {
                $rawOrderItems = $order->getItemsWithProductNames();
            } elseif (method_exists($order, 'getItems')) {
                $rawOrderItems = $order->getItems();
            } elseif (isset($order->items)) {
                $rawOrderItems = is_array($order->items) ? $order->items : [];
            } elseif (isset($order->line_items)) {
                $rawOrderItems = is_array($order->line_items) ? $order->line_items : [];
            }
        } else {
            $rawOrderItems = $order['items'] ?? $order['line_items'] ?? [];
        }

        // Validate that we have at least one item
        if (empty($rawOrderItems)) {
            throw new Exception(__tr('Order must contain at least one item'), 400);
        }

        // Build order details payload according to Meta's NEW ORDER_DETAILS specification
        // Convert amounts to cents (value) with offset (for decimals)
        $totalAmountInCents = (int)($totalAmount * 100);
        
        // Build items in new format
        $orderItems = [];
        foreach ($rawOrderItems as $item) {
            $itemData = is_object($item) ? (array)$item : $item;
            $itemPrice = (float)($itemData['item_price'] ?? $itemData['price'] ?? $itemData['unit_price'] ?? 0);
            $itemPriceInCents = (int)($itemPrice * 100);
            
            $orderItems[] = [
                'retailer_id' => $itemData['product_retailer_id'] ?? $itemData['retailer_id'] ?? $itemData['product_id'] ?? '',
                'name' => $itemData['display_name'] ?? $itemData['name'] ?? $itemData['product_name'] ?? 'Product',
                'quantity' => (int)($itemData['quantity'] ?? 1),
                'amount' => [
                    'value' => $itemPriceInCents,
                    'offset' => 100  // 2 decimal places
                ]
            ];
        }
        
        // Validate payment_type and currency match BEFORE building payload
        $currencyCountryMap = [
            'br' => 'BRL',
            'us' => 'USD',
            'in' => 'INR',
        ];
        $paymentType = is_object($order) ? ($order->payment_type ?? 'br') : ($order['payment_type'] ?? 'br');
        $expectedCurrency = $currencyCountryMap[$paymentType] ?? null;
        
        if ($expectedCurrency && strtoupper($currency) !== $expectedCurrency) {
            throw new Exception(__tr('Payment type __paymentType__ requires currency __currency__, but __actualCurrency__ was provided', [
                '__paymentType__' => $paymentType,
                '__currency__' => $expectedCurrency,
                '__actualCurrency__' => $currency
            ]), 400);
        }
        
        // Ensure reference_id is unique (add timestamp for Meta reconciliation)
        $referenceId = (string)$orderId;
        if (empty($referenceId)) {
            $referenceId = 'ORD-' . time() . '-' . rand(1000, 9999);
        } else {
            // Add timestamp to ensure uniqueness for Meta reconciliation
            $referenceId = $referenceId . '-' . time();
        }
        
        // Build order_details action payload
        $orderDetailsPayload = [
            'reference_id' => $referenceId,
            'type' => is_object($order) ? ($order->order_type ?? 'digital-goods') : ($order['order_type'] ?? 'digital-goods'),
            'payment_type' => $paymentType,
            'currency' => strtoupper($currency),
            'total_amount' => [
                'value' => $totalAmountInCents,
                'offset' => 100
            ],
            'order' => [
                'status' => is_object($order) ? ($order->status ?? 'pending') : ($order['status'] ?? 'pending'),
                'items' => $formattedOrderItems,
                'subtotal' => [
                    'value' => $totalAmountInCents,
                    'offset' => 100
                ]
            ]
        ];
        
        // Add tax if provided
        $tax = is_object($order) ? ($order->tax ?? 0) : ($order['tax'] ?? 0);
        if ($tax > 0) {
            $taxInCents = (int)($tax * 100);
            $orderDetailsPayload['order']['tax'] = [
                'value' => $taxInCents,
                'offset' => 100,
                'description' => is_object($order) ? ($order->tax_description ?? 'Tax') : ($order['tax_description'] ?? 'Tax')
            ];
        }
        
        // Add payment settings if provided (PIX, Payment Links, etc.)
        $paymentSettings = is_object($order) ? ($order->payment_settings ?? null) : ($order['payment_settings'] ?? null);
        if (!empty($paymentSettings)) {
            $orderDetailsPayload['payment_settings'] = $paymentSettings;
        }
        
        // Get button index from additionalComponents or default to 0
        // If sending via direct method, button index should be provided in additionalComponents
        $buttonIndex = 0;
        foreach ($additionalComponents as $component) {
            if (isset($component['type']) && $component['type'] === 'button' && 
                isset($component['sub_type']) && $component['sub_type'] === 'order_details') {
                $buttonIndex = $component['index'] ?? 0;
                break;
            }
        }
        
        // Build button component with order_details action
        $orderDetailsButtonComponent = [
            'type' => 'button',
            'sub_type' => 'order_details',
            'index' => $buttonIndex,  // Use actual button index from template
            'parameters' => [
                [
                    'type' => 'action',
                    'action' => [
                        'order_details' => $orderDetailsPayload
                    ]
                ]
            ]
        ];

        // Merge additional components with order details button component
        $components = array_merge($additionalComponents, [$orderDetailsButtonComponent]);

        // Log the request for debugging
        \Log::info('Sending order details template message', [
            'template_name' => $whatsAppTemplateName,
            'template_language' => $whatsAppTemplateLanguage,
            'to_number' => $toNumber,
            'order_id' => $orderId,
            'reference_id' => $referenceId,
            'items_count' => count($formattedOrderItems),
            'total_amount' => $totalAmount,
            'currency' => $currency,
            'payment_type' => $paymentType,
            'button_index' => $buttonIndex,
            'components_count' => count($components),
        ]);

        return $this->apiPostRequest("{$this->getServiceConfiguration('current_phone_number_id')}/messages", [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toNumber,
            'type' => 'template',
            'template' => [
                'name' => $whatsAppTemplateName,
                'language' => [
                    'policy' => 'deterministic',
                    'code' => $whatsAppTemplateLanguage,
                ],
                'components' => array_values($components),
            ],
        ]);
    }
    /**
     * Send Template Message
     *
     * @param  object  $whatsAppTemplate
     * @param  int  $toNumber
     * @param  array  $components
     *
     * @link https://developers.facebook.com/docs/whatsapp/cloud-api/guides/send-message-templates
     *
     * @return object
     */
    public function sendTemplateMessageViaPool(&$pool, $queueUid, $whatsAppTemplateName, $whatsAppTemplateLanguage, $toNumber, $components = [], $vendorId = null)
    {
        if ($vendorId) {
            $this->vendorId = $vendorId;
        }
        return $this->baseApiRequest($pool->as($queueUid))->post("{$this->baseApiRequestEndpoint}{$this->getServiceConfiguration('current_phone_number_id')}/messages", [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toNumber,
            'type' => 'template',
            'template' => [
                'name' => $whatsAppTemplateName,
                'language' => [
                    'code' => $whatsAppTemplateLanguage,
                ],
                'components' => array_values($components),
            ],
        ]);
    }

    /**
     * Send Message
     *
     * @param  int  $toNumber
     * @param  int  $toNumber
     * @param  array  $options
     *
     * @link https://developers.facebook.com/docs/whatsapp/cloud-api/guides/send-messages/#sending-free-form-messages

     *
     * @return array
     */
    public function sendMessage($toNumber, $body, $vendorId = null, $options = [])
    {
        $options = array_merge([
            'repliedToMessageWamid' => null
        ], $options);

        if ($vendorId) {
            $this->vendorId = $vendorId;
        }
        $dataToProcess = [
            'to' => $toNumber,
            'type' => 'text',
            'text' => [
                'preview_url' => true,
                'body' => $body,
            ],
        ];
        if ($options['repliedToMessageWamid']) {
            $dataToProcess['context'] = [
                'message_id' => $options['repliedToMessageWamid']
            ];
        }
        return $this->apiPostRequest("{$this->getServiceConfiguration('current_phone_number_id')}/messages", $dataToProcess);
    }

    /**
     * Send Interactive Message
     *
     * @param  int  $toNumber
     * @param  array  $messageData
     *
     * @link https://developers.facebook.com/docs/whatsapp/cloud-api/guides/send-messages/#interactive-messages

     *
     * @return array
     */
    public function sendInteractiveMessage($toNumber, $messageData, $vendorId = null)
    {
        if ($vendorId) {
            $this->vendorId = $vendorId;
        }
        $messageData = array_merge([
            'interactive_type' => 'button',
            'media_link' => '',
            'media_url' => '', // New field from bot flows
            'header_type' => '', // "text", "image", "video", or "document"
            'media_type' => '', // New field from bot flows
            'header_text' => '',
            'body_text' => '',
            'footer_text' => '',
            'buttons' => [
            ],
            'cta_url' => null,
            'action' => null,
            'list_data' => null,
            'caption' => '', // For media headers
            'filename' => '', // For document headers
        ], $messageData);
        $interactiveData = [
            'type' => $messageData['interactive_type'],
        ];

        // ===== MEDIA HEADER SUPPORT =====
        // Only allow Meta (WhatsApp) media ID, never send direct media URLs
        $mediaUrl = $messageData['media_url'] ?? $messageData['media_link'] ?? '';
        $mediaType = $messageData['media_type'] ?? $messageData['header_type'] ?? '';
        $mediaId = $messageData['media_id'] ?? $messageData['header_media_id'] ?? null;

        // Always upload to WhatsApp if mediaId is not provided
        if (empty($mediaId) && !empty($mediaUrl) && !empty($mediaType) && $mediaType !== 'text') {
            $uploadResult = $this->uploadMedia($mediaUrl);
            if (is_array($uploadResult) && isset($uploadResult['id'])) {
                $mediaId = $uploadResult['id'];
            } else {
                // If upload fails, return error and do not send the message
                \Log::error('Failed to upload media to WhatsApp. Aborting sendInteractiveMessage.', [
                    'media_url' => $mediaUrl,
                    'media_type' => $mediaType,
                    'upload_result' => $uploadResult
                ]);
                return [
                    'success' => false,
                    'error' => 'Failed to upload media to WhatsApp. Please try again with a valid file.'
                ];
            }
        }

        if (!empty($mediaId) && !empty($mediaType) && $mediaType !== 'text') {
            $mediaKey = $mediaType === 'image' ? 'image' :
                       ($mediaType === 'video' ? 'video' :
                       ($mediaType === 'document' ? 'document' : 'image'));
            $interactiveData['header'] = [
                'type' => $mediaType,
                $mediaKey => ['id' => $mediaId]
            ];
            // Caption only supported in images & video (not documents)
            if (!empty($messageData['caption']) && $mediaType !== 'document') {
                $interactiveData['header'][$mediaKey]['caption'] = $messageData['caption'];
            }
            // Filename only for documents
            if ($mediaType === 'document' && !empty($messageData['filename'])) {
                $interactiveData['header']['document']['filename'] = $messageData['filename'];
            }
        } elseif (!empty($messageData['header_text']) || ($messageData['header_type'] ?? '') === 'text') {
            // Text header
            $interactiveData['header'] = [
                'type' => 'text',
                'text' => $messageData['header_text'],
            ];
        }
        if ($messageData['body_text']) {
            $interactiveData['body'] = [
                'text' => $messageData['body_text'],
            ];
        }
        if ($messageData['footer_text']) {
            $interactiveData['footer'] = [
                'text' => $messageData['footer_text'],
            ];
        }
        if ($messageData['interactive_type'] == 'list') {
            $sections = [];
            $sectionIndex = 0;
            foreach ((array) $messageData['list_data']['sections'] as $sectionKey => $section) {
                $sections[$sectionIndex] = [
                    'title' => $section['title'],
                    'rows' => [],
                ];
                foreach ((array) $section['rows'] as $row) {
                    $sections[$sectionIndex]['rows'][] = [
                        'id' => $row['id'] ?? $row['row_id'] ?? '',
                        'title' => $row['title'],
                        'description' => $row['description'],
                    ];
                }
                $sectionIndex++;
            }
            $interactiveData['action'] = [
                'button' => $messageData['list_data']['button_text'],
                'sections' => $sections
            ];
        } elseif ($messageData['interactive_type'] == 'cta_url') {
           
            $interactiveData['type'] = 'cta_url'; // Set type to 'cta_url'
            
            $ctaUrl = $messageData['cta_url']['url'] ?? '';
            $ctaText = $messageData['cta_url']['display_text'] ?? 'Visit';
            
            if (!empty($ctaUrl)) {
                // CTA URL structure: action.name = "cta_url" with parameters object
                $interactiveData['action'] = [
                    'name' => 'cta_url',
                    'parameters' => [
                        'display_text' => substr($ctaText, 0, 20), // WhatsApp limits display text to 20 chars
                        'url' => $ctaUrl
                    ]
                ];
                
                // Log the CTA URL payload for debugging
                \Illuminate\Support\Facades\Log::info('Sending CTA URL interactive payload', [
                    'interactive_type' => 'cta_url',
                    'body_text' => $messageData['body_text'] ?? '',
                    'display_text' => substr($ctaText, 0, 20),
                    'url' => $ctaUrl,
                    'payload_structure' => $interactiveData
                ]);
            }
        } elseif ($messageData['interactive_type'] == 'button') {
            $buttons = [];
            if ($messageData['buttons']) {
                $buttonIndex = 1;
                foreach ($messageData['buttons'] as $button) {
                    // Handle both array format (from flows) and string format (legacy)
                    if (is_array($button)) {
                        $buttonTitle = $button['title'] ?? '';
                        $buttonId = $button['id'] ?? ('button-id' . $buttonIndex);
                    } else {
                        // Legacy format: button is a string
                        $buttonTitle = $button;
                        $buttonId = 'button-id' . $buttonIndex;
                    }
                    
                    // Only add button if title is not empty
                    if (!empty($buttonTitle)) {
                        $buttons[] = [
                            'type' => 'reply',
                            'reply' => [
                                'id' => $buttonId,
                                'title' => $buttonTitle,
                            ],
                        ];
                        $buttonIndex++;
                    }
                }
                $interactiveData['action'] = [
                    'buttons' => $buttons
                ];
            }
        } elseif ($messageData['interactive_type'] == 'flow') {
            // Handle WhatsApp Flow interactive messages
            $flowId = $messageData['flow_id'] ?? null;
            $flowVersion = $messageData['flow_version'] ?? null;
            
            $interactiveData['type'] = 'flow';
            $interactiveData['action'] = [
                'name' => 'flow',
                'parameters' => [
                    'flow_message_version' => '3',
                    'flow_action' => 'navigate',
                    'flow_id' => $flowId,
                    'flow_cta' => $messageData['flow_cta'] ?? 'Open Flow',
                ]
            ];
            
            // Add flow_token (version) if provided and not empty
            if (!empty($flowVersion) && trim($flowVersion) !== '') {
                $interactiveData['action']['parameters']['flow_token'] = $flowVersion;
            }
        }

        // ===============================
        // FINAL INTERACTIVE PAYLOAD BUILD
        // ===============================
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $toNumber,
            'type' => 'interactive',
            'interactive' => $interactiveData,
        ];

        // 🚀 MEDIA HEADER ATTACH — FINAL & CORRECT POSITION
        $node = $messageData['node'] ?? null;
        if (isset($node) && !empty($node['media_url'])) {
            $type = $node['media_type'] ?? 'image';
            $key = ($type === 'document') ? 'document' : $type;
            $nodeMediaId = $node['media_id'] ?? null;

            if (empty($nodeMediaId) && !empty($node['media_url']) && $type !== 'text') {
                $nodeUploadResult = $this->uploadMedia($node['media_url']);
                if (is_array($nodeUploadResult) && isset($nodeUploadResult['id'])) {
                    $nodeMediaId = $nodeUploadResult['id'];
                }
            }

            $payload['interactive']['header'] = [
                'type' => $type,
                $key => $nodeMediaId
                    ? ['id' => $nodeMediaId]
                    : ['link' => $node['media_url']]
            ];

            if (!empty($node['caption']) && $type !== 'document') {
                $payload['interactive']['header'][$key]['caption'] = $node['caption'];
            }

            if ($type === 'document' && !empty($node['filename'])) {
                $payload['interactive']['header'][$key]['filename'] = $node['filename'];
            }

            \Illuminate\Support\Facades\Log::info('🔥 MEDIA HEADER ATTACHED CORRECTLY NOW', [
                'media' => $node['media_url'],
                'media_id' => $nodeMediaId,
                'payload' => $payload
            ]);
        }

        // Extract media info for checkpoint log
        $hasMediaHeader = isset($payload['interactive']['header']) && isset($payload['interactive']['header']['type']) && $payload['interactive']['header']['type'] !== 'text';
        $mediaType = $hasMediaHeader ? ($payload['interactive']['header']['type'] ?? null) : null;
        $mediaUrl = null;
        if ($hasMediaHeader && isset($payload['interactive']['header'][$mediaType])) {
            $mediaUrl = $payload['interactive']['header'][$mediaType]['link'] ?? null;
        }

        \Illuminate\Support\Facades\Log::info("=== MEDIA HANDLER CHECKPOINT ===", [
            "has_media_header" => $hasMediaHeader ?? null,
            "media_type" => $mediaType ?? null,
            "media_url" => $mediaUrl ?? null,
        ]);

        // Log the payload before sending
        \Illuminate\Support\Facades\Log::info('Interactive Message Payload Generated', [
            'payload' => $payload,
            'has_media_header' => $hasMediaHeader,
            'media_type' => $mediaType,
            'interactive_type' => $interactiveData['type'] ?? null
        ]);

        \Illuminate\Support\Facades\Log::info("=== FINAL WHATSAPP PAYLOAD ===", [
            "payload" => $payload ?? null,
        ]);

        return $this->apiPostRequest("{$this->getServiceConfiguration('current_phone_number_id')}/messages", $payload);
    }

    /**
     * Send Media Message
     *
     * @param  int  $toNumber
     * @param  int  $toNumber
     *
     * @link https://developers.facebook.com/docs/whatsapp/cloud-api/guides/send-messages/#media-messages
     * @link https://developers.facebook.com/docs/whatsapp/cloud-api/reference/messages#media-object
     *
     * @return array
     */
    public function sendMediaMessage($toNumber, string $type, string $mediaLinkOrId, $caption = '', $filename = '', $vendorId = null)
    {
        if ($vendorId) {
            $this->vendorId = $vendorId;
        }

        $typeDetails = [];
        $mediaId = $mediaLinkOrId;

        // Always upload to Meta if a URL is provided
        if (Str::startsWith($mediaLinkOrId, 'http')) {
            $uploadResult = $this->uploadMedia($mediaLinkOrId);
            if (is_array($uploadResult) && isset($uploadResult['id'])) {
                $mediaId = $uploadResult['id'];
            } else {
                \Log::error('Failed to upload media to WhatsApp. Aborting sendMediaMessage.', [
                    'media_url' => $mediaLinkOrId,
                    'media_type' => $type,
                    'upload_result' => $uploadResult
                ]);
                return [
                    'success' => false,
                    'error' => 'Failed to upload media to WhatsApp. Please try again with a valid file.'
                ];
            }
        }

        $typeDetails['id'] = $mediaId;

        // if not audio or sticker
        if (! in_array($type, [
            'audio',
            'sticker',
        ])) {
            if (! empty($caption)) {
                $typeDetails['caption'] = $caption;
            }
        }
        if (in_array($type, [
            'document',
        ])) {
            if (! empty($filename)) {
                $typeDetails['filename'] = $filename;
            }
        }

        return $this->apiPostRequest("{$this->getServiceConfiguration('current_phone_number_id')}/messages", [
            'to' => $toNumber,
            'type' => $type,
            $type => $typeDetails,
        ]);
    }

    /**
     * Send Message
     *
     * @param  int  $toNumber
     * @param  int  $toNumber
     *
     * @link https://developers.facebook.com/docs/whatsapp/cloud-api/guides/mark-message-as-read
     *
     * @return array
     */
    public function markAsRead($toNumber, $messageId, $vendorId = null)
    {
        if ($vendorId) {
            $this->vendorId = $vendorId;
        }
        return $this->apiPostRequest("{$this->getServiceConfiguration('current_phone_number_id')}/messages", [
            'to' => $toNumber,
            'status' => 'read',
            'message_id' => $messageId,
        ]);
    }

    /**
     * Health Status
     *
     * @link https://developers.facebook.com/docs/whatsapp/cloud-api/health-status
     *
     * @return array
     */
    public function healthStatus()
    {
        return $this->apiGetRequest("{$this->getServiceConfiguration('whatsapp_business_account_id')}", [
            'fields' => 'health_status',
        ]);
    }

    /**
     * Get Phone Numbers
     *
     * @link https://developers.facebook.com/docs/whatsapp/business-management-api/manage-phone-numbers#all-phone-numbers
     *
     * @return array
     */
    public function phoneNumbers()
    {
        $phoneNumbers = $this->apiGetRequest("{$this->getServiceConfiguration('whatsapp_business_account_id')}/phone_numbers?fields=display_phone_number,certificate,name_status,new_certificate,new_name_status,last_onboarded_time", []);
        if(!empty($phoneNumbers['data'] ?? [])) {
            foreach ($phoneNumbers['data'] as $phoneNumber) {
               $this->apiPostRequest($phoneNumber['id'], [
                    'webhook_configuration' =>[
                        'override_callback_uri' => ''
                    ]
               ]);
            }
        }
        return $phoneNumbers;
    }

    /**
     * Get Messaging Limit for a Phone Number
     *
     * @link https://developers.facebook.com/docs/whatsapp/cloud-api/phone-numbers
     *
     * @param string $phoneNumberId
     * @return array
     */
   public function getMessagingLimit($phoneNumberId)
{
    // 1️⃣ Log the input
    \Log::info('📤 getMessagingLimit called', [
        'phone_number_id' => $phoneNumberId,
    ]);

    // 2️⃣ Build request subject
    $requestSubject = "{$phoneNumberId}?fields=whatsapp_business_manager_messaging_limit";

        // 2.1️⃣ Log which access token is being used (masked for info, full at debug)
        $accessToken = $this->getServiceConfiguration('whatsapp_access_token');
        \Log::info('🔐 WhatsApp access token (masked) for getMessagingLimit', [
            'access_token_masked' => $accessToken ? (substr($accessToken, 0, 6) . '...' . substr($accessToken, -4)) : null,
        ]);
        \Log::debug('🔐 WhatsApp access token (full) for getMessagingLimit', [
            'access_token' => $accessToken,
        ]);
    // 3️⃣ Log the final URL (VERY IMPORTANT)
    $url = $this->baseApiRequestEndpoint . $requestSubject;
    \Log::info('🌐 WhatsApp Messaging Limit API URL', [
        'url' => $url,
    ]);

    // 4️⃣ Make the API call and capture raw HTTP response for debugging
    $httpResponse = $this->baseApiRequest()->get($url, []);
    $rawBody = $httpResponse->body();

    // 4.1️⃣ Log raw HTTP response (status + body)
    \Log::debug('🌐 Raw HTTP response from WhatsApp Messaging Limit API', [
        'url' => $url,
        'status' => $httpResponse->status(),
        'body' => $rawBody,
    ]);

    // 4.2️⃣ Parse JSON response into array (same as apiGetRequest->json())
    $response = $httpResponse->json();

    // 5️⃣ Log the parsed API response
    \Log::info('📥 WhatsApp Messaging Limit API Response', [
        'phone_number_id' => $phoneNumberId,
        'http_status' => $httpResponse->status(),
        'response' => $response,
    ]);

    // 6️⃣ Log parsed value (optional but useful)
    \Log::info('📊 Parsed Messaging Limit', [
        'messaging_limit' => $response['whatsapp_business_manager_messaging_limit'] ?? null,
    ]);

    return $response;
}


    /**
     * Get Business Profile
     *
     * @link https://developers.facebook.com/docs/whatsapp/cloud-api/reference/business-profiles
     *
     * @return array
     */
    public function businessProfile($whatsAppPhoneNumberId)
    {
        return $this->apiGetRequest("{$whatsAppPhoneNumberId}/whatsapp_business_profile", [
            'fields' => 'about,address,description,email,profile_picture_url,websites,vertical'
        ]);
    }
    public function updateBusinessProfile($whatsAppPhoneNumberId, $updateData)
    {
        return $this->apiPostRequest("{$whatsAppPhoneNumberId}/whatsapp_business_profile", $updateData);
    }

    /**
     * Upload Media to WhatsApp server
     * Useful if your uploaded media url is barred by facebook as you may get error like:
     * Your message couldn't be sent because it includes content that other people on Facebook have reported as abusive
     *
     * @link https://developers.facebook.com/docs/whatsapp/cloud-api/reference/media/#upload-media
     *
     * @param  string  $file  - file local path or url
     * @param  string|null  $mimeType  - required if provided file is url based
     * @return array
     */
    public function uploadMedia(string $file, ?string $mimeType = null)
    {
        $tempFilePath = null;
        try {
            if (Str::startsWith($file, 'http')) {
                $response = Http::get($file);
                if (! $response->ok()) {
                    return new Exception(__tr('Failed to download media from URL'), 400);
                }
                $tempFilePath = tempnam(sys_get_temp_dir(), 'wa_media_');
                file_put_contents($tempFilePath, $response->body());
                $mimeType = $mimeType ?: $response->header('Content-Type');
                $mimeType = $mimeType ?: mime_content_type($tempFilePath);
                $file = $tempFilePath;
            } else {
                $mimeType = mime_content_type($file);
            }
            $ch = curl_init();
            $url = $this->baseApiRequestEndpoint . $this->getServiceConfiguration('current_phone_number_id') . '/media';
            $data = [
                'file' => new \CURLFile($file, $mimeType),
                'type' => $mimeType,
                'messaging_product' => 'whatsapp',
            ];
            $headers = [];
            $headers[] = 'Authorization: Bearer ' . $this->getServiceConfiguration('whatsapp_access_token');
            $headers[] = 'Content-type: multipart/form-data';
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $result = curl_exec($ch);
            if ($result === false) {
                $result = curl_error($ch) . ' - ' . curl_errno($ch);
            } else {
                $resultDecode = json_decode($result, true);
                if ($resultDecode) {
                    $result = $resultDecode;
                    if (! isset($result['error'])) {
                        return $result;
                    } else {
                        return new Exception($result['error']['message'], $result['error']['code'] ? $result['error']['code'] : 500);
                    }
                }

                return $result;
            }
            curl_close($ch);
        } catch (Exception $e) {
            abortIf(
                true,
                $e->getCode(),
                $e->getMessage()
            );
        } finally {
            if ($tempFilePath && file_exists($tempFilePath)) {
                @unlink($tempFilePath);
            }
        }
    }
    /**
     * Resumable Media upload useful for template example
     *
     * @link https://developers.facebook.com/docs/graph-api/guides/upload
     *
     * @param  string  $file  - file local path or url
     * @return array
     */
    public function uploadResumableMedia(string $fileName, $options = [])
    {
        $options = array_merge([
            'binary' => false
        ], $options);
        try {
            $file = getTempUploadedFile($fileName);
            $mimeType = mime_content_type($file);
            $fileLength = filesize($file);
            $createdUploadSessionId = null;
            $uploadSessionRequest = $this->baseApiRequest()->post("{$this->baseApiRequestEndpoint}/" . $this->getServiceConfiguration('facebook_app_id') . "/uploads?file_length=$fileLength&file_type=$mimeType", [])->json();
            $ch = curl_init();
            $url = $this->baseApiRequestEndpoint . $uploadSessionRequest['id'];
            $data = [];
            if (!$options['binary']) {
                $data = [
                    'file' => new \CURLFile($file, $mimeType),
                    'type' => $mimeType,
                   ];
            }
            $headers = [];
            $headers[] = 'Authorization: OAuth ' . $this->getServiceConfiguration('whatsapp_access_token');
            $headers[] = 'file_offset: 0';
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            // required in some cases like whatsapp business profile
            if ($options['binary']) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($file));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $result = curl_exec($ch);
            if ($result === false) {
                $result = curl_error($ch) . ' - ' . curl_errno($ch);
            } else {
                $resultDecode = json_decode($result, true);
                if ($resultDecode) {
                    $result = $resultDecode;
                    if (! isset($result['error'])) {
                        return $result['h'] ?? null;
                    } else {
                        return new Exception($result['error']['message'], $result['error']['code'] ? $result['error']['code'] : 500);
                    }
                }

                return $result;
            }
            curl_close($ch);
        } catch (Exception $e) {
            abortIf(
                true,
                $e->getCode(),
                $e->getMessage()
            );
        }
    }

    /**
     * Download media using media id
     *
     * @param string $mediaId
     * @param int $vendorId
     * @return array
     */
    public function downloadMedia($mediaId, $vendorId = null)
    {
        if ($vendorId) {
            $this->vendorId = $vendorId;
        }
        $retrievedMedia = $this->apiGetRequest("$mediaId", []);
        $mediaResponse = $this->baseApiRequest()->get($retrievedMedia['url']);

        return array_merge($retrievedMedia, [
            'body' => $mediaResponse->body(),
        ]);
    }

    /**
     * ----------------------------------------------------------------------------------------------------------------
     * Below are the BASE Requests like get,post, delete etc
     * -----------------------------------------------------------------------------------------------------------------
     */

    /**
     * Manual API requests
     *
     * @return array
     */
    protected function apiGetRequest(string $requestSubject, array $parameters = [])
    {
        return $this->baseApiRequest()->get("{$this->baseApiRequestEndpoint}{$requestSubject}", $parameters)->json();
    }

    /**
     * Manual API requests for messaging
     *
     * @return array
     */
    protected function apiPostRequest(string $requestSubject, array $parameters = [])
    {
        // __dd($requestSubject, $parameters);
        return $this->baseApiRequest()->post("{$this->baseApiRequestEndpoint}{$requestSubject}", array_merge(
            [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
            ],
            $parameters
        ))->json();
    }

    /**
     * Manual API requests for commerce and other non-messaging endpoints
     *
     * @return array
     */
    protected function apiPostRequestCommerce(string $requestSubject, array $parameters = [])
    {
        $url = "{$this->baseApiRequestEndpoint}{$requestSubject}";

        // Log the API call for debugging
        \Log::info('WhatsApp Commerce API Call', [
            'url' => $url,
            'parameters' => $parameters,
            'vendor_id' => $this->vendorId ?: getVendorId()
        ]);

        $response = $this->baseApiRequest()->post($url, $parameters)->json();

        // Log the response for debugging
        \Log::info('WhatsApp Commerce API Response', [
            'url' => $url,
            'response' => $response,
            'vendor_id' => $this->vendorId ?: getVendorId()
        ]);

        return $response;
    }

    /**
     * Manual API requests
     *
     * @return array
     */
    protected function apiDeleteRequest(string $requestSubject, array $parameters = [])
    {
        return $this->baseApiRequest()->delete("{$this->baseApiRequestEndpoint}{$requestSubject}", $parameters)->json();
    }

    /**
     * Base API requests
     *
     * @return Http query request
     */
    protected function baseApiRequest($requestBaseObject = null)
    {
        if ($requestBaseObject) {
            $baseRequest = $requestBaseObject->withToken($this->getServiceConfiguration('whatsapp_access_token'));
        } else {
            $baseRequest = Http::withToken($this->getServiceConfiguration('whatsapp_access_token'));
        }
        return $baseRequest->throw(function ($response, $e) {
            $getContents = $response->getBody()->getContents();
            $getContentsDecoded = json_decode($getContents, true);
            $userMessage = Arr::get($getContentsDecoded, 'error.error_user_title', '') . ' '
            . Arr::get($getContentsDecoded, 'error.message', '') . ' '
            . Arr::get($getContentsDecoded, 'error.error_user_msg', '') . ' '
            . Arr::get($getContentsDecoded, 'error.error_data.details');
            if (!$userMessage) {
                $userMessage = $e->getMessage();
            }
            // __logDebug($userMessage);
            // set notification as your key is token expired
            if (Str::contains($e->getMessage(), 'Session has expired') and !getVendorSettings(
                'whatsapp_access_token_expired',
                null,
                null,
                $this->vendorId ?? getVendorId()
            )
            ) {
                setVendorSettings(
                    'internals',
                    [
                        'whatsapp_access_token_expired' => true
                    ],
                    $this->vendorId ?? getVendorId()
                );
            }
            // stop and response back for error if any
            if (!ignoreFacebookApiError()) {
                // stop and response back for error if any
                abortIf(
                    true,
                    $response->status(),
                    $userMessage
                );
            }
        });
    }

    /**
     * Create Template Request API
     *
     * @param string $whatsAppTemplateName
     * @param string $whatsAppTemplateLanguage
     * @param string $category
     * @param array $components
     * @param int $vendorId
     * @param string|null $displayFormat Display format (e.g., 'ORDER_DETAILS' for order details templates)
     * @link https://developers.facebook.com/docs/graph-api/reference/whats-app-business-hsm/#Creating
     * @return json
     */
    public function createTemplate($whatsAppTemplateName, $whatsAppTemplateLanguage, $category, $components, $vendorId = null, $displayFormat = null)
    {
        if ($vendorId) {
            $this->vendorId = $vendorId;
        }
        
        $requestData = [
            'name' => $whatsAppTemplateName,
            'language' => $whatsAppTemplateLanguage,
            'category' => $category,
            'components' => $components,
            'allow_category_change' => false,
        ];
        
        // ✅ CRITICAL: Add display_format to API request BEFORE sending
        if (!empty($displayFormat)) {
            $requestData['display_format'] = $displayFormat;
            
            \Log::info('🔍 [ORDER_DETAILS] display_format added to API request', [
                'template_name' => $whatsAppTemplateName,
                'display_format' => $displayFormat,
                'request_data_keys' => array_keys($requestData),
            ]);
        } else {
            \Log::info('ℹ️ [ORDER_DETAILS] No display_format (not an ORDER_DETAILS template)', [
                'template_name' => $whatsAppTemplateName,
            ]);
        }
        
        // Log the COMPLETE payload being sent
        \Log::info('WhatsApp Commerce API Call', [
            'url' => "{$this->getServiceConfiguration('whatsapp_business_account_id')}/message_templates",
            'parameters' => $requestData,  // This should now include display_format
            'vendor_id' => $vendorId,
        ]);
        
        $response = $this->apiPostRequestCommerce(
            "{$this->getServiceConfiguration('whatsapp_business_account_id')}/message_templates",
            $requestData
        );
        
        // Log response
        \Log::info('WhatsApp Commerce API Response', [
            'template_name' => $whatsAppTemplateName,
            'response' => $response,
            'has_display_format' => isset($response['display_format']),
            'display_format_value' => $response['display_format'] ?? 'not_returned',
            'template_id' => $response['id'] ?? 'not_returned',
        ]);
        
        return $response;
    }

    /**
     * Update Template Request API
     *
     * @param int $whatsAppTemplateId
     * @param array $components
     * @param int $vendorId
     * @link https://developers.facebook.com/docs/graph-api/reference/whats-app-business-hsm/#Updating
     * @return json
     */
    public function updateTemplate($whatsAppTemplateId, $whatsAppTemplateName, $components, $vendorId = null)
    {
        if ($vendorId) {
            $this->vendorId = $vendorId;
        }
        return $this->apiPostRequestCommerce("$whatsAppTemplateId", [
            'name' => $whatsAppTemplateName,
            'components' => $components,
        ]);
    }

    /**
     * Get WhatsApp Commerce Settings
     *
     * @param string|null $whatsAppPhoneNumberId
     * @param int|null $vendorId
     * @link https://developers.facebook.com/docs/whatsapp/cloud-api/reference/whatsapp-commerce-settings
     * @return array
     */
    public function getCommerceSettings($whatsAppPhoneNumberId = null, $vendorId = null)
    {
        if ($vendorId) {
            $this->vendorId = $vendorId;
        }

        $phoneNumberId = $whatsAppPhoneNumberId ?: $this->getServiceConfiguration('current_phone_number_id');

        if (!$phoneNumberId) {
            // If phone number ID is not configured, return a fallback
            \Log::debug('Phone number ID not configured for vendor', [
                'vendor_id' => $vendorId,
            ]);
            return [
                'is_catalog_visible' => false,
                'is_cart_enabled' => false,
                'catalog_id' => null,
            ];
        }

        try {
            return $this->apiGetRequest("{$phoneNumberId}/whatsapp_commerce_settings", [
                'fields' => 'is_catalog_visible,is_cart_enabled,catalog_id'
            ]);
        } catch (Exception $e) {
            // If API call fails, return a fallback
            \Log::warning('Failed to fetch commerce settings from WhatsApp API', [
                'vendor_id' => $vendorId,
                'phone_number_id' => $phoneNumberId,
                'error' => $e->getMessage(),
            ]);
            return [
                'is_catalog_visible' => false,
                'is_cart_enabled' => false,
                'catalog_id' => null,
            ];
        }
    }

    /**
     * Update WhatsApp Commerce Settings
     *
     * @param array $settings
     * @param string|null $whatsAppPhoneNumberId
     * @param int|null $vendorId
     * @link https://developers.facebook.com/docs/whatsapp/cloud-api/reference/whatsapp-commerce-settings
     * @return array
     */
    public function updateCommerceSettings($settings, $whatsAppPhoneNumberId = null, $vendorId = null)
    {
        if ($vendorId) {
            $this->vendorId = $vendorId;
        }

        $phoneNumberId = $whatsAppPhoneNumberId ?: $this->getServiceConfiguration('current_phone_number_id');

        if (!$phoneNumberId) {
            throw new Exception(__tr('Phone number ID is required to update commerce settings'), 400);
        }

        // Validate settings structure
        $allowedSettings = ['is_catalog_visible', 'is_cart_enabled'];
        $validatedSettings = [];

        foreach ($allowedSettings as $setting) {
            if (array_key_exists($setting, $settings)) {
                $validatedSettings[$setting] = (bool) $settings[$setting];
            }
        }

        if (empty($validatedSettings)) {
            throw new Exception(__tr('No valid commerce settings provided'), 400);
        }

        return $this->apiPostRequestCommerce("{$phoneNumberId}/whatsapp_commerce_settings", $validatedSettings);
    }

    /**
     * Get Catalog Information
     *
     * @param string|null $catalogId
     * @param int|null $vendorId
     * @link https://developers.facebook.com/docs/marketing-api/catalog
     * @return array
     */
    public function getCatalogInfo($catalogId = null, $vendorId = null)
    {
        if ($vendorId) {
            $this->vendorId = $vendorId;
        }

        if (!$catalogId) {
            // Try to get catalog ID from commerce settings first
            try {
                $commerceSettings = $this->getCommerceSettings(null, $vendorId);
                $catalogId = $commerceSettings['catalog_id'] ?? null;
            } catch (Exception $e) {
                throw new Exception(__tr('Catalog ID is required and could not be retrieved from commerce settings'), 400);
            }
        }

        if (!$catalogId) {
            throw new Exception(__tr('Catalog ID is required to fetch catalog information'), 400);
        }

        return $this->apiGetRequest("{$catalogId}", [
            'fields' => 'name,product_count,vertical'
        ]);
    }

    /**
     * Get Catalog Products
     *
     * @param string|null $catalogId
     * @param array $options
     * @param int|null $vendorId
     * @link https://developers.facebook.com/docs/marketing-api/catalog/products
     * @return array
     */
    public function getCatalogProducts($catalogId = null, $options = [], $vendorId = null)
    {
        if ($vendorId) {
            $this->vendorId = $vendorId;
        }

        if (!$catalogId) {
            // Try to get catalog ID from commerce settings first
            try {
                $commerceSettings = $this->getCommerceSettings(null, $vendorId);
                $catalogId = $commerceSettings['catalog_id'] ?? null;
            } catch (Exception $e) {
                throw new Exception(__tr('Catalog ID is required and could not be retrieved from commerce settings'), 400);
            }
        }

        if (!$catalogId) {
            throw new Exception(__tr('Catalog ID is required to fetch catalog products'), 400);
        }

        $defaultOptions = [
            'fields' => 'id,name,description,price,currency,availability,condition,image_url',
            'limit' => 25
        ];

        $queryParams = array_merge($defaultOptions, $options);

        return $this->apiGetRequest("{$catalogId}/products", $queryParams);
    }

    /**
     * Get Catalog Product by ID
     *
     * @param string $productId
     * @param int|null $vendorId
     * @link https://developers.facebook.com/docs/marketing-api/catalog/products
     * @return array
     */
    public function getCatalogProduct($productId, $vendorId = null)
    {
        if ($vendorId) {
            $this->vendorId = $vendorId;
        }

        if (!$productId) {
            throw new Exception(__tr('Product ID is required to fetch catalog product'), 400);
        }

        // Get catalog ID from commerce settings
        try {
            $commerceSettings = $this->getCommerceSettings(null, $vendorId);
            $catalogId = $commerceSettings['catalog_id'] ?? null;
        } catch (Exception $e) {
            // If commerce settings are not configured, return a fallback
            \Log::debug('Commerce settings not configured for vendor', [
                'vendor_id' => $vendorId,
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);
            return [
                'id' => $productId,
                'name' => "Product #{$productId}",
                'description' => null,
                'price' => null,
                'currency' => null,
                'availability' => null,
                'condition' => null,
                'image_url' => null,
            ];
        }

        if (!$catalogId) {
            // If no catalog ID found, return a fallback
            \Log::debug('No catalog ID found for vendor', [
                'vendor_id' => $vendorId,
                'product_id' => $productId,
            ]);
            return [
                'id' => $productId,
                'name' => "Product #{$productId}",
                'description' => null,
                'price' => null,
                'currency' => null,
                'availability' => null,
                'condition' => null,
                'image_url' => null,
            ];
        }

        try {
            $queryParams = [
                'fields' => 'id,name,description,price,currency,availability,condition,image_url'
            ];

            return $this->apiGetRequest("{$catalogId}/products/{$productId}", $queryParams);
        } catch (Exception $e) {
            // If API call fails, return a fallback
            \Log::warning('Failed to fetch product from WhatsApp API', [
                'vendor_id' => $vendorId,
                'product_id' => $productId,
                'catalog_id' => $catalogId,
                'error' => $e->getMessage(),
            ]);
            return [
                'id' => $productId,
                'name' => "Product #{$productId}",
                'description' => null,
                'price' => null,
                'currency' => null,
                'availability' => null,
                'condition' => null,
                'image_url' => null,
            ];
        }
    }
}
