<?php

declare(strict_types=1);

namespace App\Tenancy\Infrastructure\Http;

use App\Tenancy\Application\Command\OAuth\BeginOAuth\BeginOAuthCommand;
use App\Tenancy\Application\Command\OAuth\CompleteOAuth\CompleteOAuthCommand;
use App\Tenancy\Domain\Exception\InvalidOAuthCallbackException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/shopify')]
final class ShopifyOAuthController extends AbstractController
{
    use HandleTrait;

    public function __construct(MessageBusInterface $messageBus)
    {
        $this->messageBus = $messageBus;
    }

    #[Route('/install', name: 'shopify_oauth_install', methods: ['GET'])]
    public function install(Request $request): Response
    {
        $shopDomain = $request->query->getString('shop');

        /** @var string $authorizationUrl */
        $authorizationUrl = $this->handle(new BeginOAuthCommand($shopDomain));

        return $this->redirect($authorizationUrl);
    }

    #[Route('/callback', name: 'shopify_oauth_callback', methods: ['GET'])]
    public function callback(Request $request): Response
    {
        /** @var array<string, string> $params */
        $params = $request->query->all();

        try {
            $this->handle(new CompleteOAuthCommand(
                shopDomain: $params['shop'] ?? '',
                code: $params['code'] ?? '',
                state: $params['state'] ?? '',
                hmac: $params['hmac'] ?? '',
                queryParams: $params,
            ));
        } catch (InvalidOAuthCallbackException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        return $this->redirectToRoute('app_home');
    }
}
