<?php declare(strict_types=1);

namespace App\Command;

use App\Entity\Trailer;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class FetchDataCommand extends Command
{
    private const SOURCE = 'https://trailers.apple.com/trailers/home/rss/newtrailers.rss';

    private const TRAILERS_LIMIT = 10;

    protected static $defaultName = 'fetch:trailers';

    private string $source;

    /**
     * FetchDataCommand constructor.
     *
     * @param ClientInterface        $httpClient
     * @param LoggerInterface        $logger
     * @param EntityManagerInterface $doctrine
     * @param string|null            $name
     */
    public function __construct(
        private ClientInterface $httpClient,
        private LoggerInterface $logger,
        private EntityManagerInterface $doctrine,
        string $name = null
    )
    {
        parent::__construct($name);
    }

    public function configure(): void
    {
        $this
            ->setDescription('Fetch data from iTunes Movie Trailers')
            ->addArgument('source', InputArgument::OPTIONAL, 'Overwrite source')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->logger->info(sprintf('Start %s at %s', __CLASS__, date_create()->format(DATE_ATOM)));

        $source = $input->getArgument('source') ?? self::SOURCE;

        if (!is_string($source)) {
            throw new RuntimeException('Source must be string');
        }

        $io = new SymfonyStyle($input, $output);
        $io->title(sprintf('Fetch data from %s', $source));

        try {
            $response = $this->httpClient->sendRequest(new Request('GET', $source));
        } catch (ClientExceptionInterface $e) {
            throw new RuntimeException($e->getMessage());
        }
        if (($status = $response->getStatusCode()) !== 200) {
            throw new RuntimeException(sprintf('Response status is %d, expected %d', $status, 200));
        }
        $data = $response->getBody()->getContents();
        $this->processXml($data);

        $this->logger->info(sprintf('End %s at %s', __CLASS__, date_create()->format(DATE_ATOM)));

        return 0;
    }

    protected function processXml(string $data): void
    {
        try {
            $xml = (new \SimpleXMLElement($data))->children();
        } catch (\Exception) {
            throw new RuntimeException('Could not parse the xml from source');
        }

        if (!property_exists($xml, 'channel')) {
            throw new RuntimeException('Could not find \'channel\' element in feed');
        }

        // TODO: refactor this logic
        $existingTrailers = $this->getAllTrailers();
        $existingTrailersCount = count($existingTrailers);
        $existingTrailerTitles = array_map(fn($trailer) => $trailer->getTitle(), $existingTrailers);

        foreach ($xml->channel->item as $item) {
            // TODO: seems like it is impossible to control this limit by constructing uri. But bet there is a better way.
            if ($existingTrailersCount >= self::TRAILERS_LIMIT) {
                $this->logger->info('Movie Trailers count limit reached');
                break;
            }

            if (in_array($item->title, $existingTrailerTitles)) {
                $this->logger->info('Movie Trailer found', ['title' => $item->title]);
                continue;
            }

            $this->logger->info('Create new Movie Trailer', ['title' => $item->title]);

            // TODO: find a better way for parsing cdata
            preg_match(
                '/https[^<>]+jpg/',
                $item->children('content', true)->asXML(),
                $imageLinks
            );

            $trailer = new Trailer();
            $trailer
                ->setTitle((string) $item->title)
                ->setDescription((string) $item->description)
                ->setLink((string) $item->link)
                ->setImage($imageLinks[0] ?? null)
            ;

            try {
                $trailer->setPubDate($this->parseDate((string) $item->pubDate));
            } catch (\Exception $e) {
                $this->logger->warning($e->getMessage(), ['title' => $item->title]);
            }

            $this->doctrine->persist($trailer);

            $existingTrailersCount++;
        }

        $this->doctrine->flush();
    }

    /**
     * @throws \Exception
     */
    protected function parseDate(string $date): \DateTime
    {
        return new \DateTime($date);
    }

    protected function getAllTrailers(): array
    {
        return $this->doctrine->getRepository(Trailer::class)->findAll();
    }
}
