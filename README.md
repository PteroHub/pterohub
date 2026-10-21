# PteroHub

**A modern hosting management and billing platform integrated with Pterodactyl.**

PteroHub is a self-hosted platform designed to manage hosting services, customer accounts, orders, billing, and game server provisioning through the Pterodactyl Panel.

The project aims to provide a unified experience for hosting providers, administrators, and customers.

## Features

PteroHub is designed around the following capabilities:

- Modern, responsive public website
- Customer registration and authentication
- Customer dashboard
- Hosting products and service plans
- Order and service management
- Billing and invoice management
- Payment gateway integration
- Pterodactyl API integration
- Game server provisioning and management
- Administrative dashboard
- Centralized application settings
- Customizable branding

Feature availability depends on the installed release.

## Requirements

PteroHub requires a Linux-based web hosting environment with a supported PHP runtime, database server, web server, and dependency manager.

Exact PHP, database, Node.js, and Composer requirements must be verified against the release before installation.

A working Pterodactyl Panel is required to use server provisioning features.

## Installation

Read [Installation Guide](docs/INSTALLATION.md) for the complete installation procedure.

Additional documentation:

- [Configuration](docs/CONFIGURATION.md)
- [Updating](docs/UPDATING.md)
- [Troubleshooting](docs/TROUBLESHOOTING.md)
- [Security](docs/SECURITY.md)
- [Architecture](docs/ARCHITECTURE.md)

## Pterodactyl Integration

PteroHub communicates with Pterodactyl using its API.

Configure the Pterodactyl Panel URL and an appropriately scoped API key through the supported application configuration.

Keep API credentials private and never commit them to the repository.

## Development

Contributions, bug reports, and feature suggestions are welcome through GitHub Issues and Pull Requests.

Before submitting changes, review the contribution guidelines and test your changes in a development environment.

## Security

Never publish production environment files, API keys, database credentials, payment gateway secrets, or customer information.

Please report security vulnerabilities privately rather than opening public issues containing sensitive information.

## License

See the LICENSE file for the applicable license and redistribution terms.

## Disclaimer

PteroHub is an independent project and is not officially affiliated with or endorsed by Pterodactyl.

Pterodactyl is a separate project maintained by its respective developers and contributors.
