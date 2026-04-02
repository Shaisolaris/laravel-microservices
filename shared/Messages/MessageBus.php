<?php

declare(strict_types=1);

namespace Shared\Messages;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Channel\AMQPChannel;

class MessageBus
{
    private ?AMQPStreamConnection $connection = null;
    private ?AMQPChannel $channel = null;

    public function __construct(
        private readonly string $host = 'rabbitmq',
        private readonly int $port = 5672,
        private readonly string $user = 'guest',
        private readonly string $password = 'guest',
        private readonly string $vhost = '/',
    ) {}

    private function getChannel(): AMQPChannel
    {
        if ($this->channel === null || !$this->channel->is_open()) {
            $this->connection = new AMQPStreamConnection(
                $this->host, $this->port, $this->user, $this->password, $this->vhost,
            );
            $this->channel = $this->connection->channel();
        }

        return $this->channel;
    }

    /**
     * Publish a message to an exchange.
     */
    public function publish(string $exchange, string $routingKey, array $data, string $type = 'topic'): void
    {
        $channel = $this->getChannel();
        $channel->exchange_declare($exchange, $type, false, true, false);

        $message = new AMQPMessage(
            json_encode([
                'event' => $routingKey,
                'data' => $data,
                'timestamp' => now()->toIso8601String(),
                'id' => bin2hex(random_bytes(16)),
            ]),
            [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            ],
        );

        $channel->basic_publish($message, $exchange, $routingKey);
    }

    /**
     * Subscribe to messages from an exchange.
     */
    public function subscribe(
        string $exchange,
        string $queue,
        string $routingKey,
        callable $handler,
        string $type = 'topic',
    ): void {
        $channel = $this->getChannel();
        $channel->exchange_declare($exchange, $type, false, true, false);
        $channel->queue_declare($queue, false, true, false, false);
        $channel->queue_bind($queue, $exchange, $routingKey);

        $channel->basic_qos(0, 1, false);
        $channel->basic_consume(
            $queue,
            '',
            false,
            false,
            false,
            false,
            function (AMQPMessage $message) use ($handler) {
                $data = json_decode($message->body, true);
                try {
                    $handler($data);
                    $message->ack();
                } catch (\Throwable $e) {
                    $message->nack(true); // requeue on failure
                    throw $e;
                }
            },
        );

        while ($channel->is_consuming()) {
            $channel->wait();
        }
    }

    /**
     * Publish to a direct queue (RPC-style).
     */
    public function publishToQueue(string $queue, array $data): void
    {
        $channel = $this->getChannel();
        $channel->queue_declare($queue, false, true, false, false);

        $message = new AMQPMessage(
            json_encode($data),
            ['content_type' => 'application/json', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT],
        );

        $channel->basic_publish($message, '', $queue);
    }

    public function close(): void
    {
        $this->channel?->close();
        $this->connection?->close();
        $this->channel = null;
        $this->connection = null;
    }

    public function __destruct()
    {
        $this->close();
    }
}
