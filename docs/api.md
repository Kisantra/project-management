# Coal Management API Documentation

## Base URL
```
http://localhost:8000/api
```

## Endpoints

### Users

#### Get All Users
```http
GET /user
```

#### Get Specific User
```http
GET /users/{user_id}
```

### Projects

#### Get All Projects
```http
GET /projects
```

#### Get Specific Project
```http
GET /projects/{project_id}
```

### Clients

#### Get All Clients
```http
GET /clients
```

#### Get Specific Client
```http
GET /clients/{client_id}
```

### Activities

#### Get All Activities
```http
GET /activities
```

#### Get Specific Activity
```http
GET /activities/{activity_id}
```

### Klien masuk dari Client Management

Dipanggil oleh aplikasi Client Management (CM), bukan oleh pengguna. Wajib header
`Authorization: Bearer <CLIENT_MANAGEMENT_TOKEN>`; token salah → 401, token belum
diatur di PM → 503. Respons selalu JSON (validasi → 422).

#### Lead mencapai Deal
```http
POST /v1/intake/clients
```
Body: `cm_lead_id`, `name`, `entity`, `client_type` (`Badan`|`Pribadi`), `npwp`, `email`,
`address`, `contact{name,position,phone,email}`, `service`, `value` (rupiah, boleh 0),
`marketing_owner`, `deal_at`, `lead_url`.

Hanya menyimpan data ke daftar **Klien Masuk**; klien dan proyek dibuat saat staf memproses.
Idempoten per `cm_lead_id`. NPWP (angka saja; 15 digit disetarakan dengan 16 digit berawalan 0)
atau email dipakai untuk menyarankan klien lama.

```json
{ "intake_id": 86, "status": "received", "completed": false, "pm_client_id": null, "client_url": "https://pm.../klien-masuk/86/proses" }
```
Bila sudah diproses: `completed: true`, `pm_client_id` terisi, `client_url` = halaman klien.

#### Deal dibatalkan
```http
POST /v1/intake/clients/{cm_lead_id}/withdraw
```
Body: `reason` (`mundur`|`ditutup`), `detail`, `note`. Respons `{"ok": true}`; 404 bila lead belum pernah dikirim.

PM memanggil balik CM (`CLIENT_MANAGEMENT_URL`) di `GET /api/pm/leads/{id}/documents`
(pratinjau dan penyalinan lampiran) dan `POST /api/pm/leads/{id}/handoff` (lewat antrean,
job `SendHandoffToClientManagement`) setelah klien dan proyek dibuat. Body handoff:
`status`, `pm_client_id`, `completed_by`, `completed_at`, `client_url`, `matched`,
`project_name`, `project_url`.

## Response Format

### Success Response
```json
{
    "success": true,
    "message": "Success message here",
    "data": {
        // Response data
    }
}
```

### Error Response
```json
{
    "success": false,
    "message": "Error message here",
    "error": "Detailed error message",
    "trace": "Stack trace (in development)"
}
```

## Models

### User
- id
- name
- email
- roles
- projects
- recent_activities
- statistics
- created_at
- updated_at

### Project
- id
- name
- type
- priority
- status
- client
- created_at

### Client
- id
- name
- email
- phone
- address
- projects
- users
- statistics
- created_at
- updated_at

### Activity
- id
- description
- properties
- created_at
