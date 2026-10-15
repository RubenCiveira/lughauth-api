<?php

declare(strict_types=1);

use Civi\Lughauth\Features\Access\Tenant\Application\Usecase\List\TenantListUsecase;
use Civi\Lughauth\Features\Access\Tenant\Domain\Gateway\TenantAttributesSlide;
use Civi\Lughauth\Features\Access\Tenant\Domain\Gateway\TenantCursor;
use Civi\Lughauth\Features\Access\Tenant\Domain\TenantAttributes;
use Civi\Lughauth\Features\Access\Tenant\Infrastructure\Driver\Rest\TenantListController;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class TenantListControllerNextLinkUnitTest extends TestCase
{
    public function test_next_link_keeps_request_and_moves_the_keyset(): void
    {
        $next = new TenantCursor(limit: 2, sinceUid: 'u5', sinceName: 'bravo', sinceDomain: 'c.example', order: ['nameAsc']);
        $body = $this->list('limit=2&order=name-asc&search=a&since-name=alpha&since-uid=u2', $next, 2, true);

        parse_str(substr($body['next'], 1), $link);
        $this->assertSame([
            'limit' => '2',
            'order' => 'name-asc',
            'search' => 'a',
            'since-name' => 'bravo',
            'since-uid' => 'u5',
            'since-domain' => 'c.example',
        ], $link);
    }

    public function test_no_next_link_on_a_short_page(): void
    {
        $body = $this->list('limit=2&order=name-asc', new TenantCursor(limit: 2, sinceUid: 'u5', sinceName: 'bravo'), 1);

        $this->assertArrayNotHasKey('next', $body);
    }

    public function test_no_next_link_on_an_exactly_full_last_page(): void
    {
        $body = $this->list('limit=2&order=name-asc', new TenantCursor(limit: 2, sinceUid: 'u5', sinceName: 'bravo'), 2);

        $this->assertCount(2, $body['items']);
        $this->assertArrayNotHasKey('next', $body);
    }

    public function test_limit_must_be_a_positive_integer(): void
    {
        $usecase = $this->createMock(TenantListUsecase::class);
        $usecase->expects($this->never())->method('list');
        $controller = new TenantListController($usecase);
        $controller->setLogger(new NullLogger());
        foreach (['0', '-1', 'abc', '2.5', ''] as $limit) {
            $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/access/tenants')
                ->withQueryParams(['limit' => $limit]);
            $response = $controller->list($request, (new ResponseFactory())->createResponse(), []);

            $this->assertSame(400, $response->getStatusCode());
            $this->assertSame(['error' => 'invalid_limit'], json_decode((string) $response->getBody(), true));
        }
    }

    private function list(string $query, TenantCursor $next, int $items, bool $hasNext = false): array
    {
        parse_str($query, $params);
        $usecase = $this->createStub(TenantListUsecase::class);
        $usecase->method('list')->willReturn(new TenantAttributesSlide($next, array_fill(0, $items, new TenantAttributes()), $hasNext));
        $controller = new TenantListController($usecase);
        $controller->setLogger(new NullLogger());

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/access/tenants?' . $query)->withQueryParams($params);
        $response = $controller->list($request, (new ResponseFactory())->createResponse(), []);

        return json_decode((string) $response->getBody(), true);
    }
}
