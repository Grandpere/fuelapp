# UI i18n Migration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Introduce Symfony translations with French as the default locale and English as the fallback locale, then migrate the main user-facing UI copy in a progressive, maintainable way.

**Architecture:** The implementation adds standard Symfony translator configuration and message catalogs, then migrates shared UI strings before feature-specific templates. Controllers that currently push hard-coded flash copy will emit translation keys and parameters so the rendering layer stays responsible for localization.

**Tech Stack:** Symfony 8, Twig, YAML translation catalogs, PHPUnit functional tests, Make quality gates.

---

## File structure

**Create**
- `config/packages/translation.yaml`
- `translations/messages.fr.yaml`
- `translations/messages.en.yaml`
- `tests/Functional/Ui/TranslationSmokeTest.php`

**Modify**
- `config/packages/framework.yaml`
- `templates/base.html.twig`
- `templates/dashboard/index.html.twig`
- `templates/contact/index.html.twig`
- `templates/security/login.html.twig`
- `templates/receipt/index.html.twig`
- `templates/receipt/new.html.twig`
- `templates/receipt/show.html.twig`
- `templates/receipt/edit_metadata.html.twig`
- `templates/receipt/edit_lines.html.twig`
- `templates/receipt/_form.html.twig`
- `templates/import/index.html.twig`
- `templates/import/show.html.twig`
- `templates/vehicle/index.html.twig`
- `templates/vehicle/show.html.twig`
- `templates/vehicle/form.html.twig`
- `templates/station/index.html.twig`
- `templates/station/show.html.twig`
- `templates/station/edit.html.twig`
- `templates/maintenance/index.html.twig`
- `templates/maintenance/event_form.html.twig`
- `templates/maintenance/plan_form.html.twig`
- `templates/maintenance/rule_form.html.twig`
- `templates/analytics/index.html.twig`
- `templates/public_fuel_station/index.html.twig`
- `src/Security/Oidc/OidcStartController.php`
- `src/Security/Oidc/OidcCallbackController.php`
- `src/Receipt/UI/Web/Controller/CreateReceiptController.php`
- `src/Receipt/UI/Web/Controller/DeleteReceiptController.php`
- `src/Receipt/UI/Web/Controller/EditReceiptMetadataController.php`
- `src/Receipt/UI/Web/Controller/EditReceiptLinesController.php`
- `src/Vehicle/UI/Web/Controller/VehicleFormController.php`
- `src/Vehicle/UI/Web/Controller/VehicleDeleteController.php`
- `src/Station/UI/Web/Controller/EditStationController.php`
- `src/Station/UI/Web/Controller/ToggleFavoriteStationController.php`
- `src/Maintenance/UI/Web/Controller/MaintenanceEventFormController.php`
- `src/Maintenance/UI/Web/Controller/MaintenancePlannedCostFormController.php`
- `src/Maintenance/UI/Web/Controller/MaintenanceReminderRuleFormController.php`
- `src/Maintenance/UI/Web/Controller/MaintenanceReminderRuleDeleteController.php`
- `src/Import/UI/Web/Controller/ImportJobWebController.php`
- `src/Import/UI/Web/Controller/ImportJobFinalizeWebController.php`
- `src/Import/UI/Web/Controller/ImportJobReparseWebController.php`
- `src/Import/UI/Web/Controller/ImportJobDeleteWebController.php`
- user-facing functional tests that assert English labels today

---

### Task 1: Translation infrastructure

**Files:**
- Create: `config/packages/translation.yaml`
- Create: `translations/messages.fr.yaml`
- Create: `translations/messages.en.yaml`
- Modify: `config/packages/framework.yaml`

- [ ] **Step 1: Write the failing infrastructure smoke test**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Functional\Ui;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TranslationSmokeTest extends KernelTestCase
{
    public function testFrenchIsDefaultLocaleAndEnglishFallbackExists(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $translator = $container->get(TranslatorInterface::class);
        self::assertInstanceOf(TranslatorInterface::class, $translator);
        self::assertSame('fr', $translator->getLocale());
        self::assertSame('Tableau de bord', $translator->trans('nav.dashboard', locale: 'fr'));
        self::assertSame('Dashboard', $translator->trans('nav.dashboard', locale: 'en'));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php bin/phpunit tests/Functional/Ui/TranslationSmokeTest.php`
Expected: FAIL because translator config and catalogs do not exist yet.

- [ ] **Step 3: Add standard Symfony translation configuration**

```yaml
# config/packages/framework.yaml
framework:
    default_locale: 'fr'
    enabled_locales: ['fr', 'en']
```

```yaml
# config/packages/translation.yaml
framework:
    translator:
        default_path: '%kernel.project_dir%/translations'
        fallbacks:
            - en
```

```yaml
# translations/messages.fr.yaml
nav:
    dashboard: 'Tableau de bord'
```

```yaml
# translations/messages.en.yaml
nav:
    dashboard: 'Dashboard'
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php bin/phpunit tests/Functional/Ui/TranslationSmokeTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add config/packages/framework.yaml config/packages/translation.yaml translations/messages.fr.yaml translations/messages.en.yaml tests/Functional/Ui/TranslationSmokeTest.php
git commit -m "feat: add UI translation infrastructure"
```

### Task 2: Shared shell and reusable labels

**Files:**
- Modify: `templates/base.html.twig`
- Modify: `translations/messages.fr.yaml`
- Modify: `translations/messages.en.yaml`
- Test: `tests/Functional/Security/TopbarNavigationWebUiTest.php`

- [ ] **Step 1: Extend the topbar test with translated expectations**

```php
self::assertStringContainsString('>Tableau de bord<', $receiptsContent);
self::assertStringContainsString('>Contact<', $receiptsContent);
self::assertStringContainsString('>Carte carburants<', $receiptsContent);
self::assertStringContainsString('>Déconnexion<', $receiptsContent);
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php bin/phpunit tests/Functional/Security/TopbarNavigationWebUiTest.php`
Expected: FAIL because the topbar still renders hard-coded English labels.

- [ ] **Step 3: Migrate the shared layout to translations**

```twig
<a class="topbar-nav-link" href="{{ path('ui_dashboard') }}">{{ 'nav.dashboard'|trans }}</a>
<a class="topbar-nav-link" href="{{ path('ui_receipt_index') }}">{{ 'nav.receipts'|trans }}</a>
<a class="topbar-nav-link" href="{{ path('ui_public_fuel_station_map') }}">{{ 'nav.public_fuel_map'|trans }}</a>
<button class="btn btn-secondary btn-compact" type="submit">{{ 'action.sign_out'|trans }}</button>
```

```yaml
nav:
    dashboard: 'Tableau de bord'
    receipts: 'Reçus'
    imports: 'Imports'
    vehicles: 'Véhicules'
    stations: 'Stations'
    public_fuel_map: 'Carte carburants'
    maintenance: 'Entretien'
    analytics: 'Analyses'
    contact: 'Contact'
    admin: 'Back-office'

action:
    sign_out: 'Déconnexion'
    save: 'Enregistrer'
    reset: 'Réinitialiser'
    apply: 'Appliquer'
    cancel: 'Annuler'
```

- [ ] **Step 4: Run the topbar test**

Run: `php bin/phpunit tests/Functional/Security/TopbarNavigationWebUiTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add templates/base.html.twig translations/messages.fr.yaml translations/messages.en.yaml tests/Functional/Security/TopbarNavigationWebUiTest.php
git commit -m "feat: translate shared user UI shell"
```

### Task 3: Flash-message translation flow

**Files:**
- Modify: `templates/base.html.twig`
- Modify: user-facing web controllers currently calling `addFlash(...)`
- Modify: `translations/messages.fr.yaml`
- Modify: `translations/messages.en.yaml`
- Test: user-facing functional tests covering create/update/delete flows

- [ ] **Step 1: Write one failing flash-message test**

```php
self::assertStringContainsString('Véhicule créé.', (string) $response->getContent());
```

- [ ] **Step 2: Run the targeted test to confirm the current English flash**

Run: `php bin/phpunit tests/Functional/Vehicle/VehicleWebUiTest.php`
Expected: FAIL on the French flash assertion.

- [ ] **Step 3: Change controllers to emit translation keys instead of hard-coded sentences**

```php
$this->addFlash('success', 'flash.vehicle.created');
$this->addFlash('success', 'flash.receipt.updated');
$this->addFlash('error', 'flash.csrf.invalid');
```

```twig
{% for message in app.flashes(label) %}
    <div class="flash flash-{{ label }}">{{ message|trans }}</div>
{% endfor %}
```

```yaml
flash:
    csrf:
        invalid: 'Jeton CSRF invalide.'
    vehicle:
        created: 'Véhicule créé.'
        updated: 'Véhicule mis à jour.'
        deleted: 'Véhicule supprimé.'
```

- [ ] **Step 4: Run targeted user-flow tests**

Run: `php bin/phpunit tests/Functional/Vehicle/VehicleWebUiTest.php tests/Functional/Receipt/ReceiptWebUiTest.php tests/Functional/Import/ImportWebUiTest.php`
Expected: PASS after translating the flashed messages used by those flows.

- [ ] **Step 5: Commit**

```bash
git add templates/base.html.twig src/Security/Oidc/OidcStartController.php src/Security/Oidc/OidcCallbackController.php src/Receipt/UI/Web/Controller src/Vehicle/UI/Web/Controller src/Station/UI/Web/Controller src/Maintenance/UI/Web/Controller src/Import/UI/Web/Controller translations/messages.fr.yaml translations/messages.en.yaml tests/Functional
git commit -m "feat: translate user-facing flash messages"
```

### Task 4: Receipt and import screens

**Files:**
- Modify: `templates/receipt/*.twig`
- Modify: `templates/import/*.twig`
- Modify: `translations/messages.fr.yaml`
- Modify: `translations/messages.en.yaml`
- Test: `tests/Functional/Import/ImportWebUiTest.php`

- [ ] **Step 1: Write failing assertions for key French labels**

```php
self::assertStringContainsString('Importer des reçus', $pageContent);
self::assertStringContainsString('Nouveau reçu', $pageContent);
self::assertStringContainsString('Détails du reçu', $pageContent);
```

- [ ] **Step 2: Run receipt/import functional tests**

Run: `php bin/phpunit tests/Functional/Import/ImportWebUiTest.php`
Expected: FAIL because receipt/import templates still render English labels.

- [ ] **Step 3: Translate receipt and import templates**

```twig
{% block title %}{{ 'import.title'|trans }}{% endblock %}
<h1 class="page-title">{{ 'import.title'|trans }}</h1>
<a class="btn btn-primary">{{ 'receipt.action.new'|trans }}</a>
```

```yaml
import:
    title: 'Importer des reçus'
    action:
        upload: 'Importer'
        finalize: 'Finaliser'
```

- [ ] **Step 4: Run the targeted tests again**

Run: `php bin/phpunit tests/Functional/Import/ImportWebUiTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add templates/import/index.html.twig templates/import/show.html.twig templates/receipt/_form.html.twig templates/receipt/_row.html.twig templates/receipt/edit_lines.html.twig templates/receipt/edit_metadata.html.twig templates/receipt/index.html.twig templates/receipt/new.html.twig templates/receipt/show.html.twig translations/messages.fr.yaml translations/messages.en.yaml tests/Functional/Import/ImportWebUiTest.php
git commit -m "feat: translate receipt and import screens"
```

### Task 5: Vehicle, station, maintenance, analytics, dashboard, and contact screens

**Files:**
- Modify: `templates/dashboard/index.html.twig`
- Modify: `templates/contact/index.html.twig`
- Modify: `templates/security/login.html.twig`
- Modify: `templates/vehicle/*.twig`
- Modify: `templates/station/*.twig`
- Modify: `templates/maintenance/*.twig`
- Modify: `templates/analytics/index.html.twig`
- Modify: `templates/public_fuel_station/index.html.twig`
- Modify: `translations/messages.fr.yaml`
- Modify: `translations/messages.en.yaml`
- Test: corresponding functional tests

- [ ] **Step 1: Add failing French assertions on one screen per area**

```php
self::assertStringContainsString('Connexion', $loginContent);
self::assertStringContainsString('Entretien', $maintenanceContent);
self::assertStringContainsString('Analyses', $analyticsContent);
self::assertStringContainsString('Véhicules', $vehicleContent);
```

- [ ] **Step 2: Run the relevant tests**

Run: `php bin/phpunit tests/Functional/Maintenance/MaintenanceWebUiTest.php tests/Functional/Station/StationWebUiTest.php tests/Functional/Vehicle/VehicleWebUiTest.php`
Expected: FAIL on untranslated labels.

- [ ] **Step 3: Translate the remaining user-facing templates**

```twig
<h1 class="page-title">{{ 'maintenance.title'|trans }}</h1>
<button class="btn btn-primary" type="submit">{{ 'action.apply'|trans }}</button>
<a class="btn btn-secondary">{{ 'action.reset'|trans }}</a>
```

```yaml
maintenance:
    title: 'Entretien'
analytics:
    title: 'Analyses'
vehicle:
    title: 'Véhicules'
station:
    title: 'Stations'
security:
    login:
        title: 'Connexion'
```

- [ ] **Step 4: Run the migrated-area test set**

Run: `php bin/phpunit tests/Functional/Maintenance/MaintenanceWebUiTest.php tests/Functional/PublicFuelStation/PublicFuelStationWebUiTest.php tests/Functional/Station/StationWebUiTest.php tests/Functional/Vehicle/VehicleWebUiTest.php tests/Functional/Security/DashboardWebUiTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add templates/dashboard/index.html.twig templates/contact/index.html.twig templates/security/login.html.twig templates/vehicle/form.html.twig templates/vehicle/index.html.twig templates/vehicle/show.html.twig templates/station/edit.html.twig templates/station/index.html.twig templates/station/show.html.twig templates/maintenance/event_form.html.twig templates/maintenance/index.html.twig templates/maintenance/plan_form.html.twig templates/maintenance/rule_form.html.twig templates/analytics/index.html.twig templates/public_fuel_station/index.html.twig translations/messages.fr.yaml translations/messages.en.yaml tests/Functional
git commit -m "feat: translate remaining user-facing screens"
```

### Task 6: Quality gates, docs, and handover

**Files:**
- Modify: `docs/ai/memory.md`
- Modify: `tasks/todo.md`

- [ ] **Step 1: Record the implementation lesson**

```md
## 2026-05-16 - User UI translations should use keys in controllers and Twig
- Symptom: hard-coded mixed-language UI copy made French rollout inconsistent and difficult to maintain.
- Root cause: user-facing labels and flash messages were embedded directly in templates and controllers without translation catalogs.
- Fix: enable Symfony translator, centralize user-facing copy in `translations/messages.*.yaml`, and flash translation keys instead of final sentences.
- Prevention: every new user-facing string in web UI must be added through translation keys from the start.
```

- [ ] **Step 2: Mark the task progress**

```md
- [completed] Implement translation infrastructure and migrate user-facing UI wave 1.
- [pending] Migrate admin/back-office wave 2.
```

- [ ] **Step 3: Run the project quality gates**

Run: `make phpstan`
Expected: PASS

Run: `make phpunit-unit`
Expected: PASS

Run: `make phpunit-integration`
Expected: PASS

Run: `make php-cs-fixer-check`
Expected: PASS

- [ ] **Step 4: Ask the user to run the manual functional suite**

Run by user: `make phpunit-functional`
Expected: user shares failures, if any, for follow-up fixes

- [ ] **Step 5: Commit**

```bash
git add docs/ai/memory.md tasks/todo.md
git commit -m "docs: record UI translation rollout guidance"
```

---

## Self-review

### Spec coverage
- Translator infrastructure: covered by Task 1.
- French default and English fallback: covered by Task 1.
- Shared shell first: covered by Task 2.
- User-facing wave 1 migration: covered by Tasks 3, 4, and 5.
- Test adaptation: covered by Tasks 1 through 5.
- Admin deferred to wave 2: captured in Task 6 handover/progress.

### Placeholder scan
- No `TODO`, `TBD`, or “similar to previous task” shortcuts remain.
- Each task has explicit files, commands, and expected outcomes.

### Type consistency
- Translation files consistently use `messages.fr.yaml` and `messages.en.yaml`.
- Shared keys use the same namespaces across tasks: `nav`, `action`, `flash`, and feature namespaces.
