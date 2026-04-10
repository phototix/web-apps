# WhatsApp Incoming Messages API

This API allows external services to store incoming WhatsApp messages in group chats via HTTP requests. The API uses the `WHATSAPP_API_KEY` from the `.env` file for authentication.

## Base URL
```
http://your-domain.com/api/
```
For development:
```
http://127.0.0.1:8000/api/
```

## Authentication
All endpoints require the API key to be passed in the `X-API-Key` header:
```
X-API-Key: 8cd0de4e14cd240a97209625af4bdeb0
```

## Response Format
All API responses follow this format:

### Success Response
```json
{
  "success": true,
  "message": "Success message",
  "data": { ... },
  "timestamp": 1775529514
}
```

### Error Response
```json
{
  "success": false,
  "message": "Error message",
  "errors": { ... },
  "timestamp": 1775529514
}
```

## API Endpoints

### 1. Store Incoming Group Message
**POST** `/api/whatsapp/incoming/message`

Store an incoming WhatsApp message in a group chat.

**Headers:**
```
Content-Type: application/json
X-API-Key: 8cd0de4e14cd240a97209625af4bdeb0
```

**Request Body:**
```json
{
  "session_name": "default_session",
  "chat_id": "120363043023650559@g.us",
  "message_id": "3EB0C6D95A5B8A9392B6",
  "sender": "1234567890@s.whatsapp.net",
  "sender_name": "John Doe",
  "content": "Hello everyone!",
  "message_type": "chat",
  "is_from_me": false
}
```

**Minimal Request Example (only required fields):**
```json
{
  "session_name": "default_session",
  "chat_id": "120363043023650559@g.us",
  "message_id": "3EB0C6D95A5B8A9392B6",
  "sender": "1234567890@s.whatsapp.net",
  "content": "Hello everyone!",
  "message_type": "chat",
  "is_from_me": false
}
```

**Field Descriptions:**
- `session_name` (required): The WhatsApp session name (string)
- `chat_id` (required): WhatsApp group ID (ends with @g.us)
- `message_id` (required): Unique message identifier from WhatsApp
- `sender` (required): Sender's WhatsApp ID
- `sender_name` (optional): Display name of the sender
- `content` (required for text messages): Message text content
- `message_type` (required): Type of message - "chat", "image", "video", "audio", "document", "sticker"
- `timestamp` (optional): Unix timestamp in milliseconds of when message was sent (default: current time)
- `is_from_me` (required): Boolean indicating if message was sent by the session owner
- `quoted_message_id` (optional): ID of quoted message if applicable
- `media_url` (optional): URL to media file for media messages
- `media_caption` (optional): Caption for media messages
- `caption` (optional): Additional caption text for media messages (image, video, document)
- `media_type` (optional): MIME type for media messages
- `media_size` (optional): Size of media file in bytes

**Success Response (201):**
```json
{
  "success": true,
  "message": "Message stored successfully",
  "data": {
    "message_id": 12345,
    "group_id": "120363043023650559@g.us",
    "session_name": "default_session",
    "session_id": 1,
    "stored_at": "2026-04-09 10:30:45"
  },
  "timestamp": 1775529514
}
```

**Error Responses:**
- `400` - Missing required fields or invalid data
- `401` - Invalid or missing API key
- `403` - Session not found or access denied
- `404` - Group not found
- `422` - Validation errors
- `500` - Server error

---

### 2. Store Multiple Incoming Messages (Batch)
**POST** `/api/whatsapp/incoming/messages/batch`

Store multiple incoming WhatsApp messages in a single request.

**Headers:**
```
Content-Type: application/json
X-API-Key: 8cd0de4e14cd240a97209625af4bdeb0
```

**Request Body:**
```json
{
  "messages": [
    {
      "session_name": "default_session",
      "chat_id": "120363043023650559@g.us",
      "message_id": "3EB0C6D95A5B8A9392B6",
      "sender": "1234567890@s.whatsapp.net",
      "content": "Hello everyone!",
      "message_type": "chat",
      "is_from_me": false
    },
    {
      "session_name": "default_session",
      "chat_id": "120363043023650559@g.us",
      "message_id": "4FC1D7EA6B6C9B4A283C",
      "sender": "0987654321@s.whatsapp.net",
      "content": "Hi John!",
      "message_type": "chat",
      "is_from_me": false
    }
  ]
}
```

**Success Response (201):**
```json
{
  "success": true,
  "message": "2 messages stored successfully",
  "data": {
    "stored_count": 2,
    "failed_count": 0,
    "failed_messages": [],
    "session_name": "default_session",
    "session_id": 1
  },
  "timestamp": 1775529514
}
```

**Error Responses:**
- `400` - Missing required fields or invalid data
- `401` - Invalid or missing API key
- `403` - Session not found or access denied
- `422` - Validation errors
- `500` - Server error

---

### 3. Verify API Key
**GET** `/api/whatsapp/incoming/verify`

Verify that the API key is valid and get session information.

**Headers:**
```
X-API-Key: 8cd0de4e14cd240a97209625af4bdeb0
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "API key is valid",
  "data": {
    "valid": true,
    "api_key": "8cd0de4e14cd240a97209625af4bdeb0",
    "whatsapp_endpoint": "http://localhost:3000",
    "available_sessions": [
      {
        "id": 1,
        "session_name": "default_session",
        "user_id": 1,
        "status": "active"
      }
    ]
  },
  "timestamp": 1775529514
}
```

**Error Responses:**
- `401` - Invalid or missing API key

---

## Using cURL Examples

### Store Single Message:
```bash
curl -X POST http://127.0.0.1:8000/api/whatsapp/incoming/message \
  -H "Content-Type: application/json" \
  -H "X-API-Key: 8cd0de4e14cd240a97209625af4bdeb0" \
  -d '{
    "session_name": "default_session",
    "chat_id": "120363043023650559@g.us",
    "message_id": "3EB0C6D95A5B8A9392B6",
    "sender": "1234567890@s.whatsapp.net",
    "content": "Hello everyone!",
    "message_type": "chat",
    "is_from_me": false
  }'
```

**With optional fields:**
```bash
curl -X POST http://127.0.0.1:8000/api/whatsapp/incoming/message \
  -H "Content-Type: application/json" \
  -H "X-API-Key: 8cd0de4e14cd240a97209625af4bdeb0" \
  -d '{
    "session_name": "default_session",
    "chat_id": "120363043023650559@g.us",
    "message_id": "3EB0C6D95A5B8A9392B6",
    "sender": "1234567890@s.whatsapp.net",
    "sender_name": "John Doe",
    "content": "Hello everyone!",
    "message_type": "chat",
    "timestamp": 1775529514000,
    "is_from_me": false,
    "quoted_message_id": "2DA1B5C84A4A7A8281A5",
    "media_url": "https://example.com/image.jpg",
    "media_caption": "Check out this image",
    "media_type": "image/jpeg",
    "media_size": 102400
  }'
```

### Store Batch Messages:
```bash
curl -X POST http://127.0.0.1:8000/api/whatsapp/incoming/messages/batch \
  -H "Content-Type: application/json" \
  -H "X-API-Key: 8cd0de4e14cd240a97209625af4bdeb0" \
  -d '{
    "messages": [
      {
        "session_name": "default_session",
        "chat_id": "120363043023650559@g.us",
        "message_id": "3EB0C6D95A5B8A9392B6",
        "sender": "1234567890@s.whatsapp.net",
        "content": "Hello everyone!",
        "message_type": "chat",
        "is_from_me": false
      }
    ]
  }'
```

### Verify API Key:
```bash
curl -X GET http://127.0.0.1:8000/api/whatsapp/incoming/verify \
  -H "X-API-Key: 8cd0de4e14cd240a97209625af4bdeb0"
```

## Field Defaults and Important Notes

### Default Values:
- `timestamp`: Current server time in milliseconds (time() * 1000)
- `sender_name`: Uses `sender` value if not provided
- `media_url`, `media_caption`, `media_type`, `media_size`: `null` if not provided
- `quoted_message_id`: `null` if not provided

### Important Notes:
1. **Session Name**: Must match an existing active session in the database
2. **Chat ID**: Must be a valid WhatsApp group ID ending with `@g.us`
3. **Message ID**: Must be unique per session and group (duplicate messages will be ignored)
4. **Timestamp**: If provided, should be in milliseconds (not seconds)
5. **Media Messages**: For media messages, `content` can be empty if `media_url` is provided
6. **Sender Field**: Should be a valid WhatsApp ID (phone number with @s.whatsapp.net suffix)

## Error Codes
- `200` - Success
- `201` - Created (message stored)
- `400` - Bad Request
- `401` - Unauthorized (invalid API key)
- `403` - Forbidden (session access denied)
- `404` - Not Found (group not found)
- `422` - Validation Error
- `500` - Internal Server Error

## Database Schema
Messages are stored in the `group_messages` table:

```sql
CREATE TABLE group_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id INT NOT NULL,
    group_id VARCHAR(255) NOT NULL,
    whatsapp_message_id VARCHAR(255) NOT NULL,
    sender VARCHAR(255) NOT NULL,
    sender_name VARCHAR(255),
    content TEXT,
    message_type ENUM('chat', 'image', 'video', 'audio', 'document', 'sticker') NOT NULL DEFAULT 'chat',
    timestamp BIGINT NOT NULL,
    is_from_me BOOLEAN NOT NULL DEFAULT FALSE,
    has_quoted_message BOOLEAN NOT NULL DEFAULT FALSE,
    quoted_message_id VARCHAR(255),
    media_url TEXT,
    media_caption TEXT,
    caption TEXT,
    media_type VARCHAR(100),
    media_size INT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_session_group (session_id, group_id),
    INDEX idx_timestamp (timestamp),
    INDEX idx_whatsapp_id (whatsapp_message_id),
    UNIQUE KEY unique_message (session_id, group_id, whatsapp_message_id)
);
```

## Finding Session Names
To get the available session names for your API key, use the Verify API Key endpoint:

```bash
curl -X GET http://127.0.0.1:8000/api/whatsapp/incoming/verify \
  -H "X-API-Key: 8cd0de4e14cd240a97209625af4bdeb0"
```

The response will include an `available_sessions` array with session names.

## Integration with WhatsApp Webhooks
This API can be used alongside the existing webhook system. When a webhook receives a message from WhatsApp, it can forward it to this API for storage.

Example webhook processor integration:
```php
// In webhook processing code:
$messageData = extractMessageFromWebhook($webhookPayload);
$apiResponse = callIncomingMessageAPI($messageData);
```

## Example Data Flow
1. WhatsApp sends message → Webhook receives it
2. Webhook extracts: `session_name`, `chat_id`, `message_id`, `sender`, `content`, `message_type`, `is_from_me`
3. Webhook calls this API with extracted data
4. API validates session by name, stores message in database
5. Message appears in the ERP Ezy Chat interface

## Security Notes
1. The API key (`WHATSAPP_API_KEY=8cd0de4e14cd240a97209625af4bdeb0`) should be kept secret
2. Use HTTPS in production environments
3. Consider implementing rate limiting for the API endpoints
4. Validate all incoming data to prevent injection attacks
5. Log API access for audit purposes

## Testing
Test the API endpoints using the provided cURL examples or tools like Postman. Ensure the API key matches the one in your `.env` file and that the session and group exist in the database.