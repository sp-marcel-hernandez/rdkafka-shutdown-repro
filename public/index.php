<?php

use Nyholm\Psr7\Response;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Slim\Factory\AppFactory;
use Slim\Logger;
use Slim\Middleware\ContentLengthMiddleware;
use Symfony\Component\Stopwatch\Stopwatch;
use UMA\DIC\Container;

require_once __DIR__ . '/../vendor/autoload.php';

$clock = new Stopwatch(true);
$clock->start('all');

$cnt = new Container([
    'config' => [
        'kafka' => [
            'events' => 100000,
            'endpoint' => 'kafka:9092',
            'topic' => 'demo_topic'
        ]
    ],

    LoggerInterface::class => static function(): LoggerInterface {
        return new Logger();
    },

    RdKafka\Conf::class => static function (ContainerInterface $c): RdKafka\Conf {
        $conf = new RdKafka\Conf();
        $conf->set('metadata.broker.list', $c->get('config')['kafka']['endpoint']);

        return $conf;
    },

    RdKafka\Producer::class => static function (ContainerInterface $c): RdKafka\Producer {
        return new RdKafka\Producer($c->get(RdKafka\Conf::class));
    },

    RdKafka\ProducerTopic::class => static function (ContainerInterface $c): RdKafka\ProducerTopic {
        /** @var RdKafka\Producer $producer */
        $producer = $c->get(RdKafka\Producer::class);

        return $producer->newTopic($c->get('config')['kafka']['topic']);
    },

    'index' => static function(ContainerInterface $c) use ($clock): RequestHandlerInterface {
        return new class(
            $c->get('config')['kafka']['events'],
            $clock,
            $c->get(LoggerInterface::class),
            $c->get(RdKafka\Producer::class),
            $c->get(RdKafka\ProducerTopic::class)
        ) implements RequestHandlerInterface {
            /** @var int */
            private $events;
            /** @var Stopwatch */
            private $clock;
            /** @var LoggerInterface */
            private $logger;
            /** @var \RdKafka\Producer */
            private $producer;
            /** @var \RdKafka\ProducerTopic */
            private $topic;
            /** @var array<string, float>s */
            private $times;

            public function __construct(int $events, Stopwatch $clock, LoggerInterface $logger, RdKafka\Producer $producer, RdKafka\ProducerTopic $topic)
            {
                $this->events = $events;
                $this->clock = $clock;
                $this->logger = $logger;
                $this->producer = $producer;
                $this->topic = $topic;
                $this->times = [];
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->clock->start('handler');

                $this->clock->start('produce');
                for ($i = 0; $i < $this->events; $i++) {
                    $this->topic->produce(RD_KAFKA_PARTITION_UA, 0, $i);
                }
                $this->times["produce $this->events messages (ms): "] = $this->clock->stop('produce')->getDuration();

                $this->clock->start('flush');
                $this->producer->flush(PHP_INT_MAX);
                $this->times['flush (ms): '] = $this->clock->stop('flush')->getDuration();

                $response = new Response(200, ['Content-Type' => 'text/plain'], "Published $this->events messages" . PHP_EOL);

                $this->times['handler (ms): '] = $this->clock->stop('handler')->getDuration();

                return $response;
            }

            public function logTimings(): void
            {
                foreach ($this->times as $key => $value) {
                    $this->logger->log(LogLevel::INFO, $key . $value);
                }
            }
        };
    }
]);

$app = AppFactory::create(null, $cnt);

$app->get('/', 'index');

$app->add(new ContentLengthMiddleware());

$app->run();

// Write logs out of band
fastcgi_finish_request();
$all = $clock->stop('all')->getDuration();

$cnt->get('index')->logTimings();
$cnt->get(LoggerInterface::class)->log(LogLevel::INFO, 'all (ms): ' . $all);
