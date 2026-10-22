<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Domain\ValueObject;

/**
 * How the authorization response reaches the client (OAuth 2.0 Multiple Response Type Encoding
 * Practices and Form Post Response Mode). JARM (054) will be one more case.
 */
enum ResponseMode: string
{
    case Query = 'query';
    case Fragment = 'fragment';
    case FormPost = 'form_post';

    /** Default mode: the query for a code alone, the fragment once tokens are returned. */
    public static function defaultFor(ResponseType $responseType): self
    {
        return $responseType->issuesTokens() ? self::Fragment : self::Query;
    }

    /**
     * The requested mode, or the default when none is requested. Null for an unknown mode and for
     * the query with tokens, which would expose them to the redirect target's server and logs.
     */
    public static function resolve(ResponseType $responseType, ?string $requested): ?self
    {
        if ($requested === null || $requested === '') {
            return self::defaultFor($responseType);
        }
        $mode = self::tryFrom($requested);
        return $mode === self::Query && $responseType->issuesTokens() ? null : $mode;
    }

    /**
     * The redirect URI with the parameters encoded (RFC 3986) after the query or fragment it
     * already has. A registered parameter named like a response parameter (e.g. state) is dropped,
     * so each one is sent once and only with its response value. form_post sends the parameters
     * in the body, so its URI only loses those registered duplicates.
     *
     * @param array<string, int|string> $params
     */
    public function redirectUri(string $redirect, array $params): string
    {
        if ($params === []) {
            return $redirect;
        }
        [$base, $fragment] = array_pad(explode('#', $redirect, 2), 2, null);
        [$path, $query] = array_pad(explode('?', $base, 2), 2, null);
        $query = self::without($query, $params);
        $fragment = self::without($fragment, $params);
        $encoded = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        if ($this === self::Query) {
            $query = self::append($query, $encoded);
        } elseif ($this === self::Fragment) {
            $fragment = self::append($fragment, $encoded);
        }
        return $path . ($query !== null ? '?' . $query : '') . ($fragment !== null ? '#' . $fragment : '');
    }

    /**
     * The URI component without the parameters named like a response parameter; null when none is left.
     *
     * @param array<string, int|string> $params
     */
    private static function without(?string $component, array $params): ?string
    {
        if ($component === null || $component === '') {
            return $component;
        }
        $kept = array_filter(
            explode('&', $component),
            static fn (string $pair): bool => $pair !== '' && !array_key_exists(urldecode(explode('=', $pair, 2)[0]), $params)
        );
        return $kept === [] ? null : implode('&', $kept);
    }

    private static function append(?string $component, string $encoded): string
    {
        return $component === null || $component === '' ? $encoded : $component . '&' . $encoded;
    }
}
