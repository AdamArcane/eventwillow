<div align="center">
    <picture>
        <source srcset="public/images/light_logo.png" media="(prefers-color-scheme: dark)">
        <img src="public/images/dark_logo.png" alt="Event Schedule" width="350">
    </picture>
    <p>
        An open-source platform to share events, sell tickets and bring communities together.
    </p>
    <p>
        <a href="https://eventwillow.com">Website</a> &middot;
        <a href="https://eventwillow/docs">Docs</a>
    </p>
</div>


# EventWillow

**Open-source event management, ticketing, and community engagement.**

EventWillow is a self-hostable platform designed to help organizations, venues, performers, and communities create events, manage registrations, sell tickets, and connect with their audiences.

Built on Laravel and Vue.js, EventWillow brings event publishing, scheduling, ticketing, and audience management together in one platform, giving organizations greater flexibility and control over their event infrastructure.

EventWillow is a fork of [Event Schedule](https://github.com/eventschedule/eventschedule), building upon its open-source foundation.

## Overview

Managing events shouldn't require juggling multiple services or surrendering control of your audience and data.

EventWillow provides an integrated solution for managing the event lifecycle, from publishing an event and accepting registrations to communicating with attendees and tracking engagement.

Whether you're organizing community gatherings, managing a venue calendar, or hosting ticketed events, the goal is to offer a flexible platform that can be hosted and managed on your own infrastructure.

## Features

### Event Management
- Create and manage events and calendars.
- Support recurring events and scheduling exceptions.
- Organize events with categories and sub-schedules.
- Manage public, private, draft, and unlisted events.
- Accept public event submissions with approval workflows.
- Provide embeddable calendars and calendar subscriptions.

### Registration and Ticketing
- Free event registration and paid ticketing.
- Multiple ticket types and pricing options.
- Discount codes, add-ons, and group pricing.
- Capacity limits and attendee management.
- Reserved seating and seating plan management.
- QR code tickets and attendee check-in.
- Payment integrations, including Stripe and PayPal.

### Scheduling and Integrations
- Appointment scheduling and availability management.
- Calendar synchronization with supported providers.
- Event importing from external calendars and sources.
- REST API and webhook integration capabilities.

### Audience Engagement
- Email newsletters and subscriber management.
- Event announcements and reminders.
- Attendee feedback and community participation.
- Event promotion and sharing tools.
- Reporting and analytics.

### Administration
- Multiple schedules and team management.
- Custom branding, themes, and styles.
- Multilingual interface support.
- Two-factor authentication and audit logging.
- Backup and restore capabilities.
- Optional integrations with external services.

Some functionality requires additional configuration, external service credentials, or compatible payment providers.

## Technology Stack

EventWillow is built using established open-source technologies:

| Technology | Purpose |
|---|---|
| PHP 8.2+ | Server-side application runtime |
| Laravel 11 | Backend application framework |
| Vue.js 3 | Interactive frontend components |
| Tailwind CSS | Interface styling |
| Vite | Frontend build tooling |
| MySQL / MariaDB | Database storage |

## Getting Started

EventWillow can be deployed to a compatible PHP hosting environment or run locally for development.

### Requirements

- PHP 8.2 or newer, with required extensions
- Composer
- Node.js and npm
- MySQL or MariaDB
- Apache or Nginx
- A properly configured web server and writable application storage

### Local Development

Clone the repository:

```bash
git clone https://github.com/AdamArcane/eventwillow.git
cd eventwillow
```

Install PHP dependencies:

```bash
composer install
```

Install frontend dependencies:

```bash
npm ci
```

Create your environment configuration:

```bash
cp .env.example .env
```

Update `.env` with your local application URL, database credentials, mail settings, and other required configuration.

Generate the application key:

```bash
php artisan key:generate
```

Run database migrations against your configured development database:

```bash
php artisan migrate
```

Create the public storage link:

```bash
php artisan storage:link
```

Start the frontend development server:

```bash
npm run dev
```

In a separate terminal, start Laravel:

```bash
php artisan serve
```

Open the local application URL printed by Laravel.

**Important:** The commands above provide a general Laravel development setup. Additional configuration may be required depending on your environment and which EventWillow features you intend to use. Do not run migrations against an existing production database without a verified backup.

## Configuration

Application settings are managed through the `.env` file.

EventWillow supports optional integrations for functionality such as:

- Outbound email and notifications
- Payment processing
- Google and Microsoft calendar services
- Artificial intelligence features
- External object storage
- Authentication providers
- Web push notifications

Refer to [`.env.example`](.env.example) for available environment variables.

Never commit API keys, application secrets, production credentials, or your populated `.env` file to version control.

## Development and Testing

Run the Laravel test suite:

```bash
php artisan test
```

Check the Vue component bindings:

```bash
npm run check:vue
```

Build production frontend assets:

```bash
npm run build
```

The test suite requires a correctly configured, isolated test database. Review the repository's test configuration and development instructions before running tests against an unfamiliar environment.

## Project Status

EventWillow is an actively evolving fork of Event Schedule.

The project builds upon the functionality provided by the upstream application while allowing for independent development, customization, and improvements.

Features, documentation, and implementation details may change as development continues.

For current development activity, see the repository's commit history and issue tracker.

## Contributing

Feedback, bug reports, and constructive contributions are welcome.

If you discover a problem or have an idea for an improvement, please open a [GitHub issue](https://github.com/AdamArcane/eventwillow/issues).

For code contributions, consider opening an issue first to discuss substantial changes before submitting a pull request.

## Acknowledgments

EventWillow is derived from [Event Schedule](https://github.com/eventschedule/eventschedule), originally created by Hillel Coren.

The project acknowledges and appreciates the work of the original author and upstream contributors.

EventWillow is an independent fork and is not presented as an official release of, or endorsed by, the upstream project.

## License

EventWillow retains the Attribution Assurance License (AAL) of the original Event Schedule project.

Redistribution and modification are permitted subject to the license's attribution and other conditions.

See the [LICENSE](LICENSE) file for the complete terms, including the attribution requirements applicable to derivative works.

---

**EventWillow**  
*Bringing events and communities together.*

[Source Code](https://github.com/AdamArcane/eventwillow) | [Report an Issue](https://github.com/AdamArcane/eventwillow/issues) | [Original Project](https://github.com/eventschedule/eventschedule)
