# psr18-adapter/ruflin-elastica

## Install

Via [Composer](https://getcomposer.org/doc/00-intro.md)

```bash
composer require psr18-adapter/ruflin-elastica
```

## Usage

This would be how a service configuration would look like when using symfony/dependency-injection:
 
```yml
services:
  Psr18Adapter\Ruflin\Elastica\RuflinPsr18Transport:
    autowire: true
    arguments:
      $client: '@httplug.client.es'
  Elastica\Connection:
    arguments:
      - { host: "%es_host%", port: "%es_port%" }
    calls:
      - [setTransport, ['@Psr18Adapter\Ruflin\Elastica\RuflinPsr18Transport']]
  Elastica\Client:
    calls:
      - [setConnections, [['@Elastica\Connection']]]
```

## Licensing

MIT license. Please see [License File](LICENSE) for more information.
