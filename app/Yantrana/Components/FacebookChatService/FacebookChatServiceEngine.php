<?php

/**
 * FacebookChatServiceEngine.php - Main Facebook chat service engine
 *
 * This file is part of the Facebook Chat Service component.
 *-----------------------------------------------------------------------------*/

namespace App\Yantrana\Components\FacebookChatService;

use App\Yantrana\Base\BaseEngine;
use App\Yantrana\Components\FacebookChatService\Services\FacebookChatApiService;
use Illuminate\Support\Facades\Log;

class FacebookChatServiceEngine extends BaseEngine
{
    /**
     * @var FacebookChatApiService
     */
    protected $facebookChatApiService;

    /**
     * Constructor
     */
    public function __construct(FacebookChatApiService $facebookChatApiService)
    {
        $this->facebookChatApiService = $facebookChatApiService;
    }

    /**
     * Test Facebook API connection
     *
     * @return array
     */
    public function testConnection()
    {
        $result = $this->facebookChatApiService->testConnection();

        if ($result['success']) {
            return $this->engineSuccessResponse([
                'page_info' => $result['data']
            ], __tr('Facebook API connection successful'));
        }

        return $this->engineFailedResponse([], $result['message']);
    }

    /**
     * Get Facebook conversations
     *
     * @return array
     */
    public function getConversations()
    {
        $result = $this->facebookChatApiService->getConversations();

        if ($result['success']) {
            // Process conversations data to match the expected format
            $processedConversations = $this->processConversationsData($result['data']);

            return $this->engineSuccessResponse([
                'conversations' => $processedConversations,
                'paging' => $result['paging'] ?? null
            ], __tr('Conversations retrieved successfully'));
        }

        return $this->engineFailedResponse([], $result['message']);
    }

    /**
     * Get messages for a specific conversation
     *
     * @param string $conversationId
     * @return array
     */
    public function getConversationMessages($conversationId)
    {
        $result = $this->facebookChatApiService->getConversationMessages($conversationId);

        if ($result['success']) {
            // Process messages data to match the expected format
            $processedMessages = $this->processMessagesData($result['data']);

            return $this->engineSuccessResponse([
                'messages' => $processedMessages,
                'paging' => $result['paging'] ?? null
            ], __tr('Messages retrieved successfully'));
        }

        return $this->engineFailedResponse([], $result['message']);
    }

    /**
     * Get facebook_id for a specific conversation
     *
     * @param string $conversationId
     * @return string|null
     */
    public function getConversationFacebookId($conversationId)
    {
        try {
            // Get all conversations and find the matching one
            $result = $this->facebookChatApiService->getConversations();
            
            if ($result['success'] && isset($result['data'])) {
                $processedConversations = $this->processConversationsData($result['data']);
                
                // Find the conversation matching the conversationId
                foreach ($processedConversations as $conversation) {
                    if (($conversation['id'] === $conversationId) || 
                        ($conversation['conversation_id'] === $conversationId)) {
                        return $conversation['facebook_id'] ?? null;
                    }
                }
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('Error getting conversation facebook_id', [
                'error' => $e->getMessage(),
                'conversation_id' => $conversationId
            ]);
            return null;
        }
    }

    /**
     * Send message to Facebook conversation
     *
     * @param string $conversationId
     * @param string $message
     * @param array $options Optional parameters for interactive messages
     * @return array
     */
    public function sendConversationMessage($conversationId, $message, $options = [])
    {
        $result = $this->facebookChatApiService->sendConversationMessage($conversationId, $message, $options);

        if ($result['success']) {
            return $this->engineSuccessResponse([
                'message_data' => $result['data']
            ], __tr('Message sent successfully'));
        }

        return $this->engineFailedResponse([], $result['message']);
    }

    /**
     * Send bot reply message to Facebook conversation
     *
     * @param string $conversationId
     * @param string $replyText
     * @param int $vendorId
     * @param array|null $interactionMessageData
     * @param array $options
     * @return array
     */
    public function sendReplyBotMessage($conversationId, $replyText, $vendorId, $interactionMessageData = null, $options = [])
    {
        $mediaMessageData = $options['mediaMessageData'] ?? $options['media_message_data'] ?? null;
        
        $messageOptions = [];
        
        // CRITICAL FIX: Pass facebook_id if available
        if (isset($options['facebook_id'])) {
            $messageOptions['facebook_id'] = $options['facebook_id'];
        } elseif (isset($options['recipient_id'])) {
            $messageOptions['recipient_id'] = $options['recipient_id'];
        } else {
            // Try to get facebook_id from conversation data stored in session or database
            // This is a fallback - ideally it should be passed in options
            Log::warning('Facebook bot reply missing facebook_id', [
                'conversation_id' => $conversationId,
                'vendor_id' => $vendorId
            ]);
        }
        
        // Pass media message data if provided
        if ($mediaMessageData) {
            // Convert WhatsApp format to Facebook format if needed
            $messageOptions['mediaMessageData'] = [
                'header_type' => $mediaMessageData['type'] ?? $mediaMessageData['header_type'] ?? 'image',
                'media_link' => $mediaMessageData['link'] ?? $mediaMessageData['media_link'] ?? $mediaMessageData['media_url'] ?? '',
                'caption' => $mediaMessageData['caption'] ?? '',
                'file_name' => $mediaMessageData['file_name'] ?? $mediaMessageData['filename'] ?? ''
            ];
        }
        
        // Pass interaction message data if provided
        if ($interactionMessageData) {
            $messageOptions['interaction_message'] = $interactionMessageData;
        }
        
        // Pass carousel data if provided
        if (isset($options['carousel']) || isset($options['carousel_data'])) {
            $messageOptions['carousel'] = $options['carousel'] ?? $options['carousel_data'];
        }
        
        return $this->sendConversationMessage($conversationId, $replyText, $messageOptions);
    }

    /**
     * Process conversations data to standardize format
     *
     * @param array $conversations
     * @return array
     */
    protected function processConversationsData($conversations)
    {
        $processedConversations = [];
        $pageId = getVendorSettings('facebook_page_id');

        foreach ($conversations as $conversation) {
            $participants = $this->extractParticipants($conversation);
            
            // Get the other participant (not the page) - similar to Instagram
            $otherParticipant = null;
            foreach ($participants as $participant) {
                $participantId = is_array($participant) ? ($participant['id'] ?? $participant) : $participant;
                if ($participantId !== $pageId) {
                    $otherParticipant = is_array($participant) ? $participant : ['id' => $participantId];
                    break;
                }
            }
            
            // Extract username/name from participant - prioritize name, then username, then fallback
            $senderName = 'Facebook User';
            $facebookUsername = null;
            
            if ($otherParticipant) {
                if (isset($otherParticipant['name'])) {
                    $senderName = $otherParticipant['name'];
                    $facebookUsername = $otherParticipant['name'];
                } elseif (isset($otherParticipant['username'])) {
                    $senderName = $otherParticipant['username'];
                    $facebookUsername = $otherParticipant['username'];
                } elseif (isset($otherParticipant['id'])) {
                    // If we only have ID, try to get name from snippet or use ID
                    $senderName = 'User ' . substr($otherParticipant['id'], -4);
                }
            }
            
            // Fallback to snippet if participant name not found
            if ($senderName === 'Facebook User' && isset($conversation['snippet'])) {
                $snippet = $conversation['snippet'];
                // Try to extract name from snippet if it contains a colon
                if (strpos($snippet, ':') !== false) {
                    $parts = explode(':', $snippet, 2);
                    $senderName = trim($parts[0]);
                }
            }
            
            $processedConversations[] = [
                'id' => $conversation['id'] ?? '',
                'conversation_id' => $conversation['id'] ?? '',
                'link' => $conversation['link'] ?? '',
                'updated_time' => $conversation['updated_time'] ?? '',
                'participants' => $participants,
                'facebook_id' => $otherParticipant['id'] ?? null,
                'facebook_username' => $facebookUsername,
                'sender_name' => $senderName,
                'full_name' => $senderName, // For consistency with Instagram
                'name_initials' => $this->getNameInitials($senderName),
                'last_message_preview' => $conversation['snippet'] ?? $this->getLastMessagePreview($conversation),
                'unread_count' => 0, // Facebook API doesn't provide this directly
                'platform' => 'facebook'
            ];
        }

        return $processedConversations;
    }

    /**
     * Process messages data to standardize format
     *
     * @param array $messages
     * @return array
     */
    protected function processMessagesData($messages)
    {
        $processedMessages = [];

        foreach ($messages as $message) {
            $processedMessages[] = [
                'id' => $message['id'] ?? '',
                'message' => $message['message'] ?? '',
                'from' => $message['from'] ?? null,
                'created_time' => $message['created_time'] ?? '',
                'is_incoming_message' => $this->isIncomingMessage($message),
                'message_type' => 'text',
                'platform' => 'facebook',
                'formatted_time' => $this->formatMessageTime($message['created_time'] ?? ''),
                'sender_name' => $this->getSenderName($message)
            ];
        }

        // Sort messages by created_time (oldest first)
        usort($processedMessages, function ($a, $b) {
            return strtotime($a['created_time']) - strtotime($b['created_time']);
        });

        return $processedMessages;
    }

    /**
     * Extract participants from conversation data
     *
     * @param array $conversation
     * @return array
     */
    protected function extractParticipants($conversation)
    {
        $participants = [];
        
        // Facebook API returns participants in different formats
        if (isset($conversation['participants']['data'])) {
            $participants = $conversation['participants']['data'];
        } elseif (isset($conversation['participants']) && is_array($conversation['participants'])) {
            $participants = $conversation['participants'];
        }
        
        // If participants is a string (ID), convert to array format
        if (is_string($participants)) {
            $participants = [['id' => $participants]];
        }
        
        // Ensure we have an array
        if (!is_array($participants)) {
            $participants = [];
        }
        
        return $participants;
    }

    /**
     * Get last message preview from conversation
     *
     * @param array $conversation
     * @return string
     */
    protected function getLastMessagePreview($conversation)
    {
        // This would typically require a separate API call to get the latest message
        // For now, return empty string
        return '';
    }

    /**
     * Determine if message is incoming (from user to page)
     *
     * @param array $message
     * @return bool
     */
    protected function isIncomingMessage($message)
    {
        $pageId = getVendorSettings('facebook_page_id');
        
        if (isset($message['from']['id'])) {
            // If message is from the page, it's outgoing
            return $message['from']['id'] !== $pageId;
        }

        return true; // Default to incoming if we can't determine
    }

    /**
     * Format message timestamp for display
     *
     * @param string $timestamp
     * @return string
     */
    protected function formatMessageTime($timestamp)
    {
        if (empty($timestamp)) {
            return '';
        }

        try {
            $date = new \DateTime($timestamp);
            return $date->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return $timestamp;
        }
    }

    /**
     * Get sender name from message data
     *
     * @param array $message
     * @return string
     */
    protected function getSenderName($message)
    {
        if (isset($message['from']['name'])) {
            return $message['from']['name'];
        }

        if (isset($message['from']['id'])) {
            $pageId = getVendorSettings('facebook_page_id');
            if ($message['from']['id'] === $pageId) {
                return 'Page'; // This is from the business page
            }
            return 'User'; // This is from a user
        }

        return 'Unknown';
    }

    /**
     * Get Facebook page information
     *
     * @return array
     */
    public function getPageInfo()
    {
        return $this->testConnection();
    }

    /**
     * Get Facebook posts
     *
     * @return array
     */
    public function getPosts()
    {
        $result = $this->facebookChatApiService->getPosts();

        if ($result['success']) {
            // Process posts data to match the expected format
            $processedPosts = $this->processPostsData($result['data']);

            return $this->engineSuccessResponse([
                'posts' => $processedPosts,
                'paging' => $result['paging'] ?? null
            ], __tr('Posts retrieved successfully'));
        }

        return $this->engineFailedResponse([], $result['message']);
    }

    /**
     * Get comments for a specific post
     *
     * @param string $postId
     * @return array
     */
    public function getPostComments($postId)
    {
        $result = $this->facebookChatApiService->getPostComments($postId);

        if ($result['success']) {
            // Process comments data to match the expected format
            $processedComments = $this->processCommentsData($result['data']);

            return $this->engineSuccessResponse([
                'comments' => $processedComments,
                'paging' => $result['paging'] ?? null
            ], __tr('Comments retrieved successfully'));
        }

        return $this->engineFailedResponse([], $result['message']);
    }

    /**
     * Reply to a Facebook comment privately (via DM)
     *
     * @param string $commentId
     * @param string $message
     * @return array
     */
    public function replyToComment($commentId, $message)
    {
        $result = $this->facebookChatApiService->replyToComment($commentId, $message);

        if ($result['success']) {
            return $this->engineSuccessResponse([
                'reply_data' => $result['data'],
                'reply_type' => $result['type'] ?? 'private'
            ], __tr('Private reply sent successfully'));
        }

        return $this->engineFailedResponse([], $result['message']);
    }

    /**
     * Reply to a Facebook comment publicly (under the post)
     *
     * @param string $commentId
     * @param string $message
     * @return array
     */
    public function replyToCommentPublicly($commentId, $message)
    {
        $result = $this->facebookChatApiService->replyToCommentPublicly($commentId, $message);

        if ($result['success']) {
            return $this->engineSuccessResponse([
                'reply_data' => $result['data'],
                'reply_type' => $result['type'] ?? 'public'
            ], __tr('Public reply posted successfully'));
        }

        return $this->engineFailedResponse([], $result['message']);
    }

    /**
     * Process posts data to standardize format
     *
     * @param array $posts
     * @return array
     */
    protected function processPostsData($posts)
    {
        $processedPosts = [];

        foreach ($posts as $post) {
            $message = $post['message'] ?? null;
            $story = $post['story'] ?? null;
            $postType = $post['type'] ?? null;
            $hasPicture = !empty($post['full_picture'] ?? $post['picture'] ?? null);
            
            // Since we're using /published_posts, most automatic story updates are already excluded
            // But we still filter out any remaining story-only posts without content
            // Only include posts that have:
            // 1. A message field, OR
            // 2. A picture (photo/video post), OR  
            // 3. A valid story that's not an automatic update
            if (empty($message) && !$hasPicture) {
                // Check if it's a story update we want to skip
                if (!empty($story)) {
                    $storyLower = strtolower($story);
                    // Skip profile/cover photo updates and similar automatic story updates
                    if (strpos($storyLower, 'updated their cover photo') !== false ||
                        strpos($storyLower, 'updated their profile picture') !== false ||
                        strpos($storyLower, 'updated the cover photo') !== false ||
                        strpos($storyLower, 'updated the profile picture') !== false ||
                        strpos($storyLower, 'changed their cover photo') !== false ||
                        strpos($storyLower, 'changed their profile picture') !== false) {
                        continue;
                    }
                } else {
                    // No message, no picture, no story - skip it
                    continue;
                }
            }
            
            // Determine post type
            $type = $postType ?? 'post';
            if (!empty($story) && !empty($message)) {
                $type = 'post'; // Post with both message and story
            } elseif (!empty($message)) {
                $type = 'post';
            } elseif ($hasPicture) {
                $type = $postType ?? 'photo'; // Post with picture
            }
            
            // Use message if available, otherwise use story or a default
            $displayMessage = $message ?? $story ?? ($hasPicture ? 'Post with media' : 'No content');
            
            $processedPosts[] = [
                'id' => $post['id'] ?? '',
                'message' => $displayMessage,
                'story' => $story,
                'created_time' => $post['created_time'] ?? '',
                'formatted_time' => $this->formatPostTime($post['created_time'] ?? ''),
                'platform' => 'facebook',
                'type' => $type
            ];
        }

        // Sort posts by created_time (newest first)
        usort($processedPosts, function ($a, $b) {
            $timeA = strtotime($a['created_time'] ?? '1970-01-01');
            $timeB = strtotime($b['created_time'] ?? '1970-01-01');
            return $timeB - $timeA;
        });

        Log::info('Facebook Posts Processed', [
            'total_posts' => count($processedPosts),
            'sample_posts' => array_slice($processedPosts, 0, 3)
        ]);

        return $processedPosts;
    }

    /**
     * Process comments data to standardize format including nested replies
     *
     * @param array $comments
     * @return array
     */
    protected function processCommentsData($comments)
    {
        $processedComments = [];

        foreach ($comments as $comment) {
            // Extract username from 'from' object (Facebook API structure)
            $commenterName = 'Unknown User';
            $commenterId = '';
            if (isset($comment['from'])) {
                if (isset($comment['from']['name'])) {
                    $commenterName = $comment['from']['name'];
                } elseif (isset($comment['from']['id'])) {
                    // If name is not available, use ID or a generic name
                    $commenterName = 'Facebook User';
                }
                $commenterId = $comment['from']['id'] ?? '';
            }

            // Process main comment
            $processedComment = [
                'id' => $comment['id'] ?? '',
                'message' => $comment['message'] ?? '',
                'from' => $comment['from'] ?? null,
                'created_time' => $comment['created_time'] ?? '',
                'formatted_time' => $this->formatCommentTime($comment['created_time'] ?? ''),
                'commenter_name' => $commenterName,
                'commenter_id' => $commenterId,
                'platform' => 'facebook',
                'type' => 'comment',
                'replies' => []
            ];

            // Process nested replies if they exist
            if (isset($comment['comments']['data']) && is_array($comment['comments']['data'])) {
                foreach ($comment['comments']['data'] as $reply) {
                    // Clean up message if it's JSON encoded
                    $replyMessage = $reply['message'] ?? '';
                    if (strpos($replyMessage, '{"text":') === 0) {
                        $decoded = json_decode($replyMessage, true);
                        $replyMessage = $decoded['text'] ?? $replyMessage;
                    }

                    // Extract username from 'from' object for replies
                    $replyCommenterName = 'Unknown User';
                    $replyCommenterId = '';
                    if (isset($reply['from'])) {
                        if (isset($reply['from']['name'])) {
                            $replyCommenterName = $reply['from']['name'];
                        } elseif (isset($reply['from']['id'])) {
                            $replyCommenterName = 'Facebook User';
                        }
                        $replyCommenterId = $reply['from']['id'] ?? '';
                    }

                    $processedComment['replies'][] = [
                        'id' => $reply['id'] ?? '',
                        'message' => $replyMessage,
                        'from' => $reply['from'] ?? null,
                        'created_time' => $reply['created_time'] ?? '',
                        'formatted_time' => $this->formatCommentTime($reply['created_time'] ?? ''),
                        'commenter_name' => $replyCommenterName,
                        'commenter_id' => $replyCommenterId,
                        'platform' => 'facebook',
                        'type' => 'reply'
                    ];
                }

                // Sort replies by created_time (oldest first)
                usort($processedComment['replies'], function ($a, $b) {
                    return strtotime($a['created_time']) - strtotime($b['created_time']);
                });
            }

            $processedComments[] = $processedComment;
        }

        // Sort comments by created_time (oldest first)
        usort($processedComments, function ($a, $b) {
            return strtotime($a['created_time']) - strtotime($b['created_time']);
        });

        return $processedComments;
    }

    /**
     * Format post timestamp for display
     *
     * @param string $timestamp
     * @return string
     */
    protected function formatPostTime($timestamp)
    {
        if (empty($timestamp)) {
            return '';
        }

        try {
            $date = new \DateTime($timestamp);
            return $date->format('M j, Y \a\t g:i A');
        } catch (\Exception $e) {
            return $timestamp;
        }
    }

    /**
     * Format comment timestamp for display
     *
     * @param string $timestamp
     * @return string
     */
    protected function formatCommentTime($timestamp)
    {
        if (empty($timestamp)) {
            return '';
        }

        try {
            $date = new \DateTime($timestamp);
            $now = new \DateTime();
            $diff = $now->diff($date);

            if ($diff->days == 0) {
                if ($diff->h == 0) {
                    return $diff->i . 'm ago';
                }
                return $diff->h . 'h ago';
            } elseif ($diff->days == 1) {
                return '1 day ago';
            } elseif ($diff->days < 7) {
                return $diff->days . ' days ago';
            } else {
                return $date->format('M j');
            }
        } catch (\Exception $e) {
            return $timestamp;
        }
    }

    /**
     * Get name initials from full name
     *
     * @param string $name
     * @return string
     */
    private function getNameInitials($name)
    {
        if (empty($name)) {
            return 'FU';
        }

        $words = explode(' ', trim($name));
        $initials = '';

        if (count($words) >= 2) {
            // Take first letter of first and last word
            $initials = strtoupper(substr($words[0], 0, 1) . substr($words[count($words) - 1], 0, 1));
        } else {
            // Take first two letters of the name
            $initials = strtoupper(substr($name, 0, 2));
        }

        return $initials;
    }

    /**
     * Check if Facebook service is configured
     *
     * @return bool
     */
    public function isConfigured()
    {
        return $this->facebookChatApiService->isConfigured();
    }
}
