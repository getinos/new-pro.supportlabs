<?php

namespace App\Yantrana\Components\BotReply\Services;

use App\Yantrana\Components\BotReply\Services\FlowExecutionService;
use App\Yantrana\Components\BotReply\Services\FlowNodeService;
use App\Yantrana\Components\BotReply\Models\BotFlowModel;
use App\Models\UserActiveFlow;
use Illuminate\Support\Facades\Log;

/**
 * Service to integrate new node-based flows with existing WhatsApp flow system
 */
class FlowIntegrationService
{
    /**
     * @var FlowExecutionService
     */
    private $flowExecutionService;

    /**
     * @var FlowNodeService
     */
    private $flowNodeService;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->flowExecutionService = new FlowExecutionService();
        $this->flowNodeService = new FlowNodeService();
    }

    /**
     * Process bot flow using new node-based system if available
     *
     * @param object $contact
     * @param string $messageBody
     * @param object $botReply
     * @param array $options
     * @return array|null
     */
    public function processNodeBasedFlow($contact, $messageBody, $botReply, $options = [])
    {
        // Check if this bot reply belongs to a flow
        if (!$botReply->bot_flows__id) {
            return null;
        }

        $botFlow = BotFlowModel::find($botReply->bot_flows__id);
        if (!$botFlow) {
            return null;
        }

        // Prioritize new flow structure, fallback to legacy if needed
        $flowData = $botFlow->getFlowNodesData();
        if (!$flowData && $botFlow->usesLegacyFlowStructure()) {
            // Convert legacy to new format on-the-fly for processing
            $legacyData = $botFlow->getFlowBuilderData();
            if ($legacyData) {
                $botFlowEngine = app(\App\Yantrana\Components\BotReply\BotFlowEngine::class);
                $flowData = $botFlowEngine->convertToNewFlowStructure($legacyData, $botFlow->_uid);

                Log::info('Using converted legacy flow data', [
                    'flow_id' => $botFlow->_uid,
                    'node_count' => count($flowData['nodes'] ?? [])
                ]);
            }
        }

        if (!$flowData || empty($flowData['nodes'])) {
            Log::warning('No valid flow data found', [
                'flow_id' => $botFlow->_uid,
                'has_legacy' => $botFlow->usesLegacyFlowStructure(),
                'has_new' => $botFlow->usesNewFlowStructure()
            ]);
            return null;
        }

        Log::info('Processing node-based flow', [
            'user' => $contact->wa_id,
            'flow_id' => $flowData['flow_id'],
            'message' => $messageBody,
            'trigger' => $botReply->reply_trigger,
            'node_count' => count($flowData['nodes'] ?? []),
            'first_node_id' => $flowData['nodes'][0]['id'] ?? 'none',
            'bot_reply_id' => $botReply->_uid
        ]);

        // Get or create active flow context
        $activeFlow = UserActiveFlow::getActiveFlow($contact->wa_id);
        $context = $this->getFlowContext($activeFlow, $contact);
        
        // ============================================
        // Extract flow connections from flowData and add to context
        // ============================================
        $flowConnections = $this->extractFlowConnections($flowData);
        $context['flow_connections'] = $flowConnections;

        // ============================================
        // PRIORITY -1: Check if this is a flow response (nfm_reply) from a flow node
        // This must happen before checking questions or start triggers
        // ============================================
        if ($activeFlow && $activeFlow->flow_id == $botReply->bot_flows__id) {
            $currentNodeId = $activeFlow->__data['current_node_id'] ?? null;
            $isWaitingForInput = $activeFlow->__data['waiting_for_input'] ?? false;
            
            if ($currentNodeId && $isWaitingForInput) {
                $currentNode = $this->flowNodeService->findNodeById($flowData, $currentNodeId);
                // If current node is a flow node, this is a flow response - continue to next node
                if ($currentNode && ($currentNode['type'] === 'flow' || $currentNode['type'] === 'flow_message_reply')) {
                    Log::info('Detected flow response (nfm_reply), continuing flow to next node', [
                        'user' => $contact->wa_id,
                        'current_node' => $currentNodeId,
                        'node_type' => $currentNode['type'],
                        'message' => $messageBody
                    ]);
                    
                    // ============================================
                    // TRIGGER AI BOT REPLY BEFORE CONTINUING FLOW
                    // ============================================
                    $this->triggerAiBotForFlowSubmission($contact, $messageBody, $options);
                    
                    // Continue flow from next node - the flow response means user submitted, so move to next
                    $nextNodeId = $currentNode['payload']['next_node'] ?? null;
                    if ($nextNodeId) {
                        // Get the next node to log its type
                        $nextNode = $this->flowNodeService->findNodeById($flowData, $nextNodeId);
                        Log::info('Continuing flow after flow node response', [
                            'user' => $contact->wa_id,
                            'previous_flow_node' => $currentNodeId,
                            'next_node_id' => $nextNodeId,
                            'next_node_type' => $nextNode['type'] ?? 'unknown',
                            'next_node_payload_keys' => array_keys($nextNode['payload'] ?? [])
                        ]);
                        
                        $result = $this->flowExecutionService->executeFlow(
                            $flowData,
                            $nextNodeId,
                            $context
                        );
                        
                        Log::info('Flow execution result after flow node', [
                            'user' => $contact->wa_id,
                            'response_count' => count($result['responses'] ?? []),
                            'response_types' => array_column($result['responses'] ?? [], 'type'),
                            'is_complete' => $result['is_complete'] ?? false,
                            'current_node_id' => $result['current_node_id'] ?? null
                        ]);
                        
                        $this->updateActiveFlowState($activeFlow, $result, $contact);
                        
                        if ($result['is_complete'] ?? false) {
                            UserActiveFlow::where('phone_number', $contact->wa_id)->delete();
                        }
                        
                        return $this->formatFlowResult($result, $contact, $options);
                    } else {
                        // No next node - complete the flow
                        Log::info('Flow node has no next node, completing flow', [
                            'user' => $contact->wa_id,
                            'current_node' => $currentNodeId
                        ]);
                        UserActiveFlow::where('phone_number', $contact->wa_id)->delete();
                        
                        // Return empty result to indicate flow completed (but AI was already sent)
                        return [
                            'responses' => [],
                            'current_node_id' => null,
                            'context' => $context,
                            'is_complete' => true,
                            'processed_by_new_flow' => true,
                            'ai_reply_sent' => true // Flag to indicate AI was sent
                        ];
                    }
                }
            }
        }

        // ============================================
        // PRIORITY 0: Check for active_question BEFORE checking start triggers
        // ============================================
        $flowContext = $context['flow_context'] ?? [];
        $activeQuestion = $flowContext['active_question'] ?? null;
        
        if ($activeQuestion && $activeFlow && $activeFlow->flow_id == $botReply->bot_flows__id) {
            $currentNodeId = $activeQuestion['node_id'] ?? $activeFlow->__data['current_node_id'] ?? null;
            $variableName = $activeQuestion['variable_name'] ?? null;
            
            Log::info('QUESTION_REPLY_DETECTED', [
                'user' => $contact->wa_id,
                'input_message' => $messageBody,
                'current_node_id' => $currentNodeId,
                'variable_name' => $variableName,
                'active_question' => $activeQuestion
            ]);
            
            if ($currentNodeId) {
                // Get the node to verify it's a question node
                $node = $this->flowNodeService->findNodeById($flowData, $currentNodeId);
                
                if ($node && ($node['type'] === 'question' || isset($flowContext['active_question']))) {
                    Log::info('FORWARDING TO processUserInput()', [
                        'user' => $contact->wa_id,
                        'node_id' => $currentNodeId,
                        'node_type' => $node['type'] ?? 'unknown',
                        'variable_name' => $variableName,
                        'has_flow_connections' => !empty($flowConnections)
                    ]);
                    
                    // Route directly to processUserInput()
                    $result = $this->flowExecutionService->continueFlowWithInput(
                        $flowData,
                        $currentNodeId,
                        $messageBody,
                        $context
                    );
                    
                    // Clear active_question after processing and log variable storage
                    if (isset($result['context']['flow_context']['variables'][$variableName])) {
                        // Clear active_question since we've processed the input
                        if (isset($result['context']['flow_context']['active_question'])) {
                            unset($result['context']['flow_context']['active_question']);
                        }
                        
                        Log::info('VARIABLE STORED SUCCESSFULLY', [
                            'user' => $contact->wa_id,
                            'variable_name' => $variableName,
                            'value' => $result['context']['flow_context']['variables'][$variableName] ?? 'unknown',
                            'stored_in_flow_context' => true
                        ]);
                    }
                    
                    $nextNodeId = $result['next_node'] ?? $result['current_node_id'] ?? null;
                    if ($nextNodeId) {
                        Log::info('NEXT NODE X RESOLVED', [
                            'user' => $contact->wa_id,
                            'next_node' => $nextNodeId,
                            'current_node' => $currentNodeId,
                            'variable_name' => $variableName,
                            'response_count' => count($result['responses'] ?? []),
                            'is_complete' => $result['is_complete'] ?? false
                        ]);
                    } else {
                        Log::warning('No next node found after processing question answer', [
                            'user' => $contact->wa_id,
                            'current_node' => $currentNodeId,
                            'variable_name' => $variableName,
                            'result_keys' => array_keys($result)
                        ]);
                    }
                    
                    // Update active flow state (this will clear active_question from flow_context)
                    $this->updateActiveFlowState($activeFlow, $result, $contact);
                    
                    // Check if flow is complete and clean up
                    if ($result['is_complete'] ?? false) {
                        Log::info('Flow completed after question answer', [
                            'user' => $contact->wa_id,
                            'flow_id' => $botReply->bot_flows__id,
                            'final_node' => $result['current_node_id'],
                            'response_count' => count($result['responses'] ?? [])
                        ]);
                        
                        // Delete the active flow session
                        UserActiveFlow::where('phone_number', $contact->wa_id)->delete();
                    } else {
                        Log::info('Flow continuing after question answer', [
                            'user' => $contact->wa_id,
                            'current_node' => $result['current_node_id'],
                            'response_count' => count($result['responses'] ?? []),
                            'waiting_for_input' => !empty($result['responses']) && ($result['responses'][0]['requires_input'] ?? false)
                        ]);
                    }
                    
                    // Format and return response (use formatFlowResult, not formatFlowResponse)
                    return $this->formatFlowResult($result, $contact, $options);
                }
            }
        }

        // Check if this is a flow start trigger (always check, even if there's an active flow)
        $isFlowStartTrigger = false;
        $triggerType = $botFlow->trigger_type ?? 'is';

        // For welcome and new_message triggers, we don't need a start_trigger set
        if (in_array($triggerType, ['welcome', 'new_message'])) {
            $isFlowStartTrigger = $this->isFlowStartTrigger($messageBody, $botReply, $botFlow, $contact);
        } elseif ($botFlow->start_trigger) {
            // For other trigger types, check the start_trigger
            $isFlowStartTrigger = $this->isFlowStartTrigger($messageBody, $botReply, $botFlow, $contact);
        } else {
            Log::info('Flow has no start_trigger set and is not a special trigger type', [
                'user' => $contact->wa_id,
                'flow_id' => $botFlow->_uid,
                'trigger_type' => $triggerType,
                'message' => $messageBody
            ]);
        }

        // Check if we have an active flow waiting for input
        $isWaitingForInput = $activeFlow && isset($activeFlow->__data['waiting_for_input']) && $activeFlow->__data['waiting_for_input'];

        Log::info('Flow trigger analysis', [
            'user' => $contact->wa_id,
            'is_start_trigger' => $isFlowStartTrigger,
            'message' => $messageBody,
            'reply_trigger' => $botReply->reply_trigger,
            'flow_start_trigger' => $botFlow->start_trigger ?? 'none',
            'trigger_type' => $triggerType,
            'has_active_flow' => !is_null($activeFlow),
            'waiting_for_input' => $isWaitingForInput,
            'active_flow_id' => $activeFlow ? $activeFlow->flow_id : null,
            'current_flow_id' => $botReply->bot_flows__id,
            'all_button_texts' => $this->getAllButtonTextsFromFlow($flowData)
        ]);

        // PRIORITY 1: If this is a flow start trigger, restart the flow
        // Start triggers should always restart the flow, even if user is waiting for input
        // This allows users to restart a flow by sending the trigger again
        if ($isFlowStartTrigger) {
            // Clear existing flow (whether same or different)
            if ($activeFlow) {
                if ($activeFlow->flow_id == $botReply->bot_flows__id) {
                    Log::info('Restarting flow from start trigger (same flow, resetting)', [
                        'user' => $contact->wa_id,
                        'trigger' => $messageBody,
                        'flow_id' => $activeFlow->flow_id,
                        'was_waiting_for_input' => $isWaitingForInput
                    ]);
                } else {
                    Log::info('Starting new flow from trigger (different flow)', [
                        'user' => $contact->wa_id,
                        'trigger' => $messageBody,
                        'old_flow_id' => $activeFlow->flow_id,
                        'new_flow_id' => $botReply->bot_flows__id
                    ]);
                }
                // Clear existing flow
                UserActiveFlow::where('phone_number', $contact->wa_id)->delete();
                $activeFlow = null;
            } else {
                Log::info('Starting new flow from trigger (no active flow)', [
                    'user' => $contact->wa_id,
                    'trigger' => $messageBody,
                    'flow_id' => $botReply->bot_flows__id
                ]);
            }

            // Refresh context after clearing active flow (to get fresh context)
            $context = $this->getFlowContext(null, $contact);

            // Create new active flow
            $activeFlow = UserActiveFlow::setActiveFlow(
                $contact->_id,
                $botReply->bot_flows__id,
                $contact->wa_id
            );

            // Find the first node in the flow
            $firstNode = $this->flowExecutionService->getFirstNode($flowData);
            if (!$firstNode) {
                Log::error('No first node found in flow', [
                    'user' => $contact->wa_id,
                    'flow_id' => $flowData['flow_id']
                ]);
                return null;
            }

            $currentNodeId = $firstNode['id'];

            // Initialize flow context for new node structure
            $activeFlowData = $activeFlow->__data ?? [];
            $activeFlowData['current_node_id'] = $currentNodeId;
            $activeFlowData['flow_context'] = $context;
            $activeFlowData['waiting_for_input'] = false;
            $activeFlow->__data = $activeFlowData;
            $activeFlow->save();

            // Start flow execution from first node
            $result = $this->flowExecutionService->executeFlow(
                $flowData,
                $currentNodeId,
                $context
            );
            
            Log::info('Flow restarted and executed', [
                'user' => $contact->wa_id,
                'flow_id' => $botReply->bot_flows__id,
                'first_node_id' => $currentNodeId,
                'response_count' => count($result['responses'] ?? []),
                'has_responses' => !empty($result['responses']),
                'response_types' => array_column($result['responses'] ?? [], 'type'),
                'is_complete' => $result['is_complete'] ?? false
            ]);
        }
        // PRIORITY 2: Check if this is user input for an active flow waiting for input
        // Only process as input if it's NOT a start trigger (already handled above)
        elseif ($isWaitingForInput && $activeFlow && $activeFlow->flow_id == $botReply->bot_flows__id) {
            Log::info('Processing user input for active flow', [
                'user' => $contact->wa_id,
                'input' => $messageBody,
                'current_node' => $activeFlow->__data['current_node_id'] ?? 'unknown'
            ]);

            // This is user input - use the FlowExecutionService to continue the flow with input
            $currentNodeId = $activeFlow->__data['current_node_id'];
            
            // Get updated context from active flow
            $context = $this->getFlowContext($activeFlow, $contact);
            
            // Extract and add flow connections to context
            $flowConnections = $this->extractFlowConnections($flowData);
            $context['flow_connections'] = $flowConnections;
            
            Log::info('About to continue flow with user input', [
                'user' => $contact->wa_id,
                'current_node' => $currentNodeId,
                'input' => $messageBody,
                'context_keys' => array_keys($context),
                'flow_id' => $flowData['flow_id'] ?? 'unknown',
                'has_flow_connections' => !empty($flowConnections)
            ]);

            // Use continueFlowWithInput to process input and continue execution
            $result = $this->flowExecutionService->continueFlowWithInput(
                $flowData,
                $currentNodeId,
                $messageBody,
                $context
            );

            Log::info('Flow continued after user input', [
                'user' => $contact->wa_id,
                'current_node' => $currentNodeId,
                'next_node' => $result['current_node_id'] ?? 'none',
                'response_count' => count($result['responses'] ?? []),
                'has_error' => isset($result['error']),
                'is_complete' => $result['is_complete'] ?? false
            ]);

            // Check for error - but only return null if there are no responses
            // (Interactive nodes may return responses even on error to re-send the message)
            if (isset($result['error']) && empty($result['responses'])) {
                Log::error('Error processing user input with no responses', [
                    'user' => $contact->wa_id,
                    'current_node' => $currentNodeId,
                    'input' => $messageBody,
                    'error' => $result['error']
                ]);
                return null;
            }
            
            // If there's an error but responses exist (e.g., re-sending interactive message), log it but continue
            if (isset($result['error']) && !empty($result['responses'])) {
                Log::warning('Error processing user input, but responses available', [
                    'user' => $contact->wa_id,
                    'current_node' => $currentNodeId,
                    'input' => $messageBody,
                    'error' => $result['error'],
                    'response_count' => count($result['responses'])
                ]);
            }

            Log::info('Successfully processed user input and continued flow', [
                'user' => $contact->wa_id,
                'current_node' => $currentNodeId,
                'input' => $messageBody,
                'new_current_node' => $result['current_node_id'],
                'response_count' => count($result['responses'] ?? []),
                'is_complete' => $result['is_complete'] ?? false
            ]);
        }
        // PRIORITY 3: Button-map triggered flows (even when not waiting_for_input)
        elseif (
            !$isWaitingForInput &&
            $activeFlow &&
            ($buttonMapTarget = $this->getButtonMapTarget($messageBody, $context))
        ) {
            Log::info('BUTTON_MAP_TRIGGER_START', [
                'user' => $contact->wa_id,
                'payload_raw' => $messageBody,
                'payload_normalized' => strtolower(trim($messageBody)),
                'target_node' => $buttonMapTarget,
                'flow_id' => $activeFlow->flow_id,
                'button_map_keys' => array_keys($context['button_map'] ?? [])
            ]);

            // Refresh context to ensure latest data before executing mapped path
            $context = $this->getFlowContext($activeFlow, $contact);
            $context['flow_connections'] = $flowConnections;

            $currentNodeId = $buttonMapTarget;

            $result = $this->flowExecutionService->executeFlow(
                $flowData,
                $currentNodeId,
                $context
            );

            Log::info('BUTTON_MAP_TRIGGER_DONE', [
                'user' => $contact->wa_id,
                'start_node' => $currentNodeId,
                'response_count' => count($result['responses'] ?? []),
                'is_complete' => $result['is_complete'] ?? false,
                'next_current_node' => $result['current_node_id'] ?? null
            ]);
        } else {
            // If we reach here, it means:
            // 1. Not a flow start trigger
            // 2. Not waiting for input in active flow (or different flow)
            // Therefore, we should NOT process this message as a flow
            Log::info('Message does not qualify for flow processing', [
                'user' => $contact->wa_id,
                'message' => $messageBody,
                'reason' => 'Not a start trigger and not waiting for input',
                'flow_start_trigger' => $botFlow->start_trigger ?? 'not_set',
                'has_active_flow' => !is_null($activeFlow),
                'waiting_for_input' => $isWaitingForInput
            ]);

            return null;
        }

        // Update active flow state
        $this->updateActiveFlowState($activeFlow, $result, $contact);

        // Check if flow is complete and clean up
        if ($result['is_complete'] ?? false) {
            $hasButtonMap = !empty($result['context']['button_map'] ?? []);

            Log::info('Flow completed, assessing cleanup', [
                'user' => $contact->wa_id,
                'flow_id' => $flowData['flow_id'],
                'final_node' => $result['current_node_id'],
                'response_count' => count($result['responses'] ?? []),
                'has_button_map' => $hasButtonMap,
                'button_map_keys' => array_keys($result['context']['button_map'] ?? [])
            ]);

            if ($hasButtonMap && $activeFlow) {
                // Preserve session but clear waiting flag so button clicks still work
                $activeFlowData = $activeFlow->__data ?? [];
                $activeFlowData['waiting_for_input'] = false;
                $activeFlowData['current_node_id'] = null;
                $activeFlowData['flow_context'] = $result['context'] ?? ($activeFlowData['flow_context'] ?? []);
                $activeFlow->__data = $activeFlowData;
                $activeFlow->save();

                Log::info('Preserved session for button_map reuse', [
                    'user' => $contact->wa_id,
                    'flow_id' => $flowData['flow_id'],
                    'button_map_keys' => array_keys($result['context']['button_map'] ?? [])
                ]);
            } else {
                // Delete the active flow session
                $deletedCount = UserActiveFlow::where('phone_number', $contact->wa_id)->delete();

                Log::info('Active flow session cleaned up', [
                    'user' => $contact->wa_id,
                    'deleted_sessions' => $deletedCount
                ]);
            }
        } else {
            Log::info('Flow continuing, session maintained', [
                'user' => $contact->wa_id,
                'current_node' => $result['current_node_id'],
                'waiting_for_input' => !empty($result['responses']) && ($result['responses'][0]['requires_input'] ?? false)
            ]);
        }

        return $this->formatFlowResult($result, $contact, $options);
    }

    /**
     * Get flow context from active flow and contact
     *
     * @param object|null $activeFlow
     * @param object $contact
     * @return array
     */
    private function getFlowContext($activeFlow, $contact)
    {
        // Start with basic context
        $context = [
            'contact' => $contact,
            'contact_id' => $contact->_id,
            'wa_id' => $contact->wa_id,
            'vendor_id' => $contact->vendors__id,
            'timestamp' => time(),
            'debug_info' => []
        ];

        // Add existing context from active flow if available
        if ($activeFlow && isset($activeFlow->__data['flow_context'])) {
            $existingContext = $activeFlow->__data['flow_context'];
            $context = array_merge($existingContext, $context);
            
            // Log context retrieval for debugging
            Log::info('Retrieved existing flow context', [
                'user' => $contact->wa_id,
                'flow_id' => $activeFlow->flow_id,
                'context_keys' => array_keys($context),
                'has_stored_variables' => !empty(array_diff(array_keys($context), ['contact', 'contact_id', 'wa_id', 'vendor_id', 'timestamp', 'debug_info'])),
                'has_active_interactive_nodes' => isset($context['active_interactive_nodes']),
                'active_nodes_count' => count($context['active_interactive_nodes'] ?? [])
            ]);
        }

        return $context;
    }

    /**
     * Determine current node ID based on flow state
     *
     * @param object|null $activeFlow
     * @param array $flowData
     * @param string $messageBody
     * @param object $botReply
     * @return string|null
     */
    private function getCurrentNodeId($activeFlow, $flowData, $messageBody, $botReply)
    {
        // If there's an active flow with current node, use it
        if ($activeFlow && isset($activeFlow->__data['current_node_id'])) {
            return $activeFlow->__data['current_node_id'];
        }

        // If this is a flow start, find the first node
        $firstNode = $this->flowExecutionService->getFirstNode($flowData);
        return $firstNode ? $firstNode['id'] : null;
    }

    /**
     * Find the node that should be triggered by the user's message
     *
     * @param array $flowData
     * @param string $messageBody
     * @param object|null $activeFlow
     * @return string|null
     */
    private function findNodeByUserInput($flowData, $messageBody, $activeFlow)
    {
        $nodes = $flowData['nodes'] ?? [];
        $messageBody = trim($messageBody);

        // If we have an active flow and waiting for input, find the next node based on user selection
        if ($activeFlow && isset($activeFlow->__data['current_node_id']) && isset($activeFlow->__data['waiting_for_input'])) {
            $currentNodeId = $activeFlow->__data['current_node_id'];
            $currentNode = $this->flowNodeService->findNodeById($flowData, $currentNodeId);
            
            Log::info('Finding next node based on user input', [
                'current_node' => $currentNodeId,
                'user_input' => $messageBody,
                'node_type' => $currentNode['type'] ?? 'unknown'
            ]);

            // For interactive nodes with buttons
            if ($currentNode && $currentNode['type'] === 'interactive' && isset($currentNode['payload']['buttons'])) {
                $buttons = $currentNode['payload']['buttons'];
                
                Log::info('Checking button matches', [
                    'user_input' => $messageBody,
                    'available_buttons' => array_column($buttons, 'title'),
                    'button_count' => count($buttons)
                ]);
                
                foreach ($buttons as $button) {
                    // Match by title (case insensitive) or by button ID
                    $titleMatch = strtolower(trim($button['title'])) === strtolower(trim($messageBody));
                    $idMatch = (string)$button['id'] === (string)$messageBody;
                    
                    // Also check for partial matches or numeric selection
                    $numericMatch = false;
                    if (is_numeric($messageBody) && isset($buttons[intval($messageBody)-1])) {
                        $numericMatch = true;
                        $button = $buttons[intval($messageBody)-1];
                    }

                    if ($titleMatch || $idMatch || $numericMatch) {
                        Log::info('Button match found', [
                            'matched_button' => $button,
                            'next_node' => $button['next_node'],
                            'match_type' => $titleMatch ? 'title' : ($idMatch ? 'id' : 'numeric')
                        ]);
                        return $button['next_node'] ?? null;
                    }
                }

                Log::warning('No button match found', [
                    'user_input' => $messageBody,
                    'available_buttons' => array_column($buttons, 'title'),
                    'button_ids' => array_column($buttons, 'id'),
                    'current_node' => $currentNodeId
                ]);
            }
        }
        
        return null;
    }

    /**
     * Start a flow directly from a button trigger without requiring an active session.
     *
     * @param object $contact
     * @param string $flowId
     * @param string $nodeId
     * @param array $options
     * @return array|null
     */
    public function startFlowFromButtonTrigger($contact, $flowId, $nodeId, $options = [])
    {
        $botFlow = BotFlowModel::find($flowId);
        if (!$botFlow) {
            Log::warning('BUTTON_MAP_FLOW_START_FAILED', [
                'reason' => 'flow_not_found',
                'flow_id' => $flowId,
                'node_id' => $nodeId,
                'wa_id' => $contact->wa_id ?? null
            ]);
            return null;
        }

        // Load flow data (new structure or converted legacy)
        $flowData = $botFlow->getFlowNodesData();
        if (!$flowData && $botFlow->usesLegacyFlowStructure()) {
            $legacyData = $botFlow->getFlowBuilderData();
            if ($legacyData) {
                $botFlowEngine = app(\App\Yantrana\Components\BotReply\BotFlowEngine::class);
                $flowData = $botFlowEngine->convertToNewFlowStructure($legacyData, $botFlow->_uid);
            }
        }

        if (!$flowData || empty($flowData['nodes'])) {
            Log::warning('BUTTON_MAP_FLOW_START_FAILED', [
                'reason' => 'flow_data_missing',
                'flow_id' => $flowId,
                'node_id' => $nodeId,
                'wa_id' => $contact->wa_id ?? null
            ]);
            return null;
        }

        $context = $this->getFlowContext(null, $contact);
        $flowConnections = $this->extractFlowConnections($flowData);
        $context['flow_connections'] = $flowConnections;

        Log::info('BUTTON_MAP_FLOW_START', [
            'flow_id' => $flowId,
            'node_id' => $nodeId,
            'wa_id' => $contact->wa_id ?? null
        ]);

        // Get or create active flow session for this contact
        $activeFlow = \App\Models\UserActiveFlow::getActiveFlow($contact->wa_id);
        
        // If no active flow exists, or if it's a different flow, create/update it
        if (!$activeFlow) {
            // No active flow - create new one
            $activeFlow = \App\Models\UserActiveFlow::setActiveFlow(
                $contact->_id,
                $flowId,
                $contact->wa_id
            );
            
            Log::info('BUTTON_MAP_CREATED_NEW_ACTIVE_FLOW', [
                'wa_id' => $contact->wa_id,
                'flow_id' => $flowId
            ]);
        } elseif ($activeFlow->flow_id != $flowId) {
            // Different flow - replace it
            $oldFlowId = $activeFlow->flow_id;
            $activeFlow = \App\Models\UserActiveFlow::setActiveFlow(
                $contact->_id,
                $flowId,
                $contact->wa_id
            );
            
            Log::info('BUTTON_MAP_REPLACED_ACTIVE_FLOW', [
                'wa_id' => $contact->wa_id,
                'old_flow_id' => $oldFlowId,
                'new_flow_id' => $flowId
            ]);
        } else {
            // Same flow - update existing session
            Log::info('BUTTON_MAP_UPDATING_EXISTING_ACTIVE_FLOW', [
                'wa_id' => $contact->wa_id,
                'flow_id' => $flowId,
                'old_current_node' => $activeFlow->__data['current_node_id'] ?? null
            ]);
        }

        $result = $this->flowExecutionService->executeFlow(
            $flowData,
            $nodeId,
            $context
        );

        $result['processed_by_new_flow'] = true;
        $result['flow_id'] = $flowData['flow_id'] ?? $flowId;

        // CRITICAL: Update the active flow session with the execution result
        // This ensures that when user sends text input, it processes against the correct node
        if ($activeFlow) {
            $this->updateActiveFlowState($activeFlow, $result, $contact);
            
            Log::info('BUTTON_MAP_UPDATED_ACTIVE_FLOW_STATE', [
                'wa_id' => $contact->wa_id,
                'flow_id' => $flowId,
                'current_node_id' => $result['current_node_id'] ?? null,
                'is_complete' => $result['is_complete'] ?? false,
                'waiting_for_input' => !empty($result['responses']) && 
                    array_reduce($result['responses'], function($carry, $response) {
                        return $carry || ($response['requires_input'] ?? false);
                    }, false)
            ]);
        }

        return $result;
    }

    /**
     * Fetch target node id from persisted button map.
     *
     * @param string $messageBody
     * @param array $context
     * @return string|null
     */
    private function getButtonMapTarget($messageBody, $context = [])
    {
        $buttonMap = $context['button_map'] ?? [];
        if (empty($buttonMap)) {
            Log::info('BUTTON_MAP_LOOKUP_EMPTY', [
                'payload' => $messageBody
            ]);
            return null;
        }

        $normalizedPayload = strtolower(trim($messageBody));
        $target = $buttonMap[$normalizedPayload] ?? null;

        Log::info('BUTTON_MAP_LOOKUP', [
            'payload_raw' => $messageBody,
            'payload_normalized' => $normalizedPayload,
            'target_found' => !is_null($target),
            'target_node' => $target,
            'button_map_keys' => array_keys($buttonMap)
        ]);

        return $target;
    }

    /**
     * Check if message is user input for current flow
     *
     * @param string $messageBody
     * @param object|null $activeFlow
     * @return bool
     */
    private function isUserInput($messageBody, $activeFlow)
    {
        return $activeFlow && 
               isset($activeFlow->__data['waiting_for_input']) && 
               $activeFlow->__data['waiting_for_input'] === true;
    }

    /**
     * Update active flow state with execution result
     *
     * @param object|null $activeFlow
     * @param array $result
     * @param object $contact
     * @return void
     */
    private function updateActiveFlowState($activeFlow, $result, $contact)
    {
        if (!$activeFlow) {
            return;
        }

        $activeFlowData = $activeFlow->__data ?? [];

        // Update context
        if (isset($result['context'])) {
            // Use the result context directly (it contains the full context including flow_context)
            $activeFlowData['flow_context'] = $result['context'];

            // Log context update for debugging
            Log::info('Updating flow context in active flow', [
                'user' => $contact->wa_id,
                'context_keys' => array_keys($result['context']),
                'has_active_interactive_nodes' => isset($result['context']['active_interactive_nodes']),
                'active_nodes_count' => count($result['context']['active_interactive_nodes'] ?? []),
                'has_active_question' => isset($result['context']['flow_context']['active_question']),
                'flow_context_keys' => array_keys($result['context']['flow_context'] ?? [])
            ]);
        }

        // Update current node
        if (isset($result['current_node_id'])) {
            $activeFlowData['current_node_id'] = $result['current_node_id'];
            
            // Also update the explicit column so other code paths that read current_node_uid stay in sync
            $activeFlow->current_node_uid = $result['current_node_id'];
        }

        // Set waiting for input flag and store active_question for question nodes
        $waitingForInput = false;
        $activeQuestion = null;
        
        if (isset($result['responses'])) {
            foreach ($result['responses'] as $response) {
                // Check for requires_input, expects_input, or wait_for_input flags
                if (($response['requires_input'] ?? false) || 
                    ($response['expects_input'] ?? false) || 
                    ($response['wait_for_input'] ?? false)) {
                    $waitingForInput = true;
                    
                    // If this is a question node, store active_question in flow_context
                    if (($response['type'] ?? '') === 'question') {
                        $currentNodeId = $activeFlowData['current_node_id'] ?? null;
                        $variableName = $response['variable_name'] ?? null;
                        
                        if ($currentNodeId && $variableName) {
                            $activeQuestion = [
                                'node_id' => $currentNodeId,
                                'variable_name' => $variableName,
                                'question_text' => $response['text'] ?? ''
                            ];
                            
                            // Store in flow_context (nested structure)
                            if (!isset($activeFlowData['flow_context'])) {
                                $activeFlowData['flow_context'] = [];
                            }
                            if (!isset($activeFlowData['flow_context']['flow_context'])) {
                                $activeFlowData['flow_context']['flow_context'] = [];
                            }
                            $activeFlowData['flow_context']['flow_context']['active_question'] = $activeQuestion;
                            
                            Log::info('Stored active_question in flow_context', [
                                'user' => $contact->wa_id,
                                'node_id' => $currentNodeId,
                                'variable_name' => $variableName
                            ]);
                        }
                    }
                    
                    break;
                }
                
                // Also check for question type responses
                if (($response['type'] ?? '') === 'question') {
                    $waitingForInput = true;
                    
                    // Store active_question for question nodes
                    $currentNodeId = $activeFlowData['current_node_id'] ?? null;
                    $variableName = $response['variable_name'] ?? null;
                    
                    if ($currentNodeId && $variableName) {
                        $activeQuestion = [
                            'node_id' => $currentNodeId,
                            'variable_name' => $variableName,
                            'question_text' => $response['text'] ?? ''
                        ];
                        
                        // Store in flow_context (nested structure)
                        if (!isset($activeFlowData['flow_context'])) {
                            $activeFlowData['flow_context'] = [];
                        }
                        if (!isset($activeFlowData['flow_context']['flow_context'])) {
                            $activeFlowData['flow_context']['flow_context'] = [];
                        }
                        $activeFlowData['flow_context']['flow_context']['active_question'] = $activeQuestion;
                        
                        Log::info('Stored active_question in flow_context', [
                            'user' => $contact->wa_id,
                            'node_id' => $currentNodeId,
                            'variable_name' => $variableName
                        ]);
                    }
                    
                    break;
                }
            }
        }
        $activeFlowData['waiting_for_input'] = $waitingForInput;

        // Log the state update for debugging
        Log::info('Updated active flow state', [
            'user' => $contact->wa_id,
            'current_node_id' => $activeFlowData['current_node_id'] ?? 'none',
            'waiting_for_input' => $waitingForInput,
            'response_count' => count($result['responses'] ?? []),
            'is_complete' => $result['is_complete'] ?? false
        ]);

        // If flow is complete, mark for deletion
        if ($result['is_complete'] ?? false) {
            $activeFlowData['completed'] = true;
            $activeFlowData['completed_at'] = now();
        }

        $activeFlow->__data = $activeFlowData;
        $activeFlow->save();
    }

    /**
     * Format flow execution result for WhatsApp response
     *
     * @param array $result
     * @param object $contact
     * @param array $options
     * @return array
     */
    private function formatFlowResult($result, $contact, $options = [])
    {
        if (!is_array($result)) {
            Log::warning('formatFlowResult: result is not an array', [
                'user' => $contact->wa_id ?? 'unknown',
                'result_type' => gettype($result)
            ]);
            return [
                'responses' => [],
                'is_complete' => true,
                'current_node_id' => null,
                'processed_by_new_flow' => true
            ];
        }

        $responses = $result['responses'] ?? [];
        $formattedResponses = [];
        
        Log::info('formatFlowResult: Formatting responses', [
            'user' => $contact->wa_id ?? 'unknown',
            'response_count' => count($responses),
            'is_complete' => $result['is_complete'] ?? false
        ]);

        foreach ($responses as $response) {
            if (!is_array($response) || !isset($response['type'])) {
                continue;
            }

            $formattedResponse = [
                'type' => $response['type'],
                'node_id' => $response['node_id'] ?? null
            ];

            // Handle different response types
            switch ($response['type']) {
                case 'message':
                    $formattedResponse['text'] = $response['text'] ?? '';
                    break;

                case 'flow':
                    $formattedResponse['type'] = 'flow';
                    $formattedResponse['whatsapp_flow_id'] = $response['whatsapp_flow_id'] ?? $response['flow_id'] ?? null;
                    $formattedResponse['flow_id'] = $response['flow_id'] ?? $response['whatsapp_flow_id'] ?? null;
                    $formattedResponse['flow_version'] = $response['flow_version'] ?? null;
                    $formattedResponse['header_text'] = $response['header_text'] ?? '';
                    $formattedResponse['body_text'] = $response['body_text'] ?? $response['text'] ?? '';
                    $formattedResponse['footer_text'] = $response['footer_text'] ?? '';
                    $formattedResponse['flow_name'] = $response['flow_name'] ?? '';
                    $formattedResponse['flow_status'] = $response['flow_status'] ?? '';
                    break;

                case 'media_message':
                    $formattedResponse['media_type'] = $response['media_type'] ?? 'image';
                    $formattedResponse['media_url'] = $response['media_url'] ?? '';
                    $formattedResponse['caption'] = $response['caption'] ?? '';
                    $formattedResponse['filename'] = $response['filename'] ?? '';
                    break;

                case 'interactive':
                    $formattedResponse['text'] = $response['text'] ?? '';
                    
                    // Handle CTA URL type interactive messages
                    if (isset($response['cta_url']) && !empty($response['cta_url'])) {
                        $formattedResponse['interaction_type'] = 'cta_url';
                        $formattedResponse['cta_url'] = $response['cta_url'];
                        // Ensure body_text is set for CTA URL messages (used by WhatsAppServiceEngine)
                        $formattedResponse['body_text'] = $response['text'] ?? $response['body_text'] ?? '';
                        
                        // Add header/footer if present
                        if (!empty($response['header_text'])) {
                            $formattedResponse['header_text'] = $response['header_text'];
                        }
                        if (!empty($response['footer_text'])) {
                            $formattedResponse['footer_text'] = $response['footer_text'];
                        }
                    }
                    // Handle list type interactive messages
                    elseif (isset($response['list_data'])) {
                        $formattedResponse['interaction_type'] = 'list';
                        $formattedResponse['list_data'] = [
                            'button_text' => $response['list_data']['button_text'] ?? 'Select an option',
                            'sections' => $response['list_data']['sections'] ?? []
                        ];
                        
                        // Add header/footer if present
                        if (!empty($response['header_text'])) {
                            $formattedResponse['header_text'] = $response['header_text'];
                        }
                        if (!empty($response['footer_text'])) {
                            $formattedResponse['footer_text'] = $response['footer_text'];
                        }
                    }
                    // Handle button type interactive messages
                    elseif (!empty($response['buttons'])) {
                        $formattedResponse['interaction_type'] = 'button';
                        $formattedResponse['buttons'] = $response['buttons'];
                        // Ensure body_text is set for button messages (used by WhatsAppServiceEngine)
                        $formattedResponse['body_text'] = $response['text'] ?? $response['body_text'] ?? '';
                    }
                    
                    // Add media support for all interactive types (image/video/document header)
                    if (isset($response['media_url']) && !empty($response['media_url'])) {
                        $formattedResponse['media_url'] = $response['media_url'];
                        $formattedResponse['media_type'] = $response['media_type'] ?? 'image';
                        if (isset($response['caption'])) {
                            $formattedResponse['caption'] = $response['caption'];
                        }
                        if (isset($response['filename'])) {
                            $formattedResponse['filename'] = $response['filename'];
                        }
                    }
                    break;

                case 'question':
                    $formattedResponse['text'] = $response['text'] ?? '';
                    $formattedResponse['expects_input'] = true;
                    $formattedResponse['variable_name'] = $response['variable_name'] ?? null;
                    break;

                default:
                    // Fallback for unknown types
                    $formattedResponse['text'] = $response['text'] ?? '';
                    break;
            }

            $formattedResponses[] = $formattedResponse;
        }

        $formattedResult = [
            'responses' => $formattedResponses,
            'is_complete' => $result['is_complete'] ?? false,
            'current_node_id' => $result['current_node_id'] ?? null,
            'processed_by_new_flow' => true
        ];
        
        Log::info('formatFlowResult: Formatted result', [
            'user' => $contact->wa_id ?? 'unknown',
            'formatted_response_count' => count($formattedResponses),
            'response_types' => array_column($formattedResponses, 'type'),
            'has_interactive' => !empty(array_filter($formattedResponses, function($r) { return ($r['type'] ?? '') === 'interactive'; }))
        ]);

        return $formattedResult;
    }

    /**
     * Check if a bot flow uses the new node structure
     *
     * @param int $flowId
     * @return bool
     */
    public function usesNewFlowStructure($flowId)
    {
        $botFlow = BotFlowModel::find($flowId);
        return $botFlow && $botFlow->usesNewFlowStructure();
    }

    /**
     * Get flow data for a bot flow
     *
     * @param int $flowId
     * @return array|null
     */
    public function getFlowData($flowId)
    {
        $botFlow = BotFlowModel::find($flowId);
        return $botFlow ? $botFlow->getFlowNodesData() : null;
    }

    /**
     * Get available buttons for a node (for debugging)
     *
     * @param array $flowData
     * @param string $nodeId
     * @return array
     */
    private function getAvailableButtons($flowData, $nodeId)
    {
        $node = $this->flowNodeService->findNodeById($flowData, $nodeId);
        if ($node && $node['type'] === 'interactive') {
            return $node['payload']['buttons'] ?? [];
        }
        return [];
    }

    /**
     * Check if the message matches the flow start_trigger ONLY
     *
     * @param string $messageBody
     * @param object $botReply
     * @param object $botFlow
     * @param object $contact
     * @return bool
     */
    private function isFlowStartTrigger($messageBody, $botReply, $botFlow, $contact = null)
    {
        $triggerType = $botFlow->trigger_type ?? 'is';

        // Handle welcome trigger type
        if ($triggerType === 'welcome') {
            // Welcome triggers should fire for first-time contacts or when explicitly triggered
            // Check if this is a first message from this contact
            if ($contact) {
                // Check if this contact has any previous incoming messages
                $hasIncomingMessages = \App\Yantrana\Components\WhatsAppService\Models\WhatsAppMessageLogModel::where([
                    'contacts__id' => $contact->_id,
                    'is_incoming_message' => 1
                ])->exists();

                Log::info('Welcome trigger check', [
                    'user' => $contact->wa_id,
                    'contact_id' => $contact->_id,
                    'has_incoming_messages' => $hasIncomingMessages,
                    'flow_id' => $botFlow->_uid,
                    'will_trigger' => !$hasIncomingMessages
                ]);

                // Trigger welcome flow for first-time contacts
                if (!$hasIncomingMessages) {
                    return true;
                }
            }
            return false;
        }

        // Handle new_message trigger type
        if ($triggerType === 'new_message') {
            // New message triggers fire for any message when there's no active flow
            $activeFlow = \App\Models\UserActiveFlow::getActiveFlow($contact->wa_id ?? '');

            Log::info('New message trigger check', [
                'user' => $contact->wa_id,
                'has_active_flow' => !is_null($activeFlow),
                'active_flow_id' => $activeFlow ? $activeFlow->flow_id : null,
                'flow_id' => $botFlow->_uid,
                'will_trigger' => !$activeFlow
            ]);

            return !$activeFlow; // Only trigger if no active flow
        }

        if (!$botFlow->start_trigger) {
            return false;
        }

        // Split start_trigger by commas and trim each trigger word
        $triggerWords = array_map('trim', explode(',', $botFlow->start_trigger));
        $messageBody = strtolower(trim($messageBody));

        // Check if message matches any of the trigger words based on trigger type
        foreach ($triggerWords as $trigger) {
            $trigger = strtolower(trim($trigger));
            $isMatch = false;

            switch ($triggerType) {
                case 'is':
                    $isMatch = $trigger === $messageBody;
                    break;
                case 'starts_with':
                    $isMatch = str_starts_with($messageBody, $trigger);
                    break;
                case 'ends_with':
                    $isMatch = str_ends_with($messageBody, $trigger);
                    break;
                case 'contains_word':
                    // Match whole words only
                    $isMatch = preg_match('/\b' . preg_quote($trigger, '/') . '\b/i', $messageBody);
                    break;
                case 'contains':
                    $isMatch = str_contains($messageBody, $trigger);
                    break;
                case 'stop_promotional':
                    // Handle stop promotional logic if needed
                    $isMatch = $trigger === $messageBody;
                    break;
                default:
                    $isMatch = $trigger === $messageBody;
                    break;
            }

            if ($isMatch) {
                Log::info('Message matches flow start_trigger', [
                    'message' => $messageBody,
                    'matched_trigger' => $trigger,
                    'trigger_type' => $triggerType,
                    'all_triggers' => $triggerWords,
                    'flow_id' => $botFlow->_uid
                ]);
                return true;
            }
        }

        Log::info('Message does not match any flow start_trigger', [
            'message' => $messageBody,
            'trigger_type' => $triggerType,
            'available_triggers' => $triggerWords,
            'flow_id' => $botFlow->_uid
        ]);

        return false;
    }

    /**
     * Check if a message is a button text from any node in the flow
     *
     * @param string $messageBody
     * @param array $flowData
     * @return bool
     */
    private function isButtonTextFromFlow($messageBody, $flowData)
    {
        if (!$flowData || !isset($flowData['nodes'])) {
            return false;
        }

        foreach ($flowData['nodes'] as $node) {
            if ($node['type'] === 'interactive' && isset($node['payload']['buttons'])) {
                foreach ($node['payload']['buttons'] as $button) {
                    if (strtolower(trim($button['title'])) === strtolower(trim($messageBody))) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get all button texts from the flow (for debugging)
     *
     * @param array $flowData
     * @return array
     */
    /**
     * Extract flow connections from flowData structure
     *
     * @param array $flowData
     * @return array
     */
    private function extractFlowConnections($flowData)
    {
        $connections = [];
        
        // Extract from links (legacy structure or new structure with links array)
        if (isset($flowData['links'])) {
            foreach ($flowData['links'] as $link) {
                $fromNode = $link['fromOperator'] ?? $link['from'] ?? null;
                $toNode = $link['toOperator'] ?? $link['to'] ?? null;
                $connector = $link['fromConnector'] ?? $link['fromConnector'] ?? $link['connector'] ?? 'output';
                
                if ($fromNode && $toNode) {
                    if (!isset($connections[$fromNode])) {
                        $connections[$fromNode] = [];
                    }
                    $connections[$fromNode][$connector] = $toNode;
                }
            }
        }
        
        // Extract from nodes (new structure)
        if (isset($flowData['nodes'])) {
            foreach ($flowData['nodes'] as $node) {
                $nodeId = $node['id'] ?? null;
                if (!$nodeId) {
                    continue;
                }
                
                if (!isset($connections[$nodeId])) {
                    $connections[$nodeId] = [];
                }
                
                $payload = $node['payload'] ?? [];
                
                // Extract connections based on node type
                if ($node['type'] === 'interactive') {
                    // Button connections
                    if (isset($payload['buttons'])) {
                        foreach ($payload['buttons'] as $button) {
                            if (isset($button['next_node'])) {
                                $buttonId = $button['id'] ?? 'button_' . uniqid();
                                $connections[$nodeId][$buttonId] = $button['next_node'];
                            }
                        }
                    }
                    // List connections
                    if (isset($payload['list_data']['sections'])) {
                        foreach ($payload['list_data']['sections'] as $section) {
                            if (isset($section['rows'])) {
                                foreach ($section['rows'] as $row) {
                                    if (isset($row['next_node'])) {
                                        $rowId = $row['id'] ?? 'row_' . uniqid();
                                        $connections[$nodeId][$rowId] = $row['next_node'];
                                    }
                                }
                            }
                        }
                    }
                } elseif ($node['type'] === 'question') {
                    // Question node connections - check for simple_output
                    if (isset($payload['next_node'])) {
                        $connections[$nodeId]['simple_output'] = $payload['next_node'];
                    }
                    if (isset($payload['default_next_node'])) {
                        $connections[$nodeId]['default_flow'] = $payload['default_next_node'];
                    }
                    // Conditional flows
                    if (isset($payload['conditional_flows'])) {
                        foreach ($payload['conditional_flows'] as $index => $flow) {
                            if (isset($flow['target_node'])) {
                                $connections[$nodeId]['condition_' . $index] = $flow['target_node'];
                            }
                        }
                    }
                } elseif ($node['type'] === 'goto') {
                    // Goto node connection
                    if (isset($payload['redirect_to_node'])) {
                        $connections[$nodeId]['goto_output'] = $payload['redirect_to_node'];
                    }
                } else {
                    // Simple message node - check for next_node
                    if (isset($payload['next_node'])) {
                        $connections[$nodeId]['output'] = $payload['next_node'];
                    }
                }
            }
        }
        
        Log::info('Extracted flow connections', [
            'connection_count' => count($connections),
            'nodes_with_connections' => array_keys($connections)
        ]);
        
        return $connections;
    }

    private function getAllButtonTextsFromFlow($flowData)
    {
        $buttonTexts = [];

        if (!$flowData || !isset($flowData['nodes'])) {
            return $buttonTexts;
        }

        foreach ($flowData['nodes'] as $node) {
            if ($node['type'] === 'interactive' && isset($node['payload']['buttons'])) {
                foreach ($node['payload']['buttons'] as $button) {
                    $buttonTexts[] = $button['title'];
                }
            }
        }

        return array_unique($buttonTexts);
    }

    /**
     * Trigger AI bot reply for flow submission
     *
     * @param object $contact
     * @param string $messageBody
     * @param array $options
     * @return void
     */
    private function triggerAiBotForFlowSubmission($contact, $messageBody, $options = [])
    {
        // Check if AI bot should be triggered
        $vendorPlanDetails = vendorPlanDetails('ai_chat_bot', 0, $contact->vendors__id);
        if (!$vendorPlanDetails['is_limit_available'] || $contact->disable_ai_bot) {
            return;
        }

        $aiBotReplyText = null;
        
        // Try OpenAI first
        if (getVendorSettings('enable_open_ai_bot', null, null, $contact->vendors__id) && 
            getVendorSettings('open_ai_access_key', null, null, $contact->vendors__id)) {
            try {
                $aiBotReplyText = app()->make(\App\Yantrana\Components\WhatsAppService\Services\OpenAiService::class)
                    ->generateAnswerFromMultipleSections($messageBody, $contact->_uid, $contact->vendors__id);
                
                if ($aiBotReplyText) {
                    $botName = getVendorSettings('open_ai_bot_name', null, null, $contact->vendors__id);
                    $aiBotReplyText = $botName ? ($botName . ":\n\n" . $aiBotReplyText) : $aiBotReplyText;
                    
                    // Get WhatsAppServiceEngine instance to send message using public method
                    $whatsAppServiceEngine = app(\App\Yantrana\Components\WhatsAppService\WhatsAppServiceEngine::class);
                    $whatsAppServiceEngine->processSendChatMessage([
                        'contactUid' => $contact->_uid,
                        'messageBody' => $aiBotReplyText,
                    ], false, $contact->vendors__id, [
                        'bot_reply' => true,
                        'ai_bot_reply' => true,
                        'open_ai_reply' => true,
                        'from_phone_number_id' => $options['fromPhoneNumberId'] ?? null,
                        'messageWamid' => $options['messageWamid'] ?? null,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::debug('AI Bot Error: ' . $e->getMessage());
                if (getVendorSettings('open_ai_failed_message', null, null, $contact->vendors__id)) {
                    $whatsAppServiceEngine = app(\App\Yantrana\Components\WhatsAppService\WhatsAppServiceEngine::class);
                    $whatsAppServiceEngine->processSendChatMessage([
                        'contactUid' => $contact->_uid,
                        'messageBody' => getVendorSettings('open_ai_failed_message', null, null, $contact->vendors__id),
                    ], false, $contact->vendors__id, [
                        'bot_reply' => true,
                        'ai_error_triggered' => true,
                        'from_phone_number_id' => $options['fromPhoneNumberId'] ?? null,
                        'messageWamid' => $options['messageWamid'] ?? null,
                    ]);
                }
            }
        }
        
        // Try Flowise if OpenAI didn't respond
        if (!$aiBotReplyText && 
            getVendorSettings('enable_flowise_ai_bot', null, null, $contact->vendors__id) && 
            getVendorSettings('flowise_url', null, null, $contact->vendors__id)) {
            try {
                $botRequest = \Illuminate\Support\Facades\Http::throw(function ($response, $e) {
                    Log::debug($e->getMessage());
                });
                
                if ($bearerToken = getVendorSettings('flowise_access_token', null, null, $contact->vendors__id)) {
                    $botRequest->withToken($bearerToken);
                }
                
                $aiBotReplyText = $botRequest->post(getVendorSettings('flowise_url', null, null, $contact->vendors__id), [
                    'question' => $messageBody,
                    'overrideConfig' => [
                        'sessionId' => $contact->_uid
                    ],
                ])->json('text');
                
                if ($aiBotReplyText) {
                    $whatsAppServiceEngine = app(\App\Yantrana\Components\WhatsAppService\WhatsAppServiceEngine::class);
                    $whatsAppServiceEngine->processSendChatMessage([
                        'contactUid' => $contact->_uid,
                        'messageBody' => $aiBotReplyText,
                    ], false, $contact->vendors__id, [
                        'bot_reply' => true,
                        'ai_bot_reply' => true,
                        'open_flowise_ai_reply' => true,
                        'from_phone_number_id' => $options['fromPhoneNumberId'] ?? null,
                        'messageWamid' => $options['messageWamid'] ?? null,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::debug('Flowise AI Bot Error: ' . $e->getMessage());
                if (getVendorSettings('flowise_failed_message', null, null, $contact->vendors__id)) {
                    $whatsAppServiceEngine = app(\App\Yantrana\Components\WhatsAppService\WhatsAppServiceEngine::class);
                    $whatsAppServiceEngine->processSendChatMessage([
                        'contactUid' => $contact->_uid,
                        'messageBody' => getVendorSettings('flowise_failed_message', null, null, $contact->vendors__id),
                    ], false, $contact->vendors__id, [
                        'bot_reply' => true,
                        'ai_error_triggered' => true,
                        'from_phone_number_id' => $options['fromPhoneNumberId'] ?? null,
                        'messageWamid' => $options['messageWamid'] ?? null,
                    ]);
                }
            }
        }
    }
}
