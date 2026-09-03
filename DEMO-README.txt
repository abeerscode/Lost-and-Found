LOST & FOUND — CLEAN DEMO BUILD

Database: lost_and_found
Import: database/lost_and_found.sql

Demo users
1) john.doe@uni.edu / demo123
2) sara.khan@uni.edu / demo123

Admin
admin@uni.edu / admin123

Important
- Keep the project folder name consistent with BASE_URL in config/config.php.
- Current BASE_URL is /lost-and-found.
- Seed images are stored in uploads/demo/.
- The app normalizes both demo/... and uploads/demo/... seed paths for compatibility.

PROFILE UPGRADE
---------------
If you created the database before the profile redesign, run:
database/migrations/2026_09_profile_upgrade.sql

This adds the `batch` and `profile_photo` fields used by the new profile UI.
Public profiles intentionally hide email, phone and the full university ID.
