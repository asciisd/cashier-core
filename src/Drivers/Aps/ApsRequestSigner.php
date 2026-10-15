<?php

declare(strict_types=1);

namespace Asciisd\CashierCore\Drivers\Aps;

use Asciisd\CashierCore\Exceptions\PaymentProcessingException;
use OpenSSLAsymmetricKey;
use Psr\Http\Message\RequestInterface;

/**
 * Signs outbound APS requests with the merchant's ES256 (P-256) private key.
 *
 * APS verifies `X-Signature` over TIMESTAMP + METHOD + URL + BODY, rebuilding
 * the URL from the request it received and comparing character by character.
 * Signing runs as request middleware, on the PSR-7 request about to go out, so
 * the bytes signed are the bytes sent — no re-serialisation can sit between.
 *
 * The URL is normalised before signing, and the normalised URL is what goes
 * on the wire: https scheme, lower-case host, query names lower-cased and
 * sorted. APS requires the sort; PHP does not guarantee the order an array
 * becomes a query string in.
 */
class ApsRequestSigner
{
    private readonly OpenSSLAsymmetricKey $privateKey;

    /**
     * @param  string  $privateKey  PEM contents, PEM with `\n` escapes (as an env
     *                              var holds it), or a path to a PEM file
     */
    public function __construct(
        private readonly string $appToken,
        string $privateKey,
    ) {
        $pem = str_contains($privateKey, '-----BEGIN')
            ? str_replace('\n', "\n", $privateKey)
            : (is_file($privateKey) ? (string) file_get_contents($privateKey) : $privateKey);

        $key = openssl_pkey_get_private($pem);

        if ($key === false) {
            throw new PaymentProcessingException('APS private key could not be loaded.');
        }

        $this->privateKey = $key;
    }

    public function __invoke(RequestInterface $request): RequestInterface
    {
        return $this->sign($request);
    }

    public function sign(RequestInterface $request, ?string $timestamp = null): RequestInterface
    {
        $timestamp ??= gmdate('Y-m-d\TH:i:s\Z');

        $uri = $request->getUri()
            ->withScheme('https')
            ->withHost(strtolower($request->getUri()->getHost()))
            ->withQuery(self::normalizeQuery($request->getUri()->getQuery()))
            ->withFragment('');

        $request = $request->withUri($uri, preserveHost: false);
        $body = (string) $request->getBody();
        $request->getBody()->rewind();

        $signedData = $timestamp.strtoupper($request->getMethod()).$uri.$body;

        if (! openssl_sign($signedData, $signature, $this->privateKey, OPENSSL_ALGO_SHA256)) {
            throw new PaymentProcessingException('APS request could not be signed.');
        }

        return $request
            ->withoutHeader('X-App-Secret')
            ->withHeader('X-App-Token', $this->appToken)
            ->withHeader('X-Sign-Timestamp', $timestamp)
            ->withHeader('X-Signature', base64_encode($signature));
    }

    /**
     * Lower-case each name and sort by it; values stay exactly as encoded.
     */
    public static function normalizeQuery(string $query): string
    {
        $params = [];

        foreach (explode('&', $query) as $pair) {
            if ($pair === '') {
                continue;
            }

            [$name, $value] = array_pad(explode('=', $pair, 2), 2, '');
            $params[strtolower($name)] = $value;
        }

        ksort($params, SORT_STRING);

        return implode('&', array_map(
            fn (string $name, string $value) => "{$name}={$value}",
            array_keys($params),
            $params,
        ));
    }
}
