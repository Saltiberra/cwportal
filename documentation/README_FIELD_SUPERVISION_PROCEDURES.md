# Field Supervision & Procedures/Credentials Modules

Status: Initial scaffolding (2025-11-09)

## Overview

Two new modules extend the commissioning platform:

1. **Field Supervision**: Register site visits, inspections, technical and safety audits, with notes, attachments and action items.
2. **Procedures & Credentials**: Central library to store procedural documents and encrypted credentials.

## Field Supervision

### Entities

- `field_visit` (core record)
- `field_visit_note` (quick textual notes / timeline)
- `field_visit_action_item` (tasks derived from findings)
- `field_visit_attachment` (photos, PDFs, etc.)

### Visit Types

`visit | inspection | technical_audit | safety_audit`

### Status Flow

`open -> in_progress -> closed` (or `cancelled`)

### Severity (optional)

`info | minor | major | critical`

### Endpoints (manage_field_supervision.php)

- `list_visits` (filters: type, status, q)
- `get_visit`
- `create_visit`
- `update_visit`
- `add_note`, `list_notes`
- `add_action_item`, `list_action_items`, `update_action_item_status`, `delete_action_item`
New UI features: Projects can be deleted from the UI (only for admin/gestor roles). Deleting a project cascades to timeline entries and problems as defined by the DB migration.
Edit projects: Projects' title and description (and dates/phase) can be edited via the UI Edit button available to admins, gestores, and the project's supervisor.

Available attachments endpoints (added): `upload_visit_attachment`, `list_visit_attachments`, `delete_visit_attachment`, `download_visit_attachment`.

## Procedures & Credentials

### Entities

- `proc_category`
- `procedure_doc` (document metadata, file path)
- `credential_store` (encrypted secret)
- `credential_access_log` (audit trail of views and actions)

### Credential Roles

`admin | manager | tech | restricted` — controls visibility/decrypt permission.

### Endpoints (manage_procedures_credentials.php)

Categories: `list_categories`, `create_category`, `update_category`, `delete_category`
Procedures: `list_procedures`, `upload_procedure (TODO)`, `update_procedure`, `toggle_procedure_active`, `delete_procedure`
Credentials: `list_credentials`, `get_credential`, `create_credential`, `update_credential`, `delete_credential`, `access_log`

## Encryption

Secrets encrypted using AES-256-CBC. Key defined in `config/secure_key.php` (placeholder). Replace with secure random key (32 bytes) and restrict access.

Example generation:

```
php -r "echo 'base64:'.base64_encode(random_bytes(32));"
```

## Migration

SQL file: `db_migrate_field_supervision_procedures.sql` — create tables + a view.
Run manually in MySQL before using endpoints.

## Next Development Steps

1. Integrate file upload handling for `procedure_doc` (validate MIME, store outside web root optionally).
2. Add endpoints for attachment upload in Field Supervision.
3. UI pages: build dashboard listing visits and procedures.
4. Add Access Control UI (reveal credential with timed mask + logging).
5. Credential lifecycle and audit considerations (rotation workflows removed in this version).
6. Add indexing / search improvements (fulltext on descriptions if needed).
7. Rate-limit credential decryption attempts.

## Security Considerations

- Log every credential decryption attempt (even failed). (TODO)
- Enforce role checks server-side; never trust client.
- Consider second-factor or re-auth for viewing critical credentials.
- Do not commit real production keys/secrets.

## Testing Ideas

- Unit test encryption/decryption roundtrip.
- CRUD flows for visits and credentials.
- Permission matrix: user with role `tech` cannot decrypt restricted or admin-only credential.

## Changelog

- 2025-11-09: Initial scaffolding (migration + manage_field_supervision.php + manage_procedures_credentials.php + README)
