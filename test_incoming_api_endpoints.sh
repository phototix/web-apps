#!/bin/bash

# Test script for WhatsApp Incoming Messages API
API_KEY="8cd0de4e14cd240a97209625af4bdeb0"
BASE_URL="http://localhost:8000"

echo "=== Testing WhatsApp Incoming Messages API ===\n"

# 1. Test API key verification
echo "1. Testing API key verification..."
curl -s -X GET "$BASE_URL/api/whatsapp/incoming/verify" \
  -H "X-API-Key: $API_KEY" | jq .

echo "\n2. Testing with invalid API key..."
curl -s -X GET "$BASE_URL/api/whatsapp/incoming/verify" \
  -H "X-API-Key: invalid_key" | jq .

# 2. Test storing single message
echo "\n3. Testing store single message..."
SESSION_NAME="user1_session1_6df3ac42"
CHAT_ID="120363043023650559@g.us"
MESSAGE_ID="API_TEST_$(date +%s)"

curl -s -X POST "$BASE_URL/api/whatsapp/incoming/message" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: $API_KEY" \
  -d "{
    \"session_name\": \"$SESSION_NAME\",
    \"chat_id\": \"$CHAT_ID\",
    \"message_id\": \"$MESSAGE_ID\",
    \"sender\": \"1234567890@s.whatsapp.net\",
    \"content\": \"Hello from API test!\",
    "message_type": "chat",
    \"is_from_me\": false
  }" | jq .

# 3. Test storing message with all optional fields
echo "\n4. Testing store message with optional fields..."
MESSAGE_ID="API_TEST_FULL_$(date +%s)"

curl -s -X POST "$BASE_URL/api/whatsapp/incoming/message" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: $API_KEY" \
  -d "{
    \"session_name\": \"$SESSION_NAME\",
    \"chat_id\": \"$CHAT_ID\",
    \"message_id\": \"$MESSAGE_ID\",
    \"sender\": \"0987654321@s.whatsapp.net\",
    \"sender_name\": \"Jane Smith\",
    \"content\": \"Check out this image!\",
    \"message_type\": \"image\",
    \"timestamp\": $(($(date +%s) * 1000)),
    \"is_from_me\": false,
    \"quoted_message_id\": \"QUOTED_123\",
    \"media_url\": \"https://example.com/image.jpg\",
    \"media_caption\": \"Beautiful sunset\",
    \"media_type\": \"image/jpeg\",
    \"media_size\": 102400
  }" | jq .

# 4. Test batch messages
echo "\n5. Testing batch messages..."
curl -s -X POST "$BASE_URL/api/whatsapp/incoming/messages/batch" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: $API_KEY" \
  -d "{
    \"messages\": [
      {
        \"session_name\": \"$SESSION_NAME\",
        \"chat_id\": \"$CHAT_ID\",
        \"message_id\": \"BATCH_1_$(date +%s)\",
        \"sender\": \"1111111111@s.whatsapp.net\",
        \"content\": \"First batch message\",
        "message_type": "chat",
        \"is_from_me\": false
      },
      {
        \"session_name\": \"$SESSION_NAME\",
        \"chat_id\": \"$CHAT_ID\",
        \"message_id\": \"BATCH_2_$(date +%s)\",
        \"sender\": \"2222222222@s.whatsapp.net\",
        \"content\": \"Second batch message\",
        "message_type": "chat",
        \"is_from_me\": false
      }
    ]
  }" | jq .

# 5. Test error cases
echo "\n6. Testing error - missing required field..."
curl -s -X POST "$BASE_URL/api/whatsapp/incoming/message" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: $API_KEY" \
  -d "{
    \"session_name\": \"$SESSION_NAME\",
    \"chat_id\": \"$CHAT_ID\",
    \"message_id\": \"ERROR_TEST\",
    \"sender\": \"1234567890@s.whatsapp.net\"
    // Missing content and message_type
  }" | jq .

echo "\n7. Testing error - invalid chat ID..."
curl -s -X POST "$BASE_URL/api/whatsapp/incoming/message" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: $API_KEY" \
  -d "{
    \"session_name\": \"$SESSION_NAME\",
    \"chat_id\": \"not_a_group_id\",
    \"message_id\": \"ERROR_TEST_2\",
    \"sender\": \"1234567890@s.whatsapp.net\",
    \"content\": \"Test message\",
    "message_type": "chat",
    \"is_from_me\": false
  }" | jq .

echo "\n8. Testing error - non-existent session..."
curl -s -X POST "$BASE_URL/api/whatsapp/incoming/message" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: $API_KEY" \
  -d "{
    \"session_name\": \"non_existent_session\",
    \"chat_id\": \"$CHAT_ID\",
    \"message_id\": \"ERROR_TEST_3\",
    \"sender\": \"1234567890@s.whatsapp.net\",
    \"content\": \"Test message\",
    "message_type": "chat",
    \"is_from_me\": false
  }" | jq .

echo "\n=== API Tests Complete ==="