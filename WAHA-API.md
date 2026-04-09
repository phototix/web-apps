# WAHA API Documentation

## Overview

This document provides a compact reference for the WAHA (WhatsApp HTTP API) endpoints.

## Table of Contents

- [Presence](#presence)
- [Profile](#profile)
- [Labels](#labels)
- [Contacts](#contacts)
- [Groups](#groups)
- [Chats](#chats)
- [Events](#events)
- [Calls](#calls)
- [Channels](#channels)
- [Chatting](#chatting)
- [Pairing](#pairing)
- [Observability](#observability)
- [Api Keys](#api-keys)
- [Sessions](#sessions)
- [Media](#media)
- [Status](#status)
- [Apps](#apps)

---

## Presence

### `POST /api/{session}/presence`

**Summary:** Set session presence

**Operation ID:** `PresenceController_setPresence`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Request Body:** `WAHASessionPresence`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

### `GET /api/{session}/presence`

**Summary:** Get all subscribed presence information\.

**Operation ID:** `PresenceController_getPresenceAll`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `Array[WAHAChatPresences]` |

---

### `GET /api/{session}/presence/{chatId}`

**Summary:** Get the presence for the chat id\. If it hasn't been subscribed \- it also subscribes to it\.

**Operation ID:** `PresenceController_getPresence`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `chatId` | `path` | Yes | `string` | Chat ID |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `WAHAChatPresences` |

---

### `POST /api/{session}/presence/{chatId}/subscribe`

**Summary:** Subscribe to presence events for the chat\.

**Operation ID:** `PresenceController_subscribe`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `chatId` | `path` | Yes | `string` | Chat ID |

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

## Profile

### `GET /api/{session}/profile`

**Summary:** Get my profile

**Operation ID:** `ProfileController_getMyProfile`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `MyProfile` |

---

### `PUT /api/{session}/profile/name`

**Summary:** Set my profile name

**Operation ID:** `ProfileController_setProfileName`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Request Body:** `ProfileNameRequest`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `Result` |

---

### `PUT /api/{session}/profile/picture`

**Summary:** Set profile picture

**Operation ID:** `ProfileController_setProfilePicture`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Request Body:** `ProfilePictureRequest`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `Result` |

---

### `DELETE /api/{session}/profile/picture`

**Summary:** Delete profile picture

**Operation ID:** `ProfileController_deleteProfilePicture`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `Result` |

---

### `PUT /api/{session}/profile/status`

**Summary:** Set profile status \(About\)

**Operation ID:** `ProfileController_setProfileStatus`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Request Body:** `ProfileStatusRequest`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `Result` |

---

## Labels

### `GET /api/{session}/labels`

**Summary:** Get all labels

**Operation ID:** `LabelsController_getAll`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `Array[Label]` |

---

### `POST /api/{session}/labels`

**Summary:** Create a new label

**Operation ID:** `LabelsController_create`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Request Body:** `LabelBody`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `Label` |

---

### `GET /api/{session}/labels/chats/{chatId}`

**Summary:** Get labels for the chat

**Operation ID:** `LabelsController_getChatLabels`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `chatId` | `path` | Yes | `string` | Chat ID |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `Array[Label]` |

---

### `PUT /api/{session}/labels/chats/{chatId}`

**Summary:** Save labels for the chat

**Operation ID:** `LabelsController_putChatLabels`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `chatId` | `path` | Yes | `string` | Chat ID |

**Request Body:** `SetLabelsRequest`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `PUT /api/{session}/labels/{labelId}`

**Summary:** Update a label

**Operation ID:** `LabelsController_update`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `labelId` | `path` | Yes | `string` |  |

**Request Body:** `LabelBody`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `Label` |

---

### `DELETE /api/{session}/labels/{labelId}`

**Summary:** Delete a label

**Operation ID:** `LabelsController_delete`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `labelId` | `path` | Yes | `string` |  |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `object` |

---

### `GET /api/{session}/labels/{labelId}/chats`

**Summary:** Get chats by label

**Operation ID:** `LabelsController_getChatsByLabel`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `labelId` | `path` | Yes | `string` |  |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

## Contacts

### `GET /api/contacts`

**Summary:** Get contact basic info

**Operation ID:** `ContactsController_get`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `contactId` | `query` | Yes | `string` |  |
| `session` | `query` | Yes | `string` |  |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `GET /api/contacts/about`

**Summary:** Gets the Contact's "about" info

**Operation ID:** `ContactsController_getAbout`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `contactId` | `query` | Yes | `string` |  |
| `session` | `query` | Yes | `string` |  |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `GET /api/contacts/all`

**Summary:** Get all contacts

**Operation ID:** `ContactsController_getAll`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `query` | Yes | `string` |  |
| `sortBy` | `query` | No | `string` | Sort by field |
| `sortOrder` | `query` | No | `string` | Sort order \- <b>desc</b>ending \(Z => A, New first\) or <b>asc</b>ending \(A => Z, Old first\) |
| `limit` | `query` | No | `number` |  |
| `offset` | `query` | No | `number` |  |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `POST /api/contacts/block`

**Summary:** Block contact

**Operation ID:** `ContactsController_block`

**Request Body:** `ContactRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

### `GET /api/contacts/check-exists`

**Summary:** Check phone number is registered in WhatsApp\.

**Operation ID:** `ContactsController_checkExists`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `phone` | `query` | Yes | `string` | The phone number to check |
| `session` | `query` | Yes | `string` |  |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `WANumberExistResult` |

---

### `GET /api/contacts/profile-picture`

**Summary:** Get contact's profile picture URL

**Operation ID:** `ContactsController_getProfilePicture`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `contactId` | `query` | Yes | `string` |  |
| `refresh` | `query` | No | `boolean` | Refresh the picture from the server \(24h cache by default\)\. Do not refresh if not needed, you can get rate limit error |
| `session` | `query` | Yes | `string` |  |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `POST /api/contacts/unblock`

**Summary:** Unblock contact

**Operation ID:** `ContactsController_unblock`

**Request Body:** `ContactRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

### `PUT /api/{session}/contacts/{chatId}`

**Summary:** Create or update contact

**Operation ID:** `ContactsSessionController_put`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `chatId` | `path` | Yes | `string` | Chat ID |

**Request Body:** `ContactUpdateBody`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `Result` |

---

### `GET /api/{session}/contacts/{id}`

**Summary:** Get contact basic info

**Operation ID:** `ContactsSessionController_get`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `id` | `path` | Yes | `string` | Contact ID |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `GET /api/{session}/lids`

**Summary:** Get all known lids to phone number mapping

**Operation ID:** `LidsController_getAll`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `limit` | `query` | No | `number` |  |
| `offset` | `query` | No | `number` |  |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `Array[LidToPhoneNumber]` |

---

### `GET /api/{session}/lids/count`

**Summary:** Get the number of known lids

**Operation ID:** `LidsController_getLidsCount`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `CountResponse` |

---

### `GET /api/{session}/lids/pn/{phoneNumber}`

**Summary:** Get lid by phone number \(chat id\)

**Operation ID:** `LidsController_findLIDByPhoneNumber`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `phoneNumber` | `path` | Yes | `string` |  |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `LidToPhoneNumber` |

---

### `GET /api/{session}/lids/{lid}`

**Summary:** Get phone number by lid

**Operation ID:** `LidsController_findPNByLid`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `lid` | `path` | Yes | `string` |  |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `LidToPhoneNumber` |

---

## Groups

### `POST /api/{session}/groups`

**Summary:** Create a new group\.

**Operation ID:** `GroupsController_createGroup`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Request Body:** `CreateGroupRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

### `GET /api/{session}/groups`

**Summary:** Get all groups\.

**Operation ID:** `GroupsController_getGroups`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `sortBy` | `query` | No | `string` | Sort by field |
| `sortOrder` | `query` | No | `string` | Sort order \- <b>desc</b>ending \(Z => A, New first\) or <b>asc</b>ending \(A => Z, Old first\) |
| `limit` | `query` | No | `number` |  |
| `offset` | `query` | No | `number` |  |
| `exclude` | `query` | No | `array` | Exclude fields |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `object` |

---

### `GET /api/{session}/groups/count`

**Summary:** Get the number of groups\.

**Operation ID:** `GroupsController_getGroupsCount`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `CountResponse` |

---

### `POST /api/{session}/groups/join`

**Summary:** Join group via code

**Operation ID:** `GroupsController_joinGroup`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Request Body:** `JoinGroupRequest`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `JoinGroupResponse` |

---

### `GET /api/{session}/groups/join-info`

**Summary:** Get info about the group before joining\.

**Operation ID:** `GroupsController_joinInfoGroup`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `code` | `query` | Yes | `string` | Group code \(123\) or url \(https://chat\.whatsapp\.com/123\) |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `object` |

---

### `POST /api/{session}/groups/refresh`

**Summary:** Refresh groups from the server\.

**Operation ID:** `GroupsController_refreshGroups`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `GET /api/{session}/groups/{id}`

**Summary:** Get the group\.

**Operation ID:** `GroupsController_getGroup`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `id` | `path` | Yes | `string` | Group ID |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `DELETE /api/{session}/groups/{id}`

**Summary:** Delete the group\.

**Operation ID:** `GroupsController_deleteGroup`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `id` | `path` | Yes | `string` | Group ID |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `POST /api/{session}/groups/{id}/admin/demote`

**Summary:** Demotes participants to regular users\.

**Operation ID:** `GroupsController_demoteToAdmin`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `id` | `path` | Yes | `string` | Group ID |

**Request Body:** `ParticipantsRequest`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `POST /api/{session}/groups/{id}/admin/promote`

**Summary:** Promote participants to admin users\.

**Operation ID:** `GroupsController_promoteToAdmin`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `id` | `path` | Yes | `string` | Group ID |

**Request Body:** `ParticipantsRequest`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `PUT /api/{session}/groups/{id}/description`

**Summary:** Updates the group description\.

**Operation ID:** `GroupsController_setDescription`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `id` | `path` | Yes | `string` | Group ID |

**Request Body:** `DescriptionRequest`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `GET /api/{session}/groups/{id}/invite-code`

**Summary:** Gets the invite code for the group\.

**Operation ID:** `GroupsController_getInviteCode`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `id` | `path` | Yes | `string` | Group ID |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `string` |

---

### `POST /api/{session}/groups/{id}/invite-code/revoke`

**Summary:** Invalidates the current group invite code and generates a new one\.

**Operation ID:** `GroupsController_revokeInviteCode`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `id` | `path` | Yes | `string` | Group ID |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `string` |

---

### `POST /api/{session}/groups/{id}/leave`

**Summary:** Leave the group\.

**Operation ID:** `GroupsController_leaveGroup`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `id` | `path` | Yes | `string` | Group ID |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `GET /api/{session}/groups/{id}/participants`

**Summary:** Get participants

**Operation ID:** `GroupsController_getParticipants`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `id` | `path` | Yes | `string` | Group ID |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `POST /api/{session}/groups/{id}/participants/add`

**Summary:** Add participants

**Operation ID:** `GroupsController_addParticipants`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `id` | `path` | Yes | `string` | Group ID |

**Request Body:** `ParticipantsRequest`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `POST /api/{session}/groups/{id}/participants/remove`

**Summary:** Remove participants

**Operation ID:** `GroupsController_removeParticipants`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `id` | `path` | Yes | `string` | Group ID |

**Request Body:** `ParticipantsRequest`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `GET /api/{session}/groups/{id}/participants/v2`

**Summary:** Get group participants\.

**Operation ID:** `GroupsController_getGroupParticipants`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `id` | `path` | Yes | `string` | Group ID |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `Array[GroupParticipant]` |

---

### `GET /api/{session}/groups/{id}/picture`

**Summary:** Get group picture

**Operation ID:** `GroupsController_getChatPicture`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `id` | `path` | Yes | `string` | Group ID |
| `refresh` | `query` | No | `boolean` | Refresh the picture from the server \(24h cache by default\)\. Do not refresh if not needed, you can get rate limit error |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `ChatPictureResponse` |

---

### `PUT /api/{session}/groups/{id}/picture`

**Summary:** Set group picture

**Operation ID:** `GroupsController_setPicture`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `id` | `path` | Yes | `string` | Group ID |
| `session` | `path` | Yes | `any` | Session name |

**Request Body:** `ProfilePictureRequest`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `Result` |

---

### `DELETE /api/{session}/groups/{id}/picture`

**Summary:** Delete group picture

**Operation ID:** `GroupsController_deletePicture`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `id` | `path` | Yes | `string` | Group ID |
| `session` | `path` | Yes | `any` | Session name |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `Result` |

---

### `PUT /api/{session}/groups/{id}/settings/security/info-admin-only`

**Summary:** Updates the group "info admin only" settings\.

**Operation ID:** `GroupsController_setInfoAdminOnly`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `id` | `path` | Yes | `string` | Group ID |

**Request Body:** `SettingsSecurityChangeInfo`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `GET /api/{session}/groups/{id}/settings/security/info-admin-only`

**Summary:** Get the group's 'info admin only' settings\.

**Operation ID:** `GroupsController_getInfoAdminOnly`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `id` | `path` | Yes | `string` | Group ID |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `SettingsSecurityChangeInfo` |

---

### `PUT /api/{session}/groups/{id}/settings/security/messages-admin-only`

**Summary:** Update settings \- who can send messages

**Operation ID:** `GroupsController_setMessagesAdminOnly`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `id` | `path` | Yes | `string` | Group ID |

**Request Body:** `SettingsSecurityChangeInfo`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `GET /api/{session}/groups/{id}/settings/security/messages-admin-only`

**Summary:** Get settings \- who can send messages

**Operation ID:** `GroupsController_getMessagesAdminOnly`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `id` | `path` | Yes | `string` | Group ID |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `SettingsSecurityChangeInfo` |

---

### `PUT /api/{session}/groups/{id}/subject`

**Summary:** Updates the group subject

**Operation ID:** `GroupsController_setSubject`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `id` | `path` | Yes | `string` | Group ID |

**Request Body:** `SubjectRequest`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

## Chats

### `GET /api/{session}/chats`

**Summary:** Get chats

**Operation ID:** `ChatsController_getChats`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `sortBy` | `query` | No | `string` | Sort by field |
| `sortOrder` | `query` | No | `string` | Sort order \- <b>desc</b>ending \(Z => A, New first\) or <b>asc</b>ending \(A => Z, Old first\) |
| `merge` | `query` | No | `boolean` | Merge LID \(@lid\) and phone\-number \(@c\.us\) chats referencing the same contact |
| `limit` | `query` | No | `number` |  |
| `offset` | `query` | No | `number` |  |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `GET /api/{session}/chats/overview`

**Summary:** Get chats overview\. Includes all necessary things to build UI "your chats overview" page \- chat id, name, picture, last message\. Sorting by last message timestamp

**Operation ID:** `ChatsController_getChatsOverview`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `merge` | `query` | No | `boolean` | Merge LID \(@lid\) and phone\-number \(@c\.us\) chats referencing the same contact |
| `limit` | `query` | No | `number` |  |
| `offset` | `query` | No | `number` |  |
| `ids` | `query` | No | `array` | Filter by chat ids |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `Array[ChatSummary]` |

---

### `POST /api/{session}/chats/overview`

**Summary:** Get chats overview\. Use POST if you have too many "ids" params \- GET can limit it

**Operation ID:** `ChatsController_postChatsOverview`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Request Body:** `OverviewBodyRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `Array[ChatSummary]` |

---

### `DELETE /api/{session}/chats/{chatId}`

**Summary:** Deletes the chat

**Operation ID:** `ChatsController_deleteChat`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `chatId` | `path` | Yes | `string` | Chat ID |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `POST /api/{session}/chats/{chatId}/archive`

**Summary:** Archive the chat

**Operation ID:** `ChatsController_archiveChat`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `chatId` | `path` | Yes | `string` | Chat ID |

**Responses:**

| Status | Type |
|--------|------|
| `201` | `object` |

---

### `GET /api/{session}/chats/{chatId}/messages`

**Summary:** Gets messages in the chat

**Operation ID:** `ChatsController_getChatMessages`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `sortBy` | `query` | No | `string` | Sort by field |
| `sortOrder` | `query` | No | `string` | Sort order \- <b>desc</b>ending \(Z => A, New first\) or <b>asc</b>ending \(A => Z, Old first\) |
| `downloadMedia` | `query` | No | `boolean` | Download media for messages |
| `merge` | `query` | No | `boolean` | Merge LID \(@lid\) and phone\-number \(@c\.us\) chats referencing the same contact |
| `limit` | `query` | Yes | `number` |  |
| `offset` | `query` | No | `number` |  |
| `filter.timestamp.lte` | `query` | No | `number` | Filter messages before this timestamp \(inclusive\) |
| `filter.timestamp.gte` | `query` | No | `number` | Filter messages after this timestamp \(inclusive\) |
| `filter.fromMe` | `query` | No | `boolean` | From me filter \(by default shows all messages\) |
| `filter.ack` | `query` | No | `string` | Filter messages by acknowledgment status |
| `session` | `path` | Yes | `any` | Session name |
| `chatId` | `path` | Yes | `string` | Chat ID |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `Array[WAMessage]` |

---

### `DELETE /api/{session}/chats/{chatId}/messages`

**Summary:** Clears all messages from the chat

**Operation ID:** `ChatsController_clearMessages`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `chatId` | `path` | Yes | `string` | Chat ID |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `POST /api/{session}/chats/{chatId}/messages/read`

**Summary:** Read unread messages in the chat

**Operation ID:** `ChatsController_readChatMessages`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `messages` | `query` | No | `number` | How much messages to read \(latest first\) |
| `days` | `query` | No | `number` | How much days to read \(latest first\) |
| `session` | `path` | Yes | `any` | Session name |
| `chatId` | `path` | Yes | `string` | Chat ID |

**Responses:**

| Status | Type |
|--------|------|
| `201` | `ReadChatMessagesResponse` |

---

### `GET /api/{session}/chats/{chatId}/messages/{messageId}`

**Summary:** Gets message by id

**Operation ID:** `ChatsController_getChatMessage`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `downloadMedia` | `query` | No | `boolean` | Download media for messages |
| `merge` | `query` | No | `boolean` | Merge LID \(@lid\) and phone\-number \(@c\.us\) chats referencing the same contact |
| `session` | `path` | Yes | `any` | Session name |
| `chatId` | `path` | Yes | `string` | Chat ID |
| `messageId` | `path` | Yes | `string` |  |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `WAMessage` |

---

### `DELETE /api/{session}/chats/{chatId}/messages/{messageId}`

**Summary:** Deletes a message from the chat

**Operation ID:** `ChatsController_deleteMessage`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `chatId` | `path` | Yes | `string` | Chat ID |
| `messageId` | `path` | Yes | `string` | Message ID in format <code>{fromMe}\_{chat}\_{message\_id}\[\_{participant}\]</code> |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `PUT /api/{session}/chats/{chatId}/messages/{messageId}`

**Summary:** Edits a message in the chat

**Operation ID:** `ChatsController_editMessage`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `chatId` | `path` | Yes | `string` | Chat ID |
| `messageId` | `path` | Yes | `string` | Message ID in format <code>{fromMe}\_{chat}\_{message\_id}\[\_{participant}\]</code> |

**Request Body:** `EditMessageRequest`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `POST /api/{session}/chats/{chatId}/messages/{messageId}/pin`

**Summary:** Pins a message in the chat

**Operation ID:** `ChatsController_pinMessage`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `chatId` | `path` | Yes | `string` | Chat ID |
| `messageId` | `path` | Yes | `string` |  |

**Request Body:** `PinMessageRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

### `POST /api/{session}/chats/{chatId}/messages/{messageId}/unpin`

**Summary:** Unpins a message in the chat

**Operation ID:** `ChatsController_unpinMessage`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `chatId` | `path` | Yes | `string` | Chat ID |
| `messageId` | `path` | Yes | `string` |  |

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

### `GET /api/{session}/chats/{chatId}/picture`

**Summary:** Gets chat picture

**Operation ID:** `ChatsController_getChatPicture`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `chatId` | `path` | Yes | `string` |  |
| `refresh` | `query` | No | `boolean` | Refresh the picture from the server \(24h cache by default\)\. Do not refresh if not needed, you can get rate limit error |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `ChatPictureResponse` |

---

### `POST /api/{session}/chats/{chatId}/unarchive`

**Summary:** Unarchive the chat

**Operation ID:** `ChatsController_unarchiveChat`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `chatId` | `path` | Yes | `string` | Chat ID |

**Responses:**

| Status | Type |
|--------|------|
| `201` | `object` |

---

### `POST /api/{session}/chats/{chatId}/unread`

**Summary:** Unread the chat

**Operation ID:** `ChatsController_unreadChat`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `chatId` | `path` | Yes | `string` | Chat ID |

**Responses:**

| Status | Type |
|--------|------|
| `201` | `object` |

---

## Events

### `POST /api/{session}/events`

**Summary:** Send an event message

**Operation ID:** `EventsController_sendEvent`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Request Body:** `EventMessageRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `WAMessage` |

---

## Calls

### `POST /api/{session}/calls/reject`

**Summary:** Reject incoming call

**Operation ID:** `CallsController_rejectCall`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Request Body:** `RejectCallRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

## Channels

### `GET /api/{session}/channels`

**Summary:** Get list of know channels

**Operation ID:** `ChannelsController_list`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `role` | `query` | No | `string` |  |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `Array[Channel]` |

---

### `POST /api/{session}/channels`

**Summary:** Create a new channel\.

**Operation ID:** `ChannelsController_create`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Request Body:** `CreateChannelRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `Channel` |

---

### `POST /api/{session}/channels/search/by-text`

**Summary:** Search for channels \(by text\)

**Operation ID:** `ChannelsController_searchByText`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Request Body:** `ChannelSearchByText`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `ChannelListResult` |

---

### `POST /api/{session}/channels/search/by-view`

**Summary:** Search for channels \(by view\)

**Operation ID:** `ChannelsController_searchByView`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Request Body:** `ChannelSearchByView`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `ChannelListResult` |

---

### `GET /api/{session}/channels/search/categories`

**Summary:** Get list of categories for channel search

**Operation ID:** `ChannelsController_getSearchCategories`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `Array[ChannelCategory]` |

---

### `GET /api/{session}/channels/search/countries`

**Summary:** Get list of countries for channel search

**Operation ID:** `ChannelsController_getSearchCountries`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `Array[ChannelCountry]` |

---

### `GET /api/{session}/channels/search/views`

**Summary:** Get list of views for channel search

**Operation ID:** `ChannelsController_getSearchViews`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `Array[ChannelView]` |

---

### `DELETE /api/{session}/channels/{id}`

**Summary:** Delete the channel\.

**Operation ID:** `ChannelsController_delete`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `id` | `path` | Yes | `any` | WhatsApp Channel ID |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `GET /api/{session}/channels/{id}`

**Summary:** Get the channel info

**Operation ID:** `ChannelsController_get`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `id` | `path` | Yes | `any` | WhatsApp Channel ID or invite code from invite link https://www\.whatsapp\.com/channel/11111 |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `Channel` |

---

### `POST /api/{session}/channels/{id}/follow`

**Summary:** Follow the channel\.

**Operation ID:** `ChannelsController_follow`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `id` | `path` | Yes | `any` | WhatsApp Channel ID |

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

### `GET /api/{session}/channels/{id}/messages/preview`

**Summary:** Preview channel messages

**Operation ID:** `ChannelsController_previewChannelMessages`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `id` | `path` | Yes | `any` | Channel id or invite code |
| `downloadMedia` | `query` | Yes | `boolean` |  |
| `limit` | `query` | Yes | `number` |  |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `Array[ChannelMessage]` |

---

### `POST /api/{session}/channels/{id}/mute`

**Summary:** Mute the channel\.

**Operation ID:** `ChannelsController_mute`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `id` | `path` | Yes | `any` | WhatsApp Channel ID |

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

### `POST /api/{session}/channels/{id}/unfollow`

**Summary:** Unfollow the channel\.

**Operation ID:** `ChannelsController_unfollow`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `id` | `path` | Yes | `any` | WhatsApp Channel ID |

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

### `POST /api/{session}/channels/{id}/unmute`

**Summary:** Unmute the channel\.

**Operation ID:** `ChannelsController_unmute`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `id` | `path` | Yes | `any` | WhatsApp Channel ID |

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

## Chatting

### `GET /api/checkNumberStatus`

**Summary:** Check number status

**Operation ID:** `ChattingController_DEPRECATED_checkNumberStatus`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `phone` | `query` | Yes | `string` | The phone number to check |
| `session` | `query` | Yes | `string` |  |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `WANumberExistResult` |

---

### `POST /api/forwardMessage`

**Summary:** 

**Operation ID:** `ChattingController_forwardMessage`

**Request Body:** `MessageForwardRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `WAMessage` |

---

### `GET /api/messages`

**Summary:** Get messages in a chat

**Operation ID:** `ChattingController_getMessages`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `sortBy` | `query` | No | `string` | Sort by field |
| `sortOrder` | `query` | No | `string` | Sort order \- <b>desc</b>ending \(Z => A, New first\) or <b>asc</b>ending \(A => Z, Old first\) |
| `downloadMedia` | `query` | No | `boolean` | Download media for messages |
| `merge` | `query` | No | `boolean` | Merge LID \(@lid\) and phone\-number \(@c\.us\) chats referencing the same contact |
| `chatId` | `query` | Yes | `string` |  |
| `session` | `query` | Yes | `string` |  |
| `limit` | `query` | Yes | `number` |  |
| `offset` | `query` | No | `number` |  |
| `filter.timestamp.lte` | `query` | No | `number` | Filter messages before this timestamp \(inclusive\) |
| `filter.timestamp.gte` | `query` | No | `number` | Filter messages after this timestamp \(inclusive\) |
| `filter.fromMe` | `query` | No | `boolean` | From me filter \(by default shows all messages\) |
| `filter.ack` | `query` | No | `string` | Filter messages by acknowledgment status |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `Array[WAMessage]` |

---

### `PUT /api/reaction`

**Summary:** React to a message with an emoji

**Operation ID:** `ChattingController_setReaction`

**Request Body:** `MessageReactionRequest`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `object` |

---

### `POST /api/reply`

**Summary:** DEPRECATED \- you can set "reply\_to" field when sending text, image, etc

**Operation ID:** `ChattingController_reply`

**Request Body:** `MessageReplyRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `object` |

---

### `POST /api/send/buttons/reply`

**Summary:** Reply on a button message

**Operation ID:** `ChattingController_sendButtonsReply`

**Request Body:** `MessageButtonReply`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

### `POST /api/send/link-custom-preview`

**Summary:** Send a text message with a CUSTOM link preview\.

**Operation ID:** `ChattingController_sendLinkCustomPreview`

**Request Body:** `MessageLinkCustomPreviewRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `object` |

---

### `POST /api/sendButtons`

**Summary:** Send buttons message \(interactive\)

**Operation ID:** `ChattingController_sendButtons`

**Request Body:** `SendButtonsRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

### `POST /api/sendContactVcard`

**Summary:** 

**Operation ID:** `ChattingController_sendContactVcard`

**Request Body:** `MessageContactVcardRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

### `POST /api/sendFile`

**Summary:** Send a file

**Operation ID:** `ChattingController_sendFile`

**Request Body:** `MessageFileRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `object` |

---

### `POST /api/sendImage`

**Summary:** Send an image

**Operation ID:** `ChattingController_sendImage`

**Request Body:** `MessageImageRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `object` |

---

### `POST /api/sendLinkPreview`

**Summary:** 

**Operation ID:** `ChattingController_sendLinkPreview_DEPRECATED`

**Request Body:** `MessageLinkPreviewRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

### `POST /api/sendList`

**Summary:** Send a list message \(interactive\)

**Operation ID:** `ChattingController_sendList`

**Request Body:** `SendListRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `object` |

---

### `POST /api/sendLocation`

**Summary:** 

**Operation ID:** `ChattingController_sendLocation`

**Request Body:** `MessageLocationRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `object` |

---

### `POST /api/sendPoll`

**Summary:** Send a poll with options

**Operation ID:** `ChattingController_sendPoll`

**Request Body:** `MessagePollRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

### `POST /api/sendPollVote`

**Summary:** Vote on a poll

**Operation ID:** `ChattingController_sendPollVote`

**Request Body:** `MessagePollVoteRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

### `POST /api/sendSeen`

**Summary:** 

**Operation ID:** `ChattingController_sendSeen`

**Request Body:** `SendSeenRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `object` |

---

### `POST /api/sendText`

**Summary:** Send a text message

**Operation ID:** `ChattingController_sendText`

**Request Body:** `MessageTextRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `WAMessage` |

---

### `GET /api/sendText`

**Summary:** Send a text message

**Operation ID:** `ChattingController_sendTextGet`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `phone` | `query` | Yes | `string` |  |
| `text` | `query` | Yes | `string` |  |
| `session` | `query` | Yes | `string` |  |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `object` |

---

### `POST /api/sendVideo`

**Summary:** Send a video

**Operation ID:** `ChattingController_sendVideo`

**Request Body:** `MessageVideoRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

### `POST /api/sendVoice`

**Summary:** Send an voice message

**Operation ID:** `ChattingController_sendVoice`

**Request Body:** `MessageVoiceRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `object` |

---

### `PUT /api/star`

**Summary:** Star or unstar a message

**Operation ID:** `ChattingController_setStar`

**Request Body:** `MessageStarRequest`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `POST /api/startTyping`

**Summary:** 

**Operation ID:** `ChattingController_startTyping`

**Request Body:** `ChatRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

### `POST /api/stopTyping`

**Summary:** 

**Operation ID:** `ChattingController_stopTyping`

**Request Body:** `ChatRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

## Pairing

### `GET /api/screenshot`

**Summary:** Get a screenshot of the current WhatsApp session \(\*\*WEBJS/WPP\*\* only\)

**Operation ID:** `ScreenshotController_screenshot`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `query` | Yes | `string` |  |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `GET /api/{session}/auth/qr`

**Summary:** Get QR code for pairing WhatsApp API\.

**Operation ID:** `AuthController_getQR`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `format` | `query` | Yes | `string` |  |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `POST /api/{session}/auth/request-code`

**Summary:** Request authentication code\.

**Operation ID:** `AuthController_requestCode`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Request Body:** `RequestCodeRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

## Observability

### `GET /api/server/debug/browser/trace/{session}`

**Summary:** Collect and get a trace\.json for Chrome DevTools 

**Operation ID:** `ServerDebugController_browserTrace`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `seconds` | `query` | Yes | `number` | How many seconds to trace |
| `categories` | `query` | Yes | `array` | Categories to trace \(all by default\) |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `GET /api/server/debug/cpu`

**Summary:** Collect and return a CPU profile for the current nodejs process

**Operation ID:** `ServerDebugController_cpuProfile`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `seconds` | `query` | No | `number` | How many seconds to sample CPU |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `GET /api/server/debug/heapsnapshot`

**Summary:** Return a heapsnapshot for the current nodejs process

**Operation ID:** `ServerDebugController_heapsnapshot`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `GET /api/server/environment`

**Summary:** Get the server environment

**Operation ID:** `ServerController_environment`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `all` | `query` | No | `boolean` | Include all environment variables |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `object` |

---

### `GET /api/server/status`

**Summary:** Get the server status

**Operation ID:** `ServerController_status`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `ServerStatusResponse` |

---

### `POST /api/server/stop`

**Summary:** Stop \(and restart\) the server

**Operation ID:** `ServerController_stop`

**Request Body:** `StopRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `StopResponse` |

---

### `GET /api/server/version`

**Summary:** Get the version of the server

**Operation ID:** `ServerController_get`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `WAHAEnvironment` |

---

### `GET /api/version`

**Summary:** Get the server version 

**Operation ID:** `VersionController_get`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `WAHAEnvironment` |

---

### `GET /health`

**Summary:** Check the health of the server

**Operation ID:** `HealthController_check`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `object` |
| `503` | `object` |

---

### `GET /ping`

**Summary:** Ping the server

**Operation ID:** `PingController_ping`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `PingResponse` |

---

## Api Keys

### `POST /api/keys`

**Summary:** Create a new API key

**Operation ID:** `ApiKeysController_create`

**Request Body:** `ApiKeyRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `ApiKeyDTO` |

---

### `GET /api/keys`

**Summary:** Get all API keys

**Operation ID:** `ApiKeysController_list`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `Array[ApiKeyDTO]` |

---

### `PUT /api/keys/{id}`

**Summary:** Update an API key

**Operation ID:** `ApiKeysController_update`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `id` | `path` | Yes | `string` |  |

**Request Body:** `ApiKeyRequest`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `ApiKeyDTO` |

---

### `DELETE /api/keys/{id}`

**Summary:** Delete an API key

**Operation ID:** `ApiKeysController_delete`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `id` | `path` | Yes | `string` |  |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

## Sessions

### `GET /api/sessions`

**Summary:** List all sessions

**Operation ID:** `SessionsController_list`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `expand` | `query` | No | `array` | Expand additional session details\. |
| `all` | `query` | No | `boolean` | Return all sessions, including those that are in the STOPPED state\. |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `Array[SessionInfo]` |

---

### `POST /api/sessions`

**Summary:** Create a session

**Operation ID:** `SessionsController_create`

**Request Body:** `SessionCreateRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `SessionDTO` |

---

### `POST /api/sessions/logout`

**Summary:** Logout and Delete session\.

**Operation ID:** `SessionsController_DEPRECATED_logout`

**Request Body:** `SessionLogoutDeprecatedRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

### `POST /api/sessions/start`

**Summary:** Upsert and Start session

**Operation ID:** `SessionsController_DEPRACATED_start`

**Request Body:** `SessionStartDeprecatedRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `SessionDTO` |

---

### `POST /api/sessions/stop`

**Summary:** Stop \(and Logout if asked\) session

**Operation ID:** `SessionsController_DEPRECATED_stop`

**Request Body:** `SessionStopDeprecatedRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

### `GET /api/sessions/{session}`

**Summary:** Get session information

**Operation ID:** `SessionsController_get`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |
| `expand` | `query` | No | `array` | Expand additional session details\. |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `SessionInfo` |

---

### `PUT /api/sessions/{session}`

**Summary:** Update a session

**Operation ID:** `SessionsController_update`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Request Body:** `SessionUpdateRequest`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `SessionDTO` |

---

### `DELETE /api/sessions/{session}`

**Summary:** Delete the session

**Operation ID:** `SessionsController_delete`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `POST /api/sessions/{session}/logout`

**Summary:** Logout from the session

**Operation ID:** `SessionsController_logout`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Responses:**

| Status | Type |
|--------|------|
| `201` | `SessionDTO` |

---

### `GET /api/sessions/{session}/me`

**Summary:** Get information about the authenticated account

**Operation ID:** `SessionsController_getMe`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `MeInfo` |

---

### `POST /api/sessions/{session}/restart`

**Summary:** Restart the session

**Operation ID:** `SessionsController_restart`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Responses:**

| Status | Type |
|--------|------|
| `201` | `SessionDTO` |

---

### `POST /api/sessions/{session}/start`

**Summary:** Start the session

**Operation ID:** `SessionsController_start`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Responses:**

| Status | Type |
|--------|------|
| `201` | `SessionDTO` |

---

### `POST /api/sessions/{session}/stop`

**Summary:** Stop the session

**Operation ID:** `SessionsController_stop`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Responses:**

| Status | Type |
|--------|------|
| `201` | `SessionDTO` |

---

## Media

### `POST /api/{session}/media/convert/video`

**Summary:** Convert video to WhatsApp format \(mp4\)

**Operation ID:** `MediaController_convertVideo`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Request Body:** `VideoFileDTO`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |
| `201` | `No content` |

---

### `POST /api/{session}/media/convert/voice`

**Summary:** Convert voice to WhatsApp format \(opus\)

**Operation ID:** `MediaController_convertVoice`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Request Body:** `VoiceFileDTO`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |
| `201` | `No content` |

---

## Status

### `POST /api/{session}/status/delete`

**Summary:** DELETE sent status

**Operation ID:** `StatusController_deleteStatus`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Request Body:** `DeleteStatusRequest`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

### `POST /api/{session}/status/image`

**Summary:** Send image status

**Operation ID:** `StatusController_sendImageStatus`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Request Body:** `ImageStatus`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

### `GET /api/{session}/status/new-message-id`

**Summary:** Generate message ID you can use to batch contacts

**Operation ID:** `StatusController_getNewMessageId`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `NewMessageIDResponse` |

---

### `POST /api/{session}/status/text`

**Summary:** Send text status

**Operation ID:** `StatusController_sendTextStatus`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Request Body:** `TextStatus`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

### `POST /api/{session}/status/video`

**Summary:** Send video status

**Operation ID:** `StatusController_sendVideoStatus`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Request Body:** `VideoStatus`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

### `POST /api/{session}/status/voice`

**Summary:** Send voice status

**Operation ID:** `StatusController_sendVoiceStatus`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `path` | Yes | `any` | Session name |

**Request Body:** `VoiceStatus`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

## Apps

### `GET /api/apps`

**Summary:** List all apps for a session

**Operation ID:** `AppsController_list`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `session` | `query` | Yes | `string` | Session name to list apps for |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `POST /api/apps`

**Summary:** Create a new app

**Operation ID:** `AppsController_create`

**Request Body:** `App`

**Responses:**

| Status | Type |
|--------|------|
| `201` | `No content` |

---

### `GET /api/apps/{id}`

**Summary:** Get app by ID

**Operation ID:** `AppsController_get`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `id` | `path` | Yes | `string` |  |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `PUT /api/apps/{id}`

**Summary:** Update an existing app

**Operation ID:** `AppsController_update`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `id` | `path` | Yes | `string` |  |

**Request Body:** `App`

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

### `DELETE /api/apps/{id}`

**Summary:** Delete an app

**Operation ID:** `AppsController_delete`

**Parameters:**

| Name | In | Required | Type | Description |
|------|----|----------|------|-------------|
| `id` | `path` | Yes | `string` |  |

**Responses:**

| Status | Type |
|--------|------|
| `200` | `No content` |

---

