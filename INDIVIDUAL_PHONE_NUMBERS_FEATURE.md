# Individual Phone Numbers Feature for Campaigns

## Overview
This feature allows you to send WhatsApp campaigns to specific individual phone numbers without needing to create contact groups or add contacts to your database.

## How It Works

### 1. Campaign Processing Flow
1. **Campaign Creation**: User selects a WhatsApp template and chooses recipients
2. **Recipient Selection**: Can choose from:
   - All contacts
   - Specific contact groups
   - Individual phone numbers (NEW FEATURE)
3. **Message Queue**: Each recipient gets a separate entry in `whatsapp_message_queue` table
4. **Processing**: Messages are sent in batches using HTTP pools for efficiency
5. **Status Tracking**: Real-time status updates (queued, processing, sent, failed)

### 2. Database Schema
- **`campaigns`** table: Stores campaign metadata including phone numbers in `__data` field
- **`whatsapp_message_queue`** table: Queues individual messages
  - `phone_with_country_code`: Recipient's phone number
  - `contacts__id`: NULL for individual numbers, contact ID for existing contacts
  - `campaigns__id`: Reference to the campaign

### 3. Individual Phone Numbers Implementation

#### Frontend (UI)
- Added a textarea field in the campaign creation form
- Real-time validation and formatting
- Supports comma-separated phone numbers
- Phone number format: 10-15 digits with country code (no + or 0 prefix)

#### Backend Processing
- Validates phone number format (10-15 digits)
- Creates queue entries for each individual number
- Uses dummy contact data for individual numbers
- Processes alongside group contacts

## Usage Instructions

### 1. Creating a Campaign with Individual Numbers
1. Go to WhatsApp Templates → Create New Campaign
2. Select your template
3. In the "Contacts and Schedule" section:
   - Choose contact group (optional - can be left empty)
   - **NEW**: Enter individual phone numbers in the textarea
4. Format: `1234567890, 9876543210, 5551234567`
5. Schedule and send

**Important**: You can now send campaigns to individual phone numbers without selecting any contact group!

### 2. Phone Number Format
- **Correct**: `1234567890, 9876543210`
- **Incorrect**: `+1234567890, 01234567890`
- Must be 10-15 digits
- Include country code (without + or 0 prefix)

### 3. Validation Features
- Real-time formatting (removes invalid characters)
- Length validation (10-15 digits)
- Visual feedback (green checkmark for valid numbers)
- Error highlighting for invalid numbers

## Technical Implementation

### Files Modified
1. **`resources/views/whatsapp/template-send-message.blade.php`**
   - Added phone numbers textarea field
   - Added JavaScript validation

2. **`app/Yantrana/Components/WhatsAppService/WhatsAppServiceEngine.php`**
   - Added individual phone number processing logic
   - Enhanced campaign creation to handle both groups and individual numbers

### Key Features
- **Backward Compatible**: Existing functionality unchanged
- **Flexible**: Can use groups, individual numbers, or both
- **Validated**: Real-time phone number validation
- **Efficient**: Uses existing queue system
- **Trackable**: Full campaign tracking and status updates

## Example Usage

### Scenario 1: Send to Specific Numbers Only (NEW - Now Supported!)
```
Contact Group: (leave empty - this is now allowed!)
Individual Numbers: 1234567890, 9876543210, 5551234567
```

### Scenario 2: Send to Group + Additional Numbers
```
Contact Group: "VIP Customers"
Individual Numbers: 1111111111, 2222222222
```

### Scenario 3: Send to All Contacts + Additional Numbers
```
Contact Group: "All Contacts"
Individual Numbers: 3333333333, 4444444444
```

## Benefits
1. **No Contact Management**: Send to numbers without adding to database
2. **Quick Campaigns**: Rapid deployment to specific numbers
3. **Flexible Targeting**: Mix groups and individual numbers
4. **Cost Effective**: No need to maintain contact database for one-time sends
5. **Compliance**: Respects opt-out preferences for existing contacts

## Notes
- Individual numbers are treated as separate from your contact database
- No contact history is maintained for individual numbers
- All existing campaign features work with individual numbers
- Queue processing and error handling work the same way
