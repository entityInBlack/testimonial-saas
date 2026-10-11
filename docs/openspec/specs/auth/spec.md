# auth Specification

## Purpose

Lets a business owner register, log in, log out, and reset their password using email and password only (no social login, no email verification in v1), with throttles that protect against credential stuffing.

## Requirements

### Requirement: Email and password authentication
The system SHALL let users register, log in, log out, and reset their password using email and password. The system SHALL NOT require email verification in v1 (the `email_verified_at` column is kept; `MustVerifyEmail` is OFF). The system SHALL NOT offer social login in v1.

#### Scenario: Register
- **WHEN** a visitor submits the registration form with a unique email and a strong password
- **THEN** the system creates a User row and authenticates the new owner

#### Scenario: Login
- **WHEN** an owner submits valid credentials
- **THEN** the system authenticates the owner and redirects to `/dashboard`

#### Scenario: Logout
- **WHEN** an authenticated owner clicks Logout
- **THEN** the system ends the session

#### Scenario: Forgot/reset password
- **WHEN** an owner submits the forgot-password form
- **THEN** the system sends a password-reset email (log mailer in v1) and shows a confirmation

#### Scenario: Authenticated routes require login
- **WHEN** a guest visits an authenticated route (e.g. `/dashboard`, `/spaces`, `/inbox`)
- **THEN** the system redirects to login

### Requirement: Auth throttles
The system SHALL rate-limit forgot-password, reset-password, and register attempts at 5 attempts per 15 minutes, with two keys (per-IP and per-`email+IP`), enforced **inside** the Livewire component action. Named limiters SHALL be defined in `AppServiceProvider::boot()`. Login keeps Breeze's built-in `ThrottlesLogins`.

#### Scenario: Forgot-password throttle
- **WHEN** a caller invokes the forgot-password Livewire action 6 times within 15 minutes from the same IP and same email+IP
- **THEN** the 6th call returns a validation error "Too many attempts. Try again later." and does not process the action

#### Scenario: Reset-password throttle
- **WHEN** a caller invokes the reset-password Livewire action 6 times within 15 minutes from the same IP and same email+IP
- **THEN** the 6th call returns a validation error "Too many attempts. Try again later."

#### Scenario: Register throttle
- **WHEN** a caller invokes the register Livewire action 6 times within 15 minutes from the same IP and same email+IP
- **THEN** the 6th call returns a validation error "Too many attempts. Try again later."

### Requirement: Storage symlink
The system SHALL have `php artisan storage:link` run during setup so that `public/storage` resolves to `../storage/app/public`. Without it, photo URLs 404.

#### Scenario: storage:link creates symlink
- **WHEN** setup runs `php artisan storage:link`
- **THEN** `readlink(public_path('storage'))` resolves to `../storage/app/public`
