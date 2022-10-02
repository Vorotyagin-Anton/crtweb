<?php declare(strict_types=1);

namespace App\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpInternalServerErrorException;
use Twig\Environment;

class HomeController
{
    public function __construct(private Environment $twig)
    {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $data = $this->twig->render('home/index.html.twig');
        } catch (\Exception $e) {
            throw new HttpInternalServerErrorException($request, $e->getMessage(), $e);
        }

        $response->getBody()->write($data);

        return $response;
    }
}
