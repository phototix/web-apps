# WAHA API Documentation

## Overview

This document provides a detailed reference for the WAHA (WhatsApp HTTP API) endpoints.

## Important Notes

### Session Parameter
- **Parameter:** `{session}` in URL paths and `session` in request bodies
- **Type:** String (not numeric)
- **Required:** Yes
- **Description:** Session identifier name
- **Valid Examples:** `"default"`, `"main"`, `"backup"`, `"session1"`
- **Invalid Examples:** `123`, `1.5`, `0`

### Key Endpoints Requiring Session Parameter

**In URL paths:**
- `/api/{session}/auth/qr` - QR code generation
- `/api/{session}/profile/*` - Profile management
- `/api/{session}/chats/*` - Chat operations
- `/api/{session}/groups/*` - Group management
- `/api/{session}/contacts/*` - Contact operations
- `/api/{session}/labels/*` - Label management
- `/api/{session}/presence/*` - Presence management
- `/api/{session}/status/*` - Status updates
- `/api/{session}/media/*` - Media conversion

**In request bodies:**
- `sendText`, `sendImage`, `sendFile`, `sendVoice`, `sendVideo` - All messaging endpoints
- `sendButtons`, `sendList`, `sendPoll` - Interactive message endpoints
- `forwardMessage`, `reply` - Message forwarding and replies
- `sendSeen`, `startTyping`, `stopTyping` - Chat indicators
- `reaction`, `star` - Message reactions

### Authentication
- All endpoints require API key authentication via the `X-API-Key` header
- API keys can be managed via the `/api/keys` endpoints

## API Categories

### 📱 Pairing (3 endpoints)
Authentication and QR code pairing for WhatsApp sessions.

### 🔑 Api Keys (4 endpoints)
Create, read, update, and delete API keys for authentication.

### 🖥️ Sessions (13 endpoints)
Session lifecycle management including create, start, stop, restart, and delete operations.

### 🆔 Profile (5 endpoints)
Manage user profile information including name, status, and profile picture.

### 📤 Chatting (38 endpoints)
Core messaging functionality including text, images, files, videos, voice messages, buttons, lists, polls, and reactions.

### 👤 Contacts (13 endpoints)
Contact management including block/unblock, profile pictures, and phone number verification.

### 👥 Groups (27 endpoints)
Comprehensive group management including create, join, leave, participant management, and group settings.

### 💬 Chats (16 endpoints)
Chat operations including archiving, message management, and chat overview.

### 🏷️ Labels (7 endpoints)
Label management for organizing chats.

### ✅ Presence (4 endpoints)
User presence status management and subscription.

### 🟢 Status (6 endpoints)
Status message functionality including text, image, video, and voice status updates.

### 📅 Events (1 endpoint)
Send event messages.

### 📞 Calls (2 endpoints)
Call-related operations.

### 📢 Channels (2 endpoints)
Channel management.

### 🖼️ Media (2 endpoints)
Media conversion utilities for video and voice files.

### 🔍 Observability (9 endpoints)
Server monitoring, debugging, and health checks.

### 🧩 Apps (5 endpoints)
App management for session extensions.

## Complete Endpoint List

### 📱 Pairing
**Note:** Session parameter in URL path (`{session}`) must be string, not numeric

- `GET /api/{session}/auth/qr` - Get QR code for pairing WhatsApp API.
- `POST /api/{session}/auth/request-code` - Request authentication code.
- `GET /api/screenshot` - Get a screenshot of the current WhatsApp session (**WEBJS/WPP** only)

### 🔑 Api Keys
- `GET /api/keys` - Get all API keys
- `POST /api/keys` - Create a new API key
- `DELETE /api/keys/{id}` - Delete an API key
- `PUT /api/keys/{id}` - Update an API key

### 🖥️ Sessions
**Note:** Session parameter in URL path (`{session}`) must be string, not numeric

- `GET /api/sessions` - List all sessions
- `POST /api/sessions` - Create a session
- `DELETE /api/sessions/{session}` - Delete the session
- `GET /api/sessions/{session}` - Get session information
- `PUT /api/sessions/{session}` - Update a session
- `GET /api/sessions/{session}/me` - Get information about the authenticated account
- `POST /api/sessions/{session}/start` - Start the session
- `POST /api/sessions/{session}/stop` - Stop the session
- `POST /api/sessions/{session}/restart` - Restart the session
- `POST /api/sessions/{session}/logout` - Logout from the session
- `POST /api/sessions/start` - Upsert and Start session
- `POST /api/sessions/stop` - Stop (and Logout if asked) session
- `POST /api/sessions/logout` - Logout and Delete session.

### 🆔 Profile
- `GET /api/{session}/profile` - Get my profile
- `PUT /api/{session}/profile/name` - Set my profile name
- `PUT /api/{session}/profile/status` - Set profile status (About)
- `PUT /api/{session}/profile/picture` - Set profile picture
- `DELETE /api/{session}/profile/picture` - Delete profile picture

### 📤 Chatting
**Note:** All messaging endpoints require `session` parameter in request body (string, not numeric)

- `GET /api/sendText` - Send a text message
- `POST /api/sendText` - Send a text message
- `POST /api/sendImage` - Send an image
- `POST /api/sendFile` - Send a file
- `POST /api/sendVoice` - Send a voice message
- `POST /api/sendVideo` - Send a video
- `POST /api/send/link-custom-preview` - Send a link with custom preview
- `POST /api/sendButtons` - Send buttons
- `POST /api/sendList` - Send a list
- `POST /api/forwardMessage` - Forward a message
- `POST /api/sendSeen` - Send seen receipt
- `POST /api/startTyping` - Start typing indicator
- `POST /api/stopTyping` - Stop typing indicator
- `PUT /api/reaction` - Add/remove reaction
- `PUT /api/star` - Star/unstar message
- `POST /api/sendPoll` - Send a poll
- `POST /api/sendPollVote` - Send poll vote
- `POST /api/sendLocation` - Send location
- `POST /api/sendContactVcard` - Send contact vcard
- `POST /api/send/buttons/reply` - Send buttons reply
- `GET /api/messages` - Get messages
- `GET /api/checkNumberStatus` - Check phone number status
- `POST /api/reply` - Reply to a message
- `GET /api/{session}/chats/{chatId}/messages` - Gets messages in the chat
- `GET /api/{session}/chats/{chatId}/messages/{messageId}` - Gets message by id
- `PUT /api/{session}/chats/{chatId}/messages/{messageId}` - Edits a message in the chat
- `DELETE /api/{session}/chats/{chatId}/messages/{messageId}` - Deletes a message from the chat
- `POST /api/{session}/chats/{chatId}/messages/{messageId}/pin` - Pins a message in the chat
- `POST /api/{session}/chats/{chatId}/messages/{messageId}/unpin` - Unpins a message in the chat
- `POST /api/{session}/chats/{chatId}/messages/read` - Read unread messages in the chat
- `DELETE /api/{session}/chats/{chatId}/messages` - Clears all messages from the chat
- `GET /api/{session}/chats` - Get chats
- `GET /api/{session}/chats/overview` - Get chats overview
- `POST /api/{session}/chats/overview` - Get chats overview (POST version)
- `DELETE /api/{session}/chats/{chatId}` - Deletes the chat
- `POST /api/{session}/chats/{chatId}/archive` - Archive the chat
- `POST /api/{session}/chats/{chatId}/unarchive` - Unarchive the chat
- `POST /api/{session}/chats/{chatId}/unread` - Unread the chat

### 👤 Contacts
- `GET /api/contacts` - Get contact basic info
- `GET /api/contacts/all` - Get all contacts
- `GET /api/contacts/about` - Gets the Contact's "about" info
- `GET /api/contacts/profile-picture` - Get contact's profile picture URL
- `GET /api/contacts/check-exists` - Check phone number is registered in WhatsApp.
- `POST /api/contacts/block` - Block contact
- `POST /api/contacts/unblock` - Unblock contact
- `GET /api/{session}/contacts/{id}` - Get contact basic info
- `PUT /api/{session}/contacts/{chatId}` - Create or update contact
- `GET /api/{session}/lids` - Get all known lids to phone number mapping
- `GET /api/{session}/lids/count` - Get the number of known lids
- `GET /api/{session}/lids/{lid}` - Get phone number by lid
- `GET /api/{session}/lids/pn/{phoneNumber}` - Get lid by phone number (chat id)

### 👥 Groups
- `GET /api/{session}/groups` - Get all groups.
- `POST /api/{session}/groups` - Create a new group.
- `GET /api/{session}/groups/count` - Get the number of groups.
- `POST /api/{session}/groups/refresh` - Refresh groups from the server.
- `POST /api/{session}/groups/join` - Join group via code
- `GET /api/{session}/groups/join-info` - Get info about the group before joining.
- `GET /api/{session}/groups/{id}` - Get the group.
- `DELETE /api/{session}/groups/{id}` - Delete the group.
- `POST /api/{session}/groups/{id}/leave` - Leave the group.
- `PUT /api/{session}/groups/{id}/subject` - Updates the group subject
- `PUT /api/{session}/groups/{id}/description` - Updates the group description
- `GET /api/{session}/groups/{id}/invite-code` - Gets the invite code for the group.
- `POST /api/{session}/groups/{id}/invite-code/revoke` - Invalidates the current group invite code and generates a new one.
- `GET /api/{session}/groups/{id}/picture` - Get group picture
- `PUT /api/{session}/groups/{id}/picture` - Set group picture
- `DELETE /api/{session}/groups/{id}/picture` - Delete group picture
- `GET /api/{session}/groups/{id}/participants` - Get participants
- `GET /api/{session}/groups/{id}/participants/v2` - Get group participants.
- `POST /api/{session}/groups/{id}/participants/add` - Add participants
- `POST /api/{session}/groups/{id}/participants/remove` - Remove participants
- `POST /api/{session}/groups/{id}/admin/promote` - Promote participants to admin users.
- `POST /api/{session}/groups/{id}/admin/demote` - Demotes participants to regular users.
- `GET /api/{session}/groups/{id}/settings/security/messages-admin-only` - Get settings - who can send messages
- `PUT /api/{session}/groups/{id}/settings/security/messages-admin-only` - Update settings - who can send messages
- `GET /api/{session}/groups/{id}/settings/security/info-admin-only` - Get the group's 'info admin only' settings.
- `PUT /api/{session}/groups/{id}/settings/security/info-admin-only` - Updates the group "info admin only" settings.

### 💬 Chats
- `GET /api/{session}/chats` - Get chats
- `GET /api/{session}/chats/overview` - Get chats overview
- `POST /api/{session}/chats/overview` - Get chats overview (POST version)
- `DELETE /api/{session}/chats/{chatId}` - Deletes the chat
- `POST /api/{session}/chats/{chatId}/archive` - Archive the chat
- `POST /api/{session}/chats/{chatId}/unarchive` - Unarchive the chat
- `POST /api/{session}/chats/{chatId}/unread` - Unread the chat
- `GET /api/{session}/chats/{chatId}/picture` - Gets chat picture
- `GET /api/{session}/chats/{chatId}/messages` - Gets messages in the chat
- `DELETE /api/{session}/chats/{chatId}/messages` - Clears all messages from the chat
- `GET /api/{session}/chats/{chatId}/messages/{messageId}` - Gets message by id
- `PUT /api/{session}/chats/{chatId}/messages/{messageId}` - Edits a message in the chat
- `DELETE /api/{session}/chats/{chatId}/messages/{messageId}` - Deletes a message from the chat
- `POST /api/{session}/chats/{chatId}/messages/{messageId}/pin` - Pins a message in the chat
- `POST /api/{session}/chats/{chatId}/messages/{messageId}/unpin` - Unpins a message in the chat
- `POST /api/{session}/chats/{chatId}/messages/read` - Read unread messages in the chat

### 🏷️ Labels
- `GET /api/{session}/labels` - Get all labels
- `POST /api/{session}/labels` - Create a new label
- `GET /api/{session}/labels/{labelId}/chats` - Get chats by label
- `PUT /api/{session}/labels/{labelId}` - Update a label
- `DELETE /api/{session}/labels/{labelId}` - Delete a label
- `GET /api/{session}/labels/chats/{chatId}` - Get labels for the chat
- `PUT /api/{session}/labels/chats/{chatId}` - Save labels for the chat

### ✅ Presence
- `GET /api/{session}/presence` - Get all subscribed presence information.
- `POST /api/{session}/presence` - Set session presence
- `GET /api/{session}/presence/{chatId}` - Get the presence for the chat id.
- `POST /api/{session}/presence/{chatId}/subscribe` - Subscribe to presence events for the chat.

### 🟢 Status
- `GET /api/{session}/status/new-message-id` - Generate message ID you can use to batch contacts
- `POST /api/{session}/status/text` - Send text status
- `POST /api/{session}/status/image` - Send image status
- `POST /api/{session}/status/video` - Send video status
- `POST /api/{session}/status/voice` - Send voice status
- `POST /api/{session}/status/delete` - DELETE sent status

### 📅 Events
- `POST /api/{session}/events` - Send an event message

### 📞 Calls
- `GET /api/{session}/calls` - Get all calls
- `POST /api/{session}/calls/end` - End a call

### 📢 Channels
- `GET /api/{session}/channels` - Get all channels
- `POST /api/{session}/channels` - Create a new channel

### 🖼️ Media
- `POST /api/{session}/media/convert/video` - Convert video to WhatsApp format (mp4)
- `POST /api/{session}/media/convert/voice` - Convert voice to WhatsApp format (opus)

### 🔍 Observability
- `GET /health` - Check the health of the server
- `GET /ping` - Ping the server
- `GET /api/version` - Get the server version 
- `GET /api/server/version` - Get the version of the server
- `GET /api/server/status` - Get the server status
- `GET /api/server/environment` - Get the server environment
- `POST /api/server/stop` - Stop (and restart) the server
- `GET /api/server/debug/cpu` - Collect and return a CPU profile
- `GET /api/server/debug/heapsnapshot` - Return a heapsnapshot
- `GET /api/server/debug/browser/trace/{session}` - Collect and get a trace.json for Chrome DevTools

### 🧩 Apps
- `GET /api/apps` - List all apps for a session
- `POST /api/apps` - Create a new app
- `GET /api/apps/{id}` - Get app by ID
- `PUT /api/apps/{id}` - Update an existing app
- `DELETE /api/apps/{id}` - Delete an app

## Total Endpoints: 154

## Usage Examples

### Starting a Session
```bash
curl -X POST "http://localhost:3000/api/sessions" \
  -H "X-API-Key: your-api-key" \
  -H "Content-Type: application/json" \
  -d '{"name": "default", "config": {}}'
```

### Sending a Message
```bash
curl -X POST "http://localhost:3000/api/sendText" \
  -H "X-API-Key: your-api-key" \
  -H "Content-Type: application/json" \
  -d '{
    "session": "default",
    "chatId": "1234567890@c.us",
    "text": "Hello World",
    "quotedMessageId": null
  }'
```

### Getting QR Code
```bash
curl -X GET "http://localhost:3000/api/default/auth/qr?format=image" \
  -H "X-API-Key: your-api-key"
```