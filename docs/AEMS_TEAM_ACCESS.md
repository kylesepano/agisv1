# Engagement team access

The team selected when creating an unplanned engagement or importing an approved
IAP item is now saved in `engagement_teams`, with assignment, access, and audit
history. The creation form's `initial_team_plan` also remains available for its
AEO metadata and form values.

Department head maps to `SUPERVISOR`, team leader to `TEAM_LEADER`, team members
to `AUDITOR`, and support staff to `AUTHORIZED_PARTICIPANT`. Existing application
permissions still apply. Assigned leaders and auditors can enter planning data
while the engagement and planning package are editable. Support staff can view
the engagement; assignment does not grant review, approval, or global access.

Authorized team changes from the engagement edit form synchronize assignments
and revoke removed members' access. Metadata-only saves preserve assignments.

## Existing databases

After deploying the backend, run from `backend` against the intended database:

```sh
php artisan aems:sync-initial-teams --dry-run
php artisan aems:sync-initial-teams
```

This repair creates assignments only for active, non-draft, non-closed engagements
with a saved initial team and **no assignment history**, including soft-deleted
assignments. It is safe to repeat and never restores previously revoked members.
Invalid selections are reported per engagement without partially saving its team.
No schema migration or permission reseeding is required for this fix.
