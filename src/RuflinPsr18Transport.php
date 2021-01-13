<?php

declare(strict_types=1);

namespace Psr18Adapter\Ruflin\Elastica;

use Elastica\Connection;
use Elastica\Exception\ConnectionException;
use Elastica\Exception\PartialShardFailureException;
use Elastica\Exception\ResponseException;
use Elastica\Request;
use Elastica\Response;
use Elastica\Transport\AbstractTransport;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

class RuflinPsr18Transport extends AbstractTransport
{
    /** @var ClientInterface */
    private $client;
    /** @var UriFactoryInterface */
    private $uriFactory;
    /** @var RequestFactoryInterface */
    private $requestFactory;
    /** @var StreamFactoryInterface  */
    private $streamFactory;

    public function __construct(
        Connection $connection,
        ClientInterface $client,
        UriFactoryInterface  $uriFactory,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory
    ) {
        parent::__construct($connection);

        $this->client = $client;
        $this->uriFactory = $uriFactory;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
    }

    public function exec(Request $request, array $params): Response
    {
        $connection = $this->getConnection();

        $req = $this->requestFactory->createRequest($request->getMethod(), $this->buildUrl($request));

        foreach ($connection->hasConfig('headers') ? $connection->getConfig('headers') : [] as $key => $value) {
            $req = $req->withHeader($key, $value);
        }

        $data = $request->getData();
        if ($data || '0' === $data) {
            $hasBody = $this->hasParam('postWithRequestBody') && $this->getParam('postWithRequestBody');

            if ($hasBody || $req->getMethod() === Request::GET) {
                $req = $req->withMethod(Request::POST);
            }

            if ($hasBody) {
                $request->setMethod(Request::POST);
            }

            $req = $req->withBody($this->streamFactory->createStream(
                is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data
            ));
        }

        try {
            $start = microtime(true);
            $res = $this->client->sendRequest($req);
        } catch (ClientExceptionInterface $ex) {
            throw new ConnectionException($ex->getMessage(), $request, new Response($ex->getMessage()));
        }

        $response = new Response((string) $res->getBody(), $res->getStatusCode());
        $response->setQueryTime(microtime(true) - $start);
        $response->setTransferInfo(['request_header' => $request->getMethod(), 'http_code' => $res->getStatusCode()]);

        if ($response->hasError()) {
            throw new ResponseException($request, $response);
        }

        if ($response->hasFailedShards()) {
            throw new PartialShardFailureException($request, $response);
        }

        return $response;
    }

    private function buildUrl(Request $request): UriInterface
    {
        $connection = $request->getConnection();
        $url = $connection->hasConfig('url')
            ? $connection->getConfig('url')
            : "http://{$connection->getHost()}:{$connection->getPort()}/{$connection->getPath()}"
        ;

        if ($action = $request->getPath()) {
            $action = '/'.ltrim($action, '/');
        }

        $uri = $this->uriFactory->createUri($url.$action);

        if ($query = $request->getQuery()) {
            return $uri->withQuery(http_build_query($query));
        }

        return $uri;
    }
}