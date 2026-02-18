<?php

namespace App\Yantrana\Components\BotReply\Services\NodeTypeHandlers;

use Illuminate\Support\Facades\Log;
use App\Yantrana\Components\Contact\Models\ContactModel;
use App\Yantrana\Components\Contact\Repositories\ContactCustomFieldRepository;

/**
 * Handler for question type nodes
 */
class QuestionNodeHandler extends BaseNodeHandler
{
    /**
     * @var ContactCustomFieldRepository
     */
    protected $contactCustomFieldRepository;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->contactCustomFieldRepository = new ContactCustomFieldRepository();
    }
    /**
     * Process the question node
     *
     * @param array $node
     * @param array $context
     * @return array
     */
    public function process($node, $context = [])
    {
        $payload = $node['payload'] ?? [];

        // Get the actual reply text from the bot_replies table using node ID
        $text = $this->getBotReplyText($node['id'], $context);

        // If no reply text found in database, fallback to payload text
        if (empty($text)) {
            $text = $payload['text'] ?? '';
        }

        // Get question settings from database
        $questionData = $this->getQuestionData($node['id'], $context);
        $questionStoreField = $questionData['store_in_field'] ?? null;
        $variableName = $questionData['variable_name'] ?? $payload['variable_name'] ?? null;

        // ============================================
        // AUTO-ASSIGN variable_name if missing
        // ============================================
        if (empty($variableName)) {
            // If variable_name is missing, use store_in_field (input_name from contact_custom_fields)
            $variableName = $questionStoreField ?? null;
        }
        
        // If still empty, generate: "question_" + node ID
        if (empty($variableName)) {
            $variableName = 'question_' . $node['id'];
        }

        // Process dynamic variables including contact variables
        $variables = $this->getAllVariables($context);
        $processedText = $this->processDynamicVariables($text, $variables);

        Log::info('Processing question node', [
            'node_id' => $node['id'],
            'question' => $processedText,
            'question_store_field' => $questionStoreField ?? 'none',
            'variable_name' => $variableName
        ]);

        // Format response for WhatsApp compatibility
        $response = [
            'type' => 'question',
            'text' => $processedText,
            'requires_input' => true,
            'variable_name' => $variableName, // Include variable_name in response
            'question_store_field' => $questionStoreField,
            'node_id' => $node['id'],
            'expects_input' => true,
            'wait_for_input' => true,
            'auto_proceed' => true
        ];

        return $response;
    }

    /**
     * Validate question node payload
     *
     * @param array $payload
     * @return array
     */
    public function validatePayload($payload)
    {
        $errors = [];

        if (empty($payload['text'])) {
            $errors[] = 'Question text is required';
        }

        if (empty($payload['variable_name'])) {
            $errors[] = 'Variable name is required for question nodes';
        } elseif (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $payload['variable_name'])) {
            $errors[] = 'Variable name must be a valid identifier';
        }

        return $errors;
    }

    /**
     * Get the next node ID based on user input and conditional flows
     *
     * @param array $node
     * @param string|null $userInput
     * @param array $context
     * @return string|null
     */
    public function getNextNodeId($node, $userInput = null, $context = [])
    {
        $payload = $node['payload'] ?? [];

        // Get question data from database, fallback to payload
        $questionData = $this->getQuestionData($node['id'], $context);

        // Merge database data with payload data (payload takes precedence for flow structure)
        $conditionalFlows = $payload['conditional_flows'] ?? $questionData['conditional_flows'] ?? [];
        $defaultNext = $payload['default_next_node'] ?? $questionData['default_next_node'] ?? $payload['next_node'] ?? null;

        Log::info('Question node getNextNodeId called', [
            'node_id' => $node['id'],
            'user_input' => $userInput,
            'default_next_from_payload' => $payload['default_next_node'] ?? 'not_set',
            'default_next_from_db' => $questionData['default_next_node'] ?? 'not_set',
            'final_default_next' => $defaultNext,
            'conditional_flows_count' => count($conditionalFlows)
        ]);

        // Get flow builder connections from database and context
        $flowConnections = $context['flow_connections'] ?? [];
        $nodeConnections = $flowConnections[$node['id']] ?? [];

        // Note: Flow connections are now handled through the node-based structure
        // No separate connections table needed
        Log::info('Question node connections from flow structure', [
            'node_id' => $node['id'],
            'node_connections' => $nodeConnections
        ]);

        // Check for standard flow scenarios first
        $standardResult = $this->handleStandardFlowScenarios($node, $userInput, $context);
        if ($standardResult) {
            return $standardResult['next_node'];
        }

        // If no user input provided, return default next node or first connection
        if ($userInput === null) {
            $result = $defaultNext ?? $this->getFirstConnection($nodeConnections);
            Log::info('Question node returning next node (no user input)', [
                'node_id' => $node['id'],
                'result' => $result
            ]);
            return $result;
        }

        // Check conditional flows first
        foreach ($conditionalFlows as $index => $flow) {
            if ($this->evaluateCondition($userInput, $flow)) {
                Log::info('Question node condition matched', [
                    'node_id' => $node['id'],
                    'user_input' => $userInput,
                    'condition' => $flow,
                    'target_node' => $flow['target_node']
                ]);

                // Check if there's a manual connection for this condition
                $conditionConnector = 'condition_' . $index;
                if (isset($nodeConnections[$conditionConnector])) {
                    return $nodeConnections[$conditionConnector];
                }

                // Fallback to configured target node
                return $flow['target_node'];
            }
        }

        // No conditions matched, check for default flow connection
        if (isset($nodeConnections['default_flow'])) {
            Log::info('Question node using manual default connection', [
                'node_id' => $node['id'],
                'user_input' => $userInput,
                'target_node' => $nodeConnections['default_flow']
            ]);
            return $nodeConnections['default_flow'];
        }

        // Check for simple output connection
        if (isset($nodeConnections['simple_output'])) {
            Log::info('Question node using simple output connection', [
                'node_id' => $node['id'],
                'user_input' => $userInput,
                'target_node' => $nodeConnections['simple_output']
            ]);
            return $nodeConnections['simple_output'];
        }

        // Check for no match scenario if no conditions matched and no default connections
        $noMatchResult = $this->handleNoMatchScenario($node, $userInput, $context);
        if ($noMatchResult) {
            return $noMatchResult['next_node'];
        }

        // Fallback to configured default next node
        Log::info('Question node using configured default next node', [
            'node_id' => $node['id'],
            'user_input' => $userInput,
            'default_next' => $defaultNext,
            'conditional_flows_count' => count($conditionalFlows),
            'payload_keys' => array_keys($payload),
            'question_data_keys' => array_keys($questionData)
        ]);

        return $defaultNext;
    }

    /**
     * Get the first available connection from node connections
     *
     * @param array $connections
     * @return string|null
     */
    private function getFirstConnection($connections)
    {
        if (empty($connections)) {
            return null;
        }

        // Priority order: default_flow, simple_output, then any condition
        $priorities = ['default_flow', 'simple_output'];
        
        foreach ($priorities as $priority) {
            if (isset($connections[$priority])) {
                return $connections[$priority];
            }
        }

        // Return first condition connection if no priority matches
        foreach ($connections as $connector => $targetNode) {
            if (strpos($connector, 'condition_') === 0) {
                return $targetNode;
            }
        }

        return null;
    }

    /**
     * Check if this node type requires user input
     *
     * @return bool
     */
    public function requiresUserInput()
    {
        return true;
    }

    /**
     * Get node type identifier
     *
     * @return string
     */
    public function getType()
    {
        return 'question';
    }

    /**
     * Process user input for this question
     *
     * @param array $node
     * @param string $userInput
     * @param array $context
     * @return array
     */
    public function processUserInput($node, $userInput, $context = [])
    {
        $payload = $node['payload'] ?? [];
        $questionData = $this->getQuestionData($node['id'], $context);
        $validationRules = $questionData['validation_rules'] ?? [];
        $inputType = $questionData['input_type'] ?? 'text';
        $isRequired = $questionData['is_required'] ?? true;
        $minLength = isset($validationRules['min_length']) ? (int)$validationRules['min_length'] : 1;
        $maxLength = isset($validationRules['max_length']) ? (int)$validationRules['max_length'] : 500;
        $errorMessage = $questionData['error_message'] ?? null;
        $questionStoreField = $questionData['store_in_field'] ?? null;
        
        // ============================================
        // GET VARIABLE NAME - AUTO-ASSIGN if missing
        // ============================================
        $variableName = $questionData['variable_name'] ?? $payload['variable_name'] ?? null;
        
        // If variable_name is missing, use question_store_field (input_name from contact_custom_fields)
        if (empty($variableName)) {
            $variableName = $questionStoreField;
        }
        
        // If still empty, generate: "question_" + node ID
        if (empty($variableName)) {
            $variableName = 'question_' . $node['id'];
        }

        Log::info('Processing user input for question node', [
            'node_id' => $node['id'],
            'user_input' => $userInput,
            'variable_name' => $variableName,
            'question_store_field' => $questionStoreField,
            'has_payload' => !empty($payload),
            'has_question_data' => !empty($questionData)
        ]);

        // --- VALIDATION ---
        $processedInput = trim((string)$userInput);
        if ($isRequired && $processedInput === '') {
            return [
                'error' => $errorMessage ?: 'This field is required.',
                'context' => $context,
                'next_node' => null
            ];
        }
        
        // ============================================
        // MAP variable_name to contact_custom_fields by input_name
        // ============================================
        $vendorId = $context['vendor_id'] ?? getVendorId();
        $customField = $this->contactCustomFieldRepository->fetchIt([
            'input_name' => $variableName,
            'vendors__id' => $vendorId
        ]);
        
        // If not found by variable_name, try question_store_field as fallback
        if (!$customField && $questionStoreField && $questionStoreField !== $variableName) {
            $customField = $this->contactCustomFieldRepository->fetchIt([
                'input_name' => $questionStoreField,
                'vendors__id' => $vendorId
            ]);
        }
        
        $fieldType = 'text'; // Default field type
        if ($customField) {
            $fieldType = $customField->input_type ?? 'text';
        }
        // Validate user input against the custom field type
        $typeError = null;
        switch ($fieldType) {
            case 'number':
                if ($processedInput !== '' && !is_numeric($processedInput)) {
                    $typeError = $errorMessage ?: 'Please enter a valid number.';
                }
                break;
            case 'email':
                if ($processedInput !== '' && !filter_var($processedInput, FILTER_VALIDATE_EMAIL)) {
                    $typeError = $errorMessage ?: 'Please enter a valid email address.';
                }
                break;
            case 'url':
                if ($processedInput !== '' && !filter_var($processedInput, FILTER_VALIDATE_URL)) {
                    $typeError = $errorMessage ?: 'Please enter a valid URL.';
                }
                break;
            case 'date':
                if ($processedInput !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $processedInput)) {
                    $typeError = $errorMessage ?: 'Please enter a valid date (YYYY-MM-DD).';
                }
                break;
            case 'time':
                if ($processedInput !== '' && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $processedInput)) {
                    $typeError = $errorMessage ?: 'Please enter a valid time (HH:MM or HH:MM:SS).';
                }
                break;
            case 'datetime-local':
                if ($processedInput !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2})?$/', $processedInput)) {
                    $typeError = $errorMessage ?: 'Please enter a valid date and time (YYYY-MM-DDTHH:MM or YYYY-MM-DDTHH:MM:SS).';
                }
                break;
            case 'text':
            default:
                if ($processedInput !== '') {
                    if (mb_strlen($processedInput) < $minLength) {
                        $typeError = $errorMessage ?: 'Input is too short (minimum ' . $minLength . ' characters).';
                    } elseif (mb_strlen($processedInput) > $maxLength) {
                        $typeError = $errorMessage ?: 'Input is too long (maximum ' . $maxLength . ' characters).';
                    }
                }
                break;
        }
        if ($typeError) {
            // Send the question again with error
            return [
                'type' => 'validation_error',
                'error' => $typeError,
                'context' => $context,
                'next_node' => null,
                'repeat_question' => true,
                'question_text' => $this->getBotReplyText($node['id'], $context)
            ];
        }
        
        // ============================================
        // STORE RESPONSE IN FLOW_CONTEXT.VARIABLES
        // ============================================
        // Initialize flow_context if it doesn't exist
        if (!isset($context['flow_context'])) {
            $context['flow_context'] = [];
        }
        if (!isset($context['flow_context']['variables'])) {
            $context['flow_context']['variables'] = [];
        }
        
        // Store the user response in flow_context.variables[variable_name]
        $context['flow_context']['variables'][$variableName] = $processedInput;
        
        Log::info('Stored user response in flow_context.variables', [
            'node_id' => $node['id'],
            'variable_name' => $variableName,
            'value' => $processedInput,
            'flow_context_variables' => $context['flow_context']['variables']
        ]);
        
        // Also store in user_responses for backward compatibility
        $context = $this->storeUserResponse($context, $variableName, $processedInput);

        // ============================================
        // STORE IN contact_custom_field_values
        // ============================================
        if (isset($context['contact']) && $context['contact'] instanceof ContactModel) {
            if ($customField) {
                // Use the custom field found by variable_name mapping
                $this->storeUserResponseInCustomFieldById($context['contact'], $customField->_id, $processedInput, $vendorId);
            } elseif ($questionStoreField) {
                // Fallback: try to save using question_store_field (input_name)
                $this->storeUserResponseInCustomField($context['contact'], $questionStoreField, $processedInput, $vendorId);
            }
        }
        
        // ============================================
        // GET NEXT NODE - Resolve simple_output connection
        // ============================================
        // Get next node using flow connections (this handles simple_output automatically)
        $nextNode = $this->getNextNodeId($node, $processedInput, $context);
        
        Log::info('Completed processing user input for question', [
            'node_id' => $node['id'],
            'variable_name' => $variableName,
            'question_store_field' => $questionStoreField,
            'custom_field_id' => $customField->_id ?? null,
            'raw_input' => $userInput,
            'processed_input' => $processedInput,
            'next_node' => $nextNode,
            'contact_id' => $context['contact']->_id ?? 'unknown',
            'stored_in_flow_context' => true,
            'stored_in_custom_field' => isset($customField)
        ]);
        
        return [
            'context' => $context,
            'next_node' => $nextNode,
            'stored_field' => $questionStoreField,
            'stored_value' => $processedInput,
            'variable_name' => $variableName,
            'processed_input' => $processedInput
        ];
    }

    /**
     * Evaluate a condition against user input
     *
     * @param string $userInput
     * @param array $condition
     * @return bool
     */
    private function evaluateCondition($userInput, $condition)
    {
        $conditionType = $condition['condition_type'] ?? 'equals';
        $conditionValue = $condition['condition_value'] ?? '';

        // Normalize user input for comparison
        $normalizedInput = trim(strtolower($userInput));
        $normalizedValue = trim(strtolower($conditionValue));

        switch ($conditionType) {
            case 'equals':
                return $normalizedInput === $normalizedValue;

            case 'contains':
                return strpos($normalizedInput, $normalizedValue) !== false;

            case 'starts_with':
                return strpos($normalizedInput, $normalizedValue) === 0;

            case 'regex':
                try {
                    return preg_match('/' . $conditionValue . '/i', $userInput) === 1;
                } catch (\Exception $e) {
                    Log::warning('Invalid regex pattern in question condition', [
                        'pattern' => $conditionValue,
                        'error' => $e->getMessage()
                    ]);
                    return false;
                }

            case 'number_range':
                if (!is_numeric($userInput)) {
                    return false;
                }

                $number = (float) $userInput;

                // Parse range like "1-10" or ">=5" or "<100"
                if (preg_match('/^(\d+(?:\.\d+)?)-(\d+(?:\.\d+)?)$/', $conditionValue, $matches)) {
                    $min = (float) $matches[1];
                    $max = (float) $matches[2];
                    return $number >= $min && $number <= $max;
                } elseif (preg_match('/^>=(\d+(?:\.\d+)?)$/', $conditionValue, $matches)) {
                    return $number >= (float) $matches[1];
                } elseif (preg_match('/^<=(\d+(?:\.\d+)?)$/', $conditionValue, $matches)) {
                    return $number <= (float) $matches[1];
                } elseif (preg_match('/^>(\d+(?:\.\d+)?)$/', $conditionValue, $matches)) {
                    return $number > (float) $matches[1];
                } elseif (preg_match('/^<(\d+(?:\.\d+)?)$/', $conditionValue, $matches)) {
                    return $number < (float) $matches[1];
                } elseif (is_numeric($conditionValue)) {
                    return $number == (float) $conditionValue;
                }

                return false;

            default:
                Log::warning('Unknown condition type in question node', [
                    'condition_type' => $conditionType,
                    'condition_value' => $conditionValue
                ]);
                return false;
        }
    }

    /**
     * Get question data from database
     *
     * @param string $nodeId
     * @param array $context
     * @return array
     */
    private function getQuestionData($nodeId, $context = [])
    {
        try {
            // Get vendor ID from context or fallback to global function
            $vendorId = $context['vendor_id'] ?? getVendorId();

            if (!$vendorId) {
                return [];
            }

            // The node ID corresponds to the _uid field in bot_replies table
            $botReply = $this->botReplyRepository->fetchIt([
                '_uid' => $nodeId,
                'vendors__id' => $vendorId
            ]);

            if (__isEmpty($botReply)) {
                return [];
            }

            return $botReply->__data['question_message'] ?? [];
        } catch (\Exception $e) {
            Log::error('Failed to get question data', [
                'node_id' => $nodeId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Store user response in contact's custom field by field ID
     *
     * @param ContactModel $contact
     * @param string|int $customFieldId
     * @param string $userInput
     * @param int|null $vendorId
     * @return void
     */
    private function storeUserResponseInCustomFieldById($contact, $customFieldId, $userInput, $vendorId = null)
    {
        try {
            // Use vendorId from parameter or fallback to global function
            $vendorId = $vendorId ?? getVendorId();
            
            if (!$vendorId) {
                Log::error('No vendor ID available for storing custom field value', [
                    'contact_id' => $contact->_id,
                    'custom_field_id' => $customFieldId
                ]);
                return;
            }

            // Check if a custom field value already exists for this contact and field
            $existingValue = \App\Yantrana\Components\Contact\Models\ContactCustomFieldValueModel::where([
                'contacts__id' => $contact->_id,
                'contact_custom_fields__id' => $customFieldId
            ])->first();

            if ($existingValue) {
                // Update existing value
                $existingValue->field_value = $userInput;
                $result = $existingValue->save();
                
                Log::info('Updated existing custom field value', [
                    'contact_id' => $contact->_id,
                    'custom_field_id' => $customFieldId,
                    'old_value' => $existingValue->getOriginal('field_value'),
                    'new_value' => $userInput,
                    'result' => $result ? 'success' : 'failed'
                ]);
            } else {
                // Create new value
                $newValue = new \App\Yantrana\Components\Contact\Models\ContactCustomFieldValueModel();
                $newValue->contacts__id = $contact->_id;
                $newValue->contact_custom_fields__id = $customFieldId;
                $newValue->field_value = $userInput;
                $result = $newValue->save();
                
                Log::info('Created new custom field value', [
                    'contact_id' => $contact->_id,
                    'custom_field_id' => $customFieldId,
                    'value' => $userInput,
                    'result' => $result ? 'success' : 'failed'
                ]);
            }

            if (!$result) {
                Log::error('Failed to store custom field value in database', [
                    'contact_id' => $contact->_id,
                    'custom_field_id' => $customFieldId,
                    'value' => $userInput,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error storing user response in custom field by ID', [
                'contact_id' => $contact->_id,
                'custom_field_id' => $customFieldId,
                'value' => $userInput,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Store user response in contact's custom field by field name
     *
     * @param ContactModel $contact
     * @param string $customFieldName
     * @param string $userInput
     * @param int|null $vendorId
     * @return void
     */
    private function storeUserResponseInCustomField($contact, $customFieldName, $userInput, $vendorId = null)
    {
        try {
            // Use vendorId from parameter or fallback to global function
            $vendorId = $vendorId ?? getVendorId();
            
            if (!$vendorId) {
                Log::error('No vendor ID available for storing custom field value', [
                    'contact_id' => $contact->_id,
                    'custom_field_name' => $customFieldName
                ]);
                return;
            }

            // Find the custom field by name to get its ID
            $customField = $this->contactCustomFieldRepository->fetchIt([
                'input_name' => $customFieldName,
                'vendors__id' => $vendorId
            ]);

            if (!$customField) {
                Log::error('Custom field not found by name', [
                    'custom_field_name' => $customFieldName,
                    'vendor_id' => $vendorId,
                    'contact_id' => $contact->_id
                ]);
                return;
            }

            // Use the ID-based method to store the value
            $this->storeUserResponseInCustomFieldById($contact, $customField->_id, $userInput, $vendorId);

        } catch (\Exception $e) {
            Log::error('Error storing user response in custom field by name', [
                'contact_id' => $contact->_id,
                'custom_field_name' => $customFieldName,
                'value' => $userInput,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Validate node configuration
     *
     * @param array $node
     * @return array
     */
    public function validateNode($node)
    {
        $errors = [];
        $payload = $node['payload'] ?? [];

        if (empty($payload['text'])) {
            $errors[] = 'Question text is required';
        }

        if (empty($payload['variable_name'])) {
            $errors[] = 'Variable name is required';
        } elseif (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $payload['variable_name'])) {
            $errors[] = 'Variable name must be a valid identifier';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get the next node ID for a specific choice
     *
     * @param array $node
     * @param mixed $choice
     * @param int $index
     * @return string|null
     */
    private function getNextNodeForChoice($node, $choice, $index, $context = [])
    {
        $payload = $node['payload'] ?? [];
        $questionData = $this->getQuestionData($node['id'], $context);

        // Check if choice has specific next node
        if (is_array($choice) && isset($choice['next_node'])) {
            return $choice['next_node'];
        }

        // Note: Flow connections are now handled through the node-based structure
        Log::info('Looking for choice connection in flow structure', [
            'node_id' => $node['id'],
            'choice_index' => $index
        ]);

        // Check conditional flows for this choice
        $conditionalFlows = $payload['conditional_flows'] ?? $questionData['conditional_flows'] ?? [];
        foreach ($conditionalFlows as $flow) {
            if (isset($flow['choice_index']) && $flow['choice_index'] === $index) {
                return $flow['target_node'];
            }
        }

        // Note: Flow connections are now handled through the node-based structure
        Log::info('Using flow structure for default connection', [
            'node_id' => $node['id']
        ]);

        // Final fallback to configured next node
        return $payload['next_node'] ?? $questionData['default_next_node'] ?? null;
    }
}