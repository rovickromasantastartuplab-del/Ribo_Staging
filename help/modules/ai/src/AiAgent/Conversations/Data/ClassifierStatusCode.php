<?php

namespace Ai\AiAgent\Conversations\Data;

class ClassifierStatusCode
{
    const Greeting = 'c01';
    const Thanks = 'c02';
    const Smalltalk = 'c03';
    const TransferToHuman = 'c05';
    const DetailsSupplied = 'c06';
    const Assistance = 'c07';

    public function __construct(protected string $code) {}

    public function isAssistance(): bool
    {
        return $this->code === self::Assistance;
    }

    public function isTransferToHuman(): bool
    {
        return $this->code === self::TransferToHuman;
    }

    public function isFlowIntent(): bool
    {
        return str_starts_with($this->code, 'f');
    }

    public function flowIntentCodeToIndex(): int
    {
        if (str_starts_with($this->code, 'f0')) {
            return (int) substr($this->code, 2);
        }

        return (int) substr($this->code, 1);
    }
}
