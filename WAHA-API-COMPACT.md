# WAHA API Compact Reference

## Overview

This document provides a compact reference for the WAHA (WhatsApp HTTP API) endpoints.

**Important Notes:**
- **Session Parameter:** Session names (`{session}` in URL paths and `session` in request bodies) must be strings, not numeric values
- **Valid Examples:** `"default"`, `"main"`, `"backup"`, `"session1"`
- **Invalid Examples:** `123`, `1.5`, `0`
- **Key Endpoints:** All `sendText`, `sendImage`, `sendFile`, `sendVoice`, `sendVideo`, QR code, and session management endpoints require session parameter

## Presence

- `GET /api/{session}/presence` - Get all subscribed presence information.
- `POST /api/{session}/presence` - Set session presence
- `GET /api/{session}/presence/{chatId}` - Get the presence for the chat id. If it hasn't been subscribed - it also subscribes to it.
- `POST /api/{session}/presence/{chatId}/subscribe` - Subscribe to presence events for the chat.

## Profile

- `GET /api/{session}/profile` - Get my profile
- `PUT /api/{session}/profile/name` - Set my profile name
- `DELETE /api/{session}/profile/picture` - Delete profile picture
- `PUT /api/{session}/profile/picture` - Set profile picture
- `PUT /api/{session}/profile/status` - Set profile status (About)

## Labels

- `GET /api/{session}/labels` - Get all labels
- `POST /api/{session}/labels` - Create a new label
- `GET /api/{session}/labels/chats/{chatId}` - Get labels for the chat
- `PUT /api/{session}/labels/chats/{chatId}` - Save labels for the chat
- `DELETE /api/{session}/labels/{labelId}` - Delete a label
- `PUT /api/{session}/labels/{labelId}` - Update a label
- `GET /api/{session}/labels/{labelId}/chats` - Get chats by label

## Contacts

- `GET /api/contacts` - Get contact basic info
- `GET /api/contacts/about` - Gets the Contact's "about" info
- `GET /api/contacts/all` - Get all contacts
- `POST /api/contacts/block` - Block contact
- `GET /api/contacts/check-exists` - Check phone number is registered in WhatsApp.
- `GET /api/contacts/profile-picture` - Get contact's profile picture URL
- `POST /api/contacts/unblock` - Unblock contact
- `PUT /api/{session}/contacts/{chatId}` - Create or update contact
- `GET /api/{session}/contacts/{id}` - Get contact basic info
- `GET /api/{session}/lids` - Get all known lids to phone number mapping
- `GET /api/{session}/lids/count` - Get the number of known lids
- `GET /api/{session}/lids/pn/{phoneNumber}` - Get lid by phone number (chat id)
- `GET /api/{session}/lids/{lid}` - Get phone number by lid

## Groups

- `GET /api/{session}/groups` - Get all groups.
- `POST /api/{session}/groups` - Create a new group.
- `GET /api/{session}/groups/count` - Get the number of groups.
- `POST /api/{session}/groups/join` - Join group via code
- `GET /api/{session}/groups/join-info` - Get info about the group before joining.
- `POST /api/{session}/groups/refresh` - Refresh groups from the server.
- `DELETE /api/{session}/groups/{id}` - Delete the group.
- `GET /api/{session}/groups/{id}` - Get the group.
- `POST /api/{session}/groups/{id}/admin/demote` - Demotes participants to regular users.
- `POST /api/{session}/groups/{id}/admin/promote` - Promote participants to admin users.
- `PUT /api/{session}/groups/{id}/description` - Updates the group description.
- `GET /api/{session}/groups/{id}/invite-code` - Gets the invite code for the group.
- `POST /api/{session}/groups/{id}/invite-code/revoke` - Invalidates the current group invite code and generates a new one.
- `POST /api/{session}/groups/{id}/leave` - Leave the group.
- `GET /api/{session}/groups/{id}/participants` - Get participants
- `POST /api/{session}/groups/{id}/participants/add` - Add participants
- `POST /api/{session}/groups/{id}/participants/remove` - Remove participants
- `GET /api/{session}/groups/{id}/participants/v2` - Get group participants.
- `DELETE /api/{session}/groups/{id}/picture` - Delete group picture
- `GET /api/{session}/groups/{id}/picture` - Get group picture
- `PUT /api/{session}/groups/{id}/picture` - Set group picture
- `GET /api/{session}/groups/{id}/settings/security/info-admin-only` - Get the group's 'info admin only' settings.
- `PUT /api/{session}/groups/{id}/settings/security/info-admin-only` - Updates the group "info admin only" settings.
- `GET /api/{session}/groups/{id}/settings/security/messages-admin-only` - Get settings - who can send messages
- `PUT /api/{session}/groups/{id}/settings/security/messages-admin-only` - Update settings - who can send messages
- `PUT /api/{session}/groups/{id}/subject` - Updates the group subject

## Chats

- `GET /api/{session}/chats` - Get chats
- `GET /api/{session}/chats/overview` - Get chats overview. Includes all necessary things to build UI "your chats overview" page - chat id, name, picture, last message. Sorting by last message timestamp
- `POST /api/{session}/chats/overview` - Get chats overview. Use POST if you have too many "ids" params - GET can limit it
- `DELETE /api/{session}/chats/{chatId}` - Deletes the chat
- `POST /api/{session}/chats/{chatId}/archive` - Archive the chat
- `DELETE /api/{session}/chats/{chatId}/messages` - Clears all messages from the chat
- `GET /api/{session}/chats/{chatId}/messages` - Gets messages in the chat
- `POST /api/{session}/chats/{chatId}/messages/read` - Read unread messages in the chat
- `DELETE /api/{session}/chats/{chatId}/messages/{messageId}` - Deletes a message from the chat
- `GET /api/{session}/chats/{chatId}/messages/{messageId}` - Gets message by id
- `PUT /api/{session}/chats/{chatId}/messages/{messageId}` - Edits a message in the chat
- `POST /api/{session}/chats/{chatId}/messages/{messageId}/pin` - Pins a message in the chat
- `POST /api/{session}/chats/{chatId}/messages/{messageId}/unpin` - Unpins a message in the chat
- `GET /api/{session}/chats/{chatId}/picture` - Gets chat picture
- `POST /api/{session}/chats/{chatId}/unarchive` - Unarchive the chat
- `POST /api/{session}/chats/{chatId}/unread` - Unread the chat

## Events

- `POST /api/{session}/events` - Send an event message

## Calls

- `POST /api/{session}/calls/reject` - Reject incoming call

## Channels

- `GET /api/{session}/channels` - Get list of know channels
- `POST /api/{session}/channels` - Create a new channel.
- `POST /api/{session}/channels/search/by-text` - Search for channels (by text)
- `POST /api/{session}/channels/search/by-view` - Search for channels (by view)
- `GET /api/{session}/channels/search/categories` - Get list of categories for channel search
- `GET /api/{session}/channels/search/countries` - Get list of countries for channel search
- `GET /api/{session}/channels/search/views` - Get list of views for channel search
- `DELETE /api/{session}/channels/{id}` - Delete the channel.
- `GET /api/{session}/channels/{id}` - Get the channel info
- `POST /api/{session}/channels/{id}/follow` - Follow the channel.
- `GET /api/{session}/channels/{id}/messages/preview` - Preview channel messages
- `POST /api/{session}/channels/{id}/mute` - Mute the channel.
- `POST /api/{session}/channels/{id}/unfollow` - Unfollow the channel.
- `POST /api/{session}/channels/{id}/unmute` - Unmute the channel.

## Chatting

- `GET /api/checkNumberStatus` - Check number status
- `POST /api/forwardMessage`
- `GET /api/messages` - Get messages in a chat
- `PUT /api/reaction` - React to a message with an emoji
- `POST /api/reply` - DEPRECATED - you can set "reply_to" field when sending text, image, etc
- `POST /api/send/buttons/reply` - Reply on a button message
- `POST /api/send/link-custom-preview` - Send a text message with a CUSTOM link preview.
- `POST /api/sendButtons` - Send buttons message (interactive)
- `POST /api/sendContactVcard`
- `POST /api/sendFile` - Send a file
- `POST /api/sendImage` - Send an image
- `POST /api/sendLinkPreview`
- `POST /api/sendList` - Send a list message (interactive)
- `POST /api/sendLocation`
- `POST /api/sendPoll` - Send a poll with options
- `POST /api/sendPollVote` - Vote on a poll
- `POST /api/sendSeen`
- `GET /api/sendText` - Send a text message
- `POST /api/sendText` - Send a text message
- `POST /api/sendVideo` - Send a video
- `POST /api/sendVoice` - Send an voice message
- `PUT /api/star` - Star or unstar a message
- `POST /api/startTyping`
- `POST /api/stopTyping`

## Pairing

- `GET /api/screenshot` - Get a screenshot of the current WhatsApp session (**WEBJS/WPP** only)
- `GET /api/{session}/auth/qr` - Get QR code for pairing WhatsApp API.
- `POST /api/{session}/auth/request-code` - Request authentication code.

## Observability

- `GET /api/server/debug/browser/trace/{session}` - Collect and get a trace.json for Chrome DevTools 
- `GET /api/server/debug/cpu` - Collect and return a CPU profile for the current nodejs process
- `GET /api/server/debug/heapsnapshot` - Return a heapsnapshot for the current nodejs process
- `GET /api/server/environment` - Get the server environment
- `GET /api/server/status` - Get the server status
- `POST /api/server/stop` - Stop (and restart) the server
- `GET /api/server/version` - Get the version of the server
- `GET /api/version` - Get the server version 
- `GET /health` - Check the health of the server
- `GET /ping` - Ping the server

## Api Keys

- `GET /api/keys` - Get all API keys
- `POST /api/keys` - Create a new API key
- `DELETE /api/keys/{id}` - Delete an API key
- `PUT /api/keys/{id}` - Update an API key

## Sessions

- `GET /api/sessions` - List all sessions
- `POST /api/sessions` - Create a session
- `POST /api/sessions/logout` - Logout and Delete session.
- `POST /api/sessions/start` - Upsert and Start session
- `POST /api/sessions/stop` - Stop (and Logout if asked) session
- `DELETE /api/sessions/{session}` - Delete the session
- `GET /api/sessions/{session}` - Get session information
- `PUT /api/sessions/{session}` - Update a session
- `POST /api/sessions/{session}/logout` - Logout from the session
- `GET /api/sessions/{session}/me` - Get information about the authenticated account
- `POST /api/sessions/{session}/restart` - Restart the session
- `POST /api/sessions/{session}/start` - Start the session
- `POST /api/sessions/{session}/stop` - Stop the session

## Media

- `POST /api/{session}/media/convert/video` - Convert video to WhatsApp format (mp4)
- `POST /api/{session}/media/convert/voice` - Convert voice to WhatsApp format (opus)

## Status

- `POST /api/{session}/status/delete` - DELETE sent status
- `POST /api/{session}/status/image` - Send image status
- `GET /api/{session}/status/new-message-id` - Generate message ID you can use to batch contacts
- `POST /api/{session}/status/text` - Send text status
- `POST /api/{session}/status/video` - Send video status
- `POST /api/{session}/status/voice` - Send voice status

## Apps

- `GET /api/apps` - List all apps for a session
- `POST /api/apps` - Create a new app
- `DELETE /api/apps/{id}` - Delete an app
- `GET /api/apps/{id}` - Get app by ID
- `PUT /api/apps/{id}` - Update an existing app


**Total Endpoints:** 154
