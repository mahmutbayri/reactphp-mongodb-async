<?php

namespace Mahmutbayri\ReacthpMongodbAsync;

use Evenement\EventEmitter;
use React\Stream\DuplexStreamInterface;
use Exception;
use RuntimeException;
use SplQueue;
use UnderflowException;
use UnexpectedValueException;

class Connection extends EventEmitter
{
    private $ending;
    private $responseParser;
    private $requestId;
    private $requestQueue;
    private $stream;

    public function __construct(DuplexStreamInterface $stream)
    {

        $this->requestQueue = new SplQueue();
        $this->responseParser = new ResponseParser();
        $this->stream = $stream;

        $stream->on('data', function ($data) {
            try {
                $replies = $this->responseParser->pushAndGetParsed($data);

                foreach ($replies as $reply) {
                    $this->handleReply($reply);
                }
            } catch (RuntimeException $e) {
                $this->emit('error', [$e]);
                $this->close();
                return;
            }
        });

        $stream->on('close', function () {
            $this->close();
            $this->emit('close');
        });

        $stream->on('error', function (Exception $e, DuplexStreamInterface $stream) {
            $this->emit('error', [$e, $stream]);
            $this->close();
        });

        $stream->on('end', function () {
            echo 'END';
        });

        $stream->on('error', function (Exception $e) {
            echo 'Error: ' . $e->getMessage() . PHP_EOL;
        });
    }

    public function close()
    {
        $this->ending = true;

        $this->stream->close();

        // reject all remaining requests in the queue
        while (! $this->requestQueue->isEmpty()) {
            $request = $this->requestQueue->dequeue();
            $request->reject(new RuntimeException('Connection closed'));
        }
    }

    public function end()
    {
        $this->ending = true;

        if ($this->requestQueue->isEmpty()) {
            $this->close();
        }
    }

    public function handleReply(Reply $reply)
    {
        $this->emit('message', [$reply, $this]);

        if ($this->requestQueue->isEmpty()) {
            throw new UnderflowException('Unexpected reply received; request queue is empty');
        }

        $request = $this->requestQueue->dequeue();

        if (! $reply->isResponseTo($request->getRequestId())) {
            throw new UnexpectedValueException(sprintf('Request ID (%d) does not match reply (%d)', $request->getRequestId(), $reply->getResponseTo()));
        }

        $request->handleReply($reply);

        if ($this->ending && $this->requestQueue->isEmpty()) {
            $this->close();
        }
    }

    public function send(RequestInterface $requestMessage)
    {
        if ($this->ending) {
            return \React\Promise\reject(new RuntimeException('Connection closed'));
        }

        // TODO: Ensure generated request IDs are unique per host until rollover
        $requestId = ++$this->requestId;

//        $filter = new \stdClass();
//        $filter->{'$where'} = 'sleep(100) || true';
//        $sections = \MongoDB\BSON\fromPHP(['find' => 'test-connection', 'filter' => $filter, '$db' => 'test-database']);
//        $message = pack('V*', 21 + strlen($sections), 1, 0, 1234) . "\0" . $sections;

        $this->stream->write($requestMessage->getMessageData($requestId));
//        $this->stream->write($message);


        $deferredRequest = new Request($requestId);

        $this->requestQueue->enqueue($deferredRequest);

        return $deferredRequest->promise();
    }
}