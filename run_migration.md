# Database Migration Instructions

To add the `reflection_text` field to the `post_evaluations` table, run the following command in your backend directory:

```bash
cd CommEase-backend
php artisan migrate
```

This will run the migration file:
`2025_01_15_000000_add_reflection_text_to_post_evaluations_table.php`

## What the migration does:
- Adds a `reflection_text` TEXT field to the `post_evaluations` table
- The field is nullable and positioned after `recommendation_rating`
- This allows storing text-based reflections instead of file uploads

## After running the migration:
The `post_evaluations` table will have this structure:
- id
- event_id
- volunteer_id
- quality_rating
- responsiveness_rating
- effectiveness_rating
- organization_rating
- recommendation_rating
- **reflection_text** (NEW - TEXT field)
- reflection_paper_url (existing, for backward compatibility)
- reflection_paper_public_id (existing)
- reflection_paper_filename (existing)
- created_at
- updated_at

## Verification:
You can verify the migration worked by checking the database:
```sql
DESCRIBE post_evaluations;
```

The `reflection_text` field should appear in the table structure.
