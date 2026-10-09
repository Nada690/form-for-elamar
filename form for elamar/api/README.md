# REST API Documentation

## Overview
This is a RESTful API for the Lead/Client Submission Management System. Each Sales employee has a unique form URL, and client submissions are automatically associated with the correct Sales employee.

**Base URL:** `http://yourdomain.com/api`

---

## Authentication
Currently, the API does not require authentication. For production, implement API keys or JWT tokens.

---

## Endpoints

### 1. Sales Employees API

#### GET /api/sales
List all sales employees with their client counts.

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Ahmed Mohamed",
      "email": "ahmed@example.com",
      "slug": "ahmed-a7x92k",
      "active": 1,
      "created_at": "2026-10-07 12:00:00",
      "client_count": 5,
      "form_url": "http://yourdomain.com/form.php?slug=ahmed-a7x92k"
    }
  ],
  "message": "Sales employees retrieved successfully"
}
```

---

#### POST /api/sales
Create a new sales employee. System auto-generates a unique slug.

**Request Body:**
```json
{
  "name": "Mostafa Ali",
  "email": "mostafa@example.com"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 2,
    "name": "Mostafa Ali",
    "email": "mostafa@example.com",
    "slug": "mostafa-k9x2",
    "active": 1,
    "created_at": "2026-10-07 12:30:00",
    "client_count": 0,
    "form_url": "http://yourdomain.com/form.php?slug=mostafa-k9x2"
  },
  "message": "Sales employee created successfully"
}
```

**Error Responses:**
- `400` - Name or email missing
- `409` - Email already exists

---

#### GET /api/sales/{id}
Get a specific sales employee by ID.

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Ahmed Mohamed",
    "email": "ahmed@example.com",
    "slug": "ahmed-a7x92k",
    "active": 1,
    "created_at": "2026-10-07 12:00:00",
    "client_count": 5,
    "form_url": "http://yourdomain.com/form.php?slug=ahmed-a7x92k"
  },
  "message": "Sales employee retrieved successfully"
}
```

**Error Responses:**
- `404` - Sales employee not found

---

#### PUT /api/sales/{id}
Update a sales employee. All fields are optional.

**Request Body:**
```json
{
  "name": "Ahmed Updated",
  "email": "ahmed.new@example.com",
  "active": true
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Ahmed Updated",
    "email": "ahmed.new@example.com",
    "slug": "ahmed-a7x92k",
    "active": 1,
    "created_at": "2026-10-07 12:00:00",
    "client_count": 5,
    "form_url": "http://yourdomain.com/form.php?slug=ahmed-a7x92k"
  },
  "message": "Sales employee updated successfully"
}
```

**Error Responses:**
- `404` - Sales employee not found
- `409` - Email already exists

---

#### DELETE /api/sales/{id}
Delete a sales employee. Client submissions will have `sales_id` set to NULL.

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1
  },
  "message": "Sales employee deleted successfully"
}
```

**Error Responses:**
- `404` - Sales employee not found

---

### 2. Client Submissions API

#### GET /api/submissions
List all client submissions with optional filters.

**Query Parameters:**
- `sales_id` (optional) - Filter by sales employee ID
- `date_from` (optional) - Filter by date from (YYYY-MM-DD)
- `date_to` (optional) - Filter by date to (YYYY-MM-DD)
- `limit` (optional, default: 100) - Number of results
- `offset` (optional, default: 0) - Pagination offset

**Example:**
```
GET /api/submissions?sales_id=1&limit=10&offset=0
```

**Response:**
```json
{
  "success": true,
  "data": {
    "submissions": [
      {
        "id": 1,
        "sales_id": 1,
        "name": "Client Name",
        "phone": "+20 1234567890",
        "email": "client@gmail.com",
        "id_type": "national_id",
        "id_number": "12345678901234",
        "governorate": "القاهرة",
        "source": "مباشر",
        "project": "شورز — Shores (الساحل الشمالي)",
        "contract_location": "القاهرة",
        "unit_type": "",
        "eoi_number": "nn-13",
        "payment_period": "5 سنوات",
        "paid_5_percent": "نعم",
        "addresses": "Full address here",
        "attachments": ["uploads/doc_123_file.pdf"],
        "lang": "ar",
        "created_at": "2026-10-07 13:00:00",
        "sales_name": "Ahmed Mohamed",
        "sales_email": "ahmed@example.com"
      }
    ],
    "total": 15,
    "limit": 10,
    "offset": 0
  },
  "message": "Submissions retrieved successfully"
}
```

---

#### GET /api/submissions/{id}
Get a specific client submission by ID.

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "sales_id": 1,
    "name": "Client Name",
    "phone": "+20 1234567890",
    "email": "client@gmail.com",
    "id_type": "national_id",
    "id_number": "12345678901234",
    "governorate": "القاهرة",
    "source": "مباشر",
    "project": "شورز — Shores (الساحل الشمالي)",
    "contract_location": "القاهرة",
    "unit_type": "",
    "eoi_number": "nn-13",
    "payment_period": "5 سنوات",
    "paid_5_percent": "نعم",
    "addresses": "Full address here",
    "attachments": ["uploads/doc_123_file.pdf"],
    "lang": "ar",
    "created_at": "2026-10-07 13:00:00",
    "sales_name": "Ahmed Mohamed",
    "sales_email": "ahmed@example.com"
  },
  "message": "Submission retrieved successfully"
}
```

**Error Responses:**
- `404` - Submission not found

---

#### PUT /api/submissions/{id}
Update a client submission. All fields are optional.

**Request Body:**
```json
{
  "name": "Updated Client Name",
  "phone": "+20 9876543210",
  "email": "newclient@gmail.com",
  "payment_period": "7 سنوات",
  "paid_5_percent": "لا"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Updated Client Name",
    "phone": "+20 9876543210",
    "email": "newclient@gmail.com",
    "payment_period": "7 سنوات",
    "paid_5_percent": "لا",
    "sales_name": "Ahmed Mohamed",
    "sales_email": "ahmed@example.com"
  },
  "message": "Submission updated successfully"
}
```

**Error Responses:**
- `404` - Submission not found

---

#### DELETE /api/submissions/{id}
Delete a client submission and associated attachment files.

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 1
  },
  "message": "Submission deleted successfully"
}
```

**Error Responses:**
- `404` - Submission not found

---

### 3. Public Form API

#### GET /api/public/forms/{slug}
Validate a form slug and return form metadata.

**Example:**
```
GET /api/public/forms/ahmed-a7x92k
```

**Response:**
```json
{
  "success": true,
  "data": {
    "sales_id": 1,
    "sales_name": "Ahmed Mohamed",
    "slug": "ahmed-a7x92k",
    "form_valid": true
  },
  "message": "Form link is valid"
}
```

**Error Responses:**
- `404` - Invalid or inactive form link

---

#### POST /api/public/forms/{slug}/submissions
Submit client data for a specific sales employee via their unique slug.

**Example:**
```
POST /api/public/forms/ahmed-a7x92k/submissions
```

**Request Body (JSON):**
```json
{
  "name": "Client Name",
  "phone": "+20 1234567890",
  "email": "client@gmail.com",
  "id_type": "national_id",
  "id_number": "12345678901234",
  "governorate": "القاهرة",
  "source": "مباشر",
  "project": "شورز — Shores (الساحل الشمالي)",
  "contract_location": "القاهرة",
  "unit_type": "",
  "eoi_number": "nn-13",
  "payment_period": "5 سنوات",
  "paid_5_percent": "نعم",
  "addresses": "Full address here",
  "lang": "ar"
}
```

**Request Body (multipart/form-data for file uploads):**
```
name: Client Name
phone: +20 1234567890
email: client@gmail.com
id_type: national_id
id_number: 12345678901234
contract_location: القاهرة
eoi_number: nn-13
payment_period: 5 سنوات
paid_5_percent: نعم
addresses: Full address here
attachments: [file1.pdf, file2.jpg]
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 10,
    "sales_id": 1,
    "name": "Client Name",
    "phone": "+20 1234567890",
    "email": "client@gmail.com",
    "eoi_number": "nn-13",
    "created_at": "2026-10-07 14:00:00",
    "sales_name": "Ahmed Mohamed",
    "sales_email": "ahmed@example.com",
    "attachments": ["uploads/doc_123_file.pdf"]
  },
  "message": "Client submission created successfully"
}
```

**Error Responses:**
- `404` - Invalid or inactive form link
- `422` - Validation failed (missing required fields)
- `409` - Unit code (EOI number) already exists

---

## Error Response Format

All error responses follow this format:

```json
{
  "success": false,
  "error": "Error message",
  "code": "ERROR_CODE"
}
```

**Common HTTP Status Codes:**
- `200` - Success
- `400` - Bad Request (validation error)
- `404` - Not Found
- `405` - Method Not Allowed
- `409` - Conflict (duplicate data)
- `422` - Unprocessable Entity (validation error)
- `500` - Internal Server Error

---

## Unique Slug Generation Strategy

The system generates unique slugs using the following pattern:
```
{sanitized_name}-{random_6_char_hash}
```

Example:
- Name: "Ahmed Mohamed" → Slug: `ahmed-mohamed-a7x92k`
- Name: "Mostafa Ali" → Slug: `mostafa-ali-k9x2`

The system ensures uniqueness by:
1. Sanitizing the name (removing special characters, converting to lowercase)
2. Appending a random 6-character hash
3. Checking the database for collisions
4. Regenerating if collision exists

---

## Security Considerations

1. **CORS:** Currently enabled for all origins. Restrict in production.
2. **Authentication:** Not implemented. Add API keys or JWT for production.
3. **Input Validation:** All inputs are validated and sanitized.
4. **SQL Injection:** Using PDO prepared statements.
5. **File Uploads:** Limited to specific file types and size (25MB max).
6. **Slug Security:** Random suffix prevents guessing.

---

## Example Usage (cURL)

### Create Sales Employee
```bash
curl -X POST http://yourdomain.com/api/sales \
  -H "Content-Type: application/json" \
  -d '{"name":"Ahmed Mohamed","email":"ahmed@example.com"}'
```

### Get All Sales
```bash
curl http://yourdomain.com/api/sales
```

### Submit Client Data via Slug
```bash
curl -X POST http://yourdomain.com/api/public/forms/ahmed-a7x92k/submissions \
  -H "Content-Type: application/json" \
  -d '{
    "name":"Client Name",
    "phone":"+20 1234567890",
    "email":"client@gmail.com",
    "id_number":"12345678901234",
    "contract_location":"القاهرة",
    "eoi_number":"nn-13",
    "payment_period":"5 سنوات",
    "paid_5_percent":"نعم",
    "addresses":"Full address"
  }'
```

### Get Submissions Filtered by Sales
```bash
curl "http://yourdomain.com/api/submissions?sales_id=1&limit=10"
```

---

## Integration with Frontend

The existing PHP pages (`form.php`, `view_clients.php`, `manage_sales.php`) can be updated to use these REST API endpoints instead of direct database queries. This would provide better separation of concerns and make the system more maintainable.
