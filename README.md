# reproducer-symfony-6-4-49-50-issue

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

## Form

- Root form with two steps, both `inherit_data`, so both `email` fields have the property path `[email]`
- `details` is the active step: enabled, `email` has `NotBlank`, submitted empty
- `personal_infos` is the inactive step: `disabled`, `email` without constraints

See `src/Command/ReproCommand.php`.

## Result

- Up to 6.4.49 the `NotBlank` violation of `details.email` is mapped to the disabled `personal_infos.email`. A disabled form is always valid, so the root form is valid
- Since 6.4.50 the violation stays on `details.email`. The root form is invalid

## Suspects

Both in 6.4.50, both touch `ViolationMapper`:

- https://github.com/symfony/symfony/pull/67772 Keep validation errors on their originating form
- https://github.com/symfony/symfony/pull/67776 Map data validation errors to the sibling form that has the field

Reverting only `ViolationMapper.php` to the 6.4.48 version restores the old result in the real application this was found in.

## Question

The new result looks like the more correct one. An application that submits the wrong step's field only passed validation because the error was swallowed by the disabled sibling. Is this change intended for a patch release?
