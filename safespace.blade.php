
<?php
public function processBotFlowClone($botFlowIdOrUid)
{
    $vendorId = getVendorId();

    // fetch the record
    $botFlow = $this->botFlowRepository->fetchIt([
        '_uid' => $botFlowIdOrUid,
        'vendors__id' => $vendorId,
    ]);

    if (__isEmpty($botFlow)) {
        return $this->engineResponse(18, [
            'botFlowUid' => $botFlowIdOrUid
        ], __tr('Bot Flow not found'));
    }

    // Create new UID for the cloned flow
    $newBotFlowUid = Str::uuid();
    $newBotFlow = $botFlow->replicate();
    $newBotFlow->title = $botFlow->title . ' - Copy';
    $newBotFlow->_uid = $newBotFlowUid;
    $newBotFlow->status = 2; 
    $newBotFlow->vendors__id = $vendorId;

    // Clone flow builder data and update context_flow_uid
    $flowData = $botFlow->__data;
    if ($flowData) {
        if (isset($flowData['flow_builder_data']['operators'])) {
            foreach ($flowData['flow_builder_data']['operators'] as &$operator) {
                if (isset($operator['properties']['body'])) {
                    $bodyHtml = $operator['properties']['body'];

                    // Replace old context_flow_uid with new flow UID
                    $bodyHtml = preg_replace(
                        '/"context_flow_uid":\s*"[^"]+"/',
                        '"context_flow_uid": "'.$newBotFlowUid.'"',
                        $bodyHtml
                    );

                    $operator['properties']['body'] = $bodyHtml;
                }
            }
        }
        $newBotFlow->__data = $flowData;
    }

    if ($newBotFlow->save()) {
        // ✅ Clone associated bot replies
        $this->cloneBotFlowReplies($botFlow->_id, $newBotFlow->_id, $vendorId);

        return $this->engineResponse(1, [
            'botFlowUid' => $newBotFlowUid
        ], __tr('Bot Flow cloned successfully'));
    }

    return $this->engineResponse(2, [
        'botFlowUid' => $botFlowIdOrUid
    ], __tr('Failed to clone Bot Flow'));
}

/**
 * Clone bot replies when a flow is cloned
 */
private function cloneBotFlowReplies($originalFlowId, $newFlowId, $vendorId)
{
    // Get all bot replies associated with the original flow
    $originalBotReplies = $this->botReplyRepository->fetchItAll([
        'vendors__id' => $vendorId,
        'bot_flows__id' => $originalFlowId,
    ]);

    foreach ($originalBotReplies as $originalBotReply) {
        $newBotReplyUid = Str::uuid();
        $newBotReply = $originalBotReply->replicate();
        $newBotReply->name = $originalBotReply->name . ' - Copy';
        $newBotReply->_uid = $newBotReplyUid;
        $newBotReply->bot_flows__id = $newFlowId;
        $newBotReply->status = 2; 
        $newBotReply->vendors__id = $vendorId;

        // Clone and clean bot data
        $botData = $originalBotReply->__data;
        if ($botData) {
            // Reset interactive elements like duplicate
            if (isset($botData['interaction_message']['buttons'])) {
                $botData['interaction_message']['buttons'] = [];
            }
            if (isset($botData['interaction_message']['list_data'])) {
                $botData['interaction_message']['list_data'] = [
                    'button_text' => $botData['interaction_message']['list_data']['button_text'] ?? '',
                ];
            }
            $newBotReply->__data = $botData;
        }

        $newBotReply->save();
    }
}







private function cloneBotFlowReplies($originalFlowId, $newFlowId, $vendorId)
{
    // Get all bot replies associated with the original flow
    $originalBotReplies = $this->botReplyRepository->fetchItAll([
        'vendors__id' => $vendorId,
        'bot_flows__id' => $originalFlowId,
    ]);

    // Fetch the cloned flow
    $botFlow = $this->botFlowRepository->fetchIt([
        'vendors__id' => $vendorId,
        '_id' => $newFlowId,
    ]);

    // Work with the flow’s __data
    $flowData = $botFlow->__data;
    // if (is_array($flowData)) {
    //     $flowData = json_encode($flowData); // normalize to string for replacements
    // }

    // foreach ($originalBotReplies as $originalBotReply) {
    //     $newBotReplyUid = Str::uuid();

    //     // Clone reply
    //     $newBotReply = $originalBotReply->replicate();
    //     $newBotReply->name = $originalBotReply->name . ' - Copy';
    //     $newBotReply->_uid = $newBotReplyUid;
    //     $newBotReply->bot_flows__id = $newFlowId;
    //     $newBotReply->status = 2; 
    //     $newBotReply->vendors__id = $vendorId;
    //     $newBotReply->save();

    //     // Replace old UID with new UID in flow JSON
    //     $flowData = str_replace($originalBotReply->_uid, $newBotReplyUid, $flowData);

    // }

    foreach ($originalBotReplies as $originalBotReply) {
        $newBotReplyUid = (string) Str::uuid();
    
        // Clone reply
        $newBotReply = $originalBotReply->replicate();
        $newBotReply->name = $originalBotReply->name . ' - Copy';
        $newBotReply->_uid = $newBotReplyUid;
        $newBotReply->bot_flows__id = $newFlowId;
        $newBotReply->status = 2; 
        $newBotReply->vendors__id = $vendorId;
        $newBotReply->save();
    
        // Debugging
        Log::info('Replacement Debug', [
            'original_uid' => $originalBotReply->_uid,
            'new_uid'      => $newBotReplyUid,
            'found_in_flow'=> strpos($flowData, $originalBotReply->_uid) !== false,
        ]);
    
        // Replace old UID with new UID in flow JSON
        $flowData = str_replace($originalBotReply->_uid, $newBotReplyUid, $flowData);
    }
    Log::info('FlowData after replace', [
        'snippet' => substr($flowData, 0, 300)
    ]);
    

    // Save updated flow data back
$botFlow->__data = $flowData;
    $botFlow->save();
}


public function processBotFlowClone($botFlowIdOrUid)
{
    $vendorId = getVendorId();

    // fetch the record
    $botFlow = $this->botFlowRepository->fetchIt([
        '_uid' => $botFlowIdOrUid,
        'vendors__id' => $vendorId,
    ]);

    if (__isEmpty($botFlow)) {
        return $this->engineResponse(18, [
            'botFlowUid' => $botFlowIdOrUid
        ], __tr('Bot Flow not found'));
    }

    // Create new UID for the cloned flow
    $newBotFlowUid = Str::uuid();
    $newBotFlow = $botFlow->replicate();
    $newBotFlow->title = $botFlow->title . ' - Copy';
    $newBotFlow->_uid = $newBotFlowUid;
    $newBotFlow->status = 2; 
    $newBotFlow->vendors__id = $vendorId;

    // Clone flow builder data and update context_flow_uid
    $flowData = $botFlow->__data;

    
    if ($flowData && isset($flowData['flow_builder_data'])) {

        // ✅ Reset operator IDs to null
        if (isset($flowData['flow_builder_data']['operators'])) {
            $newOperators = [];

            foreach ($flowData['flow_builder_data']['operators'] as $oldUid => $operator) {
                $newOperators[null] = $operator; // set key as null

                // Also replace context_flow_uid in body if exists
                if (isset($operator['properties']['body'])) {
                    $bodyHtml = $operator['properties']['body'];

                    $bodyHtml = preg_replace(
                        '/"context_flow_uid":\s*"[^"]+"/',
                        '"context_flow_uid": "'.$newBotFlowUid.'"',
                        $bodyHtml
                    );

                    $operator['properties']['body'] = $bodyHtml;
                }
            }

            // overwrite operators with new null-keyed array
            $flowData['flow_builder_data']['operators'] = $newOperators;
        }

        $newBotFlow->__data = $flowData;
    }

    if ($newBotFlow->save()) {
        // ✅ Clone associated bot replies
        $this->cloneBotFlowReplies($botFlow->_id, $newBotFlow->_id, $vendorId);
        return $this->engineResponse(1, [
            'botFlowUid' => $newBotFlowUid
        ], __tr('Bot Flow cloned successfully'));
    }

    return $this->engineResponse(2, [
        'botFlowUid' => $botFlowIdOrUid
    ], __tr('Failed to clone Bot Flow'));
}



APP_URL=https://test.thesupportlabs.com
APP_NAME="WABA PANEL"
# Use 'production' for live uses
APP_ENV=development
# Set to true for debugging
APP_DEBUG=true
# application key
APP_KEY=base64:/6cAnbpThXWkN5OIJUaThlpSjpwrZQcvJFNyLlKGRiY=
# Database Host
DB_HOST=localhost
# Database Name
DB_DATABASE=u932436910_testsupportlab
# Database Username
DB_USERNAME=u932436910_testsupportlab
# Database Password
DB_PASSWORD=]HL^$m1ORP2w 
# Mail Configuration
MAIL_MAILER=sendmail
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=null
MAIL_FROM_NAME="${APP_NAME}"
    

# SSO Secret - Shared secret for JWT token verification
SSO_SECRET=saskae

# Module API Secret - Token for CRM to authenticate API requests
MODULE_API_SECRET=saskae












