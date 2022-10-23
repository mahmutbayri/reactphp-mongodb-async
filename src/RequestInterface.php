<?php

namespace Mahmutbayri\ReacthpMongodbAsync;

interface RequestInterface extends MessageInterface
{
    public function getMessageData($requestId);
}