# ERP Ezy Chat API Documentation

## Base URL
```
http://your-domain.com/api/
```
For development:
```
http://127.0.0.1:8000/api/
```

## Authentication
All API endpoints (except login and register) require authentication via session cookies.

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

### Authentication Endpoints

#### 1. Login
**POST** `/api/auth/login`

Authenticate a user and create a session.

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "name": "User Name",
      "email": "user@example.com",
      "role": "users",
      "created_at": "2026-04-07 02:07:39"
    },
    "session_id": "session_id_here"
  },
  "timestamp": 1775529514
}
```

**Error Responses:**
- `400` - Missing required fields
- `401` - Invalid credentials
- `500` - Server error

---

#### 2. Register
**POST** `/api/auth/register`

Create a new user account and automatically log in.

**Request Body:**
```json
{
  "name": "User Name",
  "email": "user@example.com",
  "password": "password123",
  "confirm_password": "password123"
}
```

**Validation Rules:**
- Name: Required
- Email: Required, valid email format
- Password: Required, minimum 8 characters
- Confirm Password: Must match password

**Success Response (201):**
```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "user": {
      "id": 2,
      "name": "User Name",
      "email": "user@example.com",
      "role": "users",
      "created_at": "2026-04-07 02:38:12"
    },
    "session_id": "session_id_here"
  },
  "timestamp": 1775529514
}
```

**Error Responses:**
- `400` - Missing required fields
- `422` - Validation errors
- `500` - Server error

---

#### 3. Logout
**POST** `/api/auth/logout`

Destroy the current user session.

**Headers:** Requires session cookie

**Success Response (200):**
```json
{
  "success": true,
  "message": "Logout successful",
  "data": {
    "email": "user@example.com"
  },
  "timestamp": 1775529514
}
```

---

#### 4. Get User Profile
**GET** `/api/auth/profile`

Get the currently authenticated user's profile.

**Headers:** Requires session cookie

**Success Response (200):**
```json
{
  "success": true,
  "message": "Profile retrieved",
  "data": {
    "user": {
      "id": 1,
      "name": "User Name",
      "email": "user@example.com",
      "role": "users",
      "created_at": "2026-04-07 02:07:39"
    }
  },
  "timestamp": 1775529514
}
```

**Error Responses:**
- `401` - Not authenticated

---

#### 5. Check Authentication Status
**GET** `/api/auth/check`

Check if the current session is authenticated.

**Headers:** Optional session cookie

**Success Response (200):**
```json
{
  "success": true,
  "message": "Authenticated",
  "data": {
    "authenticated": true,
    "user": { ... }
  },
  "timestamp": 1775529514
}
```

**Not Authenticated Response (200):**
```json
{
  "success": true,
  "message": "Not authenticated",
  "data": {
    "authenticated": false
  },
  "timestamp": 1775529514
}
```

## User Roles
The system supports three roles:
1. `superadmin` - Full system access
2. `admin` - Administrative access
3. `users` - Regular user access (default)

## Testing the API

### Using cURL Examples

#### Login:
```bash
curl -X POST http://127.0.0.1:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"brandon@kkbuddy.com","password":"#Quidents64#"}'
```

#### Register:
```bash
curl -X POST http://127.0.0.1:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -d '{"name":"Test User","email":"test@example.com","password":"TestPass123","confirm_password":"TestPass123"}'
```

#### Get Profile (with session):
```bash
curl -X GET http://127.0.0.1:8000/api/auth/profile \
  -H "Content-Type: application/json" \
  -b cookies.txt
```

### Test Scripts
The repository includes test scripts:
- `test_api.php` - CLI-based functional tests
- `test_api_server.sh` - HTTP API tests
- `test_api_with_session.sh` - Session-based API tests

## Error Codes
- `200` - Success
- `201` - Created (registration)
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `405` - Method Not Allowed
- `422` - Validation Error
- `500` - Internal Server Error

## Session Management
- Sessions are managed via PHP native sessions
- Session cookies are automatically handled by the browser/HTTP client
- Sessions expire when browser closes (default PHP session behavior)
- Manual logout destroys the session server-side

## Database Schema
The API uses the following database table:

```sql
CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('superadmin', 'admin', 'users') NOT NULL DEFAULT 'users',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## Default Superadmin Account
- Email: `brandon@kkbuddy.com`
- Password: `#Quidents64#`

To recreate the superadmin account:
```bash
php scripts/create_superadmin.php
```