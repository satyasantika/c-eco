<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\TestSession;

final readonly class SessionState
{
    /**
     * @param  array<string, mixed>|null  $result
     */
    public function __construct(
        public TestSession $session,
        public ?PresentedItem $item = null,
        public bool $duplicate = false,
        public ?array $result = null,
    ) {}

    public function isFinished(): bool
    {
        return $this->session->status === 'completed';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'session' => [
                'status' => $this->session->status,
                'sequence' => $this->item?->sequence,
                'items_administered' => $this->session->items_administered,
            ],
        ];

        if ($this->item !== null) {
            $payload['item'] = $this->item->toArray();
        }

        if ($this->duplicate) {
            $payload['duplicate'] = true;
        }

        if ($this->isFinished()) {
            $payload['finished'] = true;
            $payload['result'] = $this->result;
        }

        return $payload;
    }
}
