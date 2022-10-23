<?php declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Mahmutbayri\ReacthpMongodbAsync\Connection;
use Mahmutbayri\ReacthpMongodbAsync\Query;
use Mahmutbayri\ReacthpMongodbAsync\Reply;
use React\Dns\Resolver\Factory as DnsFactory;
use React\EventLoop\Loop;
use React\Promise\PromiseInterface;
use React\Socket\Connector;
use React\Stream\DuplexStreamInterface;

$host = $_SERVER['HOST'] ?? '127.0.0.1';
$port = $_SERVER['PORT'] ?? '27017';

$connectionObject = null;

$loop = Loop::get();
$factory = new DnsFactory();

$resolver = $factory->create('8.8.8.8', $loop);
$connector = new Connector([$resolver], $loop);;

$connector
    ->connect(sprintf('%s:%s', $host, $port))
    ->then(function (DuplexStreamInterface $stream) {
        return new Connection($stream);
    })
    ->then(
        function (Connection $connection) use (&$connectionObject) {
            $connectionObject = $connection;

            $connection->on('close', function () {
                dump("# connection closed\n");
            });

            $connection->on('error', function (Exception $e) {
                dump("# connection error: %s\n", $e->getMessage());
                dump("%s\n", $e->getTraceAsString());
            });

//        $connection->on('message', function (Mahmutbayri\ReacthpMongodbAsync\Reply $reply) {
//            dump("# received reply of size: %d\n", $reply->getMessageLength());
//        });

        },
        function (Exception $e) {
            dump("# connection error 2: %s\n", $e->getMessage());
            dump("%s\n", $e->getTraceAsString());
        }
    );

$loop->addPeriodicTimer(2, function () use (&$connectionObject) {
    if (is_null($connectionObject)) {
        dump('$connectionObject is not ready');
        return;
    }

    $message = new Query('admin.$cmd', ['hello' => 1], null, 0, 1);

    $connectionObject
        ->send($message)
        ->then(
            function (Reply $reply) {
                foreach ($reply as $document) {
                    /** @var \MongoDB\BSON\UTCDateTime $localTime */
                    $localTime = $document->localTime;
                    dump($localTime->toDateTime()->format(DATE_ISO8601));
                }
            },
            function (Exception $e) {
                dump("# query error 1: %s\n", $e->getMessage());
                dump("%s\n", $e->getTraceAsString());
            }
        );
});

$loop->run();
