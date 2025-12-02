# NWC PHP

Add the following to your composer file:

```json
"repositories": [
  {
    "type": "vcs",
    "url": "https://github.com/getAlby/nwc-php"
  }
],
"require": {
  "getalby/nwc-php": "v1.0.1"
}
```

## Development

### Installation

```bash
composer install
```

### Testing

Create an .env file and update your connection secret:

```bash
cp .env.example.env
```

> You should use an isolated connection secret with all permissions for testing.

```bash
./vendor/bin/phpunit tests
```
