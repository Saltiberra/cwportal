# Restore pv_module_brands / pv_module_models

This folder contains a safe idempotent script and a helper PowerShell script to restore `pv_module_brands` and `pv_module_models` in the DB.

Files:

- `restore_pv_modules.sql` - SQL script that does:
  1. Backup current tables
  2. Archive any `id = 0` rows and delete them from main tables
  3. Reset AUTO_INCREMENT values
  4. Insert default brands and example models if missing
  5. Finaly re-check counts and reset AUTO_INCREMENT

- `apply_restore.ps1` - PowerShell script to execute the SQL file (interactive). Adjust `MySqlClientPath` if your MySQL executable is in another location.

Checklist & instructions

1) Backup current data

- Open phpMyAdmin -> Export and export the two tables `pv_module_brands` and `pv_module_models` or export the entire DB for safety.

2) Test the SQL script in a staging environment (recommended)

- If you have a dev/staging DB, run `restore_pv_modules.sql` there and ensure everything is OK.

3) Run the script (phpMyAdmin)

- Open phpMyAdmin -> your DB -> SQL tab -> paste the contents of `restore_pv_modules.sql` and execute.

OR via CLI (local XAMPP sample)

```powershell
# Save script in tools\restore_pv_modules.sql then
cd C:\xampp\htdocs\cleanwattsportal
# Run the PowerShell helper; follow interactive prompts
.\tools\apply_restore.ps1 -Host localhost -Port 3306 -User root -Database cleanwattsportal
```

4) Manual checks after running

- Verify brand insertion

```sql
SELECT id, brand_name FROM pv_module_brands ORDER BY id LIMIT 100;
```

- Verify models

```sql
SELECT id, brand_id, model_name, power_options FROM pv_module_models ORDER BY id LIMIT 100;
```

5) Reload app pages and confirm:

- Admin -> PV Modules (brands and models should show)
- Comissionamento page -> the dropdown will be reloaded (or refresh the page with Ctrl+F5)

6) Troubleshooting

- If inserts fail with `Duplicate entry '0'`:
  - Execute the SQL in the file to delete id=0 rows and reset AUTO_INCREMENT (script included);
- If pages still not updated in other tabs:
  - Reload `comissionamento.php`, or make sure the BroadcastChannel/localStorage update code is present and loaded (we added it earlier).

If you'd like, I can:

- Customize the SQL to add your exact production brand/model list (paste it here), or
- Provide a `restore_pv_modules.sql` version that uses fixed `id` values (for compatibility if other tables reference brand ids), or
- Walk you step-by-step via terminal or remote session to run the script.

---

If you want me to proceed to the next step, say which option you prefer:

- Let me generate a version with specific brands/models that you prefer to restore
- Or run the above on your local XAMPP (I can't run it without your system access; I can provide a command and script)
