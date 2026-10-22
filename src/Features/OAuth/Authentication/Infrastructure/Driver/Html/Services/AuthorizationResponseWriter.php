<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\OAuth\Authentication\Infrastructure\Driver\Html\Services;

use Psr\Http\Message\ResponseInterface;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\OidcFlowContext;
use Civi\Lughauth\Features\OAuth\Authentication\Domain\ValueObject\ResponseMode;

/**
 * Single writer of authorization responses, successful or not, to the client's redirect URI (EP-08).
 *
 * The flow's redirect must already be validated for its client: an invalid one never reaches this
 * class and gets a local error instead. Every response carries the client's state (once, when it
 * sent one) and the tenant issuer as iss (RFC 9207), never the nonce. The mode is the flow's
 * response mode (OidcFlowContext::responseMode):
 *
 * - query and fragment: a 302 whose Location adds the parameters, encoded, to the redirect URI.
 * - form_post: an HTML page that POSTs them to the redirect URI (OAuth 2.0 Form Post Response
 *   Mode), with escaped fields and a CSP that only allows its own submit script and that target.
 */
final class AuthorizationResponseWriter
{
    private const string AUTO_SUBMIT = 'document.forms[0].submit();';

    /**
     * Writes a successful response with the given result parameters (code, tokens).
     *
     * @param array<string, int|string> $params
     */
    public function success(OidcFlowContext $flow, array $params, ResponseInterface $response): ResponseInterface
    {
        return $this->write($flow, $params, $response);
    }

    /** Writes an error response (RFC 6749 §4.1.2.1) with an OAuth error code. */
    public function error(OidcFlowContext $flow, string $error, ResponseInterface $response): ResponseInterface
    {
        return $this->write($flow, ['error' => $error], $response);
    }

    /**
     * HTML of a button that takes the user back to the client with an error, in the flow's
     * response mode, for pages that let the user decide when to leave.
     */
    public function errorButton(OidcFlowContext $flow, string $error, string $label): string
    {
        $mode = $flow->responseMode();
        $params = $this->withCorrelation($flow, ['error' => $error]);
        $label = self::escape($label);
        if ($mode !== ResponseMode::FormPost) {
            return '<a class="primary-button" href="' . self::escape($mode->redirectUri($flow->redirect, $params)) . '">' . $label . '</a>';
        }
        return $this->form($mode->redirectUri($flow->redirect, $params), $params, '<button class="primary-button" type="submit">' . $label . '</button>');
    }

    /** @param array<string, int|string> $params */
    private function write(OidcFlowContext $flow, array $params, ResponseInterface $response): ResponseInterface
    {
        $mode = $flow->responseMode();
        $params = $this->withCorrelation($flow, $params);
        if ($mode !== ResponseMode::FormPost) {
            return $response->withStatus(302)->withHeader('Location', $mode->redirectUri($flow->redirect, $params));
        }
        $response->getBody()->write(
            '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Submit</title></head><body>'
            . $this->form($mode->redirectUri($flow->redirect, $params), $params, '<noscript><button type="submit">Continue</button></noscript>')
            . '<script>' . self::AUTO_SUBMIT . '</script></body></html>'
        );
        $csp = "default-src 'none'; script-src 'sha256-" . base64_encode(hash('sha256', self::AUTO_SUBMIT, true)) . "'; "
            . 'form-action ' . self::formActionSource($flow->redirect) . "; base-uri 'none'; frame-ancestors 'none'";
        return $response->withStatus(200)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Content-Security-Policy', $csp)
            ->withHeader('Referrer-Policy', 'no-referrer');
    }

    /**
     * @param array<string, int|string> $params
     * @return array<string, int|string>
     */
    private function withCorrelation(OidcFlowContext $flow, array $params): array
    {
        if ($flow->state !== '') {
            $params['state'] = $flow->state;
        }
        $params['iss'] = $flow->issuer;
        return $params;
    }

    /** @param array<string, int|string> $params */
    private function form(string $action, array $params, string $submit): string
    {
        $fields = '';
        foreach ($params as $name => $value) {
            $fields .= '<input type="hidden" name="' . self::escape((string) $name) . '" value="' . self::escape((string) $value) . '"/>';
        }
        return '<form method="post" action="' . self::escape($action) . '">' . $fields . $submit . '</form>';
    }

    /**
     * CSP source for the redirect URI: its origin, or its scheme for a URI without host (a native
     * app's custom scheme). A relative redirect is on this server ('self').
     */
    private static function formActionSource(string $redirect): string
    {
        $parts = parse_url($redirect);
        $scheme = is_array($parts) ? strtolower($parts['scheme'] ?? '') : '';
        if (!preg_match('/^[a-z][a-z0-9+.-]*$/', $scheme)) {
            return "'self'";
        }
        $host = $parts['host'] ?? '';
        if ($host === '') {
            return $scheme . ':';
        }
        // A host with characters a CSP source cannot hold leaves only this server as target.
        if (!preg_match('/^[A-Za-z0-9.\-]+$|^\[[0-9A-Fa-f:.]+\]$/', $host)) {
            return "'self'";
        }
        return $scheme . '://' . $host . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
