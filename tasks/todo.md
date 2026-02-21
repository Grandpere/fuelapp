# TODO - Vehicle Ownership + Receipt Vehicle Reference

## Plan
- [completed] Link vehicles to users in domain, persistence, and admin API (`ownerId` required).
- [completed] Scope vehicle uniqueness by owner (`owner_id + plate_number`).
- [completed] Add optional receipt-to-vehicle reference in domain/application/API/persistence.
- [completed] Enforce ownership boundary: a receipt can only reference a vehicle owned by current user.
- [completed] Run quality checks and validate migration/test suite.
