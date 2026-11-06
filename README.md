# reproducer-symfony-6-4-49-50-issue

> **INVALID: not a Symfony bug.** The changed behaviour in `symfony/form` 6.4.50 is an intended bug fix ([#67772](https://github.com/symfony/symfony/pull/67772), fixes [#61484](https://github.com/symfony/symfony/issues/61484)): a validation error is no longer attached to a disabled sibling form with the same property path. The application where this was found relied on the old behaviour (its tests submitted the field of a disabled step), so the fix belongs in that application. The repository is kept only as a reference in case something related shows up again.

Behaviour change of the form `ViolationMapper` between `symfony/form` 6.4.49 and 6.4.50.

## Setup

```bash
composer install
```

## Reproduce

```bash
composer require symfony/form:6.4.49 && php bin/console app:repro
# isValid: true
#   error on "root.personal_infos.email": This value should not be blank.

composer require symfony/form:6.4.50 && php bin/console app:repro
# isValid: false
#   error on "root.details.email": This value should not be blank.
```

## Form structure

Reproducer (`src/Command/ReproCommand.php`):

```
root                      FormType, data: [] (array, no data_class)
├── details               FormType, inherit_data, enabled (active step)
│   └── email             EmailType, NotBlank           property path: [email]
└── personal_infos        FormType, inherit_data, disabled (inactive step)
    └── email             EmailType, no constraints     property path: [email]
```

The application this was found in is a multi step registration form. Each step is a `StepFormType` with `inherit_data: true`, which is `disabled` when it is not the active step. Real structure, reduced to the relevant fields:

```
contact_register_form                         root, array data, no data_class
├── details                                   StepFormType, inherit_data, enabled (active step)
│   └── email                                 EmailType, constraints: Email, NotBlank
└── personal_infos                            StepFormType, inherit_data, disabled (inactive step)
    └── form                                  ContactFormType, inherit_data
        └── contact_section                   (inherit_data)
            └── email                         EmailType
```

- Because of `inherit_data`, both `email` fields read and write the same key `[email]` of the root array
- The `NotBlank` of `details.email` produces a violation with the property path `children[details].children[email].data`
- The `ViolationMapper` then finds two forms for the segment `email`: the enabled `details.email` and the disabled `personal_infos...email`
- Request data: `contact_register_form[personal_infos][form][contact_section][email]=...` is submitted, `details[email]` is missing and therefore empty

## Result

- Up to 6.4.49 the `NotBlank` violation of `details.email` is mapped to the disabled `personal_infos.email`. A disabled form is always valid, so the root form is valid
- Since 6.4.50 the violation stays on `details.email`. The root form is invalid

## Findings in the application

- 4 functional tests failed after updating to 6.4.50. All of them relied on the old behaviour
- 3 registration tests submitted the email of the disabled step (`personal_infos`) instead of the active `details` step. The empty `details.email` violated `NotBlank`, but the error was swallowed by the disabled sibling
- `testRegistrationOneTimeRegistrationAlreadyExists` only passed because the invite lookup ran with no phone and no email
- One invitation test (contact without email, flow config `email.required = false`) still fails: `details.email` always has `NotBlank`, so the form was only valid because of the swallowed error. This is a real behaviour change for invitees without an email address and needs a code change in the application
- Fix in the application: tests submit the active step, `NotBlank` follows the `required` config

## Check for open Symfony issues (2026-11-06)

- No open issue or PR about `ViolationMapper` or a regression after #67772 and #67776 (searched issues and PRs created since 2026-11-01)
- #67772 fixes #61484 and is marked as a bug fix, #67776 is a follow-up for data constraints
- #67825 (validation groups callable, 7.4.14) is closed and unrelated: the application does not use `validation_groups`

## Suspects

Both in 6.4.50, both touch `ViolationMapper`:

- https://github.com/symfony/symfony/pull/67772 Keep validation errors on their originating form
- https://github.com/symfony/symfony/pull/67776 Map data validation errors to the sibling form that has the field

Reverting only `ViolationMapper.php` to the 6.4.48 version restores the old result in the real application this was found in.

## Question

The new result looks like the more correct one. An application that submits the wrong step's field only passed validation because the error was swallowed by the disabled sibling. Is this change intended for a patch release?
