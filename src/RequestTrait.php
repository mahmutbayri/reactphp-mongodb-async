<?php

namespace Mahmutbayri\ReacthpMongodbAsync;

trait RequestTrait
{
    public function getMessageData($requestId)
    {
        $data = $this->getMessageDataAfterHeader();

        $header = pack('V4', MessageInterface::MSG_HEADER_SIZE + strlen($data), $requestId, 0, $this->getOpCode());

        return $header . $data;
    }

    abstract protected function getMessageDataAfterHeader();
}
