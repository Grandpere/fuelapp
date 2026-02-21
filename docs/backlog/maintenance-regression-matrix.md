# Maintenance Regression Matrix

This matrix lists critical maintenance scenarios that must remain green in CI.

## Reminder rules and due-state

| Scenario | Expected behavior | Coverage |
|---|---|---|
| DATE rule with no history | Due immediately (`dueAtDate = now`) | `tests/Unit/Maintenance/Application/Reminder/ReminderDueCalculatorTest.php` (`testDateRuleWithoutHistoryIsImmediatelyDue`) |
| WHICHEVER_FIRST - odometer reached first | Due by odometer only | `tests/Unit/Maintenance/Application/Reminder/ReminderDueCalculatorTest.php` (`testWhicheverFirstIsDueWhenOdometerThresholdReached`) |
| WHICHEVER_FIRST - date reached first | Due by date only | `tests/Unit/Maintenance/Application/Reminder/ReminderDueCalculatorTest.php` (`testWhicheverFirstIsDueWhenDateThresholdReachedBeforeOdometer`) |
| WHICHEVER_FIRST - none reached | Not due | `tests/Unit/Maintenance/Application/Reminder/ReminderDueCalculatorTest.php` (`testWhicheverFirstIsNotDueWhenNoThresholdReached`) |
| Event type filter baseline | Ignore non-matching event types | `tests/Unit/Maintenance/Application/Reminder/ReminderDueCalculatorTest.php` (`testDateRuleIgnoresEventsOfDifferentTypeWhenSelectingBaseline`) |

## Scheduler and idempotency

| Scenario | Expected behavior | Coverage |
|---|---|---|
| Due reminder emitted once | First run creates reminder and notifies | `tests/Integration/Maintenance/Application/EvaluateMaintenanceRemindersMessageHandlerIntegrationTest.php` |
| Duplicate run | Second run does not duplicate reminder | `tests/Integration/Maintenance/Application/EvaluateMaintenanceRemindersMessageHandlerIntegrationTest.php` (`testHandlerGeneratesSingleReminderAndPreventsDuplicates`) |
| Not due rule | No reminder generated | `tests/Integration/Maintenance/Application/EvaluateMaintenanceRemindersMessageHandlerIntegrationTest.php` (`testHandlerSkipsRuleWhenNotDueYet`) |

## API and ownership boundaries

| Scenario | Expected behavior | Coverage |
|---|---|---|
| Maintenance event CRUD for owner | Works for owner, validates payloads | `tests/Functional/Maintenance/MaintenanceEventApiTest.php` |
| Cross-owner access to events | Forbidden/not found across owners | `tests/Functional/Maintenance/MaintenanceEventApiTest.php` |
| Planned costs + variance | Planned/actual/variance query remains correct | `tests/Functional/Maintenance/MaintenancePlannedCostApiTest.php` |

## Web UI flows

| Scenario | Expected behavior | Coverage |
|---|---|---|
| User maintenance dashboard/forms | Timeline/planner + create/edit event/plan | `tests/Functional/Maintenance/MaintenanceWebUiTest.php` |
| Admin maintenance read scope | Admin can view events/reminders, user cannot | `tests/Functional/Admin/AdminApiManagementTest.php`, `tests/Functional/Admin/AdminBackofficeUiTest.php` |

## CI gate

Required jobs to keep this matrix effective:
- `php-cs-fixer-check`
- `phpstan`
- `phpunit-unit`
- `phpunit-integration`
- `phpunit-functional`

Current CI workflows already execute these checks via:
- `.github/workflows/pull-request-checks.yml`
- `.github/workflows/tests.yml`
