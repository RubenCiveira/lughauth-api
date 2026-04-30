<?php

declare(strict_types=1);

namespace Civi\Lughauth\Features\Oidc\Profile\Infrastructure\Driver\Html;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Civi\Lughauth\Shared\Context;
use Civi\Lughauth\Shared\Infrastructure\Sql\SqlTemplate;
use Civi\Lughauth\Shared\Observability\LoggerAwareTrait;
use Civi\Lughauth\Shared\Observability\TracerAwareTrait;
use Civi\Lughauth\Features\Oidc\Authentication\Application\SessionManager;
use Civi\Lughauth\Features\Oidc\Profile\Domain\OidcProfileData;
use Civi\Lughauth\Features\Oidc\Profile\Domain\Gateway\ProfileGateway;
use Civi\Lughauth\Features\Oidc\Profile\Infrastructure\Driver\Html\Panels\ProfileEditPanel;
use Civi\Lughauth\Features\Oidc\Profile\Infrastructure\Driver\Html\Panels\ProfileViewPanel;
use Civi\Lughauth\Features\Oidc\Theme\Application\DecorateHtml;

class ProfileHtml
{
    use LoggerAwareTrait;
    use TracerAwareTrait;

    public function __construct(
        private readonly Context $context,
        private readonly SqlTemplate $sql,
        private readonly SessionManager $sessions,
        private readonly ProfileGateway $gateway,
        private readonly DecorateHtml $decorator,
        private readonly ProfileViewPanel $viewPanel,
        private readonly ProfileEditPanel $editPanel,
    ) {
    }

    public function view(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->logDebug("Profile view page");
        $span = $this->startSpan("Profile view page");
        try {
            $tenant = $args['tenant'];
            $session = $this->loadSession($request, $tenant);
            if ($session === null) {
                return $this->renderUnauthenticated($request, $response, $tenant);
            }
            $profile = $this->gateway->findByUser($session->userId);
            $editUrl = $this->context->getBaseUrl() . '/oauth/openid/' . $tenant . '/me/edit';
            $html = $this->viewPanel->render($profile, $editUrl);
            $locale = $profile?->locale ?? '';
            $response->getBody()->write($this->decorator->getFullPage($request, 'My profile', $html, $locale));
            return $response;
        } catch (Throwable $ex) {
            $span->recordException($ex);
            throw $ex;
        } finally {
            $span->end();
        }
    }

    public function edit(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->logDebug("Profile edit page");
        $span = $this->startSpan("Profile edit page");
        try {
            $tenant = $args['tenant'];
            $session = $this->loadSession($request, $tenant);
            if ($session === null) {
                return $this->renderUnauthenticated($request, $response, $tenant);
            }
            $profile = $this->gateway->findByUser($session->userId);
            $saveUrl = $this->context->getBaseUrl() . '/oauth/openid/' . $tenant . '/me/edit';
            $cancelUrl = $this->context->getBaseUrl() . '/oauth/openid/' . $tenant . '/me';
            $html = $this->editPanel->render($profile, $saveUrl, $cancelUrl);
            $locale = $profile?->locale ?? '';
            $response->getBody()->write($this->decorator->getFullPage($request, 'Edit profile', $html, $locale));
            return $response;
        } catch (Throwable $ex) {
            $span->recordException($ex);
            throw $ex;
        } finally {
            $span->end();
        }
    }

    public function save(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $this->logDebug("Profile save");
        $span = $this->startSpan("Profile save");
        $this->sql->begin();
        try {
            $tenant = $args['tenant'];
            $session = $this->loadSession($request, $tenant);
            if ($session === null) {
                return $this->renderUnauthenticated($request, $response, $tenant);
            }
            $data = $this->readData($request);
            $this->gateway->save($session->userId, $data);
            $this->sql->commit();
            $viewUrl = $this->context->getBaseUrl() . '/oauth/openid/' . $tenant . '/me';
            return $response->withStatus(302)->withHeader('Location', $viewUrl);
        } catch (Throwable $ex) {
            $span->recordException($ex);
            $session = isset($session) ? $session : null;
            if ($session !== null) {
                $tenant = $args['tenant'];
                $profile = $this->gateway->findByUser($session->userId);
                $saveUrl = $this->context->getBaseUrl() . '/oauth/openid/' . $tenant . '/me/edit';
                $cancelUrl = $this->context->getBaseUrl() . '/oauth/openid/' . $tenant . '/me';
                $html = $this->editPanel->render($profile, $saveUrl, $cancelUrl, $ex->getMessage());
                $response->getBody()->write($this->decorator->getFullPage($request, 'Edit profile', $html, ''));
                return $response->withStatus(422);
            }
            throw $ex;
        } finally {
            $this->sql->close();
            $span->end();
        }
    }

    private function loadSession(ServerRequestInterface $request, string $tenant): ?\Civi\Lughauth\Features\Oidc\Session\Domain\SessionInfo
    {
        $cookies = $request->getCookieParams();
        $sessionId = $cookies['AUTH_SESSION_ID_' . strtoupper($tenant)] ?? null;
        if ($sessionId === null || $sessionId === '') {
            return null;
        }
        return $this->sessions->loadSession($sessionId, '', '');
    }

    private function renderUnauthenticated(ServerRequestInterface $request, ResponseInterface $response, string $tenant): ResponseInterface
    {
        $loginUrl = htmlspecialchars($this->context->getBaseUrl() . '/oauth/openid/' . $tenant . '/authorize');
        $html = <<<HTML
            <h1>Not authenticated</h1>
            <p>You need to be logged in to view your profile.</p>
            <p><a class="primary-button" href="{$loginUrl}">Go to login</a></p>
            HTML;
        $response->getBody()->write($this->decorator->getFullPage($request, 'My profile', $html, ''));
        return $response->withStatus(401);
    }

    private function readData(ServerRequestInterface $request): OidcProfileData
    {
        $parsed = $request->getParsedBody() ?? [];
        $body = is_array($parsed) ? $parsed : get_object_vars($parsed);
        return new OidcProfileData(
            givenName: isset($body['givenName']) ? (string) $body['givenName'] : null,
            familyName: isset($body['familyName']) ? (string) $body['familyName'] : null,
            middleName: isset($body['middleName']) ? (string) $body['middleName'] : null,
            nickname: isset($body['nickname']) ? (string) $body['nickname'] : null,
            preferredUsername: isset($body['preferredUsername']) ? (string) $body['preferredUsername'] : null,
            pictureUrl: isset($body['pictureUrl']) ? (string) $body['pictureUrl'] : null,
            websiteUrl: isset($body['websiteUrl']) ? (string) $body['websiteUrl'] : null,
            gender: isset($body['gender']) ? (string) $body['gender'] : null,
            birthdate: isset($body['birthdate']) ? (string) $body['birthdate'] : null,
            zoneinfo: isset($body['zoneinfo']) ? (string) $body['zoneinfo'] : null,
            locale: isset($body['locale']) ? (string) $body['locale'] : null,
            phoneNumber: isset($body['phoneNumber']) ? (string) $body['phoneNumber'] : null,
            phoneNumberVerified: null,
            addressJson: null,
        );
    }
}
