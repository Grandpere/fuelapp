# TODO - User UI import finalize

## Plan
- [completed] Add user-facing finalize action for `needs_review` import jobs in `/ui/imports`.
- [completed] Wire secure POST controller (owner-scoped + CSRF) to finalize imports via existing application handler.
- [completed] Add functional UI test covering user finalize flow.
- [completed] Run quality gates and prepare commit.
