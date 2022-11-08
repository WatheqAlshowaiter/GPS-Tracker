<?php declare(strict_types=1);

namespace App\Services\Socket;

use Closure;
use Socket;
use stdClass;
use Throwable;
use App\Domains\DeviceMessage\Model\DeviceMessage as DeviceMessageModel;
use App\Services\Protocol\Resource\ResourceAbstract;

class Client
{
    /**
     * @return self
     */
    public static function new(): self
    {
        return new static(...func_get_args());
    }

    /**
     * @param \stdClass &$client
     * @param \Closure $handler
     *
     * @return self
     */
    public function __construct(protected stdClass &$client, protected Closure $handler)
    {
    }

    /**
     * @return bool
     */
    public function handle(): bool
    {
        $buffer = $this->readBuffer();

        if ($buffer === null) {
            return false;
        }

        if (empty($buffer)) {
            return true;
        }

        foreach ($this->readHandle($buffer) as $resource) {
            $this->readResource($resource);
        }

        return true;
    }

    /**
     * @return ?string
     */
    protected function readBuffer(): ?string
    {
        if ($this->isSocket($this->client->socket) === false) {
            return null;
        }

        if (($buffer = socket_read($this->client->socket, 2048)) === null) {
            return null;
        }

        return trim($buffer);
    }

    /**
     * @param string $buffer
     *
     * @return array
     */
    protected function readHandle(string $buffer): array
    {
        $this->client->timestamp = time();

        try {
            return ($this->handler)($buffer);
        } catch (Throwable $e) {
            $this->error($e);
        }

        return [];
    }

    /**
     * @param \App\Services\Protocol\Resource\ResourceAbstract $resource
     *
     * @return void
     */
    protected function readResource(ResourceAbstract $resource): void
    {
        $this->readResourceResponse($resource);
        $this->readResourceMessagesRead($resource);
        $this->readResourceMessagesWrite($resource);
    }

    /**
     * @param \App\Services\Protocol\Resource\ResourceAbstract $resource
     *
     * @return void
     */
    protected function readResourceResponse(ResourceAbstract $resource): void
    {
        socket_write($this->client->socket, $response = $resource->response(), strlen($response));
    }

    /**
     * @param \App\Services\Protocol\Resource\ResourceAbstract $resource
     *
     * @return void
     */
    protected function readResourceMessagesRead(ResourceAbstract $resource): void
    {
        if ($resource->format() !== 'sms') {
            return;
        }

        DeviceMessageModel::byDeviceSerial($resource->serial())
            ->whereSentAt(true)
            ->whereResponseAt(false)
            ->withDevice()
            ->orderByCreatedAtAsc()
            ->limit(1)
            ->get()
            ->each(fn ($message) => $this->readResourceMessageRead($resource, $message));
    }

    /**
     * @param \App\Services\Protocol\Resource\ResourceAbstract $resource
     * @param \App\Domains\DeviceMessage\Model\DeviceMessage $message
     *
     * @return void
     */
    protected function readResourceMessageRead(ResourceAbstract $resource, DeviceMessageModel $message): void
    {
        $message->response = $resource->body();
        $message->response_at = date('Y-m-d H:i:s');

        $message->save();
    }

    /**
     * @param \App\Services\Protocol\Resource\ResourceAbstract $resource
     *
     * @return void
     */
    protected function readResourceMessagesWrite(ResourceAbstract $resource): void
    {
        DeviceMessageModel::byDeviceSerial($resource->serial())
            ->whereSentAt(false)
            ->withDevice()
            ->get()
            ->each(fn ($message) => $this->readResourceMessageWrite($message));
    }

    /**
     * @param \App\Domains\DeviceMessage\Model\DeviceMessage $message
     *
     * @return void
     */
    protected function readResourceMessageWrite(DeviceMessageModel $message): void
    {
        $message->sent_at = date('Y-m-d H:i:s');
        $message->save();

        socket_write($this->client->socket, $response = $message->message(), strlen($response));
    }

    /**
     * @param mixed $socket
     *
     * @return bool
     */
    protected function isSocket(mixed $socket): bool
    {
        return $socket && ($socket instanceof Socket);
    }

    /**
     * @param \Throwable $e
     *
     * @return void
     */
    protected function error(Throwable $e): void
    {
        report($e);
    }
}
