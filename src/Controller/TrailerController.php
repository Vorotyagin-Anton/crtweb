<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Trailer;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpInternalServerErrorException;
use Slim\Exception\HttpNotFoundException;
use Slim\Interfaces\RouteCollectorInterface;
use Twig\Environment;

class TrailerController
{
    public function __construct(private Environment $twig, private EntityManagerInterface $em)
    {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        try {
            $data = $this->twig->render('trailer/trailers.html.twig', [
                'trailers' => $this->fetchAllTrailers(),
            ]);
        } catch (\Exception $e) {
            throw new HttpInternalServerErrorException($request, $e->getMessage(), $e);
        }

        $response->getBody()->write($data);

        return $response;
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // TODO: get param another way
        $id = (int) $request->getAttribute('id');
        $trailer = $this->fetchOneTrailerById($id);

        if (!isset($trailer)) {
            throw new HttpNotFoundException($request);
        }

        try {
            $data = $this->twig->render('trailer/trailer.html.twig', [
                'trailer' => $trailer,
            ]);
        } catch (\Exception $e) {
            throw new HttpInternalServerErrorException($request, $e->getMessage(), $e);
        }

        $response->getBody()->write($data);

        return $response;
    }

    protected function fetchAllTrailers(): Collection
    {
        $data = $this->em->getRepository(Trailer::class)->findAll();

        return new ArrayCollection($data);
    }

    protected function fetchOneTrailerById(int $id): ?Trailer
    {
        return $this->em->getRepository(Trailer::class)->findOneBy(['id' => $id]);
    }
}
