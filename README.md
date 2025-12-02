# Http Response Validator Bundle

A Symfony bundle for HTTP responses validating using a simple Result monad and handler chains.

The main idea: the input is `ResponseInterface` (eg from `symfony/http-client`), then through a series of handlers (pipelines) the status code is validated, the content is logged, JSON is extracted and the structure is checked. Each handler returns a `Result', so the chain breaks on the first error and throws an exception with a unique message ID.

## Characteristics

- Declarative stacking steps over `Result::success(...)->bind(...)`
- Built-in ready-to-use handlers:
  - `ZJKiza\HttpResponseValidator\Handler\HttpResponseLoggerHandler` – validates the expected status, logs the response, and masks sensitive keys
  - `ZJKiza\HttpResponseValidator\Handler\ExtractResponseJsonHandler` – decodes the JSON body (associatively or as an object)
  - `ZJKiza\HttpResponseValidator\Handler\ValidateArrayKeysExistHandler` – checks whether certain keys are present in the associative array
- Simple extension: add your own handler and register it with a single service tag
- Clear error messages with `Message ID=<hex>` for easy tracking in logs


## Installation

Add "zjkiza/http-response-validator" to your composer.json file.

```
composer require zjkiza/http-response-validator
```


## Symfony integration

Bundle wires up all classes together and provides method to easily setup.

1. Register bundle within your configuration (i.e: `bundles.php`).


```php
<?php

declare(strict_types=1);

return [
    // ...
    ZJKiza\HttpResponseValidator\ZJKizaHttpResponseValidatorBundle::class => ['all' => true],
];
```

2) Bundle automatically registers services and factory class for handlers via service tag `zjkiza.http_response_validate.handler_factory`.


## Quick start

Example with `symfony/http-client` and built-in handlers:

```php
use Symfony\Component\HttpClient\HttpClient;
use ZJKiza\HttpResponseValidator\Monad\Result;
use ZJKiza\HttpResponseValidator\Contract\HandlerFactoryInterface;
use ZJKiza\HttpResponseValidator\Handler\HttpResponseLoggerHandler;
use ZJKiza\HttpResponseValidator\Handler\ExtractResponseJsonHandler;
use ZJKiza\HttpResponseValidator\Handler\ValidateArrayKeysExistHandler;

// ... in the service/controller with DI you get a handler factory
public function __construct(private HandlerFactoryInterface $handlerFactory) {}

public function fetch(): array
{
    $client = HttpClient::create();
    $response = $client->request('GET', 'https://example.com/api/user');

    // We arrange pipeline steps; getOrThrow() will throw an exception on error
    $data = Result::success($response)
        ->bind($this->handlerFactory->create(HttpResponseLoggerHandler::class)
            ->setExpectedStatus(200)
            ->addSensitiveKeys(['password', 'token']))
        ->bind($this->handlerFactory->create(ExtractResponseJsonHandler::class)
            ->setAssociative(true))
        ->bind($this->handlerFactory->create(ValidateArrayKeysExistHandler::class)
            ->setKeys(['id', 'email']))
        ->getOrThrow();

    // $data is now an associative array with the required keys
    return $data;
}
```

If a step fails, an exception will be thrown with a message containing a unique `Message ID=...', and the error will be logged via the PSR‑3 logger.

## Ugrađeni handler‑i

All handlers implement `ZJKiza\HttpResponseValidator\Contract\HandlerInterface` and expose a fluent API for configuration.

- `HttpResponseLoggerHandler`
  - What it does: Validates the expected HTTP status, logs the response, and masks the values for the defined keys in the body
  - Essential methods:
    - `setExpectedStatus(int $status): self`
    - `addSensitiveKeys(string[] $keys): self`

- `ExtractResponseJsonHandler`
  - What it does: calls `$response->getContent(false)`, decodes the JSON, and returns the result as a string or object
  - Essential methods:
    - `setAssociative(bool $assoc = true): self` – when `true' returns an associative array; when `false' is an object

- `ValidateArrayKeysExistHandler`
  - What it does: Checks whether the passed associative string contains required keys
  - Essential methods:
    - `setKeys(string[] $keys): self`


## How to add your own Handler

1) Create a class that implements `HandlerInterface` (recommendation: inherit from `AbstractHandler` and use the `TagIndexMethod` trait). Example: validating the format of an email field in a string.

```php
<?php

declare(strict_types=1);

namespace App\HttpResponse\Handler;

use ZJKiza\HttpResponseValidator\Handler\AbstractHandler;
use ZJKiza\HttpResponseValidator\Handler\Factory\TagIndexMethod;
use ZJKiza\HttpResponseValidator\Contract\HandlerInterface;
use ZJKiza\HttpResponseValidator\Monad\Result;

/**
 * @implements HandlerInterface<array<string,mixed>, array<string,mixed>>
 */
final class ValidateEmailFormatHandler extends AbstractHandler implements HandlerInterface
{
    use TagIndexMethod; // automates the static index for factory registration

    public function __invoke(mixed $value): Result
    {
        if (!isset($value['email']) || !\filter_var($value['email'], FILTER_VALIDATE_EMAIL)) {
            return $this->fail('Email is not valid..');
        }

        return Result::success($value);
    }
}
```

2) Register the service and be sure to add a tag `zjkiza.http_response_validate.handler_factory`:

```yaml
# config/services.yaml
services:
  App\HttpResponse\Handler\ValidateEmailFormatHandler:
    tags:
      - { name: 'zjkiza.http_response_validate.handler_factory' }
```

3) Use it in a chain across `HandlerFactoryInterface`:

```php
$result = Result::success($response)
    ->bind($handlerFactory->create(HttpResponseLoggerHandler::class)->setExpectedStatus(200))
    ->bind($handlerFactory->create(ExtractResponseJsonHandler::class)->setAssociative(true))
    ->bind($handlerFactory->create(ValidateEmailFormatHandler::class))
    ->getOrThrow();
```

Note: if you don't want to use the `TagIndexMethod`, make sure your class implements the static `getIndex(): string` method that returns a unique index (most commonly FQCN).


## Logging in and message ID

Errors are logged via the PSR‑3 logger. Messages are automatically prefixed with `Message ID=<hex>` (see helper `ZJKiza\HttpResponseValidator\addIdInMessage()`), which facilitates correlation between logs and client messages.

## Development and tests (optional for contributors)

```
composer phpunit
composer phpstan
composer psalm
composer php-cs-fixer
```


## License

MIT. View the `LICENSE' file in the repository.

## What else needs to be done
[ ] Update readme
[+] When ValidateArrayKeysExistHandler encounters the first error in the keys, it does not immediately throw an exception, but collects all the missing keys and only then throws the exception. 