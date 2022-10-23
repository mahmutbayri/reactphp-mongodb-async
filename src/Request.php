<?php

namespace Mahmutbayri\ReacthpMongodbAsync;

use React\Promise\Deferred;

class Request extends Deferred
{
    private $requestId;

    public function __construct($requestId)
    {
        $this->requestId = $requestId;
        parent::__construct(null);
    }

    public function getRequestId()
    {
        return $this->requestId;
    }

    public function handleReply(Reply $reply)
    {
        // TODO: Check reply for error and call Deferred::reject()

        $this->resolve($reply);
    }
}